/*
 * ExmentPM: on a record's page of Exment, the issues linked to it (ISS02939).
 *
 * Loaded on Exment's pages by the plugin type "script". It does nothing unless the page is a
 * record's page, /<admin>/data/<table>/<id> (the admin prefix as Exment has it, read from the
 * address of this file), and <table> is one some project links to: that list is read once and
 * kept for 5 minutes, so the other pages make no request. Then it asks for the issues linked to
 * the record that the person may read, and adds a box of them. Text only: nothing of the data
 * becomes HTML.
 */
(function () {
    'use strict';
    var FILE = '/public/js/record-links.js';
    var CACHE = 'exment_pm_link_tables';
    var TTL = 5 * 60 * 1000;
    var BOX = 'pmportal-record-links';

    function scriptBase() {
        var list = document.getElementsByTagName('script');
        for (var i = 0; i < list.length; i++) {
            var src = list[i].getAttribute('src') || '';
            var at = src.indexOf(FILE);
            if (at > 0) {
                return src.slice(0, at);
            }
        }
        return null;
    }

    function pathOf(url) {
        var a = document.createElement('a');
        a.href = url;
        var p = a.pathname || '';
        return p.charAt(0) === '/' ? p : '/' + p;
    }

    // [table, id] when this is a record's page, else null
    function recordOfPage(adminPath) {
        var path = window.location.pathname.replace(/\/+$/, '');
        var prefix = adminPath.replace(/\/+$/, '') + '/data/';
        if (path.indexOf(prefix) !== 0) {
            return null;
        }
        var m = /^([A-Za-z0-9_]+)\/([1-9][0-9]{0,18})$/.exec(path.slice(prefix.length));
        return m ? [m[1], m[2]] : null;
    }

    function readCache() {
        try {
            var v = JSON.parse(window.sessionStorage.getItem(CACHE) || 'null');
            if (v && Array.isArray(v.t) && Date.now() - v.at < TTL) {
                return v.t;
            }
        } catch (e) { /* none */ }
        return null;
    }

    function writeCache(tables) {
        try {
            window.sessionStorage.setItem(CACHE, JSON.stringify({ at: Date.now(), t: tables }));
        } catch (e) { /* none */ }
    }

    function getJson(url) {
        return fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.ok ? r.json() : null; })
            .catch(function () { return null; });
    }

    function tables(base) {
        var cached = readCache();
        if (cached) {
            return Promise.resolve(cached);
        }
        return getJson(base + '/record_links/tables').then(function (j) {
            var t = j && Array.isArray(j.tables) ? j.tables.map(String) : [];
            writeCache(t);
            return t;
        });
    }

    function words() {
        var ja = String(document.documentElement.lang || '').indexOf('ja') === 0;
        return ja
            ? { title: '関連する課題', none: '関連する課題はありません。' }
            : { title: 'Linked issues', none: 'No linked issues.' };
    }

    function el(tag, cls, text) {
        var e = document.createElement(tag);
        if (cls) { e.className = cls; }
        if (text !== undefined && text !== null) { e.textContent = String(text); }
        return e;
    }

    function render(issues) {
        var old = document.getElementById(BOX);
        if (old) { old.parentNode.removeChild(old); }
        if (!issues.length) { return; }
        var where = document.querySelector('section.content') || document.querySelector('.content-wrapper') || document.body;
        var w = words();
        var box = el('div', 'box box-default');
        box.id = BOX;
        var head = el('div', 'box-header with-border');
        head.appendChild(el('h3', 'box-title', w.title + ' (' + issues.length + ')'));
        box.appendChild(head);
        var body = el('div', 'box-body');
        var list = el('ul');
        list.style.cssText = 'margin:0;padding-left:18px;';
        issues.forEach(function (i) {
            var li = el('li');
            li.style.margin = '3px 0';
            var a = el('a', null, String(i.key || '') + ' ' + String(i.s || ''));
            // only the portal's own addresses
            if (/^(https?:\/\/[^\s]+|\/[^\s]*)$/.test(String(i.u || '')) && !/^javascript:/i.test(String(i.u))) {
                a.setAttribute('href', String(i.u));
            }
            li.appendChild(a);
            var meta = [i.st, i.p].filter(function (x) { return x; }).join(' / ');
            if (meta) {
                var span = el('span', null, ' — ' + meta);
                span.style.color = '#8a97a3';
                li.appendChild(span);
            }
            list.appendChild(li);
        });
        body.appendChild(list);
        box.appendChild(body);
        where.appendChild(box);
    }

    function run() {
        var base = scriptBase();
        if (!base) { return; }
        // the plugin's address is <admin>/plugins/<uri>: Exment's admin path is before it
        var adminPath = pathOf(base).replace(/\/plugins\/[^/]+\/?$/, '');
        var rec = recordOfPage(adminPath);
        if (!rec) {
            var old = document.getElementById(BOX);
            if (old) { old.parentNode.removeChild(old); }
            return;
        }
        tables(base).then(function (list) {
            if (list.indexOf(rec[0]) < 0) { return null; }
            return getJson(base + '/record_links?table=' + encodeURIComponent(rec[0]) + '&id=' + encodeURIComponent(rec[1]));
        }).then(function (j) {
            if (j && Array.isArray(j.issues)) { render(j.issues); }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', run);
    } else {
        run();
    }
    // Exment moves between pages with pjax
    if (window.jQuery) {
        window.jQuery(document).on('pjax:end', run);
    }
})();
