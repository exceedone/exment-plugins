@extends(\App\Plugins\ExmentITSupport\Its::view('public._layout'))
@php($Its = \App\Plugins\ExmentITSupport\Its::class)
@section('content')
<div class="card">
    <h1>{{ $title }}</h1>
    <p style="white-space:pre-wrap">{{ $message }}</p>
    <form method="get" action="{{ $Its::publicUrl('faq') }}" style="display:flex;gap:8px;margin:12px 0">
        <input type="text" name="q" placeholder="困っていることを入れて、答えを探す（例: プリンター 印刷できない）">
        <button class="btn" type="submit">探す</button>
    </form>
    <div class="grid2" style="margin-top:12px">
        <a class="btn primary big" href="{{ $Its::publicUrl('request') }}">依頼・問い合わせをする</a>
        <a class="btn big" href="{{ $Its::publicUrl('mine') }}">自分の依頼の進み具合を見る</a>
    </div>
</div>
@if(!empty($popular))
<div class="card list">
    <h2>よくある質問</h2>
    @foreach($popular as $k)
    <a class="item" href="{{ $Its::publicUrl('faq', (string) $k->id) }}"><b>{{ $k->value['title'] ?? '' }}</b></a>
    @endforeach
</div>
@endif
@endsection
