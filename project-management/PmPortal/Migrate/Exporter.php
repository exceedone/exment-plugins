<?php

namespace App\Plugins\PmPortal\Migrate;

use Exceedone\Exment\Model\Define;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Data migration, originally written by TTM as the separate PmMigrate plugin
 * and folded into PmPortal in 1.2.0.
 *
 * Builds the bundle of the chosen projects.
 *
 * Reads the database with plain queries and writes nothing to it. A project
 * brings its issues and wiki pages (children of the project row), its
 * milestones and categories (rows that point at it), the master rows its
 * issues use, the links between its issues, and for all of those the
 * comments, documents, change history, files, stars and per-record
 * permissions. The plugin's Git keys and AI logs are never read.
 */
class Exporter
{
    const FORMAT = 1;
    const CHUNK = 500;

    /** The order the roles are read in: what others point at comes first. */
    const ROLE_ORDER = [
        'project_table',
        'milestone_table',
        'category_table',
        'issue_table',
        'wiki_table',
        'status_table',
        'issue_type_table',
        'priority_table',
        'resolution_table',
        'related_table',
    ];

    /** @var array<string, string> */
    protected $roles = [];

    /** @var array<string, array<string, mixed>> table_name => description */
    protected $descs = [];

    /** @var Bundle */
    protected $bundle;

    /** @var array<string, array<int|string, bool>> table_name => ids exported */
    protected $selected = [];

    /** @var array<string, bool> */
    protected $userIds = [];

    /** @var array<int, string> */
    protected $warnings = [];

    /** @var array<string, array<string, string>> target table_name => id => "table.column" that points at it */
    protected $refs = [];

    /** @var array<string, string> uuid => file row's path on the disk, for what is to be copied */
    protected $filePaths = [];

    /** @var array<string, int> */
    protected $revisionKeys = [];

    /** @var int */
    protected $orgValues = 0;

    /** @var int */
    protected $orgAuthorities = 0;

    /** @var array<int, array{0: string, 1: string}> */
    protected $columnRefs = [];

    /**
     * The projects to offer on the screen.
     *
     * @return array<int, array<string, mixed>>
     * @throws MigrateException
     */
    public static function projectChoices(): array
    {
        $roles = MigrateSchema::roles();
        $desc = MigrateSchema::describe($roles['project_table']);

        $choices = [];
        foreach (DB::table($desc['physical'])->whereNull('deleted_at')->orderBy('id')->get(['id', 'value']) as $row) {
            $value = json_decode(strval($row->value), true);
            $value = is_array($value) ? $value : [];
            $choices[] = [
                'id' => intval($row->id),
                'code' => strval($value['project_code'] ?? ''),
                'name' => strval($value['name'] ?? ''),
                'archived' => !empty($value['archived']),
            ];
        }

        return $choices;
    }

    /**
     * Build the bundle and return the path of the ZIP. The caller deletes it.
     *
     * @param array<int, int> $projectIds
     * @return string
     * @throws MigrateException
     */
    public static function run(array $projectIds): string
    {
        $dir = Bundle::makeTempDir();
        try {
            return (new static())->build($projectIds, new Bundle($dir));
        } finally {
            Bundle::removeDir($dir);
        }
    }

    /**
     * @param array<int, int> $projectIds
     * @param Bundle $bundle
     * @return string
     */
    protected function build(array $projectIds, Bundle $bundle): string
    {
        MigrateSchema::assertSupportedDriver();
        MigrateSchema::forget();

        $this->bundle = $bundle;
        $this->roles = MigrateSchema::roles();
        foreach ($this->roles as $name) {
            $this->descs[$name] = MigrateSchema::describe($name);
        }
        foreach ([MigrateSchema::COMMENT_TABLE, MigrateSchema::DOCUMENT_TABLE, MigrateSchema::USER_TABLE] as $name) {
            $this->descs[$name] = MigrateSchema::describe($name);
        }
        $this->columnRefs = MigrateSchema::columnRefs();

        $projects = $this->readProjects(array_values(array_unique(array_map('intval', $projectIds))));

        foreach (static::ROLE_ORDER as $role) {
            if ($role !== 'project_table') {
                $this->readRole($role);
            }
        }

        $this->readComments();
        $this->readDocuments();
        $this->readFiles();
        $this->readRevisions();
        $this->readStars();
        $this->readAuthorities();
        $this->checkReferences();

        $missingFiles = $this->copyFiles();

        $manifest = [
            'format' => static::FORMAT,
            'created_at' => date('c'),
            'exment_version' => $this->exmentVersion(),
            'roles' => $this->roleManifest(),
            'projects' => $projects,
            'users' => $this->userManifest(),
            'revision_keys' => $this->revisionKeys,
            'org_values' => $this->orgValues,
            'org_authorities' => $this->orgAuthorities,
            'missing_files' => $missingFiles,
            'warnings' => $this->warnings,
        ];
        $manifest['counts'] = $this->bundle->counts();
        $this->bundle->writeManifest($manifest);

        return $this->zip();
    }

    // ---- reading the rows -----------------------------------------------

    /**
     * @param array<int, int> $ids
     * @return array<int, array<string, mixed>>
     */
    protected function readProjects(array $ids): array
    {
        $desc = $this->descs[$this->roles['project_table']];
        $rows = DB::table($desc['physical'])->select($desc['dataColumns'])->whereIn('id', $ids)->orderBy('id')->get();
        if ($rows->count() !== count($ids)) {
            throw new MigrateException('選択したプロジェクトの一部が見つかりません。画面を開き直してやり直してください。');
        }

        $projects = [];
        foreach ($rows as $row) {
            $row = (array)$row;
            $value = json_decode(strval($row['value'] ?? ''), true);
            $value = is_array($value) ? $value : [];
            $projects[] = [
                'id' => intval($row['id']),
                'code' => strval($value['project_code'] ?? ''),
                'name' => strval($value['name'] ?? ''),
            ];
            $this->emit('project_table', $row);
        }

        return $projects;
    }

    /**
     * @param string $role
     * @return void
     */
    protected function readRole(string $role): void
    {
        $desc = $this->descs[$this->roles[$role]];
        $projectDesc = $this->descs[$this->roles['project_table']];
        $projectIds = array_keys($this->selected[$projectDesc['table_name']] ?? []);
        $strategy = $this->strategy($role, $desc, $projectDesc);
        $isMaster = in_array($role, MigrateSchema::MASTER_ROLES, true);

        if ($strategy === 'parent') {
            foreach (array_chunk($projectIds, static::CHUNK) as $chunk) {
                $query = DB::table($desc['physical'])->select($desc['dataColumns'])->orderBy('id')
                    ->where(function ($q) use ($projectDesc, $chunk, $isMaster) {
                        $q->where(function ($q2) use ($projectDesc, $chunk) {
                            $q2->where('parent_type', $projectDesc['table_name'])->whereIn('parent_id', $chunk);
                        });
                        if ($isMaster) {
                            $q->orWhereNull('parent_id');
                        }
                    });
                foreach ($query->get() as $row) {
                    $this->emit($role, (array)$row);
                }
            }

            return;
        }

        $projectColumns = array_keys(array_filter($desc['selectColumns'], function ($target) use ($projectDesc) {
            return $target === $projectDesc['id'];
        }));
        $issueDesc = $this->descs[$this->roles['issue_table']];
        $issueColumns = array_keys(array_filter($desc['selectColumns'], function ($target) use ($issueDesc) {
            return $target === $issueDesc['id'];
        }));
        $issueIds = $this->selected[$issueDesc['table_name']] ?? [];
        $excluded = 0;

        DB::table($desc['physical'])->select($desc['dataColumns'])->orderBy('id')->chunkById(static::CHUNK, function ($rows) use ($role, $strategy, $isMaster, $projectColumns, $issueColumns, $issueIds, $projectIds, &$excluded) {
            foreach ($rows as $row) {
                $row = (array)$row;
                $value = json_decode(strval($row['value'] ?? ''), true);
                $value = is_array($value) ? $value : [];

                if ($strategy === 'all') {
                    $this->emit($role, $row);
                    continue;
                }

                if ($strategy === 'column') {
                    $ids = [];
                    foreach ($projectColumns as $column) {
                        $ids = array_merge($ids, static::refIds($value, $column));
                    }
                    // a master row that names no project is shared by all of them
                    $take = empty($ids) ? $isMaster : !empty(array_intersect($ids, array_map('strval', $projectIds)));
                    if ($take) {
                        $this->emit($role, $row);
                    }
                    continue;
                }

                // refs: a link is taken when every issue it names is in the export
                $linked = [];
                foreach ($issueColumns as $column) {
                    $linked = array_merge($linked, static::refIds($value, $column));
                }
                if (empty($linked)) {
                    continue;
                }
                $outside = array_filter($linked, function ($id) use ($issueIds) {
                    return !isset($issueIds[$id]);
                });
                if (count($outside) === count($linked)) {
                    continue;
                }
                if (!empty($outside)) {
                    $excluded++;
                    continue;
                }
                $this->emit($role, $row);
            }
        }, 'id');

        if ($excluded > 0) {
            $this->warnings[] = "{$desc['table_name']}: 選択外の課題とのリンク {$excluded} 件は含めていません。";
        }
    }

    /**
     * How the rows of a role are tied to the chosen projects.
     *
     * @param string $role
     * @param array<string, mixed> $desc
     * @param array<string, mixed> $projectDesc
     * @return string parent|column|refs|all
     * @throws MigrateException
     */
    protected function strategy(string $role, array $desc, array $projectDesc): string
    {
        if ($role === 'related_table') {
            return 'refs';
        }
        if (in_array($projectDesc['id'], MigrateSchema::parentTableIds($desc['id']), true)) {
            return 'parent';
        }
        if (in_array($projectDesc['id'], $desc['selectColumns'], true)) {
            return 'column';
        }
        if (in_array($role, MigrateSchema::MASTER_ROLES, true)) {
            return 'all';
        }

        throw new MigrateException("テーブル「{$desc['table_name']}」とプロジェクトの関係を判定できません。");
    }

    protected function readComments(): void
    {
        $this->readChildren(MigrateSchema::COMMENT_TABLE, MigrateSchema::COMMENT_TABLE);
    }

    protected function readDocuments(): void
    {
        $this->readChildren(MigrateSchema::DOCUMENT_TABLE, MigrateSchema::DOCUMENT_TABLE);
    }

    /**
     * Rows of the comment or document table that hang off an exported row.
     *
     * @param string $tableName
     * @param string $dataset
     * @return void
     */
    protected function readChildren(string $tableName, string $dataset): void
    {
        $desc = $this->descs[$tableName];
        foreach ($this->parentTargets() as $parentName => $ids) {
            foreach (array_chunk($ids, static::CHUNK) as $chunk) {
                $query = DB::table($desc['physical'])->select($desc['dataColumns'])->orderBy('id')
                    ->where('parent_type', $parentName)->whereIn('parent_id', $chunk);
                foreach ($query->get() as $row) {
                    $this->emit($dataset, (array)$row, $desc);
                }
            }
        }
    }

    protected function readFiles(): void
    {
        $rows = [];

        // files that belong to an exported row, or to a document of one
        foreach ($this->allTargets() as $parentName => $ids) {
            foreach (array_chunk($ids, static::CHUNK) as $chunk) {
                foreach (DB::table('files')->where('parent_type', $parentName)->whereIn('parent_id', $chunk)->get() as $row) {
                    $rows[$row->uuid] = (array)$row;
                }
            }
        }

        // the file a document row names
        $documentDesc = $this->descs[MigrateSchema::DOCUMENT_TABLE];
        $uuids = [];
        foreach (array_chunk(array_keys($this->selected[MigrateSchema::DOCUMENT_TABLE] ?? []), static::CHUNK) as $chunk) {
            foreach (DB::table($documentDesc['physical'])->whereIn('id', $chunk)->pluck('value') as $json) {
                $value = json_decode(strval($json), true);
                if (is_array($value) && !empty($value['file_uuid'])) {
                    $uuids[] = strval($value['file_uuid']);
                }
            }
        }
        foreach (array_chunk($uuids, static::CHUNK) as $chunk) {
            foreach (DB::table('files')->whereIn('uuid', $chunk)->get() as $row) {
                $rows[$row->uuid] = (array)$row;
            }
        }

        foreach ($rows as $row) {
            $columnId = $row['custom_column_id'] ?? null;
            $row['_column_ref'] = ($columnId !== null && isset($this->columnRefs[intval($columnId)])) ? $this->columnRefs[intval($columnId)] : null;
            $this->addUser($row['created_user_id'] ?? null);
            $this->addUser($row['updated_user_id'] ?? null);
            $this->filePaths[$row['uuid']] = trim(strval($row['local_dirname']), '/') . '/' . strval($row['local_filename']);
            $this->bundle->append('files', $row);
        }
    }

    protected function readRevisions(): void
    {
        foreach ($this->allTargets() as $parentName => $ids) {
            $desc = $this->descs[$parentName];
            // a revision written before the morph map was defined carries Class_{suuid}
            $types = [$parentName, 'Class_' . $desc['suuid']];
            foreach (array_chunk($ids, static::CHUNK) as $chunk) {
                $query = DB::table('revisions')->whereIn('revisionable_type', $types)->whereIn('revisionable_id', $chunk)->orderBy('id');
                foreach ($query->get() as $row) {
                    $row = (array)$row;
                    $row['revisionable_type'] = $parentName;
                    $key = strval($row['key']);
                    $this->revisionKeys[$key] = ($this->revisionKeys[$key] ?? 0) + 1;

                    $this->addUser($row['create_user_id'] ?? null);
                    $this->addUser($row['delete_user_id'] ?? null);
                    if ($key === 'value') {
                        $this->collectUsers($row['old_value'] ?? null, $desc['userColumns']);
                        $this->collectUsers($row['new_value'] ?? null, $desc['userColumns']);
                    }
                    $this->bundle->append('revisions', $row);
                }
            }
        }
    }

    protected function readStars(): void
    {
        if (!Schema::hasTable(MigrateSchema::STARS_TABLE)) {
            return;
        }

        foreach ($this->allTargets() as $parentName => $ids) {
            foreach (array_chunk(array_map('strval', $ids), static::CHUNK) as $chunk) {
                $query = DB::table(MigrateSchema::STARS_TABLE)->where('target_type', $parentName)->whereIn('target_id', $chunk)->orderBy('id');
                foreach ($query->get() as $row) {
                    $row = (array)$row;
                    $this->addUser($row['user_id'] ?? null);
                    $this->addUser($row['created_user_id'] ?? null);
                    $this->addUser($row['updated_user_id'] ?? null);
                    $this->bundle->append('stars', $row);
                }
            }
        }
    }

    protected function readAuthorities(): void
    {
        $table = MigrateSchema::authoritableTable();
        if (!Schema::hasTable($table)) {
            return;
        }

        foreach ($this->parentTargets() as $parentName => $ids) {
            foreach (array_chunk($ids, static::CHUNK) as $chunk) {
                $query = DB::table($table)->where('parent_type', $parentName)->whereIn('parent_id', $chunk)->orderBy('id');
                foreach ($query->get() as $row) {
                    $row = (array)$row;
                    if (($row['authoritable_user_org_type'] ?? null) === MigrateSchema::USER_TABLE) {
                        $this->addUser($row['authoritable_target_id'] ?? null);
                    } else {
                        // an organization id means something else on the other system
                        $this->orgAuthorities++;
                    }
                    $this->addUser($row['created_user_id'] ?? null);
                    $this->addUser($row['updated_user_id'] ?? null);
                    $this->bundle->append('authorities', $row);
                }
            }
        }
    }

    /**
     * Warn about select columns whose target row did not make it in.
     *
     * @return void
     */
    protected function checkReferences(): void
    {
        foreach ($this->refs as $targetName => $refs) {
            $missing = array_diff_key($refs, $this->selected[$targetName] ?? []);
            if (empty($missing)) {
                continue;
            }
            $sample = [];
            foreach (array_slice($missing, 0, 5, true) as $id => $from) {
                $sample[] = "{$from} → {$targetName}#{$id}";
            }
            $this->warnings[] = count($missing) . " 件の参照先が含まれていません（{$targetName}）: " . implode(', ', $sample);
        }
    }

    // ---- files ------------------------------------------------------------

    /**
     * Copy the bytes of every file row into the bundle folder.
     *
     * @return array<int, string> uuids whose bytes could not be read
     */
    protected function copyFiles(): array
    {
        $disk = Storage::disk(config('admin.upload.disk', Define::DISKNAME_ADMIN));
        $dir = $this->bundle->dir() . DIRECTORY_SEPARATOR . Bundle::FILE_DIR;
        $missing = [];

        foreach ($this->filePaths as $uuid => $path) {
            $stream = null;
            try {
                $stream = $disk->exists($path) ? $disk->readStream($path) : null;
            } catch (\Throwable $e) {
                $stream = null;
            }
            if (!is_resource($stream)) {
                $missing[] = $uuid;
                continue;
            }

            if (!is_dir($dir)) {
                mkdir($dir, 0700, true);
            }
            $out = fopen($this->bundle->filePath($uuid), 'wb');
            stream_copy_to_stream($stream, $out);
            fclose($out);
            fclose($stream);
        }

        return $missing;
    }

    /**
     * @return string
     * @throws MigrateException
     */
    protected function zip(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'pmm');
        $zip = new \ZipArchive();
        if ($path === false || $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new MigrateException('ZIP ファイルを作成できません。');
        }

        $zip->addFile($this->bundle->dir() . DIRECTORY_SEPARATOR . Bundle::MANIFEST, Bundle::MANIFEST);
        foreach (glob($this->bundle->dir() . DIRECTORY_SEPARATOR . Bundle::DATA_DIR . DIRECTORY_SEPARATOR . '*.ndjson') ?: [] as $file) {
            $zip->addFile($file, Bundle::DATA_DIR . '/' . basename($file));
        }
        foreach (glob($this->bundle->dir() . DIRECTORY_SEPARATOR . Bundle::FILE_DIR . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            $zip->addFile($file, Bundle::FILE_DIR . '/' . basename($file));
        }

        // addFile reads at close(): the folder must still exist until then
        $zip->close();

        return $path;
    }

    // ---- helpers --------------------------------------------------------

    /**
     * Write one value-table row and note what it refers to.
     *
     * @param string $dataset
     * @param array<string, mixed> $row
     * @param array<string, mixed>|null $desc defaults to the role's table
     * @return void
     */
    protected function emit(string $dataset, array $row, ?array $desc = null): void
    {
        $desc = $desc ?? $this->descs[$this->roles[$dataset]];
        $name = $desc['table_name'];

        $this->selected[$name][$row['id']] = true;
        foreach (['created_user_id', 'updated_user_id', 'deleted_user_id'] as $column) {
            $this->addUser($row[$column] ?? null);
        }

        $value = json_decode(strval($row['value'] ?? ''), true);
        if (is_array($value)) {
            $this->collectUsers($row['value'], $desc['userColumns']);
            foreach ($desc['orgColumns'] as $column) {
                if (!empty(static::refIds($value, $column))) {
                    $this->orgValues++;
                }
            }
            foreach ($desc['selectColumns'] as $column => $targetId) {
                $target = $this->tableNameById($targetId);
                if ($target === null) {
                    continue;
                }
                foreach (static::refIds($value, $column) as $id) {
                    $this->refs[$target][$id] = $this->refs[$target][$id] ?? "{$name}#{$row['id']}.{$column}";
                }
            }
        }

        $this->bundle->append($dataset, $row);
    }

    /**
     * @param int $id
     * @return string|null table_name, when that table is one of the exported roles
     */
    protected function tableNameById(int $id): ?string
    {
        foreach ($this->roles as $name) {
            if ($this->descs[$name]['id'] === $id) {
                return $name;
            }
        }

        return null;
    }

    /**
     * The rows that other rows hang off: table_name => ids, for the portal's tables only.
     *
     * @return array<string, array<int, int|string>>
     */
    protected function parentTargets(): array
    {
        $targets = [];
        foreach ($this->roles as $name) {
            if (!empty($this->selected[$name])) {
                $targets[$name] = array_keys($this->selected[$name]);
            }
        }

        return $targets;
    }

    /**
     * parentTargets() plus the comment and document rows.
     *
     * @return array<string, array<int, int|string>>
     */
    protected function allTargets(): array
    {
        $targets = $this->parentTargets();
        foreach ([MigrateSchema::COMMENT_TABLE, MigrateSchema::DOCUMENT_TABLE] as $name) {
            if (!empty($this->selected[$name])) {
                $targets[$name] = array_keys($this->selected[$name]);
            }
        }

        return $targets;
    }

    /**
     * @param mixed $id
     * @return void
     */
    protected function addUser($id): void
    {
        if ($id === null || $id === '' || $id === 0 || $id === '0') {
            return;
        }
        $this->userIds[strval($id)] = true;
    }

    /**
     * @param string|null $json
     * @param array<int, string> $userColumns
     * @return void
     */
    protected function collectUsers(?string $json, array $userColumns): void
    {
        if ($json === null || $json === '' || empty($userColumns)) {
            return;
        }
        $value = json_decode($json, true);
        if (!is_array($value)) {
            return;
        }
        UserMap::rewriteValue($value, $userColumns, function ($id) {
            $this->addUser($id);

            return $id;
        });
    }

    /**
     * The ids a select, user or organization column holds, as strings.
     *
     * @param array<string, mixed> $value
     * @param string $column
     * @return array<int, string>
     */
    public static function refIds(array $value, string $column): array
    {
        $current = $value[$column] ?? null;
        $ids = [];
        foreach (is_array($current) ? $current : [$current] as $id) {
            if ((is_int($id) || is_string($id)) && $id !== '') {
                $ids[] = strval($id);
            }
        }

        return $ids;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function roleManifest(): array
    {
        $manifest = [];
        foreach ($this->roles as $role => $name) {
            $desc = $this->descs[$name];
            $manifest[$role] = [
                'table_name' => $name,
                'columns' => $desc['columns'],
                'data_columns' => $desc['dataColumns'],
                'user_columns' => $desc['userColumns'],
                'master' => in_array($role, MigrateSchema::MASTER_ROLES, true),
            ];
        }

        return $manifest;
    }

    /**
     * @return array<string, array<string, mixed>> source user id => who it was
     */
    protected function userManifest(): array
    {
        $users = [];
        $desc = $this->descs[MigrateSchema::USER_TABLE];
        foreach (array_chunk(array_keys($this->userIds), static::CHUNK) as $chunk) {
            foreach (DB::table($desc['physical'])->whereIn('id', $chunk)->get(['id', 'value']) as $row) {
                $value = json_decode(strval($row->value), true);
                $value = is_array($value) ? $value : [];
                $users[strval($row->id)] = [
                    'user_code' => $value['user_code'] ?? null,
                    'user_name' => $value['user_name'] ?? null,
                    'email' => $value['email'] ?? null,
                ];
            }
        }
        // an id with no row is a user deleted at the source
        foreach (array_keys($this->userIds) as $id) {
            $users[strval($id)] = $users[strval($id)] ?? ['user_code' => null, 'user_name' => null, 'email' => null];
        }
        ksort($users, SORT_NUMERIC);

        return $users;
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
