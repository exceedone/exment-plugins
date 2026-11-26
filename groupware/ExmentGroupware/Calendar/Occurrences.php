<?php

namespace App\Plugins\ExmentGroupware\Calendar;

/**
 * The occurrences of an event in a window of time (ISS02947), without the database: the event's
 * own times, its repeat (Rrule, in the event's time zone, so 09:00 stays 09:00 over a change of
 * summer time), the dates taken out (EXDATE), the dates added (RDATE), and the occurrences changed
 * or cancelled one by one (overrides).
 *
 * An event: starts_at, ends_at (UTC, 'Y-m-d H:i:s'), all_day (then the dates are the days, the end
 * the day after the last), tz, rrule, exdates, rdates ('Y-m-d' lists). An override: original_start
 * (the start it had, UTC), cancelled, starts_at, ends_at, title, location (null: as the event).
 */
class Occurrences
{
    /** Occurrences one event gives at most in a window. */
    const MAX = 1000;

    /**
     * @param array<string, mixed> $e
     * @param array<int, array<string, mixed>> $overrides
     * @return array<int, array{start: string, end: string, original: string, changed: bool, title: ?string, location: ?string}> by start, UTC
     */
    public static function between(array $e, string $from, string $to, array $overrides = []): array
    {
        $utc = new \DateTimeZone('UTC');
        try {
            $tz = new \DateTimeZone(strval($e['tz'] ?? '') ?: 'Asia/Tokyo');
        } catch (\Throwable $ex) {
            $tz = new \DateTimeZone('Asia/Tokyo');
        }
        $allDay = !empty($e['all_day']);
        $start = new \DateTimeImmutable(strval($e['starts_at']), $utc);
        $end = new \DateTimeImmutable(strval($e['ends_at'] ?? $e['starts_at']), $utc);
        $length = max(0, $end->getTimestamp() - $start->getTimestamp());
        $local = $allDay ? $start : $start->setTimezone($tz);
        $time = $local->format('H:i:s');
        $wFrom = new \DateTimeImmutable($from, $utc);
        $wTo = new \DateTimeImmutable($to, $utc);

        $byOriginal = [];
        foreach ($overrides as $o) {
            $byOriginal[(new \DateTimeImmutable(strval($o['original_start']), $utc))->format('Y-m-d H:i:s')] = $o;
        }

        // the local dates of the occurrences (a day before and after the window: a time zone apart)
        $rule = trim(strval($e['rrule'] ?? ''));
        $rdates = static::days($e['rdates'] ?? []);
        if ($rule === '' && empty($rdates)) {
            $dates = [$local->format('Y-m-d')];
        } else {
            $dFrom = $wFrom->modify('-' . (intdiv($length, 86400) + 2) . ' day')->setTimezone($tz)->format('Y-m-d');
            $dTo = $wTo->modify('+2 day')->setTimezone($tz)->format('Y-m-d');
            $dates = Rrule::between($rule, $local->format('Y-m-d'), $dFrom, $dTo, $rdates) ?? [$local->format('Y-m-d')];
            if (!in_array($local->format('Y-m-d'), $dates, true) && $rule === '' && strcmp($local->format('Y-m-d'), $dFrom) >= 0) {
                $dates[] = $local->format('Y-m-d');
            }
        }
        $ex = array_flip(static::days($e['exdates'] ?? []));

        $out = [];
        foreach ($dates as $date) {
            if (isset($ex[$date])) {
                continue;
            }
            $s = $allDay
                ? new \DateTimeImmutable($date . ' 00:00:00', $utc)
                : (new \DateTimeImmutable($date . ' ' . $time, $tz))->setTimezone($utc);
            $original = $s->format('Y-m-d H:i:s');
            $occ = ['start' => $original, 'end' => $s->modify('+' . $length . ' second')->format('Y-m-d H:i:s'), 'original' => $original, 'changed' => false, 'title' => null, 'location' => null];
            if (isset($byOriginal[$original])) {
                $o = $byOriginal[$original];
                unset($byOriginal[$original]);
                if (!empty($o['cancelled'])) {
                    continue;
                }
                $occ = static::applied($occ, $o);
            }
            if (static::overlaps($occ, $wFrom, $wTo)) {
                $out[] = $occ;
            }
            if (count($out) >= static::MAX) {
                break;
            }
        }
        // an occurrence moved into the window from a date outside it
        foreach ($byOriginal as $original => $o) {
            if (!empty($o['cancelled']) || empty($o['starts_at'])) {
                continue;
            }
            $occ = static::applied(['start' => $original, 'end' => $original, 'original' => $original, 'changed' => false, 'title' => null, 'location' => null], $o);
            if (static::overlaps($occ, $wFrom, $wTo)) {
                $out[] = $occ;
            }
        }
        usort($out, function ($a, $b) {
            return strcmp($a['start'], $b['start']);
        });

        return $out;
    }

    /**
     * @param array<string, mixed> $occ
     * @param array<string, mixed> $o
     * @return array<string, mixed>
     */
    protected static function applied(array $occ, array $o): array
    {
        if (!empty($o['starts_at'])) {
            $occ['start'] = strval($o['starts_at']);
            $occ['end'] = strval($o['ends_at'] ?? $o['starts_at']);
        }
        $occ['title'] = isset($o['title']) && $o['title'] !== '' ? strval($o['title']) : null;
        $occ['location'] = isset($o['location']) && $o['location'] !== '' ? strval($o['location']) : null;
        $occ['changed'] = true;
        return $occ;
    }

    /** @param array<string, mixed> $occ */
    protected static function overlaps(array $occ, \DateTimeImmutable $from, \DateTimeImmutable $to): bool
    {
        $s = new \DateTimeImmutable($occ['start'], new \DateTimeZone('UTC'));
        $e = new \DateTimeImmutable($occ['end'], new \DateTimeZone('UTC'));
        // an event of no length is in the window it starts in
        return $s < $to && ($e > $from || ($e == $s && $s >= $from));
    }

    /**
     * @param mixed $v a list or its JSON
     * @return array<int, string>
     */
    public static function days($v): array
    {
        if (is_string($v)) {
            $v = json_decode($v, true);
        }
        $out = [];
        foreach ((array) $v as $d) {
            $d = Rrule::day($d);
            if ($d !== null) {
                $out[] = $d;
            }
        }
        return array_values(array_unique($out));
    }
}
