#!/usr/bin/env node
// ExmentPM: the records of other tables an issue links to, on the screen (ISS02939).
//
//   node project-management/tools/test-pm-record-links.js
//
// The pure helpers of resources/js/src/issue-record-links.js, read out of the
// source and run with small stand-ins: the rows of the box (a record the reader
// may not read, an address that is not http(s)), what a search sends, the found
// rows and the 300 ms wait. The views and the settings pane are read as text.
'use strict';

const fs = require('fs');
const path = require('path');
const assert = require('assert');

const PM = path.join(__dirname, '..', 'ExmentPM');
const SRC = path.join(PM, 'resources', 'js', 'src');
const read = (name) => fs.readFileSync(path.join(SRC, name), 'utf8');
const view = (name) => fs.readFileSync(path.join(PM, 'resources', 'views', name), 'utf8');

// The body of a function declared in a source file, by counting braces.
function functionSource(source, name) {
    const start = source.indexOf('function ' + name + '(');
    assert.ok(start >= 0, 'function ' + name + ' is not in the source');
    let depth = 0;
    for (let i = source.indexOf('{', start); i < source.length; i++) {
        if (source[i] === '{') { depth++; }
        if (source[i] === '}' && --depth === 0) { return source.slice(start, i + 1); }
    }
    throw new Error('function ' + name + ' does not end');
}
function load(source, names, prelude, extra) {
    const body = (prelude || '') + '\n' + names.map((n) => functionSource(source, n)).join('\n') +
        '\nreturn {' + names.concat(extra || []).join(',') + '};';
    return new Function(body)();
}

let failed = 0;
function test(name, fn) {
    try {
        fn();
        console.log('ok   ' + name);
    } catch (e) {
        failed++;
        console.log('FAIL ' + name + '\n     ' + String(e && e.message || e).split('\n').join('\n     '));
    }
}

const rl = read('issue-record-links.js');
const BASE = 'https://pm.example.com/admin/plugins/pm/7';
// A clock of our own: setTimeout only queues, tick(ms) runs what is due.
const STUBS = `
    var D = { urls: { base: ${JSON.stringify(BASE)}, link_search: ${JSON.stringify(BASE + '/links/search')} },
        record_links: { tables: [{ name: 'customer', label: '顧客' }, { name: 'contract', label: '契約' }, null] } };
    var L = { links: { hidden: '見られないデータ' } };
    var location = { href: ${JSON.stringify(BASE + '#issue-5')} };
    var NOW = 0, TIMERS = [], NEXT = 1;
    function setTimeout(fn, ms) { var id = NEXT++; TIMERS.push({ id: id, at: NOW + ms, fn: fn }); return id; }
    function clearTimeout(id) { TIMERS = TIMERS.filter(function (t) { return t.id !== id; }); }
    function tick(ms) {
        NOW += ms;
        TIMERS.filter(function (t) { return t.at <= NOW; }).forEach(function (t) { clearTimeout(t.id); t.fn(); });
    }
    function getD() { return D; }
`;
const R = load(rl, ['rlLang', 'rlTables', 'rlOn', 'rlUrl', 'rlSafeHref', 'rlRows', 'rlSearchParams', 'rlFound', 'rlDebounce'],
    STUBS, ['tick', 'getD']);
const D = R.getD();

/* ---------------------------------------------------- the rows ------ */
test('a readable record: its table, its title and its address', () => {
    const rows = R.rlRows([{ id: 3, table: 'customer', tl: '顧客', rid: 12, t: '株式会社A', u: 'https://pm.example.com/admin/data/customer/12', can: true }],
        '見られないデータ', BASE);
    assert.deepStrictEqual(rows, [{ id: '3', label: '顧客', text: '株式会社A', href: 'https://pm.example.com/admin/data/customer/12', hidden: false }]);
});
test('a record the reader may not read: no title, no address, the hidden text', () => {
    const rows = R.rlRows([{ id: 4, table: 'contract', tl: '契約', rid: 9, t: null, u: null, can: false },
        { id: 5, table: 'contract', tl: '契約', rid: 10, t: 'leak', u: 'https://x/', can: false }], '見られないデータ', BASE);
    rows.forEach((r) => {
        assert.strictEqual(r.text, '見られないデータ');
        assert.strictEqual(r.href, '');
        assert.strictEqual(r.hidden, true);
        assert.strictEqual(r.label, '契約');
    });
});
test('a readable record without a title says its number; the table name stands in for a lost label', () => {
    const rows = R.rlRows([{ id: 6, table: 'asset', rid: 77, t: '', u: '', can: true }], '', BASE);
    assert.strictEqual(rows[0].text, '#77');
    assert.strictEqual(rows[0].label, 'asset');
    assert.strictEqual(rows[0].href, '');
});
test('only http(s) goes into href; a relative address is made absolute on this site', () => {
    assert.strictEqual(R.rlSafeHref('javascript:alert(1)', BASE), '');
    assert.strictEqual(R.rlSafeHref(' JavaScript:alert(1)', BASE), '');
    assert.strictEqual(R.rlSafeHref('java\tscript:alert(1)', BASE), '');
    assert.strictEqual(R.rlSafeHref('data:text/html,<b>x</b>', BASE), '');
    assert.strictEqual(R.rlSafeHref('vbscript:x', BASE), '');
    assert.strictEqual(R.rlSafeHref('', BASE), '');
    assert.strictEqual(R.rlSafeHref(null, BASE), '');
    assert.strictEqual(R.rlSafeHref({}, BASE), '');
    assert.strictEqual(R.rlSafeHref('/admin/data/customer/1', BASE), 'https://pm.example.com/admin/data/customer/1');
    assert.strictEqual(R.rlSafeHref('http://other.example.com/a?b=1', BASE), 'http://other.example.com/a?b=1');
    const row = R.rlRows([{ id: 1, tl: 'x', rid: 1, t: 'a', u: 'javascript:alert(1)', can: true }], '', BASE)[0];
    assert.strictEqual(row.href, '');
    assert.strictEqual(row.text, 'a');
});
test('the title stays text: the box is built without innerHTML', () => {
    const row = R.rlRows([{ id: 1, tl: '<i>t</i>', rid: 1, t: '<img src=x onerror=alert(1)>', u: '/a', can: true }], '', BASE)[0];
    assert.strictEqual(row.text, '<img src=x onerror=alert(1)>');
    assert.strictEqual(row.label, '<i>t</i>');
    assert.ok(rl.indexOf('innerHTML') < 0, 'textContent only');
});
test('rubbish in, no rows out', () => {
    assert.deepStrictEqual(R.rlRows(null, '', BASE), []);
    assert.deepStrictEqual(R.rlRows({}, '', BASE), []);
});

/* ---------------------------------------------------- the switch ---- */
test('the box shows while the project links to some table; the issue URL is like the others', () => {
    assert.strictEqual(R.rlOn(), true);
    assert.deepStrictEqual(R.rlTables().map((t) => t.name), ['customer', 'contract']);
    assert.strictEqual(R.rlUrl(5), BASE + '/issue/5/links');
    const keep = D.record_links;
    D.record_links = { tables: [], settings: { on: true } };
    assert.strictEqual(R.rlOn(), false);
    delete D.record_links;
    assert.strictEqual(R.rlOn(), false);
    D.record_links = keep;
});

/* ---------------------------------------------------- the search ---- */
test('a search sends a table of the project and the words, trimmed and cut at 100', () => {
    const tables = R.rlTables();
    assert.deepStrictEqual(R.rlSearchParams('customer', '  ACME ', tables), { table: 'customer', q: 'ACME' });
    assert.deepStrictEqual(R.rlSearchParams('customer', null, tables), { table: 'customer', q: '' });
    assert.strictEqual(R.rlSearchParams('customer', 'x'.repeat(150), tables).q.length, 100);
    assert.strictEqual(R.rlSearchParams('user', 'a', tables), null);
    assert.strictEqual(R.rlSearchParams('', 'a', tables), null);
});
test('at most 20 found rows, ids as strings, a title-less record by its number', () => {
    const found = [];
    for (let i = 1; i <= 25; i++) { found.push({ id: i, t: i === 2 ? '' : 'r' + i }); }
    const rows = R.rlFound(found);
    assert.strictEqual(rows.length, 20);
    assert.deepStrictEqual(rows[0], { id: '1', text: 'r1' });
    assert.deepStrictEqual(rows[1], { id: '2', text: '#2' });
    assert.deepStrictEqual(R.rlFound(undefined), []);
});
test('the search waits 300 ms after the last key, and a cancel forgets it', () => {
    const calls = [];
    const later = R.rlDebounce((v) => calls.push(v), 300);
    later('a'); R.tick(100); later('ab'); R.tick(299);
    assert.deepStrictEqual(calls, []);
    R.tick(1);
    assert.deepStrictEqual(calls, ['ab']);
    later('abc'); R.tick(200); later.cancel(); R.tick(500);
    assert.deepStrictEqual(calls, ['ab']);
    assert.ok(/rlDebounce\(run, 300\)/.test(rl), 'the input waits 300 ms');
});

/* ---------------------------------------------------- wiring -------- */
test('the detail draws the box and reads the links again for an issue opened afresh', () => {
    const det = read('issue-detail.js');
    assert.ok(/renderRelated\(i, res\);\s*\n\s*rlRender\(i, res\);/.test(det));
    assert.ok(functionSource(det, 'renderDetail').indexOf('rlForget(id)') > 0);
    const render = functionSource(rl, 'rlRender');
    assert.ok(render.indexOf('res.editable') > 0, 'remove and add follow the editable flag');
    assert.ok(/target = '_blank'[\s\S]*?rel = 'noopener'/.test(render));
    assert.ok(render.indexOf('window.confirm') > 0, 'a remove asks first');
    const build = fs.readFileSync(path.join(__dirname, 'build-pm-script.js'), 'utf8');
    assert.ok(build.indexOf("'issue-record-links.js'") > build.indexOf("'issue-detail.js'"));
});
test('the views know the links', () => {
    const index = view('index.blade.php');
    assert.ok(index.indexOf("$lang['links'] = \\App\\Plugins\\ExmentPM\\PortalDefinition::trans('links');") > 0);
    assert.ok(index.indexOf('id="pp-d-reclinkbox"') > 0 && index.indexOf('id="pp-d-reclinks"') > 0);
    const settings = view('_settings.blade.php');
    assert.ok(/links: paneLinks/.test(settings));
    assert.ok(settings.indexOf("'ln' => PortalDefinition::trans('links')") > 0);
    assert.ok(settings.indexOf("['links', $st('tab_links'), 'fa-link']") > 0);
    assert.ok(/foot\.hidden = \[[^\]]*'links'/.test(settings));
});
test('the settings pane keeps the order of the kept tables and adds the new ones at the end', () => {
    const settings = view('_settings.blade.php');
    const lnOrder = new Function(functionSource(settings, 'lnOrder') + '\nreturn lnOrder;')();
    const choices = [{ name: 'a' }, { name: 'b' }, { name: 'c' }, { name: 'd' }];
    assert.deepStrictEqual(lnOrder(['c', 'a'], ['a', 'b', 'c'], choices), ['c', 'a', 'b']);
    assert.deepStrictEqual(lnOrder(['c', 'a'], ['d'], choices), ['d']);
    assert.deepStrictEqual(lnOrder([], [], choices), []);
});

if (failed) {
    console.log('\n' + failed + ' failed');
    process.exit(1);
}
console.log('\nall passed');
