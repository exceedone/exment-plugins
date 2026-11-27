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
require __DIR__ . '/../ExmentGroupware/Calendar/PmLayer.php';

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

// ---- ExmentPM on the calendar (part 4)
use App\Plugins\ExmentGroupware\Calendar\PmLayer as PM;

gw_cal_check('pm: a project keeps its colour, one of the list', PM::color(7) === PM::color(7) && in_array(PM::color(7), PM::COLORS, true) && PM::color(7) !== PM::color(8));
gw_cal_check('pm: start and due: a bar, both days shown', PM::span('2026-12-01', '2026-12-04') === ['start' => '2026-12-01', 'end' => '2026-12-05', 'kind' => 'bar']);
gw_cal_check('pm: due only: a mark on the due date', PM::span(null, '2026-12-04') === ['start' => '2026-12-04', 'end' => '2026-12-05', 'kind' => 'due']);
gw_cal_check('pm: a start after the due date: the due date', PM::span('2026-12-09', '2026-12-04')['kind'] === 'due');
gw_cal_check('pm: start only: that day', PM::span('2026-12-01 00:00:00', '')['start'] === '2026-12-01' && PM::span('2026-12-01', '')['kind'] === 'start');
gw_cal_check('pm: no date (or not one): not shown', PM::span(null, null) === null && PM::span('', 'tomorrow') === null);
gw_cal_check('pm: a bar over the window is in it; one that ended before is not', PM::inWindow(PM::span('2026-11-01', '2027-01-31'), '2026-12-01', '2027-01-01') && !PM::inWindow(PM::span('2026-11-01', '2026-11-30'), '2026-12-01', '2027-01-01'));
gw_cal_check('pm: the last day of the window is in it, the day after is not', PM::inWindow(PM::span(null, '2026-12-31'), '2026-12-01', '2027-01-01') && !PM::inWindow(PM::span(null, '2027-01-01'), '2026-12-01', '2027-01-01'));
gw_cal_check('pm: a user column names the user (one, several, as JSON)', PM::names('5', 5) && PM::names(5, 5) && PM::names(['3', '5'], 5) && PM::names([3, 5], 5) && PM::names('["3","5"]', 5));
gw_cal_check('pm: ...and not another (15 is not 5; nobody is 0)', !PM::names(['15', '51'], 5) && !PM::names(null, 5) && !PM::names('', 5) && !PM::names(['0'], 0));
$cols = ['manager' => 'manager', 'members' => 'members', 'viewers' => 'viewers', 'member_orgs' => 'member_orgs', 'viewer_orgs' => 'viewer_orgs'];
gw_cal_check('pm: on a project as its manager, a member, a viewer', PM::onProject(['manager' => '5'], $cols, 5, []) && PM::onProject(['members' => ['2', '5']], $cols, 5, []) && PM::onProject(['viewers' => ['5']], $cols, 5, []));
gw_cal_check('pm: on a project by an organization', PM::onProject(['viewer_orgs' => ['9']], $cols, 5, [9]) && !PM::onProject(['viewer_orgs' => ['9']], $cols, 5, [8]));
gw_cal_check('pm: not on a project', !PM::onProject(['manager' => '4', 'members' => ['6']], $cols, 5, [1]));
gw_cal_check('pm: done by the status list (is_done), or the word', PM::done('3', [3]) && !PM::done('4', [3]) && PM::done('closed', []) && PM::done(['3'], [3]) && !PM::done('open', []) && !PM::done(null, [3]));

// a working day as ExmentPM's business calendar: 2026-12-05 is a Saturday, 2027-01-11 Coming of Age Day
$days = ['2027-01-11' => ['kind' => 'holiday'], '2026-12-07' => ['kind' => 'off'], '2026-12-12' => ['kind' => 'work']];
gw_cal_check('pm: a weekday works; Saturday, a holiday, a company day off do not', PM::workingDay('2026-12-04', $days) && !PM::workingDay('2026-12-05', $days) && !PM::workingDay('2027-01-11', $days) && !PM::workingDay('2026-12-07', $days));
gw_cal_check('pm: a company working day on a Saturday works', PM::workingDay('2026-12-12', $days));
gw_cal_check('pm: a day off kept, moved before, after, or skipped', PM::shifted('2026-12-05', 'keep', $days) === '2026-12-05' && PM::shifted('2026-12-05', 'before', $days) === '2026-12-04' && PM::shifted('2026-12-05', 'after', $days) === '2026-12-08' && PM::shifted('2026-12-05', 'skip', $days) === null);
gw_cal_check('pm: a working day is not moved', PM::shifted('2026-12-04', 'after', $days) === '2026-12-04' && PM::shifted('2026-12-04', 'skip', $days) === '2026-12-04');

// a monthly schedule on the 5th, from 2026-10-05; today 2026-12-03
$monthly = ['rrule' => 'FREQ=MONTHLY;BYMONTHDAY=5', 'start_date' => '2026-10-05', 'end_date' => null, 'rdates' => '[]', 'shift' => 'keep'];
$dates = function ($list) {
    return array_map(function ($d) {
        return $d['original'] . '>' . $d['date'];
    }, $list);
};
$r = PM::scheduleDates($monthly, '2026-11-30', '2027-03-01', '2026-12-03', [], [], $days);
gw_cal_check('pm: the dates of a schedule from today, as its rule says', $dates($r) === ['2026-12-05>2026-12-05', '2027-01-05>2027-01-05', '2027-02-05>2027-02-05'], $dates($r));
$r = PM::scheduleDates($monthly, '2026-11-30', '2027-03-01', '2026-12-03', [], ['2026-12-05'], $days);
gw_cal_check('pm: a date made into an issue already is not shown (the issue is)', $dates($r) === ['2027-01-05>2027-01-05', '2027-02-05>2027-02-05'], $dates($r));
$r = PM::scheduleDates($monthly, '2026-11-30', '2027-03-01', '2026-12-03', ['2027-01-05' => null, '2027-02-05' => '2027-02-10'], [], $days);
gw_cal_check('pm: a date skipped is gone, a date moved is on its new day', $dates($r) === ['2026-12-05>2026-12-05', '2027-02-05>2027-02-10'], $dates($r));
$r = PM::scheduleDates($monthly, '2027-01-01', '2027-02-01', '2026-12-03', ['2026-12-05' => '2027-01-20'], [], $days);
gw_cal_check('pm: a date moved into the window from outside it is in it', $dates($r) === ['2027-01-05>2027-01-05', '2026-12-05>2027-01-20'], $dates($r));
$r = PM::scheduleDates(['shift' => 'after'] + $monthly, '2026-11-30', '2027-01-01', '2026-12-03', [], [], $days);
gw_cal_check('pm: a date on a day off moves as the schedule says (Saturday 12-05 to Tuesday 12-08: 12-07 is a company day off)', $dates($r) === ['2026-12-05>2026-12-08'], $dates($r));
$r = PM::scheduleDates($monthly, '2026-10-01', '2026-12-01', '2026-12-03', [], [], $days);
gw_cal_check('pm: no date before today (a past one with no issue was not made)', $r === []);
$r = PM::scheduleDates(['end_date' => '2027-01-31'] + $monthly, '2026-11-30', '2027-06-01', '2026-12-03', [], [], $days);
gw_cal_check('pm: not after the schedule ends', count($r) === 2, $dates($r));
$r = PM::scheduleDates(['rrule' => '', 'rdates' => '["2026-12-24","2027-03-01"]'] + $monthly, '2026-12-01', '2027-01-01', '2026-12-03', [], [], $days);
gw_cal_check('pm: a schedule of dates only', $dates($r) === ['2026-12-24>2026-12-24'], $dates($r));
gw_cal_check('pm: a rule it cannot read: nothing', PM::scheduleDates(['rrule' => 'FREQ=HOURLY'] + $monthly, '2026-12-01', '2027-01-01', '2026-12-03', [], [], $days) === []);

echo $failed ? "\n$failed failed\n" : "\nall passed\n";
exit($failed ? 1 : 0);
