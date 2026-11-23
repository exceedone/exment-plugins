    /* ------------------------------------------------ shortcuts --------- */
    // Backlog's single-key jumps. A held modifier, focus in any field, or an
    // open form or modal all mean the key belongs to someone else. Clicks go
    // through the tab itself, so feature gating and routing stay in one
    // place; a tab hidden by a feature switch reports no offsetParent and
    // its key does nothing. Unhooks itself once the page is replaced (pjax).
    var SHORTCUT_TABS = { h: 'home', b: 'board', f: 'issues', g: 'gantt', u: 'burndown', w: 'wiki' };
    document.addEventListener('keydown', function onPpShortcut(ev) {
        if (!alive()) { document.removeEventListener('keydown', onPpShortcut); return; }
        if (ev.ctrlKey || ev.metaKey || ev.altKey || ev.shiftKey || ev.defaultPrevented) { return; }
        var t = ev.target;
        if (t && (t.tagName === 'INPUT' || t.tagName === 'TEXTAREA' || t.tagName === 'SELECT' || t.isContentEditable)) { return; }
        if (FM.el && !FM.el.hidden) { return; }
        if (NED.el && !NED.el.hidden) { return; }
        var k = String(ev.key || '').toLowerCase();
        if (SHORTCUT_TABS[k]) {
            var tab = root.querySelector('.bl-tab[data-scr="' + SHORTCUT_TABS[k] + '"]');
            if (tab && tab.offsetParent !== null) { ev.preventDefault(); tab.click(); }
            return;
        }
        if (k === 'a') {
            var add = root.querySelector('.bl-addissue');
            if (add) { ev.preventDefault(); add.click(); }
            return;
        }
        if (ev.key === '/') {
            var jump = document.getElementById('pp-jump');
            if (jump) { ev.preventDefault(); jump.focus(); }
        }
    });

    // Escape closes the dock. Bound on its own rather than added to the
    // shortcut handler above, which ignores every key pressed inside a field
    // - and the dock is nothing but fields.
    document.addEventListener('keydown', function onPpDockKey(ev) {
        if (!alive()) { document.removeEventListener('keydown', onPpDockKey); return; }
        if (ev.key !== 'Escape' || ev.defaultPrevented) { return; }
        if (NED.el && !NED.el.hidden) { return; }
        if (!ppDockIsOpen()) { return; }
        ev.preventDefault();
        ppDockOpen(false);
    });

    /* ------------------------------------------------ boot --------------- */
    // Old-exment style flash: nedSave sets a sessionStorage flag right
    // before reloading. On boot, if the flag is present, surface it as a
    // one-shot toast so the user sees "Saved" on the reloaded page
    // instead of on the pre-reload frame that would flash for 100ms and
    // vanish. Wrapped in try/catch because private-mode Safari throws
    // on any sessionStorage access.
    try {
        var savedFlash = sessionStorage.getItem('pp-ne-saved-flash');
        if (savedFlash) {
            sessionStorage.removeItem('pp-ne-saved-flash');
            setTimeout(function () { toast(savedFlash); }, 60);
        }
    } catch (e) { /* ignore */ }
    renderHead();
    renderFormStyleMenu();
    renderFormStyleBadge();
    renderHome();
    renderIssues();
    renderBurndownSelect();
    renderWiki();
    renderFiles();
    syncUploadUi();
    nedDraftPurge();
    // A bare address (the project list, the dashboard, the top bar) opens the
    // tab last used. A hash is a link to somewhere and is followed as it is.
    if (!location.hash || location.hash === '#') {
        var startTab = lastTab();
        if (startTab && startTab !== 'home' && !screenOff(startTab)
            && document.getElementById('pp-' + startTab) && history.replaceState) {
            history.replaceState(null, '', location.pathname + location.search + '#' + startTab);
        }
    }
    route();
    // Whatever the page we just replaced wanted to warm up is not worth a
    // request any more; this page's own data goes first.
    ppQueue.dropLow();
    // Warming up pays off on the screens the user reads and clicks through.
    // On the settings screen it is pure server load: nothing there opens an
    // issue, a create form or the board. Checked when the timer fires, not
    // when it is set, so landing on #settings and leaving is judged right.
    function warmAllowed() {
        var scr = String(location.hash || '').replace(/^#/, '').split('?')[0] || 'home';
        // A tab nobody is looking at gets nothing warmed either.
        return alive() && !document.hidden && scr !== 'settings';
    }
    setTimeout(function () { if (warmAllowed()) { prefetchDetails(); } }, 600);
    // Warm the create-form schema so the first click on 課題の追加 / Wiki
    // 新規 renders instantly. Subsequent opens read straight from cache.
    setTimeout(function () {
        if (!warmAllowed()) { return; }
        nedPrewarmCreateForm(D.issue_table);
        // ISS00257: the wiki create form is warmed on hover instead.
    }, 1200);
    // First-click accelerator: warm the edit forms the user is most
    // likely to touch next so the very first 編集 / プロジェクト設定 click
    // is a cache hit instead of a 1-2s cold round-trip. Priority order:
    //   1. The row addressed by the URL hash (#issue-224 / #wiki-42),
    //      fired synchronously so it beats an immediate click.
    //   2. The current project (プロジェクト設定 target).
    //   3. Top of the visible issue list, deferred to requestIdleCallback
    //      so it never fights the initial render for the network.
    // Capped at 5 prewarms total per page load to keep server pressure
    // negligible, and skipped entirely on 2g / slow-2g connections.
    (function schedulePrewarmOnLoad() {
        var conn;
        try { conn = navigator.connection || navigator.mozConnection || navigator.webkitConnection; }
        catch (e) { /* Safari private mode / unsupported */ }
        if (conn && (conn.effectiveType === 'slow-2g' || conn.effectiveType === '2g' || conn.saveData)) { return; }
        // Nothing speculative for a tab nobody is looking at.
        if (document.hidden) { return; }
        // ISS00257: 1 (was 3) - each warm-up is a full framework boot on the server.
        var MAX = 1;
        var fired = 0;
        function fire(table, id) {
            if (fired >= MAX) { return; }
            if (!table || id === undefined || id === null || id === '') { return; }
            var key = table + '::' + id;
            if (NED.editFormCache[key] || NED.editFormInflight[key]) { return; }
            fired++;
            nedPrewarmEditForm(table, id);
        }
        var h = String(location.hash || '');
        var mi = h.match(/^#issue-(\d+)/);
        var mw = h.match(/^#wiki-(\d+)/);
        if (mi) { fire(D.issue_table, mi[1]); }
        else if (mw) { fire(D.wiki_table, mw[1]); }
        // The board joins the queue here, ahead of everything speculative.
        // Background work runs one job at a time with a pause between them,
        // so a board queued behind five row warm-ups does not start for the
        // better part of ten seconds - long after the click it was meant to
        // save. It also costs about twice what any of them costs and is a
        // whole screen one click away, where a row warm-up is a guess about
        // a row nobody has opened. The row named by the address bar still
        // goes first: whoever arrived on a link to it is already looking at
        // it, and the board is not what they asked for.
        // ISS00257: the board is no longer embedded in advance (the heaviest
        // server-rendered page); ensureEmbed loads it when its tab is opened.
        if (D.project && D.project.id) { fire(D.project_table, D.project.id); }
        var idle = window.requestIdleCallback || function (fn) { return setTimeout(fn, 300); };
        idle(function () {
            if (!alive()) { return; }
            // Only the rows the user can actually see are worth warming: on
            // the settings or wiki screen the issue list is not even drawn.
            var scr = String(location.hash || '').replace(/^#/, '').split('?')[0] || 'home';
            if (scr !== 'home' && scr !== 'issues' && scr !== 'board') { return; }
            var rows = D.issues || [];
            for (var i = 0; i < rows.length && fired < MAX; i++) {
                fire(D.issue_table, rows[i].id);
            }
        });
    })();
    // Warm the heavy tabs in the background so the first click on them
    // draws immediately. Kicked off after the detail prefetch and the form
    // warm so it never fights them for the network.
    // ISS00257: the gantt is no longer embedded in advance either; it loads
    // when its tab is opened (ensureEmbed).
