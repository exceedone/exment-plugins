#!/usr/bin/env node
// ExmentPM: the custom attributes of the issues on the screen (ISS02939).
//
//   node project-management/tools/test-pm-attributes.js
//
// The pure helpers of resources/js/src/attributes.js, read out of the source
// and run with small stand-ins: the JSON a save sends, how a value reads, the
// filter, the sort, the bulk edit. The views are read as text.
'use strict';

const fs = require('fs');
const path = require('path');
const assert = require('assert');

const PM = path.join(__dirname, '..', 'ExmentPM');
const SRC = path.join(PM, 'resources', 'js', 'src');
const read = (name) => fs.readFileSync(path.join(SRC, name), 'utf8');
const view = (name) => fs.readFileSync(path.join(PM, 'resources', 'views', name), 'utf8');

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
// extra: names the prelude declares, handed back too
function load(source, names, prelude, extra) {
    const body = (prelude || '') + '\n' + names.map((n) => functionSource(source, n)).join('\n') +
        '\nreturn {' + names.concat(extra || []).join(',') + '};';
    return new Function(body)();
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

const at = read('attributes.js');
const list = read('list.js');

const DEFS = [
    { id: 12, l: 'OS', t: 'select', o: ['Linux', 'Windows'], r: false, u: '', d: 0, mx: 0 },
    { id: 13, l: '工数', t: 'number', o: [], r: true, u: '時間', d: 2, mx: 0 },
    { id: 14, l: '納期', t: 'date', o: [], r: false, u: '', d: 0, mx: 0 },
    { id: 15, l: '環境', t: 'multi_select', o: ['a', 'b', 'c'], r: false, u: '', d: 0, mx: 0 },
    { id: 16, l: '確認者', t: 'user', o: [], r: false, u: '', d: 0, mx: 0 },
    { id: 17, l: 'メモ', t: 'text', o: [], r: false, u: '', d: 0, mx: 1000 }
];
const STUBS = `
    var D = { project: { attributes: ${JSON.stringify(DEFS)} }, users: { '7': { n: '山田' }, '9': { n: 'Ann' } } };
    var SORT_COLS = {};
    var SORT_DEF = { by: 'dd', dir: 'asc' };
    var SORT = { by: 'at13', dir: 'asc' };
    var COLL = new Intl.Collator('ja');
    function own(o, k) { return o && Object.prototype.hasOwnProperty.call(o, k) ? o[k] : undefined; }
`;
const A = load(at + '\n' + list, [
    'atDefs', 'atDef', 'atEmpty', 'atDecode', 'atNormalize', 'atJson', 'atNumber', 'atText', 'atUserName',
    'atSortValue', 'atSortCol', 'atSortRegister', 'atFilterParse', 'atFilterString', 'atFilterKeep', 'atMatch',
    'atRowMatch', 'atBulkValue', 'cmpText', 'sortBlank', 'sortCmp'
], STUBS + 'function setSort(by, dir) { SORT.by = by; SORT.dir = dir; } function cols() { return SORT_COLS; }', ['setSort', 'cols']);
const def = (id) => DEFS.filter((d) => d.id === id)[0];
const name = (id) => ({ 7: '山田', 9: 'Ann' })[id] || '#' + id;

/* ---------------------------------------------------- the save ------ */
test('the column holds the JSON of the values, keys as strings in order, empties left out', () => {
    const json = A.atJson(DEFS, {}, { 15: ['c', 'a'], 12: 'Linux', 13: '3.50', 14: '', 16: ['9', '7', '9'], 17: '  x  ' });
    assert.strictEqual(json, '{"12":"Linux","13":3.5,"15":["a","c"],"16":[9,7],"17":"x"}');
    const back = JSON.parse(json);
    Object.keys(back).forEach((k) => assert.strictEqual(typeof k, 'string'));
});
test('nothing set is an empty string, not {}', () => {
    assert.strictEqual(A.atJson(DEFS, {}, { 12: '', 13: '', 15: [], 16: [], 17: '   ' }), '');
    assert.strictEqual(A.atJson(DEFS, { 12: 'Linux' }, { 12: '' }), '');
});
test('a value the form does not show (a deleted attribute) is kept as it was', () => {
    const json = A.atJson(DEFS, { 99: 'old', 12: 'Linux' }, { 12: 'Windows' });
    assert.strictEqual(json, '{"12":"Windows","99":"old"}');
});
test('an unchanged form gives back the same string, so the diff leaves it out', () => {
    const stored = '{"12":"Linux","13":3.5,"15":["a","c"],"16":[7]}';
    const base = A.atDecode(stored);
    const json = A.atJson(DEFS, base, { 12: 'Linux', 13: '3.5', 14: '', 15: ['a', 'c'], 16: ['7'], 17: '' });
    assert.strictEqual(json, stored);
});
test('a number that is not one is sent as typed (the server words the error)', () => {
    assert.strictEqual(A.atNormalize(def(13), 'abc'), 'abc');
    assert.strictEqual(A.atNormalize(def(13), ' 2 '), 2);
});
test('the stored JSON is read back: a string, an object, [] or rubbish', () => {
    assert.deepStrictEqual(A.atDecode('{"12":"Linux","x":1,"13":""}'), { 12: 'Linux' });
    assert.deepStrictEqual(A.atDecode({ 15: ['a'], 16: [] }), { 15: ['a'] });
    assert.deepStrictEqual(A.atDecode([]), {});
    assert.deepStrictEqual(A.atDecode(''), {});
    assert.deepStrictEqual(A.atDecode('{oops'), {});
});

/* ---------------------------------------------------- how it reads -- */
test('a number reads with its decimals, its thousands and its unit', () => {
    assert.strictEqual(A.atText(def(13), 1234.5, name), '1,234.50 時間');
    assert.strictEqual(A.atText(Object.assign({}, def(13), { u: '', d: 0 }), 3, name), '3');
    assert.strictEqual(A.atText(def(13), -1234567, name), '-1,234,567.00 時間');
});
test('the choices of a multi-select and the users are joined with 、', () => {
    assert.strictEqual(A.atText(def(15), ['a', 'c'], name), 'a、c');
    assert.strictEqual(A.atText(def(16), [7, 9, 3], name), '山田、Ann、#3');
    assert.strictEqual(A.atUserName(9), 'Ann');
    assert.strictEqual(A.atUserName(4), '#4');
});
test('an empty value reads as nothing (the detail hides the row)', () => {
    assert.strictEqual(A.atText(def(12), undefined, name), '');
    assert.strictEqual(A.atText(def(15), [], name), '');
    assert.strictEqual(A.atText(def(17), 'メモ', name), 'メモ');
});

/* ---------------------------------------------------- the filters --- */
test('text: contains, either case', () => {
    assert.ok(A.atMatch(def(17), 'Hello World', 'world'));
    assert.ok(!A.atMatch(def(17), 'Hello', 'x'));
    assert.ok(!A.atMatch(def(17), undefined, 'x'));
    assert.ok(A.atMatch(def(17), undefined, ''));
});
test('number and date: from and to, either end open; an empty value is out', () => {
    assert.ok(A.atMatch(def(13), 3, ['1', '5']));
    assert.ok(A.atMatch(def(13), 5, ['', '5']));
    assert.ok(!A.atMatch(def(13), 6, ['', '5']));
    assert.ok(!A.atMatch(def(13), 0.5, ['1', '']));
    assert.ok(!A.atMatch(def(13), undefined, ['1', '']));
    assert.ok(A.atMatch(def(14), '2026-12-01', ['2026-12-01', '2026-12-31']));
    assert.ok(!A.atMatch(def(14), '2027-01-01', ['', '2026-12-31']));
});
test('select, multi-select and user: one value', () => {
    assert.ok(A.atMatch(def(12), 'Linux', 'Linux'));
    assert.ok(!A.atMatch(def(12), 'Windows', 'Linux'));
    assert.ok(A.atMatch(def(15), ['a', 'b'], 'b'));
    assert.ok(!A.atMatch(def(15), ['a'], 'b'));
    assert.ok(A.atMatch(def(16), [7, 9], '9'));
    assert.ok(!A.atMatch(def(16), [7], '9'));
});
test('a row passes every filter; one of an attribute not defined here is ignored', () => {
    const f = A.atFilterParse('{"12":"Linux","13":["1",""],"88":"x"}');
    assert.ok(A.atRowMatch({ at: { 12: 'Linux', 13: 2 } }, DEFS, f));
    assert.ok(!A.atRowMatch({ at: { 12: 'Linux' } }, DEFS, f));
    assert.ok(!A.atRowMatch({ at: [] }, DEFS, f), 'a row with no values (packed as []) has none');
});
test('the filter is one string: empties dropped, keys in order, rubbish ignored', () => {
    assert.strictEqual(A.atFilterString({ 17: 'x', 12: '', 13: ['', ''], 14: ['2026-01-01', ''] }), '{"14":["2026-01-01",""],"17":"x"}');
    assert.strictEqual(A.atFilterString({}), '');
    assert.deepStrictEqual(A.atFilterParse('nope'), {});
    assert.deepStrictEqual(A.atFilterParse('{"toString":"x","12":{"a":1}}'), {});
    assert.strictEqual(A.atFilterKeep('{"12":"Linux","88":"x"}'), '{"12":"Linux"}', 'from another project, only ours');
});

/* ---------------------------------------------------- the sort ------ */
test('sorting: numbers as numbers, dates as strings, text collated, blanks last either way', () => {
    A.atSortRegister();
    const rows = [
        { id: 1, at: { 13: 10, 14: '2026-12-05', 17: 'い' } },
        { id: 2, at: [] },
        { id: 3, at: { 13: 9, 14: '2026-11-30', 17: 'あ' } },
        { id: 4, at: { 13: 100, 17: 'う' } }
    ];
    const order = (by, dir) => {
        A.setSort(by, dir);
        return rows.slice().sort(A.sortCmp()).map((r) => r.id);
    };
    assert.ok(A.cols().at13 && A.cols().at17, 'a column for each attribute');
    assert.deepStrictEqual(order('at13', 'asc'), [3, 1, 4, 2]);
    assert.deepStrictEqual(order('at13', 'desc'), [4, 1, 3, 2]);
    assert.deepStrictEqual(order('at14', 'asc'), [3, 1, 2, 4]);
    assert.deepStrictEqual(order('at17', 'asc'), [3, 1, 4, 2]);
    assert.strictEqual(A.atSortValue(def(16), [9, 7], name), 'Ann', 'users by the first name');
});

/* ---------------------------------------------------- the bulk edit - */
test('bulk: nothing, a value, a choice as a list, or __none__ to empty it', () => {
    assert.strictEqual(A.atBulkValue(def(17), '', false), undefined);
    assert.strictEqual(A.atBulkValue(def(17), ' x ', false), 'x');
    assert.strictEqual(A.atBulkValue(def(17), 'x', true), '__none__');
    assert.deepStrictEqual(A.atBulkValue(def(15), 'b', false), ['b']);
    assert.deepStrictEqual(A.atBulkValue(def(16), '7', false), ['7']);
    assert.strictEqual(A.atBulkValue(def(12), '__none__', false), '__none__');
});

/* ---------------------------------------------------- wiring -------- */
test('the form never shows the raw column, and the description cannot be it', () => {
    const form = read('form.js');
    assert.ok(/nedIsHiddenColumn[\s\S]*?col\.name === nedAtColumnName\(\)/.test(functionSource(form, 'nedIsHiddenColumn')));
    assert.ok(functionSource(form, 'nedPickDescColumn').indexOf('nedAtColumnName()') > 0);
    assert.ok(functionSource(read('form-fields.js'), 'nedRenderForm').indexOf('nedAtMount(slots)') > 0);
});
test('the views and the build know the attributes', () => {
    const index = view('index.blade.php');
    assert.ok(index.indexOf("$lang['attributes'] = \\App\\Plugins\\ExmentPM\\PortalDefinition::trans('attributes');") > 0);
    assert.ok(index.indexOf('id="pp-f-at"') > 0 && index.indexOf('id="pp-bk-at"') > 0);
    const settings = view('_settings.blade.php');
    assert.ok(/attributes: paneAttributes/.test(settings));
    assert.ok(settings.indexOf("'at' => PortalDefinition::trans('attributes')") > 0);
    assert.ok(settings.indexOf("['attributes', $st('tab_attributes'), 'fa-tags']") > 0);
    const build = fs.readFileSync(path.join(__dirname, 'build-pm-script.js'), 'utf8');
    assert.ok(build.indexOf("'attributes.js'") > build.indexOf("'errors.js'") && build.indexOf("'attributes.js'") < build.indexOf("'list.js'"));
    assert.ok(/FILTER_FIELDS = \[[^\]]*'at'/.test(list), 'the filter rides with the others');
});

if (failed) {
    console.log('\n' + failed + ' failed');
    process.exit(1);
}
console.log('\nall passed');
