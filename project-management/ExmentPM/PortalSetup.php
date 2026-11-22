<?php

namespace App\Plugins\ExmentPM;

use Exceedone\Exment\Enums\RoleType;
use Exceedone\Exment\Model\CustomTable;
use Exceedone\Exment\Model\Define;
use Exceedone\Exment\Model\Menu;
use Exceedone\Exment\Model\Notify;
use Exceedone\Exment\Model\Plugin;
use Exceedone\Exment\Model\RoleGroup;
use Exceedone\Exment\Model\System;
use Exceedone\Exment\Services\TemplateImportExport\TemplateImporter;

/**
 * First run setup for the portal.
 *
 * The plugin ships its tables as an Exment template but deliberately does not
 * install it on upload. A template import matches tables by name, so dropping
 * the zip on a system that already owns a table called "project" would rewrite
 * that table without asking. Instead the portal's own entry screen runs a
 * preflight: every table the template declares is checked against the install,
 * clashes are shown in red, a free prefix is offered, and the import runs only
 * once an administrator confirms.
 *
 * The answer is kept in the plugin row's options column. PluginInstaller
 * seeds its option array from the existing row and only overwrites the keys it
 * knows, so re-uploading the plugin keeps this state and the setup screen is
 * never shown again.
 */
class PortalSetup
{
    /** Key holding the setup answer inside plugins.options. */
    const STATE_KEY = 'portal_setup';

    /** Directory name of the bundled template. */
    const TEMPLATE_NAME = 'pm_portal';

    /** Same limit the custom table screen applies to a table name. */
    const MAX_TABLE_NAME_LENGTH = 30;

    /**
     * Leaf keys anywhere in a template whose value is a custom table name.
     *
     * Matching on the leaf key rather than a full path keeps this working when
     * Exment nests an existing structure differently, and it is what makes the
     * rename safe: the template also contains a *column* named "project", but
     * a column sits under "column_name", which is not in this list.
     */
    const TABLE_NAME_KEYS = [
        'table_name',
        'select_target_table_name',
        'share_table_name',
        'table_label_table_name',
        'parent_custom_table_name',
        'child_custom_table_name',
        'form_block_target_table_name',
        'view_column_table_name',
        'target_table_name',
        'custom_table_name',
        'from_custom_table_name',
        'to_custom_table_name',
    ];

    /**
     * Role group permission types whose target is a custom table name.
     */
    const TABLE_ROLE_TYPES = [RoleType::TABLE, RoleType::VALUE];

    /**
     * Template sections Exment matches on a name a person chose.
     *
     * Tables are cleared by the preflight, relations are matched by table id
     * and views by a random suuid, so those three can be written outright.
     * These three cannot: this install may already own a menu node, a role
     * group or a notification of the same name for something unrelated, and
     * an outright import would overwrite it. They go in separately, told to
     * create what is missing and leave everything else alone.
     */
    const NAME_MATCHED_SECTIONS = ['admin_menu', 'roles', 'notifies'];

    /** @var array<string, mixed>|null */
    protected static $state_cache = null;

    /** @var array<string, mixed>|null */
    protected static $template_cache = null;

    // ---- template ------------------------------------------------------

    public static function templateDir(): string
    {
        return __DIR__ . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . self::TEMPLATE_NAME;
    }

    public static function templateConfigPath(): string
    {
        return static::templateDir() . DIRECTORY_SEPARATOR . 'config.json';
    }

    /**
     * The bundled template, decoded.
     *
     * @return array<string, mixed>|null null when the plugin ships without one
     */
    public static function templateJson(): ?array
    {
        if (isset(static::$template_cache)) {
            return static::$template_cache;
        }

        $path = static::templateConfigPath();
        if (!file_exists($path)) {
            return null;
        }

        $json = json_decode(file_get_contents($path), true);

        return static::$template_cache = (is_array($json) ? $json : null);
    }

    // ---- preflight -----------------------------------------------------

    /**
     * Every table an install under this prefix would create: the template's
     * and the ones PortalSchema adds straight after - every role in
     * PortalDefinition::tableRoles().
     *
     * The schema's tables are imported the same way the template's are, by
     * name and leaving what is there in place, so one that already exists for
     * something else would have the portal's columns, seed rows and role
     * grants put onto it. They have to clear the preflight too.
     *
     * @param string $prefix
     * @return array<int, array<string, mixed>>
     */
    public static function plannedTables(string $prefix = ''): array
    {
        $rows = static::templateTables($prefix);

        $defaults = static::namesFor('');
        foreach (PortalSchema::newTables(static::namesFor($prefix)) as $table) {
            $target = strval(array_get($table, 'table_name'));
            $role = array_search(substr($target, strlen($prefix)), $defaults, true);
            if ($target === '' || $role === false) {
                continue;
            }

            $rows[] = static::plannedRow($defaults[$role], $prefix, $table);
        }

        return $rows;
    }

    /**
     * The tables the bundled template itself declares, resolved against a
     * prefix. These are the ones an install is recognised by: needsSetup()
     * and the adoption of an older install look at these alone, since the
     * schema's tables only appear once the upgrade has run.
     *
     * @param string $prefix
     * @return array<int, array<string, mixed>>
     */
    public static function templateTables(string $prefix = ''): array
    {
        $rows = [];

        foreach (array_get(static::templateJson() ?? [], 'custom_tables', []) as $table) {
            $name = array_get($table, 'table_name');
            if (!is_string($name) || $name === '') {
                continue;
            }

            $rows[] = static::plannedRow($name, $prefix, $table);
        }

        return $rows;
    }

    /**
     * @param string $name the name the table ships under
     * @param string $prefix
     * @param array<string, mixed> $table its template definition
     * @return array<string, mixed>
     */
    protected static function plannedRow(string $name, string $prefix, array $table): array
    {
        $target = $prefix . $name;

        return [
            'template_name' => $name,
            'name' => $target,
            'view_name' => strval(array_get($table, 'table_view_name', $name)),
            'description' => strval(array_get($table, 'description', '')),
            'column_count' => count(array_get($table, 'custom_columns', [])),
            'exists' => static::tableExists($target),
            'valid' => static::isValidTableName($target),
        ];
    }

    /**
     * The table name each role resolves to under a prefix.
     *
     * Needed before the install exists, which is why it takes the prefix
     * rather than reading the stored answer the way PortalUpgrade does.
     *
     * @param string $prefix
     * @return array<string, string>
     */
    public static function namesFor(string $prefix): array
    {
        $names = [];
        foreach (PortalDefinition::tableRoles() as $role => $name) {
            $names[$role] = $prefix . $name;
        }

        return $names;
    }

    /**
     * Names this install is about to claim that are not table names.
     *
     * The table preflight can promise a free name, because a custom table is
     * the only thing here that nothing else may share. A menu node, a role
     * group and a notification are all matched on a name somebody typed, and
     * this install may be using one of them already. Those are reported
     * rather than blocked: the import leaves an existing row exactly as it
     * is, and PortalUpgrade then adds the portal's own permissions to
     * whatever group it found - so an administrator can see what is being
     * joined rather than shared into by surprise.
     *
     * Each row carries the part of the name that does not change so the
     * screen can re-check a typed prefix without asking the server again,
     * the same way the table list does.
     *
     * @param string $prefix
     * @return array<int, array<string, mixed>> kind, suffix, name, exists
     */
    public static function reservedNames(string $prefix = ''): array
    {
        // Built with an empty prefix and prefixed here, because that is what
        // makes a suffix that the screen can re-apply to whatever is typed.
        $names = static::namesFor('');
        $suffixes = [];

        foreach (array_get(static::templateJson() ?? [], 'admin_menu', []) as $menu) {
            $suffixes[] = ['kind' => 'menu', 'suffix' => strval(array_get($menu, 'menu_name'))];
        }
        foreach (PortalSchema::menus($names, '') as $menu) {
            $suffixes[] = ['kind' => 'menu', 'suffix' => strval(array_get($menu, 'menu_name'))];
        }
        foreach (array_keys(PortalSchema::roleGroups()) as $group) {
            $suffixes[] = ['kind' => 'role', 'suffix' => $group];
        }
        foreach (PortalSchema::notifies($names, '') as $notify) {
            // The display name, because that is the one Exment matches on.
            $suffixes[] = ['kind' => 'notify', 'suffix' => strval(array_get($notify, 'notify_view_name'))];
        }

        $taken = static::takenNames();
        $seen = [];
        $out = [];
        foreach ($suffixes as $row) {
            $key = $row['kind'] . '|' . $row['suffix'];
            if ($row['suffix'] === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $row['name'] = $prefix . $row['suffix'];
            $row['exists'] = in_array($row['name'], array_get($taken, $row['kind'], []), true);
            $out[] = $row;
        }

        return $out;
    }

    /**
     * Every name of each kind this install already uses.
     *
     * Three small tables read whole, rather than a query per candidate: the
     * screen needs the same lists anyway to re-check a prefix as it is typed.
     *
     * @return array<string, array<int, string>>
     */
    public static function takenNames(): array
    {
        return [
            'menu' => Menu::query()->pluck('menu_name')->filter()->unique()->values()->all(),
            'role' => RoleGroup::query()->pluck('role_group_name')->filter()->unique()->values()->all(),
            'notify' => Notify::query()->pluck('notify_view_name')->filter()->unique()->values()->all(),
        ];
    }

    /**
     * Table names already used by this install, for the client side preview.
     *
     * @return array<int, string>
     */
    public static function existingTableNames(): array
    {
        return CustomTable::query()->orderBy('table_name')->pluck('table_name')->all();
    }

    public static function hasConflict(string $prefix): bool
    {
        foreach (static::plannedTables($prefix) as $table) {
            if ($table['exists'] || !$table['valid']) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether every table the template declares already exists under a prefix.
     */
    public static function allTablesExist(string $prefix): bool
    {
        $tables = static::templateTables($prefix);
        if (empty($tables)) {
            return false;
        }

        foreach ($tables as $table) {
            if (!$table['exists']) {
                return false;
            }
        }

        return true;
    }

    /**
     * A prefix that clears every clash, or an empty string when none is needed.
     */
    public static function suggestPrefix(): string
    {
        if (!static::hasConflict('')) {
            return '';
        }

        for ($i = 2; $i <= 99; $i++) {
            $prefix = 'pm' . $i . '_';
            if (!static::hasConflict($prefix)) {
                return $prefix;
            }
        }

        return 'pm' . date('ymdHis') . '_';
    }

    protected static function tableExists(string $name): bool
    {
        if ($name === '') {
            return false;
        }

        return CustomTable::query()->where('table_name', $name)->exists();
    }

    public static function isValidTableName(string $name): bool
    {
        if ($name === '' || mb_strlen($name) > self::MAX_TABLE_NAME_LENGTH) {
            return false;
        }

        return boolval(preg_match('/' . Define::RULES_REGEX_SYSTEM_NAME . '/', $name));
    }

    // ---- stored state --------------------------------------------------

    protected static function plugin(): ?Plugin
    {
        return PortalDefinition::plugin();
    }

    /**
     * The name this plugin is registered under.
     *
     * Exment resolves plugin references by name rather than uuid in several
     * places, so read it off the installed row instead of assuming it.
     *
     * @return string
     */
    public static function pluginName(): string
    {
        return PortalDefinition::pluginName();
    }

    /**
     * @return array<string, mixed>
     */
    public static function state(): array
    {
        if (isset(static::$state_cache)) {
            return static::$state_cache;
        }

        $plugin = static::plugin();
        $stored = isset($plugin) ? array_get($plugin, 'options.' . self::STATE_KEY, []) : [];

        return static::$state_cache = array_merge([
            'installed' => false,
            'prefix' => '',
            'tables' => [],
        ], is_array($stored) ? $stored : []);
    }

    /**
     * Drop this request's copy of the stored state, so the next read comes
     * from the plugin row. For a run that has just taken the upgrade lock:
     * what it read before then may be what another run has since replaced.
     *
     * @return void
     */
    public static function forgetState(): void
    {
        static::$state_cache = null;
        System::clearCache();
    }

    /**
     * The table names this install actually uses, keyed the way
     * PortalDefinition::config() expects them.
     *
     * @return array<string, string>
     */
    public static function tableMap(): array
    {
        $tables = array_get(static::state(), 'tables', []);
        if (!is_array($tables)) {
            return [];
        }

        return array_filter($tables, function ($value, $key) {
            return is_string($value) && $value !== '' && array_key_exists($key, PortalDefinition::tableRoles());
        }, ARRAY_FILTER_USE_BOTH);
    }

    /**
     * Whether the portal has been set up on this install.
     */
    public static function isInstalled(): bool
    {
        return boolval(array_get(static::state(), 'installed'));
    }

    /**
     * The schema version already applied here. Zero means the install predates
     * PortalSchema and still has the shape the bundled template creates.
     */
    public static function schemaVersion(): int
    {
        return intval(array_get(static::state(), PortalSchema::STATE_KEY, 0));
    }

    public static function setSchemaVersion(int $version): void
    {
        static::patchState([PortalSchema::STATE_KEY => $version]);
    }

    /**
     * Record a table name for every role the code knows about.
     *
     * An install set up before a role existed has no entry for it, which would
     * leave PortalDefinition falling back to the unprefixed default and, on a
     * prefixed install, pointing at the wrong table or none at all.
     *
     * @return array<string, string> the map as stored afterwards
     */
    public static function syncTables(): array
    {
        $prefix = static::prefix();
        $tables = array_get(static::state(), 'tables', []);
        $tables = is_array($tables) ? $tables : [];

        $added = false;
        foreach (PortalDefinition::tableRoles() as $role => $name) {
            if (!is_string(array_get($tables, $role)) || array_get($tables, $role) === '') {
                $tables[$role] = $prefix . $name;
                $added = true;
            }
        }

        if ($added) {
            static::patchState(['tables' => $tables]);
        }

        return $tables;
    }

    /**
     * Merge keys into the stored setup state without disturbing the rest.
     *
     * @param array<string, mixed> $values
     * @return void
     */
    public static function patchState(array $values): void
    {
        $plugin = static::plugin();
        if (!isset($plugin)) {
            return;
        }

        $options = (array) $plugin->options;
        $state = array_get($options, self::STATE_KEY, []);
        $options[self::STATE_KEY] = array_merge(is_array($state) ? $state : [], $values);

        $plugin->options = $options;
        $plugin->save();

        static::$state_cache = null;
        System::clearCache();
    }

    public static function prefix(): string
    {
        return strval(array_get(static::state(), 'prefix', ''));
    }

    /**
     * Whether the entry screen should hand over to the setup wizard.
     *
     * A plugin re-upload keeps the stored answer, so this stays false and the
     * wizard never reappears. It does turn true again when the tables the
     * answer points at have since been deleted, which is a broken install
     * rather than a re-install, and offering the wizard beats a 404.
     */
    public static function needsSetup(): bool
    {
        if (static::templateJson() === null) {
            return false;
        }

        if (!boolval(array_get(static::state(), 'installed'))) {
            // Earlier releases of this plugin imported the template on upload,
            // so an existing install already owns the tables under the names
            // the template ships with. Adopt those rather than asking an
            // administrator to set up something that is already running.
            // Not after a wizard run, though: a failed import leaves its
            // tables behind as well, and adopting those would hide the failure
            // behind an empty portal with no menus and no role groups.
            if (!static::failedAttemptAt() && static::allTablesExist('')) {
                static::markInstalled('');

                return false;
            }

            return true;
        }

        // The template's tables only: the schema's appear once the upgrade has
        // run, and an install waiting for it is not a broken one.
        foreach (static::templateTables(static::prefix()) as $table) {
            if (!$table['exists']) {
                return true;
            }
        }

        return false;
    }

    /**
     * When the setup wizard last started an import that never finished.
     *
     * markInstalled() replaces the whole state, so the marker only survives
     * an attempt that failed.
     */
    public static function failedAttemptAt(): ?string
    {
        $state = static::state();
        if (boolval(array_get($state, 'installed'))) {
            return null;
        }

        $at = array_get($state, 'attempted_at');

        return is_string($at) && $at !== '' ? $at : null;
    }

    /**
     * The menu entry a site has before 初期セットアップ (7.2.1): on a fresh site the
     * plugin made its entry only in the setup, so nobody knew where to start.
     *
     * One entry, "プロジェクト管理", in the group of the feature map when the core has
     * it, pointing at the plugin; the plugin's page shows the setup screen until it
     * is done. Only those who may use the plugin see an entry of a plugin - on a
     * fresh site, the system administrators. Its own name, not the one the setup
     * imports (pm_portal_page): the setup reports a name in use as taken. The setup
     * takes it away when it has made its own (markInstalled). Made once; a site that
     * is set up, or has any entry of the plugin, is left alone.
     */
    public static function ensurePreSetupMenu(): void
    {
        if (static::isInstalled() || !hasTable('admin_menu')) {
            return;
        }
        $plugin = static::plugin();
        if (!isset($plugin)) {
            return;
        }
        $exists = \DB::table('admin_menu')->where(function ($q) use ($plugin) {
            $q->where('menu_name', self::PRESETUP_MENU)
                ->orWhere(function ($q) use ($plugin) {
                    $q->where('menu_type', 'plugin')->where('menu_target', strval($plugin->id));
                });
        })->exists();
        if ($exists) {
            return;
        }
        $group = PortalSchema::menuGroupId();
        $now = now();
        \DB::table('admin_menu')->insert([
            'parent_id' => $group ?? 0,
            'order' => isset($group) ? 1 : 34,
            'title' => strval(PortalDefinition::trans('schema.menu.single')),
            'icon' => 'fa-tasks',
            'uri' => '',
            'menu_type' => 'plugin',
            'menu_target' => strval($plugin->id),
            'menu_name' => self::PRESETUP_MENU,
            'options' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        System::clearCache();
    }

    /** The name of the entry before the setup (ensurePreSetupMenu). */
    const PRESETUP_MENU = 'pm_portal_presetup';

    /**
     * After the setup: its own entry is there (the template's pm_portal_page), so the
     * one from before the setup goes - one entry, as on a site set up before 7.2.1.
     */
    protected static function removePreSetupMenu(): void
    {
        if (!hasTable('admin_menu')) {
            return;
        }
        $ids = \DB::table('admin_menu')->where('menu_name', self::PRESETUP_MENU)->pluck('id')->all();
        if (empty($ids)) {
            return;
        }
        if (hasTable('admin_role_menu')) {
            \DB::table('admin_role_menu')->whereIn('menu_id', $ids)->delete();
        }
        \DB::table('admin_menu')->whereIn('id', $ids)->delete();
    }

    protected static function markInstalled(string $prefix): void
    {
        $plugin = static::plugin();
        if (!isset($plugin)) {
            return;
        }

        $tables = static::namesFor($prefix);

        $options = (array) $plugin->options;
        $options[self::STATE_KEY] = [
            'installed' => true,
            'prefix' => $prefix,
            'tables' => $tables,
            'installed_at' => now()->toDateTimeString(),
        ];

        $plugin->options = $options;
        $plugin->save();

        static::$state_cache = null;
        static::removePreSetupMenu();
        System::clearCache();
    }

    // ---- install -------------------------------------------------------

    /**
     * Import the bundled template under the given prefix.
     *
     * One at a time with the schema upgrade it ends in (see
     * PortalUpgrade::exclusive()): a second submit of the same form - a
     * double click, a retry after a proxy gave up - waits for nothing and is
     * told the first one is still running, or that it has finished.
     *
     * @param string $prefix
     * @return array{ok: bool, message: string}
     */
    public static function install(string $prefix): array
    {
        PortalUpgrade::keepRunning();

        return PortalUpgrade::exclusive(function () use ($prefix) {
            // The state was just read again (exclusive()): a run that held
            // the lock until a moment ago may have finished the install.
            if (static::isInstalled() && !static::needsSetup()) {
                return ['ok' => true, 'message' => PortalDefinition::trans('setup.already')];
            }

            return static::installGuarded($prefix);
        }, function () {
            return ['ok' => false, 'message' => PortalDefinition::trans('setup.busy')];
        });
    }

    /**
     * @param string $prefix
     * @return array{ok: bool, message: string}
     */
    protected static function installGuarded(string $prefix): array
    {
        $prefix = trim($prefix);

        $jsonString = static::templateJson() === null ? null : file_get_contents(static::templateConfigPath());
        if (!isset($jsonString)) {
            return ['ok' => false, 'message' => PortalDefinition::trans('setup.error_template')];
        }

        foreach (static::plannedTables($prefix) as $table) {
            if (!$table['valid']) {
                return [
                    'ok' => false,
                    'message' => PortalDefinition::trans('setup.error_invalid', ['name' => $table['name']]),
                ];
            }
            if ($table['exists']) {
                return [
                    'ok' => false,
                    'message' => PortalDefinition::trans('setup.error_conflict', ['name' => $table['name']]),
                ];
            }
        }

        // Table DDL commits on its own, so an import that fails halfway still
        // leaves tables behind. Record the attempt first so needsSetup() does
        // not take them for a finished install.
        static::patchState(['attempted_at' => now()->toDateTimeString(), 'attempted_prefix' => $prefix]);

        try {
            $importer = new TemplateImporter();

            // Merge the language file first: it is keyed by the names the
            // template ships with, so renaming has to come after.
            $json = $importer->getMergeJson($jsonString, ['basePath' => static::templateDir()]);
            if (!is_array($json)) {
                return ['ok' => false, 'message' => PortalDefinition::trans('setup.error_template')];
            }

            $json = static::applyPrefix($json, $prefix);

            // See NAME_MATCHED_SECTIONS: the second pass is the one that must
            // not overwrite. Split rather than importing everything twice,
            // because a table written with $is_update = true would silently
            // skip a column added since the template was last exported.
            $importer->import(array_except($json, self::NAME_MATCHED_SECTIONS), false, false);

            $shared = array_filter(array_only($json, self::NAME_MATCHED_SECTIONS));
            if (!empty($shared)) {
                $importer->import($shared, false, true);
            }
        } catch (\Throwable $e) {
            // The exception stays in the log: it is English, and a database
            // error carries the SQL, the host and the database name. The
            // screen gets a reference to find it by.
            $ref = PortalUpgrade::errorRef();
            \Log::error('PmPortal setup failed [' . $ref . ']: ' . $e->getMessage(), ['exception' => $e]);

            return [
                'ok' => false,
                'message' => PortalDefinition::trans('setup.error_import', ['ref' => $ref]),
            ];
        }

        static::markInstalled($prefix);

        // A fresh install has no data to convert, so bring it straight up to
        // the current schema instead of leaving an upgrade waiting on the
        // settings screen.
        $upgrade = PortalUpgrade::run();
        if (!$upgrade['ok']) {
            return $upgrade;
        }

        return ['ok' => true, 'message' => PortalDefinition::trans('setup.success')];
    }

    // ---- prefixing -----------------------------------------------------

    /**
     * Rewrite a decoded template so every name it owns carries the prefix.
     *
     * Table names are only half the job: role group names carry a unique
     * index, and menu nodes, notifies and template names are matched by name
     * on import, so leaving those alone would let a prefixed install quietly
     * take over rows belonging to something else.
     *
     * @param array<string, mixed> $json
     * @param string $prefix
     * @return array<string, mixed>
     */
    public static function applyPrefix(array $json, string $prefix): array
    {
        if ($prefix === '') {
            return $json;
        }

        $map = [];
        foreach (array_get($json, 'custom_tables', []) as $table) {
            $name = array_get($table, 'table_name');
            if (is_string($name) && $name !== '') {
                $map[$name] = $prefix . $name;
            }
        }

        if (empty($map)) {
            return $json;
        }

        $json = static::renameTableNameKeys($json, $map);
        $json = static::renameMenu($json, $map, $prefix);
        $json = static::renameRoles($json, $map, $prefix);
        $json = static::renameNotifies($json, $map, $prefix);
        $json = static::renameViewRefs($json, $map);
        $json = static::renameSuuids($json, $prefix);

        $template_name = array_get($json, 'template_name');
        if (is_string($template_name) && $template_name !== '') {
            $json['template_name'] = $prefix . $template_name;
        }

        return $json;
    }

    /**
     * @param array<mixed> $node
     * @param array<string, string> $map
     * @return array<mixed>
     */
    protected static function renameTableNameKeys(array $node, array $map): array
    {
        foreach ($node as $key => $value) {
            if (is_array($value)) {
                $node[$key] = static::renameTableNameKeys($value, $map);
                continue;
            }

            if (is_string($value) && isset($map[$value]) && in_array($key, self::TABLE_NAME_KEYS, true)) {
                $node[$key] = $map[$value];
            }
        }

        return $node;
    }

    /**
     * @param array<string, mixed> $json
     * @param array<string, string> $map
     * @return array<string, mixed>
     */
    protected static function renameMenu(array $json, array $map, string $prefix): array
    {
        $menus = array_get($json, 'admin_menu');
        if (!is_array($menus)) {
            return $json;
        }

        // Only rename nodes the template itself declares, so a parent node
        // the template merely attaches to keeps pointing at the right place.
        $owned = [];
        foreach ($menus as $menu) {
            $name = array_get($menu, 'menu_name');
            if (is_string($name) && $name !== '') {
                $owned[$name] = $prefix . $name;
            }
        }

        foreach ($menus as $i => $menu) {
            foreach (['menu_name', 'parent_name'] as $key) {
                $value = array_get($menu, $key);
                if (is_string($value) && isset($owned[$value])) {
                    $menus[$i][$key] = $owned[$value];
                }
            }

            if (array_get($menu, 'menu_type') !== 'table') {
                continue;
            }

            foreach (['menu_target_name', 'uri'] as $key) {
                $value = array_get($menu, $key);
                if (is_string($value) && isset($map[$value])) {
                    $menus[$i][$key] = $map[$value];
                }
            }
        }

        $json['admin_menu'] = $menus;

        return $json;
    }

    /**
     * @param array<string, mixed> $json
     * @param array<string, string> $map
     * @return array<string, mixed>
     */
    protected static function renameRoles(array $json, array $map, string $prefix): array
    {
        $roles = array_get($json, 'roles');
        if (!is_array($roles)) {
            return $json;
        }

        $table_types = array_map('strval', self::TABLE_ROLE_TYPES);

        foreach ($roles as $i => $role) {
            $name = array_get($role, 'role_group_name');
            if (is_string($name) && $name !== '') {
                $roles[$i]['role_group_name'] = $prefix . $name;
            }

            foreach (array_get($role, 'role_group_permissions', []) as $j => $permission) {
                if (!in_array(strval(array_get($permission, 'role_group_permission_type')), $table_types, true)) {
                    continue;
                }

                $target = array_get($permission, 'role_group_target_name');
                if (is_string($target) && isset($map[$target])) {
                    $roles[$i]['role_group_permissions'][$j]['role_group_target_name'] = $map[$target];
                }
            }
        }

        $json['roles'] = $roles;

        return $json;
    }

    /**
     * @param array<string, mixed> $json
     * @param array<string, string> $map
     * @return array<string, mixed>
     */
    protected static function renameNotifies(array $json, array $map, string $prefix): array
    {
        $notifies = array_get($json, 'notifies');
        if (!is_array($notifies)) {
            return $json;
        }

        foreach ($notifies as $i => $notify) {
            // Exment matches a notify on its view name, so the label doubles
            // as the identity and has to move with the prefix.
            foreach (['notify_name', 'notify_view_name'] as $key) {
                $value = array_get($notify, $key);
                if (is_string($value) && $value !== '') {
                    $notifies[$i][$key] = $prefix . $value;
                }
            }

            foreach (array_get($notify, 'action_settings', []) as $j => $action) {
                $targets = array_get($action, 'notify_action_target');
                if (!is_array($targets)) {
                    continue;
                }

                foreach ($targets as $k => $target) {
                    $notifies[$i]['action_settings'][$j]['notify_action_target'][$k] = static::renameRef($target, $map);
                }
            }
        }

        $json['notifies'] = $notifies;

        return $json;
    }

    /**
     * View settings address a column as "table_name.column_name", and kanban
     * keys embed the same reference in front of a value.
     *
     * @param array<string, mixed> $json
     * @param array<string, string> $map
     * @return array<string, mixed>
     */
    protected static function renameViewRefs(array $json, array $map): array
    {
        $views = array_get($json, 'custom_views');
        if (!is_array($views)) {
            return $json;
        }

        foreach ($views as $i => $view) {
            foreach (['options', 'custom_options'] as $key) {
                if (isset($view[$key])) {
                    $views[$i][$key] = static::renameRefsDeep($view[$key], $map);
                }
            }
        }

        $json['custom_views'] = $views;

        return $json;
    }

    /**
     * @param mixed $node
     * @param array<string, string> $map
     * @return mixed
     */
    protected static function renameRefsDeep($node, array $map)
    {
        if (is_array($node)) {
            foreach ($node as $key => $value) {
                $node[$key] = static::renameRefsDeep($value, $map);
            }

            return $node;
        }

        return static::renameRef($node, $map);
    }

    /**
     * @param mixed $value
     * @param array<string, string> $map
     * @return mixed
     */
    protected static function renameRef($value, array $map)
    {
        if (!is_string($value) || strpos($value, '.') === false) {
            return $value;
        }

        // Hyphens are legal in an Exment system name, dots are not, so the
        // first dot always separates the table from the rest.
        if (!preg_match('/^([-_a-zA-Z0-9]+)\.(.+)$/', $value, $matches)) {
            return $value;
        }

        return isset($map[$matches[1]]) ? $map[$matches[1]] . '.' . $matches[2] : $value;
    }

    /**
     * Views and forms are exported with a short uuid that identifies them
     * across installs. A prefixed install is a second, independent copy, so
     * it needs its own - derived from the prefix to stay stable when the same
     * prefix is installed again.
     *
     * @param array<mixed> $node
     * @return array<mixed>
     */
    protected static function renameSuuids(array $node, string $prefix): array
    {
        foreach ($node as $key => $value) {
            if (is_array($value)) {
                $node[$key] = static::renameSuuids($value, $prefix);
                continue;
            }

            if ($key === 'suuid' && is_string($value) && $value !== '') {
                $node[$key] = substr(md5($prefix . $value), 0, 20);
            }
        }

        return $node;
    }
}
