@php($Its = \App\Plugins\ExmentITSupport\Its::class)
@include($Its::view('_style'))
<div class="its its-narrow"><div class="its-card its-emptybox" role="alert">
    <i class="fa fa-lock" aria-hidden="true"></i>
    <h2 style="justify-content:center">この画面は使えません</h2>
    <p>ITサポートのセットアップと設定は、システム管理者だけができます。@if(!$Its::isInstalled()) まだセットアップされていません。管理者に連絡してください。@endif</p>
</div></div>
