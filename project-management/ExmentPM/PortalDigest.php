<?php

namespace App\Plugins\ExmentPM;

use Exceedone\Exment\Enums\MailBodyType;
use Exceedone\Exment\Enums\SystemTableName;
use Exceedone\Exment\Model\CustomColumn;
use Exceedone\Exment\Model\CustomTable;
use Exceedone\Exment\Model\System;
use Exceedone\Exment\Notifications\MailSender;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The morning digest of a person (ISS02939, 「朝のまとめ」): once a day, at the time they chose,
 * what is theirs to look at -
 *
 *   - their issues due today, and the ones past their due date (open ones)
 *   - issues given to them since yesterday
 *   - comments that @mention them
 *   - the status changes of the issues they watch (by others)
 *
 * by mail or Teams, as they get the event 「朝のまとめ」 (PortalNotifyPrefs, their notice
 * settings). With the site's AI (TypeSafe, the key of the AI auto-fill) and the site's option on,
 * a line says which issue to begin with; with no AI, the digest is the same without that line.
 *
 * Off at first twice over: the site turns it on (Feature management 「プロジェクト管理：朝のまとめ」)
 * and each person turns it on for themselves (prefs.digest.on, their notice settings). Sent from
 * the batch (--param1=digest, every 15 minutes) or when the portal is opened. A digest with
 * nothing in it is not sent. Times are the site's.
 */
class PortalDigest
{
    const FEATURE = 'exment_pm.digest';
    const FEATURES = 'Exceedone\Exment\Services\Features';
    const LOG_TABLE = 'pm_portal_digest_logs';
    const DEFAULT_TIME = '08:00';
    const TICK_MINUTES = 5;

    /** Lines a section shows at most; the rest are counted. */
    const PER_SECTION = 10;

    /** People one run sends to at most; the next run sends the rest. */
    const RUN_LIMIT = 300;

    /** The issues past their due date it looks at, at most this many days back. */
    const OVERDUE_DAYS = 60;

    const SECTIONS = ['due', 'assigned', 'mention', 'status'];

    public static function registerFeature(): void
    {
        if (!class_exists(static::FEATURES)) {
            return;
        }
        try {
            call_user_func([static::FEATURES, 'register'], static::FEATURE, [
                'label' => strval(PortalDefinition::trans('digest.feature')),
                'description' => strval(PortalDefinition::trans('digest.feature_help')),
                'who' => false,
            ]);
        } catch (\Throwable $e) {
            Log::warning('ExmentPM could not register the digest feature: ' . $e->getMessage());
        }
    }

    public static function enabled(): bool
    {
        if (class_exists(static::FEATURES)) {
            try {
                return boolval(call_user_func([static::FEATURES, 'enabled'], static::FEATURE));
            } catch (\Throwable $e) {
                return false;
            }
        }
        return IssueAutofill::flag('digest_on', false);
    }

    /** A person's own setting: on, and the time ("08:00"). */
    public static function personal($prefs): array
    {
        $d = is_array($prefs) ? array_get($prefs, 'digest') : null;

        return [
            'on' => is_array($d) && !empty($d['on']),
            'time' => (is_array($d) ? PortalNotifyPrefs::hm($d['time'] ?? null) : null) ?? static::DEFAULT_TIME,
        ];
    }

    /** Run when the last run is a few minutes old. Never throws. */
    public static function tick(): void
    {
        try {
            if (!static::enabled()) {
                return;
            }
            $key = 'pmportal_digest:' . DB::connection()->getDatabaseName();
            if (!Cache::add($key, 1, now()->addMinutes(self::TICK_MINUTES))) {
                return;
            }
            static::run();
        } catch (\Throwable $e) {
            Log::warning('PmPortal digest: ' . get_class($e) . ': ' . $e->getMessage());
        }
    }

    /**
     * Send the digests whose time has come.
     *
     * @return array{people: int, sent: int, empty: int, failed?: int}
     */
    public static function run(bool $dry = false, ?string $today = null, ?string $nowHM = null): array
    {
        $result = ['people' => 0, 'sent' => 0, 'empty' => 0];
        if (!static::enabled() || !\Schema::hasTable(static::LOG_TABLE)) {
            return $result;
        }
        $today = $today ?? now()->format('Y-m-d');
        $nowHM = $nowHM ?? now()->format('H:i');
        $due = static::dueNow($today, $nowHM);
        if (empty($due)) {
            return $result;
        }
        $result['people'] = count($due);
        $content = static::gather(array_keys($due), $today);
        foreach (array_slice($due, 0, static::RUN_LIMIT, true) as $uid => $user) {
            $mine = $content[$uid] ?? [];
            $count = array_sum(array_map('count', $mine));
            if (!$dry) {
                // the log first: a digest that failed half way is not sent again today
                DB::table(static::LOG_TABLE)->insertOrIgnore(['user_id' => $uid, 'day' => $today, 'items' => $count, 'created_at' => now(), 'updated_at' => now()]);
            }
            if ($count === 0) {
                $result['empty']++;
                continue;
            }
            // counted when it went out by at least one way (a dry run: would go)
            if ($dry || static::send($user, $mine, $today)) {
                $result['sent']++;
            } else {
                $result['failed'] = ($result['failed'] ?? 0) + 1;
            }
        }

        return $result;
    }

    /**
     * The people whose digest is due now: on, their time passed, none sent today.
     *
     * @return array<int, \Exceedone\Exment\Model\CustomValue> user id => user
     */
    protected static function dueNow(string $today, string $nowHM): array
    {
        $ids = [];
        foreach (\Exceedone\Exment\Model\UserSetting::query()->get(['base_user_id', 'settings']) as $row) {
            $p = static::personal(array_get((array) ($row->settings ?? []), PortalNotifyPrefs::SETTING));
            if ($p['on'] && strcmp($nowHM, $p['time']) >= 0) {
                $ids[] = intval($row->base_user_id);
            }
        }
        if (empty($ids)) {
            return [];
        }
        $sent = DB::table(static::LOG_TABLE)->where('day', $today)->whereIn('user_id', $ids)->pluck('user_id')->map('intval')->all();
        $ids = array_values(array_diff($ids, $sent));
        $out = [];
        foreach (getModelName(SystemTableName::USER)::withoutGlobalScopes()->whereIn('id', $ids)->get() as $user) {
            $out[intval($user->id)] = $user;
        }
        return $out;
    }

    /**
     * What each of these people has today, read once for all of them.
     *
     * @param array<int, int> $userIds
     * @return array<int, array<string, array<int, array<string, mixed>>>> user id => section => entries
     */
    public static function gather(array $userIds, string $today): array
    {
        $def = PortalDefinition::config();
        $is = (array) array_get($def, 'issue', []);
        $issueTable = CustomTable::getEloquent(array_get($def, 'issue_table'));
        $projectTable = CustomTable::getEloquent(array_get($def, 'project_table'));
        if (!isset($issueTable, $projectTable)) {
            return [];
        }
        $want = array_flip($userIds);
        $out = [];
        $issues = [];
        $add = function (int $uid, string $section, $issue, array $extra = []) use (&$out, &$issues, $want) {
            if (!isset($want[$uid])) {
                return;
            }
            $issues[intval($issue->id)] = $issue;
            $out[$uid][$section][intval($issue->id)] = ['id' => intval($issue->id)] + $extra + ($out[$uid][$section][intval($issue->id)] ?? []);
        };
        $assigneesOf = function ($value) use ($is) {
            return array_map('intval', PortalMembership::ids(array_get((array) $value, array_get($is, 'assignees', 'assignees'))));
        };

        // 1. due today or past it, open
        $dueColumn = CustomColumn::getEloquent(array_get($is, 'due'), $issueTable);
        $statusColumn = CustomColumn::getEloquent(array_get($is, 'status'), $issueTable);
        if (isset($dueColumn)) {
            $query = $issueTable->getValueModel()->query()->withoutGlobalScopes()
                ->where('parent_type', $projectTable->table_name)
                ->where($dueColumn->getQueryKey(), '>=', (new \DateTimeImmutable($today))->modify('-' . static::OVERDUE_DAYS . ' day')->format('Y-m-d'))
                ->where($dueColumn->getQueryKey(), '<=', $today);
            $done = PortalReminder::doneStatusIds($def);
            if (isset($statusColumn) && !empty($done)) {
                IssueAutofill::whereOpen($query, $statusColumn->getQueryKey(), $done);
            }
            foreach ($query->orderBy('id')->limit(5000)->get() as $issue) {
                $dueDay = BusinessCalendar::normalize(array_get($issue->value, array_get($is, 'due')));
                if ($dueDay === null) {
                    continue;
                }
                foreach ($assigneesOf($issue->value) as $uid) {
                    $add($uid, 'due', $issue, ['due' => $dueDay, 'over' => strcmp($dueDay, $today) < 0]);
                }
            }
        }

        // 2. and 4. since yesterday: given to them, and the status changes of what they watch
        $since = now()->subDay();
        $revs = DB::table('revisions')->where('revisionable_type', $issueTable->table_name)
            ->where('created_at', '>=', $since)->whereNull('deleted_at')->orderBy('id')->limit(5000)->get();
        $byIssue = [];
        foreach ($revs as $rev) {
            $byIssue[intval($rev->revisionable_id)][] = $rev;
        }
        if (!empty($byIssue)) {
            $models = $issueTable->getValueModel()->query()->withoutGlobalScopes()->whereIn('id', array_keys($byIssue))->get()->keyBy('id');
            $statusKey = strval(array_get($is, 'status', 'status'));
            foreach ($byIssue as $issueId => $list) {
                $issue = $models->get($issueId);
                if (!isset($issue)) {
                    continue;
                }
                $watchers = null;
                foreach ($list as $rev) {
                    $old = json_decode(strval($rev->old_value), true) ?: [];
                    $new = json_decode(strval($rev->new_value), true) ?: [];
                    $by = intval($rev->create_user_id);
                    if (array_key_exists(array_get($is, 'assignees', 'assignees'), $new)) {
                        foreach (array_diff($assigneesOf($new), $assigneesOf($old)) as $uid) {
                            if ($uid !== $by) {
                                $add($uid, 'assigned', $issue);
                            }
                        }
                    }
                    if (array_key_exists($statusKey, $new) && intval($rev->revision_no) > 1
                        && strval(array_get($old, $statusKey, '')) !== strval(array_get($new, $statusKey, ''))) {
                        $watchers = $watchers ?? array_map('intval', Star::userIds(Star::TYPE_WATCH, $issueTable->table_name, $issueId));
                        foreach ($watchers as $uid) {
                            if ($uid !== $by) {
                                $add($uid, 'status', $issue, ['to' => strval(array_get($new, $statusKey, ''))]);
                            }
                        }
                    }
                }
            }
        }

        // 3. comments since yesterday that @mention them (Exment's "@code")
        $codes = [];
        foreach (getModelName(SystemTableName::USER)::withoutGlobalScopes()->whereIn('id', $userIds)->get() as $user) {
            $code = strval(array_get($user->value, 'user_code', ''));
            if ($code !== '') {
                $codes[intval($user->id)] = $code;
            }
        }
        if (!empty($codes)) {
            $comments = getModelName(SystemTableName::COMMENT)::withoutGlobalScopes()->where('parent_type', $issueTable->table_name)
                ->where('created_at', '>=', $since)->orderBy('id')->limit(2000)->get();
            $parents = $issueTable->getValueModel()->query()->withoutGlobalScopes()->whereIn('id', $comments->pluck('parent_id')->unique()->all())->get()->keyBy('id');
            foreach ($comments as $comment) {
                $issue = $parents->get(intval($comment->parent_id));
                if (!isset($issue)) {
                    continue;
                }
                $text = strval(array_get($comment->value, 'comment_detail', ''));
                foreach ($codes as $uid => $code) {
                    if ($uid !== intval($comment->created_user_id) && static::mentions($text, $code)) {
                        $add($uid, 'mention', $issue);
                    }
                }
            }
        }

        // what each may not read is not theirs to be told
        foreach ($out as $uid => $sections) {
            foreach ($sections as $section => $entries) {
                foreach (array_keys($entries) as $issueId) {
                    if (empty(PortalMembership::filterReaders($issues[$issueId], [$uid]))) {
                        unset($out[$uid][$section][$issueId]);
                    }
                }
                $out[$uid][$section] = array_values($out[$uid][$section]);
            }
        }
        static::$issues = $issues;

        return $out;
    }

    /** @var array<int, \Exceedone\Exment\Model\CustomValue> the issues of the last gather() */
    protected static $issues = [];

    /** Whether a comment names a person: "@code", not a part of a longer word. */
    public static function mentions(string $text, string $code): bool
    {
        return preg_match('/(^|[^0-9A-Za-z_.\-@])@' . preg_quote($code, '/') . '(?![0-9A-Za-z_\-])/u', $text) === 1;
    }

    /**
     * The words of a digest (no database: the CI checks it).
     *
     * @param array<string, array<int, array<string, mixed>>> $sections entries with key, subject, due, over, to
     * @return array{title: string, lines: array<int, string>}
     */
    public static function compose(array $sections, string $today, ?string $aiLine = null): array
    {
        $t = function ($k, $a = []) {
            return strval(PortalDefinition::trans('digest.' . $k, $a));
        };
        $lines = [];
        if ($aiLine !== null && $aiLine !== '') {
            $lines[] = $aiLine;
        }
        foreach (static::SECTIONS as $s) {
            $entries = array_values((array) ($sections[$s] ?? []));
            if (empty($entries)) {
                continue;
            }
            if ($s === 'due') {
                // the oldest due date first
                usort($entries, function ($a, $b) {
                    return strcmp(strval($a['due'] ?? ''), strval($b['due'] ?? '')) ?: (intval($a['id']) <=> intval($b['id']));
                });
            }
            $lines[] = $t('sec_' . $s, ['n' => count($entries)]);
            foreach (array_slice($entries, 0, static::PER_SECTION) as $e) {
                $line = '・' . $e['key'] . ' ' . mb_strimwidth(strval($e['subject'] ?? ''), 0, 60, '…');
                if ($s === 'due') {
                    $line .= !empty($e['over'])
                        ? $t('over', ['due' => (new \DateTimeImmutable($e['due']))->format('n/j')])
                        : $t('today');
                } elseif ($s === 'status' && strval($e['to'] ?? '') !== '') {
                    $line .= $t('status_to', ['to' => $e['to']]);
                }
                $lines[] = $line;
            }
            if (count($entries) > static::PER_SECTION) {
                $lines[] = $t('more', ['n' => count($entries) - static::PER_SECTION]);
            }
        }

        return ['title' => $t('title', ['day' => (new \DateTimeImmutable($today))->format('n/j')]), 'lines' => $lines];
    }

    /**
     * @param array<string, array<int, array<string, mixed>>> $mine
     */
    protected static function send($user, array $mine, string $today): bool
    {
        $def = PortalDefinition::config();
        $is = (array) array_get($def, 'issue', []);
        $projects = [];
        $projectTable = CustomTable::getEloquent(array_get($def, 'project_table'));
        foreach ($mine as $section => $entries) {
            foreach ($entries as $k => $e) {
                $issue = static::$issues[$e['id']] ?? null;
                if (!isset($issue)) {
                    continue;
                }
                $pid = intval($issue->parent_id);
                if (!array_key_exists($pid, $projects)) {
                    $projects[$pid] = isset($projectTable) ? $projectTable->getValueModel()->query()->withoutGlobalScopes()->find($pid) : null;
                }
                $pkey = isset($projects[$pid]) ? strtoupper(strval(array_get($projects[$pid]->value, array_get($def, 'project.key'), ''))) : '';
                $mine[$section][$k]['key'] = ($pkey !== '' ? $pkey : '#') . '-' . $issue->id;
                $mine[$section][$k]['subject'] = strval(array_get($issue->value, array_get($is, 'subject'), ''));
                if ($section === 'status' && isset($e['to'])) {
                    $mine[$section][$k]['to'] = static::statusLabel($issue, strval(array_get($is, 'status', 'status')), strval($e['to']));
                }
            }
        }
        $m = static::compose($mine, $today, static::aiLine($mine));
        $url = PortalDefinition::url();
        $prefs = PortalNotifyPrefs::channelsFor([intval($user->id)], 'digest')[intval($user->id)] ?? ['mail' => false, 'teams' => false];
        // asked for, but their notices are the bell only: the digest is not a bell, so by mail
        if (empty($prefs['mail']) && empty($prefs['teams'])) {
            $prefs['mail'] = true;
        }
        $sent = false;
        if (!empty($prefs['mail']) && !is_nullorempty($user->getValue('email'))) {
            try {
                $html = !isMatchString(System::system_mail_body_type(), MailBodyType::PLAIN);
                MailSender::make(null, $user)
                    ->subject($m['title'])
                    ->body($html ? PortalTeams::mailHtml($m['title'], '', $m['lines'], $url) : PortalTeams::mailText($m['title'], '', $m['lines'], $url))
                    ->send();
                $sent = true;
            } catch (\Throwable $e) {
                Log::warning('pm_portal digest mail to user ' . $user->id . ' failed: ' . get_class($e));
            }
        }
        if (!empty($prefs['teams'])) {
            try {
                call_user_func(['Exceedone\Exment\Services\NotifyService', 'notifyTeamsPersonal'], [
                    'user' => $user,
                    'custom_value' => null,
                    'subject' => $m['title'],
                    'body' => PortalTeams::body('', $m['lines']),
                    'url' => $url,
                ]);
                $sent = true;
            } catch (\Throwable $e) {
                Log::warning('pm_portal digest teams to user ' . $user->id . ' failed: ' . get_class($e));
            }
        }

        return $sent;
    }

    protected static function statusLabel($issue, string $column, string $value): string
    {
        try {
            $copy = $issue->replicate();
            $copy->setValue([$column => $value]);
            $label = $copy->getValue($column, true);
            return is_scalar($label) && strval($label) !== '' ? strval($label) : $value;
        } catch (\Throwable $e) {
            return $value;
        }
    }

    /**
     * The AI's line: which of the person's issues to begin with. Only with the site's AI and the
     * plugin's option 「朝のまとめに AI のおすすめを入れる」; the subjects go out masked. null when
     * there is no AI, too little to choose from, or the AI is not sure.
     *
     * @param array<string, array<int, array<string, mixed>>> $mine
     */
    protected static function aiLine(array $mine): ?string
    {
        if (!IssueAutofill::flag('digest_ai', false) || !JevClient::configured()) {
            return null;
        }
        $criteria = [];
        foreach (['due', 'assigned', 'mention'] as $s) {
            foreach ((array) ($mine[$s] ?? []) as $e) {
                if (count($criteria) >= 10 || isset($criteria[$e['key']])) {
                    continue;
                }
                $criteria[$e['key']] = IssueAutofill::maskPii(mb_strimwidth(strval($e['subject'] ?? ''), 0, 80, '…'))
                    . ($s === 'due' ? (!empty($e['over']) ? ' (overdue since ' . $e['due'] . ')' : ' (due today)') : ($s === 'mention' ? ' (someone asked me in a comment)' : ' (newly assigned to me)'));
            }
        }
        if (count($criteria) < 2) {
            return null;
        }
        try {
            $res = (new JevClient())->ask('Today is ' . now()->format('l, Y-m-d') . '. These are my open tasks.', [
                'focus' => [
                    'type' => 'choice',
                    'instructions' => 'Which one task should I work on first this morning? Prefer what is overdue or blocks other people, then what is due today.',
                    'criteria' => $criteria,
                ],
            ]);
            $answer = (array) array_get($res, 'answers.focus', []);
            $choice = strval($answer['choice'] ?? '');
            $confidence = floatval($answer['confidence'] ?? 0);
            if (!isset($criteria[$choice]) || $confidence < 0.3) {
                return null;
            }
            foreach (['due', 'assigned', 'mention'] as $s) {
                foreach ((array) ($mine[$s] ?? []) as $e) {
                    if ($e['key'] === $choice) {
                        return strval(PortalDefinition::trans('digest.ai_line', ['key' => $e['key'], 'subject' => mb_strimwidth(strval($e['subject'] ?? ''), 0, 60, '…')]));
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::info('pm_portal digest: no AI line (' . get_class($e) . ')');
        }
        return null;
    }
}
