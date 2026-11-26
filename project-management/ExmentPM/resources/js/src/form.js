    /* ------------------------------------------------ in-portal form ---- */
    // Create / edit / history open inside the portal: the real admin form is
    // loaded in an iframe with the admin chrome hidden, so the user never
    // feels thrown out of the Backlog-style screens onto the data screens.
    // A second, hidden iframe stays pre-warmed (the create form at idle, the
    // open issue's edit form when its screen opens), so the modal usually
    // appears with the form already rendered - no spinner at all.
    var FM = { el: document.getElementById('pp-fmodal'), frame: null, warm: null, snap: '', lastUrl: '', reloading: false };
    if (FM.el) {
        FM.frame = FM.el.querySelector('iframe:not(.pp-fm-warm)');
        FM.warm = FM.el.querySelector('iframe.pp-fm-warm');
    }
    function chromeCss() {
        return '.main-header,.main-sidebar,.main-footer,section.content-header{display:none!important}' +
            '.content-wrapper{margin-left:0!important;background:transparent!important;padding-top:6px;min-height:0!important}' +
            'body{background:var(--pp-bg,var(--bl-bg,#fff))!important;color:var(--bl-ink,#24323f)!important;' +
            'font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Meiryo,sans-serif;}' +
            'a[href="' + D.urls.issue_data + '"],a[href="' + D.urls.wiki_data + '"],a[href="' + D.urls.project_data + '"],' +
            'a[href^="' + D.urls.data_base.replace('/data', '/table') + '"]{display:none!important}';
    }
    // The portal's palette follows into the iframe as CSS variables, so the
    // body rules that shape the form see the same accent / surface / ink
    // colours. Read off the page rather than held as a second copy, so a
    // deployment that overrides the stylesheet is followed here too.
    var PALETTE = ['--bl-accent', '--bl-accent-d', '--bl-ink', '--bl-sub', '--bl-faint',
        '--bl-line', '--bl-bg', '--bl-box', '--bl-box2', '--bl-chip', '--bl-hover',
        '--bl-red', '--bl-amber'];
    function frameThemeVars() {
        var style = window.getComputedStyle(root);
        var out = '';
        PALETTE.forEach(function (name) {
            var value = style.getPropertyValue(name);
            if (value && value.trim() !== '') { out += name + ':' + value.trim() + ';'; }
        });
        return out;
    }
    function frameFormStyleVars() {
        var style = (D.form_styles || {})[CUR_FORM_STYLE] || {};
        var vars = style.vars || {};
        var out = '';
        Object.keys(vars).forEach(function (k) { out += k + ':' + vars[k] + ';'; });
        return out;
    }
    // Big pack of body rules that read the variables set above. Every style
    // - card / sheet / compact / flow - is a set of numbers, and this rule
    // pack turns those numbers into a full admin form layout.
    function frameBodyCss() {
        return [
            /* root reads all the variables */
            ':root{--pp-bg:var(--bl-bg,#f5f7f9);}',
            /* frame the form as a single central sheet */
            '.wrapper,#pjax-container,#app{background:transparent!important;}',
            'section.content{max-width:var(--pp-form-max,960px);margin:16px auto 40px!important;padding:0 16px!important;}',
            /* the outer .box */
            '.box.custom-border-info,.box{border:0!important;background:var(--bl-box,#fff)!important;' +
                'border-radius:var(--pp-form-radius,10px)!important;box-shadow:var(--pp-form-shadow,0 8px 24px rgba(15,42,60,.08))!important;' +
                'overflow:hidden;}',
            /* header */
            '.box-header.with-border{background:var(--pp-form-header-bg,var(--bl-box,#fff))!important;border-bottom:1px solid var(--bl-line,#dfe5ea)!important;padding:14px 22px!important;}',
            '.box-header .box-title{font-size:var(--pp-form-header-fs,16px)!important;font-weight:700!important;color:var(--bl-ink,#24323f);}',
            /* header text turns white on the accent gradient of "flow" */
            '.pp-form-flow .box-header.with-border{color:#fff!important;border-bottom:0!important;padding:22px 28px!important;}',
            '.pp-form-flow .box-header .box-title,.pp-form-flow .box-header i,.pp-form-flow .box-header a{color:#fff!important;}',
            '.pp-form-flow .box-header .btn-default{background:rgba(255,255,255,.15)!important;color:#fff!important;border:1px solid rgba(255,255,255,.3)!important;}',
            /* body */
            '.box-body{padding:var(--pp-form-body-pad,18px 22px 22px)!important;background:var(--bl-box,#fff);}',
            /* form-group as a row: label / control */
            '.form-group{margin-bottom:var(--pp-form-gap,18px)!important;padding-top:0!important;}',
            '.form-group .control-label{color:var(--bl-sub,#6b7a88)!important;font-weight:600!important;' +
                'text-transform:var(--pp-form-label-transform,none);letter-spacing:var(--pp-form-label-letter,0);font-size:12.5px;}',
            /* sheet: stack label above input, dim label */
            '.pp-form-sheet .form-group>.control-label,.pp-form-sheet .form-group>[class^="col-"].control-label{' +
                'text-align:left!important;width:100%!important;max-width:100%!important;flex:0 0 100%!important;padding:0 0 6px!important;}',
            '.pp-form-sheet .form-group>[class^="col-"]:not(.control-label){' +
                'width:100%!important;max-width:100%!important;flex:0 0 100%!important;padding-left:0!important;padding-right:0!important;}',
            '.pp-form-sheet .form-control,.pp-form-sheet .select2-selection{' +
                'border:0!important;border-bottom:1px solid var(--bl-line,#dfe5ea)!important;border-radius:0!important;' +
                'background:transparent!important;padding-left:2px!important;padding-right:2px!important;}',
            /* compact: two-column grid inside .box-body */
            '.pp-form-compact .box-body{display:grid;grid-template-columns:1fr 1fr;gap:2px 24px;}',
            '.pp-form-compact .form-group{margin-bottom:var(--pp-form-gap,6px)!important;}',
            '.pp-form-compact .form-group.textarea-group,.pp-form-compact .form-group:has(.editor-input),.pp-form-compact .form-group:has(textarea){grid-column:1 / -1;}',
            /* inputs */
            '.form-control,.select2-selection,.select2-selection--single,.select2-selection--multiple{' +
                'border-radius:var(--pp-form-input-radius,6px)!important;border-color:var(--bl-line,#dfe5ea)!important;' +
                'transition:border-color .15s ease, box-shadow .15s ease;}',
            '.form-control:focus,.select2-container--focus .select2-selection{' +
                'border-color:var(--bl-accent,#4caf93)!important;box-shadow:0 0 0 3px rgba(76,175,147,.15)!important;outline:0!important;}',
            'textarea.form-control{min-height:100px;}',
            /* buttons */
            '.btn{border-radius:var(--pp-form-input-radius,6px)!important;font-weight:600;transition:filter .15s ease;}',
            '.btn-primary,.btn-info{background:var(--bl-accent,#4caf93)!important;border-color:var(--bl-accent-d,#3d9a80)!important;color:#fff!important;}',
            '.btn-primary:hover,.btn-primary:focus,.btn-info:hover,.btn-info:focus{filter:brightness(1.05);background:var(--bl-accent-d,#3d9a80)!important;}',
            '.btn-default{background:var(--bl-box2,#f5f7f9)!important;border-color:var(--bl-line,#dfe5ea)!important;color:var(--bl-ink,#24323f)!important;}',
            /* submit area */
            '.after-submit,.box-footer{background:var(--pp-form-footer-bg,var(--bl-box,#fff))!important;' +
                'border-top:1px solid var(--bl-line,#dfe5ea)!important;box-shadow:var(--pp-form-footer-shadow,none)!important;padding:14px 22px!important;}',
            /* helpers */
            '.help-block{color:var(--bl-sub,#6b7a88)!important;font-size:12px;}',
            '.asterisk{color:#e57373;}',
            'a{color:var(--bl-accent,#4caf93);}',
            /* dark tweaks */
            '.pp-form-dark,.pp-form-dark .box-body,.pp-form-dark .box{color:var(--bl-ink,#e4e9ee)!important;}',
            '.pp-form-dark .form-control,.pp-form-dark .select2-selection{background:var(--bl-box2,#2b3541)!important;color:var(--bl-ink,#e4e9ee)!important;}',
            '.pp-form-dark .select2-dropdown{background:var(--bl-box,#252e38)!important;color:var(--bl-ink,#e4e9ee)!important;border-color:var(--bl-line,#3a4653)!important;}',
            '.pp-form-dark .select2-results__option{color:var(--bl-ink,#e4e9ee)!important;}',
            '.pp-form-dark .btn-default{background:var(--bl-box2,#2b3541)!important;color:var(--bl-ink,#e4e9ee)!important;}',
            /* the header widget-modal button pack is not for us here */
            '.box-header .btn-group{filter:saturate(.8);}',
        ].join('\n');
    }
    // Repaint the iframe: kill the admin chrome, drop in the palette and the
    // form-style variables, install the body rules. Signature-guarded so the
    // 400ms fmSync tick is a no-op after the first paint; only an actual
    // form-style change rewrites the style element and body class.
    function themeFrame(doc) {
        var sig = CUR_FORM_STYLE;
        var st = doc.getElementById('pp-frame-theme');
        if (st && st.dataset.sig === sig && doc.body.dataset.ppFormSig === sig) { return; }
        if (!st) {
            st = doc.createElement('style');
            st.id = 'pp-frame-theme';
            doc.head.appendChild(st);
        }
        if (st.dataset.sig !== sig) {
            st.textContent = ':root{' + frameThemeVars() + frameFormStyleVars() + '}\n' + frameBodyCss();
            st.dataset.sig = sig;
        }
        // Body class controls the two style-specific shapes: sheet stacking
        // and the compact two-column grid. Only rewritten when the signature
        // actually changes, so we don't force a style recalc on every
        // polling tick.
        if (doc.body.dataset.ppFormSig !== sig) {
            var cls = doc.body.className.replace(/\bpp-form-\S+/g, '').trim();
            doc.body.className = (cls + ' pp-form-' + CUR_FORM_STYLE).trim();
            doc.body.dataset.ppFormSig = sig;
        }
    }
    function styleFrame(frame) {
        var win, doc;
        try {
            win = frame.contentWindow;
            doc = frame.contentDocument;
        } catch (e) { return false; }
        if (!doc || !doc.body || (win.location.href || '') === 'about:blank' || doc.readyState === 'loading') { return false; }
        if (!/\/(create|edit|compare)(\/|\.|$)/.test(win.location.pathname)) { return false; }
        if (!doc.getElementById('pp-frame-style')) {
            var st = doc.createElement('style');
            st.id = 'pp-frame-style';
            st.textContent = chromeCss();
            doc.head.appendChild(st);
        }
        themeFrame(doc);
        frame.dataset.ready = '1';
        return true;
    }
    function prewarm(url) {
        if (!FM.warm || !url || !(D.features && D.features.prefetch)) { return; }
        if (FM.warm.dataset.url === url) { return; }
        FM.warm.dataset.url = url;
        delete FM.warm.dataset.ready;
        FM.warm.src = url;
    }
    function formModal(url, title, sub) {
        if (!FM.el) { location.href = url; return; }
        FM.el.querySelector('.t').textContent = title || '';
        FM.el.querySelector('.s').textContent = sub || D.project.key || D.project.name || '';
        FM.el.querySelector('.pp-fm-confirm').hidden = true;
        FM.el.hidden = false;
        document.body.classList.add('pp-fm-open');
        FM.lastUrl = url;
        if (FM.warm && FM.warm.dataset.url === url && FM.warm.dataset.ready) {
            var cold = FM.frame;
            FM.frame = FM.warm;
            FM.warm = cold;
            FM.frame.classList.remove('pp-fm-warm');
            FM.warm.classList.add('pp-fm-warm');
            delete FM.frame.dataset.url;
            delete FM.warm.dataset.url;
            delete FM.warm.dataset.ready;
            FM.warm.src = 'about:blank';
            FM.frame.style.visibility = 'visible';
            FM.el.querySelector('.pp-fm-spin').hidden = true;
            FM.snap = fmSerialize(FM.frame.contentDocument);
            // Repaint the warm slot with the same URL: if the user closes
            // and reopens (cancel path), the second open is a hot swap too
            // instead of a cold /admin/data/... GET with all admin chrome.
            setTimeout(function () { prewarm(url); }, 250);
            return;
        }
        FM.el.querySelector('.pp-fm-spin').hidden = false;
        FM.snap = '';
        FM.frame.style.visibility = 'hidden';
        FM.frame.src = url;
    }
    function fmSerialize(doc) {
        try {
            return Array.prototype.map.call(doc.querySelectorAll('form'), function (f) { return $(f).serialize(); }).join('&');
        } catch (e) { return ''; }
    }
    function fmHide() {
        FM.el.hidden = true;
        document.body.classList.remove('pp-fm-open');
        FM.frame.src = 'about:blank';
        // Re-warm whichever URL the user was just on so the very next open
        // (cancel-and-retry, or reopen the same issue's edit) is hot too.
        // Skip if a save just fired location.reload - the tab is going away.
        var url = FM.lastUrl;
        if (url && !FM.reloading) {
            setTimeout(function () { prewarm(url); }, 250);
        }
    }
    function fmClose(force) {
        if (!FM.el || FM.el.hidden) { return; }
        if (!force && FM.frame.style.visibility !== 'hidden') {
            if (fmSerialize(FM.frame.contentDocument) !== FM.snap) {
                FM.el.querySelector('.pp-fm-confirm').hidden = false;
                return;
            }
        }
        fmHide();
    }
    function fmFinish(path) {
        FM.reloading = true;
        fmHide();
        toast(L.m_saved);
        var mi = path.match(new RegExp('/data/' + D.issue_table + '/(\\d+)$'));
        var mw = path.match(new RegExp('/data/' + D.wiki_table + '/(\\d+)$'));
        var hash = mi ? 'issue-' + mi[1] : (mw ? 'wiki-' + mw[1] : null);
        setTimeout(function () {
            FM.reloading = false;
            softReload(hash);
        }, 400);
    }
    // One idempotent sync covers both full loads and pjax swaps inside the
    // frames: style whichever frame is ready, close when the active form
    // leaves for a non-form page (that is what a successful save does).
    function fmSync() {
        if (!alive()) { clearInterval(fmTimer); return; }
        if (FM.warm && FM.warm.dataset.url && !FM.warm.dataset.ready) { styleFrame(FM.warm); }
        if (!FM.el || FM.el.hidden) { return; }
        var win, doc, href, path;
        try {
            win = FM.frame.contentWindow;
            doc = FM.frame.contentDocument;
            href = win.location.href;
            path = win.location.pathname;
        } catch (e) { return; }
        if (!doc || !doc.body || href === 'about:blank' || doc.readyState === 'loading') { return; }
        if (/\/auth\/login/.test(path)) { location.href = href; return; }
        if (!/\/(create|edit|compare)(\/|\.|$)/.test(path)) { fmFinish(path); return; }
        if (styleFrame(FM.frame) && FM.frame.style.visibility === 'hidden') {
            FM.snap = fmSerialize(doc);
            FM.el.querySelector('.pp-fm-spin').hidden = true;
            FM.frame.style.visibility = 'visible';
        }
    }
    if (FM.frame) {
        FM.frame.addEventListener('load', fmSync);
        if (FM.warm) { FM.warm.addEventListener('load', fmSync); }
        var fmTimer = setInterval(fmSync, 400);
        FM.el.querySelector('.pp-fm-x').addEventListener('click', function () { fmClose(); });
        FM.el.querySelector('.pp-fm-back').addEventListener('click', function () { fmClose(); });
        FM.el.querySelector('.pp-fm-confirm .yes').addEventListener('click', function () { fmHide(); });
        FM.el.querySelector('.pp-fm-confirm .no').addEventListener('click', function () {
            FM.el.querySelector('.pp-fm-confirm').hidden = true;
        });
        // form-style picker: click the paint-brush button to toggle the
        // menu, click an entry to apply it (and persist per user).
        var styleBtn = FM.el.querySelector('.pp-fm-style-btn');
        var styleMenu = FM.el.querySelector('.pp-fm-style-menu');
        if (styleBtn && styleMenu) {
            styleBtn.addEventListener('click', function (ev) {
                ev.stopPropagation();
                styleMenu.hidden = !styleMenu.hidden;
                if (!styleMenu.hidden) { renderFormStyleMenu(); }
            });
            styleMenu.addEventListener('click', function (ev) {
                var item = ev.target.closest('.pp-fm-style-item');
                if (!item) { return; }
                ev.stopPropagation();
                applyFormStyle(item.dataset.style, true);
                styleMenu.hidden = true;
            });
            document.addEventListener('click', function onStyleDoc(ev) {
                if (!alive()) { document.removeEventListener('click', onStyleDoc); return; }
                if (!styleMenu.hidden && !ev.target.closest('.pp-fm-style-wrap')) { styleMenu.hidden = true; }
            });
        }
        document.addEventListener('keydown', function onFmKey(ev) {
            if (!alive()) { document.removeEventListener('keydown', onFmKey); return; }
            if (ev.key === 'Escape' && !FM.el.hidden) { fmClose(); }
        });
    }

    /* --------------------------------------- native (no-iframe) edit modal */
    // Alternative to the iframe form modal for the very common "edit an
    // issue" flow: talks to /admin/lightapi/data/{table}/{id}/edit for a
    // JSON schema + current values, renders the form itself, and saves
    // back with a JSON PATCH. No admin chrome, no page reload, no depend-
    // ency on the iCheck / select2 / TinyMCE stack. Everything else
    // (create issue, wiki edit, settings) still uses the iframe path.
    var NED = {
        el: document.getElementById('pp-nedit'),
        mode: 'edit', rowId: null, table: null, columns: null, values: {}, labels: {}, initial: '',
        lookupCache: {}, lookupInflight: {},
        saveUrl: null, saveMethod: 'PATCH', busy: false, canEdit: true,
        epoch: 0, pbarEpoch: 0, draftOwner: '', createSaving: false, uploadingImage: false,
        createFormCache: {}, createFormInflight: {},
        // Per-row edit payload cache. Same shape as createFormCache but
        // keyed by "<table>::<id>" so 編集 / プロジェクト設定 open instantly
        // on the second click (and on the first click if hover-prefetch
        // fired). TTL: within-tab session only — a successful save does a
        // full location.reload() which clears the cache naturally.
        editFormCache: {}, editFormInflight: {}, editFormAt: {},
        // Phase A: Backlog-parity defaults. Phase B enhancements (segmented
        // pills, colored option dots, autosave draft) toggle on via opts.
        opts: { segmentedPills: false }
    };
    var nedPageId = Date.now().toString(36) + '.' + Math.random().toString(36).slice(2);
    var NED_TYPES_WIDE = { text: 0, richtext: 1, file: 1 };
    function nedCurrent(epoch) {
        return alive() && NED.epoch === epoch && NED.el && !NED.el.hidden && NED.el.isConnected;
    }
    function nedRetireContext() {
        if (nedDirty()) { nedDraftSave(); }
        if (nedGuardTimer) { clearTimeout(nedGuardTimer); nedGuardTimer = null; }
        nedGuard(false);
        NED.epoch++;
        NED.pbarEpoch++;
        NED.draftOwner = nedPageId + '.' + NED.epoch;
        NED.busy = false;
        NED.createSaving = false;
        NED.uploadingImage = false;
        NED.createdId = null;
        NED.fromBoard = false;
        NED.force = false;
        NED.columns = null;
        NED.initial = '';
        NED.saveUrl = null;
        NED._payload = null;
        NED._ai = null;
        if (NED.el) {
            NED.el.inert = false;
            var bar = NED.el.querySelector('.pp-ne-pbar');
            if (bar) { bar.hidden = true; }
        }
        return NED.epoch;
    }

    // Every key ships in the lang payload, so a miss means the key is gone.
    // Showing the key keeps that visible instead of printing a stale literal.
    // Test for presence, not truthiness: a deliberately empty label is a
    // valid value and must not fall through to the key name.
    function nedGetLangKey(k, fallback) {
        if (L && typeof L[k] === 'string') { return L[k]; }
        return fallback !== undefined ? fallback : k;
    }
    function nedFieldEl(name) {
        if (!NED.el) { return null; }
        return NED.el.querySelector('.pp-ne-form [data-fname="' + CSS.escape(name) + '"]');
    }
    function nedInputEl(name) {
        var f = nedFieldEl(name);
        return f ? f.querySelector('.pp-ne-in') : null;
    }
    function nedShowAlert(msg, ok) {
        var a = NED.el.querySelector('.pp-ne-alert');
        a.textContent = msg;
        a.hidden = !msg;
        a.classList.toggle('ok', !!ok);
    }

    // Somebody else saved this row while the form was open. Saving now would
    // write the whole of what this form is holding over the whole of what
    // they wrote, and neither screen would ever say so. So it stops here and
    // offers the two honest ways out: read theirs, or knowingly replace it.
    function nedShowConflict(info) {
        var a = NED.el.querySelector('.pp-ne-alert');
        a.classList.remove('ok');
        a.hidden = false;
        a.innerHTML = '';
        var who = info && info.updated_by ? String(info.updated_by) : '';
        var when = info && info.updated_at ? nedFormatMetaDate(info.updated_at_shown || info.updated_at) : '';
        var msg = document.createElement('span');
        msg.textContent = String(nedGetLangKey(who ? 'ned_stale_by' : 'ned_stale'))
            .replace(':name', who).replace(':at', when);
        a.appendChild(msg);

        var reload = document.createElement('button');
        reload.type = 'button';
        reload.className = 'pp-ne-sidebtn';
        reload.style.marginLeft = '10px';
        reload.textContent = nedGetLangKey('ned_stale_reload');
        reload.addEventListener('click', function () {
            // Their version, read fresh. The draft the form keeps is what
            // holds this form's own edits, so nothing typed is gone for good.
            nedDraftSave();
            var table = NED.table, id = NED.rowId;
            if (NED.editFormCache) { delete NED.editFormCache[table + '::' + id]; }
            nedGuard(false);
            openNedit(table, id);
        });
        a.appendChild(reload);

        var force = document.createElement('button');
        force.type = 'button';
        force.className = 'pp-ne-sidebtn';
        force.style.marginLeft = '6px';
        force.textContent = nedGetLangKey('ned_stale_force');
        force.addEventListener('click', function () {
            NED.force = true;
            nedSave();
        });
        a.appendChild(force);
    }

    /* ----------------------------------------- unsaved-work protection --- */
    // nedClose() already asks before throwing edits away, but it only sees the
    // ways out that go through it: 戻る, the X, Escape. Every admin link is a
    // pjax navigation that leaves the page without touching it, and the modal
    // shares the viewport with the admin menu. beforeunload is the only hook
    // that covers those, and localStorage is the only thing that survives a
    // crash, a session timeout or a mis-click that gets confirmed anyway.
    function nedBeforeUnload(ev) {
        // A pjax swap never fires unload, so a stale instance's guard would
        // survive it and nag on every later navigation - and nedGuard(false)
        // from the new instance compares against its OWN function, so it
        // could never clear this one. A dead page has nothing left to lose.
        if (!alive()) { nedGuard(false); return; }
        // Browsers substitute their own wording; returning any string at all
        // is what actually raises the prompt.
        ev.preventDefault();
        ev.returnValue = '';

        return '';
    }
    function nedGuard(on) {
        if (on) { window.onbeforeunload = nedBeforeUnload; }
        else if (window.onbeforeunload === nedBeforeUnload) { window.onbeforeunload = null; }
    }
    function nedDirty() {
        if (!NED.el || NED.el.hidden || !NED.canEdit || !NED.columns) { return false; }
        if (NED.uploadingImage) { return true; }
        return nedSerialize() !== NED.initial;
    }
    // Debounced: nedSerialize() stringifies the whole form, body text and all,
    // and the caller is a keystroke handler.
    var nedGuardTimer = null;
    function nedSyncGuard() {
        if (nedGuardTimer) { clearTimeout(nedGuardTimer); }
        var epoch = NED.epoch;
        nedGuardTimer = setTimeout(function () {
            nedGuardTimer = null;
            if (!nedCurrent(epoch)) { return; }
            var dirty = nedDirty();
            nedGuard(dirty);
            if (dirty) { nedDraftSave(); }
        }, 250);
    }

    /* ----------------------------------------- back button --------------- */
    // Where #new came from, so closing the create panel puts the reader back
    // on the screen they left - filter query and all. Written by the hash
    // listener, which is the only thing that still knows: by the time the
    // router runs, the hash it replaced is gone. Null after a cold load
    // straight onto #new, where there is no screen to go back to.
    var NED_BACK = null;
    // The address a panel is open at, when the hash is what opened it, and
    // the hash to put back when it closes. Written by the router, read by
    // nedClose. Null while the panel on screen has no address of its own -
    // the wiki and project editors, which are opened in place.
    var NED_HASH = null;
    // True for the one open the hash is performing. See nedPushHistory.
    var NED_HASH_OPEN = false;
    // On a phone the back gesture is how people close things. With no entry of
    // our own it walks out of the portal entirely and takes the edit with it.
    // The pushed entry reuses the CURRENT url on purpose: the hash does not
    // change, so only popstate fires and the hash router never sees it.
    // Overlay mounts only - the inline mounts sit inside a screen the hash
    // router already governs, and giving them a second mechanism would mean
    // two competing owners of the same back press.
    function nedPushHistory() {
        if (!history.pushState || NED._histPushed || NED._target !== 'overlay') { return; }
        // The same reason, for the one overlay that does have an address:
        // #board?edit=<id> is already an entry for this panel, so a second
        // one would mean two back presses to leave it.
        if (NED_HASH_OPEN) { return; }
        try {
            history.pushState({ ppModal: 1 }, '', location.href);
            NED._histPushed = true;
        } catch (e) { /* ignore */ }
    }
    // Consume our entry when the modal closes any other way, so the back button
    // does not have to be pressed twice afterwards to leave the screen.
    function nedPopHistory() {
        if (!NED._histPushed) { return; }
        NED._histPushed = false;
        NED._histSelfPop = true;
        history.back();
    }
    window.addEventListener('popstate', function onNedPop() {
        if (!alive()) { window.removeEventListener('popstate', onNedPop); return; }
        if (NED._histSelfPop) { NED._histSelfPop = false; return; }
        if (!NED.el || NED.el.hidden || !NED._histPushed) { return; }
        // The entry is already gone by the time this fires.
        NED._histPushed = false;
        if (!nedClose()) { nedPushHistory(); }
    });

    /* ----------------------------------------- draft autosave ------------ */
    // Drafts are kept per person and per project, and a create form's per
    // kind of start: a plain new issue, the child of a given issue, the copy
    // of a given issue. One slot for all of them handed the next user of a
    // shared browser the last one's unsent text, and let a draft typed in one
    // project be restored - status, milestone and category ids included -
    // into an issue of another.
    var DRAFT_PREFIX = 'pmportal.draft2.';
    // How long a draft is offered back. It is for the crash, the expired
    // session, the tab closed by mistake - not for months-old text that
    // would only be restored over a row that has moved on since.
    var DRAFT_MAX_AGE = 7 * 86400000;
    function nedDraftKey() {
        if (!NED.table) { return null; }
        var who = 'u' + String(D.me || 0) + '.p' + String((D.project && D.project.id) || 0);
        var what = NED.mode === 'create' ? 'new' + (NED.draftTag ? '.' + NED.draftTag : '') : String(NED.rowId);

        return DRAFT_PREFIX + who + '.' + NED.table + '.' + what;
    }
    function nedDraftSave() {
        var key = nedDraftKey();
        if (!key) { return; }
        try {
            localStorage.setItem(key, JSON.stringify({
                v: JSON.parse(nedSerialize()),
                // The server baseline this draft was typed on top of, and the
                // version of the row it was read from. Restoring puts back
                // only what the draft changed against that baseline, and the
                // save quotes that version, so a row that has moved on since
                // is refused and shown rather than quietly overwritten.
                base: NED.initial,
                ua: NED.draftBaseAt || null,
                owner: NED.draftOwner,
                at: Date.now()
            }));
        } catch (e) { /* quota or private mode: a draft is a bonus, not a duty */ }
    }
    function nedDraftRead() {
        var key = nedDraftKey();
        if (!key) { return null; }
        try {
            var raw = localStorage.getItem(key);
            if (!raw) { return null; }
            var d = JSON.parse(raw);
            if (!d || !d.v) { return null; }
            if (!(Date.now() - Number(d.at) < DRAFT_MAX_AGE)) { localStorage.removeItem(key); return null; }

            return d;
        } catch (e) { return null; }
    }
    // What a draft changed against the form it was typed on, field by field.
    function nedDraftOwn(d) {
        var base = {};
        try { base = d.base ? JSON.parse(d.base) : {}; } catch (e) { base = {}; }
        var out = {};
        Object.keys(d.v || {}).forEach(function (name) {
            if (JSON.stringify(d.v[name]) !== JSON.stringify(own(base, name))) { out[name] = d.v[name]; }
        });

        return out;
    }
    // Once per page: drafts past their age go, and so do the slots from
    // before drafts were per person (pmportal.draft.*) - nobody can tell any
    // more whose they were, and none of them would ever be offered again.
    function nedDraftPurge() {
        try {
            var now = Date.now();
            var drop = [];
            for (var n = 0; n < localStorage.length; n++) {
                var key = localStorage.key(n);
                if (!key) { continue; }
                if (key.indexOf('pmportal.draft.') === 0) { drop.push(key); continue; }
                if (key.indexOf(DRAFT_PREFIX) !== 0) { continue; }
                var d = null;
                try { d = JSON.parse(localStorage.getItem(key)); } catch (e) { d = null; }
                if (!d || !(now - Number(d.at) < DRAFT_MAX_AGE)) { drop.push(key); }
            }
            drop.forEach(function (key) { localStorage.removeItem(key); });
        } catch (e) { /* storage unavailable */ }
    }
    function nedDraftClear(key, owner, submitted) {
        key = key || nedDraftKey();
        if (!key) { return; }
        try {
            if (owner !== undefined) {
                var raw = localStorage.getItem(key);
                var draft = raw && JSON.parse(raw);
                if (!draft || draft.owner !== owner) { return; }
                if (submitted !== undefined && JSON.stringify(draft.v) !== submitted) { return; }
            }
            localStorage.removeItem(key);
        } catch (e) { /* ignore */ }
    }
    function nedDraftBar(show) {
        var bar = NED.el && NED.el.querySelector('.pp-ne-draft');
        if (bar) { bar.hidden = !show; }
    }
    // Called once the form is painted and NED.initial is set. A draft equal to
    // what is already on screen is noise, so drop it instead of asking.
    function nedDraftOffer() {
        var d = nedDraftRead();
        if (!d) { nedDraftBar(false); return; }
        // Nothing to give back: it says what the form already says, or it
        // changed nothing against the form it was typed on.
        if (JSON.stringify(d.v) === NED.initial || !Object.keys(nedDraftOwn(d)).length) {
            nedDraftClear();
            nedDraftBar(false);
            return;
        }
        nedDraftBar(true);
    }
    function nedDraftRestore() {
        var d = nedDraftRead();
        if (!d || !NED._payload) { nedDraftBar(false); return; }
        // Re-render off the original payload with the draft's values merged in.
        // Going through nedRenderForm keeps every field type - lookups, dates,
        // checkboxes - populated by the same code that populates them normally,
        // instead of a second setter that would drift out of step with it.
        //
        // Only the fields the draft changed are put back. The rest of it is
        // whatever the row said when it was typed, and writing that over the
        // form would undo whoever has changed those fields since.
        var serverValues = NED.values;
        var serverInitial = NED.initial;
        var serverBaseAt = NED.draftBaseAt;
        var p = $.extend(true, {}, NED._payload);
        p.row = p.row || {};
        p.row.value = $.extend({}, p.row.value, nedDraftOwn(d));
        nedDraftBar(false);
        nedRenderForm(p, true);
        // Restore the server defaults and the serialized diff/dirty baseline.
        NED.values = serverValues;
        NED.initial = serverInitial;
        NED.draftBaseAt = serverBaseAt;
        // The save quotes the version the draft was typed against: if the
        // row has moved on since, the server says so instead of accepting
        // the old picture of it.
        if (NED.mode !== 'create' && d.ua) { NED.rowUpdatedAt = d.ua; }
        nedSyncGuard();
    }
    // Route the modal into the right host container so it visually replaces
    // the section the user asked to work on:
    //   edit-issue    → sits in place of #pp-d-main (issue detail's main card)
    //   create-issue  → sits in place of #pp-issue (issue detail screen)
    //   edit-project  → sits in place of #pp-issue (project settings uses same slot)
    //   overlay       → falls back to the old full-viewport overlay (wiki, etc.)
    function nedResolveMountTarget() {
        // opened from a screen that is not the issue detail - the board -
        // there is no detail card on display to put the editor inside
        if (NED.forceTarget) { return NED.forceTarget; }
        if (NED.mode === 'edit') {
            if (NED.table === D.issue_table) { return 'edit-issue'; }
            if (NED.table === D.project_table) { return 'edit-project'; }
        } else if (NED.mode === 'create') {
            if (NED.table === D.issue_table) { return 'create-issue'; }
        }
        return 'overlay';
    }
    function nedMount(target) {
        if (!NED.el) { return; }
        // Undo any lingering mount state so a second open (e.g. 編集 →
        // プロジェクト設定 without a close in between) starts from a
        // clean slate — otherwise a hidden card / demoted screen would
        // never come back.
        if (NED._hidden) {
            NED._hidden.forEach(function (t) { t.el.style.display = t.prev; });
            NED._hidden = null;
        }
        if (NED._prevScreen) {
            NED._prevScreen.classList.add('on');
            NED._prevScreen = null;
        }
        // Remember original DOM home once so we can put the panel back
        // where the Blade template left it after every close.
        if (!NED._home) {
            NED._home = { parent: NED.el.parentNode, next: NED.el.nextSibling };
        }
        NED.el.classList.remove('pp-nedit-overlay', 'pp-nedit-inline-main', 'pp-nedit-inline-screen');
        // 'pmportal' is only borrowed while the panel lives on <body>; an
        // inline mount is already inside the real #pmportal and does not want
        // a second one nested in it.
        NED.el.classList.remove('bl-screen', 'on', 'pmportal');
        document.body.classList.remove('pp-fm-open');
        NED._target = target;

        if (target === 'edit-issue') {
            var mainEl = document.getElementById('pp-d-main');
            var mainCard = mainEl ? mainEl.closest('.bl-card-box') : null;
            if (mainCard && mainCard.parentNode) {
                // The editor brings its own 基本情報 card, so the read-only one
                // steps aside: two of them stacked would say the same thing
                // twice and only one of them would be live.
                var propsEl = document.getElementById('pp-d-props');
                var propsCard = propsEl ? propsEl.closest('.bl-card-box') : null;
                NED._hidden = [{ el: mainCard, prev: mainCard.style.display || '' }];
                if (propsCard) {
                    NED._hidden.push({ el: propsCard, prev: propsCard.style.display || '' });
                    propsCard.style.display = 'none';
                }
                mainCard.style.display = 'none';
                mainCard.parentNode.insertBefore(NED.el, mainCard);
                NED.el.classList.add('pp-nedit-inline-main');
                // The editor takes the issue's place on screen, so the
                // composer pinned under the issue steps aside while it is
                // open. ppDetailEditing(false) puts it back on unmount.
                ppDetailEditing(true);
                return;
            }
        } else if (target === 'create-issue' || target === 'edit-project') {
            var issueSection = document.getElementById('pp-issue');
            if (issueSection && issueSection.parentNode) {
                NED._prevScreen = root.querySelector('.bl-screen.on');
                root.querySelectorAll('.bl-screen').forEach(function (s) { s.classList.remove('on'); });
                issueSection.parentNode.insertBefore(NED.el, issueSection);
                NED.el.classList.add('pp-nedit-inline-screen', 'bl-screen', 'on');
                window.scrollTo(0, 0);
                return;
            }
        }
        // Overlay fallback (wiki edit/create, or if the target host is missing).
        //
        // Re-home the panel on <body> first. It is born inside
        // #main.content-wrapper, which carries z-index:800, and a z-index on a
        // flex item creates a stacking context - so every descendant is
        // trapped at 800 however high it sets its own z-index. The admin
        // sidebar sits at 810 and the header at 1030, both siblings of that
        // wrapper, so the "full viewport" overlay used to leave the whole
        // admin chrome painted on top of itself and still clickable. One
        // stray click on the menu is a pjax navigation, and the half-written
        // wiki page goes with it.
        //
        // Carry the 'pmportal' class along on the way out. Half the portal's
        // stylesheet is written as ".pmportal <thing>", the modal's own Save
        // and 戻る buttons included, and off <body> there is no .pmportal
        // ancestor left to satisfy it. Putting the class on the panel itself
        // makes those descendant selectors match again, and brings the
        // palette with it: the --bl-* variables are declared on .pmportal
        // for exactly this reason.
        NED.el.classList.add('pp-nedit-overlay', 'pmportal');
        if (NED.el.parentNode !== document.body) {
            document.body.appendChild(NED.el);
        }
        document.body.classList.add('pp-fm-open');
        nedSizeOverlay();
        nedWatchOverlaySize(true);
    }
    // The modal is eight tenths of the content area, centred in it. Measured
    // rather than guessed: the admin sidebar is 230px until somebody collapses
    // it to 50, and a box centred on the viewport is off by half of whichever
    // it currently is.
    function nedSizeOverlay() {
        // Bound on window while the overlay is open; a page pjax replaced
        // with the overlay still up lets go of it here.
        if (!alive()) { nedWatchOverlaySize(false); return; }
        if (!NED.el || NED._target !== 'overlay') { return; }
        var wrap = document.querySelector('.content-wrapper');
        var s = NED.el.style;
        if (!wrap) {
            // No admin chrome to measure: fall back to the stylesheet, which
            // centres on the viewport.
            s.removeProperty('--pp-ne-w');
            s.removeProperty('--pp-ne-l');
            s.removeProperty('--pp-ne-tx');
            return;
        }
        var r = wrap.getBoundingClientRect();
        // Narrow enough that a tenth off each side would leave nothing to
        // read in: take the width and keep a margin instead.
        var pad = r.width < 820 ? 8 : r.width * 0.1;
        s.setProperty('--pp-ne-w', Math.round(r.width - pad * 2) + 'px');
        s.setProperty('--pp-ne-l', Math.round(r.left + pad) + 'px');
        s.setProperty('--pp-ne-tx', '0px');
    }
    // Re-measured while the modal is open: collapsing the sidebar resizes the
    // content area without resizing the window, so a window listener alone
    // would miss it.
    function nedWatchOverlaySize(on) {
        if (!on) {
            if (NED._ro) { NED._ro.disconnect(); NED._ro = null; }
            window.removeEventListener('resize', nedSizeOverlay);
            return;
        }
        window.addEventListener('resize', nedSizeOverlay);
        if (NED._ro || typeof ResizeObserver !== 'function') { return; }
        var wrap = document.querySelector('.content-wrapper');
        if (!wrap) { return; }
        NED._ro = new ResizeObserver(function () { nedSizeOverlay(); });
        NED._ro.observe(wrap);
    }
    function nedUnmount() {
        if (!NED.el) { return; }
        nedWatchOverlaySize(false);
        ['--pp-ne-w', '--pp-ne-l', '--pp-ne-tx'].forEach(function (p) {
            NED.el.style.removeProperty(p);
        });
        document.body.classList.remove('pp-fm-open');
        NED.el.classList.remove('pp-nedit-overlay', 'pp-nedit-inline-main', 'pp-nedit-inline-screen');
        // 'pmportal' is only borrowed while the panel lives on <body>; an
        // inline mount is already inside the real #pmportal and does not want
        // a second one nested in it.
        NED.el.classList.remove('bl-screen', 'on', 'pmportal');
        if (NED._hidden) {
            NED._hidden.forEach(function (t) { t.el.style.display = t.prev; });
            NED._hidden = null;
        }
        if (NED._prevScreen) {
            NED._prevScreen.classList.add('on');
            NED._prevScreen = null;
        }
        if (NED._home && NED._home.parent) {
            NED._home.parent.insertBefore(NED.el, NED._home.next);
        }
        NED._home = null;
        NED._target = null;
        ppDetailEditing(false);
    }
    // Returns true when the modal actually closed, false when the user chose
    // to keep editing - the popstate handler needs to know which happened so
    // it can put its history entry back.
    function nedClose() {
        if (!NED.el || NED.el.hidden) { return true; }
        if (NED.createSaving) { return false; }
        // If there are unsaved edits, confirm before closing. The draft stays
        // in localStorage either way: someone who discards by accident should
        // still be offered it back on the next open.
        if (nedDirty()) {
            if (!window.confirm(nedGetLangKey('m_discard'))) { return false; }
        }
        var refreshBoard = NED.fromBoard && NED.portalStale;
        var savedId = NED.rowId, savedTable = NED.table;
        nedRetireContext();
        nedDraftBar(false);
        nedPopHistory();
        // A panel's address must not outlive the panel. Left standing, the link
        // that opens it would set a hash that is already set: no hashchange
        // fires, nothing happens, and the control looks broken. replaceState
        // rather than a hash write, because nedUnmount has already put back
        // what was behind - there is nothing left to route.
        if (NED_HASH && history.replaceState && location.hash.slice(1) === NED_HASH.at) {
            history.replaceState(null, '', NED_HASH.back);
        }
        NED_HASH = null;
        NED.el.hidden = true;
        nedUnmount();
        nedClearLayout();
        NED.el.querySelector('.pp-ne-form').hidden = true;
        NED.el.querySelector('.pp-ne-foot').hidden = true;
        NED.el.querySelector('.pp-ne-spin').hidden = false;
        nedShowAlert('', false);
        NED.rowId = null; NED.columns = null; NED.initial = ''; NED._payload = null;
        if (refreshBoard) { nedRefreshBoard(savedId, savedTable); }

        return true;
    }
    // Wipe the field slots but keep the hero/main/side skeleton so the
    // next open can populate them without rebuilding the layout each time.
    function nedClearLayout() {
        var form = NED.el.querySelector('.pp-ne-form');
        if (!form) { return; }
        ['pp-ne-top-row', 'pp-ne-title-row', 'pp-ne-desc-row',
         'pp-ne-props', 'pp-ne-files-row'].forEach(function (cls) {
            var el = form.querySelector('.' + cls);
            if (el) { el.innerHTML = ''; }
        });
        // Hide optional sections until nedRenderForm decides they have content.
        ['pp-ne-top-row', 'pp-ne-desc-row', 'pp-ne-files-row'].forEach(function (cls) {
            var el = form.querySelector('.' + cls);
            if (el) { el.hidden = true; }
        });
    }
    // Ensure the layout slots exist as direct children of the form:
    //   .pp-ne-top-row    — parent issue and type, the two fields that frame
    //                       everything below them
    //   .pp-ne-title-row  — subject (full-width, document-title style)
    //   .pp-ne-desc-row   — description (textarea / richtext, full-width panel)
    //   .pp-ne-props      — the metadata fields, one to a row, inside the
    //                       基本情報 card the issue screen already shows. The
    //                       card does not move or change shape when editing
    //                       starts; only what each row holds changes.
    //   .pp-ne-files-row  — file drop zone
    //
    // One field to a row, not two. Two columns can only line up by accident:
    // one help line on the left and every row below it sits half a line lower
    // than the row it is meant to be beside - and the read-only card they
    // stand in for has always been one field to a row.
    // Idempotent: creates any missing slot and clears innerHTML on every call.
    function nedEnsureLayout() {
        var form = NED.el.querySelector('.pp-ne-form');
        // Every re-render empties these slots, date fields and all.
        if (window.ppDateFieldDestroy) { window.ppDateFieldDestroy(form); }
        // Fast path: if we already built the 5 slots for a previous open
        // and they're still attached to the form, just clear their
        // children instead of re-running querySelector / createElement.
        // Idempotent with the ensure() rebuild path — the DOM shape and
        // slot identity are exactly the same either way.
        if (NED._slots && NED._slots.title.parentNode === form) {
            var s = NED._slots;
            s.top.innerHTML = '';
            s.title.innerHTML = '';
            s.desc.innerHTML = '';
            s.grid.innerHTML = '';
            s.files.innerHTML = '';
            s.top.hidden = true;
            s.desc.hidden = true;
            s.files.hidden = true;
            return s;
        }
        function ensure(sel, parent, tag) {
            var el = parent.querySelector(':scope > .' + sel);
            if (!el) {
                el = document.createElement(tag || 'div');
                el.className = sel;
                parent.appendChild(el);
            }
            el.innerHTML = '';
            return el;
        }
        var top = ensure('pp-ne-top-row', form, 'div');
        var title = ensure('pp-ne-title-row', form, 'div');
        var desc = ensure('pp-ne-desc-row', form, 'div');
        // The metadata grid lives inside a card, so it is found rather than
        // made: ensure() only looks at the form's own children and would
        // build a second, bare copy at the end of the form.
        var grid = form.querySelector('.pp-ne-props')
            || ensure('pp-ne-props', form, 'div');
        grid.innerHTML = '';
        var files = ensure('pp-ne-files-row', form, 'div');
        top.hidden = true;
        desc.hidden = true;
        files.hidden = true;
        NED._slots = { top: top, title: title, desc: desc, grid: grid, files: files };
        return NED._slots;
    }
    // The issue form's shape, written in field names rather than left to
    // whatever order the columns were created in. Backlog's own arrangement:
    // the two fields that frame the issue above the subject, the text in the
    // middle, and metadata in pairs that belong together - the two dates on
    // one row, the two hour counts on the next.
    //
    // Names come from the def map and from the schema upgrade's own list, so
    // an install that renamed a column, or has not run the upgrade and simply
    // has not got one, lays out what it actually has. Every other table falls
    // through with a null and keeps the old behaviour.
    function nedIssueLayout() {
        if (!D.issue_table || NED.table !== D.issue_table) { return null; }
        var c = D.issue_cols || {};
        var x = D.issue_extra_cols || {};
        // Two to a row on a wide card (each pair starts a row, see
        // nedRenderForm), so the pairs are fields read together.
        // rows: Backlog's eight, always shown. more: the rest, folded under
        // 「その他の項目」 (1.8.0) - it opens by itself when one of them has a
        // value or an error, and stays open for whoever opened it.
        var keep = function (r) { return r.some(Boolean); };
        var rows = [
            [c.status || null, c.assignees || null],
            [c.priority || null, c.milestone || null],
            [c.category || null, x.versions || null],
            [c.start || null, c.due || null],
            // the time of the due date, under it: folded away it was not found when an
            // issue was added (ISS02923)
            [null, x.due_time || null]
        ].filter(keep);
        var more = [
            // Schema 27: the other people, beside the assignees.
            [x.sub_assignees || null, x.managers || null],
            [x.reviewers || null, x.notified_users || null],
            [x.difficulty || null, x.recurrence || null],
            [x.subject_sub || null, null],
            [c.estimate || null, c.actual || null],
            [c.progress || null, x.resolution || null]
        ].filter(keep);

        return {
            top: [c.parent || null, c.type || null].filter(Boolean),
            rows: rows,
            more: more
        };
    }
    // A column with nothing to say until the row exists. A resolution is how
    // an issue was closed, and an issue being created has not been.
    function nedSkipOnCreate(col) {
        if (NED.mode !== 'create' || NED.table !== D.issue_table) { return false; }
        var x = D.issue_extra_cols || {};

        return !!x.resolution && col.name === x.resolution;
    }
    // Auto-pick the "headline" column: the first non-readonly text-ish
    // field, which by convention is the record's title/subject/name.
    function nedPickHeroColumn(cols) {
        for (var i = 0; i < cols.length; i++) {
            var c = cols[i];
            if (c.readonly) { continue; }
            if (c.type !== 'text') { continue; }
            // Skip textarea/richtext (they belong to the description panel).
            if (c.raw_type === 'textarea') { continue; }
            return c;
        }
        return null;
    }
    // First long-form column: textarea / richtext. Renders as a full-width
    // panel between title and metadata grid, matching Backlog's layout.
    function nedPickDescColumn(cols) {
        for (var i = 0; i < cols.length; i++) {
            var c = cols[i];
            if (c.readonly) { continue; }
            // the custom attributes' JSON is a textarea too, and never the text (ISS02939)
            if (c.name === nedAtColumnName()) { continue; }
            if (c.type === 'richtext') { return c; }
            if (c.type === 'text' && c.raw_type === 'textarea') { return c; }
        }
        return null;
    }
    // First file/image column: shown as its own drop-zone strip below the grid.
    function nedPickFileColumn(cols) {
        for (var i = 0; i < cols.length; i++) {
            if (cols[i].type === 'file') { return cols[i]; }
        }
        return null;
    }
    // Fields the modal never renders as inputs: identity, timestamps,
    // audit columns, soft-delete, parent link, and anything the backend
    // marked read-only (auto_number, calc). The user asked us to hide
    // rows that aren't editable so the form focuses on what they can
    // actually change; timestamps/creator are surfaced separately in
    // the header's meta strip so no useful information is lost.
    function nedIsHiddenColumn(col) {
        if (col.readonly) { return true; }
        if (col.name === 'id' || col.name === 'created_at' || col.name === 'updated_at') { return true; }
        if (col.name === 'created_user_id' || col.name === 'updated_user_id') { return true; }
        if (col.name === 'suuid' || col.name === 'deleted_at' || col.name === 'deleted_user_id') { return true; }
        if (col.name === 'parent_id' || col.name === 'parent_type') { return true; }
        // The custom attributes: one block of their own, never the raw JSON
        // (nedAtMount, ISS02939).
        if (NED.table === D.issue_table && col.name === nedAtColumnName()) { return true; }
        return false;
    }
    // ---- searchable chip picker ----------------------------------------
    // One widget for every relation-ish field, here and in the settings
    // tab (exported as window.ppMakePicker). A plain <select> filled with
    // the first N rows of a table can never reach row N+1 - on a 10k-row
    // user table that means most users simply cannot be picked. This one
    // searches server-side through lookup_url?q= (the endpoint answers 50
    // rows at most) and keeps what was chosen as chips, so the current
    // value never depends on what the top-N happened to contain.
    //
    // The value rides in a hidden <select> carrying opts.valueClass:
    // every serializer, differ and validator that read the old visible
    // <select> keeps working against this one unchanged.
    function ppMakePicker(opts) {
        var multiple = !!opts.multiple;
        var readonly = !!opts.readonly;
        var texts = opts.texts || {};

        var root = document.createElement('div');
        root.className = 'pp-lkp' + (readonly ? ' readonly' : '');

        var select = document.createElement('select');
        if (multiple) { select.multiple = true; }
        select.hidden = true;
        select.tabIndex = -1;
        if (opts.valueClass) { select.className = opts.valueClass; }

        var box = document.createElement('div');
        box.className = 'pp-lkp-box';
        var q = document.createElement('input');
        q.type = 'text';
        q.className = 'pp-lkp-q';
        q.placeholder = texts.search || '';
        q.autocomplete = 'off';
        if (readonly) { q.disabled = true; }

        var dd = document.createElement('div');
        dd.className = 'pp-lkp-dd';
        dd.hidden = true;

        var chosen = [];
        (opts.values || []).forEach(function (v) {
            if (v && v.value != null && v.value !== '') {
                chosen.push({ value: String(v.value), label: v.label || String(v.value) });
            }
        });
        if (!multiple && chosen.length > 1) { chosen = chosen.slice(0, 1); }

        function sync() {
            select.innerHTML = '';
            if (!multiple) {
                // The empty option is what makes "nothing chosen" read back
                // as '' instead of the first real option.
                var none = document.createElement('option');
                none.value = ''; none.textContent = '';
                select.appendChild(none);
            }
            chosen.forEach(function (c) {
                var opt = document.createElement('option');
                opt.value = c.value; opt.textContent = c.label; opt.selected = true;
                select.appendChild(opt);
            });
            if (!multiple && !chosen.length) { select.value = ''; }
            renderChips();
        }
        function renderChips() {
            Array.prototype.slice.call(box.querySelectorAll('.pp-lkp-chip')).forEach(function (n) { n.remove(); });
            chosen.forEach(function (c) {
                var chip = document.createElement('span');
                chip.className = 'pp-lkp-chip';
                var tx = document.createElement('span'); tx.textContent = c.label;
                chip.appendChild(tx);
                // Only a field that holds several values needs a cross on each
                // one. Where one value is all there is, picking another
                // replaces it and the dropdown carries the way to empty it.
                if (!readonly && multiple) {
                    var x = document.createElement('button');
                    x.type = 'button'; x.className = 'pp-lkp-x'; x.textContent = '×';
                    x.addEventListener('click', function (ev) {
                        ev.stopPropagation();
                        removeValue(c.value);
                    });
                    chip.appendChild(x);
                }
                box.insertBefore(chip, q);
            });
        }
        function announce() {
            select.dispatchEvent(new Event('input', { bubbles: true }));
            select.dispatchEvent(new Event('change', { bubbles: true }));
        }
        function addValue(v, label) {
            if (!multiple) { chosen = []; }
            if (!chosen.some(function (c) { return c.value === String(v); })) {
                chosen.push({ value: String(v), label: label });
            }
            sync(); announce();
            q.value = '';
            if (multiple) { runSearch(''); q.focus(); } else { closeDd(); }
        }
        function removeValue(v) {
            chosen = chosen.filter(function (c) { return c.value !== String(v); });
            sync(); announce();
        }

        // Search results per typed term, so flicking between two queries
        // costs nothing the second time. The empty term rides the shared
        // nedFetchLookup cache, which the form prewarms on open.
        var seq = 0, qCache = {}, timer = null, active = -1;
        function runSearch(term) {
            var my = ++seq;
            var key = String(term || '');
            var paint = function (list) { if (my === seq) { renderDd(list); } };
            if (key === '') { nedFetchLookup(opts.url).then(paint); return; }
            if (qCache[key]) { paint(qCache[key]); return; }
            var sep = opts.url.indexOf('?') >= 0 ? '&' : '?';
            ppQueue(function () {
                return fetch(opts.url + sep + 'limit=30&q=' + encodeURIComponent(key), {
                    headers: { Accept: 'application/json' }, credentials: 'same-origin'
                }).then(function (r) { return r.ok ? r.json() : { results: [] }; });
            })
                .then(function (j) { qCache[key] = j.results || []; paint(qCache[key]); })
                .catch(function () { paint([]); });
        }
        function renderDd(list) {
            dd.innerHTML = '';
            var have = {};
            chosen.forEach(function (c) { have[c.value] = true; });
            var shown = 0;
            // A single-value field has no cross on its chip, so emptying it
            // is offered here instead - and only once there is something to
            // empty, so a field that was never filled shows no such row.
            if (!multiple && !readonly && chosen.length) {
                var none = document.createElement('div');
                none.className = 'pp-lkp-none';
                none.textContent = texts.none || L.empty;
                none.addEventListener('mousedown', function (ev) {
                    ev.preventDefault();
                    chosen = [];
                    sync(); announce();
                    q.value = '';
                    closeDd();
                });
                dd.appendChild(none);
            }
            (list || []).forEach(function (r) {
                if (have[String(r.value)]) { return; }
                var op = document.createElement('div');
                op.className = 'pp-lkp-op';
                op.textContent = r.label;
                op.setAttribute('data-v', String(r.value));
                op.addEventListener('mousedown', function (ev) {
                    // mousedown, not click: the input's blur would close the
                    // dropdown before a click ever landed on it.
                    ev.preventDefault();
                    addValue(r.value, r.label);
                });
                dd.appendChild(op);
                shown++;
            });
            if (!shown) {
                var em = document.createElement('div');
                em.className = 'pp-lkp-empty';
                em.textContent = texts.empty || '-';
                dd.appendChild(em);
            }
            dd.hidden = false;
            active = -1;
        }
        function closeDd() { dd.hidden = true; active = -1; }
        function moveActive(delta) {
            var ops = dd.querySelectorAll('.pp-lkp-op');
            if (!ops.length) { return; }
            active = (active + delta + ops.length) % ops.length;
            Array.prototype.forEach.call(ops, function (o, i) { o.classList.toggle('on', i === active); });
            ops[active].scrollIntoView({ block: 'nearest' });
        }

        if (!readonly) {
            box.addEventListener('mousedown', function (ev) {
                if (ev.target === q) {
                    // The box keeps focus after a pick, so a second click on
                    // the input fires no focus event. Without this the list
                    // could only be reopened from the keyboard.
                    if (dd.hidden) { runSearch(q.value.trim()); }
                    return;
                }
                ev.preventDefault();
                q.focus();
            });
            q.addEventListener('focus', function () { runSearch(q.value.trim()); });
            q.addEventListener('input', function () {
                if (timer) { clearTimeout(timer); }
                timer = setTimeout(function () { runSearch(q.value.trim()); }, 250);
            });
            q.addEventListener('keydown', function (ev) {
                if (ev.key === 'ArrowDown') {
                    ev.preventDefault();
                    if (dd.hidden) { runSearch(q.value.trim()); } else { moveActive(1); }
                    return;
                }
                if (ev.key === 'ArrowUp') { ev.preventDefault(); moveActive(-1); return; }
                if (ev.key === 'Enter') {
                    if (!dd.hidden) {
                        ev.preventDefault();
                        var ops = dd.querySelectorAll('.pp-lkp-op');
                        var pick = active >= 0 ? ops[active] : ops[0];
                        if (pick) { addValue(pick.getAttribute('data-v'), pick.textContent); }
                    }
                    return;
                }
                if (ev.key === 'Escape') {
                    if (!dd.hidden) { ev.stopPropagation(); closeDd(); }
                    return;
                }
                if (ev.key === 'Backspace' && q.value === '' && chosen.length) {
                    removeValue(chosen[chosen.length - 1].value);
                }
            });
            q.addEventListener('blur', function () {
                // Give a mousedown on an option time to run first.
                setTimeout(closeDd, 120);
            });
        }

        box.appendChild(q);
        root.appendChild(box);
        root.appendChild(dd);
        root.appendChild(select);
        sync();

        return {
            root: root,
            select: select,
            // For the buttons that stand next to one: "me", and the row the
            // quick add box has just created.
            add: function (v, label) { if (!readonly) { addValue(v, label); } },
            // For the AI fill and its undo: replace the whole selection, and
            // read it back with labels so it can be put back as it was.
            set: function (list) {
                if (readonly) { return; }
                chosen = [];
                (list || []).forEach(function (x) {
                    if (x && x.value != null && x.value !== '') {
                        chosen.push({ value: String(x.value), label: x.label || String(x.value) });
                    }
                });
                if (!multiple && chosen.length > 1) { chosen = chosen.slice(0, 1); }
                sync(); announce();
            },
            values: function () {
                return chosen.map(function (c) { return { value: c.value, label: c.label }; });
            },
            focus: function () { try { q.focus(); } catch (e) { /* ignore */ } }
        };
    }
    window.ppMakePicker = ppMakePicker;

