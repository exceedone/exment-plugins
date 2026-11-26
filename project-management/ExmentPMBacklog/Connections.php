<?php

namespace App\Plugins\ExmentPMBacklog;

use Carbon\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * The connections table, and the lock a run takes on one connection.
 */
class Connections
{
    const TABLE = 'pmbl_connections';

    /** A run that dies keeps the connection this long at most. */
    const LOCK_MINUTES = 60;

    /** @return array<int, object> */
    public static function all(): array
    {
        return DB::table(static::TABLE)->orderBy('id')->get()->all();
    }

    public static function find($id): ?object
    {
        $row = DB::table(static::TABLE)->where('id', intval($id))->first();

        return $row ?: null;
    }

    /**
     * Save the form's fields. An empty key keeps the stored one.
     *
     * @param array<string, mixed> $in
     */
    public static function save(?int $id, array $in): int
    {
        $values = [
            'name' => mb_substr(trim(strval($in['name'] ?? '')), 0, 255),
            'space_url' => rtrim(trim(strval($in['space_url'] ?? '')), '/'),
            'backlog_project_key' => strtoupper(trim(strval($in['backlog_project_key'] ?? ''))),
            'exment_project_id' => intval($in['exment_project_id'] ?? 0),
            'interval_minutes' => max(5, intval($in['interval_minutes'] ?? 30) ?: 30),
            'enabled' => !empty($in['enabled']) ? 1 : 0,
            'updated_at' => Carbon::now(),
        ];
        $key = trim(strval($in['api_key'] ?? ''));
        if ($key !== '') {
            $values['api_key_enc'] = Crypt::encryptString($key);
        }
        if ($id) {
            $old = static::find($id);
            // Another project or space: the old ids mean nothing there.
            if ($old && ($old->backlog_project_key !== $values['backlog_project_key'] || $old->space_url !== $values['space_url'])) {
                $values['backlog_project_id'] = null;
                $values['mapping'] = null;
                $values['cursor_updated'] = null;
            }
            DB::table(static::TABLE)->where('id', $id)->update($values);

            return $id;
        }
        $user = \Exment::user();
        $values['created_user_id'] = $user ? $user->getUserId() : null;
        $values['created_at'] = Carbon::now();

        return intval(DB::table(static::TABLE)->insertGetId($values));
    }

    public static function delete(int $id): void
    {
        DB::table('pmbl_runs')->where('connection_id', $id)->delete();
        DB::table('pmbl_links')->where('connection_id', $id)->delete();
        DB::table(static::TABLE)->where('id', $id)->delete();
    }

    public static function hasKey(object $conn): bool
    {
        return static::apiKey($conn) !== '';
    }

    /** The plain key, or '' when none is stored or it no longer decrypts. */
    public static function apiKey(object $conn): string
    {
        // the key of a connection of Exment's data sync, given decrypted (SyncBridge)
        if (isset($conn->api_key_plain) && strval($conn->api_key_plain) !== '') {
            return strval($conn->api_key_plain);
        }
        $enc = strval($conn->api_key_enc ?? '');
        if ($enc === '') {
            return '';
        }
        try {
            return Crypt::decryptString($enc);
        } catch (\Throwable $e) {
            return '';
        }
    }

    public static function client(object $conn): Backlog
    {
        return new Backlog(strval($conn->space_url), static::apiKey($conn));
    }

    /** @return array<string, mixed> */
    public static function mapping(object $conn): array
    {
        $m = json_decode(strval($conn->mapping ?? ''), true);

        return is_array($m) ? $m : [];
    }

    /**
     * @param array<string, mixed> $values
     */
    public static function patch(int $id, array $values): void
    {
        DB::table(static::TABLE)->where('id', $id)->update($values);
    }

    /**
     * Take the connection for one run. One statement, so two runs started at
     * the same moment cannot both get it.
     */
    public static function lock(int $id): bool
    {
        $now = Carbon::now();

        return DB::table(static::TABLE)->where('id', $id)
            ->where(function ($q) use ($now) {
                $q->whereNull('locked_until')->orWhere('locked_until', '<', $now);
            })
            ->update(['locked_until' => $now->copy()->addMinutes(static::LOCK_MINUTES)]) === 1;
    }

    public static function unlock(int $id): void
    {
        DB::table(static::TABLE)->where('id', $id)->update(['locked_until' => null]);
    }

    /** The connection's settings page, for a notice. */
    public static function settingsUrl(int $id): string
    {
        try {
            $plugin = \Exceedone\Exment\Model\Plugin::where('plugin_name', 'ExmentPMBacklog')->first();

            return $plugin ? $plugin->getFullUrl(strval($id)) : '';
        } catch (\Throwable $e) {
            return '';
        }
    }

    /** Whether the schedule should run it now. */
    public static function due(object $conn): bool
    {
        if (!$conn->enabled || !static::hasKey($conn) || empty($conn->mapping)) {
            return false;
        }

        return empty($conn->next_run_at) || Carbon::parse($conn->next_run_at)->lte(Carbon::now());
    }
}
