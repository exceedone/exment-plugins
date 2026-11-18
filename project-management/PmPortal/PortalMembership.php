<?php

namespace App\Plugins\PmPortal;

use Exceedone\Exment\Enums\Permission;
use Exceedone\Exment\Enums\RelationType;
use Exceedone\Exment\Enums\SystemTableName;
use Exceedone\Exment\Model\CustomColumn;
use Exceedone\Exment\Model\CustomRelation;
use Exceedone\Exment\Model\CustomTable;
use Exceedone\Exment\Model\CustomValue;
use Exceedone\Exment\Model\CustomValueAuthoritable;
use Exceedone\Exment\Model\CustomValueModelScope;
use Exceedone\Exment\Model\System;
use Exceedone\Exment\Services\AuthUserOrgHelper;
use Illuminate\Support\Facades\DB;

/**
 * What being named on a project means for the shares Exment keeps.
 *
 * The manager / members / viewers columns are share columns: saving a project
 * hands everyone they name a custom_value share on the project row, and the
 * project's issues and wiki pages inherit it. Exment only ever adds, though.
 * Take a name off the project and the share it was given stays behind, along
 * with the shares that person picked up on the rows under the project - the
 * issues they opened, the ones they were assigned, the wiki pages they wrote.
 * The person disappears from the project screen and keeps every door open.
 *
 * Exment's own answer, the share_setting_sync table option, does not fit.
 * It deletes every share on the row that the columns do not name, so it also
 * takes the creator's own share and anything set through the share dialog,
 * and it never looks at the rows under the project. So the portal works from
 * the change itself instead: whoever a save takes off the project loses what
 * they held on it and under it, whoever it moves from a writing role to a
 * reading one loses the right to write, and whoever it gives a writing role
 * gets back what Exment would have given them for the rows they opened or
 * were assigned. Nobody else's shares are touched.
 *
 * Exment also re-grants on its own: every save of an issue gives each of its
 * assignees edit rights on it again. An issue still assigned to someone who
 * has left would hand that person the issue back the next time anyone saved
 * it, so issueSaved() takes that grant back as soon as Exment makes it.
 */
class PortalMembership
{
    const MANAGER = 'manager';
    const MEMBER = 'member';
    const VIEWER = 'viewer';

    /** The roles whose share columns grant custom_value_edit. */
    const EDITORS = [self::MANAGER, self::MEMBER];

    /**
     * Which event dispatchers already call issueSaved(), keyed by dispatcher
     * and model class, so the listener goes on once per process.
     *
     * @var array<string, bool>
     */
    protected static $watching = [];

    /**
     * readers() answers, keyed by "table:id": when each was worked out, and
     * the answer.
     *
     * @var array<string, array{0: float, 1: array<int, int>}>
     */
    protected static $readers = [];

    /**
     * How long a readers() answer is kept, in seconds. Long enough for every
     * bell one save sends; short enough that a queue worker, which lives
     * across many requests, does not answer from a membership that has since
     * changed.
     */
    const READERS_TTL = 10;

    /**
     * Who a project names, and in which role. A user named in two columns
     * keeps the stronger role.
     *
     * @param array<string, mixed>|null $value a project row's value
     * @return array<int, string> user id => role
     */
    public static function roles(?array $value): array
    {
        $pj = (array) array_get(PortalDefinition::config(), 'project', []);
        $roles = [];
        foreach ([['viewers', self::VIEWER], ['members', self::MEMBER], ['manager', self::MANAGER]] as [$key, $role]) {
            $column = array_get($pj, $key);
            if (is_nullorempty($column)) {
                continue;
            }
            foreach (static::ids(array_get((array) $value, $column)) as $id) {
                $roles[$id] = $role;
            }
        }

        return $roles;
    }

    /**
     * Two characters that tell people apart on an avatar without an image:
     * "Seiji Noro (EX1)" -> "SN", "Bui Thu Huong (TTM)" -> "BH",
     * "sysadmin" -> "SY", "野呂 誠司" / "野呂誠司" -> "野呂". A trailing
     * "(EX1)" style company tag is ignored. One letter was not enough to know
     * who an issue belongs to.
     */
    public static function initials(string $name): string
    {
        $s = trim(preg_replace('/[(（][^)）]*[)）]/u', '', $name) ?? '');
        if ($s === '') {
            $s = trim(preg_replace('/[()（）]/u', '', $name) ?? '');
        }
        if ($s === '') {
            return '?';
        }
        $words = preg_split('/[\s\x{3000}]+/u', $s, -1, PREG_SPLIT_NO_EMPTY) ?: [$s];
        if (preg_match('/^[\p{Latin}\p{N}]/u', $words[0])) {
            if (count($words) >= 2) {
                return mb_strtoupper(mb_substr($words[0], 0, 1) . mb_substr(end($words), 0, 1));
            }
            return mb_strtoupper(mb_substr($words[0], 0, 2));
        }

        return mb_substr($words[0], 0, 2);
    }

    /**
     * The avatar each of these users registered in Exment, by user id, as the
     * URL Exment itself shows (LoginUser::display_avatar, served through
     * Exment's file route, so a private S3 bucket still works). Users without
     * one are left out and the portal keeps drawing their initial. One query
     * for the whole set. ISS00254.
     *
     * @param array<int, mixed> $ids
     * @return array<string, string>
     */
    public static function avatars(array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (empty($ids)) {
            return [];
        }
        $out = [];
        try {
            $rows = \Exceedone\Exment\Model\LoginUser::whereIn('base_user_id', $ids)
                ->whereNotNull('avatar')
                ->where('avatar', '<>', '')
                ->get();
            foreach ($rows as $row) {
                $key = strval($row->base_user_id);
                if (isset($out[$key])) {
                    continue;
                }
                $url = strval($row->display_avatar);
                if ($url !== '') {
                    $out[$key] = $url;
                }
            }
        } catch (\Throwable $e) {
            // An avatar is decoration: never let it break the page.
            \Illuminate\Support\Facades\Log::warning('pm_portal could not read avatars: ' . $e->getMessage());
        }

        return $out;
    }

    /**
     * The user ids a user column holds, whatever shape it was stored in: one
     * id, a list of them, or a comma separated string.
     *
     * @param mixed $raw
     * @return array<int, int>
     */
    public static function ids($raw): array
    {
        if (is_string($raw)) {
            $raw = explode(',', $raw);
        }

        $ids = [];
        foreach ((array) $raw as $id) {
            if (is_numeric($id) && intval($id) > 0) {
                $ids[] = intval($id);
            }
        }
        $ids = array_values(array_unique($ids));
        sort($ids);

        return $ids;
    }

    /**
     * What one save did to the people on a project.
     *
     * removed: named before, named nowhere now.
     * demoted: could write before, may only read now.
     * editors: may write now, could not before - new to the project or moved
     *          up from reading.
     *
     * @param array<int, string> $before roles() of the stored row
     * @param array<int, string> $after roles() of the row as saved
     * @return array{removed: array<int, int>, demoted: array<int, int>, editors: array<int, int>}
     */
    public static function changes(array $before, array $after): array
    {
        $removed = array_values(array_map('intval', array_keys(array_diff_key($before, $after))));
        $demoted = [];
        $editors = [];
        foreach ($after as $id => $role) {
            $wrote = isset($before[$id]) && in_array($before[$id], self::EDITORS, true);
            $writes = in_array($role, self::EDITORS, true);
            if ($wrote && !$writes) {
                $demoted[] = intval($id);
            } elseif ($writes && !$wrote) {
                $editors[] = intval($id);
            }
        }

        return ['removed' => $removed, 'demoted' => $demoted, 'editors' => $editors];
    }

    /**
     * The value a row holds in the database, as opposed to the one about to
     * be written. During the saving and saved events the model still keeps
     * the stored value as its original.
     *
     * @return array<string, mixed>
     */
    public static function storedValue(CustomValue $row): array
    {
        $raw = $row->getOriginal('value');
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }

        return is_array($raw) ? $raw : [];
    }

    /**
     * Whether the signed-in user manages this project: may change its
     * settings, who is on it, and its Git and AI switches, and may delete it.
     *
     * Backlog keeps all of that with the project's administrator, and the
     * portal has to as well: the members column grants edit on the project
     * row, because Exment asks for an editable parent before anyone may file
     * an issue under it, so "may edit the row" says nothing about who runs
     * the project. Taking someone off a project also takes their access with
     * it, which in a member's hands would let them lock the manager out.
     *
     * The manager may, and so may whoever edits every project anyway. A
     * project that names no manager falls back to the user who created it,
     * so a project made without one is not left with nobody able to add the
     * first member. Without a signed-in user this is a batch or the schema
     * upgrade, which may.
     */
    public static function canManage(CustomValue $project): bool
    {
        if (\Exment::user() === null) {
            return true;
        }
        $table = $project->custom_table;
        if ($table && $table->hasPermission(Permission::AVAILABLE_ALL_EDIT_CUSTOM_VALUE)) {
            return true;
        }

        $me = intval(\Exment::getUserId());
        $column = array_get(PortalDefinition::config(), 'project.manager');
        $value = $project->exists ? static::storedValue($project) : (array) $project->value;
        $managers = is_nullorempty($column) ? [] : static::ids(array_get($value, $column));
        if (!empty($managers)) {
            return in_array($me, $managers, true);
        }

        return $me > 0 && $me === intval($project->created_user_id);
    }

    /**
     * The users who can read this row right now, by Exment's own rules:
     * whoever's role reads every row of the table, plus whoever holds a share
     * on the row - or, for a row that follows its project, on the project -
     * and has a role on the table that lets a share count at all.
     *
     * This is who a notice about the row may go to. A notice carries the
     * row's title and often a comment, so sending one to someone who could
     * not open the row hands them what the row was hiding: a member taken
     * off the project, a watcher whose access went with it, a user id typed
     * into a request.
     *
     * Remembered for READERS_TTL seconds; projectSaved() forgets every answer,
     * as that is where they change.
     *
     * @return array<int, int> user ids
     */
    public static function readers(CustomValue $row): array
    {
        $table = $row->custom_table;
        if (!$table || is_nullorempty($row->id)) {
            return [];
        }
        $key = $table->table_name . ':' . $row->id;
        $now = microtime(true);
        if (isset(static::$readers[$key]) && $now - static::$readers[$key][0] < self::READERS_TTL) {
            return static::$readers[$key][1];
        }

        // Every row: system administrators and every role that reads or
        // edits the whole table, organizations' members included.
        $ids = [];
        $all = AuthUserOrgHelper::getRoleUserAndOrgBelongsUserQueryTable($table, Permission::AVAILABLE_ALL_CUSTOM_VALUE);
        if ($all) {
            $ids = $all->withoutGlobalScope(CustomValueModelScope::class)->pluck('id')->all();
        }

        // Shared rows: the row's own shares, and the project's when the row
        // reads through it. A share only counts for a user whose role uses
        // this table at all, which is what Exment checks first.
        $holders = [[$table->table_name, $row->id]];
        if (!is_nullorempty($row->parent_type) && !is_nullorempty($row->parent_id)
            && boolval($table->getOption('inherit_parent_permission'))) {
            $holders[] = [$row->parent_type, $row->parent_id];
        }
        $shared = [];
        $orgs = [];
        foreach ($holders as [$type, $id]) {
            $shares = CustomValueAuthoritable::where('parent_type', $type)->where('parent_id', $id)->get();
            foreach ($shares as $share) {
                if ($share->authoritable_user_org_type === SystemTableName::USER) {
                    $shared[] = intval($share->authoritable_target_id);
                } elseif ($share->authoritable_user_org_type === SystemTableName::ORGANIZATION) {
                    $orgs[] = intval($share->authoritable_target_id);
                }
            }
        }
        if (!empty($orgs) && System::organization_available()) {
            $orgTable = CustomTable::getEloquent(SystemTableName::ORGANIZATION);
            $orgRows = $orgTable ? $orgTable->getValueModel()->newQueryWithoutScopes()->with('users')->whereIn('id', array_unique($orgs))->get() : collect();
            foreach ($orgRows as $org) {
                foreach ($org->users as $user) {
                    $shared[] = intval($user->getUserId());
                }
            }
        }
        if (!empty($shared)) {
            $usable = $table->getAccessibleUserIds()->map(function ($id) {
                return intval($id);
            })->all();
            $ids = array_merge($ids, array_intersect($shared, $usable));
        }

        $ids = array_values(array_unique(array_filter(array_map(function ($id) {
            return intval($id);
        }, $ids))));
        sort($ids);
        static::$readers[$key] = [$now, $ids];

        return $ids;
    }

    /**
     * The given users that can read the row, in the order given.
     *
     * @param iterable<mixed> $userIds
     * @return array<int, int>
     */
    public static function filterReaders(CustomValue $row, iterable $userIds): array
    {
        $readers = array_flip(static::readers($row));
        $out = [];
        foreach ($userIds as $id) {
            $id = intval($id);
            if ($id > 0 && isset($readers[$id]) && !in_array($id, $out, true)) {
                $out[] = $id;
            }
        }

        return $out;
    }

    /**
     * Bring the shares in line with a project save. Called from the saved
     * event, which Exment fires before it hands out the shares the columns
     * grant: a user still named keeps or gets their share on the project row
     * from Exment right after this, and a user no longer named is not given
     * one back.
     *
     * Fails loudly rather than quietly. The change is worked out from this
     * one save, so a revoke that did not happen now would never be retried.
     */
    public static function projectSaved(CustomValue $project): void
    {
        $changes = static::changes(
            static::roles(static::storedValue($project)),
            static::roles((array) $project->value)
        );
        if (empty($changes['removed']) && empty($changes['demoted']) && empty($changes['editors'])) {
            return;
        }

        static::$readers = [];
        DB::transaction(function () use ($project, $changes) {
            $owned = static::ownedRows($project);
            static::revoke($project, $owned, $changes['removed'], $changes['demoted']);
            static::restore($owned, $changes['editors']);
        });

        // Git tokens live on the Git server, so they are revoked once the
        // save is committed: a remote call cannot be rolled back with it, and
        // a slow one must not hold the rows locked. A demoted member loses
        // push as well; they may ask for a read-only token again. Best effort
        // - revokeForUsers() logs what it could not reach rather than failing
        // a save that is already written.
        $gone = array_values(array_unique(array_merge($changes['removed'], $changes['demoted'])));
        if (!empty($gone)) {
            $projectId = intval($project->id);
            DB::afterCommit(function () use ($projectId, $gone) {
                try {
                    GitKey::revokeForUsers($projectId, $gone);
                } catch (\Throwable $e) {
                    \Log::error('pm_portal could not revoke Git tokens on project ' . $projectId . ': ' . $e->getMessage(), [
                        'exception' => $e,
                    ]);
                }
            });
        }
    }

    /**
     * Make issueSaved() run after every save of this issue's model class.
     *
     * It has to run after Exment's own share step, and the plugin's saved
     * event runs before it. Eloquent's "saved" fires after both, so the
     * listener goes there. Registered from the plugin event rather than at
     * boot because a plugin has no boot hook; the first issue save of a
     * request registers it in time for its own "saved".
     */
    public static function watchIssue(CustomValue $issue): void
    {
        $dispatcher = $issue::getEventDispatcher();
        if (!$dispatcher) {
            return;
        }
        $class = get_class($issue);
        $key = spl_object_id($dispatcher) . '|' . $class;
        if (isset(static::$watching[$key])) {
            return;
        }
        static::$watching[$key] = true;

        $class::saved(function ($row) {
            if ($row instanceof CustomValue) {
                static::issueSaved($row);
            }
        });
    }

    /**
     * Take back the edit share Exment just gave an assignee who may not
     * write on the issue's project - someone taken off it, moved to the
     * viewers, or never on it. Their name stays on the issue; reassigning it
     * is the manager's call, not a side effect of somebody else's edit.
     *
     * A project that names nobody does not use membership at all, and is
     * left to Exment's default.
     */
    public static function issueSaved(CustomValue $issue): void
    {
        $def = PortalDefinition::config();
        if ($issue->parent_type !== array_get($def, 'project_table') || is_nullorempty($issue->parent_id)) {
            return;
        }
        $assignees = static::ids(array_get((array) $issue->value, array_get($def, 'issue.assignees')));
        if (empty($assignees)) {
            return;
        }

        $projectTable = CustomTable::getEloquent(array_get($def, 'project_table'));
        $project = $projectTable ? $projectTable->getValueModel()->newQueryWithoutScopes()->find($issue->parent_id) : null;
        $roles = $project ? static::roles((array) $project->value) : [];
        if (empty($roles)) {
            return;
        }

        $outsiders = array_values(array_filter($assignees, function ($id) use ($roles) {
            return !in_array($roles[$id] ?? null, self::EDITORS, true);
        }));
        if (empty($outsiders)) {
            return;
        }

        static::shares($issue->custom_table->table_name)
            ->where('parent_id', $issue->id)
            ->where('authoritable_type', Permission::CUSTOM_VALUE_EDIT)
            ->whereIn('authoritable_target_id', $outsiders)
            ->delete();
    }

    /**
     * The rows that belong to a project, as an id query per table: its
     * children through the parent relation (issues, wiki pages and the
     * project's own status and type lists), and the lists that name the
     * project in a column instead (milestones, categories). Deleted rows
     * count too - a row restored from the trash should come back the way the
     * people on the project are now, not the way they were.
     *
     * @return array<string, \Illuminate\Database\Eloquent\Builder> table name => id query
     */
    protected static function ownedRows(CustomValue $project): array
    {
        $def = PortalDefinition::config();
        $projectTable = $project->custom_table;
        $owned = [];

        foreach (CustomRelation::getRelationsByParent($projectTable, RelationType::ONE_TO_MANY) as $relation) {
            $child = CustomTable::getEloquent($relation->child_custom_table_id);
            if (!$child) {
                continue;
            }
            $owned[$child->table_name] = $child->getValueModel()->newQueryWithoutScopes()
                ->select('id')
                ->where('parent_type', $projectTable->table_name)
                ->where('parent_id', $project->id);
        }

        foreach (['milestone', 'category'] as $role) {
            $table = CustomTable::getEloquent(array_get($def, $role . '_table'));
            if (!$table || isset($owned[$table->table_name])) {
                continue;
            }
            $column = CustomColumn::getEloquent(array_get($def, $role . '.project'), $table);
            if (!$column) {
                continue;
            }
            $owned[$table->table_name] = $table->getValueModel()->newQueryWithoutScopes()
                ->select('id')
                ->where($column->getQueryKey(), strval($project->id));
        }

        return $owned;
    }

    /**
     * @param array<string, \Illuminate\Database\Eloquent\Builder> $owned
     * @param array<int, int> $removed every share goes, on the project and under it
     * @param array<int, int> $demoted only the edit shares go; reading comes from the project
     */
    protected static function revoke(CustomValue $project, array $owned, array $removed, array $demoted): void
    {
        $targets = [$project->custom_table->table_name => [$project->id]] + $owned;
        foreach ($targets as $tableName => $ids) {
            if (!empty($removed)) {
                static::shares($tableName)->whereIn('parent_id', $ids)
                    ->whereIn('authoritable_target_id', $removed)
                    ->delete();
            }
            if (!empty($demoted)) {
                static::shares($tableName)->whereIn('parent_id', $ids)
                    ->where('authoritable_type', Permission::CUSTOM_VALUE_EDIT)
                    ->whereIn('authoritable_target_id', $demoted)
                    ->delete();
            }
        }

        // Watching is how the comment notices keep arriving, and those carry
        // the issue's title and the comment itself.
        if (!empty($removed)) {
            Star::where('star_type', Star::TYPE_WATCH)
                ->whereIn('user_id', $removed)
                ->where(function ($query) use ($targets) {
                    foreach ($targets as $tableName => $ids) {
                        $query->orWhere(function ($one) use ($tableName, $ids) {
                            $one->where('target_type', $tableName)->whereIn('target_id', $ids);
                        });
                    }
                })
                ->delete();
        }
    }

    /**
     * Give a new writer the edit shares Exment would have given them on the
     * rows they opened and the issues they are assigned - what they had
     * before they were taken off, if they were.
     *
     * @param array<string, \Illuminate\Database\Eloquent\Builder> $owned
     * @param array<int, int> $editors
     */
    protected static function restore(array $owned, array $editors): void
    {
        if (empty($editors)) {
            return;
        }

        $def = PortalDefinition::config();
        $issueName = array_get($def, 'issue_table');
        $issueTable = CustomTable::getEloquent($issueName);
        $assignees = $issueTable ? CustomColumn::getEloquent(array_get($def, 'issue.assignees'), $issueTable) : null;

        foreach ($owned as $tableName => $ids) {
            foreach ($editors as $userId) {
                $rows = (clone $ids)->where('created_user_id', $userId)->pluck('id')->all();
                if ($tableName === $issueName && $assignees) {
                    $rows = array_merge($rows, (clone $ids)->whereInArrayString($assignees->getQueryKey(), $userId)->pluck('id')->all());
                }
                static::grantEdit($tableName, $rows, $userId);
            }
        }
    }

    /**
     * @param array<int, mixed> $rowIds
     */
    protected static function grantEdit(string $tableName, array $rowIds, int $userId): void
    {
        $rowIds = array_values(array_unique(array_map(function ($id) {
            return intval($id);
        }, $rowIds)));
        if (empty($rowIds)) {
            return;
        }

        $held = static::shares($tableName)
            ->whereIn('parent_id', $rowIds)
            ->where('authoritable_type', Permission::CUSTOM_VALUE_EDIT)
            ->where('authoritable_target_id', $userId)
            ->pluck('parent_id')
            ->map(function ($id) {
                return intval($id);
            })
            ->all();

        $now = now();
        $by = \Exment::getUserId();
        $insert = [];
        foreach (array_diff($rowIds, $held) as $rowId) {
            $insert[] = [
                'parent_id' => $rowId,
                'parent_type' => $tableName,
                'authoritable_type' => Permission::CUSTOM_VALUE_EDIT,
                'authoritable_user_org_type' => SystemTableName::USER,
                'authoritable_target_id' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
                'created_user_id' => $by,
                'updated_user_id' => $by,
            ];
        }
        foreach (array_chunk($insert, 500) as $chunk) {
            CustomValueAuthoritable::insert($chunk);
        }
    }

    /**
     * The user shares on rows of one table. Organization shares are never
     * the people columns' doing, so they are never this class's to remove.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected static function shares(string $tableName)
    {
        return CustomValueAuthoritable::where('parent_type', $tableName)
            ->where('authoritable_user_org_type', SystemTableName::USER);
    }
}
