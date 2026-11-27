#!/usr/bin/env node
// ExmentPM: the actions of an issue in one place (the button map): the head
// keeps the next status and 編集 as buttons; the rest is in the ⋯ menu.
//
//   node project-management/tools/test-pm-issue-actions.js
'use strict';

const fs = require('fs');
const path = require('path');
const assert = require('assert');

const PM = path.join(__dirname, '..', 'ExmentPM');
const detail = fs.readFileSync(path.join(PM, 'resources', 'js', 'src', 'issue-detail.js'), 'utf8');
const style = fs.readFileSync(path.join(PM, 'resources', 'views', 'style.blade.php'), 'utf8');
const index = fs.readFileSync(path.join(PM, 'resources', 'views', 'index.blade.php'), 'utf8');
const langJa = fs.readFileSync(path.join(PM, 'resources', 'lang', 'ja', 'pmportal.php'), 'utf8');

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

const start = detail.indexOf("var head = ");
const head = detail.slice(start, detail.indexOf("'<div class=\"pp-move\" hidden></div>'", start));
const menuAt = head.indexOf('<span class="m" hidden>');

test('the head: next status and edit stay buttons, before the menu', () => {
    assert.ok(menuAt > 0);
    assert.ok(head.indexOf('pp-next-status') < menuAt);
    assert.ok(head.indexOf('bl-btn-sub pp-mlink') < menuAt);
});
test('the ⋯ menu: child, 注目, お気に入り, ウォッチ, then the rest, delete last', () => {
    const order = ['pp-addchild', 'starDetailBtn', 'pp-fav', 'pp-watch', 'class="sep"', 'pp-dquote', 'pp-issuecopy', 'pp-linkcopy', 'pp-mailbtn', 'pp-issuemove', 'pp-issuedel'];
    let last = menuAt;
    order.forEach((c) => {
        const at = head.indexOf(c, last);
        assert.ok(at > last, c + ' after the one before it, inside the menu');
        last = at;
    });
    assert.ok(!/bl-btn-sub pp-(fav|watch|addchild)/.test(head), 'no button look inside the menu');
    assert.ok(/starDetailBtn\([^)]*\)\.replace\('class="bl-btn-sub ', 'class="'\)/.test(head));
});
test('what is on shows on the ⋯ button', () => {
    assert.ok(/pp-act-on/.test(head));
    ['.pp-star.on', '.pp-fav.on', '.pp-watch.on'].forEach((s) => assert.ok(style.indexOf(':has(.m ' + s + ')') > 0, s));
});
test('every button has a title, the words in ja', () => {
    ['act_edit_tip', 'act_more_tip', 'act_watch_tip'].forEach((k) => {
        assert.ok(new RegExp("'" + k + "' => '").test(langJa), k);
        assert.ok(index.indexOf("'" + k + "'") > 0, k + ' passed to the script');
        assert.ok(head.indexOf('L.' + k) > 0, k + ' used');
    });
    assert.ok(langJa.indexOf('// ACT-1') > 0);
});

test('the on / off items keep the menu open; 注目 says its word on a phone', () => {
    const events = fs.readFileSync(path.join(PM, 'resources', 'js', 'src', 'events.js'), 'utf8');
    assert.ok(/moreKeep = moreBtn \|\| ev\.target\.closest\('\.pp-more \.pp-issuedel, \.pp-more \.pp-star, \.pp-more \.pp-fav, \.pp-more \.pp-watch'\)/.test(events));
    assert.ok(style.indexOf('.pmportal .pp-more > .m .pp-star-d .lb{display:inline !important;}') > 0);
});

if (failed) {
    console.log('\n' + failed + ' failed');
    process.exit(1);
}
console.log('\nall passed');
