<?php

/**
 * ExmentPM: the pages of 「最近の更新」 (UX S15, M5) - PortalFeedPage::cut and
 * scan, without a database.
 *
 *   php project-management/tools/test-pm-feed.php
 */

use App\Plugins\ExmentPM\PortalFeedPage;

require __DIR__ . '/../ExmentPM/PortalFeedPage.php';

$failed = 0;
function pm_feed_check($name, $cond)
{
    global $failed;
    echo ($cond ? 'ok   ' : 'FAIL ') . $name . PHP_EOL;
    if (!$cond) {
        $failed++;
    }
}
$fmt = function ($ts) {
    return 'at ' . $ts;
};
$e = function ($ts, $id) {
    return ['ts' => $ts, 'id' => $id];
};

// cut by the limit, after the whole of the last second shown
$feed = [$e('2026-01-01 10:00:05', 1), $e('2026-01-01 10:00:04', 2), $e('2026-01-01 10:00:03', 3), $e('2026-01-01 10:00:03', 4), $e('2026-01-01 10:00:01', 5)];
$out = PortalFeedPage::cut($feed, 3, null, $fmt);
pm_feed_check('the last second shown is shown whole', array_column($out['feed'], 'id') === [1, 2, 3, 4]);
pm_feed_check('the next page reads before it', $out['more'] === true && $out['next'] === '2026-01-01 10:00:03');
pm_feed_check('each row gets its shown time', $out['feed'][0]['at'] === 'at 2026-01-01 10:00:05');

$out = PortalFeedPage::cut($feed, 10, null, $fmt);
pm_feed_check('everything fits: no more', count($out['feed']) === 5 && $out['more'] === false && $out['next'] === '');

// a source stopped at the floor: nothing older from the others
$out = PortalFeedPage::cut($feed, 10, '2026-01-01 10:00:03', $fmt);
pm_feed_check('rows under the floor wait for the next page', array_column($out['feed'], 'id') === [1, 2, 3, 4]);
pm_feed_check('...which starts at the floor', $out['more'] === true && $out['next'] === '2026-01-01 10:00:03');

// scan: pages of 3 rows, keeps this project's (ids 1, 2), stops at 2 kept
$rows = [];
foreach ([[1, '12'], [9, '11'], [2, '10'], [2, '10'], [9, '09'], [1, '08'], [1, '07']] as $r) {
    $rows[] = (object) ['pid' => $r[0], 'created_at' => '2026-01-01 10:00:' . $r[1]];
}
$page = function ($skip, $take) use ($rows) {
    return array_slice($rows, $skip, $take);
};
$floor = null;
$got = PortalFeedPage::scan($page, [1 => 0, 2 => 1], 'pid', 2, $floor, 3, 2);
pm_feed_check('the rest of the second is read after enough are kept', count($got) === 3);
pm_feed_check('the floor is that second', $floor === '2026-01-01 10:00:10');

$floor = null;
$got = PortalFeedPage::scan($page, [5 => 0], 'pid', 2, $floor, 3, 1);
pm_feed_check('at the page limit: the floor is the last row read', $got === [] && $floor === '2026-01-01 10:00:10');

$floor = null;
$got = PortalFeedPage::scan($page, [1 => 0], 'pid', 10, $floor, 3, 5);
pm_feed_check('read to the end: no floor', count($got) === 3 && $floor === null);

if ($failed) {
    echo PHP_EOL . $failed . ' failed' . PHP_EOL;
    exit(1);
}
echo PHP_EOL . 'all passed' . PHP_EOL;
