<?php
// The lang files of the official plugins: every locale has the keys of the base locale (ja for
// ExmentPM, en for the others) and the same placeholders. Plain PHP, no Exment needed (CI):
//   php project-management/tools/test-lang-keys.php
// Exit 1 when a key or a placeholder is missing. A new ja key waits for its translations in a
// later PR, so CI runs this as a warning (continue-on-error). The order of the lookup is checked
// by test-languages.php (in an Exment folder).
//   php project-management/tools/test-lang-keys.php --ja-en
// only checks that every ja key is also in en (a key only in ja shows as "undefined" or the key
// itself in every other language). CI runs this one as an error: add the en text with the ja one.
// Both exit 1 when no plugin folder is found (nothing checked is not a pass).
$root = dirname(__DIR__, 2);
$plugins = [
    ['project-management/ExmentPM/resources/lang', 'pmportal.php', 'ja'],
    ['support-desk/ExmentSupportDesk/resources/lang', 'sd.php', 'en'],
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

$jaEn = in_array('--ja-en', $argv, true);
$bad = 0;
$checked = 0;
foreach ($plugins as [$dir, $file, $baseLocale]) {
    $dir = $root . '/' . $dir;
    if (!is_dir($dir)) {
        continue;
    }
    $checked++;
    if ($jaEn) {
        $missing = array_keys(array_diff_key($flat(require "$dir/ja/$file"), $flat(require "$dir/en/$file")));
        $name = basename(dirname($dir, 2));
        if (count($missing) === 0) {
            echo "OK  $name: every ja key is in en\n";
        } else {
            $bad++;
            echo "NG  $name: in ja but not in en: " . implode(', ', $missing) . "\n";
        }
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
if ($checked === 0) {
    echo "NG  no plugin folder found under $root\n";
    exit(1);
}
if ($jaEn) {
    echo $bad === 0 ? "every ja key is in en\n" : "$bad plugin(s) have ja keys without en\n";
    exit($bad === 0 ? 0 : 1);
}
echo $bad === 0 ? "all locales match\n" : "$bad locale(s) do not match\n";
exit($bad === 0 ? 0 : 1);
