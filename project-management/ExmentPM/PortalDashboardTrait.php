<?php

namespace App\Plugins\ExmentPM;

use Exceedone\Exment\Enums\SystemTableName;
use Exceedone\Exment\Model\CustomColumn;

/**
 * The space level dashboard: what one person has to deal with today, across
 * every project they can open.
 *
 * Split out of PmPortalController because that file is long enough already,
 * and because nothing here belongs to a single project - this is the only
 * part of the portal that reads across all of them at once.
 *
 * Everything is read through CustomTable::getValueModel(), so the row level
 * permission scope applies untouched: a member sees the issues of the
 * projects they were assigned to and nothing else, exactly as they do inside
 * a project. Nothing in this file decides who may see what.
 *
 * Cost, because a landing page that takes a second is a landing page nobody
 * keeps open: eight queries whatever the size of the install. Four of them
 * are grouped counts over an indexed column, three fetch one person's own
 * work, one is the feed. The lists are capped, and the caps are on the query
 * rather than on the loop, so a space with fifty thousand issues costs the
 * same as this one.
 */
trait PortalDashboardTrait
{
    /** Rows per "my issues" tab. */
    protected static $dash_list_limit = 40;

    /** Entries in the cross project feed. */
    protected static $dash_feed_limit = 25;

    /**
     * Build the dashboard, and fill in the per-project numbers the cards show.
     *
     * @param array<string, array<string, mixed>> $cards project id => card, by
     *        reference: each one gains done / open / overdue / mine / starred
     * @return array<string, mixed>
     */
    protected function buildDashboard(array &$cards): array
    {
        $me = intval(\Exment::getUserId());
        $is = $this->def['issue'];
        $today = PortalTime::today();

        // Status and priority are rows owned by a project, so the same label
        // exists once per project and a cross-project screen has to read the
        // whole set rather than one project's slice - that is what the null
        // scope asks for.
        list($status_meta) = $this->selectMeta($this->issue_table, array_get($is, 'status'), null);
        list($priority_meta) = $this->selectMeta($this->issue_table, array_get($is, 'priority'), null);

        $done_ids = $this->dashboardDoneIds($status_meta);

        $this->dashboardProjectCounts($cards, $done_ids, $today);
        $this->dashboardStars($cards);

        $tabs = $this->dashboardTabs($me, $done_ids, $cards, $status_meta, $priority_meta, $today);

        return [
            'me' => $me,
            'kpi' => [
                'open' => count($tabs['assigned']),
                'overdue' => count($tabs['overdue']),
                'soon' => count($tabs['soon']),
                'closed' => count($tabs['closed']),
            ],
            'tabs' => $tabs,
            // Kept until an issue, a comment, a file or a history line is
            // added (UX S15): it was read again on every visit.
            'feed' => $this->pmCached('dashfeed', [$this->issueStamp(), $this->feedChangeStamp(), array_keys($cards)], function () use ($cards) {
                return $this->dashboardFeed($cards);
            }),
            // So the view can say "there are more of these" rather than
            // quietly showing the first forty as if they were all of them.
            'limit' => static::$dash_list_limit,
        ];
    }

    /**
     * The status rows flagged done, as the ids a query can filter on.
     *
     * @param array<string, array<string, mixed>> $status_meta from selectMeta
     * @return array<int, string>
     */
    protected function dashboardDoneIds(array $status_meta): array
    {
        $done_ids = [];
        foreach ($status_meta as $key => $meta) {
            if (array_get($meta, 'done')) {
                $done_ids[] = strval($key);
            }
        }

        return $done_ids;
    }

    /**
     * Total / done / overdue / mine per project, as grouped counts.
     *
     * Grouped rather than per project because one query that returns fifty
     * rows beats fifty queries that return one, and the status and due date
     * columns are both indexed so each is a range scan rather than a read of
     * every issue's JSON.
     *
     * @param array<string, array<string, mixed>> $cards by reference
     * @param array<int, string> $done_ids status row ids flagged done
     * @param string $today Y-m-d
     */
    protected function dashboardProjectCounts(array &$cards, array $done_ids, string $today): void
    {
        $is = $this->def['issue'];
        $parent_type = $this->project_table->table_name;
        $status_key = $this->dashboardQueryKey(array_get($is, 'status'));
        $due_key = $this->dashboardQueryKey(array_get($is, 'due'));

        foreach ($cards as $id => $card) {
            $cards[$id]['done'] = 0;
            $cards[$id]['open'] = 0;
            $cards[$id]['overdue'] = 0;
            $cards[$id]['mine'] = 0;
            $cards[$id]['starred'] = false;
            $cards[$id]['progress'] = 0;
        }

        // Kept until an issue anywhere changes (issueStamp), or the day or
        // the done statuses do.
        $stamp = $this->issueStamp();
        $grouped = function ($apply, string $name) use ($parent_type, $stamp, $done_ids, $today) {
            return collect($this->pmCached('counts', [$name, $stamp, $done_ids, $today], function () use ($apply, $parent_type) {
                $query = $this->issue_table->getValueModel()->query()->where('parent_type', $parent_type);
                $apply($query);

                return $query->selectRaw('parent_id, count(*) as cnt')->groupBy('parent_id')->pluck('cnt', 'parent_id')->all();
            }));
        };

        $noop = function ($query) {
        };
        $totals = $grouped($noop, 'total');

        // An install that has not converted its status column yet has no rows
        // to flag as done, and every issue would count as open. Skipping the
        // done and overdue passes there is honest: the card shows a count and
        // no progress bar rather than a bar that is always empty.
        $dones = empty($done_ids) ? collect() : $grouped(function ($query) use ($status_key, $done_ids) {
            $query->whereIn($status_key, $done_ids);
        }, 'done');
        $overdues = empty($done_ids) ? collect() : $grouped(function ($query) use ($status_key, $due_key, $done_ids, $today) {
            $this->dashboardWhereOpen($query, $status_key, $done_ids)
                ->whereNotNull($due_key)
                ->where($due_key, '<', $today);
        }, 'overdue');

        // Counted here rather than off the "my issues" tab, which stops at
        // forty rows: a card that said "自分 40" on every project would be
        // wrong on exactly the installs big enough to need the number.
        $assignee_key = $this->dashboardQueryKey(array_get($is, 'assignees'));
        $me = intval(\Exment::getUserId());
        $mines = $grouped(function ($query) use ($status_key, $assignee_key, $done_ids, $me) {
            if (!empty($done_ids)) {
                $this->dashboardWhereOpen($query, $status_key, $done_ids);
            }
            $query->whereInArrayString($assignee_key, $me);
        }, 'mine');

        foreach ($cards as $id => $card) {
            $total = intval($totals->get(intval($id), 0));
            $done = intval($dones->get(intval($id), 0));
            $cards[$id]['count'] = $total;
            $cards[$id]['done'] = $done;
            $cards[$id]['open'] = max(0, $total - $done);
            $cards[$id]['overdue'] = intval($overdues->get(intval($id), 0));
            $cards[$id]['mine'] = intval($mines->get(intval($id), 0));
            $cards[$id]['progress'] = $total > 0 ? intval(round($done * 100 / $total)) : 0;
        }
    }

    /**
     * Narrow a query to the issues still open: a status that is not a done one,
     * or no status at all. An issue imported without one (CSV, the API) is
     * open work, but NOT IN alone drops it - NULL NOT IN (...) is never true
     * in SQL - so it went missing from 自分の課題 and from the counts while
     * the card's own open count, total minus done, still included it.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $status_key
     * @param array<int, string> $done_ids
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function dashboardWhereOpen($query, string $status_key, array $done_ids)
    {
        return $query->where(function ($open) use ($status_key, $done_ids) {
            $open->whereNotIn($status_key, $done_ids)->orWhereNull($status_key);
        });
    }

    /**
     * Mark the projects this user starred.
     *
     * @param array<string, array<string, mixed>> $cards by reference
     */
    protected function dashboardStars(array &$cards): void
    {
        $starred = Star::where('star_type', Star::TYPE_STAR)
            ->where('target_type', $this->project_table->table_name)
            ->where('user_id', \Exment::getUserId())
            ->pluck('target_id');

        foreach ($starred as $target) {
            $id = strval(intval($target));
            if (isset($cards[$id])) {
                $cards[$id]['starred'] = true;
            }
        }
    }

    /**
     * The five lists behind the tabs.
     *
     * @param int $me
     * @param array<int, string> $done_ids
     * @param array<string, array<string, mixed>> $cards
     * @param array<string, array<string, string>> $status_meta
     * @param array<string, array<string, string>> $priority_meta
     * @param string $today Y-m-d
     * @return array<string, array<int, array<string, mixed>>>
     */
    protected function dashboardTabs(int $me, array $done_ids, array $cards, array $status_meta, array $priority_meta, string $today): array
    {
        $is = $this->def['issue'];
        $parent_type = $this->project_table->table_name;
        $status_key = $this->dashboardQueryKey(array_get($is, 'status'));
        $due_key = $this->dashboardQueryKey(array_get($is, 'due'));
        $assignee_key = $this->dashboardQueryKey(array_get($is, 'assignees'));

        $open = function () use ($parent_type, $status_key, $done_ids) {
            $query = $this->dashboardLean($this->issue_table->getValueModel()->query())->where('parent_type', $parent_type);
            if (!empty($done_ids)) {
                // Narrow on the indexed status column first: whatever follows
                // it - FIND_IN_SET over the assignee list, a LIKE - cannot use
                // an index, so the cheapest thing to do is give it less to
                // read.
                $this->dashboardWhereOpen($query, $status_key, $done_ids);
            }

            return $query;
        };

        $map = function ($issues) use ($cards, $status_meta, $priority_meta, $is, $today) {
            $out = [];
            foreach ($issues as $issue) {
                $row = $this->dashboardRow($issue, $cards, $status_meta, $priority_meta, $is, $today);
                if ($row !== null) {
                    $out[] = $row;
                }
            }

            return $out;
        };

        // Due first, and undated last rather than first - an issue with no due
        // date is not the most urgent thing on the list, which is what a plain
        // "order by due" would claim.
        $byDue = function ($query) use ($due_key) {
            return $query->orderByRaw("CASE WHEN $due_key IS NULL OR $due_key = '' THEN 1 ELSE 0 END, $due_key asc")
                ->take(static::$dash_list_limit)->get();
        };

        // Both filter every open issue on columns computed from its JSON;
        // kept until an issue changes, or the day or the cards do.
        $listKey = [$this->issueStamp(), $done_ids, $today, md5(json_encode([$cards, $status_meta, $priority_meta]))];
        $assigned = $this->pmCached('assigned', $listKey, function () use ($map, $byDue, $open, $assignee_key, $me) {
            return $map($byDue($open()->whereInArrayString($assignee_key, $me)));
        });
        $created = $this->pmCached('created', $listKey, function () use ($map, $byDue, $open, $me) {
            return $map($byDue($open()->where('created_user_id', $me)));
        });

        $watched = [];
        // target_id is a string column. Not map('intval'): map() passes the
        // key as intval's base, which kept only the first watched issue.
        $watch_ids = Star::where('star_type', Star::TYPE_WATCH)
            ->where('target_type', $this->issue_table->table_name)
            ->where('user_id', $me)
            ->pluck('target_id')->map(function ($id) {
                return intval($id);
            })->filter()->values()->toArray();
        if (!empty($watch_ids)) {
            $watched = $map($byDue(
                $this->dashboardLean($this->issue_table->getValueModel()->query())
                    ->where('parent_type', $parent_type)
                    ->whereIn('id', $watch_ids)
            ));
        }

        // Review requests (ISS00341): the issues with a comment somebody asked
        // this user to confirm and that they have not confirmed yet - open or
        // finished, since the question still stands on a closed issue. Read
        // through the comment table, so a request whose comment is gone drops
        // out; the row check in $map leaves out what the user cannot reach.
        $review = [];
        $review_comments = Star::pendingReviews('comment', $me);
        if (!empty($review_comments)) {
            $review_ids = getModelName(SystemTableName::COMMENT)::whereIn('id', $review_comments)
                ->where('parent_type', $this->issue_table->table_name)
                ->pluck('parent_id')->map(function ($id) {
                    return intval($id);
                })->filter()->unique()->values()->toArray();
            if (!empty($review_ids)) {
                $review = $map($byDue(
                    $this->dashboardLean($this->issue_table->getValueModel()->query())
                        ->where('parent_type', $parent_type)
                        ->whereIn('id', $review_ids)
                ));
            }
        }

        // "Closed this week" is read off updated_at, not off the revision that
        // actually flipped the status. Reading the revisions would cost a join
        // per row for a number nobody audits, and an issue that was closed and
        // then edited is still an issue that was closed - so the count can
        // only ever be a little generous, never short.
        $closed = [];
        if (!empty($done_ids)) {
            $closed = $map(
                $this->dashboardLean($this->issue_table->getValueModel()->query())
                    ->where('parent_type', $parent_type)
                    ->whereIn($status_key, $done_ids)
                    ->whereInArrayString($assignee_key, $me)
                    ->where('updated_at', '>=', PortalTime::dayStartForQuery(
                        \Carbon\Carbon::parse(PortalTime::today())->startOfWeek()->format('Y-m-d')
                    ))
                    ->orderBy('updated_at', 'desc')
                    ->take(static::$dash_list_limit)->get()
            );
        }

        $overdue = array_values(array_filter($assigned, function ($row) {
            return $row['late'];
        }));
        $soon = array_values(array_filter($assigned, function ($row) {
            return $row['soon'];
        }));

        return [
            'assigned' => $assigned,
            'overdue' => $overdue,
            'soon' => $soon,
            'created' => $created,
            'review' => $review,
            'watched' => $watched,
            'closed' => $closed,
        ];
    }

    /**
     * One issue, flattened for the dashboard.
     *
     * Returns null for an issue whose project is not in the card set. That
     * happens when the project row itself is out of reach while the issue is
     * not, which the portal has no sensible way to draw - there would be
     * nowhere to link it to.
     *
     * @param \Exceedone\Exment\Model\CustomValue $issue
     * @param array<string, array<string, mixed>> $cards
     * @param array<string, array<string, string>> $status_meta
     * @param array<string, array<string, string>> $priority_meta
     * @param array<string, string> $is issue column names
     * @param string $today Y-m-d
     * @return array<string, mixed>|null
     */
    protected function dashboardRow($issue, array $cards, array $status_meta, array $priority_meta, array $is, string $today)
    {
        $project_id = strval(intval($issue->parent_id));
        $card = array_get($cards, $project_id);
        if (!isset($card)) {
            return null;
        }

        $v = $issue->value;
        $due = $this->rawDate(array_get($v, array_get($is, 'due')));
        $key = ($card['key'] !== '' ? $card['key'] : '#') . '-' . $issue->id;

        return [
            'id' => $issue->id,
            'key' => $key,
            'subject' => strval(array_get($v, array_get($is, 'subject'), '')),
            'url' => $card['url'] . '#issue-' . rawurlencode(PortalDefinition::issueAnchor($issue)),
            'project_id' => $project_id,
            'project' => $card['name'],
            'status' => array_get($status_meta, strval(array_get($v, array_get($is, 'status'), ''))),
            'priority' => array_get($priority_meta, strval(array_get($v, array_get($is, 'priority'), ''))),
            'due' => $due,
            'due_label' => $this->dashboardDueLabel($due, $today),
            'late' => $due !== null && $due < $today,
            // "Soon" is today and tomorrow. Any wider and it stops being the
            // thing you look at before deciding what to do next.
            'soon' => $due !== null && $due >= $today && $due <= \Carbon\Carbon::parse($today)->addDay()->format('Y-m-d'),
            'progress' => is_numeric(array_get($v, array_get($is, 'progress')))
                ? intval(array_get($v, array_get($is, 'progress'))) : null,
        ];
    }

    /**
     * What changed lately, anywhere this user can see.
     *
     * Issue edits come off updated_at rather than the revision table: the feed
     * wants "this moved", not a field by field diff, and updated_at is indexed
     * where a revision join is not.
     *
     * @param array<string, array<string, mixed>> $cards
     * @return array<int, array<string, mixed>>
     */
    protected function dashboardFeed(array $cards): array
    {
        $is = $this->def['issue'];
        $issue_name = $this->issue_table->table_name;
        $limit = static::$dash_feed_limit;

        $issues = $this->dashboardLean($this->issue_table->getValueModel()->query())
            ->where('parent_type', $this->project_table->table_name)
            ->orderBy('updated_at', 'desc')->take($limit)->get();

        $feed = [];
        $keys = [];
        foreach ($issues as $issue) {
            $project_id = strval(intval($issue->parent_id));
            $card = array_get($cards, $project_id);
            if (!isset($card)) {
                continue;
            }
            $key = ($card['key'] !== '' ? $card['key'] : '#') . '-' . $issue->id;
            $keys[intval($issue->id)] = ['key' => $key, 'anchor' => PortalDefinition::issueAnchor($issue), 'card' => $card, 'subject' => strval(array_get($issue->value, array_get($is, 'subject'), ''))];
            $created = strval($issue->created_at);
            $updated = strval($issue->updated_at ?: $issue->created_at);
            $feed[] = [
                'tp' => $updated === $created ? 'add' : 'chg',
                'by' => intval($issue->updated_user_id ?: $issue->created_user_id),
                'ts' => $updated,
                'key' => $key,
                'label' => $keys[intval($issue->id)]['subject'],
                'project' => $card['name'],
                'url' => $card['url'] . '#issue-' . rawurlencode($keys[intval($issue->id)]['anchor']),
                'extra' => '',
            ];
        }

        // Comments are fetched for the issues already in hand rather than for
        // the whole table: a comment on an issue this user cannot open must
        // not appear, and the issue list above is the permission filtered one.
        if (!empty($keys)) {
            $comments = getModelName(SystemTableName::COMMENT)::whereIn('parent_id', array_keys($keys))
                ->where('parent_type', $issue_name)
                ->orderBy('created_at', 'desc')->take($limit)->get();
            foreach ($comments as $comment) {
                $target = array_get($keys, intval($comment->parent_id));
                if (!isset($target)) {
                    continue;
                }
                $feed[] = [
                    'tp' => 'cmt',
                    'by' => intval($comment->created_user_id),
                    'ts' => strval($comment->created_at),
                    'key' => $target['key'],
                    'label' => $target['subject'],
                    'project' => $target['card']['name'],
                    'url' => $target['card']['url'] . '#issue-' . rawurlencode($target['anchor']),
                    'extra' => mb_strimwidth(strval(array_get($comment->value, 'comment_detail', '')), 0, 70, '…'),
                ];
            }
        }

        usort($feed, function ($a, $b) {
            return strcmp($b['ts'], $a['ts']);
        });
        $feed = array_slice($feed, 0, $limit);

        $users = $this->usersFor(collect($feed)->pluck('by')->all());
        $weekdays = (array) PortalDefinition::trans('dash.weekdays');
        foreach ($feed as $i => $entry) {
            $feed[$i]['who'] = array_get($users, strval($entry['by']), ['n' => '', 'i' => '?', 'c' => '#95a5a6']);
            $feed[$i]['at'] = $this->fmtAt($entry['ts']);
            // The view prints a header whenever this changes between two
            // neighbouring entries, which is all a date group is.
            try {
                // The day the entry falls on for the user, like its time above.
                $ts = \Carbon\Carbon::parse(PortalTime::format($entry['ts'], 'Y-m-d H:i:s'));
                $feed[$i]['day'] = $ts->format(strval(PortalDefinition::trans('dash.day_fmt')))
                    . '(' . strval(array_get($weekdays, $ts->dayOfWeek, '')) . ')';
            } catch (\Exception $e) {
                $feed[$i]['day'] = '';
            }
        }

        return $feed;
    }

    /**
     * How a due date reads on the dashboard.
     *
     * A date on its own makes the reader do the arithmetic. "3 days over" and
     * "today" do not, which is the whole reason this screen exists.
     *
     * @param string|null $due Y-m-d
     * @param string $today Y-m-d
     * @return string
     */
    protected function dashboardDueLabel($due, string $today): string
    {
        if ($due === null) {
            return PortalDefinition::trans('dash.no_due');
        }

        // intval, because Carbon 3 answers diffInDays() with a float and
        // "=== 0" against 0.0 is false - which is how a due date of today
        // rendered as a plain date instead of saying so.
        $days = intval(\Carbon\Carbon::parse($today)->diffInDays(\Carbon\Carbon::parse($due), false));
        if ($days < 0) {
            return PortalDefinition::trans('dash.overdue_days', ['days' => abs($days)]);
        }
        if ($days === 0) {
            return PortalDefinition::trans('dash.today');
        }
        if ($days === 1) {
            return PortalDefinition::trans('dash.tomorrow');
        }

        return PortalDefinition::trans('dash.due_prefix') . ' ' . str_replace('-', '/', $due);
    }

    /**
     * Where a column's value can be read in SQL - the generated index column
     * when the column has one, the JSON path when it does not.
     *
     * @param string|null $column_name
     * @return string
     */
    protected function dashboardQueryKey($column_name): string
    {
        $column = is_nullorempty($column_name) ? null : CustomColumn::getEloquent($column_name, $this->issue_table);

        return isset($column) ? $column->getQueryKey() : 'value->' . strval($column_name);
    }

    /** @var array<string, array<int, string>> table => its columns, read once per request */
    protected $dash_columns = [];

    /** @var array<string, string> issue stamps read in this request */
    protected $issue_stamps = [];

    /**
     * Count and newest change of the issues - of one project, or of all of
     * them - which moves whenever an issue is added, edited, moved, deleted
     * or restored. Cheap (an index range and one column), so it can key the
     * caches of what is costly: the counts below group or filter on index
     * columns that MariaDB computes from each issue's JSON, ~0.1-0.3s each on
     * a project of 2,400 imported issues.
     */
    protected function issueStamp(?int $projectId = null): string
    {
        $k = strval($projectId ?? 'all');
        if (!isset($this->issue_stamps[$k])) {
            $query = $this->issue_table->getValueModel()->query()->where('parent_type', $this->project_table->table_name);
            if ($projectId !== null) {
                $query->where('parent_id', $projectId);
            }
            $row = $query->selectRaw('count(*) as pm_n, max(updated_at) as pm_at')->first();
            $this->issue_stamps[$k] = intval(array_get($row, 'pm_n', 0)) . '/' . substr(strval(array_get($row, 'pm_at', '')), 0, 19);
        }

        return $this->issue_stamps[$k];
    }

    /**
     * A costly read kept until the issues change. The key always holds the
     * reader: Exment may narrow what a user's queries see, so one user's
     * counts are never another's. Without a working cache it simply reads.
     *
     * @param array<int, mixed> $key what the result depends on (an issue stamp among them)
     * @return mixed
     */
    protected function pmCached(string $name, array $key, callable $read)
    {
        // The time zone the reader's times are shown in is part of what was
        // read ("today", formatted times): a user who changes it is not handed
        // the lists kept for the old one. Nothing is added for the time zone
        // of the site, so those keys are the ones they always were.
        $zone = PortalTime::zone();
        $id = 'pmportal:' . $name . ':' . md5(json_encode(array_merge([\Exment::getUserId()], $zone === '' ? [] : [$zone], $key)));
        try {
            return \Illuminate\Support\Facades\Cache::remember($id, 86400, $read);
        } catch (\Throwable $e) {
            return $read();
        }
    }

    /**
     * Read the issues without their body. The dashboard lists show the
     * subject, status, due date and people - never the description - and the
     * description is by far the largest part of a row: on a project of long
     * documents, reading it made each list query pull most of the table's
     * size across from the database for nothing. MySQL / MariaDB only (JSON_REMOVE);
     * other databases read the whole row as before.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function dashboardLean($query)
    {
        $desc = strval(array_get($this->def['issue'], 'description', ''));
        $connection = $query->getQuery()->getConnection();
        if ($desc === '' || !in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)) {
            return $query;
        }
        $table = $query->getModel()->getTable();
        if (!isset($this->dash_columns[$table])) {
            $this->dash_columns[$table] = \Illuminate\Support\Facades\Schema::connection($connection->getName())->getColumnListing($table);
        }
        if (!in_array('value', $this->dash_columns[$table], true)) {
            return $query; // no value column: nothing to trim
        }
        // Nor the index columns (column_<hash>): generated from value, each
        // up to 768 characters, and never read - the model reads value. A
        // list of 363 issues out of 2,400 took 1.6s with the 20 of them in
        // the select and 0.11s without (MariaDB, ex1task).
        $columns = array_values(array_filter($this->dash_columns[$table], function ($c) {
            return $c !== 'value' && strpos($c, 'column_') !== 0;
        }));
        $grammar = $query->getQuery()->getGrammar();

        return $query->select(array_map(function ($c) use ($table) {
            return $table . '.' . $c;
        }, $columns))->selectRaw('JSON_REMOVE(' . $grammar->wrap($table . '.value') . ', ?) as ' . $grammar->wrap('value'), ['$."' . addcslashes($desc, '"\\') . '"']);
    }
}
