<?php

namespace App\Plugins\ExmentGroupware;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

/**
 * The ICS export address of a person's calendar (ISS02947, part 5): /gw-calendar/ics/{token}.ics
 *
 * A calendar program (Google Calendar, Outlook, the iPhone's) reads it without logging in, so it
 * cannot be a route of config.json (Exment gives those the admin login on purpose). It is added
 * here, from PluginPage's constructor while Exment boots, as ExmentITSupport adds its pages for
 * customers. The token in the address opens one person's calendar as somebody not logged in
 * sees it (Calendar\Feeds), and nothing else. An unknown or stopped token, a person deleted or
 * no longer allowed the feature, or the feature off: 404, all the same.
 *
 * No session (a calendar program has no cookies: a session a read would be thrown away), so not
 * Exment's "adminweb" group; Exment's maintenance mode (as its public pages) and, on Exment SaaS,
 * the tenant's expiry, when the site has them. Not behind the web IP filter of the admin pages
 * (Admin setting → IP filter): Google's or Microsoft's servers read the address from outside.
 * The token is the only key, so it is limited by address and by client IP.
 */
class CalendarFeed
{
    const PREFIX = 'gw-calendar';
    /** Reads of one address in a minute at most. */
    const PER_MINUTE = 30;
    /** Reads from one IP in a minute at most (any token). */
    const PER_IP_MINUTE = 120;

    /** @var bool */
    protected static $registered = false;

    public static function register(): void
    {
        if (static::$registered || app()->routesAreCached()) {
            return;
        }
        static::$registered = true;
        Route::group(['prefix' => static::PREFIX, 'middleware' => static::middleware()], function () {
            Route::get('ics/{token}.ics', static::class . '@show')->where('token', '[0-9a-f]{64}');
        });
    }

    /** @return array<int, string> the middleware of the route: only the ones this Exment has */
    protected static function middleware(): array
    {
        $out = [];
        try {
            $aliases = app('router')->getMiddleware();
        } catch (\Throwable $e) {
            return $out;
        }
        if (isset($aliases['exment.maintenance'])) {
            $out[] = 'exment.maintenance:public';
        }
        if (isset($aliases['admin.tenant-expired'])) {
            $out[] = 'admin.tenant-expired';
        }
        return $out;
    }

    public static function url(string $token): string
    {
        return url(static::PREFIX . '/ics/' . $token . '.ics');
    }

    /** @return \Symfony\Component\HttpFoundation\Response */
    public function show($token)
    {
        $notFound = response('Not Found', 404, ['Content-Type' => 'text/plain; charset=utf-8', 'X-Robots-Tag' => 'noindex', 'Cache-Control' => 'no-store']);
        // an address read by machines outside: never Laravel's debug page, whatever happens (even
        // an error the catch below cannot take, as out of memory, is then a plain error page)
        config(['app.debug' => false]);
        try {
            require_once __DIR__ . '/Calendar/Bootstrap.php';
            $token = strval($token);
            if (!Calendar\Feeds::validToken($token) || !Calendar\Feeds::ready()
                || !FeatureGate::allowedOnSite(FeatureGate::CALENDAR) || !FeatureGate::allowedOnSite(FeatureGate::CALENDAR_LINKS)) {
                return $notFound;
            }
            foreach (['gw_cal_feed_ip:' . sha1(strval(request()->ip())) => static::PER_IP_MINUTE, 'gw_cal_feed_rate:' . substr(Calendar\Feeds::hash($token), 0, 24) => static::PER_MINUTE] as $key => $max) {
                Cache::add($key, 0, now()->addMinute());
                if (intval(Cache::increment($key)) > $max) {
                    return response('Too Many Requests', 429, ['Content-Type' => 'text/plain; charset=utf-8', 'Retry-After' => '60']);
                }
            }
            $feed = Calendar\Feeds::find($token);
            // a person deleted (Exment's users are deleted softly) or no longer allowed: as unknown
            if ($feed === null || !Calendar\Links::userActive(intval($feed->user_id))
                || !FeatureGate::allowedFor(FeatureGate::CALENDAR_LINKS, intval($feed->user_id))) {
                return $notFound;
            }
            $busy = trans('exment_exment_groupware::groupware.cal.busy');
            $name = trans('exment_exment_groupware::groupware.cal.title');
            $body = Calendar\Feeds::render(
                $feed,
                is_string($busy) && strpos($busy, '::') === false ? $busy : 'Busy',
                is_string($name) && strpos($name, '::') === false ? $name : 'Calendar',
                strval(request()->getHost())
            );
            return response($body, 200, [
                'Content-Type' => 'text/calendar; charset=utf-8',
                'Content-Disposition' => 'inline; filename="calendar.ics"',
                'Cache-Control' => 'private, max-age=300',
                'X-Robots-Tag' => 'noindex',
                'Referrer-Policy' => 'no-referrer',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        } catch (\Throwable $e) {
            Log::warning('gw_calendar feed: ' . get_class($e));
            // not 404: a calendar program would empty the calendar
            return response('Service Unavailable', 503, ['Content-Type' => 'text/plain; charset=utf-8', 'Retry-After' => '300']);
        }
    }
}
