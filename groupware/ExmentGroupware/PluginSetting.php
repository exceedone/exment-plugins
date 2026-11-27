<?php

namespace App\Plugins\ExmentGroupware;

use App\Plugins\ExmentGroupware\Mail\OAuth;
use Exceedone\Exment\Services\Plugin\PluginBase;

/**
 * The plugin's settings on Exment's plugin management screen: the OAuth clients of the web mail,
 * the servers the mail may reach, the call's STUN / TURN, ibisOffice's place.
 *
 * A plugin whose plugin_type is a list (page, batch) has its settings class by the fixed name
 * "PluginSetting" (PluginType::getPluginClassShortName with as_setting): the form that was in
 * Plugin.php, unchanged. The values are the plugin's options as before (getCustomOption).
 */
class PluginSetting
{
    use PluginBase;

    /**
     * @param \Exceedone\Exment\Model\Plugin $plugin
     */
    public function __construct($plugin)
    {
        $this->plugin = $plugin;
    }

    /**
     * A method, not the property: a class property whose default differs from PluginBase's is a
     * fatal composition error on PHP 8.3 (as ExmentPM's PluginSetting says).
     *
     * @return bool
     */
    public function useCustomOption()
    {
        return true;
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
        $form->exmheader('通話（チャット）')->hr();
        $form->textarea('call_ice', '通話のサーバー（STUN / TURN）')->rows(3)
            ->help('1 行に 1 つ。STUN は stun:ホスト:ポート、TURN は turn:ホスト:ポート|ユーザー|パスワード。空のときは、2 台の PC が直接つながるとき（同じ LAN・VPN）だけ通話できます。公開の STUN（例: stun:stun.l.google.com:19302）を入れると、多くの家や会社のネットからつながりますが、ブラウザーはその外のサーバーに自分の IP アドレスを知らせます。TURN のパスワードは、通話する人のブラウザーに渡ります。');
        $form->exmheader('カレンダー')->hr();
        $form->select('cal_resource_clash', '会議室・備品が重なったとき')->options(['warn' => '知らせて、たしかめてから保存する', 'block' => '保存させない'])->default('warn')
            ->help('同じ会議室・備品を、重なる時間に予約しようとしたとき。初めは「知らせて、たしかめてから保存する」です。');
        $form->exmheader('Office')->hr();
        $form->url('ibisoffice_url', 'ibisOffice の場所（URL）')
            ->help('空にすると、このサイトにインストールした ibisOffice ' . IbisOffice::VERSION . ' を使います。ほかの場所の ibisOffice を使うときだけ、index.html のあるフォルダーの URL を入れます（例: https://office.example.com/ibisoffice/0.8.9/）。このサイトにインストールした ibisOffice があるときは、ページの中で動く ibisoffice-host.js はそちらを使います。ほかの場所のものは https のときだけ使います。');
    }
}
