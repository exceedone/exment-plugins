<?php

namespace App\Plugins\ExmentPM;

use ExmentAdminCore\Admin\Layout\Content;
use Illuminate\Http\Request;

/**
 * ISS00432, stage 2b: the pages of the calendars.
 *
 *   GET  calendar                          the company's days off and working days (administrators)
 *   POST calendar                          add days (a range)
 *   POST calendar/delete                   remove one day
 *   GET  {id}/schedules/calendar           the dates of a project's schedules, a month or a year
 */
trait PortalCalendarPageTrait
{
    public function companyCalendar(Request $request, Content $content)
    {
        $this->companyCalendarAllowed();
        $year = max(2000, min(2100, intval($request->query('year', substr(PortalTime::today(), 0, 4)))));
        $title = strval(PortalDefinition::trans('cal.page_title'));
        $this->setPageInfo($title, $title, '', 'fa-calendar');

        return $this->AdminContent($content)->body(view(PortalDefinition::view('company-calendar'), [
            'year' => $year,
            'days' => PortalCalendarDays::ofYear($year),
            'holidays' => JapaneseHolidays::forYear($year),
            'base_url' => PortalDefinition::url('calendar'),
            'saved' => $request->query('saved') === '1',
            'error' => $request->query('error') === '1',
            'topbar' => $this->topbarData(null),
        ]));
    }

    public function companyCalendarSave(Request $request)
    {
        $this->companyCalendarAllowed();
        $n = PortalCalendarDays::add(strval($request->input('from')), strval($request->input('to')), strval($request->input('kind')), strval($request->input('name')), intval(\Exment::getUserId()));
        $year = substr(strval(BusinessCalendar::normalize($request->input('from')) ?? PortalTime::today()), 0, 4);

        return redirect(PortalDefinition::url('calendar') . '?year=' . $year . ($n === null ? '&error=1' : '&saved=1'));
    }

    public function companyCalendarDelete(Request $request)
    {
        $this->companyCalendarAllowed();
        PortalCalendarDays::remove(strval($request->input('date')));
        $year = substr(strval(BusinessCalendar::normalize($request->input('date')) ?? PortalTime::today()), 0, 4);

        return redirect(PortalDefinition::url('calendar') . '?year=' . $year . '&saved=1');
    }

    /** The company calendar: for administrators, while the schedules are on. */
    protected function companyCalendarAllowed(): void
    {
        if (!PortalSchedule::enabled() || !PortalCalendarDays::exists() || !static::topbarIsAdmin()) {
            abort(404);
        }
    }

    /**
     * The dates of every schedule of a project in a month (view=month, ym=2026-12) or a year
     * (view=year, ym=2026): the due date each makes, the days off shaded.
     */
    public function schedulesCalendar(Request $request, Content $content, $id)
    {
        $project = $this->scheduleProject($id);
        $view = $request->query('view') === 'year' ? 'year' : 'month';
        $today = PortalTime::today();
        $ym = strval($request->query('ym', substr($today, 0, $view === 'year' ? 4 : 7)));
        if ($view === 'year') {
            $y = preg_match('/^\d{4}$/', $ym) ? intval($ym) : intval(substr($today, 0, 4));
            $from = sprintf('%04d-01-01', $y);
            $to = sprintf('%04d-12-31', $y);
            $label = strval($y);
        } else {
            if (!preg_match('/^(\d{4})-(\d{2})$/', $ym, $m) || intval($m[2]) < 1 || intval($m[2]) > 12) {
                $ym = substr($today, 0, 7);
            }
            $from = $ym . '-01';
            $to = (new \DateTimeImmutable($from))->format('Y-m-t');
            $label = $ym;
        }
        $cal = PortalCalendarDays::calendar();
        $events = [];
        $names = [];
        foreach (PortalSchedule::ofProject(intval($project->id)) as $s) {
            if (!$s->enabled) {
                continue;
            }
            $names[intval($s->id)] = strval($s->name);
            // a date moved from just outside the month lands in it
            $wideFrom = (new \DateTimeImmutable($from))->modify('-31 day')->format('Y-m-d');
            $wideTo = (new \DateTimeImmutable($to))->modify('+31 day')->format('Y-m-d');
            foreach ((PortalSchedule::occurrences($s, $wideFrom, $wideTo, $cal) ?? []) as $orig => $due) {
                if ($due !== null && strcmp($due, $from) >= 0 && strcmp($due, $to) <= 0) {
                    $events[$due][] = ['id' => intval($s->id), 'name' => strval($s->name), 'moved' => $due !== $orig];
                }
            }
        }
        $off = [];
        for ($d = new \DateTimeImmutable($from); $d->format('Y-m-d') <= $to; $d = $d->modify('+1 day')) {
            $date = $d->format('Y-m-d');
            if (!$cal->isBusinessDay($date)) {
                $off[$date] = strval($cal->dayOffLabel($date) ?? '');
            }
        }
        $title = strval(PortalDefinition::trans('sched.cal_title'));
        $this->setPageInfo($title, $title, '', 'fa-calendar');

        return $this->AdminContent($content)->body(view(PortalDefinition::view('schedules-calendar'), [
            'project_name' => strval($project->getLabel()),
            'base_url' => PortalDefinition::url($project->id, 'schedules'),
            'view' => $view,
            'label' => $label,
            'from' => $from,
            'to' => $to,
            'today' => $today,
            'events' => $events,
            'off' => $off,
            'admin' => static::topbarIsAdmin(),
            'company_url' => PortalDefinition::url('calendar'),
            'topbar' => $this->topbarData(strval($project->id)),
        ]));
    }
}
