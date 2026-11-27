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

    /**
     * The servers of the setting that are not this site (the notice of an outside server): what a
     * browser reaches outside when a call starts, as host, port and protocol ("UDP", "TCP"),
     * one each. A TURN without ?transport= is UDP (the browsers' first try), stuns / turns TCP (TLS).
     * A server on the site's own host name is left out.
     *
     * @param array<int, array<string, string>> $ice iceServers()
     * @param array<int, string> $siteHosts the host names of this site
     * @return array<int, array{scheme: string, host: string, port: int, proto: string}>
     */
    public static function outsideServers(array $ice, array $siteHosts): array
    {
        $site = array_filter(array_map(fn ($h) => strtolower(trim(strval($h), '[] ')), $siteHosts));
        $out = [];
        foreach ($ice as $s) {
            if (!preg_match('#^(stuns?|turns?):(\[[0-9A-Fa-f:.]+\]|[A-Za-z0-9.\-]+)(?::(\d+))?(?:\?transport=(udp|tcp))?$#', strval($s['urls'] ?? ''), $m)) {
                continue;
            }
            $scheme = $m[1];
            $host = strtolower(trim($m[2], '[]'));
            if ($host === '' || in_array($host, $site, true)) {
                continue;
            }
            $tls = str_ends_with($scheme, 's');
            $port = isset($m[3]) && $m[3] !== '' ? intval($m[3]) : ($tls ? 5349 : 3478);
            $proto = $tls || (($m[4] ?? '') === 'tcp') ? 'TCP' : 'UDP';
            $key = "$host:$port/$proto";
            if (!isset($out[$key])) {
                $out[$key] = ['scheme' => rtrim($scheme, 's'), 'host' => str_contains($host, ':') ? "[$host]" : $host, 'port' => $port, 'proto' => $proto];
            }
        }

        return array_values($out);
    }

    /**
     * The host names of this site: the request's, and the one of APP_URL.
     *
     * @return array<int, string>
     */
    public static function siteHosts(): array
    {
        $hosts = [];
        try {
            $hosts[] = strval(request()->getHost());
        } catch (\Throwable $e) {
            // no request (a batch)
        }
        $app = parse_url(strval(config('app.url')), PHP_URL_HOST);
        if (is_string($app) && $app !== '') {
            $hosts[] = $app;
        }

        return array_values(array_unique(array_filter($hosts)));
    }

    /**
     * What the screens say of the outside servers of a setting: null when there are none.
     *
     * @return array{hosts: array<int, string>, allow: array<int, string>, turn: bool}|null
     */
    public static function outsideSummary($text, ?array $siteHosts = null): ?array
    {
        $out = static::outsideServers(static::iceServers($text), $siteHosts ?? static::siteHosts());
        if (count($out) === 0) {
            return null;
        }

        return [
            'hosts' => array_values(array_unique(array_column($out, 'host'))),
            'allow' => array_map(fn ($s) => $s['host'] . ':' . $s['port'] . ' (' . $s['proto'] . ')', $out),
            'turn' => in_array('turn', array_column($out, 'scheme'), true),
        ];
    }

    /**
     * A text of "call_outside" in the language of the user, read from the plugin's lang file
     * (English when the language has none; the key when English has none either). Read here,
     * not through trans(): the plugin's settings form may be drawn before Exment adds the
     * plugin's lang files.
     *
     * @param array<string, string> $args
     */
    public static function outsideText(string $key, array $args = [], ?string $locale = null): string
    {
        static $cache = [];
        if ($locale === null) {
            try {
                $locale = strval(app()->getLocale());
            } catch (\Throwable $e) {
                $locale = 'en';
            }
        }
        if (!preg_match('/^[A-Za-z_]{2,10}$/', $locale)) {
            $locale = 'en';
        }
        if (!isset($cache[$locale])) {
            $file = __DIR__ . '/resources/lang/' . $locale . '/its.php';
            $lines = is_file($file) ? require $file : [];
            $cache[$locale] = is_array($lines['call_outside'] ?? null) ? $lines['call_outside'] : [];
        }
        $text = $cache[$locale][$key] ?? null;
        if (!is_string($text)) {
            return $locale === 'en' ? $key : static::outsideText($key, $args, 'en');
        }
        foreach ($args as $name => $value) {
            $text = str_replace(':' . $name, $value, $text);
        }

        return $text;
    }

    /**
     * The notice under the setting of the servers (HTML), or '' when none is outside the site.
     *
     * @param array{hosts: array<int, string>, allow: array<int, string>, turn: bool}|null $summary outsideSummary()
     */
    public static function outsideNoticeHtml(?array $summary, string $class, ?string $locale = null): string
    {
        if ($summary === null) {
            return '';
        }
        $h = fn ($s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        $t = fn ($k, $a = []) => $h(static::outsideText($k, $a, $locale));
        $allow = '';
        foreach ($summary['allow'] as $a) {
            $allow .= '<li><code>' . $h($a) . '</code></li>';
        }

        return '<div class="' . $h($class) . '" role="note" data-call-outside="1">'
            . '<div><strong>' . $t('title') . '</strong>'
            . '<p style="margin:4px 0">' . $t('lead', ['hosts' => implode(', ', $summary['hosts'])]) . ' ' . $t('ip')
            . ($summary['turn'] ? ' ' . $t('turn') : '') . '</p>'
            . '<p style="margin:4px 0">' . $t('firewall') . '</p>'
            . '<ul style="margin:0 0 4px 18px;padding:0">' . $allow . '</ul>'
            . '<p style="margin:4px 0 0">' . $t('avoid') . '</p></div></div>';
    }

    /**
     * What a call screen needs for its note of the outside servers, or null when there are none.
     *
     * @return array{hosts: array<int, string>, text: string, more: string, close: string, manual: string}|null
     */
    public static function outsideNote($text, string $manualUrl, ?array $siteHosts = null, ?string $locale = null): ?array
    {
        $summary = static::outsideSummary($text, $siteHosts);
        if ($summary === null) {
            return null;
        }

        return [
            'hosts' => $summary['hosts'],
            'text' => static::outsideText('note', ['hosts' => implode(', ', $summary['hosts'])], $locale),
            'more' => static::outsideText('more', [], $locale),
            'close' => static::outsideText('close', [], $locale),
            'manual' => $manualUrl,
        ];
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
