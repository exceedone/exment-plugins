<?php
// ExmentPM: a project the user may not see answers as one that is not there (404), by its
// number and by its code, so that a number does not tell that a project exists. The same for
// an issue of it. (Exment's row filter already leaves such a project out of find(), so the
// 403 of findProject() / findIssue() is not reached for a project: this keeps it that way.)
//
// Run inside a test Exment that has ExmentPM set up and Exment's test users (docker-exment):
//   cd /path/to/exment && php /path/to/project-management/tools/test-project-not-visible-404.php
// It needs the users 3 (the manager) and 10 (not on the project, with the member role). The
// project it makes is made inside a transaction and rolled back.
require getcwd() . '/vendor/autoload.php';
$app = require getcwd() . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
\Exceedone\Exment\Middleware\Morph::defineMorphMap();
use App\Plugins\ExmentPM\PmPortalController;
use App\Plugins\ExmentPM\PortalDefinition;
use Exceedone\Exment\Model\CustomTable;
use Exceedone\Exment\Model\LoginUser;
use Exceedone\Exment\Model\System;

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
    $ref = new ReflectionProperty(PortalDefinition::class, 'projectAnswers');
    $ref->setAccessible(true);
    $ref->setValue(null, []);
}
/** the status findProject() / findIssue() answers with (200 when it gives the record) */
function statusOf(callable $call): int
{
    try {
        $call();
        return 200;
    } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e) {
        return $e->getStatusCode();
    }
}

$MANAGER = 3; $OUTSIDER = 10;
\DB::beginTransaction();
try {
    actAs(1);
    $code = 'NOTV' . substr(strval(time()), -4);
    $project = CustomTable::getEloquent('project')->getValueModel();
    $project->setValue(['name' => 'not visible test', 'project_code' => $code, 'manager' => $MANAGER, 'members' => [], 'archived' => 0]);
    $project->save();
    $issue = CustomTable::getEloquent('pm_issue')->getValueModel();
    $issue->parent_type = 'project';
    $issue->parent_id = $project->id;
    $issue->setValue(['subject' => 'not visible']);
    $issue->save();

    $controller = new PmPortalController();
    $findProject = new ReflectionMethod(PmPortalController::class, 'findProject');
    $findProject->setAccessible(true);
    $findIssue = new ReflectionMethod(PmPortalController::class, 'findIssue');
    $findIssue->setAccessible(true);

    actAs($MANAGER);
    ok(statusOf(fn () => $findProject->invoke($controller, $project->id)) === 200, 'the manager opens the project by its number');
    ok(statusOf(fn () => $findProject->invoke($controller, $code)) === 200, 'the manager opens the project by its code');

    actAs($OUTSIDER);
    $missing = intval(CustomTable::getEloquent('project')->getValueModel()->newQueryWithoutScopes()->max('id')) + 1000;
    ok(statusOf(fn () => $findProject->invoke($controller, $missing)) === 404, 'a number that is not there: 404');
    $byId = statusOf(fn () => $findProject->invoke($controller, $project->id));
    ok($byId === 404, 'a project not visible, by its number: 404 as a missing one', 'got ' . $byId);
    $byCode = statusOf(fn () => $findProject->invoke($controller, $code));
    ok($byCode === 404, 'a project not visible, by its code: 404', 'got ' . $byCode);
    $issueStatus = statusOf(fn () => $findIssue->invoke($controller, $project, $issue->id));
    ok($issueStatus === 404, 'an issue not visible: 404', 'got ' . $issueStatus);
} finally {
    \DB::rollBack();
}
echo $fail === 0 ? "all ok\n" : "$fail failed\n";
exit($fail === 0 ? 0 : 1);
