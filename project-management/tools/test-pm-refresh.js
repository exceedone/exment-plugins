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

if (failed) {
    console.log('\n' + failed + ' failed');
    process.exit(1);
}
console.log('\nall passed');
