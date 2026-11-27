#!/usr/bin/env node
// ExmentGroupware: the calendar's page (ISS02947).
//
//   node groupware/tools/test-gw-calendar.js
//
// The pure helpers of resources/views/calendar.blade.php, read out of the view and run alone:
// the days of a view (weeks start on Monday, the weeks of a month grid), the layout of events that
// overlap, the RRULE of the repeat choice, the form's times to and from UTC (all day and timed),
// and that a busy item is drawn as the busy label and nothing else. Run in Asia/Tokyo.
'use strict';

process.env.TZ = 'Asia/Tokyo';

const fs = require('fs');
const path = require('path');
const assert = require('assert');

const VIEW = path.join(__dirname, '..', 'ExmentGroupware', 'resources', 'views', 'calendar.blade.php');
const blade = fs.readFileSync(VIEW, 'utf8');
// the script of the page: the last <script> of the @verbatim part
const verbatim = blade.slice(blade.indexOf('@verbatim'));
const source = verbatim.slice(verbatim.lastIndexOf('<script>') + 8, verbatim.lastIndexOf('</script>'));

// The body of a function declared in the source, by counting braces.
function functionSource(src, name) {
    const start = src.indexOf('function ' + name + '(');
    assert.ok(start >= 0, 'function ' + name + ' is not in the view');
    let depth = 0;
    for (let i = src.indexOf('{', start); i < src.length; i++) {
        if (src[i] === '{') { depth++; }
        if (src[i] === '}' && --depth === 0) { return src.slice(start, i + 1); }
    }
    throw new Error('function ' + name + ' does not end');
}
function load(names, prelude) {
    const body = (prelude || '') + '\n' + names.map((n) => functionSource(source, n)).join('\n') + '\nreturn {' + names.join(',') + '};';
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

// a small stand-in of the DOM: enough to see what a node shows
function fakeDocument() {
    function node(tag) {
        const n = {
            tagName: tag.toUpperCase(), className: '', attrs: {}, style: {}, children: [], _text: '',
            setAttribute(k, v) { this.attrs[k] = String(v); },
            appendChild(c) { this.children.push(c); return c; },
            addEventListener() {},
            get textContent() { return this._text + this.children.map((c) => c.textContent).join(''); },
            set textContent(v) { this._text = String(v); this.children = []; }
        };
        return n;
    }
    return { createElement: node, createTextNode: (s) => ({ textContent: String(s) }) };
}

const LANG = { busy: '予定あり', f_all_day: '終日', more: 'ほか :n 件' };
const H = load([
    't', 'pad', 'dayKey', 'parseDay', 'addDays', 'startOfDay', 'startOfWeek', 'periodRange', 'shiftAnchor',
    'parseUtc', 'toUtc', 'itemSpan', 'overlapsDay', 'fmtTime', 'fmtRange', 'daySegments', 'layoutTimed',
    'buildRrule', 'repeatChoice', 'eventToForm', 'formTimes', 'localToUtc', 'formCheck', 'holidayLabel', 'evNode'
], 'var L = ' + JSON.stringify(LANG) + ';');
// evNode with a stand-in document
const withDoc = (doc) => new Function(
    'var L = ' + JSON.stringify(LANG) + '; var document = arguments[0];\n' +
    ['t', 'pad', 'parseDay', 'addDays', 'parseUtc', 'itemSpan', 'fmtTime', 'fmtRange', 'evNode'].map((n) => functionSource(source, n)).join('\n') +
    '\nreturn evNode;'
)(doc);
const keys = (days) => days.map(H.dayKey);

test('the time zone of the test is Asia/Tokyo', () => {
    assert.strictEqual(new Date(2026, 11, 1).getTimezoneOffset(), -540);
});

/* ---------------------------------------------------- the period ------ */
test('a week starts on Monday (from a Sunday, a Monday and a Wednesday)', () => {
    assert.strictEqual(H.dayKey(H.startOfWeek(new Date(2026, 10, 29))), '2026-11-23'); // Sun
    assert.strictEqual(H.dayKey(H.startOfWeek(new Date(2026, 10, 30))), '2026-11-30'); // Mon
    assert.strictEqual(H.dayKey(H.startOfWeek(new Date(2026, 11, 2, 15, 30))), '2026-11-30'); // Wed
});
test('week: seven days, Monday to Sunday, the end the Monday after', () => {
    const r = H.periodRange('week', new Date(2026, 11, 3), 'week');
    assert.deepStrictEqual(keys(r.days), ['2026-11-30', '2026-12-01', '2026-12-02', '2026-12-03', '2026-12-04', '2026-12-05', '2026-12-06']);
    assert.strictEqual(H.dayKey(r.to), '2026-12-07');
    assert.strictEqual(r.days[0].getDay(), 1);
});
test('day: one day, sent as UTC', () => {
    const r = H.periodRange('day', new Date(2026, 11, 3, 18), 'week');
    assert.deepStrictEqual(keys(r.days), ['2026-12-03']);
    assert.strictEqual(r.from.toISOString(), '2026-12-02T15:00:00.000Z');
    assert.strictEqual(r.to.toISOString(), '2026-12-03T15:00:00.000Z');
});
test('month grid: whole weeks from the Monday on or before the 1st to the Sunday on or after the last', () => {
    const dec = H.periodRange('month', new Date(2026, 11, 15));
    assert.strictEqual(H.dayKey(dec.from), '2026-11-30');
    assert.strictEqual(H.dayKey(dec.to), '2027-01-04');
    assert.strictEqual(dec.weeks, 5);
    assert.strictEqual(dec.days.length, 35);
    // February 2027 starts on a Monday and has 28 days: four weeks
    const feb = H.periodRange('month', new Date(2027, 1, 10));
    assert.strictEqual(H.dayKey(feb.from), '2027-02-01');
    assert.strictEqual(feb.weeks, 4);
    // August 2027 starts on a Sunday: six weeks
    const aug = H.periodRange('month', new Date(2027, 7, 1));
    assert.strictEqual(H.dayKey(aug.from), '2027-07-26');
    assert.strictEqual(aug.weeks, 6);
    assert.ok(aug.days.every((d, i) => i % 7 !== 0 || d.getDay() === 1));
});
test('list: the month; people: the day or the week', () => {
    const l = H.periodRange('list', new Date(2026, 11, 15));
    assert.strictEqual(H.dayKey(l.from), '2026-12-01');
    assert.strictEqual(H.dayKey(l.to), '2027-01-01');
    assert.strictEqual(H.periodRange('people', new Date(2026, 11, 3), 'day').days.length, 1);
    assert.strictEqual(H.periodRange('people', new Date(2026, 11, 3), 'week').days.length, 7);
});
test('previous / next move by the view', () => {
    const a = new Date(2027, 0, 31);
    assert.strictEqual(H.dayKey(H.shiftAnchor('day', a, 1)), '2027-02-01');
    assert.strictEqual(H.dayKey(H.shiftAnchor('week', a, -1)), '2027-01-24');
    assert.strictEqual(H.dayKey(H.shiftAnchor('month', a, 1)), '2027-02-01'); // not March 3
    assert.strictEqual(H.dayKey(H.shiftAnchor('people', a, 1, 'day')), '2027-02-01');
});

/* ---------------------------------------------------- overlap ------ */
test('events that overlap share the width; a group ends when nothing overlaps', () => {
    const segs = H.layoutTimed([
        { id: 'a', s: 540, e: 600 },  // 9:00-10:00
        { id: 'b', s: 570, e: 630 },  // 9:30-10:30
        { id: 'c', s: 600, e: 660 },  // 10:00-11:00: a's column is free again
        { id: 'd', s: 720, e: 780 }   // 12:00-13:00: alone
    ], 20);
    const by = {};
    segs.forEach((x) => { by[x.id] = x; });
    assert.deepStrictEqual([by.a.col, by.b.col, by.c.col], [0, 1, 0]);
    assert.deepStrictEqual([by.a.cols, by.b.cols, by.c.cols], [2, 2, 2]);
    assert.deepStrictEqual([by.d.col, by.d.cols], [0, 1]);
});
test('three at once take three columns; a short one is laid out as at least minLen', () => {
    const segs = H.layoutTimed([{ id: 1, s: 600, e: 660 }, { id: 2, s: 600, e: 630 }, { id: 3, s: 610, e: 620 }, { id: 4, s: 700, e: 700 }, { id: 5, s: 705, e: 730 }], 20);
    const by = {};
    segs.forEach((x) => { by[x.id] = x; });
    assert.deepStrictEqual([by[1].cols, by[2].cols, by[3].cols], [3, 3, 3]);
    assert.deepStrictEqual([by[1].col, by[2].col, by[3].col].sort(), [0, 1, 2]);
    // 11:40 of no length is drawn 20 minutes long, so 11:45 goes beside it
    assert.deepStrictEqual([by[4].col, by[5].col, by[5].cols], [0, 1, 2]);
});
test('the timed parts of a day, clipped to it (an event over midnight)', () => {
    const it = { id: 1, start: '2026-12-01 13:00:00', end: '2026-12-01 16:00:00', all_day: false }; // 22:00-01:00 Tokyo
    const d1 = H.daySegments([it], new Date(2026, 11, 1));
    const d2 = H.daySegments([it], new Date(2026, 11, 2));
    assert.deepStrictEqual([d1[0].s, d1[0].e], [1320, 1440]);
    assert.deepStrictEqual([d2[0].s, d2[0].e], [0, 60]);
    assert.strictEqual(H.daySegments([{ all_day: true, start: '2026-12-01 00:00:00', end: '2026-12-02 00:00:00' }], new Date(2026, 11, 1)).length, 0);
});
test('an all-day item is on its days and not the day after', () => {
    const it = { id: 1, all_day: true, start: '2026-12-01 00:00:00', end: '2026-12-03 00:00:00' };
    assert.strictEqual(H.overlapsDay(it, new Date(2026, 11, 1)), true);
    assert.strictEqual(H.overlapsDay(it, new Date(2026, 11, 2)), true);
    assert.strictEqual(H.overlapsDay(it, new Date(2026, 11, 3)), false);
});

/* ---------------------------------------------------- repeats ------ */
test('the RRULE of each repeat choice', () => {
    assert.strictEqual(H.buildRrule('none', '2026-12-01'), '');
    assert.strictEqual(H.buildRrule('daily', '2026-12-01'), 'FREQ=DAILY');
    assert.strictEqual(H.buildRrule('weekdays', '2026-12-01'), 'FREQ=WEEKLY;BYDAY=MO,TU,WE,TH,FR');
    assert.strictEqual(H.buildRrule('weekly', '2026-12-01'), 'FREQ=WEEKLY;BYDAY=TU');
    assert.strictEqual(H.buildRrule('weekly', '2026-12-06'), 'FREQ=WEEKLY;BYDAY=SU');
    assert.strictEqual(H.buildRrule('monthly', '2026-12-01'), 'FREQ=MONTHLY');
    assert.strictEqual(H.buildRrule('yearly', '2026-12-01'), 'FREQ=YEARLY');
    assert.strictEqual(H.buildRrule('custom', '2026-12-01', ' rrule:freq=monthly;byday=2tu '), 'FREQ=MONTHLY;BYDAY=2TU');
});
test('an RRULE back to the choice (weekly only on the start\'s day; the rest custom)', () => {
    assert.strictEqual(H.repeatChoice('', '2026-12-01'), 'none');
    assert.strictEqual(H.repeatChoice('FREQ=WEEKLY;BYDAY=MO,TU,WE,TH,FR', '2026-12-01'), 'weekdays');
    assert.strictEqual(H.repeatChoice('FREQ=WEEKLY;BYDAY=TU', '2026-12-01'), 'weekly');
    assert.strictEqual(H.repeatChoice('FREQ=WEEKLY;BYDAY=WE', '2026-12-01'), 'custom');
    assert.strictEqual(H.repeatChoice('FREQ=DAILY;COUNT=5', '2026-12-01'), 'custom');
});

/* ---------------------------------------------------- local and UTC ------ */
test('timed: UTC from the server to the local form, and the form to what is sent', () => {
    const v = H.eventToForm('2026-12-01 01:00:00', '2026-12-01 02:30:00', false);
    assert.deepStrictEqual(v, { all_day: false, sd: '2026-12-01', st: '10:00', ed: '2026-12-01', et: '11:30' });
    assert.deepStrictEqual(H.formTimes(v), { start: '2026-12-01T10:00', end: '2026-12-01T11:30' });
    assert.strictEqual(H.localToUtc('2026-12-01', '10:00'), '2026-12-01 01:00:00');
    // early in the morning in Tokyo is the day before in UTC, and back
    assert.strictEqual(H.localToUtc('2026-12-01', '08:00'), '2026-11-30 23:00:00');
    assert.deepStrictEqual(H.eventToForm('2026-11-30 23:00:00', '2026-12-01 00:00:00', false).sd, '2026-12-01');
});
test('all day: the days as they are, the end shown as the last day and sent as the last day', () => {
    const v = H.eventToForm('2026-12-01 00:00:00', '2026-12-04 00:00:00', true);
    assert.deepStrictEqual([v.all_day, v.sd, v.ed], [true, '2026-12-01', '2026-12-03']);
    assert.deepStrictEqual(H.formTimes(v), { start: '2026-12-01', end: '2026-12-03' });
    const one = H.eventToForm('2026-12-01 00:00:00', '2026-12-02 00:00:00', true);
    assert.deepStrictEqual([one.sd, one.ed], ['2026-12-01', '2026-12-01']);
    // an all-day item is the days, not moved by the time zone
    const sp = H.itemSpan({ all_day: true, start: '2026-12-01 00:00:00', end: '2026-12-02 00:00:00' });
    assert.deepStrictEqual([H.dayKey(sp.s), H.dayKey(sp.e)], ['2026-12-01', '2026-12-02']);
});
test('the form is checked before it is sent', () => {
    assert.strictEqual(H.formCheck({ title: ' ', sd: '2026-12-01', st: '10:00', ed: '2026-12-01', et: '11:00' }), 'err_title');
    assert.strictEqual(H.formCheck({ title: 'a', sd: '2026-12-01', st: '10:00', ed: '2026-12-01', et: '09:00' }), 'err_time');
    assert.strictEqual(H.formCheck({ title: 'a', sd: '2026-12-01', st: '23:00', ed: '2026-12-02', et: '01:00' }), null);
    assert.strictEqual(H.formCheck({ title: 'a', all_day: true, sd: '2026-12-02', ed: '2026-12-01' }), 'err_time');
    assert.strictEqual(H.formCheck({ title: 'a', all_day: true, sd: '2026-12-01', ed: '2026-12-01' }), null);
});

/* ---------------------------------------------------- what is shown ------ */
test('a busy item is the busy label and nothing else', () => {
    const evNode = withDoc(fakeDocument());
    const n = evNode({ busy: true, start: '2026-12-01 01:00:00', end: '2026-12-01 02:00:00', all_day: false, owner: 5, title: '人事面談 secret', location: '会議室A' }, { time: true });
    assert.strictEqual(n.textContent, '予定あり');
    assert.strictEqual(n.children.length, 0);
    assert.strictEqual(n.tagName, 'DIV'); // not a button: it cannot be opened
    assert.ok(!/secret|会議室/.test(JSON.stringify(n.attrs)), JSON.stringify(n.attrs));
    assert.strictEqual(n.attrs.title, '予定あり 10:00–11:00');
    // an item without an id is treated the same
    assert.strictEqual(evNode({ start: '2026-12-01 01:00:00', end: '2026-12-01 02:00:00', title: 'x' }).textContent, '予定あり');
});
test('an event shows its time and title as text', () => {
    const evNode = withDoc(fakeDocument());
    const n = evNode({ id: 3, start: '2026-12-01 01:00:00', end: '2026-12-01 02:00:00', all_day: false, title: '<b>定例</b>', repeat: true, mine: 'none' }, { time: true });
    assert.strictEqual(n.tagName, 'BUTTON');
    assert.strictEqual(n.textContent, '10:00<b>定例</b>');
    assert.ok(/tentative/.test(n.className));
});
test('the view puts no data into innerHTML', () => {
    assert.ok(!/innerHTML|insertAdjacentHTML|outerHTML|document\.write/.test(source), 'innerHTML is used');
});
test('a holiday\'s label: its name, or the kind', () => {
    const T = load(['t', 'holidayLabel'], 'var L = {holiday:"祝日",company_off:"会社の休み",company_work:"休日出勤"};');
    assert.strictEqual(T.holidayLabel({ name: '元日', kind: 'holiday' }), '元日');
    assert.strictEqual(T.holidayLabel({ name: '', kind: 'off' }), '会社の休み');
    assert.strictEqual(T.holidayLabel({ name: '', kind: 'work' }), '休日出勤');
    assert.strictEqual(H.t('more', 3), 'ほか 3 件');
});

/* ---------------------------------------------------- ExmentPM (part 4) ------ */
const PMLANG = { pm_pending: '予定（まだ課題になっていない）', pm_milestone: 'マイルストーン', pm_due: '期限' };
const pmNodeOf = (doc) => new Function(
    'var L = ' + JSON.stringify(PMLANG) + '; var document = arguments[0];\n' +
    ['t', 'pmNode', 'evNode'].map((n) => functionSource(source, n)).join('\n') +
    '\nreturn evNode;'
)(doc);
test('ExmentPM: the choice of the toolbar as the query', () => {
    const P = load(['pmQuery']);
    assert.deepStrictEqual(P.pmQuery('mine'), { filter: 'mine' });
    assert.deepStrictEqual(P.pmQuery('projects'), { filter: 'projects' });
    assert.deepStrictEqual(P.pmQuery('p12'), { filter: 'project', project: '12' });
    assert.strictEqual(P.pmQuery('off'), null);
    assert.strictEqual(P.pmQuery('p12;x'), null);
    assert.strictEqual(P.pmQuery(null), null);
});
test('ExmentPM: an issue is a button in its project\'s colour, its title as text', () => {
    const evNode = pmNodeOf(fakeDocument());
    const n = evNode({ pm: 'issue', kind: 'bar', start: '2026-12-01', end: '2026-12-05', all_day: true, title: 'ISS1 <b>見積</b>', project_name: 'P', color: '#0f8a6a', due: '2026-12-04', done: true });
    assert.strictEqual(n.tagName, 'BUTTON'); // it opens (no id: not taken for a busy one)
    assert.strictEqual(n.textContent, 'ISS1 <b>見積</b>');
    assert.ok(/\bpm\b/.test(n.className) && /\bdone\b/.test(n.className), n.className);
    assert.strictEqual(n.style.borderLeftColor, '#0f8a6a');
    assert.strictEqual(n.attrs.title, 'ISS1 <b>見積</b> (期限 2026-12-04) - P');
});
test('ExmentPM: a date of a schedule says it is not an issue yet; a colour that is not one is not set', () => {
    const evNode = pmNodeOf(fakeDocument());
    const n = evNode({ pm: 'schedule', start: '2026-12-05', end: '2026-12-06', all_day: true, title: '月次報告', color: 'red;background:url(x)' });
    assert.ok(/\bsched\b/.test(n.className));
    assert.strictEqual(n.style.borderLeftColor, undefined);
    assert.strictEqual(n.attrs.title, '月次報告 (予定（まだ課題になっていない）)');
});
test('the rooms of a day: a room taken out of use shows while it has bookings that day', () => {
    const R = load(['roomsOfDay']);
    const rooms = [{ id: 1, name: 'A', enabled: true }, { id: 2, name: 'B', enabled: false }, { id: 3, name: 'C', enabled: false }];
    const out = R.roomsOfDay(rooms, [{ resources: [2], title: '定例' }]);
    assert.deepStrictEqual(out.map((x) => [x.room.name, x.off, x.events.length]), [['A', false, 0], ['B', true, 1]]);
    assert.deepStrictEqual(R.roomsOfDay(rooms, []).map((x) => x.room.name), ['A']);
});

if (failed) {
    console.log(failed + ' failed');
    process.exit(1);
}
console.log('all passed');
