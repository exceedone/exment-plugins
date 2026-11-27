<?php

namespace App\Plugins\ExmentPM;

/**
 * ExmentPM on phones as an installable web app (ISS02939 item 6, step 1): the manifest, the
 * service worker and the offline page. See docs/pwa-design.md.
 *
 * Everything is a route of the plugin, so the service worker's scope is the portal only, built
 * from the real route: /admin/plugins/exment_pm/ or, with an empty admin prefix,
 * /plugins/exment_pm/. The worker keeps no data: pages go to the network (the offline page only
 * when it fails), the portal's versioned assets are cached, nothing else is touched.
 */
class PortalPwa
{
    const FEATURE = 'exment_pm.pwa';
    const FEATURES = 'Exceedone\Exment\Services\Features';
    const ICONS = ['192' => 'icon-192.png', '512' => 'icon-512.png', 'maskable' => 'icon-maskable.png', '180' => 'icon-180.png'];
    const THEME = '#4caf93';
    const BACKGROUND = '#f5f7f9';

    public static function registerFeature(): void
    {
        if (!class_exists(static::FEATURES)) {
            return;
        }
        try {
            call_user_func([static::FEATURES, 'register'], static::FEATURE, [
                'label' => strval(PortalDefinition::trans('pwa.feature')),
                'description' => strval(PortalDefinition::trans('pwa.feature_help')),
                'who' => false,
            ]);
        } catch (\Throwable $e) {
            // an older Exment: the switch is not there
        }
    }

    public static function enabled(): bool
    {
        if (!class_exists(static::FEATURES)) {
            return false;
        }
        try {
            return boolval(call_user_func([static::FEATURES, 'enabled'], static::FEATURE));
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** The portal's address with a slash at the end: the service worker's scope. */
    public static function scope(): string
    {
        return rtrim(PortalDefinition::url(), '/') . '/';
    }

    /** @return array<string, string> what the page needs, from the real route */
    public static function urls(): array
    {
        $scope = static::scope();

        return [
            'scope' => $scope,
            'manifest' => $scope . 'manifest.webmanifest',
            'sw' => $scope . 'sw.js',
            'icon' => $scope . 'pwa-icon/180',
            'theme' => static::THEME,
        ];
    }

    /**
     * The web app manifest. Plain data, no one's data: the site's name and the addresses.
     *
     * @return array<string, mixed>
     */
    public static function manifest(string $scope, string $siteName, string $appName): array
    {
        $path = strval(parse_url($scope, PHP_URL_PATH) ?: '/');
        $name = trim($siteName) !== '' ? trim($siteName) . ' ' . $appName : $appName;

        return [
            'id' => $path,
            'name' => mb_substr($name, 0, 60),
            'short_name' => mb_substr($appName, 0, 12),
            'start_url' => $path . '?source=pwa',
            'scope' => $path,
            'display' => 'standalone',
            'orientation' => 'any',
            'theme_color' => static::THEME,
            'background_color' => static::BACKGROUND,
            'icons' => [
                ['src' => $path . 'pwa-icon/192', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => $path . 'pwa-icon/512', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => $path . 'pwa-icon/maskable', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
        ];
    }

    /**
     * The service worker. Its version changes with this file, the icons and the offline page's
     * words, so the browser takes a new one when they change.
     *
     * @param string $assetPrefixes "|" between: where the portal's versioned assets are
     */
    public static function worker(string $scope, string $assetPrefixes, string $version, string $name = ''): string
    {
        $path = strval(parse_url($scope, PHP_URL_PATH) ?: '/');
        $cfg = json_encode([
            'scope' => $path,
            'offline' => $path . 'offline',
            'icons' => [$path . 'pwa-icon/192', $path . 'pwa-icon/512'],
            'assets' => array_values(array_filter(explode('|', $assetPrefixes), 'strlen')),
            'version' => $version,
            // the title of a Web Push notice that came without one
            'name' => $name,
        ], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        return "/* ExmentPM service worker (ISS02939). No data is kept: see docs/pwa-design.md. */\n"
            . 'var CFG = ' . $cfg . ";\n"
            . file_get_contents(__DIR__ . '/resources/pwa/sw.js');
    }

    /** A hash of what the worker is made from. */
    public static function version(): string
    {
        $parts = [];
        foreach (array_merge(['sw.js'], array_values(static::ICONS)) as $f) {
            $p = __DIR__ . '/resources/pwa/' . $f;
            $parts[] = is_file($p) ? md5_file($p) : '-';
        }
        $parts[] = md5(json_encode(PortalDefinition::trans('pwa')));

        return substr(md5(implode(':', $parts)), 0, 12);
    }

    /** The offline page: no data, the name, a line, a button to try again. */
    public static function offlinePage(string $appName, string $title, string $line, string $retry): string
    {
        $e = function ($s) {
            return htmlspecialchars(strval($s), ENT_QUOTES, 'UTF-8');
        };

        return '<!doctype html><html lang="' . $e(app()->getLocale()) . '"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">'
            . '<meta name="theme-color" content="' . static::THEME . '"><title>' . $e($appName) . '</title>'
            . '<style>html,body{margin:0;height:100%;font-family:system-ui,-apple-system,"Segoe UI","Hiragino Sans","Noto Sans JP",sans-serif;background:' . static::BACKGROUND . ';color:#24323f}'
            . 'main{min-height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:24px;box-sizing:border-box;text-align:center}'
            . 'h1{font-size:20px;margin:16px 0 8px}p{margin:0 0 20px;color:#6b7a88;line-height:1.6}'
            . 'button{font:inherit;padding:10px 22px;border:0;border-radius:6px;background:' . static::THEME . ';color:#fff;min-height:44px}</style></head>'
            . '<body><main><svg width="56" height="56" viewBox="0 0 24 24" aria-hidden="true"><path fill="#9aa7b3" d="M2.3 4.7 3.7 3.3l17 17-1.4 1.4-3.1-3.1H7a5 5 0 0 1-1.2-9.9L2.3 4.7Zm5.5 5.5A3 3 0 0 0 7 16.5h7.2L7.8 10.2ZM12 4a7 7 0 0 1 6.7 5 4.5 4.5 0 0 1 2.6 7.7l-1.4-1.4A2.5 2.5 0 0 0 18.5 11H17l-.2-1A5 5 0 0 0 9 7.1L7.6 5.7A7 7 0 0 1 12 4Z"/></svg>'
            . '<h1>' . $e($title) . '</h1><p>' . $e($line) . '</p>'
            . '<button type="button" onclick="location.reload()">' . $e($retry) . '</button></main></body></html>';
    }

    /** The bytes of an icon, or null. */
    public static function icon(string $name): ?string
    {
        $file = static::ICONS[$name] ?? null;
        $path = $file !== null ? __DIR__ . '/resources/pwa/' . $file : null;

        return $path !== null && is_file($path) ? strval(file_get_contents($path)) : null;
    }
}
