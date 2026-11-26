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

echo $fail === 0 ? "all ok\n" : "$fail failed\n";
exit($fail === 0 ? 0 : 1);
