{{-- Exment Plugin Groupware: the calendar (ISS02947). Day, week, month, list, and people side by side; the event dialog and its form. Everything is read from cal/api. --}}
<script>
var GWCAL_CFG = @json($cfg);
</script>
@verbatim
<div class="gwcal" id="gwcal">
<style>
.gwcal{--line:#e3e6ea;--line2:#eef0f3;--ink:#1f2328;--sub:#5b6470;--faint:#8b939e;--box:#fff;--box2:#f7f8fa;--hover:#f1f4f8;--acc:#2563c9;--acc-soft:#e6effc;--red:#d23c3c;--hol:#fdf0f0;--off:#f4f5f7;--busy:#d5d9de;color:var(--ink);font-size:13px;line-height:1.45;max-width:100%;min-width:0}
.gwcal *{box-sizing:border-box}
.gwcal [hidden]{display:none!important}
.gwcal button,.gwcal input,.gwcal select,.gwcal textarea{font:inherit;color:inherit}
.gwcal .gb{display:inline-flex;align-items:center;justify-content:center;gap:5px;height:32px;padding:0 11px;border:1px solid var(--line);border-radius:7px;background:var(--box);color:var(--ink);cursor:pointer;white-space:nowrap}
.gwcal .gb:hover{background:var(--hover)}
.gwcal .gb.sq{width:32px;padding:0}
.gwcal .gb[aria-pressed="true"]{background:var(--acc-soft);border-color:var(--acc);color:var(--acc);font-weight:600}
.gwcal .gb.danger{color:var(--red)}
.gwcal .gbp{display:inline-flex;align-items:center;justify-content:center;gap:6px;height:32px;padding:0 14px;border:0;border-radius:7px;background:var(--acc);color:#fff;font-weight:600;cursor:pointer;white-space:nowrap}
.gwcal .gbp:hover{filter:brightness(1.06)}
.gwcal .gbp:disabled{opacity:.6;cursor:default}
.gwcal button:focus-visible,.gwcal a:focus-visible,.gwcal input:focus-visible,.gwcal select:focus-visible,.gwcal textarea:focus-visible{outline:2px solid var(--acc);outline-offset:1px}
/* toolbar */
.gwcal-bar{display:flex;flex-wrap:wrap;align-items:center;gap:8px;margin-bottom:10px}
.gwcal-nav,.gwcal-views{display:flex;gap:4px;flex-wrap:wrap}
.gwcal-label{flex:1 1 160px;min-width:0;margin:0;font-size:17px;font-weight:700;line-height:1.3;overflow-wrap:anywhere}
.gwcal-pbar{display:flex;flex-wrap:wrap;align-items:flex-start;gap:8px;margin-bottom:10px;padding:8px;border:1px solid var(--line);border-radius:8px;background:var(--box2)}
.gwcal-pbar .gwcal-pick{flex:1 1 240px;min-width:0}
.gwcal-err{margin:0 0 10px;padding:9px 12px;border-radius:8px;background:#fdecec;color:#a52a2a;border:1px solid #f5c2c2}
.gwcal-body{position:relative;background:var(--box);border:1px solid var(--line);border-radius:10px;overflow:hidden;min-width:0}
.gwcal-empty{padding:30px 16px;text-align:center;color:var(--faint)}
/* events */
.gwcal-ev{display:flex;align-items:baseline;gap:4px;width:100%;min-width:0;margin:0 0 2px;padding:1px 5px;border:0;border-left:3px solid var(--acc);border-radius:4px;background:var(--acc-soft);color:var(--ink);font-size:12px;line-height:1.35;text-align:left;cursor:pointer;overflow:hidden;white-space:nowrap}
.gwcal-ev:hover{filter:brightness(.97)}
.gwcal-ev .tm{flex:0 0 auto;color:var(--sub);font-variant-numeric:tabular-nums}
.gwcal-ev .ti{flex:1 1 auto;min-width:0;overflow:hidden;text-overflow:ellipsis}
.gwcal-ev .fa{flex:0 0 auto;font-size:10px;color:var(--faint)}
.gwcal-ev.allday{background:var(--acc);color:#fff;border-left-color:var(--acc)}
.gwcal-ev.allday .fa,.gwcal-ev.allday .tm{color:rgba(255,255,255,.8)}
.gwcal-ev.tentative{background:var(--box);border:1px dashed var(--acc);border-left:3px solid var(--acc)}
.gwcal-ev.declined .ti{text-decoration:line-through;color:var(--faint)}
.gwcal-ev.busy{background:var(--busy);border-left-color:#9aa1aa;color:#3d434b;cursor:default}
.gwcal-ev.busy:hover{filter:none}
/* ExmentPM (part 4): the project's colour on the left, the bar light */
.gwcal-ev.pm{background:var(--box2);color:var(--ink);border-left:4px solid var(--acc)}
.gwcal-ev.pm .fa{color:var(--sub)}
.gwcal-ev.pm.sched{background:var(--box);border:1px dashed var(--line);border-left:4px solid var(--acc)}
.gwcal-ev.pm.done .ti{text-decoration:line-through;color:var(--faint)}
.gwcal-pmsel{height:32px;max-width:220px;padding:0 8px;border:1px solid var(--line);border-radius:7px;background:var(--box)}
.gwcal-pmdot{display:inline-block;width:10px;height:10px;margin-right:6px;border-radius:5px;vertical-align:middle}
/* month */
.gwcal-mhead,.gwcal-mrow{display:grid;grid-template-columns:repeat(7,minmax(0,1fr))}
.gwcal-mhead div{padding:6px 4px;text-align:center;font-size:12px;font-weight:600;color:var(--sub);border-bottom:1px solid var(--line)}
.gwcal-mrow{border-bottom:1px solid var(--line2)}
.gwcal-mrow:last-child{border-bottom:0}
.gwcal-mc{min-height:104px;min-width:0;padding:3px;border-right:1px solid var(--line2);cursor:pointer;overflow:hidden}
.gwcal-mc:last-child{border-right:0}
.gwcal-mc.out{background:var(--box2)}
.gwcal-mc.out .gwcal-dn{color:var(--faint)}
.gwcal-mtop{display:flex;align-items:center;gap:4px;min-width:0;margin-bottom:2px}
.gwcal-dn{flex:0 0 auto;min-width:24px;height:24px;padding:0 4px;border:0;border-radius:12px;background:none;font-weight:600;cursor:pointer}
.gwcal-dn:hover{background:var(--hover)}
.gwcal .today .gwcal-dn{background:var(--acc);color:#fff}
.gwcal-hl{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:11px;color:var(--red)}
.gwcal-hl.work{color:var(--sub)}
.gwcal .hol{background:var(--hol)}
.gwcal .off{background:var(--off)}
.gwcal .sun .gwcal-dn,.gwcal .hol .gwcal-dn{color:var(--red)}
.gwcal .today.sun .gwcal-dn,.gwcal .today.hol .gwcal-dn{color:#fff}
.gwcal-more{display:block;width:100%;padding:0 5px;border:0;background:none;color:var(--sub);font-size:11.5px;text-align:left;cursor:pointer}
.gwcal-more:hover{color:var(--acc);text-decoration:underline}
/* hour grid */
.gwcal-tg{--gut:52px}
.gwcal-tgrow{display:grid;min-width:0}
.gwcal-tghead{border-bottom:1px solid var(--line)}
.gwcal-dh{min-width:0;padding:5px 3px;text-align:center;border-left:1px solid var(--line2);overflow:hidden}
.gwcal-dh .w{display:block;font-size:11.5px;color:var(--sub)}
.gwcal-dh .gwcal-dn{font-size:15px;height:28px;min-width:28px;border-radius:14px}
.gwcal-dh .gwcal-hl{display:block}
.gwcal-ad{border-bottom:1px solid var(--line);background:var(--box2)}
.gwcal-ad>div{min-width:0;padding:2px;border-left:1px solid var(--line2);min-height:24px}
.gwcal-gut{font-size:11px;color:var(--faint);text-align:right;padding:4px 6px 0 0;border-left:0!important}
.gwcal-scroll{max-height:min(70vh,640px);overflow-y:auto;overflow-x:hidden}
.gwcal-tgbody{position:relative}
.gwcal-hours{position:relative}
.gwcal-hours span{position:absolute;right:6px;transform:translateY(-50%);font-size:11px;color:var(--faint);font-variant-numeric:tabular-nums}
.gwcal-col{position:relative;min-width:0;border-left:1px solid var(--line2);background-image:repeating-linear-gradient(to bottom,var(--line2) 0,var(--line2) 1px,transparent 1px,transparent 44px);cursor:pointer}
.gwcal-col .gwcal-ev{position:absolute;margin:0;flex-direction:column;align-items:stretch;gap:0;white-space:normal;padding:2px 4px;border:1px solid #fff;border-left:3px solid var(--acc)}
.gwcal-col .gwcal-ev.busy{border-left-color:#9aa1aa}
.gwcal-col .gwcal-ev .ti{white-space:normal;overflow-wrap:anywhere}
.gwcal-now{position:absolute;left:0;right:0;height:0;border-top:2px solid var(--red);z-index:3;pointer-events:none}
.gwcal-now::before{content:"";position:absolute;left:-4px;top:-5px;width:8px;height:8px;border-radius:50%;background:var(--red)}
/* list */
.gwcal-lday{border-bottom:1px solid var(--line2)}
.gwcal-lday:last-child{border-bottom:0}
.gwcal-lhd{display:flex;align-items:center;flex-wrap:wrap;gap:8px;padding:7px 12px;background:var(--box2);font-weight:600}
.gwcal-lhd.today{color:var(--acc)}
.gwcal-lrow{display:flex;align-items:flex-start;gap:10px;padding:6px 12px}
.gwcal-lrow .tm{flex:0 0 96px;color:var(--sub);font-size:12px;font-variant-numeric:tabular-nums;padding-top:1px}
.gwcal-lrow .gwcal-ev{flex:1 1 auto;margin:0;font-size:13px;padding:3px 8px}
/* people */
.gwcal-pp{--name:150px}
.gwcal-pprow{display:grid;grid-template-columns:var(--name) repeat(var(--n),minmax(0,1fr));border-bottom:1px solid var(--line2);min-width:0}
.gwcal-pprow:last-child{border-bottom:0}
.gwcal-pphead{border-bottom:1px solid var(--line)}
.gwcal-ppname{display:flex;align-items:center;gap:4px;min-width:0;padding:4px 8px;font-weight:600}
.gwcal-ppname .n{flex:1 1 auto;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.gwcal-ppcell{position:relative;min-width:0;height:46px;border-left:1px solid var(--line2);overflow:hidden}
.gwcal-ppcell.ticks{background-image:repeating-linear-gradient(to right,var(--line2) 0,var(--line2) 1px,transparent 1px,transparent 12.5%)}
.gwcal-ppcell .gwcal-ev{position:absolute;top:14px;bottom:4px;margin:0;padding:1px 3px;min-width:3px;font-size:11px;border:0;border-left:2px solid var(--acc)}
.gwcal-ppcell .gwcal-ev.busy{border-left-color:#9aa1aa}
.gwcal-ppcell .gwcal-ev.allday{top:2px;bottom:auto;height:10px;left:0;right:0;width:auto;padding:0;font-size:0}
.gwcal-pptick{position:relative;height:30px;border-left:1px solid var(--line2)}
.gwcal-pptick span{position:absolute;top:8px;transform:translateX(-50%);font-size:11px;color:var(--faint)}
/* picker */
.gwcal-pick{position:relative}
.gwcal-chips{display:flex;flex-wrap:wrap;gap:5px;margin-bottom:5px}
.gwcal-chips:empty{display:none}
.gwcal-chip{display:inline-flex;align-items:center;gap:2px;max-width:100%;padding:2px 2px 2px 10px;border:1px solid var(--line);border-radius:999px;background:var(--box);font-size:12px}
.gwcal-chip .n{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.gwcal-chip button{flex:0 0 auto;width:22px;height:22px;border:0;border-radius:50%;background:none;color:var(--faint);cursor:pointer}
.gwcal-chip button:hover{background:var(--hover);color:var(--red)}
.gwcal-pick input{width:100%;height:32px;padding:0 10px;border:1px solid var(--line);border-radius:7px;background:var(--box)}
.gwcal-res{position:absolute;left:0;right:0;top:100%;z-index:20;max-height:220px;overflow:auto;margin-top:2px;padding:4px;border:1px solid var(--line);border-radius:8px;background:var(--box);box-shadow:0 6px 20px rgba(16,24,40,.15)}
.gwcal-res button{display:block;width:100%;padding:6px 8px;border:0;border-radius:5px;background:none;text-align:left;cursor:pointer;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.gwcal-res button:hover,.gwcal-res button:focus{background:var(--hover)}
.gwcal-res .none{padding:6px 8px;color:var(--faint)}
/* dialog */
.gwcal-modal{position:fixed;inset:0;z-index:1050;display:flex;align-items:flex-start;justify-content:center;padding:40px 12px 12px;background:rgba(16,24,40,.45);overflow-y:auto}
.gwcal-dlg{width:100%;max-width:560px;min-width:0;background:var(--box);border-radius:12px;box-shadow:0 16px 48px rgba(16,24,40,.3)}
.gwcal-dhead{display:flex;align-items:flex-start;gap:8px;padding:14px 14px 8px 18px}
.gwcal-dhead h3{flex:1;min-width:0;margin:0;font-size:17px;font-weight:700;line-height:1.4;overflow-wrap:anywhere}
.gwcal-dbody{padding:4px 18px 12px}
.gwcal-drow{display:flex;gap:10px;margin:6px 0;min-width:0}
.gwcal-drow>.fa{flex:0 0 16px;margin-top:3px;color:var(--faint);text-align:center}
.gwcal-drow>div{flex:1;min-width:0;overflow-wrap:anywhere}
.gwcal-notes{white-space:pre-wrap}
.gwcal-badge{display:inline-block;margin:2px 6px 0 0;padding:0 7px;border-radius:999px;background:var(--box2);border:1px solid var(--line);color:var(--sub);font-size:11.5px}
.gwcal-att{list-style:none;margin:0;padding:0}
.gwcal-att li{display:flex;align-items:center;gap:8px;padding:2px 0;min-width:0}
.gwcal-att .n{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.gwcal-att .r{flex:0 0 auto;font-size:11.5px;padding:0 7px;border-radius:999px;background:var(--box2);color:var(--sub)}
.gwcal-att .r.yes{background:#e3f5ec;color:#0f6b46}
.gwcal-att .r.no{background:#fdecec;color:#a52a2a}
.gwcal-att .r.maybe{background:#fff4dc;color:#7a5400}
.gwcal-reply{display:flex;flex-wrap:wrap;gap:6px;margin-top:4px}
.gwcal-call{display:inline-flex;align-items:center;gap:6px;color:var(--acc);font-weight:600}
.gwcal-dfoot{display:flex;flex-wrap:wrap;align-items:center;gap:8px;padding:10px 18px 16px;border-top:1px solid var(--line2)}
.gwcal-dfoot .sp{flex:1}
.gwcal-q{margin:4px 0 6px;font-size:14px}
/* rooms and equipment (part 3) */
.gwcal-rooms{display:flex;flex-wrap:wrap;gap:4px 14px}
.gwcal-rooms label{display:inline-flex;align-items:center;gap:6px;font-weight:400;color:inherit;font-size:13px;cursor:pointer}
.gwcal-clash{margin:0 0 10px;padding:9px 12px;border-radius:8px;background:#fff6e0;color:#7a5200;border:1px solid #f0d58a}
.gwcal-clash ul{margin:6px 0 8px;padding-left:18px}
.gwcal-rlist{list-style:none;margin:0;padding:0}
.gwcal-rlist li{padding:8px 0;border-bottom:1px solid var(--line2)}
.gwcal-rlist .n{font-weight:600}
.gwcal-rlist .b{display:block;margin-top:3px;font-size:12px;color:var(--sub)}
.gwcal-rlist .off{color:var(--sub)}
.gwcal-rows{display:flex;flex-wrap:wrap;gap:8px}
.gwcal-rows>.gwcal-f{flex:1 1 140px}
/* form */
.gwcal-f{display:block;margin:0 0 10px;min-width:0}
.gwcal-f>label,.gwcal-f>.lb{display:block;margin:0 0 3px;font-weight:600;color:var(--sub);font-size:12px}
.gwcal-f input[type=text],.gwcal-f input[type=date],.gwcal-f input[type=time],.gwcal-f select,.gwcal-f textarea{width:100%;min-width:0;height:34px;padding:0 9px;border:1px solid var(--line);border-radius:7px;background:var(--box)}
.gwcal-f textarea{height:auto;min-height:76px;padding:6px 9px;resize:vertical}
.gwcal-when{display:grid;grid-template-columns:minmax(0,1.3fr) minmax(0,1fr);gap:6px}
.gwcal-chk{display:inline-flex;align-items:center;gap:6px;margin:0 0 10px;font-weight:600;cursor:pointer}
.gwcal-ferr{margin:0 0 10px;padding:8px 10px;border-radius:7px;background:#fdecec;color:#a52a2a;border:1px solid #f5c2c2}
.gwcal-toast{position:fixed;left:50%;bottom:24px;transform:translateX(-50%);z-index:1060;max-width:calc(100vw - 32px);padding:9px 16px;border-radius:8px;background:#1f2937;color:#fff;box-shadow:0 6px 20px rgba(16,24,40,.25)}
.gwcal-toast.err{background:#a52a2a}
@media (max-width:640px){
  .gwcal-label{flex-basis:100%;order:-1;font-size:16px}
  .gwcal-bar .gwcal-new{margin-left:auto}
  .gwcal .gb{padding:0 9px}
  .gwcal-mc{min-height:70px;padding:2px 1px}
  .gwcal-mc .gwcal-ev{padding:0 2px;font-size:10.5px;border-left-width:2px}
  .gwcal-mc .gwcal-ev .tm,.gwcal-mc .gwcal-ev .fa{display:none}
  .gwcal-hl{font-size:10px}
  .gwcal-tg{--gut:40px}
  .gwcal-dh .w{font-size:10.5px}
  .gwcal-col .gwcal-ev{padding:1px 2px;font-size:10.5px}
  .gwcal-col .gwcal-ev .tm{display:none}
  .gwcal-lrow .tm{flex-basis:78px}
  .gwcal-pp{--name:84px}
  .gwcal-ppname{padding:4px;font-size:12px}
  .gwcal-modal{padding:8px}
}
</style>
<div class="gwcal-bar" id="gwcal-bar"></div>
<div class="gwcal-pbar" id="gwcal-pbar" hidden></div>
<div class="gwcal-err" id="gwcal-err" role="alert" hidden></div>
<div class="gwcal-body" id="gwcal-body"></div>
<div class="gwcal-modal" id="gwcal-modal" hidden><div class="gwcal-dlg" id="gwcal-dlg" role="dialog" aria-modal="true" aria-labelledby="gwcal-dlg-title"></div></div>
<div class="gwcal-toast" id="gwcal-toast" role="status" hidden></div>
</div>

<script>
(function () {
'use strict';
var C = window.GWCAL_CFG || {}, root = document.getElementById('gwcal');
if (!root) { return; }
var L = C.lang && typeof C.lang === 'object' ? C.lang : {};
var U = C.urls || {};
var LOC = String(document.documentElement.getAttribute('lang') || 'ja').replace('_', '-');
var HOUR = 44;
var VIEWS = ['day', 'week', 'month', 'list', 'people'];
var $ = function (id) { return document.getElementById(id); };

// ---- pure helpers (groupware/tools/test-gw-calendar.js reads these out) ----------
function t(k, n) {
    var v = L[k];
    v = typeof v === 'string' ? v : k;
    return n == null ? v : v.replace(':n', String(n));
}
function pad(n) { return (n < 10 ? '0' : '') + n; }
function dayKey(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); }
function parseDay(k) {
    var m = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(k || ''));
    return m ? new Date(+m[1], +m[2] - 1, +m[3]) : null;
}
function addDays(d, n) { return new Date(d.getFullYear(), d.getMonth(), d.getDate() + n); }
function startOfDay(d) { return new Date(d.getFullYear(), d.getMonth(), d.getDate()); }
// weeks start on Monday
function startOfWeek(d) { var x = startOfDay(d); return addDays(x, -((x.getDay() + 6) % 7)); }
// The days a view shows: from (local midnight), to (exclusive), the days, the weeks of a month grid.
function periodRange(view, anchor, span) {
    var a = startOfDay(anchor), from, to;
    if (view === 'people') { view = span === 'day' ? 'day' : 'week'; }
    if (view === 'day') {
        from = a; to = addDays(a, 1);
    } else if (view === 'week') {
        from = startOfWeek(a); to = addDays(from, 7);
    } else if (view === 'month') {
        from = startOfWeek(new Date(a.getFullYear(), a.getMonth(), 1));
        to = addDays(startOfWeek(new Date(a.getFullYear(), a.getMonth() + 1, 0)), 7);
    } else {
        from = new Date(a.getFullYear(), a.getMonth(), 1); to = new Date(a.getFullYear(), a.getMonth() + 1, 1);
    }
    var days = [];
    for (var d = from; d < to; d = addDays(d, 1)) { days.push(d); }
    return { from: from, to: to, days: days, weeks: Math.ceil(days.length / 7) };
}
function shiftAnchor(view, anchor, dir, span) {
    if (view === 'day' || (view === 'people' && span === 'day')) { return addDays(anchor, dir); }
    if (view === 'week' || view === 'people') { return addDays(anchor, 7 * dir); }
    return new Date(anchor.getFullYear(), anchor.getMonth() + dir, 1);
}
// 'Y-m-d H:i:s' (UTC, as the server sends it) to a Date
function parseUtc(s) {
    var m = /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})(?::(\d{2}))?/.exec(String(s || ''));
    return m ? new Date(Date.UTC(+m[1], +m[2] - 1, +m[3], +m[4], +m[5], +(m[6] || 0))) : null;
}
function toUtc(d) {
    return d.getUTCFullYear() + '-' + pad(d.getUTCMonth() + 1) + '-' + pad(d.getUTCDate()) + ' ' + pad(d.getUTCHours()) + ':' + pad(d.getUTCMinutes()) + ':' + pad(d.getUTCSeconds());
}
// An item's start and end as local Dates. All day: the days themselves (the end is the day after the last).
function itemSpan(it) {
    if (it.all_day) {
        var s = parseDay(it.start), e = parseDay(it.end);
        if (!e || e <= s) { e = addDays(s, 1); }
        return { s: s, e: e };
    }
    var a = parseUtc(it.start), b = parseUtc(it.end) || a;
    return { s: a, e: b < a ? a : b };
}
function overlapsDay(it, day) {
    var sp = itemSpan(it), d0 = startOfDay(day), d1 = addDays(d0, 1);
    return sp.s < d1 && (sp.e > d0 || (+sp.e === +sp.s && sp.s >= d0));
}
function fmtTime(d) { return pad(d.getHours()) + ':' + pad(d.getMinutes()); }
function fmtRange(it) {
    if (it.all_day) { return t('f_all_day'); }
    var sp = itemSpan(it);
    return fmtTime(sp.s) + '–' + fmtTime(sp.e);
}
// The timed parts of one day: minutes from its midnight (0..1440), clipped to the day.
function daySegments(items, day) {
    var d0 = startOfDay(day), d1 = addDays(d0, 1), out = [];
    items.forEach(function (it) {
        if (it.all_day || !overlapsDay(it, day)) { return; }
        var sp = itemSpan(it);
        var s = sp.s < d0 ? 0 : sp.s.getHours() * 60 + sp.s.getMinutes();
        var e = sp.e >= d1 ? 1440 : sp.e.getHours() * 60 + sp.e.getMinutes();
        out.push({ it: it, s: s, e: Math.max(s, e) });
    });
    return out;
}
// Side by side: each segment gets col (0..) and cols (the columns of the group it overlaps with).
// minLen: the shortest a segment is drawn, so tiny ones that look overlapping are laid out apart.
function layoutTimed(segs, minLen) {
    minLen = minLen || 0;
    segs.sort(function (a, b) { return a.s - b.s || b.e - a.e; });
    var group = [], ends = [], groupEnd = -Infinity;
    function flush() {
        group.forEach(function (x) { x.cols = ends.length; });
        group = []; ends = [];
    }
    segs.forEach(function (x) {
        var e = Math.max(x.e, x.s + minLen);
        if (x.s >= groupEnd) { flush(); groupEnd = -Infinity; }
        var c = 0;
        while (c < ends.length && ends[c] > x.s) { c++; }
        ends[c] = e; x.col = c; group.push(x);
        groupEnd = Math.max(groupEnd, e);
    });
    flush();
    return segs;
}
// The RRULE of a repeat choice. weekly: the day of the week of the start.
function buildRrule(choice, startKey, custom) {
    var BYDAY = ['SU', 'MO', 'TU', 'WE', 'TH', 'FR', 'SA'];
    switch (choice) {
        case 'daily': return 'FREQ=DAILY';
        case 'weekdays': return 'FREQ=WEEKLY;BYDAY=MO,TU,WE,TH,FR';
        case 'weekly': return 'FREQ=WEEKLY;BYDAY=' + BYDAY[(parseDay(startKey) || new Date()).getDay()];
        case 'monthly': return 'FREQ=MONTHLY';
        case 'yearly': return 'FREQ=YEARLY';
        case 'custom': return String(custom || '').trim().replace(/^RRULE:/i, '').toUpperCase();
        default: return '';
    }
}
// back from an RRULE to the choice of the form
function repeatChoice(rrule, startKey) {
    var r = String(rrule || '').trim().replace(/^RRULE:/i, '').toUpperCase();
    if (r === '') { return 'none'; }
    var all = ['daily', 'weekdays', 'weekly', 'monthly', 'yearly'];
    for (var i = 0; i < all.length; i++) {
        if (buildRrule(all[i], startKey) === r) { return all[i]; }
    }
    return 'custom';
}
// The form's values from the server's UTC times: local date and time, or the days (end = the last day).
function eventToForm(startUtc, endUtc, allDay) {
    if (allDay) {
        var s = parseDay(startUtc), e = parseDay(endUtc);
        var last = e && e > s ? addDays(e, -1) : s;
        return { all_day: true, sd: dayKey(s), st: '09:00', ed: dayKey(last), et: '10:00' };
    }
    var a = parseUtc(startUtc), b = parseUtc(endUtc) || a;
    return { all_day: false, sd: dayKey(a), st: fmtTime(a), ed: dayKey(b), et: fmtTime(b) };
}
// What the server takes: "YYYY-MM-DDTHH:MM" local (with tz), or the days when all day.
function formTimes(v) {
    if (v.all_day) { return { start: v.sd, end: v.ed || v.sd }; }
    return { start: v.sd + 'T' + v.st, end: (v.ed || v.sd) + 'T' + v.et };
}
// a local date and time of the form, in UTC ('Y-m-d H:i:s'); null when it cannot be read
function localToUtc(date, time) {
    var d = parseDay(date), m = /^(\d{1,2}):(\d{2})/.exec(String(time || ''));
    if (!d || !m) { return null; }
    return toUtc(new Date(d.getFullYear(), d.getMonth(), d.getDate(), +m[1], +m[2]));
}
// the lang key of what is wrong with the form, or null
function formCheck(v) {
    if (!String(v.title || '').trim()) { return 'err_title'; }
    if (!parseDay(v.sd) || (v.ed && !parseDay(v.ed))) { return 'err_time'; }
    if (v.all_day) { return (v.ed || v.sd) < v.sd ? 'err_time' : null; }
    var s = localToUtc(v.sd, v.st), e = localToUtc(v.ed || v.sd, v.et);
    if (s === null || e === null || e < s) { return 'err_time'; }
    return null;
}
function holidayLabel(h) {
    if (!h) { return ''; }
    return h.name || t({ holiday: 'holiday', off: 'company_off', work: 'company_work' }[h.kind] || 'holiday');
}
// ExmentPM's layer (part 4): the choice of the toolbar ('off', 'mine', 'projects', 'p<id>') as the query of cal/api/pm.
function pmQuery(sel) {
    if (sel === 'mine' || sel === 'projects') { return { filter: sel }; }
    var m = /^p(\d+)$/.exec(String(sel || ''));
    return m ? { filter: 'project', project: m[1] } : null;
}
// An item of ExmentPM: an issue (a bar, or its due date), a milestone, a date of a schedule with no issue yet.
function pmNode(it) {
    var n = document.createElement('button');
    n.type = 'button';
    n.className = 'gwcal-ev pm' + (it.pm === 'schedule' ? ' sched' : '') + (it.done ? ' done' : '');
    if (/^#[0-9a-fA-F]{6}$/.test(String(it.color || ''))) { n.style.borderLeftColor = it.color; }
    var ic = it.pm === 'milestone' ? 'fa-flag-checkered' : it.pm === 'schedule' ? 'fa-clock-o' : it.kind === 'bar' ? 'fa-tasks' : 'fa-flag';
    var i = document.createElement('i');
    i.className = 'fa ' + ic;
    i.setAttribute('aria-hidden', 'true');
    n.appendChild(i);
    var ti = document.createElement('span');
    ti.className = 'ti';
    ti.textContent = it.title || '';
    n.appendChild(ti);
    var what = it.pm === 'schedule' ? t('pm_pending') : it.pm === 'milestone' ? t('pm_milestone') : it.due ? t('pm_due') + ' ' + it.due : '';
    n.setAttribute('title', (it.title || '') + (what ? ' (' + what + ')' : '') + (it.project_name ? ' - ' + it.project_name : ''));
    return n;
}
// The rooms of a day: the rooms in use, and the ones taken out of use that still have bookings that day.
function roomsOfDay(rooms, events) {
    var out = [];
    (rooms || []).forEach(function (room) {
        var mine = (events || []).filter(function (e) { return (e.resources || []).indexOf(room.id) >= 0; });
        if (room.enabled || mine.length) { out.push({ room: room, events: mine, off: !room.enabled }); }
    });
    return out;
}
// One event as a node. A busy one is the busy label and nothing else (its time only in the tooltip).
function evNode(it, opts) {
    opts = opts || {};
    var n;
    if (it.pm) { return pmNode(it); }
    if (it.busy || it.id == null) {
        n = document.createElement('div');
        n.className = 'gwcal-ev busy';
        n.textContent = t('busy');
        n.setAttribute('title', t('busy') + ' ' + fmtRange(it));
        return n;
    }
    n = document.createElement('button');
    n.type = 'button';
    n.className = 'gwcal-ev' + (it.all_day ? ' allday' : '') + (it.mine === 'none' ? ' tentative' : '') + (it.mine === 'no' ? ' declined' : '');
    if (opts.time && !it.all_day) {
        var tm = document.createElement('span');
        tm.className = 'tm';
        tm.textContent = fmtTime(itemSpan(it).s);
        n.appendChild(tm);
    }
    var ti = document.createElement('span');
    ti.className = 'ti';
    ti.textContent = it.title || '';
    n.appendChild(ti);
    if (it.repeat) {
        var r = document.createElement('i');
        r.className = 'fa fa-repeat';
        r.setAttribute('aria-hidden', 'true');
        n.appendChild(r);
    }
    n.setAttribute('title', (it.title || '') + ' (' + fmtRange(it) + ')' + (it.location ? ' ' + it.location : ''));
    return n;
}

// ---- state ----------------------------------------------------------------
function stored(k) { try { return window.localStorage.getItem(k); } catch (e) { return null; } }
function store(k, v) { try { window.localStorage.setItem(k, v); } catch (e) { /* a convenience only */ } }
var NARROW = !!(window.matchMedia && window.matchMedia('(max-width: 640px)').matches);
var VKEY = NARROW ? 'gwcal.view.narrow' : 'gwcal.view';
var S = {
    view: VIEWS.indexOf(stored(VKEY)) >= 0 ? stored(VKEY) : (NARROW ? 'list' : 'week'),
    span: stored('gwcal.span') === 'day' || stored('gwcal.span') === 'week' ? stored('gwcal.span') : (NARROW ? 'day' : 'week'),
    anchor: startOfDay(new Date()),
    people: [], names: {}, items: [], byPerson: {}, hol: {}, seq: 0, range: null,
    // ExmentPM's layer: off at first, the choice kept in the browser
    pm: C.pm && pmQuery(stored('gwcal.pm')) ? stored('gwcal.pm') : 'off'
};
(function () {
    var ids = [];
    try { ids = JSON.parse(stored('gwcal.people') || '[]'); } catch (e) { ids = []; }
    S.people = (Array.isArray(ids) ? ids : []).map(function (x) { return parseInt(x, 10); }).filter(function (x) { return x > 0; }).slice(0, 20);
    if (!S.people.length && C.me) { S.people = [C.me]; }
})();
function browserTz() {
    try { return Intl.DateTimeFormat().resolvedOptions().timeZone || C.tz || 'Asia/Tokyo'; } catch (e) { return C.tz || 'Asia/Tokyo'; }
}

// ---- DOM ------------------------------------------------------------------
function el(tag, cls, text) {
    var n = document.createElement(tag);
    if (cls) { n.className = cls; }
    if (text != null) { n.textContent = text; }
    return n;
}
function btn(cls, text, fn, label) {
    var b = el('button', cls, text);
    b.type = 'button';
    if (label) { b.setAttribute('aria-label', label); b.title = label; }
    if (fn) { b.addEventListener('click', fn); }
    return b;
}
function icon(name) { var i = el('i', 'fa ' + name); i.setAttribute('aria-hidden', 'true'); return i; }
function clear(n) { while (n.firstChild) { n.removeChild(n.firstChild); } }
function fmtDate(d, o) { try { return new Intl.DateTimeFormat(LOC, o).format(d); } catch (e) { return dayKey(d); } }
var D_LONG = { year: 'numeric', month: 'short', day: 'numeric', weekday: 'short' };
function nameOf(id) {
    if (id === C.me) { return C.me_name || t('people_me'); }
    return S.names[id] || ('#' + id);
}
// Exment moves between pages without reloading (pjax): listeners on document and window are replaced.
function listen(target, type, fn) {
    var key = '__gwcal_' + type;
    if (target[key]) { target.removeEventListener(type, target[key]); }
    target[key] = fn;
    target.addEventListener(type, fn);
}

// ---- the server -------------------------------------------------------------
function api(url, params, post) {
    var o = { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-CSRF-TOKEN': C.csrf || '' }, credentials: 'same-origin' };
    if (post) {
        var fd = new FormData();
        Object.keys(params || {}).forEach(function (k) {
            var v = params[k];
            if (Array.isArray(v)) { v.forEach(function (x) { fd.append(k + '[]', x); }); } else if (v != null) { fd.append(k, v); }
        });
        fd.append('_token', C.csrf || '');
        o.method = 'POST';
        o.body = fd;
    } else if (params) {
        var q = Object.keys(params).filter(function (k) { return params[k] != null && params[k] !== ''; })
            .map(function (k) { return encodeURIComponent(k) + '=' + encodeURIComponent(params[k]); }).join('&');
        if (q) { url += (url.indexOf('?') < 0 ? '?' : '&') + q; }
    }
    return fetch(url, o).then(function (r) {
        if (r.status === 419) { return { ok: false, status: 419, message: t('err_expired') }; }
        return r.json().then(function (j) {
            if (!j || typeof j !== 'object') { return { ok: false, status: r.status, message: t('err_failed') }; }
            j.status = r.status;
            if (!j.ok && !j.message) { j.message = t('err_failed'); }
            return j;
        }, function () { return { ok: false, status: r.status, message: t('err_failed') }; });
    }, function () { return { ok: false, message: t('err_network') }; });
}
function ensureNames(ids) {
    var miss = ids.filter(function (id, i) { return id && id !== C.me && !S.names[id] && ids.indexOf(id) === i; });
    if (!miss.length) { return Promise.resolve(); }
    return api(U.people, { ids: miss.join(',') }).then(function (r) {
        (r.people || []).forEach(function (p) { S.names[p.id] = p.name; });
    });
}
var toastTimer;
function toast(text, err) {
    var n = $('gwcal-toast');
    n.textContent = text;
    n.className = 'gwcal-toast' + (err ? ' err' : '');
    n.hidden = false;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () { n.hidden = true; }, err ? 7000 : 3000);
}
function showError(m) { var n = $('gwcal-err'); n.textContent = m || ''; n.hidden = !m; }

// ---- a person picker (the people side by side, and the attendees) -------------------
function makePicker(ids, ph, onChange) {
    var p = { ids: ids.slice(), node: el('div', 'gwcal-pick') };
    var chips = el('div', 'gwcal-chips'), inp = el('input'), res = el('div', 'gwcal-res');
    inp.type = 'text';
    inp.placeholder = ph;
    inp.setAttribute('aria-label', ph);
    inp.autocomplete = 'off';
    res.hidden = true;
    p.node.appendChild(chips); p.node.appendChild(inp); p.node.appendChild(res);
    function changed() { draw(); if (onChange) { onChange(p.ids.slice()); } }
    function draw() {
        clear(chips);
        p.ids.forEach(function (id) {
            var c = el('span', 'gwcal-chip');
            c.appendChild(el('span', 'n', nameOf(id)));
            var x = btn('', null, function () { p.ids = p.ids.filter(function (i) { return i !== id; }); changed(); inp.focus(); }, t('people_remove') + ': ' + nameOf(id));
            x.appendChild(icon('fa-times'));
            c.appendChild(x);
            chips.appendChild(c);
        });
    }
    var timer, qseq = 0;
    function search() {
        var q = inp.value.trim(), s = ++qseq;
        if (!q) { res.hidden = true; return; }
        api(U.people, { q: q }).then(function (r) {
            if (s !== qseq) { return; }
            clear(res);
            var list = (r.people || []).filter(function (x) { return p.ids.indexOf(x.id) < 0; });
            if (!list.length) { res.appendChild(el('div', 'none', r.ok === false ? r.message : t('people_none'))); }
            list.forEach(function (x) {
                S.names[x.id] = x.name;
                res.appendChild(btn('', x.name, function () {
                    p.ids.push(x.id); inp.value = ''; res.hidden = true; changed(); inp.focus();
                }));
            });
            res.hidden = false;
        });
    }
    inp.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(search, 250); });
    inp.addEventListener('keydown', function (e) {
        var first = res.querySelector('button');
        if (e.key === 'Enter') { e.preventDefault(); if (!res.hidden && first) { first.click(); } }
        if (e.key === 'ArrowDown' && !res.hidden && first) { e.preventDefault(); first.focus(); }
    });
    res.addEventListener('keydown', function (e) {
        var b = e.target;
        if (e.key === 'ArrowDown' && b.nextSibling) { e.preventDefault(); b.nextSibling.focus(); }
        if (e.key === 'ArrowUp') { e.preventDefault(); (b.previousSibling || inp).focus(); }
    });
    p.set = function (list) { p.ids = list.slice(); draw(); };
    p.draw = draw;
    draw();
    return p;
}

// ---- the toolbar -------------------------------------------------------------
var labelNode = el('h2', 'gwcal-label');
var viewBtns = {};
(function () {
    var bar = $('gwcal-bar');
    var nav = el('div', 'gwcal-nav');
    nav.appendChild(btn('gb', t('today'), function () { S.anchor = startOfDay(new Date()); load(); }));
    var pv = btn('gb sq', null, function () { S.anchor = shiftAnchor(S.view, S.anchor, -1, S.span); load(); }, t('prev'));
    pv.appendChild(icon('fa-chevron-left'));
    var nx = btn('gb sq', null, function () { S.anchor = shiftAnchor(S.view, S.anchor, 1, S.span); load(); }, t('next'));
    nx.appendChild(icon('fa-chevron-right'));
    nav.appendChild(pv); nav.appendChild(nx);
    var views = el('div', 'gwcal-views');
    views.setAttribute('role', 'group');
    VIEWS.forEach(function (v) {
        viewBtns[v] = btn('gb', t(v), function () { setView(v); });
        views.appendChild(viewBtns[v]);
    });
    var nw = btn('gbp gwcal-new', null, function () { openForm(null, null, null, newValues(S.view === 'day' ? S.anchor : null)); });
    nw.appendChild(icon('fa-plus'));
    nw.appendChild(document.createTextNode(t('new')));
    bar.appendChild(nav); bar.appendChild(labelNode); bar.appendChild(views);
    if (roomsUsable().length || C.resources_admin) {
        var rb = btn('gb', null, function () { openRooms(); });
        rb.appendChild(icon('fa-building-o'));
        rb.appendChild(document.createTextNode(' ' + t('rooms')));
        bar.appendChild(rb);
    }
    if (C.pm && U.pm) { bar.appendChild(pmSelect()); }
    bar.appendChild(nw);
})();
// ExmentPM's layer: not shown, my issues, my projects, one project (the ones the viewer may read)
function pmSelect() {
    var sel = el('select', 'gwcal-pmsel');
    sel.setAttribute('aria-label', t('pm_layer'));
    sel.title = t('pm_layer');
    var add = function (parent, value, text) { var o = el('option', '', text); o.value = value; parent.appendChild(o); };
    add(sel, 'off', t('pm_off'));
    add(sel, 'mine', t('pm_mine'));
    add(sel, 'projects', t('pm_projects'));
    var projects = C.pm.projects || [];
    if (projects.length) {
        var g = el('optgroup');
        g.label = t('pm_project_group');
        projects.forEach(function (p) { add(g, 'p' + p.id, p.name); });
        sel.appendChild(g);
    }
    sel.value = S.pm;
    if (sel.value !== S.pm) { S.pm = 'off'; sel.value = 'off'; }
    sel.addEventListener('change', function () { S.pm = sel.value; store('gwcal.pm', S.pm); load(); });
    return sel;
}
var peoplePicker = makePicker(S.people, t('people_add'), function (ids) {
    S.people = ids;
    store('gwcal.people', JSON.stringify(ids));
    load();
});
var spanBtns = {};
(function () {
    var pbar = $('gwcal-pbar');
    pbar.appendChild(peoplePicker.node);
    var g = el('div', 'gwcal-views');
    g.setAttribute('role', 'group');
    ['day', 'week'].forEach(function (s) {
        spanBtns[s] = btn('gb', t(s), function () { S.span = s; store('gwcal.span', s); load(); });
        g.appendChild(spanBtns[s]);
    });
    pbar.appendChild(g);
})();
function setView(v) { S.view = v; store(VKEY, v); load(); }
function setLabel(r) {
    var v = S.view === 'people' ? (S.span === 'day' ? 'day' : 'week') : S.view, s;
    if (v === 'day') {
        s = fmtDate(r.from, D_LONG);
    } else if (v === 'week') {
        s = fmtDate(r.from, { year: 'numeric', month: 'short', day: 'numeric' }) + ' – ' + fmtDate(addDays(r.to, -1), { month: 'short', day: 'numeric' });
    } else {
        s = fmtDate(S.anchor, { year: 'numeric', month: 'long' });
    }
    labelNode.textContent = s;
    VIEWS.forEach(function (x) { viewBtns[x].setAttribute('aria-pressed', x === S.view ? 'true' : 'false'); });
    ['day', 'week'].forEach(function (x) { spanBtns[x].setAttribute('aria-pressed', x === S.span ? 'true' : 'false'); });
    $('gwcal-pbar').hidden = S.view !== 'people';
}

// ---- loading ------------------------------------------------------------------
function load() {
    var seq = ++S.seq, r = periodRange(S.view, S.anchor, S.span);
    S.range = r;
    setLabel(r);
    var body = $('gwcal-body');
    clear(body);
    body.appendChild(el('div', 'gwcal-empty', t('loading')));
    var w = { from: r.from.toISOString(), to: r.to.toISOString() };
    var hol = api(U.holidays, { from: dayKey(r.from), to: dayKey(addDays(r.to, -1)) });
    var evs;
    if (S.view === 'people') {
        // one call a person: an event shows in the row of everyone it is of (owner or attendee)
        evs = Promise.all(S.people.map(function (id) { return api(U.events, { from: w.from, to: w.to, people: id }); })).then(function (rs) {
            var out = { ok: true, by: {} };
            rs.forEach(function (x, i) { if (!x.ok) { out.ok = false; out.message = x.message; } out.by[S.people[i]] = x.events || []; });
            return out;
        });
    } else {
        evs = api(U.events, { from: w.from, to: w.to, people: C.me });
    }
    var pq = C.pm && U.pm && S.view !== 'people' ? pmQuery(S.pm) : null;
    var pm = pq ? api(U.pm, { from: dayKey(r.from), to: dayKey(r.to), filter: pq.filter, project: pq.project }) : null;
    Promise.all([evs, hol, S.view === 'people' ? ensureNames(S.people) : null, pm]).then(function (res) {
        if (seq !== S.seq) { return; }
        var e = res[0], h = res[1], p = res[3];
        showError(e.ok ? (h.ok ? (p && !p.ok ? p.message : '') : h.message) : e.message);
        S.hol = h.ok && h.days && typeof h.days === 'object' ? h.days : {};
        S.items = S.view === 'people' ? [] : (e.events || []).concat(p && p.ok ? (p.events || []) : []);
        S.byPerson = S.view === 'people' ? (e.by || {}) : {};
        if (S.view === 'people') { peoplePicker.draw(); }
        draw();
    });
}
function draw() {
    var body = $('gwcal-body'), r = S.range;
    clear(body);
    if (S.view === 'month') { drawMonth(body, r); } else if (S.view === 'list') { drawList(body, r); } else if (S.view === 'people') { drawPeople(body, r); } else { drawGrid(body, r); }
}
function dayClass(d, inMonth) {
    var h = S.hol[dayKey(d)], c = '';
    if (dayKey(d) === dayKey(new Date())) { c += ' today'; }
    if (inMonth === false) { c += ' out'; }
    if (h && h.kind === 'holiday') { c += ' hol'; } else if (h && h.kind === 'off') { c += ' off'; }
    if (!h || h.kind !== 'work') { if (d.getDay() === 0) { c += ' sun off'; } else if (d.getDay() === 6) { c += ' sat off'; } }
    return c;
}
function holNode(d) {
    var h = S.hol[dayKey(d)];
    if (!h) { return null; }
    var n = el('span', 'gwcal-hl' + (h.kind === 'work' ? ' work' : ''), holidayLabel(h));
    n.title = holidayLabel(h);
    return n;
}
function sorted(list) {
    return list.slice().sort(function (a, b) { return (b.all_day ? 1 : 0) - (a.all_day ? 1 : 0) || (a.start < b.start ? -1 : a.start > b.start ? 1 : 0); });
}
function openable(n, it) {
    // a busy one opens nothing (and a click on it does not make a new event either)
    n.addEventListener('click', function (e) { e.stopPropagation(); if (n.tagName === 'BUTTON') { if (it.pm) { openPm(it); } else { openEvent(it); } } });
    return n;
}
function goDay(d) { S.view = 'day'; S.anchor = startOfDay(d); load(); }

// ---- month ------------------------------------------------------------------
function drawMonth(body, r) {
    var month = S.anchor.getMonth(), max = NARROW ? 2 : 3;
    var head = el('div', 'gwcal-mhead');
    for (var i = 0; i < 7; i++) { head.appendChild(el('div', '', fmtDate(r.days[i], { weekday: 'short' }))); }
    body.appendChild(head);
    for (var w = 0; w < r.weeks; w++) {
        var row = el('div', 'gwcal-mrow');
        r.days.slice(w * 7, w * 7 + 7).forEach(function (d) {
            var cell = el('div', 'gwcal-mc' + dayClass(d, d.getMonth() === month));
            var top = el('div', 'gwcal-mtop');
            var dn = btn('gwcal-dn', String(d.getDate()), function (e) { e.stopPropagation(); goDay(d); }, fmtDate(d, D_LONG));
            top.appendChild(dn);
            var hn = holNode(d);
            if (hn) { top.appendChild(hn); }
            cell.appendChild(top);
            var list = sorted(S.items.filter(function (it) { return overlapsDay(it, d); }));
            list.slice(0, list.length > max + 1 ? max : max + 1).forEach(function (it) { cell.appendChild(openable(evNode(it, { time: true }), it)); });
            if (list.length > max + 1) {
                cell.appendChild(btn('gwcal-more', t('more', list.length - max), function (e) { e.stopPropagation(); goDay(d); }));
            }
            cell.addEventListener('click', function () { openForm(null, null, null, newValues(d)); });
            row.appendChild(cell);
        });
        body.appendChild(row);
    }
}

// ---- day and week: the hour grid ---------------------------------------------------
var nowNodes = [];
function placeNow() {
    var n = new Date(), m = n.getHours() * 60 + n.getMinutes();
    nowNodes.forEach(function (x) { x.node.hidden = dayKey(x.day) !== dayKey(n); x.node.style.top = (m / 1440 * 100) + '%'; });
}
function drawGrid(body, r) {
    var cols = 'var(--gut) repeat(' + r.days.length + ',minmax(0,1fr))';
    var tg = el('div', 'gwcal-tg');
    var head = el('div', 'gwcal-tgrow gwcal-tghead');
    head.style.gridTemplateColumns = cols;
    head.appendChild(el('div', 'gwcal-gut'));
    r.days.forEach(function (d) {
        var h = el('div', 'gwcal-dh' + dayClass(d));
        h.appendChild(el('span', 'w', fmtDate(d, { weekday: 'short' })));
        h.appendChild(btn('gwcal-dn', String(d.getDate()), r.days.length > 1 ? function () { goDay(d); } : null, fmtDate(d, D_LONG)));
        var hn = holNode(d);
        if (hn) { h.appendChild(hn); }
        head.appendChild(h);
    });
    tg.appendChild(head);
    var ad = el('div', 'gwcal-tgrow gwcal-ad');
    ad.style.gridTemplateColumns = cols;
    ad.appendChild(el('div', 'gwcal-gut', t('f_all_day')));
    r.days.forEach(function (d) {
        var c = el('div', dayClass(d));
        S.items.filter(function (it) { return it.all_day && overlapsDay(it, d); }).forEach(function (it) { c.appendChild(openable(evNode(it), it)); });
        ad.appendChild(c);
    });
    tg.appendChild(ad);
    var scroll = el('div', 'gwcal-scroll');
    var grid = el('div', 'gwcal-tgrow gwcal-tgbody');
    grid.style.gridTemplateColumns = cols;
    grid.style.height = (24 * HOUR) + 'px';
    var hours = el('div', 'gwcal-hours');
    for (var h = 1; h < 24; h++) { var hs = el('span', '', pad(h) + ':00'); hs.style.top = (h * HOUR) + 'px'; hours.appendChild(hs); }
    grid.appendChild(hours);
    nowNodes = [];
    r.days.forEach(function (d) {
        var col = el('div', 'gwcal-col' + dayClass(d));
        layoutTimed(daySegments(S.items, d), 20).forEach(function (sg) {
            var n = openable(evNode(sg.it, { time: true }), sg.it);
            n.style.top = (sg.s / 1440 * 100) + '%';
            n.style.height = (Math.max(sg.e - sg.s, 20) / 1440 * 100) + '%';
            n.style.left = (sg.col / sg.cols * 100) + '%';
            n.style.width = (100 / sg.cols) + '%';
            col.appendChild(n);
        });
        var now = el('div', 'gwcal-now');
        col.appendChild(now);
        nowNodes.push({ node: now, day: d });
        col.addEventListener('click', function (e) {
            if (e.target !== col) { return; }
            var y = e.offsetY;
            openForm(null, null, null, newValues(d, Math.max(0, Math.min(23, Math.floor(y / HOUR)))));
        });
        grid.appendChild(col);
    });
    scroll.appendChild(grid);
    tg.appendChild(scroll);
    body.appendChild(tg);
    placeNow();
    var today = r.days.some(function (d) { return dayKey(d) === dayKey(new Date()); });
    scroll.scrollTop = Math.max(0, (today ? new Date().getHours() - 2 : 8) * HOUR);
}

// ---- list ---------------------------------------------------------------------
function drawList(body, r) {
    var any = false;
    r.days.forEach(function (d) {
        var list = sorted(S.items.filter(function (it) { return overlapsDay(it, d); }));
        var hn = holNode(d);
        if (!list.length && !hn) { return; }
        any = true;
        var sec = el('section', 'gwcal-lday');
        var hd = el('div', 'gwcal-lhd' + (dayKey(d) === dayKey(new Date()) ? ' today' : ''));
        hd.appendChild(el('span', '', fmtDate(d, D_LONG)));
        if (hn) { hd.appendChild(hn); }
        sec.appendChild(hd);
        list.forEach(function (it) {
            var row = el('div', 'gwcal-lrow');
            row.appendChild(el('span', 'tm', fmtRange(it)));
            row.appendChild(openable(evNode(it), it));
            sec.appendChild(row);
        });
        body.appendChild(sec);
    });
    if (!any) { body.appendChild(el('div', 'gwcal-empty', t('no_events'))); }
}

// ---- people side by side --------------------------------------------------------------
function drawPeople(body, r) {
    if (!S.people.length) { body.appendChild(el('div', 'gwcal-empty', t('people_empty'))); return; }
    var pp = el('div', 'gwcal-pp'), byDay = r.days.length === 1;
    pp.style.setProperty('--n', String(r.days.length));
    var head = el('div', 'gwcal-pprow gwcal-pphead');
    head.appendChild(el('div', 'gwcal-ppname'));
    if (byDay) {
        var tk = el('div', 'gwcal-pptick');
        for (var h = 0; h < 24; h += NARROW ? 6 : 3) { var s = el('span', '', String(h)); s.style.left = (h / 24 * 100) + '%'; if (h === 0) { s.style.transform = 'none'; } tk.appendChild(s); }
        head.appendChild(tk);
    } else {
        r.days.forEach(function (d) {
            var c = el('div', 'gwcal-dh' + dayClass(d));
            c.appendChild(el('span', 'w', fmtDate(d, { weekday: 'short' })));
            c.appendChild(btn('gwcal-dn', String(d.getDate()), function () { S.span = 'day'; S.anchor = d; store('gwcal.span', 'day'); load(); }, fmtDate(d, D_LONG)));
            head.appendChild(c);
        });
    }
    pp.appendChild(head);
    S.people.forEach(function (id) {
        var row = el('div', 'gwcal-pprow');
        var nm = el('div', 'gwcal-ppname');
        nm.appendChild(el('span', 'n', nameOf(id)));
        nm.title = nameOf(id);
        row.appendChild(nm);
        var items = S.byPerson[id] || [];
        r.days.forEach(function (d) {
            var cell = el('div', 'gwcal-ppcell' + dayClass(d) + (byDay ? ' ticks' : ''));
            items.filter(function (it) { return it.all_day && overlapsDay(it, d); }).forEach(function (it) { cell.appendChild(openable(evNode(it), it)); });
            daySegments(items, d).forEach(function (sg) {
                var n = openable(evNode(sg.it), sg.it);
                n.style.left = (sg.s / 1440 * 100) + '%';
                n.style.width = (Math.max(sg.e - sg.s, 15) / 1440 * 100) + '%';
                cell.appendChild(n);
            });
            row.appendChild(cell);
        });
        pp.appendChild(row);
    });
    body.appendChild(pp);
}

// ---- the dialog -----------------------------------------------------------------
var lastFocus = null;
function modalOpen() { return !$('gwcal-modal').hidden; }
function showModal(nodes) {
    var m = $('gwcal-modal'), dlg = $('gwcal-dlg');
    if (m.hidden) { lastFocus = document.activeElement; }
    clear(dlg);
    nodes.forEach(function (n) { if (n) { dlg.appendChild(n); } });
    m.hidden = false;
    var f = dlg.querySelector('[data-focus]') || dlg.querySelector('input,select,textarea,button');
    if (f) { f.focus(); }
}
function closeModal() {
    $('gwcal-modal').hidden = true;
    clear($('gwcal-dlg'));
    if (lastFocus && lastFocus.focus && document.body.contains(lastFocus)) { lastFocus.focus(); }
}
function dlgHead(title) {
    var h = el('div', 'gwcal-dhead');
    var h3 = el('h3', '', title);
    h3.id = 'gwcal-dlg-title';
    h.appendChild(h3);
    var x = btn('gb sq', null, closeModal, t('close'));
    x.appendChild(icon('fa-times'));
    h.appendChild(x);
    return h;
}
function dlgRow(ic, content) {
    var r = el('div', 'gwcal-drow');
    r.appendChild(icon(ic));
    var c = el('div');
    if (typeof content === 'string') { c.textContent = content; } else { c.appendChild(content); }
    r.appendChild(c);
    return r;
}
function fmtLong(it) {
    var sp = itemSpan(it);
    if (it.all_day) {
        var last = addDays(sp.e, -1);
        return (dayKey(last) === dayKey(sp.s) ? fmtDate(sp.s, D_LONG) : fmtDate(sp.s, D_LONG) + ' – ' + fmtDate(last, D_LONG)) + ' (' + t('f_all_day') + ')';
    }
    if (dayKey(sp.s) === dayKey(sp.e)) { return fmtDate(sp.s, D_LONG) + ' ' + fmtTime(sp.s) + '–' + fmtTime(sp.e); }
    return fmtDate(sp.s, D_LONG) + ' ' + fmtTime(sp.s) + ' – ' + fmtDate(sp.e, D_LONG) + ' ' + fmtTime(sp.e);
}
function showMessage(title, msg) {
    var b = el('div', 'gwcal-dbody');
    b.appendChild(el('p', 'gwcal-q', msg));
    var f = el('div', 'gwcal-dfoot');
    f.appendChild(el('span', 'sp'));
    f.appendChild(btn('gb', t('close'), closeModal));
    showModal([dlgHead(title), b, f]);
}
// An item of ExmentPM: an issue or a milestone opens in ExmentPM; a date of a schedule says what it is.
function openPm(it) {
    if (it.pm !== 'schedule') { if (it.url) { window.location.href = it.url; } return; }
    var b = el('div', 'gwcal-dbody');
    b.appendChild(dlgRow('fa-clock-o', t('pm_pending')));
    b.appendChild(dlgRow('fa-calendar', fmtLong(it)));
    var pn = el('span');
    var dot = el('span', 'gwcal-pmdot');
    if (/^#[0-9a-fA-F]{6}$/.test(String(it.color || ''))) { dot.style.background = it.color; }
    pn.appendChild(dot);
    pn.appendChild(document.createTextNode(it.project_name || ''));
    b.appendChild(dlgRow('fa-folder-o', pn));
    if (it.original && it.original !== it.start) { b.appendChild(dlgRow('fa-repeat', t('pm_original') + ' ' + it.original)); }
    var f = el('div', 'gwcal-dfoot');
    if (it.change_url) {
        var a = el('a', 'gb', t('pm_change_one'));
        a.href = it.change_url;
        a.title = t('pm_change_help');
        f.appendChild(a);
    }
    if (it.url) {
        var o = el('a', 'gb', t('pm_open_project'));
        o.href = it.url;
        f.appendChild(o);
    }
    f.appendChild(el('span', 'sp'));
    f.appendChild(btn('gb', t('close'), closeModal));
    showModal([dlgHead(it.title || ''), b, f]);
}
function openEvent(it) {
    if (!it || it.busy || it.id == null) { return; }
    var b = el('div', 'gwcal-dbody');
    b.appendChild(el('p', 'gwcal-q', t('loading')));
    showModal([dlgHead(it.title || ''), b]);
    api(U.event + '/' + encodeURIComponent(it.id)).then(function (r) {
        if (!r.ok || !r.event) { showMessage(it.title || t('title'), r.message || t('err_not_found')); return; }
        var ev = r.event;
        ensureNames([ev.owner].concat((ev.attendees || []).map(function (a) { return a.user; }))).then(function () {
            if (modalOpen()) { showDetail(ev, it); }
        });
    });
}
function showDetail(ev, it) {
    var repeat = !!(it.repeat || ev.rrule);
    var b = el('div', 'gwcal-dbody');
    var when = el('div');
    when.appendChild(el('div', '', fmtLong(it)));
    if (repeat) { when.appendChild(el('span', 'gwcal-badge', t('repeat_mark'))); }
    if (it.changed) { when.appendChild(el('span', 'gwcal-badge', t('changed_mark'))); }
    b.appendChild(dlgRow('fa-clock-o', when));
    var loc = it.location != null && it.location !== '' ? it.location : ev.location;
    if (loc) { b.appendChild(dlgRow('fa-map-marker', loc)); }
    if (ev.body) { var nt = el('div', 'gwcal-notes', ev.body); b.appendChild(dlgRow('fa-align-left', nt)); }
    var att = ev.attendees || [], me = null;
    if (att.length) {
        var ul = el('ul', 'gwcal-att');
        ul.setAttribute('aria-label', t('f_attendees'));
        att.forEach(function (a) {
            if (a.user === C.me) { me = a; }
            var li = el('li');
            li.appendChild(el('span', 'n', nameOf(a.user)));
            var resp = ['yes', 'no', 'maybe'].indexOf(a.response) >= 0 ? a.response : 'none';
            li.appendChild(el('span', 'r ' + resp, t('reply_' + resp)));
            ul.appendChild(li);
        });
        b.appendChild(dlgRow('fa-users', ul));
    }
    b.appendChild(dlgRow('fa-user', t('owner') + ': ' + nameOf(ev.owner)));
    if (ev.reminder !== null && ev.reminder !== undefined) { b.appendChild(dlgRow('fa-bell-o', t('f_reminder') + ': ' + remLabel(ev.reminder))); }
    if ((ev.resources || []).length) { b.appendChild(dlgRow('fa-building-o', t('f_rooms') + ': ' + ev.resources.map(roomName).join(', '))); }
    if (me) {
        var rp = el('div');
        rp.appendChild(el('div', '', t('reply')));
        var g = el('div', 'gwcal-reply');
        g.setAttribute('role', 'group');
        g.setAttribute('aria-label', t('reply'));
        ['yes', 'no', 'maybe'].forEach(function (x) {
            var rb = btn('gb', t('reply_' + x), function () {
                api(U.event + '/' + ev.id + '/reply', { response: x }, true).then(function (r) {
                    if (!r.ok) { toast(r.message, true); return; }
                    toast(t('saved'));
                    load();
                    openEvent(it);
                });
            });
            rb.setAttribute('aria-pressed', me.response === x ? 'true' : 'false');
            g.appendChild(rb);
        });
        rp.appendChild(g);
        b.appendChild(dlgRow('fa-reply', rp));
    }
    if (U.call) {
        var a = el('a', 'gwcal-call');
        a.href = U.call + '?event=' + encodeURIComponent(ev.id);
        a.appendChild(icon('fa-video-camera'));
        a.appendChild(document.createTextNode(t('call')));
        b.appendChild(dlgRow('fa-phone', a));
    }
    var f = el('div', 'gwcal-dfoot');
    if (ev.editable) {
        f.appendChild(btn('gb', t('edit'), function () {
            if (repeat) { askScope('scope_ask_edit', ev, it, function (scope) { openForm(ev, it, scope); }); } else { openForm(ev, it, 'all'); }
        }));
        f.appendChild(btn('gb danger', t('delete'), function () {
            if (repeat) { askScope('scope_ask_delete', ev, it, function (scope) { doDelete(ev, it, scope); }); } else { confirmDelete(ev, it); }
        }));
    }
    f.appendChild(el('span', 'sp'));
    var cl = btn('gb', t('close'), closeModal);
    cl.setAttribute('data-focus', '1');
    f.appendChild(cl);
    showModal([dlgHead(it.title || ev.title), b, f]);
}
function askScope(q, ev, it, then) {
    var b = el('div', 'gwcal-dbody');
    b.appendChild(el('p', 'gwcal-q', t(q)));
    var f = el('div', 'gwcal-dfoot');
    var one = btn('gbp', t('scope_one'), function () { then('one'); });
    one.setAttribute('data-focus', '1');
    f.appendChild(one);
    f.appendChild(btn('gb', t('scope_all'), function () { then('all'); }));
    f.appendChild(el('span', 'sp'));
    f.appendChild(btn('gb', t('cancel'), function () { showDetail(ev, it); }));
    showModal([dlgHead(it.title || ev.title), b, f]);
}
function confirmDelete(ev, it) {
    var b = el('div', 'gwcal-dbody');
    b.appendChild(el('p', 'gwcal-q', t('delete_ask')));
    var f = el('div', 'gwcal-dfoot');
    f.appendChild(btn('gb danger', t('delete'), function () { doDelete(ev, it, 'all'); }));
    f.appendChild(el('span', 'sp'));
    var c = btn('gb', t('cancel'), function () { showDetail(ev, it); });
    c.setAttribute('data-focus', '1');
    f.appendChild(c);
    showModal([dlgHead(it.title || ev.title), b, f]);
}
function doDelete(ev, it, scope) {
    api(U.event + '/' + ev.id + '/delete', scope === 'one' ? { scope: 'one', original: it.original } : { scope: 'all' }, true).then(function (r) {
        if (!r.ok) { toast(r.message, true); return; }
        closeModal();
        toast(t('deleted'));
        load();
    });
}

// ---- the form (new and edit) ----------------------------------------------------------
function newValues(day, hour) {
    var d = day ? startOfDay(day) : startOfDay(new Date());
    var h = hour != null ? hour : (dayKey(d) === dayKey(new Date()) ? Math.min(23, new Date().getHours() + 1) : 9);
    var end = h === 23 ? addDays(d, 1) : d;
    return { all_day: false, sd: dayKey(d), st: pad(h) + ':00', ed: dayKey(end), et: pad((h + 1) % 24) + ':00' };
}
function field(label, input, cls) {
    var f = el('div', 'gwcal-f' + (cls ? ' ' + cls : ''));
    var lb = el('label', '', label);
    if (input.id) { lb.htmlFor = input.id; }
    f.appendChild(lb);
    f.appendChild(input);
    return f;
}
function input(type, id, value) { var i = el('input'); i.type = type; i.id = id; i.value = value == null ? '' : value; return i; }
function select(id, options, value) {
    var s = el('select');
    s.id = id;
    options.forEach(function (o) { var op = el('option', '', o[1]); op.value = String(o[0]); s.appendChild(op); });
    s.value = String(value);
    return s;
}
// "10 分前", "1 時間前", "1 日前", "始まるとき"
function remLabel(m) {
    m = Number(m);
    if (m === 0) { return t('rem_at_start'); }
    if (m % 1440 === 0) { return t('rem_days').replace(':n', String(m / 1440)); }
    if (m % 60 === 0) { return t('rem_hours').replace(':n', String(m / 60)); }
    return t('rem_minutes').replace(':n', String(m));
}

function openForm(ev, it, scope, init) {
    var one = scope === 'one';
    var v = init || (one ? eventToForm(it.start, it.end, ev.all_day) : eventToForm(ev.starts_at, ev.ends_at, ev.all_day));
    var b = el('div', 'gwcal-dbody');
    var err = el('div', 'gwcal-ferr');
    err.setAttribute('role', 'alert');
    err.hidden = true;
    b.appendChild(err);
    var title = input('text', 'gwcal-f-title', ev ? (one ? it.title : ev.title) : '');
    title.placeholder = t('f_title_ph');
    title.maxLength = 200;
    title.setAttribute('data-focus', '1');
    b.appendChild(field(t('f_title'), title));
    var chkL = el('label', 'gwcal-chk'), allDay = el('input');
    allDay.type = 'checkbox';
    allDay.checked = !!v.all_day;
    allDay.disabled = one;
    chkL.appendChild(allDay);
    chkL.appendChild(document.createTextNode(t('f_all_day')));
    b.appendChild(chkL);
    var sd = input('date', 'gwcal-f-sd', v.sd), st = input('time', 'gwcal-f-st', v.st);
    var ed = input('date', 'gwcal-f-ed', v.ed), et = input('time', 'gwcal-f-et', v.et);
    st.setAttribute('aria-label', t('f_start'));
    et.setAttribute('aria-label', t('f_end'));
    function whenRow(label, a, c) {
        var f = el('div', 'gwcal-f');
        var lb = el('label', '', label);
        lb.htmlFor = a.id;
        f.appendChild(lb);
        var g = el('div', 'gwcal-when');
        g.appendChild(a); g.appendChild(c);
        f.appendChild(g);
        return f;
    }
    b.appendChild(whenRow(t('f_start'), sd, st));
    b.appendChild(whenRow(t('f_end'), ed, et));
    function timesShown() { st.hidden = et.hidden = allDay.checked; }
    allDay.addEventListener('change', timesShown);
    timesShown();
    var prevStart = sd.value;
    sd.addEventListener('change', function () {
        // the end moves with the start, keeping the length in days
        var a = parseDay(prevStart), n = parseDay(sd.value), e = parseDay(ed.value);
        if (a && n && e) { ed.value = dayKey(addDays(e, Math.round((n - a) / 86400000))); }
        prevStart = sd.value;
    });
    var choice = ev ? repeatChoice(ev.rrule, v.sd) : 'none';
    var rep = select('gwcal-f-repeat', [['none', t('r_none')], ['daily', t('r_daily')], ['weekdays', t('r_weekdays')], ['weekly', t('r_weekly')], ['monthly', t('r_monthly')], ['yearly', t('r_yearly')], ['custom', t('r_custom')]], choice);
    var custom = input('text', 'gwcal-f-rrule', choice === 'custom' ? ev.rrule : '');
    custom.placeholder = t('r_custom_ph');
    custom.setAttribute('aria-label', t('r_custom'));
    custom.maxLength = 255;
    var repF = field(t('f_repeat'), rep);
    repF.appendChild(custom);
    custom.style.marginTop = '6px';
    function customShown() { custom.hidden = rep.value !== 'custom'; }
    rep.addEventListener('change', customShown);
    customShown();
    var loc = input('text', 'gwcal-f-loc', ev ? (one ? it.location : ev.location) : '');
    loc.maxLength = 200;
    var notes = el('textarea');
    notes.id = 'gwcal-f-body';
    notes.value = ev ? ev.body || '' : '';
    var picker = makePicker(ev ? (ev.attendees || []).map(function (a) { return a.user; }) : [], t('f_attendees_ph'));
    var pf = el('div', 'gwcal-f');
    pf.appendChild(el('div', 'lb', t('f_attendees')));
    pf.appendChild(picker.node);
    var vis = [['all', t('v_all')]];
    if ((C.orgs || []).length || (ev && ev.visibility === 'org')) { vis.push(['org', t('v_org')]); }
    vis.push(['attendees', t('v_attendees')], ['busy', t('v_busy')]);
    var visS = select('gwcal-f-vis', vis, ev ? ev.visibility : 'all');
    var org = select('gwcal-f-org', (C.orgs || []).map(function (o) { return [o.id, o.name]; }), ev && ev.org_id ? ev.org_id : ((C.orgs || [])[0] || {}).id || '');
    var orgF = field(t('f_org'), org);
    function orgShown() { orgF.hidden = visS.value !== 'org'; }
    visS.addEventListener('change', orgShown);
    orgShown();
    b.appendChild(repF);
    b.appendChild(field(t('f_location'), loc));
    b.appendChild(field(t('f_body'), notes));
    b.appendChild(pf);
    b.appendChild(field(t('f_visibility'), visS));
    b.appendChild(orgF);
    // the reminder (part 2): none, or minutes before
    var remOpts = [['', t('rem_none')]].concat((C.reminders || []).map(function (m) { return [String(m), remLabel(m)]; }));
    var remS = select('gwcal-f-rem', remOpts, ev && ev.reminder !== null && ev.reminder !== undefined ? String(ev.reminder) : '');
    var remF = field(t('f_reminder'), remS);
    if ((C.reminders || []).length) { b.appendChild(remF); }
    // the rooms and equipment (part 3): the ones in use, and the ones the event has
    var had = ev ? (ev.resources || []) : [];
    var offered = (C.resources || []).filter(function (r) { return r.enabled || had.indexOf(r.id) >= 0; });
    var roomBoxes = [];
    var roomF = el('div', 'gwcal-f');
    roomF.appendChild(el('div', 'lb', t('f_rooms')));
    var rw = el('div', 'gwcal-rooms');
    offered.forEach(function (r) {
        var lab = el('label');
        var cb = el('input');
        cb.type = 'checkbox';
        cb.value = String(r.id);
        cb.checked = had.indexOf(r.id) >= 0;
        roomBoxes.push(cb);
        lab.appendChild(cb);
        lab.appendChild(document.createTextNode(r.name + (r.capacity ? ' (' + t('capacity_n', r.capacity) + ')' : '')));
        rw.appendChild(lab);
    });
    roomF.appendChild(rw);
    if (offered.length) { b.appendChild(roomF); }
    // one occurrence keeps its times, title and place only: the rest is of all of them
    if (one) { [repF, notes.parentNode, pf, visS.parentNode, orgF, remF, roomF].forEach(function (n) { n.hidden = true; }); }
    var clashBox = el('div', 'gwcal-clash');
    clashBox.hidden = true;
    b.insertBefore(clashBox, b.firstChild);
    var f = el('div', 'gwcal-dfoot');
    var save = btn('gbp', t('save'), function () { submit(false); });
    f.appendChild(save);
    f.appendChild(el('span', 'sp'));
    f.appendChild(btn('gb', t('cancel'), function () { if (ev) { showDetail(ev, it); } else { closeModal(); } }));
    function fail(m) { err.textContent = m; err.hidden = false; save.disabled = false; err.scrollIntoView && err.scrollIntoView({ block: 'nearest' }); }
    function showClash(r, canGo) {
        clear(clashBox);
        clashBox.appendChild(el('div', '', r.message));
        var ul = el('ul');
        (r.clashes || []).forEach(function (c) {
            (c.times || []).slice(0, 5).forEach(function (tm) {
                ul.appendChild(el('li', '', c.name + ': ' + fmtLong({ start: tm.start, end: tm.end, all_day: allDay.checked })));
            });
        });
        clashBox.appendChild(ul);
        if (canGo) { clashBox.appendChild(btn('gb', t('clash_go'), function () { submit(true); })); }
        clashBox.hidden = false;
        save.disabled = false;
        clashBox.scrollIntoView && clashBox.scrollIntoView({ block: 'nearest' });
    }
    function submit(ignoreClash) {
        var fv = { title: title.value, all_day: allDay.checked, sd: sd.value, st: st.value, ed: ed.value, et: et.value };
        var bad = formCheck(fv);
        if (bad) { fail(t(bad)); return; }
        var tm = formTimes(fv);
        var p = {
            title: title.value, location: loc.value, body: notes.value, all_day: fv.all_day ? '1' : '',
            start: tm.start, end: tm.end, tz: browserTz(),
            rrule: one ? (ev.rrule || '') : buildRrule(rep.value, fv.sd, custom.value),
            visibility: visS.value, org_id: visS.value === 'org' ? org.value : '', attendees: picker.ids,
            reminder: remS.value
        };
        if (offered.length && !one) {
            p.resources_set = '1';
            p.resources = roomBoxes.filter(function (x) { return x.checked; }).map(function (x) { return x.value; });
        }
        if (ignoreClash === true) { p.ignore_clash = '1'; }
        if (ev && one) { p.scope = 'one'; p.original = it.original; }
        if (ev && !one) { p.scope = 'all'; p.exdates = ev.exdates || []; }
        save.disabled = true;
        err.hidden = true;
        clashBox.hidden = true;
        api(ev ? U.event + '/' + ev.id + '/save' : U.event, p, true).then(function (r) {
            if (!r.ok && r.clashes) { showClash(r, r.status === 409); return; }
            if (!r.ok) { fail(r.message); return; }
            closeModal();
            toast(t('saved'));
            load();
        });
    }
    b.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && e.target.tagName === 'INPUT' && e.target.type !== 'checkbox' && !e.target.closest('.gwcal-pick')) { e.preventDefault(); submit(false); }
    });
    showModal([dlgHead(ev ? t('edit') : t('new')), b, f]);
}

// ---- rooms and equipment (part 3) -----------------------------------------------------
function roomsUsable() { return (C.resources || []).filter(function (r) { return r.enabled; }); }
function roomName(id) {
    var r = (C.resources || []).filter(function (x) { return x.id === id; })[0];
    return r ? r.name : '#' + id;
}
// the rooms of a day: what is booked when (a busy one shows only that it is taken)
function openRooms(day) {
    day = day || (S.view === 'day' ? S.anchor : startOfDay(new Date()));
    var b = el('div', 'gwcal-dbody');
    var nav = el('div', 'gwcal-nav');
    var pv = btn('gb sq', null, function () { openRooms(addDays(day, -1)); }, t('prev'));
    pv.appendChild(icon('fa-chevron-left'));
    var nx = btn('gb sq', null, function () { openRooms(addDays(day, 1)); }, t('next'));
    nx.appendChild(icon('fa-chevron-right'));
    nav.appendChild(pv);
    nav.appendChild(el('span', 'gwcal-q', fmtDate(day, { year: 'numeric', month: 'short', day: 'numeric', weekday: 'short' })));
    nav.appendChild(nx);
    b.appendChild(nav);
    var list = el('ul', 'gwcal-rlist');
    b.appendChild(list);
    // with the rooms taken out of use: the bookings they still have show (taking a room out keeps its events)
    var rooms = (C.resources || []).slice().sort(function (a, b) { return (b.enabled ? 1 : 0) - (a.enabled ? 1 : 0); }).slice(0, 30);
    if (!roomsUsable().length) { list.appendChild(el('li', 'off', t('rooms_none'))); }
    var foot = el('div', 'gwcal-dfoot');
    if (C.resources_admin) { foot.appendChild(btn('gb', t('rooms_manage'), function () { manageRooms(); })); }
    foot.appendChild(el('span', 'sp'));
    foot.appendChild(btn('gb', t('close'), closeModal));
    showModal([dlgHead(t('rooms')), b, foot]);
    if (!rooms.length) { return; }
    api(U.resource_events, { from: toUtc(day), to: toUtc(addDays(day, 1)), ids: rooms.map(function (r) { return r.id; }).join(',') }).then(function (r) {
        if (!r.ok) { list.appendChild(el('li', 'off', r.message)); return; }
        roomsOfDay(rooms, r.events).forEach(function (x) {
            var li = el('li');
            li.appendChild(el('span', 'n' + (x.off ? ' off' : ''), x.room.name + (x.off ? ' (' + t('rooms_off') + ')' : '')));
            if (!x.events.length) { li.appendChild(el('span', 'b', t('rooms_free'))); }
            x.events.forEach(function (e) { li.appendChild(el('span', 'b', fmtRange(e) + '  ' + (e.title || ''))); });
            list.appendChild(li);
        });
    });
}
// the list (a system administrator): add, rename, take out of use
function manageRooms(edit) {
    var b = el('div', 'gwcal-dbody');
    var err = el('div', 'gwcal-err');
    err.hidden = true;
    b.appendChild(err);
    var list = el('ul', 'gwcal-rlist');
    (C.resources || []).forEach(function (r) {
        var li = el('li');
        li.appendChild(el('span', 'n' + (r.enabled ? '' : ' off'), r.name + (r.enabled ? '' : ' (' + t('rooms_off') + ')')));
        li.appendChild(document.createTextNode(' '));
        li.appendChild(btn('gb', t('edit'), function () { manageRooms(r); }));
        list.appendChild(li);
    });
    b.appendChild(list);
    var h = el('div', 'lb', edit ? t('edit') + ': ' + edit.name : t('rooms_add'));
    h.style.margin = '14px 0 6px';
    h.style.fontWeight = '600';
    b.appendChild(h);
    var nm = input('text', 'gwcal-r-name', edit ? edit.name : '');
    nm.maxLength = 100;
    nm.setAttribute('data-focus', '1');
    var kind = select('gwcal-r-kind', [['room', t('kind_room')], ['equipment', t('kind_equipment')]], edit ? edit.kind : 'room');
    var cap = input('text', 'gwcal-r-cap', edit && edit.capacity ? edit.capacity : '');
    cap.inputMode = 'numeric';
    var note = input('text', 'gwcal-r-note', edit ? edit.note : '');
    note.maxLength = 500;
    var on = el('input');
    on.type = 'checkbox';
    on.id = 'gwcal-r-on';
    on.checked = edit ? !!edit.enabled : true;
    b.appendChild(field(t('room_name'), nm));
    var rows = el('div', 'gwcal-rows');
    rows.appendChild(field(t('room_kind'), kind));
    rows.appendChild(field(t('room_capacity'), cap));
    b.appendChild(rows);
    b.appendChild(field(t('room_note'), note));
    var ol = el('label', 'gwcal-chk');
    ol.appendChild(on);
    ol.appendChild(document.createTextNode(t('rooms_in_use')));
    b.appendChild(ol);
    var foot = el('div', 'gwcal-dfoot');
    var sv = btn('gbp', t('save'), function () {
        sv.disabled = true;
        err.hidden = true;
        api(edit ? U.resource + '/' + edit.id : U.resource, { name: nm.value, kind: kind.value, capacity: cap.value, note: note.value, enabled: on.checked ? '1' : '0' }, true).then(function (r) {
            sv.disabled = false;
            if (!r.ok) { err.textContent = r.message; err.hidden = false; return; }
            C.resources = r.resources || [];
            toast(t('saved'));
            manageRooms();
        });
    });
    foot.appendChild(sv);
    if (edit) { foot.appendChild(btn('gb', t('cancel'), function () { manageRooms(); })); }
    foot.appendChild(el('span', 'sp'));
    foot.appendChild(btn('gb', t('rooms'), function () { openRooms(); }));
    showModal([dlgHead(t('rooms_manage')), b, foot]);
}

// ---- keys, clicks, the clock ---------------------------------------------------------
listen(document, 'keydown', function (e) {
    if (!document.body.contains(root)) { return; }
    if (e.key === 'Escape') {
        var open = root.querySelector('.gwcal-res:not([hidden])');
        if (open) { open.hidden = true; e.preventDefault(); return; }
        if (!modalOpen()) { return; }
        e.preventDefault();
        closeModal();
        return;
    }
    if (e.key === 'Tab' && modalOpen()) {
        // keep the focus in the dialog
        var fs = Array.prototype.filter.call($('gwcal-dlg').querySelectorAll('button,input,select,textarea,a[href]'), function (n) { return !n.disabled && n.offsetParent !== null; });
        if (!fs.length) { return; }
        if (e.shiftKey && document.activeElement === fs[0]) { e.preventDefault(); fs[fs.length - 1].focus(); } else if (!e.shiftKey && document.activeElement === fs[fs.length - 1]) { e.preventDefault(); fs[0].focus(); }
    }
});
listen(document, 'click', function (e) {
    if (!document.body.contains(root)) { return; }
    Array.prototype.forEach.call(root.querySelectorAll('.gwcal-res:not([hidden])'), function (r) { if (!r.parentNode.contains(e.target)) { r.hidden = true; } });
});
$('gwcal-modal').addEventListener('click', function (e) {
    // outside the dialog: closes a view, not a form being filled in
    if (e.target === this && !$('gwcal-dlg').querySelector('.gwcal-f')) { closeModal(); }
});
if (window.__gwcalTimer) { clearInterval(window.__gwcalTimer); }
window.__gwcalTimer = setInterval(function () {
    if (!document.body.contains(root)) { clearInterval(window.__gwcalTimer); return; }
    placeNow();
}, 60000);

load();
})();
</script>
@endverbatim
