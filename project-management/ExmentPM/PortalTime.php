<?php

namespace App\Plugins\ExmentPM;

use Carbon\Carbon;

/**
 * The times of the portal in the time zone of the user.
 *
 * The database keeps every date and time in the time zone of the site, and
 * Exment 7.2.4 shows them in the time zone each user chose
 * (\Exceedone\Exment\Services\UserTimezone). The portal prints its own times,
 * so it asks the same service - here, in one place. On a core without the
 * service, or for a user in the time zone of the site, every method answers
 * exactly what the portal printed before.
 *
 * Only a date and time is converted. A date alone (start, due) and a time
 * alone (due time) say what the user typed and are left as they are.
 */
class PortalTime
{
    /** The core's service, when this core has it. */
    protected const SERVICE = '\Exceedone\Exment\Services\UserTimezone';

    /**
     * Whether the times shown differ from the times stored.
     */
    public static function differs(): bool
    {
        $service = static::SERVICE;

        return class_exists($service) && boolval($service::differs());
    }

    /**
     * The time zone the times are shown in when it is not the site's, else ''.
     */
    public static function zone(): string
    {
        if (!static::differs()) {
            return '';
        }
        $service = static::SERVICE;

        return strval($service::user());
    }

    /**
     * A date and time of the database, formatted for the screen.
     *
     * @param mixed $value string, DateTimeInterface or null
     * @param string $format
     * @return string '' for nothing, the value as text when it is not a time
     */
    public static function format($value, string $format): string
    {
        if (is_nullorempty($value)) {
            return '';
        }
        try {
            if (static::differs()) {
                $service = static::SERVICE;
                $shown = $service::toUser($value);
                if ($shown) {
                    return $shown->format($format);
                }
            }

            return Carbon::parse($value)->format($format);
        } catch (\Throwable $e) {
            return is_scalar($value) ? strval($value) : '';
        }
    }

    /**
     * A date and time of the database as the user reads it, in the format of
     * the database (a value of a form). The value as it is when nothing converts.
     *
     * @param mixed $value
     * @return mixed
     */
    public static function toUser($value)
    {
        if (!static::differs() || is_nullorempty($value) || !is_scalar($value)) {
            return $value;
        }
        $service = static::SERVICE;

        return $service::formatToUser($value);
    }

    /**
     * A date and time the user typed, as the database keeps it. The value as
     * it is when nothing converts.
     *
     * @param mixed $value
     * @return mixed
     */
    public static function fromUser($value)
    {
        if (!static::differs() || is_nullorempty($value) || !is_scalar($value)) {
            return $value;
        }
        $service = static::SERVICE;

        return $service::formatFromUser($value);
    }

    /**
     * Now, as the user's clock shows it.
     */
    public static function now(): Carbon
    {
        if (!static::differs()) {
            return Carbon::now();
        }
        $service = static::SERVICE;

        return Carbon::now($service::user());
    }

    /**
     * Today of the user (Y-m-d): what a due date is late or soon against.
     */
    public static function today(): string
    {
        return static::now()->format('Y-m-d');
    }

    /**
     * The start of a day of the user (Y-m-d), as a time of the database: for
     * a query on a date and time column ("since the start of this week").
     */
    public static function dayStartForQuery(string $day): string
    {
        if (!static::differs()) {
            return Carbon::parse($day)->format('Y-m-d H:i:s');
        }
        $service = static::SERVICE;

        return strval($service::dayStartForQuery($day));
    }
}
