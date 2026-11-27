<?php

namespace App\Plugins\ExmentGroupware\Calendar;

use App\Plugins\ExmentGroupware\FeatureGate;
use App\Plugins\ExmentGroupware\Mail\HostGuard;
use App\Plugins\ExmentGroupware\Mail\MailException;
use Exceedone\Exment\Enums\SystemTableName;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * A person's outside calendars (ISS02947, part 5): an ICS address, read (pull only) into their
 * calendar as events with source=link, from 30 days back to 180 days ahead. An event removed
 * there is removed here. The others see them as the link says: 「予定あり」 (busy, the default) or
 * as an event open to all (details). Nobody can change them here.
 *
 * Read every 15 minutes (a little later for each link, so they do not all go at once) by the
 * plugin's batch and after the answer of a calendar page (a few links at a time, after the page
 * is sent: never in its way). A failure is kept as a code (last_error) and the next try waits
 * longer.
 *
 * The address is encrypted (APP_KEY) and never given back: the page shows its host only.
 *
 * Part 6: a Google or Microsoft calendar (provider google | microsoft), signed in with OAuth
 * (CloudOAuth) and read by CloudSync at the same times, with the same limits. Its tokens are
 * encrypted in secret_enc and never given back; the page shows the account's email only.
 */
class Links
{
    const TABLE = 'gw_cal_links';
    const MAX_PER_USER = 10;
    const EVERY_MINUTES = 15;
    const BACK_DAYS = 30;
    const AHEAD_DAYS = 180;
    const SHOW_AS = ['busy', 'details'];
    /** The providers signed in with OAuth (part 6). */
    const CLOUD = ['google', 'microsoft'];
    /** Days a Google or Microsoft link may stay not allowed before its tokens are forgotten. */
    const NOT_ALLOWED_DAYS = 30;
    /** Links read after one page's answer, and by one run of the batch. */
    const TICK_LINKS = 3;
    const BATCH_LINKS = 30;
    const TICK_SECONDS = 25;
    /** The time a calendar's text may take to be read, at most (after its fetch). */
    const PARSE_SECONDS = 10;

    public static function ready(): bool
    {
        try {
            return Events::ready() && \Schema::hasTable(static::TABLE);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** @var bool|null whether the columns of part 6 are there (account, sync_state, full_sync_at) */
    protected static $cloudReady = null;

    /** Whether Google and Microsoft calendars can be kept (the migration of part 6 has run). */
    public static function cloudReady(): bool
    {
        if (static::$cloudReady === null) {
            try {
                static::$cloudReady = static::ready() && \Schema::hasColumn(static::TABLE, 'sync_state');
            } catch (\Throwable $e) {
                static::$cloudReady = false;
            }
        }
        return static::$cloudReady;
    }

    public static function isCloud($provider): bool
    {
        return in_array(strval($provider), static::CLOUD, true);
    }

    // ---- the page --------------------------------------------------------------------

    /**
     * A person's links as the page shows them (no address).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function list(int $uid): array
    {
        $out = [];
        foreach (DB::table(static::TABLE)->where('user_id', $uid)->orderBy('id')->get() as $l) {
            $out[] = static::shown($l);
        }
        return $out;
    }

    /** @return array<string, mixed> */
    public static function shown(object $l): array
    {
        return [
            'id' => intval($l->id),
            'provider' => strval($l->provider),
            'name' => strval($l->name),
            'host' => strval($l->url_host ?? ''),
            'show_as' => strval($l->show_as),
            'enabled' => boolval($l->enabled),
            'last_sync_at' => $l->last_sync_at !== null ? strval($l->last_sync_at) : null,
            'last_error' => $l->last_error !== null ? strval($l->last_error) : null,
            'events' => intval($l->event_count),
            // Google and Microsoft (part 6): the account's email only, and whether to sign in again
            'account' => static::isCloud($l->provider ?? null) ? strval($l->account ?? '') : null,
            'reconnect' => static::isCloud($l->provider ?? null) && (empty($l->secret_enc) || strval($l->last_error ?? '') === 'auth'),
        ];
    }

    /**
     * The fields of a link from the form, checked (the address against HostGuard now, so a
     * wrong one is said at once). $new: the address is required; else empty keeps it.
     *
     * @param array<string, mixed> $in
     * @return array{0: array<string, mixed>|null, 1: string|null} the fields, or the rule broken
     */
    public static function fields(array $in, bool $new, ?callable $resolve = null): array
    {
        $name = trim(mb_substr(strval($in['name'] ?? ''), 0, 100));
        if ($name === '') {
            return [null, 'link_name'];
        }
        $showAs = strval($in['show_as'] ?? 'busy');
        if (!in_array($showAs, static::SHOW_AS, true)) {
            return [null, 'link_show_as'];
        }
        $f = ['name' => $name, 'show_as' => $showAs, 'enabled' => !array_key_exists('enabled', $in) || !in_array(strval($in['enabled']), ['0', 'false', ''], true)];
        $url = trim(strval($in['url'] ?? ''));
        if ($url === '' && $new) {
            return [null, 'link_url'];
        }
        if ($url !== '') {
            try {
                $g = HostGuard::checkWeb($url, $resolve);
            } catch (MailException $e) {
                return [null, 'link_' . $e->getMessage()];
            }
            $f['url'] = $g['url'];
            $f['url_host'] = $g['host'];
        }
        return [$f, null];
    }

    /** @return string|null the rule broken, or null */
    public static function create(int $uid, array $in): ?string
    {
        if (DB::table(static::TABLE)->where('user_id', $uid)->count() >= static::MAX_PER_USER) {
            return 'link_limit';
        }
        [$f, $why] = static::fields($in, true);
        if ($f === null) {
            return $why;
        }
        $id = intval(DB::table(static::TABLE)->insertGetId([
            'user_id' => $uid, 'provider' => 'ics', 'direction' => 'read',
            'name' => $f['name'], 'show_as' => $f['show_as'], 'enabled' => $f['enabled'] ? 1 : 0,
            'url_enc' => Crypt::encryptString($f['url']), 'url_host' => $f['url_host'],
            'next_sync_at' => static::utcNow(), 'created_at' => now(), 'updated_at' => now(),
        ]));
        Log::info('gw_calendar: link ' . $id . ' added by user ' . $uid);
        return null;
    }

    /** @return string|null the rule broken, or null */
    public static function update(int $uid, int $id, array $in): ?string
    {
        $l = DB::table(static::TABLE)->where('id', $id)->where('user_id', $uid)->first();
        if (!isset($l)) {
            return 'link_not_found';
        }
        $cloud = static::isCloud($l->provider ?? null);
        if ($cloud && !static::cloudReady()) {
            // the columns of part 6 not there (its migration not run)
            return 'not_ready';
        }
        if ($cloud) {
            // a Google or Microsoft calendar has no address to change
            unset($in['url']);
        }
        [$f, $why] = static::fields($in, false);
        if ($f === null) {
            return $why;
        }
        $v = ['name' => $f['name'], 'show_as' => $f['show_as'], 'enabled' => $f['enabled'] ? 1 : 0, 'updated_at' => now()];
        if ($cloud && ($f['show_as'] !== strval($l->show_as) || !$f['enabled'])) {
            // read everything again: an event private there stays 「予定あり」 to the others
            $v += ['sync_state' => null, 'full_sync_at' => null, 'next_sync_at' => static::utcNow()];
        }
        if (isset($f['url'])) {
            // another address: read again from nothing
            $v += ['url_enc' => Crypt::encryptString($f['url']), 'url_host' => $f['url_host'], 'etag' => null, 'last_modified' => null, 'last_error' => null, 'fails' => 0, 'next_sync_at' => static::utcNow()];
        }
        if (!$f['enabled']) {
            // off: its events go (on again, they are read again)
            static::removeEvents($id);
            $v += ['etag' => null, 'last_modified' => null, 'event_count' => 0];
        } elseif (!boolval($l->enabled)) {
            $v += ['next_sync_at' => static::utcNow(), 'fails' => 0];
        }
        DB::transaction(function () use ($id, $v, $f, $l) {
            DB::table(static::TABLE)->where('id', $id)->update($v);
            if ($f['show_as'] !== strval($l->show_as)) {
                // a Google or Microsoft calendar: 「予定あり」 until it is read again (then each event as it is there)
                $vis = static::isCloud($l->provider ?? null) ? 'busy' : static::visibility($f['show_as']);
                DB::table(Events::EVENTS)->where('source', 'link')->where('link_id', $id)->update(['visibility' => $vis]);
            }
        });
        return null;
    }

    /** @param CloudOAuth|null $oauth to revoke a Google calendar's token (part 6); null: only forget it */
    public static function delete(int $uid, int $id, ?CloudOAuth $oauth = null): bool
    {
        $l = DB::table(static::TABLE)->where('id', $id)->where('user_id', $uid)->first();
        if (!isset($l)) {
            return false;
        }
        if ($oauth !== null && static::isCloud($l->provider ?? null)) {
            $oauth->revoke($l);
        }
        DB::transaction(function () use ($id) {
            static::removeEvents($id);
            DB::table(static::TABLE)->where('id', $id)->delete();
        });
        Log::info('gw_calendar: link ' . $id . ' removed by user ' . $uid);
        return true;
    }

    /**
     * A Google or Microsoft calendar signed in (part 6): a new link, or the tokens of the link
     * signed in again ($linkId, the person's own of that provider). Another account signed in
     * again: that link's events go, and it is read from nothing.
     *
     * @param array<string, mixed> $secret the tokens (CloudOAuth::finish)
     * @return array{0: int|null, 1: string|null} the link, or the rule broken
     */
    public static function connect(int $uid, string $provider, ?int $linkId, array $secret, string $account, string $name): array
    {
        if (!static::isCloud($provider) || !static::cloudReady()) {
            return [null, 'not_ready'];
        }
        $enc = CloudOAuth::encryptSecret($secret);
        if ($linkId !== null && $linkId > 0) {
            $l = DB::table(static::TABLE)->where('id', $linkId)->where('user_id', $uid)->where('provider', $provider)->first();
            if (!isset($l)) {
                return [null, 'link_not_found'];
            }
            // always read in full again: what the new sign-in sees replaces everything (even when its account could not be read)
            $v = ['secret_enc' => $enc, 'last_error' => null, 'fails' => 0, 'next_sync_at' => static::utcNow(), 'updated_at' => now(), 'sync_state' => null, 'full_sync_at' => null];
            if ($account !== '' && $account !== strval($l->account ?? '')) {
                static::removeEvents($linkId);
                $v += ['account' => $account, 'event_count' => 0];
            }
            DB::table(static::TABLE)->where('id', $linkId)->update($v);
            Log::info('gw_calendar: link ' . $linkId . ' signed in again by user ' . $uid);
            return [$linkId, null];
        }
        if (DB::table(static::TABLE)->where('user_id', $uid)->count() >= static::MAX_PER_USER) {
            return [null, 'link_limit'];
        }
        $id = intval(DB::table(static::TABLE)->insertGetId([
            'user_id' => $uid, 'provider' => $provider, 'direction' => 'read',
            'name' => mb_substr($name, 0, 100), 'show_as' => 'busy', 'enabled' => 1,
            // no address (the provider's API, CloudSync); the column is required
            'url_enc' => Crypt::encryptString($provider . ':primary'), 'url_host' => null,
            'account' => $account !== '' ? $account : null, 'secret_enc' => $enc,
            'last_sync_at' => null, 'last_error' => null, 'fails' => 0, 'event_count' => 0,
            'next_sync_at' => static::utcNow(), 'created_at' => now(), 'updated_at' => now(),
        ]));
        Log::info('gw_calendar: link ' . $id . ' (' . $provider . ') added by user ' . $uid);
        return [$id, null];
    }

    protected static function removeEvents(int $linkId): void
    {
        $ids = DB::table(Events::EVENTS)->where('source', 'link')->where('link_id', $linkId)->pluck('id')->all();
        foreach (array_chunk($ids, 500) as $chunk) {
            DB::table(Events::OVERRIDES)->whereIn('event_id', $chunk)->delete();
            DB::table(Events::EVENTS)->whereIn('id', $chunk)->delete();
        }
    }

    /** The visibility of a link's events: busy for 「予定あり」, all for details. */
    public static function visibility(string $showAs): string
    {
        return $showAs === 'details' ? 'all' : 'busy';
    }

    // ---- reading a calendar -------------------------------------------------------------

    /**
     * The rows of a calendar's events, and what changes against the rows there are (no database).
     *
     * @param array<string, array<string, mixed>> $events Ics::events()['events']
     * @param array<string, array{id: int, etag: string}> $existing by external_id
     * @return array{insert: array<string, array<string, mixed>>, update: array<int, array<string, mixed>>, delete: array<int, int>, same: int}
     */
    public static function plan(array $events, array $existing, string $showAs): array
    {
        $plan = ['insert' => [], 'update' => [], 'delete' => [], 'same' => 0];
        foreach ($events as $uid => $e) {
            $row = static::row($e, $showAs);
            $uid = strval($uid);
            if (isset($existing[$uid])) {
                if ($existing[$uid]['etag'] === $row['external_etag']) {
                    $plan['same']++;
                } else {
                    $plan['update'][intval($existing[$uid]['id'])] = $row;
                }
            } else {
                $plan['insert'][$uid] = $row;
            }
        }
        foreach ($existing as $uid => $x) {
            if (!isset($events[$uid])) {
                $plan['delete'][] = intval($x['id']);
            }
        }
        return $plan;
    }

    /**
     * An event of an outside calendar as a row of gw_cal_events (and its overrides under
     * "overrides"); external_etag says whether it changed.
     *
     * @param array<string, mixed> $e
     * @return array<string, mixed>
     */
    public static function row(array $e, string $showAs): array
    {
        $rrule = !empty($e['rrule']) ? strval($e['rrule']) : null;
        $row = [
            'title' => mb_substr(strval($e['title'] ?? ''), 0, 200),
            'location' => strval($e['location'] ?? '') !== '' ? mb_substr(strval($e['location']), 0, 200) : null,
            'body' => strval($e['body'] ?? '') !== '' ? mb_substr(strval($e['body']), 0, 20000) : null,
            'starts_at' => strval($e['starts_at']),
            'ends_at' => strval($e['ends_at']),
            'all_day' => !empty($e['all_day']) ? 1 : 0,
            'tz' => strval($e['tz'] ?? 'Asia/Tokyo'),
            'rrule' => $rrule,
            'repeat_until' => $rrule !== null ? Events::repeatUntil($rrule, strval($e['starts_at']), strval($e['tz'] ?? 'Asia/Tokyo'), !empty($e['all_day'])) : null,
            'exdates' => !empty($e['exdates']) ? json_encode(array_values($e['exdates'])) : null,
            'rdates' => !empty($e['rdates']) ? json_encode(array_values($e['rdates'])) : null,
            'visibility' => static::visibility($showAs),
            'org_id' => null,
            'overrides' => array_values((array) ($e['overrides'] ?? [])),
        ];
        $sig = $row;
        unset($sig['visibility']);
        $row['external_etag'] = sha1(strval(json_encode($sig)));
        return $row;
    }

    /** Now in UTC, as the times of a link are kept (last_sync_at, next_sync_at), like the events'. */
    public static function utcNow(): string
    {
        return now()->setTimezone('UTC')->format('Y-m-d H:i:s');
    }

    /** The window of a read, UTC. @return array{0: string, 1: string} */
    public static function window(?string $nowUtc = null): array
    {
        $now = new \DateTimeImmutable($nowUtc ?? 'now', new \DateTimeZone('UTC'));
        return [$now->modify('-' . static::BACK_DAYS . ' day')->format('Y-m-d H:i:s'), $now->modify('+' . static::AHEAD_DAYS . ' day')->format('Y-m-d H:i:s')];
    }

    /** When to read a link next: every 15 minutes, a little later for each link; longer after failures. */
    public static function nextAt(int $id, int $fails, ?string $nowUtc = null): string
    {
        $now = new \DateTimeImmutable($nowUtc ?? 'now', new \DateTimeZone('UTC'));
        $minutes = static::EVERY_MINUTES * min(16, 2 ** min(4, max(0, $fails)));
        $stagger = ($id * 97) % 180;
        return $now->modify('+' . ($minutes * 60 + $stagger) . ' second')->format('Y-m-d H:i:s');
    }

    /**
     * Read one link now. Its row says how it went (last_sync_at, last_error, the next time).
     *
     * @return string|null the error code, or null
     */
    public static function sync(object $link, ?IcsFetch $fetch = null, ?string $nowUtc = null, ?CloudSync $cloud = null): ?string
    {
        $now = $nowUtc ?? static::utcNow();
        if (static::isCloud($link->provider ?? null)) {
            // Google or Microsoft (part 6)
            return ($cloud ?? new CloudSync())->sync($link, $now);
        }
        $fetch = $fetch ?? new IcsFetch();
        $id = intval($link->id);
        $error = null;
        $set = [];
        try {
            try {
                $url = Crypt::decryptString(strval($link->url_enc));
            } catch (\Throwable $e) {
                // APP_KEY changed: the address is to be given again
                throw new IcsFetchError('decrypt');
            }
            $res = $fetch->get($url, $link->etag ?? null, $link->last_modified ?? null);
            if ($res['status'] === 200) {
                $tz = strval(config('app.timezone') ?: 'Asia/Tokyo');
                [$from, $to] = static::window($now);
                // a calendar made to be slow stops here, and nothing of it is changed
                $parsed = Ics::events($res['body'], $tz, $from, $to, microtime(true) + static::PARSE_SECONDS);
                if ($parsed['timed_out']) {
                    throw new IcsFetchError('too_slow');
                }
                $n = static::apply($link, $parsed['events']);
                $set = ['etag' => $res['etag'], 'last_modified' => $res['last_modified'], 'event_count' => $n];
                if ($parsed['too_many']) {
                    $error = 'too_many';
                }
            }
        } catch (IcsFetchError $e) {
            $error = $e->getMessage();
        } catch (\Throwable $e) {
            Log::warning('gw_calendar link ' . $id . ': ' . get_class($e));
            $error = 'failed';
        }
        $fails = $error === null || $error === 'too_many' ? 0 : intval($link->fails ?? 0) + 1;
        DB::table(static::TABLE)->where('id', $id)->update($set + [
            'last_sync_at' => $now, 'last_error' => $error, 'fails' => $fails,
            'next_sync_at' => static::nextAt($id, $fails, $now), 'updated_at' => now(),
        ]);
        return $error;
    }

    /**
     * A calendar's events into the link's rows: new ones added, changed ones changed, the
     * ones no longer there removed.
     *
     * @param array<string, array<string, mixed>> $events
     * @return int the events of the link
     */
    public static function apply(object $link, array $events): int
    {
        $linkId = intval($link->id);
        $uid = intval($link->user_id);
        $existing = [];
        // the same event twice (two reads of the link at once): the extra rows go
        $extra = [];
        foreach (DB::table(Events::EVENTS)->where('source', 'link')->where('link_id', $linkId)->get(['id', 'external_id', 'external_etag']) as $r) {
            if (isset($existing[strval($r->external_id)])) {
                $extra[] = intval($r->id);
                continue;
            }
            $existing[strval($r->external_id)] = ['id' => intval($r->id), 'etag' => strval($r->external_etag ?? '')];
        }
        $plan = static::plan($events, $existing, strval($link->show_as));
        DB::transaction(function () use ($plan, $extra, $linkId, $uid) {
            $now = now();
            foreach (array_chunk($extra, 500) as $chunk) {
                DB::table(Events::OVERRIDES)->whereIn('event_id', $chunk)->delete();
                DB::table(Events::EVENTS)->whereIn('id', $chunk)->delete();
            }
            foreach ($plan['insert'] as $ext => $row) {
                $ov = $row['overrides'];
                unset($row['overrides']);
                $id = intval(DB::table(Events::EVENTS)->insertGetId($row + [
                    'owner_user_id' => $uid, 'source' => 'link', 'link_id' => $linkId, 'external_id' => mb_substr(strval($ext), 0, 255),
                    'created_user_id' => $uid, 'updated_user_id' => $uid, 'created_at' => $now, 'updated_at' => $now,
                ]));
                static::overrides($id, $ov);
            }
            foreach ($plan['update'] as $id => $row) {
                $ov = $row['overrides'];
                unset($row['overrides']);
                DB::table(Events::EVENTS)->where('id', $id)->update($row + ['updated_at' => $now]);
                DB::table(Events::OVERRIDES)->where('event_id', $id)->delete();
                static::overrides($id, $ov);
            }
            foreach (array_chunk($plan['delete'], 500) as $chunk) {
                DB::table(Events::OVERRIDES)->whereIn('event_id', $chunk)->delete();
                DB::table(Events::EVENTS)->whereIn('id', $chunk)->delete();
            }
        });
        return count($events);
    }

    /** @param array<int, array<string, mixed>> $list */
    protected static function overrides(int $eventId, array $list): void
    {
        $seen = [];
        foreach ($list as $o) {
            $k = strval($o['original_start']);
            if (isset($seen[$k])) {
                continue;
            }
            $seen[$k] = true;
            DB::table(Events::OVERRIDES)->insert([
                'event_id' => $eventId, 'original_start' => $k, 'cancelled' => !empty($o['cancelled']) ? 1 : 0,
                'starts_at' => $o['starts_at'] ?? null, 'ends_at' => $o['ends_at'] ?? null,
                'title' => isset($o['title']) ? mb_substr(strval($o['title']), 0, 200) : null,
                'location' => isset($o['location']) ? mb_substr(strval($o['location']), 0, 200) : null,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    // ---- when they are read ------------------------------------------------------------

    /**
     * The links whose time has come, read (each taken first, so a batch and a page do not
     * read the same one at once).
     *
     * @return array{read: int, failed: int}
     */
    public static function run(int $limit, ?IcsFetch $fetch = null, int $seconds = 0, ?CloudSync $cloud = null): array
    {
        $out = ['read' => 0, 'failed' => 0];
        if (!static::ready()) {
            return $out;
        }
        $started = microtime(true);
        $now = static::utcNow();
        $allowed = [];
        $due = DB::table(static::TABLE)->where('enabled', 1)->whereIn('provider', static::cloudReady() ? array_merge(['ics'], static::CLOUD) : ['ics'])
            ->where(function ($q) use ($now) {
                $q->whereNull('next_sync_at')->orWhere('next_sync_at', '<=', $now);
            })->orderBy('next_sync_at')->limit($limit)->get();
        foreach ($due as $l) {
            if ($seconds > 0 && microtime(true) - $started > $seconds) {
                break;
            }
            if (!static::take($l, $now)) {
                continue;
            }
            // a person who is gone, or no longer allowed the feature: not read (kept, tried again in a day)
            $uid = intval($l->user_id);
            if (!array_key_exists($uid, $allowed)) {
                $allowed[$uid] = static::userActive($uid) && FeatureGate::allowedFor(FeatureGate::CALENDAR_LINKS, $uid);
            }
            if (!$allowed[$uid]) {
                DB::table(static::TABLE)->where('id', $l->id)->update([
                    'last_error' => 'not_allowed',
                    'next_sync_at' => (new \DateTimeImmutable($now, new \DateTimeZone('UTC')))->modify('+1 day')->format('Y-m-d H:i:s'),
                ]);
                static::forgetTokensOf($l, $now);
                $out['failed']++;
                continue;
            }
            if (static::isCloud($l->provider ?? null)) {
                $cloud = $cloud ?? new CloudSync();
            }
            $error = static::sync($l, $fetch, null, $cloud);
            $out[$error === null ? 'read' : 'failed']++;
        }
        return $out;
    }

    /**
     * Take a link for one read (its next time moved 5 minutes on, if it is still what was read):
     * a batch, a page tick and 「今すぐ読む」 never read the same link at once.
     */
    public static function take(object $l, string $nowUtc): bool
    {
        return DB::table(static::TABLE)->where('id', $l->id)->where(function ($q) use ($l) {
            if ($l->next_sync_at === null) {
                $q->whereNull('next_sync_at');
            } else {
                $q->where('next_sync_at', $l->next_sync_at);
            }
        })->update(['next_sync_at' => (new \DateTimeImmutable($nowUtc, new \DateTimeZone('UTC')))->modify('+5 minute')->format('Y-m-d H:i:s')]) > 0;
    }

    /**
     * The tokens of a Google or Microsoft link not allowed for NOT_ALLOWED_DAYS (no read since):
     * forgotten. The link and its events stay; the person signs in again when allowed again.
     */
    protected static function forgetTokensOf(object $l, string $nowUtc): void
    {
        if (!static::isCloud($l->provider ?? null) || empty($l->secret_enc) || strval($l->last_error ?? '') !== 'not_allowed') {
            return;
        }
        $since = strval($l->last_sync_at ?? '') ?: strval($l->created_at ?? '');
        $limit = (new \DateTimeImmutable($nowUtc, new \DateTimeZone('UTC')))->modify('-' . static::NOT_ALLOWED_DAYS . ' day')->format('Y-m-d H:i:s');
        if ($since === '' || strcmp($since, $limit) < 0) {
            DB::table(static::TABLE)->where('id', $l->id)->update(['secret_enc' => null, 'sync_state' => null, 'full_sync_at' => null]);
            Log::info('gw_calendar: the tokens of link ' . intval($l->id) . ' forgotten (not allowed for ' . static::NOT_ALLOWED_DAYS . ' days)');
        }
    }

    /** Whether an Exment user is there (not deleted). False when it cannot be told. */
    public static function userActive(int $uid): bool
    {
        if ($uid <= 0) {
            return false;
        }
        try {
            // without the global scopes (no login in a batch), the deleted ones left out by hand
            return getModelName(SystemTableName::USER)::withoutGlobalScopes()->whereNull('deleted_at')->where('id', $uid)->exists();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** After the answer of a calendar page: a few links whose time has come, at most once a minute. */
    public static function afterResponse(bool $allowPrivate): void
    {
        app()->terminating(function () use ($allowPrivate) {
            try {
                if (static::ready() && FeatureGate::allowedOnSite(FeatureGate::CALENDAR_LINKS)
                    && Cache::add('gw_cal_links_tick:' . DB::connection()->getDatabaseName(), 1, now()->addMinutes(1))) {
                    HostGuard::allowPrivateWeb($allowPrivate);
                    static::run(static::TICK_LINKS, null, static::TICK_SECONDS);
                }
            } catch (\Throwable $e) {
                Log::warning('gw_calendar links: ' . get_class($e));
            }
        });
    }
}
