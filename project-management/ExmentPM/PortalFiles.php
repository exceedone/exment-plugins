<?php

namespace App\Plugins\ExmentPM;

use Exceedone\Exment\Model\CustomValue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The Files tab with Exment's file server (ISS02825): one workspace per project.
 *
 * - A feature of Feature management (FEATURE, off until an administrator turns it on), which
 *   needs the file server itself (Exment 7.2.0 and later, the feature "file_server"). Without
 *   it the Files tab stays the list of the attachments, as before.
 * - The project's manager makes a workspace for the project (when the site lets them make
 *   workspaces) or picks one they manage. The link is a row of pm_portal_file_workspaces.
 * - The top permissions of the workspace follow the project: the manager manages, the members
 *   (users and organizations) edit, the viewers view. They are set again when the project's
 *   people change. A folder given its own permissions in the file server keeps them.
 *
 * Exment's classes are named by strings: the plugin runs on an Exment without the file server.
 */
class PortalFiles
{
    const FEATURE = 'exment_pm.files';

    const TABLE = 'pm_portal_file_workspaces';

    protected const FEATURES = 'Exceedone\Exment\Services\Features';
    protected const LIBRARY = 'Exceedone\Exment\Services\FileServer\Library';
    protected const ACCESS = 'Exceedone\Exment\Services\FileServer\Access';
    protected const WORKSPACE = 'Exceedone\Exment\Model\FileWorkspace';
    protected const PERMISSION = 'Exceedone\Exment\Model\FilePermission';

    /** The levels of the file server (Exceedone\Exment\Services\FileServer\Level). */
    const VIEW = 1;
    const EDIT = 3;
    const MANAGE = 4;

    /** @var bool|null */
    protected static $exists = null;

    /** Whether this Exment has the file server at all (and the plugin's table is there). */
    public static function exists(): bool
    {
        if (static::$exists === null) {
            static::$exists = class_exists(static::LIBRARY) && class_exists(static::WORKSPACE) && \Schema::hasTable(static::TABLE);
        }
        return static::$exists;
    }

    /** Feature management: the feature of the Files tab (registered on every request of the plugin). */
    public static function registerFeature(): void
    {
        if (!class_exists(static::FEATURES) || !class_exists(static::LIBRARY)) {
            return;
        }
        try {
            call_user_func([static::FEATURES, 'register'], static::FEATURE, [
                'label' => strval(PortalDefinition::trans('fileserver.feature')),
                'description' => strval(PortalDefinition::trans('fileserver.feature_help')),
                'requires' => 'file_server',
                'who' => false,
                'manual' => 'file_server',
            ]);
        } catch (\Throwable $e) {
            Log::warning('ExmentPM could not register the Files feature: ' . $e->getMessage());
        }
    }

    /** Whether the Files tab uses the file server for the login user. */
    public static function enabled(): bool
    {
        if (!static::exists()) {
            return false;
        }
        if (!boolval(call_user_func([static::LIBRARY, 'featureEnabled']))) {
            return false;
        }
        // off until an administrator turns it on: without Feature management, never
        if (!class_exists(static::FEATURES)) {
            return false;
        }
        return boolval(call_user_func([static::FEATURES, 'allowed'], static::FEATURE));
    }

    /**
     * The workspace of a project, or null (none linked, or it was deleted).
     *
     * @param CustomValue|int $project
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public static function workspaceOf($project)
    {
        if (!static::exists()) {
            return null;
        }
        $projectId = $project instanceof CustomValue ? intval($project->id) : intval($project);
        $workspaceId = DB::table(static::TABLE)->where('project_id', $projectId)->value('workspace_id');
        if (!$workspaceId) {
            return null;
        }
        $class = static::WORKSPACE;
        return $class::query()->find($workspaceId);
    }

    /** The project a workspace is linked to (its id), or null. */
    public static function projectOf(int $workspaceId): ?int
    {
        if (!static::exists()) {
            return null;
        }
        $id = DB::table(static::TABLE)->where('workspace_id', $workspaceId)->value('project_id');
        return $id ? intval($id) : null;
    }

    /**
     * What the screens need: whether the tab uses the file server, the workspace and its
     * address, and for the project settings whether one may be made or picked.
     *
     * @param CustomValue $project
     * @param bool $forSettings with the workspaces that may be picked
     * @return array<string, mixed>
     */
    public static function state(CustomValue $project, bool $forSettings = false): array
    {
        if (!static::enabled()) {
            return ['on' => false];
        }
        $workspace = static::workspaceOf($project);
        $access = static::access();
        $state = [
            'on' => true,
            'workspace' => $workspace ? [
                'id' => intval($workspace->id),
                'name' => strval($workspace->name),
                'url' => static::workspaceUrl($workspace),
                'can_open' => $access !== null && intval($access->level($workspace)) >= static::VIEW,
            ] : null,
        ];
        if ($forSettings) {
            $state['can_create'] = $access !== null && boolval($access->canCreateWorkspace());
            $state['pickable'] = static::pickable($project);
        }
        return $state;
    }

    /**
     * The workspaces the login user manages that no other project uses.
     *
     * @return array<int, array{id: int, name: string}>
     */
    public static function pickable(CustomValue $project): array
    {
        $access = static::access();
        if ($access === null) {
            return [];
        }
        $taken = DB::table(static::TABLE)->where('project_id', '<>', intval($project->id))->pluck('workspace_id')->map('intval')->all();
        $out = [];
        foreach ($access->workspaces() as $workspace) {
            if (in_array(intval($workspace->id), $taken, true) || intval($access->level($workspace)) < static::MANAGE) {
                continue;
            }
            $out[] = ['id' => intval($workspace->id), 'name' => strval($workspace->name)];
        }
        return $out;
    }

    /**
     * Make a workspace for the project, named after it, and link it.
     *
     * @return \Illuminate\Database\Eloquent\Model the workspace
     */
    public static function create(CustomValue $project)
    {
        $access = static::access();
        if ($access === null || !$access->canCreateWorkspace()) {
            throw new \RuntimeException(strval(PortalDefinition::trans('fileserver.cannot_create')));
        }
        $class = static::LIBRARY;
        $library = new $class($access);
        $name = static::projectName($project);
        $workspace = $library->createWorkspace([
            'name' => $name,
            'description' => strval(PortalDefinition::trans('fileserver.workspace_description', ['project' => $name])),
        ]);
        static::link($project, $workspace);
        return $workspace;
    }

    /**
     * Link a workspace the login user manages to the project.
     *
     * @return \Illuminate\Database\Eloquent\Model the workspace
     */
    public static function pick(CustomValue $project, int $workspaceId)
    {
        $allowed = array_column(static::pickable($project), 'id');
        if (!in_array($workspaceId, $allowed, true)) {
            throw new \RuntimeException(strval(PortalDefinition::trans('fileserver.cannot_pick')));
        }
        $class = static::WORKSPACE;
        $workspace = $class::query()->findOrFail($workspaceId);
        static::link($project, $workspace);
        return $workspace;
    }

    /** Unlink the workspace (it stays, with its files and permissions). */
    public static function unlink(CustomValue $project): void
    {
        if (static::exists()) {
            DB::table(static::TABLE)->where('project_id', intval($project->id))->delete();
        }
    }

    /**
     * @param \Illuminate\Database\Eloquent\Model $workspace
     */
    protected static function link(CustomValue $project, $workspace): void
    {
        $now = now();
        DB::table(static::TABLE)->updateOrInsert(
            ['project_id' => intval($project->id)],
            ['workspace_id' => intval($workspace->id), 'updated_at' => $now, 'created_at' => $now, 'created_user_id' => \Exment::getUserId(), 'updated_user_id' => \Exment::getUserId()]
        );
        static::syncPermissions($project);
    }

    /**
     * The top permissions of the workspace from the project's people: the manager manages,
     * the members and the member organizations edit, the viewers and the viewer organizations
     * view (the higher level when one is in two). A project with no manager keeps the
     * workspace's managers, so that nobody is locked out.
     *
     * @return array<int, array{target_type: string, target_id: int, level: int}>
     */
    public static function entriesOf(CustomValue $project): array
    {
        $def = PortalDefinition::config();
        $value = (array) $project->value;
        $ids = function (string $role) use ($def, $value): array {
            $column = strval(array_get($def, 'project.' . $role, $role));
            $raw = array_get($value, $column);
            $list = is_array($raw) ? $raw : (is_nullorempty($raw) ? [] : [$raw]);
            return array_values(array_filter(array_map('intval', $list), function ($id) {
                return $id > 0;
            }));
        };
        $rows = [];
        $put = function (string $type, int $id, int $level) use (&$rows) {
            $key = $type . ':' . $id;
            if (!isset($rows[$key]) || $rows[$key]['level'] < $level) {
                $rows[$key] = ['target_type' => $type, 'target_id' => $id, 'level' => $level];
            }
        };
        foreach ($ids('viewers') as $id) {
            $put('user', $id, static::VIEW);
        }
        foreach ($ids('viewer_orgs') as $id) {
            $put('organization', $id, static::VIEW);
        }
        foreach ($ids('members') as $id) {
            $put('user', $id, static::EDIT);
        }
        foreach ($ids('member_orgs') as $id) {
            $put('organization', $id, static::EDIT);
        }
        foreach ($ids('manager') as $id) {
            $put('user', $id, static::MANAGE);
        }
        return array_values($rows);
    }

    /**
     * Set the top permissions of the project's workspace from its people (see entriesOf).
     * Called when a workspace is linked and after a project is saved. A failure is logged:
     * the project is saved by then, and the next save tries again.
     */
    public static function syncPermissions(CustomValue $project): bool
    {
        $workspace = static::workspaceOf($project);
        if (!isset($workspace)) {
            return false;
        }
        try {
            $entries = static::entriesOf($project);
            if (!collect($entries)->contains('level', static::MANAGE)) {
                $permission = static::PERMISSION;
                foreach ($permission::query()->where('workspace_id', $workspace->id)->whereNull('node_id')->where('level', static::MANAGE)->get() as $row) {
                    $entries[] = ['target_type' => strval($row->target_type), 'target_id' => intval($row->target_id), 'level' => static::MANAGE];
                }
            }
            if (!collect($entries)->contains('level', static::MANAGE)) {
                return false;
            }
            // the plugin sets them, whoever saved the project: as the system
            $accessClass = static::ACCESS;
            $libraryClass = static::LIBRARY;
            $library = new $libraryClass(new $accessClass(\Exment::getUserId() !== null ? intval(\Exment::getUserId()) : null, true));
            $library->setPermissions($workspace, null, $entries);
            return true;
        } catch (\Throwable $e) {
            Log::error('ExmentPM could not set the permissions of the workspace of project ' . $project->id . ': ' . $e->getMessage());
            return false;
        }
    }

    /**
     * The address of a workspace in the file server.
     *
     * @param \Illuminate\Database\Eloquent\Model $workspace
     */
    public static function workspaceUrl($workspace): string
    {
        return admin_urls('file_server', $workspace->id);
    }

    /**
     * The file server's access of the login user, or null.
     *
     * @return mixed
     */
    protected static function access()
    {
        if (!static::exists()) {
            return null;
        }
        $access = call_user_func([static::ACCESS, 'current']);
        return $access->userId() !== null ? $access : null;
    }

    protected static function projectName(CustomValue $project): string
    {
        $def = PortalDefinition::config();
        $name = trim(strval(array_get((array) $project->value, strval(array_get($def, 'project.name', 'name')))));
        $key = trim(strval(array_get((array) $project->value, strval(array_get($def, 'project.key', 'project_code')))));
        $name = $name !== '' ? $name : $key;
        return mb_substr($name !== '' ? $name : ('project ' . $project->id), 0, 200);
    }
}
