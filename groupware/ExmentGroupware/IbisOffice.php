<?php

namespace App\Plugins\ExmentGroupware;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * ibisOffice (ibis inc., AGPL-3.0-only / commercial): the browser Office the
 * Groupware opens the Word, Excel and PowerPoint files in.
 *
 * It is a set of static files. The plugin does not carry them (they are
 * about 120 MB): a system administrator installs the pinned version from the
 * release of ibisOffice on GitHub, checked by its SHA-256, into the public
 * folder of the site, so that the editor is served from the same origin as
 * Exment. Or the address of an ibisOffice served elsewhere is set in the
 * settings of the plugin. ibisOffice is used as it is released (not modified)
 * and runs in its own iframe; it talks to Exment only through the functions
 * the page hands it.
 *
 * https://github.com/ibisjp/ibis-office/blob/main/docs/web-embedding.md
 */
class IbisOffice
{
    public const VERSION = '0.8.9';
    public const SHA256 = 'fcffb71b0ffaf9f4273de8c430258e8441c5ec79d8c79c321d1358a342a4fc4c';
    public const RELEASE_URL = 'https://github.com/ibisjp/ibis-office/releases/download/v%1$s/ibisOffice-%1$s-Web.zip';
    public const PROJECT_URL = 'https://github.com/ibisjp/ibis-office';
    public const PUBLIC_DIR = 'vendor/exment_groupware/ibisoffice';

    /** @var string[] the extensions it opens */
    public const EXTENSIONS = ['docx', 'xlsx', 'pptx'];

    /** @var \Exceedone\Exment\Model\Plugin|null */
    protected $plugin;

    /**
     * @param \Exceedone\Exment\Model\Plugin|null $plugin
     */
    public function __construct($plugin = null)
    {
        $this->plugin = $plugin;
    }

    public function installDir(): string
    {
        return public_path(static::PUBLIC_DIR . '/' . static::VERSION);
    }

    public function isInstalled(): bool
    {
        return is_file($this->installDir() . '/ibisoffice-host.js') && is_file($this->installDir() . '/index.html');
    }

    /**
     * The address set in the settings (an ibisOffice served elsewhere), or null.
     */
    public function customUrl(): ?string
    {
        if (!isset($this->plugin)) {
            return null;
        }
        $url = trim(strval($this->plugin->getCustomOption('ibisoffice_url')));
        if ($url === '' || preg_match('#\Ahttps?://#i', $url) !== 1) {
            return null;
        }
        return rtrim($url, '/') . '/';
    }

    public function isAvailable(): bool
    {
        return $this->customUrl() !== null || $this->isInstalled();
    }

    /**
     * The address of the folder of the editor (ends with "/").
     */
    public function baseUrl(): string
    {
        return $this->customUrl() ?? rtrim(asset(static::PUBLIC_DIR . '/' . static::VERSION), '/') . '/';
    }

    /**
     * The origin of the editor when it is served from another origin than
     * Exment (then the page talks to it through messages), or null.
     */
    public function officeOrigin(): ?string
    {
        $custom = $this->customUrl();
        if ($custom === null) {
            return null;
        }
        $parts = parse_url($custom);
        if (!isset($parts['scheme'], $parts['host'])) {
            return null;
        }
        $origin = strtolower($parts['scheme']) . '://' . strtolower($parts['host']) . (isset($parts['port']) ? ':' . $parts['port'] : '');
        $own = request()->getSchemeAndHttpHost();
        return strtolower($origin) === strtolower($own) ? null : $origin;
    }

    public static function opens(string $name): bool
    {
        return in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), static::EXTENSIONS, true);
    }

    /**
     * Download the pinned version, check its SHA-256 and put it into the public folder.
     *
     * @throws \RuntimeException
     */
    public function install(): void
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('The zip extension of PHP is needed.');
        }
        $work = storage_path('app/exment_groupware_tmp/' . Str::random(16));
        if (!@mkdir($work, 0777, true) && !is_dir($work)) {
            throw new \RuntimeException('Cannot make ' . $work);
        }
        try {
            $zipPath = $work . '/ibisoffice.zip';
            $response = Http::timeout(600)->withOptions(['sink' => $zipPath])->get(sprintf(static::RELEASE_URL, static::VERSION));
            if (!$response->successful() || !is_file($zipPath)) {
                throw new \RuntimeException('The download failed (HTTP ' . $response->status() . ').');
            }
            if (!hash_equals(static::SHA256, strval(hash_file('sha256', $zipPath)))) {
                throw new \RuntimeException('The SHA-256 of the download does not match. It was not installed.');
            }

            $out = $work . '/app';
            $zip = new \ZipArchive();
            if ($zip->open($zipPath) !== true) {
                throw new \RuntimeException('Cannot open the zip.');
            }
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = strval($zip->getNameIndex($i));
                // nothing outside the folder
                if ($name === '' || str_starts_with($name, '/') || str_contains($name, '\\') || preg_match('#(^|/)\.\.(/|$)#', $name) === 1) {
                    $zip->close();
                    throw new \RuntimeException('The zip has a bad path: ' . $name);
                }
            }
            $zip->extractTo($out);
            $zip->close();
            if (!is_file($out . '/ibisoffice-host.js')) {
                throw new \RuntimeException('The zip has no ibisoffice-host.js.');
            }

            $dest = $this->installDir();
            if (is_dir($dest)) {
                File::deleteDirectory($dest);
            }
            if (!@mkdir(dirname($dest), 0777, true) && !is_dir(dirname($dest))) {
                throw new \RuntimeException('Cannot make ' . dirname($dest));
            }
            if (!@rename($out, $dest)) {
                if (!File::copyDirectory($out, $dest)) {
                    throw new \RuntimeException('Cannot write ' . $dest);
                }
            }
        } finally {
            File::deleteDirectory($work);
        }
    }
}
