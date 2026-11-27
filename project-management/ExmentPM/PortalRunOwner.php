<?php

namespace App\Plugins\ExmentPM;

use Exceedone\Exment\Enums\SystemTableName;
use Exceedone\Exment\Model\CustomTable;
use Exceedone\Exment\Model\CustomValue;
use Exceedone\Exment\Model\CustomValueModelScope;
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
 * (PortalRules::onBehalf, which also tells the portal's own notices whom the save is by),
 * canWrite() asks about the owner, the owner is written as the creator (ModelBase::saving_users
 * off), the history of the save names the owner, and no creator's share is given to the visitor
 * (PortalRunShares).
 *
 * What still names the visitor in a run of a page load (the batch has nobody to name): Exment's
 * own notices of a new row (its "updated by" and the sender label of a Teams notice), and a data
 * operation of the table that writes the login user into a column (LOGIN_USER). The batch path is
 * the normal one for that reason (PluginBatch, config.json batch_cron).
 */
class PortalRunOwner
{
    /** Nobody to make it as: the user is gone, and the project has no manager. */
    const NO_OWNER = 'no_owner';

    /** The owner may not add issues: no role that edits the issue table. */
    const NO_RIGHT = 'no_right';

    /**
     * canWrite() answers of this run, by table id: the users whose role edits it. A run reads
     * each table once; forget() starts a new run.
     *
     * @var array<int, array<int, bool>>
     */
    protected static $editors = [];

    /** A new run: the roles are read again. */
    public static function forget(): void
    {
        static::$editors = [];
    }

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
     * Whether the user may add issues: a role that edits the issue table (system administrators
     * included). A table every user may see gives nobody the right to add a row.
     */
    public static function canWrite(int $userId, CustomTable $issueTable): bool
    {
        $key = intval($issueTable->id);
        if (!isset(static::$editors[$key])) {
            try {
                static::$editors[$key] = array_fill_keys(PortalRoleUsers::editors($issueTable), true);
            } catch (\Throwable $e) {
                Log::warning('ExmentPM: could not read who may add issues: ' . $e->getMessage());

                return false;
            }
        }

        return isset(static::$editors[$key][$userId]);
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
        $visitor = intval(\Exment::getUserId());
        $from = now()->subSecond();
        PortalRules::onBehalf($row, function () use ($row) {
            PortalRunShares::saveNew($row);
        }, $owner);
        if (is_nullorempty($row->id)) {
            return;
        }
        try {
            // the history this save wrote: by the visitor (a page load) or by nobody (the batch)
            DB::table('revisions')->where('revisionable_type', $row->getMorphClass())->where('revisionable_id', $row->id)
                ->where('created_at', '>=', $from)
                ->where(function ($q) use ($visitor) {
                    $q->whereNull('create_user_id');
                    if ($visitor > 0) {
                        $q->orWhere('create_user_id', $visitor);
                    }
                })
                ->update(['create_user_id' => $owner]);
        } catch (\Throwable $e) {
            Log::warning('ExmentPM: the history of issue ' . $row->id . ' does not name its owner: ' . $e->getMessage());
        }
    }

    /** The name of a user, for a notice by them ('' when there is none). */
    public static function nameOf(int $userId): string
    {
        try {
            $users = CustomTable::getEloquent(SystemTableName::USER);
            $user = isset($users) ? $users->getValueModel()->query()->withoutGlobalScope(CustomValueModelScope::class)->find($userId) : null;

            return isset($user) ? strval($user->getLabel()) : '';
        } catch (\Throwable $e) {
            return '';
        }
    }
}
