@php($Its = \App\Plugins\ExmentITSupport\Its::class)
@php($R = \App\Plugins\ExmentITSupport\Report::class)
<div class="its its-page">
@include($Its::view('_nav'), ['tab' => 'dashboard'])
<form method="get" action="{{ $Its::url('dashboard') }}" class="its-row" style="margin-bottom:12px">
    <input type="month" name="month" value="{{ $month }}">
    <button class="its-btn" type="submit">表示</button>
    <a class="its-btn" href="{{ $Its::url('report.csv') }}?month={{ $month }}"><i class="fa fa-download"></i> お客様別 CSV</a>
    <span class="its-sub">この画面はチケットからその場で計算しています（更新ボタンは要りません）。</span>
</form>

<div class="its-kpis">
    <div class="its-kpi" style="--k:#4f6bed"><i class="i fa fa-inbox"></i><div class="n">{{ $kpi['created'] }}</div><div class="l">受付（{{ $month }}）</div></div>
    <div class="its-kpi" style="--k:#30a46c"><i class="i fa fa-check-circle"></i><div class="n">{{ $kpi['resolved'] }}</div><div class="l">解決（{{ $month }}）</div></div>
    <div class="its-kpi" style="--k:#3e8bea"><i class="i fa fa-spinner"></i><div class="n">{{ $kpi['open'] }}</div><div class="l">いま対応中</div></div>
    <div class="its-kpi" style="--k:#e5484d"><i class="i fa fa-exclamation-triangle"></i><div class="n" style="color:{{ $kpi['breached'] > 0 ? '#e5484d' : 'inherit' }}">{{ $kpi['breached'] }}</div><div class="l">SLA 超過中</div></div>
    <div class="its-kpi" style="--k:#0ea5a4"><i class="i fa fa-clock-o"></i><div class="n">{{ $kpi['sla'] === null ? '—' : $kpi['sla'] . '%' }}</div><div class="l">SLA 達成率（解決分）</div></div>
    <div class="its-kpi" style="--k:#7c5cf0"><i class="i fa fa-reply"></i><div class="n" style="font-size:20px">{{ $R::minutesText($kpi['first_median']) }}</div><div class="l">最初の返事まで（中央値）</div></div>
    <div class="its-kpi" style="--k:#f08c2b"><i class="i fa fa-star"></i><div class="n">{{ $kpi['csat'] === null ? '—' : $kpi['csat'] }}</div><div class="l">満足度（{{ $kpi['csat_n'] }} 件の平均・5 点満点）</div></div>
    <div class="its-kpi" style="--k:#d6409f"><i class="i fa fa-book"></i><div class="n">{{ $kpi['faq_views'] }}</div><div class="l">FAQ が読まれた回数（累計）</div></div>
</div>

<div class="its-grid3">
<section>
    <div class="its-card">
        <h3>日ごとの受付と解決 <span class="its-pill" style="background:var(--its-accent)">受付</span> <span class="its-pill" style="background:var(--its-green)">解決</span></h3>
        @php($max = max(1, max(array_map(function ($d) { return max($d['in'], $d['out']); }, $daily))))
        <div class="its-chart">
            @foreach($daily as $day => $d)
            <div title="{{ $day }} 受付 {{ $d['in'] }} / 解決 {{ $d['out'] }}"><i style="height:{{ intval($d['in'] * 100 / $max) }}%"></i><i class="out" style="height:{{ intval($d['out'] * 100 / $max) }}%"></i></div>
            @endforeach
        </div>
    </div>
    <div class="its-card" style="overflow-x:auto">
        <h3>お客様ごと（月の報告に）</h3>
        <table class="its-table">
            <thead><tr><th>お客様</th><th>受付</th><th>解決</th><th>作業時間</th><th>契約</th><th>SLA</th><th>満足度</th></tr></thead>
            <tbody>
            @forelse($byCustomer as $c)
            <tr>
                <td>@if($c['id'] !== '0')<a href="{{ $Its::url() }}?queue=all&customer={{ $c['id'] }}">{{ $c['name'] }}</a>@else{{ $c['name'] }}@endif</td>
                <td>{{ $c['count'] }}</td><td>{{ $c['resolved'] }}</td>
                <td style="{{ $c['over'] ? 'color:#c0392b;font-weight:700' : '' }}">{{ $R::minutesText($c['minutes']) }}</td>
                <td>{{ $c['contract'] === null ? '—' : $c['contract'] . ' 時間' }}</td>
                <td>{{ $c['sla'] === null ? '—' : $c['sla'] . '%' }}</td>
                <td>{{ $c['csat'] ?? '—' }}</td>
            </tr>
            @empty
            <tr><td colspan="7" class="its-sub">この月のチケットはありません。</td></tr>
            @endforelse
            </tbody>
        </table>
        <div class="its-sub">作業時間は、返事・メモに入れた分の合計です。赤は契約時間を超えたお客様です。</div>
    </div>
</section>
<aside>
    @foreach(['byChannel' => ['受付の方法', $Its::CHANNELS], 'byPriority' => ['優先度', $Its::PRIORITIES], 'byCategory' => ['種類', null], 'byAgent' => ['いま対応中（担当者ごと）', null]] as $key => [$title, $labelMap])
    @php($rows = $$key)
    @php($top = max(1, max(array_values($rows ?: [0]))))
    <div class="its-card">
        <h3>{{ $title }}</h3>
        @forelse($rows as $k => $n)
            @if($n > 0)
            <div style="margin-bottom:6px"><div class="its-row"><span>{{ $labelMap ? ($labelMap[$k] ?? $k) : $k }}</span><span class="its-sub" style="margin-left:auto">{{ $n }}</span></div><div class="its-bar"><span style="width:{{ intval($n * 100 / $top) }}%"></span></div></div>
            @endif
        @empty
            <div class="its-sub">—</div>
        @endforelse
    </div>
    @endforeach
</aside>
</div>
</div>
