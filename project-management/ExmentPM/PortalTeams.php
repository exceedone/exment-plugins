<?php

namespace App\Plugins\ExmentPM;

use Exceedone\Exment\Enums\MailBodyType;
use Exceedone\Exment\Enums\SystemTableName;
use Exceedone\Exment\Model\CustomValue;
use Exceedone\Exment\Model\System;
use Exceedone\Exment\Notifications\MailSender;
use Illuminate\Support\Facades\Log;

/**
 * The notices of an issue (a new issue, a change, a comment) by mail and by Teams (ISS02863), to
 * each person the bells went to.
 *
 * How each person gets them: their own choice (the "Notices" menu of the portal: bell only, by
 * mail too, by Teams too, by both), or the default of the plugin setting. Teams needs Exment
 * 7.2.6 (NotifyService::notifyTeamsPersonal, the feature "Teams notifications" and the address of
 * the Teams workflow). A writer's "also by e-mail" still mails every person of the bells.
 *
 * The mail and the Teams card look the same: the title with the issue key in bold
 * (課題が更新されました（KEY）), the link to the issue, the subject of the issue in bold, what
 * changed as a numbered list. The mail is HTML (Exment adds a text part) or text, as the system
 * setting says.
 */
class PortalTeams
{
    /** The choices: bell only, mail too, Teams too, both. */
    public const CHOICES = ['bell', 'mail', 'teams', 'both'];

    /** The setting of a user (Exment's user settings). */
    public const USER_SETTING = 'pmportal.notify_by';

    /** Whether Exment can send to a person's Teams chat. */
    public static function teamsAvailable(): bool
    {
        return method_exists('Exceedone\Exment\Services\NotifyService', 'notifyTeamsPersonal');
    }

    /** The default of the site (the plugin setting). */
    public static function siteDefault(): string
    {
        $value = strval(IssueAutofill::option('notify_by_default', 'bell'));
        return in_array($value, static::CHOICES, true) ? $value : 'bell';
    }

    /**
     * The choice of a user: their own, or the default of the site.
     *
     * @param mixed $user a login user or a user record
     */
    public static function choiceOf($user): string
    {
        $value = null;
        try {
            if ($user instanceof CustomValue) {
                // another user than the one logged in: their row of user_settings
                $setting = \Exceedone\Exment\Model\UserSetting::where('base_user_id', $user->id)->first();
                $value = isset($setting) ? array_get((array) ($setting->settings ?? []), static::USER_SETTING) : null;
            } elseif (is_object($user) && method_exists($user, 'getSettingValue')) {
                $value = $user->getSettingValue(static::USER_SETTING);
            }
        } catch (\Throwable $e) {
            $value = null;
        }
        $value = strval($value);
        return in_array($value, static::CHOICES, true) ? $value : static::siteDefault();
    }

    /**
     * After the answer: the mail and Teams for the people of the bells rung since $bell_mark.
     *
     * @param string $kind 'new' or 'updated'
     * @param array<int, string> $lines
     * @param bool $mailAll the writer ticked "also by e-mail"
     */
    public static function afterResponse(string $issue_table, CustomValue $issue, int $bell_mark, $me, string $kind, string $key, string $subject, array $lines, string $url, bool $mailAll = false): void
    {
        $issue_id = intval($issue->id);
        app()->terminating(function () use ($issue_table, $issue_id, $bell_mark, $me, $kind, $key, $subject, $lines, $url, $mailAll) {
            try {
                $targets = PortalMail::bellTargets($issue_table, $issue_id, $bell_mark, $me);
                static::sendTo($targets, $issue_table, $issue_id, $kind, $key, $subject, $lines, $url, $mailAll);
            } catch (\Throwable $e) {
                Log::error('pm_portal notices for issue ' . $issue_id . ' failed: ' . $e->getMessage());
            }
        });
    }

    /** The title: 課題が登録されました（KEY） / 課題が更新されました（KEY）. */
    public static function title(string $kind, string $key): string
    {
        return static::plain(strval(PortalDefinition::trans($kind === 'new' ? 'teams.title_new' : 'teams.title_updated', ['key' => $key])));
    }

    /**
     * The lines that are not empty, each on one line.
     *
     * @param array<int, string> $lines
     * @return array<int, string>
     */
    protected static function cleanLines(array $lines): array
    {
        return array_values(array_filter(array_map(function ($line) {
            return trim(preg_replace('/\s*\n\s*/u', ' ', str_replace(["\r\n", "\r"], "\n", strval($line))) ?? '');
        }, $lines), function ($line) {
            return $line !== '';
        }));
    }

    /**
     * The text of the Teams card: the subject in bold, then the lines (a numbered list).
     *
     * @param array<int, string> $lines
     */
    public static function body(string $subject, array $lines): string
    {
        $lines = static::cleanLines($lines);
        $text = $subject !== '' ? '**' . static::plain($subject) . '**' : '';
        if (count($lines) === 1) {
            return trim($text . "\n\n" . static::plain($lines[0]));
        }
        $list = [];
        foreach ($lines as $i => $line) {
            $list[] = ($i + 1) . '. ' . static::plain($line);
        }
        return trim($text . "\n\n" . implode("\n", $list));
    }

    /**
     * The HTML of the mail: the title in bold, the link, the subject in bold, the lines.
     *
     * @param array<int, string> $lines
     */
    public static function mailHtml(string $title, string $subject, array $lines, string $url): string
    {
        $lines = static::cleanLines($lines);
        $html = '<p><strong>' . e(static::plain($title)) . '</strong></p>'
            . '<p><a href="' . e($url) . '">' . e($url) . '</a></p>';
        if ($subject !== '') {
            $html .= '<p><strong>' . e(static::plain($subject)) . '</strong></p>';
        }
        if (count($lines) === 1) {
            $html .= '<p>' . e(static::plain($lines[0])) . '</p>';
        } elseif (count($lines) > 1) {
            $html .= '<ol>' . implode('', array_map(function ($line) {
                return '<li>' . e(static::plain($line)) . '</li>';
            }, $lines)) . '</ol>';
        }
        return $html;
    }

    /**
     * The text of the mail (when the system sends text mails).
     *
     * @param array<int, string> $lines
     */
    public static function mailText(string $title, string $subject, array $lines, string $url): string
    {
        $lines = static::cleanLines($lines);
        $text = static::plain($title) . "\n" . $url;
        if ($subject !== '') {
            $text .= "\n\n" . static::plain($subject);
        }
        if (count($lines) === 1) {
            $text .= "\n\n" . static::plain($lines[0]);
        } elseif (count($lines) > 1) {
            $list = [];
            foreach ($lines as $i => $line) {
                $list[] = ($i + 1) . '. ' . static::plain($line);
            }
            $text .= "\n\n" . implode("\n", $list);
        }
        return $text;
    }

    /**
     * @param array<int, int> $targets
     * @param array<int, string> $lines
     */
    protected static function sendTo(array $targets, string $issue_table, int $issue_id, string $kind, string $key, string $subject, array $lines, string $url, bool $mailAll): void
    {
        if (empty($targets)) {
            return;
        }
        $issue = getModelName($issue_table)::withoutGlobalScopes()->find($issue_id);
        $users = getModelName(SystemTableName::USER)::withoutGlobalScopes()->whereIn('id', $targets)->get();
        $html = !isMatchString(System::system_mail_body_type(), MailBodyType::PLAIN);
        foreach ($users as $user) {
            $choice = static::choiceOf($user);
            $byMail = $mailAll || in_array($choice, ['mail', 'both'], true);
            $byTeams = static::teamsAvailable() && in_array($choice, ['teams', 'both'], true);
            if (!$byMail && !$byTeams) {
                continue;
            }
            $title = static::title($kind, $key);
            if ($byMail && !is_nullorempty($user->getValue('email'))) {
                try {
                    MailSender::make(null, $user)
                        ->subject('[' . static::plain($key) . '] ' . static::plain($subject))
                        ->body($html ? static::mailHtml($title, $subject, $lines, $url) : static::mailText($title, $subject, $lines, $url))
                        ->send();
                } catch (\Throwable $e) {
                    Log::warning('pm_portal mail to user ' . $user->id . ' on issue ' . $issue_id . ' failed: ' . $e->getMessage());
                }
            }
            if ($byTeams) {
                try {
                    \Exceedone\Exment\Services\NotifyService::notifyTeamsPersonal([
                        'user' => $user,
                        'custom_value' => $issue,
                        'subject' => $title,
                        'body' => static::body($subject, $lines),
                        'url' => $url,
                    ]);
                } catch (\Throwable $e) {
                    Log::warning('pm_portal teams to user ' . $user->id . ' on issue ' . $issue_id . ' failed: ' . $e->getMessage());
                }
            }
        }
    }

    /** A text Exment must not read as ${...} when it fills the template. */
    protected static function plain(string $text): string
    {
        return str_replace('${', '$ {', $text);
    }
}
