<?php
// The links to the manuals of the official plugins (ISS02882): the page of the plugin on
// exment.net in the language of the user (ja and vi have their own, the others English).
//
//   php project-management/tools/test-manual-links.php
//
// Plain PHP: the language is given to url() and label(), and without Exment's CoreExtensions
// register() does nothing.

$base = __DIR__ . '/../..';
require $base . '/project-management/ExmentPM/ManualLink.php';
require $base . '/support-desk/ExmentSupportDesk/ManualLink.php';
require $base . '/groupware/ExmentGroupware/ManualLink.php';

$want = [
    'App\\Plugins\\ExmentPM\\ManualLink' => 'plugin_pm',
    'App\\Plugins\\ExmentSupportDesk\\ManualLink' => 'plugin_support_desk',
    'App\\Plugins\\ExmentGroupware\\ManualLink' => 'plugin_groupware',
];
$failed = 0;
foreach ($want as $class => $page) {
    foreach (['ja' => 'ja/', 'vi' => 'vi/', 'en' => '', 'de' => '', 'zh_CN' => ''] as $locale => $dir) {
        $url = $class::url($locale);
        $expected = 'https://exment.net/docs/' . $dir . $page . '.html';
        $ok = $url === $expected && $class::label($locale) !== '';
        echo ($ok ? 'ok   ' : 'FAIL ') . "$page $locale -> $url\n";
        $failed += $ok ? 0 : 1;
    }
    // no Exment here: register() only returns
    $class::register(new class () {
        public function getRouteUri()
        {
            return 'plugins/x';
        }
    });
}
echo $failed ? "\n$failed failed\n" : "\nall passed\n";
exit($failed ? 1 : 0);
