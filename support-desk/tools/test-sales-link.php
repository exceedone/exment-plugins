<?php
// ExmentITSupport: the quote and the trial key of a ticket (SalesLink, ISS02941), the parts
// with no database. Plain PHP: php it-support/tools/test-sales-link.php
require __DIR__ . '/../ExmentITSupport/SalesLink.php';

use App\Plugins\ExmentITSupport\SalesLink as S;

$ok = 0;
$bad = 0;
$check = function (string $label, bool $cond, $extra = '') use (&$ok, &$bad) {
    echo ($cond ? 'OK  ' : 'NG  '), $label, $cond ? '' : '  ' . var_export($extra, true), "\n";
    $cond ? $ok++ : $bad++;
};

$check('key: trimmed', S::cleanKey("  ABCD-1234 \n") === 'ABCD-1234');
$check('key: control characters out', S::cleanKey("AB\x00CD\x1b[31m") === 'ABCD[31m', S::cleanKey("AB\x00CD\x1b[31m"));
$check('key: at most 200', mb_strlen(S::cleanKey(str_repeat('k', 300))) === 200);
$check('key: nothing', S::cleanKey(null) === '');
$check('date: a date', S::cleanDate('2027-01-31') === '2027-01-31');
$check('date: not a day', S::cleanDate('2027-02-29') === null && S::cleanDate('2027-13-01') === null);
$check('date: not the form', S::cleanDate('2027/01/31') === null && S::cleanDate('') === null && S::cleanDate(null) === null);
$check('reply: both placeholders', S::fill('キー {trial_key}（{trial_until} まで）', 'K-1', '2027-01-31') === 'キー K-1（2027-01-31 まで）');
$check('reply: no end yet', S::fill('{trial_until}', 'K', null) === '');

// every route of config.json has its method in PluginPage.php
$config = json_decode(file_get_contents(__DIR__ . '/../ExmentITSupport/config.json'), true);
$page = file_get_contents(__DIR__ . '/../ExmentITSupport/PluginPage.php');
$missing = [];
foreach ($config['route'] as $r) {
    if (!preg_match('/public function ' . preg_quote($r['function'], '/') . '\s*\(/', $page)) {
        $missing[] = $r['function'];
    }
}
$check(count($config['route']) . ' routes, each with its method', $missing === [], $missing);

echo "\n", $bad === 0 ? "all passed ($ok)" : "$bad failed, $ok passed", "\n";
exit($bad === 0 ? 0 : 1);
