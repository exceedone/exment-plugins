    var root = document.getElementById('pmportal');
    if (!root || root.dataset.ppBound) { return; }
    root.dataset.ppBound = '1';

    // Exment swaps admin pages through pjax, which replaces #pmportal but
    // leaves everything this run bound to window/document alive, closures
    // and all. A leftover route() from the previous project cannot resolve
    // the new project's issue keys, so it would bounce every #issue-KEY
    // click straight back to #issues - which is exactly what a portal that
    // has been navigated between projects used to do. Every handler and
    // timer that outlives the root therefore opens with this check and
    // retires itself the moment its page is gone.
    function alive() { return document.getElementById('pmportal') === root; }

    var D = JSON.parse(document.getElementById('pmportal-data').textContent);
    // The page sends its list rows packed (PmPortalController::packRows):
    // put back what was left out before anything reads them, so every row
    // looks exactly like one from the API.
    (function unpackRows(pk) {
        if (!pk) { return; }
        var defs = pk.defaults || {};
        (D.issues || []).forEach(function (r) {
            Object.keys(defs).forEach(function (k) {
                if (!(k in r)) { r[k] = Array.isArray(defs[k]) ? [] : defs[k]; }
            });
            if (!('url' in r)) { r.url = pk.url + '/' + r.id; }
            if (!('edit_url' in r)) { r.edit_url = pk.url + '/' + r.id + '/edit'; }
            if (!('key' in r)) { r.key = pk.key + '-' + r.id; }
            if (!('up' in r)) { r.up = r.ua ? String(r.ua).slice(0, 16).replace(/-/g, '/') : ''; }
        });
    })(D.row_pack);
    var L =JSON.parse(document.getElementById('pmportal-lang').textContent);
    var CSRF = $('meta[name="csrf-token"]').attr('content');

    /* ------------------------------------------------ request queue ----- */
    // One request at a time, on purpose. Every call below boots the whole
    // framework on the server, and PHP serialises one user's requests on the
    // session lock anyway, so firing six at once does not make them arrive
    // sooner - it makes all six slower and leaves the one the user is really
    // waiting for at the back of the line. Opening this page used to do
    // exactly that: ten requests, six of them in flight together.
    //
    // Two lanes. Whatever the user is waiting for runs first; speculative
    // work (form prewarms, background embeds) waits for a quiet gap and
    // stands aside the moment real work appears. Published on window so the
    // settings pane and the top bar queue into the same line, and built once
    // per document so a pjax swap does not start a second queue beside it.
    if (!window.ppQueue) {
        window.ppQueue = (function () {
            var hi = [];
            var lo = [];
            var runHi = 0;
            var runLo = 0;
            var gap = null;
            var GAP_MS = 700;

            function start(job) {
                job.started = true;
                if (job.low) { runLo++; } else { runHi++; }
                var done = function () {
                    if (job.low) { runLo--; } else { runHi--; }
                    pump();
                };
                var p;
                try { p = job.task(); }
                catch (e) { job.reject(e); done(); return; }
                Promise.resolve(p).then(
                    function (v) { job.resolve(v); done(); },
                    function (e) { job.reject(e); done(); }
                );
            }
            // One of the user's requests at a time, and one background one at
            // a time. Counted separately on purpose: strict single file would
            // mean a background request the server is slow to answer holds up
            // the click that comes after it, which is the opposite of what
            // this is for. Two in flight is the worst case, not the norm.
            function pump() {
                if (hi.length && !runHi) {
                    if (gap) { clearTimeout(gap); gap = null; }
                    start(hi.shift());
                }
                // Speculative work waits for a clear line: nothing of the
                // user's running or queued, plus a moment of quiet after it.
                if (!lo.length || gap || runLo || runHi || hi.length) { return; }
                gap = setTimeout(function () {
                    gap = null;
                    if (runLo || runHi || hi.length) { pump(); return; }
                    var bg = lo.shift();
                    if (bg) { start(bg); }
                }, GAP_MS);
            }
            function queue(task, low) {
                var job = { task: task, started: false, low: !!low };
                var p = new Promise(function (resolve, reject) {
                    job.resolve = resolve;
                    job.reject = reject;
                });
                // Carried on the promise so a click can promote the very job
                // a prewarm queued a moment earlier instead of waiting it out.
                p.ppJob = job;
                (low ? lo : hi).push(job);
                pump();
                return p;
            }
            queue.promote = function (p) {
                var job = p && p.ppJob;
                if (!job || job.started) { return p; }
                var i = lo.indexOf(job);
                if (i >= 0) { lo.splice(i, 1); job.low = false; hi.push(job); pump(); }
                return p;
            };
            // A pjax swap replaced the page: nothing the old one wanted to
            // warm up is worth a request now.
            queue.dropLow = function () {
                lo.splice(0, lo.length).forEach(function (job) {
                    job.reject(new Error('pp-queue-dropped'));
                });
            };
            queue.waiting = function () { return hi.length + lo.length + runHi + runLo; };

            return queue;
        })();
    }
    var ppQueue = window.ppQueue;

    /* ------------------------------------------------ page title -------- */
    // Every screen here shares one URL path and differs only by the hash, so
    // the title is the only thing a bookmark, a history entry or a crowded tab
    // strip has to tell them apart by. Left alone, all of them are saved under
    // the project's name and become indistinguishable the moment there are two.
    // Captured once so whatever Exment put there stays as the suffix.
    var BASE_TITLE = document.title;
    function setTitle(lead) {
        document.title = lead ? lead + ' | ' + BASE_TITLE : BASE_TITLE;
    }

    /* ---- form styles (only affects the framed admin form) ------------- */
    // A form style applies inside the framed admin form only: it reshapes
    // the .box / .form-group markup the admin ships with, and touches none
    // of the portal's own screens.
    var CUR_FORM_STYLE = (D.form_styles && D.form_style && D.form_styles[D.form_style])
        ? D.form_style
        : (D.form_styles ? Object.keys(D.form_styles)[0] : 'card');
    function applyFormStyle(id, save) {
        if (!D.form_styles || !D.form_styles[id]) { return; }
        CUR_FORM_STYLE = id;
        if (FM && FM.el) {
            [FM.frame, FM.warm].forEach(function (frame) {
                if (!frame || !frame.dataset.ready) { return; }
                try { themeFrame(frame.contentDocument); } catch (e) { /* ignore */ }
            });
        }
        renderFormStyleMenu();
        renderFormStyleBadge();
        if (save) {
            $.ajax({ url: D.urls.form_style, type: 'POST', data: { style: id }, headers: { 'X-CSRF-TOKEN': CSRF } })
                .done(function () { toast(L.form_style_saved); });
        }
    }
    function renderFormStyleMenu() {
        var menu = FM.el && FM.el.querySelector('.pp-fm-style-menu');
        if (!menu) { return; }
        menu.innerHTML = Object.keys(D.form_styles || {}).map(function (id) {
            var style = D.form_styles[id];
            return '<a class="pp-fm-style-item' + (id === CUR_FORM_STYLE ? ' on' : '') + '" data-style="' + esc(id) + '">' +
                '<span class="ic pp-fm-style-swatch pp-fm-style-swatch-' + esc(id) + '"></span>' +
                '<span><span class="lbl">' + esc(style.label || id) + '</span>' +
                (style.description ? '<span class="desc">' + esc(style.description) + '</span>' : '') +
                '</span>' + (id === CUR_FORM_STYLE ? '<i class="fa fa-check"></i>' : '') + '</a>';
        }).join('');
    }
    function renderFormStyleBadge() {
        var badge = FM.el && FM.el.querySelector('.pp-fm-style-label');
        if (!badge) { return; }
        var style = (D.form_styles || {})[CUR_FORM_STYLE] || {};
        badge.textContent = style.label || CUR_FORM_STYLE;
    }

    var ST = {};
    (D.statuses || []).forEach(function (s) { ST[s.k] = s; });
    var ARROWS = {
        dd: 'M6 0l4 5H8v2H4V5H2zM6 6l4 5H8v3H4v-3H2z',
        up: 'M6 1l5 6H8v6H4V7H1z',
        right: 'M13 7l-6 5V9H1V5h6V2z',
        down: 'M6 13L1 7h3V1h4v6h3z'
    };
    var FEED_IC = {
        add: { i: 'fa-plus', c: '#4caf93' }, chg: { i: 'fa-pencil', c: '#4488c5' },
        cmt: { i: 'fa-comment', c: '#eda62a' }, file: { i: 'fa-paperclip', c: '#7f95a8' },
        wiki: { i: 'fa-book', c: '#8e44ad' }, wiki_add: { i: 'fa-book', c: '#8e44ad' }
    };

    /* ------------------------------------------------ tiny helpers ------ */
    function esc(s) {
        // The apostrophe is escaped in hex rather than the usual &#39;.
        // Exment's pjax layer runs the fragment through a decoder that turns
        // every DECIMAL numeric entity back into its character before the
        // browser sees it, and it does not skip script bodies - so a literal
        // '&#39;' here came back as ''', which is a syntax error that killed
        // this whole file and left the portal blank after a pjax navigation.
        // The hex form escapes the same character and the decoder ignores it.
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#x27;' }[c];
        });
    }
    // Colours from the status / type / priority master tables are dropped into
    // style attributes. Escaping is not enough on its own: a value like
    // "#fff;position:fixed;inset:0" never closes the attribute yet still paints
    // a full-screen overlay, so only a plain hex colour is let through and
    // anything else becomes a neutral grey. Colours are also checked on save;
    // this is the render-time backstop that holds even for a row saved another
    // way.
    function ppColor(v, fallback) {
        return /^#[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/.test(String(v == null ? '' : v)) ? String(v) : (fallback || '#95a5a6');
    }
    // An entry of a map that is keyed by data. A status, a sort column or a
    // user id arrives from the payload, the address bar or a stored filter,
    // and "constructor" or "__proto__" would otherwise answer with whatever
    // Object.prototype keeps under that name - truthy, and nothing like the
    // entry the caller went on to read from.
    function own(map, key) {
        return (map && Object.prototype.hasOwnProperty.call(map, key)) ? map[key] : undefined;
    }

