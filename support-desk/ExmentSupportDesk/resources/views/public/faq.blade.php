@extends(\App\Plugins\ExmentSupportDesk\Its::view('public._layout'))
@php($Its = \App\Plugins\ExmentSupportDesk\Its::class)
@section('content')
<div class="card">
    <h1>よくある質問</h1>
    <form method="get" action="{{ $Its::publicUrl('faq') }}" class="search" role="search">
        <svg class="ic" aria-hidden="true"><use href="#i-search"/></svg>
        <input type="text" name="q" value="{{ $q }}" placeholder="困っていることを入れてください" aria-label="困っていることを入れてください">
        <button class="btn primary" type="submit">探す</button>
    </form>
</div>
<div class="card list">
    @if($q !== '')
        <h2>「{{ $q }}」の答え（{{ count($results) }} 件）</h2>
        @forelse($results as $r)
        <a class="item" href="{{ $Its::publicUrl('faq', (string) $r['id']) }}"><span class="q" aria-hidden="true">Q</span><span class="m"><b>{{ $r['title'] }}</b><span class="sub">{{ $r['snippet'] }}</span></span><svg class="ic" aria-hidden="true"><use href="#i-right"/></svg></a>
        @empty
        <div class="empty"><div class="i" aria-hidden="true"><svg class="ic l"><use href="#i-search"/></svg></div><p>見つかりませんでした。<a href="{{ $Its::publicUrl('request') }}">こちらから依頼してください</a>。</p></div>
        @endforelse
    @else
        @forelse($popular as $k)
        <a class="item" href="{{ $Its::publicUrl('faq', (string) $k->id) }}"><span class="q" aria-hidden="true">Q</span><span class="m"><b>{{ $k->value['title'] ?? '' }}</b></span><svg class="ic" aria-hidden="true"><use href="#i-right"/></svg></a>
        @empty
        <div class="empty"><p class="sub">まだありません。</p></div>
        @endforelse
    @endif
</div>
@endsection
