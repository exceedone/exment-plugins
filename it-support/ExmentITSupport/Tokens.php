<?php

namespace App\Plugins\ExmentITSupport;

use Carbon\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Secret links for people who do not log in.
 *
 * The rules from ISS00210: no password is ever mailed; a link opens one
 * ticket (or one address's list) and nothing else; only a hash is stored;
 * links expire, can be revoked, and every use is logged; an important ticket
 * can ask for a one-time code sent to the registered address, so a forwarded
 * link is not enough to open it.
 */
class Tokens
{
    const KIND_TICKET = 'ticket';
    const KIND_MINE = 'mine';

    /** Days a "my requests" link lives. */
    const MINE_DAYS = 7;

    const OTP_MINUTES = 10;
    const OTP_ATTEMPTS = 5;
    /** Codes sent for one link in an hour. */
    const OTP_SENDS_PER_HOUR = 5;
    /** Wrong codes for one link in a day, over all its codes: sending a new code does not start again. */
    const OTP_FAILS_PER_DAY = 10;

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * A new token for a ticket. Earlier links for the same ticket keep
     * working until revoked, because the customer may still have the first
     * mail open.
     */
    public static function forTicket(int $ticketId, ?string $email = null): string
    {
        $token = static::random();
        DB::table('its_tokens')->insert([
            'kind' => static::KIND_TICKET,
            'token_hash' => static::hash($token),
            'token_enc' => Crypt::encryptString($token),
            'ticket_id' => $ticketId,
            'email' => $email,
            'expires_at' => null, // set when the ticket closes; see expireClosed()
            'created_user_id' => optional(\Exment::user())->getUserId(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $token;
    }

    /**
     * The link to put in a mail about this ticket: the live one already
     * given to this address if there is one, else a new one.
     */
    public static function linkFor(int $ticketId, ?string $email): string
    {
        $email = $email ? Its::normalizeEmail($email) : null;
        $rows = DB::table('its_tokens')->where('ticket_id', $ticketId)->where('kind', static::KIND_TICKET)
            ->whereNull('revoked_at')->whereNotNull('token_enc')
            ->where(function ($q) use ($email) {
                $email === null ? $q->whereNull('email') : $q->where('email', $email);
            })
            ->orderByDesc('id')->get();
        foreach ($rows as $row) {
            if (!empty($row->expires_at) && Carbon::parse($row->expires_at)->isPast()) {
                continue;
            }
            try {
                $token = Crypt::decryptString($row->token_enc);

                return Its::publicUrl('t', $token);
            } catch (\Throwable $e) {
                continue; // the app key changed
            }
        }

        return Its::publicUrl('t', static::forTicket($ticketId, $email));
    }

    public static function forMine(string $email): string
    {
        $token = static::random();
        DB::table('its_tokens')->insert([
            'kind' => static::KIND_MINE,
            'token_hash' => static::hash($token),
            'email' => Its::normalizeEmail($email),
            'expires_at' => Carbon::now()->addDays(static::MINE_DAYS),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $token;
    }

    /**
     * The live token row, or null. Expired, revoked and unknown look the
     * same to the caller, so a guess learns nothing.
     */
    public static function find(string $token, string $kind): ?object
    {
        if (!preg_match('/^[A-Za-z0-9_-]{32,128}$/', $token)) {
            return null;
        }
        $row = DB::table('its_tokens')->where('token_hash', static::hash($token))->where('kind', $kind)->first();
        if (!isset($row) || !empty($row->revoked_at)) {
            return null;
        }
        if (!empty($row->expires_at) && Carbon::parse($row->expires_at)->isPast()) {
            return null;
        }

        return $row;
    }

    public static function touch(object $row, string $action): void
    {
        DB::table('its_tokens')->where('id', $row->id)->update([
            'last_used_at' => now(),
            'use_count' => DB::raw('use_count + 1'),
        ]);
        static::log($row->id, $row->ticket_id ?? null, $action);
    }

    public static function log(?int $tokenId, ?int $ticketId, string $action): void
    {
        $request = request();
        DB::table('its_access_logs')->insert([
            'token_id' => $tokenId,
            'ticket_id' => $ticketId,
            'action' => mb_substr($action, 0, 32),
            'ip' => $request ? mb_substr(strval($request->ip()), 0, 64) : null,
            'user_agent' => $request ? mb_substr(strval($request->userAgent()), 0, 255) : null,
            'created_at' => now(),
        ]);
    }

    public static function revokeTicket(int $ticketId): int
    {
        return DB::table('its_tokens')->where('ticket_id', $ticketId)->where('kind', static::KIND_TICKET)
            ->whereNull('revoked_at')->update(['revoked_at' => now(), 'updated_at' => now()]);
    }

    /**
     * Give the links of a closed ticket an end date. Called when a ticket is
     * closed; reopening clears it again.
     */
    public static function expireClosed(int $ticketId, bool $closed): void
    {
        $days = max(1, intval(Its::setting('link_days_after_close', 30)));
        DB::table('its_tokens')->where('ticket_id', $ticketId)->where('kind', static::KIND_TICKET)->whereNull('revoked_at')
            ->update(['expires_at' => $closed ? Carbon::now()->addDays($days) : null, 'updated_at' => now()]);
    }

    /**
     * @return array<int, object>
     */
    public static function listForTicket(int $ticketId): array
    {
        return DB::table('its_tokens')->where('ticket_id', $ticketId)->orderByDesc('id')->limit(20)->get()->all();
    }

    /**
     * @return array<int, object>
     */
    public static function accessLog(int $ticketId, int $limit = 20): array
    {
        return DB::table('its_access_logs')->where('ticket_id', $ticketId)->orderByDesc('id')->limit($limit)->get()->all();
    }

    // ---- one-time codes --------------------------------------------------

    /** A 6-digit code for the token; the previous code stops working. */
    public static function newOtp(object $token): string
    {
        $code = str_pad(strval(random_int(0, 999999)), 6, '0', STR_PAD_LEFT);
        DB::table('its_otps')->where('token_id', $token->id)->whereNull('used_at')->update(['used_at' => now()]);
        DB::table('its_otps')->insert([
            'token_id' => $token->id,
            'code_hash' => static::hash($token->id . ':' . $code),
            'expires_at' => Carbon::now()->addMinutes(static::OTP_MINUTES),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $code;
    }

    /** True while this link may be sent another code (a few an hour). */
    public static function canSendOtp(object $token): bool
    {
        if (static::otpLocked($token)) {
            return false;
        }

        return DB::table('its_otps')->where('token_id', $token->id)
            ->where('created_at', '>', Carbon::now()->subHour())->count() < static::OTP_SENDS_PER_HOUR;
    }

    /**
     * True when too many wrong codes were tried for this link today. Counted
     * over all the link's codes, so asking for a new code does not give a
     * fresh set of tries.
     */
    public static function otpLocked(object $token): bool
    {
        // "attempts" holds the wrong tries only (a right try is taken back when the code is used)
        $fails = intval(DB::table('its_otps')->where('token_id', $token->id)
            ->where('created_at', '>', Carbon::now()->subDay())->sum('attempts'));

        return $fails >= static::OTP_FAILS_PER_DAY;
    }

    /**
     * Run $fn with the link's row locked, so requests for the same link at
     * the same moment take turns (the per-link limits are read, then written).
     *
     * @template T
     * @param callable(): T $fn
     * @return T
     */
    public static function withLinkLock(object $token, callable $fn)
    {
        return DB::transaction(function () use ($token, $fn) {
            DB::table('its_tokens')->where('id', $token->id)->lockForUpdate()->first();

            return $fn();
        });
    }

    public static function checkOtp(object $token, string $code): bool
    {
        return static::withLinkLock($token, function () use ($token, $code) {
            return static::checkOtpLocked($token, $code);
        });
    }

    protected static function checkOtpLocked(object $token, string $code): bool
    {
        $code = preg_replace('/\D/', '', $code);
        if (static::otpLocked($token)) {
            return false;
        }
        $row = DB::table('its_otps')->where('token_id', $token->id)->whereNull('used_at')->orderByDesc('id')->first();
        if (!isset($row) || Carbon::parse($row->expires_at)->isPast()) {
            return false;
        }
        // Count the try before comparing, in one statement: requests sent at
        // the same moment cannot all get the same "attempts so far".
        $counted = DB::table('its_otps')->where('id', $row->id)->whereNull('used_at')
            ->where('attempts', '<', static::OTP_ATTEMPTS)->increment('attempts');
        if ($counted !== 1) {
            return false;
        }
        if (!hash_equals($row->code_hash, static::hash($token->id . ':' . $code))) {
            return false;
        }

        // only one request can use the code; the right try is not a wrong one
        return DB::table('its_otps')->where('id', $row->id)->whereNull('used_at')
            ->update(['used_at' => now(), 'attempts' => DB::raw('attempts - 1')]) === 1;
    }

    // ---- throttling ------------------------------------------------------

    /**
     * True when this address may go on. Counted per IP and action, so
     * guessing tokens or flooding the request form runs into a wall.
     */
    public static function allow(string $action, int $perMinute): bool
    {
        $key = 'its:' . $action . ':' . sha1(strval(optional(request())->ip()));
        if (RateLimiter::tooManyAttempts($key, $perMinute)) {
            return false;
        }
        RateLimiter::hit($key, 60);

        return true;
    }

    protected static function random(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }
}
