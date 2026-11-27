<?php

namespace App\Plugins\ExmentPM;

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
        // the Files tab with the file server: a feature of Feature management (ISS02825)
        PortalFiles::registerFeature();
        // what happens to the issues, told to chat channels: a feature, off at first (ISS02939)
        PortalChannels::registerFeature();
        // attachments edited where they are, with versions: a feature of Feature management, off at first (ISS02938)
        PortalAttachEdit::registerFeature();
        // recurring schedules: a feature of Feature management, off at first (ISS00432)
        PortalSchedule::registerFeature();
        // Git linked to the issues by webhook: a feature, off at first, and its own route (ISS02939)
        PortalGitHooks::registerFeature();
        // the portal as an installable web app on phones: a feature, off at first (ISS02939)
        PortalPwa::registerFeature();
        // Web Push for the bells, through the web app's service worker: a feature, off at first
        PortalPush::registerFeature();
        try {
            PortalGitHooks::registerRoutes();
        } catch (\Throwable $e) {
            \Log::error('ExmentPM could not add the Git webhook route: ' . $e->getMessage());
        }
        // reminders before the due date: a feature of Feature management, off at first (ISS00432)
        PortalReminder::registerFeature();
        // the morning digest of each person: a feature of Feature management, off at first (ISS02939)
        PortalDigest::registerFeature();
        // Exment's "?" of the header opens the manual of the plugin (ISS02882)
        ManualLink::register($plugin);
        // before 初期セットアップ: the entry in the left menu that leads to it (7.2.1)
        try {
            PortalSetup::ensurePreSetupMenu();
        } catch (\Throwable $e) {
            \Log::error('ExmentPM could not add its menu before the setup: ' . $e->getMessage());
        }
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

    // The short links of 1.6.1 (/view/KEY, /projects/KEY) were routed in
    // config.json but had no method here, so they never reached the portal
    // and the links in earlier mails did not open (found in ISS00440).
    public function viewIssue($key)
    {
        return $this->respond($this->portal()->viewIssue(request(), $key));
    }

    public function viewProject($code)
    {
        return $this->respond($this->portal()->viewProject(request(), $code));
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

    // ISS00264: the issue list across the projects
    public function allIssues()
    {
        return $this->respond($this->portal()->allIssues(request(), new Content()));
    }

    public function recordLinkTables()
    {
        return $this->portal()->recordLinkTables(request());
    }

    public function recordLinks()
    {
        return $this->portal()->recordLinks(request());
    }

    public function linkTables($id)
    {
        return $this->portal()->linkTables(request(), $id);
    }

    public function postLinkTables($id)
    {
        return $this->portal()->postLinkTables(request(), $id);
    }

    public function linkSearch($id)
    {
        return $this->portal()->linkSearch(request(), $id);
    }

    public function issueLinks($id, $issue_id)
    {
        return $this->portal()->issueLinks(request(), $id, $issue_id);
    }

    public function postIssueLinks($id, $issue_id)
    {
        return $this->portal()->postIssueLinks(request(), $id, $issue_id);
    }

    public function allIssuesData()
    {
        return $this->portal()->allIssuesData(request());
    }

    public function allIssuesExport()
    {
        return $this->portal()->allIssuesExport(request());
    }

    // ISS00244: the issues of every project in one gantt, a project per group
    public function allGantt()
    {
        return $this->respond($this->portal()->allGantt(request(), new Content()));
    }

    public function exportIssues($id)
    {
        return $this->portal()->exportIssues(request(), $id);
    }

    // ISS00248
    public function setIssueAlias($id, $issue_id)
    {
        return $this->portal()->setIssueAlias(request(), $id, $issue_id);
    }

    // the notices menu (bell / mail / Teams): its route had no method here
    public function postNotifyBy()
    {
        return $this->portal()->postNotifyBy(request());
    }

    // ISS02924: hide a change of the history
    public function hideRevision($id, $issue_id, $revision_id)
    {
        return $this->portal()->hideRevision(request(), $id, $issue_id, $revision_id);
    }

    // ISS00432: the recurring schedules of a project
    public function schedulesPage($id)
    {
        return $this->respond($this->portal()->schedulesPage(request(), new Content(), $id));
    }

    public function schedulesSave($id)
    {
        return $this->portal()->schedulesSave(request(), $id);
    }

    public function schedulesDelete($id, $sid)
    {
        return $this->portal()->schedulesDelete(request(), $id, $sid);
    }

    public function schedulesException($id, $sid)
    {
        return $this->portal()->schedulesException(request(), $id, $sid);
    }

    // ISS00432 2b: the calendars
    public function schedulesCalendar($id)
    {
        return $this->respond($this->portal()->schedulesCalendar(request(), new Content(), $id));
    }

    public function companyCalendar()
    {
        return $this->respond($this->portal()->companyCalendar(request(), new Content()));
    }

    public function companyCalendarSave()
    {
        return $this->portal()->companyCalendarSave(request());
    }

    public function companyCalendarDelete()
    {
        return $this->portal()->companyCalendarDelete(request());
    }

    // ISS00432: the reminders of a project
    public function reminderPage($id)
    {
        return $this->respond($this->portal()->reminderPage(request(), new Content(), $id));
    }

    public function reminderSave($id)
    {
        return $this->portal()->reminderSave(request(), $id);
    }

    // ISS02863 / ISS02931: how the notices reach this person
    public function notifySettings()
    {
        return $this->respond($this->portal()->notifySettings(request(), new Content()));
    }

    public function notifySettingsSave()
    {
        return $this->portal()->notifySettingsSave(request());
    }

    // ISS02938: the attachments of an issue, edited where they are
    public function attachVersions($id, $issue_id, $doc)
    {
        return $this->portal()->attachVersions(request(), $id, $issue_id, $doc);
    }

    public function attachRestore($id, $issue_id, $doc, $version)
    {
        return $this->portal()->attachRestore(request(), $id, $issue_id, $doc, $version);
    }

    public function attachText($id, $issue_id, $doc)
    {
        return $this->portal()->attachText(request(), $id, $issue_id, $doc);
    }

    public function attachTextSave($id, $issue_id, $doc)
    {
        return $this->portal()->attachTextSave(request(), $id, $issue_id, $doc);
    }

    // ISS02938: the attachments of a wiki page, the same way
    public function wikiAttachVersions($id, $page_id, $doc)
    {
        return $this->portal()->attachVersions(request(), $id, $page_id, $doc, 'wiki');
    }

    public function wikiAttachRestore($id, $page_id, $doc, $version)
    {
        return $this->portal()->attachRestore(request(), $id, $page_id, $doc, $version, 'wiki');
    }

    public function wikiAttachText($id, $page_id, $doc)
    {
        return $this->portal()->attachText(request(), $id, $page_id, $doc, 'wiki');
    }

    public function wikiAttachTextSave($id, $page_id, $doc)
    {
        return $this->portal()->attachTextSave(request(), $id, $page_id, $doc, 'wiki');
    }

    public function wikiAttachLock($id, $page_id, $doc)
    {
        return $this->portal()->attachLock(request(), $id, $page_id, $doc, 'wiki');
    }

    public function attachLock($id, $issue_id, $doc)
    {
        return $this->portal()->attachLock(request(), $id, $issue_id, $doc);
    }

    public function mailIssue($id, $issue_id)
    {
        return $this->portal()->mailIssue(request(), $id, $issue_id);
    }

    // the notices of the bell inside the portal (ISS02939 item 6): the person's own only
    public function notices()
    {
        return response()->json(array_merge(['ok' => true], PortalNotices::list(intval(\Exment::getUserId()))), 200, ['Cache-Control' => 'no-store']);
    }

    public function noticesReadAll()
    {
        $n = PortalNotices::readAll(intval(\Exment::getUserId()));

        return response()->json(['ok' => true, 'marked' => $n, 'unread' => 0]);
    }

    // Web Push (off at first): 「この端末に通知する」 of this browser, the person's own only.
    // action: subscribe (the browser's PushSubscription), unsubscribe or state (its endpoint)
    public function pushAction()
    {
        if (!PortalPush::ready()) {
            abort(404);
        }
        $request = request();
        $userId = intval(\Exment::getUserId());
        // a page calls this a few times; a loop is stopped (30 a minute a person)
        $limit = 'pm_portal_push:' . $userId;
        if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($limit, 30)) {
            return response()->json(['ok' => false, 'error' => 'too_many'], 429);
        }
        \Illuminate\Support\Facades\RateLimiter::hit($limit, 60);
        $sub = $request->input('subscription');
        $endpoint = is_array($sub) && is_string($sub['endpoint'] ?? null) ? $sub['endpoint'] : '';
        switch (strval($request->input('action'))) {
            case 'key':
                // the site's public key, made the first time (here, not on a page view)
                $key = PortalPush::publicKey(true);

                return response()->json(['ok' => $key !== '', 'key' => $key], 200, ['Cache-Control' => 'no-store']);
            case 'subscribe':
                $result = PortalPush::subscribe($userId, $sub, strval($request->userAgent()));
                if (!$result['ok']) {
                    return response()->json(['ok' => false, 'error' => $result['error'] ?? 'bad', 'message' => strval(PortalDefinition::trans('push.err_' . ($result['error'] ?? 'bad')))], 422);
                }

                return response()->json(['ok' => true, 'on' => true]);
            case 'unsubscribe':
                PortalPush::unsubscribe($userId, $endpoint);

                return response()->json(['ok' => true, 'on' => false]);
            case 'state':
                return response()->json(['ok' => true, 'on' => PortalPush::subscribed($userId, $endpoint)], 200, ['Cache-Control' => 'no-store']);
        }

        return response()->json(['ok' => false, 'error' => 'bad'], 422);
    }

    // Web Push: 「鍵を作り直す」 (system administrators): a new key pair, every browser subscribes again
    public function postPushKeys()
    {
        $user = \Exment::user();
        if ($user === null || !$user->isAdministrator()) {
            abort(403);
        }
        if (!PortalPush::enabled()) {
            abort(404);
        }
        PortalPush::renewKeys();

        return redirect(PortalDefinition::url('settings') . '?push_keys=renewed#pp-push-box');
    }

    public function asset($name)
    {
        return $this->portal()->asset(request(), $name);
    }

    // ISS02939 item 6: the portal as an installable web app (off at first)
    public function pwaManifest()
    {
        if (!PortalPwa::enabled()) {
            abort(404);
        }
        $site = '';
        try {
            $site = strval(\Exceedone\Exment\Model\System::site_name());
        } catch (\Throwable $e) {
            $site = strval(config('admin.name', ''));
        }
        $body = json_encode(PortalPwa::manifest(PortalPwa::scope(), $site, strval(PortalDefinition::trans('pwa.app_name'))), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return response($body, 200, ['Content-Type' => 'application/manifest+json; charset=utf-8', 'Cache-Control' => 'private, max-age=3600', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function pwaWorker()
    {
        if (!PortalPwa::enabled()) {
            abort(404);
        }
        $assets = strval(parse_url(asset(PortalDefinition::PUBLIC_ASSET_DIR . '/'), PHP_URL_PATH) ?: '');

        return response(PortalPwa::worker(PortalPwa::scope(), $assets, PortalPwa::version(), strval(PortalDefinition::trans('pwa.app_name'))), 200, [
            'Content-Type' => 'text/javascript; charset=utf-8', 'Cache-Control' => 'no-cache', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function pwaOffline()
    {
        if (!PortalPwa::enabled()) {
            abort(404);
        }

        return response(PortalPwa::offlinePage(strval(PortalDefinition::trans('pwa.app_name')), strval(PortalDefinition::trans('pwa.offline_title')),
            strval(PortalDefinition::trans('pwa.offline_line')), strval(PortalDefinition::trans('pwa.offline_retry'))), 200, [
            'Content-Type' => 'text/html; charset=utf-8', 'Cache-Control' => 'no-cache', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function pwaIcon($name)
    {
        $png = PortalPwa::enabled() ? PortalPwa::icon(strval($name)) : null;
        if ($png === null) {
            abort(404);
        }

        return response($png, 200, ['Content-Type' => 'image/png', 'Cache-Control' => 'private, max-age=86400', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function issueMoveTargets($id, $issue_id)
    {
        return $this->portal()->issueMoveTargets(request(), $id, $issue_id);
    }

    public function moveIssue($id, $issue_id)
    {
        return $this->portal()->moveIssue(request(), $id, $issue_id);
    }

    public function deleteIssue($id, $issue_id)
    {
        return $this->portal()->deleteIssue(request(), $id, $issue_id);
    }

    public function projectReadAll($id)
    {
        return $this->portal()->projectReadAll(request(), $id);
    }

    public function archivedIssues($id)
    {
        return $this->portal()->archivedIssues(request(), $id);
    }

    // UX S15/M5: 「最近の更新」, read after the page.
    public function projectFeed($id)
    {
        return $this->portal()->projectFeed(request(), $id);
    }

    // ISS02931: where each person's notice arrives now (quiet hours, mutes), when the dock opens.
    public function projectNotifyChannels($id)
    {
        return $this->portal()->projectNotifyChannels(request(), $id);
    }

    public function issueRead($id, $issue_id)
    {
        return $this->portal()->issueRead(request(), $id, $issue_id);
    }

    public function issueReadMany($id)
    {
        return $this->portal()->issueReadMany(request(), $id);
    }

    public function uploadIssueFile($id, $issue_id)
    {
        return $this->portal()->uploadIssueFile(request(), $id, $issue_id);
    }

    public function commentIssue($id, $comment_id)
    {
        return $this->portal()->commentIssue(request(), $id, $comment_id);
    }

    public function updateComment($id, $comment_id)
    {
        return $this->portal()->updateComment(request(), $id, $comment_id);
    }

    public function deleteComment($id, $comment_id)
    {
        return $this->portal()->deleteComment(request(), $id, $comment_id);
    }

    public function reviewComment($id, $comment_id)
    {
        return $this->portal()->reviewComment(request(), $id, $comment_id);
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

    // MD-1: the editors' side-by-side preview, the user's setting
    public function postMdSplit()
    {
        return $this->portal()->postMdSplit(request());
    }

    public function searchIssues()
    {
        return $this->portal()->searchIssues(request());
    }

    public function topbarProjects()
    {
        return $this->portal()->topbarProjects(request());
    }

    public function ranking()
    {
        return $this->respond($this->portal()->ranking(request(), new Content()));
    }

    // ISS02939 item 5: the repositories of a project connected by webhook
    public function gitHooks($id)
    {
        return $this->portal()->gitHooks(request(), $id);
    }

    public function postGitHook($id)
    {
        return $this->portal()->postGitHook(request(), $id);
    }

    public function postGitHookSecret($id, $hook)
    {
        return $this->portal()->postGitHookSecret(request(), $id, $hook);
    }

    public function postGitHookUpdate($id, $hook)
    {
        return $this->portal()->postGitHookUpdate(request(), $id, $hook);
    }

    public function postGitHookDelete($id, $hook)
    {
        return $this->portal()->postGitHookDelete(request(), $id, $hook);
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

    public function fileWorkspace($id)
    {
        return $this->portal()->fileWorkspace(request(), $id);
    }

    public function postFileWorkspace($id)
    {
        return $this->portal()->postFileWorkspace(request(), $id);
    }

    public function attributes($id)
    {
        return $this->portal()->attributes(request(), $id);
    }

    public function postAttributes($id)
    {
        return $this->portal()->postAttributes(request(), $id);
    }

    public function channels($id)
    {
        return $this->portal()->channels(request(), $id);
    }

    public function postChannels($id)
    {
        return $this->portal()->postChannels(request(), $id);
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

    // Data migration: a ZIP download on success, otherwise the settings page
    // again with the error or the import report in its migration card.
    public function postMigrateExport()
    {
        return $this->respond($this->settingsController()->migrateExport(request(), new Content()));
    }

    public function postMigrateImport()
    {
        return $this->respond($this->settingsController()->migrateImport(request(), new Content()));
    }
}
