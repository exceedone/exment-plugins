<?php

namespace App\Plugins\ExmentGroupware\Mail;

use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Transport\Smtp\Auth\XOAuth2Authenticator;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Writing and sending a mail from one account, over that account's own
 * SMTP server (not Exment's system mail settings): the mail goes out from
 * the user's address, through the server that signs it (SPF / DKIM).
 */
class Sender
{
    /** @var object */
    protected $account;

    /** @var OAuth */
    protected $oauth;

    public function __construct(object $account, OAuth $oauth)
    {
        $this->account = $account;
        $this->oauth = $oauth;
    }

    /**
     * @param array<string, mixed> $m to, cc, bcc (arrays of addresses), subject, html,
     *   in_reply_to, references, attachments [[name, type, content]]
     */
    public function build(array $m): Email
    {
        $a = $this->account;
        $email = new Email();
        $email->from(new Address(strval($a->email), strval($a->from_name ?? '')));
        foreach (['to', 'cc', 'bcc'] as $key) {
            $list = static::addresses($m[$key] ?? []);
            if (!empty($list)) {
                $email->$key(...$list);
            }
        }
        $subject = trim(str_replace(["\r", "\n"], ' ', strval($m['subject'] ?? '')));
        $email->subject($subject);
        $html = strval($m['html'] ?? '');
        $clean = HtmlSafe::clean($html, [], false)['html'];
        $email->html('<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>' . $clean . '</body></html>', 'utf-8');
        $email->text(MimeParser::htmlToText($clean), 'utf-8');
        foreach ($m['attachments'] ?? [] as $att) {
            $email->attach(strval($att['content']), static::fileName(strval($att['name'])), strval($att['type'] ?: 'application/octet-stream'));
        }

        $domain = substr(strrchr(strval($a->email), '@') ?: '@localhost', 1);
        $headers = $email->getHeaders();
        $headers->addIdHeader('Message-ID', bin2hex(random_bytes(12)) . '.gw@' . $domain);
        $headers->addDateHeader('Date', new \DateTimeImmutable());
        $inReplyTo = trim(strval($m['in_reply_to'] ?? ''), '<> ');
        $refs = array_values(array_filter(array_map(function ($r) {
            return trim(strval($r), '<> ');
        }, (array) ($m['references'] ?? []))));
        if ($inReplyTo !== '') {
            $headers->addIdHeader('In-Reply-To', $inReplyTo);
            if (!in_array($inReplyTo, $refs, true)) {
                $refs[] = $inReplyTo;
            }
        }
        if (!empty($refs)) {
            $headers->addIdHeader('References', array_slice($refs, -20));
        }
        $headers->addTextHeader('X-Mailer', 'Exment Groupware');

        return $email;
    }

    /**
     * Send. Returns the message as sent, for the Sent folder.
     */
    public function send(Email $email): string
    {
        if (empty($email->getTo()) && empty($email->getCc()) && empty($email->getBcc())) {
            throw new MailException('宛先を入れてください。');
        }
        try {
            $sent = $this->transport()->send($email);
        } catch (TransportExceptionInterface $e) {
            throw new MailException('送信できませんでした: ' . static::explain($e->getMessage()));
        }
        $raw = $sent !== null ? $sent->toString() : $email->toString();

        return static::withBcc($raw, $email);
    }

    /** Connect and sign in to the SMTP server only (the account test). */
    public function test(): void
    {
        $t = $this->transport();
        try {
            $t->start();
            $t->stop();
        } catch (TransportExceptionInterface $e) {
            throw new MailException('送信（SMTP）のサーバーに入れません: ' . static::explain($e->getMessage()));
        }
    }

    /** A draft as a whole message, Bcc kept. */
    public static function draftRaw(Email $email): string
    {
        return static::withBcc($email->toString(), $email);
    }

    protected function transport(): EsmtpTransport
    {
        $a = $this->account;
        HostGuard::check(strval($a->smtp_host), intval($a->smtp_port));
        $security = strval($a->smtp_security);
        // true: TLS from the first byte (465). false: STARTTLS when offered (587).
        $t = new EsmtpTransport(strval($a->smtp_host), intval($a->smtp_port), $security === 'ssl');
        if ($security === 'tls') {
            $t->setRequireTls(true);
        } elseif ($security === 'none') {
            $t->setAutoTls(false);
        }
        $t->setUsername(strval($a->username));
        if ($a->auth_type === 'oauth') {
            $t->setPassword($this->oauth->accessToken($a));
            $t->setAuthenticators([new XOAuth2Authenticator()]);
        } else {
            $t->setPassword(strval(Accounts::secret($a)['password'] ?? ''));
        }
        $domain = substr(strrchr(strval($a->email), '@') ?: '@localhost', 1);
        $t->setLocalDomain($domain);

        return $t;
    }

    /**
     * The Sent / Drafts copy keeps Bcc, as Outlook does (Symfony takes it out
     * of what is sent, which is right for the recipients).
     */
    protected static function withBcc(string $raw, Email $email): string
    {
        $bcc = $email->getBcc();
        if (empty($bcc)) {
            return $raw;
        }
        $line = 'Bcc: ' . implode(', ', array_map(function (Address $a) {
            return $a->getAddress();
        }, $bcc));

        return $line . "\r\n" . $raw;
    }

    /**
     * @param mixed $list string "a@x, b@y" or array
     * @return array<int, Address>
     */
    public static function addresses($list): array
    {
        if (is_string($list)) {
            $list = preg_split('/[,;、，\n]+/u', $list) ?: [];
        }
        $out = [];
        foreach ((array) $list as $one) {
            $one = trim(strval($one));
            if ($one === '') {
                continue;
            }
            [$addr, $name] = MimeParser::address($one);
            if ($addr === '' || filter_var($addr, FILTER_VALIDATE_EMAIL) === false) {
                throw new MailException('メールアドレスの形が正しくありません: ' . mb_substr($one, 0, 100));
            }
            $out[] = new Address($addr, $name);
        }

        return $out;
    }

    protected static function fileName(string $name): string
    {
        $name = preg_replace('/[\x00-\x1F\x7F\/\\\\]/u', '', $name) ?? '';

        return $name !== '' ? mb_substr($name, 0, 200) : 'attachment';
    }

    protected static function explain(string $message): string
    {
        if (preg_match('/\b535\b|authentication|auth.*failed|credentials/i', $message)) {
            return 'ユーザー名・パスワード（または サインイン）を確かめてください。（' . mb_substr($message, 0, 200) . '）';
        }
        if (preg_match('/Connection could not be established|timed out|getaddrinfo/i', $message)) {
            return 'サーバー名・ポート番号・暗号化を確かめてください。（' . mb_substr($message, 0, 200) . '）';
        }

        return mb_substr($message, 0, 300);
    }
}
