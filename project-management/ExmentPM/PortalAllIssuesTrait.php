<?php

namespace App\Plugins\ExmentPM;

use ExmentAdminCore\Admin\Layout\Content;
use Exceedone\Exment\Enums\SystemTableName;
use Exceedone\Exment\Model\CustomTable;
use Illuminate\Http\Request;

/**
 * The issue list across every project the user can read (ISS00264).
 *
 *   GET issues        the page
 *   GET issues/data   one page of rows, filtered and sorted on the server
 *
 * Read through CustomTable::getValueModel(), so Exment's row permissions apply as they do in a
 * project, and only from the projects the user can open (topbarCards). Filtered and paged in
 * the query, never in PHP, so an install of thousands of issues answers one page at a time.
 *
 * Status, priority and type are rows of each project: the same label is a row in every project.
 * The filters are therefore by label, each label standing for its rows in all the projects.
 */
trait PortalAllIssuesTrait
{
    /** Rows per page. */
    protected static $all_per_page = 50;

    public function allIssues(Request $request, Content $content)
    {
        if (PortalSetup::needsSetup()) {
            return redirect(PortalDefinition::url('setup'));
        }
        if (!isset($this->project_table) || !isset($this->issue_table)) {
            abort(404);
        }
        $title = strval(PortalDefinition::trans('all.title'));
        $this->setPageInfo($title, $title, '', 'fa-list-ul');
        $cards = $this->topbarCards();
        $boot = [
            'me' => intval(\Exment::getUserId()),
            'data_url' => PortalDefinition::url('issues', 'data'),
            'export_url' => PortalDefinition::url('issues', 'export'),
            'projects' => array_values(array_map(function ($c) {
                return ['id' => strval($c['id']), 'n' => $c['name'], 'k' => $c['key'], 'ar' => !empty($c['archived'])];
            }, $cards)),
            'status' => $this->allLabels('status', true),
            'priority' => $this->allLabels('priority'),
            'type' => $this->allLabels('type'),
            'users' => $this->allUsers(),
            'per' => static::$all_per_page,
        ];

        return $this->AdminContent($content)->body(view(PortalDefinition::view('all-issues'), [
            'boot' => $boot,
            'topbar' => $this->topbarData(null, $cards),
        ]));
    }

    /**
     * The same filters and order, as a CSV or Excel file (ISS00264 with ISS00275):
     * GET issues/export?…&fmt=csv|xlsx&cols=all|screen. At most PortalExportTrait's maximum.
     */
    public function allIssuesExport(Request $request)
    {
        $found = $this->allIssuesData($request, true);
        if (!is_array($found)) {
            return $this->messageError(422, 'export_empty');
        }
        [$query, $cards] = $found;
        $issues = static::takeSorted($query, static::$EXPORT_MAX)->all();
        if (empty($issues)) {
            return $this->messageError(422, 'export_empty');
        }
        $fmt = $request->query('fmt') === 'xlsx' ? 'xlsx' : 'csv';
        $columns = ['project' => ['label' => strval(PortalDefinition::trans('all.col_project')), 'type' => 'text', 'col' => null]]
            + $this->exportColumns($request->query('cols') !== 'screen');
        $all = $request->query('cols') !== 'screen';

        // the projects of the rows, read once; the related issues per project
        $byProject = [];
        foreach ($issues as $issue) {
            $byProject[intval($issue->parent_id)][] = $issue;
        }
        $projects = [];
        foreach ($this->project_table->getValueModel()->query()->whereIn('id', array_keys($byProject))->get() as $p) {
            $projects[intval($p->id)] = $p;
        }
        $related = [];
        if ($all) {
            foreach ($byProject as $pid => $list) {
                if (isset($projects[$pid])) {
                    $related += $this->exportRelated($projects[$pid], $list);
                }
            }
        }
        $users = $this->exportUserNames($issues);
        $rows = [];
        foreach ($issues as $issue) {
            $project = $projects[intval($issue->parent_id)] ?? null;
            if (!isset($project)) {
                continue;
            }
            $row = [];
            foreach ($columns as $key => $column) {
                $row[] = $key === 'project'
                    ? strval(array_get($cards, intval($issue->parent_id) . '.name', ''))
                    : $this->exportCell($project, $issue, $key, $column, $users, $related);
            }
            $rows[] = $row;
        }
        $heads = array_map(function ($c) {
            return $c['label'];
        }, array_values($columns));
        $types = array_map(function ($c) {
            return $c['type'];
        }, array_values($columns));
        $name = 'all-issues-' . now()->format('Ymd');
        try {
            return $fmt === 'xlsx' ? $this->exportXlsx($heads, $types, $rows, $name . '.xlsx') : $this->exportCsv($heads, $rows, $name . '.csv');
        } catch (\Throwable $e) {
            \Log::error('pm_portal export across the projects failed: ' . $e->getMessage(), ['exception' => $e]);

            return PortalDefinition::jsonError(500, 'export_failed');
        }
    }

    /**
     * @param bool $forExport the filtered and sorted query and the cards, for allIssuesExport
     */
    public function allIssuesData(Request $request, bool $forExport = false)
    {
        if (!isset($this->project_table) || !isset($this->issue_table)) {
            abort(404);
        }
        $is = $this->def['issue'];
        $me = intval(\Exment::getUserId());
        $today = PortalTime::today();
        $cards = $this->topbarCards();
        $list = function ($name) use ($request) {
            $raw = $request->query($name);
            $items = is_array($raw) ? $raw : explode(',', strval($raw ?? ''));
            return array_values(array_filter(array_map(function ($v) {
                return trim(strval($v));
            }, $items), 'strlen'));
        };

        // the projects: the ones asked for, of the ones the user can open
        $projectIds = array_map('strval', array_keys($cards));
        $asked = $list('pj');
        if (!empty($asked)) {
            $projectIds = array_values(array_intersect($projectIds, $asked));
        }
        $empty = ['ok' => true, 'rows' => [], 'total' => 0, 'page' => 1];
        if (empty($projectIds)) {
            return response()->json($empty);
        }

        list($status_meta) = $this->selectMeta($this->issue_table, array_get($is, 'status'), null);
        list($priority_meta) = $this->selectMeta($this->issue_table, array_get($is, 'priority'), null);
        list($type_meta) = $this->selectMeta($this->issue_table, array_get($is, 'type'), null);
        $done_ids = $this->dashboardDoneIds($status_meta);

        $status_key = $this->dashboardQueryKey(array_get($is, 'status'));
        $priority_key = $this->dashboardQueryKey(array_get($is, 'priority'));
        $type_key = $this->dashboardQueryKey(array_get($is, 'type'));
        $due_key = $this->dashboardQueryKey(array_get($is, 'due'));
        $assignee_key = $this->dashboardQueryKey(array_get($is, 'assignees'));

        $query = $this->dashboardLean($this->issue_table->getValueModel()->query())
            ->where('parent_type', $this->project_table->table_name)
            ->whereIn('parent_id', array_map('intval', $projectIds));

        // the status: "open" (not done), and / or labels
        $st = $list('st');
        if (in_array('_open', $st, true) && !empty($done_ids)) {
            $this->dashboardWhereOpen($query, $status_key, $done_ids);
        }
        $stLabels = array_values(array_diff($st, ['_open']));
        foreach ([[$stLabels, $status_meta, $status_key], [$list('pr'), $priority_meta, $priority_key], [$list('ty'), $type_meta, $type_key]] as [$labels, $meta, $key]) {
            if (empty($labels)) {
                continue;
            }
            $ids = $this->allIdsOfLabels($meta, $labels);
            if (empty($ids)) {
                return response()->json($empty);
            }
            $query->whereIn($key, $ids);
        }

        // the assignees: ids, "me" for the user
        $as = array_values(array_unique(array_filter(array_map(function ($v) use ($me) {
            return $v === 'me' ? $me : intval($v);
        }, $list('as')))));
        if (!empty($as)) {
            $query->whereInArrayString($assignee_key, $as);
        }

        // the due date
        $due = strval($request->query('due', ''));
        $week = \Carbon\Carbon::parse($today)->addDays(6)->format('Y-m-d');
        if ($due === 'overdue') {
            $query->whereNotNull($due_key)->where($due_key, '<>', '')->where($due_key, '<', $today);
            if (!empty($done_ids)) {
                $this->dashboardWhereOpen($query, $status_key, $done_ids);
            }
        } elseif ($due === 'today') {
            $query->where($due_key, $today);
        } elseif ($due === 'week') {
            $query->where($due_key, '>=', $today)->where($due_key, '<=', $week);
        } elseif ($due === 'none') {
            $query->where(function ($q) use ($due_key) {
                $q->whereNull($due_key)->orWhere($due_key, '');
            });
        }

        // the user's favorites only (ISS00272)
        if ($request->query('fav') === '1') {
            $favs = Star::where('star_type', Star::TYPE_FAV)->where('target_type', $this->issue_table->table_name)
                ->where('user_id', $me)->pluck('target_id')->map(function ($v) {
                    return intval($v);
                })->filter()->values()->toArray();
            if (empty($favs)) {
                return response()->json($empty);
            }
            $query->whereIn('id', $favs);
        }

        // words in the subject
        $words = trim(strval($request->query('q', '')));
        if ($words !== '') {
            $subject_key = $this->dashboardQueryKey(array_get($is, 'subject'));
            foreach (array_slice(preg_split('/\s+/u', $words) ?: [], 0, 5) as $word) {
                $query->where($subject_key, 'like', '%' . addcslashes($word, '%_\\') . '%');
            }
        }

        $total = (clone $query)->count();

        $dir = $request->query('dir') === 'desc' ? 'desc' : 'asc';
        switch (strval($request->query('sort', 'due'))) {
            case 'pr':
                // the order of the label, the same in every project
                $order = [];
                foreach ($priority_meta as $id => $m) {
                    $order[intval(array_get($m, 'o', 99))][] = intval($id);
                }
                ksort($order);
                $case = 'CASE';
                $bind = [];
                foreach ($order as $o => $ids) {
                    $case .= ' WHEN ' . $priority_key . ' IN (' . implode(',', array_fill(0, count($ids), '?')) . ') THEN ' . intval($o);
                    $bind = array_merge($bind, $ids);
                }
                $case .= ' ELSE 999 END';
                if (!empty($bind)) {
                    $query->orderByRaw($case . ' ' . $dir, $bind);
                }
                $query->orderBy('id', 'desc');
                break;
            case 'up':
                $query->orderBy('updated_at', $dir)->orderBy('id', 'desc');
                break;
            case 'key':
                $query->orderBy('id', $dir);
                break;
            default:
                // no due date last, whichever way
                $query->orderByRaw("CASE WHEN $due_key IS NULL OR $due_key = '' THEN 1 ELSE 0 END, $due_key $dir")->orderBy('id', 'desc');
        }

        if ($forExport) {
            return [$query, $cards];
        }
        $per = static::$all_per_page;
        $pages = max(1, intval(ceil($total / $per)));
        $page = min($pages, max(1, intval($request->query('page', 1))));
        $issues = static::takeSorted($query, $per, ($page - 1) * $per);

        $users = $this->allUserNames($issues, array_get($is, 'assignees'));
        $rows = [];
        foreach ($issues as $issue) {
            $row = $this->dashboardRow($issue, $cards, $status_meta, $priority_meta, $is, $today);
            if ($row === null) {
                continue;
            }
            $v = $issue->value;
            $row['type'] = array_get($type_meta, strval(array_get($v, array_get($is, 'type'), '')));
            $row['as'] = array_values(array_filter(array_map(function ($id) use ($users) {
                return $users[intval($id)] ?? null;
            }, (array) array_get($v, array_get($is, 'assignees'), []))));
            $row['up'] = $this->fmtAt($issue->updated_at ?? $issue->created_at);
            $rows[] = $row;
        }

        return response()->json(['ok' => true, 'rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages]);
    }

    /**
     * The labels of a list column, each once, in their order; for the status, whether it is a
     * finished one.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function allLabels(string $name, bool $withDone = false): array
    {
        list($meta) = $this->selectMeta($this->issue_table, array_get($this->def['issue'], $name), null);
        $out = [];
        foreach ($meta as $m) {
            $l = strval(array_get($m, 'l', ''));
            if ($l === '' || isset($out[$l])) {
                continue;
            }
            $out[$l] = ['l' => $l, 'c' => strval(array_get($m, 'c', '')), 'o' => intval(array_get($m, 'o', 99))];
            if ($withDone) {
                $out[$l]['done'] = (bool) array_get($m, 'done');
            }
        }
        uasort($out, function ($a, $b) {
            return $a['o'] <=> $b['o'];
        });

        return array_values($out);
    }

    /** @return array<int, int> the rows (of all the projects) of some labels */
    protected function allIdsOfLabels(array $meta, array $labels): array
    {
        $ids = [];
        foreach ($meta as $id => $m) {
            if (in_array(strval(array_get($m, 'l', '')), $labels, true)) {
                $ids[] = intval($id);
            }
        }

        return $ids;
    }

    /** @return array<int, array{id: int, n: string}> the users for the filter, the readable ones */
    protected function allUsers(): array
    {
        $table = CustomTable::getEloquent(SystemTableName::USER);
        if (!isset($table)) {
            return [];
        }
        $out = [];
        foreach ($table->getValueModel()->query()->orderBy('id')->take(2000)->get() as $user) {
            $out[] = ['id' => intval($user->id), 'n' => strval($user->getLabel())];
        }

        return $out;
    }

    /** @return array<int, string> the names of the assignees of some issues */
    protected function allUserNames($issues, $column): array
    {
        $ids = [];
        foreach ($issues as $issue) {
            foreach ((array) array_get($issue->value, $column, []) as $id) {
                if (intval($id) > 0) {
                    $ids[intval($id)] = true;
                }
            }
        }
        $table = CustomTable::getEloquent(SystemTableName::USER);
        if (!isset($table) || empty($ids)) {
            return [];
        }
        $out = [];
        foreach ($table->getValueModel()->query()->withoutGlobalScopes()->whereIn('id', array_keys($ids))->get() as $user) {
            $out[intval($user->id)] = strval($user->getLabel());
        }

        return $out;
    }
}
