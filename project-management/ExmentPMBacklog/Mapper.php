<?php

namespace App\Plugins\ExmentPMBacklog;

use Carbon\Carbon;
use Exceedone\Exment\Enums\SystemTableName;
use Exceedone\Exment\Model\CustomTable;

/**
 * Match a Backlog project's statuses, types, priorities, categories,
 * milestones, resolutions and users to Exment's, and create in Exment what
 * it lacks (the same as backlog_sync's init --create).
 *
 * Users are matched by mail address. A user the administrator set by hand
 * stays as set: refreshing only fills what is still empty.
 */
class Mapper
{
    /** Backlog's own id of its 完了 status, which is always the last. */
    const BACKLOG_DONE_ID = 4;

    /**
     * @param array<string, string> $manualUsers backlog user id => exment user id ('' = none)
     * @return array<string, mixed> the new mapping
     */
    public static function refresh(object $conn, bool $create, array $manualUsers = []): array
    {
        $bl = Connections::client($conn);
        $old = Connections::mapping($conn);
        $pid = intval($conn->exment_project_id);
        $project = static::project($pid);
        if (!$project) {
            throw new \RuntimeException(Lang::t('error_no_project', ['id' => $pid]));
        }

        $proj = (array) $bl->get('projects/' . rawurlencode($conn->backlog_project_key));
        $bpid = intval($proj['id']);
        $blStatus = (array) $bl->get('projects/' . $bpid . '/statuses');
        $blType = (array) $bl->get('projects/' . $bpid . '/issueTypes');
        $blCat = (array) $bl->get('projects/' . $bpid . '/categories');
        $blMs = (array) $bl->get('projects/' . $bpid . '/versions');
        $blPrio = (array) $bl->get('priorities');
        $blRes = (array) $bl->get('resolutions');
        $blUsers = (array) $bl->get('projects/' . $bpid . '/users');

        $exStatus = Pm::byName(Pm::children('status_table', $pid));
        $exType = Pm::byName(Pm::children('issue_type_table', $pid));
        $exCat = Pm::byName(Pm::byProjectColumn('category_table', $pid));
        $exMs = Pm::byName(Pm::byProjectColumn('milestone_table', $pid));
        $exPrio = Pm::byName(Pm::all('priority_table'));
        $exRes = Pm::byName(Pm::all('resolution_table'));
        $created = [];

        if ($create) {
            foreach (array_values($blStatus) as $i => $s) {
                if (!isset($exStatus[$s['name']])) {
                    $exStatus[$s['name']] = static::createChild('status_table', $pid, [
                        'name' => $s['name'],
                        'color' => $s['color'] ?? null,
                        'display_order' => $i,
                        'is_done' => intval($s['id']) === static::BACKLOG_DONE_ID ? 1 : 0,
                    ]);
                    $created[] = Lang::t('created_status', ['name' => $s['name']]);
                }
            }
            static::reorderStatuses($pid, array_column($blStatus, 'name'));
            foreach (array_values($blType) as $i => $t) {
                if (!isset($exType[$t['name']])) {
                    $exType[$t['name']] = static::createChild('issue_type_table', $pid, [
                        'name' => $t['name'],
                        'color' => $t['color'] ?? null,
                        'display_order' => $i,
                        'template_subject' => $t['templateSummary'] ?? null,
                        'template_description' => $t['templateDescription'] ?? null,
                    ]);
                    $created[] = Lang::t('created_type', ['name' => $t['name']]);
                }
            }
            foreach (array_values($blCat) as $i => $c) {
                if (!isset($exCat[$c['name']])) {
                    $exCat[$c['name']] = static::createRow('category_table', [
                        'name' => $c['name'], 'project' => strval($pid), 'display_order' => $i,
                    ]);
                    $created[] = Lang::t('created_category', ['name' => $c['name']]);
                }
            }
            foreach (array_values($blMs) as $i => $m) {
                if (!isset($exMs[$m['name']])) {
                    $exMs[$m['name']] = static::createRow('milestone_table', [
                        'name' => $m['name'],
                        'project' => strval($pid),
                        'display_order' => $i,
                        'start_date' => substr(strval($m['startDate'] ?? ''), 0, 10) ?: null,
                        'due_date' => substr(strval($m['releaseDueDate'] ?? ''), 0, 10) ?: null,
                        'note' => strval($m['description'] ?? ''),
                        'archived' => !empty($m['archived']) ? 1 : 0,
                    ]);
                    $created[] = Lang::t('created_milestone', ['name' => $m['name']]);
                }
            }
        }

        $match = function (array $rows, array $ex) {
            $out = [];
            foreach ($rows as $r) {
                $out[$r['name']] = $ex[$r['name']] ?? null;
            }

            return $out;
        };

        // Users: by hand first, then what was there, then the mail address.
        $byMail = static::exmentUsersByMail();
        $oldUsers = (array) ($old['users'] ?? []);
        $users = [];
        $names = [];
        foreach ($blUsers as $u) {
            $bid = strval($u['id']);
            $names[$bid] = strval($u['name'] ?? '');
            if (array_key_exists($bid, $manualUsers)) {
                $users[$bid] = $manualUsers[$bid] === '' ? null : intval($manualUsers[$bid]);
                continue;
            }
            if (!empty($oldUsers[$bid])) {
                $users[$bid] = intval($oldUsers[$bid]);
                continue;
            }
            $mail = strtolower(trim(strval($u['mailAddress'] ?? '')));
            $users[$bid] = ($mail !== '' && isset($byMail[$mail])) ? $byMail[$mail] : null;
        }

        $mapping = [
            'backlog_project_id' => $bpid,
            'backlog_project_name' => strval($proj['name'] ?? ''),
            'status' => $match($blStatus, $exStatus),
            'issue_type' => $match($blType, $exType),
            'priority' => $match($blPrio, $exPrio),
            'category' => $match($blCat, $exCat),
            'milestone' => $match($blMs, $exMs),
            'resolution' => $match($blRes, $exRes),
            'default_status' => $exStatus ? min($exStatus) : null,
            'default_issue_type' => $exType ? min($exType) : null,
            'users' => $users,
            'user_names' => $names,
            'refreshed_at' => Carbon::now()->format('Y-m-d H:i:s'),
            'created' => $created,
        ];
        Connections::patch(intval($conn->id), [
            'backlog_project_id' => $bpid,
            'mapping' => json_encode($mapping, JSON_UNESCAPED_UNICODE),
            'updated_at' => Carbon::now(),
        ]);

        return $mapping;
    }

    /**
     * What is still missing, by kind, for the settings screen.
     *
     * @param array<string, mixed> $mapping
     * @return array<string, array<int, string>>
     */
    public static function missing(array $mapping): array
    {
        $out = [];
        foreach (['status', 'issue_type', 'priority', 'category', 'milestone', 'resolution'] as $kind) {
            $miss = [];
            foreach ((array) ($mapping[$kind] ?? []) as $name => $id) {
                if (empty($id)) {
                    $miss[] = strval($name);
                }
            }
            if ($miss) {
                $out[$kind] = $miss;
            }
        }

        return $out;
    }

    public static function project(int $id)
    {
        $t = Pm::table('project_table');

        return $t ? $t->getValueModel($id) : null;
    }

    /** @return array<string, int> lower-case mail => user id */
    protected static function exmentUsersByMail(): array
    {
        $out = [];
        $t = CustomTable::getEloquent(SystemTableName::USER);
        foreach ($t->getValueModel()->query()->get() as $u) {
            $mail = strtolower(trim(strval(array_get($u->value, 'email'))));
            if ($mail !== '') {
                $out[$mail] = intval($u->id);
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $value
     */
    protected static function createChild(string $role, int $projectId, array $value): int
    {
        $row = Pm::table($role)->getValueModel();
        $row->saved_notify(false);
        $row->parent_type = Pm::name('project_table');
        $row->parent_id = $projectId;
        $row->setValue($value);
        $row->save();

        return intval($row->id);
    }

    /**
     * @param array<string, mixed> $value
     */
    protected static function createRow(string $role, array $value): int
    {
        $row = Pm::table($role)->getValueModel();
        $row->saved_notify(false);
        $row->setValue($value);
        $row->save();

        return intval($row->id);
    }

    /**
     * Put Exment's statuses in Backlog's order. A status only Exment has goes
     * right after the one before it now. ExmentPM's 完了以外 hides only the
     * last done status in this order, so 完了 has to stay last (ISS00255).
     *
     * @param array<int, string> $blNames
     */
    public static function reorderStatuses(int $projectId, array $blNames): void
    {
        $rows = Pm::children('status_table', $projectId)->sortBy(function ($r) {
            return sprintf('%08d-%08d', intval(array_get($r->value, 'display_order')), $r->id);
        })->values();
        $byName = [];
        foreach ($rows as $r) {
            $byName[strval(array_get($r->value, 'name'))] = $r;
        }
        $order = array_values(array_filter($blNames, function ($n) use ($byName) {
            return isset($byName[$n]);
        }));
        $prev = null;
        foreach ($rows as $r) {
            $name = strval(array_get($r->value, 'name'));
            if (!in_array($name, $blNames, true)) {
                $at = $prev !== null ? array_search($prev, $order, true) : false;
                array_splice($order, $at === false ? 0 : $at + 1, 0, [$name]);
            }
            $prev = $name;
        }
        foreach ($order as $i => $name) {
            $r = $byName[$name];
            if (strval(array_get($r->value, 'display_order')) !== strval($i)) {
                $r->saved_notify(false);
                $r->setValue(['display_order' => $i])->save();
            }
        }
    }
}
