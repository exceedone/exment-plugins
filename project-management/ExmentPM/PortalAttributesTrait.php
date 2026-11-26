<?php

namespace App\Plugins\ExmentPM;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * The custom attributes of a project, a pane of its settings (ISS02939):
 *
 *   GET  {id}/attributes   the definitions (the deleted too)
 *   POST {id}/attributes   action=save | delete | restore | order
 *
 * The project's manager only, while the project uses custom attributes (its switch).
 */
trait PortalAttributesTrait
{
    public function attributes(Request $request, $id)
    {
        $project = $this->attributesProject($id);

        return response()->json(['ok' => true, 'attributes' => PortalAttributes::state(intval($project->id))]);
    }

    public function postAttributes(Request $request, $id)
    {
        $project = $this->attributesProject($id);
        $pid = intval($project->id);
        $me = intval(\Exment::getUserId());
        $action = strval($request->input('action', ''));
        try {
            switch ($action) {
                case 'save':
                    $options = $request->input('options', []);
                    if (is_string($options)) {
                        $options = preg_split('/\r\n|\r|\n/', $options) ?: [];
                    }
                    [, $why] = PortalAttributes::save($pid, [
                        'id' => $request->input('id'),
                        'type' => $request->input('type'),
                        'label' => $request->input('label'),
                        'options' => (array) $options,
                        'required' => $request->boolean('required'),
                        'unit' => $request->input('unit'),
                        'decimals' => $request->input('decimals'),
                        'max' => $request->input('max'),
                    ], $me);
                    if ($why !== null) {
                        return PortalDefinition::jsonError(422, 'attribute_' . $why, ['message' => strval(PortalDefinition::trans('attributes.err_' . $why, ['max' => PortalAttributes::MAX_PER_PROJECT]))]);
                    }
                    break;
                case 'delete':
                case 'restore':
                    if (!PortalAttributes::setDeleted($pid, intval($request->input('id')), $action === 'delete', $me)) {
                        return PortalDefinition::jsonError(404, 'not_found');
                    }
                    break;
                case 'order':
                    PortalAttributes::reorder($pid, array_map('intval', (array) $request->input('ids', [])));
                    break;
                default:
                    return PortalDefinition::jsonError(422, 'invalid');
            }
        } catch (\Throwable $e) {
            Log::error('pm_portal attributes: ' . $action . ' for project ' . $pid . ' failed (' . get_class($e) . ': ' . $e->getMessage() . ')');

            return PortalDefinition::jsonError(500, 'server_error');
        }

        return response()->json(['ok' => true, 'attributes' => PortalAttributes::state($pid)]);
    }

    /** The project, when its manager asks and it uses custom attributes; 404 otherwise. */
    protected function attributesProject($id)
    {
        $project = $this->findProject($id);
        if (!PortalAttributes::projectOn($project) || !PortalMembership::canManage($project)) {
            abort(404);
        }

        return $project;
    }
}
