@php($Its = \App\Plugins\ExmentITSupport\Its::class)
@php($st = $Its::state())
@php($imapOn = $Its::setting('imap_enabled') === '1')
@include($Its::view('_style'))
<header class="its-top">
    <a class="its-brand" href="{{ $Its::url() }}"><span class="its-logo" aria-hidden="true"><i class="fa fa-life-ring"></i></span>ITサポート</a>
    <nav class="its-tabs" aria-label="ITサポートのメニュー">
        <a href="{{ $Its::url() }}" class="{{ ($tab ?? '') === 'inbox' ? 'on' : '' }}" @if(($tab ?? '') === 'inbox') aria-current="page" @endif><i class="fa fa-inbox" aria-hidden="true"></i>受付箱</a>
        <a href="{{ $Its::url('dashboard') }}" class="{{ ($tab ?? '') === 'dashboard' ? 'on' : '' }}" @if(($tab ?? '') === 'dashboard') aria-current="page" @endif><i class="fa fa-bar-chart" aria-hidden="true"></i>レポート</a>
        <span class="sep" aria-hidden="true"></span>
        <a href="{{ $Its::dataUrl($Its::T_KNOWLEDGE) }}"><i class="fa fa-book" aria-hidden="true"></i>ナレッジ</a>
        <a href="{{ $Its::dataUrl($Its::T_CUSTOMER) }}"><i class="fa fa-building-o" aria-hidden="true"></i>お客様</a>
        <a href="{{ $Its::dataUrl($Its::T_ASSET) }}"><i class="fa fa-desktop" aria-hidden="true"></i>IT 資産</a>
        <a href="{{ $Its::dataUrl($Its::T_TEMPLATE) }}"><i class="fa fa-file-text-o" aria-hidden="true"></i>定型文</a>
        @if($isAdmin)
        <span class="sep" aria-hidden="true"></span>
        <a href="{{ $Its::url('settings') }}" class="{{ ($tab ?? '') === 'settings' ? 'on' : '' }}" @if(($tab ?? '') === 'settings') aria-current="page" @endif><i class="fa fa-cog" aria-hidden="true"></i>設定</a>
        @endif
    </nav>
    <div class="its-right">
        <span class="its-status" title="最後の確認: {{ $st['imap_checked_at'] ?? '—' }}">
            <span class="its-dot {{ !empty($st['imap_error']) ? 'err' : ($imapOn ? 'on' : '') }}" aria-hidden="true"></span>メール受信 {{ !empty($st['imap_error']) ? 'エラー' : ($imapOn ? 'オン' : 'オフ') }}
        </span>
        <a class="its-btn" href="{{ $Its::publicUrl() }}" target="_blank" rel="noopener" title="お客様の窓口（新しいタブ）" aria-label="お客様の窓口"><i class="fa fa-external-link" aria-hidden="true"></i><span class="tx">お客様の窓口</span></a>
        <a class="its-btn primary" href="{{ $Its::url('new') }}"><i class="fa fa-plus" aria-hidden="true"></i><span class="tx">チケット</span></a>
    </div>
</header>
@if(!empty($needsUpgrade))
<div class="its-warn" role="status"><i class="fa fa-arrow-circle-up" aria-hidden="true"></i><div>プラグインが新しくなりました。<a href="{{ $Its::url('setup') }}">セットアップ</a>から「テーブルを更新」を押してください（データはそのままです）。</div></div>
@endif
@if(!empty($mailAuthUnset))
<div class="its-warn" role="status"><i class="fa fa-shield" aria-hidden="true"></i><div>受け取ったメールの差出人を確かめる設定が済んでいません。いまはメールで来たチケットが、すべて「なりすましの疑い」（社内だけ）になります。管理者が<a href="{{ $Its::url('settings') }}">設定</a>の「受信サーバーの名前」を入れてください。</div></div>
@endif
@if(!empty($flash))<div class="its-flash" role="status"><i class="fa fa-check-circle" aria-hidden="true"></i><div>{{ $flash }}</div></div>@endif
@if(!empty($flashError))<div class="its-flash error" role="alert"><i class="fa fa-exclamation-circle" aria-hidden="true"></i><div>{{ $flashError }}</div></div>@endif
