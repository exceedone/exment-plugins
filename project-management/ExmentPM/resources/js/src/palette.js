    /* ------------------------------------------------ command palette ---- */
    // L2: Ctrl+K (⌘K on a Mac) opens one box that lists every action of the
    // screen with its key - the tabs, adding an issue, the search, and on an
    // issue: edit, comment, status, next status, take it, watch, favorite,
    // copy the link, the one before / after. Typing narrows the list; a key
    // or words of a subject also list the issues of the project (the same
    // match as the # typeahead, taMatch). Enter or a click runs the action.
    //
    // Each action presses the button that is already on the screen, so what
    // a feature switch, a role or an archived project hides is not offered,
    // and nothing here decides again who may do what.
    //
    // The key: Exment binds no Ctrl+K of its own (its grid, the workflow
    // editor, the board and the file server use other keys); the browser's
    // own Ctrl+K (its search box) gives way to preventDefault. Inside the
    // issue form or the edit modal the key is left alone.
    var PAL = { el: null, items: [], at: 0 };
    var PAL_ISSUES = 8;
    // A visible element of the page, or null (a hidden tab has no offsetParent).
    function palShown(sel, scope) {
        var el = (scope || document).querySelector(sel);
        return el && el.offsetParent !== null ? el : null;
    }
    function palPress(sel) {
        return function () {
            var el = palShown(sel) || document.querySelector(sel);
            if (el) { el.click(); }
        };
    }
    // Every action that can be done now: { g: group, l: label, k: key, run }.
    function palCommands() {
        var out = [];
        var keys = {};
        Object.keys(SHORTCUT_TABS).forEach(function (k) { keys[SHORTCUT_TABS[k]] = k; });
        Array.prototype.forEach.call(root.querySelectorAll('.bl-tab[data-scr]'), function (tab) {
            if (tab.offsetParent === null) { return; }
            var scr = tab.getAttribute('data-scr');
            out.push({ g: L.kh_move, l: String(tab.textContent || '').trim(), k: keys[scr] || '', run: function () { tab.click(); } });
        });
        if (document.querySelector('#pp-top .pp-top-addmain')) {
            out.push({ g: L.kh_list, l: L.add_issue, k: 'a', run: palPress('#pp-top .pp-top-addmain') });
        }
        if (document.getElementById('pp-jump')) {
            out.push({ g: L.kh_list, l: L.kh_search, k: '/', run: function () {
                var jump = document.getElementById('pp-jump');
                var tab = root.querySelector('.bl-tab[data-scr="issues"]');
                if (tab && String(currentScreen) !== 'issues') { tab.click(); }
                if (jump) { jump.focus(); }
            } });
        }
        if (String(currentScreen) === 'issue' && currentIssue !== null) {
            var main = document.getElementById('pp-d-main');
            var at = function (sel) { return main ? palShown(sel, main) : null; };
            var next = at('.pp-next-status');
            if (next) { out.push({ g: L.kh_issue, l: String(next.textContent || '').trim(), k: '', run: function () { next.click(); } }); }
            if (window.ppStMenuFromKey && at('.bl-dsum')) {
                out.push({ g: L.kh_issue, l: L.kh_status, k: 's', run: function () { window.ppStMenuFromKey(); } });
            }
            var mine = palAssignMe();
            if (mine) { out.push({ g: L.kh_issue, l: L.pal_assign_me, k: '', run: mine }); }
            if (main && main.querySelector('.bl-dhead .pp-mlink')) { out.push({ g: L.kh_issue, l: L.edit, k: 'e', run: palPress('#pp-d-main .bl-dhead .pp-mlink') }); }
            var dock = document.getElementById('pp-cmtdock');
            if (dock && !dock.hidden && typeof ppDockOpen === 'function') {
                out.push({ g: L.kh_issue, l: L.kh_comment, k: 'c', run: function () { ppDockOpen(true); } });
            }
            var watch = main ? main.querySelector('.pp-watch') : null;
            if (watch) { out.push({ g: L.kh_issue, l: watch.classList.contains('on') ? L.unwatch : L.watch, k: '', run: function () { watch.click(); } }); }
            var fav = main ? main.querySelector('.pp-fav') : null;
            if (fav) { out.push({ g: L.kh_issue, l: L.fav, k: '', run: function () { fav.click(); } }); }
            if (main && main.querySelector('.pp-linkcopy')) { out.push({ g: L.kh_issue, l: L.link_copy, k: '', run: palPress('#pp-d-main .pp-linkcopy') }); }
            if (main && main.querySelector('.bl-inav a:first-child:not(.off)')) { out.push({ g: L.kh_issue, l: L.nav_prev, k: '[', run: palPress('#pp-d-main .bl-inav a:first-child:not(.off)') }); }
            if (main && main.querySelector('.bl-inav a:last-child:not(.off)')) { out.push({ g: L.kh_issue, l: L.nav_next, k: ']', run: palPress('#pp-d-main .bl-inav a:last-child:not(.off)') }); }
        }
        out.push({ g: L.kh_other, l: L.kh_help, k: '?', run: function () { keyHelp(true); } });
        return out;
    }
    // "Take it": the issue on screen gets the reader as an assignee, through
    // the same save as the info box (qfApply, with its undo). Only where the
    // reader may edit the issue and is not on it yet.
    function palAssignMe() {
        var det = currentIssue === null ? null : own(DET, String(currentIssue));
        if (!det || !det.editable || !D.me || typeof qfApply !== 'function') { return null; }
        var i = issueById(currentIssue) || det.issue;
        if (!i) { return null; }
        var was = (qfValue(i, 'as') || []).map(String);
        if (was.indexOf(String(D.me)) >= 0) { return null; }
        var issueId = currentIssue;
        return function () {
            qfApply(issueId, { as: was.concat([String(D.me)]) }, { as: was }, function (r) {
                if (!r.ok) { toast(r.stale ? L.qf_stale : (r.error || L.dock_save_fail), true); }
            });
        };
    }
    // The words typed, each found somewhere in the label (any order, any case).
    function palMatch(commands, query) {
        var words = String(query || '').toLowerCase().split(/\s+/).filter(Boolean);
        if (!words.length) { return commands.slice(); }
        return commands.filter(function (c) {
            var text = (String(c.l) + ' ' + String(c.g) + ' ' + String(c.k)).toLowerCase();
            return words.every(function (w) { return text.indexOf(w) >= 0; });
        });
    }
    // The issues of the project for what was typed: the # typeahead's match.
    function palIssues(query) {
        var q = String(query || '').trim();
        if (!q) { return []; }
        var rows = (D.issues || []).map(function (i) { return { label: i.subject, key: i.key, id: i.id }; });
        return taMatch(rows, q).slice(0, PAL_ISSUES).map(function (r) {
            return { g: L.pal_issues, l: r.label, k: '', key: r.key, run: function () { location.hash = issueHash(r.id); } };
        });
    }
    function palRender() {
        if (!PAL.el) { return; }
        var q = PAL.el.querySelector('input').value;
        PAL.items = palMatch(palCommands(), q).concat(palIssues(q));
        if (PAL.at >= PAL.items.length) { PAL.at = Math.max(0, PAL.items.length - 1); }
        var list = PAL.el.querySelector('.ls');
        var group = null;
        list.innerHTML = PAL.items.length ? PAL.items.map(function (c, n) {
            var head = c.g !== group ? '<li class="gt" role="presentation">' + esc(c.g) + '</li>' : '';
            group = c.g;
            return head + '<li class="it' + (n === PAL.at ? ' on' : '') + '" role="option" id="pp-pal-' + n + '" data-n="' + n + '" aria-selected="' + (n === PAL.at ? 'true' : 'false') + '">' +
                (c.key ? '<span class="ky">' + esc(c.key) + '</span>' : '') +
                '<span class="lb">' + esc(c.l) + '</span>' +
                (c.k ? String(c.k).split(' ').map(function (x) { return '<kbd>' + esc(x) + '</kbd>'; }).join('') : '') + '</li>';
        }).join('') : '<li class="none">' + esc(L.pal_none) + '</li>';
        PAL.el.querySelector('input').setAttribute('aria-activedescendant', PAL.items.length ? 'pp-pal-' + PAL.at : '');
        var cur = list.querySelector('.it.on');
        if (cur) { try { cur.scrollIntoView({ block: 'nearest' }); } catch (e) { /* old browsers */ } }
    }
    function palRun(n) {
        var c = PAL.items[n];
        palOpen(false);
        if (c && c.run) { c.run(); }
    }
    function palOpen(show) {
        if (!show) {
            if (PAL.el) { PAL.el.remove(); PAL.el = null; }
            return;
        }
        if (PAL.el) { PAL.el.querySelector('input').focus(); return; }
        keyHelp(false);
        PAL.at = 0;
        var box = document.createElement('div');
        box.id = 'pp-pal';
        box.className = 'pp-pal';
        box.setAttribute('role', 'dialog');
        box.setAttribute('aria-modal', 'true');
        box.setAttribute('aria-label', L.pal_title);
        box.innerHTML = '<div class="in"><div class="hd"><i class="fa fa-search"></i>' +
            '<input type="text" autocomplete="off" role="combobox" aria-expanded="true" aria-controls="pp-pal-ls" placeholder="' + esc(L.pal_ph) + '" aria-label="' + esc(L.pal_ph) + '">' +
            '<button type="button" class="x" aria-label="' + esc(L.m_close) + '">&times;</button></div>' +
            '<ul class="ls" id="pp-pal-ls" role="listbox"></ul></div>';
        box.addEventListener('click', function (ev) {
            if (ev.target === box || (ev.target.closest && ev.target.closest('.x'))) { palOpen(false); return; }
            var it = ev.target.closest && ev.target.closest('.it');
            if (it) { palRun(parseInt(it.getAttribute('data-n'), 10)); }
        });
        var input = box.querySelector('input');
        input.addEventListener('input', function () { PAL.at = 0; palRender(); });
        input.addEventListener('keydown', function (ev) {
            if (ev.isComposing) { return; }
            if (ev.key === 'ArrowDown' || ev.key === 'ArrowUp') {
                ev.preventDefault();
                if (PAL.items.length) { PAL.at = (PAL.at + (ev.key === 'ArrowDown' ? 1 : -1) + PAL.items.length) % PAL.items.length; }
                palRender();
            } else if (ev.key === 'Enter') {
                ev.preventDefault();
                palRun(PAL.at);
            } else if (ev.key === 'Escape') {
                ev.preventDefault();
                ev.stopPropagation();
                palOpen(false);
            }
        });
        (root.closest('.pmportal') || root).appendChild(box);
        PAL.el = box;
        palRender();
        input.focus();
    }
    function palKey(ev) {
        return (ev.ctrlKey || ev.metaKey) && !ev.altKey && !ev.shiftKey && String(ev.key || '').toLowerCase() === 'k';
    }
    document.addEventListener('keydown', function onPpPalette(ev) {
        if (!alive()) { document.removeEventListener('keydown', onPpPalette); palOpen(false); return; }
        if (!palKey(ev) || ev.defaultPrevented || ev.isComposing) { return; }
        if ((FM.el && !FM.el.hidden) || (NED.el && !NED.el.hidden)) { return; }
        ev.preventDefault();
        palOpen(!PAL.el);
    });
