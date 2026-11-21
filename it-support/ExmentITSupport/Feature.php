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
