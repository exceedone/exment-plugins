@php
use App\Plugins\PmPortal\PortalDefinition as PD;
@endphp

<div class="box card p-3" id="pp-settings">
    <style>
        #pp-settings .pp-proj-toolbar { margin-bottom: 14px; display: flex; align-items: center; gap: 16px; }
        #pp-settings .pp-proj-archived { font-weight: 400; margin: 0; color: #6b7a88; cursor: pointer; }
        #pp-settings .pp-proj-archived input { margin-right: 5px; vertical-align: middle; }
        #pp-settings #pp-proj-form { background: #f8fafb; border: 1px solid #dfe6ec; border-radius: 4px; padding: 16px; margin-bottom: 18px; }
        #pp-settings .pp-proj-group { border: 0; margin: 0 0 6px; padding: 0; }
        #pp-settings .pp-proj-group legend { border: 0; border-bottom: 1px solid #e3e9ee; font-size: 13px; font-weight: 700; color: #4a5a68; margin-bottom: 10px; padding-bottom: 4px; width: 100%; }
        #pp-settings .pp-proj-group > .pp-proj-field { display: inline-block; vertical-align: top; width: 320px; margin-right: 18px; }
        #pp-settings .pp-proj-field label { font-weight: 700; }
        #pp-settings .pp-proj-field .help-block { margin: 4px 0 0; font-size: 11px; }
        #pp-settings .pp-proj-field select[multiple] { min-height: 90px; }
        #pp-settings .pp-proj-actions { border-top: 1px solid #e3e9ee; padding-top: 12px; margin-top: 6px; }
    </style>
    @if($schema['needed'])
    <div class="alert alert-warning" id="pp-upgrade-box">
        <h4 style="margin-top:0;"><i class="fa fa-database"></i> {{ PD::trans('upgrade.title') }}</h4>
        <p style="margin-bottom:8px;">{{ PD::trans('upgrade.lead') }}</p>
        <p style="margin-bottom:10px;color:#6b7a88;">
            {{ PD::trans('upgrade.version', ['current' => $schema['current'], 'target' => $schema['target']]) }}
        </p>
        @if($schema['allowed'])
        <p class="text-danger" style="margin-bottom:10px;">
            <i class="fa fa-exclamation-triangle"></i> {{ PD::trans('upgrade.backup_note') }}
        </p>
        <button type="button" class="btn btn-primary" id="pp-upgrade-run">
            <i class="fa fa-play"></i> {{ PD::trans('upgrade.button') }}
        </button>
        @else
        <span class="text-muted"><i class="fa fa-lock"></i> {{ PD::trans('upgrade.not_admin') }}</span>
        @endif
        <div id="pp-upgrade-log" style="margin-top:12px;"></div>
    </div>
    @endif

    <ul class="nav nav-tabs" id="pp-settings-tabs" style="margin-bottom:16px;">
        @if($projectSection)
        <li class="active">
            <a href="#pp-tab-project" data-pp-tab="project">
                <i class="fa {{ $projectSection['icon'] }}"></i> {{ $projectSection['label'] }}
            </a>
        </li>
        @endif
        @foreach($sections as $index => $section)
        <li class="{{ !$projectSection && $index === 0 ? 'active' : '' }}">
            <a href="#pp-tab-{{ $section['kind'] }}" data-pp-tab="{{ $section['kind'] }}">
                <i class="fa {{ $section['icon'] }}"></i> {{ $section['label'] }}
            </a>
        </li>
        @endforeach
    </ul>

    @if($projectSection)
    <section data-pp-panel="project">
        <p class="help-block">{{ $projectSection['hint'] }}</p>

        <div class="pp-proj-toolbar">
            @if($projectSection['creatable'])
            <button type="button" class="btn btn-success" id="pp-proj-new">
                <i class="fa fa-plus"></i> {{ PD::trans('settings.project_new') }}
            </button>
            @endif
            <label class="pp-proj-archived">
                <input type="checkbox" id="pp-proj-show-archived">
                {{ PD::trans('settings.show_archived') }}
            </label>
        </div>

        <div id="pp-proj-form" style="display:none;"></div>

        <table class="table table-bordered" style="background:#fff;">
            <thead>
                <tr style="background:#f5f7f9;">
                    <th style="width:120px;">{{ PD::trans('settings.field.project_code') }}</th>
                    <th>{{ PD::trans('settings.field.project_name') }}</th>
                    <th style="width:200px;">{{ PD::trans('settings.field.parent_project') }}</th>
                    <th style="width:120px;">{{ PD::trans('settings.field.project_status') }}</th>
                    <th style="width:170px;">{{ PD::trans('settings.actions') }}</th>
                </tr>
            </thead>
            <tbody id="pp-proj-rows"></tbody>
        </table>
    </section>
    @endif

    @foreach($sections as $index => $section)
    <section data-pp-panel="{{ $section['kind'] }}" style="{{ !$projectSection && $index === 0 ? '' : 'display:none;' }}">
        <p class="help-block">{{ $section['hint'] }}</p>

        @if($section['scoped'])
            @if(count($projects) > 0)
            <div class="form-group" style="max-width:420px;">
                <label style="font-weight:700;">{{ PD::trans('settings.project') }}</label>
                <select class="form-control" data-pp-project="{{ $section['kind'] }}">
                    @foreach($projects as $project)
                    <option value="{{ $project['id'] }}">{{ $project['name'] }}</option>
                    @endforeach
                </select>
            </div>
            @else
            <div class="alert alert-info">{{ PD::trans('settings.no_projects') }}</div>
            @endif
        @endif

        @if(!$section['editable'])
        <div class="alert alert-info"><i class="fa fa-eye"></i> {{ PD::trans('settings.readonly') }}</div>
        @endif

        <table class="table table-bordered" style="background:#fff;">
            <thead>
                <tr style="background:#f5f7f9;">
                    @foreach($section['fields'] as $field)
                    <th>{{ $field['label'] }}</th>
                    @endforeach
                    <th style="width:150px;">{{ PD::trans('settings.actions') }}</th>
                </tr>
            </thead>
            <tbody data-pp-rows="{{ $section['kind'] }}"></tbody>
            <tfoot data-pp-new="{{ $section['kind'] }}"></tfoot>
        </table>
    </section>
    @endforeach
</div>

@include(\App\Plugins\PmPortal\PortalDefinition::view('_datepicker'))
<script>
(function () {
    var root = document.getElementById('pp-settings');
    if (!root) { return; }

    var sections = @json($sections);
    var projectSpec = @json($projectSection);
    var projects = @json($projects);
    var urls = @json($urls);
    var text = {
        add: @json(PD::trans('settings.add')),
        save: @json(PD::trans('settings.save')),
        remove: @json(PD::trans('settings.delete')),
        saved: @json(PD::trans('settings.saved')),
        deleted: @json(PD::trans('settings.deleted')),
        confirm: @json(PD::trans('settings.confirm_delete')),
        empty: @json(PD::trans('settings.empty')),
        error: @json(PD::trans('settings.error')),
        inUse: @json(PD::trans('settings.in_use')),
        required: @json(PD::trans('settings.required')),
        running: @json(PD::trans('upgrade.running')),
        edit: @json(PD::trans('settings.edit')),
        cancel: @json(PD::trans('settings.cancel')),
        none: @json(PD::trans('settings.none')),
        newProject: @json(PD::trans('settings.project_new')),
        editProject: @json(PD::trans('settings.project_edit')),
        confirmProject: @json(PD::trans('settings.confirm_delete_project')),
        archivedTag: @json(PD::trans('settings.archived_tag')),
        noChange: @json(PD::trans('settings.no_change')),
        stale: @json(PD::trans('settings.stale')),
        upgradeConfirm: @json(PD::trans('upgrade.confirm')),
        upgraded: @json(PD::trans('upgrade.success')),
        reload: @json(PD::trans('upgrade.reload'))
    };
    // The sentences for a failure the portal did not word itself - an expired
    // session, a proxy's error page, no answer at all - picked by HTTP status.
    var httpErrors = @json(PD::httpErrors());
    var token = document.querySelector('meta[name="csrf-token"]');
    token = token ? token.getAttribute('content') : '';

    // Never rejects: a request that got no answer at all comes back as status
    // 0, so every caller words it through failure() like any other.
    function api(method, path, body) {
        var options = {
            method: method,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': token }
        };
        if (body) {
            options.headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(body);
        }
        return fetch(path, options).then(function (res) {
            return res.json().catch(function () { return {}; }).then(function (json) {
                return { ok: res.ok, status: res.status, json: json || {} };
            });
        }).catch(function () {
            return { ok: false, status: 0, json: {} };
        });
    }

    // A stored colour as the #rrggbb a colour input can hold, or '' when it
    // is not a colour at all. #abc is expanded rather than lost to black.
    function hex6(value) {
        var raw = String(value == null ? '' : value).trim().toLowerCase();
        var abc = /^#([0-9a-f])([0-9a-f])([0-9a-f])$/.exec(raw);
        if (abc) { return '#' + abc[1] + abc[1] + abc[2] + abc[2] + abc[3] + abc[3]; }
        return /^#[0-9a-f]{6}$/.test(raw) ? raw : '';
    }

    function notify(message, kind) {
        if (window.toastr && typeof window.toastr[kind === 'error' ? 'error' : 'success'] === 'function') {
            window.toastr[kind === 'error' ? 'error' : 'success'](message);
            return;
        }
        window.alert(message);
    }

    // What a person reads when a call fails, in the order every portal
    // screen follows: the portal's own sentence, then one chosen by the HTTP
    // status, then this page's fallback. The error code, an exception's text
    // and a body the portal did not write never reach the screen - they are
    // English, and a database error carries the SQL.
    function failure(result) {
        var body = (result && result.json) || {};
        var portal = body.ok === false;
        if (portal && body.error === 'in_use') {
            return text.inUse.replace('%s', /^\d+$/.test(String(body.detail)) ? body.detail : '?');
        }
        if (portal && body.fields && typeof body.fields === 'object') {
            var messages = [];
            Object.keys(body.fields).forEach(function (field) {
                [].concat(body.fields[field]).forEach(function (line) {
                    if (typeof line === 'string' && line !== '') { messages.push(line); }
                });
            });
            if (messages.length) { return messages.join(' '); }
        }
        if (portal && typeof body.message === 'string' && body.message !== '') { return body.message; }
        var http = (typeof httpErrors !== 'undefined' && httpErrors) || {};
        var said = http.messages || {};
        var status = result && typeof result.status === 'number' ? result.status : 0;
        var name = (http.status || {})[status] || (status >= 500 ? 'server' : null);
        return (name && said[name]) || text.error || said.other || '';
    }

    // ---- one master list ------------------------------------------------

    function Section(spec) {
        this.spec = spec;
        this.body = root.querySelector('[data-pp-rows="' + spec.kind + '"]');
        this.foot = root.querySelector('[data-pp-new="' + spec.kind + '"]');
        this.select = root.querySelector('[data-pp-project="' + spec.kind + '"]');

        var self = this;
        if (this.select) {
            this.select.addEventListener('change', function () { self.load(); });
        }
        this.renderNew();
        this.load();
    }

    Section.prototype.projectId = function () {
        if (!this.spec.scoped || !this.select) { return null; }
        return this.select.value || null;
    };

    Section.prototype.listUrl = function () {
        var url = urls.lightapi + '/data/' + encodeURIComponent(this.spec.table) + '?per_page=200';
        var id = this.projectId();
        if (id) { url += '&filter[parent_id]=' + encodeURIComponent(id); }
        return url;
    };

    Section.prototype.load = function () {
        var self = this;
        // load() runs after every add, save and delete. Clearing the table up
        // front left it blank for as long as the request took, so the list
        // visibly vanished and came back on each edit. Build the replacement
        // off-screen and swap it in once, and let the newest request win so a
        // slow earlier answer cannot overwrite it.
        var token = (this.loadToken = (this.loadToken || 0) + 1);
        api('GET', this.listUrl()).then(function (result) {
            if (token !== self.loadToken) { return; }
            if (!result.ok) { notify(failure(result), 'error'); return; }
            var rows = (result.json.rows || []).slice();
            var orderField = self.field('order');
            if (orderField) {
                rows.sort(function (a, b) {
                    return (parseInt(a[orderField.name], 10) || 0) - (parseInt(b[orderField.name], 10) || 0);
                });
            }
            var next = document.createDocumentFragment();
            if (!rows.length) {
                var tr = document.createElement('tr');
                var td = document.createElement('td');
                td.colSpan = self.spec.fields.length + 1;
                td.className = 'text-muted';
                td.textContent = text.empty;
                tr.appendChild(td);
                next.appendChild(tr);
            } else {
                rows.forEach(function (row) { next.appendChild(self.renderRow(row)); });
            }
            self.body.innerHTML = '';
            self.body.appendChild(next);
        });
    };

    Section.prototype.field = function (role) {
        for (var i = 0; i < this.spec.fields.length; i++) {
            if (this.spec.fields[i].role === role) { return this.spec.fields[i]; }
        }
        return null;
    };

    Section.prototype.input = function (field, value) {
        var el;
        if (field.type === 'boolean') {
            el = document.createElement('input');
            el.type = 'checkbox';
            el.checked = (value === '1' || value === 1 || value === true);
        } else if (field.type === 'textarea') {
            el = document.createElement('textarea');
            el.className = 'form-control';
            el.rows = 2;
            el.value = value == null ? '' : String(value);
        } else {
            el = document.createElement('input');
            el.className = 'form-control';
            el.type = field.type === 'color' ? 'color' : (field.type === 'number' ? 'number' : 'text');
            // A color input only holds #rrggbb and turns anything else - a
            // short #abc, an empty value - into black, so the value is
            // expanded first and the default decided from the row rather
            // than from the element.
            var raw = value == null ? '' : String(value);
            if (field.type === 'color') { raw = hex6(raw) || '#95a5a6'; }
            el.value = raw;
        }
        el.setAttribute('data-pp-field', field.name);
        // What the row showed, so a save can send only what was changed.
        el.setAttribute('data-pp-initial', field.type === 'boolean' ? (el.checked ? '1' : '0') : el.value);
        el.disabled = !this.spec.editable;
        return el;
    };

    /**
     * The collected values that differ from what the row showed when it was
     * drawn. A save sends only these: writing back every column would undo
     * what someone else changed meanwhile, and would store the colour input's
     * own spelling of a colour nobody touched.
     */
    Section.prototype.changes = function (scope, values) {
        var out = {};
        this.spec.fields.forEach(function (field) {
            var el = scope.querySelector('[data-pp-field="' + field.name + '"]');
            if (!el || !Object.prototype.hasOwnProperty.call(values, field.name)) { return; }
            if (String(values[field.name]) !== el.getAttribute('data-pp-initial')) {
                out[field.name] = values[field.name];
            }
        });
        return out;
    };

    Section.prototype.collect = function (scope) {
        var values = {};
        var missing = false;
        this.spec.fields.forEach(function (field) {
            var el = scope.querySelector('[data-pp-field="' + field.name + '"]');
            if (!el) { return; }
            if (field.type === 'boolean') {
                values[field.name] = el.checked ? '1' : '0';
            } else {
                values[field.name] = el.value;
            }
            if (field.role === 'name' && !String(values[field.name]).trim()) { missing = true; }
        });
        return missing ? null : values;
    };

    Section.prototype.button = function (label, icon, kind) {
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-sm ' + kind;
        button.style.marginRight = '6px';
        button.innerHTML = '<i class="fa ' + icon + '"></i> ';
        button.appendChild(document.createTextNode(label));
        button.disabled = !this.spec.editable;
        return button;
    };

    Section.prototype.renderRow = function (row) {
        var self = this;
        var tr = document.createElement('tr');

        this.spec.fields.forEach(function (field) {
            var td = document.createElement('td');
            td.appendChild(self.input(field, row[field.name]));
            tr.appendChild(td);
        });

        var actions = document.createElement('td');
        var save = this.button(text.save, 'fa-save', 'btn-primary');
        save.addEventListener('click', function () {
            if (save.disabled) { return; }
            var values = self.collect(tr);
            if (!values) { notify(text.required, 'error'); return; }
            var changed = self.changes(tr, values);
            if (!Object.keys(changed).length) { notify(text.noChange, 'success'); return; }
            save.disabled = true;
            // With the version the row was read at: a row someone else saved
            // in between is refused rather than overwritten.
            api('PATCH', urls.lightapi + '/data/' + encodeURIComponent(self.spec.table) + '/' + row.id,
                { value: changed, updated_at: row.updated_at })
                .then(function (result) {
                    save.disabled = false;
                    if (result.status === 409 && result.json && result.json.error === 'stale') {
                        notify(failure(result), 'error');
                        self.load();
                        return;
                    }
                    if (!result.ok) { notify(failure(result), 'error'); return; }
                    // The row now holds what was sent, at a new version; the
                    // next save from this line starts from there.
                    Object.keys(changed).forEach(function (name) {
                        var el = tr.querySelector('[data-pp-field="' + name + '"]');
                        if (el) { el.setAttribute('data-pp-initial', String(changed[name])); }
                    });
                    if (result.json && result.json.updated_at) { row.updated_at = result.json.updated_at; }
                    notify(text.saved, 'success');
                });
        });

        var remove = this.button(text.remove, 'fa-trash', 'btn-danger');
        remove.addEventListener('click', function () {
            if (!window.confirm(text.confirm)) { return; }
            remove.disabled = true;
            api('DELETE', urls.lightapi + '/data/' + encodeURIComponent(self.spec.table) + '/' + row.id)
                .then(function (result) {
                    remove.disabled = false;
                    if (!result.ok) { notify(failure(result), 'error'); return; }
                    notify(text.deleted, 'success');
                    self.load();
                });
        });

        actions.appendChild(save);
        actions.appendChild(remove);
        tr.appendChild(actions);

        return tr;
    };

    Section.prototype.renderNew = function () {
        var self = this;
        this.foot.innerHTML = '';
        if (!this.spec.editable) { return; }

        var tr = document.createElement('tr');
        tr.style.background = '#f8fafb';

        this.spec.fields.forEach(function (field) {
            var td = document.createElement('td');
            td.appendChild(self.input(field, field.role === 'order' ? 0 : ''));
            tr.appendChild(td);
        });

        var actions = document.createElement('td');
        var add = this.button(text.add, 'fa-plus', 'btn-success');
        add.addEventListener('click', function () {
            var values = self.collect(tr);
            if (!values) { notify(text.required, 'error'); return; }

            var body = { value: values };
            var id = self.projectId();
            if (id) {
                var owner = null;
                projects.forEach(function (project) {
                    if (String(project.id) === String(id)) { owner = project; }
                });
                if (owner) {
                    body.parent_id = owner.id;
                    body.parent_type = owner.type;
                }
            }

            add.disabled = true;
            api('POST', urls.lightapi + '/data/' + encodeURIComponent(self.spec.table), body)
                .then(function (result) {
                    add.disabled = false;
                    if (!result.ok) { notify(failure(result), 'error'); return; }
                    notify(text.saved, 'success');
                    self.renderNew();
                    self.load();
                });
        });
        actions.appendChild(add);
        tr.appendChild(actions);

        this.foot.appendChild(tr);
    };

    // ---- projects -------------------------------------------------------

    /**
     * The project editor: a list, and one form that serves both create and
     * edit. Backlog treats a project's settings as a page rather than a grid
     * row because there are twenty-odd fields, and a table of twenty columns
     * is unusable, so the fields are grouped the way Backlog groups them.
     */
    function ProjectEditor(spec) {
        this.spec = spec;
        this.rows = document.getElementById('pp-proj-rows');
        this.form = document.getElementById('pp-proj-form');
        this.showArchived = document.getElementById('pp-proj-show-archived');
        this.lookups = {};
        this.all = [];

        var self = this;
        var create = document.getElementById('pp-proj-new');
        if (create) {
            create.addEventListener('click', function () { self.open(null); });
        }
        if (this.showArchived) {
            this.showArchived.addEventListener('change', function () { self.render(); });
        }
        this.load();
    }

    ProjectEditor.prototype.each = function (callback) {
        this.spec.groups.forEach(function (group) {
            group.fields.forEach(callback);
        });
    };

    ProjectEditor.prototype.load = function () {
        var self = this;
        // Keep the current rows on screen until the answer arrives, for the
        // same reason as Section.load(): this runs after every create and
        // delete, and blanking the table first made the list flash empty.
        var token = (this.loadToken = (this.loadToken || 0) + 1);
        // Drop the cached pickers: load() runs after every create and delete,
        // so keeping them would offer a stale list that is missing the project
        // the user just made.
        this.lookups = {};
        api('GET', urls.lightapi + '/data/' + encodeURIComponent(this.spec.table) + '?per_page=200')
            .then(function (result) {
                if (token !== self.loadToken) { return; }
                if (!result.ok) { notify(failure(result), 'error'); return; }
                self.all = result.json.rows || [];
                self.render();
            });
    };

    ProjectEditor.prototype.isArchived = function (row) {
        var v = row.archived;
        return v === '1' || v === 1 || v === true;
    };

    ProjectEditor.prototype.render = function () {
        var self = this;
        var wanted = this.showArchived && this.showArchived.checked;
        var rows = this.all.filter(function (row) {
            return wanted || !self.isArchived(row);
        });

        this.rows.innerHTML = '';
        if (!rows.length) {
            var tr = document.createElement('tr');
            var td = document.createElement('td');
            td.colSpan = 5;
            td.className = 'text-muted';
            td.textContent = text.empty;
            tr.appendChild(td);
            this.rows.appendChild(tr);
            return;
        }

        rows.forEach(function (row) { self.rows.appendChild(self.renderRow(row)); });
    };

    ProjectEditor.prototype.cell = function (content) {
        var td = document.createElement('td');
        if (content instanceof Node) { td.appendChild(content); }
        else { td.textContent = content == null || content === '' ? '-' : String(content); }
        return td;
    };

    ProjectEditor.prototype.label = function (row, name) {
        // The api adds a resolved label alongside the raw value for select
        // and relation columns; without it the list would show an id.
        var resolved = row[name + '_label'];
        if (resolved != null && resolved !== '') { return resolved; }
        return row[name];
    };

    ProjectEditor.prototype.renderRow = function (row) {
        var self = this;
        var tr = document.createElement('tr');

        var code = document.createElement('code');
        code.textContent = row.project_code || '-';
        tr.appendChild(this.cell(code));

        var nameCell = document.createElement('span');
        nameCell.textContent = row.name || '-';
        if (this.isArchived(row)) {
            var tag = document.createElement('span');
            tag.className = 'label label-default';
            tag.style.marginLeft = '6px';
            tag.textContent = text.archivedTag;
            nameCell.appendChild(tag);
        }
        tr.appendChild(this.cell(nameCell));

        tr.appendChild(this.cell(this.label(row, 'parent_project')));
        tr.appendChild(this.cell(this.label(row, 'status')));

        var actions = document.createElement('td');
        var edit = document.createElement('button');
        edit.type = 'button';
        edit.className = 'btn btn-sm btn-primary';
        edit.style.marginRight = '6px';
        edit.innerHTML = '<i class="fa fa-pencil"></i> ';
        edit.appendChild(document.createTextNode(text.edit));
        edit.addEventListener('click', function () { self.open(row); });
        actions.appendChild(edit);

        if (this.spec.editable) {
            var remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'btn btn-sm btn-danger';
            remove.innerHTML = '<i class="fa fa-trash"></i> ';
            remove.appendChild(document.createTextNode(text.remove));
            remove.addEventListener('click', function () {
                if (!window.confirm(text.confirmProject)) { return; }
                remove.disabled = true;
                api('DELETE', urls.lightapi + '/data/' + encodeURIComponent(self.spec.table) + '/' + row.id)
                    .then(function (result) {
                        remove.disabled = false;
                        if (!result.ok) { notify(failure(result), 'error'); return; }
                        notify(text.deleted, 'success');
                        self.load();
                    });
            });
            actions.appendChild(remove);
        }

        tr.appendChild(actions);
        return tr;
    };

    /**
     * Fetch the choices for a lookup field once and reuse them. A project
     * cannot be its own parent, so the row being edited is dropped from its
     * own parent list rather than left there to be refused on save.
     */
    ProjectEditor.prototype.lookup = function (table) {
        if (!this.lookups[table]) {
            var self = this;
            // Store the promise, not the result: manager, members and viewers
            // all read the same table, and three parallel requests for one
            // list is wasteful.
            this.lookups[table] = api('GET', urls.lightapi + '/lookup/' + encodeURIComponent(table) + '?limit=50')
                .then(function (result) {
                    return result.ok ? (result.json.results || []) : [];
                });
        }
        return this.lookups[table];
    };

    ProjectEditor.prototype.control = function (field, row) {
        var value = row ? row[field.name] : '';
        var el;

        if (field.type === 'boolean') {
            el = document.createElement('input');
            el.type = 'checkbox';
            el.checked = (value === '1' || value === 1 || value === true);
        } else if (field.type === 'textarea') {
            el = document.createElement('textarea');
            el.className = 'form-control';
            el.rows = 3;
            el.value = value == null ? '' : String(value);
        } else if (field.type === 'select') {
            el = document.createElement('select');
            el.className = 'form-control';
            if (!field.required) {
                el.appendChild(new Option(text.none, ''));
            }
            (field.options || []).forEach(function (option) {
                var node = new Option(option.label, option.value);
                if (String(value) === String(option.value)) { node.selected = true; }
                el.appendChild(node);
            });
        } else if (field.type === 'lookup' || field.type === 'user') {
            el = document.createElement('select');
            el.className = 'form-control';
            el.multiple = !!field.multiple;
            if (!field.multiple) { el.appendChild(new Option(text.none, '')); }
            this.fill(el, field, value, row);
        } else {
            el = document.createElement('input');
            el.className = 'form-control';
            el.type = 'text';
            el.value = value == null ? '' : String(value);
            if (field.type === 'date' && window.ppDateField) {
                window.ppDateField(el, 'date');
            }
        }

        el.setAttribute('data-pp-pfield', field.name);
        el.disabled = !this.spec.editable;
        return el;
    };

    ProjectEditor.prototype.fill = function (el, field, value, row) {
        var chosen = [].concat(value == null || value === '' ? [] : value).map(String);
        var skip = field.name === 'parent_project' && row ? String(row.id) : null;

        // What the row holds goes in first, already selected, before the list
        // arrives. The list is one page of the table, so a value it does not
        // reach - or a save made before it lands - would otherwise go back
        // empty, and on the people columns that takes those people off the
        // project and their access with them. The row's own label names
        // each value until the list supplies the real one.
        var labels = row && row[field.name + '_label'] != null ? String(row[field.name + '_label']).split('、') : [];
        var seeded = {};
        chosen.forEach(function (id, i) {
            var label = labels.length === chosen.length && labels[i] ? labels[i] : '#' + id;
            seeded[id] = new Option(label, id, true, true);
            el.appendChild(seeded[id]);
        });

        this.lookup(field.lookup).then(function (options) {
            options.forEach(function (option) {
                var key = String(option.value);
                if (skip && key === skip) { return; }
                if (seeded[key]) {
                    seeded[key].text = option.label;
                    return;
                }
                el.appendChild(new Option(option.label, option.value));
            });
            el.setAttribute('data-pp-loaded', '1');
        });
    };

    ProjectEditor.prototype.open = function (row) {
        var self = this;
        this.form.innerHTML = '';
        this.form.style.display = '';

        var head = document.createElement('h4');
        head.style.marginTop = '0';
        head.textContent = row ? text.editProject + ' - ' + (row.name || '') : text.newProject;
        this.form.appendChild(head);

        this.spec.groups.forEach(function (group) {
            var fieldset = document.createElement('fieldset');
            fieldset.className = 'pp-proj-group';

            var legend = document.createElement('legend');
            legend.textContent = group.label;
            fieldset.appendChild(legend);

            group.fields.forEach(function (field) {
                var wrap = document.createElement('div');
                wrap.className = 'form-group pp-proj-field';

                var label = document.createElement('label');
                label.textContent = field.label;
                if (field.required) {
                    var star = document.createElement('span');
                    star.className = 'text-danger';
                    star.textContent = ' *';
                    label.appendChild(star);
                }
                wrap.appendChild(label);
                wrap.appendChild(self.control(field, row));

                if (field.help) {
                    var help = document.createElement('p');
                    help.className = 'help-block';
                    help.textContent = field.help;
                    wrap.appendChild(help);
                }
                fieldset.appendChild(wrap);
            });

            self.form.appendChild(fieldset);
        });

        var bar = document.createElement('div');
        bar.className = 'pp-proj-actions';

        if (this.spec.editable) {
            var save = document.createElement('button');
            save.type = 'button';
            save.className = 'btn btn-primary';
            save.style.marginRight = '8px';
            save.innerHTML = '<i class="fa fa-save"></i> ';
            save.appendChild(document.createTextNode(text.save));
            save.addEventListener('click', function () { self.submit(row, save); });
            bar.appendChild(save);
        }

        var cancel = document.createElement('button');
        cancel.type = 'button';
        cancel.className = 'btn btn-default';
        cancel.textContent = text.cancel;
        cancel.addEventListener('click', function () { self.close(); });
        bar.appendChild(cancel);

        this.form.appendChild(bar);
        this.form.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    };

    ProjectEditor.prototype.close = function () {
        this.form.innerHTML = '';
        this.form.style.display = 'none';
    };

    ProjectEditor.prototype.collect = function () {
        var form = this.form;
        var values = {};
        var missing = false;

        this.each(function (field) {
            var el = form.querySelector('[data-pp-pfield="' + field.name + '"]');
            if (!el) { return; }

            if (field.type === 'boolean') {
                values[field.name] = el.checked ? '1' : '0';
            } else if (field.multiple) {
                values[field.name] = Array.prototype.filter.call(el.options, function (option) {
                    return option.selected && option.value !== '';
                }).map(function (option) { return option.value; });
            } else {
                values[field.name] = el.value;
            }

            if (field.required && !String(el.value || '').trim()) { missing = true; }
        });

        return missing ? null : values;
    };

    ProjectEditor.prototype.submit = function (row, button) {
        var self = this;
        var values = this.collect();
        if (!values) { notify(text.required, 'error'); return; }

        var base = urls.lightapi + '/data/' + encodeURIComponent(this.spec.table);
        var request;
        if (row) {
            // Only what this form changed, and the version it was opened on.
            // The form is a copy of a list row that may be minutes old, and
            // sending every field back would undo whatever a co-worker saved
            // in between - on the people columns, the access they just gave.
            var changed = this.changes(row, values);
            if (!Object.keys(changed).length) { notify(text.noChange, 'success'); return; }
            request = api('PATCH', base + '/' + row.id, { value: changed, updated_at: row.updated_at });
        } else {
            request = api('POST', base, { value: values });
        }

        button.disabled = true;
        request.then(function (result) {
            button.disabled = false;
            if (result.status === 409 && result.json && result.json.error === 'stale') {
                notify(text.stale, 'error');
                self.close();
                self.load();
                return;
            }
            if (!result.ok) { notify(failure(result), 'error'); return; }
            notify(text.saved, 'success');
            self.close();
            self.load();
        });
    };

    /**
     * The collected values that differ from the row the form opened with.
     * Order does not count on a multi-value field, and an unset switch reads
     * as off - the way the form showed it.
     */
    ProjectEditor.prototype.changes = function (row, values) {
        var out = {};
        this.each(function (field) {
            if (!Object.prototype.hasOwnProperty.call(values, field.name)) { return; }
            var was = row[field.name];
            var now = values[field.name];
            var same;
            if (field.type === 'boolean') {
                same = (was === '1' || was === 1 || was === true ? '1' : '0') === now;
            } else if (field.multiple) {
                same = [].concat(was == null || was === '' ? [] : was).map(String).sort().join(',')
                    === [].concat(now).map(String).sort().join(',');
            } else {
                same = String(was == null ? '' : was) === String(now == null ? '' : now);
            }
            if (!same) { out[field.name] = now; }
        });
        return out;
    };

    // ---- tabs -----------------------------------------------------------

    root.querySelectorAll('[data-pp-tab]').forEach(function (link) {
        link.addEventListener('click', function (event) {
            event.preventDefault();
            var kind = link.getAttribute('data-pp-tab');
            root.querySelectorAll('[data-pp-panel]').forEach(function (panel) {
                panel.style.display = panel.getAttribute('data-pp-panel') === kind ? '' : 'none';
            });
            root.querySelectorAll('#pp-settings-tabs li').forEach(function (item) {
                item.className = item.contains(link) ? 'active' : '';
            });
        });
    });

    // ---- schema upgrade -------------------------------------------------

    var runner = document.getElementById('pp-upgrade-run');
    if (runner) {
        runner.addEventListener('click', function () {
            if (runner.disabled) { return; }
            // It rewrites stored values and cannot be undone, so it never
            // runs on a stray click.
            if (!window.confirm(text.upgradeConfirm)) { return; }
            runner.disabled = true;
            var log = document.getElementById('pp-upgrade-log');
            log.textContent = text.running;
            api('POST', urls.upgrade, { convert: true }).then(function (result) {
                var body = result.json || {};
                var done = result.ok && body.ok === true;
                // A finished run stays finished: the page has to be read
                // again before anything else on it is current.
                runner.disabled = done;
                log.innerHTML = '';
                var message = document.createElement('div');
                message.className = 'alert ' + (done ? 'alert-success' : 'alert-danger');
                message.textContent = done
                    ? (typeof body.message === 'string' && body.message !== '' ? body.message : text.upgraded)
                    : failure(result);
                log.appendChild(message);

                // What each step did, warnings included. Only from the
                // upgrade's own answer, which says ok one way or the other.
                var steps = typeof body.ok === 'boolean' && Array.isArray(body.steps) ? body.steps : [];
                steps = steps.filter(function (step) { return typeof step === 'string' && step !== ''; });
                if (steps.length) {
                    var list = document.createElement('ul');
                    steps.forEach(function (step) {
                        var item = document.createElement('li');
                        item.textContent = step;
                        list.appendChild(item);
                    });
                    log.appendChild(list);
                }
                // Left for the administrator to read before the page moves
                // on: a step may report values it could not convert.
                if (done) {
                    var again = document.createElement('button');
                    again.type = 'button';
                    again.className = 'btn btn-default';
                    again.textContent = text.reload;
                    again.addEventListener('click', function () { window.location.reload(); });
                    log.appendChild(again);
                }
            });
        });
    }

    sections.forEach(function (spec) { new Section(spec); });
    if (projectSpec) { new ProjectEditor(projectSpec); }
})();
</script>
