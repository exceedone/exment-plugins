<?php

namespace App\Plugins\ExmentPM;

use ExmentAdminCore\Admin\Layout\Content;
use Illuminate\Http\Request;

/**
 * The reminders of a project, its own page (ISS00432, stage 1b):
 *
 *   GET  {id}/reminder   the form
 *   POST {id}/reminder   save it
 *
 * For the project's manager, and only while the reminders are on (Feature management); otherwise
 * the page is not there (404). A webhook URL is never sent to the screen: a kept one shows masked
 * and an empty input keeps it.
 */
trait PortalReminderPageTrait
{
    public function reminderPage(Request $request, Content $content, $id)
    {
        $project = $this->reminderProject($id);
        $rule = PortalReminderRules::forProject(intval($project->id));
        $site = PortalReminder::settings();
        // the channels are on the project's settings, 「チャットへのお知らせ」 (PortalChannels)
        $channels = PortalChannels::forEvent(intval($project->id), 'reminder');
        $title = strval(PortalDefinition::trans('remind.page_title'));
        $this->setPageInfo($title, $title, '', 'fa-bell-o');

        return $this->AdminContent($content)->body(view(PortalDefinition::view('reminder'), [
            'project_name' => strval($project->getLabel()),
            'project_url' => PortalDefinition::projectUrl($project),
            'save_url' => PortalDefinition::url($project->id, 'reminder'),
            'enabled' => $rule === null ? true : $rule['enabled'],
            'use_site' => $rule === null ? true : $rule['use_site'],
            'settings' => $rule['settings'] ?? $site,
            'site' => $site,
            'channel_count' => count($channels),
            'channels_url' => PortalDefinition::projectUrl($project) . '#settings',
            'saved' => $request->query('saved') === '1',
            'errors_list' => (array) session('pm_reminder_errors', []),
            'topbar' => $this->topbarData(strval($project->id)),
        ]));
    }

    public function reminderSave(Request $request, $id)
    {
        $project = $this->reminderProject($id);
        $input = [
            'enabled' => $request->boolean('enabled'),
            'use_site' => $request->input('mode') !== 'own',
            'settings' => [
                'days' => strval($request->input('days', '')),
                'business' => $request->boolean('business') ? '1' : '0',
                'today' => $request->boolean('today') ? '1' : '0',
                'overdue' => $request->boolean('overdue') ? '1' : '0',
                'overdue_max' => strval($request->input('overdue_max', '14')),
                'time' => strval($request->input('time', '09:00')),
                'to' => (array) $request->input('to', []),
            ],
        ];
        $errors = PortalReminderRules::save(intval($project->id), $input, intval(\Exment::getUserId()));
        if (!empty($errors)) {
            return redirect(PortalDefinition::url($project->id, 'reminder'))->with('pm_reminder_errors', array_values(array_unique($errors)));
        }

        return redirect(PortalDefinition::url($project->id, 'reminder') . '?saved=1');
    }

    /** The project, when its manager asks and the reminders are on; 404 otherwise. */
    protected function reminderProject($id)
    {
        if (!PortalReminder::enabled() || !PortalReminderRules::exists()) {
            abort(404);
        }
        $project = $this->findProject($id);
        if (!PortalMembership::canManage($project)) {
            abort(404);
        }

        return $project;
    }
}
