<?php

namespace App\Plugins\ExmentGroupware\Calendar;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The ICS export address of a person's calendar (ISS02947, part 5): a random token in the
 * address, shown once when it is made; only its hash (sha256) is kept. Made again, the old
 * address stops; stopped, it answers 404 as an unknown one does.
 *
 * What it gives: the person's events and the ones they attend, from 30 days back to 365 days
 * ahead, each occurrence a VEVENT (repeats expanded, times in UTC), as somebody else who is not
 * logged in would see them (Visibility, with no viewer): an event open to all with its title
 * and place; any other (organization, attendees only, busy only, an outside calendar shown
 * busy) as a block of time 「予定あり」 only. No notes and no attendees, in any of them.
 */
class Feeds
{
    const TABLE = 'gw_cal_feeds';
    const BACK_DAYS = 30;
    const AHEAD_DAYS = 365;

    public static function ready(): bool
    {
        try {
            return Events::ready() && \Schema::hasTable(static::TABLE);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function newToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    public static function validToken(string $token): bool
    {
        return preg_match('/^[0-9a-f]{64}$/', $token) === 1;
    }

    /** A new address for the person (the old one stops): its token, to be shown once. */
    public static function create(int $uid): string
    {
        $token = static::newToken();
        DB::transaction(function () use ($uid, $token) {
            DB::table(static::TABLE)->where('user_id', $uid)->whereNull('revoked_at')->update(['revoked_at' => Links::utcNow(), 'updated_at' => now()]);
            DB::table(static::TABLE)->insert(['user_id' => $uid, 'token_hash' => static::hash($token), 'last_used_at' => null, 'revoked_at' => null, 'created_at' => now(), 'updated_at' => now()]);
        });
        Log::info('gw_calendar: ICS export address made by user ' . $uid);
        return $token;
    }

    public static function revoke(int $uid): int
    {
        $n = DB::table(static::TABLE)->where('user_id', $uid)->whereNull('revoked_at')->update(['revoked_at' => Links::utcNow(), 'updated_at' => now()]);
        Log::info('gw_calendar: ICS export address stopped by user ' . $uid);
        return $n;
    }

    /** @return array{created_at: string, last_used_at: ?string}|null the person's address now */
    public static function state(int $uid): ?array
    {
        $f = DB::table(static::TABLE)->where('user_id', $uid)->whereNull('revoked_at')->orderByDesc('id')->first();
        if (!isset($f)) {
            return null;
        }
        // created_at is Laravel's, in the site's time zone; the page gets UTC as for the rest
        try {
            $made = (new \DateTimeImmutable(strval($f->created_at), new \DateTimeZone(strval(config('app.timezone') ?: 'UTC'))))->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            $made = strval($f->created_at);
        }
        return ['created_at' => $made, 'last_used_at' => ($f->last_used_at ?? null) !== null ? strval($f->last_used_at) : null];
    }

    /** @return object|null the feed of a token still in use */
    public static function find(string $token): ?object
    {
        if (!static::validToken($token)) {
            return null;
        }
        $f = DB::table(static::TABLE)->where('token_hash', static::hash($token))->whereNull('revoked_at')->first();
        if (!isset($f)) {
            return null;
        }
        // when it was last read, at most every 10 minutes
        if (($f->last_used_at ?? null) === null || strcmp(strval($f->last_used_at), gmdate('Y-m-d H:i:s', strtotime(Links::utcNow() . ' UTC') - 600)) < 0) {
            DB::table(static::TABLE)->where('id', $f->id)->update(['last_used_at' => Links::utcNow()]);
        }
        return $f;
    }

    /** The ICS of the feed's person. */
    public static function render(object $feed, string $busyLabel, string $name, string $host, ?string $nowUtc = null): string
    {
        $now = new \DateTimeImmutable($nowUtc ?? 'now', new \DateTimeZone('UTC'));
        $from = $now->modify('-' . static::BACK_DAYS . ' day')->format('Y-m-d 00:00:00');
        $to = $now->modify('+' . static::AHEAD_DAYS . ' day')->format('Y-m-d 00:00:00');
        // viewer 0: nobody logged in, so the owner's rights never apply
        $items = Events::window($from, $to, [intval($feed->user_id)], 0, $busyLabel);
        return Ics::build($items, ['name' => $name, 'host' => $host, 'busy' => $busyLabel, 'now' => $now->format('Y-m-d H:i:s'), 'salt' => 'feed' . intval($feed->id)]);
    }
}
