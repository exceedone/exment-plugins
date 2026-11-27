@extends(\App\Plugins\ExmentITSupport\Its::view('public._layout'))
@php($Its = \App\Plugins\ExmentITSupport\Its::class)
@section('content')
<div class="card">
    <a class="back" href="{{ $Its::publicUrl('faq') }}"><svg class="ic s" aria-hidden="true"><use href="#i-left"/></svg>よくある質問</a>
    <h1>{{ $row->value['title'] ?? '' }}</h1>
    <div class="prose">{{ $row->value['body'] ?? '' }}</div>
</div>
<div class="card">
    @if($voted)
        <p style="margin:0;display:flex;gap:8px;align-items:center"><svg class="ic" aria-hidden="true" style="color:var(--green)"><use href="#i-check"/></svg>ご協力ありがとうございました。</p>
    @else
        <form method="post" action="{{ $Its::publicUrl('faq', (string) $row->id, 'vote') }}" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
            @csrf
            <b style="margin-right:6px">この答えは役に立ちましたか？</b>
            <button class="btn" name="helpful" value="1"><svg class="ic" aria-hidden="true"><use href="#i-check"/></svg>はい</button>
            <button class="btn" name="helpful" value="0"><svg class="ic" aria-hidden="true"><use href="#i-x"/></svg>いいえ</button>
        </form>
    @endif
    <p class="sub" style="margin:14px 0 0;padding-top:14px;border-top:1px solid var(--line2)">解決しないときは <a href="{{ $Its::publicUrl('request') }}">こちらから依頼してください</a>。</p>
</div>
@endsection
