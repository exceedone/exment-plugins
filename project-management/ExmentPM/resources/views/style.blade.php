<style>
/* ==== project portal (Backlog style) ================================ */
/* The palette sits on .pmportal rather than on .bl-app: the hover card is
   appended to <body> wearing that class with no .bl-app above it, so this is
   the only level both of them share. */
/* S14: with Exment's modern design on (modern.css defines --exm-ui-*), the
   portal takes its accent, text, lines and radius from it - the site's skin
   colour instead of the portal's own green. With the classic design those
   are not defined and the values after the comma are the ones it always had. */
.pmportal{--bl-accent:var(--exm-ui-accent,#4caf93);--bl-accent-d:var(--exm-ui-accent-strong,#3d9a80);
  --bl-accent-ink:var(--exm-ui-accent-strong,#2c6e5b);--bl-accent-line:var(--exm-ui-accent-ring,#bfe3d6);
  --bl-ink:var(--exm-ui-text,#24323f);--bl-sub:var(--exm-ui-text-sub,#6b7a88);
  --bl-line:var(--exm-ui-line,#dfe5ea);--bl-bg:var(--exm-ui-bg-sub,#f5f7f9);--bl-faint:var(--exm-ui-text-faint,#8b99a5);--bl-box:#ffffff;--bl-box2:#f5f7f9;--bl-chip:#eef3f6;--bl-hover:var(--exm-ui-accent-soft,#f2f8f5);--bl-red:var(--exm-ui-danger,#d9534f);--bl-amber:#eda62a;
  /* design tokens (ISS00287): soft separator, muted text, radius, shadow, type scale, spacing, focus ring */
  --bl-line2:var(--exm-ui-line-soft,#e9eef2);--bl-muted:var(--bl-faint);
  --bl-radius:var(--exm-ui-radius-sm,6px);--bl-radius-s:4px;--bl-radius-pill:999px;
  --bl-shadow-s:0 1px 2px rgba(15,42,60,.06);--bl-shadow-m:0 8px 24px rgba(15,42,60,.14);
  --bl-fs-xs:11px;--bl-fs-s:12px;--bl-fs:13px;--bl-fs-l:14px;--bl-fs-xl:20px;
  --bl-sp-1:4px;--bl-sp-2:8px;--bl-sp-3:12px;--bl-sp-4:16px;
  --bl-focus:0 0 0 3px color-mix(in srgb, var(--bl-accent) 28%, transparent);}
.pmportal .bl-app{font-size:13px;color:var(--bl-ink);}
.pmportal a{cursor:pointer;}

/* Every .row in the portal is one of its own flex lines, never a Bootstrap
   grid row. The admin layout loads Bootstrap 5, whose .row adds negative
   gutters and wrapping and gives each child width:100%, which stacked a
   line's parts vertically and pushed its rules past the box edges. */
.pmportal .row,
.pp-nedit .row{--bs-gutter-x:0;--bs-gutter-y:0;margin-left:0;margin-right:0;flex-wrap:nowrap;}
.pmportal .row > *,
.pp-nedit .row > *{width:auto;max-width:none;flex-shrink:1;}

/* ---- lazy embedded widgets (kanban / gantt) ---- */
.pmportal .pp-embed{min-height:80px;}
.pmportal .pp-embed-loading{color:var(--bl-sub);}
.pmportal .pp-embed-loading .bl-boxbody{padding:14px 16px;font-size:13px;}
.pmportal .pp-embed-loading i.fa-spinner{margin-right:6px;color:var(--bl-accent);}

/* ---- project header ---- */
.pmportal .bl-projhead{display:flex;align-items:center;gap:14px;background:var(--bl-box);border:1px solid var(--bl-line);
  border-radius:var(--bl-radius,6px);padding:12px 16px 10px;margin-bottom:10px;}
.pmportal .bl-projicon{width:44px;height:44px;border-radius:8px;background:linear-gradient(135deg,var(--bl-accent),var(--bl-accent-d));
  color:#fff;display:flex;align-items:center;justify-content:center;font-size:20px;flex:none;}
.pmportal .bl-projname{font-size:19px;font-weight:700;line-height:1.2;}
.pmportal .bl-projkey{display:inline-block;background:var(--bl-chip);border:1px solid var(--bl-line);border-radius:3px;
  padding:1px 8px;font-weight:700;color:var(--bl-sub);font-size:12px;margin-left:8px;vertical-align:2px;}
.pmportal .bl-projmeta{color:var(--bl-sub);font-size:12px;margin-top:3px;}
.pmportal .bl-projmeta i{margin:0 4px 0 10px;}
/* Takes the width left over by the icon and the buttons. Without min-width:0
   a flex item refuses to shrink below the widest word it contains, so a long
   project note used to force the row wider than the card. */
.pmportal .bl-projinfo{flex:1 1 auto;min-width:0;}
.pmportal .bl-projhead .bl-avatars{margin-left:auto;display:flex;align-items:center;}
.pmportal .bl-projhead .bl-av{margin-left:-6px;box-shadow:0 0 0 2px var(--bl-box);}

/* ---- tabs ---- */
.pmportal .bl-tabs{display:flex;align-items:center;background:var(--bl-box);border:1px solid var(--bl-line);
  border-radius:var(--bl-radius,6px);padding:0 8px;margin-bottom:12px;flex-wrap:wrap;}
.pmportal .bl-tab{padding:10px 14px;color:var(--bl-sub);font-weight:600;border-bottom:3px solid transparent;
  text-decoration:none !important;}
.pmportal .bl-tab i{margin-right:5px;}
.pmportal .bl-tab:hover{color:var(--bl-ink);}
.pmportal .bl-tab.active{color:var(--bl-accent-d);border-bottom-color:var(--bl-accent);}
.pmportal .bl-addissue{margin-left:auto;background:var(--bl-accent);color:#fff !important;border-radius:var(--bl-radius-s,4px);
  padding:6px 14px;font-weight:700;text-decoration:none !important;}
.pmportal .bl-addissue:hover{background:var(--bl-accent-d);}
.pmportal .bl-tabsearch{margin-left:10px;position:relative;}
.pmportal .bl-tabsearch input{border:1px solid var(--bl-line);border-radius:var(--bl-radius-s,4px);padding:5px 8px 5px 28px;width:200px;}
.pmportal .bl-tabsearch .fa{position:absolute;left:9px;top:8px;color:var(--bl-faint);}

/* ---- shared bits ---- */
.pmportal .bl-card-box{background:var(--bl-box);border:1px solid var(--bl-line);border-radius:var(--bl-radius,6px);margin-bottom:12px;}
.pmportal .bl-card-box>.bl-boxhead{padding:10px 14px;border-bottom:1px solid var(--bl-line);font-weight:700;font-size:14px;}
.pmportal .bl-card-box>.bl-boxhead .fa{color:var(--bl-sub);margin-right:6px;}
.pmportal .bl-card-box>.bl-boxbody{padding:12px 14px;}
/* click-to-collapse card head (used for 基本情報 / 子課題 etc.) */
.pmportal .bl-card-box>.bl-boxhead[data-collapse]{cursor:pointer;user-select:none;display:flex;align-items:center;}
.pmportal .bl-card-box>.bl-boxhead[data-collapse]:hover{background:var(--bl-hover);}
.pmportal .bl-card-box>.bl-boxhead[data-collapse] .bl-caret{margin-left:auto;font-size:11px;color:var(--bl-sub);
  transition:transform .2s ease;}
.pmportal .bl-card-box.bl-collapsed>.bl-boxhead[data-collapse] .bl-caret{transform:rotate(-90deg);}
.pmportal .bl-card-box.bl-collapsed>.bl-boxhead{border-bottom-color:transparent;}
.pmportal .bl-card-box.bl-collapsed>.bl-boxbody{display:none;}
.pmportal .bl-pill{display:inline-block;border-radius:20px;color:#fff;font-weight:700;font-size:11px;
  padding:2px 10px;line-height:1.6;white-space:nowrap;}
.pmportal .bl-tag{display:inline-block;border-radius:3px;color:#fff;font-weight:700;font-size:11px;
  padding:1px 7px;white-space:nowrap;}
.pmportal .bl-av{display:inline-flex;width:26px;height:26px;border-radius:50%;color:#fff;align-items:center;
  justify-content:center;font-size:12px;font-weight:700;flex:none;}
.pmportal .bl-av.lg{width:34px;height:34px;font-size:14px;}
/* ISS00254: registered avatar laid over the initial (shared by .bl-av and the dashboard feed .av) */
.pmportal .bl-av,.pp-ta .bl-av,.pp-fd .av{position:relative;overflow:hidden;}
/* two-letter initials ("SN", "野呂") need a little less size to fit the circle */
.pmportal .bl-av{font-size:11px;letter-spacing:-.3px;}
.pmportal .bl-av.lg{font-size:13px;}
.pp-ta .bl-av{font-size:9px;letter-spacing:-.3px;}
.bl-avimg{position:absolute;top:0;left:0;width:100%;height:100%;border-radius:50%;object-fit:cover;background:#fff;}
.pmportal .bl-avname{display:inline-flex;align-items:center;gap:6px;white-space:nowrap;}
.pmportal .bl-prio{display:inline-flex;align-items:center;gap:4px;font-weight:700;white-space:nowrap;}
.pmportal .bl-prio svg{vertical-align:-2px;}
.pmportal .bl-key{font-weight:700;color:var(--bl-sub);white-space:nowrap;}
.pmportal a.bl-key:hover{color:var(--bl-accent-d);}
.pmportal .pp-ikcopy{border:0;background:none;padding:0 4px;margin-left:2px;color:var(--bl-faint);cursor:pointer;font-size:12px;line-height:1;vertical-align:baseline;}
.pmportal .pp-ikcopy:hover,.pmportal .pp-ikcopy:focus-visible{color:var(--bl-accent-d);}
.pmportal .bl-due{white-space:nowrap;}
.pmportal .bl-due.over{color:var(--bl-red);font-weight:700;}
.pmportal .bl-due.soon{color:#b57c0e;font-weight:700;}
.pmportal .bl-star{color:#c3ccd4;cursor:pointer;white-space:nowrap;}
.pmportal .bl-star.on{color:#f2b01e;}
.pmportal .bl-star:hover{color:#f2b01e;}
/* ★ on the issue detail: a labelled button beside ウォッチ (注目). */
.pmportal .pp-star-d .fa{color:#c3ccd4;}
.pmportal .pp-star-d:hover .fa{color:#f2b01e;}
.pmportal .pp-star-d.on{border-color:#f2b01e !important;background:color-mix(in srgb, #f2b01e 12%, var(--bl-box)) !important;}
.pmportal .pp-star-d.on .fa{color:#f2b01e;}
.pmportal .pp-star-d .ct{font-weight:700;}
@media (max-width:480px){.pmportal .pp-star-d .lb{display:none;}}
.pmportal .bl-pbar{background:#e9eef2;border-radius:var(--bl-radius-s,4px);height:8px;min-width:70px;position:relative;overflow:hidden;}
.pmportal .bl-pbar>i{position:absolute;left:0;top:0;bottom:0;background:var(--bl-accent);border-radius:var(--bl-radius-s,4px);}
.pmportal .bl-mention{background:#e8f4ff;color:#2b6cb0;border-radius:3px;padding:0 4px;font-weight:700;}
.pmportal a.bl-mention{text-decoration:none;cursor:pointer;}
.pmportal a.bl-mention:hover{background:#d3e8fb;}
.pmportal .bl-issuelink{color:#2b6cb0;font-weight:700;text-decoration:none;border-bottom:1px dashed #9ec5e8;}

/* ---- home ---- */
.pmportal .bl-home{display:grid;grid-template-columns:1fr 380px;gap:12px;align-items:start;}
.pmportal .bl-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:12px;}
.pmportal .bl-stat{background:var(--bl-box);border:1px solid var(--bl-line);border-radius:var(--bl-radius,6px);padding:10px 14px;}
.pmportal .bl-stat .n{font-size:24px;font-weight:800;line-height:1.1;}
.pmportal .bl-stat .l{color:var(--bl-sub);font-size:11px;margin-top:2px;}
.pmportal .bl-stat .l .fa{margin-right:4px;}
.pmportal .bl-stat.warn .n{color:var(--bl-red);}
.pmportal .bl-stack{display:flex;gap:2px;height:14px;border-radius:var(--bl-radius-s,4px);overflow:hidden;margin:4px 0 8px;}
.pmportal .bl-stack>i{display:block;height:100%;}
.pmportal .bl-leg{display:flex;flex-wrap:wrap;gap:6px 14px;font-size:12px;color:var(--bl-sub);}
.pmportal .bl-leg b{color:var(--bl-ink);}
.pmportal .bl-leg .sw{display:inline-block;width:10px;height:10px;border-radius:2px;margin-right:5px;vertical-align:-1px;}
.pmportal .bl-ms .row{display:flex;align-items:center;gap:10px;padding:7px 0;border-top:1px dashed var(--bl-line2,#e7edf2);}
.pmportal .bl-ms .row:first-child{border-top:0;}
.pmportal .bl-ms .nm{width:170px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.pmportal .bl-ms .bl-pbar{flex:1;}
.pmportal .bl-ms .cnt{width:120px;text-align:right;color:var(--bl-sub);font-size:12px;white-space:nowrap;}
.pmportal .bl-feed .item{display:flex;gap:10px;padding:9px 0;border-top:1px dashed var(--bl-line2,#e7edf2);}
.pmportal .bl-feed .item:first-child{border-top:0;padding-top:2px;}
.pmportal .bl-feed .ic{width:26px;height:26px;border-radius:var(--bl-radius,6px);color:#fff;display:flex;align-items:center;
  justify-content:center;font-size:12px;flex:none;margin-top:1px;}
.pmportal .bl-feed .tx{line-height:1.5;}
.pmportal .bl-feed .tx .who{font-weight:700;}
.pmportal .bl-feed .tx .ex{color:var(--bl-faint);font-size:11px;display:block;}
.pmportal .bl-feed .tm{color:var(--bl-faint);font-size:11px;margin-top:1px;}
.pmportal .bl-member{display:flex;align-items:center;gap:8px;padding:6px 0;border-top:1px dashed var(--bl-line2,#e7edf2);}
.pmportal .bl-member:first-child{border-top:0;}
.pmportal .bl-member .role{margin-left:auto;font-size:11px;color:var(--bl-sub);background:var(--bl-hover);
  border-radius:10px;padding:1px 8px;}

/* ---- issues ---- */
.pmportal .bl-toolbar{background:var(--bl-box);border:1px solid var(--bl-line);border-radius:var(--bl-radius,6px);padding:10px 12px;margin-bottom:10px;}
.pmportal .bl-sttabs{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:9px;}
.pmportal .bl-sttab{border:1px solid var(--bl-line);border-radius:16px;background:var(--bl-box);padding:3px 12px;
  font-weight:600;color:var(--bl-sub);cursor:pointer;font-size:12px;}
.pmportal .bl-sttab .ct{font-weight:800;margin-left:4px;}
.pmportal .bl-sttab.active{border-color:var(--bl-accent);color:var(--bl-accent-d);background:color-mix(in srgb, var(--bl-accent) 13%, var(--bl-box));}
.pmportal .bl-sttab .dot{display:inline-block;width:8px;height:8px;border-radius:50%;margin-right:5px;}
/* A second row of pills under the status tabs. Same shape as the status
   tabs so the two read as one control, dimmer so it is clear which row is
   the primary cut. */
.pmportal .bl-qchips{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:9px;}
.pmportal .bl-qchip{border:1px solid var(--bl-line);border-radius:16px;background:var(--bl-box);
  padding:3px 12px;font-weight:600;color:var(--bl-faint);cursor:pointer;font-size:12px;}
.pmportal .bl-qchip:hover{color:var(--bl-accent-d);border-color:var(--bl-accent);}
.pmportal .bl-qchip .ct{font-weight:800;margin-left:4px;}
.pmportal .bl-qchip i{margin-right:5px;}
.pmportal .bl-qchip.active{border-color:var(--bl-accent);color:var(--bl-accent-d);
  background:color-mix(in srgb, var(--bl-accent) 13%, var(--bl-box));}
.pmportal .bl-qchip.warn.active{border-color:var(--bl-red,#d9534f);color:var(--bl-red,#d9534f);
  background:color-mix(in srgb, var(--bl-red,#d9534f) 10%, var(--bl-box));}
.pmportal .bl-filters{display:flex;gap:8px;flex-wrap:wrap;align-items:center;}
.pmportal .bl-filters select,.pmportal .bl-filters input{border:1px solid var(--bl-line);border-radius:var(--bl-radius-s,4px);
  padding:5px 8px;background:var(--bl-box);color:var(--bl-ink);font-size:12px;}
.pmportal .bl-filters .sv{color:var(--bl-accent-d);font-weight:600;font-size:12px;text-decoration:none;white-space:nowrap;}
.pmportal .bl-filters .sv:hover{text-decoration:underline;}
/* The actions sit together on the right; margin-left:auto used to live on
   each link, so a second one landed in the middle of the row. */
.pmportal .bl-fright{margin-left:auto;display:flex;gap:14px;align-items:center;}
/* Tree view of the issue list. */
.pmportal .bl-treebtn{border:1px solid var(--bl-line);border-radius:var(--bl-radius-s,4px);background:var(--bl-box);
  color:var(--bl-sub);font-size:12px;font-weight:600;padding:5px 10px;cursor:pointer;white-space:nowrap;}
.pmportal .bl-treebtn.active{border-color:var(--bl-accent);color:var(--bl-accent-d);
  background:color-mix(in srgb, var(--bl-accent) 10%, var(--bl-box));}
.pmportal .bl-list .pp-tind{display:inline-block;white-space:nowrap;}
.pmportal .bl-list .pp-tgl,.pmportal .bl-list .pp-tgl-sp{display:inline-block;min-width:16px;margin-right:6px;}
.pmportal .bl-list .pp-tgl{color:var(--bl-sub);font-size:11px;text-decoration:none;cursor:pointer;padding:2px 3px;
  border-radius:var(--bl-radius-s,4px);}
.pmportal .bl-list .pp-tgl:hover{color:var(--bl-accent-d);background:color-mix(in srgb, var(--bl-accent) 12%, var(--bl-box));}
.pmportal .bl-list .pp-tgl .n{font-weight:700;}
/* ISS00248: the alias */
.pmportal .bl-list td.pp-al{white-space:nowrap;font-family:Menlo,Consolas,monospace;font-size:12px;}
.pmportal .pp-al-d{margin-left:8px;padding:1px 7px;border-radius:9px;background:color-mix(in srgb, var(--bl-accent) 12%, var(--bl-box));font-family:Menlo,Consolas,monospace;font-size:12px;}
.pmportal .pp-al-set{margin-left:6px;color:var(--bl-sub);}
/* the picker of several values of a filter (ISS00255) */
.pmportal .pp-fpick{position:fixed;z-index:1060;background:var(--bl-box);border:1px solid var(--bl-line,#dfe5ea);border-radius:6px;box-shadow:0 6px 18px rgba(0,0,0,.14);padding:8px;width:260px;}
.pmportal .pp-fpick .s{width:100%;margin-bottom:6px;padding:4px 6px;border:1px solid var(--bl-line,#dfe5ea);border-radius:4px;}
.pmportal .pp-fpick .ls{max-height:300px;overflow:auto;}
.pmportal .pp-fpick label{display:flex;align-items:center;gap:6px;padding:4px 6px;margin:0;font-weight:400;font-size:12.5px;cursor:pointer;border-radius:4px;}
.pmportal .pp-fpick label:hover{background:var(--bl-hover,#f2f8f5);}
.pmportal .pp-fpick .ft{display:flex;justify-content:flex-end;gap:6px;margin-top:8px;}
.pmportal .bl-list .pp-tcol{width:1%;white-space:nowrap;padding-left:4px;padding-right:0;}
/* 件名サブ (schema 19): second subject line, smaller and muted */
.pmportal .bl-list td.sub .pp-subsub{font-size:12px;font-weight:400;color:var(--bl-sub);margin-top:2px;line-height:1.35;}
.pmportal .pp-dsubsub{font-size:14px;color:var(--bl-sub);margin:2px 0 8px;line-height:1.4;}
.pmportal .bl-list td.sub .pp-sbox{display:flex;align-items:flex-start;}
.pmportal .bl-list td.sub .pp-sbox .pp-tind{flex:none;}
.pmportal .bl-list td.sub .pp-stext{flex:1 1 auto;min-width:0;}
/* inline cell edit (double-click) */
.pmportal .bl-list td[data-ed]{cursor:cell;}
.pmportal .bl-list td[data-ed]:hover{box-shadow:inset 0 0 0 1px color-mix(in srgb, var(--bl-accent) 45%, transparent);}
.pmportal .bl-list td .pp-ed-in{width:100%;min-width:110px;font-size:12px;padding:3px 4px;border:1px solid var(--bl-accent);border-radius:4px;background:var(--bl-box);}
.pmportal .bl-list td.pp-ed-saving{opacity:.5;}
.pmportal .bl-list td.pp-ed-fail{background:#fde8e6 !important;box-shadow:inset 0 0 0 2px #e74c3c;}
/* the tree slot at the indent: ▼/▶ + count for a parent, └ for a leaf child */
.pmportal .bl-list .pp-tind .pp-tgl,.pmportal .bl-list .pp-tind .pp-tbr{display:inline-block;min-width:26px;margin-right:4px;}
/* ISS00361: the ▼/▶ is a 28px target (36px on a touch screen), so a near miss does not open the issue */
.pmportal .bl-list .pp-tind .pp-tgl{display:inline-flex;align-items:center;justify-content:center;min-width:28px;min-height:28px;margin:-6px 4px -6px -4px;padding:0 4px;}
.pmportal .bl-list .pp-tgl:focus-visible{outline:2px solid var(--bl-accent);outline-offset:1px;}
@media (pointer: coarse){.pmportal .bl-list .pp-tind .pp-tgl{min-width:36px;min-height:36px;margin:-10px 4px -10px -6px;}}
/* the Wiki tree: the same ▼/▶ (ISS00361) */
.pmportal #pp-wtree .pp-wtgl,.pmportal #pp-wtree .pp-wtgl-sp{display:inline-flex;align-items:center;justify-content:center;min-width:28px;min-height:28px;margin:-6px 0 -6px -6px;}
.pmportal #pp-wtree .pp-wtgl{color:var(--bl-sub);font-size:11px;text-decoration:none;border-radius:var(--bl-radius-s,4px);}
.pmportal #pp-wtree .pp-wtgl:hover{color:var(--bl-accent-d);background:color-mix(in srgb, var(--bl-accent) 12%, var(--bl-box));}
.pmportal #pp-wtree .pp-wtgl:focus-visible{outline:2px solid var(--bl-accent);outline-offset:1px;}
@media (pointer: coarse){.pmportal #pp-wtree .pp-wtgl,.pmportal #pp-wtree .pp-wtgl-sp{min-width:36px;min-height:36px;margin:-10px 0 -10px -8px;}}
.pmportal .bl-list .pp-tind .pp-tgl .n{font-size:10px;margin-left:2px;}
/* tree view: make children obvious - branch mark, tinted row, accent edge; parents bold */
/* └ branch mark: dark accent, bold and a bit larger so it reads at a glance (snoro 2026-11-18) */
.pmportal .bl-list .pp-tbr{color:var(--bl-accent-d);margin-right:4px;font-weight:800;font-size:15px;line-height:1;}
.pmportal .bl-list tr.pp-tkid td{background:color-mix(in srgb, var(--bl-accent) 5%, var(--bl-box));}
.pmportal .bl-list tr.pp-tkid.pp-td2 td{background:color-mix(in srgb, var(--bl-accent) 9%, var(--bl-box));}
.pmportal .bl-list tr.pp-tkid.pp-td3 td{background:color-mix(in srgb, var(--bl-accent) 13%, var(--bl-box));}
.pmportal .bl-list tr.pp-tkid.pp-td4 td{background:color-mix(in srgb, var(--bl-accent) 17%, var(--bl-box));}
.pmportal .pp-rollup{line-height:1.2;margin-top:2px;white-space:nowrap;}
.pmportal .bl-list tr.pp-tkid td:first-child{box-shadow:inset 3px 0 0 color-mix(in srgb, var(--bl-accent) 55%, transparent);}
.pmportal .bl-list tr.pp-tkid:hover td{background:var(--bl-hover,var(--exm-ui-accent-soft,#f2f8f5));}
.pmportal .bl-list tr.pp-tpar td.sub{font-weight:700;}
.pmportal .bl-fmine{border:1px solid var(--bl-line);border-radius:var(--bl-radius-s,4px);background:var(--bl-box);
  color:var(--bl-sub);font-size:12px;font-weight:600;padding:5px 10px;cursor:pointer;white-space:nowrap;}
.pmportal .bl-fmine:hover{border-color:var(--bl-accent);color:var(--bl-accent-d);}
.pmportal .bl-fmine[aria-pressed="true"]{border-color:var(--bl-accent);color:var(--bl-accent-d);
  background:color-mix(in srgb, var(--bl-accent) 13%, var(--bl-box));}
/* Bulk bar. Only ever on screen while something is ticked, so it is allowed
   to be loud. */
.pmportal .bl-bulk{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:10px;
  background:color-mix(in srgb, var(--bl-accent) 10%, var(--bl-box));
  border:1px solid var(--bl-accent);border-radius:var(--bl-radius,6px);padding:9px 12px;}
.pmportal .bl-bulk .n{font-weight:700;color:var(--bl-accent-d);font-size:12.5px;white-space:nowrap;}
.pmportal .bl-bulk select{border:1px solid var(--bl-line);border-radius:var(--bl-radius-s,4px);padding:5px 8px;
  background:var(--bl-box);color:var(--bl-ink);font-size:12px;max-width:230px;}
.pmportal .bl-bulk .bl-btn-main{margin-left:auto;}
.pmportal .bl-bulk .bl-btn-main.armed{background:var(--bl-red,#d9534f);border-color:var(--bl-red,#d9534f);}

.pmportal .bl-list th.chk,.pmportal .bl-list td.chk{width:30px;padding-left:12px;padding-right:0;}
.pmportal .bl-list th.chk input,.pmportal .bl-list td.chk input{cursor:pointer;margin:0;vertical-align:middle;}
.pmportal .bl-list tbody tr.sel{background:color-mix(in srgb, var(--bl-accent) 8%, var(--bl-box));}

/* Pager. Count on the left, page buttons in the middle, how many per page on
   the right - the order they get read in. */
.pmportal .bl-pager{display:flex;gap:12px;align-items:center;flex-wrap:wrap;
  padding:8px 12px;border-top:1px solid var(--bl-line);font-size:12px;color:var(--bl-sub);}
.pmportal .bl-pager .nav{display:flex;gap:4px;align-items:center;margin-left:auto;}
.pmportal .bl-pager .nav button{border:1px solid var(--bl-line);background:var(--bl-box);
  color:var(--bl-sub);border-radius:var(--bl-radius-s,4px);min-width:28px;height:26px;padding:0 7px;cursor:pointer;font-size:12px;}
.pmportal .bl-pager .nav button:hover:not(:disabled){border-color:var(--bl-accent);color:var(--bl-accent-d);}
.pmportal .bl-pager .nav button[aria-current="true"]{border-color:var(--bl-accent);color:#fff;
  background:var(--bl-accent);font-weight:700;}
.pmportal .bl-pager .nav button:disabled{opacity:.4;cursor:default;}
.pmportal .bl-pager .nav .gap{color:var(--bl-faint);padding:0 2px;}
.pmportal .bl-pager .sz{display:flex;gap:6px;align-items:center;}
.pmportal .bl-pager .sz select{border:1px solid var(--bl-line);border-radius:var(--bl-radius-s,4px);padding:3px 6px;
  background:var(--bl-box);color:var(--bl-ink);font-size:12px;}

/* Saved conditions */
.pmportal .pp-sf-wrap{position:relative;display:inline-block;}
.pmportal .pp-sf-btn{border:0;background:none;padding:0;cursor:pointer;}
.pmportal .pp-sf-menu{position:absolute;right:0;top:calc(100% + 6px);z-index:30;width:270px;
  background:var(--bl-box);border:1px solid var(--bl-line);border-radius:var(--bl-radius,6px);
  box-shadow:var(--bl-shadow-m,0 8px 24px rgba(0,0,0,.14));padding:6px 0;}
.pmportal .pp-sf-list{max-height:240px;overflow-y:auto;}
.pmportal .pp-sf-item{display:flex;align-items:center;gap:6px;padding:6px 12px;cursor:pointer;
  font-size:12.5px;color:var(--bl-ink);}
.pmportal .pp-sf-item:hover{background:var(--bl-hover);}
.pmportal .pp-sf-item .nm{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.pmportal .pp-sf-item .rm{color:var(--bl-faint);flex-shrink:0;padding:0 2px;}
.pmportal .pp-sf-item .rm:hover{color:var(--bl-red,#d9534f);}
.pmportal .pp-sf-empty{padding:8px 12px;color:var(--bl-faint);font-size:12px;}
.pmportal .pp-sf-add{display:flex;gap:6px;padding:8px 12px;border-top:1px solid var(--bl-line);}
.pmportal .pp-sf-add input{flex:1;min-width:0;border:1px solid var(--bl-line);border-radius:var(--bl-radius-s,4px);
  padding:5px 8px;background:var(--bl-box);color:var(--bl-ink);font-size:12px;}
.pmportal .pp-sf-def{display:flex;gap:6px;align-items:center;padding:7px 12px;
  border-top:1px solid var(--bl-line);font-size:12px;color:var(--bl-sub);cursor:pointer;}

/* Refresh button in the project header */
.pmportal .pp-freshat{font-size:11px;color:var(--bl-muted);margin-left:auto;padding-right:2px;white-space:nowrap;}
.pmportal .pp-refresh.busy{opacity:.6;pointer-events:none;}
.pmportal .pp-refresh.stale{border-color:var(--bl-accent);color:var(--bl-accent-d);font-weight:700;}
.pmportal .pp-refresh.stale i{color:var(--bl-accent);}

.pmportal .bl-tablewrap{background:var(--bl-box);border:1px solid var(--bl-line);border-radius:var(--bl-radius,6px);overflow-x:auto;}
.pmportal table.bl-list{width:100%;min-width:1150px;border-collapse:collapse;font-size:12.5px;}
.pmportal .bl-list th{background:var(--bl-box2);color:var(--bl-sub);font-weight:700;text-align:left;padding:8px 10px;
  border-bottom:1px solid var(--bl-line);white-space:nowrap;}
/* Sortable headers. The arrow is drawn only on the column in charge, so the
   row does not read as a set of thirteen competing carets. */
.pmportal .bl-list th[data-sort]{cursor:pointer;user-select:none;}
.pmportal .bl-list th[data-sort]:hover{color:var(--bl-accent-d);}
.pmportal .bl-list th[data-sort] .ar{margin-left:5px;opacity:0;}
.pmportal .bl-list th[data-sort]:hover .ar{opacity:.45;}
.pmportal .bl-list th.sorted{color:var(--bl-accent-d);}
.pmportal .bl-list th.sorted .ar{opacity:1;}
.pmportal .bl-list td{padding:8px 10px;border-bottom:1px solid var(--bl-line2,#edf1f5);vertical-align:middle;}
.pmportal .bl-list tbody tr{cursor:pointer;}
.pmportal .bl-list tbody tr:hover{background:var(--bl-hover);}
.pmportal .bl-list .sub{font-weight:600;}
/* Every other column holds a short value that does not wrap, so the auto
   layout used to hand the subject whatever was left: 90px at 1440px wide,
   up to five lines per subject. It gets a floor instead, paid for by letting
   the due date and the update time break between their two halves - the
   progress bar already makes a row two lines tall, so that costs no height. */
.pmportal .bl-list td.sub{min-width:180px;}
.pmportal .bl-list .bl-due{white-space:normal;}
.pmportal .bl-due-part{white-space:nowrap;}
.pmportal .bl-list td.up{color:var(--bl-faint);}
.pmportal .bl-list .parent{color:#93a1ae;font-size:11px;font-weight:700;margin-right:5px;}
.pmportal .bl-tablewrap .cnt-line{padding:8px 12px;color:var(--bl-sub);font-size:12px;}
.pmportal .bl-pager .rg{white-space:nowrap;}

/* ---- related issues ---- */
.pmportal .bl-childsum{margin-left:8px;font-size:11px;color:var(--bl-sub);font-weight:normal;}
.pmportal .bl-related .v .tx{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.pmportal .pp-rel-del{flex-shrink:0;color:var(--bl-faint);}
.pmportal .pp-rel-del:hover{color:var(--bl-red);}
.pmportal .bl-relempty{color:var(--bl-faint);font-size:12px;}
.pmportal .bl-reladd{display:flex;gap:6px;align-items:flex-start;}
.pmportal .bl-reladd select{flex-shrink:0;max-width:38%;}
.pmportal .bl-reladd #pp-rel-pick{flex:1;min-width:0;}
.pmportal .bl-reladd #pp-rel-pick .pp-lkp-box{min-height:30px;}

/* ---- burndown ---- */
.pmportal .bl-burn{display:grid;grid-template-columns:1fr 250px;gap:14px;align-items:start;}
.pmportal .bl-burn svg text{font-size:11px;fill:#7a8894;}
.pmportal .bl-burn .axis{stroke:#c9d3db;stroke-width:1;}
.pmportal .bl-burn .grid{stroke:#edf1f5;stroke-width:1;}
.pmportal .bl-burnside .tile{border:1px solid var(--bl-line);border-radius:var(--bl-radius,6px);padding:9px 12px;margin-bottom:8px;}
.pmportal .bl-burnside .tile .n{font-size:20px;font-weight:800;}
.pmportal .bl-burnside .tile .l{font-size:11px;color:var(--bl-sub);}
.pmportal .bl-burnside .warn{border-left:4px solid var(--bl-red);background:#fdf3f2;}
.pmportal .bl-burnside .warn .n{color:var(--bl-red);font-size:13px;line-height:1.5;}
.pmportal .bl-burnside .ok{border-left:4px solid var(--bl-accent);background:color-mix(in srgb, var(--bl-accent) 7%, var(--bl-box));}
.pmportal .bl-burnside .ok .n{color:var(--bl-accent-d);font-size:13px;line-height:1.5;}

/* ---- rendered markdown: description, wiki page, comment, preview ----
   Everything long-form in here is written in the same notation and so is
   read back through the same renderer; this is what that renderer's output
   looks like, once, instead of once per screen. */
.pmportal .bl-md{line-height:1.7;overflow-wrap:break-word;}
.pmportal .bl-md > *:first-child{margin-top:0;}
.pmportal .bl-md h1,.pmportal .bl-md h2,.pmportal .bl-md h3,
.pmportal .bl-md h4,.pmportal .bl-md h5,.pmportal .bl-md h6{font-weight:700;line-height:1.35;margin:18px 0 8px;}
.pmportal .bl-md h1{font-size:19px;border-bottom:2px solid var(--bl-line);padding-bottom:6px;}
.pmportal .bl-md h2{font-size:16px;border-bottom:1px solid var(--bl-line);padding-bottom:5px;}
.pmportal .bl-md h3{font-size:14.5px;border-left:3px solid var(--bl-accent);padding-left:8px;}
.pmportal .bl-md h4{font-size:13.5px;}
.pmportal .bl-md h5,.pmportal .bl-md h6{font-size:13px;color:var(--bl-sub);}
.pmportal .bl-md ul,.pmportal .bl-md ol{margin:6px 0 6px 22px;padding:0;}
.pmportal .bl-md li{margin:2px 0;}
/* A checklist keeps the box where the bullet was, so the lines still line up
   with the ordinary items above and below it. */
.pmportal .bl-md li.task{list-style:none;margin-left:-20px;}
.pmportal .bl-md li.task input{margin-right:6px;vertical-align:-1px;}
.pmportal .bl-md blockquote{margin:6px 0;padding:2px 0 2px 10px;border-left:3px solid var(--bl-line);color:var(--bl-sub);}
.pmportal .bl-md hr{border:0;border-top:1px solid var(--bl-line);margin:14px 0;}
.pmportal .bl-md code{background:var(--bl-hover);border:1px solid var(--bl-line);border-radius:3px;
  padding:0 4px;font-size:12px;}
.pmportal .bl-md pre{background:var(--bl-hover);border:1px solid var(--bl-line);border-radius:5px;
  padding:8px 10px;margin:6px 0;overflow-x:auto;white-space:pre;font-size:12px;}
.pmportal .bl-md pre code{background:transparent;border:0;padding:0;}
/* display:block is what lets a wide table scroll inside the column instead
   of pushing the whole card sideways. */
.pmportal .bl-md table{display:block;width:max-content;max-width:100%;overflow-x:auto;
  border-collapse:collapse;margin:8px 0;}
.pmportal .bl-md th,.pmportal .bl-md td{border:1px solid var(--bl-line);padding:5px 12px;
  font-size:12.5px;text-align:left;vertical-align:top;}
.pmportal .bl-md th{background:var(--bl-box2);font-weight:700;}
.pmportal .bl-md img{max-width:100%;}
.pmportal .bl-md .bl-cimg{display:inline-block;margin:4px 6px 4px 0;}
.pmportal .bl-md .bl-cimg img{max-height:340px;border:1px solid var(--bl-line);border-radius:5px;display:block;}

/* ---- issue detail ---- */
/* One column, one scroller - the window's. Everything the issue is made of
   reads top to bottom, and the composer is not part of that reading: it is
   pinned to the foot of the screen and unfolds only when it is wanted. */
.pmportal .bl-detail{display:block;}
.pmportal .bl-dhead{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:4px;}
.pmportal .bl-dtitle{font-size:20px;font-weight:800;line-height:1.3;margin:6px 0 10px;}
.pmportal .bl-dback{color:var(--bl-sub);text-decoration:none;font-weight:600;}
/* The shared row: a label column and a value column, parted by a dashed rule
   under each row. Every box on this screen that lists things uses it, so the
   labels line up from one box to the next instead of each box inventing its
   own left edge. */
.pmportal .bl-rows > .row,
.pmportal .bl-props .row{display:flex;gap:8px;padding:7px 0;border-bottom:1px dashed var(--bl-line2,#e7edf2);align-items:center;}
.pmportal .bl-rows > .row:last-child,
.pmportal .bl-props .row:last-child{border-bottom:0;}
.pmportal .bl-rows > .row > .k,
.pmportal .bl-props .k{width:110px;flex:none;color:var(--bl-sub);font-size:12px;}
.pmportal .bl-rows > .row > .v,
.pmportal .bl-props .v{flex:1;min-width:0;}
/* The value cell of a list box carries several things side by side: the link
   that names the thing, then what is known about it. */
.pmportal .bl-children .v,
.pmportal .bl-related .v{display:flex;align-items:center;gap:8px;}
.pmportal .bl-children .v > .bl-issuelink{margin-right:auto;min-width:0;overflow:hidden;
    text-overflow:ellipsis;white-space:nowrap;}
.pmportal .bl-tl .ent{display:flex;gap:10px;padding:10px 0;border-top:1px solid var(--bl-line2,#edf1f5);}
.pmportal .bl-tl .ent:first-child{border-top:0;}
.pmportal .bl-tl .body{flex:1;min-width:0;}
.pmportal .bl-tl .head{display:flex;align-items:center;gap:8px;flex-wrap:wrap;}
.pmportal .bl-tl .who{font-weight:700;}
.pmportal .bl-tl .tm{color:var(--bl-faint);font-size:11px;}
.pmportal .bl-tl .txt{margin-top:5px;line-height:1.7;background:#fafbfc;border:1px solid var(--bl-line2,#eef2f5);border-radius:var(--bl-radius,6px);
  padding:9px 12px;overflow-wrap:break-word;}
.pmportal .bl-tl .ent.hl .txt{border-color:var(--bl-accent);box-shadow:0 0 0 3px color-mix(in srgb, var(--bl-accent) 20%, transparent);}
.pmportal .bl-tl .cmenu{position:relative;margin-left:auto;}
.pmportal .bl-tl .cmenu>.m{list-style:none;margin:0;padding:4px 0;position:absolute;right:0;top:18px;z-index:30;
  background:var(--bl-box);border:1px solid var(--bl-line);border-radius:var(--bl-radius,6px);box-shadow:0 6px 18px rgba(15,42,60,.14);min-width:150px;}
.pmportal .bl-tl .cmenu>.m[hidden]{display:none;}
.pmportal .bl-tl .cmenu>.m a{display:block;padding:6px 14px;font-size:12px;color:var(--bl-ink);white-space:nowrap;}
.pmportal .bl-tl .cmenu>.m a:hover{background:var(--bl-hover);}
.pmportal .bl-tl .cedit textarea{width:100%;min-height:74px;border:1px solid var(--bl-accent);border-radius:var(--bl-radius,6px);
  padding:8px 10px;font-size:13px;resize:vertical;outline:none;}
.pmportal .bl-tl .cedit .row{display:flex;gap:8px;margin-top:6px;}

/* ---- comment composer ---- */
/* ---- pinned composer dock ---- */
/* Fixed to the foot of the window rather than the end of the document: the
   issue is long, and a composer that has to be scrolled to is a composer that
   is not used. Collapsed it is one row; opened it grows upwards and its own
   body scrolls, so the sheet can never be taller than the screen. */
/* ISS00336: the issue's own line, pinned while a long issue is read. Only
   shown once the title is out of sight, so it never repeats what is on
   screen. Fixed rather than sticky: it takes no room until it is needed. */
.pmportal .pp-dbar{position:fixed;top:var(--pp-stick-top,0px);left:var(--pp-dbar-l,0);width:var(--pp-dbar-w,100%);z-index:930;
  display:flex;align-items:center;gap:10px;height:44px;padding:0 14px;box-sizing:border-box;
  background:var(--bl-box);border-bottom:1px solid var(--bl-line);box-shadow:0 2px 6px rgba(0,0,0,.06);font-size:13px;}
.pmportal .pp-dbar[hidden]{display:none;}
/* A phone has no height to spare; the comment dock is enough there. */
@media (max-width:760px){.pmportal .pp-dbar{display:none !important;}}
.pmportal .pp-dbar .bl-key{flex:none;}
.pmportal .pp-dbar .t{flex:1;min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-weight:700;cursor:pointer;color:var(--bl-ink);}
/* A jump to a comment or a heading must not land under the pinned line
   (wide screens only - a phone has no pinned line). */
@media (min-width:761px){.pmportal #pp-issue [id]{scroll-margin-top:calc(var(--pp-stick-top,0px) + 56px);}}
.pmportal .pp-dbar .t:focus-visible{outline:2px solid var(--bl-accent);outline-offset:2px;border-radius:3px;}
@media print{
  .pmportal .pp-dbar{display:none !important;}
  .pmportal .bl-wiki > .bl-card-box.pp-pinned{position:static !important;max-height:none !important;width:auto !important;}
}
.pmportal .bl-cmtdock{position:fixed;left:var(--pp-dock-l,0);width:var(--pp-dock-w,100%);bottom:0;z-index:940;
  background:var(--bl-box);border-top:1px solid var(--bl-line);box-shadow:0 -6px 18px rgba(15,42,60,.10);
  display:flex;flex-direction:column;max-height:64vh;}
.pmportal .bl-cmtdock[hidden]{display:none;}
.pmportal .bl-dockbar{display:flex;align-items:center;gap:10px;width:100%;background:transparent;border:0;
  cursor:pointer;padding:9px 16px;font-size:13px;color:var(--bl-sub);text-align:left;}
.pmportal .bl-dockbar:hover{background:var(--bl-hover);}
.pmportal .bl-dockbar .sp{flex:1;}
.pmportal .bl-dockbar .c{transition:transform .15s;}
.pmportal .bl-cmtdock.on .bl-dockbar .c{transform:rotate(180deg);}
.pmportal .bl-dockstate{font-size:11px;color:var(--bl-faint);}
.pmportal .bl-dockav .bl-av{margin:0;}
.pmportal .bl-docksheet{display:flex;flex-direction:column;min-height:0;border-top:1px solid var(--bl-line);}
.pmportal .bl-docksheet[hidden]{display:none;}
.pmportal .bl-dockhead{display:flex;align-items:center;gap:8px;padding:8px 16px;font-weight:700;font-size:13px;
  background:var(--bl-box2);border-bottom:1px solid var(--bl-line);}
.pmportal .bl-dockhead .fa{color:var(--bl-sub);margin-right:6px;}
.pmportal .bl-dockclose{margin-left:auto;background:transparent;border:0;color:var(--bl-sub);cursor:pointer;
  font-size:14px;padding:2px 8px;border-radius:var(--bl-radius-s,4px);}
.pmportal .bl-dockclose:hover{background:var(--bl-hover);color:var(--bl-ink);}
/* Two columns: what is being written on the left, what is being changed on
   the right. Started rather than stretched - a card that grows to match its
   neighbour is mostly empty space. */
.pmportal .bl-dockbody{flex:1 1 auto;min-height:0;overflow-y:auto;padding:12px 16px 0;
  display:flex;gap:12px;align-items:flex-start;flex-wrap:wrap;}
.pmportal .bl-dockbody .bl-card-box{margin-bottom:12px;}
.pmportal .bl-dockmain{flex:1 1 380px;min-width:0;display:flex;flex-direction:column;}
.pmportal .bl-dockside{flex:1 1 320px;min-width:0;display:flex;flex-direction:column;}
.pmportal .bl-dockfoot{display:flex;align-items:center;gap:10px;padding:10px 16px;border-top:1px solid var(--bl-line);
  background:var(--bl-box2);}
.pmportal .bl-dockmsg{flex:1;font-size:12px;color:var(--bl-sub);}
.pmportal .bl-dockmsg.err{color:#c0392b;}
.pmportal .bl-dockbody .bl-cmtform{margin-top:0;}
/* Room under the last box so the dock never covers the end of the issue. */
.pmportal #pp-issue{padding-bottom:var(--pp-dock-h,0px);}
/* The admin footer and the wrapper that holds this page are both flex items
   of .wrapper, and z-index applies to a flex item whatever its position is:
   the footer's 820 beats the 800 on .content-wrapper, so at the foot of a
   long issue the footer painted straight over the dock and the composer
   disappeared exactly where it is most wanted. The dock cannot be lifted out
   of that box without leaving the portal's own styles behind, so the footer
   is asked to stop short of it instead - margin, not padding, because the
   footer paints a background over anything inside its own box. */
body.pp-dock-on .main-footer{margin-bottom:var(--pp-dock-h,0px);}
/* ---- hovercard: who that @ is, what that #123 is -----------------------
   One panel for the whole page, moved to wherever the pointer stopped. It
   lives on <body> rather than inside the timeline so no ancestor's overflow
   or transform can clip it, and it carries .pmportal itself so the portal's
   own variables still reach it there. */
.pmportal.pp-hc{position:absolute;z-index:1100;width:300px;max-width:92vw;
  background:var(--bl-box);color:var(--bl-ink);border:1px solid var(--bl-line);border-radius:var(--bl-radius,6px);
  box-shadow:0 8px 26px rgba(15,42,60,.22);padding:12px 14px;font-size:12px;line-height:1.6;
  opacity:0;transform:translateY(4px);transition:opacity .12s,transform .12s;pointer-events:none;}
.pmportal.pp-hc.on{opacity:1;transform:translateY(0);pointer-events:auto;}
.pmportal.pp-hc[hidden]{display:none;}
.pmportal .pp-hc-top{display:flex;align-items:flex-start;gap:10px;}
.pmportal .pp-hc-top .bl-av{flex:0 0 auto;margin:0;}
.pmportal .pp-hc-nm{font-weight:700;font-size:13px;word-break:break-word;}
.pmportal .pp-hc-sub{color:var(--bl-sub);font-size:11px;word-break:break-all;}
.pmportal .pp-hc-rows{margin-top:10px;border-top:1px dotted var(--bl-line);padding-top:8px;}
.pmportal .pp-hc-rows .r{display:flex;gap:10px;padding:3px 0;border-bottom:1px dotted var(--bl-line);}
.pmportal .pp-hc-rows .r:last-child{border-bottom:0;}
.pmportal .pp-hc-rows .k{flex:0 0 92px;color:var(--bl-sub);}
.pmportal .pp-hc-rows .v{flex:1;min-width:0;word-break:break-word;}
.pmportal .pp-hc-msg{color:var(--bl-sub);}
.pmportal .pp-hc-key{font-weight:700;color:#2b6cb0;font-size:11px;}
.pmportal .bl-qe-in{width:100%;height:30px;border:1px solid var(--bl-line);border-radius:var(--bl-radius-s,4px);padding:4px 8px;
  font-size:13px;line-height:20px;background:var(--bl-box);color:var(--bl-ink);box-sizing:border-box;
  font-variant-numeric:tabular-nums;transition:border-color .12s,box-shadow .12s;}
.pmportal .bl-qe-in:hover{border-color:color-mix(in srgb, var(--bl-accent) 45%, var(--bl-line));}
.pmportal .bl-qe-in:focus{border-color:var(--bl-accent);outline:none;box-shadow:var(--bl-focus,0 0 0 3px rgba(76,174,147,.18));}
.pmportal .bl-qe-in:disabled{background:var(--bl-box2);color:var(--bl-faint);}
.pmportal select.bl-qe-in{padding-right:4px;text-overflow:ellipsis;cursor:pointer;}
.pmportal .bl-qe-in::placeholder{color:var(--bl-faint);opacity:.8;}
.pmportal .bl-qe-chips{display:flex;flex-wrap:wrap;gap:5px;}
.pmportal .bl-qe-num{text-align:right;}
/* ---- dock fields: each as wide as what it holds (snoro 2026-11-19) ----
   The fields used to be label | value rows, every value stretched across the
   card: a ten-character date in a thousand-pixel box, and a card that had to
   be scrolled. Now each is a label over its widget at a width of its own and
   they wrap like words - three pickers on a line, the two chip lists a line
   each, the dates and the hours side by side on the last. */
.pmportal .bl-qe{display:flex;flex-wrap:wrap;align-items:flex-start;gap:10px 12px;}
.pmportal .bl-qe > .row{display:flex;flex-direction:column;gap:3px;min-width:0;flex:0 0 auto;}
.pmportal .bl-qe > .row > .k{display:flex;align-items:center;gap:5px;min-height:17px;font-size:11.5px;line-height:1.45;
  font-weight:600;color:var(--bl-sub);white-space:nowrap;}
.pmportal .bl-qe > .row > .k .u{font-weight:400;color:var(--bl-faint);}
.pmportal .bl-qe > .row > .v{display:block;min-width:0;}
.pmportal .bl-qe > .row.w-st{flex:1 1 190px;max-width:300px;}
.pmportal .bl-qe > .row.w-sel{flex:1 1 140px;max-width:260px;}
.pmportal .bl-qe > .row.w-full{flex:1 1 100%;}
.pmportal .bl-qe > .row.w-date{width:120px;}
.pmportal .bl-qe > .row.w-time{width:98px;}
.pmportal .bl-qe > .row.w-num{width:72px;}
.pmportal .bl-qe .bl-qe-chips .em{font-size:12px;line-height:24px;}
/* how many chips are switched on */
.pmportal .bl-qe-n{display:inline-block;min-width:16px;padding:0 5px;border-radius:9px;background:var(--bl-accent);color:#fff;
  font-size:10.5px;font-weight:700;line-height:16px;text-align:center;font-variant-numeric:tabular-nums;}
.pmportal .bl-qe-n[hidden]{display:none;}
.pmportal .bl-boxhead .bl-qe-n{margin-left:7px;vertical-align:1px;}
/* ---- dock chips: who is picked reads at a glance ---- */
.pmportal .bl-dockbody .bl-chip{gap:5px;padding:2px 10px;font-size:12px;font-weight:600;line-height:18px;border-radius:13px;
  background:var(--bl-box);border:1px solid var(--bl-line);color:var(--bl-sub);white-space:nowrap;user-select:none;
  transition:background .12s,border-color .12s,color .12s;}
.pmportal .bl-dockbody .bl-chip.has-av{padding-left:3px;}
.pmportal .bl-dockbody .bl-chip .bl-av{width:18px;height:18px;margin:0;font-size:9px;transition:opacity .12s,filter .12s;}
.pmportal .bl-dockbody .bl-chip.off .bl-av{opacity:.5;filter:grayscale(.7);}
.pmportal .bl-dockbody .bl-chip:hover{background:var(--bl-hover);border-color:color-mix(in srgb, var(--bl-accent) 55%, var(--bl-line));color:var(--bl-ink);}
.pmportal .bl-dockbody .bl-chip:hover .bl-av{opacity:1;filter:none;}
.pmportal .bl-dockbody .bl-chip:not(.off){background:color-mix(in srgb, var(--bl-accent) 14%, var(--bl-box));
  border-color:var(--bl-accent);color:var(--bl-accent-d,var(--bl-accent-ink,#2c6e5b));}
.pmportal .bl-dockbody .bl-chip:focus-visible{outline:2px solid var(--bl-accent);outline-offset:1px;}
.pmportal #pp-notify-chips{display:flex;flex-wrap:wrap;gap:5px;}
/* ---- dock cards: quieter heads, the room goes to the fields ---- */
.pmportal .bl-dockbody .bl-card-box>.bl-boxhead{display:flex;align-items:center;flex-wrap:wrap;gap:2px 0;padding:7px 12px;font-size:13px;}
.pmportal .bl-dockbody .bl-card-box>.bl-boxbody{padding:10px 12px 12px;}
.pmportal .bl-dockbody .bl-boxhead .pp-notify-mail{margin:0 0 0 auto;padding-left:12px;white-space:nowrap;}
.pmportal .bl-dockbody .bl-boxhead .pp-notify-mail input{margin:0 2px 0 0;}
.pmportal .bl-dockbody .bl-boxhead .pp-notify-mail .fa{margin-right:0;}
/* Two ticks on the head (確認を依頼する, メールでも知らせる): the first one takes
   the push to the right, the second sits beside it. */
.pmportal .bl-dockbody .bl-boxhead .pp-notify-rv + .pp-notify-mail{margin-left:0;}
/* With 確認を依頼する ticked the picked people are being asked, not only told:
   each picked chip carries the tick, so what the post will do is on screen. */
.pmportal #pp-notify-chips.rv .bl-chip:not(.off)::after{content:"\f046";font-family:FontAwesome;font-size:12px;margin-left:2px;}
/* The comment and who hears of it stand side by side once there is room for
   both; the huge grow on the comment means the people card only ever takes
   what it asked for there, and the whole line when it wraps under. */
.pmportal .bl-dockmain{flex-direction:row;flex-wrap:wrap;align-items:flex-start;gap:0 12px;}
.pmportal .bl-dockmain > .bl-card-box{flex:999 1 560px;min-width:0;}
.pmportal .bl-dockmain > .bl-card-box + .bl-card-box{flex:1 1 380px;}
/* The fields need about 700px to sit in three lines and are given no more:
   on a wide screen the rest is the comment's. */
.pmportal .bl-dockside{flex:3 1 380px;max-width:720px;}
@media (max-width:480px){
  .pmportal .bl-qe{gap:10px 8px;}
  .pmportal .bl-qe > .row.w-st,.pmportal .bl-qe > .row.w-sel{max-width:none;}
  .pmportal .bl-qe > .row.w-date{flex:1 1 120px;}
  .pmportal .bl-qe > .row.w-time{width:98px;}
  .pmportal .bl-qe > .row.w-num{width:58px;}
  .pmportal .bl-qe > .row.w-num .bl-qe-in{padding:4px 4px 4px 6px;}
}
@media (max-width:1000px){
  .pmportal .bl-dockbody{display:block;}
  .pmportal .bl-dockmain{display:block;}
  .pmportal .bl-dockside{display:block;max-width:none;}
}

/* ---- type-ahead: @ people, # issues, ~ files ---- */
/* Mounted on <body> so it is never clipped by the box the editor sits in,
   which means it needs its own copy of the palette. */
.pp-ta{--bl-box:#fff;--bl-line:#dfe5ea;--bl-ink:#2a3b47;--bl-sub:#6b7f8e;--bl-faint:#93a4b1;
  --bl-hover:#f2f6f8;--bl-accent:var(--exm-ui-accent,#4caf93);--bl-accent-d:var(--bl-accent-ink,#2c6e5b);
  position:absolute;z-index:11000;min-width:240px;max-width:420px;background:var(--bl-box);
  border:1px solid var(--bl-line);border-radius:var(--bl-radius,6px);box-shadow:0 8px 24px rgba(15,42,60,.18);overflow:hidden;
  font-size:12px;color:var(--bl-ink);}
.pp-ta[hidden]{display:none;}
.pp-ta .hd{padding:4px 10px;background:#f4f7f9;border-bottom:1px solid var(--bl-line);font-size:11px;
  color:var(--bl-sub);font-weight:700;}
.pp-ta .ls{max-height:240px;overflow-y:auto;}
.pp-ta .it{display:flex;align-items:center;gap:8px;width:100%;background:transparent;border:0;cursor:pointer;
  padding:6px 10px;text-align:left;color:var(--bl-ink);}
.pp-ta .it:hover,.pp-ta .it.on{background:color-mix(in srgb, var(--bl-accent) 14%, var(--bl-box));}
.pp-ta .it .n{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.pp-ta .it .s{color:var(--bl-faint);font-size:11px;white-space:nowrap;}
.pp-ta .it .bl-key{flex:none;font-weight:700;color:var(--bl-sub);white-space:nowrap;}
.pp-ta .em{padding:8px 10px;color:var(--bl-faint);}
.pp-ta .ft{padding:4px 10px;border-top:1px solid var(--bl-line);color:var(--bl-faint);font-size:10px;}
.pp-ta .bl-av{width:20px;height:20px;line-height:20px;font-size:10px;border-radius:50%;display:inline-block;
  text-align:center;color:#fff;flex:none;}

.pmportal .bl-cmttools{display:flex;align-items:center;gap:2px;padding:5px 6px;border-bottom:1px solid var(--bl-line2,#eef2f5);flex-wrap:wrap;}
.pmportal .bl-cmttools .sp{flex:1;}
.pmportal .bl-cmttools .sep{width:1px;height:16px;background:var(--bl-line);margin:0 5px;}
.pmportal .bl-cmttools button{background:transparent;border:0;border-radius:var(--bl-radius-s,4px);color:var(--bl-sub);cursor:pointer;
  padding:4px 8px;font-size:12px;line-height:1;}
.pmportal .bl-cmttools button:hover{background:var(--bl-hover);color:var(--bl-ink);}
.pmportal .bl-cmttools button.on{background:color-mix(in srgb, var(--bl-accent) 15%, var(--bl-box));color:var(--bl-accent-d);}
.pmportal .bl-cmtprev{padding:9px 12px;min-height:74px;}
.pmportal .bl-cmtprev .txt{margin-top:0;}
.pmportal .bl-cmthint{padding:0 12px 6px;font-size:11px;color:var(--bl-faint);}
.pmportal .bl-cmtform.drag{border-color:var(--bl-accent);background:color-mix(in srgb, var(--bl-accent) 6%, var(--bl-box));}
.pmportal .bl-cmtform.busy{opacity:.75;}

/* ---- timeline filter / issue nav / attachments ---- */
.pmportal .bl-tlfilter{display:inline-flex;border:1px solid var(--bl-line);border-radius:14px;overflow:hidden;}
.pmportal .bl-tlfilter button{background:var(--bl-box);border:0;color:var(--bl-sub);cursor:pointer;
  font-size:11px;font-weight:600;padding:3px 12px;}
.pmportal .bl-tlfilter button.on{background:var(--bl-accent);color:#fff;}
.pmportal .bl-tlexpand{background:transparent;border:0;color:var(--bl-sub);cursor:pointer;font-size:11px;font-weight:600;padding:3px 8px;}
.pmportal .bl-tlexpand:hover{color:var(--bl-accent-d);}
.pmportal .bl-tl .txt.clip{max-height:260px;overflow:hidden;}
.pmportal .bl-tl .more{margin-top:4px;}
.pmportal .bl-tl .more a{font-size:11px;color:var(--bl-accent-d);font-weight:600;text-decoration:none;}
.pmportal .bl-emoji{display:flex;flex-wrap:wrap;gap:2px;padding:6px 8px;border-bottom:1px solid var(--bl-line2,#eef2f5);}
.pmportal .bl-emoji[hidden]{display:none;}
.pmportal .bl-emoji button{background:transparent;border:0;border-radius:var(--bl-radius-s,4px);cursor:pointer;font-size:17px;line-height:1;padding:4px 5px;}
.pmportal .bl-emoji button:hover{background:var(--bl-hover);}
.pmportal .bl-inav{display:inline-flex;align-items:center;gap:4px;color:var(--bl-faint);font-size:11px;}
.pmportal .bl-inav a{color:var(--bl-sub);text-decoration:none;padding:2px 6px;border:1px solid var(--bl-line);border-radius:var(--bl-radius-s,4px);}
.pmportal .bl-inav a.off{opacity:.4;pointer-events:none;}
/* An attachment keeps its preview, but on the shared row: the preview fills
   the label cell, the name and who brought it fill the value cell. */
.pmportal .bl-dfiles .th{display:block;width:72px;text-decoration:none;}
.pmportal .bl-dfiles .th img{width:72px;height:54px;object-fit:cover;border:1px solid var(--bl-line);border-radius:var(--bl-radius-s,4px);display:block;}
.pmportal .bl-dfiles .th .ic{width:72px;height:54px;border:1px solid var(--bl-line);border-radius:var(--bl-radius-s,4px);display:flex;
  align-items:center;justify-content:center;font-size:20px;color:var(--bl-faint);background:var(--bl-hover);}
.pmportal .bl-dfiles .v{display:flex;flex-direction:column;gap:3px;}
.pmportal .bl-dfiles .v .n{font-size:12.5px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.pmportal .bl-dfiles .v .mt{font-size:11px;color:var(--bl-faint);}

/* ---- wiki / files ---- */
.pmportal .bl-tl .chg{margin-top:5px;font-size:12px;color:var(--bl-sub);background:#f4f7f9;border-radius:var(--bl-radius,6px);padding:7px 12px;}
.pmportal .bl-tl .chg .arr{color:#98a5b1;margin:0 6px;}
.pmportal .bl-tl .act{margin-top:5px;font-size:11px;color:var(--bl-faint);display:flex;gap:14px;}
.pmportal .bl-tl .act a{color:var(--bl-faint);text-decoration:none;}
.pmportal .bl-tl .act a:hover{color:var(--bl-accent-d);}
.pmportal .bl-tl .act a.on{color:#f2b01e;}
.pmportal .bl-file{display:inline-flex;align-items:center;gap:6px;background:var(--bl-hover);border:1px solid var(--bl-line);
  border-radius:var(--bl-radius-s,4px);padding:3px 10px;font-size:12px;margin-top:6px;text-decoration:none;color:var(--bl-ink);}
.pmportal .pp-officeview{display:inline-flex;align-items:center;gap:4px;margin-left:6px;font-size:12px;color:var(--bl-accent-d);text-decoration:none;white-space:nowrap;}
.pmportal .pp-officeview:hover{text-decoration:underline;}
/* ISS00416: the Office view dialog sits on <body>, outside .pmportal */
.pp-officebox{position:fixed;inset:0;z-index:10040;background:rgba(20,28,36,.55);display:flex;align-items:center;justify-content:center;padding:16px;box-sizing:border-box;}
.pp-officebox-in{display:flex;flex-direction:column;width:min(1280px,100%);height:100%;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 10px 40px rgba(0,0,0,.35);}
.pp-officebox-hd{display:flex;align-items:center;gap:10px;padding:8px 12px;border-bottom:1px solid #dfe5ea;font-size:13px;color:#24323f;}
.pp-officebox-hd .nm{flex:1;min-width:0;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.pp-officebox-tab{font-size:12px;color:var(--bl-accent-d,var(--exm-ui-accent-strong,#3d9a80));text-decoration:none;white-space:nowrap;}
.pp-officebox-x{border:0;background:none;font-size:22px;line-height:1;cursor:pointer;color:#6b7a88;padding:0 4px;}
.pp-officebox iframe{flex:1;width:100%;border:0;display:block;background:#fff;}
@media (prefers-color-scheme: dark){
  .pp-officebox-in{background:#1e2329;}
  .pp-officebox-hd{border-color:#333b44;color:#e3e8ed;}
}
.pmportal .bl-cmtform{border:2px solid var(--bl-line);border-radius:8px;margin-top:12px;background:var(--bl-box);}
.pmportal .bl-cmtform:focus-within{border-color:var(--bl-accent);}
.pmportal .bl-cmtform textarea{border:0;width:100%;padding:10px 12px;min-height:74px;resize:vertical;outline:none;
  border-radius:8px 8px 0 0;font-size:13px;box-shadow:none;}
.pmportal .bl-notifyrow{display:flex;align-items:center;gap:8px;padding:8px 10px;border-top:1px solid var(--bl-line2,#eef2f5);
  font-size:12px;color:var(--bl-sub);flex-wrap:wrap;}
.pmportal .bl-chip{display:inline-flex;align-items:center;gap:5px;background:color-mix(in srgb, var(--bl-accent) 13%, var(--bl-box));border:1px solid var(--bl-accent-line,#bfe3d6);
  color:var(--bl-accent-ink,#2c6e5b);border-radius:14px;padding:1px 9px;font-weight:600;cursor:pointer;}
.pmportal .bl-chip.off{background:#f4f6f8;border-color:#dfe5ea;color:var(--bl-faint);}
.pmportal .bl-notifyrow select{border:1px solid var(--bl-line);border-radius:var(--bl-radius-s,4px);padding:4px 6px;font-size:12px;}
.pmportal .bl-btn-main{background:var(--bl-accent);color:#fff;border:0;border-radius:var(--bl-radius-s,4px);padding:7px 18px;
  font-weight:700;cursor:pointer;}
.pmportal .bl-btn-main[disabled]{opacity:.6;}
.pmportal .bl-btn-sub{background:var(--bl-box);border:1px solid var(--bl-line);border-radius:var(--bl-radius-s,4px);padding:6px 12px;
  color:var(--bl-sub);cursor:pointer;text-decoration:none;display:inline-block;}
.pmportal .bl-btn-sub.on{color:var(--bl-accent-ink,#2c6e5b);border-color:var(--bl-accent-line,#bfe3d6);background:color-mix(in srgb, var(--bl-accent) 13%, var(--bl-box));}

/* ---- wiki / files ---- */
.pmportal .bl-wiki{display:grid;grid-template-columns:260px 1fr;gap:12px;align-items:start;}
/* ISS00336: the page list stays beside a long article (PC only). Pinned
   from script (ppWtreeFit), not with position:sticky - AdminLTE's .wrapper
   has overflow:hidden, and sticky does nothing under such a box. The
   article keeps its column while the list is out of the grid's flow. */
.pmportal .bl-wiki.pp-wtree-pinned > .bl-card-box:nth-child(2){grid-column:2;}
.pmportal .bl-wiki > .bl-card-box.pp-pinned{position:fixed;z-index:20;overflow:hidden;margin:0;}
.pmportal .bl-wiki > .bl-card-box.pp-pinned .bl-wtree{overflow:auto;}
.pmportal .bl-wtree .nd{padding:5px 8px;border-radius:var(--bl-radius-s,4px);cursor:pointer;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.pmportal .bl-wtree .nd:hover{background:var(--bl-hover);}
.pmportal .bl-wtree .nd.on{background:color-mix(in srgb, var(--bl-accent) 13%, var(--bl-box));color:var(--bl-accent-d);font-weight:700;}
.pmportal .bl-wtree .nd .fa{color:var(--bl-faint);margin-right:6px;width:14px;text-align:center;}
/* One step per level. Indenting with padding rather than margin keeps the
   hover and selection background spanning the full width of the tree, which a
   margin would eat into a little more on every level. */
.pmportal .bl-wtree .lv1{padding-left:26px;}
.pmportal .bl-wtree .lv2{padding-left:44px;}
.pmportal .bl-wtree .lv3{padding-left:62px;}
.pmportal .bl-wtree .lv4{padding-left:80px;}
/* A page whose parent is gone is shown at the root, marked so it is obvious
   the tree position is a fallback and not where someone filed it. */
.pmportal .bl-wtree .nd.orphan .fa{color:var(--bl-amber);}
.pmportal .bl-wmeta{color:var(--bl-sub);font-size:12px;display:flex;gap:16px;flex-wrap:wrap;margin:2px 0 6px;}
.pmportal .bl-files table{width:100%;border-collapse:collapse;}
.pmportal .bl-files th{background:var(--bl-box2);text-align:left;padding:7px 12px;font-size:12px;color:var(--bl-sub);
  border-bottom:1px solid var(--bl-line);}
.pmportal .bl-files td{padding:8px 12px;border-bottom:1px solid var(--bl-line2,#edf1f5);}
.pmportal .bl-drop{border:2px dashed #c8d4dd;border-radius:8px;text-align:center;color:var(--bl-faint);
  padding:16px;margin-bottom:10px;}
.pmportal .bl-drop.hover{border-color:var(--bl-accent);color:var(--bl-accent-d);background:color-mix(in srgb, var(--bl-accent) 7%, var(--bl-box));}

/* ---- toast + screens ---- */
.pmportal .bl-screen{display:none;}
.pmportal .bl-screen.on{display:block;}
#pp-toast{position:fixed;right:18px;bottom:18px;z-index:10050;background:#2f3e4d;color:#fff;border-radius:var(--bl-radius,6px);
  padding:10px 16px;font-size:13px;box-shadow:0 4px 14px rgba(0,0,0,.25);display:none;}
#pp-toast.danger{background:#c0392b;}
@media (max-width:1200px){.pmportal .bl-home,.pmportal .bl-burn{grid-template-columns:1fr;}}

/* ---- narrow screens ----
   The project header and the tab bar are one card, so they wrap together.
   On a phone the header used to keep every child on a single row: the name
   block was squeezed to about 60px and stacked one glyph per line, and the
   project-settings button was pushed clean off the right edge where nothing
   could reach it. Here the name block claims the whole first row next to the
   icon, which pushes the avatars and both buttons onto a row of their own. */
@media (max-width:760px){
  .pmportal .bl-projhead{flex-wrap:wrap;align-items:flex-start;gap:10px 12px;padding:12px 12px 10px;}
  .pmportal .bl-projicon{width:36px;height:36px;font-size:16px;border-radius:var(--bl-radius,6px);}
  /* 36px icon + 12px gap = the space it has to leave on the first row */
  .pmportal .bl-projinfo{flex:1 1 calc(100% - 48px);}
  .pmportal .bl-projname{font-size:16px;}
  .pmportal .bl-projmeta{margin-top:5px;line-height:1.7;}
  .pmportal .bl-projmeta i{margin-left:0;margin-right:4px;}
  .pmportal .bl-projmeta i:not(:first-child){margin-left:8px;}
  .pmportal .bl-projhead .bl-avatars{margin-left:0;}

  /* Tabs stay on their own rows; search and "add issue" each take a full row
     rather than being wedged into whatever width is left over. */
  .pmportal .bl-tabs{padding:0 8px 8px;}
  .pmportal .bl-tab{padding:9px 10px;font-size:13px;}
  .pmportal .bl-tabsearch{flex:1 1 100%;margin-left:0;margin-top:6px;}
  .pmportal .bl-tabsearch input{width:100%;box-sizing:border-box;}
  .pmportal .bl-addissue{flex:1 1 100%;margin-left:0;margin-top:6px;text-align:center;}
}

/* ---- in-portal form modal (framed admin form) ---- */
body.pp-fm-open{overflow:hidden;}
.pp-fmodal{position:fixed;inset:0;z-index:1080;}
.pp-fmodal[hidden],.pp-fmodal [hidden]{display:none !important;}
.pp-fmodal .pp-fm-back{position:absolute;inset:0;background:rgba(36,50,63,.45);}
.pp-fmodal .pp-fm-panel{position:absolute;top:0;right:0;bottom:0;width:min(1080px,94vw);background:var(--bl-box);
  box-shadow:-10px 0 30px rgba(20,30,40,.28);display:flex;flex-direction:column;animation:pp-fm-slide .16s ease;}
@keyframes pp-fm-slide{from{transform:translateX(28px);opacity:.4;}to{transform:none;opacity:1;}}
.pp-fmodal .pp-fm-head{display:flex;align-items:center;gap:10px;padding:11px 16px;border-bottom:1px solid var(--bl-line);
  background:var(--bl-box2);flex:none;}
.pp-fmodal .pp-fm-head .t{font-weight:700;font-size:15px;color:var(--bl-ink);}
.pp-fmodal .pp-fm-head .s{color:var(--bl-sub);font-size:12px;background:var(--bl-chip);border:1px solid var(--bl-line);border-radius:3px;
  padding:1px 8px;font-weight:700;}
.pp-fmodal .pp-fm-x{margin-left:auto;cursor:pointer;color:var(--bl-sub);font-size:16px;padding:4px 9px;border-radius:var(--bl-radius-s,4px);}
.pp-fmodal .pp-fm-x:hover{background:var(--bl-chip);color:var(--bl-ink);}

/* form-style picker in the modal header */
.pp-fmodal .pp-fm-style-wrap{position:relative;margin-left:auto;}
.pp-fmodal .pp-fm-style-btn{border:1px solid var(--bl-line);background:var(--bl-box2);color:var(--bl-sub);
  border-radius:var(--bl-radius-s,4px);padding:4px 10px;font-size:12px;font-weight:600;cursor:pointer;
  display:inline-flex;align-items:center;gap:6px;line-height:1.2;}
.pp-fmodal .pp-fm-style-btn:hover{background:var(--bl-chip);color:var(--bl-ink);}
.pp-fmodal .pp-fm-style-btn i{color:var(--bl-accent);}
.pp-fmodal .pp-fm-style-menu{position:absolute;right:0;top:calc(100% + 6px);z-index:10;
  background:var(--bl-box);border:1px solid var(--bl-line);border-radius:8px;
  box-shadow:0 12px 30px rgba(15,42,60,.16);padding:6px;width:280px;}
.pp-fmodal .pp-fm-style-item{display:flex;align-items:flex-start;gap:10px;padding:8px 10px;border-radius:var(--bl-radius,6px);color:var(--bl-ink);
  text-decoration:none;cursor:pointer;}
.pp-fmodal .pp-fm-style-item:hover{background:var(--bl-hover);}
.pp-fmodal .pp-fm-style-item.on{background:var(--bl-hover);}
.pp-fmodal .pp-fm-style-item .lbl{font-weight:700;font-size:13px;display:block;}
.pp-fmodal .pp-fm-style-item .desc{color:var(--bl-sub);font-size:11.5px;display:block;line-height:1.4;}
.pp-fmodal .pp-fm-style-item .fa-check{color:var(--bl-accent);margin-left:auto;align-self:center;}
.pp-fmodal .pp-fm-style-swatch{display:inline-block;width:26px;height:26px;border-radius:var(--bl-radius,6px);flex:none;
  margin-top:1px;border:1px solid var(--bl-line);box-shadow:inset 0 0 0 1px var(--bl-box);}
.pp-fmodal .pp-fm-style-swatch-card{background:linear-gradient(135deg,#fff 0%,var(--bl-box2) 100%);
  box-shadow:inset 0 -6px 0 var(--bl-chip);}
.pp-fmodal .pp-fm-style-swatch-sheet{background:var(--bl-box);
  border-bottom:2px solid var(--bl-accent);border-radius:0;}
.pp-fmodal .pp-fm-style-swatch-compact{background:
  linear-gradient(90deg,var(--bl-chip) 50%,var(--bl-box) 50%);
  border-radius:2px;}
.pp-fmodal .pp-fm-style-swatch-flow{background:linear-gradient(135deg,var(--bl-accent),var(--bl-accent-d));
  border:0;}
.pp-fmodal .pp-fm-confirm{display:flex;align-items:center;gap:10px;padding:8px 16px;background:#fdf3f2;
  border-bottom:1px solid #f0c7c3;color:#a94442;font-size:12px;flex:none;}
.pp-fmodal .pp-fm-confirm button{border:1px solid #d9b0ac;background:var(--bl-box);border-radius:var(--bl-radius-s,4px);padding:4px 12px;
  cursor:pointer;font-size:12px;}
.pp-fmodal .pp-fm-confirm button.yes{background:#d9534f;border-color:#d9534f;color:#fff;font-weight:700;}
.pp-fmodal .pp-fm-body{flex:1;position:relative;background:var(--bl-box);}
.pp-fmodal iframe{position:absolute;inset:0;width:100%;height:100%;border:0;}
.pp-fmodal .pp-fm-spin{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;
  color:var(--bl-sub);font-size:14px;background:var(--bl-box);}

/* ---- the pre-warmed hidden frame never covers the active one ---- */
.pp-fmodal iframe.pp-fm-warm{display:none;}

/* ------------------------------------------------------------------
 * Native edit modal: no iframe, form built client-side
 * from the JSON schema returned by /admin/lightapi/data/{t}/{id}/edit.
 *
 * Layout inspired by Backlog issue edit but goes further:
 *  - hero row: giant title input (auto-picked from the first non-readonly
 *    text-type column) so the primary field feels like a page heading;
 *  - two-column body: main (long text, richtext, files) + side (meta:
 *    select, date, user, boolean, number) in a subtly-tinted panel;
 *  - segmented pill selector for coloured selects with <=5 options
 *    (status / priority): one click, colour-coded, no dropdown;
 *  - sticky footer with visible ⌘⏎ / ESC hints and a "N changes"
 *    counter on the save button;
 *  - skeleton loading instead of a spinner so the layout resolves in
 *    place.
 * Palette is locked to the Backlog look regardless of the active
 * portal theme (Exment blue / Plum / Dark still get the calm white
 * surface + green accent inside the modal).
 * ------------------------------------------------------------------ */
.pp-nedit{
  display:flex;flex-direction:column;background:var(--bl-box);
  border:1px solid var(--bl-line);border-radius:var(--bl-radius,6px);overflow:hidden;
  --bl-accent:var(--exm-ui-accent,#4caf93);--bl-accent-d:var(--exm-ui-accent-strong,#3d9a80);
  --bl-ink:#24323f;--bl-sub:#6b7a88;--bl-faint:#8b99a5;
  --bl-line:#dfe5ea;--bl-box:#ffffff;--bl-box2:#f5f7f9;
  --bl-chip:#eef3f6;--bl-hover:var(--exm-ui-accent-soft,#f2f8f5);
  color-scheme:light;color:var(--bl-ink);}
.pp-nedit[hidden],.pp-nedit [hidden]{display:none !important;}
@keyframes pp-fm-fade{from{opacity:0;}to{opacity:1;}}
.pp-nedit .pp-ne-back{display:none;}
.pp-nedit .pp-ne-panel{position:relative;display:flex;flex-direction:column;
  background:var(--bl-box);min-width:0;animation:pp-fm-fade .15s ease;}
/* Overlay mode: a modal over the page (board card edit, wiki edit/create).
   Width and left edge are measured from .content-wrapper and handed in as
   custom properties, so the box sits centred inside the content area rather
   than inside the viewport - a viewport-centred box is off-centre by half
   the admin sidebar, and moves when the sidebar collapses. */
.pp-nedit.pp-nedit-overlay{position:fixed;inset:0;z-index:1090;display:block;
  background:transparent;border:0;border-radius:0;overflow:visible;}
.pp-nedit.pp-nedit-overlay .pp-ne-back{display:block;position:fixed;inset:0;
  background:rgba(17,25,33,.5);animation:pp-fm-fade .15s ease;}
.pp-nedit.pp-nedit-overlay .pp-ne-panel{position:fixed;top:50%;
  left:var(--pp-ne-l, 50%);
  transform:translate(var(--pp-ne-tx, -50%), -50%);
  width:var(--pp-ne-w, min(80vw, 1200px));
  max-width:calc(100vw - 16px);
  height:auto;max-height:88vh;
  border:1px solid var(--bl-line);border-radius:8px;overflow:hidden;
  box-shadow:0 18px 48px rgba(0,0,0,.28);}
/* Inline main mode: acts as a card in place of pp-d-main (issue edit). */
.pp-nedit.pp-nedit-inline-main{margin-bottom:12px;}
.pp-nedit.pp-nedit-inline-main .pp-ne-body{overflow:visible;}
.pp-nedit.pp-nedit-inline-main .pp-ne-head{padding:12px 20px;}
.pp-nedit.pp-nedit-inline-main .pp-ne-alert{padding-left:20px;padding-right:20px;}
.pp-nedit.pp-nedit-inline-main .pp-ne-body-inner{padding:20px 24px 24px;}
.pp-nedit.pp-nedit-inline-main .pp-ne-foot{padding:12px 20px;position:sticky;bottom:0;z-index:5;}
/* Inline screen mode: acts as a full bl-screen (issue create, project edit). */
.pp-nedit.pp-nedit-inline-screen{min-height:calc(100vh - 240px);}
.pp-nedit.pp-nedit-inline-screen .pp-ne-body{overflow:visible;}
.pp-nedit .pp-ne-pbar{position:absolute;top:0;left:0;height:2.5px;width:0;background:var(--bl-accent);
  transition:width .4s ease, opacity .3s ease;z-index:20;border-radius:0 2px 2px 0;}
.pp-nedit .pp-ne-pbar[hidden]{display:none;}
.pp-nedit .pp-ne-pbar.err{background:#e57373;}
.pp-nedit .pp-ne-head{display:flex;flex-direction:column;gap:6px;padding:12px 40px;
  border-bottom:1px solid var(--bl-line);background:var(--bl-box);flex:none;}
.pp-nedit .pp-ne-head-top{display:flex;align-items:center;gap:12px;}
.pp-nedit .pp-ne-key{color:var(--bl-sub);font-size:12px;background:var(--bl-chip);border:1px solid var(--bl-line);
  border-radius:3px;padding:2px 9px;font-weight:700;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;
  cursor:pointer;user-select:none;transition:background .15s,color .15s,transform .15s;}
.pp-nedit .pp-ne-key:hover{background:var(--bl-hover);color:var(--bl-ink);border-color:color-mix(in srgb, var(--bl-accent) 30%, var(--bl-line));}
.pp-nedit .pp-ne-key:focus{outline:none;box-shadow:0 0 0 3px rgba(76,175,147,.22);}
.pp-nedit .pp-ne-key.copied{background:var(--bl-accent);color:#fff;border-color:var(--bl-accent-d);}
.pp-nedit .pp-ne-t{font-weight:500;font-size:12.5px;color:var(--bl-sub);flex:1;
  white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.pp-nedit .pp-ne-head-actions{display:flex;gap:8px;align-items:center;margin-left:auto;flex:none;}
.pp-nedit .pp-ne-head-actions .bl-btn-main,.pp-nedit .pp-ne-head-actions .bl-btn-sub{min-width:78px;}
.pp-nedit .pp-ne-x{cursor:pointer;color:var(--bl-sub);font-size:16px;padding:4px 9px;border-radius:var(--bl-radius-s,4px);}
.pp-nedit .pp-ne-x:hover{background:var(--bl-chip);color:var(--bl-ink);}
.pp-nedit .pp-ne-meta{display:flex;flex-wrap:wrap;align-items:center;gap:8px;font-size:11.5px;
  color:var(--bl-faint);padding-left:2px;line-height:1.4;}
.pp-nedit .pp-ne-meta[hidden]{display:none;}
.pp-nedit .pp-ne-meta-sep{opacity:.5;}
.pp-nedit .pp-ne-alert{padding:10px 40px;font-size:12.5px;border-bottom:1px solid #f0c7c3;
  background:#fdf3f2;color:#a94442;display:flex;align-items:center;gap:8px;}
.pp-nedit .pp-ne-alert.ok{background:#eefaf3;color:#1a7c56;border-bottom-color:#bbe6d1;}
/* Unsaved-draft offer. Amber rather than red: nothing is wrong, there is just
   something waiting to be picked up. Sits under the alert so a validation
   error still reads first. */
.pp-nedit .pp-ne-draft{padding:9px 40px;font-size:12.5px;border-bottom:1px solid #f0dfb0;
  background:#fdf8ec;color:#8a6d2f;display:flex;align-items:center;gap:10px;flex-wrap:wrap;}
.pp-nedit .pp-ne-draft[hidden]{display:none;}
.pp-nedit .pp-ne-draft .pp-ne-draft-msg{flex:1 1 auto;min-width:0;}
.pp-nedit .pp-ne-draft button{padding:4px 12px;font-size:12.5px;flex:none;}
.pp-nedit .pp-ne-body{flex:1;position:relative;overflow:auto;background:var(--bl-box);}
.pp-nedit .pp-ne-body-inner{max-width:1240px;margin:0 auto;padding:24px 40px 40px;}

/* ---- skeleton — mirrors real form: title row + desc row + 2-col grid ---- */
.pp-nedit .pp-ne-spin{color:var(--bl-sub);font-size:14px;padding:0;text-align:left;}
.pp-nedit .pp-ne-skel{display:grid;grid-template-columns:1fr 1fr;gap:14px 40px;}
.pp-nedit .pp-ne-skl{background:linear-gradient(90deg,var(--bl-chip) 25%,var(--bl-box2) 50%,var(--bl-chip) 75%);
  background-size:200% 100%;animation:pp-skel 1.15s linear infinite;border-radius:var(--bl-radius,6px);}
.pp-nedit .pp-ne-skl-hero{grid-column:1/-1;height:44px;}
.pp-nedit .pp-ne-skl-main{grid-column:1/-1;height:160px;}
.pp-nedit .pp-ne-skl-side{height:38px;}

/* ---- Backlog-style form layout: stacked title/desc + 2-col metadata ---- */
.pp-nedit .pp-ne-form{display:flex;flex-direction:column;}
.pp-nedit .pp-ne-title-row{margin-bottom:14px;}
/* ---- top strip: the two fields that frame everything below them ---- */
.pp-nedit .pp-ne-top-row{display:flex;align-items:center;gap:16px;flex-wrap:wrap;
  margin-bottom:12px;}
.pp-nedit .pp-ne-top-row[hidden]{display:none !important;}
.pp-nedit .pp-ne-top-row .pp-ne-fg{display:flex;flex-direction:row;align-items:center;gap:8px;
  margin:0;padding:0;border:0;min-width:0;}
.pp-nedit .pp-ne-top-row .pp-ne-fg > label{margin:0;font-size:12px;font-weight:600;
  color:var(--bl-sub);text-transform:none;letter-spacing:0;white-space:nowrap;}
.pp-nedit .pp-ne-top-row .pp-ne-fg > select.pp-ne-in{width:auto;min-width:150px;}
.pp-nedit .pp-ne-top-row .pp-ne-fg-parent{flex:1 1 320px;min-width:0;}
.pp-nedit .pp-ne-top-row .pp-ne-fg-parent .pp-lkp{flex:1 1 auto;min-width:0;}
.pp-nedit .pp-ne-top-row .pp-ne-fg .err{flex-basis:100%;}
.pp-nedit .pp-ne-parent-open{border:1px dashed var(--bl-line);background:transparent;
  color:var(--bl-sub);cursor:pointer;border-radius:var(--bl-radius-s,4px);padding:5px 12px;font-size:12px;
  display:inline-flex;align-items:center;gap:6px;}
.pp-nedit .pp-ne-parent-open:hover{border-color:var(--bl-accent);color:var(--bl-accent-d);
  border-style:solid;}
.pp-nedit .pp-ne-desc-row{margin-bottom:20px;background:transparent;border:0;border-radius:0;padding:0;}
.pp-nedit .pp-ne-desc-row .pp-ne-fg{gap:6px;}
/* The field's own label moves up into the header strip beside the tabs, so
   nothing stands between the toolbar and the box it acts on. */
.pp-nedit .pp-ne-desc-row .pp-ne-fg > label{display:none;}
.pp-nedit .pp-ne-desc-head{display:flex;align-items:center;gap:8px;margin-bottom:6px;}
.pp-nedit .pp-ne-desc-head .nm{color:var(--bl-sub);font-weight:600;font-size:11.5px;
  letter-spacing:.03em;text-transform:uppercase;}
.pp-nedit .pp-ne-desc-head .tabs{margin-left:auto;display:flex;gap:2px;}
.pp-nedit .pp-ne-desc-head .tab{border:1px solid transparent;background:transparent;color:var(--bl-sub);
  cursor:pointer;border-radius:var(--bl-radius-s,4px);padding:4px 12px;font-size:12px;font-weight:600;}
.pp-nedit .pp-ne-desc-head .tab:hover{background:var(--bl-hover);color:var(--bl-ink);}
.pp-nedit .pp-ne-desc-head .tab.on{background:var(--bl-box);border-color:var(--bl-line);color:var(--bl-accent-d);}
/* Monospace because the notation is the document: a table only reads as a
   table while its pipes stand over one another. */
.pp-nedit .pp-ne-desc-row .pp-ne-fg textarea.pp-ne-in{border:1px solid var(--bl-line);
  background:var(--bl-box);padding:10px 12px;min-height:180px;border-radius:var(--bl-radius-s,4px);
  font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,"Courier New",monospace;
  font-size:12.5px;line-height:1.65;tab-size:2;}
.pp-nedit .pp-ne-desc-prev{border:1px solid var(--bl-line);background:var(--bl-box);
  border-radius:var(--bl-radius-s,4px);padding:10px 12px;min-height:180px;max-height:52vh;overflow:auto;}
.pp-nedit .pp-ne-desc-prev[hidden]{display:none !important;}
/* On a desktop there is room to see the shape of what is being written -
   a heading, a list, where the paragraph ends - without scrolling for it.
   The writing surface and its preview are raised together so switching
   between the two tabs does not move everything below them. Left at the
   smaller height on narrow screens, where 240px would push the save
   buttons off the bottom. */
@media (min-width:768px){
  .pp-nedit .pp-ne-desc-row .pp-ne-fg textarea.pp-ne-in,
  .pp-nedit .pp-ne-desc-prev{min-height:240px;}
}
/* The field group stays in place so a failed save can still show its error
   against it; only the writing surface steps aside. */
.pp-nedit .pp-ne-desc-row.previewing .pp-ne-desc-toolbar,
.pp-nedit .pp-ne-desc-row.previewing .pp-ne-fg > .pp-ne-in,
.pp-nedit .pp-ne-desc-row.previewing .pp-ne-charcount{display:none;}
.pp-nedit .pp-ne-desc-row .pp-ne-fg textarea.pp-ne-in:focus{border-color:var(--bl-accent);
  box-shadow:0 0 0 3px rgba(76,175,147,.16);}
.pp-nedit .pp-ne-desc-toolbar{display:flex;align-items:center;gap:2px;padding:6px 8px;
  background:var(--bl-box2);border:1px solid var(--bl-line);border-bottom:0;
  border-radius:4px 4px 0 0;margin-bottom:-1px;}
.pp-nedit .pp-ne-desc-toolbar .tbtn{border:0;background:transparent;color:var(--bl-sub);
  cursor:pointer;padding:4px 8px;border-radius:3px;font-size:12.5px;line-height:1;
  min-width:26px;text-align:center;font-weight:600;}
.pp-nedit .pp-ne-desc-toolbar .tbtn:hover{background:var(--bl-hover);color:var(--bl-ink);}
.pp-nedit .pp-ne-desc-toolbar .tbtn:active{background:var(--bl-chip);}
.pp-nedit .pp-ne-desc-toolbar .tsep{width:1px;height:16px;background:var(--bl-line);margin:0 4px;}
.pp-nedit .pp-ne-desc-toolbar + .pp-ne-fg textarea.pp-ne-in{border-top-left-radius:0;border-top-right-radius:0;}
/* ---- a control with a small button of its own beside it ---- */
.pp-nedit .pp-ne-withbtn{display:flex;align-items:flex-start;gap:6px;min-width:0;}
.pp-nedit .pp-ne-props .pp-ne-fg > .pp-ne-withbtn{grid-column:2;}
.pp-nedit .pp-ne-withbtn > .pp-lkp{flex:1 1 auto;min-width:0;}
.pp-nedit .pp-ne-sidebtn{flex:0 0 auto;border:1px solid var(--bl-line);background:var(--bl-box);
  color:var(--bl-sub);cursor:pointer;border-radius:var(--bl-radius-s,4px);padding:6px 9px;font-size:12px;
  line-height:1.2;white-space:nowrap;}
.pp-nedit .pp-ne-sidebtn:hover{border-color:var(--bl-accent);color:var(--bl-accent-d);}
.pp-nedit .pp-ne-sidebtn:disabled{opacity:.5;cursor:default;}
/* ---- quick add: a name and a button, no second screen ---- */
.pp-nedit .pp-ne-qa{display:flex;align-items:center;gap:6px;margin-top:6px;}
.pp-nedit .pp-ne-props .pp-ne-fg > .pp-ne-qa{grid-column:2;}
.pp-nedit .pp-ne-qa[hidden]{display:none !important;}
.pp-nedit .pp-ne-qa input{flex:1 1 auto;min-width:0;border:1px solid var(--bl-line);
  border-radius:var(--bl-radius-s,4px);padding:6px 9px;font-size:12.5px;background:var(--bl-box);color:var(--bl-ink);}
.pp-nedit .pp-ne-qa input:focus{border-color:var(--bl-accent);outline:none;
  box-shadow:0 0 0 3px rgba(76,175,147,.16);}
/* ---- toolbar pop-outs: emoji, mention, file link, markdown help ---- */
.pp-nedit .pp-ne-desc-toolbar{position:relative;}
.pp-nedit .pp-ne-tpop{position:absolute;z-index:30;top:100%;left:8px;margin-top:4px;
  background:var(--bl-box);border:1px solid var(--bl-line);border-radius:var(--bl-radius,6px);
  box-shadow:var(--bl-shadow-m,0 8px 24px rgba(0,0,0,.14));padding:8px;max-height:260px;overflow:auto;
  min-width:200px;max-width:360px;}
.pp-nedit .pp-ne-tpop[hidden]{display:none !important;}
.pp-nedit .pp-ne-tpop .emo{display:grid;grid-template-columns:repeat(8,1fr);gap:2px;}
.pp-nedit .pp-ne-tpop .emo button{border:0;background:transparent;cursor:pointer;
  font-size:17px;line-height:1;padding:4px;border-radius:var(--bl-radius-s,4px);}
.pp-nedit .pp-ne-tpop .emo button:hover{background:var(--bl-hover);}
.pp-nedit .pp-ne-tpop .row{display:block;width:100%;text-align:left;border:0;
  background:transparent;cursor:pointer;padding:6px 8px;border-radius:var(--bl-radius-s,4px);
  font-size:12.5px;color:var(--bl-ink);}
.pp-nedit .pp-ne-tpop .row:hover{background:var(--bl-hover);}
.pp-nedit .pp-ne-tpop .empty{color:var(--bl-faint);font-size:12px;padding:6px 8px;}
.pp-nedit .pp-ne-tpop table{border-collapse:collapse;font-size:12px;}
.pp-nedit .pp-ne-tpop td{padding:3px 8px;vertical-align:top;}
.pp-nedit .pp-ne-tpop td code{background:var(--bl-box2);padding:1px 5px;border-radius:3px;
  font-size:11.5px;white-space:nowrap;}
.pp-nedit .pp-ne-charcount{font-size:11px;color:var(--bl-faint);text-align:right;margin-top:4px;}
.pp-nedit .pp-ne-charcount.warn{color:#c68a00;}
.pp-nedit .pp-ne-charcount.over{color:#e57373;font-weight:600;}
/* The metadata fields are the 基本情報 card. Each field is one row of it, on
   the same 110px label column and the same dashed rule the read-only card
   uses, so starting to edit swaps what a row holds without moving it. */
.pp-nedit .pp-ne-propbox{margin-top:20px;}
.pp-nedit .pp-ne-props .pp-ne-fg{display:grid;grid-template-columns:110px 1fr;gap:8px;align-items:center;
  padding:7px 0;border-bottom:1px dashed var(--bl-line2,#e7edf2);min-width:0;}
.pp-nedit .pp-ne-props .pp-ne-fg:last-child{border-bottom:0;}
.pp-nedit .pp-ne-props .pp-ne-fg > label{margin:0;font-size:12px;font-weight:400;text-transform:none;
  letter-spacing:0;color:var(--bl-sub);align-self:center;}
.pp-nedit .pp-ne-props .pp-ne-fg > .pp-ne-in,
.pp-nedit .pp-ne-props .pp-ne-fg > select.pp-ne-in,
.pp-nedit .pp-ne-props .pp-ne-fg > input.pp-ne-in{grid-column:2;min-width:0;}
.pp-nedit .pp-ne-props .pp-ne-fg > .help,
.pp-nedit .pp-ne-props .pp-ne-fg > .err,
.pp-nedit .pp-ne-props .pp-ne-fg > .pp-ne-seg,
.pp-nedit .pp-ne-props .pp-ne-fg > .pp-ne-check{grid-column:2;}
/* While editing, a field holds an input and its help, not a short value:
   the read-only card's as-many-260px-columns-as-fit made four narrow
   columns on a wide screen, the inputs 150px wide and the help five lines
   long. Columns of 440px at least instead: two on the editor's card (at
   most 1240px less its padding), one when it is narrow. A pair goes to a
   row (.pp-ne-rowstart starts one; in one column it changes nothing). The
   label sits level with the input, not halfway down its help. */
.pp-nedit .bl-props.pp-ne-props{grid-template-columns:repeat(auto-fit,minmax(min(100%,440px),1fr));column-gap:32px;align-items:start;}
.pp-nedit .bl-props.pp-ne-props > .pp-ne-rowstart{grid-column:1;}
/* the empty first cell of a [null, name] pair: none when the card has one column */
.pp-nedit .pp-ne-gap{min-height:1px;}
@media (max-width: 1099px){ .pp-nedit .pp-ne-gap{display:none;} }
.pp-nedit .pp-ne-props .pp-ne-fg{align-items:start;}
.pp-nedit .pp-ne-props .pp-ne-fg > label{align-self:start;padding-top:8px;line-height:1.35;}
.pp-nedit .pp-ne-props .pp-ne-fg > .help{margin-top:-2px;}
/* The help as a (?) beside the name in the card; under the field elsewhere. */
.pp-nedit .pp-ne-q{display:none;}
.pp-nedit .pp-ne-props .pp-ne-fg > .help{display:none;}
.pp-nedit .pp-ne-props .pp-ne-q{display:inline-flex;align-items:center;justify-content:center;position:relative;
  width:15px;height:15px;margin-left:5px;border:1px solid var(--bl-line);border-radius:50%;font-size:10px;line-height:1;
  color:var(--bl-sub);cursor:help;vertical-align:1px;outline:0;}
.pp-nedit .pp-ne-props .pp-ne-q:hover,.pp-nedit .pp-ne-props .pp-ne-q:focus{border-color:var(--bl-accent);color:var(--bl-accent-d);}
.pp-nedit .pp-ne-q .pp-ne-tip{display:none;position:absolute;z-index:50;left:-8px;top:calc(100% + 6px);width:260px;
  padding:8px 10px;border-radius:var(--bl-radius-s,4px);background:var(--bl-ink,#333);color:var(--bl-box,#fff);
  font-size:12px;line-height:1.5;font-weight:400;text-align:left;white-space:normal;box-shadow:0 4px 14px rgba(0,0,0,.18);}
.pp-nedit .pp-ne-q:hover .pp-ne-tip,.pp-nedit .pp-ne-q:focus .pp-ne-tip{display:block;}
/* 「その他の項目」: a row of its own across the card, and the folded fields
   on the same two columns below it. */
.pp-nedit .pp-ne-props > .pp-ne-more-btn{grid-column:1 / -1;justify-self:start;margin:10px 0 2px;padding:5px 10px;
  border:1px solid var(--bl-line);border-radius:var(--bl-radius-s,4px);background:var(--bl-box2);color:var(--bl-sub);
  font-size:12.5px;cursor:pointer;}
.pp-nedit .pp-ne-props > .pp-ne-more-btn:hover{border-color:var(--bl-accent);color:var(--bl-accent-d);}
.pp-nedit .pp-ne-more-btn .fa{transition:transform .12s ease;font-size:10px;}
.pp-nedit .pp-ne-more-btn.open .fa{transform:rotate(90deg);}
.pp-nedit .pp-ne-more-btn .n{display:inline-block;min-width:18px;padding:0 5px;border-radius:9px;background:var(--bl-line2,#e7edf2);
  font-size:11px;line-height:16px;text-align:center;}
.pp-nedit .pp-ne-more-btn .filled{color:var(--bl-accent-d);font-size:11.5px;}
.pp-nedit .pp-ne-props > .pp-ne-more{grid-column:1 / -1;display:grid;
  grid-template-columns:repeat(auto-fit,minmax(min(100%,440px),1fr));column-gap:32px;align-items:start;}
.pp-nedit .pp-ne-props > .pp-ne-more[hidden]{display:none;}
.pp-nedit .pp-ne-props > .pp-ne-more > .pp-ne-rowstart{grid-column:1;}
.pp-nedit .pp-ne-files-row{margin-top:24px;padding:0;background:transparent;
  border:none;border-radius:0;}
.pp-nedit .pp-ne-files-row[hidden]{display:none !important;}
.pp-nedit .pp-ne-files-row > .pp-ne-fg{gap:8px;}
.pp-nedit .pp-ne-files-row > .pp-ne-fg > label{margin-bottom:4px;}
.pp-nedit .pp-ne-drop{position:relative;border:1.5px dashed var(--bl-line);border-radius:var(--bl-radius,6px);
  background:var(--bl-box2);padding:28px 18px;text-align:center;cursor:pointer;
  transition:border-color .12s ease, background .12s ease;}
.pp-nedit .pp-ne-drop:hover{border-color:var(--bl-accent);background:var(--bl-box);}
.pp-nedit .pp-ne-drop-hint{color:var(--bl-sub);font-size:12.5px;pointer-events:none;}
.pp-nedit .pp-ne-drop input[type=file]{position:absolute;inset:0;opacity:0;cursor:pointer;
  width:100%;height:100%;}
@media (max-width:820px){
  /* One column already; what a narrow screen needs is the label above the
     field rather than a 110px column eating a third of the width. */
  .pp-nedit .pp-ne-props .pp-ne-fg{grid-template-columns:1fr;gap:4px;}
  .pp-nedit .pp-ne-props .pp-ne-fg > *{grid-column:1 !important;}
  .pp-nedit .pp-ne-body-inner{padding:16px 20px 32px;}
  .pp-nedit .pp-ne-head,.pp-nedit .pp-ne-alert{padding-left:20px;padding-right:20px;}
}

/* ---- field group ---- */
.pp-nedit .pp-ne-fg{display:flex;flex-direction:column;gap:5px;min-width:0;}
.pp-nedit .pp-ne-fg > label{color:var(--bl-sub);font-weight:600;font-size:11.5px;letter-spacing:.03em;
  text-transform:uppercase;}
.pp-nedit .pp-ne-fg > label .req{color:#e57373;margin-left:3px;font-weight:700;}
.pp-nedit .pp-ne-fg > label .ro{color:var(--bl-faint);margin-left:6px;font-weight:600;font-size:10px;
  text-transform:uppercase;letter-spacing:.06em;}
.pp-nedit .pp-ne-fg > .help{color:var(--bl-sub);font-size:11px;line-height:1.4;}
.pp-nedit .pp-ne-fg > .err{color:#a94442;font-size:11.5px;line-height:1.4;}

/* base inputs */
.pp-nedit .pp-ne-fg .form-control,
.pp-nedit .pp-ne-fg input.pp-ne-in,
.pp-nedit .pp-ne-fg textarea.pp-ne-in,
.pp-nedit .pp-ne-fg select.pp-ne-in{
  border:1px solid var(--bl-line);background:var(--bl-box);color:var(--bl-ink);
  border-radius:var(--bl-radius-s,4px);padding:6px 10px;font-size:13px;line-height:1.4;font-family:inherit;
  transition:border-color .12s ease, box-shadow .12s ease;
  min-height:32px;width:100%;box-sizing:border-box;}
.pp-nedit .pp-ne-fg textarea.pp-ne-in{min-height:120px;resize:vertical;}
.pp-nedit .pp-ne-fg .pp-ne-in::placeholder{color:var(--bl-faint);}
.pp-nedit .pp-ne-fg .pp-ne-in:hover{border-color:#c8d1d8;}
.pp-nedit .pp-ne-fg .pp-ne-in:focus{border-color:var(--bl-accent);
  box-shadow:0 0 0 3px rgba(76,175,147,.16);outline:0;}
.pp-nedit .pp-ne-fg .pp-ne-in[readonly],.pp-nedit .pp-ne-fg .pp-ne-in:disabled{
  background:var(--bl-box2);color:var(--bl-sub);cursor:not-allowed;}
.pp-nedit .pp-ne-fg.err .pp-ne-in{border-color:#e57373;box-shadow:0 0 0 3px rgba(229,115,115,.14);}
.pp-nedit .pp-ne-check{display:flex;align-items:center;gap:8px;padding:6px 0;}
.pp-nedit .pp-ne-check input{width:16px;height:16px;margin:0;accent-color:var(--bl-accent);}

/* searchable chip picker (window.ppMakePicker) — used by the record editor
   and the project settings tab, so the rules are not scoped to .pp-nedit */
.pmportal .pp-lkp{position:relative;min-width:0;}
.pmportal .pp-lkp-box{display:flex;flex-wrap:wrap;align-items:center;gap:5px;min-height:34px;
  border:1px solid var(--bl-line);border-radius:var(--bl-radius-s,4px);background:var(--bl-box);padding:4px 6px;cursor:text;}
.pmportal .pp-lkp-box:focus-within{border-color:var(--bl-accent);box-shadow:0 0 0 3px rgba(76,175,147,.16);}
.pmportal .pp-lkp.readonly .pp-lkp-box{background:var(--bl-box2);cursor:not-allowed;}
.pmportal .pp-lkp-chip{display:inline-flex;align-items:center;gap:5px;max-width:100%;
  background:var(--bl-chip);border:1px solid var(--bl-line);border-radius:12px;
  padding:1px 4px 1px 9px;font-size:12px;line-height:19px;color:var(--bl-ink);}
.pmportal .pp-lkp-chip > span{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.pmportal .pp-lkp-x{border:0;background:none;color:var(--bl-sub);cursor:pointer;font-size:14px;
  line-height:1;padding:0 3px;}
.pmportal .pp-lkp-x:hover{color:var(--bl-red);}
.pmportal .pp-lkp-q{flex:1 1 90px;min-width:90px;border:0;outline:0;background:transparent;
  color:var(--bl-ink);font-size:13px;padding:3px 2px;}
.pmportal .pp-lkp-dd{position:absolute;z-index:40;left:0;right:0;top:calc(100% + 2px);max-height:230px;
  overflow-y:auto;background:var(--bl-box);border:1px solid var(--bl-line);border-radius:var(--bl-radius-s,4px);
  box-shadow:0 6px 18px rgba(0,0,0,.12);}
.pmportal .pp-lkp-op{padding:7px 10px;font-size:12.5px;cursor:pointer;color:var(--bl-ink);}
.pmportal .pp-lkp-op:hover,.pmportal .pp-lkp-op.on{background:var(--bl-hover);}
/* The way to empty a single-value field, in place of the cross it no longer
   carries. It is not an option, so the arrow keys and Enter pass it by. */
.pmportal .pp-lkp-none{padding:7px 10px;font-size:12.5px;cursor:pointer;color:var(--bl-faint);
    border-bottom:1px solid var(--bl-line);}
.pmportal .pp-lkp-none:hover{background:var(--bl-hover);}
.pmportal .pp-lkp-empty{padding:8px 10px;font-size:12px;color:var(--bl-faint);}

/* ---- issue field: what was picked, and the button that picks it ---- */
.pmportal .pp-ibf{display:flex;align-items:center;gap:6px;min-width:0;}
.pmportal .pp-ibf-face{flex:1 1 auto;min-width:0;display:flex;align-items:center;gap:8px;
  border:1px solid var(--bl-line);border-radius:var(--bl-radius-s,4px);background:var(--bl-box);
  color:var(--bl-faint);cursor:pointer;padding:6px 10px;font-size:12.5px;min-height:34px;
  text-align:left;}
.pmportal .pp-ibf-face:hover{border-color:var(--bl-accent);}
.pmportal .pp-ibf-face:focus{outline:none;border-color:var(--bl-accent);
  box-shadow:0 0 0 3px rgba(76,175,147,.16);}
.pmportal .pp-ibf-face.set{color:var(--bl-ink);}
.pmportal .pp-ibf-face .sj{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.pmportal .pp-ibf-face:disabled{background:var(--bl-box2);cursor:not-allowed;}
.pp-nedit .pp-ne-props .pp-ne-fg > .pp-ibf{grid-column:2;}
.pp-nedit .pp-ne-top-row .pp-ne-fg-parent .pp-ibf{flex:1 1 auto;min-width:0;}
.pmportal .pp-ne-fg.err .pp-ibf-face{border-color:#e57373;box-shadow:0 0 0 3px rgba(229,115,115,.14);}

/* ---- issue browser: the modal the field opens ---- */
/* Sits on <body> above the edit form, which is itself an overlay, so the
   z-index has to clear that one rather than the page - and, for the same
   reason, it is outside .bl-app and has to carry the palette itself, the
   way .pp-nedit does. */
.pp-ib{position:fixed;inset:0;z-index:2200;display:flex;align-items:flex-start;
  justify-content:center;padding:6vh 16px 16px;
  --bl-accent:var(--exm-ui-accent,#4caf93);--bl-accent-d:var(--exm-ui-accent-strong,#3d9a80);
  --bl-ink:#24323f;--bl-sub:#6b7a88;--bl-faint:#8b99a5;
  --bl-line:#dfe5ea;--bl-box:#ffffff;--bl-box2:#f5f7f9;
  --bl-chip:#eef3f6;--bl-hover:var(--exm-ui-accent-soft,#f2f8f5);--bl-red:#d9534f;
  color-scheme:light;color:var(--bl-ink);
  font-family:inherit;}
.pp-ib [hidden]{display:none !important;}
.pp-ib-bd{position:absolute;inset:0;background:rgba(15,23,32,.42);}
.pp-ib-panel{position:relative;display:flex;flex-direction:column;width:100%;max-width:640px;
  max-height:76vh;background:var(--bl-box,#fff);border-radius:8px;
  box-shadow:0 18px 52px rgba(0,0,0,.28);overflow:hidden;}
.pp-ib-head{display:flex;align-items:center;gap:10px;padding:12px 14px;
  border-bottom:1px solid var(--bl-line);}
.pp-ib-title{font-size:14px;font-weight:600;color:var(--bl-ink);}
.pp-ib-x{margin-left:auto;border:0;background:none;color:var(--bl-sub);cursor:pointer;
  font-size:20px;line-height:1;padding:0 4px;}
.pp-ib-x:hover{color:var(--bl-ink);}
.pp-ib-qwrap{position:relative;display:flex;align-items:center;gap:8px;padding:10px 14px;
  border-bottom:1px solid var(--bl-line);color:var(--bl-faint);}
.pp-ib-q{flex:1 1 auto;min-width:0;border:1px solid var(--bl-line);border-radius:var(--bl-radius-s,4px);
  background:var(--bl-box);color:var(--bl-ink);font-size:13px;padding:7px 10px;}
.pp-ib-q:focus{outline:none;border-color:var(--bl-accent);
  box-shadow:0 0 0 3px rgba(76,175,147,.16);}
.pp-ib-body{flex:1 1 auto;min-height:120px;overflow-y:auto;}
.pp-ib-tb{width:100%;border-collapse:collapse;font-size:12.5px;table-layout:fixed;}
.pp-ib-tb th{position:sticky;top:0;z-index:1;background:var(--bl-box2);color:var(--bl-sub);
  font-size:11px;font-weight:600;letter-spacing:.03em;text-transform:uppercase;
  text-align:left;padding:7px 12px;border-bottom:1px solid var(--bl-line);}
/* The key is fixed width so the titles beside it line up and can be scanned
   as a column rather than as ragged text. */
.pp-ib-tb th.k,.pp-ib-tb td.k{width:132px;}
.pp-ib-tb td{padding:7px 12px;border-bottom:1px solid var(--bl-line);color:var(--bl-ink);
  overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.pp-ib-tb tbody tr{cursor:pointer;}
.pp-ib-tb tbody tr.on,.pp-ib-tb tbody tr:hover{background:var(--bl-hover);}
.pp-ib-msg{padding:18px 14px;font-size:12.5px;color:var(--bl-faint);text-align:center;}
.pp-ib-msg[hidden]{display:none !important;}
.pp-ib-foot{padding:8px 14px;border-top:1px solid var(--bl-line);background:var(--bl-box2);
  display:flex;align-items:center;gap:10px;}
.pp-ib-hint{font-size:11px;color:var(--bl-faint);flex:1;}
.pp-ib-clear{border:1px solid var(--bl-line);background:var(--bl-box);border-radius:var(--bl-radius-s,4px);cursor:pointer;
  padding:4px 12px;font-size:12px;color:var(--bl-sub);}
.pp-ib-clear:hover{border-color:var(--bl-red);color:var(--bl-red);}
.pp-ib-clear[hidden]{display:none !important;}
/* The key carries the same weight here as it does in the issue list, so the
   column reads as keys rather than as two columns of plain text. */
.pp-ib .bl-key{font-weight:700;color:var(--bl-sub);white-space:nowrap;}
body.pp-ib-open{overflow:hidden;}
@media (max-width:640px){
  .pp-ib{padding:0;}
  .pp-ib-panel{max-width:none;max-height:100vh;height:100vh;border-radius:0;}
  .pp-ib-tb th.k,.pp-ib-tb td.k{width:96px;}
}
.pp-nedit .pp-ne-props .pp-ne-fg > .pp-lkp{grid-column:2;}
.pmportal .pp-ne-fg.err .pp-lkp-box{border-color:#e57373;box-shadow:0 0 0 3px rgba(229,115,115,.14);}
.pp-nedit select.pp-ne-in[multiple]{min-height:80px;padding:6px;}
/* colored dot preview for a single-value coloured <select> — the pill shows
   the current option's colour so users get the same signal as the list view */
.pp-nedit .pp-ne-sel-wrap{position:relative;display:flex;align-items:center;gap:8px;}
.pp-nedit .pp-ne-sel-wrap .pp-ne-in{flex:1;}
.pp-nedit .pp-ne-sel-dot{width:10px;height:10px;border-radius:50%;flex:none;
  background:var(--sel-c, transparent);border:1px solid rgba(0,0,0,.08);
  transition:background .12s ease;}
.pp-nedit .pp-ne-sel-dot.hidden{display:none;}
/* inline validation on required-field blur */
.pp-nedit .pp-ne-fg.err > .pp-ne-in,
.pp-nedit .pp-ne-fg.err select.pp-ne-in,
.pp-nedit .pp-ne-fg.err textarea.pp-ne-in{border-color:#e57373;box-shadow:0 0 0 3px rgba(229,115,115,.14);}
.pp-nedit .pp-ne-fg.err > .err{color:#a94442;font-size:11.5px;line-height:1.4;}

/* ---- title row (subject as document-title-like heading, full width) ---- */
.pp-nedit .pp-ne-fg-title{gap:0;padding:0;}
.pp-nedit .pp-ne-fg-title > label{display:none;}
.pp-nedit .pp-ne-fg-title .pp-ne-in{font-size:18px;font-weight:500;letter-spacing:-.01em;
  padding:10px 12px;min-height:46px;line-height:1.3;border-radius:var(--bl-radius-s,4px);
  background:var(--bl-box);border-color:var(--bl-line);}
.pp-nedit .pp-ne-fg-title .pp-ne-in:hover{border-color:color-mix(in srgb, var(--bl-accent) 30%, var(--bl-line));}
.pp-nedit .pp-ne-fg-title .pp-ne-in:focus{background:var(--bl-box);border-color:var(--bl-accent);
  box-shadow:0 0 0 3px rgba(76,175,147,.18);}

/* ---- segmented pill (select with <=5 options; e.g. status, priority) ---- */
.pp-nedit .pp-ne-seg{display:flex;flex-wrap:wrap;gap:4px;padding:2px;background:var(--bl-box);
  border:1px solid var(--bl-line);border-radius:var(--bl-radius,6px);}
.pp-nedit .pp-ne-seg.readonly{opacity:.7;pointer-events:none;}
.pp-nedit .pp-ne-seg-b{--seg-c:var(--bl-sub);flex:1 0 auto;background:transparent;border:1px solid transparent;
  color:var(--bl-sub);font-weight:600;font-size:12px;padding:5px 10px;border-radius:var(--bl-radius-s,4px);cursor:pointer;
  display:inline-flex;align-items:center;gap:6px;justify-content:center;
  transition:background .12s ease,color .12s ease,border-color .12s ease;white-space:nowrap;}
.pp-nedit .pp-ne-seg-b::before{content:"";width:8px;height:8px;border-radius:50%;background:var(--seg-c);flex:none;}
.pp-nedit .pp-ne-seg-b:hover{background:var(--bl-box2);color:var(--bl-ink);}
.pp-nedit .pp-ne-seg-b.on{background:color-mix(in srgb, var(--seg-c) 14%, var(--bl-box));
  color:var(--seg-c);border-color:color-mix(in srgb, var(--seg-c) 42%, transparent);}

/* ---- chips + preview pill ---- */
.pp-nedit .pp-ne-chips{display:flex;flex-wrap:wrap;gap:6px;padding:3px 0;}
.pp-nedit .pp-ne-chip{background:var(--bl-chip);border:1px solid var(--bl-line);color:var(--bl-ink);
  border-radius:14px;padding:2px 10px;font-size:12px;display:inline-flex;align-items:center;gap:5px;}
.pp-nedit .pp-ne-fg .pp-ne-pill{display:inline-flex;align-items:center;gap:6px;padding:3px 10px;border-radius:12px;
  font-size:11.5px;font-weight:700;color:#fff;background:var(--bl-sub);width:fit-content;}

/* ---- AI auto-fill: the button beside the subject, and the chips under the fields it filled ---- */
.pp-nedit .pp-ne-title-row.has-ai{display:flex;align-items:flex-start;gap:8px;}
.pp-nedit .pp-ne-title-row.has-ai .pp-ne-fg-title{flex:1 1 auto;min-width:0;}
.pp-nedit .pp-ne-ai-btn{flex:none;height:46px;padding:0 14px;border-radius:var(--bl-radius-s,4px);border:1px solid var(--bl-accent);
  background:var(--bl-box);color:var(--bl-accent-d);font-weight:700;font-size:12.5px;cursor:pointer;
  display:inline-flex;align-items:center;gap:6px;white-space:nowrap;}
.pp-nedit .pp-ne-ai-btn:hover{background:var(--bl-hover);}
.pp-nedit .pp-ne-ai-btn:disabled{opacity:.5;cursor:default;background:var(--bl-box);}
.pp-nedit .pp-ne-ai-chip{display:inline-flex;align-items:center;gap:4px;margin-top:5px;padding:1px 8px;border-radius:10px;
  font-size:11px;font-weight:700;border:1px solid transparent;width:fit-content;max-width:100%;line-height:1.5;}
.pp-nedit .pp-ne-ai-chip.ok{background:#e6f5ef;color:#1a7c56;border-color:#bbe6d1;}
.pp-nedit .pp-ne-ai-chip.mid{background:#fff6e0;color:#8a5a00;border-color:#f3dc9a;}
.pp-nedit .pp-ne-ai-chip.off{background:var(--bl-chip);color:var(--bl-sub);border-color:var(--bl-line);font-weight:400;}
.pp-nedit .pp-ne-ai-chip.adopt{cursor:pointer;background:var(--bl-box);color:var(--bl-accent-d);border-color:var(--bl-accent);}
.pp-nedit .pp-ne-ai-chip.adopt:hover{background:var(--bl-hover);}
.pp-nedit .pp-ne-ai-chip.pick{cursor:pointer;background:var(--bl-box);color:var(--bl-sub);border-color:var(--bl-line);border-style:dashed;font-weight:400;}
.pp-nedit .pp-ne-ai-chip.pick:hover{background:var(--bl-hover);color:var(--bl-accent-d);}
.pp-nedit .pp-ne-props .pp-ne-fg > .pp-ne-ai-chip{grid-column:2;margin-top:0;}
.pp-nedit .pp-ne-fg.pp-ne-ai > .pp-ne-in,
.pp-nedit .pp-ne-fg.pp-ne-ai .pp-lkp-box{box-shadow:0 0 0 2px rgba(76,175,147,.22);border-radius:var(--bl-radius-s,4px);}
.pp-nedit .pp-ne-alert .pp-ne-ai-undo{margin-left:10px;border:1px solid #bbe6d1;background:#fff;color:#1a7c56;
  border-radius:var(--bl-radius-s,4px);padding:2px 9px;font-size:12px;cursor:pointer;}
.pp-nedit .pp-ne-alert .pp-ne-ai-undo:hover{background:#e6f5ef;}
@media (max-width:560px){ .pp-nedit .pp-ne-title-row.has-ai{flex-wrap:wrap;} .pp-nedit .pp-ne-ai-btn{height:36px;} }

/* ---- footer with visible shortcut hint + change counter on save (sticky) ---- */
.pp-nedit .pp-ne-foot{flex:none;display:flex;justify-content:space-between;align-items:center;gap:12px;
  padding:12px 40px;border-top:1px solid var(--bl-line);background:var(--bl-box);
  box-shadow:0 -1px 0 rgba(0,0,0,.02);}
.pp-nedit .pp-ne-hint{font-size:11.5px;color:var(--bl-sub);display:flex;align-items:center;
  gap:8px;user-select:none;}
.pp-nedit .pp-ne-hint kbd{display:inline-flex;align-items:center;padding:0 6px;height:20px;border-radius:var(--bl-radius-s,4px);
  background:var(--bl-box);border:1px solid var(--bl-line);border-bottom-width:2px;
  font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:10.5px;font-weight:600;color:var(--bl-ink);
  box-shadow:0 1px 0 rgba(0,0,0,.02);}
.pp-nedit .pp-ne-hint .plus{color:var(--bl-faint);margin:0 -2px;}
.pp-nedit .pp-ne-hint .sep{opacity:.4;margin:0 2px;}
.pp-nedit .pp-ne-btns{display:flex;gap:10px;flex:none;}
.pp-nedit .pp-ne-foot .pp-ne-save{min-width:130px;}
.pp-nedit .pp-ne-foot .pp-ne-save[disabled]{opacity:.6;cursor:progress;}
.pp-nedit .pp-ne-foot .pp-ne-save .pp-ne-save-label{transition:opacity .1s ease;}
@media (max-width:560px){ .pp-nedit .pp-ne-hint{display:none;} }

/* ---- scrollbars ---- */
.pp-nedit .pp-ne-panel::-webkit-scrollbar,
.pp-nedit .pp-ne-body::-webkit-scrollbar{width:8px;}
.pp-nedit .pp-ne-body::-webkit-scrollbar-thumb{background:var(--bl-line);border-radius:var(--bl-radius-s,4px);}
.pp-nedit .pp-ne-body::-webkit-scrollbar-track{background:transparent;}

/* ---- skeleton lines for the async parts of the issue screen ---- */
.pmportal .pp-skel{height:12px;border-radius:var(--bl-radius,6px);background:linear-gradient(90deg,var(--bl-chip) 25%,var(--bl-box2) 50%,var(--bl-chip) 75%);
  background-size:200% 100%;animation:pp-skel 1.1s linear infinite;margin:10px 0;}
.pmportal .pp-skel.w2{width:70%;}
.pmportal .pp-skel.w3{width:45%;}
@keyframes pp-skel{from{background-position:200% 0;}to{background-position:-200% 0;}}

/* ---- the accent flows into the embedded widgets' primary buttons ---- */
.pmportal .bl-app .btn-primary{background:var(--bl-accent) !important;border-color:var(--bl-accent-d) !important;}
.pmportal .bl-app .btn-primary:hover,.pmportal .bl-app .btn-primary:focus{background:var(--bl-accent-d) !important;}

/* ==== ISS00287 polish: list, detail head, hover/focus, phone width ==== */
/* ---- issue list: roomier rows, soft separators, sticky header ---- */
.pmportal .bl-list td{padding:10px 10px;border-bottom-color:var(--bl-line2);line-height:1.45;}
.pmportal .bl-list tbody tr:last-child td{border-bottom:0;}
.pmportal .bl-list th{position:sticky;top:0;z-index:2;border-bottom:0;box-shadow:inset 0 -1px 0 var(--bl-line);
  font-size:var(--bl-fs-s);letter-spacing:.02em;}
/* The wrapper already scrolls sideways, so a header can only stick inside it:
   on a desktop it becomes the list's own scroller, pager pinned at its foot. */
@media (min-width:761px) and (min-height:560px){
  .pmportal .bl-tablewrap{max-height:calc(100vh - 110px);overflow:auto;}
  .pmportal .bl-tablewrap > .bl-pager{position:sticky;bottom:0;left:0;z-index:2;background:var(--bl-box);}
}
/* priority as a tinted badge in its own colour; status and type already are pills */
.pmportal .bl-list .bl-prio,.pmportal .bl-dsum .bl-prio{border-radius:var(--bl-radius-pill);padding:1px 9px 1px 6px;
  font-size:var(--bl-fs-xs);line-height:1.7;background:color-mix(in srgb, currentColor 12%, transparent);}
/* overdue / due soon: a tinted chip, and a red edge on the overdue row */
.pmportal .bl-list .bl-due.over,.pmportal .bl-dsum .bl-due.over{display:inline-block;border-radius:var(--bl-radius-s);
  padding:1px 6px;background:color-mix(in srgb, var(--bl-red) 11%, transparent);}
.pmportal .bl-list .bl-due.soon,.pmportal .bl-dsum .bl-due.soon{display:inline-block;border-radius:var(--bl-radius-s);
  padding:1px 6px;background:color-mix(in srgb, var(--bl-amber) 16%, transparent);}
.pmportal .bl-list tbody tr:has(.bl-due.over) > td:first-child{box-shadow:inset 3px 0 0 var(--bl-red);}

/* ---- issue detail: back button, summary strip under the subject ---- */
/* 課題一覧へ: filled accent button so it stands out (snoro 2026-11-18) */
/* S14: a way back is a quiet grey button; the accent colour is kept for the
   one action that matters on a screen (次の状態にする, 送信, 保存, 課題を追加). */
.pmportal .bl-dback{display:inline-flex;align-items:center;gap:6px;background:var(--bl-box);border:1px solid var(--bl-line);
  border-radius:var(--bl-radius-s);padding:7px 16px;color:var(--bl-sub);font-weight:600;}
.pmportal .bl-dback:hover,.pmportal .bl-dback:focus{border-color:var(--bl-faint);color:var(--bl-ink);background:var(--bl-hover);text-decoration:none;}
.pmportal .bl-dhead > span:last-child{flex-wrap:wrap;justify-content:flex-end;}
.pmportal .pp-ar-note { font-size: 12px; color: var(--bl-sub); margin-left: 10px; }
.pmportal .pp-ar-note a { color: var(--bl-accent-d); font-weight: 700; }
.pmportal .pp-rc { color: var(--bl-accent-d); font-size: 11px; margin-right: 2px; }
.pmportal .bl-dback-foot{margin:14px 0 4px;}
.pmportal .bl-dback.bl-dback-s{padding:4px 12px;font-size:12px;}
.pmportal .bl-dback.bl-dback-proj{flex:0 0 auto;align-self:center;margin-right:4px;}
.pmportal .bl-dsum{display:flex;flex-wrap:wrap;align-items:center;gap:8px 20px;margin-bottom:12px;padding:9px 12px;
  background:var(--bl-box2);border:1px solid var(--bl-line2);border-radius:var(--bl-radius);}
.pmportal .bl-dsum .it{display:inline-flex;align-items:center;gap:8px;flex-wrap:wrap;min-width:0;}
/* Issue detail, kept short (ISS00336): the fields as a grid of as many
   columns as the width takes, rather than one field per line. */
.pmportal .bl-props{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));column-gap:28px;}
.pmportal .bl-dsum .pp-tocmt{margin-left:auto;font-weight:700;color:var(--bl-accent-d);white-space:nowrap;cursor:pointer;}
.pmportal .pp-dbar .pp-tocmt{flex:none;font-weight:700;color:var(--bl-accent-d);white-space:nowrap;cursor:pointer;}
.pmportal .bl-reladd-open{color:var(--bl-accent-d);font-weight:600;}
.pmportal .bl-dsum .k{color:var(--bl-sub);font-size:var(--bl-fs-xs);font-weight:700;white-space:nowrap;}
/* How many comments wait below, on the links that lead to them and on the
   timeline's own head. */
.pmportal .pp-tocmt .n:not(:empty),.pmportal #pp-d-tlsum:not(:empty){display:inline-block;margin-left:6px;padding:0 7px;min-width:20px;
  box-sizing:border-box;text-align:center;border-radius:var(--bl-radius-pill,10px);font-size:11px;line-height:18px;font-weight:700;
  background:color-mix(in srgb, var(--bl-accent) 16%, var(--bl-box));color:var(--bl-accent-d);}
/* The issue's head: three or four buttons and one menu for the rest. */
.pmportal .bl-dhead .bl-btn-sub,.pmportal .bl-dhead .bl-btn-main{white-space:nowrap;}
.pmportal .pp-more{position:relative;display:inline-flex;}
.pmportal .pp-more > .m{position:absolute;right:0;top:calc(100% + 4px);z-index:40;min-width:190px;padding:4px 0;
  background:var(--bl-box);border:1px solid var(--bl-line);border-radius:var(--bl-radius,6px);box-shadow:0 6px 18px rgba(15,42,60,.14);}
.pmportal .pp-more > .m[hidden]{display:none;}
.pmportal .pp-more > .m a{display:flex;align-items:center;gap:8px;padding:8px 14px;font-size:13px;color:var(--bl-ink);
  white-space:nowrap;text-decoration:none;cursor:pointer;}
.pmportal .pp-more > .m a .fa{width:14px;text-align:center;color:var(--bl-sub);}
.pmportal .pp-more > .m a:hover,.pmportal .pp-more > .m a:focus-visible{background:var(--bl-hover);outline:0;}
.pmportal .pp-more > .m a.pp-issuedel{margin-top:4px;padding-top:9px;border-top:1px solid var(--bl-line2);color:var(--bl-red,#d9534f);}
.pmportal .pp-more > .m a.pp-issuedel .fa{color:inherit;}
.pmportal .pp-more > .m a.pp-issuedel[data-armed="1"]{background:color-mix(in srgb, var(--bl-red,#d9534f) 12%, var(--bl-box));font-weight:700;}
/* On a phone the button may sit anywhere in a wrapped row: the menu hangs
   from the head as a whole instead, so it can never leave the screen. */
@media (max-width:760px){
  .pmportal .bl-dhead{position:relative;}
  .pmportal .pp-more{position:static;}
  .pmportal .pp-more > .m{left:0;right:0;top:100%;}
}
/* A wide screen has room beside the text: the boxes about the issue (fields,
   children, files, related) stand in a column on the right, and the comments
   follow the description with nothing in between. .pp-two is set by
   ppSideFit() only while there is a box to show. The second row takes any
   height the column needs beyond the description, so no gap opens above the
   comments. From 1200px (S1; it was 1800px, so most screens never saw it). */
@media (min-width:1200px){
  .pmportal .bl-detail.pp-two{display:grid;grid-template-columns:minmax(0,1fr) clamp(300px,28%,420px);grid-template-rows:auto 1fr;
    column-gap:12px;align-items:start;}
  .pmportal .bl-detail.pp-two > *{grid-column:1;min-width:0;}
  .pmportal .bl-detail.pp-two > #pp-d-tlbox{grid-row:2;}
  .pmportal .bl-detail.pp-two > .bl-dside{grid-column:2;grid-row:1 / span 2;}
  .pmportal .bl-detail.pp-two > .bl-dside > .bl-card-box:last-child{margin-bottom:0;}
  .pmportal .bl-detail.pp-two .bl-dside .bl-props{grid-template-columns:minmax(0,1fr);}
  .pmportal .bl-detail.pp-two .bl-dside .bl-rows > .row > .k,
  .pmportal .bl-detail.pp-two .bl-dside .bl-props .k{width:84px;}
  .pmportal .bl-detail.pp-two .bl-dside .bl-children .row > .k{width:auto;}
  .pmportal .bl-detail.pp-two .bl-dside .bl-reladd{flex-wrap:wrap;}
  /* A related issue in the narrow column: the kind of relation above, the
     subject on as many lines as it needs - a subject cut after ten
     characters says nothing. */
  .pmportal .bl-detail.pp-two .bl-dside .bl-related > .row{flex-wrap:wrap;gap:2px 8px;}
  .pmportal .bl-detail.pp-two .bl-dside .bl-related > .row > .k{width:100%;}
  .pmportal .bl-detail.pp-two .bl-dside .bl-related > .row > .k:empty{display:none;}
  .pmportal .bl-detail.pp-two .bl-dside .bl-related .v{flex:1 1 100%;align-items:flex-start;}
  .pmportal .bl-detail.pp-two .bl-dside .bl-related .v .tx{white-space:normal;overflow-wrap:anywhere;}
  .pmportal .bl-detail.pp-two .bl-dside .bl-children .v > .bl-issuelink{white-space:normal;overflow-wrap:anywhere;}
}

/* ---- hover / focus ---- */
.pmportal .bl-btn-main,.pmportal .bl-btn-sub,.pmportal .bl-sttab,.pmportal .bl-qchip,.pmportal .bl-fmine,
.pmportal .bl-tab,.pmportal .bl-addissue,.pmportal .bl-dback{transition:background-color .12s ease,border-color .12s ease,color .12s ease,box-shadow .12s ease;}
.pmportal .bl-btn-main:hover:not([disabled]){background:var(--bl-accent-d);}
.pmportal .bl-bulk .bl-btn-main.armed:hover:not([disabled]){background:var(--bl-red);filter:brightness(.92);}
.pmportal .bl-btn-sub:hover{border-color:var(--bl-accent);color:var(--bl-accent-d);text-decoration:none;}
.pmportal .bl-sttab:hover{border-color:var(--bl-accent);color:var(--bl-accent-d);}
.pmportal .bl-tab:hover{background:var(--bl-hover);}
.pmportal .bl-btn-main:focus-visible,.pmportal .bl-btn-sub:focus-visible,.pmportal .bl-sttab:focus-visible,
.pmportal .bl-qchip:focus-visible,.pmportal .bl-fmine:focus-visible,.pmportal .bl-tab:focus-visible,
.pmportal .bl-addissue:focus-visible,.pmportal .bl-dback:focus-visible,.pmportal .bl-pager .nav button:focus-visible,
.pp-top .pp-top-home:focus-visible,.pp-top .pp-top-dd>button:focus-visible{outline:2px solid var(--bl-accent,var(--exm-ui-accent,#4caf93));outline-offset:1px;}
.pmportal .bl-filters select:hover,.pmportal .bl-filters input:hover,.pmportal .bl-tabsearch input:hover,
.pmportal .bl-bulk select:hover,.pmportal .bl-pager .sz select:hover,.pmportal .pp-sf-add input:hover{border-color:var(--bl-faint);}
.pmportal .bl-filters select:focus,.pmportal .bl-filters input:focus,.pmportal .bl-tabsearch input:focus,
.pmportal .bl-bulk select:focus,.pmportal .bl-pager .sz select:focus,.pmportal .pp-sf-add input:focus,
.pmportal .bl-notifyrow select:focus{border-color:var(--bl-accent);outline:none;box-shadow:var(--bl-focus);}

/* ---- phone width: nothing wider than the screen ---- */
.pmportal{max-width:100%;}
@media (max-width:760px){
  .pmportal .bl-stats{grid-template-columns:repeat(2,minmax(0,1fr));}
  .pmportal .bl-home > *,.pmportal .bl-burn > *{min-width:0;}
  .pmportal .bl-fright{margin-left:0;flex-wrap:wrap;}
  .pmportal .bl-filters select,.pmportal .bl-filters input{max-width:100%;}
  .pmportal .bl-bulk select{max-width:100%;}
  .pmportal .bl-dhead > span:last-child{margin-left:0 !important;justify-content:flex-start;}
  .pmportal .bl-dtitle{font-size:17px;overflow-wrap:anywhere;}
  .pmportal .bl-rows > .row > .k,.pmportal .bl-props .k{width:84px;}
  .pmportal .bl-ms .nm{width:110px;}
  .pmportal .bl-ms .cnt{width:auto;}
  .pmportal .pp-sf-menu{max-width:calc(100vw - 40px);}
  .pp-top .pp-top-menu{max-width:calc(100vw - 32px);}
  .pp-top .pp-top-search{max-width:none;flex-basis:100%;margin:0 0 8px;}
}

/* Unread (read receipts): a row with something the reader has not seen yet -
   a light tint, a bar on the row's left edge and the subject in bold, like an
   unread mail. The bar is drawn inside the first cell, so the subjects of read
   and unread rows start at the same place (a dot before the subject pushed it
   right - snoro 2026-11-19). */
.pmportal .bl-list tbody tr.pp-unread{background:color-mix(in srgb, #2f80ed 6%, var(--bl-box));}
.pmportal .bl-list tbody tr.pp-unread.sel{background:color-mix(in srgb, var(--bl-accent) 10%, color-mix(in srgb, #2f80ed 6%, var(--bl-box)));}
.pmportal .bl-list tbody tr.pp-unread td.sub{font-weight:700;}
.pmportal .bl-list tbody tr.pp-unread > td:first-child{box-shadow:inset 3px 0 0 #2f80ed;}
/* Move an issue to another project: the panel under the detail head. */
.pmportal .pp-move{margin:8px 0 4px;}
.pmportal .pp-move-in{padding:10px 12px;border:1px solid var(--bl-line);border-radius:6px;background:var(--bl-box);font-size:13px;}
.pmportal .pp-move-in .pp-move-sel{max-width:100%;margin:0 6px;padding:4px 6px;}
.pmportal .pp-move-note{margin-top:6px;color:var(--bl-sub);font-size:12px;}
.pmportal .pp-issuedel{color:#c0392b;}
/* Review request under a comment (ISS00341): one line - who was asked, each
   確認待ち or 確認済み, and the asked reader's own button. */
.pmportal .pp-rv{display:flex;align-items:center;flex-wrap:wrap;gap:4px 6px;margin:6px 0 2px;font-size:12px;}
.pmportal .pp-rv .lb{color:var(--bl-sub);font-weight:700;white-space:nowrap;}
.pmportal .pp-rvp{display:inline-flex;align-items:center;gap:4px;padding:1px 8px 1px 2px;border:1px solid var(--bl-line);border-radius:12px;background:var(--bl-box);line-height:20px;white-space:nowrap;}
.pmportal .pp-rvp .bl-av{width:18px;height:18px;margin:0;font-size:9px;}
.pmportal .pp-rvp .s{color:#b7791f;font-weight:700;}
.pmportal .pp-rvp .tm{color:var(--bl-sub);font-size:11px;}
.pmportal .pp-rvp.wait{border-color:#e0b458;background:color-mix(in srgb, #eda62a 10%, var(--bl-box));}
.pmportal .pp-rvp.ok{border-color:color-mix(in srgb, #2e9e6b 55%, var(--bl-line));background:color-mix(in srgb, #2e9e6b 9%, var(--bl-box));}
.pmportal .pp-rvp.ok .s{color:#2e9e6b;}
.pmportal .pp-rvbtn{display:inline-flex;align-items:center;gap:4px;padding:2px 10px;border:1px solid var(--bl-accent);border-radius:12px;background:var(--bl-accent);color:#fff;font-size:12px;font-weight:700;line-height:20px;cursor:pointer;}
.pmportal .pp-rvbtn:hover{filter:brightness(.94);}
.pmportal .pp-rvbtn.undo{border:0;background:none;color:var(--bl-sub);font-weight:400;padding:2px 2px;text-decoration:underline;}
.pmportal .pp-rvbtn[disabled]{opacity:.6;cursor:default;}
/* Reactions on an issue and a comment (ISS00259). */
.pmportal .pp-rxrow{margin:8px 0 4px;}
.pmportal .pp-rxbar{display:inline-flex;align-items:center;flex-wrap:wrap;gap:4px;position:relative;margin-right:8px;vertical-align:middle;}
.pmportal .pp-rx{display:inline-flex;align-items:center;gap:3px;padding:1px 7px;border:1px solid var(--bl-line);border-radius:12px;background:var(--bl-box);font-size:13px;line-height:20px;cursor:pointer;user-select:none;}
.pmportal .pp-rx .n{font-size:11px;color:var(--bl-sub);}
.pmportal .pp-rx.on{border-color:#2f80ed;background:color-mix(in srgb, #2f80ed 10%, var(--bl-box));}
.pmportal .pp-rx.on .n{color:#2f80ed;font-weight:700;}
.pmportal .pp-rxadd{display:inline-flex;align-items:center;gap:4px;padding:2px 10px;border:1px solid var(--bl-line);border-radius:14px;background:var(--bl-box);font-size:13px;line-height:20px;color:var(--bl-sub);cursor:pointer;user-select:none;}
.pmportal .pp-rxadd .fa{font-size:15px;}
.pmportal .pp-rxadd:hover{color:#2f80ed;border-color:#2f80ed;}
.pmportal .pp-rxpick{position:absolute;left:0;top:100%;margin-top:4px;z-index:50;display:flex;flex-wrap:wrap;gap:2px;max-width:260px;padding:6px;border:1px solid var(--bl-line);border-radius:8px;background:var(--bl-box);box-shadow:0 4px 14px rgba(0,0,0,.15);}
.pmportal .pp-rxpick[hidden]{display:none;}
.pmportal .pp-rxopt{font-size:20px;line-height:28px;width:30px;text-align:center;border-radius:6px;cursor:pointer;}
.pmportal .pp-rxopt:hover{background:var(--bl-hover, rgba(0,0,0,.06));transform:scale(1.15);}
/* Who opened the issue, on the detail's summary line. */
.pmportal .pp-reads .pp-reads-v{display:inline-flex;align-items:center;gap:2px;flex-wrap:wrap;}
.pmportal .pp-reads .pp-reads-v.none{color:var(--bl-faint);font-size:12px;}
.pmportal .pp-reads .pp-reads-n{font-size:12px;color:var(--bl-sub);margin-right:4px;}
.pmportal .pp-reads .pp-read{width:20px;height:20px;font-size:10px;line-height:20px;}
.pmportal .pp-reads .pp-read.old{opacity:.4;}
.pmportal .pp-reads .pp-reads-more{font-size:11px;color:var(--bl-sub);margin-left:3px;}
/* Right-click menu of the issue list. */
.pmportal .pp-ctx{position:fixed;z-index:3000;min-width:160px;padding:4px 0;background:var(--bl-box);color:var(--bl-ink);
  border:1px solid var(--bl-line);border-radius:6px;box-shadow:0 6px 20px rgba(0,0,0,.18);}
.pmportal .pp-ctx button{display:block;width:100%;text-align:left;padding:7px 14px;border:0;background:none;color:inherit;font-size:13px;cursor:pointer;}
.pmportal .pp-ctx button:hover,.pmportal .pp-ctx button:focus{background:var(--bl-hover);outline:none;}
.pmportal .pp-ctx button i{width:16px;color:var(--bl-sub);}
/* "Also by e-mail" on the comment form and the new-issue form. */
.pmportal .pp-notify-mail{display:flex;align-items:center;gap:4px;margin:8px 0 0;font-size:12px;font-weight:400;color:var(--bl-sub);cursor:pointer;}
.pmportal .pp-ne-mail{display:inline-flex;align-items:center;gap:4px;margin:0 6px 0 0;font-size:12px;font-weight:400;color:var(--bl-sub);cursor:pointer;white-space:nowrap;}
.pmportal .pp-ne-mail[hidden]{display:none;}

/* "Mail this issue" dialog. */
.pmportal .pp-mailbox{position:fixed;inset:0;z-index:2900;display:flex;align-items:center;justify-content:center;padding:16px;}
.pmportal .pp-mailbox-back{position:absolute;inset:0;background:rgba(0,0,0,.35);}
.pmportal .pp-mailbox-panel{position:relative;width:100%;max-width:520px;max-height:calc(100vh - 32px);overflow:auto;background:var(--bl-box);color:var(--bl-ink);
  border-radius:8px;box-shadow:0 10px 30px rgba(0,0,0,.25);padding:16px 18px;}
.pmportal .pp-mailbox-head{font-weight:700;font-size:15px;margin-bottom:10px;}
.pmportal .pp-mailbox-sub{font-weight:400;font-size:12px;color:var(--bl-sub);margin-top:2px;}
.pmportal .pp-mailbox-k{font-size:12px;color:var(--bl-sub);margin:6px 0 4px;}
.pmportal .pp-mailbox-users{display:flex;flex-wrap:wrap;gap:6px 14px;max-height:180px;overflow:auto;padding:6px 0;}
.pmportal .pp-mailbox-users label{display:flex;align-items:center;gap:4px;font-weight:400;cursor:pointer;margin:0;}
.pmportal .pp-mailbox-msg{width:100%;margin-top:8px;border:1px solid var(--bl-line);border-radius:4px;padding:6px 8px;background:var(--bl-box);color:var(--bl-ink);}
.pmportal .pp-mailbox-note{font-size:11px;color:var(--bl-faint);margin-top:6px;}
.pmportal .pp-mailbox-foot{display:flex;align-items:center;gap:8px;justify-content:flex-end;margin-top:12px;}
.pmportal .pp-mailbox-err{margin-right:auto;color:var(--bl-red);font-size:12px;}

/* ==== compact layout (snoro 2026-11-18) ===============================
   The header, the tabs and the filter block took a third of the screen
   before the first issue. Everything here is an override of the rules
   above, kept in one place so it can be read (or dropped) as a unit. */
/* ---- project header: one line ---- */
.pmportal .bl-projhead{flex-wrap:wrap;gap:6px 12px;padding:7px 14px;margin-bottom:8px;}
.pmportal .bl-projicon{width:30px;height:30px;font-size:14px;border-radius:var(--bl-radius,6px);}
/* min(): on a phone a 300px basis is wider than the card and would drop the
   name under the icon instead of beside it. */
.pmportal .bl-projinfo{flex:1 1 min(300px,calc(100% - 42px));display:flex;flex-wrap:wrap;align-items:center;gap:2px 14px;}
.pmportal .bl-projttl{display:flex;align-items:center;gap:8px;min-width:0;max-width:100%;}
.pmportal .bl-projname{font-size:17px;line-height:1.3;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.pmportal .bl-projkey{flex:none;margin-left:0;vertical-align:0;padding:0 7px;font-size:11.5px;}
.pmportal .bl-projttl .bl-pill{flex:none;}
.pmportal .bl-projmeta{flex:1 1 440px;min-width:0;display:flex;align-items:center;gap:2px 14px;margin-top:0;white-space:nowrap;overflow:hidden;flex-wrap:wrap;}
.pmportal .bl-projmeta i{margin:0 4px 0 0;}
.pmportal .bl-projmeta .mi{flex:none;}
/* The note keeps to what is left of the line; a click opens it in place. */
.pmportal .bl-projnote{flex:1 1 170px;min-width:0;display:flex;align-items:baseline;color:inherit;text-decoration:none;
  border-radius:var(--bl-radius-s,4px);}
.pmportal .bl-projnote .tx{flex:0 1 auto;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.pmportal .bl-projnote::after{content:'';flex:none;width:5px;height:5px;margin:0 2px 0 7px;border:solid currentColor;
  border-width:0 1.5px 1.5px 0;transform:translateY(-3px) rotate(45deg);opacity:.7;}
.pmportal .bl-projnote:hover,.pmportal .bl-projnote:focus{color:var(--bl-ink);text-decoration:none;}
.pmportal .bl-projnote:focus-visible{outline:2px solid var(--bl-accent);outline-offset:-2px;}
.pmportal .bl-projhead.note-open .bl-projmeta{flex-basis:100%;flex-wrap:wrap;white-space:normal;overflow:visible;}
.pmportal .bl-projhead.note-open .bl-projnote{flex-basis:100%;}
.pmportal .bl-projhead.note-open .bl-projnote .tx{white-space:pre-line;overflow:visible;line-height:1.55;}
.pmportal .bl-projhead.note-open .bl-projnote::after{transform:translateY(1px) rotate(-135deg);}
.pmportal .bl-projside{flex:0 1 auto;min-width:0;max-width:100%;margin-left:auto;display:flex;align-items:center;gap:10px;}
.pmportal .bl-projhead .bl-avatars{flex:0 1 auto;min-width:0;margin-left:0;padding-left:6px;flex-wrap:wrap;row-gap:3px;}
.pmportal .bl-projhead .bl-av{width:24px;height:24px;}
.pmportal .bl-projhead .pp-freshat{flex:none;margin-left:0;}
/* flex:none + nowrap: squeezed by a long note, 更新 used to stand one glyph per line */
.pmportal .bl-projhead .pp-refresh{flex:none;white-space:nowrap;padding:4px 10px;font-size:12px;}
@media (max-width:760px){
  .pmportal .bl-projhead{gap:6px 10px;padding:8px 10px;}
  .pmportal .bl-projicon{width:30px;height:30px;font-size:14px;}
  .pmportal .bl-projinfo{flex:1 1 calc(100% - 42px);}
  .pmportal .bl-projname{font-size:16px;}
  .pmportal .bl-projmeta{margin-top:0;line-height:1.5;flex-wrap:wrap;}
  .pmportal .bl-projmeta i,.pmportal .bl-projmeta i:not(:first-child){margin:0 4px 0 0;}
  .pmportal .bl-projnote{flex-basis:100%;}
}
/* ---- screen tabs ---- */
.pmportal .bl-tabs{margin-bottom:8px;}
.pmportal .bl-tab{padding:8px 13px;}
.pmportal .bl-addissue{padding:5px 14px;}
/* The tab row stays one line on a laptop: the search box gives up width
   before the add button is pushed down to a second row. */
@media (min-width:761px){
  /* A small basis that grows: a wrapping row breaks on the basis, before
     anything is asked to shrink. */
  .pmportal .bl-tabs .bl-tabsearch{flex:1 1 110px;max-width:210px;margin-right:8px;}
  .pmportal .bl-tabs .bl-tabsearch input{width:100%;box-sizing:border-box;}
  .pmportal .bl-tabs .bl-tab,.pmportal .bl-tabs .bl-addissue{white-space:nowrap;flex:none;}
  .pmportal .bl-tabs .bl-addissue{margin-left:auto;}
}
/* ---- wiki with no pages: only the list card, no empty article frame ---- */
.pmportal .bl-wiki.pp-wempty{grid-template-columns:minmax(260px,420px);}
.pmportal .bl-wiki.pp-wempty > .bl-card-box:nth-child(2){display:none;}
/* ---- issue toolbar: status tabs and quick chips share one wrapping row ---- */
.pmportal .bl-toolbar{padding:8px 12px;margin-bottom:8px;}
.pmportal .bl-pillrow{display:flex;flex-wrap:wrap;align-items:center;gap:5px 6px;margin-bottom:7px;}
.pmportal .bl-pillrow .bl-sttabs,.pmportal .bl-pillrow .bl-qchips{display:contents;}
/* a hairline where the status cut ends and the shortcuts begin */
.pmportal .bl-pillrow .bl-qchips::before{content:'';flex:none;width:1px;height:16px;background:var(--bl-line);margin:0 5px;}
.pmportal .bl-sttab,.pmportal .bl-qchip{padding:2px 10px;line-height:1.5;white-space:nowrap;}
.pmportal .bl-qchip{color:var(--bl-sub);background:var(--bl-box2);border-color:var(--bl-line2);}
.pmportal .bl-qchip i{margin-right:4px;opacity:.8;}
.pmportal .bl-sttab .ct,.pmportal .bl-qchip .ct{font-variant-numeric:tabular-nums;}
.pmportal .bl-filters{gap:6px;}
.pmportal .bl-filters select,.pmportal .bl-filters input{padding:4px 8px;}
.pmportal .bl-filters .bl-treebtn{padding:4px 10px;}
/* ---- issue list: tighter rows, quieter header, calmer secondary text ---- */
.pmportal .bl-list th{padding:7px 10px;font-size:11.5px;color:var(--bl-faint);letter-spacing:.03em;}
.pmportal .bl-list th.sorted{color:var(--bl-accent-d);}
.pmportal .bl-list td{padding:7px 10px;line-height:1.4;}
.pmportal .bl-list td.sub{color:var(--bl-ink);}
.pmportal .bl-list td.sub .pp-subsub{margin-top:1px;color:var(--bl-faint);}
.pmportal .bl-list a.bl-key{font-variant-numeric:tabular-nums;white-space:nowrap;}
.pmportal .bl-list td.up{font-size:11.5px;font-variant-numeric:tabular-nums;}
.pmportal .bl-tablewrap{box-shadow:var(--bl-shadow-s);}
@media (min-width:761px) and (min-height:560px){
  .pmportal .bl-tablewrap{max-height:calc(100vh - 70px);}
}

/* ISS02931: the project's settings beside its name */
.pmportal .bl-projset{display:inline-flex;align-items:center;gap:4px;margin-left:8px;padding:1px 8px;border:1px solid var(--bl-line);
  border-radius:var(--bl-radius-pill, 999px);color:var(--bl-faint);font-size:12px;font-weight:400;text-decoration:none;vertical-align:middle;}
.pmportal .bl-projset:hover,.pmportal .bl-projset:focus{color:var(--bl-accent-d);border-color:var(--bl-accent);}
/* ISS02931: set the roles, the hours and the people told on the issue itself */
.pmportal .pp-qf-pen{border:0;background:none;padding:0 2px;margin-left:2px;color:var(--bl-faint);cursor:pointer;font-size:12px;line-height:1;}
.pmportal .pp-qf-pen:hover,.pmportal .pp-qf-pen:focus{color:var(--bl-accent-d);}
.pmportal .pp-qf{flex-basis:100%;display:block;margin:6px 0 8px;padding:8px 10px;border:1px solid var(--bl-line);border-radius:6px;background:var(--bl-box);}
.pmportal .pp-qf-foot{display:flex;align-items:center;justify-content:flex-end;gap:8px;margin-top:6px;}
.pmportal .pp-qf-msg{margin-right:auto;font-size:12px;color:#c0392b;}
.pmportal .pp-qf-sub{color:var(--bl-faint);font-size:11.5px;margin-right:6px;}
.pmportal .pp-qf-mail{font-size:12px;margin-left:6px;white-space:nowrap;}
/* ISS02931: the optional columns of the issue list */
.pmportal .pp-oc-wrap{position:relative;display:inline-block;}
.pmportal .pp-oc-btn{border:0;background:none;padding:0;cursor:pointer;}
.pmportal .pp-oc-menu{position:absolute;right:0;top:calc(100% + 6px);z-index:30;min-width:170px;padding:6px 10px;
  background:var(--bl-box);border:1px solid var(--bl-line);border-radius:6px;box-shadow:0 6px 18px rgba(0,0,0,.12);}
.pmportal .pp-oc-menu label{display:block;margin:4px 0;font-weight:400;white-space:nowrap;cursor:pointer;}

/* ==== UX-C: the issue screen (S1, S2, S17, S19, M1) ==== */
/* S19: the trail above the issue instead of the green 課題一覧へ button. */
.pmportal .pp-crumbs{display:flex;align-items:center;flex-wrap:wrap;gap:2px 6px;margin:0 0 8px;font-size:12px;color:var(--bl-sub);min-width:0;}
.pmportal .pp-crumbs a{color:var(--bl-sub);text-decoration:none;max-width:22em;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.pmportal .pp-crumbs a:hover,.pmportal .pp-crumbs a:focus-visible{color:var(--bl-accent-d);text-decoration:underline;}
.pmportal .pp-crumbs .fa{color:var(--bl-faint);font-size:11px;}
.pmportal .pp-crumbs [aria-current]{color:var(--bl-ink);font-weight:700;}
.pmportal .pp-dbar .pp-dbar-back{flex:none;color:var(--bl-sub);font-weight:600;text-decoration:none;white-space:nowrap;}
.pmportal .pp-dbar .pp-dbar-back:hover{color:var(--bl-accent-d);}
/* S1: one box for the issue's fields. Its top rows (status, assignees, due,
   priority, roles) are said in the strip under the title as long as there is
   no column beside the text; with the column, the strip keeps only who read
   it and the way to the comments. */
.pmportal .bl-props .row[data-top]{display:none;}
.pmportal #pp-d-propsum{display:none;}
@media (max-width:760px){.pmportal #pp-d-propsum:not(:empty){display:inline;}}
@media (min-width:761px) and (max-width:1199px){
  .pmportal #pp-d-propbox.pp-no-mid{display:none !important;}
}
@media (min-width:1200px){
  .pmportal .bl-detail.pp-two .bl-dsum [data-sum]{display:none;}
  .pmportal .bl-detail.pp-two .bl-props .row[data-top]{display:flex;}
  /* the first four rows, a little apart from the rest */
  .pmportal .bl-detail.pp-two .bl-props .row[data-p="pr"]{border-bottom:1px solid var(--bl-line);padding-bottom:9px;margin-bottom:2px;}
}
/* S2: a phone reads the text, then the comments, then the rest - folded,
   with their counts on their heads. Its strip keeps the status, the
   assignees and the due date; the priority and the roles move to the box. */
@media (max-width:760px){
  .pmportal .bl-detail{display:flex;flex-direction:column;}
  .pmportal .bl-detail > .bl-card-box:first-child{order:1;}
  .pmportal .bl-detail > #pp-d-tlbox{order:2;}
  .pmportal .bl-detail > #pp-d-side{order:3;}
  .pmportal .bl-dsum [data-sum="pr"],.pmportal .bl-dsum [data-sum="role"]{display:none;}
  .pmportal .bl-props .row[data-top="pr"],.pmportal .bl-props .row[data-top="role"]{display:flex;}
  .pmportal #pp-d-propbox.pp-no-phone{display:none !important;}
  .pmportal .pp-crumbs a{max-width:12em;}
}
/* M1: a value of the box opens its editor in place. */
.pmportal .bl-props .pp-qf-v{cursor:pointer;border-radius:4px;margin:-2px -4px;padding:2px 22px 2px 4px;position:relative;}
.pmportal .bl-props .pp-qf-v:hover,.pmportal .bl-props .pp-qf-v:focus-visible{background:var(--bl-hover);outline:0;}
.pmportal .bl-props .pp-qf-v::after{content:"040";font-family:FontAwesome;position:absolute;right:5px;top:50%;transform:translateY(-50%);
  font-size:11px;color:var(--bl-faint);opacity:0;transition:opacity .12s;}
.pmportal .bl-props .pp-qf-v:hover::after,.pmportal .bl-props .pp-qf-v:focus-visible::after{opacity:1;}
@media (hover:none){.pmportal .bl-props .pp-qf-v::after{opacity:.6;}}
.pmportal .bl-props > .pp-qf,.pmportal .bl-props > .pp-qf-unset{grid-column:1 / -1;}
.pmportal .pp-qf-unset{display:flex;flex-wrap:wrap;align-items:center;gap:4px 12px;padding:8px 0 2px;font-size:12px;}
.pmportal .pp-qf-unset .k{color:var(--bl-faint);font-size:11.5px;}
.pmportal .pp-qf-unset .pp-qf-add{color:var(--bl-sub);text-decoration:none;white-space:nowrap;}
.pmportal .pp-qf-unset .pp-qf-add:hover,.pmportal .pp-qf-unset .pp-qf-add:focus-visible{color:var(--bl-accent-d);}
.pmportal .pp-qf-unset .pp-qf-add .fa{font-size:10px;margin-right:2px;}
/* the editor sits inside the box's rows: their row and label rules stop at it */
.pmportal .bl-props .pp-qf .bl-qe > .row{align-items:stretch;border-bottom:0;padding:0;}
.pmportal .bl-props .pp-qf .bl-qe > .row > .k{width:auto;}
.pmportal .pp-qf .bl-qe > .row.w-date{width:auto;}
.pmportal .pp-qf .bl-qe > .row.w-date > .v{display:flex;flex-wrap:wrap;align-items:center;gap:6px;}
.pmportal .pp-qf .bl-qe > .row.w-date .bl-qe-in{width:120px;}
.pmportal .pp-qf-quick{display:inline-flex;gap:4px;}
.pmportal .pp-qf-quick .bl-btn-sub,.pmportal .pp-qf-mine{padding:3px 9px;font-size:12px;}
.pmportal .pp-qf-mine{margin-top:6px;}
/* the undo button in the toast */
#pp-toast .pp-toast-act{margin-left:10px;border:1px solid rgba(255,255,255,.55);background:transparent;color:#fff;
  border-radius:4px;padding:2px 10px;font-weight:700;cursor:pointer;}
#pp-toast .pp-toast-act:hover,#pp-toast .pp-toast-act:focus-visible{background:rgba(255,255,255,.16);}
/* S17: the dock is a comment box first. The status stays beside the comment;
   the other fields fold under ほかの項目も変える, the due time beside its date. */
.pmportal .pp-cmt-st{margin-top:10px;}
/* Closed, the fields take one line under the comment rather than a column
   of their own (a browser without :has keeps the two columns). */
.pmportal .bl-dockbody:has(#pp-d-editbox:not(.pp-qe-open)){display:block;}
.pmportal .bl-dockbody:has(#pp-d-editbox:not(.pp-qe-open)) .bl-dockside{max-width:none;}
.pmportal #pp-d-editbox .pp-qe-toggle{cursor:pointer;user-select:none;}
.pmportal #pp-d-editbox .pp-qe-toggle .bl-caret{margin-left:auto;transition:transform .12s;}
.pmportal #pp-d-editbox.pp-qe-open .pp-qe-toggle .bl-caret{transform:rotate(90deg);}
.pmportal #pp-d-editbox:not(.pp-qe-open) > .bl-boxbody{display:none;}
.pmportal #pp-d-editbox:not(.pp-qe-open) > .bl-boxhead{border-bottom-color:transparent;}
.pmportal .bl-qe > .row.pp-qe-due{width:auto;}
.pmportal .bl-qe > .row.pp-qe-due > .v{display:flex;align-items:center;gap:6px;}
.pmportal .bl-qe > .row.pp-qe-due .bl-qe-in{width:120px;}
.pmportal .bl-qe > .row.pp-qe-due .bl-qe-in.pp-qe-dt{width:80px;font-size:12px;}
/* ==== UX-D: navigation and the phone's head (S3, S4, S8, M3) ==== */
/* M3: the project's name is a button that opens the list of projects. */
.pmportal .bl-projttl .pp-pjswitch{display:inline-flex;align-items:center;gap:6px;min-width:0;border:0;background:none;padding:0;
  font:inherit;font-size:17px;font-weight:700;color:var(--bl-ink);cursor:pointer;text-align:left;}
.pmportal .bl-projttl .pp-pjswitch .nm{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.pmportal .bl-projttl .pp-pjswitch .fa-caret-down{flex:none;font-size:12px;color:var(--bl-faint);}
.pmportal .bl-projttl .pp-pjswitch:hover .nm{text-decoration:underline;}
.pmportal .bl-projttl .pp-pjswitch:hover .fa-caret-down{color:var(--bl-accent-d);}
.pmportal .bl-projttl .pp-pjswitch:focus-visible{outline:2px solid var(--bl-accent);outline-offset:2px;border-radius:3px;}
.pmportal .bl-projmore{display:none;}
@media (max-width:760px){
  /* S4: one line - ‹, the name, the key, ⓘ. The rest behind ⓘ. */
  .pmportal .bl-projhead{flex-wrap:wrap;align-items:center;}
  .pmportal .bl-projhead .bl-projicon{display:none;}
  .pmportal .bl-projhead .bl-dback-proj{padding:4px 9px;margin-right:0;align-self:flex-start;margin-top:2px;}
  .pmportal .bl-projhead .bl-dback-proj .tx{display:none;}
  .pmportal .bl-projinfo{flex:1 1 calc(100% - 52px);}
  .pmportal .bl-projttl{flex:1 1 100%;}
  .pmportal .bl-projttl .pp-pjswitch{font-size:16px;flex:0 1 auto;}
  .pmportal .bl-projttl .bl-projset .tx{display:none;}
  .pmportal .bl-projmore{display:inline-flex;align-items:center;justify-content:center;flex:none;margin-left:auto;width:32px;height:32px;
    border:0;background:none;color:var(--bl-sub);font-size:17px;cursor:pointer;border-radius:50%;}
  .pmportal .bl-projmore[aria-expanded="true"]{color:var(--bl-accent-d);background:var(--bl-hover);}
  .pmportal .bl-projhead:not(.info-open) .bl-projmeta,.pmportal .bl-projhead:not(.info-open) .bl-projside{display:none;}
  .pmportal .bl-projhead.info-open .bl-projmeta{flex-basis:100%;}
  .pmportal .bl-projhead.info-open .bl-projside{flex-basis:100%;flex-wrap:wrap;margin-left:0;}
  /* S3: the tabs in one row that scrolls sideways, faded at the right end
     while there is more; the row's search and the project settings tab
     (the gear beside the name) stay off a phone. */
  .pmportal .bl-tabs{flex-wrap:nowrap;overflow-x:auto;overflow-y:hidden;padding:0 4px;scrollbar-width:none;
    -webkit-overflow-scrolling:touch;overscroll-behavior-x:contain;
    -webkit-mask-image:linear-gradient(to right,#000 calc(100% - 28px),transparent);
            mask-image:linear-gradient(to right,#000 calc(100% - 28px),transparent);}
  .pmportal .bl-tabs::-webkit-scrollbar{display:none;}
  .pmportal .bl-tabs .bl-tab{flex:none;white-space:nowrap;min-height:40px;box-sizing:border-box;display:inline-flex;align-items:center;}
  .pmportal .bl-tabs .bl-tabsearch{display:none;}
  .pmportal .bl-tabs .bl-tab[data-scr="settings"]{display:none;}
}
/* ==== UX-E: list, board and gantt (S9, S10, S11, S18, M4, M7, M8) ==== */
/* S11, M7: a cell that can be changed shows it - a dotted edge and a pencil
   on hover, the pencil one press away. */
.pmportal #pp-issue-rows td[data-ed]{position:relative;}
.pmportal #pp-issue-rows td[data-ed]:hover{box-shadow:inset 0 0 0 1px color-mix(in srgb, var(--bl-accent) 45%, transparent);
  outline:1px dashed color-mix(in srgb, var(--bl-accent) 55%, var(--bl-line));outline-offset:-3px;}
.pmportal .pp-ed-pen{position:absolute;right:2px;top:50%;transform:translateY(-50%);border:0;border-radius:4px;padding:2px 5px;
  background:var(--bl-box);color:var(--bl-sub);font-size:11px;line-height:1;cursor:pointer;opacity:0;transition:opacity .12s;}
.pmportal #pp-issue-rows td[data-ed]:hover .pp-ed-pen,.pmportal .pp-ed-pen:focus-visible{opacity:1;}
.pmportal .pp-ed-pen:hover{color:var(--bl-accent-d);background:var(--bl-hover);}
@media (hover:none){.pmportal .pp-ed-pen{opacity:.55;}}
.pmportal .pp-ed-hint{display:flex;align-items:center;gap:8px;margin:8px 12px 0;padding:6px 10px;border-radius:var(--bl-radius-s);
  background:var(--bl-box2);color:var(--bl-sub);font-size:12px;}
.pmportal .pp-ed-hint[hidden]{display:none;}
.pmportal .pp-ed-hint .x{margin-left:auto;border:0;background:none;color:var(--bl-faint);font-size:16px;line-height:1;cursor:pointer;}
/* S18: the way from this project's 自分が担当 to every project's */
.pmportal .pp-mine-all{display:inline-flex;align-items:center;gap:4px;margin-left:6px;font-size:12px;color:var(--bl-accent-d);
  white-space:nowrap;text-decoration:none;}
.pmportal .pp-mine-all:hover{text-decoration:underline;}
/* S10: the gantt on a phone */
.pmportal .pp-gantt-hint{display:none;}
@media (max-width:760px){
  .pmportal .pp-gantt-hint{display:flex;flex-wrap:wrap;align-items:center;gap:4px 8px;margin-bottom:8px;padding:8px 12px;
    background:var(--bl-box);border:1px solid var(--bl-line);border-radius:var(--bl-radius);font-size:12.5px;color:var(--bl-sub);}
  .pmportal .pp-gantt-hint a{font-weight:700;color:var(--bl-accent-d);white-space:nowrap;}
}
/* S9: the board is calmer - the quick add appears in the column under the
   pointer (or with the focus) - and says it moves sideways: the right edge
   fades while there is more, with ‹ › to move. */
.pmportal .pp-kb-wrap{position:relative;}
.pmportal .pp-kb-wrap .kb-quickadd{transition:opacity .12s;}
@media (hover:hover){
  .pmportal .pp-kb-wrap .kb-col:not(:hover):not(:focus-within) .kb-quickadd{opacity:0;}
}
@media (hover:none){.pmportal .pp-kb-wrap .kb-quickadd{display:none;}}
.pmportal .pp-kb-wrap.pp-kb-more-r .kb-scroll{-webkit-mask-image:linear-gradient(to right,#000 calc(100% - 40px),transparent);
  mask-image:linear-gradient(to right,#000 calc(100% - 40px),transparent);}
.pmportal .pp-kb-nav{display:flex;align-items:center;gap:6px;justify-content:flex-end;padding:4px 8px;}
.pmportal .pp-kb-nav .pp-kb-chips{display:none;}
.pmportal .pp-kb-prev,.pmportal .pp-kb-next{width:30px;height:28px;border:1px solid var(--bl-line);border-radius:var(--bl-radius-s);
  background:var(--bl-box);color:var(--bl-sub);cursor:pointer;font-size:15px;line-height:1;}
.pmportal .pp-kb-prev:hover:not([disabled]),.pmportal .pp-kb-next:hover:not([disabled]){color:var(--bl-accent-d);border-color:var(--bl-accent);}
.pmportal .pp-kb-prev[disabled],.pmportal .pp-kb-next[disabled]{opacity:.35;cursor:default;}
.pmportal .pp-kb-wrap:not(.pp-kb-more-r):not(.pp-kb-more-l) .pp-kb-nav{display:none;}
/* M4: a phone shows one status column at a time - the columns snap, and
   the chips above jump to one. Only inside the portal (Exment's own kanban
   page is left as it is). */
@media (max-width:760px){
  .pmportal .pp-kb-nav{justify-content:flex-start;padding:4px 4px 6px;}
  .pmportal .pp-kb-nav .pp-kb-chips{display:flex;flex:1 1 auto;min-width:0;gap:6px;overflow-x:auto;scrollbar-width:none;}
  .pmportal .pp-kb-nav .pp-kb-chips::-webkit-scrollbar{display:none;}
  .pmportal .pp-kb-prev,.pmportal .pp-kb-next{display:none;}
  .pmportal .pp-kb-wrap:not(.pp-kb-more-r):not(.pp-kb-more-l) .pp-kb-nav{display:flex;}
  .pmportal .pp-kb-chip{flex:none;display:inline-flex;align-items:center;gap:5px;min-height:32px;padding:0 11px;border:1px solid var(--bl-line);
    border-radius:16px;background:var(--bl-box);color:var(--bl-sub);font-size:12.5px;font-weight:600;white-space:nowrap;cursor:pointer;}
  .pmportal .pp-kb-chip .n{font-size:11px;color:var(--bl-faint);}
  .pmportal .pp-kb-chip.on{border-color:var(--bl-accent);color:var(--bl-accent-d);background:var(--bl-hover);}
  .pmportal .pp-kb-wrap .kb-scroll{scroll-snap-type:x mandatory;-webkit-overflow-scrolling:touch;}
  .pmportal .pp-kb-wrap .kb-col{scroll-snap-align:start;width:calc(100vw - 72px);min-width:calc(100vw - 72px);max-width:calc(100vw - 72px);}
}
/* M8: the status, pressed to pick another */
.pmportal .pp-st-btn{display:inline-flex;align-items:center;gap:4px;border:0;background:none;padding:2px 4px;margin:-2px -4px;
  border-radius:12px;cursor:pointer;}
.pmportal .pp-st-btn .fa-caret-down{font-size:11px;color:var(--bl-faint);}
.pmportal .pp-st-btn:hover,.pmportal .pp-st-btn[aria-expanded="true"]{background:var(--bl-hover);}
.pmportal .pp-st-btn:focus-visible{outline:2px solid var(--bl-accent);outline-offset:1px;}
.pmportal .pp-st-menu{position:absolute;z-index:45;margin-top:4px;min-width:180px;padding:4px 0;background:var(--bl-box);
  border:1px solid var(--bl-line);border-radius:var(--bl-radius);box-shadow:var(--bl-shadow-m);}
.pmportal .bl-dsum .it,.pmportal .bl-props .row .v{position:relative;}
.pmportal .bl-props .row .v > .pp-st-menu,.pmportal .bl-dsum .it > .pp-st-menu{top:100%;left:0;}
.pmportal .pp-st-menu button{display:flex;align-items:center;gap:8px;width:100%;padding:7px 14px;border:0;background:none;
  font-size:13px;color:var(--bl-ink);text-align:left;cursor:pointer;}
.pmportal .pp-st-menu button:hover:not([disabled]),.pmportal .pp-st-menu button:focus-visible{background:var(--bl-hover);outline:0;}
.pmportal .pp-st-menu button[disabled]{color:var(--bl-sub);cursor:default;font-weight:700;}
.pmportal .pp-st-menu .dot{width:9px;height:9px;border-radius:50%;flex:none;}
.pmportal .pp-st-menu .fa-check{margin-left:auto;color:var(--bl-accent-d);}
/* ==== UX-F: keys, empty states, recent updates (S15, S16, M5, M10) ==== */
/* S16: the row j / k are on */
.pmportal #pp-issue-rows tr.pp-kbd-cur > td{background:var(--bl-hover);}
.pmportal #pp-issue-rows tr.pp-kbd-cur > td:first-child{box-shadow:inset 3px 0 0 var(--bl-accent);}
/* S16: the list of keys (?) */
.pmportal .pp-keyhelp{position:fixed;inset:0;z-index:1090;display:flex;align-items:center;justify-content:center;
  background:rgba(15,23,32,.35);padding:16px;}
.pmportal .pp-keyhelp .in{width:min(560px,100%);max-height:calc(100vh - 32px);overflow:auto;background:var(--bl-box);
  border-radius:var(--bl-radius);border:1px solid var(--bl-line);box-shadow:var(--bl-shadow-m);padding:0 0 12px;}
.pmportal .pp-keyhelp .hd{display:flex;align-items:center;padding:12px 16px;border-bottom:1px solid var(--bl-line2);font-weight:700;font-size:14px;}
.pmportal .pp-keyhelp .hd .x{margin-left:auto;border:0;background:none;font-size:20px;line-height:1;color:var(--bl-sub);cursor:pointer;}
.pmportal .pp-keyhelp .grp{padding:8px 16px 0;}
.pmportal .pp-keyhelp .gt{font-size:11.5px;font-weight:700;color:var(--bl-sub);margin-bottom:4px;}
.pmportal .pp-keyhelp .kr{display:flex;align-items:center;gap:6px;padding:3px 0;font-size:13px;}
.pmportal .pp-keyhelp .kr span{margin-left:8px;color:var(--bl-ink);}
.pmportal .pp-keyhelp kbd{display:inline-block;min-width:22px;padding:1px 6px;border:1px solid var(--bl-line);border-bottom-width:2px;
  border-radius:4px;background:var(--bl-box2);color:var(--bl-ink);font:600 12px/1.5 Menlo,Consolas,monospace;text-align:center;box-shadow:none;}
/* S15: もっと見る under the recent updates */
.pmportal .pp-feed-more{display:block;width:100%;margin-top:8px;}
/* M10: what to do when there is nothing yet */
.pmportal .pp-home-empty{margin-bottom:12px;padding:14px 16px;background:var(--bl-box);border:1px solid var(--bl-line);border-radius:var(--bl-radius);}
.pmportal .pp-home-empty[hidden]{display:none;}
.pmportal .pp-home-empty .t{font-weight:700;margin-bottom:8px;}
.pmportal .pp-home-empty .t .fa{color:var(--bl-amber);margin-right:4px;}
.pmportal .pp-home-empty ol{display:flex;flex-wrap:wrap;gap:8px;margin:0;padding:0 0 0 18px;}
.pmportal .pp-home-empty li{padding-left:2px;}
.pmportal .pp-empty-row td{padding:18px 12px;text-align:center;color:var(--bl-sub);}
.pmportal .pp-empty-row .bl-btn-sub{margin-left:8px;}
.pmportal .pp-none-list{margin-left:8px;font-weight:700;color:var(--bl-accent-d);}
/* ==== UX-H: the issue form (S6, M11) ==== */
/* S6: on a phone the buttons of the form's head go to a line of their own,
   so 保存 is never pushed off the right edge. */
@media (max-width:560px){
  .pp-nedit .pp-ne-head-top{flex-wrap:wrap;row-gap:8px;}
  .pp-nedit .pp-ne-head-actions{margin-left:0;flex:1 1 100%;flex-wrap:wrap;justify-content:flex-end;gap:6px;}
  .pp-nedit .pp-ne-head-actions .bl-btn-main,.pp-nedit .pp-ne-head-actions .bl-btn-sub{min-width:0;}
  .pp-nedit .pp-ne-head-actions .pp-ne-mail{margin-right:auto;}
}
/* M11: the fold of the other fields can be searched */
.pp-nedit .pp-ne-more-find{grid-column:1 / -1;width:100%;max-width:320px;box-sizing:border-box;margin:0 0 6px;padding:5px 10px;
  border:1px solid var(--bl-line);border-radius:var(--bl-radius-s,4px);font-size:12.5px;}
.pp-nedit .pp-ne-more-find:focus{border-color:var(--bl-accent);outline:none;box-shadow:var(--bl-focus);}
.pp-nedit .pp-ne-more .pp-ne-nomatch{display:none !important;}
</style>
