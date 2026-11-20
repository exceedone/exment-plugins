@php($Its = \App\Plugins\ExmentITSupport\Its::class)
@include($Its::view('_style'))
<div class="its">
    <div class="its-top">
        <h1><i class="fa fa-life-ring"></i> {{ $Its::setting('portal_title', 'ITサポート窓口') }}</h1>
        <div class="its-right">
            @if($isAgent)<a class="its-btn" href="{{ $Its::url() }}">受付箱へ</a>@endif
            <a class="its-btn primary" href="{{ $requestUrl }}" target="_blank" rel="noopener"><i class="fa fa-plus"></i> 新しく依頼する</a>
        </div>
    </div>
    <div class="its-card">
        <h2>{{ $isCompanyAdmin ? '会社のご依頼' : 'あなたのご依頼' }}（{{ count($tickets) }} 件）</h2>
        <div class="its-sub" style="margin-bottom:8px">ログインする前にメールで依頼したものも、同じメールアドレスなら出ています。</div>
        <table class="its-table">
            <thead><tr><th>番号</th><th>件名</th><th>状態</th><th class="hide-sm">受付</th><th class="hide-sm">更新</th></tr></thead>
            <tbody>
            @forelse($tickets as $t)
                <tr>
                    <td class="its-sub">{{ $t->value['ticket_no'] ?? '' }}</td>
                    <td class="subj"><a href="{{ $links[$t->id] }}" target="_blank" rel="noopener">{{ $t->value['subject'] ?? '' }}</a>@if($isCompanyAdmin)<div class="its-sub">{{ $t->value['requester_name'] ?? '' }}</div>@endif</td>
                    <td><span class="its-pill" style="background:{{ $Its::STATUS_COLORS[$t->value['status'] ?? 'new'] ?? '#999' }}">{{ $Its::label($Its::STATUSES, $t->value['status'] ?? 'new') }}</span></td>
                    <td class="its-sub hide-sm">{{ \Carbon\Carbon::parse($t->created_at)->format('Y/m/d') }}</td>
                    <td class="its-sub hide-sm">{{ \Carbon\Carbon::parse($t->updated_at)->format('Y/m/d H:i') }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="its-sub" style="text-align:center;padding:20px">ご依頼はまだありません。</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
