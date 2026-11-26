    /* ------------------------------------------------ attachments edited in place (ISS02938) - */
    // An attachment of an issue edited where it is, with its versions: the 「版 n」 badge opens the
    // list of versions (download, back to one), 「編集」 opens a text attachment in an editor on
    // this page, or an Office one in Exment Plugin Groupware (D.urls.office_edit). One editor at
    // a time: a lock the server keeps, renewed while the editor is open. Only when the feature is
    // on (D.can.attach_edit); the server decides again on every call.
    function attachOn() { return !!(D.can && D.can.attach_edit); }
    function attachBase(issueId, doc) { return D.urls.base + '/issue/' + issueId + '/file/' + doc; }
    function attachTools(f, res) {
        if (!attachOn() || !f || !f.doc) { return ''; }
        var out = ' <button type="button" class="pp-ver" data-doc="' + esc(f.doc) + '" title="' + esc(L.attach_versions) + '">' +
            esc(attachFmt(L.attach_ver, f.ver || 1)) + '</button>';
        var canEdit = !!(res && res.editable);
        if (canEdit && f.ek === 'text') {
            out += ' <a href="javascript:void(0);" class="pp-aedit" data-doc="' + esc(f.doc) + '" data-kind="text"><i class="fa fa-pencil"></i> ' + esc(L.attach_edit) + '</a>';
        } else if (canEdit && f.ek === 'office' && D.urls && D.urls.office_edit) {
            out += ' <a href="javascript:void(0);" class="pp-aedit" data-doc="' + esc(f.doc) + '" data-kind="office" data-url="' + esc(f.url) + '"><i class="fa fa-pencil"></i> ' + esc(L.attach_edit) + '</a>';
        } else if (f.ek === 'text') {
            out += ' <a href="javascript:void(0);" class="pp-aedit" data-doc="' + esc(f.doc) + '" data-kind="text" data-ro="1"><i class="fa fa-file-text-o"></i> ' + esc(L.attach_read) + '</a>';
        }
        return out;
    }
    function attachFmt(tpl, n) { return String(tpl || '').replace(/:n(?![A-Za-z0-9_])/g, String(n)); }
    function attachBox(title) {
        attachClose();
        var wrap = document.createElement('div');
        wrap.className = 'pp-aed';
        wrap.id = 'pp-aed';
        wrap.innerHTML = '<div class="box" role="dialog" aria-modal="true"><div class="hd"><span class="t"></span><span class="sp"></span>' +
            '<span class="lock" hidden></span><a href="javascript:void(0);" class="x" aria-label="' + esc(L.attach_close) + '"><i class="fa fa-times"></i></a></div>' +
            '<div class="bd"></div><div class="ft"><span class="msg"></span></div></div>';
        wrap.querySelector('.t').textContent = title;
        document.body.appendChild(wrap);
        wrap.querySelector('.x').addEventListener('click', function () { attachClose(); });
        wrap.addEventListener('mousedown', function (ev) { if (ev.target === wrap) { attachClose(); } });
        return wrap;
    }
    var ATTACH = { doc: null, issue: null, beat: null, dirty: false };
    function attachRelease() {
        if (ATTACH.beat) { clearInterval(ATTACH.beat); ATTACH.beat = null; }
        if (ATTACH.doc && ATTACH.issue && ATTACH.mine) {
            $.ajax({ url: attachBase(ATTACH.issue, ATTACH.doc) + '/lock', type: 'POST', data: { release: 1 }, headers: { 'X-CSRF-TOKEN': CSRF } });
        }
        ATTACH.doc = null; ATTACH.issue = null; ATTACH.mine = false; ATTACH.dirty = false;
    }
    function attachClose(force) {
        var box = document.getElementById('pp-aed');
        if (!box) { return true; }
        if (!force && ATTACH.dirty && !window.confirm(L.attach_unsaved)) { return false; }
        attachRelease();
        box.parentNode.removeChild(box);
        return true;
    }
    window.addEventListener('beforeunload', function () { attachRelease(); });

    // the versions of an attachment
    function attachVersions(doc) {
        var issueId = currentIssue;
        var box = attachBox(L.attach_versions);
        box.querySelector('.bd').innerHTML = '<i class="fa fa-spinner fa-spin"></i>';
        $.ajax({ url: attachBase(issueId, doc) + '/versions', dataType: 'json' }).done(function (res) {
            if (!res || !res.ok) { box.querySelector('.bd').textContent = errText(null, res, L.attach_fail); return; }
            $.extend(D.users, res.users || {});
            box.querySelector('.bd').innerHTML = '<table class="vl"><tr><th>' + esc(L.attach_col_ver) + '</th><th>' + esc(L.attach_col_name) + '</th><th>' + esc(L.attach_col_by) + '</th><th>' + esc(L.attach_col_at) + '</th><th></th></tr>' +
                (res.versions || []).map(function (v) {
                    return '<tr><td>' + esc(attachFmt(L.attach_ver, v.no)) + (v.current ? ' <b>' + esc(L.attach_current) + '</b>' : '') + '</td><td>' + esc(v.name) + '</td><td>' + esc(user(v.by).n) + '</td><td>' + esc(v.at) + '</td><td>' +
                        '<a href="' + esc(linkUrl(v.url)) + '" target="_blank" rel="noopener"><i class="fa fa-download"></i> ' + esc(L.attach_download) + '</a>' +
                        (!v.current && res.edit ? ' <a href="javascript:void(0);" class="pp-arestore" data-id="' + esc(v.id) + '" style="margin-left:8px;">' + esc(L.attach_restore) + '</a>' : '') +
                        '</td></tr>';
                }).join('') + '</table>';
            box.querySelectorAll('.pp-arestore').forEach(function (a) {
                a.addEventListener('click', function () {
                    if (!window.confirm(L.attach_restore_ask)) { return; }
                    var epoch = detailEpoch, seq = ++DET_SEQ;
                    $.ajax({ url: attachBase(issueId, doc) + '/restore/' + a.getAttribute('data-id'), type: 'POST', dataType: 'json', headers: { 'X-CSRF-TOKEN': CSRF } })
                        .done(function (r) {
                            if (!r || !r.ok || !r.issue) { toast(errText(null, r, L.attach_fail), true); return; }
                            attachClose(true);
                            applyDetail(r, epoch, true, seq);
                            toast(L.attach_restored);
                        }).fail(function (xhr) { toast(xhrErr(xhr, L.attach_fail), true); });
                });
            });
        }).fail(function (xhr) { box.querySelector('.bd').textContent = xhrErr(xhr, L.attach_fail); });
    }

    // the lock: taken, renewed every 4 minutes while the editor is open
    function attachLock(issueId, doc, done) {
        $.ajax({ url: attachBase(issueId, doc) + '/lock', type: 'POST', dataType: 'json', headers: { 'X-CSRF-TOKEN': CSRF } })
            .done(function (res) { done(res && res.ok ? res : null); })
            .fail(function () { done(null); });
    }

    // a text attachment: an editor on this page (Markdown with a preview, CSV also as a table)
    function attachText(doc, readOnly) {
        var issueId = currentIssue;
        var box = attachBox(L.attach_edit);
        var bd = box.querySelector('.bd');
        bd.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';
        $.ajax({ url: attachBase(issueId, doc) + '/text', dataType: 'json' }).done(function (res) {
            if (!res || !res.ok) { bd.textContent = errText(null, res, L.attach_fail); return; }
            box.querySelector('.t').textContent = res.name + ' — ' + attachFmt(L.attach_ver, res.no);
            var ext = String(res.name).split('.').pop().toLowerCase();
            var start = function (mine, by) {
                var ro = readOnly || !res.edit || !mine;
                if (!mine && by) {
                    var lk = box.querySelector('.lock');
                    lk.hidden = false;
                    lk.textContent = String(L.attach_locked_by || '').replace(':name', function () { return by; });
                }
                bd.innerHTML = '';
                var ta = document.createElement('textarea');
                ta.value = res.text;
                ta.readOnly = ro;
                ta.spellcheck = false;
                bd.appendChild(ta);
                var view = document.createElement('div');
                view.hidden = true;
                bd.appendChild(view);
                var ft = box.querySelector('.ft');
                ft.querySelector('.msg').textContent = String(L.attach_encoding || '').replace(':enc', function () { return res.encoding; });
                if (ext === 'md' || ext === 'markdown' || ext === 'csv' || ext === 'tsv') {
                    var tog = document.createElement('button');
                    tog.type = 'button';
                    tog.className = 'bl-btn-sub';
                    tog.textContent = (ext === 'csv' || ext === 'tsv') ? L.attach_table : L.attach_preview;
                    tog.addEventListener('click', function () {
                        var showing = !view.hidden;
                        if (!showing) {
                            if (ext === 'csv' || ext === 'tsv') {
                                // as text cells only: nothing of the file is read as HTML
                                var t = document.createElement('table');
                                csvParse(ta.value, ext === 'tsv' ? '\t' : ',').slice(0, 2000).forEach(function (cells) {
                                    var tr = t.insertRow();
                                    cells.forEach(function (c) { tr.insertCell().textContent = c; });
                                });
                                view.innerHTML = '';
                                view.appendChild(t);
                            } else {
                                view.innerHTML = '<div class="bl-md">' + renderCommentText(ta.value) + '</div>';
                            }
                        }
                        view.hidden = showing;
                        ta.hidden = !showing;
                    });
                    ft.appendChild(tog);
                }
                if (!ro) {
                    ta.addEventListener('input', function () { ATTACH.dirty = true; });
                    var save = document.createElement('button');
                    save.type = 'button';
                    save.className = 'bl-btn-main';
                    save.textContent = L.attach_save;
                    save.addEventListener('click', function () {
                        save.disabled = true;
                        var epoch = detailEpoch, seq = ++DET_SEQ;
                        $.ajax({
                            url: attachBase(issueId, doc) + '/text', type: 'POST', dataType: 'json',
                            contentType: 'application/json', processData: false,
                            data: JSON.stringify({ text: ta.value, hash: res.hash }), headers: { 'X-CSRF-TOKEN': CSRF }
                        }).done(function (r) {
                            save.disabled = false;
                            if (!r || !r.ok || !r.issue) { toast(errText(null, r, L.attach_fail), true); return; }
                            ATTACH.dirty = false;
                            ATTACH.mine = false;
                            attachClose(true);
                            applyDetail(r, epoch, true, seq);
                            toast(L.attach_saved);
                        }).fail(function (xhr) { save.disabled = false; toast(xhrErr(xhr, L.attach_fail), true); });
                    });
                    ft.appendChild(save);
                }
                ta.focus();
            };
            if (readOnly || !res.edit) { start(false, null); return; }
            attachLock(issueId, doc, function (lk) {
                if (lk && lk.mine) {
                    ATTACH.doc = doc; ATTACH.issue = issueId; ATTACH.mine = true;
                    ATTACH.beat = setInterval(function () { attachLock(issueId, doc, function () {}); }, 240000);
                    start(true, null);
                } else {
                    start(false, lk ? lk.name : '');
                }
            });
        }).fail(function (xhr) { bd.textContent = xhrErr(xhr, L.attach_fail); });
    }
    // RFC 4180, enough for a look: quoted cells, doubled quotes, new lines in quotes
    function csvParse(text, sep) {
        var rows = [], row = [], cell = '', q = false, i, c;
        for (i = 0; i < text.length; i++) {
            c = text[i];
            if (q) {
                if (c === '"') { if (text[i + 1] === '"') { cell += '"'; i++; } else { q = false; } } else { cell += c; }
            } else if (c === '"') { q = true; }
            else if (c === sep) { row.push(cell); cell = ''; }
            else if (c === '\n') { row.push(cell.replace(/\r$/, '')); rows.push(row); row = []; cell = ''; }
            else { cell += c; }
        }
        if (cell !== '' || row.length) { row.push(cell); rows.push(row); }
        return rows;
    }

    // an Office attachment: Groupware's page, after the lock
    function attachOffice(doc, url) {
        var issueId = currentIssue;
        var m = /\/files\/([0-9a-f][0-9a-f-]{7,63})(?:[\/?#]|$)/i.exec(String(url || ''));
        if (!m || !D.urls.office_edit) { return; }
        attachLock(issueId, doc, function (lk) {
            if (!lk || !lk.mine) {
                toast(String(L.attach_locked_by || '').replace(':name', function () { return lk ? lk.name : ''; }), true);
                return;
            }
            // Groupware saves, keeps the older version and writes its own line; the lock is let go
            // when its tab is left (or after 10 minutes)
            ATTACH.doc = doc; ATTACH.issue = issueId; ATTACH.mine = true;
            var w = window.open(D.urls.office_edit + '/' + m[1], '_blank', 'noopener');
            setTimeout(function () { attachRelease(); }, 600000);
            if (!w) { toast(L.attach_popup, true); }
        });
    }

    document.addEventListener('click', function (ev) {
        var t = ev.target;
        if (!t || !t.closest) { return; }
        var ver = t.closest('.pp-ver');
        if (ver) { ev.preventDefault(); attachVersions(ver.getAttribute('data-doc')); return; }
        var ed = t.closest('.pp-aedit');
        if (ed) {
            ev.preventDefault();
            if (ed.getAttribute('data-kind') === 'office') { attachOffice(ed.getAttribute('data-doc'), ed.getAttribute('data-url')); return; }
            attachText(ed.getAttribute('data-doc'), ed.getAttribute('data-ro') === '1');
        }
    });
    document.addEventListener('keydown', function (ev) {
        if (ev.key === 'Escape' && document.getElementById('pp-aed')) { attachClose(); }
    });
