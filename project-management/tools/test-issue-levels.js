#!/usr/bin/env node
// ExmentPM: issues nested up to five levels, on the screen.
//
//   node project-management/tools/test-issue-levels.js
//
// The tree of the list puts every level under its parent (indent, fold), and
// the progress of what hangs under an issue is shown as the average of its
// descendant leaves (a done one counts 100), never saved.
'use strict';

const fs = require('fs');
const path = require('path');
const assert = require('assert');

const SRC = path.join(__dirname, '..', 'ExmentPM', 'resources', 'js', 'src');
const read = (name) => fs.readFileSync(path.join(SRC, name), 'utf8');

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

const list = read('list.js');
const errors = read('errors.js');

// five levels: 1 > 2 > 3 > 4 > 5, and 6 beside 2; 7 a loop of old data with 8
const issues = [
    { id: 1, parent: null, st: 'open', pg: 10 },
    { id: 2, parent: 1, st: 'open', pg: 20 },
    { id: 3, parent: 2, st: 'open', pg: 30 },
    { id: 4, parent: 3, st: 'open', pg: 40 },
    { id: 5, parent: 4, st: 'open', pg: 50 },
    { id: 6, parent: 1, st: 'done', pg: 0 },
    { id: 7, parent: 8, st: 'open', pg: 0 },
    { id: 8, parent: 7, st: 'open', pg: 0 },
    { id: 9, parent: 4, st: 'open', pg: null },
];

test('the tree puts all five levels under their parents', () => {
    const treeRows = new Function('TREE_FOLD',
        'function treeFolded(id) { return Object.prototype.hasOwnProperty.call(TREE_FOLD, "i" + id); }\n' +
        functionSource(list, 'treeRows') + '; return treeRows;')({});
    const rows = treeRows(issues, true);
    const at = {};
    rows.forEach((r) => { at[r.i.id] = r; });
    assert.deepStrictEqual([1, 2, 3, 4, 5].map((id) => at[id].depth), [0, 1, 2, 3, 4]);
    assert.strictEqual(at[9].depth, 4);
    assert.strictEqual(at[6].depth, 1);
    assert.strictEqual(at[4].n, 2, 'level 4 has two children');
    assert.strictEqual(rows.length, issues.length, 'nothing dropped, a loop of old data included');
    // under its parent, in order: 1 2 3 4 5 9 6
    assert.deepStrictEqual(rows.slice(0, 7).map((r) => r.i.id), [1, 2, 3, 4, 5, 9, 6]);
});

test('a folded level hides everything under it, the levels above stay', () => {
    const treeRows = new Function('TREE_FOLD',
        'function treeFolded(id) { return Object.prototype.hasOwnProperty.call(TREE_FOLD, "i" + id); }\n' +
        functionSource(list, 'treeRows') + '; return treeRows;')({ i3: true });
    const ids = treeRows(issues, false).map((r) => r.i.id);
    assert.ok(ids.indexOf(3) >= 0);
    [4, 5, 9].forEach((id) => assert.ok(ids.indexOf(id) < 0, id + ' is under the folded 3'));
    assert.ok(ids.indexOf(6) >= 0);
});

test('the progress under an issue: the average of its leaves through every level', () => {
    const rollupMap = new Function('D', 'isDone',
        functionSource(errors, 'rollupMap') + '; return rollupMap;')({ issues }, (st) => st === 'done');
    const ru = rollupMap();
    // leaves under 1: 5 (50), 9 (no progress: 0), 6 (done: 100)
    assert.deepStrictEqual(ru.i1, { pg: 50, n: 3 });
    assert.deepStrictEqual(ru.i2, { pg: 25, n: 2 });
    assert.deepStrictEqual(ru.i4, { pg: 25, n: 2 });
    assert.strictEqual(ru.i5, undefined, 'a leaf has no roll-up');
    assert.strictEqual(ru.i6, undefined);
    // the loop of old data ends without a roll-up that never stops
    assert.ok(!ru.i7 || ru.i7.n >= 0);
});

test('the roll-up is shown, never written into the issue', () => {
    const copy = JSON.parse(JSON.stringify(issues));
    const rollupMap = new Function('D', 'isDone',
        functionSource(errors, 'rollupMap') + '; return rollupMap;')({ issues: copy }, (st) => st === 'done');
    rollupMap();
    assert.deepStrictEqual(copy, issues);
    const badge = new Function('L', 'esc', 'fmt',
        functionSource(errors, 'rollupBadge') + '; return rollupBadge;')(
        { rollup: '子の進捗', rollup_tip: '%s' }, (s) => String(s), (t, n) => t.replace('%s', n));
    assert.ok(badge({ i1: { pg: 50, n: 3 } }, 1).indexOf('子の進捗 50%') >= 0);
    assert.strictEqual(badge({}, 5), '');
});

test('no "add a child" on the last level', () => {
    const byId = {};
    issues.forEach((i) => { byId[i.id] = i; });
    const make = (levels) => new Function('D', 'issueById',
        functionSource(errors, 'issueLevel') + functionSource(errors, 'childRoom') + '; return { issueLevel, childRoom };')(
        { issues, issue_levels: levels }, (id) => byId[id]);
    const f = make(5);
    assert.deepStrictEqual([1, 2, 3, 4, 5].map((id) => f.issueLevel(byId[id])), [1, 2, 3, 4, 5]);
    assert.strictEqual(f.childRoom(byId[4]), true);
    assert.strictEqual(f.childRoom(byId[5]), false);
    assert.strictEqual(make(3).childRoom(byId[3]), false);
    assert.strictEqual(make(3).childRoom(byId[2]), true);
    // a loop of old data ends
    assert.ok(f.issueLevel(byId[7]) >= 1);
});

if (failed) {
    console.log('\n' + failed + ' failed');
    process.exit(1);
}
console.log('\nall passed');
