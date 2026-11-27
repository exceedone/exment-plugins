@php($Its = \App\Plugins\ExmentSupportDesk\Its::class)
<div class="sd sd-page">
@include($Its::view('_nav'), ['tab' => 'problems'])
<div class="sd-head">
    <h1>問題</h1>
    <span class="sd-sub">同じ原因のチケットをまとめたものです。受付箱のチケットの右の「問題」から、つないだり作ったりします。</span>
</div>

<div class="sd-card">
    <div class="sd-row" style="justify-content:space-between;margin-bottom:10px">
        <span class="sd-sub">{{ $all ? 'すべての問題' : '調べ中・原因がわかった問題' }}（新しい順に 200 件まで）</span>
        <a class="sd-btn small" href="{{ $Its::url('problems') . ($all ? '' : '?all=1') }}">{{ $all ? '開いているものだけ' : 'すべて見る' }}</a>
    </div>
    @if(empty($rows))
    <div class="sd-emptybox">問題はありません。</div>
    @else
    <div class="sd-scroll">
    <table class="sd-table">
        <thead><tr><th scope="col">番号</th><th scope="col">件名</th><th scope="col">状態</th><th scope="col">チケット</th><th scope="col">開いている</th><th scope="col">更新</th></tr></thead>
        <tbody>
        @foreach($rows as $row)
        <tr>
            <td><a href="{{ $Its::url('problem', (string) $row['id']) }}">{{ $row['no'] }}</a></td>
            <td><a href="{{ $Its::url('problem', (string) $row['id']) }}">{{ $row['title'] }}</a></td>
            <td><span class="sd-pill">{{ $statuses[$row['status']] ?? $row['status'] }}</span></td>
            <td class="sd-num">{{ $row['tickets'] }}</td>
            <td class="sd-num">{{ $row['open'] }}</td>
            <td>{{ $row['updated'] }}</td>
        </tr>
        @endforeach
        </tbody>
    </table>
    </div>
    @endif
</div>
</div>
