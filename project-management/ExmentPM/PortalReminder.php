<?php

namespace App\Plugins\ExmentPM;

use Exceedone\Exment\Enums\SystemTableName;
use Exceedone\Exment\Model\CustomColumn;
use Exceedone\Exment\Model\CustomTable;
use Exceedone\Exment\Model\NotifyNavbar;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Reminders before the due date of an issue (ISS00432, stage 1a).
 *
 * One rule for the site, in the plugin settings: how many days before the due date (several,
 * "3,1"), counted in business days or not; on the morning of the due date; every business day
 * once it is overdue (for so many days at most); the time of the day they go out at; to whom
 * (the assignees, the sub-assignees, the managers, the watchers). Only to people who can read the
 * issue, never on a finished issue or in an archived project.
 *
 * A reminder is a bell, then the mail or the Teams chat each person chose for the notices of the
 * portal (PortalTeams). pm_portal_reminder_logs keeps what went out, one row per issue, person and
 * "slot" (before:3:2026-11-30, today:2026-11-30, over:2026-12-02), so nothing goes twice; a new due
 * date makes new slots.
 *
 * Off until an administrator turns it on: a feature of Feature management (exment_pm.reminder),
 * or, on an Exment without it, the plugin setting "reminder_on". Run by the batch
 * (exment:batch --name=ExmentPM --param1=remind, every 15 minutes) and by page loads (every few
 * minutes at most) as a fallback.
 *
 * The dates are worked out by slots(), which reads no database: tools/test-pm-reminder.php.
 */
class PortalReminder
{
    const FEATURE = 'exment_pm.reminder';
    const FEATURES = 'Exceedone\Exment\Services\Features';
    const LOG_TABLE = 'pm_portal_reminder_logs';

    /** Minutes between two runs started by page loads. */
    const TICK_MINUTES = 5;

    /** Reminders (one person, one issue) one run sends at most; the next run sends the rest. */
    const RUN_LIMIT = 500;

    /** The people a reminder may go to. */
    const TARGETS = ['assignees', 'sub_assignees', 'managers', 'watchers'];

    public static function registerFeature(): void
    {
        if (!class_exists(static::FEATURES)) {
            return;
        }
        try {
            call_user_func([static::FEATURES, 'register'], static::FEATURE, [
                'label' => strval(PortalDefinition::trans('remind.feature')),
                'description' => strval(PortalDefinition::trans('remind.feature_help')),
                'who' => false,
            ]);
        } catch (\Throwable $e) {
            Log::warning('ExmentPM could not register the reminder feature: ' . $e->getMessage());
        }
    }

    /** Whether reminders go out on this site. */
    public static function enabled(): bool
    {
        if (class_exists(static::FEATURES)) {
            try {
                return boolval(call_user_func([static::FEATURES, 'enabled'], static::FEATURE));
            } catch (\Throwable $e) {
                return false;
            }
        }
        return IssueAutofill::flag('reminder_on', false);
    }

    /**
     * The rule of the site, from the plugin settings.
     *
     * @return array{days: array<int, int>, business: bool, today: bool, overdue: bool, overdue_max: int, time: string, to: array<int, string>}
     */
    public static function settings(): array
    {
        return static::parseSettings([
            'days' => IssueAutofill::option('reminder_days', '1'),
            'business' => IssueAutofill::option('reminder_business', '0'),
            'today' => IssueAutofill::option('reminder_today', '1'),
            'overdue' => IssueAutofill::option('reminder_overdue', '0'),
            'overdue_max' => IssueAutofill::option('reminder_overdue_max', '14'),
            'time' => IssueAutofill::option('reminder_time', '09:00'),
            'to' => IssueAutofill::option('reminder_to', ['assignees']),
        ]);
    }

    /**
     * The settings as values, whatever was typed.
     *
     * @param array<string, mixed> $raw
     * @return array{days: array<int, int>, business: bool, today: bool, overdue: bool, overdue_max: int, time: string, to: array<int, string>}
     */
    public static function parseSettings(array $raw): array
    {
        $days = [];
        foreach (preg_split('/[\s,、，]+/u', strval($raw['days'] ?? '')) ?: [] as $d) {
            if (ctype_digit($d) && intval($d) >= 1 && intval($d) <= 60) {
                $days[intval($d)] = true;
            }
        }
        $days = array_keys($days);
        rsort($days);
        $time = strval($raw['time'] ?? '09:00');
        if (!preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', $time, $m)) {
            $time = '09:00';
        } else {
            $time = sprintf('%02d:%s', intval($m[1]), $m[2]);
        }
        $to = $raw['to'] ?? ['assignees'];
        $to = is_array($to) ? $to : explode(',', strval($to));
        $to = array_values(array_intersect(static::TARGETS, array_map('strval', $to)));

        return [
            'days' => $days,
            'business' => boolval(intval($raw['business'] ?? 0)),
            'today' => boolval(intval($raw['today'] ?? 0)),
            'overdue' => boolval(intval($raw['overdue'] ?? 0)),
            'overdue_max' => max(1, min(90, intval($raw['overdue_max'] ?? 14) ?: 14)),
            'time' => $time,
            'to' => empty($to) ? ['assignees'] : $to,
        ];
    }

    /**
     * The reminders an issue due on $due has today: slot => [kind, n]. Nothing before the time
     * of the day. A reminder "n days before" that falls on a day off goes on the business day
     * before it; counted in business days, it is n business days before the due date.
     *
     * @param array{days: array<int, int>, business: bool, today: bool, overdue: bool, overdue_max: int, time: string} $s
     * @return array<string, array{0: string, 1: int}>
     */
    public static function slots(string $due, string $today, string $nowHM, array $s, BusinessCalendar $cal): array
    {
        $due = BusinessCalendar::normalize($due);
        if ($due === null || strcmp($nowHM, $s['time']) < 0) {
            return [];
        }
        $out = [];
        foreach ($s['days'] as $n) {
            $send = $s['business']
                ? $cal->addBusinessDays($due, -$n)
                : $cal->shift((new \DateTimeImmutable($due))->modify('-' . $n . ' day')->format('Y-m-d'), BusinessCalendar::BEFORE);
            if ($send === $today) {
                $out['before:' . $n . ':' . $due] = ['before', $n];
            }
        }
        if ($s['today'] && $due === $today) {
            $out['today:' . $due] = ['today', 0];
        }
        if ($s['overdue'] && strcmp($today, $due) > 0 && $cal->isBusinessDay($today)) {
            $late = intval((new \DateTimeImmutable($due))->diff(new \DateTimeImmutable($today))->days);
            if ($late <= $s['overdue_max']) {
                $out['over:' . $today] = ['over', $late];
            }
        }
        return $out;
    }

    /**
     * Run when the last run is a few minutes old. Never throws.
     */
    public static function tick(): void
    {
        try {
            if (!static::enabled()) {
                return;
            }
            $key = 'pmportal_remind:' . DB::connection()->getDatabaseName();
            if (!Cache::add($key, 1, now()->addMinutes(self::TICK_MINUTES))) {
                return;
            }
            static::run();
        } catch (\Throwable $e) {
            Log::warning('PmPortal reminders: ' . $e->getMessage());
        }
    }

    /**
     * Send the reminders whose time has come.
     *
     * @return array{issues: int, sent: int, skipped: int}
     */
    public static function run(bool $dry = false, ?string $today = null, ?string $nowHM = null): array
    {
        $result = ['issues' => 0, 'sent' => 0, 'skipped' => 0];
        if (!static::enabled() || !\Schema::hasTable(static::LOG_TABLE)) {
            return $result;
        }
        $def = PortalDefinition::config();
        $is = (array) array_get($def, 'issue', []);
        $issueTable = CustomTable::getEloquent(array_get($def, 'issue_table'));
        $projectTable = CustomTable::getEloquent(array_get($def, 'project_table'));
        if (!isset($issueTable, $projectTable) || is_nullorempty(array_get($is, 'due'))) {
            return $result;
        }
        // the site's rule, and the projects' own (stage 1b): a project without a row has the site's
        $site = static::settings();
        $rules = PortalReminderRules::exists() ? PortalReminderRules::all() : [];
        $used = [$site];
        foreach ($rules as $rule) {
            if ($rule['enabled'] && !$rule['use_site']) {
                $used[] = $rule['settings'];
            }
        }
        $used = array_values(array_filter($used, function ($u) {
            return !empty($u['days']) || $u['today'] || $u['overdue'];
        }));
        if (empty($used)) {
            return $result;
        }
        $today = $today ?? PortalTime::today();
        $nowHM = $nowHM ?? now()->format('H:i');
        $cal = new BusinessCalendar();

        // the due dates that can have a reminder today: from the oldest overdue one to the
        // furthest "n days before" (a few days more for the days off it may step over)
        $maxDays = 0;
        $maxOver = 0;
        foreach ($used as $u) {
            $maxDays = max($maxDays, empty($u['days']) ? 0 : max($u['days']));
            $maxOver = max($maxOver, $u['overdue'] ? $u['overdue_max'] : 0);
        }
        $from = (new \DateTimeImmutable($today))->modify('-' . $maxOver . ' day')->format('Y-m-d');
        $to = (new \DateTimeImmutable($today))->modify('+' . ($maxDays * 2 + 10) . ' day')->format('Y-m-d');

        $dueColumn = CustomColumn::getEloquent(array_get($is, 'due'), $issueTable);
        $statusColumn = CustomColumn::getEloquent(array_get($is, 'status'), $issueTable);
        if (!isset($dueColumn)) {
            return $result;
        }
        $query = $issueTable->getValueModel()->query()->withoutGlobalScopes()
            ->where('parent_type', $projectTable->table_name)
            ->where($dueColumn->getQueryKey(), '>=', $from)
            ->where($dueColumn->getQueryKey(), '<=', $to);
        $done = static::doneStatusIds($def);
        if (isset($statusColumn) && !empty($done)) {
            IssueAutofill::whereOpen($query, $statusColumn->getQueryKey(), $done);
        }
        if (CustomColumn::getEloquent('archived', $issueTable) !== null) {
            $query->where(function ($q) {
                $q->whereNull('value->archived')->orWhere('value->archived', '<>', '1');
            });
        }

        $projects = [];
        $budget = static::RUN_LIMIT;
        foreach ($query->orderBy('id')->cursor() as $issue) {
            $due = BusinessCalendar::normalize(array_get($issue->value, array_get($is, 'due')));
            if ($due === null) {
                continue;
            }
            $pid = intval($issue->parent_id);
            $rule = $rules[$pid] ?? null;
            if (isset($rule) && !$rule['enabled']) {
                continue;
            }
            $s = (isset($rule) && !$rule['use_site']) ? $rule['settings'] : $site;
            $slots = static::slots($due, $today, $nowHM, $s, $cal);
            if (empty($slots)) {
                continue;
            }
            if (!array_key_exists($pid, $projects)) {
                $p = $projectTable->getValueModel()->query()->withoutGlobalScopes()->find($pid);
                $projects[$pid] = (isset($p) && !boolval(array_get($p->value, 'archived'))) ? $p : null;
            }
            $project = $projects[$pid];
            if (!isset($project)) {
                continue;
            }
            $people = PortalMembership::filterReaders($issue, static::targets($issue, $s['to'], $def, $issueTable));
            $channels = $rule['channels'] ?? [];
            if (empty($people) && empty($channels)) {
                continue;
            }
            $result['issues']++;
            foreach ($slots as $slot => [$kind, $n]) {
                $sent = static::alreadySent(intval($issue->id), $slot);
                $fresh = array_values(array_diff($people, $sent));
                // a channel is "user 0" of the slot ch<n>:<slot>
                $freshChannels = [];
                foreach ($channels as $ci => $c) {
                    if (empty(static::alreadySent(intval($issue->id), 'ch' . $ci . ':' . $slot))) {
                        $freshChannels[$ci] = $c;
                    }
                }
                $result['skipped'] += count($people) - count($fresh) + count($channels) - count($freshChannels);
                if (empty($fresh) && empty($freshChannels)) {
                    continue;
                }
                if ($budget <= 0) {
                    return $result;
                }
                $fresh = array_slice($fresh, 0, $budget);
                $budget -= count($fresh) + count($freshChannels);
                $result['sent'] += count($fresh) + count($freshChannels);
                if (!$dry) {
                    static::send($project, $issue, $fresh, $slot, $kind, $n, $due, $def, $issueTable, $freshChannels);
                }
            }
            if ($budget <= 0) {
                break;
            }
        }

        return $result;
    }

    /**
     * The people of the rule on one issue.
     *
     * @param array<int, string> $to
     * @return array<int, int>
     */
    protected static function targets($issue, array $to, array $def, CustomTable $issueTable): array
    {
        $ids = [];
        $columns = [
            'assignees' => array_get($def, 'issue.assignees'),
            'sub_assignees' => array_get($def, 'issue_extra.sub_assignees', 'sub_assignees'),
            'managers' => array_get($def, 'issue_extra.managers', 'managers'),
        ];
        foreach ($to as $who) {
            if ($who === 'watchers') {
                foreach (Star::where('star_type', Star::TYPE_WATCH)->where('target_type', $issueTable->table_name)
                    ->where('target_id', strval($issue->id))->pluck('user_id') as $uid) {
                    $ids[intval($uid)] = true;
                }
                continue;
            }
            $col = $columns[$who] ?? null;
            if (is_nullorempty($col)) {
                continue;
            }
            foreach (PortalMembership::ids(array_get($issue->value, $col)) as $uid) {
                $ids[$uid] = true;
            }
        }
        unset($ids[0]);

        return array_keys($ids);
    }

    /** @return array<int, int> the people a slot of an issue has gone to */
    protected static function alreadySent(int $issueId, string $slot): array
    {
        return DB::table(static::LOG_TABLE)->where('issue_id', $issueId)->where('slot', $slot)
            ->pluck('user_id')->map(function ($v) {
                return intval($v);
            })->all();
    }

    /** @return array<int, string> the status rows that are done (every project's) */
    protected static function doneStatusIds(array $def): array
    {
        $table = CustomTable::getEloquent(array_get($def, 'status_table'));
        if (!isset($table)) {
            return [];
        }
        $doneCol = strval(array_get($def, 'status_row.done', 'is_done'));
        $out = [];
        foreach ($table->getValueModel()->query()->withoutGlobalScopes()->get() as $row) {
            if (boolval(array_get($row->value, $doneCol))) {
                $out[] = strval($row->id);
            }
        }

        return $out;
    }

    /**
     * Ring the bells and send the mails and Teams chats of one slot, then write the log. The log
     * first: a reminder that failed half way is not sent again to the ones it reached.
     *
     * @param array<int, int> $people
     */
    protected static function send($project, $issue, array $people, string $slot, string $kind, int $n, string $due, array $def, CustomTable $issueTable, array $channels = []): void
    {
        $now = now();
        $rows = [];
        foreach ($people as $uid) {
            $rows[] = ['issue_id' => intval($issue->id), 'user_id' => $uid, 'slot' => $slot, 'created_at' => $now, 'updated_at' => $now];
        }
        foreach (array_keys($channels) as $ci) {
            $rows[] = ['issue_id' => intval($issue->id), 'user_id' => 0, 'slot' => 'ch' . $ci . ':' . $slot, 'created_at' => $now, 'updated_at' => $now];
        }
        DB::table(static::LOG_TABLE)->insertOrIgnore($rows);

        $pkey = strtoupper(strval(array_get($project->value, array_get($def, 'project.key'), '')));
        $key = ($pkey !== '' ? $pkey : '#') . '-' . $issue->id;
        $subject = strval(array_get($issue->value, array_get($def, 'issue.subject'), ''));
        $url = PortalDefinition::projectUrl($project) . '#issue-' . rawurlencode(PortalDefinition::issueAnchor($issue));
        $dueText = (new \DateTimeImmutable($due))->format('n/j');
        $line = strval(PortalDefinition::trans('remind.line_' . $kind, ['n' => $n, 'due' => $dueText]));
        $title = strval(PortalDefinition::trans('remind.title', ['key' => $key]));

        foreach ($people as $uid) {
            try {
                $bell = new NotifyNavbar();
                $bell->fill([
                    'notify_id' => 0,
                    'parent_id' => $issue->id,
                    'parent_type' => $issueTable->table_name,
                    'target_user_id' => $uid,
                    'notify_subject' => PortalDefinition::notifySubject('[' . $key . '] ' . $subject),
                    'notify_body' => PortalDefinition::notifyBodyWithLink($line, $url),
                    'trigger_user_id' => null,
                ]);
                $bell->save();
            } catch (\Throwable $e) {
                Log::warning('pm_portal reminder bell to user ' . $uid . ' on issue ' . $issue->id . ' failed: ' . $e->getMessage());
            }
        }
        // the mail or the Teams chat each one chose (PortalTeams)
        if (!empty($people)) {
            PortalTeams::sendTo($people, $issueTable->table_name, intval($issue->id), 'remind', $key, $subject, [$line], $url, false, $title);
        }
        // the channels of the project (stage 1b)
        if (!empty($channels)) {
            PortalReminderRules::post(array_values($channels), $issue, $title, $subject, $line, $url);
        }
    }
}
