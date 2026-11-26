<?php
// ExmentITSupport: the fields of the request catalog (Alerts\CatalogSpec). Plain PHP:
//   php it-support/tools/test-catalog-spec.php
require __DIR__ . '/../ExmentITSupport/Alerts/CatalogSpec.php';

use App\Plugins\ExmentITSupport\Alerts\CatalogSpec as S;

$ok = 0;
$bad = 0;
$check = function (string $label, bool $cond, $extra = '') use (&$ok, &$bad) {
    echo ($cond ? 'OK  ' : 'NG  '), $label, $cond ? '' : '  ' . var_export($extra, true), "\n";
    $cond ? $ok++ : $bad++;
};

$spec = "対象のシステム|select|必須|メール,VPN、そのほか\nユーザー ID|text|必須\n\n# a comment\n借りる日|date|1\n理由|textarea\nルーターも|yesno\n人数|number\n連絡先|email\n変な種類|picture|yes\n選択肢なし|select|必須|";
$f = S::parse($spec);
$check('every line but the empty and the comment', count($f) === 9, count($f));
$check('keys in order', array_column($f, 'key') === ['f1', 'f2', 'f3', 'f4', 'f5', 'f6', 'f7', 'f8', 'f9']);
$check('a select with "," and "、"', $f[0]['type'] === 'select' && $f[0]['choices'] === ['メール', 'VPN', 'そのほか'] && $f[0]['required']);
$check('required: 必須, 1, yes', $f[1]['required'] && $f[2]['required'] && $f[7]['required']);
$check('not required', !$f[3]['required'] && !$f[4]['required']);
$check('an unknown type is text', $f[7]['type'] === 'text');
$check('a select without choices is text', $f[8]['type'] === 'text');
$check('types', array_column($f, 'type') === ['select', 'text', 'date', 'textarea', 'yesno', 'number', 'email', 'text', 'text']);
$check('at most 20 fields', count(S::parse(implode("\n", array_fill(0, 30, 'a')))) === 20);
$check('a line without a label is left out', S::parse("|text|必須\n名前") === [['key' => 'f1', 'label' => '名前', 'type' => 'text', 'required' => false, 'choices' => []]]);

$good = ['f1' => 'VPN', 'f2' => 'u123', 'f3' => '2026-12-01', 'f4' => "長い\n理由", 'f5' => '1', 'f6' => '3', 'f7' => 'a@example.co.jp', 'f8' => 'x', 'f9' => 'y'];
$check('good answers pass', S::validate($f, $good) === [], S::validate($f, $good));
$e = S::validate($f, ['f1' => 'Slack', 'f3' => '12/01/2026', 'f5' => 'maybe', 'f6' => 'three', 'f7' => 'not-mail', 'f9' => ['x']]);
$check('missing required', isset($e['f2']) && isset($e['f9']), $e);
$check('a choice not in the list', isset($e['f1']));
$check('a date not a date', isset($e['f3']));
$check('a yes/no not 1 or 0', isset($e['f5']));
$check('a number not a number', isset($e['f6']));
$check('an email not an email', isset($e['f7']));
$check('an optional one left empty is fine', !isset($e['f4']));
$check('too long', isset(S::validate([['key' => 'f1', 'label' => 'a', 'type' => 'text', 'required' => false, 'choices' => []]], ['f1' => str_repeat('x', 501)])['f1']));

$text = S::render($f, $good);
$check('rendered as "label: value"', strpos($text, '■ 対象のシステム: VPN') !== false && strpos($text, '■ ユーザー ID: u123') !== false);
$check('a long text below its label', strpos($text, "■ 理由\n長い\n理由") !== false);
$check('yes/no in words', strpos($text, '■ ルーターも: はい') !== false);
$check('an empty one as —', strpos(S::render($f, []), '■ ユーザー ID: —') !== false);

echo "\n$ok OK, $bad NG\n";
exit($bad === 0 ? 0 : 1);
