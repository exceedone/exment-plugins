<?php
// Links to stored files must open outside the admin's page loading (pjax): each <a> written in
// the scripts and views of ExmentPM, Groupware and IT Support that points at a file (a /files/
// address, a file's url, the bl-file links) carries data-nopjax or target="_blank".
//   php project-management/tools/check-file-links.php
$root = dirname(__DIR__, 2);
$dirs = [
    'project-management/ExmentPM/resources/js/src',
    'project-management/ExmentPM/resources/views',
    'groupware/ExmentGroupware/resources/views',
    'it-support/ExmentITSupport/resources/views',
];
// what makes an <a> a link to a file
$file = '#/files/|bl-file|\b(f|e|file|att|a|d|doc)\.url\b|u\(\'file\'|B\.url\.file#';
$bad = [];
$n = 0;
foreach ($dirs as $d) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $d, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if (!preg_match('/\.(js|php)$/', $f->getFilename())) {
            continue;
        }
        foreach (file($f->getPathname()) as $i => $line) {
            // every <a ...> written on the line
            if (!preg_match_all('/<a\s[^>]*>?/i', $line, $m)) {
                continue;
            }
            foreach ($m[0] as $a) {
                if (!preg_match($file, $a) || preg_match('/href=["\']javascript:/', $a)) {
                    continue;
                }
                $n++;
                if (stripos($a, 'data-nopjax') === false && stripos($a, '_blank') === false) {
                    $bad[] = substr($f->getPathname(), strlen($root) + 1) . ':' . ($i + 1) . '  ' . trim(substr($a, 0, 140));
                }
            }
        }
    }
}
if ($bad) {
    echo "Links to files that pjax would load into the page (add data-nopjax or target=\"_blank\"):\n  " . implode("\n  ", $bad) . "\n";
    exit(1);
}
echo "file links: $n, all outside pjax\n";
exit(0);
