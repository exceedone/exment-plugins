<?php

namespace App\Plugins\ExmentPM;

use Carbon\Carbon;
use Exceedone\Exment\Model\CustomColumn;
use Exceedone\Exment\Model\CustomTable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Recurring issues (schema 20): daily, weekdays, weekly, monthly, yearly.
 *
 * The setting lives on one issue at a time, the newest of its chain. When the
 * next turn comes round, a copy of that issue is made with its dates moved on
 * by whole periods, and the setting moves to the copy. The issue it came from
 * is left as it was - it stays open until someone closes it.
 *
 * When the next turn comes round:
 *   - an issue with a start date: on the next start date
 *   - an issue with only a due date: the day after its due date, so the next
 *     one is there for the whole of its own period
 *   - an issue with neither: on the next turn of the day it was created
 * A turn nobody was there for is not made up: after a long gap only the
 * latest one is created, not a pile of old ones.
 *
 * Nothing on the server runs on a clock for this: Exment SaaS does not run
 * plugin batches on a schedule. tick() is called whenever someone opens the
 * portal, at most once every few minutes per Exment, and the batch can run
 * it too (php artisan exment:batch --name=ExmentPM --param1=recur).
 *
 * The copy is recorded as the issue's creator's, not the visitor's whose
 * page load happened to make it, and neither gets a creator's share on it
 * (PortalRunShares::saveNew), and the issue it came from keeps its last-changed
 * time and person: moving the setting is not anyone's edit, and must not
 * make the issue look new to everyone else.
 */
class PortalRecurrence
{
    const KINDS = ['daily', 'weekdays', 'weekly', 'monthly', 'yearly'];

    /** Column on the issue table (PortalSchema::ISSUE_COLUMNS). */
    const COLUMN = 'recurrence';

    /** Minutes between two runs started by page loads. */
    const TICK_MINUTES = 5;

    /** Issues one run creates at most; the next run takes the rest. */
    const RUN_LIMIT = 200;

    /**
     * Run when the last run is a few minutes old. Never throws: a page load
     * must not fail because a copy could not be made.
     */
    public static function tick(): void
    {
        try {
            $key = 'pmportal_recur:' . DB::connection()->getDatabaseName();
            if (!Cache::add($key, 1, now()->addMinutes(self::TICK_MINUTES))) {
                return;
            }
            static::run();
        } catch (\Throwable $e) {
            Log::warning('PmPortal recurrence: ' . $e->getMessage());
        }
    }

    /**
     * Create every issue whose turn has come.
     *
     * @param bool $dry only say what would be created
     * @param Carbon|null $today for tests
     * @return array<int, array<string, mixed>> one entry per issue created (or that would be)
     */
    public static function run(bool $dry = false, ?Carbon $today = null): array
    {
        PortalRunOwner::forget();
        $def = PortalDefinition::config();
        $issue_table = CustomTable::getEloquent(array_get($def, 'issue_table'));
        $project_table = CustomTable::getEloquent(array_get($def, 'project_table'));
        if (!isset($issue_table) || !isset($project_table)) {
            return [];
        }
        $column = CustomColumn::getEloquent(static::columnName(), $issue_table);
        if (!isset($column)) {
            return [];
        }

        $today = ($today ?? Carbon::today())->copy()->startOfDay();
        $query = $issue_table->getValueModel()->newQuery()
            ->where('parent_type', $project_table->table_name);
        $index = $column->index_enabled ? $column->getIndexColumnName() : null;
        if ($index) {
            $query->whereNotNull($index)->where($index, '<>', '');
        } else {
            $query->whereNotNull('value->' . static::columnName());
        }

        $out = [];
        $projects = [];
        foreach ($query->orderBy('id')->get() as $issue) {
            if (count($out) >= self::RUN_LIMIT) {
                break;
            }
            $plan = static::plan($issue->value, $issue->created_at, $def, $today);
            if ($plan === null) {
                continue;
            }
            $pid = strval($issue->parent_id);
            if (!array_key_exists($pid, $projects)) {
                $projects[$pid] = $project_table->getValueModel()->find($issue->parent_id);
            }
            $project = $projects[$pid];
            if (!isset($project) || boolval(array_get($project->value, 'archived'))) {
                continue;
            }
            if ($dry) {
                $out[] = ['from' => $issue->id, 'value' => $plan];
                continue;
            }
            $id = static::copy($issue_table, $issue, $project, $plan, $def);
            if ($id !== null) {
                $out[] = ['from' => $issue->id, 'id' => $id, 'value' => $plan];
            }
        }

        return $out;
    }

    public static function columnName(): string
    {
        return strval(array_get(PortalDefinition::config(), 'issue_extra.recurrence', PortalSchema::ISSUE_COLUMNS['recurrence']));
    }

    /**
     * The dates of the next issue, if its turn has come by $today.
     *
     * @param array<string, mixed> $value the issue's values
     * @param mixed $createdAt
     * @param array<string, mixed> $def
     * @return array<string, string|null>|null [start => Y-m-d|null, due => Y-m-d|null]
     */
    public static function plan($value, $createdAt, array $def, Carbon $today): ?array
    {
        $kind = strval(array_get($value, static::columnName(), ''));
        if (!in_array($kind, self::KINDS, true)) {
            return null;
        }
        $start = static::date(array_get($value, array_get($def, 'issue.start')));
        $due = static::date(array_get($value, array_get($def, 'issue.due')));

        if (isset($start)) {
            $n = static::stepsUpTo($kind, $start, $today);
            if ($n < 1) {
                return null;
            }
        } elseif (isset($due)) {
            // The next one appears the day after this one was due.
            $n = static::stepsUpTo($kind, $due, $today->copy()->subDay());
            if ($n < 0) {
                return null;
            }
            $n++;
        } else {
            $created = static::date($createdAt);
            if (!isset($created)) {
                return null;
            }
            $n = static::stepsUpTo($kind, $created, $today);
            if ($n < 1) {
                return null;
            }

            return ['start' => null, 'due' => null];
        }

        return [
            'start' => isset($start) ? static::advance($kind, $start, $n)->format('Y-m-d') : null,
            'due' => isset($due) ? static::advance($kind, $due, $n)->format('Y-m-d') : null,
        ];
    }

    /**
     * How many turns after $base the latest one on or before $ref is:
     * 0 when $ref is $base itself or before the first turn, -1 when $ref is
     * before $base.
     */
    public static function stepsUpTo(string $kind, Carbon $base, Carbon $ref): int
    {
        if ($ref->lt($base)) {
            return -1;
        }
        $days = intval($base->diffInDays($ref));
        switch ($kind) {
            case 'daily':
                return $days;
            case 'weekly':
                return intdiv($days, 7);
            case 'weekdays':
                $n = 0;
                // Count whole weeks at once, then walk the rest.
                $weeks = intdiv($days, 7);
                $n += $weeks * 5;
                $d = $base->copy()->addDays($weeks * 7);
                while ($d->lt($ref)) {
                    $d->addDay();
                    if (!$d->isWeekend()) {
                        $n++;
                    }
                }

                return $n;
            case 'monthly':
            case 'yearly':
                $per = $kind === 'monthly' ? 1 : 12;
                $n = intdiv(($ref->year - $base->year) * 12 + ($ref->month - $base->month), $per) + 1;
                while ($n > 0 && static::advance($kind, $base, $n)->gt($ref)) {
                    $n--;
                }

                return $n;
        }

        return 0;
    }

    /** $base moved on by $n turns. */
    public static function advance(string $kind, Carbon $base, int $n): Carbon
    {
        $d = $base->copy();
        switch ($kind) {
            case 'daily':
                return $d->addDays($n);
            case 'weekly':
                return $d->addDays(7 * $n);
            case 'weekdays':
                while ($n > 0) {
                    $d->addDay();
                    if (!$d->isWeekend()) {
                        $n--;
                    }
                }

                return $d;
            case 'monthly':
                return $d->addMonthsNoOverflow($n);
            case 'yearly':
                return $d->addYearsNoOverflow($n);
        }

        return $d;
    }

    /**
     * @param mixed $raw
     */
    protected static function date($raw): ?Carbon
    {
        if ($raw instanceof \DateTimeInterface) {
            return Carbon::instance($raw)->startOfDay();
        }
        $raw = trim(strval($raw ?? ''));
        if ($raw === '') {
            return null;
        }
        try {
            return Carbon::parse(substr($raw, 0, 10))->startOfDay();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Make the next issue and move the setting to it.
     *
     * The setting is taken off the old issue first, in one statement that
     * only succeeds while it is still there, so two runs at once cannot
     * both make a copy. A copy that fails puts it back.
     *
     * @return int|null the new issue's id
     */
    protected static function copy(CustomTable $issue_table, $issue, $project, array $plan, array $def): ?int
    {
        // made as the issue's creator (or the project's first manager), never as the visitor
        $owner = null;
        $blocked = PortalRunOwner::check($project, intval($issue->created_user_id) ?: null, $issue_table, $owner);
        $logKey = 'pmportal_recur_block:' . DB::connection()->getDatabaseName() . ':' . $issue->id;
        if ($blocked !== null || $owner === null) {
            // the setting stays on the issue; said once a day at most
            if (Cache::add($logKey, 1, now()->addDay())) {
                Log::warning('PmPortal recurrence: issue ' . $issue->id . ' makes no copy (' . $blocked . '): nobody on the project to make it as, or they may not add issues');
            }

            return null;
        }
        Cache::forget($logKey);

        $col = static::columnName();
        $kind = strval(array_get($issue->value, $col));
        $db = getDBTableName($issue_table);
        $path = '$."' . $col . '"';
        $claimed = DB::table($db)->where('id', $issue->id)
            ->whereRaw('JSON_EXTRACT(value, ?) IS NOT NULL', [$path])
            ->update(['value' => DB::raw("JSON_REMOVE(value, '" . $path . "')")]);
        if ($claimed !== 1) {
            return null;
        }

        try {
            $value = static::copyValues($issue_table, $issue, $project, $plan, $def);
            $row = $issue_table->getValueModel();
            $row->saved_notify(false);
            $row->parent_id = $issue->parent_id;
            $row->parent_type = $issue->parent_type;
            $row->setValue($value);
            // Made as its first issue's creator (or the project's first manager), with no creator's
            // share for anyone: they reach it the way every member of the project does.
            PortalRunOwner::saveAs($row, $owner);

            return intval($row->id);
        } catch (\Throwable $e) {
            DB::table($db)->where('id', $issue->id)
                ->update(['value' => DB::raw("JSON_SET(value, '" . $path . "', " . DB::getPdo()->quote($kind) . ')')]);
            Log::warning('PmPortal recurrence: issue ' . $issue->id . ': ' . $e->getMessage());

            return null;
        }
    }

    /**
     * An issue's values without what belongs to that issue alone: its files, its key (an auto
     * number: Exment numbers only an empty one, so a copy that kept it would show the same key as
     * the issue it came from, where a new issue gets the next one), where it was imported from, the
     * work done on it and how it ended. The imported issue's own data goes too: its custom fields'
     * text is written by the sync of the issue it came from, which the copy is not.
     *
     * @param array<string, mixed> $value
     * @param array<string, string> $types column name => column type
     * @param array<string, mixed> $def
     * @return array<string, mixed>
     */
    public static function withoutOwn(array $value, array $types, array $def): array
    {
        foreach ($types as $name => $type) {
            if (in_array($type, ['file', 'image', 'auto_number'], true)) {
                unset($value[$name]);
            }
        }
        $is = (array) ($def['issue'] ?? []);
        $extra = (array) ($def['issue_extra'] ?? PortalSchema::ISSUE_COLUMNS);
        $names = array_merge(
            [static::keyColumn($def), $is['actual'] ?? null, $extra['resolution'] ?? null, $extra['custom_fields'] ?? PortalSchema::ISSUE_COLUMNS['custom_fields']],
            static::sourceColumns($def)
        );
        foreach ($names as $name) {
            if (!empty($name)) {
                unset($value[$name]);
            }
        }

        return $value;
    }

    /** @param array<string, mixed> $def */
    public static function keyColumn(array $def): string
    {
        return strval(($def['issue'] ?? [])['key'] ?? 'issue_key');
    }

    /**
     * The columns that say where an imported issue came from (source_key, source_url, ...).
     *
     * @param array<string, mixed> $def
     * @return array<int, string>
     */
    public static function sourceColumns(array $def): array
    {
        $extra = (array) ($def['issue_extra'] ?? PortalSchema::ISSUE_COLUMNS);
        $out = [];
        foreach (PortalSchema::ISSUE_COLUMNS as $k => $name) {
            if (strpos($k, 'source_') === 0) {
                $out[] = strval($extra[$k] ?? $name);
            }
        }

        return $out;
    }

    /**
     * The issues that show the key of an older issue: copies made before the copy left the key
     * out. $rows in any order: [id, project, key, source_key]. An issue whose key an older one
     * has gets a new key ('key'); one whose source_key an older issue of the same project has
     * loses where it was imported from ('source'), as the copy was not imported. The oldest of
     * each keeps its own.
     *
     * @param array<int, array{id: int, project: int, key: string, source: string}> $rows
     * @return array<int, array{key: bool, source: bool, dup_of: int}> issue id => what to clear
     */
    public static function duplicateKeys(array $rows): array
    {
        usort($rows, function ($a, $b) {
            return $a['id'] <=> $b['id'];
        });
        $keys = [];
        $sources = [];
        $out = [];
        foreach ($rows as $r) {
            $key = trim($r['key']);
            $src = trim($r['source']);
            $k = $key !== '' && isset($keys[$key]);
            $s = $src !== '' && isset($sources[$r['project'] . "\0" . $src]);
            if ($k || $s) {
                $out[$r['id']] = ['key' => $k, 'source' => $s, 'dup_of' => $k ? $keys[$key] : $sources[$r['project'] . "\0" . $src]];
            }
            if ($key !== '' && !isset($keys[$key])) {
                $keys[$key] = $r['id'];
            }
            if ($src !== '' && !isset($sources[$r['project'] . "\0" . $src])) {
                $sources[$r['project'] . "\0" . $src] = $r['id'];
            }
        }

        return $out;
    }

    /**
     * Find (and with $apply, fix) the issues that show the key of an older issue (duplicateKeys):
     * the key is cleared and Exment numbers it again on the save, as for a new issue; where it was
     * imported from is cleared where it is the older issue's too. Deleted issues are left out.
     * Safe to run again: a fixed issue has a key of its own.
     *
     * @return array{issues: int, found: array<int, array<string, mixed>>, fixed: int, failed: array<int, int>}
     */
    public static function repairKeys(bool $apply): array
    {
        $out = ['issues' => 0, 'found' => [], 'fixed' => 0, 'failed' => []];
        $def = PortalDefinition::config();
        $issueTable = CustomTable::getEloquent(array_get($def, 'issue_table'));
        if (!isset($issueTable)) {
            return $out;
        }
        $keyCol = static::keyColumn($def);
        $srcCol = strval(array_get($def, 'issue_extra.source_key', PortalSchema::ISSUE_COLUMNS['source_key']));
        $rows = [];
        $query = $issueTable->getValueModel()->query()->withoutGlobalScope(\Exceedone\Exment\Model\CustomValueModelScope::class);
        $query->select(['id', 'parent_id', 'value'])->chunkById(500, function ($chunk) use (&$rows, $keyCol, $srcCol) {
            foreach ($chunk as $issue) {
                $v = (array) $issue->value;
                $rows[] = [
                    'id' => intval($issue->id),
                    'project' => intval($issue->parent_id),
                    'key' => is_scalar($v[$keyCol] ?? null) ? strval($v[$keyCol]) : '',
                    'source' => is_scalar($v[$srcCol] ?? null) ? strval($v[$srcCol]) : '',
                ];
            }
        });
        $out['issues'] = count($rows);
        $byId = array_column($rows, null, 'id');
        foreach (static::duplicateKeys($rows) as $id => $d) {
            $out['found'][] = $d + ['issue' => $id, 'old_key' => $byId[$id]['key'], 'source_key' => $byId[$id]['source']];
        }
        if (!$apply) {
            Log::info('ExmentPM schedule keys repair (dry run): issues ' . $out['issues'] . ', found ' . count($out['found']));

            return $out;
        }
        foreach ($out['found'] as $f) {
            try {
                $issue = $issueTable->getValueModel()->query()->withoutGlobalScope(\Exceedone\Exment\Model\CustomValueModelScope::class)->find($f['issue']);
                if (!isset($issue)) {
                    continue;
                }
                $clear = [];
                if ($f['key']) {
                    $clear[$keyCol] = null;
                }
                if ($f['source']) {
                    foreach (static::sourceColumns($def) as $c) {
                        $clear[$c] = null;
                    }
                }
                $issue->saved_notify(false);
                $issue->setValue($clear);
                $issue->save();
                if ($f['key']) {
                    // Exment numbers an empty auto number on the save; a key column that is not one stays empty
                    $fresh = $issueTable->getValueModel()->newQueryWithoutScopes()->find($f['issue']);
                    $new = isset($fresh) ? strval(array_get((array) $fresh->value, $keyCol, '')) : '';
                    if ($new === '' || $new === $f['old_key']) {
                        $out['failed'][] = intval($f['issue']);
                        continue;
                    }
                }
                $out['fixed']++;
            } catch (\Throwable $e) {
                $out['failed'][] = intval($f['issue']);
                Log::warning('ExmentPM schedule keys repair: issue ' . $f['issue'] . ': ' . $e->getMessage());
            }
        }
        Log::info('ExmentPM schedule keys repair: issues ' . $out['issues'] . ', found ' . count($out['found'])
            . ', fixed ' . $out['fixed'] . ', failed ' . count($out['failed']));

        return $out;
    }

    /**
     * What the next issue starts with: the same issue, opened afresh.
     *
     * @return array<string, mixed>
     */
    public static function copyValues(CustomTable $issue_table, $issue, $project, array $plan, array $def): array
    {
        $is = (array) array_get($def, 'issue', []);
        $types = [];
        foreach ($issue_table->custom_columns as $c) {
            $types[strval($c->column_name)] = strval($c->column_type);
        }
        $value = static::withoutOwn((array) $issue->value, $types, $def);
        if (!empty(array_get($is, 'progress')) && array_key_exists($is['progress'], $value)) {
            $value[$is['progress']] = 0;
        }
        foreach (['start', 'due'] as $k) {
            if (!empty(array_get($is, $k)) && isset($plan[$k])) {
                $value[$is[$k]] = $plan[$k];
            }
        }
        $value[static::columnName()] = strval(array_get($issue->value, static::columnName()));

        // Back to the project's first status (未対応).
        $status_table = CustomTable::getEloquent(array_get($def, 'status_table'));
        if (!empty(array_get($is, 'status')) && isset($status_table)) {
            $order = array_get(PortalSchema::COLUMNS, 'status.order');
            $q = $status_table->getValueModel()->newQuery()
                ->where('parent_type', $project->custom_table->table_name)
                ->where('parent_id', $project->id);
            if (isset($order) && CustomColumn::getEloquent($order, $status_table) !== null) {
                $q->orderByRaw("CAST(JSON_UNQUOTE(JSON_EXTRACT(value, '$." . $order . "')) AS UNSIGNED) asc");
            }
            $first = $q->orderBy('id')->first();
            if (isset($first)) {
                $value[$is['status']] = strval($first->id);
            }
        }

        return $value;
    }
}
