<?php
// ExmentPM: where each person's notice arrives besides the bell (ISS02931): their own channels,
// plus the writer's "also by e-mail" / "also by Teams" for one notice. Plain PHP, no database.
//
//   php project-management/tools/test-pm-notify-to.php
require __DIR__ . '/../ExmentPM/PortalNotifyTo.php';

use App\Plugins\ExmentPM\PortalNotifyTo;

$fail = 0;
function ok($cond, $label)
{
    global $fail;
    echo ($cond ? 'ok   ' : 'FAIL ') . $label . "\n";
    if (!$cond) {
        $fail++;
    }
}
$own = [
    'bell' => ['mail' => false, 'teams' => false],
    'mail' => ['mail' => true, 'teams' => false],
    'teams' => ['mail' => false, 'teams' => true],
    'both' => ['mail' => true, 'teams' => true],
];
$r = function ($choice, $hasMail, $mailAll, $teamsAll, $teamsOn) use ($own) {
    $to = PortalNotifyTo::route($own[$choice], $hasMail, $mailAll, $teamsAll, $teamsOn);
    return ($to['mail'] ? 'M' : '-') . ($to['teams'] ? 'T' : '-');
};

ok($r('bell', true, false, false, true) === '--', 'bell only: no mail, no Teams');
ok($r('mail', true, false, false, true) === 'M-', 'mail too');
ok($r('teams', true, false, false, true) === '-T', 'Teams too');
ok($r('both', true, false, false, true) === 'MT', 'both');
ok($r('bell', true, true, false, true) === 'M-', 'the writer ticked mail: mail for a bell-only person');
ok($r('bell', true, false, true, true) === '-T', 'the writer ticked Teams: Teams for a bell-only person');
ok($r('mail', false, true, false, true) === '--', 'no address: no mail');
ok($r('both', true, false, true, false) === 'M-', 'no Teams where Exment cannot send it');

$star = file_get_contents(__DIR__ . '/../ExmentPM/Star.php');
ok(strpos($star, "const TYPE_NOTICE_MAIL = 'nt:mail';") !== false && strpos($star, "const TYPE_NOTICE_TEAMS = 'nt:teams';") !== false, 'the log under a comment: its own star types');
$ctl = file_get_contents(__DIR__ . '/../ExmentPM/PmPortalController.php');
ok(strpos($ctl, "Star::noticeLog('comment', \$comment_id, \$plan['mail'], \$plan['teams']);") !== false, 'kept when the comment is posted');
ok(strpos($ctl, "\$comment_notices = Star::notices('comment', \$comment_ids);") !== false, 'read with the comments, one query');

$cfg = json_decode(file_get_contents(__DIR__ . '/../ExmentPM/config.json'), true);
$route = null;
foreach ((array) ($cfg['route'] ?? []) as $r) {
    if (($r['uri'] ?? '') === '{id}/notify-channels') {
        $route = $r;
    }
}
ok(is_array($route) && $route['method'] === 'get' && $route['function'] === 'projectNotifyChannels', 'the light read of the channels is a GET route');
ok(strpos($ctl, "\$project = \$this->findProject(\$id);\n\n        return response()->json([\n            'ok' => true,\n            'notify_by'") !== false, 'it goes through findProject (admin.morph is skipped)');

echo $fail === 0 ? "all ok\n" : "$fail failed\n";
exit($fail === 0 ? 0 : 1);
