<?php

namespace App\Plugins\PmPortal;

use Exceedone\Exment\Controllers\AdminControllerBase;
use Exceedone\Exment\Enums\Permission;
use Exceedone\Exment\Model\CustomTable;
use ExmentAdminCore\Admin\Layout\Content;
use Illuminate\Http\Request;

/**
 * The portal's own settings screen.
 *
 * Backlog keeps its per project lists - statuses, issue types - and its space
 * wide ones - priorities, close reasons - behind a settings page rather than
 * the raw data grid, because the person who curates them is a project admin,
 * not somebody comfortable with a custom table editor. This is that page.
 *
 * Everything on it is edited through the plugin's own lightapi, so there is
 * exactly one code path that writes a portal row and exactly one place where
 * permission is enforced. This controller decides what a user is offered; the
 * API decides what they are allowed to do, and does not trust this screen.
 */
class PortalSettingsController extends AdminControllerBase
{
    /** @var array<string, mixed> */
    protected $def;

    public function __construct()
    {
        $this->def = PortalDefinition::config();
    }

    /**
     * The settings screen.
     */
    public function index(Request $request, Content $content)
    {
        if (PortalSetup::needsSetup()) {
            return redirect(PortalDefinition::url('setup'));
        }

        // System administrators only, like the setup page and the upgrade
        // endpoint it carries: the page offers every project's row, every
        // master list and the schema upgrade. Project managers have the
        // settings tab of their own project; everyone else is told whom to
        // ask, on Exment's own error page.
        if (!static::isSystemAdmin()) {
            abort(403, strval(PortalDefinition::trans('settings.not_admin')));
        }

        $this->setPageInfo(
            PortalDefinition::trans('settings.title'),
            PortalDefinition::trans('settings.title'),
            PortalDefinition::trans('settings.lead'),
            'fa-sliders'
        );

        $sections = $this->sections();
        $schema = $this->schemaState();

        // An empty list is normal before the schema upgrade has run - the
        // upgrade panel is the whole point of the screen at that moment.
        if (empty($sections) && !$this->projectSection() && !$schema['needed']) {
            abort(403);
        }

        return $this->AdminContent($content)->body(view(PortalDefinition::view('settings'), [
            'sections' => $sections,
            'projectSection' => $this->projectSection(),
            'projects' => $this->projects(),
            'schema' => $schema,
            'urls' => [
                'lightapi' => PortalDefinition::url('lightapi'),
                'upgrade' => PortalDefinition::url('settings', 'upgrade'),
                'portal' => PortalDefinition::url(),
            ],
        ])->render());
    }

    /**
     * Run the schema upgrade.
     *
     * Restricted to a system administrator: it creates tables, rewrites a
     * column definition and touches every issue's stored value, which is more
     * than "may edit the status list" ought to buy.
     */
    public function upgrade(Request $request)
    {
        // Answered in the portal's own shape rather than by abort(): the
        // screen shows `message`, and Laravel's own 403 body is English.
        if (!static::isSystemAdmin()) {
            return PortalDefinition::jsonError(403, 'forbidden');
        }

        if (!PortalUpgrade::needed()) {
            return response()->json([
                'ok' => true,
                'message' => PortalDefinition::trans('upgrade.already'),
                'steps' => [],
            ]);
        }

        $result = PortalUpgrade::run($request->boolean('convert', true));
        if ($result['ok']) {
            return response()->json($result);
        }

        // Another run holds the lock: nothing went wrong, it is just not this
        // request's turn. `message` is already the sentence to show.
        $busy = !empty($result['busy']);

        return response()->json([
            'ok' => false,
            'error' => $busy ? 'upgrade_busy' : 'upgrade_failed',
            'message' => $result['message'],
            'steps' => $result['steps'],
        ], $busy ? 409 : 500);
    }

    // ---- page data -----------------------------------------------------

    /**
     * Which master list a user is offered, and whether they may change it.
     *
     * A list the install has not got yet - because the schema upgrade has not
     * run - is left out rather than shown broken.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function sections(): array
    {
        $specs = [
            ['role' => 'status_table', 'kind' => 'status', 'scoped' => true, 'icon' => 'fa-flag-o'],
            ['role' => 'issue_type_table', 'kind' => 'issue_type', 'scoped' => true, 'icon' => 'fa-bookmark-o'],
            ['role' => 'priority_table', 'kind' => 'priority', 'scoped' => false, 'icon' => 'fa-arrows-v'],
            ['role' => 'resolution_table', 'kind' => 'resolution', 'scoped' => false, 'icon' => 'fa-check-circle-o'],
        ];

        $sections = [];
        foreach ($specs as $spec) {
            $name = array_get($this->def, $spec['role']);
            $table = CustomTable::getEloquent($name);
            if (!isset($table) || !$table->hasPermission(Permission::AVAILABLE_VIEW_CUSTOM_VALUE)) {
                continue;
            }

            $sections[] = [
                'kind' => $spec['kind'],
                'table' => $table->table_name,
                'label' => PortalDefinition::trans('settings.section.' . $spec['kind']),
                'hint' => PortalDefinition::trans('settings.hint.' . $spec['kind']),
                'icon' => $spec['icon'],
                'scoped' => $spec['scoped'],
                'editable' => $table->hasPermission(Permission::AVAILABLE_ALL_EDIT_CUSTOM_VALUE),
                'fields' => $this->fields($spec['kind']),
            ];
        }

        return $sections;
    }

    /**
     * The editable shape of one master list, as the client form needs it.
     *
     * @param string $kind
     * @return array<int, array<string, mixed>>
     */
    protected function fields(string $kind): array
    {
        $map = (array) array_get($this->def, $kind . '_row', []);
        $types = [
            'name' => 'text',
            'color' => 'color',
            'order' => 'number',
            'done' => 'boolean',
            'template_subject' => 'text',
            'template_description' => 'textarea',
        ];

        $fields = [];
        foreach ($map as $role => $column) {
            if (!isset($types[$role]) || !is_string($column)) {
                continue;
            }
            $fields[] = [
                'role' => $role,
                'name' => $column,
                'type' => $types[$role],
                'label' => PortalDefinition::trans('settings.field.' . $role),
            ];
        }

        return $fields;
    }

    /**
     * The project editor.
     *
     * Backlog puts project creation and its settings behind their own screen
     * rather than a data grid, and so does this. The field list is built from
     * the columns the install actually has, so a site that has not run the
     * schema upgrade - or has removed a column - gets a smaller form instead
     * of a broken one.
     *
     * The groups mirror Backlog's own project settings tabs: what the project
     * is, when it runs, who is on it, which features it uses.
     *
     * @return array<string, mixed>|null null when the user may not see projects
     */
    protected function projectSection(): ?array
    {
        $table = CustomTable::getEloquent(array_get($this->def, 'project_table'));
        if (!isset($table) || !$table->hasPermission(Permission::AVAILABLE_VIEW_CUSTOM_VALUE)) {
            return null;
        }

        $groups = [
            'basic' => ['name', 'project_code', 'note', 'parent_project', 'status', 'formatting_rule'],
            'schedule' => ['start_date', 'end_date'],
            'people' => ['manager', 'members', 'viewers'],
            'features' => ['use_wiki', 'use_file', 'use_gantt', 'use_git', 'subtasking'],
            'state' => ['archived'],
        ];

        $columns = [];
        foreach ($table->custom_columns_cache as $column) {
            $columns[$column->column_name] = $column;
        }

        $out = [];
        foreach ($groups as $group => $names) {
            $fields = [];
            foreach ($names as $name) {
                if (!isset($columns[$name])) {
                    continue;
                }
                $field = $this->projectField($columns[$name]);
                if ($field !== null) {
                    $fields[] = $field;
                }
            }
            if ($fields) {
                $out[] = [
                    'key' => $group,
                    'label' => PortalDefinition::trans('settings.group.' . $group),
                    'fields' => $fields,
                ];
            }
        }

        if (empty($out)) {
            return null;
        }

        return [
            'table' => $table->table_name,
            'label' => PortalDefinition::trans('settings.section.project'),
            'hint' => PortalDefinition::trans('settings.hint.project'),
            'icon' => 'fa-folder-open-o',
            'groups' => $out,
            // The API is the thing that actually decides; this only chooses
            // whether to draw the controls at all.
            'editable' => $table->hasPermission(Permission::AVAILABLE_EDIT_CUSTOM_VALUE),
            'creatable' => $table->enableCreate() === true,
        ];
    }

    /**
     * Describe one project column for the client form.
     *
     * @param mixed $column
     * @return array<string, mixed>|null
     */
    protected function projectField($column): ?array
    {
        $name = $column->column_name;
        $type = strval($column->column_type);

        $field = [
            'name' => $name,
            'label' => strval($column->column_view_name),
            'help' => strval($column->getOption('help')),
            'required' => boolval($column->getOption('required')),
            'type' => 'text',
        ];

        switch ($type) {
            case 'textarea':
            case 'editor':
                $field['type'] = 'textarea';
                break;
            case 'date':
                $field['type'] = 'date';
                break;
            case 'yesno':
            case 'boolean':
                $field['type'] = 'boolean';
                break;
            case 'select':
            case 'select_valtext':
                $field['type'] = 'select';
                $field['options'] = $this->selectOptions($column);
                break;
            case 'select_table':
                $field['type'] = 'lookup';
                $target = $column->select_target_table;
                if (!isset($target)) {
                    return null;
                }
                $field['lookup'] = $target->table_name;
                break;
            case 'user':
            case 'organization':
                $field['type'] = 'user';
                $field['lookup'] = $type === 'user' ? 'user' : 'organization';
                $field['multiple'] = boolval($column->getOption('multiple_enabled'));
                break;
            default:
                $field['type'] = 'text';
                break;
        }

        return $field;
    }

    /**
     * Read the fixed choices off a select column.
     *
     * @param mixed $column
     * @return array<int, array<string, string>>
     */
    protected function selectOptions($column): array
    {
        $raw = strval($column->getOption('select_item_valtext'));
        $options = [];

        if ($raw !== '') {
            foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                $parts = explode(',', $line, 2);
                $options[] = [
                    'value' => trim($parts[0]),
                    'label' => trim($parts[1] ?? $parts[0]),
                ];
            }

            return $options;
        }

        foreach (preg_split('/\r\n|\r|\n/', strval($column->getOption('select_item'))) as $line) {
            $line = trim($line);
            if ($line !== '') {
                $options[] = ['value' => $line, 'label' => $line];
            }
        }

        return $options;
    }

    /**
     * Projects the user may see, for the per project lists.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function projects(): array
    {
        $table = CustomTable::getEloquent(array_get($this->def, 'project_table'));
        if (!isset($table)) {
            return [];
        }

        $rows = [];
        foreach ($table->getValueModel()->newQuery()->get() as $value) {
            if ($value->enableAccess() !== true) {
                continue;
            }
            $rows[] = [
                'id' => $value->id,
                'name' => strval($value->getLabel()),
                'type' => $table->table_name,
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    protected function schemaState(): array
    {
        return [
            'current' => PortalSetup::schemaVersion(),
            'target' => PortalSchema::VERSION,
            'needed' => PortalUpgrade::needed(),
            'allowed' => static::isSystemAdmin(),
        ];
    }

    /**
     * Whether the signed-in user is an Exment system administrator.
     */
    protected static function isSystemAdmin(): bool
    {
        $user = \Exment::user();

        return $user !== null && boolval($user->isAdministrator());
    }
}
