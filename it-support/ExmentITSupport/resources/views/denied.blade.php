@php($Its = \App\Plugins\ExmentITSupport\Its::class)
@include($Its::view('_style'))
<div class="its"><div class="its-card">
    <h2>この画面は使えません</h2>
    <p>ITサポートのセットアップと設定は、システム管理者だけができます。@if(!$Its::isInstalled()) まだセットアップされていません。管理者に連絡してください。@endif</p>
</div></div>
