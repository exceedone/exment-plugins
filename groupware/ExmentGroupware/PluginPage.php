<?php

namespace App\Plugins\ExmentGroupware;

use App\Plugins\ExmentGroupware\Mail\Accounts;
use App\Plugins\ExmentGroupware\Mail\WebMail;
use Exceedone\Exment\Enums\Permission;
use Exceedone\Exment\Services\Plugin\PluginPageBase;

/**
 * Exment Plugin Groupware (ISS00233).
 *
 * Web mail (P2): a mail program in the browser, like Outlook or Gmail, that
 * connects to the mail server the company already uses (Xserver, KAGOYA,
 * Microsoft 365, Gmail, any IMAP / SMTP server). See Mail\WebMail.
 *
 * Office (P3): the Word, Excel and PowerPoint files of the file
 * server of Exment (ISS00293) open in a browser Office (ibisOffice): the file page
 * gets an "Office で開く" button, the file opens in the editor, and a save
 * becomes a new version of the file. While it is open for editing, the file
 * is checked out, so nobody else changes it at the same time (ibisOffice
 * has no editing together yet).
 */
class PluginPage extends PluginPageBase
{
    /**
     * Exment builds the page plugin's class on every request (it asks each one
     * for its views), so this is where the button is added to the file page,
     * and where the mail becomes a feature of Exment's Feature management.
     *
     * @param \Exceedone\Exment\Model\Plugin $plugin
     */
    public function __construct($plugin)
    {
        parent::__construct($plugin);
        OfficeOpener::register($plugin);
        FeatureGate::registerMail($plugin);
        // Exment's "?" of the header opens the manual of the plugin (ISS02882)
        ManualLink::register($plugin);
        FeatureGate::registerOffice($plugin);
        // the calendar (ISS02947): a feature of Feature management, off at first
        FeatureGate::registerCalendar($plugin);
        FeatureGate::registerChat($plugin);
        FeatureGate::registerCall($plugin);
    }


    /**
     * The state of the editor and its installation.
     *
     * @return mixed
     */
    public function index()
    {
        // Without the file server the Office part has nothing to open: the top page is the mail.
        $mailUsed = FeatureGate::allowed(FeatureGate::MAIL);
        if ($mailUsed && !OfficeOpener::fileServerUsed() && Accounts::ready()) {
            return $this->mailDenied() ?? $this->webMail()->page();
        }
        $office = new IbisOffice($this->plugin);
        $user = \Exment::user();
        return $this->pluginView('index', [
            'mail_used' => $mailUsed,
            'feature_url' => FeatureGate::available() && isset($user) && $user->hasPermission(Permission::SYSTEM) ? admin_url('feature') : null,
            'mail_url' => $this->plugin->getFullUrl('mail'),
            'mail_ready' => Accounts::ready(),
            'mail_accounts' => Accounts::ready() && Accounts::currentUserId() !== null ? count(Accounts::forUser(intval(Accounts::currentUserId()))) : 0,
            'version' => IbisOffice::VERSION,
            'installed' => $office->isInstalled(),
            // an older version installed: Office opens with it until it is updated (ISS02827)
            'active_version' => $office->activeVersion(),
            'update_available' => $office->updateAvailable(),
            'custom_url' => $office->customUrl(),
            'available' => $office->isAvailable(),
            'can_install' => isset($user) && $user->hasPermission(Permission::SYSTEM),
            'install_url' => $this->plugin->getFullUrl('install'),
            'file_server_url' => OfficeOpener::fileServerUsed() ? admin_url('file_server') : null,
            'project_url' => IbisOffice::PROJECT_URL,
            'extensions' => IbisOffice::EXTENSIONS,
            'problems' => $office->isInstalled() ? [] : $office->problems(),
            'elsewhere' => IbisOffice::ELSEWHERE,
        ]);
    }

    /**
     * Install ibisOffice into the public folder of the site (system administrators).
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function install()
    {
        $user = \Exment::user();
        if (!isset($user) || !$user->hasPermission(Permission::SYSTEM)) {
            abort(403);
        }
        try {
            (new IbisOffice($this->plugin))->install();
            admin_toastr('ibisOffice ' . IbisOffice::VERSION . ' をインストールしました。');
        } catch (\Throwable $e) {
            \Log::error('[ExmentGroupware] ibisOffice install: ' . $e->getMessage());
            WebMail::toast('インストールできませんでした: ' . $e->getMessage(), 'error');
        }
        return redirect($this->plugin->getFullUrl());
    }

    /**
     * A file of the file server in the editor: a page of its own (the editor
     * takes the whole window), opened in a new tab from the file page.
     *
     * @param string|int $workspace
     * @param string|int $node
     * @return \Symfony\Component\HttpFoundation\Response
     */
    /**
     * A blank Word, Excel or PowerPoint document. An empty file (0 bytes, as
     * Windows makes with New > Word document) has nothing ibisOffice could
     * tell the kind from - it opened as a spreadsheet - so the editor opens
     * this instead, and the first save makes the file a real document.
     *
     * @param string $ext docx, xlsx or pptx
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function blank($ext)
    {
        $ext = strtolower(strval($ext));
        $path = __DIR__ . '/resources/blank/blank.' . $ext;
        if (!in_array($ext, IbisOffice::EXTENSIONS, true) || !is_file($path)) {
            abort(404);
        }
        return response(strval(file_get_contents($path)), 200, [
            'Content-Type' => 'application/octet-stream',
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Shown in the preview of an Office file of the file server while ibisOffice is
     * not installed, to system administrators only (ISS02827): what it would give,
     * what it needs, what stops it here, and the way to the install.
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function officeMissing()
    {
        $user = \Exment::user();
        if (!isset($user) || !$user->hasPermission(Permission::SYSTEM)) {
            // a plain 404 (abort() in an admin page answers 200 with the error page)
            return response('', 404);
        }
        $office = new IbisOffice($this->plugin);
        return response(view('exment_exment_groupware::office_missing', [
            'version' => IbisOffice::VERSION,
            'installed' => $office->isAvailable(),
            'problems' => $office->problems(),
            'elsewhere' => IbisOffice::ELSEWHERE,
            'install_url' => $this->plugin->getFullUrl(),
        ])->render())->header('X-Frame-Options', 'SAMEORIGIN');
    }

    public function office($workspace, $node)
    {
        if (!OfficeOpener::fileServerAvailable()) {
            abort(404);
        }
        if (($denied = FeatureGate::deny(FeatureGate::FILE_SERVER)) !== null) {
            return $denied;
        }
        $office = new IbisOffice($this->plugin);
        $ws = intval($workspace);
        $id = intval($node);
        $user = \Exment::user();
        $userName = '';
        if (isset($user) && isset($user->base_user)) {
            $userName = strval($user->base_user->getLabel());
        }

        // ?view=1: read only, shown in the preview of the file page (no lock, no save)
        $view = boolval(request()->get('view'));

        return response(view('exment_exment_groupware::office', [
            'view' => $view,
            'edit_url' => $this->plugin->getFullUrl('office', $ws, $id),
            'available' => $office->isAvailable() && $office->hostModuleUrl() !== null,
            'editor_base' => $office->baseUrl(),
            'host_module' => $office->hostModuleUrl(),
            'office_origin' => $office->officeOrigin(),
            'info_url' => admin_urls('file_server', $ws, 'nodes', $id, 'info'),
            'back_url' => admin_urls('file_server', $ws, 'nodes', $id),
            'settings_url' => $this->plugin->getFullUrl(),
            'user_name' => $userName,
            'locale' => str_starts_with(strval(app()->getLocale()), 'ja') ? 'ja' : 'en',
            'project_url' => IbisOffice::PROJECT_URL,
            'version' => IbisOffice::VERSION,
            'blank_url' => $this->plugin->getFullUrl('blank', '__EXT__'),
        ])->render())->header('X-Frame-Options', 'SAMEORIGIN');
    }

    /**
     * An attachment of a record (files/{uuid}) in ibisOffice - the Word, Excel
     * and PowerPoint files of other plugins' screens (ExmentPM's issues,
     * comments, wiki pages and projects). view/{uuid} shows it read only;
     * edit/{uuid} opens it for editing when the user may edit the record it
     * is attached to, and saving replaces its content (see saveFile).
     * The file itself is read from the same address its download link uses,
     * so who may see it is decided there, as for the download.
     *
     * @param string $uuid
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function viewFile($uuid)
    {
        list($file, $record) = $this->attachment($uuid);

        return $this->attachmentPage($file, false, $this->mayEdit($record, $file));
    }

    /**
     * @param string $uuid
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function editFile($uuid)
    {
        list($file, $record) = $this->attachment($uuid);

        // without the right to edit, the same file read only
        return $this->attachmentPage($file, $this->mayEdit($record, $file), false);
    }

    /**
     * Save from the editor: the attachment keeps its address (links to it in
     * comments and descriptions go on working) and gets the new content. The
     * content it had is kept as a file of its own on the same record - not
     * listed as an attachment, readable by the same people - and a comment on
     * the record says who changed it, with a link to what it was before.
     * A save made from content someone else has replaced since is refused
     * (409), so nothing is overwritten unseen.
     *
     * @param string $uuid
     * @return \Illuminate\Http\JsonResponse
     */
    public function saveFile($uuid)
    {
        list($file, $record) = $this->attachment($uuid);
        if (!$this->mayEdit($record, $file)) {
            return response()->json(['result' => false, 'message' => '編集の権限がありません。'], 403);
        }
        $upload = request()->file('file');
        if (!isset($upload) || !$upload->isValid()) {
            return response()->json(['result' => false, 'message' => 'ファイルを受け取れませんでした。'], 422);
        }
        // every save keeps what was there, so the size is bounded
        if ($upload->getSize() > static::maxSaveBytes()) {
            return response()->json(['result' => false, 'message' => 'ファイルが大きすぎます。'], 413);
        }
        $bytes = strval(file_get_contents($upload->getRealPath()));
        // .docx, .xlsx and .pptx are zip files
        if (strncmp($bytes, "PK\x03\x04", 4) !== 0) {
            return response()->json(['result' => false, 'message' => 'Word・Excel・PowerPoint のファイルではありません。'], 422);
        }
        $disk = \Illuminate\Support\Facades\Storage::disk(config('admin.upload.disk'));

        $lock = \Illuminate\Support\Facades\Cache::lock('exment_groupware_attachment_' . strval($file->uuid), 30);
        if (!$lock->get()) {
            return response()->json(['result' => false, 'message' => 'ほかの人が保存しているところです。少し待ってからもう一度保存してください。'], 423);
        }
        try {
            $current = strval($disk->get($file->path));
            if (!hash_equals(hash('sha256', $current), strval(request()->get('base_version')))) {
                return response()->json(['result' => false, 'message' => '開いたあとに、ほかの人がこのファイルを保存しました。いったん閉じて開き直してください。'], 409);
            }
            // what it was: kept on the same record, not listed as an attachment
            $previous = \Exceedone\Exment\Model\File::storeAs($file->file_type, $current, strval($file->local_dirname), strval($file->filename), [
                'options' => ['exment_groupware_previous_of' => strval($file->uuid), 'saved_at' => now()->toDateTimeString()],
            ]);
            $previous->parent_id = $file->parent_id;
            $previous->parent_type = $file->parent_type;
            $previous->save();
            $this->addOlderVersion($file, $previous);

            $disk->put($file->path, $bytes);
            $file->touch();
        } finally {
            $lock->release();
        }

        $this->commentEdited($record, $file, $previous);

        return response()->json(['result' => true, 'version' => hash('sha256', $bytes)]);
    }

    /**
     * The attachment and the record it hangs on, or 404. Keep this in step
     * with core FileController::downloadFile (the same getData and
     * hasPermissionData), only stricter: only a file attached to a record of
     * a table that still exists, and that this user may read - anything else
     * answers as if it were not there, so no page names a file it cannot
     * vouch for.
     *
     * @param mixed $uuid
     * @return array{0: \Exceedone\Exment\Model\File, 1: \Exceedone\Exment\Model\CustomValue}
     */
    protected function attachment($uuid): array
    {
        $uuid = strval($uuid);
        $file = \Exceedone\Exment\Model\File::getData([$uuid, pathinfo($uuid, PATHINFO_FILENAME)]);
        if (!$file || !isset($file->parent_id) || !isset($file->parent_type)) {
            abort(404);
        }
        $custom_table = \Exceedone\Exment\Model\CustomTable::getEloquent($file->parent_type);
        if (!isset($custom_table) || !$custom_table->hasPermissionData($file->parent_id)) {
            abort(404);
        }
        $record = $custom_table->getValueModel($file->parent_id);
        if (!isset($record) || !IbisOffice::opens(strval($file->filename))) {
            abort(404);
        }

        return [$file, $record];
    }

    /**
     * Whether the user may replace the content of this file: it is an
     * attachment of the record (a document row, not the value of a file
     * column, which has its own checks when it is set), the user may change
     * the record (permission, workflow lock, parent records... as Exment's
     * own edit screen asks), and - when Exment lets only the uploader delete
     * a file (exment.file_delete_useronly) - the user uploaded it: replacing
     * the content is as good as deleting it.
     *
     * @param \Exceedone\Exment\Model\CustomValue $record
     * @param \Exceedone\Exment\Model\File $file
     */
    protected function mayEdit($record, $file): bool
    {
        try {
            if (!is_nullorempty($file->custom_column_id)) {
                return false;
            }
            $listed = getModelName(\Exceedone\Exment\Enums\SystemTableName::DOCUMENT)::where('parent_type', $file->parent_type)
                ->where('parent_id', $file->parent_id)
                ->where('value->file_uuid', strval($file->uuid))
                ->exists();
            if (!$listed) {
                return false;
            }
            if (boolval(config('exment.file_delete_useronly', false)) && $file->created_user_id != \Exment::getUserId()) {
                return false;
            }

            return $record->enableEdit(true) === true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** The largest file a save takes: Exment's upload limit, at most 50 MB. */
    protected static function maxSaveBytes(): int
    {
        $limit = 50 * 1024 * 1024;
        try {
            $exment = intval(\Exment::getUploadMaxFileSize());
            if ($exment > 0) {
                $limit = min($limit, $exment);
            }
        } catch (\Throwable $e) {
        }

        return $limit;
    }

    /**
     * On an Exment that keeps older versions of attachments (core
     * DocumentVersionService, the 「差し替え」 of the attachment list), the
     * content kept by a save becomes one of them: it shows under 「版 n」,
     * counts towards exment.document_version_limit, and goes when the
     * attachment is deleted. On an older Exment, nothing is added and the
     * earlier content stays as the file the comment links to.
     *
     * @param \Exceedone\Exment\Model\File $file
     * @param \Exceedone\Exment\Model\File $previous
     */
    protected function addOlderVersion($file, $previous): void
    {
        $service = 'Exceedone\Exment\Services\DocumentVersionService';
        try {
            if (!class_exists($service) || !method_exists($service, 'addOlderVersion') || !\Schema::hasTable('document_versions')) {
                return;
            }
            $document = getModelName(\Exceedone\Exment\Enums\SystemTableName::DOCUMENT)::where('parent_type', $file->parent_type)
                ->where('parent_id', $file->parent_id)
                ->where('value->file_uuid', strval($file->uuid))
                ->first();
            if (isset($document)) {
                // who made the content that is now the older version
                $by = $file->updated_user_id ?? $file->created_user_id;
                call_user_func([$service, 'addOlderVersion'], $document, $previous, isset($by) ? intval($by) : null);
            }
        } catch (\Throwable $e) {
            \Log::warning('[ExmentGroupware] could not add the older version of an edited attachment: ' . $e->getMessage());
        }
    }

    /**
     * @param \Exceedone\Exment\Model\CustomValue $record
     * @param \Exceedone\Exment\Model\File $file
     * @param \Exceedone\Exment\Model\File $previous
     */
    protected function commentEdited($record, $file, $previous): void
    {
        try {
            $comment = \Exceedone\Exment\Model\CustomTable::getEloquent(\Exceedone\Exment\Enums\SystemTableName::COMMENT)->getValueModel();
            $comment->parent_id = $record->id;
            $comment->parent_type = $record->custom_table->table_name;
            $comment->setValue('comment_detail', '「' . strval($file->filename) . '」を Office（ibisOffice）で編集しました。'
                . "\n" . '編集する前のファイル: ' . admin_url('files', [strval($previous->uuid)]));
            $comment->save();
        } catch (\Throwable $e) {
            \Log::warning('[ExmentGroupware] could not comment on an edited attachment: ' . $e->getMessage());
        }
    }

    /**
     * @param \Exceedone\Exment\Model\File $file
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function attachmentPage($file, bool $edit, bool $offerEdit)
    {
        $office = new IbisOffice($this->plugin);
        $uuid = strval($file->uuid);
        $download = admin_url('files', [$uuid]);
        $hash = '';
        if ($edit) {
            $hash = hash('sha256', strval(\Illuminate\Support\Facades\Storage::disk(config('admin.upload.disk'))->get($file->path)));
        }

        return response(view('exment_exment_groupware::office', [
            'view' => !$edit,
            'file' => [
                'name' => strval($file->filename),
                'download' => $download,
                'edit' => $edit,
                'base' => $hash,
                'save_url' => $edit ? $this->plugin->getFullUrl('edit', $uuid, 'save') : null,
            ],
            'edit_url' => $offerEdit ? $this->plugin->getFullUrl('edit', $uuid) : null,
            'available' => $office->isAvailable() && $office->hostModuleUrl() !== null,
            'editor_base' => $office->baseUrl(),
            'host_module' => $office->hostModuleUrl(),
            'office_origin' => $office->officeOrigin(),
            'info_url' => null,
            'back_url' => $download,
            'settings_url' => $this->plugin->getFullUrl(),
            'user_name' => $this->userName(),
            'locale' => str_starts_with(strval(app()->getLocale()), 'ja') ? 'ja' : 'en',
            'project_url' => IbisOffice::PROJECT_URL,
            'version' => IbisOffice::VERSION,
            'blank_url' => $this->plugin->getFullUrl('blank', '__EXT__'),
        ])->render())->header('X-Frame-Options', 'SAMEORIGIN');
    }

    protected function userName(): string
    {
        $user = \Exment::user();

        return (isset($user) && isset($user->base_user)) ? strval($user->base_user->getLabel()) : '';
    }

    // ---- Web mail (Mail\WebMail) ------------------------------------------

    /**
     * Exment's answer when the login user may not use the mail (Feature management), or null.
     *
     * @return \Symfony\Component\HttpFoundation\Response|null
     */
    protected function mailDenied()
    {
        return FeatureGate::deny(FeatureGate::MAIL);
    }

    protected function webMail(): WebMail
    {
        // Feature management of Exment: the mail not used, or not for this user
        // Feature management of Exment (the pages answer with mailDenied() first)
        if (!FeatureGate::allowed(FeatureGate::MAIL)) {
            abort(404);
        }
        return new WebMail($this->plugin);
    }

    /** The mail program draws the whole page (no page header). */
    public function mail()
    {
        return $this->mailDenied() ?? $this->webMail()->page();
    }

    public function mailAccounts()
    {
        return $this->mailDenied() ?? $this->webMail()->accounts();
    }

    public function postMailAccount()
    {
        return $this->mailDenied() ?? $this->webMail()->postAccount(request());
    }

    public function postMailAccountDelete($id)
    {
        return $this->mailDenied() ?? $this->webMail()->postAccountDelete($id);
    }

    public function mailSignin($id)
    {
        return $this->mailDenied() ?? $this->webMail()->signin($id);
    }

    public function mailOauthCallback()
    {
        return $this->mailDenied() ?? $this->webMail()->oauthCallback(request());
    }

    public function mailPoll()
    {
        return $this->mailDenied() ?? $this->webMail()->apiPoll();
    }

    public function mailFolders($account)
    {
        return $this->mailDenied() ?? $this->webMail()->apiFolders($account);
    }

    public function mailMessages($account)
    {
        return $this->mailDenied() ?? $this->webMail()->apiMessages(request(), $account);
    }

    public function mailMessage($account)
    {
        return $this->mailDenied() ?? $this->webMail()->apiMessage(request(), $account);
    }

    public function mailAttachment($account)
    {
        return $this->mailDenied() ?? $this->webMail()->apiAttachment(request(), $account);
    }

    public function mailEml($account)
    {
        return $this->mailDenied() ?? $this->webMail()->apiEml(request(), $account);
    }

    public function mailFlag($account)
    {
        return $this->mailDenied() ?? $this->webMail()->apiFlag(request(), $account);
    }

    public function mailMove($account)
    {
        return $this->mailDenied() ?? $this->webMail()->apiMove(request(), $account);
    }

    public function mailDelete($account)
    {
        return $this->mailDenied() ?? $this->webMail()->apiDelete(request(), $account);
    }

    public function mailCreateFolder($account)
    {
        return $this->mailDenied() ?? $this->webMail()->apiCreateFolder(request(), $account);
    }

    public function mailSend($account)
    {
        return $this->mailDenied() ?? $this->webMail()->apiSend(request(), $account);
    }

    public function mailDraft($account)
    {
        return $this->mailDenied() ?? $this->webMail()->apiDraft(request(), $account);
    }

    // ---- the calendar (ISS02947) -----------------------------------------------

    protected function calendar(): Calendar\CalendarPage
    {
        require_once __DIR__ . '/Calendar/Bootstrap.php';
        return new Calendar\CalendarPage($this->plugin);
    }

    protected function calDenied()
    {
        return FeatureGate::deny(FeatureGate::CALENDAR);
    }

    public function cal()
    {
        return $this->calDenied() ?? $this->calendar()->page();
    }

    public function calEvents()
    {
        return $this->calDenied() ?? $this->calendar()->events(request());
    }

    public function calEvent($id)
    {
        return $this->calDenied() ?? $this->calendar()->event($id);
    }

    public function calCreate()
    {
        return $this->calDenied() ?? $this->calendar()->create(request());
    }

    public function calSave($id)
    {
        return $this->calDenied() ?? $this->calendar()->save(request(), $id);
    }

    public function calDelete($id)
    {
        return $this->calDenied() ?? $this->calendar()->delete(request(), $id);
    }

    public function calReply($id)
    {
        return $this->calDenied() ?? $this->calendar()->reply(request(), $id);
    }

    public function calHolidays()
    {
        return $this->calDenied() ?? $this->calendar()->holidays(request());
    }

    public function calPeople()
    {
        return $this->calDenied() ?? $this->calendar()->people(request());
    }

    // ---- the chat (ISS02945) ------------------------------------------------

    protected function chat(): \App\Plugins\ExmentGroupware\Chat\ChatPage
    {
        if (!FeatureGate::allowed(FeatureGate::CHAT)) {
            abort(404);
        }

        return new \App\Plugins\ExmentGroupware\Chat\ChatPage($this->plugin);
    }

    protected function chatDenied()
    {
        return FeatureGate::deny(FeatureGate::CHAT);
    }

    public function chatPage()
    {
        return $this->chatDenied() ?? $this->chat()->page();
    }

    public function chatRooms()
    {
        return $this->chatDenied() ?? $this->chat()->apiRooms();
    }

    public function chatPostRoom()
    {
        return $this->chatDenied() ?? $this->chat()->postRoom(request());
    }

    public function chatRoom($id)
    {
        return $this->chatDenied() ?? $this->chat()->apiRoom($id);
    }

    public function chatDirect($id)
    {
        return $this->chatDenied() ?? $this->chat()->postDirect($id);
    }

    public function chatMembers($id)
    {
        return $this->chatDenied() ?? $this->chat()->postMembers(request(), $id);
    }

    public function chatMessages($id)
    {
        return $this->chatDenied() ?? $this->chat()->apiMessages(request(), $id);
    }

    public function chatPost($id)
    {
        return $this->chatDenied() ?? $this->chat()->postMessage(request(), $id);
    }

    public function chatEdit($id)
    {
        return $this->chatDenied() ?? $this->chat()->postEdit(request(), $id);
    }

    public function chatDelete($id)
    {
        return $this->chatDenied() ?? $this->chat()->postDelete($id);
    }

    public function chatReact($id)
    {
        return $this->chatDenied() ?? $this->chat()->postReact(request(), $id);
    }

    public function chatTasks()
    {
        return $this->chatDenied() ?? $this->chat()->apiTasks();
    }

    public function chatTask($id)
    {
        return $this->chatDenied() ?? $this->chat()->postTask(request(), $id);
    }

    public function chatTaskDone($id)
    {
        return $this->chatDenied() ?? $this->chat()->postTaskDone(request(), $id);
    }

    public function chatProjects()
    {
        return $this->chatDenied() ?? $this->chat()->apiProjects();
    }

    public function chatIssue($id)
    {
        return $this->chatDenied() ?? $this->chat()->postIssue(request(), $id);
    }

    public function chatSearch()
    {
        return $this->chatDenied() ?? $this->chat()->apiSearch(request());
    }

    public function chatMute($id)
    {
        return $this->chatDenied() ?? $this->chat()->postMute(request(), $id);
    }

    public function chatRoomSettings($id)
    {
        return $this->chatDenied() ?? $this->chat()->postRoomSettings(request(), $id);
    }

    public function chatRead($id)
    {
        return $this->chatDenied() ?? $this->chat()->postRead(request(), $id);
    }

    public function chatPoll()
    {
        return $this->chatDenied() ?? $this->chat()->apiPoll(request());
    }

    public function chatFile($uuid)
    {
        return $this->chatDenied() ?? $this->chat()->file($uuid);
    }

    // ---- calls from the chat (ISS02946) ---------------------------------------

    protected function callDenied()
    {
        return $this->chatDenied() ?? FeatureGate::deny(FeatureGate::CALL);
    }

    public function chatCallStart($id)
    {
        return $this->callDenied() ?? $this->chat()->postCallStart($id);
    }

    public function chatCallSignal($id)
    {
        return $this->callDenied() ?? $this->chat()->postCallSignal(request(), $id);
    }

    public function chatCallSignals($id)
    {
        return $this->callDenied() ?? $this->chat()->apiCallSignals(request(), $id);
    }

    public function chatCallNew()
    {
        return $this->callDenied() ?? $this->chat()->callNew(request());
    }

    public function chatCallJoin($id)
    {
        return $this->callDenied() ?? $this->chat()->postCallJoin($id);
    }

    public function chatCallEnd($id)
    {
        return $this->callDenied() ?? $this->chat()->postCallEnd($id);
    }
}
