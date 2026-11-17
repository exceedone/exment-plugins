@php
    use App\Plugins\PmPortal\PortalDefinition;

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
        'watched' => $t('tab_watched'),
        'closed' => $t('tab_closed'),
    ];

    // The launch menu a project row grows on hover - Backlog's, minus the
    // features the portal does not have (documents, git). Each entry is a
    // hash the portal's router already answers.
    $launch = [
        ['new', PortalDefinition::trans('add_issue')],
        ['issues', PortalDefinition::trans('tab_issues')],
        ['board', PortalDefinition::trans('tab_board')],
        ['gantt', PortalDefinition::trans('tab_gantt')],
        ['burndown', PortalDefinition::trans('tab_burndown')],
        ['wiki', PortalDefinition::trans('tab_wiki')],
        ['files', PortalDefinition::trans('tab_files')],
    ];
@endphp

{{-- Scoped to .pp-dash: this page renders inside the Exment admin layout,
     which has no portal stylesheet of its own. --}}
<style>
.pp-dash{--d-line:#dfe5ea;--d-text:#33434f;--d-mute:#8a97a3;--d-accent:#4cae93;--d-red:#e5544b;--d-amber:#eda62a;
    color:var(--d-text);font-size:13px;}
.pp-dash a{color:inherit;text-decoration:none;}
.pp-dash .pp-panel{background:#fff;border:1px solid var(--d-line);border-radius:8px;margin-bottom:16px;overflow:hidden;}
.pp-dash .pp-ph{display:flex;align-items:center;gap:10px;padding:11px 16px;border-bottom:1px solid var(--d-line);
    font-weight:700;font-size:13.5px;background:#fbfcfd;}
.pp-dash .pp-ph .pp-ph-right{margin-left:auto;font-weight:400;font-size:12px;}

/* two columns, projects in the top-left corner ------------------------- */
.pp-dash .pp-wrap{display:grid;grid-template-columns:minmax(0,1.08fr) minmax(0,1fr);gap:16px;align-items:start;}
@media (max-width:1100px){.pp-dash .pp-wrap{grid-template-columns:minmax(0,1fr);}}

/* project rows --------------------------------------------------------- */
.pp-dash .pp-pj{display:flex;gap:9px;padding:9px 14px;border-bottom:1px solid #eef2f5;align-items:flex-start;}
.pp-dash .pp-pj:last-child{border-bottom:0;}
.pp-dash .pp-pj.arch{background:#f6f7f8;opacity:.8;}
.pp-dash .pp-pj-main{flex:1 1 auto;min-width:0;display:flex;flex-direction:column;gap:5px;}
.pp-dash .pp-pj-l1{display:flex;align-items:center;gap:7px;flex-wrap:wrap;min-height:20px;}
.pp-dash .pp-pj-name{font-size:14px;font-weight:700;}
.pp-dash .pp-pj-name:hover{text-decoration:underline;}
.pp-dash .pp-pj-up{color:var(--d-mute);font-size:10.5px;}
.pp-dash .cd{background:#eef3f6;border:1px solid var(--d-line);border-radius:3px;padding:0 7px;
    font-weight:700;color:#51616f;font-size:11px;}
.pp-dash .pill{color:#fff;border-radius:20px;padding:1px 8px;font-size:10px;font-weight:700;}
.pp-dash .pp-pj-meta{display:flex;align-items:center;gap:9px;flex-wrap:wrap;font-size:11px;color:var(--d-mute);min-height:18px;}
.pp-dash .pp-pj-meta .pj-bar{flex:none;width:72px;height:5px;border-radius:3px;background:#eef2f5;overflow:hidden;}
.pp-dash .pp-pj-meta .pj-bar i{display:block;height:100%;background:var(--d-accent);}
.pp-dash .pp-pj-meta .od{color:var(--d-red);font-weight:700;}
.pp-dash .pp-pj-meta .mi{color:var(--d-accent);font-weight:700;}

/* The Backlog move: the row turns theme-green and the meta line gives its
   place to the launch links. Inline rather than a popup, so nothing needs
   z-index and nothing can be clipped by the panel. focus-within keeps the
   links reachable by keyboard. */
.pp-dash .pp-launch{display:none;align-items:center;flex-wrap:wrap;min-height:18px;}
.pp-dash .pp-pj:hover,.pp-dash .pp-pj:focus-within{background:var(--d-accent);}
.pp-dash .pp-pj:hover .pp-pj-meta,.pp-dash .pp-pj:focus-within .pp-pj-meta{display:none;}
.pp-dash .pp-pj:hover .pp-launch,.pp-dash .pp-pj:focus-within .pp-launch{display:flex;}
.pp-dash .pp-pj:hover .pp-pj-name,.pp-dash .pp-pj:focus-within .pp-pj-name,
.pp-dash .pp-pj:hover .pp-pj-up,.pp-dash .pp-pj:focus-within .pp-pj-up{color:#fff;}
.pp-dash .pp-pj:hover .cd,.pp-dash .pp-pj:focus-within .cd{background:rgba(255,255,255,.22);border-color:transparent;color:#fff;}
.pp-dash .pp-launch a{color:#fff;font-size:11.5px;padding:0 9px;border-left:1px solid rgba(255,255,255,.4);
    white-space:nowrap;line-height:1.5;}
.pp-dash .pp-launch a:first-child{padding-left:0;border-left:0;}
.pp-dash .pp-launch a:hover{text-decoration:underline;color:#fff;}

.pp-dash .pp-star{border:0;background:none;cursor:pointer;padding:1px 2px;font-size:15px;color:#c9d2d9;line-height:1;flex:none;margin-top:1px;}
.pp-dash .pp-star:hover{color:var(--d-amber);}
.pp-dash .pp-star[aria-pressed="true"]{color:var(--d-amber);}

/* KPI ------------------------------------------------------------------ */
.pp-dash .pp-kpi{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px;margin-bottom:16px;}
.pp-dash .pp-kpi button{display:flex;flex-direction:column;gap:1px;text-align:left;background:#fff;
    border:1px solid var(--d-line);border-left:4px solid var(--d-accent);border-radius:8px;padding:9px 13px;
    cursor:pointer;font:inherit;color:inherit;width:100%;transition:box-shadow .12s,transform .12s;}
.pp-dash .pp-kpi button:hover{box-shadow:0 2px 10px rgba(51,67,79,.13);transform:translateY(-1px);}
.pp-dash .pp-kpi button:focus-visible{outline:2px solid var(--d-accent);outline-offset:2px;}
.pp-dash .pp-kpi .n{font-size:22px;font-weight:700;line-height:1.15;}
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
.pp-dash .pp-tabs button{border:0;background:none;font:inherit;color:var(--d-mute);cursor:pointer;padding:10px 7px;
    border-bottom:2px solid transparent;white-space:nowrap;font-size:12.5px;}
.pp-dash .pp-tabs button:hover{color:var(--d-text);}
.pp-dash .pp-tabs button[aria-selected="true"]{color:var(--d-text);font-weight:700;border-bottom-color:var(--d-accent);}
.pp-dash .pp-tabs .c{display:inline-block;margin-left:5px;background:#eef3f6;border-radius:9px;padding:0 6px;
    font-size:11px;font-weight:700;color:#51616f;}
.pp-dash .pp-tabs button[aria-selected="true"] .c{background:var(--d-accent);color:#fff;}

/* Same cap as the feed, plus a floor so an empty tab does not collapse the
   panel to a strip and make the two columns look broken. */
.pp-dash [data-body]{min-height:180px;max-height:520px;overflow-y:auto;overscroll-behavior:contain;}
.pp-dash .pp-row{display:flex;align-items:center;gap:10px;padding:9px 16px;border-bottom:1px solid #eef2f5;}
.pp-dash .pp-row:last-child{border-bottom:0;}
.pp-dash .pp-row:hover{background:#f7fafb;}
.pp-dash .pp-row .pj{flex:none;max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;
    font-size:11px;color:var(--d-mute);}
.pp-dash .pp-row .key{flex:none;font-size:11.5px;font-weight:700;color:#3c8dbc;font-family:Menlo,Consolas,monospace;}
.pp-dash .pp-row .sub{flex:1 1 auto;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.pp-dash .pp-row:hover .sub{text-decoration:underline;}
.pp-dash .pp-row .st{flex:none;color:#fff;border-radius:20px;padding:1px 9px;font-size:10.5px;font-weight:700;}
.pp-dash .pp-row .pr{flex:none;color:#fff;border-radius:20px;padding:1px 8px;font-size:10px;font-weight:700;}
@media (max-width:1400px){.pp-dash .pp-row .pr{display:none;}}
.pp-dash .pp-row .due{flex:none;font-size:11.5px;color:var(--d-mute);min-width:82px;text-align:right;}
.pp-dash .pp-row .due.late{color:var(--d-red);font-weight:700;}
.pp-dash .pp-row .due.soon{color:#b9791a;font-weight:700;}
.pp-dash .pp-empty{padding:26px 16px;text-align:center;color:var(--d-mute);font-size:12.5px;}
.pp-dash .pp-more{padding:8px 16px;font-size:11.5px;color:var(--d-mute);background:#fbfcfd;border-top:1px solid #eef2f5;}

/* feed ----------------------------------------------------------------- */
/* Capped and scrolled inside itself, so a long feed cannot push everything
   below it off the screen. */
.pp-dash .pp-feedbody{max-height:760px;overflow-y:auto;overscroll-behavior:contain;}
.pp-dash .pp-fday{position:sticky;top:0;background:#f4f7f9;border-bottom:1px solid var(--d-line);
    padding:6px 16px;font-size:11.5px;font-weight:700;color:#51616f;z-index:1;}
.pp-dash .pp-fd{display:flex;gap:9px;padding:10px 16px;border-bottom:1px solid #eef2f5;align-items:flex-start;}
.pp-dash .pp-fd:last-child{border-bottom:0;}
.pp-dash .pp-fd:hover{background:#f7fafb;}
.pp-dash .pp-fd .av{flex:none;width:26px;height:26px;border-radius:50%;color:#fff;display:flex;align-items:center;
    justify-content:center;font-size:12px;font-weight:700;}
.pp-dash .pp-fd .bd{flex:1 1 auto;min-width:0;}
.pp-dash .pp-fd .ln1{font-size:11.5px;color:var(--d-mute);}
.pp-dash .pp-fd .ln1 b{color:var(--d-text);}
.pp-dash .pp-fd .ln2{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:12.5px;}
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
            <section class="pp-panel">
                <div class="pp-ph">
                    <i class="fa fa-folder-open-o"></i> {{ $t('projects') }}
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
                @forelse($projects as $project)
                <div class="pp-pj {{ $project['archived'] ? 'arch' : '' }}">
                    <button type="button" class="pp-star" data-star="{{ $project['id'] }}"
                            data-url="{{ $project['star_url'] }}"
                            aria-pressed="{{ $project['starred'] ? 'true' : 'false' }}"
                            title="{{ $project['starred'] ? $t('star_on') : $t('star_off') }}">
                        <i class="fa {{ $project['starred'] ? 'fa-star' : 'fa-star-o' }}"></i>
                    </button>
                    <div class="pp-pj-main">
                        <div class="pp-pj-l1">
                            <a class="pp-pj-name" href="{{ $project['url'] }}">{{ $project['name'] }}</a>
                            @if($project['key'] !== '')
                            <span class="cd">{{ $project['key'] }}</span>
                            @endif
                            @if($project['status'])
                            <span class="pill" style="background:{{ $project['status']['c'] }}">{{ $project['status']['l'] }}</span>
                            @endif
                            @if($project['archived'])
                            <span class="pill" style="background:#7f8c8d">{{ PortalDefinition::trans('archived_tag') }}</span>
                            @endif
                            @if($project['parent'])
                            <span class="pp-pj-up"><i class="fa fa-level-up fa-rotate-90"></i> {{ $project['parent'] }}</span>
                            @endif
                        </div>
                        <div class="pp-pj-meta">
                            <span class="pj-bar" title="{{ $t('progress') }} {{ $project['progress'] }}%"><i style="width:{{ $project['progress'] }}%"></i></span>
                            <span>{{ $project['progress'] }}%</span>
                            <span>{{ $t('card_open', ['count' => $project['open']]) }}</span>
                            @if($project['overdue'] > 0)
                            <span class="od">{{ $t('card_overdue', ['count' => $project['overdue']]) }}</span>
                            @endif
                            @if($project['mine'] > 0)
                            <span class="mi">{{ $t('card_mine', ['count' => $project['mine']]) }}</span>
                            @endif
                        </div>
                        <div class="pp-launch">
                            @foreach($launch as $item)
                            @if($item[0] !== 'new' || !empty($project['can_add']))
                            <a href="{{ $project['url'] }}#{{ $item[0] }}">{{ $item[1] }}</a>
                            @endif
                            @endforeach
                            @if(!empty($project['can_edit']))
                            <a href="{{ $project['url'] }}#settings"><i class="fa fa-cog"></i> {{ PortalDefinition::trans('tab_settings') }}</a>
                            @endif
                        </div>
                    </div>
                </div>
                @empty
                <div class="pp-empty">{{ PortalDefinition::trans('empty') }}</div>
                @endforelse
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
                        <span class="key">{{ $row['key'] }}</span>
                        <span class="sub" title="{{ $row['subject'] }}">{{ $row['subject'] }}</span>
                        @if($row['priority'])
                        <span class="pr" style="background:{{ $row['priority']['c'] }}">{{ $row['priority']['l'] }}</span>
                        @endif
                        @if($row['status'])
                        <span class="st" style="background:{{ $row['status']['c'] }}">{{ $row['status']['l'] }}</span>
                        @endif
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
                    <span class="av" style="background:{{ $entry['who']['c'] }}">{{ $entry['who']['i'] }}</span>
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
        try { entries = JSON.parse(localStorage.getItem('pp_recent') || '[]'); } catch (e) { entries = []; }
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
