<?php

namespace App\Plugins\ExmentPluginPM\Migrate;

use App\Plugins\ExmentPluginPM\PortalDefinition;
use Exceedone\Exment\Model\CustomValueAuthoritable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Data migration, originally written by TTM as the separate PmMigrate plugin
 * and folded into PmPortal in 1.2.0.
 *
 * What the two ends of a migration must agree on: which Exment tables the
 * portal uses, which of their columns hold user ids, and which columns can be
 * written to.
 *
 * Reads Exment's own metadata tables with plain queries; only the table names
 * come from the portal itself (PortalDefinition::config()).
 */
class MigrateSchema
{
    /**
     * The roles a bundle carries, with the names the template ships. The
     * names an install really uses (they gain a prefix when a table of that
     * name already existed) come from PortalDefinition::config().
     */
    const ROLE_DEFAULTS = [
        'project_table' => 'project',
        'issue_table' => 'pm_issue',
        'wiki_table' => 'pm_wiki',
        'milestone_table' => 'task_milestone',
        'category_table' => 'task_category',
        'status_table' => 'pm_status',
        'issue_type_table' => 'pm_issue_type',
        'priority_table' => 'pm_priority',
        'resolution_table' => 'pm_resolution',
        'related_table' => 'pm_related_issue',
    ];

    /** Roles whose rows are exported whole or per project and shared by projects. */
    const MASTER_ROLES = ['status_table', 'issue_type_table', 'priority_table', 'resolution_table'];

    const COMMENT_TABLE = 'comment';
    const DOCUMENT_TABLE = 'document';
    const USER_TABLE = 'user';
    const ORGANIZATION_TABLE = 'organization';

    const STARS_TABLE = 'exment_stars';

    /** @var array<string, array<string, mixed>> */
    private static $described = [];

    /**
     * @return void
     */
    public static function forget(): void
    {
        static::$described = [];
    }

    /**
     * @throws MigrateException when the connection is neither MySQL nor MariaDB
     */
    public static function assertSupportedDriver(): void
    {
        // Rows are inserted with their own ids and read back as JSON text: both are
        // done the MySQL way, and the generated index columns are told apart by
        // Exment's MySQL schema helper. Exment runs a MariaDB install under its own
        // "mariadb" driver name, with the same SQL.
        $driver = DB::connection()->getDriverName();
        if (!in_array($driver, ['mysql', 'mariadb'], true)) {
            throw new MigrateException("この移行ツールは MySQL / MariaDB にのみ対応しています（現在のドライバー: {$driver}）。");
        }
    }

    /**
     * role => the table_name this install uses.
     *
     * @return array<string, string>
     */
    public static function roles(): array
    {
        // Now that this code runs inside the portal it asks the portal: the
        // same answer the separate plugin read out of the plugin row, plus a
        // deployment override in config('exment.pmportal') that the plugin
        // row never saw, so both ends name the tables the screens really use.
        $config = PortalDefinition::config();

        $roles = [];
        foreach (static::ROLE_DEFAULTS as $role => $default) {
            $name = $config[$role] ?? null;
            $roles[$role] = is_string($name) && $name !== '' ? $name : $default;
        }

        return $roles;
    }

    /**
     * Everything the migration needs to know about one custom table.
     *
     * @param string $tableName
     * @return array<string, mixed>
     * @throws MigrateException when the table is not defined here
     */
    public static function describe(string $tableName): array
    {
        if (isset(static::$described[$tableName])) {
            return static::$described[$tableName];
        }

        $table = DB::table('custom_tables')->where('table_name', $tableName)->first();
        if (!$table) {
            throw new MigrateException("テーブル「{$tableName}」が見つかりません。プロジェクト管理のセットアップが済んでいるか確認してください。");
        }

        $physical = 'exm__' . $table->suuid;
        if (!Schema::hasTable($physical)) {
            throw new MigrateException("テーブル「{$tableName}」のデータテーブルがありません。");
        }

        $userTableId = DB::table('custom_tables')->where('table_name', static::USER_TABLE)->value('id');
        $orgTableId = DB::table('custom_tables')->where('table_name', static::ORGANIZATION_TABLE)->value('id');

        $columns = [];
        $userColumns = [];
        $orgColumns = [];
        $selectColumns = [];
        foreach (DB::table('custom_columns')->where('custom_table_id', $table->id)->orderBy('id')->get() as $column) {
            $options = json_decode(strval($column->options), true);
            $options = is_array($options) ? $options : [];
            $target = $options['select_target_table'] ?? null;
            $columns[] = $column->column_name;

            if ($column->column_type === 'user' || ($column->column_type === 'select_table' && static::sameId($target, $userTableId))) {
                $userColumns[] = $column->column_name;
            } elseif ($column->column_type === 'organization' || ($column->column_type === 'select_table' && static::sameId($target, $orgTableId))) {
                $orgColumns[] = $column->column_name;
            } elseif ($column->column_type === 'select_table' && $target !== null && $target !== '') {
                $selectColumns[$column->column_name] = intval($target);
            }
        }

        // columns that can be written: the generated index columns cannot
        $dataColumns = [];
        foreach (Schema::getColumnDefinitions($physical) as $definition) {
            if (!boolval($definition['virtual'])) {
                $dataColumns[] = strtolower($definition['column_name']);
            }
        }

        return static::$described[$tableName] = [
            'id' => intval($table->id),
            'table_name' => $tableName,
            'suuid' => $table->suuid,
            'physical' => $physical,
            'columns' => $columns,
            'dataColumns' => $dataColumns,
            'userColumns' => $userColumns,
            'orgColumns' => $orgColumns,
            // column_name => id of the table it points to
            'selectColumns' => $selectColumns,
        ];
    }

    /**
     * Tables that hold the parent side of a one-to-many relation to this one.
     *
     * @param int $childTableId
     * @return array<int, int> parent table ids
     */
    public static function parentTableIds(int $childTableId): array
    {
        return DB::table('custom_relations')
            ->where('child_custom_table_id', $childTableId)
            ->pluck('parent_custom_table_id')
            ->map(function ($id) {
                return intval($id);
            })->all();
    }

    /**
     * column id => [table_name, column_name], for every custom column.
     *
     * @return array<int, array{0: string, 1: string}>
     */
    public static function columnRefs(): array
    {
        $refs = [];
        $rows = DB::table('custom_columns')
            ->join('custom_tables', 'custom_tables.id', '=', 'custom_columns.custom_table_id')
            ->get(['custom_columns.id as id', 'custom_tables.table_name as table_name', 'custom_columns.column_name as column_name']);
        foreach ($rows as $row) {
            $refs[intval($row->id)] = [$row->table_name, $row->column_name];
        }

        return $refs;
    }

    /**
     * @return string the table of the per-record permissions
     */
    public static function authoritableTable(): string
    {
        return CustomValueAuthoritable::getTableName();
    }

    /**
     * @param mixed $a
     * @param mixed $b
     * @return bool
     */
    private static function sameId($a, $b): bool
    {
        return $a !== null && $a !== '' && $b !== null && strval($a) === strval($b);
    }
}
