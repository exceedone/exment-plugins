<?php

namespace App\Plugins\ExmentPM;

use ExmentAdminCore\Admin\Layout\Content;
use Illuminate\Http\Request;

/**
 * The page where each person decides how the notices of the portal reach them (ISS02863,
 * ISS02931): first the one choice of the 「通知」 menu (bell / mail / Teams / both), and folded
 * under it the details - per event, projects muted, quiet hours.
 *
 *   GET  notify-settings   the page
 *   POST notify-settings   save it
 *
 * Every user, for themselves only. Read through PortalNotifyPrefs::channelsFor().
 */
trait PortalNotifySettingsTrait
{
    public function notifySettings(Request $request, Content $content)
    {
        $user = \Exment::user();
        if (!isset($user)) {
            abort(403);
        }
        $prefs = $user->getSettingValue(PortalNotifyPrefs::SETTING, []);
        $title = strval(PortalDefinition::trans('nset.title'));
        $this->setPageInfo($title, $title, '', 'fa-sliders');
        $cards = $this->topbarCards();

        return $this->AdminContent($content)->body(view(PortalDefinition::view('notify-settings'), [
            'choice' => PortalTeams::choiceOf($user),
            'choices' => PortalTeams::teamsAvailable() ? PortalTeams::CHOICES : ['bell', 'mail'],
            'teams' => PortalTeams::teamsAvailable(),
            'prefs' => is_array($prefs) ? $prefs : [],
            // the morning digest's row only while the site has it (ISS02939)
            'events' => array_values(array_filter(PortalNotifyPrefs::OFFERED, function ($e) {
                return $e !== 'digest' || PortalDigest::enabled();
            })),
            'digest' => PortalDigest::enabled() ? PortalDigest::personal($prefs) : null,
            'projects' => array_values(array_filter(array_map(function ($c) {
                return empty($c['archived']) ? ['id' => strval($c['id']), 'name' => $c['name'], 'key' => $c['key']] : null;
            }, $cards))),
            'save_url' => PortalDefinition::url('notify-settings'),
            'saved' => $request->query('saved') === '1',
            'topbar' => $this->topbarData(null, $cards),
        ]));
    }

    public function notifySettingsSave(Request $request)
    {
        $user = \Exment::user();
        if (!isset($user)) {
            abort(403);
        }
        $choice = strval($request->input('notify_by', ''));
        if (in_array($choice, PortalTeams::CHOICES, true) && (PortalTeams::teamsAvailable() || !in_array($choice, ['teams', 'both'], true))) {
            $user->setSettingValue(PortalTeams::USER_SETTING, $choice);
        }
        // the digest's own setting kept as it was while the site has it off (the page has no box)
        $old = $user->getSettingValue(PortalNotifyPrefs::SETTING, []);
        $digest = PortalDigest::enabled() ? (array) $request->input('digest', []) : (array) (is_array($old) ? ($old['digest'] ?? []) : []);
        $user->setSettingValue(PortalNotifyPrefs::SETTING, PortalNotifyPrefs::clean([
            'events' => (array) $request->input('events', []),
            'projects' => (array) $request->input('projects', []),
            'quiet' => (array) $request->input('quiet', []),
            'digest' => $digest,
        ]));

        return redirect(PortalDefinition::url('notify-settings') . '?saved=1');
    }
}
