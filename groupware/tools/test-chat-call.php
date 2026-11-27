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
$check('a call: up to 4 people', C::MAX_PEOPLE === 4);
$check('signal types', C::TYPES === ['join', 'offer', 'answer', 'ice', 'hangup']);

// a server outside this site (the notice under the setting, the note of the call screen)
$out = C::outsideServers(C::iceServers("stun:stun.outside.invalid:19302\nstun:call.site.invalid:3478\nturn:relay.outside.invalid|u|p\nturns:tls.outside.invalid|u|p\nturn:tcp.outside.invalid:443?transport=tcp|u|p\nstun:STUN.outside.invalid:19302"), ['call.site.invalid']);
$check('outside: the site itself is left out', !in_array('call.site.invalid', array_column($out, 'host'), true), $out);
$check('outside: stun with its port, UDP', $out[0] === ['scheme' => 'stun', 'host' => 'stun.outside.invalid', 'port' => 19302, 'proto' => 'UDP'], $out);
$check('outside: turn without a port: 3478 UDP', $out[1]['host'] === 'relay.outside.invalid' && $out[1]['port'] === 3478 && $out[1]['proto'] === 'UDP' && $out[1]['scheme'] === 'turn', $out);
$check('outside: turns: 5349 TCP', $out[2]['port'] === 5349 && $out[2]['proto'] === 'TCP' && $out[2]['scheme'] === 'turn', $out);
$check('outside: ?transport=tcp is TCP', $out[3]['port'] === 443 && $out[3]['proto'] === 'TCP', $out);
$check('outside: the same server once (case)', count($out) === 4, $out);
$v6 = C::outsideServers(C::iceServers('stun:[2001:db8::1]:3478'), ['call.site.invalid']);
$check('outside: an IPv6 address in brackets', count($v6) === 1 && $v6[0]['host'] === '[2001:db8::1]' && $v6[0]['port'] === 3478, $v6);
$check('outside: only the site: none', C::outsideServers(C::iceServers('stun:Call.Site.invalid:3478'), ['call.site.invalid']) === []);
$check('outside: empty setting: no summary', C::outsideSummary('', ['call.site.invalid']) === null);
$sum = C::outsideSummary("stun:stun.outside.invalid:19302\nturn:relay.outside.invalid:3478|u|p", ['call.site.invalid']);
$check('summary: hosts, what to allow, a TURN', $sum === ['hosts' => ['stun.outside.invalid', 'relay.outside.invalid'], 'allow' => ['stun.outside.invalid:19302 (UDP)', 'relay.outside.invalid:3478 (UDP)'], 'turn' => true], $sum);
$html = C::outsideNoticeHtml($sum, 'x', 'ja');
$check('notice: the host and port to allow', str_contains($html, '<code>stun.outside.invalid:19302 (UDP)</code>'), $html);
$check('notice (ja): IP address, firewall, coturn, TURN', str_contains($html, 'IP アドレス') && str_contains($html, 'ファイアウォール') && str_contains($html, 'coturn') && str_contains($html, 'TURN のサーバーは'), $html);
$check('notice (en)', str_contains(C::outsideNoticeHtml($sum, 'x', 'en'), 'firewall'));
$check('notice: none without a summary', C::outsideNoticeHtml(null, 'x', 'ja') === '');
$check('notice: no TURN sentence for a STUN only', !str_contains(C::outsideNoticeHtml(C::outsideSummary('stun:stun.outside.invalid:19302', ['call.site.invalid']), 'x', 'ja'), 'TURN のサーバーは'));
$check('notice: escaped', !str_contains(C::outsideNoticeHtml(['hosts' => ['<b>'], 'allow' => ['<b>'], 'turn' => false], 'x', 'ja'), '<b>'));
$note = C::outsideNote('stun:stun.outside.invalid:19302', 'https://manual.invalid/page', ['call.site.invalid'], 'ja');
$check('note (ja): the text of the call screen', $note['text'] === '通話をつなぐために、外のサーバー（stun.outside.invalid）を使います。' && $note['manual'] === 'https://manual.invalid/page' && $note['hosts'] === ['stun.outside.invalid'], $note);
$check('note: a language with no file reads English', C::outsideText('note', ['hosts' => 'h'], 'xx') === 'To connect the call, an outside server (h) is used.');
$check('note: none for the site only', C::outsideNote('stun:call.site.invalid:3478', 'https://manual.invalid/', ['call.site.invalid']) === null);

echo "\n", $bad === 0 ? "all passed ($ok)" : "$bad failed, $ok passed", "\n";
exit($bad === 0 ? 0 : 1);
