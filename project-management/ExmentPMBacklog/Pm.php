<?php

namespace App\Plugins\ExmentPMBacklog;

use Exceedone\Exment\Model\CustomTable;

/**
 * The Exment PM tables the connector writes to, by the names ExmentPM
 * installs them under. ExmentPM's own definition wins when it is loaded, so
 * an install with prefixed table names is followed.
 */
class Pm
{
    const DEFAULTS = [
        'project_table' => 'project',
        'issue_table' => 'pm_issue',
        'milestone_table' => 'task_milestone',
        'category_table' => 'task_category',
        'status_table' => 'pm_status',
        'issue_type_table' => 'pm_issue_type',
        'priority_table' => 'pm_priority',
        'resolution_table' => 'pm_resolution',
    ];

    /** @var array<string, string>|null */
    protected static $names;

    public static function name(string $role): string
    {
        if (static::$names === null) {
            $names = static::DEFAULTS;
            $def = 'App\\Plugins\\ExmentPM\\PortalDefinition';
            if (class_exists($def)) {
                try {
                    $config = (array) $def::config();
                    foreach (array_keys($names) as $k) {
                        if (!empty($config[$k]) && is_string($config[$k])) {
                            $names[$k] = $config[$k];
                        }
                    }
                } catch (\Throwable $e) {
                    // the defaults are what a standard install uses
                }
            }
            static::$names = $names;
        }

        return static::$names[$role] ?? static::DEFAULTS[$role];
    }

    public static function table(string $role): ?CustomTable
    {
        $t = CustomTable::getEloquent(static::name($role));

        return $t instanceof CustomTable ? $t : null;
    }

    /**
     * Rows that hang off a project (statuses, issue types).
     *
     * @return \Illuminate\Support\Collection<int, mixed>
     */
    public static function children(string $role, int $projectId)
    {
        $t = static::table($role);
        if (!$t) {
            return collect();
        }

        return $t->getValueModel()->query()
            ->where('parent_type', static::name('project_table'))
            ->where('parent_id', $projectId)
            ->get();
    }

    /**
     * Rows tied to a project through their "project" column (categories,
     * milestones).
     *
     * @return \Illuminate\Support\Collection<int, mixed>
     */
    public static function byProjectColumn(string $role, int $projectId)
    {
        $t = static::table($role);
        if (!$t) {
            return collect();
        }

        return $t->getValueModel()->query()->get()->filter(function ($row) use ($projectId) {
            return strval(array_get($row->value, 'project')) === strval($projectId);
        })->values();
    }

    /** @return \Illuminate\Support\Collection<int, mixed> */
    public static function all(string $role)
    {
        $t = static::table($role);

        return $t ? $t->getValueModel()->query()->get() : collect();
    }

    /**
     * name => id
     *
     * @param iterable<mixed> $rows
     * @return array<string, int>
     */
    public static function byName($rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $name = strval(array_get($row->value, 'name'));
            if ($name !== '' && !isset($out[$name])) {
                $out[$name] = intval($row->id);
            }
        }

        return $out;
    }

    /** Backlog's UTC time (2025-12-02T07:59:12Z) in the app's time zone, as Exment stores it. */
    public static function localTime(?string $ts): ?string
    {
        if (!$ts) {
            return null;
        }
        try {
            return \Carbon\Carbon::parse($ts)->setTimezone(config('app.timezone') ?: 'UTC')->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            return null;
        }
    }
}
