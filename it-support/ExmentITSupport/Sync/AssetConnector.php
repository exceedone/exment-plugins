<?php

namespace App\Plugins\ExmentITSupport\Sync;

use App\Plugins\ExmentITSupport\Assets\AssetMatcher;
use App\Plugins\ExmentITSupport\Assets\HostMapper;
use App\Plugins\ExmentITSupport\Its;
use Exceedone\Exment\Model\CustomValue;
use Exceedone\Exment\Model\SyncConnection;
use Exceedone\Exment\Model\SyncMapping;
use Exceedone\Exment\Services\Sync\ChangesPage;
use Exceedone\Exment\Services\Sync\RemoteItem;
use Exceedone\Exment\Services\Sync\RemoteObject;
use Exceedone\Exment\Services\Sync\SyncConnector;
use Exceedone\Exment\Services\Sync\SyncException;
use Exceedone\Exment\Services\Sync\SyncFinisher;
use Exceedone\Exment\Services\Sync\SyncMappingFields;
use Exceedone\Exment\Services\Sync\SyncWriteResult;
use Exceedone\Exment\Services\Sync\SyncWriter;

/**
 * The hosts of a monitoring tool into the IT assets (ISS02928, phase 3), on the data sync of
 * Exment 7.3 (the schedule, the lock, the runs, the dry run, the bell, the secrets).
 *
 * Every run reads all the hosts (a few thousand at most; an unchanged one is not written again,
 * by the hash of its values). A host is written on the asset it is (AssetMatcher): only what the
 * tool knows. With the mapping's "link only" (the default) a host with no asset is left (a note
 * says so); with "create" an asset is made for it. After a complete run, the assets of this
 * connection whose host is gone are marked retired (never deleted).
 *
 * Loaded only when Exment has the data sync (AssetSync::register()).
 */
abstract class AssetConnector implements SyncConnector, SyncWriter, SyncMappingFields, SyncFinisher
{
    const OBJECT = 'hosts';

    /** @var \GuzzleHttp\ClientInterface|null set by a test */
    public static $http;

    /**
     * All the hosts of the tool, read into HostMapper's shape.
     *
     * @return array<int, array<string, mixed>>
     * @throws SyncException
     */
    abstract protected function hosts(SyncConnection $connection): array;

    /** zabbix, mackerel: with the connection, what an asset's "source" says. */
    abstract protected function tool(): string;

    public function source(SyncConnection $connection): string
    {
        return $this->tool() . ':' . intval($connection->id);
    }

    protected function http(): \GuzzleHttp\ClientInterface
    {
        return static::$http ?? new \GuzzleHttp\Client(['timeout' => 20, 'connect_timeout' => 5, 'allow_redirects' => false]);
    }

    /** An address of the settings that may be called (https, not the server's own network when Exment says so). */
    protected function allowed(string $url): bool
    {
        if (!preg_match('#^https?://#i', $url)) {
            return false;
        }
        $guard = '\\Exceedone\\Exment\\Services\\WebhookUrlGuard';

        return !class_exists($guard) || $guard::isAllowed($url);
    }

    public function objects(SyncConnection $connection): array
    {
        return [new RemoteObject(static::OBJECT, 'ホスト（すべて）')];
    }

    public function fields(SyncConnection $connection, string $object): array
    {
        // written by write(): the assets' fields are the plugin's own
        return [];
    }

    public function mappingFields(SyncConnection $connection, string $object, SyncMapping $mapping): array
    {
        return [
            'asset_mode' => ['label' => 'IT 資産がないホスト', 'type' => 'select', 'options' => [
                'link' => 'つなぐだけ（資産を作らない）',
                'create' => '新しい IT 資産を作る',
            ], 'help' => '同じ資産は、ホストの ID・シリアル・管理番号・名前・IP アドレスで探します。'],
            'asset_customers' => ['label' => 'お客様の決め方', 'type' => 'text',
                'help' => '「グループ=お客様コード」を ; で区切って（例：本社サーバー=C001; 大阪=C002）。Zabbix はホストグループ、Mackerel はサービス（サービス:ロール）。資産にお客様がないときだけ入れます。'],
        ];
    }

    public function checkMappingFields(SyncConnection $connection, string $object, array $values): ?string
    {
        if (!in_array(strval($values['asset_mode'] ?? 'link'), ['link', 'create'], true)) {
            return 'IT 資産がないホストの扱いを選んでください。';
        }
        if (Its::table(Its::T_ASSET) === null) {
            return 'IT サポートのセットアップ（IT 資産のテーブル）が済んでいません。';
        }

        return null;
    }

    public function changes(SyncConnection $connection, string $object, ?string $cursor, int $limit): ChangesPage
    {
        $hosts = $this->hosts($connection);
        $offset = max(0, intval($cursor));
        $items = [];
        foreach (array_slice($hosts, $offset, max(1, $limit)) as $host) {
            if (strval($host['external_id'] ?? '') === '') {
                continue;
            }
            $items[] = new RemoteItem(strval($host['external_id']), $host, null, null, strval($host['url'] ?? '') ?: null);
        }
        $next = $offset + max(1, $limit);
        $done = $next >= count($hosts);

        // done: the next run reads all from the start again
        return new ChangesPage($items, $done ? null : strval($next), $done);
    }

    public function write(SyncMapping $mapping, RemoteItem $item, bool $dry): SyncWriteResult
    {
        $connection = $mapping->connection;
        $table = Its::table(Its::T_ASSET);
        if (!isset($connection, $table)) {
            throw new SyncException('IT サポートのセットアップ（IT 資産のテーブル）が済んでいません。');
        }
        $host = $item->values;
        $source = $this->source($connection);
        $options = (array) ($mapping->option('connector') ?? []);
        $query = function () use ($table) {
            return $table->getValueModel()->query()->withoutGlobalScope(\Exceedone\Exment\Model\CustomValueModelScope::class);
        };

        // the assets that could be it
        $q = $query()->where(function ($w) use ($host, $source) {
            $w->where(function ($x) use ($host, $source) {
                $x->where('value->source', $source)->where('value->external_id', strval($host['external_id']));
            });
            foreach (['serial', 'asset_tag'] as $k) {
                if (strval($host[$k] ?? '') !== '') {
                    $w->orWhere('value->' . $k, strval($host[$k]));
                }
            }
            $fqdn = strval($host['hostname'] ?? '');
            foreach (array_unique(array_filter([$fqdn, strval($host['name'] ?? ''), strpos($fqdn, '.') !== false ? explode('.', $fqdn)[0] : ''])) as $name) {
                $w->orWhere('value->name', $name)->orWhere('value->name', strtoupper($name));
            }
            foreach ((array) ($host['ips'] ?? []) as $ip) {
                $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $ip) . '%';
                $w->orWhere('value->location', 'like', $like)->orWhere('value->ip_addresses', 'like', $like);
            }
        })->limit(50)->get();
        $candidates = [];
        foreach ($q as $row) {
            $v = (array) $row->value;
            $candidates[] = ['id' => intval($row->id)] + array_map('strval', array_intersect_key($v, array_flip(['source', 'external_id', 'serial', 'asset_tag', 'name', 'location', 'ip_addresses'])));
        }
        $found = AssetMatcher::match($host, $source, $candidates);
        $label = strval($host['name'] ?? '') ?: strval($host['external_id']);

        if (!empty($found['ambiguous'])) {
            return new SyncWriteResult(SyncWriteResult::SKIPPED, null, [
                "{$label}: IT 資産が " . count($found['ambiguous']) . ' 件あてはまります（' . $found['by'] . '：#' . implode('・#', $found['ambiguous']) . '）。つないでいません。どれかを直してください。',
            ]);
        }
        if (isset($found['id'])) {
            /** @var CustomValue $asset */
            $asset = $query()->find($found['id']);
            $changes = AssetMatcher::changes($host, $source, (array) $asset->value, false);
            $customer = empty(array_get($asset->value, 'customer')) ? $this->customer($host, $options) : null;
            if (isset($customer)) {
                $changes['customer'] = $customer;
            }
            if (empty($changes)) {
                return new SyncWriteResult(SyncWriteResult::UNCHANGED, intval($asset->id));
            }
            if (!$dry) {
                $asset->setValue($changes);
                $asset->save();
            }

            return new SyncWriteResult(SyncWriteResult::UPDATED, intval($asset->id));
        }
        if (strval($options['asset_mode'] ?? 'link') !== 'create') {
            return new SyncWriteResult(SyncWriteResult::SKIPPED, null, ["{$label}: あてはまる IT 資産がありません（つなぐだけの設定なので、作っていません）。"]);
        }
        $values = AssetMatcher::changes($host, $source, [], true);
        $customer = $this->customer($host, $options);
        if (isset($customer)) {
            $values['customer'] = $customer;
        }
        if ($dry) {
            return new SyncWriteResult(SyncWriteResult::CREATED);
        }
        $asset = $table->getValueModel();
        $asset->setValue($values);
        $asset->save();

        return new SyncWriteResult(SyncWriteResult::CREATED, intval($asset->id));
    }

    /**
     * @param array<string, mixed> $host
     * @param array<string, mixed> $options
     */
    protected function customer(array $host, array $options): ?int
    {
        $code = HostMapper::customerCode((array) ($host['groups'] ?? []), strval($options['asset_customers'] ?? ''));
        $customers = Its::table(Its::T_CUSTOMER);
        if (!isset($code, $customers)) {
            return null;
        }
        $row = $customers->getValueModel()->query()->withoutGlobalScope(\Exceedone\Exment\Model\CustomValueModelScope::class)->where('value->code', $code)->first();

        return isset($row) ? intval($row->id) : null;
    }

    /**
     * After a complete run: the assets of this connection whose host the tool no longer has, or
     * has as retired, are marked retired (never deleted); one seen again is active again (by
     * write()).
     */
    public function finished(SyncMapping $mapping, bool $dry, bool $complete): array
    {
        $connection = $mapping->connection;
        $table = Its::table(Its::T_ASSET);
        if ($dry || !$complete || !isset($connection, $table)) {
            return [];
        }
        $ids = [];
        foreach ($this->hosts($connection) as $host) {
            if (empty($host['retired'])) {
                $ids[strval($host['external_id'])] = true;
            }
        }
        $n = 0;
        $rows = $table->getValueModel()->query()->withoutGlobalScope(\Exceedone\Exment\Model\CustomValueModelScope::class)
            ->where('value->source', $this->source($connection))->get();
        foreach ($rows as $asset) {
            if (strval(array_get($asset->value, 'sync_state')) === 'retired' || isset($ids[strval(array_get($asset->value, 'external_id'))])) {
                continue;
            }
            $asset->setValue(['sync_state' => 'retired']);
            $asset->save();
            $n++;
        }

        return $n > 0 ? ["監視の道具から見えなくなった IT 資産 {$n} 件に「引退」の印をつけました。"] : [];
    }

    public function allIds(SyncConnection $connection, string $object): ?array
    {
        // the engine never deletes an asset: retiring is finished()'s
        return null;
    }

    public function attachments(SyncConnection $connection, string $object, RemoteItem $item): array
    {
        return [];
    }

    public function capabilities(string $object): array
    {
        return ['import' => $object === static::OBJECT, 'export' => false];
    }

    public function callsPerMinute(): int
    {
        return 60;
    }

    /**
     * A JSON answer of the tool, or an exception that names what failed and never carries a secret.
     *
     * @param array<string, mixed> $options
     * @return array<mixed>
     */
    protected function json(string $method, string $url, array $options, string $what): array
    {
        if (!$this->allowed($url)) {
            throw new SyncException("{$what}: このアドレスには、つなげません（https で、サーバーの内側のアドレスでないもの）。");
        }
        try {
            $response = $this->http()->request($method, $url, $options + ['http_errors' => false]);
        } catch (\Throwable $e) {
            throw new SyncException("{$what}: つながりません（" . class_basename($e) . '）。');
        }
        $status = $response->getStatusCode();
        if ($status === 401 || $status === 403) {
            throw new SyncException("{$what}: キー・トークンが受け付けられませんでした（{$status}）。");
        }
        if ($status < 200 || $status >= 300) {
            throw new SyncException("{$what}: {$status} が返りました。");
        }
        $json = json_decode((string) $response->getBody(), true);
        if (!is_array($json)) {
            throw new SyncException("{$what}: 答えが JSON ではありません。");
        }

        return $json;
    }
}
