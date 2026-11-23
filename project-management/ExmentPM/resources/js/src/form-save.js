    // Update the save button label with a live "(N)" counter of changed
    // fields. Only meaningful in edit mode; create mode always says "保存".
    function nedUpdateChangeCount() {
        var neMail = NED.el.querySelector('.pp-ne-mail');
        if (neMail) {
            var mailOn = NED.mode === 'create' && NED.table === D.issue_table;
            if (neMail.hidden === mailOn) {
                neMail.hidden = !mailOn;
                if (!mailOn) { neMail.querySelector('input').checked = false; }
            }
        }
        var labels = NED.el.querySelectorAll('.pp-ne-save-label');
        if (!labels.length) { return; }
        var base = NED.mode === 'create'
            ? nedGetLangKey('ned_save')
            : nedGetLangKey('ned_save');
        var text = base;
        if (NED.mode !== 'create' && NED.canEdit && NED.columns) {
            var diff = nedBuildDiff();
            var n = Object.keys(diff).length;
            if (n > 0) { text = base + ' (' + n + ')'; }
        }
        labels.forEach(function (l) { l.textContent = text; });
    }
    function nedFocusFirstField() {
        var epoch = NED.epoch;
        setTimeout(function () {
            if (!nedCurrent(epoch)) { return; }
            var pref = NED.el.querySelector('.pp-ne-fg-title .pp-ne-in:not([readonly]):not([disabled])');
            // :not([hidden]) keeps the focus off a chip picker's value
            // carrier, which is a real <select> the user never sees.
            var any = pref || NED.el.querySelector('.pp-ne-form .pp-ne-in:not([readonly]):not([disabled]):not([type=hidden]):not([hidden])');
            if (any && typeof any.focus === 'function') {
                try { any.focus({ preventScroll: true }); } catch (e) { any.focus(); }
                if (any.tagName === 'INPUT' && any.type === 'text') {
                    try { any.select(); } catch (e) { /* ignore */ }
                }
            }
        }, 40);
    }

    // Same-tab per-row edit payload fetch with cache + in-flight dedup.
    // Mirrors nedFetchCreateForm but keyed by "<table>::<id>". Callers
    // pass forceRefresh=true to bypass the cache for background
    // revalidation after a stale-hit render.
    function nedFetchEditForm(table, id, forceRefresh, low) {
        var key = table + '::' + id;
        if (!forceRefresh && NED.editFormCache[key]) { return Promise.resolve(NED.editFormCache[key]); }
        if (NED.editFormInflight[key]) {
            // A prewarm queued this a moment ago. Someone is waiting for it
            // now, so it must not stay behind the rest of the slow lane.
            if (!low) { ppQueue.promote(NED.editFormInflight[key]); }
            return NED.editFormInflight[key];
        }
        var url = (D.urls && D.urls.lightapi_base) + '/data/' + table + '/' + id + '/edit';
        var q = ppQueue(function () { return nedGetForm(url); }, low);
        var p = q
            .then(function (j) { NED.editFormCache[key] = j; NED.editFormAt[key] = Date.now(); return j; })
            .finally(function () { delete NED.editFormInflight[key]; });
        p.ppJob = q.ppJob;
        NED.editFormInflight[key] = p;
        return p;
    }
    // A form payload - or a refusal, thrown as the {status, body} pair
    // errText words: a 403 the portal explained, a proxy's error page, no
    // answer at all. Never the browser's own exception text.
    function nedGetForm(url) {
        return fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
            .then(ppJson, ppNoAnswer)
            .then(function (res) {
                if (res.status >= 200 && res.status < 300 && res.body && typeof res.body === 'object') { return res.body; }
                throw res;
            });
    }
    // The sentence for a form that could not be opened.
    function nedFormError(e) {
        return errText(e && typeof e.status === 'number' ? e.status : null, e && e.body, nedGetLangKey('ned_error'));
    }
    // Whether a cached form is recent enough to open without a round trip.
    function nedFormFresh(table, id, maxAgeMs) {
        var at = NED.editFormAt[table + '::' + id];
        return !!at && (Date.now() - at) < (maxAgeMs || 90000);
    }
    // The settings pane reads the same payload from the same endpoint;
    // sharing this cache is what turns its two requests into one.
    window.ppFetchEditForm = nedFetchEditForm;
    function nedPrewarmEditForm(table, id) {
        // Fire-and-forget on hover / mousedown of an edit link so the
        // click sees a hot promise (or a cache hit) instead of a cold
        // network round-trip.
        if (!NED.el || !table || !id) { return; }
        nedFetchEditForm(table, id, false, true).catch(function () { });
    }

    // The cached form is a photograph, and the row may have moved since it
    // was taken. It is still shown at once - the reader gets their form with
    // no wait - but the real one is fetched straight away, and when it comes
    // back different the form is corrected underneath them.
    function nedRevalidateForm(table, id) {
        var epoch = NED.epoch;
        var version = NED.rowUpdatedAt;
        nedFetchEditForm(table, id, true, true).then(function (j) {
            // They may have closed it, or moved on to another row, in the
            // time this took.
            if (!nedCurrent(epoch) || NED.busy || NED.rowUpdatedAt !== version) { return; }
            if (NED.mode !== 'edit' || NED.table !== table) { return; }
            if (String(NED.rowId) !== String(id)) { return; }
            var fresh = (j.row && j.row.updated_at) || null;
            if (!fresh || String(fresh) === String(NED.rowUpdatedAt)) { return; }
            // Anything typed outranks the news: redrawing would throw their
            // work away to deliver it. They are told instead, with the same
            // two ways out that a refused save offers.
            if (nedDirty()) {
                nedShowConflict({ updated_at: fresh, updated_by: (j.row && j.row.updated_by) || null });
                return;
            }
            nedRenderForm(j);
            nedShowAlert(nedGetLangKey('ned_reloaded'), true);
        }).catch(function () { /* the cached form stands */ });
    }

    function openNedit(table, id) {
        if (!NED.el) { return; }
        // Preserve back-compat with the old openNedit(id) 1-arg signature.
        if (id === undefined) { id = table; table = D.issue_table; }
        var epoch = nedRetireContext();
        NED.mode = 'edit';
        NED.table = table;
        NED.rowId = id;
        NED.draftTag = '';
        // Opened over the board, by the board. It drew this card and can draw
        // it again, so a save there need not build the whole portal a second
        // time. Set on every open, so it never outlives the one it was set for.
        NED.fromBoard = (NED.forceTarget === 'overlay');
        nedMount(nedResolveMountTarget());
        nedPushHistory();
        NED.el.hidden = false;
        nedShowAlert('', false);

        var key = table + '::' + id;
        if (NED.editFormCache[key]) {
            // Cache hit: paint the form immediately, so opening costs nothing
            // the reader can feel, and then check what was painted.
            NED.el.querySelector('.pp-ne-spin').hidden = true;
            nedRenderForm(NED.editFormCache[key]);
            // Three seconds, not ninety. The old number was picked to spare
            // the server a request, and bought that by showing a form that
            // could be a minute and a half out of date - which is a row's
            // whole lifetime on a busy board. This is short enough that only
            // a hover-prewarm, or the same form opened twice in a breath,
            // skips the check.
            if (!nedFormFresh(table, id, 3000)) {
                nedRevalidateForm(table, id);
            }
            return;
        }

        NED.el.querySelector('.pp-ne-spin').hidden = false;
        NED.el.querySelector('.pp-ne-form').hidden = true;
        NED.el.querySelector('.pp-ne-foot').hidden = true;
        NED.el.querySelector('.pp-ne-key').textContent = '';
        NED.el.querySelector('.pp-ne-t').textContent = '';

        nedFetchEditForm(table, id)
            .then(function (j) { if (nedCurrent(epoch)) { nedRenderForm(j); } })
            .catch(function (e) {
                if (!nedCurrent(epoch)) { return; }
                NED.el.querySelector('.pp-ne-spin').hidden = true;
                nedShowAlert(nedFormError(e), false);
            });
    }

    // Open the native modal in "create" mode: fetches an empty form
    // (schema + defaults) from the light endpoint and never touches the
    // iframe pipeline, so a second click never redownloads admin
    // chrome/CSS/JS. The schema does not vary between opens in the same
    // tab, so we memoise the payload in NED.createFormCache and treat
    // subsequent opens as offline (0 network) with a quiet background
    // refresh.
    function nedFetchCreateForm(tbl, low) {
        // The status and type lists belong to a project, so the defaults the
        // form should offer depend on which project we are in. Keep the cache
        // key in step with that or the second project reuses the first one's.
        var parent = (D.project && D.project.id) || '';
        var ck = tbl + '|' + parent;
        if (NED.createFormCache[ck]) { return Promise.resolve(NED.createFormCache[ck]); }
        if (NED.createFormInflight[ck]) {
            if (!low) { ppQueue.promote(NED.createFormInflight[ck]); }
            return NED.createFormInflight[ck];
        }
        var url = (D.urls && D.urls.lightapi_base) + '/data/' + tbl + '/create'
            + (parent ? '?parent_id=' + encodeURIComponent(parent) : '');
        var q = ppQueue(function () { return nedGetForm(url); }, low);
        var p = q
            .then(function (j) { NED.createFormCache[ck] = j; return j; })
            .finally(function () { delete NED.createFormInflight[ck]; });
        p.ppJob = q.ppJob;
        NED.createFormInflight[ck] = p;
        return p;
    }
    function nedCreateFormCached(tbl) {
        return NED.createFormCache[tbl + '|' + ((D.project && D.project.id) || '')];
    }
    // A create form that starts from somewhere - "add a child of this one",
    // "copy this one". The values are resolved on the server so a picker
    // arrives with its label already attached, exactly as a default does;
    // the answer describes one row, not the table, so it is never cached.
    function nedFetchPrefilledForm(tbl, prefill) {
        var parent = (D.project && D.project.id) || '';
        var url = (D.urls && D.urls.lightapi_base) + '/data/' + tbl + '/create'
            + '?prefill=' + encodeURIComponent(JSON.stringify(prefill))
            + (parent ? '&parent_id=' + encodeURIComponent(parent) : '');

        return ppQueue(function () { return nedGetForm(url); });
    }
    function nedPrewarmCreateForm(tbl) {
        // Fire-and-forget so the first click on 課題の追加 renders instantly.
        if (!NED.el) { return; }
        nedFetchCreateForm(tbl || D.issue_table, true).catch(function () { });
    }

    // The create form has an address, so a click goes through it rather than
    // straight into openNcreate: that is what leaves something in the address
    // bar to reload, bookmark or send to somebody.
    //
    // Only the issue form. A child or a copy carries values taken off the
    // issue on screen - the description among them - and none of that is in
    // the URL, so a link that reopened those would quietly drop fields.
    function goCreate() {
        if (parseHash().path === 'new') { return; }
        location.hash = 'new';
    }

    // The editor has an address too: #issue-<key>?edit. Going through it is
    // what makes a reload, or a link sent to somebody, open the form rather
    // than the issue behind it - and it makes the back gesture close the
    // editor rather than walk out of the issue.
    //
    // Only the issue editor. The wiki and project forms are opened from
    // screens of their own and are left as they were; the board keeps its
    // overlay, which is a different screen behind the same panel rather than
    // a different address for this one.
    // Edit a row where it stands, on a screen that keeps its place behind
    // the panel: the board's cards and the gantt's bars. The address names
    // that screen rather than the issue, because that screen is what is
    // still on display - #board?edit=<id>, #gantt?edit=<id>.
    function goEditOver(screen, id) {
        var want = screen + '?edit=' + id;
        if (location.hash.slice(1) === want) { return; }
        location.hash = want;
    }

    function goEdit(table, id) {
        if (table !== D.issue_table) { openNedit(table, id); return; }
        var want = issueHash(id) + '?edit';
        if (location.hash.slice(1) === want) { return; }
        location.hash = want;
    }

    // draftTag: which kind of start this is - '' for a plain new row,
    // 'child<id>' or 'copy<id>' for one begun from an issue - so each keeps a
    // draft slot of its own (nedDraftKey).
    function openNcreate(table, prefill, draftTag) {
        if (!NED.el) { return; }
        var tbl = table || D.issue_table;
        var epoch = nedRetireContext();
        NED.mode = 'create';
        NED.table = tbl;
        NED.rowId = null;
        NED.draftTag = draftTag ? String(draftTag).replace(/[^A-Za-z0-9_-]/g, '') : '';
        nedMount(nedResolveMountTarget());
        nedPushHistory();
        NED.el.hidden = false;
        NED.el.querySelector('.pp-ne-key').textContent = '';
        NED.el.querySelector('.pp-ne-t').textContent = '';
        nedShowAlert('', false);

        if (!prefill && nedCreateFormCached(tbl)) {
            NED.el.querySelector('.pp-ne-spin').hidden = true;
            nedRenderForm(nedCreateFormCached(tbl));
            return;
        }
        NED.el.querySelector('.pp-ne-spin').hidden = false;
        NED.el.querySelector('.pp-ne-form').hidden = true;
        NED.el.querySelector('.pp-ne-foot').hidden = true;
        (prefill ? nedFetchPrefilledForm(tbl, prefill) : nedFetchCreateForm(tbl))
            .then(function (j) { if (nedCurrent(epoch)) { nedRenderForm(j); } })
            .catch(function (e) {
                if (!nedCurrent(epoch)) { return; }
                NED.el.querySelector('.pp-ne-spin').hidden = true;
                nedShowAlert(nedFormError(e), false);
            });
    }

    // Phase B: top-edge progress bar during save. 'start' widens the bar
    // to 70% immediately (perceived motion); 'done' completes to 100%
    // then fades out; 'error' snaps to 100% in red then fades. The bar
    // element itself is inert markup — see index.blade.php.
    function nedPbar(state) {
        var bar = NED.el && NED.el.querySelector('.pp-ne-pbar');
        if (!bar) { return; }
        if (state === 'start') {
            NED.pbarEpoch++;
            bar.hidden = false; bar.classList.remove('err'); bar.style.opacity = '1';
            bar.style.width = '0%';
            // Force a reflow so the transition kicks in from 0 → 70.
            void bar.offsetWidth;
            bar.style.width = '70%';
            return;
        }
        if (state === 'done' || state === 'error') {
            var epoch = NED.epoch, progress = NED.pbarEpoch;
            if (state === 'error') { bar.classList.add('err'); }
            bar.style.width = '100%';
            setTimeout(function () {
                if (!nedCurrent(epoch) || NED.pbarEpoch !== progress) { return; }
                bar.style.opacity = '0';
                setTimeout(function () {
                    if (!nedCurrent(epoch) || NED.pbarEpoch !== progress) { return; }
                    bar.hidden = true; bar.style.width = '0%'; bar.classList.remove('err');
                }, 300);
            }, 250);
        }
    }
    function nedRefreshBoard(id, table) {
        NED.portalStale = true;
        window.postMessage({
            exmentFormFrame: 'saved',
            id: String(id),
            table: String(table)
        }, window.location.origin);
        refreshData(true, function (fresh) {
            if (alive() && fresh) { NED.portalStale = false; }
        });
    }
    function nedImagePaste(ev) {
        var input = ev.target;
        // ISS00245: the issue description and the wiki page body (2026-11-18)
        if (!input || input.tagName !== 'TEXTAREA' || !input.classList.contains('pp-ne-in')
            || (NED.table !== D.issue_table && NED.table !== D.wiki_table) || !NED.canEdit || input.readOnly) { return; }
        var field = input.closest('[data-fname]');
        var col = field && nedAiCol(field.dataset.fname);
        if (!col || col.type !== 'richtext' || !ev.clipboardData) { return; }
        var files = [];
        Array.prototype.forEach.call(ev.clipboardData.items || [], function (item) {
            if (item.kind !== 'file') { return; }
            var file = item.getAsFile();
            if (file && /^image\//i.test(file.type)) { files.push(file); }
        });
        if (!files.length) {
            files = Array.prototype.filter.call(ev.clipboardData.files || [], function (file) {
                return /^image\//i.test(file.type);
            });
        }
        if (!files.length) { return; }
        ev.preventDefault();
        if (NED.busy) { nedShowAlert(nedGetLangKey('ned_saving'), false); return; }
        if (!D.urls.temp_image_upload) { nedShowAlert(L.cmt_up_fail, false); return; }
        var extensions = { 'image/png': 'png', 'image/jpeg': 'jpg', 'image/gif': 'gif', 'image/bmp': 'bmp', 'image/webp': 'webp' };
        if (files.some(function (file) { return !extensions[String(file.type).toLowerCase()]; })) {
            nedShowAlert(L.cmt_up_fail, false);
            return;
        }
        var epoch = NED.epoch, start = input.selectionStart, end = input.selectionEnd;
        var uploadUrl = D.urls.temp_image_upload, tempBase;
        try {
            var parsed = new URL(uploadUrl, location.href);
            if (parsed.origin !== location.origin || !/\/tmpimages\/?$/.test(parsed.pathname)) {
                throw new Error('Invalid clipboard upload endpoint.');
            }
            tempBase = parsed.href.replace(/\/tmpimages\/?$/, '/tmpfiles/');
        } catch (error) { nedShowAlert(L.cmt_up_fail, false); return; }
        var html = mdAuthoredAsHtml(input.value);
        var buttons = Array.prototype.slice.call(NED.el.querySelectorAll('.pp-ne-save'));
        var labels = buttons.map(function (button) { return button.innerHTML; });
        var aiButton = NED.el.querySelector('.pp-ne-ai-btn');
        NED.busy = true; NED.uploadingImage = true;
        input.readOnly = true;
        nedGuard(true);
        buttons.forEach(function (button) { button.disabled = true; });
        if (aiButton) { aiButton.disabled = true; }
        nedShowAlert(L.cmt_uploading, true);
        var done = function () {
            NED.busy = false; NED.uploadingImage = false;
            input.readOnly = false;
            buttons.forEach(function (button, index) { button.disabled = !NED.canEdit; button.innerHTML = labels[index]; });
            if (aiButton && aiButton._ppAiRefresh) { aiButton._ppAiRefresh(); }
            nedUpdateChangeCount();
            nedGuard(nedDirty());
        };
        Promise.all(files.map(function (file, index) {
            var name = 'image-' + Date.now() + '-' + index + '.' + extensions[String(file.type).toLowerCase()];
            var data = new FormData();
            data.append('file', file, name);
            return fetch(uploadUrl, {
                method: 'POST', body: data, credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF }
            }).then(ppJson, ppNoAnswer).then(function (res) {
                var body = res.body;
                var url = body && body.location;
                // Refused as the {status, body} pair errText words: this is
                // Exment's own endpoint, and what it says on failure - often
                // English validation text - is never shown as it stands.
                if (res.status < 200 || res.status >= 300 || typeof url !== 'string' || url.indexOf(tempBase) !== 0
                    || !/^[0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12}$/i.test(url.slice(tempBase.length))) {
                    throw { status: res.status, body: body };
                }
                return html ? '<img src="' + esc(url) + '" alt="' + esc(name) + '">'
                    : mdFileLink(name, url, true);
            });
        })).then(function (markup) {
            if (!nedCurrent(epoch)) { return; }
            input.setRangeText(markup.join(html ? '' : '\n'), start, end, 'end');
            done();
            input.dispatchEvent(new Event('input', { bubbles: true }));
            nedDraftSave();
            nedShowAlert(fmt(L.cmt_up_ok, markup.length), true);
            input.focus();
        }).catch(function (error) {
            if (!nedCurrent(epoch)) { return; }
            done();
            // A 2xx that is not an upload (an address off the endpoint) has no
            // status sentence and falls through to the upload's own wording.
            var status = error && typeof error.status === 'number' && (error.status < 200 || error.status >= 300) ? error.status : null;
            nedShowAlert(errText(status, error && error.body, L.cmt_up_fail), false);
        });
    }
    function nedResolveImageUrls(text, images) {
        if (typeof text !== 'string') { return text; }
        text = text.replace(/<img\b[^>]*>/gi, function (tag) {
            var src = tag.match(/\bsrc="([^"]*)"/i);
            var image = src && images[src[1]];
            if (!image) { return tag; }
            tag = tag.replace(/\sdata-exment-file-uuid="[^"]*"/gi, '');
            return tag.replace('<img', '<img data-exment-file-uuid="' + image.uuid + '"');
        });
        Object.keys(images).forEach(function (source) { text = text.split(source).join(images[source].url); });
        return text;
    }
    function nedRewriteDraftImages(key, owner, images) {
        try {
            var raw = key && localStorage.getItem(key);
            var draft = raw && JSON.parse(raw);
            if (!draft || draft.owner !== owner) { return; }
            Object.keys(images || {}).forEach(function (name) {
                draft.v[name] = nedResolveImageUrls(draft.v[name], images[name]);
            });
            localStorage.setItem(key, JSON.stringify(draft));
        } catch (e) { /* quota or private mode */ }
    }

    // Which row a newly created record hangs off. Issues and wiki pages
    // live inside the current project; anything else is standalone.
    function nedParentFor(mode, table) {
        if (mode !== 'create') { return null; }
        if (table !== D.issue_table && table !== D.wiki_table) { return null; }
        if (!D.project || !D.project.id || !D.project_table) { return null; }
        return { id: D.project.id, type: D.project_table };
    }
    function nedPreserveEdits() {
        nedGuard(true);
        nedDraftSave();
        nedShowAlert(nedGetLangKey('ned_saved') + ' ' + nedGetLangKey('draft_found'), true);
    }

    function nedSave() {
        if (NED.busy || !NED.canEdit || !NED.saveUrl) { return; }
        nedLeavePreview();
        // Clear stale per-field errors from a previous attempt so the
        // user sees only the errors that came back this time.
        NED.el.querySelectorAll('.pp-ne-fg.err').forEach(function (fg) {
            fg.classList.remove('err');
            var e = fg.querySelector('.err');
            if (e) { e.hidden = true; e.textContent = ''; }
        });
        // Phase B: block save if any required field is empty. The blur
        // handlers already show inline errors; here we re-run for the
        // "user hit Save without focusing anything" case.
        if (!nedValidate()) {
            nedShowAlert(nedGetLangKey('ned_validation'), false);
            var firstErr = NED.el.querySelector('.pp-ne-fg.err .pp-ne-in');
            if (firstErr) { try { firstErr.focus(); } catch (e) { /* ignore */ } }
            return;
        }
        var isCreate = NED.mode === 'create';
        var diff = isCreate ? nedBuildCreate() : nedBuildDiff();
        if (!isCreate && !Object.keys(diff).length) {
            nedShowAlert(nedGetLangKey('ned_saved'), true);
            return;
        }
        var epoch = NED.epoch;
        var table = NED.table, rowId = NED.rowId, fromBoard = NED.fromBoard;
        var submitted = nedSerialize(), submittedValues = JSON.parse(submitted);
        var draftKey = nedDraftKey(), draftOwner = NED.draftOwner;
        var aiState = NED._ai, feedbackUrl = D.urls && D.urls.ai_feedback;
        var createdId = NED.createdId;
        var apiBase = D.urls && D.urls.lightapi_base;
        nedDraftSave();
        NED.busy = true;
        NED.createSaving = isCreate;
        NED.el.inert = isCreate;
        var saveBtns = NED.el.querySelectorAll('.pp-ne-save');
        var savedLabel = saveBtns.length ? saveBtns[0].innerHTML : '';
        var restore = function () {
            NED.busy = false;
            NED.createSaving = false;
            NED.el.inert = false;
            saveBtns.forEach(function (b) { b.disabled = !NED.canEdit; b.innerHTML = savedLabel; });
            var aiButton = NED.el.querySelector('.pp-ne-ai-btn');
            if (aiButton && aiButton._ppAiRefresh) { aiButton._ppAiRefresh(); }
            nedUpdateChangeCount();
        };
        saveBtns.forEach(function (b) {
            b.disabled = true;
            b.innerHTML = '<i class="fa fa-spinner fa-spin"></i> ' + nedGetLangKey('ned_saving');
        });
        nedShowAlert('', false);
        nedPbar('start');

        var body = { value: diff };
        var neMail = NED.el.querySelector('.pp-ne-mail input');
        if (isCreate && table === D.issue_table && neMail && neMail.checked) { body.notify_mail = 1; }
        // What this form knew when it opened. A save that carries it can be
        // refused; one that does not is the old blind overwrite.
        if (!isCreate && NED.rowUpdatedAt && !NED.force) { body.updated_at = NED.rowUpdatedAt; }
        NED.force = false;
        // A new issue or wiki page belongs to the project through Exment's
        // parent relation, which is not a custom column and therefore never
        // part of the diff. Without it the row is saved with no project and
        // disappears from every project-scoped list.
        var parent = nedParentFor(NED.mode, NED.table);
        if (parent) {
            body.parent_id = parent.id;
            body.parent_type = parent.type;
        }

        fetch(NED.saveUrl, {
            method: NED.saveMethod || 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': CSRF
            },
            credentials: 'same-origin',
            body: JSON.stringify(body)
        }).then(ppJson, ppNoAnswer).then(function (res) {
            var newId = res.body && (res.body.id || (res.body.row && res.body.row.id));
            var ok = (res.status === 200 || res.status === 201) && res.body && res.body.ok && (!isCreate || newId);
            if (ok) {
                if (rowId) {
                    delete DET[rowId];
                    delete NED.editFormCache[table + '::' + rowId];
                }
                nedAiFeedback(isCreate ? newId : rowId, aiState, submittedValues, feedbackUrl);
                if (!nedCurrent(epoch)) {
                    nedDraftClear(draftKey, draftOwner, submitted);
                    nedRewriteDraftImages(draftKey, draftOwner, res.body.image_urls);
                    return;
                }
                nedPbar('done');
                if (!isCreate) { restore(); }
                (NED.columns || []).forEach(function (c) {
                    if (res.body.row && (c.name in res.body.row)) {
                        NED.values[c.name] = res.body.row[c.name];
                    }
                });
                NED.rowUpdatedAt = res.body.updated_at || (res.body.row && res.body.row.updated_at) || NED.rowUpdatedAt;
                NED.draftBaseAt = NED.rowUpdatedAt;
                if (NED._payload && NED._payload.row) {
                    NED._payload.row.value = NED.values;
                    NED._payload.row.updated_at = NED.rowUpdatedAt;
                }
                Object.keys(res.body.image_urls || {}).forEach(function (name) {
                    submittedValues[name] = nedResolveImageUrls(submittedValues[name], res.body.image_urls[name]);
                    var input = nedInputEl(name);
                    if (input) { input.value = nedResolveImageUrls(input.value, res.body.image_urls[name]); }
                });
                NED.initial = JSON.stringify(submittedValues);
                if (NED._ai === aiState) { NED._ai = null; }
                if (fromBoard) { NED.portalStale = true; }
                if (isCreate) {
                    // A committed create must never be retried as another POST.
                    NED.mode = 'edit';
                    NED.rowId = newId;
                    NED.createdId = newId;
                    NED.saveUrl = apiBase + '/data/' + table + '/' + newId;
                    NED.saveMethod = 'PATCH';
                    if (NED._payload) {
                        NED._payload.save = { url: NED.saveUrl, method: 'PATCH' };
                        if (NED._payload.row) { NED._payload.row.id = newId; }
                    }
                }
                nedUpdateChangeCount();
                if (nedDirty()) {
                    restore();
                    nedPreserveEdits();
                    if (isCreate) { nedDraftClear(draftKey, draftOwner); }
                    return;
                }
                nedGuard(false);
                nedDraftClear(draftKey, draftOwner);
                nedDraftBar(false);
                if (!isCreate && fromBoard && rowId) {
                    NED.fromBoard = false;
                    nedClose();
                    nedRefreshBoard(rowId, table);
                    return;
                }

                setTimeout(function () {
                    if (!nedCurrent(epoch) || (!isCreate && NED.busy)) { return; }
                    if (nedDirty()) { restore(); nedPreserveEdits(); return; }
                    var destinationId = isCreate ? newId : createdId;
                    var hash = null;
                    if (destinationId) {
                        if (table === D.wiki_table) {
                            hash = 'wiki-' + destinationId;
                        } else if (table === D.issue_table) {
                            hash = 'issue-' + destinationId;
                        }
                    }
                    // Saved and nothing left to keep: the panel goes, and the
                    // screens behind it are drawn again from fresh data.
                    NED.portalStale = false;
                    // The save is over: let go of the panel (a create keeps it
                    // locked until now) so it can close.
                    restore();
                    nedClose();
                    softReload(hash, nedGetLangKey('m_saved'));
                }, 350);
                return;
            }
            if (!nedCurrent(epoch)) { return; }
            restore();
            nedPbar('error');
            if (res.status === 409 && res.body && res.body.error === 'stale') {
                nedShowConflict(res.body);
                return;
            }
            if (res.status === 422 && res.body && res.body.fields && typeof res.body.fields === 'object') {
                Object.keys(res.body.fields).forEach(function (k) {
                    var fg = nedFieldEl(k);
                    if (!fg) { return; }
                    fg.classList.add('err');
                    nedRevealField(fg);
                    var e = fg.querySelector('.err');
                    if (e) { e.hidden = false; e.textContent = Array.isArray(res.body.fields[k]) ? res.body.fields[k].join(' ') : String(res.body.fields[k]); }
                });
            }
            nedShowAlert(errText(res.status, res.body, nedGetLangKey('ned_error')), false);
        }).catch(function () {
            if (!nedCurrent(epoch)) { return; }
            restore();
            nedPbar('error');
            nedShowAlert(nedGetLangKey('ned_error'), false);
        });
    }

    // Returns {table, id} when a URL points at any portal-managed
    // table's edit page - the ones where we swap the iframe modal for the
    // native lightweight-form. Otherwise null.
    function maybeNedit(url) {
        var tables = [D.issue_table, D.wiki_table, D.project_table];
        var s = String(url || '');
        for (var i = 0; i < tables.length; i++) {
            var t = tables[i];
            if (!t) { continue; }
            var m = s.match(new RegExp('/data/' + t + '/(\\d+)/edit(?:$|[?#])'));
            if (m) { return { table: t, id: m[1] }; }
        }
        return null;
    }

    // Returns a table name when a URL points at a portal-managed
    // create page so we can swap the iframe modal for the native form.
    function maybeNcreate(url) {
        var tables = [D.issue_table, D.wiki_table];
        var s = String(url || '');
        for (var i = 0; i < tables.length; i++) {
            var t = tables[i];
            if (!t) { continue; }
            if (new RegExp('/data/' + t + '/create(?:$|[?#])').test(s)) { return t; }
        }
        return null;
    }

    if (NED.el) {
        NED.el.querySelector('.pp-ne-x').addEventListener('click', nedClose);
        NED.el.querySelector('.pp-ne-back').addEventListener('click', nedClose);
        NED.el.querySelectorAll('.pp-ne-cancel').forEach(function (b) { b.addEventListener('click', nedClose); });
        NED.el.querySelectorAll('.pp-ne-save').forEach(function (b) { b.addEventListener('click', nedSave); });
        var keyBtn = NED.el.querySelector('.pp-ne-key');
        if (keyBtn) {
            keyBtn.addEventListener('click', nedCopyKey);
            keyBtn.addEventListener('keydown', function (ev) {
                if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); nedCopyKey(); }
            });
        }
        // Live change counter: recompute on any field change/input so the
        // save button always shows the pending diff count.
        var form = NED.el.querySelector('.pp-ne-form');
        if (form) {
            form.addEventListener('paste', nedImagePaste);
            // ISS00245: an image dropped on the body goes the same way as one
            // pasted (uploaded, then ![name](url) at the caret).
            form.addEventListener('dragover', function (ev) {
                var t = ev.target;
                if (t && t.tagName === 'TEXTAREA' && t.classList.contains('pp-ne-in')
                    && ev.dataTransfer && Array.prototype.indexOf.call(ev.dataTransfer.types || [], 'Files') >= 0) {
                    ev.preventDefault();
                }
            });
            form.addEventListener('drop', function (ev) {
                var t = ev.target;
                if (!t || t.tagName !== 'TEXTAREA' || !t.classList.contains('pp-ne-in') || !ev.dataTransfer
                    || !(ev.dataTransfer.files && ev.dataTransfer.files.length)) { return; }
                // never let the browser open a dropped file in place of the form
                ev.preventDefault();
                var hasImage = Array.prototype.some.call(ev.dataTransfer.files, function (f) { return /^image\//i.test(f.type); });
                if (!hasImage) { nedShowAlert('本文に入れられるのは画像だけです。ほかのファイルは「ファイル」から添付してください。', false); return; }
                nedImagePaste({ target: t, clipboardData: ev.dataTransfer, preventDefault: function () {} });
            });
            form.addEventListener('input', nedUpdateChangeCount);
            form.addEventListener('change', nedUpdateChangeCount);
            // Same two events drive the leave-page guard and the draft, both
            // debounced inside nedSyncGuard because this fires per keystroke.
            form.addEventListener('input', nedSyncGuard);
            form.addEventListener('change', nedSyncGuard);
        }
        var draftBar = NED.el.querySelector('.pp-ne-draft');
        if (draftBar) {
            draftBar.querySelector('.pp-ne-draft-yes').addEventListener('click', nedDraftRestore);
            draftBar.querySelector('.pp-ne-draft-no').addEventListener('click', function () {
                nedDraftClear();
                nedDraftBar(false);
            });
        }
        // Ctrl+Enter and Command+Enter both save (below); the hint names the
        // key this keyboard actually has, Ctrl unless it is an Apple one.
        if (/Mac|iPhone|iPad|iPod/.test(navigator.platform || '')) {
            NED.el.querySelectorAll('.pp-ne-modkey[data-mac]').forEach(function (k) {
                k.textContent = k.getAttribute('data-mac');
            });
        }
        document.addEventListener('keydown', function onNedKey(ev) {
            if (!alive()) { document.removeEventListener('keydown', onNedKey); return; }
            if (ev.key === 'Escape' && !NED.el.hidden) { nedClose(); }
            if ((ev.metaKey || ev.ctrlKey) && ev.key === 'Enter' && !NED.el.hidden) {
                ev.preventDefault(); nedSave();
            }
        });
    }
    window.openNedit = openNedit;
    window.openNcreate = openNcreate;
    // How the kanban board embedded in this screen opens a card: the portal's
    // own editor, rather than a form window that knows nothing about the
    // screen around it. Over the board rather than inside the issue detail -
    // that card is not on display while the board is.
    // Through the hash, so the panel has an address a reload can come back
    // to. The router answers it by calling neditOverScreen below; this one
    // only writes the address.
    window.openNeditOverlay = function (table, id) {
        var scr = parseHash().path;
        if (table === D.issue_table && (scr === 'board' || scr === 'gantt')) {
            goEditOver(scr, id);
            return;
        }
        neditOverScreen(table, id);
    };
    function neditOverScreen(table, id, fromHash) {
        NED.forceTarget = 'overlay';
        NED_HASH_OPEN = !!fromHash;
        try {
            openNedit(table, id);
        } finally {
            NED.forceTarget = null;
            NED_HASH_OPEN = false;
        }
    }
    window.nedPrewarmEditForm = nedPrewarmEditForm;
    window.nedPrewarmCreateForm = nedPrewarmCreateForm;

    // Any link that points at the raw data screens is folded back into the
    // portal: record pages become the portal detail, forms become the modal.
    function interceptDataLink(a, ev) {
        if (ev.defaultPrevented || ev.ctrlKey || ev.metaKey || ev.shiftKey || (ev.button && ev.button !== 0)) { return false; }
        if (a.target && a.target !== '_self') { return false; }
        var href = a.href || '';
        var base = D.urls.data_base + '/';
        if (href.indexOf(base) !== 0) { return false; }
        var seg = href.slice(base.length).split(/[?#]/)[0].replace(/\/+$/, '').split('/');
        var table = seg[0], id = seg[1] || '', act = seg[2] || '';
        function handled(fn) { ev.preventDefault(); fn(); return true; }
        if (table === D.issue_table) {
            if (id === 'create') { return handled(goCreate); }
            if (/^\d+$/.test(id) && !act) {
                return handled(function () {
                    // A click on the chart opens the editor over it, the way a
                    // card does on the board: the gantt is where a wrong date
                    // is noticed, and the two date fields are in that panel.
                    // Only from the chart itself - a link inside an open panel
                    // still means the issue, which is what it says.
                    if (parseHash().path === 'gantt' && (!NED.el || NED.el.hidden)) {
                        goEditOver('gantt', id);
                        return;
                    }
                    location.hash = 'issue-' + id;
                });
            }
            if (act === 'edit') {
                return handled(function () { goEdit(D.issue_table, id); });
            }
            if (act === 'compare') { return handled(function () { formModal(href, L.m_history, ''); }); }
        }
        if (table === D.wiki_table) {
            if (id === 'create') { return handled(function () { openNcreate(D.wiki_table); }); }
            if (/^\d+$/.test(id) && !act) { return handled(function () { location.hash = 'wiki-' + id; }); }
            if (act === 'edit') { return handled(function () { openNedit(D.wiki_table, id); }); }
            if (act === 'compare') { return handled(function () { formModal(href, L.m_history, ''); }); }
        }
        if (table === D.project_table && String(id) === String(D.project.id)) {
            if (act === 'edit') { return handled(function () { openNedit(D.project_table, id); }); }
            if (!act) { return handled(function () { location.hash = 'home'; }); }
        }
        return false;
    }
    // Embedded widgets (kanban / gantt) route their record jumps through here.
    window.ppNavigate = function (url) {
        var a = document.createElement('a');
        a.href = url;
        var fake = { defaultPrevented: false, ctrlKey: false, metaKey: false, shiftKey: false, button: 0, preventDefault: function () {} };
        return interceptDataLink(a, fake);
    };
    // The board attaches its card drawer to <body>, outside root, so clicks on
    // the drawer's links never reach the handler on root and would leave the
    // portal for the raw record screen. Caught on the document instead, and
    // released once this page is gone.
    document.addEventListener('click', function onDrawerLink(ev) {
        if (!alive()) { document.removeEventListener('click', onDrawerLink); return; }
        var a = ev.target.closest ? ev.target.closest('#kb-drawer a[href]') : null;
        if (a) { interceptDataLink(a, ev); }
    });

