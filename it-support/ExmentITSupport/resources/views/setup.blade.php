@php($Its = \App\Plugins\ExmentITSupport\Its::class)
@include($Its::view('_style'))
<div class="its its-narrow">
    @if(!empty($flash))<div class="its-flash" role="status"><i class="fa fa-check-circle" aria-hidden="true"></i><div>{{ $flash }}</div></div>@endif
    @if(!empty($flashError))<div class="its-flash error" role="alert"><i class="fa fa-exclamation-circle" aria-hidden="true"></i><div>{{ $flashError }}</div></div>@endif
    @php($steps = (array) session()->get('its_setup_steps', []))
    @if(count($steps))
    <div class="its-card"><ul class="its-list">@foreach($steps as $step)<li><i class="fa fa-check" aria-hidden="true"></i><span>{{ $step }}</span></li>@endforeach</ul></div>
    @endif
    <div class="its-card its-hero">
        <span class="its-logo l" aria-hidden="true"><i class="fa fa-life-ring"></i></span>
        <h2>サポート窓口のセットアップ</h2>
        @if(!$installed)
        <p>ボタンを 1 回押すと、サポート窓口に使うテーブル・メニュー・役割グループを作ります。最初からすぐに使えるよう、SLA・チケットの種類・定型文・FAQ の例も入れます（あとで自由に変えられます）。</p>
        <ul class="its-list">
            <li><i class="fa fa-ticket" aria-hidden="true"></i><span>チケット・チケットのやりとり</span></li>
            <li><i class="fa fa-building-o" aria-hidden="true"></i><span>お客様（会社）・お客様の担当者</span></li>
            <li><i class="fa fa-clock-o" aria-hidden="true"></i><span>チケットの種類・SLA・定型文</span></li>
            <li><i class="fa fa-book" aria-hidden="true"></i><span>ナレッジ（FAQ）・IT 資産</span></li>
        </ul>
        <form method="post" action="{{ $Its::url('setup') }}">
            @csrf
            <label class="its-check" style="margin:16px 0"><input type="checkbox" class="its-sw" name="seed" value="1" checked> 例のデータを入れる</label>
            <div><button class="its-btn primary big" type="submit"><i class="fa fa-rocket" aria-hidden="true"></i>セットアップする</button></div>
        </form>
        @else
        <p><span class="its-badge" style="--c:#30a46c"><i class="fa fa-check" aria-hidden="true"></i>セットアップは終わっています</span> <span class="its-sub its-num">{{ $state['installed_at'] ?? '' }}・構成 {{ $state['schema_version'] ?? '?' }}</span></p>
        <p>プラグインを新しくしたときは、次のボタンで足りないテーブル・列だけを足します。データと、Exment の画面で変えた項目はそのままです。</p>
        <form method="post" action="{{ $Its::url('setup') }}">@csrf<input type="hidden" name="seed" value="0"><button class="its-btn" type="submit"><i class="fa fa-refresh" aria-hidden="true"></i>テーブルを更新</button></form>
        <div class="its-actions"><a class="its-btn primary" href="{{ $Its::url() }}"><i class="fa fa-inbox" aria-hidden="true"></i>受付箱へ</a><a class="its-btn" href="{{ $Its::url('settings') }}"><i class="fa fa-cog" aria-hidden="true"></i>設定</a></div>
        @endif
    </div>
</div>
