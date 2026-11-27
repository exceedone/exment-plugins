<?php
// Exment Plugin Groupware: the chat's parts with no database (ChatText, ISS02945). Plain PHP:
//   php groupware/tools/test-chat-text.php
require __DIR__ . '/../ExmentGroupware/Chat/ChatText.php';

use App\Plugins\ExmentGroupware\Chat\ChatText as T;

$ok = 0;
$bad = 0;
$check = function (string $label, bool $cond, $extra = '') use (&$ok, &$bad) {
    echo ($cond ? 'OK  ' : 'NG  '), $label, $cond ? '' : '  ' . var_export($extra, true), "\n";
    $cond ? $ok++ : $bad++;
};

$check('direct key: the same both ways', T::directKey(7, 3) === '3:7' && T::directKey(3, 7) === '3:7');

$check('clean: line breaks kept, controls out', T::clean("a\r\nb\x00c\x1b") === "a\nbc");
$check('clean: at most 20,000', mb_strlen(T::clean(str_repeat('あ', 25000))) === 20000);
$check('clean: nothing', T::clean(null) === '');
$check('name: one line, at most 100', T::cleanName("営業\n部" . str_repeat('x', 200)) === mb_substr('営業部' . str_repeat('x', 200), 0, 100));

$members = [1 => '山田', 2 => '山田 太郎', 3 => 'Sato', 4 => ''];
$check('mention: the longest name wins', T::mentions('@山田 太郎 お願いします', $members, true) === [2], T::mentions('@山田 太郎 お願いします', $members, true));
$check('mention: two people', T::mentions("@山田 と @Sato さん", $members, true) === [1, 3] || T::mentions("@山田 と @Sato さん", $members, true) === [3, 1], T::mentions("@山田 と @Sato さん", $members, true));
$check('mention: inside a word is not one', T::mentions('mail@Sato.invalid', $members, true) === []);
$check('mention: @全員 in a group', T::mentions('@全員 集合', $members, true) === ['all']);
$check('mention: @all not in a direct room', T::mentions('@all hi', $members, false) === []);
$check('mention: after a punctuation', T::mentions('@Sato、確認を', $members, true) === [3]);
$check('mention: someone not a member', T::mentions('@鈴木 さん', $members, true) === []);

$reads = [1 => 10, 2 => 5, 3 => 12];
$check('read count: not the writer', T::readCount($reads, 10, 1) === 1);
$check('read count: all who reached it', T::readCount($reads, 5, null) === 3);
$check('read count: none yet', T::readCount($reads, 13, 1) === 0);

$check('poll: 3 s seen', T::pollDelay(true, 10) === 3);
$check('poll: 30 s hidden', T::pollDelay(false, 10) === 30);
$check('poll: stop after 30 min idle', T::pollDelay(true, 1800) === null && T::pollDelay(false, 4000) === null);

$check('reaction: from the list', T::reaction('👍') === '👍' && T::reaction('<b>') === null && T::reaction(null) === null);
$check('quote: one line, at most 80', T::quote("a\n\nb") === 'a b' && mb_strlen(T::quote(str_repeat('あ', 100))) === 81);

echo "\n", $bad === 0 ? "all passed ($ok)" : "$bad failed, $ok passed", "\n";
exit($bad === 0 ? 0 : 1);
