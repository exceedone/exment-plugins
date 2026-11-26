<?php

namespace App\Plugins\ExmentITSupport;

use App\Plugins\ExmentITSupport\Alerts\Adapters;
use App\Plugins\ExmentITSupport\Alerts\Rules;
use App\Plugins\ExmentITSupport\Mail\ImapClient;
use App\Plugins\ExmentITSupport\Mail\MailException;
use App\Plugins\ExmentITSupport\Mail\MimeParser;
use Carbon\Carbon;
use Exceedone\Exment\Model\CustomValue;
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
    /** The first line of a note made from a mail whose sender could not be checked. */
    const UNPROVEN_MARK = '【差出人を確かめられないメール（なりすましの疑い）】';
    /** The tag on a ticket that got such a note. */
    const TAG_CHECK = '要確認';
    const UNPROVEN_NOTES_PER_DAY = 5;

    /** At most this many mails per run, so one run never takes too long. */
    const BATCH = 50;

    /**
     * @return array{ok: bool, created: int, appended: int, skipped: int, message: string}
     */
    public static function fetch(): array
    {
        $result = ['ok' => true, 'created' => 0, 'appended' => 0, 'skipped' => 0, 'message' => ''];
        if (!Feature::on(Feature::PORTAL)) {
            $result['message'] = '「お客様の窓口」を、管理者設定の「機能の管理」で使っていません。';

            return $result;
        }
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
        $suspicious = static::senderUnproven($mail);
        static::noteAuthserv($mail, $suspicious);

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

        if (isset($ticket) && $suspicious && in_array(strval(array_get($ticket->value, 'status')), Its::ACTIVE_STATUSES, true)
            && empty(array_get($ticket->value, 'merged_into')) && static::unprovenNotesToday($ticket) < static::UNPROVEN_NOTES_PER_DAY) {
            // A reply in the thread of an open ticket whose sender could not be
            // checked: kept on the ticket for the agents only, as a flagged
            // note (not written as the customer's, not mailed on), and the
            // ticket is tagged so somebody looks. A few a day per ticket, no
            // attachments (their names are listed): whoever knows a requester's
            // address and a ticket number can send these. Past the limit the
            // mail goes the way of other unproven mail (a new internal ticket),
            // so a real customer's reply is never dropped.
            $names = array_map(function ($f) {
                return strval($f['name']);
            }, $files);
            Tickets::addMessage($ticket, [
                'kind' => 'note',
                'body' => static::UNPROVEN_MARK . "\nFrom: " . $mail['from']
                    . (!empty($names) ? "\n添付（取り込んでいません）: " . implode(', ', $names) : '') . "\n\n" . mb_substr($body, 0, 20000),
                'via' => 'mail',
                'message_id' => $mail['message_id'] ?: null,
            ]);
            Tickets::addTag($ticket, static::TAG_CHECK);
            if ($mail['message_id'] !== '') {
                Mailer::remember($mail['message_id'], intval($ticket->id), 'in');
            }

            return 'appended';
        }
        if (isset($ticket) && !$suspicious) {
            if (Its::normalizeEmail(strval(array_get($ticket->value, 'requester_email'))) === Its::normalizeEmail($mail['from'])) {
                Tickets::markAddressVerified($ticket);
            }
            $after = Tickets::customerWrote($ticket, $body, 'mail', $mail['from'], $mail['from_name'], $mail['message_id'] ?: null, $files);

            return intval($after->id) === intval($ticket->id) ? 'appended' : 'created';
        }

        [$customer] = Tickets::identify($mail['from'], $mail['from_name'], null, !$suspicious);
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
            // a CC may write into the ticket (senderBelongs): only from a sender who was checked
            'cc' => $suspicious ? '' : implode(', ', array_slice($mail['cc'], 0, 10)),
            'priority' => static::guessPriority($subject . "\n" . $body),
        ];
        if ($suspicious) {
            // Kept, not dropped - a real customer's mis-configured domain looks
            // the same - but never shown to anyone outside until a person looks.
            $values['customer_visible'] = '0';
            $values['tags'] = 'なりすましの疑い';
        }
        $ticket = Tickets::create($values, ['body' => $body, 'message_id' => $mail['message_id'] ?: null], $files, !$suspicious);

        if (!$suspicious) {
            static::acknowledge($ticket);
        }

        return 'created';
    }

    /**
     * The From of this mail may be someone else's. With the default setting
     * ("require"), a mail counts as the sender's only when the receiving
     * server's checks passed for the From's domain (MimeParser::authPassed);
     * with "fail_only", only a mail that failed them is doubted (the old rule,
     * for a server that adds no Authentication-Results header).
     */
    /**
     * What the settings can tell the admin about the headers on top of the
     * mail that comes in: the server name seen on top of mail from at least
     * three different sender domains (one sender can write any name on a
     * server that adds no header of its own), or that the top header carries
     * no name (Microsoft 365). Also when unproven mail last came, for the
     * desk's warning.
     */
    protected static function noteAuthserv(array $mail, bool $unproven): void
    {
        $state = Its::state();
        $patch = [];
        $top = strval(($mail['headers']['authentication-results'] ?? [])[0] ?? '');
        $fromDomain = mb_substr(strtolower(substr(strrchr(strval($mail['from']), '@') ?: '', 1)), 0, 100);
        if ($top !== '' && $fromDomain !== '') {
            $id = MimeParser::parseAuthResults($top)['id'];
            $key = $id === '' ? '(none)' : $id;
            if (preg_match('/^[a-z0-9.-]{1,200}$/', $id) === 1 || $id === '') {
                $seen = (array) array_get($state, 'mail_authserv_tops', []);
                $domains = (array) ($seen[$key] ?? []);
                if (!in_array($fromDomain, $domains, true) && count($domains) < 3) {
                    $domains[] = $fromDomain;
                    $seen[$key] = $domains;
                    $patch['mail_authserv_tops'] = array_slice($seen, -5, 5, true);
                    if (count($domains) >= 3) {
                        $patch['mail_authserv_seen'] = $key;
                    }
                }
            }
        }
        if ($unproven && strval(array_get($state, 'mail_unproven_on')) !== date('Y-m-d')) {
            $patch['mail_unproven_on'] = date('Y-m-d');
        }
        if (!empty($patch)) {
            Its::patchState($patch);
        }
    }

    protected static function unprovenNotesToday($ticket): int
    {
        $table = Its::table(Its::T_MESSAGE);
        if (!isset($table)) {
            return 0;
        }

        return $table->getValueModel()->query()->withoutGlobalScope(\Exceedone\Exment\Model\CustomValueModelScope::class)
            ->where('parent_type', Its::T_TICKET)->where('parent_id', $ticket->id)
            ->where('created_at', '>=', Carbon::today())->where('value->kind', 'note')
            ->where('value->body', 'like', static::UNPROVEN_MARK . '%')->count();
    }

    public static function senderUnproven(array $mail): bool
    {
        $mode = Its::setting('mail_auth', 'require');
        if ($mode === 'fail_only') {
            return MimeParser::authFailed($mail);
        }

        return !MimeParser::authPassed($mail, strval(Its::setting('mail_authserv_id', '')), $mode === 'require_top');
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
            [$customer] = Tickets::identify($from, '', null, false);
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
    /** @return bool whether the mail went out */
    public static function acknowledge($ticket): bool
    {
        $to = strval(array_get($ticket->value, 'requester_email'));
        if (!Its::isEmail($to)) {
            return false;
        }
        $name = strval(array_get($ticket->value, 'requester_name')) ?: 'お客様';
        $link = Its::portalOn() ? Tokens::linkFor(intval($ticket->id), $to) : null;
        $text = $name . " 様\n\nお問い合わせを受け付けました。\nチケット番号は " . array_get($ticket->value, 'ticket_no') . " です。担当者から返事をお送りします。";
        $id = Mailer::send($ticket, [$to], Mailer::subjectFor($ticket), Mailer::customerBody($ticket, $text, $link));
        Tickets::addMessage($ticket, [
            'kind' => 'system',
            'body' => '受付のメールを送りました。',
            'via' => 'staff',
            'mail_status' => $id !== null ? 'sent' : 'failed',
            'message_id' => $id,
        ]);

        return $id !== null;
    }

    /**
     * An alert from a monitor or an application's error log, in the fields
     * this door has always taken (the old shared key).
     *
     * @param array<string, mixed> $data
     * @return array{ticket_id: int, ticket_no: string, created: bool, occurrences: int, action: string}
     */
    public static function receiveAlert(array $data): array
    {
        $events = Adapters::read('generic', $data) ?: Adapters::read('generic', ['title' => 'アラート'] + $data);
        $event = $events[0];
        $event['status'] = 'firing'; // the old key: no field ever resolved a ticket

        return static::receiveAlertEvent($event, IntakeSources::legacy());
    }

    /**
     * One alert, read into the one shape (Alerts\Adapters).
     *
     * The same problem is one ticket: a repeat adds to the count instead of
     * opening a new ticket. Its recovery writes "recovered" on the ticket and,
     * if the sender is set so, resolves or closes it. A problem back within
     * the sender's minutes after its ticket ended opens that ticket again
     * (a flapping alert stays one ticket). A ticket made this way is internal
     * until someone decides to tell the customer.
     *
     * @param array<string, string> $event
     * @param array{kind: string, on_resolved: string, flap_minutes: int, link_asset: bool, legacy: bool} $options
     * @return array{ticket_id: int, ticket_no: string, created: bool, occurrences: int, action: string}
     */
    public static function receiveAlertEvent(array $event, array $options): array
    {
        $fingerprint = $event['fingerprint'];
        $now = Carbon::now();
        $stamp = $now->format('Y-m-d H:i:s');

        $open = Tickets::query()->where('value->fingerprint', $fingerprint)->whereIn('value->status', Its::ACTIVE_STATUSES)->orderByDesc('id')->first();
        $ended = null;
        $endedAt = null;
        if (!isset($open) && $event['status'] === 'firing' && $options['flap_minutes'] > 0) {
            $ended = Tickets::query()->where('value->fingerprint', $fingerprint)->whereIn('value->status', ['resolved', 'closed'])->orderByDesc('id')->first();
            $at = isset($ended) ? (array_get($ended->value, 'resolved_at') ?: array_get($ended->value, 'closed_at')) : null;
            $endedAt = !empty($at) ? Carbon::parse($at)->getTimestamp() : null;
        }
        $action = Rules::decide(
            $event['status'],
            isset($open) ? strval(array_get($open->value, 'status')) : null,
            $endedAt,
            $options['on_resolved'],
            $options['flap_minutes'],
            $now->getTimestamp()
        );
        $result = function ($ticket, bool $created, string $action) {
            return [
                'ticket_id' => isset($ticket) ? intval($ticket->id) : 0,
                'ticket_no' => isset($ticket) ? strval(array_get($ticket->value, 'ticket_no')) : '',
                'created' => $created,
                'occurrences' => isset($ticket) ? intval(array_get($ticket->value, 'occurrences', 1)) : 0,
                'action' => $action,
            ];
        };

        switch ($action) {
            case 'ignore':
                return $result(null, false, 'ignore');

            case 'note':
            case 'resolve':
            case 'close':
                // One "recovered" a while per ticket: a flapping alert would write it every minute.
                $wait = max(60, $options['flap_minutes'] * 60);
                if (Cache::add('its_alert_recovered:' . $open->id, 1, $wait) || $action !== 'note') {
                    Tickets::addMessage($open, ['kind' => 'system', 'body' => static::recoveredText($event, $action), 'via' => 'monitor']);
                }
                if ($action !== 'note') {
                    $open->setValue(['status' => $action === 'close' ? 'closed' : 'resolved']);
                    $open->save();
                }

                return $result($open, false, $action);

            case 'count':
                $count = intval(array_get($open->value, 'occurrences', 1)) + 1;
                $open->setValue(['occurrences' => $count, 'last_occurred_at' => $stamp]);
                if ($options['link_asset'] && empty(array_get($open->value, 'asset'))) {
                    static::linkAsset($open, $event);
                }
                $open->save();
                // Not every repeat: the 2nd, 10th, 100th... keep the timeline readable.
                if (in_array($count, [2, 10, 100, 1000], true)) {
                    Tickets::addMessage($open, ['kind' => 'system', 'body' => "同じアラートが {$count} 回目です。\n" . mb_substr($event['detail'], 0, 2000), 'via' => 'monitor']);
                }

                return $result($open, false, 'count');

            case 'reopen':
                $count = intval(array_get($ended->value, 'occurrences', 1)) + 1;
                $ended->setValue(['status' => 'open', 'occurrences' => $count, 'last_occurred_at' => $stamp]);
                $ended->save();
                Tickets::addTag($ended, 'ばたつき');
                Tickets::addMessage($ended, [
                    'kind' => 'system',
                    'body' => "復旧から {$options['flap_minutes']} 分以内に、また起きました（ばたつき）。このチケットを開け直しました（{$count} 回目）。\n" . mb_substr($event['detail'], 0, 2000),
                    'via' => 'monitor',
                ]);

                return $result($ended, false, 'reopen');
        }

        $severity = $event['severity'];
        $priority = $severity === 'critical' ? 'urgent' : ($severity === 'error' ? 'high' : ($severity === 'info' ? 'low' : 'normal'));
        if ($options['legacy']) {
            // as before: only the old words, and nothing below normal
            $priority = $priority === 'low' ? 'normal' : $priority;
        }
        $source = $event['source'];
        $description = trim(($source !== '' ? "発生元: {$source}\n" : '') . $event['detail']
            . ($event['url'] !== '' ? "\n\n監視の画面: " . $event['url'] : '')
            . ($event['external_id'] !== '' && !$options['legacy'] ? "\nアラートの ID: " . $event['external_id'] : ''));
        $values = [
            'subject' => $event['title'],
            'description' => mb_substr($description, 0, 20000),
            'channel' => 'monitor',
            'priority' => $priority,
            'customer_visible' => '0',
            'fingerprint' => $fingerprint,
            'occurrences' => 1,
            'last_occurred_at' => $stamp,
            'tags' => 'アラート',
        ];
        $customer = static::customerForAlert(['customer' => $event['customer'], 'source' => $source]);
        if (isset($customer)) {
            $values['customer'] = $customer->id;
        }
        if ($options['link_asset']) {
            $asset = static::assetFor($event);
            if (isset($asset)) {
                $values['asset'] = $asset->id;
                if (!isset($values['customer']) && !empty(array_get($asset->value, 'customer'))) {
                    $values['customer'] = array_get($asset->value, 'customer');
                }
            }
        }
        $ticket = Tickets::create($values);

        return $result($ticket, true, 'create');
    }

    /**
     * @param array<string, string> $event
     */
    protected static function recoveredText(array $event, string $action): string
    {
        $done = ['note' => '', 'resolve' => '「解決」にしました。', 'close' => '「完了」にしました。'][$action] ?? '';

        return trim('監視から「復旧しました」の知らせが来ました。' . $done . "\n" . mb_substr($event['detail'], 0, 2000));
    }

    /**
     * The IT asset the alert is about: by its name (the host, or the host's
     * first label) or by the IP address or host in its "place / IP address".
     *
     * @param array<string, string> $event
     */
    public static function assetFor(array $event): ?CustomValue
    {
        $assets = Its::table(Its::T_ASSET);
        $host = $event['host'];
        $ip = $event['ip'];
        if (!isset($assets) || ($host === '' && $ip === '')) {
            return null;
        }
        $names = array_values(array_unique(array_filter([$host, strpos($host, '.') !== false && !Adapters::isIp($host) ? explode('.', $host)[0] : ''])));
        $query = $assets->getValueModel()->query()->withoutGlobalScope(\Exceedone\Exment\Model\CustomValueModelScope::class)
            ->where(function ($q) use ($names, $ip, $host) {
                foreach ($names as $name) {
                    $q->orWhere('value->name', $name)->orWhere('value->name', strtoupper($name));
                }
                foreach (array_filter([$ip, $host]) as $word) {
                    $q->orWhere('value->location', 'like', '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $word) . '%');
                }
            })->orderBy('id')->limit(20);
        foreach ($query->get() as $asset) {
            if (Rules::assetMatches(strval(array_get($asset->value, 'name')), strval(array_get($asset->value, 'location')), $host, $ip)) {
                return $asset;
            }
        }

        return null;
    }

    /**
     * @param array<string, string> $event
     */
    protected static function linkAsset(CustomValue $ticket, array $event): void
    {
        $asset = static::assetFor($event);
        if (isset($asset)) {
            $ticket->setValue(['asset' => $asset->id]);
        }
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
