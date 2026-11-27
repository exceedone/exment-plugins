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
    /** 「ExmentPM の課題にする」 (ISS02941): new, off at first. */
    const PM_LINK = 'exment_it_support.pm_link';
    /** The website forms of the 受付箱 (ISS02941): new, off at first. */
    const WEB_FORM = 'exment_it_support.web_form';
    /** The chat window of a box on a website (ISS02941): new, off at first. */
    const WEB_CHAT = 'exment_it_support.web_chat';
    /** The quote of the sales pack and the trial key of a ticket (ISS02941): new, off at first. */
    const SALES_LINK = 'exment_it_support.sales_link';

    const CORE = '\\Exceedone\\Exment\\Services\\Features';

    public static function available(): bool
    {
        return class_exists(static::CORE);
    }

    /**
     * Called from PluginPage's constructor. The names are keys of
     * resources/lang, translated when Exment shows them.
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
        $lang = 'exment_' . snake_case($plugin->plugin_name) . '::its.feature.';
        // existing: a site that had the plugin before feature management keeps it on.
        $core::register(static::PORTAL, [
            'label' => $lang . 'portal',
            'description' => $lang . 'portal_help',
            'endpoints' => [$base . '/my'],
            'plugin' => $plugin->plugin_name,
            'requires' => static::DESK,
            'who' => false,
            'existing' => true,
        ]);
        $core::register(static::SALES_LINK, [
            'label' => $lang . 'sales_link',
            'description' => $lang . 'sales_link_help',
            'endpoints' => [],
            'plugin' => $plugin->plugin_name,
            'requires' => static::DESK,
            'existing' => false,
        ]);
        $core::register(static::PM_LINK, [
            'label' => $lang . 'pm_link',
            'description' => $lang . 'pm_link_help',
            'endpoints' => [],
            'plugin' => $plugin->plugin_name,
            'requires' => static::DESK,
            'existing' => false,
        ]);
        $core::register(static::WEB_CHAT, [
            'label' => $lang . 'web_chat',
            'description' => $lang . 'web_chat_help',
            'endpoints' => [],
            'plugin' => $plugin->plugin_name,
            'requires' => static::DESK,
            'existing' => false,
        ]);
        $core::register(static::WEB_FORM, [
            'label' => $lang . 'web_form',
            'description' => $lang . 'web_form_help',
            // none: the boxes page says why there is no button, and the POST answers 404 itself
            'endpoints' => [],
            'plugin' => $plugin->plugin_name,
            'requires' => static::DESK,
            'existing' => false,
        ]);
        $core::register(static::DESK, [
            'label' => $lang . 'desk',
            'description' => $lang . 'desk_help',
            'endpoints' => [$base],
            'plugin' => $plugin->plugin_name,
            'existing' => true,
        ]);
    }

    /** Whether the door is open on the site (the portal needs the desk). */
    public static function on(string $key): bool
    {
        if (!static::available()) {
            return true;
        }
        $core = static::CORE;
        if (in_array($key, [static::PM_LINK, static::WEB_FORM, static::SALES_LINK, static::WEB_CHAT], true) && !$core::enabled(static::DESK)) {
            return false;
        }
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
