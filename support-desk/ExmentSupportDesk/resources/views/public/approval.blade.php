@extends(\App\Plugins\ExmentSupportDesk\Its::view('public._layout'))
@php($Its = \App\Plugins\ExmentSupportDesk\Its::class)
@php($v = (array) $ticket->value)
@php($waiting = ($v['approval_state'] ?? '') === 'waiting')
@section('mainclass', 'narrow')
@section('content')
<div class="card">
    <h1>承認：{{ $v['ticket_no'] ?? '' }} {{ $v['subject'] ?? '' }}</h1>
    <p class="lead">{{ $states[$v['approval_state'] ?? 'none'] ?? '' }} ・ 依頼した人：{{ $v['requester_name'] ?? '' }}（{{ $v['requester_email'] ?? '' }}）</p>
    <div style="white-space:pre-wrap;overflow-wrap:anywhere;margin-top:16px">{{ $v['description'] ?? '' }}</div>
    @if($waiting)
    <form method="post" action="{{ $Its::publicUrl('approval', (string) $ticket->id) }}" style="margin-top:20px">
        @csrf
        <label class="f" for="a-comment">ひとこと（承認はチケットに、却下は依頼した人へのメールにも書きます）</label>
        <textarea id="a-comment" name="comment" maxlength="2000"></textarea>
        <div class="actions" style="margin-top:16px">
            <button class="btn primary" type="submit" name="decision" value="approve">承認する</button>
            <button class="btn" type="submit" name="decision" value="reject" onclick="return confirm('この申請を却下します。依頼した人にメールで知らせます。')">却下する</button>
        </div>
    </form>
    @else
    <p class="sub" style="margin-top:16px">この申請は、もう承認待ちではありません。</p>
    @endif
</div>
@endsection
