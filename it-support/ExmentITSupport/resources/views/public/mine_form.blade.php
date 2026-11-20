@extends(\App\Plugins\ExmentITSupport\Its::view('public._layout'))
@php($Its = \App\Plugins\ExmentITSupport\Its::class)
@section('content')
<div class="card">
    <h1>自分の依頼を見る</h1>
    <p>依頼したときのメールアドレスを入れてください。依頼の一覧を見るリンクを、そのアドレスにお送りします（パスワードは要りません）。</p>
    <form method="post" action="{{ $Its::publicUrl('mine') }}">
        @csrf
        <label class="f">メールアドレス</label>
        <input type="email" name="email" required maxlength="200" autocomplete="email">
        <div style="margin-top:14px"><button class="btn primary" type="submit">リンクを送る</button></div>
    </form>
</div>
@endsection
