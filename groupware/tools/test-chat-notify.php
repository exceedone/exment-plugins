<?php
// Exment Plugin Groupware: who the chat tells, and how; how long a room keeps its messages
// (ChatNotify, ChatRetention, ISS02945 step 3). Plain PHP: php groupware/tools/test-chat-notify.php
require __DIR__ . '/../ExmentGroupware/Chat/ChatNotify.php';
require __DIR__ . '/../ExmentGroupware/Chat/ChatRetention.php';

use App\Plugins\ExmentGroupware\Chat\ChatNotify as N;
use App\Plugins\ExmentGroupware\Chat\ChatRetention as R;

$ok = 0;
$bad = 0;
$check = function (string $label, bool $cond, $extra = '') use (&$ok, &$bad) {
    echo ($cond ? 'OK  ' : 'NG  '), $label, $cond ? '' : '  ' . var_export($extra, true), "\n";
    $cond ? $ok++ : $bad++;
};

$members = [['user_id' => 1, 'muted' => false], ['user_id' => 2, 'muted' => false], ['user_id' => 3, 'muted' => true], ['user_id' => 4, 'muted' => false]];
$check('direct: the other one', N::recipients('direct', array_slice($members, 0, 2), [], 1) === [2]);
$check('group: only the named', N::recipients('group', $members, [4], 1) === [4]);
$check('group: nothing named, nobody', N::recipients('group', $members, [], 1) === []);
$check('group: @全員 everyone but the writer and the muted', N::recipients('group', $members, ['all'], 1) === [2, 4], N::recipients('group', $members, ['all'], 1));
$check('a muted one named: not told', N::recipients('group', $members, [3], 1) === []);
$check('the writer named: not told', N::recipients('group', $members, [1], 1) === []);

$check('channels: bell only', N::channels('bell') === ['mail' => false, 'teams' => false]);
$check('channels: mail', N::channels('mail') === ['mail' => true, 'teams' => false]);
$check('channels: both', N::channels('both') === ['mail' => true, 'teams' => true]);
$check('channels: unknown is the bell', N::channels('x') === ['mail' => false, 'teams' => false]);

$check('retention: a choice', R::clean(90) === 90 && R::clean('365') === 365);
$check('retention: always', R::clean(0) === null && R::clean(null) === null);
$check('retention: not a choice', R::clean(7) === null && R::clean(-30) === null);

echo "\n", $bad === 0 ? "all passed ($ok)" : "$bad failed, $ok passed", "\n";
exit($bad === 0 ? 0 : 1);
