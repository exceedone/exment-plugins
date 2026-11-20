@extends(\App\Plugins\ExmentITSupport\Its::view('public._layout'))
@php($Its = \App\Plugins\ExmentITSupport\Its::class)
@section('content')
<div class="card">
    <h1>{{ $heading }}</h1>
    <p>{{ $text }}</p>
    @if(!empty($warnPassword))<div class="warn">内容にパスワードらしき文字がありました。パスワードは、担当者から別の方法をご案内しますので、変更をおすすめします。</div>@endif
    @if(!empty($link))<p><a class="btn primary" href="{{ $link }}">進み具合を見る</a></p>@endif
    @if(!empty($showMine))<p><a class="btn" href="{{ $Its::publicUrl('mine') }}">自分の依頼を見る</a></p>@endif
    <p><a href="{{ $Its::publicUrl() }}">窓口のはじめへ</a></p>
</div>
@endsection
