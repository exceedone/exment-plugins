<?php
// ExmentITSupport: 「ExmentPM の課題にする」 (PmLink, ISS02941), the parts with no database, and every
// route of config.json with its method. Plain PHP: php it-support/tools/test-pm-link.php
$src = file_get_contents(__DIR__ . '/../ExmentITSupport/PmLink.php');

$ok = 0;
$bad = 0;
$check = function (string $label, bool $cond, $extra = '') use (&$ok, &$bad) {
    echo ($cond ? 'OK  ' : 'NG  '), $label, $cond ? '' : '  ' . var_export($extra, true), "\n";
    $cond ? $ok++ : $bad++;
};

// parse() as it is in the file (the class needs Exment to load)
preg_match('/public static function parse\(\$value\): \?array\s*\{(.*?)\n    \}/s', $src, $m);
$parse = eval('return function ($value) {' . ($m[1] ?? 'return false;') . '};');
$check('parse "12:345"', $parse('12:345') === [12, 345]);
$check('parse nothing', $parse(null) === null && $parse('') === null);
$check('parse junk', $parse('12:') === null && $parse('a:1') === null && $parse('1:2:3') === null && $parse(' 1:2') === null);

$check('the issue is made with the person\'s own permissions (no global scope off for projects)',
    preg_match('/\$projects->getValueModel\(\)->query\(\)->find\(\$projectId\)/', $src) === 1);
$check('only the project\'s own lists are filled (as ExmentPM does)', strpos($src, "const SCOPED = ['status', 'issue_type'];") !== false);
$check('once only', strpos($src, 'もう課題になっています') !== false);

// every route of config.json has its method in PluginPage.php
$config = json_decode(file_get_contents(__DIR__ . '/../ExmentITSupport/config.json'), true);
$page = file_get_contents(__DIR__ . '/../ExmentITSupport/PluginPage.php');
$missing = [];
foreach ($config['route'] as $r) {
    if (!preg_match('/public function ' . preg_quote($r['function'], '/') . '\s*\(/', $page)) {
        $missing[] = $r['function'];
    }
}
$check(count($config['route']) . ' routes, each with its method', $missing === [], $missing);

echo "\n", $bad === 0 ? "all passed ($ok)" : "$bad failed, $ok passed", "\n";
exit($bad === 0 ? 0 : 1);
