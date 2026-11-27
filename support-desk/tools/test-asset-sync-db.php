<?php
// ExmentSupportDesk: the data sync of monitoring tools into the IT assets, in an Exment 7.3 with
// ExmentSupportDesk set up (ISS02928, phase 3):
//   php /path/to/support-desk/tools/test-asset-sync-db.php
// The answers of Mackerel and Zabbix are given by a fake HTTP client (the fixtures, hosts of
// example.invalid): nothing leaves the machine. What the test makes is in a transaction rolled
// back at the end.
require getcwd() . '/vendor/autoload.php';
$app = require getcwd() . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Exceedone\Exment\Model\LoginUser;
use Exceedone\Exment\Model\Plugin;
use Exceedone\Exment\Model\System;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;

$ok = 0; $bad = 0;
$check = function ($label, $cond, $extra = '') use (&$ok, &$bad) { echo ($cond ? 'OK  ' : 'NG  '), $label, $cond ? '' : "  $extra", "\n"; $cond ? $ok++ : $bad++; };

$plugin = Plugin::where('plugin_name', 'ExmentSupportDesk')->first();
if (!$plugin) { echo "ExmentSupportDesk is not installed here\n"; exit(1); }
$plugin->requirePlugin();
$I = 'App\Plugins\ExmentSupportDesk\Its';
$Schema = 'App\Plugins\ExmentSupportDesk\Schema';
$AS = 'App\Plugins\ExmentSupportDesk\AssetSync';
$Base = 'App\Plugins\ExmentSupportDesk\Sync\AssetConnector';
if (!$AS::available()) { echo "this Exment has no data sync (7.3 and later)\n"; exit(1); }
Auth::guard('admin')->setUser(LoginUser::query()->whereIn('base_user_id', (array) System::system_admin_users())->first());
if (!$I::isInstalled()) { echo "set up ExmentSupportDesk first\n"; exit(1); }
$r = $Schema::install(false);
$check('the tables are up to date', $r['ok'], strval($r['message'] ?? ''));
$AS::register();
$Connectors = 'Exceedone\Exment\Services\Sync\SyncConnectors';
$mk = $Connectors::get('sd_mackerel_assets');
$zb = $Connectors::get('sd_zabbix_assets');
$check('both connectors are registered', $mk !== null && $zb !== null);

$fixture = function ($name) { return (string) file_get_contents(__DIR__ . '/fixtures/hosts/' . $name . '.json'); };
$history = [];
$fake = function (array $responses) use (&$history, $Base) {
    $stack = HandlerStack::create(new MockHandler($responses));
    $stack->push(Middleware::history($history));
    $Base::$http = new Client(['handler' => $stack]);
};
$assets = $I::table($I::T_ASSET);
$q = function () use ($assets) { return $assets->getValueModel()->query()->withoutGlobalScope(\Exceedone\Exment\Model\CustomValueModelScope::class); };

// the hosts of the fixtures (*.invalid) do not resolve: Exment's address guard (WebhookUrlGuard)
// would refuse them before the fake client is asked, so they are the administrator's allowed hosts here
config(['exment.notify_webhook_allow_hosts' => 'api.example.invalid,zabbix.example.invalid']);

DB::beginTransaction();
try {
    // ---- Mackerel, link only (the default) ---------------------------------
    $conn = new Exceedone\Exment\Model\SyncConnection(['name' => 'test mackerel', 'connector' => 'sd_mackerel_assets', 'settings' => ['org' => 'example', 'api_url' => 'https://api.example.invalid'], 'enabled' => true]);
    $conn->setSecret('api_key', 'SECRET-KEY-1');
    $conn->save();
    $mapping = Exceedone\Exment\Model\SyncMapping::create([
        'sync_connection_id' => $conn->id, 'name' => 'hosts', 'remote_object' => 'hosts', 'custom_table_id' => $assets->id,
        'direction' => 'import', 'interval_minutes' => 1440, 'conflict_rule' => 'remote', 'delete_rule' => 'mark',
        'options' => ['connector' => ['asset_mode' => 'link', 'asset_customers' => '']], 'enabled' => true,
    ]);
    // an asset of web01 there before, by its name
    $web = $assets->getValueModel();
    $web->setValue(['name' => 'web01', 'kind' => 'server', 'note' => 'keep me']);
    $web->save();

    $fake([new Response(200, [], $fixture('mackerel-hosts'))]);
    $page = $mk->changes($conn, 'hosts', null, 200);
    $check('mackerel: every host in one page, then from the start', count($page->items) === 3 && $page->done && $page->cursor === null);
    $req = $history[0]['request'];
    $check('mackerel: the key in its header, to the url of the settings', $req->getHeaderLine('X-Api-Key') === 'SECRET-KEY-1' && strval($req->getUri()) === 'https://api.example.invalid/api/v0/hosts');

    $results = [];
    foreach ($page->items as $item) {
        $results[$item->id] = $mk->write($mapping, $item, false);
    }
    $check('web01 found by its name and written', $results['2eQGEaLxibb']->kind === 'updated' && $results['2eQGEaLxibb']->customValueId === intval($web->id));
    $web = $q()->find($web->id);
    $check('the tool\'s fields on it', array_get($web->value, 'os') === 'Ubuntu 22.04' && array_get($web->value, 'ip_addresses') === '192.0.2.10'
        && array_get($web->value, 'source') === 'mackerel:' . $conn->id && array_get($web->value, 'external_id') === '2eQGEaLxibb' && array_get($web->value, 'sync_state') === 'active');
    $check('the people\'s fields kept', array_get($web->value, 'note') === 'keep me' && array_get($web->value, 'name') === 'web01');
    $check('link only: no asset made for db01', $results['3fRHFbMyjcc']->kind === 'skipped' && count($results['3fRHFbMyjcc']->notes) === 1
        && $q()->where('value->external_id', '3fRHFbMyjcc')->count() === 0);
    $check('the same values again: nothing written', $mk->write($mapping, $page->items[0], false)->kind === 'unchanged');
    $check('the messages hold no key', strpos(json_encode(array_map(function ($r) { return $r->notes; }, $results)), 'SECRET') === false);

    // ---- create, with the customer by the group ----------------------------
    $customers = $I::table($I::T_CUSTOMER);
    $c = $customers->getValueModel();
    $c->setValue(['name' => 'Shop Inc.', 'code' => 'C-SHOP']);
    $c->save();
    $mapping->options = ['connector' => ['asset_mode' => 'create', 'asset_customers' => 'Shop=C-SHOP']];
    $mapping->save();
    $dry = $mk->write($mapping, $page->items[1], true);
    $check('a dry run makes nothing', $dry->kind === 'created' && $q()->where('value->external_id', '3fRHFbMyjcc')->count() === 0);
    $made = $mk->write($mapping, $page->items[1], false);
    $db = $q()->find($made->customValueId);
    $check('create: a new asset, its customer by the service', $made->kind === 'created' && isset($db) && array_get($db->value, 'name') === 'db01.example.invalid'
        && intval(array_get($db->value, 'customer')) === intval($c->id) && array_get($db->value, 'kind') === 'server');

    // ---- retired ------------------------------------------------------------
    $fake([new Response(200, [], json_encode(['hosts' => [json_decode($fixture('mackerel-hosts'), true)['hosts'][0]]]))]);
    $notes = $mk->finished($mapping, false, true);
    $check('the host gone: its asset retired, not deleted', array_get($q()->find($db->id)->value, 'sync_state') === 'retired' && count($notes) === 1);
    $check('the one still there: active', array_get($q()->find($web->id)->value, 'sync_state') === 'active');
    $check('a dry or incomplete run retires nothing', $mk->finished($mapping, true, true) === [] && $mk->finished($mapping, false, false) === []);

    // ---- two assets that could be it ---------------------------------------
    $a = $assets->getValueModel(); $a->setValue(['name' => 'twin', 'location' => '192.0.2.99']); $a->save();
    $b = $assets->getValueModel(); $b->setValue(['name' => 'twin2', 'location' => '192.0.2.99']); $b->save();
    $item = new Exceedone\Exment\Services\Sync\RemoteItem('X1', ['external_id' => 'X1', 'name' => 'x', 'hostname' => '', 'ips' => ['192.0.2.99'], 'os' => '', 'maker_model' => '', 'serial' => '', 'asset_tag' => '', 'kind' => 'server', 'groups' => [], 'url' => '', 'retired' => false]);
    $twin = $mk->write($mapping, $item, false);
    $check('two assets match: neither linked, a note names both', $twin->kind === 'skipped' && strpos(implode('', $twin->notes), '#' . $a->id) !== false && strpos(implode('', $twin->notes), '#' . $b->id) !== false);

    // ---- Zabbix --------------------------------------------------------------
    $zconn = new Exceedone\Exment\Model\SyncConnection(['name' => 'test zabbix', 'connector' => 'sd_zabbix_assets', 'settings' => ['url' => 'https://zabbix.example.invalid/', 'legacy' => ''], 'enabled' => true]);
    $zconn->setSecret('api_token', 'ZBX-TOKEN-1');
    $zconn->save();
    $zmap = Exceedone\Exment\Model\SyncMapping::create([
        'sync_connection_id' => $zconn->id, 'name' => 'hosts', 'remote_object' => 'hosts', 'custom_table_id' => $assets->id,
        'direction' => 'import', 'interval_minutes' => 1440, 'conflict_rule' => 'remote', 'delete_rule' => 'mark',
        'options' => ['connector' => ['asset_mode' => 'link']], 'enabled' => true,
    ]);
    $fs = $assets->getValueModel(); $fs->setValue(['name' => 'nas', 'serial' => 'ABC1234']); $fs->save();
    $history = [];
    $fake([new Response(200, [], $fixture('zabbix-host-get'))]);
    $zpage = $zb->changes($zconn, 'hosts', null, 2);
    $zreq = $history[0]['request'];
    $body = json_decode((string) $zreq->getBody(), true);
    $check('zabbix: host.get to api_jsonrpc.php with the token in the header', strval($zreq->getUri()) === 'https://zabbix.example.invalid/api_jsonrpc.php'
        && $zreq->getHeaderLine('Authorization') === 'Bearer ZBX-TOKEN-1' && $body['method'] === 'host.get' && !isset($body['auth']) && isset($body['params']['selectHostGroups']));
    $check('zabbix: pages of the limit, the cursor between them', count($zpage->items) === 2 && $zpage->cursor === '2' && !$zpage->done);
    $fs01 = null;
    foreach ($zpage->items as $it) { if ($it->id === '10501') { $fs01 = $it; } }
    $w = $zb->write($zmap, $fs01, false);
    $check('zabbix: found by the serial', $w->kind === 'updated' && $w->customValueId === intval($fs->id) && array_get($q()->find($fs->id)->value, 'os') === 'Windows Server 2022 Standard');

    // 6.0 / 6.2: the token in the body, and "groups"
    $zconn->settings = ['url' => 'https://zabbix.example.invalid/', 'legacy' => '1'];
    $zconn->save();
    $history = [];
    $fake([new Response(200, [], $fixture('zabbix-host-get'))]);
    $zb->changes($zconn, 'hosts', null, 200);
    $body = json_decode((string) $history[0]['request']->getBody(), true);
    $check('zabbix 6.0: the token in the body, the groups as groups', ($body['auth'] ?? '') === 'ZBX-TOKEN-1' && $history[0]['request']->getHeaderLine('Authorization') === '' && isset($body['params']['selectGroups']));

    // an error of Zabbix: its message, never the token
    $fake([new Response(200, [], json_encode(['jsonrpc' => '2.0', 'error' => ['code' => -32602, 'message' => 'Invalid params.', 'data' => 'Not authorized.'], 'id' => 1]))]);
    try {
        $zb->changes($zconn, 'hosts', null, 200);
        $check('zabbix: an error stops the run', false);
    } catch (Exceedone\Exment\Services\Sync\SyncException $e) {
        $check('zabbix: an error stops the run, with Zabbix\'s words and no token', strpos($e->getMessage(), 'Not authorized') !== false && strpos($e->getMessage(), 'ZBX-TOKEN') === false);
    }
    $fake([new Response(401, [], '')]);
    $check('mackerel: a refused key says so', strpos(strval($mk->test($conn)), '401') !== false);
} catch (\Throwable $e) {
    // a check that stops the test is a failure, not the end
    $check('the test ran to its end', false, get_class($e) . ': ' . $e->getMessage());
} finally {
    DB::rollBack();
    $Base::$http = null;
}

echo "\n$ok OK, $bad NG\n";
exit($bad === 0 ? 0 : 1);
