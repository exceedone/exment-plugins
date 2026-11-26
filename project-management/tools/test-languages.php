<?php
// ExmentPM: the portal's lang files and the language each line is read in. In the Exment folder:
//   php /path/to/project-management/tools/test-languages.php
// Reads the ExmentPM folder next to this file (not the installed copy). Changes nothing on the site.
require getcwd() . '/vendor/autoload.php';
$app = require getcwd() . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$dir = dirname(__DIR__) . '/ExmentPM';
// A site with ExmentPM installed has loaded its own PortalDefinition already, so this folder's copy is
// read under a namespace of its own (next to the original, so its resources/lang is the one used).
$copy = $dir . '/PortalDefinitionLangTest.php';
file_put_contents($copy, str_replace('namespace App\Plugins\ExmentPM;', 'namespace App\Plugins\ExmentPMLangTest;', file_get_contents($dir . '/PortalDefinition.php')));
register_shutdown_function(function () use ($copy) { @unlink($copy); });
require $copy;
$PD = 'App\Plugins\ExmentPMLangTest\PortalDefinition';

$ok = 0; $bad = 0;
$check = function ($label, $cond, $extra = '') use (&$ok, &$bad) { echo ($cond ? 'OK  ' : 'NG  '), $label, $cond ? '' : "  $extra", "\n"; $cond ? $ok++ : $bad++; };
$flat = function (array $a, string $p = '') use (&$flat) {
    $o = [];
    foreach ($a as $k => $v) {
        $key = $p === '' ? (string) $k : $p . '.' . $k;
        $o += is_array($v) ? $flat($v, $key) : [$key => $v];
    }
    return $o;
};
// :name, %s / %1$s, {0} / {word}, ${...}, and HTML tag names
$marks = function (string $s) {
    $s = preg_replace('#[a-z]+://\S+#', ' ', $s);
    preg_match_all('/(?<![A-Za-z0-9_]):[a-z][a-z0-9_]*|%[0-9$]*[sd]|\$?\{\{?[A-Za-z0-9_.]*\}?\}|<\/?[A-Za-z][A-Za-z0-9]*/', $s, $m);
    $r = array_map('strtolower', $m[0]);
    sort($r);
    return implode(' ', $r);
};

// 1. every locale has the keys of ja, and the same placeholders
$ja = $flat(require $dir . '/resources/lang/ja/pmportal.php');
$locales = array_values(array_diff(scandir($dir . '/resources/lang'), ['.', '..', 'ja']));
$check('locales besides ja: ' . implode(', ', $locales), in_array('en', $locales, true));
foreach ($locales as $loc) {
    $tr = $flat(require $dir . "/resources/lang/$loc/pmportal.php");
    $missing = array_diff_key($ja, $tr);
    $extra = array_diff_key($tr, $ja);
    $check("$loc: same keys as ja (" . count($tr) . ')', !$missing && !$extra, 'missing ' . implode(',', array_slice(array_keys($missing), 0, 5)) . ' extra ' . implode(',', array_slice(array_keys($extra), 0, 5)));
    $wrong = [];
    $empty = [];
    foreach ($ja as $k => $v) {
        if (!isset($tr[$k])) {
            continue;
        }
        if (!is_string($tr[$k]) || (trim($tr[$k]) === '' && trim($v) !== '')) {
            $empty[] = $k;
        } elseif ($marks($v) !== $marks($tr[$k])) {
            $wrong[] = $k;
        }
    }
    $check("$loc: no empty value", !$empty, implode(',', array_slice($empty, 0, 5)));
    $check("$loc: placeholders as in ja", !$wrong, implode(',', array_slice($wrong, 0, 5)));
}

// 2. the language a line is read in
$read = function (string $loc, string $key) use ($dir, $flat) {
    $file = $dir . "/resources/lang/$loc/pmportal.php";
    return file_exists($file) ? ($flat(require $file)[$key] ?? null) : null;
};
foreach (array_merge(['ja'], $locales) as $loc) {
    app()->setLocale($loc);
    $check("$loc: the screens read $loc", $PD::trans('tab_home') === $read($loc, 'tab_home'), strval($PD::trans('tab_home')));
    $check("$loc: stored names stay ja", $PD::trans('schema.values.status.todo') === $read('ja', 'schema.values.status.todo'), strval($PD::trans('schema.values.status.todo')));
    $check("$loc: an array of lines", $PD::trans('http_error') == (require $dir . "/resources/lang/$loc/pmportal.php")['http_error']);
    $check("$loc: replacements", $PD::trans('dash.overdue_days', ['days' => 7]) === str_replace(':days', '7', $read($loc, 'dash.overdue_days')));
    $check("$loc: vsprintf", $PD::trans('issue_count', 3) === sprintf($read($loc, 'issue_count'), 3));
    $check("$loc: an unknown key is unresolved", $PD::isUnresolved($PD::trans('no_such_key_' . $loc)));
}
app()->setLocale('fr');
$check('fr (not shipped): the screens read en', $PD::trans('tab_home') === $read('en', 'tab_home'), strval($PD::trans('tab_home')));
$check('fr: stored names stay ja', $PD::trans('schema.values.status.todo') === $read('ja', 'schema.values.status.todo'));
$check('localesFor: vi', $PD::localesFor('tab_home', 'vi') === ['vi', 'en', 'ja']);
$check('localesFor: ja', $PD::localesFor('tab_home', 'ja') === ['ja', 'en']);
$check('localesFor: schema', $PD::localesFor('schema.values.status.todo', 'vi') === ['ja']);

echo "\n$ok OK, $bad NG\n";
exit($bad ? 1 : 0);
