<?php

namespace App\Plugins\ExmentPM;

use Illuminate\Http\Request;

/**
 * Issues linked to records of Exment (ISS02939):
 *
 *   GET  {id}/link_tables                    the tables the project links to, and the choices (manager)
 *   POST {id}/link_tables                    tables[]=...                                      (manager)
 *   GET  {id}/issue/{issue_id}/links         the links of an issue
 *   POST {id}/issue/{issue_id}/links         action=add (table, record) | remove (link)  (who may edit it)
 *   GET  {id}/links/search                   table, q: records the person may read
 *   GET  record_links/tables                 every table some project links to (record-links.js)
 *   GET  record_links                        table, id: the issues linked to a record the person may read
 */
trait PortalRecordLinksTrait
{
    public function linkTables(Request $request, $id)
    {
        $project = $this->findProject($id);
        if (!PortalRecordLinks::exists() || !PortalMembership::canManage($project)) {
            abort(404);
        }

        return response()->json(['ok' => true, 'links' => $this->linkTablesState($project)]);
    }

    public function postLinkTables(Request $request, $id)
    {
        $project = $this->findProject($id);
        if (!PortalRecordLinks::exists() || !PortalMembership::canManage($project)) {
            abort(404);
        }
        $why = PortalRecordLinks::saveTables(intval($project->id), (array) $request->input('tables', []), intval(\Exment::getUserId()));
        if ($why !== null) {
            return PortalDefinition::jsonError(422, 'links_' . $why, ['message' => strval(PortalDefinition::trans('links.err_' . $why, ['max' => PortalRecordLinks::MAX_TABLES]))]);
        }

        return response()->json(['ok' => true, 'links' => $this->linkTablesState($project)]);
    }

    /** @return array<string, mixed> */
    protected function linkTablesState($project): array
    {
        return ['on' => true, 'tables' => PortalRecordLinks::tablesOf(intval($project->id)), 'choices' => PortalRecordLinks::candidates(), 'max' => PortalRecordLinks::MAX_TABLES];
    }

    public function issueLinks(Request $request, $id, $issue_id)
    {
        $project = $this->findProject($id);
        $issue = $this->findIssue($project, $issue_id);

        return response()->json(['ok' => true, 'links' => PortalRecordLinks::linksOf(intval($issue->id))]);
    }

    public function postIssueLinks(Request $request, $id, $issue_id)
    {
        $project = $this->findProject($id);
        $issue = $this->findIssue($project, $issue_id);
        if (!PortalRecordLinks::exists()) {
            abort(404);
        }
        if ($this->projectIsArchived($project)) {
            return $this->messageError(403, 'archived');
        }
        if (PortalDefinition::enableEdit($issue, true) !== true) {
            return $this->messageError(403, 'no_permission');
        }
        $action = strval($request->input('action', ''));
        if ($action === 'add') {
            $why = PortalRecordLinks::add($issue, strval($request->input('table', '')), intval($request->input('record')), intval(\Exment::getUserId()));
            if ($why !== null) {
                return PortalDefinition::jsonError(422, 'links_' . $why, ['message' => strval(PortalDefinition::trans('links.err_' . $why, ['max' => PortalRecordLinks::MAX_LINKS]))]);
            }
        } elseif ($action === 'remove') {
            if (!PortalRecordLinks::remove($issue, intval($request->input('link')))) {
                return $this->messageError(404, 'not_found');
            }
        } else {
            return $this->messageError(400, 'invalid_params');
        }

        return response()->json(['ok' => true, 'links' => PortalRecordLinks::linksOf(intval($issue->id))]);
    }

    public function linkSearch(Request $request, $id)
    {
        $project = $this->findProject($id);
        $table = strval($request->query('table', ''));
        if (!in_array($table, PortalRecordLinks::tablesOf(intval($project->id)), true)) {
            return response()->json(['ok' => true, 'found' => []]);
        }

        return response()->json(['ok' => true, 'found' => PortalRecordLinks::search($table, strval($request->query('q', '')))]);
    }

    public function recordLinkTables(Request $request)
    {
        return response()->json(['ok' => true, 'tables' => PortalRecordLinks::allTables()])
            ->header('Cache-Control', 'private, max-age=300');
    }

    public function recordLinks(Request $request)
    {
        $table = strval($request->query('table', ''));
        $id = intval($request->query('id', 0));
        if ($table === '' || $id <= 0) {
            return response()->json(['ok' => true, 'issues' => []]);
        }

        return response()->json(['ok' => true, 'issues' => PortalRecordLinks::issuesOf($table, $id)]);
    }
}
