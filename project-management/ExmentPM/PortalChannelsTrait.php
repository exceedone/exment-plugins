<?php

namespace App\Plugins\ExmentPM;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * The chat channels of a project, a pane of its settings (ISS02939):
 *
 *   GET  {id}/channels   the channels (no secret: masked)
 *   POST {id}/channels   action=save | remove | test
 *
 * The project's manager only, while the feature is on (Feature management).
 */
trait PortalChannelsTrait
{
    public function channels(Request $request, $id)
    {
        $project = $this->channelsProject($id);

        return response()->json(['ok' => true, 'channels' => PortalChannels::state(intval($project->id))]);
    }

    public function postChannels(Request $request, $id)
    {
        $project = $this->channelsProject($id);
        $pid = intval($project->id);
        $me = intval(\Exment::getUserId());
        $action = strval($request->input('action', ''));
        try {
            switch ($action) {
                case 'save':
                    [, $why] = PortalChannels::save($pid, [
                        'id' => $request->input('id'),
                        'type' => $request->input('type'),
                        'label' => $request->input('label'),
                        'secret' => $request->input('secret'),
                        'target' => $request->input('target'),
                        'sign_secret' => $request->input('sign_secret'),
                        'sign_clear' => $request->boolean('sign_clear'),
                        'events' => (array) $request->input('events', []),
                        'enabled' => $request->boolean('enabled'),
                    ], $me);
                    if ($why !== null) {
                        return PortalDefinition::jsonError(422, 'channel_' . $why, ['message' => strval(PortalDefinition::trans('channels.err_' . $why))]);
                    }
                    break;
                case 'remove':
                    if (!PortalChannels::remove($pid, intval($request->input('id')), $me)) {
                        return PortalDefinition::jsonError(404, 'not_found');
                    }
                    break;
                case 'test':
                    $ok = PortalChannels::test($pid, intval($request->input('id')), $project);

                    return response()->json([
                        'ok' => true,
                        'sent' => $ok,
                        'message' => strval(PortalDefinition::trans($ok ? 'channels.test_sent' : 'channels.test_failed')),
                        'channels' => PortalChannels::state($pid),
                    ]);
                default:
                    return PortalDefinition::jsonError(422, 'invalid');
            }
        } catch (\Throwable $e) {
            Log::error('pm_portal channels: ' . $action . ' for project ' . $pid . ' failed (' . get_class($e) . ')');

            return PortalDefinition::jsonError(500, 'server_error');
        }

        return response()->json(['ok' => true, 'channels' => PortalChannels::state($pid)]);
    }

    /** The project, when its manager asks and the feature is on; 404 otherwise. */
    protected function channelsProject($id)
    {
        if (!PortalChannels::enabled()) {
            abort(404);
        }
        $project = $this->findProject($id);
        if (!PortalMembership::canManage($project)) {
            abort(404);
        }

        return $project;
    }
}
