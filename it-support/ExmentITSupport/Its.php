<?php

namespace App\Plugins\ExmentITSupport;

use Exceedone\Exment\Model\CustomTable;
use Exceedone\Exment\Model\Plugin;
use Exceedone\Exment\Model\System;
use Illuminate\Support\Facades\Crypt;

/**
 * Everything the rest of the plugin needs to agree on: table names, the word
 * lists, the plugin row and the settings stored on it.
 *
 * Settings live on the plugin row (plugins.options.its_settings) rather than
 * in a table of their own, so a backup of Exment carries them and an
 * uninstall takes them away. Secrets (the mail password, the intake key) are
 * encrypted with the application key before they are written.
 */
class Its
{
    const PLUGIN_NAME = 'ExmentITSupport';

    const SETTINGS_KEY = 'its_settings';
    const STATE_KEY = 'its_state';

    // ---- tables ----------------------------------------------------------

    const T_TICKET = 'its_ticket';
    const T_MESSAGE = 'its_message';
    const T_CUSTOMER = 'its_customer';
    const T_CONTACT = 'its_contact';
    const T_CATEGORY = 'its_category';
    const T_SLA = 'its_sla';
    const T_KNOWLEDGE = 'its_knowledge';
    const T_TEMPLATE = 'its_template';
    const T_ASSET = 'its_asset';

    /** Role groups made by the setup. */
    const ROLE_AGENT = 'its_agent_group';
    const ROLE_CUSTOMER = 'its_customer_group';

    // ---- word lists ------------------------------------------------------

    /** Ticket status. The order is the order of the work. */
    const STATUSES = [
        'new' => '新規',
        'open' => '対応中',
        'pending' => 'お客様の返事待ち',
        'on_hold' => '保留（社内）',
        'resolved' => '解決',
        'closed' => '完了',
    ];

    const STATUS_COLORS = [
        'new' => '#e74c3c',
        'open' => '#3c8dbc',
        'pending' => '#f39c12',
        'on_hold' => '#8e44ad',
        'resolved' => '#27ae60',
        'closed' => '#7f8c8d',
    ];

    /** Statuses in which the ticket is still somebody's work. */
    const ACTIVE_STATUSES = ['new', 'open', 'pending', 'on_hold'];

    /** Statuses in which the SLA clock does not run. */
    const PAUSED_STATUSES = ['pending'];

    const PRIORITIES = [
        'urgent' => '緊急',
        'high' => '高',
        'normal' => '中',
        'low' => '低',
    ];

    const PRIORITY_COLORS = [
        'urgent' => '#c0392b',
        'high' => '#e67e22',
        'normal' => '#3c8dbc',
        'low' => '#95a5a6',
    ];

    const CHANNELS = [
        'mail' => 'メール',
        'form' => '受付フォーム',
        'portal' => 'ポータル',
        'phone' => '電話',
        'monitor' => '監視・エラーログ',
        'staff' => '社内で登録',
        'asset' => '資産の期限',
    ];

    const MESSAGE_KINDS = [
        'customer' => 'お客様から',
        'reply' => 'お客様への返信',
        'note' => '社内メモ',
        'system' => 'システム',
    ];

    const SLA_STATES = [
        'ok' => '期限内',
        'warning' => '期限が近い',
        'breached' => '超過',
        'met' => '達成',
        'none' => 'なし',
    ];

    const SLA_COLORS = [
        'ok' => '#27ae60',
        'warning' => '#f39c12',
        'breached' => '#c0392b',
        'met' => '#7f8c8d',
        'none' => '#bdc3c7',
    ];

    const MAIL_STATUSES = [
        'sent' => '送信済み',
        'failed' => '送信できなかった',
        'none' => '送らない',
    ];

    const ASSET_KINDS = [
        'pc' => 'PC',
        'server' => 'サーバー',
        'network' => 'ネットワーク機器',
        'printer' => 'プリンター・複合機',
        'mobile' => 'スマホ・タブレット',
        'software' => 'ソフトウェア・ライセンス',
        'cloud' => 'クラウド・ドメイン・証明書',
        'other' => 'その他',
    ];

    // ---- settings --------------------------------------------------------

    /** Defaults for every setting, so an untouched screen behaves as shown. */
    const DEFAULTS = [
        // Mail in (IMAP).
        'imap_enabled' => '0',
        'imap_host' => '',
        'imap_port' => '993',
        'imap_security' => 'ssl',
        'imap_user' => '',
        'imap_password' => '',
        'imap_folder' => 'INBOX',
        // Mail out: the address customers see and reply to.
        'support_address' => '',
        'support_name' => 'ITサポート',
        'subject_prefix' => '',
        'signature' => "--\nITサポート窓口",
        // Business hours for the SLA clock.
        'business_days' => '1,2,3,4,5',
        'business_start' => '09:00',
        'business_end' => '18:00',
        'holidays' => '',
        // Automation.
        'pending_remind_days' => '3',
        'pending_resolve_days' => '7',
        'resolved_close_days' => '7',
        'asset_notice_days' => '30',
        'auto_assign' => 'category',
        'csat_enabled' => '1',
        // No-login sharing.
        'public_enabled' => '1',
        'link_days_after_close' => '30',
        'unknown_sender' => 'accept',
        // Error-log intake.
        'intake_key' => '',
        // Text shown on the public pages.
        'portal_title' => 'ITサポート窓口',
        'portal_message' => 'パソコン・Webサイト・システムのご依頼やお困りごとをお送りください。担当者から返事をお送りします。',
    ];

    /** Settings stored encrypted. */
    const SECRET_SETTINGS = ['imap_password', 'intake_key'];

    /** @var array<string, mixed>|null */
    protected static $settings;

    /** @var Plugin|null|false */
    protected static $plugin = false;

    public static function plugin(): ?Plugin
    {
        if (static::$plugin === false) {
            static::$plugin = Plugin::where('plugin_name', static::PLUGIN_NAME)->first();
        }

        return static::$plugin ?: null;
    }

    public static function forget(): void
    {
        static::$plugin = false;
        static::$settings = null;
        System::clearCache();
    }

    /**
     * @return array<string, mixed>
     */
    public static function settings(): array
    {
        if (isset(static::$settings)) {
            return static::$settings;
        }

        $plugin = static::plugin();
        $stored = isset($plugin) ? array_get((array) $plugin->options, static::SETTINGS_KEY, []) : [];
        $values = array_merge(static::DEFAULTS, is_array($stored) ? $stored : []);

        foreach (static::SECRET_SETTINGS as $key) {
            $values[$key] = static::decrypt(strval($values[$key] ?? ''));
        }

        return static::$settings = $values;
    }

    /**
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function setting(string $key, $default = null)
    {
        $value = array_get(static::settings(), $key);

        return ($value === null || $value === '') && $default !== null ? $default : $value;
    }

    /**
     * @param array<string, mixed> $values only the keys to change
     */
    public static function saveSettings(array $values): void
    {
        $plugin = static::plugin();
        if (!isset($plugin)) {
            return;
        }

        $options = (array) $plugin->options;
        $stored = array_get($options, static::SETTINGS_KEY, []);
        $stored = is_array($stored) ? $stored : [];

        foreach ($values as $key => $value) {
            if (!array_key_exists($key, static::DEFAULTS)) {
                continue;
            }
            $value = is_array($value) ? implode(',', $value) : strval($value ?? '');
            if (in_array($key, static::SECRET_SETTINGS, true)) {
                $value = $value === '' ? '' : Crypt::encryptString($value);
            }
            $stored[$key] = $value;
        }

        $options[static::SETTINGS_KEY] = $stored;
        $plugin->options = $options;
        $plugin->save();
        static::forget();
    }

    /**
     * @return array<string, mixed>
     */
    public static function state(): array
    {
        $plugin = static::plugin();
        $state = isset($plugin) ? array_get((array) $plugin->options, static::STATE_KEY, []) : [];

        return is_array($state) ? $state : [];
    }

    /**
     * @param array<string, mixed> $values
     */
    public static function patchState(array $values): void
    {
        $plugin = static::plugin();
        if (!isset($plugin)) {
            return;
        }
        $options = (array) $plugin->options;
        $options[static::STATE_KEY] = array_merge(static::state(), $values);
        $plugin->options = $options;
        $plugin->save();
        static::forget();
    }

    protected static function decrypt(string $value): string
    {
        if ($value === '') {
            return '';
        }
        try {
            return Crypt::decryptString($value);
        } catch (\Throwable $e) {
            // Written before encryption, or under another app key.
            return '';
        }
    }

    // ---- tables ----------------------------------------------------------

    public static function table(string $name): ?CustomTable
    {
        return CustomTable::getEloquent($name);
    }

    public static function isInstalled(): bool
    {
        return boolval(array_get(static::state(), 'installed')) && static::table(static::T_TICKET) !== null;
    }

    // ---- urls ------------------------------------------------------------

    /** Agent screens (behind login). */
    public static function url(string ...$parts): string
    {
        $plugin = static::plugin();
        if (!isset($plugin)) {
            return admin_url('plugins/exment_it_support/' . implode('/', $parts));
        }

        return $plugin->getFullUrl(...$parts);
    }

    /** Public screens (no login). See PublicRoutes. */
    public static function publicUrl(string ...$parts): string
    {
        return PublicRoutes::url(...$parts);
    }

    /** The blade namespace Exment gives this plugin's resources/views. */
    public static function view(string $name): string
    {
        return 'exment_' . snake_case(static::PLUGIN_NAME) . '::' . $name;
    }

    /** Exment's own data screen for one of the plugin's tables. */
    public static function dataUrl(string $table, ...$parts): string
    {
        return admin_url(implode('/', array_merge(['data', $table], array_map('strval', $parts))));
    }

    // ---- small helpers ---------------------------------------------------

    public static function label(array $map, $key): string
    {
        return strval($map[strval($key)] ?? strval($key));
    }

    /**
     * Lower-cased bare address out of "Name <a@b>".
     */
    public static function normalizeEmail(?string $value): string
    {
        $value = trim(strval($value));
        if (preg_match('/<([^>]+)>/', $value, $m)) {
            $value = $m[1];
        }

        return mb_strtolower(trim($value));
    }

    public static function isEmail(?string $value): bool
    {
        return filter_var(strval($value), FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Something in the text that looks like a password being handed over.
     * Passwords do not belong in a ticket (EXMENT_CORE ISS00152): the screen
     * warns, it does not block, because the customer already sent it.
     */
    public static function looksLikePassword(?string $text): bool
    {
        return preg_match('/(パスワード|password|passwd|pwd|暗証番号)\s*[:：=は]\s*\S{4,}/iu', strval($text)) === 1;
    }
}
