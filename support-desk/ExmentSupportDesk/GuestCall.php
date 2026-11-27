<?php

namespace App\Plugins\ExmentITSupport;

use Exceedone\Exment\Model\CustomValue;
use Illuminate\Support\Facades\DB;

/**
 * A call with a customer by a link from a ticket (ISS02941): voice and a shared screen, browser
 * to browser (WebRTC). The help desk's own copy of the Groupware's call (the plugins share no
 * code). See docs/guest-chat-design.md.
 *
 * - The link (/support/call/<token>) is good for 30 minutes and for one browser: the first one
 *   that opens it gets a second secret (bind), and only that browser may then signal.
 * - Only hashes are kept. The agent signals through the desk (login, agents only).
 */
class GuestCall
{
    const MINUTES = 30;
    const TYPES = ['join', 'offer', 'answer', 'ice', 'hangup'];
    const MAX_PAYLOAD = 65536;
    const SIGNALS_PER_CALL = 2000;

    public static function enabled(): bool
    {
        return Feature::on(Feature::GUEST_CALL);
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

    /** The STUN / TURN servers of the setting, as the Groupware's (empty: the PCs must reach each other). */
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

    /** A new call of a ticket by an agent, and its link (the token is only in the link). */
    public static function create(CustomValue $ticket, int $agentId): array
    {
        $token = bin2hex(random_bytes(32));
        DB::table('its_call_signals')->where('created_at', '<', now()->subHours(2))->delete();
        $id = intval(DB::table('its_calls')->insertGetId([
            'ticket_id' => $ticket->id, 'agent_id' => $agentId, 'token_hash' => Tokens::hash($token),
            'expires_at' => now()->addMinutes(static::MINUTES), 'created_at' => now(), 'updated_at' => now(),
        ]));

        return ['call' => $id, 'link' => PublicRoutes::url('call', $token)];
    }

    /** The call of a link, not over, not expired; else null. */
    /** How long a call that was taken may last (the link itself is good for 30 minutes to open). */
    const TAKEN_HOURS = 4;

    public static function byToken(string $token, bool $ended = false): ?object
    {
        if (!preg_match('/^[0-9a-f]{64}$/', $token)) {
            return null;
        }
        $c = DB::table('its_calls')->where('token_hash', Tokens::hash($token))->first();
        if (!isset($c)) {
            return null;
        }
        if ($c->ended_at !== null) {
            // read once more after the end (the other side's hangup), for a few minutes
            return $ended && strtotime(strval($c->ended_at)) > time() - 600 ? $c : null;
        }
        $live = $c->joined_at !== null
            ? strtotime(strval($c->joined_at)) > time() - static::TAKEN_HOURS * 3600
            : strtotime(strval($c->expires_at)) > time();

        return $live ? $c : null;
    }

    /**
     * The guest's browser takes the call: the first one gets the bind secret; another one is
     * refused (409); the same one again gets the same answer when it sends its secret.
     */
    public static function bind(string $token, ?string $bind): array
    {
        $c = static::byToken($token);
        if (!isset($c)) {
            abort(404);
        }
        if ($c->bound_hash !== null) {
            if ($bind !== null && hash_equals($c->bound_hash, Tokens::hash($bind))) {
                return ['call' => intval($c->id), 'bind' => $bind];
            }
            abort(409);
        }
        $secret = bin2hex(random_bytes(32));
        $n = DB::table('its_calls')->where('id', $c->id)->whereNull('bound_hash')->update(['bound_hash' => Tokens::hash($secret), 'joined_at' => now(), 'updated_at' => now()]);
        if ($n !== 1) {
            abort(409);
        }

        return ['call' => intval($c->id), 'bind' => $secret];
    }

    /** The call of a guest: the link and its bind secret. */
    public static function guestCall(string $token, string $bind, bool $ended = false): object
    {
        $c = static::byToken($token, $ended);
        if (!isset($c) || $c->bound_hash === null || !hash_equals($c->bound_hash, Tokens::hash($bind))) {
            abort(404);
        }

        return $c;
    }

    /** The call of an agent: a call of a ticket the agent may see (the desk's own check). */
    public static function agentCall(int $callId): object
    {
        $c = DB::table('its_calls')->where('id', $callId)->first();
        if (!isset($c) || Tickets::find($c->ticket_id) === null) {
            abort(404);
        }

        return $c;
    }

    public static function signal(object $call, string $side, string $type, $payload): void
    {
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
        if (DB::table('its_call_signals')->where('call_id', $call->id)->count() >= static::SIGNALS_PER_CALL) {
            throw new \InvalidArgumentException('合図が多すぎます。');
        }
        if ($type === 'answer' && $call->answered_at === null) {
            DB::table('its_calls')->where('id', $call->id)->update(['answered_at' => now()]);
        }
        DB::table('its_call_signals')->insert(['call_id' => $call->id, 'side' => $side, 'type' => $type, 'payload' => $json, 'created_at' => now()]);
    }

    /** The signals for one side: those the other side sent, after an id. */
    public static function signals(object $call, string $side, int $after): array
    {
        $other = $side === 'agent' ? 'guest' : 'agent';
        $rows = DB::table('its_call_signals')->where('call_id', $call->id)->where('side', $other)->where('id', '>', $after)->orderBy('id')->limit(200)->get();
        $fresh = DB::table('its_calls')->where('id', $call->id)->first();

        return [
            'ended' => isset($fresh) && $fresh->ended_at !== null,
            'joined' => isset($fresh) && $fresh->joined_at !== null,
            'signals' => $rows->map(function ($r) {
                return ['id' => intval($r->id), 'type' => $r->type, 'payload' => $r->payload !== null ? json_decode($r->payload, true) : null];
            })->values()->all(),
        ];
    }

    /**
     * End the call: the length on the ticket and in its time spent, the agent's notes as a note.
     */
    public static function end(object $call, string $side, string $notes = ''): array
    {
        $fresh = DB::table('its_calls')->where('id', $call->id)->first();
        // the ticket whoever ended first: the agent's notes come with the agent's end, maybe the second
        $ticket = isset($fresh) ? Tickets::find($fresh->ticket_id) : null;
        if (isset($fresh) && $fresh->ended_at === null) {
            $now = now();
            DB::table('its_calls')->where('id', $call->id)->update(['ended_at' => $now, 'updated_at' => $now]);
            DB::table('its_call_signals')->insert(['call_id' => $call->id, 'side' => $side, 'type' => 'hangup', 'payload' => null, 'created_at' => $now]);
            if (isset($ticket)) {
                if ($fresh->answered_at !== null) {
                    $from = strtotime(strval($fresh->answered_at));
                    $minutes = intval(ceil(max(0, $now->getTimestamp() - $from) / 60));
                    Tickets::addMessage($ticket, ['kind' => 'system', 'body' => 'お客様と通話しました（' . static::duration($from, $now->getTimestamp()) . '）。', 'via' => 'staff']);
                    $ticket->setValue(['time_spent' => intval(array_get($ticket->value, 'time_spent', 0)) + $minutes]);
                    $ticket->save();
                } else {
                    Tickets::addMessage($ticket, ['kind' => 'system', 'body' => 'お客様との通話は、つながりませんでした。', 'via' => 'staff']);
                }
            }
        }
        $notes = trim(mb_substr(strval($notes), 0, 5000));
        if ($side === 'agent' && $notes !== '' && isset($ticket)) {
            Tickets::addMessage($ticket, ['kind' => 'note', 'body' => "通話のメモ：\n" . $notes, 'via' => 'staff']);
        }

        return ['ended' => true];
    }
}
