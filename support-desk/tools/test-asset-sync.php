<?php
// ExmentITSupport: the hosts of monitoring tools read into IT assets, and which asset a host is
// (ISS02928, phase 3). Plain PHP:
//   php it-support/tools/test-asset-sync.php
// The answers in fixtures/hosts/ follow the documented answers of Mackerel's GET /api/v0/hosts
// and Zabbix's host.get (addresses of example.invalid and the documentation ranges).
require __DIR__ . '/../ExmentITSupport/Assets/HostMapper.php';
require __DIR__ . '/../ExmentITSupport/Assets/AssetMatcher.php';

use App\Plugins\ExmentITSupport\Assets\AssetMatcher as M;
use App\Plugins\ExmentITSupport\Assets\HostMapper as H;

$ok = 0;
$bad = 0;
$check = function (string $label, bool $cond, $extra = '') use (&$ok, &$bad) {
    echo ($cond ? 'OK  ' : 'NG  '), $label, $cond ? '' : '  ' . var_export($extra, true), "\n";
    $cond ? $ok++ : $bad++;
};
$load = function (string $name): array {
    return json_decode((string) file_get_contents(__DIR__ . '/fixtures/hosts/' . $name . '.json'), true, 512, JSON_THROW_ON_ERROR);
};

// ---- Mackerel ---------------------------------------------------------------
$mk = array_map(function ($h) {
    return H::fromMackerel($h, 'example');
}, $load('mackerel-hosts')['hosts']);
$w = $mk[0];
$check('mackerel: id, display name, FQDN', $w['external_id'] === '2eQGEaLxibb' && $w['name'] === 'web01' && $w['hostname'] === 'web01.example.invalid', $w);
$check('mackerel: addresses without loopback, no twice', $w['ips'] === ['192.0.2.10'], $w['ips']);
$check('mackerel: os from the platform', $w['os'] === 'Ubuntu 22.04', $w['os']);
$check('mackerel: the cpu as the model', strpos($w['maker_model'], 'Xeon') !== false);
$check('mackerel: services and roles as groups', $w['groups'] === ['Shop', 'Shop:web', 'Shop:app'], $w['groups']);
$check('mackerel: the page of the host', $w['url'] === 'https://mackerel.io/orgs/example/hosts/2eQGEaLxibb');
$check('mackerel: no display name: the name', $mk[1]['name'] === 'db01.example.invalid' && $mk[1]['os'] === 'Linux 6.1.0');
$check('mackerel: retired', $mk[2]['retired'] === true && $mk[0]['retired'] === false && $mk[1]['retired'] === false);
$check('mackerel: a strange answer does not break it', H::fromMackerel(['id' => 'x', 'meta' => 'no', 'interfaces' => 'no', 'roles' => ['a' => 'b']])['external_id'] === 'x');

// ---- Zabbix -----------------------------------------------------------------
$zb = array_map(function ($h) {
    return H::fromZabbix($h, 'https://zabbix.example.invalid/');
}, $load('zabbix-host-get')['result']);
$fs = $zb[1];
$check('zabbix: id, visible name, FQDN from the interface', $fs['external_id'] === '10501' && $fs['name'] === 'ファイルサーバー（本社）' && $fs['hostname'] === 'fs01.example.invalid', $fs);
$check('zabbix: every address', $fs['ips'] === ['192.0.2.30', '198.51.100.30'], $fs['ips']);
$check('zabbix: the inventory', $fs['os'] === 'Windows Server 2022 Standard' && $fs['maker_model'] === 'Dell PowerEdge R650' && $fs['serial'] === 'ABC1234' && $fs['asset_tag'] === 'IT-0042', $fs);
$check('zabbix: host groups', $fs['groups'] === ['本社', 'Windows servers']);
$check('zabbix: the page of the host', $fs['url'] === 'https://zabbix.example.invalid/zabbix.php?action=host.edit&hostid=10501');
$check('zabbix: an empty inventory ([]) and only loopback', $zb[0]['os'] === '' && $zb[0]['ips'] === []);
$check('zabbix: a disabled host is retired', $zb[2]['retired'] === true && $fs['retired'] === false);
$check('zabbix: the hardware when no vendor or model', $zb[2]['maker_model'] === 'MFP');
$check('zabbix: groups of 6.0 ("groups")', H::fromZabbix(['hostid' => '1', 'groups' => [['name' => 'old']]])['groups'] === ['old']);
$check('zabbix: no url from a strange setting', H::fromZabbix(['hostid' => '1'], 'javascript:alert(1)')['url'] === '');

// ---- the customer -----------------------------------------------------------
$check('customer: the first group in the table', H::customerCode(['Windows servers', '本社'], "本社=C001; windows servers=C009") === 'C009');
$check('customer: by lines too', H::customerCode(['Shop:web'], "Other=C1\nShop:web=C2") === 'C2');
$check('customer: none', H::customerCode(['x'], 'a=b') === null && H::customerCode(['x'], '') === null);

// ---- which asset ------------------------------------------------------------
$src = 'zabbix:3';
$assets = [
    ['id' => 1, 'name' => 'fs01', 'location' => '本社 2F', 'serial' => '', 'asset_tag' => ''],
    ['id' => 2, 'name' => 'nas', 'location' => '192.0.2.30', 'serial' => 'abc 1234'],
    ['id' => 3, 'name' => 'other', 'asset_tag' => 'IT-0042'],
];
$r = M::match($fs, $src, $assets);
$check('the serial first (case and spaces aside)', $r['id'] === 2 && $r['by'] === 'serial', $r);
$r = M::match(array_merge($fs, ['serial' => '']), $src, $assets);
$check('then the asset tag', $r['id'] === 3 && $r['by'] === 'asset_tag', $r);
$r = M::match(array_merge($fs, ['serial' => '', 'asset_tag' => '']), $src, $assets);
$check('then the name (the first label of the FQDN)', $r['id'] === 1 && $r['by'] === 'name', $r);
$r = M::match(['external_id' => '9', 'name' => 'x', 'hostname' => '', 'ips' => ['192.0.2.30']], $src, $assets);
$check('then an address', $r['id'] === 2 && $r['by'] === 'ip', $r);
$r = M::match(['external_id' => '9', 'name' => 'x', 'hostname' => '', 'ips' => ['192.0.2.3']], $src, $assets);
$check('a shorter address is not the address', $r['id'] === null && $r['ambiguous'] === []);
$r = M::match($fs, $src, array_merge($assets, [['id' => 7, 'source' => $src, 'external_id' => '10501', 'name' => 'renamed']]));
$check('linked before: by the id, before anything else', $r['id'] === 7 && $r['by'] === 'external_id', $r);
$r = M::match(array_merge($fs, ['serial' => '', 'asset_tag' => '']), $src, [['id' => 1, 'name' => 'fs01'], ['id' => 5, 'name' => 'FS01']]);
$check('two that match: none linked, both named', $r['id'] === null && $r['ambiguous'] === [1, 5], $r);
$r = M::match(array_merge($fs, ['serial' => '', 'asset_tag' => '']), $src, [['id' => 1, 'name' => 'fs01', 'source' => $src, 'external_id' => '999']]);
$check('an asset of another host of the same tool is not taken', $r['id'] === null, $r);
$r = M::match(array_merge($fs, ['serial' => '', 'asset_tag' => '']), $src, [['id' => 1, 'name' => 'fs01', 'source' => 'mackerel:1', 'external_id' => 'abc']]);
$check('one of another tool is (the same machine in two tools)', $r['id'] === 1, $r);

// ---- what is written --------------------------------------------------------
$c = M::changes($fs, $src, ['name' => 'fs01', 'customer' => '5', 'note' => 'mine', 'os' => 'old'], false);
$check('the tool\'s fields', ($c['os'] ?? '') === 'Windows Server 2022 Standard' && ($c['serial'] ?? '') === 'ABC1234' && ($c['source'] ?? '') === $src && ($c['external_id'] ?? '') === '10501' && ($c['ip_addresses'] ?? '') === '192.0.2.30, 198.51.100.30' && ($c['sync_state'] ?? '') === 'active', $c);
$check('never the people\'s fields, nor the name of one there', !isset($c['customer']) && !isset($c['note']) && !isset($c['name']) && !isset($c['kind']) && !isset($c['asset_tag']), $c);
$same = array_merge(['name' => 'x'], M::changes($fs, $src, [], false));
$check('nothing when nothing changed', M::changes($fs, $src, $same, false) === []);
$check('an empty field of the tool does not empty the asset\'s', !isset(M::changes(array_merge($fs, ['os' => '']), $src, ['os' => 'mine'], false)['os']));
$n = M::changes($fs, $src, [], true);
$check('a new asset: its name, kind and tag', $n['name'] === 'ファイルサーバー（本社）' && $n['kind'] === 'server' && $n['asset_tag'] === 'IT-0042', $n);
$check('retired by the tool', M::changes($zb[2], $src, [], false)['sync_state'] === 'retired');

echo "\n$ok OK, $bad NG\n";
exit($bad === 0 ? 0 : 1);
