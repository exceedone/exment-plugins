<?php
// ExmentPM: issues nested up to five levels, through the real saves.
//
// Run inside a test Exment that has ExmentPM set up (docker-exment):
//   cd /path/to/exment && php /path/to/project-management/tools/test-issue-levels-db.php
// Levels 3 to 5 are saved, a sixth is refused, a loop is refused, an issue moves under another
// with its children when they still fit, the setting changes the levels, the parent picker
// leaves out what would be refused, the json endpoint says why, and an issue with children still
// does not move to another project. Everything is made inside a transaction and rolled back.
require getcwd() . '/vendor/autoload.php';
$app = require getcwd() . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
\Exceedone\Exment\Middleware\Morph::defineMorphMap();
use App\Plugins\ExmentPM\PortalDefinition;
use App\Plugins\ExmentPM\PortalRules;
use Exceedone\Exment\Model\CustomTable;
use Exceedone\Exment\Model\LoginUser;
use Exceedone\Exment\Model\System;
use Illuminate\Validation\ValidationException;

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
function fresh($table, $id)
{
    return CustomTable::getEloquent($table)->getValueModel()->newQueryWithoutScopes()->find($id);
}
/** Save; null when saved, else the message the rules gave. */
function trySave($model): ?string
{
    try {
        $model->save();
        return null;
    } catch (ValidationException $e) {
        return implode(' / ', \Illuminate\Support\Arr::flatten($e->errors()));
    }
}

$login = LoginUser::where('base_user_id', 1)->first();
\Auth::guard('admin')->login($login);
System::clearRequestSession();
$def = PortalDefinition::config();
$issueTable = strval(array_get($def, 'issue_table'));
$parentCol = strval(array_get($def, 'issue.parent', 'parent_issue'));
$tooDeep = function ($max) {
    return PortalDefinition::trans('rules.parent_too_deep', ['max' => $max]);
};

\DB::beginTransaction();
try {
    $makeProject = function (string $name) {
        $project = CustomTable::getEloquent('project')->getValueModel();
        $project->setValue(['name' => $name, 'project_code' => 'LV' . strtoupper(substr(md5($name . microtime()), 0, 5)), 'manager' => 1, 'archived' => 0, 'subtasking' => 1]);
        $project->save();
        return $project;
    };
    $project = $makeProject('levels test');
    $own = function (string $table, $projectId) {
        $t = CustomTable::getEloquent($table);
        $row = $t ? $t->getValueModel()->newQueryWithoutScopes()->where('parent_type', 'project')->where('parent_id', $projectId)->orderBy('id')->first() : null;
        return $row ? strval($row->id) : null;
    };
    $base = function ($projectId) use ($own) {
        return array_filter(['status' => $own('pm_status', $projectId), 'issue_type' => $own('pm_issue_type', $projectId)]);
    };
    $make = function (string $subject, ?int $parent, $projectId = null, array $more = []) use ($project, $base, $issueTable, $parentCol) {
        $projectId = $projectId ?? $project->id;
        $issue = CustomTable::getEloquent($issueTable)->getValueModel();
        $issue->parent_type = 'project';
        $issue->parent_id = $projectId;
        $issue->setValue(array_merge($base($projectId), ['subject' => $subject], $parent ? [$parentCol => $parent] : [], $more));
        $error = trySave($issue);
        return [$error === null ? intval($issue->id) : null, $error];
    };
    $reparent = function (int $id, ?int $parent) use ($issueTable, $parentCol) {
        $issue = fresh($issueTable, $id);
        $issue->setValue($parentCol, $parent);
        return trySave($issue);
    };

    ok(PortalRules::issueLevels() === 5, 'five levels by default');

    // levels 1 to 5
    $ids = [];
    $parent = null;
    foreach ([1, 2, 3, 4, 5] as $level) {
        [$id, $error] = $make('level ' . $level, $parent);
        ok($id !== null, 'level ' . $level . ' is saved', strval($error));
        $ids[$level] = $id;
        $parent = $id;
    }
    ok(PortalRules::issueLevel(CustomTable::getEloquent($issueTable), $parentCol, $ids[5]) === 5, 'the fifth is on level 5');

    // a sixth level
    [$id, $error] = $make('level 6', $ids[5]);
    ok($id === null && $error === $tooDeep(5), 'a sixth level is refused, saying the levels', strval($error));

    // a loop: the top under its own grandchild
    $error = $reparent($ids[1], $ids[3]);
    ok($error === PortalDefinition::trans('rules.parent_loop'), 'an issue may not go under one of its own', strval($error));

    // moving an issue with its children (two levels) under another
    [$a] = $make('move A', null);
    [$b] = $make('move B', $a);
    $error = $reparent($a, $ids[3]);
    ok($error === null, 'A with its child moves under level 3: A on 4, B on 5', strval($error));
    ok(intval(fresh($issueTable, $b)->getValue($parentCol, true)) === $a || strval(array_get(fresh($issueTable, $b)->value, $parentCol)) === strval($a), 'B stays under A');
    $error = $reparent($a, $ids[4]);
    ok($error === $tooDeep(5), 'A with its child under level 4 would put B on level 6: refused', strval($error));
    $error = $reparent($a, null);
    ok($error === null, 'A back to the top', strval($error));

    // the setting
    $plugin = PortalDefinition::plugin();
    // in this request only (not saved)
    $plugin->setCustomOption('issue_levels', '3');
    ok(PortalRules::issueLevels() === 3, 'the setting says three levels');
    [$id, $error] = $make('level 4 with three', $ids[3]);
    ok($id === null && $error === $tooDeep(3), 'three levels: under level 3 is refused', strval($error));
    [$id, $error] = $make('level 3 with three', $ids[2]);
    ok($id !== null, 'three levels: under level 2 is saved', strval($error));
    // the issues already deeper stay as they are (only a new parent is checked)
    ok(fresh($issueTable, $ids[5]) !== null && fresh($issueTable, $ids[5])->getValue('subject') === 'level 5', 'the issues already deeper stay');
    $plugin->setCustomOption('issue_levels', null);
    ok(PortalRules::issueLevels() === 5, 'five levels again without the setting');

    // the parent picker of B (under A, two levels): nothing that would be refused
    $table = CustomTable::getEloquent($issueTable);
    $refused = PortalRules::refusedParents($table, $parentCol, $a, $project->id);
    ok(in_array($a, $refused, true) && in_array($b, $refused, true), 'the picker for A leaves out A and B', json_encode($refused));
    ok(in_array($ids[4], $refused, true) && in_array($ids[5], $refused, true) && !in_array($ids[3], $refused, true), 'the picker for A: level 4 and 5 out, level 3 in', json_encode($refused));
    $server = ['HTTP_HOST' => 'localhost', 'HTTP_ACCEPT' => 'application/json', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'];
    $url = PortalDefinition::url('lightapi', 'lookup', $issueTable);
    $query = http_build_query(['parent_type' => 'project', 'parent_id' => $project->id, 'exclude' => $a, 'levels' => $parentCol, 'q' => '', 'limit' => 100]);
    $res = $kernel->handle(Illuminate\Http\Request::create($url . '?' . $query, 'GET', [], [], [], $server));
    \Auth::guard('admin')->login($login);
    $offered = array_map('intval', array_column((array) array_get(json_decode($res->getContent(), true), 'results', []), 'value'));
    ok($res->getStatusCode() === 200 && in_array($ids[3], $offered, true) && !in_array($ids[4], $offered, true) && !in_array($b, $offered, true),
        'the picker endpoint offers level 3, not level 4 nor B', $res->getStatusCode() . ' ' . json_encode($offered));

    // the json endpoint says why
    $patch = function (int $id, array $value) use ($kernel, $login, $issueTable, $server) {
        $url = PortalDefinition::url('lightapi', 'data', $issueTable, $id);
        $r = Illuminate\Http\Request::create($url, 'PATCH', [], [], [], $server + ['CONTENT_TYPE' => 'application/json'], json_encode(['value' => $value]));
        $res = $kernel->handle($r);
        \Auth::guard('admin')->login($login);
        return $res;
    };
    $says = function ($res, string $message): bool {
        $body = json_decode((string) $res->getContent(), true);
        return strpos(json_encode($body, JSON_UNESCAPED_UNICODE), $message) !== false;
    };
    $res = $patch($a, [$parentCol => $ids[4]]);
    ok($res->getStatusCode() >= 400 && $says($res, $tooDeep(5)), 'the json endpoint refuses a sixth level and says so', $res->getStatusCode() . ' ' . mb_substr((string) $res->getContent(), 0, 300));
    $res = $patch($ids[1], [$parentCol => $ids[2]]);
    ok($res->getStatusCode() >= 400 && $says($res, PortalDefinition::trans('rules.parent_loop')), 'the json endpoint refuses a loop and says so', $res->getStatusCode() . ' ' . mb_substr((string) $res->getContent(), 0, 300));

    // another project: an issue with children still stays
    $other = $makeProject('levels other');
    $moving = fresh($issueTable, $a);
    $moving->parent_id = $other->id;
    $error = trySave($moving);
    ok($error !== null && strpos($error, PortalDefinition::trans('rules.move_has_children')) !== false, 'an issue with children does not move to another project', strval($error));
} catch (\Throwable $e) {
    $fail++;
    echo 'ERROR ', get_class($e), ': ', $e->getMessage(), ' at ', basename($e->getFile()), ':', $e->getLine(), "\n";
} finally {
    \DB::rollBack();
}
echo $fail ? "\n$fail FAILED\n" : "\nall passed\n";
