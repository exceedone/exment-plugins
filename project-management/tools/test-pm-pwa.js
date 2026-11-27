// ExmentPM: the service worker of the installable web app (resources/pwa/sw.js, ISS02939 item 6),
// with a fake fetch and caches, for both the /admin prefix and an empty one. Also the Web Push
// notice (step 2): what it shows, and where a tap goes (inside the portal only).
//   node project-management/tools/test-pm-pwa.js
'use strict';
const fs = require('fs');
const path = require('path');
const vm = require('vm');

const src = fs.readFileSync(path.join(__dirname, '../ExmentPM/resources/pwa/sw.js'), 'utf8');
let ok = 0, bad = 0;
function check(label, cond, extra) {
    console.log((cond ? 'OK  ' : 'NG  ') + label + (cond ? '' : '  ' + JSON.stringify(extra)));
    cond ? ok++ : bad++;
}

function load(scope, online) {
    const listeners = {};
    const store = {};          // cache name -> { url: body }
    const fetched = [];
    const shown = [];       // notices shown
    const opened = [];      // windows opened
    const windows = [];     // the open windows (a test adds some)
    const sandbox = {
        URL, Request: function (u) { this.url = u; }, Response: function (b, o) { this.body = b; this.status = (o || {}).status || 200; },
        Promise, console,
        CFG: { scope: scope, offline: scope + 'offline', icons: [scope + 'pwa-icon/192', scope + 'pwa-icon/512'], assets: ['/exment-pm/'], version: 'v1', name: 'PM' },
        self: {
            location: { origin: 'https://pm.example.invalid' }, addEventListener: (n, f) => { listeners[n] = f; },
            registration: { showNotification: (title, opts) => { shown.push({ title, opts }); return Promise.resolve(); } },
            clients: {
                matchAll: () => Promise.resolve(windows),
                openWindow: (u) => { opened.push(u); return Promise.resolve(null); },
            },
        },
        fetch: function (req) {
            const u = typeof req === 'string' ? req : req.url;
            fetched.push(u);
            if (!online.value) { return Promise.reject(new TypeError('offline')); }
            return Promise.resolve({ ok: true, type: 'basic', body: 'net:' + u, clone() { return this; } });
        },
        caches: {
            open: (n) => Promise.resolve({
                addAll: (reqs) => { store[n] = store[n] || {}; reqs.forEach((r) => { store[n][new URL(r.url, 'https://pm.example.invalid').pathname] = 'cached:' + r.url; }); return Promise.resolve(); },
                match: (req) => Promise.resolve(store[n] && store[n][new URL(req.url).pathname] ? { body: store[n][new URL(req.url).pathname] } : undefined),
                put: (req, res) => { store[n] = store[n] || {}; store[n][new URL(req.url).pathname] = 'put:' + res.body; return Promise.resolve(); },
            }),
            match: (u) => { for (const n of Object.keys(store)) { const k = new URL(u, 'https://pm.example.invalid').pathname; if (store[n][k]) { return Promise.resolve({ body: store[n][k] }); } } return Promise.resolve(undefined); },
            keys: () => Promise.resolve(Object.keys(store)),
            delete: (n) => { delete store[n]; return Promise.resolve(true); },
        },
    };
    vm.createContext(sandbox);
    vm.runInContext(src, sandbox);
    const run = async (url, mode, method) => {
        let p = null;
        listeners.fetch({ request: { url: url, mode: mode || 'cors', method: method || 'GET' }, respondWith: (x) => { p = x; } });
        return p === null ? null : await p;
    };
    return { listeners, store, fetched, run, shown, opened, windows };
}

(async () => {
    for (const scope of ['/admin/plugins/exment_pm/', '/plugins/exment_pm/']) {
        const online = { value: true };
        const w = load(scope, online);
        const O = 'https://pm.example.invalid';
        let waited = null;
        w.listeners.install({ waitUntil: (p) => { waited = p; } });
        await waited;
        check(scope + ': install keeps the offline page and the icons', !!w.store['exment-pm-v1'] && !!w.store['exment-pm-v1'][scope + 'offline'] && !!w.store['exment-pm-v1'][scope + 'pwa-icon/192']);

        let r = await w.run(O + scope + '1', 'navigate');
        check(scope + ': a page online: from the network', r && r.body === 'net:' + O + scope + '1', r);
        online.value = false;
        r = await w.run(O + scope + '1', 'navigate');
        check(scope + ': a page offline: the offline page', r && String(r.body).indexOf('cached:') === 0 && String(r.body).indexOf(scope + 'offline') > 0, r);
        check(scope + ': pages are never kept', !Object.keys(w.store['exment-pm-v1']).some((k) => k === scope + '1'));
        online.value = true;

        // Exment's own pages and other plugins: not touched
        check(scope + ': another page of Exment: not touched', (await w.run(O + '/admin/data/users', 'navigate')) === null && (await w.run(O + '/plugins/other/', 'navigate')) === null);
        // the APIs and the issues: not touched (no data kept)
        check(scope + ': an API answer: not touched', (await w.run(O + scope + 'lightapi/data/issue?x=1')) === null && (await w.run(O + scope + '12/detail')) === null);
        check(scope + ': a POST: not touched', (await w.run(O + scope + 'notify-by', 'cors', 'POST')) === null);
        check(scope + ': another origin: not touched', (await w.run('https://cdn.example.invalid/exment-pm/script.1.js')) === null);

        // the versioned assets: cache first
        const a = O + '/exment-pm/script.abc.js';
        r = await w.run(a);
        check(scope + ': an asset: fetched once, then kept', r && r.body === 'net:' + a && w.store['exment-pm-v1']['/exment-pm/script.abc.js']);
        const before = w.fetched.length;
        online.value = false;
        r = await w.run(a);
        check(scope + ': an asset again (offline): from the cache, no fetch', r && w.fetched.length === before, r);
        online.value = true;
        check(scope + ': the route fallback with ?v= is an asset, without ?v not', (await w.run(O + scope + 'asset/style?v=1')) !== null && (await w.run(O + scope + 'asset/style')) === null);

        // an old version is removed on activate
        w.store['exment-pm-old'] = {};
        w.store['other-cache'] = {};
        let act = null;
        w.listeners.activate({ waitUntil: (p) => { act = p; } });
        await act;
        check(scope + ': activate removes old versions only', !w.store['exment-pm-old'] && !!w.store['other-cache'] && !!w.store['exment-pm-v1']);

        // Web Push: the subject and the link of the bell's list; a link elsewhere is not followed
        const push = async (data) => {
            let p = null;
            w.listeners.push({ data: data === undefined ? null : { json: () => (typeof data === 'string' ? JSON.parse(data) : data) }, waitUntil: (x) => { p = x; } });
            await p;
            return w.shown[w.shown.length - 1];
        };
        let n = await push({ title: '[PMD-1] Login fails', url: O + scope + '?notices=1' });
        check(scope + ': a push shows the subject, with the app icon', n && n.title === '[PMD-1] Login fails' && n.opts.icon === scope + 'pwa-icon/192' && n.opts.data.url === O + scope + '?notices=1', n);
        check(scope + ': the notice has no body (only the subject is sent)', n && n.opts.body === undefined, n);
        n = await push({ title: 'x', url: 'https://evil.example.invalid' + scope });
        check(scope + ': a link outside the portal: the bell\'s list instead', n && n.opts.data.url === O + scope + '?notices=1', n);
        n = await push({ title: 'x', url: O + '/admin/data/users' });
        check(scope + ': a link to another page of Exment: the bell\'s list instead', n && n.opts.data.url === O + scope + '?notices=1', n);
        n = await push(undefined);
        check(scope + ': a push without data still shows a notice (the app name)', n && n.title === 'PM', n);
        n = await push('not json');
        check(scope + ': a push that is not JSON still shows a notice', n && n.title === 'PM', n);
        n = await push({ title: 'a'.repeat(300) });
        check(scope + ': a long title is cut to 120', n && n.title.length === 120, n && n.title.length);

        const tap = async (url) => {
            let p = null;
            let closed = false;
            w.listeners.notificationclick({ notification: { data: { url: url }, close: () => { closed = true; } }, waitUntil: (x) => { p = x; } });
            await p;
            return closed;
        };
        check(scope + ': a tap with no window open opens the link', (await tap(O + scope + '?notices=1')) && w.opened[w.opened.length - 1] === O + scope + '?notices=1', w.opened);
        await tap('https://evil.example.invalid/');
        check(scope + ': a tap never opens outside the portal', w.opened[w.opened.length - 1] === O + scope, w.opened);
        const win = { url: O + scope + '12', focused: false, went: null, focus() { this.focused = true; return Promise.resolve(this); }, navigate(u) { this.went = u; return Promise.resolve(this); } };
        w.windows.push({ url: O + '/admin/data/users', focus() { return Promise.resolve(this); } }, win);
        const before2 = w.opened.length;
        await tap(O + scope + '?notices=1');
        check(scope + ': a tap with the portal open: that window goes to the link, focused', win.focused && win.went === O + scope + '?notices=1' && w.opened.length === before2, win);
    }
    check('no skipWaiting', src.indexOf('skipWaiting()') < 0);
    console.log('\n' + (bad === 0 ? 'all passed (' + ok + ')' : bad + ' failed, ' + ok + ' passed'));
    process.exit(bad === 0 ? 0 : 1);
})();
