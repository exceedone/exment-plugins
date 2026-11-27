<?php

namespace App\Plugins\ExmentPM;

use Carbon\Carbon;
use Exceedone\Exment\Enums\Permission;
use Exceedone\Exment\Model\CustomColumn;
use Exceedone\Exment\Model\CustomTable;

/**
 * The card of ExmentPM on "My insights" of Exment (ISS02964): the issues assigned to the user who
 * looks. Exment calls PluginPage::myInsightsCard() with that user and the period; nothing here
 * reads another user's issues. Exment versions without the page never call it.
 */
class PortalMyInsights
{
    /** Due within this many days: due soon. */
    public const DUE_SOON_DAYS = 7;

    /** The issues read for the hours, at most (the card stays quick on a large site). */
    public const HOURS_ROWS = 2000;

    /**
     * @param mixed $user the login user (Exment's LoginUser)
     * @return array<string, mixed>|null the card, or null when the site has no issue table the user may see
     */
    public static function card($user, Carbon $from, Carbon $to): ?array
    {
        // only for a user who may open ExmentPM itself: the link of the card goes there
        if (!static::canOpen($user)) {
            return null;
        }
        $def = PortalDefinition::config();
        $table = CustomTable::getEloquent(array_get($def, 'issue_table'));
        if (!isset($table) || !$table->hasPermission(Permission::AVAILABLE_VIEW_CUSTOM_VALUE)) {
            return null;
        }
        $column = function (string $role) use ($def, $table) {
            $name = array_get($def, 'issue.' . $role);
            return is_string($name) && $name !== '' ? CustomColumn::getEloquent($name, $table) : null;
        };
        $assignees = $column('assignees');
        $status = $column('status');
        if (!isset($assignees) || !isset($status)) {
            return null;
        }
        $due = $column('due');
        $actual = $column('actual');
        $statusKey = $status->getQueryKey();
        $done = static::doneKeys($def, $status);
        $userId = intval($user->base_user_id);
        $today = Carbon::today();
        $end = $to->copy()->startOfDay()->addDay();

        $mine = function () use ($table, $assignees, $userId) {
            return getModelName($table)::query()->whereInArrayString($assignees->getQueryKey(), $userId);
        };
        $open = function () use ($mine, $statusKey, $done) {
            return $mine()->where(function ($query) use ($statusKey, $done) {
                $query->whereNull($statusKey);
                if (count($done) > 0) {
                    $query->orWhereNotIn($statusKey, $done);
                } else {
                    $query->orWhereNotNull($statusKey);
                }
            });
        };
        $finished = function () use ($mine, $statusKey, $done, $from, $end) {
            return $mine()->whereIn($statusKey, count($done) > 0 ? $done : [''])->where('updated_at', '>=', $from->copy()->startOfDay())->where('updated_at', '<', $end);
        };

        $rows = [['label' => PortalDefinition::trans('my_insights.open'), 'value' => $open()->count()]];
        if (isset($due)) {
            $dueKey = $due->getQueryKey();
            $late = $open()->whereNotNull($dueKey)->where($dueKey, '<>', '')->where($dueKey, '<', $today->format('Y-m-d'))->count();
            $rows[] = ['label' => PortalDefinition::trans('my_insights.overdue'), 'value' => $late, 'warn' => $late > 0];
            $rows[] = ['label' => PortalDefinition::trans('my_insights.due_soon', static::DUE_SOON_DAYS), 'value' => $open()->where($dueKey, '>=', $today->format('Y-m-d'))
                ->where($dueKey, '<=', $today->copy()->addDays(static::DUE_SOON_DAYS)->format('Y-m-d'))->count()];
        }
        $rows[] = ['label' => PortalDefinition::trans('my_insights.finished'), 'value' => $finished()->count()];
        if (isset($actual)) {
            $hours = 0.0;
            foreach ([$finished(), $open()] as $query) {
                foreach ($query->limit(static::HOURS_ROWS)->get() as $issue) {
                    $hours += floatval($issue->getValue($actual->column_name));
                }
            }
            $rows[] = ['label' => PortalDefinition::trans('my_insights.hours'), 'value' => number_format($hours, 1) . ' h'];
        }

        return [
            'title' => PortalDefinition::trans('my_insights.title'),
            'rows' => $rows,
            'url' => PortalDefinition::url('issues') . '?as=me',
            'url_label' => PortalDefinition::trans('my_insights.open_list'),
            'help' => PortalDefinition::trans('my_insights.help'),
        ];
    }

    /**
     * Whether the user may open the pages of ExmentPM (the plugin is active and the user has its
     * access, as Exment checks for the menu of the plugin).
     *
     * @param mixed $user
     */
    public static function canOpen($user): bool
    {
        $plugin = PortalDefinition::plugin();
        if (!isset($plugin) || !boolval($plugin->active_flg) || !is_object($user) || !method_exists($user, 'hasPermissionPlugin')) {
            return false;
        }
        return boolval($user->hasPermissionPlugin($plugin, Permission::PLUGIN_ACCESS));
    }

    /**
     * The values of the status that mean done: the rows of the status table with their done flag,
     * or the words of the shipped list.
     *
     * @param array<string, mixed> $def
     * @return array<int, string>
     */
    protected static function doneKeys(array $def, CustomColumn $status): array
    {
        $target = $status->select_target_table;
        $doneColumn = array_get($def, 'status_row.done');
        if (isset($target) && is_string($doneColumn) && $doneColumn !== '' && $target->table_name === array_get($def, 'status_table')) {
            return $target->getValueModel()->newQuery()->get()->filter(function ($row) use ($doneColumn) {
                return boolval(array_get($row->value, $doneColumn));
            })->map(function ($row) {
                return strval($row->id);
            })->values()->all();
        }
        return array_values(array_map('strval', (array) array_get($def, 'done_statuses', [])));
    }
}
