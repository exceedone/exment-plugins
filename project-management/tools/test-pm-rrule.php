<?php
// ExmentPM の繰り返しの予定の日付（ISS00432 段 2）を、Exment なしで試す: php project-management/tools/test-pm-rrule.php
require __DIR__ . '/../ExmentPM/JapaneseHolidays.php';
require __DIR__ . '/../ExmentPM/BusinessCalendar.php';
require __DIR__ . '/../ExmentPM/PortalRrule.php';
use App\Plugins\ExmentPM\BusinessCalendar as C;
use App\Plugins\ExmentPM\PortalRrule as R;

$ok = 0; $bad = 0;
$check = function ($label, $cond, $extra = '') use (&$ok, &$bad) { echo ($cond ? 'OK  ' : 'NG  '), $label, $cond ? '' : "  $extra", "\n"; $cond ? $ok++ : $bad++; };
$eq = function ($label, $got, $want) use ($check) { $check($label, $got === $want, 'got ' . json_encode($got) . ' want ' . json_encode($want)); };

// what is read and what is refused
$check('parse a monthly rule', R::parse('RRULE:FREQ=MONTHLY;BYDAY=2TU') !== null);
foreach (['FREQ=HOURLY', 'FREQ=DAILY;BYDAY=2MO', 'FREQ=MONTHLY;BYMONTHDAY=0', 'FREQ=MONTHLY;BYMONTHDAY=32', 'FREQ=WEEKLY;WKST=SU', 'FREQ=MONTHLY;BYHOUR=9', 'INTERVAL=2', 'FREQ=MONTHLY;UNTIL=2026-12-01', 'FREQ=MONTHLY;BYMONTH=13', 'nonsense'] as $bad1) {
    $check("refuse $bad1", R::parse($bad1) === null);
}
$check('an empty rule is RDATE only', R::parse('')['FREQ'] === null);

// yearly
$eq('every April 1', R::between('FREQ=YEARLY;BYMONTH=4;BYMONTHDAY=1', '2026-04-01', '2026-01-01', '2029-12-31'), ['2026-04-01', '2027-04-01', '2028-04-01', '2029-04-01']);
$eq('yearly from the start day', R::between('FREQ=YEARLY', '2026-02-28', '2026-01-01', '2028-12-31'), ['2026-02-28', '2027-02-28', '2028-02-28']);
$eq('every Feb 29 (leap years only)', R::between('FREQ=YEARLY;BYMONTH=2;BYMONTHDAY=29', '2024-02-29', '2024-01-01', '2032-12-31'), ['2024-02-29', '2028-02-29', '2032-02-29']);
$eq('last day of March', R::between('FREQ=YEARLY;BYMONTH=3;BYMONTHDAY=-1', '2026-01-01', '2026-01-01', '2027-12-31'), ['2026-03-31', '2027-03-31']);

// monthly
$eq('the 25th', R::between('FREQ=MONTHLY;BYMONTHDAY=25', '2026-11-01', '2026-11-01', '2027-02-28'), ['2026-11-25', '2026-12-25', '2027-01-25', '2027-02-25']);
$eq('the 2nd Tuesday', R::between('FREQ=MONTHLY;BYDAY=2TU', '2026-11-01', '2026-11-01', '2027-01-31'), ['2026-11-10', '2026-12-08', '2027-01-12']);
$eq('the last Friday', R::between('FREQ=MONTHLY;BYDAY=-1FR', '2026-11-01', '2026-11-01', '2027-01-31'), ['2026-11-27', '2026-12-25', '2027-01-29']);
$eq('the last day', R::between('FREQ=MONTHLY;BYMONTHDAY=-1', '2027-01-01', '2027-01-01', '2027-04-30'), ['2027-01-31', '2027-02-28', '2027-03-31', '2027-04-30']);
$eq('the 31st: months without one have none', R::between('FREQ=MONTHLY', '2027-01-31', '2027-01-01', '2027-05-31'), ['2027-01-31', '2027-03-31', '2027-05-31']);
$eq('the last weekday (BYSETPOS)', R::between('FREQ=MONTHLY;BYDAY=MO,TU,WE,TH,FR;BYSETPOS=-1', '2026-11-01', '2026-11-01', '2027-01-31'), ['2026-11-30', '2026-12-31', '2027-01-29']);
$eq('every 3 months', R::between('FREQ=MONTHLY;INTERVAL=3;BYMONTHDAY=10', '2026-01-10', '2026-01-01', '2026-12-31'), ['2026-01-10', '2026-04-10', '2026-07-10', '2026-10-10']);
$eq('Friday the 13th', R::between('FREQ=MONTHLY;BYDAY=FR;BYMONTHDAY=13', '2026-01-01', '2026-01-01', '2026-12-31'), ['2026-02-13', '2026-03-13', '2026-11-13']);

// weekly and daily
$eq('Mondays and Thursdays', R::between('FREQ=WEEKLY;BYDAY=MO,TH', '2026-11-26', '2026-11-26', '2026-12-07'), ['2026-11-26', '2026-11-30', '2026-12-03', '2026-12-07']);
$eq('every other Wednesday', R::between('FREQ=WEEKLY;INTERVAL=2;BYDAY=WE', '2026-11-25', '2026-11-25', '2026-12-31'), ['2026-11-25', '2026-12-09', '2026-12-23']);
$eq('weekly from the start day', R::between('FREQ=WEEKLY', '2026-11-27', '2026-11-27', '2026-12-11'), ['2026-11-27', '2026-12-04', '2026-12-11']);
$eq('daily', R::between('FREQ=DAILY', '2026-12-30', '2026-12-30', '2027-01-02'), ['2026-12-30', '2026-12-31', '2027-01-01', '2027-01-02']);
$eq('every 3 days', R::between('FREQ=DAILY;INTERVAL=3', '2026-12-01', '2026-12-01', '2026-12-10'), ['2026-12-01', '2026-12-04', '2026-12-07', '2026-12-10']);

// COUNT, UNTIL, the window, RDATE
$eq('COUNT counts from the start', R::between('FREQ=MONTHLY;BYMONTHDAY=1;COUNT=3', '2026-01-01', '2026-02-01', '2026-12-31'), ['2026-02-01', '2026-03-01']);
$eq('UNTIL', R::between('FREQ=MONTHLY;BYMONTHDAY=1;UNTIL=20260401', '2026-01-01', '2026-01-01', '2026-12-31'), ['2026-01-01', '2026-02-01', '2026-03-01', '2026-04-01']);
$eq('until of the schedule', R::between('FREQ=MONTHLY;BYMONTHDAY=1', '2026-01-01', '2026-01-01', '2026-12-31', [], '2026-02-15'), ['2026-01-01', '2026-02-01']);
$eq('not before the start', R::between('FREQ=MONTHLY;BYMONTHDAY=1', '2026-03-15', '2026-01-01', '2026-05-31'), ['2026-04-01', '2026-05-01']);
$eq('RDATE only', R::between('', '2026-01-01', '2026-01-01', '2026-12-31', ['2026-07-07', '2026-03-03', 'x', '2027-01-01']), ['2026-03-03', '2026-07-07']);
$eq('RDATE with a rule, once each', R::between('FREQ=YEARLY;BYMONTH=4;BYMONTHDAY=1', '2026-04-01', '2026-01-01', '2026-12-31', ['2026-04-01', '2026-09-30']), ['2026-04-01', '2026-09-30']);
$check('a bad rule: null', R::between('FREQ=SECONDLY', '2026-01-01', '2026-01-01', '2026-12-31') === null);
$check('a far window ends', count(R::between('FREQ=DAILY', '2026-01-01', '2026-01-01', '2099-12-31')) === 5000);

// a day off
$cal = new C(['2026-12-29' => 'off', '2026-12-30' => 'off', '2026-12-31' => 'off']);
$eq('25th on a Friday: kept', R::shift('2026-12-25', 'before', $cal), '2026-12-25');
$eq('day off: before', R::shift('2027-04-25', 'before', $cal), '2027-04-23');
$eq('day off: after', R::shift('2027-04-25', 'after', $cal), '2027-04-26');
$eq('day off: keep', R::shift('2027-04-25', 'keep', $cal), '2027-04-25');
$eq('day off: skip', R::shift('2027-04-25', 'skip', $cal), null);
$eq('last business day of the year', R::shift('2026-12-31', 'before', $cal), '2026-12-28');
$eq('after the new year', R::shift('2026-12-31', 'after', $cal), '2027-01-04');
$eq('last business day of March 2029 (31st a Saturday)', R::shift(R::between('FREQ=YEARLY;BYMONTH=3;BYMONTHDAY=-1', '2029-01-01', '2029-01-01', '2029-12-31')[0], 'before', $cal), '2029-03-30');

echo "\n", $bad === 0 ? "all passed ($ok)" : "$bad failed, $ok passed", "\n";
exit($bad === 0 ? 0 : 1);
