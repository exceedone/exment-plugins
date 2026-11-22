<?php

namespace App\Plugins\ExmentPMBacklog;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/** Which Backlog row became which Exment row. */
class Links
{
    const TABLE = 'pmbl_links';

    public static function find(string $host, string $kind, int $backlogId): ?object
    {
        $row = DB::table(static::TABLE)
            ->where('space_host', $host)->where('kind', $kind)->where('backlog_id', $backlogId)
            ->first();

        return $row ?: null;
    }

    public static function put(int $connId, string $host, string $kind, int $backlogId, int $exmentId, ?string $key = null, ?int $parentBacklogId = null): void
    {
        $now = Carbon::now();
        DB::table(static::TABLE)->updateOrInsert(
            ['space_host' => $host, 'kind' => $kind, 'backlog_id' => $backlogId],
            ['connection_id' => $connId, 'exment_id' => $exmentId, 'backlog_key' => mb_substr(strval($key), 0, 64) ?: null,
                'parent_backlog_id' => $parentBacklogId, 'updated_at' => $now, 'created_at' => $now]
        );
    }

    public static function touch(int $linkId, string $updated, ?int $lastCommentId): void
    {
        DB::table(static::TABLE)->where('id', $linkId)->update([
            'backlog_updated' => $updated,
            'last_comment_id' => $lastCommentId,
            'updated_at' => Carbon::now(),
        ]);
    }

    public static function forget(int $linkId): void
    {
        DB::table(static::TABLE)->where('id', $linkId)->delete();
    }

    public static function count(int $connId, string $kind = 'issue'): int
    {
        return DB::table(static::TABLE)->where('connection_id', $connId)->where('kind', $kind)->count();
    }
}
