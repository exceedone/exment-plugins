<?php

namespace App\Plugins\ExmentPM;

use Exceedone\Exment\Enums\SystemTableName;
use Exceedone\Exment\Model\CustomValue;
use Illuminate\Support\Facades\Log;

/**
 * Where each person's notice arrives besides the bell (ISS02931), for the writer to see before
 * sending and to be told after: each person's own channels, plus what the writer ticked for this
 * one notice ("also by e-mail", "also by Teams").
 *
 * The mail of "also by e-mail" goes the way it always has (PortalTeams, $mailAll). "Also by
 * Teams" sends Teams here, after the answer, only to the people of this write's bells whose own
 * channels do not have Teams already (PortalTeams sends to the others).
 */
class PortalNotifyTo
{
    /**
     * Each person's own channels besides the bell, for an event of a project (App's
     * PortalNotifyPrefs::channelsFor): user id => ['mail' => bool, 'teams' => bool].
     *
     * @param array<int, mixed> $user_ids
     * @return array<string, array{mail: bool, teams: bool}>
     */
    public static function channels(array $user_ids, int $project_id = 0, string $kind = 'updated'): array
    {
        $out = [];
        try {
            foreach (PortalNotifyPrefs::channelsFor($user_ids, PortalNotifyPrefs::eventOfKind($kind), $project_id) as $id => $ch) {
                $out[strval($id)] = ['mail' => !empty($ch['mail']), 'teams' => !empty($ch['teams'])];
            }
        } catch (\Throwable $e) {
            Log::warning('pm_portal notice channels failed: ' . $e->getMessage());
        }

        return $out;
    }

    /**
     * Where one person's notice goes for this write, besides the bell.
     *
     * @param array{mail?: bool, teams?: bool} $own their own channels
     * @return array{mail: bool, teams: bool}
     */
    public static function route(array $own, bool $hasMail, bool $mailAll, bool $teamsAll, bool $teamsOn): array
    {
        return [
            'mail' => $hasMail && ($mailAll || !empty($own['mail'])),
            'teams' => $teamsOn && ($teamsAll || !empty($own['teams'])),
        ];
    }

    /**
     * Who of these people (the bells of one write) gets a mail and who a Teams message.
     *
     * @param array<int, int> $targets user ids
     * @return array{mail: array<int, int>, teams: array<int, int>}
     */
    public static function plan(array $targets, bool $mailAll, bool $teamsAll, int $project_id = 0): array
    {
        $out = ['mail' => [], 'teams' => []];
        if (empty($targets)) {
            return $out;
        }
        $own = static::channels($targets, $project_id);
        $teamsOn = PortalTeams::teamsAvailable();
        $users = getModelName(SystemTableName::USER)::withoutGlobalScopes()->whereIn('id', $targets)->get();
        foreach ($users as $user) {
            $to = static::route((array) array_get($own, strval($user->id), []), !is_nullorempty($user->getValue('email')), $mailAll, $teamsAll, $teamsOn);
            foreach (['mail', 'teams'] as $by) {
                if ($to[$by]) {
                    $out[$by][] = intval($user->id);
                }
            }
        }

        return $out;
    }

    /**
     * "Also by Teams": after the answer, Teams for the people of this write's bells who would not
     * get it anyway.
     *
     * @param array<int, string> $lines
     */
    public static function teamsAfterResponse(string $issue_table, CustomValue $issue, int $bell_mark, $me, string $kind, string $key, string $subject, array $lines, string $url, int $project_id = 0): void
    {
        if (!PortalTeams::teamsAvailable()) {
            return;
        }
        $issue_id = intval($issue->id);
        app()->terminating(function () use ($issue_table, $issue_id, $bell_mark, $me, $kind, $key, $subject, $lines, $url, $project_id) {
            try {
                $targets = PortalMail::bellTargets($issue_table, $issue_id, $bell_mark, $me);
                $own = static::channels($targets, $project_id, $kind);
                $targets = array_values(array_filter($targets, function ($id) use ($own) {
                    return !array_get($own, strval($id) . '.teams', false);
                }));
                if (empty($targets)) {
                    return;
                }
                $issue = getModelName($issue_table)::withoutGlobalScopes()->find($issue_id);
                $users = getModelName(SystemTableName::USER)::withoutGlobalScopes()->whereIn('id', $targets)->get();
                foreach ($users as $user) {
                    try {
                        call_user_func(['Exceedone\Exment\Services\NotifyService', 'notifyTeamsPersonal'], [
                            'user' => $user,
                            'custom_value' => $issue,
                            'subject' => PortalTeams::title($kind, $key),
                            'body' => PortalTeams::body($subject, $lines),
                            'url' => $url,
                        ]);
                    } catch (\Throwable $e) {
                        Log::warning('pm_portal teams to user ' . $user->id . ' on issue ' . $issue_id . ' failed: ' . $e->getMessage());
                    }
                }
            } catch (\Throwable $e) {
                Log::error('pm_portal teams notices for issue ' . $issue_id . ' failed: ' . $e->getMessage());
            }
        });
    }
}
