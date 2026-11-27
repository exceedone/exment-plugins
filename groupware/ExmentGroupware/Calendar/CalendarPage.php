<?php

namespace App\Plugins\ExmentGroupware\Calendar;

use App\Plugins\ExmentGroupware\FeatureGate;
use Exceedone\Exment\Enums\SystemTableName;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * The calendar's page and its API (ISS02947), under plugins/<uri>/cal:
 *
 *   GET  cal                          the page
 *   GET  cal/api/events               from, to (UTC), people (user ids): the occurrences, as the viewer may see them
 *   GET  cal/api/event/{id}           one event in full (404 when the viewer may see only 「予定あり」)
 *   POST cal/api/event                make one
 *   POST cal/api/event/{id}/save      change it: scope=all, or scope=one with original (one occurrence)
 *   POST cal/api/event/{id}/delete    scope=all, or scope=one with original
 *   POST cal/api/event/{id}/reply     response = yes | no | maybe | none
 *   GET  cal/api/holidays             from, to ('Y-m-d')
 *   GET  cal/api/people               q: users (for attendees and the people side by side)
 *   GET  cal/api/pm                   from, to ('Y-m-d', to the day after), filter, project: ExmentPM's issues,
 *                                     milestones and the dates of its schedules (part 4, read only)
 */
class CalendarPage
{
    /** @var \Exceedone\Exment\Model\Plugin */
    protected $plugin;

    public function __construct($plugin)
    {
        $this->plugin = $plugin;
    }

    protected function url(...$parts): string
    {
        return $this->plugin->getFullUrl(...array_merge(['cal'], $parts));
    }

    protected function me(): int
    {
        return intval(\Exment::getUserId());
    }

    protected function t(string $key, array $args = []): string
    {
        $k = 'exment_exment_groupware::groupware.cal.' . $key;
        $v = trans($k, $args);
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

    /** The reminders whose time has come, after the answer of this request (part 2). */
    protected function remind(): void
    {
        Reminders::afterResponse($this->url());
        // the outside calendars whose time has come (part 5), while that feature is on
        if (FeatureGate::allowedOnSite(FeatureGate::CALENDAR_LINKS)) {
            Links::afterResponse(boolval($this->plugin->getCustomOption('calendar_allow_private')));
        }
    }

    public function page()
    {
        if (!Events::ready()) {
            return response($this->t('not_ready'), 503);
        }
        $this->remind();
        // the settings of the outside calendars and the ICS export (part 5), for the person allowed them
        view()->share('gwcalLinks', FeatureGate::allowed(FeatureGate::CALENDAR_LINKS) ? (new LinksPage($this->plugin))->cfg() : null);
        $me = $this->me();
        $orgs = [];
        foreach (Events::orgsOf($me) as $id) {
            $org = getModelName(SystemTableName::ORGANIZATION)::withoutGlobalScopes()->find($id);
            if (isset($org)) {
                $orgs[] = ['id' => $id, 'name' => strval($org->getLabel())];
            }
        }
        $user = \Exment::user();
        $pm = PmLayer::ready();
        $view = view('exment_exment_groupware::calendar', [
            'cfg' => [
                'me' => $me,
                'me_name' => isset($user, $user->base_user) ? strval($user->base_user->getLabel()) : '',
                'orgs' => $orgs,
                'csrf' => csrf_token(),
                'tz' => strval(config('app.timezone') ?: 'Asia/Tokyo'),
                'urls' => [
                    'events' => $this->url('api', 'events'),
                    'event' => $this->url('api', 'event'),
                    'holidays' => $this->url('api', 'holidays'),
                    'people' => $this->url('api', 'people'),
                    'resources' => $this->url('api', 'resources'),
                    'resource' => $this->url('api', 'resource'),
                    'resource_events' => $this->url('api', 'resources', 'events'),
                    // the video call of an event (ISS02946), while that feature is on
                    'call' => FeatureGate::allowed('exment_groupware.call') ? $this->plugin->getFullUrl('chat', 'call', 'new') : null,
                    'pm' => $pm ? $this->url('api', 'pm') : null,
                ],
                'lang' => trans('exment_exment_groupware::groupware.cal'),
                'reminders' => Reminders::ready() ? Reminders::CHOICES : [],
                // the rooms and equipment (part 3), and whether the viewer keeps their list
                'resources' => Resources::all(true),
                'resources_admin' => Resources::ready() && $this->isAdmin(),
                // ExmentPM's layer (part 4): the projects the viewer may read, when the site has ExmentPM
                'pm' => $pm ? ['projects' => array_map(function ($p) {
                    return ['id' => $p['id'], 'name' => $p['name'], 'color' => $p['color'], 'mine' => $p['mine']];
                }, PmLayer::projects($me))] : null,
            ],
        ]);
        foreach (['ExmentAdminCore\Admin\Layout\Content', 'Encore\Admin\Layout\Content'] as $class) {
            if (class_exists($class)) {
                $content = new $class();
                $content->row($view->render());
                return response($content->header($this->t('title'))->headericon('fa-calendar'));
            }
        }
        return $view;
    }

    /** A UTC time from the query, or null. */
    protected static function utc($v): ?string
    {
        try {
            return (new \DateTimeImmutable(strval($v), new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function events(Request $request)
    {
        $from = static::utc($request->query('from'));
        $to = static::utc($request->query('to'));
        if ($from === null || $to === null || strcmp($to, $from) <= 0
            || (strtotime($to) - strtotime($from)) > Events::MAX_WINDOW_DAYS * 86400) {
            return $this->fail(422, 'window');
        }
        $people = array_filter(array_map('intval', explode(',', strval($request->query('people', strval($this->me()))))));
        $this->remind();

        return $this->ok(['events' => Events::window($from, $to, $people, $this->me(), $this->t('busy'))]);
    }

    /** GET cal/api/pm: ExmentPM on the calendar (part 4), as the viewer may read it. */
    public function pm(Request $request)
    {
        if (!PmLayer::ready()) {
            return $this->ok(['events' => []]);
        }
        $from = Rrule::day($request->query('from'));
        $to = Rrule::day($request->query('to'));
        if ($from === null || $to === null || strcmp($to, $from) <= 0 || (strtotime($to) - strtotime($from)) > PmLayer::MAX_WINDOW_DAYS * 86400) {
            return $this->fail(422, 'window');
        }
        $filter = strval($request->query('filter', 'mine'));
        if (!in_array($filter, PmLayer::FILTERS, true)) {
            $filter = 'mine';
        }
        $project = $filter === 'project' ? intval($request->query('project')) : null;
        $tz = new \DateTimeZone(strval(config('app.timezone') ?: 'Asia/Tokyo'));
        $today = (new \DateTimeImmutable('now', $tz))->format('Y-m-d');

        return $this->ok(['events' => PmLayer::window($this->me(), $from, $to, $filter, $project, $today, $this->isAdmin())]);
    }

    public function event($id)
    {
        $e = Events::get(intval($id), $this->me());
        return $e === null ? $this->fail(404, 'not_found') : $this->ok(['event' => $e]);
    }

    /**
     * The rooms of a write, checked, and their clashes (part 3): null when it may go on, else the
     * answer (422 a room not on the list; 409 a clash to confirm; 422 a clash the site refuses).
     *
     * @param array<string, mixed> $f
     * @return \Symfony\Component\HttpFoundation\Response|null
     */
    protected function roomsRefused(Request $request, ?int $id, array &$f)
    {
        if (!Resources::ready() || $f['resources'] === null) {
            $f['resources'] = null;
            return null;
        }
        $rooms = Resources::checked($f['resources'], $id !== null ? Resources::of($id) : []);
        if ($rooms === null) {
            return $this->fail(422, 'resources');
        }
        $f['resources'] = $rooms;
        return $this->clashAnswer($request, Resources::clashes($id, $f, $rooms));
    }

    /**
     * @param array<int, array<string, mixed>> $clashes
     * @return \Symfony\Component\HttpFoundation\Response|null
     */
    protected function clashAnswer(Request $request, array $clashes)
    {
        if (empty($clashes)) {
            return null;
        }
        if (strval($this->plugin->getCustomOption('cal_resource_clash')) === 'block') {
            return response()->json(['ok' => false, 'message' => $this->t('err_clash_block'), 'clashes' => $clashes], 422);
        }
        if (!$request->boolean('ignore_clash')) {
            return response()->json(['ok' => false, 'clash' => true, 'message' => $this->t('clash_ask'), 'clashes' => $clashes], 409);
        }
        return null;
    }

    public function create(Request $request)
    {
        [$f, $why] = Events::fields($request->all(), Events::orgsOf($this->me()));
        if ($f === null) {
            return $this->fail(422, $why);
        }
        $refused = $this->roomsRefused($request, null, $f);
        if ($refused !== null) {
            return $refused;
        }
        $id = Events::save(null, $f, $this->me());
        return $this->ok(['id' => $id]);
    }

    /** GET cal/api/resources: the rooms and equipment, and whether the viewer keeps the list. */
    public function resources()
    {
        $admin = $this->isAdmin();
        return $this->ok(['resources' => Resources::all($admin), 'admin' => $admin]);
    }

    /** POST cal/api/resource[/{id}]: add or change one (a system administrator). */
    public function saveResource(Request $request, $id = null)
    {
        if (!$this->isAdmin()) {
            return $this->fail(403, 'not_admin');
        }
        $why = Resources::save($id === null ? null : intval($id), $request->all());
        return $why === null ? $this->ok(['resources' => Resources::all(true)]) : $this->fail(422, $why);
    }

    /** GET cal/api/resources/events: from, to, ids: the events of the rooms (the rooms side by side). */
    public function resourceEvents(Request $request)
    {
        $from = static::utc($request->query('from'));
        $to = static::utc($request->query('to'));
        if ($from === null || $to === null || strcmp($to, $from) <= 0 || (strtotime($to) - strtotime($from)) > Events::MAX_WINDOW_DAYS * 86400) {
            return $this->fail(422, 'window');
        }
        $ids = array_filter(array_map('intval', explode(',', strval($request->query('ids', '')))));
        return $this->ok(['events' => Events::forResources($from, $to, array_slice($ids, 0, 30), $this->me(), $this->t('busy'))]);
    }

    protected function isAdmin(): bool
    {
        $user = \Exment::user();
        return isset($user) && $user->hasPermission(\Exceedone\Exment\Enums\Permission::SYSTEM);
    }

    public function save(Request $request, $id)
    {
        $id = intval($id);
        if (strval($request->input('scope')) === 'one') {
            $original = static::utc($request->input('original'));
            [$f, $why] = Events::fields($request->all(), Events::orgsOf($this->me()));
            if ($original === null || $f === null) {
                return $this->fail(422, $why ?? 'time');
            }
            // one occurrence moved: its new time against the other bookings of its rooms
            $rooms = Resources::ready() ? Resources::of($id) : [];
            if (!empty($rooms)) {
                $one = ['starts_at' => $f['starts_at'], 'ends_at' => $f['ends_at'], 'all_day' => $f['all_day'], 'tz' => $f['tz'], 'rrule' => null, 'exdates' => null];
                $refused = $this->clashAnswer($request, Resources::clashes($id, $one, $rooms));
                if ($refused !== null) {
                    return $refused;
                }
            }
            $ok = Events::override($id, $original, ['starts_at' => $f['starts_at'], 'ends_at' => $f['ends_at'], 'title' => $f['title'], 'location' => $f['location']], $this->me());
            return $ok ? $this->ok(['id' => $id]) : $this->fail(403, 'not_owner');
        }
        [$f, $why] = Events::fields($request->all(), Events::orgsOf($this->me()));
        if ($f === null) {
            return $this->fail(422, $why);
        }
        $refused = $this->roomsRefused($request, $id, $f);
        if ($refused !== null) {
            return $refused;
        }
        return Events::save($id, $f, $this->me()) === null ? $this->fail(403, 'not_owner') : $this->ok(['id' => $id]);
    }

    public function delete(Request $request, $id)
    {
        $id = intval($id);
        if (strval($request->input('scope')) === 'one') {
            $original = static::utc($request->input('original'));
            if ($original === null) {
                return $this->fail(422, 'time');
            }
            return Events::override($id, $original, null, $this->me()) ? $this->ok() : $this->fail(403, 'not_owner');
        }
        return Events::delete($id, $this->me()) ? $this->ok() : $this->fail(403, 'not_owner');
    }

    public function reply(Request $request, $id)
    {
        return Events::reply(intval($id), $this->me(), strval($request->input('response'))) ? $this->ok() : $this->fail(403, 'not_attendee');
    }

    public function holidays(Request $request)
    {
        $from = Rrule::day($request->query('from'));
        $to = Rrule::day($request->query('to'));
        if ($from === null || $to === null || strcmp($to, $from) < 0) {
            return $this->fail(422, 'window');
        }
        return $this->ok(['days' => Holidays::between($from, $to)]);
    }

    public function people(Request $request)
    {
        $q = trim(mb_substr(strval($request->query('q', '')), 0, 50));
        $ids = array_filter(array_map('intval', explode(',', strval($request->query('ids', '')))));
        $model = getModelName(SystemTableName::USER);
        try {
            $query = $model::query();
            if (!empty($ids)) {
                $query->whereIn('id', array_slice($ids, 0, Events::MAX_PEOPLE));
            } elseif ($q !== '') {
                $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $q) . '%';
                $query->where(function ($w) use ($like) {
                    $w->where('value->user_name', 'like', $like)->orWhere('value->user_code', 'like', $like)->orWhere('value->email', 'like', $like);
                });
            }
            $out = [];
            foreach ($query->orderBy('id')->limit(30)->get() as $u) {
                $out[] = ['id' => intval($u->id), 'name' => strval($u->getLabel())];
            }
            return $this->ok(['people' => $out]);
        } catch (\Throwable $e) {
            Log::warning('gw_calendar people: ' . get_class($e));
            return $this->ok(['people' => []]);
        }
    }
}
