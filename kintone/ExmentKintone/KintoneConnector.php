<?php

namespace App\Plugins\ExmentKintone;

use Carbon\Carbon;
use Exceedone\Exment\Model\SyncConnection;
use Exceedone\Exment\Services\Sync\ChangesPage;
use Exceedone\Exment\Services\Sync\RemoteField;
use Exceedone\Exment\Services\Sync\RemoteItem;
use Exceedone\Exment\Services\Sync\RemoteObject;
use Exceedone\Exment\Services\Sync\SyncConnector;
use Exceedone\Exment\Services\Sync\SyncException;
use Exceedone\Exment\Services\WebhookUrlGuard;
use Illuminate\Support\Facades\Http;

/**
 * The kintone connector of the data sync of Exment (ISS00113, Exment 7.3.0 or later): the
 * records of a kintone app imported into a table of Exment (the import only in 7.3).
 *
 * A connection is a subdomain of kintone and either API tokens (one or more, comma separated:
 * the apps of the tokens) or the login name and the password of a user (then the apps are
 * listed). The secrets are kept encrypted by Exment and never written to a log.
 *
 * Loaded only when Exment has the data sync (PluginPage): the interface is not in Exment 7.2.
 */
class KintoneConnector implements SyncConnector
{
    public function key(): string
    {
        return 'kintone';
    }

    public function label(): string
    {
        return 'kintone';
    }

    public function settingFields(): array
    {
        return [
            'domain' => Lang::t('domain'),
            'apps' => Lang::t('apps'),
            'login' => Lang::t('login'),
        ];
    }

    public function secretFields(): array
    {
        return [
            'token' => Lang::t('token'),
            'password' => Lang::t('password'),
        ];
    }

    public function test(SyncConnection $connection): ?string
    {
        try {
            $api = $this->api($connection);
            $apps = $this->appIds($connection);
            if (count($apps) > 0) {
                $api->appName($apps[0]);
            } else {
                $api->apps();
            }
            return null;
        } catch (SyncException $e) {
            return $e->getMessage();
        } catch (\Throwable $e) {
            return static::message($e);
        }
    }

    public function objects(SyncConnection $connection): array
    {
        $api = $this->api($connection);
        $objects = [];
        try {
            $ids = $this->appIds($connection);
            if (count($ids) === 0 && !$this->usesToken($connection)) {
                foreach ($api->apps() as $id => $name) {
                    $objects[] = new RemoteObject(strval($id), $name . ' (' . $id . ')');
                }
                return $objects;
            }
            foreach ($ids as $id) {
                $objects[] = new RemoteObject($id, $api->appName($id) . ' (' . $id . ')');
            }
        } catch (\Throwable $e) {
            throw new SyncException(static::message($e));
        }
        return $objects;
    }

    public function fields(SyncConnection $connection, string $object): array
    {
        try {
            $fields = [];
            foreach ($this->api($connection)->fields($object) as $code => $property) {
                $type = strval($property['type'] ?? '');
                $fields[] = new RemoteField(
                    strval($code),
                    strval($property['label'] ?? $code),
                    KintoneApi::TYPES[$type] ?? null,
                    boolval($property['required'] ?? false),
                    KintoneApi::options($property),
                    in_array($type, KintoneApi::MULTIPLE, true)
                );
            }
            return $fields;
        } catch (\Throwable $e) {
            throw new SyncException(static::message($e));
        }
    }

    public function changes(SyncConnection $connection, string $object, ?string $cursor, int $limit): ChangesPage
    {
        try {
            $host = strval($this->host($connection));
            [$records, $next, $done] = $this->api($connection)->changes($object, $cursor, $limit);
            $items = [];
            foreach ($records as $record) {
                $item = KintoneApi::item($record);
                $items[] = new RemoteItem(
                    $item['id'],
                    $item['values'],
                    isset($item['updated']) ? Carbon::parse($item['updated']) : null,
                    isset($item['created']) ? Carbon::parse($item['created']) : null,
                    KintoneApi::recordUrl($host, $object, $item['id']),
                    count($item['files']) > 0
                );
            }
            return new ChangesPage($items, $next, $done);
        } catch (\Throwable $e) {
            throw new SyncException(static::message($e));
        }
    }

    public function allIds(SyncConnection $connection, string $object): ?array
    {
        try {
            return $this->api($connection)->allIds($object);
        } catch (\Throwable $e) {
            throw new SyncException(static::message($e));
        }
    }

    public function attachments(SyncConnection $connection, string $object, RemoteItem $item): array
    {
        $api = $this->api($connection);
        // the record again: the keys of its files are given with the record only
        $records = $api->get('/k/v1/records.json', ['app' => $object, 'query' => '$id = ' . intval($item->id)])['records'] ?? [];
        $files = [];
        foreach ((array)$records as $record) {
            foreach (KintoneApi::item((array)$record)['files'] as $file) {
                if ($file['key'] !== '' && $file['name'] !== '' && !isset($files[$file['name']])) {
                    $files[$file['name']] = $api->file($file['key']);
                }
            }
        }
        return $files;
    }

    public function capabilities(string $object): array
    {
        return ['import' => true, 'export' => false];
    }

    public function callsPerMinute(): int
    {
        return 300;
    }

    /**
     * @return array<int, string>
     */
    protected function appIds(SyncConnection $connection): array
    {
        return array_values(array_filter(array_map('trim', explode(',', strval($connection->setting('apps')))), function ($id) {
            return preg_match('/^\d+$/', $id) === 1;
        }));
    }

    protected function usesToken(SyncConnection $connection): bool
    {
        return !is_nullorempty($connection->secret('token'));
    }

    protected function host(SyncConnection $connection): ?string
    {
        return KintoneApi::host(strval($connection->setting('domain')));
    }

    protected function api(SyncConnection $connection): KintoneApi
    {
        $host = $this->host($connection);
        if (!isset($host)) {
            throw new SyncException(Lang::t('bad_domain'));
        }
        $headers = ['X-Requested-With' => 'XMLHttpRequest'];
        $token = $connection->secret('token');
        if (!is_nullorempty($token)) {
            $headers['X-Cybozu-API-Token'] = strval($token);
        } else {
            $login = strval($connection->setting('login'));
            $password = strval($connection->secret('password'));
            if ($login === '' || $password === '') {
                throw new SyncException(Lang::t('no_auth'));
            }
            $headers['X-Cybozu-Authorization'] = base64_encode($login . ':' . $password);
        }
        return new KintoneApi(function (string $path, array $query) use ($host, $headers) {
            $url = 'https://' . $host . $path;
            // checked as a webhook of Exment: https, not the server itself, the address checked
            $ips = WebhookUrlGuard::allowedIps($url);
            if ($ips === null) {
                throw new SyncException(Lang::t('not_allowed', ['host' => $host]));
            }
            $options = ['allow_redirects' => false];
            if (!empty($ips) && defined('CURLOPT_RESOLVE')) {
                $ip = $ips[0];
                $options['curl'] = [CURLOPT_RESOLVE => [$host . ':443:' . (str_contains($ip, ':') ? '[' . $ip . ']' : $ip)]];
            }
            $response = Http::withHeaders($headers)->withOptions($options)->timeout(30)->connectTimeout(5)->get($url, $query);
            return [$response->status(), $response->body(), strval($response->header('Content-Type'))];
        });
    }

    /**
     * What may be shown of an error: its message has no secret (kintone's own message, or ours).
     */
    protected static function message(\Throwable $e): string
    {
        if ($e instanceof SyncException || $e instanceof \RuntimeException) {
            return $e->getMessage();
        }
        return get_class($e);
    }
}
