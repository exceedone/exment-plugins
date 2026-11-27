<?php

namespace App\Plugins\ExmentPM;

use Exceedone\Exment\Model\CustomTable;
use Exceedone\Exment\Model\CustomValue;
use Exceedone\Exment\Model\CustomValueAuthoritable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The shares of an issue the plugin makes by itself: one a recurring schedule makes for a date
 * (PortalSchedule), or the next issue of a recurring issue (PortalRecurrence).
 *
 * Those runs often happen inside somebody's page load or API call, and Exment hands whoever is
 * logged in a share on every row saved in their request (the creator's share: edit for the user,
 * and edit or view for their organizations, by System::custom_value_save_autoshare). The person
 * whose page load happened to run the schedule did not make the issue, and may not even be on
 * its project. So a run saves its issue with saveNew(): the only shares the issue gets are the
 * ones the share settings give the people its columns name (its assignees and the like), the
 * same as the batch makes when it runs with nobody logged in. The project's members reach the
 * issue through the project, as for every issue.
 *
 * repair() takes back the creator's shares that runs gave before this, on the issues the
 * schedules made only:
 *
 *   php artisan exment:batch --name=ExmentPM --param1=schedule_shares                 (dry run: lists them)
 *   php artisan exment:batch --name=ExmentPM --param1=schedule_shares --param2=apply  (removes them)
 */
class PortalRunShares
{
    // Exment's values (Permission, SystemTableName), here so that the rules run without Exment
    const EDIT = 'custom_value_edit';
    const VIEW = 'custom_value_view';
    const USER = 'user';
    const ORG = 'organization';

    /** A creator's share is written in the same save as its issue: seconds either side. */
    const WINDOW = 300;

    /**
     * The rows saveNew() is saving, by object id.
     *
     * @var array<int, CustomValue>
     */
    protected static $saving = [];

    /**
     * Which event dispatchers already have the listener, so it goes on once per process.
     *
     * @var array<int, bool>
     */
    protected static $listening = [];

    /**
     * Save a new row the plugin makes by itself, without a creator's share for whoever's request
     * it is: a share on the row that its share settings do not give is not written, and one that
     * got in all the same (another Exment version) is taken off right after.
     */
    public static function saveNew(CustomValue $row): void
    {
        static::listen();
        $key = spl_object_id($row);
        static::$saving[$key] = $row;
        try {
            $row->save();
        } finally {
            unset(static::$saving[$key]);
        }
        static::prune($row);
    }

    /**
     * Whether a share may be written on a row saveNew() is saving: only the ones its share
     * settings give ($wanted, PortalMembership::wantedShares), and an edit share only where a
     * setting gives edit.
     *
     * @param array<string, bool> $wanted "user:5" => edit
     */
    public static function allowed(array $wanted, string $permission, string $type, int $id): bool
    {
        $key = $type . ':' . $id;
        if (!array_key_exists($key, $wanted)) {
            return false;
        }

        return $permission !== self::EDIT || $wanted[$key];
    }

    /**
     * Vetoes the shares Exment writes during saveNew() that the row's settings do not give. Eloquent
     * stops a create whose "creating" listener answers false; Exment's creator's share is written
     * with firstOrCreate, so it is never written at all.
     */
    protected static function listen(): void
    {
        $dispatcher = CustomValueAuthoritable::getEventDispatcher();
        if (!$dispatcher) {
            return;
        }
        $key = spl_object_id($dispatcher);
        if (isset(static::$listening[$key])) {
            return;
        }
        static::$listening[$key] = true;

        CustomValueAuthoritable::creating(function ($share) {
            foreach (static::$saving as $row) {
                if (!static::isOn($share, $row)) {
                    continue;
                }
                if (!static::allowed(PortalMembership::wantedShares($row), strval($share->authoritable_type), strval($share->authoritable_user_org_type), intval($share->authoritable_target_id))) {
                    return false;
                }
            }

            return null;
        });
    }

    /**
     * @param mixed $share
     */
    protected static function isOn($share, CustomValue $row): bool
    {
        $table = $row->custom_table;

        return isset($table) && !is_nullorempty($row->id)
            && intval($share->parent_id) === intval($row->id)
            && strval($share->parent_type) === strval($table->table_name);
    }

    /**
     * Take off the shares on a row just saved by saveNew() that its settings do not give. The row
     * is new, so nobody has shared it by hand yet.
     *
     * @return int the shares taken off
     */
    protected static function prune(CustomValue $row): int
    {
        $table = $row->custom_table;
        if (!isset($table) || is_nullorempty($row->id)) {
            return 0;
        }
        $wanted = PortalMembership::wantedShares($row);
        $ids = [];
        foreach (CustomValueAuthoritable::where('parent_type', $table->table_name)->where('parent_id', $row->id)->get() as $share) {
            if (!static::allowed($wanted, strval($share->authoritable_type), strval($share->authoritable_user_org_type), intval($share->authoritable_target_id))) {
                $ids[] = intval($share->id);
            }
        }
        if (!empty($ids)) {
            CustomValueAuthoritable::whereIn('id', $ids)->delete();
        }

        return count($ids);
    }

    /**
     * The creator's shares on one issue a schedule made that its project does not justify.
     *
     * A share is one when all of these hold:
     *   - it is on a user or an organization, and the issue's share settings do not give it (an
     *     assignee's share stays),
     *   - it was written within WINDOW seconds of the issue itself (a share given by hand later
     *     stays),
     *   - for a user: an edit share, and the user is not a manager or a member of the project
     *     (a viewer keeps a view share),
     *   - for an organization: the organization is not one of the project's member organizations
     *     (a viewer organization keeps a view share), and a user's creator's share was written with
     *     it - Exment writes the organizations' with the user's, never alone.
     * A project that names nobody does not use membership, and none of its issues' shares are one.
     *
     * @param array<int, array{id: int, type: string, target: int, permission: string, at: ?int}> $shares
     * @param array<string, bool> $wanted PortalMembership::wantedShares of the issue
     * @param array<int, string> $userRoles user id => role on the project (PortalMembership::roles)
     * @param array<int, string> $orgRoles organization id => role (PortalMembership::orgRoles)
     * @param ?int $madeAt when the issue was made (a Unix time)
     * @return array<int, array{id: int, type: string, target: int, permission: string, at: ?int}>
     */
    public static function leaks(array $shares, array $wanted, array $userRoles, array $orgRoles, ?int $madeAt): array
    {
        if ($madeAt === null || (empty($userRoles) && empty($orgRoles))) {
            return [];
        }
        $justified = function (?string $role, string $permission) {
            return in_array($role, PortalMembership::EDITORS, true)
                || ($role === PortalMembership::VIEWER && $permission === self::VIEW);
        };
        $candidates = [];
        $creator = false;
        foreach ($shares as $s) {
            if (!in_array($s['type'], [self::USER, self::ORG], true)
                || static::allowed($wanted, $s['permission'], $s['type'], $s['target'])
                || $s['at'] === null || abs($s['at'] - $madeAt) > self::WINDOW) {
                continue;
            }
            if ($s['type'] === self::USER) {
                // a user share in the save of the issue that no setting gives: the creator's
                $creator = $creator || $s['permission'] === self::EDIT;
                if ($s['permission'] === self::EDIT && !$justified($userRoles[$s['target']] ?? null, $s['permission'])) {
                    $candidates[] = $s;
                }
                continue;
            }
            if (!$justified($orgRoles[$s['target']] ?? null, $s['permission'])) {
                $candidates[] = $s;
            }
        }

        return array_values(array_filter($candidates, function ($s) use ($creator) {
            return $s['type'] === self::USER || $creator;
        }));
    }

    /**
     * Find (and with $apply, remove) the creator's shares that runs of the schedules gave on the
     * issues they made (pm_portal_schedule_items), by leaks(). Safe to run again: what it removed
     * is gone, and nothing else matches.
     *
     * @return array{issues: int, found: array<int, array<string, mixed>>, removed: int}
     */
    public static function repair(bool $apply): array
    {
        $out = ['issues' => 0, 'found' => [], 'removed' => 0];
        if (!\Schema::hasTable(PortalSchedule::ITEMS)) {
            return $out;
        }
        $def = PortalDefinition::config();
        $issueTable = CustomTable::getEloquent(array_get($def, 'issue_table'));
        $projectTable = CustomTable::getEloquent(array_get($def, 'project_table'));
        if (!isset($issueTable, $projectTable)) {
            return $out;
        }
        $issueIds = DB::table(PortalSchedule::ITEMS)->where('issue_id', '>', 0)->distinct()->orderBy('issue_id')->pluck('issue_id')
            ->map(function ($id) {
                return intval($id);
            })->all();
        $projects = [];
        foreach (array_chunk($issueIds, 200) as $chunk) {
            $issues = $issueTable->getValueModel()->newQueryWithoutScopes()->whereIn('id', $chunk)->get();
            $byIssue = [];
            foreach (CustomValueAuthoritable::where('parent_type', $issueTable->table_name)->whereIn('parent_id', $chunk)->get() as $share) {
                $at = $share->created_at ? strtotime(strval($share->created_at)) : false;
                $byIssue[intval($share->parent_id)][] = [
                    'id' => intval($share->id),
                    'type' => strval($share->authoritable_user_org_type),
                    'target' => intval($share->authoritable_target_id),
                    'permission' => strval($share->authoritable_type),
                    'at' => $at === false ? null : $at,
                ];
            }
            foreach ($issues as $issue) {
                $out['issues']++;
                if (empty($byIssue[intval($issue->id)]) || $issue->parent_type !== $projectTable->table_name) {
                    continue;
                }
                $pid = intval($issue->parent_id);
                if (!array_key_exists($pid, $projects)) {
                    $project = $projectTable->getValueModel()->newQueryWithoutScopes()->find($pid);
                    $value = isset($project) ? (array) $project->value : null;
                    $userRoles = $value === null ? [] : PortalMembership::roles($value, true);
                    // whoever made the project may manage it (PortalMembership::canManage)
                    if (isset($project) && intval($project->created_user_id) > 0 && !empty($userRoles)) {
                        $userRoles[intval($project->created_user_id)] = PortalMembership::MANAGER;
                    }
                    $projects[$pid] = [$userRoles, $value === null ? [] : PortalMembership::orgRoles($value, true)];
                }
                [$userRoles, $orgRoles] = $projects[$pid];
                $madeAt = $issue->created_at ? strtotime(strval($issue->created_at)) : false;
                $leaks = static::leaks($byIssue[intval($issue->id)], PortalMembership::wantedShares($issue), $userRoles, $orgRoles, $madeAt === false ? null : $madeAt);
                foreach ($leaks as $s) {
                    $out['found'][] = $s + ['issue' => intval($issue->id), 'key' => PortalDefinition::issueKeyOf($issue), 'project' => $pid];
                }
            }
        }
        if ($apply && !empty($out['found'])) {
            $ids = array_column($out['found'], 'id');
            DB::transaction(function () use ($ids, &$out) {
                foreach (array_chunk($ids, 500) as $chunk) {
                    $out['removed'] += CustomValueAuthoritable::whereIn('id', $chunk)->delete();
                }
            });
        }
        Log::info('ExmentPM schedule shares repair' . ($apply ? '' : ' (dry run)') . ': issues ' . $out['issues']
            . ', found ' . count($out['found']) . ', removed ' . $out['removed']);

        return $out;
    }
}
