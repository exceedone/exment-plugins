<?php
// ExmentPMBacklog: an issue a run imports is saved without the creator's share of whoever's
// request runs it (「今すぐ同期」 runs in the admin's request): ExmentPM's
// PortalRunShares::saveNew() when ExmentPM has it, a plain save with an older ExmentPM. Plain PHP,
// no database: the rows and ExmentPM are fakes. And the links keep the time they were first written.
//
//   php project-management/tools/test-pmbl-sync-shares.php
require __DIR__ . '/../ExmentPMBacklog/Syncer.php';

use App\Plugins\ExmentPMBacklog\Links;
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

// Laravel's Log and Carbon, and the DB of the links (fakes; eval, so PHPStan reads the real ones)
$GLOBALS['logged'] = [];
eval('namespace Illuminate\Support\Facades; class Log { public static function warning($m) { $GLOBALS["logged"][] = $m; } }');

// --- an older ExmentPM (no PortalRunShares): a plain save, as before -----------------------------------
ok(Syncer::newIssueSaver() === null, 'no PortalRunShares: no saver of its own');
$row = new FakeRow();
$saveIssue->invoke($syncer, $row, ['subject' => 'a'], $issue, true);
ok($row->log === ['notify:off', 'set', 'save'], 'a new issue: the plain save, notifications off', implode(',', $row->log));
ok(count($GLOBALS['logged']) === 1 && strpos($GLOBALS['logged'][0], 'plain save') !== false, 'the plain save is logged as a warning, once (two saves, one line)', json_encode($GLOBALS['logged']));

// --- ExmentPM installed, but its folder not registered yet: registered once, then saveNew ---------------
$tried = new ReflectionProperty(Syncer::class, 'pmLoadTried');
$tried->setAccessible(true);
$tried->setValue(null, false);
$GLOBALS['savedNew'] = [];
$GLOBALS['required'] = 0;
$GLOBALS['asked'] = [];
eval('namespace Exceedone\Exment\Model; class Plugin { '
    . 'public static function getPluginByName($name) { $GLOBALS["asked"][] = $name; return new class { public function requirePlugin() { $GLOBALS["required"]++; '
    . 'eval(\'namespace App\Plugins\ExmentPM; class PortalRunShares { public static function saveNew($row): void { $GLOBALS["savedNew"][] = $row; $row->log[] = "saveNew"; $row->save(); } }\'); } }; } }');
ok(Syncer::newIssueSaver() === ['App\Plugins\ExmentPM\PortalRunShares', 'saveNew'], 'ExmentPM not loaded yet: its folder is registered, and saveNew saves the new issues');
ok($GLOBALS['asked'] === ['ExmentPM'] && $GLOBALS['required'] === 1, 'by Plugin::getPluginByName(\'ExmentPM\')->requirePlugin()');
Syncer::newIssueSaver();
ok($GLOBALS['required'] === 1 && count($GLOBALS['logged']) === 1, 'once: the next saves load nothing and log nothing');

$row = new FakeRow();
$saveIssue->invoke($syncer, $row, ['subject' => 'b', 'assignees' => [2]], $issue, true);
ok($row->log === ['notify:off', 'set', 'saveNew', 'save'], 'a new issue goes through saveNew (no creator\'s share), notifications still off', implode(',', $row->log));
ok($row->value === ['subject' => 'b', 'assignees' => [2]], 'with its values set before the save');
ok($GLOBALS['savedNew'] === [$row], 'saveNew got that row, once');

$row = new FakeRow();
$saveIssue->invoke($syncer, $row, ['subject' => 'c'], $issue, false);
ok($row->log === ['notify:off', 'set', 'save'], 'an update is a plain save (Exment gives no creator\'s share on an update)', implode(',', $row->log));
ok(count($GLOBALS['savedNew']) === 1, 'saveNew not called for the update');

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

// --- a link keeps the time it was first written (the repair's time of the import) ------------------------
/** The rows of pmbl_links, for the fake DB facade below. */
class FakeLinks
{
    public $where = [];

    public static $rows = [];

    public function where($column, $value)
    {
        $this->where[$column] = $value;

        return $this;
    }

    protected function matches(array $r): bool
    {
        foreach ($this->where as $c => $v) {
            if (($r[$c] ?? null) !== $v) {
                return false;
            }
        }

        return true;
    }

    public function exists(): bool
    {
        foreach (static::$rows as $r) {
            if ($this->matches($r)) {
                return true;
            }
        }

        return false;
    }

    public function update(array $values): int
    {
        $n = 0;
        foreach (static::$rows as $i => $r) {
            if ($this->matches($r)) {
                static::$rows[$i] = $values + $r;
                $n++;
            }
        }

        return $n;
    }

    public function insert(array $values): bool
    {
        static::$rows[] = $values;

        return true;
    }
}
$GLOBALS['now'] = '2026-11-01 10:00:00';
eval('namespace Illuminate\Support\Facades; class DB { public static function table($t) { return new \FakeLinks(); } }');
eval('namespace Carbon; class Carbon { public static function now() { return $GLOBALS["now"]; } }');
require __DIR__ . '/../ExmentPMBacklog/Links.php';

Links::put(3, 'space.example.invalid', 'issue', 101, 55, 'BL-1');
ok(count(FakeLinks::$rows) === 1 && FakeLinks::$rows[0]['created_at'] === '2026-11-01 10:00:00' && FakeLinks::$rows[0]['exment_id'] === 55, 'a new link: written with created_at', json_encode(FakeLinks::$rows));
$GLOBALS['now'] = '2026-11-20 09:00:00';
// the takeover of backlog_sync run again, for the same issue (Importer::run)
Links::upsert(['space_host' => 'space.example.invalid', 'kind' => 'issue', 'backlog_id' => 101],
    ['connection_id' => 3, 'exment_id' => 55, 'backlog_updated' => '2026-11-19T00:00:00Z', 'created_at' => $GLOBALS['now'], 'updated_at' => $GLOBALS['now']]);
ok(count(FakeLinks::$rows) === 1, 'again: the same link, not a second one');
ok(FakeLinks::$rows[0]['created_at'] === '2026-11-01 10:00:00', 'again: created_at stays the first time (even when given)', json_encode(FakeLinks::$rows[0]));
ok(FakeLinks::$rows[0]['updated_at'] === '2026-11-20 09:00:00' && FakeLinks::$rows[0]['backlog_updated'] === '2026-11-19T00:00:00Z', 'again: the rest is updated');
Links::put(3, 'space.example.invalid', 'attachment', 7, 0, 'a.png', 101);
ok(count(FakeLinks::$rows) === 2 && FakeLinks::$rows[1]['created_at'] === '2026-11-20 09:00:00', 'another link: its own row and time');
$importer = file_get_contents(__DIR__ . '/../ExmentPMBacklog/Importer.php');
ok(substr_count($importer, 'Links::upsert(') === 2 && strpos($importer, 'updateOrInsert') === false && strpos($importer, "'created_at'") === false, 'the takeover writes its links by Links::upsert, without created_at');
ok(strpos(file_get_contents(__DIR__ . '/../ExmentPMBacklog/Links.php'), 'updateOrInsert') === false, 'Links::put too');

echo $fail === 0 ? "all passed ($pass)\n" : "$fail failed, $pass passed\n";
exit($fail === 0 ? 0 : 1);
