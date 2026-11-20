@extends(\App\Plugins\ExmentITSupport\Its::view('public._layout'))
@php($Its = \App\Plugins\ExmentITSupport\Its::class)
@section('content')
<div class="card">
    <h1>依頼・問い合わせ</h1>
    <p class="sub">ログインは要りません。送ると、チケット番号と、進み具合を見るリンクをメールでお送りします。</p>
    @if($errors->any())<div class="err">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
    <form method="post" action="{{ $Its::publicUrl('request') }}" enctype="multipart/form-data" id="req">
        @csrf
        <div class="hp" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
        <div class="grid2">
            <div><label class="f">お名前<span class="req">必須</span></label><input type="text" name="name" value="{{ old('name', $old['name']) }}" required maxlength="100" autocomplete="name"></div>
            <div><label class="f">メールアドレス<span class="req">必須</span></label><input type="email" name="email" value="{{ old('email', $old['email']) }}" required maxlength="200" autocomplete="email"></div>
        </div>
        <label class="f">会社名</label><input type="text" name="company" value="{{ old('company', $old['company']) }}" maxlength="200" autocomplete="organization">
        @if(!empty($categories))
        <label class="f">何についてですか</label>
        <select name="category" id="cat">
            <option value="">選んでください</option>
            @foreach($categories as $id => $name)<option value="{{ $id }}" @selected(old('category') == $id) data-desc="{{ $descriptions[$id] ?? '' }}">{{ $name }}</option>@endforeach
        </select>
        <div class="sub" id="catdesc"></div>
        @endif
        <label class="f">件名<span class="req">必須</span></label>
        <input type="text" name="subject" id="subject" value="{{ old('subject') }}" required maxlength="200" placeholder="例: プリンターで印刷できない">
        <div id="faq" class="warn" style="display:none;margin-top:8px">
            <b>この答えで解決するかもしれません</b>
            <div id="faqlist" class="list"></div>
        </div>
        <label class="f">内容<span class="req">必須</span></label>
        <textarea name="body" required maxlength="20000" placeholder="いつから・どの画面で・どうなるか、を書いてください。">{{ old('body') }}</textarea>
        <div class="sub">パスワードは書かないでください。必要なときは担当者から別の方法をご案内します。</div>
        <label class="f">ファイル（スクリーンショットなど・5 つまで・1 つ 10MB まで）</label>
        <input type="file" name="files[]" multiple>
        <label style="display:block;margin-top:14px"><input type="checkbox" name="urgent" value="1" @checked(old('urgent'))> 急ぎです（仕事が止まっている）</label>
        <div style="margin-top:18px"><button class="btn primary big" type="submit">送る</button></div>
    </form>
</div>
<script>
(function () {
    var cat = document.getElementById('cat'), desc = document.getElementById('catdesc');
    if (cat) { var show = function () { var o = cat.options[cat.selectedIndex]; desc.textContent = o ? (o.getAttribute('data-desc') || '') : ''; }; cat.addEventListener('change', show); show(); }
    var subject = document.getElementById('subject'), box = document.getElementById('faq'), list = document.getElementById('faqlist'), timer;
    subject.addEventListener('input', function () {
        clearTimeout(timer);
        var q = subject.value.trim();
        if (q.length < 2) { box.style.display = 'none'; return; }
        timer = setTimeout(function () {
            fetch(@json($Its::publicUrl('faq', 'search')) + '?q=' + encodeURIComponent(q)).then(function (r) { return r.json(); }).then(function (d) {
                list.innerHTML = '';
                (d.items || []).forEach(function (k) {
                    var a = document.createElement('a'); a.className = 'item'; a.href = k.url; a.target = '_blank'; a.rel = 'noopener';
                    var b = document.createElement('b'); b.textContent = k.title; a.appendChild(b);
                    var s = document.createElement('div'); s.className = 'sub'; s.textContent = k.snippet; a.appendChild(s);
                    list.appendChild(a);
                });
                box.style.display = (d.items && d.items.length) ? 'block' : 'none';
            }).catch(function () {});
        }, 350);
    });
    document.getElementById('req').addEventListener('submit', function () { this.querySelector('button[type=submit]').disabled = true; });
})();
</script>
@endsection
