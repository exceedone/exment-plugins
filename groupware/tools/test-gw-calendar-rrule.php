<?php
// ExmentGroupware: the calendar's Rrule against the way it walked before (fixtures/RruleWalk.php:
// every repeat from its first period). Rrule now starts a period before the window when a rule has
// no COUNT; the dates in the window must be the same. Random rules with a fixed seed, so a failure
// can be run again; a few thousand cases.
//
//   php groupware/tools/test-gw-calendar-rrule.php

require __DIR__ . '/../ExmentGroupware/Calendar/Rrule.php';
require __DIR__ . '/../ExmentGroupware/Calendar/Ics.php';
require __DIR__ . '/fixtures/RruleWalk.php';

use App\Plugins\ExmentGroupware\Calendar\Ics;
use App\Plugins\ExmentGroupware\Calendar\Rrule;
use GwTest\RruleWalk;

mt_srand(20261128);
$pick = function (array $list, int $min, int $max) {
    shuffle($list);
    return array_slice($list, 0, mt_rand($min, $max));
};
$days = ['MO', 'TU', 'WE', 'TH', 'FR', 'SA', 'SU'];
$rule = function () use ($pick, $days) {
    $freq = ['DAILY', 'WEEKLY', 'MONTHLY', 'YEARLY'][mt_rand(0, 3)];
    $p = ['FREQ=' . $freq];
    if (mt_rand(0, 1)) {
        $p[] = 'INTERVAL=' . mt_rand(1, 6);
    }
    $ordinal = in_array($freq, ['MONTHLY', 'YEARLY'], true);
    if (mt_rand(0, 2) === 0 || $freq === 'WEEKLY' && mt_rand(0, 1)) {
        $p[] = 'BYDAY=' . implode(',', array_map(function ($d) use ($ordinal) {
            return ($ordinal && mt_rand(0, 2) === 0 ? [1, 2, 3, 4, -1, -2][mt_rand(0, 5)] : '') . $d;
        }, $pick($days, 1, 4)));
    }
    if ($freq !== 'WEEKLY' && mt_rand(0, 3) === 0) {
        $p[] = 'BYMONTHDAY=' . implode(',', $pick([1, 2, 10, 15, 28, 29, 30, 31, -1, -2, -7], 1, 3));
    }
    if ($freq !== 'WEEKLY' && mt_rand(0, 4) === 0 || $freq === 'YEARLY' && mt_rand(0, 1)) {
        $p[] = 'BYMONTH=' . implode(',', $pick(range(1, 12), 1, 4));
    }
    if ($freq !== 'DAILY' && mt_rand(0, 5) === 0) {
        $p[] = 'BYSETPOS=' . implode(',', $pick([1, 2, -1, -2], 1, 2));
    }
    if (mt_rand(0, 3) === 0) {
        $p[] = 'UNTIL=' . date('Ymd', mt_rand(strtotime('2000-01-01'), strtotime('2035-12-31')));
    }
    return implode(';', $p);
};

$cases = 0;
$bad = 0;
$dates = 0;
$started = microtime(true);
while ($cases < 3000) {
    $r = $rule();
    if (Rrule::parse($r) === null) {
        continue;
    }
    $start = date('Y-m-d', mt_rand(strtotime('1995-01-01'), strtotime('2026-12-31')));
    // the window up to 12 years after the start, sometimes before it
    $from = date('Y-m-d', strtotime($start) + 86400 * mt_rand(-60, 365 * 12));
    $to = date('Y-m-d', strtotime($from) + 86400 * mt_rand(0, 120));
    $rdates = mt_rand(0, 4) === 0 ? [date('Y-m-d', strtotime($from) + 86400 * mt_rand(-5, 30))] : [];
    $new = Rrule::between($r, $start, $from, $to, $rdates);
    $old = RruleWalk::between($r, $start, $from, $to, $rdates);
    $cases++;
    $dates += count($old ?? []);
    if ($new !== $old) {
        $bad++;
        if ($bad <= 10) {
            echo "FAIL $r start=$start from=$from to=$to\n     new: " . json_encode($new) . "\n     old: " . json_encode($old) . "\n";
        }
    }
}
// with COUNT nothing changes: it still walks from the start
$count = ['FREQ=DAILY;COUNT=40', 'FREQ=WEEKLY;BYDAY=MO,TH;COUNT=25', 'FREQ=MONTHLY;BYDAY=-1FR;COUNT=12'];
foreach ($count as $r) {
    $cases++;
    if (Rrule::between($r, '2024-01-03', '2024-03-01', '2025-12-31') !== RruleWalk::between($r, '2024-01-03', '2024-03-01', '2025-12-31')) {
        $bad++;
        echo "FAIL $r (COUNT)\n";
    }
}
// a COUNT read from an outside calendar becomes an UNTIL (Ics::lastOfCount). The common rules are
// worked out, not walked: their last date must be the one the walk from the start finds (the
// other rules are walked by Rrule itself, as before)
$lasts = 0;
while ($lasts < 1500) {
    $r = ['FREQ=DAILY', 'FREQ=DAILY;INTERVAL=' . mt_rand(2, 9), 'FREQ=WEEKLY', 'FREQ=WEEKLY;INTERVAL=' . mt_rand(2, 4),
        'FREQ=WEEKLY;BYDAY=' . implode(',', $pick($days, 1, 5)), 'FREQ=WEEKLY;INTERVAL=' . mt_rand(2, 4) . ';BYDAY=' . implode(',', $pick($days, 1, 5)),
        'FREQ=DAILY;BYDAY=' . implode(',', $pick($days, 1, 5))][mt_rand(0, 6)];
    $r .= ';COUNT=' . mt_rand(1, 1000);
    $p = Rrule::parse($r);
    if ($p === null) {
        continue;
    }
    $start = date('Y-m-d', mt_rand(strtotime('2000-01-01'), strtotime('2026-12-31')));
    $walk = RruleWalk::between($r, $start, $start, date('Y-m-d', strtotime($start . ' +80 year')));
    $want = empty($walk) ? $start : end($walk);
    $got = Ics::lastOfCount($r, $p, $start);
    $lasts++;
    $cases++;
    if ($got !== $want) {
        $bad++;
        if ($bad <= 10) {
            echo "FAIL last of $r start=$start: $got, not $want\n";
        }
    }
}
printf("%d cases, %d dates in their windows, %.1f s\n", $cases, $dates, microtime(true) - $started);
echo $bad === 0 ? "all passed\n" : "$bad failed\n";
exit($bad === 0 ? 0 : 1);
