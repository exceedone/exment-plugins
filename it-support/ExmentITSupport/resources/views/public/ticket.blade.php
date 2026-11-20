@extends(\App\Plugins\ExmentITSupport\Its::view('public._layout'))
@php($Its = \App\Plugins\ExmentITSupport\Its::class)
@php($v = $ticket->value)
@php($st = $v['status'] ?? 'new')
@php($order = ['new' => 0, 'open' => 1, 'pending' => 1, 'on_hold' => 1, 'resolved' => 2, 'closed' => 3])
@php($step = $order[$st] ?? 0)
@section('content')
@if(!empty($flash))<div class="flash" role="status"><svg class="ic" aria-hidden="true"><use href="#i-check"/></svg><div>{{ $flash }}</div></div>@endif
@if($errors->any())<div class="err" role="alert"><svg class="ic" aria-hidden="true"><use href="#i-alert"/></svg><div>@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div></div>@endif
<div class="card">
    <div class="sub"><span class="tno">{{ $v['ticket_no'] ?? '' }}</span><span class="num">受付 {{ \Carbon\Carbon::parse($ticket->created_at)->format('Y/m/d H:i') }}</span></div>
    <h1 style="margin-top:6px">{{ $v['subject'] ?? '' }}</h1>
    <ol class="steps" aria-label="進み具合">
        @foreach(['受付', '対応中', '解決', '完了'] as $i => $label)
        <li class="{{ $i === $step ? 'on' : ($i < $step ? 'done' : '') }}" @if($i === $step) aria-current="step" @endif>{{ $label }}</li>
        @endforeach
    </ol>
    <p class="now">
        <span>いまの状態:</span> <span class="pill" style="--c:{{ $Its::STATUS_COLORS[$st] ?? '#999' }}">{{ $Its::label($Its::STATUSES, $st) }}</span>
        @if($st === 'pending') <b>担当者がお返事をお待ちしています。</b>@endif
        @if(!empty($v['assignee']) && isset($labels['assignee'][$v['assignee']])) <span class="sub" style="margin-left:auto">担当: {{ $labels['assignee'][$v['assignee']] }}</span>@endif
    </p>
</div>

@if($st === 'resolved' || $st === 'closed' || $rated)
<div class="card">
    @if(!empty($v['csat']) && !$rated)
        <p style="margin:0"><span class="sub">満足度:</span> <span class="rated" aria-label="5 点中 {{ intval($v['csat']) }} 点">{{ str_repeat('★', intval($v['csat'])) }}{{ str_repeat('☆', 5 - intval($v['csat'])) }}</span>ありがとうございました。</p>
    @else
    <form method="post" action="{{ $Its::publicUrl('t', $token, 'rate') }}">
        @csrf
        <h2>対応はいかがでしたか？</h2>
        <input type="hidden" name="rate" id="rate" value="{{ $rated ?? '' }}">
        <div class="stars" id="stars" role="group" aria-label="5 段階の評価">
            @for($i = 1; $i <= 5; $i++)<button type="button" data-n="{{ $i }}" class="{{ ($rated ?? 0) >= $i ? 'on' : '' }}" aria-label="{{ $i }}" aria-pressed="{{ ($rated ?? 0) >= $i ? 'true' : 'false' }}">★</button>@endfor
        </div>
        <label class="f" for="comment">ひとこと（なくてもかまいません）</label>
        <textarea id="comment" name="comment" style="min-height:80px" maxlength="2000"></textarea>
        <div class="actions"><button class="btn primary" type="submit">送る</button></div>
    </form>
    @endif
</div>
@endif

<div class="card">
    <h2><svg class="ic" aria-hidden="true"><use href="#i-chat"/></svg>やりとり</h2>
    @php($firstMsg = $messages->first())
    @php($skipId = ($firstMsg && ($firstMsg->value['kind'] ?? '') === 'customer' && trim((string) ($firstMsg->value['body'] ?? '')) === trim((string) ($v['description'] ?? ''))) ? $firstMsg->id : null)
    <div class="thread">
    <div class="msg customer">
        <span class="av" aria-hidden="true"><svg class="ic"><use href="#i-user"/></svg></span>
        <div class="bub">
            <div class="head"><b>{{ ($v['requester_name'] ?? '') ?: 'あなた' }}</b><time>{{ \Carbon\Carbon::parse($ticket->created_at)->format('Y/m/d H:i') }}</time></div>
            <div class="body">{{ $v['description'] ?? '' }}</div>
            @if($skipId && !empty($firstMsg->value['attachments']))<div class="att"><svg class="ic s" aria-hidden="true"><use href="#i-clip"/></svg><span>添付: {{ $firstMsg->value['attachments'] }}</span></div>@endif
        </div>
    </div>
    @foreach($messages as $m)
        @php($mv = $m->value)
        @if($m->id === $skipId)
            @continue
        @endif
        @php($isReply = ($mv['kind'] ?? '') === 'reply')
        <div class="msg {{ $mv['kind'] ?? '' }}">
            <span class="av" aria-hidden="true"><svg class="ic"><use href="#{{ $isReply ? 'i-agent' : 'i-user' }}"/></svg></span>
            <div class="bub">
                <div class="head"><b>{{ $isReply ? ($mv['author_name'] ?? '担当者') . '（担当者）' : (($mv['author_name'] ?? '') ?: 'あなた') }}</b><time>{{ \Carbon\Carbon::parse($m->created_at)->format('Y/m/d H:i') }}</time></div>
                <div class="body">{{ $mv['body'] ?? '' }}</div>
                @if(!empty($mv['attachments']))<div class="att"><svg class="ic s" aria-hidden="true"><use href="#i-clip"/></svg><span>添付: {{ $mv['attachments'] }}</span></div>@endif
            </div>
        </div>
    @endforeach
    </div>
    @if(!empty($files))
    <div class="files"><b>ファイル</b>
        <div>@foreach($files as $f)<a href="{{ $Its::publicUrl('t', $token, 'file', $f->uuid) }}"><svg class="ic s" aria-hidden="true"><use href="#i-file"/></svg>{{ $f->name }}</a>@endforeach</div>
    </div>
    @endif
</div>

<div class="card">
    <form method="post" action="{{ $Its::publicUrl('t', $token, 'reply') }}" enctype="multipart/form-data">
        @csrf
        <h2><svg class="ic" aria-hidden="true"><use href="#i-pencil"/></svg><label for="reply">{{ in_array($st, ['resolved', 'closed'], true) ? 'まだ解決していない・追加で伝えたい' : '担当者に伝える' }}</label></h2>
        <textarea id="reply" name="body" required maxlength="20000" placeholder="ここに書いて送ると、担当者に届きます。" style="min-height:130px"></textarea>
        <div class="hint"><svg class="ic s" aria-hidden="true"><use href="#i-lock"/></svg><span>パスワードは書かないでください。</span></div>
        <div style="margin-top:12px"><input type="file" name="files[]" multiple aria-label="ファイル"></div>
        <div class="actions">
            <button class="btn primary" type="submit"><svg class="ic" aria-hidden="true"><use href="#i-send"/></svg>送る</button>
        </div>
    </form>
    @if(in_array($st, $Its::ACTIVE_STATUSES, true))
    <div class="sep">または</div>
    <form method="post" action="{{ $Its::publicUrl('t', $token, 'solved') }}" onsubmit="return confirm('解決にします。よろしいですか？')">
        @csrf<button class="btn ok" type="submit"><svg class="ic" aria-hidden="true"><use href="#i-check"/></svg>解決しました</button>
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
            s.querySelectorAll('button').forEach(function (x) { var on = parseInt(x.dataset.n, 10) <= n; x.classList.toggle('on', on); x.setAttribute('aria-pressed', on ? 'true' : 'false'); });
        });
    });
})();
</script>
@endsection
