<?php
// ExmentPM: the manifest and the service worker of the installable web app (PortalPwa,
// ISS02939 item 6), for the /admin prefix and an empty one. Plain PHP:
//   php project-management/tools/test-pm-pwa.php
require __DIR__ . '/../ExmentPM/PortalPwa.php';
use App\Plugins\ExmentPM\PortalPwa as P;

$ok = 0; $bad = 0;
$check = function ($label, $cond, $extra = '') use (&$ok, &$bad) { echo ($cond ? 'OK  ' : 'NG  '), $label, $cond ? '' : '  ' . var_export($extra, true), "\n"; $cond ? $ok++ : $bad++; };

foreach (['https://pm.example.invalid/admin/plugins/exment_pm/' => '/admin/plugins/exment_pm/', 'https://pm.example.invalid/plugins/exment_pm/' => '/plugins/exment_pm/'] as $scope => $path) {
    $m = P::manifest($scope, 'ACME', 'プロジェクト管理');
    $check($path . ': scope, id and start_url from the route', $m['scope'] === $path && $m['id'] === $path && $m['start_url'] === $path . '?source=pwa', $m);
    $check($path . ': the icons under the scope', count($m['icons']) === 3 && array_reduce($m['icons'], function ($c, $i) use ($path) { return $c && strpos($i['src'], $path . 'pwa-icon/') === 0; }, true));
    $check($path . ': one maskable, 192 and 512', in_array('maskable', array_column($m['icons'], 'purpose'), true) && in_array('192x192', array_column($m['icons'], 'sizes'), true) && in_array('512x512', array_column($m['icons'], 'sizes'), true));
    $check($path . ': standalone, the names', $m['display'] === 'standalone' && $m['name'] === 'ACME プロジェクト管理' && mb_strlen($m['short_name']) <= 12);

    $w = P::worker($scope, '/exment-pm/', 'v9');
    preg_match('/^var CFG = (.*);$/m', $w, $mm);
    $cfg = json_decode($mm[1] ?? 'null', true);
    $check($path . ': the worker\'s scope and offline page', is_array($cfg) && $cfg['scope'] === $path && $cfg['offline'] === $path . 'offline' && $cfg['assets'] === ['/exment-pm/'] && $cfg['version'] === 'v9', $cfg);
}
$check('no site name: the app name only', P::manifest('https://x.invalid/plugins/exment_pm/', ' ', 'PM')['name'] === 'PM');
$w = P::worker('https://x.invalid/plugins/exment_pm/', '</script><x>|', 'v');
$check('the worker\'s settings cannot close a tag', strpos($w, '</script>') === false && strpos($w, '<x>') === false);
foreach (P::ICONS as $k => $f) {
    $png = P::icon($k);
    $check('icon ' . $k . ' is a PNG', $png !== null && substr($png, 0, 8) === "\x89PNG\r\n\x1a\n");
}
$check('an unknown icon: none', P::icon('../config.json') === null && P::icon('x') === null);

echo "\n", $bad === 0 ? "all passed ($ok)" : "$bad failed, $ok passed", "\n";
exit($bad === 0 ? 0 : 1);
