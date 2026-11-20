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
            ->help('空にすると、このサイトにインストールした ibisOffice ' . IbisOffice::VERSION . ' を使います。ほかの場所の ibisOffice を使うときだけ、index.html のあるフォルダーの URL を入れます（例: https://office.example.com/ibisoffice/0.8.9/）。');
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
            admin_toastr('インストールできませんでした: ' . $e->getMessage(), 'error');
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

        return response(view('exment_exment_groupware::office', [
            'available' => $office->isAvailable(),
            'editor_base' => $office->baseUrl(),
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
