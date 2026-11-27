@php
    use App\Plugins\ExmentPM\PortalDefinition;

    $tt = function ($key, $args = []) {
        return PortalDefinition::trans('top.' . $key, $args);
    };

    // The launch row a project grows on hover - the same list the
    // dashboard panel offers, so both name the portal's tabs alike.
    $topbarLaunch = [
        ['new', PortalDefinition::trans('add_issue')],
        ['issues', PortalDefinition::trans('tab_issues')],
        ['board', PortalDefinition::trans('tab_board')],
        ['gantt', PortalDefinition::trans('tab_gantt')],
        ['burndown', PortalDefinition::trans('tab_burndown')],
        ['wiki', PortalDefinition::trans('tab_wiki')],
        ['files', PortalDefinition::trans('tab_files')],
    ];

    // Built here rather than inline in @json(): Blade 12's bracket
    // validator rejects a multi-line array inside directive parentheses.
    $topbarLang = [
        'searching' => $tt('searching'),
        'ntc_none' => PortalDefinition::trans('ntc.none'),
        'ntc_fail' => PortalDefinition::trans('ntc.fail'),
        'no_results' => $tt('no_results'),
        'more_results' => $tt('more_results'),
        'no_recent' => $tt('no_recent'),
        'no_projects' => $tt('no_projects'),
        'pj_loading' => $tt('pj_loading'),
        'pj_no_match' => $tt('pj_no_match'),
        'pj_settings' => $tt('pj_settings'),
        'pj_open' => $tt('pj_open'),
        'pj_overdue' => $tt('pj_overdue'),
        'pj_mine' => $tt('pj_mine'),
        'pj_progress' => $tt('pj_progress'),
        'pj_star_on' => $tt('pj_star_on'),
        'pj_star_off' => $tt('pj_star_off'),
        'pj_parent' => $tt('pj_parent'),
        'archived_tag' => PortalDefinition::trans('archived_tag'),
        'fold_open' => PortalDefinition::trans('fold_open'),
        'fold_close' => PortalDefinition::trans('fold_close'),
        'pj_drag' => PortalDefinition::trans('drag_sort'),
        'launch' => $topbarLaunch,
    ];

    // UX-D: the project open now (its name on the phone's bar, and its
    // own 課題の追加), and whether it may be added to.
    $topbarCur = null;
    foreach ($topbar['projects'] as $p) {
        if ($topbar['current'] !== null && $p['id'] === $topbar['current']) { $topbarCur = $p; }
    }
    $topbarCurAdd = $topbarCur !== null && !$topbarCur['archived'] && !empty($topbarCur['can_add']);
@endphp

{{-- Global bar shared by every portal page - Backlog's top navigation.
     Colors read the SPA theme variables and fall back to the portal
     defaults, so the same file serves the themed project page and the
     unthemed dashboard alike. --}}
<style>
.pp-top{display:flex;align-items:stretch;gap:2px;flex-wrap:wrap;position:relative;z-index:60;
    background:var(--bl-box,#fff);border:1px solid var(--bl-line,#dfe5ea);border-radius:8px;
    margin-bottom:10px;padding:0 8px;font-size:13px;color:var(--bl-ink,#33434f);}
.pp-top a{text-decoration:none;color:inherit;}
.pp-top .pp-top-home,.pp-top .pp-top-dd>button{display:flex;align-items:center;gap:7px;padding:0 13px;
    border:0;background:none;font:inherit;color:inherit;cursor:pointer;white-space:nowrap;height:44px;}
.pp-top .pp-top-home:hover,.pp-top .pp-top-dd>button:hover{background:var(--bl-hover,var(--exm-ui-accent-soft,#f2f8f5));}
.pp-top .pp-top-home.on{box-shadow:inset 0 -3px 0 var(--bl-accent,var(--exm-ui-accent,#4caf93));font-weight:700;}
.pp-top .pp-top-dd{position:relative;display:flex;}
.pp-top .pp-top-upgrade{display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:14px;
    background:var(--bl-amber,#eda62a);color:#fff;font-size:12px;font-weight:700;text-decoration:none;white-space:nowrap;}
.pp-top .pp-top-upgrade:hover{color:#fff;text-decoration:none;filter:brightness(.95);}
.pp-top .fa-caret-down{color:var(--bl-sub,#8a97a3);font-size:11px;}
.pp-top .pp-top-menu{position:absolute;top:100%;left:0;min-width:270px;max-width:480px;max-height:420px;overflow-y:auto;
    overscroll-behavior:contain;background:var(--bl-box,#fff);border:1px solid var(--bl-line,#dfe5ea);
    border-radius:0 0 8px 8px;box-shadow:0 8px 22px rgba(23,31,37,.16);z-index:61;}
.pp-top .pp-top-menu-r{left:auto;right:0;}
.pp-top .pp-top-mi{display:flex;align-items:center;gap:8px;padding:8px 13px;border-bottom:1px solid var(--bl-line,#eef2f5);
    font-size:12.5px;}
.pp-top .pp-top-mi:last-child{border-bottom:0;}
.pp-top .pp-top-mi:hover,.pp-top .pp-top-mi:focus{background:var(--bl-hover,var(--exm-ui-accent-soft,#f2f8f5));}
.pp-top .pp-top-mi.cur{font-weight:700;background:var(--bl-chip,#eef3f6);}
.pp-top .pp-top-mi .fa{color:var(--bl-sub,#8a97a3);width:14px;text-align:center;flex:none;}
.pp-top .pp-top-mi .fa.st{color:#eda62a;}
.pp-top .pp-top-mi .nm{flex:1 1 auto;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.pp-top .pp-top-mi .cd{flex:none;background:var(--bl-chip,#eef3f6);border-radius:3px;padding:0 6px;font-size:10.5px;
    font-weight:700;color:var(--bl-sub,#51616f);font-family:Menlo,Consolas,monospace;}
.pp-top .pp-top-mi .pj{flex:none;max-width:110px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;
    font-size:10.5px;color:var(--bl-sub,#8a97a3);}
.pp-top .pp-top-mi .ar{flex:none;background:#7f8c8d;color:#fff;border-radius:20px;padding:0 7px;font-size:10px;}
.pp-top .pp-top-none{padding:12px 14px;color:var(--bl-sub,#8a97a3);font-size:12px;}
.pp-top .pp-top-gh{padding:9px 13px 5px;color:var(--bl-sub,#8a97a3);font-size:11.5px;font-weight:700;}
.pp-top .pp-top-gear .pp-top-mi.pp-top-settings{border-top:1px solid var(--bl-line,#eef2f5);}

/* project menu - the dashboard's project panel, in a dropdown ---------- */
/* The header, the filter box and プロジェクトの追加 stay put; only the
   list moves, so the box you are typing in never scrolls away. */
/* Wide enough for a long name and key on one line (snoro 2026-11-21:
   names were cut off at 720px). */
.pp-top .pp-top-pjmenu{width:980px;min-width:0;max-width:calc(100vw - 32px);
    max-height:none;overflow:hidden;}
/* As tall as the window allows: the script sets the exact height when the
   menu opens; this is what holds until then (and without javascript). */
.pp-top .pp-pjm-list{max-height:calc(100vh - 260px);min-height:120px;overflow-y:auto;overscroll-behavior:contain;}
.pp-top .pp-pjm-head{display:flex;align-items:center;gap:10px;padding:9px 14px;
    border-bottom:1px solid var(--bl-line,#dfe5ea);background:var(--bl-bg,#fbfcfd);}
.pp-top .pp-pjm-head .ti{font-weight:700;font-size:13px;}
.pp-top .pp-pjm-head .rt{margin-left:auto;font-size:11.5px;color:var(--bl-sub,#8a97a3);}
.pp-top .pp-pjm-head .rt a{color:var(--bl-accent-d,var(--exm-ui-accent-strong,#3d9a80));font-weight:700;cursor:pointer;}
.pp-top .pp-pjm-head .rt a:hover{text-decoration:underline;}
.pp-top .pp-pjm-sbox{display:flex;align-items:center;gap:8px;padding:6px 14px;
    border-bottom:1px solid var(--bl-line,#eef2f5);}
.pp-top .pp-pjm-sbox .fa-search{color:var(--bl-sub,#8a97a3);flex:none;}
.pp-top .pp-pjm-sbox input{flex:1 1 auto;min-width:0;border:0;background:none;color:inherit;
    font:inherit;font-size:12.5px;outline:none;padding:4px 0;}
/* type=search draws a clear cross of its own, and next to ours that is two
   crosses saying the same thing. Ours stays: it is the one with a label. */
.pp-top .pp-pjm-sbox input::-webkit-search-cancel-button{display:none;}
.pp-top .pp-pjm-sbox .cl{border:0;background:none;cursor:pointer;color:var(--bl-sub,#8a97a3);
    padding:0 3px;font-size:13px;line-height:1;}
.pp-top .pp-pjm-sbox .cl:hover{color:var(--bl-ink,#33434f);}
.pp-top .pp-pjm-list [hidden]{display:none;}
/* One project per line (snoro 2026-11-18): name, key and state on the left,
   progress and counts on the right. The launch links that used to replace
   the numbers on hover are gone - the row itself opens the project. */
.pp-top .pp-pjm-row{display:flex;gap:7px;align-items:center;padding:4px 12px 4px 14px;min-height:30px;
    border-bottom:1px solid var(--bl-line2,#eef2f5);cursor:pointer;}
.pp-top .pp-pjm-row:last-child{border-bottom:0;}
.pp-top .pp-pjm-row.arch{background:#f6f7f8;}
.pp-top .pp-pjm-row.kid .nm{font-weight:600;}
.pp-top .pp-pjm-tgl{border:0;background:none;padding:0 2px;width:18px;color:var(--bl-sub,#8a97a3);font-size:10px;cursor:pointer;flex:none;}
.pp-top .pp-pjm-tgl:hover{color:var(--bl-accent-d,var(--exm-ui-accent-strong,#3d9a80));}
.pp-top .pp-pjm-head .pp-pjm-fold{font-size:11.5px;color:var(--bl-sub,#8a97a3);}
.pp-top .pp-pjm-head .pp-pjm-fold a{color:var(--bl-accent-d,var(--exm-ui-accent-strong,#3d9a80));font-weight:700;cursor:pointer;}
.pp-top .pp-pjm-head .pp-pjm-fold a:hover{text-decoration:underline;}
.pp-top .pp-pjm-row.dragging{opacity:.45;}
.pp-top .pp-pjm-row.drop-before{box-shadow:inset 0 3px 0 var(--bl-accent,var(--exm-ui-accent,#4caf93));}
.pp-top .pp-pjm-row.drop-after{box-shadow:inset 0 -3px 0 var(--bl-accent,var(--exm-ui-accent,#4caf93));}
.pp-top .pp-pjm-row .mn{flex:1 1 auto;min-width:0;display:flex;align-items:center;gap:10px;}
.pp-top .pp-pjm-row .l1{flex:1 1 0;min-width:90px;display:flex;align-items:center;gap:6px;}
.pp-top .pp-pjm-row .nm{flex:0 1 auto;min-width:0;font-size:13px;font-weight:700;line-height:1.35;
    overflow-wrap:anywhere;}
.pp-top .pp-pjm-row .nm:hover{text-decoration:underline;}
.pp-top .pp-pjm-row.cur{background:var(--bl-chip,#eef3f6);box-shadow:inset 3px 0 0 var(--bl-accent,var(--exm-ui-accent,#4caf93));}
.pp-top .pp-pjm-row.cur .nm{color:var(--bl-accent-d,var(--exm-ui-accent-strong,#3d9a80));}
.pp-top .pp-pjm-row .up{flex:0 2 auto;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;
    font-size:10.5px;color:var(--bl-sub,#8a97a3);}
.pp-top .pp-pjm-row .cd{flex:none;width:170px;box-sizing:border-box;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;
    background:var(--bl-chip,#eef3f6);border-radius:3px;padding:0 6px;
    font-size:10.5px;font-weight:700;color:var(--bl-sub,#51616f);font-family:Menlo,Consolas,monospace;}
.pp-top .pp-pjm-row .cd.none{background:none;}
.pp-top .pp-pjm-row .ps{flex:none;width:52px;text-align:center;}
.pp-top .pp-pjm-row .pill{display:inline-block;color:#fff;border-radius:20px;padding:0 7px;font-size:10px;font-weight:700;line-height:1.6;}
.pp-top .pp-pjm-row .ar{flex:none;background:#7f8c8d;color:#fff;border-radius:20px;padding:0 7px;font-size:10px;}
.pp-top .pp-pjm-row .mt{flex:none;display:flex;align-items:center;gap:7px;font-size:11px;white-space:nowrap;
    color:var(--bl-sub,#8a97a3);font-variant-numeric:tabular-nums;}
.pp-top .pp-pjm-row .mt .bar{flex:none;width:44px;height:5px;border-radius:3px;background:#e6ecf0;overflow:hidden;}
.pp-top .pp-pjm-row .mt .bar i{display:block;height:100%;background:var(--bl-accent,var(--exm-ui-accent,#4caf93));}
.pp-top .pp-pjm-row .mt .pc{min-width:30px;text-align:right;}
.pp-top .pp-pjm-row .mt .op{min-width:62px;text-align:right;}
.pp-top .pp-pjm-row .mt .od{min-width:64px;}
.pp-top .pp-pjm-row .mt .mi{min-width:50px;}
.pp-top .pp-pjm-row .mt .od{color:var(--bl-red,#e5544b);font-weight:700;}
.pp-top .pp-pjm-row .mt .mi{color:var(--bl-accent-d,var(--exm-ui-accent-strong,#3d9a80));font-weight:700;}
.pp-top .pp-pjm-row:hover,.pp-top .pp-pjm-row:focus-within{background:var(--bl-hover,var(--exm-ui-accent-soft,#f2f8f5));}
.pp-top .pp-pjm-star{flex:none;border:0;background:none;cursor:pointer;padding:1px 2px;font-size:13px;
    color:#c9d2d9;line-height:1;}
.pp-top .pp-pjm-star:hover,.pp-top .pp-pjm-star[aria-pressed="true"]{color:var(--bl-amber,#eda62a);}
.pp-top .pp-pjm-gear{flex:none;color:#b3bec7;padding:0 2px;font-size:13px;}
.pp-top .pp-pjm-row:hover .pp-pjm-gear{color:var(--bl-sub,#8a97a3);}
.pp-top .pp-pjm-gear:hover,.pp-top .pp-pjm-row:hover .pp-pjm-gear:hover{color:var(--bl-accent-d,var(--exm-ui-accent-strong,#3d9a80));}
/* Narrow menu: the bar and the percentage go first, the counts stay. */
@media (max-width:560px){
    .pp-top .pp-pjm-row .mt .bar,.pp-top .pp-pjm-row .mt .pc{display:none;}
    .pp-top .pp-pjm-row .cd,.pp-top .pp-pjm-row .ps{display:none;}
    .pp-top .pp-pjm-row .mt .od:empty,.pp-top .pp-pjm-row .mt .mi:empty{display:none;}
}
.pp-top .pp-pjm-foot{border-top:1px solid var(--bl-line,#dfe5ea);}

/* search --------------------------------------------------------------- */
.pp-top .pp-top-search{position:relative;display:flex;align-items:center;gap:7px;flex:1 1 200px;max-width:340px;
    margin-left:auto;padding:0 4px;}
.pp-top .pp-top-search .fa-search{color:var(--bl-sub,#8a97a3);flex:none;}
.pp-top .pp-top-search input{flex:1 1 auto;min-width:0;border:1px solid var(--bl-line,#dfe5ea);border-radius:16px;
    background:var(--bl-bg,#f5f7f9);color:inherit;padding:5px 30px 5px 12px;font:inherit;font-size:12.5px;outline:none;}
.pp-top .pp-top-search input:focus{border-color:var(--bl-accent,var(--exm-ui-accent,#4caf93));background:var(--bl-box,#fff);}
.pp-top .pp-top-search .kbd{position:absolute;right:14px;border:1px solid var(--bl-line,#dfe5ea);border-radius:4px;
    padding:0 5px;font-size:10.5px;color:var(--bl-sub,#8a97a3);background:var(--bl-box,#fff);pointer-events:none;}
.pp-top .pp-top-res{top:calc(100% - 3px);left:0;right:0;min-width:0;max-width:none;border-radius:8px;}
/* At the box's own width a result kept about ten characters of its subject
   beside the project name. On a desktop the box sits at the right end of the
   bar, so the list can grow leftwards instead. */
@media (min-width: 992px) {
    .pp-top .pp-top-res{left:auto;width:480px;}
}

/* add issue ------------------------------------------------------------ */
.pp-top .pp-top-addwrap>button{background:var(--bl-accent-fill,var(--bl-accent,var(--exm-ui-accent,#4caf93)));color:#fff;border-radius:6px;height:32px;
    margin:6px 0 6px 6px;padding:0 13px;}
.pp-top .pp-top-addwrap>button:hover{background:var(--bl-accent-fill-d,var(--bl-accent-d,var(--exm-ui-accent-strong,#3d9a80)));}
.pp-top .pp-top-addwrap .fa-caret-down{color:rgba(255,255,255,.8);}

/* ==== UX-D ==== */
/* S5: inside a project, add to it at once; ▾ beside it for another one. */
.pp-top .pp-top-addwrap.split{align-items:center;margin:6px 0 6px 6px;}
.pp-top .pp-top-addwrap.split>.pp-top-addmain{display:flex;align-items:center;gap:7px;height:32px;padding:0 12px;
    background:var(--bl-accent-fill,var(--bl-accent,var(--exm-ui-accent,#4caf93)));color:#fff;border-radius:6px 0 0 6px;white-space:nowrap;font-weight:600;}
.pp-top .pp-top-addwrap.split>.pp-top-addmain:hover{background:var(--bl-accent-fill-d,var(--bl-accent-d,var(--exm-ui-accent-strong,#3d9a80)));color:#fff;}
.pp-top .pp-top-addwrap.split>button{margin:0;border-radius:0 6px 6px 0;padding:0 9px;
    border-left:1px solid rgba(255,255,255,.35);}
.pp-top .pp-top-addmain:focus-visible{outline:2px solid var(--bl-accent,var(--exm-ui-accent,#4caf93));outline-offset:1px;}
/* One line from 1200px, also with Exment's sidebar open: the search box
   gives up width first, and below 1600px the three quiet entries on the
   right (通知の受け取り方, 全体の設定, マニュアル) keep only their icons,
   their names in the tooltip. */
@media (min-width:1200px){
    .pp-top{flex-wrap:nowrap;}
    .pp-top .pp-top-search{flex:1 1 160px;min-width:110px;}
}
@media (min-width:761px) and (max-width:1599px){
    .pp-top .pp-top-gear>button>span{display:none;}
    .pp-top .pp-top-gear>button{padding:0 10px;}
}
/* ISS02939 item 6, step 1b: on a phone, Exment's line under its title (the page's description)
   gives way to the content, and the + of the bar is 44px. */
@media (max-width:760px){
    .content-header > h1 > small{display:none;}
    .content-header{padding-top:6px;padding-bottom:0;}
    .content-header > h1{font-size:18px;margin:0;}
    .pp-top .pp-top-addwrap>button,.pp-top .pp-top-addwrap.split>.pp-top-addmain{min-width:44px;min-height:40px;}
}
/* the phone-only controls */
.pp-top .pp-top-burger,.pp-top .pp-top-stgl,.pp-top .pp-top-curpj{display:none;}
.pp-top .pp-top-bell[hidden]{display:none;}
/* the bell's list (ISS02939 item 6) */
/* one bell per screen: inside the portal its own (the same unread count), Exment's hidden.
   This style is part of the portal's page: Exment's bell comes back on its other pages. */
.main-header .navbar-notify{display:none !important;}
.pp-top .pp-top-bell{position:relative;}
.pp-top .pp-top-bell .n{position:absolute;top:6px;right:3px;min-width:16px;padding:0 4px;border-radius:8px;
    background:var(--bl-red,#d9534f);color:#fff;font-size:10px;line-height:16px;text-align:center;}
.pp-top .pp-top-notices{width:380px;min-width:0;max-width:calc(100vw - 16px);max-height:520px;padding:0;display:flex;flex-direction:column;}
.pp-top .pp-top-notices[hidden]{display:none;}
.pp-top .pp-ntc-head{display:flex;align-items:center;gap:8px;padding:8px 12px;border-bottom:1px solid var(--bl-line,#e3e8ec);}
.pp-top .pp-ntc-head .t{font-weight:700;flex:1;}
.pp-top .pp-ntc-head button{border:0;background:none;color:var(--bl-accent-d,#3d9a80);cursor:pointer;min-height:36px;padding:0 6px;font-size:12px;}
.pp-top .pp-ntc-close{display:none;}
.pp-top .pp-ntc-list{overflow-y:auto;flex:1;}
.pp-top .pp-ntc{display:flex;gap:8px;align-items:flex-start;padding:10px 12px;border-bottom:1px solid var(--bl-line,#eef1f3);color:inherit;text-decoration:none;min-height:44px;box-sizing:border-box;}
.pp-top .pp-ntc:hover{background:var(--bl-hover,#f2f8f5);}
.pp-top .pp-ntc .dot{flex:none;width:8px;height:8px;margin-top:6px;border-radius:50%;background:transparent;}
.pp-top .pp-ntc.unread .dot{background:var(--bl-accent,#4caf93);}
.pp-top .pp-ntc.unread .s{font-weight:700;}
.pp-top .pp-ntc .b{min-width:0;flex:1;}
.pp-top .pp-ntc .s{display:block;overflow-wrap:anywhere;line-height:1.4;}
.pp-top .pp-ntc .at{display:block;font-size:11px;color:var(--bl-faint,#8a97a3);margin-top:2px;}
.pp-top .pp-ntc-all{display:block;padding:10px 12px;text-align:center;font-size:12px;border-top:1px solid var(--bl-line,#e3e8ec);}
/* M2: a phone keeps one line - ☰, the project, search, the bell, add. */
@media (max-width:760px){
    .pp-top{padding:0 4px;gap:0;align-items:center;}
    .pp-top .pp-top-burger,.pp-top .pp-top-stgl,.pp-top .pp-top-bell{display:flex;align-items:center;justify-content:center;
        flex:none;width:44px;height:44px;border:0;background:none;color:inherit;font-size:16px;cursor:pointer;padding:0;}
    .pp-top .pp-top-bell[hidden]{display:none;}
    /* the bell's list on a phone: the whole screen, above the home bar */
    .pp-top .pp-top-belldd{order:3;flex:none;}
    .pp-top .pp-top-notices{position:fixed;top:0;left:0;right:0;bottom:0;width:auto;max-width:none;max-height:none;z-index:1100;border-radius:0;
        padding-bottom:env(safe-area-inset-bottom,0);padding-top:env(safe-area-inset-top,0);}
    .pp-top .pp-ntc-close{display:inline-flex;align-items:center;justify-content:center;min-width:44px;min-height:44px;color:var(--bl-sub,#6b7a88) !important;font-size:16px !important;}
    .pp-top .pp-ntc-head button{min-height:44px;}
    .pp-top .pp-ntc{padding:12px 16px;}
    /* the sheet sits in two stacking contexts (the bar, Exment's content): both rise while it is open,
       above Exment's header; and no gap at the right (the menus keep 32px from the edge) */
    .pp-top .pp-top-belldd .pp-top-notices{max-width:none;}
    .content-wrapper:has(.pp-top-notices:not([hidden])),.pp-top:has(.pp-top-notices:not([hidden])){z-index:1101;}
    .pp-top .pp-top-burger[aria-expanded="true"],.pp-top .pp-top-stgl[aria-expanded="true"]{color:var(--bl-accent-d,var(--exm-ui-accent-strong,#3d9a80));}
    .pp-top .pp-top-burger{order:0;}
    /* a basis of 0: a wrapping row breaks on the basis, before anything shrinks */
    .pp-top .pp-top-pjdd{order:1;flex:1 1 0;min-width:0;}
    .pp-top .pp-top-pjdd>button{flex:1 1 auto;min-width:0;max-width:100%;overflow:hidden;padding:0 8px;font-weight:700;}
    .pp-top .pp-top-pjdd .pp-top-curpj{display:block;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
    .pp-top .pp-top-pjdd>button:has(.pp-top-curpj) .pp-top-pjlbl{display:none;}
    .pp-top .pp-top-pjdd .fa-caret-down{flex:none;}
    .pp-top .pp-top-stgl{order:2;}
    .pp-top .pp-top-bell{order:3;}
    .pp-top .pp-top-addwrap{order:4;flex:none;}
    .pp-top .pp-top-addwrap,.pp-top .pp-top-addwrap.split{margin:6px 2px 6px 0;}
    .pp-top .pp-top-addwrap>button,.pp-top .pp-top-addwrap.split>.pp-top-addmain{margin:0;padding:0 10px;}
    .pp-top .pp-top-addwrap.split>button{margin:0;padding:0 6px;}
    .pp-top .pp-top-addwrap span{display:none;}
    .pp-top .pp-top-addwrap:not(.split)>button .fa-caret-down{display:none;}
    /* the rest: under the first line, one per row, while ☰ is open */
    .pp-top > .pp-top-home,.pp-top > .pp-top-dd:not(.pp-top-pjdd):not(.pp-top-addwrap):not(.pp-top-belldd),.pp-top > .pp-top-upgrade{display:none;order:10;}
    .pp-top.pp-top-more > .pp-top-home,.pp-top.pp-top-more > .pp-top-dd:not(.pp-top-pjdd):not(.pp-top-addwrap):not(.pp-top-belldd){display:flex;flex:1 1 100%;
        border-top:1px solid var(--bl-line2,#eef2f5);}
    .pp-top.pp-top-more > .pp-top-upgrade{display:inline-flex;margin:6px 8px;}
    .pp-top.pp-top-more > .pp-top-dd > button{flex:1 1 auto;justify-content:flex-start;}
    .pp-top.pp-top-more > .pp-top-dd > .pp-top-menu{left:0;right:0;max-width:none;}
    .pp-top .pp-top-home.on{box-shadow:inset 3px 0 0 var(--bl-accent,var(--exm-ui-accent,#4caf93));}
    /* search: on its own line while 🔍 is on */
    .pp-top > .pp-top-search{display:none;order:9;}
    .pp-top.pp-top-sopen > .pp-top-search{display:flex;flex:1 1 100%;max-width:none;margin:0 0 8px;}
    .pp-top .pp-top-search .kbd{display:none;}
    .pp-top .pp-top-pjmenu{left:-40px;width:calc(100vw - 40px);max-width:none;}
}
</style>

<nav class="pp-top" id="pp-top" data-search-url="{{ $topbar['search_url'] }}"
     data-projects-url="{{ $topbar['projects_url'] }}"
     data-project-table="{{ $topbar['project_table'] }}"
     data-current="{{ $topbar['current'] }}"
     aria-label="{{ PortalDefinition::trans('title') }}">
    {{-- M2: on a phone the bar is one line - this menu, the project, search,
         the bell and add. The menu shows the other entries below it. --}}
    <button type="button" class="pp-top-burger" aria-expanded="false" title="{{ PortalDefinition::trans('top_menu') }}" aria-label="{{ PortalDefinition::trans('top_menu') }}">
        <i class="fa fa-bars"></i>
    </button>
    <a class="pp-top-home {{ $topbar['current'] === null && ($topbar['page'] ?? '') !== 'ranking' ? 'on' : '' }}" href="{{ $topbar['dashboard_url'] }}">
        <i class="fa fa-home"></i><span>{{ $tt('dashboard') }}</span>
    </a>

    <div class="pp-top-dd pp-top-pjdd">
        <button type="button" aria-haspopup="true" aria-expanded="false">
            <i class="fa fa-folder-open-o"></i><span class="pp-top-pjlbl">{{ $tt('projects') }}</span>
            @if($topbarCur)<span class="pp-top-curpj">{{ $topbarCur['name'] }}</span>@endif
            <i class="fa fa-caret-down"></i>
        </button>
        <div class="pp-top-menu pp-top-pjmenu" data-projects hidden>
            <div class="pp-pjm-head">
                <span class="ti">{{ $tt('projects') }}</span>
                {{-- fold the project tree like the issue tree (snoro 2026-11-18) --}}
                <span class="pp-pjm-fold" id="pp-pjm-fold" hidden>
                    <a href="javascript:void(0);" id="pp-pjm-open">{{ PortalDefinition::trans('pj_open_all') }}</a> /
                    <a href="javascript:void(0);" id="pp-pjm-close">{{ PortalDefinition::trans('pj_close_all') }}</a>
                </span>
                {{-- Backlog's プロジェクト横断 corner. The portal's own
                     cross-project search is the box in this same bar, so the
                     link puts the cursor in it rather than opening a screen
                     that would only ask the same question again. --}}
                <span class="rt">{{ $tt('pj_cross') }}
                    <a href="javascript:void(0);" id="pp-pjm-find">{{ $tt('pj_find') }}</a></span>
            </div>
            <div class="pp-pjm-sbox">
                <i class="fa fa-search"></i>
                <input id="pp-pjm-q" type="search" autocomplete="off" spellcheck="false"
                       placeholder="{{ $tt('pj_search_ph') }}" aria-label="{{ $tt('pj_search_ph') }}">
                <button type="button" class="cl" id="pp-pjm-clear" hidden
                        aria-label="{{ $tt('pj_clear') }}" title="{{ $tt('pj_clear') }}">&times;</button>
            </div>
            {{-- Rendered here so the menu is never empty and still works
                 without javascript; the fetch swaps these for panel rows. --}}
            <div class="pp-pjm-list" id="pp-pjm-list">
                @forelse($topbar['projects'] as $p)
                <a class="pp-top-mi {{ $topbar['current'] === $p['id'] ? 'cur' : '' }}" href="{{ $p['url'] }}"
                   data-find="{{ mb_strtolower($p['name'] . ' ' . $p['key']) }}"
                   @if(!empty($p['depth'])) style="padding-left:{{ 13 + 22 * min(intval($p['depth']), 4) }}px" @endif
                   @if($topbar['current'] === $p['id']) aria-current="true" @endif>
                    <i class="fa {{ $p['starred'] ? 'fa-star st' : 'fa-folder-o' }}"></i>
                    <span class="nm">{{ $p['name'] }}</span>
                    @if($p['key'] !== '')<span class="cd">{{ $p['key'] }}</span>@endif
                    @if($p['archived'])<span class="ar">{{ PortalDefinition::trans('archived_tag') }}</span>@endif
                </a>
                @empty
                <div class="pp-top-none" data-find="">{{ $tt('no_projects') }}</div>
                @endforelse
            </div>
            @if(!empty($topbar['can_create']))
            {{-- Lands on the dashboard, whose add-project modal opens on
                 this hash; the dashboard page intercepts the click instead. --}}
            <a class="pp-top-mi pp-top-newpj pp-pjm-foot" href="{{ $topbar['dashboard_url'] }}#new-project">
                <i class="fa fa-plus"></i>
                <span class="nm">{{ $tt('add_project') }}</span>
            </a>
            @endif
        </div>
    </div>

    <a class="pp-top-home {{ ($topbar['page'] ?? '') === 'ranking' ? 'on' : '' }}" href="{{ $topbar['ranking_url'] ?? '' }}">
        <i class="fa fa-trophy"></i><span>{{ $tt('ranking') }}</span>
    </a>

    <div class="pp-top-dd">
        <button type="button" aria-haspopup="true" aria-expanded="false">
            <i class="fa fa-history"></i><span>{{ $tt('recent') }}</span><i class="fa fa-caret-down"></i>
        </button>
        {{-- Filled from localStorage on open: what this browser looked at
             lately, so the server neither knows nor cares. --}}
        <div class="pp-top-menu" data-recent hidden></div>
    </div>

    <button type="button" class="pp-top-stgl" aria-expanded="false" title="{{ $tt('search_ph') }}" aria-label="{{ $tt('search_ph') }}">
        <i class="fa fa-search"></i>
    </button>
    {{-- The bell (ISS02939 item 6): the person's own newest notices, a dropdown on a wide screen
         and a sheet on a phone, where Exment's list is a desktop table. A notice opens through
         Exment (marked read on the way). --}}
    <div class="pp-top-dd pp-top-belldd">
        <button type="button" class="pp-top-bell" aria-haspopup="true" aria-expanded="false" title="{{ PortalDefinition::trans('top_bell') }}" aria-label="{{ PortalDefinition::trans('top_bell') }}">
            <i class="fa fa-bell-o"></i><span class="n" hidden></span>
        </button>
        <div class="pp-top-menu pp-top-menu-r pp-top-notices" data-notices="{{ $topbar['notices_url'] ?? '' }}" hidden role="dialog" aria-label="{{ PortalDefinition::trans('top_bell') }}">
            <div class="pp-ntc-head">
                <span class="t">{{ PortalDefinition::trans('ntc.title') }}</span>
                <button type="button" class="pp-ntc-readall" hidden>{{ PortalDefinition::trans('ntc.read_all') }}</button>
                <button type="button" class="pp-ntc-close" aria-label="{{ PortalDefinition::trans('ntc.close') }}"><i class="fa fa-times"></i></button>
            </div>
            <div class="pp-ntc-list"><div class="pp-top-none">{{ PortalDefinition::trans('ntc.loading') }}</div></div>
            <a class="pp-ntc-all" href="{{ $topbar['notices_all_url'] ?? admin_url('notify_navbar') }}">{{ PortalDefinition::trans('ntc.all') }}</a>
        </div>
    </div>

    <div class="pp-top-search">
        <i class="fa fa-search"></i>
        <input id="pp-top-q" type="search" autocomplete="off" spellcheck="false"
               placeholder="{{ $tt('search_ph') }}" aria-label="{{ $tt('search_ph') }}">
        <span class="kbd">/</span>
        <div class="pp-top-menu pp-top-res" hidden></div>
    </div>

    @php
        $open = array_filter($topbar['projects'], function ($p) { return !$p['archived']; });
        $addable = array_filter($open, function ($p) { return !empty($p['can_add']); });
    @endphp
    {{-- Somebody who may file an issue into none of their projects is not
         offered a menu whose every entry would be refused. With no project
         at all the menu stays, to say so. --}}
    {{-- POS-1: inside a project its 課題の追加 is on the project's own header row --}}
    @if(($addable || !$open) && $topbarCur === null)
    {{-- S5: the one 課題の追加. Inside a project it adds to that project at
         once ("#new" is the route of its form); ▾ picks another. --}}
    <div class="pp-top-dd pp-top-addwrap{{ $topbarCurAdd ? ' split' : '' }}">
        @if($topbarCurAdd)
        <a class="pp-top-addmain" href="#new" title="{{ $tt('add_issue') }}: {{ $topbarCur['name'] }}">
            <i class="fa fa-plus"></i><span>{{ $tt('add_issue') }}</span>
        </a>
        <button type="button" aria-haspopup="true" aria-expanded="false" title="{{ PortalDefinition::trans('add_other') }}" aria-label="{{ PortalDefinition::trans('add_other') }}">
            <i class="fa fa-caret-down"></i>
        </button>
        @else
        <button type="button" aria-haspopup="true" aria-expanded="false">
            <i class="fa fa-plus"></i><span>{{ $tt('add_issue') }}</span><i class="fa fa-caret-down"></i>
        </button>
        @endif
        <div class="pp-top-menu pp-top-menu-r" hidden>
            @forelse($addable as $p)
            {{-- "#new" is the hash the SPA router answers with the create
                 modal; from another page it opens the project on it. --}}
            <a class="pp-top-mi" href="{{ $p['url'] }}#new">
                <i class="fa fa-plus"></i>
                <span class="nm">{{ $p['name'] }}</span>
                @if($p['key'] !== '')<span class="cd">{{ $p['key'] }}</span>@endif
            </a>
            @empty
            <div class="pp-top-none">{{ $tt('no_projects') }}</div>
            @endforelse
        </div>
    </div>
    @endif
    {{-- Button map: settings only under the gear. One menu holds how this user
         gets the notices (ISS02863), the settings of the whole portal (for
         administrators, ISS00256) and the manual (ISS02882). A project's own
         settings are under the gear beside its name. --}}
    <div class="pp-top-dd pp-top-gear">
        <button type="button" aria-haspopup="true" aria-expanded="false" title="{{ PortalDefinition::trans('top_gear_tip') }}">
            <i class="fa fa-cog"></i><span>{{ PortalDefinition::trans('top_gear') }}</span><i class="fa fa-caret-down"></i>
        </button>
        <div class="pp-top-menu pp-top-menu-r" hidden @if(!empty($topbar['notify_by_url'])) data-notify-by="{{ $topbar['notify_by_url'] }}" @endif>
            @if(!empty($topbar['notify_by_url']))
            {{-- S7: a setting (how the notices come), not the list of them - that is Exment's bell --}}
            <div class="pp-top-gh"><i class="fa fa-sliders"></i> {{ PortalDefinition::trans('teams.menu_title') }}</div>
            @foreach($topbar['notify_choices'] as $choice)
            <a class="pp-top-mi pp-notify-by" href="#" data-choice="{{ $choice }}" aria-checked="{{ $topbar['notify_by'] === $choice ? 'true' : 'false' }}" role="menuitemradio">
                <i class="fa {{ $topbar['notify_by'] === $choice ? 'fa-check' : 'fa-fw' }}"></i>
                <span class="nm">{{ PortalDefinition::trans('teams.choice_' . $choice) }}</span>
            </a>
            @endforeach
            @if(!empty($topbar['notify_settings_url']))
            <a class="pp-top-mi" href="{{ $topbar['notify_settings_url'] }}"><i class="fa fa-fw"></i><span class="nm">{{ PortalDefinition::trans('nset.menu') }}</span></a>
            @endif
            @endif
            @if(!empty($topbar['settings_url']))
            <a class="pp-top-mi pp-top-settings" href="{{ $topbar['settings_url'] }}" title="{{ PortalDefinition::trans('global_settings') }}">
                <i class="fa fa-cog"></i><span class="nm">{{ PortalDefinition::trans('global_settings') }}</span>
            </a>
            @endif
            {{-- ISS02939 item 6: add the portal to the home screen (shown when the browser can) --}}
            @if(empty($topbar['pwa']['off']))
            <a class="pp-top-mi pp-pwa-install" href="javascript:void(0);" hidden><i class="fa fa-mobile"></i><span class="nm">{{ PortalDefinition::trans('pwa.install') }}</span></a>
            @endif
            {{-- the manual of the plugin, in the language of the user (ISS02882) --}}
            <a class="pp-top-mi pp-top-manual" href="{{ \App\Plugins\ExmentPM\ManualLink::url() }}" target="_blank" rel="noopener" title="{{ \App\Plugins\ExmentPM\ManualLink::label() }}">
                <i class="fa fa-question-circle"></i><span class="nm">{{ \App\Plugins\ExmentPM\ManualLink::label() }}</span>
            </a>
        </div>
    </div>
    @if(!empty($topbar['settings_url']) && !empty($topbar['upgrade_needed']))
    <a class="pp-top-upgrade" href="{{ $topbar['settings_url'] }}" title="{{ $tt('upgrade_needed_tip') }}">
        <i class="fa fa-exclamation-circle"></i><span>{{ $tt('upgrade_needed') }}</span>
    </a>
    @endif
</nav>

<script>
(function () {
    var bar = document.getElementById('pp-top');
    if (!bar) { return; }

    // The admin layout declares no icon of its own, so the browser falls
    // back to /favicon.ico - and the file Laravel ships there is empty, so
    // nothing ever caches and every screen switch on these pages (a hash
    // change counts as a navigation) asks the server for it again. One
    // inline icon, declared once, ends the round trips.
    if (!document.querySelector('link[rel~="icon"]')) {
        var icon = document.createElement('link');
        icon.rel = 'icon';
        icon.type = 'image/svg+xml';
        icon.href = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E"
            + "%3Crect width='16' height='16' rx='3' fill='%234cae93'/%3E"
            + "%3Cpath d='M3.5 4.5h9v2h-9zm0 3h9v2h-9zm0 3h5.5v2h-5.5z' fill='%23ffffff'/%3E%3C/svg%3E";
        document.head.appendChild(icon);
    }

    // Text on the site's accent (保存, 送信, 「← 課題一覧」, 「＋ 課題を追加」): white on the
    // classic green accent is 2.67:1, under the 4.5:1 normal text needs (WCAG AA). When
    // the accent is too light for the text on it, those buttons are filled with a shade
    // of it just dark enough (--bl-accent-fill, and --bl-accent-fill-d under the pointer);
    // an accent that reads well already (Modern blue) is left as it is, and so is
    // everything else drawn in the accent (borders, lines, links).
    function ppRgb(s) {
        var m = String(s || '').match(/^rgba?\(\s*([\d.]+)[,\s]+([\d.]+)[,\s]+([\d.]+)/);
        return m ? [Number(m[1]), Number(m[2]), Number(m[3])] : null;
    }
    function ppLum(c) {
        var ch = c.map(function (v) {
            v = v / 255;
            return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
        });
        return 0.2126 * ch[0] + 0.7152 * ch[1] + 0.0722 * ch[2];
    }
    function ppContrast(a, b) {
        var x = ppLum(a), y = ppLum(b);
        return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05);
    }
    // null: the accent is fine as it is. Else the fill and its hover, mixed toward
    // black under light text (toward white under dark text) in small steps.
    function ppAccentFill(accent, text) {
        if (ppContrast(accent, text) >= 4.5) { return null; }
        var to = ppLum(text) > 0.4 ? 0 : 255;
        var mix = function (t) { return accent.map(function (v) { return Math.round(v + (to - v) * t); }); };
        for (var i = 1; i <= 50; i++) {
            var c = mix(i / 50);
            if (ppContrast(c, text) >= 4.5) { return { fill: c, hover: mix(Math.min(1, i / 50 + 0.12)) }; }
        }
        return null;
    }
    function ppAccentFit(host) {
        var probe = document.createElement('span');
        probe.style.display = 'none';
        host.appendChild(probe);
        probe.style.color = 'var(--bl-accent,var(--exm-ui-accent,#4caf93))';
        var accent = ppRgb(window.getComputedStyle(probe).color);
        probe.style.color = 'var(--bl-on-accent,var(--exm-ui-on-accent,#fff))';
        var text = ppRgb(window.getComputedStyle(probe).color);
        host.removeChild(probe);
        var f = accent && text ? ppAccentFill(accent, text) : null;
        var html = document.documentElement.style;
        if (f) {
            html.setProperty('--bl-accent-fill', 'rgb(' + f.fill.join(',') + ')');
            html.setProperty('--bl-accent-fill-d', 'rgb(' + f.hover.join(',') + ')');
        } else {
            html.removeProperty('--bl-accent-fill');
            html.removeProperty('--bl-accent-fill-d');
        }
    }
    try {
        ppAccentFit(bar);
        var ppDarkMq = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;
        if (ppDarkMq && !window.__ppAccentMq) {
            window.__ppAccentMq = true;
            // once per window: a page swapped in by pjax has its own bar, looked up again
            var refit = function () { var b = document.getElementById('pp-top'); if (b) { ppAccentFit(b); } };
            if (ppDarkMq.addEventListener) { ppDarkMq.addEventListener('change', refit); } else if (ppDarkMq.addListener) { ppDarkMq.addListener(refit); }
        }
    } catch (e) { /* the accent as it is */ }

    // ISS02939 item 6: the portal as an installable web app. The manifest and the worker are
    // routes of the plugin, so their scope is the portal only; taken off again when it is off.
    (function (P) {
        if (!P || !('serviceWorker' in navigator)) { return; }
        if (P.off) {
            navigator.serviceWorker.getRegistrations().then(function (list) {
                list.forEach(function (r) { if (r.scope === P.scope) { r.unregister(); } });
            }).catch(function () {});
            return;
        }
        var head = function (rel, href, extra) {
            if (document.querySelector('link[rel="' + rel + '"]')) { return; }
            var l = document.createElement('link');
            l.rel = rel;
            l.href = href;
            if (extra) { Object.keys(extra).forEach(function (k) { l.setAttribute(k, extra[k]); }); }
            document.head.appendChild(l);
        };
        // the manifest is behind the login: the browser fetches it without cookies unless told so
        head('manifest', P.manifest, { crossorigin: 'use-credentials' });
        head('apple-touch-icon', P.icon);
        if (!document.querySelector('meta[name="theme-color"]')) {
            var m = document.createElement('meta');
            m.name = 'theme-color';
            m.content = P.theme;
            document.head.appendChild(m);
        }
        navigator.serviceWorker.register(P.sw, { scope: P.scope }).catch(function () {});
        // 「ホーム画面に追加」: the browser's own prompt when it offered one, else how to on iOS
        var btn = document.querySelector('.pp-pwa-install');
        var standalone = window.matchMedia && window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
        if (!btn || standalone) { return; }
        var ios = /iphone|ipad|ipod/i.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
        if (ios) { btn.hidden = false; }
        window.addEventListener('beforeinstallprompt', function (ev) {
            ev.preventDefault();
            window.__ppInstall = ev;
            btn.hidden = false;
        });
        btn.addEventListener('click', function () {
            var ev = window.__ppInstall;
            if (ev) {
                ev.prompt();
                ev.userChoice.then(function () { window.__ppInstall = null; btn.hidden = true; }).catch(function () {});
            } else {
                window.alert(@json(PortalDefinition::trans('pwa.ios_help')));
            }
        });
    })(@json($topbar['pwa'] ?? null));

    var LANG = @json($topbarLang);
    // The issue screen keeps 最近見た per person under this key (recentPush).
    var RECENT_KEY = @json('pp_recent.u' . intval(\Exment::getUserId()));

    function span(cls, text) {
        var s = document.createElement('span');
        s.className = cls;
        s.textContent = String(text == null ? '' : text);
        return s;
    }

    // ---- the notices: mail / Teams (ISS02863) ---------------------------
    bar.querySelectorAll('.pp-notify-by').forEach(function (item) {
        item.addEventListener('click', function (ev) {
            ev.preventDefault();
            var menu = item.closest('[data-notify-by]');
            var token = document.querySelector('meta[name="csrf-token"]');
            fetch(menu.getAttribute('data-notify-by'), {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': token ? token.getAttribute('content') : '',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ notify_by: item.getAttribute('data-choice') })
            }).then(function (r) {
                if (!r.ok) { throw new Error(r.status); }
                menu.querySelectorAll('.pp-notify-by').forEach(function (other) {
                    var on = other === item;
                    other.setAttribute('aria-checked', on ? 'true' : 'false');
                    var icon = other.querySelector('i');
                    if (icon) { icon.className = 'fa ' + (on ? 'fa-check' : 'fa-fw'); }
                });
                if (window.toastr) { window.toastr.success({!! json_encode(strval(PortalDefinition::trans('teams.saved'))) !!}); }
            }).catch(function () {
                if (window.toastr) { window.toastr.error('Error'); }
            });
        });
    });

    // ---- dropdowns ----------------------------------------------------
    function closeMenus() {
        bar.querySelectorAll('.pp-top-dd').forEach(function (dd) {
            var menu = dd.querySelector('.pp-top-menu');
            var btn = dd.querySelector('button');
            if (menu) { menu.hidden = true; }
            if (btn) { btn.setAttribute('aria-expanded', 'false'); }
        });
    }
    function closeAll() {
        closeMenus();
        hideResults();
    }
    // A chosen menu entry may only change the hash of the page already
    // open, and then nothing reloads - so the menu closes itself.
    bar.addEventListener('click', function (ev) {
        if (ev.target.closest('.pp-top-menu a')) { closeAll(); }
    });
    bar.querySelectorAll('.pp-top-dd > button').forEach(function (btn) {
        // Start fetching the project list as soon as the pointer is on its
        // button, so the answer is usually there by the time of the click.
        if (btn.parentElement.querySelector('.pp-top-menu[data-projects]')) {
            ['pointerenter', 'focus', 'touchstart'].forEach(function (type) {
                btn.addEventListener(type, function () { loadProjects(); }, { passive: true });
            });
        }
        btn.addEventListener('click', function (ev) {
            ev.stopPropagation();
            var menu = btn.parentElement.querySelector('.pp-top-menu');
            var wasOpen = menu && !menu.hidden;
            closeAll();
            if (menu && !wasOpen) {
                if (menu.hasAttribute('data-recent')) { fillRecent(menu); }
                if (menu.hasAttribute('data-notices')) { loadNotices(menu); }
                menu.hidden = false;
                btn.setAttribute('aria-expanded', 'true');
                if (menu.hasAttribute('data-projects')) {
                    fitProjectList();
                    loadProjects();
                    // Backlog opens this menu with the cursor already in the
                    // filter box, which is what makes it a switcher rather
                    // than a list you have to aim at.
                    if (pjQ) { pjQ.focus(); pjQ.select(); }
                }
            }
        });
    });
    // The answer is usually back before the menu is asked for, so opening it
    // shows the panel rather than a spinner.
    var pjMenu = bar.querySelector('[data-projects]');
    if (pjMenu) {
        pjMenu.parentElement.querySelector('button').addEventListener('mouseenter', loadProjects);
    }
    // pjax swaps the bar out but leaves these document listeners behind;
    // each new page binds its own set, so stale ones stand down here
    // instead of piling up one copy per page visited.
    function alive() { return document.getElementById('pp-top') === bar; }
    document.addEventListener('click', function onDocClick(ev) {
        if (!alive()) { document.removeEventListener('click', onDocClick); return; }
        if (!ev.target.closest('#pp-top')) { closeAll(); }
    });
    document.addEventListener('keydown', function onDocKey(ev) {
        if (!alive()) { document.removeEventListener('keydown', onDocKey); return; }
        if (ev.key === 'Escape') { closeAll(); }
    });

    // ---- UX-D: the phone's one-line bar (M2) ---------------------------
    // ☰ shows the entries a phone keeps out of the first line under it; 🔍
    // opens the search box on a line of its own.
    function flip(cls, btn, on) {
        on = on === undefined ? !bar.classList.contains(cls) : !!on;
        bar.classList.toggle(cls, on);
        if (btn) { btn.setAttribute('aria-expanded', on ? 'true' : 'false'); }
        return on;
    }
    var burger = bar.querySelector('.pp-top-burger');
    var stgl = bar.querySelector('.pp-top-stgl');
    if (burger) {
        burger.addEventListener('click', function (ev) {
            ev.stopPropagation();
            closeAll();
            flip('pp-top-sopen', stgl, false);
            flip('pp-top-more', burger);
        });
    }
    if (stgl) {
        stgl.addEventListener('click', function (ev) {
            ev.stopPropagation();
            flip('pp-top-more', burger, false);
            if (flip('pp-top-sopen', stgl) && q) { q.focus(); }
        });
    }
    // M3: the project's name opens this list, from the project page.
    window.ppTopOpenProjects = function () {
        if (!alive()) { return; }
        var btn = pjMenu ? pjMenu.parentElement.querySelector('button') : null;
        if (!btn) { return; }
        if (bar.getBoundingClientRect().top < 0 && bar.scrollIntoView) { bar.scrollIntoView({ block: 'start' }); }
        if (pjMenu.hidden) { btn.click(); }
    };
    // The bell (ISS02939 item 6): the person's own newest notices, in a menu (a sheet on a
    // phone). Its count follows Exment's bell while that one is on the page, and the list's
    // own count once the list was opened.
    var pmBell = bar.querySelector('.pp-top-bell');
    function setBellCount(count) {
        var n = pmBell ? pmBell.querySelector('.n') : null;
        count = String(count == null ? '' : count).trim();
        if (n) { n.textContent = count; n.hidden = count === '' || count === '0'; }
    }
    function syncBell() {
        if (!pmBell || !alive()) { return; }
        var ex = document.querySelector('.navbar-notify .container-notify');
        // Exment takes its badge off and puts it back with the new count; none means none unread
        if (ex) {
            var label = ex.querySelector('.label');
            setBellCount(label ? label.textContent : '');
        }
    }
    function loadNotices(menu) {
        var list = menu.querySelector('.pp-ntc-list');
        var readAll = menu.querySelector('.pp-ntc-readall');
        fetch(menu.getAttribute('data-notices'), { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (j) {
                if (!j || !j.ok) { throw new Error('notices'); }
                list.textContent = '';
                (j.items || []).forEach(function (it) {
                    var a = document.createElement('a');
                    a.className = 'pp-ntc' + (it.read ? '' : ' unread');
                    a.href = String(it.url || '');
                    var dot = document.createElement('span');
                    dot.className = 'dot';
                    var b = document.createElement('span');
                    b.className = 'b';
                    b.appendChild(span('s', it.subject));
                    b.appendChild(span('at', it.at));
                    a.appendChild(dot);
                    a.appendChild(b);
                    list.appendChild(a);
                });
                if (!(j.items || []).length) {
                    var d = document.createElement('div');
                    d.className = 'pp-top-none';
                    d.textContent = LANG.ntc_none;
                    list.appendChild(d);
                }
                setBellCount(j.unread);
                if (readAll) { readAll.hidden = !(j.unread > 0); }
            })
            .catch(function () {
                list.textContent = '';
                var d = document.createElement('div');
                d.className = 'pp-top-none';
                d.textContent = LANG.ntc_fail;
                list.appendChild(d);
            });
    }
    (function () {
        var menu = bar.querySelector('.pp-top-notices');
        if (!menu) { return; }
        // the menu's own buttons do not close it (the bar closes menus on a click elsewhere)
        menu.addEventListener('click', function (ev) {
            if (!ev.target.closest('a')) { ev.stopPropagation(); }
        });
        var close = menu.querySelector('.pp-ntc-close');
        if (close) { close.addEventListener('click', function () { closeAll(); }); }
        var readAll = menu.querySelector('.pp-ntc-readall');
        if (readAll) {
            readAll.addEventListener('click', function () {
                var token = document.querySelector('meta[name="csrf-token"]');
                readAll.disabled = true;
                fetch(menu.getAttribute('data-notices') + '/read-all', {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': token ? token.getAttribute('content') : '' }
                }).then(function (r) { return r.ok ? r.json() : null; }).then(function (j) {
                    readAll.disabled = false;
                    if (!j || !j.ok) { return; }
                    menu.querySelectorAll('.pp-ntc.unread').forEach(function (a) { a.classList.remove('unread'); });
                    readAll.hidden = true;
                    setBellCount(0);
                    var exLabel = document.querySelector('.navbar-notify .container-notify .label');
                    if (exLabel) { exLabel.textContent = ''; }
                }).catch(function () { readAll.disabled = false; });
            });
        }
    })();
    syncBell();
    (function () {
        var menu = bar.querySelector('.pp-top-notices');
        var url = menu ? menu.getAttribute('data-notices') : '';
        if (!url || !pmBell) { return; }
        fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (j) { if (j && j.ok && alive()) { setBellCount(j.unread); } })
            .catch(function () {});
    })();
    window.addEventListener('resize', function onBellResize() {
        if (!alive()) { window.removeEventListener('resize', onBellResize); return; }
        syncBell();
    });
    (function () {
        var li = document.querySelector('.navbar-notify');
        if (!li || !window.MutationObserver || !pmBell) { return; }
        var mo = new MutationObserver(function () { if (!alive()) { mo.disconnect(); return; } syncBell(); });
        mo.observe(li, { childList: true, subtree: true, characterData: true });
    })();

    // ---- recently viewed ----------------------------------------------
    // Same pp_recent record the dashboard tab reads, same rule: strings out
    // of localStorage go through textContent, never through markup.
    function fillRecent(menu) {
        var entries = [];
        try { entries = JSON.parse(localStorage.getItem(RECENT_KEY) || '[]'); } catch (e) { entries = []; }
        if (!Array.isArray(entries)) { entries = []; }
        menu.textContent = '';
        var shown = 0;
        entries.slice(0, 12).forEach(function (e) {
            if (!e || !e.u) { return; }
            var a = document.createElement('a');
            a.className = 'pp-top-mi';
            a.href = String(e.u);
            a.title = String(e.s || '');
            a.appendChild(span('cd', e.k));
            a.appendChild(span('nm', e.s));
            a.appendChild(span('pj', e.p));
            menu.appendChild(a);
            shown++;
        });
        if (!shown) {
            var d = document.createElement('div');
            d.className = 'pp-top-none';
            d.textContent = LANG.no_recent;
            menu.appendChild(d);
        }
    }

    // ---- project menu -------------------------------------------------
    // The dashboard's project panel, fetched once per page. Until it lands
    // the plain server rendered list stands in, so the menu is never empty
    // and a failed fetch costs nothing.
    var pjQ = document.getElementById('pp-pjm-q');
    var pjList = document.getElementById('pp-pjm-list');
    var pjClear = document.getElementById('pp-pjm-clear');
    var pjState = { done: false, busy: false, table: '' };

    // The menu used to open on the plain server-rendered list and redraw a
    // moment later, once the fetch landed - a flash of an unstyled list. The
    // last answer is kept for this tab (sessionStorage, 10 minutes) and drawn
    // straight away; the fetch still runs and redraws only if it differs.
    var PJ_CACHE_MS = 10 * 60 * 1000;
    function pjCacheKey(url) { return 'pp_pjm:' + url; }
    function pjCacheRead(url) {
        try {
            var hit = JSON.parse(sessionStorage.getItem(pjCacheKey(url)) || 'null');
            if (hit && hit.t && Date.now() - hit.t < PJ_CACHE_MS && hit.j && Array.isArray(hit.j.items)) { return hit; }
        } catch (e) { /* ignore */ }
        return null;
    }
    function pjApply(json) {
        pjState.table = String((json && json.table) || '');
        pjState.reorderUrl = String((json && json.reorder_url) || '');
        paintProjects((json && Array.isArray(json.items)) ? json.items : []);
    }
    function loadProjects() {
        if (pjState.done || pjState.busy || !pjList) { return; }
        var url = bar.getAttribute('data-projects-url');
        if (!url) { return; }
        var cached = pjState.painted ? null : pjCacheRead(url);
        if (cached) { pjApply(cached.j); pjState.painted = JSON.stringify(cached.j); }
        pjState.busy = true;
        fetch(url, {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (r) {
            if (!r.ok) { throw new Error(r.status); }
            return r.json();
        }).then(function (json) {
            pjState.done = true;
            var text = JSON.stringify(json || {});
            try { sessionStorage.setItem(pjCacheKey(url), JSON.stringify({ t: Date.now(), j: json })); } catch (e) { /* ignore */ }
            if (pjState.painted !== text) { pjApply(json); }
            pjState.painted = text;
        }).catch(function () {
            // Leave the plain list alone - it links to the same projects.
        }).then(function () {
            pjState.busy = false;
        });
    }

    function el(tag, cls, text) {
        var node = document.createElement(tag);
        if (cls) { node.className = cls; }
        if (text != null) { node.textContent = String(text); }
        return node;
    }
    function ico(cls) {
        var i = document.createElement('i');
        i.className = cls;
        return i;
    }
    function count(template, n) {
        return String(template).replace(':count', String(n));
    }

    function paintProjects(items) {
        var current = bar.getAttribute('data-current') || '';
        pjList.textContent = '';
        if (!items.length) {
            // data-find="" never matches a keyword, so typing hides this and
            // leaves only the "nothing matches" line rather than both.
            var none = el('div', 'pp-top-none', LANG.no_projects);
            none.setAttribute('data-find', '');
            pjList.appendChild(none);
        } else {
            items.forEach(function (p) {
                var r = projectRow(p, current);
                if (pjState.reorderUrl) { makeDraggable(r, p); }
                pjList.appendChild(r);
            });
        }
        addFoldToggles();
        filterProjects();
    }

    // ---- folding the project tree (▼/▶, すべて開く・すべて閉じる) ----
    var PJ_FOLD_KEY = 'pmportal.pjfold';
    var pjFold = {};
    try {
        var pjFoldSaved = JSON.parse(localStorage.getItem(PJ_FOLD_KEY) || '[]');
        if (Array.isArray(pjFoldSaved)) { pjFoldSaved.forEach(function (id) { pjFold[String(id)] = true; }); }
    } catch (e) { /* ignore */ }
    function pjFoldSave() {
        try { localStorage.setItem(PJ_FOLD_KEY, JSON.stringify(Object.keys(pjFold))); } catch (e) { /* ignore */ }
    }
    function pjRows() { return pjList ? pjList.querySelectorAll('.pp-pjm-row[data-id]') : []; }
    // The list runs down to the bottom of the window, less what sits under
    // it in the menu (the add-project line) and a little air.
    function fitProjectList() {
        if (!pjList) { return; }
        var menu = pjList.closest('.pp-top-menu');
        if (!menu || menu.hidden) { return; }
        var below = menu.getBoundingClientRect().bottom - pjList.getBoundingClientRect().bottom;
        var room = window.innerHeight - pjList.getBoundingClientRect().top - below - 12;
        pjList.style.maxHeight = Math.max(160, Math.floor(room)) + 'px';
    }
    window.addEventListener('resize', fitProjectList);
    function paintToggle(b) {
        var shut = !!pjFold[b.getAttribute('data-tgl')];
        b.textContent = (shut ? '▶' : '▼');
        b.setAttribute('aria-expanded', shut ? 'false' : 'true');
        b.title = shut ? LANG.fold_open : LANG.fold_close;
    }
    function addFoldToggles() {
        var kids = {};
        pjRows().forEach(function (r) {
            var pid = r.getAttribute('data-parent');
            if (pid && Number(r.getAttribute('data-depth') || 0) > 0) { kids[pid] = (kids[pid] || 0) + 1; }
        });
        var any = false;
        pjRows().forEach(function (r) {
            var id = r.getAttribute('data-id'), n = kids[id];
            if (!n) { r.removeAttribute('data-kids'); return; }
            any = true;
            r.setAttribute('data-kids', String(n));
            var l1 = r.querySelector('.l1');
            if (!l1 || l1.querySelector('.pp-pjm-tgl')) { return; }
            var b = el('button', 'pp-pjm-tgl');
            b.type = 'button';
            b.setAttribute('data-tgl', id);
            paintToggle(b);
            l1.insertBefore(b, l1.firstChild);
        });
        var ctl = document.getElementById('pp-pjm-fold');
        if (ctl) { ctl.hidden = !any; }
    }
    // rows under a folded parent; ignored while a keyword is typed, so a
    // search still finds a project whose parent is closed
    function foldHidden() {
        var hide = {}, cut = -1;
        pjRows().forEach(function (r) {
            var d = Number(r.getAttribute('data-depth') || 0);
            if (cut >= 0 && d > cut) { hide[r.getAttribute('data-id')] = true; return; }
            cut = (r.getAttribute('data-kids') && pjFold[r.getAttribute('data-id')]) ? d : -1;
        });
        return hide;
    }
    if (pjList) {
        pjList.addEventListener('click', function (ev) {
            var b = ev.target.closest('.pp-pjm-tgl');
            if (!b) { return; }
            ev.preventDefault();
            ev.stopPropagation();
            var id = b.getAttribute('data-tgl');
            if (pjFold[id]) { delete pjFold[id]; } else { pjFold[id] = true; }
            pjFoldSave();
            paintToggle(b);
            filterProjects();
        });
        // double-click a ▼/▶: open all or close all (see the issue list)
        pjList.addEventListener('dblclick', function (ev) {
            var b = ev.target.closest('.pp-pjm-tgl');
            if (!b) { return; }
            ev.preventDefault();
            ev.stopPropagation();
            var a = document.getElementById(pjFold[b.getAttribute('data-tgl')] ? 'pp-pjm-open' : 'pp-pjm-close');
            if (a) { a.click(); }
        });
    }
    // Switching project keeps the screen: on a project's 課題 (list or one
    // issue) the next project opens on its 課題 too, and the same for the
    // other tabs every project has. Only the plain project links are
    // changed - the launch links and the gear name their own screen.
    function keptScreen() {
        if (!document.querySelector('.bl-tab[data-scr]')) { return ''; }
        var path = String(location.hash || '').slice(1).split('?')[0];
        if (path === 'issues' || /^issue-/.test(path)) { return 'issues'; }
        if (/^wiki-/.test(path)) { return 'wiki'; }
        return ['board', 'gantt', 'burndown', 'wiki', 'files'].indexOf(path) >= 0 ? path : '';
    }
    function keepScreenOnLink(ev) {
        var a = ev.target.closest('a[href]');
        if (!a || !pjList || !pjList.contains(a) || a.closest('.lc') || a.classList.contains('pp-pjm-gear')) { return; }
        var base = a.getAttribute('data-base');
        if (base === null) {
            base = a.getAttribute('href') || '';
            if (base === '' || base.indexOf('#') >= 0 || base.indexOf('javascript:') === 0) { return; }
            a.setAttribute('data-base', base);
        }
        var screen = keptScreen();
        a.setAttribute('href', screen ? base + '#' + screen : base);
    }
    if (pjList) {
        // The whole line is the target, not just the name. Going through the
        // name's own click keeps keepScreenOnLink and the menu's close in play.
        pjList.addEventListener('click', function (ev) {
            if (ev.target.closest('a,button,input')) { return; }
            var row = ev.target.closest('.pp-pjm-row');
            var nm = row && row.querySelector('a.nm');
            if (nm) { nm.click(); }
        });
        pjList.addEventListener('click', keepScreenOnLink, true);
        pjList.addEventListener('auxclick', keepScreenOnLink, true);
    }
    ['pp-pjm-open', 'pp-pjm-close'].forEach(function (ctlId) {
        var a = document.getElementById(ctlId);
        if (!a) { return; }
        a.addEventListener('click', function (ev) {
            ev.preventDefault();
            ev.stopPropagation();
            pjFold = {};
            if (ctlId === 'pp-pjm-close') {
                pjRows().forEach(function (r) { if (r.getAttribute('data-kids')) { pjFold[r.getAttribute('data-id')] = true; } });
            }
            pjFoldSave();
            if (pjList) { pjList.querySelectorAll('.pp-pjm-tgl').forEach(paintToggle); }
            filterProjects();
        });
    });

    // ISS00263: administrators drag a project among its siblings (same parent);
    // its whole subtree moves with it. The new sibling order is saved through
    // lightapi/reorder (display_order 10, 20, 30...). On failure the menu is
    // reloaded so it never shows an order that was not saved.
    var drag = null;
    function subtree(row) {
        var rows = [row], d = Number(row.getAttribute('data-depth') || 0), n = row.nextElementSibling;
        while (n && Number(n.getAttribute('data-depth') || 0) > d) { rows.push(n); n = n.nextElementSibling; }
        return rows;
    }
    function makeDraggable(row, p) {
        row.setAttribute('draggable', 'true');
        row.setAttribute('data-id', String(p.id));
        row.setAttribute('data-parent', String(p.parent_id || ''));
        row.setAttribute('data-depth', String(Number(p.depth) || 0));
        row.title = LANG.pj_drag;
        row.addEventListener('dragstart', function (ev) {
            drag = row;
            row.classList.add('dragging');
            try { ev.dataTransfer.effectAllowed = 'move'; ev.dataTransfer.setData('text/plain', String(p.id)); } catch (e) { /* ignore */ }
        });
        row.addEventListener('dragend', function () {
            row.classList.remove('dragging');
            pjList.querySelectorAll('.drop-before,.drop-after').forEach(function (x) { x.classList.remove('drop-before', 'drop-after'); });
            drag = null;
        });
        row.addEventListener('dragover', function (ev) {
            if (!drag || drag === row || drag.getAttribute('data-parent') !== row.getAttribute('data-parent')) { return; }
            ev.preventDefault();
            var box = row.getBoundingClientRect(), after = ev.clientY > box.top + box.height / 2;
            row.classList.toggle('drop-after', after);
            row.classList.toggle('drop-before', !after);
        });
        row.addEventListener('dragleave', function () { row.classList.remove('drop-before', 'drop-after'); });
        row.addEventListener('drop', function (ev) {
            if (!drag || drag === row || drag.getAttribute('data-parent') !== row.getAttribute('data-parent')) { return; }
            ev.preventDefault();
            var after = row.classList.contains('drop-after');
            row.classList.remove('drop-before', 'drop-after');
            var moving = subtree(drag), anchor = after ? subtree(row) : [row];
            var ref = after ? anchor[anchor.length - 1].nextElementSibling : anchor[0];
            moving.forEach(function (m) { pjList.insertBefore(m, ref); });
            saveOrder(drag.getAttribute('data-parent'));
        });
    }
    function saveOrder(parentId) {
        var ids = [];
        pjList.querySelectorAll('.pp-pjm-row[data-id]').forEach(function (r) {
            if (r.getAttribute('data-parent') === parentId) { ids.push(Number(r.getAttribute('data-id'))); }
        });
        var token = document.querySelector('meta[name="csrf-token"]');
        fetch(pjState.reorderUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': token ? token.getAttribute('content') : '',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ ids: ids })
        }).then(function (r) {
            if (!r.ok) { throw new Error(r.status); }
        }).catch(function () {
            pjState.done = false;
            loadProjects();
        });
    }

    function projectRow(p, current) {
        var url = String(p.url || '');
        var row = el('div', 'pp-pjm-row' + (p.archived ? ' arch' : '') + (String(p.id) === current ? ' cur' : '') + (p.depth ? ' kid' : ''));
        // ISS00263: children sit indented under their parent (server sends tree order + depth)
        if (p.depth) { row.style.paddingLeft = (14 + 22 * Math.min(Number(p.depth) || 0, 4)) + 'px'; }
        row.setAttribute('data-id', String(p.id));
        row.setAttribute('data-parent', String(p.parent_id || ''));
        row.setAttribute('data-depth', String(Number(p.depth) || 0));
        row.setAttribute('data-find', String((p.name || '') + ' ' + (p.key || '')).toLowerCase());

        var star = el('button', 'pp-pjm-star');
        star.type = 'button';
        star.setAttribute('data-star', String(p.id));
        star.setAttribute('data-url', String(p.star_url || ''));
        star.setAttribute('aria-pressed', p.starred ? 'true' : 'false');
        star.title = p.starred ? LANG.pj_star_on : LANG.pj_star_off;
        star.appendChild(ico('fa ' + (p.starred ? 'fa-star' : 'fa-star-o')));
        row.appendChild(star);

        var main = el('div', 'mn');

        var l1 = el('div', 'l1');
        var name = el('a', 'nm', p.name);
        name.href = url;
        name.title = String(p.name || '');
        l1.appendChild(name);
        if (p.archived) { l1.appendChild(el('span', 'ar', LANG.archived_tag)); }
        // nested rows already show their parent by position; only a top-level
        // row whose parent is not listed keeps the "↪ parent" hint
        if (p.parent && !p.depth) {
            var up = el('span', 'up');
            up.title = LANG.pj_parent;
            up.appendChild(ico('fa fa-level-up fa-rotate-90'));
            up.appendChild(document.createTextNode(' ' + String(p.parent)));
            l1.appendChild(up);
        }
        main.appendChild(l1);
        // Key and status sit in fixed columns right of the name, so they line
        // up from row to row whatever the name's length or indent
        // (snoro 2026-11-19). An empty slot keeps the column when one is missing.
        var key = el('span', 'cd' + (p.key ? '' : ' none'), p.key || '');
        if (p.key) { key.title = String(p.key); }
        main.appendChild(key);
        var slot = el('span', 'ps');
        if (p.status && p.status.l) {
            var pill = el('span', 'pill', p.status.l);
            pill.style.background = String(p.status.c || '#8a97a3');
            slot.appendChild(pill);
        }
        main.appendChild(slot);

        var meta = el('div', 'mt');
        var track = el('span', 'bar');
        track.title = String(LANG.pj_progress).replace(':percent', String(p.progress || 0));
        var fill = document.createElement('i');
        fill.style.width = String(Math.max(0, Math.min(100, Number(p.progress) || 0))) + '%';
        track.appendChild(fill);
        meta.appendChild(track);
        meta.appendChild(el('span', 'pc', String(p.progress || 0) + '%'));
        meta.appendChild(el('span', 'op', count(LANG.pj_open, p.open || 0)));
        // Always there, empty at zero, so the columns stay put.
        meta.appendChild(el('span', 'od', p.overdue > 0 ? count(LANG.pj_overdue, p.overdue) : ''));
        meta.appendChild(el('span', 'mi', p.mine > 0 ? count(LANG.pj_mine, p.mine) : ''));
        main.appendChild(meta);

        // No launch line any more (snoro 2026-11-18): one project per line,
        // and the whole line opens it - see the click handler on pjList.
        row.appendChild(main);

        // Backlog keeps プロジェクト設定 on the right of the row rather than
        // in the launch list, and offers it only to who may open it.
        if (p.can_edit) {
            var gear = el('a', 'pp-pjm-gear');
            gear.href = url + '#settings';
            gear.title = LANG.pj_settings;
            gear.setAttribute('aria-label', LANG.pj_settings);
            gear.appendChild(ico('fa fa-cog'));
            row.appendChild(gear);
        }

        return row;
    }

    // Filtering is done on what is already drawn: every project the user may
    // see is in the menu, so a round trip per keystroke would buy nothing.
    function filterProjects() {
        if (!pjList || !pjQ) { return; }
        var kw = pjQ.value.trim().toLowerCase();
        if (pjClear) { pjClear.hidden = kw === ''; }
        var shown = 0;
        var folded = kw === '' ? foldHidden() : {};
        pjList.querySelectorAll('[data-find]').forEach(function (row) {
            var hit = kw === '' || row.getAttribute('data-find').indexOf(kw) >= 0;
            row.hidden = !hit || !!folded[row.getAttribute('data-id')];
            if (hit) { shown++; }
        });
        var none = pjList.querySelector('.pp-pjm-nohit');
        if (shown === 0 && kw !== '') {
            if (!none) {
                none = el('div', 'pp-top-none pp-pjm-nohit', LANG.pj_no_match);
                pjList.appendChild(none);
            }
            none.hidden = false;
        } else if (none) {
            none.hidden = true;
        }
    }

    if (pjQ) {
        pjQ.addEventListener('input', filterProjects);
        pjQ.addEventListener('keydown', function (ev) {
            if (ev.key === 'Escape') {
                if (pjQ.value !== '') { ev.stopPropagation(); pjQ.value = ''; filterProjects(); }
                return;
            }
            if (ev.key !== 'Enter') { return; }
            var first = pjList.querySelector('.pp-pjm-row:not([hidden]) .nm, .pp-top-mi:not([hidden])');
            if (first) { ev.preventDefault(); first.click(); }
        });
    }
    if (pjClear) {
        pjClear.addEventListener('click', function () {
            pjQ.value = '';
            filterProjects();
            pjQ.focus();
        });
    }
    // "プロジェクト横断： 課題の検索" - the bar's own search box is the
    // portal's cross-project search, so the link hands it the cursor.
    var pjFind = document.getElementById('pp-pjm-find');
    if (pjFind) {
        pjFind.addEventListener('click', function () {
            closeAll();
            var box = document.getElementById('pp-top-q');
            if (box) { box.focus(); box.select(); }
        });
    }
    // Starring from the menu, flipped before the answer comes back the way
    // the dashboard panel flips it. The row does not move: reordering the
    // list under the cursor would make the next click land on a stranger.
    if (pjList) {
        pjList.addEventListener('click', function (ev) {
            var btn = ev.target.closest('.pp-pjm-star');
            if (!btn) { return; }
            ev.preventDefault();
            ev.stopPropagation();
            if (btn.disabled) { return; }
            var on = btn.getAttribute('aria-pressed') === 'true';
            var token = document.querySelector('meta[name="csrf-token"]');
            btn.disabled = true;
            paintStar(btn, !on);
            fetch(btn.getAttribute('data-url'), {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-TOKEN': token ? token.getAttribute('content') : '',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: 'star_type=star&target_type=' + encodeURIComponent(bar.getAttribute('data-project-table') || pjState.table)
                    + '&target_id=' + encodeURIComponent(btn.getAttribute('data-star'))
            }).then(function (r) {
                if (!r.ok) { throw new Error(r.status); }
                return r.json();
            }).then(function (j) {
                paintStar(btn, !!j.on);
            }).catch(function () {
                paintStar(btn, on);
            }).then(function () {
                btn.disabled = false;
            });
        });
    }
    function paintStar(btn, state) {
        btn.setAttribute('aria-pressed', state ? 'true' : 'false');
        btn.title = state ? LANG.pj_star_on : LANG.pj_star_off;
        btn.querySelector('i').className = 'fa ' + (state ? 'fa-star' : 'fa-star-o');
    }

    // ---- search -------------------------------------------------------
    var q = document.getElementById('pp-top-q');
    var res = bar.querySelector('.pp-top-res');
    var timer = null;
    var ctrl = null;
    var seq = 0;

    function hideResults() {
        if (res) { res.hidden = true; }
    }
    function noteRow(text) {
        var d = document.createElement('div');
        d.className = 'pp-top-none';
        d.textContent = text;
        res.appendChild(d);
    }
    function render(json) {
        res.textContent = '';
        var items = (json && Array.isArray(json.items)) ? json.items : [];
        if (!items.length) {
            noteRow(LANG.no_results);
        } else {
            items.forEach(function (it) {
                var a = document.createElement('a');
                a.className = 'pp-top-mi';
                a.href = String(it.u || '#');
                a.title = String(it.s || '');
                a.appendChild(span('cd', it.k));
                a.appendChild(span('nm', it.s));
                a.appendChild(span('pj', it.p));
                res.appendChild(a);
            });
            if (json.more) { noteRow(LANG.more_results); }
        }
        res.hidden = false;
    }
    function run() {
        var kw = q.value.trim();
        if (kw.length < 2) { hideResults(); return; }
        if (ctrl) { ctrl.abort(); }
        ctrl = ('AbortController' in window) ? new AbortController() : null;
        var mine = ++seq;
        res.textContent = '';
        noteRow(LANG.searching);
        res.hidden = false;
        fetch(bar.getAttribute('data-search-url') + '?q=' + encodeURIComponent(kw), {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            signal: ctrl ? ctrl.signal : undefined
        }).then(function (r) {
            if (!r.ok) { throw new Error(r.status); }
            return r.json();
        }).then(function (json) {
            if (mine === seq) { render(json); }
        }).catch(function (e) {
            if (mine === seq && (!e || e.name !== 'AbortError')) { hideResults(); }
        });
    }
    if (q && res) {
        q.addEventListener('input', function () {
            clearTimeout(timer);
            timer = setTimeout(run, 250);
        });
        q.addEventListener('focus', function () {
            closeMenus();
            if (res.children.length && q.value.trim().length >= 2) { res.hidden = false; }
        });
        q.addEventListener('keydown', function (ev) {
            var first = res.hidden ? null : res.querySelector('a');
            if (ev.key === 'ArrowDown' && first) { ev.preventDefault(); first.focus(); }
            if (ev.key === 'Enter' && first) { ev.preventDefault(); first.click(); }
            if (ev.key === 'Escape') { hideResults(); q.blur(); }
        });
        res.addEventListener('keydown', function (ev) {
            if (ev.key !== 'ArrowDown' && ev.key !== 'ArrowUp') { return; }
            var links = Array.prototype.slice.call(res.querySelectorAll('a'));
            var i = links.indexOf(document.activeElement);
            if (i < 0) { return; }
            ev.preventDefault();
            var next = links[i + (ev.key === 'ArrowDown' ? 1 : -1)];
            if (next) { next.focus(); } else if (ev.key === 'ArrowUp') { q.focus(); }
        });

        // Backlog's muscle memory: "/" anywhere on the page jumps to the
        // search box, unless something with its own keyboard is focused.
        document.addEventListener('keydown', function onSlash(ev) {
            if (!alive()) { document.removeEventListener('keydown', onSlash); return; }
            if (ev.key !== '/' || ev.ctrlKey || ev.metaKey || ev.altKey) { return; }
            var t = ev.target;
            if (t && (t.tagName === 'INPUT' || t.tagName === 'TEXTAREA' || t.tagName === 'SELECT' || t.isContentEditable)) { return; }
            ev.preventDefault();
            q.focus();
            q.select();
        });
    }
})();
</script>
