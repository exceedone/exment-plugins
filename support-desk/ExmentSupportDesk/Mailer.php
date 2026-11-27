<?php

namespace App\Plugins\ExmentSupportDesk;

use Exceedone\Exment\Model\CustomValue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Mail out of the desk.
 *
 * Uses Exment's own mail settings (System settings → mail), so there is
 * nothing extra to configure to send. Every mail about a ticket carries the
 * ticket number in the subject and a Message-ID we remember, so the reply
 * comes back to the same ticket however the customer's mail program treats
 * the subject.
 */
class Mailer
{
    const REPLY_MARKER = '### この行より上に返信を書いてください ###';

    /** The address Exment's settings start with: not a real one. */
    const PLACEHOLDER_FROM = 'no-reply@hogehoge.com';

    /**
     * The address the mails go from: the support address of the settings, else MAIL_FROM_ADDRESS,
     * else the "from" of Exment's mail settings, else the first system administrator's address.
     * '' when there is none (a mail without From is refused: the settings page says so).
     */
    public static function fromAddress(): string
    {
        $candidates = [strval(Its::setting('support_address', '')), strval(config('mail.from.address'))];
        try {
            $candidates[] = strval(\Exceedone\Exment\Model\System::system_mail_from());
        } catch (\Throwable $e) {
            // an Exment without the setting
        }
        foreach ($candidates as $address) {
            $address = trim($address);
            if (Its::isEmail($address) && strtolower($address) !== static::PLACEHOLDER_FROM) {
                return $address;
            }
        }

        return static::adminAddress();
    }

    protected static function adminAddress(): string
    {
        try {
            $ids = (array) \Exceedone\Exment\Model\System::system_admin_users();
            $users = \Exceedone\Exment\Model\CustomTable::getEloquent(\Exceedone\Exment\Enums\SystemTableName::USER);
            foreach (array_slice($ids, 0, 10) as $id) {
                $user = $users->getValueModel()->query()->withoutGlobalScope(\Exceedone\Exment\Model\CustomValueModelScope::class)->find($id);
                $email = isset($user) ? trim(strval(array_get($user->value, 'email'))) : '';
                if (Its::isEmail($email)) {
                    return $email;
                }
            }
        } catch (\Throwable $e) {
            // no administrator to ask
        }

        return '';
    }

    /**
     * The From of a mail of the plugin, when there is an address; else the mail goes as the
     * mailer of Exment would send it.
     *
     * @param \Illuminate\Mail\Message $message
     */
    public static function setFrom($message): void
    {
        $from = static::fromAddress();
        if (Its::isEmail($from)) {
            $message->from($from, strval(Its::setting('support_name', '')) ?: null);
        }
    }

    public static function subjectFor(CustomValue $ticket, ?string $subject = null): string
    {
        $prefix = trim(strval(Its::setting('subject_prefix', '')));
        $no = strval(array_get($ticket->value, 'ticket_no'));
        $subject = $subject ?? strval(array_get($ticket->value, 'subject'));
        // Do not stack "[SUP-1] Re: [SUP-1] ..." on every round.
        $subject = trim(preg_replace('/\[' . preg_quote($no, '/') . '\]\s*/u', '', $subject) ?? $subject);

        return trim(($prefix !== '' ? $prefix . ' ' : '') . '[' . $no . '] ' . $subject);
    }

    /**
     * Send one mail about a ticket.
     *
     * @param array<int, string> $to
     * @param array<int, string> $cc
     * @return string|null the Message-ID sent, or null when sending failed
     */
    public static function send(CustomValue $ticket, array $to, string $subject, string $body, array $cc = []): ?string
    {
        $to = array_values(array_filter(array_map([Its::class, 'normalizeEmail'], $to), [Its::class, 'isEmail']));
        $cc = array_values(array_diff(array_filter(array_map([Its::class, 'normalizeEmail'], $cc), [Its::class, 'isEmail']), $to));
        if (empty($to)) {
            return null;
        }

        $from = static::fromAddress();
        $domain = substr(strrchr($from ?: 'sd@localhost', '@') ?: '@localhost', 1);
        $messageId = 'sd.' . $ticket->id . '.' . bin2hex(random_bytes(8)) . '@' . $domain;
        $refs = static::threadIds(intval($ticket->id));

        try {
            Mail::raw($body, function ($message) use ($to, $cc, $subject, $from, $messageId, $refs) {
                $message->to($to);
                if (!empty($cc)) {
                    $message->cc($cc);
                }
                if (Its::isEmail($from)) {
                    $message->from($from, strval(Its::setting('support_name', '')) ?: null);
                    $message->replyTo($from);
                }
                $message->subject($subject);
                $headers = $message->getSymfonyMessage()->getHeaders();
                $headers->remove('Message-ID');
                $headers->addIdHeader('Message-ID', $messageId);
                if (!empty($refs)) {
                    $headers->addIdHeader('In-Reply-To', end($refs));
                    $headers->addIdHeader('References', $refs);
                }
                // Ask auto-responders not to answer.
                $headers->addTextHeader('Auto-Submitted', 'auto-generated');
                $headers->addTextHeader('X-Auto-Response-Suppress', 'OOF, AutoReply');
            });
        } catch (\Throwable $e) {
            Log::warning('ExmentSupportDesk mail failed for ticket ' . $ticket->id . ': ' . $e->getMessage());

            return null;
        }

        static::remember($messageId, intval($ticket->id), 'out');

        return $messageId;
    }

    /** Message-IDs of the thread, oldest first, at most 20. */
    public static function threadIds(int $ticketId): array
    {
        return DB::table('sd_mail_index')->where('ticket_id', $ticketId)->orderBy('id')->limit(20)->pluck('message_id')->all();
    }

    public static function remember(string $messageId, int $ticketId, string $direction): void
    {
        $messageId = mb_substr(trim($messageId, '<> '), 0, 191);
        if ($messageId === '') {
            return;
        }
        DB::table('sd_mail_index')->insertOrIgnore([
            'message_id' => $messageId,
            'ticket_id' => $ticketId,
            'direction' => $direction,
            'created_at' => now(),
        ]);
    }

    public static function ticketIdFor(array $messageIds): ?int
    {
        $ids = array_values(array_filter(array_map(function ($id) {
            return mb_substr(trim($id, '<> '), 0, 191);
        }, $messageIds)));
        if (empty($ids)) {
            return null;
        }
        $row = DB::table('sd_mail_index')->whereIn('message_id', $ids)->orderByDesc('id')->first();

        return isset($row) ? intval($row->ticket_id) : null;
    }

    public static function seen(string $messageId): bool
    {
        $messageId = mb_substr(trim($messageId, '<> '), 0, 191);

        return $messageId !== '' && DB::table('sd_mail_index')->where('message_id', $messageId)->exists();
    }

    /**
     * The body of a mail to the customer: marker, text, link, signature.
     */
    public static function customerBody(CustomValue $ticket, string $text, ?string $link): string
    {
        $lines = [static::REPLY_MARKER, '', rtrim($text), ''];
        if ($link) {
            $lines[] = '■ このチケットの進み具合（ログインは要りません）';
            $lines[] = $link;
            $lines[] = '';
        }
        $lines[] = 'チケット番号: ' . strval(array_get($ticket->value, 'ticket_no'));
        $lines[] = 'このメールにそのまま返信すると、同じチケットに入ります。';
        $sig = trim(strval(Its::setting('signature', '')));
        if ($sig !== '') {
            $lines[] = '';
            $lines[] = $sig;
        }

        return implode("\n", $lines);
    }
}
