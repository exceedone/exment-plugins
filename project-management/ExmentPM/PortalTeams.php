<?php

namespace App\Plugins\ExmentPM;

use Exceedone\Exment\Enums\SystemTableName;
use Exceedone\Exment\Model\CustomValue;
use Illuminate\Support\Facades\Log;

/**
 * The notices of an issue (a new issue, a change, a comment) in the Teams chat of each person
 * the bells went to (ISS02863), when the plugin setting "notify_teams" is on and Exment can send
 * to a person's Teams chat (Exment 7.2.6: NotifyService::notifyTeamsPersonal, the feature
 * "Teams notifications" and the address of the Teams workflow in the system setting).
 *
 * The message: the title in bold ("課題が更新されました（KEY）"), the issue's subject in bold,
 * what changed as a numbered list, the button to the issue. The title is in the language of each
 * person; the lines are written as they were for the bells.
 */
class PortalTeams
{
    /** Whether the issue notices go to Teams too. */
    public static function available(): bool
    {
        return method_exists('Exceedone\Exment\Services\NotifyService', 'notifyTeamsPersonal')
            && IssueAutofill::flag('notify_teams', false);
    }

    /**
     * After the answer: Teams to the people of the bells rung since $bell_mark.
     *
     * @param string $kind 'new' or 'updated'
     * @param array<int, string> $lines
     */
    public static function afterResponse(string $issue_table, CustomValue $issue, int $bell_mark, $me, string $kind, string $key, string $subject, array $lines, string $url): void
    {
        if (!static::available()) {
            return;
        }
        $issue_id = intval($issue->id);
        app()->terminating(function () use ($issue_table, $issue_id, $bell_mark, $me, $kind, $key, $subject, $lines, $url) {
            try {
                $targets = PortalMail::bellTargets($issue_table, $issue_id, $bell_mark, $me);
                static::sendTo($targets, $issue_table, $issue_id, $kind, $key, $subject, $lines, $url);
            } catch (\Throwable $e) {
                Log::error('pm_portal teams for issue ' . $issue_id . ' failed: ' . $e->getMessage());
            }
        });
    }

    /**
     * The text of the card: the subject in bold, then the lines as a numbered list.
     *
     * @param array<int, string> $lines
     */
    public static function body(string $subject, array $lines): string
    {
        $lines = array_values(array_filter(array_map(function ($line) {
            return trim(str_replace(["\r\n", "\r"], "\n", strval($line)));
        }, $lines), function ($line) {
            return $line !== '';
        }));
        $text = $subject !== '' ? '**' . static::plain($subject) . '**' : '';
        if (count($lines) === 1) {
            return trim($text . "\n\n" . static::plain($lines[0]));
        }
        $list = [];
        foreach ($lines as $i => $line) {
            // a line of several lines stays in its item
            $list[] = ($i + 1) . '. ' . str_replace("\n", ' ', static::plain($line));
        }
        return trim($text . "\n\n" . implode("\n", $list));
    }

    /**
     * @param array<int, int> $targets
     * @param array<int, string> $lines
     */
    protected static function sendTo(array $targets, string $issue_table, int $issue_id, string $kind, string $key, string $subject, array $lines, string $url): void
    {
        if (empty($targets)) {
            return;
        }
        $issue = getModelName($issue_table)::withoutGlobalScopes()->find($issue_id);
        $users = getModelName(SystemTableName::USER)::withoutGlobalScopes()->whereIn('id', $targets)->get();
        foreach ($users as $user) {
            try {
                $title = static::inLanguageOf($user, function () use ($kind, $key) {
                    return strval(PortalDefinition::trans($kind === 'new' ? 'teams.title_new' : 'teams.title_updated', ['key' => $key]));
                });
                \Exceedone\Exment\Services\NotifyService::notifyTeamsPersonal([
                    'user' => $user,
                    'custom_value' => $issue,
                    'subject' => static::plain($title),
                    'body' => static::body($subject, $lines),
                    'url' => $url,
                ]);
            } catch (\Throwable $e) {
                Log::warning('pm_portal teams to user ' . $user->id . ' on issue ' . $issue_id . ' failed: ' . $e->getMessage());
            }
        }
    }

    /**
     * @return mixed
     */
    protected static function inLanguageOf($user, \Closure $callback)
    {
        if (class_exists('Exceedone\Exment\Services\UserLocale') && method_exists('Exceedone\Exment\Services\UserLocale', 'runAs')) {
            return \Exceedone\Exment\Services\UserLocale::runAs($user, $callback);
        }
        return $callback();
    }

    /** A text Exment must not read as ${...} when it fills the template. */
    protected static function plain(string $text): string
    {
        return str_replace('${', '$ {', $text);
    }
}
