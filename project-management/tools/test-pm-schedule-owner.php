<?php
// ExmentPM: whom a recurring schedule's issues are made as, the batch first and the page loads as
// a fallback, what a schedule that cannot run says, and no date made twice. Plain PHP, no
// database: the store, the cache and the claim are fakes.
//
//   php project-management/tools/test-pm-schedule-owner.php
require __DIR__ . '/../ExmentPM/PortalSchema.php';
require __DIR__ . '/../ExmentPM/PortalMembership.php';
require __DIR__ . '/../ExmentPM/PortalRunOwner.php';
require __DIR__ . '/../ExmentPM/PortalSchedule.php';

use App\Plugins\ExmentPM\PortalRunOwner as O;
use App\Plugins\ExmentPM\PortalSchedule as S;

$fail = 0;
$pass = 0;
function ok($cond, $label, $extra = '')
{
    global $fail, $pass;
    echo ($cond ? 'ok   ' : 'FAIL ') . $label . ($cond || $extra === '' ? '' : '  ' . $extra) . "\n";
    $cond ? $pass++ : $fail++;
}

// the users still there; 9 is the visitor, on no project
$alive = [3 => true, 4 => true, 5 => true, 6 => true, 7 => true, 9 => true];
$exists = function (int $id) use (&$alive) {
    return isset($alive[$id]);
};
// PJ-B: 5 manager, 6 member, 7 viewer; 4 manager second
$roles = [5 => 'manager', 4 => 'manager', 6 => 'member', 7 => 'viewer'];
$managers = [5, 4];

// --- whom the issue is made as ------------------------------------------------------------------
ok(O::choose(6, $roles, $managers, 3, $exists) === 6, 'the member who made the schedule');
ok(O::choose(5, $roles, $managers, 3, $exists) === 5, 'the manager who made it');
ok(O::choose(9, $roles, $managers, 3, $exists) === 5, 'a schedule made by someone off the project: the first manager, never them');
ok(O::choose(7, $roles, $managers, 3, $exists) === 5, 'its maker is a viewer now: the first manager');
$alive6 = $alive;
unset($alive[6]);
ok(O::choose(6, $roles, $managers, 3, $exists) === 5, 'its maker is gone: the first manager');
unset($alive[5]);
ok(O::choose(6, $roles, $managers, 3, $exists) === 4, 'the first manager gone too: the next manager');
unset($alive[4]);
ok(O::choose(6, $roles, $managers, 3, $exists) === null, 'nobody: no owner');
$alive = $alive6;
ok(O::choose(null, $roles, $managers, 3, $exists) === 5, 'a schedule with no maker recorded: the first manager');
ok(O::choose(3, [], [], 3, $exists) === 3, 'a project that names nobody: its maker');
ok(O::choose(null, [], [], 3, $exists) === 3, 'a project that names nobody, no maker recorded: who made the project');
unset($alive[3]);
ok(O::choose(3, [], [], 3, $exists) === null, 'a project that names nobody, its maker gone: no owner');
$alive = $alive6;
// the visitor is never an input: run() hands choose() the schedule's maker only
$sched = file_get_contents(__DIR__ . '/../ExmentPM/PortalSchedule.php');
$run = substr($sched, strpos($sched, 'public static function run('), 4000);
ok(strpos($run, 'PortalRunOwner::check($project, intval($s->created_user_id ?? 0) ?: null, $issueTable, $owner)') !== false, 'the schedule\'s owner is the user who made it');
ok(strpos($run, 'getUserId') === false && strpos($run, 'Exment::user') === false, 'the run never reads the visitor');
$owner = file_get_contents(__DIR__ . '/../ExmentPM/PortalRunOwner.php');
ok(strpos($owner, '$row->saving_users = false;') !== false && strpos($owner, '$row->created_user_id = $owner;') !== false, 'the owner is written as the creator, Exment does not stamp the visitor');
ok(strpos($owner, "update(['create_user_id' => \$owner])") !== false, 'the history of the save names the owner');
ok(strpos($owner, 'PortalRules::onBehalf($row,') !== false, 'the portal\'s rules do not ask the visitor about this row');
ok(strpos($owner, 'setUser(') === false && strpos($owner, 'Auth::') === false, 'Exment is not switched to another user mid-request');
$rules = file_get_contents(__DIR__ . '/../ExmentPM/PortalRules.php');
$placement = substr($rules, strpos($rules, 'protected static function placement('), 3000);
ok(strpos($placement, 'if (static::nobodyAsks($model)) {') !== false, 'placement: the visitor is not asked about a row made on behalf');
ok(strpos($rules, 'isset(static::$onBehalf[spl_object_id($model)])') !== false, 'onBehalf is for that row only');

// --- when it cannot run: once in the log, on the screen ---------------------------------------
$logs = [];
$state = null;
$tick = function (?string $reason) use (&$state, &$logs) {
    $change = S::blockChange($state, $reason);
    if ($change !== null) {
        $state = $reason;
        $logs[] = $change . ':' . strval($reason);
    }
};
foreach ([O::NO_RIGHT, O::NO_RIGHT, O::NO_RIGHT, O::NO_RIGHT, O::NO_RIGHT] as $r) {
    $tick($r);
}
ok($logs === ['log:no_right'] && $state === 'no_right', 'no right, five runs: one log line, the flag kept', json_encode($logs));
$tick(O::NO_OWNER);
ok($logs === ['log:no_right', 'log:no_owner'], 'another reason: one more line');
$tick(null);
$tick(null);
ok(end($logs) === 'clear:' && count($logs) === 3 && $state === null, 'it can run again: the flag goes, said once');
ok(S::blockChange('', null) === null && S::blockChange(null, null) === null, 'nothing kept, nothing wrong: nothing');
$blade = file_get_contents(__DIR__ . '/../ExmentPM/resources/views/schedules.blade.php');
ok(strpos($blade, "\$s('blocked_' . \$row->run_error)") !== false, 'the schedule screen shows the flag');
foreach (['ja', 'en'] as $lang) {
    $t = include __DIR__ . '/../ExmentPM/resources/lang/' . $lang . '/pmportal.php';
    ok(!empty($t['sched']['blocked_no_owner']) && !empty($t['sched']['blocked_no_right']) && strpos($t['sched']['blocked_since'], ':at') !== false, 'the messages in ' . $lang);
}
$migration = glob(__DIR__ . '/../ExmentPM/database/migrations/*_add_run_error_to_pm_portal_schedules.php');
ok(count($migration) === 1 && strpos(file_get_contents($migration[0]), "string('run_error', 32)->nullable()") !== false, 'the flag\'s column comes with a migration');
ok(strpos($sched, 'if (!$dry) {' . "\n" . '                static::setBlocked($s, $blocked);') !== false, 'a dry run does not write the flag');
$copy = file_get_contents(__DIR__ . '/../ExmentPM/PortalRecurrence.php');
$copy = substr($copy, strpos($copy, 'protected static function copy('), 2500);
ok(strpos($copy, 'PortalRunOwner::check($project, intval($issue->created_user_id) ?: null') !== false
    && strpos($copy, 'PortalRunOwner::check') < strpos($copy, '$claimed = '), 'a recurring copy: its owner is checked before the setting is taken off');

// --- the batch first, the page loads as a fallback -----------------------------------------------
$now = 1764000000;
ok(!S::batchFresh(null, $now), 'the batch never ran: page loads run the schedules');
ok(S::batchFresh($now - 5 * 60, $now), 'the batch ran 5 minutes ago: page loads leave them');
ok(S::batchFresh($now - S::FALLBACK_MINUTES * 60, $now), 'just within the window');
ok(!S::batchFresh($now - S::FALLBACK_MINUTES * 60 - 1, $now), 'the batch stopped: page loads take over');
ok(!S::batchFresh('x', $now) && !S::batchFresh(0, $now), 'a value that is not a time: page loads run them');
$tickSrc = substr($sched, strpos($sched, 'public static function tick('), 800);
ok(strpos($tickSrc, 'static::batchFresh(Cache::get(static::batchKey()), time())') !== false && strpos($tickSrc, 'Cache::add($key, 1, now()->addMinutes(self::TICK_MINUTES))') !== false, 'tick: skipped while the batch runs, every few minutes at most otherwise');
$batch = file_get_contents(__DIR__ . '/../ExmentPM/PluginBatch.php');
$branch = substr($batch, strpos($batch, "if (\$target === 'schedule') {"), 600);
ok(strpos($branch, 'PortalSchedule::batchRan();') !== false && strpos($branch, 'PortalSchedule::batchRan();') < strpos($branch, 'PortalSchedule::run($dry);'), 'the batch says it ran before it runs them');
ok(strpos($branch, 'if (!$dry) {') !== false, 'a dry batch does not hold the page loads back');
ok(strpos($sched, '0-59/5 * * * *') !== false, 'the cron line is documented');

// --- no date made twice when the batch and a page load run at once ------------------------------
// the claim: (schedule, date) unique in pm_portal_schedule_items, taken before the copy
$items = [];
$made = [];
$claim = function (string $who, int $schedule, string $date) use (&$items, &$made) {
    $key = $schedule . '|' . $date;
    if (isset($items[$key])) {
        return null; // the insert fails: another run has it
    }
    $items[$key] = $who;
    $made[] = $who . ':' . $date;

    return count($made);
};
$dates = ['2026-12-01', '2026-12-08', '2026-12-15'];
foreach ($dates as $d) {
    $claim('batch', 1, $d);
    $claim('tick', 1, $d);
}
foreach (array_reverse($dates) as $d) {
    $claim('tick', 1, $d);
}
ok(count($made) === 3 && count($items) === 3, 'the batch and a page load at once: each date once', json_encode($made));
$make = substr($sched, strpos($sched, 'protected static function make('), 3000);
ok(strpos($make, 'DB::table(static::ITEMS)->insert(') !== false && strpos($make, 'return null; // another run has it') !== false
    && strpos($make, 'DB::table(static::ITEMS)->insert(') < strpos($make, 'PortalRunOwner::saveAs('), 'make(): the date is claimed before the issue is saved (as before)');

echo $fail === 0 ? "all passed ($pass)\n" : "$fail failed, $pass passed\n";
exit($fail === 0 ? 0 : 1);
