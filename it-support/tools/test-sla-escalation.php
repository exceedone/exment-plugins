<?php
// ExmentITSupport: the steps of the SLA escalation (Alerts\EscalationRule). Plain PHP:
//   php it-support/tools/test-sla-escalation.php
require __DIR__ . '/../ExmentITSupport/Alerts/EscalationRule.php';

use App\Plugins\ExmentITSupport\Alerts\EscalationRule as R;

$ok = 0;
$bad = 0;
$check = function (string $label, bool $cond, $extra = '') use (&$ok, &$bad) {
    echo ($cond ? 'OK  ' : 'NG  '), $label, $cond ? '' : '  ' . var_export($extra, true), "\n";
    $cond ? $ok++ : $bad++;
};

$start = 1800000000;
$due = $start + 4 * 3600; // 4 hours
$at = function (float $hours) use ($start) {
    return (int) ($start + $hours * 3600);
};

$check('nothing at the start', R::step($start, $due, $at(0), 75, 60) === 0);
$check('nothing at 74%', R::step($start, $due, $at(2.96), 75, 60) === 0);
$check('step 1 at 75% (3 of 4 hours)', R::step($start, $due, $at(3), 75, 60) === 1);
$check('step 2 at the due time', R::step($start, $due, $due, 75, 60) === 2);
$check('step 2 until the manager minutes', R::step($start, $due, $due + 59 * 60, 75, 60) === 2);
$check('step 3 after them', R::step($start, $due, $due + 60 * 60, 75, 60) === 3);
$check('no step 3 with 0 minutes', R::step($start, $due, $due + 86400, 75, 0) === 2);
$check('no due time: nothing', R::step($start, 0, $at(10), 75, 60) === 0);
$check('a percent out of range is kept in 1-99', R::step($start, $due, $at(0.04), 0, 60) === 1 && R::step($start, $due, $at(3.9), 150, 60) === 0 && R::step($start, $due, $at(3.97), 150, 60) === 1);
$check('a due time before the start: past it', R::step($due + 10, $due, $due + 20, 75, 60) === 2);

$check('send a step once', R::toSend(1, 0) === 1 && R::toSend(1, 1) === null);
$check('a step reached late: only the highest', R::toSend(3, 0) === 3);
$check('never back', R::toSend(1, 2) === null);

$check('the first answer due while there is none', R::dueOf(['first_response_due' => 'A', 'resolve_due' => 'B']) === 'A');
$check('then the solution due', R::dueOf(['first_response_due' => 'A', 'first_responded_at' => 'x', 'resolve_due' => 'B']) === 'B');
$check('solved: none', R::dueOf(['first_responded_at' => 'x', 'resolve_due' => 'B', 'resolved_at' => 'y']) === null);
$check('no due: none', R::dueOf([]) === null);

$agents = ['7', '8'];
$check('step 1: the assignee', R::recipients(1, '5', ['10'], ['20'], $agents) === ['5']);
$check('step 1 without an assignee: the agents', R::recipients(1, null, ['10'], ['20'], $agents) === ['7', '8']);
$check('step 2: the leads', R::recipients(2, '5', ['10', '11'], ['20'], $agents) === ['10', '11']);
$check('step 2 without leads: as step 1', R::recipients(2, '5', [], ['20'], $agents) === ['5']);
$check('step 3: the managers', R::recipients(3, '5', ['10'], ['20'], $agents) === ['20']);
$check('step 3 without managers: the leads', R::recipients(3, '5', ['10'], [], $agents) === ['10']);
$check('step 3 with nobody set: the assignee', R::recipients(3, '5', [], [], $agents) === ['5']);
$check('no one twice, no empty id', R::recipients(2, '5', ['10', '10', ''], [], $agents) === ['10']);
$check('several assignees (a column of many users): all of them', R::recipients(1, ['5', '6'], [], [], $agents) === ['5', '6']);
$check('an empty list of assignees: the agents', R::recipients(1, [], [], [], $agents) === ['7', '8']);
$check('several assignees, no leads: all of them at step 2', R::recipients(2, ['5', '6'], [], [], $agents) === ['5', '6']);

echo "\n$ok OK, $bad NG\n";
exit($bad === 0 ? 0 : 1);
