@extends(\App\Plugins\ExmentITSupport\Its::view('public._layout'))
@php($Its = \App\Plugins\ExmentITSupport\Its::class)
@section('content')
<div class="card">
    <p class="sub"><a href="{{ $Its::publicUrl('faq') }}">よくある質問</a></p>
    <h1>{{ $row->value['title'] ?? '' }}</h1>
    <div style="white-space:pre-wrap;word-break:break-word">{{ $row->value['body'] ?? '' }}</div>
</div>
<div class="card">
    @if($voted)
        <p>ご協力ありがとうございました。</p>
    @else
        <form method="post" action="{{ $Its::publicUrl('faq', (string) $row->id, 'vote') }}" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
            @csrf
            <span>この答えは役に立ちましたか？</span>
            <button class="btn" name="helpful" value="1">はい</button>
            <button class="btn" name="helpful" value="0">いいえ</button>
        </form>
    @endif
    <p class="sub" style="margin-top:12px">解決しないときは <a href="{{ $Its::publicUrl('request') }}">こちらから依頼してください</a>。</p>
</div>
@endsection
