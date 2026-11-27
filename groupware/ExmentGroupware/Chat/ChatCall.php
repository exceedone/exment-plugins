<?php

namespace App\Plugins\ExmentGroupware\Chat;

use Illuminate\Support\Facades\DB;

/**
 * A call from a 1:1 chat room (ISS02946 step 1): voice, and a screen shared either way. The
 * browsers connect to each other (WebRTC); here are only the call and the signals they pass
 * (offer, answer, ICE candidates, join, hangup), for the two members of the room.
 */
class ChatCall
{
    const TYPES = ['join', 'offer', 'answer', 'ice', 'hangup'];
    /** People in one call: each is connected to each other, so a few only (ISS02946 step 2). */
    const MAX_PEOPLE = 4;
    const MAX_PAYLOAD = 65536;
    const SIGNALS_PER_CALL = 2000;
    /** The line of the room that starts a call (the page shows 「参加」 on it). */
    const LINE = '📞 通話 #%d を始めました';

    /** The call and its room, for a member; else 404. */
    public static function call(int $uid, int $callId): array
    {
        $call = DB::table('gw_chat_calls')->where('id', $callId)->first();
        if (!isset($call)) {
            abort(404);
        }
        $room = ChatService::room($uid, intval($call->room_id));

        return [$call, $room];
    }

    /** The other member of the call's room. */
    protected static function other(int $uid, object $room): int
    {
        foreach (DB::table('gw_chat_members')->where('room_id', $room->id)->whereNotNull('user_id')->pluck('user_id') as $id) {
            if (intval($id) !== $uid) {
                return intval($id);
            }
        }
        abort(404);
    }

    /** Start a call in a 1:1 room: the call, and a line in the room (its notices ring the bell). */
    public static function start(int $uid, int $roomId, string $roomUrl): array
    {
        $room = ChatService::room($uid, $roomId);
        // the signals of calls over for an hour are of no use
        DB::table('gw_chat_signals')->where('created_at', '<', now()->subHour())->delete();
        $id = intval(DB::table('gw_chat_calls')->insertGetId(['room_id' => $roomId, 'started_by' => $uid, 'started_at' => now()]));
        DB::table('gw_chat_call_members')->insert(['call_id' => $id, 'user_id' => $uid, 'joined_at' => now()]);
        $message = ChatService::post($uid, $roomId, sprintf(static::LINE, $id));
        ChatNotify::note($roomId, $message, $roomUrl . '?room=' . $roomId . '&message=' . intval($message['id']));

        return ['call' => $id, 'message' => $message, 'people' => []];
    }

    /** The people in a call now (not who left). */
    public static function people(int $callId): array
    {
        return array_map('intval', DB::table('gw_chat_call_members')->where('call_id', $callId)->whereNull('left_at')->pluck('user_id')->all());
    }

    /**
     * Join a call: a member of its room, while it lasts, while it has room. The answer is who is
     * in it already: the one who joins offers to each of them.
     */
    public static function join(int $uid, int $callId): array
    {
        [$call] = static::call($uid, $callId);
        if ($call->ended_at !== null) {
            throw new \InvalidArgumentException('この通話は終わっています。');
        }
        $people = array_values(array_diff(static::people($callId), [$uid]));
        if (count($people) >= static::MAX_PEOPLE) {
            throw new \InvalidArgumentException('この通話は ' . static::MAX_PEOPLE . ' 人までです。');
        }
        $row = DB::table('gw_chat_call_members')->where('call_id', $callId)->where('user_id', $uid);
        if ($row->exists()) {
            $row->update(['left_at' => null, 'joined_at' => now()]);
        } else {
            DB::table('gw_chat_call_members')->insert(['call_id' => $callId, 'user_id' => $uid, 'joined_at' => now()]);
        }
        if ($call->answered_at === null && $people !== []) {
            DB::table('gw_chat_calls')->where('id', $callId)->update(['answered_at' => now()]);
        }

        return ['call' => $callId, 'people' => $people];
    }

    /** Leave a call: the others are told; the call ends when nobody is left. */
    public static function leave(int $uid, int $callId): array
    {
        [$call, $room] = static::call($uid, $callId);
        DB::table('gw_chat_call_members')->where('call_id', $callId)->where('user_id', $uid)->update(['left_at' => now()]);
        foreach (static::people($callId) as $other) {
            static::put($callId, $uid, $other, 'hangup', null);
        }
        if (static::people($callId) === []) {
            return static::end($uid, $callId);
        }

        return ['ended' => false];
    }

    protected static function put(int $callId, int $from, int $to, string $type, ?string $json): void
    {
        DB::table('gw_chat_signals')->insert([
            'call_id' => $callId, 'from_user' => $from, 'to_user' => $to, 'type' => $type, 'payload' => $json, 'created_at' => now(),
        ]);
    }

    /** Pass a signal to the other member. */
    public static function signal(int $uid, int $callId, string $type, $payload, ?int $to = null): void
    {
        [$call, $room] = static::call($uid, $callId);
        if ($to === null) {
            // only a 1:1 room has an "other one"
            if ($room->kind !== 'direct') {
                throw new \InvalidArgumentException('合図の相手を決めてください。');
            }
            $to = static::other($uid, $room);
        }
        // the sender must be in the call too (a hangup also from who just left)
        if ($type !== 'hangup' && !in_array($uid, static::people($callId), true)) {
            abort(404);
        }
        // only to someone of the room who is in the call (a hangup also to who just left)
        if ($to === $uid || ChatService::member($to, intval($room->id)) === null
            || ($type !== 'hangup' && !in_array($to, static::people($callId), true))) {
            abort(404);
        }
        if ($call->ended_at !== null && $type !== 'hangup') {
            throw new \InvalidArgumentException('この通話は終わっています。');
        }
        if (!in_array($type, static::TYPES, true)) {
            throw new \InvalidArgumentException('その合図は使えません。');
        }
        $json = $payload === null ? null : json_encode($payload);
        if ($json !== null && strlen($json) > static::MAX_PAYLOAD) {
            throw new \InvalidArgumentException('合図が大きすぎます。');
        }
        if (DB::table('gw_chat_signals')->where('call_id', $callId)->count() >= static::SIGNALS_PER_CALL) {
            throw new \InvalidArgumentException('合図が多すぎます。');
        }
        if ($type === 'answer' && $call->answered_at === null) {
            DB::table('gw_chat_calls')->where('id', $callId)->update(['answered_at' => now()]);
        }
        static::put($callId, $uid, $to, $type, $json);
    }

    /** The signals for the person since an id. */
    public static function signals(int $uid, int $callId, int $after): array
    {
        [$call] = static::call($uid, $callId);
        $rows = DB::table('gw_chat_signals')->where('call_id', $callId)->where('to_user', $uid)->where('id', '>', $after)
            ->orderBy('id')->limit(200)->get();

        return [
            'ended' => $call->ended_at !== null,
            'people' => static::people($callId),
            'signals' => $rows->map(function ($r) {
                return ['id' => intval($r->id), 'from' => intval($r->from_user), 'type' => $r->type, 'payload' => $r->payload !== null ? json_decode($r->payload, true) : null];
            })->values()->all(),
        ];
    }

    /** End the call, and say in the room how long it was. */
    public static function end(int $uid, int $callId): array
    {
        [$call, $room] = static::call($uid, $callId);
        if ($call->ended_at === null) {
            $now = now();
            DB::table('gw_chat_calls')->where('id', $callId)->update(['ended_at' => $now]);
            foreach (static::people($callId) as $other) {
                if ($other !== $uid) {
                    static::put($callId, $uid, $other, 'hangup', null);
                }
            }
            DB::table('gw_chat_call_members')->where('call_id', $callId)->whereNull('left_at')->update(['left_at' => $now]);
            $line = $call->answered_at !== null
                ? '📞 通話 #' . $callId . ' を終えました（' . static::duration(strtotime(strval($call->answered_at)), $now->getTimestamp()) . '）'
                : '📞 通話 #' . $callId . ' はつながりませんでした';
            ChatService::post($uid, intval($room->id), $line);
        }

        return ['ended' => true];
    }

    /** "1 分 05 秒", "1 時間 2 分" (no database). */
    public static function duration(int $from, int $to): string
    {
        $s = max(0, $to - $from);
        if ($s >= 3600) {
            return intdiv($s, 3600) . ' 時間 ' . intdiv($s % 3600, 60) . ' 分';
        }

        return intdiv($s, 60) . ' 分 ' . sprintf('%02d', $s % 60) . ' 秒';
    }

    /**
     * The STUN / TURN servers of the plugin's setting: one per line, "stun:host:port", or
     * "turn:host:port|user|password". Nothing when empty (the PCs must reach each other).
     *
     * @return array<int, array<string, string>>
     */
    public static function iceServers($text): array
    {
        $out = [];
        foreach (preg_split('/\r\n|\r|\n/', strval($text ?? '')) ?: [] as $line) {
            $parts = array_map('trim', explode('|', trim($line)));
            if (!preg_match('#^(stun|stuns|turn|turns):[A-Za-z0-9.\-\[\]:]+(\?transport=(udp|tcp))?$#', $parts[0])) {
                continue;
            }
            $s = ['urls' => $parts[0]];
            if (str_starts_with($parts[0], 'turn')) {
                if (count($parts) < 3) {
                    continue;
                }
                $s['username'] = $parts[1];
                $s['credential'] = $parts[2];
            }
            $out[] = $s;
            if (count($out) >= 5) {
                break;
            }
        }

        return $out;
    }
}
