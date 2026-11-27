<?php

namespace App\Plugins\ExmentGroupware\Calendar;

use App\Plugins\ExmentGroupware\Mail\HostGuard;
use App\Plugins\ExmentGroupware\Mail\MailException;

/**
 * GET of an outside calendar's ICS (ISS02947, part 5), the way Exment may knock on another
 * machine: every address (and every redirect) through HostGuard::checkWeb, the connection to
 * the address checked (not the name resolved again), no redirect followed by the client, an
 * ETag / Last-Modified sent back (If-None-Match, If-Modified-Since), a limit of size and of time.
 *
 * The client is a callable, so the tests give a fake one (no network):
 *   function (string $url, array $headers, array $opts): array{status: int, headers: array<string, string>, body: string}
 * $opts: host, port, ip (where to connect), timeout, max_bytes. It throws IcsFetchError('too_big')
 * past max_bytes and IcsFetchError('network') when it cannot connect.
 */
class IcsFetch
{
    const MAX_BYTES = 5242880;
    const TIMEOUT = 15;
    const MAX_REDIRECTS = 4;

    /** @var callable|null the client (null: curl) */
    protected $client;
    /** @var callable|null host => IP addresses (null: DNS) */
    protected $resolve;

    public function __construct(?callable $client = null, ?callable $resolve = null)
    {
        $this->client = $client;
        $this->resolve = $resolve;
    }

    /**
     * @return array{status: int, body: string, etag: ?string, last_modified: ?string}
     * @throws IcsFetchError its message a code: bad_url, scheme, userinfo, port, dns, private,
     *         redirects, http_NNN, too_big, network, not_ics
     */
    public function get(string $url, ?string $etag = null, ?string $lastModified = null): array
    {
        $headers = ['Accept' => 'text/calendar, text/plain;q=0.8, */*;q=0.1', 'User-Agent' => 'Exment-Groupware-Calendar/1.0'];
        if ($etag !== null && $etag !== '') {
            $headers['If-None-Match'] = $etag;
        }
        if ($lastModified !== null && $lastModified !== '') {
            $headers['If-Modified-Since'] = $lastModified;
        }
        for ($hop = 0; $hop <= static::MAX_REDIRECTS; $hop++) {
            try {
                $g = HostGuard::checkWeb($url, $this->resolve);
            } catch (MailException $e) {
                throw new IcsFetchError($e->getMessage());
            }
            $opts = ['host' => $g['host'], 'port' => $g['port'], 'ip' => $g['ips'][0], 'timeout' => static::TIMEOUT, 'max_bytes' => static::MAX_BYTES];
            $res = $this->client !== null ? ($this->client)($g['url'], $headers, $opts) : static::curl($g['url'], $headers, $opts);
            $status = intval($res['status'] ?? 0);
            $h = array_change_key_case((array) ($res['headers'] ?? []), CASE_LOWER);
            if (in_array($status, [301, 302, 303, 307, 308], true)) {
                $loc = trim(strval($h['location'] ?? ''));
                if ($loc === '') {
                    throw new IcsFetchError('http_' . $status);
                }
                $url = static::resolveUrl($g['url'], $loc);
                continue;
            }
            if ($status === 304) {
                return ['status' => 304, 'body' => '', 'etag' => $etag, 'last_modified' => $lastModified];
            }
            if ($status !== 200) {
                throw new IcsFetchError('http_' . ($status > 0 ? $status : 0));
            }
            $body = strval($res['body'] ?? '');
            if (strlen($body) > static::MAX_BYTES) {
                throw new IcsFetchError('too_big');
            }
            if (stripos(substr(ltrim(preg_replace('/^\xEF\xBB\xBF/', '', $body) ?? ''), 0, 64), 'BEGIN:VCALENDAR') !== 0) {
                throw new IcsFetchError('not_ics');
            }
            return [
                'status' => 200,
                'body' => $body,
                'etag' => isset($h['etag']) ? mb_substr(strval($h['etag']), 0, 255) : null,
                'last_modified' => isset($h['last-modified']) ? mb_substr(strval($h['last-modified']), 0, 64) : null,
            ];
        }
        throw new IcsFetchError('redirects');
    }

    /** A Location (absolute, or relative to the address that answered) as an address. */
    public static function resolveUrl(string $base, string $loc): string
    {
        if (preg_match('/^[a-z][a-z0-9+.-]*:/i', $loc)) {
            return $loc;
        }
        $b = parse_url($base);
        $origin = ($b['scheme'] ?? 'https') . '://' . (strpos(strval($b['host'] ?? ''), ':') !== false ? '[' . $b['host'] . ']' : ($b['host'] ?? '')) . (isset($b['port']) ? ':' . $b['port'] : '');
        if (strpos($loc, '//') === 0) {
            return ($b['scheme'] ?? 'https') . ':' . $loc;
        }
        if (strpos($loc, '/') === 0) {
            return $origin . $loc;
        }
        $dir = preg_replace('/[^\/]*$/', '', strval($b['path'] ?? '/')) ?: '/';
        return $origin . $dir . $loc;
    }

    /**
     * The real client: curl to the checked address only (CURLOPT_RESOLVE), http and https only,
     * no redirect, gzip, the limits.
     *
     * @param array<string, string> $headers
     * @param array{host: string, port: int, ip: string, timeout: int, max_bytes: int} $opts
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public static function curl(string $url, array $headers, array $opts): array
    {
        if (!function_exists('curl_init')) {
            throw new IcsFetchError('network');
        }
        $ip = strpos($opts['ip'], ':') !== false ? '[' . $opts['ip'] . ']' : $opts['ip'];
        $body = '';
        $head = [];
        $tooBig = false;
        $max = intval($opts['max_bytes']);
        $ch = curl_init($url);
        $lines = [];
        foreach ($headers as $k => $v) {
            $lines[] = $k . ': ' . str_replace(["\r", "\n"], '', $v);
        }
        curl_setopt_array($ch, [
            CURLOPT_RESOLVE => [$opts['host'] . ':' . $opts['port'] . ':' . $ip],
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => intval($opts['timeout']),
            CURLOPT_HTTPHEADER => $lines,
            CURLOPT_ENCODING => '',
            CURLOPT_NOPROXY => '*',
            CURLOPT_HEADERFUNCTION => function ($c, $line) use (&$head) {
                $p = strpos($line, ':');
                if ($p !== false) {
                    $head[strtolower(trim(substr($line, 0, $p)))] = trim(substr($line, $p + 1));
                } elseif (preg_match('/^HTTP\//', $line)) {
                    // a new answer (100 Continue): its headers only
                    $head = [];
                }
                return strlen($line);
            },
            CURLOPT_WRITEFUNCTION => function ($c, $chunk) use (&$body, &$tooBig, $max) {
                if (strlen($body) + strlen($chunk) > $max) {
                    $tooBig = true;
                    return 0;
                }
                $body .= $chunk;
                return strlen($chunk);
            },
        ]);
        $ok = curl_exec($ch);
        $status = intval(curl_getinfo($ch, CURLINFO_RESPONSE_CODE));
        curl_close($ch);
        if ($tooBig) {
            throw new IcsFetchError('too_big');
        }
        if ($ok === false) {
            throw new IcsFetchError('network');
        }
        return ['status' => $status, 'headers' => $head, 'body' => $body];
    }
}
