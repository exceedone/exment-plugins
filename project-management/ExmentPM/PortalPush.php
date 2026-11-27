<?php

namespace App\Plugins\ExmentPM;

use Illuminate\Support\Facades\Log;

/**
 * Web Push for the bells (docs/pwa-design.md, step 2): when Exment makes a bell (NotifyNavbar)
 * for a person who turned on 「この端末に通知する」 on a browser, that browser shows a notice.
 *
 * - Off at first: the feature exment_pm.web_push of Feature management. It needs the
 *   installable web app (exment_pm.pwa), whose service worker shows the notice.
 * - One VAPID key pair (P-256) per site, made when first needed. The private key is kept
 *   encrypted (Exment's data keys when the site has them, else the application key, as the
 *   channels' secrets). 「鍵を作り直す」 makes a new pair and removes every subscription.
 * - Per person, per browser: the subscription is kept in pm_portal_push_subs, the endpoint with
 *   its sha256 (unique). Only https addresses of the known push services are taken, so the
 *   server never sends to any other address.
 * - What is sent: the bell's subject (at most 120 characters) and the link to the portal's list
 *   of notices. Never the body of the bell (a comment's text). RFC 8291 (aes128gcm) and
 *   RFC 8292 (VAPID, the site's address as sub, no e-mail address), TTL one day.
 * - When: after the answer has gone out (or at the end of the batch or the queue job that made
 *   the bell), never in the request that made it.
 * - 404 / 410 from the push service, or 5 failures in a row, remove the subscription.
 */
class PortalPush
{
    const FEATURE = 'exment_pm.web_push';
    const FEATURES = 'Exceedone\Exment\Services\Features';

    /** The push services a subscription may point at: exact hosts, and hosts under a domain. */
    const HOSTS = ['fcm.googleapis.com', 'updates.push.services.mozilla.com'];
    const HOST_SUFFIXES = ['.push.apple.com', '.notify.windows.com'];

    const SUBJECT_MAX = 120;
    const TTL = 86400;
    const MAX_FAILURES = 5;
    /** Browsers one person may have; a new one beyond this replaces the oldest. */
    const MAX_PER_USER = 10;
    /** Bells to one person sent in one request (a bulk edit makes many; the list has them all). */
    const MAX_PER_FLUSH = 3;
    /** Bells kept for after the answer in one request. */
    const MAX_PENDING = 200;

    /** @var PortalPushStore|null a test gives its own */
    public static $store;

    /** @var callable|null fn(string $url, array $headers, string $body): int, a test gives a fake */
    public static $post;

    /** @var bool|null */
    protected static $on = null;

    /** @var array<int, array{user: int, subject: string}> */
    protected static $pending = [];

    /** @var bool */
    protected static $registered = false;

    // ---- the feature -----------------------------------------------------

    public static function registerFeature(): void
    {
        if (!class_exists(static::FEATURES)) {
            return;
        }
        try {
            call_user_func([static::FEATURES, 'register'], static::FEATURE, [
                'label' => strval(PortalDefinition::trans('push.feature')),
                'description' => strval(PortalDefinition::trans('push.feature_help')),
                'who' => false,
            ]);
        } catch (\Throwable $e) {
            // an older Exment: the switch is not there
        }
    }

    /** On: this feature and the web app (its service worker shows the notice), and the tables. */
    public static function enabled(): bool
    {
        if (static::$on !== null) {
            return static::$on;
        }
        $on = false;
        if (class_exists(static::FEATURES)) {
            try {
                $on = boolval(call_user_func([static::FEATURES, 'enabled'], static::FEATURE))
                    && PortalPwa::enabled() && static::store()->exists();
            } catch (\Throwable $e) {
                $on = false;
            }
        }

        return static::$on = $on;
    }

    public static function store(): PortalPushStore
    {
        return static::$store ?? (static::$store = new PortalPushStore());
    }

    // ---- the site's key pair ---------------------------------------------

    /**
     * The site's key pair, made the first time.
     *
     * @return array{public: string, private: string}|null raw public key, private key PEM; null when it cannot be read
     */
    public static function siteKeys(bool $make = true): ?array
    {
        $row = static::store()->keys();
        if ($row === null) {
            if (!$make) {
                return null;
            }
            $row = static::makeKeys();
        }
        $public = PortalPushCrypto::unb64u($row['public_key']);
        $private = PortalChannels::decrypt($row['private_enc']);
        if ($public === null || $private === null || PortalPushCrypto::publicOf($private) !== $public) {
            Log::warning('pm_portal web push: the key pair could not be read (「鍵を作り直す」 makes a new one)');

            return null;
        }

        return ['public' => $public, 'private' => $private];
    }

    /**
     * 「鍵を作り直す」: a new pair, and every subscription goes (each browser subscribes again).
     *
     * @return int the subscriptions removed
     */
    public static function renewKeys(): int
    {
        static::makeKeys();

        return static::store()->deleteAll();
    }

    /**
     * A new pair, kept: the public key as the browsers take it, the private key encrypted.
     *
     * @return array{public_key: string, private_enc: string}
     */
    protected static function makeKeys(): array
    {
        $pair = PortalPushCrypto::newKeyPair();
        $row = ['public_key' => PortalPushCrypto::b64u($pair['public']), 'private_enc' => PortalChannels::encrypt($pair['private'])];
        static::store()->putKeys($row['public_key'], $row['private_enc']);

        return $row;
    }

    /** The public key as the browsers take it (applicationServerKey), or ''. */
    public static function publicKey(): string
    {
        $keys = static::siteKeys();

        return $keys === null ? '' : PortalPushCrypto::b64u($keys['public']);
    }

    // ---- subscriptions -----------------------------------------------------

    /** Whether a subscription's address is https on a known push service. */
    public static function endpointAllowed(string $url): bool
    {
        if (strlen($url) > 2048 || preg_match('/[\s\x00-\x1f\x7f]/', $url)) {
            return false;
        }
        $p = parse_url($url);
        if (!is_array($p) || strtolower(strval($p['scheme'] ?? '')) !== 'https' || isset($p['user']) || isset($p['pass'])) {
            return false;
        }
        if (isset($p['port']) && intval($p['port']) !== 443) {
            return false;
        }
        $host = strtolower(strval($p['host'] ?? ''));
        if (!preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/', $host)) {
            return false;
        }
        if (in_array($host, static::HOSTS, true)) {
            return true;
        }
        foreach (static::HOST_SUFFIXES as $suffix) {
            if (strlen($host) > strlen($suffix) && substr($host, -strlen($suffix)) === $suffix) {
                return true;
            }
        }

        return false;
    }

    public static function hash(string $endpoint): string
    {
        return hash('sha256', $endpoint);
    }

    /**
     * What a browser sent (PushSubscription.toJSON()), checked.
     *
     * @param mixed $input
     * @return array{endpoint: string, p256dh: string, auth: string}|string the row, or the error's code
     */
    public static function parse($input)
    {
        if (!is_array($input)) {
            return 'bad';
        }
        $endpoint = is_string($input['endpoint'] ?? null) ? trim($input['endpoint']) : '';
        if (!static::endpointAllowed($endpoint)) {
            return 'endpoint';
        }
        $keys = is_array($input['keys'] ?? null) ? $input['keys'] : [];
        $p256dh = is_string($keys['p256dh'] ?? null) ? PortalPushCrypto::unb64u($keys['p256dh']) : null;
        $auth = is_string($keys['auth'] ?? null) ? PortalPushCrypto::unb64u($keys['auth']) : null;
        if ($p256dh === null || $auth === null || strlen($auth) !== 16 || !PortalPushCrypto::validPublic($p256dh)) {
            return 'keys';
        }

        return ['endpoint' => $endpoint, 'p256dh' => PortalPushCrypto::b64u($p256dh), 'auth' => PortalPushCrypto::b64u($auth)];
    }

    /**
     * 「この端末に通知する」: keep this browser's subscription for the person. The same browser
     * again replaces its row (also when another person had it: the browser changed hands).
     *
     * @param mixed $input
     * @return array{ok: bool, error?: string}
     */
    public static function subscribe(int $userId, $input, string $userAgent = ''): array
    {
        if ($userId <= 0) {
            return ['ok' => false, 'error' => 'bad'];
        }
        $sub = static::parse($input);
        if (is_string($sub)) {
            return ['ok' => false, 'error' => $sub];
        }
        $store = static::store();
        $hash = static::hash($sub['endpoint']);
        $values = ['user_id' => $userId, 'endpoint' => $sub['endpoint'], 'endpoint_hash' => $hash, 'p256dh' => $sub['p256dh'],
            'auth' => $sub['auth'], 'device' => static::deviceName($userAgent), 'failures' => 0];
        $old = $store->byHash($hash);
        if ($old !== null) {
            $store->update(intval($old['id']), $values);
        } else {
            $store->insert($values + ['last_sent_at' => null]);
        }
        // the oldest browsers beyond the limit go
        foreach (array_slice($store->forUser($userId), static::MAX_PER_USER) as $row) {
            $store->delete(intval($row['id']));
        }

        return ['ok' => true];
    }

    /** 「止める」: this browser's subscription goes at once. Only the person's own. */
    public static function unsubscribe(int $userId, string $endpoint): bool
    {
        $row = static::store()->byHash(static::hash(trim($endpoint)));
        if ($row === null || intval($row['user_id']) !== $userId || $userId <= 0) {
            return false;
        }
        static::store()->delete(intval($row['id']));

        return true;
    }

    /** Whether this browser's subscription is kept for the person (the menu's check). */
    public static function subscribed(int $userId, string $endpoint): bool
    {
        $row = static::store()->byHash(static::hash(trim($endpoint)));

        return $row !== null && intval($row['user_id']) === $userId && $userId > 0;
    }

    /** "Chrome / Android" from the user agent, for the person to tell the browsers apart. */
    public static function deviceName(string $ua): string
    {
        $browser = '';
        foreach (['Edg/' => 'Edge', 'OPR/' => 'Opera', 'SamsungBrowser/' => 'Samsung Internet', 'Firefox/' => 'Firefox', 'FxiOS/' => 'Firefox',
            'CriOS/' => 'Chrome', 'Chrome/' => 'Chrome', 'Safari/' => 'Safari'] as $needle => $name) {
            if (strpos($ua, $needle) !== false) {
                $browser = $name;
                break;
            }
        }
        $os = '';
        foreach (['Android' => 'Android', 'iPhone' => 'iPhone', 'iPad' => 'iPad', 'Windows' => 'Windows', 'Mac OS X' => 'Mac', 'CrOS' => 'ChromeOS', 'Linux' => 'Linux'] as $needle => $name) {
            if (strpos($ua, $needle) !== false) {
                $os = $name;
                break;
            }
        }

        return mb_substr(implode(' / ', array_filter([$browser, $os], 'strlen')), 0, 100);
    }

    // ---- what is sent ------------------------------------------------------

    /** The message: the subject (at most 120 characters) and the link. Nothing else of the bell. */
    public static function payload(string $subject, string $url): string
    {
        $subject = trim(strval(preg_replace('/\s+/u', ' ', $subject)));
        if (mb_strlen($subject) > static::SUBJECT_MAX) {
            $subject = mb_substr($subject, 0, static::SUBJECT_MAX - 1) . '…';
        }

        return strval(json_encode(['title' => $subject, 'url' => $url], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /** Where a notice opens: the portal's list of notices (the bell's menu, opened). */
    public static function link(): string
    {
        return rtrim(PortalDefinition::url(), '/') . '/?notices=1';
    }

    /** The site's address, the VAPID subject. */
    public static function siteUrl(): string
    {
        $url = rtrim(strval(config('app.url')), '/');

        return $url !== '' ? $url : rtrim(url('/'), '/');
    }

    /**
     * Send one message to one browser.
     *
     * @param array<string, mixed> $sub the row
     * @param array{public: string, private: string} $keys the site's
     * @return int the push service's status; 0 when not sent (an address not allowed, or a broken row)
     */
    public static function deliver(array $sub, string $payload, array $keys, string $siteUrl, callable $post): int
    {
        $endpoint = strval($sub['endpoint'] ?? '');
        if (!static::endpointAllowed($endpoint)) {
            return 0;
        }
        $p256dh = PortalPushCrypto::unb64u(strval($sub['p256dh'] ?? ''));
        $auth = PortalPushCrypto::unb64u(strval($sub['auth'] ?? ''));
        if ($p256dh === null || $auth === null) {
            return 0;
        }
        $body = PortalPushCrypto::encrypt($payload, $p256dh, $auth);
        $headers = [
            'TTL' => strval(static::TTL),
            'Content-Encoding' => 'aes128gcm',
            'Content-Type' => 'application/octet-stream',
            'Urgency' => 'normal',
            'Authorization' => PortalPushCrypto::vapid($endpoint, $siteUrl, $keys['public'], $keys['private']),
        ];

        return intval($post($endpoint, $headers, $body));
    }

    /**
     * What the answer does to the row: 2xx sent; 404 / 410 (gone) or a 5th failure in a row remove it.
     *
     * @param array<string, mixed> $sub
     * @return string sent | removed | failed
     */
    public static function record(array $sub, int $status): string
    {
        $id = intval($sub['id'] ?? 0);
        $store = static::store();
        if ($status >= 200 && $status < 300) {
            $store->update($id, ['failures' => 0, 'last_sent_at' => date('Y-m-d H:i:s')]);

            return 'sent';
        }
        $failures = intval($sub['failures'] ?? 0) + 1;
        if ($status === 404 || $status === 410 || $status === 0 || $failures >= static::MAX_FAILURES) {
            $store->delete($id);

            return 'removed';
        }
        $store->update($id, ['failures' => $failures]);

        return 'failed';
    }

    /**
     * One bell to every browser of the person. Never throws.
     *
     * @return array<string, int> how many sent / removed / failed
     */
    public static function sendTo(int $userId, string $subject, ?callable $post = null, ?string $link = null): array
    {
        $out = ['sent' => 0, 'removed' => 0, 'failed' => 0];
        try {
            $subs = static::store()->forUser($userId);
            if (empty($subs)) {
                return $out;
            }
            $keys = static::siteKeys(false);
            if ($keys === null) {
                return $out;
            }
            $post = $post ?? static::$post ?? static::httpPost();
            $payload = static::payload($subject, $link ?? static::link());
            $site = static::siteUrl();
            foreach ($subs as $sub) {
                try {
                    $status = static::deliver($sub, $payload, $keys, $site, $post);
                } catch (\Throwable $e) {
                    // the class only: an error's message can carry the endpoint
                    Log::warning('pm_portal web push failed (' . get_class($e) . ')');
                    $status = -1;
                }
                $out[static::record($sub, $status)]++;
            }
        } catch (\Throwable $e) {
            Log::warning('pm_portal web push failed (' . get_class($e) . ')');
        }

        return $out;
    }

    /** @return callable fn(string $url, array $headers, string $body): int */
    protected static function httpPost(): callable
    {
        $client = new \GuzzleHttp\Client(['timeout' => 5, 'connect_timeout' => 3, 'allow_redirects' => false]);

        return function (string $url, array $headers, string $body) use ($client) {
            return $client->request('POST', $url, ['headers' => $headers, 'body' => $body, 'http_errors' => false])->getStatusCode();
        };
    }

    // ---- after the answer --------------------------------------------------

    /**
     * A bell was written (NotifyNavbar's created event, after PortalNotify let it through):
     * kept for after the answer.
     *
     * @param object $bell
     */
    public static function bellCreated($bell): void
    {
        try {
            if (!static::enabled() || count(static::$pending) >= static::MAX_PENDING) {
                return;
            }
            $userId = intval($bell->target_user_id ?? 0);
            if ($userId <= 0) {
                return;
            }
            static::$pending[] = ['user' => $userId, 'subject' => strval($bell->notify_subject ?? '')];
            if (!static::$registered) {
                static::$registered = true;
                app()->terminating(function () {
                    static::flush();
                });
            }
        } catch (\Throwable $e) {
            Log::warning('pm_portal web push: a bell could not be kept (' . get_class($e) . ')');
        }
    }

    /** Send what was kept: the newest few bells of each person. Never throws. */
    public static function flush(?callable $post = null): void
    {
        $pending = static::$pending;
        static::$pending = [];
        $byUser = [];
        foreach (array_reverse($pending) as $p) {
            if (count($byUser[$p['user']] ?? []) < static::MAX_PER_FLUSH) {
                $byUser[$p['user']][] = $p['subject'];
            }
        }
        foreach ($byUser as $userId => $subjects) {
            foreach (array_reverse($subjects) as $subject) {
                static::sendTo(intval($userId), $subject, $post);
            }
        }
    }

    /** @return int bells waiting (a test) */
    public static function pendingCount(): int
    {
        return count(static::$pending);
    }
}
