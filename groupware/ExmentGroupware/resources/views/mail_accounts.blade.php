{{-- Exment Plugin Groupware: the Web mail accounts of the signed-in user. --}}
<div class="gwa" id="gwa">
<style>
.gwa{--line:#e3e6ea;--ink:#1f2328;--sub:#5b6470;--faint:#8b939e;--acc:#2563c9;--acc-soft:#eaf1fd;--red:#d23c3c;color:var(--ink);font-size:13px;max-width:980px}
.gwa *{box-sizing:border-box}
.gwa h3{font-size:16px;font-weight:700;margin:0 0 10px}
.gwa .card2{background:#fff;border:1px solid var(--line);border-radius:10px;padding:18px 20px;margin-bottom:16px}
.gwa .lead{color:var(--sub);line-height:1.7;margin:0 0 14px}
.gwa-acc{display:flex;align-items:center;gap:12px;padding:12px 0;border-top:1px solid var(--line)}
.gwa-acc:first-of-type{border-top:0}
.gwa-acc .ic{width:38px;height:38px;border-radius:10px;display:grid;place-items:center;background:var(--acc-soft);color:var(--acc);font-size:17px}
.gwa-acc .m{flex:1;min-width:0}
.gwa-acc .m b{display:block}
.gwa-acc .m span{color:var(--sub);font-size:12px}
.gwa-acc .er{color:var(--red);font-size:12px;margin-top:2px}
.gwa-pv{display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:10px;margin-bottom:16px}
.gwa-pv label{display:flex;flex-direction:column;gap:4px;border:1px solid var(--line);border-radius:10px;padding:12px;cursor:pointer;margin:0;font-weight:400;min-height:76px}
.gwa-pv label:hover{border-color:var(--acc)}
.gwa-pv input{display:none}
.gwa-pv input:checked+div{color:var(--acc)}
.gwa-pv label.on{border-color:var(--acc);background:var(--acc-soft);box-shadow:0 0 0 1px var(--acc) inset}
.gwa-pv b{font-size:14px}
.gwa-pv small{color:var(--sub);line-height:1.4}
.gwa-row{display:grid;grid-template-columns:180px minmax(0,1fr);gap:8px 14px;align-items:start;margin-bottom:12px}
.gwa-row>label{margin:7px 0 0;font-weight:600}
.gwa-row .help{color:var(--sub);font-size:12px;margin-top:4px;line-height:1.6}
.gwa-row input[type=text],.gwa-row input[type=email],.gwa-row input[type=password],.gwa-row input[type=number],.gwa-row select,.gwa-row textarea{width:100%;height:34px;border:1px solid var(--line);border-radius:7px;padding:0 10px;background:#fff}
.gwa-row textarea{height:96px;padding:8px 10px;font-family:inherit}
.gwa-srv{display:grid;grid-template-columns:minmax(0,1fr) 90px 150px;gap:6px}
.gwa-adv{border-top:1px dashed var(--line);margin-top:6px;padding-top:12px}
.gwa .msg{margin-top:12px;padding:10px 12px;border-radius:8px;display:none;line-height:1.6}
.gwa .msg.on{display:block}
.gwa .msg.err{background:#fdecec;color:#a52a2a;border:1px solid #f5c2c2}
.gwa .msg.ok{background:#e9f7ef;color:#1e6b3a;border:1px solid #b9e3c8}
.gwa .msg.info{background:#fff7e6;color:#7a5400;border:1px solid #f5d79e}
.gwa .foot{display:flex;gap:8px;align-items:center;margin-top:16px}
.gwa .note{color:var(--sub);font-size:12px;line-height:1.7}
@media (max-width:720px){.gwa-row{grid-template-columns:1fr}.gwa-row>label{margin:0}.gwa-srv{grid-template-columns:1fr 80px}.gwa-srv select{grid-column:1 / -1}}
</style>

<div class="card2">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px">
        <h3 style="flex:1;margin:0"><i class="fa fa-envelope-o" aria-hidden="true"></i> メールのアカウント</h3>
        @if (count($accounts))
            <a href="{{ $mail_url }}" class="btn btn-default btn-sm"><i class="fa fa-inbox" aria-hidden="true"></i> メールを開く</a>
        @endif
        <button type="button" class="btn btn-primary btn-sm" data-act="add"><i class="fa fa-plus" aria-hidden="true"></i> アカウントを追加</button>
    </div>
    <p class="lead">今お使いのメールサーバーに、Outlook やスマホのメールと同じ方法（IMAP・SMTP）でつなぎます。メールはサーバーに残ったままなので、Outlook・スマホと並べて使えます。パスワード・サインインの情報は暗号化して保存します。</p>
    @forelse ($accounts as $a)
        <div class="gwa-acc">
            <span class="ic"><i class="fa {{ $a['provider'] === 'gmail' ? 'fa-google' : ($a['provider'] === 'microsoft365' ? 'fa-windows' : 'fa-server') }}" aria-hidden="true"></i></span>
            <div class="m">
                <b>{{ $a['name'] }}</b>
                <span>{{ $a['email'] }} ・ {{ $a['provider_label'] }} ・ {{ $a['auth_type'] === 'oauth' ? 'サインイン（OAuth）' : 'パスワード' }}@if ($a['auth_type'] !== 'oauth') ・ {{ $a['imap_host'] }}@endif</span>
                @if ($a['needs_signin'])
                    <div class="er"><i class="fa fa-exclamation-triangle" aria-hidden="true"></i> サインインが必要です。</div>
                @elseif ($a['last_error'])
                    <div class="er"><i class="fa fa-exclamation-triangle" aria-hidden="true"></i> {{ $a['last_error'] }}</div>
                @endif
            </div>
            @if ($a['auth_type'] === 'oauth')
                <a href="{{ $base }}/account/{{ $a['id'] }}/signin" class="btn btn-default btn-sm" data-nopjax>サインインし直す</a>
            @endif
            <button type="button" class="btn btn-default btn-sm" data-edit="{{ $a['id'] }}">変更</button>
            <button type="button" class="btn btn-default btn-sm" data-del="{{ $a['id'] }}" title="Exment から外す（サーバーのメールは消えません）"><i class="fa fa-trash-o" aria-hidden="true"></i></button>
        </div>
    @empty
        <p class="note" style="margin:0">まだアカウントがありません。「アカウントを追加」から、お使いのメールをつないでください。</p>
    @endforelse
</div>

<form class="card2" id="gwa-form" style="display:{{ count($accounts) ? 'none' : 'block' }}" autocomplete="off" novalidate>
    <h3 id="gwa-ftitle">アカウントを追加</h3>
    <input type="hidden" name="id" value="">
    <div class="gwa-pv" id="gwa-pv">
        @foreach ($providers as $key => $p)
            <label data-pv="{{ $key }}"><input type="radio" name="provider" value="{{ $key }}"><div><b>{{ $p['short'] === 'IMAP' ? 'そのほか' : $p['short'] }}</b></div><small>{{ $p['label'] }}</small></label>
        @endforeach
    </div>

    <div id="gwa-fields" style="display:none">
        <div class="gwa-row" id="gwa-r-auth"><label>ログインの方法</label><div>
            <select name="auth_type"></select>
            <div class="help" id="gwa-h-auth"></div>
        </div></div>
        <div class="gwa-row"><label for="gwa-email">メールアドレス</label><div><input type="email" id="gwa-email" name="email" value="" placeholder="you@example.co.jp"></div></div>
        <div class="gwa-row" id="gwa-r-user"><label for="gwa-user">ユーザー名</label><div><input type="text" id="gwa-user" name="username" value=""><div class="help" id="gwa-h-user"></div></div></div>
        <div class="gwa-row" id="gwa-r-pass"><label for="gwa-pass">パスワード</label><div><input type="password" id="gwa-pass" name="password" value="" autocomplete="new-password"><div class="help" id="gwa-h-pass"></div></div></div>
        <div class="gwa-row" id="gwa-r-host"><label for="gwa-host">メールサーバー</label><div><input type="text" id="gwa-host" name="imap_host" value=""><div class="help" id="gwa-h-host"></div></div></div>
        <div class="gwa-row"><label for="gwa-name">表示名</label><div><input type="text" id="gwa-name" name="name" value="" placeholder="会社のメール"><div class="help">左の一覧に出る名前です。空ならメールアドレス。</div></div></div>
        <div class="gwa-row"><label for="gwa-from">差出人の名前</label><div><input type="text" id="gwa-from" name="from_name" value="{{ $user_name }}"><div class="help">相手に見える名前です（例: 山田 太郎／株式会社エクシード・ワン）。</div></div></div>
        <div class="gwa-row"><label for="gwa-sig">署名</label><div><textarea id="gwa-sig" name="signature" placeholder="――――――――&#10;株式会社〇〇　山田 太郎&#10;TEL 03-0000-0000"></textarea></div></div>

        <div id="gwa-adv-wrap">
            <div class="gwa-row"><label></label><div><a href="#" data-act="adv"><i class="fa fa-sliders" aria-hidden="true"></i> サーバーの詳しい設定</a></div></div>
            <div class="gwa-adv" id="gwa-adv" style="display:none">
                <div class="gwa-row"><label>受信（IMAP）</label><div class="gwa-srv">
                    <input type="text" name="imap_host_adv" placeholder="imap.example.co.jp" aria-label="受信サーバー">
                    <input type="number" name="imap_port" min="1" max="65535" aria-label="ポート">
                    <select name="imap_security" aria-label="暗号化"><option value="ssl">SSL/TLS</option><option value="tls">STARTTLS</option><option value="none">なし</option></select>
                </div></div>
                <div class="gwa-row"><label>送信（SMTP）</label><div><div class="gwa-srv">
                    <input type="text" name="smtp_host" placeholder="smtp.example.co.jp" aria-label="送信サーバー">
                    <input type="number" name="smtp_port" min="1" max="65535" aria-label="ポート">
                    <select name="smtp_security" aria-label="暗号化"><option value="ssl">SSL/TLS</option><option value="tls">STARTTLS</option><option value="none">なし</option></select>
                </div><div class="help">ユーザー名・パスワードは受信と同じものを使います（SMTP 認証）。</div></div></div>
                <div class="gwa-row"><label>送信済み</label><div><label style="font-weight:400;margin:6px 0 0"><input type="checkbox" name="append_sent" value="1"> 送ったメールを、サーバーの「送信済み」フォルダーに保存する</label>
                    <div class="help">Xserver・KAGOYA などはオン。Gmail・Microsoft 365 はサーバーが自分で保存するのでオフにします（オンだと 2 通になります）。</div></div></div>
            </div>
        </div>

        <div class="msg" id="gwa-msg" role="alert"></div>
        <div class="foot">
            <button type="submit" class="btn btn-primary" id="gwa-save">つないで保存</button>
            <button type="button" class="btn btn-default" data-act="cancel">やめる</button>
            <span class="note" id="gwa-snote"></span>
        </div>
    </div>
</form>

@if ($is_admin)
    <p class="note"><i class="fa fa-info-circle" aria-hidden="true"></i> システム管理者の方へ: Microsoft 365・Google でのサインインには、<a href="{{ $settings_url }}">プラグインの設定</a>でアプリ（クライアント ID・シークレット）の登録が必要です。社内のネットワークのメールサーバーにつなぐときも、プラグインの設定で許可します。</p>
@endif
</div>

<script>
var GWA_CFG = @json($cfg);
</script>
@verbatim
<script>
(function () {
'use strict';
var C = window.GWA_CFG, root = document.getElementById('gwa'), form = document.getElementById('gwa-form');
if (!root || !form) { return; }
var cur = null;
function f(n) { return form.elements[n]; }
function el(id) { return document.getElementById(id); }
function show(id, on) { el(id).style.display = on ? '' : 'none'; }
function msg(text, kind) { var m = el('gwa-msg'); m.className = 'msg on ' + kind; m.textContent = text; if (!text) { m.className = 'msg'; } }

function pick(key, keep) {
    var p = C.providers[key]; if (!p) { return; }
    form.querySelectorAll('[data-pv]').forEach(function (l) { l.classList.toggle('on', l.getAttribute('data-pv') === key); l.querySelector('input').checked = l.getAttribute('data-pv') === key; });
    show('gwa-fields', true);
    var sel = f('auth_type'), prev = sel.value;
    sel.innerHTML = p.auth.map(function (a) { return '<option value="' + a + '">' + (a === 'oauth' ? (key === 'gmail' ? 'Google でサインイン（おすすめ）' : 'Microsoft でサインイン') : (key === 'gmail' ? 'アプリ パスワード' : 'パスワード')) + '</option>'; }).join('');
    if (keep && p.auth.indexOf(prev) >= 0) { sel.value = prev; }
    show('gwa-r-auth', p.auth.length > 1 || p.auth[0] === 'oauth');
    if (!keep) {
        f('imap_host').value = ''; f('imap_host_adv').value = p.imap_host || '';
        f('imap_port').value = p.imap_port; f('imap_security').value = p.imap_security;
        f('smtp_host').value = p.smtp_host || ''; f('smtp_port').value = p.smtp_port; f('smtp_security').value = p.smtp_security;
        f('append_sent').checked = !!p.append_sent;
        if (!f('email').value && C.userEmail) { f('email').value = C.userEmail; }
    }
    el('gwa-host').placeholder = p.host_hint || ''; el('gwa-h-host').textContent = p.host_help || '';
    el('gwa-h-user').textContent = p.username_help || '';
    el('gwa-user').placeholder = p.username_is_email ? '空ならメールアドレス' : '';
    refresh();
}
function refresh() {
    var key = (form.querySelector('[name=provider]:checked') || {}).value; if (!key) { return; }
    var p = C.providers[key], oauth = f('auth_type').value === 'oauth', fixed = key === 'gmail' || key === 'microsoft365';
    show('gwa-r-user', !oauth && !p.username_is_email || (!oauth && key === 'other'));
    show('gwa-r-pass', !oauth);
    show('gwa-r-host', !oauth && !fixed);
    show('gwa-adv-wrap', !fixed);
    el('gwa-h-auth').textContent = oauth ? (p.oauth_ready ? '「サインインへ」を押すと ' + (key === 'gmail' ? 'Google' : 'Microsoft') + ' の画面が開きます。パスワードは Exment に保存しません。' : 'まだ使えません: システム管理者がプラグインの設定で ' + (key === 'gmail' ? 'Google' : 'Microsoft') + ' のアプリを登録すると使えます。') : (key === 'gmail' ? p.username_help : '');
    el('gwa-h-pass').textContent = cur && !oauth ? '変えないときは空のまま。' : (key === 'gmail' ? 'Google アカウント →「セキュリティ」→「アプリ パスワード」で作った 16 文字。' : '');
    el('gwa-save').textContent = oauth ? 'サインインへ' : 'つないで保存';
    el('gwa-save').disabled = oauth && !p.oauth_ready;
    el('gwa-snote').textContent = oauth ? '' : '保存する前に、受信と送信のサーバーに入れるか確かめます。';
}
function open(account) {
    cur = account || null; form.reset(); msg('');
    form.style.display = 'block';
    el('gwa-ftitle').textContent = cur ? 'アカウントを変更: ' + cur.email : 'アカウントを追加';
    f('id').value = cur ? cur.id : '';
    form.querySelectorAll('[data-pv]').forEach(function (l) { l.classList.remove('on'); l.style.display = cur && l.getAttribute('data-pv') !== cur.provider ? 'none' : ''; });
    show('gwa-fields', false);
    if (cur) {
        pick(cur.provider);
        f('auth_type').value = cur.auth_type; f('email').value = cur.email; f('username').value = cur.username;
        f('name').value = cur.name; f('from_name').value = cur.from_name; f('signature').value = cur.signature;
        f('imap_host').value = cur.imap_host; f('imap_host_adv').value = cur.imap_host; f('imap_port').value = cur.imap_port; f('imap_security').value = cur.imap_security;
        f('smtp_host').value = cur.smtp_host; f('smtp_port').value = cur.smtp_port; f('smtp_security').value = cur.smtp_security; f('append_sent').checked = !!cur.append_sent;
        refresh();
    }
    form.scrollIntoView({ behavior: 'smooth', block: 'start' });
}
// The short host field and the one in the advanced settings are the same value.
form.addEventListener('input', function (e) {
    if (e.target.name === 'imap_host') { f('imap_host_adv').value = e.target.value; var key = (form.querySelector('[name=provider]:checked') || {}).value; if (key && C.providers[key].smtp_same_host) { f('smtp_host').value = e.target.value; } }
    if (e.target.name === 'imap_host_adv') { f('imap_host').value = e.target.value; }
});
form.addEventListener('change', function (e) {
    if (e.target.name === 'provider') { pick(e.target.value); }
    if (e.target.name === 'auth_type') { refresh(); }
});
root.addEventListener('click', function (e) {
    var t = e.target.closest('[data-act],[data-edit],[data-del]'); if (!t) { return; }
    if (t.hasAttribute('data-edit')) { var id = Number(t.getAttribute('data-edit')); open(C.accounts.filter(function (a) { return a.id === id; })[0]); return; }
    if (t.hasAttribute('data-del')) {
        if (!confirm('このアカウントを Exment から外しますか？サーバーのメールは消えません。')) { return; }
        post(C.base + '/account/' + t.getAttribute('data-del') + '/delete', new FormData()).then(function (r) { if (r.ok) { location.reload(); } else { alert(r.message); } });
        return;
    }
    var act = t.getAttribute('data-act');
    if (act === 'add') { open(null); }
    if (act === 'cancel') { form.style.display = C.accounts.length ? 'none' : 'block'; cur = null; }
    if (act === 'adv') { e.preventDefault(); var a = el('gwa-adv'); a.style.display = a.style.display === 'none' ? '' : 'none'; }
});
function post(url, fd) {
    fd.append('_token', C.csrf);
    return fetch(url, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-CSRF-TOKEN': C.csrf } })
        .then(function (r) { return r.json().catch(function () { return { ok: false, message: 'サーバーの応答を読めませんでした（' + r.status + '）。' }; }); })
        .catch(function () { return { ok: false, message: 'サーバーにつながりません。' }; });
}
form.addEventListener('submit', function (e) {
    e.preventDefault();
    var fd = new FormData(form);
    if (!f('imap_host').value && f('imap_host_adv').value) { fd.set('imap_host', f('imap_host_adv').value); }
    fd.delete('imap_host_adv');
    fd.set('append_sent', f('append_sent').checked ? '1' : '0');
    var btn = el('gwa-save'), label = btn.textContent;
    btn.disabled = true; btn.textContent = f('auth_type').value === 'oauth' ? '移動しています…' : 'つないでいます…'; msg('');
    post(C.save, fd).then(function (r) {
        btn.disabled = false; btn.textContent = label;
        if (!r.ok) { msg(r.message, 'err'); return; }
        if (r.message) { msg(r.message, 'ok'); }
        if (r.redirect) { location.href = r.redirect; }
    });
});
if (!C.accounts.length) { open(null); }
})();
</script>
@endverbatim
