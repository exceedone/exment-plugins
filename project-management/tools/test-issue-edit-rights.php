<?php
// ExmentPM: who may edit an issue of a project (the manager, a member, a member through an
// organization: yes; a viewer, somebody who is not on the project, an archived project, a
// workflow lock: no).
//
// Run inside a test Exment that has ExmentPM set up and Exment's test users (docker-exment):
//   cd /path/to/exment && php /path/to/project-management/tools/test-issue-edit-rights.php
// It needs the users 3, 4, 5, 7, 8, 10 and the organization 4 (user 7 belongs to it), and the
// role groups pm_member_group / pm_viewer_group. Everything it makes - a project, two issues,
// the members of the role groups - is made inside a transaction and rolled back.
require getcwd() . '/vendor/autoload.php';
$app = require getcwd() . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
\Exceedone\Exment\Middleware\Morph::defineMorphMap();
use App\Plugins\ExmentPM\PortalDefinition;
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
    $login = LoginUser::where('base_user_id', $baseUserId)->first();
    \Auth::guard('admin')->login($login);
    System::clearRequestSession();
    // PortalDefinition remembers the project's answer per user for a few seconds
    $ref = new ReflectionProperty(PortalDefinition::class, 'projectAnswers');
    $ref->setAccessible(true);
    $ref->setValue(null, []);
}
function fresh($table, $id)
{
    return CustomTable::getEloquent($table)->getValueModel()->newQueryWithoutScopes()->find($id);
}
function patch($kernel, int $issueId, string $subject)
{
    $server = ['HTTP_HOST' => 'localhost', 'HTTP_ACCEPT' => 'application/json', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest', 'CONTENT_TYPE' => 'application/json'];
    $r = Illuminate\Http\Request::create('http://localhost/admin/plugins/exment_pm/lightapi/data/pm_issue/' . $issueId, 'PATCH', [], [], [], $server, json_encode(['value' => ['subject' => $subject]]));
    $user = \Auth::guard('admin')->user();
    $res = $kernel->handle($r);
    \Auth::guard('admin')->login($user);
    return $res->getStatusCode();
}

$MANAGER = 3; $MEMBER = 4; $ORG = 4; $ORG_MEMBER = 7; $VIEWER = 5; $OUTSIDER = 10; $ASSIGNEE = 8;
\DB::beginTransaction();
try {
    // the roles of the tables: the member group edits issues, the viewer group reads them
    $memberGroup = \Exceedone\Exment\Model\RoleGroup::where('role_group_name', 'like', '%pm_member_group')->first();
    $viewerGroup = \Exceedone\Exment\Model\RoleGroup::where('role_group_name', 'like', '%pm_viewer_group')->first();
    $join = function ($group, string $type, int $id) {
        \DB::table('role_group_user_organizations')->insert(['role_group_id' => $group->id, 'role_group_user_org_type' => $type, 'role_group_target_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
    };
    foreach ([$MANAGER, $MEMBER, $OUTSIDER, $ASSIGNEE] as $u) {
        if (!\DB::table('role_group_user_organizations')->where('role_group_id', $memberGroup->id)->where('role_group_user_org_type', 'user')->where('role_group_target_id', $u)->exists()) {
            $join($memberGroup, 'user', $u);
        }
    }
    $join($memberGroup, 'organization', $ORG);
    $join($viewerGroup, 'user', $VIEWER);

    actAs(1);
    $org = fresh('organization', $ORG);
    $inOrg = $org->users->pluck('id')->map('intval')->all();
    ok(in_array($ORG_MEMBER, $inOrg, true), 'user ' . $ORG_MEMBER . ' is in organization ' . $ORG, json_encode($inOrg));

    $project = CustomTable::getEloquent('project')->getValueModel();
    $project->setValue(['name' => 'edit rights test', 'project_code' => 'EDITT' . substr(strval(time()), -4), 'manager' => $MANAGER,
        'members' => [$MEMBER, $ASSIGNEE], 'member_orgs' => [$ORG], 'viewers' => [$VIEWER], 'archived' => 0]);
    $project->save();
    // the project's own lists (made with the project), if it has them
    $own = function (string $table) use ($project) {
        $t = CustomTable::getEloquent($table);
        $row = $t ? $t->getValueModel()->newQueryWithoutScopes()->where('parent_type', 'project')->where('parent_id', $project->id)->orderBy('id')->first() : null;
        return $row ? strval($row->id) : null;
    };
    $base = array_filter(['subject' => 'who may edit', 'status' => $own('pm_status'), 'issue_type' => $own('pm_issue_type')]);
    echo 'lists of the project: ', json_encode($base), "\n";
    $make = function (array $more) use ($project, $base) {
        $issue = CustomTable::getEloquent('pm_issue')->getValueModel();
        $issue->parent_type = 'project';
        $issue->parent_id = $project->id;
        $issue->setValue(array_merge($base, $more));
        $issue->save();
        return $issue->id;
    };
    $plain = $make([]);                              // opened by the admin, nobody assigned
    $assigned = $make(['assignees' => [$ASSIGNEE]]);   // assigned to a member
    echo "project {$project->id}, issues $plain (nobody assigned), $assigned (assigned to $ASSIGNEE)\n";

    $cases = [
        [$MANAGER, 'the manager', true],
        [$MEMBER, 'a member (user)', true],
        [$ORG_MEMBER, 'a member through an organization', true],
        [$ASSIGNEE, 'a member, on an issue assigned to somebody else', true],
        [$VIEWER, 'a viewer', false],
        [$OUTSIDER, 'somebody with the member role who is not on the project', false],
    ];
    foreach ($cases as [$user, $who, $want]) {
        actAs($user);
        $issue = fresh('pm_issue', $plain);
        $core = $issue->enableEdit(true) === true;
        $got = PortalDefinition::enableEdit($issue, true) === true;
        ok($got === $want, $who . ': ' . ($want ? 'may edit' : 'may not edit') . ' an issue not assigned to them', 'got ' . json_encode($got) . ' (Exment alone: ' . json_encode($core) . ')');
        echo '       (Exment alone answers ' . json_encode($core) . ")\n";
        $status = patch($kernel, $plain, 'edited by ' . $user);
        ok($want ? $status === 200 : in_array($status, [403, 404], true), $who . ': saving the subject -> ' . $status);
        if ($want) {
            ok(fresh('pm_issue', $plain)->getValue('subject') === 'edited by ' . $user, $who . ': the subject is saved');
        }
    }
    // the assignee keeps what Exment gives
    actAs($ASSIGNEE);
    ok(fresh('pm_issue', $assigned)->enableEdit(true) === true && PortalDefinition::enableEdit(fresh('pm_issue', $assigned), true) === true, 'the assignee edits the issue assigned to them, as before');
    actAs($VIEWER);
    ok(PortalDefinition::enableEdit(fresh('pm_issue', $assigned), true) !== true, 'a viewer may not edit that one either');

    // a workflow lock refuses, as it does for the assignee: the issue's own class with the lock on
    actAs($MEMBER);
    $real = fresh('pm_issue', $plain);
    $class = get_class($real);
    $short = 'LockedIssue' . substr(md5($class), 0, 8);
    if (!class_exists($short)) {
        eval('class ' . $short . ' extends ' . $class . ' { public function lockedWorkflow() { return true; } }');
    }
    $locked = new $short();
    $locked->setRawAttributes($real->getAttributes(), true);
    $locked->exists = true;
    $answer = PortalDefinition::enableEdit($locked, true);
    ok($answer !== true && strval($answer) === strval(\Exceedone\Exment\Enums\ErrorCode::WORKFLOW_LOCK()), 'a member may not edit an issue a workflow has locked', json_encode(strval($answer === true ? 'true' : $answer)));
    ok(PortalDefinition::enableEdit($real, true) === true, '(the same issue without the lock: may edit)');

    // archived: nobody writes
    actAs(1);
    $p = fresh('project', $project->id);
    $p->setValue('archived', 1);
    $p->save();
    foreach ([[$MANAGER, 'the manager'], [$MEMBER, 'a member'], [$ORG_MEMBER, 'a member through an organization']] as [$user, $who]) {
        actAs($user);
        ok(PortalDefinition::enableEdit(fresh('pm_issue', $plain), true) !== true, 'archived project: ' . $who . ' may not edit');
    }
    actAs($MEMBER);
    $status = patch($kernel, $plain, 'edited while archived');
    ok($status !== 200, 'archived project: saving is refused -> ' . $status);
} catch (\Throwable $e) {
    $fail++;
    echo 'ERROR ', get_class($e), ': ', $e->getMessage(), ' at ', basename($e->getFile()), ':', $e->getLine(), "\n";
} finally {
    \DB::rollBack();
}
echo $fail ? "\n$fail FAILED\n" : "\nall passed\n";
