<?php

namespace App\Plugins\ExmentPM;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The company's days off and working days (休日出勤) of the site (ISS00432, stage 2b), one list
 * for every project, kept in pm_portal_calendar_days and read only through calendar(): whatever
 * counts business days (the recurring schedules, the reminders) asks for a BusinessCalendar and
 * never reads the table.
 *
 * When Exment gets a business-day calendar of its own (IT Support's SLA keeps its own list of
 * holidays today), calendar() reads that one instead, and nothing that uses it changes.
 */
class PortalCalendarDays
{
    const TABLE = 'pm_portal_calendar_days';

    /** Days one save adds at most (a range). */
    const MAX_RANGE = 62;

    /** @var BusinessCalendar|null read once per request */
    protected static $calendar = null;

    public static function exists(): bool
    {
        return \Schema::hasTable(static::TABLE);
    }

    /** The calendar of the site: weekends, the holidays of Japan, the company's days. */
    public static function calendar(): BusinessCalendar
    {
        if (static::$calendar !== null) {
            return static::$calendar;
        }
        $days = [];
        try {
            if (static::exists()) {
                foreach (DB::table(static::TABLE)->get() as $d) {
                    $days[strval($d->date)] = ['kind' => strval($d->kind), 'name' => strval($d->name ?? '')];
                }
            }
        } catch (\Throwable $e) {
            Log::warning('pm_portal: the company days could not be read: ' . $e->getMessage());
        }

        return static::$calendar = new BusinessCalendar($days);
    }

    public static function forget(): void
    {
        static::$calendar = null;
    }

    /** @return array<int, object> the company's days of a year */
    public static function ofYear(int $year): array
    {
        if (!static::exists()) {
            return [];
        }
        return DB::table(static::TABLE)->whereBetween('date', [$year . '-01-01', $year . '-12-31'])->orderBy('date')->get()->all();
    }

    /**
     * Add the days from $from to $to (a day off, or a working day), replacing what those days
     * were. Returns how many, or null for a range that is not one.
     */
    public static function add(string $from, ?string $to, string $kind, string $name, int $userId): ?int
    {
        $from = BusinessCalendar::normalize($from);
        $to = BusinessCalendar::normalize($to ?: $from);
        if ($from === null || $to === null || strcmp($to, $from) < 0 || !in_array($kind, [BusinessCalendar::OFF, BusinessCalendar::WORK], true)) {
            return null;
        }
        $dates = [];
        for ($d = new \DateTimeImmutable($from); $d->format('Y-m-d') <= $to; $d = $d->modify('+1 day')) {
            $dates[] = $d->format('Y-m-d');
            if (count($dates) > static::MAX_RANGE) {
                return null;
            }
        }
        $now = now();
        $name = mb_substr(trim($name), 0, 50);
        DB::transaction(function () use ($dates, $kind, $name, $userId, $now) {
            DB::table(static::TABLE)->whereIn('date', $dates)->delete();
            DB::table(static::TABLE)->insert(array_map(function ($date) use ($kind, $name, $userId, $now) {
                return ['date' => $date, 'kind' => $kind, 'name' => $name, 'updated_user_id' => $userId, 'created_at' => $now, 'updated_at' => $now];
            }, $dates));
        });
        static::forget();

        return count($dates);
    }

    public static function remove(string $date): void
    {
        $date = BusinessCalendar::normalize($date);
        if ($date !== null && static::exists()) {
            DB::table(static::TABLE)->where('date', $date)->delete();
            static::forget();
        }
    }
}
