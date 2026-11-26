<?php
// ExmentITSupport: the website form of a 受付箱 (WebForms, ISS02941), and every route with its
// method. Plain PHP: php it-support/tools/test-web-forms.php
require __DIR__ . '/../ExmentITSupport/WebForms.php';

use App\Plugins\ExmentITSupport\WebForms as W;

$ok = 0;
$bad = 0;
$check = function (string $label, bool $cond, $extra = '') use (&$ok, &$bad) {
    echo ($cond ? 'OK  ' : 'NG  '), $label, $cond ? '' : '  ' . var_export($extra, true), "\n";
    $cond ? $ok++ : $bad++;
};

$c = W::columnsFor([]);
$check('no fields: the defaults', $c['columns'] === W::DEFAULT_COLUMNS && $c['dropped'] === [], $c);

$c = W::columnsFor([
    ['column' => 'requester_name', 'required' => true],
    ['column' => 'status', 'required' => false],
    ['column' => 'description', 'required' => false],
    ['column' => 'assignee', 'required' => false],
    ['column' => 'description', 'required' => true],
]);
$check('the desk\'s columns are dropped, the email comes first', $c['columns'] === ['requester_email', 'requester_name', 'description'], $c);
$check('what was dropped is said', $c['dropped'] === ['status', 'assignee'], $c['dropped']);

foreach (['status', 'category', 'channel', 'customer_visible', 'require_otp', 'assignee', 'priority', 'ticket_no'] as $col) {
    $check("$col never on a form", in_array($col, W::DENIED, true));
}

$f = W::fixedValues(7, '見積のご依頼', ['requester_email', 'description']);
$check('fixed: the box, the channel, seen by the customer', $f['category'] === '7' && $f['channel'] === 'form' && $f['customer_visible'] === '1');
$check('fixed: a subject when the form has none', $f['subject'] === '見積のご依頼（Web サイトのフォーム）');
$check('fixed: no subject when the form has one', !isset(W::fixedValues(7, 'x', ['subject'])['subject']));
$check('fixed columns are never also on the form', array_intersect(array_keys(W::fixedValues(1, 'x', W::DEFAULT_COLUMNS)), W::DEFAULT_COLUMNS) === []);
$check('owner key', W::ownerKey(3) === 'exment_it_support:box:3');

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
