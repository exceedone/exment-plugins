<?php

namespace App\Plugins\ExmentSupportDesk\Alerts;

/**
 * What each monitoring tool sends, read into one shape (ISS02928).
 *
 * Plain PHP on purpose (no Laravel, no Exment): the tools' sample payloads
 * are checked by support-desk/tools/test-alert-intake.php in the CI.
 *
 * One event:
 *   status       firing | resolved
 *   title        the ticket's subject
 *   source       what the alert is about, as the tool names it (a host, a URL, a resource)
 *   host, ip     to find the IT asset ('' when the tool does not say)
 *   severity     critical | error | warning | info | ''
 *   fingerprint  the same problem gets the same one, firing and resolved alike,
 *                and again when it comes back (so a flapping alert finds its ticket)
 *   external_id  the tool's id of this one alert (shown, not matched)
 *   url          the alert's page in the tool
 *   detail       the text of the ticket
 *   customer     a customer code when the sender gives one
 *
 * Unknown fields are ignored: the tools add fields at any time.
 */
class Adapters
{
    const KINDS = [
        'generic' => '汎用（JSON）',
        'zabbix' => 'Zabbix',
        'mackerel' => 'Mackerel',
        'alertmanager' => 'Prometheus Alertmanager',
        'azure' => 'Azure Monitor（共通アラートスキーマ）',
    ];

    /** At most this many alerts are taken from one request (Alertmanager sends groups). */
    const MAX_EVENTS = 50;

    const RESOLVED_WORDS = ['resolved', 'resolve', 'ok', 'recovered', 'recovery', 'closed', 'close', 'up', 'normal', '0'];

    /**
     * @param array<mixed> $payload the decoded JSON
     * @return array<int, array<string, string>>
     */
    public static function read(string $kind, array $payload): array
    {
        switch ($kind) {
            case 'zabbix':
                $events = [static::zabbix($payload)];
                break;
            case 'mackerel':
                $events = [static::mackerel($payload)];
                break;
            case 'alertmanager':
                $events = static::alertmanager($payload);
                break;
            case 'azure':
                $events = [static::azure($payload)];
                break;
            default:
                $events = [static::generic($payload)];
        }

        $events = array_values(array_filter($events));

        return array_slice(array_map([static::class, 'finish'], $events), 0, static::MAX_EVENTS);
    }

    /**
     * The fields this plugin has always taken, plus "status" (resolved / ok
     * / recovered...) and "ip".
     *
     * @param array<mixed> $p
     * @param bool $withStatus false for the old shared key: its alerts were never resolved by a field
     * @return array<string, string>|null
     */
    public static function generic(array $p, bool $withStatus = true): ?array
    {
        $title = static::str($p['title'] ?? '') ?: static::str($p['message'] ?? '');
        if ($title === '') {
            return null;
        }
        $source = static::str($p['source'] ?? '') ?: static::str($p['host'] ?? '');
        $status = $withStatus ? static::status(static::str($p['status'] ?? $p['state'] ?? '')) : 'firing';

        return [
            'status' => $status,
            'title' => $title,
            'source' => $source,
            'host' => static::hostOf($source),
            'ip' => static::str($p['ip'] ?? ''),
            'severity' => static::severityWord(static::str($p['severity'] ?? $p['level'] ?? '')),
            'severity_raw' => static::str($p['severity'] ?? $p['level'] ?? ''),
            'fingerprint' => static::str($p['fingerprint'] ?? ''),
            'external_id' => static::str($p['id'] ?? ''),
            'url' => static::str($p['url'] ?? ''),
            'detail' => static::str($p['detail'] ?? $p['body'] ?? ''),
            'customer' => static::str($p['customer'] ?? $p['customer_code'] ?? ''),
        ];
    }

    /**
     * The body the media type of support-desk/tools/zabbix/ sends (Zabbix 6.0
     * and later). {EVENT.VALUE} is 1 for a problem and 0 for its recovery;
     * the trigger id names the problem, so its recovery and a later return
     * find the same ticket.
     *
     * @param array<mixed> $p
     * @return array<string, string>|null
     */
    public static function zabbix(array $p): ?array
    {
        $value = static::str($p['event_value'] ?? '1');
        $host = static::str($p['host'] ?? $p['host_name'] ?? '');
        $trigger = static::str($p['trigger_id'] ?? '');
        $event = static::str($p['event_id'] ?? '');
        $title = static::str($p['subject'] ?? $p['event_name'] ?? $p['trigger_name'] ?? '');
        if ($title === '' && $trigger === '' && $event === '') {
            return null;
        }
        $severity = static::str($p['event_nseverity'] ?? $p['severity'] ?? '');
        $lines = array_filter([
            static::str($p['message'] ?? ''),
            $host !== '' ? 'ホスト: ' . $host . (static::str($p['host_ip'] ?? '') !== '' ? ' (' . static::str($p['host_ip']) . ')' : '') : '',
            static::str($p['event_severity'] ?? '') !== '' ? '重要度: ' . static::str($p['event_severity']) : '',
            static::str($p['event_tags'] ?? '') !== '' ? 'タグ: ' . static::str($p['event_tags']) : '',
            static::str($p['event_opdata'] ?? '') !== '' ? '値: ' . static::str($p['event_opdata']) : '',
        ]);

        return [
            'status' => $value === '0' || static::status(static::str($p['event_status'] ?? '')) === 'resolved' ? 'resolved' : 'firing',
            'title' => $title !== '' ? $title : 'Zabbix の問題 ' . ($event ?: $trigger),
            'source' => $host,
            'host' => $host,
            'ip' => static::str($p['host_ip'] ?? ''),
            'severity' => static::zabbixSeverity($severity),
            'severity_raw' => static::str($p['event_severity'] ?? $severity),
            'fingerprint' => 'zabbix:' . ($trigger !== '' ? 'trigger:' . $trigger : 'event:' . $event),
            'external_id' => $event,
            'url' => static::str($p['event_url'] ?? ''),
            'detail' => implode("\n", $lines),
            'customer' => static::str($p['customer'] ?? ''),
        ];
    }

    /**
     * Mackerel's webhook (event "alert"). The alert id changes each time the
     * alert opens again and the payload names no monitor id, so the problem
     * is the host (or the alert's type) with the monitor's name. "ok" or
     * isOpen false is the recovery.
     *
     * @param array<mixed> $p
     * @return array<string, string>|null
     */
    public static function mackerel(array $p): ?array
    {
        if (static::str($p['event'] ?? 'alert') !== 'alert' || !is_array($p['alert'] ?? null)) {
            return null; // a test message, or an event that is not an alert
        }
        $alert = $p['alert'];
        $host = is_array($p['host'] ?? null) ? $p['host'] : [];
        $hostName = static::str($host['name'] ?? '');
        $monitor = static::str($alert['monitorName'] ?? '');
        $status = strtolower(static::str($alert['status'] ?? ''));
        $open = $alert['isOpen'] ?? null;
        $resolved = $status === 'ok' || $open === false;
        $subject = static::str($p['service']['name'] ?? '') ?: static::str($p['type'] ?? '');
        $lines = array_filter([
            static::str($p['message'] ?? ''),
            $hostName !== '' ? 'ホスト: ' . $hostName : '',
            static::str($alert['metricLabel'] ?? '') !== '' || isset($alert['metricValue'])
                ? '値: ' . static::str($alert['metricLabel'] ?? '') . ' ' . static::str($alert['metricValue'] ?? '')
                    . (isset($alert['criticalThreshold']) ? '（危険 ' . static::str($alert['monitorOperator'] ?? '') . ' ' . static::str($alert['criticalThreshold']) . '）' : '')
                : '',
            static::str($p['memo'] ?? '') !== '' ? 'メモ: ' . static::str($p['memo']) : '',
        ]);

        return [
            'status' => $resolved ? 'resolved' : 'firing',
            'title' => ($monitor !== '' ? $monitor : 'Mackerel のアラート') . ($hostName !== '' ? ' - ' . $hostName : ''),
            'source' => $hostName !== '' ? $hostName : $subject,
            'host' => $hostName,
            'ip' => '',
            'severity' => $resolved ? '' : static::severityWord($status),
            'severity_raw' => $status,
            'fingerprint' => 'mackerel:' . (static::str($host['id'] ?? '') ?: static::str($p['type'] ?? 'alert')) . ':' . ($monitor !== '' ? $monitor : static::str($alert['id'] ?? '')),
            'external_id' => static::str($alert['id'] ?? ''),
            'url' => static::str($alert['url'] ?? ''),
            'detail' => implode("\n", $lines),
            'customer' => '',
        ];
    }

    /**
     * Alertmanager's webhook (version 4): one request carries a group of
     * alerts, each with its own status and the fingerprint of its labels
     * (the same for the alert's whole life and when it fires again).
     *
     * @param array<mixed> $p
     * @return array<int, array<string, string>|null>
     */
    public static function alertmanager(array $p): array
    {
        $alerts = is_array($p['alerts'] ?? null) ? $p['alerts'] : [];
        $common = is_array($p['commonLabels'] ?? null) ? $p['commonLabels'] : [];
        $events = [];
        foreach (array_slice($alerts, 0, static::MAX_EVENTS) as $a) {
            if (!is_array($a)) {
                continue;
            }
            $labels = is_array($a['labels'] ?? null) ? $a['labels'] : $common;
            $notes = is_array($a['annotations'] ?? null) ? $a['annotations'] : [];
            $name = static::str($labels['alertname'] ?? '');
            $instance = static::str($labels['instance'] ?? '');
            $host = static::str($labels['host'] ?? $labels['hostname'] ?? $labels['nodename'] ?? '') ?: static::hostOf($instance);
            $fingerprint = static::str($a['fingerprint'] ?? '');
            if ($fingerprint === '') {
                $sorted = $labels;
                ksort($sorted);
                $fingerprint = substr(hash('sha256', (string) json_encode($sorted)), 0, 16);
            }
            $title = static::str($notes['summary'] ?? '') ?: ($name !== '' ? $name : 'Alertmanager のアラート');
            $labelText = [];
            foreach ($labels as $k => $v) {
                $labelText[] = $k . '=' . static::str($v);
            }
            $lines = array_filter([
                static::str($notes['description'] ?? ''),
                $instance !== '' ? 'instance: ' . $instance : '',
                !empty($labelText) ? 'labels: ' . implode(', ', $labelText) : '',
                static::str($a['startsAt'] ?? '') !== '' ? '開始: ' . static::str($a['startsAt']) : '',
            ]);
            $events[] = [
                'status' => static::status(static::str($a['status'] ?? $p['status'] ?? 'firing')),
                'title' => $title . ($host !== '' && strpos($title, $host) === false ? ' - ' . $host : ''),
                'source' => $instance !== '' ? $instance : $host,
                'host' => $host,
                'ip' => static::isIp(static::hostOf($instance)) ? static::hostOf($instance) : '',
                'severity' => static::severityWord(static::str($labels['severity'] ?? '')),
                'severity_raw' => static::str($labels['severity'] ?? ''),
                'fingerprint' => 'alertmanager:' . $fingerprint,
                'external_id' => $fingerprint,
                'url' => static::str($a['generatorURL'] ?? $p['externalURL'] ?? ''),
                'detail' => implode("\n", $lines),
                'customer' => static::str($labels['customer'] ?? ''),
            ];
        }

        return $events;
    }

    /**
     * Azure Monitor with the common alert schema: monitorCondition Fired or
     * Resolved. The problem is the alert rule on its target resources.
     *
     * @param array<mixed> $p
     * @return array<string, string>|null
     */
    public static function azure(array $p): ?array
    {
        $e = $p['data']['essentials'] ?? null;
        if (!is_array($e)) {
            return null;
        }
        $rule = static::str($e['alertRule'] ?? '');
        $items = array_values(array_filter(array_map([static::class, 'str'], is_array($e['configurationItems'] ?? null) ? $e['configurationItems'] : [])));
        $targets = array_values(array_filter(array_map([static::class, 'str'], is_array($e['alertTargetIDs'] ?? null) ? $e['alertTargetIDs'] : [])));
        $host = $items[0] ?? '';
        if ($host === '' && isset($targets[0])) {
            $parts = explode('/', rtrim($targets[0], '/'));
            $host = (string) end($parts);
        }
        $key = strtolower(($rule !== '' ? $rule : static::str($e['alertRuleID'] ?? $e['alertId'] ?? '')) . '|' . implode(',', $targets ?: $items));
        $context = $p['data']['alertContext'] ?? [];
        $metrics = [];
        foreach ((array) ($context['condition']['allOf'] ?? []) as $c) {
            if (is_array($c) && isset($c['metricName'])) {
                $metrics[] = static::str($c['metricName']) . ' ' . static::str($c['metricValue'] ?? '') . '（' . static::str($c['operator'] ?? '') . ' ' . static::str($c['threshold'] ?? '') . '）';
            }
        }
        $lines = array_filter([
            static::str($e['description'] ?? ''),
            !empty($items) ? '対象: ' . implode(', ', $items) : '',
            static::str($e['signalType'] ?? '') !== '' ? '種類: ' . static::str($e['signalType']) . ' / ' . static::str($e['monitoringService'] ?? '') : '',
            !empty($metrics) ? '値: ' . implode('、', $metrics) : '',
            static::str($e['firedDateTime'] ?? '') !== '' ? '発生: ' . static::str($e['firedDateTime']) : '',
        ]);

        return [
            'status' => strtolower(static::str($e['monitorCondition'] ?? 'Fired')) === 'resolved' ? 'resolved' : 'firing',
            'title' => ($rule !== '' ? $rule : 'Azure Monitor のアラート') . ($host !== '' ? ' - ' . $host : ''),
            'source' => $host,
            'host' => $host,
            'ip' => '',
            'severity' => static::azureSeverity(static::str($e['severity'] ?? '')),
            'severity_raw' => static::str($e['severity'] ?? ''),
            'fingerprint' => 'azure:' . substr(hash('sha256', $key), 0, 32),
            'external_id' => static::str($e['alertId'] ?? ''),
            'url' => '',
            'detail' => implode("\n", $lines),
            'customer' => '',
        ];
    }

    // ---- helpers -----------------------------------------------------------

    /**
     * @param array<string, string> $e
     * @return array<string, string>
     */
    protected static function finish(array $e): array
    {
        $e['title'] = mb_substr(trim($e['title']), 0, 250);
        $e['source'] = mb_substr(trim($e['source']), 0, 200);
        $e['host'] = mb_substr(strtolower(trim($e['host'])), 0, 200);
        $e['ip'] = static::isIp(trim($e['ip'])) ? trim($e['ip']) : '';
        if ($e['ip'] === '' && static::isIp($e['host'])) {
            $e['ip'] = $e['host'];
        }
        $e['detail'] = mb_substr($e['detail'], 0, 20000);
        if (trim($e['fingerprint']) === '') {
            $normal = preg_replace('/\d+/', '#', $e['source'] . '|' . $e['title']) ?? '';
            $e['fingerprint'] = substr(hash('sha256', $normal), 0, 40);
        }
        $e['fingerprint'] = mb_substr(trim($e['fingerprint']), 0, 100);
        foreach (['external_id', 'url', 'customer', 'severity_raw'] as $k) {
            $e[$k] = mb_substr(trim($e[$k] ?? ''), 0, $k === 'url' ? 500 : 100);
        }
        if ($e['url'] !== '' && preg_match('#^https?://#i', $e['url']) !== 1) {
            $e['url'] = ''; // shown as a link: only http(s)
        }

        return $e;
    }

    public static function status(string $word): string
    {
        return in_array(strtolower(trim($word)), static::RESOLVED_WORDS, true) ? 'resolved' : 'firing';
    }

    /** critical | error | warning | info | '' from the words tools use. */
    public static function severityWord(string $word): string
    {
        $w = strtolower(trim($word));
        if (in_array($w, ['critical', 'crit', 'emergency', 'emerg', 'alert', 'fatal', 'disaster', 'page'], true)) {
            return 'critical';
        }
        if (in_array($w, ['error', 'err', 'high', 'major'], true)) {
            return 'error';
        }
        if (in_array($w, ['warning', 'warn', 'average', 'minor', 'unknown'], true)) {
            return 'warning';
        }
        if (in_array($w, ['info', 'information', 'informational', 'notice', 'low', 'debug', 'none', 'not classified'], true)) {
            return 'info';
        }

        return '';
    }

    /** {EVENT.NSEVERITY}: 0 not classified ... 5 disaster (or the name). */
    public static function zabbixSeverity(string $value): string
    {
        $map = ['5' => 'critical', '4' => 'error', '3' => 'warning', '2' => 'warning', '1' => 'info', '0' => 'info'];

        return $map[trim($value)] ?? static::severityWord($value);
    }

    /** Sev0 critical, Sev1 error, Sev2 warning, Sev3 and Sev4 information. */
    public static function azureSeverity(string $value): string
    {
        $map = ['sev0' => 'critical', 'sev1' => 'error', 'sev2' => 'warning', 'sev3' => 'info', 'sev4' => 'info'];

        return $map[strtolower(trim($value))] ?? '';
    }

    /** The host of "web01:9100", "https://www.example.jp/x", "[::1]:80". */
    public static function hostOf(string $value): string
    {
        $v = strtolower(trim($value));
        if ($v === '') {
            return '';
        }
        $v = preg_replace('#^[a-z][a-z0-9+.-]*://#', '', $v) ?? $v;
        $v = explode('/', $v)[0];
        $v = preg_replace('/^[^@]*@/', '', $v) ?? $v;
        if (preg_match('/^\[([0-9a-f:.]+)\](:\d+)?$/', $v, $m)) {
            return $m[1];
        }
        if (substr_count($v, ':') === 1) {
            $v = explode(':', $v)[0];
        }

        return $v;
    }

    public static function isIp(string $value): bool
    {
        return $value !== '' && filter_var($value, FILTER_VALIDATE_IP) !== false;
    }

    /** @param mixed $value */
    public static function str($value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        if (is_string($value)) {
            return trim($value);
        }

        return '';
    }
}
