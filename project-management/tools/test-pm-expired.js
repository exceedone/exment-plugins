#!/usr/bin/env node
// ExmentPM: a request of the portal refused because the page expired (419).
//
//   node project-management/tools/test-pm-expired.js
//
// Exment answers a script that sent an expired or missing token with 419. The
// portal's scripts said nothing, or a toast that was gone in two seconds, and
// no way back. The script of _flash.blade.php (every screen with the top bar,
// and the settings page) watches the portal's own requests - jQuery and fetch -
// and on a 419 shows a bar that stays, with the reason and 再読み込み. Run here
// on a small stand-in of the page, with the Blade values filled in.
'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');
const assert = require('assert');

const VIEW = path.join(__dirname, '..', 'ExmentPM', 'resources', 'views', '_flash.blade.php');
const blade = fs.readFileSync(VIEW, 'utf8');

let failed = 0;
async function test(name, fn) {
    try {
        await fn();
        console.log('ok   ' + name);
    } catch (e) {
        failed++;
        console.log('FAIL ' + name + '\n     ' + String(e && e.message || e).split('\n').join('\n     '));
    }
}

// The script of the view, with the one Blade value (the words and the portal's path) filled in.
function script(lang) {
    const m = blade.match(/<script>([\s\S]*?)<\/script>/);
    assert.ok(m, 'no script in _flash.blade.php');
    const src = m[1].replace(/@json\(\$ppFlashLang[^)]*\)/, JSON.stringify(lang));
    assert.ok(!/@json|\{\{|\{!!/.test(src), 'Blade left in the script');
    return src;
}

// Just enough of a page: elements by id and by class, events, a body.
function element(tag) {
    const el = {
        tagName: String(tag).toUpperCase(), id: '', className: '', type: '', title: '', hidden: false,
        textContent: '', children: [], attrs: {}, listeners: {},
        setAttribute(k, v) { this.attrs[k] = String(v); },
        getAttribute(k) { return this.attrs[k]; },
        appendChild(c) { this.children.push(c); c.parentNode = this; return c; },
        addEventListener(t, f) { (this.listeners[t] = this.listeners[t] || []).push(f); },
        click() { (this.listeners.click || []).forEach((f) => f({})); },
        querySelector(sel) {
            const cls = sel.replace(/^\./, '');
            for (const c of this.children) {
                if (String(c.className).split(/\s+/).includes(cls)) { return c; }
                const deeper = c.querySelector && c.querySelector(sel);
                if (deeper) { return deeper; }
            }
            return null;
        },
    };
    return el;
}
function page(opts) {
    const body = element('body');
    const document = {
        body,
        createElement: element,
        getElementById(id) { return body.children.find((c) => c.id === id) || null; },
    };
    const reloads = [];
    const ajaxErrorHandlers = [];
    const fetchCalls = [];
    const window = {
        location: { href: 'https://pm.example.invalid/admin/plugins/exment_pm/12', origin: 'https://pm.example.invalid', reload() { reloads.push(1); } },
        fetch(input, init) {
            fetchCalls.push(input);
            const status = (opts && opts.status) || 200;
            return opts && opts.reject ? Promise.reject(new TypeError('Failed to fetch')) : Promise.resolve({ status, ok: status < 300, marker: 'same' });
        },
        jQuery(target) { return { ajaxError(f) { ajaxErrorHandlers.push(f); } }; },
        URL,
    };
    window.window = window;
    const ctx = vm.createContext({ window, document, URL, Promise, String, Object, JSON });
    return { ctx, window, document, body, reloads, ajaxErrorHandlers, fetchCalls };
}
const LANG = { expired: 'ページの有効期限が切れました。ページを再読み込みしてからやり直してください。', reload: '再読み込み', close: '閉じる', base: '/admin/plugins/exment_pm' };
const tick = () => new Promise((r) => setImmediate(r));
function bar(p) { return p.document.getElementById('pp-expired'); }

(async () => {
    await test('a fetch of the portal answered 419: the bar with the reason and 再読み込み', async () => {
        const p = page({ status: 419 });
        vm.runInContext(script(LANG), p.ctx);
        const res = await p.window.fetch('/admin/plugins/exment_pm/12/comment', { method: 'POST' });
        await tick();
        assert.strictEqual(res.marker, 'same', 'the caller gets the same answer');
        assert.strictEqual(res.status, 419);
        const el = bar(p);
        assert.ok(el, 'no bar');
        assert.strictEqual(el.hidden, false);
        assert.strictEqual(el.getAttribute('role'), 'alert');
        assert.strictEqual(el.querySelector('.tx').textContent, LANG.expired);
        assert.strictEqual(el.querySelector('.rl').textContent, '再読み込み');
        el.querySelector('.rl').click();
        assert.strictEqual(p.reloads.length, 1, '再読み込み reloads the page');
        el.querySelector('.cl').click();
        assert.strictEqual(el.hidden, true, '× closes it');
    });

    await test('the light API and a full address of the portal count; another path or site does not', async () => {
        for (const url of ['/admin/plugins/exment_pm/lightapi/data/pm_issue', 'https://pm.example.invalid/admin/plugins/exment_pm', 'lightapi/x']) {
            const p = page({ status: 419 });
            p.window.location.href = 'https://pm.example.invalid/admin/plugins/exment_pm/12';
            vm.runInContext(script(LANG), p.ctx);
            await p.window.fetch(url);
            await tick();
            if (url === 'lightapi/x') {
                // relative to /admin/plugins/exment_pm/12 -> /admin/plugins/exment_pm/lightapi/x
                assert.ok(bar(p), 'a relative address under the portal: ' + url);
            } else {
                assert.ok(bar(p), 'no bar for ' + url);
            }
        }
        for (const url of ['/admin/data/pm_issue/1', '/admin/plugins/exment_pm_other/1', 'https://other.example.invalid/admin/plugins/exment_pm/1']) {
            const p = page({ status: 419 });
            vm.runInContext(script(LANG), p.ctx);
            await p.window.fetch(url);
            await tick();
            assert.strictEqual(bar(p), null, 'a bar for ' + url);
        }
    });

    await test('a Request object is read by its url', async () => {
        const p = page({ status: 419 });
        vm.runInContext(script(LANG), p.ctx);
        await p.window.fetch({ url: 'https://pm.example.invalid/admin/plugins/exment_pm/12/star' });
        await tick();
        assert.ok(bar(p));
    });

    await test('other answers, and no answer at all, show no bar; the failure still reaches the caller', async () => {
        for (const status of [200, 403, 422, 500]) {
            const p = page({ status });
            vm.runInContext(script(LANG), p.ctx);
            await p.window.fetch('/admin/plugins/exment_pm/12/comment');
            await tick();
            assert.strictEqual(bar(p), null, 'a bar for ' + status);
        }
        const p = page({ reject: true });
        vm.runInContext(script(LANG), p.ctx);
        await assert.rejects(p.window.fetch('/admin/plugins/exment_pm/12/comment'));
        await tick();
        assert.strictEqual(bar(p), null);
    });

    await test('a jQuery request of the portal answered 419 (also one that fails quietly): the bar', async () => {
        const p = page();
        vm.runInContext(script(LANG), p.ctx);
        assert.strictEqual(p.ajaxErrorHandlers.length, 1);
        p.ajaxErrorHandlers[0]({}, { status: 500 }, { url: '/admin/plugins/exment_pm/12/lock' });
        assert.strictEqual(bar(p), null, 'a 500 is worded by the caller');
        p.ajaxErrorHandlers[0]({}, { status: 419 }, { url: '/admin/data/x' });
        assert.strictEqual(bar(p), null, "Exment's own request");
        p.ajaxErrorHandlers[0]({}, { status: 419 }, { url: '/admin/plugins/exment_pm/12/lock' });
        assert.ok(bar(p));
    });

    await test('run again by the page loader: the hooks are set once, the words renewed, one bar', async () => {
        const p = page({ status: 419 });
        vm.runInContext(script(LANG), p.ctx);
        const wrapped = p.window.fetch;
        vm.runInContext(script(Object.assign({}, LANG, { reload: 'Reload', expired: 'The page has expired.' })), p.ctx);
        assert.strictEqual(p.window.fetch, wrapped, 'fetch wrapped twice');
        assert.strictEqual(p.ajaxErrorHandlers.length, 1, 'ajaxError set twice');
        await p.window.fetch('/admin/plugins/exment_pm/12/comment');
        await p.window.fetch('/admin/plugins/exment_pm/12/comment');
        await tick();
        assert.strictEqual(p.body.children.filter((c) => c.id === 'pp-expired').length, 1);
        assert.strictEqual(bar(p).querySelector('.rl').textContent, 'Reload');
        assert.strictEqual(bar(p).querySelector('.tx').textContent, 'The page has expired.');
        assert.strictEqual(p.fetchCalls.length, 2, 'each request sent once');
    });

    await test('no portal path known: nothing is watched as the portal', async () => {
        const p = page({ status: 419 });
        vm.runInContext(script(Object.assign({}, LANG, { base: '' })), p.ctx);
        await p.window.fetch('/admin/plugins/exment_pm/12/comment');
        await tick();
        assert.strictEqual(bar(p), null);
    });

    await test('the words come from the lang files (ja and en), the text is set as text', () => {
        assert.ok(/trans\('http_error\.expired'\)/.test(blade));
        assert.ok(/trans\('flash\.reload'\)/.test(blade));
        assert.ok(/trans\('flash\.close'\)/.test(blade));
        assert.ok(!/innerHTML/.test(script(LANG)), 'innerHTML in the script');
    });

    console.log(failed === 0 ? '\nall passed' : '\n' + failed + ' failed');
    process.exit(failed === 0 ? 0 : 1);
})();
