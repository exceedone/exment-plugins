    /* ------------------------------------------------ wiki -------------- */
    var currentWiki = null;
    // the folded pages of the Wiki tree, per person and project (ISS00361)
    var WIKI_FOLD_KEY = userKey('wf');
    var WIKI_FOLD = {};
    try {
        (JSON.parse(localStorage.getItem(WIKI_FOLD_KEY) || '[]') || []).forEach(function (id) { WIKI_FOLD['w' + id] = true; });
    } catch (e) { /* ignore */ }
    function wikiFolded(id) { return Object.prototype.hasOwnProperty.call(WIKI_FOLD, 'w' + id); }
    function wikiFoldSave() {
        try {
            var ids = Object.keys(WIKI_FOLD).map(function (k) { return k.slice(1); });
            if (ids.length) { localStorage.setItem(WIKI_FOLD_KEY, JSON.stringify(ids)); } else { localStorage.removeItem(WIKI_FOLD_KEY); }
        } catch (e) { /* ignore */ }
    }
    // the pages with children under a page, the page included when it has some
    function wikiBelow(id) {
        var kids = {};
        ((D.wiki || {}).pages || []).forEach(function (p) {
            if (p.parent) { (kids['w' + p.parent] = kids['w' + p.parent] || []).push(String(p.id)); }
        });
        var out = [], seen = {}, todo = id === null ? Object.keys(kids).map(function (k) { return k.slice(1); }) : [String(id)];
        while (todo.length) {
            var k = todo.shift();
            if (seen[k] || !kids['w' + k]) { continue; }
            seen[k] = true;
            out.push(k);
            todo = todo.concat(kids['w' + k]);
        }
        return out;
    }
    function wikiSet(ids, fold, focusId) {
        ids.forEach(function (k) { if (fold) { WIKI_FOLD['w' + k] = true; } else { delete WIKI_FOLD['w' + k]; } });
        wikiFoldSave();
        renderWiki(currentWiki);
        var again = focusId && document.querySelector('#pp-wtree .pp-wtgl[data-wtgl="' + String(focusId).replace(/[^0-9A-Za-z_-]/g, '') + '"]');
        if (again) { again.focus(); }
    }
    function renderWiki(selectId) {
        var wiki = D.wiki || { enabled: false, pages: [] };
        // With no page to show, the article card is an empty frame: it steps
        // aside (.pp-wempty) until there is something to put in it.
        var wgrid = document.querySelector('#pp-wiki .bl-wiki');
        if (wgrid) { wgrid.classList.toggle('pp-wempty', !wiki.enabled || !wiki.pages.length); }
        if (!wiki.enabled) {
            document.getElementById('pp-wtree').innerHTML = '<div style="color:var(--bl-faint);white-space:normal;">' + esc(L.wiki_none) + '</div>';
            document.getElementById('pp-whead').innerHTML = '';
            document.getElementById('pp-wbody').innerHTML = '';
            return;
        }
        if (!wiki.pages.length) {
            document.getElementById('pp-wtree').innerHTML = '<div style="color:var(--bl-faint);white-space:normal;">' + esc(L.wiki_empty) + '</div>';
            document.getElementById('pp-whead').innerHTML = '';
            document.getElementById('pp-wbody').innerHTML = '';
            return;
        }
        if (selectId === undefined || selectId === null) {
            selectId = currentWiki !== null ? currentWiki : wiki.pages[0].id;
        }
        currentWiki = selectId;
        var kids = {};
        wiki.pages.forEach(function (page) {
            var parent = page.parent || 0;
            (kids[parent] = kids[parent] || []).push(page);
        });
        var byId = {};
        wiki.pages.forEach(function (p) { byId[p.id] = p; });
        // the page shown is never inside a folded branch: its parents open for this drawing
        var openNow = {};
        for (var up = byId[selectId], guard = 0; up && up.parent && guard < 50; up = byId[up.parent], guard++) { openNow[up.parent] = true; }
        var drawn = {};
        // The nesting is unbounded; only the indent class is capped. Every
        // descendant used to be handed level 1, so a grandchild sat at exactly
        // the same indent as its own parent and a deep tree read as two flat
        // levels whatever shape it really had.
        function node(page, level, orphan) {
            // Doubles as a cycle guard: a page whose parent chain loops back on
            // itself would otherwise recurse until the stack gives out.
            if (drawn[page.id]) { return ''; }
            drawn[page.id] = true;
            var has = !!kids[page.id];
            var cls = 'nd' + (level ? ' lv' + Math.min(level, 4) : '') + (orphan ? ' orphan' : '') +
                (String(page.id) === String(selectId) ? ' on' : '');
            var folded = has && wikiFolded(page.id) && !openNow[page.id];
            var tgl = has
                ? '<a href="javascript:void(0);" role="button" class="pp-wtgl" data-wtgl="' + esc(page.id) + '" aria-expanded="' + (folded ? 'false' : 'true') +
                    '" title="' + esc(folded ? L.tree_ctx_open : L.tree_ctx_close) + '">' + (folded ? '▶' : '▼') + '</a>'
                : '<span class="pp-wtgl-sp"></span>';
            var html = '<div class="' + cls + '" data-wiki="' + esc(page.id) + '">' + tgl +
                '<i class="fa ' + (has ? (folded ? 'fa-folder-o' : 'fa-folder-open-o') : 'fa-file-text-o') + '"></i>' + esc(page.title) + '</div>';
            if (has) {
                var inner = '';
                (kids[page.id] || []).forEach(function (child) { inner += node(child, level + 1, false); });
                html += '<div class="pp-wkids"' + (folded ? ' hidden' : '') + '>' + inner + '</div>';
            }
            return html;
        }
        var tree = (kids[0] || []).map(function (page) { return node(page, 0, false); }).join('');
        // A page whose parent was deleted lands in kids[<dead id>], which the
        // walk from the root never visits: the row stays in the database and
        // disappears from the only screen that lists wiki pages. Hang those off
        // the root instead. Testing the parent against the payload - rather
        // than against what has been drawn so far - keeps this independent of
        // the order the pages arrive in, so a child can never be emitted twice.
        wiki.pages.forEach(function (page) {
            var parent = page.parent || 0;
            if (parent && !byId[parent]) { tree += node(page, 0, true); }
        });
        document.getElementById('pp-wtree').innerHTML = tree;

        var page = null;
        wiki.pages.forEach(function (p) { if (String(p.id) === String(selectId)) { page = p; } });
        if (!page) { page = wiki.pages[0]; }
        setTitle(page.title + ' | ' + L.tab_wiki);
        var files = page.files || [];
        document.getElementById('pp-whead').innerHTML = '<span>' + esc(page.title) + '</span>' +
            '<span style="margin-left:auto;display:flex;gap:8px;">' +
            '<span class="bl-btn-sub">' + starBtn('star', D.wiki_table, page.id, page.star, page.my_star) + '</span>' +
            // Offered only to whoever may read the page's history: Exment's
            // compare screen answers anyone else with an error page.
            (page.hist === false || !page.hist_url ? '' :
                '<a class="bl-btn-sub pp-mlink" data-url="' + esc(page.hist_url) + '" data-mtitle="' + esc(L.m_history) + '" data-msub="' + esc(page.title) + '"><i class="fa fa-history"></i> ' + esc(L.wiki_hist) + '</a>') +
            '<a class="bl-btn-main pp-mlink" style="padding:6px 14px;text-decoration:none;" data-url="' + esc(page.edit_url) + '" data-mtitle="' + esc(L.m_edit_wiki) + '" data-msub="' + esc(page.title) + '"><i class="fa fa-pencil"></i> ' + esc(L.wiki_edit) + '</a></span>';
        document.getElementById('pp-wbody').innerHTML =
            '<div class="bl-wmeta"><span><i class="fa fa-pencil"></i> ' + esc(L.wiki_meta) + ': ' + esc(user(page.upby).n) + ' ' + esc(page.up) + '</span>' +
            (files.length ? '<span><i class="fa fa-paperclip"></i> ' + esc(L.wiki_files) + ' (' + files.length + ')</span>' : '') + '</div>' +
            (renderRichText(page.content) || '<p style="color:var(--bl-faint);">' + esc(L.empty) + '</p>') +
            (files.length ? '<p>' + files.map(function (f) {
                return '<a class="bl-file" data-nopjax target="_blank" rel="noopener" href="' + esc(linkUrl(f.url)) + '"><i class="fa fa-file-o"></i>' + esc(f.name) + '</a>' + officeViewBtn(f) + attachTools(f, { editable: page.editable }, 'wiki:' + page.id) + ' ';
            }).join('') + '</p>' : '');
        linkifyIssueRefs(document.getElementById('pp-wbody'));
        // Opened from the pinned list half way down a long article, the next
        // page would otherwise start where the last one was being read.
        // Only when another page was opened: a save that redraws the same
        // page leaves the reader where they were.
        var wgrid = document.querySelector('#pp-wiki .bl-wiki');
        var wnow = String(currentWiki);
        if (wgrid && root._ppWikiShown !== undefined && root._ppWikiShown !== wnow
            && wgrid.getBoundingClientRect().top < ppStickTop()) {
            wgrid.scrollIntoView({ block: 'start' });
        }
        root._ppWikiShown = wnow;
        ppWtreeFit();
        // ISS00257: wiki edits open in the native NED form; no iframe prewarm.
    }

