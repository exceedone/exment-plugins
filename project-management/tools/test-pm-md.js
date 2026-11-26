#!/usr/bin/env node
// ExmentPM: the Markdown help of the editors (MD-1): the cheat sheet puts its
// Markdown at the caret, 並べて表示 is each person's setting and only on a
// wide screen, and the comment box gets 書く｜プレビュー.
//
//   node project-management/tools/test-pm-md.js
'use strict';

const fs = require('fs');
const path = require('path');
const assert = require('assert');

const PM = path.join(__dirname, '..', 'ExmentPM');
const src = fs.readFileSync(path.join(PM, 'resources', 'js', 'src', 'md-help.js'), 'utf8');
const index = fs.readFileSync(path.join(PM, 'resources', 'views', 'index.blade.php'), 'utf8');
const langJa = fs.readFileSync(path.join(PM, 'resources', 'lang', 'ja', 'pmportal.php'), 'utf8');
const ctl = fs.readFileSync(path.join(PM, 'PmPortalController.php'), 'utf8');
const cfg = JSON.parse(fs.readFileSync(path.join(PM, 'config.json'), 'utf8'));

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

const itemsSrc = src.slice(src.indexOf('var MD_ITEMS'), src.indexOf('];', src.indexOf('var MD_ITEMS')) + 2);
const f = new Function('win',
    "var L = { md_table_ins: '| a | b |\\\\n| --- | --- |', md_h: 'H', md_b: 'B', md_img: 'I', md_img_how: 'how' }; var D = { md_split: true }; var window = win;" +
    'function esc(s) { return String(s); } function Event(t, o) { this.type = t; }' +
    'var MD_SPLIT_MIN = 900;' + itemsSrc +
    functionSource(src, 'mdInsert') + functionSource(src, 'mdSheetHtml') + functionSource(src, 'mdSplitOn') +
    'return { insert: mdInsert, sheet: mdSheetHtml, split: mdSplitOn, items: MD_ITEMS, D: D };');

function area(value, from, to) {
    const ta = {
        value, selectionStart: from, selectionEnd: to, readOnly: false, disabled: false, events: [],
        focus() {},
        setRangeText(text, a, b) { this.value = this.value.slice(0, a) + text + this.value.slice(b); this.selectionStart = this.selectionEnd = a + text.length; },
        dispatchEvent(e) { this.events.push(e.type); }
    };
    return ta;
}

test('a block goes to the start of the line, an inline one around the selection', () => {
    const m = f({ innerWidth: 1200 });
    const byKey = (k) => m.items.find((it) => it[0] === k);
    const a = area('abc\ndef', 5, 5);
    m.insert(a, byKey('md_h'));
    assert.strictEqual(a.value, 'abc\n## def');
    assert.deepStrictEqual(a.events, ['input'], 'drafts and previews hear of it');
    const b = area('make bold', 5, 9);
    m.insert(b, byKey('md_b'));
    assert.strictEqual(b.value, 'make **bold**');
    const c = area('', 0, 0);
    m.insert(c, byKey('md_table'));
    assert.strictEqual(c.value, '| a | b |\n| --- | --- |\n', 'the table from the lang line');
});
test('the cheat sheet: each line a button, the image line a note', () => {
    const html = f({ innerWidth: 1200 }).sheet();
    assert.ok(/data-md="0"/.test(html) && /class="pp-md-row note"/.test(html));
    ['md_h', 'md_b', 'md_ul', 'md_ol', 'md_q', 'md_code', 'md_link', 'md_table', 'md_task', 'md_img'].forEach((k) => {
        assert.ok(itemsSrc.indexOf("'" + k + "'") > 0, k);
        assert.ok(new RegExp("'" + k + "' => '").test(langJa), k + ' in ja');
        assert.ok(index.indexOf("'" + k + "'") > 0, k + ' passed to the script');
    });
});
test('並べて表示: the person\'s setting, and only from 900px', () => {
    assert.strictEqual(f({ innerWidth: 1200 }).split(), true);
    assert.strictEqual(f({ innerWidth: 899 }).split(), false);
    assert.ok(/'md_split' => boolval\(\\Admin::user\(\)->getSettingValue\('pmportal\.md_split'\)\)/.test(ctl));
    assert.ok(cfg.route.some((r) => r.uri === 'md-split' && r.method === 'post' && r.function === 'postMdSplit'));
    assert.ok(/setTimeout\(draw, 200\)/.test(functionSource(src, 'ppMdAttach')), 'the preview follows the typing, 200ms after');
});
test('the real renderers, not wrapped', () => {
    const attach = functionSource(src, 'ppMdAttach');
    assert.ok(/kind === 'comment' \? renderCommentText : renderRichText/.test(attach));
    assert.ok(!/renderCommentText\s*=/.test(src), 'renderCommentText is not replaced');
});
test('the three editors, and 書く｜プレビュー for the comment box', () => {
    assert.ok(/ta\.classList\.contains\('pp-ne-in'\) && ta\.closest\('\.pp-ne-desc-row'\)/.test(src), 'the issue body and a wiki page');
    assert.ok(/getElementById\('pp-cmt-text'\)/.test(functionSource(src, 'mdCommentBox')));
    assert.ok(/<span class="pp-md-tabs"><button type="button" class="on" id="pp-cmt-writebtn">/.test(index));
    assert.ok(/'md_ph' => 'Markdown で書けます/.test(langJa) && langJa.indexOf('// MD-1') > 0);
    assert.ok(/'ct_bold' => '太字 \*\*…\*\*'/.test(langJa), 'the toolbar says the Markdown');
});

if (failed) {
    console.log('\n' + failed + ' failed');
    process.exit(1);
}
console.log('\nall passed');
