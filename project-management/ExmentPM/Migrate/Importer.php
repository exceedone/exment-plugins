<?php

namespace App\Plugins\ExmentPM\Migrate;

use Exceedone\Exment\Model\Define;
use Exceedone\Exment\Services\ZipService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Data migration, originally written by TTM as the separate PmMigrate plugin
 * and folded into PmPortal in 1.2.0.
 *
 * Writes a bundle into this Exment.
 *
 * Rows are inserted with the ids they had, straight into the tables, so no
 * Eloquent event, plugin validator, notification or auto number runs and every
 * created_at, revision and comment stays as it was. That only works on tables
 * that are empty, which is checked first; the one exception is the master
 * lists (status, issue type, priority, resolution), which the portal may have
 * seeded and which are compared with the bundle instead.
 *
 * Every run is a dry run unless told otherwise: the same checks and the same
 * row-by-row mapping happen, but nothing is written. An execute run stops
 * before the first write if the dry run found an error, writes inside one
 * transaction, and removes the files it copied if that transaction fails.
 */
class Importer
{
    const CHUNK = 200;

    const MASTER_ABORT = 'abort';
    const MASTER_REPLACE = 'replace';

    /** Revision keys the user mapping is known to cover. */
    const KNOWN_REVISION_KEYS = ['value', 'deleted_at'];

    /** @var Bundle */
    protected $bundle;

    /** @var array<string, mixed> */
    protected $manifest = [];

    /** @var array<string, string> role => table_name here */
    protected $roles = [];

    /** @var array<string, array<string, mixed>> table_name here => description */
    protected $descs = [];

    /** @var array<string, string> table_name in the bundle => table_name here */
    protected $tableMap = [];

    /** @var array<string, int> source user id => user id here */
    protected $userMap = [];

    /** @var array<string, string> source user id => user_code here, for the report */
    protected $userTargetCodes = [];

    /** @var int|null */
    protected $defaultUserId = null;

    /**
     * Set when the checks have already found an error: the run cannot write, so a user
     * without a target is counted instead of stopping the pass at its first row.
     *
     * @var bool
     */
    protected $lenientUsers = false;

    /** @var array<string, string> role => insert|merge|replace, for the master roles */
    protected $masterPlan = [];

    /** @var array<string, array<int|string, bool>> role => ids already identical here */
    protected $masterSkip = [];

    /** @var array<int, string> */
    protected $errors = [];

    /** @var array<int, string> */
    protected $warnings = [];

    /** @var array<int, string> */
    protected $info = [];

    /** @var array<string, int> */
    protected $counts = [];

    /** @var array<string, bool> */
    protected $unknownKeys = [];

    /** @var array<string, array<int, string>> */
    protected $tableColumns = [];

    /** @var array<int, string> old user codes whose @mention would be left as text */
    protected $mentionCodes = [];

    /** @var int */
    protected $mentionRows = 0;

    /** @var array<string, array<string, bool>> dataset => keys already kept */
    protected $seen = [];

    /** @var array<string, int> */
    protected $dropped = [];

    /** @var array<string, int> */
    protected $columnIds = [];

    /** @var array<int, string> */
    protected $copiedPaths = [];

    /**
     * @param string $zipPath uploaded bundle
     * @param array<string, string> $userSpec old user_code => new user_code, "*" the fallback
     * @param string $masterMode MASTER_ABORT|MASTER_REPLACE
     * @param bool $execute false: check and report only
     * @return array<string, mixed>
     */
    public static function run(string $zipPath, array $userSpec, string $masterMode, bool $execute): array
    {
        $dir = Bundle::makeTempDir();
        try {
            $self = new static();

            return $self->process($zipPath, new Bundle($dir), $userSpec, $masterMode, $execute);
        } finally {
            Bundle::removeDir($dir);
        }
    }

    /**
     * @param string $zipPath
     * @param Bundle $bundle
     * @param array<string, string> $userSpec
     * @param string $masterMode
     * @param bool $execute
     * @return array<string, mixed>
     */
    protected function process(string $zipPath, Bundle $bundle, array $userSpec, string $masterMode, bool $execute): array
    {
        MigrateSchema::assertSupportedDriver();
        MigrateSchema::forget();
        $this->bundle = $bundle;

        try {
            $this->extract($zipPath);
            $this->manifest = $bundle->manifest();
            $this->checkManifest();
            $this->resolveTables();
            $this->checkTargetEmpty($masterMode);
            $this->resolveUsers($userSpec);
            $this->checkFiles();

            // the mapping pass: every row is read and rewritten, none is written
            $this->lenientUsers = !empty($this->errors);
            $this->pass(false);
        } catch (MigrateException $e) {
            $this->errors[] = $e->getMessage();
        }

        $this->addPassWarnings();

        if ($execute && empty($this->errors)) {
            $this->write();
        }

        return $this->report($execute);
    }

    // ---- reading the bundle ---------------------------------------------

    /**
     * @param string $zipPath
     * @return void
     * @throws MigrateException
     */
    protected function extract(string $zipPath): void
    {
        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new MigrateException('ZIP ファイルを開けません。');
        }

        $entryError = ZipService::validateZipEntries($zip);
        if ($entryError !== null) {
            $zip->close();
            throw new MigrateException($entryError);
        }

        $zip->extractTo($this->bundle->dir());
        $zip->close();
    }

    /**
     * @return void
     * @throws MigrateException
     */
    protected function checkManifest(): void
    {
        if (intval($this->manifest['format'] ?? 0) !== Exporter::FORMAT) {
            throw new MigrateException('この移行ファイルの形式(format)には対応していません。');
        }

        $here = $this->exmentVersion();
        $there = $this->manifest['exment_version'] ?? null;
        if ($here !== null && $there !== null && $here !== $there) {
            $this->warnings[] = "Exment のバージョンが違います（移行元 {$there} / 移行先 {$here}）。";
        }

        foreach (($this->manifest['warnings'] ?? []) as $warning) {
            $this->warnings[] = '出力時: ' . $warning;
        }
        if (!empty($this->manifest['missing_files'])) {
            $this->warnings[] = '出力時にファイル本体を読めなかった添付が ' . count($this->manifest['missing_files']) . ' 件あります（行だけ移行し、ファイルは複写しません）。';
        }
        if (!empty($this->manifest['org_values']) || !empty($this->manifest['org_authorities'])) {
            $this->warnings[] = '組織(organization)の値/権限が含まれています。組織の ID は移行先で意味が違うため、値は変えずに移行します。権限行は移行しません。';
        }
    }

    /**
     * @return void
     * @throws MigrateException
     */
    protected function resolveTables(): void
    {
        $this->roles = MigrateSchema::roles();

        foreach ((array)($this->manifest['roles'] ?? []) as $role => $spec) {
            if (!isset($this->roles[$role])) {
                throw new MigrateException("移行元のテーブル役割「{$role}」を移行先は持っていません。");
            }
            $this->tableMap[$spec['table_name']] = $this->roles[$role];
        }

        foreach ($this->roles as $name) {
            $this->descs[$name] = MigrateSchema::describe($name);
        }
        foreach ([MigrateSchema::COMMENT_TABLE, MigrateSchema::DOCUMENT_TABLE, MigrateSchema::USER_TABLE] as $name) {
            $this->descs[$name] = MigrateSchema::describe($name);
            $this->tableMap[$name] = $name;
        }

        // the two ends must define the same columns, or values would be dropped or left blank
        foreach ((array)$this->manifest['roles'] as $role => $spec) {
            $desc = $this->descs[$this->roles[$role]];
            $missing = array_diff($spec['columns'], $desc['columns']);
            if (!empty($missing)) {
                $this->errors[] = "「{$desc['table_name']}」に移行元の列がありません: " . implode(', ', $missing);
            }
            $extra = array_diff($desc['columns'], $spec['columns']);
            if (!empty($extra)) {
                $this->warnings[] = "「{$desc['table_name']}」には移行元にない列があります（空のままになります）: " . implode(', ', $extra);
            }
            $missingData = array_diff($spec['data_columns'], $desc['dataColumns']);
            if (!empty($missingData)) {
                $this->errors[] = "「{$desc['table_name']}」のデータ列が足りません: " . implode(', ', $missingData);
            }
        }
    }

    // ---- what is already here ---------------------------------------------

    /**
     * Ids are kept, so nothing the bundle brings may already exist here.
     *
     * @param string $masterMode
     * @return void
     */
    protected function checkTargetEmpty(string $masterMode): void
    {
        // The master lists are left out: when the portal seeded them it also wrote their
        // history and permission rows, and planMaster() decides what happens to those.
        $masterNames = [];
        foreach (MigrateSchema::MASTER_ROLES as $role) {
            if (isset($this->roles[$role])) {
                $masterNames[] = $this->roles[$role];
            }
        }
        $names = array_values(array_diff($this->roles, $masterNames));
        $ownedNames = array_merge($names, [MigrateSchema::COMMENT_TABLE, MigrateSchema::DOCUMENT_TABLE]);

        foreach ((array)$this->manifest['roles'] as $role => $spec) {
            $desc = $this->descs[$this->roles[$role]];
            if (!empty($spec['master'])) {
                $this->planMaster($role, $desc, $masterMode);
                continue;
            }
            $count = DB::table($desc['physical'])->count();
            if ($count > 0) {
                $this->errors[] = "「{$desc['table_name']}」には既に {$count} 件のデータがあります。ID を保ったまま移行するため、空である必要があります。";
            }
        }

        foreach ([MigrateSchema::COMMENT_TABLE, MigrateSchema::DOCUMENT_TABLE] as $name) {
            $count = DB::table($this->descs[$name]['physical'])->whereIn('parent_type', $names)->count();
            if ($count > 0) {
                $this->errors[] = "「{$name}」にプロジェクト管理のデータに付いた行が既に {$count} 件あります。";
            }
        }

        $count = DB::table('revisions')->whereIn('revisionable_type', $ownedNames)->count();
        if ($count > 0) {
            $this->errors[] = "変更履歴(revisions)にプロジェクト管理のデータの行が既に {$count} 件あります。";
        }

        $table = MigrateSchema::authoritableTable();
        if (Schema::hasTable($table) && DB::table($table)->whereIn('parent_type', $names)->count() > 0) {
            $this->errors[] = '権限の行(' . $table . ')にプロジェクト管理のデータの行が既にあります。';
        }

        if ($this->bundle->has('stars')) {
            if (!Schema::hasTable(MigrateSchema::STARS_TABLE)) {
                $this->errors[] = 'スター用のテーブル(' . MigrateSchema::STARS_TABLE . ')がありません。プロジェクト管理の「データ構造の更新」が必要です。';
            } elseif (DB::table(MigrateSchema::STARS_TABLE)->whereIn('target_type', $ownedNames)->count() > 0) {
                $this->errors[] = 'スターの行がプロジェクト管理のデータに対して既にあります。';
            }
        }
    }

    /**
     * Decide what to do with a master list the portal may already have seeded.
     *
     * @param string $role
     * @param array<string, mixed> $desc
     * @param string $masterMode
     * @return void
     */
    protected function planMaster(string $role, array $desc, string $masterMode): void
    {
        $name = $desc['table_name'];
        $here = DB::table($desc['physical'])->select($desc['dataColumns'])->get();
        if ($here->isEmpty()) {
            $this->masterPlan[$role] = 'insert';

            return;
        }

        $bundleRows = [];
        foreach ($this->bundle->rows($role) as $row) {
            $bundleRows[strval($row['id'])] = $row;
        }

        $identical = [];
        $conflict = 0;
        foreach ($here as $row) {
            $row = (array)$row;
            $theirs = $bundleRows[strval($row['id'])] ?? null;
            if ($theirs !== null && $this->sameMasterRow($row, $theirs)) {
                $identical[$row['id']] = true;
            } else {
                $conflict++;
            }
        }

        if ($conflict === 0) {
            $this->masterPlan[$role] = 'merge';
            $this->masterSkip[$role] = $identical;
            $this->info[] = "「{$name}」: 既にある " . count($identical) . ' 件は移行元と同じなので、足りない行だけ追加します。';

            return;
        }

        if ($masterMode === static::MASTER_REPLACE) {
            $this->masterPlan[$role] = 'replace';
            $this->warnings[] = "「{$name}」: 移行先の既存 {$here->count()} 件を削除し、移行元の行で置き換えます。";

            return;
        }

        $this->errors[] = "「{$name}」の既存の行 {$conflict} 件が移行元と一致しません。置き換えてよければ「" . \App\Plugins\ExmentPM\PortalDefinition::trans('migrate.master_replace') . "」を選んでください。";
    }

    /**
     * @param array<string, mixed> $here
     * @param array<string, mixed> $theirs
     * @return bool
     */
    protected function sameMasterRow(array $here, array $theirs): bool
    {
        $theirType = $theirs['parent_type'] ?? null;
        $theirType = $theirType === null ? null : ($this->tableMap[$theirType] ?? $theirType);

        return json_decode(strval($here['value'] ?? ''), true) == json_decode(strval($theirs['value'] ?? ''), true)
            && strval($here['parent_id'] ?? '') === strval($theirs['parent_id'] ?? '')
            && strval($here['parent_type'] ?? '') === strval($theirType ?? '');
    }

    // ---- users ------------------------------------------------------------

    /**
     * @param array<string, string> $spec
     * @return void
     */
    protected function resolveUsers(array $spec): void
    {
        $users = (array)($this->manifest['users'] ?? []);
        $desc = $this->descs[MigrateSchema::USER_TABLE];

        $codes = array_values(array_unique(array_values($spec)));
        $found = [];
        foreach (array_chunk($codes, static::CHUNK) as $chunk) {
            $rows = DB::table($desc['physical'])
                ->whereNull('deleted_at')
                ->whereIn(DB::raw("json_unquote(json_extract(`value`, '$.\"user_code\"'))"), $chunk)
                ->get(['id', 'value']);
            foreach ($rows as $row) {
                $value = json_decode(strval($row->value), true);
                if (is_array($value) && isset($value['user_code'])) {
                    $found[strval($value['user_code'])] = intval($row->id);
                }
            }
        }
        foreach ($codes as $code) {
            if (!isset($found[$code])) {
                $this->errors[] = "移行先にユーザー「{$code}」がありません。";
            }
        }

        if (isset($spec['*']) && isset($found[$spec['*']])) {
            $this->defaultUserId = $found[$spec['*']];
        }

        foreach ($users as $id => $info) {
            $code = $info['user_code'] ?? null;
            $target = ($code !== null && isset($spec[$code])) ? $spec[$code] : ($spec['*'] ?? null);
            if ($target === null) {
                $label = $code ?? ('ID ' . $id . '（移行元で削除済み）');
                $this->errors[] = "ユーザー「{$label}」の移行先が指定されていません。「*=ユーザーコード」の行で既定を指定してください。";
                continue;
            }
            if (isset($found[$target])) {
                $this->userMap[strval($id)] = $found[$target];
                $this->userTargetCodes[strval($id)] = $target;
            }
            if ($code !== null && $target !== $code) {
                $this->mentionCodes[] = $code;
            }
        }
    }

    /**
     * @param mixed $id
     * @return int|string
     * @throws MigrateException
     */
    protected function mapUser($id)
    {
        if (isset($this->userMap[strval($id)])) {
            return $this->userMap[strval($id)];
        }
        if ($this->defaultUserId !== null) {
            return $this->defaultUserId;
        }
        if ($this->lenientUsers) {
            return 0;
        }

        throw new MigrateException("ユーザー ID {$id} の移行先が決まりません。");
    }

    /**
     * @param mixed $id
     * @return mixed
     */
    protected function mapUserOrNull($id)
    {
        if ($id === null || $id === '' || $id === 0 || $id === '0') {
            return $id;
        }

        return $this->mapUser($id);
    }

    // ---- files --------------------------------------------------------

    protected function checkFiles(): void
    {
        $disk = $this->disk();
        $uuids = [];
        $clash = 0;
        foreach ($this->bundle->rows('files') as $row) {
            $uuids[] = $row['uuid'];
            $path = trim(strval($row['local_dirname']), '/') . '/' . $row['local_filename'];
            if (is_file($this->bundle->filePath($row['uuid'])) && $disk->exists($path)) {
                $clash++;
            }
        }

        if ($clash > 0) {
            $this->errors[] = "添付ファイルの保存先に同じ名前のファイルが既に {$clash} 件あります。";
        }
        foreach (array_chunk($uuids, static::CHUNK) as $chunk) {
            $count = DB::table('files')->whereIn('uuid', $chunk)->count();
            if ($count > 0) {
                $this->errors[] = "files テーブルに同じ uuid の行が既に {$count} 件あります。";
            }
        }
    }

    /**
     * @return \Illuminate\Contracts\Filesystem\Filesystem
     */
    protected function disk()
    {
        return Storage::disk(config('admin.upload.disk', Define::DISKNAME_ADMIN));
    }

    // ---- the mapping pass ---------------------------------------------------

    /**
     * Read every dataset, rewrite each row, and either count or insert it.
     *
     * @param bool $write
     * @return void
     * @throws MigrateException
     */
    protected function pass(bool $write): void
    {
        $this->counts = [];
        $this->seen = [];
        $this->dropped = [];
        $this->mentionRows = 0;

        foreach (array_keys((array)$this->manifest['roles']) as $role) {
            $desc = $this->descs[$this->roles[$role]];
            $plan = $this->masterPlan[$role] ?? 'insert';
            $skip = $this->masterSkip[$role] ?? [];
            if ($write && $plan === 'replace') {
                DB::table($desc['physical'])->delete();
                $this->deleteSatellites($desc['table_name']);
            }
            $this->insertAll($role, $desc['physical'], $this->valueRows($role, $desc, $skip), $write);
        }

        foreach ([MigrateSchema::COMMENT_TABLE, MigrateSchema::DOCUMENT_TABLE] as $name) {
            $desc = $this->descs[$name];
            $this->insertAll($name, $desc['physical'], $this->valueRows($name, $desc, []), $write);
        }

        $this->insertAll('files', 'files', $this->fileRows(), $write);
        $this->insertAll('revisions', 'revisions', $this->revisionRows(), $write);
        $this->insertAll('stars', MigrateSchema::STARS_TABLE, $this->starRows(), $write, true);
        $this->insertAll('authorities', MigrateSchema::authoritableTable(), $this->authorityRows(), $write);
    }

    /**
     * Whether a history, star or permission row hangs off a master row that is
     * already here and identical: the copy that is here keeps its own.
     *
     * @param string $tableName table_name here
     * @param mixed $id
     * @return bool
     */
    protected function isSkippedParent(string $tableName, $id): bool
    {
        $role = array_search($tableName, $this->roles, true);

        return $role !== false && isset($this->masterSkip[$role][$id]);
    }

    /**
     * The history, stars and permissions of master rows that are about to be replaced.
     *
     * @param string $tableName
     * @return void
     */
    protected function deleteSatellites(string $tableName): void
    {
        DB::table('revisions')->where('revisionable_type', $tableName)->delete();
        if (Schema::hasTable(MigrateSchema::STARS_TABLE)) {
            DB::table(MigrateSchema::STARS_TABLE)->where('target_type', $tableName)->delete();
        }
        if (Schema::hasTable(MigrateSchema::authoritableTable())) {
            DB::table(MigrateSchema::authoritableTable())->where('parent_type', $tableName)->delete();
        }
    }

    /**
     * @param string $dataset
     * @param string $table
     * @param \Generator $rows
     * @param bool $write
     * @param bool $ignoreDuplicates
     * @return void
     */
    protected function insertAll(string $dataset, string $table, \Generator $rows, bool $write, bool $ignoreDuplicates = false): void
    {
        $this->counts[$dataset] = 0;
        $chunk = [];
        foreach ($rows as $row) {
            $this->counts[$dataset]++;
            if (!$write) {
                continue;
            }
            $chunk[] = $row;
            if (count($chunk) >= static::CHUNK) {
                $ignoreDuplicates ? DB::table($table)->insertOrIgnore($chunk) : DB::table($table)->insert($chunk);
                $chunk = [];
            }
        }
        if ($write && !empty($chunk)) {
            $ignoreDuplicates ? DB::table($table)->insertOrIgnore($chunk) : DB::table($table)->insert($chunk);
        }
    }

    /**
     * @param string $dataset
     * @param array<string, mixed> $desc
     * @param array<int|string, bool> $skip ids already here and identical
     * @return \Generator<int, array<string, mixed>>
     */
    protected function valueRows(string $dataset, array $desc, array $skip): \Generator
    {
        $map = function ($id) {
            return $this->mapUser($id);
        };

        foreach ($this->bundle->rows($dataset) as $row) {
            if (isset($skip[$row['id']])) {
                continue;
            }

            foreach (['created_user_id', 'updated_user_id', 'deleted_user_id'] as $column) {
                if (array_key_exists($column, $row)) {
                    $row[$column] = $this->mapUserOrNull($row[$column]);
                }
            }
            if (!empty($row['parent_type'])) {
                $row['parent_type'] = $this->tableMap[$row['parent_type']] ?? $row['parent_type'];
            }
            if ($dataset === MigrateSchema::COMMENT_TABLE) {
                $this->noteMention($row);
            }
            $row['value'] = UserMap::rewriteValueJson($row['value'] ?? null, $desc['userColumns'], $map);

            yield $this->only($dataset, $row, $desc['dataColumns']);
        }
    }

    /**
     * @return \Generator<int, array<string, mixed>>
     */
    protected function fileRows(): \Generator
    {
        foreach ($this->bundle->rows('files') as $row) {
            $ref = $row['_column_ref'] ?? null;
            unset($row['_column_ref']);

            $row['custom_column_id'] = null;
            if (is_array($ref) && count($ref) === 2) {
                $row['custom_column_id'] = $this->columnId($this->tableMap[$ref[0]] ?? $ref[0], $ref[1]);
            }
            if (!empty($row['parent_type'])) {
                $row['parent_type'] = $this->tableMap[$row['parent_type']] ?? $row['parent_type'];
            }
            foreach (['created_user_id', 'updated_user_id'] as $column) {
                if (array_key_exists($column, $row)) {
                    $row[$column] = $this->mapUserOrNull($row[$column]);
                }
            }

            yield $this->only('files', $row, $this->columnsOf('files'));
        }
    }

    /**
     * @return \Generator<int, array<string, mixed>>
     */
    protected function revisionRows(): \Generator
    {
        $map = function ($id) {
            return $this->mapUser($id);
        };
        $valid = array_keys($this->descs);

        foreach ($this->bundle->rows('revisions') as $row) {
            unset($row['id']);

            $type = $this->tableMap[$row['revisionable_type']] ?? $row['revisionable_type'];
            if (!in_array($type, $valid, true)) {
                throw new MigrateException("変更履歴の対象テーブル「{$row['revisionable_type']}」が移行先にありません。");
            }
            $row['revisionable_type'] = $type;
            if ($this->isSkippedParent($type, $row['revisionable_id'])) {
                continue;
            }

            foreach (['create_user_id', 'delete_user_id'] as $column) {
                if (array_key_exists($column, $row)) {
                    $row[$column] = $this->mapUserOrNull($row[$column]);
                }
            }
            if (($row['key'] ?? null) === 'value') {
                $columns = $this->descs[$type]['userColumns'];
                $row['old_value'] = UserMap::rewriteValueJson($row['old_value'] ?? null, $columns, $map);
                $row['new_value'] = UserMap::rewriteValueJson($row['new_value'] ?? null, $columns, $map);
            }

            yield $this->only('revisions', $row, $this->columnsOf('revisions'));
        }
    }

    /**
     * Stars are unique per (type, target, user). When several users map to one,
     * only the first row is kept.
     *
     * @return \Generator<int, array<string, mixed>>
     */
    protected function starRows(): \Generator
    {
        foreach ($this->bundle->rows('stars') as $row) {
            unset($row['id']);
            $row['target_type'] = $this->tableMap[$row['target_type']] ?? $row['target_type'];
            if ($this->isSkippedParent($row['target_type'], $row['target_id'])) {
                continue;
            }
            foreach (['user_id', 'created_user_id', 'updated_user_id'] as $column) {
                if (array_key_exists($column, $row)) {
                    $row[$column] = $this->mapUserOrNull($row[$column]);
                }
            }

            $key = implode('|', [$row['star_type'], $row['target_type'], $row['target_id'], $row['user_id']]);
            if (isset($this->seen['stars'][$key])) {
                $this->dropped['stars'] = ($this->dropped['stars'] ?? 0) + 1;
                continue;
            }
            $this->seen['stars'][$key] = true;

            yield $this->only('stars', $row, $this->columnsOf(MigrateSchema::STARS_TABLE));
        }
    }

    /**
     * Per-record permissions. A user row is mapped (and deduplicated when users
     * merge); an organization row is not carried over.
     *
     * @return \Generator<int, array<string, mixed>>
     */
    protected function authorityRows(): \Generator
    {
        foreach ($this->bundle->rows('authorities') as $row) {
            unset($row['id']);
            if (($row['authoritable_user_org_type'] ?? null) !== MigrateSchema::USER_TABLE) {
                $this->dropped['authorities_org'] = ($this->dropped['authorities_org'] ?? 0) + 1;
                continue;
            }

            $row['parent_type'] = $this->tableMap[$row['parent_type']] ?? $row['parent_type'];
            if ($this->isSkippedParent($row['parent_type'], $row['parent_id'])) {
                continue;
            }
            $row['authoritable_target_id'] = $this->mapUser($row['authoritable_target_id']);
            foreach (['created_user_id', 'updated_user_id'] as $column) {
                if (array_key_exists($column, $row)) {
                    $row[$column] = $this->mapUserOrNull($row[$column]);
                }
            }

            $key = implode('|', [$row['parent_type'], $row['parent_id'], $row['authoritable_type'], $row['authoritable_user_org_type'], $row['authoritable_target_id']]);
            if (isset($this->seen['authorities'][$key])) {
                $this->dropped['authorities'] = ($this->dropped['authorities'] ?? 0) + 1;
                continue;
            }
            $this->seen['authorities'][$key] = true;

            yield $this->only('authorities', $row, $this->columnsOf(MigrateSchema::authoritableTable()));
        }
    }

    /**
     * Keep only the columns the target table has. A column the bundle has and
     * the table has not is an error: dropping it would lose data silently.
     *
     * @param string $dataset
     * @param array<string, mixed> $row
     * @param array<int, string> $columns
     * @return array<string, mixed>
     */
    protected function only(string $dataset, array $row, array $columns): array
    {
        $unknown = array_diff(array_keys($row), $columns);
        if (!empty($unknown) && !isset($this->unknownKeys[$dataset])) {
            $this->unknownKeys[$dataset] = true;
            $this->errors[] = "「{$dataset}」の列 " . implode(', ', $unknown) . ' が移行先にありません。';
        }

        return array_intersect_key($row, array_flip($columns));
    }

    /**
     * @param string $table
     * @return array<int, string>
     */
    protected function columnsOf(string $table): array
    {
        return $this->tableColumns[$table] = $this->tableColumns[$table] ?? array_map('strtolower', Schema::getColumnListing($table));
    }

    /**
     * @param string $tableName
     * @param string $columnName
     * @return int
     * @throws MigrateException
     */
    protected function columnId(string $tableName, string $columnName): int
    {
        $key = $tableName . '.' . $columnName;
        if (!isset($this->columnIds[$key])) {
            $id = DB::table('custom_columns')
                ->join('custom_tables', 'custom_tables.id', '=', 'custom_columns.custom_table_id')
                ->where('custom_tables.table_name', $tableName)
                ->where('custom_columns.column_name', $columnName)
                ->value('custom_columns.id');
            if ($id === null) {
                throw new MigrateException("添付ファイルの列「{$key}」が移行先にありません。");
            }
            $this->columnIds[$key] = intval($id);
        }

        return $this->columnIds[$key];
    }

    /**
     * @param array<string, mixed> $row a comment row
     * @return void
     */
    protected function noteMention(array $row): void
    {
        if (empty($this->mentionCodes)) {
            return;
        }
        $value = json_decode(strval($row['value'] ?? ''), true);
        $text = is_array($value) ? strval($value['comment_detail'] ?? '') : '';
        foreach ($this->mentionCodes as $code) {
            if (strpos($text, '@' . $code) !== false) {
                $this->mentionRows++;

                return;
            }
        }
    }

    // ---- writing --------------------------------------------------------

    /**
     * @return void
     */
    protected function write(): void
    {
        $this->errors = [];
        $this->copiedPaths = [];

        try {
            DB::transaction(function () {
                $this->pass(true);
                $this->copyFiles();
            });
        } catch (\Throwable $e) {
            $this->removeCopiedFiles();
            if ($e instanceof MigrateException) {
                $this->errors[] = $e->getMessage();
            } else {
                Log::error('pm_portal migrate import failed: ' . $e->getMessage(), ['exception' => $e]);
                $this->errors[] = '書き込み中にエラーが発生したため、すべて取り消しました（ログを確認してください）。';
            }
        }

        // a failed run left nothing behind: report it as not executed
        if (!empty($this->errors)) {
            $this->counts = [];
        }
    }

    /**
     * @return void
     */
    protected function copyFiles(): void
    {
        $disk = $this->disk();
        foreach ($this->bundle->rows('files') as $row) {
            $source = $this->bundle->filePath($row['uuid']);
            if (!is_file($source)) {
                continue;
            }
            $path = trim(strval($row['local_dirname']), '/') . '/' . $row['local_filename'];
            $stream = fopen($source, 'rb');
            try {
                $disk->writeStream($path, $stream);
                $this->copiedPaths[] = $path;
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }
        }
    }

    protected function removeCopiedFiles(): void
    {
        $disk = $this->disk();
        foreach ($this->copiedPaths as $path) {
            try {
                $disk->delete($path);
            } catch (\Throwable $e) {
                Log::warning('pm_portal migrate could not remove a copied file: ' . $path);
            }
        }
        $this->copiedPaths = [];
    }

    // ---- the report -------------------------------------------------------

    /**
     * @return void
     */
    protected function addPassWarnings(): void
    {
        if ($this->mentionRows > 0) {
            $this->warnings[] = "{$this->mentionRows} 件のコメント本文に、移行先で別のユーザーに置き換わる「@ユーザーコード」が含まれます（本文は変更しません）。";
        }
        if (!empty($this->dropped['stars'])) {
            $this->info[] = "スター {$this->dropped['stars']} 件は、ユーザーが同じ人にまとまるため重複として除きました。";
        }
        if (!empty($this->dropped['authorities'])) {
            $this->info[] = "権限 {$this->dropped['authorities']} 件は、ユーザーが同じ人にまとまるため重複として除きました。";
        }
        if (!empty($this->dropped['authorities_org'])) {
            $this->warnings[] = "組織向けの権限 {$this->dropped['authorities_org']} 件は移行しませんでした。";
        }

        $odd = [];
        foreach ((array)($this->manifest['revision_keys'] ?? []) as $key => $count) {
            if (!in_array($key, static::KNOWN_REVISION_KEYS, true)) {
                $odd[] = "{$key}({$count})";
            }
        }
        if (!empty($odd)) {
            $this->warnings[] = '変更履歴に、ユーザー ID の置き換えを確認していない項目があります: ' . implode(', ', $odd);
        }
    }

    /**
     * @param bool $execute
     * @return array<string, mixed>
     */
    protected function report(bool $execute): array
    {
        $users = [];
        foreach ((array)($this->manifest['users'] ?? []) as $id => $info) {
            $users[] = [
                'id' => strval($id),
                'user_code' => $info['user_code'] ?? null,
                'user_name' => $info['user_name'] ?? null,
                'mapped' => $this->userTargetCodes[strval($id)] ?? null,
            ];
        }

        $ok = empty($this->errors);

        return [
            'ok' => $ok,
            'executed' => $execute && $ok,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'info' => $this->info,
            'counts' => $this->counts,
            'users' => $users,
            'projects' => (array)($this->manifest['projects'] ?? []),
        ];
    }

    protected function exmentVersion(): ?string
    {
        try {
            return strval(\Exment::getExmentCurrentVersion());
        } catch (\Throwable $e) {
            return null;
        }
    }
}
