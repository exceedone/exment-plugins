<style>
/* ITサポート - shared look. Tokens first; every color below reads a token, so
   the dark theme is only a second set of tokens. Everything is scoped under
   .its so nothing leaks into Exment's own screens. */
.its{--its-accent:#4f6bed;--its-accent-2:#7c5cf0;--its-accent-d:#3d57d6;--its-accent-ink:#3a50c8;--its-accent-soft:#eef1fe;--its-on-accent:#fff;
  --its-ink:#1a2230;--its-sub:#525d6c;--its-faint:#6b7685;--its-mute:#8d96a4;--its-bg:#f5f6f9;--its-box:#ffffff;--its-box2:#f8f9fb;
  --its-line:#e2e6ec;--its-line2:#edf0f4;--its-line3:#cfd5de;--its-hover:#f2f4f8;--its-sel:#edf0fe;
  --its-red:#d93d42;--its-orange:#e07b1a;--its-amber:#d99a06;--its-green:#23935f;--its-blue:#3e8bea;--its-purple:#8e4ec6;--its-gray:#8b95a3;
  --its-note:#fff9e8;--its-note-line:#f0dc9a;--its-note-ink:#7a5a00;
  --its-ok-bg:#eaf7f0;--its-ok-line:#bfe5d0;--its-ok-ink:#17633f;
  --its-err-bg:#fdeeee;--its-err-line:#f4c6c7;--its-err-ink:#9f2328;
  --its-warn-bg:#fff6e3;--its-warn-line:#f2d9a0;--its-warn-ink:#734f00;
  --its-code-bg:#111827;--its-code-ink:#e5e9f0;--its-toast:#1a2230;
  --its-radius:12px;--its-radius-s:8px;
  --its-shadow:0 1px 2px rgba(16,24,40,.04),0 1px 3px rgba(16,24,40,.05);--its-shadow-m:0 4px 14px rgba(16,24,40,.08),0 1px 3px rgba(16,24,40,.06);--its-shadow-l:0 16px 40px rgba(16,24,40,.18);
  --its-ring:0 0 0 3px rgba(79,107,237,.28);--its-focus:var(--its-ring);
  color:var(--its-ink);font-size:13.5px;line-height:1.55;-webkit-font-smoothing:antialiased;font-feature-settings:"palt" 0}
@media (prefers-color-scheme:dark){.its{--its-accent:#8497fa;--its-accent-2:#a58ff8;--its-accent-d:#9aaafb;--its-accent-ink:#a9b6fc;--its-accent-soft:#232a47;--its-on-accent:#0f1424;
  --its-ink:#e7eaf0;--its-sub:#aab3c0;--its-faint:#8c96a5;--its-mute:#6d7684;--its-bg:#13161b;--its-box:#1a1e25;--its-box2:#1f242c;
  --its-line:#2b313b;--its-line2:#252a33;--its-line3:#3a424e;--its-hover:#232933;--its-sel:#252c47;
  --its-red:#f0666a;--its-orange:#f09a4a;--its-amber:#e8b93c;--its-green:#4cc38a;
  --its-note:#2b2718;--its-note-line:#574a1f;--its-note-ink:#ecd07a;
  --its-ok-bg:#16291f;--its-ok-line:#245039;--its-ok-ink:#8fdcb4;
  --its-err-bg:#2f191b;--its-err-line:#5e2a2d;--its-err-ink:#f6a5a8;
  --its-warn-bg:#2d2513;--its-warn-line:#5a4718;--its-warn-ink:#efd28a;
  --its-code-bg:#0c1017;--its-toast:#2f3642;
  --its-shadow:0 1px 2px rgba(0,0,0,.35);--its-shadow-m:0 4px 14px rgba(0,0,0,.4);--its-shadow-l:0 16px 40px rgba(0,0,0,.55);
  --its-ring:0 0 0 3px rgba(132,151,250,.4)}}
@media (prefers-color-scheme:dark){.its{background:var(--its-bg);padding:12px;border-radius:18px}.its .its{padding:0;border-radius:0}}
.its *,.its *::before,.its *::after{box-sizing:border-box}
.its a{color:var(--its-accent-ink);text-decoration:none}
.its a:hover{text-decoration:underline}
.its a:focus-visible,.its button:focus-visible,.its [tabindex]:focus-visible{outline:2px solid var(--its-accent);outline-offset:2px;border-radius:6px}
.its h1,.its h2,.its h3,.its h4{color:var(--its-ink);font-family:inherit}
.its p{margin:0 0 10px}
.its ::selection{background:color-mix(in srgb,var(--its-accent) 28%,transparent)}
.its .its-num{font-variant-numeric:tabular-nums}

/* top bar */
.its-top{display:flex;align-items:center;gap:8px 16px;flex-wrap:wrap;padding:8px 10px 8px 12px;margin:0 0 12px;background:var(--its-box);border:1px solid var(--its-line);border-radius:var(--its-radius);box-shadow:var(--its-shadow)}
.its-brand{display:flex;align-items:center;gap:10px;font-weight:700;font-size:15px;letter-spacing:.01em;color:var(--its-ink)!important;text-decoration:none!important;margin-right:4px;white-space:nowrap}
.its-logo{width:30px;height:30px;border-radius:9px;display:grid;place-items:center;color:#fff;font-size:15px;background:linear-gradient(135deg,#4f6bed,#7c5cf0);box-shadow:0 3px 8px rgba(79,107,237,.32),inset 0 1px 0 rgba(255,255,255,.25)}
.its-tabs{display:flex;gap:2px;flex-wrap:wrap}
.its-tabs a{display:inline-flex;align-items:center;gap:7px;height:32px;padding:0 11px;border-radius:8px;color:var(--its-sub);font-weight:500;text-decoration:none!important;white-space:nowrap;transition:background .12s,color .12s}
.its-tabs a i.fa{font-size:13px;width:14px;text-align:center;opacity:.8}
.its-tabs a:hover{background:var(--its-hover);color:var(--its-ink)}
.its-tabs a.on{background:var(--its-accent-soft);color:var(--its-accent-ink);font-weight:600}
.its-tabs a.on i.fa{opacity:1}
.its-tabs .sep{width:1px;height:18px;background:var(--its-line);margin:7px 6px}
.its-right{margin-left:auto;display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.its-dot{display:inline-block;width:8px;height:8px;border-radius:50%;background:var(--its-mute);margin-right:6px;vertical-align:1px}
.its-dot.on{background:var(--its-green);box-shadow:0 0 0 3px color-mix(in srgb,var(--its-green) 20%,transparent)}
.its-dot.err{background:var(--its-red);box-shadow:0 0 0 3px color-mix(in srgb,var(--its-red) 20%,transparent)}
.its-status{display:inline-flex;align-items:center;height:26px;padding:0 10px;border-radius:999px;font-size:12px;color:var(--its-sub);white-space:nowrap;background:var(--its-box2);border:1px solid var(--its-line2)}

.its-status.err{color:var(--its-err-ink);border-color:var(--its-err-line);background:var(--its-err-bg)}
@media (max-width:820px){.its-top{gap:8px 10px}.its-tabs{order:3;flex:1 0 100%;flex-wrap:nowrap;overflow-x:auto;scrollbar-width:none;margin:0 -4px;padding:2px 4px}.its-tabs::-webkit-scrollbar{display:none}.its-tabs .sep{flex:0 0 1px}.its-right{gap:6px}}
@media (max-width:1400px){.its-status:not(.err){display:none}}
@media (max-width:600px){.its-brand{font-size:14px}.its-right{flex-wrap:nowrap}.its-right .tx{display:none}.its-right .its-btn.primary .tx{display:inline}}

/* buttons */
.its-btn{display:inline-flex;align-items:center;justify-content:center;gap:7px;height:32px;padding:0 12px;border:1px solid var(--its-line);border-radius:8px;background:var(--its-box);color:var(--its-ink)!important;cursor:pointer;font-family:inherit;font-size:13px;font-weight:500;line-height:1;white-space:nowrap;text-decoration:none!important;box-shadow:0 1px 1px rgba(16,24,40,.04);transition:background .12s,border-color .12s,box-shadow .12s,transform .05s}
.its-btn:hover{background:var(--its-hover);border-color:var(--its-line3)}
.its-btn:active{transform:translateY(.5px)}
.its .its-btn:focus-visible{outline:0;box-shadow:var(--its-ring);border-color:var(--its-accent);border-radius:8px}
.its-btn.primary{background:linear-gradient(135deg,#4f6bed,#7460f0);border-color:transparent;color:#fff!important;font-weight:600;box-shadow:0 1px 2px rgba(49,66,160,.35),inset 0 1px 0 rgba(255,255,255,.18)}
.its-btn.primary:hover{background:linear-gradient(135deg,#4561e4,#6a55e8)}
.its .its-btn.primary:focus-visible{box-shadow:0 0 0 2px var(--its-box),0 0 0 4px var(--its-accent)}
.its-btn.ghost{border-color:transparent;background:transparent;box-shadow:none;color:var(--its-sub)!important}
.its-btn.ghost:hover{background:var(--its-hover);color:var(--its-ink)!important}
.its-btn.danger{color:var(--its-red)!important}
.its-btn.danger:hover{background:var(--its-err-bg);border-color:var(--its-err-line)}
.its-btn.small{height:28px;padding:0 10px;font-size:12px;border-radius:7px;gap:6px}
.its-btn.icon{width:32px;padding:0}
.its-btn.small.icon{width:28px}
.its-btn.big{height:38px;padding:0 18px;font-size:14px}
.its-btn[disabled]{opacity:.5;cursor:default;pointer-events:none}

/* inputs */
.its input[type=text],.its input[type=email],.its input[type=number],.its input[type=date],.its input[type=time],.its input[type=password],.its input[type=month],.its input[type=search],.its select,.its textarea{height:34px;border:1px solid var(--its-line);border-radius:8px;padding:0 10px;font-family:inherit;font-size:13px;background-color:var(--its-box);color:var(--its-ink);max-width:100%;box-shadow:inset 0 1px 1px rgba(16,24,40,.03);transition:border-color .12s,box-shadow .12s}
.its select{padding-right:26px;cursor:pointer}
.its textarea{height:auto;min-height:110px;width:100%;padding:9px 11px;line-height:1.65;resize:vertical}
.its input:hover,.its select:hover,.its textarea:hover{border-color:var(--its-line3)}
.its input:focus,.its select:focus,.its textarea:focus{outline:0;border-color:var(--its-accent);box-shadow:var(--its-ring)}
.its input::placeholder,.its textarea::placeholder{color:var(--its-mute);opacity:1}
.its input[type=checkbox],.its input[type=radio]{accent-color:var(--its-accent);width:15px;height:15px;margin:0;vertical-align:-2px;cursor:pointer}
.its input[type=checkbox]:focus-visible{outline:2px solid var(--its-accent);outline-offset:2px;box-shadow:none}
.its label{font-weight:normal;margin:0}
.its-check{display:inline-flex;align-items:center;gap:8px;cursor:pointer}
@media (prefers-color-scheme:dark){.its input,.its select,.its textarea{color-scheme:dark}}
/* a checkbox drawn as a switch (same name/value as before) */
.its input[type=checkbox].its-sw{appearance:none;-webkit-appearance:none;position:relative;flex:0 0 34px;width:34px;height:20px;border-radius:999px;background:var(--its-line3);border:0;transition:background .15s;vertical-align:-5px}
.its input[type=checkbox].its-sw::after{content:"";position:absolute;left:2px;top:2px;width:16px;height:16px;border-radius:50%;background:#fff;box-shadow:0 1px 2px rgba(0,0,0,.3);transition:transform .15s}
.its input[type=checkbox].its-sw:checked{background:var(--its-accent)}
.its input[type=checkbox].its-sw:checked::after{transform:translateX(14px)}

/* cards and common bits */
.its-card{background:var(--its-box);border:1px solid var(--its-line);border-radius:var(--its-radius);padding:18px 20px;margin-bottom:14px;box-shadow:var(--its-shadow)}
.its .its-card h2,.its .its-card h3{display:flex;align-items:center;gap:8px;flex-wrap:wrap;font-size:14.5px!important;margin:0 0 12px;font-weight:700;color:var(--its-ink);line-height:1.4}
.its-card h2>i.fa,.its-card h3>i.fa{width:26px;height:26px;border-radius:7px;display:grid;place-items:center;font-size:12.5px;color:var(--its-accent-ink);background:var(--its-accent-soft);flex:0 0 26px}
.its-card h2+.its-sub,.its-card h3+.its-sub{margin-top:-6px}
.its-sub{color:var(--its-sub);font-size:12px;line-height:1.6}
.its-row{display:flex;flex-wrap:wrap;gap:8px;align-items:center}
.its-head{display:flex;align-items:center;gap:10px 12px;flex-wrap:wrap;margin:2px 2px 14px}
.its-head h1{font-size:19px;font-weight:700;margin:0;letter-spacing:.01em}
.its-head .its-sub{flex:1;min-width:200px}
.its-head .act{margin-left:auto;display:flex;gap:8px;flex-wrap:wrap;align-items:center}
/* badges: tinted background + dark text, always with a word (never color alone) */
.its-badge,.its-pill,.its-soft{--c:#8b95a3;display:inline-flex;align-items:center;gap:5px;height:21px;padding:0 8px;border-radius:6px;font-size:11.5px;font-weight:600;line-height:1;white-space:nowrap;color:var(--c);color:color-mix(in srgb,var(--c) 52%,var(--its-ink));background:var(--its-line2);background:color-mix(in srgb,var(--c) 13%,var(--its-box))}
.its-badge .dot{width:7px;height:7px;border-radius:50%;background:var(--c);flex:0 0 7px}
.its-badge i.fa{font-size:11px;color:var(--c);color:color-mix(in srgb,var(--c) 75%,var(--its-ink))}
.its-tone{--c:#8b95a3;color:var(--c);color:color-mix(in srgb,var(--c) 52%,var(--its-ink))}
.its-tag{display:inline-flex;align-items:center;height:20px;padding:0 7px;border-radius:6px;font-size:11px;font-weight:500;background:transparent;border:1px solid var(--its-line);color:var(--its-sub);white-space:nowrap}
.its-flash,.its-warn{display:flex;gap:10px;align-items:flex-start;padding:10px 14px;border-radius:10px;margin:0 0 12px;line-height:1.6}
.its-flash{background:var(--its-ok-bg);border:1px solid var(--its-ok-line);color:var(--its-ok-ink)}
.its-flash.error{background:var(--its-err-bg);border-color:var(--its-err-line);color:var(--its-err-ink)}
.its-warn{background:var(--its-warn-bg);border:1px solid var(--its-warn-line);color:var(--its-warn-ink)}
.its-flash>i.fa,.its-warn>i.fa{margin-top:3px}
.its-flash a,.its-warn a{color:inherit;text-decoration:underline;font-weight:600}
.its-grid3{display:grid;grid-template-columns:minmax(0,1fr) 340px;gap:14px;align-items:start}
@media (max-width:1100px){.its-grid3{grid-template-columns:minmax(0,1fr)}}
.its-scroll{overflow-x:auto;margin:0 -20px;padding:0 20px}
.its-table{width:100%;border-collapse:collapse}
.its-table th{font-size:11.5px;color:var(--its-faint);font-weight:600;text-align:left;padding:8px 10px;border-bottom:1px solid var(--its-line);white-space:nowrap;text-transform:none;background:transparent}
.its-table td{padding:10px;border-bottom:1px solid var(--its-line2);vertical-align:middle}
.its-table tbody tr:last-child td{border-bottom:0}
.its-scroll .its-table td{white-space:nowrap}
.its-scroll .its-table td.subj{white-space:normal;min-width:180px}
.its-table tbody tr:hover td{background:var(--its-hover)}
.its-table th.r,.its-table td.r{text-align:right;font-variant-numeric:tabular-nums}
.its-table td.over{color:var(--its-red);font-weight:700}

/* numbers at a glance */
.its-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-bottom:14px}
@media (max-width:1100px){.its-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media (max-width:480px){.its-kpis{grid-template-columns:minmax(0,1fr)}}
.its-kpi{--k:var(--its-accent);position:relative;display:grid;grid-template-columns:minmax(0,1fr) auto;gap:2px 10px;align-items:start;background:var(--its-box);border:1px solid var(--its-line);border-radius:var(--its-radius);padding:14px 16px 13px;box-shadow:var(--its-shadow)}
.its-kpi .l{grid-column:1;grid-row:1;font-size:12px;font-weight:600;color:var(--its-sub);line-height:1.45}
.its-kpi .n{grid-column:1;grid-row:2;font-size:26px;font-weight:700;letter-spacing:-.01em;line-height:1.25;font-variant-numeric:tabular-nums;color:var(--its-ink)}
.its-kpi .n small{font-size:13px;font-weight:600;color:var(--its-sub);margin-left:3px;letter-spacing:0}
.its-kpi .h{grid-column:1/-1;grid-row:3;font-size:11.5px;color:var(--its-faint);line-height:1.45}
.its-kpi .i{grid-column:2;grid-row:1/3;width:32px;height:32px;border-radius:9px;display:grid;place-items:center;font-size:14px;color:var(--k);color:color-mix(in srgb,var(--k) 70%,var(--its-ink));background:var(--its-line2);background:color-mix(in srgb,var(--k) 13%,var(--its-box))}
.its-kpi.alert{border-color:var(--its-err-line);background:var(--its-err-bg)}
.its-kpi.alert .n{color:var(--its-err-ink)}
.its-bar{height:6px;border-radius:999px;background:var(--its-line2);overflow:hidden}
.its-bar span{display:block;height:100%;border-radius:999px;background:var(--its-accent)}
.its-bar span.warn{background:var(--its-orange)}
.its-bar span.over{background:var(--its-red)}
.its-meter{margin-bottom:10px}
.its-meter:last-child{margin-bottom:0}
.its-meter .t{display:flex;gap:8px;align-items:baseline;margin-bottom:4px}
.its-meter .t b{margin-left:auto;font-weight:600;font-variant-numeric:tabular-nums}
.its-meter .t b small{display:inline-block;min-width:38px;text-align:right;font-size:11.5px;font-weight:500;color:var(--its-faint)}
.its-legend{display:inline-flex;gap:14px;margin-left:auto;font-size:12px;font-weight:500;color:var(--its-sub)}
.its-legend span{display:inline-flex;align-items:center;gap:6px}
.its-legend i{width:10px;height:10px;border-radius:3px;background:var(--its-accent)}
.its-legend i.out{background:var(--its-green)}
/* daily chart: two thin bars per day, one shared axis */
.its-plot{display:grid;grid-template-columns:auto minmax(0,1fr);gap:0 8px}
.its-plot .y{display:flex;flex-direction:column;justify-content:space-between;height:140px;font-size:11px;color:var(--its-faint);text-align:right;font-variant-numeric:tabular-nums;line-height:1}
.its-plot .y span:first-child{transform:translateY(-4px)}
.its-plot .y span:last-child{transform:translateY(4px)}
.its-chart{position:relative;display:flex;align-items:flex-end;gap:0;height:140px;border-bottom:1px solid var(--its-line3);background:linear-gradient(var(--its-line2),var(--its-line2)) 0 0/100% 1px no-repeat,linear-gradient(var(--its-line2),var(--its-line2)) 0 50%/100% 1px no-repeat}
.its-chart>div{position:relative;flex:1;display:flex;align-items:flex-end;justify-content:center;gap:2px;height:100%;padding:0 2px;min-width:0}
.its-chart>div:hover,.its-chart>div:focus-visible{background:var(--its-hover);outline:0}
.its-chart i{display:block;flex:0 1 9px;min-width:3px;min-height:2px;background:var(--its-accent);border-radius:3px 3px 0 0}
.its-chart i.out{background:var(--its-green)}
.its-chart i.z{visibility:hidden}
.its-chart .tip{display:none;position:absolute;bottom:calc(100% + 4px);left:50%;transform:translateX(-50%);z-index:5;padding:7px 10px;border-radius:8px;background:var(--its-toast);color:#fff;font-size:12px;line-height:1.6;white-space:nowrap;box-shadow:var(--its-shadow-m);pointer-events:none;font-style:normal;text-align:left}
.its-chart .tip b{display:block;font-weight:600;color:#cfd6e2}
.its-chart>div:hover .tip,.its-chart>div:focus-visible .tip{display:block}
.its-chart>div:nth-child(-n+3) .tip{left:0;transform:none}
.its-chart>div:nth-last-child(-n+3) .tip{left:auto;right:0;transform:none}
.its-plot .x{grid-column:2;display:flex;font-size:11px;color:var(--its-faint);padding-top:5px;font-variant-numeric:tabular-nums}
.its-plot .x span{flex:1;text-align:center;min-width:0;white-space:nowrap;overflow:visible}

/* forms */
.its-form-grid{display:grid;grid-template-columns:190px minmax(0,1fr);gap:12px 16px;align-items:center}
.its-form-grid>label[for],.its-form-grid>.lbl{font-weight:600;color:var(--its-ink);font-size:13px}
.its-form-grid .full{grid-column:1/-1}
.its-form-grid>label.full{margin-bottom:-6px}
.its-form-grid>input[type=text],.its-form-grid>input[type=email],.its-form-grid>input[type=password],.its-form-grid>select{width:100%;max-width:440px}
.its-form-grid>textarea{max-width:720px}
.its-form-grid>textarea.full{max-width:none}
@media (max-width:700px){.its-form-grid{grid-template-columns:minmax(0,1fr);gap:6px}.its-form-grid>*:nth-child(even){margin-bottom:8px}.its-form-grid>label:empty{display:none}}
.its-savebar{position:sticky;bottom:0;z-index:5;display:flex;align-items:center;gap:12px;flex-wrap:wrap;padding:10px 14px;margin-bottom:14px;background:var(--its-box);background:color-mix(in srgb,var(--its-box) 88%,transparent);backdrop-filter:blur(8px);border:1px solid var(--its-line);border-radius:var(--its-radius);box-shadow:var(--its-shadow-m)}
.its-props dt{font-size:11.5px;color:var(--its-sub);font-weight:600;margin-top:10px}
.its-props dd{margin:3px 0 0}
.its code{background:var(--its-line2);color:var(--its-ink);padding:2px 6px;border-radius:5px;font-size:12px;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;word-break:break-all}
.its .its-codeline{display:block;padding:8px 10px;margin-top:4px;border:1px solid var(--its-line);background:var(--its-box2);border-radius:8px;user-select:all}
.its pre.its-code{background:var(--its-code-bg);color:var(--its-code-ink);border:0;padding:12px 14px;margin:10px 0;border-radius:10px;font-size:11.5px;line-height:1.7;overflow:auto;white-space:pre;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace}
.its-page{max-width:1500px}
.its-narrow{max-width:880px}
.its-list{margin:0;padding:0;list-style:none}
.its-list li{display:flex;gap:10px;align-items:center;padding:7px 0;border-bottom:1px solid var(--its-line2)}
.its-list li:last-child{border-bottom:0}
.its-list li i.fa{width:26px;height:26px;border-radius:7px;display:grid;place-items:center;font-size:12px;color:var(--its-accent-ink);background:var(--its-accent-soft)}
.its-emptybox{text-align:center;padding:36px 20px;color:var(--its-sub)}
.its-emptybox i.fa{display:block;font-size:30px;color:var(--its-mute);margin-bottom:10px}
/* settings and forms: small helpers */
.its-lead{margin:-4px 0 14px}
.its-foot{margin-top:14px;padding-top:12px;border-top:1px solid var(--its-line2)}
.its-form-grid>label.top{align-self:start;padding-top:8px}
.its-form-grid>input.wide{max-width:none}
.its-inline{display:inline-flex;align-items:center;gap:8px;flex-wrap:wrap}
.its .its-inline input[type=number]{width:76px;text-align:right}
.its-days{display:flex;flex-wrap:wrap;gap:6px}
.its-days label{display:inline-flex;align-items:center;gap:7px;height:32px;padding:0 12px 0 10px;border:1px solid var(--its-line);border-radius:8px;background:var(--its-box);cursor:pointer;font-weight:500;color:var(--its-sub);transition:background .12s,border-color .12s}
.its-days label:hover{border-color:var(--its-line3)}
.its-days label:has(input:checked){background:var(--its-accent-soft);border-color:color-mix(in srgb,var(--its-accent) 45%,var(--its-line));color:var(--its-accent-ink);font-weight:600}
.its-req{display:inline-block;margin-left:4px;padding:1px 6px;border-radius:5px;font-size:10.5px;font-weight:600;color:var(--its-err-ink);background:var(--its-err-bg)}
.its-divider{display:flex;align-items:center;gap:10px;margin-top:8px;font-size:11.5px;font-weight:700;letter-spacing:.03em;color:var(--its-faint)}
.its-divider::after{content:"";flex:1;height:1px;background:var(--its-line2)}
.its-actions{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-top:18px;padding-top:16px;border-top:1px solid var(--its-line2)}
.its-logo.l{width:44px;height:44px;border-radius:13px;font-size:21px;margin-bottom:12px}
.its .its-hero h2{font-size:19px!important;margin-bottom:10px}
.its-hero p{line-height:1.8}
.its-hero .its-list{margin:4px 0 4px}
.its .its-emptybox h2{font-size:16px!important;margin-bottom:6px}
@media (max-width:700px){.its-table .hide-sm{display:none}.its-card{padding:16px}.its-scroll{margin:0 -16px;padding:0 16px}.its-head .act{margin-left:0;width:100%}.its-head .its-sub{flex-basis:100%}}
@media (prefers-reduced-motion:reduce){.its *{transition:none!important;animation:none!important}}
</style>
