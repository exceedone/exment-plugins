<?php

namespace App\Plugins\PmPortal;

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
    private const NOTIFY_PICK_LIMIT = 100;

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
                'archived' => $archived,
                'count' => 0,
                'url' => PortalDefinition::url($value->id),
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
        $projects = [];
        foreach ([true, false] as $starred) {
            foreach ($cards as $card) {
                if ($card['starred'] !== $starred || ($card['archived'] && !$showArchived)) {
                    continue;
                }
                $projects[] = $card;
            }
        }

        return $this->AdminContent($content)->body(view(PortalDefinition::view('list'), [
            'projects' => $projects,
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
     * One project's portal.
     */
    public function show(Request $request, Content $content, $id = null)
    {
        if (PortalSetup::needsSetup()) {
            return redirect(PortalDefinition::url('setup'));
        }

        $project = $this->findProject($id);

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
            'users' => $this->usersFor(collect($detail['timeline'])->pluck('by')->filter()->unique()->values()->toArray()),
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
            $user_ids = array_merge($user_ids, collect($details[$key]['timeline'])->pluck('by')->filter()->all());
            $details[$key]['issue'] = $this->buildIssueRow($project, $issue);
        }

        return response()->json([
            'ok' => true,
            'details' => $details,
            'users' => $this->usersFor(array_values(array_unique($user_ids))),
        ]);
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

        $comment_rows = getModelName(SystemTableName::COMMENT)::whereIn('parent_id', $ids)
            ->where('parent_type', $issue_name)
            ->get();
        $comment_ids = $comment_rows->pluck('id')->toArray();
        $comment_stars = Star::counts(Star::TYPE_STAR, 'comment', $comment_ids);
        $my_comment_stars = Star::mine(Star::TYPE_STAR, 'comment', $comment_ids, $me);
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

        // An archived project takes no edits and no comment changes (see
        // postComment), so the screen is not offered controls that can only
        // be refused.
        $archived = $this->projectIsArchived($project);

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
                $changes = $this->diffRevision($this->issue_table, $rev);
                if (empty($changes) || intval($rev->revision_no) <= 1) {
                    continue;
                }
                $timeline[] = [
                    'tp' => 'chg',
                    'by' => intval($rev->create_user_id),
                    'at' => $this->fmtAt($rev->created_at),
                    'ts' => strval($rev->created_at),
                    'chg' => $changes,
                ];
            }

            foreach (($comments[intval($issue->id)] ?? []) as $comment) {
                $timeline[] = [
                    'tp' => 'cmt',
                    'cid' => $comment->id,
                    'by' => intval($comment->created_user_id),
                    'at' => $this->fmtAt($comment->created_at),
                    'ts' => strval($comment->created_at),
                    'text' => strval(array_get($comment->value, 'comment_detail', '')),
                    'star' => intval(array_get($comment_stars, strval($comment->id), 0)),
                    'my_star' => in_array(strval($comment->id), $my_comment_stars),
                    'del' => !$archived && intval($comment->created_user_id) == intval($me),
                ];
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

            $out[strval($issue->id)] = [
                'description' => \Exceedone\Exment\ColumnItems\CustomColumns\Editor::replaceImgUrl(
                    strval(array_get($issue->value, array_get($this->def['issue'], 'description'), ''))
                ),
                'editable' => !$archived && $issue->enableEdit(true) === true,
                'watch' => [
                    'count' => intval(array_get($watch_counts, strval($issue->id), 0)),
                    'on' => in_array(strval($issue->id), $my_watches),
                ],
                'timeline' => collect($timeline)->map(function ($row) {
                    unset($row['ts']);
                    return $row;
                })->values()->all(),
                'files' => $files,
            ];
            if ($partial) {
                $out[strval($issue->id)]['partial'] = true;
            }
        }

        return $out;
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
        $status_meta = [];
        if ($changed) {
            if ($issue->enableEdit(true) !== true) {
                return $this->messageError(403, 'no_permission');
            }
            list($status_meta) = $this->selectMeta($this->issue_table, $status_column, $issue->parent_id);
            if (!array_key_exists($status_to, $status_meta)) {
                return $this->messageError(400, 'invalid_params');
            }
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

            // What Exment's own comment form does next (DefaultShow::addComment):
            // the issue table's notifications for a comment - its assignees,
            // notified users and creator - which a portal comment never rang.
            $this->runTableNotifies($issue, NotifySavedType::COMMENT, ['comment' => $comment]);
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
        try {
            $this->ringPortalBells($request, $project, $issue, $bell_mark, implode("\n", $lines));
        } catch (\Throwable $e) {
            // Saved is saved: an error answer now would only get the comment
            // posted a second time.
            Log::error('pm_portal bells for issue ' . $issue->id . ' failed: ' . $e->getMessage(), ['exception' => $e]);
        }

        // Answer with the refreshed detail so the screen patches itself -
        // a reload would cost another framework boot for data we already have.
        return $this->issueDetailResponse($project, $issue->id);
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
    protected function ringPortalBells(Request $request, $project, $issue, int $bell_mark, string $body): void
    {
        $me = intval(\Exment::getUserId());
        $issue_name = $this->issue_table->table_name;

        $picked = collect((array) $request->get('notify_users', []))
            ->filter(function ($v) {
                return is_numeric($v) && intval($v) > 0;
            })
            ->map(function ($v) {
                return intval($v);
            })
            ->unique()
            ->take(self::NOTIFY_PICK_LIMIT);
        $watchers = Star::userIds(Star::TYPE_WATCH, $issue_name, $issue->id);
        $candidates = $picked->merge($watchers)->unique()->values()->all();
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
        $body = PortalDefinition::notifyBody($body);
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

        return array_merge($detail, [
            'users' => $this->usersFor(collect($detail['timeline'])->pluck('by')->filter()->unique()->values()->toArray()),
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
        if ($issue->enableEdit() !== true) {
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
        $allowed_types = [Star::TYPE_STAR, Star::TYPE_WATCH];
        $allowed_targets = array_filter([
            $this->issue_table->table_name,
            $this->project_table->table_name,
            $this->def['wiki_table'],
            'comment',
        ]);
        if (!in_array($star_type, $allowed_types) || !in_array($target_type, $allowed_targets)) {
            return $this->messageError(400, 'invalid_params');
        }
        $target_id = $request->get('target_id');
        $target_id = is_scalar($target_id) ? strval($target_id) : '';
        $me = \Exment::getUserId();

        // Taking your own star or watch off asks for nothing more. A watch is a
        // subscription, and someone taken off the project has to be able to
        // end it - by then findProject() refuses them. The row is theirs; the
        // answer adds only how many marks the target still carries, on a
        // target they could read when they marked it.
        if ($target_id !== '' && !empty(Star::mine($star_type, $target_type, [$target_id], $me))) {
            return response()->json(['ok' => true] + Star::toggle($star_type, $target_type, $target_id, $me));
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
            if ($issue->enableEdit(true) !== true) {
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
        $row = $this->issue_table->getValueModel()->query()
            ->where('parent_id', $project->id)
            ->where('parent_type', $this->project_table->table_name)
            ->selectRaw('count(*) as pm_n, max(updated_at) as pm_at')
            ->first();

        return implode('/', [
            intval(array_get($row, 'pm_n', 0)),
            substr(strval(array_get($row, 'pm_at', '')), 0, 19),
            substr(strval($project->updated_at), 0, 19),
        ]);
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

        $issues = $this->issue_table->getValueModel()
            ->where('parent_id', $project->id)
            ->where('parent_type', $this->project_table->table_name)
            ->get();
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

        $rows = [];
        foreach ($issues as $issue) {
            $row = $this->buildIssueRow($project, $issue);
            $row['star'] = intval(array_get($star_counts, strval($issue->id), 0));
            $row['my_star'] = in_array(strval($issue->id), $my_stars);
            $row['my_watch'] = in_array(strval($issue->id), $my_watches);
            $rows[] = $row;
        }

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
        $feed = $this->buildFeed($project, $issues, $rows, $wiki);
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
        $details = $this->detailsFor($project, $embedded, $me);

        $user_ids = collect([intval(array_get($project->value, array_get($pj, 'manager')))])
            ->merge(collect((array) array_get($project->value, array_get($pj, 'members'), []))->map(function ($v) { return intval($v); }))
            ->merge(collect((array) array_get($project->value, array_get($pj, 'viewers'), []))->map(function ($v) { return intval($v); }))
            ->merge(collect($rows)->pluck('as')->flatten())
            ->merge(collect($rows)->pluck('by'))
            ->merge(collect($feed)->pluck('by'))
            ->merge(collect($files['rows'])->pluck('by'))
            ->merge(collect($wiki['pages'])->pluck('upby'))
            ->merge(collect($details)->pluck('timeline')->flatten(1)->pluck('by'))
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
                'members' => collect((array) array_get($project->value, array_get($pj, 'members'), []))->map(function ($v) { return intval($v); })->values(),
                'viewers' => collect((array) array_get($project->value, array_get($pj, 'viewers'), []))->map(function ($v) { return intval($v); })->values(),
                'edit_url' => admin_url('data', [$this->project_table->table_name, $project->id, 'edit']),
                'features' => $features,
                // Read-only while archived: the comment, attachment and bulk
                // routes refuse, so the screen can say so before anyone types.
                'archived' => $archived,
            ],
            'can' => $can,
            // The settings tab's server-known bits; withheld from people the
            // tab is hidden from anyway.
            'git' => $can['manage'] ? $this->gitState($project) : null,
            // Whether the issue form shows the AI button, and the numbers it
            // labels the answers with. Never the key.
            'ai' => $this->aiPayload($project),
            'settings_map' => $can['manage'] ? $this->projectSettingsMap() : null,
            'me' => intval($me),
            'today' => \Carbon\Carbon::today()->format('Y-m-d'),
            // When this payload was built, which is what "as of" means. Taken
            // here rather than from the browser so it matches every other time
            // on the screen.
            'now' => \Carbon\Carbon::now()->format('H:i'),
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
            'issues' => $rows,
            'details' => (object) $details,
            'feed' => $feed,
            'burndown' => $features['gantt'] ? $this->buildBurndown($project, $issues, $milestones) : [],
            'wiki' => $wiki,
            'files' => $files,
            'issue_table' => $issue_name,
            // The column names behind the short keys the rows use, so the
            // screen can build a create form that starts from an issue.
            'issue_cols' => $this->def['issue'],
            // The columns the schema upgrade added. Named separately because
            // an install that has not run the upgrade simply has not got them,
            // and the form has to lay out whatever is actually there.
            'issue_extra_cols' => array_get($this->def, 'issue_extra', []),
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
     * One issue as the list row / detail head sees it.
     *
     * @param \Exceedone\Exment\Model\CustomValue $project
     * @param \Exceedone\Exment\Model\CustomValue $issue
     * @return array<string, mixed>
     */
    protected function buildIssueRow($project, $issue)
    {
        $is = $this->def['issue'];
        $v = $issue->value;

        return [
            'id' => $issue->id,
            'key' => $this->issueKey($project, $issue->id),
            'url' => admin_url('data', [$this->issue_table->table_name, $issue->id]),
            'edit_url' => admin_url('data', [$this->issue_table->table_name, $issue->id, 'edit']),
            'subject' => strval(array_get($v, array_get($is, 'subject'), '')),
            'ty' => strval(array_get($v, array_get($is, 'type'), '')),
            'st' => strval(array_get($v, array_get($is, 'status'), '')),
            'pr' => strval(array_get($v, array_get($is, 'priority'), '')),
            'as' => collect((array) array_get($v, array_get($is, 'assignees'), []))->map(function ($v) { return intval($v); })->values(),
            'ms' => strval(array_get($v, array_get($is, 'milestone'), '')),
            'cat' => collect((array) array_get($v, array_get($is, 'category'), []))->map('strval')->values(),
            'sd' => $this->rawDate(array_get($v, array_get($is, 'start'))),
            'dd' => $this->rawDate(array_get($v, array_get($is, 'due'))),
            'pg' => is_numeric(array_get($v, array_get($is, 'progress'))) ? floatval(array_get($v, array_get($is, 'progress'))) : null,
            // Numbers or nothing, like the progress above: the screen prints
            // the hours straight into the page, and a column that is not a
            // decimal one could otherwise hand it any text at all.
            'eh' => is_numeric(array_get($v, array_get($is, 'estimate'))) ? floatval(array_get($v, array_get($is, 'estimate'))) : null,
            'ah' => is_numeric(array_get($v, array_get($is, 'actual'))) ? floatval(array_get($v, array_get($is, 'actual'))) : null,
            'parent' => intval(array_get($v, array_get($is, 'parent'))) ?: null,
            'up' => $this->fmtAt($issue->updated_at ?? $issue->created_at),
            // The version as the server compares it, sent back with an edit
            // so a change made in between is refused rather than overwritten.
            'ua' => $issue->updated_at ? (string) $issue->updated_at : null,
            // Who raised it. The dashboard already offers a 作成 list off this
            // column; carrying it on the row lets the project's own list do
            // the same without a second query.
            'by' => intval($issue->created_user_id) ?: null,
        ];
    }

    /**
     * The activity feed: revisions, comments, wiki edits, files and member
     * changes of one project, newest first.
     *
     * @param \Exceedone\Exment\Model\CustomValue $project
     * @param \Illuminate\Support\Collection $issues
     * @param array<int, array<string, mixed>> $rows
     * @param array<string, mixed> $wiki
     * @return array<int, array<string, mixed>>
     */
    protected function buildFeed($project, $issues, array $rows, array $wiki)
    {
        $issue_name = $this->issue_table->table_name;
        $keys = collect($rows)->keyBy('id');
        $feed = [];

        foreach ($this->revisionsOf($issue_name, $issues->pluck('id')->toArray(), 60) as $rev) {
            $row = $keys->get(intval($rev->revisionable_id));
            if (!$row) {
                continue;
            }
            $is_add = intval($rev->revision_no) <= 1;
            $changes = $is_add ? [] : $this->diffRevision($this->issue_table, $rev);
            if (!$is_add && empty($changes)) {
                continue;
            }
            $feed[] = [
                'tp' => $is_add ? 'add' : 'chg',
                'by' => intval($rev->create_user_id),
                'ts' => strval($rev->created_at),
                'kind' => 'issue',
                'id' => $row['id'],
                'key' => $row['key'],
                'label' => $row['subject'],
                'extra' => collect($changes)->take(2)->map(function ($c) {
                    return $c[0] . ': ' . $c[1] . ' → ' . $c[2];
                })->implode(' / '),
            ];
        }

        $comment_parents = [$issue_name => $keys];
        $comments = getModelName(SystemTableName::COMMENT)::whereIn('parent_id', $issues->pluck('id'))
            ->where('parent_type', $issue_name)
            ->orderBy('created_at', 'desc')->take(30)->get();
        foreach ($comments as $comment) {
            $row = $keys->get(intval($comment->parent_id));
            if (!$row) {
                continue;
            }
            $feed[] = [
                'tp' => 'cmt',
                'by' => intval($comment->created_user_id),
                'ts' => strval($comment->created_at),
                'kind' => 'issue',
                'id' => $row['id'],
                'key' => $row['key'],
                'label' => $row['subject'],
                'extra' => mb_strimwidth(strval(array_get($comment->value, 'comment_detail', '')), 0, 60, '…'),
            ];
        }

        if ($wiki['enabled']) {
            $pages = collect($wiki['pages'])->keyBy('id');
            foreach ($this->revisionsOf($this->def['wiki_table'], $pages->keys()->toArray(), 20) as $rev) {
                $page = $pages->get(intval($rev->revisionable_id));
                if (!$page) {
                    continue;
                }
                $feed[] = [
                    'tp' => intval($rev->revision_no) <= 1 ? 'wiki_add' : 'wiki',
                    'by' => intval($rev->create_user_id),
                    'ts' => strval($rev->created_at),
                    'kind' => 'wiki',
                    'id' => $page['id'],
                    'key' => '',
                    'label' => $page['title'],
                    'extra' => '',
                ];
            }
        }

        foreach ($this->documentsOf($project, $issues) as $doc) {
            $feed[] = [
                'tp' => 'file',
                'by' => intval($doc['by']),
                'ts' => $doc['ts'],
                'kind' => 'file',
                'id' => 0,
                'key' => '',
                'label' => $doc['name'],
                'extra' => $doc['src'],
            ];
        }

        // Project changes come from the project record's own history. Who was
        // added to or taken off the project is the manager's business, the
        // way the 参加ユーザー settings are: everyone else's feed shows the
        // other changes (name, dates, status...) but not the people columns.
        // Issue history is shown to every reader; see detailsFor.
        $hide = PortalMembership::canManage($project) ? [] : $this->peopleColumns();
        foreach ($this->revisionsOf($this->project_table->table_name, [$project->id], 10) as $rev) {
            if (intval($rev->revision_no) <= 1) {
                continue;
            }
            $changes = $this->diffRevision($this->project_table, $rev, $hide);
            if (empty($changes)) {
                continue;
            }
            $feed[] = [
                'tp' => 'chg',
                'by' => intval($rev->create_user_id),
                'ts' => strval($rev->created_at),
                'kind' => 'project',
                'id' => $project->id,
                'key' => '',
                'label' => strval($project->getLabel()),
                'extra' => collect($changes)->take(2)->map(function ($c) {
                    return $c[0] . ': ' . $c[1] . ' → ' . $c[2];
                })->implode(' / '),
            ];
        }

        usort($feed, function ($a, $b) {
            return strcmp($b['ts'], $a['ts']);
        });
        return collect($feed)->take(30)->map(function ($row) {
            $row['at'] = $this->fmtAt($row['ts']);
            unset($row['ts']);
            return $row;
        })->values()->toArray();
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

        $rows = \DB::table('revisions')
            ->select(['revisionable_id', 'created_at', 'old_value', 'new_value'])
            ->where('revisionable_type', $issue_name)
            ->whereIn('revisionable_id', $ids)
            ->whereNull('deleted_at')
            ->where('created_at', '>=', $from . ' 00:00:00')
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->take(self::BURNDOWN_REVISION_LIMIT + 1)
            ->get();

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
    protected function findProject($id)
    {
        if (!isset($this->project_table) || !isset($this->issue_table)) {
            abort(404);
        }
        $project = $this->project_table->getValueModel()->find($id);
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
        $palette = ['#16a085', '#3c8dbc', '#e67e22', '#8e44ad', '#2980b9', '#c0392b', '#27ae60', '#d35400', '#7f8c8d', '#1abc9c'];
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
                'c' => $palette[abs(crc32($name)) % count($palette)],
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

        return \DB::table('revisions')
            ->where('revisionable_type', $morph_type)
            ->whereIn('revisionable_id', $ids)
            ->whereNull('deleted_at')
            ->orderBy('created_at', $order)
            ->take($take)
            ->get()
            ->all();
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
        $old = json_decode(strval($rev->old_value), true) ?: [];
        $new = json_decode(strval($rev->new_value), true) ?: [];

        $changes = [];
        foreach (array_unique(array_merge(array_keys($old), array_keys($new))) as $column_name) {
            if (in_array(strval($column_name), $hide, true)) {
                continue;
            }
            $before = array_get($old, $column_name);
            $after = array_get($new, $column_name);
            if (json_encode($before) === json_encode($after)) {
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
     * The project columns that say who is on it: manager, members, viewers.
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
        $a = mb_str_split($a);
        $b = mb_str_split($b);
        $max = min(count($a), count($b));
        $n = 0;
        while ($n < $max && $a[$n] === $b[$n]) {
            $n++;
        }

        return $n;
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
        if (is_nullorempty($at)) {
            return '';
        }
        try {
            return \Carbon\Carbon::parse($at)->format('Y/m/d H:i');
        } catch (\Exception $e) {
            return strval($at);
        }
    }
}
