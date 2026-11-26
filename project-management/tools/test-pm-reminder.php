<?php
// ExmentPM の期限のリマインド（ISS00432）の日付を、Exment なしで試す: php project-management/tools/test-pm-reminder.php
require __DIR__ . '/../ExmentPM/JapaneseHolidays.php';
require __DIR__ . '/../ExmentPM/BusinessCalendar.php';
require __DIR__ . '/../ExmentPM/PortalReminder.php';
use App\Plugins\ExmentPM\BusinessCalendar as C;
use App\Plugins\ExmentPM\PortalReminder as R;

$ok = 0; $bad = 0;
$check = function ($label, $cond, $extra = '') use (&$ok, &$bad) { echo ($cond ? 'OK  ' : 'NG  '), $label, $cond ? '' : "  $extra", "\n"; $cond ? $ok++ : $bad++; };
$keys = function (array $slots) { return implode(' ', array_keys($slots)); };

$cal = new C(['2026-12-29' => 'off', '2026-12-30' => 'off', '2026-12-31' => 'off', '2027-01-02' => 'work']);
$s = R::parseSettings(['days' => '3,1', 'business' => '0', 'today' => '1', 'overdue' => '1', 'overdue_max' => '14', 'time' => '9:00', 'to' => 'assignees,watchers,bogus']);

// the settings
$check('days sorted, deduped', $s['days'] === [3, 1]);
$check('time padded', $s['time'] === '09:00');
$check('to: only known ones', $s['to'] === ['assignees', 'watchers']);
$bad1 = R::parseSettings(['days' => 'x, 0, 99, 2', 'time' => '25:00', 'to' => []]);
$check('bad values dropped', $bad1['days'] === [2] && $bad1['time'] === '09:00' && $bad1['to'] === ['assignees']);

// due Thu 2026-12-03: 3 days before is Mon 11-30, 1 day before Wed 12-02
$check('3 days before', $keys(R::slots('2026-12-03', '2026-11-30', '09:00', $s, $cal)) === 'before:3:2026-12-03');
$check('1 day before', $keys(R::slots('2026-12-03', '2026-12-02', '10:15', $s, $cal)) === 'before:1:2026-12-03');
$check('not before the time', R::slots('2026-12-03', '2026-12-02', '08:59', $s, $cal) === []);
$check('the day itself', $keys(R::slots('2026-12-03', '2026-12-03', '09:00', $s, $cal)) === 'today:2026-12-03');
$check('a day between: nothing', R::slots('2026-12-03', '2026-12-01', '12:00', $s, $cal) === []);

// due Mon 2026-12-07: 1 day before is Sun 12-06, sent on Fri 12-04; 3 days before is Fri 12-04 too
$check('day off: the business day before', $keys(R::slots('2026-12-07', '2026-12-04', '09:00', $s, $cal)) === 'before:3:2026-12-07 before:1:2026-12-07');
$check('not on the Sunday', R::slots('2026-12-07', '2026-12-06', '09:00', $s, $cal) === []);

// in business days: due Mon 12-07, 3 business days before is Wed 12-02
$sb = R::parseSettings(['days' => '3', 'business' => '1', 'today' => '0', 'overdue' => '0', 'time' => '09:00']);
$check('3 business days before', $keys(R::slots('2026-12-07', '2026-12-02', '09:00', $sb, $cal)) === 'before:3:2026-12-07');
// over the new year: due Mon 2027-01-04, 2027-01-01 a holiday, 12-29..31 company days off, 01-02 a working Saturday
$check('3 business days before, over the new year', $keys(R::slots('2027-01-04', '2026-12-25', '09:00', $sb, $cal)) === 'before:3:2027-01-04', $keys(R::slots('2027-01-04', '2026-12-25', '09:00', $sb, $cal)));
$sb1 = R::parseSettings(['days' => '1', 'business' => '1', 'today' => '0', 'overdue' => '0', 'time' => '09:00']);
$check('1 business day before: the working Saturday', $keys(R::slots('2027-01-04', '2027-01-02', '09:00', $sb1, $cal)) === 'before:1:2027-01-04');

// overdue: every business day, up to overdue_max
$check('overdue, 1 day', $keys(R::slots('2026-12-03', '2026-12-04', '09:00', $s, $cal)) === 'over:2026-12-04');
$check('overdue: not on a Saturday', R::slots('2026-12-03', '2026-12-05', '09:00', $s, $cal) === []);
$check('overdue, 14 days', $keys(R::slots('2026-12-03', '2026-12-17', '09:00', $s, $cal)) === 'over:2026-12-17');
$check('overdue, past the max', R::slots('2026-12-03', '2026-12-18', '09:00', $s, $cal) === []);
$off = R::parseSettings(['days' => '', 'today' => '0', 'overdue' => '0']);
$check('nothing on: nothing', R::slots('2026-12-03', '2026-12-03', '12:00', $off, $cal) === [] && R::slots('2026-12-03', '2026-12-04', '12:00', $off, $cal) === []);

// a new due date: new slots, so the reminder goes again
$check('another due date, another slot', array_keys(R::slots('2026-12-10', '2026-12-09', '09:00', $s, $cal)) !== array_keys(R::slots('2026-12-03', '2026-12-02', '09:00', $s, $cal)));
$check('a due date with a time', $keys(R::slots('2026-12-03 18:00:00', '2026-12-03', '09:00', $s, $cal)) === 'today:2026-12-03');
$check('not a date', R::slots('soon', '2026-12-03', '09:00', $s, $cal) === []);

echo "\n", $bad === 0 ? "all passed ($ok)" : "$bad failed, $ok passed", "\n";
exit($bad === 0 ? 0 : 1);
