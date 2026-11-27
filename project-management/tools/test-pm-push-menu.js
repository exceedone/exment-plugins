// ExmentPM: 「この端末に通知する」 in the top bar (resources/views/_topbar.blade.php, Web Push).
// The script of the menu item, taken out of the Blade file and run with a fake browser: no
// network, the server is a fake fetch.
//   node project-management/tools/test-pm-push-menu.js
'use strict';
const fs = require('fs');
const path = require('path');
const vm = require('vm');

const blade = fs.readFileSync(path.join(__dirname, '../ExmentPM/resources/views/_topbar.blade.php'), 'utf8');
const start = blade.indexOf('    // Web Push: 「この端末に通知する」');
const end = blade.indexOf('\n    })();', start);
if (start < 0 || end < 0) {
    console.log('NG  the script of the menu item was not found');
    process.exit(1);
}
const src = blade.slice(start, end + '\n    })();'.length);

let ok = 0, bad = 0;
function check(label, cond, extra) {
    console.log((cond ? 'OK  ' : 'NG  ') + label + (cond ? '' : '  ' + JSON.stringify(extra)));
    cond ? ok++ : bad++;
}
const b64u = (bytes) => Buffer.from(bytes).toString('base64').replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
const KEY = new Uint8Array(65).map((_, i) => (i === 0 ? 4 : i));
const OTHER = new Uint8Array(65).map((_, i) => (i === 0 ? 4 : 200 - i));
const tick = () => new Promise((r) => setTimeout(r, 0));

/**
 * @param {object} o key: the page's key ('' none yet); sub: the browser's subscription (its key) or null;
 *   answers: action => JSON of the server; permission
 */
function load(o) {
    const calls = [];
    const alerts = [];
    let current = o.sub ? makeSub(o.sub) : null;
    function makeSub(keyBytes) {
        const s = {
            endpoint: 'https://fcm.googleapis.com/fcm/send/test-' + Math.random(),
            options: { applicationServerKey: keyBytes.buffer },
            unsubscribed: false,
            toJSON() { return { endpoint: this.endpoint, keys: { p256dh: 'x', auth: 'y' } }; },
            unsubscribe() { this.unsubscribed = true; if (current === s) { current = null; } return Promise.resolve(true); },
        };
        return s;
    }
    const first = current;
    const icon = { className: 'fa fa-fw' };
    const attrs = { 'data-key': o.key, 'data-url': '/admin/plugins/exment_pm/push', 'aria-checked': 'false' };
    const listeners = {};
    const item = {
        hidden: true,
        getAttribute: (k) => attrs[k],
        setAttribute: (k, v) => { attrs[k] = String(v); },
        querySelector: () => icon,
        addEventListener: (t, f) => { listeners[t] = f; },
    };
    const subscribed = [];
    const reg = {
        pushManager: {
            getSubscription: () => Promise.resolve(current),
            subscribe: (opts) => { subscribed.push(opts); current = makeSub(opts.applicationServerKey); return Promise.resolve(current); },
        },
    };
    const sandbox = {
        LANG: { push_denied: 'denied', push_failed: 'failed', push_ios: 'ios' },
        atob: (s) => Buffer.from(s, 'base64').toString('binary'),
        Uint8Array, Promise, JSON, Error,
        document: { querySelector: (sel) => (sel === '.pp-push-toggle' ? item : (sel === 'meta[name="csrf-token"]' ? { getAttribute: () => 'tok' } : null)) },
        navigator: { serviceWorker: { ready: Promise.resolve(reg) }, userAgent: 'Mozilla/5.0 (X11; Linux x86_64) Chrome/131.0', platform: 'Linux', maxTouchPoints: 0 },
        fetch: (url, init) => {
            const body = JSON.parse(init.body);
            calls.push(body);
            const a = o.answers[body.action];
            return Promise.resolve({ json: () => (a === undefined ? Promise.reject(new Error('not json')) : Promise.resolve(typeof a === 'function' ? a(body) : a)) });
        },
    };
    sandbox.window = {
        PushManager: function () {}, Notification: {},
        matchMedia: () => ({ matches: false }),
        alert: (m) => { alerts.push(m); },
    };
    sandbox.Notification = { permission: o.permission || 'granted', requestPermission: () => Promise.resolve(o.grant || 'granted') };
    sandbox.window.Notification = sandbox.Notification;
    vm.createContext(sandbox);
    vm.runInContext(src, sandbox);
    return { item, attrs, icon, calls, alerts, first, subscribed, click: () => listeners.click(), current: () => current };
}

(async () => {
    // this person's subscription, kept on the server: shown on
    let p = load({ key: b64u(KEY), sub: KEY, answers: { state: { ok: true, on: true } } });
    await tick(); await tick(); await tick();
    check('this person\'s browser: shown on, kept', !p.item.hidden && p.attrs['aria-checked'] === 'true' && !p.first.unsubscribed, p.attrs);
    check('the state is asked with this browser\'s subscription', p.calls.length === 1 && p.calls[0].action === 'state' && p.calls[0].subscription.endpoint === p.first.endpoint);

    // a shared browser: kept on the server for someone else (or removed there) -> the browser stops
    p = load({ key: b64u(KEY), sub: KEY, answers: { state: { ok: true, on: false } } });
    await tick(); await tick(); await tick(); await tick();
    check('another person\'s subscription on this browser: unsubscribed in the browser, shown off', p.first.unsubscribed && p.attrs['aria-checked'] === 'false' && !p.item.hidden, p.attrs);

    // the server did not answer: nothing is undone
    p = load({ key: b64u(KEY), sub: KEY, answers: {} });
    await tick(); await tick(); await tick(); await tick();
    check('no answer from the server: the browser keeps its subscription', !p.first.unsubscribed && p.attrs['aria-checked'] === 'false');

    // made with an older key of the site (「鍵を作り直す」): off, unsubscribed, no state asked
    p = load({ key: b64u(KEY), sub: OTHER, answers: { state: { ok: true, on: true } } });
    await tick(); await tick(); await tick();
    check('an older key: unsubscribed, off, the server not asked', p.first.unsubscribed && p.attrs['aria-checked'] === 'false' && p.calls.length === 0);

    // the site has no key yet: turning it on asks the push route for it, then subscribes
    p = load({ key: '', sub: null, answers: { key: { ok: true, key: b64u(KEY) }, subscribe: { ok: true, on: true } } });
    await tick(); await tick();
    p.click();
    for (let i = 0; i < 10; i++) { await tick(); }
    check('no key yet: the key is asked for, then the browser subscribes with it', p.calls.map((c) => c.action).join(',') === 'key,subscribe'
        && p.subscribed.length === 1 && Buffer.from(p.subscribed[0].applicationServerKey).equals(Buffer.from(KEY)) && p.attrs['aria-checked'] === 'true', p.calls);

    // a subscription the server refuses: undone in the browser, the message shown
    p = load({ key: b64u(KEY), sub: null, answers: { subscribe: { ok: false, message: 'refused here' } } });
    await tick(); await tick();
    p.click();
    for (let i = 0; i < 10; i++) { await tick(); }
    check('refused by the server: unsubscribed again, the message shown', p.current() === null && p.alerts[0] === 'refused here' && p.attrs['aria-checked'] === 'false', p.alerts);

    // permission denied: no subscription
    p = load({ key: b64u(KEY), sub: null, grant: 'denied', answers: {} });
    await tick(); await tick();
    p.click();
    for (let i = 0; i < 6; i++) { await tick(); }
    check('permission denied: nothing subscribed, told so', p.subscribed.length === 0 && p.alerts[0] === 'denied' && p.calls.length === 0);

    // on -> off: the server first, then the browser
    p = load({ key: b64u(KEY), sub: KEY, answers: { state: { ok: true, on: true }, unsubscribe: { ok: true, on: false } } });
    await tick(); await tick(); await tick();
    p.click();
    for (let i = 0; i < 6; i++) { await tick(); }
    check('turned off: removed on the server and in the browser', p.calls.map((c) => c.action).join(',') === 'state,unsubscribe' && p.first.unsubscribed && p.attrs['aria-checked'] === 'false', p.calls);

    console.log('\n' + (bad === 0 ? 'all passed (' + ok + ')' : bad + ' failed, ' + ok + ' passed'));
    process.exit(bad === 0 ? 0 : 1);
})();
