{{-- Exment Plugin Groupware: the chat (ISS02945). Rooms on the left, the messages, the box to write. Everything through chat/api. --}}
<div class="gwc" id="gwc">
<style>
.gwc{--line:#e3e6ea;--ink:#1f2328;--sub:#5b6470;--faint:#8b939e;--box:#fff;--box2:#f7f8fa;--hover:#f1f4f8;--sel:#e6effc;--acc:#2563c9;--me:#e8f1ff;--ment:#fff4d6}
.gwc *{box-sizing:border-box}
.gwc-app{display:grid;grid-template-columns:260px minmax(0,1fr);height:var(--gwc-h,640px);background:var(--box);border:1px solid var(--line);border-radius:10px;overflow:hidden;color:var(--ink)}
.gwc-side{background:var(--box2);border-right:1px solid var(--line);display:flex;flex-direction:column;min-height:0}
.gwc-side .hd{display:flex;gap:6px;padding:10px}
.gwc-side .hd button{flex:1}
.gwc-rooms{flex:1;overflow:auto}
.gwc-room{display:flex;align-items:center;gap:8px;padding:8px 12px;cursor:pointer;border-left:3px solid transparent}
.gwc-room:hover{background:var(--hover)}
.gwc-room.on{background:var(--sel);border-left-color:var(--acc)}
.gwc-room .nm{flex:1;min-width:0}
.gwc-room .nm b{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-weight:600}
.gwc-room .nm small{display:block;color:var(--faint);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.gwc-room .un{background:#e5484d;color:#fff;border-radius:9px;font-size:11px;padding:0 6px;min-width:18px;text-align:center}
.gwc-main{display:flex;flex-direction:column;min-height:0;min-width:0}
.gwc-top{display:flex;align-items:center;gap:8px;padding:10px 14px;border-bottom:1px solid var(--line);font-weight:700}
.gwc-top .sp{flex:1}
.gwc-back{display:none}
.gwc-list{flex:1;overflow:auto;padding:10px 14px}
.gwc-day{text-align:center;color:var(--faint);font-size:11px;margin:10px 0}
.gwc-msg{display:flex;flex-direction:column;margin:6px 0;max-width:80%}
.gwc-msg.me{margin-left:auto;align-items:flex-end}
.gwc-msg .who{font-size:11px;color:var(--sub)}
.gwc-msg .bub{background:var(--box2);border:1px solid var(--line);border-radius:10px;padding:6px 10px;white-space:pre-wrap;word-break:break-word}
.gwc-msg.me .bub{background:var(--me)}
.gwc-msg.ment .bub{background:var(--ment)}
.gwc-msg .bub .at{color:var(--acc);font-weight:600}
.gwc-msg .bub code{background:rgba(0,0,0,.06);padding:0 3px;border-radius:3px}
.gwc-msg .meta{font-size:10.5px;color:var(--faint)}
.gwc-msg .files a{display:inline-block;margin:4px 6px 0 0}
.gwc-msg .files img{max-width:220px;max-height:160px;border-radius:6px;display:block}
.gwc-msg.del .bub{color:var(--faint);font-style:italic}
.gwc-comp{border-top:1px solid var(--line);padding:8px 12px;display:flex;gap:8px;align-items:flex-end}
.gwc-comp textarea{flex:1;min-height:44px;max-height:200px;resize:vertical;border:1px solid var(--line);border-radius:8px;padding:6px 8px}
.gwc-comp .fs{font-size:11px;color:var(--sub)}
.gwc-empty{margin:auto;color:var(--faint);padding:40px;text-align:center}
.gwc-dlg{position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:1080;display:flex;align-items:center;justify-content:center;padding:16px}
.gwc-dlg .box{background:var(--box);border-radius:10px;padding:16px;width:min(460px,100%);max-height:calc(100vh - 32px);overflow:auto}
.gwc-dlg .box h4{margin:0 0 10px}
.gwc-dlg .users{max-height:260px;overflow:auto;border:1px solid var(--line);border-radius:6px;padding:6px;margin:6px 0}
.gwc-dlg .users label{display:block;font-weight:400;margin:0;padding:2px 0}
.gwc-dlg .row{display:flex;gap:8px;justify-content:flex-end;margin-top:10px}
.gwc button.b{border:1px solid var(--line);background:var(--box);border-radius:7px;padding:5px 10px}
.gwc button.p{border:0;background:var(--acc);color:#fff;border-radius:7px;padding:6px 14px;font-weight:600}
.gwc .off{color:var(--faint);font-size:11px;padding:4px 14px}
.gwc-msg{position:relative}
.gwc-msg .acts{display:none;position:absolute;top:-10px;right:0;background:var(--box);border:1px solid var(--line);border-radius:7px;padding:1px 3px;gap:2px;z-index:2}
.gwc-msg.me .acts{right:auto;left:0}
.gwc-msg:hover .acts,.gwc-msg:focus-within .acts{display:flex}
.gwc-msg .acts button{border:0;background:none;padding:2px 6px;font-size:12px;color:var(--sub);cursor:pointer}
.gwc-msg .acts button:hover{color:var(--acc)}
.gwc-msg .q{border-left:3px solid var(--line);padding:0 6px;margin-bottom:4px;color:var(--sub);font-size:12px;cursor:pointer}
.gwc-msg .rx{display:flex;gap:4px;flex-wrap:wrap;margin-top:3px}
.gwc-msg .rx button{border:1px solid var(--line);background:var(--box);border-radius:10px;padding:0 7px;font-size:12px;cursor:pointer}
.gwc-msg .rx button.mine{border-color:var(--acc);background:var(--sel)}
.gwc-pick{position:absolute;z-index:5;background:var(--box);border:1px solid var(--line);border-radius:8px;padding:4px;display:flex;gap:2px;box-shadow:0 4px 14px rgba(0,0,0,.12)}
.gwc-pick button{border:0;background:none;font-size:18px;padding:2px 5px;cursor:pointer}
.gwc-replybar{display:flex;gap:8px;align-items:center;padding:4px 12px;border-top:1px solid var(--line);font-size:12px;color:var(--sub)}
.gwc-replybar .t{flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.gwc-msg .ed textarea{width:100%;min-height:60px;border:1px solid var(--line);border-radius:6px;padding:4px 6px}
.gwc-msg.hl .bub{outline:2px solid var(--acc)}
.gwc-msg .tk{font-size:11px;color:var(--sub);margin-top:2px}
.gwc-msg .tk b{color:var(--acc)}
.gwc-search{padding:0 10px 8px}
.gwc-search input{width:100%;border:1px solid var(--line);border-radius:7px;padding:4px 8px}
.gwc-panel{position:absolute;right:12px;top:48px;width:min(420px,calc(100% - 24px));max-height:70%;overflow:auto;background:var(--box);border:1px solid var(--line);border-radius:10px;box-shadow:0 8px 24px rgba(0,0,0,.15);z-index:6;padding:8px 10px}
.gwc-panel .it{padding:6px 4px;border-bottom:1px solid var(--line);cursor:pointer}
.gwc-panel .it:hover{background:var(--hover)}
.gwc-panel .it small{color:var(--faint)}
.gwc-panel .it.done .t{text-decoration:line-through;color:var(--faint)}
.gwc-main{position:relative}
.gwc-call{position:fixed;right:16px;bottom:16px;width:min(560px,calc(100% - 32px));background:#111;color:#fff;border-radius:12px;z-index:1090;box-shadow:0 10px 30px rgba(0,0,0,.35);overflow:hidden}
.gwc-call .tiles{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:4px;padding:4px}
.gwc-call .tile{position:relative;background:#222;border-radius:8px;min-height:60px;overflow:hidden}
.gwc-call .tile video{width:100%;max-height:40vh;background:#000;display:none}
.gwc-call .tile.vid video{display:block}
.gwc-call .tile .nm{position:absolute;left:6px;bottom:4px;font-size:12px;background:rgba(0,0,0,.5);padding:0 6px;border-radius:4px}
.gwc-call .bar{display:flex;gap:8px;align-items:center;padding:8px 10px}
.gwc-call .bar .st{flex:1;font-size:12px;color:#ccc}
.gwc-call .bar button{border:0;border-radius:18px;padding:6px 12px;background:#333;color:#fff;cursor:pointer}
.gwc-call .bar button.on{background:#2563c9}
.gwc-call .bar button.end{background:#e5484d}
.gwc-msg .join{margin-top:4px}
@media (max-width:760px){
  .gwc-app{grid-template-columns:1fr}
  .gwc-app.open .gwc-side{display:none}
  .gwc-app:not(.open) .gwc-main{display:none}
  .gwc-back{display:inline-block}
  .gwc-msg{max-width:92%}
}
</style>
<div class="gwc-app" id="gwc-app">
  <aside class="gwc-side" aria-label="ルーム">
    <div class="hd"><button type="button" class="b" id="gwc-new"><i class="fa fa-users"></i> グループ</button><button type="button" class="b" id="gwc-dm"><i class="fa fa-user"></i> 1 対 1</button></div>
    <div class="gwc-search"><input type="search" id="gwc-q" placeholder="メッセージを探す" aria-label="メッセージを探す"></div>
    <div class="gwc-rooms" id="gwc-rooms" role="list"></div>
  </aside>
  <section class="gwc-main">
    <div class="gwc-top"><button type="button" class="b gwc-back" id="gwc-back" aria-label="ルームの一覧へ"><i class="fa fa-chevron-left"></i></button><span id="gwc-title"></span><span class="sp"></span><button type="button" class="b" id="gwc-call" hidden><i class="fa fa-phone"></i> 通話</button> <button type="button" class="b" id="gwc-mute" hidden></button> <button type="button" class="b" id="gwc-tasks"><i class="fa fa-check-square-o"></i> タスク</button> <button type="button" class="b" id="gwc-mem" hidden><i class="fa fa-user-plus"></i> メンバー</button></div>
    <div class="gwc-list" id="gwc-list" aria-live="polite"><div class="gwc-empty">左のルームを選ぶか、新しいルームを作ってください。</div></div>
    <div class="off" id="gwc-off" hidden>しばらく何もしていないので、新しいメッセージの確かめを止めています。ページを動かすとまた確かめます。</div>
    <div class="gwc-replybar" id="gwc-replybar" hidden><i class="fa fa-reply"></i><span class="t" id="gwc-replyt"></span><button type="button" class="b" id="gwc-replyx" aria-label="返信をやめる"><i class="fa fa-times"></i></button></div>
    <form class="gwc-comp" id="gwc-comp" hidden>
      <textarea id="gwc-body" placeholder="メッセージ（Ctrl+Enter で送る、@名前 で知らせる）" aria-label="メッセージ"></textarea>
      <div><label class="b" style="display:inline-block;cursor:pointer;border:1px solid var(--line);border-radius:7px;padding:5px 9px" title="ファイル"><i class="fa fa-paperclip"></i><input type="file" id="gwc-files" multiple hidden></label> <span class="fs" id="gwc-fs"></span></div>
      <button type="submit" class="p" id="gwc-send">送る</button>
    </form>
  </section>
</div>
</div>
<script>
(function () {
'use strict';
var B = @json($boot);
var CSRF = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
var S = { rooms: B.rooms || [], room: null, msgs: [], top: 0, busy: false, idleAt: Date.now(), timer: null, stopped: false };
var $ = function (id) { return document.getElementById(id); };
function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
function u(name, id) { return B.url[name].replace('__ID__', encodeURIComponent(id)); }
function api(url, body) {
    var o = { credentials: 'same-origin', headers: { 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } };
    if (body !== undefined) {
        o.method = 'POST';
        if (body instanceof FormData) { o.body = body; } else { o.headers['Content-Type'] = 'application/json'; o.body = JSON.stringify(body); }
    }
    return fetch(url, o).then(function (r) { return r.json().catch(function () { return { ok: false, message: '応答を読めませんでした（' + r.status + '）' }; }); });
}
function name(id) { return (B.users || {})[id] || ''; }
function fit() { var a = $('gwc-app'); var top = a.getBoundingClientRect().top; a.style.setProperty('--gwc-h', Math.max(420, window.innerHeight - top - 18) + 'px'); }
window.addEventListener('resize', fit); fit();

// the text of a message: escaped, then links, `code`, **bold**, and @names lit
function textHtml(s) {
    var h = esc(s);
    h = h.replace(/`([^`\n]{1,200})`/g, '<code>$1</code>');
    h = h.replace(/\*\*([^*\n]{1,200})\*\*/g, '<b>$1</b>');
    h = h.replace(/(https?:\/\/[^\s<>"']{2,500})/g, '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>');
    h = h.replace(/(^|\s)@(全員|all|[^\s@<>&]{1,40})/g, '$1<span class="at">@$2</span>');
    return h;
}
function renderRooms() {
    var h = S.rooms.map(function (r) {
        return '<div class="gwc-room' + (S.room === r.id ? ' on' : '') + '" role="listitem" tabindex="0" data-id="' + r.id + '">' +
            '<i class="fa ' + (r.kind === 'direct' ? 'fa-user' : 'fa-users') + '" aria-hidden="true"></i>' + (r.muted ? '<i class="fa fa-bell-slash-o" title="知らせを止めています" style="color:var(--faint)"></i>' : '') + '<span class="nm"><b>' + esc(r.name || '（名前なし）') + '</b><small>' + esc(r.last) + '</small></span>' +
            (r.unread > 0 ? '<span class="un" aria-label="未読 ' + r.unread + '">' + (r.unread > 99 ? '99+' : r.unread) + '</span>' : '') + '</div>';
    }).join('');
    $('gwc-rooms').innerHTML = h || '<div class="gwc-empty">ルームはまだありません。</div>';
}
function day(at) { return String(at || '').slice(0, 10); }
function msgHtml(m, prevDay) {
    var mine = m.by === B.me;
    var ment = !mine && (m.mentions || []).some(function (x) { return x === 'all' || x === B.me; });
    var files = (m.files || []).map(function (f) {
        var href = u('file', f.uuid);
        return /\.(png|jpe?g|gif|webp)$/i.test(f.name)
            ? '<a href="' + esc(href) + '" target="_blank" rel="noopener"><img src="' + esc(href) + '" alt="' + esc(f.name) + '" loading="lazy"></a>'
            : '<a href="' + esc(href) + '" target="_blank" rel="noopener"><i class="fa fa-file-o"></i> ' + esc(f.name) + '</a>';
    }).join('');
    var d = day(m.at);
    return (d !== prevDay ? '<div class="gwc-day">' + esc(d) + '</div>' : '') +
        '<div class="gwc-msg' + (mine ? ' me' : '') + (ment ? ' ment' : '') + (m.deleted ? ' del' : '') + '" data-id="' + m.id + '">' +
        (mine ? '' : '<span class="who">' + esc(m.name) + '</span>') +
        (m.deleted ? '' : '<span class="acts"><button type="button" data-act="reply" title="返信">返信</button><button type="button" data-act="react" title="リアクション">☺</button>' +
            '<button type="button" data-act="task" title="タスクにする">タスク</button>' + (B.pm ? '<button type="button" data-act="issue" title="ExmentPM の課題にする">課題</button>' : '') +
            (mine ? '<button type="button" data-act="edit">編集</button><button type="button" data-act="delete">消す</button>' : '') + '</span>') +
        (m.quote ? '<div class="q" data-goto="' + m.quote.id + '">' + esc(m.quote.name) + '：' + (m.quote.deleted ? '（消されました）' : esc(m.quote.text)) + '</div>' : '') +
        '<div class="bub">' + (m.deleted ? 'このメッセージは消されました' : textHtml(m.body)) + (files ? '<div class="files">' + files + '</div>' : '') + callJoinHtml(m, mine) + '</div>' +
        rxHtml(m) + tkHtml(m) +
        '<span class="meta">' + esc(String(m.at).slice(11, 16)) + (m.edited ? '（編集）' : '') + (mine && m.read > 0 ? ' · 既読 ' + m.read : '') + '</span></div>';
}
function rxHtml(m) {
    var keys = Object.keys(m.reactions || {});
    if (!keys.length) { return ''; }
    return '<div class="rx">' + keys.map(function (e) {
        var who = m.reactions[e] || [];
        return '<button type="button" data-rx="' + esc(e) + '" class="' + (who.indexOf(B.me) >= 0 ? 'mine' : '') + '" title="' + esc(who.map(name).join('、')) + '">' + esc(e) + ' ' + who.length + '</button>';
    }).join('') + '</div>';
}
// what the list shows of the messages: drawn again only when it changes
function sig() {
    return JSON.stringify(S.msgs.map(function (m) { return [m.id, m.body, m.deleted, m.edited, m.read, m.reactions, m.tasks, m.quote, m.files]; }));
}
function tkHtml(m) {
    return (m.tasks || []).map(function (t) {
        if (t.pm_issue) { return '<div class="tk"><i class="fa fa-tasks"></i> ExmentPM の課題にしました</div>'; }
        return '<div class="tk"><i class="fa ' + (t.done ? 'fa-check-square-o' : 'fa-square-o') + '"></i> タスク' + (t.assignee ? '：<b>' + esc(name(t.assignee)) + '</b>' : '') + (t.due ? '（' + esc(t.due) + ' まで）' : '') + (t.done ? ' 済み' : '') + '</div>';
    }).join('');
}
function replace(m) { for (var i = 0; i < S.msgs.length; i++) { if (S.msgs[i].id === m.id) { S.msgs[i] = m; return true; } } return false; }
function renderMsgs(keepScroll) {
    var list = $('gwc-list');
    var atEnd = list.scrollHeight - list.scrollTop - list.clientHeight < 60;
    var prev = '';
    list.innerHTML = S.msgs.length ? S.msgs.map(function (m) { var h = msgHtml(m, prev); prev = day(m.at); return h; }).join('') : '<div class="gwc-empty">まだメッセージはありません。</div>';
    if (!keepScroll || atEnd) { list.scrollTop = list.scrollHeight; }
}
function openRoom(id, then) {
    S.room = id;
    S.editing = false;
    setReply(null);
    $('gwc-app').classList.add('open');
    var r = S.rooms.filter(function (x) { return x.id === id; })[0];
    $('gwc-title').textContent = r ? (r.name || '（名前なし）') : '';
    $('gwc-mem').hidden = !(r && r.kind === 'group');
    showMute();
    $('gwc-call').hidden = !(B.call && r);
    $('gwc-comp').hidden = false;
    renderRooms();
    $('gwc-list').innerHTML = '<div class="gwc-empty"><i class="fa fa-spinner fa-spin"></i></div>';
    api(u('messages', id)).then(function (d) {
        if (S.room !== id) { return; }
        S.msgs = d.messages || [];
        renderMsgs(false);
        markRead();
        if (then) { then(); }
    });
    try { history.replaceState(null, '', location.pathname + '?room=' + id); } catch (e) { /* the address only */ }
}
function markRead() {
    if (!S.room || !S.msgs.length || document.hidden) { return; }
    var last = S.msgs[S.msgs.length - 1].id;
    var r = S.rooms.filter(function (x) { return x.id === S.room; })[0];
    if (r && r.unread) { r.unread = 0; renderRooms(); }
    api(u('read', S.room), { upto: last });
}

// new messages: every 3 s seen, 30 s hidden, none after 30 min of nothing done
function schedule() {
    clearTimeout(S.timer);
    var idle = (Date.now() - S.idleAt) / 1000;
    if (idle >= 1800) { S.stopped = true; $('gwc-off').hidden = false; return; }
    S.timer = setTimeout(poll, (document.hidden ? 30 : 3) * 1000);
}
function poll() {
    if (S.busy || !navigator.onLine) { schedule(); return; }
    S.busy = true;
    api(B.url.poll + '?after=' + S.top + (S.room ? '&room=' + S.room : '')).then(function (d) {
        S.busy = false;
        if (!d || d.ok === false) { schedule(); return; }
        S.top = Math.max(S.top, d.top || 0);
        // drawn again only when something changed, and never under an edit or an open picker:
        // the box being written, the picker, a highlight, the hovered tools and a selection stay
        var roomsBefore = JSON.stringify(S.rooms), msgsBefore = sig();
        S.rooms = d.rooms || S.rooms;
        var mine = (d.messages || []).filter(function (m) { return m.room === S.room && !S.msgs.some(function (x) { return x.id === m.id; }); });
        if (mine.length) { S.msgs = S.msgs.concat(mine); }
        if (!S.editing) {
            if (d.reads) { S.msgs.forEach(function (m) { if (d.reads[m.id] != null) { m.read = d.reads[m.id]; } }); }
            (d.recent || []).forEach(function (m) { if (m.room === S.room) { replace(m); } });
        }
        if (S.room && sig() !== msgsBefore) {
            if (!S.editing && !document.querySelector('.gwc-pick')) { renderMsgs(true); } else { S.stale = true; }
            if (mine.length) { markRead(); }
        } else if (S.room && S.stale && !S.editing && !document.querySelector('.gwc-pick')) {
            S.stale = false; renderMsgs(true);
        }
        if (JSON.stringify(S.rooms) !== roomsBefore) { renderRooms(); }
        schedule();
    }).catch(function () { S.busy = false; schedule(); });
}
function awake() {
    S.idleAt = Date.now();
    if (S.stopped) { S.stopped = false; $('gwc-off').hidden = true; poll(); }
}
['mousemove', 'keydown', 'touchstart', 'focus'].forEach(function (ev) { window.addEventListener(ev, awake, { passive: true }); });
document.addEventListener('visibilitychange', function () { if (!document.hidden) { awake(); markRead(); schedule(); } });

// send
var pending = [];
function showFiles() { $('gwc-fs').textContent = pending.length ? pending.length + ' 件' : ''; }
$('gwc-files').addEventListener('change', function () { pending = pending.concat(Array.prototype.slice.call(this.files)); this.value = ''; showFiles(); });
$('gwc-body').addEventListener('paste', function (ev) {
    var items = (ev.clipboardData && ev.clipboardData.files) || [];
    if (items.length) { ev.preventDefault(); pending = pending.concat(Array.prototype.slice.call(items)); showFiles(); }
});
$('gwc-body').addEventListener('keydown', function (ev) { if (ev.key === 'Enter' && (ev.ctrlKey || ev.metaKey)) { ev.preventDefault(); send(); } });
$('gwc-comp').addEventListener('submit', function (ev) { ev.preventDefault(); send(); });
function send() {
    var body = $('gwc-body').value;
    if (!S.room || (!body.trim() && !pending.length)) { return; }
    if (pending.some(function (f) { return f.size > B.max_file; })) { alert('25 MB をこえるファイルは送れません。'); return; }
    var fd = new FormData();
    fd.append('body', body);
    if (S.reply) { fd.append('reply_to', S.reply); }
    pending.forEach(function (f) { fd.append('files[]', f, f.name && f.name !== 'image.png' ? f.name : ('image-' + Date.now() + '.png')); });
    $('gwc-send').disabled = true;
    api(u('post', S.room), fd).then(function (d) {
        $('gwc-send').disabled = false;
        if (!d || d.ok === false) { alert((d && d.message) || '送れませんでした'); return; }
        $('gwc-body').value = '';
        pending = []; showFiles(); setReply(null);
        if (!S.msgs.some(function (x) { return x.id === d.message.id; })) { S.msgs.push(d.message); }
        S.top = Math.max(S.top, d.message.id);
        renderMsgs(false);
    }).catch(function () { $('gwc-send').disabled = false; alert('送れませんでした'); });
}

// a message's actions: reply, react, edit, delete; the quote goes to the message it answers
function setReply(id) {
    S.reply = id;
    var m = id ? S.msgs.filter(function (x) { return x.id === id; })[0] : null;
    $('gwc-replybar').hidden = !m;
    $('gwc-replyt').textContent = m ? m.name + '：' + String(m.body || '').replace(/\s+/g, ' ').slice(0, 80) : '';
    if (m) { $('gwc-body').focus(); }
}
$('gwc-replyx').addEventListener('click', function () { setReply(null); });
function pick(btn, id) {
    var old = document.querySelector('.gwc-pick');
    if (old) { old.parentNode.removeChild(old); }
    var p = document.createElement('div');
    p.className = 'gwc-pick';
    B.reactions.forEach(function (e) {
        var b = document.createElement('button');
        b.type = 'button';
        b.textContent = e;
        b.onclick = function () { p.parentNode.removeChild(p); react(id, e); };
        p.appendChild(b);
    });
    btn.closest('.gwc-msg').appendChild(p);
    setTimeout(function () { document.addEventListener('click', function off(ev) { if (!p.contains(ev.target)) { if (p.parentNode) { p.parentNode.removeChild(p); } document.removeEventListener('click', off); } }); }, 0);
}
function react(id, e) {
    api(u('react', id), { emoji: e }).then(function (d) { if (d && d.message) { replace(d.message); renderMsgs(true); } });
}
function startEdit(el, m) {
    S.editing = true;
    var bub = el.querySelector('.bub');
    bub.innerHTML = '<div class="ed"><textarea></textarea><div style="display:flex;gap:6px;justify-content:flex-end;margin-top:4px"><button type="button" class="b x">やめる</button><button type="button" class="p ok">保存</button></div></div>';
    var ta = bub.querySelector('textarea');
    ta.value = m.body;
    ta.focus();
    bub.querySelector('.x').onclick = function () { S.editing = false; renderMsgs(true); };
    bub.querySelector('.ok').onclick = function () {
        api(u('edit', m.id), { body: ta.value }).then(function (d) {
            if (!d || d.ok === false) { alert((d && d.message) || '保存できませんでした'); return; }
            S.editing = false; replace(d.message); renderMsgs(true);
        });
    };
}
$('gwc-list').addEventListener('click', function (ev) {
    var t = ev.target;
    var el = t.closest('.gwc-msg');
    if (!el) { return; }
    var id = parseInt(el.getAttribute('data-id'), 10);
    var m = S.msgs.filter(function (x) { return x.id === id; })[0];
    if (!m) { return; }
    var act = t.closest('[data-act]');
    var rx = t.closest('[data-rx]');
    var q = t.closest('[data-goto]');
    if (rx) { react(id, rx.getAttribute('data-rx')); return; }
    if (q) {
        var to = $('gwc-list').querySelector('.gwc-msg[data-id="' + q.getAttribute('data-goto') + '"]');
        if (to) { to.scrollIntoView({ block: 'center' }); to.classList.add('hl'); setTimeout(function () { to.classList.remove('hl'); }, 1500); }
        return;
    }
    if (!act) { return; }
    var a = act.getAttribute('data-act');
    if (a === 'reply') { setReply(id); }
    if (a === 'react') { pick(act, id); }
    if (a === 'edit') { startEdit(el, m); }
    if (a === 'task') { makeTask(m); }
    if (a === 'issue') { makeIssue(m); }
    if (a === 'delete' && window.confirm('このメッセージを消しますか？')) {
        api(u('delete', id), {}).then(function (d) { if (d && d.message) { replace(d.message); renderMsgs(true); } });
    }
});

// 「タスクにする」, 「課題にする」, the list of one's tasks, the search
function memberChecks(roomId, done) {
    api(u('room', roomId)).then(function (d) { done(d && d.room ? d.room.members : []); });
}
function makeTask(m) {
    memberChecks(m.room, function (members) {
        var opts = '<option value="">（担当なし）</option>' + members.map(function (id) { return '<option value="' + id + '"' + (id === B.me ? ' selected' : '') + '>' + esc(name(id)) + '</option>'; }).join('');
        var w = dialog('タスクにする', '<input type="text" class="tt" maxlength="200" style="width:100%"><p style="margin:8px 0 2px">担当</p><select class="as" style="width:100%">' + opts + '</select><p style="margin:8px 0 2px">期限</p><input type="date" class="du">', function (w, close) {
            api(u('task', m.id), { title: w.querySelector('.tt').value, assignee: w.querySelector('.as').value, due: w.querySelector('.du').value }).then(function (d) {
                if (!d || d.ok === false) { alert((d && d.message) || 'できませんでした'); return; }
                close(); reloadOne(m.id);
            });
        });
        w.querySelector('.tt').value = String(m.body || '').split('\n')[0].slice(0, 200);
    });
}
function makeIssue(m) {
    api(B.url.projects).then(function (d) {
        var ps = d && d.projects ? d.projects : {};
        var keys = Object.keys(ps);
        if (!keys.length) { alert('課題を作れるプロジェクトがありません。'); return; }
        dialog('ExmentPM の課題にする', '<select class="pj" style="width:100%">' + keys.map(function (k) { return '<option value="' + esc(k) + '">' + esc(ps[k]) + '</option>'; }).join('') + '</select>', function (w, close) {
            api(u('issue', m.id), { project: w.querySelector('.pj').value }).then(function (r) {
                if (!r || r.ok === false) { alert((r && r.message) || 'できませんでした'); return; }
                close(); reloadOne(m.id);
                if (r.url) { window.open(r.url, '_blank', 'noopener'); }
            });
        });
    });
}
function reloadOne(id) {
    if (!S.room) { return; }
    api(u('messages', S.room) + '?around=' + id).then(function (d) { (d.messages || []).forEach(function (x) { replace(x); }); renderMsgs(true); });
}
function panel(html) {
    var old = document.querySelector('.gwc-panel');
    if (old) { old.parentNode.removeChild(old); }
    if (html === null) { return null; }
    var p = document.createElement('div');
    p.className = 'gwc-panel';
    p.innerHTML = html;
    document.querySelector('.gwc-main').appendChild(p);
    setTimeout(function () { document.addEventListener('click', function off(ev) { if (!p.contains(ev.target) && ev.target.id !== 'gwc-tasks' && ev.target.id !== 'gwc-q') { if (p.parentNode) { p.parentNode.removeChild(p); } document.removeEventListener('click', off); } }); }, 0);
    return p;
}
function jump(roomId, messageId) {
    var go = function () {
        api(u('messages', roomId) + '?around=' + messageId).then(function (d) {
            S.msgs = d.messages || [];
            renderMsgs(true);
            var el = $('gwc-list').querySelector('.gwc-msg[data-id="' + messageId + '"]');
            if (el) { el.scrollIntoView({ block: 'center' }); el.classList.add('hl'); setTimeout(function () { el.classList.remove('hl'); }, 1500); }
        });
    };
    if (S.room !== roomId) { openRoom(roomId, go); } else { go(); }
}
$('gwc-tasks').addEventListener('click', function () {
    api(B.url.tasks).then(function (d) {
        var list = (d && d.tasks) || [];
        var p = panel('<b>自分のタスク</b>' + (list.length ? list.map(function (t) {
            return '<div class="it' + (t.done ? ' done' : '') + '" data-room="' + t.room + '" data-msg="' + t.message + '"><label style="font-weight:400;margin:0"><input type="checkbox" data-done="' + t.id + '"' + (t.done ? ' checked' : '') + (t.pm_issue ? ' disabled' : '') + '> <span class="t">' + esc(t.title) + '</span></label><br><small>' +
                (t.pm_issue ? 'ExmentPM の課題' : (t.assignee_name ? esc(t.assignee_name) : '担当なし') + (t.due ? ' · ' + esc(t.due) + ' まで' : '')) + '</small></div>';
        }).join('') : '<p style="color:var(--faint)">タスクはありません。</p>'));
        p.addEventListener('click', function (ev) {
            var c = ev.target.closest('[data-done]');
            if (c) { api(u('task_done', c.getAttribute('data-done')), { done: c.checked }).then(function () { c.closest('.it').classList.toggle('done', c.checked); }); return; }
            var it = ev.target.closest('.it');
            if (it && ev.target.tagName !== 'INPUT') { panel(null); jump(parseInt(it.getAttribute('data-room'), 10), parseInt(it.getAttribute('data-msg'), 10)); }
        });
    });
});
var qTimer = null;
$('gwc-q').addEventListener('input', function () {
    var q = this.value.trim();
    clearTimeout(qTimer);
    if (!q) { panel(null); return; }
    qTimer = setTimeout(function () {
        api(B.url.search + '?q=' + encodeURIComponent(q)).then(function (d) {
            var rs = (d && d.results) || [];
            var roomName = function (id) { var r = S.rooms.filter(function (x) { return x.id === id; })[0]; return r ? r.name : ''; };
            var p = panel('<b>「' + esc(q) + '」</b>' + (rs.length ? rs.map(function (r) {
                return '<div class="it" data-room="' + r.room + '" data-msg="' + r.id + '">' + esc(r.text) + '<br><small>' + esc(roomName(r.room)) + ' · ' + esc(r.name) + ' · ' + esc(String(r.at).slice(0, 16)) + '</small></div>';
            }).join('') : '<p style="color:var(--faint)">見つかりません。</p>'));
            p.addEventListener('click', function (ev) { var it = ev.target.closest('.it'); if (it) { panel(null); jump(parseInt(it.getAttribute('data-room'), 10), parseInt(it.getAttribute('data-msg'), 10)); } });
        });
    }, 300);
});

// a room's notices: on or off for oneself
function showMute() {
    var r = S.rooms.filter(function (x) { return x.id === S.room; })[0];
    var b = $('gwc-mute');
    b.hidden = !r;
    if (r) { b.innerHTML = r.muted ? '<i class="fa fa-bell"></i> 知らせる' : '<i class="fa fa-bell-slash-o"></i> 知らせを止める'; }
}
$('gwc-mute').addEventListener('click', function () {
    var r = S.rooms.filter(function (x) { return x.id === S.room; })[0];
    if (!r) { return; }
    api(u('mute', S.room), { muted: !r.muted }).then(function (d) { if (d && d.rooms) { S.rooms = d.rooms; renderRooms(); showMute(); } });
});

// ---- calls from a room (ISS02946): up to 4 people, each browser connected to each other (WebRTC);
// the signals pass through the server, addressed to one person each
var CALL = null;
function callJoinHtml(m, mine) {
    var c = !m.deleted && /^📞 通話 #(\d+) を始めました$/.exec(String(m.body || ''));
    if (!c || mine || !B.call) { return ''; }
    // a call that is over: no 「参加」
    var over = S.msgs.some(function (x) { return x.room === m.room && new RegExp('^📞 通話 #' + c[1] + ' (を終えました|はつながりませんでした)').test(String(x.body || '')); });
    if (over) { return ''; }
    return '<div class="join"><button type="button" class="p" data-join="' + c[1] + '"><i class="fa fa-phone"></i> 参加</button></div>';
}
function callBox() {
    var w = document.createElement('div');
    w.className = 'gwc-call';
    w.innerHTML = '<div class="tiles"></div><div class="bar"><span class="st"></span>' +
        '<button type="button" class="mu" title="マイク"><i class="fa fa-microphone"></i></button>' +
        '<button type="button" class="cam" title="カメラ"><i class="fa fa-video-camera"></i></button>' +
        '<button type="button" class="sh" title="画面を共有"><i class="fa fa-desktop"></i> 画面</button>' +
        '<button type="button" class="end"><i class="fa fa-phone"></i> 切る</button></div>';
    document.body.appendChild(w);
    return w;
}
function callStatus(t) { if (CALL) { CALL.box.querySelector('.st').textContent = t; } }
function callShowStatus() {
    if (!CALL) { return; }
    var n = Object.keys(CALL.peers).filter(function (k) { return CALL.peers[k].pc.connectionState === 'connected'; }).length;
    callStatus(n ? '通話中（' + (n + 1) + ' 人）' : (Object.keys(CALL.peers).length ? 'つないでいます…' : 'ほかの人が参加するのを待っています…'));
}
function callSend(to, type, payload) {
    if (!CALL) { return Promise.resolve(); }
    // one JSON text, so the server's trimming of input strings cannot cut the SDP's last line break
    return api(u('call_signal', CALL.id), { to: to, type: type, payload_json: payload == null ? '' : JSON.stringify(payload) });
}
// the tracks of oneself: the microphone, the camera, a screen
function myTracks() {
    var out = [];
    [CALL.mic, CALL.cam, CALL.screen && CALL.screen.stream].forEach(function (st) { if (st) { st.getTracks().forEach(function (t) { out.push([t, st]); }); } });
    return out;
}
function peerTile(uid) {
    var t = document.createElement('div');
    t.className = 'tile';
    t.innerHTML = '<video autoplay playsinline></video><audio autoplay></audio><span class="nm"></span>';
    t.querySelector('.nm').textContent = name(uid);
    CALL.box.querySelector('.tiles').appendChild(t);
    return t;
}
// a connection to one other person; the one who joined later makes the first offer (polite: the earlier one)
function peer(uid, polite) {
    if (CALL.peers[uid]) { return CALL.peers[uid]; }
    var pc = new RTCPeerConnection({ iceServers: B.ice || [] });
    var p = { pc: pc, polite: polite, making: false, ignore: false, tile: peerTile(uid), senders: [] };
    CALL.peers[uid] = p;
    myTracks().forEach(function (x) { p.senders.push(pc.addTrack(x[0], x[1])); });
    pc.onicecandidate = function (e) { if (e.candidate) { callSend(uid, 'ice', e.candidate.toJSON()); } };
    pc.ontrack = function (e) {
        if (e.track.kind === 'video') {
            var v = p.tile.querySelector('video');
            var ms = v.srcObject instanceof MediaStream ? v.srcObject : new MediaStream();
            ms.getVideoTracks().forEach(function (t) { ms.removeTrack(t); });
            ms.addTrack(e.track);
            v.srcObject = ms;
            p.tile.classList.add('vid');
            e.track.onended = e.track.onmute = function () { p.tile.classList.remove('vid'); };
            e.track.onunmute = function () { p.tile.classList.add('vid'); };
        } else {
            p.tile.querySelector('audio').srcObject = e.streams[0] || new MediaStream([e.track]);
        }
    };
    pc.onconnectionstatechange = function () {
        if (pc.connectionState === 'failed') { callStatus(name(uid) + ' さんとつながりませんでした（ネットワークの中つぎが要るかもしれません）'); return; }
        callShowStatus();
    };
    pc.onnegotiationneeded = function () {
        p.making = true;
        pc.setLocalDescription().then(function () { return callSend(uid, 'offer', pc.localDescription.toJSON()); })
            .catch(function () { /* a clash: the other side's offer wins */ })
            .finally(function () { p.making = false; });
    };
    return p;
}
function dropPeer(uid) {
    var p = CALL && CALL.peers[uid];
    if (!p) { return; }
    try { p.pc.close(); } catch (e) { /* closed */ }
    if (p.tile.parentNode) { p.tile.parentNode.removeChild(p.tile); }
    delete CALL.peers[uid];
    callShowStatus();
}
// a track of oneself to everyone, or away from everyone
function addToAll(track, stream) { Object.keys(CALL.peers).forEach(function (k) { var p = CALL.peers[k]; p.senders.push(p.pc.addTrack(track, stream)); }); }
function removeFromAll(track) {
    Object.keys(CALL.peers).forEach(function (k) {
        var p = CALL.peers[k];
        p.senders = p.senders.filter(function (s) { if (s.track === track) { try { p.pc.removeTrack(s); } catch (e) { /* gone */ } return false; } return true; });
    });
}
function callSetup(id) {
    if (CALL) { alert('もう通話中です。'); return null; }
    CALL = { id: id, peers: {}, after: 0, box: callBox(), mic: null, cam: null, screen: null, timer: null };
    var box = CALL.box;
    box.querySelector('.end').onclick = function () { callEnd(true); };
    box.querySelector('.mu').onclick = function () {
        if (!CALL || !CALL.mic) { return; }
        var t = CALL.mic.getAudioTracks()[0];
        t.enabled = !t.enabled;
        this.classList.toggle('on', !t.enabled);
        this.innerHTML = t.enabled ? '<i class="fa fa-microphone"></i>' : '<i class="fa fa-microphone-slash"></i>';
    };
    box.querySelector('.cam').onclick = function () {
        var btn = this;
        if (!CALL) { return; }
        if (CALL.cam) {
            CALL.cam.getTracks().forEach(function (t) { removeFromAll(t); t.stop(); });
            CALL.cam = null;
            btn.classList.remove('on');
            return;
        }
        if (CALL.screen) { alert('画面を共有しているあいだは、カメラは使えません。'); return; }
        navigator.mediaDevices.getUserMedia({ video: true }).then(function (stream) {
            CALL.cam = stream;
            stream.getVideoTracks().forEach(function (t) { addToAll(t, stream); });
            btn.classList.add('on');
        }).catch(function () { alert('カメラを使えませんでした（ブラウザーの許可を見てください）。'); });
    };
    box.querySelector('.sh').onclick = function () {
        var btn = this;
        if (!CALL) { return; }
        if (CALL.screen) { stopShare(); return; }
        if (!navigator.mediaDevices || !navigator.mediaDevices.getDisplayMedia) { alert('このブラウザーは画面の共有ができません。'); return; }
        if (CALL.cam) { box.querySelector('.cam').click(); }
        navigator.mediaDevices.getDisplayMedia({ video: true }).then(function (stream) {
            var track = stream.getVideoTracks()[0];
            CALL.screen = { stream: stream };
            addToAll(track, stream);
            btn.classList.add('on');
            track.onended = stopShare;
        }).catch(function () { /* the person said no */ });
    };
    function stopShare() {
        if (!CALL || !CALL.screen) { return; }
        CALL.screen.stream.getTracks().forEach(function (t) { removeFromAll(t); t.stop(); });
        CALL.screen = null;
        box.querySelector('.sh').classList.remove('on');
    }
    return CALL;
}
function callMic() {
    return navigator.mediaDevices.getUserMedia({ audio: true, video: false }).then(function (stream) { CALL.mic = stream; });
}
function callPoll() {
    if (!CALL) { return; }
    var id = CALL.id;
    api(u('call_signals', id) + '?after=' + CALL.after).then(function (d) {
        if (!CALL || CALL.id !== id) { return; }
        var chain = Promise.resolve();
        if (CALL) { CALL.people = (d.people || []).map(Number); }
        (d.signals || []).forEach(function (s) {
            CALL.after = Math.max(CALL.after, s.id);
            chain = chain.then(function () { return callOn(s); }).catch(function () { /* one bad signal: the next ones still count */ });
        });
        chain.then(function () {
            if (CALL) { CALL.people = (d.people || []).map(Number); }
            if (d.ended) { callEnd(false); return; }
            if (CALL) { CALL.timer = setTimeout(callPoll, 1000); }
        });
    }).catch(function () { if (CALL) { CALL.timer = setTimeout(callPoll, 2000); } });
}
function callOn(s) {
    if (!CALL) { return; }
    if (s.type === 'hangup') { dropPeer(s.from); return; }
    if (s.type === 'join') { return; }
    // only from someone in the call (the server says who): no tile for anyone else
    if ((CALL.people || []).indexOf(Number(s.from)) < 0) { return; }
    // an offer from someone not yet connected: they joined later, so this side is the polite one
    var p = CALL.peers[s.from] || (s.type === 'offer' ? peer(s.from, true) : null);
    if (!p) { return; }
    var pc = p.pc;
    if (s.type === 'offer' || s.type === 'answer') {
        var clash = s.type === 'offer' && (p.making || pc.signalingState !== 'stable');
        p.ignore = !p.polite && clash;
        if (p.ignore) { return; }
        // an SDP must end with CRLF (a server between may have trimmed it)
        var desc = s.payload || {};
        if (desc.sdp && !/\r\n$/.test(desc.sdp)) { desc = { type: desc.type, sdp: desc.sdp + '\r\n' }; }
        return pc.setRemoteDescription(desc).then(function () {
            if (s.type === 'offer') {
                return pc.setLocalDescription().then(function () { return callSend(s.from, 'answer', pc.localDescription.toJSON()); });
            }
        });
    }
    if (s.type === 'ice' && s.payload) {
        return pc.addIceCandidate(s.payload).catch(function () { /* late or ignored */ });
    }
}
function callEnd(tell) {
    if (!CALL) { return; }
    var c = CALL;
    Object.keys(c.peers).forEach(function (k) { try { c.peers[k].pc.close(); } catch (e) { /* closed */ } });
    CALL = null;
    clearTimeout(c.timer);
    if (tell) { api(u('call_end', c.id), {}); }
    [c.mic, c.cam, c.screen && c.screen.stream].forEach(function (st) { if (st) { st.getTracks().forEach(function (t) { t.stop(); }); } });
    if (c.box.parentNode) { c.box.parentNode.removeChild(c.box); }
}
$('gwc-call').addEventListener('click', function () {
    if (!S.room || !window.RTCPeerConnection) { alert('このブラウザーでは通話できません。'); return; }
    api(u('call_start', S.room), {}).then(function (d) {
        if (!d || d.ok === false) { alert((d && d.message) || '通話を始められませんでした'); return; }
        if (!callSetup(d.call)) { return; }
        callShowStatus();
        callMic().then(callPoll).catch(function () { alert('マイクを使えませんでした（ブラウザーの許可を見てください）。'); callEnd(true); });
    });
});
$('gwc-list').addEventListener('click', function (ev) {
    var j = ev.target.closest('[data-join]');
    if (!j) { return; }
    ev.stopPropagation();
    if (!window.RTCPeerConnection) { alert('このブラウザーでは通話できません。'); return; }
    var id = parseInt(j.getAttribute('data-join'), 10);
    if (CALL) { alert('もう通話中です。'); return; }
    api(u('call_join', id), {}).then(function (d) {
        if (!d || d.ok === false) { alert((d && d.message) || '参加できませんでした'); return; }
        if (!callSetup(id)) { return; }
        callStatus('つないでいます…');
        // the one who joins offers to each one already there (onnegotiationneeded, as the tracks go in)
        callMic().then(function () { (d.people || []).forEach(function (uid) { peer(uid, false); }); callShowStatus(); callPoll(); })
            .catch(function () { alert('マイクを使えませんでした（ブラウザーの許可を見てください）。'); callEnd(true); });
    });
}, true);
window.addEventListener('beforeunload', function () { if (CALL) { callEnd(true); } });

// rooms
$('gwc-rooms').addEventListener('click', function (ev) { var r = ev.target.closest('.gwc-room'); if (r) { openRoom(parseInt(r.getAttribute('data-id'), 10)); } });
$('gwc-rooms').addEventListener('keydown', function (ev) { var r = ev.target.closest('.gwc-room'); if (r && ev.key === 'Enter') { openRoom(parseInt(r.getAttribute('data-id'), 10)); } });
$('gwc-back').addEventListener('click', function () { $('gwc-app').classList.remove('open'); });
function dialog(title, html, onOk) {
    var w = document.createElement('div');
    w.className = 'gwc-dlg';
    w.innerHTML = '<div class="box" role="dialog" aria-modal="true"><h4></h4>' + html + '<div class="row"><button type="button" class="b x">やめる</button><button type="button" class="p ok">OK</button></div></div>';
    w.querySelector('h4').textContent = title;
    $('gwc').appendChild(w);
    var close = function () { w.parentNode.removeChild(w); };
    w.querySelector('.x').onclick = close;
    w.addEventListener('mousedown', function (e) { if (e.target === w) { close(); } });
    w.querySelector('.ok').onclick = function () { onOk(w, close); };
    return w;
}
function userChecks(type, skip) {
    return '<input type="search" class="flt" placeholder="名前で探す" style="width:100%">' + '<div class="users">' + Object.keys(B.users).filter(function (id) { return parseInt(id, 10) !== B.me && (skip || []).indexOf(parseInt(id, 10)) < 0; }).map(function (id) {
        return '<label><input type="' + type + '" name="u" value="' + esc(id) + '"> ' + esc(B.users[id]) + '</label>';
    }).join('') + '</div>';
}
function filterable(w) {
    var f = w.querySelector('.flt');
    if (f) { f.addEventListener('input', function () { var q = f.value.trim().toLowerCase(); w.querySelectorAll('.users label').forEach(function (l) { l.style.display = !q || l.textContent.toLowerCase().indexOf(q) >= 0 ? '' : 'none'; }); }); }
}
$('gwc-new').addEventListener('click', function () {
    var w = dialog('グループのルームを作る', '<input type="text" class="nm" placeholder="ルームの名前" maxlength="100" style="width:100%">' + userChecks('checkbox'), function (w, close) {
        var ids = Array.prototype.map.call(w.querySelectorAll('input[name=u]:checked'), function (c) { return c.value; });
        api(B.url.rooms, { name: w.querySelector('.nm').value, members: ids }).then(function (d) {
            if (!d || d.ok === false) { alert((d && d.message) || 'できませんでした'); return; }
            close(); S.rooms = d.rooms; openRoom(d.room);
        });
    });
    filterable(w);
    w.querySelector('.nm').focus();
});
$('gwc-dm').addEventListener('click', function () {
    var w = dialog('1 対 1 で話す', userChecks('radio'), function (w, close) {
        var c = w.querySelector('input[name=u]:checked');
        if (!c) { return; }
        api(u('direct', c.value), {}).then(function (d) {
            if (!d || d.ok === false) { alert((d && d.message) || 'できませんでした'); return; }
            close(); S.rooms = d.rooms; openRoom(d.room);
        });
    });
    filterable(w);
});
$('gwc-mem').addEventListener('click', function () {
    if (!S.room) { return; }
    api(u('room', S.room)).then(function (d) {
        if (!d || !d.room) { return; }
        var rm = d.room;
        var now = '<div class="users">' + rm.members.map(function (id) {
            return '<label>' + esc(name(id)) + (rm.owner && id !== B.me ? ' <input type="checkbox" name="rm" value="' + id + '"> 外す' : '') + (id === B.me ? '（自分）' : '') + '</label>';
        }).join('') + '</div>';
        var keep = rm.owner ? '<p style="margin:8px 0 2px"><b>メッセージを残す期間</b></p><select class="ret">' + B.retention.map(function (d) {
            return '<option value="' + d + '"' + (d === (rm.retention_days || 0) ? ' selected' : '') + '>' + (d ? d + ' 日' : 'ずっと') + '</option>';
        }).join('') + '</select>' : '';
        var w = dialog('メンバー', now + (rm.owner ? '<b>足す</b>' + userChecks('checkbox', rm.members) : '') + keep + '<label style="font-weight:400"><input type="checkbox" class="leave"> このルームから出る</label>', function (w, close) {
            var add = Array.prototype.map.call(w.querySelectorAll('input[name=u]:checked'), function (c) { return c.value; });
            var remove = Array.prototype.map.call(w.querySelectorAll('input[name=rm]:checked'), function (c) { return c.value; });
            if (w.querySelector('.leave').checked) { remove.push(String(B.me)); }
            var ret = w.querySelector('.ret');
            if (ret && parseInt(ret.value, 10) !== (rm.retention_days || 0)) { api(u('settings', S.room), { retention_days: ret.value }); }
            api(u('members', S.room), { add: add, remove: remove }).then(function (r) {
                if (!r || r.ok === false) { alert((r && r.message) || 'できませんでした'); return; }
                close(); S.rooms = r.rooms;
                if (remove.indexOf(String(B.me)) >= 0) { S.room = null; $('gwc-app').classList.remove('open'); $('gwc-comp').hidden = true; $('gwc-list').innerHTML = ''; }
                renderRooms();
            });
        });
        filterable(w);
    });
});

renderRooms();
S.top = B.top || 0;
if (B.open && S.rooms.some(function (r) { return r.id === B.open; })) { if (B.jump) { jump(B.open, B.jump); } else { openRoom(B.open); } }
poll();
})();
</script>
