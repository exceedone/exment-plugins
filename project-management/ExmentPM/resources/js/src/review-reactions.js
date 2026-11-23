    /* ------------------------------------------------ review request ---- */
    // ISS00341: 「確認をお願いします」 with an answer. Under the comment, on one
    // line: everybody who was asked, 確認待ち or 確認済み with the time, and -
    // for the asked reader only - the button that answers. The server finds
    // the row by who is asking, so the button carries no user id.
    function rvBar(e) {
        if (!e.rv || !e.rv.length) { return ''; }
        var mine = null;
        var chips = e.rv.map(function (r) {
            if (String(r.by) === String(D.me)) { mine = r; }
            return '<span class="pp-rvp ' + (r.ok ? 'ok' : 'wait') + '" title="' +
                esc(user(r.by).n + ': ' + (r.ok ? L.rv_ok + (r.at ? ' ' + r.at : '') : L.rv_wait)) + '">' +
                av(r.by) + esc(user(r.by).n) +
                '<span class="s">' + (r.ok ? '<i class="fa fa-check"></i> ' + esc(L.rv_ok) : esc(L.rv_wait)) + '</span>' +
                (r.ok && r.at ? '<span class="tm">' + esc(r.at) + '</span>' : '') + '</span>';
        }).join('');
        var btn = '';
        if (mine && e.rv_on) {
            btn = mine.ok
                ? '<button type="button" class="pp-rvbtn undo" data-cid="' + esc(e.cid) + '" data-ok="0" title="' + esc(L.rv_undo_tip) + '">' + esc(L.rv_undo) + '</button>'
                : '<button type="button" class="pp-rvbtn" data-cid="' + esc(e.cid) + '" data-ok="1" title="' + esc(L.rv_confirm_tip) + '"><i class="fa fa-check"></i> ' + esc(L.rv_confirm) + '</button>';
        }
        return '<div class="pp-rv"><span class="lb"><i class="fa fa-check-square-o"></i> ' + esc(L.rv_label) + '</span>' + chips + btn + '</div>';
    }
    // Whether an issue's detail still holds a request this reader has not
    // answered - what the list's 確認待ち chip counts.
    function rvPending(res) {
        return (res.timeline || []).some(function (e) {
            return e.tp === 'cmt' && (e.rv || []).some(function (r) { return !r.ok && String(r.by) === String(D.me); });
        });
    }
    function rvAnswer(btn) {
        if (btn.disabled || currentIssue === null) { return; }
        var issueId = currentIssue, epoch = detailEpoch;
        var ok = btn.getAttribute('data-ok') === '1';
        var seq = ++DET_SEQ;
        btn.disabled = true;
        $.ajax({
            url: D.urls.base + '/comment/' + btn.getAttribute('data-cid') + '/review', type: 'POST',
            dataType: 'json',
            data: { ok: ok ? 1 : 0 },
            headers: { 'X-CSRF-TOKEN': CSRF }
        }).done(function (res) {
            btn.disabled = false;
            if (!res || !res.ok || !res.issue || String(res.issue.id) !== String(issueId)) {
                if (detailCurrent(issueId, epoch)) { toast(errText(null, res, L.rv_fail), true); }
                return;
            }
            // The answer is the whole issue again: the line under the comment
            // and the list's 確認待ち mark both come from it (applyDetail).
            applyDetail(res, epoch, true, seq);
            if (detailCurrent(issueId, epoch)) { toast(ok ? L.rv_done : L.rv_undone); }
        }).fail(function (xhr) {
            btn.disabled = false;
            if (!detailCurrent(issueId, epoch)) { return; }
            toast(xhrErr(xhr, L.rv_fail), true);
        });
    }
    // 確認を依頼する in the dock: whether it is ticked, and the tick shown on
    // the picked chips while it is.
    function rvAskOn() {
        var box = document.getElementById('pp-notify-rv');
        return !!(box && box.checked);
    }
    function rvAskSync(off) {
        var box = document.getElementById('pp-notify-rv');
        if (box && off) { box.checked = false; }
        var chips = document.getElementById('pp-notify-chips');
        if (chips) { chips.classList.toggle('rv', rvAskOn()); }
    }
    // Delegated: the tick is part of the page, but this script may be drawn
    // before or after it.
    document.addEventListener('change', function (ev) {
        if (ev.target && ev.target.id === 'pp-notify-rv') { rvAskSync(); }
    });
    /* ------------------------------------------------ reactions -------- */
    // ISS00259: Facebook's reactions plus the ones Slack and Teams use at
    // work. A person may put several kinds on one thing; each chip turns
    // the reader's own on and off. RXS holds what each bar shows, so a bar
    // can be drawn again after a click without the whole detail.
    var RX = [
        ['like', '\uD83D\uDC4D'], ['heart', '\u2764\uFE0F'], ['haha', '\uD83D\uDE06'], ['wow', '\uD83D\uDE2E'],
        ['sad', '\uD83D\uDE22'], ['angry', '\uD83D\uDE20'], ['thanks', '\uD83D\uDE4F'], ['clap', '\uD83D\uDC4F'],
        ['party', '\uD83C\uDF89'], ['eyes', '\uD83D\uDC40'], ['ok', '\u2705']
    ];
    var RXS = {};
    function rxBar(targetType, targetId, rx) {
        var key = targetType + ':' + targetId;
        RXS[key] = rx && typeof rx === 'object' ? rx : {};
        var chips = RX.filter(function (k) { return (RXS[key][k[0]] || []).length > 0; }).map(function (k) {
            var us = RXS[key][k[0]];
            var mine = us.map(String).indexOf(String(D.me)) >= 0;
            var names = us.map(function (u) { return user(u).n; }).join('\u3001');
            return '<span class="pp-rx' + (mine ? ' on' : '') + '" data-rx="' + k[0] + '" title="' + esc(L['rx_' + k[0]] + ': ' + names) + '">' +
                k[1] + '<span class="n">' + us.length + '</span></span>';
        }).join('');
        return '<span class="pp-rxbar" data-target-type="' + esc(targetType) + '" data-target-id="' + esc(targetId) + '">' + chips +
            // A word beside the face: the bare "☺+" was missed (snoro 2026-11-19).
            '<span class="pp-rxadd" title="' + esc(L.rx_add) + '"><i class="fa fa-smile-o"></i> ' + esc(L.rx_add) + '</span>' +
            '<span class="pp-rxpick" hidden>' + RX.map(function (k) {
                return '<span class="pp-rxopt" data-rx="' + k[0] + '" title="' + esc(L['rx_' + k[0]]) + '">' + k[1] + '</span>';
            }).join('') + '</span></span>';
    }
    function rxToggle(bar, kind) {
        if (!bar || bar.dataset.busy === '1') { return; }
        var tt = bar.dataset.targetType, tid = bar.dataset.targetId;
        bar.dataset.busy = '1';
        $.ajax({
            url: D.urls.base + '/star', type: 'POST', dataType: 'json',
            data: { star_type: 'r:' + kind, target_type: tt, target_id: tid },
            headers: { 'X-CSRF-TOKEN': CSRF }
        }).done(function (res) {
            bar.dataset.busy = '';
            if (!res || !res.ok) { toast(errText(null, res, L.rx_fail), true); return; }
            var rx = $.extend({}, RXS[tt + ':' + tid] || {});
            if (res.users && res.users.length) { rx[kind] = res.users; } else { delete rx[kind]; }
            var tmp = document.createElement('span');
            tmp.innerHTML = rxBar(tt, tid, rx);
            if (bar.parentNode) { bar.parentNode.replaceChild(tmp.firstChild, bar); }
        }).fail(function (xhr) {
            bar.dataset.busy = '';
            toast(xhrErr(xhr, L.rx_fail), true);
        });
    }
    // ★ on the issue detail: a labelled button beside ウォッチ (Fable's review,
    // snoro 2026-11-19). ★ is 注目度 - how many people care - counted, not
    // named; the reactions are where people are named.
    function starDetailBtn(issueId, count, on) {
        return '<a class="bl-btn-sub pp-star pp-star-d' + (on ? ' on' : '') + '" title="' + esc(on ? L.star_off_tip : L.star_on_tip) + '"' +
            ' data-star-type="star" data-target-type="' + esc(D.issue_table) + '" data-target-id="' + esc(issueId) + '">' +
            '<i class="fa ' + (on ? 'fa-star' : 'fa-star-o') + '"></i> <span class="lb">' + esc(on ? L.star_on : L.star_label) + '</span>' +
            ' <span class="ct">' + esc(count || 0) + '</span></a>';
    }
    function issueById(id) {
        var found = null;
        (D.issues || []).forEach(function (i) { if (String(i.id) === String(id)) { found = i; } });
        return found;
    }
    var toastTimer = null;
    function toast(message, danger) {
        var el = document.getElementById('pp-toast');
        if (!el) {
            el = document.createElement('div');
            el.id = 'pp-toast';
            document.body.appendChild(el);
        }
        el.className = danger ? 'danger' : '';
        el.textContent = message;
        el.style.display = 'block';
        if (toastTimer) { clearTimeout(toastTimer); }
        toastTimer = setTimeout(function () { el.style.display = 'none'; }, 2600);
    }

