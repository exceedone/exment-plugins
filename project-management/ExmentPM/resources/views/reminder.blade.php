{{-- The reminders of a project (ISS00432, stage 1b): PortalReminderPageTrait@reminderPage --}}
@php
    use App\Plugins\ExmentPM\PortalDefinition;

    $r = function ($key, $args = []) {
        return PortalDefinition::trans('remind.' . $key, $args);
    };
    $types = ['slack' => 'Slack', 'teams' => 'Microsoft Teams'];
    if ($line_works) {
        $types['line_works'] = 'LINE WORKS';
    }
    $toOpts = ['assignees', 'sub_assignees', 'managers', 'watchers'];
@endphp
<style>
.pp-rm{--r-line:#dfe5ea;--r-mute:#8a97a3;--r-accent:#4cae93;--r-red:#e5544b;color:#33434f;}
.pp-rm .pp-rm-wrap{padding:12px 16px;max-width:820px;}
.pp-rm .box2{background:#fff;border:1px solid var(--r-line);border-radius:7px;padding:16px;margin-bottom:14px;}
.pp-rm h4{margin:0 0 10px;font-size:15px;font-weight:700;}
.pp-rm .f{margin-bottom:12px;}
.pp-rm .f label.h{display:block;font-weight:700;font-size:12.5px;margin-bottom:4px;}
.pp-rm .f .help{color:var(--r-mute);font-size:12px;margin-top:3px;}
.pp-rm input[type=text],.pp-rm input[type=url],.pp-rm select{border:1px solid var(--r-line);border-radius:5px;padding:5px 8px;font-size:13px;min-height:32px;}
.pp-rm .ck{display:inline-flex;align-items:center;gap:6px;margin-right:14px;font-weight:400;}
.pp-rm .ch{display:grid;grid-template-columns:150px 1fr 140px auto;gap:8px;align-items:center;margin-bottom:8px;}
.pp-rm .ch .masked{color:var(--r-mute);font-size:12px;}
.pp-rm .ok{background:#e8f6f0;border:1px solid var(--r-accent);border-radius:6px;padding:8px 12px;margin-bottom:12px;}
.pp-rm .ng{background:#fde8e6;border:1px solid var(--r-red);border-radius:6px;padding:8px 12px;margin-bottom:12px;}
.pp-rm .site{color:var(--r-mute);font-size:12px;}
@media (max-width: 767px){ .pp-rm .ch{grid-template-columns:1fr;} }
</style>
<div class="pp-rm">
    @include(PortalDefinition::view('_topbar'))
    <div class="pp-rm-wrap">
        <p><a href="{{ $project_url }}#settings"><i class="fa fa-angle-left"></i> {{ $project_name }}</a></p>
        <h3 style="margin:4px 0 12px;font-size:18px;"><i class="fa fa-bell-o"></i> {{ $r('page_title') }}</h3>
        @if($saved)<div class="ok">{{ $r('saved') }}</div>@endif
        @if(!empty($errors_list))<div class="ng">@foreach($errors_list as $e)<div>{{ $e }}</div>@endforeach</div>@endif

        <form method="post" action="{{ $save_url }}">
            <input type="hidden" name="_token" value="{{ csrf_token() }}">
            <div class="box2">
                <div class="f"><label class="ck"><input type="checkbox" name="enabled" value="1" @if($enabled) checked @endif> {{ $r('project_on') }}</label></div>
                <div class="f">
                    <label class="ck"><input type="radio" name="mode" value="site" @if($use_site) checked @endif> {{ $r('mode_site') }}</label>
                    <label class="ck"><input type="radio" name="mode" value="own" @if(!$use_site) checked @endif> {{ $r('mode_own') }}</label>
                    <div class="site">{{ $r('site_now', ['days' => implode(',', $site['days']) ?: '—', 'time' => $site['time']]) }}</div>
                </div>
            </div>

            <div class="box2" id="pp-rm-own">
                <h4>{{ $r('own_header') }}</h4>
                <div class="f"><label class="h">{{ $r('days') }}</label><input type="text" name="days" value="{{ implode(',', $settings['days']) }}" style="width:140px;"><div class="help">{{ $r('days_help') }}</div></div>
                <div class="f"><label class="ck"><input type="checkbox" name="business" value="1" @if($settings['business']) checked @endif> {{ $r('business') }}</label>
                    <label class="ck"><input type="checkbox" name="today" value="1" @if($settings['today']) checked @endif> {{ $r('today') }}</label>
                    <label class="ck"><input type="checkbox" name="overdue" value="1" @if($settings['overdue']) checked @endif> {{ $r('overdue') }}</label></div>
                <div class="f"><label class="h">{{ $r('overdue_max') }}</label><input type="text" name="overdue_max" value="{{ $settings['overdue_max'] }}" style="width:80px;"></div>
                <div class="f"><label class="h">{{ $r('time') }}</label><input type="text" name="time" value="{{ $settings['time'] }}" style="width:80px;"></div>
                <div class="f"><label class="h">{{ $r('to') }}</label>
                    @foreach($toOpts as $o)<label class="ck"><input type="checkbox" name="to[]" value="{{ $o }}" @if(in_array($o, $settings['to'], true)) checked @endif> {{ $r('to_' . $o) }}</label>@endforeach
                </div>
            </div>

            <div class="box2">
                <h4>{{ $r('channels') }}</h4>
                <p class="site">{{ $r('channels_help') }}</p>
                @php $rows = $channels; while (count($rows) < min($max_channels, count($channels) + 1)) { $rows[] = ['index' => -1, 'type' => '', 'label' => '', 'masked' => '', 'channel' => '']; } @endphp
                @foreach($rows as $n => $c)
                <div class="ch">
                    <input type="hidden" name="channels[{{ $n }}][index]" value="{{ $c['index'] }}">
                    <select name="channels[{{ $n }}][type]" aria-label="{{ $r('ch_type') }}">
                        <option value="">{{ $r('ch_none') }}</option>
                        @foreach($types as $k => $l)<option value="{{ $k }}" @if($c['type'] === $k) selected @endif>{{ $l }}</option>@endforeach
                    </select>
                    <div>
                        <input type="url" name="channels[{{ $n }}][url]" value="" autocomplete="off" placeholder="{{ $c['masked'] !== '' ? $r('ch_keep') : $r('ch_url') }}" style="width:100%;">
                        @if($c['masked'] !== '')<div class="masked">{{ $r('ch_saved', ['url' => $c['masked']]) }}</div>@endif
                        @if($line_works)<input type="text" name="channels[{{ $n }}][channel]" value="{{ $c['channel'] }}" placeholder="{{ $r('ch_lw') }}" style="width:100%;margin-top:4px;">@endif
                    </div>
                    <input type="text" name="channels[{{ $n }}][label]" value="{{ $c['label'] }}" placeholder="{{ $r('ch_label') }}">
                    @if($c['index'] >= 0)<label class="ck"><input type="checkbox" name="channels[{{ $n }}][remove]" value="1"> {{ $r('ch_remove') }}</label>@else<span></span>@endif
                </div>
                @endforeach
            </div>

            <button type="submit" class="btn btn-primary">{{ $r('save') }}</button>
        </form>
    </div>
</div>
