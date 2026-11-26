    /* ------------------------------------------------ comment composer -- */
    function cmtBox() { return document.getElementById('pp-cmt-text'); }
    // Wrap the selection, or drop a placeholder in when there is none, and
    // leave the selection where the typing continues.
    function cmtWrap(before, after, placeholder) {
        var box = cmtBox();
        if (!box) { return; }
        var from = box.selectionStart || 0, to = box.selectionEnd || 0;
        var sel = box.value.slice(from, to) || placeholder || '';
        box.value = box.value.slice(0, from) + before + sel + after + box.value.slice(to);
        box.focus();
        box.selectionStart = from + before.length;
        box.selectionEnd = from + before.length + sel.length;
        cmtPreviewRefresh();
    }
    // Prefix every line the selection touches - what the list and quote
    // buttons do, and what makes them work on a paragraph at a time.
    function cmtPrefix(make) {
        var box = cmtBox();
        if (!box) { return; }
        var from = box.value.lastIndexOf('\n', (box.selectionStart || 0) - 1) + 1;
        var to = box.value.indexOf('\n', box.selectionEnd || 0);
        if (to < 0) { to = box.value.length; }
        var out = box.value.slice(from, to).split('\n').map(make).join('\n');
        box.value = box.value.slice(0, from) + out + box.value.slice(to);
        box.focus();
        box.selectionStart = from;
        box.selectionEnd = from + out.length;
        cmtPreviewRefresh();
    }
    function cmtTool(kind) {
        if (kind === 'attach') { document.getElementById('pp-cmt-file').click(); return; }
        if (kind === 'emoji') { cmtEmoji(); return; }
        if (kind === 'preview') { cmtPreviewToggle(); return; }
        if (kind === 'b') { cmtWrap('**', '**', ''); return; }
        if (kind === 'i') { cmtWrap('*', '*', ''); return; }
        if (kind === 's') { cmtWrap('~~', '~~', ''); return; }
        if (kind === 'code') { cmtWrap('`', '`', ''); return; }
        // No prompt() for the address: the placeholder is selected instead,
        // so the next keystroke replaces it.
        if (kind === 'link') { cmtWrap('[', '](https://)', L.ct_link_label); return; }
        if (kind === 'ul') { cmtPrefix(function (line) { return line.replace(/^([-*]\s+)?/, '- '); }); return; }
        if (kind === 'ol') { cmtPrefix(function (line, at) { return line.replace(/^(\d+\.\s+)?/, (at + 1) + '. '); }); return; }
        if (kind === 'q') { cmtPrefix(function (line) { return line.replace(/^(&gt;\s?|>\s?)?/, '> '); }); return; }
    }
    // Not the whole Unicode chart - the two dozen a project conversation
    // actually reaches for. Anything else can still be typed or pasted.
    var EMOJI = ['👍', '🙏', '🎉', '✅', '☑️', '❗', '⚠️', '🐛', '🔥', '💡', '📝', '📌',
        '👀', '🚀', '⏰', '❓', '💯', '👏', '🤝', '🔧', '😄', '😢', '🙇', '🆗'];
    function cmtEmoji() {
        var pad = document.getElementById('pp-cmt-emoji');
        if (!pad.childNodes.length) {
            EMOJI.forEach(function (ch) {
                var b = document.createElement('button');
                b.type = 'button';
                b.textContent = ch;
                b.addEventListener('click', function () { cmtInsert(ch); });
                pad.appendChild(b);
            });
        }
        pad.hidden = !pad.hidden;
        document.querySelector('#pp-cmt-tools button[data-ct="emoji"]').classList.toggle('on', !pad.hidden);
    }
    function cmtInsert(text) {
        var box = cmtBox();
        var from = box.selectionStart || 0, to = box.selectionEnd || 0;
        box.value = box.value.slice(0, from) + text + box.value.slice(to);
        box.focus();
        box.selectionStart = box.selectionEnd = from + text.length;
        cmtPreviewRefresh();
    }
    function cmtPreviewToggle() {
        var prev = document.getElementById('pp-cmt-prev');
        var btn = document.getElementById('pp-cmt-prevbtn');
        var on = prev.hidden;
        prev.hidden = !on;
        cmtBox().hidden = on;
        btn.classList.toggle('on', on);
        btn.textContent = on ? L.ct_write : L.ct_preview;
        cmtPreviewRefresh();
    }
    function cmtPreviewOff() {
        var prev = document.getElementById('pp-cmt-prev');
        if (prev && !prev.hidden) { cmtPreviewToggle(); }
    }
    function cmtPreviewRefresh() {
        var prev = document.getElementById('pp-cmt-prev');
        if (!prev || prev.hidden) { return; }
        var text = cmtBox().value;
        prev.innerHTML = text.trim()
            ? '<div class="txt bl-md">' + renderCommentText(text) + '</div>'
            : '<div style="color:var(--bl-faint);font-size:12px;">' + esc(L.empty) + '</div>';
    }
    // Paste, drop or pick: the file goes onto the issue and its address
    // comes back, so the comment being written can point at it right away.
    // This is the Backlog habit of pasting a screenshot straight into the
    // comment box and never touching an attach button at all.
    function cmtUpload(files) {
        var list = Array.prototype.slice.call(files || []);
        if (!list.length || currentIssue === null) { return; }
        var issueId = currentIssue, epoch = detailEpoch;
        var form = document.getElementById('pp-cmtform');
        var data = new FormData();
        list.forEach(function (f) {
            // A pasted bitmap carries no name of its own; stamping it keeps
            // two screenshots in the same issue apart.
            var named = f.name && f.name !== 'blob' ? f.name
                : ('image-' + Date.now() + '.' + (String(f.type || 'image/png').split('/')[1] || 'png'));
            data.append('file_data[]', f, named);
        });
        form.classList.add('busy');
        toast(L.cmt_uploading);
        $.ajax({
            url: D.urls.base + '/issue/' + issueId + '/upload', type: 'POST', data: data,
            dataType: 'json',
            processData: false, contentType: false, headers: { 'X-CSRF-TOKEN': CSRF }
        }).done(function (res) {
            if (!alive()) { return; }
            var det = own(DET, String(issueId));
            if (res && res.ok && res.files && det) {
                det.files = (det.files || []).concat(res.files.map(function (f) {
                    return { name: f.name, url: f.url, img: f.img, by: D.me, at: '' };
                }));
            }
            if (!detailCurrent(issueId, epoch)) { return; }
            form.classList.remove('busy');
            if (!res || !res.ok || !res.files) { toast(errText(null, res, L.cmt_up_fail), true); return; }
            var box = cmtBox();
            // The name is the text of the link, escaped so that a file named
            // to close the link early cannot point the link somewhere else.
            var markup = res.files.map(function (f) {
                return mdFileLink(f.name, f.url, !!f.img);
            }).join('\n');
            box.value = box.value + (box.value && !/\n$/.test(box.value) ? '\n' : '') + markup + '\n';
            box.focus();
            box.selectionStart = box.selectionEnd = box.value.length;
            dockDraftSave(issueId, box.value);
            cmtPreviewRefresh();
            qeMark();
            toast(fmt(L.cmt_up_ok, res.files.length));
            // The attachment box is fed by the detail payload, and the file
            // is already stored - so add it locally rather than spend a
            // request being told what we just sent.
            if (det) { renderIssueFiles(det); }
        }).fail(function (xhr) {
            if (!detailCurrent(issueId, epoch)) { return; }
            form.classList.remove('busy');
            toast(xhrErr(xhr, L.cmt_up_fail), true);
        });
    }

    /* ------------------------------------------------ comment actions --- */
    // Rewriting a comment happens where it is read, not in the box at the
    // bottom: the conversation around it is the context for the edit.
    // restore: an editor being put back after a redraw of the timeline (see
    // cmtEditsOpen) - its text, its caret, and focus only if it had it.
    //
    // Checked against the issue alone, not the screen's epoch: the editor
    // is carried across redraws of its issue, and a save clicked in it is a
    // save of a comment that is on screen right now.
    function cmtEditStart(cid, text, restore) {
        var issueId = currentIssue;
        var ent = document.getElementById('pp-c' + cid);
        if (!ent || ent.querySelector('.cedit')) { return; }
        var txt = ent.querySelector('.txt');
        var act = ent.querySelector('.act');
        if (!txt) { return; }
        var wrap = document.createElement('div');
        wrap.className = 'cedit';
        var area = document.createElement('textarea');
        area.value = text;
        var row = document.createElement('div');
        row.className = 'row';
        var save = document.createElement('button');
        save.className = 'bl-btn-main';
        save.textContent = L.cmt_save;
        var cancel = document.createElement('button');
        cancel.className = 'bl-btn-sub';
        cancel.textContent = L.cmt_cancel;
        row.appendChild(save);
        row.appendChild(cancel);
        wrap.appendChild(area);
        wrap.appendChild(row);
        txt.hidden = true;
        if (act) { act.hidden = true; }
        txt.parentNode.insertBefore(wrap, txt.nextSibling);
        if (!restore) {
            area.focus();
        } else {
            try { area.setSelectionRange(restore.start || 0, restore.end || 0); } catch (e) { /* not laid out */ }
            if (restore.focused) { area.focus(); }
        }
        var close = function () {
            wrap.remove();
            txt.hidden = false;
            if (act) { act.hidden = false; }
        };
        cancel.addEventListener('click', close);
        save.addEventListener('click', function () {
            if (!detailCurrent(issueId) || !wrap.isConnected) { return; }
            var next = area.value.trim();
            if (!next || save.disabled) { return; }
            if (next.length > maxBody()) { toast(tooLongText(maxBody()), true); return; }
            save.disabled = true;
            save.textContent = L.posting;
            var seq = ++DET_SEQ;
            $.ajax({
                url: D.urls.base + '/comment/' + cid, type: 'PATCH',
                dataType: 'json',
                data: { comment: next }, headers: { 'X-CSRF-TOKEN': CSRF }
            }).done(function (res) {
                if (!res || !res.ok || !res.issue || String(res.issue.id) !== String(issueId)) {
                    if (detailCurrent(issueId)) {
                        save.disabled = false; save.textContent = L.cmt_save;
                        toast(errText(null, res, L.post_fail), true);
                    }
                    return;
                }
                // Done with: closed before the redraw, which would otherwise
                // carry it across as work still open.
                if (area.value.trim() === next) { close(); }
                applyDetail(res, undefined, true, seq);
                if (detailCurrent(issueId)) { toast(L.cmt_saved); }
            }).fail(function (xhr) {
                if (!detailCurrent(issueId)) { return; }
                save.disabled = false;
                save.textContent = L.cmt_save;
                toast(xhrErr(xhr, L.post_fail), true);
            });
        });
    }
