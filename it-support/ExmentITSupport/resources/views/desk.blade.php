@php($Its = \App\Plugins\ExmentITSupport\Its::class)
<div class="its itsd" id="itsd">
@include($Its::view('_nav'), ['tab' => 'inbox'])
<style>
.itsd .its-top{margin-bottom:10px}
.d-app{display:grid;grid-template-columns:212px 380px minmax(0,1fr);gap:0;height:var(--d-h,680px);background:var(--its-box);border:1px solid var(--its-line);border-radius:var(--its-radius);box-shadow:var(--its-shadow);overflow:hidden}
.d-app>*{min-height:0}
/* queues */
.d-queues{background:var(--its-box2);border-right:1px solid var(--its-line);display:flex;flex-direction:column;overflow:auto}
.d-qhead{padding:14px 14px 6px;font-size:11px;font-weight:700;letter-spacing:.06em;color:var(--its-faint);text-transform:uppercase}
.d-q{display:flex;align-items:center;gap:9px;margin:1px 8px;padding:7px 10px;border-radius:8px;color:var(--its-ink);cursor:pointer;user-select:none;font-weight:500}
.d-q i.fa{width:16px;text-align:center;color:var(--its-faint)}
.d-q:hover{background:var(--its-hover)}
.d-q.on{background:var(--its-sel);color:var(--its-accent);font-weight:600}
.d-q.on i.fa{color:var(--its-accent)}
.d-q .c{margin-left:auto;font-size:11.5px;color:var(--its-sub);background:var(--its-line2);border-radius:999px;padding:0 7px;line-height:18px;font-weight:600}
.d-q .c.hot{background:var(--its-red);color:#fff}
.d-qfoot{margin-top:auto;padding:12px 14px;font-size:11.5px;color:var(--its-faint);border-top:1px solid var(--its-line)}
/* list */
.d-list{display:flex;flex-direction:column;border-right:1px solid var(--its-line);min-width:0}
.d-lhead{display:flex;gap:6px;align-items:center;padding:10px;border-bottom:1px solid var(--its-line)}
.d-search{position:relative;flex:1}
.d-search i{position:absolute;left:10px;top:9px;color:var(--its-faint)}
.d-search input{width:100%;padding-left:30px!important}
.d-search kbd{position:absolute;right:8px;top:7px;font-size:10px;color:var(--its-faint);border:1px solid var(--its-line);border-radius:4px;padding:0 4px;background:var(--its-box2)}
.d-filters{display:none;padding:10px;border-bottom:1px solid var(--its-line);background:var(--its-box2);gap:8px;flex-direction:column}
.d-filters.on{display:flex}
.d-chips{display:flex;flex-wrap:wrap;gap:5px}
.d-chip{height:24px;padding:0 9px;border-radius:999px;border:1px solid var(--its-line);background:var(--its-box);font-size:12px;cursor:pointer;color:var(--its-sub);display:inline-flex;align-items:center;gap:5px}
.d-chip.on{border-color:var(--its-accent);background:var(--its-accent-soft);color:var(--its-accent);font-weight:600}
.d-chip b{width:8px;height:8px;border-radius:50%;display:inline-block}
.d-filters select,.d-filters input{height:28px;font-size:12px}
.d-bulk{display:none;align-items:center;gap:6px;padding:8px 10px;border-bottom:1px solid var(--its-line);background:var(--its-accent-soft);font-size:12px}
.d-bulk.on{display:flex}
.d-bulk select{height:26px;font-size:12px}
.d-meta{display:flex;align-items:center;gap:6px;padding:6px 12px;font-size:11.5px;color:var(--its-sub);border-bottom:1px solid var(--its-line2)}
.d-meta select{height:24px;font-size:11.5px;border:0;background:transparent;color:var(--its-sub);padding:0 2px}
.d-items{flex:1;overflow:auto}
.d-item{position:relative;display:flex;gap:10px;padding:11px 12px 11px 14px;border-bottom:1px solid var(--its-line2);cursor:pointer}
.d-item:hover{background:var(--its-hover)}
.d-item.sel{background:var(--its-sel)}
.d-item .edge{position:absolute;left:0;top:0;bottom:0;width:3px}
.d-item .ck{position:absolute;left:4px;top:13px;opacity:0;transition:opacity .1s}
.d-item:hover .ck,.d-items.bulking .d-item .ck{opacity:1}
.d-av{flex:0 0 34px;width:34px;height:34px;border-radius:50%;display:grid;place-items:center;color:#fff;font-weight:700;font-size:13px;letter-spacing:.02em}
.d-av.s{flex-basis:22px;width:22px;height:22px;font-size:10px}
.d-av.l{flex-basis:40px;width:40px;height:40px;font-size:15px}
.d-im{flex:1;min-width:0}
.d-r1{display:flex;align-items:center;gap:6px;font-size:12px;color:var(--its-sub);min-width:0}
.d-r1 .who{font-weight:600;color:var(--its-ink);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.d-r1 .org{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.d-r1 .t{margin-left:auto;white-space:nowrap;color:var(--its-faint)}
.d-subj{font-weight:600;margin:2px 0 1px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.d-item.unread .d-subj::before{content:"";display:inline-block;width:7px;height:7px;border-radius:50%;background:var(--its-accent);margin-right:6px;vertical-align:2px}
.d-prev{font-size:12px;color:var(--its-sub);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.d-prev .k{color:var(--its-faint)}
.d-r4{display:flex;align-items:center;gap:5px;margin-top:6px;flex-wrap:wrap}
.d-sla{display:inline-flex;align-items:center;gap:4px;font-size:11.5px;font-weight:600;padding:0 7px;height:20px;border-radius:999px}
.d-empty{display:grid;place-items:center;height:100%;color:var(--its-faint);text-align:center;padding:30px}
.d-empty i{font-size:40px;opacity:.35;display:block;margin-bottom:10px}
.d-lfoot{display:flex;align-items:center;justify-content:space-between;gap:6px;padding:7px 10px;border-top:1px solid var(--its-line);font-size:12px;color:var(--its-sub)}
/* detail */
.d-detail{display:flex;flex-direction:column;min-width:0;background:var(--its-bg)}
.d-dhead{padding:14px 18px 12px;background:var(--its-box);border-bottom:1px solid var(--its-line)}
.d-crumb{display:flex;align-items:center;gap:8px;font-size:12px;color:var(--its-sub)}
.d-crumb .no{font-family:ui-monospace,Menlo,Consolas,monospace;font-weight:600;color:var(--its-sub)}
.d-title{font-size:19px;font-weight:700;margin:5px 0 8px;line-height:1.35;word-break:break-word}
.d-chips2{display:flex;flex-wrap:wrap;gap:6px;align-items:center}
.d-body{flex:1;min-height:0;display:grid;grid-template-columns:minmax(0,1fr) 300px}
.d-body.noside{grid-template-columns:minmax(0,1fr) 0}
.d-main{display:flex;flex-direction:column;min-height:0;min-width:0}
.d-thread{flex:1;overflow:auto;padding:16px 18px 8px}
.d-msg{display:flex;gap:10px;margin-bottom:14px}
.d-bubble{flex:1;min-width:0;background:var(--its-box);border:1px solid var(--its-line);border-radius:12px;padding:10px 14px;box-shadow:var(--its-shadow)}
.d-msg.reply .d-bubble{border-color:color-mix(in srgb,var(--its-accent) 30%,var(--its-line));background:color-mix(in srgb,var(--its-accent) 4%,var(--its-box))}
.d-msg.note .d-bubble{background:var(--its-note);border-color:var(--its-note-line)}
.d-mh{display:flex;flex-wrap:wrap;align-items:baseline;gap:4px 8px;font-size:12px;color:var(--its-sub);margin-bottom:5px}
.d-mh b{color:var(--its-ink);font-size:13px}
.d-mh .kind{font-weight:600}
.d-msg.note .d-mh .kind{color:var(--its-note-ink)}
.d-msg.reply .d-mh .kind{color:var(--its-accent)}
.d-mh .t{margin-left:auto;color:var(--its-faint)}
.d-mb{white-space:pre-wrap;word-break:break-word;line-height:1.65}
.d-mf{margin-top:8px;font-size:12px;color:var(--its-sub)}
.d-sys{display:flex;align-items:center;gap:8px;margin:0 0 12px 44px;font-size:12px;color:var(--its-faint)}
.d-sys::before{content:"";width:6px;height:6px;border-radius:50%;background:var(--its-line);flex:0 0 6px}
.d-banner{margin:10px 18px 0;padding:9px 12px;border-radius:8px;font-size:12.5px}
.d-banner.red{background:#fdecec;color:#a52a2a;border:1px solid #f5c2c2}
.d-banner.amber{background:#fff6e5;color:#7a5400;border:1px solid #f5d79e}
/* composer */
.d-comp{margin:0 18px 14px;background:var(--its-box);border:1px solid var(--its-line);border-radius:12px;box-shadow:var(--its-shadow-l);overflow:hidden}
.d-comp.note{border-color:var(--its-note-line)}
.d-comp.note textarea{background:var(--its-note)}
.d-ctabs{display:flex;align-items:center;gap:2px;padding:6px 8px 0;border-bottom:1px solid var(--its-line2)}
.d-ctab{padding:7px 12px;border-radius:8px 8px 0 0;cursor:pointer;color:var(--its-sub);font-weight:600;font-size:12.5px;border-bottom:2px solid transparent}
.d-ctab.on{color:var(--its-accent);border-bottom-color:var(--its-accent)}
.d-comp.note .d-ctab.on{color:var(--its-note-ink);border-bottom-color:var(--its-amber)}
.d-to{font-size:12px;color:var(--its-sub);padding:6px 12px 0;display:flex;align-items:center;gap:6px;flex-wrap:wrap}
.d-to input{height:24px!important;font-size:12px!important;width:240px}
.d-comp textarea{border:0!important;box-shadow:none!important;border-radius:0;min-height:92px;max-height:320px;padding:10px 12px}
.d-ctools{display:flex;align-items:center;gap:6px;padding:8px 10px;border-top:1px solid var(--its-line2);flex-wrap:wrap}
.d-ctools select,.d-ctools input{height:28px;font-size:12px}
.d-ctools .send{margin-left:auto;display:flex;gap:6px;align-items:center}
.d-hint{font-size:11px;color:var(--its-faint)}
/* side */
.d-side{border-left:1px solid var(--its-line);background:var(--its-box);overflow:auto;min-width:0}
.d-body.noside .d-side{display:none}
.d-sec{padding:12px 14px;border-bottom:1px solid var(--its-line2)}
.d-sec h4{font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--its-faint);margin:0 0 8px;display:flex;align-items:center;gap:6px}
.d-field{display:grid;grid-template-columns:76px 1fr;align-items:center;gap:6px;margin-bottom:6px}
.d-field label{font-size:12px;color:var(--its-sub)}
.d-field select,.d-field input{height:28px;font-size:12.5px;width:100%}
.d-switch{display:flex;align-items:center;justify-content:space-between;font-size:12.5px;margin:6px 0}
.d-tg{position:relative;width:34px;height:20px;flex:0 0 34px}
.d-tg input{opacity:0;width:0;height:0}
.d-tg span{position:absolute;inset:0;background:var(--its-line);border-radius:999px;cursor:pointer;transition:background .15s}
.d-tg span::after{content:"";position:absolute;left:2px;top:2px;width:16px;height:16px;border-radius:50%;background:#fff;box-shadow:0 1px 2px rgba(0,0,0,.25);transition:transform .15s}
.d-tg input:checked+span{background:var(--its-accent)}
.d-tg input:checked+span::after{transform:translateX(14px)}
.d-slab{display:flex;justify-content:space-between;font-size:12px;margin:4px 0 3px}
.d-slab b{font-weight:600}
.d-kn a{display:block;padding:6px 0;border-bottom:1px dashed var(--its-line);color:var(--its-ink);text-decoration:none!important;font-size:12.5px}
.d-kn a:hover{color:var(--its-accent)}
.d-kn small{display:block;color:var(--its-sub);font-size:11.5px}
.d-rel a{display:flex;gap:6px;align-items:center;padding:4px 0;font-size:12.5px;color:var(--its-ink);text-decoration:none!important}
.d-rel a:hover{color:var(--its-accent)}
.d-toast{position:fixed;right:22px;bottom:22px;z-index:2000;display:flex;flex-direction:column;gap:8px;align-items:flex-end}
.d-toast div{background:#1d2733;color:#fff;padding:10px 14px;border-radius:9px;box-shadow:var(--its-shadow-l);font-size:13px;max-width:380px;animation:dIn .18s ease-out}
.d-toast div.err{background:#b4232a}
@keyframes dIn{from{transform:translateY(8px);opacity:0}to{transform:none;opacity:1}}
.d-skel{height:12px;border-radius:6px;background:linear-gradient(90deg,var(--its-line2),var(--its-hover),var(--its-line2));background-size:200% 100%;animation:dSk 1.2s infinite;margin:8px 0}
@keyframes dSk{to{background-position:-200% 0}}
.d-help{position:fixed;inset:0;background:rgba(10,15,25,.45);z-index:2100;display:none;place-items:center}
.d-help.on{display:grid}
.d-help>div{background:var(--its-box);border-radius:14px;padding:20px 24px;min-width:340px;box-shadow:var(--its-shadow-l)}
.d-help table td{padding:4px 10px}
.d-help kbd,.d-hint kbd{border:1px solid var(--its-line);border-bottom-width:2px;border-radius:5px;padding:0 6px;font-size:11px;background:var(--its-box2);color:var(--its-ink)}
.d-back{display:none}
@media (max-width:1400px){.d-app{grid-template-columns:190px 340px minmax(0,1fr)}.d-body{grid-template-columns:minmax(0,1fr) 270px}}
@media (max-width:1180px){.d-app{grid-template-columns:0 340px minmax(0,1fr)}.d-queues{display:none}.d-body{grid-template-columns:minmax(0,1fr)}.d-side{border-left:0;border-top:1px solid var(--its-line)}.d-body{overflow:auto;display:block}.d-main{min-height:100%}}
@media (max-width:820px){.d-app{grid-template-columns:1fr}.d-detail{display:none}.d-app.opened .d-list{display:none}.d-app.opened .d-detail{display:flex}.d-back{display:inline-flex}}
</style>

<div class="d-app" id="d-app">
    <aside class="d-queues">
        <div class="d-qhead">受付箱</div>
        <div id="d-qlist"></div>
        <div class="d-qfoot" id="d-qfoot"></div>
    </aside>

    <section class="d-list">
        <div class="d-lhead">
            <div class="d-search"><i class="fa fa-search"></i><input type="search" id="d-kw" placeholder="件名・本文・やりとり・メール・番号" autocomplete="off"><kbd>/</kbd></div>
            <button class="its-btn small ghost" id="d-ftoggle" title="絞り込み"><i class="fa fa-sliders"></i></button>
            <button class="its-btn small ghost" id="d-reload" title="更新"><i class="fa fa-refresh"></i></button>
        </div>
        <div class="d-filters" id="d-filters">
            <div class="d-chips" id="d-fstatus"></div>
            <div class="d-chips" id="d-fprio"></div>
            <div class="its-row">
                <select id="d-fassignee"></select>
                <select id="d-fcustomer"></select>
                <select id="d-fcategory"></select>
            </div>
            <div class="its-row">
                <input type="text" id="d-ftags" placeholder="タグ（カンマ区切り）" style="flex:1">
                <select id="d-ftagmode"><option value="and">すべて含む</option><option value="or">どれか</option></select>
                <button class="its-btn small ghost" id="d-fclear">クリア</button>
            </div>
        </div>
        <div class="d-bulk" id="d-bulk">
            <b id="d-bcount"></b> 件を
            <select id="d-baction"><option value="status">状態</option><option value="priority">優先度</option><option value="assignee">担当者</option></select>
            <select id="d-bvalue"></select>
            <button class="its-btn small primary" id="d-bapply">変える</button>
            <button class="its-btn small ghost" id="d-bcancel">やめる</button>
        </div>
        <div class="d-meta"><span id="d-total"></span><span style="margin-left:auto">並び</span>
            <select id="d-sort"><option value="due">期限が近い順</option><option value="priority">優先度順</option><option value="new">新しい順</option><option value="old">古い順</option><option value="updated">更新順</option></select>
        </div>
        <div class="d-items" id="d-items"></div>
        <div class="d-lfoot"><button class="its-btn small" id="d-prev"><i class="fa fa-chevron-left"></i></button><span id="d-page"></span><button class="its-btn small" id="d-next"><i class="fa fa-chevron-right"></i></button></div>
    </section>

    <section class="d-detail" id="d-detail">
        <div class="d-empty"><div><i class="fa fa-life-ring"></i>チケットを選んでください<div class="d-hint" style="margin-top:8px"><kbd>J</kbd> <kbd>K</kbd> で上下 · <kbd>?</kbd> でショートカット</div></div></div>
    </section>
</div>
<div class="d-toast" id="d-toast"></div>
<div class="d-help" id="d-help"><div>
    <h3 style="margin:0 0 10px;font-size:15px">キーボードショートカット</h3>
    <table>
        <tr><td><kbd>J</kbd> / <kbd>K</kbd></td><td>次・前のチケット</td></tr>
        <tr><td><kbd>/</kbd></td><td>探す</td></tr>
        <tr><td><kbd>R</kbd></td><td>お客様へ返信</td></tr>
        <tr><td><kbd>N</kbd></td><td>社内メモ</td></tr>
        <tr><td><kbd>Ctrl</kbd> + <kbd>Enter</kbd></td><td>送る</td></tr>
        <tr><td><kbd>A</kbd></td><td>自分を担当者にする</td></tr>
        <tr><td><kbd>E</kbd></td><td>解決にする</td></tr>
        <tr><td><kbd>\</kbd></td><td>右の欄を開く・閉じる</td></tr>
        <tr><td><kbd>Esc</kbd></td><td>閉じる・入力から抜ける</td></tr>
    </table>
    <div style="text-align:right;margin-top:12px"><button class="its-btn small" onclick="document.getElementById('d-help').classList.remove('on')">閉じる</button></div>
</div></div>
</div>
<script>
(function () {
'use strict';
var root = document.getElementById('itsd');
if (!root || root.dataset.ready) { return; }
root.dataset.ready = '1';

var B = @json($boot);
var CSRF = @json(csrf_token());
var S = { queue: B.queue, page: 1, items: [], counts: {}, sel: null, ticket: null, f: { status: [], priority: [], assignee: '', customer: '', category: '', tags: '', tag_mode: 'and', kw: '', sort: 'due' }, checked: {}, kind: 'reply', side: true, loading: false };
try { S.side = localStorage.getItem('its_side') !== '0'; } catch (e) {}

var $ = function (id) { return document.getElementById(id); };
var app = $('d-app');
var QICON = { todo: 'fa-inbox', mine: 'fa-user', unassigned: 'fa-user-plus', replied: 'fa-reply', sla: 'fa-clock-o', pending: 'fa-hourglass-half', on_hold: 'fa-pause-circle', alerts: 'fa-bell', resolved: 'fa-check-circle', closed: 'fa-archive', all: 'fa-list' };
var KIND = { customer: 'お客様から', reply: 'お客様への返信', note: '社内メモ', system: 'システム' };
var PALETTE = ['#4f6bed', '#7c5cf0', '#e5484d', '#30a46c', '#f08c2b', '#0ea5a4', '#d6409f', '#3e8bea', '#8e4ec6', '#c2701b'];

function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
function u(name, id) { return B.url[name].replace('__ID__', id); }
function hue(s) { var h = 0; s = String(s || '?'); for (var i = 0; i < s.length; i++) { h = (h * 31 + s.charCodeAt(i)) >>> 0; } return PALETTE[h % PALETTE.length]; }
function initials(s) { s = String(s || '?').trim(); if (!s) { return '?'; } if (/^[\x00-\x7F]/.test(s)) { var p = s.split(/[\s@._-]+/).filter(Boolean); return ((p[0] || '?')[0] + ((p[1] || '')[0] || '')).toUpperCase(); } return s.replace(/\s/g, '').slice(0, 2); }
function av(name, cls) { return '<span class="d-av ' + (cls || '') + '" style="background:' + hue(name) + '">' + esc(initials(name)) + '</span>'; }
var CHICON = { monitor: ['fa-bell', '#e5484d', '監視'], asset: ['fa-desktop', '#0ea5a4', 'IT 資産'] };
function avFor(channel, name, cls) { var c = CHICON[channel]; return c ? '<span class="d-av ' + (cls || '') + '" style="background:' + c[1] + '"><i class="fa ' + c[0] + '"></i></span>' : av(name, cls); }
function rel(iso) {
    if (!iso) { return ''; }
    var d = new Date(iso), s = (Date.now() - d.getTime()) / 1000;
    if (s < 60) { return 'いま'; } if (s < 3600) { return Math.floor(s / 60) + '分前'; } if (s < 86400) { return Math.floor(s / 3600) + '時間前'; }
    if (s < 86400 * 7) { return Math.floor(s / 86400) + '日前'; }
    return (d.getMonth() + 1) + '/' + d.getDate();
}
function fmt(iso) { if (!iso) { return ''; } var d = new Date(String(iso).replace(' ', 'T')); if (isNaN(d)) { return iso; } var p = function (n) { return ('0' + n).slice(-2); }; return d.getFullYear() + '/' + p(d.getMonth() + 1) + '/' + p(d.getDate()) + ' ' + p(d.getHours()) + ':' + p(d.getMinutes()); }
function pill(text, color) { return '<span class="its-pill" style="background:' + color + '">' + esc(text) + '</span>'; }
function soft(text, color, icon) { return '<span class="its-soft" style="color:' + color + ';background:' + color + '1f">' + (icon ? '<i class="fa ' + icon + '"></i>' : '') + esc(text) + '</span>'; }
function toast(msg, err) { var t = document.createElement('div'); if (err) { t.className = 'err'; } t.textContent = msg; $('d-toast').appendChild(t); setTimeout(function () { t.remove(); }, err ? 6000 : 3200); }
function opts(map, sel, blank) { var h = blank != null ? '<option value="">' + esc(blank) + '</option>' : ''; Object.keys(map).forEach(function (k) { h += '<option value="' + esc(k) + '"' + (String(sel) === String(k) ? ' selected' : '') + '>' + esc(map[k]) + '</option>'; }); return h; }

function api(url, data) {
    var o = { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF }, credentials: 'same-origin' };
    if (data) { o.method = 'POST'; var fd = data instanceof FormData ? data : new FormData(); if (!(data instanceof FormData)) { Object.keys(data).forEach(function (k) { var v = data[k]; if (Array.isArray(v)) { v.forEach(function (x) { fd.append(k + '[]', x); }); } else { fd.append(k, v == null ? '' : v); } }); } fd.append('_token', CSRF); o.body = fd; }
    return fetch(url, o).then(function (r) { return r.json().catch(function () { return { ok: false, message: 'サーバーの応答を読めませんでした（' + r.status + '）' }; }).then(function (j) { j._status = r.status; return j; }); });
}

// ---- sizing: the app fills the window below Exment's header -------------
function fit() { var top = app.getBoundingClientRect().top + window.scrollY; app.style.setProperty('--d-h', Math.max(480, window.innerHeight - (top - window.scrollY) - 18) + 'px'); }
window.addEventListener('resize', fit); fit(); setTimeout(fit, 300);

// ---- queues -------------------------------------------------------------
function renderQueues() {
    var h = '';
    Object.keys(B.queues).forEach(function (q) {
        var n = S.counts[q];
        var hot = (q === 'sla' || q === 'replied' || q === 'unassigned') && n > 0;
        h += '<div class="d-q' + (S.queue === q ? ' on' : '') + '" data-q="' + q + '"><i class="fa ' + (QICON[q] || 'fa-circle-o') + '"></i>' + esc(B.queues[q]) + (n != null ? '<span class="c' + (hot ? ' hot' : '') + '">' + n + '</span>' : '') + '</div>';
        if (q === 'alerts') { h += '<div class="d-qhead">状態</div>'; }
    });
    $('d-qlist').innerHTML = h;
    var st = B.state;
    $('d-qfoot').innerHTML = '<div><span class="its-dot ' + (st.imap_error ? 'err' : (st.imap_on ? 'on' : '')) + '"></span>メール ' + (st.imap_error ? '<b style="color:var(--its-red)">エラー</b>' : (st.imap_on ? 'オン' : 'オフ')) + '</div><div style="margin-top:4px">自動処理 ' + esc(st.batch_at ? rel(st.batch_at.replace(' ', 'T')) : 'まだ') + '</div>';
}
$('d-qlist').addEventListener('click', function (e) { var q = e.target.closest('.d-q'); if (!q) { return; } S.queue = q.dataset.q; S.page = 1; S.checked = {}; load(); });

// ---- filters ------------------------------------------------------------
function renderFilters() {
    var chips = function (map, colors, key) { return Object.keys(map).map(function (k) { return '<span class="d-chip' + (S.f[key].indexOf(k) >= 0 ? ' on' : '') + '" data-k="' + key + '" data-v="' + k + '"><b style="background:' + colors[k] + '"></b>' + esc(map[k]) + '</span>'; }).join(''); };
    $('d-fstatus').innerHTML = chips(B.statuses, B.statusColors, 'status');
    $('d-fprio').innerHTML = chips(B.priorities, B.priorityColors, 'priority');
    $('d-fassignee').innerHTML = '<option value="">担当者: すべて</option><option value="none"' + (S.f.assignee === 'none' ? ' selected' : '') + '>担当者なし</option>' + opts(B.agents, S.f.assignee);
    $('d-fcustomer').innerHTML = opts(B.customers, S.f.customer, 'お客様: すべて');
    $('d-fcategory').innerHTML = opts(B.categories, S.f.category, '種類: すべて');
    $('d-ftags').value = S.f.tags; $('d-ftagmode').value = S.f.tag_mode;
    var n = S.f.status.length + S.f.priority.length + (S.f.assignee ? 1 : 0) + (S.f.customer ? 1 : 0) + (S.f.category ? 1 : 0) + (S.f.tags ? 1 : 0);
    $('d-ftoggle').innerHTML = '<i class="fa fa-sliders"></i>' + (n ? ' <b>' + n + '</b>' : '');
}
$('d-ftoggle').onclick = function () { $('d-filters').classList.toggle('on'); };
$('d-filters').addEventListener('click', function (e) { var c = e.target.closest('.d-chip'); if (!c) { return; } var a = S.f[c.dataset.k], i = a.indexOf(c.dataset.v); if (i >= 0) { a.splice(i, 1); } else { a.push(c.dataset.v); } S.page = 1; renderFilters(); load(); });
['d-fassignee', 'd-fcustomer', 'd-fcategory', 'd-ftagmode'].forEach(function (id) { $(id).onchange = function () { S.f.assignee = $('d-fassignee').value; S.f.customer = $('d-fcustomer').value; S.f.category = $('d-fcategory').value; S.f.tag_mode = $('d-ftagmode').value; S.page = 1; renderFilters(); load(); }; });
var tagTimer; $('d-ftags').oninput = function () { clearTimeout(tagTimer); tagTimer = setTimeout(function () { S.f.tags = $('d-ftags').value.trim(); S.page = 1; load(); }, 400); };
$('d-fclear').onclick = function () { S.f.status = []; S.f.priority = []; S.f.assignee = S.f.customer = S.f.category = S.f.tags = ''; S.page = 1; renderFilters(); load(); };
var kwTimer; $('d-kw').oninput = function () { clearTimeout(kwTimer); kwTimer = setTimeout(function () { S.f.kw = $('d-kw').value.trim(); S.page = 1; load(); }, 300); };
$('d-sort').onchange = function () { S.f.sort = this.value; load(); };
$('d-reload').onclick = function () { load(); if (S.sel) { openTicket(S.sel, true); } };
$('d-prev').onclick = function () { if (S.page > 1) { S.page--; load(); } };
$('d-next').onclick = function () { if (S.page < S.pages) { S.page++; load(); } };

// ---- list ---------------------------------------------------------------
function load(silent) {
    var p = new URLSearchParams({ queue: S.queue, page: S.page, kw: S.f.kw, assignee: S.f.assignee, customer: S.f.customer, category: S.f.category, tags: S.f.tags, tag_mode: S.f.tag_mode, sort: S.f.sort });
    S.f.status.forEach(function (x) { p.append('status[]', x); }); S.f.priority.forEach(function (x) { p.append('priority[]', x); });
    if (!silent) { $('d-items').innerHTML = '<div style="padding:14px">' + '<div class="d-skel"></div><div class="d-skel" style="width:70%"></div><div class="d-skel" style="width:85%"></div>'.repeat(3) + '</div>'; }
    return api(B.url.list + '?' + p.toString()).then(function (d) {
        if (d.error) { toast('一覧を読めませんでした', true); return; }
        S.items = d.items; S.counts = d.counts; S.total = d.total; S.pages = d.pages; S.page = d.page;
        renderQueues(); renderList(); syncUrl();
    });
}
function slaChip(it) {
    if (it.sla === 'none' || !it.sla_left) { return it.sla === 'met' ? soft('SLA 達成', B.slaColors.met, 'fa-check') : ''; }
    return soft(it.sla_kind + ' ' + it.sla_left, B.slaColors[it.sla] || '#888', 'fa-clock-o');
}
function renderList() {
    var box = $('d-items');
    $('d-total').textContent = B.queues[S.queue] + ' · ' + S.total + ' 件';
    $('d-page').textContent = S.page + ' / ' + S.pages;
    $('d-prev').disabled = S.page <= 1; $('d-next').disabled = S.page >= S.pages;
    $('d-sort').value = S.f.sort;
    if (!S.items.length) { box.innerHTML = '<div class="d-empty"><div><i class="fa fa-coffee"></i>チケットはありません</div></div>'; return; }
    box.innerHTML = S.items.map(function (it) {
        return '<div class="d-item' + (S.sel === it.id ? ' sel' : '') + (it.waiting ? ' unread' : '') + '" data-id="' + it.id + '">' +
            '<span class="edge" style="background:' + (B.priorityColors[it.priority] || 'transparent') + ';opacity:' + (it.priority === 'urgent' || it.priority === 'high' ? 1 : .35) + '"></span>' +
            '<input type="checkbox" class="ck" data-ck="' + it.id + '"' + (S.checked[it.id] ? ' checked' : '') + '>' +
            avFor(it.channel, it.requester || it.subject) +
            '<div class="d-im"><div class="d-r1"><span class="who">' + esc(it.requester || (CHICON[it.channel] ? CHICON[it.channel][2] : '—')) + '</span>' + (it.customer ? '<span class="org">· ' + esc(it.customer) + '</span>' : '') + '<span class="t" title="' + esc(fmt(it.updated)) + '">' + rel(it.updated) + '</span></div>' +
            '<div class="d-subj">' + esc(it.subject) + '</div>' +
            '<div class="d-prev"><span class="k">' + esc(it.no) + ' · ' + (it.last_kind === 'reply' ? '↩ ' : (it.last_kind === 'note' ? '✎ ' : '')) + '</span>' + esc(it.preview) + '</div>' +
            '<div class="d-r4">' + pill(B.statuses[it.status] || it.status, B.statusColors[it.status] || '#888') + slaChip(it) +
            (it.occurrences > 1 ? soft(it.occurrences + ' 回', '#e5484d', 'fa-repeat') : '') +
            (it.internal ? soft('社内', '#8b95a3', 'fa-eye-slash') : '') +
            it.tags.slice(0, 3).map(function (t) { return '<span class="its-tag">' + esc(t) + '</span>'; }).join('') +
            '<span style="margin-left:auto" title="' + esc(it.assignee_name || '担当者なし') + '">' + (it.assignee_name ? av(it.assignee_name, 's') : '<span class="d-av s" style="background:var(--its-line);color:var(--its-faint)">?</span>') + '</span>' +
            '</div></div></div>';
    }).join('');
    box.classList.toggle('bulking', Object.keys(S.checked).length > 0);
}
$('d-items').addEventListener('click', function (e) {
    var ck = e.target.closest('[data-ck]');
    if (ck) { e.stopPropagation(); if (ck.checked) { S.checked[ck.dataset.ck] = true; } else { delete S.checked[ck.dataset.ck]; } renderBulk(); $('d-items').classList.toggle('bulking', Object.keys(S.checked).length > 0); return; }
    var it = e.target.closest('.d-item'); if (it) { openTicket(parseInt(it.dataset.id, 10)); }
});

// ---- bulk ---------------------------------------------------------------
function renderBulk() {
    var n = Object.keys(S.checked).length;
    $('d-bulk').classList.toggle('on', n > 0); $('d-bcount').textContent = n;
    var a = $('d-baction').value;
    $('d-bvalue').innerHTML = a === 'status' ? opts(B.statuses) : (a === 'priority' ? opts(B.priorities) : '<option value="">（担当者なし）</option>' + opts(B.agents, B.me.id));
}
$('d-baction').onchange = renderBulk;
$('d-bcancel').onclick = function () { S.checked = {}; renderBulk(); renderList(); };
$('d-bapply').onclick = function () {
    api(B.url.bulk, { action: $('d-baction').value, value: $('d-bvalue').value, ids: Object.keys(S.checked) }).then(function (r) { toast(r.message || '変えました', !r.ok); S.checked = {}; renderBulk(); load(true); if (S.sel) { openTicket(S.sel, true); } });
};

// ---- ticket -------------------------------------------------------------
var lockTimer = null, lastHold = 0;
function openTicket(id, keep) {
    if (!id) { return; }
    var changing = S.sel !== id;
    if (changing) { saveDraft(); }
    S.sel = id; app.classList.add('opened');
    document.querySelectorAll('.d-item').forEach(function (el) { el.classList.toggle('sel', parseInt(el.dataset.id, 10) === id); });
    if (!keep) { $('d-detail').innerHTML = '<div style="padding:24px"><div class="d-skel" style="width:30%"></div><div class="d-skel" style="height:22px;width:60%"></div><div class="d-skel"></div><div class="d-skel" style="width:80%"></div></div>'; }
    api(u('ticket', id)).then(function (t) {
        if (t.error) { $('d-detail').innerHTML = '<div class="d-empty"><div><i class="fa fa-question-circle"></i>チケットが見つかりません</div></div>'; return; }
        if (S.sel !== id) { return; }
        var draft = keep && S.ticket && S.ticket.id === id ? { body: $('d-body') ? $('d-body').value : '', kind: S.kind } : null;
        S.ticket = t; renderTicket(draft); syncUrl();
    });
}
function syncUrl() {
    var url = B.url.base.replace(/\/$/, '') + (S.sel ? '/ticket/' + S.sel : '') + '?queue=' + encodeURIComponent(S.queue);
    try { history.replaceState(history.state, '', url); } catch (e) {}
}
function msgHtml(m) {
    if (m.kind === 'system') { return '<div class="d-sys">' + esc(m.body) + ' · ' + esc(fmt(m.at)) + (m.mail === 'failed' ? ' · <b style="color:var(--its-red)">メールを送れませんでした</b>' : '') + '</div>'; }
    var name = m.author || (m.kind === 'customer' ? 'お客様' : '担当者');
    return '<div class="d-msg ' + m.kind + '">' + av(name) + '<div class="d-bubble"><div class="d-mh"><b>' + esc(name) + '</b><span class="kind">' + esc(KIND[m.kind] || m.kind) + '</span>' +
        (m.kind === 'customer' && m.via ? '<span>' + esc(B.channels[m.via] || m.via) + '</span>' : '') +
        (m.mail === 'sent' ? '<span><i class="fa fa-envelope-o"></i> 送信済み</span>' : '') + (m.mail === 'failed' ? '<span style="color:var(--its-red)"><i class="fa fa-exclamation-circle"></i> 送信できず</span>' : '') +
        (m.minutes ? '<span><i class="fa fa-clock-o"></i> ' + m.minutes + ' 分</span>' : '') +
        '<span class="t" title="' + esc(fmt(m.at)) + '">' + rel(m.at) + '</span></div><div class="d-mb">' + esc(m.body) + '</div>' + (m.files ? '<div class="d-mf"><i class="fa fa-paperclip"></i> ' + esc(m.files) + '</div>' : '') + '</div></div>';
}
function slaBlock(t) {
    var s = t.sla, rows = '';
    var line = function (label, due, done, left) {
        if (!due) { return ''; }
        var state = done ? (new Date(String(done).replace(' ', 'T')) > new Date(String(due).replace(' ', 'T')) ? 'breached' : 'met') : (left.indexOf('超過') >= 0 ? 'breached' : (t.sla.state === 'warning' ? 'warning' : 'ok'));
        var color = B.slaColors[state];
        return '<div class="d-slab"><span>' + label + '</span><b style="color:' + color + '">' + (done ? (state === 'met' ? '✓ 期限内' : '超過して完了') : esc(left)) + '</b></div><div class="its-sub" style="margin-bottom:6px">期限 ' + esc(fmt(due)) + '</div>';
    };
    rows += line('最初の返事', s.first_due, s.first_done, s.first_left || '');
    rows += line('解決', s.resolve_due, s.resolve_done, s.resolve_left || '');
    if (!rows) { return '<div class="its-sub">SLA はありません（お客様に SLA が決まっていないか、標準の SLA がありません）。</div>'; }
    return rows + (s.paused ? '<div class="its-sub"><i class="fa fa-pause"></i> お客様の返事待ちの間、時計を止めています</div>' : '');
}
function renderTicket(draft) {
    var t = S.ticket, st = t.status;
    var tpl = '<option value="">定型文…</option>' + B.templates.map(function (x) { return '<option value="' + x.id + '">' + esc(x.name) + '</option>'; }).join('');
    var h = '<div class="d-dhead"><div class="d-crumb"><button class="its-btn small ghost d-back" id="d-back"><i class="fa fa-chevron-left"></i></button><span class="no">' + esc(t.no) + '</span><span>· ' + esc(B.channels[t.channel] || '') + ' · 受付 ' + esc(fmt(t.created)) + '</span>' +
        '<span style="margin-left:auto" class="its-row"><a class="its-btn small ghost" href="' + esc(u('edit', t.id)) + '" title="Exment の画面ですべての項目を編集"><i class="fa fa-pencil"></i></a><button class="its-btn small ghost" id="d-sidebtn" title="右の欄（\\）"><i class="fa fa-columns"></i></button></span></div>' +
        '<div class="d-title">' + esc(t.subject) + '</div><div class="d-chips2">' + pill(B.statuses[st] || st, B.statusColors[st] || '#888') + pill(B.priorities[t.priority] || t.priority, B.priorityColors[t.priority] || '#888') +
        (t.sla.state !== 'none' ? soft('SLA ' + (B.slaStates[t.sla.state] || ''), B.slaColors[t.sla.state] || '#888', 'fa-clock-o') : '') +
        (!t.customer_visible ? soft('お客様には見せない', '#8b95a3', 'fa-eye-slash') : '') + (t.require_otp ? soft('確認のコードあり', '#8e4ec6', 'fa-shield') : '') +
        (t.occurrences > 1 ? soft(t.occurrences + ' 回発生', '#e5484d', 'fa-repeat') : '') + (t.csat ? soft('満足度 ' + '★'.repeat(t.csat), '#f08c2b') : '') +
        '<span class="its-sub" style="margin-left:4px">' + esc(t.requester_name) + (t.requester_email ? ' &lt;' + esc(t.requester_email) + '&gt;' : '') + '</span></div></div>';

    h += '<div class="d-body' + (S.side ? '' : ' noside') + '" id="d-bodybox"><div class="d-main">';
    if (t.other_editor) { h += '<div class="d-banner red" id="d-lockbar"><i class="fa fa-user"></i> ' + esc(t.other_editor) + ' さんが返事を書いています。二重に返事をしないよう確かめてください。</div>'; } else { h += '<div class="d-banner red" id="d-lockbar" style="display:none"></div>'; }
    if (t.has_password) { h += '<div class="d-banner amber"><i class="fa fa-exclamation-triangle"></i> パスワードらしき文字があります。チケットに残さず、別の安全な場所で預かってください。</div>'; }
    h += '<div class="d-thread" id="d-thread"><div class="d-msg customer">' + avFor(t.channel, t.requester_name || t.requester_email || 'お客様') + '<div class="d-bubble"><div class="d-mh"><b>' + esc(t.requester_name || t.requester_email || (CHICON[t.channel] ? CHICON[t.channel][2] : 'お客様')) + '</b><span class="kind">内容</span><span class="t">' + rel(t.created) + '</span></div><div class="d-mb">' + esc(t.description) + '</div>' + (t.description_files ? '<div class="d-mf"><i class="fa fa-paperclip"></i> ' + esc(t.description_files) + '</div>' : '') + '</div></div>';
    h += t.messages.map(msgHtml).join('') + '</div>';

    h += '<div class="d-comp" id="d-comp"><div class="d-ctabs"><span class="d-ctab" data-kind="reply"><i class="fa fa-reply"></i> お客様へ返信</span><span class="d-ctab" data-kind="note"><i class="fa fa-lock"></i> 社内メモ</span>' +
        '<span style="margin-left:auto" class="d-hint"><kbd>Ctrl</kbd>+<kbd>Enter</kbd> で送る</span></div>' +
        '<div class="d-to" id="d-to">' + (t.requester_email ? '宛先 <b>' + esc(t.requester_email) + '</b> CC <input type="text" id="d-cc" value="' + esc(t.cc) + '" placeholder="なし">' : '<span style="color:var(--its-red)">お客様のメールアドレスがないので、メールでは届きません（チケットとリンクに残ります）。</span>') + '</div>' +
        '<textarea id="d-body" placeholder="返事を書く…"></textarea>' +
        '<div class="d-ctools"><select id="d-tpl">' + tpl + '</select><label class="its-btn small ghost" title="ファイルを付ける"><i class="fa fa-paperclip"></i><span id="d-fcount"></span><input type="file" id="d-files" multiple style="display:none"></label>' +
        '<label class="d-hint"><i class="fa fa-clock-o"></i> <input type="number" id="d-min" min="0" step="5" style="width:64px" placeholder="分"></label>' +
        '<div class="send"><select id="d-after"><option value="">状態はそのまま</option>' + opts(B.statuses) + '</select><button class="its-btn primary" id="d-send"><i class="fa fa-paper-plane"></i> <span>送る</span></button></div></div></div>';
    h += '</div>';

    // side
    var agents = '<option value="">（なし）</option>' + opts(B.agents, t.assignee);
    var assets = Object.keys(t.assets || {}).length ? '<div class="d-field"><label>IT 資産</label><select data-p="asset"><option value="">（なし）</option>' + opts(t.assets, t.asset) + '</select></div>' : '';
    h += '<aside class="d-side">' +
        '<div class="d-sec"><h4><i class="fa fa-sliders"></i> チケット</h4>' +
        '<div class="d-field"><label>状態</label><select data-p="status">' + opts(B.statuses, st) + '</select></div>' +
        '<div class="d-field"><label>優先度</label><select data-p="priority">' + opts(B.priorities, t.priority) + '</select></div>' +
        '<div class="d-field"><label>担当者</label><select data-p="assignee">' + agents + '</select></div>' +
        '<div class="d-field"><label>種類</label><select data-p="category">' + opts(B.categories, t.category, '（なし）') + '</select></div>' +
        '<div class="d-field"><label>お客様</label><select data-p="customer">' + opts(B.customers, t.customer, '（なし）') + '</select></div>' + assets +
        '<div class="d-field"><label>タグ</label><input type="text" data-p="tags" value="' + esc(t.tags) + '" placeholder="カンマ区切り"></div>' +
        '<div class="d-switch">お客様に見せる<label class="d-tg"><input type="checkbox" data-p="customer_visible"' + (t.customer_visible ? ' checked' : '') + '><span></span></label></div>' +
        '<div class="d-switch">開くときに確認のコード<label class="d-tg"><input type="checkbox" data-p="require_otp"' + (t.require_otp ? ' checked' : '') + '><span></span></label></div>' +
        '<div class="its-sub">作業時間 合計 <b>' + t.time_spent + ' 分</b></div></div>';
    h += '<div class="d-sec"><h4><i class="fa fa-clock-o"></i> SLA</h4>' + slaBlock(t) + '</div>';
    if (t.customer_info) {
        var c = t.customer_info, pct = c.contract_hours ? Math.min(100, Math.round(c.month_minutes * 100 / (parseFloat(c.contract_hours) * 60))) : null;
        h += '<div class="d-sec"><h4><i class="fa fa-building-o"></i> お客様</h4><div class="its-row">' + av(c.name, 'l') + '<div><a href="' + esc(u('customer', c.id)) + '"><b>' + esc(c.name) + '</b></a>' + (c.phone ? '<div class="its-sub"><i class="fa fa-phone"></i> ' + esc(c.phone) + '</div>' : '') + '</div></div>' +
            '<div class="d-slab" style="margin-top:10px"><span>今月の作業</span><b>' + Math.floor(c.month_minutes / 60) + ' 時間 ' + (c.month_minutes % 60) + ' 分' + (c.contract_hours ? ' / ' + esc(c.contract_hours) + ' 時間' : '') + '</b></div>' +
            (pct != null ? '<div class="its-bar"><span style="width:' + pct + '%;' + (pct >= 100 ? 'background:var(--its-red)' : (pct >= 80 ? 'background:var(--its-orange)' : '')) + '"></span></div>' : '') +
            (c.note ? '<div class="its-sub" style="margin-top:8px;white-space:pre-wrap">' + esc(c.note) + '</div>' : '') + '</div>';
    }
    if (t.asset_info) { var a = t.asset_info; h += '<div class="d-sec"><h4><i class="fa fa-desktop"></i> IT 資産</h4><a href="' + esc(u('asset', a.id)) + '"><b>' + esc(a.name) + '</b></a><div class="its-sub">' + esc([a.kind, a.model, a.os].filter(Boolean).join(' · ')) + '</div>' + (a.warranty_end ? '<div class="its-sub">保守・保証 ' + esc(a.warranty_end) + '</div>' : '') + (a.expires_on ? '<div class="its-sub">期限 ' + esc(a.expires_on) + '</div>' : '') + '</div>'; }
    h += '<div class="d-sec"><h4><i class="fa fa-link"></i> お客様のリンク</h4>' + (t.customer_visible ? '<div class="its-row"><button class="its-btn small" id="d-link"><i class="fa fa-clipboard"></i> リンクをコピー</button>' + (t.links ? '<button class="its-btn small ghost danger" id="d-revoke">取り消す</button>' : '') + '</div><div class="its-sub" style="margin-top:6px">ログインなしで、このチケットだけが見えます。返信のメールにも入ります。</div>' +
        (t.access.length ? '<div class="its-sub" style="margin-top:8px"><b>開いた記録</b>' + t.access.map(function (x) { return '<div>' + esc(rel(x.at)) + ' · ' + esc(x.action) + ' · ' + esc(x.ip || '') + '</div>'; }).join('') + '</div>' : '') : '<div class="its-sub">お客様に見せない設定です。</div>') + '</div>';
    h += '<div class="d-sec d-kn"><h4><i class="fa fa-book"></i> ナレッジ</h4><input type="search" id="d-ks" placeholder="探して返事に入れる" style="width:100%;height:28px;margin-bottom:4px"><div id="d-klist">' + knHtml(t.suggest) + '</div><button class="its-btn small ghost" id="d-tok" style="margin-top:6px"><i class="fa fa-plus"></i> このチケットからナレッジを作る</button></div>';
    if (t.files.length) { h += '<div class="d-sec"><h4><i class="fa fa-paperclip"></i> ファイル</h4>' + t.files.map(function (f) { return '<div><a href="' + esc(f.url) + '" target="_blank" rel="noopener">' + esc(f.name) + '</a></div>'; }).join('') + '</div>'; }
    if (t.related.length) { h += '<div class="d-sec d-rel"><h4><i class="fa fa-history"></i> 同じお客様のチケット</h4>' + t.related.map(function (r) { return '<a href="#" data-open="' + r.id + '"><span class="its-pill" style="background:' + (B.statusColors[r.status] || '#888') + '">' + esc(B.statuses[r.status] || r.status) + '</span>' + esc(r.subject) + '</a>'; }).join('') + '</div>'; }
    h += '<div class="d-sec"><h4><i class="fa fa-compress"></i> まとめる</h4><div class="its-row"><input type="text" id="d-into" placeholder="SUP-00012 または 12" style="flex:1;height:28px"><button class="its-btn small" id="d-merge">まとめる</button></div></div>';
    h += '</aside></div>';

    $('d-detail').innerHTML = h;
    var th = $('d-thread'); th.scrollTop = th.scrollHeight;
    setKind(draft ? draft.kind : 'reply');
    var saved = draft ? draft.body : loadDraft(t.id); if (saved) { $('d-body').value = saved; }
    bindTicket();
}
function knHtml(list) { if (!list || !list.length) { return '<div class="its-sub">見つかりませんでした。</div>'; } return list.map(function (k) { return '<a href="#" data-k="' + k.id + '">' + esc(k.title) + (k.public ? ' <span class="its-tag">公開</span>' : '') + '<small>' + esc(k.snippet) + '</small></a>'; }).join(''); }
function setKind(kind) {
    S.kind = kind; var c = $('d-comp'); if (!c) { return; }
    c.classList.toggle('note', kind === 'note');
    c.querySelectorAll('.d-ctab').forEach(function (x) { x.classList.toggle('on', x.dataset.kind === kind); });
    $('d-to').style.display = kind === 'note' ? 'none' : '';
    $('d-send').querySelector('span').textContent = kind === 'note' ? 'メモを残す' : '送る';
    $('d-body').placeholder = kind === 'note' ? '社内メモ（お客様には見えません）' : '返事を書く…（パスワードは書かないでください）';
}
function insert(text) { var b = $('d-body'); b.value = b.value ? b.value.replace(/\s*$/, '') + '\n\n' + text : text; b.focus(); hold(); }
function hold() { var now = Date.now(); if (!S.sel || now - lastHold < 50000) { return; } lastHold = now; api(u('lock', S.sel), {}).then(function (d) { var bar = $('d-lockbar'); if (!bar) { return; } if (d && d.mine === false && d.user_name) { bar.style.display = ''; bar.innerHTML = '<i class="fa fa-user"></i> ' + esc(d.user_name) + ' さんが返事を書いています。二重に返事をしないよう確かめてください。'; } else { bar.style.display = 'none'; } }); }
function draftKey(id) { return 'its_draft_' + id; }
function saveDraft() { try { var b = $('d-body'); if (b && S.ticket) { if (b.value.trim()) { localStorage.setItem(draftKey(S.ticket.id), b.value); } else { localStorage.removeItem(draftKey(S.ticket.id)); } } } catch (e) {} }
function loadDraft(id) { try { return localStorage.getItem(draftKey(id)) || ''; } catch (e) { return ''; } }
function update(data, msg) { return api(u('update', S.sel), data).then(function (r) { if (r.ok === false) { toast(r.message || '保存できませんでした', true); } else { toast(msg || '保存しました'); } load(true); openTicket(S.sel, true); }); }

function bindTicket() {
    var t = S.ticket;
    $('d-comp').querySelectorAll('.d-ctab').forEach(function (x) { x.onclick = function () { setKind(x.dataset.kind); $('d-body').focus(); }; });
    $('d-body').addEventListener('input', function () { hold(); this.style.height = 'auto'; this.style.height = Math.min(320, Math.max(92, this.scrollHeight)) + 'px'; });
    $('d-tpl').onchange = function () { var id = parseInt(this.value, 10), x = B.templates.filter(function (y) { return y.id === id; })[0]; this.value = ''; if (!x) { return; }
        var text = x.body.split('{name}').join(t.requester_name || 'お客様').split('{ticket_no}').join(t.no).split('{subject}').join(t.subject).split('{link}').join('（返信のメールに自動で入ります）');
        insert(text); if (x.status) { $('d-after').value = x.status; } };
    $('d-files').onchange = function () { $('d-fcount').textContent = this.files.length ? ' ' + this.files.length : ''; };
    $('d-send').onclick = send;
    $('d-sidebtn').onclick = toggleSide;
    var back = $('d-back'); if (back) { back.onclick = function () { app.classList.remove('opened'); }; }
    $('d-detail').querySelectorAll('[data-p]').forEach(function (el) {
        el.addEventListener('change', function () { var d = {}; d[el.dataset.p] = el.type === 'checkbox' ? (el.checked ? '1' : '0') : el.value; update(d); });
    });
    $('d-detail').querySelectorAll('[data-open]').forEach(function (a) { a.onclick = function (e) { e.preventDefault(); openTicket(parseInt(a.dataset.open, 10)); }; });
    var kl = $('d-klist');
    var bindK = function () { kl.querySelectorAll('[data-k]').forEach(function (a) { a.onclick = function (e) { e.preventDefault(); api(B.url.ksearch + '?q=' + encodeURIComponent(a.firstChild.textContent)).then(function (d) { var k = (d.items || []).filter(function (x) { return String(x.id) === a.dataset.k; })[0]; if (k) { insert(k.url ? k.title + '\n' + k.url : k.body); } }); }; }); };
    bindK();
    var kt; $('d-ks').oninput = function () { var q = this.value; clearTimeout(kt); kt = setTimeout(function () { if (!q.trim()) { kl.innerHTML = knHtml(t.suggest); bindK(); return; } api(B.url.ksearch + '?q=' + encodeURIComponent(q)).then(function (d) { kl.innerHTML = knHtml(d.items); bindK(); }); }, 300); };
    $('d-tok').onclick = function () { api(u('knowledge', t.id), {}).then(function (r) { if (r.redirect) { toast(r.message); window.open(r.redirect, '_blank'); } else { toast(r.message || 'できませんでした', true); } }); };
    var lk = $('d-link'); if (lk) { lk.onclick = function () { api(u('share', t.id), { action: 'link' }).then(function (r) { if (!r.link) { toast(r.message || 'できませんでした', true); return; } var done = function () { toast('リンクをコピーしました'); }; if (navigator.clipboard) { navigator.clipboard.writeText(r.link).then(done, function () { window.prompt('リンク', r.link); }); } else { window.prompt('リンク', r.link); } openTicket(t.id, true); }); }; }
    var rv = $('d-revoke'); if (rv) { rv.onclick = function () { if (!confirm('このチケットのリンクをすべて取り消します。お客様に送ったリンクは開けなくなります。')) { return; } api(u('share', t.id), { action: 'revoke' }).then(function (r) { toast(r.message); openTicket(t.id, true); }); }; }
    $('d-merge').onclick = function () { var into = $('d-into').value.trim(); if (!into || !confirm('このチケットを ' + into + ' にまとめて、このチケットは完了にします。')) { return; } api(u('merge', t.id), { into: into }).then(function (r) { toast(r.message, r.ok === false); var m = /ticket\/(\d+)/.exec(r.redirect || ''); load(true); if (m && r.ok !== false) { openTicket(parseInt(m[1], 10)); } }); };
}
function send() {
    var body = $('d-body').value.trim(), files = $('d-files').files;
    if (!body && !files.length) { toast('本文を書いてください', true); $('d-body').focus(); return; }
    var fd = new FormData(); fd.append('body', body); fd.append('kind', S.kind); fd.append('status', $('d-after').value); fd.append('time_spent', $('d-min').value || '0');
    if ($('d-cc')) { fd.append('cc', $('d-cc').value); }
    for (var i = 0; i < files.length; i++) { fd.append('files[]', files[i]); }
    var btn = $('d-send'); btn.disabled = true; btn.querySelector('span').textContent = '送っています…';
    api(u('reply', S.sel), fd).then(function (r) {
        btn.disabled = false;
        toast(r.message || (r.ok ? '送りました' : '送れませんでした'), r.ok === false);
        $('d-body').value = ''; try { localStorage.removeItem(draftKey(S.sel)); } catch (e) {}
        load(true); openTicket(S.sel, true);
    }).catch(function () { btn.disabled = false; setKind(S.kind); toast('送れませんでした。通信を確かめてください', true); });
}
function toggleSide() { S.side = !S.side; try { localStorage.setItem('its_side', S.side ? '1' : '0'); } catch (e) {} var b = $('d-bodybox'); if (b) { b.classList.toggle('noside', !S.side); } }

// ---- keyboard -----------------------------------------------------------
document.addEventListener('keydown', function (e) {
    if (!document.body.contains(root)) { return; }
    var tag = (e.target.tagName || '').toLowerCase(), typing = tag === 'input' || tag === 'textarea' || tag === 'select';
    if ((e.ctrlKey || e.metaKey) && e.key === 'Enter' && e.target.id === 'd-body') { e.preventDefault(); send(); return; }
    if (e.key === 'Escape') { $('d-help').classList.remove('on'); if (typing) { e.target.blur(); } return; }
    if (typing || e.ctrlKey || e.metaKey || e.altKey) { return; }
    var idx = S.items.findIndex(function (x) { return x.id === S.sel; });
    switch (e.key) {
        case 'j': case 'ArrowDown': if (S.items[idx + 1]) { e.preventDefault(); openTicket(S.items[idx + 1].id); scrollSel(); } break;
        case 'k': case 'ArrowUp': if (idx > 0) { e.preventDefault(); openTicket(S.items[idx - 1].id); scrollSel(); } break;
        case '/': e.preventDefault(); $('d-kw').focus(); break;
        case 'r': if (S.ticket) { e.preventDefault(); setKind('reply'); $('d-body').focus(); } break;
        case 'n': if (S.ticket) { e.preventDefault(); setKind('note'); $('d-body').focus(); } break;
        case 'a': if (S.ticket) { e.preventDefault(); update({ assignee: B.me.id }, '自分を担当者にしました'); } break;
        case 'e': if (S.ticket) { e.preventDefault(); update({ status: 'resolved' }, '解決にしました'); } break;
        case '\\': if (S.ticket) { e.preventDefault(); toggleSide(); } break;
        case '?': e.preventDefault(); $('d-help').classList.add('on'); break;
    }
});
function scrollSel() { setTimeout(function () { var el = document.querySelector('.d-item.sel'); if (el) { el.scrollIntoView({ block: 'nearest' }); } }, 30); }

// ---- go -----------------------------------------------------------------
renderFilters(); renderBulk();
load().then(function () { if (B.open) { openTicket(B.open); } else if (window.innerWidth > 820 && S.items.length) { openTicket(S.items[0].id); } });
// Keep the list fresh without disturbing someone who is typing.
var poll = setInterval(function () { if (!document.body.contains(root)) { clearInterval(poll); return; } var b = $('d-body'); if (document.hidden || (b && document.activeElement === b)) { return; } load(true); }, 60000);
window.addEventListener('beforeunload', saveDraft);
})();
</script>
