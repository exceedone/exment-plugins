<?php

namespace App\Plugins\ExmentGroupware;

use App\Plugins\ExmentGroupware\Mail\Accounts;
use App\Plugins\ExmentGroupware\Mail\OAuth;
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
class Plugin extends PluginPageBase
{
    protected $useCustomOption = true;

    /**
     * Exment builds the page plugin's class on every request (it asks each one
     * for its views), so this is where the button is added to the file page.
     *
     * @param \Exceedone\Exment\Model\Plugin $plugin
     */
    public function __construct($plugin)
    {
        parent::__construct($plugin);
        OfficeOpener::register($plugin);
    }

    /**
     * @param \ExmentAdminCore\Admin\Form $form
     * @return void
     */
    public function setCustomOptionForm(&$form)
    {
        $redirect = (new OAuth($this->plugin))->redirectUri();
        $form->exmheader('Web メール：Microsoft 365 でサインイン')->hr();
        $form->description('Azure portal の「アプリの登録」で Web アプリを登録し、リダイレクト URI に <code>' . e($redirect) . '</code> を入れます。API のアクセス許可は Office 365 Exchange Online の IMAP.AccessAsUser.All・SMTP.Send（委任）と offline_access・openid・email・profile。テナントの管理者が「SMTP 認証」をユーザーに許可している必要があります。')->escape(false);
        $form->text('ms_client_id', 'アプリケーション（クライアント）ID');
        $form->password('ms_client_secret', 'クライアント シークレット');
        $form->text('ms_tenant', 'テナント')->help('空のときは organizations（職場・学校のアカウントならどのテナントでも）。自社だけに限るときは、テナント ID か contoso.onmicrosoft.com のようなドメイン。');
        $form->exmheader('Web メール：Google でサインイン（Gmail・Google Workspace）')->hr();
        $form->description('Google Cloud console で OAuth クライアント ID（ウェブ アプリケーション）を作り、承認済みのリダイレクト URI に <code>' . e($redirect) . '</code> を入れます。スコープは https://mail.google.com/ を使います（外部に公開するときは Google の審査が必要です。Google Workspace の社内用なら「内部」で審査は要りません）。')->escape(false);
        $form->text('google_client_id', 'クライアント ID');
        $form->password('google_client_secret', 'クライアント シークレット');
        $form->exmheader('Web メール：つなぐ先')->hr();
        $form->switchbool('mail_allow_private', '社内のネットワークのメールサーバーを許可する')
            ->default(0)
            ->help('オンにすると、プライベート IP（10.x・192.168.x など）や localhost のサーバー、ふつうでないポートにもつなげます。社内に自社のメールサーバーがあるオンプレミスの Exment のときだけオンにしてください（Exment SaaS ではオフのまま）。');
        $form->exmheader('Office')->hr();
        $form->url('ibisoffice_url', 'ibisOffice の場所（URL）')
            ->help('空にすると、このサイトにインストールした ibisOffice ' . IbisOffice::VERSION . ' を使います。ほかの場所の ibisOffice を使うときだけ、index.html のあるフォルダーの URL を入れます（例: https://office.example.com/ibisoffice/0.8.9/）。このサイトにインストールした ibisOffice があるときは、ページの中で動く ibisoffice-host.js はそちらを使います。ほかの場所のものは https のときだけ使います。');
    }

    /**
     * The state of the editor and its installation.
     *
     * @return mixed
     */
    public function index()
    {
        // Without the file server the Office part has nothing to open: the top page is the mail.
        if (!OfficeOpener::fileServerAvailable() && Accounts::ready()) {
            return $this->webMail()->page();
        }
        $office = new IbisOffice($this->plugin);
        $user = \Exment::user();
        return $this->pluginView('index', [
            'mail_url' => $this->plugin->getFullUrl('mail'),
            'mail_ready' => Accounts::ready(),
            'mail_accounts' => Accounts::ready() && Accounts::currentUserId() !== null ? count(Accounts::forUser(intval(Accounts::currentUserId()))) : 0,
            'version' => IbisOffice::VERSION,
            'installed' => $office->isInstalled(),
            'custom_url' => $office->customUrl(),
            'available' => $office->isAvailable(),
            'can_install' => isset($user) && $user->hasPermission(Permission::SYSTEM),
            'install_url' => $this->plugin->getFullUrl('install'),
            'file_server_url' => OfficeOpener::fileServerAvailable() ? admin_url('file_server') : null,
            'project_url' => IbisOffice::PROJECT_URL,
            'extensions' => IbisOffice::EXTENSIONS,
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
    public function office($workspace, $node)
    {
        if (!OfficeOpener::fileServerAvailable()) {
            abort(404);
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

        return $this->attachmentPage($file, false, $this->mayEdit($record));
    }

    /**
     * @param string $uuid
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function editFile($uuid)
    {
        list($file, $record) = $this->attachment($uuid);

        // without the right to edit, the same file read only
        return $this->attachmentPage($file, $this->mayEdit($record), false);
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
        if (!$this->mayEdit($record)) {
            return response()->json(['result' => false, 'message' => '編集の権限がありません。'], 403);
        }
        $upload = request()->file('file');
        if (!isset($upload) || !$upload->isValid()) {
            return response()->json(['result' => false, 'message' => 'ファイルを受け取れませんでした。'], 422);
        }
        $bytes = strval(file_get_contents($upload->getRealPath()));
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
     * Whether the user may change the record the file hangs on (permission,
     * workflow lock, parent records...), as Exment's own edit screen asks.
     *
     * @param \Exceedone\Exment\Model\CustomValue $record
     */
    protected function mayEdit($record): bool
    {
        try {
            return $record->enableEdit(true) === true;
        } catch (\Throwable $e) {
            return false;
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
        ])->render())->header('X-Frame-Options', 'SAMEORIGIN');
    }

    protected function userName(): string
    {
        $user = \Exment::user();

        return (isset($user) && isset($user->base_user)) ? strval($user->base_user->getLabel()) : '';
    }

    // ---- Web mail (Mail\WebMail) ------------------------------------------

    protected function webMail(): WebMail
    {
        return new WebMail($this->plugin);
    }

    /** The mail program draws the whole page (no page header). */
    public function mail()
    {
        return $this->webMail()->page();
    }

    public function mailAccounts()
    {
        return $this->webMail()->accounts();
    }

    public function postMailAccount()
    {
        return $this->webMail()->postAccount(request());
    }

    public function postMailAccountDelete($id)
    {
        return $this->webMail()->postAccountDelete($id);
    }

    public function mailSignin($id)
    {
        return $this->webMail()->signin($id);
    }

    public function mailOauthCallback()
    {
        return $this->webMail()->oauthCallback(request());
    }

    public function mailPoll()
    {
        return $this->webMail()->apiPoll();
    }

    public function mailFolders($account)
    {
        return $this->webMail()->apiFolders($account);
    }

    public function mailMessages($account)
    {
        return $this->webMail()->apiMessages(request(), $account);
    }

    public function mailMessage($account)
    {
        return $this->webMail()->apiMessage(request(), $account);
    }

    public function mailAttachment($account)
    {
        return $this->webMail()->apiAttachment(request(), $account);
    }

    public function mailEml($account)
    {
        return $this->webMail()->apiEml(request(), $account);
    }

    public function mailFlag($account)
    {
        return $this->webMail()->apiFlag(request(), $account);
    }

    public function mailMove($account)
    {
        return $this->webMail()->apiMove(request(), $account);
    }

    public function mailDelete($account)
    {
        return $this->webMail()->apiDelete(request(), $account);
    }

    public function mailCreateFolder($account)
    {
        return $this->webMail()->apiCreateFolder(request(), $account);
    }

    public function mailSend($account)
    {
        return $this->webMail()->apiSend(request(), $account);
    }

    public function mailDraft($account)
    {
        return $this->webMail()->apiDraft(request(), $account);
    }
}
