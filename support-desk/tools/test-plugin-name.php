<?php
// ExmentSupportDesk: the plugin's names. Plain PHP, no Exment needed (CI):
//   php support-desk/tools/test-plugin-name.php
// The internal names are ExmentSupportDesk / exment_support_desk / App\Plugins\ExmentSupportDesk,
// the shown name is サポートデスク / Support Desk, and nothing shown still says the old product
// name (IT サポート / IT Support / サポート窓口 / Help desk). The boxes named after the work of IT
// support (「IT サポート（社内）」 and so on) are not in the lang files, so the lang files allow none.
$dir = dirname(__DIR__) . '/ExmentSupportDesk';
$bad = 0;
$check = function (string $name, bool $ok) use (&$bad) {
    echo ($ok ? 'OK  ' : 'NG  ') . $name . "\n";
    if (!$ok) {
        $bad++;
    }
};

$config = json_decode((string) file_get_contents("$dir/config.json"), true);
$check('config.json reads', is_array($config));
$config = is_array($config) ? $config : [];
$check('plugin_name is ExmentSupportDesk', ($config['plugin_name'] ?? null) === 'ExmentSupportDesk');
$check('uri is exment_support_desk', ($config['uri'] ?? null) === 'exment_support_desk');
$check('uuid is not the one of ExmentITSupport', ($config['uuid'] ?? '') !== '6b1f2c0e-7a4d-4e8b-9c35-1d2e8f4a7b90'
    && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', (string) ($config['uuid'] ?? '')) === 1);
$view = (string) ($config['plugin_view_name'] ?? '');
$check('plugin_view_name says Support Desk and サポートデスク', strpos($view, 'Support Desk') !== false && strpos($view, 'サポートデスク') !== false);

// the old names, in every language of the lang files and of Exment's group names
$old = ['IT Support', 'IT support', 'IT-Support', 'ITサポート', 'IT サポート', 'サポート窓口', 'Help desk', 'Helpdesk', 'help desk',
    'Soporte de TI', 'Mesa de ayuda', 'IT 지원', '헬프데스크', 'Hỗ trợ IT', 'IT 支持'];
// (服务台 and Bộ phận hỗ trợ, Exment 7.3.0's words for the group, are kept: they read as "support desk" too.)
// lang values that may keep an old word (as "lang key" => the word); none now
$allow = [];

$flat = function (array $a, string $p = '') use (&$flat) {
    $o = [];
    foreach ($a as $k => $v) {
        $key = $p === '' ? (string) $k : $p . '.' . $k;
        $o += is_array($v) ? $flat($v, $key) : [$key => (string) $v];
    }
    return $o;
};
$locales = array_values(array_diff(scandir("$dir/resources/lang"), ['.', '..']));
$check('the lang files are sd.php in ' . implode(', ', $locales), count($locales) > 0);
foreach ($locales as $locale) {
    $file = "$dir/resources/lang/$locale/sd.php";
    if (!is_file($file)) {
        $check("$locale: sd.php", false);
        continue;
    }
    $found = [];
    foreach ($flat(require $file) as $key => $value) {
        foreach ($old as $word) {
            if (strpos($value, $word) !== false && ($allow[$key] ?? null) !== $word) {
                $found[] = "$key ($word)";
            }
        }
    }
    $check("$locale: no lang value says the old name" . ($found ? ': ' . implode(', ', $found) : ''), count($found) === 0);
}

// the feature keys and the namespace
$feature = (string) file_get_contents("$dir/Feature.php");
preg_match_all("/const [A-Z_]+ = '([^']+)';/", $feature, $m);
$keys = array_values(array_filter($m[1], function ($k) { return strpos($k, '.') !== false; }));
$check('feature keys are exment_support_desk.* (' . count($keys) . ')', count($keys) > 0
    && count(array_filter($keys, function ($k) { return strpos($k, 'exment_support_desk.') !== 0; })) === 0);

$left = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if (!$f->isFile() || !preg_match('/\.(php|json|js|css)$/', $f->getFilename())) {
        continue;
    }
    $s = (string) file_get_contents($f->getPathname());
    if (preg_match('/ExmentITSupport|exment_it_support|\bits_[a-z]/', $s)) {
        $left[] = substr($f->getPathname(), strlen($dir) + 1);
    }
    if (substr($f->getFilename(), -4) === '.php' && preg_match('/^namespace (\S+);/m', $s, $ns)
        && strpos($ns[1], 'App\\Plugins\\ExmentSupportDesk') !== 0) {
        $left[] = substr($f->getPathname(), strlen($dir) + 1) . ' (namespace ' . $ns[1] . ')';
    }
}
$check('no old internal name in the plugin' . ($left ? ': ' . implode(', ', $left) : ''), count($left) === 0);

echo $bad === 0 ? "all passed\n" : "$bad failed\n";
exit($bad === 0 ? 0 : 1);
