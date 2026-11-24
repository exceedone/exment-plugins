<?php
// ExmentPM: the Files tab with Exment's file server (ISS02825).
//
// Run inside a test Exment 7.2.x that has the file server and ExmentPM set up (docker-exment):
//   cd /path/to/exment && php /path/to/project-management/tools/test-files-fileserver-db.php
// It turns the features on for itself (file_server and exment_pm.files), makes a project with
// a manager, a member, a member organization and a viewer, makes the workspace from the plugin,
// and checks the permissions, the project settings answer, the payload of the tab, picking,
// unlinking, and that a change of the project's people sets the permissions again. Everything
// is made inside a transaction and rolled back.
require getcwd() . '/vendor/autoload.php';
$app = require getcwd() . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
\Exceedone\Exment\Middleware\Morph::defineMorphMap();
use App\Plugins\ExmentPM\PortalDefinition;
use App\Plugins\ExmentPM\PortalFiles;
use Exceedone\Exment\Model\CustomTable;
use Exceedone\Exment\Model\LoginUser;
use Exceedone\Exment\Model\System;

foreach (['\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken', '\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken'] as $csrf) {
    if (class_exists($csrf) && method_exists($csrf, 'except')) {
        $csrf::except(['*']);
    }
}
$fail = 0;
function ok($cond, $label, $detail = '')
{
    global $fail;
    echo ($cond ? 'ok   ' : 'FAIL ') . $label . ($cond ? '' : '  -> ' . $detail) . "\n";
    if (!$cond) {
        $fail++;
    }
}
function actAs(int $baseUserId): void
{
    \Auth::guard('admin')->login(LoginUser::where('base_user_id', $baseUserId)->first());
    System::clearRequestSession();
}
function topPermissions(int $workspaceId): array
{
    $rows = \Exceedone\Exment\Model\FilePermission::query()->where('workspace_id', $workspaceId)->whereNull('node_id')->get();
    $out = [];
    foreach ($rows as $row) {
        $out[$row->target_type . ':' . $row->target_id] = intval($row->level);
    }
    ksort($out);
    return $out;
}

ok(class_exists('\Exceedone\Exment\Services\FileServer\Library'), 'this Exment has the file server');
ok(\Schema::hasTable(PortalFiles::TABLE), 'the plugin table ' . PortalFiles::TABLE . ' is there (plugin migrations ran)');

$MANAGER = 2; $MEMBER = 3; $VIEWER = 4; $ORG = 2; $OTHER = 5;
\DB::beginTransaction();
try {
    $features = '\Exceedone\Exment\Services\Features';
    actAs(1);
    if (class_exists($features)) {
        // off first: the tab stays the attachments
        $features::save(['file_server' => ['enabled' => true], PortalFiles::FEATURE => ['enabled' => false]]);
        if (method_exists($features, 'reset')) { $features::reset(); }
        PortalFiles::registerFeature();
        ok(!PortalFiles::enabled(), 'the feature is off by default / when turned off: no file server in the tab');
        $features::save(['file_server' => ['enabled' => true], PortalFiles::FEATURE => ['enabled' => true]]);
        if (method_exists($features, 'reset')) { $features::reset(); }
        PortalFiles::registerFeature();
    }
    ok(PortalFiles::enabled(), 'the feature on: the tab uses the file server');

    $def = PortalDefinition::config();
    $p = function ($role) use ($def) { return strval(array_get($def, 'project.' . $role, $role)); };
    $project = CustomTable::getEloquent('project')->getValueModel();
    $project->setValue([$p('name') => 'files test', $p('key') => 'FS' . strtoupper(substr(md5(microtime()), 0, 5)),
        $p('manager') => $MANAGER, $p('members') => [$MEMBER], $p('member_orgs') => [$ORG], $p('viewers') => [$VIEWER], 'archived' => 0]);
    $project->save();

    $entries = PortalFiles::entriesOf($project);
    $want = ['organization:' . $ORG => 3, 'user:' . $MANAGER => 4, 'user:' . $MEMBER => 3, 'user:' . $VIEWER => 1];
    $got = [];
    foreach ($entries as $e) { $got[$e['target_type'] . ':' . $e['target_id']] = $e['level']; }
    ksort($got);
    ksort($want);
    ok($got === $want, 'the project people as levels: manager manage, member and member org edit, viewer view', json_encode($got));

    // the system administrator makes the workspace (the site default: only they may)
    $state = PortalFiles::state($project, true);
    ok($state['on'] === true && $state['workspace'] === null && $state['can_create'] === true, 'no workspace yet, the administrator may make one', json_encode($state));
    $workspace = PortalFiles::create($project);
    ok(PortalFiles::workspaceOf($project) && intval(PortalFiles::workspaceOf($project)->id) === intval($workspace->id), 'the workspace is linked to the project');
    ok(topPermissions(intval($workspace->id)) === $want, 'its top permissions follow the project', json_encode(topPermissions(intval($workspace->id))));
    ok(PortalFiles::projectOf(intval($workspace->id)) === intval($project->id), 'the workspace knows its project');

    // the people of the project change: the permissions follow on the save
    $project = CustomTable::getEloquent('project')->getValueModel()->newQueryWithoutScopes()->find($project->id);
    $project->setValue($p('viewers'), [$VIEWER, $OTHER]);
    $project->setValue($p('members'), []);
    $project->save();
    $want2 = ['organization:' . $ORG => 3, 'user:' . $MANAGER => 4, 'user:' . $OTHER => 1, 'user:' . $VIEWER => 1];
    ksort($want2);
    ok(topPermissions(intval($workspace->id)) === $want2, 'after the save: the member left, a viewer came', json_encode(topPermissions(intval($workspace->id))));

    // the screens: the payload of the tab, as the manager and as somebody not on the project
    actAs($MANAGER);
    $s = PortalFiles::state($project, true);
    ok(($s['workspace']['can_open'] ?? false) === true && strpos(strval($s['workspace']['url'] ?? ''), 'file_server') !== false, 'the manager opens the workspace in the tab', json_encode($s));
    ok(($s['can_create'] ?? null) === false, 'the manager may not make workspaces on a site where only administrators do');
    $server = ['HTTP_HOST' => 'localhost', 'HTTP_ACCEPT' => 'application/json', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest', 'CONTENT_TYPE' => 'application/json'];
    $res = $kernel->handle(Illuminate\Http\Request::create(PortalDefinition::url($project->id, 'file_workspace'), 'GET', [], [], [], $server));
    actAs($MANAGER);
    ok($res->getStatusCode() === 200 && boolval(array_get(json_decode($res->getContent(), true), 'fileserver.workspace.id')), 'GET file_workspace answers the workspace', $res->getStatusCode() . ' ' . mb_substr($res->getContent(), 0, 200));
    actAs($OTHER);
    $s = PortalFiles::state($project, false);
    ok(($s['workspace']['can_open'] ?? null) === true, 'a viewer opens it too (view)');

    // unlink and pick again (the manager manages it)
    actAs($MANAGER);
    $res = $kernel->handle(Illuminate\Http\Request::create(PortalDefinition::url($project->id, 'file_workspace'), 'POST', [], [], [], $server, json_encode(['action' => 'unlink'])));
    actAs($MANAGER);
    ok($res->getStatusCode() === 200 && PortalFiles::workspaceOf($project) === null, 'unlink: the project has no workspace', $res->getStatusCode() . ' ' . mb_substr($res->getContent(), 0, 200));
    ok(\Exceedone\Exment\Model\FileWorkspace::query()->find($workspace->id) !== null, 'the workspace itself stays');
    $pickable = array_column(PortalFiles::pickable($project), 'id');
    ok(in_array(intval($workspace->id), $pickable, true), 'the manager may pick it again (they manage it)', json_encode($pickable));
    $res = $kernel->handle(Illuminate\Http\Request::create(PortalDefinition::url($project->id, 'file_workspace'), 'POST', [], [], [], $server, json_encode(['action' => 'pick', 'workspace_id' => $workspace->id])));
    actAs($MANAGER);
    ok($res->getStatusCode() === 200 && PortalFiles::workspaceOf($project) !== null, 'pick: linked again', $res->getStatusCode());

    // somebody who is not the manager may not change it
    actAs($VIEWER);
    $res = $kernel->handle(Illuminate\Http\Request::create(PortalDefinition::url($project->id, 'file_workspace'), 'POST', [], [], [], $server, json_encode(['action' => 'unlink'])));
    actAs($VIEWER);
    ok(in_array($res->getStatusCode(), [403, 404], true) && PortalFiles::workspaceOf($project) !== null, 'a viewer may not unlink: ' . $res->getStatusCode());

    // another project may not take the same workspace
    actAs(1);
    $other = CustomTable::getEloquent('project')->getValueModel();
    $other->setValue([$p('name') => 'files other', $p('key') => 'FO' . strtoupper(substr(md5(microtime()), 0, 5)), $p('manager') => 1, 'archived' => 0]);
    $other->save();
    $threw = false;
    try {
        PortalFiles::pick($other, intval($workspace->id));
    } catch (\RuntimeException $e) {
        $threw = true;
    }
    ok($threw, 'a workspace used by one project is not offered to another');
} catch (\Throwable $e) {
    $fail++;
    echo 'ERROR ', get_class($e), ': ', $e->getMessage(), ' at ', basename($e->getFile()), ':', $e->getLine(), "\n";
} finally {
    \DB::rollBack();
}
echo $fail ? "\n$fail FAILED\n" : "\nall passed\n";
