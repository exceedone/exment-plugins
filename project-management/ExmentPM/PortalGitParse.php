<?php

namespace App\Plugins\ExmentPM;

/**
 * The parts of the Git link with no database (ISS02939 item 5): tools/test-pm-git.php runs them.
 * Reads a webhook delivery of GitHub, GitLab or Gitea into one shape, checks its signature,
 * finds the issue keys in a text, makes a branch name.
 */
class PortalGitParse
{
    const PROVIDERS = ['github', 'gitlab', 'gitea'];
    const MAX_ISSUES = 20;
    const MAX_COMMITS = 200;
    const MAX_BODY = 1048576;

    /**
     * Whether a delivery is the repository's: GitHub / Gitea's HMAC-SHA256 of the body, or
     * GitLab's token (compared with its hash).
     *
     * @param array<string, string> $headers lower-case names
     */
    public static function verify(string $provider, string $body, array $headers, ?string $secret, string $secretHash): bool
    {
        if ($provider === 'gitlab') {
            $token = strval($headers['x-gitlab-token'] ?? '');

            return $token !== '' && hash_equals($secretHash, hash('sha256', $token));
        }
        if ($secret === null || $secret === '') {
            return false;
        }
        $sig = $provider === 'gitea' ? strval($headers['x-gitea-signature'] ?? '') : strval($headers['x-hub-signature-256'] ?? '');
        $sig = preg_replace('/^sha256=/', '', $sig) ?? '';
        if (!preg_match('/^[0-9a-f]{64}$/', $sig)) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $body, $secret), $sig);
    }

    /** The delivery's own id, when the host gives one (a replay guard). */
    public static function deliveryId(string $provider, array $headers): ?string
    {
        $id = strval($headers[$provider === 'gitea' ? 'x-gitea-delivery' : 'x-github-delivery'] ?? '');
        if ($provider === 'gitlab') {
            $id = strval($headers['x-gitlab-event-uuid'] ?? '');
        }

        return preg_match('/^[A-Za-z0-9\-]{8,80}$/', $id) ? $id : null;
    }

    /** The event's kind: push / pr / other. */
    public static function event(string $provider, array $headers): string
    {
        $e = strtolower(strval($headers[$provider === 'gitlab' ? 'x-gitlab-event' : ($provider === 'gitea' ? 'x-gitea-event' : 'x-github-event')] ?? ''));
        if (in_array($e, ['push', 'push hook'], true)) {
            return 'push';
        }
        if (in_array($e, ['pull_request', 'merge request hook'], true)) {
            return 'pr';
        }

        return 'other';
    }

    /** The repository a delivery is of: "owner/name". */
    public static function repoOf(string $provider, array $p): string
    {
        $r = $provider === 'gitlab' ? strval(array_get($p, 'project.path_with_namespace', '')) : strval(array_get($p, 'repository.full_name', ''));

        return mb_strtolower(trim($r));
    }

    /** The host of the repository's web address (links go only there). */
    public static function hostOf(string $provider, array $p): string
    {
        $u = $provider === 'gitlab' ? strval(array_get($p, 'project.web_url', '')) : strval(array_get($p, 'repository.html_url', ''));
        $h = parse_url($u, PHP_URL_HOST);

        return is_string($h) ? strtolower($h) : '';
    }

    /** A link kept: https, to the repository's host; else null. */
    public static function link($url, string $host): ?string
    {
        $u = strval($url ?? '');
        $p = parse_url($u);
        if (!isset($p['scheme'], $p['host']) || strtolower($p['scheme']) !== 'https' || strtolower($p['host']) !== $host || strlen($u) > 500) {
            return null;
        }

        return $u;
    }

    protected static function text($v, int $max): string
    {
        $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', strval($v ?? '')) ?? '';

        return mb_substr(trim($s), 0, $max);
    }

    /**
     * The items of a delivery, one shape: commits of a push, or the PR / MR.
     *
     * @return array<int, array{kind: string, ref: string, title: string, text: string, author: string, url: ?string, branch: string, state: ?string, merged: bool, at: string}>
     */
    public static function items(string $provider, string $event, array $p): array
    {
        $host = static::hostOf($provider, $p);
        $out = [];
        if ($event === 'push') {
            $branch = preg_replace('#^refs/heads/#', '', strval($p['ref'] ?? '')) ?? '';
            foreach (array_slice((array) ($p['commits'] ?? []), 0, static::MAX_COMMITS) as $c) {
                $sha = strval($c['id'] ?? '');
                if (!preg_match('/^[0-9a-f]{7,64}$/i', $sha)) {
                    continue;
                }
                $msg = static::text($c['message'] ?? '', 5000);
                $out[] = [
                    'kind' => 'commit', 'ref' => strtolower($sha),
                    'title' => mb_substr(strtok($msg . "\n", "\n") ?: '', 0, 300),
                    'text' => $msg . "\n" . $branch,
                    'author' => static::text(array_get($c, 'author.name', array_get($c, 'author.username', '')), 120),
                    'url' => static::link($c['url'] ?? '', $host),
                    'branch' => static::text($branch, 200),
                    'state' => null, 'merged' => false,
                    'at' => static::time($c['timestamp'] ?? null),
                ];
            }

            return $out;
        }
        if ($event === 'pr') {
            if ($provider === 'gitlab') {
                $a = (array) ($p['object_attributes'] ?? []);
                $num = strval($a['iid'] ?? '');
                $state = strval($a['state'] ?? '');
                $merged = $state === 'merged';
                $title = $a['title'] ?? '';
                $body = $a['description'] ?? '';
                $branch = $a['source_branch'] ?? '';
                $url = $a['url'] ?? '';
                $author = array_get($p, 'user.name', array_get($p, 'user.username', ''));
                $at = $a['updated_at'] ?? null;
                $st = $merged ? 'merged' : ($state === 'closed' ? 'closed' : 'open');
            } else {
                $pr = (array) ($p['pull_request'] ?? []);
                $num = strval($pr['number'] ?? ($p['number'] ?? ''));
                $merged = !empty($pr['merged']);
                $title = $pr['title'] ?? '';
                $body = $pr['body'] ?? '';
                $branch = array_get($pr, 'head.ref', '');
                $url = $pr['html_url'] ?? '';
                $author = array_get($pr, 'user.login', '');
                $at = $pr['updated_at'] ?? null;
                $st = $merged ? 'merged' : (strval($pr['state'] ?? '') === 'closed' ? 'closed' : 'open');
            }
            if (!preg_match('/^\d{1,10}$/', $num)) {
                return [];
            }
            $title = static::text($title, 300);
            $out[] = [
                'kind' => 'pr', 'ref' => $num, 'title' => $title,
                'text' => $title . "\n" . static::text($body, 20000) . "\n" . static::text($branch, 200),
                'author' => static::text($author, 120), 'url' => static::link($url, $host),
                'branch' => static::text($branch, 200), 'state' => $st, 'merged' => $merged,
                'at' => static::time($at),
            ];
        }

        return $out;
    }

    protected static function time($v): string
    {
        $t = is_string($v) ? strtotime($v) : false;

        return date('Y-m-d H:i:s', $t !== false ? $t : time());
    }

    /**
     * The issue keys a text names: "<PROJECT KEY>-<number>" of this project (whole words, any
     * case), and the project's aliases (whole words). Upper-case keys back; at most 20.
     *
     * @param array<int, string> $aliases
     * @return array<int, string>
     */
    public static function keys(string $text, string $projectKey, array $aliases = []): array
    {
        $found = [];
        $pk = preg_quote($projectKey, '/');
        if ($projectKey !== '' && preg_match_all('/(?<![A-Za-z0-9_])' . $pk . '-(\d{1,10})(?![A-Za-z0-9_])/i', $text, $m)) {
            foreach ($m[1] as $n) {
                $found[strtoupper($projectKey) . '-' . intval($n)] = true;
            }
        }
        foreach ($aliases as $a) {
            $a = trim(strval($a));
            if ($a !== '' && preg_match('/(?<![A-Za-z0-9_\-])' . preg_quote($a, '/') . '(?![A-Za-z0-9_\-])/i', $text)) {
                $found['@' . strtolower($a)] = true;
            }
        }

        return array_slice(array_keys($found), 0, static::MAX_ISSUES);
    }

    /**
     * The words of a text that may be an issue's own key (its key column, the key the screen shows
     * and the branch name button copies, like ISS00001): a letter first, digits last, at most 50.
     * Only checked against the issues of the hook's project.
     *
     * @return array<int, string>
     */
    public static function keyWords(string $text): array
    {
        $out = [];
        if (preg_match_all('/(?<![A-Za-z0-9_])([A-Za-z][A-Za-z0-9_]{0,40}-?\d{1,12})(?![A-Za-z0-9_])/', $text, $m)) {
            foreach ($m[1] as $w) {
                $out[$w] = true;
                if (count($out) >= 50) {
                    break;
                }
            }
        }

        return array_keys($out);
    }

    /**
     * The key-like words a text says it fixes: fix / fixes / fixed / close(s/d) / resolve(s/d),
     * then a key ("Fixes PROJ-12", "closes: ISS00001"), any case. At most 20.
     *
     * @return array<int, string>
     */
    public static function closeWords(string $text): array
    {
        $out = [];
        if (preg_match_all('/\b(?:fix(?:es|ed)?|close[sd]?|resolve[sd]?)\b[\s:]+([A-Za-z0-9][A-Za-z0-9._~\-]{0,59})/i', $text, $m)) {
            foreach ($m[1] as $w) {
                $w = rtrim($w, '.~-_');
                if ($w !== '') {
                    $out[$w] = true;
                }
            }
        }

        return array_slice(array_keys($out), 0, static::MAX_ISSUES);
    }

    /** A branch name: the key, then the subject's ASCII words (lower case, with -), at most 60. */
    public static function branchName(string $key, string $subject): string
    {
        $words = preg_split('/[^A-Za-z0-9]+/', strtolower($subject)) ?: [];
        $slug = implode('-', array_filter($words, 'strlen'));
        $name = $key . ($slug !== '' ? '-' . $slug : '');

        return rtrim(substr($name, 0, 60), '-');
    }
}
