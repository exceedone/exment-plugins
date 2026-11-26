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
        // the issue itself (ISS00361): a child, its parent (in the edit form), its link
        if (ids.length === 1) {
            if (CAN_ADD_ISSUE) {
                items.push({ l: L.ctx_add_child, i: 'fa-level-down', f: function () { openNcreate(D.issue_table, issuePrefill('child', id), 'child' + id); } });
            }
            if (row.editable !== false) {
                items.push({ l: L.ctx_change_parent, i: 'fa-sitemap', f: function () { openNedit(D.issue_table, id); } });
            }
            items.push({ l: L.ctx_copy_link, i: 'fa-link', f: function () { copyText(issuePageUrl(id), L.link_copied); } });
        }
        // the tree (ISS00361): this row, the rows under it, and all of them
        if (TREE_ON && ids.length === 1) {
            var below = treeBelow(id);
            if (below.length) {
                items.push(treeFolded(id)
                    ? { l: L.tree_ctx_open, i: 'fa-caret-down', f: function () { treeSet([id], false, id); } }
                    : { l: L.tree_ctx_close, i: 'fa-caret-right', f: function () { treeSet([id], true, id); } });
                if (below.length > 1) {
                    items.push({ l: L.tree_ctx_open_below, i: 'fa-angle-double-down', f: function () { treeSet(below, false, id); } });
                    items.push({ l: L.tree_ctx_close_below, i: 'fa-angle-double-right', f: function () { treeSet(below, true, id); } });
                }
            }
            items.push({ l: L.tree_ctx_open_all, i: 'fa-plus-square-o', f: function () { var b = document.getElementById('pp-tree-open'); if (b) { b.click(); } } });
            items.push({ l: L.tree_ctx_close_all, i: 'fa-minus-square-o', f: function () { var b = document.getElementById('pp-tree-close'); if (b) { b.click(); } } });
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
        treeSet([id], !treeFolded(id), id);
    });
    // ← closes, → opens a parent whose ▼/▶ has the focus; Enter on it toggles, as a click
    // (ISS00361). Up and down move between the toggles of the list.
    document.getElementById('pp-issue-rows').addEventListener('keydown', function (ev) {
        var tgl = ev.target.closest('.pp-tgl');
        if (!tgl || ev.altKey || ev.ctrlKey || ev.metaKey) { return; }
        var id = tgl.getAttribute('data-tgl');
        if (ev.key === 'ArrowLeft' || ev.key === 'ArrowRight') {
            ev.preventDefault();
            var fold = ev.key === 'ArrowLeft';
            if (treeFolded(id) !== fold) { treeSet([id], fold, id); }
        } else if (ev.key === 'ArrowDown' || ev.key === 'ArrowUp') {
            var all = Array.prototype.slice.call(document.querySelectorAll('#pp-issue-rows .pp-tgl'));
            var next = all[all.indexOf(tgl) + (ev.key === 'ArrowDown' ? 1 : -1)];
            if (next) { ev.preventDefault(); next.focus(); }
        }
    });
    // The Wiki tree (ISS00361): the same menu and keys as the issue tree.
    (function () {
        var wtree = document.getElementById('pp-wtree');
        if (!wtree) { return; }
        wtree.addEventListener('contextmenu', function (ev) {
            if (ev.shiftKey) { return; }
            var nd = ev.target.closest('[data-wiki]');
            if (!nd) { return; }
            ev.preventDefault();
            var wid = nd.getAttribute('data-wiki');
            var below = wikiBelow(wid);
            var items = [{ l: L.ctx_open, i: 'fa-external-link', f: function () { location.hash = 'wiki-' + wid; } }];
            if (below.length) {
                items.push(wikiFolded(wid)
                    ? { l: L.tree_ctx_open_w, i: 'fa-caret-down', f: function () { wikiSet([wid], false, wid); } }
                    : { l: L.tree_ctx_close_w, i: 'fa-caret-right', f: function () { wikiSet([wid], true, wid); } });
                if (below.length > 1) {
                    items.push({ l: L.tree_ctx_open_below, i: 'fa-angle-double-down', f: function () { wikiSet(below, false, wid); } });
                    items.push({ l: L.tree_ctx_close_below, i: 'fa-angle-double-right', f: function () { wikiSet(below, true, wid); } });
                }
            }
            items.push({ l: L.tree_ctx_open_all, i: 'fa-plus-square-o', f: function () { wikiSet(wikiBelow(null), false, wid); } });
            items.push({ l: L.tree_ctx_close_all, i: 'fa-minus-square-o', f: function () { wikiSet(wikiBelow(null), true, wid); } });
            ctxOpen(ev.clientX, ev.clientY, items);
        });
        wtree.addEventListener('keydown', function (ev) {
            var tgl = ev.target.closest('.pp-wtgl');
            if (!tgl || ev.altKey || ev.ctrlKey || ev.metaKey) { return; }
            var wid = tgl.getAttribute('data-wtgl');
            if (ev.key === 'ArrowLeft' || ev.key === 'ArrowRight') {
                ev.preventDefault();
                var fold = ev.key === 'ArrowLeft';
                if (wikiFolded(wid) !== fold) { wikiSet([wid], fold, wid); }
            } else if (ev.key === 'ArrowDown' || ev.key === 'ArrowUp') {
                var all = Array.prototype.slice.call(wtree.querySelectorAll('.pp-wtgl'));
                var next = all[all.indexOf(tgl) + (ev.key === 'ArrowDown' ? 1 : -1)];
                if (next) { ev.preventDefault(); next.focus(); }
            }
        });
    })();
    // The parents under a row, the row included when it has children: who "open / close all
    // below" changes.
    function treeBelow(id) {
        var kids = {};
        (D.issues || []).forEach(function (i) {
            if (i.parent) { (kids['i' + i.parent] = kids['i' + i.parent] || []).push(String(i.id)); }
        });
        var out = [], seen = {}, todo = [String(id)];
        while (todo.length) {
            var k = todo.shift();
            if (seen[k] || !kids['i' + k]) { continue; }
            seen[k] = true;
            out.push(k);
            todo = todo.concat(kids['i' + k]);
        }
        return out;
    }
    // Fold or unfold some parents, then put the focus back on the toggle of the row it was on.
    function treeSet(ids, fold, focusId) {
        ids.forEach(function (k) {
            if (fold) { TREE_FOLD['i' + k] = true; } else { delete TREE_FOLD['i' + k]; }
        });
        treeRerender(false);
        var again = focusId && document.querySelector('#pp-issue-rows .pp-tgl[data-tgl="' + String(focusId).replace(/[^0-9A-Za-z_-]/g, '') + '"]');
        if (again) { again.focus(); }
    }
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
    // ISS00275: CSV or Excel, every column or the list's; the server makes the file
    document.getElementById('pp-f-csv').addEventListener('click', function (ev) {
        var r = this.getBoundingClientRect();
        ev.preventDefault();
        ctxOpen(r.left, r.bottom + 2, [
            { l: L.f_dl_xlsx_all, i: 'fa-file-excel-o', f: function () { exportIssues('xlsx', 'all'); } },
            { l: L.f_dl_xlsx_screen, i: 'fa-file-excel-o', f: function () { exportIssues('xlsx', 'screen'); } },
            { l: L.f_dl_csv_all, i: 'fa-file-text-o', f: function () { exportIssues('csv', 'all'); } },
            { l: L.f_dl_csv_screen, i: 'fa-file-text-o', f: function () { exportIssues('csv', 'screen'); } }
        ]);
    });
    var EXPORT_BUSY = false;
    function exportIssues(fmt, cols) {
        var rows = filteredIssues();
        if (!rows.length) { toast(L.f_csv_empty, true); return; }
        if (EXPORT_BUSY) { return; }
        EXPORT_BUSY = true;
        toast(L.f_dl_busy);
        var body = new FormData();
        body.append('ids', rows.map(function (i) { return i.id; }).join(','));
        body.append('fmt', fmt);
        body.append('cols', cols);
        fetch(D.urls.base + '/export', {
            method: 'POST', body: body, credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (res) {
            if (!res.ok) {
                return res.json().catch(function () { return {}; }).then(function (j) { throw new Error((j && j.message) || L.f_dl_fail); });
            }
            return res.blob();
        }).then(function (blob) {
            EXPORT_BUSY = false;
            var url = URL.createObjectURL(blob);
            var a = document.createElement('a');
            a.href = url;
            a.download = (D.project.key || 'issues') + '-' + D.today + '.' + fmt;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
        }).catch(function (e) {
            EXPORT_BUSY = false;
            toast((e && e.message) || L.f_dl_fail, true);
        });
    }
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

