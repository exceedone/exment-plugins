#!/usr/bin/env node
// ExmentPM: the command palette (Ctrl+K / ⌘K, UX L2): which actions a typed
// text keeps, the issues it finds, the key, and that every action is wired.
//
//   node project-management/tools/test-pm-palette.js
'use strict';

const fs = require('fs');
const path = require('path');
const assert = require('assert');

const PM = path.join(__dirname, '..', 'ExmentPM');
const SRC = path.join(PM, 'resources', 'js', 'src');
const pal = fs.readFileSync(path.join(SRC, 'palette.js'), 'utf8');
const ta = fs.readFileSync(path.join(SRC, 'typeahead.js'), 'utf8');
const boot = fs.readFileSync(path.join(SRC, 'boot.js'), 'utf8');
const index = fs.readFileSync(path.join(PM, 'resources', 'views', 'index.blade.php'), 'utf8');
const langJa = fs.readFileSync(path.join(PM, 'resources', 'lang', 'ja', 'pmportal.php'), 'utf8');
const build = fs.readFileSync(path.join(__dirname, 'build-pm-script.js'), 'utf8');

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

const f = new Function(
    "var TA_MAX = 12; var PAL_ISSUES = 8; var L = { pal_issues: '課題へ行く' };" +
    'var D = { issues: [{ id: 1, key: "WEB-1", subject: "トップページのデザイン" }, { id: 2, key: "WEB-2", subject: "お問い合わせフォーム" }, { id: 3, key: "OPS-9", subject: "デザインの確認" }] };' +
    'var hashes = []; var location = { set hash(v) { hashes.push(v); } };' +
    'function issueHash(id) { return "issue-" + id; }\n' +
    functionSource(ta, 'taMatch') + '\n' + functionSource(pal, 'palMatch') + '\n' + functionSource(pal, 'palIssues') + '\n' + functionSource(pal, 'palKey') +
    '\nreturn { palMatch: palMatch, palIssues: palIssues, palKey: palKey, hashes: hashes };'
)();

const CMDS = [
    { g: '移動', l: 'ボード', k: 'b' },
    { g: '移動', l: '課題', k: 'f' },
    { g: '課題の画面', l: '自分を担当者にする', k: '' },
    { g: '課題の画面', l: '状態を変える', k: 's' },
];

test('nothing typed: every action', () => {
    assert.strictEqual(f.palMatch(CMDS, '').length, 4);
    assert.strictEqual(f.palMatch(CMDS, '   ').length, 4);
});
test('each word typed is found somewhere, in any order', () => {
    assert.deepStrictEqual(f.palMatch(CMDS, '担当').map((c) => c.l), ['自分を担当者にする']);
    assert.deepStrictEqual(f.palMatch(CMDS, '画面 状態').map((c) => c.l), ['状態を変える']);
    assert.deepStrictEqual(f.palMatch(CMDS, 'B').map((c) => c.l), ['ボード'], 'by the key, any case');
    assert.strictEqual(f.palMatch(CMDS, 'xyz').length, 0);
});
test('issues by key or subject, the # typeahead match, at most 8', () => {
    assert.deepStrictEqual(f.palIssues('web-2').map((c) => c.key), ['WEB-2']);
    assert.deepStrictEqual(f.palIssues('デザイン').map((c) => c.key), ['WEB-1', 'OPS-9']);
    assert.strictEqual(f.palIssues('').length, 0, 'nothing typed: no issues');
    f.palIssues('OPS')[0].run();
    assert.deepStrictEqual(f.hashes, ['issue-3'], 'running one opens it');
});
test('the key: Ctrl+K or ⌘K, not with Shift or Alt', () => {
    assert.ok(f.palKey({ ctrlKey: true, key: 'k' }));
    assert.ok(f.palKey({ metaKey: true, key: 'K' }));
    assert.ok(!f.palKey({ ctrlKey: true, shiftKey: true, key: 'K' }));
    assert.ok(!f.palKey({ ctrlKey: true, altKey: true, key: 'k' }));
    assert.ok(!f.palKey({ key: 'k' }));
});
test('every action presses what is on the screen, and the forms keep the key', () => {
    const cmds = functionSource(pal, 'palCommands');
    ['.bl-tab[data-scr]', '.pp-top-addmain', 'pp-jump', '.pp-next-status', 'ppStMenuFromKey', 'palAssignMe', '.pp-mlink', 'ppDockOpen', '.pp-watch', '.pp-fav', '.pp-linkcopy', '.bl-inav', 'keyHelp'].forEach((s) => {
        assert.ok(cmds.indexOf(s) >= 0, s);
    });
    assert.ok(/tab\.offsetParent === null/.test(cmds), 'a hidden tab is not offered');
    assert.ok(/det\.editable/.test(functionSource(pal, 'palAssignMe')), 'take it: only where the reader may edit');
    assert.ok(/qfApply\(issueId, \{ as: /.test(functionSource(pal, 'palAssignMe')), 'take it: the same save as the info box, with its undo');
    assert.ok(/FM\.el && !FM\.el\.hidden\) \|\| \(NED\.el && !NED\.el\.hidden/.test(pal));
});
test('built in, listed in the keys, the words in ja', () => {
    assert.ok(/'palette\.js',/.test(build) && build.indexOf("'palette.js'") < build.indexOf("'boot.js'"));
    assert.ok(/\['Ctrl\+K', 'kh_palette'\]/.test(boot));
    ['pal_title', 'pal_ph', 'pal_none', 'pal_issues', 'pal_assign_me', 'kh_palette'].forEach((k) => {
        assert.ok(new RegExp("'" + k + "' => '").test(langJa), k + ' in ja');
        assert.ok(index.indexOf("'" + k + "'") > 0, k + ' passed to the script');
    });
    assert.ok(langJa.indexOf('// L2') > 0);
});

if (failed) {
    console.log('\n' + failed + ' failed');
    process.exit(1);
}
console.log('\nall passed');
