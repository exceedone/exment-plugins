<?php

namespace App\Plugins\ExmentITSupport;

/**
 * IT Support in Exment's feature management (Admin setting → Feature
 * management, in the Exment versions that have it). Two doors:
 *
 * - desk:   the agents' screens (inbox, dashboard, settings, setup), the
 *           batch (mail, SLA, reminders, closing, assets) and the ticket
 *           rules on save. Who may use it is chosen there.
 * - portal: the ways in for customers: /support (no login), the links in
 *           the mails, the mailbox and /support/intake/mail, the alerts
 *           (/support/intake/alert), and "my requests" inside Exment. It
 *           needs the desk: nobody would answer.
 *
 * A feature is the door; the switches in this plugin's settings (receive
 * mail, use the customer pages, ...) are the details behind it, and both
 * must be on.
 *
 * /support is outside the admin pages, so Exment cannot stop it by its path;
 * PublicController asks on() itself. In Exment versions without feature
 * management every door counts as open, as before.
 */
class Feature
{
    const DESK = 'exment_it_support.desk';
    const PORTAL = 'exment_it_support.portal';

    const CORE = '\\Exceedone\\Exment\\Services\\Features';

    public static function available(): bool
    {
        return class_exists(static::CORE);
    }

    /**
     * Called from PluginPage's constructor. Order matters: Exment takes the
     * first feature whose endpoint matches, so "my requests" comes before
     * the desk, which covers the whole plugin.
     *
     * @param \Exceedone\Exment\Model\Plugin $plugin
     */
    public static function register($plugin): void
    {
        if (!static::available()) {
            return;
        }
        $core = static::CORE;
        $base = $plugin->getRouteUri();
        $core::register(static::PORTAL, [
            'label' => 'ITサポート：お客様の窓口 / IT Support: customer portal',
            'description' => 'ログインなしの窓口（/support）・メールのリンク・メールと監視からの受付・「自分の依頼」。受付箱がオンのときだけ動きます。'
                . ' / The no-login portal, links in mails, mail and alert intake, "my requests". Needs the desk.',
            'endpoints' => [$base . '/my'],
            'plugin' => $plugin->plugin_name,
            'requires' => static::DESK,
            'who' => false,
        ]);
        $core::register(static::DESK, [
            'label' => 'ITサポート：受付箱 / IT Support: desk',
            'description' => '担当者の画面（受付箱・ダッシュボード・設定・セットアップ）と 5 分ごとの処理。セットアップの前にオンにします。'
                . ' / The agents\' screens and the batch. Turn it on before the setup.',
            'endpoints' => [$base],
            'plugin' => $plugin->plugin_name,
        ]);
    }

    /** Whether the door is open on the site (the portal needs the desk). */
    public static function on(string $key): bool
    {
        if (!static::available()) {
            return true;
        }
        $core = static::CORE;
        if ($key === static::PORTAL && !$core::enabled(static::DESK)) {
            return false;
        }

        return $core::enabled($key);
    }

    /** The page of Exment's feature management (null: none). */
    public static function manageUrl(): ?string
    {
        return static::available() ? admin_url('feature') : null;
    }
}
