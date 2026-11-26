<?php
// Groupware: an older ibisOffice installed when the plugin asks for a newer one (ISS02827).
// Office keeps opening with the old one, and the update is offered.
//
// Run inside a test Exment (docker-exment), from the Exment folder:
//   php /path/to/groupware/tools/test-ibisoffice-update.php
// It makes folders under public/vendor/exment_groupware/ibisoffice and deletes them again
// (not when the version of the plugin is installed there: then it stops).
require getcwd() . '/vendor/autoload.php';
$app = require getcwd() . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (!class_exists('App\Plugins\ExmentGroupware\IbisOffice')) {
    require __DIR__ . '/../ExmentGroupware/IbisOffice.php';
}
use App\Plugins\ExmentGroupware\IbisOffice;

$fail = 0;
function ok($cond, $label, $detail = '')
{
    global $fail;
    echo ($cond ? 'ok   ' : 'FAIL ') . $label . ($cond ? '' : '  -> ' . $detail) . "\n";
    if (!$cond) {
        $fail++;
    }
}

$office = new IbisOffice();
$base = public_path(IbisOffice::PUBLIC_DIR);
if ($office->isInstalled()) {
    echo "ibisOffice " . IbisOffice::VERSION . " is installed here: not touched\n";
    exit(0);
}
$made = [];
$make = function (string $version) use ($base, &$made) {
    $dir = $base . '/' . $version;
    @mkdir($dir, 0777, true);
    file_put_contents($dir . '/index.html', '<!doctype html>');
    file_put_contents($dir . '/ibisoffice-host.js', '//');
    $made[] = $dir;
};
$had = $office->installedVersions();
try {
    if (empty($had)) {
        ok($office->activeVersion() === null && !$office->isAvailable() && !$office->updateAvailable(), 'nothing installed: nothing to open, nothing to update');
    }
    $make('0.0.1');
    $make('0.0.10');
    @mkdir($base . '/not-a-version', 0777, true);
    $made[] = $base . '/not-a-version';
    if (empty($had)) {
        ok($office->activeVersion() === '0.0.10', 'the newest installed version is served (0.0.10 after 0.0.1)', strval($office->activeVersion()));
    }
    ok($office->isAvailable(), 'Office opens with the older version');
    ok($office->updateAvailable(), 'the update to ' . IbisOffice::VERSION . ' is offered');
    ok(strpos(strval($office->hostModuleUrl()), '/' . $office->activeVersion() . '/ibisoffice-host.js') !== false, 'the page loads the host module of that version', strval($office->hostModuleUrl()));
    ok(!in_array('not-a-version', $office->installedVersions(), true), 'a folder that is not a version is not one');
} finally {
    foreach (array_reverse($made) as $dir) {
        @unlink($dir . '/index.html');
        @unlink($dir . '/ibisoffice-host.js');
        @rmdir($dir);
    }
}
echo $fail === 0 ? "all ok\n" : "$fail failed\n";
exit($fail === 0 ? 0 : 1);
