<?php

namespace App\Plugins\ExmentGroupware\Calendar;

use Illuminate\Support\Facades\DB;

/**
 * The days off the calendar shows (ISS02947): Japan's national holidays, and the company's own days
 * off and working days, read as data from ExmentPM's table pm_portal_calendar_days when the site
 * has it (the same list as ExmentPM's business calendar; nothing of ExmentPM's code).
 */
class Holidays
{
    const COMPANY = 'pm_portal_calendar_days';

    /**
     * @return array<string, array{name: string, kind: string}> 'Y-m-d' => name, kind (holiday | off | work)
     */
    public static function between(string $fromDay, string $toDay): array
    {
        $out = [];
        $y0 = intval(substr($fromDay, 0, 4));
        $y1 = intval(substr($toDay, 0, 4));
        for ($y = max(2020, $y0); $y <= min(2099, $y1); $y++) {
            foreach (JapaneseHolidays::forYear($y) as $date => $name) {
                if (strcmp($date, $fromDay) >= 0 && strcmp($date, $toDay) <= 0) {
                    $out[$date] = ['name' => strval($name), 'kind' => 'holiday'];
                }
            }
        }
        try {
            if (\Schema::hasTable(static::COMPANY)) {
                foreach (DB::table(static::COMPANY)->whereBetween('date', [$fromDay, $toDay])->get() as $d) {
                    $day = substr(strval($d->date), 0, 10);
                    $out[$day] = ['name' => strval($d->name ?? ''), 'kind' => strval($d->kind) === 'work' ? 'work' : 'off'];
                }
            }
        } catch (\Throwable $e) {
            // the national holidays only
        }
        ksort($out);
        return $out;
    }
}
