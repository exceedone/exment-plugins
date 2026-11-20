@extends(\App\Plugins\ExmentITSupport\Its::view('public._layout'))
@php($Its = \App\Plugins\ExmentITSupport\Its::class)
@php($v = $ticket->value)
@php($st = $v['status'] ?? 'new')
@php($order = ['new' => 0, 'open' => 1, 'pending' => 1, 'on_hold' => 1, 'resolved' => 2, 'closed' => 3])
@php($step = $order[$st] ?? 0)
@section('content')
@if(!empty($flash))<div class="flash">{{ $flash }}</div>@endif
@if($errors->any())<div class="err">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
<div class="card">
    <div class="sub">{{ $v['ticket_no'] ?? '' }} · 受付 {{ \Carbon\Carbon::parse($ticket->created_at)->format('Y/m/d H:i') }}</div>
    <h1 style="margin-top:4px">{{ $v['subject'] ?? '' }}</h1>
    <div class="steps">
        @foreach(['受付', '対応中', '解決', '完了'] as $i => $label)
        <span class="{{ $i === $step ? 'on' : ($i < $step ? 'done' : '') }}">{{ $label }}</span>
        @endforeach
    </div>
    <p style="margin:8px 0 0">
        いまの状態: <span class="pill" style="background:{{ $Its::STATUS_COLORS[$st] ?? '#999' }}">{{ $Its::label($Its::STATUSES, $st) }}</span>
        @if($st === 'pending') <b>担当者がお返事をお待ちしています。</b>@endif
        @if(!empty($v['assignee']) && isset($labels['assignee'][$v['assignee']])) <span class="sub">担当: {{ $labels['assignee'][$v['assignee']] }}</span>@endif
    </p>
</div>

@if($st === 'resolved' || $st === 'closed' || $rated)
<div class="card">
    @if(!empty($v['csat']) && !$rated)
        <p>満足度: {{ str_repeat('★', intval($v['csat'])) }}{{ str_repeat('☆', 5 - intval($v['csat'])) }}　ありがとうございました。</p>
    @else
    <form method="post" action="{{ $Its::publicUrl('t', $token, 'rate') }}">
        @csrf
        <h2>対応はいかがでしたか？</h2>
        <input type="hidden" name="rate" id="rate" value="{{ $rated ?? '' }}">
        <div class="stars" id="stars">
            @for($i = 1; $i <= 5; $i++)<button type="button" data-n="{{ $i }}" class="{{ ($rated ?? 0) >= $i ? 'on' : '' }}" aria-label="{{ $i }}">★</button>@endfor
        </div>
        <label class="f">ひとこと（なくてもかまいません）</label>
        <textarea name="comment" style="min-height:70px" maxlength="2000"></textarea>
        <div style="margin-top:10px"><button class="btn primary" type="submit">送る</button></div>
    </form>
    @endif
</div>
@endif

<div class="card">
    <h2>やりとり</h2>
    @php($firstMsg = $messages->first())
    @php($skipId = ($firstMsg && ($firstMsg->value['kind'] ?? '') === 'customer' && trim((string) ($firstMsg->value['body'] ?? '')) === trim((string) ($v['description'] ?? ''))) ? $firstMsg->id : null)
    <div class="msg customer">
        <div class="head"><b>{{ ($v['requester_name'] ?? '') ?: 'あなた' }}</b> · {{ \Carbon\Carbon::parse($ticket->created_at)->format('Y/m/d H:i') }}</div>
        <div class="body">{{ $v['description'] ?? '' }}</div>
        @if($skipId && !empty($firstMsg->value['attachments']))<div class="sub" style="margin-top:6px">添付: {{ $firstMsg->value['attachments'] }}</div>@endif
    </div>
    @foreach($messages as $m)
        @php($mv = $m->value)
        @if($m->id === $skipId)
            @continue
        @endif
        <div class="msg {{ $mv['kind'] ?? '' }}">
            <div class="head"><b>{{ ($mv['kind'] ?? '') === 'reply' ? ($mv['author_name'] ?? '担当者') . '（担当者）' : (($mv['author_name'] ?? '') ?: 'あなた') }}</b> · {{ \Carbon\Carbon::parse($m->created_at)->format('Y/m/d H:i') }}</div>
            <div class="body">{{ $mv['body'] ?? '' }}</div>
            @if(!empty($mv['attachments']))<div class="sub" style="margin-top:6px">添付: {{ $mv['attachments'] }}</div>@endif
        </div>
    @endforeach
    @if(!empty($files))
    <div class="sub" style="margin-top:8px"><b>ファイル</b>
        @foreach($files as $f)<div><a href="{{ $Its::publicUrl('t', $token, 'file', $f->uuid) }}">{{ $f->name }}</a></div>@endforeach
    </div>
    @endif
</div>

<div class="card">
    <form method="post" action="{{ $Its::publicUrl('t', $token, 'reply') }}" enctype="multipart/form-data">
        @csrf
        <h2>{{ in_array($st, ['resolved', 'closed'], true) ? 'まだ解決していない・追加で伝えたい' : '担当者に伝える' }}</h2>
        <textarea name="body" required maxlength="20000" placeholder="ここに書いて送ると、担当者に届きます。"></textarea>
        <div class="sub">パスワードは書かないでください。</div>
        <div style="margin-top:8px"><input type="file" name="files[]" multiple></div>
        <div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap">
            <button class="btn primary" type="submit">送る</button>
        </div>
    </form>
    @if(in_array($st, $Its::ACTIVE_STATUSES, true))
    <form method="post" action="{{ $Its::publicUrl('t', $token, 'solved') }}" style="margin-top:10px" onsubmit="return confirm('解決にします。よろしいですか？')">
        @csrf<button class="btn" type="submit">✓ 解決しました</button>
    </form>
    @endif
</div>
<script>
(function () {
    var s = document.getElementById('stars'); if (!s) { return; }
    var input = document.getElementById('rate');
    s.querySelectorAll('button').forEach(function (b) {
        b.addEventListener('click', function () {
            var n = parseInt(b.dataset.n, 10); input.value = n;
            s.querySelectorAll('button').forEach(function (x) { x.classList.toggle('on', parseInt(x.dataset.n, 10) <= n); });
        });
    });
})();
</script>
@endsection
