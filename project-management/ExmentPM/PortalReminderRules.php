<?php

namespace App\Plugins\ExmentPM;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The reminders of one project (ISS00432, stage 1b): whether the project has them, its own rule
 * or the site's, and the channels its reminders go to as well (Slack, Teams, LINE WORKS).
 *
 * One row per project in pm_portal_reminder_rules. A webhook URL is a secret: it is kept
 * encrypted (Exment's data keys when the site has them, else the application key), never sent
 * back to a screen (masked), and checked with Exment's WebhookUrlGuard when it is saved; Exment
 * checks it again when it posts. A LINE WORKS channel is the id of a channel of the bot of the
 * site (Exment 7.3, its feature on).
 */
class PortalReminderRules
{
    const TABLE = 'pm_portal_reminder_rules';
    const TYPES = ['slack', 'teams', 'line_works'];
    const MAX_CHANNELS = 5;

    const ENCRYPTED = 'Exceedone\Exment\Services\Encryption\EncryptedValue';
    const DATA_KEYS = 'Exceedone\Exment\Services\Encryption\DataKeys';
    const GUARD = 'Exceedone\Exment\Services\WebhookUrlGuard';
    const LINE_WORKS = 'Exceedone\Exment\Services\LineWorks\LineWorks';

    /** @var array<int, array<string, mixed>>|null project id => rule, read once per run */
    protected static $all = null;

    public static function exists(): bool
    {
        return \Schema::hasTable(static::TABLE);
    }

    public static function forget(): void
    {
        static::$all = null;
    }

    /**
     * Every project's rule (read once).
     *
     * @return array<int, array{enabled: bool, use_site: bool, settings: array<string, mixed>, channels: array<int, array<string, string>>}>
     */
    public static function all(): array
    {
        if (static::$all !== null) {
            return static::$all;
        }
        static::$all = [];
        if (!static::exists()) {
            return static::$all;
        }
        foreach (DB::table(static::TABLE)->get() as $row) {
            static::$all[intval($row->project_id)] = static::fromRow($row);
        }

        return static::$all;
    }

    /** @return array{enabled: bool, use_site: bool, settings: array<string, mixed>, channels: array<int, array<string, string>>}|null */
    public static function forProject(int $projectId): ?array
    {
        return static::all()[$projectId] ?? null;
    }

    protected static function fromRow($row): array
    {
        $settings = json_decode(strval($row->settings ?? ''), true);
        $channels = json_decode(strval($row->channels ?? ''), true);

        return [
            'enabled' => boolval($row->enabled),
            'use_site' => boolval($row->use_site),
            'settings' => PortalReminder::parseSettings(is_array($settings) ? $settings : []),
            'channels' => array_values(array_filter(is_array($channels) ? $channels : [], function ($c) {
                return is_array($c) && in_array($c['type'] ?? '', static::TYPES, true);
            })),
        ];
    }

    /** Whether LINE WORKS can be posted to on this site. */
    public static function lineWorksAvailable(): bool
    {
        if (!class_exists(static::LINE_WORKS)) {
            return false;
        }
        try {
            return boolval(call_user_func([static::LINE_WORKS, 'enabled']));
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Save the rule of a project from the form. A channel whose URL is left empty keeps the one
     * kept before (the screen never has it); "remove" drops a channel.
     *
     * @param array<string, mixed> $input
     * @return array<int, string> the errors (nothing saved), or none
     */
    public static function save(int $projectId, array $input, int $userId): array
    {
        $errors = [];
        $old = static::forProject($projectId);
        $oldChannels = $old['channels'] ?? [];
        $channels = [];
        foreach (array_slice((array) ($input['channels'] ?? []), 0, static::MAX_CHANNELS) as $i => $c) {
            if (!is_array($c) || !empty($c['remove'])) {
                continue;
            }
            $type = strval($c['type'] ?? '');
            if (!in_array($type, static::TYPES, true)) {
                continue;
            }
            $label = mb_substr(trim(strval($c['label'] ?? '')), 0, 40);
            if ($type === 'line_works') {
                $id = trim(strval($c['channel'] ?? ''));
                if (!preg_match('/^[A-Za-z0-9_\-]{1,100}$/', $id)) {
                    $errors[] = strval(PortalDefinition::trans('remind.err_channel_id'));
                    continue;
                }
                $channels[] = ['type' => $type, 'label' => $label, 'channel' => $id];
                continue;
            }
            $url = trim(strval($c['url'] ?? ''));
            if ($url === '') {
                // kept as it was, if it was there and of the same kind
                $prev = $oldChannels[intval($c['index'] ?? -1)] ?? null;
                if (is_array($prev) && ($prev['type'] ?? '') === $type && !empty($prev['url'])) {
                    $channels[] = ['type' => $type, 'label' => $label, 'url' => $prev['url']];
                } else {
                    $errors[] = strval(PortalDefinition::trans('remind.err_url_empty'));
                }
                continue;
            }
            if (!static::urlAllowed($url)) {
                $errors[] = strval(PortalDefinition::trans('remind.err_url'));
                continue;
            }
            $channels[] = ['type' => $type, 'label' => $label, 'url' => static::encrypt($url)];
        }
        if (!empty($errors)) {
            return $errors;
        }
        $settings = PortalReminder::parseSettings((array) ($input['settings'] ?? []));
        $now = now();
        $values = [
            'enabled' => !empty($input['enabled']) ? 1 : 0,
            'use_site' => !empty($input['use_site']) ? 1 : 0,
            'settings' => json_encode($settings, JSON_UNESCAPED_UNICODE),
            'channels' => json_encode($channels, JSON_UNESCAPED_UNICODE),
            'updated_user_id' => $userId,
            'updated_at' => $now,
        ];
        if (DB::table(static::TABLE)->where('project_id', $projectId)->exists()) {
            DB::table(static::TABLE)->where('project_id', $projectId)->update($values);
        } else {
            DB::table(static::TABLE)->insert($values + ['project_id' => $projectId, 'created_at' => $now]);
        }
        static::forget();
        Log::info('pm_portal: the reminders of project ' . $projectId . ' were saved by user ' . $userId . ' (' . count($channels) . ' channels)');

        return [];
    }

    /** An https URL Exment may post to. */
    public static function urlAllowed(string $url): bool
    {
        if (!preg_match('#\Ahttps://[^\s"\'<>]+\z#i', $url) || strlen($url) > 2000) {
            return false;
        }
        if (class_exists(static::GUARD)) {
            try {
                return boolval(call_user_func([static::GUARD, 'isAllowed'], $url));
            } catch (\Throwable $e) {
                return false;
            }
        }
        return true;
    }

    public static function encrypt(string $plain): string
    {
        if (class_exists(static::ENCRYPTED) && class_exists(static::DATA_KEYS)) {
            try {
                if (call_user_func([static::DATA_KEYS, 'hasKey'])) {
                    return strval(call_user_func([static::ENCRYPTED, 'encrypt'], $plain));
                }
            } catch (\Throwable $e) {
                // the application key below
            }
        }
        return 'app:' . encrypt($plain);
    }

    public static function decrypt(?string $value): ?string
    {
        $value = strval($value);
        if ($value === '') {
            return null;
        }
        try {
            if (strpos($value, 'app:') === 0) {
                return strval(decrypt(substr($value, 4)));
            }
            if (class_exists(static::ENCRYPTED) && call_user_func([static::ENCRYPTED, 'isEncrypted'], $value)) {
                $plain = call_user_func([static::ENCRYPTED, 'decrypt'], $value);
                return $plain === null ? null : strval($plain);
            }
        } catch (\Throwable $e) {
            Log::warning('pm_portal: a webhook URL of the reminders could not be decrypted');
        }
        return null;
    }

    /** The URL as the screen shows it: the host and the last 4 characters. */
    public static function mask(?string $encrypted): string
    {
        $plain = static::decrypt($encrypted);
        if ($plain === null) {
            return '';
        }
        $host = strval(parse_url($plain, PHP_URL_HOST));
        return 'https://' . $host . '/…' . substr($plain, -4);
    }

    /**
     * Post one reminder to the channels of a project. Never throws.
     *
     * @param array<int, array<string, string>> $channels
     */
    public static function post(array $channels, $issue, string $title, string $subject, string $line, string $url): void
    {
        $plain = function ($s) {
            return str_replace('${', '$ {', strval($s));
        };
        foreach ($channels as $c) {
            try {
                if ($c['type'] === 'line_works') {
                    if (!static::lineWorksAvailable()) {
                        continue;
                    }
                    $content = call_user_func([static::LINE_WORKS, 'content'], $plain($title), $plain($subject . "\n" . $line), $url);
                    call_user_func([static::LINE_WORKS, 'send'], call_user_func([static::LINE_WORKS, 'toChannel'], $c['channel']), $content);
                    continue;
                }
                $hook = static::decrypt($c['url'] ?? null);
                if ($hook === null) {
                    continue;
                }
                $method = $c['type'] === 'slack' ? 'notifySlack' : 'notifyTeams';
                call_user_func(['Exceedone\Exment\Services\NotifyService', $method], [
                    'webhook_url' => $hook,
                    'custom_value' => $issue,
                    'subject' => $plain($title),
                    'body' => $plain($subject) . "\n" . $plain($line) . "\n" . $url,
                ]);
            } catch (\Throwable $e) {
                // the class only: the message of an HTTP error can carry the URL, a secret
                Log::warning('pm_portal reminder to a ' . $c['type'] . ' channel failed on issue ' . $issue->id . ' (' . get_class($e) . ')');
            }
        }
    }
}
