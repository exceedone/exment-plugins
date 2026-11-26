<?php

namespace App\Plugins\ExmentPM;

use Exceedone\Exment\Enums\SystemTableName;

/**
 * Where each person gets a notice of the portal: the bell, the mail, Teams (ISS02863, ISS02931).
 * The receiver decides; the screens that pick people (the notify chips) and the senders
 * (PortalTeams, the reminders) read it here and nowhere else.
 *
 * channelsFor() answers per person and per event. For now it is each person's one choice of the
 * "Notices" menu (PortalTeams::USER_SETTING: bell / mail / teams / both, the site's default when
 * none), the same for every event; the settings per event, per project, quiet hours and a daily
 * digest come next, behind this same function.
 */
class PortalNotifyPrefs
{
    /** A new issue, assigned to me, a comment, a status change, a mention, a reminder. */
    const EVENTS = ['new', 'assigned', 'comment', 'status', 'mention', 'remind'];

    /**
     * The events the senders tell apart today: a new issue, a change or a comment of an issue
     * (PortalTeams 'updated'), a reminder. The page offers these; the others come when the
     * senders say them.
     */
    const OFFERED = ['new', 'comment', 'remind'];

    /** The settings of a person (Exment's user settings): per event, per project, quiet hours. */
    const SETTING = 'pmportal.notify_prefs';

    /**
     * The channels of some people for one event of a project.
     *
     * @param array<int, int|string> $userIds
     * @return array<int, array{bell: bool, mail: bool, teams: bool}> user id => channels
     */
    public static function channelsFor(array $userIds, string $event, int $projectId = 0): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $userIds))));
        if (empty($ids)) {
            return [];
        }
        $teams = PortalTeams::teamsAvailable();
        [$choices, $prefs] = static::read($ids);
        $now = now()->format('H:i');
        $out = [];
        foreach (getModelName(SystemTableName::USER)::withoutGlobalScopes()->whereIn('id', $ids)->pluck('id') as $id) {
            $id = intval($id);
            $out[$id] = static::decide($choices[$id] ?? PortalTeams::siteDefault(), $prefs[$id] ?? [], $event, $projectId, $now, $teams);
        }

        return $out;
    }

    /**
     * One person's channels, from their choice of the menu and their settings (no database: a
     * test calls it as it is).
     *
     * - the choice of the 「通知」 menu: bell only / mail too / Teams too / both
     * - settings per event (prefs.events.<event>: {mail, teams}) put that aside for the event
     * - a project muted (prefs.projects.<id> = 'mute'): no mail and no Teams from it
     * - quiet hours (prefs.quiet: {from, to}, "22:00" to "07:00" over midnight): no mail and
     *   no Teams then
     * The bell always: it is Exment's, read when the person comes.
     *
     * @param array<string, mixed> $prefs
     * @return array{bell: bool, mail: bool, teams: bool}
     */
    public static function decide(string $choice, array $prefs, string $event, int $projectId, string $nowHM, bool $teamsOn): array
    {
        $mail = in_array($choice, ['mail', 'both'], true);
        $teams = in_array($choice, ['teams', 'both'], true);
        $byEvent = array_get($prefs, 'events.' . $event);
        if (is_array($byEvent)) {
            $mail = !empty($byEvent['mail']);
            $teams = !empty($byEvent['teams']);
        }
        if ($projectId > 0 && strval(array_get($prefs, 'projects.' . $projectId)) === 'mute') {
            $mail = false;
            $teams = false;
        }
        if (static::quietNow(array_get($prefs, 'quiet'), $nowHM)) {
            $mail = false;
            $teams = false;
        }

        return ['bell' => true, 'mail' => $mail, 'teams' => $teamsOn && $teams];
    }

    /** Whether a time ("23:10") is in quiet hours ({from: "22:00", to: "07:00"}; either order). */
    public static function quietNow($quiet, string $nowHM): bool
    {
        if (!is_array($quiet)) {
            return false;
        }
        $from = static::hm($quiet['from'] ?? null);
        $to = static::hm($quiet['to'] ?? null);
        if ($from === null || $to === null || $from === $to) {
            return false;
        }
        return strcmp($from, $to) < 0
            ? strcmp($nowHM, $from) >= 0 && strcmp($nowHM, $to) < 0
            : strcmp($nowHM, $from) >= 0 || strcmp($nowHM, $to) < 0;
    }

    public static function hm($v): ?string
    {
        if (!is_scalar($v) || !preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', strval($v), $m)) {
            return null;
        }
        return sprintf('%02d:%s', intval($m[1]), $m[2]);
    }

    /**
     * The settings as they are kept, from the form: only what is known, nothing else.
     *
     * @param array<string, mixed> $in
     * @return array<string, mixed>
     */
    public static function clean(array $in): array
    {
        $out = ['events' => [], 'projects' => [], 'quiet' => null];
        foreach (static::EVENTS as $e) {
            $v = $in['events'][$e] ?? null;
            if (is_array($v) && !empty($v['own'])) {
                $out['events'][$e] = ['mail' => !empty($v['mail']), 'teams' => !empty($v['teams'])];
            }
        }
        foreach ((array) ($in['projects'] ?? []) as $pid => $mode) {
            if (ctype_digit(strval($pid)) && $mode === 'mute') {
                $out['projects'][strval(intval($pid))] = 'mute';
            }
        }
        $from = static::hm($in['quiet']['from'] ?? null);
        $to = static::hm($in['quiet']['to'] ?? null);
        if (!empty($in['quiet']['on']) && $from !== null && $to !== null && $from !== $to) {
            $out['quiet'] = ['from' => $from, 'to' => $to];
        }

        return $out;
    }

    /**
     * The choice and the settings of some people, in one read of user_settings.
     *
     * @return array{0: array<int, string>, 1: array<int, array<string, mixed>>}
     */
    protected static function read(array $ids): array
    {
        $choices = array_fill_keys($ids, PortalTeams::siteDefault());
        $prefs = [];
        try {
            foreach (\Exceedone\Exment\Model\UserSetting::whereIn('base_user_id', $ids)->get(['base_user_id', 'settings']) as $row) {
                $settings = (array) ($row->settings ?? []);
                $value = strval(array_get($settings, PortalTeams::USER_SETTING));
                if (in_array($value, PortalTeams::CHOICES, true)) {
                    $choices[intval($row->base_user_id)] = $value;
                }
                $p = array_get($settings, static::SETTING);
                if (is_array($p)) {
                    $prefs[intval($row->base_user_id)] = $p;
                }
            }
        } catch (\Throwable $e) {
            // the site's default for everyone
        }

        return [$choices, $prefs];
    }

    /**
     * The choice of the 「通知」 menu of some people, read in one query (PortalTeams::choiceOf for
     * each would read user_settings once per person: a project of 30 members, 30 reads).
     *
     * @param array<int, int> $ids
     * @return array<int, string> user id => bell / mail / teams / both
     */
    public static function choicesOf(array $ids): array
    {
        $out = array_fill_keys($ids, PortalTeams::siteDefault());
        try {
            foreach (\Exceedone\Exment\Model\UserSetting::whereIn('base_user_id', $ids)->get(['base_user_id', 'settings']) as $row) {
                $value = strval(array_get((array) ($row->settings ?? []), PortalTeams::USER_SETTING));
                if (in_array($value, PortalTeams::CHOICES, true)) {
                    $out[intval($row->base_user_id)] = $value;
                }
            }
        } catch (\Throwable $e) {
            // the site's default for everyone
        }

        return $out;
    }

    /** The event of a notice of PortalTeams ('new', 'updated', 'remind'). */
    public static function eventOfKind(string $kind): string
    {
        if (in_array($kind, static::EVENTS, true)) {
            return $kind;
        }
        // an issue saved: its comment, or its change
        return 'comment';
    }
}
