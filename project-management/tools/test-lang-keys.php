<?php
// The lang files of the official plugins: every locale has the keys of the base locale (ja for
// ExmentPM, en for the others) and the same placeholders. Plain PHP, no Exment needed (CI):
//   php project-management/tools/test-lang-keys.php
// Exit 1 when a key or a placeholder is missing. A new ja key waits for its translations in a
// later PR, so CI runs this as a warning (continue-on-error). The order of the lookup is checked
// by test-languages.php (in an Exment folder).
$root = dirname(__DIR__, 2);
$plugins = [
    ['project-management/ExmentPM/resources/lang', 'pmportal.php', 'ja'],
    ['it-support/ExmentITSupport/resources/lang', 'its.php', 'en'],
    ['groupware/ExmentGroupware/resources/lang', 'groupware.php', 'en'],
];

$flat = function (array $a, string $p = '') use (&$flat) {
    $o = [];
    foreach ($a as $k => $v) {
        $key = $p === '' ? (string) $k : $p . '.' . $k;
        $o += is_array($v) ? $flat($v, $key) : [$key => $v];
    }
    return $o;
};
// :name, %s / %1$s, {0} / {word}, ${...}, and HTML tag names (as test-languages.php)
$marks = function ($s) {
    $s = preg_replace('#[a-z]+://\S+#', ' ', (string) $s);
    preg_match_all('/(?<![A-Za-z0-9_]):[a-z][a-z0-9_]*|%[0-9$]*[sd]|\$?\{\{?[A-Za-z0-9_.]*\}?\}|<\/?[A-Za-z][A-Za-z0-9]*/', $s, $m);
    $r = array_map('strtolower', $m[0]);
    sort($r);
    return implode(' ', $r);
};

$bad = 0;
foreach ($plugins as [$dir, $file, $baseLocale]) {
    $dir = $root . '/' . $dir;
    if (!is_dir($dir)) {
        continue;
    }
    $base = $flat(require "$dir/$baseLocale/$file");
    foreach (array_diff(scandir($dir), ['.', '..', $baseLocale]) as $locale) {
        $path = "$dir/$locale/$file";
        if (!is_file($path)) {
            continue;
        }
        $lines = $flat(require $path);
        $missing = array_keys(array_diff_key($base, $lines));
        $extra = array_keys(array_diff_key($lines, $base));
        $marksDiffer = [];
        foreach (array_intersect_key($base, $lines) as $key => $text) {
            if ($marks($text) !== $marks($lines[$key])) {
                $marksDiffer[] = $key;
            }
        }
        $name = basename(dirname($dir, 2)) . " $locale";
        if (count($missing) + count($extra) + count($marksDiffer) === 0) {
            echo "OK  $name\n";
            continue;
        }
        $bad++;
        echo "NG  $name\n";
        foreach (['missing' => $missing, 'extra' => $extra, 'placeholders differ' => $marksDiffer] as $what => $keys) {
            if (count($keys) > 0) {
                echo "      $what: " . implode(', ', $keys) . "\n";
            }
        }
    }
}
echo $bad === 0 ? "all locales match\n" : "$bad locale(s) do not match\n";
exit($bad === 0 ? 0 : 1);
