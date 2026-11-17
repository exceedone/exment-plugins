<?php

namespace App\Plugins\PmMigrate;

/**
 * The folder a migration bundle is built in and read from.
 *
 *   manifest.json        what was exported, from where, with which users
 *   data/<name>.ndjson   one JSON row per line
 *   files/<uuid>         the bytes of one row of the files table
 *
 * Rows are kept as the database returned them (value columns stay JSON text),
 * so nothing is re-encoded on the way and a round trip is byte-stable.
 */
class Bundle
{
    const MANIFEST = 'manifest.json';
    const DATA_DIR = 'data';
    const FILE_DIR = 'files';

    /** @var string */
    protected $dir;

    /** @var array<string, resource> */
    protected $handles = [];

    /** @var array<string, int> */
    protected $counts = [];

    public function __construct(string $dir)
    {
        $this->dir = rtrim($dir, '/\\');
    }

    public function dir(): string
    {
        return $this->dir;
    }

    public function dataPath(string $name): string
    {
        return $this->dir . DIRECTORY_SEPARATOR . static::DATA_DIR . DIRECTORY_SEPARATOR . $name . '.ndjson';
    }

    // ---- writing --------------------------------------------------------

    /**
     * @param string $name dataset name, a-z 0-9 _ only
     * @param array<string, mixed> $row
     * @return void
     */
    public function append(string $name, array $row): void
    {
        if (!isset($this->handles[$name])) {
            if (!is_dir($this->dir . DIRECTORY_SEPARATOR . static::DATA_DIR)) {
                mkdir($this->dir . DIRECTORY_SEPARATOR . static::DATA_DIR, 0700, true);
            }
            $handle = fopen($this->dataPath($name), 'wb');
            if ($handle === false) {
                throw new MigrateException('作業ファイルを作成できません。');
            }
            $this->handles[$name] = $handle;
            $this->counts[$name] = 0;
        }

        fwrite($this->handles[$name], json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
        $this->counts[$name]++;
    }

    /**
     * @return array<string, int> dataset name => rows written
     */
    public function counts(): array
    {
        return $this->counts;
    }

    /**
     * @param array<string, mixed> $manifest
     * @return void
     */
    public function writeManifest(array $manifest): void
    {
        $this->close();
        file_put_contents(
            $this->dir . DIRECTORY_SEPARATOR . static::MANIFEST,
            json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)
        );
    }

    public function close(): void
    {
        foreach ($this->handles as $handle) {
            fclose($handle);
        }
        $this->handles = [];
    }

    // ---- reading --------------------------------------------------------

    /**
     * @return array<string, mixed>
     * @throws MigrateException
     */
    public function manifest(): array
    {
        $path = $this->dir . DIRECTORY_SEPARATOR . static::MANIFEST;
        $manifest = is_file($path) ? json_decode(strval(file_get_contents($path)), true) : null;
        if (!is_array($manifest)) {
            throw new MigrateException('移行ファイルに manifest.json がありません。このツールで出力した ZIP を指定してください。');
        }

        return $manifest;
    }

    public function has(string $name): bool
    {
        return is_file($this->dataPath($name));
    }

    /**
     * @param string $name
     * @return \Generator<int, array<string, mixed>>
     */
    public function rows(string $name): \Generator
    {
        if (!$this->has($name)) {
            return;
        }

        $handle = fopen($this->dataPath($name), 'rb');
        if ($handle === false) {
            throw new MigrateException('作業ファイルを開けません。');
        }

        try {
            while (($line = fgets($handle)) !== false) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                $row = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                if (is_array($row)) {
                    yield $row;
                }
            }
        } finally {
            fclose($handle);
        }
    }

    public function filePath(string $uuid): string
    {
        return $this->dir . DIRECTORY_SEPARATOR . static::FILE_DIR . DIRECTORY_SEPARATOR . $uuid;
    }

    // ---- housekeeping ---------------------------------------------------

    public static function makeTempDir(): string
    {
        $base = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'pm-migrate-' . bin2hex(random_bytes(8));
        mkdir($base, 0700, true);

        return $base;
    }

    public static function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    }
}
