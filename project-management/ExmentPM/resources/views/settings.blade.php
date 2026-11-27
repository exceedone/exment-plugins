@php
use App\Plugins\ExmentPM\PortalDefinition as PD;
@endphp

{{-- The space-wide settings: the lists every project shares, the schema
     upgrade, and data migration to or from another Exment. What belongs to one project is on that project's 設定 tab
     (_settings.blade.php) and is deliberately not repeated here.

     The page sits in Exment's own frame, outside .pmportal, so it names the
     portal's colours itself - the same --bl-* variables, with the same
     values - and every rule below reads them rather than a literal. --}}
<div id="pp-settings">
    <style>
        #pp-settings {
            --bl-accent: var(--exm-ui-accent,#4caf93); --bl-accent-d: var(--exm-ui-accent-strong,#3d9a80); --bl-ink: #24323f; --bl-sub: #6b7a88;
            --bl-line: #dfe5ea; --bl-faint: #8b99a5; --bl-box: #ffffff; --bl-box2: #f5f7f9;
            --bl-chip: #eef3f6; --bl-hover: var(--exm-ui-accent-soft,#f2f8f5); --bl-red: #d9534f; --bl-amber: #eda62a;
            color: var(--bl-ink); font-size: 13px; max-width: 1280px;
        }
        #pp-settings *, #pp-settings *::before, #pp-settings *::after { box-sizing: border-box; }

        /* where this page stands, and where the rest of the settings live */
        #pp-settings .pp-cfg-guide { display: flex; align-items: flex-start; gap: 12px; background: var(--bl-box);
            border: 1px solid var(--bl-line); border-left: 3px solid var(--bl-accent); border-radius: 6px;
            padding: 12px 16px; margin-bottom: 22px; line-height: 1.7; }
        #pp-settings .pp-cfg-guide > .fa { color: var(--bl-accent-d); font-size: 16px; margin-top: 3px; flex: none; }
        #pp-settings .pp-cfg-guide .tx { flex: 1 1 auto; min-width: 0; }
        #pp-settings .pp-cfg-guide .tx b { display: block; font-size: 13.5px; }
        #pp-settings .pp-cfg-guide .tx span { color: var(--bl-sub); font-size: 12.5px; }
        #pp-settings .pp-cfg-guide .go { flex: none; align-self: center; }

        #pp-settings .pp-cfg-group { margin: 0 0 24px; }
        #pp-settings .pp-cfg-gh { display: flex; align-items: baseline; flex-wrap: wrap; gap: 4px 10px; margin: 0 0 10px; }
        #pp-settings .pp-cfg-gh h2 { font-size: 15px; line-height: 1.5; font-weight: 700; margin: 0; padding: 0; color: var(--bl-ink); }
        #pp-settings .pp-cfg-gh p { margin: 0; color: var(--bl-sub); font-size: 12px; }
        #pp-settings .pp-cfg-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; align-items: start; }
        @media (max-width: 1100px) { #pp-settings .pp-cfg-grid { grid-template-columns: minmax(0, 1fr); } }

        /* one card per setting - the pane of the project 設定 tab */
        #pp-settings .pp-cfg-card { background: var(--bl-box); border: 1px solid var(--bl-line); border-radius: 6px; min-width: 0; }
        #pp-settings .pp-cfg-head { display: flex; align-items: center; gap: 8px; padding: 11px 16px;
            border-bottom: 1px solid var(--bl-line); background: var(--bl-box2); border-radius: 6px 6px 0 0; }
        #pp-settings .pp-cfg-head .fa { color: var(--bl-sub); width: 14px; text-align: center; }
        #pp-settings .pp-cfg-head h3 { font-size: 13.5px !important; line-height: 1.5; font-weight: 700; margin: 0; padding: 0; color: var(--bl-ink); }
        #pp-settings .pp-cfg-head .sp { flex: 1 1 auto; }
        #pp-settings .pp-cfg-count { color: var(--bl-sub); font-size: 11.5px; background: var(--bl-chip);
            border-radius: 20px; padding: 1px 9px; }
        #pp-settings .pp-cfg-count:empty { display: none; }
        #pp-settings .pp-cfg-body { padding: 14px 16px 16px; }
        #pp-settings .pp-cfg-hint { color: var(--bl-sub); font-size: 12px; line-height: 1.7; margin: 0 0 12px; }
        #pp-settings .pp-cfg-info { background: var(--bl-chip); border: 1px solid var(--bl-line); border-radius: 4px;
            padding: 8px 12px; font-size: 12px; color: var(--bl-sub); margin: 0 0 12px; }

        /* the rows of a list */
        #pp-settings .pp-cfg-tbl { width: 100%; border-collapse: collapse; }
        #pp-settings .pp-cfg-tbl th { text-align: left; font-weight: 700; color: var(--bl-sub); font-size: 11.5px;
            padding: 0 6px 6px; border-bottom: 1px solid var(--bl-line); white-space: nowrap; }
        #pp-settings .pp-cfg-tbl td { padding: 7px 6px; border-bottom: 1px solid var(--bl-line); vertical-align: middle; }
        #pp-settings .pp-cfg-tbl th:first-child, #pp-settings .pp-cfg-tbl td:first-child { padding-left: 0; }
        #pp-settings .pp-cfg-tbl th:last-child, #pp-settings .pp-cfg-tbl td:last-child { padding-right: 0; }
        #pp-settings .pp-cfg-tbl .c-color { width: 44px; }
        #pp-settings .pp-cfg-tbl .c-number { width: 84px; }
        #pp-settings .pp-cfg-tbl .c-act { width: 1%; white-space: nowrap; text-align: right; }
        #pp-settings .pp-cfg-tbl td.none { color: var(--bl-faint); font-size: 12px; padding: 14px 0; }
        #pp-settings .pp-cfg-tbl tfoot td { border-bottom: 0; padding-top: 12px; }
        #pp-settings .pp-cfg-tbl tr.dirty td:first-child { box-shadow: inset 3px 0 0 var(--bl-amber); padding-left: 8px; }

        #pp-settings .pp-cfg-in { width: 100%; border: 1px solid var(--bl-line); border-radius: 4px; padding: 6px 9px;
            font-size: 13px; line-height: 1.4; background: var(--bl-box); color: var(--bl-ink); }
        #pp-settings .pp-cfg-in:focus { outline: none; border-color: var(--bl-accent); box-shadow: 0 0 0 2px rgba(76, 175, 147, .18); }
        #pp-settings .pp-cfg-in:disabled { background: var(--bl-box2); color: var(--bl-sub); }
        #pp-settings input[type=color].pp-cfg-in { width: 32px; height: 30px; padding: 1px; cursor: pointer; display: block; }
        #pp-settings input[type=number].pp-cfg-in { text-align: right; }

        #pp-settings .pp-cfg-btn { display: inline-flex; align-items: center; gap: 6px; border: 1px solid var(--bl-line);
            background: var(--bl-box); color: var(--bl-ink); border-radius: 4px; padding: 6px 13px; font-size: 12.5px;
            line-height: 1.4; cursor: pointer; white-space: nowrap; text-decoration: none; }
        #pp-settings .pp-cfg-btn:hover { background: var(--bl-hover); color: var(--bl-ink); text-decoration: none; }
        #pp-settings .pp-cfg-btn.main { background: var(--bl-accent); border-color: var(--bl-accent); color: #fff; font-weight: 700; }
        #pp-settings .pp-cfg-btn.main:hover { background: var(--bl-accent-d); border-color: var(--bl-accent-d); color: #fff; }
        #pp-settings .pp-cfg-btn:disabled { opacity: .45; cursor: default; background: var(--bl-box); }
        #pp-settings .pp-cfg-btn:focus-visible, #pp-settings .pp-cfg-del:focus-visible {
            outline: 2px solid var(--bl-accent); outline-offset: 1px; }
        #pp-settings .pp-cfg-del { border: 0; background: none; color: var(--bl-faint); cursor: pointer; font-size: 14px;
            padding: 5px 8px; margin-left: 4px; border-radius: 4px; }
        #pp-settings .pp-cfg-del:hover { color: var(--bl-red); background: var(--bl-box2); }
        #pp-settings .pp-cfg-del:disabled { opacity: .5; cursor: default; }

        /* schema upgrade */
        #pp-settings .pp-cfg-state { display: flex; align-items: flex-start; gap: 10px; line-height: 1.7; }
        #pp-settings .pp-cfg-state > .fa { font-size: 17px; margin-top: 2px; flex: none; }
        #pp-settings .pp-cfg-state.ok > .fa { color: var(--bl-accent); }
        #pp-settings .pp-cfg-state.todo > .fa { color: var(--bl-amber); }
        #pp-settings .pp-cfg-state b { display: block; }
        #pp-settings .pp-cfg-state span { color: var(--bl-sub); font-size: 12px; }
        #pp-settings .pp-cfg-warn { background: var(--bl-chip); border: 1px solid var(--bl-line);
            border-left: 3px solid var(--bl-red); border-radius: 4px; padding: 9px 12px; font-size: 12.5px;
            line-height: 1.7; margin: 14px 0; }
        #pp-settings .pp-cfg-warn .fa { color: var(--bl-red); margin-right: 4px; }
        #pp-settings .pp-cfg-log { margin-top: 12px; font-size: 12.5px; line-height: 1.7; }
        #pp-settings .pp-cfg-log:empty { display: none; }
        #pp-settings .pp-cfg-log .res { border: 1px solid var(--bl-line); border-left: 3px solid var(--bl-accent);
            background: var(--bl-chip); border-radius: 4px; padding: 9px 12px; white-space: pre-line; }
        #pp-settings .pp-cfg-log .res.err { border-left-color: var(--bl-red); }
        #pp-settings .pp-cfg-log ul { margin: 10px 0; padding-left: 20px; color: var(--bl-sub); }

        /* data migration */
        #pp-settings .pp-mig-tbl .c-check { width: 28px; }
        #pp-settings .pp-mig-tbl label { font-weight: normal; margin: 0; cursor: pointer; }
        #pp-settings .pp-mig-foot { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 14px; }
        #pp-settings .pp-mig-field { margin: 0 0 12px; }
        #pp-settings .pp-mig-field > label { display: block; font-weight: 700; font-size: 12.5px; margin: 0 0 5px; }
        #pp-settings .pp-mig-field > label.pp-mig-opt { font-weight: normal; }
        #pp-settings .pp-mig-field .pp-cfg-hint { margin: 6px 0 0; }
        #pp-settings .pp-mig-map { font-family: monospace; resize: vertical; }
        #pp-settings .pp-mig-danger { border-color: var(--bl-red); color: var(--bl-red); }
        #pp-settings .pp-mig-danger:hover { background: var(--bl-red); color: #fff; }
        #pp-settings .pp-mig-report { margin-top: 14px; }
        #pp-settings .pp-mig-report h4 { font-size: 13px; font-weight: 700; margin: 16px 0 6px; }
        #pp-settings .pp-mig-line { border: 1px solid var(--bl-line); border-left: 3px solid var(--bl-accent);
            background: var(--bl-chip); border-radius: 4px; padding: 7px 12px; margin: 0 0 6px; font-size: 12.5px; line-height: 1.7; }
        #pp-settings .pp-mig-line.warn { border-left-color: var(--bl-amber); }
        #pp-settings .pp-mig-line.err { border-left-color: var(--bl-red); }
        #pp-settings .pp-mig-counts { max-width: 420px; }
        #pp-settings .pp-mig-bad { color: var(--bl-red); }

        @media (max-width: 640px) {
            #pp-settings .pp-cfg-guide { flex-wrap: wrap; }
            #pp-settings .pp-cfg-guide .go { margin-left: 28px; }
            #pp-settings .pp-cfg-body { padding: 12px; }
            #pp-settings .pp-cfg-btn .lb { display: none; }
        }
    </style>

    {{-- Exment's messages for this page (an expired form, a migration) and the 419 bar --}}
    @include(PD::view('_flash'))

    <div class="pp-cfg-guide">
        <i class="fa fa-info-circle"></i>
        <div class="tx">
            <b>{{ PD::trans('settings.guide_title') }}</b>
            <span>{{ PD::trans('settings.guide_body') }}</span>
        </div>
        <a class="pp-cfg-btn go" href="{{ $urls['portal'] }}">
            {{ PD::trans('settings.guide_link') }} <i class="fa fa-angle-right"></i>
        </a>
    </div>

    @if(count($sections) > 0)
    <div class="pp-cfg-group">
        <div class="pp-cfg-gh">
            <h2>{{ PD::trans('settings.group_lists') }}</h2>
            <p>{{ PD::trans('settings.group_lists_note') }}</p>
        </div>
        <div class="pp-cfg-grid">
            @foreach($sections as $section)
            <section class="pp-cfg-card" data-pp-panel="{{ $section['kind'] }}">
                <div class="pp-cfg-head">
                    <i class="fa {{ $section['icon'] }}"></i>
                    <h3>{{ $section['label'] }}</h3>
                    <span class="sp"></span>
                    <span class="pp-cfg-count" data-pp-count="{{ $section['kind'] }}"></span>
                </div>
                <div class="pp-cfg-body">
                    <p class="pp-cfg-hint">{{ $section['hint'] }}</p>

                    @if(!$section['editable'])
                    <div class="pp-cfg-info"><i class="fa fa-eye"></i> {{ PD::trans('settings.readonly') }}</div>
                    @endif

                    <table class="pp-cfg-tbl">
                        <thead>
                            <tr>
                                @foreach($section['fields'] as $field)
                                <th class="c-{{ $field['type'] }}">{{ $field['label'] }}</th>
                                @endforeach
                                <th class="c-act"></th>
                            </tr>
                        </thead>
                        <tbody data-pp-rows="{{ $section['kind'] }}"></tbody>
                        <tfoot data-pp-new="{{ $section['kind'] }}"></tfoot>
                    </table>
                </div>
            </section>
            @endforeach
        </div>
    </div>
    @endif

    <div class="pp-cfg-group">
        <div class="pp-cfg-gh">
            <h2>{{ PD::trans('settings.group_system') }}</h2>
        </div>
        <section class="pp-cfg-card" id="pp-upgrade-box">
            <div class="pp-cfg-head">
                <i class="fa fa-database"></i>
                <h3>{{ PD::trans('upgrade.title') }}</h3>
                <span class="sp"></span>
                <span class="pp-cfg-count">{{ PD::trans('upgrade.version_now', ['current' => $schema['current']]) }}</span>
            </div>
            <div class="pp-cfg-body">
                @if($schema['needed'])
                <div class="pp-cfg-state todo">
                    <i class="fa fa-exclamation-circle"></i>
                    <div>
                        <b>{{ PD::trans('upgrade.needed') }}</b>
                        <span>{{ PD::trans('upgrade.version', ['current' => $schema['current'], 'target' => $schema['target']]) }}</span>
                    </div>
                </div>
                <p class="pp-cfg-hint" style="margin-top:12px;">{{ PD::trans('upgrade.lead') }}</p>
                <div class="pp-cfg-warn">
                    <i class="fa fa-exclamation-triangle"></i>{{ PD::trans('upgrade.backup_note') }}
                </div>
                <button type="button" class="pp-cfg-btn main" id="pp-upgrade-run">
                    <i class="fa fa-play"></i> {{ PD::trans('upgrade.button') }}
                </button>
                <div class="pp-cfg-log" id="pp-upgrade-log"></div>
                @else
                <div class="pp-cfg-state ok">
                    <i class="fa fa-check-circle"></i>
                    <div>
                        <b>{{ PD::trans('upgrade.already') }}</b>
                        <span>{{ PD::trans('upgrade.idle_note') }}</span>
                    </div>
                </div>
                @endif
            </div>
        </section>
    </div>

    {{-- Data migration (Migrate/*, formerly the PmMigrate plugin). Plain
         forms posting to their own routes: the export answers with a ZIP the
         browser saves, the import with this page again, its report below.
         The whole page is admin-only, and both routes check again. --}}
    <div class="pp-cfg-group" id="pp-migrate">
        <div class="pp-cfg-gh">
            <h2>{{ PD::trans('migrate.group') }}</h2>
            <p>{{ PD::trans('migrate.group_note') }}</p>
        </div>

        @if($migrate['error'])
        <div class="pp-cfg-warn"><i class="fa fa-exclamation-triangle"></i>{{ $migrate['error'] }}</div>
        @endif

        <div class="pp-cfg-grid">
            <section class="pp-cfg-card">
                <div class="pp-cfg-head">
                    <i class="fa fa-download"></i>
                    <h3>{{ PD::trans('migrate.export_title') }}</h3>
                </div>
                <form class="pp-cfg-body" method="post" action="{{ $urls['migrate_export'] }}">
                    {{ csrf_field() }}
                    @if(empty($migrate['projects']))
                    <p class="pp-cfg-hint">{{ PD::trans('migrate.no_projects') }}</p>
                    @else
                    <p class="pp-cfg-hint">{{ PD::trans('migrate.export_hint') }}</p>
                    <table class="pp-cfg-tbl pp-mig-tbl">
                        <tbody>
                            @foreach($migrate['projects'] as $p)
                            <tr>
                                <td class="c-check"><input type="checkbox" name="projects[]" value="{{ $p['id'] }}" id="pp-mig-p{{ $p['id'] }}"></td>
                                <td><label for="pp-mig-p{{ $p['id'] }}">{{ $p['name'] }}</label></td>
                                <td><code>{{ $p['code'] }}</code></td>
                                <td class="c-act">@if($p['archived'])<span class="pp-cfg-count">{{ PD::trans('migrate.archived') }}</span>@endif</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @endif
                    <div class="pp-mig-foot">
                        <button type="submit" class="pp-cfg-btn main" @if(empty($migrate['projects'])) disabled @endif>
                            <i class="fa fa-file-archive-o"></i> {{ PD::trans('migrate.export_button') }}
                        </button>
                    </div>
                </form>
            </section>

            <section class="pp-cfg-card">
                <div class="pp-cfg-head">
                    <i class="fa fa-upload"></i>
                    <h3>{{ PD::trans('migrate.import_title') }}</h3>
                </div>
                <form class="pp-cfg-body" method="post" action="{{ $urls['migrate_import'] }}" enctype="multipart/form-data">
                    {{ csrf_field() }}
                    <div class="pp-mig-field">
                        <label for="pp-mig-bundle">{{ PD::trans('migrate.bundle') }}</label>
                        <input type="file" name="bundle" id="pp-mig-bundle" accept=".zip">
                    </div>
                    <div class="pp-mig-field">
                        <label for="pp-mig-map">{{ PD::trans('migrate.user_map') }}</label>
                        <textarea name="user_map" id="pp-mig-map" class="pp-cfg-in pp-mig-map" rows="5"
                            placeholder="{{ PD::trans('migrate.user_map_placeholder') }}">{{ $migrate['user_map'] }}</textarea>
                        <p class="pp-cfg-hint">{{ PD::trans('migrate.user_map_help') }}</p>
                    </div>
                    <div class="pp-mig-field">
                        <label>{{ PD::trans('migrate.master') }}</label>
                        <label class="pp-mig-opt"><input type="radio" name="master_mode" value="abort" @if($migrate['master_mode'] !== 'replace') checked @endif> {{ PD::trans('migrate.master_abort') }}</label>
                        <label class="pp-mig-opt"><input type="radio" name="master_mode" value="replace" @if($migrate['master_mode'] === 'replace') checked @endif> {{ PD::trans('migrate.master_replace') }}</label>
                    </div>
                    <div class="pp-mig-field">
                        <label class="pp-mig-opt"><input type="checkbox" name="confirm" value="1"> {{ PD::trans('migrate.confirm') }}</label>
                    </div>
                    <div class="pp-mig-foot">
                        <button type="submit" name="action" value="dry" class="pp-cfg-btn main">
                            <i class="fa fa-search"></i> {{ PD::trans('migrate.dry_button') }}
                        </button>
                        <button type="submit" name="action" value="execute" class="pp-cfg-btn pp-mig-danger">
                            <i class="fa fa-play"></i> {{ PD::trans('migrate.execute_button') }}
                        </button>
                    </div>
                </form>
            </section>
        </div>

        @if($migrate['report'])
        @php
            $report = $migrate['report'];
            $labels = (array) PD::trans('migrate.dataset');
            if ($report['executed']) {
                $title = PD::trans('migrate.result_executed');
            } elseif ($migrate['ran'] === 'execute') {
                $title = PD::trans('migrate.result_not_executed');
            } elseif ($report['ok']) {
                $title = PD::trans('migrate.result_ok');
            } else {
                $title = PD::trans('migrate.result_error');
            }
        @endphp
        <section class="pp-cfg-card pp-mig-report" id="pp-migrate-report">
            <div class="pp-cfg-head">
                <i class="fa {{ $report['ok'] ? 'fa-check-circle' : 'fa-exclamation-circle' }}"></i>
                <h3>{{ $title }}</h3>
            </div>
            <div class="pp-cfg-body">
                @foreach($report['errors'] as $line)
                <div class="pp-mig-line err">{{ $line }}</div>
                @endforeach
                @foreach($report['warnings'] as $line)
                <div class="pp-mig-line warn">{{ $line }}</div>
                @endforeach
                @foreach($report['info'] as $line)
                <div class="pp-mig-line">{{ $line }}</div>
                @endforeach

                @if(!empty($report['projects']))
                <h4>{{ PD::trans('migrate.projects') }}</h4>
                <ul>
                    @foreach($report['projects'] as $p)
                    <li>{{ $p['name'] }} <code>{{ $p['code'] }}</code></li>
                    @endforeach
                </ul>
                @endif

                @if(!empty($report['counts']))
                <h4>{{ $report['executed'] ? PD::trans('migrate.counts_done') : PD::trans('migrate.counts_plan') }}</h4>
                <table class="pp-cfg-tbl pp-mig-counts">
                    @foreach($report['counts'] as $name => $count)
                    <tr><td>{{ $labels[$name] ?? $name }}</td><td class="c-act">{{ number_format($count) }}</td></tr>
                    @endforeach
                </table>
                @endif

                @if(!empty($report['users']))
                <h4>{{ PD::trans('migrate.users') }}</h4>
                <table class="pp-cfg-tbl">
                    <thead><tr><th>{{ PD::trans('migrate.user_from') }}</th><th>{{ PD::trans('migrate.user_to') }}</th></tr></thead>
                    <tbody>
                        @foreach($report['users'] as $u)
                        <tr>
                            <td>{{ $u['user_name'] ?? '—' }} <code>{{ $u['user_code'] ?? 'ID ' . $u['id'] }}</code></td>
                            <td>@if($u['mapped'])<code>{{ $u['mapped'] }}</code>@else<span class="pp-mig-bad">{{ PD::trans('migrate.unmapped') }}</span>@endif</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @endif
            </div>
        </section>
        @endif
    </div>
</div>

@if($migrate['report'] || $migrate['error'])
{{-- The answer to a migration POST is at the bottom of a long page: take the
     administrator to it rather than leave them looking at the lists. --}}
<script>
(function () {
    var target = document.getElementById('pp-migrate');
    if (target && target.scrollIntoView) { target.scrollIntoView(); }
})();
</script>
@endif

<script>
(function () {
    var root = document.getElementById('pp-settings');
    if (!root) { return; }

    var sections = @json($sections);
    var urls = @json($urls);
    var text = {
        add: @json(PD::trans('settings.add')),
        save: @json(PD::trans('settings.save')),
        remove: @json(PD::trans('settings.delete')),
        saved: @json(PD::trans('settings.saved')),
        deleted: @json(PD::trans('settings.deleted')),
        confirm: @json(PD::trans('settings.confirm_delete')),
        empty: @json(PD::trans('settings.empty')),
        error: @json(PD::trans('settings.error')),
        inUse: @json(PD::trans('settings.in_use')),
        required: @json(PD::trans('settings.required')),
        noChange: @json(PD::trans('settings.no_change')),
        count: @json(PD::trans('settings.count')),
        newName: @json(PD::trans('settings.new_name')),
        running: @json(PD::trans('upgrade.running')),
        upgradeConfirm: @json(PD::trans('upgrade.confirm')),
        upgraded: @json(PD::trans('upgrade.success')),
        reload: @json(PD::trans('upgrade.reload')),
        already: @json(PD::trans('upgrade.already')),
        idleNote: @json(PD::trans('upgrade.idle_note')),
        versionNow: @json(PD::trans('upgrade.version_now', ['current' => $schema['target']]))
    };
    // The sentences for a failure the portal did not word itself - an expired
    // session, a proxy's error page, no answer at all - picked by HTTP status.
    var httpErrors = @json(PD::httpErrors());
    var token = document.querySelector('meta[name="csrf-token"]');
    token = token ? token.getAttribute('content') : '';

    // Never rejects: a request that got no answer at all comes back as status
    // 0, so every caller words it through failure() like any other.
    function api(method, path, body) {
        var options = {
            method: method,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': token }
        };
        if (body) {
            options.headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(body);
        }
        return fetch(path, options).then(function (res) {
            return res.json().catch(function () { return {}; }).then(function (json) {
                return { ok: res.ok, status: res.status, json: json || {} };
            });
        }).catch(function () {
            return { ok: false, status: 0, json: {} };
        });
    }

    // A stored colour as the #rrggbb a colour input can hold, or '' when it
    // is not a colour at all. #abc is expanded rather than lost to black.
    function hex6(value) {
        var raw = String(value == null ? '' : value).trim().toLowerCase();
        var abc = /^#([0-9a-f])([0-9a-f])([0-9a-f])$/.exec(raw);
        if (abc) { return '#' + abc[1] + abc[1] + abc[2] + abc[2] + abc[3] + abc[3]; }
        return /^#[0-9a-f]{6}$/.test(raw) ? raw : '';
    }

    function notify(message, kind) {
        if (window.toastr && typeof window.toastr[kind === 'error' ? 'error' : 'success'] === 'function') {
            window.toastr[kind === 'error' ? 'error' : 'success'](message);
            return;
        }
        window.alert(message);
    }

    // What a person reads when a call fails, in the order every portal
    // screen follows: the portal's own sentence, then one chosen by the HTTP
    // status, then this page's fallback. The error code, an exception's text
    // and a body the portal did not write never reach the screen - they are
    // English, and a database error carries the SQL.
    function failure(result) {
        var body = (result && result.json) || {};
        var portal = body.ok === false;
        if (portal && body.error === 'in_use') {
            return text.inUse.replace('%s', /^\d+$/.test(String(body.detail)) ? body.detail : '?');
        }
        if (portal && body.fields && typeof body.fields === 'object') {
            var messages = [];
            Object.keys(body.fields).forEach(function (field) {
                [].concat(body.fields[field]).forEach(function (line) {
                    if (typeof line === 'string' && line !== '') { messages.push(line); }
                });
            });
            if (messages.length) { return messages.join(' '); }
        }
        if (portal && typeof body.message === 'string' && body.message !== '') { return body.message; }
        var http = (typeof httpErrors !== 'undefined' && httpErrors) || {};
        var said = http.messages || {};
        var status = result && typeof result.status === 'number' ? result.status : 0;
        var name = (http.status || {})[status] || (status >= 500 ? 'server' : null);
        return (name && said[name]) || text.error || said.other || '';
    }

    // ---- one shared list --------------------------------------------------

    function Section(spec) {
        this.spec = spec;
        this.body = root.querySelector('[data-pp-rows="' + spec.kind + '"]');
        this.foot = root.querySelector('[data-pp-new="' + spec.kind + '"]');
        this.count = root.querySelector('[data-pp-count="' + spec.kind + '"]');
        this.rows = [];
        this.renderNew();
        this.load();
    }

    Section.prototype.listUrl = function () {
        return urls.lightapi + '/data/' + encodeURIComponent(this.spec.table) + '?per_page=200';
    };

    Section.prototype.load = function () {
        var self = this;
        // load() runs after every add and delete. Clearing the table up
        // front left it blank for as long as the request took, so the list
        // visibly vanished and came back on each edit. Build the replacement
        // off-screen and swap it in once, and let the newest request win so a
        // slow earlier answer cannot overwrite it.
        var token = (this.loadToken = (this.loadToken || 0) + 1);
        api('GET', this.listUrl()).then(function (result) {
            if (token !== self.loadToken) { return; }
            if (!result.ok) { notify(failure(result), 'error'); return; }
            var rows = (result.json.rows || []).slice();
            var orderField = self.field('order');
            if (orderField) {
                rows.sort(function (a, b) {
                    return (parseInt(a[orderField.name], 10) || 0) - (parseInt(b[orderField.name], 10) || 0);
                });
            }
            self.rows = rows;
            var next = document.createDocumentFragment();
            if (!rows.length) {
                var tr = document.createElement('tr');
                var td = document.createElement('td');
                td.colSpan = self.spec.fields.length + 1;
                td.className = 'none';
                td.textContent = text.empty;
                tr.appendChild(td);
                next.appendChild(tr);
            } else {
                rows.forEach(function (row) { next.appendChild(self.renderRow(row)); });
            }
            self.body.innerHTML = '';
            self.body.appendChild(next);
            if (self.count) { self.count.textContent = text.count.replace(':count', String(rows.length)); }
        });
    };

    Section.prototype.field = function (role) {
        for (var i = 0; i < this.spec.fields.length; i++) {
            if (this.spec.fields[i].role === role) { return this.spec.fields[i]; }
        }
        return null;
    };

    Section.prototype.input = function (field, value) {
        var el = document.createElement('input');
        el.className = 'pp-cfg-in';
        el.type = field.type === 'color' ? 'color' : (field.type === 'number' ? 'number' : 'text');
        // A color input only holds #rrggbb and turns anything else - a
        // short #abc, an empty value - into black, so the value is
        // expanded first and the default decided from the row rather
        // than from the element.
        var raw = value == null ? '' : String(value);
        if (field.type === 'color') { raw = hex6(raw) || '#95a5a6'; }
        el.value = raw;
        // The column heading names the cell for a sighted reader only.
        el.setAttribute('aria-label', field.label);
        el.title = field.label;
        el.setAttribute('data-pp-field', field.name);
        // What the row showed, so a save can send only what was changed.
        el.setAttribute('data-pp-initial', el.value);
        el.disabled = !this.spec.editable;
        return el;
    };

    Section.prototype.cell = function (field, value) {
        var td = document.createElement('td');
        td.className = 'c-' + field.type;
        td.appendChild(this.input(field, value));
        return td;
    };

    /**
     * The collected values that differ from what the row showed when it was
     * drawn. A save sends only these: writing back every column would undo
     * what someone else changed meanwhile, and would store the colour input's
     * own spelling of a colour nobody touched.
     */
    Section.prototype.changes = function (scope, values) {
        var out = {};
        this.spec.fields.forEach(function (field) {
            var el = scope.querySelector('[data-pp-field="' + field.name + '"]');
            if (!el || !Object.prototype.hasOwnProperty.call(values, field.name)) { return; }
            if (String(values[field.name]) !== el.getAttribute('data-pp-initial')) {
                out[field.name] = values[field.name];
            }
        });
        return out;
    };

    // Every value of the row, whether or not a name was given.
    Section.prototype.read = function (scope) {
        var values = {};
        this.spec.fields.forEach(function (field) {
            var el = scope.querySelector('[data-pp-field="' + field.name + '"]');
            if (el) { values[field.name] = el.value; }
        });
        return values;
    };

    // The same, or null when the row has no name to save under.
    Section.prototype.collect = function (scope) {
        var values = this.read(scope);
        var name = this.field('name');
        return name && !String(values[name.name] == null ? '' : values[name.name]).trim() ? null : values;
    };

    Section.prototype.button = function (label, icon, main) {
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'pp-cfg-btn' + (main ? ' main' : '');
        button.innerHTML = '<i class="fa ' + icon + '"></i>';
        var span = document.createElement('span');
        span.className = 'lb';
        span.textContent = label;
        button.appendChild(span);
        button.title = label;
        return button;
    };

    Section.prototype.renderRow = function (row) {
        var self = this;
        var tr = document.createElement('tr');

        this.spec.fields.forEach(function (field) {
            tr.appendChild(self.cell(field, row[field.name]));
        });

        var actions = document.createElement('td');
        actions.className = 'c-act';
        tr.appendChild(actions);
        if (!this.spec.editable) { return tr; }

        // 保存 wakes up only once the row differs from what was read, and the
        // row is marked until it is saved: with several rows on screen it has
        // to be plain which of them still holds an unsaved edit.
        var save = this.button(text.save, 'fa-save', false);
        var busy = false;
        function mark() {
            var dirty = Object.keys(self.changes(tr, self.read(tr))).length > 0;
            tr.classList.toggle('dirty', dirty);
            save.classList.toggle('main', dirty);
            save.disabled = busy || !dirty;
        }
        tr.addEventListener('input', mark);
        tr.addEventListener('change', mark);
        tr.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' && event.target.tagName === 'INPUT') { event.preventDefault(); save.click(); }
        });

        save.addEventListener('click', function () {
            if (save.disabled) { return; }
            var values = self.collect(tr);
            if (!values) { notify(text.required, 'error'); return; }
            var changed = self.changes(tr, values);
            if (!Object.keys(changed).length) { notify(text.noChange, 'success'); return; }
            busy = true;
            mark();
            // With the version the row was read at: a row someone else saved
            // in between is refused rather than overwritten.
            api('PATCH', urls.lightapi + '/data/' + encodeURIComponent(self.spec.table) + '/' + row.id,
                { value: changed, updated_at: row.updated_at })
                .then(function (result) {
                    busy = false;
                    if (result.status === 409 && result.json && result.json.error === 'stale') {
                        notify(failure(result), 'error');
                        self.load();
                        return;
                    }
                    if (!result.ok) { mark(); notify(failure(result), 'error'); return; }
                    // The row now holds what was sent, at a new version; the
                    // next save from this line starts from there.
                    Object.keys(changed).forEach(function (name) {
                        var el = tr.querySelector('[data-pp-field="' + name + '"]');
                        if (el) { el.setAttribute('data-pp-initial', String(changed[name])); }
                    });
                    if (result.json && result.json.updated_at) { row.updated_at = result.json.updated_at; }
                    mark();
                    notify(text.saved, 'success');
                    // A new 並び順 moves the row; redraw so the list shows the
                    // order every other screen will use.
                    var orderField = self.field('order');
                    if (orderField && Object.prototype.hasOwnProperty.call(changed, orderField.name)
                        && !self.body.querySelector('tr.dirty')) {
                        self.load();
                    }
                });
        });

        var remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'pp-cfg-del';
        remove.title = text.remove;
        remove.setAttribute('aria-label', text.remove);
        remove.innerHTML = '<i class="fa fa-trash-o"></i>';
        remove.addEventListener('click', function () {
            if (!window.confirm(text.confirm)) { return; }
            remove.disabled = true;
            api('DELETE', urls.lightapi + '/data/' + encodeURIComponent(self.spec.table) + '/' + row.id)
                .then(function (result) {
                    remove.disabled = false;
                    if (!result.ok) { notify(failure(result), 'error'); return; }
                    notify(text.deleted, 'success');
                    self.load();
                });
        });

        actions.appendChild(save);
        actions.appendChild(remove);
        mark();

        return tr;
    };

    Section.prototype.renderNew = function () {
        var self = this;
        this.foot.innerHTML = '';
        if (!this.spec.editable) { return; }

        var tr = document.createElement('tr');
        var orderField = this.field('order');
        var nameField = this.field('name');

        this.spec.fields.forEach(function (field) {
            var td = self.cell(field, '');
            var input = td.firstChild;
            if (field.role === 'name') { input.placeholder = text.newName; }
            // Left empty, the new row goes to the end of the list.
            if (field.role === 'order') { input.placeholder = '-'; }
            tr.appendChild(td);
        });

        var actions = document.createElement('td');
        actions.className = 'c-act';
        var add = this.button(text.add, 'fa-plus', false);
        var submit = function () {
            if (add.disabled) { return; }
            var values = self.collect(tr);
            if (!values) {
                notify(text.required, 'error');
                var blank = nameField && tr.querySelector('[data-pp-field="' + nameField.name + '"]');
                if (blank) { blank.focus(); }
                return;
            }
            if (orderField && String(values[orderField.name]).trim() === '') {
                var last = -1;
                self.rows.forEach(function (row) {
                    var n = parseInt(row[orderField.name], 10);
                    if (!isNaN(n) && n > last) { last = n; }
                });
                values[orderField.name] = String(last + 1);
            }

            add.disabled = true;
            api('POST', urls.lightapi + '/data/' + encodeURIComponent(self.spec.table), { value: values })
                .then(function (result) {
                    add.disabled = false;
                    if (!result.ok) { notify(failure(result), 'error'); return; }
                    notify(text.saved, 'success');
                    self.renderNew();
                    self.load();
                });
        };
        add.addEventListener('click', submit);
        tr.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' && event.target.tagName === 'INPUT') { event.preventDefault(); submit(); }
        });
        actions.appendChild(add);
        tr.appendChild(actions);

        this.foot.appendChild(tr);
    };

    // ---- schema upgrade -------------------------------------------------

    function showUpgraded(runner, log) {
        var box = document.getElementById('pp-upgrade-box');
        var count = box.querySelector('.pp-cfg-count');
        if (count) { count.textContent = text.versionNow; }
        var todo = box.querySelector('.pp-cfg-state.todo');
        if (todo) {
            var ok = document.createElement('div');
            ok.className = 'pp-cfg-state ok';
            var icon = document.createElement('i');
            icon.className = 'fa fa-check-circle';
            var words = document.createElement('div');
            var b = document.createElement('b');
            b.textContent = text.already;
            var span = document.createElement('span');
            span.textContent = text.idleNote;
            words.appendChild(b);
            words.appendChild(span);
            ok.appendChild(icon);
            ok.appendChild(words);
            todo.parentNode.replaceChild(ok, todo);
        }
        // The lead, the backup warning and the button were for the run
        // that is now over.
        box.querySelectorAll('.pp-cfg-body > .pp-cfg-hint, .pp-cfg-body > .pp-cfg-warn').forEach(function (el) { el.style.display = 'none'; });
        runner.style.display = 'none';
        log.style.marginTop = '12px';
    }

    var runner = document.getElementById('pp-upgrade-run');
    if (runner) {
        runner.addEventListener('click', function () {
            if (runner.disabled) { return; }
            // It rewrites stored values and cannot be undone, so it never
            // runs on a stray click.
            if (!window.confirm(text.upgradeConfirm)) { return; }
            runner.disabled = true;
            var log = document.getElementById('pp-upgrade-log');
            log.textContent = text.running;
            api('POST', urls.upgrade, { convert: true }).then(function (result) {
                var body = result.json || {};
                var done = result.ok && body.ok === true;
                // A finished run stays finished: the page has to be read
                // again before anything else on it is current.
                runner.disabled = done;
                log.innerHTML = '';
                var message = document.createElement('div');
                message.className = 'res' + (done ? '' : ' err');
                message.textContent = done
                    ? (typeof body.message === 'string' && body.message !== '' ? body.message : text.upgraded)
                    : failure(result);
                log.appendChild(message);

                // What each step did, warnings included. Only from the
                // upgrade's own answer, which says ok one way or the other.
                var steps = typeof body.ok === 'boolean' && Array.isArray(body.steps) ? body.steps : [];
                steps = steps.filter(function (step) { return typeof step === 'string' && step !== ''; });
                if (steps.length) {
                    var list = document.createElement('ul');
                    steps.forEach(function (step) {
                        var item = document.createElement('li');
                        item.textContent = step;
                        list.appendChild(item);
                    });
                    log.appendChild(list);
                }
                // Done: the box says so at once, the way it reads after a
                // reload - up to date, at the new version, no button - and
                // keeps what each step did below it for the administrator.
                if (done) { showUpgraded(runner, log); }
                // Left for the administrator to read before the page moves
                // on: a step may report values it could not convert.
                if (done) {
                    var again = document.createElement('button');
                    again.type = 'button';
                    again.className = 'pp-cfg-btn';
                    again.textContent = text.reload;
                    again.addEventListener('click', function () { window.location.reload(); });
                    log.appendChild(again);
                }
            });
        });
    }

    sections.forEach(function (spec) { new Section(spec); });
})();
</script>
