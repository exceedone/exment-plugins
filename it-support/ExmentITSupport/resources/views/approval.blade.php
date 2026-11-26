@php($Its = \App\Plugins\ExmentITSupport\Its::class)
@php($v = (array) $ticket->value)
@php($waiting = ($v['approval_state'] ?? '') === 'waiting')
<div class="its its-page">
@if(!empty($flash))<div class="its-flash" role="status"><i class="fa fa-check-circle" aria-hidden="true"></i><div>{{ $flash }}</div></div>@endif
@if(!empty($flashError))<div class="its-flash error" role="alert"><i class="fa fa-exclamation-circle" aria-hidden="true"></i><div>{{ $flashError }}</div></div>@endif
<div class="its-head">
    <h1>承認：{{ $v['ticket_no'] ?? '' }} {{ $v['subject'] ?? '' }}</h1>
    <span class="its-sub">{{ $states[$v['approval_state'] ?? 'none'] ?? '' }} ・ 依頼した人：{{ $v['requester_name'] ?? '' }}（{{ $v['requester_email'] ?? '' }}）</span>
</div>
<div class="its-card">
    <h2><i class="fa fa-file-text-o" aria-hidden="true"></i>申請の中身</h2>
    <div style="white-space:pre-wrap;overflow-wrap:anywhere">{{ $v['description'] ?? '' }}</div>
</div>
@if($waiting)
<div class="its-card">
    <h2><i class="fa fa-gavel" aria-hidden="true"></i>承認する・却下する</h2>
    <form method="post" action="{{ $Its::url('approval', (string) $ticket->id) }}">@csrf
        <label for="a-comment" class="its-sub">ひとこと（承認はチケットに、却下は依頼した人へのメールにも書きます）</label>
        <textarea id="a-comment" name="comment" maxlength="2000" style="width:100%;min-height:70px"></textarea>
        <div class="its-row" style="margin-top:10px">
            <button class="its-btn primary" type="submit" name="decision" value="approve"><i class="fa fa-check" aria-hidden="true"></i>承認する</button>
            <button class="its-btn danger" type="submit" name="decision" value="reject" onclick="return confirm('この申請を却下します。依頼した人にメールで知らせます。')"><i class="fa fa-times" aria-hidden="true"></i>却下する</button>
        </div>
    </form>
</div>
@else
<div class="its-card"><div class="its-sub">この申請は、もう承認待ちではありません（{{ $states[$v['approval_state'] ?? 'none'] ?? '' }}）。</div></div>
@endif
</div>
