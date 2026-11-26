<?php

namespace App\Plugins\ExmentPM;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The reminders of one project (ISS00432, stage 1b): whether the project has them, and its own
 * rule or the site's. One row per project in pm_portal_reminder_rules.
 *
 * The chat channels its reminders go to are the project's channels that take reminders
 * (PortalChannels, 「チャットへのお知らせ」, ISS02939). The list this table had (the column
 * channels) was moved there by the migration of 2026-11-29 and is no longer read.
 */
class PortalReminderRules
{
    const TABLE = 'pm_portal_reminder_rules';

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
     * @return array<int, array{enabled: bool, use_site: bool, settings: array<string, mixed>}>
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

    /** @return array{enabled: bool, use_site: bool, settings: array<string, mixed>}|null */
    public static function forProject(int $projectId): ?array
    {
        return static::all()[$projectId] ?? null;
    }

    protected static function fromRow($row): array
    {
        $settings = json_decode(strval($row->settings ?? ''), true);

        return [
            'enabled' => boolval($row->enabled),
            'use_site' => boolval($row->use_site),
            'settings' => PortalReminder::parseSettings(is_array($settings) ? $settings : []),
        ];
    }

    /**
     * Save the rule of a project from the form.
     *
     * @param array<string, mixed> $input
     * @return array<int, string> the errors (nothing saved), or none
     */
    public static function save(int $projectId, array $input, int $userId): array
    {
        $settings = PortalReminder::parseSettings((array) ($input['settings'] ?? []));
        $now = now();
        $values = [
            'enabled' => !empty($input['enabled']) ? 1 : 0,
            'use_site' => !empty($input['use_site']) ? 1 : 0,
            'settings' => json_encode($settings, JSON_UNESCAPED_UNICODE),
            'updated_user_id' => $userId,
            'updated_at' => $now,
        ];
        if (DB::table(static::TABLE)->where('project_id', $projectId)->exists()) {
            DB::table(static::TABLE)->where('project_id', $projectId)->update($values);
        } else {
            DB::table(static::TABLE)->insert($values + ['project_id' => $projectId, 'created_at' => $now]);
        }
        static::forget();
        Log::info('pm_portal: the reminders of project ' . $projectId . ' were saved by user ' . $userId);

        return [];
    }
}
