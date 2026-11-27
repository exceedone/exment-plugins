<?php

namespace App\Plugins\ExmentSupportDesk;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * "Someone else is answering this."
 *
 * The screen holds a soft lock while an agent types a reply and refreshes it
 * every minute; it runs out by itself two minutes after the tab goes quiet.
 * Nobody is stopped from answering - two people answering the same customer
 * is the problem, not two people looking - the other agent only sees a
 * warning.
 */
class Locks
{
    const MINUTES = 2;

    /**
     * Take or refresh the lock for the current user.
     *
     * @return array{mine: bool, user_name: ?string}
     */
    public static function hold(int $ticketId): array
    {
        $user = \Exment::user();
        if (!isset($user)) {
            return ['mine' => false, 'user_name' => null];
        }
        $userId = intval($user->getUserId());
        $now = Carbon::now();

        $current = DB::table('sd_locks')->where('ticket_id', $ticketId)->first();
        if (isset($current) && intval($current->user_id) !== $userId && Carbon::parse($current->expires_at)->isFuture()) {
            return ['mine' => false, 'user_name' => $current->user_name];
        }

        DB::table('sd_locks')->updateOrInsert(['ticket_id' => $ticketId], [
            'user_id' => $userId,
            'user_name' => mb_substr(strval($user->user_name), 0, 255),
            'expires_at' => $now->copy()->addMinutes(static::MINUTES),
        ]);

        return ['mine' => true, 'user_name' => null];
    }

    /** Who else holds it, or null. */
    public static function other(int $ticketId): ?string
    {
        $user = \Exment::user();
        $current = DB::table('sd_locks')->where('ticket_id', $ticketId)->first();
        if (!isset($current) || Carbon::parse($current->expires_at)->isPast()) {
            return null;
        }
        if (isset($user) && intval($current->user_id) === intval($user->getUserId())) {
            return null;
        }

        return $current->user_name;
    }

    public static function release(int $ticketId): void
    {
        $user = \Exment::user();
        $q = DB::table('sd_locks')->where('ticket_id', $ticketId);
        if (isset($user)) {
            $q->where('user_id', $user->getUserId());
        }
        $q->delete();
    }
}
