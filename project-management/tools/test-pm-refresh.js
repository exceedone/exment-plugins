#!/usr/bin/env node
// ExmentPM: the list after a refresh and after a save in the form.
//
//   node project-management/tools/test-pm-refresh.js
//
// The project's data is sent with its list rows packed (PmPortalController::
// packRows): empty values, the key and the addresses are left out and put back
// by the script (unpackRows). The payload a refresh fetches is packed the same
// way. When only the page's own rows were unpacked, the first redraw after a
// refresh stopped on a row without assignees, the list kept its old rows and
// no later refresh ever ran.
'use strict';

const fs = require('fs');
const path = require('path');
const assert = require('assert');

const SRC = path.join(__dirname, '..', 'ExmentPM', 'resources', 'js', 'src');
const read = (name) => fs.readFileSync(path.join(SRC, name), 'utf8');

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

const core = read('core.js');
const list = read('list.js');
const save = read('form-save.js');
const writes = read('writes.js');

test('unpackRows puts back what a packed row leaves out', () => {
    const unpackRows = new Function(functionSource(core, 'unpackRows') + '; return unpackRows;')();
    const data = {
        row_pack: { defaults: { as: [], cat: [], ms: '', sub: '', pg: null }, url: '/admin/data/pm_issue', key: 'PMTEST' },
        issues: [
            { id: 11, subject: 'no assignee', st: '1', ua: '2026-11-22 05:32:42' },
            { id: 12, subject: 'with one', st: '1', as: [3], key: 'KEPT-12' },
        ],
    };
    unpackRows(data);
    const [a, b] = data.issues;
    assert.deepStrictEqual(a.as, []);
    assert.deepStrictEqual(a.cat, []);
    assert.notStrictEqual(a.as, data.issues[1].as, 'each row has its own array');
    assert.strictEqual(a.pg, null);
    assert.strictEqual(a.key, 'PMTEST-11');
    assert.strictEqual(a.url, '/admin/data/pm_issue/11');
    assert.strictEqual(a.edit_url, '/admin/data/pm_issue/11/edit');
    assert.strictEqual(a.up, '2026/11/22 05:32');
    assert.deepStrictEqual(b.as, [3]);
    assert.strictEqual(b.key, 'KEPT-12', 'a value the row carries is kept');
    // every quick filter of the list can read every row
    data.issues.forEach((i) => assert.ok(Array.isArray(i.as.map(String))));
});

test('unpackRows leaves a payload that is not packed alone', () => {
    const unpackRows = new Function(functionSource(core, 'unpackRows') + '; return unpackRows;')();
    const data = { issues: [{ id: 1 }] };
    unpackRows(data);
    assert.deepStrictEqual(data.issues, [{ id: 1 }]);
    unpackRows(null);
});

test('the page unpacks the rows it came with', () => {
    assert.ok(/\n {4}unpackRows\(D\);/.test(core), 'core.js calls unpackRows(D) at start');
});

test('a refreshed payload is unpacked before the screens are drawn from it', () => {
    const adopt = functionSource(list, 'adoptPayload');
    const unpack = adopt.indexOf('unpackRows(next)');
    const swap = adopt.indexOf('D = next');
    assert.ok(unpack >= 0, 'adoptPayload calls unpackRows(next)');
    assert.ok(swap >= 0 && unpack < swap, 'before the payload becomes D');
    assert.ok(swap < adopt.indexOf('renderIssues()'), 'and before the list is drawn');
});

test('a redraw that fails does not leave the refresh busy for good', () => {
    const refresh = functionSource(list, 'refreshData');
    assert.ok(/try\s*\{\s*adoptPayload\(res\.payload, seq\);\s*\}\s*catch/.test(refresh), 'adoptPayload is called inside try/catch');
});

test('a save in the form brings the list row up to the saved values', () => {
    assert.ok(writes.indexOf('function formAdopt(') >= 0, 'formAdopt is defined');
    const adopt = functionSource(writes, 'formAdopt');
    ['subject', 'status', 'type'].forEach((role) => {
        assert.ok(writes.indexOf(role + "'") >= 0 && /FORM_COLS/.test(adopt), 'the ' + role + ' column is adopted');
    });
    assert.ok(/qeAdopt\(issueId, \{\}, answer\)/.test(adopt), 'priority, assignees, dates … through qeAdopt');
    // before the form checks whether it is still the one on screen: the row
    // is brought up to date also when the form was left before the answer
    const at = save.indexOf('formAdopt(rowId, res.body)');
    assert.ok(at >= 0, 'form-save.js calls formAdopt with the answer');
    const left = save.indexOf('if (!nedCurrent(epoch)) {', save.indexOf('var ok = (res.status === 200'));
    assert.ok(left >= 0 && at < left, 'before the early return of a form that was left');
});

test('an issue just opened stays read when a refresh fetched before the mark arrives', () => {
    const reads = read('read-receipts.js');
    const decl = reads.slice(reads.indexOf('var READ_LOCAL = {};'), reads.indexOf('function applyLocalReads('));
    const make = new Function(decl + functionSource(reads, 'readLocal') + functionSource(reads, 'applyLocalReads') +
        '; return { readLocal, applyLocalReads };');
    const r = make();
    r.readLocal(11, true);
    r.readLocal(12, false);
    const payload = { issues: [{ id: 11, unread: true }, { id: 12, unread: false }, { id: 13, unread: true }] };
    r.applyLocalReads(payload);
    assert.deepStrictEqual(payload.issues.map((i) => i.unread), [false, true, true]);
    // a minute later the server's answer is the truth again (somebody may have written since)
    const later = { issues: [{ id: 11, unread: true }] };
    r.applyLocalReads(later, Date.now() + 61000);
    assert.strictEqual(later.issues[0].unread, true);
    // the refresh applies them before the payload becomes D, and opening an issue records one
    const adopt = functionSource(list, 'adoptPayload');
    assert.ok(adopt.indexOf('applyLocalReads(next)') >= 0 && adopt.indexOf('applyLocalReads(next)') < adopt.indexOf('D = next'));
    assert.ok(/readLocal\(key, read\)/.test(functionSource(reads, 'markRead')));
});

// The read marks themselves: they reach the server even when the page is
// reloaded right after (they used to wait in the slow lane and were lost).
function readSandbox() {
    const reads = read('read-receipts.js');
    const decl = reads.slice(reads.indexOf('var READ_SENT = {};'), reads.indexOf('function readAv('));
    const body = reads.slice(reads.indexOf('    var READ_UNSENT = {};'), reads.indexOf('    // "Mail this issue"'));
    const env = { jobs: [], beacons: [], listeners: {}, timers: [] };
    const make = new Function('env', `
        var D = { urls: { base: '/p/5' }, issues: [{ id: 11, unread: true }, { id: 12, unread: true }, { id: 13, unread: false }], users: {} };
        var CSRF = 'tok', DET = {}, currentIssue = null;
        function issueById(id) { return D.issues.filter(function (i) { return String(i.id) === String(id); })[0] || null; }
        function own(o, k) { return Object.prototype.hasOwnProperty.call(o, k) ? o[k] : undefined; }
        function alive() { return true; }
        function readsHtml() { return ''; }
        function paintUnread() {}
        var document = { querySelector: function () { return null; } };
        var window = { addEventListener: function (n, f) { env.listeners[n] = f; } };
        function setTimeout(f) { env.timers.push(f); return env.timers.length; }
        function FormData() { this.v = {}; }
        FormData.prototype.append = function (k, v) { this.v[k] = v; };
        var navigator = { sendBeacon: function (url, body) { env.beacons.push({ url: url, v: body.v }); return true; } };
        var $ = { extend: Object.assign, ajax: function (o) { return o; } };
        // Answers when the test says so, and at once (no microtask to wait for).
        function ppQueue(task, low) {
            var job = { low: !!low, opts: null, cbs: [] };
            var p = {
                then: function (a, b) { job.cbs.push([a, b]); return p; },
                catch: function (b) { job.cbs.push([null, b]); return p; }
            };
            job.run = function (ok) {
                job.opts = task();
                job.cbs.forEach(function (c) { var f = ok ? c[0] : c[1]; if (f) { f({ ok: true, reads: [] }); } });
            };
            env.jobs.push(job);
            return p;
        }
        ` + decl + body + `
        return { markRead: markRead, D: D, unsent: function () { return READ_UNSENT; } };`);
    return { env, api: make(env) };
}

test('a read mark goes in the normal lane, after the click', () => {
    const { env, api } = readSandbox();
    api.markRead(11, true);
    assert.strictEqual(env.jobs.length, 0, 'queued after the click\'s own requests');
    assert.strictEqual(api.D.issues[0].unread, false, 'shown read at once');
    env.timers.shift()();
    assert.strictEqual(env.jobs.length, 1);
    assert.strictEqual(env.jobs[0].low, false, 'not the slow lane, which a reload or a page swap drops');
    env.jobs[0].run(true);
    assert.strictEqual(env.jobs[0].opts.url, '/p/5/issue/11/read');
    assert.deepStrictEqual(env.jobs[0].opts.data, { read: 1 });
    assert.deepStrictEqual(Object.keys(api.unsent()), []);
});

test('the marks of one click go in one request', () => {
    const { env, api } = readSandbox();
    api.markRead(11, false);
    api.markRead(12, false);
    env.timers.shift()();
    assert.strictEqual(env.jobs.length, 1);
    env.jobs[0].run(true);
    assert.strictEqual(env.jobs[0].opts.url, '/p/5/reads');
    assert.deepStrictEqual(env.jobs[0].opts.data, { read: 0, ids: '11,12' });
    assert.deepStrictEqual(Object.keys(api.unsent()), [], 'answered: nothing left to send');
});

test('a mark not answered yet when the page goes away leaves with sendBeacon', () => {
    const { env, api } = readSandbox();
    api.markRead(11, true);
    env.timers.shift()();
    env.jobs[0].run(true);              // answered
    api.markRead(12, true);             // queued, its turn not come yet
    env.timers.shift()();
    api.markRead(13, false);            // not even queued yet
    assert.ok(env.listeners.pagehide, 'listens for the page going away');
    env.listeners.pagehide();
    assert.deepStrictEqual(env.beacons, [
        { url: '/p/5/reads', v: { _token: 'tok', read: '1', ids: '12' } },
        { url: '/p/5/reads', v: { _token: 'tok', read: '0', ids: '13' } },
    ], 'the answered mark (11) is not sent again');
    assert.deepStrictEqual(Object.keys(api.unsent()), []);
});

test('a mark that failed is sent again at the next opening', () => {
    const { env, api } = readSandbox();
    api.markRead(11, true);
    env.timers.shift()();
    env.jobs[0].run(false);
    api.D.issues[0].unread = false;
    api.markRead(11, true);             // READ_SENT was cleared: it goes again
    assert.strictEqual(env.timers.length, 1);
});

// ISS02931: the other people of an issue on the list, and set from the issue.
test('the role filter matches the person in that role only', () => {
    const rlMatch = new Function(functionSource(list, 'rlMatch') + '; return rlMatch;')();
    const i = { sa: [3], mg: [5], rv: [] };
    assert.strictEqual(rlMatch(i, 'sa:3'), true);
    assert.strictEqual(rlMatch(i, 'mg:3'), false);
    assert.strictEqual(rlMatch(i, 'rv:5'), false);
    assert.strictEqual(rlMatch({}, 'sa:3'), false, 'a row without the column');
    assert.strictEqual(rlMatch(i, 'zz:3'), true, 'a value off the address bar that names no role filters nothing');
    assert.ok(/'rl'/.test(list.slice(0, list.indexOf('\n', list.indexOf('var FILTER_FIELDS')))), 'rl rides in the URL and the saved filters');
});

test('a saved role or notified-users field comes back as user ids', () => {
    const qeRowValue = new Function(functionSource(writes, 'qeRowValue') + '; return qeRowValue;')();
    ['sa', 'mg', 'rv', 'nu'].forEach((k) => assert.deepStrictEqual(qeRowValue(k, ['3', '0', 'x', 5]), [3, 5], k));
    assert.strictEqual(qeRowValue('df', 'hard'), 'hard');
});

test('the dock and the issue-side pencils save through one function', () => {
    const post = functionSource(writes, 'postComment');
    assert.ok(/qeSaveFields\(issueId, changed, version\)/.test(post), 'the dock calls qeSaveFields');
    const detail = read('issue-detail.js');
    assert.ok(/qeSaveFields\(issueId, changed, /.test(functionSource(detail, 'qfOpen')), 'the pencils call qeSaveFields');
    assert.ok(/fetch\(fieldsUrl/.test(functionSource(writes, 'qeSaveFields')), 'through the row API');
});

test('each comment carries its own link, and #comment-<id> is a route', () => {
    const detail = read('issue-detail.js');
    const tl = functionSource(detail, 'renderTimeline');
    assert.ok(/class="pp-ccopy" data-cid=/.test(tl), 'a copy-link button on every comment');
    const routing = read('routing.js');
    assert.ok(functionSource(routing, 'route').indexOf('path.match(/^comment-(\\d+)$/)') >= 0, 'route() knows #comment-<id>');
});

if (failed) {
    console.log('\n' + failed + ' failed');
    process.exit(1);
}
console.log('\nall passed');
