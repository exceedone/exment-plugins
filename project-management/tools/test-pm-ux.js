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

test('a cold landing on an unread issue marks the newest comment of somebody else', () => {
    const f = load(detail, ['landingComment'], 'var D = { me: 1 }; var currentIssue = 7; var ppLandOn = "7";');
    const res = { timeline: [{ tp: 'cmt', cid: 3, by: 2 }, { tp: 'cmt', cid: 9, by: 1 }, { tp: 'chg', by: 2 }, { tp: 'cmt', cid: 5, by: 4 }] };
    assert.strictEqual(f.landingComment(res), 5, 'the newest not written by the reader');
    assert.strictEqual(f.landingComment(res), null, 'asked once');
    const g = load(detail, ['landingComment'], 'var D = { me: 1 }; var currentIssue = 7; var ppLandOn = "7";');
    assert.strictEqual(g.landingComment({ timeline: null }), null, 'not while the timeline is still loading');
    assert.strictEqual(g.landingComment(res), 5, '...which keeps it for the full answer');
    assert.ok(/bootRow && bootRow\.unread/.test(detail), 'only for an issue that was unread');
    assert.ok(/!\/\[\?&\]c=\/\.test\(PP_BOOT_HASH\)/.test(detail), 'not when a comment is named');
});

test('UX-C: every new ja key is defined', () => {
    ['bc_label', 'bc_root', 'qf_click', 'qf_unset', 'qf_undo', 'qf_undone', 'qf_today', 'qf_tomorrow', 'qf_next_week', 'qf_assign_me']
        .forEach((k) => {
            assert.ok(ja(k), k);
            assert.ok(index.indexOf("'" + k + "'") > 0, k + ' reaches the script');
        });
    assert.ok(langJa.indexOf('// UX-C') > 0);
});

/* ---------------------------------------------- UX-D: navigation ------- */
const topbar = view('_topbar.blade.php');
const settingsView = view('_settings.blade.php');

test('S3: the active tab is scrolled into the middle of a sideways row', () => {
    const row = { scrollWidth: 600, clientWidth: 300, scrollLeft: 0, querySelector: () => tab };
    const tab = { offsetLeft: 420, offsetWidth: 80 };
    const f = load(read('routing.js'), ['tabIntoView'], 'var document = { getElementById: function () { return row; } };'.replace('row', 'globalThis.__row'));
    globalThis.__row = row;
    f.tabIntoView();
    assert.strictEqual(row.scrollLeft, 300, 'clamped to the end of the row');
    tab.offsetLeft = 200;
    f.tabIntoView();
    assert.strictEqual(row.scrollLeft, 90, 'centred');
    row.scrollWidth = 300; row.scrollLeft = 5;
    f.tabIntoView();
    assert.strictEqual(row.scrollLeft, 5, 'a row that fits is left alone');
    assert.ok(/flex-wrap:nowrap;overflow-x:auto/.test(style), 'one row that scrolls on a phone');
    delete globalThis.__row;
});

test('S5: one 課題の追加, in the top bar; a adds to this project', () => {
    assert.ok(index.indexOf('bl-addissue') < 0, 'no second button in the tab row');
    assert.ok(/class="pp-top-addmain" href="#new"/.test(topbar), 'inside a project it opens the form at once');
    assert.ok(/\$topbarCurAdd \? ' split' : ''/.test(topbar), 'with ▾ for the others');
    assert.ok(/querySelector\('#pp-top \.pp-top-addmain'\)/.test(read('boot.js')), 'key a presses it');
});

test('S7, S8: 通知の受け取り方 with its own icon, 全体の設定, 使うタブ second', () => {
    assert.ok(/<i class="fa fa-sliders"><\/i><span>\{\{ PortalDefinition::trans\('teams\.menu'\)/.test(topbar));
    assert.ok(/'menu' => '通知の受け取り方'/.test(langJa));
    assert.ok(/trans\('global_settings'\)/.test(topbar) && ja('global_settings') === '全体の設定');
    assert.ok(topbar.indexOf('>設定<') < 0 && topbar.indexOf('>すべて開く<') < 0, 'no Japanese written in the view');
    assert.strictEqual(ja('tab_features'), '使うタブ');
    const order = settingsView.slice(settingsView.indexOf("$setSections = [['basic'"), settingsView.indexOf("$setSections[] = ['people'"));
    assert.ok(order.indexOf("['features'") > 0, '使うタブ before 参加ユーザー');
    assert.ok(/data-scr="gantt"><i class="fa fa-align-left">/.test(index), 'the gantt tab no longer shares the sliders icon');
});

test('M2: a phone keeps the bar on one line; it stays one line from 1200px', () => {
    assert.ok(/class="pp-top-burger"/.test(topbar) && /class="pp-top-stgl"/.test(topbar));
    assert.ok(/\.pp-top \.pp-top-pjdd\{order:1;flex:1 1 0;min-width:0;\}/.test(topbar), 'the project shrinks before the row wraps');
    assert.ok(/@media \(min-width:1200px\)\{\s*\.pp-top\{flex-wrap:nowrap;\}/.test(topbar));
    assert.ok(/pmBell\.hidden = !\(ex && narrow && ex\.offsetParent === null\)/.test(topbar), 'the copy of the bell only where Exment hides its own');
});

test('S4, M3: the project head on one line, the name opens the project list', () => {
    const home = read('home.js');
    assert.ok(/class="bl-projname pp-pjswitch"/.test(home));
    assert.ok(/window\.ppTopOpenProjects\(\)/.test(home) && /window\.ppTopOpenProjects = function/.test(topbar));
    assert.ok(/class="bl-projmore"/.test(home));
    assert.ok(/\.bl-projhead:not\(\.info-open\) \.bl-projmeta/.test(style));
    ['global_settings', 'top_menu', 'top_bell', 'add_other', 'pj_open_all', 'pj_close_all', 'pj_switch', 'proj_info'].forEach((k) => assert.ok(ja(k), k));
    assert.ok(langJa.indexOf('// UX-D') > 0);
});

/* ---------------------------------------------- UX-E: list, board ------ */
const listSrc = read('list.js');
const embeds = read('embeds.js');
const listView = view('list.blade.php');

test('S11, M7: a pencil in each editable cell; one press opens the double-click editor', () => {
    const f = load(listSrc, ['edPen'], 'var BULK_ON = true; var L = { ed_pen: "x" }; function esc(s) { return String(s); }');
    assert.ok(/class="pp-ed-pen"/.test(f.edPen()));
    const g = load(listSrc, ['edPen'], 'var BULK_ON = false; var L = {}; function esc(s) { return String(s); }');
    assert.strictEqual(g.edPen(), '', 'none for a reader');
    ['ty', 'st', 'pr', 'ms', 'cat', 'sd', 'dd'].forEach((k) => assert.ok(listSrc.split(String.fromCharCode(10)).some((l) => l.indexOf("edAttr('" + k + "')") >= 0 && l.indexOf('edPen()') > 0), k));
    assert.ok(/new MouseEvent\('dblclick'/.test(listSrc), 'the pencil hands over to the existing editor');
    assert.ok(/id="pp-ed-hint" hidden/.test(index) && /pmportal\.edhint/.test(listSrc), 'the hint is shown once');
});

test('S18: 自分が担当, with the way to every project', () => {
    assert.strictEqual(ja('f_mine'), '自分が担当');
    assert.ok(/'card_mine' => '自分が担当 :count'/.test(langJa));
    const f = load(listSrc, ['mineAllLink'], 'var FILTER = { q: "mine" }; var D = { urls: { projects: "/pm" } }; var L = { mine_all: "all" }; function esc(s) { return String(s); }');
    assert.ok(/href="\/pm"/.test(f.mineAllLink()));
    const g = load(listSrc, ['mineAllLink'], 'var FILTER = { q: "" }; var D = { urls: { projects: "/pm" } }; var L = {}; function esc(s) { return String(s); }');
    assert.strictEqual(g.mineAllLink(), '', 'only while 自分が担当 is on');
});

test('S9, M4: board aids - sideways buttons, the quick add on hover, a phone column at a time', () => {
    assert.ok(/if \(kind === 'board'\) \{ kbWatch\(host\); \}/.test(embeds));
    const f = load(embeds, ['kbCols'], 'function own(o, k) { return Object.prototype.hasOwnProperty.call(o, k) ? o[k] : undefined; }');
    const col = (id, title, n) => ({ getAttribute: () => id, querySelector: (q) => (q === '.kb-col-count' ? { textContent: String(n) } : { textContent: title }) });
    const scroll = { querySelectorAll: () => [col('1', '未対応', 2), col('2', '処理中', 0), col('1', '未対応', 3)] };
    assert.deepStrictEqual(f.kbCols(scroll).map((c) => c.id + ':' + c.l + ':' + c.n), ['1:未対応:5', '2:処理中:0'], 'one chip per status, counted over the lanes');
    assert.ok(/\.kb-col:not\(:hover\):not\(:focus-within\) \.kb-quickadd\{opacity:0;\}/.test(style));
    assert.ok(/scroll-snap-type:x mandatory/.test(style) && /\.pp-kb-wrap \.kb-col\{scroll-snap-align:start/.test(style), 'only inside the portal');
});

test('S10: a phone gantt says it scrolls and offers the list by due date', () => {
    assert.ok(/class="pp-gantt-hint"/.test(index));
    assert.ok(/href="#issues\?st=%21done&amp;so=dd&amp;sd=asc"/.test(index));
});

test('S12: the dashboard names fit, and each number says what it counts', () => {
    assert.ok(/\.pp-dash \.pp-pjlist \.cd\{flex:none;width:104px;/.test(listView));
    ['progress', 'open', 'overdue', 'mine'].forEach((k) => {
        assert.ok(listView.indexOf("trans('dash_help_" + k + "')") > 0, k);
        assert.ok(ja('dash_help_' + k), k);
    });
});

test('M8: the status menu offers every status and sends through the comment', () => {
    assert.ok(/postComment\(b\.getAttribute\('data-st'\)\)/.test(functionSource(detail, 'stMenuOpen')));
    assert.ok(/stBtn\(i, res\)/.test(functionSource(detail, 'buildDetail')));
    assert.ok(/k === 's' && window\.ppStMenuFromKey/.test(read('boot.js')), 'key s');
    ['ed_hint', 'ed_pen', 'mine_all', 'gantt_hint', 'gantt_list', 'kb_prev', 'kb_next', 'kb_cols', 'st_menu'].forEach((k) => {
        assert.ok(ja(k), k);
        assert.ok(index.indexOf("'" + k + "'") > 0, k + ' reaches the script');
    });
    assert.ok(langJa.indexOf('// UX-E') > 0);
});

if (failed) {
    console.log('\n' + failed + ' failed');
    process.exit(1);
}
console.log('\nall passed');
