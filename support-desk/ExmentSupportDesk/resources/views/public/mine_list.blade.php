@extends(\App\Plugins\ExmentSupportDesk\Its::view('public._layout'))
@php($Its = \App\Plugins\ExmentSupportDesk\Its::class)
@section('content')
<div class="card list">
    <h1>ご依頼の一覧</h1>
    <p class="lead">{{ $email }} からのご依頼です。</p>
    @forelse($tickets as $t)
    @php($st = $t->value['status'] ?? 'new')
    <a class="item" href="{{ $links[$t->id] }}">
        <span class="m">
            <b>{{ $t->value['subject'] ?? '' }}</b>
            <span class="sub num"><span class="tno">{{ $t->value['ticket_no'] ?? '' }}</span>受付 {{ \Carbon\Carbon::parse($t->created_at)->format('Y/m/d') }} · 更新 {{ \Carbon\Carbon::parse($t->updated_at)->format('Y/m/d H:i') }}</span>
        </span>
        <span class="pill" style="--c:{{ $Its::STATUS_COLORS[$st] ?? '#999' }}">{{ $Its::label($Its::STATUSES, $st) }}</span>
        <svg class="ic" aria-hidden="true"><use href="#i-right"/></svg>
    </a>
    @empty
    <div class="empty"><div class="i" aria-hidden="true"><svg class="ic l"><use href="#i-list"/></svg></div><p>ご依頼はありません。</p></div>
    @endforelse
</div>
@endsection
