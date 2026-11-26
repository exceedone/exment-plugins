<?php
// ExmentPM: which issues the parent picker offers when issues nest a few levels.
//
//   php project-management/tools/test-issue-levels.php
//
// PortalRules::refusedAmong() on a map id => parent: an issue may not be the
// parent of one of its own (a loop), nor of an issue whose subtree would end
// deeper than the levels allowed. Plain PHP: the rule needs no database (the classes
// PortalRules names in its use lines are never loaded by this test).

require __DIR__ . '/../ExmentPM/PortalRules.php';

use App\Plugins\ExmentPM\PortalRules;

$failed = 0;
$test = function (string $name, callable $fn) use (&$failed) {
    try {
        $fn();
        echo "ok   $name\n";
    } catch (\Throwable $e) {
        $failed++;
        echo "FAIL $name\n     " . $e->getMessage() . "\n";
    }
};
$same = function ($want, $got, string $what) {
    sort($want);
    sort($got);
    if ($want !== $got) {
        throw new \RuntimeException("$what: want " . json_encode($want) . ', got ' . json_encode($got));
    }
};

// 1 > 2 > 3 > 4 > 5 (five levels), 6 under 1, 7 alone, 8 > 9 (two levels)
$parentOf = [1 => null, 2 => 1, 3 => 2, 4 => 3, 5 => 4, 6 => 1, 7 => null, 8 => null, 9 => 8];

$test('a new issue: anything above level 5 may be its parent', function () use ($parentOf, $same) {
    $same([5], PortalRules::refusedAmong($parentOf, null, 5), 'refused');
});

$test('three levels allowed: level 3 and deeper may not be a parent', function () use ($parentOf, $same) {
    $same([3, 4, 5], PortalRules::refusedAmong($parentOf, null, 3), 'refused');
});

$test('an issue may not go under itself or under its own children (a loop)', function () use ($parentOf, $same) {
    // 3 with 4 and 5 under it: three levels hang from it, so its parent is level 2 at most
    $same([3, 4, 5], PortalRules::refusedAmong($parentOf, 3, 5), 'refused for 3');
    // four levels allowed: only under a level 1 issue (or none)
    $same([3, 4, 5, 2, 6, 9], PortalRules::refusedAmong($parentOf, 3, 5 - 1), 'refused for 3, four levels');
});

$test('moving a subtree: the deepest issue under it counts', function () use ($parentOf, $same) {
    // 8 > 9 (two levels) may go under level 3 at most
    $refused = PortalRules::refusedAmong($parentOf, 8, 5);
    $same([4, 5, 8, 9], $refused, 'refused for 8');
});

$test('a loop already in the data is too deep, never followed for ever', function () use ($same) {
    $loop = [1 => 2, 2 => 1, 3 => null];
    $same([1, 2], PortalRules::refusedAmong($loop, null, 5), 'refused');
});

$test('the levels the setting may ask for', function () {
    if (PortalRules::ISSUE_LEVELS_DEFAULT !== 5 || PortalRules::ISSUE_LEVELS_MIN !== 2 || PortalRules::ISSUE_LEVELS_MAX !== 10) {
        throw new \RuntimeException('default 5, between 2 and 10');
    }
});

echo $failed ? "\n$failed failed\n" : "\nall passed\n";
exit($failed ? 1 : 0);
