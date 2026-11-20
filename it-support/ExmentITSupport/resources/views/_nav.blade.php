@php($Its = \App\Plugins\ExmentITSupport\Its::class)
@php($st = $Its::state())
@include($Its::view('_style'))
<header class="its-top">
    <a class="its-brand" href="{{ $Its::url() }}"><span class="its-logo"><i class="fa fa-life-ring"></i></span>ITサポート</a>
    <nav class="its-tabs">
        <a href="{{ $Its::url() }}" class="{{ ($tab ?? '') === 'inbox' ? 'on' : '' }}"><i class="fa fa-inbox"></i> 受付箱</a>
        <a href="{{ $Its::url('dashboard') }}" class="{{ ($tab ?? '') === 'dashboard' ? 'on' : '' }}"><i class="fa fa-bar-chart"></i> レポート</a>
        <a href="{{ $Its::dataUrl($Its::T_KNOWLEDGE) }}"><i class="fa fa-book"></i> ナレッジ</a>
        <a href="{{ $Its::dataUrl($Its::T_CUSTOMER) }}"><i class="fa fa-building-o"></i> お客様</a>
        <a href="{{ $Its::dataUrl($Its::T_ASSET) }}"><i class="fa fa-desktop"></i> IT 資産</a>
        <a href="{{ $Its::dataUrl($Its::T_TEMPLATE) }}"><i class="fa fa-file-text-o"></i> 定型文</a>
        @if($isAdmin)<a href="{{ $Its::url('settings') }}" class="{{ ($tab ?? '') === 'settings' ? 'on' : '' }}"><i class="fa fa-cog"></i> 設定</a>@endif
    </nav>
    <div class="its-right">
        <span class="its-status" title="最後の確認: {{ $st['imap_checked_at'] ?? '—' }}">
            <span class="its-dot {{ !empty($st['imap_error']) ? 'err' : ($Its::setting('imap_enabled') === '1' ? 'on' : '') }}"></span>メール受信 {{ !empty($st['imap_error']) ? 'エラー' : ($Its::setting('imap_enabled') === '1' ? 'オン' : 'オフ') }}
        </span>
        <a class="its-btn" href="{{ $Its::publicUrl() }}" target="_blank" rel="noopener"><i class="fa fa-external-link"></i> お客様の窓口</a>
        <a class="its-btn primary" href="{{ $Its::url('new') }}"><i class="fa fa-plus"></i> チケット</a>
    </div>
</header>
@if(!empty($needsUpgrade))
<div class="its-warn">プラグインが新しくなりました。<a href="{{ $Its::url('setup') }}">セットアップ</a>から「テーブルを更新」を押してください（データはそのままです）。</div>
@endif
@if(!empty($flash))<div class="its-flash">{{ $flash }}</div>@endif
@if(!empty($flashError))<div class="its-flash error">{{ $flashError }}</div>@endif
