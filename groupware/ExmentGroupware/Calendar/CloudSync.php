<?php

namespace App\Plugins\ExmentGroupware\Calendar;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * A Google or Microsoft calendar read into a person's calendar (ISS02947, part 6): read only,
 * the person's main calendar, from 30 days back to 180 days ahead, like an ICS link (events with
 * source=link, changed in place, removed when removed there, at most Ics::MAX_EVENTS).
 *
 * Repeats: each occurrence as the provider works it out, one event here each (Google
 * singleEvents=true, Microsoft calendarView). No RRULE is read: a moved or cancelled occurrence,
 * a series cut short, the provider's own time zones and exceptions are all as the provider shows
 * them, and the calendar here has nothing to work out.
 *
 *   Google     events.list of the calendar "primary": the first read (full) with timeMin and
 *              timeMax, then only the changes with its nextSyncToken. 410 Gone (the token too
 *              old): a full read again.
 *   Microsoft  /me/calendarView/delta of the window: the pages (@odata.nextLink) up to an
 *              @odata.deltaLink, then only the changes with it. 410 Gone or a sync state the
 *              service no longer has: a full read again.
 *
 * A change only reads the window it was first read with, so a full read again every day
 * (FULL_HOURS) moves the window on and removes what it no longer has. The cursor (sync token or
 * deltaLink) is kept only after its last page, with the events, so a read stopped halfway
 * changes nothing and the next one starts again from the cursor before.
 */
class CloudSync
{
    /** Pages of one read at most, and the time of one read (its requests each within IcsFetch::TIMEOUT). */
    const MAX_PAGES = 60;
    const SECONDS = 30;
    const FULL_HOURS = 24;
    const GOOGLE_EVENTS = 'https://www.googleapis.com/calendar/v3/calendars/primary/events';
    const GOOGLE_CALENDAR = 'https://www.googleapis.com/calendar/v3/calendars/primary';
    const MS_DELTA = 'https://graph.microsoft.com/v1.0/me/calendarView/delta';
    const MS_CALENDAR = 'https://graph.microsoft.com/v1.0/me/calendar';
    const MS_PREFER = 'odata.maxpagesize=100, outlook.timezone="UTC", outlook.body-content-type="text"';

    /** @var CloudOAuth */
    protected $oauth;

    public function __construct(?CloudOAuth $oauth = null)
    {
        $this->oauth = $oauth ?? new CloudOAuth();
    }

    public function oauth(): CloudOAuth
    {
        return $this->oauth;
    }

    /**
     * A GET of the API with the link's access token; a 401 renews the token once.
     *
     * @param array<string, string> $headers
     * @return array{status: int, headers: array<string, string>, body: string, json: array<string, mixed>|null}
     */
    protected function get(object $link, string $url, array $headers = []): array
    {
        $fetch = $this->oauth->fetch();
        $res = $fetch->request('GET', $url, ['Authorization' => 'Bearer ' . $this->oauth->accessToken($link)] + $headers);
        if ($res['status'] === 401) {
            $res = $fetch->request('GET', $url, ['Authorization' => 'Bearer ' . $this->oauth->accessToken($link, true)] + $headers);
            if ($res['status'] === 401) {
                throw new IcsFetchError('auth');
            }
        }
        return $res;
    }

    /** The account the link reads, as an email address only ('' when it is not one). */
    public function account(object $link): string
    {
        $this->oauth->fetch()->within(IcsFetch::TIMEOUT);
        if (strval($link->provider) === CloudOAuth::MICROSOFT) {
            $res = $this->get($link, static::MS_CALENDAR);
            $mail = strval($res['json']['owner']['address'] ?? '');
        } else {
            $res = $this->get($link, static::GOOGLE_CALENDAR);
            $mail = strval($res['json']['id'] ?? '');
        }
        if ($res['status'] !== 200) {
            throw new IcsFetchError('http_' . $res['status']);
        }
        $mail = mb_strtolower(trim($mail));
        return filter_var($mail, FILTER_VALIDATE_EMAIL) !== false && strlen($mail) <= 255 ? $mail : '';
    }

    /**
     * Read one link now; its row says how it went, as an ICS link's (Links::sync).
     *
     * @return string|null the error code, or null
     */
    public function sync(object $link, string $nowUtc): ?string
    {
        $id = intval($link->id);
        $error = null;
        $set = [];
        try {
            $this->oauth->fetch()->within(static::SECONDS);
            [$from, $to] = Links::window($nowUtc);
            $cursor = strval($link->sync_state ?? '');
            $full = $cursor === '' || empty($link->full_sync_at)
                || strcmp(strval($link->full_sync_at), (new \DateTimeImmutable($nowUtc, new \DateTimeZone('UTC')))->modify('-' . static::FULL_HOURS . ' hour')->format('Y-m-d H:i:s')) < 0;
            $r = strval($link->provider) === CloudOAuth::MICROSOFT
                ? $this->readMicrosoft($link, $full, $cursor, $from, $to)
                : $this->readGoogle($link, $full, $cursor, $from, $to);
            $tooMany = false;
            $n = static::apply($link, $r['items'], $r['full'], $from, $to, $tooMany);
            $set = ['sync_state' => $r['cursor'] !== '' ? $r['cursor'] : null, 'event_count' => $n];
            if ($r['full']) {
                $set['full_sync_at'] = $nowUtc;
            }
            if ($tooMany) {
                $error = 'too_many';
            }
        } catch (IcsFetchError $e) {
            $error = $e->getMessage();
        } catch (\Throwable $e) {
            Log::warning('gw_calendar link ' . $id . ': ' . get_class($e));
            $error = 'failed';
        }
        $fails = $error === null || $error === 'too_many' ? 0 : intval($link->fails ?? 0) + 1;
        DB::table(Links::TABLE)->where('id', $id)->update($set + [
            'last_sync_at' => $nowUtc, 'last_error' => $error, 'fails' => $fails,
            'next_sync_at' => Links::nextAt($id, $fails, $nowUtc), 'updated_at' => now(),
        ]);
        return $error;
    }

    /** 'Y-m-d H:i:s' UTC as RFC 3339. */
    protected static function rfc(string $utc): string
    {
        return str_replace(' ', 'T', $utc) . 'Z';
    }

    /**
     * @return array{full: bool, items: array<string, array<string, mixed>|null>, cursor: string}
     *         items: the external id => the event, or null for one removed there
     */
    public function readGoogle(object $link, bool $full, string $cursor, string $from, string $to): array
    {
        $params = ['singleEvents' => 'true', 'maxResults' => '250'];
        if ($full) {
            $params += ['timeMin' => static::rfc($from), 'timeMax' => static::rfc($to), 'showDeleted' => 'false'];
        } else {
            $params['syncToken'] = $cursor;
        }
        $tz = static::siteTz();
        $items = [];
        $page = null;
        for ($pages = 1; ; $pages++) {
            if ($pages > static::MAX_PAGES) {
                throw new IcsFetchError('too_slow');
            }
            $res = $this->get($link, static::GOOGLE_EVENTS . '?' . http_build_query($params + ($page !== null ? ['pageToken' => $page] : []), '', '&', PHP_QUERY_RFC3986));
            if ($res['status'] === 410 && !$full) {
                // the sync token too old: everything again
                return $this->readGoogle($link, true, '', $from, $to);
            }
            if ($res['status'] !== 200 || !is_array($res['json'])) {
                throw new IcsFetchError('http_' . ($res['status'] === 200 ? 0 : $res['status']));
            }
            foreach ((array) ($res['json']['items'] ?? []) as $it) {
                if (!is_array($it) || strval($it['id'] ?? '') === '') {
                    continue;
                }
                $ext = mb_substr(strval($it['id']), 0, 255);
                $items[$ext] = strval($it['status'] ?? '') === 'cancelled' ? null : static::googleEvent($it, $tz);
            }
            $next = strval($res['json']['nextPageToken'] ?? '');
            if ($next !== '') {
                $page = $next;
                continue;
            }
            return ['full' => $full, 'items' => $items, 'cursor' => strval($res['json']['nextSyncToken'] ?? '')];
        }
    }

    /** @return array{full: bool, items: array<string, array<string, mixed>|null>, cursor: string} */
    public function readMicrosoft(object $link, bool $full, string $cursor, string $from, string $to): array
    {
        if (!$full && !CloudFetch::allowedUrl($cursor)) {
            $full = true;
        }
        $url = $full ? static::MS_DELTA . '?' . http_build_query(['startDateTime' => static::rfc($from), 'endDateTime' => static::rfc($to)], '', '&', PHP_QUERY_RFC3986) : $cursor;
        $items = [];
        for ($pages = 1; ; $pages++) {
            if ($pages > static::MAX_PAGES) {
                throw new IcsFetchError('too_slow');
            }
            $res = $this->get($link, $url, ['Prefer' => static::MS_PREFER]);
            $code = strtolower(strval($res['json']['error']['code'] ?? ''));
            if (!$full && ($res['status'] === 410 || in_array($code, ['syncstatenotfound', 'resyncrequired', 'syncstateinvalid'], true))) {
                // the deltaLink too old: everything again
                return $this->readMicrosoft($link, true, '', $from, $to);
            }
            if ($res['status'] !== 200 || !is_array($res['json'])) {
                throw new IcsFetchError('http_' . ($res['status'] === 200 ? 0 : $res['status']));
            }
            foreach ((array) ($res['json']['value'] ?? []) as $it) {
                if (!is_array($it) || strval($it['id'] ?? '') === '') {
                    continue;
                }
                $ext = mb_substr(strval($it['id']), 0, 255);
                if (isset($it['@removed']) || !empty($it['isCancelled'])) {
                    $items[$ext] = null;
                    continue;
                }
                if (strval($it['type'] ?? '') === 'seriesMaster' || !isset($it['start'])) {
                    // a series itself (its occurrences come one by one), or a change without its times: nothing to do
                    continue;
                }
                $items[$ext] = static::microsoftEvent($it);
            }
            $next = strval($res['json']['@odata.nextLink'] ?? '');
            if ($next !== '') {
                if (!CloudFetch::allowedUrl($next)) {
                    throw new IcsFetchError('host');
                }
                $url = $next;
                continue;
            }
            return ['full' => $full, 'items' => $items, 'cursor' => strval($res['json']['@odata.deltaLink'] ?? '')];
        }
    }

    protected static function siteTz(): string
    {
        $tz = strval(config('app.timezone') ?: 'Asia/Tokyo');
        return static::validTz($tz) ?? 'Asia/Tokyo';
    }

    protected static function validTz(?string $tz): ?string
    {
        if ($tz === null || $tz === '') {
            return null;
        }
        try {
            return (new \DateTimeZone($tz))->getName();
        } catch (\Throwable $e) {
            $win = Ics::WINDOWS_TZ[$tz] ?? null;
            return $win !== null ? strval($win) : null;
        }
    }

    /** Text from what may be HTML (Google's description): the lines kept, no markup, as text. */
    public static function plain(string $s): string
    {
        if ($s === '' || strpos($s, '<') === false) {
            return $s;
        }
        $s = preg_replace('/<\s*(br|\/p|\/div|\/li)\b[^>]*>/i', "\n", $s) ?? $s;
        return trim(html_entity_decode(strip_tags($s), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    /**
     * An event of Google (events.list, singleEvents) as an event of a link (as Ics::events
     * gives them), or null when it has no time.
     *
     * @param array<string, mixed> $it
     * @return array<string, mixed>|null
     */
    public static function googleEvent(array $it, string $siteTz): ?array
    {
        $s = (array) ($it['start'] ?? []);
        $e = (array) ($it['end'] ?? []);
        $utc = new \DateTimeZone('UTC');
        try {
            if (!empty($s['date'])) {
                $allDay = true;
                $start = (new \DateTimeImmutable(substr(strval($s['date']), 0, 10), $utc))->format('Y-m-d') . ' 00:00:00';
                $end = !empty($e['date']) ? (new \DateTimeImmutable(substr(strval($e['date']), 0, 10), $utc))->format('Y-m-d') . ' 00:00:00' : '';
                if ($end === '' || strcmp($end, $start) <= 0) {
                    $end = (new \DateTimeImmutable($start, $utc))->modify('+1 day')->format('Y-m-d H:i:s');
                }
            } elseif (!empty($s['dateTime'])) {
                $allDay = false;
                $start = (new \DateTimeImmutable(strval($s['dateTime'])))->setTimezone($utc)->format('Y-m-d H:i:s');
                $end = !empty($e['dateTime']) ? (new \DateTimeImmutable(strval($e['dateTime'])))->setTimezone($utc)->format('Y-m-d H:i:s') : $start;
                if (strcmp($end, $start) < 0) {
                    $end = $start;
                }
            } else {
                return null;
            }
        } catch (\Throwable $ex) {
            return null;
        }
        return [
            'title' => mb_substr(strval($it['summary'] ?? ''), 0, 200),
            'location' => mb_substr(strval($it['location'] ?? ''), 0, 200),
            'body' => mb_substr(static::plain(strval($it['description'] ?? '')), 0, 20000),
            'starts_at' => $start,
            'ends_at' => $end,
            'all_day' => $allDay,
            'tz' => $allDay ? $siteTz : (static::validTz(isset($s['timeZone']) ? strval($s['timeZone']) : null) ?? $siteTz),
            'rrule' => null, 'exdates' => [], 'rdates' => [], 'overrides' => [],
            'private' => in_array(strval($it['visibility'] ?? ''), ['private', 'confidential'], true),
        ];
    }

    /**
     * An event of Microsoft Graph (calendarView, times in UTC as asked) as an event of a link.
     *
     * @param array<string, mixed> $it
     * @return array<string, mixed>|null
     */
    public static function microsoftEvent(array $it): ?array
    {
        $utc = new \DateTimeZone('UTC');
        $when = function ($w) use ($utc): ?string {
            $w = (array) $w;
            $v = substr(strval($w['dateTime'] ?? ''), 0, 19);
            if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/', $v)) {
                return null;
            }
            $zone = static::validTz(isset($w['timeZone']) ? strval($w['timeZone']) : 'UTC') ?? 'UTC';
            try {
                return (new \DateTimeImmutable($v, new \DateTimeZone($zone)))->setTimezone($utc)->format('Y-m-d H:i:s');
            } catch (\Throwable $e) {
                return null;
            }
        };
        $allDay = !empty($it['isAllDay']);
        if ($allDay) {
            // an all-day event: its dates as they are (midnight to midnight of its own days)
            $sd = substr(strval(((array) ($it['start'] ?? []))['dateTime'] ?? ''), 0, 10);
            $ed = substr(strval(((array) ($it['end'] ?? []))['dateTime'] ?? ''), 0, 10);
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $sd)) {
                return null;
            }
            $start = $sd . ' 00:00:00';
            $end = preg_match('/^\d{4}-\d{2}-\d{2}$/', $ed) ? $ed . ' 00:00:00' : '';
            if ($end === '' || strcmp($end, $start) <= 0) {
                $end = (new \DateTimeImmutable($start, $utc))->modify('+1 day')->format('Y-m-d H:i:s');
            }
        } else {
            $start = $when($it['start'] ?? []);
            if ($start === null) {
                return null;
            }
            $end = $when($it['end'] ?? []) ?? $start;
            if (strcmp($end, $start) < 0) {
                $end = $start;
            }
        }
        $body = (array) ($it['body'] ?? []);
        $text = strval($body['content'] ?? '');
        if (strtolower(strval($body['contentType'] ?? 'text')) === 'html') {
            $text = static::plain($text);
        }
        if ($text === '') {
            $text = strval($it['bodyPreview'] ?? '');
        }
        return [
            'title' => mb_substr(strval($it['subject'] ?? ''), 0, 200),
            'location' => mb_substr(strval(((array) ($it['location'] ?? []))['displayName'] ?? ''), 0, 200),
            'body' => mb_substr(trim($text), 0, 20000),
            'starts_at' => $start,
            'ends_at' => $end,
            'all_day' => $allDay,
            'tz' => $allDay ? static::siteTz() : (static::validTz(isset($it['originalStartTimeZone']) ? strval($it['originalStartTimeZone']) : null) ?? static::siteTz()),
            'rrule' => null, 'exdates' => [], 'rdates' => [], 'overrides' => [],
            'private' => in_array(strtolower(strval($it['sensitivity'] ?? '')), ['private', 'confidential'], true),
        ];
    }

    /**
     * The row of an event of a link: as an ICS link's (Links::row), but an event private there is
     * always 「予定あり」 to the others, and its visibility is part of what says it changed.
     *
     * @param array<string, mixed> $e
     * @return array<string, mixed>
     */
    public static function row(array $e, string $showAs): array
    {
        $row = Links::row($e, $showAs);
        unset($row['overrides']);
        $row['visibility'] = !empty($e['private']) ? 'busy' : Links::visibility($showAs);
        $row['external_etag'] = sha1($row['external_etag'] . '|' . $row['visibility']);
        return $row;
    }

    /**
     * What was read into the link's rows. A full read: the rows of the window, the others
     * removed. Changes: only those (null, or out of the window: removed). At most
     * Ics::MAX_EVENTS events a link ($tooMany when some were left out).
     *
     * @param array<string, array<string, mixed>|null> $items
     * @return int the events of the link
     */
    public static function apply(object $link, array $items, bool $full, string $from, string $to, bool &$tooMany = false): int
    {
        $linkId = intval($link->id);
        $uid = intval($link->user_id);
        $showAs = strval($link->show_as);
        $google = strval($link->provider) === CloudOAuth::GOOGLE;
        $existing = [];
        foreach (DB::table(Events::EVENTS)->where('source', 'link')->where('link_id', $linkId)->get(['id', 'external_id', 'external_etag']) as $r) {
            $existing[strval($r->external_id)] = ['id' => intval($r->id), 'etag' => strval($r->external_etag ?? '')];
        }
        $upsert = [];
        $gone = [];
        foreach ($items as $ext => $e) {
            $ext = strval($ext);
            if ($e === null) {
                $gone[] = $ext;
                continue;
            }
            $row = static::row($e, $showAs);
            $in = strcmp($row['starts_at'], $to) < 0
                && (strcmp($row['ends_at'], $from) > 0 || ($row['ends_at'] === $row['starts_at'] && strcmp($row['starts_at'], $from) >= 0));
            if (!$in) {
                // moved out of the window
                $gone[] = $ext;
                continue;
            }
            $upsert[$ext] = $row;
        }
        $delete = [];
        if ($full) {
            foreach ($existing as $ext => $x) {
                if (!isset($upsert[$ext])) {
                    $delete[$ext] = $x['id'];
                }
            }
        } else {
            foreach ($gone as $ext) {
                if (isset($existing[$ext])) {
                    $delete[$ext] = $existing[$ext]['id'];
                }
                if ($google) {
                    // a whole series removed at Google: its occurrences are "<series id>_<start>"
                    foreach ($existing as $x => $row) {
                        if (strpos($x, $ext . '_') === 0 && !isset($upsert[$x])) {
                            $delete[$x] = $row['id'];
                        }
                    }
                }
            }
        }
        // how many the link keeps: the ones there and not removed (a full read: only the ones read again)
        $count = 0;
        foreach ($existing as $ext => $x) {
            if (!isset($delete[$ext]) && ($full ? isset($upsert[$ext]) : true)) {
                $count++;
            }
        }
        DB::transaction(function () use ($upsert, $delete, $existing, $linkId, $uid, &$count, &$tooMany) {
            $now = now();
            foreach (array_chunk(array_values($delete), 500) as $chunk) {
                DB::table(Events::OVERRIDES)->whereIn('event_id', $chunk)->delete();
                DB::table(Events::EVENTS)->whereIn('id', $chunk)->delete();
            }
            foreach ($upsert as $ext => $row) {
                if (isset($existing[$ext]) && !isset($delete[$ext])) {
                    if ($existing[$ext]['etag'] !== $row['external_etag']) {
                        DB::table(Events::EVENTS)->where('id', $existing[$ext]['id'])->update($row + ['updated_at' => $now]);
                    }
                    continue;
                }
                if ($count >= Ics::MAX_EVENTS) {
                    $tooMany = true;
                    continue;
                }
                DB::table(Events::EVENTS)->insertGetId($row + [
                    'owner_user_id' => $uid, 'source' => 'link', 'link_id' => $linkId, 'external_id' => $ext,
                    'created_user_id' => $uid, 'updated_user_id' => $uid, 'created_at' => $now, 'updated_at' => $now,
                ]);
                $count++;
            }
        });
        return $count;
    }
}
