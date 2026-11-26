<?php

namespace App\Plugins\ExmentPM;

use Exceedone\Exment\Enums\PluginEventTrigger;
use Exceedone\Exment\Enums\SystemTableName;
use Exceedone\Exment\Model\CustomValue;
use Exceedone\Exment\Model\NotifyNavbar;
use Exceedone\Exment\Services\Plugin\PluginEventBase;
use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Last line of defence for the integrity rules, on the model's saving event.
 *
 * PluginValidator alone is not enough. Exment hands a validator plugin
 * array_get($input, 'value'), but CustomValue::setValueStrictly calls
 * validateValue with a flat column map that has no 'value' key, so the
 * validator receives null and waves the write through. That path is the one
 * the plugin's own lightapi uses, so relying on the validator by itself would
 * leave the rules enforced on the admin form and bypassable over HTTP.
 *
 * The saving event does not have that problem: it fires from CustomValue::save
 * for nearly every caller, and the model already carries the merged values and
 * its parent, so the rules see exactly what is about to be written. The one
 * save it misses, a revision restore, is caught by registerGuards().
 */
class PluginEvent extends PluginEventBase
{
    /**
     * Whether this process already listens on the models itself.
     *
     * @var bool
     */
    protected static $guarding = false;

    /**
     * Rows whose side tables were already cleared in this process, so a
     * second save of a new row in the same request leaves alone whatever
     * the first one set up.
     *
     * @var array<string, bool>
     */
    protected static $forgotten = [];

    public function execute()
    {
        $model = $this->custom_value;
        if (!isset($model) || !isset($model->custom_table)) {
            return;
        }

        $event = optional($this->pluginOptions)->event_type;
        if ($event === PluginEventTrigger::SAVED) {
            $this->afterSave($model);

            return;
        }
        if ($event !== null && $event !== PluginEventTrigger::SAVING) {
            return;
        }

        static::clearMidnightDueTime($model);
        static::check($model);
        // what the issue was, for its chat channels after the save (ISS02939)
        if ($model->custom_table->table_name === array_get(PortalDefinition::config(), 'issue_table')) {
            PortalChannels::issueSaving($model);
            // the custom attributes as kept: rounded, in order (ISS02939)
            try {
                PortalAttributes::normalizeSaving($model);
            } catch (\Throwable $e) {
                Log::warning('pm_portal attributes: issue ' . $model->id . ' not normalised (' . get_class($e) . ')');
            }
        }
    }

    /**
     * A due time of 00:00 is stored as no time (ISS00299, snoro 2026-11-19).
     *
     * The time picker starts its spinner at 00:00:00, so a click that only
     * meant to open it writes midnight - and midnight would make the issue
     * overdue from the first minute of its due date. Nobody means "due at
     * the very start of the day"; an empty time is the whole day.
     */
    protected static function clearMidnightDueTime(CustomValue $model): void
    {
        $def = PortalDefinition::config();
        if ($model->custom_table->table_name !== strval(array_get($def, 'issue_table'))) {
            return;
        }
        $column = strval(array_get($def, 'issue_extra.due_time', 'due_time'));
        $value = (array) $model->getAttribute('value');
        if (!array_key_exists($column, $value)) {
            return;
        }
        if (preg_match('/^0{1,2}:00(:00)?$/', trim(strval($value[$column])))) {
            $model->setValue([$column => null]);
        }
    }

    /**
     * Refuse a write that breaks the portal's rules.
     *
     * A ValidationException keeps the admin form and the json endpoints
     * behaving the way each already expects: a redirect back with the errors
     * attached, or a 422 carrying them.
     *
     * @throws ValidationException
     */
    public static function check(CustomValue $model): void
    {
        $messages = PortalRules::validateSave($model);
        if (empty($messages)) {
            return;
        }

        $validator = Validator::make([], []);
        foreach ($messages as $column => $message) {
            $validator->errors()->add('value.' . $column, $message);
        }

        throw new ValidationException($validator);
    }

    /**
     * Put the portal's own model listeners in place, once per process.
     *
     * Two kinds of write never reach a plugin event, so the plugin listens on
     * the models themselves:
     *
     * - A revision restore. Exment's "restore this version" saves the row with
     *   restore_revision set, and that switch skips the plugin saving event
     *   altogether. An old version went back in with no rule looked at: a key
     *   another project has taken since, a sub-issue of another project, a
     *   change to an archived project, a manager list put back by a member.
     *   Eloquent's own saving event still fires, after Exment's listener.
     * - A bell. Exment notifies creators, assignees and the users a row names
     *   without asking whether they can still open the row - see PortalNotify.
     *
     * Called from PluginPage's constructor, which Exment runs on every boot,
     * web request and artisan command alike.
     */
    public static function registerGuards(): void
    {
        if (static::$guarding) {
            return;
        }
        static::$guarding = true;

        Event::listen('eloquent.saving: *', function ($eventName, array $data) {
            $model = $data[0] ?? null;
            if ($model instanceof CustomValue && static::restoring($model)) {
                static::check($model);
            }
        });

        // A renamed project keeps answering to its old code (ISS00440).
        Event::listen('eloquent.saving: *', function ($eventName, array $data) {
            $model = $data[0] ?? null;
            if ($model instanceof CustomValue) {
                ProjectCodes::saving($model);
            }
        });

        // Someone joining or leaving a team, or a team deleted or restored,
        // changes who is on every project that names the team, without any
        // project being saved. See PortalMembership::teamsChanging().
        Event::listen(['eloquent.saving: *', 'eloquent.deleting: *', 'eloquent.restoring: *'], function ($eventName, array $data) {
            $model = $data[0] ?? null;
            if (!$model instanceof CustomValue) {
                return;
            }
            $table = $model->custom_table;
            $name = $table ? $table->table_name : null;
            if ($name === SystemTableName::USER || $name === SystemTableName::ORGANIZATION) {
                PortalMembership::teamsChanging();
            }
        });

        Event::listen('eloquent.creating: ' . NotifyNavbar::class, function ($bell) {
            return PortalNotify::allow($bell) ? null : false;
        });

        // A project key lock is let go after the save commits; a save that is
        // rolled back instead lets go here.
        Event::listen(TransactionRolledBack::class, function () {
            PortalRules::rolledBack();
        });
    }

    /**
     * Whether this save is a revision restore. Exment keeps the switch
     * protected and offers no getter.
     */
    protected static function restoring(CustomValue $model): bool
    {
        $read = function () {
            return $this->restore_revision ?? false;
        };

        return boolval(\Closure::bind($read, $model, CustomValue::class)());
    }

    /**
     * A new project starts life with the standard status and issue type lists,
     * the way Backlog does it.
     *
     * This runs on "saved" rather than "saving" because the rows hang off the
     * project's id, which does not exist yet while it is being saved.
     *
     * A failure here must not take the save down with it: the project is
     * already written by the time this runs, and an administrator can always
     * re-run the schema upgrade from the settings screen to fill the lists in.
     *
     * A project that already existed has its shares brought in line with
     * whoever the save took off it or moved, and an issue gets the listener
     * that keeps an assignee who is off the project from being handed the
     * issue back - see PortalMembership. Neither of those is caught: a revoke
     * that failed would never be retried.
     *
     * @param \Exceedone\Exment\Model\CustomValue $model
     * @return void
     */
    protected function afterSave($model): void
    {
        $def = PortalDefinition::config();
        $table_name = $model->custom_table->table_name;

        if ($table_name === array_get($def, 'project_table')) {
            PortalRules::saved();
        }

        if ($model->wasRecentlyCreated) {
            static::forget($model, $def);
        }

        if ($table_name === array_get($def, 'issue_table')) {
            PortalMembership::watchIssue($model);
            // a new issue or a new status, told to the project's chat channels (ISS02939)
            try {
                PortalChannels::issueSaved($model);
            } catch (\Throwable $e) {
                Log::warning('pm_portal channels: issue ' . $model->id . ' (' . get_class($e) . ')');
            }

            return;
        }

        if ($table_name !== array_get($def, 'project_table')) {
            return;
        }

        if (!$model->wasRecentlyCreated) {
            PortalMembership::projectSaved($model);
            // the workspace of the Files tab follows the project's people (ISS02825)
            PortalFiles::syncPermissions($model);

            return;
        }

        try {
            PortalUpgrade::seedProject($model);
        } catch (\Throwable $e) {
            Log::error('pm_portal could not seed master lists for project ' . $model->id . ': ' . $e->getMessage(), [
                'exception' => $e,
            ]);
        }
        // a new project's people get their shares now too, also the ones not in a role group yet
        // (ISS00385): Exment's share settings left them out
        try {
            PortalMembership::ensureShares($model);
        } catch (\Throwable $e) {
            Log::error('pm_portal could not share new project ' . $model->id . ' with its people: ' . $e->getMessage());
        }
    }

    /**
     * Clear what the plugin's own tables still hold for a new row's id.
     *
     * Ids come back: Exment's refreshdata and refreshtable empty the custom
     * tables and count from 1 again, while the plugin's tables keep every row.
     * A new project would otherwise show the old project's Git repository and
     * hand out tokens to it, and inherit its AI log and its watchers; a new
     * issue would inherit another issue's stars and AI history.
     *
     * @param CustomValue $model
     * @param array<string, mixed> $def
     */
    protected static function forget(CustomValue $model, array $def): void
    {
        $table_name = strval($model->custom_table->table_name);
        $id = intval($model->id);
        $key = $table_name . ':' . $id;
        if ($id <= 0 || isset(static::$forgotten[$key])) {
            return;
        }
        static::$forgotten[$key] = true;

        try {
            Star::purge($table_name, $id);
            if ($table_name === array_get($def, 'project_table')) {
                GitRepo::forgetProject($id);
                AiLog::forgetProject($id);
                ProjectCodes::forget($id);
            } elseif ($table_name === array_get($def, 'issue_table')) {
                AiLog::forgetIssue($id);
            }
        } catch (\Throwable $e) {
            Log::error('pm_portal could not clear side rows for ' . $key . ': ' . $e->getMessage(), [
                'exception' => $e,
            ]);
        }
    }
}
