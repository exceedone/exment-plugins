<?php
/**
 * Check every plugin folder (a folder with Plugin.php) in this repository.
 * - config.json exists next to Plugin.php and is valid JSON
 * - plugin_name, uuid, version, plugin_type are set
 * - uuid looks like a UUID and is unique in the repository
 * - version looks like 1.2.3
 * - plugin_name is unique in the repository
 * Exit code 1 when something is wrong.
 */
$root = realpath(__DIR__ . '/../..');
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
$errors = [];
$uuids = [];
$names = [];
$count = 0;
foreach ($it as $file) {
    $path = str_replace(DIRECTORY_SEPARATOR, '/', $file->getPathname());
    if (strpos($path, '/.git/') !== false || strpos($path, '/.github/') !== false || strpos($path, '/vendor/') !== false) {
        continue;
    }
    if ($file->getFilename() !== 'Plugin.php') {
        continue;
    }
    $dir = dirname($path);
    $rel = ltrim(substr($dir, strlen(str_replace(DIRECTORY_SEPARATOR, '/', $root))), '/');
    $count++;
    $configPath = $dir . '/config.json';
    if (!is_file($configPath)) {
        $errors[] = "$rel: config.json is missing";
        continue;
    }
    $config = json_decode(file_get_contents($configPath), true);
    if (!is_array($config)) {
        $errors[] = "$rel/config.json: invalid JSON (" . json_last_error_msg() . ")";
        continue;
    }
    foreach (['plugin_name', 'uuid', 'version', 'plugin_type'] as $key) {
        if (empty($config[$key]) || !is_string($config[$key])) {
            $errors[] = "$rel/config.json: \"$key\" is missing";
        }
    }
    $uuid = $config['uuid'] ?? '';
    if ($uuid !== '' && !preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/i', $uuid)) {
        $errors[] = "$rel/config.json: \"uuid\" is not a UUID ($uuid)";
    }
    if ($uuid !== '' && isset($uuids[strtolower($uuid)])) {
        $errors[] = "$rel/config.json: \"uuid\" is the same as " . $uuids[strtolower($uuid)];
    }
    $uuids[strtolower($uuid)] = $rel;
    $version = $config['version'] ?? '';
    if ($version !== '' && !preg_match('/\A\d+\.\d+\.\d+\z/', $version)) {
        $errors[] = "$rel/config.json: \"version\" must look like 1.2.3 ($version)";
    }
    $name = $config['plugin_name'] ?? '';
    if ($name !== '' && isset($names[strtolower($name)])) {
        $errors[] = "$rel/config.json: \"plugin_name\" is the same as " . $names[strtolower($name)];
    }
    $names[strtolower($name)] = $rel;
    echo "ok: $rel ($name $version)\n";
}
if ($errors) {
    fwrite(STDERR, implode("\n", $errors) . "\n");
    exit(1);
}
echo "checked $count plugin(s)\n";
