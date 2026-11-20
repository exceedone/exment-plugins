<style>
/* ITサポート - shared look. Tokens first; every color below reads a token, so
   the dark theme is only a second set of tokens. */
.its{--its-accent:#4f6bed;--its-accent-2:#7c5cf0;--its-accent-d:#3a52c9;--its-accent-soft:#eef1fe;
  --its-ink:#1d2733;--its-sub:#667383;--its-faint:#98a3b1;--its-bg:#f4f6fa;--its-box:#ffffff;--its-box2:#f8f9fc;
  --its-line:#e3e7ee;--its-line2:#eef1f5;--its-hover:#f3f5fb;--its-sel:#eaeefe;
  --its-red:#e5484d;--its-orange:#f08c2b;--its-amber:#f5b524;--its-green:#30a46c;--its-blue:#3e8bea;--its-purple:#8e4ec6;--its-gray:#8b95a3;
  --its-note:#fff8e6;--its-note-line:#f3d98b;--its-note-ink:#8a6400;
  --its-radius:10px;--its-radius-s:7px;--its-shadow:0 1px 2px rgba(16,24,40,.05),0 1px 3px rgba(16,24,40,.06);--its-shadow-l:0 12px 32px rgba(16,24,40,.16);
  --its-focus:0 0 0 3px rgba(79,107,237,.25);
  color:var(--its-ink);font-size:13.5px;line-height:1.55;-webkit-font-smoothing:antialiased}
@media (prefers-color-scheme:dark){.its{--its-accent:#7b8ff7;--its-accent-2:#a08af7;--its-accent-d:#93a3fa;--its-accent-soft:#252b45;
  --its-ink:#e6e9ef;--its-sub:#a2abb8;--its-faint:#79828f;--its-bg:#14171c;--its-box:#1b1f26;--its-box2:#20252d;
  --its-line:#2c323c;--its-line2:#262b34;--its-hover:#232934;--its-sel:#262d45;
  --its-note:#2e2a1c;--its-note-line:#5b4d22;--its-note-ink:#e8c86a;--its-shadow:0 1px 2px rgba(0,0,0,.4);--its-shadow-l:0 12px 32px rgba(0,0,0,.5)}}
.its *{box-sizing:border-box}
.its a{color:var(--its-accent);text-decoration:none}
.its a:hover{text-decoration:underline}

/* top bar */
.its-top{display:flex;align-items:center;gap:6px 14px;flex-wrap:wrap;padding:10px 14px;margin:0 0 12px;background:var(--its-box);border:1px solid var(--its-line);border-radius:var(--its-radius);box-shadow:var(--its-shadow)}
.its-brand{display:flex;align-items:center;gap:10px;font-weight:700;font-size:15px;color:var(--its-ink)!important;text-decoration:none!important;margin-right:6px}
.its-logo{width:30px;height:30px;border-radius:9px;display:grid;place-items:center;color:#fff;font-size:15px;background:linear-gradient(135deg,var(--its-accent),var(--its-accent-2));box-shadow:0 4px 10px rgba(79,107,237,.35)}
.its-tabs{display:flex;gap:2px;flex-wrap:wrap}
.its-tabs a{padding:6px 11px;border-radius:8px;color:var(--its-sub);font-weight:500;text-decoration:none!important}
.its-tabs a:hover{background:var(--its-hover);color:var(--its-ink)}
.its-tabs a.on{background:var(--its-accent-soft);color:var(--its-accent);font-weight:600}
.its-right{margin-left:auto;display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.its-dot{display:inline-block;width:8px;height:8px;border-radius:50%;background:var(--its-gray);margin-right:5px}
.its-dot.on{background:var(--its-green);box-shadow:0 0 0 3px rgba(48,164,108,.18)}
.its-dot.err{background:var(--its-red);box-shadow:0 0 0 3px rgba(229,72,77,.18)}
.its-status{font-size:12px;color:var(--its-sub);white-space:nowrap}

/* buttons, inputs */
.its-btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;height:32px;padding:0 12px;border:1px solid var(--its-line);border-radius:8px;background:var(--its-box);color:var(--its-ink)!important;cursor:pointer;font-size:13px;font-weight:500;white-space:nowrap;text-decoration:none!important;transition:background .12s,border-color .12s,box-shadow .12s}
.its-btn:hover{background:var(--its-hover);border-color:#cfd6e2}
.its-btn:focus-visible{outline:0;box-shadow:var(--its-focus)}
.its-btn.primary{background:linear-gradient(135deg,var(--its-accent),var(--its-accent-2));border-color:transparent;color:#fff!important;box-shadow:0 2px 6px rgba(79,107,237,.3)}
.its-btn.primary:hover{filter:brightness(1.06)}
.its-btn.ghost{border-color:transparent;background:transparent}
.its-btn.ghost:hover{background:var(--its-hover)}
.its-btn.danger{color:var(--its-red)!important}
.its-btn.small{height:26px;padding:0 9px;font-size:12px;border-radius:7px}
.its-btn[disabled]{opacity:.55;cursor:default}
.its input[type=text],.its input[type=email],.its input[type=number],.its input[type=date],.its input[type=time],.its input[type=password],.its input[type=month],.its input[type=search],.its select,.its textarea{height:32px;border:1px solid var(--its-line);border-radius:8px;padding:0 10px;font-size:13px;background:var(--its-box);color:var(--its-ink);max-width:100%;transition:border-color .12s,box-shadow .12s}
.its textarea{height:auto;min-height:110px;width:100%;padding:9px 11px;font-family:inherit;line-height:1.6;resize:vertical}
.its input:focus,.its select:focus,.its textarea:focus{outline:0;border-color:var(--its-accent);box-shadow:var(--its-focus)}
.its label{font-weight:normal;margin:0}

/* cards and common bits */
.its-card{background:var(--its-box);border:1px solid var(--its-line);border-radius:var(--its-radius);padding:14px 16px;margin-bottom:12px;box-shadow:var(--its-shadow)}
.its-card h2,.its-card h3{font-size:14px;margin:0 0 10px;font-weight:700;color:var(--its-ink)}
.its-sub{color:var(--its-sub);font-size:12px}
.its-row{display:flex;flex-wrap:wrap;gap:8px;align-items:center}
.its-pill{display:inline-flex;align-items:center;gap:4px;height:20px;padding:0 8px;border-radius:999px;font-size:11.5px;font-weight:600;color:#fff;white-space:nowrap;line-height:20px}
.its-soft{display:inline-flex;align-items:center;gap:5px;height:22px;padding:0 9px;border-radius:999px;font-size:12px;font-weight:600;white-space:nowrap}
.its-tag{display:inline-block;padding:0 7px;border-radius:5px;font-size:11px;background:var(--its-line2);color:var(--its-sub);margin-right:3px;line-height:18px}
.its-flash{padding:10px 14px;border-radius:var(--its-radius-s);margin:0 0 12px;background:#e9f7ef;border:1px solid #b9e4c9;color:#1d6b43}
.its-flash.error{background:#fdecec;border-color:#f5c2c2;color:#a52a2a}
.its-warn{padding:10px 14px;border-radius:var(--its-radius-s);margin:0 0 12px;background:#fff6e5;border:1px solid #f5d79e;color:#7a5400}
.its-grid3{display:grid;grid-template-columns:minmax(0,1fr) 330px;gap:12px}
@media (max-width:1100px){.its-grid3{grid-template-columns:1fr}}
.its-table{width:100%;border-collapse:collapse}
.its-table th{font-size:11.5px;color:var(--its-sub);font-weight:600;text-align:left;padding:8px;border-bottom:1px solid var(--its-line);white-space:nowrap;text-transform:none}
.its-table td{padding:9px 8px;border-bottom:1px solid var(--its-line2);vertical-align:middle}
.its-table tr:hover td{background:var(--its-hover)}
.its-kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:12px}
.its-kpi{position:relative;overflow:hidden;background:var(--its-box);border:1px solid var(--its-line);border-radius:var(--its-radius);padding:14px 16px;box-shadow:var(--its-shadow)}
.its-kpi::before{content:"";position:absolute;left:0;top:0;bottom:0;width:4px;background:var(--k,var(--its-accent))}
.its-kpi .n{font-size:26px;font-weight:700;letter-spacing:-.5px;line-height:1.2}
.its-kpi .l{font-size:12px;color:var(--its-sub);margin-top:2px}
.its-kpi .i{position:absolute;right:14px;top:14px;color:var(--k,var(--its-accent));opacity:.35;font-size:20px}
.its-bar{height:8px;border-radius:5px;background:var(--its-line2);overflow:hidden}
.its-bar span{display:block;height:100%;border-radius:5px;background:linear-gradient(90deg,var(--its-accent),var(--its-accent-2))}
.its-chart{display:flex;align-items:flex-end;gap:3px;height:120px;padding-top:6px;border-bottom:1px solid var(--its-line)}
.its-chart div{flex:1;display:flex;align-items:flex-end;gap:1px;height:100%}
.its-chart i{display:block;flex:1;background:var(--its-accent);opacity:.85;border-radius:3px 3px 0 0;min-height:1px}
.its-chart i.out{background:var(--its-green)}
.its-form-grid{display:grid;grid-template-columns:200px 1fr;gap:12px 14px;align-items:center}
.its-form-grid .full{grid-column:1/-1}
@media (max-width:700px){.its-form-grid{grid-template-columns:1fr}}
.its-props dt{font-size:11.5px;color:var(--its-sub);font-weight:600;margin-top:10px}
.its-props dd{margin:3px 0 0}
.its code{background:var(--its-line2);padding:1px 6px;border-radius:5px;font-size:12px;word-break:break-all}
.its pre.its-code{background:#0f172a;color:#e2e8f0;padding:12px;border-radius:8px;font-size:12px;overflow:auto;white-space:pre-wrap}
.its-page{max-width:1500px}
</style>
