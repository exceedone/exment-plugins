{{-- The 受付箱 and their website forms (ISS02941): Desk@boxes --}}
@php($Its = \App\Plugins\ExmentSupportDesk\Its::class)
<div class="sd sd-page">
@include($Its::view('_nav'), ['tab' => 'boxes'])
<div class="sd-head">
    <h1>受付箱</h1>
    <span class="sd-sub">受付箱ごとの流れ・SLA・項目は、<a href="{{ $Its::dataUrl($Its::T_CATEGORY) }}">受付箱のテーブル</a>で決めます。ここでは、Web サイトにはめ込むフォームを作ります。</span>
</div>

@if(!$formsOn)
<div class="sd-card"><div class="sd-sub">
    @if(!$serviceOn)
    Web サイトのフォームは、Exment 7.3.0 以降の公開フォームで作ります。この Exment にはまだありません。
    @else
    Web サイトのフォームは、いまは使っていません。管理者が「機能の管理」の「サポートデスク：Web サイトのフォーム」を使うにすると、ここで作れます。
    @endif
</div></div>
@endif

@foreach($groups as $gkey => $glabel)
<div class="sd-card">
    <h2 style="margin:0 0 10px;font-size:15px">{{ $glabel }}</h2>
    @php($rows = array_filter($boxes, function ($b) use ($gkey) { return $b['group'] === $gkey; }))
    @if(empty($rows))
    <div class="sd-emptybox">この受付箱はありません。</div>
    @endif
    @foreach($rows as $b)
    <div class="sd-box" style="border-top:1px solid var(--sd-line,#e3e7ec);padding:10px 0">
        <div class="sd-row" style="justify-content:space-between;gap:8px;flex-wrap:wrap">
            <div>
                <b>{{ $b['name'] }}</b> <span class="sd-pill">{{ $kinds[$b['kind']] ?? $b['kind'] }}</span>
                <div class="sd-sub">{{ implode(' → ', array_column($b['stages'], 'name')) }}</div>
            </div>
            @if($formsOn)
            <form method="post" action="{{ $Its::url('boxes', (string) $b['id'], 'web-form') }}">@csrf
                <button class="sd-btn small{{ empty($b['tags']) ? ' primary' : '' }}" type="submit"><i class="fa fa-globe" aria-hidden="true"></i>{{ empty($b['tags']) ? 'Web サイトのフォームを作る' : 'フォームを新しくする（項目を変えたとき）' }}</button>
            </form>
            @endif
        </div>
        @if(!empty($b['chat']))
        <div style="margin-top:8px">
            <label class="sd-sub" style="display:block">Web サイトのチャットの窓（サイトに貼るコード）
                <span class="sd-row" style="gap:6px;align-items:stretch">
                    <textarea readonly rows="1" style="flex:1;font-family:monospace;font-size:12px" id="c-{{ $b['id'] }}">{{ $b['chat']['script'] }}</textarea>
                    <button type="button" class="sd-btn small" data-copy="c-{{ $b['id'] }}"><i class="fa fa-clipboard" aria-hidden="true"></i>コピー</button>
                </span>
            </label>
            <div class="sd-sub">出せるサイト：{{ $b['chat']['sites'] === '*' ? 'どこでも（受付箱のテーブルの「チャットの窓を出してよいサイト」で決められます）' : $b['chat']['sites'] }}。送られた話はこの受付箱のチケット（受付の方法：Web サイトのチャット）になります。</div>
        </div>
        @endif
        @if($formsOn && !empty($b['tags']))
        <div class="sd-tags" style="margin-top:8px;display:grid;gap:6px">
            @foreach(['script' => 'サイトに貼るコード（おすすめ）', 'iframe' => 'iframe で貼るとき', 'url' => 'フォームの URL（リンクで開くとき）'] as $k => $label)
            <label class="sd-sub" style="display:block">{{ $label }}
                <span class="sd-row" style="gap:6px;align-items:stretch">
                    <textarea readonly rows="{{ $k === 'url' ? 1 : 2 }}" style="flex:1;font-family:monospace;font-size:12px" id="t-{{ $b['id'] }}-{{ $k }}">{{ $b['tags'][$k] }}</textarea>
                    <button type="button" class="sd-btn small" data-copy="t-{{ $b['id'] }}-{{ $k }}"><i class="fa fa-clipboard" aria-hidden="true"></i>コピー</button>
                </span>
            </label>
            @endforeach
            @if(!$b['tags']['active'])<div class="sd-sub">このフォームは、いま止めてあります（公開フォームの画面で「有効」にします）。</div>@endif
            <div class="sd-sub">送られたものは、この受付箱のチケットになり、入力したメールアドレスに受付のメールが届きます。https のサイトに貼ってください（http だと、ほかのサイトの中では送れません）。</div>
        </div>
        @endif
    </div>
    @endforeach
</div>
@endforeach
</div>
<script>
document.querySelectorAll('.sd [data-copy]').forEach(function (b) {
    b.addEventListener('click', function () {
        var t = document.getElementById(b.getAttribute('data-copy'));
        var done = function () { var old = b.innerHTML; b.textContent = 'コピーしました'; setTimeout(function () { b.innerHTML = old; }, 1500); };
        if (navigator.clipboard && window.isSecureContext) { navigator.clipboard.writeText(t.value).then(done); } else { t.select(); document.execCommand('copy'); done(); }
    });
});
</script>
