{{-- The chat window of a 受付箱 on a website (ISS02941): GuestChatController@window. Everything a person writes is drawn as text. --}}
<!doctype html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>{{ $boxName }}</title>
<style>
*{box-sizing:border-box}
html,body{margin:0;height:100%;font:14px/1.5 -apple-system,"Segoe UI","Hiragino Sans","Noto Sans JP",sans-serif;color:#1f2328;background:#fff}
.app{display:flex;flex-direction:column;height:100%}
.hd{padding:10px 14px;border-bottom:1px solid #e3e6ea;font-weight:700}
.hd small{display:block;font-weight:400;color:#5b6470}
.list{flex:1;overflow:auto;padding:10px 12px}
.m{max-width:85%;margin:6px 0;display:flex;flex-direction:column}
.m.me{margin-left:auto;align-items:flex-end}
.m .b{background:#f1f4f8;border-radius:10px;padding:6px 10px;white-space:pre-wrap;word-break:break-word}
.m.me .b{background:#e6effc}
.m .n{font-size:11px;color:#8b939e}
.form,.comp{border-top:1px solid #e3e6ea;padding:10px 12px}
.form input,.form textarea,.comp textarea{width:100%;border:1px solid #d6dbe1;border-radius:7px;padding:6px 8px;font:inherit;margin-bottom:6px}
.comp{display:flex;gap:6px;align-items:flex-end}
.comp textarea{margin:0;min-height:40px;max-height:140px;resize:vertical}
button{border:0;border-radius:7px;padding:7px 14px;background:#2563c9;color:#fff;font:inherit;font-weight:600;cursor:pointer}
button:disabled{opacity:.6}
.note{font-size:12px;color:#5b6470;margin:0 0 6px}
.err{color:#c62828;font-size:12px;min-height:1em}
</style>
</head>
<body>
<div class="app">
  <div class="hd" id="hd"></div>
  <div class="list" id="list" aria-live="polite"></div>
  <form class="form" id="form">
    <p class="note" id="offline" hidden>ただいま担当者がいません。メールでお返事します。</p>
    <input type="text" id="name" placeholder="お名前" maxlength="100" autocomplete="name" required>
    <input type="email" id="email" placeholder="メールアドレス" maxlength="255" autocomplete="email" required>
    <textarea id="first" placeholder="お問い合わせ" rows="3" required></textarea>
    <div class="err" id="err1"></div>
    <button type="submit" id="go">はじめる</button>
  </form>
  <form class="comp" id="comp" hidden>
    <textarea id="body" placeholder="メッセージ（Ctrl+Enter で送る）" aria-label="メッセージ"></textarea>
    <button type="submit" id="send">送る</button>
  </form>
  <div class="err" id="err2" style="padding:0 12px"></div>
</div>
<script nonce="{{ $nonce }}">
(function () {
'use strict';
var B = @json($boot);
var $ = function (id) { return document.getElementById(id); };
var S = { token: null, after: 0, timer: null, idleAt: Date.now() };
try { S.token = sessionStorage.getItem('its_chat_' + B.key); } catch (e) { /* no storage: the window starts again */ }
$('hd').textContent = @json($boxName);
var sub = document.createElement('small');
sub.textContent = B.online ? '担当者がお返事します' : 'ただいま担当者がいません（メールでお返事します）';
$('hd').appendChild(sub);
$('offline').hidden = !!B.online;

function api(url, body) {
    var o = { headers: { 'Accept': 'application/json' }, credentials: 'omit' };
    if (S.token) { o.headers['X-Chat-Token'] = S.token; }
    if (body) { o.method = 'POST'; o.headers['Content-Type'] = 'application/json'; o.body = JSON.stringify(body); }
    return fetch(url, o).then(function (r) { return r.json().catch(function () { return { ok: false, message: '応答を読めませんでした（' + r.status + '）' }; }); });
}
// every line as text: textContent only
function add(l) {
    var m = document.createElement('div');
    m.className = 'm' + (l.me ? ' me' : '');
    if (!l.me) { var n = document.createElement('span'); n.className = 'n'; n.textContent = l.name || '担当者'; m.appendChild(n); }
    var b = document.createElement('div'); b.className = 'b'; b.textContent = l.text; m.appendChild(b);
    $('list').appendChild(m);
    $('list').scrollTop = $('list').scrollHeight;
}
function chatting() { $('form').hidden = true; $('comp').hidden = false; poll(); }
function schedule() {
    clearTimeout(S.timer);
    if (Date.now() - S.idleAt > 1800000) { return; }
    S.timer = setTimeout(poll, (document.hidden ? 30 : 3) * 1000);
}
function poll() {
    api(B.lines + '?after=' + S.after).then(function (d) {
        if (d && d.ok) {
            (d.lines || []).forEach(function (l) { S.after = Math.max(S.after, l.id); add(l); });
        } else if (d && d.message && !d.ok) {
            // the token is over: start again
            try { sessionStorage.removeItem('its_chat_' + B.key); } catch (e) { /* none */ }
            S.token = null; $('form').hidden = false; $('comp').hidden = true; $('err1').textContent = d.message; return;
        }
        schedule();
    }).catch(schedule);
}
['mousemove', 'keydown', 'touchstart', 'focus'].forEach(function (ev) { window.addEventListener(ev, function () { var was = Date.now() - S.idleAt > 1800000; S.idleAt = Date.now(); if (was && S.token) { poll(); } }, { passive: true }); });
document.addEventListener('visibilitychange', function () { if (!document.hidden && S.token) { schedule(); } });

$('form').addEventListener('submit', function (ev) {
    ev.preventDefault();
    $('go').disabled = true; $('err1').textContent = '';
    api(B.start, { key: B.key, name: $('name').value, email: $('email').value, body: $('first').value }).then(function (d) {
        $('go').disabled = false;
        if (!d || !d.ok) { $('err1').textContent = (d && d.message) || 'はじめられませんでした'; return; }
        S.token = d.token;
        try { sessionStorage.setItem('its_chat_' + B.key, d.token); } catch (e) { /* the window only */ }
        chatting();
    }).catch(function () { $('go').disabled = false; $('err1').textContent = 'はじめられませんでした'; });
});
function send() {
    var t = $('body').value;
    if (!t.trim()) { return; }
    if (t.length > B.max) { $('err2').textContent = B.max + ' 文字までです。'; return; }
    $('send').disabled = true; $('err2').textContent = '';
    api(B.post, { body: t }).then(function (d) {
        $('send').disabled = false;
        if (!d || !d.ok) { $('err2').textContent = (d && d.message) || '送れませんでした'; return; }
        $('body').value = '';
        poll();
    }).catch(function () { $('send').disabled = false; $('err2').textContent = '送れませんでした'; });
}
$('comp').addEventListener('submit', function (ev) { ev.preventDefault(); send(); });
$('body').addEventListener('keydown', function (ev) { if (ev.key === 'Enter' && (ev.ctrlKey || ev.metaKey)) { ev.preventDefault(); send(); } });
if (S.token) { chatting(); }
})();
</script>
</body>
</html>
