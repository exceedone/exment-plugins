<div class="box box-default" style="padding:16px;">
    <p><a href="{{ $url() }}"><i class="fa fa-angle-left"></i> {{ $L['back'] }}</a></p>
    <h3 style="margin-top:0;">{{ $L['title'] }}{{ $conn ? '：' . $conn->name : '' }}</h3>
    @if ($ok)<div class="alert alert-success">{{ $ok }}</div>@endif
    @if ($error)<div class="alert alert-danger">{{ $error }}</div>@endif

    {{-- the connection --}}
    <form method="post" action="{{ $conn ? $url($conn->id) : $url('new') }}" class="form-horizontal" autocomplete="off">
        {{ csrf_field() }}
        <div class="form-group">
            <label class="col-sm-3 control-label">{{ $L['f_name'] }}</label>
            <div class="col-sm-7"><input class="form-control" name="name" maxlength="255" value="{{ old('name', $conn->name ?? '') }}"></div>
        </div>
        <div class="form-group">
            <label class="col-sm-3 control-label">{{ $L['f_space_url'] }}</label>
            <div class="col-sm-7"><input class="form-control" name="space_url" placeholder="https://example.backlog.com" value="{{ old('space_url', $conn->space_url ?? '') }}">
                <span class="help-block">{{ $L['f_space_url_help'] }}</span></div>
        </div>
        <div class="form-group">
            <label class="col-sm-3 control-label">{{ $L['f_api_key'] }}</label>
            <div class="col-sm-7"><input class="form-control" type="password" name="api_key" value="" autocomplete="new-password">
                <span class="help-block">{{ $hasKey ? $L['f_api_key_help_set'] : $L['f_api_key_help_unset'] }}</span></div>
        </div>
        <div class="form-group">
            <label class="col-sm-3 control-label">{{ $L['f_project_key'] }}</label>
            <div class="col-sm-4"><input class="form-control" name="backlog_project_key" value="{{ old('backlog_project_key', $conn->backlog_project_key ?? '') }}"></div>
        </div>
        <div class="form-group">
            <label class="col-sm-3 control-label">{{ $L['f_exment_project'] }}</label>
            <div class="col-sm-7"><select class="form-control" name="exment_project_id">
                <option value=""></option>
                @foreach ($projects as $pid => $plabel)
                    <option value="{{ $pid }}" @if (strval(old('exment_project_id', $conn->exment_project_id ?? '')) === strval($pid)) selected @endif>{{ $plabel }}</option>
                @endforeach
            </select></div>
        </div>
        <div class="form-group">
            <label class="col-sm-3 control-label">{{ $L['f_interval'] }}</label>
            <div class="col-sm-2"><input class="form-control" type="number" min="5" name="interval_minutes" value="{{ old('interval_minutes', $conn->interval_minutes ?? 30) }}"></div>
            <div class="col-sm-5"><span class="help-block">{{ $L['f_interval_help'] }}</span></div>
        </div>
        <div class="form-group">
            <div class="col-sm-offset-3 col-sm-7"><label style="font-weight:normal;">
                <input type="checkbox" name="enabled" value="1" @if (old('enabled', $conn->enabled ?? 0)) checked @endif> {{ $L['f_enabled'] }}</label></div>
        </div>
        <div class="form-group">
            <div class="col-sm-offset-3 col-sm-7"><button class="btn btn-primary">{{ $L['save'] }}</button></div>
        </div>
    </form>

    @if ($conn)
    <hr>
    {{-- mapping --}}
    <h4>{{ $L['mapping'] }}</h4>
    <p style="color:#666;">{{ $L['mapping_help'] }}</p>
    <form method="post" action="{{ $url($conn->id, 'mapping') }}" style="display:inline-block;margin-right:6px;">
        {{ csrf_field() }}
        <button class="btn btn-default btn-sm" @if (!$hasKey) disabled @endif><i class="fa fa-refresh"></i> {{ $L['mapping_refresh'] }}</button>
    </form>
    <form method="post" action="{{ $url($conn->id, 'mapping') }}" style="display:inline-block;">
        {{ csrf_field() }}<input type="hidden" name="create" value="1">
        <button class="btn btn-default btn-sm" @if (!$hasKey) disabled @endif><i class="fa fa-magic"></i> {{ $L['mapping_create'] }}</button>
    </form>

    @if (empty($mapping))
        <p style="margin-top:10px;">{{ $L['mapping_none'] }}</p>
    @else
        <p style="margin-top:10px;color:#888;">Backlog: {{ $mapping['backlog_project_name'] ?? '' }}（{{ $mapping['refreshed_at'] ?? '' }}）</p>
        @if (empty($missing))
            <p>{{ $L['mapping_ok'] }}</p>
        @else
            <p><strong>{{ $L['mapping_missing'] }}</strong></p>
            <ul>
            @foreach ($missing as $kind => $names)
                <li>{{ $L['kind_' . $kind] ?? $kind }}: {{ implode('、', $names) }}</li>
            @endforeach
            </ul>
        @endif

        <h5 style="margin-top:16px;"><strong>{{ $L['users'] }}</strong></h5>
        <p style="color:#666;">{{ $L['users_help'] }}</p>
        <form method="post" action="{{ $url($conn->id, 'mapping') }}">
            {{ csrf_field() }}
            <div style="overflow-x:auto;">
            <table class="table table-condensed" style="max-width:820px;">
                <tbody>
                @foreach (($mapping['user_names'] ?? []) as $bid => $bname)
                    <tr>
                        <td style="width:40%;">{{ $bname }} <small style="color:#aaa;">#{{ $bid }}</small></td>
                        <td><select class="form-control input-sm" name="users[{{ $bid }}]">
                            <option value="">{{ $L['user_none'] }}</option>
                            @foreach ($exUsers as $uid => $uname)
                                <option value="{{ $uid }}" @if (strval($mapping['users'][$bid] ?? '') === strval($uid)) selected @endif>{{ $uname }}</option>
                            @endforeach
                        </select></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            </div>
            <button class="btn btn-default btn-sm" @if (!$hasKey) disabled @endif>{{ $L['users_save'] }}</button>
        </form>
    @endif

    <hr>
    {{-- run now --}}
    <form method="post" action="{{ $url($conn->id, 'run') }}">
        {{ csrf_field() }}
        <button class="btn btn-success" @if (empty($mapping) || !$hasKey) disabled @endif><i class="fa fa-play"></i> {{ $L['run'] }}</button>
        <span style="color:#666;margin-left:8px;">{{ str_replace(':n', $manualLimit, $L['run_help']) }} {{ $L['col_issues'] }}: {{ $issues ?? 0 }}</span>
    </form>

    <h4 style="margin-top:20px;">{{ $L['runs'] }}</h4>
    <div style="overflow-x:auto;">
    <table class="table table-condensed">
        <thead><tr><th>{{ $L['r_started'] }}</th><th>{{ $L['r_trigger'] }}</th><th>{{ $L['r_status'] }}</th><th>{{ $L['r_counts'] }}</th><th>{{ $L['r_problems'] }}</th></tr></thead>
        <tbody>
        @foreach ($runs as $run)
            @php $counts = json_decode($run->counts ?? '', true) ?: []; $probs = json_decode($run->problems ?? '', true) ?: []; @endphp
            <tr>
                <td style="white-space:nowrap;">{{ substr($run->started_at, 0, 16) }}</td>
                <td>{{ $L['trigger_' . $run->trigger] ?? $run->trigger }}</td>
                <td>{{ $L['status_' . $run->status] ?? $run->status }}@if ($run->message)<br><small style="color:#c0392b;">{{ $run->message }}</small>@endif</td>
                <td style="white-space:nowrap;"><small>
                    @foreach ($counts as $k => $v){{ $k }} {{ $v }} @endforeach
                </small></td>
                <td>@if ($probs)<details><summary>{{ count($probs) }}</summary><small>@foreach ($probs as $p){{ $p }}<br>@endforeach</small></details>@endif</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    </div>

    <hr>
    {{-- take over from the internal tool --}}
    <h4>{{ $L['import'] }}</h4>
    <p style="color:#666;">{{ $L['import_help'] }}</p>
    <form method="post" action="{{ $url($conn->id, 'import') }}" enctype="multipart/form-data" class="form-inline">
        {{ csrf_field() }}
        <label style="font-weight:normal;margin-right:12px;">{{ $L['import_state'] }} <input type="file" name="state" accept=".json,application/json" style="display:inline-block;"></label>
        <label style="font-weight:normal;margin-right:12px;">{{ $L['import_mapping'] }} <input type="file" name="mapping" accept=".json,application/json" style="display:inline-block;"></label>
        <button class="btn btn-default btn-sm" @if (empty($mapping)) disabled @endif>{{ $L['import_run'] }}</button>
    </form>

    <hr>
    <form method="post" action="{{ $url($conn->id, 'delete') }}" onsubmit="return confirm(@json($L['delete_confirm']));">
        {{ csrf_field() }}
        <button class="btn btn-danger btn-sm"><i class="fa fa-trash"></i> {{ $L['delete'] }}</button>
    </form>
    @endif
</div>
