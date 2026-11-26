    /* ------------------------------------------------ record links ------ */
    // The records of other Exment tables an issue is linked to (ISS02939): a
    // customer, a contract, an asset... A box beside the related issues, shown
    // while the project links to some table (D.record_links.tables). Not in the
    // payload: read when the detail opens, one issue at a time. Exment's own
    // permissions decide what shows: a record the reader may not read is a row
    // without its title or its link. Every value goes in as text.
    var RL = {};          // issue id -> its links, as the server sent them
    var RL_LOADING = {};  // issue id -> a read on its way
    var RL_FAILED = {};   // issue id -> the last read failed (said in the box)
    var RL_OPEN = '';     // the issue whose add form is open
    var RL_LAST = null;   // { i, res }: what the box was last drawn for
    var rlBusy = false;   // an add or a remove on its way
    var rlSeq = 0;        // the newest search; older answers are dropped

    function rlLang() { return (L && L.links) || {}; }
    function rlTables() {
        var t = D.record_links && D.record_links.tables;
        return Array.isArray(t) ? t.filter(function (x) { return x && x.name; }) : [];
    }
    function rlOn() { return rlTables().length > 0 && !!(D.urls && D.urls.base); }
    function rlUrl(issueId) { return D.urls.base + '/issue/' + issueId + '/links'; }
    // An address for href: http(s) only, made absolute against the page. A
    // javascript:, data: or any other scheme is no link at all.
    function rlSafeHref(u, base) {
        if (typeof u !== 'string' || u.trim() === '') { return ''; }
        var parsed;
        try { parsed = new URL(u.trim(), base || location.href); } catch (e) { return ''; }
        return parsed.protocol === 'http:' || parsed.protocol === 'https:' ? parsed.href : '';
    }
    // The rows the box draws from the server's links: the table's label, the
    // record's title (or "hidden" when the reader may not read it) and the
    // address when there is a safe one.
    function rlRows(links, hiddenText, base) {
        return (Array.isArray(links) ? links : []).map(function (l) {
            var can = !!(l && l.can);
            var title = can && l.t !== null && l.t !== undefined && String(l.t) !== '' ? String(l.t) : '';
            return {
                id: String(l.id),
                label: String(l.tl || l.table || ''),
                text: can ? (title || '#' + String(l.rid)) : String(hiddenText || ''),
                href: can ? rlSafeHref(l.u, base) : '',
                hidden: !can
            };
        });
    }
    // What a search sends: a table the project links to, and the words, cut
    // the way the server cuts them. null: nothing to ask.
    function rlSearchParams(table, q, tables) {
        var name = String(table || '');
        if (!name || !(tables || []).some(function (t) { return t.name === name; })) { return null; }
        return { table: name, q: String(q == null ? '' : q).trim().slice(0, 100) };
    }
    // The found records as rows to click, 20 at most.
    function rlFound(found) {
        return (Array.isArray(found) ? found : []).slice(0, 20).map(function (f) {
            return { id: String(f.id), text: String(f.t == null || f.t === '' ? '#' + f.id : f.t) };
        });
    }
    // fn once the typing has stopped for ms; cancel() forgets a call still waiting.
    function rlDebounce(fn, ms) {
        var timer = null;
        var call = function () {
            var args = arguments;
            if (timer !== null) { clearTimeout(timer); }
            timer = setTimeout(function () { timer = null; fn.apply(null, args); }, ms);
        };
        call.cancel = function () {
            if (timer !== null) { clearTimeout(timer); timer = null; }
        };
        return call;
    }
    // A detail opened afresh reads the links again: someone may have linked a
    // record since.
    function rlForget(issueId) {
        delete RL[String(issueId)];
        delete RL_FAILED[String(issueId)];
        if (RL_OPEN !== String(issueId)) { RL_OPEN = ''; }
    }
    function rlRedraw(issueId) {
        if (!alive() || String(currentIssue) !== String(issueId) || !RL_LAST) { return; }
        if (String(RL_LAST.i.id) !== String(issueId)) { return; }
        rlRender(RL_LAST.i, RL_LAST.res);
    }
    function rlLoad(issueId) {
        var id = String(issueId);
        if (RL_LOADING[id]) { return; }
        RL_LOADING[id] = true;
        ppQueue(function () {
            return $.getJSON(rlUrl(id));
        }).then(function (j) {
            RL_LOADING[id] = false;
            if (!alive()) { return; }
            if (!j || !j.ok) { RL_FAILED[id] = errText(null, j, ''); rlRedraw(id); return; }
            RL[id] = Array.isArray(j.links) ? j.links : [];
            delete RL_FAILED[id];
            rlRedraw(id);
        }, function (xhr) {
            RL_LOADING[id] = false;
            if (!alive()) { return; }
            RL_FAILED[id] = xhrErr(xhr, '');
            rlRedraw(id);
        });
    }
    function rlEl(tag, cls, text) {
        var e = document.createElement(tag);
        if (cls) { e.className = cls; }
        if (text !== undefined && text !== null) { e.textContent = String(text); }
        return e;
    }
    function rlRow(key, value) {
        var row = rlEl('div', 'row');
        row.appendChild(rlEl('div', 'k', key));
        var v = rlEl('div', 'v');
        if (value) { v.appendChild(value); }
        row.appendChild(v);
        return { row: row, v: v };
    }
    function rlPost(issueId, body, btn) {
        var id = String(issueId);
        if (rlBusy) { return; }
        rlBusy = true;
        if (btn) { btn.disabled = true; }
        $.ajax({
            url: rlUrl(id),
            type: 'POST',
            contentType: 'application/json',
            dataType: 'json',
            data: JSON.stringify(body),
            headers: { 'X-CSRF-TOKEN': CSRF }
        }).done(function (j) {
            if (!alive()) { return; }
            if (!j || !j.ok) { toast(errText(null, j, ''), true); return; }
            RL[id] = Array.isArray(j.links) ? j.links : [];
            rlBusy = false;
            rlRedraw(id);
            toast(L.m_saved);
        }).fail(function (xhr) {
            if (alive()) { toast(xhrErr(xhr, ''), true); }
        }).always(function () {
            rlBusy = false;
            if (btn && btn.isConnected) { btn.disabled = false; }
        });
    }
    // The add form: a table, then words to look for; a found record is
    // linked by a click.
    function rlAddForm(issueId, T) {
        var tables = rlTables();
        var wrap = rlEl('div', 'bl-reladd');
        wrap.style.flexWrap = 'wrap';
        var sel = rlEl('select');
        sel.setAttribute('aria-label', String(T.pick_table || ''));
        sel.title = String(T.pick_table || '');
        tables.forEach(function (t) {
            var o = rlEl('option', null, t.label || t.name);
            o.value = String(t.name);
            sel.appendChild(o);
        });
        var input = rlEl('input');
        input.type = 'search';
        input.autocomplete = 'off';
        input.placeholder = String(T.search_ph || '');
        input.style.flex = '1';
        input.style.minWidth = '0';
        var out = rlEl('div', 'pp-rl-found');
        out.style.cssText = 'flex:1 1 100%;display:flex;flex-direction:column;gap:2px;max-height:240px;overflow:auto;';
        var run = function () {
            var p = rlSearchParams(sel.value, input.value, tables);
            if (!p || !D.urls.link_search) { out.textContent = ''; return; }
            var seq = ++rlSeq;
            ppQueue(function () {
                return $.getJSON(D.urls.link_search, p);
            }).then(function (j) {
                if (!alive() || seq !== rlSeq || !out.isConnected) { return; }
                var rows = rlFound(j && j.found);
                out.textContent = '';
                if (!rows.length) {
                    out.appendChild(rlEl('span', null, T.no_match || ''));
                    return;
                }
                rows.forEach(function (r) {
                    var a = rlEl('a', 'pp-rl-pick', r.text);
                    a.href = 'javascript:void(0);';
                    a.setAttribute('data-record', r.id);
                    a.addEventListener('click', function () {
                        rlPost(issueId, { action: 'add', table: p.table, record: r.id }, null);
                    });
                    out.appendChild(a);
                });
            }, function (xhr) {
                if (!alive() || seq !== rlSeq || !out.isConnected) { return; }
                out.textContent = '';
                out.appendChild(rlEl('span', null, xhrErr(xhr, '')));
            });
        };
        var later = rlDebounce(run, 300);
        input.addEventListener('input', later);
        sel.addEventListener('change', function () { later.cancel(); run(); });
        wrap.appendChild(sel);
        wrap.appendChild(input);
        wrap.appendChild(out);
        // what there is to pick, before anything is typed: the newest records
        setTimeout(function () { if (out.isConnected) { run(); input.focus(); } }, 0);
        return wrap;
    }
    function rlRender(i, res) {
        var box = document.getElementById('pp-d-reclinkbox');
        var body = document.getElementById('pp-d-reclinks');
        if (!box || !body) { return; }
        if (!rlOn() || !i) { box.style.display = 'none'; return; }
        RL_LAST = { i: i, res: res };
        box.style.display = '';
        var T = rlLang();
        var id = String(i.id);
        var sum = document.getElementById('pp-d-reclinksum');
        body.textContent = '';
        var links = own(RL, id);
        if (links === undefined) {
            if (sum) { sum.textContent = ''; }
            if (own(RL_FAILED, id) !== undefined) {
                body.appendChild(rlRow('', rlEl('span', null, RL_FAILED[id] || '')).row);
                return;
            }
            body.appendChild(rlRow('', rlEl('i', 'fa fa-spinner fa-spin')).row);
            rlLoad(id);
            return;
        }
        var canEdit = !!(res && res.editable);
        var rows = rlRows(links, T.hidden, location.href);
        if (sum) { sum.textContent = rows.length ? String(rows.length) : ''; }
        if (!rows.length) {
            body.appendChild(rlRow('', rlEl('span', null, T.empty || '')).row);
        }
        rows.forEach(function (r) {
            var tx = rlEl('span', 'tx');
            if (r.href) {
                var a = rlEl('a', null, r.text);
                a.href = r.href;
                a.target = '_blank';
                a.rel = 'noopener';
                a.title = String(T.open || '');
                tx.appendChild(a);
            } else {
                var s = rlEl('span', null, r.text);
                if (r.hidden) { s.style.color = 'var(--bl-faint)'; }
                tx.appendChild(s);
            }
            var line = rlRow(r.label, tx);
            if (canEdit) {
                var del = rlEl('a', 'pp-rel-del');
                del.href = 'javascript:void(0);';
                del.title = String(T.remove || '');
                del.setAttribute('aria-label', String(T.remove || ''));
                del.appendChild(rlEl('i', 'fa fa-times'));
                del.addEventListener('click', function () {
                    if (window.confirm(String(T.remove_confirm || ''))) {
                        rlPost(id, { action: 'remove', link: r.id }, null);
                    }
                });
                line.v.appendChild(del);
            }
            body.appendChild(line.row);
        });
        if (!canEdit) { return; }
        if (RL_OPEN !== id) {
            var opener = rlEl('a', 'bl-reladd-open');
            opener.href = 'javascript:void(0);';
            opener.appendChild(rlEl('i', 'fa fa-plus'));
            opener.appendChild(document.createTextNode(' ' + String(T.add || '')));
            opener.addEventListener('click', function () {
                RL_OPEN = id;
                rlRender(i, res);
            });
            body.appendChild(rlRow('', opener).row);
            return;
        }
        body.appendChild(rlRow('', rlAddForm(id, T)).row);
    }
