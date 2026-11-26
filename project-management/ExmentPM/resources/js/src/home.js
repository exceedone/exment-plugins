    /* ------------------------------------------------ project head ------ */
    function renderHead() {
        var p = D.project;
        var people = [p.manager].concat(p.members || [], p.viewers || []).filter(function (v, i, a) {
            return v && a.indexOf(v) === i;
        });
        document.getElementById('pp-projhead').innerHTML =
            // Back to the list of projects, shaped and placed like the issue
            // detail's 課題一覧へ, so the two read as one kind of button.
            // On a phone only its arrow shows (S4).
            (D.urls.projects ? '<a class="bl-dback bl-dback-s bl-dback-proj" href="' + esc(D.urls.projects) + '" title="' + esc(L.back_projects) + '" aria-label="' + esc(L.back_projects) + '">' +
                '<i class="fa fa-angle-left"></i> <span class="tx">' + esc(L.back_projects) + '</span></a>' : '') +
            '<div class="bl-projicon"><i class="fa fa-folder-open"></i></div>' +
            // One line: name, then dates / manager / note beside it. The note
            // is cut to what fits and opens in place on a click (snoro 2026-11-18:
            // the header took a third of the screen).
            // M3: the name opens the list of projects (the top bar's), to
            // go to another one on the same tab.
            '<div class="bl-projinfo"><span class="bl-projttl"><button type="button" class="bl-projname pp-pjswitch" title="' + esc(L.pj_switch) + '" aria-haspopup="true">' +
                '<span class="nm">' + esc(p.name) + '</span><i class="fa fa-caret-down"></i></button>' +
            (p.key ? '<span class="bl-projkey">' + esc(p.key) + '</span>' : '') +
            (p.status ? '<span class="bl-pill" style="background:' + ppColor(p.status.c) + ';">' + esc(p.status.l) + '</span>' : '') +
            // ISS02931: the project's settings, where they are looked for -
            // beside its name, for the people who may change them (the same
            // rule as the 設定 tab).
            (D.can && D.can.manage ? '<a class="bl-projset" href="#settings" title="' + esc(L.proj_settings) + '" aria-label="' + esc(L.proj_settings) + '">' +
                '<i class="fa fa-cog"></i><span class="tx">' + esc(L.proj_settings) + '</span></a>' : '') +
            // S4: on a phone the dates, the manager, the note and the people
            // wait behind this button, so the head is one line.
            '<button type="button" class="bl-projmore" aria-expanded="false" title="' + esc(L.proj_info) + '" aria-label="' + esc(L.proj_info) + '"><i class="fa fa-info-circle"></i></button>' +
            '</span><div class="bl-projmeta"><span class="mi"><i class="fa fa-calendar"></i>' +
            esc(p.start || '—') + ' 〜 ' + esc(p.end || '—') + '</span>' +
            '<span class="mi"><i class="fa fa-user"></i>' + esc(L.role_pm) + ': ' + esc(user(p.manager).n) + '</span>' +
            (p.note ? '<a class="bl-projnote" href="javascript:void(0);" aria-expanded="false" title="' + esc(p.note) + '">' +
                '<i class="fa fa-info-circle"></i><span class="tx">' + esc(p.note) + '</span></a>' : '') + '</div></div>' +
            // avatars, fetched-at and the refresh button wrap as one group
            '<div class="bl-projside"><div class="bl-avatars">' + avlist(people) + '</div>' +
            // A check that finds nothing changed is silent, which reads exactly
            // like a check that never ran. The time the data on screen was
            // fetched is what tells the two apart.
            '<span class="pp-freshat">' + esc(String(L.rf_at).replace(':time', String(D.now || ''))) + '</span>' +
            '<a class="bl-btn-sub pp-refresh" href="javascript:void(0);" title="' + esc(L.rf_button) + '">' +
            '<i class="fa fa-refresh"></i> ' + esc(L.rf_button) + '</a></div>';
        var head = document.getElementById('pp-projhead');
        if (head && !head._ppHeadBound) {
            head._ppHeadBound = true;
            head.addEventListener('click', function (ev) {
                var more = ev.target.closest('.bl-projmore');
                if (more) {
                    more.setAttribute('aria-expanded', head.classList.toggle('info-open') ? 'true' : 'false');
                    return;
                }
                if (ev.target.closest('.pp-pjswitch') && window.ppTopOpenProjects) {
                    ev.preventDefault();
                    ev.stopPropagation();
                    window.ppTopOpenProjects();
                }
            });
        }
    }

    /* ------------------------------------------------ home -------------- */
    function renderHome() {
        var open = (D.issues || []).filter(function (i) { return !isDone(i.st); });
        var overdue = open.filter(function (i) { return dueOver(i); });
        var soon = open.filter(function (i) { return i.dd && !dueOver(i) && daysTo(i.dd) <= 3; });
        document.getElementById('pp-stats').innerHTML =
            '<div class="bl-stat"><div class="n">' + (D.issues.length + arCount()) + '</div><div class="l"><i class="fa fa-tasks"></i>' + L.stat_all + '</div></div>' +
            '<div class="bl-stat"><div class="n">' + open.length + '</div><div class="l"><i class="fa fa-folder-open-o"></i>' + L.stat_open + '</div></div>' +
            '<div class="bl-stat' + (overdue.length ? ' warn' : '') + '"><div class="n">' + overdue.length + '</div><div class="l"><i class="fa fa-exclamation-circle"></i>' + L.stat_overdue + '</div></div>' +
            '<div class="bl-stat"><div class="n">' + soon.length + '</div><div class="l"><i class="fa fa-clock-o"></i>' + L.stat_soon + '</div></div>';

        var stack = '', leg = '';
        (D.statuses || []).forEach(function (s) {
            var n = D.issues.filter(function (i) { return i.st === s.k; }).length + arIn('by_status', s.k);
            if (n) { stack += '<i style="flex:' + n + ';background:' + ppColor(s.c) + '" title="' + esc(s.l) + ' ' + n + '"></i>'; }
            leg += '<span><span class="sw" style="background:' + ppColor(s.c) + '"></span>' + esc(s.l) + ' <b>' + n + '</b>' + L.unit_cnt + '</span>';
        });
        document.getElementById('pp-stack').innerHTML = stack;
        document.getElementById('pp-leg').innerHTML = leg;

        var ms = '';
        Object.keys(D.milestones || {}).forEach(function (k) {
            var m = D.milestones[k];
            var all = D.issues.filter(function (i) { return i.ms === k; });
            // Archived issues are finished ones.
            var arN = arIn('by_ms', k);
            if (!all.length && !arN) { return; }
            var done = all.filter(function (i) { return isDone(i.st); });
            done = { length: done.length + arN };
            all = { length: all.length + arN };
            var pct = Math.round(done.length * 100 / all.length);
            var left = m.due ? daysTo(m.due) : null;
            ms += '<div class="row"><span class="nm"><i class="fa fa-flag-checkered" style="color:#8e44ad;margin-right:6px;"></i>' + esc(m.l) + '</span>' +
                '<span class="bl-pbar"><i style="width:' + pct + '%"></i></span>' +
                '<span class="cnt">' + done.length + '/' + all.length + esc(L.unit_cnt) +
                (left !== null ? esc(L.sep_dot + fmt(L.ms_left, left)) : '') + '</span></div>';
        });
        document.getElementById('pp-ms').innerHTML = ms || ('<div style="color:var(--bl-faint);">' + esc(L.empty) + '</div>');

        var p = D.project;
        var members = '<div class="bl-member">' + avname(p.manager) + '<span class="role">' + L.role_pm + '</span></div>';
        (p.members || []).forEach(function (u) {
            if (u === p.manager) { return; }
            members += '<div class="bl-member">' + avname(u) + '<span class="role">' + L.role_member + '</span></div>';
        });
        (p.viewers || []).forEach(function (u) {
            members += '<div class="bl-member">' + avname(u) + '<span class="role">' + L.role_viewer + '</span></div>';
        });
        document.getElementById('pp-members').innerHTML = members;

        document.getElementById('pp-feed').innerHTML = (D.feed || []).map(function (f) {
            var ic = own(FEED_IC, f.tp) || FEED_IC.chg;
            var link;
            if (f.kind === 'issue') {
                link = '<a class="bl-issuelink" href="#' + esc(issueHash(f.id)) + '">' + esc(f.key) + ' ' + esc(f.label) + '</a>';
            } else if (f.kind === 'wiki') {
                link = '<a class="bl-issuelink pp-wikilink" href="#wiki" data-wiki="' + esc(f.id) + '">' + esc(f.label) + '</a>';
            } else if (f.kind === 'file') {
                link = '<a class="bl-issuelink" href="#files">' + esc(f.label) + '</a>';
            } else {
                link = esc(f.label);
            }
            var verb = own({ add: L.feed_add, chg: L.feed_chg, cmt: L.feed_cmt, file: L.feed_file, wiki: L.feed_wiki, wiki_add: L.feed_wiki_add }, f.tp) || L.feed_chg;
            if (f.kind === 'project') { verb = L.feed_project; }
            return '<div class="item"><span class="ic" style="background:' + ppColor(ic.c) + '"><i class="fa ' + ic.i + '"></i></span>' +
                '<div><div class="tx"><span class="who">' + esc(user(f.by).n) + '</span> ' + fmt(esc(verb), link) +
                (f.extra ? '<span class="ex">' + esc(f.extra) + '</span>' : '') + '</div>' +
                '<div class="tm"><i class="fa fa-clock-o"></i> ' + esc(f.at) + '</div></div></div>';
        }).join('') || ('<div style="color:var(--bl-faint);">' + esc(L.empty) + '</div>');
    }

    /* ------------------------------------------------ archive ----------- */
    // ISS00332: issues finished more than D.archive.days days ago are not in
    // the page. D.archive counts them (per status, milestone, parent) so the
    // numbers still cover the whole project; the rows come on request.
    var AR = { loaded: false, show: false, busy: null };
    function arOn() { return !!(D.archive && D.archive.count > 0); }
    // What D.issues is missing: nothing once the rows are here.
    function arCount() { return arOn() && !AR.loaded ? Number(D.archive.count) || 0 : 0; }
    function arIn(map, k) { return arOn() && !AR.loaded ? Number((D.archive[map] || {})[String(k)] || 0) : 0; }
    function loadArchived() {
        if (!arOn() || AR.loaded) { return $.Deferred().resolve().promise(); }
        if (AR.busy) { return AR.busy; }
        AR.busy = $.getJSON(D.archive.url).then(function (res) {
            if (!alive() || !res || !res.ok) { return; }
            $.extend(D.users, res.users || {});
            var have = {};
            (D.issues || []).forEach(function (i) { have[String(i.id)] = true; });
            (res.issues || []).forEach(function (r) {
                if (!have[String(r.id)]) { r.ar = true; D.issues.push(r); }
            });
            AR.loaded = true;
        }).always(function () { AR.busy = null; });

        return AR.busy;
    }
    function showArchived(on) {
        AR.show = !!on;
        if (!on) { PAGE.no = 1; renderIssues(); return; }
        var note = document.getElementById('pp-ar-note');
        if (note) { note.textContent = L.ar_loading; }
        loadArchived().then(function () {
            if (!alive()) { return; }
            PAGE.no = 1;
            renderIssues();
        }, function () { toast(L.ar_fail, true); renderIssues(); });
    }

