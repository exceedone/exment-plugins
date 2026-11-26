{{-- The dates of a project's schedules, a month or a year (ISS00432, stage 2b): PortalCalendarPageTrait@schedulesCalendar --}}
@php
    use App\Plugins\ExmentPM\PortalDefinition;

    $s = function ($key, $args = []) {
        return PortalDefinition::trans('sched.' . $key, $args);
    };
    $first = new \DateTimeImmutable($from);
    if ($view === 'month') {
        $prev = $first->modify('-1 month')->format('Y-m');
        $next = $first->modify('+1 month')->format('Y-m');
    } else {
        $prev = strval(intval($label) - 1);
        $next = strval(intval($label) + 1);
    }
    $months = [];
    for ($m = $first; $m->format('Y-m-d') <= $to; $m = $m->modify('first day of next month')) {
        $months[] = $m;
        if ($view === 'month') {
            break;
        }
    }
    $wdays = ['月', '火', '水', '木', '金', '土', '日'];
@endphp
<style>
.pp-scal{--k-line:#dfe5ea;--k-mute:#8a97a3;--k-accent:#4cae93;--k-red:#e5544b;color:#33434f;}
.pp-scal .wrap{padding:12px 16px;}
.pp-scal .bar{display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:10px;}
.pp-scal .bar .r{margin-left:auto;}
.pp-scal .mgrid{display:grid;grid-template-columns:repeat(7,1fr);border-left:1px solid var(--k-line);border-top:1px solid var(--k-line);background:#fff;}
.pp-scal .mgrid > div{border-right:1px solid var(--k-line);border-bottom:1px solid var(--k-line);min-height:86px;padding:4px 5px;font-size:12px;overflow:hidden;}
.pp-scal .mgrid .hd{min-height:0;font-weight:700;text-align:center;background:#f6f8fa;}
.pp-scal .mgrid .off{background:#fbf1f0;}
.pp-scal .mgrid .out{background:#f8f9fa;color:#c3cad1;}
.pp-scal .mgrid .today .d{background:var(--k-accent);color:#fff;border-radius:9px;padding:0 6px;}
.pp-scal .mgrid .lbl{color:var(--k-red);font-size:10.5px;display:block;}
.pp-scal .ev{display:block;margin-top:3px;background:color-mix(in srgb, var(--k-accent) 14%, #fff);border-left:3px solid var(--k-accent);border-radius:3px;padding:1px 4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.pp-scal .ev.moved{border-left-color:#e3a21a;}
.pp-scal .ygrid{display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:12px;}
.pp-scal .ymon{background:#fff;border:1px solid var(--k-line);border-radius:7px;padding:8px 10px;font-size:12px;}
.pp-scal .ymon h5{margin:0 0 6px;font-size:13px;font-weight:700;}
.pp-scal .ymon .mute{color:var(--k-mute);}
@media (max-width: 767px){ .pp-scal .mgrid > div{min-height:56px;font-size:10.5px;padding:2px;} }
</style>
<div class="pp-scal">
    @include(PortalDefinition::view('_topbar'))
    <div class="wrap">
        <p><a href="{{ $base_url }}"><i class="fa fa-angle-left"></i> {{ $s('page_title') }}（{{ $project_name }}）</a></p>
        <div class="bar">
            <h3 style="margin:0;font-size:18px;"><i class="fa fa-calendar"></i> {{ $s('cal_title') }}</h3>
            <a href="{{ $base_url }}/calendar?view={{ $view }}&ym={{ $prev }}">«</a>
            <b>{{ $label }}</b>
            <a href="{{ $base_url }}/calendar?view={{ $view }}&ym={{ $next }}">»</a>
            <span class="r">
                <a href="{{ $base_url }}/calendar?view=month&ym={{ substr($from, 0, 7) }}" @if($view === 'month') style="font-weight:700;" @endif>{{ $s('cal_month') }}</a> ・
                <a href="{{ $base_url }}/calendar?view=year&ym={{ substr($from, 0, 4) }}" @if($view === 'year') style="font-weight:700;" @endif>{{ $s('cal_year') }}</a>
                @if($admin) ・ <a href="{{ $company_url }}"><i class="fa fa-cog"></i> {{ PortalDefinition::trans('cal.page_title') }}</a>@endif
            </span>
        </div>

        @if($view === 'month')
        @php
            $m = $months[0];
            $lead = intval($m->format('N')) - 1;
            $days = intval($m->format('t'));
            $cells = (int) (ceil(($lead + $days) / 7) * 7);
        @endphp
        <div class="mgrid">
            @foreach($wdays as $w)<div class="hd">{{ $w }}</div>@endforeach
            @for($i = 0; $i < $cells; $i++)
                @php
                    $date = $m->modify(($i - $lead) . ' day')->format('Y-m-d');
                    $in = $i >= $lead && $i < $lead + $days;
                @endphp
                <div class="{{ !$in ? 'out' : '' }} {{ $in && isset($off[$date]) ? 'off' : '' }} {{ $date === $today ? 'today' : '' }}">
                    @if($in)
                    <span class="d">{{ intval(substr($date, 8)) }}</span>
                    @if(!empty($off[$date]))<span class="lbl">{{ $off[$date] }}</span>@endif
                    @foreach($events[$date] ?? [] as $e)<span class="ev {{ $e['moved'] ? 'moved' : '' }}" title="{{ $e['name'] }}">{{ $e['name'] }}</span>@endforeach
                    @endif
                </div>
            @endfor
        </div>
        @else
        <div class="ygrid">
            @foreach($months as $m)
            @php $ym = $m->format('Y-m'); $list = array_filter($events, function ($v, $k) use ($ym) { return substr($k, 0, 7) === $ym; }, ARRAY_FILTER_USE_BOTH); ksort($list); @endphp
            <div class="ymon">
                <h5><a href="{{ $base_url }}/calendar?view=month&ym={{ $ym }}">{{ intval($m->format('n')) }} 月</a></h5>
                @forelse($list as $date => $evs)
                    @foreach($evs as $e)<div>{{ intval(substr($date, 8)) }} 日　{{ $e['name'] }}@if($e['moved']) <span class="mute">（{{ $s('moved') }}）</span>@endif</div>@endforeach
                @empty
                    <div class="mute">—</div>
                @endforelse
            </div>
            @endforeach
        </div>
        @endif
    </div>
</div>
