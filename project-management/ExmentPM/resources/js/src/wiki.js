    /* ------------------------------------------------ wiki -------------- */
    var currentWiki = null;
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
        wiki.pages.forEach(function (p) { byId[p.id] = true; });
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
            var html = '<div class="' + cls + '" data-wiki="' + esc(page.id) + '">' +
                '<i class="fa ' + (has ? 'fa-folder-o' : 'fa-file-text-o') + '"></i>' + esc(page.title) + '</div>';
            (kids[page.id] || []).forEach(function (child) { html += node(child, level + 1, false); });
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
                return '<a class="bl-file" href="' + esc(linkUrl(f.url)) + '"><i class="fa fa-file-o"></i>' + esc(f.name) + '</a>' + officeViewBtn(f) + ' ';
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

