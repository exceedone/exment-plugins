<?php

namespace App\Plugins\ExmentGroupware\Calendar;

use App\Plugins\ExmentGroupware\Mail\HostGuard;
use App\Plugins\ExmentGroupware\Mail\MailException;

/**
 * The requests of the Google and Microsoft calendars (ISS02947, part 6): their sign-in (token)
 * and their calendar API, and nothing else.
 *
 * Only https, port 443 and the fixed hosts (HOSTS): an address of another host (a nextLink or a
 * deltaLink of another one, too) is refused before any connection. Each address goes through
 * HostGuard::checkWeb as an ICS link does, and every address of the name must be public, even
 * when the site allows the internal network for ICS links. The connection goes to the address
 * checked (no second lookup of the name). No redirect is followed. Each request has the time and
 * size limits of an ICS link (IcsFetch::TIMEOUT, IcsFetch::MAX_BYTES), within the deadline of
 * the whole read.
 *
 * The client is a callable, so the tests give a fake one (no network):
 *   function (string $method, string $url, array $headers, string $body, array $opts): array{status: int, headers: array<string, string>, body: string}
 * $opts: host, port, ip (where to connect), timeout, max_bytes.
 */
class CloudFetch
{
    const HOSTS = ['www.googleapis.com', 'oauth2.googleapis.com', 'graph.microsoft.com', 'login.microsoftonline.com'];

    /** @var callable|null the client (null: curl) */
    protected $client;
    /** @var callable|null host => IP addresses (null: DNS) */
    protected $resolve;
    /** @var callable|null the clock, seconds */
    protected $clock;
    /** @var float|null when the whole read must end (null: each request its own limit only) */
    protected $deadline = null;

    public function __construct(?callable $client = null, ?callable $resolve = null, ?callable $clock = null)
    {
        $this->client = $client;
        $this->resolve = $resolve;
        $this->clock = $clock;
    }

    public function now(): float
    {
        return $this->clock !== null ? floatval(($this->clock)()) : microtime(true);
    }

    /** The whole read (all its requests) ends within $seconds from now. */
    public function within(float $seconds): void
    {
        $this->deadline = $this->now() + $seconds;
    }

    /** The hosts of each provider: a request for one never goes to the other's. */
    const PROVIDER_HOSTS = [
        'google' => ['www.googleapis.com', 'oauth2.googleapis.com'],
        'microsoft' => ['graph.microsoft.com', 'login.microsoftonline.com'],
    ];

    /**
     * Whether an address is one this class may ask (https, port 443, a fixed host; with a
     * provider, one of that provider's hosts only).
     */
    public static function allowedUrl(string $url, ?string $provider = null): bool
    {
        $p = parse_url($url);
        if (!is_array($p) || strtolower(strval($p['scheme'] ?? '')) !== 'https' || isset($p['user']) || isset($p['pass'])) {
            return false;
        }
        if (isset($p['port']) && intval($p['port']) !== 443) {
            return false;
        }
        $hosts = $provider === null ? static::HOSTS : (static::PROVIDER_HOSTS[$provider] ?? []);
        return in_array(strtolower(strval($p['host'] ?? '')), $hosts, true);
    }

    /**
     * @param array<string, string> $headers
     * @param array<string, string>|null $form a form to POST (application/x-www-form-urlencoded)
     * @param string|null $provider google | microsoft: only that provider's hosts
     * @return array{status: int, headers: array<string, string>, body: string, json: array<string, mixed>|null}
     * @throws IcsFetchError its message a code: host, bad_url, dns, private, redirects, too_big, network, too_slow
     */
    public function request(string $method, string $url, array $headers = [], ?array $form = null, ?string $provider = null): array
    {
        if (!static::allowedUrl($url, $provider)) {
            throw new IcsFetchError('host');
        }
        $timeout = IcsFetch::TIMEOUT;
        if ($this->deadline !== null) {
            $left = $this->deadline - $this->now();
            if ($left <= 0) {
                throw new IcsFetchError('too_slow');
            }
            $timeout = min($timeout, $left);
        }
        try {
            $g = HostGuard::checkWeb($url, $this->resolve);
        } catch (MailException $e) {
            throw new IcsFetchError($e->getMessage());
        }
        // a fixed public host: never an internal address, whatever the setting of the ICS links
        foreach ($g['ips'] as $ip) {
            if (!HostGuard::webAddressAllowed($ip, false)) {
                throw new IcsFetchError('private');
            }
        }
        $body = '';
        $headers += ['Accept' => 'application/json', 'User-Agent' => 'Exment-Groupware-Calendar/1.0'];
        if ($form !== null) {
            $body = http_build_query($form, '', '&', PHP_QUERY_RFC3986);
            $headers['Content-Type'] = 'application/x-www-form-urlencoded';
        }
        $opts = ['host' => $g['host'], 'port' => $g['port'], 'ip' => $g['ips'][0], 'timeout' => max(1, intval(ceil($timeout))), 'max_bytes' => IcsFetch::MAX_BYTES];
        $method = strtoupper($method);
        $res = $this->client !== null ? ($this->client)($method, $g['url'], $headers, $body, $opts) : static::curl($method, $g['url'], $headers, $body, $opts);
        $status = intval($res['status'] ?? 0);
        if (in_array($status, [301, 302, 303, 307, 308], true)) {
            throw new IcsFetchError('redirects');
        }
        $text = strval($res['body'] ?? '');
        if (strlen($text) > IcsFetch::MAX_BYTES) {
            throw new IcsFetchError('too_big');
        }
        $json = json_decode($text, true);
        return [
            'status' => $status,
            'headers' => array_change_key_case((array) ($res['headers'] ?? []), CASE_LOWER),
            'body' => $text,
            'json' => is_array($json) ? $json : null,
        ];
    }

    /**
     * The real client: curl to the checked address only (CURLOPT_RESOLVE), https only, no
     * redirect, no proxy, the limits.
     *
     * @param array<string, string> $headers
     * @param array{host: string, port: int, ip: string, timeout: int, max_bytes: int} $opts
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public static function curl(string $method, string $url, array $headers, string $body, array $opts): array
    {
        if (!function_exists('curl_init')) {
            throw new IcsFetchError('network');
        }
        $ip = strpos($opts['ip'], ':') !== false ? '[' . $opts['ip'] . ']' : $opts['ip'];
        $out = '';
        $head = [];
        $tooBig = false;
        $max = intval($opts['max_bytes']);
        $lines = [];
        foreach ($headers as $k => $v) {
            $lines[] = $k . ': ' . str_replace(["\r", "\n"], '', $v);
        }
        $ch = curl_init($url);
        $set = [
            CURLOPT_RESOLVE => [$opts['host'] . ':' . $opts['port'] . ':' . $ip],
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => intval($opts['timeout']),
            CURLOPT_HTTPHEADER => $lines,
            CURLOPT_ENCODING => '',
            CURLOPT_NOPROXY => '*',
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HEADERFUNCTION => function ($c, $line) use (&$head) {
                $p = strpos($line, ':');
                if ($p !== false) {
                    $head[strtolower(trim(substr($line, 0, $p)))] = trim(substr($line, $p + 1));
                } elseif (preg_match('/^HTTP\//', $line)) {
                    $head = [];
                }
                return strlen($line);
            },
            CURLOPT_WRITEFUNCTION => function ($c, $chunk) use (&$out, &$tooBig, $max) {
                if (strlen($out) + strlen($chunk) > $max) {
                    $tooBig = true;
                    return 0;
                }
                $out .= $chunk;
                return strlen($chunk);
            },
        ];
        if ($method === 'POST') {
            $set[CURLOPT_POSTFIELDS] = $body;
        }
        curl_setopt_array($ch, $set);
        $ok = curl_exec($ch);
        $status = intval(curl_getinfo($ch, CURLINFO_RESPONSE_CODE));
        $primary = strval(curl_getinfo($ch, CURLINFO_PRIMARY_IP));
        curl_close($ch);
        if ($ok !== false && @inet_pton($primary) !== @inet_pton($opts['ip'])) {
            throw new IcsFetchError('private');
        }
        if ($tooBig) {
            throw new IcsFetchError('too_big');
        }
        if ($ok === false) {
            throw new IcsFetchError('network');
        }
        return ['status' => $status, 'headers' => $head, 'body' => $out];
    }
}
