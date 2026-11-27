<?php

namespace App\Plugins\ExmentGroupware\Chat;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * How long a room keeps its messages (ISS02945 step 3): a room's retention_days, set by its
 * owner. Older messages, their history, reactions, tasks and files are removed at most once a
 * day, when someone opens the chat; each removal is written to Exment's operation log.
 */
class ChatRetention
{
    const CHOICES = [0, 30, 90, 180, 365, 730, 1825];

    public static function clean($days): ?int
    {
        $d = intval($days);

        return in_array($d, static::CHOICES, true) && $d > 0 ? $d : null;
    }

    /**
     * The batch of the plugin runs it (PluginBatch --param1=chat, every 5 minutes). Only on a site
     * where the batch has not run for 3 days (no scheduler) does opening the chat run it, once a
     * day at most (a lock in the cache), after the response.
     */
    public static function maybe(): void
    {
        try {
            if (\Illuminate\Support\Facades\Cache::has('gw_chat_retention_batch')) {
                return;
            }
            if (!\Illuminate\Support\Facades\Cache::add('gw_chat_retention', 1, 86400)) {
                return;
            }
        } catch (\Throwable $e) {
            return;
        }
        app()->terminating(function () {
            try {
                static::run();
            } catch (\Throwable $e) {
                Log::warning('[ExmentGroupware] chat retention failed: ' . class_basename($e));
            }
        });
    }

    public static function run(bool $fromBatch = false): int
    {
        if ($fromBatch) {
            // the page leaves it to the batch while the batch runs
            try {
                \Illuminate\Support\Facades\Cache::put('gw_chat_retention_batch', 1, 3 * 86400);
            } catch (\Throwable $e) {
                // the page keeps doing it
            }
        }
        $total = 0;
        foreach (DB::table('gw_chat_rooms')->whereNotNull('retention_days')->where('retention_days', '>', 0)->get(['id', 'retention_days']) as $room) {
            $before = now()->subDays(intval($room->retention_days));
            $ids = DB::table('gw_chat_messages')->where('room_id', $room->id)->where('created_at', '<', $before)->limit(5000)->pluck('id')->all();
            if ($ids === []) {
                continue;
            }
            $disk = \Illuminate\Support\Facades\Storage::disk(config('admin.upload.disk'));
            foreach (DB::table('gw_chat_messages')->whereIn('id', $ids)->get(['files']) as $m) {
                foreach ((array) json_decode(strval($m->files ?? '[]'), true) as $f) {
                    $path = strval($f['path'] ?? '');
                    if (preg_match('#^gw_chat/\d+/[0-9a-f]{32}$#', $path)) {
                        try {
                            $disk->delete($path);
                        } catch (\Throwable $e) {
                            // the row goes; a file left is found by its folder
                        }
                    }
                }
            }
            DB::transaction(function () use ($ids) {
                DB::table('gw_chat_revisions')->whereIn('message_id', $ids)->delete();
                if (\Illuminate\Support\Facades\Schema::hasTable('gw_chat_reactions')) {
                    DB::table('gw_chat_reactions')->whereIn('message_id', $ids)->delete();
                }
                if (\Illuminate\Support\Facades\Schema::hasTable('gw_chat_tasks')) {
                    DB::table('gw_chat_tasks')->whereIn('message_id', $ids)->whereNull('pm_issue')->delete();
                }
                DB::table('gw_chat_messages')->whereIn('id', $ids)->delete();
            });
            static::log('plugins/exment_groupware/chat/retention', ['room' => intval($room->id), 'days' => intval($room->retention_days), 'removed' => count($ids)]);
            $total += count($ids);
        }

        return $total;
    }

    /** A line of Exment's operation log (who: the person whose visit ran it, or the system). */
    public static function log(string $path, array $input): void
    {
        try {
            $user = \Exment::user();
            \Exceedone\Exment\Model\OperationLog::create([
                'user_id' => $user ? $user->id : 0,
                'path' => mb_substr($path, 0, 255),
                'method' => 'DELETE',
                'ip' => strval(request()->ip() ?? ''),
                'input' => json_encode($input),
            ]);
        } catch (\Throwable $e) {
            Log::info('[ExmentGroupware] chat: ' . $path . ' ' . json_encode($input));
        }
    }
}
