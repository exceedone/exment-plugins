<?php

namespace App\Plugins\ExmentITSupport;

use App\Plugins\ExmentITSupport\Mail\ImapClient;
use App\Plugins\ExmentITSupport\Mail\MailException;
use App\Plugins\ExmentITSupport\Mail\MimeParser;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Mail and alerts in, tickets out.
 *
 * fetch() reads the support mailbox over IMAP (the default, works anywhere).
 * receiveRaw() takes one raw message from elsewhere - a Postfix pipe, an
 * SES or SendGrid inbound hook - for those who prefer push; it is optional,
 * as is anything tied to one cloud (the AWS-free rule of the project).
 * receiveAlert() is the monitor and error-log door (ISS00212).
 */
class Inbox
{
    /** At most this many mails per run, so one run never takes too long. */
    const BATCH = 50;

    /**
     * @return array{ok: bool, created: int, appended: int, skipped: int, message: string}
     */
    public static function fetch(): array
    {
        $result = ['ok' => true, 'created' => 0, 'appended' => 0, 'skipped' => 0, 'message' => ''];
        if (Its::setting('imap_enabled', '0') !== '1' || Its::setting('imap_host', '') === '') {
            $result['message'] = 'メールの受信はオフです。';

            return $result;
        }

        // One run at a time: the batch runs every five minutes and a slow
        // server must not make two runs import the same mail.
        $lock = Cache::lock('its_imap_fetch', 600);
        if (!$lock->get()) {
            $result['message'] = '別の受信が動いています。';

            return $result;
        }

        $client = new ImapClient();
        try {
            $client->connect(strval(Its::setting('imap_host')), intval(Its::setting('imap_port', 993)), strval(Its::setting('imap_security', 'ssl')));
            $client->login(strval(Its::setting('imap_user')), strval(Its::setting('imap_password')));
            $box = $client->select(strval(Its::setting('imap_folder', 'INBOX')) ?: 'INBOX');

            $state = Its::state();
            $last = strval(array_get($state, 'imap_uidvalidity')) === $box['uidvalidity'] ? intval(array_get($state, 'imap_last_uid', 0)) : 0;

            $uids = array_slice($client->unseenUids($last), 0, static::BATCH);
            $maxUid = $last;
            foreach ($uids as $uid) {
                $raw = $client->fetch($uid);
                $outcome = static::receiveRaw($raw);
                $result[$outcome] = ($result[$outcome] ?? 0) + 1;
                $client->markSeen($uid);
                $maxUid = max($maxUid, $uid);
            }
            Its::patchState(['imap_uidvalidity' => $box['uidvalidity'], 'imap_last_uid' => $maxUid, 'imap_checked_at' => now()->toDateTimeString(), 'imap_error' => null]);
            $result['message'] = "新しいチケット {$result['created']} 件・返信 {$result['appended']} 件・とばした {$result['skipped']} 件";
        } catch (MailException $e) {
            Its::patchState(['imap_checked_at' => now()->toDateTimeString(), 'imap_error' => $e->getMessage()]);
            $result = array_merge($result, ['ok' => false, 'message' => $e->getMessage()]);
        } catch (\Throwable $e) {
            Log::error('ExmentITSupport IMAP fetch failed: ' . $e->getMessage(), ['exception' => $e]);
            Its::patchState(['imap_checked_at' => now()->toDateTimeString(), 'imap_error' => 'メールを取り込めませんでした（くわしくはログ）。']);
            $result = array_merge($result, ['ok' => false, 'message' => 'メールを取り込めませんでした（くわしくはログ）。']);
        } finally {
            $client->logout();
            $lock->release();
        }

        return $result;
    }

    /**
     * One raw RFC 822 message.
     *
     * @return string created | appended | skipped
     */
    public static function receiveRaw(string $raw): string
    {
        $mail = MimeParser::parse($raw);

        if ($mail['message_id'] !== '' && Mailer::seen($mail['message_id'])) {
            return 'skipped'; // already imported, or our own mail coming back
        }
        if (MimeParser::isAutomatic($mail)) {
            return 'skipped';
        }
        $own = Its::normalizeEmail(Mailer::fromAddress());
        if ($mail['from'] === '' || ($own !== '' && $mail['from'] === $own)) {
            return 'skipped';
        }

        $body = MimeParser::stripQuoted($mail['text']);
        $files = array_map(function ($a) {
            return ['name' => $a['name'], 'content' => $a['content']];
        }, array_slice($mail['attachments'], 0, 20));
        $suspicious = MimeParser::authFailed($mail);

        // Which ticket is this a reply to: the number in the subject, else
        // the mail it answers.
        $ticket = null;
        if (preg_match('/\[(SUP-\d+)\]/u', $mail['subject'], $m)) {
            $ticket = Tickets::findByNo($m[1]);
            // A number in the subject from a stranger is not enough: anyone
            // can type "[SUP-00012]" and write into somebody's ticket.
            if (isset($ticket) && !static::senderBelongs($ticket, $mail['from'])) {
                $ticket = null;
            }
        }
        if (!isset($ticket)) {
            $id = Mailer::ticketIdFor(array_merge([$mail['in_reply_to']], $mail['references']));
            $ticket = $id !== null ? Tickets::find($id) : null;
            if (isset($ticket) && !static::senderBelongs($ticket, $mail['from'])) {
                $ticket = null;
            }
        }

        // A reply quoting a merged ticket belongs to the ticket it went into,
        // when that is the same person's and shown to customers.
        for ($hop = 0; isset($ticket) && $hop < 5 && !empty($into = array_get($ticket->value, 'merged_into')); $hop++) {
            $target = Tickets::find($into);
            if (!isset($target) || !boolval(array_get($target->value, 'customer_visible', '1'))
                || Its::normalizeEmail(strval(array_get($target->value, 'requester_email'))) !== Its::normalizeEmail($mail['from'])) {
                break;
            }
            $ticket = $target;
        }

        if (isset($ticket) && !$suspicious) {
            $after = Tickets::customerWrote($ticket, $body, 'mail', $mail['from'], $mail['from_name'], $mail['message_id'] ?: null, $files);

            return intval($after->id) === intval($ticket->id) ? 'appended' : 'created';
        }

        [$customer] = Tickets::identify($mail['from'], $mail['from_name']);
        if (!isset($customer) && Its::setting('unknown_sender', 'accept') === 'ignore') {
            return 'skipped';
        }

        $subject = trim(preg_replace('/^\s*((re|fw|fwd|返信|転送)\s*[:：]\s*)+/iu', '', $mail['subject']) ?? '');
        $values = [
            'subject' => mb_substr($subject !== '' ? $subject : '（件名なし）', 0, 250),
            'description' => mb_substr($body, 0, 20000),
            'channel' => 'mail',
            'requester_email' => $mail['from'],
            'requester_name' => $mail['from_name'],
            'cc' => implode(', ', array_slice($mail['cc'], 0, 10)),
            'priority' => static::guessPriority($subject . "\n" . $body),
        ];
        if ($suspicious) {
            // Kept, not dropped - a real customer's mis-configured domain looks
            // the same - but never shown to anyone outside until a person looks.
            $values['customer_visible'] = '0';
            $values['tags'] = 'なりすましの疑い';
        }
        $ticket = Tickets::create($values, ['body' => $body, 'message_id' => $mail['message_id'] ?: null], $files);

        if (!$suspicious) {
            static::acknowledge($ticket);
        }

        return 'created';
    }

    /**
     * The sender may write into the ticket: the requester, a CC, or someone
     * at the same customer.
     */
    protected static function senderBelongs($ticket, string $from): bool
    {
        $from = Its::normalizeEmail($from);
        if ($from === Its::normalizeEmail(strval(array_get($ticket->value, 'requester_email')))) {
            return true;
        }
        foreach (explode(',', strval(array_get($ticket->value, 'cc'))) as $cc) {
            if ($from === Its::normalizeEmail($cc)) {
                return true;
            }
        }
        $customerId = array_get($ticket->value, 'customer');
        if (!empty($customerId)) {
            [$customer] = Tickets::identify($from);
            if (isset($customer) && intval($customer->id) === intval($customerId)) {
                return true;
            }
        }

        return false;
    }

    /** Words that mean the work has stopped. */
    public static function guessPriority(string $text): string
    {
        if (preg_match('/(至急|緊急|大至急|止まって|停止|ダウン|全員|使えない|つながらない|urgent|down|outage)/iu', $text)) {
            return 'high';
        }

        return 'normal';
    }

    /** "We got it": the number and the link, so nobody has to ask. */
    public static function acknowledge($ticket): void
    {
        $to = strval(array_get($ticket->value, 'requester_email'));
        if (!Its::isEmail($to)) {
            return;
        }
        $name = strval(array_get($ticket->value, 'requester_name')) ?: 'お客様';
        $link = Its::setting('public_enabled', '1') === '1' ? Tokens::linkFor(intval($ticket->id), $to) : null;
        $text = $name . " 様\n\nお問い合わせを受け付けました。\nチケット番号は " . array_get($ticket->value, 'ticket_no') . " です。担当者から返事をお送りします。";
        $id = Mailer::send($ticket, [$to], Mailer::subjectFor($ticket), Mailer::customerBody($ticket, $text, $link));
        Tickets::addMessage($ticket, [
            'kind' => 'system',
            'body' => '受付のメールを送りました。',
            'via' => 'staff',
            'mail_status' => $id !== null ? 'sent' : 'failed',
            'message_id' => $id,
        ]);
    }

    /**
     * An alert from a monitor or an application's error log.
     *
     * The same error is one ticket: a fingerprint is made from what the
     * sender says identifies it (or from the source and the message with the
     * numbers taken out), and a repeat adds to the count instead of opening a
     * new ticket. A ticket made this way is internal until someone decides to
     * tell the customer.
     *
     * @param array<string, mixed> $data
     * @return array{ticket_id: int, ticket_no: string, created: bool, occurrences: int}
     */
    public static function receiveAlert(array $data): array
    {
        $title = mb_substr(trim(strval($data['title'] ?? $data['message'] ?? 'アラート')), 0, 250);
        $source = mb_substr(trim(strval($data['source'] ?? $data['host'] ?? '')), 0, 200);
        $detail = mb_substr(strval($data['detail'] ?? $data['body'] ?? ''), 0, 20000);
        $fingerprint = trim(strval($data['fingerprint'] ?? ''));
        if ($fingerprint === '') {
            $normal = preg_replace('/\d+/', '#', $source . '|' . $title) ?? '';
            $fingerprint = substr(hash('sha256', $normal), 0, 40);
        }
        $fingerprint = mb_substr($fingerprint, 0, 100);
        $now = Carbon::now()->format('Y-m-d H:i:s');

        $open = Tickets::query()->where('value->fingerprint', $fingerprint)->whereIn('value->status', Its::ACTIVE_STATUSES)->orderByDesc('id')->first();
        if (isset($open)) {
            $count = intval(array_get($open->value, 'occurrences', 1)) + 1;
            $open->setValue(['occurrences' => $count, 'last_occurred_at' => $now]);
            $open->save();
            // Not every repeat: the 2nd, 10th, 100th... keep the timeline readable.
            if (in_array($count, [2, 10, 100, 1000], true)) {
                Tickets::addMessage($open, ['kind' => 'system', 'body' => "同じアラートが {$count} 回目です。\n" . mb_substr($detail, 0, 2000), 'via' => 'monitor']);
            }

            return ['ticket_id' => intval($open->id), 'ticket_no' => strval(array_get($open->value, 'ticket_no')), 'created' => false, 'occurrences' => $count];
        }

        $severity = strtolower(strval($data['severity'] ?? $data['level'] ?? ''));
        $priority = in_array($severity, ['critical', 'emergency', 'alert', 'fatal'], true) ? 'urgent'
            : (in_array($severity, ['error', 'high'], true) ? 'high' : (in_array($severity, ['warning', 'warn'], true) ? 'normal' : 'normal'));

        $values = [
            'subject' => $title,
            'description' => trim(($source !== '' ? "発生元: {$source}\n" : '') . $detail),
            'channel' => 'monitor',
            'priority' => $priority,
            'customer_visible' => '0',
            'fingerprint' => $fingerprint,
            'occurrences' => 1,
            'last_occurred_at' => $now,
            'tags' => 'アラート',
        ];
        $customer = static::customerForAlert($data);
        if (isset($customer)) {
            $values['customer'] = $customer->id;
        }
        $ticket = Tickets::create($values);

        return ['ticket_id' => intval($ticket->id), 'ticket_no' => strval(array_get($ticket->value, 'ticket_no')), 'created' => true, 'occurrences' => 1];
    }

    protected static function customerForAlert(array $data)
    {
        $customers = Its::table(Its::T_CUSTOMER);
        if (!isset($customers)) {
            return null;
        }
        $code = trim(strval($data['customer'] ?? $data['customer_code'] ?? ''));
        if ($code !== '') {
            $hit = $customers->getValueModel()->query()->withoutGlobalScope(\Exceedone\Exment\Model\CustomValueModelScope::class)->where('value->code', $code)->first()
                ?? $customers->getValueModel()->query()->withoutGlobalScope(\Exceedone\Exment\Model\CustomValueModelScope::class)->where('value->name', $code)->first();
            if (isset($hit)) {
                return $hit;
            }
        }
        $host = mb_strtolower(trim(strval($data['source'] ?? $data['host'] ?? '')));
        if ($host !== '' && strpos($host, '.') !== false) {
            $bare = preg_replace('/^[a-z]+:\/\//', '', $host) ?? $host;
            $bare = preg_replace('/:\d+$/', '', explode('/', $bare)[0]) ?? '';

            return $bare === '' ? null : Tickets::customerByDomain('x@' . $bare);
        }

        return null;
    }
}
