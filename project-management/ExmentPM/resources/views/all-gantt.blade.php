{{-- The issues of every project in one gantt, a project per group (ISS00244): PortalAllIssuesTrait@allGantt --}}
@php
    use App\Plugins\ExmentPM\PortalDefinition;
@endphp
<style>
.pp-ag .wrap{padding:12px 16px;}
.pp-ag h3{margin:4px 0 6px;font-size:18px;}
.pp-ag .mute{color:#8a97a3;font-size:12px;margin:0 0 10px;}
</style>
<div class="pp-ag">
    @include(PortalDefinition::view('_topbar'))
    <div class="wrap">
        <h3><i class="fa fa-align-left"></i> {{ PortalDefinition::trans('ag_title') }}</h3>
        <p class="mute">{{ PortalDefinition::trans('ag_help') }}</p>
        {!! $gantt_html !!}
    </div>
</div>
