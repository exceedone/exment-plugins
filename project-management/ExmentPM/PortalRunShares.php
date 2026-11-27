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
 * its project. So a run saves its issue with saveNew(), which keeps exactly that share off: the
 * issue gets the shares its share settings give (its assignees and the like) and whatever other
 * plugins add, the same as when the batch runs with nobody logged in. The project's members
 * reach the issue through the project, as for every issue.
 *
 * repair() takes back the creator's shares that runs gave before this, on the issues the
 * schedules made and on the copies of the recurring issues:
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
     * The rows saveNew() is saving, by object id: the row, the user of the request and their
     * organizations (null: not known).
     *
     * @var array<int, array{0: CustomValue, 1: int, 2: ?array<int, int>}>
     */
    protected static $saving = [];

    /**
     * Which event dispatchers already have the listener, so it goes on once per process.
     *
     * @var array<int, bool>
     */
    protected static $listening = [];

    /**
     * Save a new row the plugin makes by itself, without the creator's share Exment would give
     * whoever's request it is: that share is not written, and one that got in all the same
     * (another Exment version) is taken off right after. Nothing changes with nobody logged in.
     */
    public static function saveNew(CustomValue $row): void
    {
        $userId = intval(\Exment::getUserId());
        if ($userId <= 0) {
            $row->save();

            return;
        }
        static::listen();
        $key = spl_object_id($row);
        static::$saving[$key] = [$row, $userId, static::orgsOf()];
        try {
            $row->save();
        } finally {
            $orgs = static::$saving[$key][2];
            unset(static::$saving[$key]);
        }
        // a failure here must not undo the save: the issue is there, and the repair finds the rest
        try {
            static::prune($row, $userId, $orgs);
        } catch (\Throwable $e) {
            Log::warning('ExmentPM: the shares of the new issue ' . $row->id . ' could not be checked: ' . $e->getMessage());
        }
    }

    /**
     * Whether the share settings give a share ($wanted, PortalMembership::wantedShares): an edit
     * share only where a setting gives edit.
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
     * Whether a share written in a request is the creator's share Exment gives its user
     * (CustomValueAuthoritable::setValueAuthoritable): on the user themself, or on one of their
     * organizations and written by them. A share another plugin writes on someone else is not one.
     *
     * @param ?int $by the share's created_user_id
     * @param ?array<int, int> $orgs the request user's organizations (null: not known, then any
     *                               organization the user writes a share on counts)
     */
    public static function creatorShare(string $type, int $target, ?int $by, int $user, ?array $orgs): bool
    {
        if ($user <= 0) {
            return false;
        }
        if ($type === self::USER) {
            return $target === $user;
        }
        if ($type === self::ORG) {
            return $by === $user && ($orgs === null || in_array($target, $orgs, true));
        }

        return false;
    }

    /**
     * Whether saveNew() keeps a share off its row: a creator's share the settings do not give.
     *
     * @param array<string, bool> $wanted
     * @param ?array<int, int> $orgs
     */
    public static function vetoed(array $wanted, string $permission, string $type, int $target, ?int $by, int $user, ?array $orgs): bool
    {
        return static::creatorShare($type, $target, $by, $user, $orgs)
            && !static::allowed($wanted, $permission, $type, $target);
    }

    /**
     * The organizations of the user logged in, as Exment reads them for the creator's share.
     *
     * @return ?array<int, int> null: not known
     */
    protected static function orgsOf(): ?array
    {
        try {
            $user = \Exment::user();
            $base = isset($user) ? $user->base_user : null;
            if (!isset($base)) {
                return null;
            }
            $ids = [];
            foreach ($base->belong_organizations ?? [] as $org) {
                $ids[] = intval($org->id);
            }

            return $ids;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Vetoes the creator's share Exment writes during saveNew(). Eloquent stops a create whose
     * "creating" listener answers false; Exment writes that share with firstOrCreate, so it is
     * never written. The shares of the settings and of other plugins go on as before.
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
            foreach (static::$saving as [$row, $user, $orgs]) {
                if (!static::isOn($share, $row)) {
                    continue;
                }
                $by = isset($share->created_user_id) ? intval($share->created_user_id) : $user;
                if (static::vetoed(PortalMembership::wantedShares($row), strval($share->authoritable_type), strval($share->authoritable_user_org_type), intval($share->authoritable_target_id), $by, $user, $orgs)) {
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
     * Take off a creator's share that got onto a row saveNew() just saved all the same: only the
     * rows the request's user wrote, and only the creator's share among them.
     *
     * @param ?array<int, int> $orgs
     * @return int the shares taken off
     */
    protected static function prune(CustomValue $row, int $user, ?array $orgs): int
    {
        $table = $row->custom_table;
        if (!isset($table) || is_nullorempty($row->id)) {
            return 0;
        }
        $wanted = PortalMembership::wantedShares($row);
        $ids = [];
        foreach (CustomValueAuthoritable::where('parent_type', $table->table_name)->where('parent_id', $row->id)->where('created_user_id', $user)->get() as $share) {
            if (static::vetoed($wanted, strval($share->authoritable_type), strval($share->authoritable_user_org_type), intval($share->authoritable_target_id), $user, $user, $orgs)) {
                $ids[] = intval($share->id);
            }
        }
        if (!empty($ids)) {
            CustomValueAuthoritable::whereIn('id', $ids)->delete();
        }

        return count($ids);
    }

    /**
     * The creator's shares on one issue a run made that its project does not justify.
     *
     * A share is one when all of these hold:
     *   - it is on a user or an organization, and the issue's share settings do not give it (an
     *     assignee's share stays),
     *   - it was written within WINDOW seconds of the issue itself (a share given by hand later
     *     stays),
     *   - for a user: an edit share the user wrote on themself (the creator's share; a share
     *     somebody else gave them by hand stays), and the user is not a manager or a member of
     *     the project (a viewer keeps a view share),
     *   - for an organization: written by a user whose creator's share is beside it (Exment writes
     *     the organizations' with the user's, never alone), and not one of the project's member
     *     organizations (a viewer organization keeps a view share).
     * A project that names nobody does not use membership, and none of its issues' shares are one.
     *
     * @param array<int, array{id: int, type: string, target: int, permission: string, at: ?int, by: ?int}> $shares
     * @param array<string, bool> $wanted PortalMembership::wantedShares of the issue
     * @param array<int, string> $userRoles user id => role on the project (PortalMembership::roles)
     * @param array<int, string> $orgRoles organization id => role (PortalMembership::orgRoles)
     * @param ?int $madeAt when the issue was made (a Unix time)
     * @return array<int, array{id: int, type: string, target: int, permission: string, at: ?int, by: ?int}>
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
        $inSave = array_values(array_filter($shares, function ($s) use ($wanted, $madeAt) {
            return in_array($s['type'], [self::USER, self::ORG], true)
                && !static::allowed($wanted, $s['permission'], $s['type'], $s['target'])
                && $s['at'] !== null && abs($s['at'] - $madeAt) <= self::WINDOW;
        }));
        // the users whose creator's share is on the issue: an edit share they wrote on themselves
        $creators = [];
        foreach ($inSave as $s) {
            if ($s['type'] === self::USER && $s['permission'] === self::EDIT && ($s['by'] ?? null) === $s['target']) {
                $creators[$s['target']] = true;
            }
        }
        $out = [];
        foreach ($inSave as $s) {
            if ($s['type'] === self::USER) {
                if (isset($creators[$s['target']]) && $s['permission'] === self::EDIT && !$justified($userRoles[$s['target']] ?? null, $s['permission'])) {
                    $out[] = $s;
                }
                continue;
            }
            if (isset($s['by'], $creators[$s['by']]) && !$justified($orgRoles[$s['target']] ?? null, $s['permission'])) {
                $out[] = $s;
            }
        }

        return $out;
    }

    /**
     * The issues a run made: the ones the schedules made (pm_portal_schedule_items), and the
     * ones with a user's share written by that user while the issue is recorded as someone
     * else's, which is what a copy of a recurring issue made in a visitor's request looks like
     * (the copy is recorded as its first issue's creator's).
     *
     * @return array<int, int> issue ids
     */
    protected static function madeByRuns(CustomTable $issueTable): array
    {
        $ids = [];
        if (\Schema::hasTable(PortalSchedule::ITEMS)) {
            foreach (DB::table(PortalSchedule::ITEMS)->where('issue_id', '>', 0)->distinct()->pluck('issue_id') as $id) {
                $ids[intval($id)] = true;
            }
        }
        $shares = CustomValueAuthoritable::getTableName();
        $rows = DB::table($shares . ' as a')
            ->join(getDBTableName($issueTable) . ' as i', 'i.id', '=', 'a.parent_id')
            ->where('a.parent_type', $issueTable->table_name)
            ->where('a.authoritable_user_org_type', self::USER)
            ->whereColumn('a.created_user_id', 'a.authoritable_target_id')
            ->where(function ($q) {
                $q->whereNull('i.created_user_id')->orWhereColumn('i.created_user_id', '<>', 'a.authoritable_target_id');
            })
            ->distinct()->pluck('a.parent_id');
        foreach ($rows as $id) {
            $ids[intval($id)] = true;
        }
        $ids = array_keys($ids);
        sort($ids);

        return $ids;
    }

    /**
     * Find (and with $apply, remove) the creator's shares that runs gave on the issues they made
     * (madeByRuns), by leaks(). Safe to run again: what it removed is gone, and nothing else
     * matches.
     *
     * @return array{issues: int, found: array<int, array<string, mixed>>, removed: int}
     */
    public static function repair(bool $apply): array
    {
        $out = ['issues' => 0, 'found' => [], 'removed' => 0];
        $def = PortalDefinition::config();
        $issueTable = CustomTable::getEloquent(array_get($def, 'issue_table'));
        $projectTable = CustomTable::getEloquent(array_get($def, 'project_table'));
        if (!isset($issueTable, $projectTable)) {
            return $out;
        }
        $projects = [];
        foreach (array_chunk(static::madeByRuns($issueTable), 200) as $chunk) {
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
                    'by' => isset($share->created_user_id) ? intval($share->created_user_id) : null,
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
