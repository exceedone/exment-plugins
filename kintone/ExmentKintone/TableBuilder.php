<?php

namespace App\Plugins\ExmentKintone;

use Exceedone\Exment\Model\CustomColumn;
use Exceedone\Exment\Model\CustomTable;
use Exceedone\Exment\Model\SyncConnection;
use Exceedone\Exment\Model\SyncMapping;
use Exceedone\Exment\Model\System;
use Exceedone\Exment\Services\Studio\BlankFormBuilder;

/**
 * An Exment table made from the definition of a kintone app (ISS00113): a column for each field
 * a column type fits (the same label, the required ones required, the choices of a select), the
 * default form and view (BlankFormBuilder of the Studio), and a mapping of the data sync with
 * each field mapped (the schedule off, so a dry run comes first).
 *
 * A field no column fits (a table in the record, a related record, a group or an organization)
 * is not made and is told. The attachments become the attachments of each record.
 */
class TableBuilder
{
    /** The field types that are the record itself (its id, its times), not columns. */
    const RECORD_FIELDS = ['RECORD_NUMBER', 'CREATED_TIME', 'UPDATED_TIME', 'CREATOR', 'MODIFIER', 'STATUS', 'FILE'];

    /**
     * @return array{table: CustomTable, mapping: SyncMapping, skipped: array<int, string>}
     */
    public static function build(SyncConnection $connection, string $app, string $tableName, string $tableViewName): array
    {
        $connector = $connection->connectorInstance();
        if (!isset($connector)) {
            throw new \RuntimeException('kintone');
        }
        $fields = [];
        $map = [];
        $skipped = [];
        $multiple = [];
        $used = [];
        foreach ($connector->fields($connection, $app) as $field) {
            $property = $field;
            if (!isset($property->type) || in_array($property->type, ['file'], true)) {
                if (!isset($property->type)) {
                    $skipped[] = $property->label;
                }
                continue;
            }
            $name = static::columnName($property->code, count($fields) + 1, $used);
            $used[] = $name;
            $fields[] = [
                'column_name' => $name,
                'label' => mb_substr($property->label, 0, 40),
                'type' => $property->type,
                'required' => $property->required,
                'choices' => implode("\n", $property->options),
            ];
            if ($property->multiple) {
                $multiple[] = $name;
            }
            $map[] = ['remote' => $property->code, 'column' => $name];
        }

        $table = BlankFormBuilder::build([
            'table_view_name' => $tableViewName,
            'table_name' => $tableName,
            'menu_parent_id' => null,
            'fields' => $fields,
        ]);
        foreach ($multiple as $name) {
            $column = CustomColumn::getEloquent($name, $table);
            if ($column instanceof CustomColumn) {
                $column->setOption('multiple_enabled', '1');
                $column->save();
            }
        }
        System::clearRequestSession();

        $label = $app;
        foreach ($connector->objects($connection) as $object) {
            if ($object->key === $app) {
                $label = $object->label;
            }
        }
        $mapping = SyncMapping::query()->create([
            'sync_connection_id' => $connection->id,
            'name' => mb_substr($tableViewName, 0, 100),
            'remote_object' => $app,
            'remote_label' => mb_substr($label, 0, 255),
            'custom_table_id' => $table->id,
            'direction' => SyncMapping::DIRECTION_IMPORT,
            'interval_minutes' => 30,
            'conflict_rule' => SyncMapping::CONFLICT_REMOTE,
            'delete_rule' => SyncMapping::DELETE_MARK,
            'field_map' => $map,
            'options' => ['keep_times' => true, 'check_deletes' => true],
            'enabled' => false,
        ]);
        return ['table' => $table, 'mapping' => $mapping, 'skipped' => $skipped];
    }

    /**
     * The column name of a field: its code when it is one already (letters, digits, _), else
     * "field_<n>".
     *
     * @param array<int, string> $used
     */
    public static function columnName(string $code, int $n, array $used): string
    {
        $name = strtolower($code);
        if (preg_match('/^[a-z][a-z0-9_]{0,29}$/', $name) !== 1 || in_array($name, $used, true)) {
            $name = 'field_' . $n;
        }
        return $name;
    }

    public static function validTableName(string $name): bool
    {
        return preg_match('/^[a-z][a-z0-9_]{0,29}$/', $name) === 1;
    }
}
