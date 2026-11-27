<?php

namespace App\Plugins\ExmentSupportDesk;

use Exceedone\Exment\Model\CustomValue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

/**
 * The chat window of a 受付箱 on a website (ISS02941): a customer without a login talks to the
 * help desk; the talk is a ticket of the box (channel chat). See docs/guest-chat-design.md.
 *
 * - The box key (in the script's address) is not a secret: it only lets a chat start.
 * - A started chat has a guest token (only its sha256 kept, sd_tokens kind chat), sent back in
 *   the X-Chat-Token header: it reads and writes that one ticket's talk, nothing else.
 * - Everything a guest writes is text (the window and the desk draw it as text).
 */
class GuestChat
{
    const KIND = 'chat';
    const TOKEN_HOURS = 24;
    const MAX_BODY = 2000;
    const STARTS_PER_HOUR = 5;
    /** All the starts of one box an hour, when the box does not say (a burst from anywhere stops here). */
    const BOX_STARTS_PER_HOUR = 60;
    const LINES_PER_10MIN = 30;
    const OFFLINE_MINUTES = 10;

    public static function enabled(): bool
    {
        return Feature::on(Feature::WEB_CHAT);
    }

    const PUBLIC_FORM = '\\Exceedone\\Exment\\Model\\PublicForm';
    const RECAPTCHA = '\\Exceedone\\Exment\\Services\\ReCaptchaService';

    /**
     * The robot check of the first message, as the site's public forms have it (reCAPTCHA v2 or
     * v3, Exment's setting): ['version' => 'v2'|'v3', 'sitekey' => ...] or null when not set.
     */
    public static function robot(): ?array
    {
        $f = static::PUBLIC_FORM;
        try {
            if (!class_exists($f) || !class_exists(static::RECAPTCHA) || $f::isEnableRecaptcha() !== true) {
                return null;
            }
            $version = strval($f::recaptchaVersion());
            $key = strval($f::recaptchaSiteKey());
            if ($key === '' || !in_array($version, ['v2', 'v3'], true)) {
                return null;
            }

            // v3: the action Exment's verify expects (ReCaptchaService::ACTION, "publicform")
            $service = static::RECAPTCHA;
            $action = defined($service . '::ACTION') ? strval(constant($service . '::ACTION')) : 'publicform';

            return ['version' => $version, 'sitekey' => $key, 'action' => $action];
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** A new box key: 24 random hex characters. */
    public static function newKey(): string
    {
        return bin2hex(random_bytes(12));
    }

    public static function validKey($key): bool
    {
        return is_string($key) && preg_match('/^[0-9a-f]{24}$/', $key) === 1;
    }

    /**
     * The frame-ancestors of a box's window: its allowed sites (one origin per line, https or
     * http with a host), or any site when it has none.
     */
    public static function frameAncestors($sites): string
    {
        $out = [];
        foreach (preg_split('/\r\n|\r|\n|\s+/', strval($sites ?? '')) ?: [] as $s) {
            $s = trim($s);
            if (preg_match('#^https?://[A-Za-z0-9.\-]+(:\d{1,5})?$#', $s)) {
                $out[] = $s;
            }
            if (count($out) >= 20) {
                break;
            }
        }

        return $out === [] ? '*' : implode(' ', array_unique($out));
    }

    /** A guest's text: one paragraph block, no control characters but line breaks, at most 2,000. */
    public static function clean($text, int $max = self::MAX_BODY): string
    {
        $s = str_replace(["\r\n", "\r"], "\n", strval($text ?? ''));
        $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s) ?? '';

        return mb_substr(trim($s), 0, $max);
    }

    /** Whether an agent was on the desk lately (the desk marks it, Desk::apiList). */
    public static function online(?int $lastSeen, int $now): bool
    {
        return $lastSeen !== null && ($now - $lastSeen) < static::OFFLINE_MINUTES * 60;
    }

    public static function markAgentSeen(): void
    {
        try {
            Cache::put('sd_agent_seen', time(), 3600);
            $uid = intval(\Exment::getUserId());
            if ($uid > 0) {
                Cache::put('sd_agent_seen:' . $uid, time(), 3600);
            }
        } catch (\Throwable $e) {
            // the window then says nobody is there
        }
    }

    /**
     * Whether someone is there for a box: its first assignee when it has one (a box of its own
     * people), else any agent.
     */
    public static function onlineFor($box): bool
    {
        $assignee = intval(array_get(isset($box) ? (array) $box->value : [], 'default_assignee'));
        try {
            $seen = $assignee > 0 ? Cache::get('sd_agent_seen:' . $assignee) : Cache::get('sd_agent_seen');
        } catch (\Throwable $e) {
            $seen = null;
        }

        return static::online($seen !== null ? intval($seen) : null, time());
    }

    public static function agentSeen(): ?int
    {
        try {
            $v = Cache::get('sd_agent_seen');

            return $v !== null ? intval($v) : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** The box of a key, when its chat window is used; else null. */
    public static function box(string $key): ?CustomValue
    {
        if (!static::validKey($key) || ($t = Its::table(Its::T_CATEGORY)) === null) {
            return null;
        }

        return $t->getValueModel()->query()->withoutGlobalScope(\Exceedone\Exment\Model\CustomValueModelScope::class)
            ->where('value->chat_key', $key)->first();
    }

    /** A box's key, made the first time (the 受付箱 page). */
    public static function keyOf(CustomValue $box): string
    {
        $key = strval(array_get($box->value, 'chat_key'));
        if (!static::validKey($key)) {
            $key = static::newKey();
            $box->setValue(['chat_key' => $key]);
            $box->save();
        }

        return $key;
    }

    /**
     * Whether a request comes from the window itself: the iframe is on Exment's own address, so
     * its fetch carries Exment's origin. A form or no-cors POST from another site carries that
     * site's: refused (a simple request has no preflight to stop it).
     */
    public static function fromWindow(?string $origin, ?string $referer, ?string $fetchSite, array $owns): bool
    {
        $owns = array_map(function ($o) {
            return rtrim(strtolower($o), '/');
        }, $owns);
        if ($fetchSite !== null && $fetchSite !== '' && $fetchSite !== 'same-origin') {
            return false;
        }
        if ($origin !== null && $origin !== '') {
            return in_array(rtrim(strtolower($origin), '/'), $owns, true);
        }
        if ($referer !== null && $referer !== '') {
            $p = parse_url($referer);
            $ref = isset($p['scheme'], $p['host']) ? strtolower($p['scheme'] . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '')) : '';

            return in_array($ref, $owns, true);
        }

        return false;
    }

    /** Exment's own origins: as the request sees it, and as APP_URL says (behind a proxy they differ). */
    public static function ownOrigins(): array
    {
        $out = [];
        foreach ([url('/'), strval(config('app.url', ''))] as $u) {
            $p = parse_url($u);
            if (isset($p['scheme'], $p['host'])) {
                $out[] = strtolower($p['scheme'] . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : ''));
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * The agents' replies shown in a window (mail_status "window") that the window did not read
     * (it was not used after the reply came): mailed now, as a reply is (the batch, every 5 min).
     */
    public static function mailUnread(): int
    {
        $table = Its::table(Its::T_MESSAGE);
        if (!isset($table)) {
            return 0;
        }
        $n = 0;
        $rows = $table->getValueModel()->query()->withoutGlobalScope(\Exceedone\Exment\Model\CustomValueModelScope::class)
            ->where('value->kind', 'reply')->where('value->mail_status', 'window')
            ->where('created_at', '<', now()->subMinutes(2))->orderBy('id')->limit(200)->get();
        foreach ($rows as $message) {
            $ticket = Tickets::find($message->parent_id);
            if (!isset($ticket)) {
                continue;
            }
            $seen = DB::table('sd_tokens')->where('kind', static::KIND)->where('ticket_id', $ticket->id)
                ->where('last_used_at', '>=', $message->created_at)->exists();
            if ($seen) {
                $message->setValue(['mail_status' => 'shown']);
                $message->save();
                continue;
            }
            $to = strval(array_get($ticket->value, 'requester_email'));
            if (!Its::isEmail($to)) {
                $message->setValue(['mail_status' => 'none']);
                $message->save();
                continue;
            }
            $link = boolval(array_get($ticket->value, 'customer_visible', '1')) && Its::portalOn() ? Tokens::linkFor(intval($ticket->id), $to) : null;
            $cc = array_filter(array_map('trim', explode(',', strval(array_get($ticket->value, 'cc', '')))));
            $id = Mailer::send($ticket, [$to], Mailer::subjectFor($ticket), Mailer::customerBody($ticket, strval(array_get($message->value, 'body')), $link), $cc);
            $message->setValue(['mail_status' => $id !== null ? 'sent' : 'failed', 'message_id' => $id]);
            $message->save();
            $n++;
        }

        return $n;
    }

    /**
     * The limits of a box: starts per IP an hour, all starts an hour (the box's settings, or 5 and 60).
     *
     * @return array{0: int, 1: int}
     */
    public static function limitsOf($value): array
    {
        $v = (array) $value;
        $ip = intval($v['chat_per_ip'] ?? 0);
        $box = intval($v['chat_per_hour'] ?? 0);

        return [$ip >= 1 && $ip <= 1000 ? $ip : static::STARTS_PER_HOUR, $box >= 1 && $box <= 100000 ? $box : static::BOX_STARTS_PER_HOUR];
    }

    /** Too many tries for a key (IP or token) in a while? */
    protected static function limited(string $name, int $max, int $seconds): bool
    {
        if (RateLimiter::tooManyAttempts($name, $max)) {
            return true;
        }
        RateLimiter::hit($name, $seconds);

        return false;
    }

    /**
     * Start a chat: a ticket of the box, its first line, and the guest token.
     *
     * @return array{token: string, ticket: string, online: bool}
     */
    public static function start(string $key, string $name, string $email, string $body, string $ip, ?string $robot = null): array
    {
        $box = static::box($key);
        if (!isset($box)) {
            abort(404);
        }
        // the client's IP as Laravel resolves it with the site's trusted proxies (never a raw
        // X-Forwarded-For), and a ceiling for the box whatever the IPs
        [$perIp, $perBox] = static::limitsOf($box->value);
        // the IP first, every try counted (one person's tries are theirs); the box only counts the
        // chats that start, so someone refused cannot use up the box for everyone
        if (static::limited('sd_chat_start:' . sha1($ip), $perIp, 3600)) {
            abort(429);
        }
        if (RateLimiter::tooManyAttempts('sd_chat_box:' . $box->id, $perBox)) {
            abort(429);
        }
        // the site's reCAPTCHA, when it has one
        $check = static::robot();
        if ($check !== null) {
            $service = static::RECAPTCHA;
            if (!$service::verify($robot !== '' ? $robot : null, $ip, $check['version'])) {
                throw new \InvalidArgumentException('ロボットでないことを確かめてください。');
            }
        }
        if (mb_strlen(trim($body)) > static::MAX_BODY) {
            throw new \InvalidArgumentException(static::MAX_BODY . ' 文字までです。');
        }
        $name = static::clean($name, 100);
        $email = Its::normalizeEmail($email);
        $body = static::clean($body);
        if ($name === '' || !Its::isEmail($email) || $body === '') {
            throw new \InvalidArgumentException('名前・メールアドレス・お問い合わせを入れてください。');
        }
        $ticket = Tickets::create([
            'subject' => mb_substr(strval(array_get($box->value, 'name')) . '：' . preg_replace('/\s+/u', ' ', $body), 0, 120),
            'description' => $body,
            'category' => strval($box->id),
            'channel' => 'chat',
            'requester_name' => $name,
            'requester_email' => $email,
        ], ['body' => $body]);
        RateLimiter::hit('sd_chat_box:' . $box->id, 3600);
        $token = bin2hex(random_bytes(32));
        DB::table('sd_tokens')->insert([
            'kind' => static::KIND,
            'token_hash' => Tokens::hash($token),
            'ticket_id' => $ticket->id,
            'email' => $email,
            'expires_at' => now()->addHours(static::TOKEN_HOURS),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['token' => $token, 'ticket' => strval(array_get($ticket->value, 'ticket_no')), 'online' => static::onlineFor($box)];
    }

    /** The ticket of a guest token, still good (its use moves the expiry on); else 404. */
    public static function ticketOf(string $token): CustomValue
    {
        if (!preg_match('/^[0-9a-f]{64}$/', $token)) {
            abort(404);
        }
        $row = DB::table('sd_tokens')->where('kind', static::KIND)->where('token_hash', Tokens::hash($token))
            ->whereNull('revoked_at')->where('expires_at', '>', now())->first();
        $ticket = isset($row) ? Tickets::find($row->ticket_id) : null;
        if (!isset($ticket)) {
            abort(404);
        }
        DB::table('sd_tokens')->where('id', $row->id)->update([
            'last_used_at' => now(), 'use_count' => DB::raw('use_count + 1'), 'expires_at' => now()->addHours(static::TOKEN_HOURS),
        ]);

        return $ticket;
    }

    /** The talk the guest sees: their lines and the agents' replies (no notes, no system lines). */
    public static function lines(string $token, int $after): array
    {
        $ticket = static::ticketOf($token);
        $out = [];
        foreach (Tickets::messages($ticket, true) as $m) {
            if (intval($m->id) <= $after) {
                continue;
            }
            $out[] = [
                'id' => intval($m->id),
                'me' => array_get($m->value, 'kind') === 'customer',
                'name' => array_get($m->value, 'kind') === 'customer' ? '' : strval(array_get($m->value, 'author_name') ?: '担当者'),
                'text' => strval(array_get($m->value, 'body')),
                'at' => strval($m->created_at),
            ];
        }

        $box = Its::table(Its::T_CATEGORY) !== null && !empty(array_get($ticket->value, 'category'))
            ? Its::table(Its::T_CATEGORY)->getValueModel()->query()->withoutGlobalScope(\Exceedone\Exment\Model\CustomValueModelScope::class)->find(array_get($ticket->value, 'category')) : null;

        return ['lines' => $out, 'online' => static::onlineFor($box)];
    }

    public static function post(string $token, string $body): array
    {
        $ticket = static::ticketOf($token);
        if (static::limited('sd_chat_line:' . Tokens::hash($token), static::LINES_PER_10MIN, 600)) {
            abort(429);
        }
        if (mb_strlen(trim(strval($body))) > static::MAX_BODY) {
            throw new \InvalidArgumentException(static::MAX_BODY . ' 文字までです。');
        }
        $body = static::clean($body);
        if ($body === '') {
            throw new \InvalidArgumentException('書くことを入れてください。');
        }
        Tickets::customerWrote($ticket, $body, 'chat', strval(array_get($ticket->value, 'requester_email')), strval(array_get($ticket->value, 'requester_name')));

        return ['ok' => true];
    }

    /** Whether the guest's window is open now (used in the last 2 minutes): an agent's reply then needs no mail. */
    public static function windowOpen(int $ticketId): bool
    {
        return DB::table('sd_tokens')->where('kind', static::KIND)->where('ticket_id', $ticketId)
            ->where('last_used_at', '>', now()->subMinutes(2))->exists();
    }
}
