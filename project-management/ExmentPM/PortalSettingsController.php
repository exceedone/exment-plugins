<?php

namespace App\Plugins\ExmentPM;

use Exceedone\Exment\Controllers\AdminControllerBase;
use Exceedone\Exment\Enums\Permission;
use Exceedone\Exment\Model\CustomTable;
use ExmentAdminCore\Admin\Layout\Content;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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
 *
 * The one exception is data migration (Migrate/*, formerly the separate
 * PmMigrate plugin): it reads and writes the tables directly and moves files
 * both ways, so it is two form POSTs of its own, each checking for a system
 * administrator again.
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
     *
     * @param array<string, mixed> $migrate what a data migration POST has to
     *     show on the page it answers with: an error, or the import report
     */
    public function index(Request $request, Content $content, array $migrate = [])
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
            'migrate' => $this->migrateState($migrate),
            // Web Push (off at first): 「鍵を作り直す」 of the site's key pair
            'push' => PortalPush::enabled() ? ['url' => PortalDefinition::url('settings', 'push-keys'), 'renewed' => $request->query('push_keys') === 'renewed'] : null,
            'urls' => [
                'lightapi' => PortalDefinition::url('lightapi'),
                'upgrade' => PortalDefinition::url('settings', 'upgrade'),
                'portal' => PortalDefinition::url(),
                'migrate_export' => PortalDefinition::url('settings', 'migrate', 'export'),
                'migrate_import' => PortalDefinition::url('settings', 'migrate', 'import'),
            ],
        ])->render());
    }

    // ---- data migration ------------------------------------------------

    /**
     * Build the bundle of the ticked projects and send it as a download.
     *
     * A plain form POST rather than a lightapi call, as it was in the
     * PmMigrate plugin: the answer is a ZIP the browser saves, which a fetch
     * would have to rebuild into a download by hand. A failure answers with
     * the settings page again, the sentence in the migration card.
     *
     * @return mixed
     */
    public function migrateExport(Request $request, Content $content)
    {
        // The page that offers this is already admin-only, but a POST does not
        // come through that page: refuse it here too. It reads every row of
        // every project, whoever may see them.
        if (!static::isSystemAdmin()) {
            abort(403, strval(PortalDefinition::trans('migrate.not_admin')));
        }

        $ids = array_values(array_filter(array_map('intval', (array) $request->input('projects', []))));
        if (empty($ids)) {
            return $this->index($request, $content, ['error' => PortalDefinition::trans('migrate.select_projects')]);
        }

        static::liftLimits();
        try {
            $path = Migrate\Exporter::run($ids);
        } catch (\Throwable $e) {
            return $this->index($request, $content, ['error' => static::migrateMessage($e, 'export')]);
        }

        return response()->download($path, 'pm-migrate-' . date('Ymd-His') . '.zip')->deleteFileAfterSend(true);
    }

    /**
     * Check an uploaded bundle against this Exment, and write it if asked to.
     *
     * Every run is checked first (a dry run); writing needs the 実行 button
     * and the backup tick both, so a stray click cannot import anything.
     *
     * @return mixed
     */
    public function migrateImport(Request $request, Content $content)
    {
        // Writes rows straight into the tables, past every permission check:
        // a system administrator's call alone, whatever the screen showed.
        if (!static::isSystemAdmin()) {
            abort(403, strval(PortalDefinition::trans('migrate.not_admin')));
        }

        $file = $request->file('bundle');
        if ($file === null || !$file->isValid()) {
            return $this->index($request, $content, ['error' => PortalDefinition::trans('migrate.select_bundle')]);
        }

        $execute = $request->input('action') === 'execute';
        if ($execute && !$request->boolean('confirm')) {
            return $this->index($request, $content, ['error' => PortalDefinition::trans('migrate.need_confirm')]);
        }

        static::liftLimits();
        try {
            $spec = Migrate\UserMap::parseSpec(strval($request->input('user_map', '')));
            $mode = $request->input('master_mode') === Migrate\Importer::MASTER_REPLACE
                ? Migrate\Importer::MASTER_REPLACE
                : Migrate\Importer::MASTER_ABORT;
            $report = Migrate\Importer::run(strval($file->getRealPath()), $spec, $mode, $execute);
        } catch (\Throwable $e) {
            return $this->index($request, $content, ['error' => static::migrateMessage($e, 'import')]);
        }

        return $this->index($request, $content, ['report' => $report, 'ran' => $execute ? 'execute' : 'dry']);
    }

    /**
     * What the migration card shows: the projects that can be exported, and
     * the answer to the POST this page is the reply to, if any.
     *
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    protected function migrateState(array $extra): array
    {
        $projects = [];
        $error = $extra['error'] ?? null;
        try {
            $projects = Migrate\Exporter::projectChoices();
        } catch (\Throwable $e) {
            // The rest of the settings page must still open: a missing table
            // only takes the export list away.
            $error = $error ?? static::migrateMessage($e, 'projects');
        }

        $request = request();

        return [
            'projects' => $projects,
            'error' => $error,
            'report' => $extra['report'] ?? null,
            'ran' => $extra['ran'] ?? null,
            // An import answered with this page keeps what was typed, so a dry
            // run can be followed by the real one without retyping the map.
            'user_map' => strval($request->input('user_map', '')),
            'master_mode' => $request->input('master_mode') === Migrate\Importer::MASTER_REPLACE
                ? Migrate\Importer::MASTER_REPLACE
                : Migrate\Importer::MASTER_ABORT,
        ];
    }

    /**
     * A bundle can take longer, and more memory, than a web request is
     * normally allowed.
     */
    protected static function liftLimits(): void
    {
        @set_time_limit(0);
        @ini_set('memory_limit', '1024M');
    }

    /**
     * The sentence shown for a failure. Only MigrateException carries one that
     * is safe to show: anything else may hold SQL, a host name or a path, so
     * it goes to the log and the screen gets a fixed sentence.
     */
    protected static function migrateMessage(\Throwable $e, string $what): string
    {
        if ($e instanceof Migrate\MigrateException) {
            return $e->getMessage();
        }

        Log::error('pm_portal migrate ' . $what . ' failed: ' . $e->getMessage(), ['exception' => $e]);

        return strval(PortalDefinition::trans('migrate.failed'));
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
