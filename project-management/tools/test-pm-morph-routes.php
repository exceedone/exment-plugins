<?php
// The project routes that skip Exment's admin.morph middleware (stamp, feed, read):
// they still find a project or an issue through findProject() / findIssue(), and
// those define the morph map before enableAccess(). Without the map the shares of a
// row are looked up under the class name, and a person who is in the project only
// through a share (a role group) got 403 there.
//
//   php project-management/tools/test-pm-morph-routes.php
//
// Plain PHP: reads config.json and the source, no Exment and no database.

$dir = __DIR__ . '/../ExmentPM';
$config = json_decode(file_get_contents($dir . '/config.json'), true);
$page = file_get_contents($dir . '/PluginPage.php');
$ctrl = file_get_contents($dir . '/PmPortalController.php');
$failed = 0;
$check = function ($ok, $label) use (&$failed) {
    echo ($ok ? 'ok   ' : 'FAIL ') . $label . "\n";
    $failed += $ok ? 0 : 1;
};

// The body of a method: from its signature to the next method at the same indent.
$body = function ($src, $name) {
    if (!preg_match('/function ' . preg_quote($name, '/') . '\s*\(/', $src, $m, PREG_OFFSET_CAPTURE)) {
        return null;
    }
    $start = $m[0][1];
    $next = preg_match('/\n    (?:public|protected|private) (?:static )?function /', $src, $n, PREG_OFFSET_CAPTURE, $start + 10)
        ? $n[0][1] : strlen($src);

    return substr($src, $start, $next - $start);
};

foreach (['findProject', 'findIssue'] as $finder) {
    $b = $body($ctrl, $finder);
    $at = $b === null ? false : strpos($b, '$this->ensureMorphMap()');
    $access = $b === null ? false : strpos($b, 'enableAccess()');
    $check($at !== false && $access !== false && $at < $access, "$finder defines the morph map before enableAccess()");
}
$e = $body($ctrl, 'ensureMorphMap');
$check($e !== null && strpos($e, 'Morph::defineMorphMap(') !== false, 'ensureMorphMap calls Morph::defineMorphMap');

$routes = 0;
foreach ($config['route'] ?? [] as $route) {
    $uri = $route['uri'] ?? '';
    if (strpos($uri, '{id}/') !== 0 || !in_array('admin.morph', $route['middleware_except'] ?? [], true)) {
        continue;
    }
    $routes++;
    $fn = $route['function'];
    // PluginPage hands the call to the portal controller of the same name...
    $pass = $body($page, $fn);
    $check($pass !== null && strpos($pass, 'portal()->' . $fn . '(') !== false, "$uri: PluginPage::$fn passes to the portal");
    // ...which finds the project with findProject() (the same rule as every other screen).
    $impl = $body($ctrl, $fn);
    if ($impl === null) {
        // a method of a trait of the controller
        foreach (glob($dir . '/Portal*Trait.php') as $trait) {
            $impl = $body(file_get_contents($trait), $fn) ?? $impl;
        }
    }
    $check($impl !== null && strpos($impl, '$this->findProject(') !== false, "$uri: $fn finds the project with findProject()");
}
$check($routes > 0, "$routes {id}/ route(s) without admin.morph");

echo $failed ? "\n$failed failed\n" : "\nall passed\n";
exit($failed ? 1 : 0);
