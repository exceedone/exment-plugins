@php
    use App\Plugins\ExmentPM\PortalDefinition;

    $r = function ($key, $args = []) {
        return PortalDefinition::trans('rank.' . $key, $args);
    };
    $medals = [1 => '🥇', 2 => '🥈', 3 => '🥉'];
    // Olympic order: 2nd on the left, 1st in the middle, 3rd on the right.
    $byRank = [];
    foreach ($podium as $p) {
        $byRank[$p['rank']][] = $p;
    }
    $stand = [];
    foreach ([2, 1, 3] as $place) {
        foreach ($byRank[$place] ?? [] as $p) {
            $stand[] = $p;
        }
    }
@endphp
<style>
.pp-rank{--d-line:#dfe5ea;--d-text:#33434f;--d-sub:#8a97a3;--d-accent:#4cae93;--d-accent-d:#3d9a80;--d-chip:#eef3f6;
    color:var(--d-text);font-size:13px;}
.pp-rank a{color:inherit;text-decoration:none;}
.pp-rank .pp-rk-panel{background:#fff;border:1px solid var(--d-line);border-radius:8px;margin-bottom:16px;overflow:hidden;}
.pp-rank .pp-rk-head{display:flex;align-items:center;flex-wrap:wrap;gap:10px;padding:12px 16px;border-bottom:1px solid var(--d-line);background:#fbfcfd;}
.pp-rank .pp-rk-title{font-size:16px;font-weight:700;margin-right:6px;}
.pp-rank .pp-rk-seg{display:inline-flex;border:1px solid var(--d-line);border-radius:6px;overflow:hidden;}
.pp-rank .pp-rk-seg a{padding:4px 12px;border-left:1px solid var(--d-line);}
.pp-rank .pp-rk-seg a:first-child{border-left:0;}
.pp-rank .pp-rk-seg a.on{background:var(--d-accent);color:#fff;font-weight:700;}
.pp-rank .pp-rk-nav{display:inline-flex;align-items:center;gap:8px;}
.pp-rank .pp-rk-nav a,.pp-rank .pp-rk-nav span.off{padding:3px 9px;border:1px solid var(--d-line);border-radius:6px;}
.pp-rank .pp-rk-nav span.off{color:#c3ccd4;}
.pp-rank .pp-rk-nav b{min-width:150px;text-align:center;}
.pp-rank .pp-rk-pj{margin-left:auto;}
.pp-rank .pp-rk-pj select{padding:3px 6px;border:1px solid var(--d-line);border-radius:6px;max-width:260px;}
.pp-rank .pp-rk-cats{display:flex;flex-wrap:wrap;gap:6px;padding:10px 16px;border-bottom:1px solid var(--d-line);}
.pp-rank .pp-rk-cats a{padding:4px 11px;border-radius:14px;background:var(--d-chip);}
.pp-rank .pp-rk-cats a.on{background:var(--d-accent-d);color:#fff;font-weight:700;}
.pp-rank .pp-rk-help{padding:8px 16px 0;color:var(--d-sub);font-size:12px;}
.pp-rank .pp-podium{display:flex;justify-content:center;align-items:flex-end;gap:14px;padding:22px 16px 0;flex-wrap:wrap;}
.pp-rank .pp-pd{display:flex;flex-direction:column;align-items:center;width:150px;}
.pp-rank .pp-pd .medal{font-size:34px;line-height:1;}
.pp-rank .pp-pd .av{width:56px;height:56px;border-radius:50%;margin:6px 0;display:flex;align-items:center;justify-content:center;
    color:#fff;font-weight:700;font-size:18px;overflow:hidden;background-size:cover;background-position:center;border:3px solid #fff;box-shadow:0 1px 4px rgba(0,0,0,.18);}
.pp-rank .pp-pd .nm{font-weight:700;text-align:center;overflow-wrap:anywhere;}
.pp-rank .pp-pd .sc{color:var(--d-sub);font-size:12px;margin-bottom:6px;}
.pp-rank .pp-pd .base{width:100%;border-radius:6px 6px 0 0;display:flex;align-items:flex-start;justify-content:center;
    padding-top:8px;font-size:22px;font-weight:800;color:#fff;}
.pp-rank .pp-pd.r1 .base{height:110px;background:linear-gradient(#f7c948,#e0a800);}
.pp-rank .pp-pd.r2 .base{height:80px;background:linear-gradient(#cfd8dc,#9aa7ad);}
.pp-rank .pp-pd.r3 .base{height:60px;background:linear-gradient(#e3a46b,#b87333);}
.pp-rank .pp-rk-me{margin:14px 16px 0;padding:8px 12px;border-radius:6px;background:#f2f8f5;color:var(--d-accent-d);font-weight:700;}
.pp-rank .pp-rk-list{margin:12px 0 0;}
.pp-rank .pp-rk-row{display:flex;align-items:center;gap:10px;padding:7px 16px;border-top:1px solid #eef2f5;}
.pp-rank .pp-rk-row.me{background:#f2f8f5;}
.pp-rank .pp-rk-row .no{width:34px;text-align:right;font-weight:700;color:var(--d-sub);}
.pp-rank .pp-rk-row .av{width:26px;height:26px;border-radius:50%;flex:none;display:flex;align-items:center;justify-content:center;
    color:#fff;font-size:10px;font-weight:700;overflow:hidden;background-size:cover;background-position:center;}
.pp-rank .pp-rk-row .nm{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.pp-rank .pp-rk-row .sc{font-weight:700;}
.pp-rank .pp-rk-empty{padding:30px 16px;text-align:center;color:var(--d-sub);}
@media (max-width:640px){.pp-rank .pp-pd{width:30%;}.pp-rank .pp-rk-pj{margin-left:0;}}
</style>

<div class="pp-dash pp-rank">
    @include(PortalDefinition::view('_topbar'))

    <div class="pp-rk-panel">
        <div class="pp-rk-head">
            <span class="pp-rk-title"><i class="fa fa-trophy"></i> {{ $r('title') }}</span>
            <span class="pp-rk-seg">
                @foreach (['day', 'week', 'month'] as $p)
                    <a class="{{ $period === $p ? 'on' : '' }}" href="{{ $period_urls[$p] }}">{{ $r('period_' . $p) }}</a>
                @endforeach
            </span>
            <span class="pp-rk-nav">
                <a href="{{ $prev_url }}" title="{{ $r('prev') }}">&larr; {{ $r('prev') }}</a>
                <b>{{ $label }}</b>
                @if ($next_url !== '')
                    <a href="{{ $next_url }}" title="{{ $r('next') }}">{{ $r('next') }} &rarr;</a>
                @else
                    <span class="off">{{ $r('next') }} &rarr;</span>
                @endif
            </span>
            <span class="pp-rk-pj">
                <select onchange="location.href={{ json_encode($project_url) }} + (this.value ? '&pj=' + encodeURIComponent(this.value) : '');" aria-label="{{ $r('project') }}">
                    <option value="">{{ $r('all_projects') }}</option>
                    @foreach ($projects as $id => $name)
                        <option value="{{ $id }}" {{ (string) $project === (string) $id ? 'selected' : '' }}>{{ $name }}</option>
                    @endforeach
                </select>
            </span>
        </div>

        <div class="pp-rk-cats">
            @foreach ($categories as $c)
                <a class="{{ $category === $c ? 'on' : '' }}" href="{{ $category_urls[$c] }}">{{ $r('cat_' . $c) }}</a>
            @endforeach
        </div>
        <div class="pp-rk-help">
            {{ $r('help_' . $category, $weights) }}
        </div>

        @if (empty($rows))
            <div class="pp-rk-empty">{{ $r('empty') }}</div>
        @else
            <div class="pp-podium">
                @foreach ($stand as $p)
                    <div class="pp-pd r{{ $p['rank'] }}">
                        <div class="medal">{{ $medals[$p['rank']] }}</div>
                        @if (!empty($p['user']['a']))
                            <div class="av" style="background-image:url('{{ $p['user']['a'] }}')"></div>
                        @else
                            <div class="av" style="background:{{ $p['user']['c'] }}">{{ $p['user']['i'] }}</div>
                        @endif
                        <div class="nm">{{ $p['user']['n'] }}</div>
                        <div class="sc">{{ $r('score', ['n' => $p['score']]) }}</div>
                        <div class="base">{{ $p['rank'] }}</div>
                    </div>
                @endforeach
            </div>

            <div class="pp-rk-me">
                @if ($mine)
                    {{ $r('you_rank', ['rank' => $mine['rank'], 'n' => $mine['score'], 'all' => $total_people]) }}
                @else
                    {{ $r('you_none') }}
                @endif
            </div>

            <div class="pp-rk-list">
                @foreach ($rows as $row)
                    @if ($row['rank'] > 3)
                        <div class="pp-rk-row {{ $mine && $mine['id'] === $row['id'] ? 'me' : '' }}">
                            <span class="no">{{ $row['rank'] }}</span>
                            @if (!empty($row['user']['a']))
                                <span class="av" style="background-image:url('{{ $row['user']['a'] }}')"></span>
                            @else
                                <span class="av" style="background:{{ $row['user']['c'] }}">{{ $row['user']['i'] }}</span>
                            @endif
                            <span class="nm">{{ $row['user']['n'] }}</span>
                            <span class="sc">{{ $r('score', ['n' => $row['score']]) }}</span>
                        </div>
                    @endif
                @endforeach
            </div>
        @endif
    </div>
</div>
