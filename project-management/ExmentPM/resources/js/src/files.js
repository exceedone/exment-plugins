    /* ------------------------------------------------ files ------------- */
    /* ------------------------------------------------ office view ------ */
    // ISS00416: a Word, Excel or PowerPoint attachment opens read only on this
    // screen, in Exment Plugin Groupware's ibisOffice page, when that plugin
    // is installed (D.urls.office_view). The page reads the file from the
    // same download address the link uses, so it shows only what the link
    // would have downloaded. Without the plugin, the link downloads as before.
    function officeViewUrl(f) {
        var base = D.urls && D.urls.office_view;
        if (!base || !/\.(docx|xlsx|pptx)$/i.test(String((f && f.name) || ''))) { return null; }
        var m = /\/files\/([0-9a-f][0-9a-f-]{7,63})(?:[\/?#]|$)/i.exec(String(f.url || ''));
        return m ? base + '/' + m[1] : null;
    }
    function officeViewBtn(f) {
        var url = officeViewUrl(f);
        if (!url || !safeHref(url)) { return ''; }
        return ' <a class="pp-officeview" href="' + esc(url) + '" data-name="' + esc(f.name) + '" target="_blank" rel="noopener" title="' + esc(L.office_view_tip) + '">' +
            '<i class="fa fa-eye"></i> ' + esc(L.office_view) + '</a>';
    }
    function officeViewClose() {
        var box = document.getElementById('pp-officebox');
        // an attachment being edited in it (Groupware 0.4.0) says whether it has unsaved changes
        try {
            var frame = box && box.querySelector('iframe');
            if (frame && frame.contentWindow && frame.contentWindow.gwDirty && !window.confirm(L.office_unsaved)) { return; }
        } catch (e) {}
        if (box) { box.parentNode.removeChild(box); }
        document.removeEventListener('keydown', officeViewKey, true);
    }
    function officeViewKey(ev) {
        if (ev.key === 'Escape') { ev.preventDefault(); ev.stopPropagation(); officeViewClose(); }
    }
    function officeViewOpen(url, name) {
        officeViewClose();
        var box = document.createElement('div');
        box.id = 'pp-officebox';
        box.className = 'pp-officebox';
        box.setAttribute('role', 'dialog');
        box.setAttribute('aria-label', name);
        box.innerHTML = '<div class="pp-officebox-in"><div class="pp-officebox-hd">' +
            '<i class="fa fa-file-o"></i><span class="nm">' + esc(name) + '</span>' +
            '<a class="pp-officebox-tab" href="' + esc(url) + '" target="_blank" rel="noopener">' + esc(L.office_newtab) + '</a>' +
            '<button type="button" class="pp-officebox-x" title="' + esc(L.office_close) + '">&times;</button></div>' +
            '<iframe src="' + esc(url) + '" title="' + esc(name) + '" allow="clipboard-read; clipboard-write; fullscreen"></iframe></div>';
        box.addEventListener('click', function (ev) {
            if (ev.target === box || ev.target.closest('.pp-officebox-x')) { officeViewClose(); }
        });
        document.body.appendChild(box);
        document.addEventListener('keydown', officeViewKey, true);
    }
    document.addEventListener('click', function (ev) {
        var a = ev.target.closest && ev.target.closest('a.pp-officeview');
        if (!a || ev.ctrlKey || ev.metaKey || ev.shiftKey || ev.button !== 0) { return; }
        ev.preventDefault();
        officeViewOpen(a.getAttribute('href'), a.getAttribute('data-name') || '');
    });

    function renderFiles() {
        var files = D.files || {};
        var rows = (files.rows || []).map(function (f) {
            return '<tr><td><a class="bl-file" style="margin-top:0;" href="' + esc(linkUrl(f.url)) + '"><i class="fa fa-file-o"></i>' + esc(f.name) + '</a>' + officeViewBtn(f) + '</td>' +
                '<td>' + esc(f.src) + '</td><td>' + esc(user(f.by).n) + '</td><td>' + esc(f.at) + '</td></tr>';
        }).join('');
        document.getElementById('pp-file-rows').innerHTML = rows ||
            '<tr><td colspan="4" style="color:var(--bl-faint);">' + esc(L.files_empty) + '</td></tr>';
        // The list has a ceiling. Saying so beats a list that silently stops
        // and reads as everything there is.
        var cut = document.getElementById('pp-files-partial');
        if (cut) { cut.hidden = files.partial !== true; }
    }
    function uploadFiles(files) {
        if (!files || !files.length) { return; }
        // No upload url means the server would refuse: archived, no edit, or
        // attachments off. The drop zone is hidden then, but a drop onto the
        // table itself could still reach here.
        if (!(D.urls && D.urls.upload)) { toast(L.files_up_fail, true); return; }
        var data = new FormData();
        Array.prototype.forEach.call(files, function (f) { data.append('file_data[]', f); });
        toast(L.posting);
        $.ajax({
            url: D.urls.upload, type: 'POST', data: data, processData: false, contentType: false,
            headers: { 'X-CSRF-TOKEN': CSRF }
        }).done(function () {
            if (!alive()) { return; }
            toast(L.files_up_ok);
            // A reload is how the list learns about the file - unless
            // something half-written is on the page, which a reload would
            // throw away. The refresh button says there is news instead.
            setTimeout(function () {
                if (!alive()) { return; }
                if (safeToRefresh()) { softReload(); } else { markStale(true); }
            }, 600);
        }).fail(function (xhr) {
            if (alive()) { toast(xhrErr(xhr, L.files_up_fail), true); }
        });
    }

