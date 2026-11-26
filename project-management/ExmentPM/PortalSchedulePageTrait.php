<?php

namespace App\Plugins\ExmentPM;

use ExmentAdminCore\Admin\Layout\Content;
use Illuminate\Http\Request;

/**
 * The recurring schedules of a project, their own page (ISS00432, stage 2a):
 *
 *   GET  {id}/schedules                      the list, the form (?edit=<id> or ?new=1)
 *   POST {id}/schedules                      save one (schedule_id empty: a new one)
 *   POST {id}/schedules/{sid}/delete         delete one
 *   POST {id}/schedules/{sid}/exception      move one date, skip it, or put it back
 *
 * For the project's manager, while schedules are on (Feature management); 404 otherwise.
 */
trait PortalSchedulePageTrait
{
    public function schedulesPage(Request $request, Content $content, $id)
    {
        $project = $this->scheduleProject($id);
        $pid = intval($project->id);
        $cal = PortalCalendarDays::calendar();
        $today = PortalTime::today();
        $far = (new \DateTimeImmutable($today))->modify('+2 year')->format('Y-m-d');
        $list = [];
        foreach (PortalSchedule::ofProject($pid) as $s) {
            $ex = PortalSchedule::exceptions(intval($s->id));
            $occ = PortalSchedule::occurrences($s, $today, $far, $cal, $ex) ?? [];
            $items = PortalSchedule::items(intval($s->id));
            $next = [];
            foreach (array_slice($occ, 0, 10, true) as $orig => $due) {
                $next[] = ['orig' => $orig, 'due' => $due, 'moved' => array_key_exists($orig, $ex), 'issue' => $items[$orig] ?? null];
            }
            $list[] = ['row' => $s, 'next' => $next, 'template' => $this->scheduleIssueLabel($project, intval($s->template_issue_id))];
        }
        $edit = null;
        if ($request->query('edit')) {
            $edit = PortalSchedule::find($pid, intval($request->query('edit')));
        }
        $title = strval(PortalDefinition::trans('sched.page_title'));
        $this->setPageInfo($title, $title, '', 'fa-repeat');

        return $this->AdminContent($content)->body(view(PortalDefinition::view('schedules'), [
            'project_name' => strval($project->getLabel()),
            'project_url' => PortalDefinition::projectUrl($project),
            'base_url' => PortalDefinition::url($project->id, 'schedules'),
            'list' => $list,
            'edit' => $edit,
            'form' => $edit ? (json_decode(strval($edit->form ?? ''), true) ?: []) : [],
            'split' => $edit !== null ? BusinessCalendar::normalize($request->query('split')) : null,
            'calendar_url' => PortalDefinition::url($project->id, 'schedules', 'calendar'),
            'company_url' => static::topbarIsAdmin() && PortalCalendarDays::exists() ? PortalDefinition::url('calendar') : null,
            'show_form' => $edit !== null || $request->query('new') === '1' || !empty(session('pm_schedule_errors')),
            'issues' => $this->scheduleTemplates($project),
            'today' => $today,
            'saved' => $request->query('saved') === '1',
            'errors_list' => (array) session('pm_schedule_errors', []),
            'old' => (array) session('pm_schedule_old', []),
            'topbar' => $this->topbarData(strval($project->id)),
        ]));
    }

    public function schedulesSave(Request $request, $id)
    {
        $project = $this->scheduleProject($id);
        $sid = $request->input('schedule_id');
        $f = $request->only(['name', 'template', 'kind', 'interval', 'by', 'monthday', 'nth', 'weekday', 'month', 'weekdays', 'rrule', 'start_date', 'end_date', 'rdates', 'shift', 'lead_days', 'enabled']);
        $split = BusinessCalendar::normalize($request->input('split_from'));
        if ($split !== null && ctype_digit(strval($sid))) {
            // ISS00432 2b: from this date on
            [$saved, $errors] = PortalSchedule::split(intval($project->id), intval($sid), $split, $f, intval(\Exment::getUserId()), $this->issue_table);
        } else {
            [$saved, $errors] = PortalSchedule::save(intval($project->id), ctype_digit(strval($sid)) ? intval($sid) : null, $f, intval(\Exment::getUserId()), $this->issue_table);
        }
        $base = PortalDefinition::url($project->id, 'schedules');
        if (!empty($errors)) {
            return redirect($base . (ctype_digit(strval($sid)) ? '?edit=' . intval($sid) . ($split !== null ? '&split=' . $split : '') : '?new=1'))
                ->with('pm_schedule_errors', $errors)->with('pm_schedule_old', $f);
        }

        return redirect($base . '?saved=1');
    }

    public function schedulesDelete(Request $request, $id, $sid)
    {
        $project = $this->scheduleProject($id);
        PortalSchedule::delete(intval($project->id), intval($sid));

        return redirect(PortalDefinition::url($project->id, 'schedules') . '?saved=1');
    }

    public function schedulesException(Request $request, $id, $sid)
    {
        $project = $this->scheduleProject($id);
        $s = PortalSchedule::find(intval($project->id), intval($sid));
        $orig = BusinessCalendar::normalize($request->input('orig'));
        if (!isset($s) || $orig === null) {
            abort(404);
        }
        $do = strval($request->input('do'));
        $new = BusinessCalendar::normalize($request->input('new_date'));
        if ($do === 'move' && $new !== null) {
            PortalSchedule::setException(intval($s->id), $orig, $new);
        } elseif ($do === 'skip') {
            PortalSchedule::setException(intval($s->id), $orig, null);
        } elseif ($do === 'reset') {
            PortalSchedule::setException(intval($s->id), $orig, null, true);
        }

        return redirect(PortalDefinition::url($project->id, 'schedules') . '?saved=1');
    }

    protected function scheduleProject($id)
    {
        if (!PortalSchedule::enabled()) {
            abort(404);
        }
        $project = $this->findProject($id);
        if (!PortalMembership::canManage($project)) {
            abort(404);
        }

        return $project;
    }

    /** @return array<int, array{id: int, l: string}> the issues a schedule may copy, newest first */
    protected function scheduleTemplates($project): array
    {
        $out = [];
        foreach ($this->issue_table->getValueModel()->query()
            ->where('parent_type', $this->project_table->table_name)->where('parent_id', $project->id)
            ->orderBy('id', 'desc')->take(300)->get() as $issue) {
            $out[] = ['id' => intval($issue->id), 'l' => $this->issueKey($project, $issue->id) . ' ' . strval(array_get($issue->value, array_get($this->def['issue'], 'subject'), ''))];
        }

        return $out;
    }

    protected function scheduleIssueLabel($project, int $issueId): string
    {
        $issue = $this->issue_table->getValueModel()->query()->find($issueId);

        return isset($issue) ? $this->issueKey($project, $issueId) . ' ' . strval(array_get($issue->value, array_get($this->def['issue'], 'subject'), '')) : '—';
    }
}
