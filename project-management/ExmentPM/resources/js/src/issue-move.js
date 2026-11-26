    /* ------------------------------------- move / delete an issue ------ */
    // Move: a small panel under the detail head lists the projects the
    // issue may go to (asked of the server when opened - it knows who may
    // add issues where). The server converts status and type to the new
    // project's and takes off what does not travel; the page then goes to
    // the issue in its new project.
    function issueMoveClose() {
        var box = document.querySelector('#pp-d-main .pp-move');
        if (box) { box.hidden = true; box.innerHTML = ''; }
    }
    function issueMoveOpen(issueId) {
        var box = document.querySelector('#pp-d-main .pp-move');
        if (!box) { return; }
        if (!box.hidden) { issueMoveClose(); return; }
        box.hidden = false;
        box.innerHTML = '<div class="pp-move-in">' + esc(L.move_loading) + '</div>';
        $.ajax({
            url: D.urls.base + '/issue/' + encodeURIComponent(issueId) + '/move',
            dataType: 'json', cache: false
        }).done(function (res) {
            if (String(currentIssue) !== String(issueId) || box.hidden) { return; }
            var list = (res && res.projects) || [];
            if (!list.length) {
                box.innerHTML = '<div class="pp-move-in">' + esc(L.move_none) +
                    ' <a class="bl-btn-sub pp-move-cancel">' + esc(L.move_cancel) + '</a></div>';
                return;
            }
            box.innerHTML = '<div class="pp-move-in"><b>' + esc(L.move_title) + '</b> ' +
                '<select class="pp-move-sel"><option value="">' + esc(L.move_pick) + '</option>' +
                list.map(function (p) {
                    return '<option value="' + esc(p.id) + '">' + esc(p.name) + (p.key ? ' (' + esc(p.key) + ')' : '') + '</option>';
                }).join('') + '</select> ' +
                '<button type="button" class="bl-btn-main pp-move-do">' + esc(L.move_do) + '</button> ' +
                '<a class="bl-btn-sub pp-move-cancel">' + esc(L.move_cancel) + '</a>' +
                '<div class="pp-move-note">' + esc(L.move_note) + '</div></div>';
        }).fail(function (xhr) {
            if (String(currentIssue) !== String(issueId)) { return; }
            issueMoveClose();
            toast(xhrErr(xhr, L.move_fail), true);
        });
    }
    function issueMoveRun(issueId, btn) {
        var sel = document.querySelector('#pp-d-main .pp-move-sel');
        if (!sel || !sel.value || btn.disabled) { return; }
        btn.disabled = true;
        $.ajax({
            url: D.urls.base + '/issue/' + encodeURIComponent(issueId) + '/move',
            type: 'POST', dataType: 'json',
            data: { project: sel.value },
            headers: { 'X-CSRF-TOKEN': CSRF }
        }).done(function (res) {
            if (!res || !res.ok || !res.url) {
                btn.disabled = false;
                toast(errText(null, res, L.move_fail), true);
                return;
            }
            if (res.message) { toast(res.message); }
            // Another project's page: a full load, which also leaves this
            // project's list without the issue.
            setTimeout(function () { location.href = res.url; }, 400);
        }).fail(function (xhr) {
            btn.disabled = false;
            toast(xhrErr(xhr, L.move_fail), true);
        });
    }
    // Delete arms itself like a comment's: the second click within a few
    // seconds is the confirmation. Shown to the project's manager.
    function issueDelete(issueId, link) {
        if (link.dataset.busy === '1') { return; }
        var lb = link.querySelector('.lb') || link;
        if (link.dataset.armed !== '1') {
            link.dataset.armed = '1';
            lb.textContent = L.issue_del_ask;
            setTimeout(function () {
                if (!link.parentNode) { return; }
                link.dataset.armed = '';
                lb.textContent = L.issue_delete;
            }, 4000);
            return;
        }
        link.dataset.busy = '1';
        $.ajax({
            url: D.urls.base + '/issue/' + encodeURIComponent(issueId) + '/delete',
            type: 'POST', dataType: 'json',
            headers: { 'X-CSRF-TOKEN': CSRF }
        }).done(function (res) {
            if (!res || !res.ok) {
                link.dataset.busy = '';
                toast(errText(null, res, L.issue_del_fail), true);
                return;
            }
            if (res.message) { toast(res.message); }
            // Back to the list, drawn again without the issue.
            // Off the deleted issue first, so the redraw does not go looking
            // for its detail; then the list again from fresh data.
            setTimeout(function () {
                location.hash = 'issues';
                softReload();
            }, 400);
        }).fail(function (xhr) {
            link.dataset.busy = '';
            toast(xhrErr(xhr, L.issue_del_fail), true);
        });
    }

    // Delete asks by arming itself rather than by opening a dialog: the
    // second click within a few seconds is the confirmation.
    function cmtDelete(link) {
        if (link.dataset.busy === '1') { return; }
        if (link.dataset.armed !== '1') {
            link.dataset.armed = '1';
            link.textContent = L.cmt_del_ask;
            setTimeout(function () {
                if (!link.parentNode) { return; }
                link.dataset.armed = '';
                link.textContent = L.del;
            }, 4000);
            return;
        }
        var issueId = currentIssue, epoch = detailEpoch;
        var seq = ++DET_SEQ;
        link.dataset.busy = '1';
        $.ajax({
            url: D.urls.base + '/comment/' + link.dataset.cid, type: 'DELETE',
            dataType: 'json',
            headers: { 'X-CSRF-TOKEN': CSRF }
        }).done(function (res) {
            link.dataset.busy = '';
            if (!res || !res.ok || !res.issue || String(res.issue.id) !== String(issueId)) {
                if (detailCurrent(issueId, epoch)) { toast(errText(null, res, L.cmt_del_fail), true); }
                return;
            }
            applyDetail(res, epoch, true, seq);
            if (detailCurrent(issueId, epoch)) { toast(L.cmt_deleted); }
        }).fail(function (xhr) {
            link.dataset.busy = '';
            if (!detailCurrent(issueId, epoch)) { return; }
            toast(xhrErr(xhr, L.cmt_del_fail), true);
        });
    }
    // The short link to an issue, /view/KEY like Backlog's (1.6.1): the
    // server redirects it to the project page. Only for a real key - an
    // issue on a project without a key keeps the long form.
    function issueShortUrl(i) {
        var key = i && i.key ? String(i.key) : '';
        if (!key || key.indexOf('#') >= 0) { return null; }
        var base = location.pathname.replace(/\/\d+\/?$/, '');
        return location.origin + base + '/view/' + encodeURIComponent(key);
    }
    // ISS00440: what is copied is the page's own address by the project's
    // code, with the issue key after the # - the form every link now has.
    function pageUrl() {
        return (D.urls && D.urls.page) ? String(D.urls.page) : location.origin + location.pathname;
    }
    function issuePageUrl(id) {
        return pageUrl() + '#' + issueHash(id);
    }
    function cmtCopyLink(cid) {
        copyText(issuePageUrl(currentIssue) + '?c=' + cid, L.cmt_copied);
    }
    function issueCopyLink() {
        copyText(issuePageUrl(currentIssue), L.link_copied);
    }
    // The copy button by the issue key (ISS00440, like Backlog's): a click
    // copies "ISS00239 subject", Shift+click the key alone. As text and as
    // a link, so a paste into Teams, OneNote, Outlook or Word is a link to
    // the issue; plain text where the browser has no ClipboardItem.
    function ikText(i) {
        return String((i && (i.ik || i.key)) || '');
    }
    function ikCopyButton(i) {
        return '<button type="button" class="pp-ikcopy" data-issue="' + esc(i.id) + '" title="' + esc(L.ik_copy_tip) +
            '" aria-label="' + esc(L.ik_copy_tip) + '"><i class="fa fa-clipboard"></i></button>';
    }
    function ikCopy(id, keyOnly) {
        var i = issueById(id);
        if (!i) { return; }
        var key = ikText(i);
        var text = keyOnly ? key : key + ' ' + String(i.subject || '');
        var html = '<a href="' + esc(issuePageUrl(i.id)) + '">' + esc(text) + '</a>';
        var done = function () { toast(keyOnly ? L.ik_key_copied : L.ik_copied); };
        try {
            if (window.ClipboardItem && navigator.clipboard && navigator.clipboard.write) {
                navigator.clipboard.write([new window.ClipboardItem({
                    'text/plain': new Blob([text], { type: 'text/plain' }),
                    'text/html': new Blob([html], { type: 'text/html' })
                })]).then(done, function () { copyText(text, keyOnly ? L.ik_key_copied : L.ik_copied); });
                return;
            }
        } catch (e) { /* plain text below */ }
        copyText(text, keyOnly ? L.ik_key_copied : L.ik_copied);
    }
    // Clipboard with the execCommand fallback the admin's iframe context
    // still needs on older browsers.
    function copyText(text, okMessage) {
        var done = function () { toast(okMessage); };
        try {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(done, done);
                return;
            }
        } catch (e) { /* fall through */ }
        try {
            var area = document.createElement('textarea');
            area.value = text;
            area.style.position = 'fixed';
            area.style.top = '-1000px';
            document.body.appendChild(area);
            area.select();
            document.execCommand('copy');
            document.body.removeChild(area);
            done();
        } catch (e) { /* silent */ }
    }
    // What a new issue should start from. "child" hangs it under the open
    // issue; "copy" repeats what the open issue says, minus its key, its
    // history and its status - a duplicate starts at the project's first
    // status, the way a brand new issue does.
    function issuePrefill(kind, id) {
        var cols = D.issue_cols || {};
        // id: the issue of a row's menu; the one open otherwise
        var i = issueById(id !== undefined && id !== null ? id : currentIssue);
        if (!i || !cols.subject) { return null; }
        var out = {};
        function put(key, value) {
            if (!cols[key] || value === null || value === undefined || value === '') { return; }
            if (Array.isArray(value) && !value.length) { return; }
            out[cols[key]] = value;
        }
        if (kind === 'child') {
            put('parent', i.id);
            put('milestone', i.ms);
            put('type', i.ty);

            return out;
        }
        put('subject', i.subject);
        put('type', i.ty);
        put('priority', i.pr);
        put('assignees', i.as);
        put('milestone', i.ms);
        put('category', i.cat);
        put('parent', i.parent);
        put('start', i.sd);
        put('due', i.dd);
        put('estimate', i.eh);
        var det = DET[currentIssue];
        if (det && det.description) { put('description', det.description); }

        return out;
    }

