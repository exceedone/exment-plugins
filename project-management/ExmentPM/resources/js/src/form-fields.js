    /* ------------------------------------------------------------------ */
    /* the issue browser                                                   */
    /* ------------------------------------------------------------------ */

    // A key and a title, the way the issue list and the detail header write
    // them. The lookup endpoint answers with a row id and a label, which is
    // enough to file the row but not enough to recognise it by: PORTAL-291
    // is how people refer to a課題 to each other. Where the portal already
    // holds the issue its own key is used, and where it does not - an issue
    // outside the loaded set - the key is composed the same way the server
    // composes it, from the project key and the row id.
    function ppIssueRow(value, label) {
        var known = typeof issueById === 'function' ? issueById(value) : null;
        if (known && known.key) {
            return { value: String(value), key: String(known.key), subject: String(known.subject || label || '') };
        }
        var pk = (D.project && D.project.key) ? String(D.project.key) : '#';
        return { value: String(value), key: pk + '-' + value, subject: String(label || '') };
    }

    // Formats one row for the places that show a chosen issue as a single
    // line: the field itself, and the title of the form it opened from.
    function ppIssueLabel(row) {
        return row.subject ? row.key + ' ' + row.subject : row.key;
    }

    // A modal list of this project's issues, searched on the server.
    //
    // Searching here rather than in the browser is what makes it usable on a
    // project with more issues than one response can carry: the endpoint
    // answers at most a hundred rows, so a list filtered locally would be
    // filtering the first hundred and quietly hiding the rest. Every rule
    // about which issues may be picked - not itself, and not one that is
    // already somebody's child where subtasking is one level deep - is
    // already encoded in the url the caller passes, so this function decides
    // nothing about what is offered and only decides how it is shown.
    //
    // opts: { url, title, onPick }
    function ppIssueModal(opts) {
        var el = document.createElement('div');
        el.className = 'pp-ib';
        el.setAttribute('role', 'dialog');
        el.setAttribute('aria-modal', 'true');
        el.innerHTML =
            '<div class="pp-ib-bd"></div>' +
            '<div class="pp-ib-panel">' +
              '<div class="pp-ib-head">' +
                '<span class="pp-ib-title"></span>' +
                '<button type="button" class="pp-ib-x" aria-label="' + esc(L.ib_close || '') + '">&times;</button>' +
              '</div>' +
              '<div class="pp-ib-qwrap"><i class="fa fa-search"></i>' +
                '<input type="text" class="pp-ib-q" autocomplete="off">' +
              '</div>' +
              '<div class="pp-ib-body">' +
                '<table class="pp-ib-tb"><thead><tr>' +
                  '<th class="k"></th><th class="s"></th>' +
                '</tr></thead><tbody></tbody></table>' +
                '<div class="pp-ib-msg"></div>' +
              '</div>' +
              '<div class="pp-ib-foot"><span class="pp-ib-hint"></span>' +
                '<button type="button" class="pp-ib-clear" hidden></button>' +
              '</div>' +
            '</div>';

        var q = el.querySelector('.pp-ib-q');
        var tb = el.querySelector('.pp-ib-tb tbody');
        var msg = el.querySelector('.pp-ib-msg');
        el.querySelector('.pp-ib-title').textContent = opts.title || L.ib_pick || '';
        q.placeholder = L.ib_search || '';
        el.querySelector('.pp-ib-tb th.k').textContent = L.ib_col_key || '';
        el.querySelector('.pp-ib-tb th.s').textContent = L.ib_col_subject || '';
        el.querySelector('.pp-ib-hint').textContent = L.ib_hint || '';
        // Offered only when the field already holds an issue, because this
        // button is the only way to empty a field that carries no cross.
        var clearBtn = el.querySelector('.pp-ib-clear');
        clearBtn.textContent = L.ib_clear || '';
        clearBtn.hidden = !opts.canClear;
        clearBtn.addEventListener('click', function () {
            close();
            if (opts.onPick) { opts.onPick(null); }
        });

        var rows = [];
        var active = -1;
        var seq = 0;
        var closed = false;

        function close() {
            if (closed) { return; }
            closed = true;
            document.removeEventListener('keydown', onKey, true);
            el.remove();
            document.body.classList.remove('pp-ib-open');
        }

        function setActive(n) {
            var trs = tb.querySelectorAll('tr');
            if (!trs.length) { active = -1; return; }
            active = Math.max(0, Math.min(trs.length - 1, n));
            Array.prototype.forEach.call(trs, function (tr, i) {
                tr.classList.toggle('on', i === active);
            });
            var cur = trs[active];
            if (cur && cur.scrollIntoView) { cur.scrollIntoView({ block: 'nearest' }); }
        }

        function choose(n) {
            var row = rows[n];
            if (!row) { return; }
            close();
            if (opts.onPick) { opts.onPick(row); }
        }

        function paint(list) {
            rows = list;
            tb.innerHTML = '';
            if (!list.length) {
                msg.textContent = L.ib_empty || '';
                msg.hidden = false;
                active = -1;
                return;
            }
            msg.hidden = true;
            list.forEach(function (row, i) {
                var tr = document.createElement('tr');
                tr.innerHTML = '<td class="k"><span class="bl-key">' + esc(row.key) + '</span></td>' +
                    '<td class="s">' + esc(row.subject) + '</td>';
                tr.addEventListener('click', function () { choose(i); });
                tr.addEventListener('mousemove', function () { if (active !== i) { setActive(i); } });
                tb.appendChild(tr);
            });
            setActive(0);
        }

        function load(term) {
            var mine = ++seq;
            msg.textContent = L.ib_loading || '';
            msg.hidden = false;
            var sep = opts.url.indexOf('?') >= 0 ? '&' : '?';
            var url = opts.url + sep + 'limit=100' + (term ? '&q=' + encodeURIComponent(term) : '');
            fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
                .then(function (r) { return r.ok ? r.json() : { results: [] }; })
                .catch(function () { return { results: [] }; })
                .then(function (j) {
                    // A slow answer to an old keystroke must not replace the
                    // list the newest one already painted.
                    if (closed || mine !== seq) { return; }
                    paint((j.results || []).map(function (r) { return ppIssueRow(r.value, r.label); }));
                });
        }

        function onKey(ev) {
            // Left open by a page pjax has since replaced: it would go on
            // eating Enter and the arrow keys on the page that replaced it.
            if (!alive()) { close(); return; }
            if (ev.key === 'Escape') { ev.preventDefault(); ev.stopPropagation(); close(); return; }
            if (ev.key === 'ArrowDown') { ev.preventDefault(); setActive(active + 1); return; }
            if (ev.key === 'ArrowUp') { ev.preventDefault(); setActive(active - 1); return; }
            if (ev.key === 'Enter') { ev.preventDefault(); choose(active); }
        }

        var timer = null;
        q.addEventListener('input', function () {
            clearTimeout(timer);
            timer = setTimeout(function () { load(q.value.trim()); }, 220);
        });
        el.querySelector('.pp-ib-x').addEventListener('click', close);
        el.querySelector('.pp-ib-bd').addEventListener('click', close);
        // Capture, so Escape closes this and not the form underneath it.
        document.addEventListener('keydown', onKey, true);

        document.body.appendChild(el);
        document.body.classList.add('pp-ib-open');
        try { q.focus(); } catch (e) { /* ignore */ }
        load('');

        return { close: close };
    }
    window.ppIssueModal = ppIssueModal;

    // An issue-valued field that is filled from the modal above.
    //
    // The value rides in a hidden <select> carrying opts.valueClass, exactly
    // as ppMakePicker's does, so every serializer, differ and validator that
    // reads a picker keeps working against this one unchanged.
    //
    // opts: { url, title, valueClass, readonly, value: {value,label}|null }
    function ppIssueField(opts) {
        var readonly = !!opts.readonly;

        var root = document.createElement('div');
        root.className = 'pp-ibf' + (readonly ? ' readonly' : '');

        var select = document.createElement('select');
        select.hidden = true;
        select.tabIndex = -1;
        if (opts.valueClass) { select.className = opts.valueClass; }

        var face = document.createElement('button');
        face.type = 'button';
        face.className = 'pp-ibf-face';
        if (readonly) { face.disabled = true; }

        var current = null;

        function sync() {
            select.innerHTML = '';
            var none = document.createElement('option');
            none.value = ''; none.textContent = '';
            select.appendChild(none);
            if (current) {
                var opt = document.createElement('option');
                opt.value = current.value;
                opt.textContent = ppIssueLabel(current);
                opt.selected = true;
                select.appendChild(opt);
            }
            if (current) {
                face.innerHTML = '<span class="bl-key">' + esc(current.key) + '</span>' +
                    '<span class="sj">' + esc(current.subject) + '</span>';
                face.classList.add('set');
            } else {
                face.textContent = L.ib_pick || '';
                face.classList.remove('set');
            }
            select.dispatchEvent(new Event('change', { bubbles: true }));
        }

        function open() {
            if (readonly) { return; }
            ppIssueModal({
                url: opts.url,
                title: opts.title,
                // One value means no cross beside the field: the browser that
                // sets it is also where it is unset.
                canClear: !!current,
                onPick: function (row) { current = row || null; sync(); }
            });
        }

        face.addEventListener('click', open);

        if (opts.value && opts.value.value) {
            current = ppIssueRow(opts.value.value, opts.value.label);
        }

        root.appendChild(face);
        root.appendChild(select);
        sync();

        return {
            root: root,
            select: select,
            open: open,
            reset: function () { current = null; sync(); },
            focus: function () { try { face.focus(); } catch (e) { /* ignore */ } }
        };
    }
    window.ppIssueField = ppIssueField;

    // A list that has just grown a row. Dropping the cached copy is what
    // makes the new one appear the next time the picker is opened.
    function nedForgetLookup(url) {
        if (!url) { return; }
        delete NED.lookupCache[url];
        delete NED.lookupInflight[url];
    }
    function nedFetchLookup(url) {
        if (NED.lookupCache[url]) { return Promise.resolve(NED.lookupCache[url]); }
        // Dedupe in-flight requests: N select_table columns pointing at
        // the same lookup URL (common when several fields reference the
        // same master table) collapse to a single network request.
        if (NED.lookupInflight[url]) { return NED.lookupInflight[url]; }
        var sep = url.indexOf('?') >= 0 ? '&' : '?';
        // A form with six relation columns used to open six connections at
        // once. They go through the queue now, so the server sees them one
        // after another and the first list still paints straight away.
        var p = ppQueue(function () {
            return fetch(url + sep + 'limit=100', { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
                .then(function (r) { return r.ok ? r.json() : { results: [] }; });
        })
            .then(function (j) { NED.lookupCache[url] = j.results || []; return NED.lookupCache[url]; })
            .catch(function () { NED.lookupCache[url] = []; return []; })
            .finally(function () { delete NED.lookupInflight[url]; });
        NED.lookupInflight[url] = p;
        return p;
    }

    // Compact date rendering for the header meta strip. Turns
    // "2026-08-25 14:47:07" into "2026/08/25 14:47" (drops seconds and
    // matches the Backlog format users are used to reading).
    function nedFormatMetaDate(raw) {
        if (!raw) { return ''; }
        var s = String(raw).replace('T', ' ');
        var m = s.match(/^(\d{4})-(\d{2})-(\d{2})[ ]?(\d{2}:\d{2})?/);
        if (!m) { return s.slice(0, 16); }
        return m[1] + '/' + m[2] + '/' + m[3] + (m[4] ? ' ' + m[4] : '');
    }

    // Populate the small "created / updated" strip under the title so the
    // user still sees provenance even though we hid the id/timestamp
    // rows from the form. Silently no-ops in create mode.
    function nedRenderMetaStrip(payload) {
        var strip = NED.el.querySelector('.pp-ne-meta');
        var sepEl = NED.el.querySelector('.pp-ne-meta-sep');
        var cEl = NED.el.querySelector('.pp-ne-meta-created');
        var uEl = NED.el.querySelector('.pp-ne-meta-updated');
        if (!strip) { return; }
        var row = payload && payload.row;
        if (!row || NED.mode === 'create') {
            strip.hidden = true; return;
        }
        // "作成 2026/10/14 10:00 · 山田": who comes after the time, with no
        // English "by" between them.
        var cLabel = nedGetLangKey('ned_meta_created');
        var uLabel = nedGetLangKey('ned_meta_updated');
        // The times as the server formatted them for this user (their time
        // zone); created_at / updated_at themselves are the stored values.
        var cDate = nedFormatMetaDate(row.created_at_shown || row.created_at);
        var uDate = nedFormatMetaDate(row.updated_at_shown || row.updated_at);
        var cBy = row.created_by || '';
        var uBy = row.updated_by || '';
        var showC = !!cDate;
        var showU = !!uDate && uDate !== cDate;
        if (showC) {
            cEl.textContent = cLabel + ' ' + cDate + (cBy ? ' · ' + cBy : '');
            cEl.hidden = false;
        } else { cEl.hidden = true; }
        if (showU) {
            uEl.textContent = uLabel + ' ' + uDate + (uBy && uBy !== cBy ? ' · ' + uBy : '');
            uEl.hidden = false;
        } else { uEl.hidden = true; }
        if (sepEl) { sepEl.hidden = !(showC && showU); }
        strip.hidden = !(showC || showU);
    }

    // Copy the issue key (or fallback "#<id>") to the clipboard. Falls
    // back to a hidden textarea + execCommand path on old browsers so
    // this keeps working in the admin's iframe context.
    function nedCopyKey() {
        var keyEl = NED.el.querySelector('.pp-ne-key');
        if (!keyEl) { return; }
        var text = keyEl.textContent || '';
        if (!text || text === nedGetLangKey('ned_new')) { return; }
        var showFlash = function () {
            var orig = text;
            keyEl.classList.add('copied');
            keyEl.textContent = nedGetLangKey('ned_key_copied');
            setTimeout(function () {
                keyEl.classList.remove('copied');
                keyEl.textContent = orig;
            }, 900);
        };
        try {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(showFlash, function () { showFlash(); });
                return;
            }
        } catch (e) { /* fall through */ }
        try {
            var ta = document.createElement('textarea');
            ta.value = text; ta.style.position = 'fixed'; ta.style.top = '-1000px';
            document.body.appendChild(ta); ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
            showFlash();
        } catch (e) { /* silent */ }
    }

    // Renders one field into the form and returns the wrapping element.
    // The dispatch is intentionally boring so the reader can tell at a
    // glance which HTML control shows up for which Exment column type.
    // The issue form offers one assignee, the way Backlog does: a task two
    // people own is a task nobody owns. The column itself stays multi-valued,
    // so a row that already carries more than one still shows all of them -
    // dropping the second name silently on the next save would be a worse
    // answer than the question deserves.
    function nedPickerMultiple(col, current) {
        if (!col.multiple) { return false; }
        if (NED.table !== D.issue_table) { return true; }
        var as = (D.issue_cols || {}).assignees;
        if (!as || col.name !== as) { return true; }

        return current.length > 1;
    }
    // The column's own help describes the column, which is multi-valued and
    // says so. This form offers one name, so under this form it would be
    // describing something the reader cannot do.
    function nedFieldHelp(col) {
        if (NED.table === D.issue_table) {
            var as = (D.issue_cols || {}).assignees;
            if (as && col.name === as && !nedPickerMultiple(col, [])) {
                return nedGetLangKey('ne_as_help', col.help);
            }
        }

        return col.help;
    }
    // "I will take this one" - the single most used field value on the form,
    // and the one that needs a search box least.
    function nedMineButton(col, picker) {
        if (NED.table !== D.issue_table || !D.me) { return null; }
        var as = (D.issue_cols || {}).assignees;
        if (!as || col.name !== as) { return null; }
        var b = document.createElement('button');
        b.type = 'button';
        b.className = 'pp-ne-sidebtn';
        b.textContent = nedGetLangKey('ne_mine');
        b.addEventListener('click', function () {
            picker.add(String(D.me), user(D.me).n);
        });

        return b;
    }
    // Wanting a milestone that does not exist yet is not a reason to leave
    // the issue you are writing, go to the settings tab, make one, come back
    // and start again. Which column holds the name and which one ties the row
    // to the project comes from the server, and the button only exists when
    // the server also said this user may create one.
    function nedQuickAdd(col, picker) {
        var conf = (D.quick_add || {})[col.target_table];
        if (!conf || col.can_create !== true) { return null; }
        if (!D.project || !D.project.id || !(D.urls && D.urls.lightapi_base)) { return null; }

        var input = document.createElement('input');
        input.type = 'text';
        input.placeholder = nedGetLangKey('ne_qa_ph');
        var add = document.createElement('button');
        add.type = 'button';
        add.className = 'pp-ne-sidebtn';
        add.textContent = nedGetLangKey('ne_qa_add');
        var box = document.createElement('div');
        box.className = 'pp-ne-qa';
        box.hidden = true;
        box.appendChild(input);
        box.appendChild(add);

        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'pp-ne-sidebtn';
        btn.title = nedGetLangKey('ne_qa_open');
        btn.setAttribute('aria-label', nedGetLangKey('ne_qa_open'));
        btn.textContent = '+';
        btn.addEventListener('click', function () {
            box.hidden = !box.hidden;
            if (!box.hidden) { input.focus(); }
        });

        var busy = false;
        var submit = function () {
            var name = input.value.trim();
            if (busy || name === '') { return; }
            busy = true;
            add.disabled = true;
            var value = {};
            value[conf.col] = name;
            value[conf.parent] = String(D.project.id);
            fetch(D.urls.lightapi_base + '/data/' + col.target_table, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': CSRF
                },
                credentials: 'same-origin',
                body: JSON.stringify({ value: value })
            }).then(ppJson, ppNoAnswer).then(function (res) {
                busy = false;
                add.disabled = false;
                var body = res.body || {};
                if (!body.ok || !body.id) {
                    toast(nedQuickAddError(res.status, res.body), true);
                    return;
                }
                nedForgetLookup(col.lookup_url);
                picker.add(String(body.id), name);
                input.value = '';
                box.hidden = true;
                toast(String(nedGetLangKey('ne_qa_done')).replace(':name', name));
            }).catch(function () {
                busy = false;
                add.disabled = false;
                toast(nedGetLangKey('ne_qa_fail'), true);
            });
        };
        add.addEventListener('click', submit);
        input.addEventListener('keydown', function (ev) {
            if (ev.key === 'Enter') { ev.preventDefault(); submit(); return; }
            // Stopped here, or the form itself would read it as "close me".
            if (ev.key === 'Escape') { ev.stopPropagation(); box.hidden = true; }
        });

        return { btn: btn, box: box };
    }
    // Say what went wrong when the server knows, rather than "failed": the
    // field's own validation message first, then the portal's sentence.
    function nedQuickAddError(status, body) {
        var fields = body && body.ok === false && body.fields;
        if (fields && typeof fields === 'object') {
            var first = Object.keys(fields)[0];
            if (first) {
                var msg = fields[first];

                return Array.isArray(msg) ? msg.join(' ') : String(msg);
            }
        }

        return errText(status, body, nedGetLangKey('ne_qa_fail'));
    }
    // Most issues have no parent, so the field opens as a line of text rather
    // than as a search box nobody is going to type in. One click turns it
    // into the search box. An issue that already has a parent skips this and
    // shows it, because there it is the answer rather than the question.
    // Whether this column is the one that makes a課題 somebody's subtask.
    // Named by the payload rather than by a literal, because the column is
    // whatever the install called it.
    function nedIsParentIssueCol(col) {
        var parentCol = (D.issue_cols || {}).parent;
        return !!(parentCol && NED.table === D.issue_table && col && col.name === parentCol);
    }
    function nedParentFold(col, wrap, holder, picker, readonly) {
        var parentCol = (D.issue_cols || {}).parent;
        if (NED.table !== D.issue_table || !parentCol || col.name !== parentCol) { return; }
        wrap.classList.add('pp-ne-fg-parent');
        if (readonly || picker.select.value) { return; }
        holder.hidden = true;
        var open = document.createElement('button');
        open.type = 'button';
        open.className = 'pp-ne-parent-open';
        var icon = document.createElement('i');
        icon.className = 'fa fa-sitemap';
        open.appendChild(icon);
        open.appendChild(document.createTextNode(nedGetLangKey('ne_parent_set')));
        open.addEventListener('click', function () {
            open.remove();
            holder.hidden = false;
            picker.focus();
        });
        wrap.appendChild(open);
    }
    // A picker, plus whatever small controls belong beside it.
    function nedAppendPicker(col, wrap, picker, readonly) {
        var holder = picker.root;
        var sides = [];
        var qa = null;
        if (!readonly) {
            var mine = nedMineButton(col, picker);
            if (mine) { sides.push(mine); }
            qa = nedQuickAdd(col, picker);
            if (qa) { sides.push(qa.btn); }
        }
        if (sides.length) {
            holder = document.createElement('div');
            holder.className = 'pp-ne-withbtn';
            holder.appendChild(picker.root);
            sides.forEach(function (b) { holder.appendChild(b); });
        }
        wrap.appendChild(holder);
        if (qa) { wrap.appendChild(qa.box); }
        nedParentFold(col, wrap, holder, picker, readonly);
        // Kept so one field can fill in another: a mention in the description
        // adds the person mentioned to the notify column.
        NED._pickers[col.name] = picker;
    }

    function nedField(col, value, labels) {
        var wrap = document.createElement('div');
        wrap.className = 'pp-ne-fg';
        wrap.dataset.fname = col.name;
        wrap.dataset.ftype = col.type;
        if (NED_TYPES_WIDE[col.type]) { wrap.classList.add('wide'); }

        var lbl = document.createElement('label');
        lbl.textContent = col.label || col.name;
        if (col.required && !col.readonly) {
            var req = document.createElement('span'); req.className = 'req'; req.textContent = '*'; lbl.appendChild(req);
        }
        if (col.readonly) {
            var ro = document.createElement('span'); ro.className = 'ro'; ro.textContent = nedGetLangKey('ned_readonly');
            lbl.appendChild(ro);
        }
        wrap.appendChild(lbl);

        var input, ph;
        var readonly = col.readonly || !NED.canEdit;

        if (!NED.canEdit && col.name !== 'id' && col.type !== 'auto_number') { readonly = true; }

        if (col.type === 'boolean') {
            // The row label above already names this switch; naming it a
            // second time beside the box is the doubled text the project
            // settings escape hatch used to show. The box rides alone in
            // the control column, with the row label as its click target.
            var chk = document.createElement('label'); chk.className = 'pp-ne-check';
            input = document.createElement('input'); input.type = 'checkbox'; input.className = 'pp-ne-in';
            input.id = 'pp-ne-b-' + col.name;
            lbl.htmlFor = input.id;
            input.checked = !!(value === true || value === 1 || value === '1' || value === 'on');
            input.disabled = readonly;
            chk.appendChild(input);
            wrap.appendChild(chk);
        } else if (col.type === 'richtext' || (col.type === 'text' && col.raw_type === 'textarea')) {
            input = document.createElement('textarea'); input.className = 'pp-ne-in';
            input.rows = 6; input.value = value == null ? '' : String(value);
            if (readonly) { input.readOnly = true; }
            if (col.placeholder) { input.placeholder = col.placeholder; }
            wrap.appendChild(input);
        } else if (col.type === 'select' && Array.isArray(col.options)) {
            // Segmented pill selector for compact coloured selects
            // (status/priority): one click, colour-coded, no dropdown.
            // Reserved for single-select with a small option set so the
            // Phase A parity: Backlog uses plain <select> dropdowns for
            // status / priority; the segmented pill lives in Phase B as
            // opt-in "enhanced" mode gated by NED.opts.segmentedPills.
            var useSeg = !!(NED.opts && NED.opts.segmentedPills)
                && !col.multiple && col.options.length >= 1 && col.options.length <= 5;
            if (useSeg) {
                var seg = document.createElement('div'); seg.className = 'pp-ne-seg';
                if (readonly) { seg.classList.add('readonly'); }
                input = document.createElement('input');
                input.type = 'hidden'; input.className = 'pp-ne-in';
                input.value = (value == null || value === undefined) ? '' : String(value);
                var curVal = String(input.value);
                col.options.forEach(function (o) {
                    var btn = document.createElement('button');
                    btn.type = 'button'; btn.className = 'pp-ne-seg-b';
                    btn.dataset.value = String(o.key);
                    btn.textContent = o.label;
                    if (o.color) { btn.style.setProperty('--seg-c', o.color); }
                    if (String(o.key) === curVal) { btn.classList.add('on'); }
                    btn.addEventListener('click', function () {
                        if (readonly) { return; }
                        seg.querySelectorAll('.pp-ne-seg-b').forEach(function (x) { x.classList.remove('on'); });
                        var alreadyOn = input.value === String(o.key);
                        if (alreadyOn) { input.value = ''; }
                        else { input.value = String(o.key); btn.classList.add('on'); }
                        input.dispatchEvent(new Event('change', { bubbles: true }));
                    });
                    seg.appendChild(btn);
                });
                wrap.appendChild(seg);
                wrap.appendChild(input);
            } else {
                input = document.createElement('select'); input.className = 'pp-ne-in';
                if (col.multiple) { input.multiple = true; }
                if (!col.multiple) {
                    var empty = document.createElement('option');
                    empty.value = ''; empty.textContent = nedGetLangKey('ned_none');
                    input.appendChild(empty);
                }
                var multiVals = Array.isArray(value) ? value.map(String) : (value == null ? [] : [String(value)]);
                col.options.forEach(function (o) {
                    var opt = document.createElement('option');
                    opt.value = o.key; opt.textContent = o.label;
                    if (multiVals.indexOf(String(o.key)) >= 0) { opt.selected = true; }
                    if (o.color) { opt.style.color = o.color; }
                    input.appendChild(opt);
                });
                if (readonly) { input.disabled = true; }
                wrap.appendChild(input);
                // Optional pill preview under a single-value coloured select.
                if (!col.multiple) {
                    var pill = document.createElement('span'); pill.className = 'pp-ne-pill';
                    var applyPill = function () {
                        var opt = input.options[input.selectedIndex];
                        if (opt && opt.value) {
                            pill.hidden = false; pill.textContent = opt.textContent;
                            pill.style.background = opt.style.color || 'var(--bl-sub)';
                        } else { pill.hidden = true; }
                    };
                    applyPill();
                    input.addEventListener('change', applyPill);
                    var pillWrap = document.createElement('div'); pillWrap.style.marginTop = '4px'; pillWrap.appendChild(pill);
                    wrap.appendChild(pillWrap);
                }
            }
        } else if ((col.type === 'relation' || col.type === 'user' || col.type === 'organization') && col.lookup_url) {
            var pre = Array.isArray(value) ? value.map(String)
                : (typeof value === 'string' && value.indexOf(',') >= 0 ? value.split(',').map(function (v) { return v.trim(); })
                : (value == null || value === '' ? [] : [String(value)]));
            var preLabels = (labels && labels[col.name])
                ? String(labels[col.name]).split(/、/) : [];
            if (nedIsParentIssueCol(col)) {
                // The parent of a課題 is picked from a table of keys and
                // titles, not from a line of labels: a dropdown of subjects
                // alone cannot be read, because the key is how people refer
                // to an issue and two issues often share a subject.
                var pf = ppIssueField({
                    url: col.lookup_url,
                    title: nedGetLangKey('ib_title_parent'),
                    valueClass: 'pp-ne-in',
                    readonly: readonly,
                    value: pre.length ? { value: pre[0], label: preLabels[0] || '' } : null
                });
                input = pf.select;
                wrap.classList.add('pp-ne-fg-parent');
                wrap.appendChild(pf.root);
                NED._pickers[col.name] = pf;
            } else {
            var picker = ppMakePicker({
                url: col.lookup_url,
                multiple: nedPickerMultiple(col, pre),
                readonly: readonly,
                valueClass: 'pp-ne-in',
                values: pre.map(function (v, i) { return { value: v, label: preLabels[i] || v }; }),
                texts: { search: nedGetLangKey('lkp_search'), empty: nedGetLangKey('lkp_empty') }
            });
            input = picker.select;
            nedAppendPicker(col, wrap, picker, readonly);
            }
        } else if (col.type === 'date' || col.type === 'datetime' || col.type === 'time') {
            input = document.createElement('input');
            input.className = 'pp-ne-in';
            // The picker's format is the API's own, so the value goes in
            // untouched apart from trimming a date's trailing time. That
            // also makes the diff honest: the old datetime-local input held
            // "...T14:30" while the server had "...14:30:00", so a datetime
            // column counted as edited on every save even when nobody
            // touched it, and was written back each time.
            var v = String(value == null ? '' : value);
            if (col.type === 'date') { v = v.slice(0, 10); }
            // The picker writes hours and minutes (see _datepicker); a stored
            // 14:30:00 is shown as 14:30.
            if (col.type === 'time') { v = v.slice(0, 5); }
            input.value = v;
            if (readonly) { input.readOnly = true; }
            if (window.ppDateField) { window.ppDateField(input, col.type); }
            wrap.appendChild(input);
        } else if (col.type === 'integer' || col.type === 'number') {
            input = document.createElement('input');
            input.className = 'pp-ne-in';
            input.type = 'number';
            if (col.type === 'integer') { input.step = '1'; }
            input.value = value == null ? '' : String(value);
            if (readonly) { input.readOnly = true; }
            wrap.appendChild(input);
        } else if (col.type === 'auto_number' || col.type === 'id') {
            input = document.createElement('input');
            input.className = 'pp-ne-in';
            input.type = 'text';
            input.value = value == null ? '' : String(value);
            input.readOnly = true;
            wrap.appendChild(input);
        } else if (col.type === 'file') {
            // Backlog-style drop zone: dashed border box with a hidden
            // <input type="file"> and a hint. Upload still goes through
            // the existing controller path; here we just surface the
            // current attachment name and let the user swap it locally.
            var drop = document.createElement('div');
            drop.className = 'pp-ne-drop';
            var hint = document.createElement('div');
            hint.className = 'pp-ne-drop-hint';
            hint.textContent = value
                ? String(value)
                : nedGetLangKey('ned_files_drop_hint');
            input = document.createElement('input');
            input.type = 'file'; input.className = 'pp-ne-in';
            if (readonly) { input.disabled = true; }
            drop.appendChild(hint);
            drop.appendChild(input);
            wrap.appendChild(drop);
            // Clicking anywhere in the drop zone opens the picker.
            drop.addEventListener('click', function (ev) {
                if (readonly) { return; }
                if (ev.target !== input) { input.click(); }
            });
            input.addEventListener('change', function () {
                var f = input.files && input.files[0];
                if (f) { hint.textContent = f.name; }
            });
        } else {
            input = document.createElement('input');
            input.className = 'pp-ne-in';
            input.type = col.type === 'email' ? 'email' : (col.type === 'url' ? 'url' : 'text');
            input.value = value == null ? '' : String(value);
            if (col.placeholder) { input.placeholder = col.placeholder; }
            if (readonly) { input.readOnly = true; }
            wrap.appendChild(input);
        }

        var helpText = nedFieldHelp(col);
        if (helpText) {
            var help = document.createElement('div'); help.className = 'help'; help.textContent = helpText;
            wrap.appendChild(help);
            // In the 基本情報 card the help is a (?) beside the name, shown
            // on hover or focus, as Backlog does: two lines of help under
            // every field made the card twice as tall (CSS picks which).
            var q = document.createElement('span');
            q.className = 'pp-ne-q';
            q.tabIndex = 0;
            q.setAttribute('role', 'note');
            q.setAttribute('aria-label', helpText);
            q.textContent = '?';
            var tip = document.createElement('span'); tip.className = 'pp-ne-tip'; tip.textContent = helpText;
            q.appendChild(tip);
            lbl.appendChild(q);
        }
        var err = document.createElement('div'); err.className = 'err'; err.hidden = true;
        wrap.appendChild(err);
        return wrap;
    }

    // Turn a rendered field back into the value we send in the PATCH body.
    function nedCollect(col) {
        var el = nedInputEl(col.name);
        if (!el) { return undefined; }
        if (col.type === 'boolean') { return el.checked ? 1 : 0; }
        if (el.tagName === 'SELECT' && el.multiple) {
            var out = [];
            Array.prototype.forEach.call(el.options, function (o) { if (o.selected && o.value) { out.push(o.value); } });
            return out;
        }
        if (el.tagName === 'SELECT') { return el.value || null; }
        return el.value;
    }
    function nedSerialize() {
        if (!NED.columns) { return ''; }
        var snap = {};
        NED.columns.forEach(function (c) {
            if (c.readonly) { return; }
            snap[c.name] = nedCollect(c);
        });
        return JSON.stringify(snap);
    }
    // Only ship the fields the user actually changed - the API supports
    // partial updates, and there is no reason to overwrite a co-worker's
    // concurrent tweak to a field we didn't touch.
    function nedBuildDiff() {
        var diff = {};
        var baseline = NED.initial ? JSON.parse(NED.initial) : NED.values;
        NED.columns.forEach(function (c) {
            if (c.readonly) { return; }
            var now = nedCollect(c);
            var was = baseline[c.name];
            var eq = false;
            if (Array.isArray(now) && Array.isArray(was)) {
                eq = now.length === was.length && now.every(function (v, i) { return String(v) === String(was[i]); });
            } else if (now == null && (was == null || was === '')) {
                eq = true;
            } else {
                eq = String(now == null ? '' : now) === String(was == null ? '' : was);
            }
            if (!eq) { diff[c.name] = now; }
        });
        return diff;
    }

    // ... but a diff needs something to diff against, and on create there is
    // no row on the server to leave alone. The form is not empty either: it
    // opens on the project's first status and first issue type, exactly as
    // Backlog's does, and those are the values the diff calls unchanged and
    // throws away. What arrived was the subject the user typed and nothing
    // else - an issue with no status, which belongs to no board column, so
    // the card was simply not on the board.
    //
    // Empty fields are still left out. A column the user did not fill has
    // nothing to say, and posting "" for every one of them would hand the
    // server a date column an empty string to parse.
    function nedBuildCreate() {
        var out = {};
        NED.columns.forEach(function (c) {
            if (c.readonly) { return; }
            var now = nedCollect(c);
            if (now === undefined || now === null || now === '') { return; }
            if (Array.isArray(now) && !now.length) { return; }
            out[c.name] = now;
        });
        return out;
    }

    // Phase B: Backlog-style formatting toolbar above the description
    // textarea. Buttons wrap the current selection with markdown
    // syntax so keyboard users can format without leaving the field.
    // Fill subject and description from the picked type's template. Tracks
    // what the template last wrote, so switching the type replaces its own
    // text - or clears it, for a type with none - but never text the user
    // typed over it.
    function nedBindTypeTemplate(tpl, titleCol, descCol) {
        var typeEl = nedInputEl(tpl.column);
        if (!typeEl) { return; }
        var applied = { s: null, d: null };
        function applyOne(col, key) {
            var el = col ? nedInputEl(col.name) : null;
            if (!el || el.readOnly || el.disabled) { return; }
            var row = tpl.rows[String(typeEl.value || '')] || {};
            var next = String(row[key] || '');
            var cur = String(el.value || '');
            if (cur !== '' && cur !== applied[key]) { return; }
            applied[key] = next;
            if (cur === next) { return; }
            el.value = next;
            el.dispatchEvent(new Event('input', { bubbles: true }));
        }
        function applyBoth() { applyOne(titleCol, 's'); applyOne(descCol, 'd'); }
        typeEl.addEventListener('change', applyBoth);
        applyBoth();
    }

    var NED_EMOJI = [
        '😀', '😄', '😅', '😊', '😉', '🙂', '😍', '🤔',
        '😐', '😑', '😢', '😭', '😱', '😡', '🙏', '👍',
        '👎', '👌', '👏', '🙌', '💪', '🎉', '🔥', '✨',
        '⭐', '❤️', '✅', '❌', '⚠️', '❓', '❗', '💡',
        '📌', '📝', '📎', '📅', '⏰', '🐛', '🚀', '🍀'
    ];
    // A markdown table nobody wants to type out by hand. Two columns is the
    // shape most tables start as; adding a third is one more pipe.
    var NED_TABLE_SNIPPET = '\n| A | B |\n| --- | --- |\n|  |  |\n';

    // execCommand is deprecated and is still the only way to change a
    // textarea while leaving the browser's undo stack standing. Assigning
    // .value is the fallback, and costs the writer every undo step they had
    // built up - which is why it is the fallback and not the method.
    function nedMdReplace(ta, from, to, text) {
        ta.focus();
        ta.setSelectionRange(from, to);
        var ok = false;
        try {
            ok = text === '' ? document.execCommand('delete') : document.execCommand('insertText', false, text);
        } catch (e) { ok = false; }
        if (ok) { return; }
        var v = ta.value;
        ta.value = v.slice(0, from) + text + v.slice(to);
        ta.selectionStart = ta.selectionEnd = from + text.length;
        ta.dispatchEvent(new Event('input', { bubbles: true }));
    }
    // Enter at the end of a list item makes the next one. An item left empty
    // is the writer saying the list is over, so the marker they just made
    // goes away instead. Returns whether the newline has been dealt with.
    function nedMdContinueList(ta) {
        if (ta.selectionStart !== ta.selectionEnd) { return false; }
        var at = ta.selectionStart, v = ta.value;
        var from = v.lastIndexOf('\n', at - 1) + 1;
        var line = v.slice(from, at);
        var m = line.match(/^(\s*)(?:([-*])\s(\[[ xX]\]\s)?|(\d+)\.\s)/);
        if (!m) { return false; }
        if (line.length === m[0].length) {
            nedMdReplace(ta, from, at, '');
            return true;
        }
        var marker = m[2] ? (m[2] + ' ' + (m[3] ? '[ ] ' : '')) : (String(Number(m[4]) + 1) + '. ');
        nedMdReplace(ta, at, at, '\n' + m[1] + marker);

        return true;
    }
    function nedMdWrap(ta, open, close) {
        var s = ta.selectionStart, e = ta.selectionEnd;
        var sel = ta.value.slice(s, e);
        nedMdReplace(ta, s, e, open + sel + close);
        ta.selectionStart = s + open.length;
        ta.selectionEnd = s + open.length + sel.length;
    }
    // The three shortcuts every editor has, plus the list carried on. Bound
    // once the field exists, which is after the toolbar above it was built.
    var NED_MD_KEYS = { b: ['**', '**'], i: ['*', '*'], k: ['[', '](url)'] };
    function nedBindMarkdownKeys(fg) {
        var ta = fg.querySelector('textarea.pp-ne-in');
        if (!ta || ta.readOnly) { return; }
        ta.addEventListener('keydown', function (ev) {
            if (ev.key === 'Enter') {
                if (ev.shiftKey || ev.ctrlKey || ev.metaKey || ev.altKey) { return; }
                if (nedMdContinueList(ta)) { ev.preventDefault(); }
                return;
            }
            if (!(ev.ctrlKey || ev.metaKey) || ev.altKey) { return; }
            var wrap = NED_MD_KEYS[String(ev.key || '').toLowerCase()];
            if (!wrap) { return; }
            ev.preventDefault();
            nedMdWrap(ta, wrap[0], wrap[1]);
        });
    }
    // Errors from a refused save are shown against the field, and focus is
    // put on it. Neither works while the preview is covering it.
    function nedLeavePreview() {
        var row = NED.el && NED.el.querySelector('.pp-ne-desc-row.previewing');
        var tab = row && row.querySelector('.pp-ne-desc-head .tab[data-tab="edit"]');
        if (tab) { tab.click(); }
    }

    function nedInjectDescToolbar(descRow, col) {
        // Idempotent: skip if we already injected once for this open. The head
        // is the part every reader gets; the toolbar may have been left out.
        if (descRow.querySelector('.pp-ne-desc-head')) { return; }
        var bar = document.createElement('div');
        bar.className = 'pp-ne-desc-toolbar';
        // The pop-outs share one box: only one of them can be open anyway,
        // and a second click on the same button closes it.
        var pop = document.createElement('div');
        pop.className = 'pp-ne-tpop';
        pop.hidden = true;
        var popKind = null;
        var closePop = function () { pop.hidden = true; popKind = null; };

        var textarea = function () { return descRow.querySelector('textarea.pp-ne-in'); };
        // Replaces the selection and leaves the caret where the writer will
        // carry on typing, which for a table is the first data cell.
        var insertText = function (text, back) {
            var ta = textarea();
            if (!ta) { return; }
            var s = ta.selectionStart;
            nedMdReplace(ta, s, ta.selectionEnd, text);
            var caret = s + text.length - (back || 0);
            ta.selectionStart = ta.selectionEnd = caret;
            ta.focus();
        };

        var fillEmoji = function () {
            var grid = document.createElement('div');
            grid.className = 'emo';
            NED_EMOJI.forEach(function (ch) {
                var b = document.createElement('button');
                b.type = 'button';
                b.textContent = ch;
                b.addEventListener('click', function () { insertText(ch); closePop(); });
                grid.appendChild(b);
            });
            pop.appendChild(grid);
        };
        // Writing the name is half of it. The other half is that the person
        // named should hear about it, which on this table is the notify
        // column - so the mention fills it in and says so, rather than
        // leaving a name in the text that reaches nobody.
        var fillMention = function () {
            var ids = Object.keys(D.users || {});
            if (!ids.length) { pop.appendChild(nedPopEmpty()); return; }
            var notify = (D.issue_extra_cols || {}).notified_users;
            ids.sort(function (a, b) { return cmpText(user(a).n, user(b).n); }).forEach(function (id) {
                var b = document.createElement('button');
                b.type = 'button';
                b.className = 'row';
                b.textContent = '@' + user(id).n;
                b.addEventListener('click', function () {
                    insertText('@' + user(id).n + ' ');
                    var picker = notify && NED._pickers ? NED._pickers[notify] : null;
                    if (picker) {
                        picker.add(String(id), user(id).n);
                        toast(String(nedGetLangKey('md_mention_added')).replace(':name', user(id).n));
                    }
                    closePop();
                });
                pop.appendChild(b);
            });
        };
        var fillFile = function () {
            var rows = (D.files && D.files.rows) || [];
            if (!rows.length) { pop.appendChild(nedPopEmpty()); return; }
            rows.forEach(function (f) {
                var b = document.createElement('button');
                b.type = 'button';
                b.className = 'row';
                b.textContent = f.name;
                b.addEventListener('click', function () {
                    insertText(mdFileLink(f.name, f.url, false));
                    closePop();
                });
                pop.appendChild(b);
            });
        };
        var fillHelp = function () {
            var rules = [
                ['# ', L.md_head],
                ['**' + nedGetLangKey('md_bold') + '**', L.md_bold],
                ['*' + nedGetLangKey('md_italic') + '*', L.md_italic],
                ['~~' + nedGetLangKey('md_strike') + '~~', L.md_strike],
                ['- ', L.md_list],
                ['1. ', L.md_list_ordered],
                ['- [ ] ', L.md_list_check],
                ['> ', L.md_quote],
                ['`code`', L.md_code],
                ['[text](url)', L.md_link],
                ['| a | b |', L.md_table],
                ['---', L.md_rule]
            ];
            var table = document.createElement('table');
            rules.forEach(function (r) {
                var tr = document.createElement('tr');
                var td1 = document.createElement('td');
                var code = document.createElement('code');
                code.textContent = r[0];
                td1.appendChild(code);
                var td2 = document.createElement('td');
                td2.textContent = r[1];
                tr.appendChild(td1);
                tr.appendChild(td2);
                table.appendChild(tr);
            });
            pop.appendChild(table);
        };
        var openPop = function (kind, anchor) {
            if (popKind === kind) { closePop(); return; }
            pop.innerHTML = '';
            popKind = kind;
            if (kind === 'emoji') { fillEmoji(); }
            else if (kind === 'mention') { fillMention(); }
            else if (kind === 'file') { fillFile(); }
            else { fillHelp(); }
            pop.hidden = false;
            pop.style.left = Math.max(0, anchor.offsetLeft) + 'px';
        };

        // Backlog splits the description in two: a tab the writer types in
        // and a tab that shows what they typed. Markdown is only half
        // readable as source - a table is not readable at all until it is
        // drawn - and there was no way to see it here short of saving.
        var head = document.createElement('div');
        head.className = 'pp-ne-desc-head';
        var nm = document.createElement('span');
        nm.className = 'nm';
        // The field's own label, moved up out of the box so that the toolbar
        // sits directly on the thing it writes into.
        nm.textContent = (col && (col.label || col.name)) || '';
        head.appendChild(nm);
        var tabs = document.createElement('div');
        tabs.className = 'tabs';
        var prev = document.createElement('div');
        prev.className = 'pp-ne-desc-prev bl-md';
        prev.hidden = true;
        var tabEdit = null, tabPrev = null;
        var showPreview = function (on) {
            if (on) {
                closePop();
                var ta = textarea();
                prev.innerHTML = renderRichText(ta ? ta.value : '') ||
                    '<p style="color:var(--bl-faint);">' + esc(nedGetLangKey('md_prev_empty')) + '</p>';
                linkifyIssueRefs(prev);
                // Last in the row, so it stands where the field stood.
                if (!prev.isConnected) { descRow.appendChild(prev); }
            }
            prev.hidden = !on;
            descRow.classList.toggle('previewing', !!on);
            tabEdit.classList.toggle('on', !on);
            tabPrev.classList.toggle('on', !!on);
            if (!on) { var back = textarea(); if (back) { back.focus(); } }
        };
        var mkTab = function (kind, label) {
            var t = document.createElement('button');
            t.type = 'button';
            t.className = 'tab';
            t.dataset.tab = kind;
            t.textContent = label;
            t.addEventListener('click', function (ev) { ev.preventDefault(); showPreview(kind === 'prev'); });
            tabs.appendChild(t);

            return t;
        };
        tabEdit = mkTab('edit', nedGetLangKey('md_tab_edit'));
        tabPrev = mkTab('prev', nedGetLangKey('md_tab_prev'));
        tabEdit.classList.add('on');
        head.appendChild(tabs);
        descRow.appendChild(head);
        // readOnly stops typing, not these buttons: they write into the field
        // from script. A reader who may not change it keeps the preview and
        // gets nothing to format with. Same rule as nedRenderField.
        if (!NED.canEdit || (col && col.readonly)) { return; }

        var actions = [
            { label: 'H', title: L.md_head, line: '## ' },
            { label: 'B', title: L.md_bold, wrap: ['**', '**'] },
            { label: 'I', title: L.md_italic, wrap: ['*', '*'] },
            { label: 'S', title: L.md_strike, wrap: ['~~', '~~'] },
            { sep: 1 },
            { label: '•', title: L.md_list, line: '- ' },
            { label: '1.', title: L.md_list_ordered, line: '1. ' },
            { label: '☐', title: L.md_list_check, line: '- [ ] ' },
            { sep: 1 },
            { label: '❝', title: L.md_quote, line: '> ' },
            { label: '</>', title: L.md_code, wrap: ['`', '`'] },
            { label: '🔗', title: L.md_link, wrap: ['[', '](url)'] },
            { label: '⊞', title: L.md_table, insert: NED_TABLE_SNIPPET, back: 6 },
            { sep: 1 },
            { label: '@', title: L.md_mention, pop: 'mention' },
            { label: '☺', title: L.md_emoji, pop: 'emoji' },
            { label: '📎', title: L.md_file, pop: 'file' },
            { label: '?', title: L.md_help, pop: 'help' }
        ];
        actions.forEach(function (a) {
            if (a.sep) { var s = document.createElement('span'); s.className = 'tsep'; bar.appendChild(s); return; }
            var b = document.createElement('button');
            b.type = 'button'; b.className = 'tbtn'; b.title = a.title; b.textContent = a.label;
            b.addEventListener('click', function (ev) {
                ev.preventDefault();
                if (a.pop) { openPop(a.pop, b); return; }
                closePop();
                if (a.insert) { insertText(a.insert, a.back); return; }
                var ta = textarea();
                if (!ta) { return; }
                var s = ta.selectionStart, e = ta.selectionEnd, v = ta.value;
                if (a.wrap) {
                    nedMdWrap(ta, a.wrap[0], a.wrap[1]);
                } else if (a.line) {
                    // The prefix goes at the head of every selected line, or
                    // of the line the caret is on when nothing is selected.
                    var lineStart = v.lastIndexOf('\n', s - 1) + 1;
                    var lineEnd = v.indexOf('\n', e);
                    if (lineEnd < 0) { lineEnd = v.length; }
                    var prefixed = v.slice(lineStart, lineEnd).split('\n').map(function (ln) {
                        return a.line + ln;
                    }).join('\n');
                    nedMdReplace(ta, lineStart, lineEnd, prefixed);
                    ta.selectionStart = ta.selectionEnd = s + a.line.length;
                }
                ta.focus();
            });
            bar.appendChild(b);
        });
        bar.appendChild(pop);
        // A click anywhere else is the usual way out of a pop-out. The form
        // is torn down and rebuilt per open, so the listener has to let go
        // when the page it belongs to does.
        document.addEventListener('click', function onTpopOutside(ev) {
            if (!alive() || !bar.isConnected) {
                document.removeEventListener('click', onTpopOutside);
                return;
            }
            if (!bar.contains(ev.target)) { closePop(); }
        });
        descRow.appendChild(bar);
    }
    function nedPopEmpty() {
        var em = document.createElement('div');
        em.className = 'empty';
        em.textContent = nedGetLangKey('lkp_empty');

        return em;
    }

    // Phase B: live char counter below the description textarea. The limit
    // comes from col.max_length when present, or falls back to 2000 which
    // matches the default Exment text-column length.
    function nedAttachCharCount(fg) {
        var ta = fg.querySelector('textarea.pp-ne-in');
        if (!ta) { return; }
        var col = NED.columns.filter(function (c) { return c.name === fg.dataset.fname; })[0];
        var max = (col && col.max_length) ? col.max_length : 2000;
        var box = document.createElement('div');
        box.className = 'pp-ne-charcount';
        var n = document.createElement('span'); n.className = 'n'; n.textContent = String((ta.value || '').length);
        var slash = document.createTextNode(' / ');
        var m = document.createElement('span'); m.className = 'max'; m.textContent = String(max);
        box.appendChild(n); box.appendChild(slash); box.appendChild(m);
        fg.appendChild(box);
        var upd = function () {
            var len = (ta.value || '').length;
            n.textContent = String(len);
            box.classList.toggle('warn', len >= max * 0.8 && len < max);
            box.classList.toggle('over', len >= max);
        };
        ta.addEventListener('input', upd);
        upd();
    }

    // Phase B: on-blur required-field check. Empties get a red border and
    // an inline error message; typing anything clears both. Save is also
    // gated by nedValidate() before firing the AJAX.
    function nedValidateField(fg) {
        if (!fg || !fg.dataset || !fg.dataset.fname) { return true; }
        // The chip picker validates 150ms after its search box loses focus,
        // so leaving the box by clicking save lands this call after the form
        // has closed and dropped its columns. There is nothing left to check
        // then, and throwing here kills whatever else was queued behind it.
        if (!NED.columns || !NED.columns.filter) { return true; }
        var col = NED.columns.filter(function (c) { return c.name === fg.dataset.fname; })[0];
        if (!col || !col.required || col.readonly) { return true; }
        var val = nedCollect(col);
        var isEmpty = (val == null) || val === '' || (Array.isArray(val) && val.length === 0);
        var errEl = fg.querySelector('.err');
        if (isEmpty) {
            fg.classList.add('err');
            nedRevealField(fg);
            if (errEl) { errEl.textContent = nedGetLangKey('ned_required'); errEl.hidden = false; }
            return false;
        }
        fg.classList.remove('err');
        if (errEl) { errEl.hidden = true; errEl.textContent = ''; }
        return true;
    }
    function nedValidate() {
        var ok = true;
        NED.el.querySelectorAll('.pp-ne-fg').forEach(function (fg) {
            if (!nedValidateField(fg)) { ok = false; }
        });
        return ok;
    }

    // Phase B: colored bullet next to a coloured single-value <select>.
    // We wrap the <select> in .pp-ne-sel-wrap and drop a small dot span
    // that reads the option's color on change. Non-coloured selects are
    // untouched (dot stays hidden).
    function nedAttachSelectDot(fg) {
        var sel = fg.querySelector('select.pp-ne-in');
        if (!sel || sel.multiple) { return; }
        var hasColor = Array.prototype.some.call(sel.options, function (o) { return o.style && o.style.color; });
        if (!hasColor) { return; }
        var wrap = document.createElement('div'); wrap.className = 'pp-ne-sel-wrap';
        var dot = document.createElement('span'); dot.className = 'pp-ne-sel-dot';
        sel.parentNode.insertBefore(wrap, sel);
        wrap.appendChild(dot);
        wrap.appendChild(sel);
        var upd = function () {
            var o = sel.options[sel.selectedIndex];
            var c = o && o.style && o.style.color;
            if (c) { dot.style.setProperty('--sel-c', c); dot.classList.remove('hidden'); }
            else { dot.classList.add('hidden'); }
        };
        sel.addEventListener('change', upd);
        upd();
    }

    // keepDraft is set when this render IS the draft restore, so the bar does
    // not turn round and offer to restore what it just restored, and so the
    // pristine server payload stays available for a second restore.
    // 「その他の項目」 (1.7.14): the issue fields Backlog does not show, folded.
    // Open when one of them holds something (an issue with a reviewer shows
    // its reviewer) or when its owner left it open last time.
    var NED_MORE_KEY = 'pp_ne_more_open';
    function nedMoreRemembered() {
        try { return window.localStorage.getItem(NED_MORE_KEY) === '1'; } catch (e) { return false; }
    }
    function nedMoreRemember(open) {
        try { window.localStorage.setItem(NED_MORE_KEY, open ? '1' : '0'); } catch (e) { /* private window */ }
    }
    function nedHasValue(v) {
        if (v == null || v === '' || v === 0 || v === '0') { return false; }
        if (Array.isArray(v)) { return v.length > 0; }
        if (typeof v === 'object') { return Object.keys(v).length > 0; }
        return true;
    }
    function nedMoreSetOpen(box, btn, open) {
        box.hidden = !open;
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        btn.classList.toggle('open', open);
    }
    function nedMountMore(grid, box) {
        var names = Array.prototype.map.call(box.querySelectorAll('[data-fname]'), function (el) { return el.dataset.fname; });
        var filled = names.filter(function (n) { return nedHasValue(NED.values[n]); }).length;
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'pp-ne-more-btn';
        btn.innerHTML = '<i class="fa fa-chevron-right"></i> ' + esc(nedGetLangKey('ned_more', 'その他の項目')) +
            ' <span class="n">' + names.length + '</span>' +
            (filled ? ' <span class="filled">' + esc(String(nedGetLangKey('ned_more_filled', ':n 件入力あり')).replace(':n', filled)) + '</span>' : '');
        btn.addEventListener('click', function () {
            var open = box.hidden;
            nedMoreSetOpen(box, btn, open);
            nedMoreRemember(open);
        });
        grid.appendChild(btn);
        grid.appendChild(box);
        nedMoreSetOpen(box, btn, filled > 0 || nedMoreRemembered());
    }
    // A field with an error must be seen: open the fold it is in.
    function nedRevealField(fg) {
        var box = fg && fg.closest ? fg.closest('.pp-ne-more') : null;
        if (!box || !box.hidden) { return; }
        var btn = box.previousElementSibling;
        if (btn && btn.classList.contains('pp-ne-more-btn')) { nedMoreSetOpen(box, btn, true); } else { box.hidden = false; }
    }
    function nedRenderForm(payload, keepDraft) {
        var epoch = NED.epoch;
        if (!keepDraft) { NED._payload = payload; }
        var form = NED.el.querySelector('.pp-ne-form');
        var slots = nedEnsureLayout();
        NED.columns = payload.columns || [];
        NED.values = (payload.row && payload.row.value) || {};
        NED.labels = (payload.row && payload.row.labels) || {};
        NED.saveUrl = payload.save && payload.save.url;
        NED.saveMethod = (payload.save && payload.save.method) || 'PATCH';
        NED.canEdit = !!(payload.permissions && payload.permissions.can_edit);
        // The version this form was drawn from. Sent back on save so the
        // server can refuse to write it over a newer one - and kept with any
        // draft typed on top of it, for the same reason (nedDraftSave).
        NED.rowUpdatedAt = (payload.row && payload.row.updated_at) || null;
        NED.draftBaseAt = NED.rowUpdatedAt;
        NED._pickers = {};

        // Kick off every lookup fetch in parallel BEFORE building the
        // field DOM. Without this the per-field nedField() calls fire
        // their nedFetchLookup() sequentially as they're appended — so
        // N select_table columns = N serial round-trips on first render.
        // Because nedFetchLookup dedupes both cache and in-flight, the
        // per-field .then() below reuses these same promises for free.
        NED.columns.forEach(function (c) {
            if (c && c.lookup_url && !nedIsHiddenColumn(c)) { nedFetchLookup(c.lookup_url); }
        });

        var isCreate = NED.mode === 'create';
        if (isCreate) {
            NED.el.querySelector('.pp-ne-key').textContent = nedGetLangKey('ned_new');
            NED.el.querySelector('.pp-ne-t').textContent = (payload.table && payload.table.label) || '';
        } else {
            // The portal calls an issue PORTAL-13 in the list, the detail
            // head, links and the search box, and this chip copies what it
            // shows - so it must not show the template's auto-number
            // (ISS00013) instead. An issue this page has not loaded (one
            // of another project, opened from a link) keeps the old label.
            var knownIssue = NED.table === D.issue_table && payload.row ? issueById(payload.row.id) : null;
            NED.el.querySelector('.pp-ne-key').textContent = (knownIssue && knownIssue.key)
                || (payload.row && payload.row.value && payload.row.value.issue_key) || ('#' + (payload.row ? payload.row.id : ''));
            NED.el.querySelector('.pp-ne-t').textContent = payload.row ? payload.row.label : '';
        }
        nedRenderMetaStrip(payload);

        var visibleCols = NED.columns.filter(function (c) {
            return !nedIsHiddenColumn(c) && !nedSkipOnCreate(c);
        });
        var titleCol = nedPickHeroColumn(visibleCols);
        var descCol = nedPickDescColumn(visibleCols);
        var fileCol = nedPickFileColumn(visibleCols);

        // Title, description and file have slots of their own; everything
        // else waits in metaOrder for the layout below to place it.
        var built = {};
        var metaOrder = [];
        visibleCols.forEach(function (c) {
            var el = nedField(c, NED.values[c.name], NED.labels);
            built[c.name] = el;
            if (titleCol && c.name === titleCol.name) {
                el.classList.add('pp-ne-fg-title');
                var heroIn = el.querySelector('.pp-ne-in');
                if (heroIn && !heroIn.readOnly && !heroIn.value) {
                    heroIn.placeholder = nedGetLangKey('ned_hero_ph');
                }
                slots.title.appendChild(el);
            } else if (descCol && c.name === descCol.name) {
                nedInjectDescToolbar(slots.desc, descCol);
                slots.desc.appendChild(el);
                nedAttachCharCount(el);
                nedBindMarkdownKeys(el);
                slots.desc.hidden = false;
            } else if (fileCol && c.name === fileCol.name) {
                slots.files.appendChild(el);
                slots.files.hidden = false;
            } else {
                metaOrder.push(c.name);
            }
        });

        var layout = nedIssueLayout();
        if (layout) {
            var taken = {};
            var waiting = function (name) {
                return !!name && !!built[name] && !taken[name] && metaOrder.indexOf(name) >= 0;
            };
            layout.top.forEach(function (name) {
                if (!waiting(name)) { return; }
                slots.top.appendChild(built[name]);
                slots.top.hidden = false;
                taken[name] = true;
            });
            // The first field of a pair starts a row of the two-column
            // card, so a missing field leaves a gap rather than pulling
            // every field after it into the wrong pair.
            var place = function (rows, into) {
                rows.forEach(function (pair) {
                    var first = true;
                    pair.forEach(function (name) {
                        if (!waiting(name)) { return; }
                        built[name].classList.toggle('pp-ne-rowstart', first);
                        first = false;
                        into.appendChild(built[name]);
                        taken[name] = true;
                    });
                });
            };
            place(layout.rows, slots.grid);
            var moreBox = document.createElement('div');
            moreBox.className = 'pp-ne-more';
            place(layout.more, moreBox);
            // Any other column of the issue table (added since, or the
            // imported issue's source) is folded with them.
            metaOrder.filter(function (name) { return !taken[name]; }).forEach(function (name) {
                moreBox.appendChild(built[name]);
                taken[name] = true;
            });
            if (moreBox.children.length) { nedMountMore(slots.grid, moreBox); }
            metaOrder = metaOrder.filter(function (name) { return !taken[name]; });
        }
        // Whatever the layout did not name: every column of every other table.
        // These keep the old behaviour and follow in column order.
        metaOrder.forEach(function (name) { slots.grid.appendChild(built[name]); });

        // A type may bring a template subject and description (Backlog's
        // per-type issue template). Only when creating: an existing issue's
        // text belongs to whoever wrote it, not to the type. Applied before
        // the snapshot below, so an untouched prefilled form is still clean.
        if (isCreate && payload.templates && payload.templates.rows) {
            nedBindTypeTemplate(payload.templates, titleCol, descCol);
        }

        // The AI button sits beside the subject; a button is not a field, so
        // the clean snapshot below is unaffected.
        nedAiMount(slots, titleCol, descCol);

        NED.initial = nedSerialize();
        // A fresh paint is by definition clean; drop any guard left over from
        // the previous row so opening a second record does not inherit it.
        nedGuard(false);
        if (!keepDraft) { nedDraftOffer(); }
        NED.el.querySelector('.pp-ne-spin').hidden = true;
        form.hidden = false;
        NED.el.querySelector('.pp-ne-foot').hidden = false;
        NED.el.querySelectorAll('.pp-ne-save').forEach(function (b) { b.disabled = !NED.canEdit; });
        // Push post-paint work (colored-dot decoration, blur/input
        // validators) into a rAF so the browser can paint the form once
        // and the user sees fields immediately; the decoration lands on
        // the very next frame (~16ms later) without blocking the paint.
        var attachDeco = function () {
            if (!nedCurrent(epoch)) { return; }
            NED.el.querySelectorAll('.pp-ne-form .pp-ne-fg').forEach(function (fg) {
                nedAttachSelectDot(fg);
                var inputs = fg.querySelectorAll('.pp-ne-in');
                inputs.forEach(function (input) {
                    input.addEventListener('blur', function () { nedValidateField(fg); });
                    input.addEventListener('input', function () {
                        if (fg.classList.contains('err')) { nedValidateField(fg); }
                    });
                });
                // A chip picker's value carrier is hidden and so never
                // blurs; its search box is the part the user leaves.
                var pq = fg.querySelector('.pp-lkp-q');
                if (pq) {
                    pq.addEventListener('blur', function () {
                        setTimeout(function () {
                            if (nedCurrent(epoch) && fg.isConnected) { nedValidateField(fg); }
                        }, 150);
                    });
                }
            });
            nedUpdateChangeCount();
        };
        if (typeof requestAnimationFrame === 'function') {
            requestAnimationFrame(attachDeco);
        } else {
            attachDeco();
        }
        if (!NED.canEdit) {
            nedShowAlert((payload.permissions && payload.permissions.edit_denied) || nedGetLangKey('ned_no_edit'), false);
        } else {
            nedShowAlert('', false);
        }
        nedFocusFirstField();
    }

