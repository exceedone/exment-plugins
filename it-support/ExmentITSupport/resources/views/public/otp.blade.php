@extends(\App\Plugins\ExmentITSupport\Its::view('public._layout'))
@php($Its = \App\Plugins\ExmentITSupport\Its::class)
@section('content')
<div class="card">
    <h1>確認のコード</h1>
    <p>このチケットは、開くときに確認が要ります。{{ $email }} に 6 桁のコードをお送りします。</p>
    @if(session()->has('its_otp_error'))<div class="err">{{ session()->get('its_otp_error') }}</div>@endif
    @if($sent)
    <form method="post" action="{{ $Its::publicUrl('t', $token, 'otp') }}">
        @csrf
        <label class="f">コード（6 桁）</label>
        <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="8" required style="font-size:22px;letter-spacing:6px;max-width:220px">
        <div style="margin-top:14px"><button class="btn primary" type="submit">開く</button></div>
    </form>
    <form method="post" action="{{ $Its::publicUrl('t', $token, 'otp', 'send') }}" style="margin-top:12px">@csrf<button class="btn" type="submit">コードをもう一度送る</button></form>
    @else
    <form method="post" action="{{ $Its::publicUrl('t', $token, 'otp', 'send') }}">@csrf<button class="btn primary" type="submit">コードを送る</button></form>
    @endif
</div>
@endsection
