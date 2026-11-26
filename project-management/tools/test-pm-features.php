<?php
// ExmentPM: simple by default (schema 30). A new project starts without the Wiki and the chart;
// every project there already is keeps what it has. Plain PHP, no database.
//
//   php project-management/tools/test-pm-features.php
require __DIR__ . '/../ExmentPM/PortalSchema.php';
require __DIR__ . '/../ExmentPM/PortalUpgrade.php';

use App\Plugins\ExmentPM\PortalSchema;
use App\Plugins\ExmentPM\PortalUpgrade;

$fail = 0;
function ok($cond, $label)
{
    global $fail;
    echo ($cond ? 'ok   ' : 'FAIL ') . $label . "\n";
    if (!$cond) {
        $fail++;
    }
}

$cols = ['use_wiki', 'use_gantt'];
ok(PortalSchema::VERSION >= 30, 'schema 30 or later');
ok(PortalSchema::QUIET_FEATURES === $cols, 'the Wiki and the chart start off');
foreach (PortalSchema::QUIET_FEATURES as $role) {
    ok(array_key_exists($role, PortalSchema::PROJECT_COLUMNS), $role . ' is a project column');
}
ok(PortalUpgrade::keepFeaturesPatch([], $cols) === ['use_wiki' => '1', 'use_gantt' => '1'], 'never saved: both kept on');
ok(PortalUpgrade::keepFeaturesPatch(['use_wiki' => '', 'use_gantt' => null], $cols) === ['use_wiki' => '1', 'use_gantt' => '1'], 'empty: kept on');
ok(PortalUpgrade::keepFeaturesPatch(['use_wiki' => '0', 'use_gantt' => '1'], $cols) === [], 'a choice made stays as it is');
ok(PortalUpgrade::keepFeaturesPatch(['use_wiki' => 0, 'use_gantt' => '1'], $cols) === [], 'a 0 stays off');

$schema = file_get_contents(__DIR__ . '/../ExmentPM/PortalSchema.php');
ok(strpos($schema, "[\$pc['use_wiki'], 'project.use_wiki', '0', 31]") !== false, 'a new install: the Wiki off');
ok(strpos($schema, "[\$pc['use_gantt'], 'project.use_gantt', '0', 33]") !== false, 'a new install: the chart off');
ok(strpos($schema, "[\$pc['use_file'], 'project.use_file', '1', 32]") !== false, 'files stay on');
$up = file_get_contents(__DIR__ . '/../ExmentPM/PortalUpgrade.php');
ok(preg_match('/if \(\$applied < 30\) \{\s*\$quieted = static::quietNewProjects/', $up) === 1, 'once, on the way past 30');

echo $fail === 0 ? "all ok\n" : "$fail failed\n";
exit($fail === 0 ? 0 : 1);
