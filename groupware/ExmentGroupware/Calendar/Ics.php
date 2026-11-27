<?php

namespace App\Plugins\ExmentGroupware\Calendar;

/**
 * iCalendar (RFC 5545) for the calendar (ISS02947, part 5), without the database and without a
 * library: the text of the ICS export, and the events of an outside calendar's ICS.
 *
 * Writing: the occurrences as the viewer sees them (Events::window), one VEVENT each, times in
 * UTC (an all-day one by its days). A busy one is a block of time only: SUMMARY is the word
 * 「予定あり」, and no UID of the event, no place, no notes, no attendees.
 *
 * Reading: VEVENTs with DTSTART, DTEND or DURATION, all day (VALUE=DATE), TZID (an IANA name, a
 * Windows name of the common ones, or the X-LIC-LOCATION of its VTIMEZONE; else the site's time
 * zone), UTC, floating times, RRULE (the part Rrule reads), EXDATE, RDATE, RECURRENCE-ID and
 * STATUS:CANCELLED. Anything it cannot read is left out, never a failure of the whole.
 */
class Ics
{
    /** Events read from one calendar at most. */
    const MAX_EVENTS = 5000;
    /** Lines read at most (a calendar of 5 MB has fewer). */
    const MAX_LINES = 200000;
    /** The occurrences a repeat read from outside counts at most (COUNT). */
    const MAX_COUNT = 1000;

    /** The common Windows names of time zones (Outlook / Exchange write them). */
    const WINDOWS_TZ = [
        'Tokyo Standard Time' => 'Asia/Tokyo',
        'Korea Standard Time' => 'Asia/Seoul',
        'China Standard Time' => 'Asia/Shanghai',
        'Taipei Standard Time' => 'Asia/Taipei',
        'Singapore Standard Time' => 'Asia/Singapore',
        'SE Asia Standard Time' => 'Asia/Bangkok',
        'India Standard Time' => 'Asia/Kolkata',
        'UTC' => 'UTC',
        'Coordinated Universal Time' => 'UTC',
        'GMT Standard Time' => 'Europe/London',
        'W. Europe Standard Time' => 'Europe/Berlin',
        'Romance Standard Time' => 'Europe/Paris',
        'Central Europe Standard Time' => 'Europe/Budapest',
        'Eastern Standard Time' => 'America/New_York',
        'Central Standard Time' => 'America/Chicago',
        'Mountain Standard Time' => 'America/Denver',
        'Pacific Standard Time' => 'America/Los_Angeles',
        'AUS Eastern Standard Time' => 'Australia/Sydney',
    ];

    // ---- writing ----------------------------------------------------------------------

    /** A TEXT value (RFC 5545 3.3.11): backslash, semicolon, comma and line breaks escaped. */
    public static function escape(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        // other control characters are not allowed in a value
        $text = preg_replace('/[\x00-\x08\x0b-\x1f\x7f]/u', '', $text) ?? '';
        return str_replace(['\\', ';', ',', "\n"], ['\\\\', '\\;', '\\,', '\\n'], $text);
    }

    /** One content line folded at 75 octets (3.1), never inside a UTF-8 character. */
    public static function fold(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line . "\r\n";
        }
        $out = '';
        $cur = '';
        $limit = 75;
        foreach (preg_split('//u', $line, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $ch) {
            if (strlen($cur) + strlen($ch) > $limit) {
                $out .= $cur . "\r\n ";
                $cur = '';
                // the next lines start with a space: 74 octets of text
                $limit = 74;
            }
            $cur .= $ch;
        }
        return $out . $cur . "\r\n";
    }

    /** 'Y-m-d H:i:s' (UTC) as a DATE-TIME in UTC, or a DATE. */
    protected static function stamp(string $utc, bool $date): string
    {
        $d = new \DateTimeImmutable($utc, new \DateTimeZone('UTC'));
        return $date ? $d->format('Ymd') : $d->format('Ymd\THis\Z');
    }

    /**
     * The ICS export of one person's calendar.
     *
     * @param array<int, array<string, mixed>> $items occurrences from Events::window (busy ones have 'busy')
     * @param array{name?: string, host?: string, busy?: string, now?: string, salt?: string} $o
     */
    public static function build(array $items, array $o = []): string
    {
        $host = preg_replace('/[^a-z0-9.-]/i', '', strval($o['host'] ?? '')) ?: 'exment.invalid';
        $now = static::stamp(strval($o['now'] ?? gmdate('Y-m-d H:i:s')), false);
        $busy = strval($o['busy'] ?? 'Busy');
        $salt = strval($o['salt'] ?? '');
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Exment//Groupware Calendar//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
        ];
        if (!empty($o['name'])) {
            $lines[] = 'X-WR-CALNAME:' . static::escape(strval($o['name']));
        }
        $seen = [];
        foreach ($items as $it) {
            $allDay = !empty($it['all_day']);
            $start = strval($it['start'] ?? '');
            $end = strval($it['end'] ?? $start);
            if ($start === '') {
                continue;
            }
            try {
                $s = static::stamp($start, $allDay);
                $e = static::stamp($end, $allDay);
            } catch (\Throwable $ex) {
                continue;
            }
            if ($allDay && strcmp($e, $s) <= 0) {
                $e = (new \DateTimeImmutable($start, new \DateTimeZone('UTC')))->modify('+1 day')->format('Ymd');
            }
            $isBusy = !empty($it['busy']) || !isset($it['id']);
            if ($isBusy) {
                // a block of time: its UID says nothing of the event (not its id)
                $base = 'busy-' . substr(hash('sha256', $salt . '|' . $start . '|' . $end . '|' . ($allDay ? 1 : 0)), 0, 32);
            } else {
                $base = 'gw-' . intval($it['id']) . '-' . preg_replace('/\D/', '', strval($it['original'] ?? $start));
            }
            $uid = $base;
            for ($n = 2; isset($seen[$uid]); $n++) {
                $uid = $base . '-' . $n;
            }
            $seen[$uid] = true;
            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:' . $uid . '@' . $host;
            $lines[] = 'DTSTAMP:' . $now;
            $lines[] = $allDay ? 'DTSTART;VALUE=DATE:' . $s : 'DTSTART:' . $s;
            $lines[] = $allDay ? 'DTEND;VALUE=DATE:' . $e : 'DTEND:' . $e;
            if ($isBusy) {
                $lines[] = 'SUMMARY:' . static::escape($busy);
                $lines[] = 'CLASS:PRIVATE';
            } else {
                $lines[] = 'SUMMARY:' . static::escape(strval($it['title'] ?? ''));
                if (strval($it['location'] ?? '') !== '') {
                    $lines[] = 'LOCATION:' . static::escape(strval($it['location']));
                }
                $lines[] = 'CLASS:PUBLIC';
            }
            $lines[] = 'TRANSP:OPAQUE';
            $lines[] = 'END:VEVENT';
        }
        $lines[] = 'END:VCALENDAR';
        return implode('', array_map([static::class, 'fold'], $lines));
    }

    // ---- reading ----------------------------------------------------------------------

    /** A TEXT value read back. */
    public static function unescape(string $v): string
    {
        return preg_replace_callback('/\\\\([\\\\;,nN])/', function ($m) {
            return ($m[1] === 'n' || $m[1] === 'N') ? "\n" : $m[1];
        }, $v) ?? $v;
    }

    /**
     * The components of an ICS text: VEVENTs (their properties) and the X-LIC-LOCATION of the
     * VTIMEZONEs. Never throws: a broken line is skipped.
     *
     * @return array{events: array<int, array<string, array<int, array{params: array<string, string>, value: string}>>>, tz: array<string, string>}
     */
    public static function components(string $text): array
    {
        // a BOM, the line ends of any system, then the folded lines joined
        $text = preg_replace('/^\xEF\xBB\xBF/', '', $text) ?? '';
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/\n[ \t]/', '', $text) ?? '';
        if (!mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        }
        $events = [];
        $tz = [];
        $stack = [];
        $cur = null;
        $tzid = null;
        $n = 0;
        foreach (explode("\n", $text) as $line) {
            if (++$n > static::MAX_LINES || count($events) > static::MAX_EVENTS * 2) {
                break;
            }
            if ($line === '') {
                continue;
            }
            $prop = static::property($line);
            if ($prop === null) {
                continue;
            }
            [$name, $params, $value] = $prop;
            if ($name === 'BEGIN') {
                $stack[] = strtoupper(trim($value));
                if (strtoupper(trim($value)) === 'VEVENT' && count($stack) >= 1) {
                    $cur = [];
                }
                continue;
            }
            if ($name === 'END') {
                $what = strtoupper(trim($value));
                array_pop($stack);
                if ($what === 'VEVENT' && $cur !== null) {
                    $events[] = $cur;
                    $cur = null;
                } elseif ($what === 'VTIMEZONE') {
                    $tzid = null;
                }
                continue;
            }
            $top = end($stack);
            if ($top === 'VEVENT' && $cur !== null) {
                $cur[$name][] = ['params' => $params, 'value' => $value];
            } elseif ($top === 'VTIMEZONE') {
                if ($name === 'TZID') {
                    $tzid = $value;
                } elseif ($name === 'X-LIC-LOCATION' && $tzid !== null) {
                    $tz[$tzid] = $value;
                }
            }
        }
        // a calendar cut short (its END lines lost): the last event as far as it goes
        if ($cur !== null) {
            $events[] = $cur;
        }
        return ['events' => $events, 'tz' => $tz];
    }

    /**
     * One content line: name, parameters (upper-case names), value.
     *
     * @return array{0: string, 1: array<string, string>, 2: string}|null
     */
    protected static function property(string $line): ?array
    {
        // the value starts at the first ":" outside a quoted parameter value
        $len = strlen($line);
        $q = false;
        $colon = -1;
        for ($i = 0; $i < $len; $i++) {
            $c = $line[$i];
            if ($c === '"') {
                $q = !$q;
            } elseif ($c === ':' && !$q) {
                $colon = $i;
                break;
            }
        }
        if ($colon <= 0) {
            return null;
        }
        $head = substr($line, 0, $colon);
        $value = substr($line, $colon + 1);
        $parts = preg_split('/;(?=(?:[^"]*"[^"]*")*[^"]*$)/', $head) ?: [];
        $name = strtoupper(trim(array_shift($parts) ?? ''));
        if ($name === '' || !preg_match('/^[A-Z0-9-]+$/', $name)) {
            return null;
        }
        $params = [];
        foreach ($parts as $p) {
            $eq = strpos($p, '=');
            if ($eq === false) {
                continue;
            }
            $params[strtoupper(trim(substr($p, 0, $eq)))] = trim(substr($p, $eq + 1), '"');
        }
        return [$name, $params, $value];
    }

    /** A time zone by its TZID, or null. */
    public static function zone(?string $tzid, array $known = []): ?\DateTimeZone
    {
        if ($tzid === null || $tzid === '') {
            return null;
        }
        $tzid = trim($tzid, '"/ ');
        $names = [$tzid, static::WINDOWS_TZ[$tzid] ?? null, $known[$tzid] ?? null];
        // "/mozilla.org/20050126_1/Asia/Tokyo" and the like: the last two parts
        if (preg_match('/([A-Za-z_]+\/[A-Za-z_+-]+)$/', $tzid, $m)) {
            $names[] = $m[1];
        }
        foreach ($names as $name) {
            if ($name === null || $name === '') {
                continue;
            }
            try {
                return new \DateTimeZone($name);
            } catch (\Throwable $e) {
                // the next
            }
        }
        return null;
    }

    /**
     * A DATE or DATE-TIME value: the moment in UTC ('Y-m-d H:i:s'), whether it is a date, and
     * the time zone it was written in ('UTC' for a "Z" time).
     *
     * @param array<string, string> $params
     * @param array<string, string> $known TZID => an IANA name (VTIMEZONE's X-LIC-LOCATION)
     * @return array{utc: string, date: bool, tz: string, local: string}|null
     */
    public static function when(string $value, array $params, string $defaultTz, array $known = []): ?array
    {
        $value = trim($value);
        $utc = new \DateTimeZone('UTC');
        if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $value, $m) || strtoupper(strval($params['VALUE'] ?? '')) === 'DATE') {
            if (!preg_match('/^(\d{4})(\d{2})(\d{2})/', $value, $m) || !checkdate(intval($m[2]), intval($m[3]), intval($m[1]))) {
                return null;
            }
            $d = $m[1] . '-' . $m[2] . '-' . $m[3];
            return ['utc' => $d . ' 00:00:00', 'date' => true, 'tz' => $defaultTz, 'local' => $d];
        }
        if (!preg_match('/^(\d{4})(\d{2})(\d{2})T(\d{2})(\d{2})(\d{2})(Z?)$/i', $value, $m)
            || !checkdate(intval($m[2]), intval($m[3]), intval($m[1])) || intval($m[4]) > 23 || intval($m[5]) > 59 || intval($m[6]) > 60) {
            return null;
        }
        $txt = sprintf('%s-%s-%s %s:%s:%02d', $m[1], $m[2], $m[3], $m[4], $m[5], min(59, intval($m[6])));
        if ($m[7] !== '') {
            $zone = $utc;
            $name = 'UTC';
        } else {
            $zone = static::zone($params['TZID'] ?? null, $known);
            if ($zone === null) {
                try {
                    $zone = new \DateTimeZone($defaultTz);
                } catch (\Throwable $e) {
                    $zone = new \DateTimeZone('Asia/Tokyo');
                }
            }
            $name = $zone->getName();
        }
        try {
            $d = new \DateTimeImmutable($txt, $zone);
        } catch (\Throwable $e) {
            return null;
        }
        return ['utc' => $d->setTimezone($utc)->format('Y-m-d H:i:s'), 'date' => false, 'tz' => $name, 'local' => $d->format('Y-m-d')];
    }

    /** A DURATION (3.3.6) in seconds, or null. */
    public static function duration(string $v): ?int
    {
        if (!preg_match('/^([+-])?P(?:(\d+)W)?(?:(\d+)D)?(?:T(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?)?$/i', trim($v), $m) || trim($v, '+-Pp') === '') {
            return null;
        }
        $s = intval($m[2] ?? 0) * 604800 + intval($m[3] ?? 0) * 86400 + intval($m[4] ?? 0) * 3600 + intval($m[5] ?? 0) * 60 + intval($m[6] ?? 0);
        return ($m[1] ?? '') === '-' ? -$s : $s;
    }

    /**
     * An RRULE of an outside calendar as Rrule reads it, or null when it cannot be read: the
     * "RRULE:" gone, WKST=MO dropped (Rrule's weeks start on Monday), WKST of another day only
     * where it changes nothing, and UNTIL as the date of the event's time zone.
     */
    public static function rule(string $rule, string $tz, bool $allDay): ?string
    {
        $parts = [];
        $r = strtoupper(trim(preg_replace('/^RRULE:/i', '', trim($rule)) ?? ''));
        $weekly = preg_match('/(^|;)FREQ=WEEKLY(;|$)/', $r) === 1;
        $interval = preg_match('/(^|;)INTERVAL=(\d+)/', $r, $im) ? intval($im[2]) : 1;
        $byDay = preg_match('/(^|;)BYDAY=([^;]+)/', $r, $bm) ? count(explode(',', $bm[2])) : 0;
        foreach (explode(';', $r) as $p) {
            if ($p === '') {
                continue;
            }
            if (preg_match('/^WKST=([A-Z]{2})$/', $p, $w)) {
                // the start of the week counts only for a weekly rule of more than a week with days
                if ($w[1] !== 'MO' && $weekly && $interval > 1 && $byDay > 1) {
                    return null;
                }
                continue;
            }
            if (preg_match('/^COUNT=(\d+)$/', $p, $cm)) {
                // a repeat read from outside counts to MAX_COUNT at most
                $p = 'COUNT=' . min(static::MAX_COUNT, max(1, intval($cm[1])));
            }
            if (preg_match('/^UNTIL=(\d{8})(T\d{6}Z?)?$/', $p, $u)) {
                if (!$allDay && isset($u[2]) && substr($u[2], -1) === 'Z') {
                    try {
                        $d = (new \DateTimeImmutable($u[1] . $u[2], new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone($tz));
                        $p = 'UNTIL=' . $d->format('Ymd');
                    } catch (\Throwable $e) {
                        return null;
                    }
                } else {
                    $p = 'UNTIL=' . $u[1];
                }
            }
            $parts[] = $p;
        }
        $out = implode(';', $parts);
        if ($out === '' || strlen($out) > 255 || Rrule::parse($out) === null || Rrule::parse($out)['FREQ'] === null) {
            return null;
        }
        return $out;
    }

    /**
     * A rule with COUNT as the same rule with UNTIL its last date (in the event's time zone),
     * worked out once when it is read: then nothing has to walk it from its start again (its
     * occurrences on the page, the end kept for the queries, the plan of a read).
     */
    public static function countAsUntil(string $rule, string $localStart): string
    {
        $r = Rrule::parse($rule);
        if ($r === null || $r['COUNT'] === null) {
            return $rule;
        }
        $last = static::lastOfCount($rule, $r, $localStart);
        $parts = array_filter(explode(';', $rule), function ($p) {
            return strpos($p, 'COUNT=') !== 0 && strpos($p, 'UNTIL=') !== 0;
        });
        $parts[] = 'UNTIL=' . str_replace('-', '', $last);
        $out = implode(';', $parts);
        return strlen($out) <= 255 ? $out : $rule;
    }

    /**
     * The last date of a rule with COUNT ('Y-m-d'). The common ones are worked out (every n days;
     * every n weeks on some weekdays, or a daily rule kept to some weekdays): a calendar may have
     * hundreds of them. The others are walked from the start (Rrule), as the calendar does.
     *
     * @param array<string, mixed> $r Rrule::parse($rule)
     */
    public static function lastOfCount(string $rule, array $r, string $localStart): string
    {
        $count = intval($r['COUNT']);
        $s = new \DateTimeImmutable($localStart);
        $plain = empty($r['BYMONTH']) && empty($r['BYMONTHDAY']) && empty($r['BYSETPOS']) && $r['UNTIL'] === null;
        $weekdays = [];
        foreach ($r['BYDAY'] as [$n, $wd]) {
            if ($n !== 0) {
                $plain = false;
            }
            $weekdays[$wd] = true;
        }
        $weekdays = array_keys($weekdays);
        sort($weekdays);
        if ($plain && $r['FREQ'] === 'DAILY' && empty($weekdays)) {
            return $s->modify('+' . (($count - 1) * $r['INTERVAL']) . ' day')->format('Y-m-d');
        }
        if ($plain && $r['FREQ'] === 'WEEKLY' && empty($weekdays)) {
            return $s->modify('+' . (($count - 1) * $r['INTERVAL']) . ' week')->format('Y-m-d');
        }
        if ($plain && !empty($weekdays) && ($r['FREQ'] === 'WEEKLY' || ($r['FREQ'] === 'DAILY' && $r['INTERVAL'] === 1))) {
            $interval = $r['FREQ'] === 'WEEKLY' ? $r['INTERVAL'] : 1;
            $dow = intval($s->format('N'));
            // the dates of the start's own week, from the start on
            $first = array_values(array_filter($weekdays, function ($wd) use ($dow) {
                return $wd >= $dow;
            }));
            $monday = $s->modify('-' . ($dow - 1) . ' day');
            if ($count <= count($first)) {
                return $monday->modify('+' . ($first[$count - 1] - 1) . ' day')->format('Y-m-d');
            }
            $q = $count - count($first) - 1;
            $week = 1 + intdiv($q, count($weekdays));
            return $monday->modify('+' . ($week * $interval * 7 + $weekdays[$q % count($weekdays)] - 1) . ' day')->format('Y-m-d');
        }
        $far = $s->modify('+100 year')->format('Y-m-d');
        $dates = Rrule::between($rule, $localStart, $localStart, $r['UNTIL'] ?? $far) ?? [];
        return empty($dates) ? $localStart : strval(end($dates));
    }

    /**
     * The values of a list property (EXDATE, RDATE) as dates of the event's time zone.
     *
     * @param array<int, array{params: array<string, string>, value: string}> $props
     * @return array<int, string>
     */
    protected static function dates(array $props, string $tz, bool $allDay, string $defaultTz, array $known): array
    {
        $out = [];
        foreach ($props as $p) {
            if (strtoupper(strval($p['params']['VALUE'] ?? '')) === 'PERIOD') {
                continue;
            }
            foreach (explode(',', $p['value']) as $v) {
                $w = static::when($v, $p['params'], $defaultTz, $known);
                if ($w === null) {
                    continue;
                }
                if ($allDay || $w['date']) {
                    $out[] = substr($w['utc'], 0, 10);
                } else {
                    try {
                        $out[] = (new \DateTimeImmutable($w['utc'], new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone($tz))->format('Y-m-d');
                    } catch (\Throwable $e) {
                        // skipped
                    }
                }
            }
        }
        $out = array_values(array_unique($out));
        sort($out);
        return array_slice($out, 0, 1000);
    }

    /** @param array<string, array<int, array{params: array<string, string>, value: string}>> $ev */
    protected static function first(array $ev, string $name): ?array
    {
        return $ev[$name][0] ?? null;
    }

    /** @param array<string, array<int, array{params: array<string, string>, value: string}>> $ev */
    protected static function text(array $ev, string $name, int $max): string
    {
        $p = static::first($ev, $name);
        if ($p === null) {
            return '';
        }
        return trim(mb_substr(static::unescape($p['value']), 0, $max));
    }

    /**
     * The events of an ICS text, as the calendar keeps them: one per UID (the series), with its
     * occurrences changed or cancelled (RECURRENCE-ID), in a window of time ('Y-m-d H:i:s', UTC).
     * A series that ends before the window or starts after it is left out.
     *
     * $deadline (microtime(true)): past it, the read stops and says so (timed_out), with no events:
     * a calendar made to be slow never holds a batch or a page.
     *
     * @return array{events: array<string, array<string, mixed>>, skipped: int, rules_unread: int, too_many: bool, timed_out: bool}
     */
    public static function events(string $text, string $defaultTz, string $from, string $to, ?float $deadline = null): array
    {
        $late = function () use ($deadline) {
            return $deadline !== null && microtime(true) > $deadline;
        };
        $timedOut = ['events' => [], 'skipped' => 0, 'rules_unread' => 0, 'too_many' => false, 'timed_out' => true];
        $c = static::components($text);
        $known = $c['tz'];
        $masters = [];
        $changes = [];
        $skipped = 0;
        $unread = 0;
        foreach ($c['events'] as $ev) {
            if ($late()) {
                return $timedOut;
            }
            $uid = static::text($ev, 'UID', 255);
            $ds = static::first($ev, 'DTSTART');
            $start = $ds !== null ? static::when($ds['value'], $ds['params'], $defaultTz, $known) : null;
            if ($uid === '' || $start === null) {
                $skipped++;
                continue;
            }
            $allDay = $start['date'];
            $end = null;
            $de = static::first($ev, 'DTEND');
            if ($de !== null) {
                $w = static::when($de['value'], $de['params'], $defaultTz, $known);
                $end = $w !== null ? $w['utc'] : null;
            } elseif (($du = static::first($ev, 'DURATION')) !== null && ($sec = static::duration($du['value'])) !== null && $sec >= 0) {
                $end = (new \DateTimeImmutable($start['utc'], new \DateTimeZone('UTC')))->modify('+' . $sec . ' second')->format('Y-m-d H:i:s');
            }
            if ($end === null || strcmp($end, $start['utc']) < 0) {
                // no end: a day for a date, a moment for a time (RFC 5545 3.6.1)
                $end = $allDay ? (new \DateTimeImmutable($start['utc'], new \DateTimeZone('UTC')))->modify('+1 day')->format('Y-m-d H:i:s') : $start['utc'];
            }
            if ($allDay && strcmp(substr($end, 0, 10), substr($start['utc'], 0, 10)) <= 0) {
                $end = (new \DateTimeImmutable($start['utc'], new \DateTimeZone('UTC')))->modify('+1 day')->format('Y-m-d') . ' 00:00:00';
            }
            $cancelled = strtoupper(static::text($ev, 'STATUS', 20)) === 'CANCELLED';
            $fields = [
                'title' => static::text($ev, 'SUMMARY', 200),
                'location' => static::text($ev, 'LOCATION', 200),
                'body' => static::text($ev, 'DESCRIPTION', 20000),
                'starts_at' => $start['utc'],
                'ends_at' => $allDay ? substr($end, 0, 10) . ' 00:00:00' : $end,
                'all_day' => $allDay,
                'tz' => $allDay ? $defaultTz : $start['tz'],
                'cancelled' => $cancelled,
            ];
            $rid = static::first($ev, 'RECURRENCE-ID');
            if ($rid !== null) {
                $orig = static::when($rid['value'], $rid['params'], $defaultTz, $known);
                if ($orig === null) {
                    $skipped++;
                    continue;
                }
                $changes[$uid][$orig['utc']] = $fields + ['original' => $orig['utc'], 'original_date' => $orig['date']];
                continue;
            }
            $rr = static::first($ev, 'RRULE');
            $rule = null;
            if ($rr !== null) {
                $rule = static::rule($rr['value'], $fields['tz'], $allDay);
                if ($rule === null) {
                    $unread++;
                } else {
                    $rule = static::countAsUntil($rule, $allDay ? substr($start['utc'], 0, 10) : $start['local']);
                }
            }
            $fields['rrule'] = $rule;
            $fields['exdates'] = static::dates($ev['EXDATE'] ?? [], $fields['tz'], $allDay, $defaultTz, $known);
            $fields['rdates'] = static::dates($ev['RDATE'] ?? [], $fields['tz'], $allDay, $defaultTz, $known);
            // the same UID twice (a broken calendar): the first
            if (!isset($masters[$uid])) {
                $masters[$uid] = $fields;
            }
        }
        $out = [];
        $tooMany = false;
        foreach ($masters as $uid => $m) {
            if ($late()) {
                return $timedOut;
            }
            if ($m['cancelled']) {
                continue;
            }
            $m['overrides'] = [];
            foreach ($changes[$uid] ?? [] as $orig => $ch) {
                // the original start as the calendar works it out: a date at 00:00 UTC for all day
                $original = $m['all_day'] ? substr($orig, 0, 10) . ' 00:00:00' : $orig;
                $m['overrides'][] = $ch['cancelled']
                    ? ['original_start' => $original, 'cancelled' => true, 'starts_at' => null, 'ends_at' => null, 'title' => null, 'location' => null]
                    : ['original_start' => $original, 'cancelled' => false, 'starts_at' => $ch['starts_at'], 'ends_at' => $ch['ends_at'],
                        'title' => $ch['title'] !== $m['title'] ? $ch['title'] : null, 'location' => $ch['location'] !== $m['location'] ? $ch['location'] : null];
            }
            unset($changes[$uid]);
            if (!static::inWindow($m, $from, $to)) {
                continue;
            }
            if (count($out) >= static::MAX_EVENTS) {
                $tooMany = true;
                break;
            }
            unset($m['cancelled']);
            $out[$uid] = $m;
        }
        // an occurrence changed whose series is not in the calendar: an event of its own
        foreach ($changes as $uid => $list) {
            foreach ($list as $orig => $ch) {
                if ($ch['cancelled']) {
                    continue;
                }
                $ch['rrule'] = null;
                $ch['exdates'] = [];
                $ch['rdates'] = [];
                $ch['overrides'] = [];
                $key = mb_substr(strval($uid), 0, 230) . '#' . $orig;
                if (count($out) >= static::MAX_EVENTS) {
                    $tooMany = true;
                    break 2;
                }
                if (static::inWindow($ch, $from, $to)) {
                    unset($ch['cancelled'], $ch['original'], $ch['original_date']);
                    $out[$key] = $ch;
                }
            }
        }
        return ['events' => $out, 'skipped' => $skipped, 'rules_unread' => $unread, 'too_many' => $tooMany, 'timed_out' => false];
    }

    /** Whether an event (with its repeat) can have an occurrence in the window. */
    protected static function inWindow(array $m, string $from, string $to): bool
    {
        if (strcmp($m['starts_at'], $to) >= 0) {
            // a moved occurrence may come into the window from later: rare, left out
            return false;
        }
        if (empty($m['rrule']) && empty($m['rdates'])) {
            if (strcmp($m['ends_at'], $from) > 0 || (strcmp($m['ends_at'], $m['starts_at']) === 0 && strcmp($m['starts_at'], $from) >= 0)) {
                return true;
            }
            foreach ($m['overrides'] ?? [] as $o) {
                if (!empty($o['ends_at']) && strcmp(strval($o['ends_at']), $from) > 0) {
                    return true;
                }
            }
            return false;
        }
        if (!empty($m['rdates']) && strcmp(end($m['rdates']), substr($from, 0, 10)) >= 0) {
            return true;
        }
        if (!empty($m['rrule'])) {
            $until = Events::repeatUntil($m['rrule'], $m['starts_at'], $m['tz'], $m['all_day']);
            return $until === null || strcmp($until, $from) >= 0;
        }
        return false;
    }
}
