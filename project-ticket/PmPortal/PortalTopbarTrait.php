<?php

namespace App\Plugins\PmPortal;

use Exceedone\Exment\Model\CustomColumn;
use Illuminate\Http\Request;

/**
 * The global bar every portal page carries - Backlog's top navigation:
 * dashboard, a project switcher, the issues this browser opened lately,
 * a quick "add issue" and a keyword search across every visible project.
 *
 * Split out of PmPortalController for the same reasons as the dashboard
 * trait: that file is long enough already, and everything here is shared
 * chrome rather than any one screen's logic.
 *
 * Depends on PortalDashboardTrait for dashboardStars() and
 * dashboardQueryKey(); the controller mixes both traits in.
 */
trait PortalTopbarTrait
{
    /**
     * Whether the signed-in user is a system administrator (ISS00256: only
     * they get the top bar's settings link).
     */
    protected static function topbarIsAdmin(): bool
    {
        try {
            $user = \Exment::user();

            return $user !== null && boolval($user->isAdministrator());
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Rows a search answer carries. */
    protected static $topbar_search_limit = 10;

    /**
     * Everything the shared bar needs, resolved once per page.
     *
     * The dashboard hands over the project cards it has already built and
     * permission filtered; every other page passes null and the same list
     * is read here the same way - the hoisted table check first, then the
     * row check per project.
     *
     * @param string|null $current project id when a project page is open
     * @param array<string, array<string, mixed>>|null $cards prebuilt dashboard cards
     * @return array<string, mixed>
     */
    protected function topbarData($current = null, ?array $cards = null): array
    {
        $projects = [];
        foreach ($cards ?? $this->topbarCards() as $card) {
            $projects[] = [
                'id' => strval($card['id']),
                'name' => strval($card['name']),
                'key' => strval($card['key']),
                'url' => strval($card['url']),
                'starred' => boolval(array_get($card, 'starred', false)),
                'archived' => boolval(array_get($card, 'archived', false)),
                'can_add' => boolval(array_get($card, 'can_add', false)),
            ];
        }

        // Starred first, archived last; within a band the table order stays
        // (usort is stable on this PHP), so children still follow parents.
        usort($projects, function ($a, $b) {
            return [$a['archived'], !$a['starred']] <=> [$b['archived'], !$b['starred']];
        });

        return [
            'projects' => $projects,
            'current' => isset($current) ? strval($current) : null,
            'dashboard_url' => PortalDefinition::url(),
            'search_url' => PortalDefinition::url('search'),
            'projects_url' => PortalDefinition::url('projects'),
            'project_table' => isset($this->project_table) ? $this->project_table->table_name : '',
            // Backlog offers プロジェクトの追加 only to who may actually add;
            // the entry links to the dashboard, whose modal does the work.
            'can_create' => isset($this->project_table) && $this->project_table->enableCreate(true) === true,
            // ISS00256: the portal settings open from the top bar, for
            // administrators only (the sidebar keeps a single entry).
            'settings_url' => static::topbarIsAdmin() ? PortalDefinition::url('settings') : '',
        ];
    }

    /**
     * The projects this user may open, as minimal cards keyed by id.
     *
     * Also the permission whitelist of the search endpoint: an issue whose
     * parent is not in this map is not shown, whatever the row scope said.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function topbarCards(): array
    {
        if (!isset($this->project_table) || $this->project_table->enableAccess() !== true) {
            return [];
        }

        $pj = $this->def['project'];
        $hasArchived = CustomColumn::getEloquent('archived', $this->project_table) !== null;
        $issue_create = isset($this->issue_table) && $this->issue_table->enableCreate(true) === true;

        $cards = [];
        $projectRows = $this->project_table->getValueModel()->get();
        // ISS00257: read the shares of every project once, instead of one or
        // two queries per project in each permission check below. Always the
        // whole readable set: Exment keeps the first preload for the request.
        $this->project_table->setGridAuthoritable($projectRows);
        foreach ($projectRows as $value) {
            if (!$this->project_table->hasPermissionData($value)) {
                continue;
            }
            $cards[strval($value->id)] = [
                'id' => $value->id,
                'name' => strval($value->getLabel()),
                'key' => strtoupper(strval(array_get($value->value, array_get($pj, 'key'), ''))),
                'url' => PortalDefinition::url($value->id),
                'starred' => false,
                'archived' => $hasArchived && boolval(array_get($value->value, 'archived')),
                // The bar's 課題の追加 menu lists only where an issue can be filed.
                'can_add' => $this->childAddable($this->issue_table ?? null, $value, $issue_create),
            ];
        }
        $this->dashboardStars($cards);

        return $cards;
    }

    /**
     * The project menu's own rows, fetched when the menu is opened.
     *
     * The bar is on every page and almost nobody opens the menu, so the
     * numbers behind these rows are not worth four grouped counts per page
     * load - Backlog fetches its own menu the same way. What ships in the
     * page is the plain list, which costs one read the bar already does;
     * this endpoint upgrades it to the dashboard's project panel.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function topbarProjects(Request $request)
    {
        if (PortalSetup::needsSetup() || !isset($this->project_table) || !isset($this->issue_table)
            || $this->project_table->enableAccess() !== true) {
            return response()->json(['items' => []]);
        }

        $pj = $this->def['project'];
        list($project_status) = $this->selectMeta($this->project_table, array_get($pj, 'status'));
        list($issue_status) = $this->selectMeta($this->issue_table, array_get($this->def['issue'], 'status'), null);

        $parent_column = CustomColumn::getEloquent('parent_project', $this->project_table) !== null ? 'parent_project' : null;
        $has_archived = CustomColumn::getEloquent('archived', $this->project_table) !== null;
        $issue_create = $this->issue_table->enableCreate(true) === true;

        // One read, used twice - the same shape the dashboard builds its
        // cards from, so both screens name a parent project the same way.
        $rows = $this->project_table->getValueModel()->get();
        // ISS00257: one query for every project's shares (see topbarCards).
        $this->project_table->setGridAuthoritable($rows);
        $names = [];
        if ($parent_column) {
            foreach ($rows as $row) {
                $names[strval($row->id)] = strval($row->getLabel());
            }
        }

        $cards = [];
        foreach ($rows as $value) {
            if (!$this->project_table->hasPermissionData($value)) {
                continue;
            }
            $parent_id = $parent_column ? strval(array_get($value->value, $parent_column, '')) : '';
            $cards[strval($value->id)] = [
                'id' => strval($value->id),
                'name' => strval($value->getLabel()),
                'key' => strtoupper(strval(array_get($value->value, array_get($pj, 'key'), ''))),
                'status' => array_get($project_status, strval(array_get($value->value, array_get($pj, 'status'), ''))),
                'parent' => $parent_id === '' ? null : array_get($names, $parent_id),
                'archived' => $has_archived && boolval(array_get($value->value, 'archived')),
                'url' => PortalDefinition::url($value->id),
                'star_url' => PortalDefinition::url($value->id, 'star'),
                // The gear opens the settings tab, which is the manager's (see
                // the dashboard cards in PmPortalController::index).
                'can_edit' => $this->project_table->hasPermissionEditData($value) && PortalMembership::canManage($value),
                'can_add' => $this->childAddable($this->issue_table, $value, $issue_create),
            ];
        }

        if (!empty($cards)) {
            $this->dashboardProjectCounts($cards, $this->dashboardDoneIds($issue_status), \Carbon\Carbon::today()->format('Y-m-d'));
            $this->dashboardStars($cards);
        }

        // Same order the bar's plain list uses, so the menu does not reshuffle
        // itself the moment the answer lands.
        $items = array_values($cards);
        usort($items, function ($a, $b) {
            return [$a['archived'], !$a['starred']] <=> [$b['archived'], !$b['starred']];
        });

        return response()->json([
            'items' => $items,
            'table' => $this->project_table->table_name,
        ]);
    }

    /**
     * Keyword search behind the bar's search box.
     *
     * Matches the subject, and "PORTAL-153" or a bare "153" also matches the
     * row id, because that is how people quote issues to each other. The
     * subject lives in the value JSON, so this is a scan of the issue table -
     * bounded by parent_type, read newest first, and small enough here that
     * honesty about it beats building a search index nobody asked for. The
     * row permission scope applies to the query itself, and so does the
     * project map above: only issues of projects this user may open are
     * matched, before the limit rather than after it.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function searchIssues(Request $request)
    {
        if (PortalSetup::needsSetup() || !isset($this->project_table) || !isset($this->issue_table)) {
            return response()->json(['items' => [], 'more' => false]);
        }

        // Two characters keeps single keystrokes out; 100 keeps a pasted
        // paragraph from becoming a LIKE pattern.
        $q = $request->query('q', '');
        $q = is_scalar($q) ? trim(strval($q)) : '';
        if (mb_strlen($q) < 2) {
            return response()->json(['items' => [], 'more' => false]);
        }
        $q = mb_substr($q, 0, 100);

        $cards = $this->topbarCards();
        if (empty($cards)) {
            return response()->json(['items' => [], 'more' => false]);
        }

        $is = $this->def['issue'];
        $subject_key = $this->dashboardQueryKey(array_get($is, 'subject'));

        $id_hit = null;
        if (preg_match('/-(\d+)$/', $q, $m) || preg_match('/^(\d+)$/', $q, $m)) {
            $id_hit = intval($m[1]);
        }

        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
        $limit = intval(static::$topbar_search_limit);

        // One extra row, so the answer can say "there are more" without a
        // second count query.
        //
        // The project list is part of the query. Filtered afterwards, as it
        // used to be, the limit was spent first: the row scope alone still
        // matches issues someone opened or was assigned before they were taken
        // off a project, and a page of those crowded out the issues they may
        // open and made "more" wrong.
        $rows = $this->issue_table->getValueModel()->query()
            ->where('parent_type', $this->project_table->table_name)
            ->whereIn('parent_id', array_map('intval', array_keys($cards)))
            ->where(function ($query) use ($subject_key, $like, $id_hit) {
                $query->where($subject_key, 'LIKE', $like);
                if ($id_hit !== null) {
                    $query->orWhere('id', $id_hit);
                }
            })
            ->orderBy('updated_at', 'desc')
            ->take($limit + 1)
            ->get();

        $items = [];
        foreach ($rows as $issue) {
            if (count($items) >= $limit) {
                break;
            }
            $card = array_get($cards, strval(intval($issue->parent_id)));
            if (!isset($card)) {
                continue;
            }
            // Same key and URL shape as the dashboard feed, which the SPA
            // router is already proven to answer.
            $key = ($card['key'] !== '' ? $card['key'] : '#') . '-' . $issue->id;
            $items[] = [
                'k' => $key,
                's' => strval(array_get($issue->value, array_get($is, 'subject'), '')),
                'p' => strval($card['name']),
                'u' => $card['url'] . '#issue-' . rawurlencode($key),
            ];
        }

        return response()->json([
            'items' => $items,
            'more' => count($rows) > $limit,
        ]);
    }
}
