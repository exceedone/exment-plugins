    /* ------------------------------------------------ issue detail ------ */
    var currentIssue = null;
    // Which screen is on. The pinned composer belongs to the issue screen
    // alone, so everything that shows or hides it asks this first.
    var currentScreen = null;
    var detailEpoch = 0;
    var cmtBusy = false;
    var cmtPending = {};
    var cmtSequence = 0;
    function detailCurrent(id, epoch) {
        return alive() && currentScreen === 'issue' && String(currentIssue) === String(id)
            && (epoch === undefined || epoch === detailEpoch);
    }
    function cmtSetBusy(on) {
        cmtBusy = on;
        var btn = document.getElementById('pp-cmt-submit');
        if (btn) { btn.disabled = on; btn.textContent = on ? L.posting : L.submit; }
        ['pp-cmt-fields', 'pp-cmt-status', 'pp-notify-chips', 'pp-dockcancel'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) { el.inert = on; }
        });
        root.querySelectorAll('.pp-next-status').forEach(function (b) { b.disabled = on; });
    }
    // The page carries the details of its issues, so opening one costs no
    // request at all - which is the only thing that helps here: a request
    // spends ~400ms booting the framework and ~50ms doing the work.
    var DET = {};
    // The number the next request that writes DET takes; see detPut.
    var DET_SEQ = 0;
    // Same work on a refresh, so it lives in one function now.
    seedDetails();
    function skel(count) {
        var html = '';
        for (var i = 0; i < count; i++) { html += '<div class="pp-skel' + (i % 3 === 1 ? ' w2' : (i % 3 === 2 ? ' w3' : '')) + '"></div>'; }
        return html;
    }
    // Everything the list already knows renders instantly; only the
    // description and the timeline wait for the fetch (usually already
    // prefetched, so there is nothing to wait for at all).
    function localDetail(issue) {
        return {
            issue: issue,
            description: null,
            editable: true,
            watch: { count: null, on: false },
            timeline: null
        };
    }
    // A small repeat mark after the subject of a recurring issue.
    function rcMark(i) {
        if (!i.rc) { return ''; }
        return '<i class="fa fa-repeat pp-rc" title="' + esc(L.col_rc + ': ' + (L['rc_' + i.rc] || i.rc)) + '"></i> ';
    }
    function renderDetail(id) {
        var epoch = ++detailEpoch;
        cmtSetBusy(false);
        ppDockMsg('', false);
        document.getElementById('pp-cmtform').classList.remove('busy');
        if (String(currentIssue) !== String(id)) {
            // Each issue has its own half-written comment, kept for the life
            // of the tab: the box holds this issue's, not the last one's.
            document.getElementById('pp-cmt-text').value = dockDraftRead(id);
            cmtPreviewOff();
        }
        currentIssue = id;
        markRead(id, true);
        cmtSetBusy(!!cmtPending[id]);
        var issue = issueById(id);
        // ISS00257: no prewarm of issue.edit_url here - issue edits open in
        // the native NED form, so that hidden admin-form iframe was never used.
        if (DET[id]) {
            buildDetail(DET[id]);
            // a partial copy (the bulk query hit its ceiling) is shown at once
            // and then completed from the single-issue endpoint
            if (!DET[id].partial) { return; }
            var seq = ++DET_SEQ;
            ppQueue(function () {
                return $.getJSON(D.urls.base + '/issue/' + id);
            }).then(function (res) {
                if (!alive() || !res || !res.ok) { return; }
                $.extend(D.users, res.users || {});
                if (!detPut(id, res, seq)) { return; }
                if (detailCurrent(id, epoch) && safeToRefresh(true)) { buildDetail(res); }
            }).catch(function () { });
            return;
        }
        if (issue) {
            buildDetail(localDetail(issue));   // instant from what the list knows
        } else {
            document.getElementById('pp-d-main').innerHTML = '<i class="fa fa-spinner fa-spin"></i>';
            document.getElementById('pp-d-timeline').innerHTML = '';
        }
        fetchDetails(id, false, epoch);
    }
    // Only a project bigger than the embed limit ever gets here. One request
    // then brings the missing issue AND its neighbours in the list, so the
    // clicks around it are free too.
    function fetchDetails(id, low, epoch) {
        if (epoch === undefined) { epoch = detailEpoch; }
        var initialPaint = !issueById(id) && !DET[id];
        var want = [id];
        (D.issues || []).forEach(function (row) {
            if (want.length < 30 && !own(DET, String(row.id)) && String(row.id) !== String(id)) { want.push(row.id); }
        });
        var seq = ++DET_SEQ;
        ppQueue(function () {
            return $.getJSON(D.urls.base + '/issue_details', { ids: want.join(',') });
        }, low).then(function (res) {
            if (!alive() || !res || !res.ok) { return; }
            $.extend(D.users, res.users || {});
            var wrote = {};
            Object.keys(res.details || {}).forEach(function (key) {
                var det = res.details[key];
                // The server's row is the newer one; what only the list
                // carries (stars, watches) is kept from the list's copy.
                var local = issueById(key);
                det.issue = det.issue ? $.extend({}, local || {}, det.issue) : local;
                if (!det.issue || !detPut(key, det, seq)) { return; }
                wrote[key] = true;
                (D.issues || []).forEach(function (row, n) {
                    if (String(row.id) === String(key)) { D.issues[n] = det.issue; }
                });
            });
            if (detailCurrent(currentIssue, epoch) && currentIssue !== null && own(wrote, String(currentIssue))
                && (initialPaint || safeToRefresh(true))) {
                buildDetail(DET[currentIssue]);
            } else if (detailCurrent(id, epoch) && !issueById(id) && !DET[id]) {
                toast(L.post_fail, true);       // asked for an issue that is not here
                location.hash = 'issues';
            }
        }, function (xhr) {
            if (detailCurrent(id, epoch) && !DET[id]) {
                document.getElementById('pp-d-main').innerHTML = '<span style="color:#c0392b;">' + esc(xhrErr(xhr, L.post_fail)) + '</span>';
            }
        });
    }
    // Whatever the page could not embed is topped up once in the background.
    function prefetchDetails() {
        if (!(D.features && D.features.prefetch)) { return; }
        var missing = (D.issues || []).filter(function (row) { return !DET[row.id]; });
        if (!missing.length) { return; }
        fetchDetails(missing[0].id, true);
    }
    function buildDetail(res) {
        var i = res.issue;
        var local = issueById(i.id) || i;
        // show() names the screen from what the list already knows; once the
        // detail lands it can name the ticket even if the list never had it.
        if (String(currentIssue) === String(i.id)) { setTitle(i.key + ' ' + i.subject); }
        var next = null, seen = false;
        (D.statuses || []).forEach(function (s) {
            if (seen && !next) { next = s; }
            if (s.k === i.st) { seen = true; }
        });
        // The way back sits on the head row itself, so it costs no line of
        // its own above the issue (the same button closes the page too).
        var head = '<div class="bl-dhead"><a class="bl-dback bl-dback-s" href="#issues"><i class="fa fa-angle-left"></i> ' + esc(L.back) + '</a>' + tag(i.ty) +
            '<span class="bl-key" style="font-size:14px;">' + esc(i.key) + '</span>' + ikCopyButton(i) +
            issueNav(i) +
            '<span style="margin-left:auto;display:flex;gap:8px;">' +
            (res.editable && next ? '<button class="bl-btn-main pp-next-status" data-next="' + esc(next.k) + '" style="padding:5px 12px;font-size:12px;">' +
                '<i class="fa fa-arrow-right"></i> ' + fmt(esc(L.to_next), esc(next.l)) + '</button>' : '') +
            // What is used every day stays a button; what is used now and
            // then waits in one menu, so the row is one line and the
            // delete button is not a neighbour of the edit button.
            // With no children yet the 子課題 box stays away; its one button
            // waits up here instead of costing a box of its own.
            (res.editable && CAN_ADD_ISSUE && childRoom(i) && !(D.issues || []).some(function (x) { return String(x.parent) === String(i.id); })
                ? '<a class="bl-btn-sub pp-addchild"><i class="fa fa-sitemap"></i> ' + esc(L.child_add) + '</a>' : '') +
            starDetailBtn(i.id, local.star, local.my_star) +
            '<a class="bl-btn-sub pp-watch' + (res.watch.on ? ' on' : '') + '" data-target-type="' + esc(D.issue_table) + '" data-target-id="' + esc(i.id) + '">' +
                '<i class="fa fa-eye"></i> <span class="wl">' + esc(res.watch.on ? L.unwatch : L.watch) + '</span> <span class="wc">' + (res.watch.count === null ? '…' : esc(res.watch.count)) + '</span></a>' +
            '<a class="bl-btn-sub pp-mlink" data-url="' + esc(i.edit_url) + '" data-mtitle="' + esc(L.m_edit_issue) + '" data-msub="' + esc(i.key) + '"><i class="fa fa-pencil"></i> ' + esc(L.edit) + '</a>' +
            '<span class="pp-more"><a class="bl-btn-sub pp-morebtn" href="javascript:void(0);" role="button" aria-haspopup="true" aria-expanded="false">' +
                '<i class="fa fa-ellipsis-h"></i> ' + esc(L.issue_more) + '</a><span class="m" hidden>' +
            (res.description ? '<a class="pp-quote pp-dquote" href="javascript:void(0);"><i class="fa fa-quote-right"></i> ' + esc(L.desc_quote) + '</a>' : '') +
            (CAN_ADD_ISSUE ? '<a class="pp-issuecopy" href="javascript:void(0);"><i class="fa fa-clone"></i> ' + esc(L.issue_copy) + '</a>' : '') +
            '<a class="pp-linkcopy" href="javascript:void(0);"><i class="fa fa-link"></i> ' + esc(L.link_copy) + '</a>' +
            '<a class="pp-mailbtn" href="javascript:void(0);"><i class="fa fa-envelope-o"></i> ' + esc(L.mail_btn) + '</a>' +
            (res.movable ? '<a class="pp-issuemove" href="javascript:void(0);"><i class="fa fa-exchange"></i> ' + esc(L.issue_move) + '</a>' : '') +
            (res.deletable ? '<a class="pp-issuedel" href="javascript:void(0);"><i class="fa fa-trash-o"></i> <span class="lb">' + esc(L.issue_delete) + '</span></a>' : '') +
            '</span></span></span></div>' +
            '<div class="pp-move" hidden></div>' +
            '<div class="bl-dtitle">' + esc(i.subject) + '</div>' +
            (i.sub ? '<div class="pp-dsubsub">' + esc(i.sub) + '</div>' : '') +
            '<div class="bl-dsum">' +
            '<span class="it"><span class="k">' + esc(L.col_st) + '</span>' + pill(i.st) + '</span>' +
            '<span class="it"><span class="k">' + esc(L.col_pr) + '</span>' + (prio(i.pr) || '—') + '</span>' +
            // No user here for the source's assignee (schema 29): its name, greyed.
            '<span class="it"><span class="k">' + esc(L.col_as) + '</span>' + ((i.as || []).map(function (u) { return avname(u); }).join('') ||
                (res.source && res.source.asg ? '<span style="color:var(--bl-faint);" title="' + esc(L.src_assignees_hint) + '">' + esc(res.source.asg) + '</span>' : '—')) + '</span>' +
            '<span class="it"><span class="k">' + esc(L.col_dd) + '</span>' + dueCell(i) + '</span>' +
            '<span class="it pp-reads"><span class="k"><i class="fa fa-check"></i> ' + esc(L.reads) + '</span>' + readsHtml(res.reads) + '</span>' +
            '<a class="pp-tocmt" href="javascript:void(0);"><i class="fa fa-comments-o"></i> ' + esc(L.to_comments) + '<span class="n"></span></a></div>' +
            '<div class="bl-desc bl-md">' + (res.description === null ? skel(3)
                : (renderRichText(res.description) || '<p style="color:var(--bl-faint);">' + esc(L.empty) + '</p>')) + '</div>' +
            '<div class="pp-rxrow">' + rxBar(D.issue_table, i.id, res.reactions) + '</div>';
        document.getElementById('pp-d-main').innerHTML = head;
        ppDbarFill(i);
        linkifyIssueRefs(document.querySelector('#pp-d-main .bl-desc'));
        // The quote button carries the description as words, not markup, and
        // the rendered block is the shortest way to those words.
        var descEl = document.querySelector('#pp-d-main .bl-desc');
        var dquote = document.querySelector('#pp-d-main .pp-dquote');
        if (dquote && descEl) { dquote.dataset.text = String(descEl.innerText || '').trim(); }

        var parentIssue = i.parent ? issueById(i.parent) : null;
        var ms = own(D.milestones, i.ms);
        var props = [
            [L.col_st, pill(i.st)], [L.col_pr, prio(i.pr)],
            [L.col_as, (i.as || []).map(function (u) { return avname(u); }).join('<br>') || '—'],
            // Schema 27 (ISS00252): the other people on the issue.
            [L.lbl_sub_assignees, (i.sa || []).map(function (u) { return avname(u); }).join('<br>')],
            [L.lbl_managers, (i.mg || []).map(function (u) { return avname(u); }).join('<br>')],
            [L.lbl_reviewers, (i.rv || []).map(function (u) { return avname(u); }).join('<br>')],
            [L.lbl_difficulty, i.df ? esc(L['df_' + i.df] || i.df) : ''],
            // An import's author is the source's; Exment's is the importer.
            [L.lbl_author, res.source && res.source.cby ? esc(res.source.cby) : (i.by ? avname(i.by) : '')],
            [L.col_ms, ms ? '<i class="fa fa-flag-checkered" style="color:#8e44ad;margin-right:5px;"></i>' + esc(ms.l) : '—'],
            [L.col_cat, (i.cat || []).map(function (c) { return esc(own(D.categories, c) || ''); }).join(', ') || '—'],
            [L.col_sd, esc(d2(i.sd))], [L.col_dd, dueCell(i)],
            [L.col_rc, i.rc ? '<i class="fa fa-repeat" style="color:var(--bl-accent-d);margin-right:5px;"></i>' + esc(L['rc_' + i.rc] || i.rc) : '—'],
            [L.lbl_eh, esc(hours(i.eh) + ' / ' + hours(i.ah))],
            [L.lbl_pg, pbar(i.pg) + rollupBadge(rollupMap(), i.id)],
            [L.lbl_parent, parentIssue ? '<a class="bl-issuelink" href="#' + esc(issueHash(parentIssue.id)) + '">' + esc(parentIssue.key) + ' ' + esc(parentIssue.subject) + '</a>' : '—'],
            [L.lbl_source, sourceHtml(res.source)],
            [L.lbl_custom_fields, res.source && res.source.cf ? res.source.cf.split(/\r?\n/).filter(function (l) { return l.trim() !== ''; }).map(esc).join('<br>') : ''],
            [L.lbl_watch, '<i class="fa fa-eye" style="color:var(--bl-faint);"></i> ' + (res.watch.count === null ? '…' : esc(res.watch.count + L.people))]
        ];
        // Status, priority, assignees and the due date are on the summary row
        // above the description, and the watch count on its button: said
        // once is enough. Of the rest, only what is set - a column of dashes
        // only pushed the comments further down. Nothing set, no box.
        var shown = [L.col_st, L.col_pr, L.col_as, L.col_dd, L.lbl_watch];
        var setProps = props.filter(function (p) {
            if (shown.indexOf(p[0]) >= 0) { return false; }
            var v = String(p[1] === null || p[1] === undefined ? '' : p[1]);
            if (p[0] === L.lbl_eh) { return i.eh !== null && i.eh !== undefined && i.eh !== '' || i.ah !== null && i.ah !== undefined && i.ah !== ''; }
            if (p[0] === L.lbl_pg) { return Number(i.pg || 0) > 0 || p[1].indexOf('pp-rollup') >= 0; }
            return v !== '' && v !== '—' && v !== esc('—');
        });
        document.getElementById('pp-d-props').innerHTML = setProps.map(function (p) {
            return '<div class="row"><span class="k">' + esc(p[0]) + '</span><span class="v">' + p[1] + '</span></div>';
        }).join('');
        var propBox = document.getElementById('pp-d-propbox');
        // Left alone while the inline editor has stood it aside: the editor
        // brings its own fields, and puts this box back as it was on close.
        var mainBox = document.getElementById('pp-d-main').closest('.bl-card-box');
        var editingNow = mainBox && mainBox.style.display === 'none';
        if (propBox && !editingNow) { propBox.style.display = setProps.length ? '' : 'none'; }

        var kids = (D.issues || []).filter(function (x) { return String(x.parent) === String(i.id); });
        // A child is a new issue: editing this one is not enough on its own.
        var canAddChild = res.editable && CAN_ADD_ISSUE && childRoom(i);
        // No children, no box: the add button moved up to the issue's head.
        document.getElementById('pp-d-childbox').style.display = kids.length ? '' : 'none';
        document.getElementById('pp-d-children').innerHTML = kids.map(function (k) {
            return '<div class="row"><div class="k"><span class="bl-key">' + esc(k.key) + '</span></div>' +
                '<div class="v"><a class="bl-issuelink" href="#issue-' + esc(k.id) + '">' + esc(k.subject) + '</a>'
                + pill(k.st) + av((k.as || [])[0]) + '</div></div>';
        }).join('') + (canAddChild
            ? '<div class="row"><div class="k"></div><div class="v">'
                + '<a href="javascript:void(0);" class="pp-addchild" style="color:var(--bl-accent-d);font-weight:600;">'
                + '<i class="fa fa-plus"></i> ' + esc(L.child_add) + '</a></div></div>'
            : '');
        // Backlog's childIssueSummary, from what the list already knows.
        var kidsAr = arIn('kids', i.id);
        var kidsDone = kids.filter(function (k) { return isDone(k.st); }).length + kidsAr;
        document.getElementById('pp-d-childsum').textContent =
            (kids.length + kidsAr) ? fmt(L.children_done, kidsDone, kids.length + kidsAr) : '';

        // What the reader had picked in the dock for this same issue outlives
        // the redraw - the status to move it to and who to tell - the way the
        // fields do (see renderQuickEdit). A refused save repaints the issue
        // with what changed, and the choices made against it are theirs.
        var same = QE.issue !== null && String(QE.issue) === String(i.id);
        var keepSt = '';
        var keepNotify = null;
        if (same) {
            var stWas = document.getElementById('pp-cmt-status');
            keepSt = stWas ? stWas.value : '';
            keepNotify = Array.prototype.map.call(
                document.querySelectorAll('#pp-notify-chips .pp-notify:not(.off)'),
                function (c) { return String(c.getAttribute('data-user')); });
        }

        renderRelated(i, res);
        renderIssueFiles(res);
        renderTimeline(res);
        renderQuickEdit(i, res);

        var sel = document.getElementById('pp-cmt-status');
        var stNow = own(ST, i.st);
        sel.innerHTML = '<option value="">' + esc(fmt(L.st_keep, stNow ? stNow.l : i.st)) + '</option>' +
            (D.statuses || []).filter(function (s) { return s.k !== i.st; }).map(function (s) {
                return '<option value="' + esc(s.k) + '">→ ' + esc(s.l) + '</option>';
            }).join('');
        sel.disabled = !res.editable;
        if (keepSt && res.editable) {
            // Only a status still on offer: one the issue has meanwhile
            // reached is no longer a change.
            Array.prototype.forEach.call(sel.options, function (o) { if (o.value === keepSt) { sel.value = keepSt; } });
        }

        var p = D.project;
        var people = [p.manager].concat(p.members || [], p.viewers || []).filter(function (v, x, a) {
            return v && a.indexOf(v) === x && String(v) !== String(D.me);
        });
        document.getElementById('pp-notify-chips').innerHTML = people.map(function (u) {
            var on = keepNotify && keepNotify.indexOf(String(u)) >= 0;
            return '<span class="bl-chip has-av' + (on ? '' : ' off') + ' pp-notify" role="button" tabindex="0" data-user="' + esc(u) + '">' + av(u) + esc(user(u).n) + '</span>';
        }).join('');
        ppNotifyCount();
        var dockav = document.getElementById('pp-dockav');
        if (dockav) { dockav.innerHTML = av(D.me); }
        cmtSetBusy(cmtBusy);
        ppDockFit();
    }
    /* ------------------------------------------------ quick edit -------- */
    // Backlog changes an issue from the same form the comment is written in:
    // the fields that move day to day sit under the comment box, and one
    // button sends both. Only what the reader may edit is offered, and only
    // what they actually changed is sent.
    //
    // The widgets are built from the masters the payload already carries, so
    // opening the dock costs no request. What it collects is diffed against
    // QE.base, which is refreshed from every answer the server sends back.
    //
    // Only a widget the reader touched can be sent (QE.touched). The others
    // were drawn from the row, and a value the dock has no option for - an
    // assignee who is not a member, a milestone deleted since - used to read
    // back as missing, so a plain comment took it off the issue.
    //
    // issue / st / ua: which issue the dock was drawn for, the status it
    // showed and the version it was drawn from - what a save quotes back so
    // the server can refuse it when somebody else got there first.
    var QE = { base: {}, editable: false, touched: {}, issue: null, st: '', ua: null };
    // The dock's fields by their short names, and the def-map role each one
    // is stored under.
    var QE_COLS = {
        pr: 'priority', as: 'assignees', ms: 'milestone', cat: 'category',
        sd: 'start', dd: 'due', dt: 'due_time', eh: 'estimate', ah: 'actual', pg: 'progress'
    };
    // Column names live in the def map, not in this file: an install that
    // renamed one overrides the map instead of patching code.
    function qeCol(key) { return own(D.issue_cols, key) || ''; }
    // One field of the dock: its label over its widget. `size` names how
    // much room the field is worth (see .bl-qe in the stylesheet) - a date is
    // ten characters wherever it is shown, and a field that stretches to the
    // width of the card says nothing about what goes in it. `unit` is the
    // quiet suffix after the label ("h", "%").
    function qeRow(label, node, size, unit) {
        var multi = !!(node.dataset && node.dataset.qeMulti);
        var row = document.createElement('div');
        row.className = 'row' + (size ? ' ' + size : '');
        var k = document.createElement('span');
        k.className = 'k';
        k.textContent = label;
        if (unit) {
            var u = document.createElement('span');
            u.className = 'u';
            u.textContent = unit;
            k.appendChild(u);
        }
        if (multi) {
            // How many are switched on, so a long list says so at a glance.
            var n = document.createElement('span');
            n.className = 'bl-qe-n';
            n.hidden = true;
            k.appendChild(n);
        }
        var v = document.createElement('span');
        v.className = 'v';
        v.appendChild(node);
        row.appendChild(k);
        row.appendChild(v);
        if (multi) { qeChipsCount(node); }

        return row;
    }
    function qeChipsCount(box) {
        var row = box.closest ? box.closest('.row') : null;
        var n = row ? row.querySelector('.k .bl-qe-n') : null;
        if (!n) { return; }
        var c = box.querySelectorAll('.bl-chip:not(.off)').length;
        n.textContent = c ? String(c) : '';
        n.hidden = !c;
    }
    function ppNotifyCount() {
        var n = document.getElementById('pp-notify-n');
        if (!n) { return; }
        var c = document.querySelectorAll('#pp-notify-chips .pp-notify:not(.off)').length;
        n.textContent = c ? String(c) : '';
        n.hidden = !c;
    }
    function qeSelect(name, options, value, blank) {
        var sel = document.createElement('select');
        sel.className = 'bl-qe-in';
        sel.dataset.qe = name;
        sel.add(new Option(blank, ''));
        options.forEach(function (o) { sel.add(new Option(o[1], o[0])); });
        qeSelectEnsure(sel, value);
        sel.value = value == null ? '' : String(value);

        return sel;
    }
    // A value the list does not offer - an option deleted since, a word from
    // before the upgrade - is still the field's value. It gets an entry of its
    // own; without one the select read back as empty, and the next comment
    // quietly blanked the field.
    function qeSelectEnsure(sel, value) {
        var v = value == null ? '' : String(value);
        if (v === '') { return; }
        var listed = Array.prototype.some.call(sel.options, function (o) { return o.value === v; });
        if (listed) { return; }
        var opt = new Option(qeUnlisted(v), v);
        opt.className = 'pp-qe-unlisted';
        sel.add(opt);
    }
    // How a value with no label of its own is written in the dock.
    function qeUnlisted(v) {
        return String(L.qe_unlisted || ':value').replace(':value', String(v));
    }
    function qeInput(name, type, value, extra) {
        var input = document.createElement('input');
        // A date, datetime or time field is not the browser's own widget -
        // see _datepicker.blade.php for why. Anything else is a plain input.
        var dated = type === 'date' || type === 'datetime' || type === 'time';
        input.type = dated ? 'text' : type;
        input.className = 'bl-qe-in' + (type === 'number' ? ' bl-qe-num' : '');
        input.dataset.qe = name;
        input.value = value == null ? '' : String(value);
        Object.keys(extra || {}).forEach(function (k) { input.setAttribute(k, extra[k]); });
        if (dated && window.ppDateField) { window.ppDateField(input, type); }

        return input;
    }
    // Multi-value fields are chips rather than a multi-select: a <select
    // multiple> hides everything not currently scrolled to, and these lists
    // are short enough to show whole.
    //
    // labelOf names a value the options do not carry, for the chip it gets.
    // people: the values are user ids, and each chip carries that face.
    function qeChips(name, options, values, labelOf, people) {
        var box = document.createElement('div');
        box.className = 'bl-qe-chips';
        box.dataset.qe = name;
        box.dataset.qeMulti = '1';
        box._qeLabel = labelOf || qeUnlisted;
        box._qePeople = !!people;
        options.forEach(function (o) { qeChip(box, String(o[0]), o[1], false); });
        if (!options.length) {
            var em = document.createElement('span');
            em.className = 'em';
            em.style.color = 'var(--bl-faint)';
            em.textContent = L.empty;
            box.appendChild(em);
        }
        qeChipsSet(box, values);

        return box;
    }
    function qeChip(box, val, label, extra) {
        var chip = document.createElement('span');
        chip.className = 'bl-chip off' + (extra ? ' pp-qe-extra' : '');
        chip.dataset.val = val;
        chip.textContent = label;
        chip.setAttribute('role', 'button');
        chip.tabIndex = 0;
        if (box._qePeople) {
            chip.classList.add('has-av');
            chip.insertAdjacentHTML('afterbegin', av(val));
        }
        if (extra && L.qe_extra) { chip.title = L.qe_extra; }
        chip.addEventListener('click', function () {
            chip.classList.toggle('off');
            QE.touched[box.dataset.qe] = true;
            qeChipsCount(box);
            qeMark();
        });
        box.appendChild(chip);

        return chip;
    }
    // Turn on exactly these values. One the options do not offer - an
    // assignee who is not a member of the project, a category deleted since -
    // gets a chip of its own, switched on, rather than being dropped from
    // the list the next save sends.
    function qeChipsSet(box, values) {
        var want = (values || []).map(String);
        var have = {};
        Array.prototype.forEach.call(box.querySelectorAll('.bl-chip'), function (chip) {
            have[chip.dataset.val] = true;
            chip.classList.toggle('off', want.indexOf(chip.dataset.val) < 0);
        });
        want.forEach(function (v) {
            if (own(have, v)) { return; }
            have[v] = true;
            qeChip(box, v, box._qeLabel ? box._qeLabel(v) : v, true).classList.remove('off');
        });
        var em = box.querySelector('.em');
        if (em) { em.hidden = !!box.querySelector('.bl-chip'); }
        qeChipsCount(box);
    }
    function renderQuickEdit(i, res) {
        var box = document.getElementById('pp-cmt-fields');
        var editbox = document.getElementById('pp-d-editbox');
        if (!box) { return; }
        // The same issue drawn again - a refresh, a save refused because
        // somebody else got there first - keeps what the reader moved and has
        // not sent. Another issue starts clean.
        var same = QE.issue !== null && QE.issue !== undefined && String(QE.issue) === String(i.id);
        var carry = same ? qeCarry() : null;
        QE.touched = {};
        QE.issue = i.id;
        QE.st = i.st == null ? '' : String(i.st);
        QE.ua = i.ua || null;
        QE.editable = !!res.editable;
        QE.base = {
            pr: i.pr == null ? '' : String(i.pr),
            as: (i.as || []).map(String),
            ms: i.ms == null ? '' : String(i.ms),
            cat: (i.cat || []).map(String),
            sd: i.sd || '', dd: i.dd || '', dt: i.dt || '',
            eh: i.eh == null ? '' : String(i.eh),
            ah: i.ah == null ? '' : String(i.ah),
            pg: i.pg == null ? '' : String(i.pg)
        };
        // The two date rows carry a picker each, and the calendar they open
        // lives off <body> - so the picker has to be told before its input
        // disappears, or it is left holding a detached field.
        if (window.ppDateFieldDestroy) { window.ppDateFieldDestroy(box); }
        box.innerHTML = '';
        if (!box._qeBound) {
            // Typed into or picked is what makes a field the reader's; a
            // field nobody touched is never part of a save.
            var touch = function (ev) {
                var w = ev.target && ev.target.closest ? ev.target.closest('[data-qe]') : null;
                if (w && w.dataset.qe) { QE.touched[w.dataset.qe] = true; }
                qeMark();
            };
            box.addEventListener('change', touch);
            box.addEventListener('input', touch);
            box._qeBound = true;
        }
        // The status row is built here so it sits with the other fields, but
        // the select keeps its id: the comment endpoint - not the row API -
        // is what changes a status, because that is what notifies people.
        var st = document.createElement('select');
        st.id = 'pp-cmt-status';
        st.className = 'bl-qe-in';
        box.appendChild(qeRow(L.st_change, st, 'w-st'));
        if (!res.editable) {
            if (editbox) { editbox.hidden = true; }

            return;
        }
        if (editbox) { editbox.hidden = false; }

        var people = [D.project.manager].concat(D.project.members || []).filter(function (v, x, a) {
            return v && a.indexOf(v) === x;
        }).map(function (u) { return [String(u), user(u).n]; });
        var prios = metaKeys(D.priorities).map(function (k) { return [k, D.priorities[k].l]; });
        var miles = Object.keys(D.milestones || {}).map(function (k) { return [k, D.milestones[k].l]; })
            .sort(function (a, b) { return cmpText(a[1], b[1]); });
        var cats = Object.keys(D.categories || {}).map(function (k) { return [k, String(D.categories[k])]; })
            .sort(function (a, b) { return cmpText(a[1], b[1]); });

        // Laid out by what each field holds: the three pickers share a line,
        // the two chip lists take a line each, and the dates and the hours -
        // short values, every one - sit side by side on the last.
        box.appendChild(qeRow(L.col_pr, qeSelect('pr', prios, QE.base.pr, L.empty), 'w-sel'));
        box.appendChild(qeRow(L.col_ms, qeSelect('ms', miles, QE.base.ms, L.empty), 'w-sel'));
        box.appendChild(qeRow(L.col_as, qeChips('as', people, QE.base.as, function (v) {
            // Not a member, but a person the payload knows by name.
            var u = own(D.users, v);
            return u ? String(u.n) : qeUnlisted(v);
        }, true), 'w-full'));
        box.appendChild(qeRow(L.col_cat, qeChips('cat', cats, QE.base.cat), 'w-full'));
        box.appendChild(qeRow(L.col_sd, qeInput('sd', 'date', QE.base.sd), 'w-date'));
        box.appendChild(qeRow(L.col_dd, qeInput('dd', 'date', QE.base.dd), 'w-date'));
        box.appendChild(qeRow(L.col_dt, qeInput('dt', 'time', QE.base.dt), 'w-time'));
        box.appendChild(qeRow(L.lbl_eh_only, qeInput('eh', 'number', QE.base.eh, { min: '0', step: '0.5' }), 'w-num', 'h'));
        box.appendChild(qeRow(L.lbl_ah_only, qeInput('ah', 'number', QE.base.ah, { min: '0', step: '0.5' }), 'w-num', 'h'));
        box.appendChild(qeRow(L.lbl_pg, qeInput('pg', 'number', QE.base.pg, { min: '0', max: '100', step: '1' }), 'w-num', '%'));
        if (carry) { qeApplyCarry(carry); }
        qeMark();
    }
    // What the reader has moved and not sent, each with the base it was moved
    // from - read off the dock just before it is drawn again.
    function qeCarry() {
        var now = qeRead();
        var out = {};
        Object.keys(QE.touched || {}).forEach(function (key) {
            if (!(key in now) || qeSame(now[key], QE.base[key])) { return; }
            out[key] = { now: now[key], was: QE.base[key] };
        });

        return out;
    }
    // ...and put back over the new base. A multi-value field takes the
    // reader's additions and removals rather than their whole list: an
    // assignee somebody else added in the meantime stays added.
    function qeApplyCarry(carry) {
        var box = document.getElementById('pp-cmt-fields');
        if (!box) { return; }
        Object.keys(carry).forEach(function (key) {
            var el = box.querySelector('[data-qe="' + key + '"]');
            if (!el) { return; }
            var c = carry[key];
            var value;
            if (el.dataset.qeMulti) {
                var was = (c.was || []).map(String);
                var now = (c.now || []).map(String);
                value = (QE.base[key] || []).map(String).filter(function (v) {
                    return !(was.indexOf(v) >= 0 && now.indexOf(v) < 0);
                });
                now.forEach(function (v) {
                    if (was.indexOf(v) < 0 && value.indexOf(v) < 0) { value.push(v); }
                });
                qeChipsSet(el, value);
            } else {
                value = String(c.now == null ? '' : c.now);
                if (el.tagName === 'SELECT') { qeSelectEnsure(el, value); }
                el.value = value;
            }
            if (!qeSame(value, QE.base[key])) { QE.touched[key] = true; }
        });
    }
    // Back to what the issue says: every widget shows its base again, the
    // status and the people picked are let go, and nothing counts as unsent.
    function qeReset() {
        var box = document.getElementById('pp-cmt-fields');
        QE.touched = {};
        if (box) {
            box.querySelectorAll('[data-qe]').forEach(function (el) {
                var key = el.dataset.qe;
                if (el.dataset.qeMulti) { qeChipsSet(el, QE.base[key] || []); return; }
                el.value = QE.base[key] == null ? '' : String(QE.base[key]);
            });
        }
        var st = document.getElementById('pp-cmt-status');
        if (st) { st.value = ''; }
        document.querySelectorAll('#pp-notify-chips .pp-notify').forEach(function (c) { c.classList.add('off'); });
        rvAskSync(true);
        ppNotifyCount();
        qeMark();
    }
    // What the widgets hold now, in the same shape QE.base is kept in, so the
    // two can be compared field by field.
    function qeRead() {
        var out = {};
        var box = document.getElementById('pp-cmt-fields');
        if (!box) { return out; }
        box.querySelectorAll('[data-qe]').forEach(function (el) {
            var name = el.dataset.qe;
            if (name === 'st') { return; }
            if (el.dataset.qeMulti) {
                out[name] = Array.prototype.slice.call(el.querySelectorAll('.bl-chip:not(.off)'))
                    .map(function (c) { return String(c.dataset.val); });

                return;
            }
            out[name] = String(el.value == null ? '' : el.value);
            // The due time leaves as HH:MM, and 00:00 (where the picker's
            // spinner starts) as no time at all.
            if (name === 'dt') { out[name] = qeRowValue('dt', out[name]) || ''; }
        });

        return out;
    }
    function qeSame(a, b) {
        if (Array.isArray(a) || Array.isArray(b)) {
            var x = (a || []).slice().sort(), y = (b || []).slice().sort();

            return x.length === y.length && x.every(function (v, n) { return String(v) === String(y[n]); });
        }

        return String(a == null ? '' : a) === String(b == null ? '' : b);
    }
    // The fields the reader moved, by their short names, holding what each
    // widget says now. A widget nobody touched is left out whatever it reads:
    // it was drawn from the row, and a comment that only meant to say
    // something must never "correct" a value the dock merely could not show.
    function qeChanged() {
        var now = qeRead();
        var out = {};
        Object.keys(QE_COLS).forEach(function (key) {
            if (!own(QE.touched, key) || !(key in now) || qeSame(now[key], QE.base[key])) { return; }
            out[key] = now[key];
        });

        return out;
    }
    // ...keyed by the column each is stored in - a partial write, so a field
    // this form never offered is never touched either.
    function qeColumns(changed) {
        var out = {};
        Object.keys(changed || {}).forEach(function (key) {
            var col = qeCol(own(QE_COLS, key));
            if (col) { out[col] = changed[key]; }
        });

        return out;
    }
    function qeDiff() { return qeColumns(qeChanged()); }
    // A count on the collapsed bar, so the dock says it is holding unsent
    // work without having to be opened to find out.
    function qeMark() {
        var state = document.getElementById('pp-dockstate');
        if (!state) { return; }
        var text = document.getElementById('pp-cmt-text');
        var n = Object.keys(qeChanged()).length;
        var stEl = document.getElementById('pp-cmt-status');
        if (stEl && stEl.value) { n += 1; }
        var typed = !!(text && text.value.trim());
        state.textContent = (n || typed) ? L.dock_draft + (n ? ' (' + n + ')' : '') : '';
    }
    window.ppQeDiff = qeDiff;
    // The dock is fixed to the window, so it has to be told where the content
    // area is and how tall it ended up. Measured rather than written as a
    // calc(): the sidebar collapses, the theme changes the header height, and
    // any constant here would be wrong on somebody's screen.
    function ppDockFit() {
        var dock = document.getElementById('pp-cmtdock');
        var screen = document.getElementById('pp-issue');
        if (!dock || !screen) { return; }
        if (dock.hidden) {
            screen.style.removeProperty('--pp-dock-h');
            document.body.style.removeProperty('--pp-dock-h');
            document.body.classList.remove('pp-dock-on');
            return;
        }
        var host = screen.parentNode && screen.parentNode.getBoundingClientRect
            ? screen.parentNode : screen;
        var r = host.getBoundingClientRect();
        dock.style.setProperty('--pp-dock-l', Math.round(r.left) + 'px');
        dock.style.setProperty('--pp-dock-w', Math.round(r.width) + 'px');
        var h = Math.ceil(dock.getBoundingClientRect().height);
        screen.style.setProperty('--pp-dock-h', h + 'px');
        // The admin footer lives outside this page's markup and has to be
        // told as well, or it scrolls up over the dock at the foot of the
        // document. Only the bar's own height is reserved: the sheet opens
        // upwards over the issue and closes again, and a footer that jumped
        // by half a screen every time it did would be worse than the bug.
        var bar = document.getElementById('pp-dockbar');
        var barH = bar ? Math.ceil(bar.getBoundingClientRect().height) : h;
        document.body.style.setProperty('--pp-dock-h', barH + 'px');
        document.body.classList.add('pp-dock-on');
    }
    window.ppDockFit = ppDockFit;
    // ISS00336. How far down the window a pinned line has to start: below
    // Exment's own header when the theme fixes it to the window, else at the
    // very top. Measured, for the same reason the dock is.
    function ppStickTop() {
        var top = 0;
        var hdr = document.querySelector('.main-header');
        if (hdr) {
            var pos = window.getComputedStyle(hdr).position;
            if (pos === 'fixed' || pos === 'sticky') {
                top = Math.max(0, Math.round(hdr.getBoundingClientRect().bottom));
            }
        }
        if (root._ppStickTop !== top) {
            root._ppStickTop = top;
            root.style.setProperty('--pp-stick-top', top + 'px');
        }
        return top;
    }
    // The issue's line: on screen only while an issue is open and its title
    // has scrolled up out of sight.
    function ppDbarFit(top) {
        var bar = document.getElementById('pp-dbar');
        var screen = document.getElementById('pp-issue');
        if (!bar || !screen) { return; }
        if (top === undefined) { top = ppStickTop(); }
        var title = document.querySelector('#pp-d-main .bl-dtitle');
        // offsetParent is null while the issue's card is swapped out for the
        // inline editor: its title then measures 0 and would read as
        // "scrolled away", putting a way out on top of a half-written edit.
        var on = String(currentScreen) === 'issue' && !!title && !!bar.dataset.id
            && title.offsetParent !== null
            && title.getBoundingClientRect().bottom < top;
        if (on) {
            var host = screen.parentNode && screen.parentNode.getBoundingClientRect ? screen.parentNode : screen;
            var r = host.getBoundingClientRect();
            bar.style.setProperty('--pp-dbar-l', Math.round(r.left) + 'px');
            bar.style.setProperty('--pp-dbar-w', Math.round(r.width) + 'px');
        }
        if (bar.hidden === on) { bar.hidden = !on; }
    }
    // The wiki's page list, held beside a long article on a wide screen.
    // Fixed once the grid's top has gone above the pinned line, and pushed
    // up with the grid's bottom so it never runs over the footer.
    var WIDE = window.matchMedia ? window.matchMedia('(min-width:761px) and (min-height:560px)') : null;
    function ppWtreeFit(top) {
        var grid = document.querySelector('#pp-wiki .bl-wiki');
        var card = grid ? grid.firstElementChild : null;
        if (!card) { return; }
        var unpin = function () {
            if (!card.classList.contains('pp-pinned')) { return; }
            card.classList.remove('pp-pinned');
            grid.classList.remove('pp-wtree-pinned');
            ['top', 'left', 'width', 'max-height'].forEach(function (k) { card.style.removeProperty(k); });
            var body = card.querySelector('.bl-wtree');
            if (body) { body.style.removeProperty('max-height'); }
        };
        if (String(currentScreen) !== 'wiki' || (WIDE && !WIDE.matches)) { unpin(); return; }
        // Only beside an article longer than the list: pinning a list that
        // is the taller of the two would shorten the page under the reader.
        var article = card.nextElementSibling;
        var listH = card.classList.contains('pp-pinned') ? card.scrollHeight : card.offsetHeight;
        if (!article || article.offsetHeight <= listH) { unpin(); return; }
        top = (top === undefined ? ppStickTop() : top) + 8;
        var g = grid.getBoundingClientRect();
        if (g.top >= top) { unpin(); return; }
        var cols = String(window.getComputedStyle(grid).gridTemplateColumns || '').split(' ');
        var w = parseFloat(cols[0]) || 260;
        var maxH = Math.max(120, window.innerHeight - top - 8);
        card.classList.add('pp-pinned');
        grid.classList.add('pp-wtree-pinned');
        card.style.width = Math.round(w) + 'px';
        card.style.left = Math.round(g.left) + 'px';
        // The list scrolls inside, under its own heading (and its add link),
        // rather than the heading scrolling away with it.
        var body = card.querySelector('.bl-wtree');
        var headH = body ? card.offsetHeight - body.offsetHeight : 0;
        if (body) { body.style.maxHeight = Math.max(80, maxH - headH) + 'px'; }
        var h = Math.min(card.offsetHeight, maxH);
        card.style.top = Math.round(Math.min(top, g.bottom - h - 12)) + 'px';
    }
    // Down to the comments, from the summary row or the pinned line.
    root.addEventListener('click', function (ev) {
        if (!(ev.target.closest && ev.target.closest('.pp-tocmt'))) { return; }
        ev.preventDefault();
        var tl = document.getElementById('pp-d-tlbox');
        if (tl) { tl.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
    });
    // Boxes folded on an earlier visit come back folded.
    Array.prototype.forEach.call(root.querySelectorAll('.bl-card-box[id]'), function (card) {
        if (!card.querySelector('.bl-boxhead[data-collapse]')) { return; }
        try {
            if (localStorage.getItem('pmportal.fold.' + card.id) === '1') { card.classList.add('bl-collapsed'); }
        } catch (e) { /* ignore */ }
    });
    // The boxes about the issue stand beside its text on a wide screen
    // (.pp-two in the stylesheet) - but only while there is a box to show
    // and the text itself is on screen: with every box hidden the column
    // would be an empty strip, and the inline editor (which hides the main
    // card) wants the whole width. Each box is shown and hidden through its
    // style by whoever draws it, so that is what is watched.
    function ppSideFit() {
        var side = document.getElementById('pp-d-side');
        var main = document.getElementById('pp-d-main');
        var mainCard = main ? main.closest('.bl-card-box') : null;
        if (!side || !mainCard || !side.parentNode) { return; }
        var any = Array.prototype.some.call(side.children, function (c) { return c.style.display !== 'none'; });
        var two = any && mainCard.style.display !== 'none';
        if (side.hidden === any) { side.hidden = !any; }
        if (side.parentNode.classList.contains('pp-two') !== two) { side.parentNode.classList.toggle('pp-two', two); }
    }
    (function () {
        var side = document.getElementById('pp-d-side');
        var main = document.getElementById('pp-d-main');
        var mainCard = main ? main.closest('.bl-card-box') : null;
        if (!side || !mainCard) { return; }
        ppSideFit();
        if (!window.MutationObserver) { return; }
        var mo = new MutationObserver(ppSideFit);
        [mainCard].concat(Array.prototype.slice.call(side.children)).forEach(function (el) {
            mo.observe(el, { attributes: true, attributeFilter: ['style'] });
        });
    })();
    // The issue's menu also closes on a click outside the portal (the admin
    // sidebar, the header) and on Escape; clicks inside are the root
    // listener's business.
    function ppMoreClose() {
        Array.prototype.forEach.call(root.querySelectorAll('.pp-more'), function (w) {
            var m = w.querySelector('.m');
            var b = w.querySelector('.pp-morebtn');
            if (m) { m.hidden = true; }
            if (b) { b.setAttribute('aria-expanded', 'false'); }
        });
    }
    document.addEventListener('click', function onPpMoreDoc(ev) {
        if (!alive()) { document.removeEventListener('click', onPpMoreDoc); return; }
        if (!root.contains(ev.target)) { ppMoreClose(); }
    });
    document.addEventListener('keydown', function onPpMoreKey(ev) {
        if (!alive()) { document.removeEventListener('keydown', onPpMoreKey); return; }
        if (ev.key === 'Escape') { ppMoreClose(); }
    });
    // The issue list scrolls inside its own frame on a wide screen, so its
    // column heads can stay at the frame's top (position:sticky only works
    // inside the box that scrolls). A fixed max-height (100vh - 70px) left
    // the page scrolling too wherever the head of the portal is taller than
    // that guess - and then the frame, heads and all, scrolled out of sight.
    // So the frame gets exactly the room left under it on screen, measured.
    function ppListFit() {
        var wrap = document.querySelector('#pp-issue-head') ? document.querySelector('#pp-issue-head').closest('.bl-tablewrap') : null;
        if (!wrap) { return; }
        if (String(currentScreen) !== 'issues' || (WIDE && !WIDE.matches)) {
            wrap.style.removeProperty('max-height');
            return;
        }
        var se = document.scrollingElement || document.documentElement;
        var top = wrap.getBoundingClientRect().top + (se ? se.scrollTop : 0);
        // Whatever stays below it on the page: Exment's footer where there
        // still is one, and the frame's own margin.
        var foot = document.querySelector('.main-footer');
        var below = (foot ? foot.offsetHeight : 0) + 16;
        wrap.style.maxHeight = Math.max(240, Math.floor(window.innerHeight - top - below)) + 'px';
    }
    // Both pinned things, measured once per frame.
    function ppPinFit() {
        var top = ppStickTop();
        ppDbarFit(top);
        ppWtreeFit(top);
    }
    function ppDbarFill(i) {
        var bar = document.getElementById('pp-dbar');
        if (!bar) { return; }
        bar.dataset.id = String(i.id);
        bar.innerHTML = '<a class="bl-dback bl-dback-s" href="#issues"><i class="fa fa-angle-left"></i> ' + esc(L.back) + '</a>' +
            '<span class="bl-key">' + esc(i.key) + '</span>' +
            '<span class="t" role="button" tabindex="0" title="' + esc(i.subject) + '">' + esc(i.subject) + '</span>' + pill(i.st) +
            '<a class="pp-tocmt" href="javascript:void(0);"><i class="fa fa-comments-o"></i> ' + esc(L.to_comments) + '<span class="n"></span></a>';
        ppDbarFit();
    }
    // The title takes you back up to the whole head of the issue. Scrolled
    // into view rather than window.scrollTo: on this admin theme the window
    // itself may not be what scrolls (see SCROLL_POS below).
    var ppDbarEl = document.getElementById('pp-dbar');
    var ppDbarTop = function (ev) {
        if (!(ev.target.closest && ev.target.closest('.t'))) { return; }
        if (ev.type === 'keydown' && ev.key !== 'Enter' && ev.key !== ' ') { return; }
        ev.preventDefault();
        var main = document.getElementById('pp-d-main');
        if (main) { main.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
    };
    if (ppDbarEl) {
        ppDbarEl.addEventListener('click', ppDbarTop);
        ppDbarEl.addEventListener('keydown', ppDbarTop);
    }
    // Listened for on the document, in the capture phase, so the scroll is
    // heard whichever box does the scrolling - the window, or an admin
    // wrapper when the theme pins html and body to the window's height.
    var ppPinPending = false;
    document.addEventListener('scroll', function onPpPin() {
        if (!alive()) { document.removeEventListener('scroll', onPpPin, true); return; }
        if (ppPinPending) { return; }
        ppPinPending = true;
        window.requestAnimationFrame(function () { ppPinPending = false; if (alive()) { ppPinFit(); } });
    }, { capture: true, passive: true });
    // Folding Exment's sidebar moves the content area without a resize.
    document.addEventListener('click', function onPpSidebar(ev) {
        if (!alive()) { document.removeEventListener('click', onPpSidebar); return; }
        if (!(ev.target.closest && ev.target.closest('.sidebar-toggle'))) { return; }
        setTimeout(function () { if (alive()) { ppDockFit(); ppPinFit(); } }, 350);
    });
    // On screen only while an issue is: every other screen has its own way in
    // to writing, and a composer pinned under a wiki page means nothing.
    function ppDockShow(on) {
        var dock = document.getElementById('pp-cmtdock');
        if (!dock) { return; }
        if (!on) { ppDockOpen(false); }
        dock.hidden = !on;
        ppDockFit();
    }
    function ppDockOpen(on) {
        var dock = document.getElementById('pp-cmtdock');
        var sheet = document.getElementById('pp-docksheet');
        var bar = document.getElementById('pp-dockbar');
        if (!dock || !sheet) { return; }
        sheet.hidden = !on;
        dock.classList.toggle('on', !!on);
        if (bar) { bar.setAttribute('aria-expanded', on ? 'true' : 'false'); }
        ppDockFit();
        if (on) {
            var box = document.getElementById('pp-cmt-text');
            if (box) { try { box.focus(); } catch (e) { /* ignore */ } }
        }
    }
    window.ppDockOpen = ppDockOpen;
    // The chips are not buttons, but they are pressed like one: reachable
    // with Tab, switched with Enter or Space. Ctrl+Enter stays the form's.
    (function () {
        var sheet = document.getElementById('pp-docksheet');
        if (!sheet) { return; }
        sheet.addEventListener('keydown', function (ev) {
            if (ev.key !== 'Enter' && ev.key !== ' ') { return; }
            if (ev.ctrlKey || ev.metaKey || ev.altKey) { return; }
            var t = ev.target;
            if (!t || !t.classList || !t.classList.contains('bl-chip')) { return; }
            ev.preventDefault();
            t.click();
        });
    }());
    function ppDockIsOpen() {
        var sheet = document.getElementById('pp-docksheet');
        return !!(sheet && !sheet.hidden);
    }
    // The inline editor owns the whole screen while it is mounted, and a
    // composer floating over it belongs to the issue it just replaced.
    function ppDetailEditing(on) {
        if (on) { detailEpoch++; cmtSetBusy(false); }
        // The pinned line follows the issue's card in and out of the editor.
        setTimeout(function () { if (alive()) { ppDbarFit(); } }, 0);
        var dock = document.getElementById('pp-cmtdock');
        if (!dock) { return; }
        if (on) {
            dock._wasOn = String(currentScreen) === 'issue' && !dock.hidden;
            ppDockShow(false);
            return;
        }
        if (dock._wasOn) { ppDockShow(true); }
        dock._wasOn = false;
    }
    window.ppDetailEditing = ppDetailEditing;
    var ppFitPending = null;
    window.addEventListener('resize', function onPpFit() {
        if (!alive()) { window.removeEventListener('resize', onPpFit); return; }
        if (ppFitPending) { return; }
        ppFitPending = setTimeout(function () { ppFitPending = null; if (alive()) { ppDockFit(); ppPinFit(); ppListFit(); } }, 120);
    });
    // Backlog shows the stream two ways: everything that happened, or the
    // comments on their own. The choice belongs to the person, not to the
    // issue, so it is remembered and applies to whatever opens next.
    var TL_KEY = 'pmportal.timeline.filter';
    var TLF = 'all';
    try { TLF = localStorage.getItem(TL_KEY) === 'cmt' ? 'cmt' : 'all'; } catch (e) { /* ignore */ }
    function renderTimeline(res) {
        var box = document.getElementById('pp-d-timeline');
        document.querySelectorAll('#pp-tl-filter button').forEach(function (b) {
            b.classList.toggle('on', b.getAttribute('data-tl') === TLF);
        });
        // A comment being rewritten in place is the reader's unsent work, and
        // this is the redraw that would take its box away - a background
        // refresh, a comment posted or deleted. Each open editor is put back
        // on its comment afterwards, text and caret as they were.
        var editing = cmtEditsOpen();
        // How many comments there are, on the box's head and on the links
        // that lead down to it: whether the trip is worth making.
        var cmtN = res.timeline === null ? 0
            : (res.timeline || []).filter(function (e) { return e.tp === 'cmt'; }).length;
        var tlSum = document.getElementById('pp-d-tlsum');
        if (tlSum) { tlSum.textContent = cmtN ? String(cmtN) : ''; }
        Array.prototype.forEach.call(root.querySelectorAll('#pp-issue .pp-tocmt .n'), function (n) {
            n.textContent = cmtN ? String(cmtN) : '';
        });
        if (res.timeline === null) { box.innerHTML = skel(4); return; }
        var rows = (res.timeline || []).filter(function (e) { return TLF !== 'cmt' || e.tp === 'cmt'; });
        box.innerHTML = rows.map(function (e) {
            var body = '';
            if (e.tp === 'chg') {
                body = '<div class="chg">' + (e.chg || []).map(function (c) {
                    return '<div><b>' + esc(c[0]) + '</b>: ' + esc(c[1]) + '<span class="arr">→</span><b>' + esc(c[2]) + '</b></div>';
                }).join('') + '</div>';
            } else if (e.tp === 'add') {
                body = '<div class="chg"><i class="fa fa-plus" style="color:#4caf93;margin-right:6px;"></i>' + esc(L.added) + '</div>';
            } else if (e.tp === 'file') {
                body = '<div class="chg"><i class="fa fa-paperclip" style="margin-right:6px;"></i>' + esc(L.file_added) +
                    ' <a class="bl-file" href="' + esc(linkUrl(e.url)) + '"><i class="fa fa-file-o"></i>' + esc(e.name) + '</a>' + officeViewBtn(e) + '</div>';
            } else {
                // Only the author may rewrite or remove what they wrote; the
                // address of a comment is public to everyone who can read it.
                var mine = !!e.del;
                body = '<div class="txt bl-md">' + renderCommentText(e.text) + '</div>' + rvBar(e) +
                    '<div class="act">' + rxBar('comment', e.cid, e.rx) + starBtn('star', 'comment', e.cid, e.star, e.my_star, 'as-link') +
                    '<a href="javascript:void(0);" class="pp-quote" data-text="' + esc(e.text) + '"><i class="fa fa-quote-right"></i> ' + esc(L.quote) + '</a>' +
                    '<span class="cmenu"><a href="javascript:void(0);" class="pp-cmenu" title="' + esc(L.cmt_menu) + '"><i class="fa fa-ellipsis-h"></i></a>' +
                    '<span class="m" hidden>' +
                    (mine ? '<a href="javascript:void(0);" class="pp-cedit" data-cid="' + esc(e.cid) + '" data-text="' + esc(e.text) + '">' + esc(L.cmt_edit) + '</a>' : '') +
                    '<a href="javascript:void(0);" class="pp-ccopy" data-cid="' + esc(e.cid) + '">' + esc(L.cmt_copy) + '</a>' +
                    (mine ? '<a href="javascript:void(0);" class="pp-cdel" data-cid="' + esc(e.cid) + '">' + esc(L.del) + '</a>' : '') +
                    '</span></span></div>';
            }
            return '<div class="ent"' + (e.tp === 'cmt' ? ' id="pp-c' + esc(e.cid) + '"' : '') + '>' + av(e.by, 1) +
                '<div class="body"><div class="head"><span class="who">' + esc(user(e.by).n) + '</span><span class="tm">' + esc(e.at) + '</span></div>' + body + '</div></div>';
        }).join('') || ('<div style="color:var(--bl-faint);font-size:12px;">' + esc(L.empty) + '</div>');
        clipLongComments();
        highlightLinkedComment();
        editing.forEach(function (open) { cmtEditStart(open.cid, open.value, open); });
    }
    // The inline comment editors that are open: which comment, what is typed
    // and where the caret is - enough to put one back after a redraw.
    function cmtEditsOpen() {
        var out = [];
        document.querySelectorAll('#pp-d-timeline .ent .cedit').forEach(function (wrap) {
            var ent = wrap.closest('.ent');
            var area = wrap.querySelector('textarea');
            if (!ent || !area || !/^pp-c/.test(ent.id || '')) { return; }
            out.push({
                cid: ent.id.slice(4), value: area.value,
                start: area.selectionStart, end: area.selectionEnd,
                focused: document.activeElement === area
            });
        });

        return out;
    }
    // A long comment is folded the way Backlog folds one, so a conversation
    // stays scannable. The fold is per comment and per reading - nothing is
    // stored, and reopening the issue folds it again.
    var CLIP_AT = 260;
    function clipLongComments() {
        var box = document.getElementById('pp-d-timeline');
        box.querySelectorAll('.ent .txt').forEach(clipOne);
        // A picture has no height until it has arrived, so a comment that is
        // long only because of one measures short here and has to be asked
        // again the moment the browser knows how big it is.
        box.querySelectorAll('.ent .txt img').forEach(function (img) {
            if (img.complete) { return; }
            img.addEventListener('load', function () { clipOne(img.closest('.txt')); }, { once: true });
        });
        document.getElementById('pp-tl-expand').textContent = L.tl_expand;
        syncExpandAll();
    }
    function clipOne(txt) {
        if (!txt || txt.classList.contains('clip') || txt.parentNode.querySelector('.more')) { return; }
        if (txt.scrollHeight <= CLIP_AT) { return; }
        txt.classList.add('clip');
        var row = document.createElement('div');
        row.className = 'more';
        var more = document.createElement('a');
        more.className = 'pp-cmore';
        more.href = 'javascript:void(0);';
        more.textContent = L.cmt_more;
        row.appendChild(more);
        txt.parentNode.insertBefore(row, txt.nextSibling);
        syncExpandAll();
    }
    function syncExpandAll() {
        var box = document.getElementById('pp-d-timeline');
        document.getElementById('pp-tl-expand').hidden = !box.querySelector('.ent .more');
    }
    // A copied comment address opens the issue and says which comment it
    // meant. show() restores the screen's own scroll one frame from now, so
    // the jump waits for that to be over rather than fighting it.
    function highlightLinkedComment() {
        var want = parseQuery(parseHash().query).c;
        if (!want) { return; }
        var ent = document.getElementById('pp-c' + want);
        if (!ent) { return; }
        ent.classList.add('hl');
        setTimeout(function () { ent.classList.remove('hl'); }, 2600);
        setTimeout(function () {
            try { ent.scrollIntoView({ block: 'center' }); } catch (e) { ent.scrollIntoView(); }
        }, 80);
    }
    // The files hanging off this issue, laid out on the same two-cell rule as
    // every other card on this screen: the preview sits in the label cell, what
    // is known about the file in the value cell.
    function renderIssueFiles(res) {
        var rows = res.files || [];
        document.getElementById('pp-d-filebox').style.display = rows.length ? '' : 'none';
        document.getElementById('pp-d-filesum').textContent = rows.length ? String(rows.length) : '';
        document.getElementById('pp-d-files').innerHTML = rows.map(function (f) {
            var href = esc(linkUrl(f.url));
            return '<div class="row"><div class="k">' +
                '<a class="th" href="' + href + '" target="_blank" rel="noopener" title="' + esc(f.name) + '">' +
                (f.img && imgSrcOk(f.url) ? '<img src="' + esc(f.url) + '" alt="' + esc(f.name) + '">'
                       : '<span class="ic"><i class="fa fa-file-o"></i></span>') +
                '</a></div>' +
                '<div class="v"><a class="n" href="' + href + '" target="_blank" rel="noopener">' + esc(f.name) + '</a>' + officeViewBtn(f) +
                '<span class="mt">' + esc(user(f.by).n) + ' · ' + esc(f.at) + '</span></div></div>';
        }).join('');
    }
    // Backlog's "‹ 3 / 21 ›": step through the list you came from without
    // going back to it between issues. The order and the membership are the
    // grid's own, filters included - what the user is actually looking at.
    function issueNav(current) {
        var list = filteredIssues();
        // In tree mode the grid's order is the tree's, folded rows included.
        if (TREE_ON) { list = treeRows(list, true).map(function (e) { return e.i; }); }
        var at = -1;
        list.forEach(function (row, index) { if (String(row.id) === String(current.id)) { at = index; } });
        if (at < 0 || list.length < 2) { return ''; }
        var prev = list[at - 1], next = list[at + 1];
        return '<span class="bl-inav">' +
            '<a class="' + (prev ? '' : 'off') + '" href="#' + (prev ? esc(issueHash(prev.id)) : '') + '" title="' + esc(L.nav_prev) + '"><i class="fa fa-angle-left"></i></a>' +
            '<span>' + esc(fmt(L.nav_pos, at + 1, list.length)) + '</span>' +
            '<a class="' + (next ? '' : 'off') + '" href="#' + (next ? esc(issueHash(next.id)) : '') + '" title="' + esc(L.nav_next) + '"><i class="fa fa-angle-right"></i></a></span>';
    }
    // Comment text is written in the small subset of Backlog's formatting
    // rules a conversation actually uses. It arrives as plain text and is
    // escaped first, so every tag below is one this function wrote - no
    // comment can smuggle markup in, however it was typed.
    //
    // Code, links and images are lifted into a holding array the moment they
    // are recognised: what is left of them - a URL, the body of a snippet -
    // must not be read as markup by the rules that run after.
    function renderCommentText(text) {
        var html = esc(text);
        var kept = [];
        var MARK = String.fromCharCode(1);
        function keep(h) { kept.push(h); return MARK + (kept.length - 1) + MARK; }
        // \[ and \] are a bracket and nothing more, the way markdown writes
        // one inside the text of a link - and the way a file name is written
        // when it is inserted as a link (see mdLinkText). Each is held aside
        // as the single character it stands for, so a name cannot close its
        // link early and open a second one pointing somewhere of its own.
        var LIT = String.fromCharCode(2);
        var lits = [];
        function unlit(s) {
            return String(s).replace(new RegExp(LIT + '(\\d+)' + LIT, 'g'), function (m, at) {
                return lits[Number(at)] || '';
            });
        }

        // A fence may name its language on the first line (```js): the name is
        // the block's, not a line of its code. Only a word of name characters,
        // so it can go in a class as it is.
        html = html.replace(/```[ \t]*([A-Za-z0-9_+#.\-]+)[ \t]*\r?\n([\s\S]*?)```/g, function (m, lang, body) {
            return keep('<pre class="bl-code" data-lang="' + lang.toLowerCase() + '">' + body.replace(/\s+$/, '') + '</pre>');
        });
        html = html.replace(/```[ \t]*\r?\n?([\s\S]*?)```/g, function (m, body) {
            return keep('<pre class="bl-code">' + body.replace(/\s+$/, '') + '</pre>');
        });
        html = html.replace(/`([^`\r\n]+)`/g, function (m, body) { return keep('<code>' + body + '</code>'); });
        // A backslash before a character markdown would read keeps the
        // character as it is (\* \_ \# \~ \| \! \\ and the brackets).
        html = html.replace(/\\([\[\]*_`#~|!\\])/g, function (m, ch) {
            lits.push(ch);
            return LIT + (lits.length - 1) + LIT;
        });
        // The image and the link differ by one leading '!', so images first.
        // A picture is fetched the moment the comment is read, with the
        // reader's session - so on this site only a file may be one, and a
        // comment cannot make its readers' browsers call anything else here
        // (a GET logout, say). Any other safe address is an ordinary link.
        // The title after the address ([text](url "title")) is the tooltip.
        // It is escaped text already, so it can go in the attribute.
        function titleAttr(title) { return title ? ' title="' + unlit(title) + '"' : ''; }
        html = html.replace(/!\[([^\]\r\n]*)\]\(([^)\s]+)(?:[ \t]+&quot;([^\r\n]*?)&quot;)?\)/g, function (m, alt, url, title) {
            var href = unlit(url);
            if (!safeHref(href)) { return m; }
            if (!imgSrcOk(unesc(href))) {
                return keep('<a href="' + href + '" target="_blank" rel="noopener"' + titleAttr(title) + '>' + (unlit(alt) || href) + '</a>');
            }
            return keep('<a class="bl-cimg" href="' + href + '" target="_blank" rel="noopener"' + titleAttr(title) + '>' +
                '<img src="' + href + '" alt="' + unlit(alt) + '"></a>');
        });
        html = html.replace(/\[([^\]\r\n]+)\]\(([^)\s]+)(?:[ \t]+&quot;([^\r\n]*?)&quot;)?\)/g, function (m, label, url, title) {
            var href = unlit(url);
            if (!safeHref(href)) { return m; }
            return keep('<a href="' + href + '" target="_blank" rel="noopener"' + titleAttr(title) + '>' + unlit(label) + '</a>');
        });
        // <https://...>: the brackets are markdown's, not the address's.
        html = html.replace(/&lt;(https?:\/\/[^\s]+?)&gt;/g, function (m, url) {
            var href = unlit(url);
            if (!safeHref(unesc(href))) { return m; }
            return keep('<a href="' + href + '" target="_blank" rel="noopener">' + href + '</a>');
        });
        // A bare address is a link too - the commonest thing anyone pastes.
        // Trailing punctuation is sentence, not URL, so it stays outside.
        html = html.replace(/https?:\/\/[^\s<>]+/g, function (m) {
            // The text was escaped first: an address ends where a quote or an
            // angle bracket of the sentence begins, not after its entity.
            var cut = m.search(/&(?:quot|lt|gt|#x27);/);
            var rest = cut >= 0 ? m.slice(cut) : '';
            m = cut >= 0 ? m.slice(0, cut) : m;
            var tail = m.match(/[.,;:)\]}]+$/);
            var url = unlit(tail ? m.slice(0, m.length - tail[0].length) : m);
            return keep('<a href="' + url + '" target="_blank" rel="noopener">' + url + '</a>') + (tail ? tail[0] : '') + rest;
        });

        html = html.replace(/\*\*\*([^*\r\n]+)\*\*\*/g, '<b><i>$1</i></b>');
        html = html.replace(/\*\*([^*\r\n]+)\*\*/g, '<b>$1</b>');
        html = html.replace(/(^|[^*\w])\*([^*\r\n]+)\*(?!\w)/g, '$1<i>$2</i>');
        // The underscore forms, only around words: snake_case and a file
        // name with underscores stay as they are.
        html = html.replace(/(^|[^\w])__([^_\r\n]+)__(?!\w)/g, '$1<b>$2</b>');
        html = html.replace(/(^|[^\w])_([^_\r\n]+)_(?!\w)/g, '$1<i>$2</i>');
        html = html.replace(/~~([^~\r\n]+)~~/g, '<s>$1</s>');
        html = mdBlocks(html);

        // A mention is an address, so it is a link: hovering one says who the
        // person is, and the card behind it is the only place that ever says.
        // Known names are linked and held aside; what is left is a handle
        // this project has never heard of, which is marked but not linked.
        var mm = mentions();
        if (mm.re) {
            html = html.replace(mm.re, function (m, lead, name) {
                return lead + keep('<a class="bl-mention pp-card" href="javascript:void(0);" data-uid="' +
                    esc(mm.by[name]) + '">@' + name + '</a>');
            });
        }
        // A handle nobody on this project answers to. Marked, so the writer
        // can see it was read as a mention, but not linked to anyone. The
        // same leading guard keeps it out of a mail address.
        html = html.replace(/(^|[^\w@.])@([A-Za-z0-9_.\-]+)/g, '$1<span class="bl-mention">@$2</span>');
        var key = (D.project.key || '#') + '-';
        html = html.replace(new RegExp(reEsc(key) + '(\\d+)', 'g'), function (m, num) {
            // The number in a key is the key's own number, which is not always
            // the row id - so it is resolved rather than pasted into the
            // address, and a key this project does not carry is left as it was
            // written instead of pointing at whatever row shares the number.
            var hit = issueByKeyNum(num) || issueById(num);
            return keep('<a class="bl-issuelink pp-card" href="#' +
                (hit ? esc(issueHash(hit.id)) : ('issue-' + num)) + '"' +
                (hit ? ' data-issue="' + esc(hit.id) + '"' : '') + '>' + m + '</a>');
        });
        // An issue key written out - ISS00239 (ISS00440). Linked only when it
        // is one of the issues this page carries, which are the ones the
        // reader may open; any other is left as it was written.
        html = html.replace(/(^|[^\w&;\-])([A-Za-z][A-Za-z0-9_]*\d)(?![\w\-])/g, function (m, lead, word) {
            var hit = issueByIk(word);
            if (!hit) { return m; }
            return lead + keep('<a class="bl-issuelink pp-card" href="#' + esc(issueHash(hit.id)) +
                '" data-issue="' + esc(hit.id) + '">' + word + '</a>');
        });
        // The other way an issue gets written - "#292" - which is what the
        // key is under the surface and what anyone types when they are not
        // looking at the key. Only linked when this project is carrying it:
        // a bare number that names nothing is a number.
        html = html.replace(/(^|[^\w&;])#(\d+)\b/g, function (m, lead, num) {
            var hit = issueById(num) || issueByKeyNum(num);
            if (!hit) { return m; }
            return lead + keep('<a class="bl-issuelink pp-card" href="#' + esc(issueHash(hit.id)) +
                '" data-issue="' + esc(hit.id) + '">#' + num + '</a>');
        });

        return unlit(html.replace(new RegExp(MARK + '(\\d+)' + MARK, 'g'), function (m, at) {
            return kept[Number(at)];
        // A block brings its own spacing, so the newlines that open and
        // close it must not draw one as well.
        }).replace(/<br>(?=<(?:ul|ol|blockquote|pre|table|hr|h[1-6])\b)/g, '')
            .replace(/(<\/(?:ul|ol|blockquote|pre|table|h[1-6])>)<br>/g, '$1')
            .replace(/<hr><br>/g, '<hr>'));
    }
    // The text esc() wrote, read back as the characters it stood for - what
    // the browser will make of an address once it is in an attribute.
    function unesc(s) {
        return String(s).replace(/&(amp|lt|gt|quot|#x27);/g, function (m, e) {
            return { amp: '&', lt: '<', gt: '>', quot: '"', '#x27': "'" }[e];
        });
    }
    // A file name as the text of a markdown link. Brackets are escaped, so a
    // name cannot close its link early and open one of its own pointing
    // somewhere else; a backslash at the very end would escape the closing
    // bracket, so it is written as the full-width one instead.
    function mdLinkText(name) {
        return String(name == null ? '' : name)
            .replace(/[\r\n]+/g, ' ')
            .replace(/([\[\]])/g, '\\$1')
            .replace(/\\+$/, function (m) { return m.replace(/\\/g, '＼'); });
    }
    // A file's address as the target of a markdown link: one that would end
    // the link early or break it in two is percent-encoded instead.
    function mdLinkUrl(url) {
        return String(url == null ? '' : url).replace(/[()\s]/g, function (c) { return encodeURIComponent(c); });
    }
    // The link markup for one file, picture or not.
    function mdFileLink(name, url, img) {
        return (img ? '!' : '') + '[' + mdLinkText(name) + '](' + mdLinkUrl(url) + ')';
    }
    // The description and the wiki page are markdown now, the way Backlog
    // writes them. Rows saved through Exment's own form before that - and
    // through it still, since that form keeps its rich-text editor - hold
    // the editor's html instead, and escaping that would show the reader
    // their own tags. So: html that was authored as html is passed through
    // as it always was, and everything else is read as markdown.
    function mdAuthoredAsHtml(s) {
        // The block tags a rich-text editor opens a document with. Not <img>
        // and not <br>, which open nothing and are what an injected string
        // would arrive as.
        return /^\s*<(?:p|div|h[1-6]|ul|ol|table|blockquote|pre|figure)[\s>]/i.test(s)
            || /<\/(?:p|div)>/i.test(s);
    }
    // What this branch carries is formatting written in an editor. It is read
    // through an allowlist, not a blocklist: every tag and every attribute not
    // named below is dropped, so a tag added to HTML later cannot reopen a hole
    // the way a forgotten blocklist entry would. style is not on the attribute
    // list - it let an injected row paint a full-screen overlay, and safe CSS
    // is too hard to prove by hand - and the elements the HTML parser treats
    // specially (raw-text, escapable-text, a foreign namespace) are removed
    // whole: those are exactly the ones whose re-parse on assignment to
    // innerHTML can differ from the parse seen here, which is the mismatch a
    // mutation payload rides. Images pasted into the old editor arrive as data:
    // urls and are kept.
    var MD_ALLOW_TAGS = {
        a: 1, abbr: 1, b: 1, blockquote: 1, br: 1, caption: 1, code: 1, col: 1,
        colgroup: 1, dd: 1, del: 1, div: 1, dl: 1, dt: 1, em: 1, figcaption: 1,
        figure: 1, h1: 1, h2: 1, h3: 1, h4: 1, h5: 1, h6: 1, hr: 1, i: 1, img: 1,
        ins: 1, li: 1, ol: 1, p: 1, pre: 1, s: 1, small: 1, span: 1, strike: 1,
        strong: 1, sub: 1, sup: 1, table: 1, tbody: 1, td: 1, tfoot: 1, th: 1,
        thead: 1, tr: 1, u: 1, ul: 1
    };
    var MD_ALLOW_ATTR = { href: 1, src: 1, alt: 1, title: 1, colspan: 1, rowspan: 1, start: 1 };
    var MD_DROP_TAGS = {
        script: 1, style: 1, noscript: 1, template: 1, iframe: 1, frame: 1,
        frameset: 1, object: 1, embed: 1, applet: 1, form: 1, input: 1,
        button: 1, select: 1, option: 1, textarea: 1, link: 1, meta: 1,
        base: 1, title: 1, svg: 1, math: 1, xmp: 1, listing: 1
    };
    function mdSanitizeNode(parent) {
        var child = parent.firstChild;
        while (child) {
            var next = child.nextSibling;
            if (child.nodeType === 3) {
                // text node: nothing in it can run, keep it
            } else if (child.nodeType !== 1) {
                parent.removeChild(child); // comment, CDATA, processing instruction
            } else {
                var tag = String(child.tagName || '').toLowerCase();
                if (MD_DROP_TAGS[tag]) {
                    parent.removeChild(child);
                } else if (!MD_ALLOW_TAGS[tag]) {
                    // Unknown, but not known to run: keep the text it wraps,
                    // drop the tag itself, and carry on through the lifted nodes
                    // so they are checked too.
                    var first = child.firstChild;
                    while (child.firstChild) { parent.insertBefore(child.firstChild, child); }
                    parent.removeChild(child);
                    next = first || next;
                } else {
                    var attrs = Array.prototype.slice.call(child.attributes);
                    for (var i = 0; i < attrs.length; i++) {
                        var n = String(attrs[i].name).toLowerCase();
                        if (!MD_ALLOW_ATTR[n]) { child.removeAttribute(attrs[i].name); continue; }
                        if (n === 'href' && !safeHref(child.getAttribute('href'))) {
                            child.removeAttribute('href');
                        } else if (n === 'src' && !imgSrcOk(child.getAttribute('src'))) {
                            // Same rule as a picture in a comment: nothing on
                            // this site but a file is fetched just by reading.
                            child.removeAttribute('src');
                        }
                    }
                    mdSanitizeNode(child);
                }
            }
            child = next;
        }
    }
    function mdSanitizeOnce(html) {
        if (typeof DOMParser !== 'function') { return null; }
        var doc;
        try { doc = new DOMParser().parseFromString(html, 'text/html'); } catch (e) { return null; }
        if (!doc || !doc.body) { return null; }
        mdSanitizeNode(doc.body);
        return doc.body.innerHTML;
    }
    function mdSanitizeHtml(html) {
        var out = String(html == null ? '' : html);
        // Re-clean until the string stops changing. A payload that only turns
        // dangerous when the browser re-parses the cleaned string shows up as a
        // difference on the next pass and is cleaned then; a string that comes
        // back unchanged is one the browser parses the same way this did.
        // Capped so a pathological input cannot spin.
        for (var i = 0; i < 4; i++) {
            var cleaned = mdSanitizeOnce(out);
            if (cleaned === null) { return esc(out); } // no parser here: show as text, never raw
            if (cleaned === out) { return out; }
            out = cleaned;
        }
        return out;
    }
    function renderRichText(text) {
        var s = String(text == null ? '' : text);
        if (!s.trim()) { return ''; }

        return mdAuthoredAsHtml(s) ? mdSanitizeHtml(s) : renderCommentText(s);
    }
    // Only addresses a browser can be pointed at without running anything.
    // "javascript:" survives escaping untouched, so it is stopped here.
    function safeHref(url) {
        return /^(https?:\/\/|\/|\.{1,2}\/|#)/i.test(String(url || ''));
    }
    // An address for a link the page writes itself; anything that is not a
    // safe one points nowhere.
    function linkUrl(url) { return safeHref(url) ? String(url) : '#'; }
    // Where a picture may come from. Showing one is a request made with the
    // reader's session, before anybody clicks anything - so on this site
    // only the file endpoints qualify (stored files, and the temporary ones
    // a paste makes before the save), and elsewhere any http(s) picture or a
    // data:image. Everything else - javascript:, other data:, this site's
    // other pages - is never put in a src.
    var IMG_PATHS = (function () {
        var out = [];
        function add(url, from, to) {
            if (!url) { return; }
            try {
                var path = new URL(String(url), location.href).pathname.replace(/\/+$/, '');
                if (from.test(path)) { out.push(path.replace(from, to)); }
            } catch (e) { /* not an address */ }
        }
        add(D.urls && D.urls.data_base, /\/data$/, '/files/');
        add(D.urls && D.urls.temp_image_upload, /\/tmpimages$/, '/tmpfiles/');

        return out;
    })();
    function imgSrcOk(url) {
        var s = String(url == null ? '' : url).trim();
        if (/^data:image\//i.test(s)) { return true; }
        var u;
        try { u = new URL(s, location.href); } catch (e) { return false; }
        if (u.protocol !== 'http:' && u.protocol !== 'https:') { return false; }
        if (u.origin !== location.origin) { return true; }

        return IMG_PATHS.some(function (p) { return u.pathname.indexOf(p) === 0; });
    }
    // "- [ ] still to do" / "- [x] done". The box shows the state; it does
    // not change it, because the state is a character in the text and this is
    // the rendered copy of that text.
    // The item is left open: a list indented under it goes inside it, and
    // mdBlocks closes it (</li>) before the next item or with its list.
    function mdListItem(text) {
        var box = text.match(/^\[([ xX])\]\s+(.*)$/);
        if (!box) { return '<li>' + text; }

        return '<li class="task"><input type="checkbox" disabled' +
            (box[1] === ' ' ? '' : ' checked') + '> ' + box[2];
    }
    // Lines into blocks: headings, rules, tables, quotes, code, the two kinds
    // of list - and a <br> for each newline that stands between two ordinary
    // lines, never around a block, which brings its own spacing.
    function mdBlocks(html) {
        var out = [], list = null, plain = false, table = null, code = null;
        // How many empty lines are waiting to be spent, and where the last
        // ordinary line was written - a heading underlined by = or - has to
        // reach back and rewrite the line it belongs to.
        var blanks = 0, lastText = null;
        // Every block ends the paragraph above it, so the next ordinary line
        // opens a new one and the empty lines in between are already spent.
        function opened() { plain = false; blanks = 0; lastText = null; }
        // The lists open now, the outermost first: each with its tag and the
        // indent of its items. An item indented under another is a list of
        // its own inside that item.
        var lists = [];
        function closeList() {
            while (lists.length) { out.push('</li></' + lists.pop().tag + '>'); }
            list = null;
            lastText = null;
        }
        function listItem(tag, width, number, text) {
            while (lists.length && width < lists[lists.length - 1].width) {
                out.push('</li></' + lists.pop().tag + '>');
            }
            var top = lists[lists.length - 1];
            if (top && width === top.width && top.tag !== tag) {
                out.push('</li></' + lists.pop().tag + '>');
                top = lists[lists.length - 1];
            }
            if (!top || width > top.width) {
                lists.push({ tag: tag, width: width });
                out.push('<' + tag + (tag === 'ol' && number !== 1 ? ' start="' + number + '"' : '') + '>');
            } else {
                out.push('</li>');
            }
            list = tag;
            out.push(mdListItem(text));
        }
        // Lines of a quote are gathered and written together: one quote with
        // its lines, and a quote inside it for the lines with one more >.
        var quote = null;
        function quoteHtml(lines) {
            var html = '', inner = [], first = true;
            function flushInner() {
                if (inner.length) { html += '<blockquote>' + quoteHtml(inner) + '</blockquote>'; inner = []; first = true; }
            }
            lines.forEach(function (l) {
                var deeper = l.match(/^\s*&gt;\s?(.*)$/);
                if (deeper) { inner.push(deeper[1]); return; }
                flushInner();
                html += (first ? '' : '<br>') + l;
                first = false;
            });
            flushInner();
            return html;
        }
        function closeQuote() {
            if (!quote) { return; }
            out.push('<blockquote>' + quoteHtml(quote) + '</blockquote>');
            quote = null;
            opened();
        }
        // A line that was indented stays indented. Markdown drops the spaces
        // and html collapses what is left, and a stack trace or a pasted
        // listing is only readable while its shape survives. This renderer
        // already keeps every newline the author typed, so keeping the
        // columns they typed as well is the same promise.
        function indent(line) {
            var run = line.match(/^[ \t]+/);
            if (!run) { return line; }
            var wide = 0, i;
            for (i = 0; i < run[0].length; i++) { wide += run[0].charAt(i) === '\t' ? 4 : 1; }
            return new Array(wide + 1).join('&nbsp;') + line.slice(run[0].length);
        }
        // An ordinary line. One newline draws one break; a run of empty lines
        // draws one more and no further, so a pasted mail cannot leave a
        // screenful of nothing between two paragraphs.
        function pushLine(line) {
            var lead = plain ? (blanks ? '<br><br>' : '<br>') : '';
            blanks = 0;
            lastText = { at: out.push(lead + indent(line)) - 1, lead: lead, text: line.trim() };
            plain = true;
        }
        // Pipes only make a table once the divider row has said so. Without
        // it they are pipes - a keyboard drawing, a shell pipeline - and
        // wrapping them in <table> would invent a header nobody typed.
        function closeTable() {
            if (!table) { return; }
            var rows = table, i;
            table = null;
            if (!(rows.length > 1 && rows[1].indexOf('-') >= 0 && /^[\s|:-]+$/.test(rows[1]))) {
                rows.forEach(pushLine);
                return;
            }
            var cells = function (row) {
                return row.replace(/^\s*\|/, '').replace(/\|\s*$/, '').split('|');
            };
            // :--- left, :---: center, ---: right, as the divider row says
            var align = cells(rows[1]).map(function (c) {
                c = c.trim();
                var left = c.charAt(0) === ':', right = c.charAt(c.length - 1) === ':';
                return left && right ? 'center' : (right ? 'right' : (left ? 'left' : ''));
            });
            var at = function (n) { return align[n] ? ' style="text-align:' + align[n] + '"' : ''; };
            var t = '<table><thead><tr>';
            cells(rows[0]).forEach(function (c, n) { t += '<th' + at(n) + '>' + c.trim() + '</th>'; });
            t += '</tr></thead><tbody>';
            for (i = 2; i < rows.length; i++) {
                t += '<tr>';
                cells(rows[i]).forEach(function (c, n) { t += '<td' + at(n) + '>' + c.trim() + '</td>'; });
                t += '</tr>';
            }
            out.push(t + '</tbody></table>');
            opened();
        }
        // Four spaces or a tab, where a paragraph is not already running: the
        // other way markdown writes code, and the one a pasted stack trace
        // arrives in. Without it the indent collapses and the trace loses the
        // shape that made it readable.
        function closeCode() {
            if (!code) { return; }
            while (code.length && !code[code.length - 1].trim()) { code.pop(); }
            if (code.length) { out.push('<pre class="bl-code">' + code.join('\n') + '</pre>'); }
            code = null;
            opened();
        }
        html.split(/\r?\n/).forEach(function (line) {
            var quoted = line.match(/^\s*&gt;\s?(.*)$/);
            if (quote && !quoted) { closeQuote(); }
            if (code) {
                // A blank line is only a gap inside the block; anything that
                // has come back to the margin has left it.
                if (!line.trim()) { code.push(''); return; }
                if (/^(?: {4}|\t)/.test(line)) { code.push(line.replace(/^(?: {4}|\t)/, '')); return; }
                closeCode();
            }
            if (/^\s*\|.*\|\s*$/.test(line)) {
                // A table opening inside a list ends the list first, or the
                // rows would be emitted between the last <li> and its </ul>.
                if (!table) { closeList(); table = []; }
                table.push(line);
                return;
            }
            closeTable();
            // Empty lines are counted, not written. What they are worth is
            // decided by whatever comes next, which may be a block that
            // brings its own spacing and wants none of theirs.
            if (!line.trim()) { if (plain) { blanks++; } return; }
            // The other way markdown writes a heading: the words on one line,
            // a rule of = or - under them. Only directly under - an empty line
            // in between means the rule is a rule.
            var under = line.match(/^\s*(=+|-+)\s*$/);
            if (under && lastText && !blanks) {
                var lv = under[1].charAt(0) === '=' ? 1 : 2;
                // The heading carries its own spacing, so the break that used
                // to open this line goes with the line it opened.
                out[lastText.at] = '<h' + lv + '>' + lastText.text + '</h' + lv + '>';
                opened();
                return;
            }
            // Before the list rule, which would otherwise read "* * *" as
            // three bullets. "---" has no space after the dash, so it was
            // never a list to begin with.
            if (/^\s*(?:-{3,}|_{3,}|\*{3,})\s*$/.test(line)) { closeList(); out.push('<hr>'); opened(); return; }
            var head = line.match(/^\s*(#{1,6})\s+(.*)$/);
            if (head) {
                closeList();
                out.push('<h' + head[1].length + '>' + head[2] + '</h' + head[1].length + '>');
                opened();
                return;
            }
            // Indented code cannot interrupt a paragraph - an empty line has
            // to have ended it first - and inside a list an indent is the
            // item continuing, not code.
            if (!list && (!plain || blanks) && /^(?: {4}|\t)/.test(line)) {
                code = [line.replace(/^(?: {4}|\t)/, '')];
                return;
            }
            var ul = line.match(/^([ \t]*)[-*+]\s+(.*)$/);
            var ol = line.match(/^([ \t]*)(\d{1,9})[.)]\s+(.*)$/);
            if (ul || ol) {
                var lead = (ul || ol)[1].replace(/\t/g, '    ').length;
                listItem(ul ? 'ul' : 'ol', lead, ol ? parseInt(ol[2], 10) : 1, ul ? ul[2] : ol[3]);
                opened();
                return;
            }
            closeList();
            if (quoted) { quote = quote || []; quote.push(quoted[1]); return; }
            pushLine(line);
        });
        closeQuote();
        closeCode();
        closeList();
        closeTable();

        return out.join('');
    }
    // The same links for text that is stored as HTML - the description and
    // the wiki - where escape-then-replace cannot be reused. Walk the text
    // nodes and leave the markup alone; anything already inside a link, and
    // any key the payload cannot name, stays as it is.
    var issueRefRe = null;
    function linkifyIssueRefs(el) {
        if (!el) { return; }
        if (issueRefRe === null) {
            var key = String(D.project.key || '');
            issueRefRe = (key && key.indexOf('#') < 0)
                ? new RegExp('(' + key.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '-\\d+)', 'gi')
                : false;
        }
        if (!issueRefRe || !window.NodeFilter) { return; }
        var walker = document.createTreeWalker(el, NodeFilter.SHOW_TEXT, null, false);
        var nodes = [];
        while (walker.nextNode()) {
            var n = walker.currentNode;
            var p = n.parentNode;
            if (p && p.closest && p.closest('a,code,pre,script,style,textarea')) { continue; }
            issueRefRe.lastIndex = 0;
            if (issueRefRe.test(String(n.nodeValue))) { nodes.push(n); }
        }
        nodes.forEach(function (node) {
            var text = String(node.nodeValue);
            var frag = document.createDocumentFragment();
            var last = 0;
            var changed = false;
            var m;
            issueRefRe.lastIndex = 0;
            while ((m = issueRefRe.exec(text)) !== null) {
                var hitId = resolveIssueRef(m[1]);
                if (!hitId) { continue; }
                changed = true;
                frag.appendChild(document.createTextNode(text.slice(last, m.index)));
                var a = document.createElement('a');
                a.className = 'bl-issuelink pp-card';
                a.href = '#' + issueHash(hitId);
                a.dataset.issue = hitId;
                a.textContent = m[1];
                frag.appendChild(a);
                last = m.index + m[1].length;
            }
            if (!changed) { return; }
            frag.appendChild(document.createTextNode(text.slice(last)));
            node.parentNode.replaceChild(frag, node);
        });
    }

    /* ---- related issues: the sideways links the parent column cannot say */
    function relationsOf(id) {
        return (D.relations || []).filter(function (r) {
            return String(r.f) === String(id) || String(r.t) === String(id);
        });
    }
    // Read from the far side, "blocks" must answer as "blocked by" - the
    // inverse key rides in the payload next to each label.
    function relTypeLabel(rt, incoming) {
        var types = D.relation_types || {};
        var t = own(types, rt);
        if (!t) { return rt; }
        if (!incoming) { return t.l; }
        return (own(types, t.inv) || {}).l || t.l;
    }
    // Issues of other projects named in related rows, by id: the card
    // endpoint's answer, or false while it is being asked.
    var REL_FAR = {};
    function relFarText(id) {
        var c = REL_FAR[id];
        return c ? esc(c.key || ('#' + id)) + ' ' + esc(c.subject || '') : '#' + esc(id);
    }
    function relFarPaint(a) {
        var c = REL_FAR[a.dataset.issue];
        if (!c || !a.isConnected) { return; }
        a.innerHTML = relFarText(a.dataset.issue);
        var tx = a.parentNode;
        if (c.st_l && tx && !(tx.nextElementSibling && tx.nextElementSibling.classList.contains('pp-relfar-st'))) {
            // Its own project's status label, in the neutral colour: this
            // project's status colours do not apply to another's rows.
            var st = document.createElement('span');
            st.className = 'bl-pill pp-relfar-st';
            st.style.background = '#95a5a6';
            st.textContent = c.st_l;
            tx.insertAdjacentElement('afterend', st);
        }
    }
    function relFarFill(a) {
        var id = a.dataset.issue;
        if (REL_FAR[id]) { relFarPaint(a); return; }
        if (REL_FAR[id] === false) { return; }
        REL_FAR[id] = false;
        hcFetch('issue', id, function (res) {
            if (!res || !res.ok) { delete REL_FAR[id]; return; }
            REL_FAR[id] = res;
            Array.prototype.forEach.call(document.querySelectorAll('.pp-relfar[data-issue="' + id + '"]'), relFarPaint);
        });
    }
    // One link being added at a time. The duplicate check reads the links
    // the page holds, which only learn about this one when its answer is
    // back - so a double click used to file the same link twice.
    var relAdding = false;
    function renderRelated(i, res) {
        var box = document.getElementById('pp-d-relbox');
        if (!box) { return; }
        var rows = relationsOf(i.id);
        var canEdit = !!(D.related_table && res.editable);
        if (!rows.length && !canEdit) { box.style.display = 'none'; return; }
        // The add form costs a whole row on every issue that is read far more
        // often than it is linked; it opens from a link, and stays open for
        // the issue it was opened on.
        var relOpen = box.dataset.addFor === String(i.id);
        box.style.display = '';
        var html = rows.map(function (r) {
            var incoming = String(r.t) === String(i.id) && String(r.f) !== String(i.id);
            var otherId = incoming ? r.f : r.t;
            var other = issueById(otherId);
            var link = other
                ? '<a class="bl-issuelink pp-card" href="#issue-' + esc(keyRef(other)) + '" data-issue="' + esc(other.id) + '">' + esc(other.key) + ' ' + esc(other.subject) + '</a>'
                // An issue this project is not carrying - one that was related
                // across projects, or has since moved. All the row can say by
                // itself is the number; the card behind it says the rest.
                : '<a class="bl-issuelink pp-card pp-relfar" href="' + esc(D.urls.data_base + '/' + D.issue_table + '/' + otherId) + '" data-issue="' + esc(otherId) + '">' + relFarText(otherId) + '</a>';
            return '<div class="row"><div class="k">' + esc(relTypeLabel(r.rt, incoming)) + '</div>' +
                '<div class="v"><span class="tx">' + link + '</span>' +
                (other ? pill(other.st) : '') +
                (canEdit ? '<a href="javascript:void(0);" class="pp-rel-del" data-rel="' + esc(r.id) + '" title="' + esc(L.del) + '"><i class="fa fa-times"></i></a>' : '') +
                '</div></div>';
        }).join('');
        if (canEdit && !relOpen) {
            html += '<div class="row"><div class="k"></div><div class="v">' +
                '<a href="javascript:void(0);" class="bl-reladd-open" data-issue="' + esc(i.id) + '"><i class="fa fa-plus"></i> ' + esc(L.rel_add_open) + '</a></div></div>';
        }
        if (canEdit) {
            html += '<div class="row"' + (relOpen ? '' : ' hidden') + '><div class="k"></div><div class="v bl-reladd">' +
                '<select id="pp-rel-type">' +
                Object.keys(D.relation_types || {}).map(function (key) {
                    return '<option value="' + esc(key) + '">' + esc(D.relation_types[key].l) + '</option>';
                }).join('') +
                '</select><span id="pp-rel-pick"></span>' +
                '<button type="button" class="bl-btn-sub" id="pp-rel-add"><i class="fa fa-plus"></i> ' + esc(L.rel_add) + '</button></div></div>';
        }
        document.getElementById('pp-d-related').innerHTML = html;
        // An issue of another project: the row starts with its number and is
        // filled in with its key, subject and status from the card endpoint
        // (ISS00258) - the same answer the hover card shows.
        Array.prototype.forEach.call(box.querySelectorAll('.pp-relfar'), function (a) {
            relFarFill(a);
        });
        var relOpener = box.querySelector('.bl-reladd-open');
        if (relOpener) {
            relOpener.addEventListener('click', function () {
                box.dataset.addFor = String(i.id);
                var form = box.querySelector('.bl-reladd');
                var row = form ? form.closest('.row') : null;
                if (row) { row.hidden = false; }
                var openerRow = relOpener.closest('.row');
                if (openerRow) { openerRow.parentNode.removeChild(openerRow); }
            });
        }
        Array.prototype.forEach.call(box.querySelectorAll('.pp-rel-del'), function (btn) {
            btn.addEventListener('click', onRelDel);
        });
        if (!canEdit) { return; }
        var pick = ppIssueField({
            url: D.urls.lightapi_base + '/lookup/' + D.issue_table + '?parent_id=' + D.project.id + '&exclude=' + i.id,
            title: L.ib_title_rel,
            multiple: false
        });
        document.getElementById('pp-rel-pick').appendChild(pick.root);
        var addBtn = document.getElementById('pp-rel-add');
        addBtn.disabled = relAdding;
        addBtn.addEventListener('click', function () {
            if (relAdding) { return; }
            var to = pick.select.value;
            var rt = document.getElementById('pp-rel-type').value;
            if (!to) { return; }
            if (String(to) === String(i.id)) { toast(L.rel_self, true); return; }
            var dup = relationsOf(i.id).some(function (r) {
                return String(String(r.t) === String(i.id) ? r.f : r.t) === String(to);
            });
            if (dup) { toast(L.rel_dup, true); return; }
            var value = {};
            value[D.related_cols.f] = String(i.id);
            value[D.related_cols.t] = String(to);
            value[D.related_cols.rt] = rt;
            relAdding = true;
            addBtn.disabled = true;
            $.ajax({
                url: D.urls.lightapi_base + '/data/' + D.related_table,
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ value: value }),
                headers: { 'X-CSRF-TOKEN': CSRF }
            }).done(function (r2) {
                if (!alive()) { return; }
                if (!r2 || !r2.ok) { toast(errText(null, r2, L.rel_fail), true); return; }
                (D.relations = D.relations || []).push({ id: r2.id, f: parseInt(i.id, 10), t: parseInt(to, 10), rt: rt });
                if (String(currentIssue) === String(i.id)) { relAdding = false; renderRelated(i, res); }
                toast(L.m_saved);
            }).fail(function (xhr) {
                if (alive()) { toast(xhrErr(xhr, L.rel_fail), true); }
            }).always(function () {
                relAdding = false;
                if (addBtn.isConnected) { addBtn.disabled = false; }
            });
        });
    }
    function onRelDel(ev) {
        var btn = ev.currentTarget;
        if (btn.dataset.busy === '1') { return; }
        var relId = btn.getAttribute('data-rel');
        var issueId = currentIssue;
        btn.dataset.busy = '1';
        $.ajax({
            url: D.urls.lightapi_base + '/data/' + D.related_table + '/' + relId,
            type: 'DELETE',
            headers: { 'X-CSRF-TOKEN': CSRF }
        }).done(function (r2) {
            if (!alive()) { return; }
            if (!r2 || !r2.ok) { toast(errText(null, r2, L.rel_del_fail), true); return; }
            D.relations = (D.relations || []).filter(function (r) { return String(r.id) !== String(relId); });
            var issue = issueById(issueId);
            if (String(currentIssue) === String(issueId) && issue) {
                renderRelated(issue, DET[issueId] || localDetail(issue));
            }
            toast(L.m_saved);
        }).fail(function (xhr) {
            if (alive()) { toast(xhrErr(xhr, L.rel_del_fail), true); }
        }).always(function () { btn.dataset.busy = ''; });
    }

