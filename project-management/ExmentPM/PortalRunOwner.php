<?php

namespace App\Plugins\ExmentPM;

use Exceedone\Exment\Enums\Permission;
use Exceedone\Exment\Enums\SystemTableName;
use Exceedone\Exment\Model\CustomTable;
use Exceedone\Exment\Model\CustomValue;
use Exceedone\Exment\Model\CustomValueModelScope;
use Exceedone\Exment\Model\System;
use Exceedone\Exment\Services\AuthUserOrgHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Whom an issue the plugin makes by itself is made as: a recurring schedule's issue (the user who
 * made the schedule) or the copy of a recurring issue (the user who made the first one). When
 * that user is gone, or is no longer a manager or a member of the project, the project's first
 * manager who is still there. Never the visitor whose page load happened to run it.
 *
 * Exment is not switched to that user: Exment keeps the permissions it read for the request's
 * user for the rest of the request (System::requestSession), so a user put in mid-request would
 * be judged by the visitor's rights, and the visitor by theirs afterwards. The issue is made the
 * way the batch makes it instead, with nobody asking: the portal's rules do not ask the visitor
 * (PortalRules::onBehalf), canWrite() asks about the owner, the owner is written as the creator
 * (ModelBase::saving_users off), the history names the owner, and no creator's share is given to
 * the visitor (PortalRunShares).
 */
class PortalRunOwner
{
    /** Nobody to make it as: the user is gone, and the project has no manager. */
    const NO_OWNER = 'no_owner';

    /** The owner may not add issues: no role that edits the issue table. */
    const NO_RIGHT = 'no_right';

    /**
     * Whom to make it as.
     *
     * @param ?int $preferred the user who made the schedule (or the first issue)
     * @param array<int, string> $roles user id => role on the project (PortalMembership::roles)
     * @param array<int, int> $managers the project's managers, in their order
     * @param ?int $projectCreator who made the project, for a project that names nobody
     * @param callable $exists fn(int $userId): bool, whether the user is still there
     */
    public static function choose(?int $preferred, array $roles, array $managers, ?int $projectCreator, callable $exists): ?int
    {
        $named = !empty($roles);
        if ($preferred !== null && $preferred > 0 && $exists($preferred)
            && (!$named || in_array($roles[$preferred] ?? null, PortalMembership::EDITORS, true))) {
            return $preferred;
        }
        foreach ($managers as $id) {
            $id = intval($id);
            if ($id > 0 && $exists($id)) {
                return $id;
            }
        }
        if (!$named && $projectCreator !== null && $projectCreator > 0 && $exists($projectCreator)) {
            return $projectCreator;
        }

        return null;
    }

    /**
     * The owner of a run in a project, or null.
     *
     * @param ?int $preferred
     */
    public static function resolve(CustomValue $project, ?int $preferred): ?int
    {
        $value = (array) $project->value;
        $managerColumn = array_get(PortalDefinition::config(), 'project.manager');
        $managers = is_nullorempty($managerColumn) ? [] : PortalMembership::ids(array_get($value, $managerColumn));
        $users = CustomTable::getEloquent(SystemTableName::USER);
        $exists = function (int $id) use ($users) {
            return isset($users) && $users->getValueModel()->query()->withoutGlobalScope(CustomValueModelScope::class)->whereKey($id)->exists();
        };
        $creator = intval($project->created_user_id);

        return static::choose($preferred, PortalMembership::roles($value), $managers, $creator > 0 ? $creator : null, $exists);
    }

    /**
     * Why the run cannot be made as anyone in this project (NO_OWNER, NO_RIGHT), or null with the
     * owner in $owner.
     */
    public static function check(CustomValue $project, ?int $preferred, CustomTable $issueTable, ?int &$owner = null): ?string
    {
        $owner = static::resolve($project, $preferred);
        if ($owner === null) {
            return self::NO_OWNER;
        }

        return static::canWrite($owner, $issueTable) ? null : self::NO_RIGHT;
    }

    /**
     * Whether the user may add issues: a system administrator, or a role that edits the issue
     * table (Exment's own rule for who may add a row).
     */
    public static function canWrite(int $userId, CustomTable $issueTable): bool
    {
        try {
            if (in_array($userId, array_map('intval', (array) System::system_admin_users()), true)) {
                return true;
            }
            $query = AuthUserOrgHelper::getRoleUserAndOrgBelongsUserQueryTable($issueTable, Permission::AVAILABLE_EDIT_CUSTOM_VALUE);

            return isset($query) && $query->withoutGlobalScope(CustomValueModelScope::class)->whereKey($userId)->exists();
        } catch (\Throwable $e) {
            Log::warning('ExmentPM: could not tell whether user ' . $userId . ' may add issues: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Save a new issue as made by $owner, whoever's request this is: the portal's rules do not
     * ask the visitor, the owner is its creator and last editor, the history of the save names
     * the owner, and no creator's share comes with it (PortalRunShares::saveNew).
     */
    public static function saveAs(CustomValue $row, int $owner): void
    {
        $row->saving_users = false;
        $row->created_user_id = $owner;
        $row->updated_user_id = $owner;
        PortalRules::onBehalf($row, function () use ($row) {
            PortalRunShares::saveNew($row);
        });
        $visitor = intval(\Exment::getUserId());
        if ($visitor > 0 && $visitor !== $owner && !is_nullorempty($row->id)) {
            try {
                // the history of a row made just now: all of it is this save's
                DB::table('revisions')->where('revisionable_type', $row->getMorphClass())->where('revisionable_id', $row->id)
                    ->update(['create_user_id' => $owner]);
            } catch (\Throwable $e) {
                Log::warning('ExmentPM: the history of issue ' . $row->id . ' still names the visitor: ' . $e->getMessage());
            }
        }
    }
}
