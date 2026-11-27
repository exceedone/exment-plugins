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
require __DIR__ . '/../ExmentPM/PortalNightly.php';

use App\Plugins\ExmentPM\PortalNightly as N;
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
$saveAs = substr($owner, strpos($owner, 'public static function saveAs('), 1800);
ok(strpos($saveAs, "update(['create_user_id' => \$owner])") !== false, 'the history of the save names the owner');
ok(strpos($saveAs, "->where('created_at', '>=', \$from)") !== false && strpos($saveAs, "\$q->whereNull('create_user_id');") !== false
    && strpos($saveAs, "\$q->orWhere('create_user_id', \$visitor);") !== false, 'only the history this save wrote: by the visitor or by nobody (the batch), from its start on');
ok(strpos($saveAs, 'if ($visitor > 0 && $visitor !== $owner') === false, 'the batch\'s history is repointed too');
ok(strpos($saveAs, 'PortalRules::onBehalf($row, function () use ($row) {') !== false && strpos($saveAs, '}, $owner);') !== false, 'the portal\'s rules do not ask the visitor about this row, and know whom it is by');
$canWrite = substr($owner, strpos($owner, 'public static function canWrite('), 3000);
ok(strpos($canWrite, 'PortalRoleUsers::editors($issueTable)') !== false && strpos($canWrite, 'getRoleUserAndOrgBelongsUserQueryTable') === false, 'canWrite asks for a role that edits, not for who may see the table');
ok(strpos($canWrite, 'static::$editors[$key]') !== false && strpos($sched, 'PortalRunOwner::forget();') !== false, 'one read of the roles per run');
ok(strpos($canWrite, "boolval(System::permission_available())") !== false && strpos($canWrite, "boolval(\$issueTable->getOption('all_user_editable_flg'))") !== false, 'canWrite reads the permission switch and the table\'s "every user edits"');

// writeRule: Exment's rule for who may add a row, on fakes
$reads = 0;
$editors = function () use (&$reads) {
    $reads++;

    return [5 => true, 6 => true];
};
ok(O::writeRule(5, true, false, $editors) && O::writeRule(6, true, false, $editors), 'permissions on: a user whose role edits the table');
ok(!O::writeRule(7, true, false, $editors), 'permissions on: a user with no such role (a viewer) may not');
$reads = 0;
ok(O::writeRule(7, false, false, $editors) && $reads === 0, 'permissions off (System::permission_available): any real user, the roles not read');
ok(O::writeRule(7, true, true, $editors) && $reads === 0, 'a table every user may edit (all_user_editable_flg): any real user');
ok(!O::writeRule(0, false, true, $editors) && !O::writeRule(-1, false, false, $editors), 'nobody (0) never');
ok(!O::writeRule(5, true, false, function () {
    return null;
}), 'the roles could not be read: refused');

// the editors of the roles and of the organizations' roles
ok(O::mergeIds(['5', 3, 0, null], [8, '3', 12]) === [3, 5, 8, 12], 'merge: users of the roles and of the organizations, once each, no empty id');
$roleUsers = file_get_contents(__DIR__ . '/../ExmentPM/PortalRoleUsers.php');
ok(strpos($roleUsers, 'Permission::AVAILABLE_EDIT_CUSTOM_VALUE') !== false && strpos($roleUsers, 'allUserAccessable') === false && strpos($roleUsers, 'getRoleUserOrgId($table, SystemTableName::USER') !== false, 'the editors: Exment\'s role groups with an edit permission, no "everyone may see it" shortcut');
ok(substr_count($roleUsers, '->query()->withoutGlobalScope(CustomValueModelScope::class)') === 2 && strpos($roleUsers, "->with(['users' => \$unscoped])") !== false
    && strpos($roleUsers, '$query->withoutGlobalScope(CustomValueModelScope::class);') !== false, 'the organizations and their users unscoped: not filtered by the request\'s user (filter_multi_user)');

// the portal's notices name the owner, not the visitor (PortalChannels)
if (!class_exists('Exceedone\Exment\Model\CustomValue')) {
    eval('namespace Exceedone\Exment\Model; class CustomValue {}');
}
require_once __DIR__ . '/../ExmentPM/PortalRules.php';
// a stand-in row (Exment's own CustomValue is abstract and needs Laravel)
// @phpstan-ignore-next-line
$rowA = new \Exceedone\Exment\Model\CustomValue();
// @phpstan-ignore-next-line
$rowB = new \Exceedone\Exment\Model\CustomValue();
$seen = \App\Plugins\ExmentPM\PortalRules::onBehalf($rowA, function () use ($rowA, $rowB) {
    return [\App\Plugins\ExmentPM\PortalRules::behalfOf($rowA), \App\Plugins\ExmentPM\PortalRules::behalfOf($rowB)];
}, 5);
ok($seen === [5, null], 'behalfOf: the owner for the row being made, nothing for another row', json_encode($seen));
ok(\App\Plugins\ExmentPM\PortalRules::behalfOf($rowA) === null, 'behalfOf: nothing once the save is done');
try {
    \App\Plugins\ExmentPM\PortalRules::onBehalf($rowA, function () {
        throw new \RuntimeException('x');
    }, 5);
} catch (\RuntimeException $e) {
}
ok(\App\Plugins\ExmentPM\PortalRules::behalfOf($rowA) === null, 'behalfOf: nothing after a save that threw');
$channels = file_get_contents(__DIR__ . '/../ExmentPM/PortalChannels.php');
ok(strpos($channels, "static::\$pending[\$id]['by'] = static::actorName(\$issue);") !== false
    && strpos($channels, '$owner = PortalRules::behalfOf($issue);') !== false && strpos($channels, 'PortalRunOwner::nameOf($owner)') !== false, 'the chat channel notice is by the owner');
ok(strpos($owner, 'LOGIN_USER') !== false && strpos($owner, 'Teams notice') !== false, 'what may still name the visitor is written down');

// saving a schedule: the saver must be able to add issues and see the template
$trait = file_get_contents(__DIR__ . '/../ExmentPM/PortalSchedulePageTrait.php');
$saveFn = substr($trait, strpos($trait, 'public function schedulesSave('), 1800);
ok(strpos($saveFn, 'if (!PortalSchedule::saverMayAdd($project, intval(\Exment::getUserId()), $this->issue_table)) {') !== false
    && strpos($saveFn, 'saverMayAdd') < strpos($saveFn, 'PortalSchedule::split(') && strpos($saveFn, 'saverMayAdd') < strpos($saveFn, 'PortalSchedule::save('), 'a save or a split is refused to a user who may not add issues');
$mayAdd = substr($sched, strpos($sched, 'public static function saverMayAdd('), 500);
ok(strpos($mayAdd, 'PortalRunOwner::canWrite($userId, $issueTable)') !== false && strpos($mayAdd, 'PortalDefinition::enableEdit($project, true) === true') !== false, 'may add: a role that edits issues, and the project editable for them');
ok(strpos($mayAdd, 'PortalRunOwner::forget();') !== false && strpos($mayAdd, 'PortalRunOwner::forget();') < strpos($mayAdd, 'PortalRunOwner::canWrite('), 'may add: the roles read afresh, not from an earlier run\'s memo');
$saveSched = substr($sched, strpos($sched, 'public static function save(int $projectId'), 900);
ok(strpos($saveSched, '$issueTable->getValueModel()->query()->find($template)') !== false && strpos($saveSched, 'withoutGlobalScopes') === false, 'the template as the saver sees it: hidden or in the trash is refused');
ok(strpos($run, '$issueTable->getValueModel()->query()->withoutGlobalScope(CustomValueModelScope::class)->find(intval($s->template_issue_id))') !== false, 'a run skips a template in the trash');
foreach (['ja', 'en'] as $lang) {
    $t = include __DIR__ . '/../ExmentPM/resources/lang/' . $lang . '/pmportal.php';
    ok(!empty($t['sched']['err_no_right']), 'the refusal in ' . $lang);
}
ok(strpos($owner, 'setUser(') === false && strpos($owner, 'Auth::') === false, 'Exment is not switched to another user mid-request');
$rules = file_get_contents(__DIR__ . '/../ExmentPM/PortalRules.php');
$placement = substr($rules, strpos($rules, 'protected static function placement('), 3000);
ok(strpos($placement, 'if (static::nobodyAsks($model)) {') !== false, 'placement: the visitor is not asked about a row made on behalf');
ok(strpos($rules, 'return static::behalfOf($model) !== null || \Exment::user() === null;') !== false, 'onBehalf is for that row only');

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
$branch = substr($batch, strpos($batch, 'protected function schedules(bool $dry): int'), 700);
ok(strpos($branch, 'PortalSchedule::batchRan();') !== false && strpos($branch, 'PortalSchedule::batchRan();') > strpos($branch, 'PortalSchedule::run($dry);'), 'the batch says it ran only after a run that got through (one that throws leaves the page loads on)');
// schedule:run calls "exment:batch <id>" with no params on the plugin's batch_cron, which the
// installer copies from config.json at every upload
$config = json_decode(file_get_contents(__DIR__ . '/../ExmentPM/config.json'), true);
ok(($config['batch_cron'] ?? null) === '*/5 * * * *' && ($config['batch_hour'] ?? null) === -1, 'config.json: batch_cron every 5 minutes, batch_hour still off');
ok(preg_match('/^(\*|\*\/\d+|\d+(-\d+)?(\/\d+)?)( (\*|\d+)){4}$/', $config['batch_cron']) === 1, 'the cron has five fields');
ok(strpos($batch, 'page loads of the portal (its top page and a') !== false && strpos($sched, 'PmPortalController index and') !== false, 'which page loads tick is written down');
ok(strpos($branch, 'if (!$dry) {') !== false, 'a dry batch does not hold the page loads back');
ok(preg_match("/if \\(\\\$target === 'schedule'\\) \\{\\s*return \\\$this->schedules\\(\\\$dry\\);/", $batch) === 1, '--param1=schedule runs them');

// Exment's own scheduler: exment:batch --uuid=<plugin> or exment:batch <id>, with no param1
$exec = substr($batch, strpos($batch, 'public function execute()'));
$noParam = strpos($exec, "if (\$target === '') {");
ok($noParam !== false && preg_match("/if \\(\\\$target === ''\\) \\{\\s*\\\$code = \\\$this->schedules\\(\\\$dry\\);/", $exec) === 1, 'no param1 (Exment\'s scheduler): the schedules, with the time of the run kept');
ok($noParam < strpos($exec, "if (\$target === 'shares')") && $noParam < strpos($exec, '$autofill = $this->autofill();'), 'no param1 returns before anything else, the AI back-fill included');
ok(strpos($exec, "static::target(array_get(\$options, 'param1'))") !== false && strpos($exec, "?? 'all')") === false, 'param1 is read as given: no default to the AI back-fill');
// PluginBatch::target, as written (the class needs Exment to load)
$targetSrc = substr($batch, strpos($batch, 'public static function target($param1): string'));
$targetSrc = substr($targetSrc, strpos($targetSrc, '{') + 1, strpos($targetSrc, "\n    }") - strpos($targetSrc, '{') - 1);
$target = eval('return function ($param1) {' . $targetSrc . '};');
ok($target(null) === '' && $target('') === '' && $target('  ') === '' && $target(['x']) === '', 'no param1, an empty one, or not a word: the scheduler\'s call');
ok($target('schedule') === 'schedule' && $target(' all ') === 'all' && $target(12) === '12', 'a param1 given is kept');
ok(strpos($batch, 'options.batch_cron') !== false && strpos($batch, 'BATCH_HOUR_OFF') !== false, 'the setting Exment\'s scheduler needs is documented');
ok(strpos($sched, '0-59/5 * * * *') !== false, 'the cron line is documented');

// --- the AI fill once a night, from the 5-minute batch (PortalNightly) ---------------------------

ok(N::hour('') === null && N::hour(null) === null && N::hour('off') === null && N::hour('24') === null && N::hour('-1') === null, 'off: empty, missing or not an hour');
ok(N::hour('0') === 0 && N::hour('3') === 3 && N::hour(' 23 ') === 23 && N::hour(5) === 5, 'an hour 0 to 23');
$settingSrc = file_get_contents(__DIR__ . '/../ExmentPM/PluginSetting.php');
$field = substr($settingSrc, strpos($settingSrc, '$form->select(PortalNightly::OPTION'), 300);
ok(strpos($field, "->default('');") !== false && strpos($settingSrc, "\$hours = ['' => \$a('nightly_off')];") !== false, 'the setting is off by default');
ok(strpos($settingSrc, 'PortalNightly::OPTION') > strpos($settingSrc, "'ai_severe_bump'"), 'with the other AI settings');
foreach (['ja', 'en'] as $lang) {
    $t = include __DIR__ . '/../ExmentPM/resources/lang/' . $lang . '/pmportal.php';
    ok(!empty($t['ai']['opt_nightly_hour']) && !empty($t['ai']['opt_nightly_hour_help']) && !empty($t['ai']['opt_nightly_off']), 'the setting\'s text in ' . $lang);
}
ok((include __DIR__ . '/../ExmentPM/resources/lang/ja/pmportal.php')['ai']['opt_nightly_hour'] === 'AI のうめ合わせを毎晩動かす（時刻）', 'the ja label as agreed');

// a day of 5-minute runs (288), the fill counted; $fails: the runs of the fill that fail
$day = function ($setting, string $date, ?string &$last, array $fails = []) {
    $fills = [];
    for ($m = 0; $m < 24 * 60; $m += 5) {
        $hour = intdiv($m, 60);
        if (N::due($setting, $hour, $date, $last)) {
            $n = count($fills);
            $fills[] = sprintf('%02d:%02d', $hour, $m % 60);
            if (!in_array($n, $fails, true)) {
                $last = $date; // ran(): only after a fill that got through
            }
        }
    }

    return $fills;
};
$last = null;
ok($day('', '2026-12-01', $last) === [] && $last === null, 'off by default: no fill all day');
$last = null;
ok($day('3', '2026-12-01', $last) === ['03:00'], 'on at 3: one fill, at 3:00, among the twelve runs of that hour', json_encode($day('3', '2026-12-01', $last)));
ok($day('3', '2026-12-01', $last) === [], 'not twice in the day, even if the hour comes again');
ok($day('3', '2026-12-02', $last) === ['03:00'], 'the next day: once again');
$last = null;
ok($day('3', '2026-12-03', $last, [0, 1]) === ['03:00', '03:05', '03:10'], 'a fill that fails is tried again in the hour, then kept', json_encode($day('3', '2026-12-04', $last, [0, 1])));
$last = null;
ok($day('3', '2026-12-05', $last, range(0, 20)) === ['03:00', '03:05', '03:10', '03:15', '03:20', '03:25', '03:30', '03:35', '03:40', '03:45', '03:50', '03:55'] && $last === null, 'failing all hour: not outside the hour');
ok(!N::due('3', 2, '2026-12-01', null) && !N::due('3', 4, '2026-12-01', null) && N::due('3', 3, '2026-12-01', null), 'not at other hours');
ok(!N::due('3', 3, '2026-12-01', '2026-12-01') && N::due('3', 3, '2026-12-01', '2026-11-30'), 'the day\'s stamp');
$noParam = substr($batch, strpos($batch, "if (\$target === '') {"), 900);
ok(strpos($noParam, 'PortalNightly::due(IssueAutofill::option(PortalNightly::OPTION, null)') !== false && strpos($noParam, "\$this->fill('all', \$limit, \$dry)") !== false, 'the batch with no param1: the fill of every project when the night\'s hour is due');
ok(strpos($noParam, 'if (!$dry && $filled === 0) {') !== false && strpos($noParam, 'PortalNightly::ran(') > strpos($noParam, "\$this->fill('all'"), 'stamped only after a fill that got through, never in a dry run');
ok(strpos($noParam, '$this->schedules($dry)') < strpos($noParam, 'PortalNightly::due('), 'the schedules first');
ok(strpos($batch, 'return $this->fill($target, $limit, $dry);') !== false, '--param1=all or a project id: the fill as before');
$readme = file_get_contents(__DIR__ . '/../README.md');
$unreleased = substr($readme, strpos($readme, '**Unreleased / 未リリース**'), 2000);
ok(strpos($unreleased, '`--param1=all`') !== false && strpos($unreleased, 'without parameters for the AI fill') !== false && strpos($unreleased, 'パラメーターなしで AI のうめ合わせ') !== false
    && strpos($readme, '**Unreleased / 未リリース**') < strpos($readme, '**Changed in 1.7.26'), 'the release note, ja and en, under Unreleased');
ok(strpos($unreleased, 'set your own `options.batch_cron`') !== false && strpos($unreleased, '`options.batch_cron` を自分で入れていた') !== false, 'the release note: an own batch_cron is replaced at the next upload (ja and en)');
$schemaSrc = file_get_contents(__DIR__ . '/../ExmentPM/PortalSchema.php');
ok(strpos($schemaSrc, 'Either survives a re-upload') === false && strpos($schemaSrc, 'sets options.batch_cron') === false, 'the old advice to set batch_cron for the back-fill is gone');

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
