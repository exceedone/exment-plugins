<?php
// ExmentPMBacklog: an issue a run imports is saved without the creator's share of whoever's
// request runs it (「今すぐ同期」 runs in the admin's request): ExmentPM's
// PortalRunShares::saveNew() when ExmentPM has it, a plain save with an older ExmentPM. Plain PHP,
// no database: the rows and ExmentPM are fakes.
//
//   php project-management/tools/test-pmbl-sync-shares.php
require __DIR__ . '/../ExmentPMBacklog/Syncer.php';

use App\Plugins\ExmentPMBacklog\Syncer;

$fail = 0;
$pass = 0;
function ok($cond, $label, $extra = '')
{
    global $fail, $pass;
    echo ($cond ? 'ok   ' : 'FAIL ') . $label . ($cond || $extra === '' ? '' : '  ' . $extra) . "\n";
    $cond ? $pass++ : $fail++;
}

/** A row as the Syncer sees it: it records how it was saved. */
class FakeRow
{
    public $log = [];
    public $value = [];

    public function saved_notify($on)
    {
        $this->log[] = 'notify:' . ($on ? 'on' : 'off');
    }

    public function setValue(array $value)
    {
        $this->value = $value + $this->value;
        $this->log[] = 'set';

        return $this;
    }

    public function save()
    {
        $this->log[] = 'save';

        return true;
    }
}

$syncer = (new ReflectionClass(Syncer::class))->newInstanceWithoutConstructor();
$saveIssue = new ReflectionMethod(Syncer::class, 'saveIssue');
$saveIssue->setAccessible(true);
$issue = ['issueKey' => 'BL-1'];

// --- an older ExmentPM (no PortalRunShares): a plain save, as before -----------------------------------
ok(Syncer::newIssueSaver() === null, 'no PortalRunShares: no saver of its own');
$row = new FakeRow();
$saveIssue->invoke($syncer, $row, ['subject' => 'a'], $issue, true);
ok($row->log === ['notify:off', 'set', 'save'], 'a new issue: the plain save, notifications off', implode(',', $row->log));

// --- ExmentPM with PortalRunShares::saveNew() --------------------------------------------------------
eval('namespace App\Plugins\ExmentPM; class PortalRunShares { public static $saved = []; '
    . 'public static function saveNew($row): void { static::$saved[] = $row; $row->log[] = "saveNew"; $row->save(); } }');
ok(Syncer::newIssueSaver() === ['App\Plugins\ExmentPM\PortalRunShares', 'saveNew'], 'ExmentPM has it: saveNew saves the new issues');

$row = new FakeRow();
$saveIssue->invoke($syncer, $row, ['subject' => 'b', 'assignees' => [2]], $issue, true);
ok($row->log === ['notify:off', 'set', 'saveNew', 'save'], 'a new issue goes through saveNew (no creator\'s share), notifications still off', implode(',', $row->log));
ok($row->value === ['subject' => 'b', 'assignees' => [2]], 'with its values set before the save');
ok(\App\Plugins\ExmentPM\PortalRunShares::$saved === [$row], 'saveNew got that row, once');

$row = new FakeRow();
$saveIssue->invoke($syncer, $row, ['subject' => 'c'], $issue, false);
ok($row->log === ['notify:off', 'set', 'save'], 'an update is a plain save (Exment gives no creator\'s share on an update)', implode(',', $row->log));
ok(count(\App\Plugins\ExmentPM\PortalRunShares::$saved) === 1, 'saveNew not called for the update');

// --- the code uses it ----------------------------------------------------------------------------------
$src = file_get_contents(__DIR__ . '/../ExmentPMBacklog/Syncer.php');
ok(strpos($src, '$this->saveIssue($row, $value, $issue, $action === \'add\');') !== false, 'the run passes whether the issue is new');
ok(strpos($src, '$this->saveQuietly($row, $value, $isNew);') !== false, 'saveIssue saves a new issue as new (every retry too)');
ok(strpos($src, 'class_exists($class) && method_exists($class, \'saveNew\')') !== false, 'guarded: an ExmentPM without saveNew gets the plain save');
$page = file_get_contents(__DIR__ . '/../ExmentPMBacklog/PluginPage.php');
ok(strpos($page, 'Syncer::runConnection($conn, \'manual\'') !== false, 'the manual run of the page is the same run');

// the repair of before covers the imported issues (ExmentPM)
$run = file_get_contents(__DIR__ . '/../ExmentPM/PortalRunShares.php');
ok(strpos($run, "const BACKLOG_LINKS = 'pmbl_links';") !== false && strpos($run, "->where('kind', 'issue')") !== false, 'the repair reads the issues ExmentPMBacklog imported from its links');
ok(preg_match('/static::madeAt\(\$imported\[intval\(\$issue->id\)\] \?\? null,/', $run) === 1, 'and takes the import\'s time for them');
ok(strpos(file_get_contents(__DIR__ . '/../ExmentPMBacklog/Links.php'), "const TABLE = 'pmbl_links';") !== false, 'the same table as ExmentPMBacklog\'s links');

echo $fail === 0 ? "all passed ($pass)\n" : "$fail failed, $pass passed\n";
exit($fail === 0 ? 0 : 1);
