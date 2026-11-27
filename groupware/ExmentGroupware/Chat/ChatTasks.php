<?php

namespace App\Plugins\ExmentGroupware\Chat;

use Exceedone\Exment\Enums\Permission;
use Exceedone\Exment\Enums\RelationType;
use Exceedone\Exment\Model\CustomColumn;
use Exceedone\Exment\Model\CustomRelation;
use Exceedone\Exment\Model\CustomTable;
use Illuminate\Support\Facades\DB;

/**
 * 「タスクにする」 and 「課題にする」 from a chat message, and the search of the chat (ISS02945
 * step 2). A task is the chat's own (assignee, due, done). An issue is made in ExmentPM when it
 * is there, read as Exment tables (project, pm_issue): no code of ExmentPM is used, the way the
 * help desk does it.
 */
class ChatTasks
{
    const PROJECT_TABLE = 'project';
    const ISSUE_TABLE = 'pm_issue';
    const SEARCH_MAX = 50;

    /** A message of a room the person is in, or 404. */
    protected static function message(int $uid, int $messageId): object
    {
        $m = DB::table('gw_chat_messages')->where('id', $messageId)->first();
        if (!isset($m) || $m->deleted_at !== null || ChatService::member($uid, intval($m->room_id)) === null) {
            abort(404);
        }

        return $m;
    }

    /** The task's title from the message: its first line, at most 200. */
    public static function titleOf(string $body): string
    {
        $line = trim(strtok(str_replace("\r", '', $body) . "\n", "\n") ?: '');

        return mb_substr($line !== '' ? $line : 'チャットのタスク', 0, 200);
    }

    public static function create(int $uid, int $messageId, ?int $assignee, ?string $due, string $title = ''): array
    {
        $m = static::message($uid, $messageId);
        if ($assignee !== null && ChatService::member($assignee, intval($m->room_id)) === null) {
            throw new \InvalidArgumentException('担当はこのルームのメンバーから選んでください。');
        }
        if ($due !== null && $due !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $due)) {
            throw new \InvalidArgumentException('期限は 2027-01-31 のような日付で入れてください。');
        }
        $title = ChatText::cleanName(trim($title) !== '' ? $title : static::titleOf(strval($m->body)));
        $now = now();
        DB::table('gw_chat_tasks')->insert([
            'message_id' => $m->id, 'room_id' => $m->room_id, 'title' => mb_substr($title, 0, 200),
            'assignee' => $assignee, 'due' => $due ?: null, 'created_by' => $uid, 'created_at' => $now, 'updated_at' => $now,
        ]);

        return static::mine($uid);
    }

    /** Done, or open again: the assignee or the maker. */
    public static function done(int $uid, int $taskId, bool $done): array
    {
        $t = DB::table('gw_chat_tasks')->where('id', $taskId)->first();
        if (!isset($t) || ChatService::member($uid, intval($t->room_id)) === null) {
            abort(404);
        }
        if (intval($t->assignee) !== $uid && intval($t->created_by) !== $uid) {
            abort(403);
        }
        DB::table('gw_chat_tasks')->where('id', $taskId)->update(['done_at' => $done ? now() : null, 'updated_at' => now()]);

        return static::mine($uid);
    }

    /**
     * The person's tasks: given to them or made by them, of rooms they are in; open first.
     *
     * @return array{tasks: array<int, array<string, mixed>>}
     */
    public static function mine(int $uid): array
    {
        $rooms = DB::table('gw_chat_members')->where('user_id', $uid)->pluck('room_id')->all();
        $users = ChatService::users();
        $rows = $rooms === [] ? collect() : DB::table('gw_chat_tasks')->whereIn('room_id', $rooms)
            ->where(function ($q) use ($uid) {
                $q->where('assignee', $uid)->orWhere('created_by', $uid);
            })
            ->orderByRaw('done_at is not null')->orderByRaw('due is null')->orderBy('due')->orderByDesc('id')->limit(200)->get();

        return ['tasks' => $rows->map(function ($t) use ($users) {
            return [
                'id' => intval($t->id), 'room' => intval($t->room_id), 'message' => intval($t->message_id),
                'title' => strval($t->title), 'assignee' => $t->assignee !== null ? intval($t->assignee) : null,
                'assignee_name' => $t->assignee !== null ? ($users[intval($t->assignee)] ?? '') : '',
                'due' => $t->due, 'done' => $t->done_at !== null, 'pm_issue' => $t->pm_issue,
            ];
        })->values()->all()];
    }

    // ---- 「課題にする」 (ExmentPM) -------------------------------------------

    public static function pmAvailable(): bool
    {
        return CustomTable::getEloquent(static::ISSUE_TABLE) !== null && CustomTable::getEloquent(static::PROJECT_TABLE) !== null;
    }

    public static function pmCanCreate(): bool
    {
        $t = CustomTable::getEloquent(static::ISSUE_TABLE);

        return isset($t) && boolval($t->hasPermission(Permission::AVAILABLE_EDIT_CUSTOM_VALUE));
    }

    /** @return array<string, string> the open projects the person may see */
    public static function projects(): array
    {
        $t = CustomTable::getEloquent(static::PROJECT_TABLE);
        if (!isset($t)) {
            return [];
        }
        $out = [];
        foreach ($t->getValueModel()->query()->orderBy('id', 'desc')->limit(300)->get() as $p) {
            if (!empty(array_get($p->value, 'archived'))) {
                continue;
            }
            $code = strval(array_get($p->value, 'project_code'));
            $out[strval($p->id)] = trim(($code !== '' ? $code . ' ' : '') . strval(array_get($p->value, 'name')));
        }

        return $out;
    }

    /** Make an issue of a project from a message (the person's own permissions). */
    public static function createIssue(int $uid, int $messageId, int $projectId, string $chatUrl): array
    {
        $m = static::message($uid, $messageId);
        if (!static::pmAvailable() || !static::pmCanCreate()) {
            throw new \InvalidArgumentException('ExmentPM の課題を作る権限がありません。');
        }
        $project = CustomTable::getEloquent(static::PROJECT_TABLE)->getValueModel()->query()->find($projectId);
        if (!isset($project)) {
            throw new \InvalidArgumentException('プロジェクトが見つかりません（見られるプロジェクトを選んでください）。');
        }
        $issues = CustomTable::getEloquent(static::ISSUE_TABLE);
        $values = [
            'subject' => static::titleOf(strval($m->body)),
            'description' => strval($m->body) . "\n\n---\nチャットのメッセージ：" . $chatUrl,
        ];
        foreach (['status', 'issue_type'] as $col) {
            $first = static::firstOfProject($issues, $col, $projectId);
            if ($first !== null) {
                $values[$col] = $first;
            }
        }
        $issue = $issues->getValueModel();
        $issue->setValue($values);
        $issue->parent_type = static::PROJECT_TABLE;
        $issue->parent_id = $projectId;
        $issue->save();
        $now = now();
        DB::table('gw_chat_tasks')->insert([
            'message_id' => $m->id, 'room_id' => $m->room_id, 'title' => mb_substr($values['subject'], 0, 200),
            'pm_issue' => $projectId . ':' . $issue->id, 'created_by' => $uid, 'created_at' => $now, 'updated_at' => $now,
        ]);

        return ['url' => admin_url('plugins/exment_pm/' . $projectId . '/issue/' . $issue->id)] + static::mine($uid);
    }

    /** The first row (display_order) of the project's own list a column points at (as ExmentPM fills a new issue). */
    protected static function firstOfProject(CustomTable $issues, string $col, int $projectId): ?string
    {
        $column = CustomColumn::getEloquent($col, $issues);
        if (!isset($column) || $column->column_type !== 'select_table') {
            return null;
        }
        $target = CustomTable::getEloquent(array_get($column->options, 'select_target_table'));
        $relation = isset($target) ? CustomRelation::getRelationByChild($target, RelationType::ONE_TO_MANY) : null;
        if (!isset($relation) || optional($relation->parent_custom_table)->table_name !== static::PROJECT_TABLE) {
            return null;
        }
        $q = $target->getValueModel()->query()->withoutGlobalScope(\Exceedone\Exment\Model\CustomValueModelScope::class)
            ->where('parent_type', static::PROJECT_TABLE)->where('parent_id', $projectId);
        if (CustomColumn::getEloquent('display_order', $target) !== null) {
            $q->orderByRaw("CAST(JSON_UNQUOTE(JSON_EXTRACT(value, '$.display_order')) AS UNSIGNED) asc");
        }
        $row = $q->orderBy('id')->first();

        return isset($row) ? strval($row->id) : null;
    }

    // ---- search -------------------------------------------------------------

    /** The words of a search: up to 5, each 1 to 50 characters, LIKE's own marks as text. */
    public static function words(string $q): array
    {
        $out = [];
        foreach (preg_split('/[\s　]+/u', trim($q)) ?: [] as $w) {
            if ($w === '') {
                continue;
            }
            $out[] = addcslashes(mb_substr($w, 0, 50), '%_\\');
            if (count($out) >= 5) {
                break;
            }
        }

        return $out;
    }

    /** Messages of the person's rooms (or one of them) with every word, newest first. */
    public static function search(int $uid, string $q, ?int $roomId): array
    {
        $words = static::words($q);
        if ($words === []) {
            return ['results' => []];
        }
        $rooms = DB::table('gw_chat_members')->where('user_id', $uid)->get(['room_id', 'joined_at'])->keyBy('room_id');
        if ($roomId !== null) {
            $rooms = $rooms->only([$roomId]);
        }
        if ($rooms->isEmpty()) {
            return ['results' => []];
        }
        $query = DB::table('gw_chat_messages')->whereIn('room_id', $rooms->keys()->all())->whereNull('deleted_at');
        foreach ($words as $w) {
            $query->where('body', 'like', '%' . $w . '%');
        }
        $users = ChatService::users();
        $out = [];
        foreach ($query->orderByDesc('id')->limit(static::SEARCH_MAX * 2)->get() as $m) {
            // not what was said before the person joined
            if (strval($m->created_at) < strval($rooms[$m->room_id]->joined_at)) {
                continue;
            }
            $out[] = ['id' => intval($m->id), 'room' => intval($m->room_id), 'name' => $users[intval($m->user_id)] ?? '', 'text' => ChatText::quote(strval($m->body)), 'at' => strval($m->created_at)];
            if (count($out) >= static::SEARCH_MAX) {
                break;
            }
        }

        return ['results' => $out];
    }
}
