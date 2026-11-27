<?php

namespace App\Plugins\ExmentSupportDesk;

use App\Plugins\ExmentSupportDesk\Alerts\EscalationRule;
use Carbon\Carbon;
use Exceedone\Exment\Enums\SystemTableName;
use Exceedone\Exment\Model\CustomTable;
use Exceedone\Exment\Model\CustomValue;
use Illuminate\Support\Facades\Log;

/**
 * The SLA escalation in steps (off at first, the setting "esc_enabled"): before the due time
 * (esc_warn_percent of the time, 75% at first) the assignee is told, at the due time the leads,
 * esc_manager_after minutes later the managers. By the bell and mail, and the Teams workflow,
 * LINE WORKS and a webhook when they are set. Each step once a ticket (the column
 * sla_escalation); a new due time (another priority, the clock stopped and started) starts again.
 *
 * While it is on, the single warning and breach notices of checkSla are not sent: the steps
 * take their place.
 */
class Escalation
{
    const CHANNELS = [
        'bell' => '画面のお知らせ（ベル）',
        'mail' => 'メール',
        'teams' => 'Microsoft Teams（ワークフローの URL）',
        'lineworks' => 'LINE WORKS（Exment の LINE WORKS の設定）',
        'webhook' => 'Webhook（JSON）',
    ];

    const STEP_LABELS = [1 => 'まもなく期限', 2 => '期限を過ぎました', 3 => '期限を過ぎたまま'];

    public static function enabled(): bool
    {
        return Its::setting('esc_enabled', '0') === '1';
    }

    /**
     * @return array<int, string>
     */
    public static function ids(string $key): array
    {
        return array_values(array_filter(array_map('trim', explode(',', strval(Its::setting($key, ''))))));
    }

    /**
     * @return array<int, string>
     */
    public static function channels(): array
    {
        $list = array_values(array_intersect(array_keys(static::CHANNELS), static::ids('esc_channels')));

        return empty($list) ? ['bell', 'mail'] : $list;
    }

    /**
     * The 5-minute batch: every open ticket whose clock runs, the step it reached, told once.
     *
     * @return int the notices sent (steps)
     */
    public static function run(?Carbon $now = null): int
    {
        if (!static::enabled()) {
            return 0;
        }
        $now = $now ?? Carbon::now();
        $sent = 0;
        $percent = intval(Its::setting('esc_warn_percent', '75'));
        $after = intval(Its::setting('esc_manager_after', '60'));
        foreach (Tickets::query()->whereIn('value->status', Its::ACTIVE_STATUSES)->cursor() as $ticket) {
            /** @var CustomValue $ticket */
            $v = (array) $ticket->value;
            if (in_array(strval($v['status'] ?? ''), Its::PAUSED_STATUSES, true)) {
                continue;
            }
            $due = EscalationRule::dueOf($v);
            if (!isset($due)) {
                continue;
            }
            $start = $ticket->created_at ? Carbon::parse($ticket->created_at)->getTimestamp() : $now->getTimestamp();
            $reached = EscalationRule::step($start, Carbon::parse($due)->getTimestamp(), $now->getTimestamp(), $percent, $after);
            $step = EscalationRule::toSend($reached, intval($v['sla_escalation'] ?? 0));
            if (!isset($step)) {
                continue;
            }
            // marked first: a failure of a channel does not tell the same step every 5 minutes
            $ticket->setValue(['sla_escalation' => $step]);
            $ticket->save();
            static::tell($ticket, $step, $due);
            $sent++;
        }

        return $sent;
    }

    public static function tell(CustomValue $ticket, int $step, string $due): void
    {
        $no = strval(array_get($ticket->value, 'ticket_no'));
        $subject = '【SLA ' . (static::STEP_LABELS[$step] ?? '') . "】{$no} " . strval(array_get($ticket->value, 'subject'));
        $body = '期限: ' . $due . "\n段階: {$step}（" . ['', '担当者', 'リーダー', '管理者'][$step] . 'へ）'
            . "\n優先度: " . strval(Its::PRIORITIES[strval(array_get($ticket->value, 'priority'))] ?? array_get($ticket->value, 'priority'));
        $url = Its::url('ticket', strval($ticket->id));
        $ids = EscalationRule::recipients(
            $step,
            Tickets::assigneeIds($ticket),
            static::ids('esc_lead_users'),
            static::ids('esc_manager_users'),
            array_map('strval', Tickets::agentIds())
        );
        $channels = static::channels();

        if (in_array('bell', $channels, true) || in_array('mail', $channels, true)) {
            Automation::notifyUsers($ticket, $ids, $subject, $body, in_array('bell', $channels, true), in_array('mail', $channels, true));
        }
        if (in_array('lineworks', $channels, true)) {
            static::lineWorks($ids, $subject, $body, $url);
        }
        $payload = [
            'event' => 'sla_escalation',
            'step' => $step,
            'ticket_no' => $no,
            'subject' => strval(array_get($ticket->value, 'subject')),
            'priority' => strval(array_get($ticket->value, 'priority')),
            'due' => $due,
            'url' => $url,
        ];
        if (in_array('teams', $channels, true) && Its::setting('esc_teams_url', '') !== '') {
            static::post(strval(Its::setting('esc_teams_url')), ['text' => $subject . "\n\n" . $body . "\n" . $url] + $payload);
        }
        if (in_array('webhook', $channels, true) && Its::setting('esc_webhook_url', '') !== '') {
            static::post(strval(Its::setting('esc_webhook_url')), $payload);
        }
        Tickets::addMessage($ticket, ['kind' => 'system', 'body' => "SLA の知らせ（段階 {$step}）を送りました。", 'via' => 'staff']);
    }

    /**
     * @param array<int, string> $ids
     */
    protected static function lineWorks(array $ids, string $subject, string $body, string $url): void
    {
        $core = '\\Exceedone\\Exment\\Services\\LineWorks\\LineWorks';
        if (!class_exists($core) || !$core::enabled()) {
            return; // Exment before 7.3, or LINE WORKS not set
        }
        try {
            $content = $core::content($subject, $body, $url);
            $channel = trim(strval(Its::setting('esc_lineworks_channel', '')));
            if ($channel !== '') {
                $core::send($core::toChannel($channel), $content);

                return;
            }
            $users = CustomTable::getEloquent(SystemTableName::USER);
            foreach (array_slice($ids, 0, 50) as $id) {
                $user = $users->getValueModel()->query()->withoutGlobalScope(\Exceedone\Exment\Model\CustomValueModelScope::class)->find($id);
                $to = isset($user) ? $core::userIdOf($user) : null;
                if (isset($to) && $to !== '') {
                    $core::send($core::toUser($to), $content);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('ExmentSupportDesk LINE WORKS escalation failed: ' . class_basename($e));
        }
    }

    /**
     * A JSON POST to an address of the settings, never to the server's own network when Exment
     * says so (WebhookUrlGuard, Exment 7.1.2 and later).
     *
     * @param array<string, mixed> $payload
     */
    public static function post(string $url, array $payload): bool
    {
        if (!preg_match('#^https?://#i', $url)) {
            return false;
        }
        $guard = '\\Exceedone\\Exment\\Services\\WebhookUrlGuard';
        if (class_exists($guard) && !$guard::isAllowed($url)) {
            Log::warning('ExmentSupportDesk escalation webhook refused: the address is not allowed.');

            return false;
        }
        try {
            $response = (new \GuzzleHttp\Client())->request('POST', $url, [
                'json' => $payload,
                'connect_timeout' => 5,
                'timeout' => 10,
                'allow_redirects' => false,
                'http_errors' => false,
            ]);

            return $response->getStatusCode() >= 200 && $response->getStatusCode() < 300;
        } catch (\Throwable $e) {
            Log::warning('ExmentSupportDesk escalation webhook failed: ' . class_basename($e));

            return false;
        }
    }
}
