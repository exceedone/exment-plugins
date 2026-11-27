@extends(\App\Plugins\ExmentSupportDesk\Its::view('public._layout'))
@php($Its = \App\Plugins\ExmentSupportDesk\Its::class)
@php($done = !empty($link) || $heading === '受け付けました')
@section('mainclass', 'narrow')
@section('content')
<div class="card center">
    <div class="bigicon {{ $done ? 'ok' : '' }}" aria-hidden="true"><svg class="ic"><use href="#{{ $done ? 'i-check' : (!empty($showMine) || $heading === 'メールをご確認ください' ? 'i-mail' : 'i-info') }}"/></svg></div>
    <h1>{{ $heading }}</h1>
    <p class="lead">{{ $text }}</p>
    @if(!empty($warnPassword))<div class="warn" role="alert" style="margin-top:16px;text-align:left"><svg class="ic" aria-hidden="true"><use href="#i-alert"/></svg><div>内容にパスワードらしき文字がありました。パスワードは、担当者から別の方法をご案内しますので、変更をおすすめします。</div></div>@endif
    @if(!empty($link) || !empty($showMine))
    <div class="actions" style="justify-content:center;margin-top:20px">
        @if(!empty($link))<a class="btn primary" href="{{ $link }}">進み具合を見る<svg class="ic" aria-hidden="true"><use href="#i-right"/></svg></a>@endif
        @if(!empty($showMine))<a class="btn" href="{{ $Its::publicUrl('mine') }}">自分の依頼を見る</a>@endif
    </div>
    @endif
    <p class="sub" style="margin:18px 0 0"><a href="{{ $Its::publicUrl() }}">窓口のはじめへ</a></p>
</div>
@endsection
