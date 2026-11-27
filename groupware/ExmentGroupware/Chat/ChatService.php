<?php

namespace App\Plugins\ExmentGroupware\Chat;

use Exceedone\Exment\Enums\SystemTableName;
use Illuminate\Support\Facades\DB;

/**
 * The chat's rooms and messages (ISS02945). Every read and write checks that the person is a
 * member of the room: a room or a file of someone else's is "not found".
 */
class ChatService
{
    const PAGE = 50;
    const POLL_MAX = 200;
    const MAX_FILES = 10;
    const MAX_FILE_BYTES = 25 * 1024 * 1024;

    /** @return array<int, string> user id => name (the users of the site, for the pickers and the names) */
    public static function users(): array
    {
        $out = [];
        $model = getModelName(SystemTableName::USER);
        foreach ($model::withoutGlobalScopes()->orderBy('id')->limit(3000)->get(['id', 'value']) as $u) {
            $out[intval($u->id)] = strval(array_get($u->value, 'user_name', ''));
        }

        return $out;
    }

    /** The newest message id of the person's rooms (where the polling starts). */
    public static function topId(int $uid): int
    {
        $roomIds = DB::table('gw_chat_members')->where('user_id', $uid)->pluck('room_id')->all();

        return $roomIds === [] ? 0 : intval(DB::table('gw_chat_messages')->whereIn('room_id', $roomIds)->max('id'));
    }

    /** The membership of a person in a room, or null. */
    public static function member(int $uid, int $roomId): ?object
    {
        return DB::table('gw_chat_members')->where('room_id', $roomId)->where('user_id', $uid)->first();
    }

    /** The room, when the person is in it; else 404. */
    public static function room(int $uid, int $roomId): object
    {
        $room = DB::table('gw_chat_rooms')->where('id', $roomId)->first();
        if (!isset($room) || static::member($uid, $roomId) === null) {
            abort(404);
        }

        return $room;
    }

    /** @return array<int, string> the members of a room, user id => name */
    public static function memberNames(int $roomId, ?array $users = null): array
    {
        $users = $users ?? static::users();
        $out = [];
        foreach (DB::table('gw_chat_members')->where('room_id', $roomId)->whereNotNull('user_id')->pluck('user_id') as $id) {
            $out[intval($id)] = $users[intval($id)] ?? '';
        }

        return $out;
    }

    /**
     * The rooms of a person, newest first, with their unread counts and the last line.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function rooms(int $uid): array
    {
        $users = static::users();
        $mine = DB::table('gw_chat_members')->where('user_id', $uid)->get()->keyBy('room_id');
        if ($mine->isEmpty()) {
            return [];
        }
        $rooms = DB::table('gw_chat_rooms')->whereIn('id', $mine->keys()->all())->whereNull('archived_at')
            ->orderByDesc('last_message_id')->orderByDesc('id')->get();
        $out = [];
        foreach ($rooms as $r) {
            $m = $mine[$r->id];
            $unread = DB::table('gw_chat_messages')->where('room_id', $r->id)->where('id', '>', intval($m->last_read_id))
                ->where('created_at', '>=', $m->joined_at)->whereNull('deleted_at')
                ->where(function ($q) use ($uid) {
                    $q->whereNull('user_id')->orWhere('user_id', '<>', $uid);
                })->count();
            $last = $r->last_message_id ? DB::table('gw_chat_messages')->where('id', $r->last_message_id)->first() : null;
            $name = strval($r->name ?? '');
            if ($r->kind === 'direct') {
                [$a, $b] = array_map('intval', explode(':', strval($r->direct_key)));
                $name = $users[$a === $uid ? $b : $a] ?? '';
            }
            $out[] = [
                'id' => intval($r->id),
                'kind' => $r->kind,
                'name' => $name,
                'unread' => $unread,
                'muted' => boolval($m->muted),
                'owner' => $m->role === 'owner',
                'last' => isset($last) && $last->deleted_at === null ? mb_substr(preg_replace('/\s+/u', ' ', strval($last->body)) ?? '', 0, 60) : '',
                'last_at' => isset($last) ? strval($last->created_at) : null,
            ];
        }

        return $out;
    }

    /** A group room with its members (the maker is its owner). */
    public static function createGroup(int $uid, string $name, array $memberIds): int
    {
        $name = ChatText::cleanName($name);
        if ($name === '') {
            throw new \InvalidArgumentException('ルームの名前を入れてください。');
        }
        $users = static::users();
        $ids = array_values(array_unique(array_filter(array_map('intval', $memberIds), function ($id) use ($users, $uid) {
            return $id !== $uid && isset($users[$id]);
        })));
        $now = now();

        return DB::transaction(function () use ($uid, $name, $ids, $now) {
            $roomId = intval(DB::table('gw_chat_rooms')->insertGetId([
                'kind' => 'group', 'name' => $name, 'created_by' => $uid, 'created_at' => $now, 'updated_at' => $now,
            ]));
            $rows = [['room_id' => $roomId, 'user_id' => $uid, 'role' => 'owner', 'joined_at' => $now]];
            foreach ($ids as $id) {
                $rows[] = ['room_id' => $roomId, 'user_id' => $id, 'role' => 'member', 'joined_at' => $now];
            }
            DB::table('gw_chat_members')->insert($rows);

            return $roomId;
        });
    }

    /** The one direct room of two people, made the first time. */
    public static function direct(int $uid, int $other): int
    {
        if ($other === $uid || !isset(static::users()[$other])) {
            abort(404);
        }
        $key = ChatText::directKey($uid, $other);
        $room = DB::table('gw_chat_rooms')->where('direct_key', $key)->first();
        if (isset($room)) {
            return intval($room->id);
        }
        $now = now();

        return DB::transaction(function () use ($uid, $other, $key, $now) {
            $roomId = intval(DB::table('gw_chat_rooms')->insertGetId([
                'kind' => 'direct', 'direct_key' => $key, 'created_by' => $uid, 'created_at' => $now, 'updated_at' => $now,
            ]));
            DB::table('gw_chat_members')->insert([
                ['room_id' => $roomId, 'user_id' => $uid, 'role' => 'member', 'joined_at' => $now],
                ['room_id' => $roomId, 'user_id' => $other, 'role' => 'member', 'joined_at' => $now],
            ]);

            return $roomId;
        });
    }

    /** No bell, mail or Teams from a room (the unread count still shows). */
    public static function setMuted(int $uid, int $roomId, bool $muted): void
    {
        static::room($uid, $roomId);
        DB::table('gw_chat_members')->where('room_id', $roomId)->where('user_id', $uid)->update(['muted' => $muted]);
    }

    /** The owner names a group room and sets how long it keeps its messages. */
    public static function setRoom(int $uid, int $roomId, ?string $name, $retention): void
    {
        $room = static::room($uid, $roomId);
        $me = static::member($uid, $roomId);
        if ($room->kind !== 'group' || $me->role !== 'owner') {
            abort(403);
        }
        // null: not sent, the saved one stays; 0 or empty: always; else one of the choices, in digits
        $values = ['updated_at' => now()];
        if ($retention !== null) {
            $raw = trim(strval($retention));
            if ($raw !== '' && (!ctype_digit($raw) || ($raw !== '0' && ChatRetention::clean($raw) === null))) {
                throw new \InvalidArgumentException('残す期間は、えらぶ中から決めてください。');
            }
            $values['retention_days'] = ChatRetention::clean($raw);
        }
        if ($name !== null) {
            $name = ChatText::cleanName($name);
            if ($name === '') {
                throw new \InvalidArgumentException('ルームの名前を入れてください。');
            }
            $values['name'] = $name;
        }
        if (array_key_exists('retention_days', $values) && intval($room->retention_days) !== intval($values['retention_days'])) {
            ChatRetention::log('plugins/exment_groupware/chat/room/' . $roomId . '/retention', ['room' => $roomId, 'days' => $values['retention_days']]);
        }
        DB::table('gw_chat_rooms')->where('id', $roomId)->update($values);
    }

    /** The owner adds or removes members; anyone may leave (a direct room stays). */
    public static function setMembers(int $uid, int $roomId, array $add, array $remove): void
    {
        $room = static::room($uid, $roomId);
        $me = static::member($uid, $roomId);
        if ($room->kind !== 'group') {
            abort(404);
        }
        $remove = array_map('intval', $remove);
        if ($me->role !== 'owner' && ($add !== [] || array_diff($remove, [$uid]) !== [])) {
            abort(403);
        }
        $users = static::users();
        $now = now();
        foreach (array_map('intval', $add) as $id) {
            if (isset($users[$id]) && static::member($id, $roomId) === null) {
                DB::table('gw_chat_members')->insert(['room_id' => $roomId, 'user_id' => $id, 'role' => 'member', 'joined_at' => $now]);
            }
        }
        if ($remove !== []) {
            DB::table('gw_chat_members')->where('room_id', $roomId)->whereIn('user_id', $remove)->delete();
        }
    }

    /**
     * Messages of a room, newest page (or those before an id), oldest first, with who wrote them,
     * how many have read each, and the files.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function messages(int $uid, int $roomId, ?int $before = null, ?int $around = null): array
    {
        static::room($uid, $roomId);
        $me = static::member($uid, $roomId);
        $base = function () use ($roomId, $me) {
            return DB::table('gw_chat_messages')->where('room_id', $roomId)->where('created_at', '>=', $me->joined_at);
        };
        if ($around !== null) {
            $older = $base()->where('id', '<=', $around)->orderByDesc('id')->limit(intdiv(static::PAGE, 2))->get()->reverse();
            $newer = $base()->where('id', '>', $around)->orderBy('id')->limit(intdiv(static::PAGE, 2))->get();

            return static::shape($roomId, $older->concat($newer)->values());
        }
        $q = $base();
        if ($before !== null) {
            $q->where('id', '<', $before);
        }
        $rows = $q->orderByDesc('id')->limit(static::PAGE)->get()->reverse()->values();

        return static::shape($roomId, $rows);
    }

    /** @return array<int, array<string, mixed>> */
    protected static function shape(int $roomId, $rows): array
    {
        $users = static::users();
        $ids = [];
        $replyIds = [];
        foreach ($rows as $r) {
            $ids[] = intval($r->id);
            if ($r->reply_to !== null) {
                $replyIds[] = intval($r->reply_to);
            }
        }
        $reactions = [];
        if ($ids !== [] && \Illuminate\Support\Facades\Schema::hasTable('gw_chat_reactions')) {
            foreach (DB::table('gw_chat_reactions')->whereIn('message_id', $ids)->orderBy('id')->get() as $x) {
                $reactions[intval($x->message_id)][$x->emoji][] = intval($x->user_id);
            }
        }
        $tasks = [];
        if ($ids !== [] && \Illuminate\Support\Facades\Schema::hasTable('gw_chat_tasks')) {
            foreach (DB::table('gw_chat_tasks')->whereIn('message_id', $ids)->get() as $t) {
                $tasks[intval($t->message_id)][] = [
                    'id' => intval($t->id), 'assignee' => $t->assignee !== null ? intval($t->assignee) : null,
                    'due' => $t->due, 'done' => $t->done_at !== null, 'pm_issue' => $t->pm_issue,
                ];
            }
        }
        $quoted = [];
        if ($replyIds !== []) {
            foreach (DB::table('gw_chat_messages')->whereIn('id', array_unique($replyIds))->where('room_id', $roomId)->get() as $q) {
                $quoted[intval($q->id)] = [
                    'id' => intval($q->id),
                    'name' => $q->user_id !== null ? ($users[intval($q->user_id)] ?? '') : '',
                    'text' => $q->deleted_at !== null ? '' : ChatText::quote(strval($q->body)),
                    'deleted' => $q->deleted_at !== null,
                ];
            }
        }
        $reads = [];
        foreach (DB::table('gw_chat_members')->where('room_id', $roomId)->whereNotNull('user_id')->get(['user_id', 'last_read_id']) as $m) {
            $reads[intval($m->user_id)] = intval($m->last_read_id);
        }
        $out = [];
        foreach ($rows as $r) {
            $deleted = $r->deleted_at !== null;
            $out[] = [
                'id' => intval($r->id),
                'room' => intval($r->room_id),
                'by' => $r->user_id !== null ? intval($r->user_id) : null,
                'name' => $r->user_id !== null ? ($users[intval($r->user_id)] ?? '') : '',
                'body' => $deleted ? '' : strval($r->body),
                'deleted' => $deleted,
                'edited' => $r->edited_at !== null,
                'reply_to' => $r->reply_to !== null ? intval($r->reply_to) : null,
                'quote' => $r->reply_to !== null ? ($quoted[intval($r->reply_to)] ?? null) : null,
                'reactions' => (object) ($reactions[intval($r->id)] ?? []),
                'tasks' => $tasks[intval($r->id)] ?? [],
                'mentions' => (array) json_decode(strval($r->mentions ?? '[]'), true),
                'files' => $deleted ? [] : array_values(array_map(function ($f) {
                    return ['uuid' => strval($f['uuid'] ?? ''), 'name' => strval($f['name'] ?? ''), 'size' => intval($f['size'] ?? 0)];
                }, (array) json_decode(strval($r->files ?? '[]'), true))),
                'read' => ChatText::readCount($reads, intval($r->id), $r->user_id !== null ? intval($r->user_id) : null),
                'at' => strval($r->created_at),
            ];
        }

        return $out;
    }

    /**
     * Post a message (with files already saved), and read up to it.
     *
     * @param array<int, array{uuid: string, name: string, size: int}> $files
     */
    public static function post(int $uid, int $roomId, string $body, array $files = [], ?int $replyTo = null): array
    {
        $room = static::room($uid, $roomId);
        // a reply answers a message of the same room
        if ($replyTo !== null && !DB::table('gw_chat_messages')->where('id', $replyTo)->where('room_id', $roomId)->exists()) {
            $replyTo = null;
        }
        if ($room->archived_at !== null) {
            abort(403);
        }
        $body = ChatText::clean($body);
        if ($body === '' && $files === []) {
            throw new \InvalidArgumentException('書くことかファイルを入れてください。');
        }
        $mentions = ChatText::mentions($body, static::memberNames($roomId), $room->kind === 'group');
        $now = now();
        $id = intval(DB::table('gw_chat_messages')->insertGetId([
            'room_id' => $roomId, 'user_id' => $uid, 'body' => $body, 'reply_to' => $replyTo,
            'mentions' => json_encode($mentions), 'files' => json_encode(array_values($files)), 'created_at' => $now,
        ]));
        DB::table('gw_chat_rooms')->where('id', $roomId)->update(['last_message_id' => $id, 'updated_at' => $now]);
        DB::table('gw_chat_members')->where('room_id', $roomId)->where('user_id', $uid)->update(['last_read_id' => $id]);

        return static::shape($roomId, DB::table('gw_chat_messages')->where('id', $id)->get())[0];
    }

    /** One's own message of a room one is in, or 404. */
    protected static function ownMessage(int $uid, int $messageId): object
    {
        $m = DB::table('gw_chat_messages')->where('id', $messageId)->first();
        if (!isset($m) || static::member($uid, intval($m->room_id)) === null) {
            abort(404);
        }
        if (intval($m->user_id) !== $uid || $m->deleted_at !== null) {
            abort(403);
        }

        return $m;
    }

    /** Change one's own message; the text before is kept in the history. */
    public static function edit(int $uid, int $messageId, string $body): array
    {
        $m = static::ownMessage($uid, $messageId);
        $room = static::room($uid, intval($m->room_id));
        $body = ChatText::clean($body);
        if ($body === '' && (array) json_decode(strval($m->files ?? '[]'), true) === []) {
            throw new \InvalidArgumentException('書くことを入れてください（消すときは「消す」）。');
        }
        if ($body === strval($m->body)) {
            return static::shape(intval($m->room_id), DB::table('gw_chat_messages')->where('id', $messageId)->get())[0];
        }
        $now = now();
        DB::transaction(function () use ($m, $uid, $body, $room, $now) {
            DB::table('gw_chat_revisions')->insert(['message_id' => $m->id, 'body' => $m->body, 'user_id' => $uid, 'created_at' => $now]);
            DB::table('gw_chat_messages')->where('id', $m->id)->update([
                'body' => $body, 'edited_at' => $now,
                'mentions' => json_encode(ChatText::mentions($body, static::memberNames(intval($m->room_id)), $room->kind === 'group')),
            ]);
        });

        return static::shape(intval($m->room_id), DB::table('gw_chat_messages')->where('id', $messageId)->get())[0];
    }

    /** Delete one's own message: its text goes to the history, the row says "deleted". */
    public static function delete(int $uid, int $messageId): array
    {
        $m = static::ownMessage($uid, $messageId);
        $now = now();
        DB::transaction(function () use ($m, $uid, $now) {
            DB::table('gw_chat_revisions')->insert(['message_id' => $m->id, 'body' => $m->body, 'user_id' => $uid, 'created_at' => $now]);
            DB::table('gw_chat_messages')->where('id', $m->id)->update(['body' => null, 'deleted_at' => $now, 'mentions' => json_encode([])]);
        });

        return static::shape(intval($m->room_id), DB::table('gw_chat_messages')->where('id', $messageId)->get())[0];
    }

    /** Give a reaction, or take it back when it is there. */
    public static function react(int $uid, int $messageId, string $emoji): array
    {
        $e = ChatText::reaction($emoji);
        $m = DB::table('gw_chat_messages')->where('id', $messageId)->first();
        if (!isset($m) || static::member($uid, intval($m->room_id)) === null || $m->deleted_at !== null) {
            abort(404);
        }
        if ($e === null) {
            throw new \InvalidArgumentException('そのリアクションは使えません。');
        }
        $q = DB::table('gw_chat_reactions')->where('message_id', $messageId)->where('user_id', $uid)->where('emoji', $e);
        if ($q->exists()) {
            $q->delete();
        } else {
            DB::table('gw_chat_reactions')->insert(['message_id' => $messageId, 'user_id' => $uid, 'emoji' => $e, 'created_at' => now()]);
        }

        return static::shape(intval($m->room_id), DB::table('gw_chat_messages')->where('id', $messageId)->get())[0];
    }

    public static function markRead(int $uid, int $roomId, int $upTo): void
    {
        static::room($uid, $roomId);
        $max = intval(DB::table('gw_chat_messages')->where('room_id', $roomId)->max('id'));
        DB::table('gw_chat_members')->where('room_id', $roomId)->where('user_id', $uid)
            ->where('last_read_id', '<', min($upTo, $max))->update(['last_read_id' => min($upTo, $max)]);
    }

    /**
     * What is new since a message id: the new messages of the person's rooms, the unread counts,
     * and the read counts of the open room's recent messages.
     */
    public static function poll(int $uid, int $after, ?int $openRoom): array
    {
        $roomIds = DB::table('gw_chat_members')->where('user_id', $uid)->pluck('room_id')->all();
        $new = [];
        if ($roomIds !== []) {
            $rows = DB::table('gw_chat_messages')->whereIn('room_id', $roomIds)->where('id', '>', $after)
                ->orderBy('id')->limit(static::POLL_MAX)->get();
            foreach ($rows->groupBy('room_id') as $roomId => $list) {
                foreach (static::shape(intval($roomId), $list) as $m) {
                    $new[] = $m;
                }
            }
            usort($new, function ($a, $b) {
                return $a['id'] <=> $b['id'];
            });
        }
        $reads = [];
        $recent = [];
        if ($openRoom !== null && in_array($openRoom, array_map('intval', $roomIds), true)) {
            $rows = DB::table('gw_chat_messages')->where('room_id', $openRoom)->orderByDesc('id')->limit(static::PAGE)->get();
            foreach (static::shape($openRoom, $rows) as $m) {
                $reads[$m['id']] = $m['read'];
                $recent[] = $m;
            }
        }
        $top = intval(DB::table('gw_chat_messages')->whereIn('room_id', $roomIds ?: [0])->max('id'));

        return ['messages' => $new, 'rooms' => static::rooms($uid), 'reads' => (object) $reads, 'recent' => $recent, 'top' => max($after, $top)];
    }

    /**
     * A file of the chat for a member of its room: the message that carries it, in one of the
     * person's rooms, else null.
     */
    public static function fileFor(int $uid, string $uuid): ?array
    {
        if (!preg_match('/^[0-9a-f]{32}$/', $uuid)) {
            return null;
        }
        $roomIds = DB::table('gw_chat_members')->where('user_id', $uid)->pluck('room_id')->all();
        if ($roomIds === []) {
            return null;
        }
        $row = DB::table('gw_chat_messages')->whereIn('room_id', $roomIds)->whereNull('deleted_at')
            ->where('files', 'like', '%"' . $uuid . '"%')->first();
        if (!isset($row)) {
            return null;
        }
        foreach ((array) json_decode(strval($row->files), true) as $f) {
            if (strval($f['uuid'] ?? '') === $uuid) {
                return $f;
            }
        }

        return null;
    }
}
