{{-- Exment Plugin Groupware: the calendar's links (ISS02947 part 5). The button 「連携」 in the calendar's toolbar and its dialog: the person's ICS export address, and the outside calendars (ICS addresses) read into their calendar. Read from cal/api/links; every text is put in as text (textContent), never as markup. --}}
<script>
var GWCAL_LINKS = @json($links);
</script>
@verbatim
<style>
.gwcal-lk h4{margin:14px 0 4px;font-size:14px;font-weight:700}
.gwcal-lk h4:first-child{margin-top:4px}
.gwcal-lk .help{margin:0 0 8px;color:var(--sub);font-size:12.5px;overflow-wrap:anywhere}
.gwcal-lk .state{margin:0 0 8px;overflow-wrap:anywhere}
.gwcal-lk .acts{display:flex;flex-wrap:wrap;gap:6px;margin:0 0 6px}
.gwcal-lk .once{margin:8px 0;padding:8px 10px;border:1px solid #f0d58a;border-radius:8px;background:#fff6e0;color:#5c3d00}
.gwcal-lk .once p{margin:0 0 6px}
.gwcal-lk .urlrow{display:flex;gap:6px;min-width:0}
.gwcal-lk .urlrow input{flex:1;min-width:0;height:32px;padding:0 8px;border:1px solid var(--line);border-radius:7px;background:var(--box);font-family:ui-monospace,Menlo,Consolas,monospace;font-size:12px}
.gwcal-lk ul.lks{list-style:none;margin:0 0 8px;padding:0}
.gwcal-lk ul.lks li{padding:8px 0;border-bottom:1px solid var(--line2);min-width:0}
.gwcal-lk ul.lks .n{font-weight:600;overflow-wrap:anywhere}
.gwcal-lk ul.lks .m{display:block;margin-top:2px;font-size:12px;color:var(--sub);overflow-wrap:anywhere}
.gwcal-lk ul.lks .m.err{color:var(--red)}
.gwcal-lk ul.lks .acts{margin:6px 0 0}
.gwcal-lk .none{color:var(--faint)}
.gwcal-lk .gwcal-f input[type=url]{width:100%;min-width:0;height:34px;padding:0 9px;border:1px solid var(--line);border-radius:7px;background:var(--box)}
@media (max-width:640px){
  .gwcal-lk .urlrow{flex-wrap:wrap}
  .gwcal-lk .urlrow input{flex-basis:100%}
}
</style>
<script>
(function () {
'use strict';
var K = window.GWCAL_LINKS || null, C = window.GWCAL_CFG || {};
var bar = document.getElementById('gwcal-bar');
if (!K || !K.urls || !bar) { return; }
var L = K.lang && typeof K.lang === 'object' ? K.lang : {};
var LOC = String(document.documentElement.getAttribute('lang') || 'ja').replace('_', '-');
var $ = function (id) { return document.getElementById(id); };

function t(k, args) {
    var v = typeof L[k] === 'string' ? L[k] : k;
    Object.keys(args || {}).forEach(function (a) { v = v.split(':' + a).join(String(args[a])); });
    return v;
}
function el(tag, cls, text) {
    var n = document.createElement(tag);
    if (cls) { n.className = cls; }
    if (text != null) { n.textContent = text; }
    return n;
}
function btn(cls, text, fn) {
    var b = el('button', cls, text);
    b.type = 'button';
    if (fn) { b.addEventListener('click', fn); }
    return b;
}
function clear(n) { while (n.firstChild) { n.removeChild(n.firstChild); } }
// 'Y-m-d H:i:s' UTC as the viewer's local date and time
function when(s) {
    var m = /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/.exec(String(s || ''));
    if (!m) { return ''; }
    var d = new Date(Date.UTC(+m[1], +m[2] - 1, +m[3], +m[4], +m[5]));
    try { return new Intl.DateTimeFormat(LOC, { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }).format(d); } catch (e) { return s; }
}
function toast(text, err) {
    var n = $('gwcal-toast');
    if (!n) { return; }
    n.textContent = text;
    n.className = 'gwcal-toast' + (err ? ' err' : '');
    n.hidden = false;
    clearTimeout(toast.timer);
    toast.timer = setTimeout(function () { n.hidden = true; }, err ? 7000 : 3000);
}
function api(url, params, post) {
    var o = { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-CSRF-TOKEN': C.csrf || '' }, credentials: 'same-origin' };
    if (post) {
        var fd = new FormData();
        Object.keys(params || {}).forEach(function (k) { if (params[k] != null) { fd.append(k, params[k]); } });
        fd.append('_token', C.csrf || '');
        o.method = 'POST';
        o.body = fd;
    }
    return fetch(url, o).then(function (r) {
        if (r.status === 419) { return { ok: false, message: t('err_expired') }; }
        return r.json().then(function (j) {
            if (!j || typeof j !== 'object') { return { ok: false, message: t('err_failed') }; }
            if (!j.ok && !j.message) { j.message = t('err_failed'); }
            return j;
        }, function () { return { ok: false, message: t('err_failed') }; });
    }, function () { return { ok: false, message: t('err_network') }; });
}
// the error of a read, as words (the server keeps a code)
function errText(code) {
    code = String(code || '');
    var m = /^http_(\d+)$/.exec(code);
    if (m) { return t('e_http', { n: m[1] }); }
    return typeof L['e_' + code] === 'string' ? L['e_' + code] : t('e_failed');
}

// ---- the dialog (the calendar's own) --------------------------------------------
var lastFocus = null;
function show(nodes) {
    var m = $('gwcal-modal'), dlg = $('gwcal-dlg');
    if (!m || !dlg) { return; }
    if (m.hidden) { lastFocus = document.activeElement; }
    clear(dlg);
    nodes.forEach(function (n) { if (n) { dlg.appendChild(n); } });
    m.hidden = false;
    var f = dlg.querySelector('[data-focus]') || dlg.querySelector('input,select,button');
    if (f) { f.focus(); }
}
function close() {
    var m = $('gwcal-modal');
    if (m) { m.hidden = true; clear($('gwcal-dlg')); }
    if (lastFocus && lastFocus.focus && document.body.contains(lastFocus)) { lastFocus.focus(); }
}
function head(title) {
    var h = el('div', 'gwcal-dhead');
    var h3 = el('h3', '', title);
    h3.id = 'gwcal-dlg-title';
    h.appendChild(h3);
    var x = btn('gb sq', null, close);
    x.setAttribute('aria-label', t('close'));
    x.title = t('close');
    var i = el('i', 'fa fa-times');
    i.setAttribute('aria-hidden', 'true');
    x.appendChild(i);
    h.appendChild(x);
    return h;
}
function field(label, input) {
    var f = el('div', 'gwcal-f');
    var lb = el('label', '', label);
    input.id = input.id || ('gwlk-' + Math.random().toString(36).slice(2));
    lb.htmlFor = input.id;
    f.appendChild(lb);
    f.appendChild(input);
    return f;
}

var state = { links: [], feed: null, url: null };

function load() {
    return api(K.urls.links).then(function (r) {
        if (!r.ok) { toast(r.message, true); return; }
        state.links = r.links || [];
        state.feed = r.feed || null;
    });
}
function open() {
    state.url = null;
    load().then(render);
}

function render() {
    var b = el('div', 'gwcal-dbody gwcal-lk');
    // the ICS export address
    b.appendChild(el('h4', '', t('feed_title')));
    b.appendChild(el('p', 'help', t('feed_help')));
    var acts = el('div', 'acts');
    if (state.feed) {
        var st = el('p', 'state', t('feed_active', { date: when(state.feed.created_at) }));
        if (state.feed.last_used_at) { st.appendChild(document.createTextNode(' ' + t('feed_used', { date: when(state.feed.last_used_at) }))); }
        b.appendChild(st);
        acts.appendChild(btn('gb', t('feed_regen'), function () { if (window.confirm(t('feed_regen_ask'))) { makeFeed(); } }));
        acts.appendChild(btn('gb danger', t('feed_revoke'), function () {
            if (!window.confirm(t('feed_revoke_ask'))) { return; }
            api(K.urls.feed_revoke, {}, true).then(function (r) {
                if (!r.ok) { toast(r.message, true); return; }
                state.feed = null; state.url = null;
                toast(t('feed_revoked'));
                render();
            });
        }));
    } else {
        b.appendChild(el('p', 'state none', t('feed_none')));
        acts.appendChild(btn('gbp', t('feed_create'), makeFeed));
    }
    b.appendChild(acts);
    if (state.url) {
        var once = el('div', 'once');
        once.appendChild(el('p', '', t('feed_once')));
        var row = el('div', 'urlrow');
        var inp = el('input');
        inp.type = 'text';
        inp.readOnly = true;
        inp.value = state.url;
        inp.setAttribute('aria-label', t('feed_title'));
        inp.setAttribute('data-focus', '1');
        inp.addEventListener('focus', function () { inp.select(); });
        row.appendChild(inp);
        row.appendChild(btn('gb', t('copy'), function () {
            inp.select();
            var done = function () { toast(t('copied')); };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(inp.value).then(done, function () { try { document.execCommand('copy'); done(); } catch (e) { /* select it by hand */ } });
            } else {
                try { document.execCommand('copy'); done(); } catch (e) { /* select it by hand */ }
            }
        }));
        once.appendChild(row);
        b.appendChild(once);
    }

    // the outside calendars
    b.appendChild(el('h4', '', t('links_title')));
    b.appendChild(el('p', 'help', t('links_help', { max: K.max || 10 })));
    var ul = el('ul', 'lks');
    if (!state.links.length) { ul.appendChild(el('li', 'none', t('links_none'))); }
    state.links.forEach(function (l) {
        var li = el('li');
        li.appendChild(el('span', 'n', l.name));
        var meta = [l.host, l.show_as === 'details' ? t('show_details') : t('show_busy')];
        if (!l.enabled) { meta.push(t('link_off')); } else {
            meta.push(l.last_sync_at ? t('last_sync', { date: when(l.last_sync_at) }) : t('never'));
            if (l.last_sync_at) { meta.push(t('events_n', { n: l.events })); }
        }
        li.appendChild(el('span', 'm', meta.filter(Boolean).join(' · ')));
        if (l.last_error) { li.appendChild(el('span', 'm err', errText(l.last_error))); }
        var a = el('div', 'acts');
        a.appendChild(btn('gb', t('link_edit'), function () { form(l); }));
        if (l.enabled) {
            var sb = btn('gb', t('link_sync'), function () {
                sb.disabled = true;
                api(K.urls.link + '/' + l.id + '/sync', {}, true).then(function (r) {
                    sb.disabled = false;
                    if (!r.ok) { toast(r.message, true); return; }
                    state.links = r.links || state.links;
                    var now = state.links.filter(function (x) { return x.id === l.id; })[0];
                    if (now && now.last_error) { toast(errText(now.last_error), true); } else { toast(t('synced')); }
                    render();
                    refreshCalendar();
                });
            });
            a.appendChild(sb);
        }
        a.appendChild(btn('gb danger', t('link_delete'), function () {
            if (!window.confirm(t('link_delete_ask'))) { return; }
            api(K.urls.link + '/' + l.id + '/delete', {}, true).then(function (r) {
                if (!r.ok) { toast(r.message, true); return; }
                state.links = r.links || [];
                toast(t('deleted'));
                render();
                refreshCalendar();
            });
        }));
        li.appendChild(a);
        ul.appendChild(li);
    });
    b.appendChild(ul);
    if (state.links.length < (K.max || 10)) {
        var add = el('div', 'acts');
        add.appendChild(btn('gb', t('link_add'), function () { form(null); }));
        b.appendChild(add);
    }
    var foot = el('div', 'gwcal-dfoot');
    foot.appendChild(el('span', 'sp'));
    foot.appendChild(btn('gb', t('close'), close));
    show([head(t('title')), b, foot]);
}

function makeFeed() {
    api(K.urls.feed, {}, true).then(function (r) {
        if (!r.ok) { toast(r.message, true); return; }
        state.feed = r.feed || null;
        state.url = r.url || null;
        render();
    });
}

function form(l) {
    var b = el('div', 'gwcal-dbody gwcal-lk');
    var err = el('div', 'gwcal-ferr');
    err.hidden = true;
    err.setAttribute('role', 'alert');
    b.appendChild(err);
    var name = el('input');
    name.type = 'text';
    name.maxLength = 100;
    name.value = l ? l.name : '';
    name.setAttribute('data-focus', '1');
    b.appendChild(field(t('link_name'), name));
    var url = el('input');
    url.type = 'url';
    url.inputMode = 'url';
    url.autocomplete = 'off';
    url.spellcheck = false;
    url.maxLength = 2000;
    // the address is never given back: empty keeps it
    url.placeholder = l ? t('link_url_keep') : 'https://';
    b.appendChild(field(t('link_url'), url));
    var sel = el('select');
    [['busy', t('show_busy')], ['details', t('show_details')]].forEach(function (o) {
        var op = el('option', '', o[1]);
        op.value = o[0];
        sel.appendChild(op);
    });
    sel.value = l ? l.show_as : 'busy';
    b.appendChild(field(t('link_show_as'), sel));
    var chk = el('label', 'gwcal-chk');
    var on = el('input');
    on.type = 'checkbox';
    on.checked = l ? !!l.enabled : true;
    chk.appendChild(on);
    chk.appendChild(document.createTextNode(t('link_enabled')));
    b.appendChild(chk);
    var foot = el('div', 'gwcal-dfoot');
    var sv = btn('gbp', t('save'), function () {
        sv.disabled = true;
        err.hidden = true;
        api(l ? K.urls.link + '/' + l.id : K.urls.link, { name: name.value, url: url.value, show_as: sel.value, enabled: on.checked ? '1' : '0' }, true).then(function (r) {
            sv.disabled = false;
            if (!r.ok) { err.textContent = r.message; err.hidden = false; return; }
            state.links = r.links || [];
            toast(t('saved'));
            render();
            refreshCalendar();
        });
    });
    foot.appendChild(sv);
    foot.appendChild(btn('gb', t('cancel'), render));
    show([head(l ? t('link_edit') : t('link_add')), b, foot]);
}

// the calendar shows the events again (its "today" keeps the day: the view is reloaded the way a click on the current view does)
function refreshCalendar() {
    var cur = bar.querySelector('.gwcal-views .gb[aria-pressed="true"]');
    if (cur) { setTimeout(function () { cur.click(); }, 50); }
}

var ob = btn('gb gwcal-lkbtn', null, open);
var ic = el('i', 'fa fa-link');
ic.setAttribute('aria-hidden', 'true');
ob.appendChild(ic);
ob.appendChild(document.createTextNode(' ' + t('open')));
var nw = bar.querySelector('.gwcal-new');
var old = bar.querySelector('.gwcal-lkbtn');
if (old) { old.parentNode.removeChild(old); }
bar.insertBefore(ob, nw || null);
})();
</script>
@endverbatim
