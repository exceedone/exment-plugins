<?php

namespace App\Plugins\ExmentGroupware\Calendar;

use App\Plugins\ExmentGroupware\CalendarFeed;
use App\Plugins\ExmentGroupware\Mail\HostGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The person's outside calendars in the calendar's settings (ISS02947, part 5), under
 * plugins/<uri>/cal/api (Feature management: exment_groupware.calendar_links):
 *
 *   GET  cal/api/links              the person's links (no address) and their ICS export address (whether there is one)
 *   POST cal/api/link               add one: name, url, show_as (busy | details)
 *   POST cal/api/link/{id}          change it (url empty: kept), enabled
 *   POST cal/api/link/{id}/delete   remove it and its events
 *   POST cal/api/link/{id}/sync     read it now (once a minute at most)
 *   POST cal/api/feed               a new ICS export address (the old one stops): shown this once
 *   POST cal/api/feed/revoke        stop it
 */
class LinksPage
{
    /** @var \Exceedone\Exment\Model\Plugin */
    protected $plugin;

    public function __construct($plugin)
    {
        $this->plugin = $plugin;
        HostGuard::allowPrivate(boolval($plugin->getCustomOption('mail_allow_private')));
    }

    protected function me(): int
    {
        return intval(\Exment::getUserId());
    }

    protected function t(string $key): string
    {
        $k = 'exment_exment_groupware::groupware.cal_links.' . $key;
        $v = trans($k);
        return is_string($v) && $v !== $k ? $v : $key;
    }

    /** @return \Symfony\Component\HttpFoundation\Response */
    protected function ok(array $data = [])
    {
        return response()->json(['ok' => true] + $data);
    }

    /** @return \Symfony\Component\HttpFoundation\Response */
    protected function fail(int $status, string $rule)
    {
        return response()->json(['ok' => false, 'message' => $this->t('err_' . $rule)], $status);
    }

    /** What the calendar page needs for its settings (null: not used). @return array<string, mixed>|null */
    public function cfg(): ?array
    {
        if (!Links::ready() || !Feeds::ready()) {
            return null;
        }
        $u = function (...$p) {
            return $this->plugin->getFullUrl(...array_merge(['cal', 'api'], $p));
        };
        return [
            'urls' => ['links' => $u('links'), 'link' => $u('link'), 'feed' => $u('feed'), 'feed_revoke' => $u('feed', 'revoke')],
            'lang' => trans('exment_exment_groupware::groupware.cal_links'),
            'max' => Links::MAX_PER_USER,
        ];
    }

    protected function notReady()
    {
        return (!Links::ready() || !Feeds::ready()) ? $this->fail(503, 'not_ready') : null;
    }

    public function links()
    {
        if ($r = $this->notReady()) {
            return $r;
        }
        return $this->ok(['links' => Links::list($this->me()), 'feed' => Feeds::state($this->me())]);
    }

    public function create(Request $request)
    {
        if ($r = $this->notReady()) {
            return $r;
        }
        $why = Links::create($this->me(), $request->only(['name', 'url', 'show_as', 'enabled']));
        if ($why !== null) {
            return $this->fail(422, $why);
        }
        $this->readSoon();
        return $this->ok(['links' => Links::list($this->me())]);
    }

    public function save(Request $request, $id)
    {
        if ($r = $this->notReady()) {
            return $r;
        }
        $why = Links::update($this->me(), intval($id), $request->only(['name', 'url', 'show_as', 'enabled']));
        if ($why !== null) {
            return $this->fail($why === 'link_not_found' ? 404 : 422, $why);
        }
        $this->readSoon();
        return $this->ok(['links' => Links::list($this->me())]);
    }

    public function delete($id)
    {
        if ($r = $this->notReady()) {
            return $r;
        }
        return Links::delete($this->me(), intval($id)) ? $this->ok(['links' => Links::list($this->me())]) : $this->fail(404, 'link_not_found');
    }

    /** Read one now (the person waits for it: at most the time limit of a fetch). */
    public function sync($id)
    {
        if ($r = $this->notReady()) {
            return $r;
        }
        $l = DB::table(Links::TABLE)->where('id', intval($id))->where('user_id', $this->me())->first();
        if (!isset($l)) {
            return $this->fail(404, 'link_not_found');
        }
        if (!boolval($l->enabled)) {
            return $this->fail(422, 'link_off');
        }
        if (!Cache::add('gw_cal_link_sync:' . intval($l->id), 1, now()->addMinute())) {
            return $this->fail(429, 'link_wait');
        }
        Links::sync($l);
        return $this->ok(['links' => Links::list($this->me())]);
    }

    /** A new link, or one changed: read after this answer (the page does not wait). */
    protected function readSoon(): void
    {
        Cache::forget('gw_cal_links_tick:' . DB::connection()->getDatabaseName());
        Links::afterResponse(boolval($this->plugin->getCustomOption('mail_allow_private')));
    }

    public function feedCreate()
    {
        if ($r = $this->notReady()) {
            return $r;
        }
        $token = Feeds::create($this->me());
        // the address is in this answer only
        return response()->json(['ok' => true, 'url' => CalendarFeed::url($token), 'feed' => Feeds::state($this->me())])
            ->header('Cache-Control', 'no-store');
    }

    public function feedRevoke()
    {
        if ($r = $this->notReady()) {
            return $r;
        }
        Feeds::revoke($this->me());
        return $this->ok(['feed' => null]);
    }
}
