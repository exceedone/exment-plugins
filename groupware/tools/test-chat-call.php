<?php
// Exment Plugin Groupware: calls from the chat (ChatCall, ISS02946), the parts with no database.
//   php groupware/tools/test-chat-call.php
require __DIR__ . '/../ExmentGroupware/Chat/ChatCall.php';

use App\Plugins\ExmentGroupware\Chat\ChatCall as C;

$ok = 0;
$bad = 0;
$check = function (string $label, bool $cond, $extra = '') use (&$ok, &$bad) {
    echo ($cond ? 'OK  ' : 'NG  '), $label, $cond ? '' : '  ' . var_export($extra, true), "\n";
    $cond ? $ok++ : $bad++;
};

$check('duration: seconds', C::duration(100, 165) === '1 分 05 秒');
$check('duration: hours', C::duration(0, 3725) === '1 時間 2 分');
$check('duration: never negative', C::duration(10, 5) === '0 分 00 秒');

$ice = C::iceServers("stun:stun.example.invalid:3478\nturn:turn.example.invalid:3478?transport=udp|u1|p1\nturn:no-password.invalid:3478\njavascript:alert(1)\nhttp://x.invalid\n\n stuns:s.example.invalid:5349 ");
$check('ice: stun kept', $ice[0] === ['urls' => 'stun:stun.example.invalid:3478'], $ice);
$check('ice: turn with its user and password', $ice[1] === ['urls' => 'turn:turn.example.invalid:3478?transport=udp', 'username' => 'u1', 'credential' => 'p1'], $ice);
$check('ice: a turn without a password, other schemes: out', count($ice) === 3 && $ice[2]['urls'] === 'stuns:s.example.invalid:5349', $ice);
$check('ice: empty', C::iceServers('') === [] && C::iceServers(null) === []);
$check('ice: at most 5', count(C::iceServers(implode("\n", array_fill(0, 8, 'stun:a.invalid:1')))) === 5);

$check('the line of a call', sprintf(C::LINE, 12) === '📞 通話 #12 を始めました');
$check('signal types', C::TYPES === ['join', 'offer', 'answer', 'ice', 'hangup']);

echo "\n", $bad === 0 ? "all passed ($ok)" : "$bad failed, $ok passed", "\n";
exit($bad === 0 ? 0 : 1);
