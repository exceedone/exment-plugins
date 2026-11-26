<div class="box box-default" style="padding:16px;">
    <h3 style="margin-top:0;">{{ $L['title'] }}</h3>
    <p style="color:#666;">{{ $L['intro'] }}</p>
    @if ($ok)<div class="alert alert-success">{{ $ok }}</div>@endif
    @if ($error)<div class="alert alert-danger">{{ $error }}</div>@endif

    <p><a class="btn btn-primary btn-sm" href="{{ $url('new') }}"><i class="fa fa-plus"></i> {{ $L['add'] }}</a></p>

    @if (!empty($hasSync))
        <div class="alert alert-info">
            <strong>{{ $L['sync_header'] }}</strong><br>{{ $L['sync_help'] }}
            <form method="post" action="{{ $url('to-sync') }}" style="margin-top:8px;">
                <input type="hidden" name="_token" value="{{ csrf_token() }}">
                <button type="submit" class="btn btn-default btn-sm">{{ $L['sync_move'] }}</button>
                <a class="btn btn-link btn-sm" href="{{ $syncUrl }}">{{ $syncUrl }}</a>
            </form>
        </div>
    @endif

    @if (empty($rows))
        <p>{{ $L['none'] }}</p>
    @else
    <div style="overflow-x:auto;">
    <table class="table table-striped table-condensed">
        <thead><tr>
            <th>{{ $L['col_name'] }}</th><th>{{ $L['col_backlog'] }}</th><th>{{ $L['col_exment'] }}</th>
            <th>{{ $L['col_interval'] }}</th><th>{{ $L['col_enabled'] }}</th><th>{{ $L['col_issues'] }}</th>
            <th>{{ $L['col_last'] }}</th><th>{{ $L['col_next'] }}</th><th></th>
        </tr></thead>
        <tbody>
        @foreach ($rows as $r)
            <tr>
                <td>{{ $r['conn']->name }}</td>
                <td>{{ $r['conn']->backlog_project_key }}<br><small style="color:#888;">{{ $r['conn']->space_url }}</small></td>
                <td>{{ $r['project'] }}</td>
                <td>{{ str_replace(':n', $r['conn']->interval_minutes, $L['minutes']) }}</td>
                <td>{{ !empty($hasSync) && !empty($r['conn']->sync_mapping_id) ? $L['sync_is_moved'] : ($r['conn']->enabled ? $L['on'] : $L['off']) }}</td>
                <td>{{ $r['issues'] }}</td>
                <td>{{ $r['conn']->last_run_at ? substr($r['conn']->last_run_at, 0, 16) : '—' }}</td>
                <td>{{ $r['conn']->enabled && $r['conn']->next_run_at ? substr($r['conn']->next_run_at, 0, 16) : '—' }}</td>
                <td><a class="btn btn-default btn-xs" href="{{ $url($r['conn']->id) }}">{{ $L['edit'] }}</a></td>
            </tr>
        @endforeach
        </tbody>
    </table>
    </div>
    @endif
</div>
