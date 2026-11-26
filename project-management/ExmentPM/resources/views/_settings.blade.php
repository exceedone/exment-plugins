@php
    use App\Plugins\ExmentPM\PortalDefinition;

    $st = function ($key, $args = []) {
        return PortalDefinition::trans('set.' . $key, $args);
    };

    // Whole blocks at once: a group key hands back the array, and the JS
    // does the ":key" substitution the same way the server would.
    // Hoisted out of @json() because Blade 12's bracket validator rejects a
    // multi-line array literal inside directive parentheses.
    $setLang = [
        'set' => PortalDefinition::trans('set'),
        'git' => PortalDefinition::trans('git_repo'),
        'fs' => PortalDefinition::trans('fileserver'),
        'ch' => PortalDefinition::trans('channels'),
        'ai' => PortalDefinition::trans('ai'),
        'required' => PortalDefinition::trans('ned_required'),
        'none' => PortalDefinition::trans('ned_none'),
        'lkp_search' => PortalDefinition::trans('lkp_search'),
        'lkp_empty' => PortalDefinition::trans('lkp_empty'),
        'm_loading' => PortalDefinition::trans('m_loading'),
        // The sentences a failure is worded with when the answer is not one
        // of the portal's own (errText below).
        'http' => PortalDefinition::httpErrors(),
    ];

    $pjId = $payload['project']['id'];
    $pjKey = $payload['project']['key'] !== '' ? $payload['project']['key'] : strval($pjId);
    $apiBase = $payload['urls']['lightapi_base'] . '/data/' . $payload['project_table'] . '/' . $pjId;

    // The per project master lists the tab manages, in Backlog's own order.
    // A list whose table this install has not got is left out rather than
    // shown as an empty box nothing can fill. This tab is the only screen
    // that edits them: the space-wide settings page keeps to what every
    // project shares.
    $setDef = PortalDefinition::config();
    $setLists = [];
    $setListRoles = [
        ['statuses', 'status_table', 'list_statuses_title', 'fa-flag-o', true, false],
        ['types', 'issue_type_table', 'list_types_title', 'fa-tag', true, false],
        ['categories', 'category_table', 'list_categories_title', 'fa-folder-o', false, false],
        ['milestones', 'milestone_table', 'list_milestones_title', 'fa-flag-checkered', false, true],
    ];
    // What only one of the lists carries, by the column that holds it: a
    // status counts as done or not, an issue type prefills a new issue. A
    // column this install has not got simply leaves its control out.
    $setListColumn = function ($listTable, $rowKey, $role) use ($setDef) {
        $name = strval(array_get($setDef, $rowKey . '.' . $role));

        return $name !== '' && \Exceedone\Exment\Model\CustomColumn::getEloquent($name, $listTable) !== null ? $name : null;
    };
    foreach ($setListRoles as list($secKey, $roleKey, $titleKey, $icon, $hasColor, $hasDates)) {
        $tableName = array_get($setDef, $roleKey);
        if (is_nullorempty($tableName)) {
            continue;
        }
        $listTable = \Exceedone\Exment\Model\CustomTable::getEloquent($tableName);
        if (!isset($listTable)) {
            continue;
        }
        $setLists[$secKey] = [
            'table' => $tableName,
            'title' => $st($titleKey),
            'icon' => $icon,
            'hint' => $secKey === 'statuses' ? $st('list_statuses_hint') : ($secKey === 'types' ? $st('list_types_hint') : ''),
            'color' => $hasColor,
            'dates' => $hasDates,
            'done' => $secKey === 'statuses' ? $setListColumn($listTable, 'status_row', 'done') : null,
            'tpl_subject' => $secKey === 'types' ? $setListColumn($listTable, 'issue_type_row', 'template_subject') : null,
            'tpl_body' => $secKey === 'types' ? $setListColumn($listTable, 'issue_type_row', 'template_description') : null,
            // Statuses and issue types hang off the project as Exment
            // children; categories and milestones carry a project column.
            // The pane has to know which, or it creates rows the project
            // screen will never find again.
            'link' => \Exceedone\Exment\Model\CustomColumn::getEloquent('project', $listTable) !== null ? 'column' : 'parent',
            // display_order only exists after the schema 9 upgrade; without
            // it the rows still list and edit, they just cannot be dragged.
            'sortable' => \Exceedone\Exment\Model\CustomColumn::getEloquent('display_order', $listTable) !== null,
        ];
    }

    // Everything the pane needs, as its own small blob: re-parsing the full
    // portal payload for a handful of ids would cost more than it carries.
    $setData = [
        'table' => $payload['project_table'],
        'id' => strval($pjId),
        'key' => $pjKey,
        'edit_url' => $apiBase . '/edit',
        'save_url' => $apiBase,
        'git_url' => $payload['urls']['git_repo'],
        'git_keys_url' => $payload['urls']['git_keys'],
        'git_key_remove_url' => $payload['urls']['git_key_remove'],
        'git_token_url' => $payload['urls']['git_token'],
        'file_workspace_url' => $payload['urls']['file_workspace'] ?? null,
        'fileserver' => $payload['fileserver'] ?? ['on' => false],
        'channels_url' => $payload['urls']['channels'] ?? null,
        'channels' => $payload['channels'] ?? ['on' => false],
        'delete_url' => $payload['urls']['delete_project'],
        'dashboard_url' => $payload['urls']['dashboard'],
        'map' => $payload['settings_map'] ?? [],
        'git' => $payload['git'],
        'git_on' => boolval(array_get($payload, 'project.features.git')),
        // The AI pane: whether a key exists and the thresholds, never the key.
        'ai' => $payload['ai'] ?? [],
        'ai_state_url' => $payload['urls']['ai_state'] ?? null,
        'ai_test_url' => $payload['urls']['ai_test'] ?? null,
        'can_delete' => boolval(array_get($payload, 'can.delete')),
        'can_people' => boolval(array_get($payload, 'can.people')),
        'lightapi' => $payload['urls']['lightapi_base'],
        'members_url' => $payload['urls']['lightapi_base'] . '/project/' . $pjId . '/members',
        'lists' => $setLists,
    ];

    // Columns this deployment added to the project table itself. Everything
    // the portal knows by role is already edited by a pane above, so what is
    // left over is the only thing a "edit every field" dialog would have
    // added - and on a standard install there is nothing left over at all.
    $setMapped = [];
    foreach ($setData['map'] as $role => $mappedName) {
        if ($role === 'features' || $role === 'ai') {
            $setMapped = array_merge($setMapped, (array) $mappedName);
            continue;
        }
        if (!is_nullorempty($mappedName)) {
            $setMapped[] = $mappedName;
        }
    }
    $setOther = [];
    $setProjectTable = \Exceedone\Exment\Model\CustomTable::getEloquent($payload['project_table']);
    if (isset($setProjectTable)) {
        foreach ($setProjectTable->custom_columns as $projectColumn) {
            if (!in_array($projectColumn->column_name, $setMapped, true)) {
                $setOther[] = $projectColumn->column_name;
            }
        }
    }
    $setData['other'] = $setOther;

    // Sub-pages of the tab, in Backlog's reading order. Git only appears on
    // an install whose schema has the switch; delete only for who may.
    // S8: 使うタブ second, where it is looked for (it was after the lists).
    $setSections = [['basic', $st('tab_basic'), 'fa-sliders']];
    if (!empty($setData['map']['features'])) {
        $setSections[] = ['features', $st('tab_features'), 'fa-toggle-on'];
    }
    $setSections[] = ['people', $st('tab_people'), 'fa-users'];
    foreach ($setLists as $secKey => $list) {
        $setSections[] = [$secKey, $st('tab_' . $secKey), $list['icon']];
    }
    if (!empty($setOther)) {
        $setSections[] = ['other', $st('tab_other'), 'fa-list-ul'];
    }
    // The AI pane exists once the schema 14 columns do.
    if (!empty($setData['map']['ai'])) {
        $setSections[] = ['ai', $st('tab_ai'), 'fa-magic'];
    }
    if (in_array('use_git', (array) array_get($setData, 'map.features', []), true)) {
        $setSections[] = ['git', $st('tab_git'), 'fa-code-fork'];
    }
    // The workspace of the Files tab, while the feature is on (ISS02825).
    if (boolval(array_get($setData, 'fileserver.on'))) {
        $setSections[] = ['fileserver', $st('tab_fileserver'), 'fa-folder-open'];
    }
    // The chat channels, while the feature is on (ISS02939).
    if (boolval(array_get($setData, 'channels.on'))) {
        $setSections[] = ['channels', $st('tab_channels'), 'fa-comments-o'];
    }
    if ($setData['can_delete']) {
        $setSections[] = ['danger', $st('tab_danger'), 'fa-trash-o'];
    }
@endphp

<style>
.pmportal .pp-set{display:grid;grid-template-columns:190px minmax(0,1fr);gap:14px;align-items:start;}
@media (max-width:900px){.pmportal .pp-set{grid-template-columns:minmax(0,1fr);}}
.pmportal .pp-set-nav{background:var(--bl-box);border:1px solid var(--bl-line);border-radius:6px;overflow:hidden;}
.pmportal .pp-set-nav a{display:flex;align-items:center;gap:8px;padding:10px 13px;color:var(--bl-sub);
  border-bottom:1px solid var(--bl-line);cursor:pointer;font-size:12.5px;}
.pmportal .pp-set-nav a:last-child{border-bottom:0;}
.pmportal .pp-set-nav a:hover{background:var(--bl-hover);color:var(--bl-ink);}
.pmportal .pp-set-nav a.on{background:var(--bl-hover);color:var(--bl-accent-d);font-weight:700;
  box-shadow:inset 3px 0 0 var(--bl-accent);}
.pmportal .pp-set-nav a .fa{width:14px;text-align:center;}
.pmportal .pp-set-nav a.dg{color:var(--bl-red);}

.pmportal .pp-set-pane{background:var(--bl-box);border:1px solid var(--bl-line);border-radius:6px;min-height:220px;}
.pmportal .pp-set-head{display:flex;align-items:center;gap:8px;padding:11px 16px;border-bottom:1px solid var(--bl-line);
  font-weight:700;background:var(--bl-box2);border-radius:6px 6px 0 0;}
.pmportal .pp-set-body{padding:16px;}
.pmportal .pp-set-spin{padding:26px 16px;color:var(--bl-sub);}
.pmportal .pp-set-note{color:var(--bl-sub);font-size:12px;margin-bottom:14px;line-height:1.6;}

.pmportal .pp-set-f{margin-bottom:14px;max-width:520px;}
.pmportal .pp-set-lab{display:block;font-weight:700;font-size:12.5px;margin-bottom:4px;}
.pmportal .pp-set-lab .req{color:var(--bl-red);}
.pmportal .pp-set-in{width:100%;border:1px solid var(--bl-line);border-radius:4px;padding:7px 9px;font-size:13px;
  background:var(--bl-box);color:var(--bl-ink);}
.pmportal .pp-set-in:focus{outline:none;border-color:var(--bl-accent);box-shadow:0 0 0 2px rgba(76,175,147,.18);}
.pmportal select.pp-set-in[multiple]{min-height:120px;}
.pmportal .pp-set-f .help{color:var(--bl-faint);font-size:11.5px;margin-top:4px;line-height:1.6;}
.pmportal .pp-set-f .err{color:var(--bl-red);font-size:11.5px;margin-top:4px;white-space:pre-line;}

/* feature switches: label on the right, Backlog style */
.pmportal .pp-set-sw{display:flex;align-items:flex-start;gap:9px;padding:9px 0;border-bottom:1px solid var(--bl-line);}
.pmportal .pp-set-sw:last-child{border-bottom:0;}
.pmportal .pp-set-sw input{margin:2px 0 0;flex:none;width:15px;height:15px;}
.pmportal .pp-set-sw .tx{min-width:0;}
.pmportal .pp-set-sw .nm{font-weight:700;font-size:12.5px;}
.pmportal .pp-set-sw .hp{color:var(--bl-faint);font-size:11.5px;margin-top:2px;line-height:1.6;}
.pmportal .pp-set-sub{padding-left:24px;}
.pmportal .pp-set-sw.off .tx{opacity:.5;}

.pmportal .pp-set-foot{display:flex;align-items:center;gap:12px;padding:12px 16px;border-top:1px solid var(--bl-line);
  background:var(--bl-box2);border-radius:0 0 6px 6px;}
.pmportal .pp-set-msg{font-size:12px;color:var(--bl-sub);white-space:pre-line;}
.pmportal .pp-set-msg.err{color:var(--bl-red);}
.pmportal .pp-set-msg.ok{color:var(--bl-accent-d);}
.pmportal .pp-set-foot .sp{flex:1 1 auto;}
.pmportal .pp-set-btn{border:1px solid var(--bl-line);background:var(--bl-box);color:var(--bl-ink);border-radius:4px;
  padding:7px 15px;font-size:12.5px;cursor:pointer;white-space:nowrap;flex:none;}
.pmportal .pp-set-btn:hover{background:var(--bl-hover);}
.pmportal .pp-set-btn.main{background:var(--bl-accent);border-color:var(--bl-accent);color:#fff;font-weight:700;}
.pmportal .pp-set-btn.main:hover{background:var(--bl-accent-d);}
.pmportal .pp-set-btn.dg{background:var(--bl-red);border-color:var(--bl-red);color:#fff;font-weight:700;}
.pmportal .pp-set-btn:disabled{opacity:.55;cursor:default;}
.pmportal .pp-set-link{color:var(--bl-accent-d);cursor:pointer;font-size:12px;}
.pmportal .pp-set-link:hover{text-decoration:underline;}

/* git */
.pmportal .pp-set-chip{display:inline-block;border-radius:20px;padding:1px 10px;font-size:11px;font-weight:700;color:#fff;}
.pmportal .pp-set-chip.s-none{background:var(--bl-faint);}
.pmportal .pp-set-chip.s-pending{background:var(--bl-amber);}
.pmportal .pp-set-chip.s-ready{background:var(--bl-accent);}
.pmportal .pp-set-chip.s-failed{background:var(--bl-red);}
.pmportal .pp-set-url{font-family:Menlo,Consolas,monospace;font-size:12px;background:var(--bl-chip);
  border:1px solid var(--bl-line);border-radius:4px;padding:6px 9px;word-break:break-all;}
.pmportal a.pp-set-url{display:block;color:var(--bl-accent-d);text-decoration:none;}
.pmportal a.pp-set-url:hover{text-decoration:underline;}
.pmportal .pp-set-f textarea.pp-set-in{font-family:Menlo,Consolas,monospace;font-size:11.5px;
  line-height:1.5;resize:vertical;}
.pmportal .pp-set-how{border:1px solid var(--bl-line);border-radius:4px;padding:8px 10px;
  margin:0 0 10px;font-size:12px;}
.pmportal .pp-set-how summary{cursor:pointer;color:var(--bl-accent-d);font-weight:700;}
.pmportal .pp-set-how .st{margin:9px 0 4px;line-height:1.7;color:var(--bl-sub);}
.pmportal .pp-set-how code{display:block;font-family:Menlo,Consolas,monospace;font-size:11.5px;
  background:var(--bl-chip);border:1px solid var(--bl-line);border-radius:3px;padding:5px 8px;
  word-break:break-all;white-space:pre-wrap;user-select:all;}
.pmportal .pp-set-how .nt{margin-top:9px;color:var(--bl-faint);line-height:1.7;}
.pmportal .pp-set-keys{border:1px solid var(--bl-line);border-radius:4px;margin:0 0 10px;}
.pmportal .pp-set-keys .row{display:flex;align-items:center;gap:10px;padding:7px 10px;
  border-bottom:1px solid var(--bl-line);font-size:12px;}
.pmportal .pp-set-keys .row:last-child{border-bottom:none;}
.pmportal .pp-set-keys .ttl{font-weight:700;white-space:nowrap;}
.pmportal .pp-set-keys .who{color:var(--bl-sub);white-space:nowrap;}
.pmportal .pp-set-keys .who.me{color:var(--bl-accent-d);font-weight:700;}
.pmportal .pp-set-keys .fp{flex:1;font-family:Menlo,Consolas,monospace;color:var(--bl-sub);word-break:break-all;}
.pmportal .pp-set-keys .at{color:var(--bl-faint);white-space:nowrap;}
.pmportal .pp-set-keys .none{padding:9px 10px;color:var(--bl-faint);font-size:12px;}
.pmportal .pp-set-keys .del{border:1px solid var(--bl-line);background:var(--bl-box);color:var(--bl-red);
  border-radius:3px;font-size:11px;padding:3px 8px;cursor:pointer;white-space:nowrap;}
.pmportal .pp-set-keys .del:disabled{opacity:.55;cursor:default;}
.pmportal .pp-set-keys .ty{border:1px solid var(--bl-line);border-radius:9px;padding:1px 7px;
  font-size:10.5px;color:var(--bl-sub);white-space:nowrap;}
.pmportal .pp-set-tok{margin:0 0 10px;font-size:12px;}
.pmportal .pp-set-tok .st{margin:0 0 8px;line-height:1.7;color:var(--bl-sub);}
.pmportal .pp-set-tok .out{margin-top:10px;}
.pmportal .pp-set-tok .out .nt{color:var(--bl-red);line-height:1.7;}
.pmportal .pp-set-tok .out code{display:block;font-family:Menlo,Consolas,monospace;font-size:11.5px;
  background:var(--bl-chip);border:1px solid var(--bl-line);border-radius:3px;padding:6px 8px;
  margin-top:6px;word-break:break-all;white-space:pre-wrap;user-select:all;}
.pmportal .pp-set-warn{background:var(--bl-chip);border:1px solid var(--bl-line);border-left:3px solid var(--bl-amber);
  border-radius:4px;padding:9px 12px;font-size:12px;line-height:1.7;margin-bottom:14px;}
.pmportal .pp-set-danger{border:1px solid var(--bl-red);border-radius:5px;padding:14px;}
.pmportal .pp-set-danger .hd{color:var(--bl-red);font-weight:700;margin-bottom:8px;}

/* member roster + master lists */
.pmportal .pp-set-sub{font-weight:700;font-size:13px;margin:18px 0 8px;}
.pmportal .pp-set-sub:first-child{margin-top:0;}
.pmportal .pp-set-tbl{width:100%;border-collapse:collapse;font-size:12.5px;}
.pmportal .pp-set-tbl th{text-align:left;font-weight:700;color:var(--bl-sub);font-size:11.5px;
  padding:6px 8px;border-bottom:1px solid var(--bl-line);white-space:nowrap;}
.pmportal .pp-set-tbl td{padding:7px 8px;border-bottom:1px solid var(--bl-line);vertical-align:middle;}
.pmportal .pp-set-tbl tr:last-child td{border-bottom:0;}
.pmportal .pp-set-tbl .sub{color:var(--bl-faint);font-size:11.5px;}
.pmportal .pp-set-role{display:inline-block;border-radius:11px;padding:1px 9px;font-size:11px;font-weight:700;
  background:var(--bl-chip);color:var(--bl-sub);}
.pmportal .pp-set-role.r-manager{background:var(--bl-accent);color:#fff;}
.pmportal .pp-set-role.r-member{background:var(--bl-chip);color:var(--bl-ink);}
.pmportal .pp-set-bar{display:flex;flex-wrap:wrap;align-items:center;gap:8px;margin-bottom:10px;}
.pmportal .pp-set-bar .pp-set-in{width:auto;flex:1 1 200px;min-width:150px;}
.pmportal .pp-set-bar select.pp-set-in{flex:0 0 auto;width:auto;}
.pmportal .pp-set-pg{display:flex;align-items:center;gap:10px;margin-top:10px;font-size:12px;color:var(--bl-sub);}
.pmportal .pp-set-pg .sp{flex:1 1 auto;}
.pmportal .pp-set-empty{color:var(--bl-faint);font-size:12px;padding:10px 2px;}

.pmportal .pp-set-row{display:flex;align-items:center;gap:8px;padding:7px 6px;border-bottom:1px solid var(--bl-line);}
.pmportal .pp-set-row:last-of-type{border-bottom:0;}
.pmportal .pp-set-row.drag{opacity:.45;}
.pmportal .pp-set-row.over{box-shadow:inset 0 2px 0 var(--bl-accent);}
.pmportal .pp-set-grip{cursor:grab;color:var(--bl-faint);flex:none;width:16px;text-align:center;}
.pmportal .pp-set-row.nodrag .pp-set-grip{cursor:default;opacity:.35;}
.pmportal .pp-set-row .nm{flex:1 1 auto;min-width:0;}
.pmportal .pp-set-row .pp-set-in{padding:5px 8px;font-size:12.5px;}
.pmportal .pp-set-row input[type=color]{flex:none;width:30px;height:28px;padding:1px;border:1px solid var(--bl-line);
  border-radius:4px;background:var(--bl-box);cursor:pointer;}
/* ppDateField turns these into type=text, so match its marker as well. */
.pmportal .pp-set-row input[type=date],
.pmportal .pp-set-row input[data-pp-date]{flex:0 0 140px;width:140px;}
.pmportal .pp-set-row .pp-set-done{display:flex;align-items:center;gap:5px;flex:none;margin:0;font-weight:400;
  font-size:12px;color:var(--bl-sub);cursor:pointer;white-space:nowrap;}
.pmportal .pp-set-row .pp-set-done input{margin:0;width:14px;height:14px;}
.pmportal .pp-set-row .pp-set-done.on{color:var(--bl-accent-d);font-weight:700;}
.pmportal .pp-set-donenote{margin:8px 0 2px;padding:6px 10px;border-radius:4px;background:var(--bl-box,#f7f8f8);color:var(--bl-sub,#666);font-size:12px;}
.pmportal .pp-set-donenote.warn{background:#fff4e5;color:#8a4b00;}
.pmportal .pp-set-tpl{border:1px solid var(--bl-line);background:var(--bl-box);color:var(--bl-sub);border-radius:4px;
  font-size:11.5px;padding:4px 9px;cursor:pointer;white-space:nowrap;flex:none;}
.pmportal .pp-set-tpl:hover{background:var(--bl-hover);color:var(--bl-ink);}
.pmportal .pp-set-tpl.has{color:var(--bl-accent-d);border-color:var(--bl-accent);font-weight:700;}
.pmportal .pp-set-row.tpl-open{flex-wrap:wrap;}
.pmportal .pp-set-tplbox{flex:0 0 calc(100% - 24px);margin:4px 0 4px 24px;padding:12px 14px;background:var(--bl-box2);
  border:1px solid var(--bl-line);border-radius:4px;}
.pmportal .pp-set-tplbox .pp-set-f{max-width:none;margin-bottom:10px;}
.pmportal .pp-set-tplbox textarea.pp-set-in{font-family:inherit;font-size:12.5px;min-height:110px;resize:vertical;}
.pmportal .pp-set-tplbox .pp-set-note{margin:0;}
.pmportal .pp-set-del{border:0;background:none;color:var(--bl-faint);cursor:pointer;font-size:13px;padding:2px 6px;flex:none;}
.pmportal .pp-set-del:hover{color:var(--bl-red);}
.pmportal .pp-set-addrow{display:flex;align-items:center;gap:8px;margin-top:12px;}
.pmportal .pp-set-addrow .pp-set-in{flex:1 1 auto;}
</style>

<section class="bl-screen" id="pp-settings">
    <div class="pp-set">
        <nav class="pp-set-nav">
            @foreach($setSections as $section)
            <a data-sec="{{ $section[0] }}" class="{{ $loop->first ? 'on' : '' }} {{ $section[0] === 'danger' ? 'dg' : '' }}">
                <i class="fa {{ $section[2] }}"></i>{{ $section[1] }}
            </a>
            @endforeach
            {{-- ISS00432: a page of its own --}}
            @if(!empty($schedule_url))
            <a href="{{ $schedule_url }}"><i class="fa fa-repeat"></i>{{ PortalDefinition::trans('sched.page_title') }}</a>
            @endif
            @if(!empty($reminder_url))
            <a href="{{ $reminder_url }}"><i class="fa fa-bell-o"></i>{{ PortalDefinition::trans('remind.page_title') }}</a>
            @endif
        </nav>

        <div class="pp-set-pane">
            <div class="pp-set-head">
                <i class="fa fa-cog"></i><span class="pp-set-title">{{ $setSections[0][1] }}</span>
                <span style="flex:1 1 auto;"></span>
            </div>
            <div class="pp-set-spin"><i class="fa fa-spinner fa-spin"></i> {{ PortalDefinition::trans('m_loading') }}</div>
            <div class="pp-set-body" hidden></div>
            <div class="pp-set-foot" hidden>
                <span class="pp-set-msg"></span>
                <span class="sp"></span>
                <button type="button" class="pp-set-btn main pp-set-save">{{ $st('save') }}</button>
            </div>
        </div>
    </div>
</section>

<script>
(function () {
    'use strict';

    // Runs after the portal script has parsed the page, so window.openNedit
    // is already published by the time the escape hatch can be clicked.
    function boot() {
        var host = document.getElementById('pp-settings');
        if (!host || host.dataset.ppBound) { return; }
        host.dataset.ppBound = '1';

        var S = @json($setData);
        var LANG = @json($setLang);
        var L = LANG.set;
        var G = LANG.git;
        var A = LANG.ai || {};
        var MAP = S.map || {};
        var TOKEN = document.querySelector('meta[name="csrf-token"]');
        TOKEN = TOKEN ? TOKEN.getAttribute('content') : '';

        var nav = host.querySelector('.pp-set-nav');
        var title = host.querySelector('.pp-set-title');
        var spin = host.querySelector('.pp-set-spin');
        var body = host.querySelector('.pp-set-body');
        var foot = host.querySelector('.pp-set-foot');
        var msg = host.querySelector('.pp-set-msg');
        var saveBtn = host.querySelector('.pp-set-save');

        var COLUMNS = null;   // name -> column meta from the edit endpoint
        var VALUES = {};      // name -> value as the server holds it
        var ROW_AT = null;    // updated_at of the row VALUES was read from
        var FIELDS = {};      // name -> the input element rendered for it
        var GIT = S.git || {};
        var current = 'basic';
        var loading = false;

        // The portal script owns the request queue, and on a pjax swap this
        // pane boots before it. Looked up per call, therefore, and the
        // request simply runs unqueued when there is no queue to join.
        function ppq(task, low) {
            return window.ppQueue ? window.ppQueue(task, low) : task();
        }
        // The edit payload is shared with the portal's own record editor, so
        // it must be read and never written to.
        function copyPlain(o) {
            var out = {};
            Object.keys(o || {}).forEach(function (k) { out[k] = o[k]; });
            return out;
        }

        // ---- small DOM helpers -----------------------------------------
        function el(tag, cls, text) {
            var e = document.createElement(tag);
            if (cls) { e.className = cls; }
            if (text !== undefined && text !== null) { e.textContent = text; }
            return e;
        }
        function fmt(text, args) {
            return String(text === undefined || text === null ? '' : text)
                .replace(/:([a-z_]+)/g, function (m, k) {
                    return Object.prototype.hasOwnProperty.call(args || {}, k) ? String(args[k]) : m;
                });
        }
        function say(text, kind) {
            msg.textContent = text || '';
            msg.className = 'pp-set-msg' + (kind ? ' ' + kind : '');
        }

        // ---- failures ----------------------------------------------------
        // What a person reads when a call fails, and only that: the sentence
        // the portal wrote for it (`message` on an ok:false answer), else one
        // chosen by the HTTP status (0 = no answer at all), else the call
        // site's own. Never `error` or `detail` - a code and data - and never
        // a browser's or a server's own English.
        var HTTP = LANG.http || {};
        function errText(status, body, fallback) {
            if (body && body.ok === false && typeof body.message === 'string' && body.message !== '') {
                return body.message;
            }
            var messages = HTTP.messages || {};
            var key = (HTTP.status || {})[status];
            if (!key && status >= 500) { key = 'server'; }
            if (key && messages[key]) { return messages[key]; }
            return fallback || messages.other || '';
        }
        // A response as { status, body }. A body that is not JSON - a proxy's
        // error page, an expired session's login page - is null rather than
        // a rejected promise, so it is worded by its status, not as a lost
        // connection.
        function readJson(r) {
            var parsed = typeof r.text === 'function'
                ? r.text().then(function (text) { return text ? JSON.parse(text) : null; })
                : r.json();
            return parsed.then(function (body) {
                return { status: r.status, body: body };
            }, function () {
                return { status: r.status, body: null };
            });
        }

        // ---- fields -----------------------------------------------------
        // Same widget vocabulary and the same wire format as the portal's
        // record editor, so a value saved here and a value saved there are
        // indistinguishable to the API.
        function colOf(name) {
            return name && COLUMNS ? COLUMNS[name] : null;
        }
        function preselected(value) {
            if (Array.isArray(value)) { return value.map(String); }
            if (typeof value === 'string' && value.indexOf(',') >= 0) {
                return value.split(',').map(function (v) { return v.trim(); });
            }
            return (value === null || value === undefined || value === '') ? [] : [String(value)];
        }
        function buildInput(col, value, labels, readonly) {
            var input;
            if (col.type === 'select') {
                input = el('select', 'pp-set-in');
                input.appendChild(new Option(LANG.none, ''));
                (col.options || []).forEach(function (o) {
                    input.appendChild(new Option(o.label, o.key));
                });
                input.value = value === null || value === undefined ? '' : String(value);
            } else if ((col.type === 'relation' || col.type === 'user' || col.type === 'organization') && col.lookup_url) {
                // Searchable chips, not a select filled with the first N
                // rows: on a user table of any size the person you want is
                // usually not in the first N, and the old widget had no way
                // to reach them at all. Shared with the record editor.
                var pre = preselected(value);
                var preLabels = labels && labels[col.name] ? String(labels[col.name]).split(/、/) : [];
                var picker = window.ppMakePicker({
                    url: col.lookup_url,
                    multiple: !!col.multiple,
                    readonly: !!readonly,
                    valueClass: 'pp-set-in',
                    values: pre.map(function (v, i) { return { value: v, label: preLabels[i] || v }; }),
                    texts: { search: LANG.lkp_search, empty: LANG.lkp_empty }
                });
                input = picker.select;
                input.setAttribute('data-col', col.name);
                // The caller appends what it gets back, so hand it the
                // picker; FIELDS still points at the <select> underneath.
                picker.root.__ppInput = input;
                return picker.root;
            } else if (col.type === 'date') {
                input = el('input', 'pp-set-in');
                input.value = String(value === null || value === undefined ? '' : value).slice(0, 10);
                if (window.ppDateField) { window.ppDateField(input, 'date'); }
            } else if (col.raw_type === 'textarea') {
                input = el('textarea', 'pp-set-in');
                input.rows = 3;
                input.value = value === null || value === undefined ? '' : String(value);
            } else {
                input = el('input', 'pp-set-in');
                input.type = 'text';
                input.value = value === null || value === undefined ? '' : String(value);
            }
            input.setAttribute('data-col', col.name);
            if (readonly) { input.disabled = true; }
            return input;
        }
        function field(col, extraHelp, readonly) {
            var wrap = el('div', 'pp-set-f');
            var lab = el('label', 'pp-set-lab', col.label);
            if (col.required) { lab.appendChild(el('span', 'req', ' *')); }
            wrap.appendChild(lab);

            var built = buildInput(col, VALUES[col.name], VALUES._labels, readonly);
            wrap.appendChild(built);
            // A chip picker hands back its wrapper; the value lives in the
            // hidden <select> it carries, and that is what collect() reads.
            FIELDS[col.name] = built.__ppInput || built;

            var help = extraHelp || col.help;
            if (help) { wrap.appendChild(el('div', 'help', help)); }
            wrap.appendChild(el('div', 'err'));
            wrap.querySelector('.err').hidden = true;

            return wrap;
        }
        function switchRow(col, helpText, nameOverride) {
            var row = el('div', 'pp-set-sw');
            var input = el('input');
            input.type = 'checkbox';
            input.id = 'pp-set-sw-' + col.name;
            // A project never saved with this column holds nothing, and the
            // code reading it falls back to the column's default - so the box
            // shows that default rather than "off".
            var value = VALUES[col.name];
            if (value === null || value === undefined || value === '') { value = col.default; }
            input.defaultChecked = String(value) === '1';
            input.checked = input.defaultChecked;
            input.setAttribute('data-col', col.name);
            FIELDS[col.name] = input;

            var tx = el('div', 'tx');
            var nm = el('label', 'nm', nameOverride || col.label);
            nm.setAttribute('for', input.id);
            tx.appendChild(nm);
            if (helpText) { tx.appendChild(el('div', 'hp', helpText)); }

            row.appendChild(input);
            row.appendChild(tx);
            return row;
        }
        // The value to send for one rendered field, in the API's own shape.
        function collect(name) {
            var input = FIELDS[name];
            if (!input) { return undefined; }
            if (input.type === 'checkbox') { return input.checked ? 1 : 0; }
            if (input.tagName === 'SELECT' && input.multiple) {
                var out = [];
                Array.prototype.forEach.call(input.options, function (o) {
                    if (o.selected && o.value) { out.push(o.value); }
                });
                return out;
            }
            if (input.tagName === 'SELECT') { return input.value || null; }
            return input.value;
        }
        // Only what actually changed goes on the wire - the API takes partial
        // updates, so a co-worker's edit to a field nobody touched survives.
        function diff() {
            var out = {};
            Object.keys(FIELDS).forEach(function (name) {
                // A switch is compared with what it first showed: an empty
                // value shows its default, and sending that back untouched
                // would still overwrite a co-worker's change.
                if (FIELDS[name].type === 'checkbox') {
                    if (FIELDS[name].checked !== FIELDS[name].defaultChecked) { out[name] = collect(name); }
                    return;
                }
                var now = collect(name);
                var was = VALUES[name];
                var same;
                if (Array.isArray(now)) {
                    var wasArr = preselected(was);
                    same = now.length === wasArr.length && now.every(function (v, i) { return String(v) === String(wasArr[i]); });
                } else if ((now === null || now === '') && (was === null || was === undefined || was === '')) {
                    same = true;
                } else {
                    same = String(now === null || now === undefined ? '' : now) === String(was === null || was === undefined ? '' : was);
                }
                if (!same) { out[name] = now; }
            });
            return out;
        }
        function clearErrors() {
            body.querySelectorAll('.pp-set-f .err').forEach(function (e) {
                e.textContent = '';
                e.hidden = true;
            });
        }
        function putError(name, messages) {
            var input = FIELDS[name];
            // closest(), not parentNode: a chip picker puts two elements
            // between its value carrier and the .pp-set-f holding the slot.
            var box = input ? input.closest('.pp-set-f') : null;
            var slot = box ? box.querySelector('.err') : null;
            var text = Array.isArray(messages) ? messages.join('\n') : String(messages);
            if (!slot) { say(text, 'err'); return; }
            slot.textContent = text;
            slot.hidden = false;
        }

        // ---- panes -------------------------------------------------------
        function paneBasic() {
            var frag = document.createDocumentFragment();
            ['name', 'key', 'note', 'status', 'start', 'end', 'parent'].forEach(function (role) {
                var col = colOf(MAP[role]);
                if (!col) { return; }
                frag.appendChild(field(col, role === 'key' ? L.key_warn : null));
            });
            return frag;
        }
        function panePeople() {
            var frag = document.createDocumentFragment();
            frag.appendChild(el('div', 'pp-set-note', L.people_help));
            // The server refuses the change anyway; shown read-only so that
            // nobody fills the pickers in only to be told so on save.
            var locked = !S.can_people;
            if (locked) { frag.appendChild(el('div', 'pp-set-note', L.people_locked)); }
            ['manager', 'members', 'viewers', 'member_orgs', 'viewer_orgs'].forEach(function (role) {
                var col = colOf(MAP[role]);
                if (col) { frag.appendChild(field(col, null, locked)); }
            });

            // Read-only roster under the pickers: who is actually on this
            // project right now, and which teams they bring with them. The
            // pickers above are how it is changed; this is how it is read,
            // which on a project with dozens of members is a different job.
            var host = el('div');
            host.id = 'pp-set-roster';
            host.appendChild(el('div', 'pp-set-spin', LANG.m_loading || ''));
            frag.appendChild(host);
            loadRoster(host);
            return frag;
        }

        // ---- member roster ----------------------------------------------
        // The API's role words are singular; the labels the user reads are
        // the plural ones already on the picker above.
        var ROLE_LABEL = { manager: 'manager', member: 'members', viewer: 'viewers' };
        function roleLabel(role) {
            return L[ROLE_LABEL[role] || role] || role;
        }
        var ROSTER = null;
        function loadRoster(host) {
            var paint = function () { renderRoster(host); };
            if (ROSTER) { paint(); return; }
            ppq(function () {
                return fetch(S.members_url, {
                    credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' }
                }).then(function (r) {
                    if (!r.ok) { throw new Error(String(r.status)); }
                    return r.json();
                });
            }).then(function (j) {
                ROSTER = { teams: j.teams || [], users: j.users || [] };
                paint();
            }).catch(function () {
                host.textContent = '';
                host.appendChild(el('div', 'pp-set-warn', L.mem_load_fail));
            });
        }
        var rosterQ = '', rosterRole = '', rosterPage = 1;
        var ROSTER_PER = 20;
        function renderRoster(host) {
            if (!ROSTER) { return; }
            host.textContent = '';

            // -- teams --
            host.appendChild(el('div', 'pp-set-sub', fmt(L.mem_teams, { count: ROSTER.teams.length })));
            if (!ROSTER.teams.length) {
                host.appendChild(el('div', 'pp-set-empty', L.mem_teams_empty));
            } else {
                var tt = el('table', 'pp-set-tbl');
                var thead = el('tr');
                [L.mem_col_team, L.mem_col_role, L.mem_col_since].forEach(function (h) {
                    thead.appendChild(el('th', null, h));
                });
                tt.appendChild(thead);
                ROSTER.teams.forEach(function (t) {
                    var tr = el('tr');
                    tr.appendChild(el('td', null, t.name));
                    var rd = el('td');
                    rd.appendChild(el('span', 'pp-set-role r-' + t.role, roleLabel(t.role)));
                    tr.appendChild(rd);
                    tr.appendChild(el('td', 'sub', t.since || ''));
                    tt.appendChild(tr);
                });
                host.appendChild(tt);
            }

            // -- users --
            var filtered = ROSTER.users.filter(function (u) {
                if (rosterRole && u.role !== rosterRole) { return false; }
                if (!rosterQ) { return true; }
                var needle = rosterQ.toLowerCase();
                return String(u.name).toLowerCase().indexOf(needle) >= 0
                    || String(u.code).toLowerCase().indexOf(needle) >= 0;
            });
            var pages = Math.max(1, Math.ceil(filtered.length / ROSTER_PER));
            if (rosterPage > pages) { rosterPage = pages; }
            var from = (rosterPage - 1) * ROSTER_PER;
            var slice = filtered.slice(from, from + ROSTER_PER);

            host.appendChild(el('div', 'pp-set-sub', fmt(L.mem_users, { count: ROSTER.users.length })));

            var bar = el('div', 'pp-set-bar');
            var q = el('input', 'pp-set-in');
            q.type = 'search';
            q.placeholder = L.mem_filter_ph;
            q.setAttribute('aria-label', L.mem_filter_label);
            q.value = rosterQ;
            // The roster is already in memory, so filtering is local: no
            // round trip, and no debounce needed for a list this size.
            q.addEventListener('input', function () {
                rosterQ = q.value.trim(); rosterPage = 1; renderRoster(host);
                var again = host.querySelector('.pp-set-bar .pp-set-in');
                if (again) { again.focus(); }
            });
            bar.appendChild(q);

            var roleSel = el('select', 'pp-set-in');
            [['', L.mem_role_all], ['manager', L.manager], ['member', L.members], ['viewer', L.viewers]]
                .forEach(function (o) { roleSel.appendChild(new Option(o[1], o[0])); });
            roleSel.value = rosterRole;
            roleSel.addEventListener('change', function () {
                rosterRole = roleSel.value; rosterPage = 1; renderRoster(host);
            });
            bar.appendChild(roleSel);
            host.appendChild(bar);

            if (!slice.length) {
                host.appendChild(el('div', 'pp-set-empty', L.mem_users_empty));
            } else {
                var ut = el('table', 'pp-set-tbl');
                var uhead = el('tr');
                [L.mem_col_user, L.mem_col_id, L.mem_col_role, L.mem_col_orgs].forEach(function (h) {
                    uhead.appendChild(el('th', null, h));
                });
                ut.appendChild(uhead);
                slice.forEach(function (u) {
                    var tr = el('tr');
                    tr.appendChild(el('td', null, u.name));
                    tr.appendChild(el('td', 'sub', u.code || ''));
                    var rd = el('td');
                    rd.appendChild(el('span', 'pp-set-role r-' + u.role, roleLabel(u.role)));
                    tr.appendChild(rd);
                    tr.appendChild(el('td', 'sub', (u.orgs || []).join('、')));
                    ut.appendChild(tr);
                });
                host.appendChild(ut);
            }

            var pg = el('div', 'pp-set-pg');
            pg.appendChild(el('span', null, fmt(L.mem_page, {
                total: filtered.length,
                from: filtered.length ? from + 1 : 0,
                to: from + slice.length
            })));
            pg.appendChild(el('span', 'sp'));
            var prev = el('button', 'pp-set-btn', L.mem_prev);
            prev.type = 'button';
            prev.disabled = rosterPage <= 1;
            prev.addEventListener('click', function () { rosterPage--; renderRoster(host); });
            var next = el('button', 'pp-set-btn', L.mem_next);
            next.type = 'button';
            next.disabled = rosterPage >= pages;
            next.addEventListener('click', function () { rosterPage++; renderRoster(host); });
            pg.appendChild(prev);
            pg.appendChild(next);
            host.appendChild(pg);

            host.appendChild(el('div', 'pp-set-note', L.mem_manage_hint));
        }
        function paneFeatures() {
            var frag = document.createDocumentFragment();
            frag.appendChild(el('div', 'pp-set-note', L.features_help));
            (MAP.features || []).forEach(function (name) {
                var col = colOf(name);
                if (col) { frag.appendChild(switchRow(col, L['feat_' + name])); }
            });
            var rule = colOf(MAP.formatting_rule);
            if (rule) {
                var wrap = el('div');
                wrap.style.marginTop = '16px';
                wrap.appendChild(field(rule));
                frag.appendChild(wrap);
            }
            var arch = colOf(MAP.archived);
            if (arch) {
                var box = el('div');
                box.style.marginTop = '10px';
                box.style.borderTop = '1px solid var(--bl-line)';
                box.style.paddingTop = '6px';
                box.appendChild(switchRow(arch, L.archive_help, L.archive_label));
                frag.appendChild(box);
            }
            return frag;
        }
        // Whatever the panes above do not model. Standard installs have
        // nothing here and the tab is not rendered at all; where a column has
        // been added to the project table, this is where it is edited -
        // rather than in a dialog repeating every field the tab already has.
        function paneOther() {
            var frag = document.createDocumentFragment();
            frag.appendChild(el('div', 'pp-set-note', L.other_help));

            var beyond = [];
            (S.other || []).forEach(function (name) {
                var col = colOf(name);
                if (!col) { return; }
                // A file or a rich text column needs the uploader and the
                // editor the record form carries; the rest are the same
                // widgets every other pane here is built from.
                if (col.type === 'file' || col.type === 'image' || col.raw_type === 'editor') {
                    beyond.push(col.label || name);
                    return;
                }
                frag.appendChild(col.type === 'boolean' ? switchRow(col) : field(col));
            });

            if (beyond.length) {
                var box = el('div');
                box.style.marginTop = '14px';
                box.appendChild(el('div', 'pp-set-note', fmt(L.other_full_note, { fields: beyond.join('、') })));
                var btn = el('button', 'pp-set-btn', L.full_form);
                btn.type = 'button';
                btn.addEventListener('click', function () {
                    if (typeof window.openNedit === 'function') { window.openNedit(S.table, S.id); }
                });
                box.appendChild(btn);
                frag.appendChild(box);
            }
            return frag;
        }
        // A labelled URL box. Linked when it is somewhere to go rather than
        // something to copy, and opened in a new tab because this screen is
        // a single page that would lose its state on navigation.
        function urlField(label, url, linked) {
            var wrap = el('div', 'pp-set-f');
            wrap.appendChild(el('label', 'pp-set-lab', label));
            if (linked) {
                var a = el('a', 'pp-set-url', url);
                a.href = url;
                a.target = '_blank';
                a.rel = 'noopener';
                wrap.appendChild(a);
            } else {
                wrap.appendChild(el('div', 'pp-set-url', url));
            }
            return wrap;
        }

        function paneGit() {
            var frag = document.createDocumentFragment();
            var status = GIT.status || 'none';

            var head = el('div', 'pp-set-f');
            var chip = el('span', 'pp-set-chip s-' + status, G['status_' + status] || status);
            head.appendChild(chip);
            if (GIT.requested_at && status !== 'none') {
                var at = el('span', null, ' ' + fmt(G.requested_at, { at: GIT.requested_at }));
                at.style.color = 'var(--bl-sub)';
                at.style.fontSize = '12px';
                head.appendChild(at);
            }
            frag.appendChild(head);

            if (!S.git_on) {
                frag.appendChild(el('div', 'pp-set-note', G.off_note));
                var onErr = el('div', 'err');
                onErr.hidden = true;
                var onBtn = el('button', 'pp-set-btn main', G.enable);
                onBtn.type = 'button';
                onBtn.addEventListener('click', function () { enableGit(onBtn, onErr); });
                frag.appendChild(onBtn);
                frag.appendChild(onErr);
                return frag;
            }
            // Only the unconfigured case is worth saying out loud: that
            // one tells the reader to go and ask an administrator. Which
            // brand of Git server is connected is not something anybody on
            // this tab can act on.
            if (!GIT.configured) {
                frag.appendChild(el('div', 'pp-set-warn', G.not_configured));
            }
            if (GIT.error) {
                var err = el('div', 'pp-set-warn', GIT.error);
                err.style.borderLeftColor = 'var(--bl-red)';
                frag.appendChild(err);
            }
            // HTTP, and only HTTP: the port the site is already on, which
            // for someone behind a firewall is often the only port open. The
            // SSH route is gone on purpose - the Git server has no SSH port
            // open at all now, so offering one would send people down a
            // route that cannot work.
            if (GIT.http_url) {
                frag.appendChild(urlField(G.clone_hint, GIT.http_url, false));
            }
            // Looking at the code is a different URL from cloning it: this
            // one carries the owner segment the clone URL hides.
            if (GIT.browse_url) {
                frag.appendChild(urlField(G.browse_hint, GIT.browse_url, true));
            }

            var nameWrap = el('div', 'pp-set-f');
            nameWrap.appendChild(el('label', 'pp-set-lab', G.name_label));
            var input = el('input', 'pp-set-in');
            input.type = 'text';
            input.value = GIT.repo_name || '';
            input.id = 'pp-set-repo';
            if (status === 'ready') { input.readOnly = true; }
            nameWrap.appendChild(input);
            nameWrap.appendChild(el('div', 'help', G.name_help));
            var gerr = el('div', 'err');
            gerr.hidden = true;
            nameWrap.appendChild(gerr);
            frag.appendChild(nameWrap);

            if (status !== 'ready') {
                var btn = el('button', 'pp-set-btn main', G.request);
                btn.type = 'button';
                btn.addEventListener('click', function () { requestRepo(input, gerr, btn); });
                frag.appendChild(btn);
            } else {
                frag.appendChild(gitKeysBox());
            }
            return frag;
        }

        // ---- credentials for this repository --------------------------
        // Each one is scoped to this repository and to nothing else, which is
        // why they belong to the project tab rather than to a personal screen
        // the portal does not have. The list still shows a public key if one
        // is registered on the Git server - from before this route was
        // dropped, or put there by hand - so it can be revoked from here.
        function gitKeysBox() {
            var box = el('div', 'pp-set-f');
            box.appendChild(el('label', 'pp-set-lab', G.keys_label));
            box.appendChild(el('div', 'help', G.keys_help));

            var list = el('div', 'pp-set-keys');
            list.appendChild(el('div', 'none', G.keys_loading));

            box.appendChild(gitTokenBox(list));
            box.appendChild(list);

            fetchGitKeys(list, null);
            return box;
        }

        // The HTTP route, and the only one: there is nothing to prepare on
        // the machine doing the cloning - no key, no port, no client config.
        function gitTokenBox(list) {
            var wrap = el('div', 'pp-set-tok');
            wrap.appendChild(el('div', 'st', G.token_help_sep));

            // Read-only unless this is ticked. A credential that can rewrite
            // a branch is worth one deliberate click, and a clone to read
            // the code - the common case - never needs it.
            var pushLine = el('div', 'st');
            var pushLab = el('label');
            var push = el('input');
            push.type = 'checkbox';
            push.style.marginRight = '.4em';
            pushLab.appendChild(push);
            pushLab.appendChild(document.createTextNode(G.token_push));
            pushLine.appendChild(pushLab);
            wrap.appendChild(pushLine);

            var out = el('div', 'out');
            out.hidden = true;
            var terr = el('div', 'err');
            terr.hidden = true;

            var btn = el('button', 'pp-set-btn main', G.token_add);
            btn.type = 'button';
            btn.addEventListener('click', function () {
                issueGitToken(btn, out, terr, list, push.checked);
            });

            wrap.appendChild(btn);
            wrap.appendChild(terr);
            wrap.appendChild(out);
            return wrap;
        }

        // The token is readable once. The Git server keeps a hash of it, so
        // there is no second chance to show it and the warning is not
        // decoration.
        //
        // Shown beside the clone command, never inside its URL: a URL with
        // the password in it is saved as it is in .git/config, in shell
        // history and in build logs. git asks for the password instead.
        function issueGitToken(btn, out, errSlot, list, allowPush) {
            if (btn.disabled) { return; }
            errSlot.hidden = true;
            btn.disabled = true;
            btn.textContent = L.saving;
            fetch(S.git_token_url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': TOKEN,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ allow_push: !!allowPush })
            }).then(readJson).then(function (res) {
                var j = res.body || {};
                btn.disabled = false;
                btn.textContent = G.token_add;
                if (res.status === 200 && j.ok && j.token) {
                    out.textContent = '';
                    out.appendChild(el('div', 'nt', G.token_once_sep));
                    if (j.clone_url) {
                        out.appendChild(tokenLine(G.token_clone_label, 'git clone ' + j.clone_url));
                    }
                    out.appendChild(tokenLine(G.token_user_label, G.token_user_value, true));
                    out.appendChild(tokenLine(G.token_pass_label, j.token));
                    out.hidden = false;
                    drawGitKeys(list, j.keys);
                    return;
                }
                errSlot.textContent = errText(res.status, res.body, L.save_fail);
                errSlot.hidden = false;
            }).catch(function () {
                btn.disabled = false;
                btn.textContent = G.token_add;
                errSlot.textContent = errText(0, null, L.save_fail);
                errSlot.hidden = false;
            });
        }
        // One labelled value of a freshly issued credential. Something to
        // copy goes in a selectable code box; a plain note stays plain.
        function tokenLine(label, value, plain) {
            var line = el('div', 'st');
            line.appendChild(el('div', null, label));
            line.appendChild(el(plain ? 'div' : 'code', null, value));
            return line;
        }

        // One place draws the list, whether it came from the first load, an
        // add or a remove - all three answer with the whole list.
        function drawGitKeys(list, keys) {
            list.textContent = '';
            if (!keys || !keys.length) {
                list.appendChild(el('div', 'none', G.keys_empty));
                return;
            }
            keys.forEach(function (k) {
                var row = el('div', 'row');
                // The person's own label; the part of the title the portal
                // generates to keep titles unique is not for reading.
                row.appendChild(el('span', 'ttl', (k.label !== undefined ? k.label : k.title) || ''));
                row.appendChild(el('span', 'ty',
                    k.key_type === 'token' ? G.keys_type_token : G.keys_type_ssh));
                // What it is allowed to do, which is now a choice made when
                // it was issued rather than always "everything".
                row.appendChild(el('span', 'ty',
                    k.read_only === false ? G.keys_push : G.keys_readonly));
                // Whose it is, from the portal's own record - not from the
                // title, which is a label whoever issued it chose.
                var who = k.mine ? G.keys_owner_me
                    : (k.owner_name || (k.owner_id ? '' : G.keys_owner_unknown));
                row.appendChild(el('span', k.mine ? 'who me' : 'who', who));
                row.appendChild(el('span', 'fp', k.fingerprint || ''));
                if (k.created_at) { row.appendChild(el('span', 'at', k.created_at)); }

                // Somebody else's, and you do not administer projects: the
                // endpoint would refuse, so the button is not offered.
                if (k.can_remove === false) {
                    list.appendChild(row);
                    return;
                }

                var del = el('button', 'del', G.keys_remove);
                del.type = 'button';
                // Two clicks instead of a confirm() dialog, the same as the
                // master lists below: a browser modal inside this page
                // freezes every later interaction.
                var armed = false, armTimer = null;
                del.addEventListener('click', function () {
                    if (!armed) {
                        armed = true;
                        del.textContent = G.keys_remove_sure;
                        armTimer = setTimeout(function () {
                            armed = false;
                            del.textContent = G.keys_remove;
                        }, 4000);
                        return;
                    }
                    if (armTimer) { clearTimeout(armTimer); }
                    del.disabled = true;
                    fetchGitKeys(list, { key_id: k.id });
                });
                row.appendChild(del);
                list.appendChild(row);
            });
        }

        // No body loads the list; a body removes one key from it.
        function fetchGitKeys(list, remove) {
            var opts = remove ? {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': TOKEN,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(remove)
            } : {
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            };
            fetch(remove ? S.git_key_remove_url : S.git_keys_url, opts)
                .then(readJson)
                .then(function (res) {
                    var j = res.body;
                    if (res.status === 200 && j && j.ok) {
                        drawGitKeys(list, j.keys);
                        return;
                    }
                    list.textContent = '';
                    list.appendChild(el('div', 'none', errText(res.status, j, L.load_fail)));
                })
                .catch(function () {
                    list.textContent = '';
                    list.appendChild(el('div', 'none', errText(0, null, L.load_fail)));
                });
        }

        function paneAi() {
            var frag = document.createDocumentFragment();
            var AI = S.ai || {};

            var head = el('div', 'pp-set-f');
            head.appendChild(el('span', 'pp-set-chip ' + (AI.configured ? 's-ready' : 's-failed'),
                AI.configured ? A.state_connected : A.state_unconfigured));
            frag.appendChild(head);
            if (!AI.configured) {
                frag.appendChild(el('div', 'pp-set-warn', A.not_configured));
            }
            frag.appendChild(el('div', 'pp-set-note', A.settings_help));

            // One switch for the AI, and under it one for the assignee.
            (MAP.ai || []).forEach(function (name) {
                var col = colOf(name);
                if (!col) { return; }
                var row = switchRow(col, A['help_' + name] || '');
                if (name === 'ai_assign') { row.classList.add('pp-set-sub'); }
                frag.appendChild(row);
            });
            // The assignee switch means nothing while the AI is off.
            var aiOn = FIELDS.ai_enabled;
            var aiAssign = FIELDS.ai_assign;
            if (aiOn && aiAssign) {
                var follow = function () {
                    aiAssign.disabled = !aiOn.checked;
                    aiAssign.closest('.pp-set-sw').classList.toggle('off', !aiOn.checked);
                };
                aiOn.addEventListener('change', follow);
                follow();
            }

            // -- one live call with a sample report --
            var tbox = el('div', 'pp-set-f');
            var tbtn = el('button', 'pp-set-btn', A.test);
            tbtn.type = 'button';
            tbtn.disabled = !AI.configured;
            var terr = el('div', 'err');
            terr.hidden = true;
            var tout = el('div', 'pp-set-keys');
            tout.hidden = true;
            tout.style.marginTop = '8px';
            tbtn.addEventListener('click', function () { runAiTest(tbtn, tout, terr); });
            tbox.appendChild(tbtn);
            tbox.appendChild(terr);
            tbox.appendChild(tout);
            frag.appendChild(tbox);

            // -- the last thirty days --
            var sbox = el('div', 'pp-set-f');
            var slab = el('label', 'pp-set-lab', fmt(A.stats_title, { days: 30 }));
            sbox.appendChild(slab);
            var slist = el('div', 'pp-set-keys');
            slist.appendChild(el('div', 'none', A.stats_loading));
            sbox.appendChild(slist);
            frag.appendChild(sbox);
            loadAiState(slab, slist);
            return frag;
        }
        function aiRow(list, label, value) {
            var row = el('div', 'row');
            row.appendChild(el('span', 'ttl', label));
            row.appendChild(el('span', 'fp', value));
            list.appendChild(row);
        }
        function loadAiState(slab, list) {
            if (!S.ai_state_url) { list.textContent = ''; return; }
            ppq(function () {
                return fetch(S.ai_state_url, {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                }).then(function (r) {
                    if (!r.ok) { throw new Error(String(r.status)); }
                    return r.json();
                });
            }).then(function (j) {
                var ai = (j && j.ai) || {};
                var st = ai.stats || {};
                list.textContent = '';
                slab.textContent = fmt(A.stats_title, { days: st.days || 30 });
                aiRow(list, A.stats_calls, String(st.calls || 0));
                aiRow(list, A.stats_errors, String(st.errors || 0));
                aiRow(list, A.stats_avg_ms, fmt(A.unit_ms, { n: st.avg_ms || 0 }));
                aiRow(list, A.stats_cost, String(st.cost_usd || 0));
                aiRow(list, A.stats_today, String(ai.today || 0) + ' / ' + String(ai.daily_cap || 0));
                var kept = st.kept || {};
                var names = Object.keys(kept);
                if (!names.length) {
                    aiRow(list, A.stats_kept, A.stats_kept_none);
                    return;
                }
                names.forEach(function (name) {
                    var k = kept[name] || {};
                    var rate = k.n ? Math.round(100 * (k.kept || 0) / k.n) : 0;
                    aiRow(list, A.stats_kept + ' - ' + ((ai.labels && ai.labels[name]) || name),
                        rate + '% (' + (k.kept || 0) + '/' + (k.n || 0) + ')');
                });
            }).catch(function () {
                list.textContent = '';
                list.appendChild(el('div', 'none', A.stats_fail));
            });
        }
        function runAiTest(btn, out, errSlot) {
            errSlot.hidden = true;
            out.hidden = true;
            btn.disabled = true;
            btn.textContent = A.testing;
            fetch(S.ai_test_url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': TOKEN,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({})
            }).then(readJson).then(function (res) {
                var j = res.body || {};
                btn.disabled = false;
                btn.textContent = A.test;
                if (res.status !== 200 || !j.ok) {
                    errSlot.textContent = errText(res.status, res.body, A.test_fail);
                    errSlot.hidden = false;
                    return;
                }
                out.textContent = '';
                out.appendChild(el('div', 'none', fmt(A.test_result, { subject: A.test_subject })));
                Object.keys(j.fields || {}).forEach(function (name) {
                    var f = j.fields[name] || {};
                    var text = f.ok
                        ? (f.label || '-') + ' (' + Math.round((f.confidence || 0) * 100) + '%)' + (f.reason ? ' - ' + f.reason : '')
                        : (f.reason || '-');
                    aiRow(out, (j.labels && j.labels[name]) || name, text);
                });
                out.appendChild(el('div', 'none', fmt(A.unit_ms, { n: j.latency_ms || 0 })));
                out.hidden = false;
            }).catch(function () {
                btn.disabled = false;
                btn.textContent = A.test;
                errSlot.textContent = errText(0, null, A.test_fail);
                errSlot.hidden = false;
            });
        }

        function paneDanger() {
            var frag = document.createDocumentFragment();
            var box = el('div', 'pp-set-danger');
            box.appendChild(el('div', 'hd', L.tab_danger));
            box.appendChild(el('div', 'pp-set-note', L.danger_help));

            var wrap = el('div', 'pp-set-f');
            wrap.appendChild(el('label', 'pp-set-lab', fmt(L.danger_confirm, { key: S.key })));
            var input = el('input', 'pp-set-in');
            input.type = 'text';
            input.autocomplete = 'off';
            wrap.appendChild(input);
            var derr = el('div', 'err');
            derr.hidden = true;
            wrap.appendChild(derr);
            box.appendChild(wrap);

            var btn = el('button', 'pp-set-btn dg', L.danger_button);
            btn.type = 'button';
            btn.disabled = true;
            // The typed key is the confirmation - no browser dialog, which
            // would also be the one thing that can freeze the whole page.
            input.addEventListener('input', function () {
                btn.disabled = input.value.trim().toUpperCase() !== String(S.key).toUpperCase();
            });
            btn.addEventListener('click', function () { destroy(input, derr, btn); });
            box.appendChild(btn);

            frag.appendChild(box);
            return frag;
        }

        // ---- per project master lists ------------------------------------
        // 状態 / 種別 / カテゴリー / マイルストーン. Each row saves itself through
        // the same lightapi the record editor uses, so nothing here can do
        // what a user could not do on the data screen.
        function paneList(secKey) {
            var spec = (S.lists || {})[secKey];
            var frag = document.createDocumentFragment();
            if (!spec) { return frag; }

            frag.appendChild(el('div', 'pp-set-sub', spec.title));
            if (spec.hint) {
                frag.appendChild(el('div', 'pp-set-note', spec.hint));
            }
            if (spec.sortable) {
                frag.appendChild(el('div', 'pp-set-note', L.list_drag_hint));
            }
            var host = el('div');
            host.id = 'pp-set-list-' + secKey;
            host.appendChild(el('div', 'pp-set-spin', LANG.m_loading || ''));
            frag.appendChild(host);
            loadList(secKey, spec, host);
            return frag;
        }

        function listUrl(spec, extra) {
            // Rows belong to one project: either as Exment children of it or
            // through a project column. Filtering server side matters - the
            // table holds every project's rows.
            var q = spec.link === 'parent'
                ? 'filter[parent_id]=' + encodeURIComponent(S.id)
                : 'filter[project]=' + encodeURIComponent(S.id);
            return S.lightapi + '/data/' + spec.table + '?' + q + '&per_page=200' + (extra || '');
        }
        function loadList(secKey, spec, host) {
            ppq(function () {
                return fetch(listUrl(spec), {
                    credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' }
                }).then(function (r) {
                    if (!r.ok) { throw new Error(String(r.status)); }
                    return r.json();
                });
            }).then(function (j) {
                renderList(secKey, spec, host, j.rows || []);
            }).catch(function () {
                host.textContent = '';
                host.appendChild(el('div', 'pp-set-warn', L.list_load_fail));
            });
        }
        function listSort(rows) {
            return rows.slice().sort(function (a, b) {
                var ao = parseInt(a.display_order, 10);
                var bo = parseInt(b.display_order, 10);
                if (isNaN(ao)) { ao = 1e9; }
                if (isNaN(bo)) { bo = 1e9; }
                if (ao !== bo) { return ao - bo; }
                return (a.id || 0) - (b.id || 0);
            });
        }
        function renderList(secKey, spec, host, rows) {
            if (window.ppDateFieldDestroy) { window.ppDateFieldDestroy(host); }
            host.textContent = '';
            var sorted = listSort(rows);
            if (!sorted.length) {
                host.appendChild(el('div', 'pp-set-empty', L.list_empty));
            }

            var listBox = el('div');
            listBox.setAttribute('data-list', secKey);
            sorted.forEach(function (row) { listBox.appendChild(listRow(spec, row, listBox, host, secKey)); });
            host.appendChild(listBox);
            if (secKey === 'statuses' && spec.done) {
                // ISS00255: 完了以外 hides only the last status, in this
                // order, that counts as done. Name it, so a done status added
                // after 完了 (as an import once did) shows up here at once
                // rather than as a 完了以外 that hides nothing.
                listBox._doneNote = el('div', 'pp-set-donenote');
                host.appendChild(listBox._doneNote);
                doneNote(listBox);
            }

            // -- add --
            var add = el('div', 'pp-set-addrow');
            var name = el('input', 'pp-set-in');
            name.type = 'text';
            name.placeholder = L.list_add_ph;
            var btn = el('button', 'pp-set-btn main', L.list_add);
            btn.type = 'button';
            var err = el('div', 'err');
            err.hidden = true;
            // Enter pressed twice - or Enter and a click - would otherwise
            // post the same row twice before the first answer arrives.
            var busy = false;
            var submit = function () {
                if (busy) { return; }
                var v = name.value.trim();
                if (!v) {
                    err.textContent = L.list_name_required; err.hidden = false;
                    return;
                }
                err.hidden = true;
                busy = true;
                btn.disabled = true;
                var body = { value: { name: v } };
                if (spec.link === 'parent') {
                    body.parent_id = S.id;
                    body.parent_type = S.table;
                } else {
                    body.value.project = S.id;
                }
                if (spec.sortable) {
                    body.value.display_order = String((sorted.length + 1) * 10);
                }
                fetch(S.lightapi + '/data/' + spec.table, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': TOKEN },
                    credentials: 'same-origin',
                    body: JSON.stringify(body)
                }).then(readJson)
                    .then(function (res) {
                        var b = res.body || {};
                        busy = false;
                        btn.disabled = false;
                        if (res.status === 200 && b.ok) {
                            name.value = '';
                            loadList(secKey, spec, host);
                            say(L.list_saved, 'ok');
                            return;
                        }
                        // The per-field sentences are the validator's own,
                        // in Japanese; anything else is worded by errText.
                        var fields = b.fields || {};
                        var first = Object.keys(fields)[0];
                        err.textContent = first
                            ? (Array.isArray(fields[first]) ? fields[first].join(' ') : String(fields[first]))
                            : errText(res.status, res.body, L.save_fail);
                        err.hidden = false;
                    }).catch(function () {
                        busy = false;
                        btn.disabled = false;
                        err.textContent = errText(0, null, L.save_fail); err.hidden = false;
                    });
            };
            btn.addEventListener('click', submit);
            name.addEventListener('keydown', function (ev) {
                if (ev.key === 'Enter') { ev.preventDefault(); submit(); }
            });
            add.appendChild(name);
            add.appendChild(btn);
            host.appendChild(add);
            host.appendChild(err);
        }
        function patchRow(spec, id, patch, onFail) {
            fetch(S.lightapi + '/data/' + spec.table + '/' + id, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': TOKEN },
                credentials: 'same-origin',
                body: JSON.stringify({ value: patch })
            }).then(readJson)
                .then(function (res) {
                    var b = res.body || {};
                    if (res.status === 200 && b.ok) { say(L.list_saved, 'ok'); return; }
                    var fields = b.fields || {};
                    var first = Object.keys(fields)[0];
                    say(first
                        ? (Array.isArray(fields[first]) ? fields[first].join(' ') : String(fields[first]))
                        : errText(res.status, res.body, L.save_fail), 'err');
                    if (onFail) { onFail(); }
                }).catch(function () {
                    say(errText(0, null, L.save_fail), 'err');
                    if (onFail) { onFail(); }
                });
        }
        function listRow(spec, row, listBox, host, secKey) {
            var el_ = el('div', 'pp-set-row' + (spec.sortable ? '' : ' nodrag'));
            el_.setAttribute('data-id', String(row.id));

            var grip = el('span', 'pp-set-grip');
            grip.innerHTML = '<i class="fa fa-bars"></i>';
            el_.appendChild(grip);

            var nameWrap = el('div', 'nm');
            var name = el('input', 'pp-set-in');
            name.type = 'text';
            name.value = row.name == null ? '' : String(row.name);
            var lastName = name.value;
            name.addEventListener('change', function () {
                var v = name.value.trim();
                if (v === lastName) { return; }
                if (!v) { name.value = lastName; say(L.list_name_required, 'err'); return; }
                var prev = lastName;
                lastName = v;
                doneNote(listBox);
                patchRow(spec, row.id, { name: v }, function () { name.value = prev; lastName = prev; doneNote(listBox); });
            });
            nameWrap.appendChild(name);
            el_.appendChild(nameWrap);

            if (spec.color) {
                var color = el('input');
                color.type = 'color';
                color.value = /^#[0-9a-f]{6}$/i.test(String(row.color || '')) ? row.color : '#888888';
                color.title = L.list_color;
                color.addEventListener('change', function () {
                    patchRow(spec, row.id, { color: color.value });
                });
                el_.appendChild(color);
            }
            if (spec.done) {
                // 完了扱い: what the burndown and the overdue count read to
                // tell a finished issue from an open one.
                var doneWrap = el('label', 'pp-set-done');
                var done = el('input');
                done.type = 'checkbox';
                done.checked = isOn(row[spec.done]);
                doneWrap.title = L.list_done_tip;
                doneWrap.classList.toggle('on', done.checked);
                done.addEventListener('change', function () {
                    var patch = {};
                    patch[spec.done] = done.checked ? '1' : '0';
                    doneWrap.classList.toggle('on', done.checked);
                    doneNote(listBox);
                    patchRow(spec, row.id, patch, function () {
                        done.checked = !done.checked;
                        doneWrap.classList.toggle('on', done.checked);
                        doneNote(listBox);
                    });
                });
                doneWrap.appendChild(done);
                doneWrap.appendChild(document.createTextNode(L.list_done));
                el_.appendChild(doneWrap);
            }
            var tplBox = null;
            if (spec.tpl_subject || spec.tpl_body) {
                tplBox = templateBox(spec, row, el_);
            }
            if (spec.dates) {
                [['start_date', L.list_start], ['due_date', L.list_due]].forEach(function (d) {
                    var inp = el('input', 'pp-set-in');
                    inp.title = d[1];
                    inp.value = String(row[d[0]] || '').slice(0, 10);
                    if (window.ppDateField) { window.ppDateField(inp, 'date'); }
                    inp.addEventListener('change', function () {
                        var patch = {};
                        patch[d[0]] = inp.value || null;
                        patchRow(spec, row.id, patch);
                    });
                    el_.appendChild(inp);
                });
            }

            var del = el('button', 'pp-set-del');
            del.type = 'button';
            del.title = L.list_delete;
            del.innerHTML = '<i class="fa fa-trash-o"></i>';
            // Two clicks instead of a confirm() dialog: a browser modal
            // inside this page freezes every later interaction.
            var armed = false, armTimer = null;
            del.addEventListener('click', function () {
                if (!armed) {
                    armed = true;
                    del.textContent = L.list_delete_sure;
                    armTimer = setTimeout(function () {
                        armed = false;
                        del.innerHTML = '<i class="fa fa-trash-o"></i>';
                    }, 4000);
                    return;
                }
                if (armTimer) { clearTimeout(armTimer); }
                del.disabled = true;
                fetch(S.lightapi + '/data/' + spec.table + '/' + row.id, {
                    method: 'DELETE',
                    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': TOKEN },
                    credentials: 'same-origin'
                }).then(readJson)
                    .then(function (res) {
                        var b = res.body || {};
                        if (res.status === 200 && b.ok) { loadList(secKey, spec, host); return; }
                        del.disabled = false;
                        armed = false;
                        del.innerHTML = '<i class="fa fa-trash-o"></i>';
                        // in_use carries the count in detail - data, not prose.
                        say(b.error === 'in_use'
                            ? fmt(L.list_in_use, { count: b.detail })
                            : errText(res.status, res.body, L.save_fail), 'err');
                    }).catch(function () {
                        del.disabled = false;
                        armed = false;
                        del.innerHTML = '<i class="fa fa-trash-o"></i>';
                        say(errText(0, null, L.save_fail), 'err');
                    });
            });
            el_.appendChild(del);
            // Last, so it opens under the whole row once the row may wrap.
            if (tplBox) { el_.appendChild(tplBox); }

            if (spec.sortable) { makeDraggable(el_, grip, listBox, spec); }
            return el_;
        }

        function isOn(v) { return v === '1' || v === 1 || v === true; }

        // 種別 only: the subject and body a new issue of this type starts
        // with. Folded away behind a button on the row - most types have no
        // template, and two more boxes on every row would bury the names.
        function templateBox(spec, row, rowEl) {
            var box = el('div', 'pp-set-tplbox');
            box.hidden = true;
            var toggle = el('button', 'pp-set-tpl');
            toggle.type = 'button';
            toggle.title = L.list_tpl_tip;
            toggle.innerHTML = '<i class="fa fa-file-text-o"></i> ';
            toggle.appendChild(document.createTextNode(L.list_tpl));
            toggle.setAttribute('aria-expanded', 'false');

            var inputs = [];
            var has = function () {
                return inputs.some(function (i) { return i.value.trim() !== ''; });
            };
            [[spec.tpl_subject, L.list_tpl_subject, 'input'], [spec.tpl_body, L.list_tpl_body, 'textarea']].forEach(function (d) {
                if (!d[0]) { return; }
                var f = el('div', 'pp-set-f');
                f.appendChild(el('label', 'pp-set-lab', d[1]));
                var inp = el(d[2], 'pp-set-in');
                if (d[2] === 'input') { inp.type = 'text'; }
                inp.value = row[d[0]] == null ? '' : String(row[d[0]]);
                var last = inp.value;
                inp.addEventListener('change', function () {
                    if (inp.value === last) { return; }
                    var prev = last;
                    var patch = {};
                    patch[d[0]] = inp.value;
                    last = inp.value;
                    toggle.classList.toggle('has', has());
                    patchRow(spec, row.id, patch, function () { last = prev; });
                });
                inputs.push(inp);
                f.appendChild(inp);
                box.appendChild(f);
            });
            box.appendChild(el('div', 'pp-set-note', L.list_tpl_note));
            toggle.classList.toggle('has', has());
            toggle.addEventListener('click', function () {
                box.hidden = !box.hidden;
                rowEl.classList.toggle('tpl-open', !box.hidden);
                toggle.setAttribute('aria-expanded', box.hidden ? 'false' : 'true');
                if (!box.hidden && inputs[0]) { inputs[0].focus(); }
            });
            rowEl.appendChild(toggle);
            return box;
        }

        // Native HTML5 drag, no library: the rows are a flat list in one
        // container, which is the one case the native API handles cleanly.
        function makeDraggable(rowEl, grip, listBox, spec) {
            grip.addEventListener('mousedown', function () { rowEl.draggable = true; });
            grip.addEventListener('mouseup', function () { rowEl.draggable = false; });
            rowEl.addEventListener('dragstart', function (ev) {
                ev.dataTransfer.effectAllowed = 'move';
                // Firefox ignores a drag that carries no data at all.
                ev.dataTransfer.setData('text/plain', rowEl.getAttribute('data-id'));
                listBox.__dragging = rowEl;
                rowEl.classList.add('drag');
            });
            rowEl.addEventListener('dragend', function () {
                rowEl.classList.remove('drag');
                rowEl.draggable = false;
                listBox.querySelectorAll('.pp-set-row.over').forEach(function (n) { n.classList.remove('over'); });
                if (listBox.__moved) {
                    listBox.__moved = false;
                    saveOrder(listBox, spec);
                }
                listBox.__dragging = null;
            });
            rowEl.addEventListener('dragover', function (ev) {
                var src = listBox.__dragging;
                if (!src || src === rowEl) { return; }
                ev.preventDefault();
                ev.dataTransfer.dropEffect = 'move';
                rowEl.classList.add('over');
                var box = rowEl.getBoundingClientRect();
                var after = (ev.clientY - box.top) > box.height / 2;
                listBox.insertBefore(src, after ? rowEl.nextSibling : rowEl);
                listBox.__moved = true;
            });
            rowEl.addEventListener('dragleave', function () { rowEl.classList.remove('over'); });
            rowEl.addEventListener('drop', function (ev) { ev.preventDefault(); });
        }
        function doneNote(listBox) {
            var note = listBox && listBox._doneNote;
            if (!note) { return; }
            var done = [];
            listBox.querySelectorAll('.pp-set-row').forEach(function (n) {
                var cb = n.querySelector('.pp-set-done input');
                var nm = n.querySelector('.nm .pp-set-in');
                if (cb && cb.checked) { done.push(nm ? nm.value : ''); }
            });
            var last = done.length ? done[done.length - 1] : null;
            var rest = done.slice(0, -1);
            note.textContent = last === null ? L.list_done_none
                : String(L.list_done_final).replace(':name', last) +
                    (rest.length ? ' ' + String(L.list_done_others).replace(':names', rest.join('、')) : '');
            note.classList.toggle('warn', last === null);
        }
        function saveOrder(listBox, spec) {
            doneNote(listBox);
            var ids = Array.prototype.map.call(
                listBox.querySelectorAll('.pp-set-row'),
                function (n) { return n.getAttribute('data-id'); }
            );
            fetch(S.lightapi + '/reorder/' + spec.table, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': TOKEN },
                credentials: 'same-origin',
                body: JSON.stringify({ ids: ids })
            }).then(function (r) { return r.json(); })
                .then(function (j) { say(j && j.ok ? L.list_saved : L.list_order_fail, j && j.ok ? 'ok' : 'err'); })
                .catch(function () { say(L.list_order_fail, 'err'); });
        }

        // ---- the workspace of the Files tab (ISS02825) ------------------
        // One workspace of Exment's file server per project. Made here (when
        // the site lets this user make workspaces) or picked among the ones
        // they manage. Its top permissions follow the project's people.
        var FS = S.fileserver || {};
        function paneFiles() {
            var frag = document.createDocumentFragment();
            var F = LANG.fs || {};
            var err = el('div', 'err');
            err.hidden = true;
            var post = function (body, btn) {
                err.hidden = true;
                if (btn) { btn.disabled = true; }
                fetch(S.file_workspace_url, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': TOKEN, 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify(body)
                }).then(readJson).then(function (res) {
                    var j = res.body || {};
                    if (res.status === 200 && j.ok) {
                        FS = j.fileserver || FS;
                        renderPane('fileserver');
                        say(F.saved || '', 'ok');
                        return;
                    }
                    err.textContent = errText(res.status, res.body, L.save_fail);
                    err.hidden = false;
                    if (btn) { btn.disabled = false; }
                }).catch(function () {
                    err.textContent = errText(0, null, L.save_fail);
                    err.hidden = false;
                    if (btn) { btn.disabled = false; }
                });
            };
            frag.appendChild(el('div', 'pp-set-note', F.note || ''));
            var ws = FS.workspace;
            if (ws) {
                var row = el('div', 'pp-set-f');
                row.appendChild(el('label', 'pp-set-lab', F.linked || ''));
                var a = el('a', null, ws.name);
                a.href = ws.url;
                a.target = '_blank';
                a.rel = 'noopener';
                row.appendChild(a);
                frag.appendChild(row);
                frag.appendChild(el('div', 'help', F.perm_note || ''));
                var sync = el('button', 'pp-set-btn', F.sync || '');
                sync.type = 'button';
                sync.addEventListener('click', function () { post({ action: 'sync' }, sync); });
                var unlink = el('button', 'pp-set-btn', F.unlink || '');
                unlink.type = 'button';
                unlink.style.marginLeft = '8px';
                unlink.addEventListener('click', function () {
                    if (window.confirm(F.unlink_confirm || '')) { post({ action: 'unlink' }, unlink); }
                });
                frag.appendChild(sync);
                frag.appendChild(unlink);
                frag.appendChild(err);
                return frag;
            }
            if (FS.can_create) {
                var make = el('button', 'pp-set-btn main', F.create || '');
                make.type = 'button';
                make.addEventListener('click', function () { post({ action: 'create' }, make); });
                var mk = el('div', 'pp-set-f');
                mk.appendChild(make);
                mk.appendChild(el('div', 'help', F.create_help || ''));
                frag.appendChild(mk);
            } else {
                frag.appendChild(el('div', 'pp-set-warn', F.cannot_create || ''));
            }
            var list = FS.pickable || [];
            var pk = el('div', 'pp-set-f');
            pk.appendChild(el('label', 'pp-set-lab', F.pick_label || ''));
            if (!list.length) {
                pk.appendChild(el('div', 'help', F.pick_none || ''));
            } else {
                var sel = el('select', 'pp-set-in');
                list.forEach(function (w) {
                    var o = document.createElement('option');
                    o.value = String(w.id);
                    o.textContent = w.name;
                    sel.appendChild(o);
                });
                pk.appendChild(sel);
                var use = el('button', 'pp-set-btn', F.pick || '');
                use.type = 'button';
                use.style.marginTop = '6px';
                use.addEventListener('click', function () { post({ action: 'pick', workspace_id: sel.value }, use); });
                pk.appendChild(use);
            }
            frag.appendChild(pk);
            frag.appendChild(err);
            return frag;
        }

        // ---- the chat channels (ISS02939) ----------------------------------
        var CH = S.channels || {};
        function paneChannels() {
            var frag = document.createDocumentFragment();
            var C = LANG.ch || {};
            var typeName = { slack: 'Slack', teams: 'Microsoft Teams', line_works: 'LINE WORKS', chatwork: 'Chatwork', webhook: C.type_webhook || 'Webhook (JSON)' };
            var post = function (body, btn, err) {
                err.hidden = true;
                if (btn) { btn.disabled = true; }
                fetch(S.channels_url, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': TOKEN, 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify(body)
                }).then(readJson).then(function (res) {
                    var j = res.body || {};
                    if (res.status === 200 && j.ok) {
                        CH = j.channels || CH;
                        renderPane('channels');
                        say(j.message || C.saved || '', j.sent === false ? 'err' : 'ok');
                        return;
                    }
                    err.textContent = errText(res.status, res.body, L.save_fail);
                    err.hidden = false;
                    if (btn) { btn.disabled = false; }
                }).catch(function () {
                    err.textContent = errText(0, null, L.save_fail);
                    err.hidden = false;
                    if (btn) { btn.disabled = false; }
                });
            };
            var input = function (label, value, placeholder, help) {
                var f = el('div', 'pp-set-f');
                f.appendChild(el('label', 'pp-set-lab', label));
                var i = el('input', 'pp-set-in');
                i.type = 'text';
                i.value = value || '';
                i.placeholder = placeholder || '';
                i.autocomplete = 'off';
                f.appendChild(i);
                if (help) { f.appendChild(el('div', 'help', help)); }
                return [f, i];
            };
            // one channel: kept (c.id) or new (the type chosen)
            var card = function (c, type) {
                var box = el('div', 'pp-set-card');
                box.style.cssText = 'border:1px solid #dfe5ea;border-radius:7px;padding:12px;margin-bottom:12px;';
                var err = el('div', 'err');
                err.hidden = true;
                box.appendChild(el('div', 'pp-set-lab', (typeName[type] || type) + (c.id ? '' : ' — ' + (C.new_channel || ''))));
                var lab = input(C.label || '', c.label, C.label_ph || '');
                box.appendChild(lab[0]);
                var sec = null, tgt = null, sign = null;
                if (type === 'line_works') {
                    tgt = input(C.lw_channel || '', c.target, '', C.lw_help || '');
                } else if (type === 'chatwork') {
                    tgt = input(C.cw_room || '', c.target, '', C.cw_room_help || '');
                    sec = input(C.cw_token || '', '', c.masked ? (C.keep || '') + ' (' + c.masked + ')' : '', C.cw_token_help || '');
                } else {
                    sec = input(C.url || '', '', c.masked ? (C.keep || '') + ' (' + c.masked + ')' : 'https://', C['url_help_' + type] || '');
                    if (type === 'webhook') {
                        sign = input(C.sign || '', '', c.signed ? (C.sign_kept || '') : '', C.sign_help || '');
                    }
                }
                [tgt, sec, sign].forEach(function (x) { if (x) { box.appendChild(x[0]); } });
                var ev = el('div', 'pp-set-f');
                ev.appendChild(el('label', 'pp-set-lab', C.events || ''));
                var checks = {};
                (CH.events || []).forEach(function (name) {
                    var l = el('label', null);
                    l.style.cssText = 'display:inline-flex;align-items:center;gap:4px;margin-right:14px;font-weight:400;';
                    var cb = document.createElement('input');
                    cb.type = 'checkbox';
                    cb.checked = c.id ? (c.events || []).indexOf(name) >= 0 : true;
                    checks[name] = cb;
                    l.appendChild(cb);
                    l.appendChild(document.createTextNode(C['ev_' + name] || name));
                    ev.appendChild(l);
                });
                box.appendChild(ev);
                var on = el('label', null);
                on.style.cssText = 'display:inline-flex;align-items:center;gap:4px;font-weight:400;';
                var onCb = document.createElement('input');
                onCb.type = 'checkbox';
                onCb.checked = c.id ? !!c.enabled : true;
                on.appendChild(onCb);
                on.appendChild(document.createTextNode(C.enabled || ''));
                var onRow = el('div', 'pp-set-f');
                onRow.appendChild(on);
                box.appendChild(onRow);
                var save = el('button', 'pp-set-btn main', c.id ? (C.save || '') : (C.add || ''));
                save.type = 'button';
                save.addEventListener('click', function () {
                    var body = { action: 'save', id: c.id || 0, type: type, label: lab[1].value, enabled: onCb.checked ? 1 : 0, events: [] };
                    Object.keys(checks).forEach(function (k) { if (checks[k].checked) { body.events.push(k); } });
                    if (sec) { body.secret = sec[1].value; }
                    if (tgt) { body.target = tgt[1].value; }
                    if (sign) { body.sign_secret = sign[1].value; }
                    post(body, save, err);
                });
                box.appendChild(save);
                if (c.id) {
                    var test = el('button', 'pp-set-btn', C.test || '');
                    test.type = 'button';
                    test.style.marginLeft = '8px';
                    test.addEventListener('click', function () { post({ action: 'test', id: c.id }, test, err); });
                    var rm = el('button', 'pp-set-btn', C.remove || '');
                    rm.type = 'button';
                    rm.style.marginLeft = '8px';
                    rm.addEventListener('click', function () {
                        if (window.confirm(C.remove_confirm || '')) { post({ action: 'remove', id: c.id }, rm, err); }
                    });
                    box.appendChild(test);
                    box.appendChild(rm);
                }
                box.appendChild(err);
                return box;
            };
            frag.appendChild(el('div', 'pp-set-note', C.note || ''));
            (CH.channels || []).forEach(function (c) { frag.appendChild(card(c, c.type)); });
            if ((CH.channels || []).length < (CH.max || 10)) {
                var add = el('div', 'pp-set-f');
                add.appendChild(el('label', 'pp-set-lab', C.add_label || ''));
                var sel = el('select', 'pp-set-in');
                (CH.types || []).forEach(function (t) {
                    var o = document.createElement('option');
                    o.value = t;
                    o.textContent = typeName[t] || t;
                    sel.appendChild(o);
                });
                add.appendChild(sel);
                var slot = el('div', null);
                slot.style.marginTop = '8px';
                var draw = function () { slot.textContent = ''; slot.appendChild(card({}, sel.value)); };
                sel.addEventListener('change', draw);
                add.appendChild(slot);
                frag.appendChild(add);
                draw();
            } else {
                frag.appendChild(el('div', 'help', C.max_reached || ''));
            }
            return frag;
        }

        var PANES = {
            basic: paneBasic, people: panePeople, features: paneFeatures,
            other: paneOther, git: paneGit, ai: paneAi, danger: paneDanger, fileserver: paneFiles, channels: paneChannels,
            statuses: function () { return paneList('statuses'); },
            types: function () { return paneList('types'); },
            categories: function () { return paneList('categories'); },
            milestones: function () { return paneList('milestones'); }
        };

        function renderPane(name) {
            current = name;
            FIELDS = {};
            // A project date column and a milestone's two dates both carry a
            // picker, and the calendar opens off <body> - so each one has to
            // be released before the pane that holds it is emptied.
            if (window.ppDateFieldDestroy) { window.ppDateFieldDestroy(body); }
            body.textContent = '';
            say('');
            var build = PANES[name] || paneBasic;
            body.appendChild(build());
            body.hidden = false;
            // The save bar belongs to the panes that edit project columns.
            // Git and delete carry their own buttons, and a master list
            // saves each row the moment it is changed.
            foot.hidden = ['git', 'fileserver', 'channels', 'danger', 'statuses', 'types', 'categories', 'milestones'].indexOf(name) >= 0;
            nav.querySelectorAll('a').forEach(function (a) {
                a.classList.toggle('on', a.getAttribute('data-sec') === name);
            });
            var active = nav.querySelector('a[data-sec="' + name + '"]');
            title.textContent = active ? active.textContent.trim() : '';
        }

        // ---- server ------------------------------------------------------
        // The row as the server holds it now. Forced past the portal's cache
        // every time: that copy was taken when the page opened, maybe an
        // hour ago, and a people list read then and saved now would take
        // back whatever a co-worker changed in between. The portal shares an
        // answer that is still on its way, so a prewarm in flight is reused.
        function fetchForm() {
            return window.ppFetchEditForm
                ? window.ppFetchEditForm(S.table, S.id, true)
                : ppq(function () {
                    return fetch(S.edit_url, {
                        credentials: 'same-origin',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    }).then(function (r) {
                        if (!r.ok) { throw new Error(String(r.status)); }
                        return r.json();
                    });
                });
        }
        function adopt(j) {
            COLUMNS = {};
            (j.columns || []).forEach(function (c) { COLUMNS[c.name] = c; });
            VALUES = copyPlain(j.row && j.row.value);
            VALUES._labels = (j.row && j.row.labels) || {};
            ROW_AT = (j.row && j.row.updated_at) || null;
            if (!(j.permissions && j.permissions.can_edit)) {
                body.textContent = '';
                body.appendChild(el('div', 'pp-set-warn', L.no_perm));
                body.hidden = false;
                foot.hidden = true;
                return;
            }
            renderPane(current);
        }
        function load() {
            if (COLUMNS || loading) { return Promise.resolve(); }
            loading = true;
            spin.hidden = false;
            body.hidden = true;
            foot.hidden = true;
            return fetchForm().then(function (j) {
                spin.hidden = true;
                loading = false;
                adopt(j);
            }).catch(function () {
                loading = false;
                spin.hidden = true;
                body.textContent = '';
                body.appendChild(el('div', 'pp-set-warn', L.load_fail));
                body.hidden = false;
            });
        }
        // The tab opened again: read the row again. A pane with edits in it
        // keeps what it read - its save still names that version, so a
        // change made since is reported (409) rather than written over - and
        // an answer naming the version already shown changes nothing.
        function refresh() {
            if (!COLUMNS || loading) { return Promise.resolve(); }
            loading = true;
            return fetchForm().then(function (j) {
                loading = false;
                var at = (j.row && j.row.updated_at) || null;
                if (at === ROW_AT || Object.keys(diff()).length) { return; }
                // The roster under the people pickers is read once and kept;
                // a row that moved on may have moved its people too.
                ROSTER = null;
                adopt(j);
            }).catch(function () {
                loading = false;
            });
        }

        function save() {
            clearErrors();
            var payload = diff();
            if (!Object.keys(payload).length) { say(L.no_change); return; }

            saveBtn.disabled = true;
            saveBtn.textContent = L.saving;
            say('');
            fetch(S.save_url, {
                method: 'PATCH',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': TOKEN,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                // The version this pane read, so a co-worker's save in
                // between is reported rather than written over. On the people
                // pickers that matters twice: a list read before someone was
                // added would take them off again, and with them their access.
                body: JSON.stringify({ value: payload, updated_at: ROW_AT })
            }).then(readJson).then(function (res) {
                var j = res.body || {};
                if (res.status === 200 && j.ok) {
                    say(L.saved, 'ok');
                    // The name, the key and the feature switches all shape the
                    // page around this tab, so the honest answer is to redraw
                    // the whole thing rather than patch pieces of it.
                    setTimeout(function () { location.reload(); }, 400);
                    return;
                }
                if (res.status === 409 && j.error === 'stale') {
                    say(fmt(j.updated_by ? L.stale_by : L.stale, { name: j.updated_by, at: j.updated_at }), 'err');
                } else if (j.fields) {
                    Object.keys(j.fields).forEach(function (name) { putError(name, j.fields[name]); });
                    say(errText(res.status, res.body, L.save_fail), 'err');
                } else {
                    say(errText(res.status, res.body, L.save_fail), 'err');
                }
                saveBtn.disabled = false;
                saveBtn.textContent = L.save;
            }).catch(function () {
                say(errText(0, null, L.save_fail), 'err');
                saveBtn.disabled = false;
                saveBtn.textContent = L.save;
            });
        }

        // Turning the project's Git switch on from the Git tab itself, so
        // that asking for a repository is one place rather than two. The
        // switch is saved, not just flipped on screen: postGitRepo refuses a
        // request made against an unsaved one, and rightly - whatever a
        // reload shows has to be what the screen just claimed.
        function enableGit(btn, errSlot) {
            if (!colOf('use_git')) { return; }
            errSlot.hidden = true;
            btn.disabled = true;
            btn.textContent = L.saving;
            fetch(S.save_url, {
                method: 'PATCH',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': TOKEN,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ value: { use_git: '1' } })
            }).then(readJson).then(function (res) {
                var j = res.body || {};
                if (res.status === 200 && j.ok) {
                    // No reload: nothing outside this pane and the feature
                    // tab's own switch reads the flag, and both are drawn
                    // from VALUES the next time they are rendered.
                    VALUES.use_git = '1';
                    // This save moved the row on; the next one from this pane
                    // starts from here, not from the version it first read.
                    ROW_AT = j.updated_at || ROW_AT;
                    S.git_on = true;
                    renderPane('git');
                    return;
                }
                errSlot.textContent = errText(res.status, res.body, L.save_fail);
                errSlot.hidden = false;
                btn.disabled = false;
                btn.textContent = G.enable;
            }).catch(function () {
                errSlot.textContent = errText(0, null, L.save_fail);
                errSlot.hidden = false;
                btn.disabled = false;
                btn.textContent = G.enable;
            });
        }

        function requestRepo(input, errSlot, btn) {
            errSlot.hidden = true;
            btn.disabled = true;
            btn.textContent = G.requesting;
            fetch(S.git_url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': TOKEN,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ repo_name: input.value.trim().toLowerCase() })
            }).then(readJson).then(function (res) {
                var j = res.body || {};
                if (res.status === 200 && j.ok) {
                    GIT = j.git || GIT;
                    renderPane('git');
                    return;
                }
                errSlot.textContent = errText(res.status, res.body, L.save_fail);
                errSlot.hidden = false;
                btn.disabled = false;
                btn.textContent = G.request;
            }).catch(function () {
                errSlot.textContent = errText(0, null, L.save_fail);
                errSlot.hidden = false;
                btn.disabled = false;
                btn.textContent = G.request;
            });
        }

        function destroy(input, errSlot, btn) {
            errSlot.hidden = true;
            btn.disabled = true;
            btn.textContent = L.deleting;
            fetch(S.delete_url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': TOKEN,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ confirm: input.value.trim() })
            }).then(readJson).then(function (res) {
                var j = res.body || {};
                if (res.status === 200 && j.ok) {
                    errSlot.textContent = '';
                    btn.textContent = L.deleted;
                    location.href = j.redirect || S.dashboard_url;
                    return;
                }
                errSlot.textContent = errText(res.status, res.body, L.save_fail);
                errSlot.hidden = false;
                btn.disabled = false;
                btn.textContent = L.danger_button;
            }).catch(function () {
                errSlot.textContent = errText(0, null, L.save_fail);
                errSlot.hidden = false;
                btn.disabled = false;
                btn.textContent = L.danger_button;
            });
        }

        // ---- wiring ------------------------------------------------------
        // Leaving a pane that holds unsaved edits takes a second click: the
        // first one says what would be lost. Two clicks rather than a
        // confirm() dialog, the same as the delete buttons - a browser modal
        // inside this page freezes every later interaction.
        var leaveArmed = null;
        var leaveTimer = null;
        nav.addEventListener('click', function (ev) {
            var a = ev.target.closest('a[data-sec]');
            if (!a) { return; }
            var want = a.getAttribute('data-sec');
            // Before the schema arrives there is nothing to draw yet, so the
            // choice is remembered and load() paints it when it lands.
            if (!COLUMNS) { current = want; load(); return; }
            if (Object.keys(diff()).length && leaveArmed !== want) {
                leaveArmed = want;
                say(L.unsaved_switch, 'err');
                if (leaveTimer) { clearTimeout(leaveTimer); }
                leaveTimer = setTimeout(function () { leaveArmed = null; }, 4000);
                return;
            }
            leaveArmed = null;
            renderPane(want);
        });
        saveBtn.addEventListener('click', save);

        // The pane is built the first time the tab is actually opened: the
        // schema request is one round trip that most visits never need.
        // Every later opening reads the row again (refresh), so a tab that
        // sat in the background does not edit from an old copy.
        function maybeLoad() {
            if (!host.classList.contains('on')) { return; }
            if (!COLUMNS) { load(); } else { refresh(); }
        }
        // Which script gets to run first is not ours to choose. On a full
        // page load the portal router is parsed before this block, so its
        // own hashchange listener marks the screen before ours looks at it;
        // after a pjax swap this block runs first and the order flips, so a
        // hashchange listener here would check the screen before the router
        // had opened it, find nothing to do, and leave the pane sitting on
        // "loading" for ever - with no second hashchange to come, because
        // clicking the tab again sets the hash it already has. The class the
        // router puts on the section is the one signal that arrives in the
        // right order on both paths, whoever opened the screen.
        // Only the moment the screen opens counts: any other change to the
        // section's classes is not a reason to read the row again.
        var wasOpen = host.classList.contains('on');
        var watch = new MutationObserver(function () {
            // pjax replaces #pp-settings but never this observer; a detached
            // host keeps its classes, so a stale instance would keep loading
            // its old project. Retire it once its page is gone.
            if (document.getElementById('pp-settings') !== host) {
                watch.disconnect();
                return;
            }
            var open = host.classList.contains('on');
            if (open && !wasOpen) { maybeLoad(); }
            wasOpen = open;
        });
        watch.observe(host, { attributes: true, attributeFilter: ['class'] });
        // Clicking the settings tab while it is already the open screen
        // changes neither hash nor class, so nothing above fires. Without
        // this, a pane whose first load failed could not be retried from the
        // tab it belongs to.
        document.addEventListener('click', function onTab(ev) {
            if (document.getElementById('pp-settings') !== host) {
                document.removeEventListener('click', onTab);
                return;
            }
            if (ev.target && ev.target.closest && ev.target.closest('[data-scr="settings"]')) {
                setTimeout(maybeLoad, 0);
            }
        });
        maybeLoad();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
</script>
