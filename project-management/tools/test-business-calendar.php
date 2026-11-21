<?php
// ExmentPM の祝日と営業日（ISS00432）を、Exment なしで試す: php project-management/tools/test-business-calendar.php
require __DIR__ . '/../ExmentPM/JapaneseHolidays.php';
require __DIR__ . '/../ExmentPM/BusinessCalendar.php';
use App\Plugins\ExmentPM\JapaneseHolidays as H;
use App\Plugins\ExmentPM\BusinessCalendar as C;

$ok = 0; $bad = 0;
$check = function ($label, $cond, $extra = '') use (&$ok, &$bad) { echo ($cond ? 'OK  ' : 'NG  '), $label, $cond ? '' : "  $extra", "\n"; $cond ? $ok++ : $bad++; };

// 内閣府「国民の祝日について」の表
$expected = [
  2025 => ['01-01','01-13','02-11','02-23','02-24','03-20','04-29','05-03','05-04','05-05','05-06','07-21','08-11','09-15','09-23','10-13','11-03','11-23','11-24'],
  2026 => ['01-01','01-12','02-11','02-23','03-20','04-29','05-03','05-04','05-05','05-06','07-20','08-11','09-21','09-22','09-23','10-12','11-03','11-23'],
  2027 => ['01-01','01-11','02-11','02-23','03-21','03-22','04-29','05-03','05-04','05-05','07-19','08-11','09-20','09-23','10-11','11-03','11-23'],
  2020 => ['01-01','01-13','02-11','02-23','02-24','03-20','04-29','05-03','05-04','05-05','05-06','07-23','07-24','08-10','09-21','09-22','11-03','11-23'],
  2021 => ['01-01','01-11','02-11','02-23','03-20','04-29','05-03','05-04','05-05','07-22','07-23','08-08','08-09','09-20','09-23','11-03','11-23'],
];
foreach ($expected as $y => $list) {
    $got = array_keys(H::forYear($y));
    $want = array_map(fn($d) => "$y-$d", $list);
    $check("holidays $y", $got === $want, 'missing ' . implode(',', array_diff($want, $got)) . ' extra ' . implode(',', array_diff($got, $want)));
}
$check('2026-09-22 is 国民の休日', H::name('2026-09-22') === '国民の休日');
$check('2026-05-06 is 振替休日', H::name('2026-05-06') === '振替休日');
$check('equinoxes 2028-2030', H::vernalEquinox(2028) === 20 && H::autumnalEquinox(2028) === 22 && H::vernalEquinox(2030) === 20 && H::autumnalEquinox(2030) === 23);
$check('outside range is empty', H::forYear(2019) === [] && H::forYear(2100) === []);

$c = new C([
    '2026-08-13' => ['kind' => 'off', 'name' => '夏休み'],
    '2026-08-14' => 'off',
    '2026-11-21' => ['kind' => 'work', 'name' => '休日出勤'], // Saturday
    'nonsense' => 'off',
    '2026-12-31' => 'maybe',
]);
$check('weekday is business', $c->isBusinessDay('2026-11-20'));
$check('Sunday is not', !$c->isBusinessDay('2026-11-22'));
$check('holiday is not', !$c->isBusinessDay('2026-11-23'));
$check('company off is not', !$c->isBusinessDay('2026-08-13'));
$check('company work on Saturday is', $c->isBusinessDay('2026-11-21'));
$check('unknown kind ignored', $c->isBusinessDay('2026-12-31'));
$check('datetime string accepted', $c->isBusinessDay('2026-11-20 23:59:00'));
$check('labels', $c->dayOffLabel('2026-08-13') === '夏休み' && $c->dayOffLabel('2026-11-23') === '勤労感謝の日' && $c->dayOffLabel('2026-11-22') === '' && $c->dayOffLabel('2026-11-20') === null);

// 2026-11-20 (Fri) + 1 → 11-21 is a company working Saturday
$check('add 1 from Fri lands on working Sat', $c->addBusinessDays('2026-11-20', 1) === '2026-11-21');
// 11-21 + 1 → skip Sun 22, holiday Mon 23 → Tue 24
$check('add 1 skips Sunday and holiday', $c->addBusinessDays('2026-11-21', 1) === '2026-11-24');
$check('3 business days before 2026-05-07', $c->addBusinessDays('2026-05-07', -3) === '2026-04-28'); // 5/1, 4/30, (4/29 昭和の日), 4/28
$check('add 0 is the date', $c->addBusinessDays('2026-11-22', 0) === '2026-11-22');
$check('shift before', $c->shift('2026-08-16', C::BEFORE) === '2026-08-12');
$check('shift after', $c->shift('2026-08-13', C::AFTER) === '2026-08-17');
$check('shift keep', $c->shift('2026-08-13', C::KEEP) === '2026-08-13');
$check('shift business day kept', $c->shift('2026-08-12', C::BEFORE) === '2026-08-12');
$check('last business day of March 2026', $c->nthBusinessDayOfMonth(2026, 3, -1) === '2026-03-31');
$check('last business day of Jan 2027 (31 is Sunday)', $c->nthBusinessDayOfMonth(2027, 1, -1) === '2027-01-29');
$check('first business day of Jan 2027', $c->nthBusinessDayOfMonth(2027, 1, 1) === '2027-01-04');
$check('first business day of May 2026', $c->nthBusinessDayOfMonth(2026, 5, 1) === '2026-05-01');
$r = $c->range('2026-11-20', '2026-11-24');
$check('range', array_keys($r) === ['2026-11-21', '2026-11-22', '2026-11-23'] && $r['2026-11-21']['business'] === true && $r['2026-11-23']['label'] === '勤労感謝の日');

$none = new C([], [0, 6], null);
$check('no national holidays', $none->isBusinessDay('2026-11-23'));
$all = new C([], [0, 1, 2, 3, 4, 5, 6], null);
try { $all->addBusinessDays('2026-01-01', 1); $check('all days off throws', false); } catch (RuntimeException $e) { $check('all days off throws', true); }
try { $c->isBusinessDay('2026-02-30'); $check('bad date throws', false); } catch (InvalidArgumentException $e) { $check('bad date throws', true); }
try { $c->shift('2026-08-13', 'sideways'); $check('bad shift throws', false); } catch (InvalidArgumentException $e) { $check('bad shift throws', true); }

echo "passed $ok, failed $bad\n";
exit($bad ? 1 : 0);
