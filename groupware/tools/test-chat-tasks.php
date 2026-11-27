<?php
// Exment Plugin Groupware: the chat's tasks and search, the parts with no database (ChatTasks, ISS02945).
//   php groupware/tools/test-chat-tasks.php
require __DIR__ . '/../ExmentGroupware/Chat/ChatText.php';
require __DIR__ . '/../ExmentGroupware/Chat/ChatTasks.php';

use App\Plugins\ExmentGroupware\Chat\ChatTasks as K;

$ok = 0;
$bad = 0;
$check = function (string $label, bool $cond, $extra = '') use (&$ok, &$bad) {
    echo ($cond ? 'OK  ' : 'NG  '), $label, $cond ? '' : '  ' . var_export($extra, true), "\n";
    $cond ? $ok++ : $bad++;
};

$check('title: the first line', K::titleOf("見積を送る\n詳しくは…") === '見積を送る');
$check('title: an empty message', K::titleOf("\n\n") === 'チャットのタスク');
$check('title: at most 200', mb_strlen(K::titleOf(str_repeat('a', 300))) === 200);
$check('search: words split by spaces (also 全角)', K::words("見積　 2026 ") === ['見積', '2026']);
$check('search: LIKE marks as text', K::words('50%_off a\\b') === ['50\\%\\_off', 'a\\\\b'], K::words('50%_off a\\b'));
$check('search: at most 5 words', count(K::words('a b c d e f g')) === 5);
$check('search: nothing', K::words('   ') === []);

echo "\n", $bad === 0 ? "all passed ($ok)" : "$bad failed, $ok passed", "\n";
exit($bad === 0 ? 0 : 1);
