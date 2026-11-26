    /* ------------------------------------------------ Markdown help ----- */
    // MD-1: it is plain at a glance that a box takes Markdown. Under each of
    // the three editors - the comment box, the issue body, a wiki page - a
    // small 「Markdown が使えます」 mark opens a cheat sheet whose lines put
    // their Markdown at the caret, and 「並べて表示」 shows a live preview
    // beside the text (each person's choice, kept in their Exment settings;
    // off at first, tabs only under 900px).
    //
    // Attached from outside the editors' own code: the comment box when the
    // page starts, a body editor when it gets the focus. What is put in goes
    // through setRangeText and an input event, so drafts and the editors'
    // own previews hear of it as of typing. The preview uses the renderer of
    // the real view: renderCommentText for a comment, renderRichText for a
    // body (neither is changed here).
    var MD_SPLIT_MIN = 900;
    var MD_ITEMS = [
        ['md_h', '## ', ''], ['md_b', '**', '**'], ['md_ul', '- ', ''], ['md_ol', '1. ', ''],
        ['md_q', '> ', ''], ['md_code', '`', '`'], ['md_link', '[', '](https://)'],
        ['md_table', '', ''], ['md_task', '- [ ] ', ''], ['md_img', '', '']
    ];
    // What one line puts in: at the start of the line for a block (heading,
    // list, quote, task), around the selection for an inline one.
    function mdInsert(ta, item) {
        if (!ta || ta.readOnly || ta.disabled) { return; }
        // the table's head words are the reader's language (md_table_ins)
        var before = item[0] === 'md_table' ? String(L.md_table_ins || '').replace(/\\n/g, '\n') + '\n' : item[1], after = item[2];
        var from = ta.selectionStart || 0, to = ta.selectionEnd || 0;
        var block = !after && before && before.indexOf('\n') < 0;
        ta.focus();
        if (block) {
            var start = ta.value.lastIndexOf('\n', from - 1) + 1;
            ta.setRangeText(before, start, start, 'end');
        } else {
            var sel = ta.value.slice(from, to);
            ta.setRangeText(before + sel + after, from, to, 'end');
            if (!sel && after) { ta.selectionStart = ta.selectionEnd = from + before.length; }
        }
        ta.dispatchEvent(new Event('input', { bubbles: true }));
    }
    function mdSheetHtml() {
        return MD_ITEMS.map(function (it, n) {
            if (it[0] === 'md_img') {
                return '<div class="pp-md-row note"><span class="lb">' + esc(L.md_img) + '</span><span class="ex">' + esc(L.md_img_how) + '</span></div>';
            }
            var ex = it[0] === 'md_table' ? '| … | … |' : it[1] + '…' + it[2];
            return '<button type="button" class="pp-md-row" data-md="' + n + '"><span class="lb">' + esc(L[it[0]]) + '</span>' +
                '<code class="ex">' + esc(ex) + '</code></button>';
        }).join('');
    }
    function mdSplitOn() { return !!D.md_split && window.innerWidth >= MD_SPLIT_MIN; }
    function mdSaveSplit(on) {
        D.md_split = !!on;
        if (!D.urls || !D.urls.md_split) { return; }
        $.ajax({ url: D.urls.md_split, type: 'POST', data: { on: on ? 1 : 0 }, headers: { 'X-CSRF-TOKEN': CSRF } });
    }
    function ppMdAttach(ta, kind) {
        if (!ta || ta._ppMd) { return; }
        ta._ppMd = true;
        var render = kind === 'comment' ? renderCommentText : renderRichText;
        if (L.md_ph && ta.placeholder.indexOf(L.md_ph) < 0) {
            ta.placeholder = L.md_ph + (ta.placeholder ? ' ' + ta.placeholder : '');
        }
        var bar = document.createElement('div');
        bar.className = 'pp-md-bar';
        bar.innerHTML = '<label class="pp-md-split" title="' + esc(L.md_split_tip) + '"><input type="checkbox"> ' + esc(L.md_split) + '</label>' +
            '<span class="pp-md-wrap"><button type="button" class="pp-md-badge" aria-haspopup="true" aria-expanded="false" title="' + esc(L.md_badge_tip) + '">' +
            '<span class="mk" aria-hidden="true">M↓</span> ' + esc(L.md_badge) + '</button>' +
            '<div class="pp-md-sheet" hidden role="menu">' + mdSheetHtml() + '</div></span>';
        ta.parentNode.insertBefore(bar, ta.nextSibling);
        var side = document.createElement('div');
        side.className = 'pp-md-side bl-md';
        side.hidden = true;
        bar.parentNode.insertBefore(side, bar.nextSibling);
        var host = ta.parentNode;
        var timer = null;
        var draw = function () {
            if (side.hidden) { return; }
            var text = ta.value;
            side.innerHTML = text.trim() ? render(text) : '<p class="pp-md-empty">' + esc(L.empty) + '</p>';
        };
        var apply = function () {
            var on = mdSplitOn();
            host.classList.toggle('pp-md-splitting', on);
            side.hidden = !on;
            bar.querySelector('.pp-md-split input').checked = !!D.md_split;
            draw();
        };
        ta.addEventListener('input', function () {
            if (side.hidden) { return; }
            clearTimeout(timer);
            timer = setTimeout(draw, 200);
        });
        ta.addEventListener('scroll', function () {
            if (side.hidden) { return; }
            var max = ta.scrollHeight - ta.clientHeight;
            var ratio = max > 0 ? ta.scrollTop / max : 0;
            side.scrollTop = ratio * Math.max(0, side.scrollHeight - side.clientHeight);
        });
        bar.addEventListener('change', function (ev) {
            if (!ev.target.closest('.pp-md-split')) { return; }
            mdSaveSplit(ev.target.checked);
            apply();
        });
        bar.addEventListener('click', function (ev) {
            var badge = ev.target.closest('.pp-md-badge');
            var sheet = bar.querySelector('.pp-md-sheet');
            if (badge) {
                ev.preventDefault();
                ev.stopPropagation();
                sheet.hidden = !sheet.hidden;
                badge.setAttribute('aria-expanded', sheet.hidden ? 'false' : 'true');
                return;
            }
            var row = ev.target.closest('.pp-md-row[data-md]');
            if (row) {
                ev.preventDefault();
                ev.stopPropagation();
                sheet.hidden = true;
                mdInsert(ta, MD_ITEMS[parseInt(row.getAttribute('data-md'), 10)]);
            }
        });
        window.addEventListener('resize', function onMdResize() {
            if (!ta.isConnected) { window.removeEventListener('resize', onMdResize); return; }
            apply();
        });
        apply();
    }
    window.ppMdAttach = ppMdAttach;
    document.addEventListener('click', function onPpMdOutside(ev) {
        if (!alive()) { document.removeEventListener('click', onPpMdOutside); return; }
        if (ev.target.closest && ev.target.closest('.pp-md-wrap')) { return; }
        document.querySelectorAll('.pp-md-sheet').forEach(function (s) { s.hidden = true; });
    });
    // The issue body and a wiki page: the editor of the form, found when it
    // gets the focus (the form is drawn by its own code, later).
    document.addEventListener('focusin', function onPpMdFocus(ev) {
        if (!alive()) { document.removeEventListener('focusin', onPpMdFocus); return; }
        var ta = ev.target;
        if (ta && ta.tagName === 'TEXTAREA' && ta.classList.contains('pp-ne-in') && ta.closest('.pp-ne-desc-row')) {
            ppMdAttach(ta, 'body');
        }
    });
    // The comment box, with 書く｜プレビュー as a pair (the preview button of
    // the box is the second half and keeps its own code).
    function mdCommentBox() {
        var ta = document.getElementById('pp-cmt-text');
        if (ta) { ppMdAttach(ta, 'comment'); }
        var write = document.getElementById('pp-cmt-writebtn');
        var prev = document.getElementById('pp-cmt-prev');
        if (!write || !prev) { return; }
        L.ct_write = L.ct_preview;
        var sync = function () { write.classList.toggle('on', prev.hidden); };
        write.addEventListener('click', function (ev) { ev.preventDefault(); cmtPreviewOff(); sync(); });
        try { new MutationObserver(sync).observe(prev, { attributes: true, attributeFilter: ['hidden'] }); } catch (e) { /* old browsers */ }
        sync();
    }
    mdCommentBox();
