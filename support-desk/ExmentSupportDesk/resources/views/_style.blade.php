<style>
/* サポートデスク - shared look. Tokens first; every color below reads a token, so
   the dark theme is only a second set of tokens. Everything is scoped under
   .sd so nothing leaks into Exment's own screens. */
.sd{--sd-accent:#4f6bed;--sd-accent-2:#7c5cf0;--sd-accent-d:#3d57d6;--sd-accent-ink:#3a50c8;--sd-accent-soft:#eef1fe;--sd-on-accent:#fff;
  --sd-ink:#1a2230;--sd-sub:#525d6c;--sd-faint:#6b7685;--sd-mute:#8d96a4;--sd-bg:#f5f6f9;--sd-box:#ffffff;--sd-box2:#f8f9fb;
  --sd-line:#e2e6ec;--sd-line2:#edf0f4;--sd-line3:#cfd5de;--sd-hover:#f2f4f8;--sd-sel:#edf0fe;
  --sd-red:#d93d42;--sd-orange:#e07b1a;--sd-amber:#d99a06;--sd-green:#23935f;--sd-blue:#3e8bea;--sd-purple:#8e4ec6;--sd-gray:#8b95a3;
  --sd-note:#fff9e8;--sd-note-line:#f0dc9a;--sd-note-ink:#7a5a00;
  --sd-ok-bg:#eaf7f0;--sd-ok-line:#bfe5d0;--sd-ok-ink:#17633f;
  --sd-err-bg:#fdeeee;--sd-err-line:#f4c6c7;--sd-err-ink:#9f2328;
  --sd-warn-bg:#fff6e3;--sd-warn-line:#f2d9a0;--sd-warn-ink:#734f00;
  --sd-code-bg:#111827;--sd-code-ink:#e5e9f0;--sd-toast:#1a2230;
  --sd-radius:12px;--sd-radius-s:8px;
  --sd-shadow:0 1px 2px rgba(16,24,40,.04),0 1px 3px rgba(16,24,40,.05);--sd-shadow-m:0 4px 14px rgba(16,24,40,.08),0 1px 3px rgba(16,24,40,.06);--sd-shadow-l:0 16px 40px rgba(16,24,40,.18);
  --sd-ring:0 0 0 3px rgba(79,107,237,.28);--sd-focus:var(--sd-ring);
  color:var(--sd-ink);font-size:13.5px;line-height:1.55;-webkit-font-smoothing:antialiased;font-feature-settings:"palt" 0}
@media (prefers-color-scheme:dark){.sd{--sd-accent:#8497fa;--sd-accent-2:#a58ff8;--sd-accent-d:#9aaafb;--sd-accent-ink:#a9b6fc;--sd-accent-soft:#232a47;--sd-on-accent:#0f1424;
  --sd-ink:#e7eaf0;--sd-sub:#aab3c0;--sd-faint:#8c96a5;--sd-mute:#6d7684;--sd-bg:#13161b;--sd-box:#1a1e25;--sd-box2:#1f242c;
  --sd-line:#2b313b;--sd-line2:#252a33;--sd-line3:#3a424e;--sd-hover:#232933;--sd-sel:#252c47;
  --sd-red:#f0666a;--sd-orange:#f09a4a;--sd-amber:#e8b93c;--sd-green:#4cc38a;
  --sd-note:#2b2718;--sd-note-line:#574a1f;--sd-note-ink:#ecd07a;
  --sd-ok-bg:#16291f;--sd-ok-line:#245039;--sd-ok-ink:#8fdcb4;
  --sd-err-bg:#2f191b;--sd-err-line:#5e2a2d;--sd-err-ink:#f6a5a8;
  --sd-warn-bg:#2d2513;--sd-warn-line:#5a4718;--sd-warn-ink:#efd28a;
  --sd-code-bg:#0c1017;--sd-toast:#2f3642;
  --sd-shadow:0 1px 2px rgba(0,0,0,.35);--sd-shadow-m:0 4px 14px rgba(0,0,0,.4);--sd-shadow-l:0 16px 40px rgba(0,0,0,.55);
  --sd-ring:0 0 0 3px rgba(132,151,250,.4)}}
@media (prefers-color-scheme:dark){.sd{background:var(--sd-bg);padding:12px;border-radius:18px}.sd .sd{padding:0;border-radius:0}}
.sd *,.sd *::before,.sd *::after{box-sizing:border-box}
.sd a{color:var(--sd-accent-ink);text-decoration:none}
.sd a:hover{text-decoration:underline}
.sd a:focus-visible,.sd button:focus-visible,.sd [tabindex]:focus-visible{outline:2px solid var(--sd-accent);outline-offset:2px;border-radius:6px}
.sd h1,.sd h2,.sd h3,.sd h4{color:var(--sd-ink);font-family:inherit}
.sd p{margin:0 0 10px}
.sd ::selection{background:color-mix(in srgb,var(--sd-accent) 28%,transparent)}
.sd .sd-num{font-variant-numeric:tabular-nums}

/* top bar */
.sd-top{display:flex;align-items:center;gap:8px 16px;flex-wrap:wrap;padding:8px 10px 8px 12px;margin:0 0 12px;background:var(--sd-box);border:1px solid var(--sd-line);border-radius:var(--sd-radius);box-shadow:var(--sd-shadow)}
.sd-brand{display:flex;align-items:center;gap:10px;font-weight:700;font-size:15px;letter-spacing:.01em;color:var(--sd-ink)!important;text-decoration:none!important;margin-right:4px;white-space:nowrap}
.sd-logo{width:30px;height:30px;border-radius:9px;display:grid;place-items:center;color:#fff;font-size:15px;background:linear-gradient(135deg,#4f6bed,#7c5cf0);box-shadow:0 3px 8px rgba(79,107,237,.32),inset 0 1px 0 rgba(255,255,255,.25)}
.sd-tabs{display:flex;gap:2px;flex-wrap:wrap}
.sd-tabs a{display:inline-flex;align-items:center;gap:7px;height:32px;padding:0 11px;border-radius:8px;color:var(--sd-sub);font-weight:500;text-decoration:none!important;white-space:nowrap;transition:background .12s,color .12s}
.sd-tabs a i.fa{font-size:13px;width:14px;text-align:center;opacity:.8}
.sd-tabs a:hover{background:var(--sd-hover);color:var(--sd-ink)}
.sd-tabs a.on{background:var(--sd-accent-soft);color:var(--sd-accent-ink);font-weight:600}
.sd-tables{list-style:none;margin:10px 0 0;padding:0;display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:4px 12px}
.sd-tables a{font-size:13px}
.sd-tabs a.on i.fa{opacity:1}
.sd-tabs .sep{width:1px;height:18px;background:var(--sd-line);margin:7px 6px}
.sd-right{margin-left:auto;display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.sd-dot{display:inline-block;width:8px;height:8px;border-radius:50%;background:var(--sd-mute);margin-right:6px;vertical-align:1px}
.sd-dot.on{background:var(--sd-green);box-shadow:0 0 0 3px color-mix(in srgb,var(--sd-green) 20%,transparent)}
.sd-dot.err{background:var(--sd-red);box-shadow:0 0 0 3px color-mix(in srgb,var(--sd-red) 20%,transparent)}
.sd-status{display:inline-flex;align-items:center;height:26px;padding:0 10px;border-radius:999px;font-size:12px;color:var(--sd-sub);white-space:nowrap;background:var(--sd-box2);border:1px solid var(--sd-line2)}

.sd-status.err{color:var(--sd-err-ink);border-color:var(--sd-err-line);background:var(--sd-err-bg)}
@media (max-width:820px){.sd-top{gap:8px 10px}.sd-tabs{order:3;flex:1 0 100%;flex-wrap:nowrap;overflow-x:auto;scrollbar-width:none;margin:0 -4px;padding:2px 4px}.sd-tabs::-webkit-scrollbar{display:none}.sd-tabs .sep{flex:0 0 1px}.sd-right{gap:6px}}
@media (max-width:1400px){.sd-status:not(.err){display:none}}
@media (max-width:600px){.sd-brand{font-size:14px}.sd-right{flex-wrap:nowrap}.sd-right .tx{display:none}.sd-right .sd-btn.primary .tx{display:inline}}

/* buttons */
.sd-btn{display:inline-flex;align-items:center;justify-content:center;gap:7px;height:32px;padding:0 12px;border:1px solid var(--sd-line);border-radius:8px;background:var(--sd-box);color:var(--sd-ink)!important;cursor:pointer;font-family:inherit;font-size:13px;font-weight:500;line-height:1;white-space:nowrap;text-decoration:none!important;box-shadow:0 1px 1px rgba(16,24,40,.04);transition:background .12s,border-color .12s,box-shadow .12s,transform .05s}
.sd-btn:hover{background:var(--sd-hover);border-color:var(--sd-line3)}
.sd-btn:active{transform:translateY(.5px)}
.sd .sd-btn:focus-visible{outline:0;box-shadow:var(--sd-ring);border-color:var(--sd-accent);border-radius:8px}
.sd-btn.primary{background:linear-gradient(135deg,#4f6bed,#7460f0);border-color:transparent;color:#fff!important;font-weight:600;box-shadow:0 1px 2px rgba(49,66,160,.35),inset 0 1px 0 rgba(255,255,255,.18)}
.sd-btn.primary:hover{background:linear-gradient(135deg,#4561e4,#6a55e8)}
.sd .sd-btn.primary:focus-visible{box-shadow:0 0 0 2px var(--sd-box),0 0 0 4px var(--sd-accent)}
.sd-btn.ghost{border-color:transparent;background:transparent;box-shadow:none;color:var(--sd-sub)!important}
.sd-btn.ghost:hover{background:var(--sd-hover);color:var(--sd-ink)!important}
.sd-btn.danger{color:var(--sd-red)!important}
.sd-btn.danger:hover{background:var(--sd-err-bg);border-color:var(--sd-err-line)}
.sd-btn.small{height:28px;padding:0 10px;font-size:12px;border-radius:7px;gap:6px}
.sd-btn.icon{width:32px;padding:0}
.sd-btn.small.icon{width:28px}
.sd-btn.big{height:38px;padding:0 18px;font-size:14px}
.sd-btn[disabled]{opacity:.5;cursor:default;pointer-events:none}

/* inputs */
.sd input[type=text],.sd input[type=email],.sd input[type=number],.sd input[type=date],.sd input[type=time],.sd input[type=password],.sd input[type=month],.sd input[type=search],.sd select,.sd textarea{height:34px;border:1px solid var(--sd-line);border-radius:8px;padding:0 10px;font-family:inherit;font-size:13px;background-color:var(--sd-box);color:var(--sd-ink);max-width:100%;box-shadow:inset 0 1px 1px rgba(16,24,40,.03);transition:border-color .12s,box-shadow .12s}
.sd select{padding-right:26px;cursor:pointer}
.sd textarea{height:auto;min-height:110px;width:100%;padding:9px 11px;line-height:1.65;resize:vertical}
.sd input:hover,.sd select:hover,.sd textarea:hover{border-color:var(--sd-line3)}
.sd input:focus,.sd select:focus,.sd textarea:focus{outline:0;border-color:var(--sd-accent);box-shadow:var(--sd-ring)}
.sd input::placeholder,.sd textarea::placeholder{color:var(--sd-mute);opacity:1}
.sd input[type=checkbox],.sd input[type=radio]{accent-color:var(--sd-accent);width:15px;height:15px;margin:0;vertical-align:-2px;cursor:pointer}
.sd input[type=checkbox]:focus-visible{outline:2px solid var(--sd-accent);outline-offset:2px;box-shadow:none}
.sd label{font-weight:normal;margin:0}
.sd-check{display:inline-flex;align-items:center;gap:8px;cursor:pointer}
@media (prefers-color-scheme:dark){.sd input,.sd select,.sd textarea{color-scheme:dark}}
/* a checkbox drawn as a switch (same name/value as before) */
.sd input[type=checkbox].sd-sw{appearance:none;-webkit-appearance:none;position:relative;flex:0 0 34px;width:34px;height:20px;border-radius:999px;background:var(--sd-line3);border:0;transition:background .15s;vertical-align:-5px}
.sd input[type=checkbox].sd-sw::after{content:"";position:absolute;left:2px;top:2px;width:16px;height:16px;border-radius:50%;background:#fff;box-shadow:0 1px 2px rgba(0,0,0,.3);transition:transform .15s}
.sd input[type=checkbox].sd-sw:checked{background:var(--sd-accent)}
.sd input[type=checkbox].sd-sw:checked::after{transform:translateX(14px)}

/* cards and common bits */
.sd-card{background:var(--sd-box);border:1px solid var(--sd-line);border-radius:var(--sd-radius);padding:18px 20px;margin-bottom:14px;box-shadow:var(--sd-shadow)}
.sd .sd-card h2,.sd .sd-card h3{display:flex;align-items:center;gap:8px;flex-wrap:wrap;font-size:14.5px!important;margin:0 0 12px;font-weight:700;color:var(--sd-ink);line-height:1.4}
.sd-card h2>i.fa,.sd-card h3>i.fa{width:26px;height:26px;border-radius:7px;display:grid;place-items:center;font-size:12.5px;color:var(--sd-accent-ink);background:var(--sd-accent-soft);flex:0 0 26px}
.sd-card h2+.sd-sub,.sd-card h3+.sd-sub{margin-top:-6px}
.sd-sub{color:var(--sd-sub);font-size:12px;line-height:1.6}
.sd-row{display:flex;flex-wrap:wrap;gap:8px;align-items:center}
.sd-head{display:flex;align-items:center;gap:10px 12px;flex-wrap:wrap;margin:2px 2px 14px}
.sd-head h1{font-size:19px;font-weight:700;margin:0;letter-spacing:.01em}
.sd-head .sd-sub{flex:1;min-width:200px}
.sd-head .act{margin-left:auto;display:flex;gap:8px;flex-wrap:wrap;align-items:center}
/* badges: tinted background + dark text, always with a word (never color alone) */
.sd-badge,.sd-pill,.sd-soft{--c:#8b95a3;display:inline-flex;align-items:center;gap:5px;height:21px;padding:0 8px;border-radius:6px;font-size:11.5px;font-weight:600;line-height:1;white-space:nowrap;color:var(--c);color:color-mix(in srgb,var(--c) 52%,var(--sd-ink));background:var(--sd-line2);background:color-mix(in srgb,var(--c) 13%,var(--sd-box))}
.sd-badge .dot{width:7px;height:7px;border-radius:50%;background:var(--c);flex:0 0 7px}
.sd-badge i.fa{font-size:11px;color:var(--c);color:color-mix(in srgb,var(--c) 75%,var(--sd-ink))}
.sd-tone{--c:#8b95a3;color:var(--c);color:color-mix(in srgb,var(--c) 52%,var(--sd-ink))}
.sd-tag{display:inline-flex;align-items:center;height:20px;padding:0 7px;border-radius:6px;font-size:11px;font-weight:500;background:transparent;border:1px solid var(--sd-line);color:var(--sd-sub);white-space:nowrap}
.sd-flash,.sd-warn{display:flex;gap:10px;align-items:flex-start;padding:10px 14px;border-radius:10px;margin:0 0 12px;line-height:1.6}
.sd-flash{background:var(--sd-ok-bg);border:1px solid var(--sd-ok-line);color:var(--sd-ok-ink)}
.sd-flash.error{background:var(--sd-err-bg);border-color:var(--sd-err-line);color:var(--sd-err-ink)}
.sd-warn{background:var(--sd-warn-bg);border:1px solid var(--sd-warn-line);color:var(--sd-warn-ink)}
.sd-flash>i.fa,.sd-warn>i.fa{margin-top:3px}
.sd-flash a,.sd-warn a{color:inherit;text-decoration:underline;font-weight:600}
.sd-grid3{display:grid;grid-template-columns:minmax(0,1fr) 340px;gap:14px;align-items:start}
@media (max-width:1100px){.sd-grid3{grid-template-columns:minmax(0,1fr)}}
.sd-scroll{overflow-x:auto;margin:0 -20px;padding:0 20px}
.sd-table{width:100%;border-collapse:collapse}
.sd-table th{font-size:11.5px;color:var(--sd-faint);font-weight:600;text-align:left;padding:8px 10px;border-bottom:1px solid var(--sd-line);white-space:nowrap;text-transform:none;background:transparent}
.sd-table td{padding:10px;border-bottom:1px solid var(--sd-line2);vertical-align:middle}
.sd-table tbody tr:last-child td{border-bottom:0}
.sd-scroll .sd-table td{white-space:nowrap}
.sd-scroll .sd-table td.subj{white-space:normal;min-width:180px}
.sd-table tbody tr:hover td{background:var(--sd-hover)}
.sd-table th.r,.sd-table td.r{text-align:right;font-variant-numeric:tabular-nums}
.sd-table td.over{color:var(--sd-red);font-weight:700}

/* numbers at a glance */
.sd-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-bottom:14px}
@media (max-width:1100px){.sd-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media (max-width:480px){.sd-kpis{grid-template-columns:minmax(0,1fr)}}
.sd-kpi{--k:var(--sd-accent);position:relative;display:grid;grid-template-columns:minmax(0,1fr) auto;gap:2px 10px;align-items:start;background:var(--sd-box);border:1px solid var(--sd-line);border-radius:var(--sd-radius);padding:14px 16px 13px;box-shadow:var(--sd-shadow)}
.sd-kpi .l{grid-column:1;grid-row:1;font-size:12px;font-weight:600;color:var(--sd-sub);line-height:1.45}
.sd-kpi .n{grid-column:1;grid-row:2;font-size:26px;font-weight:700;letter-spacing:-.01em;line-height:1.25;font-variant-numeric:tabular-nums;color:var(--sd-ink)}
.sd-kpi .n small{font-size:13px;font-weight:600;color:var(--sd-sub);margin-left:3px;letter-spacing:0}
.sd-kpi .h{grid-column:1/-1;grid-row:3;font-size:11.5px;color:var(--sd-faint);line-height:1.45}
.sd-kpi .i{grid-column:2;grid-row:1/3;width:32px;height:32px;border-radius:9px;display:grid;place-items:center;font-size:14px;color:var(--k);color:color-mix(in srgb,var(--k) 70%,var(--sd-ink));background:var(--sd-line2);background:color-mix(in srgb,var(--k) 13%,var(--sd-box))}
.sd-kpi.alert{border-color:var(--sd-err-line);background:var(--sd-err-bg)}
.sd-kpi.alert .n{color:var(--sd-err-ink)}
.sd-bar{height:6px;border-radius:999px;background:var(--sd-line2);overflow:hidden}
.sd-bar span{display:block;height:100%;border-radius:999px;background:var(--sd-accent)}
.sd-bar span.warn{background:var(--sd-orange)}
.sd-bar span.over{background:var(--sd-red)}
.sd-meter{margin-bottom:10px}
.sd-meter:last-child{margin-bottom:0}
.sd-meter .t{display:flex;gap:8px;align-items:baseline;margin-bottom:4px}
.sd-meter .t b{margin-left:auto;font-weight:600;font-variant-numeric:tabular-nums}
.sd-meter .t b small{display:inline-block;min-width:38px;text-align:right;font-size:11.5px;font-weight:500;color:var(--sd-faint)}
.sd-legend{display:inline-flex;gap:14px;margin-left:auto;font-size:12px;font-weight:500;color:var(--sd-sub)}
.sd-legend span{display:inline-flex;align-items:center;gap:6px}
.sd-legend i{width:10px;height:10px;border-radius:3px;background:var(--sd-accent)}
.sd-legend i.out{background:var(--sd-green)}
/* daily chart: two thin bars per day, one shared axis */
.sd-plot{display:grid;grid-template-columns:auto minmax(0,1fr);gap:0 8px}
.sd-plot .y{display:flex;flex-direction:column;justify-content:space-between;height:140px;font-size:11px;color:var(--sd-faint);text-align:right;font-variant-numeric:tabular-nums;line-height:1}
.sd-plot .y span:first-child{transform:translateY(-4px)}
.sd-plot .y span:last-child{transform:translateY(4px)}
.sd-chart{position:relative;display:flex;align-items:flex-end;gap:0;height:140px;border-bottom:1px solid var(--sd-line3);background:linear-gradient(var(--sd-line2),var(--sd-line2)) 0 0/100% 1px no-repeat,linear-gradient(var(--sd-line2),var(--sd-line2)) 0 50%/100% 1px no-repeat}
.sd-chart>div{position:relative;flex:1;display:flex;align-items:flex-end;justify-content:center;gap:2px;height:100%;padding:0 2px;min-width:0}
.sd-chart>div:hover,.sd-chart>div:focus-visible{background:var(--sd-hover);outline:0}
.sd-chart i{display:block;flex:0 1 9px;min-width:3px;min-height:2px;background:var(--sd-accent);border-radius:3px 3px 0 0}
.sd-chart i.out{background:var(--sd-green)}
.sd-chart i.z{visibility:hidden}
.sd-chart .tip{display:none;position:absolute;bottom:calc(100% + 4px);left:50%;transform:translateX(-50%);z-index:5;padding:7px 10px;border-radius:8px;background:var(--sd-toast);color:#fff;font-size:12px;line-height:1.6;white-space:nowrap;box-shadow:var(--sd-shadow-m);pointer-events:none;font-style:normal;text-align:left}
.sd-chart .tip b{display:block;font-weight:600;color:#cfd6e2}
.sd-chart>div:hover .tip,.sd-chart>div:focus-visible .tip{display:block}
.sd-chart>div:nth-child(-n+3) .tip{left:0;transform:none}
.sd-chart>div:nth-last-child(-n+3) .tip{left:auto;right:0;transform:none}
.sd-plot .x{grid-column:2;display:flex;font-size:11px;color:var(--sd-faint);padding-top:5px;font-variant-numeric:tabular-nums}
.sd-plot .x span{flex:1;text-align:center;min-width:0;white-space:nowrap;overflow:visible}

/* forms */
.sd-form-grid{display:grid;grid-template-columns:190px minmax(0,1fr);gap:12px 16px;align-items:center}
.sd-form-grid>label[for],.sd-form-grid>.lbl{font-weight:600;color:var(--sd-ink);font-size:13px}
.sd-form-grid .full{grid-column:1/-1}
.sd-form-grid>label.full{margin-bottom:-6px}
.sd-form-grid>input[type=text],.sd-form-grid>input[type=email],.sd-form-grid>input[type=password],.sd-form-grid>select{width:100%;max-width:440px}
.sd-form-grid>textarea{max-width:720px}
.sd-form-grid>textarea.full{max-width:none}
@media (max-width:700px){.sd-form-grid{grid-template-columns:minmax(0,1fr);gap:6px}.sd-form-grid>*:nth-child(even){margin-bottom:8px}.sd-form-grid>label:empty{display:none}}
.sd-savebar{position:sticky;bottom:0;z-index:5;display:flex;align-items:center;gap:12px;flex-wrap:wrap;padding:10px 14px;margin-bottom:14px;background:var(--sd-box);background:color-mix(in srgb,var(--sd-box) 88%,transparent);backdrop-filter:blur(8px);border:1px solid var(--sd-line);border-radius:var(--sd-radius);box-shadow:var(--sd-shadow-m)}
.sd-props dt{font-size:11.5px;color:var(--sd-sub);font-weight:600;margin-top:10px}
.sd-props dd{margin:3px 0 0}
.sd code{background:var(--sd-line2);color:var(--sd-ink);padding:2px 6px;border-radius:5px;font-size:12px;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;word-break:break-all}
.sd .sd-codeline{display:block;padding:8px 10px;margin-top:4px;border:1px solid var(--sd-line);background:var(--sd-box2);border-radius:8px;user-select:all}
.sd pre.sd-code{background:var(--sd-code-bg);color:var(--sd-code-ink);border:0;padding:12px 14px;margin:10px 0;border-radius:10px;font-size:11.5px;line-height:1.7;overflow:auto;white-space:pre;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace}
.sd-page{max-width:1500px}
.sd-narrow{max-width:880px}
.sd-list{margin:0;padding:0;list-style:none}
.sd-list li{display:flex;gap:10px;align-items:center;padding:7px 0;border-bottom:1px solid var(--sd-line2)}
.sd-list li:last-child{border-bottom:0}
.sd-list li i.fa{width:26px;height:26px;border-radius:7px;display:grid;place-items:center;font-size:12px;color:var(--sd-accent-ink);background:var(--sd-accent-soft)}
.sd-emptybox{text-align:center;padding:36px 20px;color:var(--sd-sub)}
.sd-emptybox i.fa{display:block;font-size:30px;color:var(--sd-mute);margin-bottom:10px}
/* settings and forms: small helpers */
.sd-lead{margin:-4px 0 14px}
.sd-foot{margin-top:14px;padding-top:12px;border-top:1px solid var(--sd-line2)}
.sd-form-grid>label.top{align-self:start;padding-top:8px}
.sd-form-grid>input.wide{max-width:none}
.sd-inline{display:inline-flex;align-items:center;gap:8px;flex-wrap:wrap}
.sd .sd-inline input[type=number]{width:76px;text-align:right}
.sd-days{display:flex;flex-wrap:wrap;gap:6px}
.sd-days label{display:inline-flex;align-items:center;gap:7px;height:32px;padding:0 12px 0 10px;border:1px solid var(--sd-line);border-radius:8px;background:var(--sd-box);cursor:pointer;font-weight:500;color:var(--sd-sub);transition:background .12s,border-color .12s}
.sd-days label:hover{border-color:var(--sd-line3)}
.sd-days label:has(input:checked){background:var(--sd-accent-soft);border-color:color-mix(in srgb,var(--sd-accent) 45%,var(--sd-line));color:var(--sd-accent-ink);font-weight:600}
.sd-req{display:inline-block;margin-left:4px;padding:1px 6px;border-radius:5px;font-size:10.5px;font-weight:600;color:var(--sd-err-ink);background:var(--sd-err-bg)}
.sd-divider{display:flex;align-items:center;gap:10px;margin-top:8px;font-size:11.5px;font-weight:700;letter-spacing:.03em;color:var(--sd-faint)}
.sd-divider::after{content:"";flex:1;height:1px;background:var(--sd-line2)}
.sd-actions{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-top:18px;padding-top:16px;border-top:1px solid var(--sd-line2)}
.sd-logo.l{width:44px;height:44px;border-radius:13px;font-size:21px;margin-bottom:12px}
.sd .sd-hero h2{font-size:19px!important;margin-bottom:10px}
.sd-hero p{line-height:1.8}
.sd-hero .sd-list{margin:4px 0 4px}
.sd .sd-emptybox h2{font-size:16px!important;margin-bottom:6px}
@media (max-width:700px){.sd-table .hide-sm{display:none}.sd-card{padding:16px}.sd-scroll{margin:0 -16px;padding:0 16px}.sd-head .act{margin-left:0;width:100%}.sd-head .sd-sub{flex-basis:100%}}
@media (prefers-reduced-motion:reduce){.sd *{transition:none!important;animation:none!important}}
</style>
