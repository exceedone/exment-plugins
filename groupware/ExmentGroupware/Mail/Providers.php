<?php

namespace App\Plugins\ExmentGroupware\Mail;

/**
 * The mail services a user can pick when adding an account.
 *
 * Exment does not run a mail server: it is a mail program, like Outlook,
 * that connects to the server the company already uses, over IMAP (read)
 * and SMTP (send). So the same mailbox can stay open in Outlook or on the
 * phone at the same time.
 *
 * - host_hint: the host is the customer's own (Xserver "sv1234.xserver.jp",
 *   KAGOYA "m123.kagoya.net"), so it is asked for, with this as the example.
 * - append_sent: save a copy into the Sent folder by IMAP after sending.
 *   Gmail and Microsoft 365 do this themselves for mail sent by SMTP; on
 *   Xserver and KAGOYA, without it, the mail would be missing from Sent.
 */
class Providers
{
    const XSERVER = 'xserver';
    const KAGOYA = 'kagoya';
    const MICROSOFT = 'microsoft365';
    const GMAIL = 'gmail';
    const OTHER = 'other';

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        return [
            static::XSERVER => [
                'label' => 'Xserver（エックスサーバー・Xserver ビジネス）',
                'short' => 'Xserver',
                'auth' => ['password'],
                'imap_host' => '',
                'imap_port' => 993,
                'imap_security' => 'ssl',
                'smtp_host' => '',
                'smtp_port' => 465,
                'smtp_security' => 'ssl',
                'smtp_same_host' => true,
                'host_hint' => 'sv1234.xserver.jp',
                'host_help' => 'サーバーパネルの「サーバー情報」の「ホスト名」、または設定完了のメールにある「メールサーバー」（例: sv1234.xserver.jp）。受信・送信とも同じです。',
                'username_help' => 'メールアドレス全体（@ 以降も含む）。',
                'username_is_email' => true,
                'append_sent' => true,
            ],
            static::KAGOYA => [
                'label' => 'KAGOYA（カゴヤ・ジャパン）',
                'short' => 'KAGOYA',
                'auth' => ['password'],
                'imap_host' => '',
                'imap_port' => 993,
                'imap_security' => 'ssl',
                'smtp_host' => 'smtp.kagoya.net',
                'smtp_port' => 465,
                'smtp_security' => 'ssl',
                'smtp_same_host' => false,
                'host_hint' => 'm123.kagoya.net',
                'host_help' => 'コントロールパネルの「メールサーバー情報」にある受信（IMAP）サーバー名（共用メールサーバーは m・mas で始まる名前、例: m123.kagoya.net）。送信は smtp.kagoya.net です。',
                'username_help' => 'メールアカウント名（コントロールパネルの「メールアカウント」に出ている名前）。',
                'username_is_email' => false,
                'append_sent' => true,
            ],
            static::MICROSOFT => [
                'label' => 'Microsoft 365（Outlook・Exchange Online）',
                'short' => 'Microsoft 365',
                'auth' => ['oauth'],
                'imap_host' => 'outlook.office365.com',
                'imap_port' => 993,
                'imap_security' => 'ssl',
                'smtp_host' => 'smtp.office365.com',
                'smtp_port' => 587,
                'smtp_security' => 'tls',
                'smtp_same_host' => false,
                'host_hint' => '',
                'host_help' => '',
                'username_help' => 'Microsoft 365 のメールアドレス。サインインは Microsoft の画面で行います（パスワードは Exment に保存しません）。',
                'username_is_email' => true,
                'append_sent' => false,
            ],
            static::GMAIL => [
                'label' => 'Gmail・Google Workspace',
                'short' => 'Gmail',
                'auth' => ['oauth', 'password'],
                'imap_host' => 'imap.gmail.com',
                'imap_port' => 993,
                'imap_security' => 'ssl',
                'smtp_host' => 'smtp.gmail.com',
                'smtp_port' => 465,
                'smtp_security' => 'ssl',
                'smtp_same_host' => false,
                'host_hint' => '',
                'host_help' => '',
                'username_help' => 'Gmail のアドレス。Google でサインインする（おすすめ）か、Google アカウントの「アプリ パスワード」（2 段階認証が必要）を使います。ふだんのパスワードでは入れません。',
                'username_is_email' => true,
                'append_sent' => false,
            ],
            static::OTHER => [
                'label' => 'そのほかの IMAP・SMTP サーバー（さくら・ロリポップ・お名前.com・社内のサーバーなど）',
                'short' => 'IMAP',
                'auth' => ['password'],
                'imap_host' => '',
                'imap_port' => 993,
                'imap_security' => 'ssl',
                'smtp_host' => '',
                'smtp_port' => 465,
                'smtp_security' => 'ssl',
                'smtp_same_host' => false,
                'host_hint' => 'mail.example.co.jp',
                'host_help' => 'プロバイダーの案内にある受信（IMAP）・送信（SMTP）サーバー名。',
                'username_help' => 'プロバイダーの案内にあるユーザー名（多くはメールアドレス）。',
                'username_is_email' => false,
                'append_sent' => true,
            ],
        ];
    }

    /** @return array<string, mixed> */
    public static function get(string $key): array
    {
        $all = static::all();

        return $all[$key] ?? $all[static::OTHER];
    }

    public static function exists(string $key): bool
    {
        return array_key_exists($key, static::all());
    }
}
