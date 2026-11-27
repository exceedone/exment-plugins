{{-- A call with the help desk by a link (ISS02941): GuestCallController@page. Voice and a shared screen, browser to browser. --}}
<!doctype html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>サポート窓口との通話</title>
<style>
*{box-sizing:border-box}
html,body{margin:0;min-height:100%;font:15px/1.6 -apple-system,"Segoe UI","Hiragino Sans","Noto Sans JP",sans-serif;color:#1f2328;background:#f4f6f9}
.box{max-width:720px;margin:24px auto;background:#fff;border:1px solid #e3e6ea;border-radius:12px;padding:20px}
h1{font-size:18px;margin:0 0 6px}
.st{color:#5b6470;margin:6px 0 12px}
video{width:100%;background:#000;border-radius:8px;display:none;max-height:60vh}
video.on{display:block}
.bar{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px}
button{border:0;border-radius:20px;padding:8px 16px;background:#2563c9;color:#fff;font:inherit;font-weight:600;cursor:pointer}
button.sub{background:#e9edf2;color:#1f2328}
button.end{background:#e5484d}
button.on{outline:2px solid #2563c9}
button:disabled{opacity:.5}
.note{font-size:12px;color:#5b6470;margin-top:14px}
</style>
</head>
<body>
<div class="box">
  <h1>サポート窓口との通話</h1>
  @if($gone)
  <p class="st">このリンクは使えません。期限（30 分）が切れたか、通話が終わっています。担当者に、もう一度リンクを送ってもらってください。</p>
  @else
  <p class="st" id="st">「通話をはじめる」を押すと、マイクの許可をたずねられます。</p>
  <video id="v" autoplay playsinline></video><audio id="a" autoplay></audio>
  <div class="bar">
    <button type="button" id="go">通話をはじめる</button>
    <button type="button" class="sub" id="mu" disabled>マイクを切る</button>
    <button type="button" class="sub" id="sh" disabled>画面を共有</button>
    <button type="button" class="end" id="end" disabled>切る</button>
  </div>
  <p class="note">声と画面は、あなたのブラウザーと担当者のブラウザーのあいだで直接つながります（暗号化され、録音はしません）。このリンクは、はじめに開いたブラウザーだけで使えます。</p>
  @endif
</div>
@if(!$gone)
<script nonce="{{ $nonce }}">
(function () {
'use strict';
var B = @json($boot);
var $ = function (id) { return document.getElementById(id); };
var TOKEN = location.pathname.split('/').pop();
var BIND = null;
try { BIND = sessionStorage.getItem('its_call_' + TOKEN); } catch (e) { /* none */ }
var C = { pc: null, mic: null, screen: null, after: 0, timer: null, making: false, ignore: false, ready: false, done: false };
function st(t) { $('st').textContent = t; }
function api(url, body) {
    var o = { credentials: 'omit', headers: { 'Accept': 'application/json', 'X-Call-Token': TOKEN } };
    if (BIND) { o.headers['X-Call-Bind'] = BIND; }
    if (body) { o.method = 'POST'; o.headers['Content-Type'] = 'application/json'; o.body = JSON.stringify(body); }
    return fetch(url, o).then(function (r) { return r.json().catch(function () { return { ok: false, message: '応答を読めませんでした（' + r.status + '）' }; }); });
}
function send(type, payload) { return api(B.signal, { type: type, payload_json: payload == null ? '' : JSON.stringify(payload) }); }
function setup() {
    var pc = new RTCPeerConnection({ iceServers: B.ice || [] });
    C.pc = pc;
    pc.onicecandidate = function (e) { if (e.candidate) { send('ice', e.candidate.toJSON()); } };
    pc.ontrack = function (e) {
        if (e.track.kind === 'video') {
            $('v').srcObject = new MediaStream([e.track]); $('v').classList.add('on');
            e.track.onended = e.track.onmute = function () { $('v').classList.remove('on'); };
            e.track.onunmute = function () { $('v').classList.add('on'); };
        } else { $('a').srcObject = e.streams[0] || new MediaStream([e.track]); }
    };
    pc.onconnectionstatechange = function () {
        var s = pc.connectionState;
        st(s === 'connected' ? '通話中です。' : s === 'failed' ? 'つながりませんでした（ネットワークによっては、つながらないことがあります）。' : s === 'disconnected' ? '切れそうです…' : 'つないでいます…');
    };
    pc.onnegotiationneeded = function () {
        if (!C.ready) { return; }
        C.making = true;
        pc.setLocalDescription().then(function () { return send('offer', pc.localDescription.toJSON()); }).finally(function () { C.making = false; });
    };
}
function on(s) {
    var pc = C.pc;
    if (s.type === 'hangup') { finish('担当者が通話を終えました。'); return; }
    if (s.type === 'offer' || s.type === 'answer') {
        var clash = s.type === 'offer' && (C.making || pc.signalingState !== 'stable');
        // the guest is the polite side: the agent's offer wins
        C.ignore = false;
        if (clash) { /* polite: roll back to take theirs */ }
        var desc = s.payload || {};
        if (desc.sdp && !/\r\n$/.test(desc.sdp)) { desc = { type: desc.type, sdp: desc.sdp + '\r\n' }; }
        return pc.setRemoteDescription(desc).then(function () {
            C.ready = true;
            if (s.type === 'offer') { return pc.setLocalDescription().then(function () { return send('answer', pc.localDescription.toJSON()); }); }
        });
    }
    if (s.type === 'ice' && s.payload) { return pc.addIceCandidate(s.payload).catch(function () { /* late */ }); }
}
function poll() {
    if (C.done) { return; }
    api(B.signals + '?after=' + C.after).then(function (d) {
        if (C.done) { return; }
        if (!d || !d.ok) { finish(d && d.message ? d.message : '通話が終わりました。'); return; }
        var chain = Promise.resolve();
        (d.signals || []).forEach(function (s) { C.after = Math.max(C.after, s.id); chain = chain.then(function () { return on(s); }).catch(function () { /* one bad signal */ }); });
        chain.then(function () { if (d.ended) { finish('通話が終わりました。'); return; } C.timer = setTimeout(poll, 1000); });
    }).catch(function () { C.timer = setTimeout(poll, 2000); });
}
function finish(msg) {
    if (C.done) { return; }
    C.done = true;
    clearTimeout(C.timer);
    [C.mic, C.screen && C.screen.stream].forEach(function (s) { if (s) { s.getTracks().forEach(function (t) { t.stop(); }); } });
    try { if (C.pc) { C.pc.close(); } } catch (e) { /* closed */ }
    st(msg);
    ['mu', 'sh', 'end', 'go'].forEach(function (id) { $(id).disabled = true; });
}
$('go').onclick = function () {
    if (!window.RTCPeerConnection || !navigator.mediaDevices) { st('このブラウザーでは通話できません。'); return; }
    $('go').disabled = true;
    api(B.join, {}).then(function (d) {
        if (!d || !d.ok) { st((d && d.message) || '参加できませんでした。'); return; }
        BIND = d.bind;
        try { sessionStorage.setItem('its_call_' + TOKEN, BIND); } catch (e) { /* the page only */ }
        return navigator.mediaDevices.getUserMedia({ audio: true }).then(function (stream) {
            C.mic = stream;
            setup();
            stream.getTracks().forEach(function (t) { C.pc.addTrack(t, stream); });
            ['mu', 'sh', 'end'].forEach(function (id) { $(id).disabled = false; });
            st('担当者を待っています…');
            return send('join');
        }).then(poll);
    }).catch(function () { $('go').disabled = false; st('マイクを使えませんでした（ブラウザーの許可を見てください）。'); });
};
$('mu').onclick = function () {
    if (!C.mic) { return; }
    var t = C.mic.getAudioTracks()[0];
    t.enabled = !t.enabled;
    this.textContent = t.enabled ? 'マイクを切る' : 'マイクを入れる';
    this.classList.toggle('on', !t.enabled);
};
$('sh').onclick = function () {
    var btn = this;
    if (C.screen) {
        C.screen.stream.getTracks().forEach(function (t) { t.stop(); });
        try { C.pc.removeTrack(C.screen.sender); } catch (e) { /* gone */ }
        C.screen = null; btn.classList.remove('on'); btn.textContent = '画面を共有';
        return;
    }
    if (!navigator.mediaDevices.getDisplayMedia) { st('このブラウザーは画面の共有ができません。'); return; }
    navigator.mediaDevices.getDisplayMedia({ video: true }).then(function (stream) {
        var track = stream.getVideoTracks()[0];
        C.screen = { stream: stream, sender: C.pc.addTrack(track, stream) };
        btn.classList.add('on'); btn.textContent = '共有を止める';
        track.onended = function () { if (C.screen) { btn.click(); } };
    }).catch(function () { /* said no */ });
};
$('end').onclick = function () { api(B.end, {}); finish('通話を終えました。'); };
window.addEventListener('beforeunload', function () { if (!C.done && BIND) { api(B.end, {}); } });
})();
</script>
@endif
</body>
</html>
