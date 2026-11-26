<?php

namespace App\Plugins\ExmentITSupport\Sync;

use App\Plugins\ExmentITSupport\Assets\HostMapper;
use Exceedone\Exment\Model\SyncConnection;
use Exceedone\Exment\Services\Sync\SyncException;

/**
 * The hosts of Mackerel (GET /api/v0/hosts) into the IT assets. A read-only API key is enough.
 */
class MackerelAssetConnector extends AssetConnector
{
    const KEY = 'its_mackerel_assets';

    const DEFAULT_API = 'https://api.mackerelio.com';

    public function key(): string
    {
        return static::KEY;
    }

    public function label(): string
    {
        return 'Mackerel → IT 資産（サポート窓口）';
    }

    protected function tool(): string
    {
        return 'mackerel';
    }

    public function settingFields(): array
    {
        return [
            'org' => 'Mackerel のオーガニゼーションの名前（ホストの画面へのリンクに使う）',
            'api_url' => 'API の URL（空なら ' . static::DEFAULT_API . '）',
        ];
    }

    public function secretFields(): array
    {
        return ['api_key' => 'API キー（Read-only でかまいません）'];
    }

    protected function api(SyncConnection $connection): string
    {
        return rtrim(strval($connection->setting('api_url')) ?: static::DEFAULT_API, '/');
    }

    /**
     * @return array<string, mixed>
     */
    protected function headers(SyncConnection $connection): array
    {
        $key = strval($connection->secret('api_key'));
        if ($key === '') {
            throw new SyncException('Mackerel: API キーがありません。');
        }

        return ['X-Api-Key' => $key, 'Accept' => 'application/json'];
    }

    public function test(SyncConnection $connection): ?string
    {
        try {
            $org = $this->json('GET', $this->api($connection) . '/api/v0/org', ['headers' => $this->headers($connection)], 'Mackerel');

            return isset($org['name']) ? null : 'Mackerel: オーガニゼーションが読めませんでした。';
        } catch (SyncException $e) {
            return $e->getMessage();
        }
    }

    protected function hosts(SyncConnection $connection): array
    {
        $json = $this->json('GET', $this->api($connection) . '/api/v0/hosts', ['headers' => $this->headers($connection)], 'Mackerel');
        $org = strval($connection->setting('org'));
        $out = [];
        foreach ((array) ($json['hosts'] ?? []) as $h) {
            if (is_array($h)) {
                $out[] = HostMapper::fromMackerel($h, $org);
            }
        }
        usort($out, function ($a, $b) {
            return strcmp(strval($a['external_id']), strval($b['external_id']));
        });

        return $out;
    }
}
