@php
    use App\Plugins\ExmentPM\PortalDefinition;

    $t = function ($key, $args = []) {
        return PortalDefinition::trans('dash.' . $key, $args);
    };
    $kpi = $dashboard['kpi'];
    $tabs = $dashboard['tabs'];

    // Tab order is the reading order of the screen: what is mine, what is
    // late, what I started, what I am watching, what I finished, where I was.
    $tabList = [
        'assigned' => $t('tab_assigned'),
        'overdue' => $t('tab_overdue'),
        'soon' => $t('tab_soon'),
        'created' => $t('tab_created'),
        // ISS00341: comments somebody asked me to confirm.
        'review' => $t('tab_review'),
        'watched' => $t('tab_watched'),
        'closed' => $t('tab_closed'),
    ];

    // The launch menu a project row grows on hover - Backlog's, minus the
    // features the portal does not have (documents, git). Each entry is a
    // hash the portal's router already answers.
    $launch = [
        ['new', PortalDefinition::trans('add_issue'), 'fa-plus'],
        ['issues', PortalDefinition::trans('tab_issues'), 'fa-list-ul'],
        ['board', PortalDefinition::trans('tab_board'), 'fa-columns'],
        ['gantt', PortalDefinition::trans('tab_gantt'), 'fa-tasks'],
        ['burndown', PortalDefinition::trans('tab_burndown'), 'fa-line-chart'],
        ['wiki', PortalDefinition::trans('tab_wiki'), 'fa-book'],
        ['files', PortalDefinition::trans('tab_files'), 'fa-folder-o'],
    ];

    // Column captions of the project list (snoro 2026-11-19). The numbers sit
    // in fixed columns under one caption instead of repeating the word in
    // every row; the captions are the existing "未完了 :count" strings with
    // the count left out, so no new language key is needed.
    $colLabel = function ($key) use ($t) {
        return trim($t($key, ['count' => '']));
    };
@endphp

{{-- Scoped to .pp-dash: this page renders inside the Exment admin layout,
     which has no portal stylesheet of its own. --}}
<style>
.pp-dash{--d-line:#dfe5ea;--d-text:#33434f;--d-mute:#8a97a3;--d-accent:#4cae93;--d-red:#e5544b;--d-amber:#eda62a;
    --d-line2:#eef2f5;--d-sub:#8a97a3;--d-accent-d:#3d9a80;--d-hover:#f2f8f5;--d-chip:#eef3f6;--d-ink2:#51616f;
    color:var(--d-text);font-size:13px;}
.pp-dash a{color:inherit;text-decoration:none;}
.pp-dash .pp-panel{background:#fff;border:1px solid var(--d-line);border-radius:8px;margin-bottom:16px;overflow:hidden;}
.pp-dash .pp-ph{display:flex;align-items:center;gap:10px;padding:11px 16px;border-bottom:1px solid var(--d-line);
    font-weight:700;font-size:13.5px;background:#fbfcfd;}
.pp-dash .pp-ph .pp-ph-right{margin-left:auto;font-weight:400;font-size:12px;}

/* two columns, projects in the top-left corner ------------------------- */
.pp-dash .pp-wrap{display:grid;grid-template-columns:minmax(0,1.25fr) minmax(0,1fr);gap:16px;align-items:start;}
@media (max-width:1100px){.pp-dash .pp-wrap{grid-template-columns:minmax(0,1fr);}}
/* Everything in the left column sizes itself by the column, not by the
   window: the sidebar and the two column grid make the window width a poor
   guide to the room a row really has. */
.pp-dash .pp-left{container-type:inline-size;container-name:ppl;}

/* project list (snoro 2026-11-19) ---------------------------------------
   One project per line, like the project menu of the top bar: the name
   takes what is left, everything after it is a fixed-width column packed
   to the right. A child row is indented by its padding only, so the
   columns of parents and children stand in one line whatever the depth or
   the length of the name. The caption row uses the same cells. */
.pp-dash .pp-pjpanel{overflow:visible;position:relative;z-index:2;}
.pp-dash .pp-pjpanel>.pp-ph{border-radius:7px 7px 0 0;}
.pp-dash .pp-pjlist>:last-child{border-radius:0 0 7px 7px;}
.pp-dash .pp-ph .pp-ph-n{background:var(--d-chip);border-radius:9px;padding:0 7px;font-size:11px;font-weight:700;
    color:var(--d-ink2);margin-left:-3px;}
.pp-dash .pp-pjh,.pp-dash .pp-pj{display:flex;align-items:center;gap:8px;padding:0 10px 0 14px;}
.pp-dash .pp-pjh{height:24px;border-bottom:1px solid var(--d-line2);font-size:10.5px;font-weight:700;
    color:var(--d-mute);white-space:nowrap;}
.pp-dash .pp-pjh .sp{flex:1 1 0;min-width:0;}
.pp-dash .pp-pj{position:relative;min-height:34px;border-bottom:1px solid var(--d-line2);cursor:pointer;}
.pp-dash .pp-pj:last-child{border-bottom:0;}
.pp-dash .pp-pj:hover,.pp-dash .pp-pj:focus-within,.pp-dash .pp-pj.open{background:var(--d-hover);}
.pp-dash .pp-pj.open{z-index:5;}
.pp-dash .pp-pj.arch{background:#f6f7f8;}
.pp-dash .pp-pj.arch .pp-pj-name{color:var(--d-ink2);}
.pp-dash .pp-pj-l1{flex:1 1 0;min-width:0;display:flex;align-items:center;gap:6px;}
.pp-dash .pp-pj-name{flex:0 1 auto;min-width:0;font-size:13.5px;font-weight:700;
    overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.pp-dash .pp-pj-name:hover{text-decoration:underline;color:var(--d-accent-d);}
.pp-dash .pp-pj-up{flex:0 2 auto;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;
    color:var(--d-mute);font-size:10.5px;}
.pp-dash .pp-pj .ar{flex:none;background:#7f8c8d;color:#fff;border-radius:20px;padding:0 7px;font-size:10px;
    font-weight:700;line-height:1.6;white-space:nowrap;}
/* the fixed columns - caption row and project rows share these widths */
.pp-dash .pp-pjlist .cd{flex:none;width:170px;box-sizing:border-box;}
.pp-dash .pp-pjlist .ps{flex:none;width:56px;text-align:center;overflow:hidden;line-height:1;}
.pp-dash .pp-pjlist .pg{flex:none;width:84px;display:flex;align-items:center;justify-content:flex-end;gap:6px;}
.pp-dash .pp-pjlist .op{flex:none;width:46px;text-align:right;}
.pp-dash .pp-pjlist .od{flex:none;width:52px;text-align:right;}
.pp-dash .pp-pjlist .mi{flex:none;width:40px;text-align:right;}
.pp-dash .pp-pjlist .mo{flex:none;width:24px;}
.pp-dash .pp-pj .cd{overflow:hidden;line-height:1;}
.pp-dash .pp-pj .cd .k{display:inline-block;max-width:100%;box-sizing:border-box;vertical-align:middle;overflow:hidden;
    text-overflow:ellipsis;white-space:nowrap;background:var(--d-chip);border-radius:3px;padding:0 6px;line-height:1.7;
    font-size:10.5px;font-weight:700;color:var(--d-ink2);font-family:Menlo,Consolas,monospace;}
.pp-dash .pp-pj .pill{display:inline-block;max-width:100%;box-sizing:border-box;overflow:hidden;text-overflow:ellipsis;
    white-space:nowrap;vertical-align:middle;color:#fff;border-radius:20px;padding:0 7px;font-size:10px;font-weight:700;line-height:1.6;}
.pp-dash .pp-pj .pj-bar{flex:none;width:44px;height:5px;border-radius:3px;background:#e6ecf0;overflow:hidden;}
.pp-dash .pp-pj .pj-bar i{display:block;height:100%;background:var(--d-accent);border-radius:3px;}
.pp-dash .pp-pj .pc{flex:none;width:34px;text-align:right;font-size:11.5px;color:var(--d-mute);}
.pp-dash .pp-pj .pg,.pp-dash .pp-pj .op,.pp-dash .pp-pj .od,.pp-dash .pp-pj .mi{font-variant-numeric:tabular-nums;white-space:nowrap;}
.pp-dash .pp-pj .op,.pp-dash .pp-pj .od,.pp-dash .pp-pj .mi{font-size:12.5px;font-weight:700;}
.pp-dash .pp-pj .op{color:var(--d-ink2);}
.pp-dash .pp-pj .op.z{color:#c3ccd4;font-weight:400;}
.pp-dash .pp-pj .od{color:var(--d-red);}
.pp-dash .pp-pj .mi{color:var(--d-accent-d);}
.pp-dash .pp-pjh .pg{display:block;text-align:right;}

/* Launch links. They used to replace the numbers while the row was hovered,
   which hid exactly what the eye was reading across and could not be
   reached by touch at all. Now a small menu behind the "..." button of
   the row. */
.pp-dash .pp-pj-more{width:24px;height:24px;border:0;background:none;border-radius:5px;padding:0;cursor:pointer;
    color:#b3bec7;font-size:13px;line-height:1;}
.pp-dash .pp-pj:hover .pp-pj-more{color:var(--d-mute);}
.pp-dash .pp-pj-more:hover,.pp-dash .pp-pj:hover .pp-pj-more:hover,
.pp-dash .pp-pj-more[aria-expanded="true"]{background:#e3ece8;color:var(--d-accent-d);}
.pp-dash .pp-pj-more:focus-visible{outline:2px solid var(--d-accent);outline-offset:1px;}
.pp-dash .pp-launch{position:absolute;right:8px;top:calc(100% - 3px);z-index:20;min-width:180px;background:#fff;
    border:1px solid var(--d-line);border-radius:8px;box-shadow:0 8px 24px rgba(15,42,60,.16);padding:5px 0;cursor:default;}
.pp-dash .pp-launch[hidden]{display:none;}
.pp-dash .pp-launch.up{top:auto;bottom:calc(100% - 3px);}
.pp-dash .pp-launch a{display:flex;align-items:center;gap:9px;padding:6px 14px;font-size:12.5px;white-space:nowrap;}
.pp-dash .pp-launch a i{flex:none;width:14px;text-align:center;color:var(--d-mute);}
.pp-dash .pp-launch a:hover,.pp-dash .pp-launch a:focus-visible{background:var(--d-hover);outline:0;}
.pp-dash .pp-launch a:hover i{color:var(--d-accent-d);}
.pp-dash .pp-launch .sep{border-top:1px solid var(--d-line2);margin:4px 0;}

.pp-dash .pp-star{border:0;background:none;cursor:pointer;padding:1px 2px;font-size:14px;color:#c9d2d9;line-height:1;flex:none;width:20px;}
.pp-dash .pp-star:hover{color:var(--d-amber);}
.pp-dash .pp-star[aria-pressed="true"]{color:var(--d-amber);}

/* Narrow column: the bar goes first, then the key, then the percentage and
   the state - the name and the three counts always stay. */
@container ppl (max-width:680px){
    .pp-dash .pp-pjlist .pj-bar{display:none;}
    .pp-dash .pp-pjlist .pg{width:34px;}
}
@container ppl (max-width:600px){
    .pp-dash .pp-pjlist .cd{display:none;}
}
@container ppl (max-width:470px){
    .pp-dash .pp-pjlist .pg{display:none;}
}
@container ppl (max-width:420px){
    .pp-dash .pp-pjlist .ps{display:none;}
    .pp-dash .pp-pjh,.pp-dash .pp-pj{gap:6px;padding:0 6px 0 8px;}
    .pp-dash .pp-pjh{font-size:10px;}
    .pp-dash .pp-pjlist .op{width:40px;}
    .pp-dash .pp-pjlist .od{width:42px;}
    .pp-dash .pp-pjlist .mi{width:30px;}
    .pp-dash .pp-pj{min-height:38px;}
    .pp-dash .pp-pj-name{white-space:normal;overflow-wrap:anywhere;line-height:1.3;padding:4px 0;}
}

/* project tree on the dashboard (fold, drag) */
.pp-dash .pp-pj[hidden]{display:none !important;}
.pp-dash .pp-dtgl-slot{flex:none;width:16px;display:inline-flex;justify-content:center;}
.pp-dash .pp-dtgl{border:0;background:none;padding:4px 2px;color:var(--d-sub);font-size:10px;line-height:1;cursor:pointer;}
.pp-dash .pp-dtgl:hover{color:var(--d-accent-d);}
.pp-dash .pp-pj.kid .pp-pj-name{font-weight:600;}
.pp-dash .pp-pj.dragging{opacity:.45;}
.pp-dash .pp-pj.drop-before{box-shadow:inset 0 3px 0 var(--d-accent);}
.pp-dash .pp-pj.drop-after{box-shadow:inset 0 -3px 0 var(--d-accent);}
.pp-dash .pp-dfold{font-size:11.5px;font-weight:400;color:var(--d-sub);margin-left:6px;white-space:nowrap;}
.pp-dash .pp-dfold a{color:var(--d-accent-d);font-weight:700;cursor:pointer;}
.pp-dash .pp-dfold a:hover,.pp-dash .pp-ph-right a:hover{text-decoration:underline;}
.pp-dash .pp-ph-right a{color:var(--d-accent-d);font-weight:700;white-space:nowrap;}
.pp-dash .pp-pjpanel>.pp-ph{flex-wrap:wrap;row-gap:4px;}

/* KPI ------------------------------------------------------------------ */
/* Four in a row, or two by two when the column is narrow - never three and
   one left over. */
.pp-dash .pp-kpi{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin-bottom:16px;}
@container ppl (max-width:620px){.pp-dash .pp-kpi{grid-template-columns:repeat(2,minmax(0,1fr));}}
.pp-dash .pp-kpi button{display:flex;flex-direction:column;gap:1px;text-align:left;background:#fff;
    border:1px solid var(--d-line);border-left:4px solid var(--d-accent);border-radius:8px;padding:9px 13px;
    cursor:pointer;font:inherit;color:inherit;width:100%;transition:box-shadow .12s,transform .12s;}
.pp-dash .pp-kpi button:hover{box-shadow:0 2px 10px rgba(51,67,79,.13);transform:translateY(-1px);}
.pp-dash .pp-kpi button:focus-visible{outline:2px solid var(--d-accent);outline-offset:2px;}
.pp-dash .pp-kpi .n{font-size:22px;font-weight:700;line-height:1.15;font-variant-numeric:tabular-nums;}
.pp-dash .pp-kpi .l{font-size:11.5px;font-weight:700;}
.pp-dash .pp-kpi .h{font-size:10.5px;color:var(--d-mute);}
.pp-dash .pp-kpi .k-overdue{border-left-color:var(--d-red);}
.pp-dash .pp-kpi .k-overdue .n{color:var(--d-red);}
.pp-dash .pp-kpi .k-soon{border-left-color:var(--d-amber);}
.pp-dash .pp-kpi .k-soon .n{color:#b9791a;}
.pp-dash .pp-kpi .k-closed{border-left-color:#7f8c8d;}

/* my issues ------------------------------------------------------------ */
/* Wraps rather than scrolls: the strip showed no scrollbar, so at 1440px the
   last tab was cut in half and below that it was gone, with nothing to say
   there was more. */
.pp-dash .pp-tabs{display:flex;flex-wrap:wrap;gap:0;padding:0 10px;border-bottom:1px solid var(--d-line);background:#fbfcfd;}
.pp-dash .pp-tabs button{border:0;background:none;font:inherit;color:var(--d-mute);cursor:pointer;padding:9px 7px;
    border-bottom:2px solid transparent;white-space:nowrap;font-size:12.5px;}
.pp-dash .pp-tabs button:hover{color:var(--d-text);}
.pp-dash .pp-tabs button[aria-selected="true"]{color:var(--d-text);font-weight:700;border-bottom-color:var(--d-accent);}
.pp-dash .pp-tabs .c{display:inline-block;margin-left:5px;background:var(--d-chip);border-radius:9px;padding:0 6px;
    font-size:11px;font-weight:700;color:var(--d-ink2);}
.pp-dash .pp-tabs button[aria-selected="true"] .c{background:var(--d-accent);color:#fff;}

/* Same cap as the feed, plus a floor so an empty tab does not collapse the
   panel to a strip and make the two columns look broken. */
.pp-dash [data-body]{min-height:180px;max-height:520px;overflow-y:auto;overscroll-behavior:contain;}
/* Fixed columns here as well: project, key, then the subject taking the
   rest, then priority, state and due date in slots of their own, so a
   short state name no longer pulls the priority badge sideways. */
.pp-dash .pp-row{display:flex;align-items:center;gap:10px;padding:0 16px;min-height:35px;border-bottom:1px solid var(--d-line2);}
.pp-dash .pp-row:last-child{border-bottom:0;}
.pp-dash .pp-row:hover{background:var(--d-hover);}
.pp-dash .pp-row .pj{flex:none;width:80px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;
    font-size:11px;color:var(--d-mute);}
.pp-dash .pp-row .key{flex:none;width:134px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;
    font-size:11.5px;font-weight:700;color:#3c8dbc;font-family:Menlo,Consolas,monospace;}
.pp-dash .pp-row .sub{flex:1 1 0;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.pp-dash .pp-row:hover .sub{text-decoration:underline;}
.pp-dash .pp-row .prs{flex:none;width:40px;text-align:center;line-height:1;}
.pp-dash .pp-row .sts{flex:none;width:74px;text-align:center;line-height:1;}
.pp-dash .pp-row .st,.pp-dash .pp-row .pr{display:inline-block;max-width:100%;box-sizing:border-box;overflow:hidden;
    text-overflow:ellipsis;white-space:nowrap;vertical-align:middle;color:#fff;border-radius:20px;font-weight:700;line-height:1.6;}
.pp-dash .pp-row .st{padding:0 9px;font-size:10.5px;}
.pp-dash .pp-row .pr{padding:0 8px;font-size:10px;}
.pp-dash .pp-row .due{flex:none;font-size:11.5px;color:var(--d-mute);width:84px;text-align:right;white-space:nowrap;
    font-variant-numeric:tabular-nums;}
.pp-dash .pp-row .due.late{color:var(--d-red);font-weight:700;}
.pp-dash .pp-row .due.soon{color:#b9791a;font-weight:700;}
/* The key already starts with the project code, so the project name is the
   first to go; then the priority; a phone keeps key, subject, state, due. */
@container ppl (max-width:700px){.pp-dash .pp-row .pj{display:none;}}
@container ppl (max-width:560px){
    .pp-dash .pp-row .prs{display:none;}
    .pp-dash .pp-row .key{width:auto;max-width:134px;}
}
@container ppl (max-width:420px){
    .pp-dash .pp-row{flex-wrap:wrap;gap:2px 8px;padding:7px 12px;}
    .pp-dash .pp-row .key{order:1;max-width:none;flex:1 1 0;}
    .pp-dash .pp-row .sts{order:2;width:auto;}
    .pp-dash .pp-row .due{order:3;width:auto;}
    .pp-dash .pp-row .sub{order:4;flex:1 1 100%;}
}
.pp-dash .pp-empty{padding:26px 16px;text-align:center;color:var(--d-mute);font-size:12.5px;}
.pp-dash .pp-more{padding:8px 16px;font-size:11.5px;color:var(--d-mute);background:#fbfcfd;border-top:1px solid var(--d-line2);}

/* feed ----------------------------------------------------------------- */
/* Capped and scrolled inside itself, so a long feed cannot push everything
   below it off the screen. */
.pp-dash .pp-feedbody{max-height:760px;overflow-y:auto;overscroll-behavior:contain;}
.pp-dash .pp-fday{position:sticky;top:0;background:#f4f7f9;border-bottom:1px solid var(--d-line);
    padding:5px 16px;font-size:11.5px;font-weight:700;color:var(--d-ink2);z-index:1;}
.pp-dash .pp-fd{display:flex;gap:10px;padding:8px 16px;border-bottom:1px solid var(--d-line2);align-items:flex-start;}
.pp-dash .pp-fd:last-child{border-bottom:0;}
.pp-dash .pp-fd:hover{background:var(--d-hover);}
.pp-dash .pp-fd .av{flex:none;width:26px;height:26px;border-radius:50%;color:#fff;display:flex;align-items:center;
    justify-content:center;font-size:11px;letter-spacing:-.3px;font-weight:700;position:relative;overflow:hidden;margin-top:2px;}
/* ISS00254: registered avatar laid over the initial */
.pp-dash .pp-fd .av .bl-avimg{position:absolute;top:0;left:0;width:100%;height:100%;border-radius:50%;object-fit:cover;background:#fff;}
.pp-dash .pp-fd .bd{flex:1 1 auto;min-width:0;}
/* Each on a line of its own: as inline spans the who/when line and the
   issue line ran together on a wide screen and broke apart on a narrow
   one, so no two entries had the same shape. */
.pp-dash .pp-fd .ln1,.pp-dash .pp-fd .ln2,.pp-dash .pp-fd .ex{display:block;}
.pp-dash .pp-fd .ln1{font-size:11.5px;color:var(--d-mute);line-height:1.5;}
.pp-dash .pp-fd .ln1 b{color:var(--d-text);}
.pp-dash .pp-fd .ln2{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:12.5px;line-height:1.55;}
.pp-dash .pp-fd .ln2 .key{font-family:Menlo,Consolas,monospace;font-size:11.5px;font-weight:700;color:#3c8dbc;}
.pp-dash .pp-fd:hover .ln2{text-decoration:underline;}
.pp-dash .pp-fd .ex{font-size:11.5px;color:var(--d-mute);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
</style>

<div class="pp-dash">
    @include(PortalDefinition::view('_topbar'))
    <div class="pp-wrap">
        <div class="pp-left">

            {{-- Projects, in the top-left corner where Backlog keeps them.
                 Hovering a row swaps its numbers for the launch menu. --}}
            <section class="pp-panel pp-pjpanel">
                <div class="pp-ph">
                    <i class="fa fa-folder-open-o"></i> {{ $t('projects') }}
                    @if(count($projects) > 0)
                    <span class="pp-ph-n">{{ count($projects) }}</span>
                    @endif
                    {{-- fold the project tree (snoro 2026-11-18) --}}
                    <span class="pp-dfold" id="pp-dfold" hidden>
                        <a href="javascript:void(0);" id="pp-dfold-open">すべて開く</a> /
                        <a href="javascript:void(0);" id="pp-dfold-close">すべて閉じる</a>
                    </span>
                    @if($create['can'] || $archived_count > 0)
                    <span class="pp-ph-right">
                        @if($create['can'])
                        <a href="javascript:void(0);" id="pp-newpj-open">
                            <i class="fa fa-plus"></i> {{ PortalDefinition::trans('newpj.title') }}
                        </a>
                        @endif
                        @if($archived_count > 0)
                        <a href="{{ $toggle_url }}" @if($create['can']) style="margin-left:14px;" @endif>
                            <i class="fa {{ $show_archived ? 'fa-eye-slash' : 'fa-archive' }}"></i>
                            {{ $show_archived
                                ? PortalDefinition::trans('hide_archived')
                                : PortalDefinition::trans('show_archived', $archived_count) }}
                        </a>
                        @endif
                    </span>
                    @endif
                </div>
                <div class="pp-pjlist">
                @if(count($projects) > 0)
                {{-- Caption row: the same cells as a project row, so each word
                     stands over its column at every width. --}}
                <div class="pp-pjh" aria-hidden="true">
                    <span class="sp"></span>
                    <span class="cd"></span>
                    <span class="ps"></span>
                    <span class="pg">{{ $t('progress') }}</span>
                    <span class="op">{{ $colLabel('card_open') }}</span>
                    <span class="od">{{ $colLabel('card_overdue') }}</span>
                    <span class="mi">{{ $colLabel('card_mine') }}</span>
                    <span class="mo"></span>
                </div>
                @endif
                @forelse($projects as $project)
                <div class="pp-pj {{ $project['archived'] ? 'arch' : '' }} {{ !empty($project['depth']) ? 'kid' : '' }}"
                     id="pp-dpj-{{ $project['id'] }}"
                     data-id="{{ $project['id'] }}" data-parent="{{ $project['parent_id'] ?? '' }}" data-depth="{{ intval($project['depth'] ?? 0) }}"
                     @if(!empty($reorder_url)) draggable="true" title="ドラッグで並べ替え" @endif
                     @if(!empty($project['depth'])) style="padding-left:{{ 14 + 22 * min(intval($project['depth']), 4) }}px" @endif>
                    <span class="pp-dtgl-slot"></span>
                    <button type="button" class="pp-star" data-star="{{ $project['id'] }}"
                            data-url="{{ $project['star_url'] }}"
                            aria-pressed="{{ $project['starred'] ? 'true' : 'false' }}"
                            title="{{ $project['starred'] ? $t('star_on') : $t('star_off') }}">
                        <i class="fa {{ $project['starred'] ? 'fa-star' : 'fa-star-o' }}"></i>
                    </button>
                    <div class="pp-pj-l1">
                        <a class="pp-pj-name" href="{{ $project['url'] }}" title="{{ $project['name'] }}">{{ $project['name'] }}</a>
                        @if($project['archived'])
                        <span class="ar">{{ PortalDefinition::trans('archived_tag') }}</span>
                        @endif
                        @if($project['parent'] && empty($project['depth']))
                        {{-- nested rows show their parent by position; only an
                             orphan whose parent is not listed keeps the hint --}}
                        <span class="pp-pj-up"><i class="fa fa-level-up fa-rotate-90"></i> {{ $project['parent'] }}</span>
                        @endif
                    </div>
                    {{-- Fixed columns. An empty cell still takes its width, so
                         a project without a key or a state does not pull the
                         cells after it out of line. --}}
                    <span class="cd">@if($project['key'] !== '')<span class="k" title="{{ $project['key'] }}">{{ $project['key'] }}</span>@endif</span>
                    <span class="ps">@if($project['status'])<span class="pill" style="background:{{ $project['status']['c'] }}" title="{{ $project['status']['l'] }}">{{ $project['status']['l'] }}</span>@endif</span>
                    <span class="pg" title="{{ $t('progress') }} {{ $project['progress'] }}%">
                        <span class="pj-bar"><i style="width:{{ $project['progress'] }}%"></i></span>
                        <span class="pc">{{ $project['progress'] }}%</span>
                    </span>
                    <span class="op {{ $project['open'] > 0 ? '' : 'z' }}" title="{{ $t('card_open', ['count' => $project['open']]) }}">{{ $project['open'] }}</span>
                    <span class="od" @if($project['overdue'] > 0) title="{{ $t('card_overdue', ['count' => $project['overdue']]) }}" @endif>{{ $project['overdue'] > 0 ? $project['overdue'] : '' }}</span>
                    <span class="mi" @if($project['mine'] > 0) title="{{ $t('card_mine', ['count' => $project['mine']]) }}" @endif>{{ $project['mine'] > 0 ? $project['mine'] : '' }}</span>
                    <button type="button" class="pp-pj-more mo" aria-haspopup="true" aria-expanded="false"
                            title="メニューを開く" aria-label="メニューを開く"><i class="fa fa-ellipsis-h"></i></button>
                    <div class="pp-launch" hidden>
                        @foreach($launch as $item)
                        @if($item[0] !== 'new' || !empty($project['can_add']))
                        <a href="{{ $project['url'] }}#{{ $item[0] }}"><i class="fa {{ $item[2] }}"></i>{{ $item[1] }}</a>
                        @endif
                        @endforeach
                        @if(!empty($project['can_edit']))
                        <div class="sep"></div>
                        <a href="{{ $project['url'] }}#settings"><i class="fa fa-cog"></i>{{ PortalDefinition::trans('tab_settings') }}</a>
                        @endif
                    </div>
                </div>
                @empty
                <div class="pp-empty">{{ PortalDefinition::trans('empty') }}</div>
                @endforelse
                </div>
            </section>

            {{-- What needs attention. Clicking a card opens the tab behind it. --}}
            <div class="pp-kpi">
                @foreach([
                    ['assigned', 'open', 'k-open'],
                    ['overdue', 'overdue', 'k-overdue'],
                    ['soon', 'soon', 'k-soon'],
                    ['closed', 'closed', 'k-closed'],
                ] as $card)
                <button type="button" class="{{ $card[2] }}" data-goto="{{ $card[0] }}">
                    <span class="n">{{ $kpi[$card[1]] }}</span>
                    <span class="l">{{ $t('kpi_' . $card[1]) }}</span>
                    <span class="h">{{ $t('kpi_hint_' . $card[1]) }}</span>
                </button>
                @endforeach
            </div>

            <section class="pp-panel">
                <div class="pp-ph"><i class="fa fa-user-circle-o"></i> {{ $t('mine') }}</div>
                <div class="pp-tabs" role="tablist">
                    @foreach($tabList as $key => $label)
                    <button type="button" role="tab" data-tab="{{ $key }}"
                            aria-selected="{{ $loop->first ? 'true' : 'false' }}">{{ $label }}<span class="c">{{ count($tabs[$key]) }}</span></button>
                    @endforeach
                    {{-- Filled from localStorage: what this browser looked at
                         lately, so the server neither knows nor cares. --}}
                    <button type="button" role="tab" data-tab="recent"
                            aria-selected="false">{{ $t('tab_recent') }}<span class="c" id="pp-recent-count">0</span></button>
                </div>
                @foreach($tabList as $key => $label)
                <div data-body="{{ $key }}" @if(!$loop->first) hidden @endif>
                    @forelse($tabs[$key] as $row)
                    <a class="pp-row" href="{{ $row['url'] }}">
                        <span class="pj" title="{{ $row['project'] }}">{{ $row['project'] }}</span>
                        <span class="key" title="{{ $row['key'] }}">{{ $row['key'] }}</span>
                        <span class="sub" title="{{ $row['subject'] }}">{{ $row['subject'] }}</span>
                        {{-- slots, filled or not, so the columns hold --}}
                        <span class="prs">@if($row['priority'])<span class="pr" style="background:{{ $row['priority']['c'] }}">{{ $row['priority']['l'] }}</span>@endif</span>
                        <span class="sts">@if($row['status'])<span class="st" style="background:{{ $row['status']['c'] }}">{{ $row['status']['l'] }}</span>@endif</span>
                        <span class="due {{ $row['late'] ? 'late' : ($row['soon'] ? 'soon' : '') }}">{{ $row['due_label'] }}</span>
                    </a>
                    @empty
                    <div class="pp-empty">{{ $t('empty_' . $key) }}</div>
                    @endforelse
                    @if(count($tabs[$key]) >= $dashboard['limit'])
                    <div class="pp-more"><i class="fa fa-ellipsis-h"></i> {{ $t('more') }}</div>
                    @endif
                </div>
                @endforeach
                <div data-body="recent" hidden>
                    <div class="pp-empty">{{ $t('empty_recent') }}</div>
                </div>
            </section>
        </div>

        <div class="pp-right">
            <section class="pp-panel">
                <div class="pp-ph"><i class="fa fa-clock-o"></i> {{ $t('feed') }}</div>
                <div class="pp-feedbody">
                @php $lastDay = null; @endphp
                @forelse($dashboard['feed'] as $entry)
                @if($entry['day'] !== '' && $entry['day'] !== $lastDay)
                <div class="pp-fday">{{ $entry['day'] }}</div>
                @php $lastDay = $entry['day']; @endphp
                @endif
                <a class="pp-fd" href="{{ $entry['url'] }}">
                    <span class="av" style="background:{{ $entry['who']['c'] }}">{{ $entry['who']['i'] }}@if(!empty($entry['who']['a']))<img class="bl-avimg" src="{{ $entry['who']['a'] }}" alt="" loading="lazy" onerror="this.remove()">@endif</span>
                    <span class="bd">
                        <span class="ln1"><b>{{ $entry['who']['n'] }}</b>{{ $t('feed_' . $entry['tp']) }} · {{ $entry['at'] }} · {{ $entry['project'] }}</span>
                        <span class="ln2"><span class="key">{{ $entry['key'] }}</span> {{ $entry['label'] }}</span>
                        @if($entry['extra'] !== '')
                        <span class="ex">{{ $entry['extra'] }}</span>
                        @endif
                    </span>
                </a>
                @empty
                <div class="pp-empty">{{ $t('feed_empty') }}</div>
                @endforelse
                </div>
            </section>
        </div>
    </div>

    @if($create['can'])
    @php
        $npj = function ($key, $args = []) {
            return PortalDefinition::trans('newpj.' . $key, $args);
        };
    @endphp
    {{-- Backlog's add-project form: a name and a key, nothing else in the
         way. Opens from the panel's ＋, the top bar entry, or #new-project. --}}
    <style>
    .pp-npj-back{position:fixed;inset:0;background:rgba(30,40,48,.45);z-index:1040;}
    .pp-npj-panel{position:fixed;left:50%;top:16vh;transform:translateX(-50%);width:min(440px,92vw);
        background:#fff;border-radius:8px;box-shadow:0 14px 44px rgba(20,30,40,.3);z-index:1041;overflow:hidden;}
    .pp-npj-head{display:flex;align-items:center;gap:8px;padding:13px 18px;font-weight:700;font-size:14px;
        border-bottom:1px solid var(--d-line);background:#fbfcfd;}
    .pp-npj-x{margin-left:auto;cursor:pointer;color:var(--d-mute);font-size:15px;padding:2px 5px;}
    .pp-npj-x:hover{color:var(--d-text);}
    .pp-npj-body{padding:16px 18px 18px;display:flex;flex-direction:column;gap:12px;}
    .pp-npj-body label{display:flex;flex-direction:column;gap:5px;font-weight:700;font-size:12.5px;margin:0;}
    .pp-npj-body label .req{color:var(--d-red);font-weight:400;}
    .pp-npj-body input,.pp-npj-body select{border:1px solid var(--d-line);border-radius:5px;padding:8px 10px;
        font-size:13px;font-weight:400;width:100%;background:#fff;color:var(--d-text);}
    .pp-npj-body input:focus,.pp-npj-body select:focus{outline:none;border-color:var(--d-accent);
        box-shadow:0 0 0 2px rgba(76,174,147,.18);}
    .pp-npj-hint{color:var(--d-mute);font-size:11.5px;font-weight:400;}
    .pp-npj-err{color:var(--d-red);font-size:11.5px;font-weight:400;display:none;white-space:pre-line;}
    .pp-npj-err.on{display:block;}
    .pp-npj-foot{display:flex;justify-content:flex-end;gap:8px;margin-top:2px;}
    .pp-npj-foot button{border-radius:5px;padding:8px 16px;font-size:13px;cursor:pointer;border:1px solid var(--d-line);background:#fff;color:var(--d-text);}
    .pp-npj-foot .main{background:var(--d-accent);border-color:var(--d-accent);color:#fff;font-weight:700;}
    .pp-npj-foot .main:disabled{opacity:.6;cursor:default;}
    </style>
    <div class="pp-npj" id="pp-npj" hidden>
        <div class="pp-npj-back"></div>
        <div class="pp-npj-panel" role="dialog" aria-modal="true" aria-labelledby="pp-npj-title">
            <div class="pp-npj-head">
                <i class="fa fa-folder-open-o"></i><span id="pp-npj-title">{{ $npj('title') }}</span>
                <span class="pp-npj-x" role="button" tabindex="0" aria-label="{{ $npj('cancel') }}"><i class="fa fa-times"></i></span>
            </div>
            <form class="pp-npj-body" id="pp-npj-form" autocomplete="off">
                <label>{{ $npj('name') }} <span class="req">*</span>
                    <input id="pp-npj-name" data-col="{{ $create['name_col'] }}" placeholder="{{ $npj('name_ph') }}">
                </label>
                <div class="pp-npj-err" data-err="{{ $create['name_col'] }}"></div>
                <label>{{ $npj('key') }} <span class="req">*</span>
                    <input id="pp-npj-key" data-col="{{ $create['key_col'] }}" placeholder="{{ $npj('key_ph') }}"
                           maxlength="{{ $create['code_max'] }}" style="text-transform:uppercase;">
                </label>
                <div class="pp-npj-hint">{{ $npj('key_help', ['max' => $create['code_max']]) }}</div>
                <div class="pp-npj-err" data-err="{{ $create['key_col'] }}"></div>
                @if($create['parent_col'])
                <label>{{ $npj('parent') }}
                    <select id="pp-npj-parent" data-col="{{ $create['parent_col'] }}">
                        <option value="">{{ $npj('parent_none') }}</option>
                        @foreach($projects as $project)
                        @if(!$project['archived'])
                        <option value="{{ $project['id'] }}">{{ $project['name'] }}</option>
                        @endif
                        @endforeach
                    </select>
                </label>
                <div class="pp-npj-err" data-err="{{ $create['parent_col'] }}"></div>
                @endif
                <div class="pp-npj-err" data-err="_top"></div>
                <div class="pp-npj-foot">
                    <button type="button" class="sub" id="pp-npj-cancel">{{ $npj('cancel') }}</button>
                    <button type="submit" class="main" id="pp-npj-submit">{{ $npj('create') }}</button>
                </div>
            </form>
        </div>
    </div>
    @endif
</div>

<script>
(function () {
    var root = document.querySelector('.pp-dash');
    if (!root) { return; }

    // ---- project tree: fold (▼/▶, すべて開く・閉じる, double-click) and
    //      drag & drop order for administrators (snoro 2026-11-18) ----------
    (function () {
        var rows = function () { return root.querySelectorAll('.pp-pj[data-id]'); };
        var KEY = 'pmportal.dpjfold';
        var fold = {};
        try {
            var s = JSON.parse(localStorage.getItem(KEY) || '[]');
            if (Array.isArray(s)) { s.forEach(function (id) { fold[String(id)] = true; }); }
        } catch (e) { /* ignore */ }
        function save() { try { localStorage.setItem(KEY, JSON.stringify(Object.keys(fold))); } catch (e) { /* ignore */ } }
        function paint(b) {
            var shut = !!fold[b.getAttribute('data-tgl')];
            b.textContent = shut ? '▶' : '▼';
            b.title = shut ? '開く' : '閉じる';
            b.setAttribute('aria-expanded', shut ? 'false' : 'true');
        }
        function apply() {
            var cut = -1;
            rows().forEach(function (r) {
                var d = Number(r.getAttribute('data-depth') || 0);
                if (cut >= 0 && d > cut) { r.hidden = true; return; }
                r.hidden = false;
                cut = (r.getAttribute('data-kids') && fold[r.getAttribute('data-id')]) ? d : -1;
            });
        }
        function build() {
            var kids = {}, any = false;
            rows().forEach(function (r) {
                var p = r.getAttribute('data-parent');
                if (p && Number(r.getAttribute('data-depth') || 0) > 0) { kids[p] = (kids[p] || 0) + 1; }
            });
            rows().forEach(function (r) {
                var id = r.getAttribute('data-id'), slot = r.querySelector('.pp-dtgl-slot');
                if (!slot) { return; }
                slot.textContent = '';
                if (!kids[id]) { r.removeAttribute('data-kids'); return; }
                any = true;
                r.setAttribute('data-kids', String(kids[id]));
                var b = document.createElement('button');
                b.type = 'button';
                b.className = 'pp-dtgl';
                b.setAttribute('data-tgl', id);
                paint(b);
                slot.appendChild(b);
            });
            var ctl = document.getElementById('pp-dfold');
            if (ctl) { ctl.hidden = !any; }
            apply();
        }
        function all(close) {
            fold = {};
            if (close) { rows().forEach(function (r) { if (r.getAttribute('data-kids')) { fold[r.getAttribute('data-id')] = true; } }); }
            save();
            root.querySelectorAll('.pp-dtgl').forEach(paint);
            apply();
        }
        root.addEventListener('click', function (ev) {
            var b = ev.target.closest('.pp-dtgl');
            if (!b) { return; }
            ev.preventDefault();
            ev.stopPropagation();
            var id = b.getAttribute('data-tgl');
            if (fold[id]) { delete fold[id]; } else { fold[id] = true; }
            save(); paint(b); apply();
        });
        root.addEventListener('dblclick', function (ev) {
            var b = ev.target.closest('.pp-dtgl');
            if (!b) { return; }
            ev.preventDefault();
            ev.stopPropagation();
            all(!fold[b.getAttribute('data-tgl')]);
        });
        // ---- one line per project (snoro 2026-11-19): the whole line opens
        //      the project, and the launch links live in a menu behind the
        //      row's "…" button instead of replacing the numbers on hover.
        function closeLaunch(focusBack) {
            root.querySelectorAll('.pp-pj.open').forEach(function (r) {
                r.classList.remove('open');
                var m = r.querySelector('.pp-launch'), b = r.querySelector('.pp-pj-more');
                if (m) { m.hidden = true; m.classList.remove('up'); }
                if (b) { b.setAttribute('aria-expanded', 'false'); if (focusBack) { b.focus(); } }
            });
        }
        root.addEventListener('click', function (ev) {
            var more = ev.target.closest('.pp-pj-more');
            if (more) {
                ev.preventDefault();
                var row = more.closest('.pp-pj'), menu = row.querySelector('.pp-launch');
                var was = row.classList.contains('open');
                closeLaunch(false);
                if (was || !menu) { return; }
                row.classList.add('open');
                more.setAttribute('aria-expanded', 'true');
                menu.hidden = false;
                // opens upward when it would run off the bottom of the window
                if (menu.getBoundingClientRect().bottom > window.innerHeight - 8
                    && row.getBoundingClientRect().top > menu.offsetHeight + 8) { menu.classList.add('up'); }
                return;
            }
            var line = ev.target.closest('.pp-pj[data-id]');
            if (!line || ev.target.closest('a,button,input,select,.pp-launch')) { return; }
            var name = line.querySelector('.pp-pj-name');
            if (name) { name.click(); }
        });
        // Bound on document, which outlives this page under pjax: each
        // handler retires itself once its dashboard is gone.
        document.addEventListener('click', function onDoc(ev) {
            if (!document.body.contains(root)) { document.removeEventListener('click', onDoc); return; }
            if (!ev.target.closest('.pp-pj-more') && !ev.target.closest('.pp-launch')) { closeLaunch(false); }
        });
        document.addEventListener('keydown', function onEsc(ev) {
            if (!document.body.contains(root)) { document.removeEventListener('keydown', onEsc); return; }
            if (ev.key === 'Escape' && root.querySelector('.pp-pj.open')) { closeLaunch(true); }
        });

        var o = document.getElementById('pp-dfold-open'), c = document.getElementById('pp-dfold-close');
        if (o) { o.addEventListener('click', function (ev) { ev.preventDefault(); all(false); }); }
        if (c) { c.addEventListener('click', function (ev) { ev.preventDefault(); all(true); }); }

        // drag & drop among siblings; the subtree moves along
        var reorderUrl = @json($reorder_url ?? '');
        var drag = null;
        function subtree(r) {
            var out = [r], d = Number(r.getAttribute('data-depth') || 0), n = r.nextElementSibling;
            while (n && n.classList.contains('pp-pj') && Number(n.getAttribute('data-depth') || 0) > d) { out.push(n); n = n.nextElementSibling; }
            return out;
        }
        if (reorderUrl) {
            rows().forEach(function (r) {
                r.addEventListener('dragstart', function (ev) {
                    drag = r; r.classList.add('dragging');
                    try { ev.dataTransfer.effectAllowed = 'move'; ev.dataTransfer.setData('text/plain', r.getAttribute('data-id')); } catch (e) { /* ignore */ }
                });
                r.addEventListener('dragend', function () {
                    r.classList.remove('dragging');
                    root.querySelectorAll('.drop-before,.drop-after').forEach(function (x) { x.classList.remove('drop-before', 'drop-after'); });
                    drag = null;
                });
                r.addEventListener('dragover', function (ev) {
                    if (!drag || drag === r || drag.getAttribute('data-parent') !== r.getAttribute('data-parent')) { return; }
                    ev.preventDefault();
                    var box = r.getBoundingClientRect(), after = ev.clientY > box.top + box.height / 2;
                    r.classList.toggle('drop-after', after);
                    r.classList.toggle('drop-before', !after);
                });
                r.addEventListener('dragleave', function () { r.classList.remove('drop-before', 'drop-after'); });
                r.addEventListener('drop', function (ev) {
                    if (!drag || drag === r || drag.getAttribute('data-parent') !== r.getAttribute('data-parent')) { return; }
                    ev.preventDefault();
                    var after = r.classList.contains('drop-after');
                    r.classList.remove('drop-before', 'drop-after');
                    var moving = subtree(drag), anchor = after ? subtree(r) : [r];
                    var ref = after ? anchor[anchor.length - 1].nextElementSibling : anchor[0];
                    var parent = r.parentNode;
                    moving.forEach(function (m) { parent.insertBefore(m, ref); });
                    var pid = drag.getAttribute('data-parent'), ids = [];
                    rows().forEach(function (x) { if (x.getAttribute('data-parent') === pid) { ids.push(Number(x.getAttribute('data-id'))); } });
                    var token = document.querySelector('meta[name="csrf-token"]');
                    fetch(reorderUrl, {
                        method: 'POST', credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token ? token.getAttribute('content') : '', 'X-Requested-With': 'XMLHttpRequest' },
                        body: JSON.stringify({ ids: ids })
                    }).then(function (res) { if (!res.ok) { throw new Error(res.status); } })
                      .catch(function () { location.reload(); });
                });
            });
        }
        build();
    })();

    // ---- tabs ---------------------------------------------------------
    function showTab(name) {
        root.querySelectorAll('[data-tab]').forEach(function (b) {
            b.setAttribute('aria-selected', b.getAttribute('data-tab') === name ? 'true' : 'false');
        });
        root.querySelectorAll('[data-body]').forEach(function (d) {
            d.hidden = d.getAttribute('data-body') !== name;
        });
    }
    root.querySelectorAll('[data-tab]').forEach(function (b) {
        b.addEventListener('click', function () { showTab(b.getAttribute('data-tab')); });
    });
    // The KPI cards are the headline numbers; clicking one should show the
    // rows behind it rather than being decoration.
    root.querySelectorAll('[data-goto]').forEach(function (b) {
        b.addEventListener('click', function () { showTab(b.getAttribute('data-goto')); });
    });

    // ---- recently viewed ---------------------------------------------
    // Written by the portal page whenever an issue detail opens; read here.
    // Everything is inserted through textContent, because these strings come
    // back out of localStorage, not out of the server's escaping.
    (function () {
        var entries = [];
        // Kept per person by the issue screen (recentPush in script.blade.php).
        var recentKey = @json('pp_recent.u' . intval(\Exment::getUserId()));
        try { entries = JSON.parse(localStorage.getItem(recentKey) || '[]'); } catch (e) { entries = []; }
        if (!Array.isArray(entries) || !entries.length) { return; }
        var body = root.querySelector('[data-body="recent"]');
        var count = document.getElementById('pp-recent-count');
        if (!body || !count) { return; }
        body.textContent = '';
        entries.slice(0, 20).forEach(function (e) {
            if (!e || !e.u) { return; }
            var a = document.createElement('a');
            a.className = 'pp-row';
            a.href = String(e.u);
            var pj = document.createElement('span');
            pj.className = 'pj';
            pj.textContent = String(e.p || '');
            var key = document.createElement('span');
            key.className = 'key';
            key.textContent = String(e.k || '');
            var sub = document.createElement('span');
            sub.className = 'sub';
            sub.textContent = String(e.s || '');
            sub.title = sub.textContent;
            a.appendChild(pj); a.appendChild(key); a.appendChild(sub);
            body.appendChild(a);
        });
        count.textContent = String(body.children.length);
    })();

    // ---- star ---------------------------------------------------------
    var TOKEN = document.querySelector('meta[name="csrf-token"]');
    TOKEN = TOKEN ? TOKEN.getAttribute('content') : '';
    var TABLE = @json($project_table);

    root.querySelectorAll('[data-star]').forEach(function (b) {
        b.addEventListener('click', function () {
            if (b.disabled) { return; }
            var id = b.getAttribute('data-star');
            var on = b.getAttribute('aria-pressed') === 'true';
            b.disabled = true;
            // Flipped before the answer comes back, and put back if it fails:
            // a star is a one bit preference, and waiting on a round trip to
            // see it move makes the whole grid feel broken.
            paint(!on);
            fetch(b.getAttribute('data-url'), {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-TOKEN': TOKEN,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: 'star_type=star&target_type=' + encodeURIComponent(TABLE) + '&target_id=' + encodeURIComponent(id)
            }).then(function (r) {
                if (!r.ok) { throw new Error(r.status); }
                return r.json();
            }).then(function (j) {
                paint(!!j.on);
            }).catch(function () {
                paint(on);
            }).then(function () {
                b.disabled = false;
            });

            function paint(state) {
                b.setAttribute('aria-pressed', state ? 'true' : 'false');
                b.querySelector('i').className = 'fa ' + (state ? 'fa-star' : 'fa-star-o');
            }
        });
    });
})();
</script>

@if($create['can'])
@php
    // Hoisted: Blade 12 rejects a multi-line array literal inside @json().
    $npjLang = [
        'create' => PortalDefinition::trans('newpj.create'),
        'creating' => PortalDefinition::trans('newpj.creating'),
        'failed' => PortalDefinition::trans('newpj.failed'),
        'required' => PortalDefinition::trans('ned_required'),
        // The sentences for a failure the form did not word itself - an
        // expired session, a proxy's page, a dropped line - picked by the
        // HTTP status, so no English status text ever reaches the screen.
        'http' => PortalDefinition::httpErrors(),
    ];
@endphp
<script>
(function () {
    'use strict';
    var wrap = document.getElementById('pp-npj');
    if (!wrap || wrap.dataset.ppBound) { return; }
    wrap.dataset.ppBound = '1';

    var TOKEN = document.querySelector('meta[name="csrf-token"]');
    TOKEN = TOKEN ? TOKEN.getAttribute('content') : '';
    var API = @json($create['api']);
    var LANG = @json($npjLang);
    var DEFAULTS = @json($create['defaults']);

    var nameInput = document.getElementById('pp-npj-name');
    var keyInput = document.getElementById('pp-npj-key');
    var parentSelect = document.getElementById('pp-npj-parent');
    var submitBtn = document.getElementById('pp-npj-submit');

    // The same rule as the portal page: the server's own Japanese sentence
    // when it sent one (ok:false + message), otherwise one picked by the
    // HTTP status (0 = no answer), otherwise the form's own fallback. Never
    // the machine code, the raw detail or a transport string.
    function errText(status, body, fallback) {
        if (body && typeof body === 'object' && body.ok === false
            && typeof body.message === 'string' && body.message.trim() !== '') {
            return body.message;
        }
        var http = LANG.http || {};
        var said = http.messages || {};
        var key = (http.status && Object.prototype.hasOwnProperty.call(http.status, String(status)))
            ? http.status[String(status)] : null;
        if (!key && typeof status === 'number' && status >= 500) { key = 'server'; }
        var text = key && Object.prototype.hasOwnProperty.call(said, key) ? said[key] : '';
        return text || fallback || said.other || '';
    }
    function slot(name) {
        return wrap.querySelector('.pp-npj-err[data-err="' + name + '"]');
    }
    function clearErrors() {
        wrap.querySelectorAll('.pp-npj-err').forEach(function (el) {
            el.textContent = '';
            el.classList.remove('on');
        });
    }
    // Everything shown here is server text or user input - always assigned
    // through textContent, never markup.
    function putError(name, messages) {
        var el = slot(name) || slot('_top');
        if (!el) { return; }
        var text = Array.isArray(messages) ? messages.join('\n') : String(messages);
        el.textContent = (el.textContent ? el.textContent + '\n' : '') + text;
        el.classList.add('on');
    }

    function open() {
        clearErrors();
        wrap.hidden = false;
        window.requestAnimationFrame(function () { nameInput.focus(); });
    }
    function close() {
        wrap.hidden = true;
        clearErrors();
        // Leaving the hash behind would block the next #new-project click
        // from firing a hashchange at all.
        if (location.hash === '#new-project' && history.replaceState) {
            history.replaceState(null, '', location.pathname + location.search);
        }
    }

    var opener = document.getElementById('pp-newpj-open');
    if (opener) { opener.addEventListener('click', open); }
    wrap.querySelector('.pp-npj-back').addEventListener('click', close);
    wrap.querySelector('.pp-npj-x').addEventListener('click', close);
    document.getElementById('pp-npj-cancel').addEventListener('click', close);
    // pjax keeps these document/window listeners alive after this dashboard
    // is swapped out. The delegated click below would then swallow the top
    // bar's プロジェクトの追加 on the NEXT page - preventDefault on a link
    // that is supposed to navigate back here - so each handler retires
    // itself the moment its modal is no longer the one in the document.
    function alive() { return document.getElementById('pp-npj') === wrap; }
    document.addEventListener('keydown', function onKey(ev) {
        if (!alive()) { document.removeEventListener('keydown', onKey); return; }
        if (ev.key === 'Escape' && !wrap.hidden) { close(); }
    });
    // The top bar's プロジェクトの追加 points at this page with #new-project;
    // when this page is already open that navigation is a no-op, so the
    // click itself opens the modal.
    document.addEventListener('click', function onClick(ev) {
        if (!alive()) { document.removeEventListener('click', onClick); return; }
        var a = ev.target.closest('.pp-top-newpj');
        if (a) { ev.preventDefault(); open(); }
    });
    function openOnHash() {
        if (location.hash === '#new-project') { open(); }
    }
    window.addEventListener('hashchange', function onHash() {
        if (!alive()) { window.removeEventListener('hashchange', onHash); return; }
        openOnHash();
    });
    openOnHash();

    // The key rule is uppercase-only; typing lowercase and being told off
    // for it would be pointless friction.
    keyInput.addEventListener('input', function () {
        var pos = keyInput.selectionStart;
        keyInput.value = keyInput.value.toUpperCase();
        try { keyInput.setSelectionRange(pos, pos); } catch (e) { /* type=hidden etc. */ }
    });
    [nameInput, keyInput, parentSelect].forEach(function (el) {
        if (el) { el.addEventListener('input', clearErrors); }
    });

    document.getElementById('pp-npj-form').addEventListener('submit', function (ev) {
        ev.preventDefault();
        clearErrors();

        var missing = false;
        [nameInput, keyInput].forEach(function (el) {
            if (el.value.trim() === '') {
                putError(el.getAttribute('data-col'), LANG.required);
                missing = true;
            }
        });
        if (missing) { return; }

        // The column defaults first, so a new project starts with the same
        // switches the admin form would have offered; the typed fields win.
        var value = {};
        Object.keys(DEFAULTS).forEach(function (col) { value[col] = DEFAULTS[col]; });
        value[nameInput.getAttribute('data-col')] = nameInput.value.trim();
        value[keyInput.getAttribute('data-col')] = keyInput.value.trim();
        if (parentSelect && parentSelect.value !== '') {
            value[parentSelect.getAttribute('data-col')] = parentSelect.value;
        }

        submitBtn.disabled = true;
        submitBtn.textContent = LANG.creating;
        fetch(API, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': TOKEN,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ value: value })
        }).then(function (r) {
            return r.json().then(function (j) { return { status: r.status, body: j }; },
                function () { return { status: r.status, body: null }; });
        }, function () {
            return { status: 0, body: null };
        }).then(function (res) {
            var j = res.body || {};
            if (res.status === 200 && j.ok && j.id) {
                // Straight into the new project's settings tab, the way
                // Backlog lands on 基本設定 after a create.
                location.href = location.pathname.replace(/\/+$/, '') + '/' + j.id + '#settings';
                return;
            }
            // Per-field messages are the server's own Japanese; anything else
            // is worded from the status, never from the code or raw detail.
            if (j.ok === false && j.fields && typeof j.fields === 'object') {
                Object.keys(j.fields).forEach(function (col) { putError(col, j.fields[col]); });
            } else {
                putError('_top', errText(res.status, j, LANG.failed));
            }
            submitBtn.disabled = false;
            submitBtn.textContent = LANG.create;
        });
    });
})();
</script>
@endif
