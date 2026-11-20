<?php

namespace App\Plugins\ExmentPluginPM;

use Carbon\Carbon;
use Exceedone\Exment\Enums\SystemTableName;
use ExmentAdminCore\Admin\Layout\Content;
use Illuminate\Http\Request;

/**
 * The ranking (ISS00337, snoro 2026-11-19): who finished, filed, commented
 * and was thanked the most today, this week or this month, shown as an
 * Olympic podium for the top three and a list below.
 *
 * Only what the viewer can already read is counted: the projects come from
 * the same permission scoped read as the top bar's project menu, and every
 * count is taken over the issues of those projects (and their comments).
 * Archived projects are kept - finished work still counts in the period it
 * was done.
 *
 * Nobody is credited for their own ★ or reaction on their own issue or
 * comment, and a reaction given to oneself does not count as given either.
 */
trait PortalRankingTrait
{
    /** Points per thing in the 総合 ranking. Constants for now (v1). */
    protected static $rank_weights = [
        'done' => 3,
        'created' => 1,
        'comments' => 1,
        'rx_recv' => 1,
        'star' => 1,
    ];

    /** Rows shown below the podium. */
    protected static $rank_limit = 50;

    /** Revisions read per request for the 完了 count. */
    protected static $rank_revision_cap = 20000;

    /**
     * GET ranking?p=day|week|month&d=YYYY-MM-DD&c=<category>&pj=<project id>
     */
    public function ranking(Request $request, Content $content)
    {
        if (!isset($this->project_table) || !isset($this->issue_table)) {
            abort(404);
        }
        $this->setPageInfo(PortalDefinition::trans('rank.title'), PortalDefinition::trans('rank.title'), '', 'fa-trophy');

        $period = strval($request->get('p', 'week'));
        if (!in_array($period, ['day', 'week', 'month'], true)) {
            $period = 'week';
        }
        $categories = ['total', 'done', 'created', 'comments', 'rx_recv', 'rx_given', 'star'];
        $category = strval($request->get('c', 'total'));
        if (!in_array($category, $categories, true)) {
            $category = 'total';
        }

        $today = Carbon::today();
        $anchor = $today->copy();
        $raw = $request->get('d');
        if (is_string($raw) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            try {
                $parsed = Carbon::createFromFormat('Y-m-d', $raw);
                if ($parsed instanceof Carbon) {
                    $anchor = $parsed->startOfDay();
                }
            } catch (\Throwable $e) {
                $anchor = $today->copy();
            }
        }
        if ($anchor->gt($today)) {
            $anchor = $today->copy();
        }
        list($from, $to) = static::rankRange($period, $anchor);

        // Projects the viewer can read, the project menu's own list.
        $cards = $this->topbarCards();
        $projects = [];
        foreach ($cards as $card) {
            $projects[strval($card['id'])] = strval($card['name']);
        }
        $pj = strval($request->get('pj', ''));
        if ($pj !== '' && !array_key_exists($pj, $projects)) {
            $pj = '';
        }
        $projectIds = $pj !== '' ? [intval($pj)] : array_map('intval', array_keys($projects));

        $counts = $this->rankCounts($projectIds, $from, $to);

        // The chosen category, as rows: score, user, rank (ties share a rank).
        $scores = $counts[$category] ?? [];
        arsort($scores);
        $userIds = array_keys($scores);
        $users = $this->usersFor($userIds);
        $rows = [];
        foreach ($scores as $uid => $score) {
            if ($score <= 0 || !isset($users[strval($uid)])) {
                continue;
            }
            $rows[] = ['id' => intval($uid), 'score' => $score, 'user' => $users[strval($uid)]];
        }
        usort($rows, function ($a, $b) {
            return $b['score'] <=> $a['score'] ?: strcmp($a['user']['n'], $b['user']['n']);
        });
        $rank = 0;
        $last = null;
        $count = count($rows);
        for ($n = 0; $n < $count; $n++) {
            if ($rows[$n]['score'] !== $last) {
                $rank = $n + 1;
                $last = $rows[$n]['score'];
            }
            $rows[$n]['rank'] = $rank;
        }

        $me = intval(\Exment::getUserId());
        $mine = null;
        foreach ($rows as $row) {
            if ($row['id'] === $me) {
                $mine = $row;
                break;
            }
        }

        $prev = static::rankShift($period, $anchor, -1);
        $next = static::rankShift($period, $anchor, 1);
        $url = function (array $over) use ($period, $anchor, $category, $pj) {
            $q = array_merge(['p' => $period, 'd' => $anchor->format('Y-m-d'), 'c' => $category, 'pj' => $pj], $over);
            $q = array_filter($q, function ($v) {
                return $v !== '' && $v !== null;
            });

            return PortalDefinition::url('ranking') . '?' . http_build_query($q);
        };

        $topbar = $this->topbarData(null, $cards);
        $topbar['page'] = 'ranking';

        return $this->AdminContent($content)->body(view(PortalDefinition::view('ranking'), [
            'topbar' => $topbar,
            'period' => $period,
            'category' => $category,
            'categories' => $categories,
            'label' => static::rankLabel($period, $from, $to),
            'is_current' => static::rankRange($period, $today)[0]->eq($from),
            'prev_url' => $url(['d' => $prev->format('Y-m-d')]),
            'next_url' => $next->gt($today) ? '' : $url(['d' => $next->format('Y-m-d')]),
            'period_urls' => [
                'day' => $url(['p' => 'day', 'd' => $today->format('Y-m-d')]),
                'week' => $url(['p' => 'week', 'd' => $today->format('Y-m-d')]),
                'month' => $url(['p' => 'month', 'd' => $today->format('Y-m-d')]),
            ],
            'category_urls' => array_combine($categories, array_map(function ($c) use ($url) {
                return $url(['c' => $c]);
            }, $categories)),
            'projects' => $projects,
            'project' => $pj,
            'project_url' => PortalDefinition::url('ranking') . '?' . http_build_query(['p' => $period, 'd' => $anchor->format('Y-m-d'), 'c' => $category]),
            'podium' => array_values(array_filter($rows, function ($r) {
                return $r['rank'] <= 3;
            })),
            'rows' => array_slice($rows, 0, static::$rank_limit),
            'mine' => $mine,
            'total_people' => count($rows),
            'weights' => static::$rank_weights,
        ])->render());
    }

    /**
     * [start, end) of the period that holds $day. A week starts on Monday.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    protected static function rankRange(string $period, Carbon $day): array
    {
        if ($period === 'day') {
            $from = $day->copy()->startOfDay();

            return [$from, $from->copy()->addDay()];
        }
        if ($period === 'month') {
            $from = $day->copy()->startOfMonth();

            return [$from, $from->copy()->addMonth()];
        }
        $from = $day->copy()->startOfWeek(Carbon::MONDAY);

        return [$from, $from->copy()->addWeek()];
    }

    /** The same day one period earlier (-1) or later (1). */
    protected static function rankShift(string $period, Carbon $day, int $step): Carbon
    {
        if ($period === 'day') {
            return $day->copy()->addDays($step);
        }
        if ($period === 'month') {
            return $day->copy()->startOfMonth()->addMonths($step);
        }

        return $day->copy()->addWeeks($step);
    }

    /** "2026/11/19", "2026/11/17〜11/23" or "2026年11月". */
    protected static function rankLabel(string $period, Carbon $from, Carbon $to): string
    {
        if ($period === 'day') {
            return $from->format('Y/m/d');
        }
        if ($period === 'month') {
            return $from->format('Y') . '年' . $from->format('n') . '月';
        }

        return $from->format('Y/m/d') . '〜' . $to->copy()->subDay()->format('m/d');
    }

    /**
     * user id => count, per category, over the issues of these projects.
     *
     * @param array<int, int> $projectIds
     * @return array<string, array<int, int>>
     */
    protected function rankCounts(array $projectIds, Carbon $from, Carbon $to): array
    {
        $out = ['done' => [], 'created' => [], 'comments' => [], 'rx_recv' => [], 'rx_given' => [], 'star' => [], 'total' => []];
        if (empty($projectIds)) {
            return $out;
        }
        $add = function (string $cat, $uid, int $n = 1) use (&$out) {
            $uid = intval($uid);
            if ($uid <= 0) {
                return;
            }
            $out[$cat][$uid] = ($out[$cat][$uid] ?? 0) + $n;
        };

        $issueName = $this->issue_table->table_name;
        $is = (array) $this->def['issue'];
        $statusCol = strval(array_get($is, 'status'));
        $assigneeCol = strval(array_get($is, 'assignees'));
        $fromS = $from->format('Y-m-d H:i:s');
        $toS = $to->format('Y-m-d H:i:s');

        // The issues of these projects: who filed each one, for credit.
        $issues = $this->issue_table->getValueModel()->newQueryWithoutScopes()
            ->whereNull('deleted_at')
            ->where('parent_type', $this->project_table->table_name)
            ->whereIn('parent_id', $projectIds)
            ->get(['id', 'parent_id', 'created_user_id', 'created_at', 'value']);
        $issueAuthor = [];
        $issueAssignees = [];
        foreach ($issues as $issue) {
            $issueAuthor[intval($issue->id)] = intval($issue->created_user_id);
            $issueAssignees[intval($issue->id)] = PortalMembership::ids(array_get((array) $issue->value, $assigneeCol));
            $created = strval($issue->created_at);
            if ($created >= $fromS && $created < $toS) {
                $add('created', $issue->created_user_id);
            }
        }
        $issueIds = array_keys($issueAuthor);
        if (empty($issueIds)) {
            return $out;
        }

        // 完了: a revision in the period that moved the status from not done
        // to done, credited to the assignees it then had.
        $done = [];
        foreach ($projectIds as $pid) {
            foreach ($this->doneKeys($pid, $statusCol) as $key) {
                $done[strval($key)] = true;
            }
        }
        if ($statusCol !== '' && !empty($done)) {
            $revs = \DB::table('revisions')
                ->where('revisionable_type', $issueName)
                ->whereIn('revisionable_id', $issueIds)
                ->whereNull('deleted_at')
                ->where('created_at', '>=', $fromS)
                ->where('created_at', '<', $toS)
                ->orderBy('created_at')
                ->take(static::$rank_revision_cap)
                ->get(['revisionable_id', 'old_value', 'new_value']);
            foreach ($revs as $rev) {
                $old = json_decode(strval($rev->old_value), true) ?: [];
                $new = json_decode(strval($rev->new_value), true) ?: [];
                $was = strval(array_get($old, $statusCol, ''));
                $now = strval(array_get($new, $statusCol, ''));
                if ($now === '' || $was === $now || !isset($done[$now]) || isset($done[$was])) {
                    continue;
                }
                $who = PortalMembership::ids(array_get($new, $assigneeCol));
                if (empty($who)) {
                    $who = $issueAssignees[intval($rev->revisionable_id)] ?? [];
                }
                foreach ($who as $uid) {
                    $add('done', $uid);
                }
            }
        }

        // Comments on these issues: written in the period, and the author of
        // every one, for the reactions they receive.
        $commentModel = getModelName(SystemTableName::COMMENT);
        $commentAuthor = [];
        foreach (array_chunk($issueIds, 1000) as $chunk) {
            $rows = $commentModel::whereIn('parent_id', $chunk)
                ->where('parent_type', $issueName)
                ->get(['id', 'created_user_id', 'created_at']);
            foreach ($rows as $comment) {
                $commentAuthor[intval($comment->id)] = intval($comment->created_user_id);
                $created = strval($comment->created_at);
                if ($created >= $fromS && $created < $toS) {
                    $add('comments', $comment->created_user_id);
                }
            }
        }

        // Reactions and ★ put in the period, on these issues and comments.
        $marks = function (string $targetType, array $ids, bool $reactions) use ($fromS, $toS) {
            if (empty($ids)) {
                return [];
            }
            $query = Star::whereIn('target_id', array_map('strval', $ids))
                ->where('target_type', $targetType)
                ->where('created_at', '>=', $fromS)
                ->where('created_at', '<', $toS);
            if ($reactions) {
                $query->where('star_type', 'like', Star::REACTION_PREFIX . '%');
            } else {
                $query->where('star_type', Star::TYPE_STAR);
            }

            return $query->get(['star_type', 'target_id', 'user_id'])->all();
        };
        foreach ([[$issueName, $issueAuthor], ['comment', $commentAuthor]] as [$type, $authors]) {
            foreach (array_chunk(array_keys($authors), 1000) as $chunk) {
                foreach ($marks($type, $chunk, true) as $mark) {
                    if (!Star::isReaction(strval($mark->star_type))) {
                        continue;
                    }
                    $author = $authors[intval($mark->target_id)] ?? 0;
                    if ($author === intval($mark->user_id)) {
                        continue;
                    }
                    $add('rx_recv', $author);
                    $add('rx_given', $mark->user_id);
                }
            }
        }
        foreach (array_chunk($issueIds, 1000) as $chunk) {
            foreach ($marks($issueName, $chunk, false) as $mark) {
                $author = $issueAuthor[intval($mark->target_id)] ?? 0;
                if ($author === intval($mark->user_id)) {
                    continue;
                }
                $add('star', $author);
            }
        }

        foreach (static::$rank_weights as $cat => $weight) {
            foreach ($out[$cat] as $uid => $n) {
                $add('total', $uid, $n * $weight);
            }
        }

        return $out;
    }
}
