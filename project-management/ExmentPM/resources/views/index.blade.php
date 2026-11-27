@php
    $lang = collect([
        'tab_home', 'tab_board', 'tab_issues', 'tab_gantt', 'tab_burndown', 'tab_wiki', 'tab_files',
        'period', 'pm', 'add_issue', 'search_ph',
        'stat_all', 'stat_open', 'stat_overdue', 'stat_soon',
        'status_box', 'ms_box', 'members_box', 'feed_box',
        'role_pm', 'role_member', 'role_viewer', 'ms_left', 'unit_cnt', 'unit_day', 'sep_dot',
        'hc_loading', 'hc_fail', 'hc_norole', 'hc_code', 'hc_orgs', 'hc_assigned',
        'hc_open_overdue', 'hc_no_issue',
        'feed_add', 'feed_chg', 'feed_cmt', 'feed_file', 'feed_wiki', 'feed_wiki_add', 'feed_project',
        'f_all', 'f_kw', 'f_as', 'f_ty', 'f_ms', 'f_cat', 'f_rd', 'f_rd_unread', 'f_rd_read', 'f_save', 'f_saved',
        'f_pr', 'f_mine', 'f_clear', 'f_cleared', 'f_csv', 'f_csv_title', 'f_csv_empty',
        'sort_asc', 'sort_desc',
        'q_overdue', 'q_soon', 'q_created', 'q_watch', 'q_unread', 'q_review',
        'rv_ask', 'rv_ask_hint', 'rv_need_people', 'rv_need_text', 'rv_label', 'rv_wait', 'rv_ok',
        'rv_confirm', 'rv_confirm_tip', 'rv_undo', 'rv_undo_tip', 'rv_done', 'rv_undone', 'rv_fail',
        'pg_size', 'pg_all', 'pg_range', 'pg_prev', 'pg_next', 'pg_page',
        'sf_menu', 'sf_name_ph', 'sf_add', 'sf_none', 'sf_del', 'sf_saved', 'sf_deleted',
        'sf_need_name', 'sf_full',
        'bk_selected', 'bk_keep', 'bk_none', 'bk_apply', 'bk_again', 'bk_working', 'bk_clear',
        'bk_nothing', 'bk_done', 'bk_denied', 'bk_missing', 'bk_fail', 'bk_failed', 'bk_failed_title',
        'rf_button', 'rf_working', 'rf_done', 'rf_fail', 'rf_stale', 'rf_at',
        'col_ty', 'col_key', 'col_subject', 'col_as', 'col_st', 'col_pr', 'col_ms', 'col_cat',
        'col_sd', 'col_dd', 'col_dt', 'col_rc', 'rc_daily', 'rc_weekdays', 'rc_weekly', 'rc_monthly', 'rc_yearly', 'col_pg', 'col_up', 'cnt_line', 'overdue_d', 'left_d',
        'back', 'box_props', 'box_children', 'box_timeline', 'added',
        'box_related', 'children_done', 'rel_add', 'rel_none', 'rel_add_open', 'to_comments', 'issue_more', 'dm_more', 'dm_less', 'dm_more_tip', 'dm_less_tip', 'dm_hint', 'act_edit_tip', 'act_more_tip', 'act_watch_tip',
        'rel_self', 'rel_dup', 'rel_fail', 'rel_del_fail',
        'lbl_eh', 'lbl_pg', 'rollup', 'rollup_tip', 'lbl_parent', 'lbl_watch', 'lbl_source', 'lbl_sub_assignees', 'lbl_managers', 'lbl_reviewers', 'lbl_difficulty', 'lbl_author', 'df_easy', 'df_normal', 'df_hard', 'lbl_custom_fields', 'src_created', 'src_updated', 'src_assignees', 'src_assignees_hint', 'watch', 'unwatch', 'star', 'star_label', 'star_on', 'star_on_tip', 'star_off_tip', 'edit', 'to_next',
        'notify_to', 'notify_mail', 'notify_mail_hint',
        'nt_teams', 'nt_teams_hint', 'nt_bell', 'nt_mail', 'nt_by_teams', 'nt_tip', 'nt_line_mail', 'nt_line_teams', 'nt_line_bell', 'nt_others', 'nt_sent_mail', 'nt_sent_teams',
        'mail_btn', 'mail_title', 'mail_to', 'mail_msg_ph', 'mail_send',
        'mail_cancel', 'mail_none', 'mail_sent', 'mail_skipped', 'mail_fail', 'mail_note', 'reads', 'reads_none', 'reads_old', 'ctx_read', 'ctx_unread', 'ctx_open', 'ctx_read_all', 'pv_download', 'pv_open', 'pv_edit', 'pv_more', 'pv_big', 'pv_fail', 'pv_expand', 'pv_collapse', 'pv_unfold', 'pv_big_view', 'l_more', 'l_more_tip', 'l_bulk_pick', 'attach_ver', 'attach_versions', 'attach_edit', 'attach_read', 'attach_close', 'attach_unsaved', 'attach_fail', 'attach_col_ver', 'attach_col_name', 'attach_col_by', 'attach_col_at', 'attach_current', 'attach_download', 'attach_restore', 'attach_restore_ask', 'attach_restored', 'attach_locked_by', 'attach_encoding', 'attach_table', 'attach_preview', 'attach_save', 'attach_saved', 'attach_popup', 'f_dl_csv_all', 'f_dl_csv_screen', 'f_dl_xlsx_all', 'f_dl_xlsx_screen', 'f_dl_busy', 'f_dl_fail', 'tree_ctx_open', 'tree_ctx_close', 'tree_ctx_open_below', 'tree_ctx_close_below', 'tree_ctx_open_all', 'tree_ctx_close_all', 'f_carried_cleared', 'col_tree', 'ar_hidden', 'ar_shown', 'ar_show', 'ar_hide', 'ar_loading', 'ar_fail', 'read_all_done', 'read_all_fail', 'tree_ctx_open_w', 'tree_ctx_close_w', 'ctx_add_child', 'ctx_change_parent', 'ctx_copy_link', 'fav', 'fav_on', 'fav_tip', 'fav_add', 'fav_off', 'fav_added', 'fav_removed', 'fav_fail', 'col_alias', 'alias', 'alias_set', 'alias_ask', 'alias_saved', 'alias_fail', 'f_multi', 'f_multi_n', 'f_multi_find', 'f_multi_ok', 'f_multi_clear',
        'st_change', 'st_keep', 'submit', 'posting', 'posted', 'post_fail',
        'dock_open', 'dock_title', 'dock_close', 'dock_cancel', 'dock_cmt', 'dock_edit', 'dock_keep',
        'dock_draft', 'dock_saving', 'dock_save_fail', 'dock_stale', 'dock_stale_keep', 'dock_empty',
        'qe_unlisted', 'qe_extra',
        'lbl_eh_only', 'lbl_ah_only',
        'ta_user', 'ta_issue', 'ta_file', 'ta_none', 'ta_hint',
        'cmt_ph', 'quote', 'del', 'file_added', 'people', 'empty',
        'box_files', 'tl_all', 'tl_cmt', 'child_add', 'issue_copy', 'link_copy', 'link_copied', 'branch_copy', 'branch_copied', 'git_pr', 'git_commit', 'git_open', 'git_merged', 'git_closed', 'git_done', 'ik_copied', 'ik_key_copied', 'ik_copy_tip', 'office_view', 'office_view_tip', 'office_close', 'office_newtab', 'office_unsaved', 'desc_quote',
        'nav_prev', 'nav_next', 'nav_pos', 'tl_expand', 'tl_collapse', 'cmt_more', 'cmt_less', 'ct_emoji',
        'cmt_hint', 'cmt_drop', 'ct_attach', 'ct_bold', 'ct_italic', 'ct_strike',
        'ct_ul', 'ct_ol', 'ct_quote', 'ct_code', 'ct_link', 'ct_preview', 'ct_write',
        'ct_link_label', 'cmt_uploading', 'cmt_up_ok', 'cmt_up_fail', 'ned_file_after_create', 'cmt_menu',
        'rx_add', 'rx_fail', 'rx_like', 'rx_heart', 'rx_haha', 'rx_wow', 'rx_sad', 'rx_angry', 'rx_thanks', 'rx_clap', 'rx_party', 'rx_eyes', 'rx_ok',
        'cmt_edit', 'cmt_copy', 'cmt_copied', 'cmt_save', 'cmt_cancel', 'cmt_saved',
        'cmt_link', 'cmt_link_tip', 'cmt_not_found', 'proj_settings', 'qf_edit', 'qf_save', 'qf_stale',
        'lbl_notify', 'lbl_notified', 'f_rl', 'oc_menu',
        'cmt_del_ask', 'cmt_deleted', 'cmt_del_fail',
        'chg_hide', 'chg_hide_ask', 'chg_hidden', 'chg_hide_fail',
        'wiki_pages', 'wiki_add', 'wiki_edit', 'wiki_hist', 'wiki_files', 'wiki_meta', 'wiki_none', 'wiki_empty',
        'board_none', 'gantt_none',
        'bd_box', 'bd_y', 'bd_plan', 'bd_actual', 'bd_forecast', 'bd_today', 'bd_left', 'bd_done',
        'bd_days_left', 'bd_forecast_warn', 'bd_forecast_ok', 'bd_no_ms', 'bd_partial', 'files_partial',
        'files_name', 'files_by', 'files_at', 'files_src', 'files_drop', 'files_pick',
        'files_up_ok', 'files_up_fail', 'files_empty',
        'fs_title', 'fs_open', 'fs_attachments', 'fs_none', 'fs_none_manager', 'fs_no_access', 'back_projects',
        'tab_settings', 'm_add_issue', 'm_edit_issue', 'm_edit_wiki', 'm_new_wiki', 'm_history',
        'm_saved', 'm_discard', 'm_discard_yes', 'm_discard_no', 'm_loading', 'm_close',
        'ned_title', 'ned_save', 'ned_saving', 'ned_saved', 'ned_error', 'ned_close',
        'ned_cancel', 'ned_readonly', 'ned_required', 'ned_no_edit', 'ned_more', 'ned_more_filled',
        'ned_pick_user', 'ned_pick_rel', 'ned_none', 'ned_new',
        'ned_shortcut_save', 'ned_shortcut_close', 'ned_key_mod', 'ned_key_mod_mac',
        'ned_change_count', 'ned_hero_ph', 'ned_side',
        'ned_meta_created', 'ned_meta_updated', 'ned_meta_by', 'ned_key_copy', 'ned_key_copied',
        'ned_back', 'ned_files_drop_hint', 'ned_validation',
        'lkp_search', 'lkp_empty',
        'ib_title_parent', 'ib_title_rel', 'ib_search', 'ib_col_key', 'ib_col_subject',
        'ib_empty', 'ib_loading', 'ib_close', 'ib_clear', 'ib_change', 'ib_pick', 'ib_hint',
        'draft_found', 'draft_restore', 'draft_discard',
        'form_style', 'form_style_saved',
        'md_head', 'md_bold', 'md_italic', 'md_strike', 'md_list', 'md_list_ordered',
        'md_list_check', 'md_quote', 'md_code', 'md_link', 'md_table', 'md_rule',
        'md_mention', 'md_mention_added', 'md_emoji', 'md_file', 'md_help',
        'md_tab_edit', 'md_tab_prev', 'md_prev_empty',
        'ne_parent_set', 'ne_mine', 'ne_as_help', 'ne_qa_open', 'ne_qa_ph', 'ne_qa_add',
        'ne_qa_done', 'ne_qa_fail',
        'ned_stale', 'ned_stale_by', 'ned_stale_reload', 'ned_stale_force', 'ned_reloaded',
        'issue_move', 'move_title', 'move_pick', 'move_note', 'move_do', 'move_cancel', 'move_loading',
        'move_none', 'move_fail', 'issue_delete', 'issue_del_ask', 'issue_del_fail',
        // UX-C
        'bc_label', 'bc_root', 'bc_hidden', 'bc_more', 'qf_click', 'qf_unset', 'qf_undo', 'qf_undone', 'qf_today', 'qf_tomorrow', 'qf_next_week', 'qf_assign_me',
        // UX-D
        'pj_switch', 'proj_info',
        // UX-E
        'ed_hint', 'ed_pen', 'mine_all', 'gantt_hint', 'gantt_list', 'kb_prev', 'kb_next', 'kb_cols', 'st_menu',
        // UX-F
        'feed_more', 'pal_title', 'pal_ph', 'pal_none', 'pal_issues', 'pal_assign_me', 'kh_palette', 'sh_title', 'sh_palette', 'sh_home_1', 'sh_home_2', 'sh_home_3', 'sh_issues_1', 'sh_issues_2', 'sh_issues_3', 'sh_issues_4', 'sh_board_1', 'sh_board_2', 'sh_gantt_1', 'sh_gantt_2', 'sh_burndown_1', 'sh_wiki_1', 'sh_wiki_2', 'sh_files_1', 'sh_issue_1', 'sh_issue_2', 'sh_issue_3', 'sh_issue_4', 'sh_issue_5', 'hint_remind', 'hint_remind_go', 'hint_saved', 'hint_saved_go', 'pos_add_tip', 'md_ph', 'md_badge', 'md_badge_tip', 'md_split', 'md_split_tip', 'md_write', 'mdx_h', 'mdx_b', 'mdx_ul', 'mdx_ol', 'mdx_q', 'mdx_code', 'mdx_link', 'mdx_table', 'mdx_table_ins', 'mdx_task', 'mdx_img', 'mdx_img_how', 'kh_title', 'kh_move', 'kh_list', 'kh_issue', 'kh_other', 'kh_search', 'kh_updown', 'kh_open',
        'kh_comment', 'kh_status', 'kh_prevnext', 'kh_send', 'kh_close', 'kh_help', 'empty_issues', 'empty_filtered', 'empty_wiki',
        'first_steps', 'fs_add_issue', 'fs_invite', 'fs_statuses', 'f_clear_all',
        // UX-H
        'ned_more_find',
    ])->mapWithKeys(function ($key) {
        return [$key => \App\Plugins\ExmentPM\PortalDefinition::trans($key)];
    })->toArray();
    // The issue form's AI button reads its strings as one block (L.ai.*).
    $lang['ai'] = \App\Plugins\ExmentPM\PortalDefinition::trans('ai');
    // The custom attributes of the project's issues (ISS02939): the form, the detail, the list
    // and its filters read their strings as one block (L.attributes.*).
    $lang['attributes'] = \App\Plugins\ExmentPM\PortalDefinition::trans('attributes');
    // The records of other tables the issues link to (ISS02939): the detail's box (L.links.*).
    $lang['links'] = \App\Plugins\ExmentPM\PortalDefinition::trans('links');
    // Wording for a failure the portal did not word itself - an expired
    // session, a proxy's error page, a dropped line - picked in the client
    // by the HTTP status (errText). Embedded once so no English transport
    // text ever reaches the screen.
    $lang['http'] = \App\Plugins\ExmentPM\PortalDefinition::httpErrors();
@endphp
@php
    // Backlog's per project switches decide which tabs exist at all. The
    // data behind a hidden tab is not deleted - and not even read, see
    // buildPayload() - so switching one back on brings everything with it.
    $feat = $payload['project']['features'] ?? [];
    $canManage = !empty($payload['can']['manage']);
@endphp
<div class="pmportal" id="pmportal">

    {{-- The project's name and note are already in the portal's own header
         card below, so Exment's page header would show them twice (snoro
         2026-11-18). Only on this page; the browser tab title is unchanged. --}}
    <style>#app > .content-header{display:none;}</style>
    {{-- The custom attributes (ISS02939): the widgets of the issue form look
         like its other inputs, and the filter row wraps like the one above. --}}
    <style>
    .pp-nedit .pp-ne-at input.pp-ne-atin,.pp-nedit .pp-ne-at textarea.pp-ne-atin,.pp-nedit .pp-ne-at select.pp-ne-atin{
      border:1px solid var(--bl-line);background:var(--bl-box);color:var(--bl-ink);border-radius:var(--bl-radius-s,4px);
      padding:6px 10px;font-size:13px;line-height:1.4;font-family:inherit;min-height:32px;width:100%;box-sizing:border-box;}
    .pp-nedit .pp-ne-at .pp-ne-atin:focus{border-color:var(--bl-accent);box-shadow:0 0 0 3px rgba(76,175,147,.16);outline:0;}
    .pp-nedit .pp-ne-at .pp-ne-atin[readonly],.pp-nedit .pp-ne-at .pp-ne-atin:disabled{background:var(--bl-box2);color:var(--bl-sub);}
    .pp-nedit .pp-ne-at.err .pp-ne-atin{border-color:#e57373;}
    .pp-nedit .pp-ne-at > .err{margin-top:6px;}
    .pmportal .pp-f-at{display:flex;flex-wrap:wrap;align-items:center;gap:6px 10px;margin-top:6px;}
    .pmportal .pp-f-at[hidden]{display:none;}
    </style>

    {{-- Defines window.ppDateField, used by the panes below and by the
         main script. Included before them so it is there when they run. --}}
    @include(\App\Plugins\ExmentPM\PortalDefinition::view('_datepicker'))

    {{-- The portal menu (ダッシュボード / プロジェクト / 最近見た課題) stays at
         the very top, the project's name right under it (snoro 2026-11-18). --}}
    @include(\App\Plugins\ExmentPM\PortalDefinition::view('_topbar'))

    {{-- project header + tabs + screens; the header is filled by the script --}}
    <div class="bl-app">
        <div class="bl-projhead" id="pp-projhead"></div>
        <nav class="bl-tabs" id="pp-tabs">
            <a class="bl-tab" data-scr="home"><i class="fa fa-home"></i>{{ $lang['tab_home'] }}</a>
            <a class="bl-tab" data-scr="board"><i class="fa fa-columns"></i>{{ $lang['tab_board'] }}</a>
            <a class="bl-tab" data-scr="issues"><i class="fa fa-tasks"></i>{{ $lang['tab_issues'] }}</a>
            @if(array_get($feat, 'gantt', true))
            <a class="bl-tab" data-scr="gantt"><i class="fa fa-align-left"></i>{{ $lang['tab_gantt'] }}</a>
            <a class="bl-tab" data-scr="burndown"><i class="fa fa-line-chart"></i>{{ $lang['tab_burndown'] }}</a>
            @endif
            @if(array_get($feat, 'wiki', true))
            <a class="bl-tab" data-scr="wiki"><i class="fa fa-book"></i>{{ $lang['tab_wiki'] }}</a>
            @endif
            @if(array_get($feat, 'file', true))
            <a class="bl-tab" data-scr="files"><i class="fa fa-paperclip"></i>{{ $lang['tab_files'] }}</a>
            @endif
            <span class="bl-tabsearch"><i class="fa fa-search"></i><input id="pp-jump" placeholder="{{ $lang['search_ph'] }}"></span>
            {{-- POS-1: the project's settings at the right end; the gear beside its name opens the same --}}
            @if($canManage)
            <a class="bl-tab pp-tab-set" data-scr="settings"><i class="fa fa-cog"></i>{{ $lang['tab_settings'] }}</a>
            @endif
            {{-- S5: 課題の追加 is the top bar's (it adds to this project at once). --}}
        </nav>

        {{-- ============ home ============ --}}
        <section class="bl-screen" id="pp-home">
            {{-- M10: filled by the script while the project has no issue --}}
            <div class="pp-home-empty" id="pp-home-empty" hidden></div>
            <div class="bl-stats" id="pp-stats"></div>
            {{-- HINT-1: shown once, when it fits the project (hintShow) --}}
            <div class="pp-ed-hint pp-hint" id="pp-hint-home" hidden data-reminder-url="{{ $reminder_url ?? '' }}"></div>
            <div class="bl-home">
                <div>
                    <div class="bl-card-box">
                        <div class="bl-boxhead"><i class="fa fa-pie-chart"></i>{{ $lang['status_box'] }}</div>
                        <div class="bl-boxbody">
                            <div class="bl-stack" id="pp-stack"></div>
                            <div class="bl-leg" id="pp-leg"></div>
                        </div>
                    </div>
                    <div class="bl-card-box">
                        <div class="bl-boxhead"><i class="fa fa-flag-checkered"></i>{{ $lang['ms_box'] }}</div>
                        <div class="bl-boxbody bl-ms" id="pp-ms"></div>
                    </div>
                    <div class="bl-card-box">
                        <div class="bl-boxhead"><i class="fa fa-users"></i>{{ $lang['members_box'] }}</div>
                        <div class="bl-boxbody" id="pp-members"></div>
                    </div>
                </div>
                <div>
                    <div class="bl-card-box">
                        <div class="bl-boxhead"><i class="fa fa-rss"></i>{{ $lang['feed_box'] }}</div>
                        <div class="bl-boxbody bl-feed" id="pp-feed"></div>
                    </div>
                </div>
            </div>
        </section>

        {{-- ============ board (the real kanban view, embedded) ============ --}}
        <section class="bl-screen" id="pp-board">
            @if(!empty($lazy_widgets))
                @if($board_available)
                <div class="pp-embed" id="pp-embed-board" data-embed="board" data-embed-url="{{ $payload['urls']['embed_board'] }}">
                    <div class="pp-embed-loading bl-card-box"><div class="bl-boxbody"><i class="fa fa-spinner fa-spin"></i> {{ $lang['m_loading'] }}</div></div>
                </div>
                @else
                <div class="bl-card-box"><div class="bl-boxbody"><i class="fa fa-info-circle"></i> {{ $lang['board_none'] }}</div></div>
                @endif
            @else
                @if($board_html)
                {!! $board_html !!}
                @else
                <div class="bl-card-box"><div class="bl-boxbody"><i class="fa fa-info-circle"></i> {{ $lang['board_none'] }}</div></div>
                @endif
            @endif
        </section>

        {{-- ============ issues ============ --}}
        <section class="bl-screen" id="pp-issues">
            <div class="bl-toolbar">
                {{-- Status tabs and the quick chips flow as one wrapping
                     row (compact layout, snoro 2026-11-18). --}}
                <div class="bl-pillrow">
                    <div class="bl-sttabs" id="pp-sttabs"></div>
                    {{-- The dashboard's own cuts, on the project's list. --}}
                    <div class="bl-qchips" id="pp-qchips"></div>
                </div>
                <div class="bl-filters">
                    <input id="pp-f-kw" placeholder="{{ $lang['f_kw'] }}" style="width:160px;">
                    <select id="pp-f-as"><option value="">{{ $lang['f_as'] }}: {{ $lang['f_all'] }}</option></select>
                    <select id="pp-f-ty"><option value="">{{ $lang['f_ty'] }}: {{ $lang['f_all'] }}</option></select>
                    <select id="pp-f-pr"><option value="">{{ $lang['f_pr'] }}: {{ $lang['f_all'] }}</option></select>
                    <select id="pp-f-ms"><option value="">{{ $lang['f_ms'] }}: {{ $lang['f_all'] }}</option></select>
                    <select id="pp-f-cat"><option value="">{{ $lang['f_cat'] }}: {{ $lang['f_all'] }}</option></select>
                    <select id="pp-f-rd"><option value="">{{ $lang['f_rd'] }}: {{ $lang['f_all'] }}</option><option value="unread">{{ $lang['f_rd'] }}: {{ $lang['f_rd_unread'] }}</option><option value="read">{{ $lang['f_rd'] }}: {{ $lang['f_rd_read'] }}</option></select>
                    {{-- ISS02931: the other people of an issue and its difficulty;
                         shown by the script where the issue table has them. --}}
                    <select id="pp-f-rl" hidden><option value="">{{ $lang['f_rl'] }}: {{ $lang['f_all'] }}</option></select>
                    <select id="pp-f-df" hidden><option value="">{{ $lang['lbl_difficulty'] }}: {{ $lang['f_all'] }}</option></select>
                    <span class="bl-fright">
                        {{-- Tree view (ISS00253): off by default; the two
                             fold links only show while it is on. --}}
                        <span class="pp-oc-wrap" id="pp-oc-wrap" hidden>
                            <button type="button" class="sv pp-oc-btn" id="pp-oc-btn" aria-haspopup="true" aria-expanded="false">
                                <i class="fa fa-columns"></i> {{ $lang['oc_menu'] }} <i class="fa fa-caret-down"></i>
                            </button>
                            <div class="pp-oc-menu" id="pp-oc-menu" hidden></div>
                        </span>
                        <button type="button" class="bl-treebtn" id="pp-tree-btn" aria-pressed="false"><i class="fa fa-sitemap"></i> {{ $lang['tree_view'] ?? 'ツリー表示' }}</button>
                        <a class="sv" href="javascript:void(0);" id="pp-tree-open" hidden>{{ $lang['tree_open_all'] ?? 'すべて開く' }}</a>
                        <a class="sv" href="javascript:void(0);" id="pp-tree-close" hidden>{{ $lang['tree_close_all'] ?? 'すべて閉じる' }}</a>
                        {{-- One named condition per row, plus the "open the
                             list like this next time" switch the single slot
                             used to be on its own. --}}
                        <span class="pp-sf-wrap">
                            <button type="button" class="sv pp-sf-btn" id="pp-sf-btn" aria-haspopup="true" aria-expanded="false">
                                <i class="fa fa-bookmark-o"></i> {{ $lang['sf_menu'] }} <i class="fa fa-caret-down"></i>
                            </button>
                            <div class="pp-sf-menu" id="pp-sf-menu" hidden>
                                <div class="pp-sf-list" id="pp-sf-list"></div>
                                <div class="pp-sf-add">
                                    <input id="pp-sf-name" type="text" maxlength="40" autocomplete="off"
                                           placeholder="{{ $lang['sf_name_ph'] }}" aria-label="{{ $lang['sf_name_ph'] }}">
                                    <button type="button" class="bl-btn-main" id="pp-sf-save">{{ $lang['sf_add'] }}</button>
                                </div>
                                <label class="pp-sf-def"><input type="checkbox" id="pp-f-save"> {{ $lang['f_save'] }}</label>
                            </div>
                        </span>
                        <a class="sv" href="javascript:void(0);" id="pp-f-clear" hidden><i class="fa fa-times-circle-o"></i> {{ $lang['f_clear'] }}</a>
                        {{-- Button map: the list's own actions in one ⋯ menu (the downloads,
                             choosing every issue on screen for a bulk change). The old
                             download link stays in the page for its handler, out of sight. --}}
                        <a class="sv" href="javascript:void(0);" id="pp-f-csv" title="{{ $lang['f_csv_title'] }}" hidden><i class="fa fa-download"></i> {{ $lang['f_csv'] }}</a>
                        <a class="sv pp-l-more" href="javascript:void(0);" id="pp-l-more" role="button" aria-haspopup="true" title="{{ $lang['l_more_tip'] }}"><i class="fa fa-ellipsis-h"></i> {{ $lang['l_more'] }}</a>
                    </span>
                </div>
                {{-- ISS02939: a filter for each custom attribute of the project;
                     drawn by the script, only while there are some. --}}
                <div class="bl-filters pp-f-at" id="pp-f-at" hidden></div>
            </div>

            {{-- HINT-1: above the list, under the filters, where it is seen --}}
            <div class="pp-ed-hint pp-hint" id="pp-hint-list" hidden></div>
            {{-- Appears with the first tick; every control in it starts on
                 "leave as is", so an accidental apply changes nothing. --}}
            <div class="bl-bulk" id="pp-bulk" hidden>
                <span class="n" id="pp-bulk-n"></span>
                <select id="pp-bk-st"></select>
                <select id="pp-bk-as"></select>
                <select id="pp-bk-ty"></select>
                <select id="pp-bk-pr"></select>
                <select id="pp-bk-ms"></select>
                {{-- ISS02939: one custom attribute and its value --}}
                <select id="pp-bk-at" hidden></select>
                <span id="pp-bk-atv" hidden></span>
                <button type="button" class="bl-btn-main" id="pp-bk-apply">{{ $lang['bk_apply'] }}</button>
                <button type="button" class="bl-btn-sub" id="pp-bk-cancel">{{ $lang['bk_clear'] }}</button>
            </div>
            {{-- The rows a bulk edit could not save, each with the reason.
                 Filled by the script; stays until closed, because a toast is
                 gone before a list of keys can be read. --}}
            <div class="bl-bulk-failed" id="pp-bk-failed" hidden></div>
            <div class="bl-tablewrap">
                <table class="bl-list">
                    <thead><tr id="pp-issue-head">
                        <th class="chk" id="pp-chk-cell"><input type="checkbox" id="pp-chk-all" aria-label="{{ $lang['bk_selected'] }}"></th>
                        <th style="width:26px;"></th>
                        <th data-sort="ty">{{ $lang['col_ty'] }}</th>
                        <th data-sort="key">{{ $lang['col_key'] }}</th>
                        <th data-sort="al" id="pp-al-head" hidden>{{ $lang['col_alias'] }}</th>
                        <th class="pp-tcol" id="pp-tcol-head" hidden></th>
                        <th data-sort="subject">{{ $lang['col_subject'] }}</th>
                        <th data-sort="as">{{ $lang['col_as'] }}</th>
                        <th data-sort="sa" data-ocol="sa" hidden>{{ $lang['lbl_sub_assignees'] }}</th>
                        <th data-sort="mg" data-ocol="mg" hidden>{{ $lang['lbl_managers'] }}</th>
                        <th data-sort="rv" data-ocol="rv" hidden>{{ $lang['lbl_reviewers'] }}</th>
                        <th data-sort="df" data-ocol="df" hidden>{{ $lang['lbl_difficulty'] }}</th>
                        <th data-sort="st">{{ $lang['col_st'] }}</th>
                        <th data-sort="pr">{{ $lang['col_pr'] }}</th>
                        <th data-sort="ms">{{ $lang['col_ms'] }}</th>
                        <th data-sort="cat">{{ $lang['col_cat'] }}</th>
                        <th data-sort="sd">{{ $lang['col_sd'] }}</th>
                        <th data-sort="dd">{{ $lang['col_dd'] }}</th>
                        <th data-sort="pg">{{ $lang['col_pg'] }}</th>
                        <th data-sort="up">{{ $lang['col_up'] }}</th>
                    </tr></thead>
                    <tbody id="pp-issue-rows"></tbody>
                </table>
                {{-- S11: said once, to whoever may change the cells. --}}
                <div class="pp-ed-hint" id="pp-ed-hint" hidden><i class="fa fa-pencil"></i> {{ $lang['ed_hint'] }} <button type="button" class="x" aria-label="{{ $lang['m_close'] }}">&times;</button></div>
                <div class="bl-pager">
                    <span class="rg" id="pp-issue-count"></span>
                    <span class="pp-ar-note" id="pp-ar-note"></span>
                    <span class="nav" id="pp-pg-nav"></span>
                    <span class="sz">
                        <label for="pp-pg-size">{{ $lang['pg_size'] }}</label>
                        <select id="pp-pg-size">
                            <option value="20">20</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                            <option value="0">{{ $lang['pg_all'] }}</option>
                        </select>
                    </span>
                </div>
            </div>
        </section>

        {{-- ============ gantt (the real gantt view, embedded) ============ --}}
        <section class="bl-screen" id="pp-gantt">
            {{-- S10: a phone shows the gantt's list only; say that it moves
                 sideways, and offer the same issues as a list by due date. --}}
            <div class="pp-gantt-hint"><i class="fa fa-info-circle"></i> <span>{{ $lang['gantt_hint'] }}</span>
                <a href="#issues?st=%21done&amp;so=dd&amp;sd=asc">{{ $lang['gantt_list'] }} <i class="fa fa-angle-right"></i></a></div>
            @if(!empty($lazy_widgets))
                @if($gantt_available)
                <div class="pp-embed" id="pp-embed-gantt" data-embed="gantt" data-embed-url="{{ $payload['urls']['embed_gantt'] }}">
                    <div class="pp-embed-loading bl-card-box"><div class="bl-boxbody"><i class="fa fa-spinner fa-spin"></i> {{ $lang['m_loading'] }}</div></div>
                </div>
                @else
                <div class="bl-card-box"><div class="bl-boxbody"><i class="fa fa-info-circle"></i> {{ $lang['gantt_none'] }}</div></div>
                @endif
            @else
                @if($gantt_html)
                {!! $gantt_html !!}
                @else
                <div class="bl-card-box"><div class="bl-boxbody"><i class="fa fa-info-circle"></i> {{ $lang['gantt_none'] }}</div></div>
                @endif
            @endif
        </section>

        {{-- ============ burndown ============ --}}
        <section class="bl-screen" id="pp-burndown">
            <div class="bl-card-box">
                <div class="bl-boxhead" style="display:flex;align-items:center;gap:10px;">
                    <span><i class="fa fa-line-chart"></i>{{ $lang['bd_box'] }}</span>
                    <select id="pp-bd-select" style="border:1px solid #dfe5ea;border-radius:4px;padding:4px 8px;font-size:12px;font-weight:400;"></select>
                </div>
                <div class="bl-boxbody bl-burn" id="pp-bd-body">
                    <div style="overflow-x:auto;"><svg id="pp-bd-svg" width="760" height="320" role="img"></svg></div>
                    <div class="bl-burnside" id="pp-bd-side"></div>
                </div>
            </div>
        </section>

        {{-- ============ issue detail ============ --}}
        <section class="bl-screen" id="pp-issue">
            {{-- ISS00336: one line that stays at the top once the title has
                 scrolled away - which issue this is, and the way back. --}}
            <div class="pp-dbar" id="pp-dbar" hidden></div>
            <div class="bl-detail">
                <div class="bl-card-box"><div class="bl-boxbody" id="pp-d-main"><i class="fa fa-spinner fa-spin"></i></div></div>
                {{-- The boxes about the issue rather than its text. On a wide
                     screen they stand beside the description (see .pp-two), so
                     the comments follow the description directly. --}}
                <div class="bl-dside" id="pp-d-side">
                    <div class="bl-card-box" id="pp-d-propbox">
                        <div class="bl-boxhead" data-collapse="1"><i class="fa fa-info-circle"></i>{{ $lang['box_props'] }}<span class="bl-childsum" id="pp-d-propsum"></span><i class="fa fa-chevron-down bl-caret"></i></div>
                        <div class="bl-boxbody bl-rows bl-props" id="pp-d-props"></div>
                    </div>
                    <div class="bl-card-box" id="pp-d-childbox" style="display:none;">
                        <div class="bl-boxhead" data-collapse="1"><i class="fa fa-sitemap"></i>{{ $lang['box_children'] }}<span class="bl-childsum" id="pp-d-childsum"></span><i class="fa fa-chevron-down bl-caret"></i></div>
                        <div class="bl-boxbody bl-rows bl-children" id="pp-d-children"></div>
                    </div>
                    <div class="bl-card-box" id="pp-d-filebox" style="display:none;">
                        <div class="bl-boxhead" data-collapse="1"><i class="fa fa-paperclip"></i>{{ $lang['box_files'] }}<span class="bl-childsum" id="pp-d-filesum"></span><i class="fa fa-chevron-down bl-caret"></i></div>
                        <div class="bl-boxbody bl-rows bl-dfiles" id="pp-d-files"></div>
                    </div>
                    <div class="bl-card-box" id="pp-d-relbox" style="display:none;">
                        <div class="bl-boxhead" data-collapse="1"><i class="fa fa-link"></i>{{ $lang['box_related'] }}<span class="bl-childsum" id="pp-d-relsum"></span><i class="fa fa-chevron-down bl-caret"></i></div>
                        <div class="bl-boxbody bl-rows bl-related" id="pp-d-related"></div>
                    </div>
                    {{-- ISS02939: the records of other tables the issue links to (issue-record-links.js) --}}
                    <div class="bl-card-box" id="pp-d-reclinkbox" style="display:none;">
                        <div class="bl-boxhead" data-collapse="1"><i class="fa fa-database"></i>{{ $lang['links']['section'] ?? '' }}<span class="bl-childsum" id="pp-d-reclinksum"></span><i class="fa fa-chevron-down bl-caret"></i></div>
                        <div class="bl-boxbody bl-rows bl-related" id="pp-d-reclinks"></div>
                    </div>
                </div>
                <div class="bl-card-box" id="pp-d-tlbox">
                    <div class="bl-boxhead" style="display:flex;align-items:center;gap:10px;">
                        <span><i class="fa fa-comments-o"></i>{{ $lang['box_timeline'] }}<span class="bl-childsum" id="pp-d-tlsum"></span></span>
                        <button type="button" class="bl-tlexpand" id="pp-tl-expand" style="margin-left:auto;" hidden>{{ $lang['tl_expand'] }}</button>
                        <span class="bl-tlfilter" id="pp-tl-filter">
                            <button type="button" data-tl="all">{{ $lang['tl_all'] }}</button><button type="button" data-tl="cmt">{{ $lang['tl_cmt'] }}</button>
                        </span>
                    </div>
                    <div class="bl-boxbody">
                        <div class="bl-tl" id="pp-d-timeline"></div>
                    </div>
                </div>
            </div>

            {{-- Backlog's pinned composer: a thin row that stays on screen while
                 the issue is read, and unfolds into the comment box together
                 with the fields that are changed alongside a comment. --}}
            <div class="bl-cmtdock" id="pp-cmtdock" hidden>
                <button type="button" class="bl-dockbar" id="pp-dockbar" aria-expanded="false" aria-controls="pp-docksheet">
                    <span class="bl-dockav" id="pp-dockav"></span>
                    <span class="t">{{ $lang['dock_open'] }}</span>
                    <span class="sp"></span>
                    <span class="bl-dockstate" id="pp-dockstate"></span>
                    <i class="fa fa-chevron-up c"></i>
                </button>
                <div class="bl-docksheet" id="pp-docksheet" hidden>
                    <div class="bl-dockhead">
                        <span><i class="fa fa-pencil-square-o"></i>{{ $lang['dock_title'] }}</span>
                        <button type="button" class="bl-dockclose" id="pp-dockclose" title="{{ $lang['dock_close'] }}" aria-label="{{ $lang['dock_close'] }}"><i class="fa fa-times"></i></button>
                    </div>
                    <div class="bl-dockbody">
                        <div class="bl-dockmain">
                        <div class="bl-card-box">
                            <div class="bl-boxhead"><i class="fa fa-comment-o"></i>{{ $lang['dock_cmt'] }}</div>
                            <div class="bl-boxbody">
                                <div class="bl-cmtform" id="pp-cmtform">
                                    <div class="bl-cmttools" id="pp-cmt-tools">
                                        <button type="button" data-ct="attach" title="{{ $lang['ct_attach'] }}"><i class="fa fa-paperclip"></i></button>
                                        <button type="button" data-ct="emoji" title="{{ $lang['ct_emoji'] }}"><i class="fa fa-smile-o"></i></button>
                                        <span class="sep"></span>
                                        <button type="button" data-ct="b" title="{{ $lang['ct_bold'] }}"><b>B</b></button>
                                        <button type="button" data-ct="i" title="{{ $lang['ct_italic'] }}"><i class="fa fa-italic"></i></button>
                                        <button type="button" data-ct="s" title="{{ $lang['ct_strike'] }}"><i class="fa fa-strikethrough"></i></button>
                                        <button type="button" data-ct="ul" title="{{ $lang['ct_ul'] }}"><i class="fa fa-list-ul"></i></button>
                                        <button type="button" data-ct="ol" title="{{ $lang['ct_ol'] }}"><i class="fa fa-list-ol"></i></button>
                                        <button type="button" data-ct="q" title="{{ $lang['ct_quote'] }}"><i class="fa fa-quote-right"></i></button>
                                        <button type="button" data-ct="code" title="{{ $lang['ct_code'] }}"><i class="fa fa-code"></i></button>
                                        <button type="button" data-ct="link" title="{{ $lang['ct_link'] }}"><i class="fa fa-link"></i></button>
                                        <span class="sp"></span>
                                        {{-- MD-1: 書く｜プレビュー as a pair --}}
                                        <span class="pp-md-tabs"><button type="button" class="on" id="pp-cmt-writebtn">{{ $lang['md_write'] }}</button><button type="button" data-ct="preview" id="pp-cmt-prevbtn">{{ $lang['ct_preview'] }}</button></span>
                                    </div>
                                    <div class="bl-emoji" id="pp-cmt-emoji" hidden></div>
                                    {{-- No data-mention here: the portal runs its own type-ahead
                                         (@ people, # issues, ~ files) and two dropdowns racing
                                         for the same caret is worse than either alone. --}}
                                    <textarea id="pp-cmt-text" placeholder="{{ $lang['cmt_ph'] }}"></textarea>
                                    <div class="bl-cmtprev bl-tl" id="pp-cmt-prev" hidden></div>
                                    <div class="bl-cmthint">{{ $lang['cmt_hint'] }}</div>
                                    <input type="file" id="pp-cmt-file" multiple hidden>
                                </div>
                                {{-- S17: the status is changed with the comment, so it stays here, open. --}}
                                <div class="bl-qe pp-cmt-st" id="pp-cmt-st"></div>
                            </div>
                        </div>
                        {{-- Who hears about it belongs with what is being said,
                             not with the fields being changed. One value, so no
                             label column: the card head already names it. --}}
                        <div class="bl-card-box">
                            <div class="bl-boxhead"><i class="fa fa-bell-o"></i>{{ $lang['notify_to'] }}<span class="bl-qe-n" id="pp-notify-n" hidden></span>
                                {{-- ISS00341: the people picked below are also asked to
                                     confirm the comment. A tick on the card that already
                                     picks them, rather than a second list of the same people:
                                     the dock must not grow taller. --}}
                                <label class="pp-notify-mail pp-notify-rv" title="{{ $lang['rv_ask_hint'] }}"><input type="checkbox" id="pp-notify-rv"> <i class="fa fa-check-square-o"></i> {{ $lang['rv_ask'] }}</label>
                                <label class="pp-notify-mail" title="{{ $lang['notify_mail_hint'] }}"><input type="checkbox" id="pp-notify-mail"> <i class="fa fa-envelope-o"></i> {{ $lang['notify_mail'] }}</label>
                                {{-- ISS02931: Teams for everyone of this one notice, where Exment can send it --}}
                                @if (!empty($payload['notify_teams']))
                                <label class="pp-notify-mail pp-notify-teams" title="{{ $lang['nt_teams_hint'] }}"><input type="checkbox" id="pp-notify-teams"> <i class="fa fa-commenting-o"></i> {{ $lang['nt_teams'] }}</label>
                                @endif
                            </div>
                            <div class="bl-boxbody">
                                <div id="pp-notify-chips"></div>
                                <div class="pp-notify-route" id="pp-notify-route" hidden></div>
                            </div>
                        </div>
                        </div>
                        <div class="bl-dockside">
                            {{-- S17: closed at first, so the dock reads as a comment box
                                 and not as a second edit form. --}}
                            <div class="bl-card-box" id="pp-d-editbox">
                                <div class="bl-boxhead pp-qe-toggle" role="button" tabindex="0" aria-expanded="false" aria-controls="pp-cmt-fields"><i class="fa fa-sliders"></i>{{ $lang['dock_edit'] }}<span class="bl-qe-n" id="pp-qe-n" hidden></span><i class="fa fa-chevron-right bl-caret"></i></div>
                                <div class="bl-boxbody bl-qe" id="pp-cmt-fields"></div>
                            </div>
                        </div>
                    </div>
                    <div class="bl-dockfoot">
                        <span class="bl-dockmsg" id="pp-dockmsg"></span>
                        <button type="button" class="bl-btn-sub" id="pp-dockcancel">{{ $lang['dock_cancel'] }}</button>
                        <button type="button" class="bl-btn-main" id="pp-cmt-submit">{{ $lang['submit'] }}</button>
                    </div>
                </div>
            </div>
        </section>

        {{-- ============ wiki ============ --}}
        <section class="bl-screen" id="pp-wiki">
            <div class="bl-wiki">
                <div class="bl-card-box">
                    <div class="bl-boxhead"><i class="fa fa-book"></i>{{ $lang['wiki_pages'] }}
                        @if(!empty($payload['wiki']['create_url']))
                        <a class="pp-mlink" href="javascript:void(0);" data-url="{{ $payload['wiki']['create_url'] }}" data-mtitle="{{ $lang['m_new_wiki'] }}" style="float:right;color:var(--bl-accent);font-size:12px;"><i class="fa fa-plus"></i> {{ $lang['wiki_add'] }}</a>
                        @endif
                        </div>
                    <div class="bl-boxbody bl-wtree" id="pp-wtree"></div>
                </div>
                <div class="bl-card-box">
                    <div class="bl-boxhead" id="pp-whead" style="display:flex;align-items:center;"></div>
                    <div class="bl-boxbody bl-wart bl-md" id="pp-wbody"></div>
                </div>
            </div>
        </section>

        {{-- ============ files ============ --}}
        <section class="bl-screen" id="pp-files">
            {{-- The workspace of the file server, when the project has one and the feature is
                 on (ISS02825): its own pages, framed, the admin chrome hidden. Filled by the script. --}}
            <div class="bl-card-box pp-fs" id="pp-fs" hidden>
                <div class="bl-boxhead" style="display:flex;align-items:center;gap:8px;">
                    <i class="fa fa-folder-open"></i><span id="pp-fs-name"></span>
                    <a id="pp-fs-open" class="bl-btn-sub" style="margin-left:auto;" target="_blank" rel="noopener"></a>
                </div>
                <div class="bl-boxbody" id="pp-fs-body" style="padding:0;"></div>
            </div>
            <div class="bl-card-box bl-files">
                <div class="bl-boxhead"><i class="fa fa-paperclip"></i><span id="pp-files-title">{{ $lang['tab_files'] }}</span></div>
                <div class="bl-boxbody">
                    <div class="bl-drop" id="pp-drop">
                        <i class="fa fa-cloud-upload"></i> {{ $lang['files_drop'] }}
                        <label class="bl-btn-sub" style="margin-left:10px;cursor:pointer;">
                            {{ $lang['files_pick'] }}<input type="file" id="pp-file-input" multiple style="display:none;">
                        </label>
                    </div>
                    {{-- Shown by the script when the list was cut at the
                         server's ceiling, so it does not read as everything. --}}
                    <div class="bl-files-partial" id="pp-files-partial" hidden>
                        <i class="fa fa-info-circle"></i> {{ $lang['files_partial'] }}
                    </div>
                    <table>
                        <thead><tr><th>{{ $lang['files_name'] }}</th><th style="width:160px;">{{ $lang['files_src'] }}</th>
                            <th style="width:140px;">{{ $lang['files_by'] }}</th><th style="width:130px;">{{ $lang['files_at'] }}</th></tr></thead>
                        <tbody id="pp-file-rows"></tbody>
                    </table>
                </div>
            </div>
        </section>

        @if($canManage)
        @include(\App\Plugins\ExmentPM\PortalDefinition::view('_settings'))
        @endif

    </div>

    {{-- in-portal form modal: the real admin form, framed, chrome hidden --}}
    <div class="pp-fmodal" id="pp-fmodal" hidden>
        <div class="pp-fm-back"></div>
        <div class="pp-fm-panel">
            <div class="pp-fm-head">
                <span class="t"></span><span class="s"></span>
                <span class="pp-fm-style-wrap">
                    <button type="button" class="pp-fm-style-btn" title="{{ $lang['form_style'] }}"><i class="fa fa-paint-brush"></i><span class="pp-fm-style-label"></span></button>
                    <div class="pp-fm-style-menu" hidden></div>
                </span>
                <span class="pp-fm-x" title="{{ $lang['m_close'] }}"><i class="fa fa-times"></i></span>
            </div>
            <div class="pp-fm-confirm" hidden>
                <i class="fa fa-exclamation-triangle"></i> {{ $lang['m_discard'] }}
                <button type="button" class="yes">{{ $lang['m_discard_yes'] }}</button>
                <button type="button" class="no">{{ $lang['m_discard_no'] }}</button>
            </div>
            <div class="pp-fm-body">
                <iframe src="about:blank" title="{{ $lang['m_loading'] }}"></iframe>
                <iframe class="pp-fm-warm" src="about:blank" tabindex="-1" aria-hidden="true"></iframe>
                <div class="pp-fm-spin"><i class="fa fa-spinner fa-spin"></i>&nbsp;{{ $lang['m_loading'] }}</div>
            </div>
        </div>
    </div>

    {{-- Native edit modal: renders form fields from JSON so a click on --}}
    {{-- an issue's / wiki's / project's "Edit" button never leaves the portal shell. --}}
    <div class="pp-nedit" id="pp-nedit" hidden>
        <div class="pp-ne-back"></div>
        <div class="pp-ne-panel">
            <div class="pp-ne-pbar" hidden></div>
            <div class="pp-ne-head">
                <div class="pp-ne-head-top">
                    <span class="pp-ne-key" role="button" tabindex="0" title="{{ $lang['ned_key_copy'] }}"></span>
                    <span class="pp-ne-t"></span>
                    <div class="pp-ne-head-actions">
                        <label class="pp-ne-mail" hidden title="{{ $lang['notify_mail_hint'] }}"><input type="checkbox"> <i class="fa fa-envelope-o"></i> {{ $lang['notify_mail'] }}</label>
                        <button type="button" class="pp-ne-cancel bl-btn-sub">{{ $lang['ned_back'] }}</button>
                        <button type="button" class="pp-ne-save bl-btn-main"><i class="fa fa-check"></i>&nbsp;<span class="pp-ne-save-label">{{ $lang['ned_save'] }}</span></button>
                        <span class="pp-ne-x" title="{{ $lang['ned_close'] }}"><i class="fa fa-times"></i></span>
                    </div>
                </div>
                <div class="pp-ne-meta" hidden>
                    <span class="pp-ne-meta-created" hidden></span>
                    <span class="pp-ne-meta-sep" hidden>·</span>
                    <span class="pp-ne-meta-updated" hidden></span>
                </div>
            </div>
            <div class="pp-ne-alert" hidden></div>
            {{-- offered when localStorage still holds edits that never reached the server --}}
            <div class="pp-ne-draft" hidden>
                <i class="fa fa-history"></i>
                <span class="pp-ne-draft-msg">{{ $lang['draft_found'] }}</span>
                <button type="button" class="pp-ne-draft-yes bl-btn-main">{{ $lang['draft_restore'] }}</button>
                <button type="button" class="pp-ne-draft-no bl-btn-sub">{{ $lang['draft_discard'] }}</button>
            </div>
            <div class="pp-ne-body">
                <div class="pp-ne-body-inner">
                    <div class="pp-ne-spin">
                        <div class="pp-ne-skel">
                            <div class="pp-ne-skl pp-ne-skl-hero"></div>
                            <div class="pp-ne-skl pp-ne-skl-main"></div>
                            <div class="pp-ne-skl pp-ne-skl-side"></div>
                            <div class="pp-ne-skl pp-ne-skl-side"></div>
                            <div class="pp-ne-skl pp-ne-skl-side"></div>
                            <div class="pp-ne-skl pp-ne-skl-side"></div>
                        </div>
                    </div>
                    <form class="pp-ne-form" autocomplete="off" hidden>
                        <div class="pp-ne-top-row" hidden></div>
                        <div class="pp-ne-title-row"></div>
                        <div class="pp-ne-desc-row" hidden></div>
                        {{-- The metadata fields are not a grid of their own any
                             more: they are the 基本情報 card, so editing changes
                             what a row holds without moving the row. --}}
                        <div class="bl-card-box pp-ne-propbox">
                            <div class="bl-boxhead"><i class="fa fa-sliders"></i>{{ $lang['box_props'] }}</div>
                            <div class="bl-boxbody bl-rows bl-props pp-ne-props"></div>
                        </div>
                        <div class="pp-ne-files-row" hidden></div>
                    </form>
                </div>
            </div>
            <div class="pp-ne-foot" hidden>
                <div class="pp-ne-hint">
                    <kbd class="pp-ne-modkey" data-mac="{{ $lang['ned_key_mod_mac'] }}">{{ $lang['ned_key_mod'] }}</kbd><span class="plus">+</span><kbd>↵</kbd>&nbsp;{{ $lang['ned_shortcut_save'] }}
                    <span class="sep">·</span>
                    <kbd>ESC</kbd>&nbsp;{{ $lang['ned_shortcut_close'] }}
                </div>
                <div class="pp-ne-btns">
                    <button type="button" class="pp-ne-cancel bl-btn-sub">{{ $lang['ned_back'] }}</button>
                    <button type="button" class="pp-ne-save bl-btn-main"><i class="fa fa-check"></i>&nbsp;<span class="pp-ne-save-label">{{ $lang['ned_save'] }}</span></button>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Japanese as itself (not \uXXXX, which doubled its size) and / unescaped;
     the HEX flags still keep </script>, & and quotes from closing the tag. --}}
<script type="application/json" id="pmportal-data">@json($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)</script>
<script type="application/json" id="pmportal-lang">@json($lang)</script>
{{-- The script and the stylesheet are files of their own (PortalDefinition::
     ASSETS): the browser keeps them across pages and versions them by content,
     instead of reading ~600 KB of inline code on every page. --}}
<link rel="stylesheet" href="{{ \App\Plugins\ExmentPM\PortalDefinition::assetUrl('style') }}">
<script src="{{ \App\Plugins\ExmentPM\PortalDefinition::assetUrl('script') }}"></script>
{{-- pjax keeps an already loaded script file from running again: start it here. --}}
<script>window.PmPortalBoot && window.PmPortalBoot();</script>
