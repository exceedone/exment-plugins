    /* ------------------------------------------------ attachments seen in place (ISS02938) ---- */
    // Next to every link to an attachment (the issue's list, a link in a comment, the wiki): small
    // buttons 「ダウンロード」 and 「オンラインで開く」 (and 「オンラインで編集」 for Word, Excel and
    // PowerPoint when Exment Plugin Groupware edits them). 「オンラインで開く」 shows the file here:
    // pictures and PDF as they are, text and CSV as text and a table, Excel as its sheets, Word as its
    // text. All drawn as text (textContent), never as the file's own HTML. Works without the
    // versions of #152 (an Exment without DocumentVersionService).
    // the screen of the page now (Exment swaps pages through pjax and boots again); the
    // listeners below are added once and read it
    window.__ppPv = { D: D, L: L, esc: esc };
    if (!window.__ppPvBound) {
        window.__ppPvBound = true;
        (function () {
        var P = function () { return window.__ppPv; };
        var PV_MAX = 10 * 1024 * 1024;
        var PV_ROWS = 2000;
        var PV_COLS = 100;
        var PV_EXT = {
            image: ['png', 'jpg', 'jpeg', 'gif', 'webp', 'bmp'],
            pdf: ['pdf'],
            text: ['txt', 'md', 'markdown', 'csv', 'tsv', 'log', 'json', 'xml', 'yml', 'yaml', 'ini', 'conf', 'sql'],
            sheet: ['xlsx', 'xlsm', 'xls', 'ods'],
            word: ['docx'],
            office: ['docx', 'xlsx', 'pptx']
        };
        function pvExt(name) { var m = /\.([a-z0-9]{1,8})$/i.exec(String(name || '')); return m ? m[1].toLowerCase() : ''; }
        function pvKind(name) {
            var e = pvExt(name);
            var k = ['image', 'pdf', 'text', 'sheet', 'word'];
            for (var i = 0; i < k.length; i++) { if (PV_EXT[k[i]].indexOf(e) >= 0) { return k[i]; } }
            return null;
        }
        function pvUuid(href) { var m = /\/files\/([0-9a-f][0-9a-f-]{7,63})(?:[\/?#]|$)/i.exec(String(href || '')); return m ? m[1] : null; }
        function pvName(a) {
            var t = (a.getAttribute('download') || a.textContent || '').trim();
            return t || (a.getAttribute('title') || '').trim();
        }

        // the buttons after a link
        function pvButtons(href, name) {
            var uuid = pvUuid(href);
            if (!uuid) { return null; }
            var box = document.createElement('span');
            box.className = 'pp-pvb';
            var dl = document.createElement('a');
            dl.href = href;
            dl.setAttribute('download', name || '');
            dl.className = 'pp-pvbtn';
            dl.innerHTML = '<i class="fa fa-download"></i>';
            dl.appendChild(document.createTextNode(' ' + P().L.pv_download));
            box.appendChild(dl);
            if (pvKind(name)) {
                var op = document.createElement('a');
                op.href = 'javascript:void(0);';
                op.className = 'pp-pvbtn pp-pvopen';
                op.setAttribute('data-pv-url', href);
                op.setAttribute('data-pv-name', name || '');
                op.innerHTML = '<i class="fa fa-eye"></i>';
                op.appendChild(document.createTextNode(' ' + P().L.pv_open));
                box.appendChild(op);
            }
            if (PV_EXT.office.indexOf(pvExt(name)) >= 0 && P().D.urls && P().D.urls.pv_office) {
                var ed = document.createElement('a');
                ed.href = P().D.urls.pv_office + '/' + uuid;
                ed.target = '_blank';
                ed.rel = 'noopener';
                ed.className = 'pp-pvbtn';
                ed.innerHTML = '<i class="fa fa-pencil"></i>';
                ed.appendChild(document.createTextNode(' ' + P().L.pv_edit));
                box.appendChild(ed);
            }
            return box;
        }
        // every link to an attachment under a node, once
        function pvDecorate(root) {
            if (!root || !root.querySelectorAll) { return; }
            var links = root.querySelectorAll('a[href*="/files/"]');
            for (var i = 0; i < links.length; i++) {
                var a = links[i];
                if (a.hasAttribute('data-pv') || a.closest('.pp-pvb, .pp-pv, .pp-aed, .pp-pvskip')) { continue; }
                a.setAttribute('data-pv', '1');
                // a picture or an icon only (the list's thumbnail): its name link next to it gets them
                if ((a.textContent || '').trim() === '') { continue; }
                // the same file linked twice in one row: once
                var row = a.parentNode, dup = false;
                if (row) {
                    var same = row.querySelectorAll('a[data-pvdone]');
                    for (var j = 0; j < same.length; j++) { if (same[j].getAttribute('href') === a.getAttribute('href')) { dup = true; } }
                }
                if (dup) { continue; }
                a.setAttribute('data-pvdone', '1');
                var b = pvButtons(a.getAttribute('href'), pvName(a));
                if (b) { a.parentNode.insertBefore(b, a.nextSibling); }
            }
        }
        var PV_TICK = null;
        function pvSoon() {
            if (PV_TICK) { return; }
            PV_TICK = (window.requestAnimationFrame || setTimeout)(function () { PV_TICK = null; pvDecorate(document.body); });
        }
        if (window.MutationObserver) {
            new MutationObserver(function () { pvSoon(); }).observe(document.body, { childList: true, subtree: true });
        }
        pvSoon();

        // the window
        function pvClose() { var w = document.getElementById('pp-pv'); if (w) { w.parentNode.removeChild(w); } (PV_URLS || []).forEach(function (u) { URL.revokeObjectURL(u); }); PV_URLS = []; }
        var PV_URLS = [];
        function pvBox(name, href) {
            pvClose();
            var wrap = document.createElement('div');
            wrap.className = 'pp-aed pp-pv';
            wrap.id = 'pp-pv';
            wrap.innerHTML = '<div class="box" role="dialog" aria-modal="true"><div class="hd"><span class="t"></span><span class="sp"></span>' +
                '<a class="pp-pvbtn dl"><i class="fa fa-download"></i> </a>' +
                '<a href="javascript:void(0);" class="x" aria-label="' + P().esc(P().L.attach_close) + '"><i class="fa fa-times"></i></a></div>' +
                '<div class="bd"><i class="fa fa-spinner fa-spin"></i></div></div>';
            wrap.querySelector('.t').textContent = name;
            var dl = wrap.querySelector('.dl');
            dl.href = href;
            dl.setAttribute('download', name);
            dl.appendChild(document.createTextNode(P().L.pv_download));
            document.body.appendChild(wrap);
            wrap.querySelector('.x').addEventListener('click', pvClose);
            wrap.addEventListener('mousedown', function (ev) { if (ev.target === wrap) { pvClose(); } });
            return wrap.querySelector('.bd');
        }
        function pvMsg(bd, text) { bd.textContent = ''; var p = document.createElement('p'); p.textContent = text; bd.appendChild(p); }
        function pvScript(url, ready) {
            if (!url) { ready(false); return; }
            var s = document.createElement('script');
            s.src = url;
            s.onload = function () { ready(true); };
            s.onerror = function () { ready(false); };
            document.head.appendChild(s);
        }
        function pvText(buf) {
            var bytes = new Uint8Array(buf);
            if (bytes.length >= 3 && bytes[0] === 0xEF && bytes[1] === 0xBB && bytes[2] === 0xBF) { bytes = bytes.subarray(3); }
            try { return new TextDecoder('utf-8', { fatal: true }).decode(bytes); } catch (e) { /* not UTF-8 */ }
            try { return new TextDecoder('shift_jis').decode(bytes); } catch (e2) { return new TextDecoder('utf-8').decode(bytes); }
        }
        function pvCsv(text, sep) {
            var rows = [], row = [], cell = '', q = false;
            for (var i = 0; i < text.length; i++) {
                var c = text[i];
                if (q) {
                    if (c === '"') { if (text[i + 1] === '"') { cell += '"'; i++; } else { q = false; } } else { cell += c; }
                } else if (c === '"') { q = true; } else if (c === sep) { row.push(cell); cell = ''; } else if (c === '\n' || c === '\r') {
                    if (c === '\r' && text[i + 1] === '\n') { i++; }
                    row.push(cell); rows.push(row); row = []; cell = '';
                    if (rows.length >= PV_ROWS) { break; }
                } else { cell += c; }
            }
            if (cell !== '' || row.length) { row.push(cell); rows.push(row); }
            return rows;
        }
        function pvTable(rows) {
            var t = document.createElement('table');
            t.className = 'pp-pvtable';
            rows.slice(0, PV_ROWS).forEach(function (r) {
                var tr = document.createElement('tr');
                (r || []).slice(0, PV_COLS).forEach(function (v) { var td = document.createElement('td'); td.textContent = v == null ? '' : String(v); tr.appendChild(td); });
                t.appendChild(tr);
            });
            var wrap = document.createElement('div');
            wrap.className = 'pp-pvscroll';
            wrap.appendChild(t);
            if (rows.length >= PV_ROWS) { var more = document.createElement('p'); more.className = 'pp-pvmore'; more.textContent = P().L.pv_more; wrap.appendChild(more); }
            return wrap;
        }
        function pvPre(text) { var pre = document.createElement('pre'); pre.className = 'pp-pvpre'; pre.textContent = text; return pre; }

        function pvOpen(href, name) {
            var bd = pvBox(name, href);
            var kind = pvKind(name);
            fetch(href, { credentials: 'same-origin' }).then(function (r) {
                if (!r.ok) { throw new Error(String(r.status)); }
                var len = parseInt(r.headers.get('Content-Length') || '0', 10);
                if (len > PV_MAX) { throw new Error('big'); }
                return r.arrayBuffer();
            }).then(function (buf) {
                if (buf.byteLength > PV_MAX) { throw new Error('big'); }
                bd.textContent = '';
                if (kind === 'image' || kind === 'pdf') {
                    var type = kind === 'pdf' ? 'application/pdf' : 'image/' + (pvExt(name) === 'jpg' ? 'jpeg' : pvExt(name));
                    var u = URL.createObjectURL(new Blob([buf], { type: type }));
                    PV_URLS.push(u);
                    var el = document.createElement(kind === 'pdf' ? 'iframe' : 'img');
                    el.className = kind === 'pdf' ? 'pp-pvpdf' : 'pp-pvimg';
                    el.src = u;
                    el.setAttribute(kind === 'pdf' ? 'title' : 'alt', name);
                    bd.appendChild(el);
                } else if (kind === 'text') {
                    var text = pvText(buf);
                    var e = pvExt(name);
                    bd.appendChild(e === 'csv' || e === 'tsv' ? pvTable(pvCsv(text, e === 'tsv' ? '\t' : ',')) : pvPre(text));
                } else if (kind === 'sheet') {
                    pvScript(window.XLSX ? null : P().D.urls.pv_xlsx, function () {
                        if (!window.XLSX) { pvMsg(bd, P().L.pv_fail); return; }
                        var wb = window.XLSX.read(new Uint8Array(buf), { type: 'array', cellHTML: false, cellFormula: false, sheetRows: PV_ROWS });
                        var tabs = document.createElement('div');
                        tabs.className = 'pp-pvtabs';
                        var body = document.createElement('div');
                        var show = function (n) {
                            body.textContent = '';
                            body.appendChild(pvTable(window.XLSX.utils.sheet_to_json(wb.Sheets[n], { header: 1, raw: false, defval: '' })));
                            Array.prototype.forEach.call(tabs.children, function (b) { b.classList.toggle('on', b.getAttribute('data-n') === n); });
                        };
                        wb.SheetNames.forEach(function (n) {
                            var b = document.createElement('button');
                            b.type = 'button';
                            b.setAttribute('data-n', n);
                            b.textContent = n;
                            b.addEventListener('click', function () { show(n); });
                            tabs.appendChild(b);
                        });
                        bd.appendChild(tabs);
                        bd.appendChild(body);
                        if (wb.SheetNames.length) { show(wb.SheetNames[0]); }
                    });
                } else if (kind === 'word') {
                    pvScript(window.mammoth ? null : P().D.urls.pv_mammoth, function () {
                        if (!window.mammoth) { pvMsg(bd, P().L.pv_fail); return; }
                        window.mammoth.extractRawText({ arrayBuffer: buf }).then(function (res) { bd.appendChild(pvPre(res.value || '')); })
                            .catch(function () { pvMsg(bd, P().L.pv_fail); });
                    });
                }
            }).catch(function (e) { pvMsg(bd, e && e.message === 'big' ? P().L.pv_big : P().L.pv_fail); });
        }

        document.addEventListener('click', function (ev) {
            var t = ev.target && ev.target.closest ? ev.target.closest('.pp-pvopen') : null;
            if (!t) { return; }
            ev.preventDefault();
            pvOpen(t.getAttribute('data-pv-url'), t.getAttribute('data-pv-name'));
        });
        document.addEventListener('keydown', function (ev) {
            if (ev.key === 'Escape' && document.getElementById('pp-pv')) { pvClose(); }
        });
        })();
    }
