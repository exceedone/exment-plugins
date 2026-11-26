{{-- The company's days off and working days (ISS00432, stage 2b): PortalCalendarPageTrait@companyCalendar --}}
@php
    use App\Plugins\ExmentPM\PortalDefinition;

    $c = function ($key, $args = []) {
        return PortalDefinition::trans('cal.' . $key, $args);
    };
    $wd = function ($date) { return ['日', '月', '火', '水', '木', '金', '土'][intval(date('w', strtotime($date)))]; };
@endphp
<style>
.pp-cc{--c-line:#dfe5ea;--c-mute:#8a97a3;--c-accent:#4cae93;--c-red:#e5544b;color:#33434f;}
.pp-cc .wrap{padding:12px 16px;max-width:860px;}
.pp-cc .box2{background:#fff;border:1px solid var(--c-line);border-radius:7px;padding:14px 16px;margin-bottom:14px;}
.pp-cc h4{margin:0 0 8px;font-size:15px;font-weight:700;}
.pp-cc .mute{color:var(--c-mute);font-size:12px;}
.pp-cc table{width:100%;border-collapse:collapse;font-size:12.5px;}
.pp-cc td,.pp-cc th{padding:5px 6px;border-bottom:1px solid var(--c-line);text-align:left;}
.pp-cc .off{color:var(--c-red);font-weight:700;}
.pp-cc .work{color:#2a7ab8;font-weight:700;}
.pp-cc input[type=text],.pp-cc input[type=date],.pp-cc select{border:1px solid var(--c-line);border-radius:5px;padding:4px 7px;font-size:13px;min-height:30px;}
.pp-cc .row{display:flex;flex-wrap:wrap;gap:8px;align-items:center;}
.pp-cc .ok{background:#e8f6f0;border:1px solid var(--c-accent);border-radius:6px;padding:8px 12px;margin-bottom:12px;}
.pp-cc .ng{background:#fde8e6;border:1px solid var(--c-red);border-radius:6px;padding:8px 12px;margin-bottom:12px;}
.pp-cc .lnk{border:0;background:none;color:#3c8dbc;cursor:pointer;padding:0;font-size:12px;}
.pp-cc .grid2{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
@media (max-width: 767px){ .pp-cc .grid2{grid-template-columns:1fr;} }
</style>
<div class="pp-cc">
    @include(PortalDefinition::view('_topbar'))
    <div class="wrap">
        <h3 style="margin:4px 0 6px;font-size:18px;"><i class="fa fa-calendar"></i> {{ $c('page_title') }}</h3>
        <p class="mute">{{ $c('page_help') }}</p>
        @if($saved)<div class="ok">{{ $c('saved') }}</div>@endif
        @if($error)<div class="ng">{{ $c('error') }}</div>@endif
        <p><a href="{{ $base_url }}?year={{ $year - 1 }}">« {{ $year - 1 }}</a> <b style="margin:0 10px;">{{ $year }}</b> <a href="{{ $base_url }}?year={{ $year + 1 }}">{{ $year + 1 }} »</a></p>

        <form method="post" action="{{ $base_url }}" class="box2">
            <input type="hidden" name="_token" value="{{ csrf_token() }}">
            <h4>{{ $c('add') }}</h4>
            <div class="row">
                <input type="date" name="from" required> 〜 <input type="date" name="to">
                <select name="kind"><option value="off">{{ $c('kind_off') }}</option><option value="work">{{ $c('kind_work') }}</option></select>
                <input type="text" name="name" placeholder="{{ $c('name_ph') }}" style="width:200px;">
                <button type="submit" class="btn btn-primary btn-sm">{{ $c('add_btn') }}</button>
            </div>
            <div class="mute" style="margin-top:6px;">{{ $c('add_help') }}</div>
        </form>

        <div class="grid2">
            <div class="box2">
                <h4>{{ $c('company', ['year' => $year]) }}</h4>
                <table>
                    @forelse($days as $d)
                    <tr>
                        <td>{{ $d->date }}（{{ $wd($d->date) }}）</td>
                        <td class="{{ $d->kind === 'work' ? 'work' : 'off' }}">{{ $d->kind === 'work' ? $c('kind_work') : $c('kind_off') }}</td>
                        <td>{{ $d->name }}</td>
                        <td><form method="post" action="{{ $base_url }}/delete" style="margin:0;"><input type="hidden" name="_token" value="{{ csrf_token() }}"><input type="hidden" name="date" value="{{ $d->date }}"><button class="lnk" type="submit">{{ $c('remove') }}</button></form></td>
                    </tr>
                    @empty
                    <tr><td class="mute">{{ $c('none') }}</td></tr>
                    @endforelse
                </table>
            </div>
            <div class="box2">
                <h4>{{ $c('holidays', ['year' => $year]) }}</h4>
                <table>
                    @forelse($holidays as $date => $name)
                    <tr><td>{{ $date }}（{{ $wd($date) }}）</td><td>{{ $name }}</td></tr>
                    @empty
                    <tr><td class="mute">{{ $c('holidays_none') }}</td></tr>
                    @endforelse
                </table>
            </div>
        </div>
    </div>
</div>
