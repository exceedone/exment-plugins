@php($Its = \App\Plugins\ExmentSupportDesk\Its::class)
@php($here = function ($path) { return request()->is(\App\Plugins\ExmentSupportDesk\PublicRoutes::PREFIX . '/' . $path) ? 'on' : ''; })
<!doctype html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="referrer" content="no-referrer">
<meta name="color-scheme" content="light dark">
<title>{{ $title }}</title>
<style>
/* Customer pages. Tokens first; the dark theme is only a second set of tokens. No outside files. */
:root{--bg:#f5f6f9;--card:#fff;--card2:#f8f9fb;--line:#e2e6ec;--line2:#edf0f4;--line3:#cfd5de;--text:#1a2230;--sub:#525d6c;--faint:#6b7685;
  --accent:#4f6bed;--accent2:#7460f0;--accent-ink:#3a50c8;--soft:#eef1fe;--soft-line:#d5dcfb;--hover:#f2f4f8;
  --red:#c93036;--green:#1c7d50;--star:#e59a0b;
  --ok-bg:#eaf7f0;--ok-line:#bfe5d0;--ok-ink:#17633f;--err-bg:#fdeeee;--err-line:#f4c6c7;--err-ink:#9f2328;--warn-bg:#fff6e3;--warn-line:#f2d9a0;--warn-ink:#734f00;
  --shadow:0 1px 2px rgba(16,24,40,.04),0 1px 3px rgba(16,24,40,.05);--shadow-m:0 6px 20px rgba(16,24,40,.08),0 1px 3px rgba(16,24,40,.05);--ring:0 0 0 3px rgba(79,107,237,.28)}
@media (prefers-color-scheme:dark){:root{--bg:#13161b;--card:#1a1e25;--card2:#1f242c;--line:#2b313b;--line2:#252a33;--line3:#3a424e;--text:#e7eaf0;--sub:#aab3c0;--faint:#8c96a5;
  --accent:#8497fa;--accent2:#a58ff8;--accent-ink:#a9b6fc;--soft:#232a47;--soft-line:#343e6b;--hover:#232933;
  --red:#f0666a;--green:#4cc38a;--star:#f0b53c;
  --ok-bg:#16291f;--ok-line:#245039;--ok-ink:#8fdcb4;--err-bg:#2f191b;--err-line:#5e2a2d;--err-ink:#f6a5a8;--warn-bg:#2d2513;--warn-line:#5a4718;--warn-ink:#efd28a;
  --shadow:0 1px 2px rgba(0,0,0,.35);--shadow-m:0 6px 20px rgba(0,0,0,.4);--ring:0 0 0 3px rgba(132,151,250,.4)}}
*,*::before,*::after{box-sizing:border-box}
html{-webkit-text-size-adjust:100%}
body{margin:0;background:var(--bg);color:var(--text);font:15px/1.7 -apple-system,BlinkMacSystemFont,"Segoe UI","Hiragino Sans","Hiragino Kaku Gothic ProN","Noto Sans JP","Yu Gothic UI",Meiryo,sans-serif;-webkit-font-smoothing:antialiased;min-height:100vh;display:flex;flex-direction:column}
a{color:var(--accent-ink);text-underline-offset:3px}
a:focus-visible,button:focus-visible,summary:focus-visible{outline:2px solid var(--accent);outline-offset:2px;border-radius:6px}
::selection{background:rgba(79,107,237,.25)}
.ic{width:18px;height:18px;flex:0 0 auto;fill:none;stroke:currentColor;stroke-width:1.9;stroke-linecap:round;stroke-linejoin:round;vertical-align:-3px}
.ic.s{width:15px;height:15px;vertical-align:-2px}
.ic.l{width:24px;height:24px}
.sprite{position:absolute;width:0;height:0;overflow:hidden}
.skip{position:absolute;left:-10000px}
.skip:focus{left:12px;top:12px;z-index:20;background:var(--card);padding:8px 12px;border-radius:8px;box-shadow:var(--shadow-m)}

header{position:sticky;top:0;z-index:10;background:var(--card);border-bottom:1px solid var(--line)}
header .in{max-width:880px;margin:0 auto;padding:10px 16px;display:flex;flex-wrap:wrap;gap:6px 16px;align-items:center}
header .brand{display:inline-flex;align-items:center;gap:10px;font-weight:700;font-size:16px;color:var(--text);text-decoration:none;min-width:0}
header .brand span.t{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.logo{width:32px;height:32px;border-radius:10px;display:grid;place-items:center;flex:0 0 32px;color:#fff;background:linear-gradient(135deg,#4f6bed,#7c5cf0);box-shadow:0 3px 8px rgba(79,107,237,.32),inset 0 1px 0 rgba(255,255,255,.25)}
.logo .ic{width:19px;height:19px;vertical-align:0}
header nav{display:flex;gap:2px;margin-left:auto}
header nav a{display:inline-flex;align-items:center;gap:7px;height:36px;padding:0 12px;border-radius:9px;text-decoration:none;color:var(--sub);font-size:14px;font-weight:500;white-space:nowrap}
header nav a:hover{background:var(--hover);color:var(--text)}
header nav a.on{background:var(--soft);color:var(--accent-ink);font-weight:600}
@media (max-width:640px){header{position:static}header .in{padding:10px 12px 8px}header nav{margin:0 -4px;flex:1 0 100%}header nav a{flex:1;justify-content:center;padding:0 6px;font-size:13px;gap:5px}header nav a .ic{width:16px;height:16px}}

main{flex:1;width:100%;max-width:880px;margin:0 auto;padding:24px 16px 40px}
main.narrow{max-width:640px}
.card{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:22px 24px;margin-bottom:16px;box-shadow:var(--shadow)}
@media (max-width:640px){main{padding:16px 12px 32px}.card{padding:18px 16px;border-radius:12px}}
h1{font-size:22px;line-height:1.4;margin:0 0 10px;letter-spacing:.01em;word-break:break-word}
h2{font-size:16px;line-height:1.5;margin:0 0 12px;display:flex;align-items:center;gap:8px}
h2 .ic{color:var(--accent-ink)}
p{margin:0 0 12px}
.sub{color:var(--sub);font-size:13px;line-height:1.65}
.lead{color:var(--sub);margin:0 0 6px}
.num{font-variant-numeric:tabular-nums}
.back{display:inline-flex;align-items:center;gap:4px;font-size:13px;text-decoration:none;color:var(--sub);margin-bottom:8px}
.back:hover{color:var(--accent-ink)}

.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:42px;padding:8px 18px;border:1px solid var(--line3);border-radius:10px;background:var(--card);color:var(--text);text-decoration:none;cursor:pointer;font:inherit;font-size:15px;font-weight:600;line-height:1.3;white-space:nowrap;flex:0 0 auto;box-shadow:0 1px 1px rgba(16,24,40,.04);transition:background .12s,border-color .12s,box-shadow .12s}
.btn:hover{background:var(--hover)}
.btn:focus-visible{outline:0;box-shadow:var(--ring);border-color:var(--accent);border-radius:10px}
.btn.primary{background:linear-gradient(135deg,#4f6bed,#7460f0);border-color:transparent;color:#fff;box-shadow:0 1px 2px rgba(49,66,160,.35),inset 0 1px 0 rgba(255,255,255,.18)}
.btn.primary:hover{background:linear-gradient(135deg,#4561e4,#6a55e8)}
.btn.primary:focus-visible{box-shadow:0 0 0 2px var(--card),0 0 0 4px var(--accent)}
.btn.big{min-height:50px;padding:12px 22px;font-size:16px;width:100%}
.btn.ok{color:var(--green);border-color:var(--ok-line)}
.btn.ok:hover{background:var(--ok-bg)}
.btn[disabled]{opacity:.55;cursor:default}
.actions{display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-top:16px}

label.f{display:block;font-weight:600;font-size:14px;margin:18px 0 6px}
.grid2 label.f:first-child,form>label.f:first-child{margin-top:0}
input[type=text],input[type=email],input[type=search],textarea,select{width:100%;min-height:44px;padding:9px 12px;border:1px solid var(--line3);border-radius:10px;font:inherit;font-size:16px;line-height:1.5;background:var(--card);color:var(--text);box-shadow:inset 0 1px 1px rgba(16,24,40,.03);transition:border-color .12s,box-shadow .12s}
textarea{min-height:160px;resize:vertical;display:block;line-height:1.7}
input:focus,textarea:focus,select:focus{outline:0;border-color:var(--accent);box-shadow:var(--ring)}
input::placeholder,textarea::placeholder{color:var(--faint);opacity:.85}
input[type=checkbox]{accent-color:var(--accent);width:18px;height:18px;margin:0;flex:0 0 auto}
input[type=file]{width:100%;font:inherit;font-size:14px;color:var(--sub);padding:10px;border:1px dashed var(--line3);border-radius:10px;background:var(--card2)}
input[type=file]::file-selector-button{font:inherit;font-size:13px;font-weight:600;margin-right:12px;padding:7px 14px;border:1px solid var(--line3);border-radius:8px;background:var(--card);color:var(--text);cursor:pointer}
.hint{color:var(--sub);font-size:13px;margin-top:6px;display:flex;gap:6px;align-items:flex-start;line-height:1.6}
.hint .ic{margin-top:3px;vertical-align:0}
.req{display:inline-block;color:var(--err-ink);background:var(--err-bg);font-size:11px;font-weight:600;line-height:1.5;margin-left:6px;padding:0 6px;border-radius:5px;vertical-align:1px}
.opt{display:flex;gap:10px;align-items:center;margin-top:18px;padding:12px 14px;border:1px solid var(--line);border-radius:10px;background:var(--card2);cursor:pointer;font-weight:500}
.opt:has(input:checked){border-color:var(--warn-line);background:var(--warn-bg);color:var(--warn-ink)}
.search{position:relative;display:flex;gap:8px}
.search .ic{position:absolute;left:14px;top:50%;margin-top:-9px;color:var(--faint);pointer-events:none}
.search input{padding-left:42px}
.search.big input{min-height:52px;border-radius:12px;box-shadow:var(--shadow)}
.search.big .btn{min-height:52px;border-radius:12px}
@media (max-width:480px){.search .btn{padding:8px 14px}}

.flash,.err,.warn{display:flex;gap:10px;align-items:flex-start;padding:12px 14px;border-radius:10px;margin-bottom:16px;line-height:1.65;border:1px solid}
.flash>.ic,.err>.ic,.warn>.ic{margin-top:4px;vertical-align:0}
.flash{background:var(--ok-bg);border-color:var(--ok-line);color:var(--ok-ink)}
.err{background:var(--err-bg);border-color:var(--err-line);color:var(--err-ink)}
.warn{background:var(--warn-bg);border-color:var(--warn-line);color:var(--warn-ink)}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:0 14px}
@media (max-width:640px){.grid2{grid-template-columns:1fr}.grid2>div+div label.f:first-child{margin-top:18px}}

/* status: a tinted chip with a dot and a word (never color alone) */
.pill{--c:#8b95a3;display:inline-flex;align-items:center;gap:6px;padding:2px 10px 2px 8px;border-radius:999px;font-size:12.5px;font-weight:600;line-height:1.6;white-space:nowrap;color:var(--c);color:color-mix(in srgb,var(--c) 55%,var(--text));background:var(--line2);background:color-mix(in srgb,var(--c) 14%,var(--card))}
.pill::before{content:"";width:7px;height:7px;border-radius:50%;background:var(--c)}

/* home */
.hero{padding:20px 4px 8px;text-align:center}
.hero h1{font-size:26px;margin-bottom:8px}
.hero p{color:var(--sub);max-width:560px;margin:0 auto 20px}
.hero .search{max-width:600px;margin:0 auto 22px;text-align:left}
.tiles{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:16px}
@media (max-width:640px){.tiles{grid-template-columns:1fr;gap:10px}.hero{padding-top:8px}.hero h1{font-size:22px}}
.tile{display:flex;gap:14px;align-items:center;padding:18px;border:1px solid var(--line);border-radius:14px;background:var(--card);color:var(--text);text-decoration:none;box-shadow:var(--shadow);transition:border-color .12s,box-shadow .12s,transform .12s}
.tile:hover{border-color:var(--soft-line);box-shadow:var(--shadow-m);transform:translateY(-1px)}
.tile .i{width:44px;height:44px;border-radius:12px;display:grid;place-items:center;flex:0 0 44px;background:var(--soft);color:var(--accent-ink)}
.tile.primary{background:linear-gradient(135deg,#4f6bed,#7460f0);border-color:transparent;color:#fff}
.tile.primary .i{background:rgba(255,255,255,.2);color:#fff}
.tile.primary small{color:rgba(255,255,255,.92)}
.tile b{display:block;font-size:16px;line-height:1.45}
.tile small{display:block;font-size:13px;color:var(--sub);line-height:1.5}
.tile>.ic:last-child{margin-left:auto;opacity:.7}

/* lists */
.list a.item{display:flex;gap:12px;align-items:center;padding:13px 10px;margin:0 -10px;border-radius:10px;border-bottom:0;text-decoration:none;color:var(--text);position:relative}
.list a.item+a.item::before{content:"";position:absolute;left:10px;right:10px;top:0;height:1px;background:var(--line2)}
.list a.item:hover{background:var(--hover)}
.list a.item:hover b{color:var(--accent-ink)}
.list a.item .m{flex:1;min-width:0}
.list a.item .m .sub{display:block;margin-top:2px}
.list a.item:has(.m .sub){align-items:flex-start}
.list a.item:has(.m .sub)>.ic{align-self:center}
.list a.item b{display:block;font-weight:600;line-height:1.5;word-break:break-word}
.list a.item>.ic{color:var(--faint)}
.list a.item .q{width:28px;height:28px;border-radius:8px;display:grid;place-items:center;flex:0 0 28px;background:var(--soft);color:var(--accent-ink);font-weight:700;font-size:13px}
.empty{text-align:center;padding:26px 10px;color:var(--sub)}
.empty .i{width:52px;height:52px;border-radius:16px;display:grid;place-items:center;margin:0 auto 12px;background:var(--soft);color:var(--accent-ink)}
.suggest{display:none;margin-top:10px;padding:14px 16px 6px;border:1px solid var(--soft-line);border-radius:12px;background:var(--soft)}
.suggest>b{display:flex;gap:8px;align-items:center;color:var(--accent-ink);font-size:14px}
.suggest .list a.item{display:block;padding:10px;margin:0 -10px}
.suggest .list a.item:hover{background:var(--card)}
.suggest .list a.item+a.item::before{background:var(--soft-line)}

/* ticket */
.tno{display:inline-block;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:12px;font-weight:600;color:var(--sub);background:var(--line2);border-radius:6px;padding:1px 8px;margin-right:6px}
.steps{display:flex;margin:18px 0 14px;padding:0;list-style:none;counter-reset:st}
.steps li{flex:1;min-width:0;position:relative;text-align:center;font-size:13px;color:var(--faint);counter-increment:st}
.steps li::before{content:counter(st);position:relative;z-index:1;display:grid;place-items:center;width:28px;height:28px;margin:0 auto 6px;border-radius:50%;border:2px solid var(--line3);background:var(--card);font-size:12.5px;font-weight:700;font-variant-numeric:tabular-nums}
.steps li::after{content:"";position:absolute;top:13px;left:calc(-50% + 14px);right:calc(50% + 14px);height:2px;background:var(--line3)}
.steps li:first-child::after{display:none}
.steps li.done{color:var(--text)}
.steps li.done::before{content:"✓";background:var(--accent);border-color:var(--accent);color:#fff}
@media (prefers-color-scheme:dark){.steps li.done::before,.steps li.on::before{color:#0f1424}}
.steps li.done::after,.steps li.on::after{background:var(--accent)}
.steps li.on{color:var(--accent-ink);font-weight:700}
.steps li.on::before{background:var(--accent);border-color:var(--accent);color:#fff;box-shadow:0 0 0 4px var(--soft)}
.now{display:flex;flex-wrap:wrap;gap:6px 10px;align-items:center;margin:0;padding-top:14px;border-top:1px solid var(--line2)}
.thread{display:flex;flex-direction:column;gap:14px}
.msg{display:flex;gap:10px;align-items:flex-start;margin:0;border:0;padding:0;background:none}
.msg .av{width:34px;height:34px;border-radius:50%;display:grid;place-items:center;flex:0 0 34px;background:var(--line2);color:var(--sub);margin-top:2px}
.msg.reply .av{background:linear-gradient(135deg,#4f6bed,#7c5cf0);color:#fff}
.msg .bub{flex:1;min-width:0;border:1px solid var(--line);border-radius:4px 14px 14px 14px;padding:10px 14px 12px;background:var(--card2)}
.msg.reply .bub{background:var(--soft);border-color:var(--soft-line)}
.msg .head{display:flex;flex-wrap:wrap;gap:2px 8px;align-items:baseline;font-size:12.5px;color:var(--sub);margin-bottom:4px}
.msg .head b{color:var(--text);font-size:14px}
.msg .head .tag{font-size:11px;font-weight:600;color:var(--accent-ink);border:1px solid var(--soft-line);border-radius:5px;padding:0 6px;background:var(--card)}
.msg .head time{margin-left:auto;font-variant-numeric:tabular-nums}
.msg .body{white-space:pre-wrap;word-break:break-word}
.att{display:inline-flex;gap:6px;align-items:center;margin-top:8px;padding:3px 10px;border:1px solid var(--line);border-radius:8px;background:var(--card);font-size:12.5px;color:var(--sub);max-width:100%;word-break:break-all}
.files{margin-top:16px;padding-top:14px;border-top:1px solid var(--line2);font-size:14px}
.files a{display:inline-flex;gap:6px;align-items:center;margin:6px 8px 0 0;padding:5px 12px;border:1px solid var(--line);border-radius:8px;text-decoration:none;background:var(--card2);word-break:break-all}
.files a:hover{border-color:var(--soft-line);background:var(--soft)}
.stars{display:flex;gap:2px;margin:2px 0 4px -4px}
.stars button{font-size:34px;line-height:1;background:none;border:0;cursor:pointer;color:var(--line3);padding:4px;border-radius:8px;transition:transform .1s,color .1s}
.stars button:hover{transform:scale(1.12)}
.stars button.on{color:var(--star)}
.rated{color:var(--star);font-size:22px;letter-spacing:2px;margin-right:8px}
.sep{display:flex;align-items:center;gap:12px;margin:18px 0 14px;color:var(--faint);font-size:12.5px}
.sep::before,.sep::after{content:"";flex:1;height:1px;background:var(--line2)}
.center{text-align:center}
.bigicon{width:60px;height:60px;border-radius:18px;display:grid;place-items:center;margin:4px auto 14px;background:var(--soft);color:var(--accent-ink)}
.bigicon.ok{background:var(--ok-bg);color:var(--green)}
.bigicon .ic{width:30px;height:30px}
.code{font-size:26px!important;font-weight:700;letter-spacing:8px;text-align:center;max-width:240px;font-variant-numeric:tabular-nums;min-height:56px!important}
.prose{white-space:pre-wrap;word-break:break-word;line-height:1.9;font-size:15.5px}
.hp{position:absolute;left:-10000px;width:1px;height:1px;overflow:hidden}
footer{width:100%;max-width:880px;margin:0 auto;padding:0 16px 28px;color:var(--faint);font-size:12px;display:flex;gap:6px;align-items:center;justify-content:center;text-align:center}
@media (prefers-reduced-motion:reduce){*{transition:none!important}}
</style>
</head>
<body>
<svg class="sprite" aria-hidden="true" focusable="false"><defs>
    <symbol id="i-buoy" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="3.5"/><path d="m5.6 5.6 3.9 3.9M14.5 14.5l3.9 3.9M18.4 5.6l-3.9 3.9M9.5 14.5l-3.9 3.9"/></symbol>
    <symbol id="i-send" viewBox="0 0 24 24"><path d="M21 3 10 14"/><path d="m21 3-7 18-4-7-7-4 18-7z"/></symbol>
    <symbol id="i-pencil" viewBox="0 0 24 24"><path d="M4 20h4L19 9l-4-4L4 16v4z"/><path d="m13.5 6.5 4 4"/></symbol>
    <symbol id="i-help" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M9.5 9.5a2.5 2.5 0 1 1 3.5 2.3c-.7.4-1 1-1 1.7"/><path d="M12 17h.01"/></symbol>
    <symbol id="i-list" viewBox="0 0 24 24"><path d="M8 6h12M8 12h12M8 18h12M4 6h.01M4 12h.01M4 18h.01"/></symbol>
    <symbol id="i-search" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></symbol>
    <symbol id="i-right" viewBox="0 0 24 24"><path d="m9 6 6 6-6 6"/></symbol>
    <symbol id="i-left" viewBox="0 0 24 24"><path d="m15 6-6 6 6 6"/></symbol>
    <symbol id="i-check" viewBox="0 0 24 24"><path d="m5 12.5 4.5 4.5L19 7.5"/></symbol>
    <symbol id="i-x" viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18"/></symbol>
    <symbol id="i-clip" viewBox="0 0 24 24"><path d="m20 11.5-8.5 8.5a5 5 0 0 1-7-7L13 4.5a3.3 3.3 0 0 1 4.7 4.7L9.2 17.7a1.7 1.7 0 0 1-2.4-2.4l7.8-7.8"/></symbol>
    <symbol id="i-lock" viewBox="0 0 24 24"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></symbol>
    <symbol id="i-bulb" viewBox="0 0 24 24"><path d="M9 18h6M10 21h4"/><path d="M12 3a6 6 0 0 0-4 10.5c.7.7 1 1.5 1 2.5h6c0-1 .3-1.8 1-2.5A6 6 0 0 0 12 3z"/></symbol>
    <symbol id="i-mail" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3.5 7 8.5 6 8.5-6"/></symbol>
    <symbol id="i-alert" viewBox="0 0 24 24"><path d="M12 4 2.5 20h19L12 4z"/><path d="M12 10v4M12 17h.01"/></symbol>
    <symbol id="i-info" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/></symbol>
    <symbol id="i-user" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4.5 20a7.5 7.5 0 0 1 15 0"/></symbol>
    <symbol id="i-agent" viewBox="0 0 24 24"><path d="M5 13v-2a7 7 0 0 1 14 0v2"/><rect x="3" y="12.5" width="4" height="6" rx="1.5"/><rect x="17" y="12.5" width="4" height="6" rx="1.5"/><path d="M19 18.5c0 1.700-2 2.5-5 2.5"/></symbol>
    <symbol id="i-shield" viewBox="0 0 24 24"><path d="M12 3 5 6v6c0 4.5 3 7.5 7 9 4-1.5 7-4.5 7-9V6l-7-3z"/><path d="m9 12 2 2 4-4"/></symbol>
    <symbol id="i-chat" viewBox="0 0 24 24"><path d="M4 5h16v11H9l-5 4V5z"/></symbol>
    <symbol id="i-file" viewBox="0 0 24 24"><path d="M6 3h8l4 4v14H6V3z"/><path d="M14 3v4h4"/></symbol>
</defs></svg>
<a class="skip" href="#main">本文へ</a>
<header><div class="in">
    <a class="brand" href="{{ $Its::publicUrl() }}"><span class="logo" aria-hidden="true"><svg class="ic"><use href="#i-buoy"/></svg></span><span class="t">{{ $title }}</span></a>
    <nav aria-label="メニュー">
        <a href="{{ $Its::publicUrl('request') }}" class="{{ $here('request') }}"><svg class="ic" aria-hidden="true"><use href="#i-pencil"/></svg>依頼する</a>
        <a href="{{ $Its::publicUrl('faq') }}" class="{{ $here('faq*') }}"><svg class="ic" aria-hidden="true"><use href="#i-help"/></svg>よくある質問</a>
        <a href="{{ $Its::publicUrl('mine') }}" class="{{ $here('mine*') }}"><svg class="ic" aria-hidden="true"><use href="#i-list"/></svg>自分の依頼を見る</a>
    </nav>
</div></header>
<main id="main" class="@yield('mainclass')">
@yield('content')
</main>
<footer><svg class="ic s" aria-hidden="true"><use href="#i-lock"/></svg><span>このページはお客様専用です。リンクはほかの人に教えないでください。</span></footer>
</body>
</html>
