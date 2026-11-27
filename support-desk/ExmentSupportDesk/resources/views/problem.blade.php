@php($Its = \App\Plugins\ExmentITSupport\Its::class)
@php($v = (array) $problem->value)
@php($open = $tickets->filter(function ($t) use ($Its) { return in_array((string) array_get($t->value, 'status'), $Its::ACTIVE_STATUSES, true); })->count())
<div class="its its-page">
@include($Its::view('_nav'), ['tab' => 'problems'])
<div class="its-head">
    <h1>{{ $v['problem_no'] ?? '' }} {{ $v['title'] ?? '' }}</h1>
    <span class="its-sub"><a href="{{ $Its::url('problems') }}">問題の一覧</a> ・ {{ $statuses[$v['status'] ?? ''] ?? '' }} ・ つないだチケット {{ $tickets->count() }} 件（開いている {{ $open }} 件）</span>
</div>

<div class="its-grid3">
<section>
<form method="post" action="{{ $Its::url('problem', (string) $problem->id) }}">
@csrf
    <div class="its-card">
        <h2><i class="fa fa-bug" aria-hidden="true"></i>問題</h2>
        <div class="its-form-grid">
            <label for="p-title">件名</label><input type="text" id="p-title" name="title" value="{{ $v['title'] ?? '' }}" maxlength="250" required>
            <label for="p-status">状態</label><select id="p-status" name="status">@foreach($statuses as $k => $l)<option value="{{ $k }}" @selected(($v['status'] ?? '') === $k)>{{ $l }}</option>@endforeach</select>
            <label for="p-desc" class="top">起きていること</label><textarea id="p-desc" name="description" style="min-height:70px">{{ $v['description'] ?? '' }}</textarea>
            <label for="p-cause" class="top">原因</label><textarea id="p-cause" name="root_cause" style="min-height:70px">{{ $v['root_cause'] ?? '' }}</textarea>
            <label for="p-work" class="top">回避のしかた</label><textarea id="p-work" name="workaround" style="min-height:70px" placeholder="直るまでの間の、とりあえずの方法">{{ $v['workaround'] ?? '' }}</textarea>
            <label for="p-res" class="top">解決のしかた</label><textarea id="p-res" name="resolution" style="min-height:70px">{{ $v['resolution'] ?? '' }}</textarea>
        </div>
        <div class="its-row" style="margin-top:10px">
            <button class="its-btn primary" type="submit"><i class="fa fa-save" aria-hidden="true"></i>保存</button>
            <a class="its-btn ghost" href="{{ $Its::dataUrl($Its::T_PROBLEM, $problem->id, 'edit') }}">ほかの項目（Exment の画面）</a>
        </div>
    </div>
</form>
</section>

<section>
    <div class="its-card">
        <h2><i class="fa fa-ticket" aria-hidden="true"></i>つないだチケット</h2>
        @if($tickets->isEmpty())
        <div class="its-emptybox">まだつないだチケットはありません。受付箱のチケットの右の「問題」で、番号 {{ $v['problem_no'] ?? '' }} を入れてつなぎます。</div>
        @else
        <ul class="its-list">
            @foreach($tickets as $t)
            <li class="its-row" style="justify-content:space-between">
                <span><a href="{{ $Its::url('ticket', (string) $t->id) }}">{{ array_get($t->value, 'ticket_no') }}</a> {{ array_get($t->value, 'subject') }} <span class="its-pill">{{ $ticketStatuses[(string) array_get($t->value, 'status')] ?? '' }}</span></span>
                <form method="post" action="{{ $Its::url('problem', (string) $problem->id) }}">@csrf<input type="hidden" name="action" value="unlink"><input type="hidden" name="ticket" value="{{ $t->id }}"><button class="its-btn small ghost" type="submit">外す</button></form>
            </li>
            @endforeach
        </ul>
        @endif
    </div>

    <div class="its-card">
        <h2><i class="fa fa-check-circle" aria-hidden="true"></i>まとめて解決</h2>
        <form method="post" action="{{ $Its::url('problem', (string) $problem->id) }}" onsubmit="return confirm('開いているチケット {{ $open }} 件を「解決」にします。よろしいですか？')">@csrf
            <input type="hidden" name="action" value="resolve">
            <label for="p-note" class="its-sub">チケットに書くメモ（社内。空なら「解決のしかた」、それもなければ「回避のしかた」）</label>
            <textarea id="p-note" name="note" style="width:100%;min-height:60px"></textarea>
            <button class="its-btn" type="submit" @disabled($open === 0)><i class="fa fa-check" aria-hidden="true"></i>開いているチケット {{ $open }} 件を解決にする</button>
        </form>
        <div class="its-sub" style="margin-top:8px">お客様にはメールを送りません。お客様に知らせるときは、チケットごとに返信してください。</div>
    </div>

    <div class="its-card">
        <h2><i class="fa fa-book" aria-hidden="true"></i>ナレッジ</h2>
        @if(!empty($v['knowledge_id']))
        <div class="its-sub" style="margin-bottom:8px"><a href="{{ $Its::dataUrl($Its::T_KNOWLEDGE, $v['knowledge_id']) }}">作ったナレッジを開く</a></div>
        @endif
        <form method="post" action="{{ $Its::url('problem', (string) $problem->id) }}">@csrf<input type="hidden" name="action" value="knowledge"><button class="its-btn small" type="submit"><i class="fa fa-plus" aria-hidden="true"></i>原因・回避・解決からナレッジの下書きを作る</button></form>
    </div>
</section>
</div>
</div>
