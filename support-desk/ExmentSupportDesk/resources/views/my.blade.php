@php($Its = \App\Plugins\ExmentSupportDesk\Its::class)
@php($icons = ['new' => 'fa-circle-o', 'open' => 'fa-comments-o', 'pending' => 'fa-hourglass-half', 'on_hold' => 'fa-pause', 'resolved' => 'fa-check', 'closed' => 'fa-archive'])
@include($Its::view('_style'))
<div class="sd sd-page">
    <header class="sd-top">
        <span class="sd-brand"><span class="sd-logo" aria-hidden="true"><i class="fa fa-life-ring"></i></span>{{ $Its::setting('portal_title', 'サポートデスク') }}</span>
        <div class="sd-right">
            @if($isAgent)<a class="sd-btn" href="{{ $Its::url() }}"><i class="fa fa-inbox" aria-hidden="true"></i>受付箱へ</a>@endif
            <a class="sd-btn primary" href="{{ $requestUrl }}" target="_blank" rel="noopener"><i class="fa fa-plus" aria-hidden="true"></i>新しく依頼する</a>
        </div>
    </header>
    <div class="sd-card">
        <h2><i class="fa fa-ticket" aria-hidden="true"></i>{{ $isCompanyAdmin ? '会社のご依頼' : 'あなたのご依頼' }}（{{ count($tickets) }} 件）</h2>
        <div class="sd-sub sd-lead">ログインする前にメールで依頼したものも、同じメールアドレスなら出ています。</div>
        @if(count($tickets))
        <div class="sd-scroll">
        <table class="sd-table">
            <thead><tr><th>番号</th><th>件名</th><th>状態</th><th class="hide-sm">受付</th><th class="hide-sm">更新</th></tr></thead>
            <tbody>
            @foreach($tickets as $t)
                @php($st = $t->value['status'] ?? 'new')
                <tr>
                    <td class="sd-sub sd-num" style="white-space:nowrap">{{ $t->value['ticket_no'] ?? '' }}</td>
                    <td class="subj"><a href="{{ $links[$t->id] }}" target="_blank" rel="noopener"><b>{{ $t->value['subject'] ?? '' }}</b></a>@if($isCompanyAdmin)<div class="sd-sub">{{ $t->value['requester_name'] ?? '' }}</div>@endif</td>
                    <td><span class="sd-badge" style="--c:{{ $Its::STATUS_COLORS[$st] ?? '#999' }}"><i class="fa {{ $icons[$st] ?? 'fa-circle-o' }}" aria-hidden="true"></i>{{ $Its::label($Its::STATUSES, $st) }}</span></td>
                    <td class="sd-sub sd-num hide-sm" style="white-space:nowrap">{{ \Carbon\Carbon::parse($t->created_at)->format('Y/m/d') }}</td>
                    <td class="sd-sub sd-num hide-sm" style="white-space:nowrap">{{ \Carbon\Carbon::parse($t->updated_at)->format('Y/m/d H:i') }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        </div>
        @else
        <div class="sd-emptybox"><i class="fa fa-inbox" aria-hidden="true"></i>ご依頼はまだありません。</div>
        @endif
    </div>
</div>
