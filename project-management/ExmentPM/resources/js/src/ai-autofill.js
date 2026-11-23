    /* ------------------------------------------------ AI auto-fill ------ */
    // The button that fills type, category, priority and assignee from the
    // subject and description. The server decides everything that matters -
    // what the project's lists are, what counts as confident, who is free -
    // and this side only puts values into fields the way the person would,
    // and never over a value the person chose.
    function nedAiActive() {
        return !!(D.ai && D.ai.enabled && NED.canEdit && NED.table === D.issue_table && D.urls && D.urls.ai_suggest);
    }
    function nedAiLang(k, args) {
        var text = (L.ai && typeof L.ai[k] === 'string') ? L.ai[k] : k;
        return String(text).replace(/:([a-z_]+)/g, function (m, key) {
            return args && Object.prototype.hasOwnProperty.call(args, key) ? String(args[key]) : m;
        });
    }
    // The columns the AI may write, by this install's names.
    function nedAiColumns() {
        var c = D.issue_cols || {};
        return [c.type, c.category, c.priority, c.assignees].filter(Boolean);
    }
    function nedAiCol(name) {
        var found = null;
        (NED.columns || []).forEach(function (col) { if (col.name === name) { found = col; } });
        return found;
    }
    // A field's value as a list of strings, whatever widget holds it.
    function nedAiNorm(v) {
        if (v === undefined || v === null || v === '') { return []; }
        if (Array.isArray(v)) {
            return v.filter(function (x) { return x !== null && x !== undefined && x !== ''; }).map(String);
        }
        return [String(v)];
    }
    function nedAiSame(a, b) {
        a = nedAiNorm(a).slice().sort();
        b = nedAiNorm(b).slice().sort();
        return a.length === b.length && a.every(function (v, i) { return v === b[i]; });
    }
    // Only an empty field is filled - and, on a new issue, a field still
    // holding the default the form opened with: that default is the
    // project's first row, not a choice anybody made. On an existing issue
    // every value is somebody's and stays.
    function nedAiFillable(col) {
        var now = nedAiNorm(nedCollect(col));
        if (!now.length) { return true; }
        return NED.mode === 'create' && nedAiSame(now, NED.values[col.name]);
    }
    // What a field holds right now, with labels, so undo can put it back.
    function nedAiSnapshot(col) {
        var picker = NED._pickers[col.name];
        if (picker && typeof picker.values === 'function') { return picker.values(); }
        var el = nedInputEl(col.name);
        if (!el) { return []; }
        if (el.tagName === 'SELECT') {
            var out = [];
            Array.prototype.forEach.call(el.options, function (o) {
                if (o.selected && o.value) { out.push({ value: o.value, label: o.textContent }); }
            });
            return out;
        }
        return el.value === '' ? [] : [{ value: String(el.value), label: String(el.value) }];
    }
    // Fill one field the way the user would, so every serializer, differ and
    // validator that reads the form sees an ordinary value.
    function nedAiSet(col, list) {
        var picker = NED._pickers[col.name];
        if (picker && typeof picker.set === 'function') { picker.set(list); return true; }
        var el = nedInputEl(col.name);
        if (!el) { return false; }
        if (el.tagName === 'SELECT') {
            var want = {};
            list.forEach(function (x) { want[String(x.value)] = true; });
            Array.prototype.forEach.call(el.options, function (o) { o.selected = !!want[o.value]; });
            if (!el.multiple && !list.length) { el.value = ''; }
        } else {
            el.value = list.length ? String(list[0].value) : '';
        }
        el.dispatchEvent(new Event('change', { bubbles: true }));
        return true;
    }
    function nedAiChip(col, kind, text, tip) {
        var fg = nedFieldEl(col.name);
        if (!fg) { return null; }
        Array.prototype.slice.call(fg.querySelectorAll('.pp-ne-ai-chip')).forEach(function (n) { n.remove(); });
        var chip = document.createElement('span');
        chip.className = 'pp-ne-ai-chip ' + kind;
        chip.textContent = text;
        if (tip) { chip.title = tip; }
        fg.appendChild(chip);
        return chip;
    }
    // Green above the auto threshold, amber between the two: the backtest
    // put the category at 57-76% right in that band, so it has to be seen.
    function nedAiBand(conf) {
        var auto = (D.ai && D.ai.threshold_auto) || 0.8;
        return conf >= auto ? 'ok' : 'mid';
    }
    function nedAiPct(conf) { return Math.round((conf || 0) * 100); }
    function nedAiClear() {
        if (!NED.el) { return; }
        Array.prototype.slice.call(NED.el.querySelectorAll('.pp-ne-ai-chip')).forEach(function (n) { n.remove(); });
        Array.prototype.slice.call(NED.el.querySelectorAll('.pp-ne-fg.pp-ne-ai')).forEach(function (n) { n.classList.remove('pp-ne-ai'); });
    }
    function nedAiMount(slots, titleCol, descCol) {
        NED._ai = null;
        if (!nedAiActive() || !titleCol) { return; }
        var titleIn = nedInputEl(titleCol.name);
        if (!titleIn) { return; }
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'pp-ne-ai-btn';
        btn.innerHTML = '<i class="fa fa-magic"></i> ' + esc(nedAiLang('fill'));
        slots.title.classList.add('has-ai');
        slots.title.appendChild(btn);

        var descIn = descCol ? nedInputEl(descCol.name) : null;
        var minSubject = (D.ai && D.ai.min_subject) || 3;
        var minDesc = (D.ai && D.ai.min_description) || 30;
        // Dim until there is a subject; the tooltip says what a longer
        // description would add, because priority is only scored with one.
        var refresh = function () {
            if (NED._ai && NED._ai.busy) { return; }
            var subject = String(titleIn.value || '').trim();
            var desc = String(descIn ? descIn.value : '').replace(/<[^>]*>/g, '').trim();
            btn.disabled = NED.busy || subject.length < minSubject;
            btn.title = btn.disabled ? nedAiLang('need_text')
                : (desc.length < minDesc ? nedAiLang('need_desc_tip', { n: minDesc }) : '');
        };
        titleIn.addEventListener('input', refresh);
        if (descIn) { descIn.addEventListener('input', refresh); }
        btn.addEventListener('click', function () { nedAiRun(btn, titleCol, descCol); });
        btn._ppAiRefresh = refresh;
        refresh();
    }
    function nedAiRun(btn, titleCol, descCol) {
        if (NED.busy || !nedAiActive()) { return; }
        var subject = String(nedCollect(titleCol) || '').trim();
        var description = descCol ? String(nedCollect(descCol) || '') : '';
        if (!subject) { return; }
        var epoch = NED.epoch;
        // Busy blocks the save: a save landing between the request and the
        // answer would store a half-filled form and reload under the answer.
        NED.busy = true;
        NED._ai = { busy: true };
        var was = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> ' + esc(nedAiLang('filling'));
        var saveBtns = NED.el.querySelectorAll('.pp-ne-save');
        saveBtns.forEach(function (b) { b.disabled = true; });
        nedShowAlert('', false);
        var done = function () {
            if (!nedCurrent(epoch)) { return; }
            NED.busy = false;
            btn.disabled = false;
            btn.innerHTML = was;
            saveBtns.forEach(function (b) { b.disabled = !NED.canEdit; });
        };
        var body = { subject: subject, description: description };
        if (NED.mode !== 'create' && NED.rowId) { body.issue_id = NED.rowId; }
        // The category as the form holds it now - unsaved, or emptied - which
        // is what decides a category's default assignee, not the one stored
        // on the row or the one the AI would guess.
        var catCol = nedAiCol((D.issue_cols || {}).category);
        if (catCol) {
            var cat = nedCollect(catCol);
            body.category = (cat === undefined || cat === null) ? '' : cat;
        }
        fetch(D.urls.ai_suggest, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
            credentials: 'same-origin',
            body: JSON.stringify(body)
        }).then(ppJson, ppNoAnswer).then(function (res) {
            if (!nedCurrent(epoch)) { return; }
            done();
            if (res.status !== 200 || !res.body || !res.body.ok) {
                NED._ai = null;
                nedShowAlert(errText(res.status, res.body, nedAiLang('err_failed')), false);
                return;
            }
            nedAiApply(res.body);
        }).catch(function () {
            if (!nedCurrent(epoch)) { return; }
            done();
            NED._ai = null;
            nedShowAlert(nedAiLang('err_failed'), false);
        });
    }
    // Put the answers on the form: fill what is empty, offer where a value
    // already stands or where the AI was not sure, and say why where it has
    // nothing to offer.
    function nedAiApply(res) {
        nedAiClear();
        var state = { busy: false, suggestion_id: res.suggestion_id, prev: {}, filled: [], fields: res.fields || {} };
        var filled = 0;
        var picks = 0;
        nedAiColumns().forEach(function (name) {
            var col = nedAiCol(name);
            var f = state.fields[name];
            if (!col || !f) { return; }
            if (f.value === null || f.value === undefined) {
                nedAiChip(col, 'off', f.reason || nedAiLang('unsure'), null);
                return;
            }
            var list = nedAiNorm(f.value).map(function (v, i) {
                return { value: v, label: (i === 0 && f.label) ? f.label : v };
            });
            var pct = nedAiPct(f.confidence);
            var tip = f.reason || '';
            var mark = function () {
                var fg = nedFieldEl(col.name);
                if (fg) { fg.classList.add('pp-ne-ai'); }
                nedAiChip(col, nedAiBand(f.confidence), (f.severe ? '⚠ ' : '') + nedAiLang('chip', { pct: pct }), tip);
            };
            if (!f.ok) {
                // Below the threshold the best guess is offered, never put
                // in: on the type check it was still right 8 times in 9,
                // so it is worth one click - the person's click.
                if (nedAiSame(nedCollect(col), f.value)) {
                    nedAiChip(col, 'off', f.reason || nedAiLang('unsure'), null);
                    return;
                }
                var pickTip = nedAiLang('pick_tip');
                if (f.reason && f.reason !== nedAiLang('unsure')) { pickTip += '\n' + f.reason; }
                nedAiOffer(col, 'pick', nedAiLang('pick', { label: f.label || '', pct: pct }), pickTip, list, state, mark);
                picks++;
                return;
            }
            if (nedAiFillable(col)) {
                state.prev[name] = nedAiSnapshot(col);
                if (nedAiSet(col, list)) {
                    state.filled.push(name);
                    filled++;
                    mark();
                }
                return;
            }
            if (nedAiSame(nedCollect(col), f.value)) { return; }
            // A value the person chose stays; the suggestion becomes a chip.
            nedAiOffer(col, 'adopt', nedAiLang('adopt', { label: f.label || '', pct: pct }), tip, list, state, mark);
        });
        NED._ai = state;
        nedUpdateChangeCount();
        nedAiNotice(filled, picks);
    }
    // A suggestion as a chip beside the field; a click on it is the person
    // choosing that value, the same as picking it from the list.
    function nedAiOffer(col, kind, text, tip, list, state, mark) {
        var chip = nedAiChip(col, kind, text, tip);
        if (!chip) { return; }
        chip.setAttribute('role', 'button');
        chip.tabIndex = 0;
        var take = function () {
            state.prev[col.name] = nedAiSnapshot(col);
            if (nedAiSet(col, list)) {
                state.filled.push(col.name);
                mark();
                nedUpdateChangeCount();
            }
        };
        chip.addEventListener('click', take);
        chip.addEventListener('keydown', function (ev) {
            if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); take(); }
        });
    }
    function nedAiNotice(filled, picks) {
        if (!filled) { nedShowAlert(nedAiLang(picks ? 'filled_none_pick' : 'filled_none'), true); return; }
        nedShowAlert(nedAiLang('filled', { n: filled }), true);
        var alert = NED.el.querySelector('.pp-ne-alert');
        var undo = document.createElement('button');
        undo.type = 'button';
        undo.className = 'pp-ne-ai-undo';
        undo.textContent = nedAiLang('undo');
        undo.addEventListener('click', nedAiUndo);
        alert.appendChild(undo);
    }
    function nedAiUndo() {
        var state = NED._ai;
        if (!state) { return; }
        Object.keys(state.prev).forEach(function (name) {
            var col = nedAiCol(name);
            if (col) { nedAiSet(col, state.prev[name] || []); }
        });
        nedAiClear();
        state.filled = [];
        state.prev = {};
        nedUpdateChangeCount();
        nedShowAlert(nedAiLang('undone'), true);
    }
    // After a successful save: what the person kept, against what was
    // offered. Fire and forget - the page is about to reload - and keepalive
    // so the reload does not cancel it.
    function nedAiFeedback(issueId, state, submitted, url) {
        if (!state || !state.suggestion_id || !url) { return; }
        var applied = {};
        Object.keys(state.fields || {}).forEach(function (name) {
            if (Object.prototype.hasOwnProperty.call(submitted, name)) { applied[name] = submitted[name]; }
        });
        try {
            fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
                credentials: 'same-origin',
                keepalive: true,
                body: JSON.stringify({ suggestion_id: state.suggestion_id, issue_id: issueId || null, applied: applied })
            }).catch(function () { /* statistics only */ });
        } catch (e) { /* statistics only */ }
    }

