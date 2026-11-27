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
    function crumbParents() { return ''; }
    function crumbAncestors() { return []; }
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
    // TOP-1: under the gear, with its own icon
    assert.ok(/<div class="pp-top-gh"><i class="fa fa-sliders"><\/i> \{\{ PortalDefinition::trans\('teams\.menu_title'\)/.test(topbar));
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

/* ---------------------------------------------- UX-F: labels, colors -- */
const boot = read('boot.js');
const home = read('home.js');

test('S13: one word for one thing (ja)', () => {
    const want = { add_issue: '課題を追加', col_dd: '期限', lbl_author: '作成者', submit: '送信', rf_button: '最新にする', col_up: '更新日時',
        cmt_save: '保存', tab_settings: 'プロジェクトの設定', issue_more: 'ほかの操作', ned_more: 'ほかの項目', box_timeline: 'コメントと履歴',
        to_comments: 'コメント', notify_to: '知らせる人', reads: '読んだ人', lbl_eh: '予定時間 / 実績時間',
        stat_overdue: '期限切れ' };
    Object.keys(want).forEach((k) => assert.strictEqual(ja(k), want[k], k));
    const listView2 = view('list.blade.php');
    ['ドラッグで並べ替え', 'メニューを開く', '>すべて開く<', "'開く' : '閉じる'"].forEach((w) => assert.ok(listView2.indexOf(w) < 0 && topbar.indexOf(w) < 0, w + ' is in lang now'));
});

test('S14, M9: the colours are variables, Exment Modern first, the green after', () => {
    assert.ok(/--bl-accent:var\(--exm-ui-accent,#4caf93\)/.test(style));
    assert.ok(/--bl-accent-d:var\(--exm-ui-accent-strong,#3d9a80\)/.test(style));
    ['style.blade.php', '_topbar.blade.php', 'list.blade.php', 'ranking.blade.php', 'settings.blade.php'].forEach((f) => {
        const src = view(f).replace(/var\(--[a-z-]+,#[0-9a-fA-F]{6}\)/g, '');
        assert.ok(!/#4ca[ef]93|#3d9a80|#2c6e5b|#bfe3d6|#f2f8f5/i.test(src), f + ' has no green of its own left');
    });
    assert.ok(/\.pmportal \.bl-dback\{[^}]*background:var\(--bl-box\)/.test(style), 'a way back is a grey button');
});

test('S16: ? lists the keys; j k o move and open in the list; [ ] e c on an issue', () => {
    assert.ok(/ev\.key !== '\?'/.test(boot) && /function keyHelp\(show\)/.test(boot));
    const f = load(boot, ['kbdRow'], `
        var hashes = []; var location = { set hash(v) { hashes.push(v); } };
        function issueHash(id) { return 'issue-' + id; }
        function row(id) { return { id: id, cls: {}, classList: { contains: function (c) { return !!this.o[c]; }, add: function (c) { this.o[c] = 1; }, remove: function (c) { delete this.o[c]; }, o: {} },
            getAttribute: function () { return id; }, scrollIntoView: function () {} }; }
        var rows = [row('1'), row('2'), row('3')];
        var document = { querySelectorAll: function () { return rows; } };
        globalThis.__rows = rows; globalThis.__hashes = hashes;`);
    assert.strictEqual(f.kbdRow(0), false, 'nothing marked: o does nothing');
    f.kbdRow(1); f.kbdRow(1);
    assert.ok(globalThis.__rows[1].classList.contains('pp-kbd-cur'));
    f.kbdRow(-1);
    assert.ok(globalThis.__rows[0].classList.contains('pp-kbd-cur'));
    f.kbdRow(-1);
    assert.ok(globalThis.__rows[0].classList.contains('pp-kbd-cur'), 'stays on the first');
    f.kbdRow(0);
    assert.deepStrictEqual(globalThis.__hashes, ['issue-1']);
    assert.ok(/'\[': '\.bl-inav a:first-child:not\(\.off\)'/.test(boot) && /k === 'c'/.test(boot));
    ['kh_title', 'kh_move', 'kh_updown', 'kh_open', 'kh_comment', 'kh_status', 'kh_prevnext'].forEach((k) => assert.ok(ja(k), k));
    delete globalThis.__rows; delete globalThis.__hashes;
});

test('S15, M5: 「最近の更新」 is read after the page, ten at a time', () => {
    assert.ok(/\$feed = null;/.test(fs.readFileSync(path.join(PM, 'PmPortalController.php'), 'utf8')), 'not built with the page');
    assert.ok(fs.readFileSync(path.join(PM, 'PmPortalController.php'), 'utf8').indexOf('function buildFeed(') < 0);
    assert.ok(/"uri": "\{id\}\/feed", "function": "projectFeed"/.test(fs.readFileSync(path.join(PM, 'config.json'), 'utf8')));
    assert.ok(/public function projectFeed\(\$id\)/.test(fs.readFileSync(path.join(PM, 'PluginPage.php'), 'utf8')));
    const trait = fs.readFileSync(path.join(PM, 'PortalFeedTrait.php'), 'utf8');
    assert.ok(trait.indexOf("whereIn('revisionable_id'") === trait.lastIndexOf("whereIn('revisionable_id'"), 'issues are not named one by one (only the wiki / project read does)');
    assert.ok(/pmCached\('feed'/.test(trait));
    assert.ok(/params\.before = FEED\.next/.test(functionSource(home, 'feedLoad')));
    assert.ok(/Array\.isArray\(D\.feed\)/.test(functionSource(home, 'renderFeed')), 'an old payload with the feed still shows');
    assert.ok(/<details class="pp-fmore">/.test(listView), 'the dashboard shows ten, the rest behind もっと見る');
});

test('M10: empty project, empty filter result, first steps', () => {
    assert.ok(/id="pp-home-empty"/.test(index) && /function renderFirstSteps/.test(home));
    assert.ok(/pp-empty-clear/.test(listSrc) && /anyFilter\(\)/.test(listSrc));
    assert.ok(/id="pp-first"/.test(listView) && /pmportal\.firststeps/.test(listView));
    ['empty_issues', 'empty_filtered', 'first_steps', 'fs_add_issue', 'fs_invite', 'fs_new_project', 'empty_projects', 'f_clear_all'].forEach((k) => assert.ok(ja(k), k));
    assert.ok(langJa.indexOf('// UX-F') > 0);
});

/* ---------------------------------------------- UX-H: the form -------- */
test('M11: a new issue asks for who, by when and how urgent first; the rest folds and can be searched', () => {
    const form = read('form.js');
    const f = load(form, ['nedIssueLayout'], `
        var NED = { mode: 'create', table: 'pm_issue' };
        var D = { issue_table: 'pm_issue', issue_cols: { status: 'st', assignees: 'as', priority: 'pr', milestone: 'ms', category: 'cat', start: 'sd', due: 'dd', type: 'ty', parent: 'pa', estimate: 'eh' },
            issue_extra_cols: { due_time: 'dt' } };`);
    const lay = f.nedIssueLayout();
    assert.deepStrictEqual(lay.rows, [['as', 'dd'], ['pr', 'dt']]);
    assert.deepStrictEqual(lay.more.slice(0, 3), [['st', 'ms'], ['cat', null], ['sd', null]]);
    const g = load(form, ['nedIssueLayout'], `
        var NED = { mode: 'edit', table: 'pm_issue' };
        var D = { issue_table: 'pm_issue', issue_cols: { status: 'st', assignees: 'as', priority: 'pr', milestone: 'ms', due: 'dd' }, issue_extra_cols: {} };`);
    assert.deepStrictEqual(g.nedIssueLayout().rows[0], ['st', 'as'], 'the edit form keeps its rows');
    const fields = read('form-fields.js');
    assert.ok(/col && col\.required && !col\.readonly\) \{ slots\.grid\.appendChild\(fg\)/.test(fields), 'a required field is never folded');
    assert.ok(/className = 'pp-ne-more-find'/.test(fields) && ja('ned_more_find'));
});

test('S6: on a phone the form head wraps so 保存 stays on screen', () => {
    assert.ok(/\.pp-nedit \.pp-ne-head-top\{flex-wrap:wrap;/.test(style));
});

test('M12: who to tell - assignees and author first, then the recent picks, none switched on', () => {
    const f = load(detail, ['notifyOrder']);
    const order = f.notifyOrder({ as: [5], by: 7 }, [1, 2, 5, 7, 9], ['9', '2']);
    assert.deepStrictEqual(order, [5, 7, 9, 2, 1]);
    assert.ok(/notifyRecentSave\(notifyUsers\)/.test(read('writes.js')), 'the picks of a sent comment are remembered');
    assert.ok(/var on = keepNotify && keepNotify\.indexOf/.test(functionSource(detail, 'buildDetail')), 'nothing on by itself');
    assert.ok(langJa.indexOf('// UX-H') > 0);
});

test('FIX-1: phone menus of the list stay on screen, the key copy button on the row in focus', () => {
    assert.ok(/\.pmportal \.pp-sf-menu,\.pmportal \.pp-oc-menu\{left:0;right:auto;\}/.test(style));
    assert.ok(/@media \(hover:hover\)\{\s*\.pmportal #pp-issue-rows tr \.pp-ikcopy\{opacity:0;/.test(style));
});

test('TOP-1: 通知の受け取り方, 全体の設定 and マニュアル in one menu under the gear', () => {
    const gear = topbar.slice(topbar.indexOf('<div class="pp-top-dd pp-top-gear">'), topbar.indexOf('</nav>'));
    assert.ok(gear.length > 0);
    ['data-notify-by=', 'pp-notify-by', 'pp-top-mi pp-top-settings', 'pp-top-mi pp-top-manual'].forEach((c) => assert.ok(gear.indexOf(c) > 0, c));
    assert.ok(gear.indexOf('pp-notify-by') < gear.indexOf('pp-top-settings') && gear.indexOf('pp-top-settings') < gear.indexOf('pp-top-manual'));
    assert.ok(topbar.indexOf('pp-top-notifywrap') < 0, 'no second menu for the notices');
    assert.ok(/PortalDefinition::trans\('top_gear'\)/.test(gear) && ja('top_gear') && ja('top_gear_tip'));
    assert.ok(langJa.indexOf('// TOP-1') > 0);
});

test('LIST-1: the list\'s ⋯ menu holds the downloads and the bulk change', () => {
    assert.ok(/id="pp-f-csv"[^>]*hidden>/.test(index), 'the old download link is out of sight');
    assert.ok(/id="pp-l-more"/.test(index));
    const items = functionSource(listSrc, 'listMoreItems');
    ["exportIssues('xlsx', 'all')", "exportIssues('xlsx', 'screen')", "exportIssues('csv', 'all')", "exportIssues('csv', 'screen')", 'pp-chk-all'].forEach((c) => assert.ok(items.indexOf(c) > 0, c));
    ['l_more', 'l_more_tip', 'l_bulk_pick'].forEach((k) => { assert.ok(ja(k), k); assert.ok(index.indexOf("'" + k + "'") > 0, k); });
    assert.ok(langJa.indexOf('// LIST-1') > 0);
});

test('HELP-1: the ? box says what the buttons of this screen do', () => {
    const bootSrc = read('boot.js');
    const f = new Function("var L = { sh_issue_1: 'a', sh_issue_2: 'b', sh_title: 'T', sh_palette: 'P' }; function esc(s) { return String(s); } " +
        functionSource(bootSrc, 'screenHelpLines') + functionSource(bootSrc, 'screenHelpHtml') + 'return { lines: screenHelpLines, html: screenHelpHtml };')();
    assert.deepStrictEqual(f.lines('issue'), ['a', 'b']);
    assert.deepStrictEqual(f.lines('nothing'), []);
    assert.strictEqual(f.html('nothing'), '');
    assert.ok(f.html('issue').indexOf('<li>a</li><li>b</li>') > 0);
    assert.ok(/screenHelpHtml\(currentScreen\)/.test(functionSource(bootSrc, 'keyHelp')));
    assert.ok(/L\.sh_title/.test(read('palette.js')), 'in the palette too');
    ['home', 'issues', 'board', 'gantt', 'wiki', 'files', 'issue'].forEach((scr) => {
        assert.ok(ja('sh_' + scr + '_1'), scr);
        assert.ok(index.indexOf("'sh_" + scr + "_1'") > 0, scr + ' passed to the script');
    });
    assert.ok(langJa.indexOf('// HELP-1') > 0);
});

test('HELP-1 fix: the ? box and the palette start under the fixed header', () => {
    const bootSrc = read('boot.js');
    const safe = functionSource(bootSrc, 'ppTopSafe');
    assert.ok(/portal\.getBoundingClientRect\(\)\.top/.test(safe) && /\.main-header/.test(safe), 'below the top of the portal, or the header');
    const f = new Function('root', 'document', safe + 'return ppTopSafe();');
    const portal = { getBoundingClientRect: () => ({ top: 63 }) };
    const fakeRoot = { closest: () => portal };
    assert.strictEqual(f(fakeRoot, { querySelector: () => ({ getBoundingClientRect: () => ({ bottom: 50 }) }) }), 63);
    assert.strictEqual(f(fakeRoot, { querySelector: () => ({ getBoundingClientRect: () => ({ bottom: 100 }) }) }), 100);
    assert.strictEqual(f({ closest: () => ({ getBoundingClientRect: () => ({ top: -400 }) }) }, { querySelector: () => null }), 0, 'never above 0');
    assert.ok(/ppPlaceBox\(box\);/.test(functionSource(bootSrc, 'keyHelp')), 'the ? box');
    assert.ok(/ppPlaceBox\(box\);/.test(functionSource(read('palette.js'), 'palOpen')), 'the palette, the same helper');
    assert.ok(/addEventListener\('resize'/.test(functionSource(bootSrc, 'ppPlaceBox')));
    assert.ok(style.indexOf('.pmportal .pp-keyhelp{align-items:flex-start;padding:calc(var(--pp-top-safe, 0px) + 8px) 16px 16px;z-index:1100;}') > 0);
    assert.ok(style.indexOf('.pmportal .pp-keyhelp .in{max-height:calc(100vh - var(--pp-top-safe, 0px) - 24px);overflow:auto;}') > 0);
});

test('HINT-1: hints shown once, only where they fit', () => {
    const homeSrc = read('home.js');
    const f = new Function('var HINT_MANY_DUES = 10; var HINT_LONG_LIST = 50; var D = { me: 3 }; var store = {};' +
        'var localStorage = { getItem: function (k) { return store[k] || null; }, setItem: function (k, v) { store[k] = v; } };' +
        functionSource(homeSrc, 'hintKey') + functionSource(homeSrc, 'hintSeen') + functionSource(homeSrc, 'hintDone') + functionSource(homeSrc, 'hintWants') +
        'return { seen: hintSeen, done: hintDone, wants: hintWants };')();
    assert.ok(f.wants('remind', { reminderUrl: '/r', openWithDue: 10 }));
    assert.ok(!f.wants('remind', { reminderUrl: '', openWithDue: 99 }), 'no reminders on the site, or not the manager: no hint');
    assert.ok(!f.wants('remind', { reminderUrl: '/r', openWithDue: 9 }));
    assert.ok(f.wants('saved', { matched: 50, saved: 0 }));
    assert.ok(!f.wants('saved', { matched: 80, saved: 1 }), 'has a saved condition already');
    assert.ok(!f.seen('saved'));
    f.done('saved');
    assert.ok(f.seen('saved'), 'once');
    assert.ok(/hintHome\(\);/.test(functionSource(homeSrc, 'renderHome')));
    assert.ok(/hintList\(matched\.length, savedList\(\)\.length\);/.test(functionSource(listSrc, 'renderIssues')));
    assert.ok(/data-reminder-url="\{\{ \$reminder_url \?\? '' \}\}"/.test(index));
    ['hint_remind', 'hint_remind_go', 'hint_saved', 'hint_saved_go'].forEach((k) => { assert.ok(ja(k), k); assert.ok(index.indexOf("'" + k + "'") > 0, k); });
    assert.ok(langJa.indexOf('// HINT-1') > 0);
});

test('HINT-1 fix: the list hint is above the table and its link opens 保存した条件', () => {
    assert.ok(index.indexOf('id="pp-hint-list"') < index.indexOf('id="pp-bulk"') && index.indexOf('id="pp-hint-list"') < index.indexOf('<table class="bl-list">'));
    const show = functionSource(read('home.js'), 'hintShow');
    assert.ok(/ev\.stopPropagation\(\);/.test(show) && /setTimeout\(go, 0\)/.test(show));
});

test('QE-SA / QE-ROLES: the dock changes 副担当者, 管理者, レビュアー and 難易度, where the table has them', () => {
    const qe = functionSource(detail, 'renderQuickEdit');
    assert.ok(/QF_ROLES\.forEach\(function \(r\) \{/.test(qe));
    assert.ok(/if \(!qeCol\(QE_COLS\[r\[0\]\]\)\) \{ return; \}/.test(qe), 'only where the column is there');
    assert.ok(/qeChips\(r\[0\], people, QE\.base\[r\[0\]\], named, true\)/.test(qe), 'the assignees picker');
    assert.ok(/if \(qeCol\(QE_COLS\.df\)\) \{\s*box\.appendChild\(qeRow\(L\.lbl_difficulty, qeSelect\('df'/.test(qe));
    assert.ok(qe.indexOf("qeChips('as'") < qe.indexOf('QF_ROLES.forEach') && qe.indexOf('QF_ROLES.forEach') < qe.indexOf("qeChips('cat'"), 'right under the assignees');
    assert.ok(/var QF_ROLES = \[\['sa', 'sub_assignees', 'lbl_sub_assignees'\], \['mg', 'managers', 'lbl_managers'\], \['rv', 'reviewers', 'lbl_reviewers'\]\];/.test(detail));
    assert.ok(/sa: 'sub_assignees', mg: 'managers', rv: 'reviewers', df: 'difficulty', nu: 'notified_users'/.test(detail), 'saved by the same column map');
    ['lbl_sub_assignees', 'lbl_managers', 'lbl_reviewers', 'lbl_difficulty'].forEach((k) => assert.ok(index.indexOf("'" + k + "'") > 0, k));
});

test('POS-1: one 課題の追加 in a project (its header row), the project settings at the right of the tabs', () => {
    const homeSrc = read('home.js');
    assert.ok(/pp-projadd" href="#new"/.test(homeSrc) && /CAN_ADD_ISSUE && !p\.archived/.test(homeSrc));
    assert.ok(/@if\(\(\$addable \|\| !\$open\) && \$topbarCur === null\)/.test(topbar), 'the top bar has none inside a project');
    assert.ok(/#pp-projhead \.pp-projadd/.test(read('boot.js')) && /#pp-projhead \.pp-projadd/.test(read('palette.js')), 'the a key and Ctrl+K');
    assert.ok(index.indexOf('class="bl-tabsearch"') < index.indexOf('pp-tab-set'), 'settings after the search, at the right end');
    assert.strictEqual(ja('top_gear'), '全体の設定');
    assert.ok(ja('pos_add_tip') && langJa.indexOf('// POS-1') > 0);
    assert.ok(/プロジェクト名の行の右/.test(ja('sh_home_1')));
});

test('POS-1 fix: at 390 the name row stays one line', () => {
    assert.ok(style.indexOf('.pmportal .bl-projhead:not(.info-open):not(.note-open){flex-wrap:nowrap;gap:6px;}') > 0);
    assert.ok(style.indexOf('.pmportal .bl-projhead:not(.info-open):not(.note-open) .bl-projinfo{flex:1 1 0;min-width:0;}') > 0);
    assert.ok(/\.pp-projadd,\.pmportal \.bl-projhead \.bl-dback-proj\{flex:none;\}/.test(style));
});

test('BC-1: the parents in the trail above an issue', () => {
    const f = new Function("var L = { bc_hidden: '見られない課題', bc_more: '…' }; function esc(s) { return String(s); } function issueHash(id) { return 'issue-' + id; }" +
        functionSource(detail, 'crumbParents') + 'return crumbParents;')();
    assert.strictEqual(f([]), '');
    const one = f([{ id: 3, key: 'WEB-3', s: '親', ok: true }]);
    assert.ok(one.indexOf('href="#issue-3"') > 0 && one.indexOf('title="WEB-3 親"') > 0 && one.indexOf('pp-crumb-anc last') > 0);
    const three = f([{ id: 1, key: 'WEB-1', s: 'a', ok: true }, { id: 2, ok: false }, { id: 3, key: 'WEB-3', s: 'c', ok: true }]);
    assert.ok(/pp-crumb-ancs folded/.test(three) && /pp-crumb-more/.test(three), 'more than two: the middle folds');
    assert.ok(three.indexOf('見られない課題') > 0 && three.indexOf('href="#issue-2"') < 0, 'one the reader may not open: no link');
    assert.ok(/crumbParents\(crumbAncestors\(i\)\)/.test(functionSource(detail, 'crumbs')));
    const ctl = fs.readFileSync(path.join(PM, 'PmPortalController.php'), 'utf8');
    assert.ok(/'anc' => \$ancestors\[intval\(\$issue->id\)\] \?\? \[\]/.test(ctl) && /function ancestorsOf\(/.test(ctl));
    assert.ok(ja('bc_hidden') && langJa.indexOf('// BC-1') > 0);
});

test('tree view: a child that was read does not look unread (the unread mark is a blue edge and tint)', () => {
    assert.ok(!/tr\.pp-tkid td:first-child\{box-shadow:inset 3px/.test(style), 'no edge at the left of a child: that is the unread mark');
    assert.ok(!/tr\.pp-tkid td\{background:color-mix\(in srgb, var\(--bl-accent\)/.test(style), 'no accent tint: a blue theme made it the unread tint');
    assert.ok(/tr\.pp-tkid\.pp-unread td\{background:color-mix\(in srgb, #2f80ed 6%/.test(style), 'an unread child keeps the unread tint');
    assert.ok(/tr\.pp-unread > td:first-child\{box-shadow:inset 3px 0 0 #2f80ed;\}/.test(style), 'the unread edge is still there');
});

test('BACK-1: an issue goes back where it was opened from', () => {
    const routing = read('routing.js');
    const f = new Function("var L = { back_list: '課題一覧', back_board: 'ボード', back_gantt: 'ガント', back_home: 'ホーム', back_tip: '%s に戻る' };" +
        functionSource(routing, 'backFrom') + functionSource(routing, 'backTarget') + 'return { from: backFrom, target: backTarget };')();
    assert.strictEqual(f.from('board'), 'board');
    assert.strictEqual(f.from('wiki'), 'issues', 'elsewhere: the list');
    assert.strictEqual(f.from(null), 'issues', 'opened from a link or another page: the list');
    assert.deepStrictEqual(f.target('gantt'), { hash: '#gantt', label: 'ガント', tip: 'ガント に戻る' });
    assert.strictEqual(f.target('issue').hash, '#issues');
    const show = functionSource(routing, 'show');
    assert.ok(/if \(scr === 'issue' && currentScreen !== 'issue'\) \{ BACK\.from = backFrom\(currentScreen\); \}/.test(show), 'kept across ‹ › between issues');
    assert.ok(/BACK\.flash = String\(currentIssue\)/.test(show));
    assert.ok(/backFlash\(\);/.test(functionSource(listSrc, 'renderIssues')), 'the issue lit up in the list');
    assert.ok(/backButton\(''\) \+ crumbs\(i\)/.test(detail) && /backButton\('pp-dbar-back'\)/.test(detail), 'before the trail and in the bar on scroll');
    ['back_list', 'back_board', 'back_gantt', 'back_home', 'back_tip'].forEach((k) => { assert.ok(ja(k), k); assert.ok(index.indexOf("'" + k + "'") > 0, k); });
    assert.ok(langJa.indexOf('// BACK-1') > 0);
});

if (failed) {
    console.log('\n' + failed + ' failed');
    process.exit(1);
}
console.log('\nall passed');
