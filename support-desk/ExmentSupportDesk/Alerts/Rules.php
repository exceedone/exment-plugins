<?php

namespace App\Plugins\ExmentSupportDesk\Alerts;

/**
 * The decisions of the alert intake, without the database (ISS02928), so the
 * CI can check them: what an alert does to the tickets, the signature, and
 * which IT asset an alert is about.
 */
class Rules
{
    /** What a recovery does to the ticket of the problem. */
    const ON_RESOLVED = [
        'note' => '「復旧しました」を書くだけ（担当者が閉じる）',
        'resolve' => '「復旧しました」を書いて「解決」にする',
        'close' => '「復旧しました」を書いて「完了」にする',
    ];

    /** How old a signed request may be, in seconds (either way). */
    const SIGNATURE_TOLERANCE = 300;

    /**
     * @param string $status firing | resolved
     * @param string|null $openStatus the status of the open ticket of this fingerprint, if any
     * @param int|null $lastEndedAt when the newest resolved or closed ticket of it ended (unix time)
     * @return string count | reopen | create | note | resolve | close | ignore
     */
    public static function decide(string $status, ?string $openStatus, ?int $lastEndedAt, string $onResolved, int $flapMinutes, int $now): string
    {
        if ($status === 'resolved') {
            if ($openStatus === null) {
                return 'ignore'; // nothing open: already closed by a person, or never seen
            }

            return array_key_exists($onResolved, static::ON_RESOLVED) ? $onResolved : 'note';
        }
        if ($openStatus !== null) {
            return 'count';
        }
        if ($flapMinutes > 0 && $lastEndedAt !== null && $now - $lastEndedAt >= 0 && $now - $lastEndedAt <= $flapMinutes * 60) {
            return 'reopen';
        }

        return 'create';
    }

    /**
     * The signature of a request: "sha256=" and the hex HMAC-SHA256 of
     * "<timestamp>.<body>" with the source's signing secret (the same form as
     * Exment's own JSON webhook).
     */
    public static function sign(string $secret, string $timestamp, string $body): string
    {
        return 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $body, $secret);
    }

    public static function verify(string $secret, string $timestamp, string $body, string $given, int $now): bool
    {
        if ($secret === '' || $given === '' || preg_match('/^\d{9,12}$/', $timestamp) !== 1) {
            return false;
        }
        if (abs($now - intval($timestamp)) > static::SIGNATURE_TOLERANCE) {
            return false;
        }
        $given = strtolower(trim($given));
        if (strpos($given, 'sha256=') !== 0) {
            $given = 'sha256=' . $given;
        }

        return hash_equals(static::sign($secret, $timestamp, $body), $given);
    }

    /**
     * Whether an IT asset is the alert's host: its name is the host or the
     * host's first label (web01 for web01.example.jp), or its "place / IP
     * address" field holds the IP or the host as a word.
     *
     * @param string $name the asset's name
     * @param string $location the asset's "場所・IP アドレス"
     */
    public static function assetMatches(string $name, string $location, string $host, string $ip): bool
    {
        $name = strtolower(trim($name));
        $host = strtolower(trim($host));
        if ($name !== '' && $host !== '') {
            if ($name === $host) {
                return true;
            }
            if (!Adapters::isIp($host) && strpos($host, '.') !== false && $name === explode('.', $host)[0]) {
                return true;
            }
        }
        $words = preg_split('/[\s,;、，\/()（）]+/u', strtolower($location)) ?: [];
        foreach (array_filter([$ip, $host]) as $wanted) {
            if (in_array(strtolower($wanted), $words, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the IP is allowed by the list (one address or CIDR range a
     * line; empty means every address).
     */
    public static function ipAllowed(string $list, string $ip): bool
    {
        $ranges = array_values(array_filter(array_map('trim', preg_split('/[\s,]+/', $list) ?: [])));
        if (empty($ranges)) {
            return true;
        }
        foreach ($ranges as $range) {
            if (static::inRange($ip, $range)) {
                return true;
            }
        }

        return false;
    }

    public static function inRange(string $ip, string $range): bool
    {
        $bin = @inet_pton($ip);
        if ($bin === false) {
            return false;
        }
        $parts = explode('/', $range, 2);
        $net = @inet_pton($parts[0]);
        if ($net === false || strlen($net) !== strlen($bin)) {
            return false;
        }
        $bits = isset($parts[1]) ? intval($parts[1]) : strlen($bin) * 8;
        if ($bits < 0 || $bits > strlen($bin) * 8 || (isset($parts[1]) && preg_match('/^\d+$/', $parts[1]) !== 1)) {
            return false;
        }
        $bytes = intdiv($bits, 8);
        if (substr($bin, 0, $bytes) !== substr($net, 0, $bytes)) {
            return false;
        }
        $rest = $bits % 8;
        if ($rest === 0) {
            return true;
        }
        $mask = (0xFF << (8 - $rest)) & 0xFF;

        return (ord($bin[$bytes]) & $mask) === (ord($net[$bytes]) & $mask);
    }
}
