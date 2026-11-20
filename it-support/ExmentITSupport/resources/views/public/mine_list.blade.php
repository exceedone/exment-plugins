@extends(\App\Plugins\ExmentITSupport\Its::view('public._layout'))
@php($Its = \App\Plugins\ExmentITSupport\Its::class)
@section('content')
<div class="card list">
    <h1>ご依頼の一覧</h1>
    <p class="sub">{{ $email }} からのご依頼です。</p>
    @forelse($tickets as $t)
    @php($st = $t->value['status'] ?? 'new')
    <a class="item" href="{{ $links[$t->id] }}">
        <span class="pill" style="background:{{ $Its::STATUS_COLORS[$st] ?? '#999' }}">{{ $Its::label($Its::STATUSES, $st) }}</span>
        <b>{{ $t->value['subject'] ?? '' }}</b>
        <div class="sub">{{ $t->value['ticket_no'] ?? '' }} · 受付 {{ \Carbon\Carbon::parse($t->created_at)->format('Y/m/d') }} · 更新 {{ \Carbon\Carbon::parse($t->updated_at)->format('Y/m/d H:i') }}</div>
    </a>
    @empty
    <p>ご依頼はありません。</p>
    @endforelse
</div>
@endsection
