<?php

namespace App\Plugins\PmPortal;

use Exceedone\Exment\Services\Plugin\PluginPageBase;
use ExmentAdminCore\Admin\Layout\Content;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * Project portal, packaged as a plugin.
 *
 * Named PluginPage rather than Plugin because the plugin declares more than
 * one plugin type: once plugin_type is a list Exment looks for Plugin{Type},
 * so a file called Plugin.php would simply stop being found.
 *
 * PluginPageController::__call hands over route parameters only, so the
 * request is taken from the container here and the work is forwarded to the
 * controllers. Anything that is already a Response - every lightapi endpoint
 * - is returned untouched, which is what keeps the JSON contract identical to
 * the routes this plugin replaces.
 */
class PluginPage extends PluginPageBase
{
    /**
     * Exment builds this class on every boot - ExmentServiceProvider::load()
     * asks each page plugin for its views - which makes the constructor the
     * one place a plugin runs in every request and every artisan command.
     * The model listeners that PluginEvent needs go on there.
     *
     * @param \Exceedone\Exment\Model\Plugin $plugin
     */
    public function __construct($plugin)
    {
        parent::__construct($plugin);

        PluginEvent::registerGuards();
    }

    /**
     * The portal renders its own chrome, so the generic plugin header would
     * only push the layout down.
     *
     * @return bool
     */
    public function _showHeader()
    {
        return false;
    }

    protected function portal(): PmPortalController
    {
        return app(PmPortalController::class);
    }

    protected function api(): LightApiController
    {
        return app(LightApiController::class);
    }

    /**
     * Call a lightapi method and make sure the caller gets JSON either way.
     *
     * Without this an uncaught throw is rendered by Exment's admin error
     * page: an HTML body carrying HTTP 200. A JSON client reads that as a
     * successful call, so a delete that actually failed looked like it had
     * worked and the row silently stayed. Answering 500 with a JSON body
     * keeps every response on the contract the client is written against.
     *
     * @param array<int, mixed> $args
     */
    protected function apiCall(string $method, array $args): JsonResponse
    {
        // The lightapi routes carry admin.morph in their middleware_except, an
        // optimisation that assumes a route touches only the table in its URL.
        // Permission and history do not hold under it: without the morph map a
        // row's share stops resolving, so a member is refused on rows that are
        // theirs, and every revision is written under the raw Class_{suuid}
        // name instead of the table name, dropping the edit from history, the
        // activity feed and the burndown. Define it here - the same call the
        // middleware makes - so every call behaves like the web path it mirrors.
        \Exceedone\Exment\Middleware\Morph::defineMorphMap();

        try {
            return call_user_func_array([$this->api(), $method], $args);
        } catch (\Throwable $e) {
            Log::error('pm_portal lightapi ' . $method . ' failed: ' . $e->getMessage(), [
                'exception' => $e,
            ]);

            // The message can carry the SQL text, the host, the port and the
            // database name (a QueryException prints all four), so it is logged
            // above for the operator but never handed back to the client.
            return PortalDefinition::jsonError(500, 'server_error');
        }
    }

    protected function settingsController(): PortalSettingsController
    {
        return app(PortalSettingsController::class);
    }

    /**
     * Hand back something PluginPageController::__call will not touch.
     *
     * That method wraps any non-Response return value in a fresh Content row,
     * which would nest the portal's own layout inside a second one - the page
     * still renders, but the header is lost and the markup is duplicated.
     * Rendering here turns it into a Response, which __call passes straight
     * through.
     *
     * @param mixed $result
     * @return mixed
     */
    protected function respond($result)
    {
        if ($result instanceof \Symfony\Component\HttpFoundation\Response) {
            return $result;
        }

        if ($result instanceof Content || $result instanceof \Illuminate\Contracts\Support\Renderable) {
            return response($result->render());
        }

        return $result;
    }

    // ---- portal screens -------------------------------------------------

    public function index()
    {
        return $this->respond($this->portal()->index(request(), new Content()));
    }

    public function setup()
    {
        return $this->respond($this->portal()->setup(request(), new Content()));
    }

    public function postSetup()
    {
        return $this->portal()->postSetup(request());
    }

    public function show($id = null)
    {
        return $this->respond($this->portal()->show(request(), new Content(), $id));
    }

    public function embed($id, $kind)
    {
        return $this->respond($this->portal()->embed(request(), $id, $kind));
    }

    public function issueDetails($id)
    {
        return $this->portal()->issueDetails(request(), $id);
    }

    public function issueDetail($id, $issue_id)
    {
        return $this->portal()->issueDetail(request(), $id, $issue_id);
    }

    public function postComment($id, $issue_id)
    {
        return $this->portal()->postComment(request(), $id, $issue_id);
    }

    public function uploadIssueFile($id, $issue_id)
    {
        return $this->portal()->uploadIssueFile(request(), $id, $issue_id);
    }

    public function updateComment($id, $comment_id)
    {
        return $this->portal()->updateComment(request(), $id, $comment_id);
    }

    public function deleteComment($id, $comment_id)
    {
        return $this->portal()->deleteComment(request(), $id, $comment_id);
    }

    public function toggleStar($id)
    {
        return $this->portal()->toggleStar(request(), $id);
    }

    public function projectData($id)
    {
        return $this->portal()->projectData(request(), $id);
    }

    public function projectStamp($id)
    {
        return $this->portal()->projectStamp(request(), $id);
    }

    public function bulkUpdate($id)
    {
        return $this->portal()->bulkUpdate(request(), $id);
    }

    public function postFormStyle()
    {
        return $this->portal()->postFormStyle(request());
    }

    public function searchIssues()
    {
        return $this->portal()->searchIssues(request());
    }

    public function topbarProjects()
    {
        return $this->portal()->topbarProjects(request());
    }

    public function gitRepo($id)
    {
        return $this->portal()->gitRepo(request(), $id);
    }

    public function postGitRepo($id)
    {
        return $this->portal()->postGitRepo(request(), $id);
    }

    public function gitKeys($id)
    {
        return $this->portal()->gitKeys(request(), $id);
    }

    public function postGitKeyRemove($id)
    {
        return $this->portal()->postGitKeyRemove(request(), $id);
    }

    public function postGitToken($id)
    {
        return $this->portal()->postGitToken(request(), $id);
    }

    public function aiSuggest($id)
    {
        return $this->portal()->aiSuggest(request(), $id);
    }

    public function aiFeedback($id)
    {
        return $this->portal()->aiFeedback(request(), $id);
    }

    public function aiState($id)
    {
        return $this->portal()->aiState(request(), $id);
    }

    public function aiTest($id)
    {
        return $this->portal()->aiTest(request(), $id);
    }

    public function deleteProject($id)
    {
        return $this->portal()->deleteProject(request(), $id);
    }

    // ---- lightapi -------------------------------------------------------

    public function apiSchema($tableKey)
    {
        return $this->apiCall('schema', [request(), $tableKey]);
    }

    public function apiList($tableKey)
    {
        return $this->apiCall('list', [request(), $tableKey]);
    }

    public function apiFind($tableKey, $id)
    {
        return $this->apiCall('find', [request(), $tableKey, $id]);
    }

    public function apiLookup($tableKey)
    {
        return $this->apiCall('lookup', [request(), $tableKey]);
    }

    public function apiEditForm($tableKey, $id)
    {
        return $this->apiCall('editForm', [request(), $tableKey, $id]);
    }

    public function apiCreateForm($tableKey)
    {
        return $this->apiCall('createForm', [request(), $tableKey]);
    }

    public function apiCreate($tableKey)
    {
        return $this->apiCall('create', [request(), $tableKey]);
    }

    public function apiUpdate($tableKey, $id)
    {
        return $this->apiCall('update', [request(), $tableKey, $id]);
    }

    public function apiDestroy($tableKey, $id)
    {
        return $this->apiCall('destroy', [request(), $tableKey, $id]);
    }

    public function apiReorder($tableKey)
    {
        return $this->apiCall('reorder', [request(), $tableKey]);
    }

    public function apiProjectMembers($id)
    {
        return $this->apiCall('projectMembers', [request(), $id]);
    }

    public function apiCard($kind, $id)
    {
        return $this->apiCall('card', [request(), $kind, $id]);
    }

    // ---- settings -------------------------------------------------------

    public function settings()
    {
        return $this->respond($this->settingsController()->index(request(), new Content()));
    }

    public function postUpgrade()
    {
        return $this->settingsController()->upgrade(request());
    }
}
