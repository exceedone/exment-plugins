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

    /**
     * The address of ibisoffice-host.js. This script runs in Exment's own
     * page (it can do what the user can do), so the copy installed on this
     * site is used whenever there is one, and one from elsewhere only over
     * https.
     */
    public function hostModuleUrl(): ?string
    {
        if ($this->isInstalled()) {
            return rtrim(asset(static::PUBLIC_DIR . '/' . static::VERSION), '/') . '/ibisoffice-host.js';
        }
        $custom = $this->customUrl();
        if ($custom === null || stripos($custom, 'https://') !== 0) {
            return null;
        }

        return $custom . 'ibisoffice-host.js';
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

    /** Free space needed in public/ to install (the release unpacks to ~125MB, plus its .gz copies). */
    public const NEEDED_BYTES = 180 * 1024 * 1024;

    /**
     * What would stop an install here, in words for the administrator; empty when
     * nothing is known to. Cheap (no network): shown on every page that offers the
     * install. Whether GitHub can be reached is only known when the install tries.
     *
     * @return string[]
     */
    public function problems(): array
    {
        $problems = [];
        if (!class_exists(\ZipArchive::class)) {
            $problems[] = 'PHP の zip の拡張がありません（php-zip を入れてください）。';
        }
        // the folder it goes into, or the nearest one that is there
        $dir = dirname($this->installDir());
        while (!is_dir($dir) && dirname($dir) !== $dir) {
            $dir = dirname($dir);
        }
        if (!is_writable($dir)) {
            $problems[] = 'サイトの public/ に書き込めません（' . $dir . '）。Web サーバーのユーザーが書けるようにしてください。';
        } else {
            $free = @disk_free_space($dir);
            if (is_float($free) && $free < static::NEEDED_BYTES) {
                $problems[] = 'public/ のあるディスクの空きが足りません（約 180MB 要ります。今 ' . intval($free / 1048576) . 'MB）。';
            }
        }
        return $problems;
    }

    /** The way round when it cannot be installed here. */
    public const ELSEWHERE = '入れられないときは、展開した ibisOffice をほかの場所（https）に置き、プラグインの設定の「ibisOffice の場所（URL）」にその URL を入れても使えます。';

    /** The files a .gz copy is written for: what the browser fetches and compresses well. */
    public const GZIP_EXTENSIONS = ['wasm', 'js', 'mjs', 'css', 'json', 'html', 'svg', 'txt', 'map'];

    /** Smaller files are not worth a copy. */
    public const GZIP_MIN_BYTES = 1024;

    /**
     * Write file.gz beside each file worth compressing, streamed so the ~90MB
     * .wasm does not have to fit in PHP's memory. A copy that cannot be
     * written is left out: the server then compresses on the fly, or not.
     */
    public static function writeGzipCopies(string $dir): int
    {
        $count = 0;
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            /** @var \SplFileInfo $file */
            $path = $file->getPathname();
            if (!$file->isFile() || !in_array(strtolower($file->getExtension()), static::GZIP_EXTENSIONS, true) || $file->getSize() < static::GZIP_MIN_BYTES) {
                continue;
            }
            $in = @fopen($path, 'rb');
            $gz = @gzopen($path . '.gz', 'wb9');
            if ($in === false || $gz === false) {
                if ($in !== false) {
                    fclose($in);
                }
                continue;
            }
            $ok = true;
            while (!feof($in)) {
                $chunk = fread($in, 1048576);
                if ($chunk === false || ($chunk !== '' && gzwrite($gz, $chunk) === false)) {
                    $ok = false;
                    break;
                }
            }
            fclose($in);
            gzclose($gz);
            if ($ok) {
                @touch($path . '.gz', intval(filemtime($path)));
                $count++;
            } else {
                @unlink($path . '.gz');
            }
        }
        return $count;
    }

    /**
     * An .htaccess for Apache (rental servers such as XServer): the .wasm type,
     * a year's cache (each version has its own folder), and the .gz copies
     * sent to browsers that take gzip - or mod_deflate when they are missing.
     * Every rule sits in an IfModule, so a server without that module simply
     * skips it. nginx does not read it; see the README for nginx.
     */
    public static function writeHtaccess(string $dir): void
    {
        $rules = <<<'HTACCESS'
# Written by Exment Plugin Groupware when it installed ibisOffice.
# The editor is large (the .wasm is ~90MB): send it compressed and let the browser keep it.
# Each version has its own folder, so a year's cache is safe.

<IfModule mod_mime.c>
    AddType application/wasm .wasm
    AddType text/javascript .js .mjs
</IfModule>

<IfModule mod_headers.c>
    Header set Cache-Control "public, max-age=31536000, immutable"
</IfModule>

# The .gz copies written at install, to browsers that accept gzip.
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{HTTP:Accept-Encoding} gzip
    RewriteCond %{REQUEST_FILENAME}.gz -f
    RewriteRule ^(.+\.(wasm|js|mjs|css|json|html|svg|txt|map))$ $1.gz [L]
    # never compress a .gz again
    RewriteRule \.gz$ - [E=no-gzip:1,E=dont-vary:1]
</IfModule>
<FilesMatch "\.gz$">
    <IfModule mod_headers.c>
        Header set Content-Encoding gzip
        Header append Vary Accept-Encoding
    </IfModule>
</FilesMatch>
<IfModule mod_mime.c>
    <FilesMatch "\.wasm\.gz$">
        ForceType application/wasm
    </FilesMatch>
    <FilesMatch "\.m?js\.gz$">
        ForceType text/javascript
    </FilesMatch>
    <FilesMatch "\.css\.gz$">
        ForceType text/css
    </FilesMatch>
    <FilesMatch "\.(json|map)\.gz$">
        ForceType application/json
    </FilesMatch>
    <FilesMatch "\.html\.gz$">
        ForceType "text/html; charset=utf-8"
    </FilesMatch>
    <FilesMatch "\.svg\.gz$">
        ForceType image/svg+xml
    </FilesMatch>
    <FilesMatch "\.txt\.gz$">
        ForceType "text/plain; charset=utf-8"
    </FilesMatch>
</IfModule>

# Without the copies (or without mod_rewrite): compress on the fly.
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE application/wasm text/javascript application/javascript text/css application/json text/html image/svg+xml text/plain
</IfModule>
HTACCESS;
        @file_put_contents($dir . '/.htaccess', $rules);
    }

    /**
     * Download the pinned version, check its SHA-256 and put it into the public folder.
     *
     * @throws \RuntimeException
     */
    public function install(): void
    {
        $problems = $this->problems();
        if (!empty($problems)) {
            throw new \RuntimeException(implode(' ', $problems));
        }
        $work = storage_path('app/exment_groupware_tmp/' . Str::random(16));
        if (!@mkdir($work, 0777, true) && !is_dir($work)) {
            throw new \RuntimeException('Cannot make ' . $work);
        }
        try {
            $zipPath = $work . '/ibisoffice.zip';
            try {
                $response = Http::timeout(600)->connectTimeout(15)->withOptions(['sink' => $zipPath])->get(sprintf(static::RELEASE_URL, static::VERSION));
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                throw new \RuntimeException('GitHub（github.com）につながりません。サーバーから外へつながるか（プロキシ・ファイアウォール）を確かめてください。' . static::ELSEWHERE);
            }
            if (!$response->successful() || !is_file($zipPath)) {
                throw new \RuntimeException('ダウンロードできませんでした（HTTP ' . $response->status() . '）。' . static::ELSEWHERE);
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
            // compressed copies, for a web server that serves them as they are
            // (nginx gzip_static, the .htaccess below): the .wasm is ~90MB, ~24MB gzipped
            static::writeGzipCopies($out);
            if (!@rename($out, $dest)) {
                if (!File::copyDirectory($out, $dest)) {
                    throw new \RuntimeException('Cannot write ' . $dest);
                }
            }
            static::writeHtaccess(dirname($dest));
        } finally {
            File::deleteDirectory($work);
        }
    }
}
