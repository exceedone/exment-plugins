#!/usr/bin/env node
// ExmentPM: the screens made easier to use (the UX proposal, S and M items).
//
//   node project-management/tools/test-pm-ux.js
//
// Each block names the items it covers. The functions are read out of
// resources/js/src/ and run with small stand-ins for what they use; the
// stylesheet and the views are read as text.
'use strict';

const fs = require('fs');
const path = require('path');
const assert = require('assert');

const PM = path.join(__dirname, '..', 'ExmentPM');
const SRC = path.join(PM, 'resources', 'js', 'src');
const read = (name) => fs.readFileSync(path.join(SRC, name), 'utf8');
const view = (name) => fs.readFileSync(path.join(PM, 'resources', 'views', name), 'utf8');
const langJa = fs.readFileSync(path.join(PM, 'resources', 'lang', 'ja', 'pmportal.php'), 'utf8');

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
// Several functions of one source, run together over the given stand-ins.
function load(source, names, prelude) {
    const body = (prelude || '') + '\n' + names.map((n) => functionSource(source, n)).join('\n') +
        '\nreturn {' + names.join(',') + '};';
    return new Function(body)();
}
// A ja key's value, as the lang file has it.
function ja(key) {
    const m = new RegExp("'" + key + "' => '([^']*)'").exec(langJa);
    return m ? m[1] : undefined;
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

const detail = read('issue-detail.js');
const style = view('style.blade.php');
const index = view('index.blade.php');

/* ---------------------------------------------- UX-C: the issue screen -- */
const STUBS = `
    var D = { issue_cols: { assignees: 'as_col', priority: 'pr_col', milestone: 'ms_col', category: 'cat_col', start: 'sd_col',
        due: 'dd_col', due_time: 'dt_col', progress: 'pg_col', estimate: 'eh_col' }, issue_extra_on: { reviewers: 1 },
        issue_extra_cols: { reviewers: 'rv_col' }, project: { name: 'Web <b>', manager: 1 }, urls: { projects: '/admin/plugins/exment_pm' },
        today: '2026-11-30' };
    var L = { tab_issues: '課題', bc_root: 'プロジェクト管理', bc_label: 'here', lbl_reviewers: 'レビュアー', lbl_difficulty: '難易度' };
    var QF_ROLES = [['sa', 'sub_assignees', 'lbl_sub_assignees'], ['mg', 'managers', 'lbl_managers'], ['rv', 'reviewers', 'lbl_reviewers']];
    function own(o, k) { return o && Object.prototype.hasOwnProperty.call(o, k) ? o[k] : undefined; }
    function esc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }
    function qeCol(key) { return own(D.issue_cols, key) || (xOn(key) ? String(own(D.issue_extra_cols, key) || '') : '') || ''; }
    function xOn(role) { return !!(D.issue_extra_on && own(D.issue_extra_on, role)); }
    function qfPeople(ids) { return (ids || []).join(',') || '—'; }
`;
const ux = load(detail, ['qfField', 'qfKeys', 'qfValue', 'qfDayFrom', 'propsShown', 'crumbs', 'qfRoleProps'], STUBS);

test('S19: the trail names the portal, the project and its issues, each a link', () => {
    const html = ux.crumbs({ key: 'WEB-1' });
    assert.ok(html.indexOf('href="/admin/plugins/exment_pm">プロジェクト管理</a>') > 0);
    assert.ok(html.indexOf('href="#home">Web &lt;b&gt;</a>') > 0, 'the project name is escaped');
    assert.ok(html.indexOf('href="#issues">課題</a>') > 0);
    assert.ok(/aria-current="page">WEB-1</.test(html));
    assert.ok(functionSource(detail, 'buildDetail').indexOf('crumbs(i)') > 0, 'drawn above the issue');
    assert.ok(functionSource(detail, 'buildDetail').indexOf('bl-dback') < 0, 'no green back button on the issue');
    assert.ok(index.indexOf('bl-dback-foot') < 0, 'and none at its foot');
});

test('M1: only an editable issue and a column the table has get an editor', () => {
    const res = { editable: true };
    assert.strictEqual(ux.qfField('as', res), 'as');
    assert.strictEqual(ux.qfField('dd', res), 'dd');
    assert.strictEqual(ux.qfField('time', res), 'time');
    assert.strictEqual(ux.qfField('rv', res), 'rv', 'a role that is on');
    assert.strictEqual(ux.qfField('mg', res), '', 'a role that is off');
    assert.strictEqual(ux.qfField('st', res), '', 'the status goes through the comment');
    assert.strictEqual(ux.qfField('by', res), '');
    assert.strictEqual(ux.qfField('as', { editable: false }), '', 'not for somebody who may not edit');
    assert.deepStrictEqual(ux.qfKeys('dd'), ['dd', 'dt'], 'the due time with its date');
    assert.deepStrictEqual(ux.qfKeys('time'), ['eh', 'ah']);
    assert.deepStrictEqual(ux.qfKeys('pr'), ['pr']);
});

test('M1: the values as the widgets read them back', () => {
    assert.deepStrictEqual(ux.qfValue({ as: [3, 5] }, 'as'), ['3', '5']);
    assert.deepStrictEqual(ux.qfValue({}, 'cat'), []);
    assert.strictEqual(ux.qfValue({ pr: 2 }, 'pr'), '2');
    assert.strictEqual(ux.qfValue({ dd: null }, 'dd'), '');
});

test('M1: today, tomorrow and next week count from the server\'s today', () => {
    assert.strictEqual(ux.qfDayFrom(0), '2026-11-30');
    assert.strictEqual(ux.qfDayFrom(1), '2026-12-01');
    assert.strictEqual(ux.qfDayFrom(7), '2026-12-07');
});

test('S1: the info box keeps the top rows, drops the unset ones, lists what may be set', () => {
    const props = [
        ['st', 'S', '<span>open</span>', 'top'], ['as', 'A', '—', 'top'], ['dd', 'D', '—', 'top'], ['pr', 'P', '—', 'pr'],
        ['rv', 'R', '—', 'role'],
        ['ms', 'M', '—'], ['cat', 'C', 'Design'], ['sd', 'SD', '—'], ['rc', 'RC', '—'],
        ['time', 'T', '— h / — h'], ['pg', 'PG', '—'], ['by', 'BY', ''], ['notify', 'N', 'x'],
    ];
    const out = ux.propsShown(props, { pg: 0 }, { editable: true });
    const keys = out.rows.map((p) => p[0]);
    assert.deepStrictEqual(keys, ['st', 'as', 'dd', 'pr', 'rv', 'cat', 'time', 'notify']);
    assert.deepStrictEqual(out.unset.map((p) => p[0]), ['ms', 'sd', 'pg'], 'unset but editable: offered to set');
    assert.strictEqual(out.rest, 4, 'cat, time, notify and the list of unset ones');
    assert.strictEqual(out.phone, 2, 'the priority and the role');
    const ro = ux.propsShown(props, { pg: 0 }, { editable: false });
    assert.deepStrictEqual(ro.unset, [], 'nothing to set for a reader');
    assert.deepStrictEqual(ux.qfRoleProps({ rv: [4] }).map((p) => p[0] + ':' + p[3]), ['rv:role']);
});

test('S1: the info column from 1200px, the strip without its fields beside it', () => {
    assert.ok(/@media \(min-width:1200px\)\{\s*\.pmportal \.bl-detail\.pp-two\{display:grid/.test(style), 'two columns from 1200px');
    assert.ok(style.indexOf('@media (min-width:1800px){\n  .pmportal .bl-detail.pp-two') < 0, 'not from 1800px any more');
    assert.ok(style.indexOf('.pmportal .bl-detail.pp-two .bl-dsum [data-sum]{display:none;}') > 0);
    const b = functionSource(detail, 'buildDetail');
    ['st', 'pr', 'as', 'dd'].forEach((k) => assert.ok(b.indexOf('data-sum="' + k + '"') > 0, 'strip item ' + k));
    assert.ok(functionSource(detail, 'qfSummary').indexOf('data-sum="role"') > 0);
});

test('S2: a phone reads the comments right after the text, the side boxes folded', () => {
    assert.ok(/\.bl-detail > #pp-d-tlbox\{order:2;\}/.test(style));
    assert.ok(/\.bl-detail > #pp-d-side\{order:3;\}/.test(style));
    assert.ok(/stored === null && side && PHONE && PHONE\.matches/.test(detail), 'folded unless the reader opened one');
    assert.ok(index.indexOf('id="pp-d-propsum"') > 0 && index.indexOf('id="pp-d-relsum"') > 0, 'counts on the heads');
});

test('S17: the dock keeps the status open and folds the other fields', () => {
    const rq = functionSource(detail, 'renderQuickEdit');
    assert.ok(/getElementById\('pp-cmt-st'\)/.test(rq), 'the status row lives beside the comment');
    assert.ok(/if \(!same\) \{ qeFold\(false\); \}/.test(rq), 'closed for each new issue');
    assert.ok(/qeFold\(true\)/.test(rq), 'open again when carried work is in it');
    assert.ok(/pp-qe-due/.test(rq) && /pp-qe-dt/.test(rq), 'the due time beside the due date');
    assert.ok(index.indexOf('id="pp-cmt-st"') > 0 && index.indexOf('class="bl-boxhead pp-qe-toggle"') > 0);
    assert.strictEqual(ja('dock_edit'), 'ほかの項目も変える');
    assert.strictEqual(ja('box_props'), '課題の情報');
});

test('M1: a value saves through qeSaveFields, with an undo in the toast', () => {
    assert.ok(/qfApply\(issueId, changed, undo, /.test(functionSource(detail, 'qfOpen')));
    const apply = functionSource(detail, 'qfApply');
    assert.ok(/qeSaveFields\(issueId, changed, version\)/.test(apply));
    assert.ok(/label: L\.qf_undo/.test(apply) && /qfApply\(issueId, undo, null/.test(apply), 'undo writes the old values back');
    const toast = new Function('var toastTimer = null;' + functionSource(read('review-reactions.js'), 'toast') + '; return toast;')();
    const made = [];
    const el = { style: {}, children: [], appendChild(c) { this.children.push(c); } };
    global.document = {
        getElementById: () => el,
        createElement: () => { const b = { listeners: {}, addEventListener(t, f) { this.listeners[t] = f; } }; made.push(b); return b; },
        createTextNode: (t) => ({ text: t }),
        body: { appendChild() {} },
    };
    let ran = 0;
    global.setTimeout = () => 1;
    global.clearTimeout = () => {};
    toast('saved', false, { label: 'undo', run: () => { ran++; } });
    assert.strictEqual(made.length, 1);
    assert.strictEqual(made[0].textContent, 'undo');
    made[0].listeners.click();
    assert.strictEqual(ran, 1);
    assert.strictEqual(el.style.display, 'none', 'pressed: the toast goes');
    delete global.document;
});

test('UX-C: every new ja key is defined', () => {
    ['bc_label', 'bc_root', 'qf_click', 'qf_unset', 'qf_undo', 'qf_undone', 'qf_today', 'qf_tomorrow', 'qf_next_week', 'qf_assign_me']
        .forEach((k) => {
            assert.ok(ja(k), k);
            assert.ok(index.indexOf("'" + k + "'") > 0, k + ' reaches the script');
        });
    assert.ok(langJa.indexOf('// UX-C') > 0);
});

if (failed) {
    console.log('\n' + failed + ' failed');
    process.exit(1);
}
console.log('\nall passed');
