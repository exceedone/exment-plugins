<?php

namespace App\Plugins\ExmentPM;

/**
 * The national holidays of Japan (国民の祝日), worked out from the law, so
 * nothing has to be fetched or updated each year (ISS00432).
 *
 * From 2020 on (the law as it stands, with the moves of the Tokyo Olympics in
 * 2020 and 2021) up to 2099, the last year the formulas of the equinoxes hold
 * for. A year outside that range has no holidays here.
 *
 * - fixed days, and the Mondays of the "Happy Monday" law
 * - the equinoxes (春分の日・秋分の日) by the usual formula. The Cabinet
 *   Office announces them each February for the next year; the formula has
 *   matched every announcement so far.
 * - 国民の休日: a day between two holidays (as 22 September 2026)
 * - 振替休日: a holiday on a Sunday moves the next day off to the first day
 *   after it that is not a holiday (3 May on a Sunday: 6 May)
 *
 * A new holiday or a one-off (as the days of an enthronement) needs a change
 * here, as the law does.
 */
class JapaneseHolidays
{
    const FIRST_YEAR = 2020;
    const LAST_YEAR = 2099;

    /** @var array<int, array<string, string>> */
    protected static $cache = [];

    /**
     * The holidays of a year, by date.
     *
     * @return array<string, string> ['Y-m-d' => name], in date order
     */
    public static function forYear(int $year): array
    {
        if ($year < self::FIRST_YEAR || $year > self::LAST_YEAR) {
            return [];
        }
        if (isset(static::$cache[$year])) {
            return static::$cache[$year];
        }

        $days = [];
        $add = function (int $month, int $day, string $name) use (&$days, $year) {
            $days[sprintf('%04d-%02d-%02d', $year, $month, $day)] = $name;
        };

        $add(1, 1, '元日');
        $add(1, static::nthMonday($year, 1, 2), '成人の日');
        $add(2, 11, '建国記念の日');
        $add(2, 23, '天皇誕生日');
        $add(3, static::vernalEquinox($year), '春分の日');
        $add(4, 29, '昭和の日');
        $add(5, 3, '憲法記念日');
        $add(5, 4, 'みどりの日');
        $add(5, 5, 'こどもの日');
        if ($year === 2020) {
            $add(7, 23, '海の日');
            $add(7, 24, 'スポーツの日');
            $add(8, 10, '山の日');
        } elseif ($year === 2021) {
            $add(7, 22, '海の日');
            $add(7, 23, 'スポーツの日');
            $add(8, 8, '山の日');
        } else {
            $add(7, static::nthMonday($year, 7, 3), '海の日');
            $add(8, 11, '山の日');
            $add(10, static::nthMonday($year, 10, 2), 'スポーツの日');
        }
        $add(9, static::nthMonday($year, 9, 3), '敬老の日');
        $add(9, static::autumnalEquinox($year), '秋分の日');
        $add(11, 3, '文化の日');
        $add(11, 23, '勤労感謝の日');
        ksort($days);

        // 国民の休日: a day, not a Sunday nor a holiday, between two holidays
        $base = $days;
        foreach (array_keys($base) as $date) {
            $next = static::shiftDate($date, 1);
            $after = static::shiftDate($date, 2);
            if (!isset($base[$next]) && isset($base[$after]) && static::weekday($next) !== 0) {
                $days[$next] = '国民の休日';
            }
        }

        // 振替休日
        foreach (array_keys($base) as $date) {
            if (static::weekday($date) !== 0) {
                continue;
            }
            $sub = static::shiftDate($date, 1);
            while (isset($days[$sub])) {
                $sub = static::shiftDate($sub, 1);
            }
            $days[$sub] = '振替休日';
        }
        ksort($days);

        return static::$cache[$year] = $days;
    }

    /** The name of the holiday on a date ('Y-m-d'), or null. */
    public static function name(string $date): ?string
    {
        $year = intval(substr($date, 0, 4));

        return static::forYear($year)[$date] ?? null;
    }

    /** The day of March. Holds from 1980 to 2099. */
    public static function vernalEquinox(int $year): int
    {
        return intval(floor(20.8431 + 0.242194 * ($year - 1980) - floor(($year - 1980) / 4)));
    }

    /** The day of September. Holds from 1980 to 2099. */
    public static function autumnalEquinox(int $year): int
    {
        return intval(floor(23.2488 + 0.242194 * ($year - 1980) - floor(($year - 1980) / 4)));
    }

    /** The day of the month of its n-th Monday. */
    protected static function nthMonday(int $year, int $month, int $n): int
    {
        $first = static::weekday(sprintf('%04d-%02d-01', $year, $month));
        $firstMonday = 1 + ((8 - $first) % 7);

        return $firstMonday + 7 * ($n - 1);
    }

    /** 0 (Sunday) to 6 (Saturday). */
    protected static function weekday(string $date): int
    {
        return intval((new \DateTimeImmutable($date))->format('w'));
    }

    protected static function shiftDate(string $date, int $days): string
    {
        return (new \DateTimeImmutable($date))->modify(($days >= 0 ? '+' : '') . $days . ' day')->format('Y-m-d');
    }
}
