    /* --------------------------------------- lazy embedded widgets ------ */
    // Kanban / gantt widgets are heavy (~1s each on a busy project) and
    // most sessions never open those tabs. Loading them the moment the tab
    // is switched on, and topping them up in the background afterwards,
    // keeps the portal home instant no matter how many cards there are.
    var EMBEDS = {
        board: { url: (D.urls || {}).embed_board || '' },
        gantt: { url: (D.urls || {}).embed_gantt || '' }
    };
    function ensureEmbed(kind, cb, low) {
        var slot = EMBEDS[kind];
        if (!slot || !slot.url) { if (cb) { cb(); } return; }
        var host = document.getElementById('pp-embed-' + kind);
        if (!host) { if (cb) { cb(); } return; }
        if (slot.loaded) { if (cb) { cb(); } return; }
        if (slot.loading) {
            slot.cbs.push(cb || function () {});
            // Queued as background work and wanted for real now: jump it.
            if (!low && slot.job) { ppQueue.promote(slot.job); }
            return;
        }
        slot.loading = true;
        slot.cbs = cb ? [cb] : [];
        // These two are whole pages - the heaviest thing the portal asks for.
        // Queued like everything else, so warming them up in the background
        // can never be what the user's next click is waiting behind.
        slot.job = ppQueue(function () {
            return $.ajax({ url: slot.url, cache: false, dataType: 'text' })
            .done(function (html) {
                if (typeof html !== 'string' || !html.length) {
                    // M10: with the way to the same issues as a list.
                    host.innerHTML = '<div class="bl-card-box"><div class="bl-boxbody"><i class="fa fa-info-circle"></i> ' +
                        esc(kind === 'gantt' ? L.gantt_none : L.board_none) +
                        ' <a class="pp-none-list" href="#issues">' + esc(L.tab_issues) + ' <i class="fa fa-angle-right"></i></a></div></div>';
                } else {
                    host.innerHTML = html;
                    execEmbeddedScripts(host);
                    if (kind === 'board') { kbWatch(host); }
                }
                slot.loaded = true;
                slot.loading = false;
                slot.cbs.forEach(function (fn) { fn(); });
                // The data moved while this html was on its way, so what
                // just landed is already the old board. Marking it unread
                // afterwards would not help: it has been marked loaded, and
                // nothing is going to ask for it again.
                if (slot.refetch) {
                    slot.refetch = false;
                    slot.loaded = false;
                    ensureEmbed(kind, null, true);
                }
            })
            .fail(function (xhr) {
                slot.loading = false;
                // Release whoever was waiting on it either way, or a caller
                // sits for ever on a callback that is never coming.
                slot.cbs.forEach(function (fn) { fn(); });
                host.innerHTML = '<div class="bl-card-box"><div class="bl-boxbody" style="color:#c0392b;">' +
                    '<i class="fa fa-warning"></i> ' + esc(xhrErr(xhr, L.post_fail)) + '</div></div>';
            });
        }, low).catch(function () { });
    }
    // innerHTML swaps the markup but leaves inline scripts inert; the kanban
    // and gantt widgets both keep their state and behaviour in one such
    // block, so we re-create every <script> node in order and let the
    // browser run them the second time around.
    function execEmbeddedScripts(container) {
        var scripts = container.querySelectorAll('script');
        for (var i = 0; i < scripts.length; i++) {
            var old = scripts[i];
            var ns = document.createElement('script');
            if (old.type) { ns.type = old.type; }
            if (old.id) { ns.id = old.id; }
            if (old.src) {
                ns.src = old.src;
                if (old.async) { ns.async = true; }
                if (old.defer) { ns.defer = true; }
            } else {
                ns.text = old.textContent;
            }
            old.parentNode.replaceChild(ns, old);
        }
    }
    /* ------------------------------------------------ board aids (UX-E) - */
    // The board is Exment's own kanban (.kb-scroll > .kb-board > lanes of
    // .kb-col). Around it the portal adds only what helps reading it here:
    // ‹ › to move sideways while there is more (S9), and on a phone a row of
    // the status columns to jump to one (M4) - the columns snap one at a time
    // there (stylesheet). The kanban may draw itself again (its filters), so
    // the aids are put back whenever its markup changes.
    function kbCols(scroll) {
        var seen = {};
        var out = [];
        Array.prototype.forEach.call(scroll.querySelectorAll('.kb-col[data-col]'), function (col) {
            var id = col.getAttribute('data-col');
            var n = parseInt(((col.querySelector('.kb-col-count') || {}).textContent || '0'), 10) || 0;
            if (own(seen, id)) { seen[id].n += n; return; }
            var t = col.querySelector('.kb-col-title');
            seen[id] = { id: id, l: t ? String(t.textContent || '').trim() : id, n: n, el: col };
            out.push(seen[id]);
        });
        return out;
    }
    // The chips of the columns, drawn again when the columns or their counts
    // change (the kanban fills its columns after it is put on the page).
    function kbChips(scroll) {
        var nav = scroll.parentNode ? scroll.parentNode.querySelector(':scope > .pp-kb-nav') : null;
        var box = nav ? nav.querySelector('.pp-kb-chips') : null;
        if (!box) { return; }
        var html = kbCols(scroll).map(function (c) {
            return '<button type="button" class="pp-kb-chip" data-col="' + esc(c.id) + '">' + esc(c.l) + '<span class="n">' + c.n + '</span></button>';
        }).join('');
        if (box._ppHtml !== html) { box._ppHtml = html; box.innerHTML = html; }
    }
    function kbFit(scroll) {
        var wrap = scroll.parentNode;
        var nav = wrap ? wrap.querySelector(':scope > .pp-kb-nav') : null;
        if (!nav) { return; }
        var max = scroll.scrollWidth - scroll.clientWidth;
        var x = scroll.scrollLeft;
        wrap.classList.toggle('pp-kb-more-r', x < max - 2);
        wrap.classList.toggle('pp-kb-more-l', x > 2);
        nav.querySelector('.pp-kb-prev').disabled = x <= 2;
        nav.querySelector('.pp-kb-next').disabled = x >= max - 2;
        // the column whose left edge is nearest the view's
        var best = null;
        nav.querySelectorAll('.pp-kb-chip').forEach(function (c) {
            var col = scroll.querySelector('.kb-col[data-col="' + c.getAttribute('data-col') + '"]');
            var d = col ? Math.abs(col.offsetLeft - x) : Infinity;
            if (best === null || d < best.d) { best = { c: c, d: d }; }
            c.classList.remove('on');
        });
        if (best) { best.c.classList.add('on'); }
    }
    function kbAids(host) {
        var scroll = host.querySelector('.kb-scroll');
        if (!scroll || scroll._ppKb) { return; }
        scroll._ppKb = true;
        var wrap = scroll.parentNode;
        wrap.classList.add('pp-kb-wrap');
        var nav = document.createElement('div');
        nav.className = 'pp-kb-nav';
        nav.innerHTML = '<div class="pp-kb-chips" role="group" aria-label="' + esc(L.kb_cols) + '"></div>' +
            '<button type="button" class="pp-kb-prev" title="' + esc(L.kb_prev) + '" aria-label="' + esc(L.kb_prev) + '"><i class="fa fa-angle-left"></i></button>' +
            '<button type="button" class="pp-kb-next" title="' + esc(L.kb_next) + '" aria-label="' + esc(L.kb_next) + '"><i class="fa fa-angle-right"></i></button>';
        wrap.insertBefore(nav, scroll);
        kbChips(scroll);
        var step = function (dir) {
            var w = Math.max(240, Math.round(scroll.clientWidth * 0.8));
            scroll.scrollBy ? scroll.scrollBy({ left: dir * w, behavior: 'smooth' }) : (scroll.scrollLeft += dir * w);
        };
        nav.querySelector('.pp-kb-prev').addEventListener('click', function () { step(-1); });
        nav.querySelector('.pp-kb-next').addEventListener('click', function () { step(1); });
        nav.querySelector('.pp-kb-chips').addEventListener('click', function (ev) {
            var chip = ev.target.closest('.pp-kb-chip');
            var col = chip ? scroll.querySelector('.kb-col[data-col="' + chip.getAttribute('data-col') + '"]') : null;
            if (!col) { return; }
            var left = col.offsetLeft - (scroll.querySelector('.kb-board') || scroll).offsetLeft;
            scroll.scrollTo ? scroll.scrollTo({ left: Math.max(0, left), behavior: 'smooth' }) : (scroll.scrollLeft = left);
        });
        var pending = false;
        scroll.addEventListener('scroll', function () {
            if (pending) { return; }
            pending = true;
            window.requestAnimationFrame(function () { pending = false; kbFit(scroll); });
        }, { passive: true });
        kbFit(scroll);
    }
    function kbWatch(host) {
        kbAids(host);
        if (host._ppKbMo || !window.MutationObserver) { return; }
        var timer = null;
        host._ppKbMo = new MutationObserver(function () {
            if (!alive()) { host._ppKbMo.disconnect(); return; }
            clearTimeout(timer);
            timer = setTimeout(function () {
                kbAids(host);
                var sc = host.querySelector('.kb-scroll');
                if (sc) { kbChips(sc); kbFit(sc); }
            }, 60);
        });
        host._ppKbMo.observe(host, { childList: true, subtree: true });
        window.addEventListener('resize', function onKbResize() {
            if (!alive()) { window.removeEventListener('resize', onKbResize); return; }
            var sc = host.querySelector('.kb-scroll');
            if (sc) { kbFit(sc); }
        });
    }
    // A board drawn into the page itself (lazy widgets off) gets the same aids.
    (function () {
        var inline = document.getElementById('pp-board');
        if (inline && !inline.querySelector('.pp-embed') && inline.querySelector('.kb-scroll')) { kbWatch(inline); }
    }());
