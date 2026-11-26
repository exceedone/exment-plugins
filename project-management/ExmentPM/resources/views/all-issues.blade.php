{{-- The issue list across all projects (ISS00264): PortalAllIssuesTrait@allIssues --}}
@php
    use App\Plugins\ExmentPM\PortalDefinition;

    $a = function ($key, $args = []) {
        return PortalDefinition::trans('all.' . $key, $args);
    };
    $texts = [];
    foreach (['projects', 'assignees', 'me', 'status', 'open', 'priority', 'type', 'due', 'due_any', 'due_overdue', 'due_today', 'due_week', 'due_none',
              'words', 'sort_due', 'sort_pr', 'sort_up', 'sort_key', 'count', 'none', 'loading', 'fail', 'prev', 'next', 'clear', 'save', 'save_ask',
              'saved', 'del', 'all', 'pick', 'col_key', 'col_subject', 'col_project', 'col_type', 'col_status', 'col_priority', 'col_as', 'col_due', 'col_up'] as $k) {
        $texts[$k] = strval($a($k));
    }
@endphp
<style>
.pp-all{--a-line:#dfe5ea;--a-text:#33434f;--a-mute:#8a97a3;--a-accent:#4cae93;--a-red:#e5544b;--a-box:#fff;--a-soft:#f6f8fa;color:var(--a-text);}
.pp-all .pp-a-wrap{padding:12px 16px;}
.pp-all .pp-a-bar{display:flex;flex-wrap:wrap;gap:8px;align-items:flex-start;background:var(--a-box);border:1px solid var(--a-line);border-radius:7px;padding:10px 12px;}
.pp-all .pp-a-f{position:relative;}
.pp-all .pp-a-f>button{border:1px solid var(--a-line);background:var(--a-box);border-radius:5px;padding:5px 10px;font-size:12.5px;cursor:pointer;min-height:32px;}
.pp-all .pp-a-f>button.on{border-color:var(--a-accent);background:color-mix(in srgb, var(--a-accent) 10%, var(--a-box));font-weight:700;}
.pp-all .pp-a-pop{position:absolute;z-index:70;top:100%;left:0;margin-top:4px;background:var(--a-box);border:1px solid var(--a-line);border-radius:6px;
  box-shadow:0 6px 18px rgba(0,0,0,.12);padding:6px;min-width:220px;max-height:340px;overflow:auto;}
.pp-all .pp-a-pop input[type=search]{width:100%;margin-bottom:6px;padding:4px 6px;border:1px solid var(--a-line);border-radius:4px;}
.pp-all .pp-a-pop label{display:flex;align-items:center;gap:6px;padding:4px 6px;font-weight:400;font-size:12.5px;margin:0;cursor:pointer;border-radius:4px;}
.pp-all .pp-a-pop label:hover{background:var(--a-soft);}
.pp-all .pp-a-bar input.q{border:1px solid var(--a-line);border-radius:5px;padding:5px 8px;font-size:12.5px;min-height:32px;min-width:160px;}
.pp-all .pp-a-bar select{border:1px solid var(--a-line);border-radius:5px;padding:4px 6px;font-size:12.5px;min-height:32px;background:var(--a-box);}
.pp-all .pp-a-sv{display:flex;flex-wrap:wrap;gap:6px;margin:8px 0 0;font-size:12px;}
.pp-all .pp-a-sv a{color:var(--a-accent);font-weight:700;}
.pp-all .pp-a-sv .x{color:var(--a-mute);margin-left:2px;}
.pp-all .pp-a-head{display:flex;align-items:center;gap:10px;margin:12px 0 6px;font-size:12.5px;color:var(--a-mute);}
.pp-all .pp-a-head .pg{margin-left:auto;display:flex;gap:6px;align-items:center;}
.pp-all .pp-a-head .pg button{border:1px solid var(--a-line);background:var(--a-box);border-radius:4px;padding:3px 9px;cursor:pointer;}
.pp-all .pp-a-head .pg button[disabled]{opacity:.4;cursor:default;}
.pp-all table{width:100%;border-collapse:collapse;background:var(--a-box);border:1px solid var(--a-line);border-radius:7px;overflow:hidden;font-size:12.5px;}
.pp-all th{background:var(--a-soft);text-align:left;font-weight:700;padding:7px 8px;border-bottom:1px solid var(--a-line);white-space:nowrap;}
.pp-all th[data-sort]{cursor:pointer;}
.pp-all th[data-sort]:hover{text-decoration:underline;}
.pp-all td{padding:6px 8px;border-bottom:1px solid var(--a-line);vertical-align:top;}
.pp-all tr:hover td{background:#f2f8f5;}
.pp-all td.key a{font-family:Menlo,Consolas,monospace;font-weight:700;color:#3c8dbc;white-space:nowrap;}
.pp-all td.sub a{color:var(--a-text);}
.pp-all .chip{display:inline-block;border-radius:9px;padding:1px 8px;color:#fff;font-size:11px;white-space:nowrap;}
.pp-all .late{color:var(--a-red);font-weight:700;}
.pp-all .mute{color:var(--a-mute);}
@media (max-width: 767px){
  .pp-all .pp-a-wrap{padding:8px;}
  .pp-all table thead{display:none;}
  .pp-all table,.pp-all tbody,.pp-all tr,.pp-all td{display:block;width:100%;}
  .pp-all tr{border-bottom:1px solid var(--a-line);padding:6px 0;}
  .pp-all td{border:0;padding:2px 8px;}
  .pp-all td.c-pj,.pp-all td.c-up{font-size:11.5px;color:var(--a-mute);}
}
</style>

<div class="pp-all">
    @include(PortalDefinition::view('_topbar'))
    <div class="pp-a-wrap">
        <h3 style="margin:4px 0 10px;font-size:18px;"><i class="fa fa-list-ul"></i> {{ $a('title') }}</h3>
        <div class="pp-a-bar" id="pp-a-bar"></div>
        <div class="pp-a-sv" id="pp-a-sv"></div>
        <div class="pp-a-head"><span id="pp-a-count"></span><span class="pg" id="pp-a-pg"></span></div>
        <div id="pp-a-list"></div>
    </div>
</div>

<script type="application/json" id="pp-a-boot">@json(['boot' => $boot, 'L' => $texts])</script>
<script>
(function () {
    'use strict';
    var B = JSON.parse(document.getElementById('pp-a-boot').textContent);
    var D = B.boot, L = B.L;
    var KEY = 'pmportal.all.u' + String(D.me || 0);
    function esc(s) { return String(s === null || s === undefined ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
    function tx(k, n) { return String(L[k] || k).replace(':n', n); }

    // the filters: lists of values, each list one filter; also in the address, so a view can be bookmarked
    var F = { pj: [], as: [], st: [], pr: [], ty: [], due: '', q: '', sort: 'due', dir: 'asc', page: 1 };
    var FILTERS = [
        { k: 'pj', l: L.projects, items: function () { return D.projects.map(function (p) { return { v: p.id, l: (p.k ? p.k + ' ' : '') + p.n + (p.ar ? '（アーカイブ）' : '') }; }); }, search: true },
        { k: 'as', l: L.assignees, items: function () { return [{ v: 'me', l: L.me }].concat(D.users.map(function (u) { return { v: String(u.id), l: u.n }; })); }, search: true },
        { k: 'st', l: L.status, items: function () { return [{ v: '_open', l: L.open }].concat(D.status.map(function (s) { return { v: s.l, l: s.l }; })); } },
        { k: 'pr', l: L.priority, items: function () { return D.priority.map(function (s) { return { v: s.l, l: s.l }; }); } },
        { k: 'ty', l: L.type, items: function () { return D.type.map(function (s) { return { v: s.l, l: s.l }; }); } }
    ];
    function fromUrl() {
        var p = new URLSearchParams(location.search);
        var any = false;
        ['pj', 'as', 'st', 'pr', 'ty'].forEach(function (k) { if (p.has(k)) { F[k] = p.get(k).split(',').filter(Boolean); any = true; } });
        ['due', 'q', 'sort', 'dir'].forEach(function (k) { if (p.has(k)) { F[k] = p.get(k); any = true; } });
        if (p.has('page')) { F.page = Math.max(1, parseInt(p.get('page'), 10) || 1); }
        if (!any) { F.as = ['me']; F.st = ['_open']; }
    }
    function query(withPage) {
        var p = new URLSearchParams();
        ['pj', 'as', 'st', 'pr', 'ty'].forEach(function (k) { if (F[k].length) { p.set(k, F[k].join(',')); } });
        ['due', 'q'].forEach(function (k) { if (F[k]) { p.set(k, F[k]); } });
        if (F.sort !== 'due') { p.set('sort', F.sort); }
        if (F.dir !== 'asc') { p.set('dir', F.dir); }
        if (withPage && F.page > 1) { p.set('page', String(F.page)); }
        return p.toString();
    }

    var bar = document.getElementById('pp-a-bar');
    function labelOf(f) {
        var n = F[f.k].length;
        return esc(f.l) + (n ? ' (' + n + ')' : '');
    }
    function drawBar() {
        bar.innerHTML = FILTERS.map(function (f) {
            return '<div class="pp-a-f" data-f="' + f.k + '"><button type="button" class="' + (F[f.k].length ? 'on' : '') + '" aria-haspopup="true">' + labelOf(f) + ' <i class="fa fa-caret-down"></i></button></div>';
        }).join('') +
            '<select id="pp-a-due" aria-label="' + esc(L.due) + '">' + [['', L.due_any], ['overdue', L.due_overdue], ['today', L.due_today], ['week', L.due_week], ['none', L.due_none]].map(function (o) {
                return '<option value="' + o[0] + '"' + (F.due === o[0] ? ' selected' : '') + '>' + esc(L.due + ': ' + o[1]) + '</option>';
            }).join('') + '</select>' +
            '<input type="search" class="q" id="pp-a-q" placeholder="' + esc(L.words) + '" value="' + esc(F.q) + '">' +
            '<select id="pp-a-sort">' + [['due', L.sort_due], ['pr', L.sort_pr], ['up', L.sort_up], ['key', L.sort_key]].map(function (o) {
                return '<option value="' + o[0] + '"' + (F.sort === o[0] ? ' selected' : '') + '>' + esc(o[1]) + '</option>';
            }).join('') + '</select>' +
            '<select id="pp-a-dir"><option value="asc"' + (F.dir === 'asc' ? ' selected' : '') + '>↑</option><option value="desc"' + (F.dir === 'desc' ? ' selected' : '') + '>↓</option></select>' +
            '<button type="button" id="pp-a-clear" style="border:0;background:none;color:var(--a-mute);text-decoration:underline;cursor:pointer;">' + esc(L.clear) + '</button>' +
            '<button type="button" id="pp-a-save" style="border:0;background:none;color:var(--a-accent);font-weight:700;cursor:pointer;"><i class="fa fa-bookmark-o"></i> ' + esc(L.save) + '</button>';
    }
    var openPop = null;
    function closePop() { if (openPop) { openPop.remove(); openPop = null; } }
    function pop(box, f) {
        closePop();
        var items = f.items();
        var el = document.createElement('div');
        el.className = 'pp-a-pop';
        el.setAttribute('role', 'dialog');
        el.innerHTML = (f.search || items.length > 12 ? '<input type="search" placeholder="' + esc(L.pick) + '">' : '') +
            '<label><input type="checkbox" data-all="1"' + (F[f.k].length ? '' : ' checked') + '> ' + esc(L.all) + '</label>' +
            items.map(function (it) {
                return '<label data-l="' + esc(String(it.l).toLowerCase()) + '"><input type="checkbox" value="' + esc(it.v) + '"' + (F[f.k].indexOf(String(it.v)) >= 0 ? ' checked' : '') + '> ' + esc(it.l) + '</label>';
            }).join('');
        box.appendChild(el);
        openPop = el;
        var s = el.querySelector('input[type=search]');
        if (s) {
            s.focus();
            s.addEventListener('input', function () {
                var w = s.value.trim().toLowerCase();
                el.querySelectorAll('label[data-l]').forEach(function (lb) { lb.hidden = w !== '' && lb.getAttribute('data-l').indexOf(w) < 0; });
            });
        }
        el.addEventListener('change', function (ev) {
            var cb = ev.target;
            if (cb.getAttribute('data-all')) {
                F[f.k] = [];
                el.querySelectorAll('input[type=checkbox]:not([data-all])').forEach(function (x) { x.checked = false; });
                cb.checked = true;
            } else {
                F[f.k] = Array.prototype.map.call(el.querySelectorAll('input[type=checkbox]:not([data-all]):checked'), function (x) { return x.value; });
                el.querySelector('[data-all]').checked = !F[f.k].length;
            }
            box.querySelector('button').className = F[f.k].length ? 'on' : '';
            box.querySelector('button').innerHTML = labelOf(f) + ' <i class="fa fa-caret-down"></i>';
            F.page = 1;
            load();
        });
    }
    bar.addEventListener('click', function (ev) {
        var btn = ev.target.closest('.pp-a-f > button');
        if (btn) {
            var box = btn.parentNode;
            if (openPop && openPop.parentNode === box) { closePop(); return; }
            pop(box, FILTERS.filter(function (f) { return f.k === box.getAttribute('data-f'); })[0]);
            return;
        }
        if (ev.target.closest('#pp-a-clear')) {
            F = { pj: [], as: [], st: [], pr: [], ty: [], due: '', q: '', sort: 'due', dir: 'asc', page: 1 };
            drawBar(); load();
            return;
        }
        if (ev.target.closest('#pp-a-save')) { saveView(); }
    });
    document.addEventListener('mousedown', function (ev) { if (openPop && !ev.target.closest('.pp-a-f')) { closePop(); } });
    document.addEventListener('keydown', function (ev) { if (ev.key === 'Escape') { closePop(); } });
    bar.addEventListener('change', function (ev) {
        if (ev.target.id === 'pp-a-due') { F.due = ev.target.value; }
        else if (ev.target.id === 'pp-a-sort') { F.sort = ev.target.value; }
        else if (ev.target.id === 'pp-a-dir') { F.dir = ev.target.value; }
        else { return; }
        F.page = 1; load();
    });
    var qTimer = null;
    bar.addEventListener('input', function (ev) {
        if (ev.target.id !== 'pp-a-q') { return; }
        clearTimeout(qTimer);
        qTimer = setTimeout(function () { F.q = ev.target.value.trim(); F.page = 1; load(); }, 350);
    });

    // saved views: in this browser
    function saved() { try { var v = JSON.parse(localStorage.getItem(KEY) || '[]'); return Array.isArray(v) ? v : []; } catch (e) { return []; } }
    function drawSaved() {
        var list = saved();
        document.getElementById('pp-a-sv').innerHTML = list.map(function (v, n) {
            return '<span><a href="?' + esc(v.q) + '">' + esc(v.n) + '</a><a href="javascript:void(0);" class="x" data-del="' + n + '" title="' + esc(L.del) + '">×</a></span>';
        }).join(' ・ ');
    }
    function saveView() {
        var name = window.prompt(L.save_ask);
        if (!name) { return; }
        var list = saved().filter(function (v) { return v.n !== name; });
        list.push({ n: name.slice(0, 40), q: query(false) });
        try { localStorage.setItem(KEY, JSON.stringify(list.slice(-20))); } catch (e) { /* ignore */ }
        drawSaved();
    }
    document.getElementById('pp-a-sv').addEventListener('click', function (ev) {
        var d = ev.target.closest('[data-del]');
        if (!d) { return; }
        var list = saved();
        list.splice(Number(d.getAttribute('data-del')), 1);
        try { localStorage.setItem(KEY, JSON.stringify(list)); } catch (e) { /* ignore */ }
        drawSaved();
    });

    // the rows
    var box = document.getElementById('pp-a-list');
    var seq = 0;
    function chip(m) { return m ? '<span class="chip" style="background:' + esc(m.c || '#999') + '">' + esc(m.l) + '</span>' : ''; }
    function load() {
        var my = ++seq;
        var qs = query(true);
        history.replaceState(null, '', location.pathname + (qs ? '?' + qs : ''));
        document.getElementById('pp-a-count').textContent = L.loading;
        fetch(D.data_url + '?' + query(true), { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' } })
            .then(function (r) { if (!r.ok) { throw new Error(); } return r.json(); })
            .then(function (res) { if (my === seq) { draw(res); } })
            .catch(function () { if (my === seq) { document.getElementById('pp-a-count').textContent = L.fail; } });
    }
    function th(key, label) {
        var on = F.sort === key;
        return '<th' + (key ? ' data-sort="' + key + '"' : '') + '>' + esc(label) + (on ? (F.dir === 'asc' ? ' ▲' : ' ▼') : '') + '</th>';
    }
    function draw(res) {
        F.page = res.page || 1;
        document.getElementById('pp-a-count').textContent = tx('count', res.total);
        var pages = res.pages || 1;
        document.getElementById('pp-a-pg').innerHTML = pages > 1
            ? '<button type="button" data-pg="' + (F.page - 1) + '"' + (F.page <= 1 ? ' disabled' : '') + '>' + esc(L.prev) + '</button>' +
              '<span>' + F.page + ' / ' + pages + '</span>' +
              '<button type="button" data-pg="' + (F.page + 1) + '"' + (F.page >= pages ? ' disabled' : '') + '>' + esc(L.next) + '</button>'
            : '';
        if (!res.rows.length) { box.innerHTML = '<div class="mute" style="padding:24px;text-align:center;">' + esc(L.none) + '</div>'; return; }
        box.innerHTML = '<table><thead><tr>' + th('key', L.col_key) + th('', L.col_subject) + th('', L.col_project) + th('', L.col_type) + th('', L.col_status) +
            th('pr', L.col_priority) + th('', L.col_as) + th('due', L.col_due) + th('up', L.col_up) + '</tr></thead><tbody>' +
            res.rows.map(function (r) {
                return '<tr><td class="key"><a href="' + esc(r.url) + '">' + esc(r.key) + '</a></td>' +
                    '<td class="sub"><a href="' + esc(r.url) + '">' + esc(r.subject) + '</a></td>' +
                    '<td class="c-pj">' + esc(r.project) + '</td><td>' + chip(r.type) + '</td><td>' + chip(r.status) + '</td><td>' + chip(r.priority) + '</td>' +
                    '<td>' + esc((r.as || []).join(', ')) + '</td>' +
                    '<td class="' + (r.late ? 'late' : '') + '" style="white-space:nowrap;">' + esc(r.due || '') + '</td>' +
                    '<td class="c-up mute" style="white-space:nowrap;">' + esc(r.up || '') + '</td></tr>';
            }).join('') + '</tbody></table>';
    }
    box.addEventListener('click', function (ev) {
        var h = ev.target.closest('th[data-sort]');
        if (!h) { return; }
        var k = h.getAttribute('data-sort');
        if (F.sort === k) { F.dir = F.dir === 'asc' ? 'desc' : 'asc'; } else { F.sort = k; F.dir = k === 'up' ? 'desc' : 'asc'; }
        F.page = 1; drawBar(); load();
    });
    document.getElementById('pp-a-pg').addEventListener('click', function (ev) {
        var b = ev.target.closest('button[data-pg]');
        if (!b || b.disabled) { return; }
        F.page = Number(b.getAttribute('data-pg'));
        load();
        window.scrollTo(0, 0);
    });

    fromUrl();
    drawBar();
    drawSaved();
    load();
})();
</script>
