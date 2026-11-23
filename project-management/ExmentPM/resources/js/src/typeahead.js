    /* ------------------------------------------------ type-ahead -------- */
    // Backlog lets a writer reach the three things a ticket is always about
    // without leaving the keyboard: @ a person, # another issue, ~ a file.
    // The list is drawn from what the page already holds, so a trigger costs
    // no request and works with the network down.
    //
    // Mounted on <body> rather than beside the field: the editors sit inside
    // boxes that clip and scroll, and a list anchored inside one of them is
    // cut off at the first border it meets.
    var TA_MAX = 12;
    var TA = { el: null, ta: null, trigger: null, from: 0, items: [], idx: 0 };
    var TA_HOSTS = '#pp-cmt-text, .pp-ne-desc-row textarea.pp-ne-in, .bl-tl .cedit textarea';
    // A trigger only counts at the start of a word. Without this every mail
    // address in a paragraph would open the people list.
    var TA_OPEN_BEFORE = /[\s(\[{>]/;
    function taSources() {
        return {
            '@': {
                title: L.ta_user,
                rows: function () {
                    return Object.keys(D.users || {}).map(function (id) {
                        var u = user(id);

                        return { label: u.n, av: id, insert: '@' + u.n + ' ' };
                    }).sort(function (a, b) { return cmpText(a.label, b.label); });
                }
            },
            '#': {
                title: L.ta_issue,
                rows: function () {
                    // The bare key is what linkifyIssueRefs turns back into a
                    // link, so that is what is written - not "#KEY", which
                    // would read as a link and render as text.
                    return (D.issues || []).map(function (i) {
                        return { label: i.subject, key: i.key, insert: i.key + ' ' };
                    });
                }
            },
            '~': {
                title: L.ta_file,
                rows: function () {
                    return ((D.files && D.files.rows) || []).map(function (f) {
                        return { label: f.name, insert: mdFileLink(f.name, f.url, false) };
                    });
                }
            }
        };
    }
    function taEl() {
        if (TA.el) { return TA.el; }
        var box = document.createElement('div');
        box.className = 'pp-ta';
        box.hidden = true;
        // mousedown, not click: the field must keep focus, or the caret this
        // is about to write at is gone by the time the click lands.
        box.addEventListener('mousedown', function (ev) { ev.preventDefault(); });
        box.addEventListener('click', function (ev) {
            var b = ev.target.closest('.it');
            if (!b) { return; }
            taAccept(parseInt(b.dataset.i, 10) || 0);
        });
        document.body.appendChild(box);
        TA.el = box;

        return box;
    }
    function taClose() {
        if (TA.el) { TA.el.hidden = true; }
        TA.trigger = null;
        TA.items = [];
    }
    function taIsOpen() { return !!(TA.el && !TA.el.hidden); }
    // Where the caret is, in document space. A textarea gives no such answer,
    // so the text before the caret is laid out again in a hidden copy of the
    // field and the position of what follows it is read off that.
    function taCaret(el, pos) {
        var cs = getComputedStyle(el);
        var mirror = document.createElement('div');
        ['fontFamily', 'fontSize', 'fontWeight', 'fontStyle', 'letterSpacing', 'lineHeight',
         'textTransform', 'wordSpacing', 'textIndent', 'paddingTop', 'paddingRight',
         'paddingBottom', 'paddingLeft', 'borderTopWidth', 'borderRightWidth',
         'borderBottomWidth', 'borderLeftWidth', 'boxSizing'].forEach(function (p) {
            mirror.style[p] = cs[p];
        });
        mirror.style.position = 'absolute';
        mirror.style.visibility = 'hidden';
        mirror.style.whiteSpace = 'pre-wrap';
        mirror.style.overflowWrap = 'break-word';
        mirror.style.width = el.clientWidth + 'px';
        mirror.style.left = '0px';
        mirror.style.top = '0px';
        mirror.textContent = el.value.slice(0, pos);
        var mark = document.createElement('span');
        mark.textContent = el.value.slice(pos) || '.';
        mirror.appendChild(mark);
        document.body.appendChild(mirror);
        var x = mark.offsetLeft, y = mark.offsetTop, h = mark.offsetHeight;
        document.body.removeChild(mirror);
        var r = el.getBoundingClientRect();

        return {
            left: r.left + window.scrollX + x - el.scrollLeft,
            top: r.top + window.scrollY + y - el.scrollTop,
            h: h || parseInt(cs.lineHeight, 10) || 16
        };
    }
    function taRender() {
        var box = taEl();
        var src = own(taSources(), TA.trigger);
        box.innerHTML = '';
        var head = document.createElement('div');
        head.className = 'hd';
        head.textContent = TA.trigger + ' ' + (src ? src.title : '');
        box.appendChild(head);
        var list = document.createElement('div');
        list.className = 'ls';
        if (!TA.items.length) {
            var em = document.createElement('div');
            em.className = 'em';
            em.textContent = L.ta_none;
            list.appendChild(em);
        }
        TA.items.forEach(function (it, n) {
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'it' + (n === TA.idx ? ' on' : '');
            b.dataset.i = String(n);
            if (it.av) {
                var u = user(it.av);
                var a = document.createElement('span');
                a.className = 'bl-av';
                a.style.background = ppColor(u.c);
                a.textContent = u.i;
                if (u.a) { a.insertAdjacentHTML('beforeend', avImg(u)); }
                b.appendChild(a);
            }
            if (it.key) {
                var k = document.createElement('span');
                k.className = 'bl-key';
                k.textContent = it.key;
                b.appendChild(k);
            }
            var n1 = document.createElement('span');
            n1.className = 'n';
            n1.textContent = it.label;
            b.appendChild(n1);
            list.appendChild(b);
        });
        box.appendChild(list);
        var foot = document.createElement('div');
        foot.className = 'ft';
        foot.textContent = L.ta_hint;
        box.appendChild(foot);
    }
    function taPlace() {
        var box = taEl();
        var at = taCaret(TA.ta, TA.from);
        box.hidden = false;
        box.style.left = '0px';
        box.style.top = '0px';
        var w = box.offsetWidth, h = box.offsetHeight;
        var left = Math.min(at.left, window.scrollX + document.documentElement.clientWidth - w - 8);
        // Under the caret when there is room, above it when there is not -
        // which in the pinned composer is almost always.
        var below = at.top + at.h + 4;
        var roomBelow = window.scrollY + window.innerHeight - below;
        var top = roomBelow >= h ? below : Math.max(window.scrollY + 4, at.top - h - 4);
        box.style.left = Math.max(window.scrollX + 4, left) + 'px';
        box.style.top = top + 'px';
    }
    // Read back from the caret to the nearest trigger. Anything with a space
    // in between means the writer has moved on, and the list closes.
    function taScan(el) {
        var pos = el.selectionStart;
        if (pos !== el.selectionEnd) { return null; }
        var value = el.value;
        var srcs = taSources();
        for (var i = pos - 1; i >= 0 && pos - i <= 40; i--) {
            var ch = value.charAt(i);
            if (/\s/.test(ch)) { return null; }
            if (!own(srcs, ch)) { continue; }
            var before = i > 0 ? value.charAt(i - 1) : '';
            if (before && !TA_OPEN_BEFORE.test(before)) { return null; }

            return { trigger: ch, from: i, query: value.slice(i + 1, pos) };
        }

        return null;
    }
    function taMatch(rows, query) {
        var q = String(query || '').toLowerCase();
        if (!q) { return rows.slice(0, TA_MAX); }

        return rows.filter(function (r) {
            return String(r.label || '').toLowerCase().indexOf(q) >= 0 ||
                String(r.key || '').toLowerCase().indexOf(q) >= 0;
        }).slice(0, TA_MAX);
    }
    function taRefresh(el) {
        var hit = taScan(el);
        if (!hit) { taClose(); return; }
        var src = own(taSources(), hit.trigger);
        if (!src) { taClose(); return; }
        var rows = src.rows();
        // A source with nothing in it never opens: a project with no files
        // would otherwise flash an empty box at every ~ in ~~strike~~. One
        // that has rows but matches none says so, which is how the writer
        // learns the trigger was understood.
        if (!rows.length) { taClose(); return; }
        TA.ta = el;
        TA.trigger = hit.trigger;
        TA.from = hit.from;
        TA.items = taMatch(rows, hit.query);
        TA.idx = 0;
        taRender();
        taPlace();
    }
    function taMove(step) {
        if (!TA.items.length) { return; }
        TA.idx = (TA.idx + step + TA.items.length) % TA.items.length;
        taRender();
        var on = TA.el.querySelector('.it.on');
        if (on && on.scrollIntoView) { on.scrollIntoView({ block: 'nearest' }); }
    }
    function taAccept(index) {
        var it = TA.items[index == null ? TA.idx : index];
        var el = TA.ta;
        if (!it || !el) { taClose(); return; }
        var to = el.selectionStart;
        el.value = el.value.slice(0, TA.from) + it.insert + el.value.slice(to);
        var caret = TA.from + it.insert.length;
        el.selectionStart = el.selectionEnd = caret;
        taClose();
        el.focus();
        // Everything downstream of a field listens for input, not for us.
        el.dispatchEvent(new Event('input', { bubbles: true }));
    }
    // A field is bound by the page it belongs to. The mark names that page's
    // root rather than just saying "bound": a page left behind by pjax that
    // marked a field first would otherwise keep it, and fill the new
    // project's comment box with the old project's people, issues and files.
    function ppTaAttach(el) {
        if (!el || el._taBound === root) { return; }
        el._taBound = root;
        el.addEventListener('input', function () { if (alive()) { taRefresh(el); } });
        el.addEventListener('click', function () { if (alive() && taIsOpen()) { taRefresh(el); } });
        el.addEventListener('blur', function () { setTimeout(taClose, 120); });
        // Capture, so the list answers the arrow keys before the field moves
        // its caret and before Ctrl+Enter posts a half-typed mention.
        el.addEventListener('keydown', function (ev) {
            if (!alive() || !taIsOpen() || TA.ta !== el) { return; }
            if (ev.key === 'Escape') { ev.preventDefault(); ev.stopPropagation(); taClose(); return; }
            if (ev.key === 'ArrowDown') { ev.preventDefault(); taMove(1); return; }
            if (ev.key === 'ArrowUp') { ev.preventDefault(); taMove(-1); return; }
            if (ev.key === 'Enter' || ev.key === 'Tab') {
                if (ev.ctrlKey || ev.metaKey || ev.altKey) { return; }
                // Nothing matched: the key belongs to the field, not to a
                // list that has nothing to choose from.
                if (!TA.items.length) { taClose(); return; }
                ev.preventDefault();
                ev.stopPropagation();
                taAccept();
            }
        }, true);
    }
    window.ppTaAttach = ppTaAttach;
    // Bound once, lazily: the editors these run in are built and torn down
    // per open, so there is no one moment when they all exist. Both
    // listeners outlive a pjax swap, so each stands down with its page.
    document.addEventListener('focusin', function onTaFocus(ev) {
        if (!alive()) { document.removeEventListener('focusin', onTaFocus); return; }
        var t = ev.target;
        if (t && t.matches && t.matches(TA_HOSTS)) { ppTaAttach(t); }
    });
    window.addEventListener('scroll', function onTaScroll() {
        if (!alive()) { window.removeEventListener('scroll', onTaScroll, true); taClose(); return; }
        if (taIsOpen()) { taClose(); }
    }, true);

    // Fold a fresh detail answer back into the page: the caches the list and
    // the screens read from, then a repaint. Every write on this screen -
    // comment, edit, delete - answers with one, so none of them reloads.
    //
    // paint: false = caches only; 'force' = draw the issue again even with
    // work in the dock (renderQuickEdit carries it across); anything else =
    // draw it if nothing is in the way, else only the timeline and files.
    // seq: the number its request took (detPut); an older answer than the
    // copy held is dropped. Returns whether it was taken.
    function applyDetail(res, epoch, paint, seq) {
        if (!alive()) { return false; }
        if (!res || !res.ok || !res.issue) { toast(L.post_fail, true); return false; }
        $.extend(D.users, res.users || {});
        res.issue = $.extend({}, issueById(res.issue.id) || {}, res.issue);
        // The detail answer's row has no reader's marks; 確認待ち is read off
        // the timeline it came with, so the list's chip follows a request
        // just made, answered or taken back without a reload.
        if (res.timeline) { res.issue.my_rv = rvPending(res); }
        if (!detPut(res.issue.id, res, seq)) { return false; }
        (D.issues || []).forEach(function (row, index) {
            if (String(row.id) === String(res.issue.id)) { D.issues[index] = res.issue; }
        });
        if (paint !== false && detailCurrent(res.issue.id, epoch)) {
            if (paint === 'force' || safeToRefresh()) { buildDetail(res); }
            else { renderTimeline(res); renderIssueFiles(res); }
        }

        return true;
    }

