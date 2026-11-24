#!/usr/bin/env node
// The scripts' side of "times in the time zone of the user" (Exment 7.2.4):
// ExmentPM's clock (nowHM) and the dates of Exment Plugin Groupware's web mail.
//
//   node project-management/tools/test-user-timezone.js
//
// A Tokyo site and a New York user. The process runs in a third time zone
// (TZ below), so a result that is right only on the developer's computer fails.
'use strict';

process.env.TZ = 'Europe/Berlin';

const fs = require('fs');
const path = require('path');
const assert = require('assert');

const ROOT = path.join(__dirname, '..', '..');
const read = (file) => fs.readFileSync(path.join(ROOT, file), 'utf8');

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

// ---- ExmentPM: nowHM -------------------------------------------------------
const errors = read('project-management/ExmentPM/resources/js/src/errors.js');
const nowHM = (D) => new Function('D', functionSource(errors, 'nowHM') + '; return nowHM();')(D);
const pad = (n) => ('0' + n).slice(-2);

test('PM: a user in the time zone of the site keeps the clock of the computer', () => {
    const n = new Date();
    const here = [pad(n.getHours()) + ':' + pad(n.getMinutes()), pad(new Date(n.getTime() + 60000).getHours()) + ':' + pad(new Date(n.getTime() + 60000).getMinutes())];
    assert.ok(here.includes(nowHM({ now: '03:04', tz_differs: false, _at: Date.now() })));
    assert.ok(here.includes(nowHM({ now: '03:04' })), 'a server that says nothing about time zones');
});

test('PM: a user in another time zone gets the clock the server stamped, counted on', () => {
    assert.strictEqual(nowHM({ now: '19:30', tz_differs: true, _at: Date.now() }), '19:30');
    assert.strictEqual(nowHM({ now: '19:30', tz_differs: true, _at: Date.now() - 45 * 60000 }), '20:15');
    assert.strictEqual(nowHM({ now: '9:05', tz_differs: true, _at: Date.now() - 5 * 60000 }), '09:10');
    // the day D.today names does not roll over under the reader
    assert.strictEqual(nowHM({ now: '23:50', tz_differs: true, _at: Date.now() - 30 * 60000 }), '23:59');
});

test('PM: the data carries when it arrived (unpackRows)', () => {
    const core = read('project-management/ExmentPM/resources/js/src/core.js');
    const unpackRows = new Function(functionSource(core, 'unpackRows') + '; return unpackRows;')();
    const data = { issues: [] };
    const before = Date.now();
    unpackRows(data);
    assert.ok(data._at >= before && data._at <= Date.now());
});

test('PM: the form shows the times the server formatted for the user', () => {
    const fields = read('project-management/ExmentPM/resources/js/src/form-fields.js');
    assert.ok(fields.includes('nedFormatMetaDate(row.created_at_shown || row.created_at)'));
    assert.ok(fields.includes('nedFormatMetaDate(row.updated_at_shown || row.updated_at)'));
    const writes = read('project-management/ExmentPM/resources/js/src/writes.js');
    assert.ok(/answer\.updated_at_shown\) \|\| \(D\.tz_differs \? '' : issue\.ua\)/.test(writes), 'the list row never rebuilds a shown time from a stored one for such a user');
});

// ---- Groupware: the dates of the web mail -----------------------------------
const mail = read('groupware/ExmentGroupware/resources/views/mail.blade.php');
function mailWhen(timezone) {
    const from = mail.indexOf('var TZ_FMT = null;');
    const to = mail.indexOf('function size(n)');
    assert.ok(from >= 0 && to > from, 'the date code of mail.blade.php');
    return new Function('GWM_CFG', mail.slice(from, to) + '; return when;')({ timezone: timezone });
}

test('Mail: the dates are the days and hours of the user (New York)', () => {
    const when = mailWhen('America/New_York');
    // 2026-11-24 09:30 in Tokyo is 2026-11-23 19:30 in New York (a Monday)
    assert.strictEqual(when('2026-11-24T09:30:00+09:00', true), '2026/11/23（月） 19:30');
    // summer time: 2026-07-01 09:30 in Tokyo is 2026-06-30 20:30 in New York (a Tuesday)
    assert.strictEqual(when('2026-07-01T09:30:00+09:00', true), '2026/6/30（火） 20:30');
    // midnight is 00, not 24
    assert.strictEqual(when('2026-11-24T14:05:00+09:00', true), '2026/11/24（火） 00:05');
});

test('Mail: the dates of a user in Tokyo, whatever the computer is set to', () => {
    const when = mailWhen('Asia/Tokyo');
    assert.strictEqual(when('2026-11-24T09:30:00+09:00', true), '2026/11/24（火） 09:30');
    assert.strictEqual(when('2025-03-05T23:59:00+09:00'), '2025/3/5', 'another year: the date alone');
});

test('Mail: a mail of today shows its time, in the time zone of the user', () => {
    const when = mailWhen('America/New_York');
    const now = new Date();
    const parts = {};
    new Intl.DateTimeFormat('en-US', { timeZone: 'America/New_York', hourCycle: 'h23', hour: 'numeric', minute: 'numeric' })
        .formatToParts(now).forEach((x) => { parts[x.type] = x.value; });
    assert.strictEqual(when(now.toISOString()), pad(Number(parts.hour) % 24) + ':' + pad(Number(parts.minute)));
});

test('Mail: without a time zone from Exment (an older core) the browser\'s is used, as before', () => {
    const when = mailWhen('');
    // the process is in Europe/Berlin: 09:30 in Tokyo is 01:30 there
    assert.strictEqual(when('2026-11-24T09:30:00+09:00', true), '2026/11/24（火） 01:30');
    assert.strictEqual(mailWhen('No/Such_Zone')('2026-11-24T09:30:00+09:00', true), '2026/11/24（火） 01:30', 'an unknown zone falls back too');
    assert.strictEqual(when(''), '');
    assert.strictEqual(when('not a date'), '');
});

if (failed) {
    console.log('\n' + failed + ' failed');
    process.exit(1);
}
console.log('\nall passed');
