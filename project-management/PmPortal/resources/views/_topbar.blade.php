@php
    use App\Plugins\PmPortal\PortalDefinition;

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
        'launch' => $topbarLaunch,
    ];
@endphp

{{-- Global bar shared by every portal page - Backlog's top navigation.
     Colors read the SPA theme variables and fall back to the portal
     defaults, so the same file serves the themed project page and the
     unthemed dashboard alike. --}}
<style>
.pp-top{display:flex;align-items:stretch;gap:2px;flex-wrap:wrap;position:relative;z-index:60;
    background:var(--bl-box,#fff);border:1px solid var(--bl-line,#dfe5ea);border-radius:8px;
    margin-bottom:14px;padding:0 8px;font-size:13px;color:var(--bl-ink,#33434f);}
.pp-top a{text-decoration:none;color:inherit;}
.pp-top .pp-top-home,.pp-top .pp-top-dd>button{display:flex;align-items:center;gap:7px;padding:0 13px;
    border:0;background:none;font:inherit;color:inherit;cursor:pointer;white-space:nowrap;height:44px;}
.pp-top .pp-top-home:hover,.pp-top .pp-top-dd>button:hover{background:var(--bl-hover,#f2f8f5);}
.pp-top .pp-top-home.on{box-shadow:inset 0 -3px 0 var(--bl-accent,#4cae93);font-weight:700;}
.pp-top .pp-top-dd{position:relative;display:flex;}
.pp-top .pp-top-settings{color:var(--bl-sub,#8a97a3);}
.pp-top .fa-caret-down{color:var(--bl-sub,#8a97a3);font-size:11px;}
.pp-top .pp-top-menu{position:absolute;top:100%;left:0;min-width:270px;max-width:480px;max-height:420px;overflow-y:auto;
    overscroll-behavior:contain;background:var(--bl-box,#fff);border:1px solid var(--bl-line,#dfe5ea);
    border-radius:0 0 8px 8px;box-shadow:0 8px 22px rgba(23,31,37,.16);z-index:61;}
.pp-top .pp-top-menu-r{left:auto;right:0;}
.pp-top .pp-top-mi{display:flex;align-items:center;gap:8px;padding:8px 13px;border-bottom:1px solid var(--bl-line,#eef2f5);
    font-size:12.5px;}
.pp-top .pp-top-mi:last-child{border-bottom:0;}
.pp-top .pp-top-mi:hover,.pp-top .pp-top-mi:focus{background:var(--bl-hover,#f2f8f5);}
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

/* project menu - the dashboard's project panel, in a dropdown ---------- */
/* The header, the filter box and プロジェクトの追加 stay put; only the
   list moves, so the box you are typing in never scrolls away. */
.pp-top .pp-top-pjmenu{width:560px;min-width:0;max-width:calc(100vw - 32px);
    max-height:none;overflow:hidden;}
.pp-top .pp-pjm-list{max-height:330px;overflow-y:auto;overscroll-behavior:contain;}
.pp-top .pp-pjm-head{display:flex;align-items:center;gap:10px;padding:9px 14px;
    border-bottom:1px solid var(--bl-line,#dfe5ea);background:var(--bl-bg,#fbfcfd);}
.pp-top .pp-pjm-head .ti{font-weight:700;font-size:13px;}
.pp-top .pp-pjm-head .rt{margin-left:auto;font-size:11.5px;color:var(--bl-sub,#8a97a3);}
.pp-top .pp-pjm-head .rt a{color:var(--bl-accent-d,#3d9a80);font-weight:700;cursor:pointer;}
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
.pp-top .pp-pjm-row{display:flex;gap:9px;align-items:flex-start;padding:8px 14px;
    border-bottom:1px solid var(--bl-line,#eef2f5);}
.pp-top .pp-pjm-row:last-child{border-bottom:0;}
.pp-top .pp-pjm-row.arch{background:#f6f7f8;}
.pp-top .pp-pjm-row.kid .nm{font-weight:600;}
.pp-top .pp-pjm-row[draggable="true"]{cursor:grab;}
.pp-top .pp-pjm-row.dragging{opacity:.45;}
.pp-top .pp-pjm-row.drop-before{box-shadow:inset 0 3px 0 var(--bl-accent,#4cae93);}
.pp-top .pp-pjm-row.drop-after{box-shadow:inset 0 -3px 0 var(--bl-accent,#4cae93);}
.pp-top .pp-pjm-row .mn{flex:1 1 auto;min-width:0;display:flex;flex-direction:column;gap:4px;}
.pp-top .pp-pjm-row .l1{display:flex;align-items:center;gap:7px;flex-wrap:wrap;min-height:19px;}
.pp-top .pp-pjm-row .nm{font-size:13.5px;font-weight:700;}
.pp-top .pp-pjm-row .nm:hover{text-decoration:underline;}
.pp-top .pp-pjm-row.cur .nm::after{content:'●';color:var(--bl-accent,#4cae93);font-size:9px;margin-left:5px;
    vertical-align:middle;}
.pp-top .pp-pjm-row .up{font-size:10.5px;color:var(--bl-sub,#8a97a3);}
.pp-top .pp-pjm-row .cd{flex:none;background:var(--bl-chip,#eef3f6);border-radius:3px;padding:0 6px;
    font-size:10.5px;font-weight:700;color:var(--bl-sub,#51616f);font-family:Menlo,Consolas,monospace;}
.pp-top .pp-pjm-row .pill{color:#fff;border-radius:20px;padding:1px 8px;font-size:10px;font-weight:700;}
.pp-top .pp-pjm-row .ar{background:#7f8c8d;color:#fff;border-radius:20px;padding:0 7px;font-size:10px;}
.pp-top .pp-pjm-row .mt{display:flex;align-items:center;gap:9px;flex-wrap:wrap;font-size:11px;
    color:var(--bl-sub,#8a97a3);min-height:17px;}
.pp-top .pp-pjm-row .mt .bar{flex:none;width:68px;height:5px;border-radius:3px;background:#eef2f5;overflow:hidden;}
.pp-top .pp-pjm-row .mt .bar i{display:block;height:100%;background:var(--bl-accent,#4cae93);}
.pp-top .pp-pjm-row .mt .od{color:var(--bl-red,#e5544b);font-weight:700;}
.pp-top .pp-pjm-row .mt .mi{color:var(--bl-accent-d,#3d9a80);font-weight:700;}
/* The Backlog move the dashboard panel already makes: the row turns
   theme-green and the numbers give their place to the launch links. */
.pp-top .pp-pjm-row .lc{display:none;align-items:center;flex-wrap:wrap;min-height:17px;}
.pp-top .pp-pjm-row .lc a{color:#fff;font-size:11.5px;padding:0 7px;line-height:1.5;white-space:nowrap;
    border-left:1px solid rgba(255,255,255,.4);}
.pp-top .pp-pjm-row .lc a:first-child{padding-left:0;border-left:0;}
.pp-top .pp-pjm-row .lc a:hover{text-decoration:underline;color:#fff;}
.pp-top .pp-pjm-row:hover,.pp-top .pp-pjm-row:focus-within{background:var(--bl-accent,#4cae93);}
.pp-top .pp-pjm-row:hover .mt,.pp-top .pp-pjm-row:focus-within .mt{display:none;}
.pp-top .pp-pjm-row:hover .lc,.pp-top .pp-pjm-row:focus-within .lc{display:flex;}
.pp-top .pp-pjm-row:hover .nm,.pp-top .pp-pjm-row:focus-within .nm,
.pp-top .pp-pjm-row:hover .up,.pp-top .pp-pjm-row:focus-within .up{color:#fff;}
.pp-top .pp-pjm-row:hover .cd,.pp-top .pp-pjm-row:focus-within .cd{background:rgba(255,255,255,.22);color:#fff;}
.pp-top .pp-pjm-row:hover .pp-pjm-gear,.pp-top .pp-pjm-row:focus-within .pp-pjm-gear{color:#fff;}
.pp-top .pp-pjm-star{flex:none;border:0;background:none;cursor:pointer;padding:1px 2px;font-size:14px;
    color:#c9d2d9;line-height:1;margin-top:3px;}
.pp-top .pp-pjm-star:hover,.pp-top .pp-pjm-star[aria-pressed="true"]{color:var(--bl-amber,#eda62a);}
.pp-top .pp-pjm-gear{flex:none;color:var(--bl-sub,#8a97a3);padding:3px 2px 0;font-size:13px;}
.pp-top .pp-pjm-gear:hover{color:var(--bl-accent-d,#3d9a80);}
.pp-top .pp-pjm-foot{border-top:1px solid var(--bl-line,#dfe5ea);}

/* search --------------------------------------------------------------- */
.pp-top .pp-top-search{position:relative;display:flex;align-items:center;gap:7px;flex:1 1 200px;max-width:340px;
    margin-left:auto;padding:0 4px;}
.pp-top .pp-top-search .fa-search{color:var(--bl-sub,#8a97a3);flex:none;}
.pp-top .pp-top-search input{flex:1 1 auto;min-width:0;border:1px solid var(--bl-line,#dfe5ea);border-radius:16px;
    background:var(--bl-bg,#f5f7f9);color:inherit;padding:5px 30px 5px 12px;font:inherit;font-size:12.5px;outline:none;}
.pp-top .pp-top-search input:focus{border-color:var(--bl-accent,#4cae93);background:var(--bl-box,#fff);}
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
.pp-top .pp-top-addwrap>button{background:var(--bl-accent,#4cae93);color:#fff;border-radius:6px;height:32px;
    margin:6px 0 6px 6px;padding:0 13px;}
.pp-top .pp-top-addwrap>button:hover{background:var(--bl-accent-d,#3d9a80);}
.pp-top .pp-top-addwrap .fa-caret-down{color:rgba(255,255,255,.8);}
</style>

<nav class="pp-top" id="pp-top" data-search-url="{{ $topbar['search_url'] }}"
     data-projects-url="{{ $topbar['projects_url'] }}"
     data-project-table="{{ $topbar['project_table'] }}"
     data-current="{{ $topbar['current'] }}"
     aria-label="{{ PortalDefinition::trans('title') }}">
    <a class="pp-top-home {{ $topbar['current'] === null ? 'on' : '' }}" href="{{ $topbar['dashboard_url'] }}">
        <i class="fa fa-home"></i><span>{{ $tt('dashboard') }}</span>
    </a>

    <div class="pp-top-dd">
        <button type="button" aria-haspopup="true" aria-expanded="false">
            <i class="fa fa-folder-open-o"></i><span>{{ $tt('projects') }}</span><i class="fa fa-caret-down"></i>
        </button>
        <div class="pp-top-menu pp-top-pjmenu" data-projects hidden>
            <div class="pp-pjm-head">
                <span class="ti">{{ $tt('projects') }}</span>
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

    <div class="pp-top-dd">
        <button type="button" aria-haspopup="true" aria-expanded="false">
            <i class="fa fa-history"></i><span>{{ $tt('recent') }}</span><i class="fa fa-caret-down"></i>
        </button>
        {{-- Filled from localStorage on open: what this browser looked at
             lately, so the server neither knows nor cares. --}}
        <div class="pp-top-menu" data-recent hidden></div>
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
    @if($addable || !$open)
    <div class="pp-top-dd pp-top-addwrap">
        <button type="button" aria-haspopup="true" aria-expanded="false">
            <i class="fa fa-plus"></i><span>{{ $tt('add_issue') }}</span><i class="fa fa-caret-down"></i>
        </button>
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
    {{-- ISS00256: the sidebar shows only "プロジェクト管理", so the portal's
         settings (and the master lists under it) open from here - for
         administrators only. --}}
    @if(!empty($topbar['settings_url']))
    <a class="pp-top-home pp-top-settings" href="{{ $topbar['settings_url'] }}" title="設定">
        <i class="fa fa-cog"></i><span>設定</span>
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

    var LANG = @json($topbarLang);

    function span(cls, text) {
        var s = document.createElement('span');
        s.className = cls;
        s.textContent = String(text == null ? '' : text);
        return s;
    }

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
        btn.addEventListener('click', function (ev) {
            ev.stopPropagation();
            var menu = btn.parentElement.querySelector('.pp-top-menu');
            var wasOpen = menu && !menu.hidden;
            closeAll();
            if (menu && !wasOpen) {
                if (menu.hasAttribute('data-recent')) { fillRecent(menu); }
                menu.hidden = false;
                btn.setAttribute('aria-expanded', 'true');
                if (menu.hasAttribute('data-projects')) {
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

    // ---- recently viewed ----------------------------------------------
    // Same pp_recent record the dashboard tab reads, same rule: strings out
    // of localStorage go through textContent, never through markup.
    function fillRecent(menu) {
        var entries = [];
        try { entries = JSON.parse(localStorage.getItem('pp_recent') || '[]'); } catch (e) { entries = []; }
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

    function loadProjects() {
        if (pjState.done || pjState.busy || !pjList) { return; }
        var url = bar.getAttribute('data-projects-url');
        if (!url) { return; }
        pjState.busy = true;
        fetch(url, {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (r) {
            if (!r.ok) { throw new Error(r.status); }
            return r.json();
        }).then(function (json) {
            pjState.done = true;
            pjState.table = String((json && json.table) || '');
            pjState.reorderUrl = String((json && json.reorder_url) || '');
            paintProjects((json && Array.isArray(json.items)) ? json.items : []);
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
        filterProjects();
    }

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
        row.title = LANG.pj_drag || 'ドラッグで並べ替え';
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
        l1.appendChild(name);
        if (p.key) { l1.appendChild(el('span', 'cd', p.key)); }
        if (p.status && p.status.l) {
            var pill = el('span', 'pill', p.status.l);
            pill.style.background = String(p.status.c || '#8a97a3');
            l1.appendChild(pill);
        }
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

        var meta = el('div', 'mt');
        var track = el('span', 'bar');
        track.title = String(LANG.pj_progress).replace(':percent', String(p.progress || 0));
        var fill = document.createElement('i');
        fill.style.width = String(Math.max(0, Math.min(100, Number(p.progress) || 0))) + '%';
        track.appendChild(fill);
        meta.appendChild(track);
        meta.appendChild(el('span', '', String(p.progress || 0) + '%'));
        meta.appendChild(el('span', '', count(LANG.pj_open, p.open || 0)));
        if (p.overdue > 0) { meta.appendChild(el('span', 'od', count(LANG.pj_overdue, p.overdue))); }
        if (p.mine > 0) { meta.appendChild(el('span', 'mi', count(LANG.pj_mine, p.mine))); }
        main.appendChild(meta);

        var launch = el('div', 'lc');
        (LANG.launch || []).forEach(function (item) {
            if (item[0] === 'new' && !p.can_add) { return; }
            var a = el('a', '', item[1]);
            a.href = url + '#' + item[0];
            launch.appendChild(a);
        });
        main.appendChild(launch);
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
        pjList.querySelectorAll('[data-find]').forEach(function (row) {
            var hit = kw === '' || row.getAttribute('data-find').indexOf(kw) >= 0;
            row.hidden = !hit;
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
