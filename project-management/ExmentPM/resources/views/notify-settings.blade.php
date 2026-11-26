{{-- How the notices reach this person (ISS02863, ISS02931): PortalNotifySettingsTrait@notifySettings --}}
@php
    use App\Plugins\ExmentPM\PortalDefinition;

    $n = function ($key, $args = []) {
        return PortalDefinition::trans('nset.' . $key, $args);
    };
    $evs = (array) ($prefs['events'] ?? []);
    $muted = (array) ($prefs['projects'] ?? []);
    $quiet = is_array($prefs['quiet'] ?? null) ? $prefs['quiet'] : null;
    $open = !empty($evs) || !empty($muted) || $quiet !== null;
@endphp
<style>
.pp-ns{--n-line:#dfe5ea;--n-mute:#8a97a3;--n-accent:var(--bl-accent,#4cae93);color:#33434f;}
.pp-ns .wrap{padding:12px 16px;max-width:760px;}
.pp-ns .box2{background:#fff;border:1px solid var(--n-line);border-radius:7px;padding:14px 16px;margin-bottom:14px;}
.pp-ns h4{margin:0 0 6px;font-size:15px;font-weight:700;}
.pp-ns .mute{color:var(--n-mute);font-size:12px;}
.pp-ns .choice{display:flex;flex-wrap:wrap;gap:8px;margin-top:8px;}
.pp-ns .choice label{display:flex;align-items:center;gap:6px;border:1px solid var(--n-line);border-radius:6px;padding:8px 12px;font-weight:400;cursor:pointer;margin:0;min-height:40px;}
.pp-ns .choice input:checked + span{font-weight:700;color:var(--n-accent);}
.pp-ns details{margin-top:4px;}
.pp-ns summary{cursor:pointer;font-weight:700;padding:6px 0;}
.pp-ns table{border-collapse:collapse;width:100%;font-size:13px;margin:6px 0 4px;}
.pp-ns td,.pp-ns th{border-bottom:1px solid var(--n-line);padding:6px 8px;text-align:left;}
.pp-ns th{font-size:12px;color:var(--n-mute);font-weight:700;}
.pp-ns .ok{background:#e8f6f0;border:1px solid var(--n-accent);border-radius:6px;padding:8px 12px;margin-bottom:12px;}
.pp-ns input[type=time]{border:1px solid var(--n-line);border-radius:5px;padding:3px 6px;}
.pp-ns .plist{max-height:260px;overflow:auto;}
</style>
<div class="pp-ns">
    @include(PortalDefinition::view('_topbar'))
    <div class="wrap">
        <h3 style="margin:4px 0 6px;font-size:18px;"><i class="fa fa-sliders"></i> {{ $n('title') }}</h3>
        <p class="mute">{{ $n('lead') }}</p>
        @if($saved)<div class="ok">{{ $n('saved') }}</div>@endif
        <form method="post" action="{{ $save_url }}">
            <input type="hidden" name="_token" value="{{ csrf_token() }}">
            <div class="box2">
                <h4>{{ $n('main') }}</h4>
                <div class="mute">{{ $n('main_help') }}</div>
                <div class="choice">
                    @foreach($choices as $c)
                    <label><input type="radio" name="notify_by" value="{{ $c }}" @if($choice === $c) checked @endif> <span>{{ PortalDefinition::trans('teams.choice_' . $c) }}</span></label>
                    @endforeach
                </div>
            </div>

            @if(!empty($digest))
            {{-- the morning digest (ISS02939): off unless the person asks for it --}}
            <div class="box2">
                <h4>{{ $n('digest') }}</h4>
                <div class="mute">{{ $n('digest_help') }}</div>
                <p style="margin-top:6px;">
                    <label style="font-weight:400;"><input type="checkbox" name="digest[on]" value="1" @if($digest['on']) checked @endif> {{ $n('digest_on') }}</label>
                    <input type="time" name="digest[time]" value="{{ $digest['time'] }}" aria-label="{{ $n('digest_time') }}">
                </p>
            </div>
            @endif

            <div class="box2">
                <details @if($open) open @endif>
                    <summary>{{ $n('details') }}</summary>
                    <h4 style="margin-top:10px;">{{ $n('by_event') }}</h4>
                    <div class="mute">{{ $n('by_event_help') }}</div>
                    <table>
                        <tr><th>{{ $n('col_event') }}</th><th>{{ $n('col_own') }}</th><th>{{ $n('col_mail') }}</th>@if($teams)<th>Teams</th>@endif</tr>
                        @foreach($events as $e)
                        @php $own = isset($evs[$e]) && is_array($evs[$e]); @endphp
                        <tr>
                            <td>{{ $n('ev_' . $e) }}</td>
                            <td><input type="checkbox" name="events[{{ $e }}][own]" value="1" @if($own) checked @endif aria-label="{{ $n('col_own') }}"></td>
                            <td><input type="checkbox" name="events[{{ $e }}][mail]" value="1" @if($own && !empty($evs[$e]['mail'])) checked @endif aria-label="{{ $n('col_mail') }}"></td>
                            @if($teams)<td><input type="checkbox" name="events[{{ $e }}][teams]" value="1" @if($own && !empty($evs[$e]['teams'])) checked @endif aria-label="Teams"></td>@endif
                        </tr>
                        @endforeach
                    </table>

                    <h4 style="margin-top:14px;">{{ $n('by_project') }}</h4>
                    <div class="mute">{{ $n('by_project_help') }}</div>
                    <div class="plist">
                        <table>
                            @forelse($projects as $p)
                            <tr><td>{{ $p['key'] !== '' ? $p['key'] . ' ' : '' }}{{ $p['name'] }}</td>
                                <td style="width:140px;"><label style="font-weight:400;margin:0;"><input type="checkbox" name="projects[{{ $p['id'] }}]" value="mute" @if(($muted[$p['id']] ?? '') === 'mute') checked @endif> {{ $n('mute') }}</label></td></tr>
                            @empty
                            <tr><td class="mute">{{ $n('no_projects') }}</td></tr>
                            @endforelse
                        </table>
                    </div>

                    <h4 style="margin-top:14px;">{{ $n('quiet') }}</h4>
                    <div class="mute">{{ $n('quiet_help') }}</div>
                    <p style="margin-top:6px;">
                        <label style="font-weight:400;"><input type="checkbox" name="quiet[on]" value="1" @if($quiet) checked @endif> {{ $n('quiet_on') }}</label>
                        <input type="time" name="quiet[from]" value="{{ $quiet['from'] ?? '22:00' }}"> 〜 <input type="time" name="quiet[to]" value="{{ $quiet['to'] ?? '07:00' }}">
                    </p>
                </details>
            </div>
            <button type="submit" class="btn btn-primary">{{ $n('save') }}</button>
        </form>
    </div>
</div>
