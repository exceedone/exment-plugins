<?php

namespace App\Plugins\PmPortal;

use Exceedone\Exment\Controllers\AdminControllerBase;
use Exceedone\Exment\Enums\Permission;
use Exceedone\Exment\Model\CustomTable;
use ExmentAdminCore\Admin\Layout\Content;
use Illuminate\Http\Request;

/**
 * The portal's own settings screen: what the whole space shares.
 *
 * Priorities and close reasons are the same in every project, so they are
 * curated here, behind a page rather than the raw data grid; the schema
 * upgrade lives here too. What belongs to one project - its own row, its
 * people, its statuses, issue types, categories and milestones - is edited
 * on that project's 設定 tab (_settings.blade.php) by its manager, and is
 * deliberately not repeated on this page.
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
        // endpoint it carries: the page offers the lists every project
        // shares and the schema upgrade. Project managers have the settings
        // tab of their own project; everyone else is told whom to ask, on
        // Exment's own error page.
        if (!static::isSystemAdmin()) {
            abort(403, strval(PortalDefinition::trans('settings.not_admin')));
        }

        $this->setPageInfo(
            PortalDefinition::trans('settings.title'),
            PortalDefinition::trans('settings.title'),
            PortalDefinition::trans('settings.lead'),
            'fa-sliders'
        );

        // No list at all is normal before the schema upgrade has run - the
        // upgrade panel is the whole point of the screen at that moment.
        return $this->AdminContent($content)->body(view(PortalDefinition::view('settings'), [
            'sections' => $this->sections(),
            'schema' => $this->schemaState(),
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
     * The lists the whole space shares, and whether the user may change them.
     *
     * A list the install has not got yet - because the schema upgrade has not
     * run - is left out rather than shown broken.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function sections(): array
    {
        $specs = [
            ['role' => 'priority_table', 'kind' => 'priority', 'icon' => 'fa-arrows-v'],
            ['role' => 'resolution_table', 'kind' => 'resolution', 'icon' => 'fa-check-circle-o'],
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
     * @return array<string, mixed>
     */
    protected function schemaState(): array
    {
        return [
            'current' => PortalSetup::schemaVersion(),
            'target' => PortalSchema::VERSION,
            'needed' => PortalUpgrade::needed(),
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
