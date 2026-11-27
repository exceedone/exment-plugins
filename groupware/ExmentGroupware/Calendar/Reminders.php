<?php

namespace App\Plugins\ExmentGroupware\Calendar;

use Exceedone\Exment\Enums\MailBodyType;
use Exceedone\Exment\Enums\SystemTableName;
use Exceedone\Exment\Model\NotifyNavbar;
use Exceedone\Exment\Model\System;
use Exceedone\Exment\Notifications\MailSender;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The reminders of the calendar (ISS02947, part 2): before an occurrence, its owner and the
 * attendees who have not said 「出ない」 are told - the bell always, and the mail or Teams as each
 * person chose in ExmentPM's 「通知の受け取り方」 (read as data, Exment's user settings; with no
 * choice: the bell and the mail). Quiet hours there keep the mail and Teams back.
 *
 * Run after the answer of a calendar request (at most every few minutes) and, once Groupware has a
 * batch, from it. A reminder a run reaches late (up to LATE minutes) is still sent; older, not.
 * The log comes first: a reminder is sent once.
 */
class Reminders
{
    const TABLE = 'gw_cal_reminders';
    const LOGS = 'gw_cal_reminder_logs';
    /** The choices of the form: minutes before (0: when it starts). */
    const CHOICES = [0, 5, 10, 15, 30, 60, 120, 1440, 2880];
    const LATE = 30;
    const TICK_MINUTES = 3;
    const RUN_LIMIT = 500;

    /** ExmentPM's settings of a person (Exment's user settings), read as data. */
    const PM_CHOICE = 'pmportal.notify_by';
    const PM_PREFS = 'pmportal.notify_prefs';

    public static function ready(): bool
    {
        try {
            return \Schema::hasTable(static::TABLE) && \Schema::hasTable(static::LOGS);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** After the answer of this request, a run when the last one is a few minutes old. */
    public static function afterResponse(string $url): void
    {
        app()->terminating(function () use ($url) {
            try {
                if (static::ready() && Cache::add('gw_cal_remind:' . DB::connection()->getDatabaseName(), 1, now()->addMinutes(static::TICK_MINUTES))) {
                    static::run(null, $url);
                }
            } catch (\Throwable $e) {
                Log::warning('gw_calendar reminders: ' . get_class($e));
            }
        });
    }

    /**
     * The reminders due now, sent.
     *
     * @return array{sent: int, people: int}
     */
    public static function run(?string $nowUtc = null, string $url = ''): array
    {
        $result = ['sent' => 0, 'people' => 0];
        if (!static::ready()) {
            return $result;
        }
        $utc = new \DateTimeZone('UTC');
        $now = new \DateTimeImmutable($nowUtc ?? 'now', $utc);
        $byEvent = [];
        foreach (DB::table(static::TABLE)->get() as $r) {
            $byEvent[intval($r->event_id)][] = intval($r->minutes_before);
        }
        if (empty($byEvent)) {
            return $result;
        }
        $maxBefore = max(array_map('max', $byEvent));
        $from = $now->modify('-' . static::LATE . ' minute')->format('Y-m-d H:i:s');
        $to = $now->modify('+' . ($maxBefore + 1) . ' minute')->format('Y-m-d H:i:s');
        $events = DB::table(Events::EVENTS)->whereNull('deleted_at')->whereIn('id', array_keys($byEvent))->get();
        $overrides = [];
        foreach (DB::table(Events::OVERRIDES)->whereIn('event_id', array_keys($byEvent))->get() as $o) {
            $overrides[intval($o->event_id)][] = (array) $o;
        }
        $due = [];
        foreach ($events as $row) {
            $e = (array) $row;
            $eid = intval($e['id']);
            foreach (Occurrences::between($e, $from, $to, $overrides[$eid] ?? []) as $occ) {
                foreach (static::dueMinutes($occ['start'], $byEvent[$eid], $now) as $m) {
                    $due[] = ['event' => $e, 'occ' => $occ, 'minutes' => $m];
                }
            }
        }
        foreach (array_slice($due, 0, static::RUN_LIMIT) as $d) {
            $eid = intval($d['event']['id']);
            $people = static::targets($eid, intval($d['event']['owner_user_id']));
            foreach ($people as $uid) {
                $logged = DB::table(static::LOGS)->insertOrIgnore([
                    'event_id' => $eid, 'occurrence' => $d['occ']['original'], 'user_id' => $uid, 'minutes_before' => $d['minutes'],
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                if ($logged === 0) {
                    continue;
                }
                static::send($uid, $d['event'], $d['occ'], $d['minutes'], $url);
                $result['sent']++;
            }
            $result['people'] += count($people);
        }
        return $result;
    }

    /**
     * The minutes of an occurrence's reminders that are due now: their time has come, and not
     * longer ago than LATE (no database: the CI checks it).
     *
     * @param array<int, int> $minutes
     * @return array<int, int>
     */
    public static function dueMinutes(string $startUtc, array $minutes, \DateTimeImmutable $now): array
    {
        $start = new \DateTimeImmutable($startUtc, new \DateTimeZone('UTC'));
        $out = [];
        foreach ($minutes as $m) {
            $at = $start->modify('-' . intval($m) . ' minute');
            if ($at <= $now && $at > $now->modify('-' . static::LATE . ' minute') && $start >= $now->modify('-' . static::LATE . ' minute')) {
                $out[] = intval($m);
            }
        }
        return $out;
    }

    /** @return array<int, int> the owner and the attendees who have not said no */
    protected static function targets(int $eventId, int $owner): array
    {
        $ids = [$owner];
        foreach (DB::table(Events::ATTENDEES)->where('event_id', $eventId)->get() as $a) {
            if (strval($a->response) !== 'no') {
                $ids[] = intval($a->user_id);
            } elseif (intval($a->user_id) === $owner) {
                $ids = array_diff($ids, [$owner]);
            }
        }
        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * Where a person is told, from ExmentPM's settings when they are there (no database: the CI
     * checks it): bell always; mail and Teams by the choice, not in quiet hours.
     *
     * @param mixed $choice bell | mail | teams | both, or null (none chosen: bell and mail)
     * @param mixed $prefs ExmentPM's prefs (quiet: {from, to})
     * @return array{bell: bool, mail: bool, teams: bool}
     */
    public static function channels($choice, $prefs, string $nowHM): array
    {
        $choice = in_array($choice, ['bell', 'mail', 'teams', 'both'], true) ? $choice : 'mail';
        $mail = in_array($choice, ['mail', 'both'], true);
        $teams = in_array($choice, ['teams', 'both'], true);
        $quiet = is_array($prefs) ? ($prefs['quiet'] ?? null) : null;
        if (is_array($quiet) && static::inQuiet(strval($quiet['from'] ?? ''), strval($quiet['to'] ?? ''), $nowHM)) {
            $mail = false;
            $teams = false;
        }
        return ['bell' => true, 'mail' => $mail, 'teams' => $teams];
    }

    public static function inQuiet(string $from, string $to, string $now): bool
    {
        if (!preg_match('/^\d{2}:\d{2}$/', $from) || !preg_match('/^\d{2}:\d{2}$/', $to) || $from === $to) {
            return false;
        }
        return strcmp($from, $to) < 0 ? (strcmp($now, $from) >= 0 && strcmp($now, $to) < 0) : (strcmp($now, $from) >= 0 || strcmp($now, $to) < 0);
    }

    /** The words of a reminder (no database: the CI checks it). */
    public static function text(int $minutes, array $words): string
    {
        if ($minutes === 0) {
            return strval($words['rem_now'] ?? 'Starts now');
        }
        if ($minutes % 1440 === 0) {
            return str_replace(':n', strval(intdiv($minutes, 1440)), strval($words['rem_in_days'] ?? 'In :n days'));
        }
        if ($minutes % 60 === 0) {
            return str_replace(':n', strval(intdiv($minutes, 60)), strval($words['rem_in_hours'] ?? 'In :n hours'));
        }
        return str_replace(':n', strval($minutes), strval($words['rem_in_minutes'] ?? 'In :n minutes'));
    }

    /**
     * @param array<string, mixed> $e
     * @param array<string, mixed> $occ
     */
    protected static function send(int $uid, array $e, array $occ, int $minutes, string $url): void
    {
        $words = (array) trans('exment_exment_groupware::groupware.cal');
        $title = strval($occ['title'] ?? $e['title']);
        $when = static::when($occ, boolval($e['all_day']), strval($e['tz']));
        $line = static::text($minutes, $words) . ' — ' . $when;
        $place = strval($occ['location'] ?? $e['location'] ?? '');
        $lines = array_values(array_filter([$line, $place !== '' ? strval($words['f_location'] ?? '') . ': ' . $place : '']));
        try {
            $bell = new NotifyNavbar();
            $bell->fill([
                'notify_id' => 0,
                'target_user_id' => $uid,
                'notify_subject' => mb_substr(strval($words['rem_title'] ?? 'Reminder') . ': ' . $title, 0, 200),
                'notify_body' => mb_substr(implode("\n", $lines) . ($url !== '' ? "\n" . $url : ''), 0, 2000),
                'trigger_user_id' => null,
            ]);
            $bell->save();
        } catch (\Throwable $ex) {
            Log::warning('gw_calendar reminder bell to user ' . $uid . ' failed (' . get_class($ex) . ')');
        }
        [$choice, $prefs] = static::settingsOf($uid);
        $ch = static::channels($choice, $prefs, now()->format('H:i'));
        $user = getModelName(SystemTableName::USER)::withoutGlobalScopes()->find($uid);
        if (!isset($user)) {
            return;
        }
        if ($ch['mail'] && !is_nullorempty($user->getValue('email'))) {
            try {
                $html = !isMatchString(System::system_mail_body_type(), MailBodyType::PLAIN);
                $esc = function ($s) {
                    return htmlspecialchars(strval($s), ENT_QUOTES, 'UTF-8');
                };
                $body = $html
                    ? '<p><b>' . $esc($title) . '</b></p><p>' . implode('<br>', array_map($esc, $lines)) . '</p>' . ($url !== '' ? '<p><a href="' . $esc($url) . '">' . $esc($url) . '</a></p>' : '')
                    : $title . "\n\n" . implode("\n", $lines) . ($url !== '' ? "\n\n" . $url : '');
                MailSender::make(null, $user)->subject(strval($words['rem_title'] ?? 'Reminder') . ': ' . $title)->body($body)->send();
            } catch (\Throwable $ex) {
                Log::warning('gw_calendar reminder mail to user ' . $uid . ' failed (' . get_class($ex) . ')');
            }
        }
        if ($ch['teams'] && method_exists('Exceedone\Exment\Services\NotifyService', 'notifyTeamsPersonal')) {
            try {
                call_user_func(['Exceedone\Exment\Services\NotifyService', 'notifyTeamsPersonal'], [
                    'user' => $user, 'custom_value' => null,
                    'subject' => strval($words['rem_title'] ?? 'Reminder') . ': ' . $title,
                    'body' => implode("\n", $lines), 'url' => $url,
                ]);
            } catch (\Throwable $ex) {
                Log::warning('gw_calendar reminder teams to user ' . $uid . ' failed (' . get_class($ex) . ')');
            }
        }
    }

    /** @return array{0: mixed, 1: mixed} ExmentPM's choice and prefs of a person */
    protected static function settingsOf(int $uid): array
    {
        try {
            $row = \Exceedone\Exment\Model\UserSetting::where('base_user_id', $uid)->first(['settings']);
            $s = isset($row) ? (array) ($row->settings ?? []) : [];
            return [array_get($s, static::PM_CHOICE), array_get($s, static::PM_PREFS)];
        } catch (\Throwable $e) {
            return [null, null];
        }
    }

    /** @param array<string, mixed> $occ */
    protected static function when(array $occ, bool $allDay, string $tz): string
    {
        try {
            $zone = new \DateTimeZone(strval(config('app.timezone') ?: $tz));
            $s = new \DateTimeImmutable($occ['start'], new \DateTimeZone('UTC'));
            return $allDay ? $s->format('Y-m-d') : $s->setTimezone($zone)->format('Y-m-d H:i');
        } catch (\Throwable $e) {
            return strval($occ['start']);
        }
    }
}
