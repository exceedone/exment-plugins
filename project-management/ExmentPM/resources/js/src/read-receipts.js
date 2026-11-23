    /* ------------------------------------------------ read receipts ----- */
    // Who has opened an issue, and the list's unread mark (Outlook-style:
    // bold until opened, and right-click to mark read / unread).
    //
    // Opening an issue tells the server without waiting, in the slow lane of
    // the request queue, and only once per page view: the screen never waits
    // on it and a busy reader's real requests go first.
    var READ_SENT = {};
    function readAv(r) {
        var u = user(r.by);
        var title = u.n + '  ' + (r.at || '') + (r.old ? '  (' + L.reads_old + ')' : '');
        return '<span class="bl-av pp-read' + (r.old ? ' old' : '') + '" style="background:' + ppColor(u.c) + '" title="' + esc(title) + '">' +
            esc(u.i) + avImg(u) + '</span>';
    }
    function readsHtml(reads) {
        if (reads === undefined || reads === null) { return '<span class="pp-reads-v">…</span>'; }
        if (!reads.length) { return '<span class="pp-reads-v none">' + esc(L.reads_none) + '</span>'; }
        var shown = reads.slice(0, 15);
        return '<span class="pp-reads-v"><span class="pp-reads-n">' + reads.length + '</span>' +
            shown.map(readAv).join('') +
            (reads.length > shown.length ? '<span class="pp-reads-more">+' + (reads.length - shown.length) + '</span>' : '') +
            '</span>';
    }
    function paintUnread(id) {
        var row = issueById(id);
        var tr = document.querySelector('#pp-issue-rows tr[data-issue="' + String(id).replace(/[^0-9]/g, '') + '"]');
        if (tr) { tr.classList.toggle('pp-unread', !!(row && row.unread)); }
    }
    // Every issue of the project, in one request - not one per row.
    function readAll() {
        if (!D.urls || !D.urls.base) { return; }
        $.ajax({
            url: D.urls.base + '/read-all',
            type: 'POST', dataType: 'json',
            headers: { 'X-CSRF-TOKEN': CSRF }
        }).then(function (res) {
            if (!alive() || !res || !res.ok) { return; }
            (D.issues || []).forEach(function (i) {
                if (i.unread) { i.unread = false; paintUnread(i.id); }
                READ_SENT[String(i.id)] = true;
            });
            toast(String(L.read_all_done).replace(':count', res.count));
        }, function (xhr) { toast(xhrErr(xhr, L.read_all_fail), true); });
    }
    function markRead(id, read) {
        if (id === null || id === undefined || !D.urls || !D.urls.base) { return; }
        var row = issueById(id);
        var key = String(id);
        if (read && READ_SENT[key] === true && !(row && row.unread)) { return; }
        READ_SENT[key] = read;
        if (row) { row.unread = !read; }
        paintUnread(id);
        ppQueue(function () {
            return $.ajax({
                url: D.urls.base + '/issue/' + encodeURIComponent(key) + '/read',
                type: 'POST', dataType: 'json', data: { read: read ? 1 : 0 },
                headers: { 'X-CSRF-TOKEN': CSRF }
            });
        }, true).then(function (res) {
            if (!alive() || !res || !res.ok) { return; }
            $.extend(D.users, res.users || {});
            var det = own(DET, key);
            // A reader who just opened it has seen the latest: nobody's mark
            // in this fresh list is "old" for what this screen shows.
            if (det) {
                var before = {};
                (det.reads || []).forEach(function (r) { before[r.by] = r; });
                det.reads = (res.reads || []).map(function (r) {
                    var was = before[r.by];
                    return { by: r.by, at: r.at, old: !!(was && was.at === r.at && was.old) };
                });
            }
            if (String(currentIssue) === key) {
                var box = document.querySelector('#pp-d-main .pp-reads .pp-reads-v');
                if (box) { box.outerHTML = readsHtml(det ? det.reads : res.reads); }
            }
        }).catch(function () {
            // Not worth a message: the next opening tries again.
            if (READ_SENT[key] === read) { delete READ_SENT[key]; }
        });
    }
    // "Mail this issue": pick people of the project, add a note, send.
    function mailClose() {
        var old = document.getElementById('pp-mailbox');
        if (old) { old.remove(); }
    }
    function mailOpen(issueId) {
        if (issueId === null || issueId === undefined) { return; }
        mailClose();
        var p = D.project || {};
        // The sender comes first, so a copy to oneself is one tick away.
        var ids = [];
        [D.me, p.manager].concat(p.members || [], p.viewers || []).forEach(function (u) {
            u = Number(u);
            if (u && ids.indexOf(u) < 0) { ids.push(u); }
        });
        var issue = issueById(issueId) || {};
        var box = document.createElement('div');
        box.id = 'pp-mailbox';
        box.className = 'pp-mailbox';
        box.innerHTML = '<div class="pp-mailbox-back"></div><div class="pp-mailbox-panel" role="dialog" aria-modal="true">' +
            '<div class="pp-mailbox-head"><i class="fa fa-envelope-o"></i> ' + esc(L.mail_title) +
                '<div class="pp-mailbox-sub">' + esc((issue.key || '') + ' ' + (issue.subject || '')) + '</div></div>' +
            '<div class="pp-mailbox-k">' + esc(L.mail_to) + '</div>' +
            '<div class="pp-mailbox-users">' + ids.map(function (u) {
                return '<label><input type="checkbox" value="' + esc(u) + '">' + avname(u) + '</label>';
            }).join('') + '</div>' +
            '<textarea class="pp-mailbox-msg" rows="4" placeholder="' + esc(L.mail_msg_ph) + '"></textarea>' +
            '<div class="pp-mailbox-note">' + esc(L.mail_note) + '</div>' +
            '<div class="pp-mailbox-foot"><span class="pp-mailbox-err"></span>' +
                '<button type="button" class="bl-btn-sub pp-mailbox-cancel">' + esc(L.mail_cancel) + '</button>' +
                '<button type="button" class="bl-btn-main pp-mailbox-send"><i class="fa fa-paper-plane"></i> ' + esc(L.mail_send) + '</button></div>' +
            '</div>';
        root.appendChild(box);
        var err = box.querySelector('.pp-mailbox-err');
        box.querySelector('.pp-mailbox-back').addEventListener('click', mailClose);
        box.querySelector('.pp-mailbox-cancel').addEventListener('click', mailClose);
        box.addEventListener('keydown', function (ev) { if (ev.key === 'Escape') { mailClose(); } });
        box.querySelector('.pp-mailbox-users').addEventListener('change', function () { err.textContent = ''; });
        var send = box.querySelector('.pp-mailbox-send');
        send.addEventListener('click', function () {
            var users = [].slice.call(box.querySelectorAll('.pp-mailbox-users input:checked')).map(function (c) { return c.value; });
            if (!users.length) { err.textContent = L.mail_none; return; }
            send.disabled = true;
            err.textContent = '';
            $.ajax({
                url: D.urls.base + '/issue/' + encodeURIComponent(issueId) + '/mail',
                type: 'POST', dataType: 'json',
                data: { users: users, message: box.querySelector('.pp-mailbox-msg').value },
                headers: { 'X-CSRF-TOKEN': CSRF }
            }).done(function (res) {
                if (!res || !res.ok) { send.disabled = false; err.textContent = errText(null, res, L.mail_fail); return; }
                mailClose();
                toast(String(L.mail_sent).replace(':count', res.count) +
                    (res.skipped ? String(L.mail_skipped).replace(':count', res.skipped) : ''));
            }).fail(function (xhr) {
                send.disabled = false;
                err.textContent = xhrErr(xhr, L.mail_fail);
            });
        });
        var first = box.querySelector('.pp-mailbox-users input');
        if (first) { first.focus(); }
    }
    function ctxClose() {
        var old = document.getElementById('pp-ctx');
        if (old) { old.remove(); }
    }
    function ctxOpen(x, y, items) {
        ctxClose();
        var menu = document.createElement('div');
        menu.id = 'pp-ctx';
        menu.className = 'pp-ctx';
        menu.setAttribute('role', 'menu');
        menu.innerHTML = items.map(function (it, n) {
            return '<button type="button" role="menuitem" data-n="' + n + '"><i class="fa ' + esc(it.i) + '"></i> ' + esc(it.l) + '</button>';
        }).join('');
        root.appendChild(menu);
        var w = menu.offsetWidth, h = menu.offsetHeight;
        menu.style.left = Math.max(4, Math.min(x, window.innerWidth - w - 4)) + 'px';
        menu.style.top = Math.max(4, Math.min(y, window.innerHeight - h - 4)) + 'px';
        menu.addEventListener('click', function (ev) {
            var b = ev.target.closest('button[data-n]');
            if (!b) { return; }
            ctxClose();
            items[Number(b.getAttribute('data-n'))].f();
        });
        var first = menu.querySelector('button');
        if (first) { first.focus(); }
    }
    document.addEventListener('mousedown', function (ev) {
        if (!ev.target.closest || !ev.target.closest('#pp-ctx')) { ctxClose(); }
    }, true);
    document.addEventListener('keydown', function (ev) { if (ev.key === 'Escape') { ctxClose(); } });
    window.addEventListener('scroll', ctxClose, true);
    window.addEventListener('blur', ctxClose);

