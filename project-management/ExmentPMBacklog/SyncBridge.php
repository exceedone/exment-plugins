<?php

namespace App\Plugins\ExmentPMBacklog;

use Exceedone\Exment\Model\SyncConnection;
use Exceedone\Exment\Model\SyncMapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The connections of this plugin and the data sync of Exment 7.3 (ISS00113). Loaded only when
 * Exment has the data sync (PluginPage::hasSync()).
 *
 * - move(): each connection of this plugin becomes a connection and a mapping of the data sync
 *   (once: a connection moved before is left as it is), its API key kept encrypted by Exment.
 *   The links of the issues (pmbl_links) are kept and used by the data sync as they are, so
 *   nothing is imported twice. The batch of this plugin leaves a moved connection alone.
 * - conn(): the connection of this plugin a mapping of the data sync writes with, as Syncer reads
 *   it (its mapping of the statuses, the types and the users), with the space and the key of the
 *   connection of the data sync.
 *
 * The API key is never written to a log or shown.
 */
class SyncBridge
{
    /**
     * Move the connections not moved yet.
     *
     * @return array{moved: int, kept: int, lines: array<int, string>}
     */
    public static function move(?int $userId = null): array
    {
        PluginPage::ensureTables();
        $issueTable = Pm::table('issue_table');
        if (!$issueTable) {
            throw new \RuntimeException(Lang::t('error_no_pm'));
        }
        $result = ['moved' => 0, 'kept' => 0, 'lines' => []];
        foreach (Connections::all() as $conn) {
            if (!empty($conn->sync_mapping_id) && SyncMapping::query()->whereKey(intval($conn->sync_mapping_id))->exists()) {
                $result['kept']++;
                continue;
            }
            DB::transaction(function () use ($conn, $issueTable, $userId, &$result) {
                $connection = new SyncConnection([
                    'name' => mb_substr(strval($conn->name), 0, 100),
                    'connector' => BacklogConnector::KEY,
                    'settings' => ['space_url' => strval($conn->space_url)],
                    'enabled' => true,
                    'created_user_id' => $userId,
                    'updated_user_id' => $userId,
                ]);
                $key = Connections::apiKey($conn);
                if ($key !== '') {
                    $connection->setSecret('api_key', $key);
                }
                $connection->save();
                $mapping = SyncMapping::query()->create([
                    'sync_connection_id' => $connection->id,
                    'name' => mb_substr(strval($conn->name), 0, 100),
                    'remote_object' => strval($conn->backlog_project_key),
                    'remote_label' => strval($conn->backlog_project_key),
                    'custom_table_id' => $issueTable->id,
                    'direction' => SyncMapping::DIRECTION_IMPORT,
                    'interval_minutes' => in_array(intval($conn->interval_minutes), SyncMapping::INTERVALS, true) ? intval($conn->interval_minutes) : 30,
                    'conflict_rule' => SyncMapping::CONFLICT_REMOTE,
                    'delete_rule' => SyncMapping::DELETE_MARK,
                    'field_map' => [],
                    // the deleted and moved issues: the daily check of this plugin (SyncFinisher)
                    'options' => ['connector' => ['pmbl_connection' => strval($conn->id)], 'check_deletes' => false, 'keep_times' => true],
                    'enabled' => boolval($conn->enabled),
                    'cursor' => $conn->cursor_updated ?: null,
                    'next_run_at' => $conn->next_run_at ?: null,
                ]);
                Connections::patch(intval($conn->id), ['sync_mapping_id' => $mapping->id]);
                $result['moved']++;
                $result['lines'][] = Lang::t('sync_moved_one', ['name' => strval($conn->name)]);
                Log::info('pm_backlog: connection ' . $conn->id . ' moved to the data sync (connection ' . $connection->id . ', mapping ' . $mapping->id . ')');
            });
        }
        return $result;
    }

    /**
     * The connection of this plugin of a mapping, with the space and the key of its connection
     * of the data sync, null: none.
     */
    public static function conn(SyncMapping $mapping, ?SyncConnection $connection = null): ?object
    {
        $connection = $connection ?? $mapping->connection;
        $id = intval(array_get($mapping->options ?? [], 'connector.pmbl_connection'));
        $conn = $id > 0 ? Connections::find($id) : null;
        if (!$conn || !isset($connection)) {
            return null;
        }
        $conn = clone $conn;
        $conn->space_url = rtrim(strval($connection->setting('space_url')), '/') ?: $conn->space_url;
        $conn->api_key_plain = strval($connection->secret('api_key'));
        return $conn;
    }

    /**
     * The mapping of a connection and a project key (the data sync gives the connector those).
     */
    public static function mapping(SyncConnection $connection, string $object): ?SyncMapping
    {
        /** @var SyncMapping|null $mapping */
        $mapping = SyncMapping::query()->where('sync_connection_id', $connection->id)->where('remote_object', $object)->orderBy('id')->first();
        return $mapping;
    }
}
