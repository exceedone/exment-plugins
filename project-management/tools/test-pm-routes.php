<?php
// ExmentPM: every route of config.json has its method in PluginPage.php (Exment calls the page
// class, which hands over to the portal). A route without one answers 500 "Method ... does not
// exist", and nothing else finds it. php project-management/tools/test-pm-routes.php
$dir = __DIR__ . '/../ExmentPM';
$config = json_decode(file_get_contents($dir . '/config.json'), true);
$page = file_get_contents($dir . '/PluginPage.php');
$routes = [];
array_walk_recursive($config, function ($v, $k) use (&$routes) {
    if ($k === 'function') {
        $routes[$v] = true;
    }
});
$missing = [];
foreach (array_keys($routes) as $fn) {
    if (!preg_match('/function\s+' . preg_quote($fn, '/') . '\s*\(/', $page)) {
        $missing[] = $fn;
    }
}
echo count($routes) . " routes; ";
if ($missing) {
    echo "no method in PluginPage.php for: " . implode(', ', $missing) . "\n";
    exit(1);
}
echo "all have their method\n";
