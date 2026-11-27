<?php

namespace App\Plugins\ExmentSupportDesk\Assets;

/**
 * A host of a monitoring tool read into the one shape of an IT asset (ISS02928, phase 3), without
 * the database, so the CI checks it with the tools' sample answers.
 *
 * One host:
 *   external_id  the host's id in the tool
 *   name         its display name
 *   hostname     the FQDN when the tool knows it ('' else)
 *   ips          its addresses (no loopback)
 *   os, maker_model, serial, asset_tag
 *   kind         server (the tools watch servers), or '' to leave the asset's
 *   groups       the host groups (Zabbix), services and roles (Mackerel): to find the customer
 *   url          the host's page in the tool
 *   retired      the tool says it is no longer used (disabled, retired)
 */
class HostMapper
{
    /**
     * A host of Mackerel's GET /api/v0/hosts.
     *
     * @param array<string, mixed> $h
     * @return array<string, mixed>
     */
    public static function fromMackerel(array $h, string $org = ''): array
    {
        $meta = is_array($h['meta'] ?? null) ? $h['meta'] : [];
        $kernel = is_array($meta['kernel'] ?? null) ? $meta['kernel'] : [];
        $cpu = is_array($meta['cpu'] ?? null) ? $meta['cpu'] : [];
        $firstCpu = is_array($cpu[0] ?? null) ? $cpu[0] : [];
        $ips = [];
        foreach ((array) ($h['interfaces'] ?? []) as $if) {
            if (is_array($if)) {
                $ips[] = static::str($if['ipAddress'] ?? '');
                foreach ((array) ($if['ipv4Addresses'] ?? []) as $ip) {
                    $ips[] = static::str($ip);
                }
            }
        }
        $groups = [];
        foreach ((array) ($h['roles'] ?? []) as $service => $roles) {
            $groups[] = static::str($service);
            foreach ((array) $roles as $role) {
                $groups[] = static::str($service) . ':' . static::str($role);
            }
        }
        $os = trim(static::str($kernel['platform_name'] ?? '') . ' ' . static::str($kernel['platform_version'] ?? ''));
        if ($os === '') {
            $os = trim(static::str($kernel['name'] ?? '') . ' ' . static::str($kernel['release'] ?? ''));
        }
        $name = static::str($h['displayName'] ?? '') ?: static::str($h['name'] ?? '');
        $id = static::str($h['id'] ?? '');

        return static::finish([
            'external_id' => $id,
            'name' => $name,
            'hostname' => static::str($h['name'] ?? ''),
            'ips' => $ips,
            'os' => $os,
            'maker_model' => static::str($firstCpu['model name'] ?? ''),
            'serial' => '',
            'asset_tag' => '',
            'kind' => 'server',
            'groups' => $groups,
            'url' => $id !== '' && $org !== '' ? 'https://mackerel.io/orgs/' . rawurlencode($org) . '/hosts/' . rawurlencode($id) : '',
            // retired in Mackerel (a host only powered off is still the asset it was)
            'retired' => (bool) ($h['isRetired'] ?? false),
        ]);
    }

    /**
     * A host of Zabbix's host.get (selectInterfaces, selectInventory, selectHostGroups or
     * selectGroups).
     *
     * @param array<string, mixed> $h
     * @return array<string, mixed>
     */
    public static function fromZabbix(array $h, string $zabbixUrl = ''): array
    {
        $inv = is_array($h['inventory'] ?? null) ? $h['inventory'] : [];
        $ips = [];
        $dns = '';
        foreach ((array) ($h['interfaces'] ?? []) as $if) {
            if (!is_array($if)) {
                continue;
            }
            $ips[] = static::str($if['ip'] ?? '');
            if ($dns === '' && static::str($if['dns'] ?? '') !== '') {
                $dns = static::str($if['dns']);
            }
        }
        $groups = [];
        foreach ((array) ($h['hostgroups'] ?? $h['groups'] ?? []) as $g) {
            if (is_array($g)) {
                $groups[] = static::str($g['name'] ?? '');
            }
        }
        $id = static::str($h['hostid'] ?? '');
        $technical = static::str($h['host'] ?? '');
        $base = rtrim($zabbixUrl, '/');

        return static::finish([
            'external_id' => $id,
            'name' => static::str($h['name'] ?? '') ?: $technical,
            'hostname' => $dns !== '' ? $dns : (strpos($technical, '.') !== false ? $technical : ''),
            'ips' => $ips,
            'os' => static::str($inv['os_full'] ?? '') ?: static::str($inv['os'] ?? ''),
            'maker_model' => trim(static::str($inv['vendor'] ?? '') . ' ' . static::str($inv['model'] ?? '')) ?: static::str($inv['hardware'] ?? ''),
            'serial' => static::str($inv['serialno_a'] ?? ''),
            'asset_tag' => static::str($inv['asset_tag'] ?? ''),
            'kind' => 'server',
            'groups' => $groups,
            'url' => $id !== '' && $base !== '' && preg_match('#^https?://#i', $base) ? $base . '/zabbix.php?action=host.edit&hostid=' . rawurlencode($id) : '',
            // status 1: the host is not monitored (disabled)
            'retired' => static::str($h['status'] ?? '0') === '1',
        ]);
    }

    /**
     * @param array<string, mixed> $h
     * @return array<string, mixed>
     */
    protected static function finish(array $h): array
    {
        foreach (['external_id' => 100, 'name' => 200, 'hostname' => 200, 'os' => 200, 'maker_model' => 200, 'serial' => 100, 'asset_tag' => 100, 'url' => 500] as $k => $max) {
            $h[$k] = mb_substr(trim(strval($h[$k] ?? '')), 0, $max);
        }
        $h['hostname'] = strtolower($h['hostname']);
        $ips = [];
        foreach ((array) $h['ips'] as $ip) {
            $ip = trim(strval($ip));
            if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP) !== false && !in_array($ip, ['127.0.0.1', '::1', '0.0.0.0'], true) && !in_array($ip, $ips, true)) {
                $ips[] = $ip;
            }
        }
        $h['ips'] = array_slice($ips, 0, 20);
        $h['groups'] = array_values(array_unique(array_filter(array_map(function ($g) {
            return mb_substr(trim(strval($g)), 0, 200);
        }, (array) $h['groups']))));
        if ($h['url'] !== '' && preg_match('#^https?://#i', $h['url']) !== 1) {
            $h['url'] = '';
        }
        $h['retired'] = (bool) $h['retired'];

        return $h;
    }

    /**
     * The customer of a host by the table of the mapping ("group=customer code", separated by ";"
     * or a new line): the first group of the host found in it.
     *
     * @param array<int, string> $groups
     */
    public static function customerCode(array $groups, string $table): ?string
    {
        $map = [];
        foreach (preg_split('/[\r\n;]+/', $table) ?: [] as $line) {
            $parts = explode('=', $line, 2);
            if (count($parts) === 2 && trim($parts[0]) !== '' && trim($parts[1]) !== '') {
                $map[mb_strtolower(trim($parts[0]))] = trim($parts[1]);
            }
        }
        foreach ($groups as $g) {
            $key = mb_strtolower(trim($g));
            if (isset($map[$key])) {
                return $map[$key];
            }
        }

        return null;
    }

    /** @param mixed $v */
    public static function str($v): string
    {
        if (is_string($v)) {
            return trim($v);
        }
        if (is_int($v) || is_float($v)) {
            return (string) $v;
        }

        return '';
    }
}
