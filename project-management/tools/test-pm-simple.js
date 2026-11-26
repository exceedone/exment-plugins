#!/usr/bin/env node
// ExmentPM: an issue's かんたん / くわしく (the button map): simple by
// default, each person's choice remembered, what simple hides.
//
//   node project-management/tools/test-pm-simple.js
'use strict';

const fs = require('fs');
const path = require('path');
const assert = require('assert');

const PM = path.join(__dirname, '..', 'ExmentPM');
const detail = fs.readFileSync(path.join(PM, 'resources', 'js', 'src', 'issue-detail.js'), 'utf8');
const style = fs.readFileSync(path.join(PM, 'resources', 'views', 'style.blade.php'), 'utf8');
const index = fs.readFileSync(path.join(PM, 'resources', 'views', 'index.blade.php'), 'utf8');
const langJa = fs.readFileSync(path.join(PM, 'resources', 'lang', 'ja', 'pmportal.php'), 'utf8');

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

function make(store, broken) {
    return new Function('store', 'broken',
        'var D = { me: 7 };' +
        'var localStorage = { getItem: function (k) { if (broken) { throw new Error("x"); } return Object.prototype.hasOwnProperty.call(store, k) ? store[k] : null; }, setItem: function (k, v) { if (broken) { throw new Error("x"); } store[k] = v; } };' +
        functionSource(detail, 'dmodeKey') + functionSource(detail, 'dmodeHintKey') + functionSource(detail, 'dmodeSimple') +
        functionSource(detail, 'dmodeHintShown') + functionSource(detail, 'dmodeHintDone') +
        'return { key: dmodeKey, simple: dmodeSimple, hint: dmodeHintShown, done: dmodeHintDone };')(store, broken);
}

test('full unless the person chose simple, per user', () => {
    const f = make({});
    assert.strictEqual(f.key(), 'pmportal.dmode.u7');
    assert.strictEqual(f.simple(), false, 'nobody loses what they see today');
    assert.strictEqual(make({ 'pmportal.dmode.u7': 'full' }).simple(), false);
    assert.strictEqual(make({ 'pmportal.dmode.u7': 'simple' }).simple(), true);
    assert.strictEqual(make({}, true).simple(), false, 'no storage: full');
});
test('the hint: once, until closed or the switch is used', () => {
    const store = {};
    const f = make(store);
    assert.strictEqual(f.hint(), true);
    f.done();
    assert.strictEqual(f.hint(), false, 'closed: not again');
    assert.strictEqual(make({ 'pmportal.dmode.u7': 'simple' }).hint(), false, 'chose already: no hint');
    assert.strictEqual(make({}, true).hint(), false, 'no storage: no hint');
});
test('the switch is in the strip under the title and applied on every draw', () => {
    assert.ok(/dmodeButton\(\) \+ '<\/div>'/.test(detail));
    assert.ok(/ppDbarFill\(i\);\s*dmodeApply\(\);/.test(detail));
});
test('simple hides the rest, keeps status, assignees and due date', () => {
    ['.bl-props .row:not([data-top="top"])', '#pp-d-childbox', '#pp-d-filebox', '#pp-d-relbox', '.pp-qf-unset'].forEach((s) => {
        assert.ok(style.indexOf('.bl-detail.pp-simple ' + s) > 0, s);
    });
    assert.ok(style.indexOf('pp-simple #pp-d-tlbox') < 0, 'the comments stay');
});
test('the words in ja, passed to the script', () => {
    ['dm_more', 'dm_less', 'dm_more_tip', 'dm_less_tip', 'dm_hint'].forEach((k) => {
        assert.ok(new RegExp("'" + k + "' => '").test(langJa), k);
        assert.ok(index.indexOf("'" + k + "'") > 0, k);
    });
    assert.ok(langJa.indexOf('// SIMPLE-1') > 0);
});

if (failed) {
    console.log('\n' + failed + ' failed');
    process.exit(1);
}
console.log('\nall passed');
