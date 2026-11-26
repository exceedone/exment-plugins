<?php

namespace App\Plugins\ExmentPM;

use Exceedone\Exment\Enums\SystemTableName;
use Illuminate\Http\Request;

/**
 * 「最近の更新」 of a project, read after the page (ExmentPM UX S15, M5).
 *
 * The project page used to build the whole feed with the page: the history of
 * every issue of the project read with whereIn(every issue id), each change
 * compared with the one before, comments, wiki history, files, project
 * history - before the first byte of the page went out. Now the page comes
 * without it and the home asks for it, ten at a time (?before=<time> for the
 * next ten).
 *
 * Each source is read newest first by its kind (revisions by
 * revisionable_type, comments and files by parent_type) in small pages, and
 * only the rows of this project are kept - so no query names every issue of
 * the project. The index revisions (revisionable_type, created_at) of Exment
 * 7.3 makes the history read cheap; without it the read is what it was.
 *
 * Kept with pmCached until anything changes: the newest revision, comment and
 * file, by their ids, are part of the key (a comment does not move the issue
 * stamp).
 */
trait PortalFeedTrait
{
    public function projectFeed(Request $request, $id)
    {
        $started = microtime(true);
        $project = $this->findProject($id);
        $limit = max(1, min(30, intval($request->query('limit', 10))));
        $before = strval($request->query('before', ''));
        if ($before !== '' && !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $before)) {
            $before = '';
        }
        $key = [intval($project->id), $before, $limit, $this->issueStamp(intval($project->id)), $this->feedChangeStamp()];
        $out = $this->pmCached('feed', $key, function () use ($project, $limit, $before) {
            return $this->feedPage($project, $limit, $before === '' ? null : $before);
        });
        $response = response()->json(['ok' => true] + $out);
        // How long it took, for the browser's network panel - only in debug.
        if (config('app.debug')) {
            $response->headers->set('Server-Timing', 'pmfeed;dur=' . round((microtime(true) - $started) * 1000, 1));
        }

        return $response;
    }

    /**
     * Ids of the newest revision, comment and file: any new one changes the
     * feed. Primary-key reads.
     */
    protected function feedChangeStamp(): string
    {
        try {
            $rev = intval(\DB::table('revisions')->max('id'));
            $cmt = intval(getModelName(SystemTableName::COMMENT)::query()->max('id'));
            $doc = intval(getModelName(SystemTableName::DOCUMENT)::query()->max('id'));
        } catch (\Throwable $e) {
            return strval(microtime(true));
        }

        return $rev . '/' . $cmt . '/' . $doc;
    }

    /**
     * One page of the feed: the newest $limit entries older than $before.
     *
     * @return array{feed: array<int, array<string, mixed>>, more: bool}
     */
    protected function feedPage($project, int $limit, ?string $before): array
    {
        $issue_name = $this->issue_table->table_name;
        // Some history lines say nothing shown (a change of a hidden column):
        // read twice as many as are shown.
        $want = $limit * 2;
        // The project's issue ids, as a set: only the ids are read.
        $issue_ids = array_flip(array_map('intval', $this->projectIssueQuery($project)->pluck('id')->all()));
        $feed = [];
        // Set when a source stopped at its page limit: the time of the last
        // row it read. Nothing older is shown from the other sources (it
        // could hide rows of the stopped one), and the next page starts there.
        $floor = null;

        $revs = PortalFeedPage::scan(function ($skip, $take) use ($issue_name, $before) {
            $q = \DB::table('revisions')->where('revisionable_type', $issue_name)->whereNull('deleted_at');
            if ($before !== null) {
                $q->where('created_at', '<', $before);
            }
            return $q->orderBy('created_at', 'desc')->orderBy('id', 'desc')->skip($skip)->take($take)->get()->all();
        }, $issue_ids, 'revisionable_id', $want, $floor);
        $this->primeLabels($this->issue_table, $revs);
        $issue_rev = [];
        foreach ($revs as $rev) {
            $is_add = intval($rev->revision_no) <= 1;
            $changes = $is_add ? [] : $this->diffRevision($this->issue_table, $rev);
            if (!$is_add && empty($changes)) {
                continue;
            }
            $issue_rev[] = [$rev, $is_add, $changes];
        }

        $comments = PortalFeedPage::scan(function ($skip, $take) use ($issue_name, $before) {
            $q = getModelName(SystemTableName::COMMENT)::query()->where('parent_type', $issue_name);
            if ($before !== null) {
                $q->where('created_at', '<', $before);
            }
            return $q->orderBy('created_at', 'desc')->orderBy('id', 'desc')->skip($skip)->take($take)->get()->all();
        }, $issue_ids, 'parent_id', $want, $floor);

        $docs = PortalFeedPage::scan(function ($skip, $take) use ($issue_name, $before) {
            $q = getModelName(SystemTableName::DOCUMENT)::query()->where('parent_type', $issue_name);
            if ($before !== null) {
                $q->where('created_at', '<', $before);
            }
            return $q->orderBy('created_at', 'desc')->orderBy('id', 'desc')->skip($skip)->take($take)->get()->all();
        }, $issue_ids, 'parent_id', $want, $floor);

        // The subjects of the issues named, and nothing else.
        $named = [];
        foreach ($issue_rev as $r) {
            $named[intval($r[0]->revisionable_id)] = true;
        }
        foreach (array_merge($comments, $docs) as $row) {
            $named[intval($row->parent_id)] = true;
        }
        $subjects = [];
        if (!empty($named)) {
            $subject_col = strval(array_get($this->def['issue'], 'subject', 'subject'));
            foreach ($this->issue_table->getValueModel()->query()->whereIn('id', array_keys($named))->get() as $issue) {
                $subjects[intval($issue->id)] = strval($issue->getValue($subject_col));
            }
        }
        $issueEntry = function ($tp, $by, $ts, $issueId, $extra) use ($project, $subjects) {
            return [
                'tp' => $tp, 'by' => intval($by), 'ts' => strval($ts), 'kind' => 'issue', 'id' => intval($issueId),
                'key' => $this->issueKey($project, $issueId), 'label' => strval($subjects[intval($issueId)] ?? ''), 'extra' => $extra,
            ];
        };
        foreach ($issue_rev as $r) {
            [$rev, $is_add, $changes] = $r;
            if (!isset($subjects[intval($rev->revisionable_id)])) {
                continue;
            }
            $feed[] = $issueEntry($is_add ? 'add' : 'chg', $rev->create_user_id, $rev->created_at, $rev->revisionable_id,
                collect($changes)->take(2)->map(function ($c) {
                    return $c[0] . ': ' . $c[1] . ' → ' . $c[2];
                })->implode(' / '));
        }
        foreach ($comments as $comment) {
            if (!isset($subjects[intval($comment->parent_id)])) {
                continue;
            }
            $feed[] = $issueEntry('cmt', $comment->created_user_id, $comment->created_at, $comment->parent_id,
                mb_strimwidth(strval(array_get($comment->value, 'comment_detail', '')), 0, 60, '…'));
        }
        foreach ($docs as $doc) {
            $feed[] = [
                'tp' => 'file', 'by' => intval($doc->created_user_id), 'ts' => strval($doc->created_at), 'kind' => 'file', 'id' => 0,
                'key' => '', 'label' => strval(array_get($doc->value, 'document_name', '')),
                'extra' => $this->issueKey($project, $doc->parent_id),
            ];
        }

        // The wiki and the project itself: few records, read by their ids.
        if (!empty($this->projectFeatures($project)['wiki']) && strval($this->def['wiki_table'] ?? '') !== '') {
            $wiki_table = \Exceedone\Exment\Model\CustomTable::getEloquent(strval($this->def['wiki_table']));
            if (isset($wiki_table)) {
                $title_col = strval(array_get($this->def['wiki'] ?? [], 'title', 'title'));
                $pages = $wiki_table->getValueModel()->where('parent_id', $project->id)
                    ->where('parent_type', $this->project_table->table_name)->get()->keyBy('id');
                foreach ($this->feedRevisionsOf($wiki_table->table_name, $pages->keys()->all(), $want, $before, $floor) as $rev) {
                    $page = $pages->get(intval($rev->revisionable_id));
                    if (!$page) {
                        continue;
                    }
                    $feed[] = [
                        'tp' => intval($rev->revision_no) <= 1 ? 'wiki_add' : 'wiki', 'by' => intval($rev->create_user_id),
                        'ts' => strval($rev->created_at), 'kind' => 'wiki', 'id' => intval($page->id), 'key' => '',
                        'label' => strval($page->getValue($title_col)), 'extra' => '',
                    ];
                }
            }
        }
        // Who was added to or taken off the project is the manager's
        // business (as in the settings): the others do not see those columns.
        $hide = PortalMembership::canManage($project) ? [] : $this->peopleColumns();
        $project_revisions = $this->feedRevisionsOf($this->project_table->table_name, [$project->id], $want, $before, $floor);
        $this->primeLabels($this->project_table, $project_revisions, $hide);
        foreach ($project_revisions as $rev) {
            if (intval($rev->revision_no) <= 1) {
                continue;
            }
            $changes = $this->diffRevision($this->project_table, $rev, $hide);
            if (empty($changes)) {
                continue;
            }
            $feed[] = [
                'tp' => 'chg', 'by' => intval($rev->create_user_id), 'ts' => strval($rev->created_at), 'kind' => 'project',
                'id' => intval($project->id), 'key' => '', 'label' => strval($project->getLabel()),
                'extra' => collect($changes)->take(2)->map(function ($c) {
                    return $c[0] . ': ' . $c[1] . ' → ' . $c[2];
                })->implode(' / '),
            ];
        }

        return PortalFeedPage::cut($feed, $limit, $floor, function ($ts) {
            return $this->fmtAt($ts);
        });
    }

    /**
     * revisionsOf() with a time to read before; raises $floor when it
     * stopped at $take.
     *
     * @param array<int, mixed> $ids
     * @return array<int, object>
     */
    protected function feedRevisionsOf(string $type, array $ids, int $take, ?string $before, ?string &$floor): array
    {
        if (empty($ids)) {
            return [];
        }
        $q = \DB::table('revisions')->where('revisionable_type', $type)->whereIn('revisionable_id', $ids)->whereNull('deleted_at');
        if ($before !== null) {
            $q->where('created_at', '<', $before);
        }

        $rows = $q->orderBy('created_at', 'desc')->take($take)->get()->all();
        // As many as asked for: there may be older ones (see PortalFeedPage::scan).
        if (count($rows) >= $take) {
            $at = strval($rows[count($rows) - 1]->created_at);
            if ($floor === null || strcmp($at, $floor) > 0) {
                $floor = $at;
            }
        }

        return $rows;
    }
}
