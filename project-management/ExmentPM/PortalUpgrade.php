<?php

namespace App\Plugins\ExmentPM;

use Exceedone\Exment\Enums\ColumnType;
use Exceedone\Exment\Enums\MenuType;
use Exceedone\Exment\Enums\MultisettingType;
use Exceedone\Exment\Enums\Permission;
use Exceedone\Exment\Enums\PluginType;
use Exceedone\Exment\Enums\RoleType;
use Exceedone\Exment\Enums\ShareTrigger;
use Exceedone\Exment\Enums\ViewKindType;
use Exceedone\Exment\Model\CustomColumn;
use Exceedone\Exment\Model\CustomColumnMulti;
use Exceedone\Exment\Model\CustomTable;
use Exceedone\Exment\Model\CustomView;
use Exceedone\Exment\Model\Menu;
use Exceedone\Exment\Model\Notify;
use Exceedone\Exment\Model\RoleGroup;
use Exceedone\Exment\Model\RoleGroupPermission;
use Exceedone\Exment\Model\System;
use Exceedone\Exment\Services\TemplateImportExport\TemplateImporter;
use Exceedone\Exment\ColumnItems\GridCellStyle;

/**
 * Brings an install up to the schema PortalSchema describes.
 *
 * This runs on installs that already have data, so every step is written to be
 * safe to repeat: the template fragment is imported with $is_update = true,
 * which makes Exment create what is missing and leave everything else exactly
 * as it is, seed rows are matched by name before being created, and a value is
 * only rewritten while it still looks like the old one.
 *
 * The interesting part is the conversion. Status, issue type and priority used
 * to be word lists baked into a column; Backlog makes them rows a project owns.
 * Changing that means three things have to happen in order and none of them can
 * happen twice:
 *
 *   1. read the words out of the column while it still has them,
 *   2. create one row per word - per project for status and issue type,
 *      because that is the whole point of the change,
 *   3. point the column at the new table and rewrite every stored value from
 *      the word to the id of its row.
 *
 * Step 1 before step 3 is why this is not a plain Exment template import.
 * The translation is stored before step 3 starts, so a run cut off halfway
 * through it is finished by the next one (finishConverted()). Only one run
 * goes at a time (exclusive()).
 */
class PortalUpgrade
{
    /**
     * Where the word-to-row translation lives in the plugin state.
     */
    const LEGACY_KEY = 'legacy_values';

    /**
     * The schema version whose structural steps last went through, with or
     * without the conversions. PortalSchema::STATE_KEY moves only when a run
     * converted as well, so the upgrade stays on offer after a structural
     * one; this one keeps the once-only steps from repeating meanwhile.
     */
    const STRUCTURE_KEY = 'portal_structure_version';

    /**
     * How long the cache lock outlives a run that never released it, on a
     * database without connection-held locks. See exclusive().
     */
    const LOCK_SECONDS = 3600;

    /**
     * How many times exclusive() has been entered by this process: setup
     * holds the lock and runs the upgrade, which asks for it again.
     *
     * @var int
     */
    protected static $lockDepth = 0;

    /**
     * The table names this install uses, for every role including the ones
     * this schema adds.
     *
     * @return array<string, string>
     */
    public static function names(): array
    {
        $prefix = PortalSetup::prefix();
        $stored = PortalSetup::tableMap();

        $names = [];
        foreach (PortalDefinition::tableRoles() as $role => $default) {
            $name = array_get($stored, $role);
            $names[$role] = (is_string($name) && $name !== '') ? $name : $prefix . $default;
        }

        return $names;
    }

    /**
     * Whether the stored schema version is behind this build.
     */
    public static function needed(): bool
    {
        if (!PortalSetup::isInstalled()) {
            return false;
        }

        return PortalSetup::schemaVersion() < PortalSchema::VERSION;
    }

    /**
     * Apply the schema.
     *
     * @param bool $convert false leaves the three select columns alone, which
     *                      is how the settings screen offers a dry structural
     *                      upgrade before touching stored values. Such a run
     *                      does not record the version, so the upgrade stays
     *                      on offer until the conversions have run too.
     * @return array{ok: bool, message: string, steps: array<int, string>, busy?: bool}
     */
    public static function run(bool $convert = true): array
    {
        static::keepRunning();

        return static::exclusive(function () use ($convert) {
            // The upgrade rewrites stored rows in bulk - issues of archived
            // projects included - so the integrity rules stand aside for the
            // duration. They come back on whatever path this returns through.
            return PortalRules::suspend(function () use ($convert) {
                return static::runGuarded($convert);
            });
        }, function () {
            return [
                'ok' => false,
                'busy' => true,
                'message' => PortalDefinition::trans('upgrade.busy'),
                'steps' => [],
            ];
        });
    }

    /**
     * Let a long run finish once it has started.
     *
     * The run rewrites stored values a row at a time. Stopped halfway - by
     * PHP's time limit, or by a browser or proxy that gave up waiting - it
     * leaves a column half converted. The next run repairs that (see
     * convert()), but nothing is gained by stopping in the first place.
     *
     * @return void
     */
    public static function keepRunning(): void
    {
        if (function_exists('ignore_user_abort')) {
            ignore_user_abort(true);
        }
        if (function_exists('set_time_limit')) {
            set_time_limit(0);
        }
    }

    /**
     * Run $work while no other setup or upgrade runs on this install.
     *
     * Both create rows matched by name, so two at once - a double submit, a
     * retry after a proxy timed out while the first request was still busy -
     * each find nothing, each create a status list for the same project, and
     * every issue then points at whichever list its run wrote last.
     *
     * On MySQL and MariaDB the lock belongs to the database connection, so it
     * ends with the process however that ends and a crashed run cannot lock
     * the next one out. Other databases use a cache lock, which expires on
     * its own after LOCK_SECONDS. A lock that cannot be taken at all is
     * logged and the work runs anyway: refusing every upgrade would be worse
     * than the race it guards against.
     *
     * @param callable $work
     * @param callable $busy what to answer instead when another run holds it
     * @return mixed
     */
    public static function exclusive(callable $work, callable $busy)
    {
        if (static::$lockDepth > 0) {
            static::$lockDepth++;
            try {
                return $work();
            } finally {
                static::$lockDepth--;
            }
        }

        $release = static::acquireLock();
        if ($release === null) {
            return $busy();
        }

        static::$lockDepth = 1;
        try {
            // A request that read the state while another run was still
            // going holds a copy that run has since replaced; read it again
            // now that nobody else writes.
            PortalSetup::forgetState();

            return $work();
        } finally {
            static::$lockDepth = 0;
            $release();
        }
    }

    /**
     * @return callable|null what releases the lock, or null when another run holds it
     */
    protected static function acquireLock(): ?callable
    {
        $connection = \DB::connection();
        $name = 'pmportal_upgrade_' . substr(md5(strval($connection->getDatabaseName()) . '|' . PortalDefinition::pluginName()), 0, 16);

        try {
            if (in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)) {
                // 1 taken, 0 held by another connection, NULL an error.
                $got = $connection->selectOne('SELECT GET_LOCK(?, 0) AS got', [$name]);
                $value = $got->got ?? null;
                if ($value === null) {
                    throw new \RuntimeException('GET_LOCK answered NULL');
                }
                if (intval($value) !== 1) {
                    return null;
                }

                return function () use ($connection, $name) {
                    try {
                        $connection->selectOne('SELECT RELEASE_LOCK(?) AS released', [$name]);
                    } catch (\Throwable $e) {
                        \Log::warning('PmPortal could not release the upgrade lock: ' . $e->getMessage());
                    }
                };
            }

            $lock = \Cache::lock($name, self::LOCK_SECONDS);
            if (!$lock->get()) {
                return null;
            }

            return function () use ($lock) {
                $lock->release();
            };
        } catch (\Throwable $e) {
            \Log::warning('PmPortal could not take the upgrade lock, running without it: ' . $e->getMessage());

            return function () {
            };
        }
    }

    /**
     * A short code that ties a message on screen to its line in the log.
     *
     * The exception itself stays in the log: it is English, and a database
     * error carries the SQL, the host and the database name.
     *
     * @return string
     */
    public static function errorRef(): string
    {
        return strtoupper(substr(md5(uniqid('pmportal', true)), 0, 6));
    }

    /**
     * @param bool $convert
     * @return array{ok: bool, message: string, steps: array<int, string>}
     */
    protected static function runGuarded(bool $convert): array
    {
        $steps = [];
        $from = PortalSetup::schemaVersion();
        // The version the once-only steps below are gated on: a structural
        // run without the conversions has applied them as well.
        $applied = max($from, intval(array_get(PortalSetup::state(), self::STRUCTURE_KEY, 0)));

        try {
            $names = static::names();

            // config.json is read only when the plugin is uploaded, so the
            // plugin row is brought up to date here: its types, the tables
            // whose saves it checks, its event triggers and the batch
            // schedule. First, so that a run failing further down has still
            // put the integrity rules in front of every table they guard.
            if (static::syncPluginTypes()) {
                $steps[] = PortalDefinition::trans('upgrade.step_plugin_types');
            }
            if (static::registerPluginRow($names)) {
                $steps[] = PortalDefinition::trans('upgrade.step_plugin_row');
            }

            // Everything below goes in with $is_update = true, so Exment
            // creates what is missing and leaves the rest exactly as it is.
            // Notifications are not part of it: Exment's template importer
            // has no notifies section and drops the key without a word, which
            // is how ex1task ran for months with no "issue updated" bell at
            // all. createMissingNotifies() writes them itself, after the
            // tables exist (ISS00343).
            $fragment = PortalSchema::template($names, PortalSetup::prefix());
            unset($fragment['notifies']);

            $importer = new TemplateImporter();
            $importer->import($fragment, false, true);
            $steps[] = PortalDefinition::trans('upgrade.step_tables');

            PortalSetup::syncTables();
            System::clearCache();

            $notified = static::createMissingNotifies($names);
            if ($notified > 0) {
                $steps[] = PortalDefinition::trans('upgrade.step_notifies', ['count' => $notified]);
            }

            $seeded = static::seedResolutions($names);
            if ($seeded > 0) {
                $steps[] = PortalDefinition::trans('upgrade.step_resolutions', ['count' => $seeded]);
            }

            $granted = static::grantPermissions($names);
            if ($granted > 0) {
                $steps[] = PortalDefinition::trans('upgrade.step_roles', ['count' => $granted]);
            }

            $revoked = static::revokeRetiredGrants($names, $applied);
            if ($revoked > 0) {
                $steps[] = PortalDefinition::trans('upgrade.step_revoked', ['count' => $revoked]);
            }

            $opened = static::grantPluginAccess();
            if ($opened > 0) {
                $steps[] = PortalDefinition::trans('upgrade.step_plugin_access', ['count' => $opened]);
            }

            $retargeted = static::syncNotifyTargets($names);
            if ($retargeted > 0) {
                $steps[] = PortalDefinition::trans('upgrade.step_notify_targets', ['count' => $retargeted]);
            }

            $optioned = static::applyTableOptions($names);
            if ($optioned > 0) {
                $steps[] = PortalDefinition::trans('upgrade.step_table_options', ['count' => $optioned]);
            }

            // Schema 23: the organization columns become share columns.
            // Idempotent, so no version gate.
            $shared = static::syncShareSettings($names);
            if ($shared > 0) {
                $steps[] = PortalDefinition::trans('upgrade.step_share_settings', ['count' => $shared]);
            }

            // Schema 8 is where the per project switches became something a
            // user can actually see and set. Until then the columns existed
            // but nothing offered them, so whatever sits in a row is an
            // artefact of a form save rather than a decision - and a stray 0
            // would silently take a project's Wiki or file tab away. Bringing
            // every row up to the schema's own defaults happens exactly once,
            // on the way past version 8; a switch turned off afterwards stays
            // off however often the upgrade is re-run.
            if ($applied < 8) {
                $flagged = static::seedFeatureFlags($names);
                if ($flagged > 0) {
                    $steps[] = PortalDefinition::trans('upgrade.step_feature_flags', ['count' => $flagged]);
                }
            }

            // Schema 30: a new project starts with the basics only. Every
            // project there already is keeps what it has: an empty switch
            // (read as on) is written as on first, then the default of the
            // column turns off. Once, on the way past 30.
            if ($applied < 30) {
                $quieted = static::quietNewProjects($names);
                if ($quieted > 0) {
                    $steps[] = PortalDefinition::trans('feat_upgrade_step', ['count' => $quieted]);
                }
            }

            static::repairPluginMenu();

            // Schema 25: one sidebar entry (ISS00256). On the way past 26,
            // not 25: an install that took 1.5.0 from its branch (ex1task)
            // ran the Backlog columns as 25 and so never met this. It finds
            // the plugin's entries by their internal menu_name
            // (pm_portal_root and the like), which nobody types for a menu
            // of their own, so a second pass on an install that did run
            // 1.4.1's 25 finds nothing left to take.
            if ($applied < 26) {
                $tidied = static::tidyLegacyMenus($names);
                if ($tidied > 0) {
                    $steps[] = PortalDefinition::trans('upgrade.step_menus', ['count' => $tidied]);
                }
            }

            if ($convert) {
                foreach (PortalSchema::conversions($names) as $conversion) {
                    $result = static::convert($conversion);
                    if (isset($result)) {
                        $steps[] = $result;
                    }
                }
            }

            $repaired = static::repairUntranslatedNames($names);
            if ($repaired > 0) {
                $steps[] = PortalDefinition::trans('upgrade.step_repair_names', ['count' => $repaired]);
            }

            // Schema 9: the project key becomes a required column. The rules
            // already refuse an empty key on every form that submits the
            // field; required at the column level is what covers a create
            // that never mentions it - a minimal API body, a CSV without the
            // column. Idempotent, so no version gate.
            if (static::markProjectCodeRequired($names)) {
                $steps[] = PortalDefinition::trans('upgrade.step_code_required');
            }

            $boards = static::repairKanbanKeys($names);
            if ($boards > 0) {
                $steps[] = PortalDefinition::trans('upgrade.step_kanban_keys', ['count' => $boards]);
            }

            // Schema 12: an issue with no status sits in no board column, so
            // the card is not on the board at all - the quietest way for work
            // to go missing here. Fill what is already empty first, then make
            // the two fields required so nothing lands that way again.
            $filled = static::fillIssueDefaults($names);
            if ($filled > 0) {
                $steps[] = PortalDefinition::trans('upgrade.step_issue_defaults', ['count' => $filled]);
            }

            if (static::markIssueFieldsRequired($names)) {
                $steps[] = PortalDefinition::trans('upgrade.step_issue_required');
            }

            PortalSetup::patchState([self::STRUCTURE_KEY => PortalSchema::VERSION]);
            if ($convert) {
                PortalSetup::setSchemaVersion(PortalSchema::VERSION);
            } else {
                // The version says the stored values are in the new shape too.
                // Recorded now, it would hide the upgrade for good and the
                // conversions would never run.
                $steps[] = PortalDefinition::trans('upgrade.step_convert_skipped');
            }
            System::clearCache();
        } catch (\Throwable $e) {
            $ref = static::errorRef();
            \Log::error('PmPortal schema upgrade failed [' . $ref . ']: ' . $e->getMessage(), ['exception' => $e]);

            return [
                'ok' => false,
                'message' => PortalDefinition::trans('upgrade.error', ['ref' => $ref]),
                'steps' => $steps,
            ];
        }

        return [
            'ok' => true,
            'message' => PortalDefinition::trans('upgrade.success'),
            'steps' => $steps,
        ];
    }

    /**
     * The notifications this install has not got yet.
     *
     * Matched on notify_name rather than the display name Exment treats as
     * the unique key, so renaming one on screen cannot produce a duplicate on
     * the next upgrade. One deleted on purpose does come back at the next
     * schema version, the same as a permission row would.
     *
     * @param array<int, array<string, mixed>> $notifies
     * @return array<int, array<string, mixed>>
     */
    protected static function missingNotifies(array $notifies): array
    {
        return array_values(array_filter($notifies, function ($notify) {
            $name = array_get($notify, 'notify_name');

            return is_string($name) && $name !== '' && !Notify::where('notify_name', $name)->exists();
        }));
    }

    /**
     * Write the portal's notifications that this install has not got yet.
     *
     * PortalSchema::notifies() describes them the way a template file would:
     * a column target as "table.column", which only the template importer
     * knows how to read - and that importer skips notifications altogether.
     * So they are stored here the way Exment's own notify screen stores one:
     * the target table by id, a column target by the column's id, and the
     * standard "data saved" mail template, which the in-app bell also takes
     * its subject and body from.
     *
     * @param array<string, string> $names
     * @return int notifications created
     */
    protected static function createMissingNotifies(array $names): int
    {
        $missing = static::missingNotifies(PortalSchema::notifies($names, PortalSetup::prefix()));
        if (empty($missing)) {
            return 0;
        }
        $template = CustomTable::getEloquent('mail_template');
        $mail = isset($template)
            ? $template->getValueModel()->where('value->mail_key_name', 'data_saved_notify')->first()
            : null;

        $created = 0;
        foreach ($missing as $notify) {
            $table = CustomTable::getEloquent(strval(array_get($notify, 'target_table_name')));
            if (!isset($table)) {
                continue;
            }

            $actions = [];
            foreach ((array) array_get($notify, 'action_settings', []) as $action) {
                $targets = [];
                foreach ((array) array_get($action, 'notify_action_target', []) as $target) {
                    $target = strval($target);
                    if (strpos($target, '.') === false) {
                        $targets[] = $target;
                        continue;
                    }
                    list($table_name, $column_name) = explode('.', $target, 2);
                    $target_table = CustomTable::getEloquent($table_name);
                    $column = isset($target_table) ? CustomColumn::getEloquent($column_name, $target_table) : null;
                    if (isset($column)) {
                        $targets[] = strval($column->id);
                    }
                }
                // mail_subject is a template-file convenience; the mail
                // template above carries the subject.
                unset($action['mail_subject']);
                $action['notify_action_target'] = $targets;
                $actions[] = $action;
            }

            $trigger = (array) array_get($notify, 'trigger_settings', []);
            // A null here would be read as a schedule date column.
            unset($trigger['notify_target_date']);

            $model = new Notify();
            $model->notify_name = array_get($notify, 'notify_name');
            $model->notify_view_name = array_get($notify, 'notify_view_name');
            $model->notify_trigger = array_get($notify, 'notify_trigger');
            $model->target_id = $table->id;
            $model->active_flg = 1;
            $model->mail_template_id = isset($mail) ? $mail->id : null;
            $model->trigger_settings = $trigger;
            $model->action_settings = $actions;
            $model->save();
            $created++;
        }

        return $created;
    }

    /**
     * Put the notified-users column, and since schema 27 the sub-assignee,
     * manager and reviewer columns, on the saved notification's target list.
     *
     * missingNotifies() only ever adds whole notifications, so an install
     * that already had the issue notification when the column arrived kept
     * telling assignees and author only - the field collected names and
     * nothing ever reached them. Idempotent: a target already present is
     * left alone, and whatever an administrator added of their own survives.
     *
     * @param array<string, string> $names resolved table names
     * @return int notifications changed
     */
    protected static function syncNotifyTargets(array $names): int
    {
        $table = CustomTable::getEloquent($names['issue_table']);
        if (!isset($table)) {
            return 0;
        }
        // Schema 27: the sub-assignees, managers and reviewers are told too.
        $wanted = [];
        $dotted = [];
        foreach (array_merge(['notified_users'], PortalSchema::ROLE_COLUMNS) as $role) {
            $column_name = array_get(PortalDefinition::config(), 'issue_extra.' . $role, PortalSchema::ISSUE_COLUMNS[$role]);
            $column = CustomColumn::getEloquent($column_name, $table);
            if (!isset($column)) {
                continue;
            }
            // A saved notification names its column targets by id - the
            // assignees target already reads "651", not "pm_issue.assignees".
            // The dotted form only exists inside template files, where the
            // importer translates it; stored raw it would never match a user.
            $wanted[] = strval($column->id);
            $dotted[] = $names['issue_table'] . '.' . $column_name;
        }
        if (empty($wanted)) {
            return 0;
        }

        $changed = 0;
        $name = PortalSetup::prefix() . PortalSchema::NOTIFY_ISSUE_SAVED;
        foreach (Notify::where('notify_name', $name)->get() as $notify) {
            $settings = $notify->action_settings;
            if (!is_array($settings)) {
                continue;
            }
            $dirty = false;
            foreach ($settings as $index => $setting) {
                $targets = array_get($setting, 'notify_action_target', []);
                if (!is_array($targets)) {
                    continue;
                }
                // absorb the dotted form an earlier run may have written
                $cleaned = array_values(array_filter($targets, function ($t) use ($dotted) {
                    return !in_array($t, $dotted, true);
                }));
                foreach ($wanted as $target) {
                    if (!in_array($target, $cleaned)) {
                        $cleaned[] = $target;
                    }
                }
                if ($cleaned == $targets) {
                    continue;
                }
                $settings[$index]['notify_action_target'] = $cleaned;
                $dirty = true;
            }
            if ($dirty) {
                $notify->action_settings = $settings;
                $notify->save();
                $changed++;
            }
        }

        return $changed;
    }

    /**
     * Give an issue with no status, or no type, its project's first one.
     *
     * A board column is a status row, so an issue holding none is in no
     * column and its card is nowhere on the board. Nothing was lost, but to
     * anyone reading the board it may as well have been.
     *
     * What gets written is what the create form would have offered: the top
     * row of that project's own list. An issue in a project whose lists were
     * never seeded is left alone rather than pointed at another project's
     * copy - seedProject() is the fix for that one, not this.
     *
     * @param array<string, string> $names
     * @return int rows filled
     */
    protected static function fillIssueDefaults(array $names): int
    {
        $table = CustomTable::getEloquent(array_get($names, 'issue_table'));
        if (!isset($table)) {
            return 0;
        }

        $def = PortalDefinition::config();
        $targets = [];
        foreach (['status' => 'status_table', 'type' => 'issue_type_table'] as $key => $role) {
            $column_name = array_get($def, 'issue.' . $key);
            $target = CustomTable::getEloquent(array_get($names, $role));
            $column = (isset($column_name) && isset($target)) ? CustomColumn::getEloquent($column_name, $table) : null;
            // Only a column that already points at the table: a row id
            // written into one still holding words would be a word nobody
            // offers. A run without the conversions leaves those alone.
            if (!isset($column) || $column->column_type != ColumnType::SELECT_TABLE
                || intval($column->getOption('select_target_table')) !== intval($target->id)) {
                continue;
            }
            $targets[$column_name] = $target;
        }
        if (empty($targets)) {
            return 0;
        }

        $firsts = [];
        $filled = 0;

        $table->getValueModel()->newQuery()->chunkById(200, function ($rows) use ($targets, &$firsts, &$filled) {
            foreach ($rows as $row) {
                $patch = [];
                foreach ($targets as $column_name => $target) {
                    if (!is_nullorempty(array_get($row->value, $column_name))) {
                        continue;
                    }

                    // The status rows hang off the same project the issue
                    // does, so the issue's own parent is the scope to look in.
                    $first = static::firstScopedRow($target, strval($row->parent_type), $row->parent_id, $firsts);
                    if (isset($first)) {
                        $patch[$column_name] = $first;
                    }
                }
                if (empty($patch)) {
                    continue;
                }

                $row->saved_notify(false);
                $row->setValue($patch);
                $row->save();
                $filled++;
            }
        });

        return $filled;
    }

    /**
     * The row a picker puts at the top of $target for one project.
     *
     * Memoised across the pass: a thousand issues spread over ten projects
     * ask twenty questions, not two thousand.
     *
     * @param CustomTable $target
     * @param string $parent_type
     * @param mixed $parent_id
     * @param array<int, array<string, string|null>> $cache
     * @return string|null
     */
    protected static function firstScopedRow(CustomTable $target, string $parent_type, $parent_id, array &$cache): ?string
    {
        $key = strval($parent_id);
        if ($parent_type === '' || !ctype_digit($key)) {
            return null;
        }
        if (array_key_exists($key, array_get($cache, $target->id, []))) {
            return $cache[$target->id][$key];
        }

        $query = $target->getValueModel()->newQuery()
            ->where('parent_type', $parent_type)
            ->where('parent_id', $parent_id);

        $order = array_get(PortalSchema::COLUMNS, 'status.order');
        if (isset($order) && CustomColumn::getEloquent($order, $target) !== null) {
            $query->orderByRaw("CAST(JSON_UNQUOTE(JSON_EXTRACT(value, '$." . $order . "')) AS UNSIGNED) asc");
        }

        $row = $query->orderBy('id')->first();
        $cache[$target->id][$key] = isset($row) ? strval($row->id) : null;

        return $cache[$target->id][$key];
    }

    /**
     * Make status and issue type required, once.
     *
     * The create form fills both in before anyone sees it, so requiring them
     * costs a user nothing. What it stops is the write that never mentions
     * them at all - a short API body, a CSV without the column - which is how
     * an issue ends up on a board that cannot show it.
     *
     * @param array<string, string> $names
     * @return bool
     */
    protected static function markIssueFieldsRequired(array $names): bool
    {
        $def = PortalDefinition::config();
        $changed = false;

        foreach (['status', 'type'] as $key) {
            $column = CustomColumn::getEloquent(
                array_get($def, 'issue.' . $key),
                array_get($names, 'issue_table')
            );
            if (!isset($column) || boolval($column->getOption('required'))) {
                continue;
            }

            $column->setOption('required', '1');
            $column->save();
            $changed = true;
        }

        return $changed;
    }

    /**
     * Drop board settings that name a status by the word it used to be.
     *
     * The board once grouped on a fixed select column, so its "finished" and
     * WIP settings could name a value outright - status::closed. The status
     * list has since become a table each project writes for itself, where the
     * same state is a different row id in every project, and one view shared
     * by all of them cannot name any of them. Those settings match nothing
     * now: the WIP limit never triggers, the done column is never marked.
     *
     * The board reads "finished" off the list itself instead - a status row
     * says whether it means the work is over - so what is left here is dead
     * weight that would only mislead whoever opens the view setting next.
     *
     * An entry written after the move survives: only one whose value is not a
     * row id is dropped.
     *
     * @param array<string, string> $names
     * @return int views changed
     */
    protected static function repairKanbanKeys(array $names): int
    {
        $table = CustomTable::getEloquent(array_get($names, 'issue_table'));
        if (!isset($table)) {
            return 0;
        }

        $changed = 0;
        $views = CustomView::where('custom_table_id', $table->id)
            ->where('view_kind_type', ViewKindType::KANBAN)
            ->get();

        foreach ($views as $view) {
            $column = CustomColumn::getEloquent($view->getOption('kanban_group_column_id'));
            if (!isset($column) || $column->column_type != ColumnType::SELECT_TABLE) {
                continue;
            }

            $dirty = false;
            foreach (['kanban_done_keys' => null, 'kanban_wips' => 'kanban_wip_key'] as $key => $inner) {
                $stored = $view->getOption($key);
                if (is_nullorempty($stored)) {
                    continue;
                }

                $kept = array_values(array_filter((array) $stored, function ($entry) use ($inner) {
                    return static::isRowIdSetting(isset($inner) ? array_get($entry, $inner) : $entry);
                }));
                if (count($kept) === count((array) $stored)) {
                    continue;
                }

                // Null, not forgetOption(): CustomView::saving() puts back any
                // key the original json had and the new one does not, so that a
                // form which posts half the settings cannot wipe the other
                // half. A key that is present and null survives that and is
                // dropped from the json straight after.
                $view->setOption($key, empty($kept) ? null : $kept);
                $dirty = true;
            }

            // The note above the board promised the WIP limits that just went.
            // Rewritten only while it is still word for word the one the
            // template shipped, so an administrator who put their own wording
            // there keeps it.
            if (strval($view->getOption('view_infobox')) === PortalDefinition::trans('schema.view.board_infobox_wip')) {
                $view->setOption('view_infobox', PortalDefinition::trans('schema.view.board_infobox'));
                $dirty = true;
            }

            if (!$dirty) {
                continue;
            }

            $view->save();
            $changed++;
        }

        return $changed;
    }

    /**
     * Whether a board setting names a value by row id.
     *
     * Both settings store "<column id>::<value>". A select_table column holds
     * a row id, so anything else in that second half is left over from the
     * fixed list the column used to be.
     *
     * @param mixed $value
     * @return bool
     */
    protected static function isRowIdSetting($value): bool
    {
        if (!is_string($value)) {
            return false;
        }
        $parts = explode('::', $value, 2);

        return count($parts) === 2 && ctype_digit($parts[1]);
    }

    /**
     * Make the project key column required, once.
     *
     * @param array<string, string> $names
     * @return bool whether the flag actually changed
     */
    protected static function markProjectCodeRequired(array $names): bool
    {
        $columnName = array_get(PortalDefinition::config(), 'project.key', 'project_code');
        $column = CustomColumn::getEloquent($columnName, array_get($names, 'project_table'));
        if (!isset($column) || boolval($column->getOption('required'))) {
            return false;
        }

        $column->setOption('required', '1');
        $column->save();

        return true;
    }

    // ---- seeding -------------------------------------------------------

    /**
     * Rewrite master rows that were seeded with an unresolved translation key.
     *
     * Before seedLabel() existed, a run whose lang namespace had not been
     * registered wrote the key itself as the row name, so pm_status grew rows
     * literally called "exment_pm_portal::pmportal.schema.values.status.todo".
     * The next run's name match then missed them and seeded the list a second
     * time, which is why the damage shows up as a duplicated master list.
     *
     * A poisoned row whose proper name already exists in the same scope is
     * that duplicate: anything pointing at it is moved onto the real row and
     * the copy goes. A poisoned row with no twin is the only one of its kind,
     * so it is renamed instead and nothing is lost either way.
     *
     * @param array<string, string> $names
     * @return int rows repaired
     */
    protected static function repairUntranslatedNames(array $names): int
    {
        $repaired = 0;

        $kinds = [
            'status_table' => 'status',
            'issue_type_table' => 'issue_type',
            'priority_table' => 'priority',
            'resolution_table' => 'resolution',
        ];

        foreach ($kinds as $role => $kind) {
            $table = CustomTable::getEloquent(array_get($names, $role));
            $column = array_get(PortalSchema::COLUMNS, $kind . '.name');
            if (!isset($table) || !isset($column)) {
                continue;
            }

            $rows = $table->getValueModel()->query()->get();

            foreach ($rows as $row) {
                $stored = strval($row->getValue($column));
                $key = PortalDefinition::untranslatedKey($stored);
                if ($key === null) {
                    continue;
                }

                $label = PortalDefinition::trans($key);
                if (PortalDefinition::isUnresolved($label)) {
                    // Still unreadable, so renaming would only swap one broken
                    // name for another. Leave the row for a later run.
                    continue;
                }

                $twin = $rows->first(function ($other) use ($row, $column, $label) {
                    return $other->id != $row->id
                        && strval($other->getValue($column)) === strval($label)
                        && strval($other->getAttribute('parent_id')) === strval($row->getAttribute('parent_id'));
                });

                if (isset($twin)) {
                    static::repointReferences($table, $row->id, $twin->id);

                    if (method_exists($row, 'setValidationDestroy')) {
                        $row->setValidationDestroy(true);
                    }
                    $row->delete();
                } else {
                    $row->saved_notify(false);
                    $row->setValue($column, strval($label));
                    $row->save();
                }

                $repaired++;
            }
        }

        return $repaired;
    }

    /**
     * Move every pointer at one master row onto another so the first can go.
     *
     * Only reached while repairing a duplicated master list, so the sweep over
     * the value tables costs nothing on a healthy install.
     *
     * @param CustomTable $table the master table being cleaned
     * @param mixed $from row id being retired
     * @param mixed $to row id to keep
     * @return int values rewritten
     */
    protected static function repointReferences(CustomTable $table, $from, $to): int
    {
        $moved = 0;

        foreach (CustomTable::all() as $other) {
            foreach ($other->custom_columns_cache as $column) {
                $target = array_get($column->options, 'select_target_table');
                if (!isset($target) || intval($target) !== intval($table->id)) {
                    continue;
                }

                $name = $column->column_name;

                foreach ($other->getValueModel()->query()->get() as $row) {
                    $raw = $row->getValue($name, true);
                    if (!isset($raw)) {
                        continue;
                    }

                    if (is_array($raw)) {
                        $new = array_map(function ($one) use ($from, $to) {
                            return intval($one) === intval($from) ? strval($to) : strval($one);
                        }, $raw);

                        if ($new === array_map('strval', $raw)) {
                            continue;
                        }
                    } else {
                        if (intval($raw) !== intval($from)) {
                            continue;
                        }
                        $new = strval($to);
                    }

                    $row->saved_notify(false);
                    if (method_exists($row, 'disable_saving_event')) {
                        $row->disable_saving_event(true);
                    }
                    $row->setValue($name, $new);
                    $row->save();
                    $moved++;
                }
            }
        }

        return $moved;
    }

    /**
     * A translated string that is about to become a master row name.
     *
     * trans() hands back the key itself when a line is missing, and an earlier
     * build persisted exactly that: rows named
     * "exment_pm_portal::pmportal.schema.values.status.todo". Those rows then
     * failed the name match on the next run and were seeded a second time, so
     * the list grew a duplicate set every upgrade. Failing the upgrade is the
     * better outcome - the message reaches the settings screen, and the master
     * data stays clean.
     *
     * @param string $key
     * @return string
     * @throws \Exception
     */
    protected static function seedLabel(string $key): string
    {
        $label = PortalDefinition::trans($key);

        if (PortalDefinition::isUnresolved($label)) {
            throw new \Exception('Missing translation for seed label: ' . $key);
        }

        return strval($label);
    }

    /**
     * Close reasons, shared by the whole space. They have no select column to
     * grow out of, so the list comes from PortalSchema.
     *
     * Seeded into an empty table only. Once the list has a row - deleted ones
     * included - it is the administrator's, the same rule convert() applies
     * to the other lists: matching rows by the shipped labels would bring
     * back every reason that was renamed or removed on purpose, at every
     * schema bump.
     *
     * @param array<string, string> $names
     * @return int rows created
     */
    protected static function seedResolutions(array $names): int
    {
        $table = CustomTable::getEloquent(array_get($names, 'resolution_table'));
        if (!isset($table) || $table->getValueModel()->newQuery()->withTrashed()->exists()) {
            return 0;
        }

        $c = PortalSchema::COLUMNS['resolution'];
        $created = 0;
        $order = 0;

        foreach (PortalSchema::resolutionKeys() as $key) {
            $label = static::seedLabel('schema.values.resolution.' . $key);
            if (static::findByName($table, $c['name'], $label, null) === null) {
                static::createRow($table, [$c['name'] => $label, $c['order'] => $order], null);
                $created++;
            }
            $order++;
        }

        return $created;
    }

    /**
     * Bring the plugin row's registration up to what the portal runs on.
     *
     * - target_tables: Plugin::getPluginsByTable only consults this list, so
     *   a table that is not on it never reaches the validator or the event
     *   half - the rules would exist and quietly do nothing there. The
     *   install's own names are added (PortalSchema::EVENT_TABLE_ROLES);
     *   anything else already listed stays, another feature may rely on it.
     *   config.json deliberately carries no list: Exment's installer copies
     *   one over the stored list at every re-upload, and the file cannot know
     *   the prefix an install chose, so a prefixed install would lose its own
     *   tables to another install's.
     * - event_triggers: SAVING carries the integrity rules, SAVED seeds a new
     *   project's lists and keeps memberships in line. Triggers the portal
     *   does not handle are taken off (PortalSchema::RETIRED_EVENT_TRIGGERS).
     * - batch_hour: a row that never had one runs the AI back-fill every night
     *   at 00:00 (see PortalSchema::BATCH_HOUR_OFF). Set only while unset, so
     *   an hour an operator chose stays.
     * - plugin_types gains validator and event, without which Exment never
     *   loads either half.
     *
     * @param array<string, string> $names
     * @return bool whether the row changed
     */
    protected static function registerPluginRow(array $names): bool
    {
        $plugin = PortalDefinition::plugin();
        if (!isset($plugin)) {
            return false;
        }

        // Getting this wrong is not subtle: Exment resolves the page class by
        // name from the type list, so a stale list means the portal's own
        // routes disappear.
        $types = array_values(array_filter(array_map('strval', (array) $plugin->plugin_types), 'strlen'));
        foreach ([PluginType::VALIDATOR, PluginType::EVENT] as $type) {
            if (!in_array($type, $types, true)) {
                $types[] = $type;
            }
        }
        // The setter parses a comma separated string; handing it an array
        // reaches explode() and throws.
        $plugin->plugin_types = implode(',', $types);

        $options = (array) $plugin->options;
        $options['target_tables'] = static::wantedTargetTables(array_get($options, 'target_tables', []), $names);
        $options['event_triggers'] = static::wantedEventTriggers(array_get($options, 'event_triggers', []));
        if (is_nullorempty(array_get($options, 'batch_hour')) && is_nullorempty(array_get($options, 'batch_cron'))) {
            $options['batch_hour'] = PortalSchema::BATCH_HOUR_OFF;
        }
        $plugin->options = $options;

        // The description shows on Exment's plugin list. Up to 16 the file
        // shipped it in English; a row still carrying that text takes the
        // file's, a wording an administrator wrote stays.
        $declared = strval(array_get(static::declaredConfig(), 'description', ''));
        if ($declared !== '' && static::isShippedEnglishDescription(strval($plugin->description))) {
            $plugin->description = $declared;
        }

        if (!$plugin->isDirty()) {
            return false;
        }
        $plugin->save();
        System::clearCache();

        return true;
    }

    /**
     * Whether a plugin description is the one the plugin itself used to ship,
     * or nothing at all. Every shipped wording opened with "Backlog style";
     * one written by hand - in whatever language - reads otherwise and is
     * left alone.
     *
     * @param string $text
     * @return bool
     */
    public static function isShippedEnglishDescription(string $text): bool
    {
        $text = trim($text);

        return $text === '' || preg_match('/^Backlog[ -]style\b/i', $text) === 1;
    }

    /**
     * The plugin's own config.json, decoded. Empty when the file is missing
     * or unreadable, so every reader falls back to leaving the row alone.
     *
     * @return array<string, mixed>
     */
    protected static function declaredConfig(): array
    {
        $path = __DIR__ . '/config.json';
        if (!file_exists($path)) {
            return [];
        }
        $config = json_decode(strval(file_get_contents($path)), true);

        return is_array($config) ? $config : [];
    }

    /**
     * The target table list with the portal's own tables added.
     *
     * @param mixed $current the stored list
     * @param array<string, string> $names role => real table name
     * @return array<int, string>
     */
    public static function wantedTargetTables($current, array $names): array
    {
        $list = array_values(array_filter(array_map('strval', stringToArray($current)), 'strlen'));
        foreach (PortalSchema::EVENT_TABLE_ROLES as $role) {
            $name = strval(array_get($names, $role, ''));
            if ($name !== '' && !in_array($name, $list, true)) {
                $list[] = $name;
            }
        }

        return $list;
    }

    /**
     * The event trigger list the portal wants: its own triggers present,
     * retired ones gone, anything else left as it was.
     *
     * @param mixed $current the stored list
     * @return array<int, string>
     */
    public static function wantedEventTriggers($current): array
    {
        $list = array_values(array_filter(array_map('strval', stringToArray($current)), function ($trigger) {
            return $trigger !== '' && !in_array($trigger, PortalSchema::RETIRED_EVENT_TRIGGERS, true);
        }));
        foreach (PortalSchema::EVENT_TRIGGERS as $trigger) {
            if (!in_array($trigger, $list, true)) {
                $list[] = $trigger;
            }
        }

        return array_values(array_unique($list));
    }

    // ---- permissions ---------------------------------------------------

    /**
     * Make sure the portal's own menu entry points at the plugin.
     *
     * A plugin menu with no target is silently dropped when the sidebar is
     * drawn, so the entry looks created but never appears. The import leaves
     * an existing menu row untouched, which means a row written before this
     * was understood would stay broken for good unless it is repaired here.
     *
     * @return void
     */
    protected static function repairPluginMenu(): void
    {
        static::syncSettingsMenu();

        $menu = Menu::where('menu_name', PortalSetup::prefix() . PortalSchema::MENU_PAGE)->first();
        if (!isset($menu) || $menu->menu_type != MenuType::PLUGIN) {
            return;
        }

        // the group "プロジェクト" of the feature map (Exment 7.2.1): only an
        // entry still at the top level moves; one an administrator moved stays
        $group = PortalSchema::menuGroupId();
        if (isset($group) && intval($menu->parent_id) === 0) {
            $menu->parent_id = $group;
            $menu->order = 1;
            $menu->save();
        }

        $plugin = PortalDefinition::plugin();
        if (!isset($plugin) || strval($menu->menu_target) === strval($plugin->id)) {
            return;
        }

        $menu->menu_target = $plugin->id;
        $menu->save();
    }

    /**
     * Take the sidebar down to the single "プロジェクト管理" entry (ISS00256).
     *
     * Before 1.4.1 an install got two branches: the schema's (a root node
     * with the portal, the settings link and a master node holding the five
     * master tables) and the bundled template's (a node with the project,
     * issue, milestone and category tables). Neither is created any more;
     * this removes what older versions left behind and lifts the portal entry
     * to the top level, in the root's place.
     *
     * Only the plugin's own rows go, found by their menu_name: a table entry
     * only while it still sits under the node the plugin put it in, a node
     * only once nothing else is left under it. Anything an administrator
     * added or moved there therefore stays, and so does its parent. Nothing
     * else changes - the tables, the views and the screens are all still
     * there, and the settings and master lists open from the portal's
     * ⚙ 設定. The portal entry keeps any title or icon an administrator gave
     * it; only the old defaults are replaced.
     *
     * @param array<string, string> $names
     * @return int menu rows removed or moved
     */
    protected static function tidyLegacyMenus(array $names): int
    {
        $prefix = PortalSetup::prefix();
        $count = 0;

        $find = function (string $name) {
            return Menu::query()->where('menu_name', $name)->first();
        };

        // Table entries under a node, by the names the plugin gave them.
        $dropTables = function ($node, array $tableNames) use (&$count) {
            if (!isset($node)) {
                return;
            }
            $rows = Menu::query()->where('parent_id', $node->id)
                ->where('menu_type', MenuType::TABLE)
                ->whereIn('menu_name', array_values(array_unique(array_filter($tableNames))))
                ->get();
            foreach ($rows as $row) {
                $row->delete();
                $count++;
            }
        };

        // A node goes only when nothing is left under it.
        $dropNode = function ($node) use (&$count) {
            if (!isset($node) || Menu::query()->where('parent_id', $node->id)->exists()) {
                return;
            }
            $node->delete();
            $count++;
        };

        $root = $find($prefix . PortalSchema::MENU_ROOT);
        $master = $find($prefix . PortalSchema::MENU_MASTER);
        $settings = $find($prefix . PortalSchema::MENU_SETTINGS);
        $page = $find($prefix . PortalSchema::MENU_PAGE);
        $template = $find($prefix . PortalSchema::MENU_TEMPLATE);

        // The schema's master node: the five master tables, then the node.
        // Before 17 the priority and resolution tables did not follow the
        // install prefix, so the unprefixed name counts too - under this
        // node it can only be the plugin's own entry.
        $masterTables = [];
        foreach (PortalSchema::TABLE_ROLES as $role => $default) {
            $masterTables[] = array_get($names, $role);
            $masterTables[] = $prefix . $default;
            $masterTables[] = $default;
        }
        $dropTables($master, $masterTables);
        if (isset($master) && $master->menu_type == MenuType::PARENT_NODE) {
            $dropNode($master);
        }

        // The settings link: a plain link to the settings screen.
        if (isset($settings) && $settings->menu_type == MenuType::CUSTOM) {
            $settings->delete();
            $count++;
        }

        // The portal entry, out of the root and in its place.
        if (isset($page) && isset($root) && $page->parent_id == $root->id) {
            $page->parent_id = $root->parent_id;
            $page->order = $root->order;
            $count++;
        }
        if (isset($page)) {
            // Titles the plugin itself has used for this entry.
            if (in_array(trim(strval($page->title)), ['ポータル', 'プロジェクトポータル'], true)) {
                $page->title = PortalDefinition::trans('schema.menu.single');
            }
            if (strval($page->icon) === 'fa-dashboard') {
                $page->icon = 'fa-tasks';
            }
            if ($page->isDirty()) {
                $page->save();
            }
        }
        if (isset($root) && $root->menu_type == MenuType::PARENT_NODE) {
            $dropNode($root);
        }

        // The bundled template's node with the four data tables.
        $templateTables = [];
        foreach (['project_table', 'issue_table', 'milestone_table', 'category_table'] as $role) {
            $templateTables[] = array_get($names, $role);
            $templateTables[] = $prefix . array_get(PortalDefinition::TABLE_ROLES, $role);
        }
        $dropTables($template, $templateTables);
        if (isset($template) && $template->menu_type == MenuType::PARENT_NODE) {
            $dropNode($template);
        }

        return $count;
    }

    /**
     * Point the settings menu entry at the plugin's current path.
     *
     * The portal entry is a plugin menu, which Exment resolves to the live
     * route every time the sidebar is drawn. The settings entry cannot be one -
     * a plugin menu only ever links to the plugin's root - so it is a plain
     * link holding the path as it was when written, and that path follows the
     * URI an admin can change on Exment's plugin screen at any time. Called
     * from the portal's own top page as well as the upgrade, so a changed URI
     * is caught on the next visit rather than the next schema upgrade; a
     * matching row costs one read and no write.
     *
     * @return void
     */
    public static function syncSettingsMenu(): void
    {
        try {
            $menu = Menu::where('menu_name', PortalSetup::prefix() . PortalSchema::MENU_SETTINGS)->first();
            if (!isset($menu) || $menu->menu_type != MenuType::CUSTOM) {
                return;
            }

            $uri = PortalSchema::settingsMenuUri();
            if (trim(strval($menu->uri), '/') === $uri) {
                return;
            }

            $menu->uri = $uri;
            $menu->save();
        } catch (\Throwable $e) {
            // A stale sidebar link is not worth failing the page over.
            \Log::warning('PmPortal settings menu sync failed: ' . $e->getMessage());
        }
    }

    /**
     * Bring the plugin row's type list up to what config.json declares.
     *
     * Exment copies plugin_type into the plugins table when a plugin is
     * uploaded and never looks at the file again, so a type added to a
     * plugin that is already installed - "batch", for the AI back-fill
     * command - is invisible to exment:batch until the row is updated.
     * Types already on the row are kept; nothing is ever removed.
     *
     * @return bool whether the row changed
     */
    protected static function syncPluginTypes(): bool
    {
        $plugin = PortalDefinition::plugin();
        $config = static::declaredConfig();
        if (!isset($plugin) || empty($config)) {
            return false;
        }

        $wanted = static::declaredPluginTypes(array_get($config, 'plugin_type', []));

        $current = array_values(array_filter(array_map('strval', (array) $plugin->plugin_types), 'strlen'));
        $missing = array_values(array_diff($wanted, $current));
        if (empty($missing)) {
            return false;
        }

        $plugin->plugin_types = implode(',', array_merge($current, $missing));
        $plugin->save();

        return true;
    }

    /**
     * The plugin types config.json declares, as the values the plugins table
     * stores.
     *
     * Exment's installer takes plugin_type as one comma separated string:
     * PluginTypeRule and Plugin::setPluginTypesAttribute both explode() it, so
     * a JSON list there fails the upload with a TypeError. config.json uses
     * the string; a list is still read here because earlier copies of the
     * file declared one.
     *
     * @param mixed $declared
     * @return array<int, string>
     */
    public static function declaredPluginTypes($declared): array
    {
        $names = is_array($declared) ? $declared : explode(',', strval($declared));
        $wanted = [];
        foreach ($names as $name) {
            $name = trim(strval($name));
            if ($name === '') {
                continue;
            }
            $enum = PluginType::getEnum($name);
            if (isset($enum)) {
                $wanted[] = strval($enum->getValue());
            }
        }

        return array_values(array_unique($wanted));
    }

    /**
     * Give the portal's role groups access to the tables this schema added.
     *
     * Done here rather than in the template fragment because an import that
     * leaves existing rows alone leaves their permissions alone too, so an
     * install that already has these groups would never hear about the new
     * tables. A permission row that is already there is left untouched, so an
     * administrator who tightened one by hand keeps their change.
     *
     * @param array<string, string> $names
     * @return int permission rows created
     */
    protected static function grantPermissions(array $names): int
    {
        $prefix = PortalSetup::prefix();
        $created = 0;

        foreach (PortalSchema::rolePermissions($names) as $group_name => $targets) {
            $group = static::roleGroup($prefix . $group_name, $group_name);
            if (!isset($group)) {
                continue;
            }

            foreach ($targets as $table_name => $permissions) {
                $table = CustomTable::getEloquent($table_name);
                if (!isset($table)) {
                    continue;
                }

                $exists = RoleGroupPermission::where('role_group_id', $group->id)
                    ->where('role_group_permission_type', RoleType::TABLE)
                    ->where('role_group_target_id', $table->id)
                    ->exists();
                if ($exists) {
                    continue;
                }

                $permission = new RoleGroupPermission();
                $permission->role_group_id = $group->id;
                $permission->role_group_permission_type = RoleType::TABLE;
                $permission->role_group_target_id = $table->id;
                $permission->permissions = array_values($permissions);
                $permission->save();
                $created++;
            }
        }

        return $created;
    }

    /**
     * Take back the permission rows a later schema withdrew.
     *
     * grantPermissions() only ever adds, so a grant dropped from
     * PortalSchema::rolePermissions() would stay on every install that already
     * had it. Only rows the portal wrote are removed: same group, same table,
     * and exactly the permission set it granted. A row an administrator
     * changed differs from that set and stays. Once only, on the way past the
     * version that withdrew it, so a grant an administrator puts back by hand
     * afterwards is theirs to keep.
     *
     * @param array<string, string> $names
     * @param int $applied the version the install's once-only steps reached
     * @return int permission rows removed or narrowed
     */
    protected static function revokeRetiredGrants(array $names, int $applied): int
    {
        $prefix = PortalSetup::prefix();
        $removed = 0;

        foreach (PortalSchema::retiredGrants($names) as $version => $grants) {
            if ($applied >= $version) {
                continue;
            }

            foreach ($grants as $grant) {
                $group = RoleGroup::where('role_group_name', $prefix . $grant['group'])->first();
                $table = CustomTable::getEloquent($grant['table']);
                if (!isset($group) || !isset($table)) {
                    continue;
                }

                $rows = RoleGroupPermission::where('role_group_id', $group->id)
                    ->where('role_group_permission_type', RoleType::TABLE)
                    ->where('role_group_target_id', $table->id)
                    ->get();
                foreach ($rows as $row) {
                    // A narrowing takes the withdrawn permissions off the
                    // group's row whatever else an administrator added to it:
                    // the withdrawal is the point, the rest stays theirs.
                    if (isset($grant['keep'])) {
                        $drop = array_values(array_diff($grant['permissions'], $grant['keep']));
                        $have = static::permissionList($row->permissions);
                        $left = array_values(array_diff($have, $drop));
                        if (count($left) === count($have)) {
                            continue;
                        }
                        $row->permissions = $left;
                        $row->save();
                        $removed++;
                        continue;
                    }
                    if (!static::samePermissions($row->permissions, $grant['permissions'])) {
                        continue;
                    }
                    $row->delete();
                    $removed++;
                }
            }
        }

        if ($removed > 0) {
            System::clearCache();
        }

        return $removed;
    }

    /**
     * A stored permission list as plain strings, once each.
     *
     * @param mixed $stored
     * @return array<int, string>
     */
    public static function permissionList($stored): array
    {
        if (is_string($stored)) {
            $decoded = json_decode($stored, true);
            $stored = is_array($decoded) ? $decoded : [$stored];
        }

        return array_values(array_unique(array_map('strval', (array) $stored)));
    }

    /**
     * Whether a stored permission list holds exactly the given permissions,
     * in any order.
     *
     * @param mixed $stored
     * @param array<int, string> $wanted
     * @return bool
     */
    public static function samePermissions($stored, array $wanted): bool
    {
        $have = static::permissionList($stored);
        $want = array_values(array_unique(array_map('strval', $wanted)));
        sort($have);
        sort($want);

        return $have === $want;
    }

    /**
     * Let the portal's role groups open the portal itself.
     *
     * The table permissions decide what a member may read and change, but
     * Exment checks a page plugin's own permission first, on every route the
     * plugin serves: without a plugin_access row - or the plugin's "all users"
     * switch - everybody but a system administrator gets "no permission" on
     * the dashboard, the project pages and the lightapi alike. The switch
     * would open the portal to every user of the space, so the grant goes to
     * the portal's own groups only; what each person then sees inside is
     * still decided per project.
     *
     * Same rule as grantPermissions(): a row that is already there is left
     * untouched.
     *
     * @return int permission rows created
     */
    protected static function grantPluginAccess(): int
    {
        $plugin = PortalDefinition::plugin();
        if (!isset($plugin)) {
            return 0;
        }

        $prefix = PortalSetup::prefix();
        $created = 0;

        foreach (array_keys(PortalSchema::roleGroups()) as $group_name) {
            $group = static::roleGroup($prefix . $group_name, $group_name);
            if (!isset($group)) {
                continue;
            }

            $exists = RoleGroupPermission::where('role_group_id', $group->id)
                ->where('role_group_permission_type', RoleType::PLUGIN)
                ->where('role_group_target_id', $plugin->id)
                ->exists();
            if ($exists) {
                continue;
            }

            $permission = new RoleGroupPermission();
            $permission->role_group_id = $group->id;
            $permission->role_group_permission_type = RoleType::PLUGIN;
            $permission->role_group_target_id = $plugin->id;
            $permission->permissions = [Permission::PLUGIN_ACCESS];
            $permission->save();
            $created++;
        }

        return $created;
    }

    /**
     * Set the table options from PortalSchema::tableOptions() on installs whose
     * tables were created before the portal needed them.
     *
     * Same rule as grantPermissions(): an option that already holds the wanted
     * value is left as it is, and an option the schema does not mention is
     * never touched, so nothing an administrator configured by hand is lost.
     *
     * @param array<string, string> $names
     * @return int tables changed
     */
    protected static function applyTableOptions(array $names): int
    {
        $changed = 0;

        foreach (PortalSchema::tableOptions($names) as $table_name => $options) {
            $table = CustomTable::getEloquent($table_name);
            if (!isset($table)) {
                continue;
            }

            $dirty = false;
            foreach ($options as $key => $value) {
                if (strval($table->getOption($key)) === strval($value)) {
                    continue;
                }
                $table->setOption($key, $value);
                $dirty = true;
            }

            if ($dirty) {
                $table->save();
                $changed++;
            }
        }

        return $changed;
    }

    /**
     * Make each column of PortalSchema::shareSettings() a share column, the
     * way Exment's own table settings screen would.
     *
     * Not through the template: its import deletes every share setting the
     * table has before it writes the ones it brings, which would take the
     * members and viewers settings - and any an administrator added - with
     * it. Here a setting is only added, and only when the table has none for
     * that column; one an administrator changed or removed by hand after
     * this added it comes back only if the column has no setting at all.
     *
     * @param array<string, string> $names
     * @return int settings added
     */
    protected static function syncShareSettings(array $names): int
    {
        $added = 0;
        foreach (PortalSchema::shareSettings($names) as $setting) {
            $table = CustomTable::getEloquent($setting['table']);
            $column = $table ? CustomColumn::getEloquent($setting['column'], $table) : null;
            if (!$column) {
                continue;
            }

            $has = CustomColumnMulti::where('custom_table_id', $table->id)
                ->where('multisetting_type', MultisettingType::SHARE_SETTINGS)
                ->get()
                ->contains(function ($multi) use ($column) {
                    return intval($multi->share_column_id) === intval($column->id);
                });
            if ($has) {
                continue;
            }

            $multi = new CustomColumnMulti();
            $multi->custom_table_id = $table->id;
            $multi->multisetting_type = MultisettingType::SHARE_SETTINGS;
            $multi->priority = CustomColumnMulti::where('custom_table_id', $table->id)
                ->where('multisetting_type', MultisettingType::SHARE_SETTINGS)
                ->max('priority') + 1;
            $multi->share_column_id = $column->id;
            $multi->share_trigger_type = ShareTrigger::CREATE . ',' . ShareTrigger::UPDATE;
            $multi->share_permission = $setting['permission'];
            $multi->save();
            $added++;
        }

        if ($added > 0) {
            System::clearCache();
        }

        return $added;
    }

    /**
     * The role group of that name, created if the install never imported the
     * bundled template's groups.
     *
     * @param string $name
     * @param string $schema_name key into PortalSchema::roleGroups()
     * @return RoleGroup|null
     */
    protected static function roleGroup(string $name, string $schema_name)
    {
        $group = RoleGroup::where('role_group_name', $name)->first();
        if (isset($group)) {
            return $group;
        }

        $meta = array_get(PortalSchema::roleGroups(), $schema_name);
        if (!isset($meta)) {
            return null;
        }

        $group = new RoleGroup();
        $group->role_group_name = $name;
        $group->role_group_view_name = PortalDefinition::trans('schema.' . $meta['key']);
        $group->role_group_order = $meta['order'];
        $group->description = PortalDefinition::trans('schema.' . $meta['key'] . '_description');
        $group->save();

        return $group;
    }

    // ---- conversion ----------------------------------------------------

    /**
     * Turn one word list column into a pointer at a table.
     *
     * @param array<string, mixed> $conversion one entry of PortalSchema::conversions()
     * @return string|null a line for the result log, null when there was nothing to do
     */
    protected static function convert(array $conversion): ?string
    {
        $table = CustomTable::getEloquent(array_get($conversion, 'table'));
        $target = CustomTable::getEloquent(array_get($conversion, 'target'));
        $column_name = array_get($conversion, 'column');

        if (!isset($table) || !isset($target) || is_nullorempty($column_name)) {
            return null;
        }

        $column = CustomColumn::getEloquent($column_name, $table);
        if (!isset($column)) {
            return null;
        }

        $kind = array_get($conversion, 'kind');
        $scoped = boolval(array_get($conversion, 'scoped'));

        // Already pointing at the table: make sure every project that
        // appeared since the last run has its own copy of the list, and
        // finish what an earlier run left half done. A list that already has
        // rows belongs to the administrator - a row renamed, deleted or
        // imported under another label must not come back.
        $converted = ($column->column_type == ColumnType::SELECT_TABLE);
        if ($converted) {
            // Pointed at some other table by hand: nothing here is the
            // portal's to finish.
            if (intval($column->getOption('select_target_table')) !== intval($target->id)) {
                return null;
            }

            return static::finishConverted($table, $column, $target, $kind, $scoped);
        }

        $options = static::currentList($column, $kind);
        if (empty($options)) {
            return null;
        }

        // Projects in the bin too: their issues come back with them on a
        // restore, and need a row of their own project to point at.
        $created = 0;
        $map = static::seedOptions($target, $kind, $options, $scoped, false, $created, true);

        // Stored before the column changes, so a run cut off below can still
        // be finished from it by the next one.
        static::rememberLegacyMap($column_name, $map);

        static::pointColumnAtTable($column, $target, $map, $scoped);
        list($rewritten, $unmapped) = static::rewriteValues($table, $column_name, $map, $scoped);

        $lines = [PortalDefinition::trans('upgrade.step_converted', [
            'column' => $column->column_view_name,
            'table' => $target->table_view_name,
            'count' => $rewritten,
        ])];
        if ($unmapped > 0) {
            $lines[] = PortalDefinition::trans('upgrade.step_unmapped', [
                'column' => $column->column_view_name,
                'count' => $unmapped,
            ]);
        }

        return implode(' ', $lines);
    }

    /**
     * The part of a conversion that runs on every later upgrade.
     *
     * The column already points at the table, but that alone does not mean
     * the work is done: a run cut off after the column changed - a timeout, a
     * browser that gave up, a crash - left the rest of the stored values as
     * words, which a table column shows as nothing and the board puts in no
     * column. Up to 16 nothing ever came back for them. Whatever still holds
     * a word the stored translation knows is rewritten here; a value that
     * already names a row of the table is never touched.
     *
     * @param CustomTable $table the issue table
     * @param CustomColumn $column
     * @param CustomTable $target
     * @param string $kind
     * @param bool $scoped
     * @return string|null a line for the result log, null when there was nothing to do
     */
    protected static function finishConverted(CustomTable $table, CustomColumn $column, CustomTable $target, string $kind, bool $scoped): ?string
    {
        $column_name = $column->column_name;

        // Only projects that are live: a project in the bin that never had
        // lists gets none now, and a list with rows is left alone.
        $created = 0;
        $map = static::seedOptions($target, $kind, static::fallbackList($kind), $scoped, true, $created);
        static::rememberLegacyMap($column_name, $map);

        // Lists this run left alone still translate through the words the
        // first conversion stored, so repair against those.
        $known = static::legacyMap($column_name);
        $repaired = static::repairColumnDefault($column, $target, $known, $scoped);
        list($rewritten, $unmapped) = static::rewriteValues($table, $column_name, $known, $scoped, $target);

        $lines = [];
        if ($created > 0) {
            $lines[] = PortalDefinition::trans('upgrade.step_seeded', [
                'table' => $target->table_view_name,
                'count' => $created,
            ]);
        }
        if ($repaired) {
            $lines[] = PortalDefinition::trans('upgrade.step_repair_default', [
                'column' => $column->column_view_name,
            ]);
        }
        if ($rewritten > 0) {
            $lines[] = PortalDefinition::trans('upgrade.step_repaired', [
                'column' => $column->column_view_name,
                'count' => $rewritten,
            ]);
        }
        if ($unmapped > 0) {
            $lines[] = PortalDefinition::trans('upgrade.step_unmapped', [
                'column' => $column->column_view_name,
                'count' => $unmapped,
            ]);
        }

        return empty($lines) ? null : implode(' ', $lines);
    }

    /**
     * The words a select column offers today, with the colour it draws them in
     * and, for status, whether the portal counts them as finished.
     *
     * @param CustomColumn $column
     * @param string $kind
     * @return array<int, array<string, mixed>>
     */
    protected static function currentList(CustomColumn $column, string $kind): array
    {
        $choices = $column->createSelectOptions();
        if (empty($choices)) {
            return static::fallbackList($kind);
        }

        $colors = GridCellStyle::parseValueColors($column->getOption('grid_value_colors'));
        $done = (array) array_get(PortalDefinition::config(), 'done_statuses', []);
        $fallback = collect(static::fallbackList($kind))->keyBy('key');

        $rows = [];
        foreach ($choices as $key => $label) {
            $key = strval($key);
            $rows[] = [
                'key' => $key,
                'label' => strval($label),
                'color' => array_get($colors, $key . '.color') ?: strval(array_get($fallback, $key . '.color', '#95a5a6')),
                'done' => $kind === 'status'
                    ? in_array($key, $done, true)
                    : boolval(array_get($fallback, $key . '.done', false)),
            ];
        }

        return $rows;
    }

    /**
     * @param string $kind
     * @return array<int, array<string, mixed>>
     */
    protected static function fallbackList(string $kind): array
    {
        $rows = [];
        foreach (array_get(PortalSchema::fallbackOptions(), $kind, []) as $row) {
            $key = strval(array_get($row, 'key'));
            $rows[] = [
                'key' => $key,
                'label' => static::seedLabel('schema.values.' . $kind . '.' . $key),
                'color' => strval(array_get($row, 'color', '#95a5a6')),
                'done' => boolval(array_get($row, 'done', false)),
            ];
        }

        return $rows;
    }

    /**
     * Create the rows behind a converted column and hand back the map the
     * value rewrite needs.
     *
     * Rows are matched by name so a second run finds what the first one made
     * instead of duplicating it. A scoped list is created once per project;
     * the map is then keyed by project id, because "in review" is a different
     * row in every project.
     *
     * With $onlyEmpty a list that already has rows is skipped and left out of
     * the map: once the column is converted, the rows are the administrator's
     * to rename or remove, and matching them by name against the shipped
     * labels would put back every one that was changed.
     *
     * A scoped list is seeded for projects only. A space with no project yet
     * gets no list at all rather than one shared copy: every project is
     * given its own the moment it is created (seedProject()), and a shared
     * copy was never removed afterwards, so pickers outside the portal went
     * on offering it next to every project's own.
     *
     * @param CustomTable $target
     * @param string $kind
     * @param array<int, array<string, mixed>> $options
     * @param bool $scoped
     * @param bool $onlyEmpty
     * @param int $created raised by one for every row actually written
     * @param bool $withTrashed seed projects in the bin as well
     * @return array<string, array<string, string>> scope id => key => row id
     */
    protected static function seedOptions(CustomTable $target, string $kind, array $options, bool $scoped, bool $onlyEmpty = false, int &$created = 0, bool $withTrashed = false): array
    {
        $scopes = $scoped ? static::projectScopes($withTrashed) : [null];
        $map = [];

        foreach ($scopes as $scope) {
            if ($onlyEmpty && static::scopeHasRows($target, $scope)) {
                continue;
            }
            $bucket = strval($scope === null ? 0 : $scope->id);
            $map[$bucket] = static::seedScope($target, $kind, $options, $scope, $created);
        }

        return $map;
    }

    /**
     * Whether a list already has any row: one project's own copy, or for a
     * null scope the rows that hang off nothing.
     *
     * @param CustomTable $target
     * @param \Exceedone\Exment\Model\CustomValue|null $scope
     * @return bool
     */
    protected static function scopeHasRows(CustomTable $target, $scope): bool
    {
        $query = $target->getValueModel()->newQuery();

        if (isset($scope)) {
            $query->where('parent_id', $scope->id)
                ->where('parent_type', $scope->custom_table->table_name);
        } else {
            $query->whereNull('parent_id');
        }

        return $query->exists();
    }

    /**
     * Seed one list for one owner and hand back key => row id.
     *
     * Split out of seedOptions so a single project can be given its lists
     * without walking every project in the space - which is what happens when
     * a project is created long after the upgrade last ran.
     *
     * @param CustomTable $target
     * @param string $kind
     * @param array<int, array<string, mixed>> $options
     * @param \Exceedone\Exment\Model\CustomValue|null $scope
     * @param int $created raised by one for every row actually written
     * @return array<string, string>
     */
    protected static function seedScope(CustomTable $target, string $kind, array $options, $scope, int &$created = 0): array
    {
        $columns = array_get(PortalSchema::COLUMNS, $kind, []);
        $map = [];

        foreach ($options as $index => $option) {
            $values = [
                $columns['name'] => $option['label'],
                $columns['order'] => $index,
            ];
            if (isset($columns['color'])) {
                $values[$columns['color']] = $option['color'];
            }
            if (isset($columns['done'])) {
                $values[$columns['done']] = $option['done'] ? '1' : '0';
            }

            $row = static::findByName($target, $columns['name'], $option['label'], $scope);
            if (!isset($row)) {
                $row = static::createRow($target, $values, $scope);
                $created++;
            }

            $map[$option['key']] = strval($row->id);
        }

        return $map;
    }

    /**
     * Schema 30: a new project starts without the Wiki and the chart, and
     * every project there already is keeps what it has.
     *
     * @param array<string, string> $names
     * @return int project rows changed
     */
    protected static function quietNewProjects(array $names): int
    {
        $table = CustomTable::getEloquent(array_get($names, 'project_table'));
        if (!isset($table)) {
            return 0;
        }
        $columns = [];
        foreach (PortalSchema::QUIET_FEATURES as $role) {
            $column_name = strval(array_get(PortalSchema::PROJECT_COLUMNS, $role, ''));
            $column = $column_name === '' ? null : CustomColumn::getEloquent($column_name, $table);
            if (isset($column)) {
                $columns[$column_name] = $column;
            }
        }
        if (empty($columns)) {
            return 0;
        }

        $changed = 0;
        foreach ($table->getValueModel()->get() as $row) {
            $patch = static::keepFeaturesPatch((array) $row->value, array_keys($columns));
            if (empty($patch)) {
                continue;
            }
            $row->saved_notify(false);
            $row->setValue($patch);
            $row->save();
            $changed++;
        }
        foreach ($columns as $column) {
            if (strval($column->getOption('default')) !== '0') {
                $column->setOption('default', '0');
                $column->save();
            }
        }

        return $changed;
    }

    /**
     * What keeps a project's switches as they read now: an empty one is read
     * as on (PortalProjectTrait::projectFlag), so it is written as on before
     * the default of its column turns off. Plain PHP, tested without a
     * database (tools/test-pm-features.php).
     *
     * @param array<string, mixed> $value the row's values
     * @param array<int, string> $column_names
     * @return array<string, string>
     */
    public static function keepFeaturesPatch(array $value, array $column_names): array
    {
        $patch = [];
        foreach ($column_names as $name) {
            $now = array_key_exists($name, $value) ? $value[$name] : null;
            if ($now === null || $now === '' || (is_array($now) && empty($now))) {
                $patch[$name] = '1';
            }
        }

        return $patch;
    }

    /**
     * Give every project the schema's own defaults for the per project
     * switches, once.
     *
     * The defaults are read off each column rather than repeated here, so a
     * deployment that overrode one in PortalSchema gets its value and not a
     * second copy of the shipped one. archived and formatting_rule are left
     * alone: an archived project must not come back from the dead, and the
     * text rule only decides how a description is rendered.
     *
     * @param array<string, string> $names
     * @return int project rows changed
     */
    protected static function seedFeatureFlags(array $names): int
    {
        $table = CustomTable::getEloquent(array_get($names, 'project_table'));
        if (!isset($table)) {
            return 0;
        }

        $defaults = [];
        foreach (['use_wiki', 'use_file', 'use_gantt', 'use_git', 'subtasking'] as $role) {
            $column_name = array_get(PortalSchema::PROJECT_COLUMNS, $role);
            $column = is_nullorempty($column_name) ? null : CustomColumn::getEloquent($column_name, $table);
            if (isset($column)) {
                $defaults[$column_name] = strval(array_get($column->options, 'default', '1'));
            }
        }
        if (empty($defaults)) {
            return 0;
        }

        $changed = 0;
        foreach ($table->getValueModel()->get() as $row) {
            $patch = [];
            foreach ($defaults as $column_name => $default) {
                if (strval(array_get($row->value, $column_name, '')) !== $default) {
                    $patch[$column_name] = $default;
                }
            }
            if (empty($patch)) {
                continue;
            }
            $row->saved_notify(false);
            $row->setValue($patch);
            $row->save();
            $changed++;
        }

        return $changed;
    }

    /**
     * Give one project its own copy of every list a project owns.
     *
     * Backlog hands a new project the standard statuses and issue types the
     * moment it is created, and the portal has to do the same: the create-issue
     * form reads its default status and type from the project's own rows
     * (LightApiController::scopedDefault), so a project without them produces
     * issues with no status - which then belong to no board column and show as
     * blank in the list. Seeding only ran during setup and the schema upgrade,
     * so every project created afterwards was left empty.
     *
     * Rows are matched by name, so calling this again costs a few reads and
     * writes nothing.
     *
     * @param \Exceedone\Exment\Model\CustomValue|null $project
     * @return int rows created
     */
    public static function seedProject($project): int
    {
        if (!isset($project) || !PortalSetup::isInstalled()) {
            return 0;
        }

        $names = static::names();
        $created = 0;

        foreach (['status' => 'status_table', 'issue_type' => 'issue_type_table'] as $kind => $role) {
            $target = CustomTable::getEloquent(array_get($names, $role));
            if (!isset($target)) {
                continue;
            }

            static::seedScope($target, $kind, static::fallbackList($kind), $project, $created);
        }

        return $created;
    }

    /**
     * Every project that owns a copy of a scoped list. None at all on a space
     * with no project yet - see seedOptions().
     *
     * @param bool $withTrashed projects in the bin as well
     * @return array<int, \Exceedone\Exment\Model\CustomValue>
     */
    protected static function projectScopes(bool $withTrashed = false): array
    {
        $names = static::names();
        $table = CustomTable::getEloquent(array_get($names, 'project_table'));
        if (!isset($table)) {
            return [];
        }

        $query = $table->getValueModel()->newQuery();
        if ($withTrashed) {
            $query->withTrashed();
        }

        return $query->orderBy('id')->get()->all();
    }

    /**
     * @param CustomTable $table
     * @param string $column
     * @param string $name
     * @param \Exceedone\Exment\Model\CustomValue|null $parent
     * @return \Exceedone\Exment\Model\CustomValue|null
     */
    protected static function findByName(CustomTable $table, string $column, string $name, $parent)
    {
        $query = $table->getValueModel()->newQuery();

        // An indexed column is a real database column, so prefer it; a table
        // whose index has not been built yet still matches through the JSON.
        $custom_column = CustomColumn::getEloquent($column, $table);
        $index = isset($custom_column) && method_exists($custom_column, 'getIndexColumnName')
            ? $custom_column->getIndexColumnName()
            : null;

        if ($index) {
            $query->where($index, $name);
        } else {
            $query->where('value->' . $column, $name);
        }

        if (isset($parent)) {
            $query->where('parent_id', $parent->id)
                ->where('parent_type', $parent->custom_table->table_name);
        }

        return $query->first();
    }

    /**
     * @param CustomTable $table
     * @param array<string, mixed> $values
     * @param \Exceedone\Exment\Model\CustomValue|null $parent
     * @return \Exceedone\Exment\Model\CustomValue
     */
    protected static function createRow(CustomTable $table, array $values, $parent)
    {
        $row = $table->getValueModel();
        $row->saved_notify(false);

        if (isset($parent)) {
            $row->parent_id = $parent->id;
            $row->parent_type = $parent->custom_table->table_name;
        }

        $row->setValue($values);
        $row->save();

        return $row;
    }

    /**
     * Clean up a column that was already converted to a table pointer.
     *
     * pointColumnAtTable() does this work while it converts a column, but it
     * used array_forget to drop the settings that stop meaning anything, and
     * the saving hook puts back any key that simply goes missing. So every
     * converted column kept its dead word list, its colours and - worst - a
     * default such as "todo" on a column that now expects a row id. The create
     * form then offers a synthetic option and pre-selects it, the save
     * silently discards it, and the user gets an issue with no type and no
     * status. A column that was converted before this fix never comes back
     * through pointColumnAtTable(), so the repair has to run here too.
     *
     * @param CustomColumn $column
     * @param CustomTable $target
     * @param array<string, array<string, string>> $map bucket => word => id
     * @param bool $scoped
     * @return bool whether anything was changed
     */
    protected static function repairColumnDefault(CustomColumn $column, CustomTable $target, array $map, bool $scoped): bool
    {
        $options = (array) $column->options;
        $changed = false;

        // Leftovers from the word-list era. The rows of the target table carry
        // the labels and colours now, so these only confuse whoever reads the
        // definition next.
        foreach (['select_item', 'select_item_valtext', 'grid_value_colors', 'grid_style'] as $key) {
            if (array_has($options, $key)) {
                $column->setOption($key, null);
                $changed = true;
            }
        }

        $default = strval(array_get($options, 'default', ''));

        // A numeric default is only meaningful while the row it names exists.
        $usable = $default === ''
            || (ctype_digit($default) && $target->getValueModel()->query()->find(intval($default)) !== null);

        if (!$usable) {
            // A per-project list has no single row that could serve everyone,
            // so the default is dropped rather than pinned to one project's
            // copy. Dropping means storing null, for the reason above.
            $id = $scoped ? null : static::lookupWord((array) ($map['0'] ?? []), $default);
            $column->setOption('default', isset($id) ? strval($id) : null);
            $changed = true;
        }

        if ($changed) {
            $column->save();
        }

        return $changed;
    }

    /**
     * Swap a column's definition from a word list to a table pointer, keeping
     * the settings that still mean something and dropping the ones that do not.
     *
     * The stored default is a word such as "normal", which stops meaning
     * anything the moment the column expects a row id, so it is translated
     * along with everything else. A per-project list has no single row that
     * could serve as the default, so there the default is dropped instead of
     * being pinned to whichever project happened to be converted first.
     *
     * @param CustomColumn $column
     * @param CustomTable $target
     * @param array<string, array<string, string>> $map
     * @param bool $scoped
     * @return void
     */
    protected static function pointColumnAtTable(CustomColumn $column, CustomTable $target, array $map = [], bool $scoped = false): void
    {
        $options = (array) $column->options;

        // The word list and its colours now live in the target table's rows.
        // Storing null rather than removing the key is deliberate: the saving
        // hook puts back any key that simply went missing.
        foreach (['select_item', 'select_item_valtext', 'grid_value_colors', 'grid_style'] as $key) {
            $column->setOption($key, null);
        }

        $default = strval(array_get($options, 'default', ''));
        if ($default !== '') {
            $id = $scoped ? null : static::lookupWord((array) ($map['0'] ?? []), $default);
            $column->setOption('default', isset($id) ? strval($id) : null);
        }

        $column->setOption('select_target_table', $target->id);
        $column->setOption('select_load_ajax', '1');

        $column->column_type = ColumnType::SELECT_TABLE;
        $column->save();
    }

    /**
     * Keep the old word next to the row that replaced it.
     *
     * Anything written before the upgrade - a bookmarked filter, a saved view,
     * another system posting to the API - still speaks in words like "normal".
     * Storing the translation lets those callers keep working instead of
     * failing validation on a column that now wants a number.
     *
     * @param string $column_name
     * @param array<string, array<string, string>> $map bucket => word => id
     * @return void
     */
    protected static function rememberLegacyMap(string $column_name, array $map): void
    {
        if (empty($map)) {
            return;
        }

        $stored = (array) array_get(PortalSetup::state(), static::LEGACY_KEY, []);
        $existing = (array) array_get($stored, $column_name, []);

        foreach ($map as $bucket => $pairs) {
            // Replaced key by key rather than merged: array_merge renumbers a
            // word made of digits, and the word is the key.
            $existing[$bucket] = array_replace((array) ($existing[$bucket] ?? []), (array) $pairs);
        }

        $stored[$column_name] = $existing;
        PortalSetup::patchState([static::LEGACY_KEY => $stored]);
    }

    /**
     * The stored word-to-row translation of one column, bucket => word => id.
     *
     * @param string $column_name
     * @return array<string, array<string, string>>
     */
    protected static function legacyMap(string $column_name): array
    {
        $stored = (array) array_get(PortalSetup::state(), static::LEGACY_KEY, []);
        $map = $stored[$column_name] ?? [];

        return is_array($map) ? $map : [];
    }

    /**
     * Translate a word this column used to accept into the row id it means now.
     *
     * @param string $column_name
     * @param string $value
     * @param string|int|null $parent_id
     * @return string|null the row id, or null when the word is unknown
     */
    public static function legacyValue(string $column_name, string $value, $parent_id = null): ?string
    {
        if ($value === '' || ctype_digit($value)) {
            return null;
        }

        return static::translateWord(static::legacyMap($column_name), $value, strval($parent_id), true);
    }

    /**
     * The row id a word means for one owner, from a bucket => word => id map.
     *
     * Its owner's bucket first, then the shared one ('0'). With $anyBucket a
     * word found in no bucket of its own is accepted from the others, but
     * only while every bucket that knows it agrees on the row.
     *
     * @param array<string, mixed> $map
     * @param string $word
     * @param string $bucket
     * @param bool $anyBucket
     * @return string|null
     */
    public static function translateWord(array $map, string $word, string $bucket, bool $anyBucket = false): ?string
    {
        foreach (array_unique([$bucket, '0']) as $key) {
            if ($key === '' || !isset($map[$key]) || !is_array($map[$key])) {
                continue;
            }
            $id = static::lookupWord($map[$key], $word);
            if (isset($id)) {
                return $id;
            }
        }

        if (!$anyBucket) {
            return null;
        }

        // A word shared by every project: only accept it when it is unambiguous.
        $found = null;
        foreach ($map as $pairs) {
            $id = is_array($pairs) ? static::lookupWord($pairs, $word) : null;
            if (!isset($id)) {
                continue;
            }
            if (isset($found) && $id !== $found) {
                return null;
            }
            $found = $id;
        }

        return $found;
    }

    /**
     * The row id one bucket of a translation gives a word, or null.
     *
     * Read by key, never through array_get(): a word may hold a dot, which
     * array_get() takes for a path. A word that differs from a key only in
     * letter case or surrounding spaces - what a CSV import or a hand edit
     * leaves behind - still finds it, unless two keys look alike that way and
     * name different rows; then only the exact word counts.
     *
     * @param array<string|int, mixed> $pairs word => row id
     * @param string $word
     * @return string|null
     */
    public static function lookupWord(array $pairs, string $word): ?string
    {
        if (array_key_exists($word, $pairs) && is_scalar($pairs[$word]) && strval($pairs[$word]) !== '') {
            return strval($pairs[$word]);
        }

        $wanted = static::normaliseWord($word);
        if ($wanted === '') {
            return null;
        }

        $found = null;
        foreach ($pairs as $key => $id) {
            if (!is_scalar($id) || strval($id) === '' || static::normaliseWord(strval($key)) !== $wanted) {
                continue;
            }
            if (isset($found) && strval($id) !== $found) {
                return null;
            }
            $found = strval($id);
        }

        return $found;
    }

    /**
     * A word folded for a forgiving comparison: lower case, without the
     * spaces around it - full-width ones included.
     *
     * @param string $word
     * @return string
     */
    protected static function normaliseWord(string $word): string
    {
        $trimmed = preg_replace('/^[\s\x{3000}]+|[\s\x{3000}]+$/u', '', $word);

        return mb_strtolower(is_string($trimmed) ? $trimmed : trim($word));
    }

    /**
     * Rewrite stored values from the old word to the id of its new row.
     *
     * Every issue is read, the ones in the bin included: they come back with
     * their values on a restore, and a value left as a word comes back blank.
     *
     * A word the map does not know is left as it is and, on an issue outside
     * the bin, counted: it is a word nobody offers any more, and losing it
     * silently would be worse than leaving it in the stored data and
     * reported in the result.
     *
     * With $target, the column already points at that table - a run finishing
     * what an earlier one left. A value naming one of its rows is converted
     * already and never read as a word; that matters only for a list whose
     * words were digits, where the two look alike.
     *
     * @param CustomTable $table
     * @param string $column_name
     * @param array<string, array<string, string>> $map
     * @param bool $scoped
     * @param CustomTable|null $target
     * @return array{0: int, 1: int} rows rewritten, values no row was found for
     */
    protected static function rewriteValues(CustomTable $table, string $column_name, array $map, bool $scoped, ?CustomTable $target = null): array
    {
        $changed = 0;
        $unmapped = 0;

        $rowIds = null;
        if (isset($target)) {
            $rowIds = array_fill_keys(array_map('strval', $target->getValueModel()->newQuery()->withTrashed()->pluck('id')->all()), true);
        }

        $table->getValueModel()->newQuery()->withTrashed()->chunkById(200, function ($rows) use ($column_name, $map, $scoped, $rowIds, &$changed, &$unmapped) {
            foreach ($rows as $row) {
                $value = (array) $row->value;
                $old = $value[$column_name] ?? null;
                if (!is_string($old) && !is_numeric($old)) {
                    continue;
                }
                $old = strval($old);
                if ($old === '' || (isset($rowIds) && isset($rowIds[$old]))) {
                    continue;
                }

                // A scoped issue with no list of its own project's falls back
                // to the shared list an older install may still have.
                $id = static::translateWord($map, $old, $scoped ? strval($row->parent_id) : '0');
                if (!isset($id)) {
                    // Counted for the issues people can open and fix. One in
                    // the bin is tried all the same, but reporting it would
                    // only repeat at every upgrade with nothing to act on.
                    if (!$row->trashed()) {
                        $unmapped++;
                    }
                    continue;
                }
                if ($id === $old) {
                    continue;
                }

                $value[$column_name] = $id;
                $row->saved_notify(false);
                $row->value = $value;
                $row->save();
                $changed++;
            }
        });

        return [$changed, $unmapped];
    }
}
