#!/usr/bin/env node
// ExmentPM: where each person's notice arrives (ISS02931): the icons on the
// chips of the comment dock, the line under them, and the toast after a post.
//
//   node project-management/tools/test-pm-notify-to.js
'use strict';

const fs = require('fs');
const path = require('path');
const assert = require('assert');

const PM = path.join(__dirname, '..', 'ExmentPM');
const SRC = path.join(PM, 'resources', 'js', 'src');
const detail = fs.readFileSync(path.join(SRC, 'issue-detail.js'), 'utf8');
const writes = fs.readFileSync(path.join(SRC, 'writes.js'), 'utf8');
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
function load(names, prelude) {
    return new Function((prelude || '') + '\n' + names.map((n) => functionSource(detail, n)).join('\n') +
        '\nreturn {' + names.join(',') + '};')();
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

const L = "var L = { nt_bell: 'ベル', nt_mail: 'メール', nt_by_teams: 'Teams', nt_sent_mail: 'メールで知らせた: %s', nt_sent_teams: 'Teams で知らせた: %s' };" +
    'function fmt(t) { var a = [].slice.call(arguments, 1), i = 0; return String(t).replace(/%s/g, function () { return a[i++]; }); }';
const f = load(['ntRoute', 'ntWords', 'ntSentText', 'ntLine'], L +
    "function esc(s) { return String(s); } function user(u) { return { n: { 1: '山田', 2: '佐藤', 3: '鈴木' }[u] || '?' }; }");

test('each person: their own choice', () => {
    const B = { mail: false, teams: false }, M = { mail: true, teams: false }, T = { mail: false, teams: true }, MT = { mail: true, teams: true };
    assert.deepStrictEqual(f.ntRoute(B, false, false, true), B);
    assert.deepStrictEqual(f.ntRoute(M, false, false, true), M);
    assert.deepStrictEqual(f.ntRoute(T, false, false, true), T);
    assert.deepStrictEqual(f.ntRoute(MT, false, false, true), MT);
});
test('the ticks of the writer add mail or Teams for everyone', () => {
    assert.deepStrictEqual(f.ntRoute({ mail: false, teams: false }, true, false, true), { mail: true, teams: false });
    assert.deepStrictEqual(f.ntRoute({ mail: false, teams: false }, false, true, true), { mail: false, teams: true });
});
test('no Teams where Exment cannot send it', () => {
    assert.deepStrictEqual(f.ntRoute({ mail: true, teams: true }, false, true, false), { mail: true, teams: false });
});
test('no choice in the payload (an older server): nothing shown', () => {
    assert.strictEqual(f.ntRoute(undefined, true, true, true), null);
    assert.strictEqual(f.ntRoute('both', false, false, true), null);
});
test('the words of the title', () => {
    assert.strictEqual(f.ntWords({ mail: true, teams: true }), 'ベル・メール・Teams');
    assert.strictEqual(f.ntWords({ mail: false, teams: false }), 'ベル');
});
test('the toast after a post', () => {
    assert.strictEqual(f.ntSentText({ mail: ['山田', '佐藤'], teams: [] }), 'メールで知らせた: 山田、佐藤');
    assert.strictEqual(f.ntSentText({ mail: ['山田'], teams: ['鈴木'] }), 'メールで知らせた: 山田 ／ Teams で知らせた: 鈴木');
    assert.strictEqual(f.ntSentText(undefined), '');
    assert.strictEqual(f.ntSentText({ mail: [], teams: [] }), '');
});
test('the dock sends the Teams tick and unticks it after', () => {
    assert.ok(/data\.notify_teams = 1/.test(writes));
    assert.ok(/teamsNow\.checked = false/.test(writes));
    assert.ok(/ntSentText\(res\.notice_to\)/.test(writes));
    assert.ok(/ntRoutes\(\);/.test(functionSource(detail, 'ppNotifyCount')), 'the icons follow every chip toggle');
});
test('the view: the Teams tick only where Teams can be sent, the line under the chips', () => {
    assert.ok(/@if \(!empty\(\$payload\['notify_teams'\]\)\)\s*<label class="pp-notify-mail pp-notify-teams"/.test(index));
    assert.ok(/id="pp-notify-route" hidden/.test(index));
    ['nt_teams', 'nt_tip', 'nt_line_mail', 'nt_line_teams', 'nt_sent_mail', 'nt_sent_teams', 'nt_others'].forEach((k) => {
        assert.ok(new RegExp("'" + k + "' => '").test(langJa), k + ' in ja');
        assert.ok(index.indexOf("'" + k + "'") > 0, k + ' passed to the script');
    });
    assert.ok(langJa.indexOf('// NOTIFY-TO') > 0);
});

test('under a comment: who it was also sent to', () => {
    assert.strictEqual(f.ntLine({}), '');
    assert.strictEqual(f.ntLine({ nt: { mail: [], teams: [] } }), '');
    const line = f.ntLine({ nt: { mail: [1, 2], teams: [3] } });
    assert.ok(line.indexOf('メールで知らせた: 山田、佐藤 ／ Teams で知らせた: 鈴木') > 0, line);
    assert.ok(/rvBar\(e\) \+ ntLine\(e\)/.test(detail));
});

test('the chips are read again when the dock opens, at most once a minute', () => {
    const g = new Function(functionSource(detail, 'ntRefreshDue') + 'return ntRefreshDue;')();
    assert.ok(g(60000, 0, false));
    assert.ok(!g(59999, 0, false));
    assert.ok(!g(120000, 0, true), 'not while a read is on its way');
    assert.ok(/if \(on\) \{ ntRefresh\(\); \}/.test(functionSource(detail, 'ppDockOpen')));
    assert.ok(/'\/notify-channels'/.test(functionSource(detail, 'ntRefresh')));
});

if (failed) {
    console.log('\n' + failed + ' failed');
    process.exit(1);
}
console.log('\nall passed');
