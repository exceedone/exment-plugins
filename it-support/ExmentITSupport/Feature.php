<?php

namespace App\Plugins\ExmentITSupport;

/**
 * The parts of IT Support in Exment's feature management (Admin setting →
 * Feature management, Exment 7.1.3 and later). A part that is not turned on
 * there is not used: its pages answer "not found" and the batch skips it.
 *
 * - desk:   the agents' screens (inbox, dashboard, settings, setup) and the
 *           batch (SLA, reminders, closing, assets)
 * - portal: the pages for customers and employees: /support (no login), the
 *           links in the mails, and "my requests" inside Exment
 * - mail:   reading the mailbox, and /support/intake/mail
 * - alerts: tickets from monitoring (/support/intake/alert)
 *
 * /support is outside the admin pages, so Exment cannot stop it by its path;
 * PublicController asks enabled() itself. Before 7.1.3 (no feature
 * management) every part counts as on, as it did.
 */
class Feature
{
    const DESK = 'exment_it_support.desk';
    const PORTAL = 'exment_it_support.portal';
    const MAIL = 'exment_it_support.mail';
    const ALERTS = 'exment_it_support.alerts';

    const CORE = '\\Exceedone\\Exment\\Services\\Features';

    public static function available(): bool
    {
        return class_exists(static::CORE);
    }

    /**
     * Called from PluginPage's constructor. Order matters: Exment takes the
     * first feature whose endpoint matches, so the narrower paths come before
     * the desk's, which covers the whole plugin.
     */
    public static function register(): void
    {
        if (!static::available()) {
            return;
        }
        $core = static::CORE;
        $base = 'plugins/' . Its::PLUGIN_URI;
        $core::register(static::PORTAL, [
            'label' => 'ITサポート：窓口',
            'description' => 'お客様・社員の窓口（ログインなしの /support、メールの進み具合のリンク、Exment の「自分の依頼」）。',
            'endpoints' => [$base . '/my'],
            'plugin' => Its::PLUGIN_NAME,
        ]);
        $core::register(static::MAIL, [
            'label' => 'ITサポート：メール受付',
            'description' => 'メールボックスを読んでチケットにする、メールの取り込み口（/support/intake/mail）。',
            'endpoints' => [$base . '/settings/fetch-now'],
            'plugin' => Its::PLUGIN_NAME,
        ]);
        $core::register(static::ALERTS, [
            'label' => 'ITサポート：監視からの起票',
            'description' => '監視のアラートをチケットにする取り込み口（/support/intake/alert）。同じアラートは 1 件にまとめる。',
            'endpoints' => [],
            'plugin' => Its::PLUGIN_NAME,
        ]);
        $core::register(static::DESK, [
            'label' => 'ITサポート：受付箱',
            'description' => '担当者の画面（受付箱・ダッシュボード・設定・セットアップ）と、5 分ごとの処理（SLA・返事待ち・自動の完了・IT 資産の期限）。',
            'endpoints' => [$base],
            'plugin' => Its::PLUGIN_NAME,
        ]);
    }

    /** Whether the part is used on the site. */
    public static function enabled(string $key): bool
    {
        if (!static::available()) {
            return true;
        }
        $core = static::CORE;

        return $core::enabled($key);
    }

    /**
     * Whether the user (the login user by default) may use the part.
     *
     * @param mixed $user
     */
    public static function allowed(string $key, $user = null): bool
    {
        if (!static::available()) {
            return true;
        }
        $core = static::CORE;

        return $core::allowed($key, $user);
    }
}
