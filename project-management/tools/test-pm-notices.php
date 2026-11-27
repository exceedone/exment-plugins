<?php
// ExmentPM: the bell's list in the portal (PortalNotices::item, ISS02939 item 6): what a notice
// looks like to the screen and where a tap goes, for /admin and an empty admin prefix.
//   php project-management/tools/test-pm-notices.php
require __DIR__ . '/../ExmentPM/PortalNotices.php';
use App\Plugins\ExmentPM\PortalNotices as N;

$ok = 0; $bad = 0;
$url = function ($path) { return 'https://pm.example.invalid/admin/' . $path; };
$check = function ($label, $cond, $extra = '') use (&$ok, &$bad) { echo ($cond ? 'OK  ' : 'NG  '), $label, $cond ? '' : '  ' . var_export($extra, true), "\n"; $cond ? $ok++ : $bad++; };

foreach (['https://pm.example.invalid/admin/', 'https://pm.example.invalid/'] as $base) {
    $u = function ($path) use ($base) { return $base . $path; };
    $it = N::item(12, 'pm_issue', "[PMD-1]  Login\n fails", false, '2026-11-27 10:00', $u);
    $check($base . ': a notice of a record opens through rowdetail (marked read on the way)', $it['url'] === $base . 'notify_navbar/rowdetail/12', $it);
    $it2 = N::item(13, null, 'x', true, '', $u);
    $check($base . ': a notice of no record opens the notice itself', $it2['url'] === $base . 'notify_navbar/13', $it2);
}
$it = N::item(12, 'pm_issue', "[PMD-1]  Login\n fails", false, 'at', $url);
$check('the subject on one line', $it['subject'] === '[PMD-1] Login fails', $it['subject']);
$check('unread and read', $it['read'] === false && N::item(1, '', 'x', true, '', $url)['read'] === true);
$check('an empty parent type: no record', strpos(N::item(5, '', 'x', true, '', $url)['url'], 'notify_navbar/5') !== false);
$long = N::item(1, 't', str_repeat('あ', 400), false, '', $url);
$check('a long subject is cut', mb_strwidth($long['subject']) <= 200, mb_strwidth($long['subject']));
$check('markup stays text (the screen uses textContent)', N::item(1, 't', '<img src=x onerror=alert(1)>', false, '', $url)['subject'] === '<img src=x onerror=alert(1)>');
$check('no body in the list (only the subject)', !array_key_exists('body', $it));

echo "\n", $bad === 0 ? "all passed ($ok)" : "$bad failed, $ok passed", "\n";
exit($bad === 0 ? 0 : 1);
