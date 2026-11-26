<?php

namespace App\Plugins\ExmentAttachmentExport;

use Exceedone\Exment\Enums\ColumnType;
use Exceedone\Exment\Enums\SystemTableName;
use Exceedone\Exment\Model\File as ExmentFile;
use Exceedone\Exment\Services\Plugin\PluginExportBase;
use Illuminate\Support\Facades\Storage;

/**
 * Exment Plugin Attachment Export: the attachments of the records of a list in one zip.
 *
 * From "Import / Export" of a table, for the records the list shows (its filter and its search):
 * the files attached to each record and the files of its file and image columns, in a folder a
 * record ("<label>_<id>/"). Only the records the user may see are in the list, so only their
 * files are in the zip. Two files of the same name in a folder are numbered.
 *
 * Limits (the settings of the plugin): at most max_files files and max_megabytes MB; past them
 * the zip stops and says so in _README.txt (the files already in stay).
 */
class Plugin extends PluginExportBase
{
    public const DEFAULT_MAX_FILES = 2000;
    public const DEFAULT_MAX_MEGABYTES = 1024;
    protected const CHUNK = 500;

    /** @var bool the settings of the plugin: the limits */
    protected $useCustomOption = true;

    /** @var array<int, string> temporary copies of the files of a cloud disk, deleted after the zip */
    protected $temps = [];

    /**
     * @return string the zip
     */
    public function execute()
    {
        $tmp = $this->getTmpFullPath();
        $zip = new \ZipArchive();
        if ($zip->open($tmp, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('The zip could not be made.');
        }
        $maxFiles = max(1, intval($this->option('max_files', static::DEFAULT_MAX_FILES)));
        $maxBytes = max(1, intval($this->option('max_megabytes', static::DEFAULT_MAX_MEGABYTES))) * 1024 * 1024;

        $records = $this->getRecords();
        $folders = [];
        foreach ($records as $record) {
            $folders[intval($record->id)] = static::folderName($record);
        }

        $count = 0;
        $bytes = 0;
        $stopped = null;
        $names = [];
        try {
            foreach (array_chunk(array_keys($folders), static::CHUNK) as $ids) {
                foreach ($this->filesOf($ids) as [$recordId, $file]) {
                    $size = $this->sizeOf($file);
                    if ($count >= $maxFiles) {
                        $stopped = 'files';
                        break 2;
                    }
                    if ($bytes + $size > $maxBytes) {
                        $stopped = 'size';
                        break 2;
                    }
                    $local = $this->localPath($file);
                    if (!isset($local)) {
                        continue;
                    }
                    $name = static::uniqueName($names, $folders[$recordId] . '/' . static::safeName(strval($file->filename ?: basename(strval($file->local_filename)))));
                    $zip->addFile($local, $name);
                    $count++;
                    $bytes += $size;
                }
            }
            $readme = static::readme($count, $bytes, $stopped, $maxFiles, $maxBytes);
            $zip->addFromString('_README.txt', $readme);
            $zip->close();
        } finally {
            foreach ($this->temps as $temp) {
                @unlink($temp);
            }
            $this->temps = [];
        }
        return $tmp;
    }

    public function getFileName(): string
    {
        return static::safeName(strval($this->custom_table->table_name)) . '_attachments_' . date('YmdHis') . '.zip';
    }

    /**
     * The files of the records: [record id, File], the attachments first, then the file and image columns.
     *
     * @param array<int, int> $ids
     * @return \Generator<array{0: int, 1: ExmentFile}>
     */
    protected function filesOf(array $ids): \Generator
    {
        $table_name = strval($this->custom_table->table_name);

        // the attachments of the records (the documents)
        $documents = getModelName(SystemTableName::DOCUMENT)::query()
            ->where('parent_type', $table_name)
            ->whereIn('parent_id', $ids)
            ->orderBy('parent_id')->orderBy('id')
            ->get();
        $uuids = $documents->map(function ($document) {
            return strval(array_get($document->value, 'file_uuid'));
        })->filter()->all();
        $files = empty($uuids) ? collect() : ExmentFile::whereIn('uuid', $uuids)->get()->keyBy('uuid');
        foreach ($documents as $document) {
            $file = $files->get(strval(array_get($document->value, 'file_uuid')));
            if (isset($file)) {
                yield [intval($document->parent_id), $file];
            }
        }

        // the file and image columns
        $columns = $this->custom_table->custom_columns_cache->filter(function ($column) {
            return ColumnType::isAttachment($column->column_type);
        })->pluck('id')->all();
        if (empty($columns)) {
            return;
        }
        $column_files = ExmentFile::query()
            ->where('parent_type', $table_name)
            ->whereIn('parent_id', $ids)
            ->whereIn('custom_column_id', $columns)
            ->orderBy('parent_id')->orderBy('created_at')
            ->get();
        foreach ($column_files as $file) {
            yield [intval($file->parent_id), $file];
        }
    }

    /**
     * @param ExmentFile $file
     */
    protected function sizeOf($file): int
    {
        try {
            return intval(Storage::disk(config('admin.upload.disk'))->size($file->path));
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * A local path of the file for the zip: on a local disk the file itself, on a cloud disk (S3) a
     * temporary copy.
     *
     * @param ExmentFile $file
     */
    protected function localPath($file): ?string
    {
        $disk = Storage::disk(config('admin.upload.disk'));
        try {
            if (method_exists($disk, 'path') && config('filesystems.disks.' . config('admin.upload.disk') . '.driver') === 'local') {
                $path = $disk->path($file->path);
                return is_file($path) ? $path : null;
            }
            $stream = $disk->readStream($file->path);
            if (!is_resource($stream)) {
                return null;
            }
            $temp = tempnam(sys_get_temp_dir(), 'exment_attachment_');
            $out = $temp !== false ? fopen($temp, 'w') : false;
            if ($temp === false || $out === false) {
                return null;
            }
            stream_copy_to_stream($stream, $out);
            fclose($out);
            fclose($stream);
            $this->temps[] = $temp;
            return $temp;
        } catch (\Throwable $e) {
            \Log::warning('[ExmentAttachmentExport] a file was not read: ' . $file->uuid);
            return null;
        }
    }

    /**
     * The folder of a record: its label and its id ("Order 12_12").
     *
     * @param \Exceedone\Exment\Model\CustomValue $record
     */
    public static function folderName($record): string
    {
        $label = trim(strval($record->getLabel()));
        $label = mb_substr(static::safeName($label), 0, 60);
        return ($label !== '' ? $label . '_' : '') . intval($record->id);
    }

    /**
     * A name for a zip: no path, no characters Windows refuses.
     */
    public static function safeName(string $name): string
    {
        $name = str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|', "\0"], '_', $name);
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? $name;
        $name = trim($name, " .");
        return $name === '' ? 'file' : $name;
    }

    /**
     * The same name in a folder gets " (2)", " (3)", ... before its extension.
     *
     * @param array<string, bool> $names the names so far
     */
    public static function uniqueName(array &$names, string $name): string
    {
        $candidate = $name;
        $n = 1;
        while (isset($names[mb_strtolower($candidate)])) {
            $n++;
            $info = pathinfo($name);
            $dir = isset($info['dirname']) && $info['dirname'] !== '.' ? $info['dirname'] . '/' : '';
            $ext = isset($info['extension']) ? '.' . $info['extension'] : '';
            $candidate = $dir . $info['filename'] . " ({$n})" . $ext;
        }
        $names[mb_strtolower($candidate)] = true;
        return $candidate;
    }

    public static function readme(int $count, int $bytes, ?string $stopped, int $maxFiles, int $maxBytes): string
    {
        $text = "Exment Plugin Attachment Export\r\n"
            . "ファイルの数: {$count} / Files: {$count}\r\n"
            . 'ファイルの大きさ: ' . round($bytes / 1048576, 1) . ' MB / Size: ' . round($bytes / 1048576, 1) . " MB\r\n";
        if ($stopped === 'files') {
            $text .= "\r\nファイルの数が上限（{$maxFiles}）に達したので、ここで止めました。一覧を絞りこんで、分けてダウンロードしてください。\r\n"
                . "Stopped at the most files ({$maxFiles}). Filter the list and download in parts.\r\n";
        } elseif ($stopped === 'size') {
            $text .= "\r\n大きさが上限（" . intval($maxBytes / 1048576) . " MB）に達したので、ここで止めました。一覧を絞りこんで、分けてダウンロードしてください。\r\n"
                . 'Stopped at the largest size (' . intval($maxBytes / 1048576) . " MB). Filter the list and download in parts.\r\n";
        }
        return $text;
    }

    /**
     * @param mixed $default
     * @return mixed
     */
    protected function option(string $key, $default)
    {
        $value = isset($this->plugin) ? $this->plugin->getCustomOption($key) : null;
        return is_nullorempty($value) ? $default : $value;
    }

    /**
     * The settings of the plugin (Admin setting → Plugins → this plugin).
     *
     * @param mixed $form
     */
    public function setCustomOptionForm(&$form)
    {
        $form->number('max_files', '1 回の zip のファイルの数の上限')
            ->default(static::DEFAULT_MAX_FILES)
            ->help('これを超えると zip はそこで止まり、_README.txt に書きます。初めは ' . static::DEFAULT_MAX_FILES . '。');
        $form->number('max_megabytes', '1 回の zip の大きさの上限（MB）')
            ->default(static::DEFAULT_MAX_MEGABYTES)
            ->help('サーバーの一時の場所に、この大きさまで作ります。初めは ' . static::DEFAULT_MAX_MEGABYTES . ' MB。');
    }
}
