<?php
// ExmentPM: the shares of the issues a recurring schedule makes, the repair of the ones runs gave
// before, and the keys of the copies. Plain PHP, no database: Exment's save is played by a fake.
//
//   php project-management/tools/test-pm-schedule-shares.php
require __DIR__ . '/../ExmentPM/PortalSchema.php';
require __DIR__ . '/../ExmentPM/PortalMembership.php';
require __DIR__ . '/../ExmentPM/PortalRecurrence.php';
require __DIR__ . '/../ExmentPM/PortalRunShares.php';

use App\Plugins\ExmentPM\PortalRecurrence;
use App\Plugins\ExmentPM\PortalRunShares as S;

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
 * Exment's save of a new issue, as CustomValue::savedEvent does it: the creator's share for the
 * user logged in (and their organizations), then the shares of the share settings. $veto plays
 * the "creating" listener of saveNew(); without it, the save is the plain $row->save() of before.
 *
 * @return array<int, string> the shares written, "permission type:id"
 */
function exmentSave(?int $loginUser, array $loginOrgs, array $settings, ?callable $veto): array
{
    $written = [];
    $create = function (string $perm, string $type, int $id) use (&$written, $veto) {
        if ($veto !== null && $veto($perm, $type, $id) === false) {
            return;
        }
        $written[$perm . ' ' . $type . ':' . $id] = true;
    };
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

// the issue's share settings: its assignee (user 2) gets an edit share
$settings = [[E, U, 2]];
$wanted = ['user:2' => true];
$veto = function ($perm, $type, $id) use ($wanted) {
    return S::allowed($wanted, $perm, $type, $id) ? null : false;
};

// --- a run inside a non-member's request --------------------------------------------------------
$before = exmentSave(1, [11, 12, 13, 14], $settings, null);
ok(in_array('custom_value_edit user:1', $before, true) && in_array('custom_value_edit organization:14', $before, true), 'the plain save of before: the visitor and their 4 organizations got an edit share');
$now = exmentSave(1, [11, 12, 13, 14], $settings, $veto);
ok($now === ['custom_value_edit user:2'], 'saveNew in user 1\'s request: only the assignee\'s share', implode(', ', $now));
$batch = exmentSave(null, [], $settings, null);
ok($now === $batch, 'the same shares as the batch makes with nobody logged in');
ok(!preg_grep('/user:1$|organization:1[1-4]$/', $now), 'nothing for the visitor or their organizations');
$noSettings = exmentSave(1, [11], [], function ($p, $t, $i) {
    return S::allowed([], $p, $t, $i) ? null : false;
});
ok($noSettings === [], 'no share settings: no share at all (the members reach it through the project)');

// a visitor who is also the assignee: what the setting gives, not more
$viewOnly = ['user:1' => false];
$asViewer = exmentSave(1, [11], [[V, U, 1]], function ($p, $t, $i) use ($viewOnly) {
    return S::allowed($viewOnly, $p, $t, $i) ? null : false;
});
ok($asViewer === ['custom_value_view user:1'], 'an assignee with a view setting gets view, not the creator\'s edit');
ok(S::allowed(['user:1' => true], E, U, 1) && S::allowed(['user:1' => true], V, U, 1), 'an edit setting allows edit and view');
ok(!S::allowed(['user:1' => true], E, O, 1), 'a user\'s setting does not allow the organization with the same id');

// --- the repair -----------------------------------------------------------------------------------
$t = 1764000000;
$roles = [5 => 'manager', 6 => 'member', 7 => 'viewer'];
$orgRoles = [21 => 'member', 22 => 'viewer'];
$share = function (int $id, string $type, int $target, string $perm, ?int $at) {
    return ['id' => $id, 'type' => $type, 'target' => $target, 'permission' => $perm, 'at' => $at];
};
// one issue PJ-B's schedule made in user 1's request (user 1 is not on PJ-B)
$store = [
    $share(1, U, 1, E, $t + 1),        // the creator's share: a leak
    $share(2, O, 11, E, $t + 1),       // user 1's organizations: leaks
    $share(3, O, 12, E, $t + 1),
    $share(4, O, 13, E, $t + 1),
    $share(5, O, 14, E, $t + 1),
    $share(6, U, 2, E, $t + 1),        // the assignee's (share settings): stays
    $share(7, U, 9, E, $t + 86400),    // given by hand the next day: stays
    $share(8, O, 21, E, $t + 1),       // a member organization: stays
    $share(9, O, 22, V, $t + 1),       // a viewer organization, view: stays
    $share(10, 'role', 3, E, $t + 1),  // not a user or an organization: stays
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
ok(array_column($store, 'id') === [6, 7, 8, 9, 10], 'apply: the legitimate shares stay', json_encode(array_column($store, 'id')));
$again = $store;
ok($repair($store, true) === [] && $store === $again, 'a second run changes nothing');

// a member's request: their own share stays, their organizations outside the project go
$member = [$share(1, U, 6, E, $t), $share(2, O, 31, E, $t), $share(3, O, 21, E, $t)];
ok(array_column(S::leaks($member, [], $roles, $orgRoles, $t), 'id') === [2], 'a member who ran it keeps theirs; their organization outside the project goes');
// a viewer's request: the edit goes, a view share would stay
ok(array_column(S::leaks([$share(1, U, 7, E, $t), $share(2, U, 7, V, $t)], [], $roles, $orgRoles, $t), 'id') === [1], 'a viewer loses the creator\'s edit, keeps a view');
// the project's manager
ok(S::leaks([$share(1, U, 5, E, $t)], [], $roles, $orgRoles, $t) === [], 'a manager\'s share stays');
// an organization share with no creator's share of a user next to it: not Exment's creator share
ok(S::leaks([$share(1, O, 41, E, $t)], [], $roles, $orgRoles, $t) === [], 'an organization alone is left as it is');
// an organization next to a view share of a user only: no creator's share (that one is always edit)
ok(S::leaks([$share(1, U, 8, V, $t), $share(2, O, 41, E, $t)], [], $roles, $orgRoles, $t) === [], 'a user\'s view share is not a creator\'s share');
// an assignee outside the project keeps what the settings give
ok(S::leaks([$share(1, U, 2, E, $t)], $wanted, $roles, $orgRoles, $t) === [], 'an assignee\'s share is the rule\'s, not a leak');
// edges
ok(S::leaks([$share(1, U, 1, E, $t)], [], [], [], $t) === [], 'a project that names nobody: nothing');
ok(S::leaks([$share(1, U, 1, E, $t)], [], $roles, $orgRoles, null) === [], 'an issue without a time: nothing');
ok(S::leaks([$share(1, U, 1, E, null)], [], $roles, $orgRoles, $t) === [], 'a share without a time: nothing');
ok(S::leaks([$share(1, U, 1, E, $t - S::WINDOW - 1)], [], $roles, $orgRoles, $t) === [], 'a share older than the issue: nothing');
ok(count(S::leaks([$share(1, U, 1, E, $t + S::WINDOW)], [], $roles, $orgRoles, $t)) === 1, 'within the window: found');

// --- the keys of the copies ------------------------------------------------------------------------
$types = ['issue_key' => 'auto_number', 'subject' => 'text', 'attachments' => 'file', 'source_key' => 'text', 'source_url' => 'url', 'actual_h' => 'decimal'];
$def = ['issue' => ['subject' => 'subject', 'actual' => 'actual_h'], 'issue_extra' => \App\Plugins\ExmentPM\PortalSchema::ISSUE_COLUMNS];
$template = ['issue_key' => 'ISS00008', 'subject' => 'Monthly report', 'attachments' => 'f1', 'source_key' => 'BL-12', 'source_url' => 'https://example.invalid/BL-12', 'actual_h' => 3, 'assignees' => [2]];
$v = PortalRecurrence::withoutOwn($template, $types, $def);
ok(!array_key_exists('issue_key', $v), 'the key is not copied');
ok(!array_key_exists('source_key', $v) && !array_key_exists('source_url', $v), 'where the template was imported from is not copied');
ok(!array_key_exists('attachments', $v) && !array_key_exists('actual_h', $v), 'files and work done are not copied (as before)');
ok($v['subject'] === 'Monthly report' && $v['assignees'] === [2], 'the rest is copied');
$renamed = PortalRecurrence::withoutOwn(['ticket_no' => 'T-1', 'subject' => 's'], ['subject' => 'text'], ['issue' => ['key' => 'ticket_no']]);
ok(!array_key_exists('ticket_no', $renamed), 'a key column renamed in the definition is not copied either');

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
    $keys[] = $number(PortalRecurrence::withoutOwn($template, $types, $def))['issue_key'];
}
ok(count(array_unique($keys)) === 4 && $keys[1] === 'ISS00009', 'three runs: three new keys, none the template\'s', implode(' ', $keys));
ok($number($template)['issue_key'] === 'ISS00008', 'a copy that kept the key would have had the template\'s (the bug)');

// --- the code uses them ---------------------------------------------------------------------------
$src = function ($f) {
    return file_get_contents(__DIR__ . '/../ExmentPM/' . $f);
};
$make = $src('PortalSchedule.php');
$make = substr($make, strpos($make, 'protected static function make('));
ok(strpos($make, 'PortalRunShares::saveNew($row);') !== false && strpos($make, '$row->save()') === false, 'a schedule\'s run saves with saveNew');
$copyFn = $src('PortalRecurrence.php');
$copyFn = substr($copyFn, strpos($copyFn, 'protected static function copy('), 3000);
ok(strpos($copyFn, 'PortalRunShares::saveNew($row);') !== false && strpos($copyFn, '$row->save()') === false, 'a recurring issue\'s copy saves with saveNew');
ok(strpos($src('PortalRecurrence.php'), '$value = static::withoutOwn(') !== false, 'copyValues drops the issue\'s own values');
$batchSrc = $src('PluginBatch.php');
ok(strpos($batchSrc, "\$target === 'schedule_shares'") !== false && strpos($batchSrc, "=== 'apply'") !== false, 'the batch: schedule_shares, a dry run unless apply');
$run = $src('PortalRunShares.php');
ok(strpos($run, 'DB::table(PortalSchedule::ITEMS)') !== false, 'the repair looks only at the issues the schedules made');
ok(strpos($run, 'CustomValueAuthoritable::creating(') !== false && strpos($run, 'return false;') !== false, 'the creator\'s share is vetoed before it is written');

echo $fail === 0 ? "all passed ($pass)\n" : "$fail failed, $pass passed\n";
exit($fail === 0 ? 0 : 1);
