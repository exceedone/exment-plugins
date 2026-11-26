<?php

namespace App\Plugins\ExmentPMBacklog;

use Exceedone\Exment\Model\NotifyNavbar;
use Exceedone\Exment\Model\System;
use Illuminate\Support\Facades\Log;

/**
 * Tell the system administrators (Exment's bell) when a connection starts
 * failing, and once more when it works again. Not on every failed run: a
 * connection failing every 30 minutes overnight would bury the bell.
 */
class Notices
{
    /**
     * @param int $before fail_count before this run
     * @param int $after fail_count after it
     */
    public static function afterRun(object $conn, int $before, int $after, string $message, string $url): void
    {
        if ($before === 0 && $after > 0) {
            static::send(
                Lang::t('notice_failed_subject', ['name' => $conn->name]),
                Lang::t('notice_failed_body', ['name' => $conn->name, 'message' => $message, 'url' => $url])
            );
        } elseif ($before > 0 && $after === 0) {
            static::send(
                Lang::t('notice_recovered_subject', ['name' => $conn->name]),
                Lang::t('notice_recovered_body', ['name' => $conn->name, 'count' => $before, 'url' => $url])
            );
        }
    }

    public static function send(string $subject, string $body): void
    {
        try {
            foreach ((array) System::system_admin_users() as $userId) {
                $bell = new NotifyNavbar();
                $bell->fill([
                    'notify_id' => 0,
                    'target_user_id' => $userId,
                    'notify_subject' => mb_substr($subject, 0, 200),
                    'notify_body' => $body,
                    'trigger_user_id' => null,
                ]);
                $bell->save();
            }
        } catch (\Throwable $e) {
            // A notice that cannot be sent must not fail the run's bookkeeping.
            Log::error('pm_backlog notice failed: ' . Secrets::scrub($e->getMessage()));
        }
    }
}
