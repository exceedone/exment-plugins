@extends(\App\Plugins\ExmentITSupport\Its::view('public._layout'))
@php($Its = \App\Plugins\ExmentITSupport\Its::class)
@section('mainclass', 'narrow')
@section('content')
<div class="card center">
    <div class="bigicon" aria-hidden="true"><svg class="ic"><use href="#i-shield"/></svg></div>
    <h1>確認のコード</h1>
    <p class="lead">このチケットは、開くときに確認が要ります。{{ $email }} に 6 桁のコードをお送りします。</p>
    @if(session()->has('its_otp_error'))<div class="err" role="alert" style="margin-top:14px;text-align:left"><svg class="ic" aria-hidden="true"><use href="#i-alert"/></svg><div>{{ session()->get('its_otp_error') }}</div></div>@endif
    @if($sent)
    <form method="post" action="{{ $Its::publicUrl('t', $token, 'otp') }}" style="margin-top:18px">
        @csrf
        <label class="f" for="code">コード（6 桁）</label>
        <input type="text" id="code" class="code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="8" required style="margin:0 auto;display:block">
        <div style="margin-top:18px"><button class="btn primary big" type="submit">開く</button></div>
    </form>
    <form method="post" action="{{ $Its::publicUrl('t', $token, 'otp', 'send') }}" style="margin-top:12px">@csrf<button class="btn" type="submit"><svg class="ic" aria-hidden="true"><use href="#i-mail"/></svg>コードをもう一度送る</button></form>
    @else
    <form method="post" action="{{ $Its::publicUrl('t', $token, 'otp', 'send') }}" style="margin-top:18px">@csrf<button class="btn primary big" type="submit"><svg class="ic" aria-hidden="true"><use href="#i-mail"/></svg>コードを送る</button></form>
    @endif
</div>
@endsection
