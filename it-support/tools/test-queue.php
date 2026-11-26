<?php
// ExmentITSupport: the 受付箱 of the help desk, their flows and progress (Queue, ISS02941). Plain PHP:
//   php it-support/tools/test-queue.php
require __DIR__ . '/../ExmentITSupport/Queue.php';

use App\Plugins\ExmentITSupport\Queue as Q;

$ok = 0;
$bad = 0;
$check = function (string $label, bool $cond, $extra = '') use (&$ok, &$bad) {
    echo ($cond ? 'OK  ' : 'NG  '), $label, $cond ? '' : '  ' . var_export($extra, true), "\n";
    $cond ? $ok++ : $bad++;
};

// the statuses agree with Its::STATUSES (read from the file: Its needs Exment)
preg_match('/const STATUSES = \[(.*?)\];/s', file_get_contents(__DIR__ . '/../ExmentITSupport/Its.php'), $m);
preg_match_all("/'([a-z_]+)' =>/", $m[1] ?? '', $keys);
$check('STATUS_KEYS are the keys of Its::STATUSES', $keys[1] === Q::STATUS_KEYS, $keys[1]);

// every kind's own flow is a good one
foreach (Q::KINDS as $kind => $k) {
    $check("the flow of $kind reads", Q::check(Q::parse($k[2])) === [], Q::check(Q::parse($k[2])));
    $check("the group of $kind", isset(Q::GROUPS[$k[1]]));
}
$check('an unknown kind is IT inside', Q::kindOf('x') === 'it_internal' && Q::kindOf(null) === 'it_internal');
$check('groups', Q::groupOf('quote') === 'inquiry' && Q::groupOf('it_external') === 'it');

// a flow of its own, or the kind's
$own = Q::stages('quote', "受付|new\n作る|open\n出した|resolved");
$check('own flow used', array_column($own, 'name') === ['受付', '作る', '出した']);
$check('a bad own flow: the kind\'s', array_column(Q::stages('quote', "受付|new\n変|nope"), 'name') === ['受付', '見積を作る', '送る', '受注', '失注']);
$check('an empty own flow: the kind\'s', count(Q::stages('trial', '')) === 4);
$check('errors: too short, unknown status, twice, no end', count(Q::check(Q::parse("a|new"))) === 2
    && !empty(Q::check(Q::parse("a|new\na|open\nb|resolved")))
    && !empty(Q::check(Q::parse("a|new\nb|open"))));

// progress
$it = Q::stages('it_internal', '');
$check('progress 受付 0', Q::progress($it, '受付') === 0);
$check('progress 対応 33', Q::progress($it, '対応') === 33);
$check('progress 確認 67', Q::progress($it, '確認') === 67);
$check('progress 完了 100', Q::progress($it, '完了') === 100);
$q = Q::stages('quote', '');
$check('quote: 受注 and 失注 both 100', Q::progress($q, '受注') === 100 && Q::progress($q, '失注') === 100);
$check('an unknown stage 0', Q::progress($q, 'x') === 0);

// a status set by the desk itself moves the stage
$check('open → 見積を作る', Q::stageFor($q, 'open', '受付') === '見積を作る');
$check('pending → 送る', Q::stageFor($q, 'pending', '見積を作る') === '送る');
$check('closed keeps 失注', Q::stageFor($q, 'closed', '失注') === '失注');
$check('resolved from 送る → 受注', Q::stageFor($q, 'resolved', '送る') === '受注');
$check('on_hold has no stage: kept', Q::stageFor($q, 'on_hold', '見積を作る') === '見積を作る');
$check('closed in a flow without closed: the first end', Q::stageFor($it, 'closed', '確認') === '完了');
$check('nothing yet: the first stage', Q::stageFor($it, 'on_hold', '') === '受付');
$check('status of a stage', Q::statusOf($q, '送る') === 'pending' && Q::statusOf($q, 'x') === null);

// the fields a queue asks for
$f = Q::fields("requester_name|必須\nrequester_email|1\nproduct\n\nBad Name|必須\nproduct|必須");
$check('fields: names, required, no doubles, no bad names', $f === [
    ['column' => 'requester_name', 'required' => true],
    ['column' => 'requester_email', 'required' => true],
    ['column' => 'product', 'required' => true],
], $f);

echo "\n", $bad === 0 ? "all passed ($ok)" : "$bad failed, $ok passed", "\n";
exit($bad === 0 ? 0 : 1);
