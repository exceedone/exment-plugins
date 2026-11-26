    /* ------------------------------------------------ writes ------------ */
    // One button, two writes, in the order Backlog puts them in: the fields
    // first, then the comment that explains them.
    //
    // The fields go through the row API, which already checks permission,
    // whitelists columns and runs the table's own validation - so this screen
    // adds no second, weaker copy of those rules. The status stays on the
    // comment endpoint because changing it is what notifies people, and that
    // pipeline lives there.
    function postComment(statusOnly) {
        if (cmtBusy || currentIssue === null || cmtPending[currentIssue]) { return; }
        var issueId = currentIssue;
        var epoch = detailEpoch;
        var base = D.urls.base;
        var submittedBox = document.getElementById('pp-cmt-text');
        var text = statusOnly ? '' : submittedBox.value;
        var statusEl = document.getElementById('pp-cmt-status');
        var statusTo = statusOnly || (statusEl ? statusEl.value : '');
        var ours = QE.issue !== null && String(QE.issue) === String(issueId);
        // The status the reader was looking at when they picked the next one.
        // The server refuses the move when somebody changed it in between,
        // rather than walking the issue back to where this screen thought it
        // still stood - which the "next status" button above all would do.
        var statusFrom = ours ? QE.st : null;
        var changed = (statusOnly || !QE.editable) ? {} : qeChanged();
        var diff = qeColumns(changed);
        // The version the dock was drawn from: a save over somebody else's
        // newer one is refused instead of written.
        var version = ours ? QE.ua : null;
        var hasText = String(text).trim() !== '';
        var hasFields = Object.keys(diff).length > 0;
        if (!hasText && !statusTo && !hasFields) {
            // Nothing typed, nothing picked, nothing changed: the comment
            // endpoint would only refuse it. Said here, and nothing in the
            // dock is touched.
            ppDockMsg(L.dock_empty, true);
            return;
        }
        // ISS00341: a review request asks the picked people about this
        // comment, so it needs both. Said before anything is sent - the
        // server would post the comment and quietly ask nobody. The "next
        // status" button sends no comment and so asks nobody.
        if (String(text).length > maxBody()) {
            ppDockMsg(tooLongText(maxBody()), true);
            return;
        }
        var review = !statusOnly && rvAskOn();
        if (review && !document.querySelector('#pp-notify-chips .pp-notify:not(.off)')) {
            ppDockMsg(L.rv_need_people, true);
            return;
        }
        if (review && !hasText) {
            ppDockMsg(L.rv_need_text, true);
            return;
        }
        var label = (issueById(issueId) || {}).key || '';
        var sequence = ++cmtSequence;
        cmtPending[issueId] = sequence;
        if (!statusOnly) {
            submittedBox._ppCmtOwner = sequence;
            // Kept for the life of the tab until the server has it: leaving
            // the issue empties the box, and a post that then fails has nobody
            // on screen to hand the text back to.
            if (hasText) { dockDraftSave(issueId, text); }
        }
        var notifyUsers = $('#pp-notify-chips .pp-notify:not(.off)').map(function () { return $(this).data('user'); }).get();
        var mailBox = document.getElementById('pp-notify-mail');
        var notifyMail = !!(mailBox && mailBox.checked);
        var current = function () { return detailCurrent(issueId, epoch); };
        var settled = function () {
            if (cmtPending[issueId] === sequence) { delete cmtPending[issueId]; }
            if (detailCurrent(issueId)) { cmtSetBusy(!!cmtPending[issueId]); }
        };
        // Said whether or not the reader is still on this issue: a failure
        // nobody hears about is a comment its writer believes was posted. The
        // text itself waits in the issue's draft for them to come back to.
        var failed = function (message) {
            settled();
            if (!current()) {
                toast((label ? label + ': ' : '') + message, true);
                return;
            }
            ppDockMsg(message, true);
            toast(message, true);
        };
        // Somebody else saved the issue first. Nothing of the reader's is
        // thrown away: the issue is drawn again with what changed - the fields
        // they moved and the status and people they picked are carried across
        // the redraw - and the text stays in the box to be sent again.
        var stale = function (fresh, seq) {
            settled();
            if (!current()) {
                toast((label ? label + ': ' : '') + L.dock_stale_keep, true);
                return;
            }
            toast(L.dock_stale_keep, true);
            if (fresh && fresh.issue && String(fresh.issue.id) === String(issueId)) {
                applyDetail($.extend({}, fresh, { ok: true }), undefined, 'force', seq);
            } else {
                reloadIssue(issueId, undefined, true);
            }
            ppDockMsg(L.dock_stale_keep, true);
        };
        cmtSetBusy(true);
        ppDockMsg('', false);

        var send = function () {
            var seq = ++DET_SEQ;
            var data = { comment: text, status_to: statusTo || '', notify_users: notifyUsers };
            if (notifyMail) { data.notify_mail = 1; }
            if (review) { data.review = 1; }
            if (statusTo && statusFrom !== null) { data.status_from = statusFrom; }
            $.ajax({
                url: base + '/issue/' + issueId + '/comment',
                type: 'POST',
                dataType: 'json',
                // as JSON: a Japanese character is 3 bytes there, 9 in a form (nginx stops at 1 MB)
                contentType: 'application/json',
                processData: false,
                data: JSON.stringify(data),
                headers: { 'X-CSRF-TOKEN': CSRF }
            }).done(function (res) {
                if (!res || !res.ok || !res.issue || String(res.issue.id) !== String(issueId)) {
                    failed(errText(null, res, L.post_fail));
                    return;
                }
                settled();
                dockDraftClear(issueId, text);
                applyDetail(res, epoch, false, seq);
                if (!statusOnly && alive() && String(currentIssue) === String(issueId)
                    && submittedBox === document.getElementById('pp-cmt-text')
                    && submittedBox._ppCmtOwner === sequence && submittedBox.value === text) {
                    submittedBox.value = '';
                    submittedBox._ppCmtOwner = null;
                }
                if (!current()) { return; }
                toast(L.posted);
                ppDockMsg('', false);
                var box = document.getElementById('pp-cmt-text');
                if (!box.value) { cmtPreviewOff(); }
                // What was sent is spent: the status picked and the people told
                // went with this comment, not with the next one. A field moved
                // while it was on its way is carried across the redraw.
                var stNow = document.getElementById('pp-cmt-status');
                if (stNow && statusTo && stNow.value === String(statusTo)) { stNow.value = ''; }
                document.querySelectorAll('#pp-notify-chips .pp-notify').forEach(function (c) { c.classList.add('off'); });
                var mailNow = document.getElementById('pp-notify-mail');
                if (mailNow) { mailNow.checked = false; }
                if (!statusOnly) { rvAskSync(true); }
                buildDetail(own(DET, String(issueId)) || res);
                if (!box.value.trim()) { ppDockOpen(false); }
                if (statusTo) {
                    setTimeout(function () {
                        if (current() && safeToRefresh()) { softReload(); }
                    }, 500);
                }
            }).fail(function (xhr) {
                var body = xhrBody(xhr);
                if (xhr && xhr.status === 409 && body && body.error === 'stale') { stale(body, seq); return; }
                failed(xhrErr(xhr, L.post_fail));
            });
        };

        // No field moved: straight to the comment.
        if (!hasFields) { send(); return; }

        ppDockMsg(L.dock_saving, false);
        qeSaveFields(issueId, changed, version).then(function (r) {
            if (r.ok) {
                if (hasText || statusTo) { send(); return; }
                // Fields alone: there is nothing to say, so nothing is posted.
                // The issue is read again for the history line the save made.
                settled();
                if (current()) { toast(L.m_saved); ppDockMsg('', false); }
                reloadIssue(issueId, epoch, false);
                return;
            }
            if (r.stale) { stale(null, null); return; }
            failed(r.error);
        }).catch(function () {
            failed(errText(null, null, L.dock_save_fail));
        });
    }
    // Write some fields of one issue through the row API - the dock's save,
    // and the pencils on the issue's own side (qfOpen). changed holds the
    // short keys of QE_COLS; version is the updated_at the screen was drawn
    // from, so a save over somebody else's newer one is refused. Answers
    // { ok } / { stale } / { error: text }, never rejects.
    function qeSaveFields(issueId, changed, version) {
        var table = D.issue_table;
        var fieldsUrl = (D.urls && D.urls.lightapi_base) + '/data/' + table + '/' + issueId;
        var patchSeq = ++DET_SEQ;
        var patch = { value: qeColumns(changed) };
        if (version) { patch.updated_at = version; }
        return fetch(fieldsUrl, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
            credentials: 'same-origin',
            body: JSON.stringify(patch)
        }).then(ppJson, ppNoAnswer).then(function (res) {
            if (res.status === 200 && res.body && res.body.ok) {
                var ua = qeAdopt(issueId, changed, res.body, patchSeq);
                if (QE.issue !== null && String(QE.issue) === String(issueId)) {
                    // Sent: these fields now say what the row says, and are no
                    // longer anybody's unsent work - unless they were moved
                    // again while the save was on its way.
                    var nowVals = qeRead();
                    Object.keys(changed).forEach(function (k) {
                        QE.base[k] = changed[k];
                        if (qeSame(nowVals[k], changed[k])) { delete QE.touched[k]; }
                    });
                    if (ua) { QE.ua = ua; }
                    qeMark();
                }
                delete NED.editFormCache[table + '::' + issueId];
                return { ok: true };
            }
            if (res.status === 409 && res.body && res.body.error === 'stale') { return { stale: true }; }
            return { error: errText(res.status, res.body, L.dock_save_fail) };
        }).catch(function () {
            return { error: errText(null, null, L.dock_save_fail) };
        });
    }
    // The row as the list holds it, brought up to what a save just wrote -
    // and to the version the server now has, which is the one the next save
    // from this screen has to quote or be refused as stale.
    function qeAdopt(issueId, changed, answer, seq) {
        var row = (answer && answer.row && typeof answer.row === 'object') ? answer.row : null;
        var ua = (answer && (answer.updated_at || (row && row.updated_at))) || null;
        var rows = [];
        var listed = issueById(issueId);
        if (listed) { rows.push(listed); }
        var det = own(DET, String(issueId));
        if (det && det.issue && rows.indexOf(det.issue) < 0) { rows.push(det.issue); }
        // An answer that left before this save is older than what it wrote.
        if (det && typeof seq === 'number' && !(det._seq > seq)) { det._seq = seq; }
        rows.forEach(function (issue) {
            Object.keys(QE_COLS).forEach(function (key) {
                var col = qeCol(QE_COLS[key]);
                if (row && col && Object.prototype.hasOwnProperty.call(row, col)) {
                    issue[key] = qeRowValue(key, row[col]);
                } else if (Object.prototype.hasOwnProperty.call(changed, key)) {
                    issue[key] = qeRowValue(key, changed[key]);
                }
            });
            if (ua) { issue.ua = ua; }
        });

        return ua;
    }
    // The same for a save made in the form, which can move any column: the
    // row the list holds takes the saved values straight from the answer, so
    // the list is right the moment the form is left - without waiting for the
    // project to be read again, and also when it never is (the form was left
    // before its save came back). No request: the answer already has the row.
    var FORM_COLS = { subject: 'subject', ty: 'type', st: 'status' };
    function formAdopt(issueId, answer) {
        var row = (answer && answer.row && typeof answer.row === 'object') ? answer.row : null;
        var issue = issueById(issueId);
        if (!row || !issue) { return; }
        qeAdopt(issueId, {}, answer);
        Object.keys(FORM_COLS).forEach(function (key) {
            var col = qeCol(FORM_COLS[key]);
            if (col && Object.prototype.hasOwnProperty.call(row, col)) { issue[key] = qeRowValue(key, row[col]); }
        });
        if (Object.prototype.hasOwnProperty.call(row, 'subject_sub')) { issue.sub = qeRowValue('sub', row.subject_sub); }
        var parentCol = qeCol('parent');
        if (parentCol && Object.prototype.hasOwnProperty.call(row, parentCol)) {
            issue.parent = parseInt(row[parentCol], 10) || null;
        }
        // The shown time: what the server formatted for this user, or - on
        // a server that does not say - the stored time cut short, which is
        // right only while the user reads the time zone of the site.
        var shown = (answer && answer.updated_at_shown) || (D.tz_differs ? '' : issue.ua);
        if (shown) { issue.up = String(shown).slice(0, 16).replace(/-/g, '/'); }
        // The list is already on screen (the form was left while the save was
        // on its way): draw it again, unless a cell of it is being edited.
        if (currentScreen === 'issues' && safeToRefresh() && !document.querySelector('#pp-issue-rows .pp-ed-in')) {
            renderIssues();
        }
    }
    // A stored value in the shape the list rows carry it (buildIssueRow).
    function qeRowValue(key, v) {
        var list = Array.isArray(v) ? v : ((v === null || v === undefined || v === '') ? [] : [v]);
        if (key === 'as' || key === 'sa' || key === 'mg' || key === 'rv' || key === 'nu') {
            return list.map(Number).filter(function (n) { return isFinite(n) && n > 0; });
        }
        if (key === 'cat') { return list.map(String); }
        if (key === 'sd' || key === 'dd') {
            var day = String(v == null ? '' : v).slice(0, 10);
            return /^\d{4}-\d{2}-\d{2}$/.test(day) ? day : null;
        }
        if (key === 'dt') {
            var hm = /^(\d{1,2}):(\d{2})/.exec(String(v == null ? '' : v));
            var t = hm ? ('0' + hm[1]).slice(-2) + ':' + hm[2] : '';
            // 00:00 is no time (the picker starts there): see clearMidnightDueTime.
            return t === '00:00' ? '' : t;
        }
        if (key === 'pg') {
            return (v === null || v === undefined || v === '' || !isFinite(Number(v))) ? null : Number(v);
        }
        if (key === 'eh' || key === 'ah') { return (v === undefined || v === '') ? null : v; }

        return (v === null || v === undefined) ? '' : String(v);
    }
    // One issue read again and folded back in, for a screen that knows its
    // copy is behind: a save that left a line in the history, or a save
    // refused because somebody else got there first (force: drawn even with
    // work in the dock, which the redraw carries across).
    function reloadIssue(issueId, epoch, force) {
        if (!(D.urls && D.urls.base)) { return; }
        var seq = ++DET_SEQ;
        ppQueue(function () {
            return $.getJSON(D.urls.base + '/issue/' + issueId);
        }).then(function (res) {
            if (!alive() || !res || !res.ok || !res.issue) { return; }
            applyDetail(res, epoch, force ? 'force' : true, seq);
        }).catch(function () { /* the copy on screen stands */ });
    }
    // The comment being written, per issue, for the life of the tab. Moving
    // to another issue empties the box, and a post that fails after its
    // writer has moved on has no box to go back into - so it waits here, and
    // the box shows it again when they return to the issue.
    function dockDraftKey(issueId) {
        return 'pmportal.dock.u' + String(D.me || 0) + '.p' + String((D.project && D.project.id) || 0) + '.' + String(issueId);
    }
    function dockDraftSave(issueId, text) {
        if (issueId === null || issueId === undefined) { return; }
        try {
            if (String(text || '').trim() === '') { sessionStorage.removeItem(dockDraftKey(issueId)); return; }
            sessionStorage.setItem(dockDraftKey(issueId), String(text));
        } catch (e) { /* private mode or full: the box still holds it */ }
    }
    function dockDraftRead(issueId) {
        try { return sessionStorage.getItem(dockDraftKey(issueId)) || ''; } catch (e) { return ''; }
    }
    // text: drop it only while it still says what was sent - newer words
    // typed for the same issue are somebody's next comment.
    function dockDraftClear(issueId, text) {
        try {
            var key = dockDraftKey(issueId);
            if (text === undefined || sessionStorage.getItem(key) === String(text)) { sessionStorage.removeItem(key); }
        } catch (e) { /* nothing stored */ }
    }
    function ppDockMsg(text, isError) {
        var el = document.getElementById('pp-dockmsg');
        if (!el) { return; }
        el.textContent = text || '';
        el.classList.toggle('err', !!isError);
    }

