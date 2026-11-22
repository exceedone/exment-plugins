<?php

namespace App\Plugins\ExmentPMBacklog;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Take over a project the internal tool (backlog_sync) imported, so this
 * plugin goes on from where the tool stopped instead of importing it again.
 *
 * state.json: issues {backlog id: {exment_id, key, updated, last_comment_id,
 * attachments: [ids]}} and last_updated {project key: Backlog time}.
 * mapping.json: users {backlog user id: exment user id}.
 *
 * Only issues that are in this connection's Exment project are taken; the
 * attachments the tool took are remembered by their Backlog id only (the
 * tool kept no document id), which is all that is needed not to take them
 * twice.
 */
class Importer
{
    /**
     * @param array<string, mixed> $state
     * @param array<string, mixed>|null $mapping
     * @return array<string, mixed> counts
     */
    public static function run(object $conn, array $state, ?array $mapping): array
    {
        $map = Connections::mapping($conn);
        if (empty($map['backlog_project_id'])) {
            throw new \RuntimeException(Lang::t('import_need_mapping'));
        }
        $issueTable = Pm::table('issue_table');
        $host = Connections::client($conn)->host();
        $pid = intval($conn->exment_project_id);
        $connId = intval($conn->id);

        $issues = (array) ($state['issues'] ?? []);
        // Which of the Exment ids are issues of this project, in one query.
        $ids = array_values(array_filter(array_map(function ($r) {
            return intval($r['exment_id'] ?? 0);
        }, $issues)));
        $inProject = [];
        foreach (array_chunk($ids, 1000) as $chunk) {
            foreach (DB::table(getDBTableName($issueTable))->whereIn('id', $chunk)
                ->where('parent_type', Pm::name('project_table'))->where('parent_id', $pid)
                ->whereNull('deleted_at')->pluck('id') as $id) {
                $inProject[intval($id)] = true;
            }
        }

        $n = ['issues' => 0, 'files' => 0, 'users' => 0, 'skipped' => 0];
        DB::transaction(function () use ($issues, $inProject, $host, $connId, &$n) {
            $now = Carbon::now();
            foreach ($issues as $bid => $r) {
                $eid = intval($r['exment_id'] ?? 0);
                if (!$eid || !isset($inProject[$eid])) {
                    $n['skipped']++;
                    continue;
                }
                DB::table('pmbl_links')->updateOrInsert(
                    ['space_host' => $host, 'kind' => 'issue', 'backlog_id' => intval($bid)],
                    [
                        'connection_id' => $connId, 'exment_id' => $eid,
                        'backlog_key' => mb_substr(strval($r['key'] ?? ''), 0, 64) ?: null,
                        'backlog_updated' => $r['updated'] ?? null,
                        'last_comment_id' => !empty($r['last_comment_id']) ? intval($r['last_comment_id']) : null,
                        'created_at' => $now, 'updated_at' => $now,
                    ]
                );
                $n['issues']++;
                foreach ((array) ($r['attachments'] ?? []) as $aid) {
                    DB::table('pmbl_links')->updateOrInsert(
                        ['space_host' => $host, 'kind' => 'attachment', 'backlog_id' => intval($aid)],
                        ['connection_id' => $connId, 'exment_id' => 0, 'parent_backlog_id' => intval($bid), 'created_at' => $now, 'updated_at' => $now]
                    );
                    $n['files']++;
                }
            }
        });

        // Users the screen has not set yet.
        if ($mapping && !empty($mapping['users'])) {
            foreach ((array) $mapping['users'] as $bid => $uid) {
                if (array_key_exists(strval($bid), (array) ($map['users'] ?? [])) && empty($map['users'][strval($bid)]) && $uid) {
                    $map['users'][strval($bid)] = intval($uid);
                    $n['users']++;
                }
            }
        }

        // Go on from the tool's last change for this project.
        $cursor = $state['last_updated'][$conn->backlog_project_key] ?? null;
        $patch = ['mapping' => json_encode($map, JSON_UNESCAPED_UNICODE), 'updated_at' => Carbon::now()];
        if ($cursor && (!$conn->cursor_updated || strcmp($cursor, $conn->cursor_updated) > 0)) {
            $patch['cursor_updated'] = $cursor;
        }
        Connections::patch($connId, $patch);
        $n['cursor'] = $patch['cursor_updated'] ?? ($conn->cursor_updated ?: '—');

        return $n;
    }
}
