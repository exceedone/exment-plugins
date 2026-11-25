<?php

namespace App\Plugins\ExmentPM;

/**
 * The manual of this plugin on exment.net (ISS02882), in the language of the user: ja and vi
 * have their own pages, every other language the English one.
 *
 * - the link 「？ マニュアル」 of the plugin's own screens (ManualLink::url()),
 * - Exment's "?" of the header on the plugin's pages (register(): CoreExtensions::addHelpUrls,
 *   Exment 7.2.4 and later; nothing on an older Exment).
 */
class ManualLink
{
    const BASE = 'https://exment.net/docs/';
    const PAGE = 'plugin_pm';
    const LOCALES = ['ja', 'vi'];

    public static function url(?string $locale = null): string
    {
        $locale = $locale ?? strval(app()->getLocale());
        $dir = in_array($locale, static::LOCALES, true) ? $locale . '/' : '';
        return static::BASE . $dir . static::PAGE . '.html';
    }

    /**
     * Exment's "?" of the header opens this manual on the pages of the plugin.
     *
     * @param \Exceedone\Exment\Model\Plugin $plugin
     */
    public static function register($plugin): void
    {
        $extensions = '\Exceedone\Exment\Services\CoreExtensions';
        if (!class_exists($extensions) || !method_exists($extensions, 'addHelpUrls')) {
            return;
        }
        try {
            call_user_func([$extensions, 'addHelpUrls'], [['uri' => $plugin->getRouteUri(), 'help_uri' => static::url()]]);
        } catch (\Throwable $e) {
            \Log::warning('ExmentPM: the manual of the header: ' . $e->getMessage());
        }
    }

    /** The text of the link. */
    public static function label(?string $locale = null): string
    {
        $locale = $locale ?? strval(app()->getLocale());
        $labels = ['ja' => 'マニュアル', 'vi' => 'Hướng dẫn', 'de' => 'Handbuch', 'es' => 'Manual', 'ko' => '매뉴얼', 'zh_CN' => '手册'];
        return $labels[$locale] ?? 'Manual';
    }
}
