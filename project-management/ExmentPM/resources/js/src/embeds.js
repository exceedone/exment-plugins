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
                    host.innerHTML = '<div class="bl-card-box"><div class="bl-boxbody"><i class="fa fa-info-circle"></i> ' +
                        esc(kind === 'gantt' ? L.gantt_none : L.board_none) + '</div></div>';
                } else {
                    host.innerHTML = html;
                    execEmbeddedScripts(host);
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

