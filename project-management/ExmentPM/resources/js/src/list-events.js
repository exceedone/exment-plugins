    /* ---- paging ---- */
    document.getElementById('pp-pg-nav').addEventListener('click', function (ev) {
        var b = ev.target.closest('button[data-pg]');
        if (!b || b.disabled) { return; }
        PAGE.no = parseInt(b.getAttribute('data-pg'), 10) || 1;
        // Only the page moved, so the filter stays and only the ticks - which
        // named rows on the page being left - are dropped.
        clearSelection();
        renderIssues();
        filterToUrl();
    });
    document.getElementById('pp-pg-size').addEventListener('change', function () {
        var size = parseInt(this.value, 10);
        PAGE.size = PAGE_SIZES.indexOf(size) >= 0 ? size : 50;
        try { localStorage.setItem(PAGE_KEY, String(PAGE.size)); } catch (e) { /* ignore */ }
        PAGE.no = 1;
        clearSelection();
        renderIssues();
        filterToUrl();
    });

    /* ---- tree view ---- */
    // Folding changes which rows are listed, so the ticks go, the same as on
    // any other change of the list: a bulk edit must not reach a hidden row.
    function treeRerender(firstPage) {
        if (firstPage) { PAGE.no = 1; }
        treeSave();
        clearSelection();
        renderIssues();
    }
    (function () {
        var treeBtn = document.getElementById('pp-tree-btn');
        var treeOpen = document.getElementById('pp-tree-open');
        var treeClose = document.getElementById('pp-tree-close');
        if (treeBtn) {
            treeBtn.addEventListener('click', function () {
                TREE_ON = !TREE_ON;
                try { localStorage.setItem(TREE_LAST_KEY, TREE_ON ? '1' : '0'); } catch (e) { /* ignore */ }
                treeRerender(true);
            });
        }
        if (treeOpen) {
            treeOpen.addEventListener('click', function () {
                TREE_FOLD = {};
                treeRerender(true);
            });
        }
        if (treeClose) {
            treeClose.addEventListener('click', function () {
                treeRows(filteredIssues(), true).forEach(function (e) {
                    if (e.n > 0) { TREE_FOLD['i' + e.i.id] = true; }
                });
                treeRerender(true);
            });
        }
    })();
    // Right-click a row: open, or mark it read / unread like a mail. With
    // rows ticked for bulk edit, a ticked row's menu acts on all of them.
    // Shift + right-click keeps the browser's own menu.
    document.getElementById('pp-issue-rows').addEventListener('contextmenu', function (ev) {
        if (ev.shiftKey) { return; }
        var tr = ev.target.closest('tr[data-issue]');
        if (!tr || ev.target.closest('input, textarea, select, .pp-ied')) { return; }
        var id = tr.getAttribute('data-issue');
        var row = issueById(id);
        if (!row) { return; }
        ev.preventDefault();
        var ids = own(SEL, id) ? Object.keys(SEL).filter(function (k) { return own(SEL, k) && issueById(k); }) : [id];
        var anyUnread = ids.some(function (k) { return !!(issueById(k) || {}).unread; });
        var items = [];
        if (ids.length === 1) {
            items.push({ l: L.ctx_open, i: 'fa-external-link', f: function () { location.hash = issueHash(id); } });
        }
        items.push(anyUnread
            ? { l: L.ctx_read + (ids.length > 1 ? ' (' + ids.length + ')' : ''), i: 'fa-envelope-open-o', f: function () { ids.slice(0, 100).forEach(function (k) { markRead(k, true); }); } }
            : { l: L.ctx_unread + (ids.length > 1 ? ' (' + ids.length + ')' : ''), i: 'fa-envelope', f: function () { ids.slice(0, 100).forEach(function (k) { markRead(k, false); }); } });
        if ((D.issues || []).some(function (i) { return !!i.unread; })) {
            items.push({ l: L.ctx_read_all, i: 'fa-check-square-o', f: readAll });
        }
        ctxOpen(ev.clientX, ev.clientY, items);
    });
    document.getElementById('pp-issue-rows').addEventListener('click', function (ev) {
        var tgl = ev.target.closest('.pp-tgl');
        if (!tgl) { return; }
        // Not a way into the issue: the row's own click must not see this.
        ev.preventDefault();
        ev.stopPropagation();
        var id = tgl.getAttribute('data-tgl');
        if (treeFolded(id)) { delete TREE_FOLD['i' + id]; } else { TREE_FOLD['i' + id] = true; }
        treeRerender(false);
    });
    // ---- inline cell edit, like Exment's Excel-style grid (snoro 2026-11-18) ----
    // Double-click a status / type / priority / milestone / category / start
    // / due cell: it turns into a picker (dates use the portal's calendar).
    // Saved through the bulk-edit endpoint for this one issue, so the same
    // server checks apply (permission per row, values of this project);
    // Esc or clicking away cancels, a refused save flashes red.
    var ED_NAV_TIMER = null;
    function edAttr(f) { return BULK_ON ? ' data-ed="' + f + '" title="ダブルクリックで編集"' : ''; }
    var ED_SRC = { st: 'pp-bk-st', ty: 'pp-bk-ty', pr: 'pp-bk-pr', ms: 'pp-bk-ms', cat: 'pp-f-cat' };
    function edOptions(field) {
        var src = document.getElementById(ED_SRC[field]), out = [];
        if (!src) { return out; }
        Array.prototype.forEach.call(src.options, function (o) {
            if (o.value === '' || o.value === '__none__') { return; }
            out.push([o.value, o.textContent]);
        });
        return out;
    }
    function edCurrent(i, f) {
        if (f === 'cat') { return String((i.cat || [])[0] || ''); }
        return String(i[f] == null ? '' : i[f]);
    }
    function edFail(td, msg) {
        td.classList.remove('pp-ed-saving');
        td.classList.add('pp-ed-fail');
        toast(msg || L.bk_fail, true);
        setTimeout(function () { if (alive()) { renderIssues(); } }, 800);
    }
    function edSave(td, id, f, v) {
        var set = {};
        if (f === 'cat') { set.cat = v ? [v] : ''; } else { set[f] = v; }
        td.classList.add('pp-ed-saving');
        $.ajax({
            url: D.urls.bulk, type: 'POST', dataType: 'json',
            data: { ids: [id], set: set },
            headers: { 'X-CSRF-TOKEN': CSRF }
        }).done(function (res) {
            if (!alive()) { return; }
            if (!res || !res.ok || !res.updated) {
                var why = res && Array.isArray(res.failed) && res.failed[0] ? res.failed[0].message : null;
                edFail(td, why || (res && res.denied ? (L.bk_denied ? String(L.bk_denied).replace(':count', 1) : null) : null));
                return;
            }
            refreshData(true);
        }).fail(function (xhr) {
            if (alive()) { edFail(td, xhrErr(xhr, L.bk_fail)); }
        });
    }
    function edCell(td) {
        var tr = td.closest('tr[data-issue]');
        var id = tr && tr.getAttribute('data-issue');
        var i = id && issueById(id);
        if (!i || td.querySelector('.pp-ed-in')) { return; }
        var f = td.getAttribute('data-ed'), cur = edCurrent(i, f), done = false;
        function commit(v) {
            if (done) { return; }
            done = true;
            if (String(v) === cur) { renderIssues(); return; }
            edSave(td, id, f, v);
        }
        function cancel() { if (!done) { done = true; renderIssues(); } }
        td.textContent = '';
        if (f === 'sd' || f === 'dd') {
            var inp = document.createElement('input');
            inp.className = 'pp-ed-in';
            inp.value = cur;
            td.appendChild(inp);
            if (window.ppDateField) { window.ppDateField(inp, 'date'); }
            inp.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') { e.preventDefault(); commit(inp.value.trim()); }
                else if (e.key === 'Escape') { cancel(); }
            });
            inp.addEventListener('change', function () { commit(inp.value.trim()); });
            if (window.jQuery) { jQuery(inp).on('dp.change', function () { commit(inp.value.trim()); }); }
            inp.addEventListener('blur', function () {
                setTimeout(function () {
                    if (!done && !document.querySelector('.bootstrap-datetimepicker-widget')) { cancel(); }
                }, 200);
            });
            inp.focus();
            return;
        }
        var sel = document.createElement('select');
        sel.className = 'pp-ed-in';
        var opts = edOptions(f);
        if (f === 'ms' || f === 'cat') { opts.unshift(['', '（なし）']); }
        if (cur !== '' && !opts.some(function (o) { return o[0] === cur; })) { opts.unshift([cur, cur]); }
        opts.forEach(function (o) {
            var op = document.createElement('option');
            op.value = o[0];
            op.textContent = o[1];
            if (o[0] === cur) { op.selected = true; }
            sel.appendChild(op);
        });
        td.appendChild(sel);
        sel.addEventListener('change', function () { commit(sel.value); });
        sel.addEventListener('keydown', function (e) { if (e.key === 'Escape') { cancel(); } });
        sel.addEventListener('blur', function () { setTimeout(function () { if (!done) { cancel(); } }, 150); });
        sel.focus();
    }
    document.getElementById('pp-issue-rows').addEventListener('dblclick', function (ev) {
        if (!BULK_ON) { return; }
        var td = ev.target.closest('td[data-ed]');
        if (!td || ev.target.closest('.pp-tgl')) { return; }
        clearTimeout(ED_NAV_TIMER);
        ev.preventDefault();
        ev.stopPropagation();
        edCell(td);
    });

    // Double-click a ▼/▶: everything opens or closes (snoro 2026-11-18). The
    // two clicks before it have put this parent back as it was, so an open
    // one closes all and a closed one opens all.
    document.getElementById('pp-issue-rows').addEventListener('dblclick', function (ev) {
        var tgl = ev.target.closest('.pp-tgl');
        if (!tgl) { return; }
        ev.preventDefault();
        ev.stopPropagation();
        var btn = document.getElementById(treeFolded(tgl.getAttribute('data-tgl')) ? 'pp-tree-open' : 'pp-tree-close');
        if (btn) { btn.click(); }
    });

    /* ---- ticking rows ---- */
    document.getElementById('pp-issue-rows').addEventListener('change', function (ev) {
        var box = ev.target.closest('.pp-bchk');
        if (!box) { return; }
        var id = box.getAttribute('data-id');
        if (box.checked) { SEL[id] = true; } else { delete SEL[id]; }
        var row = box.closest('tr');
        if (row) { row.classList.toggle('sel', box.checked); }
        syncBulkBar();
    });
    document.getElementById('pp-chk-all').addEventListener('change', function () {
        // This page only. "Select everything in the project" would be a
        // different promise, and one the bulk limit could not keep.
        var on = this.checked;
        document.querySelectorAll('#pp-issue-rows .pp-bchk').forEach(function (box) {
            box.checked = on;
            var id = box.getAttribute('data-id');
            if (on) { SEL[id] = true; } else { delete SEL[id]; }
            var row = box.closest('tr');
            if (row) { row.classList.toggle('sel', on); }
        });
        syncBulkBar();
    });
    document.getElementById('pp-bk-apply').addEventListener('click', bulkApply);
    document.getElementById('pp-bk-cancel').addEventListener('click', function () {
        clearSelection();
        renderIssues();
    });

    /* ---- staying current ---- */
    document.getElementById('pp-projhead').addEventListener('click', function (ev) {
        if (ev.target.closest('.pp-refresh')) { refreshData(false); }
        var note = ev.target.closest('.bl-projnote');
        if (note) { note.setAttribute('aria-expanded', this.classList.toggle('note-open') ? 'true' : 'false'); }
    });
    // Coming back to the tab is the moment the page is most likely to be out
    // of date and the moment the reader is least likely to be mid-sentence.
    document.addEventListener('visibilitychange', function onPpVis() {
        if (!alive()) { document.removeEventListener('visibilitychange', onPpVis); return; }
        if (!document.hidden) { checkStamp(CHECK_GAP_FOCUS); }
    });
    window.addEventListener('focus', function onPpFocus() {
        if (!alive()) { window.removeEventListener('focus', onPpFocus); return; }
        checkStamp(CHECK_GAP_FOCUS);
    });
    document.getElementById('pp-f-csv').addEventListener('click', exportCsv);
    document.getElementById('pp-jump').addEventListener('input', function () {
        FILTER.kw = this.value;
        if (parseHash().path !== 'issues') {
            if (!this.value) { return; }
            // Switching to the list from another tab: the hash change re-routes,
            // and route() renders and mirrors the filter into the URL from there.
            location.hash = 'issues';
            return;
        }
        afterFilterChange();
    });
    document.getElementById('pp-bd-select').addEventListener('change', function () {
        renderBurndown(parseInt(this.value, 10) || 0);
    });
    document.getElementById('pp-file-input').addEventListener('change', function () { uploadFiles(this.files); });
    var drop = document.getElementById('pp-drop');
    ['dragover', 'dragenter'].forEach(function (name) {
        drop.addEventListener(name, function (ev) { ev.preventDefault(); drop.classList.add('hover'); });
    });
    ['dragleave', 'drop'].forEach(function (name) {
        drop.addEventListener(name, function (ev) { ev.preventDefault(); drop.classList.remove('hover'); });
    });
    drop.addEventListener('drop', function (ev) { uploadFiles(ev.dataTransfer.files); });
    // The drop zone exists only when the server would accept an upload: the
    // project is unarchived, this user may edit it, and attachments are on.
    // The server hands back no upload url otherwise, so a dropped file would
    // only meet a refusal. Read off the payload, never hardcoded.
    function syncUploadUi() {
        var can = !!(D.urls && D.urls.upload);
        if (drop) { drop.hidden = !can; }
        var input = document.getElementById('pp-file-input');
        if (input) { input.disabled = !can; }
    }

