    /* ------------------------------------------------ issues ------------ */
    // rl: one of the other people of an issue (ISS02931), as "<role>:<user>"
    // - sa:12 is "12 is a sub-assignee"; df: the difficulty; at: the custom
    // attributes' filters, as one string (atFilterParse, ISS02939).
    var FILTER = { st: '', kw: '', as: '', ty: '', pr: '', ms: '', cat: '', rd: '', q: '', rl: '', df: '', at: '' };
    var FILTER_FIELDS = ['st', 'q', 'as', 'ty', 'pr', 'ms', 'cat', 'rd', 'rl', 'df', 'at', 'kw'];
    // These were per browser, so a shared machine handed the next person the
    // last one's filters and saved conditions. They are per person now. What
    // is still under the old key is taken over once, by whoever opens the
    // project first, and the old key goes - a filter is not worth the
    // surprise of losing every saved condition on the day this shipped.
    function userKey(kind) {
        var key = 'pmportal.' + kind + '.u' + String(D.me || 0) + '.' + D.project.id;
        try {
            var legacy = 'pmportal.' + kind + '.' + D.project.id;
            var old = localStorage.getItem(legacy);
            if (old !== null) {
                if (localStorage.getItem(key) === null) { localStorage.setItem(key, old); }
                localStorage.removeItem(legacy);
            }
        } catch (e) { /* storage unavailable: nothing to carry over */ }

        return key;
    }
    var FILTER_KEY = userKey('f');
    var SORT_KEY = userKey('s');
    var PAGE_KEY = userKey('ps');
    var SAVED_KEY = userKey('sf');
    var SAVED_MAX = 20;

    // The tab last used, so that opening a project from the list goes back to
    // it instead of always to Home: most people work on the issue list, and
    // made the extra click every time. Per person and per project, with the
    // last tab used in any project as the default for one not opened yet.
    // Settings is not kept (it is a visit, not a place to work), and an issue
    // counts as the list it was opened from.
    var LAST_TAB_KEY = userKey('tab');
    var LAST_TAB_ANY_KEY = 'pmportal.tab.u' + String(D.me || 0);
    var LAST_TABS = { home: 1, board: 1, issues: 1, gantt: 1, burndown: 1, wiki: 1, files: 1 };
    function lastTabSave(scr) {
        var tab = scr === 'issue' ? 'issues' : scr;
        if (!LAST_TABS[tab]) { return; }
        try {
            localStorage.setItem(LAST_TAB_KEY, tab);
            localStorage.setItem(LAST_TAB_ANY_KEY, tab);
        } catch (e) { /* storage unavailable: Home as before */ }
    }
    function lastTab() {
        var tab = null;
        try { tab = localStorage.getItem(LAST_TAB_KEY) || localStorage.getItem(LAST_TAB_ANY_KEY); } catch (e) { tab = null; }

        return tab && LAST_TABS[tab] ? tab : null;
    }

    // Tree view of the issue list (ISS00253). Off unless the user turns it
    // on, so the flat list is what everyone keeps seeing by default. Both the
    // switch and the folded parents are per person and per project.
    var TREE_KEY = userKey('tr');
    var TREE_FOLD_KEY = userKey('tc');
    var TREE_ON = false;
    var TREE_FOLD = {};
    // the tree switch also follows the person to the next project unless this
    // project has its own setting (snoro 2026-11-18)
    var TREE_LAST_KEY = 'pmportal.trlast.u' + String(D.me || 0);
    try {
        var treeOwn = localStorage.getItem(TREE_KEY);
        TREE_ON = (treeOwn !== null ? treeOwn : localStorage.getItem(TREE_LAST_KEY)) === '1';
        var savedFold = JSON.parse(localStorage.getItem(TREE_FOLD_KEY) || '[]');
        if (Array.isArray(savedFold)) {
            savedFold.forEach(function (id) {
                if (typeof id === 'string' || typeof id === 'number') { TREE_FOLD['i' + id] = true; }
            });
        }
    } catch (e) { /* ignore */ }
    function treeFolded(id) { return Object.prototype.hasOwnProperty.call(TREE_FOLD, 'i' + id); }
    function treeSave() {
        try {
            if (TREE_ON) { localStorage.setItem(TREE_KEY, '1'); } else { localStorage.removeItem(TREE_KEY); }
            // Only ids that still exist, so the list cannot grow for ever.
            var ids = Object.keys(TREE_FOLD).map(function (k) { return k.slice(1); })
                .filter(function (id) { return !!issueById(id); });
            if (ids.length) { localStorage.setItem(TREE_FOLD_KEY, JSON.stringify(ids)); }
            else { localStorage.removeItem(TREE_FOLD_KEY); }
        } catch (e) { /* ignore */ }
    }
    // The given (already filtered and sorted) list, re-ordered so children
    // sit under their parent. Returns [{i, depth, n}] where n is the number
    // of direct children placed under the row. A child whose parent is not in
    // the list (filtered out, or the id does not exist) is a top-level row;
    // rows caught in a parent cycle are put at top level too, so nothing is
    // ever dropped and nothing can loop. Siblings keep the order of the list
    // they came in, which is the current sort. Unless `full`, the rows under
    // a folded parent are left out.
    function treeRows(list, full) {
        var has = Object.prototype.hasOwnProperty;
        var ids = {}, kids = {}, roots = [];
        list.forEach(function (i) { ids['i' + i.id] = true; });
        list.forEach(function (i) {
            var p = (i.parent === null || i.parent === undefined) ? '' : String(i.parent);
            if (p !== '' && p !== String(i.id) && has.call(ids, 'i' + p)) {
                (kids['i' + p] = kids['i' + p] || []).push(i);
            } else {
                roots.push(i);
            }
        });
        var out = [], seen = {};
        var walk = function (root) {
            var stack = [{ i: root, depth: 0, up: null }];
            while (stack.length) {
                var cur = stack.pop();
                var k = 'i' + cur.i.id;
                if (has.call(seen, k)) { continue; }
                seen[k] = true;
                var entry = { i: cur.i, depth: cur.depth, n: 0 };
                if (cur.up) { cur.up.n++; }
                out.push(entry);
                var ch = has.call(kids, k) ? kids[k] : [];
                for (var c = ch.length - 1; c >= 0; c--) {
                    stack.push({ i: ch[c], depth: cur.depth + 1, up: entry });
                }
            }
        };
        roots.forEach(walk);
        // Whatever is left has a parent chain that never reaches a root: a cycle.
        list.forEach(function (i) { if (!has.call(seen, 'i' + i.id)) { walk(i); } });
        if (full) { return out; }

        var vis = [], hide = -1;
        out.forEach(function (e) {
            if (hide >= 0 && e.depth > hide) { return; }
            hide = (e.n > 0 && treeFolded(e.i.id)) ? e.depth : -1;
            vis.push(e);
        });

        return vis;
    }
    function syncTreeControls(parents) {
        var btn = document.getElementById('pp-tree-btn');
        if (btn) {
            btn.classList.toggle('active', TREE_ON);
            btn.setAttribute('aria-pressed', TREE_ON ? 'true' : 'false');
        }
        ['pp-tree-open', 'pp-tree-close'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) { el.hidden = !TREE_ON || !parents; }
        });
    }

    // Whether the tick column and the bulk bar exist at all. Table level, so
    // a reader is not offered a control that could only ever be refused; the
    // server still checks every row on its own when the edit is applied.
    var BULK_ON = !!(D.can && D.can.issue_edit);

    // Whether this user may file an issue here at all - the issue table's
    // create rule and the project's own, the same pair the save checks.
    // Every way into the create form (課題の追加, the a key, #new, 複製,
    // 子課題の追加) is offered only when it is true.
    var CAN_ADD_ISSUE = !!(D.can && D.can.issue_create);

    var QCHIPS = [
        { k: 'mine', l: 'f_mine', i: 'fa-user-o', t: function (i) {
            return !!D.me && i.as.map(String).indexOf(String(D.me)) >= 0;
        } },
        { k: 'overdue', l: 'q_overdue', i: 'fa-exclamation-circle', warn: true, t: function (i) {
            return !isDone(i.st) && dueOver(i);
        } },
        { k: 'soon', l: 'q_soon', i: 'fa-clock-o', t: function (i) {
            if (isDone(i.st) || !i.dd || dueOver(i)) { return false; }
            var d = daysTo(i.dd);
            return d >= 0 && d <= 1;
        } },
        { k: 'created', l: 'q_created', i: 'fa-pencil', t: function (i) {
            return !!D.me && String(i.by) === String(D.me);
        } },
        { k: 'watch', l: 'q_watch', i: 'fa-eye', t: function (i) { return !!i.my_watch; } },
        { k: 'unread', l: 'q_unread', i: 'fa-envelope', t: function (i) { return !!i.unread; } },
        // ISS00341: issues with a comment somebody asked me to confirm and
        // that I have not confirmed yet.
        { k: 'review', l: 'q_review', i: 'fa-check-square-o', warn: true, t: function (i) { return !!i.my_rv; } }
    ];
    function qchipTest(key) {
        for (var n = 0; n < QCHIPS.length; n++) {
            if (QCHIPS[n].k === key) { return QCHIPS[n].t; }
        }

        return null;
    }

    // Paging. The whole project is in memory either way, so this is about the
    // dom and about the reader: three thousand rows is three thousand nodes
    // the browser lays out on every keystroke, and a list nobody can get to
    // the bottom of.
    var PAGE_SIZES = [20, 50, 100, 0];
    var PAGE = { size: 50, no: 1 };
    try {
        var savedSize = parseInt(localStorage.getItem(PAGE_KEY), 10);
        if (PAGE_SIZES.indexOf(savedSize) >= 0) { PAGE.size = savedSize; }
    } catch (e) { /* ignore */ }

    // Ticked rows, by id. Cleared whenever the list underneath changes: a
    // selection that survived a filter change would apply an edit to rows the
    // user can no longer see, which is the one thing a bulk edit must never
    // do.
    var SEL = {};
    function selIds() { return Object.keys(SEL); }
    function clearSelection() { SEL = {}; }

    // Every filter, sort or page-size change goes through here, so the page
    // number and the selection can never be left describing the old list.
    function afterFilterChange() {
        FILTER_CARRIED = false;
        PAGE.no = 1;
        clearSelection();
        renderIssues();
        filterToUrl();
        rememberLastFilter();
    }
    // The last filter and sort used anywhere, per person, across projects
    // (snoro 2026-11-18: moving to another project reset the filters). A
    // project with its own saved default ("次回もこの条件で開く") keeps that.
    var FILTER_LAST_KEY = 'pmportal.flast.u' + String(D.me || 0);
    function rememberLastFilter() {
        try { localStorage.setItem(FILTER_LAST_KEY, JSON.stringify({ f: FILTER, s: SORT })); } catch (e) { /* ignore */ }
    }
    // Only what means the same in every project is carried over: status ids,
    // types, milestones and categories are per project, so a value from
    // another project is kept only if this project has it too.
    // Set while the filter in use is the one brought over from another
    // project. If it shows nothing here, it is dropped (see renderIssues):
    // a keyword, an assignee or 未読 from the last project can easily match
    // nothing in this one, and an empty list read as "the issues are gone".
    var FILTER_CARRIED = false;
    function dropCarriedFilterIfEmpty() {
        if (!FILTER_CARRIED || !(D.issues || []).length) { return; }
        FILTER_CARRIED = false;
        if (filteredIssues().length > 0) { return; }
        FILTER_FIELDS.forEach(function (k) { FILTER[k] = ''; });
        syncFilterControls();
        rememberLastFilter();
        toast(L.f_carried_cleared);
    }
    function carriedFilterValue(k, v) {
        if (k === 'st') { return (v === '!done' || own(ST, v)) ? v : ''; }
        if (k === 'ty') { return own(D.types, v) ? v : ''; }
        if (k === 'ms') { return own(D.milestones, v) ? v : ''; }
        if (k === 'cat') { return own(D.categories, v) ? v : ''; }
        if (k === 'pr') { return own(D.priorities, v) ? v : ''; }
        if (k === 'rd') { return (v === 'unread' || v === 'read') ? v : ''; }
        if (k === 'df') { return difficulties().indexOf(v) >= 0 ? v : ''; }
        if (k === 'at') { return atFilterKeep(v); }
        return v;
    }
    try {
        var saved = JSON.parse(localStorage.getItem(FILTER_KEY) || 'null');
        // Field by field, and only the fields a filter has: what comes back
        // out of storage is whatever was put there.
        if (saved && typeof saved === 'object') {
            FILTER_FIELDS.forEach(function (k) {
                var v = own(saved, k);
                if (typeof v === 'string' || typeof v === 'number') { FILTER[k] = String(v); }
            });
        } else {
            var last = JSON.parse(localStorage.getItem(FILTER_LAST_KEY) || 'null');
            if (last && typeof last === 'object' && last.f && typeof last.f === 'object') {
                FILTER_FIELDS.forEach(function (k) {
                    var v = own(last.f, k);
                    if (typeof v === 'string' || typeof v === 'number') { FILTER[k] = carriedFilterValue(k, String(v)); }
                });
                FILTER_CARRIED = true;
            }
        }
    } catch (e) { /* ignore */ }

    // The order was fixed in code - due date, ascending - and the caret drawn
    // beside that header was a picture of a sort the table could not do. Every
    // column is live now. The default is the old order, so a link taken before
    // this still opens the list the way its sender saw it.
    var SORT_DEF = { by: 'dd', dir: 'asc' };
    var SORT = { by: SORT_DEF.by, dir: SORT_DEF.dir };

    // A japanese list sorted with < compares code points, which puts 送 before
    // 開 and reads as no order at all. Intl does the collation the reader
    // expects; the fallback is only there for a browser that has none.
    var COLL = (window.Intl && Intl.Collator) ? new Intl.Collator('ja') : null;
    function cmpText(a, b) {
        if (COLL) { return COLL.compare(a, b); }
        return a < b ? -1 : (a > b ? 1 : 0);
    }

    // Where an option sits in the project's own list. JSON hands a map with
    // numeric keys back renumbered into ascending order, so a row-backed
    // status or priority cannot be read off the key order - the server sends
    // the ordinal with each entry instead.
    function metaRank(map, key) {
        var o = own(map, key);
        return (o && typeof o.o === 'number') ? o.o : Infinity;
    }
    function metaKeys(map) {
        return Object.keys(map || {}).sort(function (a, b) {
            var x = metaRank(map, a), y = metaRank(map, b);
            return x === y ? cmpText(String(a), String(b)) : (x < y ? -1 : 1);
        });
    }
    var ST_RANK = {};
    (D.statuses || []).forEach(function (s, n) { ST_RANK[s.k] = n; });

    // A milestone has no ordinal of its own; it is due on a date, and the ones
    // with no date belong after the ones that have one. \uffff sorts above any
    // digit, so one string says both without a second comparison.
    function msSortKey(id) {
        var m = own(D.milestones, id);
        if (!m) { return ''; }
        return (m.due || '\uffff') + '\u0000' + String(m.l || '');
    }

    // What a cell is worth when the table is sorted on its column, and whether
    // that value is text a reader would want collated.
    var SORT_COLS = {
        ty: { t: false, v: function (i) { return metaRank(D.types, i.ty); } },
        // The key is <project>-<row id> everywhere in this project, so the id
        // is the same order without the string parsing.
        key: { t: false, v: function (i) { return Number(i.id); } },
        // ISS00248: by alias, natural order (P1.2 before P1.10); none last
        al: { t: true, nat: true, v: function (i) { return i.al ? String(i.al) : ''; } },
        subject: { t: true, v: function (i) { return String(i.subject || ''); } },
        as: { t: true, v: function (i) { return (i.as && i.as.length) ? String(user(i.as[0]).n) : ''; } },
        st: { t: false, v: function (i) { var r = own(ST_RANK, i.st); return r == null ? Infinity : r; } },
        pr: { t: false, v: function (i) { return metaRank(D.priorities, i.pr); } },
        ms: { t: true, v: function (i) { return msSortKey(i.ms); } },
        cat: { t: true, v: function (i) { return (i.cat && i.cat.length) ? String(own(D.categories, i.cat[0]) || '') : ''; } },
        sd: { t: false, v: function (i) { return i.sd || ''; } },
        dd: { t: false, v: function (i) { return i.dd ? i.dd + ' ' + (i.dt || '99:99') : ''; } },
        pg: { t: false, v: function (i) { return (i.pg === null || i.pg === undefined) ? null : Number(i.pg); } },
        up: { t: false, v: function (i) { return String(i.up || ''); } },
        // The optional columns (ISS02931)
        sa: { t: true, v: function (i) { return (i.sa && i.sa.length) ? String(user(i.sa[0]).n) : ''; } },
        mg: { t: true, v: function (i) { return (i.mg && i.mg.length) ? String(user(i.mg[0]).n) : ''; } },
        rv: { t: true, v: function (i) { return (i.rv && i.rv.length) ? String(user(i.rv[0]).n) : ''; } },
        df: { t: false, v: function (i) { var r = difficulties().indexOf(String(i.df || '')); return r < 0 ? null : r; } }
    };
    // and one for each custom attribute of the project (at<id>, ISS02939)
    atSortRegister();
    /* ---- optional columns (ISS02931) ---- */
    // The other people of an issue and its difficulty, as columns of the list
    // the reader turns on (off at first: the list is wide already), kept per
    // browser. Offered only where the issue table has the column.
    var OCOLS = [
        { k: 'sa', role: 'sub_assignees', l: 'lbl_sub_assignees' },
        { k: 'mg', role: 'managers', l: 'lbl_managers' },
        { k: 'rv', role: 'reviewers', l: 'lbl_reviewers' },
        { k: 'df', role: 'difficulty', l: 'lbl_difficulty' }
    ];
    var OCOL_KEY = 'pmportal.listcols';
    var OCOL_ON = {};
    try {
        (JSON.parse(localStorage.getItem(OCOL_KEY) || '[]') || []).forEach(function (k) {
            if (typeof k === 'string') { OCOL_ON[k] = true; }
        });
    } catch (e) { /* ignore */ }
    function difficulties() { return (D.difficulties || ['easy', 'normal', 'hard']).map(String); }
    // the custom attributes' columns after them (ISS02939)
    function ocolsOffered() { return OCOLS.filter(function (c) { return xOn(c.role); }).concat(atOcols()); }
    function ocolsShown() { return ocolsOffered().filter(function (c) { return own(OCOL_ON, c.k); }); }
    function ocolSave() {
        try { localStorage.setItem(OCOL_KEY, JSON.stringify(Object.keys(OCOL_ON))); } catch (e) { /* ignore */ }
    }
    function ocolCell(i, c) {
        if (c.at) { return atCell(i, c.at); }
        if (c.k === 'df') { return '<td style="white-space:nowrap;">' + (i.df ? esc(L['df_' + i.df] || i.df) : '') + '</td>'; }
        return '<td>' + avlist(i[c.k]) + '</td>';
    }
    // The headers, the columns menu and the two filters, drawn from what the
    // table has.
    function renderOcols() {
        var offered = ocolsOffered();
        var head = document.getElementById('pp-issue-head');
        // (the attributes may have changed with a refresh of the data)
        atSortRegister();
        if (head) {
            atEnsureHead(head);
            head.querySelectorAll('th[data-ocol]').forEach(function (th) {
                var k = th.getAttribute('data-ocol');
                th.hidden = !offered.some(function (c) { return c.k === k && own(OCOL_ON, k); });
            });
        }
        var wrap = document.getElementById('pp-oc-wrap');
        if (wrap) { wrap.hidden = !offered.length; }
        var menu = document.getElementById('pp-oc-menu');
        if (menu && !menu._ppBuilt && offered.length) {
            menu._ppBuilt = true;
            menu.innerHTML = offered.map(function (c) {
                return '<label><input type="checkbox" data-ocol="' + esc(c.k) + '"' + (own(OCOL_ON, c.k) ? ' checked' : '') + '> ' + esc(c.at ? c.at.l : L[c.l]) + '</label>';
            }).join('');
        }
        var rl = document.getElementById('pp-f-rl');
        if (rl) {
            var roles = offered.filter(function (c) { return c.k !== 'df' && !c.at; });
            rl.hidden = !roles.length;
            if (roles.length && rl.options.length <= 1) {
                var people = Object.keys(D.users || {}).sort(function (a, b) { return cmpText(user(a).n, user(b).n); });
                roles.forEach(function (c) {
                    var g = document.createElement('optgroup');
                    g.label = L[c.l];
                    people.forEach(function (u) { g.appendChild(new Option(L[c.l] + ': ' + user(u).n, c.k + ':' + u)); });
                    rl.appendChild(g);
                });
            }
        }
        var df = document.getElementById('pp-f-df');
        if (df) {
            df.hidden = !xOn('difficulty');
            if (!df.hidden && df.options.length <= 1) {
                difficulties().forEach(function (d) { df.add(new Option(L.lbl_difficulty + ': ' + (L['df_' + d] || d), d)); });
            }
        }
    }
    function rlMatch(i, v) {
        var at = String(v).indexOf(':');
        if (at < 0) { return true; }
        var k = String(v).slice(0, at), u = String(v).slice(at + 1);
        if (['sa', 'mg', 'rv'].indexOf(k) < 0) { return true; }
        return (i[k] || []).map(String).indexOf(u) >= 0;
    }
    function sortBlank(v) {
        if (v === '' || v === null || v === undefined) { return true; }
        return typeof v === 'number' && !isFinite(v);
    }
    function sortCmp() {
        var col = own(SORT_COLS, SORT.by) || SORT_COLS[SORT_DEF.by];
        var sign = SORT.dir === 'desc' ? -1 : 1;
        return function (a, b) {
            var x = col.v(a), y = col.v(b);
            // An empty cell has no place in either order, so it goes last
            // whichever way the arrow points: a descending sort that opened
            // with forty blank rows would bury the answer being looked for.
            var ex = sortBlank(x), ey = sortBlank(y);
            if (ex !== ey) { return ex ? 1 : -1; }
            if (!ex) {
                if (col.nat) {
                    // natural: P1.2 before P1.10 (ISS00248)
                    var cn = String(x).localeCompare(String(y), undefined, { numeric: true, sensitivity: 'base' });
                    if (cn !== 0) { return cn * sign; }
                } else if (col.t) {
                    var c = cmpText(String(x), String(y));
                    if (c !== 0) { return c * sign; }
                } else if (typeof x === 'number' || typeof y === 'number') {
                    if (Number(x) !== Number(y)) { return (Number(x) < Number(y) ? -1 : 1) * sign; }
                } else if (x !== y) {
                    return (x < y ? -1 : 1) * sign;
                }
            }
            // Equal in the sorted column: the id decides, so the order is the
            // same on every render and rows do not swap under the cursor.
            return a.id - b.id;
        };
    }
    function sortActive() {
        return SORT.by !== SORT_DEF.by || SORT.dir !== SORT_DEF.dir;
    }
    try {
        var savedSort = JSON.parse(localStorage.getItem(SORT_KEY) || 'null');
        // no default of this project's own: the last sort used anywhere
        if (!savedSort) {
            var lastSort = JSON.parse(localStorage.getItem(FILTER_LAST_KEY) || 'null');
            savedSort = lastSort && lastSort.s && typeof lastSort.s === 'object' ? lastSort.s : null;
        }
        // Checked against the live table rather than trusted: a column that
        // existed when this was saved may be gone from the project now.
        if (savedSort && own(SORT_COLS, savedSort.by)) {
            SORT.by = savedSort.by;
            SORT.dir = savedSort.dir === 'desc' ? 'desc' : 'asc';
        }
    } catch (e) { /* ignore */ }

    // The filter used to live only in localStorage. That made a filtered list
    // impossible to send to anyone - the link they got showed them something
    // else entirely - impossible to bookmark twice for the same project, and
    // invisible: a filter set last week came back today with an almost empty
    // list and nothing in the URL to explain it. It now rides in the hash as
    //   #issues?st=<status>&as=<user>&ty=<type>&pr=<priority>&ms=<milestone>
    //          &cat=<cat>&kw=<text>&so=<column>&sd=<asc|desc>
    // with localStorage kept on as the personal default for a bare #issues.
    // so/sd are written only while the sort differs from the default, so an
    // ordinary link is as short as it was before the table could sort.
    function filterToQuery() {
        var parts = FILTER_FIELDS.filter(function (k) {
            return FILTER[k] !== '' && FILTER[k] != null;
        }).map(function (k) {
            return k + '=' + encodeURIComponent(FILTER[k]);
        });
        // The sort rides along, and is left out while it matches the default
        // so an ordinary link stays as short as it was before it could sort.
        if (sortActive()) {
            parts.push('so=' + encodeURIComponent(SORT.by));
            parts.push('sd=' + (SORT.dir === 'desc' ? 'desc' : 'asc'));
        }
        // Page one is the same list with or without the parameter, so a link
        // to the top of a list stays as plain as it ever was.
        if (PAGE.no > 1) { parts.push('p=' + PAGE.no); }

        return parts.join('&');
    }
    // A field the query does not mention is off, not unmentioned - otherwise a
    // shared link would silently inherit whatever the recipient had set.
    function filterFromQuery(q) {
        FILTER_FIELDS.forEach(function (k) {
            FILTER[k] = Object.prototype.hasOwnProperty.call(q, k) ? String(q[k]) : '';
        });
        var by = String(q.so || '');
        // Looked up as the table's own column only: a name off the address
        // bar such as "toString" is truthy on any object and has no value
        // reader, which used to take the list and the detail screen down.
        if (own(SORT_COLS, by)) {
            SORT.by = by;
            SORT.dir = String(q.sd || '') === 'desc' ? 'desc' : 'asc';
        } else {
            SORT.by = SORT_DEF.by;
            SORT.dir = SORT_DEF.dir;
        }
        var page = parseInt(q.p, 10);
        PAGE.no = (page > 1) ? page : 1;
        // A link describes a different list from the one on screen, so
        // whatever was ticked here belongs to neither.
        clearSelection();
    }
    // replaceState, not pushState: the list filters as you type, and a history
    // entry per keystroke would bury the back button.
    function filterToUrl() {
        if (!history.replaceState) { return; }
        if (parseHash().path !== 'issues') { return; }
        var q = filterToQuery();
        history.replaceState(null, '', '#issues' + (q ? '?' + q : ''));
    }

    // #issue-4821 said nothing about what it pointed at, and 4821 is the global
    // row id, so the link also died the moment a project was exported and
    // re-imported somewhere else. The key the rest of the UI already shows -
    // ITSM-4821 - says both project and ticket. The numeric form still resolves
    // so bookmarks taken before this keep working.
    //
    // A project with no key of its own makes issueKey() fall back to '#-<id>',
    // and a '#' inside a fragment truncates the URL - stay numeric there.
    //
    // ISS00440: the address uses the issue key (#issue-ISS00239), which does
    // not change when the project is renamed. PROJECTKEY-id and the bare id
    // still resolve (resolveIssueRef), and route() rewrites them to this.
    // ISS00248: the list shows the number of the key ("156" of "EXMENT_AI-156")
    function keyNum(i) {
        var m = String((i && i.key) || '').match(/-(\d+)$/);
        return m ? m[1] : String((i && i.key) || '');
    }
    // the alias column, when an issue of the project has one
    function aliasOn() {
        return (D.issues || []).some(function (i) { return !!i.al; });
    }
    function keyRef(i) {
        var ik = i && i.ik ? String(i.ik) : '';
        if (/^[A-Za-z0-9_-]+$/.test(ik)) { return ik; }
        var key = i && i.key ? String(i.key) : '';
        return (key && key.indexOf('#') < 0) ? key : String(i ? i.id : '');
    }
    function issueHash(id) {
        var i = issueById(id);
        return 'issue-' + (i ? keyRef(i) : String(id));
    }
    // Returns the row id, or null when the reference names nothing this project
    // is carrying - a stale bookmark, or a link from a different project.
    function resolveIssueRef(ref) {
        if (/^\d+$/.test(ref)) { return String(ref); }
        var want = String(ref).toUpperCase();
        var found = null;
        (D.issues || []).forEach(function (i) {
            if (String(i.ik || '').toUpperCase() === want || String(i.key).toUpperCase() === want || (i.al && String(i.al).toUpperCase() === want)) { found = i; }
        });
        // OLDKEY-239 after the project was renamed: the number is the row id,
        // and the row is one this project is carrying.
        var old = found ? null : want.match(/^(.+)-(\d+)$/);
        if (old) { found = issueById(old[2]); }
        return found ? String(found.id) : null;
    }

    // The issue with this issue key (ISS00239), either case, or null.
    var IK_MAP = null, IK_LEN = -1;
    function issueByIk(word) {
        var list = D.issues || [];
        if (!IK_MAP || IK_LEN !== list.length) {
            IK_MAP = {};
            IK_LEN = list.length;
            list.forEach(function (i) { if (i.ik) { IK_MAP[String(i.ik).toUpperCase()] = i; } });
        }
        return own(IK_MAP, String(word).toUpperCase()) || null;
    }
    // The number at the end of a key - "292" out of "PORTAL-292". Written on
    // its own after a '#' by anyone who is not looking at the key itself.
    function issueByKeyNum(num) {
        var want = '-' + String(num);
        var found = null;
        (D.issues || []).forEach(function (i) {
            if (String(i.key || '').slice(-want.length) === want) { found = i; }
        });

        return found;
    }

    // ISS00255: "完了以外" - every issue except those in the project's final
    // status: the last status (in the project's order) that counts as done.
    // With 未対応・処理中・レビュー中・処理済み・完了 that hides only 完了, so
    // 処理済み (also a done status) stays visible, as asked. Works whatever the
    // status ids are, since each project has its own.
    var ST_NOT_DONE = '!done';
    function finalDoneStatus() {
        var last = '';
        (D.statuses || []).forEach(function (s) { if (s.done) { last = s.k; } });
        return last;
    }
    function stMatch(st, want) {
        if (!want) { return true; }
        if (want === ST_NOT_DONE) {
            var fin = finalDoneStatus();
            return fin ? st !== fin : !isDone(st);
        }
        return st === want;
    }

    // S18: 自分が担当 counts this project only; the dashboard has them all.
    function mineAllLink() {
        if (FILTER.q !== 'mine' || !(D.urls && D.urls.projects)) { return ''; }
        return '<a class="pp-mine-all" href="' + esc(D.urls.projects) + '">' + esc(L.mine_all) + ' <i class="fa fa-angle-right"></i></a>';
    }
    // S11, M7: a pencil in each cell that can be changed in place; one press
    // opens the same editor a double-click does (edCell), which stays.
    function edPen() {
        return BULK_ON ? '<button type="button" class="pp-ed-pen" tabindex="-1" title="' + esc(L.ed_pen) + '" aria-label="' + esc(L.ed_pen) + '"><i class="fa fa-pencil"></i></button>' : '';
    }
    (function () {
        var rows = document.getElementById('pp-issue-rows');
        if (!rows) { return; }
        // Capture: before the row's own click opens the issue.
        rows.addEventListener('click', function (ev) {
            if (ev.target.closest && ev.target.closest('.pp-empty-clear')) {
                ev.preventDefault();
                ev.stopPropagation();
                var clear = document.getElementById('pp-f-clear');
                if (clear) { clear.click(); }
                return;
            }
            var pen = ev.target.closest && ev.target.closest('.pp-ed-pen');
            if (!pen) { return; }
            ev.preventDefault();
            ev.stopPropagation();
            var td = pen.closest('td[data-ed]');
            if (!td) { return; }
            edHintSeen();
            var dbl;
            try { dbl = new MouseEvent('dblclick', { bubbles: true, cancelable: true }); } catch (e) { dbl = null; }
            if (dbl) { td.dispatchEvent(dbl); }
        }, true);
        rows.addEventListener('dblclick', function () { edHintSeen(); });
        // A first visit is told once, under the list, how a cell is changed.
        var hint = document.getElementById('pp-ed-hint');
        var seen = true;
        try { seen = localStorage.getItem('pmportal.edhint') === '1'; } catch (e) { seen = true; }
        if (hint && BULK_ON && !seen) {
            hint.hidden = false;
            var x = hint.querySelector('.x');
            if (x) { x.addEventListener('click', edHintSeen); }
        }
    }());
    function edHintSeen() {
        var hint = document.getElementById('pp-ed-hint');
        if (hint) { hint.hidden = true; }
        try { localStorage.setItem('pmportal.edhint', '1'); } catch (e) { /* ignore */ }
    }
    function renderIssueFilters() {
        var tabs = '<button class="bl-sttab' + (FILTER.st === '' ? ' active' : '') + '" data-st="">' +
            esc(L.f_all) + '<span class="ct">' + (D.issues.length + arCount()) + '</span></button>';
        tabs += '<button class="bl-sttab' + (FILTER.st === ST_NOT_DONE ? ' active' : '') + '" data-st="' + ST_NOT_DONE + '">' +
            esc(L.f_not_done || '完了以外') + '<span class="ct">' +
            (D.issues.filter(function (i) { return stMatch(i.st, ST_NOT_DONE); }).length +
                (D.statuses || []).reduce(function (n, s) { return n + (stMatch(s.k, ST_NOT_DONE) ? arIn('by_status', s.k) : 0); }, 0)) + '</span></button>';
        (D.statuses || []).forEach(function (s) {
            var n = D.issues.filter(function (i) { return i.st === s.k; }).length + arIn('by_status', s.k);
            tabs += '<button class="bl-sttab' + (FILTER.st === s.k ? ' active' : '') + '" data-st="' + esc(s.k) + '">' +
                '<span class="dot" style="background:' + ppColor(s.c) + '"></span>' + esc(s.l) + '<span class="ct">' + n + '</span></button>';
        });
        document.getElementById('pp-sttabs').innerHTML = tabs;

        var chips = '';
        QCHIPS.forEach(function (c) {
            // Counted over the whole project, like the status tabs above -
            // a count that moved with the other filters would make the row
            // read as a running total rather than as a set of shortcuts.
            var n = (D.issues || []).filter(c.t).length;
            var on = FILTER.q === c.k;
            chips += '<button type="button" class="bl-qchip' + (c.warn ? ' warn' : '') + (on ? ' active' : '') +
                '" data-q="' + c.k + '" aria-pressed="' + (on ? 'true' : 'false') + '">' +
                '<i class="fa ' + c.i + '"></i>' + esc(L[c.l]) + '<span class="ct">' + n + '</span></button>';
        });
        document.getElementById('pp-qchips').innerHTML = chips + mineAllLink();

        var selAs = document.getElementById('pp-f-as');
        if (selAs.options.length <= 1) {
            // Every dropdown here used to be built straight off Object.keys,
            // which on a row-backed install is row-id order - arbitrary to
            // whoever reads it. Each list is now put in the order it is meant
            // to be read in: people and categories by name, types and
            // priorities by the project's own option order, milestones by when
            // they are due.
            Object.keys(D.users || {}).sort(function (a, b) {
                return cmpText(user(a).n, user(b).n);
            }).forEach(function (u) { selAs.add(new Option(user(u).n, u)); });
            // An admin reading a project they are not a member of is in no
            // user list, and 自分の課題 would then set a value this dropdown
            // has no option for - which silently resets it to すべて and leaves
            // the two controls describing different lists.
            var meStr = String(D.me || '');
            if (meStr && !(D.users || {})[meStr]) { selAs.add(new Option(L.f_mine, meStr)); }

            metaKeys(D.types).forEach(function (t) { document.getElementById('pp-f-ty').add(new Option(D.types[t].l, t)); });
            metaKeys(D.priorities).forEach(function (p) { document.getElementById('pp-f-pr').add(new Option(D.priorities[p].l, p)); });
            Object.keys(D.milestones || {}).sort(function (a, b) {
                return cmpText(msSortKey(a), msSortKey(b));
            }).forEach(function (m) { document.getElementById('pp-f-ms').add(new Option(D.milestones[m].l, m)); });
            Object.keys(D.categories || {}).sort(function (a, b) {
                return cmpText(String(D.categories[a]), String(D.categories[b]));
            }).forEach(function (c) { document.getElementById('pp-f-cat').add(new Option(D.categories[c], c)); });
            // the last option of each opens the picker of several (ISS00255)
            MULTI_SELECTS.forEach(function (id) { document.getElementById(id).add(new Option(L.f_multi, '__multi')); });
        }
        syncFilterControls();
    }

    // Which column carries the arrow, and what a click on each one will do.
    function renderSortHead() {
        var head = document.getElementById('pp-issue-head');
        if (!head) { return; }
        head.querySelectorAll('th[data-sort]').forEach(function (th) {
            // The label is stashed on the first pass: the arrow is drawn
            // inside the cell, so reading the text back later would pick it up
            // too and the header would grow an arrow per render.
            var name = th.getAttribute('data-name');
            if (name === null) {
                name = th.textContent.trim();
                th.setAttribute('data-name', name);
                th.setAttribute('role', 'button');
                th.setAttribute('tabindex', '0');
            }
            var on = th.getAttribute('data-sort') === SORT.by;
            var next = (on && SORT.dir === 'asc') ? 'desc' : 'asc';
            th.className = on ? 'sorted' : '';
            th.setAttribute('aria-sort', on ? (SORT.dir === 'asc' ? 'ascending' : 'descending') : 'none');
            th.title = String(next === 'desc' ? L.sort_desc : L.sort_asc).replace(':name', name);
            th.innerHTML = esc(name) +
                '<i class="fa ar ' + (on && SORT.dir === 'desc' ? 'fa-caret-down' : 'fa-caret-up') + '"></i>';
        });
    }
    // Push FILTER back into the controls. This has to run on every render now,
    // not only the first: a filter can arrive from the URL long after the
    // controls exist. Each value is written only when it really differs -
    // assigning an identical string to a focused input moves the caret in some
    // browsers, and this runs while the user is typing in the keyword box.
    function setVal(id, v) {
        var el = document.getElementById(id);
        if (el && el.value !== v) { el.value = v; }
    }
    function syncFilterControls() {
        setVal('pp-f-kw', FILTER.kw);
        setFilterVal('pp-f-as', FILTER.as);
        setFilterVal('pp-f-ty', FILTER.ty);
        setFilterVal('pp-f-pr', FILTER.pr);
        setFilterVal('pp-f-ms', FILTER.ms);
        setFilterVal('pp-f-cat', FILTER.cat);
        setVal('pp-f-rd', FILTER.rd);
        setVal('pp-f-rl', FILTER.rl);
        setVal('pp-f-df', FILTER.df);
        atSyncFilterControls();
        setVal('pp-jump', FILTER.kw);
        setVal('pp-pg-size', String(PAGE.size));
        // Offered only when there is something to clear, so the row does not
        // carry a permanently dead link.
        var clear = document.getElementById('pp-f-clear');
        if (clear) { clear.hidden = !anyFilter(); }
    }
    function anyFilter() {
        if (sortActive()) { return true; }
        for (var n = 0; n < FILTER_FIELDS.length; n++) {
            var v = FILTER[FILTER_FIELDS[n]];
            if (v !== '' && v != null) { return true; }
        }

        return false;
    }

    // The list exactly as the grid shows it - same sort, same filters. The
    // detail screen walks this, so "the next issue" means the next row the
    // user can see, not the next id in the table.
    // A filter holds one value or several joined by commas (ISS00255): does a row's value, or
    // one of its values, match one of them?
    function fHas(filter, values) {
        var want = String(filter).split(',');
        return (values || []).some(function (v) { return want.indexOf(String(v)) >= 0; });
    }
    function filteredIssues() {
        // Filtered first, sorted after. This runs on every keystroke in the
        // keyword box, and on a big project sorting three thousand rows to
        // then throw all but thirty of them away was the whole cost of typing.
        var kw = FILTER.kw ? FILTER.kw.toLowerCase() : '';
        var quick = FILTER.q ? qchipTest(FILTER.q) : null;
        var atDefList = atDefs();
        var atf = FILTER.at && atDefList.length ? atFilterParse(FILTER.at) : null;

        return (D.issues || []).filter(function (i) {
            if (i.ar && !AR.show) { return false; }
            if (quick && !quick(i)) { return false; }
            if (FILTER.st && !stMatch(i.st, FILTER.st)) { return false; }
            if (FILTER.as && !fHas(FILTER.as, i.as)) { return false; }
            if (FILTER.ty && !fHas(FILTER.ty, [i.ty])) { return false; }
            if (FILTER.pr && !fHas(FILTER.pr, [i.pr])) { return false; }
            if (FILTER.ms && !fHas(FILTER.ms, [i.ms])) { return false; }
            if (FILTER.cat && !fHas(FILTER.cat, i.cat)) { return false; }
            if (FILTER.rd === 'unread' && !i.unread) { return false; }
            if (FILTER.rd === 'read' && i.unread) { return false; }
            if (FILTER.rl && !rlMatch(i, FILTER.rl)) { return false; }
            if (FILTER.df && String(i.df || '') !== String(FILTER.df)) { return false; }
            if (atf && !atRowMatch(i, atDefList, atf)) { return false; }
            if (kw && (i.key + ' ' + i.subject + ' ' + (i.sub || '')).toLowerCase().indexOf(kw) < 0) { return false; }

            return true;
        }).sort(sortCmp());
    }
    function renderIssues() {
        dropCarriedFilterIfEmpty();
        var alHead = document.getElementById('pp-al-head');
        if (alHead) { alHead.hidden = !aliasOn(); }
        renderOcols();
        renderIssueFilters();
        renderAtFilters();
        renderSortHead();
        var ocols = ocolsShown();

        var matched = filteredIssues();
        // Tree mode pages over the rows that are actually listed (folded
        // children are not), so the dom stays as bounded as in the flat list.
        var parents = 0;
        var all = matched;
        if (TREE_ON) {
            all = treeRows(matched, false);
            all.forEach(function (e) { if (e.n > 0) { parents++; } });
        }
        syncTreeControls(parents);
        var total = all.length;
        var size = PAGE.size > 0 ? PAGE.size : total;
        var pages = size > 0 ? Math.max(1, Math.ceil(total / size)) : 1;
        // Clamped rather than trusted: the page can be arrived at from a link,
        // and the list it was written against may since have got shorter.
        if (PAGE.no > pages) { PAGE.no = pages; }
        if (PAGE.no < 1) { PAGE.no = 1; }
        var from = size > 0 ? (PAGE.no - 1) * size : 0;
        var page = size > 0 ? all.slice(from, from + size) : all;

        var head = document.getElementById('pp-chk-cell');
        if (head) { head.hidden = !BULK_ON; }
        // tree mode: the ▼/▶ toggles get a column of their own at the left
        // (snoro 2026-11-18: inside the subject they were hard to read)
        // tree mode only: a narrow column left of 件名 holds the indent and
        // the ▼/▶ or └ (snoro 2026-11-18), so the subject cell stays clean
        var tHead = document.getElementById('pp-tcol-head');
        if (tHead) { tHead.hidden = !TREE_ON; tHead.textContent = L.col_tree; }

        var onPage = {};
        if (TREE_ON) { page.forEach(function (e) { onPage['i' + e.i.id] = true; }); }

        var rows = '', shown = 0;
        var ru = rollupMap();
        page.forEach(function (item) {
            shown++;
            var i = TREE_ON ? item.i : item;
            var parent = i.parent && issueById(i.parent)
                ? '<span class="parent">' + esc(issueById(i.parent).key) + ' &gt;</span>' : '';
            var tgl = '', ind = '';
            if (TREE_ON) {
                // The "KEY >" prefix is only needed where the parent is not the
                // row above in the tree: filtered out, or on another page.
                if (item.depth > 0 && Object.prototype.hasOwnProperty.call(onPage, 'i' + i.parent)) { parent = ''; }
                var folded = treeFolded(i.id);
                tgl = item.n > 0
                    ? '<a href="javascript:void(0);" role="button" class="pp-tgl" data-tgl="' + esc(i.id) +
                        '" aria-expanded="' + (folded ? 'false' : 'true') + '" title="' +
                        esc(folded ? (L.tree_expand || '開く') : (L.tree_collapse || '閉じる')) + '">' +
                        (folded ? '▶' : '▼') + '<span class="n">' + item.n + '</span></a>'
                    : '';
                // one slot at the indent (snoro 2026-11-18): a parent shows its
                // ▼/▶ (with the child count) there, a leaf child shows "└"
                ind = '<span class="pp-tind" style="padding-left:' + (Math.min(item.depth, 10) * 16) + 'px;" data-depth="' + item.depth + '">' +
                    (tgl || (item.depth > 0 ? '<span class="pp-tbr">└</span>' : '')) + '</span>';
            }
            var trCls = (own(SEL, i.id) ? 'sel' : '') + (i.unread ? ' pp-unread' : '') +
                (TREE_ON && item.depth > 0 ? ' pp-tkid pp-td' + Math.min(item.depth, 4) : '') +
                (TREE_ON && item.n > 0 ? ' pp-tpar' : '');
            var ms = own(D.milestones, i.ms);
            rows += '<tr data-issue="' + esc(i.id) + '"' + (trCls.trim() ? ' class="' + trCls.trim() + '"' : '') + '>' +
                (BULK_ON ? '<td class="chk"><input type="checkbox" class="pp-bchk" data-id="' + esc(i.id) + '"' +
                    (own(SEL, i.id) ? ' checked' : '') + '></td>' : '') +
                '<td>' + starBtn('star', D.issue_table, i.id, i.star, i.my_star) + '</td>' +
                '<td' + edAttr('ty') + '>' + tag(i.ty) + edPen() + '</td>' +
                '<td><a class="bl-key" href="#issue-' + esc(keyRef(i)) + '" title="' + esc(i.key) + '">' + esc(keyNum(i)) + '</a>' + ikCopyButton(i) + '</td>' +
                (aliasOn() ? '<td class="pp-al">' + (i.al ? '<a href="#issue-' + esc(i.al) + '">' + esc(i.al) + '</a>' : '') + '</td>' : '') +
                // indent/toggle in a fixed slot on the left, subject and 件名サブ
                // stacked to its right so both lines start at the same place
                (TREE_ON ? '<td class="pp-tcol">' + ind + '</td>' : '') +
                '<td class="sub">' + parent + rcMark(i) + esc(i.subject) +
                    (i.sub ? '<div class="pp-subsub">' + esc(i.sub) + '</div>' : '') + '</td>' +
                '<td>' + avlist(i.as) + '</td>' +
                ocols.map(function (c) { return ocolCell(i, c); }).join('') +
                '<td' + edAttr('st') + '>' + pill(i.st) + edPen() + '</td>' +
                '<td' + edAttr('pr') + '>' + prio(i.pr) + edPen() + '</td>' +
                '<td style="white-space:nowrap;"' + edAttr('ms') + '>' + esc(ms ? ms.l : '') + edPen() + '</td>' +
                '<td style="white-space:nowrap;"' + edAttr('cat') + '>' + (i.cat || []).map(function (c) { return esc(own(D.categories, c) || ''); }).join(', ') + edPen() + '</td>' +
                '<td style="white-space:nowrap;"' + edAttr('sd') + '>' + esc(d2(i.sd)) + edPen() + '</td>' +
                '<td' + edAttr('dd') + '>' + dueCell(i) + edPen() + '</td>' +
                '<td>' + pbar(i.pg) + rollupBadge(ru, i.id) + '</td>' +
                '<td class="up">' + esc(i.up) + '</td></tr>';
        });
        // M10: a list emptied by its filters says so, with the way out.
        if (!rows && D.issues.length && anyFilter()) {
            rows = '<tr class="pp-empty-row"><td colspan="40"><i class="fa fa-filter"></i> ' + esc(L.empty_filtered) +
                ' <button type="button" class="bl-btn-sub pp-empty-clear">' + esc(L.f_clear_all) + '</button></td></tr>';
        }
        document.getElementById('pp-issue-rows').innerHTML = rows;
        renderPager(total, from, shown, pages, matched.length);
        syncBulkBar();
        hintList(matched.length, savedList().length);
        ppListFit();
    }

    // `matched` is how many issues pass the filters; in tree mode `total` is
    // the listed rows, which is fewer while parents are folded.
    function renderPager(total, from, shown, pages, matched) {
        // Two different facts, and the reader wants both: where this page sits
        // inside the filtered list, and how much of the project that list is.
        var count = fmt(L.cnt_line, typeof matched === 'number' ? matched : total, D.issues.length + arCount());
        renderArchiveNote();
        document.getElementById('pp-issue-count').textContent = (total === 0 || pages <= 1)
            ? count
            : String(L.pg_range).replace(':from', from + 1).replace(':to', from + shown)
                .replace(':total', total) + '　' + count;

        var nav = document.getElementById('pp-pg-nav');
        if (!nav) { return; }
        if (pages <= 1) { nav.innerHTML = ''; return; }
        var html = '<button type="button" data-pg="' + (PAGE.no - 1) + '"' +
            (PAGE.no <= 1 ? ' disabled' : '') + '>' + esc(L.pg_prev) + '</button>';
        pageNumbers(PAGE.no, pages).forEach(function (n) {
            if (n === 0) { html += '<span class="gap">…</span>'; return; }
            html += '<button type="button" data-pg="' + n + '"' + (n === PAGE.no ? ' aria-current="true"' : '') +
                ' title="' + esc(String(L.pg_page).replace(':no', n)) + '">' + n + '</button>';
        });
        html += '<button type="button" data-pg="' + (PAGE.no + 1) + '"' +
            (PAGE.no >= pages ? ' disabled' : '') + '>' + esc(L.pg_next) + '</button>';
        nav.innerHTML = html;
    }
    // Under the list: how many finished issues are archived, and the switch.
    // Only while the status filter could match them (all, or a done status).
    function renderArchiveNote() {
        var note = document.getElementById('pp-ar-note');
        if (!note) { return; }
        var could = FILTER.st === '' || FILTER.st === ST_NOT_DONE || isDone(FILTER.st);
        if (!arOn() || !could) { note.innerHTML = ''; return; }
        note.innerHTML = AR.show
            ? esc(fmt(L.ar_shown, D.archive.count)) + ' <a href="javascript:void(0);" data-ar="0">' + esc(L.ar_hide) + '</a>'
            : esc(fmt(L.ar_hidden, D.archive.count, D.archive.days)) + ' <a href="javascript:void(0);" data-ar="1">' + esc(L.ar_show) + '</a>';
    }
    $(document).on('click', '#pp-ar-note a[data-ar]', function (ev) {
        if (!alive()) { return; }
        ev.preventDefault();
        showArchived(this.getAttribute('data-ar') === '1');
    });
    // First, last, and the neighbourhood of the current page; 0 stands for the
    // gap between them. Sixty pages of buttons would be its own kind of
    // unusable.
    function pageNumbers(cur, pages) {
        var out = [];
        for (var n = 1; n <= pages; n++) {
            if (n === 1 || n === pages || Math.abs(n - cur) <= 1) {
                if (out.length && n - out[out.length - 1] > 1) { out.push(0); }
                out.push(n);
            }
        }

        return out;
    }

    /* ------------------------------------------------ bulk edit --------- */
    function syncBulkBar() {
        var bar = document.getElementById('pp-bulk');
        if (!bar) { return; }
        var n = selIds().length;
        bar.hidden = !BULK_ON || n === 0;
        if (n) {
            fillBulkSelects();
            document.getElementById('pp-bulk-n').textContent = String(L.bk_selected).replace(':count', n);
        }
        var all = document.getElementById('pp-chk-all');
        if (all) {
            // The header box speaks for this page only. Half ticked is a real
            // third state, and indeterminate is how a checkbox says so.
            var boxes = [].slice.call(document.querySelectorAll('#pp-issue-rows .pp-bchk'));
            var ticked = boxes.filter(function (b) { return b.checked; }).length;
            all.checked = boxes.length > 0 && ticked === boxes.length;
            all.indeterminate = ticked > 0 && ticked < boxes.length;
            all.disabled = boxes.length === 0;
        }
        bkDisarm();
    }

    // Built once. Every one of them opens on "leave as is", so an apply with
    // nothing chosen is refused rather than silently blanking five fields.
    function fillBulkSelects() {
        var stSel = document.getElementById('pp-bk-st');
        if (!stSel || stSel.options.length) { return; }
        var start = function (id, label, clearable) {
            var sel = document.getElementById(id);
            sel.add(new Option(label + ': ' + L.bk_keep, ''));
            if (clearable) { sel.add(new Option(label + ': ' + L.bk_none, '__none__')); }
            return sel;
        };
        var st = start('pp-bk-st', L.col_st, false);
        (D.statuses || []).forEach(function (s) { st.add(new Option(s.l, s.k)); });

        var as = start('pp-bk-as', L.f_as, true);
        Object.keys(D.users || {}).sort(function (a, b) {
            return cmpText(user(a).n, user(b).n);
        }).forEach(function (u) { as.add(new Option(user(u).n, u)); });

        var ty = start('pp-bk-ty', L.f_ty, false);
        metaKeys(D.types).forEach(function (k) { ty.add(new Option(D.types[k].l, k)); });

        var pr = start('pp-bk-pr', L.f_pr, false);
        metaKeys(D.priorities).forEach(function (k) { pr.add(new Option(D.priorities[k].l, k)); });

        var ms = start('pp-bk-ms', L.f_ms, true);
        Object.keys(D.milestones || {}).sort(function (a, b) {
            return cmpText(msSortKey(a), msSortKey(b));
        }).forEach(function (k) { ms.add(new Option(D.milestones[k].l, k)); });
        // one custom attribute and its value (ISS02939)
        atBulkFill();
    }

    // Only the boxes the user moved. '__none__' is how the two clearable
    // fields say "clear it", which is a real edit and not the same as
    // "leave it". It is sent as '' for the assignees too: jQuery drops an
    // empty array from a form post altogether, so [] never arrived and the
    // assignees were never cleared.
    function bulkSet() {
        var set = {};
        [['pp-bk-st', 'st'], ['pp-bk-as', 'as'], ['pp-bk-ty', 'ty'],
         ['pp-bk-pr', 'pr'], ['pp-bk-ms', 'ms']].forEach(function (def) {
            var el = document.getElementById(def[0]);
            if (!el || el.value === '') { return; }
            if (el.value === '__none__') {
                set[def[1]] = '';
                return;
            }
            set[def[1]] = def[1] === 'as' ? [el.value] : el.value;
        });
        var at = atBulkSet();
        if (at) { set.at = at; }

        return set;
    }
    // The rows a bulk edit could not save, each with what stopped it. Kept
    // on screen until it is closed: a toast is gone before a list of keys
    // can be read, and these are exactly the rows still saying the old thing.
    function bkShowFailed(failed) {
        var box = document.getElementById('pp-bk-failed');
        if (!box) { return; }
        box.textContent = '';
        var list = Array.isArray(failed) ? failed : [];
        box.hidden = !list.length;
        if (!list.length) { return; }
        var head = document.createElement('div');
        head.className = 'hd';
        var title = document.createElement('span');
        title.textContent = String(L.bk_failed_title).replace(':count', list.length);
        var close = document.createElement('button');
        close.type = 'button';
        close.className = 'x';
        close.title = L.m_close;
        close.setAttribute('aria-label', L.m_close);
        close.textContent = '×';
        close.addEventListener('click', function () { bkShowFailed([]); });
        head.appendChild(title);
        head.appendChild(close);
        box.appendChild(head);
        var ul = document.createElement('ul');
        list.forEach(function (row) {
            var li = document.createElement('li');
            var key = document.createElement('a');
            key.className = 'bl-key';
            key.href = '#' + issueHash(row && row.id);
            key.textContent = String((row && row.key) || ('#' + (row && row.id)));
            var why = document.createElement('span');
            why.className = 'why';
            why.textContent = String((row && row.message) || L.bk_fail);
            li.appendChild(key);
            li.appendChild(why);
            ul.appendChild(li);
        });
        box.appendChild(ul);
    }

    // Two clicks rather than a confirm dialog: this screen never opens a
    // browser modal, and an edit that lands on forty issues at once deserves
    // one deliberate second thought.
    var bkArmed = false;
    var bkTimer = null;
    var bkUpdating = false;
    function bkDisarm() {
        var btn = document.getElementById('pp-bk-apply');
        if (!btn) { return; }
        bkArmed = false;
        if (bkTimer) { clearTimeout(bkTimer); bkTimer = null; }
        btn.classList.remove('armed');
        btn.disabled = bkUpdating;
        btn.textContent = bkUpdating ? L.bk_working : L.bk_apply;
    }
    function bkArm() {
        var btn = document.getElementById('pp-bk-apply');
        bkArmed = true;
        btn.classList.add('armed');
        btn.textContent = L.bk_again;
        bkTimer = setTimeout(bkDisarm, 4000);
    }

    function bulkApply() {
        if (bkUpdating) { return; }
        var ids = selIds();
        var set = bulkSet();
        if (!ids.length) { return; }
        if (!Object.keys(set).length) { toast(L.bk_nothing, true); return; }
        if (!bkArmed) { bkArm(); return; }

        var btn = document.getElementById('pp-bk-apply');
        if (bkTimer) { clearTimeout(bkTimer); bkTimer = null; }
        bkArmed = false;
        bkUpdating = true;
        btn.disabled = true;
        btn.classList.remove('armed');
        btn.textContent = L.bk_working;

        $.ajax({
            url: D.urls.bulk,
            type: 'POST',
            data: { ids: ids, set: set },
            dataType: 'json',
            headers: { 'X-CSRF-TOKEN': CSRF }
        }).done(function (res) {
            bkUpdating = false;
            if (!alive()) { return; }
            if (!res || !res.ok) { toast(errText(null, res, L.bk_fail), true); bkDisarm(); return; }
            var failed = Array.isArray(res.failed) ? res.failed : [];
            var parts = [String(L.bk_done).replace(':count', res.updated)];
            if (res.denied) { parts.push(String(L.bk_denied).replace(':count', res.denied)); }
            if (failed.length) { parts.push(String(L.bk_failed).replace(':count', failed.length)); }
            if (res.missing) { parts.push(String(L.bk_missing).replace(':count', res.missing)); }
            toast(parts.join(' '), !res.updated || failed.length > 0);
            bkShowFailed(failed);
            clearSelection();
            // The rows on screen are now wrong in exactly the way the edit
            // was right, so the list is re-read rather than patched.
            refreshData(true);
        }).fail(function (xhr) {
            bkUpdating = false;
            if (!alive()) { return; }
            toast(xhrErr(xhr, L.bk_fail), true);
            bkDisarm();
        });
    }

    /* ------------------------------------------------ saved conditions -- */
    function savedList() {
        try {
            var raw = JSON.parse(localStorage.getItem(SAVED_KEY) || '[]');
            return Array.isArray(raw) ? raw : [];
        } catch (e) { return []; }
    }
    function savedWrite(list) {
        try { localStorage.setItem(SAVED_KEY, JSON.stringify(list)); } catch (e) { /* ignore */ }
    }
    function renderSaved() {
        var box = document.getElementById('pp-sf-list');
        if (!box) { return; }
        var list = savedList();
        if (!list.length) {
            box.innerHTML = '<div class="pp-sf-empty">' + esc(L.sf_none) + '</div>';
            return;
        }
        box.innerHTML = list.map(function (item, n) {
            return '<div class="pp-sf-item" data-sf="' + n + '" role="button" tabindex="0">' +
                '<i class="fa fa-filter"></i><span class="nm">' + esc(item.n) + '</span>' +
                '<a class="rm" href="javascript:void(0);" data-sf-del="' + n + '" title="' + esc(L.sf_del) +
                '" aria-label="' + esc(L.sf_del) + '"><i class="fa fa-times"></i></a></div>';
        }).join('');
    }
    function savedApply(n) {
        var item = savedList()[n];
        if (!item) { return; }
        FILTER_FIELDS.forEach(function (k) {
            var v = own(item.f, k);
            FILTER[k] = (v != null && typeof v !== 'object') ? String(v) : '';
        });
        SORT.by = (item.s && own(SORT_COLS, item.s.by)) ? item.s.by : SORT_DEF.by;
        SORT.dir = (item.s && item.s.dir === 'desc') ? 'desc' : 'asc';
        sfMenu(false);
        afterFilterChange();
    }
    function savedAdd() {
        var input = document.getElementById('pp-sf-name');
        var name = input.value.trim();
        if (name === '') { toast(L.sf_need_name, true); input.focus(); return; }
        var list = savedList();
        var at = -1;
        list.forEach(function (item, n) { if (item.n === name) { at = n; } });
        if (at < 0 && list.length >= SAVED_MAX) {
            toast(String(L.sf_full).replace(':max', SAVED_MAX), true);
            return;
        }
        var entry = { n: name, f: {}, s: { by: SORT.by, dir: SORT.dir } };
        FILTER_FIELDS.forEach(function (k) { entry.f[k] = FILTER[k]; });
        // Saving twice under one name corrects that condition rather than
        // growing a second one spelled the same.
        if (at >= 0) { list[at] = entry; } else { list.push(entry); }
        savedWrite(list);
        input.value = '';
        renderSaved();
        toast(String(L.sf_saved).replace(':name', name));
    }
    function savedDelete(n) {
        var list = savedList();
        var item = list[n];
        if (!item) { return; }
        list.splice(n, 1);
        savedWrite(list);
        renderSaved();
        toast(String(L.sf_deleted).replace(':name', item.n));
    }
    function sfMenu(open) {
        var menu = document.getElementById('pp-sf-menu');
        var btn = document.getElementById('pp-sf-btn');
        if (!menu) { return; }
        menu.hidden = !open;
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (!open) { return; }
        renderSaved();
        var box = document.getElementById('pp-f-save');
        try { box.checked = !!localStorage.getItem(FILTER_KEY); } catch (e) { box.checked = false; }
    }

    /* ------------------------------------------------ staying current --- */
    // checked: when the cheap question was last asked. path: the screen the
    // reader was standing on, so the filter rewriting the address bar is not
    // mistaken for a move between tabs. When the data on screen was fetched is
    // D.now, which the server stamps into the payload it builds.
    var LIVE = { stamp: String(D.stamp || ''), busy: false, checked: 0, path: parseHash().path };
    // How old the last answer has to be before asking again. Returning to the
    // window fires on every alt-tab and gets the long floor; clicking a tab
    // inside the page is a deliberate "show me this" and gets the short one.
    var CHECK_GAP_NAV = 5000;
    var CHECK_GAP_FOCUS = 30000;

    // seq: the number the request for this payload took when it left (0 for
    // the page itself). An issue answered by a request that left after it -
    // a comment posted while the refresh was on its way - is newer than the
    // payload, and keeps both its detail and its row.
    function seedDetails(seq) {
        var held = DET || {};
        var since = seq || 0;
        DET = {};
        Object.keys(D.details || {}).forEach(function (key) {
            var det = D.details[key];
            det.issue = det.issue || issueById(key);
            if (det.issue) { det._seq = since; DET[key] = det; }
        });
        Object.keys(held).forEach(function (key) {
            var old = held[key];
            if (!old || !(old._seq > since)) { return; }
            DET[key] = old;
            (D.issues || []).forEach(function (row, n) {
                if (old.issue && String(row.id) === String(key)) { D.issues[n] = old.issue; }
            });
        });
    }
    // Every answer that writes DET is numbered when its request leaves, and
    // one older than the copy already held is dropped: a prefetch sent before
    // a comment was posted must not land after the comment's own answer and
    // take the comment back off the screen. Returns whether it was stored.
    function detPut(id, det, seq) {
        var held = own(DET, String(id));
        var mine = (typeof seq === 'number') ? seq : ++DET_SEQ;
        if (held && typeof held._seq === 'number' && held._seq > mine) { return false; }
        det._seq = mine;
        DET[id] = det;

        return true;
    }
    function markStale(on) {
        var btn = root.querySelector('.pp-refresh');
        if (!btn) { return; }
        btn.classList.toggle('stale', !!on);
        btn.title = on ? L.rf_stale : L.rf_button;
    }
    // Anything the reader has started and not finished outranks freshness:
    // a redraw underneath an open form or a half written comment would throw
    // away work to deliver news.
    function safeToRefresh(detailOnly) {
        if (FM.el && !FM.el.hidden) { return false; }
        if (NED.el && !NED.el.hidden) { return false; }
        if (!detailOnly && selIds().length) { return false; }
        // A comment being rewritten in place is half-written work too, and a
        // redraw of the timeline is exactly what takes its box away.
        if (document.querySelector('#pp-d-timeline .cedit')) { return false; }
        if (currentScreen === 'issue' && (cmtBusy || (QE.editable && Object.keys(qeChanged()).length))) { return false; }
        var status = document.getElementById('pp-cmt-status');
        if (currentScreen === 'issue' && status && status.value) { return false; }
        var box = document.getElementById('pp-cmt-text');

        return !(box && box.value.trim() !== '');
    }
    // ISS00257: after a save, fetch the project's data and draw the screens
    // again in place, instead of reloading the whole page (its HTML, the
    // portal's script and every widget). hash: the screen to go to once the
    // data is in, e.g. the issue just created. flash: said when it is done.
    // Falls back to the reload it replaces when the data cannot be fetched
    // or something half-written is on the screen.
    function softReload(hash, flash) {
        refreshData(true, function (ok) {
            if (!ok) {
                if (flash) {
                    try { sessionStorage.setItem('pp-ne-saved-flash', flash); } catch (e) { /* private mode */ }
                }
                if (hash) { location.hash = hash; }
                location.reload();
                return;
            }
            if (flash) { toast(flash); }
            if (hash && location.hash.slice(1) !== hash) { location.hash = hash; }
        });
    }
    // quiet: the caller has already said something about why the data moved,
    // so a second toast would only talk over it.
    // done: called with whether the screen really was rebuilt, for a caller
    // that has a fallback to fall back to.
    function refreshData(quiet, done) {
        if (!(D.urls && D.urls.data)) {
            if (done) { done(false); }
            return;
        }
        if (LIVE.busy) {
            // One is already on its way, but it was asked before whatever
            // this caller has just changed - a bulk edit, a card saved on the
            // board - so it would land showing the state before. It goes
            // round once more when it lands instead.
            LIVE.again = LIVE.again || { quiet: true, dones: [] };
            if (!quiet) { LIVE.again.quiet = false; }
            if (done) { LIVE.again.dones.push(done); }
            return;
        }
        LIVE.busy = true;
        var ok = false;
        var seq = ++DET_SEQ;
        var btn = root.querySelector('.pp-refresh');
        if (btn) { btn.classList.add('busy'); }
        $.ajax({ url: D.urls.data, dataType: 'json', cache: false })
            .done(function (res) {
                if (!alive()) { return; }
                if (!res || !res.ok || !res.payload) { toast(errText(null, res, L.rf_fail), true); return; }
                if (!safeToRefresh()) { markStale(true); return; }
                // A redraw that stops half way leaves the screens drawn from
                // two different payloads, and - thrown out of here - this
                // refresh marked busy for good, so nothing is ever fetched
                // again. It is reported as not done instead: the caller falls
                // back to loading the page, and the next change is still seen.
                try {
                    adoptPayload(res.payload, seq);
                } catch (e) {
                    if (window.console && console.error) { console.error(e); }
                    markStale(true);
                    return;
                }
                ok = true;
                if (!quiet) { toast(L.rf_done); }
            })
            .fail(function (xhr) { if (alive()) { toast(xhrErr(xhr, L.rf_fail), true); } })
            .always(function () {
                LIVE.busy = false;
                if (!alive()) { return; }
                LIVE.checked = Date.now();
                var b = root.querySelector('.pp-refresh');
                if (b) { b.classList.remove('busy'); }
                if (done) { done(ok); }
                var again = LIVE.again;
                LIVE.again = null;
                if (again) {
                    refreshData(again.quiet, function (fresh) {
                        again.dones.forEach(function (fn) { fn(fresh); });
                    });
                }
            });
    }
    // Swap the data the whole screen is drawn from, then draw it again. The
    // filter, the sort, the page and the open screen all survive: they live
    // outside the payload, which is the point of doing it this way rather
    // than reloading the page.
    function adoptPayload(next, seq) {
        // Packed like the rows the page came with: a row left as it was sent
        // has no assignees, no key and no links, and the first screen that
        // reads one stops the whole redraw.
        unpackRows(next);
        // an issue just opened (or marked by hand) stays as the reader left it
        applyLocalReads(next);
        D = next;
        // The new payload has no archived rows again; bring them back if they
        // were on show.
        AR.loaded = false;
        AR.busy = null;
        if (AR.show) { loadArchived().then(function () { if (alive()) { renderIssues(); } }); }
        ST = {};
        ST_RANK = {};
        (D.statuses || []).forEach(function (s, n) { ST[s.k] = s; ST_RANK[s.k] = n; });
        LIVE.stamp = String(D.stamp || '');
        // Something in this project moved, which is the whole reason this
        // payload was fetched. Every form built from the old one would open
        // showing what the row used to say - and save that back over it.
        NED.editFormCache = {};
        NED.editFormAt = {};
        NED.createFormCache = {};
        clearSelection();
        seedDetails(seq);
        // The board and the gantt are whole pages the server renders; marking
        // them unread is enough - they reload the next time their tab is
        // opened, and right now if that is where the reader is standing.
        ['board', 'gantt'].forEach(function (kind) {
            if (!EMBEDS[kind]) { return; }
            EMBEDS[kind].loaded = false;
            // One already in flight cannot be called back; it is asked to go
            // round once more when it lands instead.
            if (EMBEDS[kind].loading) { EMBEDS[kind].refetch = true; }
        });
        // Emptied so the next render fills them from the data that just
        // arrived; leaving them would keep offering a milestone that has been
        // deleted and hide the member who joined this morning.
        ['pp-f-as', 'pp-f-ty', 'pp-f-pr', 'pp-f-ms', 'pp-f-cat', 'pp-f-rl', 'pp-f-df'].forEach(function (id) {
            var sel = document.getElementById(id);
            while (sel && sel.options.length > 1) { sel.remove(1); }
            // the people of the role filter come in groups
            if (sel) { sel.querySelectorAll('optgroup').forEach(function (g) { g.remove(); }); }
        });
        ['pp-bk-st', 'pp-bk-as', 'pp-bk-ty', 'pp-bk-pr', 'pp-bk-ms'].forEach(function (id) {
            var sel = document.getElementById(id);
            if (sel) { sel.innerHTML = ''; }
        });
        renderHead();
        markStale(false);
        renderHome();
        renderIssues();
        renderBurndownSelect();
        renderWiki(currentWiki);
        renderFiles();
        syncUploadUi();
        var path = parseHash().path;
        var open = path.match(/^issue-(.+)$/);
        if (open) {
            var id = resolveIssueRef(open[1]);
            if (id) { renderDetail(id); }
        }
        if (path === 'board' || path === 'gantt') { ensureEmbed(path); }
    }
    // The cheap half of the question. One aggregate on the server, with a
    // floor underneath it so a burst of navigation asks it once.
    function checkStamp(gap) {
        if (LIVE.busy || !(D.urls && D.urls.stamp)) { return; }
        var now = Date.now();
        if (now - LIVE.checked < (gap || CHECK_GAP_FOCUS)) { return; }
        LIVE.checked = now;
        $.ajax({ url: D.urls.stamp, dataType: 'json', cache: false }).done(function (res) {
            if (!alive()) { return; }
            if (!res || !res.ok || String(res.stamp) === LIVE.stamp) { return; }
            if (safeToRefresh()) { refreshData(false); } else { markStale(true); }
        });
    }
    /* ------------------------------------------------ the list's ⋯ ------ */
    // Button map: what the list does as a whole, in one menu - the downloads
    // (the same four as the download link's) and choosing every issue on the
    // screen, which opens the bulk change bar.
    function listMoreItems() {
        var check = document.getElementById('pp-chk-all');
        // the bulk change only where the list offers its ticks
        var canBulk = !!(check && check.offsetParent !== null && !check.disabled);
        return [
            { l: L.f_dl_xlsx_all, i: 'fa-file-excel-o', f: function () { exportIssues('xlsx', 'all'); } },
            { l: L.f_dl_xlsx_screen, i: 'fa-file-excel-o', f: function () { exportIssues('xlsx', 'screen'); } },
            { l: L.f_dl_csv_all, i: 'fa-file-text-o', f: function () { exportIssues('csv', 'all'); } },
            { l: L.f_dl_csv_screen, i: 'fa-file-text-o', f: function () { exportIssues('csv', 'screen'); } },
        ].concat(canBulk ? [
            { l: L.l_bulk_pick, i: 'fa-check-square-o', f: function () {
                var all = document.getElementById('pp-chk-all');
                if (!all) { return; }
                all.checked = true;
                all.dispatchEvent(new Event('change', { bubbles: true }));
            } }
        ] : []);
    }
    document.addEventListener('click', function onPpListMore(ev) {
        if (!alive()) { document.removeEventListener('click', onPpListMore); return; }
        var btn = ev.target.closest && ev.target.closest('#pp-l-more');
        if (!btn) { return; }
        ev.preventDefault();
        ev.stopPropagation();
        var r = btn.getBoundingClientRect();
        ctxOpen(r.left, r.bottom + 2, listMoreItems());
    });
