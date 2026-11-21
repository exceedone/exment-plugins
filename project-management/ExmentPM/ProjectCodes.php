<?php

namespace App\Plugins\ExmentPM;

use Exceedone\Exment\Model\CustomValue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * The codes a project answered to before it was renamed (ISS00440).
 *
 * A project's address is its code (/plugins/exment_pm/EXMENT_CUSTOMER), so a
 * rename would break every bookmark and every link in an old mail. The old
 * code is kept here when the code changes, and the portal sends an address
 * with it on to the project's new one. The project's current code always
 * wins: an old code that another project has taken since names that project.
 *
 * Codes are kept upper-cased, the way the portal shows them, and looked up
 * that way, so either case finds them.
 */
class ProjectCodes
{
    const TABLE = 'pm_portal_project_codes';

    /** @var bool|null */
    protected static $ready;

    /**
     * Whether the table is there. The migration runs when the plugin is
     * installed or updated; until then nothing is remembered, and nothing
     * fails.
     */
    public static function ready(): bool
    {
        if (static::$ready === null) {
            try {
                static::$ready = Schema::hasTable(static::TABLE);
            } catch (\Throwable $e) {
                static::$ready = false;
            }
        }

        return static::$ready;
    }

    /**
     * On a project's save: when its code changes, keep the old one.
     *
     * Called from the saving listener (PluginEvent::registerGuards), which
     * runs for every save - the portal's screens, Exment's data screen, the
     * API and imports alike.
     */
    public static function saving(CustomValue $model): void
    {
        if (!$model->exists) {
            return;
        }
        $def = PortalDefinition::config();
        if (optional($model->custom_table)->table_name !== array_get($def, 'project_table')) {
            return;
        }
        $column = strval(array_get($def, 'project.key', 'project_code'));
        $raw = $model->getRawOriginal('value');
        $before = is_array($raw) ? $raw : (array) json_decode(strval($raw), true);
        $old = static::normal(array_get($before, $column));
        $new = static::normal(array_get((array) $model->value, $column));
        if ($old === '' || $old === $new) {
            return;
        }
        static::remember(intval($model->id), $old);
    }

    public static function remember(int $projectId, string $code): void
    {
        $code = static::normal($code);
        if ($projectId <= 0 || $code === '' || !static::ready()) {
            return;
        }
        try {
            $now = now();
            DB::table(static::TABLE)->updateOrInsert(
                ['project_id' => $projectId, 'code' => $code],
                ['updated_at' => $now, 'created_at' => $now]
            );
        } catch (\Throwable $e) {
            // The rename itself must not fail because the old address could
            // not be kept.
            Log::warning('pm_portal could not keep the old code of project ' . $projectId . ': ' . $e->getMessage());
        }
    }

    /**
     * The projects that answered to $code before, the latest rename first.
     *
     * @return array<int, int>
     */
    public static function projectIds(string $code): array
    {
        $code = static::normal($code);
        if ($code === '' || strlen($code) > 200 || !static::ready()) {
            return [];
        }
        try {
            return DB::table(static::TABLE)->where('code', $code)
                ->orderBy('updated_at', 'desc')->orderBy('id', 'desc')
                ->pluck('project_id')->map(function ($v) {
                    return intval($v);
                })->unique()->values()->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Drop what is kept for a project id. Ids come back after Exment empties
     * a table (see PluginEvent::forget()), and a new project must not inherit
     * another project's old addresses.
     */
    public static function forget(int $projectId): void
    {
        if ($projectId <= 0 || !static::ready()) {
            return;
        }
        try {
            DB::table(static::TABLE)->where('project_id', $projectId)->delete();
        } catch (\Throwable $e) {
            Log::warning('pm_portal could not clear the old codes of project ' . $projectId . ': ' . $e->getMessage());
        }
    }

    protected static function normal($code): string
    {
        return is_scalar($code) ? strtoupper(trim(strval($code))) : '';
    }
}
