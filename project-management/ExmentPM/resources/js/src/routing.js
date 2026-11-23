    /* ------------------------------------------------ routing ----------- */
    /* ------------------------------------------------ recently viewed --- */
    // Every issue opened here is remembered in localStorage, newest first,
    // and the space dashboard and the top bar read the list back into their
    // 最近見た lists. It is kept per person (pp_recent.u<id>, the same key
    // _topbar and list read): one list per browser handed the next user of a
    // shared machine the keys and subjects of what the last one had open.
    // The old shared list cannot be told apart by owner, so it is dropped.
    function recentPush(issueId) {
        var i = issueById(issueId);
        if (!i) { return; }
        var entry = {
            u: pageUrl().replace(/^https?:\/\/[^\/]+/, '') + '#issue-' + keyRef(i),
            k: String(i.key || ''),
            s: String(i.subject || ''),
            p: String((D.project && D.project.name) || ''),
            t: Date.now()
        };
        var key = 'pp_recent.u' + String(D.me || 0);
        var list = [];
        try { list = JSON.parse(localStorage.getItem(key) || '[]'); } catch (e) { list = []; }
        if (!Array.isArray(list)) { list = []; }
        list = list.filter(function (e) { return e && e.u !== entry.u; });
        list.unshift(entry);
        try {
            localStorage.setItem(key, JSON.stringify(list.slice(0, 20)));
            localStorage.removeItem('pp_recent');
        } catch (e) { /* full or blocked */ }
    }

    function show(scr, issueId) {
        // If the inline modal is currently occupying a screen slot (create issue
        // or project settings), take it down before switching screens so the
        // target section renders in a clean state.
        if (NED && NED.el && !NED.el.hidden && NED._target && NED._target !== 'overlay') {
            nedRetireContext();
            NED.el.hidden = true;
            nedUnmount();
            nedClearLayout();
            NED.el.querySelector('.pp-ne-form').hidden = true;
            NED.el.querySelector('.pp-ne-foot').hidden = true;
            NED.el.querySelector('.pp-ne-spin').hidden = false;
            nedShowAlert('', false);
            // This path bypasses nedClose(), so clear the leave-page guard by
            // hand or it would outlive the modal it belongs to.
            nedGuard(false);
            nedDraftBar(false);
            NED_HASH = null;
            NED.rowId = null; NED.columns = null; NED.initial = ''; NED._payload = null;
        }
        // A card was saved on the board and only the board was told. Every
        // other screen here was drawn from what that record used to say, so
        // it is built again rather than shown as it was.
        if (NED.portalStale && scr !== 'board') {
            NED.portalStale = false;
            refreshData(true, function (ok) { if (!ok) { location.reload(); } });
        }

        // Park where this screen was before leaving it.
        if (scrollKey !== null) { SCROLL_POS[scrollKey] = window.scrollY; }
        root.querySelectorAll('.bl-screen').forEach(function (el) { el.classList.remove('on'); });
        root.querySelectorAll('.bl-tab').forEach(function (el) {
            el.classList.toggle('active', el.getAttribute('data-scr') === (scr === 'issue' ? 'issues' : scr));
        });
        var el = document.getElementById('pp-' + scr);
        if (!el) { scr = 'home'; el = document.getElementById('pp-home'); }
        el.classList.add('on');
        currentScreen = scr;
        lastTabSave(scr);
        if (scr !== 'issue') { detailEpoch++; cmtSetBusy(false); }
        // Read-only while archived: the comment, status and upload routes all
        // refuse, so the composer is not offered rather than offered and
        // bounced. The quick-edit fields inside it are gated per issue by
        // res.editable in renderQuickEdit as well.
        ppDockShow(scr === 'issue' && !(D.project && D.project.archived));
        if (scr === 'issue') { renderDetail(issueId); recentPush(issueId); }
        if (scr === 'board' || scr === 'gantt') { ensureEmbed(scr); }
        // renderWiki names the page it landed on, which beats "Wiki".
        if (scr !== 'wiki') { setTitle(screenTitle(scr, issueId)); }
        var bucket = scrollBucket(scr, issueId);
        var want = SCROLL_POS[bucket] || 0;
        scrollKey = bucket;
        // One frame later: the screen only just gained .on, and until it has
        // laid out the document is still the old, shorter height, so the
        // browser would clamp the offset to whatever fits.
        window.requestAnimationFrame(function () {
            window.scrollTo(0, want);
            // Same reason: the dock is fixed to the window, but it takes its
            // left edge and its width from a content area that has no
            // measurable box until the screen it belongs to has been laid out.
            if (scr === 'issue') { ppDockFit(); }
            ppPinFit();
            ppListFit();
        });
    }
    // Coming out of a ticket used to land you at the top of the issue list
    // every time. On a 200-row list that is a punishment for using the back
    // button. Detail screens get a bucket per issue so each still opens at the
    // top, and returning to one you had scrolled through keeps your place.
    //
    // Dormant on the current admin theme: styles.css gives html and body
    // height:100%, so the window never scrolls at all - overflowing content is
    // simply out of reach, and window.scrollY is always 0. This bookkeeping is
    // then a no-op that costs nothing, and it starts working the day that rule
    // becomes min-height. The unconditional scrollTo(0, 0) it replaces was
    // equally inert, so nothing regressed either way.
    var SCROLL_POS = {};
    var scrollKey = null;
    function scrollBucket(scr, issueId) {
        return scr === 'issue' ? 'issue:' + (issueId || '') : scr;
    }
    // The tab strip already holds a translated label for every screen, so the
    // title reuses it rather than adding a parallel set of keys to maintain.
    function screenTitle(scr, issueId) {
        if (scr === 'issue') {
            var i = issueById(issueId);
            if (i) { return i.key + ' ' + i.subject; }
        }
        var want = (scr === 'issue') ? 'issues' : scr;
        var label = '';
        root.querySelectorAll('.bl-tab').forEach(function (t) {
            if (t.getAttribute('data-scr') === want) { label = t.textContent.trim(); }
        });

        return label;
    }
    // The hash carries a screen and, on the issue list, the filter state after
    // a '?'. Splitting them here keeps every matcher below working on the
    // screen name alone.
    // The path out of any hash, not just the one in the address bar: the
    // listener below is handed the hash it is leaving.
    function parseHashOf(hash) {
        var raw = (hash || '#home').slice(1);
        var q = raw.indexOf('?');

        return (q < 0 ? raw : raw.slice(0, q)) || 'home';
    }
    // The screen a panel was opened from, out of the hash it replaced. A
    // panel's own address is not a screen to come back to: #new names the
    // create form, and ?edit names the editor, so neither is kept. The rest
    // of a query is - a filtered list is its query.
    function nedBackFrom(hash) {
        if (!hash) { return null; }
        var path = parseHashOf(hash);
        if (path === 'new') { return null; }
        var q = hash.indexOf('?');
        if (q >= 0 && ('edit' in parseQuery(hash.slice(q + 1)))) { return '#' + path; }

        return hash;
    }
    function parseHash() {
        var raw = (location.hash || '#home').slice(1);
        var q = raw.indexOf('?');

        return {
            path: (q < 0 ? raw : raw.slice(0, q)) || 'home',
            query: q < 0 ? '' : raw.slice(q + 1)
        };
    }
    function parseQuery(s) {
        var out = {};
        if (!s) { return out; }
        s.split('&').forEach(function (pair) {
            if (!pair) { return; }
            var i = pair.indexOf('=');
            var k = i < 0 ? pair : pair.slice(0, i);
            var v = i < 0 ? '' : pair.slice(i + 1);
            try { out[decodeURIComponent(k)] = decodeURIComponent(v.replace(/\+/g, ' ')); }
            catch (e) { out[k] = v; }
        });

        return out;
    }
    // Which screens this project actually has. A feature switched off in
    // the settings tab removes its tab, so the hash behind it must not
    // quietly open a screen the user can no longer navigate back to.
    var FEAT = (D.project && D.project.features) || {};
    function screenOff(path) {
        var scr = /^wiki-\d+$/.test(path) ? 'wiki' : path;
        if (scr === 'wiki') { return FEAT.wiki === false; }
        if (scr === 'files') { return FEAT.file === false; }
        if (scr === 'gantt' || scr === 'burndown') { return FEAT.gantt === false; }
        if (scr === 'settings') { return !(D.can && D.can.manage); }
        // #new from a bookmark or the top bar of an earlier page: the form
        // behind it would only be refused.
        if (scr === 'new') { return !CAN_ADD_ISSUE; }
        return false;
    }
    // Open what the hash asks for - unless that is the panel already open.
    // Routing is not only navigation here: the same hash is routed again when
    // a reader answers "keep editing" to the back button, and re-rendering
    // the form then would throw away the edits they just chose to keep.
    function nedHashOpen(at, back, open) {
        if (NED_HASH && NED_HASH.at === at && NED.el && !NED.el.hidden) { return; }
        open();
        NED_HASH = { at: at, back: back };
    }
    // ...and close it when the hash stops asking. Only the overlay is closed
    // here: a panel that takes a screen's place is taken down by show() on
    // the way in, and taken down without asking, because the draft in
    // localStorage is what answers for the text. The overlay has no screen
    // change to ride on and - opened by the hash - no history entry of its
    // own to pop, so this is where it goes. It still asks, because that is
    // what the back button does over the board today.
    function nedHashSync() {
        if (!NED_HASH || location.hash.slice(1) === NED_HASH.at) { return; }
        if (!NED.el || NED.el.hidden || NED._target !== 'overlay') { NED_HASH = null; return; }
        var at = NED_HASH.at;
        // nedClose() clears NED_HASH when it closes, and leaves it alone when
        // the reader keeps editing - so the address can simply go back.
        if (nedClose()) { return; }
        location.hash = at;
    }
    function route() {
        var h = parseHash();
        var path = h.path;
        nedHashSync();
        if (screenOff(path)) {
            if (history.replaceState) { history.replaceState(null, '', '#home'); }
            show('home');
            return;
        }
        var m = path.match(/^issue-(.+)$/);
        if (m) {
            var id = resolveIssueRef(m[1]);
            if (id === null && arOn() && !AR.loaded) {
                // Maybe an archived one: not in the page until asked for. Its
                // key is the project's key and its id ("KOYAMA-234"), so the
                // detail can be fetched on its own (fetchDetails asks the
                // server, which checks it belongs here).
                var km = String(m[1]).toUpperCase().match(/^(.+)-(\d+)$/);
                if (km && km[1] === String(D.project.key || '#').toUpperCase()) {
                    id = km[2];
                } else {
                    // An issue key (ISS00239) or an old project key names
                    // nothing until the archived rows are here: fetch them,
                    // then ask again. AR.loaded keeps this to one round.
                    loadArchived().then(function () { if (alive()) { route(); } },
                        function () { if (alive()) { show('issues'); } });
                    return;
                }
            }
            // ISS00440: an older address (#issue-PROJECTKEY-id, an old project
            // key, the bare id) is rewritten to the issue key in place.
            var canon = issueById(id);
            if (id !== null && canon && history.replaceState && String(m[1]) !== keyRef(canon)) {
                path = 'issue-' + keyRef(canon);
                history.replaceState(null, '', '#' + path + (h.query ? '?' + h.query : ''));
            }
            if (id === null) {
                // A key this project is not carrying: a stale bookmark, or a
                // link copied out of a different project. The list is a more
                // useful place to land than an empty detail pane.
                if (history.replaceState) { history.replaceState(null, '', '#issues'); }
                show('issues');
                return;
            }
            show('issue', id);
            // ?edit is the editor's address, written on top of the issue's own.
            // It opens after show() has put up the screen it mounts inside.
            if ('edit' in parseQuery(h.query)) {
                nedHashOpen(path + '?' + h.query, '#' + path, function () {
                    openNedit(D.issue_table, id);
                });
            }
            return;
        }
        var w = path.match(/^wiki-(\d+)$/);
        if (w) { currentWiki = w[1]; show('wiki'); renderWiki(w[1]); return; }
        // A bare #wiki lands on whichever page is current - renderWiki() picks
        // the first one when there is none. Routing it through renderWiki
        // rather than show() alone is also what names the page in the title.
        if (path === 'wiki') { show('wiki'); renderWiki(); return; }
        if (path === 'new') {
            // The hash stays #new for as long as the panel is open. Rewriting
            // it to #issues here read as a tidy-up and was the thing that made
            // a reload, or a copied link, open the list instead of the form.
            //
            // A screen still has to be standing behind it: this panel takes
            // the place of one rather than floating over it, so arriving cold
            // on #new with nothing shown yet would leave an empty page once it
            // closed. The list is where a new issue joins.
            if (!currentScreen) { show('issues'); }
            nedHashOpen('new', NED_BACK || '#issues', function () {
                openNcreate(D.issue_table);
            });
            return;
        }
        // The two screens that stay on display behind the editor. Their
        // address carries the row being edited, so a reload comes back to the
        // chart or the columns with the same panel open on them.
        if (path === 'board' || path === 'gantt') {
            show(path);
            var row = parseQuery(h.query).edit;
            if (row && issueById(row)) {
                nedHashOpen(path + '?' + h.query, '#' + path, function () {
                    neditOverScreen(D.issue_table, row, true);
                });
            } else if (row && history.replaceState) {
                // A row this project is not carrying: a stale link, or an
                // issue that has moved since. The screen itself is still
                // where the link meant to land.
                history.replaceState(null, '', '#' + path);
            }
            return;
        }
        if (path === 'issues') {
            // A query in the URL is the whole truth - a link has to reproduce
            // exactly what the sender was looking at. With no query at all the
            // saved personal default applies and is then written back into the
            // address bar, which is what stops a sticky filter from quietly
            // emptying the list days later with nothing on screen to explain it.
            if (h.query) { filterFromQuery(parseQuery(h.query)); }
            renderIssues();
            filterToUrl();
        }
        show(path);
    }
    window.addEventListener('hashchange', function onHash(ev) {
        if (!alive()) { window.removeEventListener('hashchange', onHash); return; }
        var from = (ev && ev.oldURL && ev.oldURL.indexOf('#') >= 0)
            ? ev.oldURL.slice(ev.oldURL.indexOf('#'))
            : '';
        NED_BACK = nedBackFrom(from);
        route();
        // The other moment the screen is likely to be behind. These tabs never
        // leave the page, so nothing in the browser announces the move - and
        // the board and the gantt are edited inside their own page, which
        // makes them precisely the screens whose changes this data cannot see.
        // The filter rewrites the address bar with replaceState and so never
        // reaches here.
        var path = parseHash().path;
        if (path !== LIVE.path) {
            LIVE.path = path;
            checkStamp(CHECK_GAP_NAV);
        }
    });

