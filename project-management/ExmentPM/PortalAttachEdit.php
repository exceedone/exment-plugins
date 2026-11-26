<?php

namespace App\Plugins\ExmentPM;

use Exceedone\Exment\Enums\SystemTableName;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Editing an attachment of an issue where it is, keeping what it was as a version (ISS02938).
 *
 * - The versions are Exment's (DocumentVersionService, document_versions, exment #1866): a save
 *   and a "back to this version" replace the attachment's file, and the file it had becomes an
 *   older version (at most exment.document_version_limit).
 * - A text attachment (txt, csv, md, json, log, xml, yaml, ini, sql, source files) is edited on
 *   the page: at most 2 MB, its encoding kept (UTF-8, or Shift_JIS / EUC-JP as found), shown as
 *   text only, never as HTML.
 * - An Excel / Word / PowerPoint attachment is edited in Exment Plugin Groupware (0.4.0 or later,
 *   ibisOffice), which saves its own older version.
 * - One editor at a time: a lock of 10 minutes, renewed while the editor is open, named after
 *   its holder ("○○さんが編集中"); the others open it read only.
 *
 * Off until an administrator turns it on (Feature management: exment_pm.attach_edit).
 */
class PortalAttachEdit
{
    const FEATURE = 'exment_pm.attach_edit';
    const FEATURES = 'Exceedone\Exment\Services\Features';
    const VERSIONS = 'Exceedone\Exment\Services\DocumentVersionService';

    /** The largest text a page edits. */
    const MAX_TEXT_BYTES = 2 * 1024 * 1024;
    /** How long a lock holds without being renewed. */
    const LOCK_SECONDS = 600;

    const TEXT_EXTENSIONS = ['txt', 'csv', 'tsv', 'md', 'markdown', 'json', 'log', 'xml', 'yaml', 'yml', 'ini', 'conf', 'cfg', 'sql',
        'php', 'js', 'ts', 'css', 'scss', 'py', 'rb', 'java', 'cs', 'go', 'rs', 'sh', 'bat', 'ps1', 'vb', 'c', 'h', 'cpp', 'kt', 'swift', 'env', 'properties', 'toml'];
    const OFFICE_EXTENSIONS = ['docx', 'xlsx', 'pptx'];

    public static function registerFeature(): void
    {
        if (!class_exists(static::FEATURES)) {
            return;
        }
        try {
            call_user_func([static::FEATURES, 'register'], static::FEATURE, [
                'label' => strval(PortalDefinition::trans('attach.feature')),
                'description' => strval(PortalDefinition::trans('attach.feature_help')),
                'who' => false,
            ]);
        } catch (\Throwable $e) {
            Log::warning('ExmentPM could not register the attachment edit feature: ' . $e->getMessage());
        }
    }

    /** On: the feature, and Exment keeps versions of attachments. */
    public static function enabled(): bool
    {
        if (!class_exists(static::VERSIONS) || !\Schema::hasTable('document_versions')) {
            return false;
        }
        if (class_exists(static::FEATURES)) {
            try {
                return boolval(call_user_func([static::FEATURES, 'enabled'], static::FEATURE));
            } catch (\Throwable $e) {
                return false;
            }
        }
        return IssueAutofill::flag('attach_edit_on', false);
    }

    public static function kindOf(string $name): ?string
    {
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (in_array($ext, static::TEXT_EXTENSIONS, true)) {
            return 'text';
        }
        if (in_array($ext, static::OFFICE_EXTENSIONS, true)) {
            return 'office';
        }
        return null;
    }

    /** @return array<int, int> document id => the number of its newest version */
    public static function numbers(array $documentIds): array
    {
        if (empty($documentIds) || !class_exists(static::VERSIONS)) {
            return [];
        }
        try {
            return (array) call_user_func([static::VERSIONS, 'currentNumbers'], array_values(array_map('intval', $documentIds)));
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** The document row of an issue, or null. */
    public static function document($issue, $documentId)
    {
        $doc = getModelName(SystemTableName::DOCUMENT)::query()
            ->where('id', intval($documentId))
            ->where('parent_id', $issue->id)
            ->where('parent_type', $issue->custom_table->table_name)
            ->first();

        return $doc ?: null;
    }

    /** The file of a document row, or null. */
    public static function fileOf($doc)
    {
        $uuid = strval(array_get($doc->value, 'file_uuid', ''));

        return $uuid === '' ? null : \Exceedone\Exment\Model\File::getData($uuid);
    }

    /**
     * Whether the user may change the attachment: they may change the issue, and - when Exment
     * lets only the uploader delete a file - they uploaded it (replacing is as good as deleting).
     */
    public static function mayEdit($issue, $doc): bool
    {
        try {
            if (PortalDefinition::enableEdit($issue, true) !== true) {
                return false;
            }
            if (boolval(config('exment.file_delete_useronly', false)) && intval($doc->created_user_id) !== intval(\Exment::getUserId())) {
                return false;
            }
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * The text of a text attachment as UTF-8, its encoding and a mark of its content.
     *
     * @return array{text: string, encoding: string, hash: string}|string the text, or why not (too_big, binary, missing)
     */
    public static function readText($file)
    {
        if (!isset($file)) {
            return 'missing';
        }
        $disk = Storage::disk(config('admin.upload.disk'));
        if (!$disk->exists($file->path)) {
            return 'missing';
        }
        if ($disk->size($file->path) > static::MAX_TEXT_BYTES) {
            return 'too_big';
        }
        $bytes = strval($disk->get($file->path));
        if (strpos($bytes, "\0") !== false) {
            return 'binary';
        }
        $encoding = static::encodingOf($bytes);
        $text = $encoding === 'UTF-8' ? $bytes : mb_convert_encoding($bytes, 'UTF-8', $encoding);
        // a mark of UTF-8 kept as it was
        $bom = strncmp($bytes, "\xEF\xBB\xBF", 3) === 0;
        if ($bom) {
            $text = substr($text, 3);
        }

        return ['text' => $text, 'encoding' => $encoding . ($bom ? '+BOM' : ''), 'hash' => hash('sha256', $bytes)];
    }

    /** UTF-8 when the bytes are, else Shift_JIS (Windows) or EUC-JP as found, else UTF-8. */
    public static function encodingOf(string $bytes): string
    {
        if ($bytes === '' || mb_check_encoding($bytes, 'UTF-8')) {
            return 'UTF-8';
        }
        $found = mb_detect_encoding($bytes, ['SJIS-win', 'EUC-JP', 'UTF-8'], true);

        return in_array($found, ['SJIS-win', 'EUC-JP'], true) ? $found : 'UTF-8';
    }

    /** The bytes of an edited text in the encoding it had. */
    public static function encodeText(string $text, string $encoding): string
    {
        $bom = substr($encoding, -4) === '+BOM';
        $enc = $bom ? substr($encoding, 0, -4) : $encoding;
        $bytes = in_array($enc, ['SJIS-win', 'EUC-JP'], true) ? mb_convert_encoding($text, $enc, 'UTF-8') : $text;

        return ($bom ? "\xEF\xBB\xBF" : '') . $bytes;
    }

    /**
     * Put new content in the place of the attachment's file; the file it had becomes an older
     * version (DocumentVersionService::replace).
     */
    public static function replaceWith($issue, $doc, string $bytes, string $name)
    {
        $tmp = tempnam(sys_get_temp_dir(), 'pmattach');
        file_put_contents($tmp, $bytes);
        try {
            $upload = new \Illuminate\Http\UploadedFile($tmp, $name, null, null, true);

            return call_user_func([static::VERSIONS, 'replace'], $issue, $doc, $upload, true);
        } finally {
            @unlink($tmp);
        }
    }

    /**
     * Take the lock of an attachment, or renew it; the one who has it, when someone else does.
     *
     * @return array{mine: bool, by: int, name: string}
     */
    public static function lock(int $documentId, int $userId, string $userName): array
    {
        $key = 'pm_attach_lock:' . $documentId;
        $held = Cache::get($key);
        if (is_array($held) && intval($held['by'] ?? 0) !== $userId) {
            return ['mine' => false, 'by' => intval($held['by']), 'name' => strval($held['name'] ?? '')];
        }
        Cache::put($key, ['by' => $userId, 'name' => $userName], static::LOCK_SECONDS);

        return ['mine' => true, 'by' => $userId, 'name' => $userName];
    }

    public static function unlock(int $documentId, int $userId): void
    {
        $key = 'pm_attach_lock:' . $documentId;
        $held = Cache::get($key);
        if (is_array($held) && intval($held['by'] ?? 0) === $userId) {
            Cache::forget($key);
        }
    }

    /** Who holds the lock of an attachment now, or null. */
    public static function holder(int $documentId): ?array
    {
        $held = Cache::get('pm_attach_lock:' . $documentId);

        return is_array($held) ? ['by' => intval($held['by'] ?? 0), 'name' => strval($held['name'] ?? '')] : null;
    }

    /**
     * The page of Exment Plugin Groupware that edits an Office attachment (0.4.0 or later), or null.
     */
    public static function officeEditBase(): ?string
    {
        try {
            $plugin = \Exceedone\Exment\Model\Plugin::where('plugin_name', 'ExmentGroupware')->where('active_flg', 1)->first();
            if (!isset($plugin) || version_compare(strval($plugin->version), '0.4.0', '<')) {
                return null;
            }

            return rtrim(strval($plugin->getFullUrl('edit')), '/');
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * A line in the history of the issue (a comment of the system, so it is notified as one): who
     * made which version of which attachment.
     */
    public static function note($issue, string $text): void
    {
        try {
            $comment = \Exceedone\Exment\Model\CustomTable::getEloquent(SystemTableName::COMMENT)->getValueModel();
            $comment->parent_id = $issue->id;
            $comment->parent_type = $issue->custom_table->table_name;
            $comment->setValue('comment_detail', $text);
            $comment->save();
        } catch (\Throwable $e) {
            Log::warning('pm_portal: the note of an attachment edit could not be written: ' . $e->getMessage());
        }
    }
}
