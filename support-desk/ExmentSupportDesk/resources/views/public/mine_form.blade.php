@extends(\App\Plugins\ExmentSupportDesk\Its::view('public._layout'))
@php($Its = \App\Plugins\ExmentSupportDesk\Its::class)
@section('mainclass', 'narrow')
@section('content')
<div class="card">
    <div class="bigicon" aria-hidden="true"><svg class="ic"><use href="#i-mail"/></svg></div>
    <h1 class="center">自分の依頼を見る</h1>
    <p class="lead center">依頼したときのメールアドレスを入れてください。依頼の一覧を見るリンクを、そのアドレスにお送りします（パスワードは要りません）。</p>
    <form method="post" action="{{ $Its::publicUrl('mine') }}" style="margin-top:20px">
        @csrf
        <label class="f" for="email">メールアドレス</label>
        <input type="email" id="email" name="email" required maxlength="200" autocomplete="email" placeholder="you@example.co.jp">
        <div style="margin-top:18px"><button class="btn primary big" type="submit"><svg class="ic" aria-hidden="true"><use href="#i-send"/></svg>リンクを送る</button></div>
    </form>
</div>
@endsection
