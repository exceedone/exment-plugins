@php($Its = \App\Plugins\ExmentSupportDesk\Its::class)
@include($Its::view('_style'))
<div class="sd sd-narrow"><div class="sd-card sd-emptybox" role="alert">
    <i class="fa fa-lock" aria-hidden="true"></i>
    <h2 style="justify-content:center">この画面は使えません</h2>
    <p>サポートデスクのセットアップと設定は、システム管理者だけができます。@if(!$Its::isInstalled()) まだセットアップされていません。管理者に連絡してください。@endif</p>
</div></div>
