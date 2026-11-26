    /* ------------------------------------------------ custom attributes -- */
    // The items a project adds to its issues (ISS02939), as Backlog's
    // 「カスタム属性」. The definitions are D.project.attributes, each
    // {id, l: label, t: type, o: choices, r: required, u: unit, d: decimals,
    // mx: max length}; an issue row carries its values in `at`, keyed by the
    // definition's id, and the issue column (pm_attributes) holds the same
    // object as one JSON string. An empty value is a missing key.
    //
    // The pure helpers come first (test-pm-attributes.js runs them), then
    // the form block, the detail rows, the list's columns and filters, and
    // the bulk edit.
    var AT_TEXT_AREA = 200; // a text attribute longer than this is typed in a textarea

    function atDefs() {
        var list = D.project && D.project.attributes;
        return Array.isArray(list) ? list : [];
    }
    function atDef(id) {
        var defs = atDefs();
        for (var n = 0; n < defs.length; n++) {
            if (String(defs[n].id) === String(id)) { return defs[n]; }
        }
        return null;
    }
    function atEmpty(v) {
        return v === null || v === undefined || v === '' || (Array.isArray(v) && !v.length);
    }
    // The values of an issue: the column's JSON string, or an object already
    // decoded (a list row's `at`). Ids only, empty values left out.
    function atDecode(raw) {
        var v = raw;
        if (typeof raw === 'string') {
            try { v = raw === '' ? null : JSON.parse(raw); } catch (e) { v = null; }
        }
        var out = {};
        if (!v || typeof v !== 'object' || Array.isArray(v)) { return out; }
        Object.keys(v).forEach(function (k) {
            if (/^[1-9][0-9]{0,9}$/.test(k) && !atEmpty(v[k])) { out[k] = v[k]; }
        });
        return out;
    }
    // One value as typed into its widget, as it is kept: null when empty.
    function atNormalize(def, raw) {
        if (atEmpty(raw)) { return null; }
        switch (def.t) {
            case 'number':
                var s = String(raw).trim();
                if (s === '') { return null; }
                var n = Number(s);
                // not a number: sent as typed, so the server says what is wrong
                return isFinite(n) ? n : s;
            case 'multi_select':
                var list = (Array.isArray(raw) ? raw : [raw]).map(String).filter(function (x) { return x !== ''; });
                var order = (def.o || []).map(String);
                list.sort(function (a, b) { return order.indexOf(a) - order.indexOf(b); });
                return list.length ? list : null;
            case 'user':
                var ids = (Array.isArray(raw) ? raw : [raw]).map(function (x) { return parseInt(x, 10); })
                    .filter(function (x, i, a) { return x > 0 && a.indexOf(x) === i; });
                return ids.length ? ids : null;
            case 'text':
                var t = String(raw).replace(/\r\n?/g, '\n').trim();
                return t === '' ? null : t;
            default:
                return String(raw);
        }
    }
    // The column's new value: what the issue had (attributes not on the form,
    // deleted ones, stay as they were) with the form's values over it. Keys
    // are the ids as strings, in their numeric order; '' when nothing is set.
    function atJson(defs, base, values) {
        var out = {};
        Object.keys(base || {}).forEach(function (k) { if (!atEmpty(base[k])) { out[k] = base[k]; } });
        (defs || []).forEach(function (d) {
            var k = String(d.id);
            if (!Object.prototype.hasOwnProperty.call(values || {}, k)) { return; }
            var v = atNormalize(d, values[k]);
            if (v === null) { delete out[k]; } else { out[k] = v; }
        });
        var keys = Object.keys(out).sort(function (a, b) { return Number(a) - Number(b); });
        if (!keys.length) { return ''; }
        var sorted = {};
        keys.forEach(function (k) { sorted[k] = out[k]; });
        return JSON.stringify(sorted);
    }
    // 1234.5 with 2 decimals -> "1,234.50", as the server's number_format.
    function atNumber(v, decimals) {
        var n = Number(v);
        if (!isFinite(n)) { return String(v); }
        var parts = n.toFixed(Math.max(0, Math.min(4, Number(decimals) || 0))).split('.');
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        return parts.join('.');
    }
    // A value as a person reads it. nameOf(id) names a user.
    function atText(def, value, nameOf) {
        if (atEmpty(value)) { return ''; }
        switch (def.t) {
            case 'number':
                var s = atNumber(value, def.d);
                return def.u ? s + ' ' + def.u : s;
            case 'multi_select':
                return (Array.isArray(value) ? value : [value]).map(String).join('、');
            case 'user':
                return (Array.isArray(value) ? value : [value]).map(function (id) { return nameOf(id); }).join('、');
            default:
                return typeof value === 'object' ? '' : String(value);
        }
    }
    function atUserName(id) {
        var u = own(D.users, String(id));
        return u ? String(u.n) : '#' + String(id);
    }
    // What a cell is worth when the list is sorted on it: numbers as numbers,
    // dates as their strings, the rest as text (null or '': blank, last).
    function atSortValue(def, value, nameOf) {
        if (atEmpty(value)) { return def.t === 'number' ? null : ''; }
        if (def.t === 'number') { var n = Number(value); return isFinite(n) ? n : null; }
        if (def.t === 'date') { return String(value); }
        if (def.t === 'user') { return nameOf((Array.isArray(value) ? value : [value])[0]); }
        return atText(def, value, nameOf);
    }
    // An entry of SORT_COLS for a definition (t: collated as text).
    function atSortCol(def) {
        return {
            t: def.t !== 'number' && def.t !== 'date',
            v: function (i) { return atSortValue(def, own(i.at, String(def.id)), atUserName); }
        };
    }
    function atSortRegister() {
        atDefs().forEach(function (d) { SORT_COLS['at' + d.id] = atSortCol(d); });
    }

    // ---- filters ----
    // FILTER.at is one string, so it rides with the others (the saved
    // default, the URL, the saved conditions): the JSON of {"<id>": f}, where
    // f is the text to find, the choice (a user id for a user), or [from, to]
    // for a number and a date ('' for an open end).
    function atFilterParse(s) {
        var v = null;
        try { v = s ? JSON.parse(String(s)) : null; } catch (e) { v = null; }
        var out = {};
        if (!v || typeof v !== 'object' || Array.isArray(v)) { return out; }
        Object.keys(v).forEach(function (k) {
            var f = v[k];
            if (!/^[1-9][0-9]{0,9}$/.test(k)) { return; }
            if (Array.isArray(f)) {
                var r = [String(f[0] == null ? '' : f[0]), String(f[1] == null ? '' : f[1])];
                if (r[0] !== '' || r[1] !== '') { out[k] = r; }
            } else if (typeof f === 'string' || typeof f === 'number') {
                if (String(f) !== '') { out[k] = String(f); }
            }
        });
        return out;
    }
    function atFilterString(obj) {
        var clean = atFilterParse(JSON.stringify(obj || {}));
        var keys = Object.keys(clean).sort(function (a, b) { return Number(a) - Number(b); });
        if (!keys.length) { return ''; }
        var sorted = {};
        keys.forEach(function (k) { sorted[k] = clean[k]; });
        return JSON.stringify(sorted);
    }
    // A filter brought over from another project: only the attributes this
    // project has are kept.
    function atFilterKeep(s) {
        var f = atFilterParse(s);
        var out = {};
        atDefs().forEach(function (d) {
            var k = String(d.id);
            if (own(f, k)) { out[k] = f[k]; }
        });
        return atFilterString(out);
    }
    // Whether one value passes its filter.
    function atMatch(def, value, f) {
        if (f === undefined || f === null || f === '') { return true; }
        if (def.t === 'number' || def.t === 'date') {
            var r = Array.isArray(f) ? f : [f, ''];
            if (r[0] === '' && r[1] === '') { return true; }
            if (atEmpty(value)) { return false; }
            if (def.t === 'number') {
                var n = Number(value);
                if (r[0] !== '' && !(n >= Number(r[0]))) { return false; }
                if (r[1] !== '' && !(n <= Number(r[1]))) { return false; }
                return true;
            }
            var s = String(value);
            if (r[0] !== '' && s < String(r[0])) { return false; }
            if (r[1] !== '' && s > String(r[1])) { return false; }
            return true;
        }
        if (atEmpty(value)) { return false; }
        if (def.t === 'text') { return String(value).toLowerCase().indexOf(String(f).toLowerCase()) >= 0; }
        if (def.t === 'multi_select' || def.t === 'user') {
            return (Array.isArray(value) ? value : [value]).map(String).indexOf(String(f)) >= 0;
        }
        return String(value) === String(f);
    }
    // Whether an issue passes every attribute filter (one not defined here
    // any more is ignored).
    function atRowMatch(i, defs, filters) {
        return defs.every(function (d) {
            var k = String(d.id);
            return !own(filters, k) || atMatch(d, own(i.at, k), filters[k]);
        });
    }

    // ---- the bulk edit ----
    // The value one attribute is set to on every ticked issue: undefined for
    // nothing (left as is), '__none__' to empty it.
    function atBulkValue(def, raw, clear) {
        if (clear || raw === '__none__') { return '__none__'; }
        if (atEmpty(raw) || String(raw).trim() === '') { return undefined; }
        if (def.t === 'multi_select' || def.t === 'user') { return [String(raw)]; }
        return String(raw).trim();
    }

    /* ---- the issue form ---- */
    function nedAtColumnName() {
        return String((D.issue_extra_cols || {}).attributes || 'pm_attributes');
    }
    // The project's people a user attribute may name (the server checks the
    // same: the manager, the members and the viewers).
    function atPeople() {
        var p = D.project || {};
        return [p.manager].concat(p.members || [], p.viewers || []).filter(function (v, x, a) {
            return v && a.indexOf(v) === x;
        }).map(function (u) { return [String(u), atUserName(u)]; })
            .sort(function (a, b) { return cmpText(a[1], b[1]); });
    }
    function atOpt(sel, value, label, on) {
        var o = new Option(label, value);
        if (on) { o.selected = true; }
        sel.add(o);
        return o;
    }
    // One widget. Every one carries data-at (the id) and the class
    // pp-ne-atin, never pp-ne-in: the form reads the column off the one
    // hidden carrier of the block, which is the only .pp-ne-in in it.
    function atWidget(def, value, readonly) {
        var k = String(def.id);
        var w;
        if (def.t === 'select' || def.t === 'user') {
            w = document.createElement('select');
            var pick = def.t === 'user' ? atPeople() : (def.o || []).map(function (o) { return [String(o), String(o)]; });
            var cur = def.t === 'user' ? (Array.isArray(value) ? value : (atEmpty(value) ? [] : [value])).map(String) : [String(value == null ? '' : value)];
            if (def.t === 'user') {
                w.multiple = true;
                w.size = Math.min(6, Math.max(3, pick.length));
                // a person no longer on the project still shows, by name
                cur.forEach(function (id) {
                    if (!pick.some(function (p) { return p[0] === id; })) { pick.push([id, atUserName(id)]); }
                });
            } else {
                atOpt(w, '', nedGetLangKey('ned_none'), false);
                if (cur[0] !== '' && pick.every(function (p) { return p[0] !== cur[0]; })) { pick.push([cur[0], cur[0]]); }
            }
            pick.forEach(function (p) { atOpt(w, p[0], p[1], cur.indexOf(p[0]) >= 0); });
            w.disabled = readonly;
        } else if (def.t === 'multi_select') {
            w = document.createElement('div');
            w.style.display = 'flex';
            w.style.flexWrap = 'wrap';
            w.style.gap = '4px 14px';
            var on = (Array.isArray(value) ? value : []).map(String);
            (def.o || []).forEach(function (o) {
                var lab = document.createElement('label');
                lab.style.cssText = 'display:inline-flex;align-items:center;gap:5px;margin:0;font-weight:400;cursor:pointer;';
                var box = document.createElement('input');
                box.type = 'checkbox';
                box.value = String(o);
                box.checked = on.indexOf(String(o)) >= 0;
                box.disabled = readonly;
                lab.appendChild(box);
                lab.appendChild(document.createTextNode(String(o)));
                w.appendChild(lab);
            });
        } else if (def.t === 'text' && Number(def.mx) > AT_TEXT_AREA) {
            w = document.createElement('textarea');
            w.rows = 3;
            w.style.minHeight = '64px';
            w.maxLength = Number(def.mx) || 1000;
            w.value = value == null ? '' : String(value);
            w.readOnly = readonly;
        } else {
            w = document.createElement('input');
            w.type = def.t === 'number' ? 'number' : 'text';
            if (def.t === 'number') { w.step = def.d > 0 ? String(Math.pow(10, -def.d)) : '1'; }
            if (def.t === 'text') { w.maxLength = Number(def.mx) || 1000; }
            w.value = value == null ? '' : String(value);
            w.readOnly = readonly;
            if (def.t === 'date' && window.ppDateField) { window.ppDateField(w, 'date'); }
        }
        w.classList.add('pp-ne-atin');
        w.setAttribute('data-at', k);
        return w;
    }
    // A widget's value as typed.
    function atWidgetValue(w) {
        if (w.tagName === 'SELECT' && w.multiple) {
            return Array.prototype.filter.call(w.options, function (o) { return o.selected && o.value; }).map(function (o) { return o.value; });
        }
        if (w.tagName === 'DIV') {
            return Array.prototype.filter.call(w.querySelectorAll('input[type=checkbox]'), function (b) { return b.checked; })
                .map(function (b) { return b.value; });
        }
        return w.value;
    }
    function atFormRow(def, w) {
        var row = document.createElement('div');
        row.className = 'pp-ne-fg';
        var lbl = document.createElement('label');
        lbl.textContent = def.l;
        if (def.r) {
            var req = document.createElement('span');
            req.className = 'req';
            req.textContent = '*';
            lbl.appendChild(req);
        }
        row.appendChild(lbl);
        var cell = w;
        if (def.t === 'number' && def.u) {
            cell = document.createElement('div');
            cell.style.cssText = 'display:flex;align-items:center;gap:6px;';
            cell.appendChild(w);
            var unit = document.createElement('span');
            unit.textContent = def.u;
            unit.style.whiteSpace = 'nowrap';
            cell.appendChild(unit);
        }
        cell.style.gridColumn = '2';
        cell.style.minWidth = '0';
        row.appendChild(cell);
        return row;
    }
    // The block of the custom attributes in the issue form, after the other
    // fields. It stands for the column: data-fname names it (a 422 on it
    // shows here), and a hidden .pp-ne-in carries the column's JSON, written
    // on every change, so the serializer, the diff, the drafts and the save
    // see one ordinary field.
    function nedAtBlock(col) {
        var defs = atDefs();
        var raw = NED.values[col.name];
        var known = NED.mode !== 'create' && NED._payload && NED._payload.row ? issueById(NED._payload.row.id) : null;
        var base = atDecode(raw === undefined && known ? known.at : raw);
        var readonly = col.readonly || !NED.canEdit;
        var wrap = document.createElement('div');
        wrap.className = 'pp-ne-fg pp-ne-at pp-ne-rowstart';
        wrap.dataset.fname = col.name;
        wrap.dataset.ftype = 'attributes';
        wrap.style.cssText = 'display:block;grid-column:1 / -1;margin-top:14px;';
        var head = document.createElement('div');
        head.className = 'pp-ne-at-h';
        head.style.cssText = 'font-weight:700;font-size:12.5px;padding:4px 0 6px;border-bottom:1px solid var(--bl-line);';
        head.textContent = (L.attributes || {}).section || '';
        wrap.appendChild(head);
        var carrier = document.createElement('input');
        carrier.type = 'hidden';
        carrier.className = 'pp-ne-in';
        wrap.appendChild(carrier);
        var grid = document.createElement('div');
        grid.style.cssText = 'display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,440px),1fr));column-gap:32px;';
        var widgets = defs.map(function (d) {
            var w = atWidget(d, own(base, String(d.id)), readonly);
            grid.appendChild(atFormRow(d, w));
            return w;
        });
        wrap.appendChild(grid);
        var err = document.createElement('div');
        err.className = 'err';
        err.hidden = true;
        wrap.appendChild(err);
        var sync = function () {
            var values = {};
            widgets.forEach(function (w) { values[w.getAttribute('data-at')] = atWidgetValue(w); });
            carrier.value = atJson(defs, base, values);
        };
        sync();
        var changed = function () {
            sync();
            if (wrap.classList.contains('err')) { wrap.classList.remove('err'); err.hidden = true; err.textContent = ''; }
        };
        wrap.addEventListener('input', changed);
        wrap.addEventListener('change', changed);
        return wrap;
    }
    // Mounted by nedRenderForm on the issue form, while the project has
    // attributes and the column is one the form may write.
    function nedAtMount(slots) {
        if (NED.table !== D.issue_table || !atDefs().length) { return; }
        var name = nedAtColumnName();
        var col = (NED.columns || []).filter(function (c) { return c.name === name; })[0];
        if (!col) { return; }
        slots.grid.appendChild(nedAtBlock(col));
    }

    /* ---- the issue detail ---- */
    // A row of the info box for each attribute the issue has a value of.
    function atProps(i) {
        return atDefs().map(function (d) {
            var t = atText(d, own(i.at, String(d.id)), atUserName);
            return ['at' + d.id, d.l, t === '' ? '' : esc(t)];
        });
    }

    /* ---- the list ---- */
    // The optional columns of the attributes, after the others.
    function atOcols() {
        return atDefs().map(function (d) { return { k: 'at' + d.id, at: d }; });
    }
    function atCell(i, d) {
        var v = own(i.at, String(d.id));
        if (d.t === 'user') { return '<td>' + avlist(Array.isArray(v) ? v : []) + '</td>'; }
        var nowrap = d.t === 'number' || d.t === 'date' ? ' style="white-space:nowrap;"' : '';
        return '<td' + nowrap + '>' + esc(atText(d, v, atUserName)) + '</td>';
    }
    // Their headers, made once each, before the status's (the headers of the
    // list are written in the view; these are the project's own).
    function atEnsureHead(head) {
        var before = head.querySelector('th[data-sort="st"]');
        atDefs().forEach(function (d) {
            var k = 'at' + d.id;
            if (head.querySelector('th[data-ocol="' + k + '"]')) { return; }
            var th = document.createElement('th');
            th.setAttribute('data-sort', k);
            th.setAttribute('data-ocol', k);
            th.hidden = true;
            th.textContent = d.l;
            head.insertBefore(th, before);
        });
    }
    // The filter row of the attributes, under the others; drawn again only
    // when the definitions change.
    function atFilterControl(d, T) {
        var k = String(d.id);
        var span = document.createElement('span');
        span.className = 'pp-f-at1';
        span.style.cssText = 'display:inline-flex;align-items:center;gap:4px;white-space:nowrap;';
        var input = function (type, part, ph) {
            var w = document.createElement('input');
            w.type = type;
            w.setAttribute('data-at', k);
            w.setAttribute('data-part', part);
            w.placeholder = ph;
            w.title = ph;
            w.setAttribute('aria-label', ph);
            if (type === 'number') { w.style.width = '90px'; }
            // the portal's own calendar, as every other date of the screen
            if (type === 'date' && window.ppDateField) { window.ppDateField(w, 'date'); w.style.width = '110px'; }
            return w;
        };
        if (d.t === 'text') {
            span.appendChild(input('text', 'v', d.l + ' ' + T.filter_contains));
            span.firstChild.style.width = '140px';
        } else if (d.t === 'number' || d.t === 'date') {
            var type = d.t === 'number' ? 'number' : 'date';
            var name = document.createElement('span');
            name.textContent = d.l;
            span.appendChild(name);
            span.appendChild(input(type, 'f', d.l + ' ' + T.filter_from));
            span.appendChild(document.createTextNode('〜'));
            span.appendChild(input(type, 't', d.l + ' ' + T.filter_to));
        } else {
            var sel = document.createElement('select');
            sel.setAttribute('data-at', k);
            sel.setAttribute('data-part', 'v');
            atOpt(sel, '', d.l + ': ' + T.filter_any, false);
            var pick = d.t === 'user' ? atPeople() : (d.o || []).map(function (o) { return [String(o), String(o)]; });
            pick.forEach(function (p) { atOpt(sel, p[0], d.l + ': ' + p[1], false); });
            span.appendChild(sel);
        }
        return span;
    }
    function atFilterFromControls(box) {
        var out = {};
        box.querySelectorAll('[data-at]').forEach(function (w) {
            var k = w.getAttribute('data-at');
            var part = w.getAttribute('data-part');
            if (part === 'v') { out[k] = w.value; return; }
            var r = Array.isArray(out[k]) ? out[k] : ['', ''];
            r[part === 'f' ? 0 : 1] = w.value;
            out[k] = r;
        });
        return atFilterString(out);
    }
    function atSyncFilterControls() {
        var box = document.getElementById('pp-f-at');
        if (!box || box.hidden) { return; }
        var f = atFilterParse(FILTER.at);
        box.querySelectorAll('[data-at]').forEach(function (w) {
            var v = own(f, w.getAttribute('data-at'));
            var part = w.getAttribute('data-part');
            var want = part === 'v' ? (typeof v === 'string' ? v : '') : (Array.isArray(v) ? v[part === 'f' ? 0 : 1] : '');
            if (w.value !== want) { w.value = want; }
        });
    }
    function renderAtFilters() {
        var box = document.getElementById('pp-f-at');
        if (!box) { return; }
        var defs = atDefs();
        box.hidden = !defs.length;
        var sig = JSON.stringify(defs);
        if (box._ppSig === sig) { return; }
        box._ppSig = sig;
        if (window.ppDateFieldDestroy) { window.ppDateFieldDestroy(box); }
        box.textContent = '';
        if (!defs.length) { return; }
        var T = L.attributes || {};
        var lead = document.createElement('span');
        lead.textContent = (T.section || '') + ':';
        lead.style.cssText = 'color:var(--bl-sub);font-size:12px;';
        box.appendChild(lead);
        defs.forEach(function (d) { box.appendChild(atFilterControl(d, T)); });
        if (!box._ppBound) {
            box._ppBound = true;
            var apply = function () {
                var next = atFilterFromControls(box);
                if (next === FILTER.at) { return; }
                FILTER.at = next;
                afterFilterChange();
            };
            // the text of a text attribute filters as it is typed; the rest on change
            box.addEventListener('input', function (ev) {
                if (ev.target.getAttribute('data-part') === 'v' && ev.target.tagName === 'INPUT') { apply(); }
            });
            box.addEventListener('change', apply);
        }
        atSyncFilterControls();
    }

    /* ---- the bulk edit ---- */
    function atBulkFill() {
        var sel = document.getElementById('pp-bk-at');
        var slot = document.getElementById('pp-bk-atv');
        if (!sel || !slot) { return; }
        var defs = atDefs();
        sel.innerHTML = '';
        if (window.ppDateFieldDestroy) { window.ppDateFieldDestroy(slot); }
        slot.textContent = '';
        slot.style.cssText = '';
        sel.hidden = !defs.length;
        slot.hidden = true;
        if (!defs.length) { return; }
        var T = L.attributes || {};
        atOpt(sel, '', T.bulk_label + ': ' + L.bk_keep, true);
        defs.forEach(function (d) { atOpt(sel, String(d.id), d.l, false); });
        if (!sel._ppBound) {
            sel._ppBound = true;
            sel.addEventListener('change', atBulkValueControl);
        }
    }
    // The value box of the attribute picked: a choice (with 空にする) for the
    // ones chosen from a list, an input and a 空にする box for the others.
    function atBulkValueControl() {
        var sel = document.getElementById('pp-bk-at');
        var slot = document.getElementById('pp-bk-atv');
        var d = atDef(sel.value);
        var T = L.attributes || {};
        if (window.ppDateFieldDestroy) { window.ppDateFieldDestroy(slot); }
        slot.textContent = '';
        slot.hidden = !d;
        // an inline display would show it through [hidden]
        slot.style.cssText = d ? 'display:inline-flex;align-items:center;gap:6px;' : '';
        if (!d) { return; }
        if (d.t === 'select' || d.t === 'multi_select' || d.t === 'user') {
            var pick = document.createElement('select');
            pick.className = 'pp-bk-atval';
            atOpt(pick, '', d.l + ': ' + L.bk_keep, true);
            atOpt(pick, '__none__', d.l + ': ' + T.bulk_clear, false);
            (d.t === 'user' ? atPeople() : (d.o || []).map(function (o) { return [String(o), String(o)]; }))
                .forEach(function (p) { atOpt(pick, p[0], p[1], false); });
            slot.appendChild(pick);
            return;
        }
        var input = document.createElement('input');
        input.className = 'pp-bk-atval';
        input.type = d.t === 'number' ? 'number' : (d.t === 'date' ? 'date' : 'text');
        input.placeholder = d.l;
        input.setAttribute('aria-label', d.l);
        if (d.t === 'date' && window.ppDateField) { window.ppDateField(input, 'date'); }
        slot.appendChild(input);
        var lab = document.createElement('label');
        lab.style.cssText = 'display:inline-flex;align-items:center;gap:4px;margin:0;font-weight:400;font-size:12px;white-space:nowrap;';
        var clear = document.createElement('input');
        clear.type = 'checkbox';
        clear.className = 'pp-bk-atclear';
        clear.addEventListener('change', function () { input.disabled = clear.checked; });
        lab.appendChild(clear);
        lab.appendChild(document.createTextNode(T.bulk_clear || ''));
        slot.appendChild(lab);
    }
    // set.at of the bulk edit, or null when no attribute is to change.
    function atBulkSet() {
        var sel = document.getElementById('pp-bk-at');
        var d = sel && !sel.hidden ? atDef(sel.value) : null;
        if (!d) { return null; }
        var slot = document.getElementById('pp-bk-atv');
        var val = slot.querySelector('.pp-bk-atval');
        var clear = slot.querySelector('.pp-bk-atclear');
        var v = atBulkValue(d, val ? val.value : '', !!(clear && clear.checked));
        if (v === undefined) { return null; }
        var out = {};
        out[String(d.id)] = v;
        return out;
    }
