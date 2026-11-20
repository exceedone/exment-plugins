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
}
