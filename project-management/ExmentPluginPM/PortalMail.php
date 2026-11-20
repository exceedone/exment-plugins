<?php

namespace App\Plugins\ExmentPluginPM;

use Exceedone\Exment\Enums\MailBodyType;
use Exceedone\Exment\Enums\SystemTableName;
use Exceedone\Exment\Model\CustomValue;
use Exceedone\Exment\Model\NotifyNavbar;
use Exceedone\Exment\Model\System;
use Exceedone\Exment\Notifications\MailSender;
use Illuminate\Support\Facades\Log;

/**
 * "Also send by e-mail" on a new issue or a comment.
 *
 * The portal's notifications are bells (in-app). When the writer ticks the
 * box, everyone who got a bell for this one write also gets a mail with the
 * same news - the same people, so nobody hears by mail what they could not
 * read in the portal (PortalNotify already dropped bells to non-readers).
 *
 * The mails go out after the answer has been sent to the browser: an SMTP
 * server that takes a second per message must not make the comment box wait.
 */
class PortalMail
{
    /** Mails one write sends at most. */
    const LIMIT = 100;

    /**
     * Send the mails once the response is out.
     *
     * @param string $issue_table the issue table name
     * @param CustomValue $issue
     * @param int $bell_mark the highest bell id before the write
     * @param mixed $me the writer's user id
     * @param string $subject
     * @param string $body plain text
     * @param string $url where the issue opens
     */
    public static function afterResponse(string $issue_table, CustomValue $issue, int $bell_mark, $me, string $subject, string $body, string $url): void
    {
        $issue_id = intval($issue->id);
        app()->terminating(function () use ($issue_table, $issue_id, $bell_mark, $me, $subject, $body, $url) {
            try {
                static::send($issue_table, $issue_id, $bell_mark, $me, $subject, $body, $url);
            } catch (\Throwable $e) {
                Log::error('pm_portal mail for issue ' . $issue_id . ' failed: ' . $e->getMessage(), ['exception' => $e]);
            }
        });
    }

    /**
     * @param mixed $me
     */
    protected static function send(string $issue_table, int $issue_id, int $bell_mark, $me, string $subject, string $body, string $url): void
    {
        $targets = NotifyNavbar::withoutGlobalScopes()
            ->where('id', '>', $bell_mark)
            ->where('parent_type', $issue_table)
            ->where('parent_id', $issue_id)
            ->where('trigger_user_id', $me)
            ->pluck('target_user_id')
            ->map(function ($v) {
                return intval($v);
            })
            ->unique()
            ->reject(function ($v) use ($me) {
                return $v <= 0 || $v === intval($me);
            })
            ->take(self::LIMIT)
            ->values()
            ->all();
        static::sendTo($targets, $issue_id, $subject, $body, $url);
    }

    /**
     * "Mail this issue" from the issue screen: the people picked, now.
     * The caller has already kept only people who can read the issue.
     *
     * @param array<int, int> $user_ids
     */
    public static function afterResponseTo(array $user_ids, CustomValue $issue, string $subject, string $body, string $url): void
    {
        $issue_id = intval($issue->id);
        $user_ids = array_slice(array_values(array_unique(array_map('intval', $user_ids))), 0, self::LIMIT);
        app()->terminating(function () use ($user_ids, $issue_id, $subject, $body, $url) {
            try {
                static::sendTo($user_ids, $issue_id, $subject, $body, $url);
            } catch (\Throwable $e) {
                Log::error('pm_portal mail for issue ' . $issue_id . ' failed: ' . $e->getMessage(), ['exception' => $e]);
            }
        });
    }

    /**
     * One mail per user, each on its own: a bad address costs that mail only.
     *
     * @param array<int, int> $targets user ids
     */
    protected static function sendTo(array $targets, int $issue_id, string $subject, string $body, string $url): void
    {
        if (empty($targets)) {
            return;
        }

        $html = !isMatchString(System::system_mail_body_type(), MailBodyType::PLAIN);
        $text = $html
            ? e(static::literal($body)) . "\n\n" . '<a href="' . e($url) . '">' . e($url) . '</a>'
            : static::literal($body) . "\n\n" . $url;
        $subject = static::literal($subject);

        $users = getModelName(SystemTableName::USER)::withoutGlobalScopes()->whereIn('id', $targets)->get();
        foreach ($users as $user) {
            if (is_nullorempty($user->getValue('email'))) {
                continue;
            }
            try {
                MailSender::make(null, $user)
                    ->subject($subject)
                    ->body($text)
                    ->send();
            } catch (\Throwable $e) {
                Log::warning('pm_portal mail to user ' . $user->id . ' on issue ' . $issue_id . ' failed: ' . $e->getMessage(), ['exception' => $e]);
            }
        }
    }

    /**
     * Text the way it was typed. Exment's sender expands ${...} in the
     * subject and body, so a comment quoting "${system:...}" would otherwise
     * have it replaced by a value of the system.
     */
    protected static function literal(string $text): string
    {
        return str_replace('${', '$ {', $text);
    }
}
