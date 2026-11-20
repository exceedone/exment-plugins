@extends(\App\Plugins\ExmentITSupport\Its::view('public._layout'))
@php($Its = \App\Plugins\ExmentITSupport\Its::class)
@section('content')
<div class="card">
    <h1>よくある質問</h1>
    <form method="get" action="{{ $Its::publicUrl('faq') }}" style="display:flex;gap:8px">
        <input type="text" name="q" value="{{ $q }}" placeholder="困っていることを入れてください">
        <button class="btn primary" type="submit">探す</button>
    </form>
</div>
<div class="card list">
    @if($q !== '')
        <h2>「{{ $q }}」の答え（{{ count($results) }} 件）</h2>
        @forelse($results as $r)
        <a class="item" href="{{ $Its::publicUrl('faq', (string) $r['id']) }}"><b>{{ $r['title'] }}</b><div class="sub">{{ $r['snippet'] }}</div></a>
        @empty
        <p>見つかりませんでした。<a href="{{ $Its::publicUrl('request') }}">こちらから依頼してください</a>。</p>
        @endforelse
    @else
        @forelse($popular as $k)
        <a class="item" href="{{ $Its::publicUrl('faq', (string) $k->id) }}"><b>{{ $k->value['title'] ?? '' }}</b></a>
        @empty
        <p class="sub">まだありません。</p>
        @endforelse
    @endif
</div>
@endsection
