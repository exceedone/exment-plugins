<?php
// ExmentITSupport: how the alerts of each monitoring tool are read, and what
// they do to the tickets (ISS02928). Plain PHP, no Exment needed:
//   php it-support/tools/test-alert-intake.php
// The bodies in fixtures/alerts/ follow the samples and field lists in each
// tool's documentation (Zabbix: the media type in tools/zabbix/).
require __DIR__ . '/../ExmentITSupport/Alerts/Adapters.php';
require __DIR__ . '/../ExmentITSupport/Alerts/Rules.php';

use App\Plugins\ExmentITSupport\Alerts\Adapters;
use App\Plugins\ExmentITSupport\Alerts\Rules;

$ok = 0;
$bad = 0;
$check = function (string $label, bool $cond, $extra = '') use (&$ok, &$bad) {
    echo ($cond ? 'OK  ' : 'NG  '), $label, $cond ? '' : '  ' . (is_string($extra) ? $extra : var_export($extra, true)), "\n";
    $cond ? $ok++ : $bad++;
};
$load = function (string $name): array {
    return json_decode((string) file_get_contents(__DIR__ . '/fixtures/alerts/' . $name . '.json'), true, 512, JSON_THROW_ON_ERROR);
};
$one = function (string $kind, string $name) use ($load): array {
    $events = Adapters::read($kind, $load($name));
    if (count($events) !== 1) {
        throw new RuntimeException("$name: " . count($events) . ' events');
    }

    return $events[0];
};
$keys = ['status', 'title', 'source', 'host', 'ip', 'severity', 'severity_raw', 'fingerprint', 'external_id', 'url', 'detail', 'customer'];

// ---- Zabbix ---------------------------------------------------------------
$p = $one('zabbix', 'zabbix-problem');
$r = $one('zabbix', 'zabbix-recovery');
$check('zabbix: every field', array_keys($p) == $keys || count(array_diff($keys, array_keys($p))) === 0, array_keys($p));
$check('zabbix: problem is firing', $p['status'] === 'firing');
$check('zabbix: recovery is resolved', $r['status'] === 'resolved');
$check('zabbix: same fingerprint for the problem and its recovery', $p['fingerprint'] === $r['fingerprint'] && $p['fingerprint'] === 'zabbix:trigger:23571', $p['fingerprint']);
$check('zabbix: High is error', $p['severity'] === 'error', $p['severity']);
$check('zabbix: host and ip', $p['host'] === 'web01.example.co.jp' && $p['ip'] === '192.0.2.10');
$check('zabbix: title is the subject', strpos($p['title'], 'High CPU utilization') !== false);
$check('zabbix: event id and url kept', $p['external_id'] === '4821' && strpos($p['url'], 'https://zabbix.example.co.jp/') === 0);
$check('zabbix: detail has the message and the value', strpos($p['detail'], 'Problem started') !== false && strpos($p['detail'], '97.1') !== false);
$check('zabbix: 5 is critical, 0 is info', Adapters::zabbixSeverity('5') === 'critical' && Adapters::zabbixSeverity('0') === 'info');
$noTrigger = Adapters::read('zabbix', ['event_id' => '9', 'subject' => 'x'])[0];
$check('zabbix: without a trigger id, the event id', $noTrigger['fingerprint'] === 'zabbix:event:9');
$check('zabbix: an empty body is nothing', Adapters::read('zabbix', []) === []);

// ---- Mackerel -------------------------------------------------------------
$m = $one('mackerel', 'mackerel-critical');
$mo = $one('mackerel', 'mackerel-ok');
$check('mackerel: critical is firing and critical', $m['status'] === 'firing' && $m['severity'] === 'critical');
$check('mackerel: ok (isOpen false) is resolved', $mo['status'] === 'resolved');
$check('mackerel: the same fingerprint (host and monitor)', $m['fingerprint'] === $mo['fingerprint'] && $m['fingerprint'] === 'mackerel:22D4b7L4SVc:loadavg5', $m['fingerprint']);
$check('mackerel: title names the monitor and the host', $m['title'] === 'loadavg5 - app01', $m['title']);
$check('mackerel: host', $m['host'] === 'app01');
$check('mackerel: alert id and url', $m['external_id'] === '2bj7fm3kfyo' && $m['url'] === 'https://mackerel.io/alerts/2bj7fm3kfyo');
$check('mackerel: value and threshold in the detail', strpos($m['detail'], '25.6') !== false && strpos($m['detail'], '20') !== false && strpos($m['detail'], 'メモ:') !== false, $m['detail']);
$again = $load('mackerel-critical');
$again['alert']['id'] = 'newAlertId';
$check('mackerel: a new alert of the same monitor finds the same ticket', Adapters::read('mackerel', $again)[0]['fingerprint'] === $m['fingerprint']);
$x = $one('mackerel', 'mackerel-external');
$check('mackerel: external (no host) is read', $x['status'] === 'firing' && $x['fingerprint'] === 'mackerel:external:https://www.example.co.jp/' && $x['host'] === '', $x);
$check('mackerel: not an alert is nothing', Adapters::read('mackerel', ['event' => 'sample']) === []);
$bad1 = $load('mackerel-critical');
$bad1['service'] = 'not an object';
$check('mackerel: a strange field does not break it', count(Adapters::read('mackerel', $bad1)) === 1);

// ---- Alertmanager ---------------------------------------------------------
$am = Adapters::read('alertmanager', $load('alertmanager-group'));
$check('alertmanager: one event per alert', count($am) === 2);
$check('alertmanager: each its own status', $am[0]['status'] === 'firing' && $am[1]['status'] === 'resolved');
$check('alertmanager: fingerprint', $am[0]['fingerprint'] === 'alertmanager:a4f1c2d3e5b60718');
$check('alertmanager: host from instance (no port)', $am[0]['host'] === 'db01.example.co.jp' && $am[0]['ip'] === '', $am[0]);
$check('alertmanager: ip from instance', $am[1]['host'] === '192.0.2.21' && $am[1]['ip'] === '192.0.2.21', $am[1]);
$check('alertmanager: summary is the title', strpos($am[0]['title'], 'Instance db01.example.co.jp:9100 down') === 0, $am[0]['title']);
$check('alertmanager: critical', $am[0]['severity'] === 'critical');
$check('alertmanager: labels in the detail', strpos($am[0]['detail'], 'job=node') !== false);
$many = ['alerts' => array_fill(0, 80, ['status' => 'firing', 'labels' => ['alertname' => 'A']])];
$check('alertmanager: at most 50 alerts a request', count(Adapters::read('alertmanager', $many)) === 50);
$check('alertmanager: no fingerprint: from the labels, the same each time', Adapters::read('alertmanager', ['alerts' => [['labels' => ['b' => '2', 'a' => '1']]]])[0]['fingerprint'] === Adapters::read('alertmanager', ['alerts' => [['labels' => ['a' => '1', 'b' => '2']]]])[0]['fingerprint']);

// ---- Azure Monitor --------------------------------------------------------
$af = $one('azure', 'azure-fired');
$ar = $one('azure', 'azure-resolved');
$check('azure: Fired and Resolved', $af['status'] === 'firing' && $ar['status'] === 'resolved');
$check('azure: same fingerprint (rule and target)', $af['fingerprint'] === $ar['fingerprint'] && strpos($af['fingerprint'], 'azure:') === 0);
$check('azure: host is the configuration item', $af['host'] === 'wcus-r2-gen2');
$check('azure: Sev3 is info, Sev0 critical', $af['severity'] === 'info' && Adapters::azureSeverity('Sev0') === 'critical');
$check('azure: the metric in the detail', strpos($af['detail'], 'Percentage CPU 31.1105') !== false, $af['detail']);
$check('azure: title', $af['title'] === 'WCUS-R2-Gen2 - wcus-r2-gen2', $af['title']);
$check('azure: not the common schema is nothing', Adapters::read('azure', ['data' => []]) === []);

// ---- generic --------------------------------------------------------------
$g = $one('generic', 'generic');
$check('generic: as before', $g['status'] === 'firing' && $g['title'] === 'Disk full on web01' && $g['severity'] === 'error' && $g['host'] === 'web01.example.co.jp');
$g2 = Adapters::read('generic', ['title' => 'Disk full on web01', 'source' => 'web01.example.co.jp', 'status' => 'resolved'])[0];
$check('generic: status resolved, same fingerprint as the problem', $g2['status'] === 'resolved' && $g2['fingerprint'] === $g['fingerprint']);
$check('generic: the fingerprint ignores numbers', Adapters::read('generic', ['title' => 'Disk 97% on web01', 'source' => 's'])[0]['fingerprint'] === Adapters::read('generic', ['title' => 'Disk 98% on web01', 'source' => 's'])[0]['fingerprint']);
$check('generic: no title is nothing', Adapters::read('generic', ['source' => 'x']) === []);
$check('generic: only http(s) links', Adapters::read('generic', ['title' => 't', 'url' => 'javascript:alert(1)'])[0]['url'] === '');
$check('generic: a long title is cut', mb_strlen(Adapters::read('generic', ['title' => str_repeat('あ', 400)])[0]['title']) === 250);
$check('generic: a list where a string is expected', Adapters::read('generic', ['title' => ['x'], 'message' => 'm'])[0]['title'] === 'm' && Adapters::read('generic', ['title' => ['x']]) === []);
$check('hostOf', Adapters::hostOf('https://User@WWW.Example.jp:8443/x') === 'www.example.jp' && Adapters::hostOf('[2001:db8::1]:9100') === '2001:db8::1' && Adapters::hostOf('2001:db8::1') === '2001:db8::1');

// ---- what an alert does (Rules::decide) -----------------------------------
$now = 1800000000;
$check('firing, a ticket open: count', Rules::decide('firing', 'open', null, 'note', 30, $now) === 'count');
$check('firing, nothing: create', Rules::decide('firing', null, null, 'note', 30, $now) === 'create');
$check('firing 10 min after the recovery: reopen (flapping)', Rules::decide('firing', null, $now - 600, 'note', 30, $now) === 'reopen');
$check('firing 40 min after: a new ticket', Rules::decide('firing', null, $now - 2400, 'note', 30, $now) === 'create');
$check('flapping off (0): a new ticket', Rules::decide('firing', null, $now - 60, 'note', 0, $now) === 'create');
$check('resolved, a ticket open: the setting', Rules::decide('resolved', 'open', null, 'resolve', 30, $now) === 'resolve' && Rules::decide('resolved', 'pending', null, 'close', 30, $now) === 'close' && Rules::decide('resolved', 'new', null, 'note', 30, $now) === 'note');
$check('resolved, an unknown setting: only a note', Rules::decide('resolved', 'open', null, 'drop_table', 30, $now) === 'note');
$check('resolved, nothing open: ignore', Rules::decide('resolved', null, $now - 10, 'resolve', 30, $now) === 'ignore');

// ---- signature ------------------------------------------------------------
$body = '{"title":"x"}';
$sig = Rules::sign('s3cret', (string) $now, $body);
$check('signature: right', Rules::verify('s3cret', (string) $now, $body, $sig, $now));
$check('signature: hex without the prefix', Rules::verify('s3cret', (string) $now, $body, substr($sig, 7), $now));
$check('signature: other body', !Rules::verify('s3cret', (string) $now, $body . ' ', $sig, $now));
$check('signature: other secret', !Rules::verify('other', (string) $now, $body, $sig, $now));
$check('signature: too old', !Rules::verify('s3cret', (string) ($now - 301), $body, Rules::sign('s3cret', (string) ($now - 301), $body), $now));
$check('signature: none', !Rules::verify('s3cret', (string) $now, $body, '', $now) && !Rules::verify('', (string) $now, $body, $sig, $now));
$check('signature: the form the Zabbix script makes', Rules::sign('k', '1700000000', 'b') === 'sha256=' . hash_hmac('sha256', '1700000000.b', 'k'));

// ---- IT asset -------------------------------------------------------------
$check('asset: the same name', Rules::assetMatches('web01.example.co.jp', '', 'web01.example.co.jp', ''));
$check('asset: the first label', Rules::assetMatches('WEB01', '', 'web01.example.co.jp', ''));
$check('asset: the IP in its place', Rules::assetMatches('db', '本社 3F ラック / 192.0.2.21', '192.0.2.21', '192.0.2.21'));
$check('asset: a longer IP is not the IP', !Rules::assetMatches('db', '192.0.2.210', '', '192.0.2.21'));
$check('asset: another name', !Rules::assetMatches('web02', '', 'web01.example.co.jp', ''));
$check('asset: no host, no IP', !Rules::assetMatches('web01', '192.0.2.1', '', ''));

// ---- addresses ------------------------------------------------------------
$check('ip: empty list allows all', Rules::ipAllowed('', '203.0.113.5'));
$check('ip: one address', Rules::ipAllowed("192.0.2.10\n", '192.0.2.10') && !Rules::ipAllowed('192.0.2.10', '192.0.2.11'));
$check('ip: a range', Rules::ipAllowed('192.0.2.0/24', '192.0.2.200') && !Rules::ipAllowed('192.0.2.0/25', '192.0.2.200'));
$check('ip: v6', Rules::ipAllowed('2001:db8::/32', '2001:db8:1::5') && !Rules::ipAllowed('2001:db8::/32', '192.0.2.1'));
$check('ip: a broken line allows nothing', !Rules::ipAllowed('not-an-ip', '192.0.2.1') && !Rules::ipAllowed('192.0.2.0/99', '192.0.2.1'));

echo "\n$ok OK, $bad NG\n";
exit($bad === 0 ? 0 : 1);
