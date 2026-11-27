<?php
// ExmentSupportDesk: calls with a customer by a link (GuestCall, ISS02941), the parts with no database.
//   php support-desk/tools/test-guest-call.php
require __DIR__ . '/../ExmentSupportDesk/GuestCall.php';

use App\Plugins\ExmentSupportDesk\GuestCall as C;

$ok = 0;
$bad = 0;
$check = function (string $label, bool $cond, $extra = '') use (&$ok, &$bad) {
    echo ($cond ? 'OK  ' : 'NG  '), $label, $cond ? '' : '  ' . var_export($extra, true), "\n";
    $cond ? $ok++ : $bad++;
};

$check('duration: seconds', C::duration(100, 165) === '1 分 05 秒');
$check('duration: hours', C::duration(0, 3725) === '1 時間 2 分');
$check('duration: never negative', C::duration(10, 5) === '0 分 00 秒');
$ice = C::iceServers("stun:s.example.invalid:3478\nturn:t.example.invalid:3478|u|p\nturn:nopass.invalid:3478\nhttp://x.invalid");
$check('ice: stun and a turn with its user', count($ice) === 2 && $ice[1]['username'] === 'u' && $ice[1]['credential'] === 'p', $ice);
$check('ice: empty', C::iceServers('') === []);
$check('a link: 30 minutes', C::MINUTES === 30);
$check('signal types', C::TYPES === ['join', 'offer', 'answer', 'ice', 'hangup']);

echo "\n", $bad === 0 ? "all passed ($ok)" : "$bad failed, $ok passed", "\n";
exit($bad === 0 ? 0 : 1);
