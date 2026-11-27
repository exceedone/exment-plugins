<?php

namespace App\Plugins\ExmentSupportDesk\Sync;

use App\Plugins\ExmentSupportDesk\Assets\HostMapper;
use Exceedone\Exment\Model\SyncConnection;
use Exceedone\Exment\Services\Sync\SyncException;

/**
 * The hosts of Zabbix (JSON-RPC host.get with the inventory, the interfaces and the host groups)
 * into the IT assets. An API token of a user that may only read the hosts is enough.
 *
 * Zabbix 6.4 and later take the token in the header (Authorization: Bearer); 6.0 and 6.2 in the
 * body ("auth"), which the setting "legacy" chooses (and the host groups are "groups" there).
 */
class ZabbixAssetConnector extends AssetConnector
{
    const KEY = 'sd_zabbix_assets';


    public function key(): string
    {
        return static::KEY;
    }

    public function label(): string
    {
        return 'Zabbix → IT 資産（サポートデスク）';
    }

    protected function tool(): string
    {
        return 'zabbix';
    }

    public function settingFields(): array
    {
        return [
            'url' => 'Zabbix の URL（https://zabbix.example.co.jp/ など。api_jsonrpc.php のあるところ）',
            'legacy' => 'Zabbix 6.0・6.2 なら 1（トークンを本文で送る）',
        ];
    }

    public function secretFields(): array
    {
        return ['api_token' => 'API トークン（ホストを読むだけのユーザーで作ってください）'];
    }

    protected function legacy(SyncConnection $connection): bool
    {
        return strval($connection->setting('legacy')) === '1';
    }

    /**
     * @param array<string, mixed> $params
     * @return mixed the result
     */
    protected function call(SyncConnection $connection, string $method, array $params, bool $auth = true)
    {
        $url = rtrim(strval($connection->setting('url')), '/');
        if ($url === '') {
            throw new SyncException('Zabbix: URL がありません。');
        }
        if (!preg_match('#/api_jsonrpc\.php$#', $url)) {
            $url .= '/api_jsonrpc.php';
        }
        $body = ['jsonrpc' => '2.0', 'method' => $method, 'params' => $params, 'id' => 1];
        $headers = ['Content-Type' => 'application/json-rpc', 'Accept' => 'application/json'];
        if ($auth) {
            $token = strval($connection->secret('api_token'));
            if ($token === '') {
                throw new SyncException('Zabbix: API トークンがありません。');
            }
            if ($this->legacy($connection)) {
                $body['auth'] = $token;
            } else {
                $headers['Authorization'] = 'Bearer ' . $token;
            }
        }
        $json = $this->json('POST', $url, ['json' => $body, 'headers' => $headers], 'Zabbix');
        if (isset($json['error'])) {
            // the message of Zabbix, not the request (the token is never in it)
            $error = is_array($json['error']) ? $json['error'] : [];
            throw new SyncException('Zabbix: ' . mb_substr(trim(strval($error['message'] ?? '') . ' ' . strval($error['data'] ?? '')), 0, 300));
        }

        return $json['result'] ?? null;
    }

    public function test(SyncConnection $connection): ?string
    {
        try {
            $this->call($connection, 'apiinfo.version', [], false);
            $this->call($connection, 'host.get', ['output' => ['hostid'], 'limit' => 1]);

            return null;
        } catch (SyncException $e) {
            return $e->getMessage();
        }
    }

    protected function hosts(SyncConnection $connection): array
    {
        $groups = $this->legacy($connection) ? 'selectGroups' : 'selectHostGroups';
        $base = rtrim(preg_replace('#/api_jsonrpc\.php$#', '', strval($connection->setting('url'))) ?? '', '/');
        // host.get has no offset: one call reads every host (without "limit" it gives all), in
        // the order of their ids; changes() pages them for the engine
        $result = $this->call($connection, 'host.get', [
            'output' => ['hostid', 'host', 'name', 'status'],
            'selectInterfaces' => ['ip', 'dns'],
            'selectInventory' => ['os', 'os_full', 'vendor', 'model', 'hardware', 'serialno_a', 'asset_tag'],
            $groups => ['name'],
            'sortfield' => 'hostid',
        ]);
        $out = [];
        foreach (is_array($result) ? $result : [] as $h) {
            if (is_array($h)) {
                $out[] = HostMapper::fromZabbix($h, $base);
            }
        }

        return $out;
    }
}
