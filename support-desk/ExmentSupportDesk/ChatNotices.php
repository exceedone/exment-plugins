<?php

namespace App\Plugins\ExmentSupportDesk;

/**
 * What happens to the tickets, told to a chat (ISS02941 step 3): a new ticket, the customer
 * writing back, the stage moving on. To Microsoft Teams (a workflow's URL), Slack (an incoming
 * webhook) or a Webhook of JSON, for the boxes chosen; off at first (settings 「チャットへの
 * 知らせ」).
 *
 * The same idea as ExmentPM's channels (#147), kept here: the plugins do not share code.
 * The sending goes through Escalation::post (https only, Exment's WebhookUrlGuard).
 * compose(), wants() and slackText() need no database: tools/test-chat-notices.php.
 */
class ChatNotices
{
    const EVENTS = [
        'new' => '新しいチケット',
        'customer' => 'お客様からの返事',
        'stage' => '段階が進んだ・終わった',
    ];

    const TARGETS = ['chat_teams_url' => 'teams', 'chat_slack_url' => 'slack', 'chat_webhook_url' => 'webhook'];

    /** @var array<int, array{event: string, ticket: \Exceedone\Exment\Model\CustomValue, data: array}> */
    protected static $pending = [];

    protected static $hooked = false;

    protected static $customerWriting = false;

    /** While the customer's reply saves the ticket (its stage moves, the reply is told). */
    public static function customerWriting(?bool $on = null): bool
    {
        if ($on !== null) {
            static::$customerWriting = $on;
        }

        return static::$customerWriting;
    }

    public static function enabled(): bool
    {
        return Its::setting('chat_enabled', '0') === '1';
    }

    /** Whether an event of a box is told, by the lists of the settings ('' boxes: all). */
    public static function wants(string $events, string $boxes, string $event, string $boxId): bool
    {
        $e = array_filter(array_map('trim', explode(',', $events)));
        if (!in_array($event, $e, true)) {
            return false;
        }
        $b = array_filter(array_map('trim', explode(',', $boxes)));

        return empty($b) || in_array($boxId === '' ? '0' : $boxId, $b, true);
    }

    /**
     * The lines of a message.
     *
     * @param array{no: string, subject: string, box: string, url: string} $t
     * @param array<string, mixed> $data from / to / progress for a stage, excerpt for a reply
     * @return array{title: string, lines: array<int, string>}
     */
    public static function compose(string $event, array $t, array $data = []): array
    {
        $head = ($t['box'] !== '' ? '【' . $t['box'] . '】' : '') . $t['no'] . ' ' . $t['subject'];
        $lines = [];
        switch ($event) {
            case 'new':
                $title = '新しいチケット';
                if (!empty($data['from'])) {
                    $lines[] = '依頼した人: ' . $data['from'];
                }
                break;
            case 'customer':
                $title = 'お客様から返事がありました';
                if (!empty($data['excerpt'])) {
                    $lines[] = static::excerpt(strval($data['excerpt']));
                }
                break;
            default:
                $title = '段階: ' . (strval($data['from'] ?? '') !== '' ? $data['from'] . ' → ' : '') . strval($data['to'] ?? '') . '（' . intval($data['progress'] ?? 0) . '%）';
        }
        $lines[] = $t['url'];

        return ['title' => $title . '｜' . $head, 'lines' => $lines];
    }

    public static function excerpt(string $s, int $max = 200): string
    {
        $s = trim(preg_replace('/\s+/u', ' ', $s) ?? '');

        return mb_strlen($s) > $max ? mb_substr($s, 0, $max) . '…' : $s;
    }

    /** Slack's own marks (&, <, >) as text, so a subject cannot ping or link. */
    public static function slackText(array $m): string
    {
        $esc = function ($s) {
            return str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], strval($s));
        };
        $lines = $m['lines'];
        $url = array_pop($lines);

        return '*' . $esc($m['title']) . "*\n" . implode("\n", array_map($esc, $lines)) . (count($lines) ? "\n" : '') . '<' . $esc($url) . '|開く>';
    }

    /** @return array<string, mixed> */
    public static function payload(string $target, string $event, array $m, array $t): array
    {
        if ($target === 'slack') {
            return ['text' => static::slackText($m)];
        }
        if ($target === 'teams') {
            return ['text' => $m['title'] . "\n\n" . implode("\n", $m['lines'])];
        }

        return ['event' => 'ticket_' . $event, 'ticket_no' => $t['no'], 'subject' => $t['subject'], 'box' => $t['box'], 'title' => $m['title'], 'lines' => $m['lines'], 'url' => $t['url']];
    }

    // ---- from the saves ------------------------------------------------------

    /** Note an event of a ticket; sent once the request (or the batch) is done. */
    public static function note(\Exceedone\Exment\Model\CustomValue $ticket, string $event, array $data = []): void
    {
        if (!static::enabled() || !isset(static::EVENTS[$event])) {
            return;
        }
        if (!static::wants(strval(Its::setting('chat_events', '')), strval(Its::setting('chat_boxes', '')), $event, strval(array_get($ticket->value, 'category') ?? ''))) {
            return;
        }
        static::$pending[] = ['event' => $event, 'ticket' => $ticket, 'data' => $data];
        // after the request (or the batch's command): by then Exment has given the ticket its
        // number, which it does after the saved event
        if (!static::$hooked) {
            static::$hooked = true;
            app()->terminating(function () {
                static::flush();
            });
        }
    }

    public static function flush(): void
    {
        $list = static::$pending;
        static::$pending = [];
        foreach ($list as $one) {
            try {
                // read again: the number (auto_number) is written after the save
                $ticket = Tickets::find($one['ticket']->id) ?? $one['ticket'];
                $box = Tickets::boxOf(array_get($ticket->value, 'category'));
                $t = [
                    'no' => strval(array_get($ticket->value, 'ticket_no')) ?: '#' . $ticket->id,
                    'subject' => strval(array_get($ticket->value, 'subject')),
                    'box' => $box['id'] ? $box['name'] : '',
                    'url' => Its::url('ticket', strval($ticket->id)),
                ];
                $m = static::compose($one['event'], $t, $one['data']);
                foreach (static::TARGETS as $key => $target) {
                    $url = strval(Its::setting($key, ''));
                    if ($url !== '') {
                        Escalation::post($url, static::payload($target, $one['event'], $m, $t));
                    }
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('ExmentSupportDesk chat notice failed: ' . class_basename($e));
            }
        }
    }

    /** A test message from the settings: whether each address answered. */
    public static function test(): array
    {
        $m = ['title' => 'サポートデスクからの試しの知らせ', 'lines' => ['この知らせが見えたら、チャットへの知らせの設定はできています。', Its::url()]];
        $t = ['no' => 'SUP-00000', 'subject' => '試し', 'box' => '', 'url' => Its::url()];
        $out = [];
        foreach (static::TARGETS as $key => $target) {
            $url = strval(Its::setting($key, ''));
            if ($url !== '') {
                $out[$target] = Escalation::post($url, static::payload($target, 'test', $m, $t));
            }
        }

        return $out;
    }
}
