<?php
// ExmentPM: the shares of the issues a recurring schedule (or a recurring issue) makes, the repair
// of the ones runs gave before, and the keys of the copies. Plain PHP, no database: Exment's save
// is played by a fake.
//
//   php project-management/tools/test-pm-schedule-shares.php
require __DIR__ . '/../ExmentPM/PortalSchema.php';
require __DIR__ . '/../ExmentPM/PortalMembership.php';
require __DIR__ . '/../ExmentPM/PortalRecurrence.php';
require __DIR__ . '/../ExmentPM/PortalRunShares.php';

use App\Plugins\ExmentPM\PortalRecurrence as R;
use App\Plugins\ExmentPM\PortalRunShares as S;
use App\Plugins\ExmentPM\PortalSchema;

$fail = 0;
$pass = 0;
function ok($cond, $label, $extra = '')
{
    global $fail, $pass;
    echo ($cond ? 'ok   ' : 'FAIL ') . $label . ($cond || $extra === '' ? '' : '  ' . $extra) . "\n";
    $cond ? $pass++ : $fail++;
}

const E = S::EDIT;
const V = S::VIEW;
const U = S::USER;
const O = S::ORG;

/**
 * Exment's save of a new issue, as CustomValue::savedEvent does it: another plugin's SAVED event
 * first ($plugin), then the creator's share for the user logged in (and their organizations),
 * then the shares of the share settings. Every share written in the request has the request's
 * user as created_user_id (ModelBase). $veto plays the "creating" listener of saveNew(); without
 * it, the save is the plain $row->save() of before.
 *
 * @return array<int, string> the shares written, "permission type:id"
 */
function exmentSave(?int $loginUser, array $loginOrgs, array $settings, ?callable $veto, array $plugin = []): array
{
    $written = [];
    $create = function (string $perm, string $type, int $id) use (&$written, $veto, $loginUser) {
        if ($veto !== null && $veto($perm, $type, $id, $loginUser) === false) {
            return;
        }
        $written[$perm . ' ' . $type . ':' . $id] = true;
    };
    foreach ($plugin as [$perm, $type, $id]) {
        $create($perm, $type, $id);
    }
    if ($loginUser !== null) {
        $create(E, U, $loginUser);
        foreach ($loginOrgs as $org) {
            $create(E, O, $org);
        }
    }
    foreach ($settings as [$perm, $type, $id]) {
        $create($perm, $type, $id);
    }
    $out = array_keys($written);
    sort($out);

    return $out;
}

/** saveNew()'s listener for a request of $user in $orgs, for an issue whose settings give $wanted. */
function veto(array $wanted, int $user, ?array $orgs): callable
{
    return function ($perm, $type, $id, $by) use ($wanted, $user, $orgs) {
        return S::vetoed($wanted, $perm, $type, $id, $by, $user, $orgs) ? false : null;
    };
}

// the issue's share settings: its assignee (user 2) gets an edit share
$settings = [[E, U, 2]];
$wanted = ['user:2' => true];
$orgs1 = [11, 12, 13, 14];

// --- a run inside a non-member's request --------------------------------------------------------
$before = exmentSave(1, $orgs1, $settings, null);
ok(in_array('custom_value_edit user:1', $before, true) && in_array('custom_value_edit organization:14', $before, true), 'the plain save of before: the visitor and their 4 organizations got an edit share');
$now = exmentSave(1, $orgs1, $settings, veto($wanted, 1, $orgs1));
ok($now === ['custom_value_edit user:2'], 'saveNew in user 1\'s request: only the assignee\'s share', implode(', ', $now));
ok($now === exmentSave(null, [], $settings, null), 'the same shares as the batch makes with nobody logged in');
ok(!preg_grep('/user:1$|organization:1[1-4]$/', $now), 'nothing for the visitor or their organizations');
ok(exmentSave(1, [11], [], veto([], 1, [11])) === [], 'no share settings: no share at all (the members reach it through the project)');
ok(exmentSave(1, $orgs1, $settings, veto($wanted, 1, null)) === ['custom_value_edit user:2'], 'organizations not known: the ones the user writes still count as theirs');

// another plugin's shares (its SAVED event) are not Exment's creator's share and stay
$plugin = [[E, U, 77], [V, O, 55]];
$withPlugin = exmentSave(1, $orgs1, $settings, veto($wanted, 1, $orgs1), $plugin);
ok($withPlugin === ['custom_value_edit user:2', 'custom_value_edit user:77', 'custom_value_view organization:55'], 'another plugin\'s shares on other users and organizations stay', implode(', ', $withPlugin));
ok(!S::creatorShare(O, 55, 1, 1, $orgs1), 'an organization the user is not in is not their creator\'s share');
ok(!S::creatorShare(O, 11, 9, 1, $orgs1), 'an organization share written by someone else is not the creator\'s');
ok(S::creatorShare(U, 1, 9, 1, $orgs1) && !S::creatorShare(U, 2, 1, 1, $orgs1), 'a user share: the creator\'s only on the request\'s user');
ok(!S::creatorShare(U, 1, 1, 0, null), 'nobody logged in: nothing is a creator\'s share');

// a visitor who is also the assignee: what the setting gives, not more
$asViewer = exmentSave(1, [11], [[V, U, 1]], veto(['user:1' => false], 1, [11]));
ok($asViewer === ['custom_value_view user:1'], 'an assignee with a view setting gets view, not the creator\'s edit');
ok(exmentSave(1, [], [[E, U, 1]], veto(['user:1' => true], 1, [])) === ['custom_value_edit user:1'], 'an assignee with an edit setting keeps edit');
ok(S::allowed(['user:1' => true], E, U, 1) && S::allowed(['user:1' => true], V, U, 1), 'an edit setting allows edit and view');
ok(!S::allowed(['user:1' => true], E, O, 1), 'a user\'s setting does not allow the organization with the same id');

// --- the repair of the shares ---------------------------------------------------------------------
$t = 1764000000;
$roles = [5 => 'manager', 6 => 'member', 7 => 'viewer'];
$orgRoles = [21 => 'member', 22 => 'viewer'];
$share = function (int $id, string $type, int $target, string $perm, ?int $at, ?int $by) {
    return ['id' => $id, 'type' => $type, 'target' => $target, 'permission' => $perm, 'at' => $at, 'by' => $by];
};
// one issue PJ-B's schedule made in user 1's request (user 1 is not on PJ-B)
$store = [
    $share(1, U, 1, E, $t + 1, 1),       // the creator's share: a leak
    $share(2, O, 11, E, $t + 1, 1),      // user 1's organizations: leaks
    $share(3, O, 12, E, $t + 1, 1),
    $share(4, O, 13, E, $t + 1, 1),
    $share(5, O, 14, E, $t + 1, 1),
    $share(6, U, 2, E, $t + 1, 1),       // the assignee's (share settings): stays
    $share(7, U, 9, E, $t + 86400, 5),   // given by hand the next day: stays
    $share(8, O, 21, E, $t + 1, 1),      // a member organization: stays
    $share(9, O, 22, V, $t + 1, 1),      // a viewer organization, view: stays
    $share(10, 'role', 3, E, $t + 1, 1), // not a user or an organization: stays
    $share(11, U, 8, E, $t + 60, 5),     // the manager shared it by hand a minute later: stays
    $share(12, O, 31, E, $t + 60, 5),    // with an organization too: stays
];
$repair = function (array &$store, bool $apply) use ($wanted, $roles, $orgRoles, $t) {
    $found = S::leaks($store, $wanted, $roles, $orgRoles, $t);
    $ids = array_column($found, 'id');
    if ($apply) {
        $store = array_values(array_filter($store, function ($s) use ($ids) {
            return !in_array($s['id'], $ids, true);
        }));
    }

    return $ids;
};
$copy = $store;
$dry = $repair($store, false);
ok($dry === [1, 2, 3, 4, 5], 'dry run: lists the creator\'s share and the 4 organizations', json_encode($dry));
ok($store === $copy, 'dry run: changes nothing');
$applied = $repair($store, true);
ok($applied === [1, 2, 3, 4, 5], 'apply: removes those');
ok(array_column($store, 'id') === [6, 7, 8, 9, 10, 11, 12], 'apply: the legitimate shares stay, the hand shares within the window too', json_encode(array_column($store, 'id')));
$again = $store;
ok($repair($store, true) === [] && $store === $again, 'a second run changes nothing');

// a member's request: their own share stays, their organizations outside the project go
$member = [$share(1, U, 6, E, $t, 6), $share(2, O, 31, E, $t, 6), $share(3, O, 21, E, $t, 6)];
ok(array_column(S::leaks($member, [], $roles, $orgRoles, $t), 'id') === [2], 'a member who ran it keeps theirs; their organization outside the project goes');
ok(array_column(S::leaks([$share(1, U, 7, E, $t, 7), $share(2, U, 7, V, $t, 7)], [], $roles, $orgRoles, $t), 'id') === [1], 'a viewer loses the creator\'s edit, keeps a view');
ok(S::leaks([$share(1, U, 5, E, $t, 5)], [], $roles, $orgRoles, $t) === [], 'a manager\'s share stays');
ok(S::leaks([$share(1, O, 41, E, $t, 1)], [], $roles, $orgRoles, $t) === [], 'an organization alone is left as it is');
ok(S::leaks([$share(1, U, 8, V, $t, 8), $share(2, O, 41, E, $t, 8)], [], $roles, $orgRoles, $t) === [], 'a user\'s view share is not a creator\'s share');
ok(S::leaks([$share(1, U, 1, E, $t, 1), $share(2, O, 41, E, $t, 5)], [], $roles, $orgRoles, $t) === [$share(1, U, 1, E, $t, 1)], 'an organization written by someone else beside a creator\'s share stays');
ok(S::leaks([$share(1, U, 2, E, $t, 2)], $wanted, $roles, $orgRoles, $t) === [], 'an assignee\'s share is the rule\'s, not a leak');
ok(S::leaks([$share(1, U, 1, E, $t, null)], [], $roles, $orgRoles, $t) === [], 'a share with no writer: nothing');
ok(S::leaks([$share(1, U, 1, E, $t, 1)], [], [], [], $t) === [], 'a project that names nobody: nothing');
ok(S::leaks([$share(1, U, 1, E, $t, 1)], [], $roles, $orgRoles, null) === [], 'an issue without a time: nothing');
ok(S::leaks([$share(1, U, 1, E, null, 1)], [], $roles, $orgRoles, $t) === [], 'a share without a time: nothing');
ok(S::leaks([$share(1, U, 1, E, $t - S::WINDOW - 1, 1)], [], $roles, $orgRoles, $t) === [], 'a share older than the issue: nothing');
ok(count(S::leaks([$share(1, U, 1, E, $t + S::WINDOW, 1)], [], $roles, $orgRoles, $t)) === 1, 'within the window: found');

// --- the keys of the copies ------------------------------------------------------------------------
$types = ['issue_key' => 'auto_number', 'subject' => 'text', 'attachments' => 'file', 'source_key' => 'text', 'source_url' => 'url', 'actual_h' => 'decimal'];
$def = ['issue' => ['subject' => 'subject', 'actual' => 'actual_h'], 'issue_extra' => PortalSchema::ISSUE_COLUMNS];
$template = ['issue_key' => 'ISS00008', 'subject' => 'Monthly report', 'attachments' => 'f1', 'source_key' => 'BL-12', 'source_url' => 'https://example.invalid/BL-12',
    'source_assignees' => 'someone', 'custom_fields' => 'Area: north', 'actual_h' => 3, 'assignees' => [2]];
$v = R::withoutOwn($template, $types, $def);
ok(!array_key_exists('issue_key', $v), 'the key is not copied');
ok(!array_key_exists('source_key', $v) && !array_key_exists('source_url', $v) && !array_key_exists('source_assignees', $v), 'where the template was imported from is not copied (source_assignees too)');
ok(!array_key_exists('custom_fields', $v), 'the imported custom fields\' text is not copied');
ok(!array_key_exists('attachments', $v) && !array_key_exists('actual_h', $v), 'files and work done are not copied (as before)');
ok($v['subject'] === 'Monthly report' && $v['assignees'] === [2], 'the rest is copied');
ok(!array_key_exists('ticket_no', R::withoutOwn(['ticket_no' => 'T-1', 'subject' => 's'], ['subject' => 'text'], ['issue' => ['key' => 'ticket_no']])), 'a key column renamed in the definition is not copied either');
ok(in_array('source_assignees', R::sourceColumns($def), true) && in_array('source_key', R::sourceColumns($def), true) && !in_array('custom_fields', R::sourceColumns($def), true), 'the source columns: every source_* one');

// Exment numbers an auto number only when it is empty (AutoNumber::saved)
$next = 8;
$number = function (array $value) use (&$next) {
    if (!isset($value['issue_key'])) {
        $value['issue_key'] = sprintf('ISS%05d', ++$next);
    }

    return $value;
};
$keys = [$template['issue_key']];
for ($i = 0; $i < 3; $i++) {
    $keys[] = $number(R::withoutOwn($template, $types, $def))['issue_key'];
}
ok(count(array_unique($keys)) === 4 && $keys[1] === 'ISS00009', 'three runs: three new keys, none the template\'s', implode(' ', $keys));
ok($number($template)['issue_key'] === 'ISS00008', 'a copy that kept the key would have had the template\'s (the bug)');

// --- the repair of the keys already copied -----------------------------------------------------------
$rows = [
    ['id' => 30, 'project' => 1, 'key' => 'ISS00008', 'source' => 'BL-12'],  // a copy of 8 from before
    ['id' => 8, 'project' => 1, 'key' => 'ISS00008', 'source' => 'BL-12'],   // the template
    ['id' => 31, 'project' => 1, 'key' => 'ISS00008', 'source' => ''],       // a copy of a template with no source
    ['id' => 9, 'project' => 1, 'key' => 'ISS00009', 'source' => ''],
    ['id' => 40, 'project' => 2, 'key' => 'ISS00040', 'source' => 'BL-12'],  // the same source key in another project
    ['id' => 41, 'project' => 1, 'key' => 'ISS00008', 'source' => 'BL-99'],  // imported with a key already taken
    ['id' => 50, 'project' => 1, 'key' => '', 'source' => ''],
    ['id' => 51, 'project' => 1, 'key' => '', 'source' => ''],
];
$d = R::duplicateKeys($rows);
ok(array_keys($d) === [30, 31, 41], 'the newer issues with an older one\'s key', json_encode(array_keys($d)));
ok($d[30] === ['key' => true, 'source' => true, 'dup_of' => 8], 'a copy of an imported issue: the key and the source go');
ok($d[31] === ['key' => true, 'source' => false, 'dup_of' => 8], 'a copy without a source: the key only');
ok($d[41] === ['key' => true, 'source' => false, 'dup_of' => 8], 'its own source stays, only the key is new');
ok(!isset($d[8]) && !isset($d[9]) && !isset($d[40]) && !isset($d[50]), 'the oldest keeps its key; other projects\' sources and empty keys are left');
// apply with the fake numbering, then again
$fixed = $rows;
foreach ($fixed as &$r) {
    if (isset($d[$r['id']])) {
        $r['key'] = $d[$r['id']]['key'] ? sprintf('ISS%05d', $r['id']) : $r['key'];
        $r['source'] = $d[$r['id']]['source'] ? '' : $r['source'];
    }
}
unset($r);
ok(R::duplicateKeys($fixed) === [], 'after apply: no issue shares a key; a second run finds nothing');
$sk = R::duplicateKeys([['id' => 1, 'project' => 1, 'key' => 'A', 'source' => 'S'], ['id' => 2, 'project' => 1, 'key' => 'B', 'source' => 'S']]);
ok($sk === [2 => ['key' => false, 'source' => true, 'dup_of' => 1]], 'the same source key in a project, keys apart: the source goes');

// --- the code uses them ---------------------------------------------------------------------------
$src = function ($f) {
    return file_get_contents(__DIR__ . '/../ExmentPM/' . $f);
};
$make = $src('PortalSchedule.php');
$make = substr($make, strpos($make, 'protected static function make('));
$saveAs = $src('PortalRunOwner.php');
$saveAs = substr($saveAs, strpos($saveAs, 'public static function saveAs('), 1500);
ok(strpos($saveAs, 'PortalRunShares::saveNew($row);') !== false, 'saveAs saves with saveNew');
ok(strpos($make, 'PortalRunOwner::saveAs($row, $owner);') !== false && strpos($make, '$row->save()') === false, 'a schedule\'s run saves with saveAs (saveNew)');
$copyFn = $src('PortalRecurrence.php');
$copyFn = substr($copyFn, strpos($copyFn, 'protected static function copy('), 4000);
ok(strpos($copyFn, 'PortalRunOwner::saveAs($row, $owner);') !== false && strpos($copyFn, '$row->save()') === false, 'a recurring issue\'s copy saves with saveAs (saveNew)');
ok(strpos($src('PortalRecurrence.php'), '$value = static::withoutOwn(') !== false, 'copyValues drops the issue\'s own values');
$batchSrc = $src('PluginBatch.php');
ok(strpos($batchSrc, "\$target === 'schedule_shares'") !== false && strpos($batchSrc, "\$target === 'schedule_keys'") !== false && substr_count($batchSrc, "=== 'apply'") === 2, 'the batch: schedule_shares and schedule_keys, dry runs unless apply');
$run = $src('PortalRunShares.php');
ok(strpos($run, 'DB::table(PortalSchedule::ITEMS)') !== false && strpos($run, "->whereColumn('a.created_user_id', 'a.authoritable_target_id')") !== false, 'the repair looks at the schedules\' issues and the recurring copies');
ok(strpos($run, 'CustomValueAuthoritable::creating(') !== false && strpos($run, 'return false;') !== false, 'the creator\'s share is vetoed before it is written');
ok(preg_match("/->where\\('created_user_id', \\\$user\\)/", $run) === 1, 'prune touches only the shares the request\'s user wrote');
$saveNew = substr($run, strpos($run, 'public static function saveNew('), 1500);
ok(preg_match('/try \{\s*static::prune\(/', $saveNew) === 1 && strpos($saveNew, 'Log::warning') !== false, 'a throw in prune is logged, not passed on (the date stays claimed)');

echo $fail === 0 ? "all passed ($pass)\n" : "$fail failed, $pass passed\n";
exit($fail === 0 ? 0 : 1);
