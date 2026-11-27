<?php
// ExmentITSupport: the chat window on a website (GuestChat, ISS02941), the parts with no database.
//   php it-support/tools/test-guest-chat.php
require __DIR__ . '/../ExmentITSupport/GuestChat.php';

use App\Plugins\ExmentITSupport\GuestChat as G;

$ok = 0;
$bad = 0;
$check = function (string $label, bool $cond, $extra = '') use (&$ok, &$bad) {
    echo ($cond ? 'OK  ' : 'NG  '), $label, $cond ? '' : '  ' . var_export($extra, true), "\n";
    $cond ? $ok++ : $bad++;
};

$k = G::newKey();
$check('a key: 24 hex', G::validKey($k), $k);
$check('keys differ', G::newKey() !== $k);
$check('not a key', !G::validKey('ABCDEF0123456789abcdef01') && !G::validKey('../x') && !G::validKey(null) && !G::validKey(str_repeat('a', 25)));

$check('frame-ancestors: any site when none', G::frameAncestors('') === '*' && G::frameAncestors(null) === '*');
$check('frame-ancestors: the sites', G::frameAncestors("https://www.example.invalid\nhttp://shop.example.invalid:8080") === 'https://www.example.invalid http://shop.example.invalid:8080');
$check('frame-ancestors: no paths, schemes or quotes', G::frameAncestors("https://a.invalid/x\njavascript:alert(1)\n'self'\n*\nhttps://b.invalid") === 'https://b.invalid', G::frameAncestors("https://a.invalid/x\njavascript:alert(1)\n'self'\n*\nhttps://b.invalid"));
$check('frame-ancestors: only junk is any site', G::frameAncestors("data:\n'none'") === '*');

$check('text: controls out, line breaks kept', G::clean("a\x00\r\nb\x1b") === "a\nb");
$check('text: at most 2,000', mb_strlen(G::clean(str_repeat('あ', 3000))) === 2000);
$check('text: a name at most 100', mb_strlen(G::clean(str_repeat('x', 300), 100)) === 100);

$now = 1000000;
$check('online: seen 5 min ago', G::online($now - 300, $now));
$check('offline: seen 10 min ago', !G::online($now - 600, $now));
$check('offline: never seen', !G::online(null, $now));

echo "\n", $bad === 0 ? "all passed ($ok)" : "$bad failed, $ok passed", "\n";
exit($bad === 0 ? 0 : 1);
