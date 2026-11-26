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
        $choices = static::choicesOf($ids);
        $out = [];
        foreach (getModelName(SystemTableName::USER)::withoutGlobalScopes()->whereIn('id', $ids)->pluck('id') as $id) {
            $choice = $choices[intval($id)] ?? PortalTeams::siteDefault();
            $out[intval($id)] = [
                'bell' => true,
                'mail' => in_array($choice, ['mail', 'both'], true),
                'teams' => $teams && in_array($choice, ['teams', 'both'], true),
            ];
        }

        return $out;
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
