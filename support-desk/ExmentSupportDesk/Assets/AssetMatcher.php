<?php

namespace App\Plugins\ExmentSupportDesk\Assets;

/**
 * Which IT asset a host of a monitoring tool is (ISS02928, phase 3), without the database: the
 * assets that could be it are read by the writer, this decides.
 *
 * In order, the first rule that finds exactly one asset decides:
 *   1. the same tool and the same id (linked before)
 *   2. the same serial number
 *   3. the same asset tag (管理番号)
 *   4. the name is the FQDN, the host's name or the first label of the FQDN
 *   5. one of the host's addresses in the asset's addresses or "place / IP address"
 * A rule that finds two or more stops: the host is not linked, and a person decides (an asset is
 * never merged by a guess). An asset linked to another host of the same tool is not taken.
 */
class AssetMatcher
{
    const BY = ['external_id', 'serial', 'asset_tag', 'name', 'ip'];

    /**
     * @param array<string, mixed> $host from HostMapper
     * @param array<int, array{id: int, source?: string, external_id?: string, serial?: string, asset_tag?: string, name?: string, location?: string, ip_addresses?: string}> $assets
     * @return array{id: int|null, by: string|null, ambiguous: array<int, int>}
     */
    public static function match(array $host, string $source, array $assets): array
    {
        $none = ['id' => null, 'by' => null, 'ambiguous' => []];
        // an asset already a host of this tool, but another host: not this one
        $free = array_values(array_filter($assets, function ($a) use ($host, $source) {
            $theirs = trim(strval($a['external_id'] ?? ''));

            return !(strval($a['source'] ?? '') === $source && $theirs !== '' && $theirs !== strval($host['external_id'] ?? ''));
        }));

        $rules = [
            'external_id' => function ($a) use ($host, $source) {
                return strval($host['external_id'] ?? '') !== '' && strval($a['source'] ?? '') === $source
                    && strval($a['external_id'] ?? '') === strval($host['external_id']);
            },
            'serial' => function ($a) use ($host) {
                return static::same($host['serial'] ?? '', $a['serial'] ?? '');
            },
            'asset_tag' => function ($a) use ($host) {
                return static::same($host['asset_tag'] ?? '', $a['asset_tag'] ?? '');
            },
            'name' => function ($a) use ($host) {
                $name = mb_strtolower(trim(strval($a['name'] ?? '')));
                if ($name === '') {
                    return false;
                }
                $fqdn = mb_strtolower(strval($host['hostname'] ?? ''));
                $names = array_filter([$fqdn, mb_strtolower(strval($host['name'] ?? '')), $fqdn !== '' && strpos($fqdn, '.') !== false ? explode('.', $fqdn)[0] : '']);

                return in_array($name, $names, true);
            },
            'ip' => function ($a) use ($host) {
                $words = preg_split('/[\s,;、，\/()（）]+/u', mb_strtolower(strval($a['location'] ?? '') . ' ' . strval($a['ip_addresses'] ?? ''))) ?: [];
                foreach ((array) ($host['ips'] ?? []) as $ip) {
                    if ($ip !== '' && in_array(mb_strtolower($ip), $words, true)) {
                        return true;
                    }
                }

                return false;
            },
        ];
        foreach ($rules as $by => $rule) {
            $hits = array_values(array_filter($by === 'external_id' ? $assets : $free, $rule));
            if (count($hits) === 1) {
                return ['id' => intval($hits[0]['id']), 'by' => $by, 'ambiguous' => []];
            }
            if (count($hits) > 1) {
                return ['id' => null, 'by' => $by, 'ambiguous' => array_map(function ($a) {
                    return intval($a['id']);
                }, $hits)];
            }
        }

        return $none;
    }

    /** Two serials or tags are the same: not empty, apart from case and spaces. */
    protected static function same($a, $b): bool
    {
        $a = preg_replace('/\s+/', '', mb_strtolower(trim(strval($a)))) ?? '';
        $b = preg_replace('/\s+/', '', mb_strtolower(trim(strval($b)))) ?? '';

        return $a !== '' && $a === $b;
    }

    /**
     * What the sync writes on the asset: only what the tool knows, and only what changed; the
     * fields people write (the customer, the user, the dates, the notes) are not here.
     *
     * @param array<string, mixed> $host
     * @param array<string, mixed> $current the asset's values
     * @return array<string, mixed>
     */
    public static function changes(array $host, string $source, array $current, bool $isNew): array
    {
        $want = [
            'source' => $source,
            'external_id' => strval($host['external_id'] ?? ''),
            'external_url' => strval($host['url'] ?? ''),
            'hostname' => strval($host['hostname'] ?? ''),
            'ip_addresses' => implode(', ', (array) ($host['ips'] ?? [])),
            'sync_state' => !empty($host['retired']) ? 'retired' : 'active',
        ];
        // the tool's description of the machine: written when it says something
        foreach (['os', 'maker_model', 'serial'] as $k) {
            if (strval($host[$k] ?? '') !== '') {
                $want[$k] = strval($host[$k]);
            }
        }
        if ($isNew) {
            $want['name'] = strval($host['name'] ?? '') ?: strval($host['hostname'] ?? '') ?: strval($host['external_id'] ?? '');
            if (strval($host['kind'] ?? '') !== '') {
                $want['kind'] = strval($host['kind']);
            }
            if (strval($host['asset_tag'] ?? '') !== '') {
                $want['asset_tag'] = strval($host['asset_tag']);
            }
        }
        $out = [];
        foreach ($want as $k => $v) {
            if (strval($current[$k] ?? '') !== strval($v)) {
                $out[$k] = $v;
            }
        }

        return $out;
    }
}
