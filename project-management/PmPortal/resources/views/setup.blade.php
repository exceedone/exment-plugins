@php
use App\Plugins\PmPortal\PortalDefinition as PD;

$badge = function ($kind, $label) {
    $colour = $kind === 'ok' ? '#4caf93' : ($kind === 'conflict' ? '#d9534f' : '#e0a800');
    return '<span style="display:inline-block;background:' . $colour
        . ';color:#fff;border-radius:20px;padding:2px 10px;font-size:11px;font-weight:700;">'
        . e($label) . '</span>';
};
@endphp

<div class="box card p-3" id="pp-setup">
    <div class="alert alert-info" style="margin-bottom:18px;">
        <i class="fa fa-info-circle"></i> {{ PD::trans('setup.lead') }}
    </div>

    @if(!$is_admin)
    {{-- Only an administrator can run the setup, and only they are shown
         what it would create: the preflight lists every table, menu, role
         group and notification name the install already uses. --}}
    <div class="alert alert-warning">
        <i class="fa fa-lock"></i> {{ PD::trans('setup.not_admin') }}
    </div>
    @else
    @if(!empty($failed_at))
    <div class="alert alert-danger" id="pp-setup-failed">
        <i class="fa fa-exclamation-circle"></i> {{ PD::trans('setup.failed_note', ['time' => $failed_at]) }}
    </div>
    @endif

    <form method="post" action="{{ $action }}" id="pp-setup-form">
        {{ csrf_field() }}

        <div class="form-group">
            <label for="pp-prefix" style="font-weight:700;">{{ PD::trans('setup.prefix_label') }}</label>
            <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;max-width:640px;">
                <input type="text" class="form-control" id="pp-prefix" name="prefix" style="max-width:340px;"
                       value="{{ $prefix }}" autocomplete="off" spellcheck="false"
                       placeholder="{{ PD::trans('setup.prefix_placeholder') }}">
                @if($suggestion !== '')
                <button type="button" class="btn btn-default" id="pp-apply-suggestion" data-prefix="{{ $suggestion }}">
                    <i class="fa fa-magic"></i>
                    {{ PD::trans('setup.apply_suggestion', ['prefix' => $suggestion]) }}
                </button>
                @endif
            </div>
            <p class="help-block" style="margin-bottom:0;">{{ PD::trans('setup.prefix_help') }}</p>
        </div>

        <div id="pp-setup-summary" style="margin:14px 0;"></div>

        <table class="table table-bordered" style="background:#fff;margin-bottom:16px;">
            <thead>
                <tr style="background:#f5f7f9;">
                    <th style="width:26%;">{{ PD::trans('setup.col_table') }}</th>
                    <th style="width:30%;">{{ PD::trans('setup.col_target') }}</th>
                    <th style="width:10%;text-align:right;">{{ PD::trans('setup.col_columns') }}</th>
                    <th style="width:34%;">{{ PD::trans('setup.col_status') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($tables as $table)
                <tr data-pp-row="{{ $table['template_name'] }}"
                    @if(!$table['valid']) style="background:#fff8e6;"
                    @elseif($table['exists']) style="background:#fdecea;" @endif>
                    <td>
                        <div style="font-weight:700;">{{ $table['view_name'] }}</div>
                        <code style="color:#6b7a88;">{{ $table['template_name'] }}</code>
                    </td>
                    <td><code data-pp-target style="font-size:13px;">{{ $table['name'] }}</code></td>
                    <td style="text-align:right;">{{ $table['column_count'] }}</td>
                    <td data-pp-status>
                        @if(!$table['valid'])
                        {!! $badge('invalid', PD::trans('setup.status_invalid')) !!}
                        @elseif($table['exists'])
                        {!! $badge('conflict', PD::trans('setup.status_conflict')) !!}
                        @else
                        {!! $badge('ok', PD::trans('setup.status_free')) !!}
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div id="pp-setup-reserved" style="margin-bottom:16px;" hidden>
            <div class="alert alert-warning" style="margin-bottom:8px;">
                <i class="fa fa-exclamation-triangle"></i> {{ PD::trans('setup.reserved_lead') }}
            </div>
            <table class="table table-bordered" style="background:#fff;margin-bottom:0;">
                <thead>
                    <tr style="background:#f5f7f9;">
                        <th style="width:26%;">{{ PD::trans('setup.col_kind') }}</th>
                        <th>{{ PD::trans('setup.col_name') }}</th>
                    </tr>
                </thead>
                <tbody id="pp-setup-reserved-body"></tbody>
            </table>
        </div>

        <button type="submit" class="btn btn-primary" id="pp-setup-submit">
            <i class="fa fa-download"></i> {{ PD::trans('setup.install') }}
        </button>
    </form>
    @endif
</div>

@if($is_admin)
<script>
(function () {
    var root = document.getElementById('pp-setup');
    if (!root) { return; }

    var existing = @json(array_fill_keys($existing, true));
    var names = @json(collect($tables)->pluck('template_name')->all());
    var maxLength = {{ (int) $max_length }};
    var isAdmin = {{ $is_admin ? 'true' : 'false' }};
    var text = {
        free: @json(PD::trans('setup.status_free')),
        conflict: @json(PD::trans('setup.status_conflict')),
        invalid: @json(PD::trans('setup.status_invalid')),
        okAll: @json(PD::trans('setup.summary_ok')),
        conflictAll: @json(PD::trans('setup.summary_conflict')),
        invalidAll: @json(PD::trans('setup.summary_invalid'))
    };

    // Names outside the table namespace. Kept as the part that does not
    // change, so a prefix typed here can be re-checked without the server.
    var reserved = @json(collect($reserved)->map(function ($row) {
        return ['kind' => $row['kind'], 'suffix' => $row['suffix']];
    })->all());
    var taken = @json($taken);
    var kindText = {
        menu: @json(PD::trans('setup.kind_menu')),
        role: @json(PD::trans('setup.kind_role')),
        notify: @json(PD::trans('setup.kind_notify'))
    };

    var input = document.getElementById('pp-prefix');
    var submit = document.getElementById('pp-setup-submit');
    var summary = document.getElementById('pp-setup-summary');
    var reservedBox = document.getElementById('pp-setup-reserved');
    var reservedBody = document.getElementById('pp-setup-reserved-body');

    // Mirrors Define::RULES_REGEX_SYSTEM_NAME: has to start with a letter, may
    // hold hyphens and underscores, must not end with one. The server checks
    // the same thing again before importing.
    var nameRule = /^(?=[a-zA-Z])(?!.*[-_]$)[-_a-zA-Z0-9]+$/;

    function badge(kind, label) {
        var colour = kind === 'ok' ? '#4caf93' : (kind === 'conflict' ? '#d9534f' : '#e0a800');
        var span = document.createElement('span');
        span.setAttribute('style', 'display:inline-block;background:' + colour +
            ';color:#fff;border-radius:20px;padding:2px 10px;font-size:11px;font-weight:700;');
        span.textContent = label;
        return span;
    }

    // Everything this install already answers to under the typed prefix.
    // Listed, never blocked: the import leaves an existing row alone, so the
    // portal joins it rather than replacing it, and that is worth seeing.
    function renderReserved(prefix) {
        reservedBody.innerHTML = '';
        var hits = 0;

        reserved.forEach(function (row) {
            var name = prefix + row.suffix;
            if ((taken[row.kind] || []).indexOf(name) < 0) { return; }
            hits++;

            var tr = document.createElement('tr');
            var kind = document.createElement('td');
            kind.textContent = kindText[row.kind] || row.kind;
            var cell = document.createElement('td');
            var code = document.createElement('code');
            code.textContent = name;
            cell.appendChild(code);
            tr.appendChild(kind);
            tr.appendChild(cell);
            reservedBody.appendChild(tr);
        });

        reservedBox.hidden = hits === 0;
    }

    function render() {
        var prefix = (input.value || '').trim();
        renderReserved(prefix);
        var conflicts = 0;
        var invalids = 0;

        names.forEach(function (name) {
            var row = root.querySelector('[data-pp-row="' + name + '"]');
            if (!row) { return; }

            var target = prefix + name;
            var valid = target.length <= maxLength && nameRule.test(target);
            var clash = Object.prototype.hasOwnProperty.call(existing, target);

            row.querySelector('[data-pp-target]').textContent = target;

            var cell = row.querySelector('[data-pp-status]');
            cell.innerHTML = '';
            if (!valid) {
                invalids++;
                cell.appendChild(badge('invalid', text.invalid));
                row.style.background = '#fff8e6';
            } else if (clash) {
                conflicts++;
                cell.appendChild(badge('conflict', text.conflict));
                row.style.background = '#fdecea';
            } else {
                cell.appendChild(badge('ok', text.free));
                row.style.background = '';
            }
        });

        if (invalids > 0) {
            summary.className = 'alert alert-warning';
            summary.textContent = text.invalidAll.replace('%s', invalids);
        } else if (conflicts > 0) {
            summary.className = 'alert alert-danger';
            summary.textContent = text.conflictAll.replace('%s', conflicts);
        } else {
            summary.className = 'alert alert-success';
            summary.textContent = text.okAll.replace('%s', names.length);
        }

        submit.disabled = invalids > 0 || conflicts > 0 || !isAdmin;
    }

    input.addEventListener('input', render);

    var apply = document.getElementById('pp-apply-suggestion');
    if (apply) {
        apply.addEventListener('click', function () {
            input.value = apply.getAttribute('data-prefix');
            input.focus();
            render();
        });
    }

    // One import per click. A second submit while the first is still
    // creating tables would only be turned away as busy by the server, but
    // the page would then show that instead of the first one's result.
    var form = document.getElementById('pp-setup-form');
    var sent = false;
    form.addEventListener('submit', function (event) {
        if (sent) { event.preventDefault(); return; }
        sent = true;
        submit.disabled = true;
    });
    // A page brought back from the browser's history cache comes back with
    // the button still disabled; start over from what the form holds.
    function onShow(event) {
        if (!document.body.contains(root)) { window.removeEventListener('pageshow', onShow); return; }
        if (event.persisted) { sent = false; render(); }
    }
    window.addEventListener('pageshow', onShow);

    render();
})();
</script>
@endif
