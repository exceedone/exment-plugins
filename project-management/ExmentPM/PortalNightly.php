<?php

namespace App\Plugins\ExmentPM;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The AI back-fill once a night (the plugin setting ai_nightly_hour, off at first): the batch Exment's
 * scheduler calls every 5 minutes (PluginBatch, no param1) also runs the fill of every project
 * once a day, in that hour of the server's clock. A day's run is kept (ran()) only after it got
 * through, so the next 5-minute run of the same hour does not run it again, and one that failed
 * is tried again within the hour.
 */
class PortalNightly
{
    /** The plugin option: '' (off) or the hour, 0 to 23. */
    const OPTION = 'ai_nightly_hour';

    /**
     * The hour the setting says, or null when it is off.
     *
     * @param mixed $setting
     */
    public static function hour($setting): ?int
    {
        if (!is_scalar($setting) || !preg_match('/\A\s*([0-9]|1[0-9]|2[0-3])\s*\z/', strval($setting), $m)) {
            return null;
        }

        return intval($m[1]);
    }

    /**
     * Whether this run does the night's fill: the setting is on, it is that hour now, and the fill
     * has not run today.
     *
     * @param mixed $setting
     */
    public static function due($setting, int $hourNow, string $today, ?string $lastDay): bool
    {
        $hour = static::hour($setting);

        return $hour !== null && $hour === $hourNow && $lastDay !== $today;
    }

    /** The day of the last night's fill that got through, or null. */
    public static function lastDay(): ?string
    {
        try {
            $day = Cache::get(static::key());

            return is_string($day) && $day !== '' ? $day : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function ran(string $day): void
    {
        try {
            Cache::forever(static::key(), $day);
        } catch (\Throwable $e) {
            Log::warning('ExmentPM nightly AI fill: ' . $e->getMessage());
        }
    }

    protected static function key(): string
    {
        return 'pmportal_ai_nightly:' . DB::connection()->getDatabaseName();
    }
}
