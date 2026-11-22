<?php

namespace App\Plugins\ExmentPMBacklog;

use Illuminate\Support\Facades\Http;

/**
 * Backlog API v2, read only.
 *
 * Backlog limits calls per user and per minute and says so in the
 * X-RateLimit-* headers. A call answered 429 waits until the reset the
 * answer names (at most a minute) and tries again; a run that keeps being
 * refused gives up rather than holding the batch.
 */
class Backlog
{
    /** Pause between two calls, so a long run stays polite. */
    const PAUSE_MS = 120;

    /** 429s in a row before a call gives up. */
    const MAX_RETRIES = 3;

    /** @var string e.g. https://example.backlog.com */
    protected $base;

    /** @var string */
    protected $key;

    /** @var int calls made, for the run log */
    public $calls = 0;

    public function __construct(string $spaceUrl, string $apiKey)
    {
        $this->base = rtrim(trim($spaceUrl), '/');
        $this->key = $apiKey;
    }

    /** The host part, which together with an id names a Backlog row. */
    public function host(): string
    {
        return strtolower(strval(parse_url($this->base, PHP_URL_HOST)));
    }

    public function issueUrl(string $issueKey): string
    {
        return $this->base . '/view/' . rawurlencode($issueKey);
    }

    /**
     * @param array<string, mixed> $params
     * @return mixed decoded JSON
     */
    public function get(string $path, array $params = [])
    {
        $query = array_merge($params, ['apiKey' => $this->key]);
        $url = $this->base . '/api/v2/' . ltrim($path, '/');

        for ($try = 0; ; $try++) {
            usleep(static::PAUSE_MS * 1000);
            $this->calls++;
            // Backlog takes arrays as name[]=a&name[]=b.
            $res = Http::timeout(60)->acceptJson()->get($url . '?' . static::query($query));
            if ($res->status() === 429 && $try < static::MAX_RETRIES) {
                $reset = intval($res->header('X-RateLimit-Reset'));
                $wait = $reset > 0 ? max(1, min(60, $reset - time())) : 30;
                sleep($wait);
                continue;
            }
            if (!$res->successful()) {
                // The key is in the query; never put the URL in a message.
                $body = mb_substr(strval($res->body()), 0, 300);
                throw new BacklogException('Backlog ' . $res->status() . ' ' . $path . ': ' . $body, $res->status());
            }

            return $res->json();
        }
    }

    /**
     * Issues of one project updated on or after a day, oldest change first,
     * page by page.
     *
     * @param string|null $since a Backlog timestamp; Backlog filters by its date only
     * @return \Generator<int, array<string, mixed>>
     */
    public function issues(int $projectId, ?string $since = null): \Generator
    {
        $offset = 0;
        while (true) {
            $params = [
                'projectId[]' => [$projectId],
                'count' => 100,
                'offset' => $offset,
                'sort' => 'updated',
                'order' => 'asc',
            ];
            if ($since) {
                $params['updatedSince'] = substr($since, 0, 10);
            }
            $page = (array) $this->get('issues', $params);
            foreach ($page as $issue) {
                yield (array) $issue;
            }
            if (count($page) < 100) {
                return;
            }
            $offset += 100;
        }
    }

    /**
     * Comments of one issue after an id, oldest first.
     *
     * @return \Generator<int, array<string, mixed>>
     */
    public function comments(int $issueId, ?int $afterId = null): \Generator
    {
        while (true) {
            $params = ['count' => 100, 'order' => 'asc'];
            if ($afterId) {
                $params['minId'] = $afterId + 1;
            }
            $page = (array) $this->get('issues/' . $issueId . '/comments', $params);
            foreach ($page as $c) {
                yield (array) $c;
            }
            if (count($page) < 100) {
                return;
            }
            $afterId = intval(end($page)['id']);
        }
    }

    /**
     * @param array<string, mixed> $params
     */
    protected static function query(array $params): string
    {
        $parts = [];
        foreach ($params as $name => $value) {
            foreach ((array) $value as $v) {
                $parts[] = rawurlencode($name) . '=' . rawurlencode(strval($v));
            }
        }

        return implode('&', $parts);
    }
}
