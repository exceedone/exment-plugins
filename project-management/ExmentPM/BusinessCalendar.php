<?php

namespace App\Plugins\ExmentPM;

/**
 * Which days are business days (ISS00432): the reminders and the recurring
 * schedules count and move their dates only through this class.
 *
 * A day is a business day unless it is a weekend day, a national holiday or
 * a day off of the company; a working day of the company (休日出勤) is one
 * whatever else it is. The company's days are one list for the whole site,
 * not per project.
 *
 * It lives in ExmentPM for now. When Exment gets a business-day calendar of
 * its own, this class reads that one instead, and nothing that uses it
 * changes: keep every other part of the plugin away from the holidays and
 * the company's days.
 *
 * Dates are 'Y-m-d' strings in the site's time zone: a business day is a
 * whole day, whatever the time.
 */
class BusinessCalendar
{
    /** What a company's day is. */
    const OFF = 'off';
    const WORK = 'work';

    /** What to do with a date that is not a business day (shift()). */
    const BEFORE = 'before';
    const AFTER = 'after';
    const KEEP = 'keep';
    const SHIFTS = [self::BEFORE, self::AFTER, self::KEEP];

    /** Days a search for a business day goes at most (a calendar of only days off ends it). */
    const SEARCH_LIMIT = 366;

    /** @var array<string, array{kind: string, name: string}> */
    protected $companyDays = [];

    /** @var array<int, int> weekdays off, 0 (Sunday) to 6 (Saturday) */
    protected $weekend;

    /** @var string|null 'jp' or null */
    protected $holidays;

    /**
     * @param array<string, array{kind?: string, name?: string}|string> $companyDays
     *   ['Y-m-d' => ['kind' => 'off'|'work', 'name' => ...]], or ['Y-m-d' => 'off'|'work']
     * @param array<int, int> $weekend
     * @param string|null $holidays the national holidays to use: 'jp', or null for none
     */
    public function __construct(array $companyDays = [], array $weekend = [0, 6], ?string $holidays = 'jp')
    {
        foreach ($companyDays as $date => $day) {
            $date = static::normalize(strval($date));
            if ($date === null) {
                continue;
            }
            $kind = is_array($day) ? strval($day['kind'] ?? self::OFF) : strval($day);
            if (!in_array($kind, [self::OFF, self::WORK], true)) {
                continue;
            }
            $this->companyDays[$date] = ['kind' => $kind, 'name' => is_array($day) ? strval($day['name'] ?? '') : ''];
        }
        $this->weekend = array_values(array_unique(array_map('intval', $weekend)));
        $this->holidays = $holidays === 'jp' ? 'jp' : null;
    }

    public function isBusinessDay(string $date): bool
    {
        $date = static::toDate($date);
        $company = $this->companyDays[$date]['kind'] ?? null;
        if ($company === self::WORK) {
            return true;
        }
        if ($company === self::OFF) {
            return false;
        }
        if (in_array(static::weekday($date), $this->weekend, true)) {
            return false;
        }

        return $this->holidayName($date) === null;
    }

    /** The national holiday on a date, or null. */
    public function holidayName(string $date): ?string
    {
        if ($this->holidays !== 'jp') {
            return null;
        }

        return JapaneseHolidays::name(static::toDate($date));
    }

    /**
     * Why a date is not a business day, for the calendar: the company's name
     * of the day, or the holiday's, or '' for a weekend. Null on a business day.
     */
    public function dayOffLabel(string $date): ?string
    {
        $date = static::toDate($date);
        if ($this->isBusinessDay($date)) {
            return null;
        }
        $company = $this->companyDays[$date] ?? null;
        $holiday = $this->holidayName($date);
        if (isset($company)) {
            // a company day off on a holiday says both (夏季休暇・山の日)
            $names = array_values(array_filter([strval($company['name']), strval($holiday ?? '')], 'strlen'));

            return implode('・', array_unique($names));
        }

        return $holiday ?? '';
    }

    /**
     * The date n business days after (n < 0: before) a date. From a day that
     * is not a business day, the first step goes to the nearest business day
     * that way. n = 0 is the date itself.
     */
    public function addBusinessDays(string $date, int $n): string
    {
        $date = static::toDate($date);
        $step = $n >= 0 ? 1 : -1;
        $left = abs($n);
        $guard = 0;
        while ($left > 0) {
            $date = static::shiftDate($date, $step);
            if ($this->isBusinessDay($date)) {
                $left--;
                $guard = 0;
            } elseif (++$guard > self::SEARCH_LIMIT) {
                throw new \RuntimeException('BusinessCalendar: no business day within ' . self::SEARCH_LIMIT . ' days of ' . $date);
            }
        }

        return $date;
    }

    /**
     * A date that is not a business day, moved to the business day before or
     * after it, or kept. A business day is kept whatever the choice.
     */
    public function shift(string $date, string $how): string
    {
        $date = static::toDate($date);
        if ($how === self::KEEP || $this->isBusinessDay($date)) {
            return $date;
        }
        if (!in_array($how, self::SHIFTS, true)) {
            throw new \InvalidArgumentException('BusinessCalendar: unknown shift ' . $how);
        }

        return $this->addBusinessDays($date, $how === self::BEFORE ? -1 : 1);
    }

    /** The first (n = 1) or last (n = -1) business day of a month, and so on. */
    public function nthBusinessDayOfMonth(int $year, int $month, int $n): ?string
    {
        if ($n === 0) {
            return null;
        }
        $first = sprintf('%04d-%02d-01', $year, $month);
        $last = (new \DateTimeImmutable($first))->modify('last day of this month')->format('Y-m-d');
        $date = $n > 0 ? $first : $last;
        $count = 0;
        while (substr($date, 0, 7) === substr($first, 0, 7)) {
            if ($this->isBusinessDay($date) && ++$count === abs($n)) {
                return $date;
            }
            $date = static::shiftDate($date, $n > 0 ? 1 : -1);
        }

        return null;
    }

    /**
     * The days off and the company's working days of a range, for the
     * calendar.
     *
     * @return array<string, array{business: bool, label: string|null}>
     */
    public function range(string $from, string $to): array
    {
        $date = static::toDate($from);
        $to = static::toDate($to);
        $out = [];
        for ($i = 0; $date <= $to && $i <= 3 * self::SEARCH_LIMIT; $i++) {
            $business = $this->isBusinessDay($date);
            $company = $this->companyDays[$date] ?? null;
            if (!$business || isset($company)) {
                $out[$date] = ['business' => $business, 'label' => $business ? $company['name'] : $this->dayOffLabel($date)];
            }
            $date = static::shiftDate($date, 1);
        }

        return $out;
    }

    /** 'Y-m-d' from a date string or a DateTime, or null when it is not one. */
    public static function normalize($date): ?string
    {
        if ($date instanceof \DateTimeInterface) {
            return $date->format('Y-m-d');
        }
        $date = trim(strval($date));
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $date, $m) || !checkdate(intval($m[2]), intval($m[3]), intval($m[1]))) {
            return null;
        }

        return $m[1] . '-' . $m[2] . '-' . $m[3];
    }

    protected static function toDate($date): string
    {
        $normal = static::normalize($date);
        if ($normal === null) {
            throw new \InvalidArgumentException('BusinessCalendar: not a date: ' . strval($date));
        }

        return $normal;
    }

    protected static function weekday(string $date): int
    {
        return intval((new \DateTimeImmutable($date))->format('w'));
    }

    protected static function shiftDate(string $date, int $days): string
    {
        return (new \DateTimeImmutable($date))->modify(($days >= 0 ? '+' : '') . $days . ' day')->format('Y-m-d');
    }
}
