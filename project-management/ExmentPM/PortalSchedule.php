<?php

namespace App\Plugins\ExmentPM;

use Exceedone\Exment\Model\CustomTable;
use Exceedone\Exment\Model\CustomValueModelScope;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Recurring schedules (ISS00432, stage 2a): a rule (PortalRrule) and a template issue of the
 * project; so many days before each date, the template is copied into a new issue due on it
 * (the way the simple recurrence of schema 20 copies one: PortalRecurrence::copyValues).
 *
 * pm_portal_schedules: the schedules. pm_portal_schedule_exceptions: one date moved or skipped.
 * pm_portal_schedule_items: the issue made for a date, (schedule, date) unique, so no date makes
 * two. A date that is a day off moves as the schedule says (before, after, keep, skip).
 *
 * Off until an administrator turns it on (Feature management: exment_pm.schedule; without it, the
 * plugin setting "schedule_on"). Run by the batch every 5 minutes: Exment's schedule:run calls
 * it on the plugin's batch_cron, which config.json ships (PluginBatch). Without schedule:run, a
 * cron line of the server's own does the same:
 *
 *   0-59/5 * * * * cd /path/to/exment && php artisan exment:batch --name=ExmentPM --param1=schedule
 *
 * Page loads of the portal (its top page and a project's page: PmPortalController index and
 * show) run them too, but only when the batch has not run for FALLBACK_MINUTES (or never did),
 * every TICK_MINUTES at most. Either way an issue is made as the schedule's owner
 * (PortalRunOwner), never as the visitor; a schedule that cannot run as anyone says so on the
 * schedule screen (run_error) and in the log, once.
 */
class PortalSchedule
{
    const FEATURE = 'exment_pm.schedule';
    const FEATURES = 'Exceedone\Exment\Services\Features';
    const TABLE = 'pm_portal_schedules';
    const EXCEPTIONS = 'pm_portal_schedule_exceptions';
    const ITEMS = 'pm_portal_schedule_items';

    const TICK_MINUTES = 5;
    // page loads run the schedules only when the batch has not run for this long
    const FALLBACK_MINUTES = 30;
    const RUN_LIMIT = 200;
    const MAX_LEAD = 90;

    public static function registerFeature(): void
    {
        if (!class_exists(static::FEATURES)) {
            return;
        }
        try {
            call_user_func([static::FEATURES, 'register'], static::FEATURE, [
                'label' => strval(PortalDefinition::trans('sched.feature')),
                'description' => strval(PortalDefinition::trans('sched.feature_help')),
                'who' => false,
            ]);
        } catch (\Throwable $e) {
            Log::warning('ExmentPM could not register the schedule feature: ' . $e->getMessage());
        }
    }

    public static function enabled(): bool
    {
        if (!\Schema::hasTable(static::TABLE)) {
            return false;
        }
        if (class_exists(static::FEATURES)) {
            try {
                return boolval(call_user_func([static::FEATURES, 'enabled'], static::FEATURE));
            } catch (\Throwable $e) {
                return false;
            }
        }
        return IssueAutofill::flag('schedule_on', false);
    }

    /** @return array<int, object> the schedules of a project */
    public static function ofProject(int $projectId): array
    {
        return DB::table(static::TABLE)->where('project_id', $projectId)->orderBy('id')->get()->all();
    }

    public static function find(int $projectId, int $id): ?object
    {
        $row = DB::table(static::TABLE)->where('project_id', $projectId)->where('id', $id)->first();

        return $row ?: null;
    }

    /** @return array<string, ?string> original date => new date (null: skipped) */
    public static function exceptions(int $scheduleId): array
    {
        $out = [];
        foreach (DB::table(static::EXCEPTIONS)->where('schedule_id', $scheduleId)->get() as $e) {
            $out[strval($e->original_date)] = $e->new_date === null ? null : strval($e->new_date);
        }

        return $out;
    }

    /** @return array<string, int> original date => issue id */
    public static function items(int $scheduleId): array
    {
        $out = [];
        foreach (DB::table(static::ITEMS)->where('schedule_id', $scheduleId)->get() as $i) {
            $out[strval($i->original_date)] = intval($i->issue_id);
        }

        return $out;
    }

    /**
     * The dates of a schedule from $from to $to: original => the date the issue is due on (null:
     * skipped, by an exception or because it is a day off and the schedule skips those).
     *
     * @return array<string, ?string>|null null: the rule cannot be read
     */
    public static function occurrences(object $s, string $from, string $to, BusinessCalendar $cal, ?array $exceptions = null): ?array
    {
        $rdates = json_decode(strval($s->rdates ?? ''), true);
        $dates = PortalRrule::between(strval($s->rrule ?? ''), strval($s->start_date), $from, $to, is_array($rdates) ? $rdates : [], $s->end_date ?? null);
        if ($dates === null) {
            return null;
        }
        $exceptions = $exceptions ?? static::exceptions(intval($s->id));
        $out = [];
        foreach ($dates as $d) {
            if (array_key_exists($d, $exceptions)) {
                $out[$d] = $exceptions[$d];
                continue;
            }
            $out[$d] = PortalRrule::shift($d, strval($s->shift ?? BusinessCalendar::KEEP), $cal);
        }

        return $out;
    }

    /**
     * The rule of the form's simple choices: yearly (a month and a day, or the n-th weekday),
     * monthly (a day, or the n-th weekday), weekly (days of the week, every n weeks), daily (every
     * n days), dates only, or a rule as typed.
     *
     * @param array<string, mixed> $f
     */
    public static function buildRule(array $f): ?string
    {
        $kind = strval($f['kind'] ?? '');
        $interval = max(1, min(99, intval($f['interval'] ?? 1)));
        $iv = $interval > 1 ? ';INTERVAL=' . $interval : '';
        $wd = function ($v) {
            $v = strtoupper(strval($v));
            return array_key_exists($v, PortalRrule::DAYS) ? $v : null;
        };
        $day = function () use ($f, $wd) {
            if (strval($f['by'] ?? 'day') === 'nth') {
                $n = intval($f['nth'] ?? 1);
                $w = $wd($f['weekday'] ?? '');
                if (!in_array($n, [1, 2, 3, 4, -1], true) || $w === null) {
                    return null;
                }
                return ';BYDAY=' . $n . $w;
            }
            $d = intval($f['monthday'] ?? 1);
            if (($d < 1 || $d > 31) && $d !== -1) {
                return null;
            }
            return ';BYMONTHDAY=' . $d;
        };
        switch ($kind) {
            case 'yearly':
                $m = intval($f['month'] ?? 0);
                $d = $day();
                return ($m < 1 || $m > 12 || $d === null) ? null : 'FREQ=YEARLY' . $iv . ';BYMONTH=' . $m . $d;
            case 'monthly':
                $d = $day();
                return $d === null ? null : 'FREQ=MONTHLY' . $iv . $d;
            case 'weekly':
                $days = array_values(array_filter(array_map($wd, (array) ($f['weekdays'] ?? []))));
                return empty($days) ? null : 'FREQ=WEEKLY' . $iv . ';BYDAY=' . implode(',', array_unique($days));
            case 'daily':
                return 'FREQ=DAILY' . $iv;
            case 'dates':
                return '';
            case 'custom':
                $rule = strtoupper(trim(strval($f['rrule'] ?? '')));
                return PortalRrule::parse($rule) !== null && $rule !== '' ? preg_replace('/^RRULE:/', '', $rule) : null;
        }
        return null;
    }

    /**
     * Whether a user may save a schedule of the project: they can add issues to it themselves (a
     * role that edits the issue table, and the project editable for them, as Exment asks before a
     * row is added under it). The schedule's issues are made later with no one asked, so a user
     * who may only manage the project row must not be able to have them made.
     *
     * @param \Exceedone\Exment\Model\CustomValue $project
     */
    public static function saverMayAdd($project, int $userId, CustomTable $issueTable): bool
    {
        // the roles as they are now, not a memo of an earlier run in this process
        PortalRunOwner::forget();

        return $userId > 0 && PortalRunOwner::canWrite($userId, $issueTable)
            && PortalDefinition::enableEdit($project, true) === true;
    }

    /**
     * Save a schedule from the form (a new one when $id is null). The template is looked for
     * as the user sees it: one they may not read, or one in the trash, is refused.
     *
     * @return array{0: ?int, 1: array<int, string>} the id, or the errors
     */
    public static function save(int $projectId, ?int $id, array $f, int $userId, $issueTable): array
    {
        $t = function ($k) {
            return strval(PortalDefinition::trans('sched.' . $k));
        };
        $errors = [];
        $name = mb_substr(trim(strval($f['name'] ?? '')), 0, 100);
        if ($name === '') {
            $errors[] = $t('err_name');
        }
        $template = intval($f['template'] ?? 0);
        $tpl = $template > 0 ? $issueTable->getValueModel()->query()->find($template) : null;
        if (!isset($tpl) || intval($tpl->parent_id) !== $projectId) {
            $errors[] = $t('err_template');
        }
        $rule = static::buildRule($f);
        if ($rule === null) {
            $errors[] = $t('err_rule');
        }
        $start = BusinessCalendar::normalize($f['start_date'] ?? '');
        if ($start === null) {
            $errors[] = $t('err_start');
        }
        $end = BusinessCalendar::normalize($f['end_date'] ?? '');
        $rdates = [];
        $notDates = [];
        foreach (preg_split('/[\s,、，]+/u', trim(strval($f['rdates'] ?? ''))) ?: [] as $d) {
            if ($d === '') {
                continue;
            }
            $n = BusinessCalendar::normalize($d);
            if ($n !== null && strlen($d) <= 10) {
                $rdates[$n] = true;
            } else {
                $notDates[] = $d;
            }
        }
        if (!empty($notDates)) {
            $errors[] = strval(PortalDefinition::trans('sched.err_not_dates', ['dates' => implode(', ', array_slice($notDates, 0, 5))]));
        }
        $rdates = array_keys($rdates);
        sort($rdates);
        if ($rule === '' && empty($rdates)) {
            $errors[] = $t('err_dates');
        }
        $shift = in_array(strval($f['shift'] ?? ''), PortalRrule::SHIFTS, true) ? strval($f['shift']) : BusinessCalendar::KEEP;
        $lead = max(0, min(static::MAX_LEAD, intval($f['lead_days'] ?? 7)));
        if (!empty($errors)) {
            return [null, $errors];
        }
        $now = now();
        $values = [
            'name' => $name,
            'template_issue_id' => $template,
            'rrule' => $rule,
            'start_date' => $start,
            'end_date' => $end,
            'rdates' => json_encode($rdates),
            'shift' => $shift,
            'lead_days' => $lead,
            'form' => json_encode(array_intersect_key($f, array_flip(['kind', 'interval', 'by', 'monthday', 'nth', 'weekday', 'month', 'weekdays', 'rrule'])), JSON_UNESCAPED_UNICODE),
            'enabled' => !empty($f['enabled']) ? 1 : 0,
            'updated_user_id' => $userId,
            'updated_at' => $now,
        ];
        if ($id !== null && static::find($projectId, $id) !== null) {
            DB::table(static::TABLE)->where('id', $id)->update($values);
        } else {
            $id = intval(DB::table(static::TABLE)->insertGetId($values + ['project_id' => $projectId, 'created_user_id' => $userId, 'created_at' => $now]));
        }

        return [$id, []];
    }

    /**
     * Change a schedule from a date on (RFC 5545's way): the schedule ends the day before, a new
     * one with the form's rule starts on the date. The issues already made from the date on move
     * to the new one, so its first run does not make them again; the moved or skipped dates from
     * the date on belonged to the old rule and go.
     *
     * @return array{0: ?int, 1: array<int, string>} the new schedule's id, or the errors
     */
    public static function split(int $projectId, int $id, string $date, array $f, int $userId, $issueTable): array
    {
        $old = static::find($projectId, $id);
        $date = BusinessCalendar::normalize($date);
        if ($old === null || $date === null || strcmp($date, strval($old->start_date)) <= 0) {
            return [null, [strval(PortalDefinition::trans('sched.err_split'))]];
        }
        $f['start_date'] = $date;
        [$newId, $errors] = static::save($projectId, null, $f, $userId, $issueTable);
        if ($newId === null) {
            return [null, $errors];
        }
        $before = (new \DateTimeImmutable($date))->modify('-1 day')->format('Y-m-d');
        DB::transaction(function () use ($id, $newId, $date, $before, $userId) {
            DB::table(static::TABLE)->where('id', $id)->update(['end_date' => $before, 'updated_user_id' => $userId, 'updated_at' => now()]);
            DB::table(static::EXCEPTIONS)->where('schedule_id', $id)->where('original_date', '>=', $date)->delete();
            DB::table(static::ITEMS)->where('schedule_id', $id)->where('original_date', '>=', $date)->update(['schedule_id' => $newId]);
        });

        return [$newId, []];
    }

    public static function delete(int $projectId, int $id): void
    {
        if (static::find($projectId, $id) === null) {
            return;
        }
        DB::table(static::EXCEPTIONS)->where('schedule_id', $id)->delete();
        DB::table(static::ITEMS)->where('schedule_id', $id)->delete();
        DB::table(static::TABLE)->where('id', $id)->delete();
    }

    /** Move one date ($new), skip it (null), or put it back as the rule says ($remove). */
    public static function setException(int $scheduleId, string $original, ?string $new, bool $remove = false): void
    {
        DB::table(static::EXCEPTIONS)->where('schedule_id', $scheduleId)->where('original_date', $original)->delete();
        // the issue of that date, made already: it moves with it (a skipped one stays as it is)
        $due = $remove ? static::dueOf($scheduleId, $original) : $new;
        if ($due !== null) {
            static::moveIssue($scheduleId, $original, $due);
        }
        if ($remove) {
            return;
        }
        $now = now();
        DB::table(static::EXCEPTIONS)->insert([
            'schedule_id' => $scheduleId, 'original_date' => $original, 'new_date' => $new,
            'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    /** The due date a date of a schedule has by its rule (no exception), or null. */
    protected static function dueOf(int $scheduleId, string $original): ?string
    {
        $s = DB::table(static::TABLE)->where('id', $scheduleId)->first();
        if (!isset($s)) {
            return null;
        }
        $dates = static::occurrences($s, $original, $original, PortalCalendarDays::calendar(), []);

        return $dates[$original] ?? null;
    }

    /** The due date (and the start, by as much) of the issue made for a date, when there is one. */
    protected static function moveIssue(int $scheduleId, string $original, string $due): void
    {
        $item = DB::table(static::ITEMS)->where('schedule_id', $scheduleId)->where('original_date', $original)->first();
        if (!isset($item) || intval($item->issue_id) <= 0) {
            return;
        }
        try {
            $def = PortalDefinition::config();
            $issueTable = CustomTable::getEloquent(array_get($def, 'issue_table'));
            $issue = isset($issueTable) ? $issueTable->getValueModel()->query()->find(intval($item->issue_id)) : null;
            $dueCol = array_get($def, 'issue.due');
            if (!isset($issue) || is_nullorempty($dueCol)) {
                return;
            }
            $old = BusinessCalendar::normalize(array_get($issue->value, $dueCol));
            if ($old === $due) {
                return;
            }
            $values = [$dueCol => $due];
            $startCol = array_get($def, 'issue.start');
            $start = !is_nullorempty($startCol) ? BusinessCalendar::normalize(array_get($issue->value, $startCol)) : null;
            if ($old !== null && $start !== null) {
                $shift = intval((new \DateTimeImmutable($old))->diff(new \DateTimeImmutable($due))->format('%r%a'));
                $values[$startCol] = (new \DateTimeImmutable($start))->modify(($shift >= 0 ? '+' : '') . $shift . ' day')->format('Y-m-d');
            }
            $issue->setValue($values);
            $issue->save();
        } catch (\Throwable $e) {
            Log::warning('PmPortal schedule ' . $scheduleId . ': the issue of ' . $original . ' could not move: ' . $e->getMessage());
        }
    }

    /**
     * What a run does with why a schedule cannot run ($now, null: it can) when it was stopped for
     * $prev before: 'log' when the reason is new (logged once, shown on the schedule screen),
     * 'clear' when it can run again, null when nothing changed.
     */
    public static function blockChange(?string $prev, ?string $now): ?string
    {
        $prev = ($prev === null || $prev === '') ? null : $prev;
        if ($now === $prev) {
            return null;
        }

        return $now === null ? 'clear' : 'log';
    }

    /**
     * Whether the schedules table has the columns of why a schedule cannot run (a migration).
     * Kept for the life of the process: a queue worker started before the migration ran keeps
     * the cache-key fallback of setBlocked() until it restarts, which is harmless.
     */
    protected static function hasBlockColumns(): bool
    {
        static $has = null;
        if ($has === null) {
            $has = \Schema::hasColumn(static::TABLE, 'run_error');
        }

        return $has;
    }

    /**
     * Keep why a schedule cannot run (PortalRunOwner::NO_OWNER, NO_RIGHT, or null: it can), log it
     * once when it starts, and say so once when it runs again.
     */
    protected static function setBlocked(object $s, ?string $reason): void
    {
        try {
            if (static::hasBlockColumns()) {
                $prev = $s->run_error ?? null;
            } else {
                // before the migration: once a day at most
                $prev = Cache::get('pmportal_schedule_block:' . DB::connection()->getDatabaseName() . ':' . $s->id);
            }
            $change = static::blockChange($prev, $reason);
            if ($change === null) {
                return;
            }
            if (static::hasBlockColumns()) {
                DB::table(static::TABLE)->where('id', $s->id)->update(['run_error' => $reason, 'run_error_at' => $reason === null ? null : now()]);
            } elseif ($reason === null) {
                Cache::forget('pmportal_schedule_block:' . DB::connection()->getDatabaseName() . ':' . $s->id);
            } else {
                Cache::put('pmportal_schedule_block:' . DB::connection()->getDatabaseName() . ':' . $s->id, $reason, now()->addDay());
            }
            $s->run_error = $reason;
            if ($change === 'log') {
                Log::warning('PmPortal schedule ' . $s->id . ' (project ' . $s->project_id . ') makes no issues: '
                    . ($reason === PortalRunOwner::NO_OWNER ? 'the user who made it is gone or off the project, and the project has no manager' : 'the user it runs as may not add issues'));
            } else {
                Log::info('PmPortal schedule ' . $s->id . ' makes issues again');
            }
        } catch (\Throwable $e) {
            Log::warning('PmPortal schedule ' . $s->id . ': ' . $e->getMessage());
        }
    }

    /**
     * The batch ran (exment:batch --name=ExmentPM --param1=schedule): page loads leave the
     * schedules to it while it keeps running.
     */
    public static function batchRan(?int $at = null): void
    {
        try {
            Cache::forever(static::batchKey(), $at ?? time());
        } catch (\Throwable $e) {
            Log::warning('PmPortal schedules: ' . $e->getMessage());
        }
    }

    /** Whether the batch ran within FALLBACK_MINUTES of $now (both Unix times). */
    public static function batchFresh($lastRun, int $now): bool
    {
        return is_numeric($lastRun) && intval($lastRun) > 0 && $now - intval($lastRun) <= self::FALLBACK_MINUTES * 60;
    }

    protected static function batchKey(): string
    {
        return 'pmportal_schedule_batch:' . DB::connection()->getDatabaseName();
    }

    /**
     * A page load: runs the schedules only when the batch has not run for FALLBACK_MINUTES (or
     * never did), at most every TICK_MINUTES. Never throws.
     */
    public static function tick(): void
    {
        try {
            if (!static::enabled() || static::batchFresh(Cache::get(static::batchKey()), time())) {
                return;
            }
            $key = 'pmportal_schedule:' . DB::connection()->getDatabaseName();
            if (!Cache::add($key, 1, now()->addMinutes(self::TICK_MINUTES))) {
                return;
            }
            static::run();
        } catch (\Throwable $e) {
            Log::warning('PmPortal schedules: ' . $e->getMessage());
        }
    }

    /**
     * Make the issues whose time has come: every date from today to today + the lead days that
     * has no issue yet. Past dates are not made.
     *
     * @return array<int, array{schedule: int, date: string, due: string, issue: ?int}>
     */
    public static function run(bool $dry = false, ?string $today = null): array
    {
        $made = [];
        if (!static::enabled()) {
            return $made;
        }
        PortalRunOwner::forget();
        $def = PortalDefinition::config();
        $issueTable = CustomTable::getEloquent(array_get($def, 'issue_table'));
        $projectTable = CustomTable::getEloquent(array_get($def, 'project_table'));
        if (!isset($issueTable, $projectTable)) {
            return $made;
        }
        $today = $today ?? PortalTime::today();
        $cal = PortalCalendarDays::calendar();
        foreach (DB::table(static::TABLE)->where('enabled', 1)->orderBy('id')->get() as $s) {
            $project = $projectTable->getValueModel()->query()->withoutGlobalScope(CustomValueModelScope::class)->find(intval($s->project_id));
            if (!isset($project) || boolval(array_get($project->value, 'archived'))) {
                continue;
            }
            $tpl = $issueTable->getValueModel()->query()->withoutGlobalScope(CustomValueModelScope::class)->find(intval($s->template_issue_id));
            if (!isset($tpl) || intval($tpl->parent_id) !== intval($project->id)) {
                continue;
            }
            // made as the schedule's owner (PortalRunOwner), never as the visitor
            $owner = null;
            $blocked = PortalRunOwner::check($project, intval($s->created_user_id ?? 0) ?: null, $issueTable, $owner);
            if (!$dry) {
                static::setBlocked($s, $blocked);
            }
            if ($blocked !== null || $owner === null) {
                continue;
            }
            // a date moved earlier than its rule's lands inside the window from a later original
            $to = (new \DateTimeImmutable($today))->modify('+' . (intval($s->lead_days) + 31) . ' day')->format('Y-m-d');
            $until = (new \DateTimeImmutable($today))->modify('+' . intval($s->lead_days) . ' day')->format('Y-m-d');
            $dates = static::occurrences($s, $today, $to, $cal);
            if ($dates === null) {
                continue;
            }
            $have = static::items(intval($s->id));
            foreach ($dates as $original => $due) {
                if ($due === null || isset($have[$original]) || strcmp($due, $today) < 0 || strcmp($due, $until) > 0) {
                    continue;
                }
                if (count($made) >= static::RUN_LIMIT) {
                    return $made;
                }
                $id = $dry ? null : static::make($issueTable, $tpl, $project, $s, $original, $due, $def, $owner);
                if (!$dry && $id === null) {
                    continue;
                }
                $made[] = ['schedule' => intval($s->id), 'date' => $original, 'due' => $due, 'issue' => $id];
            }
        }

        return $made;
    }

    /**
     * One issue of a date: the date claimed first (the unique key), then the copy; a copy that
     * fails gives the date back.
     */
    protected static function make(CustomTable $issueTable, $tpl, $project, object $s, string $original, string $due, array $def, int $owner): ?int
    {
        $now = now();
        try {
            DB::table(static::ITEMS)->insert(['schedule_id' => intval($s->id), 'original_date' => $original, 'issue_id' => 0, 'created_at' => $now, 'updated_at' => $now]);
        } catch (\Throwable $e) {
            return null; // another run has it
        }
        try {
            $is = (array) array_get($def, 'issue', []);
            // the start as far before the due date as the template's is before its own
            $start = null;
            $tStart = BusinessCalendar::normalize(array_get($tpl->value, array_get($is, 'start')));
            $tDue = BusinessCalendar::normalize(array_get($tpl->value, array_get($is, 'due')));
            if ($tStart !== null && $tDue !== null && strcmp($tStart, $tDue) <= 0) {
                $gap = intval((new \DateTimeImmutable($tStart))->diff(new \DateTimeImmutable($tDue))->days);
                $start = (new \DateTimeImmutable($due))->modify('-' . $gap . ' day')->format('Y-m-d');
            }
            $value = PortalRecurrence::copyValues($issueTable, $tpl, $project, ['start' => $start, 'due' => $due], $def);
            if ($start === null && !empty($is['start'])) {
                $value[$is['start']] = null;
            }
            // not a recurring issue of schema 20
            unset($value[PortalRecurrence::columnName()]);
            $row = $issueTable->getValueModel();
            $row->parent_id = $project->id;
            $row->parent_type = $project->custom_table->table_name;
            $row->setValue($value);
            // made as the owner, with no creator's share for whoever's page load ran it
            PortalRunOwner::saveAs($row, $owner);
            DB::table(static::ITEMS)->where('schedule_id', intval($s->id))->where('original_date', $original)->update(['issue_id' => intval($row->id)]);

            return intval($row->id);
        } catch (\Throwable $e) {
            DB::table(static::ITEMS)->where('schedule_id', intval($s->id))->where('original_date', $original)->delete();
            Log::warning('PmPortal schedule ' . $s->id . ' on ' . $original . ': ' . $e->getMessage());

            return null;
        }
    }
}
