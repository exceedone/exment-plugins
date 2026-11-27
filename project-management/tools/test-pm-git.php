<?php
// ExmentPM: Git linked to the issues (PortalGitParse, ISS02939 item 5), with fake deliveries.
//   php project-management/tools/test-pm-git.php
if (!function_exists('array_get')) {
    function array_get($array, $key, $default = null)
    {
        foreach (explode('.', $key) as $part) {
            if (!is_array($array) || !array_key_exists($part, $array)) {
                return $default;
            }
            $array = $array[$part];
        }
        return $array;
    }
}
require __DIR__ . '/../ExmentPM/PortalGitParse.php';
use App\Plugins\ExmentPM\PortalGitParse as G;

$ok = 0; $bad = 0;
$check = function ($label, $cond, $extra = '') use (&$ok, &$bad) { echo ($cond ? 'OK  ' : 'NG  '), $label, $cond ? '' : '  ' . var_export($extra, true), "\n"; $cond ? $ok++ : $bad++; };

// signatures
$body = '{"a":1}';
$sig = hash_hmac('sha256', $body, 's3cret');
$check('github: right signature', G::verify('github', $body, ['x-hub-signature-256' => 'sha256=' . $sig], 's3cret', ''));
$check('github: wrong signature', !G::verify('github', $body, ['x-hub-signature-256' => 'sha256=' . str_repeat('0', 64)], 's3cret', ''));
$check('github: body changed', !G::verify('github', $body . ' ', ['x-hub-signature-256' => 'sha256=' . $sig], 's3cret', ''));
$check('github: no signature', !G::verify('github', $body, [], 's3cret', ''));
$check('gitea: its header, no prefix', G::verify('gitea', $body, ['x-gitea-signature' => $sig], 's3cret', ''));
$check('gitlab: the token', G::verify('gitlab', $body, ['x-gitlab-token' => 'tok'], null, hash('sha256', 'tok')));
$check('gitlab: a wrong token', !G::verify('gitlab', $body, ['x-gitlab-token' => 'x'], null, hash('sha256', 'tok')));
$check('no secret: refused', !G::verify('github', $body, ['x-hub-signature-256' => 'sha256=' . $sig], '', ''));

$check('events', G::event('github', ['x-github-event' => 'push']) === 'push' && G::event('gitlab', ['x-gitlab-event' => 'Merge Request Hook']) === 'pr' && G::event('github', ['x-github-event' => 'ping']) === 'other');
$check('delivery ids', G::deliveryId('github', ['x-github-delivery' => '72d3162e-cc78-11e3-81ab-4c9367dc0958']) !== null && G::deliveryId('github', ['x-github-delivery' => '<x>']) === null);

// a push of GitHub
$push = ['ref' => 'refs/heads/EXMENT_AI-439-fix', 'repository' => ['full_name' => 'Acme/App', 'html_url' => 'https://git.example.invalid/Acme/App'],
    'commits' => [
        ['id' => str_repeat('a', 40), 'message' => "Fix login EXMENT_AI-439\n\nmore", 'author' => ['name' => '<b>Taro</b>'], 'url' => 'https://git.example.invalid/Acme/App/commit/aaa', 'timestamp' => '2026-11-27T10:00:00+09:00'],
        ['id' => 'zzz', 'message' => 'bad sha'],
        ['id' => str_repeat('b', 40), 'message' => 'evil link', 'url' => 'javascript:alert(1)'],
    ]];
$items = G::items('github', 'push', $push);
$check('push: 2 commits (a bad sha dropped)', count($items) === 2, $items);
$check('push: the first line as title, the branch', $items[0]['title'] === 'Fix login EXMENT_AI-439' && $items[0]['branch'] === 'EXMENT_AI-439-fix');
$check('push: the author as text (escaped where shown)', $items[0]['author'] === '<b>Taro</b>');
$check('push: a link to the repository host only', $items[0]['url'] === 'https://git.example.invalid/Acme/App/commit/aaa' && $items[1]['url'] === null);
$check('repo', G::repoOf('github', $push) === 'acme/app');

// a merged PR of GitHub, a MR of GitLab
$pr = ['pull_request' => ['number' => 12, 'merged' => true, 'state' => 'closed', 'title' => 'Fixes EXMENT_AI-439', 'body' => 'closes exment_ai-440 and see EXMENT_AI-4400', 'head' => ['ref' => 'feat'], 'html_url' => 'https://git.example.invalid/Acme/App/pull/12', 'user' => ['login' => 'jiro']],
    'repository' => ['full_name' => 'acme/app', 'html_url' => 'https://git.example.invalid/acme/app']];
$it = G::items('github', 'pr', $pr)[0];
$check('pr: merged, number, title', $it['state'] === 'merged' && $it['ref'] === '12' && $it['merged']);
$mr = ['object_attributes' => ['iid' => 3, 'state' => 'opened', 'title' => 'WIP', 'description' => '', 'source_branch' => 'b', 'url' => 'https://gl.example.invalid/g/p/-/merge_requests/3'],
    'project' => ['path_with_namespace' => 'g/p', 'web_url' => 'https://gl.example.invalid/g/p'], 'user' => ['name' => 'Hanako']];
$m = G::items('gitlab', 'pr', $mr)[0];
$check('mr: open, gitlab fields', $m['state'] === 'open' && $m['ref'] === '3' && $m['author'] === 'Hanako' && $m['url'] !== null);

// keys
$check('keys: the project, any case, whole words', G::keys($it['text'], 'EXMENT_AI') === ['EXMENT_AI-439', 'EXMENT_AI-440', 'EXMENT_AI-4400'], G::keys($it['text'], 'EXMENT_AI'));
$check('keys: another project not', G::keys('OTHER-1 and XEXMENT_AI-2', 'EXMENT_AI') === []);
$check('keys: an alias', G::keys('see login-bug here', 'P', ['login-bug']) === ['@login-bug']);
$check('keys: not inside a word', G::keys('mylogin-bugs', 'P', ['login-bug']) === []);
$check('keys: at most 20', count(G::keys(implode(' ', array_map(function ($n) { return 'P-' . $n; }, range(1, 30))), 'P')) === 20);
$check('closes: fixes / closes / resolved', G::closes($it['text'], 'EXMENT_AI') === ['EXMENT_AI-439', 'EXMENT_AI-440'], G::closes($it['text'], 'EXMENT_AI'));
$check('closes: "see KEY" does not', G::closes('see EXMENT_AI-1, refs EXMENT_AI-2', 'EXMENT_AI') === []);

// the issue's own key (the key column), as the branch name button copies it
$check('key words: the copied branch name', in_array('ISS00001', G::keyWords('ISS00001-pm101-issue-1'), true));
$check('key words: in a message', in_array('ISS00001', G::keyWords('touch ISS00001 now'), true) && in_array('PMD-1', G::keyWords('PMD-1-fix'), true));
$check('key words: not inside a word', !in_array('ISS00001', G::keyWords('xISS00001 ISS00001x'), true));
$check('key words: at most 50', count(G::keyWords(implode(' ', array_map(function ($n) { return 'A' . $n; }, range(1, 80))))) === 50);

// branch names
$check('branch: key and words', G::branchName('EXMENT_AI-439', 'Login fails on Safari') === 'EXMENT_AI-439-login-fails-on-safari');
$check('branch: Japanese only: the key', G::branchName('EXMENT_AI-439', 'ログインできない') === 'EXMENT_AI-439');
$check('branch: at most 60', strlen(G::branchName('K-1', str_repeat('word ', 30))) <= 60);

echo "\n", $bad === 0 ? "all passed ($ok)" : "$bad failed, $ok passed", "\n";
exit($bad === 0 ? 0 : 1);
