<?php

namespace App\Plugins\ExmentGroupware\Calendar;

use Exceedone\Exment\Model\CustomTable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ExmentPM on the calendar (ISS02947, part 4), read only. The issues with a start or a due date
 * (a bar from the start to the due date, or a mark on the due date), the milestones, and the
 * dates of the recurring schedules that have no issue yet (「予定（まだ課題になっていない）」).
 *
 * Read as data, the way the chat's 「課題にする」 reads ExmentPM: Exment's tables (project,
 * pm_issue, task_milestone, pm_status) through Exment's own permission scope, so a person sees
 * only the issues, milestones and projects they may read; and ExmentPM's tables of the schedules
 * (pm_portal_schedules, pm_portal_schedule_exceptions, pm_portal_schedule_items) for the projects
 * they may read. No code of ExmentPM is used. Off when the site has no ExmentPM.
 *
 * The names of the tables and columns are ExmentPM's defaults, or the ones its setup stored
 * (the plugin's options, portal_setup.tables), or the site's config('exment.pmportal').
 */
class PmLayer
{
    const PLUGIN = 'ExmentPM';
    const SCHEDULES = 'pm_portal_schedules';
    const EXCEPTIONS = 'pm_portal_schedule_exceptions';
    const ITEMS = 'pm_portal_schedule_items';
    const SCHEDULE_FEATURE = 'exment_pm.schedule';
    const FEATURES = 'Exceedone\Exment\Services\Features';

    const FILTERS = ['mine', 'projects', 'project'];
    const MAX_PROJECTS = 500;
    const MAX_ISSUES = 1000;
    const MAX_MILESTONES = 300;
    const MAX_SCHEDULE_DATES = 500;
    const MAX_WINDOW_DAYS = 400;
    /** How far a date of a schedule may be moved from its own (its exceptions) and still be found. */
    const MOVE_DAYS = 92;
    /** The days a date off is moved at most to find a working day (as ExmentPM). */
    const SEARCH_LIMIT = 366;
    const DONE_WORDS = ['resolved', 'closed'];

    /** One colour a project: the same project, the same colour, on every page. */
    const COLORS = ['#2563c9', '#0f8a6a', '#b45309', '#9333ea', '#c2410c', '#0e7490', '#be185d', '#4d7c0f', '#6d28d9', '#a16207', '#0369a1', '#b91c1c'];

    // ---- without the database (groupware/tools/test-gw-calendar.php) ------------------

    public static function color(int $projectId): string
    {
        return static::COLORS[abs($projectId) % count(static::COLORS)];
    }

    /**
     * The days of an issue on the calendar: a bar from the start to the due date (both shown),
     * a mark on the due date when there is no start (or the start is after it), the start alone
     * when there is no due date. end is the day after the last.
     *
     * @return array{start: string, end: string, kind: string}|null
     */
    public static function span($start, $due): ?array
    {
        $s = Rrule::day($start);
        $d = Rrule::day($due);
        if ($s !== null && $d !== null && strcmp($s, $d) <= 0) {
            return ['start' => $s, 'end' => static::next($d), 'kind' => 'bar'];
        }
        if ($d !== null) {
            return ['start' => $d, 'end' => static::next($d), 'kind' => 'due'];
        }
        if ($s !== null) {
            return ['start' => $s, 'end' => static::next($s), 'kind' => 'start'];
        }
        return null;
    }

    public static function next(string $day, int $n = 1): string
    {
        return (new \DateTimeImmutable($day))->modify(($n >= 0 ? '+' : '') . $n . ' day')->format('Y-m-d');
    }

    /** Whether days from $from to $to (the day after the last) meet the window. */
    public static function inWindow(array $span, string $from, string $to): bool
    {
        return strcmp($span['start'], $to) < 0 && strcmp($span['end'], $from) > 0;
    }

    /**
     * Whether a user column (one user, several, or the JSON of them) names the user.
     *
     * @param mixed $value
     */
    public static function names($value, int $user): bool
    {
        if ($user <= 0 || $value === null || $value === '') {
            return false;
        }
        if (is_string($value) && strlen($value) > 0 && ($value[0] === '[' || $value[0] === '{')) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : $value;
        }
        foreach ((array) $value as $v) {
            if (is_scalar($v) && strval($v) === strval($user)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Whether the person is on a project: its manager, a member, a viewer, or in one of its
     * organizations.
     *
     * @param array<string, mixed> $values the project's values
     * @param array<string, string> $cols project's columns (manager, members, viewers, member_orgs, viewer_orgs)
     * @param array<int, int> $orgs the person's organizations
     */
    public static function onProject(array $values, array $cols, int $user, array $orgs): bool
    {
        foreach (['manager', 'members', 'viewers'] as $k) {
            if (isset($cols[$k]) && static::names($values[$cols[$k]] ?? null, $user)) {
                return true;
            }
        }
        foreach (['member_orgs', 'viewer_orgs'] as $k) {
            foreach ($orgs as $org) {
                if (isset($cols[$k]) && static::names($values[$cols[$k]] ?? null, intval($org))) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Whether an issue's status is a done one: the row of the status list says so (is_done), or,
     * on a site whose status is still a word, the word is one of ExmentPM's done words.
     *
     * @param mixed $status
     * @param array<int, int> $doneIds
     */
    public static function done($status, array $doneIds): bool
    {
        if (is_array($status)) {
            $status = reset($status);
        }
        if (!is_scalar($status) || strval($status) === '') {
            return false;
        }
        if (ctype_digit(strval($status))) {
            return in_array(intval($status), $doneIds, true);
        }
        return in_array(strtolower(strval($status)), static::DONE_WORDS, true);
    }

    /**
     * Whether a day is a working day, as ExmentPM's business calendar: the company's own day
     * first (a working day, or a day off), then Saturday and Sunday, then the national holidays.
     *
     * @param array<string, array{kind: string}> $days Holidays::between()
     */
    public static function workingDay(string $day, array $days): bool
    {
        $kind = $days[$day]['kind'] ?? null;
        if ($kind === 'work') {
            return true;
        }
        if ($kind === 'off' || $kind === 'holiday') {
            return false;
        }
        return !in_array(intval((new \DateTimeImmutable($day))->format('w')), [0, 6], true);
    }

    /**
     * A date of a schedule that falls on a day off, as the schedule says: kept (keep), moved to
     * the working day before (before) or after (after), or not made (skip: null).
     *
     * @param array<string, array{kind: string}> $days
     */
    public static function shifted(string $day, string $how, array $days): ?string
    {
        if ($how === 'skip') {
            return static::workingDay($day, $days) ? $day : null;
        }
        if (!in_array($how, ['before', 'after'], true) || static::workingDay($day, $days)) {
            return $day;
        }
        $step = $how === 'before' ? -1 : 1;
        $d = $day;
        for ($i = 0; $i < static::SEARCH_LIMIT; $i++) {
            $d = static::next($d, $step);
            if (static::workingDay($d, $days)) {
                return $d;
            }
        }
        return $day;
    }

    /**
     * The dates of a schedule from $from to $to (the day after the last) that have no issue yet,
     * not before today: each date as its rule says, moved or skipped by its exceptions, moved on a
     * day off as the schedule says; minus the dates made into issues already (by the date of the
     * rule, as ExmentPM keeps them).
     *
     * @param array<string, mixed> $s a row of pm_portal_schedules
     * @param array<string, ?string> $exceptions the date of the rule => the date moved to (null: skipped)
     * @param array<int, string> $made the dates of the rule that have an issue
     * @param array<string, array{kind: string}> $days Holidays::between() of the window and MOVE_DAYS around it
     * @return array<int, array{original: string, date: string}>
     */
    public static function scheduleDates(array $s, string $from, string $to, string $today, array $exceptions, array $made, array $days): array
    {
        $first = strcmp($from, $today) > 0 ? $from : $today;
        if (strcmp($first, $to) >= 0) {
            return [];
        }
        $rdates = json_decode(strval($s['rdates'] ?? ''), true);
        $originals = Rrule::between(strval($s['rrule'] ?? ''), strval($s['start_date'] ?? ''), static::next($first, -static::MOVE_DAYS), static::next($to, static::MOVE_DAYS), is_array($rdates) ? $rdates : [], isset($s['end_date']) ? strval($s['end_date']) : null);
        if ($originals === null) {
            return [];
        }
        $made = array_flip(array_map('strval', $made));
        $out = [];
        foreach ($originals as $original) {
            if (isset($made[$original])) {
                continue;
            }
            $date = array_key_exists($original, $exceptions) ? $exceptions[$original] : static::shifted($original, strval($s['shift'] ?? 'keep'), $days);
            if ($date === null || strcmp($date, $first) < 0 || strcmp($date, $to) >= 0) {
                continue;
            }
            $out[] = ['original' => $original, 'date' => $date];
            if (count($out) >= static::MAX_SCHEDULE_DATES) {
                break;
            }
        }
        usort($out, function ($a, $b) {
            return strcmp($a['date'], $b['date']);
        });
        return $out;
    }

    // ---- ExmentPM's names ---------------------------------------------------------------

    /** @var array<string, mixed>|null */
    protected static $def;

    /** @return array<string, mixed> */
    public static function def(): array
    {
        if (static::$def !== null) {
            return static::$def;
        }
        $default = [
            'project_table' => 'project',
            'issue_table' => 'pm_issue',
            'milestone_table' => 'task_milestone',
            'status_table' => 'pm_status',
            'project' => ['key' => 'project_code', 'name' => 'name', 'manager' => 'manager', 'members' => 'members', 'viewers' => 'viewers', 'member_orgs' => 'member_orgs', 'viewer_orgs' => 'viewer_orgs'],
            'issue' => ['key' => 'issue_key', 'subject' => 'subject', 'status' => 'status', 'assignees' => 'assignees', 'start' => 'start_date', 'due' => 'due_date'],
            'milestone' => ['name' => 'name', 'start' => 'start_date', 'due' => 'due_date', 'project' => 'project'],
            'status_row' => ['done' => 'is_done'],
            'project_extra' => ['archived' => 'archived'],
        ];
        $tables = [];
        try {
            $pm = static::plugin();
            $stored = isset($pm) ? array_get((array) $pm->options, 'portal_setup.tables', []) : [];
            foreach (is_array($stored) ? $stored : [] as $role => $name) {
                if (is_string($name) && $name !== '' && array_key_exists($role, $default) && is_string($default[$role])) {
                    $tables[$role] = $name;
                }
            }
        } catch (\Throwable $e) {
            // the defaults
        }
        return static::$def = array_replace_recursive($default, $tables, (array) config('exment.pmportal', []));
    }

    /** @return \Exceedone\Exment\Model\Plugin|null ExmentPM's row, when it is installed */
    protected static function plugin()
    {
        try {
            return \Exceedone\Exment\Model\Plugin::where('plugin_name', static::PLUGIN)->first();
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected static function table(string $role): ?CustomTable
    {
        try {
            $name = strval(static::def()[$role] ?? '');
            return $name === '' ? null : CustomTable::getEloquent($name);
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected static function col(string $group, string $key): string
    {
        return strval(array_get(static::def(), $group . '.' . $key, ''));
    }

    /** Whether the site has ExmentPM's project and issue tables. */
    public static function ready(): bool
    {
        return static::table('issue_table') !== null && static::table('project_table') !== null;
    }

    /** Whether ExmentPM makes the issues of its schedules on this site (its Feature management, else its setting). */
    public static function schedulesOn(): bool
    {
        try {
            if (!\Schema::hasTable(static::SCHEDULES) || !\Schema::hasTable(static::ITEMS) || !\Schema::hasTable(static::EXCEPTIONS)) {
                return false;
            }
            if (class_exists(static::FEATURES)) {
                return boolval(call_user_func([static::FEATURES, 'enabled'], static::SCHEDULE_FEATURE));
            }
            $pm = static::plugin();
            return isset($pm) && boolval(intval($pm->getCustomOption('schedule_on')));
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Where ExmentPM's pages are: its plugin's address. */
    protected static function pmUrl(string ...$parts): string
    {
        $pm = static::plugin();
        if (isset($pm) && method_exists($pm, 'getFullUrl')) {
            try {
                return strval($pm->getFullUrl(...$parts));
            } catch (\Throwable $e) {
                // the default address
            }
        }
        return admin_url('plugins/exment_pm/' . implode('/', array_map('rawurlencode', $parts)));
    }

    // ---- with the database -----------------------------------------------------------------

    /**
     * The projects the person may read (Exment's permission scope), not archived: id, name, colour,
     * and whether they are on it.
     *
     * @return array<int, array{id: int, name: string, color: string, mine: bool, manager: bool}>
     */
    public static function projects(int $me): array
    {
        $t = static::table('project_table');
        if (!isset($t)) {
            return [];
        }
        $cols = (array) array_get(static::def(), 'project', []);
        $archived = static::col('project_extra', 'archived');
        $orgs = Events::orgsOf($me);
        $out = [];
        try {
            foreach ($t->getValueModel()->query()->orderBy('id')->limit(static::MAX_PROJECTS)->get() as $p) {
                $v = (array) $p->value;
                if ($archived !== '' && !empty($v[$archived])) {
                    continue;
                }
                $code = trim(strval($v[$cols['key'] ?? ''] ?? ''));
                $name = trim(strval($v[$cols['name'] ?? ''] ?? ''));
                $id = intval($p->id);
                $out[] = [
                    'id' => $id,
                    'name' => trim(($code !== '' ? $code . ' ' : '') . $name) ?: '#' . $id,
                    'color' => static::color($id),
                    'mine' => static::onProject($v, $cols, $me, $orgs),
                    'manager' => isset($cols['manager']) && static::names($v[$cols['manager']] ?? null, $me),
                ];
            }
        } catch (\Throwable $e) {
            Log::warning('gw_calendar pm projects: ' . get_class($e));
        }
        return $out;
    }

    /**
     * What the calendar shows of ExmentPM from $from to $to (days; $to the day after the last).
     *
     * filter: mine (the issues the person is assigned, of any project they may read, and the
     * schedules whose template they are assigned), projects (everything of the projects they are
     * on), project (everything of one project they may read).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function window(int $me, string $from, string $to, string $filter, ?int $projectId, string $today, bool $admin): array
    {
        $projects = [];
        foreach (static::projects($me) as $p) {
            if ($filter === 'mine' || ($filter === 'projects' && $p['mine']) || ($filter === 'project' && $p['id'] === $projectId)) {
                $projects[$p['id']] = $p;
            }
        }
        if (empty($projects)) {
            return [];
        }
        $out = array_merge(
            static::issues($me, $from, $to, $projects, $filter === 'mine'),
            $filter === 'mine' ? [] : static::milestones($from, $to, $projects),
            static::schedules($me, $from, $to, $projects, $filter === 'mine', $today, $admin)
        );
        usort($out, function ($a, $b) {
            return strcmp($a['start'], $b['start']) ?: strcmp($a['title'], $b['title']);
        });
        return $out;
    }

    /**
     * The window's condition on two date columns of the values: a date in it, or a span over it.
     *
     * @param \Illuminate\Database\Eloquent\Builder $q
     */
    protected static function whereDates($q, string $start, string $due, string $from, string $to): void
    {
        $last = static::next($to, -1);
        $q->where(function ($w) use ($start, $due, $from, $last) {
            if ($due !== '') {
                $w->orWhere(function ($a) use ($due, $from, $last) {
                    $a->where('value->' . $due, '>=', $from)->where('value->' . $due, '<=', $last);
                });
            }
            if ($start !== '') {
                $w->orWhere(function ($b) use ($start, $from, $last) {
                    $b->where('value->' . $start, '>=', $from)->where('value->' . $start, '<=', $last);
                });
            }
            if ($start !== '' && $due !== '') {
                $w->orWhere(function ($c) use ($start, $due, $from, $last) {
                    $c->where('value->' . $start, '<', $from)->where('value->' . $due, '>', $last);
                });
            }
        });
    }

    /** @return array<int, int> the rows of the status list that are done */
    protected static function doneIds(): array
    {
        $t = static::table('status_table');
        $col = static::col('status_row', 'done');
        if (!isset($t) || $col === '') {
            return [];
        }
        try {
            // the names of the statuses, not data of anyone: read without the scope
            return $t->getValueModel()->query()->withoutGlobalScopes()->limit(1000)->get()->filter(function ($r) use ($col) {
                return boolval(intval(array_get((array) $r->value, $col)));
            })->map(function ($r) {
                return intval($r->id);
            })->values()->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @param array<int, array<string, mixed>> $projects
     * @return array<int, array<string, mixed>>
     */
    protected static function issues(int $me, string $from, string $to, array $projects, bool $mineOnly): array
    {
        $t = static::table('issue_table');
        if (!isset($t)) {
            return [];
        }
        $c = (array) array_get(static::def(), 'issue', []);
        $start = strval($c['start'] ?? '');
        $due = strval($c['due'] ?? '');
        $out = [];
        try {
            // Exment's permission scope: the issues the person may read
            $q = $t->getValueModel()->query()
                ->where('parent_type', strval(static::def()['project_table']))
                ->whereIn('parent_id', array_keys($projects));
            static::whereDates($q, $start, $due, $from, $to);
            $doneIds = static::doneIds();
            foreach ($q->orderBy('id')->limit(static::MAX_ISSUES)->get() as $row) {
                $v = (array) $row->value;
                if ($mineOnly && !static::names($v[$c['assignees'] ?? ''] ?? null, $me)) {
                    continue;
                }
                $span = static::span($v[$start] ?? null, $v[$due] ?? null);
                $p = $projects[intval($row->parent_id)] ?? null;
                if ($span === null || $p === null || !static::inWindow($span, $from, $to)) {
                    continue;
                }
                $key = trim(strval($v[$c['key'] ?? ''] ?? ''));
                $key = preg_match('/^[A-Za-z0-9_\-]{1,64}$/', $key) ? $key : '';
                $subject = trim(strval($v[$c['subject'] ?? ''] ?? ''));
                $out[] = $span + [
                    'pm' => 'issue',
                    'all_day' => true,
                    'title' => ($key !== '' ? $key . ' ' : '') . ($subject !== '' ? $subject : '#' . $row->id),
                    'project' => $p['id'],
                    'project_name' => $p['name'],
                    'color' => $p['color'],
                    'due' => Rrule::day($v[$due] ?? null),
                    'done' => static::done($v[$c['status'] ?? ''] ?? null, $doneIds),
                    'url' => static::pmUrl(strval($p['id'])) . '#issue-' . rawurlencode($key !== '' ? $key : strval($row->id)),
                ];
            }
        } catch (\Throwable $e) {
            Log::warning('gw_calendar pm issues: ' . get_class($e) . ' ' . $e->getMessage());
        }
        return $out;
    }

    /**
     * @param array<int, array<string, mixed>> $projects
     * @return array<int, array<string, mixed>>
     */
    protected static function milestones(string $from, string $to, array $projects): array
    {
        $t = static::table('milestone_table');
        if (!isset($t)) {
            return [];
        }
        $c = (array) array_get(static::def(), 'milestone', []);
        $due = strval($c['due'] ?? '');
        $start = strval($c['start'] ?? '');
        $out = [];
        try {
            $q = $t->getValueModel()->query();
            // a milestone is a day: its due date (or its start when it has none)
            static::whereDates($q, '', $due !== '' ? $due : $start, $from, $to);
            foreach ($q->orderBy('id')->limit(static::MAX_MILESTONES)->get() as $row) {
                $v = (array) $row->value;
                $pid = $v[$c['project'] ?? ''] ?? null;
                $pid = is_array($pid) ? reset($pid) : $pid;
                $p = $projects[intval($pid ?: $row->parent_id)] ?? null;
                $day = Rrule::day($v[$due] ?? null) ?? Rrule::day($v[$start] ?? null);
                if ($p === null || $day === null || strcmp($day, $from) < 0 || strcmp($day, $to) >= 0) {
                    continue;
                }
                $out[] = [
                    'pm' => 'milestone',
                    'start' => $day,
                    'end' => static::next($day),
                    'kind' => 'due',
                    'all_day' => true,
                    'title' => trim(strval($v[$c['name'] ?? ''] ?? '')) ?: '#' . $row->id,
                    'project' => $p['id'],
                    'project_name' => $p['name'],
                    'color' => $p['color'],
                    'url' => static::pmUrl(strval($p['id'])),
                ];
            }
        } catch (\Throwable $e) {
            Log::warning('gw_calendar pm milestones: ' . get_class($e) . ' ' . $e->getMessage());
        }
        return $out;
    }

    /**
     * The dates of the schedules of the projects that have no issue yet.
     *
     * @param array<int, array<string, mixed>> $projects
     * @return array<int, array<string, mixed>>
     */
    protected static function schedules(int $me, string $from, string $to, array $projects, bool $mineOnly, string $today, bool $admin): array
    {
        if (!static::schedulesOn() || strcmp($to, $today) <= 0) {
            return [];
        }
        $out = [];
        try {
            $rows = DB::table(static::SCHEDULES)->where('enabled', 1)->whereIn('project_id', array_keys($projects))->orderBy('id')->limit(500)->get();
            if ($rows->isEmpty()) {
                return [];
            }
            $issues = static::table('issue_table');
            $assignees = strval(array_get(static::def(), 'issue.assignees', ''));
            $days = Holidays::between(static::next(max($from, $today), -static::MOVE_DAYS - 10), static::next($to, static::MOVE_DAYS + 10));
            foreach ($rows as $s) {
                $p = $projects[intval($s->project_id)] ?? null;
                if ($p === null) {
                    continue;
                }
                if ($mineOnly) {
                    // the schedule's template issue: is the person assigned it (read, not shown)
                    $tpl = isset($issues) ? $issues->getValueModel()->query()->withoutGlobalScopes()->find(intval($s->template_issue_id)) : null;
                    if (!isset($tpl) || !static::names(array_get((array) $tpl->value, $assignees), $me)) {
                        continue;
                    }
                }
                $sid = intval($s->id);
                $exceptions = [];
                foreach (DB::table(static::EXCEPTIONS)->where('schedule_id', $sid)->get() as $e) {
                    $exceptions[substr(strval($e->original_date), 0, 10)] = $e->new_date === null ? null : substr(strval($e->new_date), 0, 10);
                }
                $made = DB::table(static::ITEMS)->where('schedule_id', $sid)->pluck('original_date')->map(function ($d) {
                    return substr(strval($d), 0, 10);
                })->all();
                $row = (array) $s;
                foreach (['start_date', 'end_date'] as $k) {
                    $row[$k] = isset($row[$k]) ? substr(strval($row[$k]), 0, 10) : null;
                }
                foreach (static::scheduleDates($row, $from, $to, $today, $exceptions, $made, $days) as $d) {
                    $out[] = [
                        'pm' => 'schedule',
                        'start' => $d['date'],
                        'end' => static::next($d['date']),
                        'kind' => 'due',
                        'all_day' => true,
                        'title' => strval($s->name),
                        'original' => $d['original'],
                        'project' => $p['id'],
                        'project_name' => $p['name'],
                        'color' => $p['color'],
                        'url' => static::pmUrl(strval($p['id'])),
                        // ExmentPM's schedule screen is its project manager's
                        'change_url' => ($p['manager'] || $admin) ? static::pmUrl(strval($p['id']), 'schedules') : null,
                    ];
                }
            }
        } catch (\Throwable $e) {
            Log::warning('gw_calendar pm schedules: ' . get_class($e) . ' ' . $e->getMessage());
        }
        return $out;
    }
}
