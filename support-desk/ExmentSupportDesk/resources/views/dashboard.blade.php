@php($Its = \App\Plugins\ExmentITSupport\Its::class)
@php($R = \App\Plugins\ExmentITSupport\Report::class)
@php($monthLabel = \Carbon\Carbon::createFromFormat('Y-m-d', $month . '-01')->format('Y 年 n 月'))
<div class="its its-page">
@include($Its::view('_nav'), ['tab' => 'dashboard'])
<div class="its-head">
    <h1>レポート</h1>
    <span class="its-sub">チケットからその場で計算しています（更新ボタンは要りません）。</span>
    <form method="get" action="{{ $Its::url('dashboard') }}" class="act">
        <input type="month" name="month" value="{{ $month }}" aria-label="月">
        <select name="group" aria-label="受付箱のまとまり"><option value="">すべての受付箱</option>@foreach($groups as $gk => $gl)<option value="{{ $gk }}" @if($group === $gk) selected @endif>{{ $gl }}</option>@endforeach</select>
        <button class="its-btn" type="submit">表示</button>
        <a class="its-btn" href="{{ $Its::url('report.csv') }}?month={{ $month }}"><i class="fa fa-download" aria-hidden="true"></i>お客様別 CSV</a>
    </form>
</div>

<div class="its-kpis">
    <div class="its-kpi" style="--k:#4f6bed"><div class="l">受付</div><div class="n">{{ $kpi['created'] }}<small>件</small></div><div class="h">{{ $monthLabel }}</div><i class="i fa fa-inbox" aria-hidden="true"></i></div>
    <div class="its-kpi" style="--k:#30a46c"><div class="l">解決</div><div class="n">{{ $kpi['resolved'] }}<small>件</small></div><div class="h">{{ $monthLabel }}</div><i class="i fa fa-check-circle" aria-hidden="true"></i></div>
    <div class="its-kpi" style="--k:#3e8bea"><div class="l">いま対応中</div><div class="n">{{ $kpi['open'] }}<small>件</small></div><div class="h">いまの数（月によりません）</div><i class="i fa fa-comments-o" aria-hidden="true"></i></div>
    <div class="its-kpi {{ $kpi['breached'] > 0 ? 'alert' : '' }}" style="--k:#e5484d"><div class="l">SLA 超過中</div><div class="n">{{ $kpi['breached'] }}<small>件</small></div><div class="h">{{ $kpi['breached'] > 0 ? '期限を過ぎたチケットがあります' : '期限を過ぎたチケットはありません' }}</div><i class="i fa fa-exclamation-triangle" aria-hidden="true"></i></div>
    <div class="its-kpi" style="--k:#0ea5a4"><div class="l">SLA 達成率</div><div class="n">@if($kpi['sla'] === null)—@else{{ $kpi['sla'] }}<small>%</small>@endif</div><div class="h">解決したチケットのうち</div><i class="i fa fa-clock-o" aria-hidden="true"></i></div>
    <div class="its-kpi" style="--k:#7c5cf0"><div class="l">最初の返事まで</div><div class="n">{{ $R::minutesText($kpi['first_median']) }}</div><div class="h">中央値</div><i class="i fa fa-reply" aria-hidden="true"></i></div>
    <div class="its-kpi" style="--k:#f08c2b"><div class="l">満足度</div><div class="n">@if($kpi['csat'] === null)—@else{{ $kpi['csat'] }}<small>/ 5</small>@endif</div><div class="h">{{ $kpi['csat_n'] }} 件の平均・5 点満点</div><i class="i fa fa-star" aria-hidden="true"></i></div>
    <div class="its-kpi" style="--k:#d6409f"><div class="l">FAQ が読まれた回数</div><div class="n">{{ $kpi['faq_views'] }}<small>回</small></div><div class="h">累計</div><i class="i fa fa-book" aria-hidden="true"></i></div>
</div>

<div class="its-card">
    <h3><i class="fa fa-inbox" aria-hidden="true"></i>受付箱ごと</h3>
    @if(count($byBox))
    <div class="its-scroll">
    <table class="its-table">
        <thead><tr><th>受付箱</th><th class="r">受付</th><th class="r">解決</th><th class="r">いま対応中</th><th class="r">進捗率（平均）</th><th class="r">最初の返事まで</th><th class="r">SLA</th><th class="r">受注率</th></tr></thead>
        <tbody>
        @foreach($byBox as $b)
        <tr>
            <td>@if($b['id'] !== '0')<a href="{{ $Its::url() }}?queue=all&category={{ $b['id'] }}"><b>{{ $b['name'] }}</b></a>@else<span class="its-sub" style="font-size:inherit">{{ $b['name'] }}</span>@endif <span class="its-sub">{{ $kinds[$b['kind']] ?? '' }}</span></td>
            <td class="r">{{ $b['created'] }}</td><td class="r">{{ $b['resolved'] }}</td><td class="r">{{ $b['open'] }}</td>
            <td class="r">{{ $b['progress'] === null ? '—' : $b['progress'] . '%' }}</td>
            <td class="r">{{ $R::minutesText($b['first_median']) }}</td>
            <td class="r">{{ $b['sla'] === null ? '—' : $b['sla'] . '%' }}</td>
            <td class="r">@if($b['win_rate'] === null)—@else{{ $b['win_rate'] }}%<span class="its-sub">（{{ $b['won'] }} / {{ $b['won'] + $b['lost'] }}）</span>@endif</td>
        </tr>
        @endforeach
        </tbody>
    </table>
    </div>
    <div class="its-sub" style="margin-top:10px">受付・解決・SLA・最初の返事はこの月、いま対応中と進捗率はいまの数です。受注率は見積の受付箱で、この月に終わったもの（受注 ÷（受注＋失注））です。</div>
    @else
    <div class="its-emptybox"><i class="fa fa-inbox" aria-hidden="true"></i>この月のチケットはありません。</div>
    @endif
</div>

<div class="its-grid3">
<section>
    <div class="its-card">
        <h3><i class="fa fa-bar-chart" aria-hidden="true"></i>日ごとの受付と解決
            <span class="its-legend" aria-hidden="true"><span><i></i>受付</span><span><i class="out"></i>解決</span></span></h3>
        @php($max = max(1, max(array_map(function ($d) { return max($d['in'], $d['out']); }, $daily))))
        @php($top = max(2, $max + ($max % 2)))
        @php($week = ['日', '月', '火', '水', '木', '金', '土'])
        <div class="its-plot">
            <div class="y" aria-hidden="true"><span>{{ $top }}</span><span>{{ intdiv($top, 2) }}</span><span>0</span></div>
            <div class="its-chart" role="group" aria-label="日ごとの受付と解決（{{ $monthLabel }}）">
                @foreach($daily as $day => $d)
                @php($dt = \Carbon\Carbon::parse($day))
                <div tabindex="0" aria-label="{{ $dt->format('n/j') }} 受付 {{ $d['in'] }} 件、解決 {{ $d['out'] }} 件"><i class="{{ $d['in'] ? '' : 'z' }}" style="height:{{ round($d['in'] * 100 / $top, 1) }}%"></i><i class="out {{ $d['out'] ? '' : 'z' }}" style="height:{{ round($d['out'] * 100 / $top, 1) }}%"></i><span class="tip" aria-hidden="true"><b>{{ $dt->format('n/j') }}（{{ $week[$dt->dayOfWeek] }}）</b>受付 {{ $d['in'] }} 件<br>解決 {{ $d['out'] }} 件</span></div>
                @endforeach
            </div>
            <div class="x" aria-hidden="true">
                @foreach($daily as $day => $d)
                @php($n = intval(substr($day, 8, 2)))
                <span>{{ $n === 1 || $n % 5 === 0 ? $n : '' }}</span>
                @endforeach
            </div>
        </div>
        <div class="its-sub" style="margin-top:10px">{{ $monthLabel }}の合計: 受付 {{ $kpi['created'] }} 件・解決 {{ $kpi['resolved'] }} 件</div>
    </div>
    <div class="its-card">
        <h3><i class="fa fa-building-o" aria-hidden="true"></i>お客様ごと（月の報告に）</h3>
        @if(count($byCustomer))
        <div class="its-scroll">
        <table class="its-table">
            <thead><tr><th>お客様</th><th class="r">受付</th><th class="r">解決</th><th class="r">作業時間</th><th class="r">契約</th><th class="r">SLA</th><th class="r">満足度</th></tr></thead>
            <tbody>
            @foreach($byCustomer as $c)
            <tr>
                <td>@if($c['id'] !== '0')<a href="{{ $Its::url() }}?queue=all&customer={{ $c['id'] }}"><b>{{ $c['name'] }}</b></a>@else<span class="its-sub" style="font-size:inherit">{{ $c['name'] }}</span>@endif</td>
                <td class="r">{{ $c['count'] }}</td><td class="r">{{ $c['resolved'] }}</td>
                <td class="r {{ $c['over'] ? 'over' : '' }}">@if($c['over'])<i class="fa fa-exclamation-triangle" aria-hidden="true" title="契約時間を超えています"></i> @endif{{ $R::minutesText($c['minutes']) }}@if($c['over'])<span class="its-badge" style="--c:#e5484d;margin-left:6px">超過</span>@endif</td>
                <td class="r">{{ $c['contract'] === null ? '—' : $c['contract'] . ' 時間' }}</td>
                <td class="r">{{ $c['sla'] === null ? '—' : $c['sla'] . '%' }}</td>
                <td class="r">{{ $c['csat'] ?? '—' }}</td>
            </tr>
            @endforeach
            </tbody>
        </table>
        </div>
        <div class="its-sub" style="margin-top:10px">作業時間は、返事・メモに入れた分の合計です。赤は契約時間を超えたお客様です。</div>
        @else
        <div class="its-emptybox"><i class="fa fa-inbox" aria-hidden="true"></i>この月のチケットはありません。</div>
        @endif
    </div>
</section>
<aside>
    @foreach(['byChannel' => ['受付の方法', $Its::CHANNELS, 'fa-sign-in'], 'byPriority' => ['優先度', $Its::PRIORITIES, 'fa-flag-o'], 'byCategory' => ['受付箱', null, 'fa-inbox'], 'byAgent' => ['いま対応中（担当者ごと）', null, 'fa-users']] as $key => [$title, $labelMap, $icon])
    @php($rows = array_filter($$key ?: [], function ($n) { return $n > 0; }))
    @php($top = max(1, max(array_values($rows ?: [0]))))
    @php($sum = max(1, array_sum($rows)))
    <div class="its-card">
        <h3><i class="fa {{ $icon }}" aria-hidden="true"></i>{{ $title }}</h3>
        @forelse($rows as $k => $n)
            <div class="its-meter"><div class="t"><span>{{ $labelMap ? ($labelMap[$k] ?? $k) : $k }}</span><b>{{ $n }}<small>{{ intval(round($n * 100 / $sum)) }}%</small></b></div><div class="its-bar" aria-hidden="true"><span style="width:{{ intval($n * 100 / $top) }}%"></span></div></div>
        @empty
            <div class="its-sub">—</div>
        @endforelse
    </div>
    @endforeach
</aside>
</div>
</div>
