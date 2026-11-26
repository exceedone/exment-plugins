<?php

namespace App\Plugins\ExmentPM;

use Exceedone\Exment\Controllers\AdminControllerBase;

use ExmentAdminCore\Admin\Layout\Content;
use Exceedone\Exment\ColumnItems\GridCellStyle;
use Exceedone\Exment\Enums\ColumnType;
use Exceedone\Exment\Enums\FileType;
use Exceedone\Exment\Enums\NotifySavedType;
use Exceedone\Exment\Enums\SystemTableName;
use Exceedone\Exment\Enums\ViewKindType;
use Exceedone\Exment\Model\CustomColumn;
use Exceedone\Exment\Model\CustomTable;
use Exceedone\Exment\Model\CustomValue;
use Exceedone\Exment\Model\CustomView;
use Exceedone\Exment\Model\File as ExmentFile;
use Exceedone\Exment\Model\NotifyNavbar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * The project portal - Backlog's project screens on top of plain Exment
 * tables.
 *
 * Everything shown here already lives in Exment: issues are custom values,
 * comments are the comment system table, the change history is the revision
 * table, files are documents, notifications are the navbar notify. This
 * controller only gathers them per project and serves one page that draws
 * them the way a Backlog user expects.
 */
class PmPortalController extends AdminControllerBase
{
    use PortalDashboardTrait;
    use PortalTopbarTrait;
    use PortalProjectTrait;
    use PortalAiTrait;
    use PortalRankingTrait;
    use PortalExportTrait;
    use PortalAllIssuesTrait;
    use PortalFeedTrait;
    use PortalChannelsTrait;
    use PortalSchedulePageTrait;
    use PortalCalendarPageTrait;
    use PortalReminderPageTrait;

    /**
     * How many issues one bulk edit may touch.
     *
     * Each row is saved on its own so that validators, events, revisions and
     * notifications all fire exactly as they do for a hand edit - which means
     * the cost is per row, and an unbounded request would sit on the database
     * for minutes. The list can only ever offer a page at a time, so this is
     * a backstop against a hand written request rather than a limit anyone
     * meets by clicking.
     */
    private const BULK_LIMIT = 200;

    /**
     * How many people one comment may name to notify on top of the watchers.
     *
     * The dock offers the project's people, so a real request stays far below
     * this. It only stops a hand written one from ringing a bell for the whole
     * user table; ids past it are ignored rather than refused, the same way an
     * id that cannot read the issue is.
     */
    /** The mails of "mail this issue" a person sends in an hour at most. */
    private const MAIL_ISSUE_PER_HOUR = 30;

    private const NOTIFY_PICK_LIMIT = 100;

    /**
     * Issues one "mark read / unread" request may name. The list marks up to
     * 100 checked rows at once; a page going away sends whatever it had not
     * sent yet, which is fewer still.
     */
    private const READ_MANY_LIMIT = 500;

    /**
     * Revisions the burndown reads at most, newest first.
     *
     * The chart is worked out from the state every issue was in when its
     * window opens, so a cut at this many only moves where the drawn history
     * starts - the counts after it stay exact.
     */
    private const BURNDOWN_REVISION_LIMIT = 2000;

    /** @var array<string, mixed> */
    protected $def;

    /** @var CustomTable|null */
    protected $project_table;

    /** @var CustomTable|null */
    protected $issue_table;

    /**
     * Column definitions and related-record labels, resolved once per
     * request. The revision diff asks for the same handful over and over -
     * CustomColumn::getEloquent costs ~1ms a call and a related label costs
     * two queries, so without this a page of 200 issues spends seconds
     * looking up things it already knows.
     *
     * @var array<string, mixed>
     */
    protected $column_cache = [];

    /** @var array<string, string> */
    protected $label_cache = [];

    /**
     * ISS00257: the feed and the issue screens diff many of the same
     * revisions; each is decoded and compared once per request.
     *
     * @var array<string, array<int, array<int, string>>>
     */
    protected $diff_cache = [];

    /** @var array<int, array<int, array<string, mixed>>> decoded [old, new] by revision id */
    protected $revision_values = [];

    public function __construct()
    {
        $this->def = PortalDefinition::config();
        $this->project_table = CustomTable::getEloquent($this->def['project_table']);
        $this->issue_table = CustomTable::getEloquent($this->def['issue_table']);
    }

    /**
     * Project chooser.
     */
    public function index(Request $request, Content $content)
    {
        if (PortalSetup::needsSetup()) {
            return redirect(PortalDefinition::url('setup'));
        }
        // Recurring issues whose turn has come (at most every few minutes).
        PortalRecurrence::tick();
        // the issues of the recurring schedules (ISS00432)
        PortalSchedule::tick();
        // reminders whose time has come, when the batch is not run (ISS00432)
        PortalReminder::tick();

        if (!isset($this->project_table) || !isset($this->issue_table)) {
            abort(404);
        }
        $this->setPageInfo(PortalDefinition::trans('title'), PortalDefinition::trans('title'), PortalDefinition::trans('description'), 'fa-folder-open');

        // The sidebar's settings link stores the plugin path; catch an
        // admin's change of the plugin URI here, on the page its own menu
        // entry still reaches.
        PortalUpgrade::syncSettingsMenu();

        $pj = $this->def['project'];

        list($status_meta) = $this->selectMeta($this->project_table, array_get($pj, 'status'));

        // An archived project is finished business: Backlog keeps it out of
        // the way but does not delete it, so it is hidden unless asked for.
        $hasArchived = CustomColumn::getEloquent('archived', $this->project_table) !== null;
        $showArchived = $hasArchived && $request->boolean('archived');
        $archivedCount = 0;

        $parentColumn = CustomColumn::getEloquent('parent_project', $this->project_table) !== null ? 'parent_project' : null;

        // One read, used twice: once to resolve a parent id into a name and
        // once to build the cards.
        $rows = $this->project_table->getValueModel()->get();
        // ISS00257: one query for every project's shares instead of one or
        // two per project in the permission checks below.
        $this->project_table->setGridAuthoritable($rows);
        $names = [];
        if ($parentColumn) {
            foreach ($rows as $row) {
                $names[strval($row->id)] = strval($row->getLabel());
            }
        }

        // Every project this user can open, archived ones included. The
        // dashboard reads across all of them - an issue assigned to somebody
        // in a project that has since been archived is still their issue, and
        // a feed entry from one still has to say where it came from.
        // CustomValue::enableAccess() is the table check followed by the row
        // check, and the table half depends on nothing but the table and who
        // is asking - so calling it once per project cost 15ms a card here and
        // would cost a second and a half on an install with a hundred of them.
        // Split, the row half stays exactly as it was.
        $cards = [];
        $table_access = $this->project_table->enableAccess() === true;
        // Asked once, like the table half of enableAccess above.
        $issue_create = isset($this->issue_table) && $this->issue_table->enableCreate(true) === true;
        foreach ($table_access ? $rows : [] as $value) {
            if (!$this->project_table->hasPermissionData($value)) {
                continue;
            }

            $archived = $hasArchived && boolval(array_get($value->value, 'archived'));
            if ($archived) {
                $archivedCount++;
            }

            $parentId = $parentColumn ? strval(array_get($value->value, $parentColumn, '')) : '';
            $status_key = strval(array_get($value->value, array_get($pj, 'status'), ''));
            $cards[strval($value->id)] = [
                'id' => $value->id,
                'name' => strval($value->getLabel()),
                'key' => strtoupper(strval(array_get($value->value, array_get($pj, 'key'), ''))),
                'note' => strval(array_get($value->value, array_get($pj, 'note'), '')),
                'status' => array_get($status_meta, $status_key),
                'parent' => $parentId === '' ? null : array_get($names, $parentId),
                'parent_id' => $parentId,
                'order' => strval(array_get($value->value, 'display_order', '')),
                'archived' => $archived,
                'count' => 0,
                'url' => PortalDefinition::projectUrl($value),
                'star_url' => PortalDefinition::url($value->id, 'star'),
                // The hover menu offers 設定 only to somebody who could
                // actually open that tab, which is the project's manager
                // (projectCan) - a member edits the row too, but only so that
                // Exment lets them file issues under it. The model rather than
                // its id: by id, Exment reads the row again for every card.
                'can_edit' => $this->project_table->hasPermissionEditData($value) && PortalMembership::canManage($value),
                // ...and 課題の追加 only where an issue can be filed.
                'can_add' => $this->childAddable($this->issue_table ?? null, $value, $issue_create),
            ];
        }

        $dashboard = $this->buildDashboard($cards);

        // Starred projects first, because that is the whole point of starring
        // one. Everything else keeps the order the table gave it, so a child
        // project still follows its parent.
        // ISS00263 (snoro 2026-11-18): the same tree as the project menu -
        // each parent followed by its indented children, in the dragged order
        // then by name - instead of starred first.
        $projects = static::projectTreeOrder(array_values(array_filter($cards, function ($card) use ($showArchived) {
            return !$card['archived'] || $showArchived;
        })));
        $reorderUrl = static::topbarIsAdmin() && CustomColumn::getEloquent('display_order', $this->project_table) !== null
            ? PortalDefinition::url('lightapi/reorder/' . $this->project_table->table_name) : '';

        return $this->AdminContent($content)->body(view(PortalDefinition::view('list'), [
            'projects' => $projects,
            'reorder_url' => $reorderUrl,
            'dashboard' => $dashboard,
            'topbar' => $this->topbarData(null, $cards),
            'project_table' => $this->project_table->table_name,
            // The add-project modal: where to POST, which columns to send,
            // and the key rule to explain before the server has to.
            'create' => [
                'can' => $this->project_table->enableCreate(true) === true,
                'api' => PortalDefinition::url('lightapi', 'data', $this->project_table->table_name),
                'name_col' => array_get($pj, 'name'),
                'key_col' => array_get($pj, 'key'),
                'parent_col' => CustomColumn::getEloquent('parent_project', $this->project_table) !== null ? 'parent_project' : null,
                'code_max' => PortalRules::CODE_MAX,
                'defaults' => $this->projectCreateDefaults(),
            ],
            'archived_count' => $archivedCount,
            'show_archived' => $showArchived,
            // admin_url appends extra arguments as path segments, so the
            // query string has to be built by hand.
            'toggle_url' => PortalDefinition::url() . ($showArchived ? '' : '?archived=1'),
        ])->render());
    }

    /**
     * First run preflight.
     *
     * Shows every table the bundled template would create, flags the ones an
     * install already owns and lets an administrator move the whole set behind
     * a prefix instead of overwriting them.
     */
    public function setup(Request $request, Content $content)
    {
        if (!PortalSetup::needsSetup()) {
            return redirect(PortalDefinition::url());
        }

        // The preflight lists every table, menu, role group and notification
        // name in the install, and only a system administrator may run the
        // install it leads to. Anybody else is told to ask one, on Exment's
        // own error page, instead of being shown the inventory.
        if (!static::isSystemAdmin()) {
            abort(403, strval(PortalDefinition::trans('setup.not_admin')));
        }

        // Open on the real state so a clash is visible, and offer a prefix
        // that clears it rather than silently applying one.
        $prefix = strval($request->get('prefix', ''));

        // The lead is already the info box at the top of the view; repeating it
        // as the header description printed the same sentence twice.
        $this->setPageInfo(
            PortalDefinition::trans('title'),
            PortalDefinition::trans('setup.title'),
            null,
            'fa-database'
        );

        return $this->AdminContent($content)->body(view(PortalDefinition::view('setup'), [
            'tables' => PortalSetup::plannedTables($prefix),
            'existing' => PortalSetup::existingTableNames(),
            // Menu nodes, role groups and the notification: not blockers, but
            // an administrator should see what the install is about to join.
            'reserved' => PortalSetup::reservedNames($prefix),
            'taken' => PortalSetup::takenNames(),
            'prefix' => $prefix,
            'failed_at' => PortalSetup::failedAttemptAt(),
            'suggestion' => PortalSetup::suggestPrefix(),
            'max_length' => PortalSetup::MAX_TABLE_NAME_LENGTH,
            'is_admin' => static::isSystemAdmin(),
            'action' => PortalDefinition::url('setup'),
        ])->render());
    }

    /**
     * Whether the signed-in user is a system administrator - the only role
     * setup is open to.
     */
    protected static function isSystemAdmin(): bool
    {
        $user = \Exment::user();

        return $user !== null && boolval($user->isAdministrator());
    }

    /**
     * Run the template import the preflight screen asked for.
     */
    public function postSetup(Request $request)
    {
        if (!static::isSystemAdmin()) {
            abort(403, strval(PortalDefinition::trans('setup.not_admin')));
        }

        if (!PortalSetup::needsSetup()) {
            return redirect(PortalDefinition::url());
        }

        $result = PortalSetup::install(strval($request->get('prefix', '')));

        if (!$result['ok']) {
            admin_toastr(static::toastText($result['message']), 'error');

            // The toast is flashed for one request only, so go straight to the
            // page the administrator ends up on; a second redirect would
            // swallow the error. A step that failed after the tables were
            // made (the schema upgrade) lands on the portal, not the wizard.
            if (!PortalSetup::needsSetup()) {
                return redirect(PortalDefinition::url());
            }

            return redirect(PortalDefinition::url('setup') . '?prefix=' . rawurlencode(strval($request->get('prefix', ''))));
        }

        admin_toastr(static::toastText($result['message']));

        return redirect(PortalDefinition::url());
    }

    /**
     * Make a message safe for admin_toastr().
     *
     * The admin layout prints the flashed message unescaped inside a single
     * quoted JavaScript string, and toastr renders it as HTML. An SQL error
     * always contains quotes, so the raw text broke the script and the toast
     * never appeared. Escaped for HTML, with backslashes doubled and line
     * breaks folded, it survives both.
     */
    protected static function toastText(string $message): string
    {
        // Without /u when the text is not valid UTF-8; e() substitutes the bad bytes.
        $text = preg_replace('/\s+/u', ' ', $message) ?? preg_replace('/\s+/', ' ', $message);
        // An SQL error carries the whole statement; the log keeps it in full.
        $text = mb_strimwidth(strval($text), 0, 400, '…');

        return e(str_replace('\\', '\\\\', $text));
    }

    /**
     * A short link to one issue, the way Backlog writes them: /view/KEY.
     *
     * KEY is the portal's PROJECTKEY-id, the issue_key (ISS00123), or for an
     * imported issue its key at the source (MIGRE_TEST_2026-2). It answers
     * with a redirect to the project's page, which opens the issue from the
     * part after the #. A ?c=<comment id> is carried along, so a link to a
     * comment is short too.
     *
     * Looked for in this order, so a key means what it says first:
     * 1. PROJECTKEY-id whose project is still that project
     * 2. the source key of an imported issue
     * 3. the issue_key column
     * 4. PROJECTKEY-id of an issue since moved to another project, when
     *    PROJECTKEY is still the key of a project the user may open
     * An issue the user may not open is answered as if it were not there.
     */
    public function viewIssue(Request $request, $key)
    {
        if (PortalSetup::needsSetup()) {
            return redirect(PortalDefinition::url('setup'));
        }
        if (!isset($this->project_table) || !isset($this->issue_table)) {
            abort(404);
        }
        $key = trim(strval($key));
        $issue = $this->issueByKey($key);
        if (!isset($issue)) {
            abort(404);
        }
        $project = $this->project_table->getValueModel()->find($issue->parent_id);
        if (!isset($project) || $project->enableAccess() !== true) {
            abort(404);
        }
        $url = $this->issueUrl($project, $issue);
        $comment = strval($request->query('c', ''));
        if (ctype_digit($comment)) {
            $url .= '?c=' . $comment;
        }

        return redirect($url);
    }

    /**
     * The issue a short-link key names, or null. See viewIssue().
     *
     * @param string $key
     * @return \Exceedone\Exment\Model\CustomValue|null
     */
    protected function issueByKey(string $key)
    {
        if ($key === '' || strlen($key) > 200) {
            return null;
        }
        $model = $this->issue_table->getValueModel();
        $mine = function ($issue) {
            return isset($issue) && $issue->parent_type == $this->project_table->table_name
                && $issue->enableAccess() === true ? $issue : null;
        };

        $byId = null;
        if (preg_match('/^(.+)-(\d+)$/', $key, $m)) {
            $byId = $mine($model->find(intval($m[2])));
            if (isset($byId)) {
                $project = $this->project_table->getValueModel()->find($byId->parent_id);
                if (isset($project) && strcasecmp($this->issueKey($project, $byId->id), $key) === 0) {
                    return $byId;
                }
            }
        }
        $columns = [PortalSchema::ISSUE_COLUMNS['source_key'], array_get($this->def['issue'], 'key', 'issue_key')];
        foreach ($columns as $column) {
            if (is_nullorempty($column) || CustomColumn::getEloquent($column, $this->issue_table) === null) {
                continue;
            }
            $found = $mine($model->where('value->' . $column, $key)->first());
            if (isset($found)) {
                return $found;
            }
        }

        // ISS00248: an alias, or a key the issue had before it was moved
        $aliasOf = PortalIssueAliases::issueOf($key);
        if ($aliasOf !== null) {
            $found = $mine($model->find($aliasOf));
            if (isset($found)) {
                return $found;
            }
        }

        // A moved issue: its old project's key still names a project.
        return isset($byId) && isset($m[1]) && $this->projectByCode($m[1]) !== null ? $byId : null;
    }

    /**
     * The project whose key is $code (either case) and that this user may
     * open, or null. Only readable ones, so a short link does not tell
     * whether a project the user cannot see has that key.
     *
     * @param string $code
     * @return \Exceedone\Exment\Model\CustomValue|null
     */
    protected function projectByCode(string $code)
    {
        $column = strval(array_get($this->def['project'], 'key', ''));
        if ($code === '' || strlen($code) > 200 || $column === '' || CustomColumn::getEloquent($column, $this->project_table) === null) {
            return null;
        }

        return $this->project_table->getValueModel()
            ->whereIn('value->' . $column, array_unique([$code, strtoupper($code), strtolower($code)]))
            ->get()
            ->first(function ($row) {
                return $row->enableAccess() === true;
            });
    }

    /**
     * The project an address names by code (ISS00440): the one whose code it
     * is now, or else the one that had it before a rename (ProjectCodes).
     * Only a project this user may open, so an address does not tell
     * whether a project the user cannot see has, or had, that code.
     *
     * @param string $code
     * @return \Exceedone\Exment\Model\CustomValue|null
     */
    protected function projectByAnyCode(string $code)
    {
        $code = trim($code);
        if (PortalDefinition::codeSegment($code) === null) {
            return null;
        }
        $project = $this->projectByCode($code);
        if (isset($project)) {
            return $project;
        }
        $column = strval(array_get($this->def['project'], 'key', ''));
        foreach (ProjectCodes::projectIds($code) as $id) {
            $row = $this->project_table->getValueModel()->find($id);
            if (isset($row) && $row->enableAccess() === true
                && strcasecmp(strval(array_get($row->value, $column, '')), $code) !== 0) {
                return $row;
            }
        }

        return null;
    }

    /**
     * A short link to a project: /projects/PROJECTKEY, like Backlog's.
     * Redirects to the project's page, which is addressed by the code too
     * since ISS00440.
     */
    public function viewProject(Request $request, $code)
    {
        if (PortalSetup::needsSetup()) {
            return redirect(PortalDefinition::url('setup'));
        }
        if (!isset($this->project_table) || !isset($this->issue_table)) {
            abort(404);
        }
        // Either case, and an old code after a rename (projectByAnyCode()).
        $project = $this->projectByAnyCode(strval($code));
        if (!isset($project)) {
            abort(404);
        }

        return redirect(PortalDefinition::projectUrl($project));
    }

    /**
     * One project's portal.
     */
    public function show(Request $request, Content $content, $id = null)
    {
        if (PortalSetup::needsSetup()) {
            return redirect(PortalDefinition::url('setup'));
        }

        try {
            $project = $this->findProject($id);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            // not there, or not one of the caller's (ISS00395): said so, with the way back,
            // instead of Exment's page of an error. Both answer alike.
            if (!in_array($e->getStatusCode(), [403, 404], true)) {
                throw $e;
            }
            return $this->notMemberPage($content);
        }
        // ISS00440: the page's address is the project's code. An id, a code in
        // another case or an old code is sent on to it; the browser keeps the
        // part after the #, and the script rewrites an old #issue- there.
        $segment = PortalDefinition::projectSegment($project);
        if (strval($id) !== $segment) {
            $query = strval($request->getQueryString());

            return redirect(PortalDefinition::projectUrl($project) . ($query !== '' ? '?' . $query : ''));
        }
        PortalRecurrence::tick();
        // the issues of the recurring schedules (ISS00432)
        PortalSchedule::tick();
        // reminders whose time has come, when the batch is not run (ISS00432)
        PortalReminder::tick();

        $this->setPageInfo(
            PortalDefinition::trans('title'),
            strval($project->getLabel()),
            strval(array_get($project->value, array_get($this->def['project'], 'note'), '')),
            'fa-folder-open'
        );

        $payload = $this->buildPayload($project);
        $lazy = (bool) config('exment.pmportal.lazy_widgets', true);
        // A project with the chart feature off has no gantt tab at all, so
        // the widget behind it is neither looked for nor rendered.
        $gantt_on = boolval(array_get($payload, 'project.features.gantt', true));

        return $this->AdminContent($content)->body(view(PortalDefinition::view('index'), [
            'payload' => $payload,
            // the link to the recurring schedules of the project, for its manager (ISS00432)
            'schedule_url' => (PortalSchedule::enabled() && PortalMembership::canManage($project))
                ? PortalDefinition::url($project->id, 'schedules') : null,
            // the link to the reminders of the project, for its manager (ISS00432)
            'reminder_url' => (PortalReminder::enabled() && PortalReminderRules::exists() && PortalMembership::canManage($project))
                ? PortalDefinition::url($project->id, 'reminder') : null,
            'topbar' => $this->topbarData(strval($project->id)),
            'lazy_widgets' => $lazy,
            'board_available' => $this->hasEmbeddedView(ViewKindType::KANBAN),
            'gantt_available' => $gantt_on && $this->hasEmbeddedView(ViewKindType::GANTT),
            'board_html' => $lazy ? null : $this->renderEmbedded($project, ViewKindType::KANBAN),
            'gantt_html' => ($lazy || !$gantt_on) ? null : $this->renderEmbedded($project, ViewKindType::GANTT),
        ])->render());
    }

    /**
     * Render the kanban / gantt widget on demand. The heavy build - drawing
     * cards, laying out bars - only happens when the tab is actually opened,
     * so the portal home does not pay for widgets nobody looks at.
     *
     * @param Request $request
     * @param mixed $id
     * @param string $kind
     * @return \Illuminate\Http\Response
     */
    public function embed(Request $request, $id, $kind)
    {
        $view_kind_type = $this->embedKindType($kind);
        if ($view_kind_type === null) {
            abort(404);
        }

        $project = $this->findProject($id);
        $html = $this->renderEmbedded($project, $view_kind_type);
        if ($html === null) {
            return response('', 204);
        }

        return response($html)->header('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * Map the URL segment used by the portal to the widget's view kind.
     *
     * @param string $kind
     * @return string|null
     */
    protected function embedKindType($kind)
    {
        switch ($kind) {
            case 'board':
                return ViewKindType::KANBAN;
            case 'gantt':
                return ViewKindType::GANTT;
        }
        return null;
    }

    /**
     * Whether the issue table has at least one view of this kind. Used to
     * pick between "loading" and "no view configured" on the tab.
     *
     * @param string $view_kind_type
     * @return bool
     */
    protected function hasEmbeddedView($view_kind_type)
    {
        return CustomView::where('custom_table_id', $this->issue_table->id)
            ->where('view_kind_type', $view_kind_type)
            ->exists();
    }

    /**
     * One issue's detail: description, watchers and the merged timeline.
     */
    public function issueDetail(Request $request, $id, $issue_id)
    {
        $project = $this->findProject($id);
        $issue = $this->findIssue($project, $issue_id);
        $detail = $this->issueDetailData($project, $issue, \Exment::getUserId());

        return response()->json(array_merge(['ok' => true], $detail, [
            'users' => $this->usersFor($this->detailUserIds($detail)),
        ]));
    }

    /**
     * Details for many issues in one request. The portal prefetches these
     * right after it renders, so opening a row costs no round trip at all.
     */
    public function issueDetails(Request $request, $id)
    {
        $project = $this->findProject($id);
        $me = \Exment::getUserId();

        $ids = collect(explode(',', strval($request->get('ids'))))
            ->map(function ($v) {
                return intval(trim($v));
            })
            ->filter()->unique()->take(30)->values();

        $issues = $this->issue_table->getValueModel()
            ->whereIn('id', $ids)
            ->where('parent_id', $project->id)
            ->where('parent_type', $this->project_table->table_name)
            ->get()
            ->filter(function ($issue) {
                return $issue->enableAccess() === true;
            })
            ->values();

        $details = $this->detailsFor($project, $issues, $me);
        $user_ids = [];
        foreach ($issues as $issue) {
            $key = strval($issue->id);
            if (!isset($details[$key])) {
                continue;
            }
            $user_ids = array_merge($user_ids, $this->detailUserIds($details[$key]));
            $details[$key]['issue'] = $this->buildIssueRow($project, $issue);
        }

        return response()->json([
            'ok' => true,
            'details' => $details,
            'users' => $this->usersFor(array_values(array_unique($user_ids))),
        ]);
    }

    /**
     * How the user gets the notices of the issues (ISS02863): bell only, by mail too, by Teams
     * too, or both. Saved in the user's own settings.
     */
    public function postNotifyBy(Request $request)
    {
        $choice = $request->get('notify_by');
        $choice = is_scalar($choice) ? strval($choice) : '';
        if (!in_array($choice, PortalTeams::CHOICES, true) || (!PortalTeams::teamsAvailable() && in_array($choice, ['teams', 'both'], true))) {
            return $this->messageError(400, 'invalid_params');
        }
        \Exment::user()->setSettingValue(PortalTeams::USER_SETTING, $choice);

        return response()->json(['ok' => true, 'notify_by' => $choice]);
    }

    /**
     * The user's saved form style. With nothing saved yet the first bundled
     * style applies.
     */
    public function postFormStyle(Request $request)
    {
        $styles = PortalDefinition::formStyles();
        $style = $request->get('style');
        $style = is_scalar($style) ? strval($style) : '';
        if (!array_key_exists($style, $styles)) {
            return $this->messageError(400, 'invalid_params');
        }
        \Admin::user()->setSettingValue('pmportal.form_style', $style);

        return response()->json(['ok' => true, 'style' => $style]);
    }

    /**
     * The active form style: the user's saved pick, or the first bundled one
     * when the user has not chosen yet.
     *
     * @param array<string, mixed> $styles
     * @return string
     */
    protected function currentFormStyle(array $styles): string
    {
        $style = strval(\Admin::user()->getSettingValue('pmportal.form_style'));

        return array_key_exists($style, $styles) ? $style : strval(array_key_first($styles));
    }

    /**
     * One issue's detail payload, with its row (the single-issue API shape).
     *
     * @param \Exceedone\Exment\Model\CustomValue $project
     * @param \Exceedone\Exment\Model\CustomValue $issue
     * @param mixed $me
     * @return array<string, mixed>
     */
    protected function issueDetailData($project, $issue, $me)
    {
        $details = $this->detailsFor($project, collect([$issue]), $me);

        return array_merge(
            ['issue' => $this->buildIssueRow($project, $issue)],
            array_get($details, strval($issue->id), [])
        );
    }

    /**
     * Details of many issues at once - description, watchers and the merged
     * timeline - with bulk queries: one for the revisions, one for the
     * comments, one for the documents and four for the stars, whatever the
     * number of issues.
     *
     * The page embeds this for its own issues (see buildPayload), so opening
     * an issue costs no request at all. That is what actually makes the
     * screen fast here: a request spends ~400ms booting the framework and
     * ~50ms doing the work, so the only real win is not making the request.
     *
     * @param \Exceedone\Exment\Model\CustomValue $project
     * @param \Illuminate\Support\Collection $issues
     * @param mixed $me
     * @return array<string, array<string, mixed>> issue id => detail
     */
    /**
     * Keep the issue screens that come with the page under a size budget.
     *
     * Thirty screens are usually ~80KB, but one issue with a long thread
     * can be megabytes on its own (an imported Backlog issue of 132
     * comments made a 6MB page). The biggest go first; the screen fetches
     * whatever is missing when it is opened, as it does for any issue past
     * the embed limit.
     *
     * @param array<string, mixed> $details
     * @return array<string, mixed>
     */
    protected function trimEmbedded(array $details): array
    {
        $budget = intval(config('exment.pmportal.embed_details_bytes', 400000));
        if ($budget <= 0 || empty($details)) {
            return $details;
        }
        $sizes = [];
        foreach ($details as $key => $one) {
            $sizes[$key] = strlen(strval(json_encode($one, JSON_UNESCAPED_UNICODE)));
        }
        $total = array_sum($sizes);
        if ($total <= $budget) {
            return $details;
        }
        arsort($sizes);
        foreach ($sizes as $key => $size) {
            if ($total <= $budget) {
                break;
            }
            unset($details[$key]);
            $total -= $size;
        }

        return $details;
    }

    protected function detailsFor($project, $issues, $me): array
    {
        if ($issues->isEmpty()) {
            return [];
        }
        $issue_name = $this->issue_table->table_name;
        $ids = $issues->pluck('id')->map(function ($v) {
            return intval($v);
        })->all();
        $count = count($ids);

        // ---- one query each, then grouped by issue in memory
        $revision_cap = max(200, $count * 30);
        $revision_rows = $this->revisionsOf($issue_name, $ids, $revision_cap);
        // the query has a ceiling; when it is reached some issue's history may
        // be cut, and the screen must say so rather than show half a story
        $partial = count($revision_rows) >= $revision_cap;
        $revisions = [];
        foreach ($revision_rows as $rev) {
            $revisions[intval($rev->revisionable_id)][] = $rev;
        }
        $this->primeLabels($this->issue_table, $revision_rows);

        $comment_rows = getModelName(SystemTableName::COMMENT)::whereIn('parent_id', $ids)
            ->where('parent_type', $issue_name)
            ->get();
        $comment_ids = $comment_rows->pluck('id')->toArray();
        $comment_stars = Star::counts(Star::TYPE_STAR, 'comment', $comment_ids);
        $my_comment_stars = Star::mine(Star::TYPE_STAR, 'comment', $comment_ids, $me);
        // Reactions (ISS00259), one query for the comments and one for the issues.
        $comment_reactions = Star::reactions('comment', $comment_ids);
        $issue_reactions = Star::reactions($issue_name, $ids);
        // Review requests (ISS00341): who was asked to confirm each comment.
        $comment_reviews = Star::reviews('comment', $comment_ids);
        $comments = [];
        foreach ($comment_rows as $comment) {
            $comments[intval($comment->parent_id)][] = $comment;
        }

        $documents = [];
        $document_cap = max(50, $count * 10);
        $document_rows = getModelName(SystemTableName::DOCUMENT)::whereIn('parent_id', $ids)
            ->where('parent_type', $issue_name)
            ->orderBy('created_at', 'desc')
            ->take($document_cap)
            ->get();
        // Same ceiling, same honesty: one issue with many files can use up
        // the batch, and the screen then completes it from the single-issue
        // endpoint instead of showing a short attachment list as the whole.
        if (count($document_rows) >= $document_cap) {
            $partial = true;
        }
        foreach ($document_rows as $doc) {
            $documents[intval($doc->parent_id)][] = $doc;
        }

        $watch_counts = Star::counts(Star::TYPE_WATCH, $issue_name, $ids);
        $my_watches = Star::mine(Star::TYPE_WATCH, $issue_name, $ids, $me);
        // Read receipts: one more indexed read on the same table.
        $readers = Star::readers($issue_name, $ids);

        // An archived project takes no edits and no comment changes (see
        // postComment), so the screen is not offered controls that can only
        // be refused.
        $archived = $this->projectIsArchived($project);
        // the manager may hide any change of the history (ISS02924)
        $canManage = PortalMembership::canManage($project);

        $out = [];
        foreach ($issues as $issue) {
            // ---- timeline: created + revisions + comments + files, oldest first
            $timeline = [];
            $timeline[] = [
                'tp' => 'add',
                'by' => intval($issue->created_user_id),
                'at' => $this->fmtAt($issue->created_at),
                'ts' => strval($issue->created_at),
            ];

            // The change history is shown to everyone who can read the issue,
            // viewers included, where Exment's own detail screen shows it to
            // editors only. That is deliberate: Backlog shows every reader who
            // changed what, and an issue's history is no more private than the
            // issue - the old values were readable to the same people when
            // they were current. Changes to who is on a project are another
            // matter; see buildFeed.
            foreach (($revisions[intval($issue->id)] ?? []) as $rev) {
                if (intval($rev->revision_no) <= 1) {
                    continue;
                }
                $changes = $this->diffRevision($this->issue_table, $rev);
                if (empty($changes)) {
                    continue;
                }
                $timeline[] = [
                    'tp' => 'chg',
                    'rid' => intval($rev->id),
                    'by' => intval($rev->create_user_id),
                    'at' => $this->fmtAt($rev->created_at),
                    'ts' => strval($rev->created_at),
                    'chg' => $changes,
                    // who made the change, or the manager, may hide it (a value put in by mistake; ISS02924)
                    'hide' => !$archived && (intval($rev->create_user_id) === intval($me) || $canManage),
                ];
            }

            foreach (($comments[intval($issue->id)] ?? []) as $comment) {
                $entry = [
                    'tp' => 'cmt',
                    'cid' => $comment->id,
                    'by' => intval($comment->created_user_id),
                    'at' => $this->fmtAt($comment->created_at),
                    'ts' => strval($comment->created_at),
                    'text' => strval(array_get($comment->value, 'comment_detail', '')),
                    'star' => intval(array_get($comment_stars, strval($comment->id), 0)),
                    'my_star' => in_array(strval($comment->id), $my_comment_stars),
                    'rx' => (object) ($comment_reactions[strval($comment->id)] ?? []),
                    'del' => !$archived && intval($comment->created_user_id) == intval($me),
                ];
                // Only on a comment that asked for a confirmation: most do
                // not, and the page carries thirty issues' worth of these.
                // "at" is when the person confirmed; a pending one has none.
                if (!empty($comment_reviews[strval($comment->id)])) {
                    $entry['rv'] = array_map(function ($review) {
                        return [
                            'by' => $review['by'],
                            'ok' => $review['ok'],
                            'at' => $review['ok'] ? $this->fmtAt($review['ts']) : '',
                        ];
                    }, $comment_reviews[strval($comment->id)]);
                    // An archived project takes no confirmation (see
                    // reviewComment), so the button is not offered there.
                    $entry['rv_on'] = !$archived;
                }
                $timeline[] = $entry;
            }

            // The same documents twice: once as a line in the history, once
            // as the attachment box Backlog puts beside the description. A
            // screenshot pasted into a comment has to be findable without
            // scrolling back through the whole conversation.
            $files = [];
            foreach (($documents[intval($issue->id)] ?? []) as $doc) {
                $name = strval(array_get($doc->value, 'document_name', ''));
                $url = admin_url('files', [array_get($doc->value, 'file_uuid')]);
                $timeline[] = [
                    'tp' => 'file',
                    'by' => intval($doc->created_user_id),
                    'at' => $this->fmtAt($doc->created_at),
                    'ts' => strval($doc->created_at),
                    'name' => $name,
                    'url' => $url,
                ];
                $files[] = [
                    'name' => $name,
                    'url' => $url,
                    'by' => intval($doc->created_user_id),
                    'at' => $this->fmtAt($doc->created_at),
                    'img' => static::isImageName($name),
                ];
            }

            usort($timeline, function ($a, $b) {
                return strcmp($a['ts'], $b['ts']);
            });

            // Who has opened the issue, latest first. "old" = opened before
            // its last change, so that reader has not seen the latest yet.
            $last_change = strval($issue->updated_at ?? $issue->created_at);
            foreach (($comments[intval($issue->id)] ?? []) as $comment) {
                if (strval($comment->created_at) > $last_change) {
                    $last_change = strval($comment->created_at);
                }
            }
            $reads = [];
            foreach (($readers[strval($issue->id)] ?? []) as $read) {
                $reads[] = [
                    'by' => $read['by'],
                    'at' => $this->fmtAt($read['ts']),
                    'old' => $read['ts'] < $last_change,
                ];
            }

            $out[strval($issue->id)] = [
                'description' => \Exceedone\Exment\ColumnItems\CustomColumns\Editor::replaceImgUrl(
                    strval(array_get($issue->value, array_get($this->def['issue'], 'description'), ''))
                ),
                'editable' => !$archived && PortalDefinition::enableEdit($issue, true) === true,
                // Moving needs edit rights on the issue; the server lists the
                // projects it may go to when the button is pressed.
                'movable' => !$archived && PortalDefinition::enableEdit($issue, true) === true,
                'deletable' => !$archived && (PortalMembership::canManage($project) || PortalDefinition::enableDelete($issue, true) === true),
                'watch' => [
                    'count' => intval(array_get($watch_counts, strval($issue->id), 0)),
                    'on' => in_array(strval($issue->id), $my_watches),
                ],
                'timeline' => collect($timeline)->map(function ($row) {
                    unset($row['ts']);
                    return $row;
                })->values()->all(),
                'files' => $files,
                'reads' => $reads,
                'reactions' => (object) ($issue_reactions[strval($issue->id)] ?? []),
                // Schema 25: where an imported issue came from, and the
                // source's custom attributes. Empty for an issue made here.
                'source' => $this->issueSource($issue->value),
            ];
            if ($partial) {
                $out[strval($issue->id)]['partial'] = true;
            }
        }

        return $out;
    }

    /**
     * An imported issue's source (schema 25), or null for one made here.
     * The URL is only handed on when it is http(s): the screen makes a link
     * of it, and the column takes whatever an import wrote.
     *
     * @param mixed $value the issue's value array
     * @return array<string, string>|null
     */
    protected function issueSource($value): ?array
    {
        $v = (array) $value;
        $c = PortalSchema::ISSUE_COLUMNS;
        $out = [
            'key' => strval(array_get($v, $c['source_key'], '')),
            'url' => strval(array_get($v, $c['source_url'], '')),
            'cby' => strval(array_get($v, $c['source_created_by'], '')),
            'cd' => $this->fmtAt(array_get($v, $c['source_created_at'])),
            'uby' => strval(array_get($v, $c['source_updated_by'], '')),
            'ud' => $this->fmtAt(array_get($v, $c['source_updated_at'])),
            'cf' => strval(array_get($v, $c['custom_fields'], '')),
            'asg' => strval(array_get($v, $c['source_assignees'], '')),
        ];
        if (!preg_match('#^https?://#i', $out['url'])) {
            $out['url'] = '';
        }

        return implode('', $out) === '' ? null : $out;
    }

    /**
     * Post a comment, change the status with it, and notify people -
     * Backlog's one-form update.
     */
    public function postComment(Request $request, $id, $issue_id)
    {
        $project = $this->findProject($id);
        $issue = $this->findIssue($project, $issue_id);
        $issue_name = $this->issue_table->table_name;

        // Backlog keeps an archived project read-only, its conversation
        // included. Stars and watches stay open: they change nothing anybody
        // else reads.
        if ($this->projectIsArchived($project)) {
            return $this->messageError(403, 'archived');
        }

        $comment = $request->get('comment', '');
        $comment = is_scalar($comment) ? trim(strval($comment)) : '';
        $status_to = $request->get('status_to', '');
        $status_to = is_scalar($status_to) ? strval($status_to) : '';
        $status_column = array_get($this->def['issue'], 'status');
        $status_old = strval(array_get($issue->value, $status_column, ''));

        // The status the form showed when it was filled in. Somebody may have
        // moved the issue since, and a status picked against the old one - the
        // "next status" button above all - would then move it the wrong way.
        // Nothing is written: the answer carries the issue as it stands now, so
        // the screen can show what changed while the typed comment stays put.
        // An empty status arrives as null (ConvertEmptyStringsToNull) and is
        // compared as ''.
        if ($request->has('status_from')) {
            $status_from = $request->get('status_from');
            if ((is_scalar($status_from) ? strval($status_from) : '') !== $status_old) {
                return PortalDefinition::jsonError(409, 'stale', array_merge(
                    $this->issueDetailBody($project, $issue->id),
                    ['message' => strval(PortalDefinition::trans('message.status_stale'))]
                ));
            }
        }

        $changed = $status_to !== '' && $status_to !== $status_old;
        if ($comment === '' && !$changed) {
            return $this->messageError(400, 'comment_empty');
        }
        if (mb_strlen($comment) > PortalDefinition::maxBody()) {
            return $this->messageError(422, 'too_long', ['max' => number_format(PortalDefinition::maxBody())]);
        }
        $status_meta = [];
        if ($changed) {
            if (PortalDefinition::enableEdit($issue, true) !== true) {
                return $this->messageError(403, 'no_permission');
            }
            list($status_meta) = $this->selectMeta($this->issue_table, $status_column, $issue->parent_id);
            if (!array_key_exists($status_to, $status_meta)) {
                return $this->messageError(400, 'invalid_params');
            }
        }

        // Review request (ISS00341): with "review" ticked, the people picked to
        // be told are also asked to confirm the comment. It hangs off the
        // comment, so a status change alone asks nobody; and only people who
        // can read the issue can be asked - anybody else could never open it
        // to confirm, and would stand there as pending for ever.
        $reviewers = [];
        if ($comment !== '' && $this->wantsReview($request)) {
            $reviewers = array_values(array_diff(
                PortalMembership::filterReaders($issue, $this->pickedUsers($request)),
                [intval(\Exment::getUserId())]
            ));
        }

        // Every bell Exment rings for this write - the table's notify for the
        // status change and for the comment, the @mention pipeline - is a row
        // past this mark. The portal's own bell then skips whoever got one.
        $bell_mark = intval(NotifyNavbar::withoutGlobalScopes()->max('id'));

        if ($changed) {
            try {
                $issue->setValue([$status_column => $status_to])->save();
            } catch (ValidationException $e) {
                // PortalRules refused it (a sub-issue where sub-issues are
                // off, say); its sentence says what to change.
                return $this->validationError($e);
            } catch (\Throwable $e) {
                Log::error('pm_portal status change of issue ' . $issue->id . ' failed: ' . $e->getMessage(), ['exception' => $e]);

                return PortalDefinition::jsonError(500, 'save_failed');
            }
        }

        // The address the bells, the mail and Teams link to: the comment
        // itself once there is one (the screen scrolls to it), else the issue.
        $notice_url = $this->issueUrl($project, $issue);
        if ($comment !== '') {
            try {
                $model = CustomTable::getEloquent(SystemTableName::COMMENT)->getValueModel();
                $model->parent_id = $issue->id;
                $model->parent_type = $issue_name;
                $model->setValue(['comment_detail' => $comment]);
                $model->save();
            } catch (\Throwable $e) {
                // A status changed just before stays changed; posting again
                // finds it moved and answers "stale" with the fresh issue,
                // rather than moving it twice.
                Log::error('pm_portal comment on issue ' . $issue->id . ' failed: ' . $e->getMessage(), ['exception' => $e]);

                return PortalDefinition::jsonError(500, 'save_failed');
            }

            // Stars are kept by comment id, and an id comes round again after
            // Exment's refreshdata empties the comment table. A comment this
            // new cannot have been starred yet.
            Star::purge('comment', $model->id);
            $notice_url = static::commentUrl($notice_url, $model->id);

            // Review request (ISS00341): the picked people are asked to
            // confirm this comment. After the purge above, which would take
            // these rows with it. A failure here costs the request, never the
            // comment: it is saved, and an error answer would get it posted
            // twice.
            if (!empty($reviewers)) {
                try {
                    Star::askReview('comment', $model->id, $reviewers);
                } catch (\Throwable $e) {
                    $reviewers = [];
                    Log::error('pm_portal review request on comment ' . $model->id . ' failed: ' . $e->getMessage(), ['exception' => $e]);
                }
            }

            // What Exment's own comment form does next (DefaultShow::addComment):
            // the issue table's notifications for a comment - its assignees,
            // notified users and creator - which a portal comment never rang.
            $this->runTableNotifies($issue, NotifySavedType::COMMENT, ['comment' => $comment]);
            // the project's chat channels that take comments (ISS02939), after the answer
            PortalChannels::commented($issue, $comment);
        }

        // ---- the portal's own bell: picked people + watchers.
        // @mentions are NOT handled here: saving the comment already runs the
        // core mention pipeline (notifyMentionedUsersInComment), and the mark
        // above keeps the people it told from being told twice.
        $lines = [];
        if ($changed) {
            // Labels, not the stored keys: a converted status column stores row
            // ids, and 「状態を 12 から 15 に変更しました」 tells nobody anything.
            $status_custom_column = CustomColumn::getEloquent($status_column, $this->issue_table);
            $label = function ($key) use ($status_meta, $status_custom_column) {
                if (isset($status_meta[$key]['l'])) {
                    return strval($status_meta[$key]['l']);
                }

                return isset($status_custom_column) ? $this->formatShort($status_custom_column, $key) : $key;
            };
            $lines[] = strval(PortalDefinition::trans('message.status_changed_body', [
                'from' => $label($status_old),
                'to' => $label($status_to),
            ]));
        }
        if ($comment !== '') {
            $lines[] = $comment;
        }
        if (!empty($reviewers)) {
            // First, and with the names: the bell and the mail are where the
            // asked person learns that this one is theirs to answer.
            array_unshift($lines, strval(PortalDefinition::trans('message.review_request_body', [
                'names' => implode('、', array_column($this->usersFor($reviewers), 'n')),
            ])));
        }
        try {
            $this->ringPortalBells($request, $project, $issue, $bell_mark, implode("\n", $lines), $notice_url);
        } catch (\Throwable $e) {
            // Saved is saved: an error answer now would only get the comment
            // posted a second time.
            Log::error('pm_portal bells for issue ' . $issue->id . ' failed: ' . $e->getMessage(), ['exception' => $e]);
        }

        // The writer has seen what they wrote: their read receipt moves on.
        Star::markRead($issue_name, $issue->id, \Exment::getUserId());

        // The mail and Teams (ISS02863): everyone who got a bell for this write, as each of them
        // chose (or the default of the site); "also by e-mail" mails all of them. After the
        // answer has gone out.
        PortalTeams::afterResponse(
            $issue_name,
            $issue,
            $bell_mark,
            \Exment::getUserId(),
            'updated',
            $this->issueKey($project, $issue->id),
            strval(array_get($issue->value, array_get($this->def['issue'], 'subject'), '')),
            $lines,
            $notice_url,
            $this->wantsMail($request)
        );

        // Answer with the refreshed detail so the screen patches itself -
        // a reload would cost another framework boot for data we already have.
        return $this->issueDetailResponse($project, $issue->id);
    }

    /**
     * The projects this issue may move to: every other project the caller
     * can add issues to that is not archived. GET {id}/issue/{issue_id}/move.
     */
    public function issueMoveTargets(Request $request, $id, $issue_id)
    {
        $project = $this->findProject($id);
        $issue = $this->findIssue($project, $issue_id);
        if ($this->projectIsArchived($project)) {
            return $this->messageError(403, 'archived');
        }
        if (PortalDefinition::enableEdit($issue, true) !== true) {
            return $this->messageError(403, 'no_permission');
        }

        $key_column = array_get($this->def['project'], 'key');
        $name_column = array_get($this->def['project'], 'name', 'name');
        $targets = [];
        foreach ($this->project_table->getValueModel()->get() as $row) {
            if (intval($row->id) === intval($project->id) || $this->projectIsArchived($row)
                || PortalDefinition::enableEdit($row, true) !== true) {
                continue;
            }
            $targets[] = [
                'id' => intval($row->id),
                'name' => strval(array_get($row->value, $name_column, '')),
                'key' => strval(array_get($row->value, $key_column, '')),
            ];
        }
        usort($targets, function ($a, $b) {
            return strcmp($a['name'], $b['name']);
        });

        return response()->json(['ok' => true, 'projects' => $targets]);
    }

    /**
     * Move an issue to another project, as Backlog does. POST
     * {id}/issue/{issue_id}/move with project=<id>.
     *
     * The issue keeps its id, key, comments, files and history. The lists it
     * picks from belong to a project, so its status and type become the new
     * project's ones of the same name (or the new project's first one); its
     * category, milestone, versions and parent issue are the old project's
     * and are taken off; assignees and people to notify who are not on the
     * new project are taken off. PortalRules checks the result when it is
     * saved, like any other write.
     */
    public function moveIssue(Request $request, $id, $issue_id)
    {
        $project = $this->findProject($id);
        $issue = $this->findIssue($project, $issue_id);
        if ($this->projectIsArchived($project)) {
            return $this->messageError(403, 'archived');
        }
        if (PortalDefinition::enableEdit($issue, true) !== true) {
            return $this->messageError(403, 'no_permission');
        }

        $to = $request->get('project');
        if (!is_scalar($to) || !is_numeric($to)) {
            return $this->messageError(400, 'invalid_params');
        }
        if (intval($to) === intval($project->id)) {
            return $this->messageError(400, 'move_same');
        }
        $target = $this->project_table->getValueModel()->find(intval($to));
        if (!isset($target) || $target->enableAccess() !== true) {
            return $this->messageError(404, 'move_no_target');
        }
        if ($this->projectIsArchived($target)) {
            return $this->messageError(403, 'archived');
        }

        $is = (array) $this->def['issue'];
        $extra = (array) array_get($this->def, 'issue_extra', []);
        $changes = [];

        // Status and type: the new project's list item with the same label.
        foreach ([array_get($is, 'status'), array_get($is, 'type')] as $column) {
            if (is_nullorempty($column) || CustomColumn::getEloquent($column, $this->issue_table) === null) {
                continue;
            }
            $now = strval(array_get($issue->value, $column, ''));
            list($old_meta) = $this->selectMeta($this->issue_table, $column, $project->id);
            list($new_meta, $new_order) = $this->selectMeta($this->issue_table, $column, $target->id);
            $label = strval(array_get($old_meta, $now . '.l', ''));
            $pick = null;
            foreach ($new_order as $key) {
                if ($label !== '' && strval(array_get($new_meta, $key . '.l')) === $label) {
                    $pick = $key;
                    break;
                }
            }
            if ($pick === null && $now !== '' && array_key_exists($now, $new_meta)) {
                // a list the whole space shares
                $pick = $now;
            }
            if ($pick === null) {
                $pick = $new_order[0] ?? null;
            }
            $changes[$column] = $pick;
        }

        // The old project's own lists, and the parent issue, do not travel.
        foreach ([array_get($is, 'category'), array_get($is, 'milestone'), array_get($extra, 'versions'), array_get($is, 'parent')] as $column) {
            if (!is_nullorempty($column) && CustomColumn::getEloquent($column, $this->issue_table) !== null
                && !is_nullorempty(array_get($issue->value, $column))) {
                $changes[$column] = null;
            }
        }

        // People who are not on the new project.
        $roles = PortalMembership::roles((array) $target->value);
        if (!empty($roles)) {
            $people = [
                [array_get($is, 'assignees'), PortalMembership::EDITORS],
                [array_get($extra, 'notified_users'), null],
                // Schema 27
                [array_get($extra, 'sub_assignees'), PortalMembership::EDITORS],
                [array_get($extra, 'managers'), null],
                [array_get($extra, 'reviewers'), null],
            ];
            foreach ($people as [$column, $allowed]) {
                if (is_nullorempty($column) || CustomColumn::getEloquent($column, $this->issue_table) === null) {
                    continue;
                }
                $ids = PortalMembership::ids(array_get($issue->value, $column));
                $keep = array_values(array_filter($ids, function ($uid) use ($roles, $allowed) {
                    $role = $roles[$uid] ?? null;
                    return $role !== null && ($allowed === null || in_array($role, $allowed, true));
                }));
                if (count($keep) !== count($ids)) {
                    $changes[$column] = array_map('strval', $keep);
                }
            }
        }

        $oldKey = $this->issueKey($project, $issue->id);
        try {
            \DB::transaction(function () use ($issue, $target, $changes) {
                $issue->setValue($changes);
                $issue->parent_id = $target->id;
                $issue->parent_type = $this->project_table->table_name;
                $issue->save();
            });
            // ISS00248: the key it had still finds it
            PortalIssueAliases::addOld(intval($issue->id), $oldKey);
        } catch (ValidationException $e) {
            return $this->validationError($e);
        } catch (\Throwable $e) {
            Log::error('PmPortal issue move failed: ' . $e->getMessage());

            return $this->messageError(500, 'move_failed');
        }

        return response()->json([
            'ok' => true,
            'url' => $this->issueUrl($target, $issue),
            'message' => strval(PortalDefinition::trans('message.moved', [
                'name' => strval(array_get($target->value, array_get($this->def['project'], 'name', 'name'), '')),
            ])),
        ]);
    }

    /**
     * Whether the caller may delete this issue: the project's manager (and
     * whoever manages every project), or whoever Exment itself lets delete
     * it. Nobody while the project is archived.
     *
     * @param \Exceedone\Exment\Model\CustomValue $project
     * @param \Exceedone\Exment\Model\CustomValue $issue
     */
    protected function canDeleteIssue($project, $issue): bool
    {
        if ($this->projectIsArchived($project)) {
            return false;
        }

        return PortalMembership::canManage($project) || PortalDefinition::enableDelete($issue, true) === true;
    }

    /**
     * Delete an issue. POST {id}/issue/{issue_id}/delete.
     *
     * Its links to other issues and its stars and watches go with it. The
     * issue itself is soft deleted, so an administrator can bring it back
     * from Exment's trash. An issue with sub-issues is refused, as in
     * Backlog: take the sub-issues off first.
     */
    public function deleteIssue(Request $request, $id, $issue_id)
    {
        $project = $this->findProject($id);
        $issue = $this->findIssue($project, $issue_id);
        if ($this->projectIsArchived($project)) {
            return $this->messageError(403, 'archived');
        }
        if (!$this->canDeleteIssue($project, $issue)) {
            return $this->messageError(403, 'delete_issue_manager_only');
        }
        $reason = PortalRules::validateDestroy($issue);
        if ($reason !== null) {
            return PortalDefinition::jsonError(409, 'in_use', ['message' => $reason]);
        }

        $related_table = CustomTable::getEloquent(array_get($this->def, 'related_table'));
        $rel = (array) array_get($this->def, 'related_row', []);
        try {
            \DB::transaction(function () use ($issue, $related_table, $rel) {
                if (isset($related_table)) {
                    $from = $this->columnOf($related_table, strval(array_get($rel, 'from')));
                    $to = $this->columnOf($related_table, strval(array_get($rel, 'to')));
                    if (isset($from) && isset($to)) {
                        $links = $related_table->getValueModel()->newQueryWithoutScopes()
                            ->whereNull('deleted_at')
                            ->where(function ($query) use ($from, $to, $issue) {
                                $query->where($from->getQueryKey(), strval($issue->id))
                                    ->orWhere($to->getQueryKey(), strval($issue->id));
                            })
                            ->get();
                        foreach ($links as $link) {
                            Star::purge($related_table->table_name, $link->id);
                            $link->delete();
                        }
                    }
                }
                Star::purge($this->issue_table->table_name, $issue->id);
                $issue->delete();
            });
        } catch (\Throwable $e) {
            Log::error('PmPortal issue delete failed: ' . $e->getMessage());

            return $this->messageError(500, 'delete_failed');
        }

        return response()->json([
            'ok' => true,
            'message' => strval(PortalDefinition::trans('message.issue_deleted')),
        ]);
    }

    /**
     * Mark an issue read (opened) or unread for the signed-in user.
     *
     * The screen calls this without waiting when an issue is opened, and from
     * the list's right-click menu. Allowed on archived projects too: it
     * changes nothing anybody else reads except the read receipts.
     */
    public function issueRead(Request $request, $id, $issue_id)
    {
        $project = $this->findProject($id);
        $issue = $this->findIssue($project, $issue_id);
        $me = \Exment::getUserId();
        $flag = $request->get('read', '1');
        $read = !in_array(is_scalar($flag) ? strval($flag) : '1', ['0', 'false'], true);

        Star::markRead($this->issue_table->table_name, $issue->id, $me, $read);

        $readers = Star::readers($this->issue_table->table_name, [$issue->id]);
        $reads = [];
        foreach (($readers[strval($issue->id)] ?? []) as $row) {
            $reads[] = ['by' => $row['by'], 'at' => $this->fmtAt($row['ts'])];
        }

        return response()->json([
            'ok' => true,
            'read' => $read,
            'reads' => $reads,
            'users' => $this->usersFor(array_column($reads, 'by')),
        ]);
    }

    /**
     * The portal's script or stylesheet (PortalDefinition::ASSETS). The
     * address carries the content hash, so the answer may be kept for a year.
     */
    public function asset(Request $request, $name)
    {
        $name = strval($name);
        $body = PortalDefinition::assetBody($name);
        if ($body === null) {
            abort(404);
        }
        $etag = '"' . md5($body) . '"';
        $headers = [
            'Content-Type' => PortalDefinition::ASSETS[$name]['type'],
            'Cache-Control' => 'private, max-age=31536000, immutable',
            'ETag' => $etag,
            'X-Content-Type-Options' => 'nosniff',
        ];
        if (trim(strval($request->header('If-None-Match'))) === $etag) {
            return response('', 304, $headers);
        }

        return response($body, 200, $headers);
    }

    /**
     * Mark several issues of the project read or unread for the signed-in
     * user: the list's right-click on checked rows, and the marks a page
     * sends with navigator.sendBeacon as it goes away (ids as "1,2,3").
     * Issues of other projects, or ones the user cannot see, are skipped.
     */
    public function issueReadMany(Request $request, $id)
    {
        $project = $this->findProject($id);
        $flag = $request->get('read', '1');
        $read = !in_array(is_scalar($flag) ? strval($flag) : '1', ['0', 'false'], true);
        $raw = $request->get('ids', '');
        $raw = is_array($raw) ? $raw : explode(',', is_scalar($raw) ? strval($raw) : '');
        $wanted = [];
        foreach ($raw as $v) {
            if (is_scalar($v) && ctype_digit(trim(strval($v)))) {
                $wanted[intval(trim(strval($v)))] = true;
            }
        }
        $wanted = array_slice(array_keys($wanted), 0, self::READ_MANY_LIMIT);
        $ids = empty($wanted) ? [] : $this->issue_table->getValueModel()
            ->where('parent_id', $project->id)
            ->where('parent_type', $this->project_table->table_name)
            ->whereIn('id', $wanted)
            ->pluck('id')
            ->all();
        $count = Star::markReadMany($this->issue_table->table_name, $ids, \Exment::getUserId(), $read);

        return response()->json(['ok' => true, 'read' => $read, 'count' => $count]);
    }

    /**
     * "Mark all read": every issue of the project the user can see.
     */
    public function projectReadAll(Request $request, $id)
    {
        $project = $this->findProject($id);
        $ids = $this->issue_table->getValueModel()
            ->where('parent_id', $project->id)
            ->where('parent_type', $this->project_table->table_name)
            ->pluck('id')
            ->all();
        $count = Star::markReadMany($this->issue_table->table_name, $ids, \Exment::getUserId());

        return response()->json(['ok' => true, 'count' => $count]);
    }

    /**
     * "Mail this issue": send the issue, with a short note, to the people
     * picked on the issue screen, now - no comment or edit needed.
     *
     * Only people who can open the issue get it, and the mails go out after
     * the answer. The sender may pick themselves, to keep a copy.
     */
    public function mailIssue(Request $request, $id, $issue_id)
    {
        $project = $this->findProject($id);
        $issue = $this->findIssue($project, $issue_id);
        $me = intval(\Exment::getUserId());

        // a person sends a few of these an hour, not a stream
        $limitKey = 'exment_pm_mail_issue:' . $me;
        if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($limitKey, self::MAIL_ISSUE_PER_HOUR)) {
            return $this->messageError(429, 'mail_too_many', ['n' => strval(self::MAIL_ISSUE_PER_HOUR)]);
        }
        \Illuminate\Support\Facades\RateLimiter::hit($limitKey, 3600);

        $picked = collect((array) $request->get('users', []))
            ->filter(function ($v) {
                return is_numeric($v) && intval($v) > 0;
            })
            ->map(function ($v) {
                return intval($v);
            })
            ->unique()
            ->take(self::NOTIFY_PICK_LIMIT)
            ->values()
            ->all();
        $targets = array_values(PortalMembership::filterReaders($issue, $picked));
        if (empty($targets)) {
            return $this->messageError(400, 'mail_no_target');
        }

        $note = $request->get('message', '');
        $note = is_scalar($note) ? mb_substr(trim(strval($note)), 0, 2000) : '';
        $sender = \Exment::user();
        $sender_name = $sender && $sender->base_user ? strval($sender->base_user->getLabel()) : '';
        $description = strval(array_get($issue->value, array_get($this->def['issue'], 'description'), ''));
        $description = trim(html_entity_decode(strip_tags(preg_replace('/<br\s*\/?>|<\/p>/i', "\n", $description) ?? ''), ENT_QUOTES, 'UTF-8'));
        if (mb_strlen($description) > 1000) {
            $description = mb_substr($description, 0, 1000) . '…';
        }

        $body = strval(PortalDefinition::trans('mail_issue_from', ['name' => $sender_name]));
        if ($note !== '') {
            $body .= "\n\n" . $note;
        }
        if ($description !== '') {
            $body .= "\n\n----\n" . $description;
        }

        PortalMail::afterResponseTo(
            $targets,
            $issue,
            '[' . $this->issueKey($project, $issue->id) . '] ' . strval(array_get($issue->value, array_get($this->def['issue'], 'subject'), '')),
            $body,
            $this->issueUrl($project, $issue)
        );

        // Picked but not able to open the issue: said, not mailed.
        $skipped = count(array_diff($picked, $targets, [$me]));

        return response()->json(['ok' => true, 'count' => count($targets), 'skipped' => $skipped]);
    }

    /**
     * The issues the user has not seen in their latest state: one they have
     * never opened that somebody else wrote, changed or commented on; one
     * somebody else changed or commented on after the user last opened it;
     * or one the user marked unread by hand. An issue only the user has
     * touched counts as read - they wrote it.
     *
     * Two indexed queries for the whole project whatever its size: the
     * user's read times and the latest comment by others per issue.
     *
     * @param \Illuminate\Support\Collection $issues
     * @param mixed $me
     * @return array<int, true> issue id => true
     */
    protected function unreadIssues($issues, $me): array
    {
        if ($issues->isEmpty()) {
            return [];
        }
        $issue_name = $this->issue_table->table_name;
        $ids = $issues->pluck('id')->map(function ($v) {
            return intval($v);
        })->all();
        $reads = Star::readTimes($issue_name, $ids, $me);
        // Only comments on issues the user can already see (the ids above),
        // so the table's own scope adds nothing but cost here.
        $comments = getModelName(SystemTableName::COMMENT)::withoutGlobalScopes()
            ->whereIn('parent_id', $ids)
            ->where('parent_type', $issue_name)
            ->where('created_user_id', '<>', $me)
            ->groupBy('parent_id')
            ->selectRaw('parent_id, max(created_at) as last_at')
            ->pluck('last_at', 'parent_id')
            ->all();

        $out = [];
        foreach ($issues as $issue) {
            $id = intval($issue->id);
            $read = array_key_exists(strval($id), $reads) ? $reads[strval($id)] : null;
            if ($read === '') {
                $out[$id] = true;   // marked unread by hand
                continue;
            }
            $last = '';
            if (intval($issue->updated_user_id) !== intval($me)) {
                $last = strval($issue->updated_at ?? $issue->created_at);
            }
            $comment_at = strval($comments[$id] ?? '');
            if ($comment_at > $last) {
                $last = $comment_at;
            }
            if ($last !== '' && ($read === null || $last > $read)) {
                $out[$id] = true;
            }
        }

        return $out;
    }

    /**
     * Whether the writer ticked "also send by e-mail".
     */
    public function wantsMail(Request $request): bool
    {
        $flag = $request->input('notify_mail');

        return is_scalar($flag) && in_array(strval($flag), ['1', 'true', 'on'], true);
    }

    /**
     * Whether the writer ticked "ask them to confirm" (ISS00341).
     */
    public function wantsReview(Request $request): bool
    {
        $flag = $request->input('review');

        return is_scalar($flag) && in_array(strval($flag), ['1', 'true', 'on'], true);
    }

    /**
     * The people the writer picked in the dock (notify_users), as user ids:
     * who the bell rings for, and who a review request asks. Capped, see
     * NOTIFY_PICK_LIMIT; whether each may read the issue is the caller's to
     * check (filterReaders).
     *
     * @return array<int, int>
     */
    protected function pickedUsers(Request $request): array
    {
        return collect((array) $request->get('notify_users', []))
            ->filter(function ($v) {
                return is_numeric($v) && intval($v) > 0;
            })
            ->map(function ($v) {
                return intval($v);
            })
            ->unique()
            ->take(self::NOTIFY_PICK_LIMIT)
            ->values()
            ->all();
    }

    /**
     * Where one issue opens in the portal, as a full URL for a mail or a
     * redirect: the project's page by its code, with the issue key after the
     * # (/plugins/exment_pm/EXMENT_CUSTOMER#issue-ISS00239, ISS00440).
     *
     * The short /view/KEY links of earlier mails still open (viewIssue()).
     *
     * @param \Exceedone\Exment\Model\CustomValue $project
     * @param \Exceedone\Exment\Model\CustomValue|mixed $issue the issue, or its id
     */
    public function issueUrl($project, $issue): string
    {
        if (!$issue instanceof CustomValue) {
            $issue = $this->issue_table->getValueModel()->newQueryWithoutScopes()->find($issue)
                ?? (object) ['id' => $issue, 'value' => []];
        }

        return PortalDefinition::issuePageUrl($project, $issue);
    }

    /**
     * Everyone an issue's detail names: the timeline's people and its readers.
     *
     * @param array<string, mixed> $detail
     * @return array<int, int>
     */
    protected function detailUserIds(array $detail): array
    {
        // The people asked to confirm a comment are named under it, and need
        // not have written or opened anything on the issue.
        $reviewers = collect(array_get($detail, 'timeline', []))->flatMap(function ($row) {
            return array_column((array) array_get($row, 'rv', []), 'by');
        });

        return collect(array_get($detail, 'timeline', []))->pluck('by')
            ->merge(collect(array_get($detail, 'reads', []))->pluck('by'))
            ->merge($reviewers)
            ->filter()->map(function ($v) {
                return intval($v);
            })->unique()->values()->all();
    }

    /**
     * The portal's own bell for one update: whoever the poster picked, plus
     * everyone watching the issue - but only people who can read the issue.
     *
     * A bell carries the issue's title and the comment, and Exment's
     * notification page shows both without asking whether the reader may open
     * the issue. A watcher taken off the project, or any user id typed into
     * the request, would otherwise go on reading. Whoever Exment already rang
     * for this same write (rows past $bell_mark) is skipped as well: one
     * comment is one event, and one bell.
     *
     * Each bell is its own insert in its own try. The comment is saved by now,
     * and a failed bell must cost neither the other bells nor the answer - an
     * error here would only invite posting the same comment again.
     *
     * @param \Exceedone\Exment\Model\CustomValue $project
     * @param \Exceedone\Exment\Model\CustomValue $issue
     */
    protected function ringPortalBells(Request $request, $project, $issue, int $bell_mark, string $body, ?string $url = null): void
    {
        $me = intval(\Exment::getUserId());
        $issue_name = $this->issue_table->table_name;

        $watchers = Star::userIds(Star::TYPE_WATCH, $issue_name, $issue->id);
        $candidates = collect($this->pickedUsers($request))->merge($watchers)->unique()->values()->all();
        if (empty($candidates)) {
            return;
        }

        $rung = NotifyNavbar::withoutGlobalScopes()
            ->where('id', '>', $bell_mark)
            ->where('parent_type', $issue_name)
            ->where('parent_id', $issue->id)
            ->where('trigger_user_id', $me)
            ->pluck('target_user_id')
            ->map(function ($v) {
                return intval($v);
            })
            ->all();

        $targets = array_diff(PortalMembership::filterReaders($issue, $candidates), [$me], $rung);
        if (empty($targets)) {
            return;
        }

        $subject = PortalDefinition::notifySubject('[' . $this->issueKey($project, $issue->id) . '] '
            . strval(array_get($issue->value, array_get($this->def['issue'], 'subject'), '')));
        // the issue's address under the text: on the notification's own page
        // it is the way to the issue
        $body = PortalDefinition::notifyBodyWithLink($body, $url ?? $this->issueUrl($project, $issue));
        foreach ($targets as $target_user_id) {
            try {
                $notify = new NotifyNavbar();
                $notify->fill([
                    'notify_id' => 0,
                    'parent_id' => $issue->id,
                    'parent_type' => $issue_name,
                    'target_user_id' => $target_user_id,
                    'notify_subject' => $subject,
                    'notify_body' => $body,
                    'trigger_user_id' => $me,
                ]);
                $notify->save();
            } catch (\Throwable $e) {
                Log::warning('pm_portal bell to user ' . $target_user_id . ' on issue ' . $issue->id . ' failed: ' . $e->getMessage(), ['exception' => $e]);
            }
        }
    }

    /**
     * What Exment's detail screen does after it stores a comment or a file
     * (DefaultShow::addComment, fileupload): every notification of the issue
     * table that listens for this kind of save gets to run. Without it the
     * issue's own "comment" notification never heard about anything posted
     * through the portal.
     *
     * One that fails is logged and skipped - what it reports on is saved.
     *
     * @param \Exceedone\Exment\Model\CustomValue $issue
     * @param string $saved_type NotifySavedType::COMMENT or ::ATTACHMENT
     * @param array<string, mixed> $options what Exment passes along: comment / attachment
     */
    protected function runTableNotifies($issue, string $saved_type, array $options): void
    {
        foreach ($this->issue_table->notifies as $notify) {
            try {
                $notify->notifyCreateUpdateUser($issue, $saved_type, $options);
            } catch (\Throwable $e) {
                Log::error('pm_portal ' . $saved_type . ' notify ' . $notify->id . ' failed for issue ' . $issue->id . ': ' . $e->getMessage(), ['exception' => $e]);
            }
        }
    }

    /**
     * The whole issue, freshly read, as the comment screens expect it.
     *
     * Every write on this screen - a comment, an edit, a delete, an upload -
     * answers with this, so the browser repaints from the answer instead of
     * reloading the page. A reload here would cost another ~400ms framework
     * boot to fetch data the response already carries.
     *
     * @param \Exceedone\Exment\Model\CustomValue $project
     * @param mixed $issue_id
     * @return \Illuminate\Http\JsonResponse
     */
    protected function issueDetailResponse($project, $issue_id)
    {
        return response()->json(array_merge(['ok' => true], $this->issueDetailBody($project, $issue_id)));
    }

    /**
     * The same issue detail without the ok flag, so that a refusal can carry
     * it as well - postComment's "stale" answer does.
     *
     * @param \Exceedone\Exment\Model\CustomValue $project
     * @param mixed $issue_id
     * @return array<string, mixed>
     */
    protected function issueDetailBody($project, $issue_id): array
    {
        $me = \Exment::getUserId();
        $issue = $this->findIssue($project, $issue_id);
        $issue_name = $this->issue_table->table_name;
        $detail = $this->issueDetailData($project, $issue, $me);
        $star_counts = Star::counts(Star::TYPE_STAR, $issue_name, [$issue->id]);
        $my_stars = Star::mine(Star::TYPE_STAR, $issue_name, [$issue->id], $me);
        $detail['issue']['star'] = intval(array_get($star_counts, strval($issue->id), 0));
        $detail['issue']['my_star'] = in_array(strval($issue->id), $my_stars);
        // ISS00272: the user's own favorite
        $detail['issue']['my_fav'] = !empty(Star::mine(Star::TYPE_FAV, $issue_name, [$issue->id], $me));
        // ISS00248
        $detail['issue']['al'] = PortalIssueAliases::names([$issue->id])[intval($issue->id)] ?? '';

        return array_merge($detail, [
            'users' => $this->usersFor($this->detailUserIds($detail)),
        ]);
    }

    /**
     * Attach files to an issue and say where they landed.
     *
     * Exment stores attachments the same way through its own fileupload
     * action, but that one answers "result: true" and nothing else. The
     * comment box needs the address back: pasting a screenshot is only
     * convenient if the picture appears in the comment being written, and
     * the markup for it cannot be composed without the URL.
     */
    public function uploadIssueFile(Request $request, $id, $issue_id)
    {
        $project = $this->findProject($id);
        $issue = $this->findIssue($project, $issue_id);

        // The questions Exment's detail screen asks before it offers its upload
        // box at all (DefaultShow::useFileUpload): may this user edit the
        // issue, and does the table take attachments - plus the portal's own,
        // whether the project is still open. Exment's fileupload endpoint asks
        // for read access alone, which let a project's viewers attach files;
        // a viewer may still comment, just not attach.
        if ($this->projectIsArchived($project)) {
            return $this->messageError(403, 'archived');
        }
        if (PortalDefinition::enableEdit($issue) !== true) {
            return $this->messageError(403, 'no_permission');
        }
        if (!boolval($this->issue_table->getOption('attachment_flg') ?? true)) {
            return $this->messageError(403, 'attachment_off');
        }

        $httpfiles = $request->file('file_data');
        if (is_nullorempty($httpfiles)) {
            return $this->messageError(400, 'no_file');
        }

        $out = [];
        $failed = 0;
        foreach (toArray($httpfiles) as $httpfile) {
            // Not valid means PHP never got the whole file - most often one
            // larger than upload_max_filesize.
            if (!isset($httpfile) || !$httpfile->isValid()) {
                continue;
            }
            $filename = $httpfile->getClientOriginalName();
            try {
                $file = ExmentFile::storeAs(FileType::CUSTOM_VALUE_DOCUMENT, $httpfile, $this->issue_table->table_name, $filename)
                    ->saveCustomValue($issue->id, null, $this->issue_table);
                $file->saveDocumentModel($issue, $filename);
            } catch (\Throwable $e) {
                Log::error('pm_portal upload to issue ' . $issue->id . ' failed: ' . $e->getMessage(), ['exception' => $e]);
                $failed++;
                continue;
            }
            // saveDocumentModel saves the document with notifications off, so
            // the bell Exment's detail screen rings for a dropped file has to be
            // rung here, file by file, the way that screen does it.
            $this->runTableNotifies($issue, NotifySavedType::ATTACHMENT, ['attachment' => $filename]);
            $out[] = [
                'name' => $filename,
                'url' => admin_url('files', [$file->uuid]),
                'img' => static::isImageName($filename),
            ];
        }

        if (empty($out)) {
            return $failed > 0 ? $this->messageError(500, 'upload_failed') : $this->messageError(400, 'upload_invalid');
        }

        return response()->json(['ok' => true, 'files' => $out]);
    }

    /**
     * The address of one comment: the issue's page with ?c=<id> on its hash
     * (#issue-KEY?c=12). The hash is the portal's router, so the comment
     * rides in its query; the screen scrolls to the comment and marks it.
     */
    public static function commentUrl(string $issue_url, $comment_id): string
    {
        return $issue_url . '?c=' . rawurlencode(strval($comment_id));
    }

    /**
     * Which issue a comment of this project hangs off, for an address that
     * names only the comment (#comment-12). GET {id}/comment/{comment_id}.
     */
    public function commentIssue(Request $request, $id, $comment_id)
    {
        $project = $this->findProject($id);
        $comment = ctype_digit(strval($comment_id)) ? getModelName(SystemTableName::COMMENT)::find($comment_id) : null;
        if (!isset($comment) || strval($comment->parent_type) !== $this->issue_table->table_name) {
            return $this->messageError(404, 'not_found');
        }
        // 404 when the issue is not this project's or the user cannot see it
        $issue = $this->findIssue($project, $comment->parent_id);

        return response()->json([
            'ok' => true,
            'issue' => intval($issue->id),
            'comment' => intval($comment->id),
            'url' => static::commentUrl($this->issueUrl($project, $issue), $comment->id),
        ]);
    }

    /**
     * Rewrite one of your own comments.
     */
    public function updateComment(Request $request, $id, $comment_id)
    {
        $project = $this->findProject($id);
        $comment = $this->ownComment($project, $comment_id);
        if (!isset($comment)) {
            return $this->messageError(403, 'no_permission');
        }
        if ($this->projectIsArchived($project)) {
            return $this->messageError(403, 'archived');
        }

        $text = $request->get('comment', '');
        $text = is_scalar($text) ? trim(strval($text)) : '';
        if ($text === '') {
            return $this->messageError(400, 'comment_empty');
        }
        if (mb_strlen($text) > PortalDefinition::maxBody()) {
            return $this->messageError(422, 'too_long', ['max' => number_format(PortalDefinition::maxBody())]);
        }

        try {
            $comment->setValue(['comment_detail' => $text]);
            $comment->save();
        } catch (\Throwable $e) {
            Log::error('pm_portal comment ' . $comment->id . ' update failed: ' . $e->getMessage(), ['exception' => $e]);

            return PortalDefinition::jsonError(500, 'save_failed');
        }

        // No second notification: Backlog does not ring the bell again for an
        // edit, and the people already told have the old text in their mail.
        return $this->issueDetailResponse($project, $comment->parent_id);
    }

    /**
     * Remove one of your own comments.
     */
    public function deleteComment(Request $request, $id, $comment_id)
    {
        $project = $this->findProject($id);
        $comment = $this->ownComment($project, $comment_id);
        if (!isset($comment)) {
            return $this->messageError(403, 'no_permission');
        }
        if ($this->projectIsArchived($project)) {
            return $this->messageError(403, 'archived');
        }

        $issue_id = $comment->parent_id;
        try {
            $comment->delete();
        } catch (\Throwable $e) {
            Log::error('pm_portal comment ' . $comment->id . ' delete failed: ' . $e->getMessage(), ['exception' => $e]);

            return PortalDefinition::jsonError(500, 'delete_failed');
        }
        // After the delete, so a delete that failed keeps its stars.
        Star::purge('comment', $comment->id);

        return $this->issueDetailResponse($project, $issue_id);
    }

    /**
     * Set the alias of an issue, or take it off (ISS00248): POST {id}/issue/{issue_id}/alias
     * alias=... Whoever may edit the issue. Not one that names another issue already, nor one
     * shaped like a key (ends in -<number>) or that is an issue key of the table.
     */
    public function setIssueAlias(Request $request, $id, $issue_id)
    {
        $project = $this->findProject($id);
        $issue = $this->findIssue($project, $issue_id);
        if (!PortalIssueAliases::exists()) {
            abort(404);
        }
        if ($this->projectIsArchived($project)) {
            return $this->messageError(403, 'archived');
        }
        if (PortalDefinition::enableEdit($issue, true) !== true) {
            return $this->messageError(403, 'no_permission');
        }
        $alias = trim(strval($request->input('alias', '')));
        if ($alias !== '') {
            if (preg_match('/-\d+$/', $alias)) {
                return $this->messageError(422, 'alias_keylike');
            }
            foreach ([PortalSchema::ISSUE_COLUMNS['source_key'], array_get($this->def['issue'], 'key', 'issue_key')] as $column) {
                if (is_nullorempty($column) || CustomColumn::getEloquent($column, $this->issue_table) === null) {
                    continue;
                }
                $hit = $this->issue_table->getValueModel()->query()->withoutGlobalScopes()
                    ->whereIn('value->' . $column, array_unique([$alias, strtoupper($alias), strtolower($alias)]))
                    ->where('id', '<>', $issue->id)->exists();
                if ($hit) {
                    return $this->messageError(422, 'alias_taken');
                }
            }
        }
        $why = PortalIssueAliases::setName(intval($issue->id), $alias, intval(\Exment::getUserId()));
        if ($why !== null) {
            return $this->messageError(422, $why);
        }

        return $this->issueDetailResponse($project, $issue->id);
    }

    /**
     * Hide a change from the history of an issue (ISS02924): a value put in by mistake. POST
     * {id}/issue/{issue_id}/history/{revision_id}/hide. The one who made the change may, and the
     * manager of the project. The revision is kept, marked deleted (who and when), so Exment's own
     * screen of the revisions still has it; the portal no longer shows it.
     */
    public function hideRevision(Request $request, $id, $issue_id, $revision_id)
    {
        $project = $this->findProject($id);
        $issue = $this->findIssue($project, $issue_id);
        if ($this->projectIsArchived($project)) {
            return $this->messageError(403, 'archived');
        }
        $rev = \DB::table('revisions')
            ->where('id', intval($revision_id))
            ->where('revisionable_type', $this->issue_table->table_name)
            ->where('revisionable_id', intval($issue->id))
            ->whereNull('deleted_at')
            ->first();
        if (!isset($rev)) {
            return $this->messageError(404, 'not_found');
        }
        $me = intval(\Exment::getUserId());
        if (intval($rev->create_user_id) !== $me && !PortalMembership::canManage($project)) {
            return $this->messageError(403, 'no_permission');
        }
        \DB::table('revisions')->where('id', $rev->id)->update(['deleted_at' => now(), 'delete_user_id' => $me]);
        Log::info('pm_portal: revision ' . $rev->id . ' of issue ' . $issue->id . ' hidden by user ' . $me);

        return $this->issueDetailResponse($project, $issue->id);
    }

    /**
     * Confirm a review request on a comment, or take the confirmation back
     * (ISS00341). POST {id}/comment/{comment_id}/review with ok=1 or ok=0.
     *
     * Only somebody the comment's writer asked can answer, and only for
     * themselves: the row is found by the caller's own user id, so there is
     * no id in the request to put somebody else's name under. Taking it back
     * is allowed - a button pressed on the wrong comment has to be undoable -
     * and the time shown is always that of the last confirmation.
     */
    public function reviewComment(Request $request, $id, $comment_id)
    {
        $project = $this->findProject($id);
        $comment = getModelName(SystemTableName::COMMENT)::find($comment_id);
        if (!isset($comment) || strval($comment->parent_type) !== $this->issue_table->table_name) {
            abort(404);
        }
        // Throws when the issue it hangs off is not this project's, or is one
        // the caller cannot read.
        $issue = $this->findIssue($project, $comment->parent_id);
        // A confirmation is part of the conversation - the others read it -
        // so an archived project takes none, as it takes no comment.
        if ($this->projectIsArchived($project)) {
            return $this->messageError(403, 'archived');
        }

        $ok = $request->input('ok');
        $ok = !is_scalar($ok) || !in_array(strval($ok), ['0', 'false', 'off', ''], true);
        $me = intval(\Exment::getUserId());
        $was = in_array(strval($comment->id), Star::pendingReviews('comment', $me), true);
        if (!Star::setReview('comment', $comment->id, $me, $ok)) {
            return $this->messageError(403, 'review_not_asked');
        }

        // Tell whoever asked, once per confirmation actually given: that is
        // the answer they were waiting for. Not on taking it back, and not
        // when a second click found it confirmed already.
        if ($ok && $was) {
            try {
                $this->ringReviewDone($project, $issue, $comment, $me);
            } catch (\Throwable $e) {
                Log::warning('pm_portal review bell for comment ' . $comment->id . ' failed: ' . $e->getMessage(), ['exception' => $e]);
            }
        }

        return $this->issueDetailResponse($project, $issue->id);
    }

    /**
     * The bell that tells a comment's writer that somebody they asked has
     * confirmed it. Only while the writer can still read the issue - a bell
     * shows the issue's title and the comment to whoever receives it.
     *
     * @param \Exceedone\Exment\Model\CustomValue $project
     * @param \Exceedone\Exment\Model\CustomValue $issue
     * @param \Exceedone\Exment\Model\CustomValue $comment
     */
    protected function ringReviewDone($project, $issue, $comment, int $me): void
    {
        $asker = intval($comment->created_user_id);
        if ($asker <= 0 || $asker === $me || empty(PortalMembership::filterReaders($issue, [$asker]))) {
            return;
        }
        $notify = new NotifyNavbar();
        $notify->fill([
            'notify_id' => 0,
            'parent_id' => $issue->id,
            'parent_type' => $this->issue_table->table_name,
            'target_user_id' => $asker,
            'notify_subject' => PortalDefinition::notifySubject('[' . $this->issueKey($project, $issue->id) . '] '
                . strval(array_get($issue->value, array_get($this->def['issue'], 'subject'), ''))),
            // What was confirmed, under the sentence saying so: the writer may
            // have asked about several comments on the same issue.
            'notify_body' => PortalDefinition::notifyBodyWithLink(
                strval(PortalDefinition::trans('message.review_done_body')) . "\n\n"
                . strval(array_get($comment->value, 'comment_detail', '')),
                $this->issueUrl($project, $issue)
            ),
            'trigger_user_id' => $me,
        ]);
        $notify->save();
    }

    /**
     * The issues, out of the given ones, that carry a comment this user has
     * been asked to confirm and has not yet (ISS00341) - the list's 確認待ち.
     *
     * Two indexed reads: the user's pending requests (few - they go away as
     * they are answered), then the comments those sit on. Going through the
     * comment table also drops a request whose comment was deleted somewhere
     * that did not purge it.
     *
     * @param array<int, mixed> $issue_ids
     * @param mixed $me
     * @return array<int, bool> issue id => true
     */
    protected function reviewPendingIssues(array $issue_ids, $me): array
    {
        $comment_ids = Star::pendingReviews('comment', $me);
        if (empty($issue_ids) || empty($comment_ids)) {
            return [];
        }
        $out = [];
        $parents = getModelName(SystemTableName::COMMENT)::whereIn('id', $comment_ids)
            ->where('parent_type', $this->issue_table->table_name)
            ->pluck('parent_id');
        $wanted = array_flip(array_map('intval', $issue_ids));
        foreach ($parents as $parent_id) {
            if (isset($wanted[intval($parent_id)])) {
                $out[intval($parent_id)] = true;
            }
        }

        return $out;
    }

    /**
     * A refusal in the shape every portal screen reads - ok false, the code in
     * `error`, the sentence to show in `message` - worded by the portal's own
     * message.* line of the same name. A key with no such line still reads as
     * Japanese, through api_error.
     *
     * @param array<string, mixed> $replace
     */
    protected function messageError(int $status, string $key, array $replace = []): JsonResponse
    {
        $text = PortalDefinition::trans('message.' . $key, $replace);
        $extra = PortalDefinition::isUnresolved($text) ? [] : ['message' => strval($text)];

        return PortalDefinition::jsonError($status, $key, $extra, $replace);
    }

    /**
     * A save that PortalRules refused, as a 422 worded by the rule itself. Its
     * sentence is already Japanese and names what to change, which beats the
     * generic line; that one is only the fallback.
     */
    protected function validationError(ValidationException $e): JsonResponse
    {
        $text = $this->validationText($e);

        return PortalDefinition::jsonError(422, 'validation', $text !== null ? ['message' => $text] : []);
    }

    /**
     * The first sentence a ValidationException carries, or null.
     */
    protected function validationText(ValidationException $e): ?string
    {
        foreach ($e->errors() as $messages) {
            foreach ((array) $messages as $message) {
                if (is_string($message) && trim($message) !== '' && !PortalDefinition::isUnresolved($message)) {
                    return $message;
                }
            }
        }

        return null;
    }

    /**
     * Whether the project is archived - finished business, which Backlog keeps
     * read-only. PortalRules refuses any change to its issues when they are
     * saved; the comment, attachment and bulk routes ask up front, so a person
     * gets one clear answer instead of a write that stops half way.
     *
     * @param \Exceedone\Exment\Model\CustomValue $project
     */
    protected function projectIsArchived($project): bool
    {
        $column = strval(array_get($this->def, 'project_extra.archived', 'archived'));
        if (CustomColumn::getEloquent($column, $this->project_table) === null) {
            return false;
        }

        return boolval(array_get($project->value, $column));
    }

    /**
     * A comment of this project's, written by whoever is asking.
     *
     * Being the author is the only permission that counts here: Backlog lets
     * the writer rewrite or remove what they wrote and nobody else, however
     * senior their role on the project. Returns null when somebody else owns
     * it; a comment outside this project is a 404 through findIssue.
     *
     * @param \Exceedone\Exment\Model\CustomValue $project
     * @param mixed $comment_id
     * @return \Exceedone\Exment\Model\CustomValue|null
     */
    protected function ownComment($project, $comment_id)
    {
        $comment = getModelName(SystemTableName::COMMENT)::find($comment_id);
        if (!isset($comment) || strval($comment->parent_type) !== $this->issue_table->table_name) {
            abort(404);
        }
        // throws when the issue it hangs off is not this project's
        $this->findIssue($project, $comment->parent_id);

        return intval($comment->created_user_id) === intval(\Exment::getUserId()) ? $comment : null;
    }

    /**
     * Whether a file name is one a browser can draw inline.
     *
     * @param string $name
     * @return bool
     */
    protected static function isImageName(string $name): bool
    {
        return (bool) preg_match('/\.(png|jpe?g|gif|webp|bmp|svg)$/i', $name);
    }

    /**
     * Confirm a star/watch target really belongs to the project in the route.
     *
     * The route id says what the caller is allowed to touch. The target id
     * arrives in the request body and nothing else looks at it, so without
     * this a user who can open one project could watch an issue in another -
     * and a watch is a subscription, so it would keep reporting on a record
     * they are not allowed to read.
     *
     * Aborts 404/403 the way the rest of this controller does. Starring is
     * open to anyone who can read the target, so authorship is not consulted
     * here the way ownComment() consults it for editing.
     *
     * @param \Exceedone\Exment\Model\CustomValue $project
     * @param string $target_type
     * @param mixed $target_id
     * @return void
     */
    protected function assertStarTarget($project, string $target_type, $target_id)
    {
        if ($target_type === 'comment') {
            $comment = getModelName(SystemTableName::COMMENT)::find($target_id);
            if (!isset($comment) || strval($comment->parent_type) !== $this->issue_table->table_name) {
                abort(404);
            }
            // Throws when the issue it hangs off is not this project's.
            $this->findIssue($project, $comment->parent_id);

            return;
        }

        if ($target_type === $this->issue_table->table_name) {
            $this->findIssue($project, $target_id);

            return;
        }

        // Wiki pages hang off the project the same way issues do.
        $wiki_table = CustomTable::getEloquent(strval($this->def['wiki_table'] ?? ''));
        if (!isset($wiki_table)) {
            abort(404);
        }
        $page = $wiki_table->getValueModel()->find($target_id);
        if (!isset($page) || intval($page->parent_id) !== intval($project->id)
            || strval($page->parent_type) !== $this->project_table->table_name) {
            abort(404);
        }
        if ($page->enableAccess() !== true) {
            abort(403);
        }
    }

    /**
     * Flip a star or a watch.
     *
     * Stars and watches stay open on an archived project: they change nothing
     * on it that anybody else reads.
     */
    public function toggleStar(Request $request, $id)
    {
        if (!isset($this->project_table) || !isset($this->issue_table)) {
            abort(404);
        }

        $star_type = strval($request->get('star_type'));
        $target_type = strval($request->get('target_type'));
        $allowed_types = [Star::TYPE_STAR, Star::TYPE_WATCH, Star::TYPE_FAV];
        $allowed_targets = array_filter([
            $this->issue_table->table_name,
            $this->project_table->table_name,
            $this->def['wiki_table'],
            'comment',
        ]);
        // Reactions (ISS00259) go on an issue or a comment only.
        $reaction = Star::isReaction($star_type);
        if ($reaction && !in_array($target_type, [$this->issue_table->table_name, 'comment'], true)) {
            return $this->messageError(400, 'invalid_params');
        }
        if ((!$reaction && !in_array($star_type, $allowed_types)) || !in_array($target_type, $allowed_targets)) {
            return $this->messageError(400, 'invalid_params');
        }
        // a favorite is of an issue, and nobody learns how many people have one (ISS00272)
        if ($star_type === Star::TYPE_FAV && $target_type !== $this->issue_table->table_name) {
            return $this->messageError(400, 'invalid_params');
        }
        $private = $star_type === Star::TYPE_FAV;
        $target_id = $request->get('target_id');
        $target_id = is_scalar($target_id) ? strval($target_id) : '';
        $me = \Exment::getUserId();

        // Taking your own star or watch off asks for nothing more. A watch is a
        // subscription, and someone taken off the project has to be able to
        // end it - by then findProject() refuses them. The row is theirs; the
        // answer adds only how many marks the target still carries, on a
        // target they could read when they marked it.
        if ($target_id !== '' && !empty(Star::mine($star_type, $target_type, [$target_id], $me))) {
            $result = Star::toggle($star_type, $target_type, $target_id, $me);
            if ($private) {
                unset($result['count']);
            }
            if ($reaction) {
                $result['users'] = Star::userIds($star_type, $target_type, $target_id);
            }

            return response()->json(['ok' => true] + $result);
        }

        // Putting one on needs the target to be readable here.
        $project = $this->findProject($id);

        // Starring the project itself is the one target whose id is fixed by
        // the route: {id} has already been checked for access, so accepting a
        // different id here would let one project's URL star another.
        if ($target_type === $this->project_table->table_name) {
            if ($target_id !== strval($id)) {
                return $this->messageError(400, 'invalid_params');
            }
        } else {
            $this->assertStarTarget($project, $target_type, $target_id);
        }

        $result = Star::toggle($star_type, $target_type, $target_id, $me);
        if ($reaction) {
            // Who is on it now, so the chip's names are right without a reload.
            $result['users'] = Star::userIds($star_type, $target_type, $target_id);
        }
        if ($private) {
            unset($result['count']);
        }

        return response()->json(['ok' => true] + $result);
    }

    /**
     * Has anything moved in this project since the screen was drawn?
     *
     * The page holds the whole project in memory, so once it is open it can
     * go stale for hours without ever noticing. Reloading on every tab click
     * would answer that, but at the price of a full payload per click - the
     * expensive thing - to learn that nothing changed, which is the usual
     * answer. This is the cheap half of the question: one aggregate over an
     * indexed column. The screen asks it when it regains focus, and only
     * fetches the payload when the answer differs.
     */
    public function projectStamp(Request $request, $id)
    {
        $project = $this->findProject($id);

        return response()->json(['ok' => true, 'stamp' => $this->projectStampValue($project)]);
    }

    /**
     * ISS00416: where Exment Plugin Groupware shows an attachment read only
     * in ibisOffice (its view/{uuid}), or null when it is not installed, is
     * off, or is older than the version that has the page. The file is read
     * from its usual download address there, so who may see it does not change.
     */
    protected function officeViewBase(): ?string
    {
        try {
            $plugin = \Exceedone\Exment\Model\Plugin::where('plugin_name', 'ExmentGroupware')->where('active_flg', 1)->first();
            if (!isset($plugin) || version_compare(strval($plugin->version), '0.3.0', '<')) {
                return null;
            }

            return rtrim(strval($plugin->getFullUrl('view')), '/');
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * The payload the page was built from, on its own.
     *
     * Same work as show() minus the admin layout, the blade render and the
     * kanban / gantt widgets, so a refresh costs a good deal less than the
     * page load it replaces - and never loses what the user had typed.
     */
    public function projectData(Request $request, $id)
    {
        if (PortalSetup::needsSetup()) {
            return $this->messageError(409, 'setup_required');
        }
        $project = $this->findProject($id);

        return response()->json(['ok' => true, 'payload' => $this->buildPayload($project)]);
    }

    /**
     * One field, set on many issues at once.
     *
     * Every row is saved through the ordinary model path rather than with a
     * mass update: validators, plugin events, revisions and notifications all
     * have to fire, or a bulk edit would leave the project with history that
     * has holes in it exactly where the most rows changed. Permission is
     * checked per row, and a row the caller may not edit is counted and
     * skipped rather than failing the whole request - a selection of forty
     * that contains one locked issue should still move the other thirty-nine.
     *
     * The same holds for a row whose save is refused - PortalRules checks the
     * whole stored row, so a sub-issue left over from before sub-issues were
     * switched off refuses any edit. It is reported in `failed` with the
     * rule's own sentence and the rest are saved: each row is its own save, so
     * stopping at the first refusal used to leave the earlier rows changed and
     * the answer saying nothing about them.
     */
    public function bulkUpdate(Request $request, $id)
    {
        $project = $this->findProject($id);

        $ids = collect((array) $request->get('ids', []))->map(function ($v) {
            return is_scalar($v) ? intval($v) : 0;
        })->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return $this->messageError(400, 'invalid_params');
        }
        if ($ids->count() > self::BULK_LIMIT) {
            return $this->messageError(400, 'bulk_too_many', ['max' => self::BULK_LIMIT]);
        }
        // Every row would be refused one by one with the same sentence.
        if ($this->projectIsArchived($project)) {
            return $this->messageError(403, 'archived');
        }

        list($set, $error) = $this->bulkValues($project, (array) $request->get('set', []));
        if ($error !== null) {
            return PortalDefinition::jsonError(400, 'invalid_params', ['message' => $error]);
        }
        if (empty($set)) {
            return $this->messageError(400, 'bulk_nothing');
        }

        $issues = $this->issue_table->getValueModel()->query()
            ->where('parent_id', $project->id)
            ->where('parent_type', $this->project_table->table_name)
            ->whereIn('id', $ids->all())
            ->get();

        $updated = 0;
        $denied = 0;
        $failed = [];
        foreach ($issues as $issue) {
            if (PortalDefinition::enableEdit($issue, true) !== true) {
                $denied++;
                continue;
            }
            try {
                $issue->setValue($set)->save();
                $updated++;
            } catch (ValidationException $e) {
                $failed[] = [
                    'id' => $issue->id,
                    'key' => $this->issueKey($project, $issue->id),
                    'message' => $this->validationText($e) ?? PortalDefinition::errorMessage('validation'),
                ];
            } catch (\Throwable $e) {
                Log::error('pm_portal bulk update of issue ' . $issue->id . ' failed: ' . $e->getMessage(), ['exception' => $e]);
                $failed[] = [
                    'id' => $issue->id,
                    'key' => $this->issueKey($project, $issue->id),
                    'message' => PortalDefinition::errorMessage('save_failed'),
                ];
            }
        }

        return response()->json([
            'ok' => true,
            'updated' => $updated,
            'denied' => $denied,
            // Rows that were tried and refused, each with the sentence to show.
            'failed' => $failed,
            // Ids the caller sent that this project is not carrying. Worth
            // reporting separately from a permission refusal: it usually
            // means the list on screen is older than the project.
            'missing' => $ids->count() - $issues->count(),
            'stamp' => $this->projectStampValue($project),
        ]);
    }

    /**
     * Turn the short keys the screen speaks into real columns, refusing any
     * value the project does not actually offer.
     *
     * An empty string clears a field, but only where clearing is meaningful:
     * an issue with no assignee or no milestone is ordinary, an issue with no
     * status is not.
     *
     * @param \Exceedone\Exment\Model\CustomValue $project
     * @param array<string, mixed> $set
     * @return array{0: array<string, mixed>, 1: string|null}
     */
    protected function bulkValues($project, array $set)
    {
        $is = $this->def['issue'];
        $out = [];

        foreach (['st' => 'status', 'ty' => 'type', 'pr' => 'priority'] as $key => $role) {
            if (!array_key_exists($key, $set)) {
                continue;
            }
            $column = array_get($is, $role);
            $value = strval($set[$key]);
            if (is_nullorempty($column) || $value === '') {
                return [[], PortalDefinition::trans('message.invalid_params')];
            }
            list($meta) = $this->selectMeta($this->issue_table, $column, $project);
            if (!array_key_exists($value, $meta)) {
                return [[], PortalDefinition::trans('message.invalid_params')];
            }
            $out[$column] = $value;
        }

        if (array_key_exists('ms', $set)) {
            $column = array_get($is, 'milestone');
            $value = strval($set['ms']);
            if (is_nullorempty($column)) {
                return [[], PortalDefinition::trans('message.invalid_params')];
            }
            if ($value !== '' && !$this->bulkLookupOk(
                $this->def['milestone_table'],
                array_get($this->def['milestone'], 'project'),
                $project->id,
                [$value]
            )) {
                return [[], PortalDefinition::trans('message.invalid_params')];
            }
            $out[$column] = $value === '' ? null : $value;
        }

        // start / due date (inline cell edit, snoro 2026-11-18): YYYY-MM-DD or
        // empty to clear
        foreach (['sd' => 'start', 'dd' => 'due'] as $key => $role) {
            if (!array_key_exists($key, $set)) {
                continue;
            }
            $column = array_get($is, $role);
            $value = trim(strval($set[$key]));
            $value = str_replace('/', '-', $value);
            if (is_nullorempty($column) || ($value !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value))) {
                return [[], PortalDefinition::trans('message.invalid_params')];
            }
            if ($value !== '') {
                list($y, $m, $d) = array_map('intval', explode('-', $value));
                if (!checkdate($m, $d, $y)) {
                    return [[], PortalDefinition::trans('message.invalid_params')];
                }
            }
            $out[$column] = $value === '' ? null : $value;
        }

        if (array_key_exists('cat', $set)) {
            $column = array_get($is, 'category');
            $values = collect((array) $set['cat'])->map(function ($v) {
                return strval($v);
            })->filter(function ($v) {
                return $v !== '';
            })->unique()->values();
            if (is_nullorempty($column)) {
                return [[], PortalDefinition::trans('message.invalid_params')];
            }
            if (!$this->bulkLookupOk(
                $this->def['category_table'],
                array_get($this->def['category'], 'project'),
                $project->id,
                $values->all()
            )) {
                return [[], PortalDefinition::trans('message.invalid_params')];
            }
            $out[$column] = $values->all();
        }

        if (array_key_exists('as', $set)) {
            $column = array_get($is, 'assignees');
            $values = collect((array) $set['as'])->map(function ($v) {
                return intval($v);
            })->filter()->unique()->values();
            if (is_nullorempty($column)) {
                return [[], PortalDefinition::trans('message.invalid_params')];
            }
            // Checked against the user table rather than against the project
            // member list: the ordinary edit form does not restrict the column
            // that way either, and inventing the rule here would make a bulk
            // edit refuse an assignment a single edit accepts.
            if ($values->isNotEmpty()) {
                $found = CustomTable::getEloquent(SystemTableName::USER)->getValueModel()
                    ->query()->whereIn('id', $values->all())->count();
                if ($found != $values->count()) {
                    return [[], PortalDefinition::trans('message.invalid_params')];
                }
            }
            $out[$column] = $values->map(function ($v) {
                return strval($v);
            })->all();
        }

        return [$out, null];
    }

    /**
     * Do all of these ids exist in that master table, for this project?
     *
     * @param string|null $table_name
     * @param string|null $project_column
     * @param mixed $project_id
     * @param array<int, string> $ids
     * @return bool
     */
    protected function bulkLookupOk($table_name, $project_column, $project_id, array $ids)
    {
        if (empty($ids)) {
            return true;
        }
        $table = CustomTable::getEloquent($table_name);
        if (!isset($table)) {
            return false;
        }
        $found = $this->scopedToProject($table, $project_column, $project_id)
            ->whereIn('id', $ids)->pluck('id')->map(function ($v) {
                return strval($v);
            })->all();

        return empty(array_diff($ids, $found));
    }

    /**
     * A short string that changes whenever the project's issue list does.
     *
     * Count and newest edit together catch the three things that matter: a
     * row added, a row removed, a row changed. A comment with no field change
     * does not move it - the manual refresh button does not consult the stamp
     * at all, so nothing is unreachable, only un-noticed until asked for.
     *
     * @param \Exceedone\Exment\Model\CustomValue $project
     * @return string
     */
    /**
     * The master lists the issue form may add a row to on the spot.
     *
     * Keyed by table name so the client can match a column's target_table
     * against it. The column names come from the def map rather than from
     * JS, and whether the button appears at all is settled per column by
     * can_create, which the schema endpoint answers.
     *
     * @return array<string, array<string, string>>
     */
    protected function quickAddTables()
    {
        $out = [];
        foreach (['milestone', 'category'] as $role) {
            $table_name = array_get($this->def, $role . '_table');
            $map = array_get($this->def, $role, []);
            $name = array_get($map, 'name');
            $parent = array_get($map, 'project');
            if (is_nullorempty($table_name) || is_nullorempty($name) || is_nullorempty($parent)) {
                continue;
            }
            $out[$table_name] = ['col' => $name, 'parent' => $parent];
        }

        return $out;
    }

    protected function projectStampValue($project)
    {
        // count/newest change of its issues (read once per request), then the project's own
        return $this->issueStamp(intval($project->id)) . '/' . substr(strval($project->updated_at), 0, 19);
    }

    /* ================================================================== */
    /* payload building                                                   */
    /* ================================================================== */

    /**
     * @param \Exceedone\Exment\Model\CustomValue $project
     * @return array<string, mixed>
     */
    protected function buildPayload($project)
    {
        $pj = $this->def['project'];
        $is = $this->def['issue'];
        $me = \Exment::getUserId();
        $issue_name = $this->issue_table->table_name;

        // Without the bodies: the list never shows them, and on a big project
        // they are most of what the table holds. The few issues whose detail
        // screens come with the page are read again in full below.
        // ISS00332: issues finished long ago are left out (see archiveScope)
        // and come only when the list asks for them.
        $archive = $this->archiveScope($project);
        $query = $this->dashboardLean($this->projectIssueQuery($project));
        if ($archive !== null) {
            $query->whereRaw($archive['active_sql'], $archive['bindings']);
        }
        $issues = $query->get();
        $issue_ids = $issues->pluck('id')->toArray();
        // ISS00257: read shares once - all of this project's issues, and every
        // readable project (each issue's edit check also checks its project) -
        // instead of one or two queries per row. Whole sets only: Exment keeps
        // the first preload of a table for the rest of the request.
        $this->issue_table->setGridAuthoritable($issues);
        $this->project_table->setGridAuthoritable($this->project_table->getValueModel()->get());

        list($status_meta, $status_order) = $this->selectMeta($this->issue_table, array_get($is, 'status'), $project);
        $statuses = [];
        foreach ($status_order as $key) {
            $statuses[] = [
                'k' => $key,
                'l' => $status_meta[$key]['l'],
                'c' => $status_meta[$key]['c'],
                // A row-backed status knows for itself; a word list falls back
                // to the names the portal has always treated as finished.
                'done' => array_key_exists('done', $status_meta[$key])
                    ? boolval($status_meta[$key]['done'])
                    : in_array($key, $this->def['done_statuses']),
            ];
        }
        list($type_meta) = $this->selectMeta($this->issue_table, array_get($is, 'type'), $project);
        list($priority_meta, $priority_order) = $this->selectMeta($this->issue_table, array_get($is, 'priority'), $project);
        $this->assignPriorityArrows($priority_meta, $priority_order);

        $milestones = $this->buildMilestones($project, $issues);
        $categories = $this->buildLookup($this->def['category_table'], $issues, array_get($is, 'category'), $project);

        $star_counts = Star::counts(Star::TYPE_STAR, $issue_name, $issue_ids);
        $my_stars = Star::mine(Star::TYPE_STAR, $issue_name, $issue_ids, $me);
        // One more indexed read on the same table the stars come from, so the
        // list can offer ウォッチ中 the way the dashboard does.
        $my_watches = Star::mine(Star::TYPE_WATCH, $issue_name, $issue_ids, $me);
        $unread = $this->unreadIssues($issues, $me);

        // Lean rows: what the page's script rebuilds is not built at all.
        $rows = $this->issueRowsWith($project, $issues, $star_counts, $my_stars, $my_watches, $unread, true);

        // A switched-off feature costs nothing: the tab is not rendered and
        // the data behind it is not read. Nothing is deleted either - turning
        // it back on brings every page and file straight back.
        $features = $this->projectFeatures($project);
        $wiki = $features['wiki']
            ? $this->buildWiki($project, $me)
            : ['enabled' => false, 'pages' => [], 'create_url' => null];
        $files = $features['file']
            ? $this->buildFiles($project, $issues, $rows, $wiki)
            : ['rows' => [], 'partial' => false];
        // 「最近の更新」 is read after the page, ten at a time (projectFeed).
        $feed = null;
        $archived = $this->projectIsArchived($project);

        // The issue screens come with the page: opening one then costs no
        // request. Bounded, so a huge project does not build a huge page -
        // the rest is fetched in one batch, on demand.
        // 30 costs ~100ms and ~80KB on a heavy project, and covers the rows
        // people actually open; the background top-up fetches the next batch.
        $embed_limit = intval(config('exment.pmportal.embed_details', 30));
        $embedded = $embed_limit > 0
            ? $issues->sortByDesc(function ($issue) {
                return strval($issue->updated_at ?? $issue->created_at);
            })->take($embed_limit)->values()
            : collect();
        if ($embedded->isNotEmpty()) {
            // The list was read without bodies; these need theirs.
            $full = $this->issue_table->getValueModel()->whereIn('id', $embedded->pluck('id')->all())->get()->keyBy('id');
            $embedded = $embedded->map(function ($issue) use ($full) {
                return $full->get($issue->id) ?? $issue;
            })->values();
        }
        $details = $this->trimEmbedded($this->detailsFor($project, $embedded, $me));

        // Members and viewers as the screen uses them - to offer assignees
        // and to name people: those the columns name first, in their order,
        // then whoever the organization columns bring (ISS00333).
        $named = function ($key) use ($project, $pj) {
            return collect((array) array_get($project->value, array_get($pj, $key), []))->map(function ($v) { return intval($v); });
        };
        $roles = collect(PortalMembership::roles((array) $project->value));
        $member_ids = $named('members')->merge($roles->filter(function ($role) {
            return $role === PortalMembership::MEMBER;
        })->keys())->map(function ($v) { return intval($v); })->unique()->values();
        $viewer_ids = $named('viewers')->merge($roles->filter(function ($role) {
            return $role === PortalMembership::VIEWER;
        })->keys())->map(function ($v) { return intval($v); })->unique()->values();

        $user_ids = collect([intval(array_get($project->value, array_get($pj, 'manager')))])
            ->merge($member_ids)
            ->merge($viewer_ids)
            ->merge(collect($rows)->pluck('as')->flatten())
            ->merge(collect($rows)->pluck('by'))
            ->merge(collect($feed)->pluck('by'))
            ->merge(collect($files['rows'])->pluck('by'))
            ->merge(collect($wiki['pages'])->pluck('upby'))
            ->merge(collect($details)->pluck('timeline')->flatten(1)->pluck('by'))
            ->merge(collect($details)->pluck('reads')->flatten(1)->pluck('by'))
            // the people asked to confirm a comment (ISS00341), named under it
            ->merge(collect($details)->pluck('timeline')->flatten(1)->pluck('rv')->filter()->flatten(1)->pluck('by'))
            ->filter()->unique()->values()->toArray();

        list($relations, $relation_types, $related_meta) = $this->buildRelations($project, $issues);

        $project_status_key = strval(array_get($project->value, array_get($pj, 'status'), ''));
        list($project_status_meta) = $this->selectMeta($this->project_table, array_get($pj, 'status'));
        $can = $this->projectCan($project);

        return [
            'project' => [
                'id' => $project->id,
                'name' => strval($project->getLabel()),
                'key' => strtoupper(strval(array_get($project->value, array_get($pj, 'key'), ''))),
                'note' => strval(array_get($project->value, array_get($pj, 'note'), '')),
                'status' => array_get($project_status_meta, $project_status_key),
                'start' => strval(array_get($project->value, array_get($pj, 'start'), '')),
                'end' => strval(array_get($project->value, array_get($pj, 'end'), '')),
                'manager' => intval(array_get($project->value, array_get($pj, 'manager'))),
                'members' => $member_ids,
                'viewers' => $viewer_ids,
                'edit_url' => admin_url('data', [$this->project_table->table_name, $project->id, 'edit']),
                'features' => $features,
                // Read-only while archived: the comment, attachment and bulk
                // routes refuse, so the screen can say so before anyone types.
                'archived' => $archived,
            ],
            'can' => array_merge((array) $can, ['alias' => PortalIssueAliases::exists()]),
            // The settings tab's server-known bits; withheld from people the
            // tab is hidden from anyway.
            'git' => $can['manage'] ? $this->gitState($project) : null,
            // the Files tab with the file server (ISS02825): off unless the feature is on
            'fileserver' => $features['file'] ? PortalFiles::state($project, $can['manage']) : ['on' => false],
            // the chat channels, for the manager while the feature is on (ISS02939)
            'channels' => ($can['manage'] && PortalChannels::pageOn()) ? PortalChannels::state(intval($project->id)) : ['on' => false],
            // Whether the issue form shows the AI button, and the numbers it
            // labels the answers with. Never the key.
            'ai' => $this->aiPayload($project),
            'settings_map' => $can['manage'] ? $this->projectSettingsMap() : null,
            'me' => intval($me),
            // The user's today and clock: what a due date is late against,
            // and what "as of" says, are the days and hours the user lives in.
            'today' => PortalTime::today(),
            // When this payload was built, which is what "as of" means. Taken
            // here rather than from the browser so it matches every other time
            // on the screen.
            'now' => PortalTime::now()->format('H:i'),
            // The shown times differ from the stored ones (the user's time
            // zone is not the site's): the script then shows what the server
            // formatted, and never rebuilds a shown time from a stored one.
            'tz_differs' => PortalTime::differs(),
            // How many levels issues nest (PortalRules::issueLevels): an issue
            // on the last level is offered no "add a child".
            'issue_levels' => PortalRules::issueLevels(),
            // What the screen can compare against later to find out whether
            // anything moved while it was sitting open.
            'stamp' => $this->projectStampValue($project),
            'users' => $this->usersFor($user_ids),
            'statuses' => $statuses,
            'types' => $type_meta,
            'priorities' => $priority_meta,
            'milestones' => $milestones,
            'categories' => $categories,
            // Which master lists the issue form may add a row to without
            // leaving itself.
            'quick_add' => $this->quickAddTables(),
            'relations' => $relations,
            'relation_types' => $relation_types,
            // Packed: what the screen can rebuild (addresses, the key, the
            // shown time, empty values) is left out and filled back in by
            // the script before anything reads the rows. A project of 2,400
            // open issues sent 1.6MB of rows; this is under half of that.
            'issues' => $this->packRows($rows),
            'row_pack' => [
                'defaults' => (object) self::ROW_DEFAULTS,
                'url' => admin_url('data', [$issue_name]),
                'key' => strtoupper(strval(array_get($project->value, array_get($pj, 'key'), ''))) ?: '#',
            ],
            'details' => (object) $details,
            'feed' => $feed,
            // Issues plus their revision history: kept until an issue of the
            // project changes, or the day does (0.16s on ex1task).
            'burndown' => $features['gantt'] ? $this->pmCached('burndown', [
                intval($project->id), $this->issueStamp(intval($project->id)), \Carbon\Carbon::today()->format('Y-m-d'), $milestones, $archive,
            ], function () use ($project, $issues, $archive, $milestones) {
                return $this->buildBurndown($project, $this->withArchivedOf($project, $issues, $archive, $milestones), $milestones);
            }) : [],
            'archive' => $this->archiveSummary($project, $archive),
            'wiki' => $wiki,
            'files' => $files,
            'issue_table' => $issue_name,
            // The column names behind the short keys the rows use, so the
            // screen can build a create form that starts from an issue.
            // The quick edit saves the due time by the same role map.
            'issue_cols' => $this->def['issue'] + ['due_time' => strval(array_get($this->def, 'issue_extra.due_time', 'due_time'))],
            // The columns the schema upgrade added. Named separately because
            // an install that has not run the upgrade simply has not got them,
            // and the form has to lay out whatever is actually there.
            'issue_extra_cols' => array_get($this->def, 'issue_extra', []),
            // ...and which of them the table really has: the issue screen and
            // the list offer only those (an install that has not run the
            // upgrade has not got the roles of schema 27).
            'issue_extra_on' => $this->issueExtraOn(),
            'difficulties' => PortalSchema::DIFFICULTIES,
            'related_table' => $related_meta['table'],
            'related_cols' => $related_meta['cols'],
            'wiki_table' => $this->def['wiki_table'],
            'project_table' => $this->project_table->table_name,
            'form_styles' => $form_styles = PortalDefinition::formStyles(),
            'form_style' => $this->currentFormStyle($form_styles),
            'features' => [
                'prefetch' => (bool) config('exment.pmportal.prefetch', true),
                'embedded' => count($details),
                'lazy_widgets' => (bool) config('exment.pmportal.lazy_widgets', true),
            ],
            'urls' => [
                'base' => PortalDefinition::url($project->id),
                // The portal's list of projects, for the button on every tab.
                'projects' => PortalDefinition::url(),
                // The page's own address, by code (ISS00440): what links and
                // "copy URL" are made of. base stays the id for the requests.
                'page' => PortalDefinition::projectUrl($project),
                'create_issue' => admin_url('data', [$issue_name, 'create']) . '?select_parent=' . $project->id,
                'issue_data' => admin_url('data', [$issue_name]),
                'data_base' => admin_url('data'),
                'wiki_data' => admin_url('data', [$this->def['wiki_table']]),
                'project_data' => admin_url('data', [$this->project_table->table_name]),
                // Exment's own upload endpoint for the project's files asks for
                // read access alone, so its address goes only to who Exment's
                // detail screen would show the upload box to
                // (DefaultShow::useFileUpload) - an editor of the project, on a
                // table that takes attachments - and not while it is archived.
                // Null hides the drop zone.
                'upload' => (!$archived && $project->enableEdit() === true
                    && boolval($this->project_table->getOption('attachment_flg') ?? true))
                    ? admin_url('data', [$this->project_table->table_name, $project->id, 'fileupload'])
                    : null,
                // ISS00416: Word, Excel and PowerPoint attachments shown in place
                'office_view' => $this->officeViewBase(),
                'temp_image_upload' => admin_url('tmpimages'),
                'mention' => admin_url('webapi/mention/users'),
                'form_style' => PortalDefinition::url('form-style'),
                'embed_board' => PortalDefinition::url($project->id, 'embed', 'board'),
                'embed_gantt' => PortalDefinition::url($project->id, 'embed', 'gantt'),
                'lightapi_base' => PortalDefinition::url('lightapi'),
                'git_repo' => PortalDefinition::url($project->id, 'git_repo'),
                'git_keys' => PortalDefinition::url($project->id, 'git_keys'),
                'git_key_remove' => PortalDefinition::url($project->id, 'git_key_remove'),
                'git_token' => PortalDefinition::url($project->id, 'git_token'),
                'file_workspace' => PortalDefinition::url($project->id, 'file_workspace'),
                'channels' => PortalDefinition::url($project->id, 'channels'),
                'ai_suggest' => PortalDefinition::url($project->id, 'ai', 'suggest'),
                'ai_feedback' => PortalDefinition::url($project->id, 'ai', 'feedback'),
                'ai_state' => PortalDefinition::url($project->id, 'ai', 'state'),
                'ai_test' => PortalDefinition::url($project->id, 'ai', 'test'),
                'delete_project' => PortalDefinition::url($project->id, 'delete'),
                'dashboard' => PortalDefinition::url(),
                'data' => PortalDefinition::url($project->id, 'data'),
                'stamp' => PortalDefinition::url($project->id, 'stamp'),
                'bulk' => PortalDefinition::url($project->id, 'issues', 'bulk'),
            ],
        ];
    }

    /**
     * Sideways links between issues - relates, blocks, duplicates - which
     * the parent column cannot express, plus the vocabulary to draw them.
     *
     * One query over the link table, bounded by the project's own issues, so
     * the payload grows with the project and not with the space. A link is
     * kept when either end lives here: the far end of a cross-project link
     * still renders, as a bare number pointing at the data screen.
     *
     * Who may see a link follows from its two issues, not from a share on the
     * link row itself: the link table is read past the row scope, and a link
     * is kept only while the user can read its other end as well. A link to
     * an issue they cannot open would otherwise tell them that issue exists
     * and how it relates to this one.
     *
     * @param \Exceedone\Exment\Model\CustomValue $project
     * @param \Illuminate\Support\Collection $issues
     * @return array{0: array<int, array<string, mixed>>, 1: object, 2: array<string, mixed>}
     */
    protected function buildRelations($project, $issues)
    {
        $related_table = CustomTable::getEloquent(array_get($this->def, 'related_table'));
        $rel = (array) array_get($this->def, 'related_row', []);
        $meta = ['table' => null, 'cols' => null];
        if (!isset($related_table)) {
            return [[], (object) [], $meta];
        }

        // Labels come from the column's own options, so a deployment that
        // rewords "blocks" rewords the screen with it. The inverse key lets
        // the detail label a link from whichever side it is looking.
        list($type_meta, $type_order) = $this->selectMeta($related_table, array_get($rel, 'type'));
        $inverse = PortalSchema::relationInverse();
        $types = [];
        foreach ($type_order as $type_key) {
            $types[$type_key] = [
                'l' => strval(array_get($type_meta, $type_key . '.l', $type_key)),
                'inv' => strval(array_get($inverse, $type_key, $type_key)),
            ];
        }
        $meta = [
            'table' => $related_table->table_name,
            'cols' => [
                'f' => strval(array_get($rel, 'from')),
                't' => strval(array_get($rel, 'to')),
                'rt' => strval(array_get($rel, 'type')),
            ],
        ];

        $ids = $issues->pluck('id')->map(function ($v) {
            return strval($v);
        })->all();
        $from_column = $this->columnOf($related_table, strval(array_get($rel, 'from')));
        $to_column = $this->columnOf($related_table, strval(array_get($rel, 'to')));
        if (empty($ids) || !isset($from_column) || !isset($to_column)) {
            return [[], (object) $types, $meta];
        }

        // Without scopes, so without the soft delete one either - hence the
        // deleted_at condition.
        $link_rows = $related_table->getValueModel()->newQueryWithoutScopes()
            ->whereNull('deleted_at')
            ->where(function ($query) use ($from_column, $to_column, $ids) {
                $query->whereIn($from_column->getQueryKey(), $ids)
                    ->orWhereIn($to_column->getQueryKey(), $ids);
            })
            ->get();

        $links = [];
        $far = [];
        $here = array_flip($ids);
        foreach ($link_rows as $link) {
            $row = [
                'id' => $link->id,
                'f' => intval(array_get($link->value, array_get($rel, 'from'))),
                't' => intval(array_get($link->value, array_get($rel, 'to'))),
                'rt' => strval(array_get($link->value, array_get($rel, 'type'))),
            ];
            $links[] = $row;
            foreach ([$row['f'], $row['t']] as $end) {
                if (!isset($here[strval($end)])) {
                    $far[$end] = true;
                }
            }
        }

        // This project's issues came through the row scope already; an end
        // elsewhere is checked the same way, in one query.
        $readable = empty($far) ? [] : array_flip($this->issue_table->getValueModel()->query()
            ->whereIn('id', array_keys($far))
            ->pluck('id')
            ->map(function ($v) {
                return intval($v);
            })
            ->all());

        $out = [];
        foreach ($links as $row) {
            $ok = true;
            foreach ([$row['f'], $row['t']] as $end) {
                if (!isset($here[strval($end)]) && !isset($readable[$end])) {
                    $ok = false;
                }
            }
            if ($ok) {
                $out[] = $row;
            }
        }

        return [$out, (object) $types, $meta];
    }

    /**
     * The project's issues, as a query.
     *
     * @param \Exceedone\Exment\Model\CustomValue $project
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function projectIssueQuery($project)
    {
        return $this->issue_table->getValueModel()
            ->where('parent_id', $project->id)
            ->where('parent_type', $this->project_table->table_name);
    }

    /**
     * Days after which a finished issue is archived (ISS00332): left out of
     * the list the page loads, counted but not sent, fetched on request.
     * Nothing is written - an issue that is reopened or edited is simply
     * back. 0 turns it off.
     */
    protected function archiveDays(): int
    {
        return max(0, intval(config('exment.pmportal.archive_days', 30)));
    }

    /**
     * SQL for an issue column's value: its index column when it has one.
     */
    protected function issueColumnSql(?string $name): ?string
    {
        if (is_nullorempty($name)) {
            return null;
        }
        $column = CustomColumn::getEloquent($name, $this->issue_table);
        if (!isset($column)) {
            return null;
        }
        if ($column->index_enabled) {
            return '`' . $column->getIndexColumnName() . '`';
        }
        if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
            return null;
        }

        return "JSON_UNQUOTE(JSON_EXTRACT(`value`, '$.\"" . $name . "\"'))";
    }

    /**
     * What archived means for this project, as SQL, or null when archiving
     * is off or cannot be told (no status column, no done status, a
     * database without JSON functions).
     *
     * @return array<string, mixed>|null
     */
    protected function archiveScope($project): ?array
    {
        $days = $this->archiveDays();
        if ($days === 0) {
            return null;
        }
        $driver = $this->issue_table->getValueModel()->getConnection()->getDriverName();
        if (!in_array($driver, ['mysql', 'mariadb'], true)) {
            return null;
        }
        $status_column = array_get($this->def['issue'], 'status');
        $st = $this->issueColumnSql($status_column);
        $done = array_values(array_filter(array_map('strval', (array) $this->doneKeys($project, $status_column)), 'strlen'));
        if ($st === null || empty($done)) {
            return null;
        }
        // From the start of the day, not this second: the same all day, so
        // the archive summary and the burndown, cached on it, are read once
        // a day per change of the issues rather than on every load.
        $cutoff = \Carbon\Carbon::today()->subDays($days)->format('Y-m-d H:i:s');
        $marks = implode(',', array_fill(0, count($done), '?'));

        return [
            'days' => $days,
            'st' => $st,
            // updated_at also moves with a comment, so an issue people are
            // still talking about stays in the list.
            'active_sql' => '(' . $st . ' IS NULL OR ' . $st . ' NOT IN (' . $marks . ') OR `updated_at` >= ?)',
            'archived_sql' => '(' . $st . ' IN (' . $marks . ') AND `updated_at` < ?)',
            'bindings' => array_merge($done, [$cutoff]),
        ];
    }

    /**
     * Counts of the archived issues, so that every number on the screen
     * still covers the whole project: per status, per milestone, and done
     * children per parent.
     *
     * @return array<string, mixed>|null
     */
    protected function archiveSummary($project, ?array $archive): ?array
    {
        if ($archive === null) {
            return null;
        }
        $is = $this->def['issue'];
        $ms = $this->issueColumnSql(array_get($is, 'milestone')) ?? 'NULL';
        $pa = $this->issueColumnSql(array_get($is, 'parent')) ?? 'NULL';
        // Grouping ~2,000 finished issues on columns computed from their JSON
        // took 0.25s on ex1task; it changes only when an issue does.
        $groups = $this->pmCached('archive', [
            intval($project->id), $this->issueStamp(intval($project->id)), $archive['archived_sql'], $archive['bindings'], $ms, $pa,
        ], function () use ($project, $archive, $ms, $pa) {
            return $this->projectIssueQuery($project)->toBase()
                ->whereRaw($archive['archived_sql'], $archive['bindings'])
                ->selectRaw($archive['st'] . ' as st, ' . $ms . ' as ms, ' . $pa . ' as pa, count(*) as n')
                ->groupByRaw($archive['st'] . ', ' . $ms . ', ' . $pa)
                ->get()->map(function ($g) {
                    return (object) (array) $g;
                })->all();
        });
        $out = ['days' => $archive['days'], 'count' => 0, 'by_status' => [], 'by_ms' => [], 'kids' => []];
        foreach ($groups as $g) {
            $n = intval($g->n);
            $out['count'] += $n;
            $st = strval($g->st ?? '');
            $out['by_status'][$st] = ($out['by_status'][$st] ?? 0) + $n;
            if (!is_nullorempty($g->ms)) {
                $out['by_ms'][strval($g->ms)] = ($out['by_ms'][strval($g->ms)] ?? 0) + $n;
            }
            if (!is_nullorempty($g->pa)) {
                $out['kids'][strval($g->pa)] = ($out['kids'][strval($g->pa)] ?? 0) + $n;
            }
        }
        $out['by_status'] = (object) $out['by_status'];
        $out['by_ms'] = (object) $out['by_ms'];
        $out['kids'] = (object) $out['kids'];
        $out['url'] = PortalDefinition::url($project->id, 'archived');

        return $out;
    }

    /**
     * The issues plus the archived ones in the given milestones - the
     * burndown draws a milestone's whole history, finished work included.
     */
    protected function withArchivedOf($project, $issues, ?array $archive, array $milestones)
    {
        $ms = $this->issueColumnSql(array_get($this->def['issue'], 'milestone'));
        if ($archive === null || $ms === null || empty($milestones)) {
            return $issues;
        }
        $ids = array_map('strval', array_keys($milestones));
        $more = $this->dashboardLean($this->projectIssueQuery($project))
            ->whereRaw($archive['archived_sql'], $archive['bindings'])
            ->whereRaw($ms . ' IN (' . implode(',', array_fill(0, count($ids), '?')) . ')', $ids)
            ->get();

        return $more->isEmpty() ? $issues : $issues->concat($more)->values();
    }

    /**
     * List rows with the reader's stars, watches and unread marks.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function issueRowsWith($project, $issues, $star_counts, $my_stars, $my_watches, $unread, bool $lean = false): array
    {
        // Read here rather than handed in: every list of rows wants it, and
        // it is the reader's own, like the marks beside it.
        $review = $this->reviewPendingIssues($issues->pluck('id')->all(), \Exment::getUserId());
        $rows = [];
        // Keyed once: in_array over every row was quadratic on a big project.
        $my_stars = array_flip(array_map('strval', (array) $my_stars));
        $my_watches = array_flip(array_map('strval', (array) $my_watches));
        // ISS00272: the reader's own favorites, one indexed read
        $my_favs = array_flip(array_map('strval', Star::mine(Star::TYPE_FAV, $this->issue_table->table_name, $issues->pluck('id')->all(), \Exment::getUserId())));
        // ISS00248: the alias of each, one read
        $aliases = PortalIssueAliases::names($issues->pluck('id')->all());
        foreach ($issues as $issue) {
            $row = $this->buildIssueRow($project, $issue, $lean);
            $row['my_rv'] = isset($review[intval($issue->id)]);
            $row['star'] = intval(array_get($star_counts, strval($issue->id), 0));
            $row['my_star'] = isset($my_stars[strval($issue->id)]);
            $row['my_watch'] = isset($my_watches[strval($issue->id)]);
            $row['my_fav'] = isset($my_favs[strval($issue->id)]);
            $row['al'] = $aliases[intval($issue->id)] ?? '';
            $row['unread'] = isset($unread[intval($issue->id)]);
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * The archived issues of a project, as list rows (ISS00332).
     */
    public function archivedIssues(Request $request, $id)
    {
        if (PortalSetup::needsSetup()) {
            return $this->messageError(409, 'setup_required');
        }
        $project = $this->findProject($id);
        $archive = $this->archiveScope($project);
        if ($archive === null) {
            return response()->json(['ok' => true, 'issues' => [], 'users' => (object) []]);
        }
        $me = \Exment::getUserId();
        $issue_name = $this->issue_table->table_name;
        $issues = $this->dashboardLean($this->projectIssueQuery($project))
            ->whereRaw($archive['archived_sql'], $archive['bindings'])
            ->get();
        $this->issue_table->setGridAuthoritable($issues);
        $ids = $issues->pluck('id')->toArray();
        $rows = $this->issueRowsWith(
            $project,
            $issues,
            Star::counts(Star::TYPE_STAR, $issue_name, $ids),
            Star::mine(Star::TYPE_STAR, $issue_name, $ids, $me),
            Star::mine(Star::TYPE_WATCH, $issue_name, $ids, $me),
            $this->unreadIssues($issues, $me)
        );
        $user_ids = collect($rows)->pluck('as')->flatten()->merge(collect($rows)->pluck('by'))->filter()->unique()->values()->toArray();

        return response()->json(['ok' => true, 'issues' => $rows, 'users' => $this->usersFor($user_ids)]);
    }

    /**
     * The due time as HH:MM, or '' when the issue has none (a whole-day due
     * date, and every issue from before schema 22).
     *
     * @param mixed $value the issue's value array
     * @return string
     */
    protected function dueTime($value): string
    {
        $raw = strval(array_get((array) $value, strval(array_get($this->def, 'issue_extra.due_time', 'due_time')), ''));

        if (!preg_match('/^(\d{1,2}):(\d{2})/', $raw, $m)) {
            return '';
        }
        $time = sprintf('%02d:%s', intval($m[1]), $m[2]);

        // 00:00 is no time: see PluginEvent::clearMidnightDueTime().
        return $time === '00:00' ? '' : $time;
    }

    /**
     * One issue as the list row / detail head sees it.
     *
     * @param \Exceedone\Exment\Model\CustomValue $project
     * @param \Exceedone\Exment\Model\CustomValue $issue
     * @return array<string, mixed>
     */
    protected function buildIssueRow($project, $issue, bool $lean = false)
    {
        $is = $this->def['issue'];
        $v = $issue->value;

        $row = [
            'id' => $issue->id,
            'key' => $lean ? null : $this->issueKey($project, $issue->id),
            // The issue key (ISS00239) the address uses (ISS00440).
            'ik' => $this->issueKeyFrom($v),
            'url' => $lean ? null : admin_url('data', [$this->issue_table->table_name, $issue->id]),
            'edit_url' => $lean ? null : admin_url('data', [$this->issue_table->table_name, $issue->id, 'edit']),
            'subject' => strval(array_get($v, array_get($is, 'subject'), '')),
            // Schema 19: the second subject line (empty before the upgrade).
            'sub' => strval(array_get($v, 'subject_sub', '')),
            // Schema 20: daily / weekdays / weekly / monthly / yearly, or ''.
            'rc' => strval(array_get($v, strval(array_get($this->def, 'issue_extra.recurrence', PortalSchema::ISSUE_COLUMNS['recurrence'])), '')),
            'ty' => strval(array_get($v, array_get($is, 'type'), '')),
            'st' => strval(array_get($v, array_get($is, 'status'), '')),
            'pr' => strval(array_get($v, array_get($is, 'priority'), '')),
            'as' => collect((array) array_get($v, array_get($is, 'assignees'), []))->map(function ($v) { return intval($v); })->values(),
            'ms' => strval(array_get($v, array_get($is, 'milestone'), '')),
            'cat' => collect((array) array_get($v, array_get($is, 'category'), []))->map('strval')->values(),
            'sd' => $this->rawDate(array_get($v, array_get($is, 'start'))),
            'dd' => $this->rawDate(array_get($v, array_get($is, 'due'))),
            // Schema 22: HH:MM on the due date, or '' for the whole day.
            'dt' => $this->dueTime($v),
            'pg' => is_numeric(array_get($v, array_get($is, 'progress'))) ? floatval(array_get($v, array_get($is, 'progress'))) : null,
            // Numbers or nothing, like the progress above: the screen prints
            // the hours straight into the page, and a column that is not a
            // decimal one could otherwise hand it any text at all.
            'eh' => is_numeric(array_get($v, array_get($is, 'estimate'))) ? floatval(array_get($v, array_get($is, 'estimate'))) : null,
            'ah' => is_numeric(array_get($v, array_get($is, 'actual'))) ? floatval(array_get($v, array_get($is, 'actual'))) : null,
            'parent' => intval(array_get($v, array_get($is, 'parent'))) ?: null,
            'up' => ($lean && $issue->updated_at && !PortalTime::differs()) ? null : $this->fmtAt($issue->updated_at ?? $issue->created_at),
            // The version as the server compares it, sent back with an edit
            // so a change made in between is refused rather than overwritten.
            'ua' => $issue->updated_at ? (string) $issue->updated_at : null,
            // Who raised it. The dashboard already offers a 作成 list off this
            // column; carrying it on the row lets the project's own list do
            // the same without a second query.
            'by' => intval($issue->created_user_id) ?: null,
            // Schema 27: sub-assignees, managers, reviewers and difficulty
            // (empty before the upgrade).
            'sa' => $this->userIds($v, 'sub_assignees'),
            'mg' => $this->userIds($v, 'managers'),
            'rv' => $this->userIds($v, 'reviewers'),
            'df' => strval(array_get($v, array_get($this->def, 'issue_extra.difficulty', 'difficulty'), '')),
            // The people told of every change (お知らせ先), shown and set on
            // the issue's own side as well as in the form.
            'nu' => $this->userIds($v, 'notified_users'),
        ];
        if ($lean) {
            // Rebuilt by the page's script (unpackRows) from the id, the
            // project's key and ua.
            unset($row['url'], $row['edit_url'], $row['key']);
            // The shown time is ua cut short - in the time zone of the site.
            // For a user in another time zone it is sent as formatted.
            if ($issue->updated_at && !PortalTime::differs()) {
                unset($row['up']);
            }
        }

        return $row;
    }

    /**
     * The issue key (ISS00239) out of an issue's values: the same rule as
     * PortalDefinition::issueKeyOf, off the definition this controller
     * already holds. That one rebuilds the whole config on every call, and
     * a list calls it once per row - on 2,400 rows a good part of the time.
     *
     * @param mixed $value the issue's value array
     */
    protected function issueKeyFrom($value): string
    {
        $key = array_get((array) $value, strval(array_get($this->def, 'issue.key', 'issue_key')));
        $key = is_scalar($key) ? trim(strval($key)) : '';

        return preg_match('/^[A-Za-z0-9_\-]{1,64}$/', $key) ? $key : '';
    }

    /**
     * A list row's values that are left out of the page when they are the
     * empty ones; the script puts them back (see unpackRows in script).
     */
    const ROW_DEFAULTS = [
        'sub' => '', 'rc' => '', 'ms' => '', 'dt' => '', 'df' => '',
        'sd' => null, 'dd' => null, 'pg' => null, 'eh' => null, 'ah' => null, 'parent' => null, 'by' => null,
        'as' => [], 'cat' => [], 'sa' => [], 'mg' => [], 'rv' => [], 'nu' => [],
        'my_rv' => false, 'star' => 0, 'my_star' => false, 'my_watch' => false, 'my_fav' => false, 'al' => '', 'unread' => false,
    ];

    /**
     * The page's list rows without the values that are the empty ones (the
     * lean rows already leave out the addresses, the key and the shown time).
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    protected function packRows(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            foreach (self::ROW_DEFAULTS as $k => $empty) {
                if (!array_key_exists($k, $row)) {
                    continue;
                }
                $value = $row[$k] instanceof \Illuminate\Support\Collection ? $row[$k]->all() : $row[$k];
                if ($value === $empty) {
                    unset($row[$k]);
                }
            }
            $out[] = $row;
        }

        return $out;
    }

    /**
     * The added issue columns the issue screen and the list show and set,
     * by role, and whether the issue table has each one.
     *
     * @return array<string, bool>
     */
    protected function issueExtraOn(): array
    {
        $out = [];
        foreach (array_merge(PortalSchema::ROLE_COLUMNS, ['difficulty', 'notified_users']) as $role) {
            $column = strval(array_get($this->def, 'issue_extra.' . $role, PortalSchema::ISSUE_COLUMNS[$role] ?? $role));
            $out[$role] = $column !== '' && CustomColumn::getEloquent($column, $this->issue_table) !== null;
        }

        return $out;
    }

    /**
     * The user ids in one of the issue's extra people columns (schema 27).
     *
     * @param mixed $value the issue's value array
     * @param string $role key in PortalSchema::ISSUE_COLUMNS
     * @return array<int, int>
     */
    protected function userIds($value, string $role): array
    {
        $column = array_get($this->def, 'issue_extra.' . $role, $role);

        return array_values(array_filter(array_map('intval', (array) array_get((array) $value, $column, []))));
    }

    /**
     * Burndown series per milestone, from the records' own history.
     *
     * A record joins the scope on its creation day and leaves the remaining
     * count on the day its status first became a done one - read from the
     * revision snapshots, so the chart tells what actually happened, not
     * what today's data implies.
     *
     * Only the history the charts need is read: the revisions of the issues
     * that sit on a milestone, from the day the earliest chart starts - its
     * milestone's start date, or the day its first issue was filed when it has
     * none. What happened before that day is summed up as the number still
     * open when it began, from each issue's state just before its first change
     * inside the window, or its state now when nothing changed since. Reading
     * the project's oldest 2000 revisions instead, as this used to, dropped the
     * newest ones on a busy project - the closes the chart is there to show.
     *
     * The read is newest first and capped (BURNDOWN_REVISION_LIMIT). A cut
     * works the same way as the window: the history then starts later and
     * says so with 'partial', and the counts from there on stay exact.
     *
     * @param \Exceedone\Exment\Model\CustomValue $project
     * @param \Illuminate\Support\Collection $issues
     * @param array<string, mixed> $milestones
     * @return array<int, array<string, mixed>>
     */
    protected function buildBurndown($project, $issues, array $milestones)
    {
        $is = $this->def['issue'];
        $status_column = array_get($is, 'status');
        $milestone_column = array_get($is, 'milestone');
        $done_keys = $this->doneKeys($project, $status_column);
        $issue_name = $this->issue_table->table_name;
        $isDone = function ($value) use ($status_column, $done_keys) {
            return in_array(strval(array_get((array) $value, $status_column)), $done_keys);
        };

        // ---- the issues each chart is about
        $subsets = [];
        $ids = [];
        foreach ($issues as $issue) {
            $ms_id = array_get($issue->value, $milestone_column);
            $ms_id = is_scalar($ms_id) ? strval($ms_id) : '';
            if ($ms_id !== '' && isset($milestones[$ms_id])) {
                $subsets[$ms_id][] = $issue;
                $ids[] = intval($issue->id);
            }
        }
        if (empty($subsets)) {
            return [];
        }

        // ---- the window: from the day the earliest chart starts, and never
        // later than today - a milestone planned ahead still starts from the
        // count open now.
        $today = \Carbon\Carbon::today()->format('Y-m-d');
        $needs = [];
        foreach ($subsets as $ms_id => $subset) {
            $start = $milestones[$ms_id]['start'] ?? null;
            if ($start === null) {
                foreach ($subset as $issue) {
                    $filed = substr(strval($issue->created_at), 0, 10);
                    $start = $start === null ? $filed : min($start, $filed);
                }
            }
            $needs[strval($ms_id)] = strval($start);
        }
        $from = min($today, min($needs));

        $rows = collect(static::sortedRows(\DB::table('revisions')
            ->select(['id', 'revisionable_id', 'created_at', 'old_value', 'new_value'])
            ->where('revisionable_type', $issue_name)
            ->whereIn('revisionable_id', $ids)
            ->whereNull('deleted_at')
            ->where('created_at', '>=', $from . ' 00:00:00'), [['created_at', 'desc'], ['id', 'desc']], self::BURNDOWN_REVISION_LIMIT + 1));

        // Cut short: the oldest second read may be only half there, so the
        // window opens just after it rather than on it.
        $cut = null;
        if ($rows->count() > self::BURNDOWN_REVISION_LIMIT) {
            $cut = strval($rows->last()->created_at);
            $rows = $rows->filter(function ($rev) use ($cut) {
                return strval($rev->created_at) > $cut;
            });
        }
        $opens = $cut ?? $from . ' 00:00:00';
        $inWindow = function ($at) use ($cut, $opens) {
            $at = strval($at);

            return $cut === null ? $at >= $opens : $at > $opens;
        };
        $window_day = substr($opens, 0, 10);

        // ---- per issue: done or not just before its first change in the
        // window (the walk goes back in time, so the last write wins), and the
        // days its status crossed between open and done.
        $done_before = [];
        $moves = [];
        foreach ($rows as $rev) {
            $id = intval($rev->revisionable_id);
            $was = $isDone(json_decode(strval($rev->old_value), true) ?: []);
            $now = $isDone(json_decode(strval($rev->new_value), true) ?: []);
            $done_before[$id] = $was;
            if ($was !== $now) {
                $moves[$id][] = [substr(strval($rev->created_at), 0, 10), $now];
            }
        }

        $charts = [];
        foreach ($milestones as $ms_id => $ms) {
            $subset = $subsets[strval($ms_id)] ?? [];
            if (empty($subset)) {
                continue;
            }

            $events = [];
            $open_at_start = null;
            $done = 0;
            foreach ($subset as $issue) {
                $id = intval($issue->id);
                $is_done = $isDone($issue->value);
                if ($is_done) {
                    $done++;
                }
                $issue_moves = $moves[$id] ?? [];
                if ($inWindow($issue->created_at)) {
                    $filed = substr(strval($issue->created_at), 0, 10);
                    $events[] = [$filed, +1];
                    if (array_key_exists($id, $done_before)) {
                        // Exment's first revision holds the value as filed, so
                        // this is "filed as done".
                        if ($done_before[$id]) {
                            $events[] = [$filed, -1];
                        }
                    } elseif ($is_done) {
                        // done with no history at all (revisions off): the last
                        // update stands in for the day it closed
                        $events[] = [substr(strval($issue->updated_at ?? $issue->created_at), 0, 10), -1];
                    }
                } else {
                    // Filed before the window opened: it counts in what was
                    // still open when it did.
                    $was_done = array_key_exists($id, $done_before) ? $done_before[$id] : $is_done;
                    $open_at_start = intval($open_at_start) + ($was_done ? 0 : 1);
                }
                foreach ($issue_moves as $move) {
                    $events[] = [$move[0], $move[1] ? -1 : +1];
                }
            }
            usort($events, function ($a, $b) {
                return strcmp($a[0], $b[0]);
            });

            $points = [];
            $remaining = 0;
            if ($open_at_start !== null) {
                $remaining = $open_at_start;
                $points[$window_day] = $remaining;
            }
            foreach ($events as $event) {
                $remaining += $event[1];
                $points[strval($event[0])] = $remaining;
            }
            $series = [];
            foreach ($points as $day => $value) {
                $series[] = [strval($day), $value];
            }

            $chart = [
                'id' => strval($ms_id),
                'l' => $ms['l'],
                'start' => $ms['start'],
                'due' => $ms['due'],
                'total' => count($subset),
                'done' => $done,
                'points' => $series,
            ];
            if ($cut !== null && $needs[strval($ms_id)] < $window_day) {
                $chart['partial'] = true;
            }
            $charts[] = $chart;
        }

        return $charts;
    }

    /**
     * @param \Exceedone\Exment\Model\CustomValue $project
     * @param mixed $me
     * @return array<string, mixed>
     */
    protected function buildWiki($project, $me)
    {
        $wiki_table = CustomTable::getEloquent($this->def['wiki_table']);
        if (!isset($wiki_table)) {
            return ['enabled' => false, 'pages' => [], 'create_url' => null];
        }
        $wk = $this->def['wiki'];

        $pages = $wiki_table->getValueModel()
            ->where('parent_id', $project->id)
            ->where('parent_type', $this->project_table->table_name)
            ->get();
        $ids = $pages->pluck('id')->toArray();
        // ISS00257: one query for the shares of all of this project's pages.
        $wiki_table->setGridAuthoritable($pages);
        $stars = Star::counts(Star::TYPE_STAR, $wiki_table->table_name, $ids);
        $my_stars = Star::mine(Star::TYPE_STAR, $wiki_table->table_name, $ids, $me);
        $keeps_history = boolval($wiki_table->getOption('revision_flg', true));

        $out = [];
        foreach ($pages as $page) {
            $docs = [];
            foreach ($page->getDocuments(['count' => 10]) as $doc) {
                $docs[] = [
                    'name' => strval(array_get($doc->value, 'document_name', '')),
                    'url' => admin_url('files', [array_get($doc->value, 'file_uuid')]),
                ];
            }
            $out[] = [
                'id' => $page->id,
                'title' => strval(array_get($page->value, array_get($wk, 'title'), $page->getLabel())),
                'parent' => intval(array_get($page->value, array_get($wk, 'parent'))) ?: null,
                'content' => strval(array_get($page->value, array_get($wk, 'content'), '')),
                'up' => $this->fmtAt($page->updated_at ?? $page->created_at),
                'upby' => intval($page->updated_user_id ?? $page->created_user_id),
                'star' => intval(array_get($stars, strval($page->id), 0)),
                'my_star' => in_array(strval($page->id), $my_stars),
                'edit_url' => admin_url('data', [$wiki_table->table_name, $page->id, 'edit']),
                'hist_url' => admin_url('data', [$wiki_table->table_name, $page->id, 'compare']),
                // Whether the history button may show. Exment's compare screen
                // has a history only where the table keeps one and only for
                // whoever may edit the page - getRevisions returns none for
                // anyone else, and the screen then fails with a 500 - so the
                // question is Exment's own, asked with the model, which spares
                // it reading the row again.
                'hist' => $keeps_history && $wiki_table->hasPermissionEditData($page),
                'files' => $docs,
            ];
        }

        return [
            'enabled' => true,
            'pages' => $out,
            // Withheld where a new page could only be refused, which hides
            // the ページ追加 link.
            'create_url' => $this->childAddable($wiki_table, $project)
                ? admin_url('data', [$wiki_table->table_name, 'create']) . '?select_parent=' . $project->id
                : null,
        ];
    }

    /**
     * Every attachment of the project: its own documents, then each
     * issue's and wiki page's.
     *
     * @param \Exceedone\Exment\Model\CustomValue $project
     * @param \Illuminate\Support\Collection $issues
     * @param array<int, array<string, mixed>> $rows
     * @param array<string, mixed> $wiki
     * @return array<string, mixed>
     */
    protected function buildFiles($project, $issues, array $rows, array $wiki)
    {
        $out = [];
        $partial = false;
        foreach ($this->documentsOf($project, $issues, $wiki, $partial) as $doc) {
            $out[] = [
                'name' => $doc['name'],
                'url' => $doc['url'],
                'by' => $doc['by'],
                'at' => $this->fmtAt($doc['ts']),
                'src' => $doc['src'],
            ];
        }
        usort($out, function ($a, $b) {
            return strcmp($b['at'], $a['at']);
        });

        // The list holds the newest files of each place, not all of them;
        // the screen says so rather than passing a cut list off as complete.
        return ['rows' => $out, 'partial' => $partial];
    }

    /**
     * The raw document list feeding both the files tab and the feed.
     *
     * @param \Exceedone\Exment\Model\CustomValue $project
     * @param \Illuminate\Support\Collection $issues
     * @param array<string, mixed>|null $wiki
     * @param bool|null $partial set to true when a source had more files than
     *        were read
     * @return array<int, array<string, mixed>>
     */
    protected function documentsOf($project, $issues, $wiki = null, &$partial = null)
    {
        // Per source - the project, its issues, its wiki - the newest this
        // many. One more is read to know whether there were more.
        $limit = 50;
        $partial = false;
        $keys = [];
        $sources = [[$this->project_table->table_name, [$project->id], ['' => PortalDefinition::trans('files.project')]]];
        $issue_map = [];
        foreach ($issues as $issue) {
            $issue_map[$issue->id] = $this->issueKey($project, $issue->id);
        }
        $sources[] = [$this->issue_table->table_name, array_keys($issue_map), $issue_map];
        if (is_array($wiki) && $wiki['enabled']) {
            $page_map = collect($wiki['pages'])->pluck('title', 'id')->map(function ($t) {
                return strval(PortalDefinition::trans('files.wiki_page', ['title' => $t]));
            })->toArray();
            $sources[] = [$this->def['wiki_table'], array_keys($page_map), $page_map];
        }

        $out = [];
        foreach ($sources as $source) {
            list($type, $ids, $map) = $source;
            if (empty($ids)) {
                continue;
            }
            $docs = getModelName(SystemTableName::DOCUMENT)::whereIn('parent_id', $ids)
                ->where('parent_type', $type)
                ->orderBy('created_at', 'desc')->take($limit + 1)->get();
            if ($docs->count() > $limit) {
                $partial = true;
                $docs = $docs->take($limit);
            }
            foreach ($docs as $doc) {
                $out[] = [
                    'name' => strval(array_get($doc->value, 'document_name', '')),
                    'url' => admin_url('files', [array_get($doc->value, 'file_uuid')]),
                    'by' => intval($doc->created_user_id),
                    'ts' => strval($doc->created_at),
                    'src' => strval(array_get($map, $doc->parent_id, array_get($map, ''))),
                ];
            }
        }

        return $out;
    }

    /* ================================================================== */
    /* small helpers                                                      */
    /* ================================================================== */

    /**
     * @param mixed $id
     * @return \Exceedone\Exment\Model\CustomValue
     */
    /**
     * The page of a project the caller cannot open: not a member, or no such project.
     */
    protected function notMemberPage(Content $content)
    {
        $title = strval(PortalDefinition::trans('not_member.title'));
        $body = '<div class="box box-default" style="padding:24px;max-width:640px;">'
            . '<h3 style="margin-top:0;">' . esc_html($title) . '</h3>'
            . '<p>' . esc_html(strval(PortalDefinition::trans('not_member.body'))) . '</p>'
            . '<p><a class="btn btn-default" href="' . esc_html(PortalDefinition::url()) . '">' . esc_html(strval(PortalDefinition::trans('not_member.back'))) . '</a></p>'
            . '</div>';

        return response($content->title($title)->body($body)->render(), 404);
    }

    /**
     * Some routes (stamp, feed, read) skip Exment's admin.morph middleware to
     * stay light. Without the morph map a row's shares are looked up under the
     * class name instead of the table name, so enableAccess() refuses a person
     * who is in the project only through a share (a role group). Define the map
     * here when it is missing, as PluginPage::apiCall does for the lightapi.
     */
    protected function ensureMorphMap()
    {
        if (!isset($this->project_table)
            || \Illuminate\Database\Eloquent\Relations\Relation::getMorphedModel($this->project_table->table_name) !== null) {
            return;
        }
        $lazy = method_exists(\Exceedone\Exment\Services\ClassBuilder::class, 'autoloaderRegistered')
            && \Exceedone\Exment\Services\ClassBuilder::autoloaderRegistered();
        \Exceedone\Exment\Middleware\Morph::defineMorphMap($lazy);
    }

    protected function findProject($id)
    {
        if (!isset($this->project_table) || !isset($this->issue_table)) {
            abort(404);
        }
        $this->ensureMorphMap();
        $ref = trim(strval($id));
        if (!ctype_digit($ref)) {
            // By code (ISS00440). Not there and not allowed answer alike.
            $project = $this->projectByAnyCode($ref);
            if (!isset($project)) {
                abort(404);
            }

            return $project;
        }
        $project = $this->project_table->getValueModel()->find($ref);
        if (!isset($project)) {
            abort(404);
        }
        if ($project->enableAccess() !== true) {
            abort(403);
        }

        return $project;
    }

    /**
     * @param \Exceedone\Exment\Model\CustomValue $project
     * @param mixed $issue_id
     * @return \Exceedone\Exment\Model\CustomValue
     */
    protected function findIssue($project, $issue_id)
    {
        $this->ensureMorphMap();
        $issue = $this->issue_table->getValueModel()->find($issue_id);
        if (!isset($issue) || intval($issue->parent_id) != intval($project->id)
            || $issue->parent_type != $this->project_table->table_name) {
            abort(404);
        }
        if ($issue->enableAccess() !== true) {
            abort(403);
        }

        return $issue;
    }

    /**
     * The display key Backlog users live by: PROJECTKEY-id.
     *
     * @param \Exceedone\Exment\Model\CustomValue $project
     * @param mixed $issue_id
     * @return string
     */
    protected function issueKey($project, $issue_id)
    {
        $key = strtoupper(strval(array_get($project->value, array_get($this->def['project'], 'key'), '')));

        return ($key !== '' ? $key : '#') . '-' . $issue_id;
    }

    /**
     * key => ['l' =>, 'c' =>] of a select column, plus the option order.
     *
     * Handles both shapes a portal install can be in. Before the schema
     * upgrade, status / type / priority are word lists baked into the column
     * and the key is the word. After it, they are rows of a table the project
     * owns and the key is the row id. The contract stays the same either way,
     * which is why the whole front end is unaware the change happened.
     *
     * A row-backed list also carries 'done', so the burndown and the overdue
     * filter can stop guessing from a hardcoded list of words.
     *
     * @param CustomTable|null $custom_table
     * @param string|null $column_name
     * @param \Exceedone\Exment\Model\CustomValue|null $project scope for a per-project list
     * @return array{0: array<string, array<string, string>>, 1: array<int, string>}
     */
    protected function selectMeta($custom_table, $column_name, $project = null)
    {
        if (!isset($custom_table) || is_nullorempty($column_name)) {
            return [[], []];
        }
        $custom_column = CustomColumn::getEloquent($column_name, $custom_table);
        if (!isset($custom_column)) {
            return [[], []];
        }
        if ($custom_column->column_type == ColumnType::SELECT_TABLE) {
            return $this->rowMeta($custom_column, $project);
        }
        if (!in_array($custom_column->column_type, [ColumnType::SELECT, ColumnType::SELECT_VALTEXT])) {
            return [[], []];
        }

        $picked = GridCellStyle::parseValueColors($custom_column->getOption('grid_value_colors'));
        $meta = [];
        $order = [];
        $index = 0;
        foreach ($custom_column->createSelectOptions() as $key => $label) {
            $color = array_get($picked, strval($key) . '.color') ?: $this->paletteColor($index);
            $meta[strval($key)] = ['l' => strval($label), 'c' => $this->safeColor($color, $index), 'o' => $index];
            $order[] = strval($key);
            $index++;
        }

        return [$meta, $order];
    }

    /**
     * The same map, read from the rows of the table a select_table column
     * points at.
     *
     * Only the lists this plugin created carry a colour and a display order of
     * their own; anything else the customer points a column at still gets a
     * usable answer from its label column and the palette.
     *
     * @param CustomColumn $custom_column
     * @param \Exceedone\Exment\Model\CustomValue|null $project
     * @return array{0: array<string, array<string, string>>, 1: array<int, string>}
     */
    protected function rowMeta(CustomColumn $custom_column, $project)
    {
        $target = $custom_column->select_target_table;
        if (!isset($target)) {
            return [[], []];
        }

        $map = $this->rowColumns($target->table_name);
        $query = $target->getValueModel()->newQuery();

        // A per-project list only shows the project's own rows. Rows with no
        // parent are shared, which is what a space with no projects yet has.
        $project_id = is_object($project) ? $project->id : (is_numeric($project) ? intval($project) : null);
        $scoped = isset($project_id) && isset($map['scoped']);
        if ($scoped) {
            $query->where(function ($w) use ($project_id) {
                $w->where('parent_id', $project_id)->orWhereNull('parent_id');
            });
        }

        $meta = [];
        $order = [];
        $index = 0;
        $rows = $query->get()->sortBy(function ($row) use ($map) {
            return isset($map['order']) ? intval(array_get($row->value, $map['order'], 0)) : 0;
        })->values();
        if ($scoped) {
            $rows = $this->withoutUnusedShared($rows, $custom_column, intval($project_id));
        }

        foreach ($rows as $row) {
            $label = isset($map['name'])
                ? strval(array_get($row->value, $map['name'], ''))
                : strval($row->getLabel());
            if ($label === '') {
                $label = strval($row->getLabel());
            }

            $color = isset($map['color']) ? strval(array_get($row->value, $map['color'], '')) : '';
            $entry = [
                'l' => $label,
                'c' => $this->safeColor($color, $index),
                'o' => $index,
            ];
            if (isset($map['done'])) {
                $entry['done'] = boolval(array_get($row->value, $map['done']));
            }

            $meta[strval($row->id)] = $entry;
            $order[] = strval($row->id);
            $index++;
        }

        return [$meta, $order];
    }

    /**
     * The shared rows a project has no use for, taken out of its list.
     *
     * Setup seeds one shared copy of the status and type lists when it runs
     * before the first project exists, and every project gets its own copy
     * the moment it is created (PortalUpgrade::seedProject). Showing both put
     * 未対応 twice in the status tabs - the shared one always at zero - and
     * every type twice in the filter and in the bulk update, where picking
     * the wrong one moved an issue to a status the board does not show.
     *
     * A shared row stays while an issue of the project still points at it -
     * an install whose picker offered the whole table can have one - so that
     * issue keeps its label. A project with no rows of its own keeps them all.
     *
     * @param \Illuminate\Support\Collection $rows the project's rows and the shared ones
     * @param CustomColumn $custom_column the issue column that points at the list
     * @param int $project_id
     * @return \Illuminate\Support\Collection
     */
    protected function withoutUnusedShared($rows, CustomColumn $custom_column, int $project_id)
    {
        $shared = $rows->filter(function ($row) {
            return is_null($row->parent_id);
        });
        if ($shared->isEmpty() || $shared->count() === $rows->count()) {
            return $rows;
        }

        $key = $custom_column->getQueryKey();
        $used = $custom_column->custom_table_cache->getValueModel()->newQuery()
            ->where('parent_id', $project_id)
            ->whereIn($key, $shared->map(function ($row) {
                return strval($row->id);
            })->values()->all())
            ->select($key . ' as pp_ref')
            ->distinct()
            ->pluck('pp_ref')
            ->map('strval')
            ->all();

        return $rows->filter(function ($row) use ($used) {
            return !is_null($row->parent_id) || in_array(strval($row->id), $used, true);
        })->values();
    }

    /**
     * Which columns of a portal master table hold the label, colour, order and
     * done flag, or an empty map when the table is not one of ours.
     *
     * @param string|null $table_name
     * @return array<string, mixed>
     */
    protected function rowColumns($table_name)
    {
        $roles = [
            'status_table' => ['key' => 'status_row', 'scoped' => true],
            'issue_type_table' => ['key' => 'issue_type_row', 'scoped' => true],
            'priority_table' => ['key' => 'priority_row', 'scoped' => false],
            'resolution_table' => ['key' => 'resolution_row', 'scoped' => false],
        ];

        foreach ($roles as $role => $meta) {
            if (array_get($this->def, $role) !== $table_name) {
                continue;
            }
            $map = (array) array_get($this->def, $meta['key'], []);
            if ($meta['scoped']) {
                $map['scoped'] = true;
            }

            return $map;
        }

        return [];
    }

    /**
     * @param int $index
     * @return string
     */
    protected function paletteColor($index)
    {
        $palette = ['#c0392b', '#e67e22', '#f1c40f', '#27ae60', '#95a5a6', '#3c8dbc', '#8e44ad', '#16a085'];

        return $palette[$index % count($palette)];
    }

    /**
     * A colour safe to drop into a style attribute. A master row's colour is
     * free text, so a value such as "#fff;position:fixed;inset:0;z-index:9999"
     * would otherwise ride into a pill's style and paint a full-screen overlay
     * - escaping does not stop that, because the value never closes the
     * attribute. Only a plain hex colour is let through; anything else falls
     * back to the palette. The front end checks this again when it renders, but
     * fixing it at the source also protects the server-rendered dashboard.
     *
     * @param mixed $value
     * @param int $index palette index for the fallback
     */
    protected function safeColor($value, int $index): string
    {
        $value = strval($value);

        return preg_match('/^#[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/', $value) ? $value : $this->paletteColor($index);
    }

    /**
     * The status keys this project counts as finished.
     *
     * Row-backed statuses answer for themselves through their done flag; a
     * word list has no such flag, so the shipped list of words stands in.
     *
     * @param \Exceedone\Exment\Model\CustomValue|null $project
     * @param string|null $status_column
     * @return array<int, string>
     */
    protected function doneKeys($project, $status_column)
    {
        list($meta) = $this->selectMeta($this->issue_table, $status_column, $project);

        $keys = [];
        $flagged = false;
        foreach ($meta as $key => $entry) {
            if (!array_key_exists('done', $entry)) {
                continue;
            }
            $flagged = true;
            if ($entry['done']) {
                $keys[] = strval($key);
            }
        }

        return $flagged ? $keys : (array) $this->def['done_statuses'];
    }

    /**
     * Backlog's priority arrows, dealt by position: the first options point
     * up (urgent, high...), the middle points right, the last points down.
     *
     * @param array<string, array<string, string>> $meta by reference
     * @param array<int, string> $order
     * @return void
     */
    protected function assignPriorityArrows(array &$meta, array $order)
    {
        $count = count($order);
        foreach ($order as $index => $key) {
            if ($count >= 4 && $index == 0) {
                $arrow = 'dd';
            } elseif ($index < intdiv($count, 2)) {
                $arrow = 'up';
            } elseif ($index == intdiv($count, 2) && $count % 2 == 1) {
                $arrow = 'right';
            } elseif ($index == $count - 1) {
                $arrow = 'down';
            } else {
                $arrow = 'right';
            }
            $meta[$key]['arrow'] = $arrow;
        }
    }

    /**
     * Milestones of this project - its own rows plus anything its issues
     * already point at.
     *
     * @param \Exceedone\Exment\Model\CustomValue $project
     * @param \Illuminate\Support\Collection $issues
     * @return array<string, array<string, mixed>>
     */
    protected function buildMilestones($project, $issues)
    {
        $ms_table = CustomTable::getEloquent($this->def['milestone_table']);
        if (!isset($ms_table)) {
            return [];
        }
        $ms = $this->def['milestone'];
        $used = $issues->map(function ($issue) {
            return strval(array_get($issue->value, array_get($this->def['issue'], 'milestone'), ''));
        })->filter()->unique();

        // Filtered in SQL, not in PHP. The old form read every milestone row
        // in the install and threw away the ones belonging to other
        // projects - fine at 30 rows, a full table scan and 10k hydrated
        // models at 10k.
        $records = $this->scopedToProject($ms_table, array_get($ms, 'project'), $project->id, $used)->get();

        $out = [];
        foreach ($records as $record) {
            $out[strval($record->id)] = [
                'l' => strval(array_get($record->value, array_get($ms, 'name'), $record->getLabel())),
                'start' => $this->rawDate(array_get($record->value, array_get($ms, 'start'))),
                'due' => $this->rawDate(array_get($record->value, array_get($ms, 'due'))),
            ];
        }
        uasort($out, function ($a, $b) {
            if (is_null($a['due']) != is_null($b['due'])) {
                return is_null($a['due']) ? 1 : -1;
            }
            return strcmp(strval($a['due']), strval($b['due']));
        });

        return $out;
    }

    /**
     * id => label of a lookup table (categories), scoped like milestones.
     *
     * @param string $table_name
     * @param \Illuminate\Support\Collection $issues
     * @param string|null $issue_column
     * @return array<string, string>
     */
    protected function buildLookup($table_name, $issues, $issue_column, $project = null)
    {
        $table = CustomTable::getEloquent($table_name);
        if (!isset($table) || is_nullorempty($issue_column)) {
            return [];
        }
        $used = $issues->flatMap(function ($issue) use ($issue_column) {
            return collect((array) array_get($issue->value, $issue_column, []))->map('strval');
        })->unique();

        // $used was computed and then ignored: every row of the table was
        // loaded whatever project it belonged to. Scoped the same way
        // milestones are, so the cost follows the project rather than the
        // install.
        $query = $this->scopedToProject($table, 'project', isset($project) ? $project->id : null, $used);

        $out = [];
        foreach ($query->get() as $record) {
            $out[strval($record->id)] = strval($record->getLabel());
        }

        return $out;
    }

    /**
     * Rows of a per project master list: the ones this project owns, plus
     * any the issues on screen already point at.
     *
     * The second half matters - an issue may still carry a milestone that
     * was moved to another project, and dropping it from the map turns the
     * issue's own field blank on screen.
     *
     * @param CustomTable $table
     * @param string|null $projectColumn the column naming the owning project
     * @param mixed $projectId
     * @param \Illuminate\Support\Collection|null $used ids the issues refer to
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function scopedToProject(CustomTable $table, $projectColumn, $projectId, $used = null)
    {
        $query = $table->getValueModel()->newQuery();
        $column = is_nullorempty($projectColumn) ? null : CustomColumn::getEloquent($projectColumn, $table);
        $ids = isset($used) ? $used->filter()->values()->all() : [];

        if (!isset($column) || is_nullorempty($projectId)) {
            // Nothing to scope by. Keep the ids filter if there is one, so
            // this is never worse than the whole table.
            return $ids ? $query->whereIn('id', $ids) : $query;
        }

        return $query->where(function ($w) use ($column, $projectId, $ids) {
            if ($column->index_enabled) {
                $w->where($column->getIndexColumnName(), strval($projectId));
            } else {
                $w->where('value->' . $column->column_name, strval($projectId));
            }
            if ($ids) {
                $w->orWhereIn('id', $ids);
            }
        });
    }

    /**
     * @param array<int, mixed> $ids
     * @return array<string, array<string, string>>
     */
    protected function usersFor(array $ids)
    {
        $ids = array_values(array_filter(array_unique(array_map('intval', $ids))));
        if (empty($ids)) {
            return [];
        }
        $orgs = $this->organisationNames($ids);
        $avatars = PortalMembership::avatars($ids);
        $out = [];
        foreach (CustomTable::getEloquent(SystemTableName::USER)->getValueModel()->find($ids) as $user) {
            $name = strval($user->getLabel());
            $out[strval($user->id)] = [
                'n' => $name,
                'i' => PortalMembership::initials($name),
                // The avatar the user registered in Exment, if any (ISS00254).
                'a' => array_get($avatars, strval($user->id), ''),
                'c' => PortalMembership::avatarColor($name),
                // Who this is, for the card behind an @mention. Sent with the
                // page because the card is read on the way past a name: a
                // round trip for a login id nobody is waiting for is a round
                // trip the reader can feel.
                'cd' => strval(array_get($user->value, 'user_code')),
                'og' => array_get($orgs, strval($user->id), []),
            ];
        }

        return $out;
    }

    /**
     * The organizations each of these users belongs to, in two queries for
     * the whole set rather than two per person.
     *
     * @param array<int, int> $ids
     * @return array<string, array<int, string>>
     */
    protected function organisationNames(array $ids)
    {
        if (empty($ids)) {
            return [];
        }
        try {
            $orgTable = CustomTable::getEloquent(SystemTableName::ORGANIZATION);
            $userTable = CustomTable::getEloquent(SystemTableName::USER);
            $relation = ($orgTable && $userTable)
                ? \Exceedone\Exment\Model\CustomRelation::getRelationByParentChild(
                    $orgTable,
                    $userTable,
                    \Exceedone\Exment\Enums\RelationType::MANY_TO_MANY
                )
                : null;
            if (!$relation) {
                return [];
            }
            $pivot = \DB::table($relation->getRelationName())->whereIn('child_id', $ids)->get();
            if ($pivot->isEmpty()) {
                return [];
            }
            $names = [];
            foreach ($orgTable->getValueModel()->newQuery()->whereIn('id', $pivot->pluck('parent_id')->unique())->get() as $org) {
                $names[strval($org->id)] = strval($org->getLabel());
            }
            $out = [];
            foreach ($pivot as $link) {
                $label = array_get($names, strval($link->parent_id));
                if ($label === null) {
                    continue;
                }
                $key = strval($link->child_id);
                $out[$key] = array_values(array_unique(array_merge(array_get($out, $key, []), [$label])));
            }

            return $out;
        } catch (\Throwable $e) {
            \Log::warning('pm_portal organisation lookup failed: ' . $e->getMessage());

            return [];
        }
    }

    /**
     * Revisions of some records, whole-value snapshots.
     *
     * @param string $morph_type
     * @param array<int, mixed> $ids
     * @param int $take
     * @param string $order
     * @return array<int, object>
     */
    protected function revisionsOf($morph_type, array $ids, $take = 200, $order = 'desc')
    {
        if (empty($ids)) {
            return [];
        }

        return static::sortedRows(\DB::table('revisions')
            ->where('revisionable_type', $morph_type)
            ->whereIn('revisionable_id', $ids)
            ->whereNull('deleted_at'), [['created_at', $order], ['id', $order]], $take);
    }

    /**
     * The rows of a query already in its order (orderBy / orderByRaw), sorted over the ids only
     * and then read by id - see sortedRows(). Takes $take rows after $skip; the same kind of
     * collection as get() (an Eloquent one for an Eloquent query).
     *
     * @param \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder $query
     * @param array<int, string>|null $columns the columns of a query builder's rows (get($columns))
     * @return \Illuminate\Support\Collection
     */
    /** The ids takeSorted() sorts at most (the first step reads them unsorted). */
    const SORT_MATCH_CAP = 20000;

    public static function takeSorted($query, int $take, int $skip = 0, ?array $columns = null)
    {
        $eloquent = $query instanceof \Illuminate\Database\Eloquent\Builder;
        $base = $eloquent ? $query->getQuery() : $query;
        $table = $eloquent ? $query->getModel()->getTable() : $base->from;
        // 1. the ids that match, not sorted: a WHERE that reads value (a LIKE over a field of the
        // JSON) makes MySQL carry value into the sort, whatever is selected
        $match = (clone $query)->reorder();
        $matchBase = $eloquent ? $match->getQuery() : $match;
        $matchBase->columns = null;
        $matchBase->bindings['select'] = [];
        $matchBase->offset = null;
        $matchBase->limit = null;
        $matched = $match->select($table . '.id')->take(static::SORT_MATCH_CAP)->pluck($table . '.id')->all();
        if (empty($matched)) {
            return $eloquent ? $query->getModel()->newCollection() : collect();
        }
        // 2. those ids in the order: the WHERE reads nothing but the id now
        $keys = clone $query;
        $keysBase = $eloquent ? $keys->getQuery() : $keys;
        $keysBase->wheres = [];
        $keysBase->bindings['where'] = [];
        $keysBase->columns = null;
        $keysBase->bindings['select'] = [];
        $keys->select($table . '.id')->whereIn($table . '.id', $matched);
        if ($skip > 0) {
            $keys->skip($skip);
        }
        $keys->take($take);
        $ids = $keys->pluck($table . '.id')->map(function ($v) {
            return intval($v);
        })->all();
        $empty = $eloquent ? $query->getModel()->newCollection() : collect();
        if (empty($ids)) {
            return $empty;
        }
        $rowsQuery = (clone $query)->reorder();
        $rowsQuery->offset = null;
        $rowsQuery->limit = null;
        $rows = [];
        foreach ($rowsQuery->whereIn($table . '.id', $ids)->get($columns ?? ['*']) as $row) {
            $rows[intval($row->id)] = $row;
        }
        $out = [];
        foreach ($ids as $id) {
            if (isset($rows[$id])) {
                $out[] = $rows[$id];
            }
        }

        return $eloquent ? $query->getModel()->newCollection($out) : collect($out);
    }

    /**
     * The rows of a query in an order, sorted over their ids only, then read by id: an ORDER BY
     * over whole rows sorts the rows themselves, and one with a body of 150,000 characters does
     * not fit MySQL's sort buffer (1038 Out of sort memory, 256 KB by default).
     *
     * @param \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder $query
     * @param array<int, array{0: string, 1: string}> $orders column, direction
     * @return array<int, mixed>
     */
    public static function sortedRows($query, array $orders, ?int $take = null): array
    {
        $keys = clone $query;
        foreach ($orders as [$column, $dir]) {
            $keys->orderBy($column, $dir);
        }
        if ($take !== null) {
            $keys->take($take);
        }
        $table = $query instanceof \Illuminate\Database\Eloquent\Builder ? $query->getModel()->getTable() : $query->from;
        $ids = $keys->pluck($table . '.id')->all();
        if (empty($ids)) {
            return [];
        }
        $rows = [];
        foreach ((clone $query)->whereIn($table . '.id', $ids)->get() as $row) {
            $rows[intval($row->id)] = $row;
        }
        $out = [];
        foreach ($ids as $id) {
            if (isset($rows[intval($id)])) {
                $out[] = $rows[intval($id)];
            }
        }

        return $out;
    }

    /**
     * One column definition, resolved once per request.
     *
     * @param CustomTable $custom_table
     * @param string $column_name
     * @return CustomColumn|null
     */
    protected function columnOf($custom_table, $column_name)
    {
        $key = strval($custom_table->id) . ':' . strval($column_name);
        if (!array_key_exists($key, $this->column_cache)) {
            $this->column_cache[$key] = CustomColumn::getEloquent($column_name, $custom_table);
        }

        return $this->column_cache[$key];
    }

    /**
     * One related record's label, resolved once per request.
     *
     * @param CustomTable $target
     * @param mixed $value
     * @return string
     */
    protected function labelOf($target, $value)
    {
        $key = strval($target->id) . ':' . strval($value);
        if (!array_key_exists($key, $this->label_cache)) {
            $record = $target->getValueModel()->find($value);
            $this->label_cache[$key] = isset($record) ? strval($record->getLabel()) : strval($value);
        }

        return $this->label_cache[$key];
    }

    /**
     * The changed columns of one revision, as [label, old, new] rows the
     * screen can print - Backlog's 「状態: 処理中 → 完了」 lines.
     *
     * @param CustomTable $custom_table
     * @param object $rev
     * @param array<int, string> $hide columns left out, as if unchanged
     * @return array<int, array<int, string>>
     */
    protected function diffRevision($custom_table, $rev, array $hide = [])
    {
        $memo = isset($rev->id)
            ? strval($custom_table->id) . ':' . strval($rev->id) . ':' . implode(',', $hide)
            : null;
        if ($memo !== null && array_key_exists($memo, $this->diff_cache)) {
            return $this->diff_cache[$memo];
        }
        $changes = $this->diffRevisionValues($custom_table, $rev, $hide);
        if ($memo !== null) {
            $this->diff_cache[$memo] = $changes;
        }

        return $changes;
    }

    /**
     * @param CustomTable $custom_table
     * @param object $rev
     * @param array<int, string> $hide
     * @return array<int, array<int, string>>
     */
    protected function diffRevisionValues($custom_table, $rev, array $hide)
    {
        list($old, $new) = $this->revisionValues($rev);

        $changes = [];
        foreach (array_unique(array_merge(array_keys($old), array_keys($new))) as $column_name) {
            if (in_array(strval($column_name), $hide, true)) {
                continue;
            }
            $before = array_get($old, $column_name);
            $after = array_get($new, $column_name);
            // long bodies are most of a revision; compare strings as they are
            if ((is_string($before) && is_string($after)) ? $before === $after : json_encode($before) === json_encode($after)) {
                continue;
            }
            $custom_column = $this->columnOf($custom_table, $column_name);
            if (!isset($custom_column)) {
                continue;
            }
            // the auto number is stamped by the system right after create;
            // showing it as a change is noise nobody asked about
            if ($custom_column->column_type == ColumnType::AUTO_NUMBER) {
                continue;
            }
            $from = $this->formatShort($custom_column, $before);
            $to = $this->formatShort($custom_column, $after);
            // A long text edited past the cut read 「A… → A…」, as if nothing
            // had changed; start both a little before the first difference.
            if ($from === $to && is_string($before) && is_string($after)) {
                $skip = max(0, $this->samePrefixLength($before, $after) - 6);
                $from = $this->formatShort($custom_column, $before, $skip);
                $to = $this->formatShort($custom_column, $after, $skip);
            }
            $changes[] = [
                strval($custom_column->column_view_name),
                $from,
                $to,
            ];
            if (count($changes) >= 6) {
                break;
            }
        }

        return $changes;
    }

    /**
     * A revision's old and new values, decoded once.
     *
     * @param object $rev
     * @return array<int, array<string, mixed>>
     */
    protected function revisionValues($rev)
    {
        $id = isset($rev->id) ? intval($rev->id) : null;
        if ($id !== null && isset($this->revision_values[$id])) {
            return $this->revision_values[$id];
        }
        $pair = [
            json_decode(strval($rev->old_value), true) ?: [],
            json_decode(strval($rev->new_value), true) ?: [],
        ];
        if ($id !== null) {
            $this->revision_values[$id] = $pair;
        }

        return $pair;
    }

    /**
     * ISS00257: the people, statuses, types... the revisions' changes name,
     * read one query per table up front instead of one per value in labelOf.
     *
     * @param CustomTable $custom_table
     * @param array<int, object> $revs
     * @param array<int, string> $hide
     */
    protected function primeLabels($custom_table, array $revs, array $hide = [])
    {
        $wanted = [];
        foreach ($revs as $rev) {
            if (intval($rev->revision_no) <= 1) {
                continue;
            }
            list($old, $new) = $this->revisionValues($rev);
            foreach (array_unique(array_merge(array_keys($old), array_keys($new))) as $column_name) {
                if (in_array(strval($column_name), $hide, true)) {
                    continue;
                }
                $before = array_get($old, $column_name);
                $after = array_get($new, $column_name);
                $custom_column = $this->columnOf($custom_table, $column_name);
                if (!isset($custom_column) || !in_array($custom_column->column_type, [ColumnType::USER, ColumnType::ORGANIZATION, ColumnType::SELECT_TABLE])) {
                    continue;
                }
                $target = $custom_column->select_target_table;
                if (!isset($target) || json_encode($before) === json_encode($after)) {
                    continue;
                }
                foreach (array_merge((array) $before, (array) $after) as $v) {
                    if (is_scalar($v) && !is_nullorempty($v) && !array_key_exists(strval($target->id) . ':' . strval($v), $this->label_cache)) {
                        $wanted[$target->id][0] = $target;
                        $wanted[$target->id][1][strval($v)] = true;
                    }
                }
            }
        }
        foreach ($wanted as list($target, $values)) {
            $records = $target->getValueModel()->whereIn('id', array_keys($values))->get()->keyBy('id');
            foreach (array_keys($values) as $v) {
                $record = $records->get($v);
                // what is not found stays out, for labelOf to settle as before
                if (isset($record)) {
                    $this->label_cache[strval($target->id) . ':' . strval($v)] = strval($record->getLabel());
                }
            }
        }
    }

    /**
     * A raw stored value as a short readable string.
     *
     * @param CustomColumn $custom_column
     * @param mixed $value
     * @param int $skip leading characters to drop, replaced by 「…」
     * @return string
     */
    protected function formatShort($custom_column, $value, $skip = 0)
    {
        if (is_nullorempty($value)) {
            return PortalDefinition::trans('empty');
        }
        $values = is_array($value) ? $value : [$value];

        switch ($custom_column->column_type) {
            case ColumnType::SELECT:
            case ColumnType::SELECT_VALTEXT:
                $options = $custom_column->createSelectOptions();
                $values = array_map(function ($v) use ($options) {
                    return strval(array_get($options, strval($v), $v));
                }, $values);
                break;
            // A flag is stored as 0/1, which the feed printed as it was:
            // 「Gitを使う: 0 → 1」.
            case ColumnType::YESNO:
                $values = array_map(function ($v) {
                    return PortalDefinition::trans(boolval($v) ? 'flag_on' : 'flag_off');
                }, $values);
                break;
            case ColumnType::BOOLEAN:
                $values = array_map(function ($v) use ($custom_column) {
                    foreach (['true', 'false'] as $side) {
                        $label = $custom_column->getOption($side . '_label');
                        if (strval($v) === strval($custom_column->getOption($side . '_value')) && !is_nullorempty($label)) {
                            return strval($label);
                        }
                    }

                    return $v;
                }, $values);
                break;
            case ColumnType::USER:
            case ColumnType::ORGANIZATION:
            case ColumnType::SELECT_TABLE:
                $target = $custom_column->select_target_table;
                if (isset($target)) {
                    $values = array_map(function ($v) use ($target) {
                        return $this->labelOf($target, $v);
                    }, $values);
                }
                break;
        }

        $text = implode(', ', array_map('strval', $values));
        if ($skip > 0) {
            $text = '…' . mb_substr($text, $skip);
        }

        return mb_strimwidth($text, 0, 40, '…');
    }

    /**
     * The project columns that say who is on it: manager, members, viewers,
     * and the member and viewer organizations.
     *
     * @return array<int, string>
     */
    protected function peopleColumns(): array
    {
        $pj = $this->def['project'];

        return array_values(array_filter(array_map('strval', [
            array_get($pj, 'manager'),
            array_get($pj, 'members'),
            array_get($pj, 'viewers'),
            array_get($pj, 'member_orgs'),
            array_get($pj, 'viewer_orgs'),
        ])));
    }

    /**
     * How many leading characters two strings have in common.
     *
     * @param string $a
     * @param string $b
     * @return int
     */
    protected function samePrefixLength($a, $b)
    {
        // Bytes first (XOR is zero where they agree), then back to the start
        // of the character the first difference falls in.
        $max = min(strlen($a), strlen($b));
        $bytes = strspn(substr($a, 0, $max) ^ substr($b, 0, $max), "\0");
        while ($bytes > 0 && $bytes < $max && (ord($a[$bytes]) & 0xC0) === 0x80) {
            $bytes--;
        }

        return mb_strlen(substr($a, 0, $bytes));
    }

    /**
     * Render a kanban / gantt view of the issue table, narrowed to this
     * project. The whole widget is reused as it is - drag and drop, WIP
     * limits, the drawer... all of it - only the toolbar is dropped.
     *
     * @param \Exceedone\Exment\Model\CustomValue $project
     * @param string $view_kind_type
     * @return string|null
     */
    protected function renderEmbedded($project, $view_kind_type)
    {
        $view = CustomView::where('custom_table_id', $this->issue_table->id)
            ->where('view_kind_type', $view_kind_type)
            ->orderBy('id')
            ->first();
        if (!isset($view)) {
            return null;
        }

        $grid = $view->grid_item;
        $grid->setEmbedRelation($this->project_table->table_name, $project->id);
        // The board's quick add files a card under this project through
        // Exment's own data API, which asks only whether issues may be created
        // at all. Not saved: this view object is fresh, and other boards keep
        // the setting.
        if ($view_kind_type === ViewKindType::KANBAN && !$this->childAddable($this->issue_table, $project)) {
            $view->kanban_quickadd = false;
        }
        // a card is opened in the portal's editor, not in a form window over
        // the board: the reader stays on the screen they were already on, and
        // the portal keeps its own lookups, its drafts and its comments
        $grid->setEditorHook('openNeditOverlay');

        return $grid->grid()->render();
    }

    /**
     * @param mixed $value
     * @return string|null
     */
    protected function rawDate($value)
    {
        if (is_nullorempty($value)) {
            return null;
        }
        $value = substr(strval($value), 0, 10);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
    }

    /**
     * @param mixed $at
     * @return string
     */
    protected function fmtAt($at)
    {
        // In the time zone of the user (PortalTime): the same text as before
        // for a user in the time zone of the site.
        return PortalTime::format($at, 'Y/m/d H:i');
    }
}
