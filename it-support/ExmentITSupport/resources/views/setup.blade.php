@php($Its = \App\Plugins\ExmentITSupport\Its::class)
@include($Its::view('_style'))
<div class="its" style="max-width:820px">
    @if(!empty($flash))<div class="its-flash">{{ $flash }}</div>@endif
    @if(!empty($flashError))<div class="its-flash error">{{ $flashError }}</div>@endif
    @foreach((array) session()->get('its_setup_steps', []) as $step)<div class="its-sub">・{{ $step }}</div>@endforeach
    <div class="its-card">
        <h2 style="font-size:18px"><i class="fa fa-life-ring"></i> ITサポートのセットアップ</h2>
        @if(!$installed)
        <p>ボタンを 1 回押すと、ITサポートに使うテーブル・メニュー・役割グループを作ります。最初からすぐに使えるよう、SLA・チケットの種類・定型文・FAQ の例も入れます（あとで自由に変えられます）。</p>
        <ul>
            <li>チケット・チケットのやりとり</li>
            <li>お客様（会社）・お客様の担当者</li>
            <li>チケットの種類・SLA・定型文</li>
            <li>ナレッジ（FAQ）・IT 資産</li>
        </ul>
        <form method="post" action="{{ $Its::url('setup') }}">
            @csrf
            <label><input type="checkbox" name="seed" value="1" checked> 例のデータを入れる</label><br><br>
            <button class="its-btn primary" type="submit">セットアップする</button>
        </form>
        @else
        <p>セットアップは終わっています（{{ $state['installed_at'] ?? '' }}・構成 {{ $state['schema_version'] ?? '?' }}）。</p>
        <p>プラグインを新しくしたときは、次のボタンで足りないテーブル・列だけを足します。データと、Exment の画面で変えた項目はそのままです。</p>
        <form method="post" action="{{ $Its::url('setup') }}">@csrf<input type="hidden" name="seed" value="0"><button class="its-btn" type="submit">テーブルを更新</button></form>
        <p style="margin-top:12px"><a href="{{ $Its::url() }}">受付箱へ</a> · <a href="{{ $Its::url('settings') }}">設定</a></p>
        @endif
    </div>
</div>
