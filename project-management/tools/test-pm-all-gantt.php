<?php
// ExmentPM: every project in one gantt (ISS00244). Only where the core's gantt groups by the parent
// record (exment#2298, Exment 7.3); elsewhere the page and its link are not there. Plain PHP.
//
//   php project-management/tools/test-pm-all-gantt.php
$fail = 0;
function ok($cond, $label)
{
    global $fail;
    echo ($cond ? 'ok   ' : 'FAIL ') . $label . "\n";
    if (!$cond) {
        $fail++;
    }
}
$dir = __DIR__ . '/../ExmentPM/';
$trait = file_get_contents($dir . 'PortalAllIssuesTrait.php');
$cfg = json_decode(file_get_contents($dir . 'config.json'), true);
$found = false;
foreach ((array) ($cfg['route'] ?? []) as $r) {
    if (($r['uri'] ?? '') === 'issues/gantt' && ($r['function'] ?? '') === 'allGantt' && ($r['method'] ?? '') === 'get') {
        $found = true;
    }
}
ok($found, 'the route');
ok(strpos(file_get_contents($dir . 'PluginPage.php'), 'public function allGantt()') !== false, 'its page method');
ok(strpos($trait, "defined('Exceedone\\\\Exment\\\\DataItems\\\\Grid\\\\GanttGrid::GROUP_PARENT')") !== false, 'guarded by the core constant (a string: no class is loaded on 7.2)');
ok(strpos($trait, "if (!isset(\$view)) {\n            return redirect(PortalDefinition::url());") !== false, 'without it: back to the dashboard, no error');
ok(strpos($trait, '->save()') === false, 'the view is not saved');
$list = file_get_contents($dir . 'resources/views/list.blade.php');
ok(strpos($list, '@if(\\App\\Plugins\\ExmentPM\\PmPortalController::allGanttSupported())') !== false, 'the dashboard link only where it works');
$ja = file_get_contents($dir . 'resources/lang/ja/pmportal.php');
ok(strpos($ja, '// AG-1') !== false && strpos($ja, "'ag_title' =>") !== false && strpos($ja, "'ag_link' =>") !== false, 'the words in ja');

echo $fail === 0 ? "all ok\n" : "$fail failed\n";
exit($fail === 0 ? 0 : 1);
