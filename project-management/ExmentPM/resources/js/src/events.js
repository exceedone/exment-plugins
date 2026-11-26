    /* ------------------------------------------------ events ------------ */
    document.getElementById('pp-tabs').addEventListener('click', function (ev) {
        var t = ev.target.closest('.bl-tab');
        if (!t) { return; }
        var scr = t.getAttribute('data-scr');
        // Clicking the tab already open changes no hash, so it routes nowhere.
        // Read as "show me this again" it is the same request as arriving, and
        // gets the same answer.
        if (scr === parseHash().path) { checkStamp(CHECK_GAP_NAV); }
        location.hash = scr;
    });
    // Pointing at the board tab is about as clear a statement of intent as
    // there is, and the widget behind it takes the best part of a second to
    // come back. Starting there buys the few hundred milliseconds between
    // the cursor arriving and the click - and if the warm-up is already in
    // the background queue, waiting its turn behind a pause, this is what
    // takes it out and runs it now.
    document.getElementById('pp-tabs').addEventListener('mouseover', function (ev) {
        var t = ev.target.closest('.bl-tab');
        if (!t) { return; }
        var scr = t.getAttribute('data-scr');
        if (scr === 'board' || scr === 'gantt') { ensureEmbed(scr); }
    });
    /* ---- hovercard: who that @ is, what that #123 is -------------------
       Two kinds, one panel. An issue card is drawn straight out of the rows
       this screen is already holding, so it costs no request at all; a user
       card needs the one thing the browser cannot work out for itself - who
       the person is - and asks the server once, then remembers the answer
       for as long as the page lives. Both are read on the way past, so the
       budget is a fraction of what a click would be allowed. */
    var HC = { el: null, timer: null, key: '', users: {}, seq: 0 };
    function hcEl() {
        if (HC.el) { return HC.el; }
        var el = document.createElement('div');
        el.id = 'pp-hc';
        // Off <body> there is no .pmportal ancestor left, and half the
        // stylesheet is written as ".pmportal <thing>" - so the panel wears
        // the class itself, which is also what carries the palette.
        el.className = 'pmportal pp-hc';
        el.hidden = true;
        el.addEventListener('mouseenter', function () { clearTimeout(HC.timer); });
        el.addEventListener('mouseleave', hcHide);
        document.body.appendChild(el);
        HC.el = el;
        return el;
    }
    function hcHide() {
        clearTimeout(HC.timer);
        HC.key = '';
        // Any answer still in flight is for a card nobody is looking at.
        HC.seq++;
        if (HC.el) { HC.el.hidden = true; HC.el.classList.remove('on'); }
    }
    // Under what was hovered, or above it when the foot of the window is
    // nearer than the card is tall. Page coordinates: the panel is placed
    // against the document, not the viewport.
    function hcPlace(anchor) {
        var el = hcEl();
        var r = anchor.getBoundingClientRect();
        var w = el.offsetWidth, h = el.offsetHeight;
        var right = window.scrollX + document.documentElement.clientWidth - w - 8;
        var left = Math.max(window.scrollX + 8, Math.min(r.left + window.scrollX, right));
        var top = (r.bottom + h + 12 <= window.innerHeight)
            ? r.bottom + window.scrollY + 6
            : Math.max(window.scrollY + 4, r.top + window.scrollY - h - 6);
        el.style.left = left + 'px';
        el.style.top = top + 'px';
    }
    function hcOpen(anchor, html) {
        var el = hcEl();
        el.innerHTML = html;
        el.hidden = false;
        // Measured after the content is in, or the height is last card's.
        hcPlace(anchor);
        el.classList.add('on');
    }
    function hcRows(rows) {
        var body = rows.filter(function (r) { return r && r[1]; }).map(function (r) {
            return '<div class="r"><div class="k">' + esc(r[0]) + '</div><div class="v">' + r[1] + '</div></div>';
        }).join('');

        return body ? '<div class="pp-hc-rows">' + body + '</div>' : '';
    }
    function hcMsg(anchor, text) {
        hcOpen(anchor, '<div class="pp-hc-msg">' + esc(text) + '</div>');
    }
    // What this person is carrying on this project. Counted here rather than
    // asked for, because the screen is holding every issue already and a
    // round trip for a number it can add up itself is a round trip wasted.
    function hcUserLoad(uid) {
        var all = 0, open = 0, over = 0;
        (D.issues || []).forEach(function (i) {
            var as = i.as || [];
            var mine = false;
            for (var n = 0; n < as.length; n++) { if (String(as[n]) === String(uid)) { mine = true; } }
            if (!mine) { return; }
            all++;
            if (isDone(i.st)) { return; }
            open++;
            if (D.today && dueOver(i)) { over++; }
        });

        return { all: all, open: open, over: over };
    }
    var HC_ROLE = { manager: 'role_pm', member: 'role_member', viewer: 'role_viewer' };
    // Which of the three lists the project names this person in. The payload
    // carries all three, so this costs nothing.
    function hcRoleOf(uid) {
        var p = D.project || {};
        var has = function (list) {
            var hit = false;
            (list || []).forEach(function (v) { if (String(v) === String(uid)) { hit = true; } });
            return hit;
        };
        if (String(p.manager) === String(uid)) { return 'manager'; }
        if (has(p.members)) { return 'member'; }
        if (has(p.viewers)) { return 'viewer'; }

        return null;
    }
    // What the page already knows about this person. Anybody the screen has
    // drawn an avatar for is in here, which is everybody a comment can name -
    // so the card for a mention opens without a request, and the endpoint is
    // left for the stranger the payload never had to carry.
    function hcUserLocal(uid) {
        var u = D.users && D.users[uid];
        if (!u || u.cd === undefined) { return null; }

        return { name: u.n, code: u.cd, orgs: u.og || [], role: hcRoleOf(uid) };
    }
    function hcUserHtml(uid, info) {
        var u = user(uid);
        var load = hcUserLoad(uid);
        var name = (info && info.name) || u.n;
        var roleKey = info ? own(HC_ROLE, info.role) : undefined;
        var role = roleKey ? L[roleKey] : (info ? L.hc_norole : '');
        return '<div class="pp-hc-top">' +
            '<span class="bl-av lg" style="background:' + ppColor(u.c) + '">' + esc(u.i) + avImg(u) + '</span>' +
            '<div><div class="pp-hc-nm">' + esc(name) + '</div>' +
            (role ? '<div class="pp-hc-sub">' + esc(role) + '</div>' : '') + '</div></div>' +
            hcRows([
                [L.hc_code, info && info.code ? esc(info.code) : ''],
                [L.hc_orgs, info && info.orgs && info.orgs.length ? esc(info.orgs.join(L.sep_dot)) : ''],
                [L.hc_assigned, load.all
                    ? esc(load.all + L.unit_cnt) + '<div class="pp-hc-sub">' +
                        esc(fmt2(L.hc_open_overdue, load.open, load.over)) + '</div>'
                    : ''],
            ]) +
            (info ? '' : '<div class="pp-hc-msg">' + esc(L.hc_loading) + '</div>');
    }
    // Two positional slots, so the Japanese can put them in either order.
    function fmt2(tpl, a, b) {
        return String(tpl).replace(/%1\$s/g, String(a)).replace(/%2\$s/g, String(b));
    }
    // A label from another project: real text, neutral colour, because the
    // colour that goes with it is master data this page does not have.
    function plainPill(label) {
        return label ? '<span class="bl-pill" style="background:#95a5a6">' + esc(label) + '</span>' : '';
    }
    function plainTag(label) {
        return label ? '<span class="bl-tag" style="background:#95a5a6">' + esc(label) + '</span>' : '';
    }
    function hcIssueHtml(i) {
        var parent = i.parent ? issueById(i.parent) : null;
        // A row from another project can carry a status or a type this page has
        // no master data for. The server sends the plain label for those; drawn
        // without a colour, since the colour belongs to the other project.
        var st = own(ST, i.st) ? pill(i.st) : plainPill(i.st_l);
        var ty = tag(i.ty) || plainTag(i.ty_l);
        return '<div class="pp-hc-top"><div style="min-width:0">' +
            '<div class="pp-hc-key">' + ty + ' ' + esc(i.key) + '</div>' +
            '<div class="pp-hc-nm">' + esc(i.subject) + '</div></div></div>' +
            hcRows([
                [L.col_st, (st + ' ' + prio(i.pr)).trim()],
                [L.col_as, (i.as || []).length ? avlist(i.as) : ''],
                [L.col_dd, i.dd ? esc(d2(i.dd)) : ''],
                [L.lbl_pg, (i.pg === 0 || i.pg) ? esc(i.pg + '%') : ''],
                [L.lbl_parent, parent ? esc(parent.key + ' ' + parent.subject) : ''],
            ]);
    }
    // An issue this project is not carrying - linked from somewhere else, or
    // moved. The row comes from the server; the labels are master data the
    // screen already has, so only the row itself crosses the wire.
    function hcIssueFetch(anchor, id, seq) {
        hcFetch('issue', id, function (res) {
            if (seq !== HC.seq) { return; }
            if (!res || !res.ok) { hcMsg(anchor, L.hc_no_issue); return; }
            Object.keys(res.users || {}).forEach(function (uid) {
                if (!D.users[uid]) { D.users[uid] = res.users[uid]; }
            });
            hcOpen(anchor, hcIssueHtml({
                id: res.id, key: res.key || ('#' + res.id), subject: res.subject,
                ty: res.ty, ty_l: res.ty_l, st: res.st, st_l: res.st_l,
                pr: res.pr, as: res.as, dd: res.dd, pg: res.pg, parent: null
            }));
        });
    }
    // Each card is asked for once per page: hovering the same link again,
    // or several links to the same issue, costs no request.
    var HC_CACHE = {};
    function hcFetch(kind, id, done) {
        var key = kind + ':' + id;
        if (own(HC_CACHE, key)) { HC_CACHE[key].then(done); return; }
        var url = D.urls.lightapi_base + '/card/' + kind + '/' + encodeURIComponent(id) +
            '?project=' + encodeURIComponent(D.project.id);
        HC_CACHE[key] = fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res || !res.ok) { delete HC_CACHE[key]; }   // a failure may be asked again
                return res;
            }, function () { delete HC_CACHE[key]; return null; });
        HC_CACHE[key].then(done);
    }
    function hcShow(anchor) {
        var uid = anchor.dataset.uid;
        var iid = anchor.dataset.issue;
        var key = uid ? ('u:' + uid) : ('i:' + iid);
        if (!uid && !iid) { return; }
        if (key === HC.key && HC.el && !HC.el.hidden) { return; }
        HC.key = key;
        var seq = ++HC.seq;
        if (iid) {
            var hit = issueById(iid);
            if (hit) { hcOpen(anchor, hcIssueHtml(hit)); return; }
            hcMsg(anchor, L.hc_loading);
            hcIssueFetch(anchor, iid, seq);
            return;
        }
        if (HC.users[uid]) { hcOpen(anchor, hcUserHtml(uid, HC.users[uid])); return; }
        var local = hcUserLocal(uid);
        if (local) { hcOpen(anchor, hcUserHtml(uid, local)); return; }
        hcOpen(anchor, hcUserHtml(uid, null));
        hcFetch('user', uid, function (res) {
            if (seq !== HC.seq) { return; }
            if (!res || !res.ok) { hcMsg(anchor, L.hc_fail); return; }
            HC.users[uid] = res;
            hcOpen(anchor, hcUserHtml(uid, res));
        });
    }
    root.addEventListener('mouseover', function (ev) {
        var card = ev.target.closest('.pp-card');
        if (!card) { return; }
        clearTimeout(HC.timer);
        // Long enough that a sweep across a paragraph of mentions opens
        // nothing, short enough that stopping on one feels immediate.
        HC.timer = setTimeout(function () { hcShow(card); }, 120);
    });
    root.addEventListener('mouseout', function (ev) {
        var card = ev.target.closest('.pp-card');
        if (!card) { return; }
        var to = ev.relatedTarget;
        if (to && HC.el && (HC.el === to || HC.el.contains(to))) { return; }
        clearTimeout(HC.timer);
        HC.timer = setTimeout(hcHide, 180);
    });
    // Both outlive a pjax swap, so each stands down with its page - taking the
    // card it may have left open on <body> with it.
    window.addEventListener('scroll', function onHcScroll() {
        if (!alive()) { window.removeEventListener('scroll', onHcScroll, true); hcHide(); return; }
        if (HC.el && !HC.el.hidden) { hcHide(); }
    }, true);
    document.addEventListener('keydown', function onHcKey(ev) {
        if (!alive()) { document.removeEventListener('keydown', onHcKey); hcHide(); return; }
        if (ev.key === 'Escape' && HC.el && !HC.el.hidden) { hcHide(); }
    });

    // Hover on any form-modal link warms the standby iframe for that URL
    // so a subsequent click is a hot swap (0ms) instead of a full page load.
    // Coalesced so quick mouse sweeps don't burn a request per element.
    var hoverPrewarmTimer = null;
    var hoverPrewarmUrl = '';
    // Separate coalescer for native-modal (edit / create) prewarms so the
    // iframe timer above doesn't block them. 150ms debounce matches the
    // task spec — long enough that scrolling past a row of 編集 buttons
    // doesn't spam /edit endpoints, short enough that a deliberate hover
    // lands the payload before the click event fires.
    var nedHoverTimer = null;
    var nedHoverKey = '';
    function nedHoverDispatch(link) {
        if (!link) { return; }
        var url = link.dataset && link.dataset.url;
        if (!url) { return; }
        var editHit = maybeNedit(url);
        var createTable = editHit ? null : maybeNcreate(url);
        if (!editHit && !createTable) { return null; }
        return { edit: editHit, create: createTable, url: url };
    }
    root.addEventListener('mouseover', function (ev) {
        var link = ev.target.closest('.pp-mlink');
        if (!link) { return; }
        // First: native-modal prefetch (edit rows / create rows). This
        // path runs even when D.features.prefetch is off — it costs one
        // lightapi request per deliberate hover and pays back on the
        // click.
        var nedHit = nedHoverDispatch(link);
        if (nedHit) {
            var key = nedHit.edit
                ? ('e:' + nedHit.edit.table + ':' + nedHit.edit.id)
                : ('c:' + nedHit.create);
            if (key !== nedHoverKey) {
                nedHoverKey = key;
                clearTimeout(nedHoverTimer);
                nedHoverTimer = setTimeout(function () {
                    if (nedHit.edit) { nedPrewarmEditForm(nedHit.edit.table, nedHit.edit.id); }
                    else if (nedHit.create) { nedPrewarmCreateForm(nedHit.create); }
                }, 150);
            }
            return;
        }
        // Otherwise: warm the standby iframe for wiki / history / etc.
        if (!FM.warm || !(D.features && D.features.prefetch)) { return; }
        var iframeUrl = link.dataset.url;
        if (!iframeUrl || iframeUrl === hoverPrewarmUrl || (FM.warm.dataset.url === iframeUrl && FM.warm.dataset.ready)) { return; }
        hoverPrewarmUrl = iframeUrl;
        clearTimeout(hoverPrewarmTimer);
        hoverPrewarmTimer = setTimeout(function () { prewarm(iframeUrl); }, 90);
    });
    // Mousedown fires ~50-100ms before click on a normal mouse press —
    // giving that head start to the fetch is basically free and closes
    // the gap for users who hovered too briefly to trip the 150ms timer
    // (or clicked with keyboard focus after tabbing over).
    root.addEventListener('mousedown', function (ev) {
        var link = ev.target.closest('.pp-mlink');
        if (!link) { return; }
        var nedHit = nedHoverDispatch(link);
        if (!nedHit) { return; }
        if (nedHit.edit) { nedPrewarmEditForm(nedHit.edit.table, nedHit.edit.id); }
        else if (nedHit.create) { nedPrewarmCreateForm(nedHit.create); }
    });
    root.addEventListener('click', function (ev) {
        // The copy button by an issue key: copies, and neither opens the row
        // under it nor follows anything (ISS00440).
        var ikBtn = ev.target.closest('.pp-ikcopy');
        if (ikBtn) {
            ev.preventDefault();
            ev.stopPropagation();
            ikCopy(ikBtn.getAttribute('data-issue'), !!ev.shiftKey);
            return;
        }
        // Click-to-collapse card head (基本情報 / 子課題). Bail if the click
        // landed on an interactive descendant so the collapse doesn't
        // swallow buttons or links added later.
        var collapseHead = ev.target.closest('.bl-boxhead[data-collapse]');
        if (collapseHead && !ev.target.closest('a, button, input, select, textarea')) {
            var card = collapseHead.closest('.bl-card-box');
            if (card) {
                card.classList.toggle('bl-collapsed');
                // Folded stays folded, on the next issue and the next visit.
                if (card.id) {
                    try { localStorage.setItem('pmportal.fold.' + card.id, card.classList.contains('bl-collapsed') ? '1' : '0'); } catch (e) { /* ignore */ }
                }
                return;
            }
        }
        var mlink = ev.target.closest('.pp-mlink');
        if (mlink) {
            ev.preventDefault();
            var editHit = maybeNedit(mlink.dataset.url);
            if (editHit) { goEdit(editHit.table, editHit.id); return; }
            var createTable = maybeNcreate(mlink.dataset.url);
            if (createTable) {
                if (createTable === D.issue_table) { goCreate(); }
                else { openNcreate(createTable); }
                return;
            }
            formModal(mlink.dataset.url, mlink.dataset.mtitle || '', mlink.dataset.msub || '');
            return;
        }
        var anchor = ev.target.closest('a[href]');
        if (anchor && interceptDataLink(anchor, ev)) { return; }
        // Any click closes an open comment menu; a click on the trigger then
        // toggles its own, which is what makes "click outside to close" work
        // without a second listener watching the whole document.
        var cmenuBtn = ev.target.closest('.pp-cmenu');
        var wanted = cmenuBtn ? cmenuBtn.parentNode.querySelector('.m') : null;
        document.querySelectorAll('#pp-d-timeline .cmenu .m').forEach(function (m) {
            if (m !== wanted) { m.hidden = true; }
        });
        if (cmenuBtn) { wanted.hidden = !wanted.hidden; return; }
        // The issue's own menu (その他) works the same way. The delete entry
        // asks a second time in place, so the first click on it must leave
        // the menu open for the question to be read.
        var moreBtn = ev.target.closest('.pp-morebtn');
        var moreKeep = moreBtn || ev.target.closest('.pp-more .pp-issuedel');
        Array.prototype.forEach.call(root.querySelectorAll('.pp-more'), function (w) {
            var m = w.querySelector('.m');
            var b = w.querySelector('.pp-morebtn');
            if (!m || !b) { return; }
            if (moreBtn === b) { m.hidden = !m.hidden; }
            else if (!(moreKeep && w.contains(moreKeep))) { m.hidden = true; }
            b.setAttribute('aria-expanded', m.hidden ? 'false' : 'true');
        });
        if (moreBtn) { return; }
        var rxAdd = ev.target.closest('.pp-rxadd');
        if (rxAdd) {
            ev.stopPropagation();
            var pick = rxAdd.parentNode.querySelector('.pp-rxpick');
            document.querySelectorAll('.pp-rxpick').forEach(function (p) { if (p !== pick) { p.hidden = true; } });
            if (pick) { pick.hidden = !pick.hidden; }
            return;
        }
        var rxEl = ev.target.closest('.pp-rx, .pp-rxopt');
        if (rxEl) {
            ev.stopPropagation();
            rxToggle(rxEl.closest('.pp-rxbar'), rxEl.getAttribute('data-rx'));
            return;
        }
        if (!ev.target.closest('.pp-rxbar')) {
            document.querySelectorAll('.pp-rxpick').forEach(function (p) { p.hidden = true; });
        }
        var star = ev.target.closest('.pp-star');
        if (star) {
            ev.stopPropagation();
            $.ajax({
                url: D.urls.base + '/star', type: 'POST',
                data: { star_type: star.dataset.starType, target_type: star.dataset.targetType, target_id: star.dataset.targetId },
                headers: { 'X-CSRF-TOKEN': CSRF }
            }).done(function (res) {
                star.classList.toggle('on', res.on);
                star.querySelector('i').className = 'fa ' + (res.on ? 'fa-star' : 'fa-star-o');
                star.title = res.on ? L.star_off_tip : L.star_on_tip;
                if (star.classList.contains('pp-star-d')) {
                    // The detail's button keeps its count even at 0, and says
                    // which way it is.
                    star.querySelector('.ct').textContent = String(res.count || 0);
                    star.querySelector('.lb').textContent = res.on ? L.star_on : L.star_label;
                } else {
                    star.querySelector('.ct').textContent = res.count ? ' ' + res.count : '';
                }
                if (star.dataset.targetType === D.issue_table && star.dataset.starType === 'star') {
                    var issue = issueById(star.dataset.targetId);
                    if (issue) { issue.star = res.count; issue.my_star = res.on; }
                }
                if (star.dataset.targetType === D.wiki_table) {
                    (D.wiki.pages || []).forEach(function (p) {
                        if (String(p.id) === String(star.dataset.targetId)) { p.star = res.count; p.my_star = res.on; }
                    });
                }
            });
            return;
        }
        var watch = ev.target.closest('.pp-watch');
        if (watch) {
            $.ajax({
                url: D.urls.base + '/star', type: 'POST',
                data: { star_type: 'watch', target_type: watch.dataset.targetType, target_id: watch.dataset.targetId },
                headers: { 'X-CSRF-TOKEN': CSRF }
            }).done(function (res) {
                watch.classList.toggle('on', res.on);
                watch.querySelector('.wl').textContent = res.on ? L.unwatch : L.watch;
                watch.querySelector('.wc').textContent = res.count;
            });
            return;
        }
        var chip = ev.target.closest('.pp-notify');
        if (chip) { chip.classList.toggle('off'); ppNotifyCount(); return; }
        var rvBtn = ev.target.closest('.pp-rvbtn');
        if (rvBtn) { rvAnswer(rvBtn); return; }
        var nextBtn = ev.target.closest('.pp-next-status');
        if (nextBtn) { postComment(nextBtn.dataset.next); return; }
        var quote = ev.target.closest('.pp-quote');
        if (quote) {
            // Quoting is the start of writing a reply, so the dock has to be
            // open before the text is put in it - otherwise the quote lands
            // in a box nobody can see.
            ppDockOpen(true);
            var qbox = cmtBox();
            cmtPreviewOff();
            qbox.value = '> ' + String(quote.dataset.text || '').replace(/\n/g, '\n> ') + '\n\n' + qbox.value;
            qbox.focus();
            qbox.selectionStart = qbox.selectionEnd = qbox.value.length;
            if (currentIssue !== null) { dockDraftSave(currentIssue, qbox.value); }
            qeMark();
            return;
        }
        var more = ev.target.closest('.pp-cmore');
        if (more) {
            var moreTxt = more.closest('.body').querySelector('.txt');
            more.textContent = moreTxt.classList.toggle('clip') ? L.cmt_more : L.cmt_less;
            return;
        }
        var cedit = ev.target.closest('.pp-cedit');
        if (cedit) { cmtEditStart(cedit.dataset.cid, cedit.getAttribute('data-text') || ''); return; }
        var ccopy = ev.target.closest('.pp-ccopy');
        if (ccopy) { cmtCopyLink(ccopy.dataset.cid); return; }
        var cdel = ev.target.closest('.pp-cdel');
        if (cdel) { cmtDelete(cdel); return; }
        var addChild = ev.target.closest('.pp-addchild');
        if (addChild) { openNcreate(D.issue_table, issuePrefill('child'), 'child' + currentIssue); return; }
        var linkCopy = ev.target.closest('.pp-linkcopy');
        if (linkCopy) { issueCopyLink(); return; }
        var mailBtn = ev.target.closest('.pp-mailbtn');
        if (mailBtn) { mailOpen(currentIssue); return; }
        var issueCopy = ev.target.closest('.pp-issuecopy');
        if (issueCopy) { openNcreate(D.issue_table, issuePrefill('copy'), 'copy' + currentIssue); return; }
        var issueMove = ev.target.closest('.pp-issuemove');
        if (issueMove) { issueMoveOpen(currentIssue); return; }
        var moveCancel = ev.target.closest('.pp-move-cancel');
        if (moveCancel) { issueMoveClose(); return; }
        var moveDo = ev.target.closest('.pp-move-do');
        if (moveDo) { issueMoveRun(currentIssue, moveDo); return; }
        var issueDel = ev.target.closest('.pp-issuedel');
        if (issueDel) { issueDelete(currentIssue, issueDel); return; }
        var wikiNode = ev.target.closest('[data-wiki]');
        if (wikiNode) {
            ev.preventDefault();
            var wid = wikiNode.getAttribute('data-wiki');
            if (location.hash === '#wiki-' + wid) { renderWiki(wid); }
            else { location.hash = 'wiki-' + wid; }
            return;
        }
        var row = ev.target.closest('tr[data-issue]');
        // The tick column is a control of its own; a click there selects the
        // row rather than leaving the list for it.
        if (row && !ev.target.closest('a[href]') && !ev.target.closest('.chk')) {
            // an open inline editor is clicked into, not navigated away from
            if (ev.target.closest('.pp-ed-in')) { return; }
            var hash = issueHash(row.getAttribute('data-issue'));
            // On an editable cell the click waits a moment: a double-click
            // there edits the cell instead of opening the issue (edCell).
            if (BULK_ON && ev.target.closest('td[data-ed]')) {
                clearTimeout(ED_NAV_TIMER);
                ED_NAV_TIMER = setTimeout(function () { location.hash = hash; }, 280);
                return;
            }
            location.hash = hash;
        }
    });
    document.getElementById('pp-cmt-submit').addEventListener('click', function () { postComment(); });
    document.getElementById('pp-dockbar').addEventListener('click', function () { ppDockOpen(!ppDockIsOpen()); });
    document.getElementById('pp-dockclose').addEventListener('click', function () { ppDockOpen(false); });
    document.getElementById('pp-dockcancel').addEventListener('click', function () {
        // Cancel drops the unsent work rather than only hiding it: leaving
        // changed fields behind a closed bar is how the wrong value gets
        // sent with the next comment. The fields go back to the row too.
        var text = document.getElementById('pp-cmt-text');
        if (text) { text.value = ''; }
        if (currentIssue !== null) { dockDraftClear(currentIssue); }
        qeReset();
        cmtPreviewOff();
        ppDockMsg('', false);
        var det = currentIssue === null ? null : own(DET, String(currentIssue));
        if (det && det.issue) { buildDetail(det); }
        ppDockOpen(false);
    });
    document.getElementById('pp-cmt-tools').addEventListener('click', function (ev) {
        var b = ev.target.closest('button[data-ct]');
        if (b) { ev.preventDefault(); cmtTool(b.getAttribute('data-ct')); }
    });
    document.getElementById('pp-cmt-file').addEventListener('change', function () {
        cmtUpload(this.files);
        this.value = '';
    });
    document.getElementById('pp-tl-expand').addEventListener('click', function () {
        var box = document.getElementById('pp-d-timeline');
        var folded = !!box.querySelector('.txt.clip');
        box.querySelectorAll('.ent .more').forEach(function (row) {
            row.parentNode.querySelector('.txt').classList.toggle('clip', !folded);
            row.querySelector('.pp-cmore').textContent = folded ? L.cmt_less : L.cmt_more;
        });
        this.textContent = folded ? L.tl_collapse : L.tl_expand;
    });
    document.getElementById('pp-tl-filter').addEventListener('click', function (ev) {
        var b = ev.target.closest('button[data-tl]');
        if (!b) { return; }
        TLF = b.getAttribute('data-tl');
        try { localStorage.setItem(TL_KEY, TLF); } catch (e) { /* ignore */ }
        var det = currentIssue === null ? null : own(DET, String(currentIssue));
        if (det) { renderTimeline(det); }
    });
    (function () {
        var box = cmtBox();
        var form = document.getElementById('pp-cmtform');
        box.addEventListener('input', cmtPreviewRefresh);
        box.addEventListener('input', qeMark);
        box.addEventListener('input', function () {
            box._ppCmtOwner = null;
            // Kept per issue for the life of the tab: a post that fails after
            // its writer has left the issue has nowhere else to hand it back.
            if (currentIssue !== null) { dockDraftSave(currentIssue, box.value); }
        });
        // Ctrl+Enter posts, the way every comment box does.
        box.addEventListener('keydown', function (ev) {
            if ((ev.ctrlKey || ev.metaKey) && ev.key === 'Enter') { ev.preventDefault(); postComment(); }
        });
        // A screenshot on the clipboard arrives as a file, not as text -
        // caught here before the browser pastes its name in as characters.
        box.addEventListener('paste', function (ev) {
            var dt = ev.clipboardData;
            if (!dt) { return; }
            var files = [];
            Array.prototype.forEach.call(dt.items || [], function (item) {
                if (item.kind !== 'file') { return; }
                var f = item.getAsFile();
                if (f) { files.push(f); }
            });
            if (!files.length) { return; }
            ev.preventDefault();
            cmtUpload(files);
        });
        ['dragover', 'dragenter'].forEach(function (name) {
            form.addEventListener(name, function (ev) { ev.preventDefault(); form.classList.add('drag'); });
        });
        ['dragleave', 'dragend'].forEach(function (name) {
            form.addEventListener(name, function () { form.classList.remove('drag'); });
        });
        form.addEventListener('drop', function (ev) {
            ev.preventDefault();
            form.classList.remove('drag');
            cmtUpload(ev.dataTransfer && ev.dataTransfer.files);
        });
    })();
    document.getElementById('pp-sttabs').addEventListener('click', function (ev) {
        var b = ev.target.closest('.bl-sttab');
        if (b) { FILTER.st = b.getAttribute('data-st'); afterFilterChange(); }
    });
    document.getElementById('pp-qchips').addEventListener('click', function (ev) {
        var b = ev.target.closest('.bl-qchip');
        if (!b) { return; }
        // Clicking the chip that is already on turns it off - a chip row with
        // no way back to "everything" is a trap.
        var key = b.getAttribute('data-q');
        FILTER.q = (FILTER.q === key) ? '' : key;
        afterFilterChange();
    });
    [['pp-f-kw', 'kw', 'input'], ['pp-f-as', 'as', 'change'], ['pp-f-ty', 'ty', 'change'],
     ['pp-f-pr', 'pr', 'change'], ['pp-f-ms', 'ms', 'change'], ['pp-f-cat', 'cat', 'change'], ['pp-f-rd', 'rd', 'change'],
     ['pp-f-rl', 'rl', 'change'], ['pp-f-df', 'df', 'change']].forEach(function (def) {
        document.getElementById(def[0]).addEventListener(def[2], function () {
            FILTER[def[1]] = this.value;
            afterFilterChange();
        });
    });
    // The optional columns: the menu opens under its button, a tick shows or
    // hides one column and is remembered for the next visit.
    (function () {
        var btn = document.getElementById('pp-oc-btn');
        var menu = document.getElementById('pp-oc-menu');
        if (!btn || !menu) { return; }
        btn.addEventListener('click', function (ev) {
            ev.stopPropagation();
            menu.hidden = !menu.hidden;
            btn.setAttribute('aria-expanded', menu.hidden ? 'false' : 'true');
        });
        menu.addEventListener('click', function (ev) { ev.stopPropagation(); });
        menu.addEventListener('change', function (ev) {
            var k = ev.target && ev.target.getAttribute('data-ocol');
            if (!k) { return; }
            if (ev.target.checked) { OCOL_ON[k] = true; } else { delete OCOL_ON[k]; }
            ocolSave();
            renderIssues();
        });
        document.addEventListener('click', function onPpOc() {
            if (!alive()) { document.removeEventListener('click', onPpOc); return; }
            if (!menu.hidden) { menu.hidden = true; btn.setAttribute('aria-expanded', 'false'); }
        });
    })();
    // Clicking the column already in charge turns it around; clicking any
    // other one takes over, ascending, which is the direction a reader means
    // by "sort by this".
    function applySort(by) {
        if (!own(SORT_COLS, by)) { return; }
        if (SORT.by === by) {
            SORT.dir = SORT.dir === 'asc' ? 'desc' : 'asc';
        } else {
            SORT.by = by;
            SORT.dir = 'asc';
        }
        afterFilterChange();
    }
    var issueHead = document.getElementById('pp-issue-head');
    if (issueHead) {
        issueHead.addEventListener('click', function (ev) {
            var th = ev.target.closest('th[data-sort]');
            if (th) { applySort(th.getAttribute('data-sort')); }
        });
        issueHead.addEventListener('keydown', function (ev) {
            if (ev.key !== 'Enter' && ev.key !== ' ') { return; }
            var th = ev.target.closest('th[data-sort]');
            if (th) { ev.preventDefault(); applySort(th.getAttribute('data-sort')); }
        });
    }
    document.getElementById('pp-f-clear').addEventListener('click', function () {
        FILTER_FIELDS.forEach(function (k) { FILTER[k] = ''; });
        SORT.by = SORT_DEF.by;
        SORT.dir = SORT_DEF.dir;
        // The saved default goes with it. Leaving it behind would put the
        // whole filter back on the next visit, which is the surprise the
        // button exists to end. Named conditions are not touched: those were
        // written down on purpose.
        try { localStorage.removeItem(FILTER_KEY); localStorage.removeItem(SORT_KEY); } catch (e) { /* ignore */ }
        afterFilterChange();
        toast(L.f_cleared);
    });
    // The old single slot, now a switch inside the saved-conditions menu:
    // "open this list like this next time".
    document.getElementById('pp-f-save').addEventListener('change', function () {
        try {
            if (this.checked) {
                localStorage.setItem(FILTER_KEY, JSON.stringify(FILTER));
                localStorage.setItem(SORT_KEY, JSON.stringify(SORT));
                toast(L.f_saved);
            } else {
                localStorage.removeItem(FILTER_KEY);
                localStorage.removeItem(SORT_KEY);
            }
        } catch (e) { /* ignore */ }
    });
    document.getElementById('pp-sf-btn').addEventListener('click', function (ev) {
        ev.stopPropagation();
        sfMenu(document.getElementById('pp-sf-menu').hidden);
    });
    document.getElementById('pp-sf-menu').addEventListener('click', function (ev) {
        ev.stopPropagation();
        var del = ev.target.closest('[data-sf-del]');
        if (del) { savedDelete(parseInt(del.getAttribute('data-sf-del'), 10)); return; }
        var item = ev.target.closest('[data-sf]');
        if (item) { savedApply(parseInt(item.getAttribute('data-sf'), 10)); }
    });
    document.getElementById('pp-sf-menu').addEventListener('keydown', function (ev) {
        if (ev.key !== 'Enter') { return; }
        if (ev.target.id === 'pp-sf-name') { ev.preventDefault(); savedAdd(); return; }
        var item = ev.target.closest('[data-sf]');
        if (item) { ev.preventDefault(); savedApply(parseInt(item.getAttribute('data-sf'), 10)); }
    });
    document.getElementById('pp-sf-save').addEventListener('click', savedAdd);
    document.addEventListener('click', function onSfOutside() {
        if (!alive()) { document.removeEventListener('click', onSfOutside); return; }
        sfMenu(false);
    });

