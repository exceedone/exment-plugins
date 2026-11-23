    /* ------------------------------------------------ error wording ---- */
    // What a person reads when a request fails. The portal's own refusals
    // carry the sentence to show in `message`, beside a machine code in
    // `error` that is only ever branched on: the codes are English, and so is
    // whatever a proxy, the framework's own error page or the browser's
    // exception has to say. So, in this order: the portal's sentence, one
    // picked by the HTTP status (0 = no answer at all), the caller's own
    // fallback, the catch-all.
    function errText(status, body, fallback) {
        if (body && typeof body === 'object' && body.ok === false
            && typeof body.message === 'string' && body.message.trim() !== '') {
            return body.message;
        }
        var http = (L && L.http) || {};
        var said = http.messages || {};
        var known = typeof status === 'number' && isFinite(status);
        var key = known ? own(http.status, String(status)) : undefined;
        if (!key && known && status >= 500) { key = 'server'; }
        var text = key ? own(said, key) : '';
        if (typeof text === 'string' && text !== '') { return text; }

        return fallback || own(said, 'other') || '';
    }
    // The body of a failed jQuery request, when it was JSON. Read only to
    // find the portal's own sentence in it: the raw text is never shown.
    function xhrBody(xhr) {
        if (!xhr) { return null; }
        if (xhr.responseJSON && typeof xhr.responseJSON === 'object') { return xhr.responseJSON; }
        try {
            var parsed = JSON.parse(String(xhr.responseText || ''));
            return (parsed && typeof parsed === 'object') ? parsed : null;
        } catch (e) { return null; }
    }
    function xhrErr(xhr, fallback) {
        return errText(xhr && typeof xhr.status === 'number' ? xhr.status : null, xhrBody(xhr), fallback);
    }
    // A fetch answer as one pair, whatever came back: the status, and the
    // body when it was JSON - null for a proxy's html page. ppNoAnswer is the
    // same pair for a request that got no answer at all (status 0), so a
    // failure is worded from the pair alone and never from the browser's
    // own English exception text.
    function ppJson(r) {
        return r.json().then(function (body) {
            return { status: r.status, body: body };
        }, function () {
            return { status: r.status, body: null };
        });
    }
    function ppNoAnswer() { return { status: 0, body: null }; }
    function fmt(tpl) {
        var args = Array.prototype.slice.call(arguments, 1), i = 0;
        return String(tpl).replace(/%s/g, function () { return args[i++]; });
    }
    // An SVG tooltip, built without ever writing the tag name as one literal.
    // Exment's pjax layer picks the page title out of the response by string
    // search, and older builds close it at the LAST </title> in the body, so a
    // second one anywhere - even inside this script - makes the pjax fragment
    // swallow the whole admin layout and render it twice. Assembling the tag
    // at runtime keeps the native tooltip and leaves that scan alone, which is
    // what lets the portal work on an install that has not been patched.
    var SVG_TIP = 'title';
    function svgTip(text) {
        return '<' + SVG_TIP + '>' + esc(text) + '</' + SVG_TIP + '>';
    }
    function user(id) { return own(D.users, id) || { n: '?', i: '?', c: '#95a5a6' }; }
    function reEsc(s) { return String(s).replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); }
    // Who can be written with an @. The autocomplete inserts the display name
    // as it stands - "@鈴木 課長" - so a character class can never match one:
    // the names are matched literally instead, longest first so "@鈴木 課長"
    // wins over a colleague who is just "@鈴木". Rebuilt only when the set of
    // people changes, since this runs once per comment on a long timeline.
    var MENTIONS = { key: null, re: null, by: {} };
    function mentions() {
        var ids = Object.keys(D.users || {});
        var key = ids.join(',');
        if (MENTIONS.key === key) { return MENTIONS; }
        var by = {}, names = [];
        ids.forEach(function (id) {
            var name = String(user(id).n || '').trim();
            if (!name || name === '?') { return; }
            // The text being scanned has already been escaped, so the name
            // has to be matched in the shape it now has on the page.
            var shown = esc(name);
            if (by[shown] === undefined) { by[shown] = id; names.push(shown); }
        });
        names.sort(function (a, b) { return b.length - a.length; });
        MENTIONS = {
            key: key,
            by: by,
            // The leading guard keeps the @ in an address out of it: the
            // "@example" in "someone@example.com" is not a mention.
            re: names.length ? new RegExp('(^|[^\\w@.])@(' + names.map(reEsc).join('|') + ')', 'g') : null
        };

        return MENTIONS;
    }
    // ISS00254: the avatar the user registered in Exment, laid over the
    // initial; if the image cannot load it removes itself and the initial shows.
    function avImg(u) {
        return u && u.a ? '<img class="bl-avimg" src="' + esc(u.a) + '" alt="" loading="lazy" onerror="this.remove()">' : '';
    }
    function av(id, lg) {
        var u = user(id);
        return '<span class="bl-av' + (lg ? ' lg' : '') + '" style="background:' + ppColor(u.c) + '" title="' + esc(u.n) + '">' + esc(u.i) + avImg(u) + '</span>';
    }
    function avname(id) { return '<span class="bl-avname">' + av(id) + esc(user(id).n) + '</span>'; }
    function avlist(ids) { return (ids || []).map(function (i) { return av(i); }).join(''); }
    function pill(st) {
        var s = own(ST, st);
        return '<span class="bl-pill" style="background:' + ppColor(s ? s.c : '#95a5a6') + '">' + esc(s ? s.l : st) + '</span>';
    }
    function tag(ty) {
        var t = own(D.types, ty);
        return t ? '<span class="bl-tag" style="background:' + ppColor(t.c) + '">' + esc(t.l) + '</span>' : '';
    }
    function prio(p, iconOnly) {
        var o = own(D.priorities, p);
        if (!o) { return ''; }
        return '<span class="bl-prio" style="color:' + ppColor(o.c) + '" title="' + esc(o.l) + '">' +
            '<svg width="14" height="14" viewBox="0 0 14 14"><path fill="currentColor" d="' + (own(ARROWS, o.arrow) || ARROWS.right) + '"/></svg>' +
            (iconOnly ? '' : esc(o.l)) + '</span>';
    }
    function isDone(st) { var s = own(ST, st); return !!(s && s.done); }
    function d2(s) { return s ? String(s).replace(/^\d\d\d\d-/, '').replace('-', '/') : '—'; }
    // An hour count: the server sends a number or null, so 0 is a real
    // answer and shows as 0, and only a missing value reads as a dash.
    function hours(v) { return (v == null || v === '' ? '—' : String(v)) + ' h'; }
    function daysTo(s) {
        if (!s) { return null; }
        return Math.round((new Date(s + 'T00:00:00') - new Date(D.today + 'T00:00:00')) / 86400000);
    }
    // Schema 25: where an imported issue came from - its old key (a link
    // when the server vouched for the URL), and who raised and last changed
    // it there. '' for an issue made here, so the row stays away.
    function sourceHtml(src) {
        if (!src) { return ''; }
        var out = [];
        if (src.key || src.url) {
            var label = esc(src.key || src.url);
            out.push(src.url ? '<a href="' + esc(src.url) + '" target="_blank" rel="noopener noreferrer">' + label + ' <i class="fa fa-external-link"></i></a>' : label);
        }
        if (src.cby || src.cd) { out.push(esc(L.src_created) + ' ' + esc([src.cby, src.cd].filter(Boolean).join(' '))); }
        if (src.uby || src.ud) { out.push(esc(L.src_updated) + ' ' + esc([src.uby, src.ud].filter(Boolean).join(' '))); }
        if (src.asg) { out.push(esc(L.src_assignees) + ' ' + esc(src.asg)); }
        return out.join('<br>');
    }
    // The due date with its time (schema 22), e.g. 11/20 15:00.
    function dueText(iss) { return d2(iss.dd) + (iss.dt ? ' ' + iss.dt : ''); }
    // The clock now, HH:MM, on this computer.
    function nowHM() {
        var n = new Date();
        return ('0' + n.getHours()).slice(-2) + ':' + ('0' + n.getMinutes()).slice(-2);
    }
    // Past due: a day before today, or today with a time that has gone by.
    // An issue without a time is due at the end of its day, as before.
    function dueOver(iss) {
        if (!iss || !iss.dd) { return false; }
        var days = daysTo(iss.dd);
        return days < 0 || (days === 0 && !!iss.dt && nowHM() > iss.dt);
    }
    function dueCell(iss) {
        if (!iss.dd) { return '<span class="bl-due">—</span>'; }
        var days = daysTo(iss.dd);
        if (!isDone(iss.st) && dueOver(iss)) {
            return '<span class="bl-due over"><span class="bl-due-part"><i class="fa fa-exclamation-circle"></i> ' + esc(dueText(iss)) + '</span> ' +
                '<span class="bl-due-part">(' + esc(fmt(L.overdue_d, Math.max(-days, 0))) + ')</span></span>';
        }
        if (!isDone(iss.st) && days <= 3) {
            return '<span class="bl-due soon"><span class="bl-due-part"><i class="fa fa-clock-o"></i> ' + esc(dueText(iss)) + '</span> ' +
                '<span class="bl-due-part">(' + esc(fmt(L.left_d, days)) + ')</span></span>';
        }
        return '<span class="bl-due">' + esc(dueText(iss)) + '</span>';
    }
    function pbar(pg) {
        if (pg === null || pg === undefined || pg === '') { return '—'; }
        // A number, not whatever arrived: it is written into a style and
        // into the cell, and only a number belongs in either.
        var n = Number(pg);
        if (!isFinite(n)) { return '—'; }
        return '<span class="bl-pbar" style="display:inline-block;width:70px;vertical-align:middle;"><i style="width:' + Math.max(0, Math.min(100, n)) + '%"></i></span>' +
            ' <small style="color:var(--bl-sub);">' + n + '%</small>';
    }
    function starBtn(type, targetType, targetId, count, on, extraCls) {
        return '<span class="bl-star pp-star' + (on ? ' on' : '') + (extraCls ? ' ' + extraCls : '') +
            '" title="' + esc(on ? L.star_off_tip : L.star_on_tip) + '"' +
            ' data-star-type="' + esc(type) + '" data-target-type="' + esc(targetType) + '" data-target-id="' + esc(targetId) + '">' +
            '<i class="fa ' + (on ? 'fa-star' : 'fa-star-o') + '"></i><span class="ct">' + (count ? ' ' + esc(count) : '') + '</span></span>';
    }
