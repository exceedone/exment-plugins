{{-- The recurring schedules of a project (ISS00432, stage 2a): PortalSchedulePageTrait@schedulesPage --}}
@php
    use App\Plugins\ExmentPM\PortalDefinition;

    $s = function ($key, $args = []) {
        return PortalDefinition::trans('sched.' . $key, $args);
    };
    $v = function ($key, $default = '') use ($old, $form, $edit) {
        if (array_key_exists($key, $old)) {
            return $old[$key];
        }
        if (array_key_exists($key, $form)) {
            return $form[$key];
        }
        if ($edit && isset($edit->{$key})) {
            return $edit->{$key};
        }
        return $default;
    };
    $wdays = ['MO' => '月', 'TU' => '火', 'WE' => '水', 'TH' => '木', 'FR' => '金', 'SA' => '土', 'SU' => '日'];
    $kind = strval($v('kind', 'monthly'));
    $rdatesText = $edit ? implode(', ', json_decode(strval($edit->rdates ?? '[]'), true) ?: []) : '';
    $wd = function ($date) { return ['日', '月', '火', '水', '木', '金', '土'][intval(date('w', strtotime($date)))]; };
@endphp
<style>
.pp-sc{--s-line:#dfe5ea;--s-mute:#8a97a3;--s-accent:#4cae93;--s-red:#e5544b;color:#33434f;}
.pp-sc .wrap{padding:12px 16px;max-width:960px;}
.pp-sc .box2{background:#fff;border:1px solid var(--s-line);border-radius:7px;padding:14px 16px;margin-bottom:14px;}
.pp-sc h4{margin:0 0 8px;font-size:15px;font-weight:700;display:flex;align-items:center;gap:10px;}
.pp-sc h4 .r{margin-left:auto;font-size:12px;font-weight:400;}
.pp-sc .mute{color:var(--s-mute);font-size:12px;}
.pp-sc .f{margin-bottom:10px;}
.pp-sc .f label.h{display:block;font-weight:700;font-size:12.5px;margin-bottom:3px;}
.pp-sc input[type=text],.pp-sc input[type=date],.pp-sc input[type=number],.pp-sc select{border:1px solid var(--s-line);border-radius:5px;padding:4px 7px;font-size:13px;min-height:30px;}
.pp-sc .ck{display:inline-flex;align-items:center;gap:5px;margin-right:12px;font-weight:400;}
.pp-sc table{width:100%;border-collapse:collapse;font-size:12.5px;margin-top:6px;}
.pp-sc td,.pp-sc th{padding:5px 6px;border-bottom:1px solid var(--s-line);text-align:left;vertical-align:middle;}
.pp-sc .skip{color:var(--s-mute);text-decoration:line-through;}
.pp-sc .moved{color:#c27c0e;font-weight:700;}
.pp-sc .ok{background:#e8f6f0;border:1px solid var(--s-accent);border-radius:6px;padding:8px 12px;margin-bottom:12px;}
.pp-sc .ng{background:#fde8e6;border:1px solid var(--s-red);border-radius:6px;padding:8px 12px;margin-bottom:12px;}
.pp-sc form.inl{display:inline-flex;gap:4px;align-items:center;margin:0;}
.pp-sc .lnk{border:0;background:none;color:#3c8dbc;cursor:pointer;padding:0 4px;font-size:12px;}
.pp-sc [data-k]{display:none;}
.pp-sc [data-k].on{display:block;}
</style>
<div class="pp-sc">
    @include(PortalDefinition::view('_topbar'))
    <div class="wrap">
        <p><a href="{{ $project_url }}#settings"><i class="fa fa-angle-left"></i> {{ $project_name }}</a></p>
        <h3 style="margin:4px 0 6px;font-size:18px;"><i class="fa fa-repeat"></i> {{ $s('page_title') }}</h3>
        <p class="mute">{{ $s('page_help') }}</p>
        @if($saved)<div class="ok">{{ $s('saved') }}</div>@endif
        @if(!empty($errors_list))<div class="ng">@foreach($errors_list as $e)<div>{{ $e }}</div>@endforeach</div>@endif

        @if(!$show_form)
        <p><a class="btn btn-primary btn-sm" href="{{ $base_url }}?new=1"><i class="fa fa-plus"></i> {{ $s('add') }}</a>
            <a href="{{ $calendar_url }}" style="margin-left:12px;"><i class="fa fa-calendar"></i> {{ $s('cal_title') }}</a>
            @if($company_url)<a href="{{ $company_url }}" style="margin-left:12px;"><i class="fa fa-cog"></i> {{ PortalDefinition::trans('cal.page_title') }}</a>@endif</p>
        @endif

        @if($show_form)
        <form method="post" action="{{ $base_url }}" class="box2" id="pp-sc-form">
            <input type="hidden" name="_token" value="{{ csrf_token() }}">
            <input type="hidden" name="schedule_id" value="{{ $edit->id ?? '' }}">
            @if($split)<input type="hidden" name="split_from" value="{{ $split }}">@endif
            <h4>{{ $edit ? ($split ? $s('split_title', ['date' => $split]) : $s('edit')) : $s('add') }}</h4>
            @if($split)<p class="mute">{{ $s('split_help', ['date' => $split]) }}</p>@endif
            <div class="f"><label class="h">{{ $s('name') }}</label><input type="text" name="name" value="{{ $v('name') }}" style="width:100%;max-width:420px;"></div>
            <div class="f"><label class="h">{{ $s('template') }}</label>
                <select name="template" style="max-width:100%;">
                    <option value="">—</option>
                    @foreach($issues as $i)<option value="{{ $i['id'] }}" @if(strval($v('template', $edit->template_issue_id ?? '')) === strval($i['id'])) selected @endif>{{ $i['l'] }}</option>@endforeach
                </select>
                <div class="mute">{{ $s('template_help') }}</div></div>
            <div class="f"><label class="h">{{ $s('kind') }}</label>
                <select name="kind" id="pp-sc-kind">
                    @foreach(['yearly', 'monthly', 'weekly', 'daily', 'dates', 'custom'] as $k)<option value="{{ $k }}" @if($kind === $k) selected @endif>{{ $s('kind_' . $k) }}</option>@endforeach
                </select>
                <span data-k="yearly monthly weekly daily">{{ $s('every') }} <input type="number" name="interval" min="1" max="99" value="{{ $v('interval', 1) }}" style="width:60px;"> {{ $s('interval_unit') }}</span>
            </div>
            <div class="f" data-k="yearly"><label class="h">{{ $s('month') }}</label>
                <select name="month">@for($m = 1; $m <= 12; $m++)<option value="{{ $m }}" @if(intval($v('month', 4)) === $m) selected @endif>{{ $m }} 月</option>@endfor</select></div>
            <div class="f" data-k="yearly monthly">
                <label class="ck"><input type="radio" name="by" value="day" @if($v('by', 'day') !== 'nth') checked @endif> {{ $s('by_day') }}</label>
                <select name="monthday">@for($d = 1; $d <= 31; $d++)<option value="{{ $d }}" @if(intval($v('monthday', 1)) === $d) selected @endif>{{ $d }} 日</option>@endfor<option value="-1" @if(intval($v('monthday', 1)) === -1) selected @endif>{{ $s('last_day') }}</option></select>
                <br>
                <label class="ck"><input type="radio" name="by" value="nth" @if($v('by', 'day') === 'nth') checked @endif> {{ $s('by_nth') }}</label>
                <select name="nth">@foreach([1, 2, 3, 4, -1] as $n)<option value="{{ $n }}" @if(intval($v('nth', 1)) === $n) selected @endif>{{ $n === -1 ? $s('nth_last') : $s('nth', ['n' => $n]) }}</option>@endforeach</select>
                <select name="weekday">@foreach($wdays as $k => $l)<option value="{{ $k }}" @if($v('weekday', 'MO') === $k) selected @endif>{{ $l }}曜</option>@endforeach</select>
            </div>
            <div class="f" data-k="weekly"><label class="h">{{ $s('weekdays') }}</label>
                @foreach($wdays as $k => $l)<label class="ck"><input type="checkbox" name="weekdays[]" value="{{ $k }}" @if(in_array($k, (array) $v('weekdays', []), true)) checked @endif> {{ $l }}</label>@endforeach</div>
            <div class="f" data-k="custom"><label class="h">RRULE</label><input type="text" name="rrule" value="{{ $v('rrule', $edit->rrule ?? '') }}" style="width:100%;max-width:520px;" placeholder="FREQ=MONTHLY;BYDAY=MO,TU,WE,TH,FR;BYSETPOS=-1"><div class="mute">{{ $s('rrule_help') }}</div></div>
            <div class="f on"><label class="h">{{ $s('rdates') }}</label><input type="text" name="rdates" value="{{ $v('rdates', $rdatesText) }}" style="width:100%;max-width:520px;" placeholder="2027-01-15, 2027-06-30"><div class="mute">{{ $s('rdates_help') }}</div></div>
            <div class="f"><label class="h">{{ $s('start') }}</label><input type="date" name="start_date" value="{{ $split ?: $v('start_date', $today) }}" @if($split) readonly @endif>
                <label class="h" style="display:inline;margin-left:12px;">{{ $s('end') }}</label> <input type="date" name="end_date" value="{{ $v('end_date') }}"></div>
            <div class="f"><label class="h">{{ $s('shift') }}</label>
                <select name="shift">@foreach(['before', 'after', 'keep', 'skip'] as $k)<option value="{{ $k }}" @if($v('shift', 'keep') === $k) selected @endif>{{ $s('shift_' . $k) }}</option>@endforeach</select></div>
            <div class="f"><label class="h">{{ $s('lead') }}</label><input type="number" name="lead_days" min="0" max="90" value="{{ $v('lead_days', 7) }}" style="width:70px;"> {{ $s('lead_unit') }}</div>
            <div class="f"><label class="ck"><input type="checkbox" name="enabled" value="1" @if($v('enabled', 1)) checked @endif> {{ $s('enabled') }}</label></div>
            <button type="submit" class="btn btn-primary">{{ $s('save') }}</button>
            <a href="{{ $base_url }}" style="margin-left:10px;">{{ $s('cancel') }}</a>
        </form>
        @endif

        @forelse($list as $item)
        @php $row = $item['row']; @endphp
        <div class="box2">
            <h4><i class="fa fa-repeat"></i> {{ $row->name }}
                @if(!$row->enabled)<span class="mute">（{{ $s('off') }}）</span>@endif
                <span class="r"><a href="{{ $base_url }}?edit={{ $row->id }}">{{ $s('edit') }}</a>
                <form class="inl" method="post" action="{{ $base_url }}/{{ $row->id }}/delete" onsubmit="return confirm('{{ $s('delete_ask') }}');"><input type="hidden" name="_token" value="{{ csrf_token() }}"><button class="lnk" type="submit">{{ $s('delete') }}</button></form></span></h4>
            <div class="mute">{{ $s('template') }}: {{ $item['template'] }} ・ {{ $row->rrule !== '' ? $row->rrule : $s('kind_dates') }} ・ {{ $s('shift_' . $row->shift) }} ・ {{ $s('lead_short', ['n' => $row->lead_days]) }}</div>
            <table>
                <tr><th>{{ $s('col_orig') }}</th><th>{{ $s('col_due') }}</th><th>{{ $s('col_issue') }}</th><th>{{ $s('col_change') }}</th></tr>
                @forelse($item['next'] as $n)
                <tr>
                    <td>{{ $n['orig'] }}（{{ $wd($n['orig']) }}）</td>
                    <td class="{{ $n['due'] === null ? 'skip' : ($n['due'] !== $n['orig'] ? 'moved' : '') }}">{{ $n['due'] === null ? $s('skipped') : $n['due'] . '（' . $wd($n['due']) . '）' }}</td>
                    <td>@if($n['issue'])<a href="{{ $project_url }}#issue-{{ $n['issue'] }}">#{{ $n['issue'] }}</a>@else<span class="mute">—</span>@endif</td>
                    <td>
                        <form class="inl" method="post" action="{{ $base_url }}/{{ $row->id }}/exception"><input type="hidden" name="_token" value="{{ csrf_token() }}"><input type="hidden" name="orig" value="{{ $n['orig'] }}">
                            <input type="date" name="new_date" value="{{ $n['due'] ?? $n['orig'] }}" style="min-height:26px;"><button class="lnk" name="do" value="move">{{ $s('move') }}</button>
                            <button class="lnk" name="do" value="skip">{{ $s('skip') }}</button>
                            @if($n['moved'])<button class="lnk" name="do" value="reset">{{ $s('reset') }}</button>@endif
                        </form>
                        @if($n['orig'] > $row->start_date)<a class="lnk" href="{{ $base_url }}?edit={{ $row->id }}&split={{ $n['orig'] }}">{{ $s('split') }}</a>@endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="4" class="mute">{{ $s('none_next') }}</td></tr>
                @endforelse
            </table>
        </div>
        @empty
        <p class="mute">{{ $s('none') }}</p>
        @endforelse
    </div>
</div>
<script>
(function () {
    var kind = document.getElementById('pp-sc-kind');
    if (!kind) { return; }
    function show() {
        document.querySelectorAll('#pp-sc-form [data-k]').forEach(function (el) {
            el.classList.toggle('on', el.getAttribute('data-k').split(' ').indexOf(kind.value) >= 0);
        });
    }
    kind.addEventListener('change', show);
    show();
})();
</script>
