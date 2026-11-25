<?php
// The links to the manuals of the official plugins (ISS02882): the page of the plugin on
// exment.net in the language of the user (ja and vi have their own, the others English).
//
//   php project-management/tools/test-manual-links.php
//
// Plain PHP: app() is given here, and without Exment's CoreExtensions register() does nothing.

if (!function_exists('app')) {
    function app()
    {
        return new class () {
            public function getLocale()
            {
                return $GLOBALS['test_locale'];
            }
        };
    }
}

$base = __DIR__ . '/../..';
require $base . '/project-management/ExmentPM/ManualLink.php';
require $base . '/it-support/ExmentITSupport/ManualLink.php';
require $base . '/groupware/ExmentGroupware/ManualLink.php';

$want = [
    'App\\Plugins\\ExmentPM\\ManualLink' => 'plugin_pm',
    'App\\Plugins\\ExmentITSupport\\ManualLink' => 'plugin_it_support',
    'App\\Plugins\\ExmentGroupware\\ManualLink' => 'plugin_groupware',
];
$failed = 0;
foreach ($want as $class => $page) {
    foreach (['ja' => 'ja/', 'vi' => 'vi/', 'en' => '', 'de' => '', 'zh_CN' => ''] as $locale => $dir) {
        $GLOBALS['test_locale'] = $locale;
        $url = $class::url();
        $expected = 'https://exment.net/docs/' . $dir . $page . '.html';
        $ok = $url === $expected && $class::label() !== '';
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
