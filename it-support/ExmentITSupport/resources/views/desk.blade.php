@php($Its = \App\Plugins\ExmentITSupport\Its::class)
<div class="its itsd" id="itsd">
@include($Its::view('_nav'), ['tab' => 'inbox'])
<style>
.itsd .its-top{margin-bottom:10px}
.d-app{display:grid;grid-template-columns:220px 400px minmax(0,1fr);gap:0;height:var(--d-h,680px);background:var(--its-box);border:1px solid var(--its-line);border-radius:var(--its-radius);box-shadow:var(--its-shadow);overflow:hidden}
.d-app>*{min-height:0}
.d-app ::-webkit-scrollbar{width:10px;height:10px}
.d-app ::-webkit-scrollbar-thumb{background:var(--its-line3);border-radius:999px;border:3px solid transparent;background-clip:content-box}
.d-app ::-webkit-scrollbar-thumb:hover{background-color:var(--its-mute)}
.d-app ::-webkit-scrollbar-track{background:transparent}
/* queues */
.d-queues{background:var(--its-box2);border-right:1px solid var(--its-line);display:flex;flex-direction:column;overflow:auto;padding-top:4px}
.d-qhead{padding:12px 18px 5px;font-size:11px;font-weight:700;letter-spacing:.04em;color:var(--its-faint)}
.d-q{display:flex;align-items:center;gap:10px;height:32px;margin:1px 8px;padding:0 8px 0 10px;border-radius:8px;color:var(--its-sub);cursor:pointer;user-select:none;font-weight:500;white-space:nowrap;transition:background .1s}
.d-q i.fa{width:16px;text-align:center;color:var(--its-faint);font-size:13.5px}
.d-q span.n{overflow:hidden;text-overflow:ellipsis}
.d-q:hover{background:var(--its-hover);color:var(--its-ink)}
.d-q.on{background:var(--its-sel);color:var(--its-accent-ink);font-weight:600}
.d-q.on i.fa{color:var(--its-accent-ink)}
.its .d-q:focus-visible{outline:2px solid var(--its-accent);outline-offset:-2px;border-radius:8px}
.d-q .c{margin-left:auto;min-width:22px;text-align:center;font-size:11.5px;color:var(--its-sub);border-radius:999px;padding:0 6px;line-height:19px;font-weight:600;font-variant-numeric:tabular-nums}
.d-q .c.zero{color:var(--its-mute);font-weight:500}
.d-q.on .c{color:var(--its-accent-ink)}
.d-q .c.hot{background:var(--its-red);color:#fff}
@media (prefers-color-scheme:dark){.d-q .c.hot{color:#1a0c0d}}
.d-qfoot{margin-top:auto;padding:12px 18px 14px;font-size:11.5px;line-height:1.9;color:var(--its-faint);border-top:1px solid var(--its-line)}
.d-qfoot b{color:var(--its-red)}
/* list */
.d-list{display:flex;flex-direction:column;border-right:1px solid var(--its-line);min-width:0}
.d-lhead{display:flex;gap:4px;align-items:center;padding:10px 10px 10px 12px;border-bottom:1px solid var(--its-line)}
.d-search{position:relative;flex:1;min-width:0}
.d-search i{position:absolute;left:11px;top:10px;color:var(--its-faint);font-size:13px;pointer-events:none}
.its .d-search input{width:100%;padding-left:32px;padding-right:30px;background-color:var(--its-box2);border-color:var(--its-line2);box-shadow:none}
.its .d-search input:focus{background-color:var(--its-box);border-color:var(--its-accent);box-shadow:var(--its-ring)}
.d-search kbd{position:absolute;right:8px;top:8px;pointer-events:none}
.d-search input:focus+kbd,.d-search input:not(:placeholder-shown)+kbd{display:none}
.itsd kbd{display:inline-block;min-width:18px;text-align:center;border:1px solid var(--its-line);border-bottom-width:2px;border-radius:5px;padding:1px 5px;font-size:10.5px;line-height:1.3;font-family:inherit;font-weight:600;background:var(--its-box);color:var(--its-sub);box-shadow:none}
#d-ftoggle.has{color:var(--its-accent-ink)!important;background:var(--its-accent-soft)}
#d-ftoggle b{font-size:11px;font-variant-numeric:tabular-nums}
.d-filters{display:none;padding:12px;border-bottom:1px solid var(--its-line);background:var(--its-box2);gap:10px;flex-direction:column}
.d-filters.on{display:flex}
.d-flabel{font-size:11px;font-weight:700;color:var(--its-faint);margin-bottom:5px}
.d-chips{display:flex;flex-wrap:wrap;gap:5px}
.d-chip{height:26px;padding:0 10px 0 8px;border-radius:999px;border:1px solid var(--its-line);background:var(--its-box);font-size:12px;cursor:pointer;color:var(--its-sub);display:inline-flex;align-items:center;gap:6px;user-select:none;transition:background .1s,border-color .1s}
.d-chip:hover{border-color:var(--its-line3);color:var(--its-ink)}
.d-chip.on{border-color:var(--its-accent);background:var(--its-accent-soft);color:var(--its-accent-ink);font-weight:600}
.d-chip.on::after{content:"\f00c";font-family:FontAwesome;font-size:10px;font-weight:normal}
.d-chip b{width:8px;height:8px;border-radius:50%;display:inline-block}
.d-fgrid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:6px}
.d-fgrid .w{grid-column:1/-1;display:flex;gap:6px}
.its .d-filters select,.its .d-filters input{height:30px;font-size:12px;width:100%;min-width:0}
.its .d-filters .w select{flex:0 0 124px;width:124px}
.d-bulk{display:none;align-items:center;gap:6px;padding:8px 10px 8px 12px;border-bottom:1px solid var(--its-line);background:var(--its-accent-soft);font-size:12px;white-space:nowrap}
.d-bulk.on{display:flex}
.d-bulk .cnt{font-weight:600;color:var(--its-accent-ink)}
.its .d-bulk select{height:28px;font-size:12px;min-width:0;flex:1 1 0}
.d-meta{display:flex;align-items:center;gap:6px;height:34px;padding:0 8px 0 14px;font-size:12px;color:var(--its-sub);border-bottom:1px solid var(--its-line2)}
.d-meta #d-total{font-weight:600;color:var(--its-ink);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.d-meta #d-total small{font-size:12px;font-weight:500;color:var(--its-faint);margin-left:6px}
.its .d-meta select{height:26px;font-size:12px;border-color:transparent;background-color:transparent;color:var(--its-sub);padding-left:6px;box-shadow:none}
.its .d-meta select:hover{background-color:var(--its-hover)}
.d-meta .sp{margin-left:auto}
.its #d-qsel{display:none}
.d-items{flex:1;overflow:auto}
.d-item{position:relative;display:flex;gap:11px;padding:11px 12px 11px 14px;border-bottom:1px solid var(--its-line2);cursor:pointer;transition:background .08s}
.d-item:hover{background:var(--its-hover)}
.d-item.sel{background:var(--its-sel)}
.d-item.sel::before{content:"";position:absolute;left:0;top:0;bottom:0;width:3px;background:var(--its-accent)}
.its .d-item:focus-visible{outline:2px solid var(--its-accent);outline-offset:-2px;border-radius:0}
.its .d-item .ck{position:absolute;left:23px;top:20px;opacity:0;z-index:1}
.d-item:hover .ck,.d-items.bulking .d-item .ck,.its .d-item .ck:focus-visible,.its .d-item .ck:checked{opacity:1}
.d-item:hover>.d-av,.d-items.bulking .d-item>.d-av{visibility:hidden}
@media (hover:none){.d-items:not(.bulking) .d-item>.d-av{visibility:visible}.d-items:not(.bulking) .d-item .ck{opacity:0}}
.d-av{flex:0 0 32px;width:32px;height:32px;border-radius:50%;display:grid;place-items:center;color:#fff;font-weight:700;font-size:12px;letter-spacing:0;line-height:1;overflow:hidden;white-space:nowrap}
.d-av.s{flex-basis:20px;width:20px;height:20px;font-size:9.5px}
.d-av.l{flex-basis:38px;width:38px;height:38px;font-size:14px}
.d-av.none{background:transparent;border:1px dashed var(--its-line3);color:var(--its-mute)}
.d-im{flex:1;min-width:0}
.d-r1{display:flex;align-items:baseline;gap:6px;font-size:12px;color:var(--its-sub);min-width:0;line-height:1.4}
.d-r1 .who{font-weight:600;color:var(--its-ink);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:0 1 auto}
.d-r1 .org{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:0 9999 auto;min-width:0}
.d-r1 .org::before{content:"· "}
.d-r1 .t{margin-left:auto;white-space:nowrap;color:var(--its-faint);font-size:11.5px;font-variant-numeric:tabular-nums;padding-left:6px}
.d-subj{font-size:13.5px;font-weight:600;margin:2px 0 1px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--its-ink)}
.d-item.unread .d-subj{font-weight:700}
.d-item.unread .d-subj::before{content:"";display:inline-block;width:7px;height:7px;border-radius:50%;background:var(--its-accent);margin-right:6px;vertical-align:2px}
.d-item.unread .d-r1 .t{color:var(--its-accent-ink);font-weight:600}
.d-prev{font-size:12px;color:var(--its-sub);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.d-prev .k{color:var(--its-faint);font-variant-numeric:tabular-nums}
.d-r4{display:flex;align-items:center;gap:5px;margin-top:7px;min-width:0}
.d-r4 .chips{display:flex;align-items:center;gap:5px;flex:1;min-width:0;overflow:hidden;height:21px;flex-wrap:wrap}
.d-r4 .who2{flex:0 0 auto;display:inline-flex}
.d-empty{display:grid;place-items:center;height:100%;color:var(--its-sub);text-align:center;padding:30px}
.d-empty i.big{width:56px;height:56px;border-radius:16px;display:grid;place-items:center;margin:0 auto 12px;font-size:24px;color:var(--its-accent-ink);background:var(--its-accent-soft)}
.d-empty b{display:block;font-size:14px;color:var(--its-ink);margin-bottom:2px}
.d-lfoot{display:flex;align-items:center;justify-content:space-between;gap:6px;padding:7px 10px;border-top:1px solid var(--its-line);font-size:12px;color:var(--its-sub);font-variant-numeric:tabular-nums}
/* detail */
.d-detail{display:flex;flex-direction:column;min-width:0;background:var(--its-bg)}
.d-dhead{padding:12px 14px 14px 20px;background:var(--its-box);border-bottom:1px solid var(--its-line)}
.d-crumb{display:flex;align-items:center;gap:8px;font-size:12px;color:var(--its-sub);min-height:28px}
.d-crumb .no{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:11.5px;font-weight:600;color:var(--its-sub);background:var(--its-line2);border-radius:5px;padding:2px 7px;white-space:nowrap}
.d-crumb .m{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;min-width:0}
.d-crumb .acts{margin-left:auto;display:flex;gap:2px;flex:0 0 auto}
.d-title{font-size:19px;font-weight:700;margin:4px 0 10px;line-height:1.4;word-break:break-word;letter-spacing:.005em}
.d-chips2{display:flex;flex-wrap:wrap;gap:6px;align-items:center}
.d-chips2 .its-badge{height:23px;font-size:12px;padding:0 9px}
.d-chips2 .from{display:inline-flex;align-items:center;gap:6px;margin-left:4px;color:var(--its-sub);font-size:12.5px;min-width:0;word-break:break-all}
.d-chips2 .from b{color:var(--its-ink);font-weight:600}
.d-body{flex:1;min-height:0;display:grid;grid-template-columns:minmax(0,1fr) 312px}
.d-body.noside{grid-template-columns:minmax(0,1fr) 0}
.d-main{display:flex;flex-direction:column;min-height:0;min-width:0}
.d-thread{flex:1;overflow:auto;padding:18px 20px 8px}
.d-msg{display:flex;gap:11px;margin:0 auto 14px;max-width:860px}
.d-msg>.d-av{margin-top:2px}
.d-bubble{flex:1;min-width:0;background:var(--its-box);border:1px solid var(--its-line);border-radius:4px 12px 12px 12px;padding:10px 14px 12px;box-shadow:var(--its-shadow)}
.d-msg.reply .d-bubble{border-color:color-mix(in srgb,var(--its-accent) 28%,var(--its-line));background:color-mix(in srgb,var(--its-accent) 5%,var(--its-box))}
.d-msg.note .d-bubble{background:var(--its-note);border-color:var(--its-note-line);box-shadow:none}
.d-mh{display:flex;flex-wrap:wrap;align-items:center;gap:3px 8px;font-size:12px;color:var(--its-sub);margin-bottom:5px}
.d-mh b{color:var(--its-ink);font-size:13px;font-weight:600}
.d-mh .kind{display:inline-flex;align-items:center;gap:4px;font-weight:600;font-size:11.5px;color:var(--its-sub)}
.d-msg.note .d-mh .kind{color:var(--its-note-ink)}
.d-msg.reply .d-mh .kind{color:var(--its-accent-ink)}
.d-mh .bad{color:var(--its-red);font-weight:600}
.d-mh .t{margin-left:auto;color:var(--its-faint);font-size:11.5px;font-variant-numeric:tabular-nums}
.d-mb{white-space:pre-wrap;word-break:break-word;line-height:1.7;font-size:13.5px}
.d-mf{display:inline-flex;align-items:center;gap:6px;margin-top:10px;padding:4px 10px;border:1px solid var(--its-line);border-radius:7px;font-size:12px;color:var(--its-sub);background:var(--its-box);max-width:100%;word-break:break-all}
.d-sys{display:flex;align-items:center;gap:10px;margin:0 auto 14px;max-width:860px;font-size:11.5px;color:var(--its-faint);text-align:center}
.d-sys::before,.d-sys::after{content:"";flex:1;min-width:12px;height:1px;background:var(--its-line)}
.d-sys span{flex:0 1 auto;min-width:0}
.d-sys b{color:var(--its-red)}
.d-banner{display:flex;gap:9px;align-items:flex-start;margin:12px 20px 0;padding:9px 13px;border-radius:10px;font-size:12.5px;line-height:1.6}
.d-banner>i.fa{margin-top:3px}
.d-banner.red{background:var(--its-err-bg);color:var(--its-err-ink);border:1px solid var(--its-err-line)}
.d-banner.amber{background:var(--its-warn-bg);color:var(--its-warn-ink);border:1px solid var(--its-warn-line)}
/* composer */
.d-comp{margin:4px 20px 16px;background:var(--its-box);border:1px solid var(--its-line3);border-radius:12px;box-shadow:var(--its-shadow-m);overflow:hidden;transition:border-color .12s,box-shadow .12s}
.d-comp:focus-within{border-color:var(--its-accent);box-shadow:var(--its-ring),var(--its-shadow-m)}
.d-comp.note{border-color:var(--its-note-line);background:var(--its-note)}
.d-comp.note:focus-within{border-color:var(--its-amber);box-shadow:0 0 0 3px color-mix(in srgb,var(--its-amber) 28%,transparent),var(--its-shadow-m)}
.d-ctabs{display:flex;align-items:center;gap:2px;padding:6px 10px 0 6px;border-bottom:1px solid var(--its-line2)}
.d-comp.note .d-ctabs,.d-comp.note .d-ctools{border-color:var(--its-note-line)}
.d-ctab{display:inline-flex;align-items:center;gap:6px;padding:7px 12px 8px;margin-bottom:-1px;cursor:pointer;color:var(--its-sub);font-weight:600;font-size:12.5px;border-bottom:2px solid transparent;white-space:nowrap;user-select:none}
.d-ctab:hover{color:var(--its-ink)}
.d-ctab.on{color:var(--its-accent-ink);border-bottom-color:var(--its-accent)}
.d-comp.note .d-ctab.on{color:var(--its-note-ink);border-bottom-color:var(--its-amber)}
.its .d-ctab:focus-visible{outline:2px solid var(--its-accent);outline-offset:-2px}
.its .d-ctabs select{margin:0 0 5px auto;height:26px;font-size:12px;max-width:170px;min-width:0;border-color:transparent;background-color:transparent;box-shadow:none;color:var(--its-sub)}
.its .d-ctabs select:hover{background-color:var(--its-hover)}
.d-ctools .d-hint{white-space:nowrap;margin-left:6px}
.d-sys span{text-wrap:balance}
.d-to{font-size:12px;color:var(--its-sub);padding:8px 14px 0;display:flex;align-items:center;gap:6px;flex-wrap:wrap}
.d-to b{color:var(--its-ink);font-weight:600;margin-right:8px;word-break:break-all}
.d-to .warn{color:var(--its-err-ink)}
.its .d-to input{height:24px;font-size:12px;flex:1 1 90px;width:auto;max-width:220px;min-width:0;border-color:transparent;background-color:var(--its-box2);box-shadow:none;padding:0 8px}
.its .d-to input:focus{border-color:var(--its-accent)}
.its .d-comp textarea{display:block;border:0;box-shadow:none;border-radius:0;min-height:92px;max-height:320px;padding:10px 14px;background:transparent;font-size:13.5px}
.d-ctools{display:flex;align-items:center;gap:6px;padding:8px 10px;border-top:1px solid var(--its-line2);flex-wrap:wrap}
.its .d-ctools select,.its .d-ctools input{height:30px;font-size:12px}
.d-min{position:relative;display:inline-flex;align-items:center}
.d-min i{position:absolute;left:9px;color:var(--its-faint);font-size:12px;pointer-events:none}
.its .d-min input{width:76px;padding-left:26px;padding-right:4px}
.d-ctools .send{margin-left:auto;display:flex;gap:6px;align-items:center}
.its .d-ctools .send select{max-width:160px}
.d-hint{font-size:11px;color:var(--its-faint)}
/* side */
.d-side{border-left:1px solid var(--its-line);background:var(--its-box);overflow:auto;min-width:0}
.d-body.noside .d-side{display:none}
.d-sec{padding:14px 16px;border-bottom:1px solid var(--its-line2)}
.d-sec:last-child{border-bottom:0}
.d-sec h4{font-size:11.5px;font-weight:700;letter-spacing:.02em;color:var(--its-sub);margin:0 0 10px;display:flex;align-items:center;gap:7px}
.d-sec h4 i.fa{color:var(--its-faint);width:14px;text-align:center}
.d-field{display:grid;grid-template-columns:72px minmax(0,1fr);align-items:center;gap:6px;margin-bottom:4px}
.d-field label{font-size:12px;color:var(--its-sub)}
.its .d-field select,.its .d-field input{height:30px;font-size:12.5px;width:100%;border-color:transparent;background-color:var(--its-box2);box-shadow:none}
.its .d-field select:hover,.its .d-field input:hover{border-color:var(--its-line3)}
.its .d-field select:focus,.its .d-field input:focus{border-color:var(--its-accent);background-color:var(--its-box);box-shadow:var(--its-ring)}
.its .d-switch{display:flex;cursor:pointer;align-items:center;justify-content:space-between;gap:10px;font-size:12.5px;margin:10px 0 0}
.d-tg{position:relative;width:34px;height:20px;flex:0 0 34px}
.d-tg input{opacity:0;width:0;height:0;position:absolute}
.d-tg span{position:absolute;inset:0;background:var(--its-line3);border-radius:999px;cursor:pointer;transition:background .15s}
.d-tg span::after{content:"";position:absolute;left:2px;top:2px;width:16px;height:16px;border-radius:50%;background:#fff;box-shadow:0 1px 2px rgba(0,0,0,.3);transition:transform .15s}
.d-tg input:checked+span{background:var(--its-accent)}
.d-tg input:checked+span::after{transform:translateX(14px)}
.d-tg input:focus-visible+span{box-shadow:var(--its-ring)}
.d-time{margin-top:12px;padding-top:10px;border-top:1px dashed var(--its-line);display:flex;justify-content:space-between;font-size:12px;color:var(--its-sub)}
.d-time b{color:var(--its-ink);font-variant-numeric:tabular-nums}
.d-slab{display:flex;justify-content:space-between;align-items:baseline;gap:8px;font-size:12.5px;margin:0 0 2px}
.d-slab b{font-weight:600;font-variant-numeric:tabular-nums;text-align:right}
.d-slab+.its-sub{margin-bottom:10px;font-variant-numeric:tabular-nums}
.d-cust{display:flex;gap:10px;align-items:center}
.d-cust b{font-size:13px}
.d-kn a{display:block;padding:7px 8px;margin:0 -8px;border-radius:7px;color:var(--its-ink);text-decoration:none!important;font-size:12.5px;font-weight:500;line-height:1.5}
.d-kn a:hover{background:var(--its-hover)}
.d-kn small{color:var(--its-sub);font-size:11.5px;font-weight:400;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical}
.its .d-kn input{width:100%;height:30px;margin-bottom:4px;font-size:12.5px}
.d-rel a{display:flex;gap:8px;align-items:center;padding:5px 8px;margin:0 -8px;border-radius:7px;font-size:12.5px;color:var(--its-ink);text-decoration:none!important;min-width:0}
.d-rel a:hover{background:var(--its-hover)}
.d-rel a span.s{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;min-width:0}
.d-files a{display:flex;gap:8px;align-items:baseline;padding:4px 0;font-size:12.5px;word-break:break-all}
.d-log{margin-top:10px;font-size:11.5px;color:var(--its-sub);line-height:1.8}
.d-log b{display:block;color:var(--its-ink);font-size:11.5px}
.d-toast{position:fixed;right:22px;bottom:22px;z-index:2000;display:flex;flex-direction:column;gap:8px;align-items:flex-end}
.d-toast div{display:flex;align-items:center;gap:9px;background:var(--its-toast);color:#fff;padding:10px 14px;border-radius:10px;box-shadow:var(--its-shadow-l);font-size:13px;max-width:380px;animation:dIn .18s ease-out}
.d-toast div::before{content:"\f058";font-family:FontAwesome;color:#6ee7a8}
.d-toast div.err{background:#b4232a}
.d-toast div.err::before{content:"\f06a";color:#fff}
@keyframes dIn{from{transform:translateY(8px);opacity:0}to{transform:none;opacity:1}}
.d-skel{height:12px;border-radius:6px;background:linear-gradient(90deg,var(--its-line2),var(--its-hover),var(--its-line2));background-size:200% 100%;animation:dSk 1.2s infinite;margin:8px 0}
@keyframes dSk{to{background-position:-200% 0}}
.d-help{position:fixed;inset:0;background:rgba(10,15,25,.5);z-index:2100;display:none;place-items:center;padding:16px}
.d-help.on{display:grid}
.d-help>div{background:var(--its-box);border:1px solid var(--its-line);border-radius:14px;padding:20px 22px 16px;width:min(400px,100%);box-shadow:var(--its-shadow-l)}
.d-help h3{margin:0 0 10px;font-size:15px;font-weight:700}
.d-help table{width:100%;border-collapse:collapse}
.d-help table td{padding:7px 0;border-bottom:1px solid var(--its-line2);font-size:13px}
.d-help table tr:last-child td{border-bottom:0}
.d-help table td:first-child{width:130px;white-space:nowrap;color:var(--its-faint)}
.d-back{display:none}
/* Layout follows the room the desk really has (Exment's menu takes its share), not the window. */
.d-wrap{container:itsdesk/inline-size}
.d-detail{container:itsdetail/inline-size}
@container itsdetail (max-width:660px){.d-body{display:block;overflow:auto}.d-body.noside{display:block}.d-main{min-height:100%}.d-side{border-left:0;border-top:1px solid var(--its-line)}}
@container itsdetail (max-width:900px){.d-ctools .d-hint{display:none}}
@container itsdesk (max-width:1260px){.d-app{grid-template-columns:200px 344px minmax(0,1fr)}}
@container itsdesk (max-width:900px){.d-app{grid-template-columns:330px minmax(0,1fr)}.d-queues{display:none}.its #d-qsel{display:block;font-weight:600;color:var(--its-ink);max-width:150px}.d-meta #d-total b{display:none}.d-meta #d-total small{margin-left:0}.d-meta{padding-left:8px}}
@container itsdesk (max-width:700px){.d-app{grid-template-columns:minmax(0,1fr)}.d-detail{display:none}.d-app.opened .d-list{display:none}.d-app.opened .d-detail{display:flex}.d-back{display:inline-flex}.d-dhead{padding-left:12px}.d-thread{padding:14px 12px 6px}.d-comp{margin:4px 12px 12px}.d-banner{margin:10px 12px 0}.d-title{font-size:17px}.its .d-item .ck{display:none}}
@media (max-width:700px){.d-toast{right:12px;left:12px;bottom:12px}}
</style>

<div class="d-wrap"><div class="d-app" id="d-app">
    <aside class="d-queues" aria-label="受付箱">
        <div class="d-qhead">受付箱</div>
        <div id="d-qlist"></div>
        <div class="d-qfoot" id="d-qfoot"></div>
    </aside>

    <section class="d-list" aria-label="チケットの一覧">
        <div class="d-lhead">
            <div class="d-search"><i class="fa fa-search" aria-hidden="true"></i><input type="search" id="d-kw" placeholder="件名・本文・やりとり・メール・番号" aria-label="チケットを探す" autocomplete="off"><kbd aria-hidden="true">/</kbd></div>
            <button type="button" class="its-btn small ghost" id="d-ftoggle" title="絞り込み" aria-label="絞り込み" aria-expanded="false" aria-controls="d-filters"><i class="fa fa-sliders"></i></button>
            <button type="button" class="its-btn small ghost icon" id="d-reload" title="更新" aria-label="更新"><i class="fa fa-refresh" aria-hidden="true"></i></button>
        </div>
        <div class="d-filters" id="d-filters">
            <div><div class="d-flabel">状態</div><div class="d-chips" id="d-fstatus"></div></div>
            <div><div class="d-flabel">優先度</div><div class="d-chips" id="d-fprio"></div></div>
            <div class="d-fgrid">
                <select id="d-fassignee" aria-label="担当者"></select>
                <select id="d-fcustomer" aria-label="お客様"></select>
                <select id="d-fcategory" aria-label="種類" class="w"></select>
                <div class="w">
                    <input type="text" id="d-ftags" placeholder="タグ（カンマ区切り）" aria-label="タグ">
                    <select id="d-ftagmode" aria-label="タグの合わせ方"><option value="and">すべて含む</option><option value="or">どれか</option></select>
                </div>
            </div>
            <div><button type="button" class="its-btn small ghost" id="d-fclear"><i class="fa fa-times" aria-hidden="true"></i>クリア</button></div>
        </div>
        <div class="d-bulk" id="d-bulk">
            <span class="cnt"><b id="d-bcount"></b> 件の</span>
            <select id="d-baction" aria-label="変える項目"><option value="status">状態</option><option value="priority">優先度</option><option value="assignee">担当者</option></select>
            <span>を</span>
            <select id="d-bvalue" aria-label="変えたあとの値"></select>
            <button type="button" class="its-btn small primary" id="d-bapply">変える</button>
            <button type="button" class="its-btn small ghost" id="d-bcancel">やめる</button>
        </div>
        <div class="d-meta"><select id="d-qsel" aria-label="受付箱"></select><span id="d-total"></span><span class="sp"></span>
            <select id="d-sort" aria-label="並び順"><option value="due">期限が近い順</option><option value="priority">優先度順</option><option value="new">新しい順</option><option value="old">古い順</option><option value="updated">更新順</option></select>
        </div>
        <div class="d-items" id="d-items"></div>
        <div class="d-lfoot"><button type="button" class="its-btn small icon" id="d-prev" aria-label="前のページ" title="前のページ"><i class="fa fa-chevron-left" aria-hidden="true"></i></button><span id="d-page"></span><button type="button" class="its-btn small icon" id="d-next" aria-label="次のページ" title="次のページ"><i class="fa fa-chevron-right" aria-hidden="true"></i></button></div>
    </section>

    <section class="d-detail" id="d-detail" aria-label="チケット">
        <div class="d-empty"><div><i class="fa fa-comments-o big" aria-hidden="true"></i><b>チケットを選んでください</b><div class="d-hint" style="margin-top:10px"><kbd>J</kbd> <kbd>K</kbd> で上下 · <kbd>?</kbd> でショートカット</div></div></div>
    </section>
</div>
</div>
<div class="d-toast" id="d-toast" role="status" aria-live="polite"></div>
<div class="d-help" id="d-help" role="dialog" aria-modal="true" aria-labelledby="d-help-t"><div>
    <h3 id="d-help-t">キーボードショートカット</h3>
    <table>
        <tr><td><kbd>J</kbd> / <kbd>K</kbd></td><td>次・前のチケット</td></tr>
        <tr><td><kbd>/</kbd></td><td>探す</td></tr>
        <tr><td><kbd>R</kbd></td><td>お客様へ返信</td></tr>
        <tr><td><kbd>N</kbd></td><td>社内メモ</td></tr>
        <tr><td><kbd>Ctrl</kbd> + <kbd>Enter</kbd></td><td>送る</td></tr>
        <tr><td><kbd>A</kbd></td><td>自分を担当者にする</td></tr>
        <tr><td><kbd>E</kbd></td><td>解決にする</td></tr>
        <tr><td><kbd>\</kbd></td><td>右の欄を開く・閉じる</td></tr>
        <tr><td><kbd>Esc</kbd></td><td>閉じる・入力から抜ける</td></tr>
    </table>
    <div style="text-align:right;margin-top:12px"><button type="button" class="its-btn small" onclick="document.getElementById('d-help').classList.remove('on')">閉じる</button></div>
</div></div>
</div>
<script>
(function () {
'use strict';
var root = document.getElementById('itsd');
if (!root || root.dataset.ready) { return; }
root.dataset.ready = '1';

var B = @json($boot);
var CSRF = @json(csrf_token());
var S = { queue: B.queue, page: 1, items: [], counts: {}, sel: null, ticket: null, f: { status: [], priority: [], assignee: '', customer: '', category: '', tags: '', tag_mode: 'and', kw: '', sort: 'due' }, checked: {}, kind: 'reply', side: true, loading: false };
try { S.side = localStorage.getItem('its_side') !== '0'; } catch (e) {}

var $ = function (id) { return document.getElementById(id); };
var app = $('d-app');
var QICON = { todo: 'fa-inbox', mine: 'fa-user', unassigned: 'fa-user-plus', replied: 'fa-reply', sla: 'fa-clock-o', pending: 'fa-hourglass-half', on_hold: 'fa-pause-circle', alerts: 'fa-bell', resolved: 'fa-check-circle', closed: 'fa-archive', all: 'fa-list' };
var KIND = { customer: 'お客様から', reply: 'お客様への返信', note: '社内メモ', system: 'システム' };
var KICON = { reply: 'fa-reply', note: 'fa-lock' };
var PALETTE = ['#4f6bed', '#7c5cf0', '#e5484d', '#30a46c', '#f08c2b', '#0ea5a4', '#d6409f', '#3e8bea', '#8e4ec6', '#c2701b'];

function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
function u(name, id) { return B.url[name].replace('__ID__', id); }
function hue(s) { var h = 0; s = String(s || '?'); for (var i = 0; i < s.length; i++) { h = (h * 31 + s.charCodeAt(i)) >>> 0; } return PALETTE[h % PALETTE.length]; }
function initials(s) { s = String(s || '?').trim(); if (!s) { return '?'; } if (/^[\x00-\x7F]/.test(s)) { var p = s.split(/[\s@._-]+/).filter(Boolean); return ((p[0] || '?')[0] + ((p[1] || '')[0] || '')).toUpperCase(); } return s.replace(/\s/g, '').slice(0, 2); }
function av(name, cls) { return '<span class="d-av ' + (cls || '') + '" style="background:' + hue(name) + '">' + esc(initials(name)) + '</span>'; }
var CHICON = { monitor: ['fa-bell', '#e5484d', '監視'], asset: ['fa-desktop', '#0ea5a4', 'IT 資産'] };
function avFor(channel, name, cls) { var c = CHICON[channel]; return c ? '<span class="d-av ' + (cls || '') + '" style="background:' + c[1] + '"><i class="fa ' + c[0] + '"></i></span>' : av(name, cls); }
function rel(iso) {
    if (!iso) { return ''; }
    var d = new Date(iso), s = (Date.now() - d.getTime()) / 1000;
    if (s < 60) { return 'いま'; } if (s < 3600) { return Math.floor(s / 60) + '分前'; } if (s < 86400) { return Math.floor(s / 3600) + '時間前'; }
    if (s < 86400 * 7) { return Math.floor(s / 86400) + '日前'; }
    return (d.getMonth() + 1) + '/' + d.getDate();
}
function fmt(iso) { if (!iso) { return ''; } var d = new Date(String(iso).replace(' ', 'T')); if (isNaN(d)) { return iso; } var p = function (n) { return ('0' + n).slice(-2); }; return d.getFullYear() + '/' + p(d.getMonth() + 1) + '/' + p(d.getDate()) + ' ' + p(d.getHours()) + ':' + p(d.getMinutes()); }
// Badges: a tinted chip with a word in it. --c is the color; the look (and contrast) comes from the stylesheet.
function pill(text, color) { return '<span class="its-badge" style="--c:' + color + '"><span class="dot"></span>' + esc(text) + '</span>'; }
function soft(text, color, icon, title) { return '<span class="its-badge" style="--c:' + color + '"' + (title ? ' title="' + esc(title) + '"' : '') + '>' + (icon ? '<i class="fa ' + icon + '" aria-hidden="true"></i>' : '') + esc(text) + '</span>'; }
var PRIO_ICON = { urgent: 'fa-exclamation-circle', high: 'fa-arrow-up', normal: 'fa-minus', low: 'fa-arrow-down' };
function prio(p, withWord) { return soft((withWord ? '優先度 ' : '') + (B.priorities[p] || p), B.priorityColors[p] || '#888', PRIO_ICON[p] || 'fa-minus', '優先度: ' + (B.priorities[p] || p)); }
function toast(msg, err) { var t = document.createElement('div'); if (err) { t.className = 'err'; } t.textContent = msg; $('d-toast').appendChild(t); setTimeout(function () { t.remove(); }, err ? 6000 : 3200); }
function opts(map, sel, blank) { var h = blank != null ? '<option value="">' + esc(blank) + '</option>' : ''; Object.keys(map).forEach(function (k) { h += '<option value="' + esc(k) + '"' + (String(sel) === String(k) ? ' selected' : '') + '>' + esc(map[k]) + '</option>'; }); return h; }

function api(url, data) {
    var o = { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF }, credentials: 'same-origin' };
    if (data) { o.method = 'POST'; var fd = data instanceof FormData ? data : new FormData(); if (!(data instanceof FormData)) { Object.keys(data).forEach(function (k) { var v = data[k]; if (Array.isArray(v)) { v.forEach(function (x) { fd.append(k + '[]', x); }); } else { fd.append(k, v == null ? '' : v); } }); } fd.append('_token', CSRF); o.body = fd; }
    return fetch(url, o).then(function (r) { return r.json().catch(function () { return { ok: false, message: 'サーバーの応答を読めませんでした（' + r.status + '）' }; }).then(function (j) { j._status = r.status; return j; }); });
}

// ---- sizing: the app fills the window below Exment's header -------------
function fit() { var top = app.getBoundingClientRect().top + window.scrollY; app.style.setProperty('--d-h', Math.max(480, window.innerHeight - (top - window.scrollY) - 18) + 'px'); }
window.addEventListener('resize', fit); fit(); setTimeout(fit, 300);

// ---- queues -------------------------------------------------------------
function renderQueues() {
    var h = '', sel = '';
    Object.keys(B.queues).forEach(function (q) {
        var n = S.counts[q];
        var hot = (q === 'sla' || q === 'replied' || q === 'unassigned') && n > 0;
        h += '<div class="d-q' + (S.queue === q ? ' on' : '') + '" data-q="' + q + '" role="button" tabindex="0"' + (S.queue === q ? ' aria-current="true"' : '') + '><i class="fa ' + (QICON[q] || 'fa-circle-o') + '" aria-hidden="true"></i><span class="n">' + esc(B.queues[q]) + '</span>' + (n != null ? '<span class="c' + (hot ? ' hot' : (n ? '' : ' zero')) + '">' + n + '</span>' : '') + '</div>';
        if (q === 'alerts') { h += '<div class="d-qhead">状態</div>'; }
        sel += '<option value="' + q + '"' + (S.queue === q ? ' selected' : '') + '>' + esc(B.queues[q]) + (n != null ? '（' + n + '）' : '') + '</option>';
    });
    var hadFocus = document.activeElement && document.activeElement.classList.contains('d-q') ? document.activeElement.dataset.q : null;
    $('d-qlist').innerHTML = h;
    if (hadFocus) { var again = $('d-qlist').querySelector('[data-q="' + hadFocus + '"]'); if (again) { again.focus(); } }
    if (document.activeElement !== $('d-qsel')) { $('d-qsel').innerHTML = sel; }
    var st = B.state;
    $('d-qfoot').innerHTML = '<div><span class="its-dot ' + (st.imap_error ? 'err' : (st.imap_on ? 'on' : '')) + '" aria-hidden="true"></span>メール受信 ' + (st.imap_error ? '<b>エラー</b>' : (st.imap_on ? 'オン' : 'オフ')) + '</div><div><i class="fa fa-magic" aria-hidden="true" style="width:8px;margin-right:6px;font-size:10px"></i>自動処理 ' + esc(st.batch_at ? rel(st.batch_at.replace(' ', 'T')) : 'まだ') + '</div>';
}
function pickQueue(q) { S.queue = q; S.page = 1; S.checked = {}; renderBulk(); load(); }
$('d-qlist').addEventListener('click', function (e) { var q = e.target.closest('.d-q'); if (!q) { return; } pickQueue(q.dataset.q); });
$('d-qsel').onchange = function () { pickQueue(this.value); };

// ---- filters ------------------------------------------------------------
function renderFilters() {
    var chips = function (map, colors, key) { return Object.keys(map).map(function (k) { var on = S.f[key].indexOf(k) >= 0; return '<span class="d-chip' + (on ? ' on' : '') + '" data-k="' + key + '" data-v="' + k + '" role="button" tabindex="0" aria-pressed="' + (on ? 'true' : 'false') + '"><b style="background:' + colors[k] + '"></b>' + esc(map[k]) + '</span>'; }).join(''); };
    $('d-fstatus').innerHTML = chips(B.statuses, B.statusColors, 'status');
    $('d-fprio').innerHTML = chips(B.priorities, B.priorityColors, 'priority');
    $('d-fassignee').innerHTML = '<option value="">担当者: すべて</option><option value="none"' + (S.f.assignee === 'none' ? ' selected' : '') + '>担当者なし</option>' + opts(B.agents, S.f.assignee);
    $('d-fcustomer').innerHTML = opts(B.customers, S.f.customer, 'お客様: すべて');
    $('d-fcategory').innerHTML = opts(B.categories, S.f.category, '種類: すべて');
    $('d-ftags').value = S.f.tags; $('d-ftagmode').value = S.f.tag_mode;
    var n = S.f.status.length + S.f.priority.length + (S.f.assignee ? 1 : 0) + (S.f.customer ? 1 : 0) + (S.f.category ? 1 : 0) + (S.f.tags ? 1 : 0);
    $('d-ftoggle').innerHTML = '<i class="fa fa-sliders" aria-hidden="true"></i>' + (n ? ' <b>' + n + '</b>' : '');
    $('d-ftoggle').classList.toggle('has', n > 0); $('d-ftoggle').classList.toggle('icon', !n);
    $('d-ftoggle').setAttribute('aria-label', n ? '絞り込み（' + n + ' 個）' : '絞り込み');
}
$('d-ftoggle').onclick = function () { this.setAttribute('aria-expanded', $('d-filters').classList.toggle('on') ? 'true' : 'false'); };
$('d-filters').addEventListener('click', function (e) { var c = e.target.closest('.d-chip'); if (!c) { return; } var a = S.f[c.dataset.k], i = a.indexOf(c.dataset.v); if (i >= 0) { a.splice(i, 1); } else { a.push(c.dataset.v); } S.page = 1; var back = document.activeElement === c ? [c.dataset.k, c.dataset.v] : null; renderFilters(); if (back) { var el = $('d-filters').querySelector('.d-chip[data-k="' + back[0] + '"][data-v="' + back[1] + '"]'); if (el) { el.focus(); } } load(); });
['d-fassignee', 'd-fcustomer', 'd-fcategory', 'd-ftagmode'].forEach(function (id) { $(id).onchange = function () { S.f.assignee = $('d-fassignee').value; S.f.customer = $('d-fcustomer').value; S.f.category = $('d-fcategory').value; S.f.tag_mode = $('d-ftagmode').value; S.page = 1; renderFilters(); load(); }; });
var tagTimer; $('d-ftags').oninput = function () { clearTimeout(tagTimer); tagTimer = setTimeout(function () { S.f.tags = $('d-ftags').value.trim(); S.page = 1; load(); }, 400); };
$('d-fclear').onclick = function () { S.f.status = []; S.f.priority = []; S.f.assignee = S.f.customer = S.f.category = S.f.tags = ''; S.page = 1; renderFilters(); load(); };
var kwTimer; $('d-kw').oninput = function () { clearTimeout(kwTimer); kwTimer = setTimeout(function () { S.f.kw = $('d-kw').value.trim(); S.page = 1; load(); }, 300); };
$('d-sort').onchange = function () { S.f.sort = this.value; load(); };
$('d-reload').onclick = function () { load(); if (S.sel) { openTicket(S.sel, true); } };
$('d-prev').onclick = function () { if (S.page > 1) { S.page--; load(); } };
$('d-next').onclick = function () { if (S.page < S.pages) { S.page++; load(); } };

// ---- list ---------------------------------------------------------------
function load(silent) {
    var p = new URLSearchParams({ queue: S.queue, page: S.page, kw: S.f.kw, assignee: S.f.assignee, customer: S.f.customer, category: S.f.category, tags: S.f.tags, tag_mode: S.f.tag_mode, sort: S.f.sort });
    S.f.status.forEach(function (x) { p.append('status[]', x); }); S.f.priority.forEach(function (x) { p.append('priority[]', x); });
    if (!silent) { $('d-items').innerHTML = '<div style="padding:14px">' + '<div class="d-skel"></div><div class="d-skel" style="width:70%"></div><div class="d-skel" style="width:85%"></div>'.repeat(3) + '</div>'; }
    return api(B.url.list + '?' + p.toString()).then(function (d) {
        if (d.error) { toast('一覧を読めませんでした', true); return; }
        S.items = d.items; S.counts = d.counts; S.total = d.total; S.pages = d.pages; S.page = d.page;
        renderQueues(); renderList(); syncUrl();
    });
}
function slaChip(it) {
    if (it.sla === 'none' || !it.sla_left) { return it.sla === 'met' ? soft('SLA 達成', B.slaColors.met, 'fa-check') : ''; }
    return soft(it.sla_kind + ' ' + it.sla_left, B.slaColors[it.sla] || '#888', it.sla === 'breached' ? 'fa-exclamation-triangle' : 'fa-clock-o', 'SLA: ' + (B.slaStates[it.sla] || ''));
}
function renderList() {
    var box = $('d-items');
    $('d-total').innerHTML = '<b>' + esc(B.queues[S.queue]) + '</b><small>' + S.total + ' 件</small>';
    $('d-page').textContent = S.page + ' / ' + S.pages;
    $('d-prev').disabled = S.page <= 1; $('d-next').disabled = S.page >= S.pages;
    $('d-sort').value = S.f.sort;
    if (!S.items.length) { box.innerHTML = '<div class="d-empty"><div><i class="fa fa-check big" aria-hidden="true"></i><b>チケットはありません</b>' + (S.f.kw || $('d-ftoggle').classList.contains('has') ? '探す言葉や絞り込みを変えてみてください。' : 'この受付箱は空です。') + '</div></div>'; return; }
    var keepFocus = document.activeElement && document.activeElement.classList.contains('d-item') ? document.activeElement.dataset.id : null;
    box.innerHTML = S.items.map(function (it) {
        var who = it.requester || (CHICON[it.channel] ? CHICON[it.channel][2] : '—');
        return '<div class="d-item' + (S.sel === it.id ? ' sel' : '') + (it.waiting ? ' unread' : '') + '" data-id="' + it.id + '" role="button" tabindex="0" aria-label="' + esc(it.no + ' ' + it.subject + '、' + (B.statuses[it.status] || it.status) + (it.waiting ? '、お客様から返事あり' : '')) + '">' +
            '<input type="checkbox" class="ck" data-ck="' + it.id + '"' + (S.checked[it.id] ? ' checked' : '') + ' aria-label="' + esc(it.no) + ' を選ぶ">' +
            avFor(it.channel, it.requester || it.subject) +
            '<div class="d-im"><div class="d-r1"><span class="who">' + esc(who) + '</span>' + (it.customer ? '<span class="org">' + esc(it.customer) + '</span>' : '') + '<span class="t" title="' + esc(fmt(it.updated)) + '">' + rel(it.updated) + '</span></div>' +
            '<div class="d-subj">' + esc(it.subject) + '</div>' +
            '<div class="d-prev"><span class="k">' + esc(it.no) + ' · </span>' + (it.last_kind === 'reply' ? '<i class="fa fa-reply" aria-hidden="true" title="最後は担当者の返信"></i> ' : (it.last_kind === 'note' ? '<i class="fa fa-lock" aria-hidden="true" title="最後は社内メモ"></i> ' : '')) + esc(it.preview) + '</div>' +
            '<div class="d-r4"><span class="chips">' + pill(B.statuses[it.status] || it.status, B.statusColors[it.status] || '#888') +
            (it.priority === 'urgent' || it.priority === 'high' ? prio(it.priority) : '') + slaChip(it) +
            (it.occurrences > 1 ? soft(it.occurrences + ' 回', '#e5484d', 'fa-repeat', '同じアラートが ' + it.occurrences + ' 回') : '') +
            (it.internal ? soft('社内', '#8b95a3', 'fa-eye-slash', 'お客様には見せない') : '') +
            it.tags.slice(0, 3).map(function (t) { return '<span class="its-tag">' + esc(t) + '</span>'; }).join('') +
            '</span><span class="who2" title="' + esc(it.assignee_name ? '担当: ' + it.assignee_name : '担当者なし') + '">' + (it.assignee_name ? av(it.assignee_name, 's') : '<span class="d-av s none" aria-label="担当者なし"></span>') + '</span>' +
            '</div></div></div>';
    }).join('');
    if (keepFocus) { var kf = box.querySelector('.d-item[data-id="' + keepFocus + '"]'); if (kf) { kf.focus({ preventScroll: true }); } }
    box.classList.toggle('bulking', Object.keys(S.checked).length > 0);
}
$('d-items').addEventListener('click', function (e) {
    var ck = e.target.closest('[data-ck]');
    if (ck) { e.stopPropagation(); if (ck.checked) { S.checked[ck.dataset.ck] = true; } else { delete S.checked[ck.dataset.ck]; } renderBulk(); $('d-items').classList.toggle('bulking', Object.keys(S.checked).length > 0); return; }
    var it = e.target.closest('.d-item'); if (it) { openTicket(parseInt(it.dataset.id, 10)); }
});

// ---- bulk ---------------------------------------------------------------
function renderBulk() {
    var n = Object.keys(S.checked).length;
    $('d-bulk').classList.toggle('on', n > 0); $('d-bcount').textContent = n;
    var a = $('d-baction').value;
    $('d-bvalue').innerHTML = a === 'status' ? opts(B.statuses) : (a === 'priority' ? opts(B.priorities) : '<option value="">（担当者なし）</option>' + opts(B.agents, B.me.id));
}
$('d-baction').onchange = renderBulk;
$('d-bcancel').onclick = function () { S.checked = {}; renderBulk(); renderList(); };
$('d-bapply').onclick = function () {
    api(B.url.bulk, { action: $('d-baction').value, value: $('d-bvalue').value, ids: Object.keys(S.checked) }).then(function (r) { toast(r.message || '変えました', !r.ok); S.checked = {}; renderBulk(); load(true); if (S.sel) { openTicket(S.sel, true); } });
};

// ---- ticket -------------------------------------------------------------
var lockTimer = null, lastHold = 0;
function openTicket(id, keep) {
    if (!id) { return; }
    var changing = S.sel !== id;
    if (changing) { saveDraft(); }
    S.sel = id; app.classList.add('opened');
    document.querySelectorAll('.d-item').forEach(function (el) { el.classList.toggle('sel', parseInt(el.dataset.id, 10) === id); });
    if (!keep) { $('d-detail').innerHTML = '<div style="padding:24px"><div class="d-skel" style="width:30%"></div><div class="d-skel" style="height:22px;width:60%"></div><div class="d-skel"></div><div class="d-skel" style="width:80%"></div></div>'; }
    api(u('ticket', id)).then(function (t) {
        if (t.error) { $('d-detail').innerHTML = '<div class="d-empty"><div><i class="fa fa-question big" aria-hidden="true"></i><b>チケットが見つかりません</b></div></div>'; return; }
        if (S.sel !== id) { return; }
        var draft = keep && S.ticket && S.ticket.id === id ? { body: $('d-body') ? $('d-body').value : '', kind: S.kind } : null;
        S.ticket = t; renderTicket(draft); syncUrl();
    });
}
function syncUrl() {
    var url = B.url.base.replace(/\/$/, '') + (S.sel ? '/ticket/' + S.sel : '') + '?queue=' + encodeURIComponent(S.queue);
    try { history.replaceState(history.state, '', url); } catch (e) {}
}
function msgHtml(m) {
    if (m.kind === 'system') { return '<div class="d-sys"><span>' + esc(m.body) + ' · ' + esc(fmt(m.at)) + (m.mail === 'failed' ? ' · <b><i class="fa fa-exclamation-circle" aria-hidden="true"></i> メールを送れませんでした</b>' : '') + '</span></div>'; }
    var name = m.author || (m.kind === 'customer' ? 'お客様' : '担当者');
    return '<div class="d-msg ' + m.kind + '">' + av(name) + '<div class="d-bubble"><div class="d-mh"><b>' + esc(name) + '</b><span class="kind">' + (KICON[m.kind] ? '<i class="fa ' + KICON[m.kind] + '" aria-hidden="true"></i>' : '') + esc(KIND[m.kind] || m.kind) + '</span>' +
        (m.kind === 'customer' && m.via ? '<span>' + esc(B.channels[m.via] || m.via) + '</span>' : '') +
        (m.mail === 'sent' ? '<span><i class="fa fa-envelope-o" aria-hidden="true"></i> 送信済み</span>' : '') + (m.mail === 'failed' ? '<span class="bad"><i class="fa fa-exclamation-circle" aria-hidden="true"></i> 送信できず</span>' : '') +
        (m.minutes ? '<span><i class="fa fa-clock-o" aria-hidden="true"></i> ' + m.minutes + ' 分</span>' : '') +
        '<span class="t" title="' + esc(fmt(m.at)) + '">' + rel(m.at) + '</span></div><div class="d-mb">' + esc(m.body) + '</div>' + (m.files ? '<div class="d-mf"><i class="fa fa-paperclip" aria-hidden="true"></i> ' + esc(m.files) + '</div>' : '') + '</div></div>';
}
function slaBlock(t) {
    var s = t.sla, rows = '';
    var line = function (label, due, done, left) {
        if (!due) { return ''; }
        var state = done ? (new Date(String(done).replace(' ', 'T')) > new Date(String(due).replace(' ', 'T')) ? 'breached' : 'met') : (left.indexOf('超過') >= 0 ? 'breached' : (t.sla.state === 'warning' ? 'warning' : 'ok'));
        var color = B.slaColors[state];
        var ic = state === 'breached' ? 'fa-exclamation-triangle' : (done ? 'fa-check' : 'fa-clock-o');
        return '<div class="d-slab"><span>' + label + '</span><b class="its-tone" style="--c:' + color + '"><i class="fa ' + ic + '" aria-hidden="true"></i> ' + (done ? (state === 'met' ? '期限内' : '超過して完了') : esc(left)) + '</b></div><div class="its-sub">期限 ' + esc(fmt(due)) + '</div>';
    };
    rows += line('最初の返事', s.first_due, s.first_done, s.first_left || '');
    rows += line('解決', s.resolve_due, s.resolve_done, s.resolve_left || '');
    if (!rows) { return '<div class="its-sub">SLA はありません（お客様に SLA が決まっていないか、標準の SLA がありません）。</div>'; }
    return rows + (s.paused ? '<div class="its-sub"><i class="fa fa-pause" aria-hidden="true"></i> お客様の返事待ちの間、時計を止めています</div>' : '');
}
function renderTicket(draft) {
    var t = S.ticket, st = t.status;
    var tpl = '<option value="">定型文…</option>' + B.templates.map(function (x) { return '<option value="' + x.id + '">' + esc(x.name) + '</option>'; }).join('');
    var h = '<div class="d-dhead"><div class="d-crumb"><button type="button" class="its-btn small ghost icon d-back" id="d-back" aria-label="一覧へ戻る" title="一覧へ戻る"><i class="fa fa-chevron-left" aria-hidden="true"></i></button><span class="no">' + esc(t.no) + '</span><span class="m">' + esc(B.channels[t.channel] || '') + ' · 受付 ' + esc(fmt(t.created)) + '</span>' +
        '<span class="acts"><a class="its-btn small ghost icon" href="' + esc(u('edit', t.id)) + '" title="Exment の画面ですべての項目を編集" aria-label="Exment の画面ですべての項目を編集"><i class="fa fa-pencil" aria-hidden="true"></i></a><button type="button" class="its-btn small ghost icon" id="d-sidebtn" title="右の欄を開く・閉じる（\\）" aria-label="右の欄を開く・閉じる" aria-pressed="' + (S.side ? 'true' : 'false') + '"><i class="fa fa-columns" aria-hidden="true"></i></button></span></div>' +
        '<h2 class="d-title">' + esc(t.subject) + '</h2><div class="d-chips2">' + pill(B.statuses[st] || st, B.statusColors[st] || '#888') + prio(t.priority, true) +
        (t.sla.state !== 'none' ? soft('SLA ' + (B.slaStates[t.sla.state] || ''), B.slaColors[t.sla.state] || '#888', t.sla.state === 'breached' ? 'fa-exclamation-triangle' : (t.sla.state === 'met' ? 'fa-check' : 'fa-clock-o')) : '') +
        (!t.customer_visible ? soft('お客様には見せない', '#8b95a3', 'fa-eye-slash') : '') + (t.require_otp ? soft('確認のコードあり', '#8e4ec6', 'fa-shield') : '') +
        (t.occurrences > 1 ? soft(t.occurrences + ' 回発生', '#e5484d', 'fa-repeat') : '') + (t.csat ? soft('満足度 ' + t.csat + ' / 5', '#f08c2b', 'fa-star') : '') +
        (t.requester_name || t.requester_email ? '<span class="from"><i class="fa fa-user-o" aria-hidden="true"></i><span>' + (t.requester_name ? '<b>' + esc(t.requester_name) + '</b> ' : '') + (t.requester_email ? (t.requester_name ? '&lt;' + esc(t.requester_email) + '&gt;' : esc(t.requester_email)) : '') + '</span></span>' : '') + '</div></div>';

    h += '<div class="d-body' + (S.side ? '' : ' noside') + '" id="d-bodybox"><div class="d-main">';
    if (t.other_editor) { h += '<div class="d-banner red" id="d-lockbar" role="alert"><i class="fa fa-user" aria-hidden="true"></i> ' + esc(t.other_editor) + ' さんが返事を書いています。二重に返事をしないよう確かめてください。</div>'; } else { h += '<div class="d-banner red" id="d-lockbar" role="alert" style="display:none"></div>'; }
    if (t.has_password) { h += '<div class="d-banner amber"><i class="fa fa-exclamation-triangle" aria-hidden="true"></i><span>パスワードらしき文字があります。チケットに残さず、別の安全な場所で預かってください。</span></div>'; }
    h += '<div class="d-thread" id="d-thread"><div class="d-msg customer">' + avFor(t.channel, t.requester_name || t.requester_email || 'お客様') + '<div class="d-bubble"><div class="d-mh"><b>' + esc(t.requester_name || t.requester_email || (CHICON[t.channel] ? CHICON[t.channel][2] : 'お客様')) + '</b><span class="kind">内容</span><span class="t" title="' + esc(fmt(t.created)) + '">' + rel(t.created) + '</span></div><div class="d-mb">' + esc(t.description) + '</div>' + (t.description_files ? '<div class="d-mf"><i class="fa fa-paperclip" aria-hidden="true"></i> ' + esc(t.description_files) + '</div>' : '') + '</div></div>';
    h += t.messages.map(msgHtml).join('') + '</div>';

    h += '<div class="d-comp" id="d-comp"><div class="d-ctabs"><span class="d-ctab" data-kind="reply" role="button" tabindex="0"><i class="fa fa-reply" aria-hidden="true"></i>お客様へ返信</span><span class="d-ctab" data-kind="note" role="button" tabindex="0"><i class="fa fa-lock" aria-hidden="true"></i>社内メモ</span>' +
        '<select id="d-tpl" aria-label="定型文を入れる" title="定型文を入れる">' + tpl + '</select></div>' +
        '<div class="d-to" id="d-to">' + (t.requester_email ? '<span>宛先</span><b>' + esc(t.requester_email) + '</b><label for="d-cc">CC</label><input type="text" id="d-cc" value="' + esc(t.cc) + '" placeholder="なし">' : '<span class="warn"><i class="fa fa-exclamation-circle" aria-hidden="true"></i> お客様のメールアドレスがないので、メールでは届きません（チケットとリンクに残ります）。</span>') + '</div>' +
        '<textarea id="d-body" placeholder="返事を書く…" aria-label="本文"></textarea>' +
        '<div class="d-ctools"><label class="its-btn small ghost" title="ファイルを付ける" aria-label="ファイルを付ける"><i class="fa fa-paperclip" aria-hidden="true"></i><span id="d-fcount"></span><input type="file" id="d-files" multiple style="display:none"></label>' +
        '<label class="d-min" title="この返事・メモにかかった作業時間（分）"><i class="fa fa-clock-o" aria-hidden="true"></i><input type="number" id="d-min" min="0" step="5" placeholder="分" aria-label="作業時間（分）"></label><span class="d-hint"><kbd>Ctrl</kbd> + <kbd>Enter</kbd> で送る</span>' +
        '<div class="send"><select id="d-after" aria-label="送ったあとの状態"><option value="">状態はそのまま</option>' + opts(B.statuses) + '</select><button type="button" class="its-btn primary" id="d-send"><i class="fa fa-paper-plane" aria-hidden="true"></i><span>送る</span></button></div></div></div>';
    h += '</div>';

    // side
    var agents = '<option value="">（なし）</option>' + opts(B.agents, t.assignee);
    var assets = Object.keys(t.assets || {}).length ? '<div class="d-field"><label for="d-p-asset">IT 資産</label><select id="d-p-asset" data-p="asset"><option value="">（なし）</option>' + opts(t.assets, t.asset) + '</select></div>' : '';
    h += '<aside class="d-side" aria-label="チケットの項目">' +
        '<div class="d-sec"><h4><i class="fa fa-sliders" aria-hidden="true"></i>チケット</h4>' +
        '<div class="d-field"><label for="d-p-status">状態</label><select id="d-p-status" data-p="status">' + opts(B.statuses, st) + '</select></div>' +
        '<div class="d-field"><label for="d-p-priority">優先度</label><select id="d-p-priority" data-p="priority">' + opts(B.priorities, t.priority) + '</select></div>' +
        '<div class="d-field"><label for="d-p-assignee">担当者</label><select id="d-p-assignee" data-p="assignee">' + agents + '</select></div>' +
        '<div class="d-field"><label for="d-p-category">種類</label><select id="d-p-category" data-p="category">' + opts(B.categories, t.category, '（なし）') + '</select></div>' +
        '<div class="d-field"><label for="d-p-customer">お客様</label><select id="d-p-customer" data-p="customer">' + opts(B.customers, t.customer, '（なし）') + '</select></div>' + assets +
        '<div class="d-field"><label for="d-p-tags">タグ</label><input type="text" id="d-p-tags" data-p="tags" value="' + esc(t.tags) + '" placeholder="カンマ区切り"></div>' +
        '<label class="d-switch"><span>お客様に見せる</span><span class="d-tg"><input type="checkbox" data-p="customer_visible"' + (t.customer_visible ? ' checked' : '') + '><span></span></span></label>' +
        '<label class="d-switch"><span>開くときに確認のコード</span><span class="d-tg"><input type="checkbox" data-p="require_otp"' + (t.require_otp ? ' checked' : '') + '><span></span></span></label>' +
        '<div class="d-time"><span><i class="fa fa-clock-o" aria-hidden="true"></i> 作業時間 合計</span><b>' + t.time_spent + ' 分</b></div></div>';
    h += '<div class="d-sec"><h4><i class="fa fa-clock-o" aria-hidden="true"></i>SLA</h4>' + slaBlock(t) + '</div>';
    if (t.customer_info) {
        var c = t.customer_info, pct = c.contract_hours ? Math.min(100, Math.round(c.month_minutes * 100 / (parseFloat(c.contract_hours) * 60))) : null;
        h += '<div class="d-sec"><h4><i class="fa fa-building-o" aria-hidden="true"></i>お客様</h4><div class="d-cust">' + av(c.name, 'l') + '<div><a href="' + esc(u('customer', c.id)) + '"><b>' + esc(c.name) + '</b></a>' + (c.phone ? '<div class="its-sub"><i class="fa fa-phone" aria-hidden="true"></i> ' + esc(c.phone) + '</div>' : '') + '</div></div>' +
            '<div class="d-slab" style="margin:12px 0 5px"><span>今月の作業</span><b>' + Math.floor(c.month_minutes / 60) + ' 時間 ' + (c.month_minutes % 60) + ' 分' + (c.contract_hours ? ' / ' + esc(c.contract_hours) + ' 時間' : '') + '</b></div>' +
            (pct != null ? '<div class="its-bar" role="img" aria-label="契約時間の ' + pct + '%"><span class="' + (pct >= 100 ? 'over' : (pct >= 80 ? 'warn' : '')) + '" style="width:' + pct + '%"></span></div>' + (pct >= 100 ? '<div class="its-sub its-tone" style="--c:#e5484d;margin-top:5px"><i class="fa fa-exclamation-triangle" aria-hidden="true"></i> 契約時間を超えています</div>' : '') : '') +
            (c.note ? '<div class="its-sub" style="margin-top:8px;white-space:pre-wrap">' + esc(c.note) + '</div>' : '') + '</div>';
    }
    if (t.asset_info) { var a = t.asset_info; h += '<div class="d-sec"><h4><i class="fa fa-desktop" aria-hidden="true"></i>IT 資産</h4><a href="' + esc(u('asset', a.id)) + '"><b>' + esc(a.name) + '</b></a><div class="its-sub">' + esc([a.kind, a.model, a.os].filter(Boolean).join(' · ')) + '</div>' + (a.warranty_end ? '<div class="its-sub">保守・保証 ' + esc(a.warranty_end) + '</div>' : '') + (a.expires_on ? '<div class="its-sub">期限 ' + esc(a.expires_on) + '</div>' : '') + '</div>'; }
    h += '<div class="d-sec"><h4><i class="fa fa-link" aria-hidden="true"></i>お客様のリンク</h4>' + (t.customer_visible ? '<div class="its-row"><button type="button" class="its-btn small" id="d-link"><i class="fa fa-clipboard" aria-hidden="true"></i>リンクをコピー</button>' + (t.links ? '<button type="button" class="its-btn small ghost danger" id="d-revoke">取り消す</button>' : '') + '</div><div class="its-sub" style="margin-top:8px">ログインなしで、このチケットだけが見えます。返信のメールにも入ります。</div>' +
        (t.access.length ? '<div class="d-log"><b>開いた記録</b>' + t.access.map(function (x) { return '<div>' + esc(rel(x.at)) + ' · ' + esc(x.action) + ' · ' + esc(x.ip || '') + '</div>'; }).join('') + '</div>' : '') : '<div class="its-sub">お客様に見せない設定です。</div>') + '</div>';
    h += '<div class="d-sec d-kn"><h4><i class="fa fa-book" aria-hidden="true"></i>ナレッジ</h4><input type="search" id="d-ks" placeholder="探して返事に入れる" aria-label="ナレッジを探して返事に入れる"><div id="d-klist">' + knHtml(t.suggest) + '</div><button type="button" class="its-btn small ghost" id="d-tok" style="margin-top:8px"><i class="fa fa-plus" aria-hidden="true"></i>このチケットからナレッジを作る</button></div>';
    if (t.files.length) { h += '<div class="d-sec d-files"><h4><i class="fa fa-paperclip" aria-hidden="true"></i>ファイル</h4>' + t.files.map(function (f) { return '<a href="' + esc(f.url) + '" target="_blank" rel="noopener"><i class="fa fa-file-o" aria-hidden="true"></i>' + esc(f.name) + '</a>'; }).join('') + '</div>'; }
    if (t.related.length) { h += '<div class="d-sec d-rel"><h4><i class="fa fa-history" aria-hidden="true"></i>同じお客様のチケット</h4>' + t.related.map(function (r) { return '<a href="#" data-open="' + r.id + '">' + pill(B.statuses[r.status] || r.status, B.statusColors[r.status] || '#888') + '<span class="s">' + esc(r.subject) + '</span></a>'; }).join('') + '</div>'; }
    if (t.problems_on) {
        h += '<div class="d-sec"><h4><i class="fa fa-bug" aria-hidden="true"></i>問題</h4>' + (t.problem
            ? '<a href="' + esc(u('problem', t.problem.id)) + '"><b>' + esc(t.problem.no) + '</b> ' + esc(t.problem.title) + '</a><div class="its-row" style="margin-top:6px"><button type="button" class="its-btn small ghost" id="d-punlink">つながりを外す</button></div>'
            : '<div class="its-row" style="flex-wrap:nowrap"><input type="text" id="d-pno" placeholder="PRB-00012 または 12" aria-label="つなぐ問題の番号" style="flex:1;min-width:0;height:30px"><button type="button" class="its-btn small" id="d-plink" style="height:30px">つなぐ</button></div><button type="button" class="its-btn small ghost" id="d-pnew" style="margin-top:6px"><i class="fa fa-plus" aria-hidden="true"></i>このチケットから問題を作る</button><div class="its-sub" style="margin-top:6px">同じ原因のチケットを 1 つの問題にまとめ、あとでまとめて解決にできます。</div>') + '</div>';
    }
    h += '<div class="d-sec"><h4><i class="fa fa-compress" aria-hidden="true"></i>まとめる</h4><div class="its-row" style="flex-wrap:nowrap"><input type="text" id="d-into" placeholder="SUP-00012 または 12" aria-label="まとめる先のチケット番号" style="flex:1;min-width:0;height:30px"><button type="button" class="its-btn small" id="d-merge" style="height:30px">まとめる</button></div><div class="its-sub" style="margin-top:6px">このチケットを別のチケットにまとめて、完了にします。</div></div>';
    h += '</aside></div>';

    $('d-detail').innerHTML = h;
    var th = $('d-thread'); th.scrollTop = th.scrollHeight;
    setKind(draft ? draft.kind : 'reply');
    var saved = draft ? draft.body : loadDraft(t.id); if (saved) { $('d-body').value = saved; }
    bindTicket();
}
function knHtml(list) { if (!list || !list.length) { return '<div class="its-sub">見つかりませんでした。</div>'; } return list.map(function (k) { return '<a href="#" data-k="' + k.id + '">' + esc(k.title) + (k.public ? ' <span class="its-tag">公開</span>' : '') + '<small>' + esc(k.snippet) + '</small></a>'; }).join(''); }
function setKind(kind) {
    S.kind = kind; var c = $('d-comp'); if (!c) { return; }
    c.classList.toggle('note', kind === 'note');
    c.querySelectorAll('.d-ctab').forEach(function (x) { x.classList.toggle('on', x.dataset.kind === kind); x.setAttribute('aria-pressed', x.dataset.kind === kind ? 'true' : 'false'); });
    $('d-to').style.display = kind === 'note' ? 'none' : '';
    $('d-send').querySelector('span').textContent = kind === 'note' ? 'メモを残す' : '送る';
    $('d-body').placeholder = kind === 'note' ? '社内メモ（お客様には見えません）' : '返事を書く…（パスワードは書かないでください）';
}
function insert(text) { var b = $('d-body'); b.value = b.value ? b.value.replace(/\s*$/, '') + '\n\n' + text : text; b.focus(); hold(); }
function hold() { var now = Date.now(); if (!S.sel || now - lastHold < 50000) { return; } lastHold = now; api(u('lock', S.sel), {}).then(function (d) { var bar = $('d-lockbar'); if (!bar) { return; } if (d && d.mine === false && d.user_name) { bar.style.display = ''; bar.innerHTML = '<i class="fa fa-user"></i> ' + esc(d.user_name) + ' さんが返事を書いています。二重に返事をしないよう確かめてください。'; } else { bar.style.display = 'none'; } }); }
function draftKey(id) { return 'its_draft_' + id; }
function saveDraft() { try { var b = $('d-body'); if (b && S.ticket) { if (b.value.trim()) { localStorage.setItem(draftKey(S.ticket.id), b.value); } else { localStorage.removeItem(draftKey(S.ticket.id)); } } } catch (e) {} }
function loadDraft(id) { try { return localStorage.getItem(draftKey(id)) || ''; } catch (e) { return ''; } }
function update(data, msg) { return api(u('update', S.sel), data).then(function (r) { if (r.ok === false) { toast(r.message || '保存できませんでした', true); } else { toast(msg || '保存しました'); } load(true); openTicket(S.sel, true); }); }

function bindTicket() {
    var t = S.ticket;
    $('d-comp').querySelectorAll('.d-ctab').forEach(function (x) { x.onclick = function () { setKind(x.dataset.kind); $('d-body').focus(); }; });
    $('d-body').addEventListener('input', function () { hold(); this.style.height = 'auto'; this.style.height = Math.min(320, Math.max(92, this.scrollHeight)) + 'px'; });
    $('d-tpl').onchange = function () { var id = parseInt(this.value, 10), x = B.templates.filter(function (y) { return y.id === id; })[0]; this.value = ''; if (!x) { return; }
        var text = x.body.split('{name}').join(t.requester_name || 'お客様').split('{ticket_no}').join(t.no).split('{subject}').join(t.subject).split('{link}').join('（返信のメールに自動で入ります）');
        insert(text); if (x.status) { $('d-after').value = x.status; } };
    $('d-files').onchange = function () { $('d-fcount').textContent = this.files.length ? ' ' + this.files.length : ''; };
    $('d-send').onclick = send;
    $('d-sidebtn').onclick = toggleSide;
    var back = $('d-back'); if (back) { back.onclick = function () { app.classList.remove('opened'); }; }
    $('d-detail').querySelectorAll('[data-p]').forEach(function (el) {
        el.addEventListener('change', function () { var d = {}; d[el.dataset.p] = el.type === 'checkbox' ? (el.checked ? '1' : '0') : el.value; update(d); });
    });
    $('d-detail').querySelectorAll('[data-open]').forEach(function (a) { a.onclick = function (e) { e.preventDefault(); openTicket(parseInt(a.dataset.open, 10)); }; });
    var kl = $('d-klist');
    var bindK = function () { kl.querySelectorAll('[data-k]').forEach(function (a) { a.onclick = function (e) { e.preventDefault(); api(B.url.ksearch + '?q=' + encodeURIComponent(a.firstChild.textContent)).then(function (d) { var k = (d.items || []).filter(function (x) { return String(x.id) === a.dataset.k; })[0]; if (k) { insert(k.url ? k.title + '\n' + k.url : k.body); } }); }; }); };
    bindK();
    var kt; $('d-ks').oninput = function () { var q = this.value; clearTimeout(kt); kt = setTimeout(function () { if (!q.trim()) { kl.innerHTML = knHtml(t.suggest); bindK(); return; } api(B.url.ksearch + '?q=' + encodeURIComponent(q)).then(function (d) { kl.innerHTML = knHtml(d.items); bindK(); }); }, 300); };
    $('d-tok').onclick = function () { api(u('knowledge', t.id), {}).then(function (r) { if (r.redirect) { toast(r.message); window.open(r.redirect, '_blank'); } else { toast(r.message || 'できませんでした', true); } }); };
    var lk = $('d-link'); if (lk) { lk.onclick = function () { api(u('share', t.id), { action: 'link' }).then(function (r) { if (!r.link) { toast(r.message || 'できませんでした', true); return; } var done = function () { toast('リンクをコピーしました'); }; if (navigator.clipboard) { navigator.clipboard.writeText(r.link).then(done, function () { window.prompt('リンク', r.link); }); } else { window.prompt('リンク', r.link); } openTicket(t.id, true); }); }; }
    var rv = $('d-revoke'); if (rv) { rv.onclick = function () { if (!confirm('このチケットのリンクをすべて取り消します。お客様に送ったリンクは開けなくなります。')) { return; } api(u('share', t.id), { action: 'revoke' }).then(function (r) { toast(r.message); openTicket(t.id, true); }); }; }
    var pl = $('d-plink'); if (pl) { pl.onclick = function () { var no = $('d-pno').value.trim(); if (!no) { return; } api(u('problem_link', t.id), { action: 'link', no: no }).then(function (r) { toast(r.message, r.ok === false); openTicket(t.id, true); }); }; }
    var pn = $('d-pnew'); if (pn) { pn.onclick = function () { if (!confirm('このチケットから新しい問題を作って、つなぎます。')) { return; } api(u('problem_link', t.id), { action: 'new' }).then(function (r) { toast(r.message, r.ok === false); if (r.redirect && r.ok !== false) { window.location.href = r.redirect; } }); }; }
    var pu = $('d-punlink'); if (pu) { pu.onclick = function () { api(u('problem_link', t.id), { action: 'unlink' }).then(function (r) { toast(r.message, r.ok === false); openTicket(t.id, true); }); }; }
    $('d-merge').onclick = function () { var into = $('d-into').value.trim(); if (!into || !confirm('このチケットを ' + into + ' にまとめて、このチケットは完了にします。')) { return; } api(u('merge', t.id), { into: into }).then(function (r) { toast(r.message, r.ok === false); var m = /ticket\/(\d+)/.exec(r.redirect || ''); load(true); if (m && r.ok !== false) { openTicket(parseInt(m[1], 10)); } }); };
}
function send() {
    var body = $('d-body').value.trim(), files = $('d-files').files;
    if (!body && !files.length) { toast('本文を書いてください', true); $('d-body').focus(); return; }
    var fd = new FormData(); fd.append('body', body); fd.append('kind', S.kind); fd.append('status', $('d-after').value); fd.append('time_spent', $('d-min').value || '0');
    if ($('d-cc')) { fd.append('cc', $('d-cc').value); }
    for (var i = 0; i < files.length; i++) { fd.append('files[]', files[i]); }
    var btn = $('d-send'); btn.disabled = true; btn.querySelector('span').textContent = '送っています…';
    api(u('reply', S.sel), fd).then(function (r) {
        btn.disabled = false;
        toast(r.message || (r.ok ? '送りました' : '送れませんでした'), r.ok === false);
        $('d-body').value = ''; try { localStorage.removeItem(draftKey(S.sel)); } catch (e) {}
        load(true); openTicket(S.sel, true);
    }).catch(function () { btn.disabled = false; setKind(S.kind); toast('送れませんでした。通信を確かめてください', true); });
}
function toggleSide() { S.side = !S.side; try { localStorage.setItem('its_side', S.side ? '1' : '0'); } catch (e) {} var b = $('d-bodybox'); if (b) { b.classList.toggle('noside', !S.side); } var sb = $('d-sidebtn'); if (sb) { sb.setAttribute('aria-pressed', S.side ? 'true' : 'false'); } }
$('d-help').addEventListener('click', function (e) { if (e.target === this) { this.classList.remove('on'); } });

// ---- keyboard -----------------------------------------------------------
document.addEventListener('keydown', function (e) {
    if (!document.body.contains(root)) { return; }
    var tag = (e.target.tagName || '').toLowerCase(), typing = tag === 'input' || tag === 'textarea' || tag === 'select';
    if ((e.ctrlKey || e.metaKey) && e.key === 'Enter' && e.target.id === 'd-body') { e.preventDefault(); send(); return; }
    if (e.key === 'Escape') { $('d-help').classList.remove('on'); if (typing) { e.target.blur(); } return; }
    if (typing || e.ctrlKey || e.metaKey || e.altKey) { return; }
    // Queues, tickets, filter chips and the reply/note tabs work like buttons from the keyboard.
    if ((e.key === 'Enter' || e.key === ' ') && e.target.matches && e.target.matches('.d-q,.d-item,.d-chip,.d-ctab')) { e.preventDefault(); e.target.click(); return; }
    var idx = S.items.findIndex(function (x) { return x.id === S.sel; });
    switch (e.key) {
        case 'j': case 'ArrowDown': if (S.items[idx + 1]) { e.preventDefault(); openTicket(S.items[idx + 1].id); scrollSel(); } break;
        case 'k': case 'ArrowUp': if (idx > 0) { e.preventDefault(); openTicket(S.items[idx - 1].id); scrollSel(); } break;
        case '/': e.preventDefault(); $('d-kw').focus(); break;
        case 'r': if (S.ticket) { e.preventDefault(); setKind('reply'); $('d-body').focus(); } break;
        case 'n': if (S.ticket) { e.preventDefault(); setKind('note'); $('d-body').focus(); } break;
        case 'a': if (S.ticket) { e.preventDefault(); update({ assignee: B.me.id }, '自分を担当者にしました'); } break;
        case 'e': if (S.ticket) { e.preventDefault(); update({ status: 'resolved' }, '解決にしました'); } break;
        case '\\': if (S.ticket) { e.preventDefault(); toggleSide(); } break;
        case '?': e.preventDefault(); $('d-help').classList.add('on'); break;
    }
});
function scrollSel() { setTimeout(function () { var el = document.querySelector('.d-item.sel'); if (el) { el.scrollIntoView({ block: 'nearest' }); } }, 30); }

// ---- go -----------------------------------------------------------------
renderFilters(); renderBulk();
load().then(function () { if (B.open) { openTicket(B.open); } else if (getComputedStyle($('d-detail')).display !== 'none' && S.items.length) { openTicket(S.items[0].id); } });
// Keep the list fresh without disturbing someone who is typing.
var poll = setInterval(function () { if (!document.body.contains(root)) { clearInterval(poll); return; } var b = $('d-body'); if (document.hidden || (b && document.activeElement === b)) { return; } load(true); }, 60000);
window.addEventListener('beforeunload', saveDraft);
})();
</script>
