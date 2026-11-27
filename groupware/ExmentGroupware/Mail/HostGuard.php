<?php

namespace App\Plugins\ExmentGroupware\Mail;

/**
 * Users type the mail server's name, and Exment connects to it. Without a
 * check, that is a way to make the Exment server knock on machines of its
 * own network (on Exment SaaS: the cloud's metadata service, the database).
 * So only public addresses and the mail ports are allowed, unless the
 * system administrator allows the internal network in the plugin settings
 * (an on-premises Exment with the company's own mail server).
 *
 * The name is resolved again at every connection, so a name that later
 * points inside is still refused.
 */
class HostGuard
{
    const PORTS = [25, 110, 143, 465, 587, 993, 995, 2525];

    /** @var bool */
    protected static $allowPrivate = false;

    public static function allowPrivate(bool $allow): void
    {
        static::$allowPrivate = $allow;
    }

    public static function check(string $host, int $port): void
    {
        if (static::$allowPrivate) {
            return;
        }
        if (!in_array($port, static::PORTS, true)) {
            throw new MailException('ポート ' . $port . ' は使えません（使えるのは ' . implode('・', static::PORTS) . '）。');
        }
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (@gethostbynamel($host) ?: []);
        $v6 = @dns_get_record($host, DNS_AAAA);
        if (is_array($v6)) {
            foreach ($v6 as $r) {
                if (!empty($r['ipv6'])) {
                    $ips[] = $r['ipv6'];
                }
            }
        }
        if (empty($ips)) {
            throw new MailException('サーバー「' . $host . '」が見つかりません。名前を確かめてください。');
        }
        foreach ($ips as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                throw new MailException('サーバー「' . $host . '」は社内のネットワークのアドレスなので、つなげません。社内のメールサーバーを使うときは、システム管理者がプラグインの設定で許可してください。');
            }
        }
    }

    // ---- web addresses (the calendar's ICS links, ISS02947 part 5) -------------------------

    /** The ports of a web address, unless the internal network is allowed. */
    const WEB_PORTS = [80, 443, 8080, 8443];

    /** @var bool the internal network for web addresses: a setting of its own (calendar_allow_private), not the mail's */
    protected static $webAllowPrivate = false;

    public static function allowPrivateWeb(bool $allow): void
    {
        static::$webAllowPrivate = $allow;
    }

    /**
     * A web address (http, https; webcal is https) that Exment may fetch: its parts and the
     * addresses its name has now, all of them public. The connection must then go to one of
     * those addresses (not resolve the name again), and a redirect is checked again from here.
     *
     * Only the ports WEB_PORTS, always. Never allowed, even with the internal network allowed:
     * loopback (127/8, ::1), link-local (169.254/16, fe80::/10: the cloud's metadata service),
     * "this network" (0/8, ::), multicast, the reserved ranges and the IPv6 ranges that carry
     * IPv4 (NAT64, Teredo, 6to4). The internal network (10/8, 172.16/12, 192.168/16, 100.64/10,
     * fc00::/7) only with allowPrivateWeb() (the plugin's setting calendar_allow_private).
     *
     * @param callable|null $resolve host => list of IP addresses (the tests give a fake)
     * @return array{url: string, scheme: string, host: string, port: int, ips: array<int, string>}
     * @throws MailException with a code as its message: bad_url, scheme, userinfo, port, dns, private
     */
    public static function checkWeb(string $url, ?callable $resolve = null): array
    {
        $url = trim($url);
        if (preg_match('/^webcals?:\/\//i', $url)) {
            $url = 'https://' . preg_replace('/^webcals?:\/\//i', '', $url);
        }
        if ($url === '' || strlen($url) > 2000 || preg_match('/[\x00-\x20\x7f]/', $url)) {
            throw new MailException('bad_url');
        }
        $p = parse_url($url);
        if (!is_array($p) || empty($p['scheme']) || empty($p['host'])) {
            throw new MailException('bad_url');
        }
        $scheme = strtolower($p['scheme']);
        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new MailException('scheme');
        }
        if (isset($p['user']) || isset($p['pass'])) {
            throw new MailException('userinfo');
        }
        $host = strtolower(trim($p['host'], '[]'));
        $port = isset($p['port']) ? intval($p['port']) : ($scheme === 'https' ? 443 : 80);
        if ($port < 1 || $port > 65535 || !in_array($port, static::WEB_PORTS, true)) {
            throw new MailException('port');
        }
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ips = [$host];
        } else {
            if (!preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)*\.?$/', $host) || $host === 'localhost' || substr($host, -10) === '.localhost') {
                throw new MailException($host === 'localhost' || substr($host, -10) === '.localhost' ? 'private' : 'bad_url');
            }
            $ips = $resolve !== null ? (array) $resolve($host) : static::resolve($host);
        }
        $ips = array_values(array_unique(array_filter(array_map('strval', $ips), function ($ip) {
            return filter_var($ip, FILTER_VALIDATE_IP) !== false;
        })));
        if (empty($ips)) {
            throw new MailException('dns');
        }
        foreach ($ips as $ip) {
            if (!static::webAddressAllowed($ip, static::$webAllowPrivate)) {
                throw new MailException('private');
            }
        }
        return ['url' => $url, 'scheme' => $scheme, 'host' => $host, 'port' => $port, 'ips' => $ips];
    }

    /** @return array<int, string> the IPv4 and IPv6 addresses of a name, now */
    protected static function resolve(string $host): array
    {
        $ips = @gethostbynamel($host) ?: [];
        $v6 = @dns_get_record($host, DNS_AAAA);
        if (is_array($v6)) {
            foreach ($v6 as $r) {
                if (!empty($r['ipv6'])) {
                    $ips[] = $r['ipv6'];
                }
            }
        }
        return $ips;
    }

    /** Whether Exment may connect to this address for a web address (see checkWeb). */
    public static function webAddressAllowed(string $ip, bool $allowPrivate = false): bool
    {
        $bin = @inet_pton($ip);
        if ($bin === false) {
            return false;
        }
        if (strlen($bin) === 16) {
            // an IPv4 address inside IPv6 (::ffff:a.b.c.d, ::a.b.c.d, 64:ff9b::a.b.c.d): as that address
            $head = substr($bin, 0, 12);
            if ($head === str_repeat("\0", 10) . "\xff\xff" || ($head === str_repeat("\0", 12) && substr($bin, 12) !== "\0\0\0\0" && substr($bin, 12) !== "\0\0\0\1")
                || $head === "\x00\x64\xff\x9b" . str_repeat("\0", 8)) {
                $v4 = inet_ntop(substr($bin, 12));
                return $v4 !== false && static::webAddressAllowed($v4, $allowPrivate);
            }
            $never = ['::/128', '::1/128', 'fe80::/10', 'ff00::/8', '100::/64', '2001:db8::/32', '2002::/16', '64:ff9b:1::/48', '2001::/32'];
            $internal = ['fc00::/7', 'fec0::/10'];
        } else {
            $never = ['0.0.0.0/8', '127.0.0.0/8', '169.254.0.0/16', '224.0.0.0/4', '240.0.0.0/4', '255.255.255.255/32', '192.0.0.0/24'];
            $internal = ['10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16', '100.64.0.0/10', '198.18.0.0/15'];
        }
        foreach ($never as $cidr) {
            if (static::inCidr($bin, $cidr)) {
                return false;
            }
        }
        foreach ($internal as $cidr) {
            if (static::inCidr($bin, $cidr)) {
                return $allowPrivate;
            }
        }
        return true;
    }

    protected static function inCidr(string $bin, string $cidr): bool
    {
        [$net, $bits] = explode('/', $cidr);
        $n = @inet_pton($net);
        if ($n === false || strlen($n) !== strlen($bin)) {
            return false;
        }
        $bits = intval($bits);
        $bytes = intdiv($bits, 8);
        if (substr($bin, 0, $bytes) !== substr($n, 0, $bytes)) {
            return false;
        }
        $rest = $bits % 8;
        if ($rest === 0) {
            return true;
        }
        $mask = (0xff << (8 - $rest)) & 0xff;
        return (ord($bin[$bytes]) & $mask) === (ord($n[$bytes]) & $mask);
    }
}
