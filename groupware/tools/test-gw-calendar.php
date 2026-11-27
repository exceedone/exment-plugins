<?php
// ExmentGroupware: the calendar (ISS02947) - the occurrences of an event (repeats, time zones,
// all day, dates taken out and added, one occurrence changed or cancelled) and what a person sees
// of it - without Exment.
//
//   php groupware/tools/test-gw-calendar.php

require __DIR__ . '/../ExmentGroupware/Calendar/Rrule.php';
require __DIR__ . '/../ExmentGroupware/Calendar/Occurrences.php';
require __DIR__ . '/../ExmentGroupware/Calendar/Visibility.php';
require __DIR__ . '/../ExmentGroupware/Calendar/Reminders.php';
require __DIR__ . '/../ExmentGroupware/Calendar/Resources.php';

use App\Plugins\ExmentGroupware\Calendar\Occurrences as O;
use App\Plugins\ExmentGroupware\Calendar\Visibility as V;
use App\Plugins\ExmentGroupware\Calendar\Reminders as R;

$failed = 0;
function gw_cal_check(string $name, bool $ok, $extra = null): void
{
    global $failed;
    echo ($ok ? 'ok   ' : 'FAIL ') . $name . ($ok || $extra === null ? '' : '  ' . var_export($extra, true)) . "\n";
    $failed += $ok ? 0 : 1;
}
$starts = function (array $occ) {
    return array_column($occ, 'start');
};

// a weekly meeting, Tuesday 10:00-11:00 in Tokyo (01:00-02:00 UTC)
$weekly = ['starts_at' => '2026-12-01 01:00:00', 'ends_at' => '2026-12-01 02:00:00', 'tz' => 'Asia/Tokyo', 'rrule' => 'FREQ=WEEKLY;BYDAY=TU'];
$o = O::between($weekly, '2026-12-01 00:00:00', '2026-12-31 00:00:00');
gw_cal_check('weekly: every Tuesday of the month', $starts($o) === ['2026-12-01 01:00:00', '2026-12-08 01:00:00', '2026-12-15 01:00:00', '2026-12-22 01:00:00', '2026-12-29 01:00:00'], $starts($o));
gw_cal_check('each keeps its length', $o[2]['end'] === '2026-12-15 02:00:00');
$o = O::between($weekly + ['exdates' => ['2026-12-08']], '2026-12-01 00:00:00', '2026-12-16 00:00:00');
gw_cal_check('a date taken out', $starts($o) === ['2026-12-01 01:00:00', '2026-12-15 01:00:00'], $starts($o));
$o = O::between($weekly, '2026-12-01 00:00:00', '2026-12-16 00:00:00', [
    ['original_start' => '2026-12-08 01:00:00', 'cancelled' => true],
    ['original_start' => '2026-12-15 01:00:00', 'starts_at' => '2026-12-16 05:00:00', 'ends_at' => '2026-12-16 06:00:00', 'title' => '移した回'],
]);
gw_cal_check('one cancelled; one moved out of the window is gone from it', $starts($o) === ['2026-12-01 01:00:00'], $starts($o));
$o = O::between($weekly, '2026-12-16 00:00:00', '2026-12-17 00:00:00', [
    ['original_start' => '2026-12-15 01:00:00', 'starts_at' => '2026-12-16 05:00:00', 'ends_at' => '2026-12-16 06:00:00', 'title' => '移した回'],
]);
gw_cal_check('...and is in the window it moved to, with its own title', count($o) === 1 && $o[0]['start'] === '2026-12-16 05:00:00' && $o[0]['title'] === '移した回' && $o[0]['original'] === '2026-12-15 01:00:00' && $o[0]['changed'], $o);

// summer time: 09:00 in New York stays 09:00 (14:00 UTC in winter, 13:00 in summer)
$ny = ['starts_at' => '2027-03-08 14:00:00', 'ends_at' => '2027-03-08 15:00:00', 'tz' => 'America/New_York', 'rrule' => 'FREQ=DAILY;COUNT=10'];
$o = O::between($ny, '2027-03-13 00:00:00', '2027-03-16 00:00:00');
gw_cal_check('over a change of summer time, the local time stays', $starts($o) === ['2027-03-13 14:00:00', '2027-03-14 13:00:00', '2027-03-15 13:00:00'], $starts($o));

// all day: a holiday trip of 2 days, yearly
$trip = ['starts_at' => '2026-12-28 00:00:00', 'ends_at' => '2026-12-30 00:00:00', 'all_day' => true, 'tz' => 'Asia/Tokyo', 'rrule' => 'FREQ=YEARLY'];
$o = O::between($trip, '2027-12-29 00:00:00', '2027-12-30 00:00:00');
gw_cal_check('all day: days, not times; one a year; in the window it overlaps', count($o) === 1 && $o[0]['start'] === '2027-12-28 00:00:00' && $o[0]['end'] === '2027-12-30 00:00:00', $o);

// once, no repeat
$once = ['starts_at' => '2026-12-03 06:00:00', 'ends_at' => '2026-12-03 07:00:00', 'tz' => 'Asia/Tokyo'];
gw_cal_check('once: in its window', count(O::between($once, '2026-12-03 00:00:00', '2026-12-04 00:00:00')) === 1);
gw_cal_check('once: not in another', O::between($once, '2026-12-04 00:00:00', '2026-12-05 00:00:00') === []);
gw_cal_check('the window\'s end is not in it', O::between($once, '2026-12-01 00:00:00', '2026-12-03 06:00:00') === []);
gw_cal_check('a monthly rule: the 2nd Tuesday', $starts(O::between(['starts_at' => '2026-12-08 01:00:00', 'ends_at' => '2026-12-08 02:00:00', 'tz' => 'Asia/Tokyo', 'rrule' => 'FREQ=MONTHLY;BYDAY=2TU'], '2027-01-01 00:00:00', '2027-03-01 00:00:00')) === ['2027-01-12 01:00:00', '2027-02-09 01:00:00']);
gw_cal_check('a rule it cannot read: the first occurrence only', count(O::between(['starts_at' => '2026-12-08 01:00:00', 'ends_at' => '2026-12-08 02:00:00', 'tz' => 'Asia/Tokyo', 'rrule' => 'FREQ=HOURLY'], '2026-12-01 00:00:00', '2027-01-01 00:00:00')) === 1);

// ---- who sees what
$e = ['owner_user_id' => 5, 'visibility' => 'attendees', 'org_id' => 0];
gw_cal_check('the owner and the attendees: all', V::of($e, 5, [7], []) === V::FULL && V::of($e, 7, [7], []) === V::FULL);
gw_cal_check('attendees only: the others 予定あり', V::of($e, 9, [7], []) === V::BUSY);
gw_cal_check('all: everyone', V::of(['visibility' => 'all', 'owner_user_id' => 5], 9, [], []) === V::FULL);
gw_cal_check('org: its members all, the others 予定あり', V::of(['visibility' => 'org', 'org_id' => 3, 'owner_user_id' => 5], 9, [], [3]) === V::FULL && V::of(['visibility' => 'org', 'org_id' => 3, 'owner_user_id' => 5], 9, [], [4]) === V::BUSY);
gw_cal_check('busy: the others 予定あり', V::of(['visibility' => 'busy', 'owner_user_id' => 5], 9, [], []) === V::BUSY);
gw_cal_check('an outside calendar shown busy, even "all"', V::of(['visibility' => 'all', 'show_as' => 'busy', 'owner_user_id' => 5], 9, [], []) === V::BUSY);
gw_cal_check('nobody logged in (0) is never the owner', V::of(['visibility' => 'busy', 'owner_user_id' => 0], 0, [], []) === V::BUSY);
$item = ['id' => 12, 'start' => 's', 'end' => 'e', 'title' => '人事面談', 'location' => '会議室A', 'body' => 'secret', 'attendees' => [7], 'owner' => 5];
$busy = V::shown($item, V::BUSY, '予定あり');
gw_cal_check('a busy one keeps the time and nothing else', $busy === ['busy' => true, 'start' => 's', 'end' => 'e', 'all_day' => false, 'owner' => 5, 'title' => '予定あり'], $busy);
gw_cal_check('nothing of it leaks in its JSON', strpos(json_encode($busy, JSON_UNESCAPED_UNICODE), '人事') === false && strpos(json_encode($busy), 'secret') === false && !isset($busy['id']));
gw_cal_check('full: as it is', V::shown($item, V::FULL, '予定あり') === $item);

// ---- reminders (part 2)
$utcNow = function ($s) {
    return new \DateTimeImmutable($s, new \DateTimeZone('UTC'));
};
gw_cal_check('10 minutes before: due at its time', R::dueMinutes('2026-12-01 01:00:00', [10], $utcNow('2026-12-01 00:50:00')) === [10]);
gw_cal_check('not yet', R::dueMinutes('2026-12-01 01:00:00', [10], $utcNow('2026-12-01 00:49:59')) === []);
gw_cal_check('reached late (20 min): still sent', R::dueMinutes('2026-12-01 01:00:00', [10], $utcNow('2026-12-01 01:10:00')) === [10]);
gw_cal_check('reached too late (40 min): not sent', R::dueMinutes('2026-12-01 01:00:00', [10], $utcNow('2026-12-01 01:30:00')) === []);
gw_cal_check('a day before and at the start, each at its time', R::dueMinutes('2026-12-02 01:00:00', [1440, 0], $utcNow('2026-12-01 01:00:00')) === [1440] && R::dueMinutes('2026-12-02 01:00:00', [1440, 0], $utcNow('2026-12-02 01:00:00')) === [0]);
gw_cal_check('no choice made: bell and mail', R::channels(null, null, '12:00') === ['bell' => true, 'mail' => true, 'teams' => false]);
gw_cal_check('bell only', R::channels('bell', null, '12:00') === ['bell' => true, 'mail' => false, 'teams' => false]);
gw_cal_check('both', R::channels('both', [], '12:00') === ['bell' => true, 'mail' => true, 'teams' => true]);
gw_cal_check('quiet hours over midnight: the bell only', R::channels('both', ['quiet' => ['from' => '22:00', 'to' => '07:00']], '23:30') === ['bell' => true, 'mail' => false, 'teams' => false] && R::channels('both', ['quiet' => ['from' => '22:00', 'to' => '07:00']], '08:00')['mail'] === true);
$w = ['rem_now' => 'まもなく始まります', 'rem_in_minutes' => 'あと :n 分で始まります', 'rem_in_hours' => 'あと :n 時間で始まります', 'rem_in_days' => ':n 日後です'];
gw_cal_check('the words of a reminder', R::text(0, $w) === 'まもなく始まります' && R::text(15, $w) === 'あと 15 分で始まります' && R::text(120, $w) === 'あと 2 時間で始まります' && R::text(2880, $w) === '2 日後です');
gw_cal_check('the choices of the form are the ones accepted', R::CHOICES === [0, 5, 10, 15, 30, 60, 120, 1440, 2880]);

// ---- rooms and equipment (part 3)
use App\Plugins\ExmentGroupware\Calendar\Resources as RS;

$o = function ($s, $e) {
    return ['start' => $s, 'end' => $e];
};
gw_cal_check('rooms: an overlap is a clash', RS::overlaps([$o('2026-12-01 01:00:00', '2026-12-01 02:00:00')], [$o('2026-12-01 01:30:00', '2026-12-01 03:00:00')]) === [$o('2026-12-01 01:00:00', '2026-12-01 02:00:00')]);
gw_cal_check('rooms: one ends as the other starts: no clash', RS::overlaps([$o('2026-12-01 01:00:00', '2026-12-01 02:00:00')], [$o('2026-12-01 02:00:00', '2026-12-01 03:00:00')]) === []);
gw_cal_check('rooms: one inside the other: a clash', count(RS::overlaps([$o('2026-12-01 00:00:00', '2026-12-01 05:00:00')], [$o('2026-12-01 01:00:00', '2026-12-01 02:00:00')])) === 1);
gw_cal_check('rooms: another day: no clash', RS::overlaps([$o('2026-12-01 01:00:00', '2026-12-01 02:00:00')], [$o('2026-12-02 01:00:00', '2026-12-02 02:00:00')]) === []);
$many = [];
for ($i = 1; $i <= 30; $i++) {
    $d = sprintf('2027-01-%02d', $i);
    $many[] = $o($d . ' 01:00:00', $d . ' 02:00:00');
}
gw_cal_check('rooms: a repeating clash is said at most ' . RS::MAX_CLASHES . ' times', count(RS::overlaps($many, $many)) === RS::MAX_CLASHES);
// a repeating event against a once-only one, through the occurrences
$weekly = ['starts_at' => '2026-12-01 01:00:00', 'ends_at' => '2026-12-01 02:00:00', 'all_day' => 0, 'tz' => 'Asia/Tokyo', 'rrule' => 'FREQ=WEEKLY', 'exdates' => null];
$once = ['starts_at' => '2026-12-15 01:30:00', 'ends_at' => '2026-12-15 02:30:00', 'all_day' => 0, 'tz' => 'Asia/Tokyo', 'rrule' => null, 'exdates' => null];
$mine = \App\Plugins\ExmentGroupware\Calendar\Occurrences::between($weekly, '2026-12-01 00:00:00', '2027-01-01 00:00:00');
$theirs = \App\Plugins\ExmentGroupware\Calendar\Occurrences::between($once, '2026-12-01 00:00:00', '2027-01-01 00:00:00');
$c = RS::overlaps($mine, $theirs);
gw_cal_check('rooms: a weekly meeting clashes with a once-only one on its day only', count($c) === 1 && $c[0]['start'] === '2026-12-15 01:00:00', $c);

echo $failed ? "\n$failed failed\n" : "\nall passed\n";
exit($failed ? 1 : 0);
