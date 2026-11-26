<?php

namespace App\Plugins\ExmentGroupware;

/**
 * Exment's Feature management (Admin setting → Feature management), in the
 * versions of Exment that have it: a feature is off until a system
 * administrator turns it on, and they choose the role groups that may use it.
 *
 * - The web mail is a feature of this plugin (MAIL, registered when the
 *   plugin is made).
 * - The Office part opens the files of the file server, a feature of Exment
 *   itself (FILE_SERVER): it follows it.
 *
 * Exment turns away the pages of a feature not used (404) and hides their
 * menus. The plugin checks too (allowed()), for the versions of Exment that
 * check before the plugin has registered its feature (the routes that skip
 * admin.bootstrap). On an Exment without Feature management everything is
 * used, as before.
 */
class FeatureGate
{
    const MAIL = 'exment_groupware.mail';
    const OFFICE = 'exment_groupware.office';
    /** The chat (ISS02945): new, off at first. */
    const CHAT = 'exment_groupware.chat';
    const FILE_SERVER = 'file_server';

    // a string, not ::class: an Exment without Feature management does not have the class
    protected const FEATURES = 'Exceedone\Exment\Services\Features';

    /** Whether Exment has Feature management. */
    public static function available(): bool
    {
        return class_exists(static::FEATURES);
    }

    /** Whether the login user may use the feature (true without Feature management). */
    public static function allowed(string $key): bool
    {
        if (!static::available()) {
            return true;
        }
        return boolval(call_user_func([static::FEATURES, 'allowed'], $key));
    }

    /**
     * The answer when the login user may not use the feature, or null when
     * they may: Exment's own answer (Features::deny: 404 when it is not used,
     * with the link to Feature management for a system administrator, 403
     * when the user may not use it, JSON for ajax). It is returned, not
     * thrown: Exment shows an exception of an admin page as its error page.
     * abort(404) when Exment does not have Features::deny.
     *
     * @return \Symfony\Component\HttpFoundation\Response|null
     */
    public static function deny(string $key)
    {
        if (static::allowed($key)) {
            return null;
        }
        if (method_exists(static::FEATURES, 'deny')) {
            return call_user_func([static::FEATURES, 'deny'], $key);
        }
        abort(404);
    }

    /**
     * The web mail: its pages (and the API of the mail program, which are web
     * routes under them) are plugins/<uri>/mail.
     *
     * @param \Exceedone\Exment\Model\Plugin $plugin
     */
    public static function registerMail($plugin): void
    {
        if (!static::available()) {
            return;
        }
        call_user_func([static::FEATURES, 'register'], static::MAIL, [
            'label' => static::text('feature_mail', 'メール'),
            'description' => static::text('feature_mail_help', 'ブラウザーで、今お使いのメール（Xserver・KAGOYA・Microsoft 365・Gmail・そのほかの IMAP / SMTP のサーバー）を読む・書く・送る（Exment Plugin Groupware）。'),
            'endpoints' => [$plugin->getRouteUri('mail')],
            'plugin' => $plugin->plugin_name,
            // the plugin had the mail before Feature management: it stays on in the sites
            // that had the plugin then (a new site starts with it off)
            'existing' => true,
        ]);
    }

    /**
     * Office (ISS02827): a row of its own in Feature management. While ibisOffice is
     * not there, the row says what it needs and gives the button to install it (an
     * Exment whose Feature management has no buttons shows the words only). Its pages
     * stop when it is turned off. It stays on in the sites that had the plugin before.
     */
    public static function registerOffice($plugin): void
    {
        if (!static::available()) {
            return;
        }
        $office = new IbisOffice($plugin);
        $missing = !$office->isAvailable() && $office->customUrl() === null;
        $update = $office->customUrl() === null && $office->updateAvailable();
        $description = 'ブラウザーで Word・Excel・PowerPoint を開く・編集する（ファイル共有とデータの添付。Exment Plugin Groupware の ibisOffice）。';
        if (!$missing) {
            if ($office->customUrl() !== null) {
                $description .= ' ほかの場所の ibisOffice を使います。';
            } elseif ($update) {
                $description .= ' ibisOffice ' . $office->activeVersion() . ' がこのサイトに入っています。新しい版 ' . IbisOffice::VERSION . ' があります（更新するまでは、今の版で開きます）。';
            } else {
                $description .= ' ibisOffice ' . IbisOffice::VERSION . ' がこのサイトに入っています。';
            }
        } else {
            $description .= ' ibisOffice がまだ入っていません。入れるには、PHP の zip の拡張と、サイトの public/ に約 125MB の空きが要ります（LibreOffice は要りません）。';
            $problems = $office->problems();
            if (!empty($problems)) {
                $description .= ' 今は入れられません: ' . implode(' ', $problems);
            }
        }
        $definition = [
            'label' => static::text('feature_office', 'Office（ibisOffice）'),
            'description' => $description,
            'endpoints' => [$plugin->getRouteUri('office'), $plugin->getRouteUri('view'), $plugin->getRouteUri('edit'), $plugin->getRouteUri('blank')],
            'plugin' => $plugin->plugin_name,
            'existing' => true,
            'manual' => 'plugin_groupware?id=office',
        ];
        if ($missing) {
            $definition['action'] = ['label' => 'ibisOffice を入れる', 'url' => $plugin->getFullUrl()];
        } elseif ($update) {
            $definition['action'] = ['label' => 'ibisOffice を ' . IbisOffice::VERSION . ' に更新する', 'url' => $plugin->getFullUrl()];
        }
        call_user_func([static::FEATURES, 'register'], static::OFFICE, $definition);
    }

    /** The chat (ISS02945): its page and its API are plugins/<uri>/chat. Off at first. */
    public static function registerChat($plugin): void
    {
        if (!static::available()) {
            return;
        }
        call_user_func([static::FEATURES, 'register'], static::CHAT, [
            'label' => static::text('feature_chat', 'チャット'),
            'description' => static::text('feature_chat_help', 'グループと 1 対 1 のルームで話す。@メンション・既読の人数・ファイル（Exment Plugin Groupware）。'),
            'endpoints' => [$plugin->getRouteUri('chat')],
            'plugin' => $plugin->plugin_name,
            'existing' => false,
        ]);
    }

    /**
     * The key of a text of the plugin's language files (resources/lang):
     * Exment translates it when it shows the page, in the language of the
     * viewer. The Japanese text when Exment does not load those files.
     */
    protected static function text(string $key, string $default): string
    {
        $name = 'exment_exment_groupware::groupware.' . $key;
        return class_exists('Illuminate\Support\Facades\Lang') && \Illuminate\Support\Facades\Lang::has($name) ? $name : $default;
    }
}
