<?php

namespace App\Plugins\ExmentPluginPM;

use Exceedone\Exment\Enums\SystemTableName;
use Exceedone\Exment\Model\CustomColumn;
use Exceedone\Exment\Model\CustomTable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * The project's own lifecycle: what the settings tab may do, the delete
 * that Backlog gates behind typing the key, and the Git repository request.
 *
 * Create and update are NOT here on purpose - they go through the plugin's
 * lightapi (POST/PATCH data/{project_table}), which already carries
 * Exment's permission checks, PortalRules validation and per-field 422s.
 * This trait only holds what that generic surface cannot express: the
 * cascade delete with its typed confirmation, the provisioning state, and
 * the flags the screens decide visibility by.
 *
 * Who may do what here:
 *  - what changes the project itself - asking for its repository, deleting
 *    it - is the manager's (PortalMembership::canManage);
 *  - a clone credential is personal: anybody who can open the project may
 *    hold read-only ones, and only those who may write on it (manager and
 *    members) may hold one that pushes;
 *  - a credential is revoked by whoever holds it, or by the manager.
 *
 * Refusals answer in the shape every portal screen reads: ok false, a
 * machine code in `error`, the Japanese sentence in `message`.
 */
trait PortalProjectTrait
{
    // ---- payload helpers -------------------------------------------------

    /**
     * What the current user may do to this project. Visibility only - every
     * endpoint checks again for itself.
     *
     * @param \Exceedone\Exment\Model\CustomValue $project
     * @return array<string, bool>
     */
    protected function projectCan($project): array
    {
        // The settings tab is the manager's, the way Backlog's project
        // settings are the project administrator's: a member can edit the
        // project row only because Exment asks for that before anyone files
        // an issue under it. PortalRules refuses the same write on the
        // server, whichever screen it comes from.
        $manage = $project->enableEdit() === true && PortalMembership::canManage($project);
        // Archived is Backlog's read-only. PortalRules refuses every issue
        // write in an archived project; the screen should not offer one.
        // The settings stay the manager's, so the project can be unarchived.
        $archived = $this->projectIsArchived($project);

        return [
            'manage' => $manage,
            'people' => $manage,
            'delete' => $project->enableDelete() === true,
            // Table level, and only a gate for showing the bulk controls at
            // all: a reader should not be offered a tick box that can only
            // ever be refused. Each row is still checked on its own when the
            // edit is actually applied.
            'issue_edit' => !$archived && isset($this->issue_table) && $this->issue_table->enableEdit() === true,
            // Every 課題の追加 entry, 複製 and 子課題の追加 hang off this, so
            // none of them opens a form whose save can only be refused.
            'issue_create' => !$archived && $this->childAddable($this->issue_table ?? null, $project),
        ];
    }

    /**
     * Whether a new row of $table may be filed under this project. The same
     * two questions the save endpoint asks (LightApiController::resolveParent):
     * may the user create rows of the table at all, and may they write under
     * the project - Exment's editable_with_parent, on unless the table turns
     * it off, which asks for an editable parent rather than a readable one.
     *
     * Pass the first answer in when asking about many projects at once.
     *
     * @param \Exceedone\Exment\Model\CustomTable|null $table
     * @param \Exceedone\Exment\Model\CustomValue $project
     */
    protected function childAddable($table, $project, ?bool $tableAllows = null): bool
    {
        if (!$table) {
            return false;
        }
        if ($tableAllows === null) {
            $tableAllows = $table->enableCreate(true) === true;
        }
        if (!$tableAllows) {
            return false;
        }

        return boolval($table->getOption('editable_with_parent') ?? 1)
            ? PortalDefinition::enableEdit($project, true) === true
            : $project->enableAccess() === true;
    }

    /**
     * Backlog's per-project switches, read off the columns schema v7 added.
     * An install that has not run the upgrade has none of them and gets the
     * defaults the schema would have seeded.
     *
     * @param \Exceedone\Exment\Model\CustomValue $project
     * @return array<string, mixed>
     */
    protected function projectFeatures($project): array
    {
        $defaults = ['use_wiki' => true, 'use_file' => true, 'use_gantt' => true, 'use_git' => false, 'subtasking' => true];

        $out = [];
        foreach ($defaults as $name => $default) {
            // "use_wiki" -> payload key "wiki"
            $key = str_replace('use_', '', $name);
            $out[$key] = $this->projectFlag($project, $name, $default);
        }

        $rule = null;
        if (CustomColumn::getEloquent('formatting_rule', $this->project_table) !== null) {
            $rule = strval(array_get($project->value, 'formatting_rule', '')) ?: 'markdown';
        }
        $out['formatting_rule'] = $rule;

        return $out;
    }

    /**
     * One yesno column as a bool, defaulting when the column does not exist
     * or the row has never been saved with it.
     *
     * @param \Exceedone\Exment\Model\CustomValue $project
     * @param string $column_name
     * @param bool $default
     * @return bool
     */
    protected function projectFlag($project, string $column_name, bool $default): bool
    {
        if (CustomColumn::getEloquent($column_name, $this->project_table) === null) {
            return $default;
        }
        $value = array_get($project->value, $column_name);

        return is_nullorempty($value) ? $default : boolval(intval($value));
    }

    /**
     * The values a project created outside a form has to be given.
     *
     * Exment applies a column's default while rendering a form, not while
     * saving a row - a create over the API lands with every unmentioned yesno
     * at 0, which for the feature switches means a brand new project without
     * a Wiki, a files tab or a chart. The add-project modal therefore sends
     * the same defaults the admin form would have shown, read off the columns
     * so a deployment that changed one is not second-guessed here.
     *
     * The person creating the project becomes its manager, the way Backlog
     * makes the creator the project's administrator. Exment gives the creator
     * a share on the new row either way; naming them puts that access where
     * the 参加ユーザー screen shows it, and gives the project someone who may
     * change who else is on it.
     *
     * @return array<string, string>
     */
    protected function projectCreateDefaults(): array
    {
        $pj = $this->def['project'];
        $asked = [array_get($pj, 'name'), array_get($pj, 'key'), 'parent_project'];

        $out = [];
        foreach ($this->project_table->custom_columns as $column) {
            if (in_array($column->column_name, $asked, true)) {
                continue;
            }
            $default = array_get($column->options, 'default');
            if (is_nullorempty($default)) {
                continue;
            }
            $out[$column->column_name] = strval($default);
        }

        $manager = array_get($pj, 'manager');
        $me = \Exment::getUserId();
        if (!is_nullorempty($manager) && !isset($out[$manager]) && !is_nullorempty($me)
            && CustomColumn::getEloquent($manager, $this->project_table) !== null) {
            $out[$manager] = strval($me);
        }

        return $out;
    }

    /**
     * Which project column plays which role in the settings screen, so the
     * client renders Backlog-shaped sections without hardcoding this
     * install's column names. A role whose column does not exist is null and
     * the section skips it.
     *
     * @return array<string, mixed>
     */
    protected function projectSettingsMap(): array
    {
        $pj = $this->def['project'];
        $has = function ($name) {
            return isset($name) && CustomColumn::getEloquent($name, $this->project_table) !== null ? $name : null;
        };

        $features = [];
        foreach (['use_wiki', 'use_file', 'use_gantt', 'use_git', 'subtasking'] as $name) {
            if ($has($name)) {
                $features[] = $name;
            }
        }

        // The AI switches get a pane of their own; listed here so the
        // "other columns" pane does not offer them a second time.
        $ai = [];
        foreach (['ai_enabled', 'ai_assign'] as $name) {
            if ($has($name)) {
                $ai[] = $name;
            }
        }

        return [
            'name' => $has(array_get($pj, 'name')),
            'key' => $has(array_get($pj, 'key')),
            'note' => $has(array_get($pj, 'note')),
            'status' => $has(array_get($pj, 'status')),
            'start' => $has(array_get($pj, 'start')),
            'end' => $has(array_get($pj, 'end')),
            'manager' => $has(array_get($pj, 'manager')),
            'members' => $has(array_get($pj, 'members')),
            'viewers' => $has(array_get($pj, 'viewers')),
            'member_orgs' => $has(array_get($pj, 'member_orgs')),
            'viewer_orgs' => $has(array_get($pj, 'viewer_orgs')),
            'parent' => $has('parent_project'),
            'archived' => $has('archived'),
            'formatting_rule' => $has('formatting_rule'),
            'features' => $features,
            'ai' => $ai,
        ];
    }

    /**
     * The Git block of the settings tab: the request's state plus whether a
     * real Git server is connected. Never the endpoint, never the token.
     *
     * @param \Exceedone\Exment\Model\CustomValue $project
     * @return array<string, mixed>
     */
    protected function gitState($project): array
    {
        $repo = GitRepo::ofProject($project->id);
        $key = strtoupper(strval(array_get($project->value, array_get($this->def['project'], 'key'), '')));

        return [
            'status' => isset($repo) ? strval($repo->status) : null,
            'repo_name' => isset($repo) ? strval($repo->repo_name) : GitRepo::suggestName($key, $project->id),
            'remote_url' => isset($repo) && !is_nullorempty($repo->remote_url) ? strval($repo->remote_url) : null,
            'error' => isset($repo) && !is_nullorempty($repo->last_error) ? $this->gitErrorText(strval($repo->last_error)) : null,
            'requested_at' => isset($repo) && isset($repo->created_at) ? $repo->created_at->format('Y-m-d H:i') : null,
            'http_url' => $this->gitHttpUrl($repo),
            'browse_url' => $this->gitBrowseUrl($repo),
            'provider' => GitProvisioner::provider(),
            'configured' => GitProvisioner::configured(),
        ];
    }

    /**
     * The stored reason a repository request failed, as the tab shows it.
     *
     * Since publicError() every reason written is a Japanese sentence. A row
     * written before that may still hold a raw exception text - English,
     * with the API host in it - and that is replaced, not shown.
     *
     * @param string $stored
     * @return string
     */
    protected function gitErrorText(string $stored): string
    {
        return preg_match('/[\x{3040}-\x{30FF}\x{4E00}-\x{9FFF}]/u', $stored) === 1
            ? $stored
            : strval(PortalDefinition::trans('git_repo.remote_unknown'));
    }

    /**
     * Where a person looks at this repository on the Git server.
     *
     * Not the clone URL. The clone URL the portal hands out has no owner
     * segment, because the web server splices the owner back in for git's
     * own three endpoints only - a browser asking for the same path gets
     * nothing. So the owner goes back in here.
     *
     * The HTTP clone base doubles as the Git server's web root, which is
     * exactly why the owner-less clone URL is "<web root>/<repo>" in the
     * first place.
     *
     * @param GitRepo|null $repo
     * @return string|null
     */
    protected function gitBrowseUrl($repo): ?string
    {
        if (!isset($repo) || $repo->status !== GitRepo::STATUS_READY) {
            return null;
        }

        $settings = GitProvisioner::settings();
        $base = rtrim(strval(array_get($settings, 'clone_base', '')), '/');
        $org = trim(strval(array_get($settings, 'namespace', '')));

        return $base === '' || $org === ''
            ? null
            : $base . '/' . $org . '/' . strval($repo->repo_name);
    }

    /**
     * The HTTP clone URL - the one that works on the port the web site is
     * already on, which for a customer behind a firewall is often the only
     * port there is.
     *
     * Deliberately the full path, owner segment and all, rather than the
     * shortened remote_url stored on the row. The short form only resolves
     * where a web server rule splices the owner back in; this one resolves
     * anywhere, including on a shared Git server, and it is the same address
     * a browser opens. One URL instead of two.
     *
     * @param GitRepo|null $repo
     * @return string|null
     */
    protected function gitHttpUrl($repo): ?string
    {
        $browse = $this->gitBrowseUrl($repo);

        return isset($browse) ? $browse . '.git' : null;
    }

    // ---- endpoints -------------------------------------------------------

    /**
     * GET {id}/git_repo - the current provisioning state, for the settings
     * tab's refresh after a request. Readable by anybody on the project:
     * whoever may hold a clone credential needs the address it opens.
     *
     * @param Request $request
     * @param mixed $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function gitRepo(Request $request, $id)
    {
        $project = $this->findProject($id);

        return response()->json(['ok' => true, 'git' => $this->gitState($project)]);
    }

    /**
     * A refusal of one of the Git endpoints, worded from the git_repo lang
     * block.
     *
     * @param int $status
     * @param string $langKey key under git_repo; also the code in `error`
     * @param array<string, mixed> $replace
     * @return \Illuminate\Http\JsonResponse
     */
    protected function gitFail(int $status, string $langKey, array $replace = [])
    {
        return PortalDefinition::jsonError($status, $langKey, [
            'message' => strval(PortalDefinition::trans('git_repo.' . $langKey, $replace)),
        ]);
    }

    /**
     * A Git server failure as a refusal: GitError's own sentence, or for
     * anything else a generic one with a logged reference.
     *
     * @param \Throwable $e
     * @param mixed $context for the log
     * @return \Illuminate\Http\JsonResponse
     */
    protected function gitRemoteFail(\Throwable $e, $context)
    {
        return PortalDefinition::jsonError(422, $e instanceof GitError ? strval($e->reason) : 'remote_unavailable', [
            'message' => GitProvisioner::publicError($e, $context),
        ]);
    }

    /**
     * POST {id}/git_repo - ask for a repository.
     *
     * Records the request and lets GitProvisioner take it as far as this
     * deployment can: with no Git server connected the row simply stays
     * pending, which the answer says out loud instead of pretending.
     *
     * @param Request $request
     * @param mixed $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function postGitRepo(Request $request, $id)
    {
        $project = $this->findProject($id);
        // A repository is the project's, not one person's: the manager asks.
        if (!PortalMembership::canManage($project)) {
            return PortalDefinition::jsonError(403, 'forbidden');
        }

        // The switch has to be saved first - a request out of a dirty,
        // unsaved form would surprise whoever reloads.
        if (!$this->projectFlag($project, 'use_git', false)) {
            return $this->gitFail(422, 'disabled');
        }

        $raw = $request->input('repo_name', '');
        $name = is_scalar($raw) ? strtolower(trim(strval($raw))) : '';
        if (!preg_match(GitRepo::NAME_PATTERN, $name)) {
            return $this->gitFail(422, 'invalid_name');
        }

        $repo = GitRepo::ofProject($project->id);
        if (isset($repo) && $repo->status === GitRepo::STATUS_READY) {
            return $this->gitFail(422, 'already');
        }

        try {
            if (!isset($repo)) {
                $repo = new GitRepo();
                $repo->project_id = intval($project->id);
            }
            $repo->repo_name = $name;
            $repo->provider = GitProvisioner::provider();
            $repo->status = GitRepo::STATUS_PENDING;
            $repo->last_error = null;
            $repo->requested_user_id = intval(\Exment::getUserId());
            $repo->save();

            GitProvisioner::provision($repo);
        } catch (\Throwable $e) {
            // Two presses at once meet the unique project_id; a database
            // that is down fails here too. Neither is worth a raw 500.
            Log::error('pm_portal git: repository request for project ' . $project->id . ' failed: ' . $e->getMessage(), [
                'exception' => $e,
            ]);

            return PortalDefinition::jsonError(500, 'server_error');
        }

        return response()->json(['ok' => true, 'git' => $this->gitState($project)]);
    }

    /**
     * GET {id}/git_keys - the credentials that can clone this repository.
     *
     * Deploy tokens, and any public key an operator registered on the Git
     * server by hand or before the SSH route was dropped - listed so that
     * one can still be revoked from here. The manager sees all of them;
     * anybody else only their own. Listed even while the project's Git
     * switch is off, so that what is still out there can be revoked.
     *
     * @param Request $request
     * @param mixed $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function gitKeys(Request $request, $id)
    {
        $project = $this->findProject($id);
        $repo = $this->gitReadyRepo($project);

        return response()->json([
            'ok' => true,
            'keys' => isset($repo) ? $this->gitKeyList($repo, $project) : [],
        ]);
    }

    /**
     * POST {id}/git_key_remove - take one key off this repository.
     *
     * A POST rather than a DELETE because that is what the plugin page
     * router offers a page: a method per verb it knows, and delete is not
     * one of them.
     *
     * @param Request $request
     * @param mixed $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function postGitKeyRemove(Request $request, $id)
    {
        $project = $this->findProject($id);

        $repo = $this->gitReadyRepo($project);
        $raw = $request->input('key_id', 0);
        $keyId = is_scalar($raw) ? intval($raw) : 0;
        if (!isset($repo) || $keyId <= 0) {
            return $this->gitFail(422, 'keys_no_repo');
        }

        // Your own, or anybody's if you manage the project - the manager is
        // who notices a departed member's credential, and may not have to
        // ask a space administrator to take it back. The owner comes from
        // the portal's own record rather than from the title the Git server
        // shows, because that title is a label the caller chose.
        if (!$this->gitKeyMine($repo, $keyId) && !PortalMembership::canManage($project)) {
            return $this->gitFail(403, 'keys_remove_denied');
        }

        try {
            GitProvisioner::removeKey($repo, $keyId);
        } catch (\Throwable $e) {
            return $this->gitRemoteFail($e, 'revoke ' . $keyId);
        }

        GitKey::forget($repo, $keyId);

        return response()->json(['ok' => true, 'keys' => $this->gitKeyList($repo, $project)]);
    }

    /**
     * POST {id}/git_token - issue a credential that clones this repository
     * over HTTP, on the port the web site already uses.
     *
     * This is the answer to a plain requirement: a customer's network allows
     * port 80 and nothing else. SSH cannot help there, and an ordinary Git
     * server account cannot either - its token carries that account's
     * permissions across every repository it can reach. A deploy token
     * belongs to this repository alone.
     *
     * The value is returned once. The Git server stores a hash of it, so it
     * is not retrievable afterwards and the screen has to show it on the spot.
     *
     * It is returned beside the clone URL, never inside it. A URL carrying
     * the token ends up in .git/config in plain text, in shell history and
     * in build logs, and on an http clone base it crosses the network with
     * every fetch; git asks for the password instead, and a credential
     * helper can keep it.
     *
     * Anybody on the project may hold read-only credentials. One that can
     * push is for those who may write on the project - its manager and
     * members - the same line the issues draw.
     *
     * @param Request $request
     * @param mixed $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function postGitToken(Request $request, $id)
    {
        $project = $this->findProject($id);

        // The switch is the project's word on whether Git is in use at all;
        // off means no new way in. Listing and revoking stay open.
        if (!$this->projectFlag($project, 'use_git', false)) {
            return $this->gitFail(422, 'disabled');
        }

        $repo = $this->gitReadyRepo($project);
        if (!isset($repo)) {
            return $this->gitFail(422, 'keys_no_repo');
        }

        // Read-only unless the caller asked for push, rather than the other
        // way round: a credential that can rewrite a branch should take a
        // deliberate click, and a request that forgot the field gets the
        // harmless one. boolean() rather than boolval(): the string "false"
        // is a request for read-only, not for push.
        $allowPush = $request->boolean('allow_push');
        if ($allowPush && $project->enableEdit() !== true) {
            return $this->gitFail(403, 'push_denied');
        }
        // An archived project is read-only, its code included: a clone, yes,
        // a new way to push, no.
        if ($allowPush && $this->projectIsArchived($project)) {
            return $this->gitFail(403, 'push_archived');
        }

        // A label from the caller is a convenience - "my laptop" - and
        // nothing more. The owner is recorded from the session, never read
        // back off this string: a delete is authorised against the owner,
        // and a caller-supplied owner would authorise anybody. The user id
        // and a timestamp go in regardless, because the Git server keeps
        // titles unique per repository and a fixed default would come back
        // as a duplicate on the second press.
        $rawTitle = $request->input('title', '');
        $title = GitKey::tokenTitle(\Exment::getUserId(), is_scalar($rawTitle) ? strval($rawTitle) : '');

        // One person cannot hold an unbounded number of credentials on one
        // repository: each is a password that works until somebody revokes
        // it. Counted and issued as one locked step (GitKey::issueToken), so
        // presses that arrive together cannot all slip under the ceiling.
        try {
            $issued = GitKey::issueToken($repo, $title, !$allowPush);
        } catch (\Throwable $e) {
            return $this->gitRemoteFail($e, 'token for project ' . $project->id);
        }

        $token = strval(array_get($issued, 'token'));
        unset($issued['token']);

        return response()->json([
            'ok' => true,
            'key' => $issued,
            'token' => $token,
            'clone_url' => $this->gitHttpUrl($repo),
            'keys' => $this->gitKeyList($repo, $project),
        ]);
    }

    /**
     * The repository's credentials as the Git server lists them, with the
     * owner the portal recorded merged in.
     *
     * The Git server answers what exists; this adds the one thing it does
     * not keep. A credential with no local record is ownerless - made by
     * hand on the Git server, or issued before the portal recorded owners -
     * so only the project's manager can remove it.
     *
     * The manager is shown every credential; anybody else only their own.
     * 'label' is what is worth reading of the title: for one the portal
     * issued, the person's own label without the part the portal generated.
     *
     * @param GitRepo $repo
     * @param \Exceedone\Exment\Model\CustomValue $project
     * @return array<int, array<string, mixed>>
     */
    protected function gitKeyList(GitRepo $repo, $project): array
    {
        $owned = GitKey::ofRepo($repo);
        $me = intval(\Exment::getUserId());
        $manager = PortalMembership::canManage($project);

        $names = $this->gitUserNames(array_map(function ($row) {
            return $row->created_user_id;
        }, $owned));

        $out = [];
        foreach (GitProvisioner::keys($repo) as $key) {
            $keyId = intval(array_get($key, 'id'));
            $row = array_get($owned, $keyId);
            $ownerId = isset($row) && isset($row->created_user_id) ? intval($row->created_user_id) : null;
            $mine = isset($ownerId) && $ownerId === $me;
            if (!$mine && !$manager) {
                continue;
            }

            $title = strval(array_get($key, 'title', ''));
            $key['label'] = isset($row) ? GitKey::labelOf($title) : $title;
            $key['owner_id'] = $ownerId;
            $key['owner_name'] = isset($ownerId) ? strval(array_get($names, $ownerId, '')) : null;
            $key['mine'] = $mine;
            $key['can_remove'] = $mine || $manager;
            $out[] = $key;
        }

        return $out;
    }

    /**
     * Whether one credential on this repository belongs to the caller.
     *
     * @param GitRepo $repo
     * @param int $keyId
     * @return bool
     */
    protected function gitKeyMine(GitRepo $repo, int $keyId): bool
    {
        $row = array_get(GitKey::ofRepo($repo), $keyId);

        return isset($row) && isset($row->created_user_id)
            && intval($row->created_user_id) === intval(\Exment::getUserId());
    }

    /**
     * Display names for a set of user custom-value ids, in one query.
     *
     * @param array<int|string, mixed> $ids
     * @return array<int, string>
     */
    protected function gitUserNames(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', array_values($ids)))));
        if (empty($ids)) {
            return [];
        }

        $table = CustomTable::getEloquent(SystemTableName::USER);
        if (!isset($table)) {
            return [];
        }

        $out = [];
        foreach ($table->getValueModel()->newQuery()->whereIn('id', $ids)->get() as $row) {
            $out[intval($row->id)] = strval($row->getLabel());
        }

        return $out;
    }

    /**
     * This project's repository, but only once it really exists on the Git
     * server: a key cannot be registered against a row that is still
     * pending.
     *
     * @param mixed $project
     * @return GitRepo|null
     */
    protected function gitReadyRepo($project)
    {
        $repo = GitRepo::ofProject($project->id);

        return isset($repo) && $repo->status === GitRepo::STATUS_READY ? $repo : null;
    }

    /**
     * POST {id}/delete - Backlog's project delete, typed key and all. The
     * manager's to make.
     *
     * Everything inside the project goes with it: issues, wiki pages and the
     * per-project master rows fall as relation children of the row itself;
     * milestones and categories pointing at the project and issue-link rows
     * pointing at its issues are cleaned up here because nothing cascades a
     * select_table reference. All of it is Exment's soft delete, so unlike
     * Backlog this is recoverable from the trash by an administrator.
     *
     * Rows are found without the caller's row scope: the manager deletes the
     * whole project, including what their own share does not reach - a link
     * from another project's issue, say.
     *
     * What hangs off the ids and not off the rows goes too: stars and
     * watches on the project, its issues and its pages, and the AI log.
     *
     * Git: every clone credential the portal issued on the project's
     * repository is revoked on the Git server, and the portal forgets the
     * repository. The repository itself stays on the Git server, on purpose:
     * it holds the project's source, which a delete from here must not be
     * able to destroy. A credential the Git server would not let go of keeps
     * its record - and the repository row with it - so it can still be found
     * and revoked; the log says which.
     *
     * @param Request $request
     * @param mixed $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteProject(Request $request, $id)
    {
        $project = $this->findProject($id);
        // (true): also respect form-action conditions, the way the admin
        // grid's own delete button would.
        if ($project->enableDelete(true) !== true || !PortalMembership::canManage($project)) {
            return PortalDefinition::jsonError(403, 'forbidden');
        }

        // The same confirmation Backlog asks for. A project without a key
        // falls back to its id, and the client shows whichever it expects.
        $key = strtoupper(strval(array_get($project->value, array_get($this->def['project'], 'key'), '')));
        $expected = $key !== '' ? $key : strval($project->id);
        $confirm = $request->input('confirm', '');
        if (!is_scalar($confirm) || strtoupper(trim(strval($confirm))) !== $expected) {
            return PortalDefinition::jsonError(422, 'danger_mismatch', [
                'message' => strval(PortalDefinition::trans('set.danger_mismatch')),
            ]);
        }

        // The integrity rule the admin grid also enforces: child projects
        // must be re-parented first.
        $reason = PortalRules::validateDestroy($project);
        if ($reason !== null) {
            return PortalDefinition::jsonError(422, 'destroy_refused', ['message' => strval($reason)]);
        }

        $projectId = intval($project->id);
        $issues = $this->issue_table->getValueModel()->newQueryWithoutScopes()
            ->whereNull('deleted_at')
            ->where('parent_id', $projectId)
            ->where('parent_type', $this->project_table->table_name)
            ->get();
        $issue_ids = $issues->pluck('id')->map(function ($id) {
            return intval($id);
        })->all();

        try {
            DB::transaction(function () use ($project, $issues, $issue_ids) {
                // Links and children first, then the project itself. Exment's
                // own cascade would take the issues down with the parent, but
                // it asks the destroy validator about each one on the way -
                // and that validator refuses an issue that still has sub
                // issues, which would abort the whole delete halfway. Clearing
                // the links, then the children, then the parents, leaves it
                // nothing to object to.
                $this->deleteIssueLinks($issue_ids);
                $this->deleteIssues($issues);
                $wiki_ids = $this->deleteProjectWiki($project);
                $this->deleteByProjectColumn('milestone', $project);
                $this->deleteByProjectColumn('category', $project);
                $this->purgeProjectStars($project, $issue_ids, $wiki_ids);
                AiLog::forgetProject(intval($project->id));

                // What is left - the per project status and issue type rows -
                // goes with the row itself, as 1:n relation children.
                $project->delete();
            });
        } catch (ValidationException $e) {
            // A project whose own values no longer satisfy the rules (a key
            // duplicated before those rules existed, say) cannot be saved,
            // and Exment's soft delete is a save. Saying so beats a 500.
            return PortalDefinition::jsonError(422, 'validation', [
                'message' => implode("\n", $e->validator->errors()->all()),
            ]);
        } catch (\Throwable $e) {
            Log::error('pm_portal project ' . $projectId . ' delete failed: ' . $e->getMessage(), ['exception' => $e]);

            return PortalDefinition::jsonError(500, 'server_error');
        }

        // After the commit, never inside it: what the Git server let go of
        // cannot be rolled back, so it is only let go of once the project is
        // gone for certain.
        $git = GitKey::revokeProject($projectId);
        if ($git['failed'] === 0) {
            GitRepo::forgetProject($projectId);
        }

        Log::info(sprintf(
            'pm_portal project %d deleted by user %s (%d issues, %d credential(s) revoked, %d not revoked)',
            $projectId,
            strval(\Exment::getUserId()),
            count($issue_ids),
            $git['revoked'],
            $git['failed']
        ));

        return response()->json(['ok' => true, 'redirect' => PortalDefinition::url()]);
    }

    /**
     * Every issue of the project, sub issues before the issues they hang off,
     * so the destroy validator never meets a parent that still has children.
     *
     * @param \Illuminate\Support\Collection $issues
     * @return void
     */
    protected function deleteIssues($issues): void
    {
        $parent_column = array_get($this->def, 'issue.parent');
        $sorted = $issues->sortByDesc(function ($issue) use ($parent_column) {
            return is_nullorempty($parent_column) ? 0 : (is_nullorempty(array_get($issue->value, $parent_column)) ? 0 : 1);
        });

        foreach ($sorted as $issue) {
            $issue->delete();
        }
    }

    /**
     * The project's wiki pages, deepest first for the same reason.
     *
     * @param \Exceedone\Exment\Model\CustomValue $project
     * @return array<int, int> the ids of the pages deleted
     */
    protected function deleteProjectWiki($project): array
    {
        $wiki_table = CustomTable::getEloquent(array_get($this->def, 'wiki_table'));
        if (!isset($wiki_table)) {
            return [];
        }

        $parent_column = array_get($this->def, 'wiki.parent');
        $pages = $wiki_table->getValueModel()->newQueryWithoutScopes()
            ->whereNull('deleted_at')
            ->where('parent_id', $project->id)
            ->where('parent_type', $this->project_table->table_name)
            ->get()
            ->sortByDesc(function ($page) use ($parent_column) {
                return is_nullorempty($parent_column) ? 0 : (is_nullorempty(array_get($page->value, $parent_column)) ? 0 : 1);
            });

        $ids = [];
        foreach ($pages as $page) {
            $ids[] = intval($page->id);
            $page->delete();
        }

        return $ids;
    }

    /**
     * Milestones and categories carry the project in a select_table column
     * rather than as a parent, so they survive the cascade and are removed
     * here - categories used to be left behind. Found by the column's index
     * rather than by reading the whole table, and checked again on the row,
     * because the JSON may hold the id as either string or int.
     *
     * @param string $role 'milestone' or 'category'
     * @param \Exceedone\Exment\Model\CustomValue $project
     * @return void
     */
    protected function deleteByProjectColumn(string $role, $project): void
    {
        $table = CustomTable::getEloquent(array_get($this->def, $role . '_table'));
        if (!isset($table)) {
            return;
        }
        $column = CustomColumn::getEloquent(array_get($this->def, $role . '.project'), $table);
        if (!isset($column)) {
            return;
        }

        $rows = $table->getValueModel()->newQueryWithoutScopes()
            ->whereNull('deleted_at')
            ->where($column->getQueryKey(), strval($project->id))
            ->get();
        foreach ($rows as $row) {
            if (intval(array_get($row->value, $column->column_name)) === intval($project->id)) {
                $row->delete();
            }
        }
    }

    /**
     * Stars and watches on the project, its issues and its pages. They are
     * kept by table name and id, so left behind they would land on whatever
     * takes those ids next, and a watch keeps sending a deleted issue's
     * comments to whoever set it.
     *
     * @param \Exceedone\Exment\Model\CustomValue $project
     * @param array<int, int> $issue_ids
     * @param array<int, int> $wiki_ids
     * @return void
     */
    protected function purgeProjectStars($project, array $issue_ids, array $wiki_ids): void
    {
        $targets = [
            [$this->project_table->table_name, [intval($project->id)]],
            [$this->issue_table->table_name, $issue_ids],
            [strval(array_get($this->def, 'wiki_table')), $wiki_ids],
        ];
        foreach ($targets as [$type, $ids]) {
            if ($type === '' || empty($ids)) {
                continue;
            }
            foreach (array_chunk($ids, 500) as $chunk) {
                Star::where('target_type', $type)
                    ->whereIn('target_id', array_map('strval', $chunk))
                    ->delete();
            }
        }
    }

    /**
     * Issue-link rows reference issues from both ends; once the issues of a
     * deleted project are gone the links point at nothing and would only
     * resurface as broken "related" chips if the project were restored
     * without them. Removed with the same soft delete.
     *
     * Found without the caller's row scope: a link from an issue of another
     * project, one the manager cannot see, points at this project all the
     * same.
     *
     * @param array<int, mixed> $issue_ids
     * @return void
     */
    protected function deleteIssueLinks(array $issue_ids): void
    {
        if (empty($issue_ids)) {
            return;
        }
        $rel_table = CustomTable::getEloquent(array_get($this->def, 'related_table'));
        $rel = array_get($this->def, 'related_row', []);
        if (!isset($rel_table) || is_nullorempty(array_get($rel, 'from'))) {
            return;
        }

        $keys = [];
        foreach (['from', 'to'] as $end) {
            $column = CustomColumn::getEloquent(array_get($rel, $end), $rel_table);
            if (isset($column)) {
                $keys[] = $column->getQueryKey();
            }
        }
        if (empty($keys)) {
            return;
        }

        $ids = array_map('intval', $issue_ids);
        foreach (array_chunk($ids, 500) as $chunk) {
            $values = array_map('strval', $chunk);
            $rows = $rel_table->getValueModel()->newQueryWithoutScopes()
                ->whereNull('deleted_at')
                ->where(function ($either) use ($keys, $values) {
                    foreach ($keys as $key) {
                        $either->orWhereIn($key, $values);
                    }
                })
                ->get();
            foreach ($rows as $row) {
                $from = intval(array_get($row->value, array_get($rel, 'from')));
                $to = intval(array_get($row->value, array_get($rel, 'to')));
                if (in_array($from, $ids, true) || in_array($to, $ids, true)) {
                    $row->delete();
                }
            }
        }
    }
}
