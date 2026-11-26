<?php

namespace App\Plugins\ExmentPM;

use Exceedone\Exment\Enums\SystemTableName;
use Exceedone\Exment\Model\CustomTable;
use Exceedone\Exment\Model\CustomValue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Issues linked to records of Exment (ISS02939): a customer, a contract, an asset, an inquiry...
 *
 * A project chooses the tables its issues may link to (pm_portal_link_tables; none at first, so
 * nothing shows). An issue's links are rows of pm_portal_record_links. Exment's own permissions
 * decide what a person sees: a record they may not read is a link without its title; on a
 * record's page (public/js/record-links.js) only the issues they may read are listed.
 */
class PortalRecordLinks
{
    const LINKS = 'pm_portal_record_links';
    const TABLES = 'pm_portal_link_tables';
    const MAX_TABLES = 20;
    const MAX_LINKS = 50;
    const CACHE_KEY = 'pmportal_link_tables';

    /** @var bool|null */
    protected static $exists = null;

    public static function exists(): bool
    {
        if (static::$exists === null) {
            try {
                static::$exists = \Schema::hasTable(static::LINKS) && \Schema::hasTable(static::TABLES);
            } catch (\Throwable $e) {
                return false;
            }
        }
        return static::$exists;
    }

    // ---- the tables a project links to -------------------------------------

    /** @return array<int, string> the names, in their order */
    public static function tablesOf(int $projectId): array
    {
        if (!static::exists()) {
            return [];
        }
        return DB::table(static::TABLES)->where('project_id', $projectId)->orderBy('sort')->orderBy('id')
            ->pluck('table_name')->map(function ($v) {
                return strval($v);
            })->all();
    }

    /**
     * Every table some project links to: what the record pages look for. Cached.
     *
     * @return array<int, string>
     */
    public static function allTables(): array
    {
        if (!static::exists()) {
            return [];
        }
        return Cache::remember(static::CACHE_KEY, 300, function () {
            return DB::table(static::TABLES)->distinct()->orderBy('table_name')->pluck('table_name')->map(function ($v) {
                return strval($v);
            })->all();
        });
    }

    /**
     * The tables a project may choose: Exment's own tables of data, not the portal's, not the
     * system's.
     *
     * @return array<int, array{name: string, label: string}>
     */
    public static function candidates(): array
    {
        $own = [];
        $def = PortalDefinition::config();
        foreach (array_keys(PortalDefinition::tableRoles()) as $role) {
            $name = strval(array_get($def, $role, ''));
            if ($name !== '') {
                $own[$name] = true;
            }
        }
        $system = array_flip(array_map('strval', array_values((new \ReflectionClass(SystemTableName::class))->getConstants())));
        $out = [];
        foreach (CustomTable::allRecords() as $table) {
            $name = strval($table->table_name);
            // the portal's, Exment's system tables (by name or by their flag: the safety check's too)
            if (isset($own[$name]) || isset($system[$name]) || boolval($table->system_flg) || $table->hasPermission() !== true) {
                continue;
            }
            $out[] = ['name' => $name, 'label' => strval($table->table_view_name)];
        }
        return $out;
    }

    /**
     * @param array<int, string> $names
     */
    public static function saveTables(int $projectId, array $names, int $userId): ?string
    {
        $allowed = array_column(static::candidates(), 'name');
        $names = array_values(array_unique(array_filter(array_map('strval', $names), function ($n) use ($allowed) {
            return in_array($n, $allowed, true);
        })));
        if (count($names) > static::MAX_TABLES) {
            return 'too_many';
        }
        DB::transaction(function () use ($projectId, $names) {
            DB::table(static::TABLES)->where('project_id', $projectId)->delete();
            foreach ($names as $i => $n) {
                DB::table(static::TABLES)->insert(['project_id' => $projectId, 'table_name' => $n, 'sort' => $i + 1, 'created_at' => now(), 'updated_at' => now()]);
            }
        });
        Cache::forget(static::CACHE_KEY);
        Log::info('pm_portal: the link tables of project ' . $projectId . ' set by user ' . $userId . ' (' . count($names) . ')');

        return null;
    }

    // ---- an issue's links -----------------------------------------------------

    /**
     * The links of an issue, as the person sees them: the title and the address of a record
     * they may read, only 「見られないデータ」 of one they may not.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function linksOf(int $issueId): array
    {
        if (!static::exists()) {
            return [];
        }
        $rows = DB::table(static::LINKS)->where('issue_id', $issueId)->orderBy('id')->get();
        $byTable = [];
        foreach ($rows as $row) {
            $byTable[strval($row->table_name)][] = intval($row->record_id);
        }
        $seen = [];
        $labels = [];
        foreach ($byTable as $name => $ids) {
            $table = CustomTable::getEloquent($name);
            $labels[$name] = isset($table) ? strval($table->table_view_name) : $name;
            if (!isset($table)) {
                continue;
            }
            // Exment's permissions: only what this person may read comes back
            foreach ($table->getValueModel()->query()->whereIn('id', $ids)->get() as $record) {
                $seen[$name][intval($record->id)] = ['t' => strval($record->getLabel()), 'u' => strval($record->getUrl())];
            }
        }
        $out = [];
        foreach ($rows as $row) {
            $name = strval($row->table_name);
            $r = $seen[$name][intval($row->record_id)] ?? null;
            $out[] = [
                'id' => intval($row->id),
                'table' => $name,
                'tl' => $labels[$name] ?? $name,
                // the id of a record the person may not read is not told either
                'rid' => $r !== null ? intval($row->record_id) : null,
                't' => $r['t'] ?? null,
                'u' => $r['u'] ?? null,
                'can' => $r !== null,
            ];
        }
        return $out;
    }

    /** Link an issue to a record. null when done, else why not. */
    public static function add(CustomValue $issue, string $tableName, int $recordId, int $userId): ?string
    {
        if (!in_array($tableName, static::tablesOf(intval($issue->parent_id)), true)) {
            return 'table';
        }
        $table = CustomTable::getEloquent($tableName);
        // a record this person may read, through Exment's permissions
        if (!isset($table) || $table->getValueModel()->query()->find($recordId) === null) {
            return 'record';
        }
        if (DB::table(static::LINKS)->where('issue_id', $issue->id)->count() >= static::MAX_LINKS) {
            return 'too_many';
        }
        DB::table(static::LINKS)->insertOrIgnore([
            'issue_id' => intval($issue->id), 'table_name' => $tableName, 'record_id' => $recordId,
            'created_user_id' => $userId, 'created_at' => now(), 'updated_at' => now(),
        ]);
        return null;
    }

    public static function remove(CustomValue $issue, int $linkId): bool
    {
        return DB::table(static::LINKS)->where('issue_id', $issue->id)->where('id', $linkId)->delete() > 0;
    }

    /** An issue deleted: its links go with it. */
    public static function forgetIssue(int $issueId): void
    {
        if (static::exists()) {
            DB::table(static::LINKS)->where('issue_id', $issueId)->delete();
        }
    }

    /**
     * Records of a table the person may read, found by words, for the picker.
     *
     * @return array<int, array{id: int, t: string}>
     */
    public static function search(string $tableName, string $q): array
    {
        $table = CustomTable::getEloquent($tableName);
        if (!isset($table)) {
            return [];
        }
        $q = trim(mb_substr($q, 0, 100));
        try {
            $found = $q === ''
                ? $table->getValueModel()->query()->orderByDesc('updated_at')->limit(20)->get()
                : $table->searchValue($q, ['maxCount' => 20]);
        } catch (\Throwable $e) {
            Log::warning('pm_portal record link search in ' . $tableName . ' failed (' . get_class($e) . ')');
            return [];
        }
        $out = [];
        foreach ($found ?? [] as $record) {
            $out[] = ['id' => intval($record->id), 't' => strval($record->getLabel())];
        }
        return $out;
    }

    /**
     * The issues linked to a record that the person may read, for its page.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function issuesOf(string $tableName, int $recordId): array
    {
        if (!static::exists() || !in_array($tableName, static::allTables(), true)) {
            return [];
        }
        // a record this person may not read: its page says nothing of it
        $recordTable = CustomTable::getEloquent($tableName);
        if (!isset($recordTable) || $recordTable->getValueModel()->query()->find($recordId) === null) {
            return [];
        }
        $ids = DB::table(static::LINKS)->where('table_name', $tableName)->where('record_id', $recordId)->pluck('issue_id')->map(function ($v) {
                return intval($v);
            })->all();
        if (empty($ids)) {
            return [];
        }
        $def = PortalDefinition::config();
        $issueTable = CustomTable::getEloquent(array_get($def, 'issue_table'));
        $projectTable = CustomTable::getEloquent(array_get($def, 'project_table'));
        if (!isset($issueTable, $projectTable)) {
            return [];
        }
        $out = [];
        $projects = [];
        // Exment's permissions on the issues: only the ones this person may read
        foreach ($issueTable->getValueModel()->query()->whereIn('id', $ids)->orderByDesc('updated_at')->limit(100)->get() as $issue) {
            $pid = intval($issue->parent_id);
            if (!array_key_exists($pid, $projects)) {
                $projects[$pid] = $projectTable->getValueModel()->query()->withoutGlobalScopes()->find($pid);
            }
            $project = $projects[$pid];
            if (!isset($project) || empty(PortalMembership::filterReaders($issue, [intval(\Exment::getUserId())]))) {
                continue;
            }
            $pkey = strtoupper(strval(array_get($project->value, array_get($def, 'project.key'), '')));
            $out[] = [
                'key' => ($pkey !== '' ? $pkey : '#') . '-' . $issue->id,
                's' => strval(array_get($issue->value, array_get($def, 'issue.subject'), '')),
                'st' => strval($issue->getValue(array_get($def, 'issue.status', 'status'), true) ?? ''),
                'p' => strval($project->getLabel()),
                'u' => PortalDefinition::projectUrl($project) . '#issue-' . rawurlencode(PortalDefinition::issueAnchor($issue)),
            ];
        }
        return $out;
    }
}
