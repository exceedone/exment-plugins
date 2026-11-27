@extends(\App\Plugins\ExmentITSupport\Its::view('public._layout'))
@php($Its = \App\Plugins\ExmentITSupport\Its::class)
@section('content')
<section class="hero">
    <h1>{{ $title }}</h1>
    <p style="white-space:pre-wrap">{{ $message }}</p>
    <form method="get" action="{{ $Its::publicUrl('faq') }}" class="search big" role="search">
        <svg class="ic" aria-hidden="true"><use href="#i-search"/></svg>
        <input type="text" name="q" placeholder="困っていることを入れて、答えを探す（例: プリンター 印刷できない）" aria-label="困っていることを入れて、答えを探す">
        <button class="btn" type="submit">探す</button>
    </form>
</section>
<div class="tiles">
    <a class="tile primary" href="{{ $Its::publicUrl('request') }}"><span class="i" aria-hidden="true"><svg class="ic l"><use href="#i-pencil"/></svg></span><span><b>依頼・問い合わせをする</b><small>ログインは要りません</small></span><svg class="ic" aria-hidden="true"><use href="#i-right"/></svg></a>
    <a class="tile" href="{{ $Its::publicUrl('mine') }}"><span class="i" aria-hidden="true"><svg class="ic l"><use href="#i-list"/></svg></span><span><b>自分の依頼の進み具合を見る</b><small>メールアドレスだけで見られます</small></span><svg class="ic" aria-hidden="true"><use href="#i-right"/></svg></a>
</div>
@if(!empty($popular))
<div class="card list">
    <h2><svg class="ic" aria-hidden="true"><use href="#i-help"/></svg>よくある質問</h2>
    @foreach($popular as $k)
    <a class="item" href="{{ $Its::publicUrl('faq', (string) $k->id) }}"><span class="q" aria-hidden="true">Q</span><span class="m"><b>{{ $k->value['title'] ?? '' }}</b></span><svg class="ic" aria-hidden="true"><use href="#i-right"/></svg></a>
    @endforeach
</div>
@endif
@endsection
