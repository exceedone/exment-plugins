<?php

namespace App\Plugins\ExmentGroupware\Calendar;

/**
 * The dates of a repeating event of the calendar (ISS02947): a part of iCalendar's RRULE (RFC 5545)
 * and RDATE, read by this class alone - no library. A copy of ExmentPM's PortalRrule (the plugins
 * share no code); the time of an occurrence is the event's own (Occurrences).
 *
 * Read: FREQ (DAILY, WEEKLY, MONTHLY, YEARLY), INTERVAL, BYDAY (MO..SU, with an ordinal in a
 * monthly or yearly rule: 2TU, -1FR), BYMONTHDAY (1..31, -1..-31), BYMONTH, BYSETPOS, COUNT,
 * UNTIL. Weeks start on Monday (WKST=MO). Anything else is refused by parse(), so a rule that
 * would mean something else elsewhere is never read differently here.
 *
 * Dates only ('Y-m-d'), in the time zone of the event: Occurrences puts the time of day on them.
 */
class Rrule
{
    const FREQS = ['DAILY', 'WEEKLY', 'MONTHLY', 'YEARLY'];
    const DAYS = ['MO' => 1, 'TU' => 2, 'WE' => 3, 'TH' => 4, 'FR' => 5, 'SA' => 6, 'SU' => 7];


    /** Periods walked at most: a rule that never matches cannot run for ever. */
    const MAX_PERIODS = 5000;

    /**
     * The parts of a rule, or null when it is not one this class reads. An empty rule is "no
     * rule" (RDATE only): ['FREQ' => null].
     *
     * @return array<string, mixed>|null
     */
    public static function parse(string $rule): ?array
    {
        $rule = strtoupper(trim(preg_replace('/^RRULE:/i', '', trim($rule)) ?? ''));
        $out = ['FREQ' => null, 'INTERVAL' => 1, 'BYDAY' => [], 'BYMONTHDAY' => [], 'BYMONTH' => [], 'BYSETPOS' => [], 'COUNT' => null, 'UNTIL' => null];
        if ($rule === '') {
            return $out;
        }
        foreach (explode(';', $rule) as $part) {
            if ($part === '') {
                continue;
            }
            if (!preg_match('/^([A-Z]+)=(.+)$/', $part, $m)) {
                return null;
            }
            [$name, $value] = [$m[1], $m[2]];
            switch ($name) {
                case 'FREQ':
                    if (!in_array($value, static::FREQS, true)) {
                        return null;
                    }
                    $out['FREQ'] = $value;
                    break;
                case 'INTERVAL':
                    if (!ctype_digit($value) || intval($value) < 1 || intval($value) > 1000) {
                        return null;
                    }
                    $out['INTERVAL'] = intval($value);
                    break;
                case 'COUNT':
                    if (!ctype_digit($value) || intval($value) < 1 || intval($value) > 10000) {
                        return null;
                    }
                    $out['COUNT'] = intval($value);
                    break;
                case 'UNTIL':
                    if (!preg_match('/^(\d{4})(\d{2})(\d{2})(T\d{6}Z?)?$/', $value, $d) || !checkdate(intval($d[2]), intval($d[3]), intval($d[1]))) {
                        return null;
                    }
                    $out['UNTIL'] = $d[1] . '-' . $d[2] . '-' . $d[3];
                    break;
                case 'WKST':
                    if ($value !== 'MO') {
                        return null;
                    }
                    break;
                case 'BYDAY':
                    foreach (explode(',', $value) as $v) {
                        if (!preg_match('/^([+-]?\d{1,2})?(MO|TU|WE|TH|FR|SA|SU)$/', $v, $d)) {
                            return null;
                        }
                        $n = isset($d[1]) && $d[1] !== '' ? intval($d[1]) : 0;
                        if (abs($n) > 53) {
                            return null;
                        }
                        $out['BYDAY'][] = [$n, static::DAYS[$d[2]]];
                    }
                    break;
                case 'BYMONTHDAY':
                case 'BYMONTH':
                case 'BYSETPOS':
                    $max = ['BYMONTHDAY' => 31, 'BYMONTH' => 12, 'BYSETPOS' => 366][$name];
                    foreach (explode(',', $value) as $v) {
                        if (!preg_match('/^[+-]?\d{1,3}$/', $v) || intval($v) === 0 || abs(intval($v)) > $max || ($name === 'BYMONTH' && intval($v) < 0)) {
                            return null;
                        }
                        $out[$name][] = intval($v);
                    }
                    break;
                default:
                    return null;
            }
        }
        if ($out['FREQ'] === null) {
            return null;
        }
        // an ordinal day belongs to a month or a year
        if (in_array($out['FREQ'], ['DAILY', 'WEEKLY'], true)) {
            foreach ($out['BYDAY'] as [$n]) {
                if ($n !== 0) {
                    return null;
                }
            }
        }
        return $out;
    }

    /**
     * The dates of a schedule from $from to $to (both included), before any shift: the rule's
     * from $start, with the extra dates ($rdates), sorted, each once. COUNT counts from $start.
     *
     * @param array<int, string> $rdates
     * @return array<int, string>|null null: the rule is not one this class reads
     */
    public static function between(string $rule, string $start, string $from, string $to, array $rdates = [], ?string $until = null): ?array
    {
        $r = static::parse($rule);
        $start = static::day($start);
        $from = static::day($from);
        $to = static::day($to);
        if ($r === null || $start === null || $from === null || $to === null) {
            return null;
        }
        $end = $to;
        foreach ([$r['UNTIL'], static::day($until)] as $u) {
            if ($u !== null && strcmp($u, $end) < 0) {
                $end = $u;
            }
        }
        $out = [];
        if ($r['FREQ'] !== null) {
            $count = 0;
            $s = new \DateTimeImmutable($start);
            for ($i = 0; $i < static::MAX_PERIODS; $i++) {
                $period = static::period($s, $r['FREQ'], $i * $r['INTERVAL']);
                if ($period === null || strcmp($period, $end) > 0) {
                    break;
                }
                foreach (static::inPeriod($r, $s, $period) as $date) {
                    if (strcmp($date, $start) < 0) {
                        continue;
                    }
                    if (strcmp($date, $end) > 0) {
                        break 2;
                    }
                    $count++;
                    if ($r['COUNT'] !== null && $count > $r['COUNT']) {
                        break 2;
                    }
                    if (strcmp($date, $from) >= 0) {
                        $out[$date] = true;
                    }
                }
            }
        }
        foreach ($rdates as $d) {
            $d = static::day($d);
            if ($d !== null && strcmp($d, $from) >= 0 && strcmp($d, $end) <= 0) {
                $out[$d] = true;
            }
        }
        $dates = array_keys($out);
        sort($dates);
        return $dates;
    }


    /** The first day of the n-th period after the start's (the start itself for n = 0). */
    protected static function period(\DateTimeImmutable $s, string $freq, int $n): ?string
    {
        switch ($freq) {
            case 'DAILY':
                return $s->modify('+' . $n . ' day')->format('Y-m-d');
            case 'WEEKLY':
                // the Monday of the start's week, n weeks on
                return $s->modify('-' . (intval($s->format('N')) - 1) . ' day')->modify('+' . $n . ' week')->format('Y-m-d');
            case 'MONTHLY':
                return $s->modify('first day of this month')->modify('+' . $n . ' month')->format('Y-m-d');
            case 'YEARLY':
                return $s->setDate(intval($s->format('Y')) + $n, 1, 1)->format('Y-m-d');
        }
        return null;
    }

    /**
     * The dates of one period, sorted, BYSETPOS applied.
     *
     * @return array<int, string>
     */
    protected static function inPeriod(array $r, \DateTimeImmutable $s, string $period): array
    {
        $p = new \DateTimeImmutable($period);
        $dates = [];
        switch ($r['FREQ']) {
            case 'DAILY':
                $dates = static::keep($r, [$period]);
                break;
            case 'WEEKLY':
                $days = empty($r['BYDAY']) ? [intval($s->format('N'))] : array_map(function ($d) {
                    return $d[1];
                }, $r['BYDAY']);
                foreach (array_unique($days) as $wd) {
                    $dates[] = $p->modify('+' . ($wd - 1) . ' day')->format('Y-m-d');
                }
                $dates = static::keep(['BYDAY' => [], 'BYMONTHDAY' => [], 'BYMONTH' => $r['BYMONTH']], $dates);
                break;
            case 'MONTHLY':
                if (!empty($r['BYMONTH']) && !in_array(intval($p->format('n')), $r['BYMONTH'], true)) {
                    break;
                }
                $dates = static::inMonth($r, $s, intval($p->format('Y')), intval($p->format('n')));
                break;
            case 'YEARLY':
                $months = empty($r['BYMONTH']) ? [intval($s->format('n'))] : $r['BYMONTH'];
                if (empty($r['BYMONTH']) && empty($r['BYMONTHDAY']) && empty($r['BYDAY'])) {
                    $months = [intval($s->format('n'))];
                }
                sort($months);
                foreach (array_unique($months) as $m) {
                    $dates = array_merge($dates, static::inMonth($r, $s, intval($p->format('Y')), $m));
                }
                break;
        }
        $dates = array_values(array_unique($dates));
        sort($dates);
        if (!empty($r['BYSETPOS'])) {
            $picked = [];
            $n = count($dates);
            foreach ($r['BYSETPOS'] as $pos) {
                $i = $pos > 0 ? $pos - 1 : $n + $pos;
                if ($i >= 0 && $i < $n) {
                    $picked[$dates[$i]] = true;
                }
            }
            $dates = array_keys($picked);
            sort($dates);
        }
        return $dates;
    }

    /**
     * The days of one month the rule names: BYMONTHDAY and / or BYDAY (an ordinal counts in the
     * month), or the start's day of the month.
     *
     * @return array<int, string>
     */
    protected static function inMonth(array $r, \DateTimeImmutable $s, int $y, int $m): array
    {
        $last = intval((new \DateTimeImmutable(sprintf('%04d-%02d-01', $y, $m)))->format('t'));
        $byMonthDay = [];
        foreach ($r['BYMONTHDAY'] as $d) {
            $day = $d > 0 ? $d : $last + $d + 1;
            if ($day >= 1 && $day <= $last) {
                $byMonthDay[$day] = true;
            }
        }
        $byDay = [];
        foreach ($r['BYDAY'] as [$n, $wd]) {
            // the weekdays $wd of the month, in order
            $all = [];
            for ($day = 1; $day <= $last; $day++) {
                if (intval(date('N', mktime(0, 0, 0, $m, $day, $y))) === $wd) {
                    $all[] = $day;
                }
            }
            if ($n === 0) {
                foreach ($all as $day) {
                    $byDay[$day] = true;
                }
            } else {
                $i = $n > 0 ? $n - 1 : count($all) + $n;
                if ($i >= 0 && $i < count($all)) {
                    $byDay[$all[$i]] = true;
                }
            }
        }
        if (!empty($r['BYMONTHDAY']) && !empty($r['BYDAY'])) {
            $days = array_keys(array_intersect_key($byMonthDay, $byDay));
        } elseif (!empty($r['BYMONTHDAY'])) {
            $days = array_keys($byMonthDay);
        } elseif (!empty($r['BYDAY'])) {
            $days = array_keys($byDay);
        } else {
            // the start's day; a month without it (31 in April) has none, as RFC 5545 says
            $d = intval($s->format('j'));
            $days = $d <= $last ? [$d] : [];
        }
        sort($days);
        return array_map(function ($d) use ($y, $m) {
            return sprintf('%04d-%02d-%02d', $y, $m, $d);
        }, $days);
    }

    /**
     * The dates of a daily or weekly rule that BYDAY, BYMONTHDAY and BYMONTH keep.
     *
     * @param array<int, string> $dates
     * @return array<int, string>
     */
    protected static function keep(array $r, array $dates): array
    {
        return array_values(array_filter($dates, function ($date) use ($r) {
            $t = new \DateTimeImmutable($date);
            if (!empty($r['BYMONTH']) && !in_array(intval($t->format('n')), $r['BYMONTH'], true)) {
                return false;
            }
            if (!empty($r['BYDAY']) && !in_array(intval($t->format('N')), array_map(function ($d) {
                return $d[1];
            }, $r['BYDAY']), true)) {
                return false;
            }
            if (!empty($r['BYMONTHDAY'])) {
                $last = intval($t->format('t'));
                $day = intval($t->format('j'));
                $hit = false;
                foreach ($r['BYMONTHDAY'] as $d) {
                    $hit = $hit || ($d > 0 ? $d === $day : $last + $d + 1 === $day);
                }
                if (!$hit) {
                    return false;
                }
            }
            return true;
        }));
    }

    /** A date as 'Y-m-d', or null. */
    public static function day($v): ?string
    {
        $s = substr(trim(is_scalar($v) ? strval($v) : ''), 0, 10);
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $s, $m) || !checkdate(intval($m[2]), intval($m[3]), intval($m[1]))) {
            return null;
        }
        return $s;
    }
}
