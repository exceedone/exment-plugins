<?php

namespace App\Plugins\ExmentITSupport;

use Exceedone\Exment\Enums\PluginEventTrigger;
use Exceedone\Exment\Services\Plugin\PluginEventBase;
use Illuminate\Support\Facades\Log;

/**
 * The ticket rules on every save, whoever saves.
 *
 * Exment's own data screen, its import, its REST API and this plugin's
 * screens all go through CustomValue::save, so putting the rules here keeps
 * a ticket edited anywhere consistent: the assignee, the SLA due times and
 * state, the moments a status was reached, the clock stopping while the
 * customer is asked.
 *
 * Target table: the plugin row's options.target_tables is set to the ticket
 * table by the setup (Exment calls an event plugin only for tables listed
 * there).
 */
class PluginEvent extends PluginEventBase
{
    /** @var array<int, string> status before the save, by model object */
    protected static $before = [];

    /** @var array<int, array{new: bool, stage: string}> for the chat notices (ISS02941) */
    protected static $chat = [];

    public function execute()
    {
        if (!Feature::on(Feature::DESK)) {
            return true;
        }
        $model = $this->custom_value;
        if (!isset($model) || !isset($model->custom_table) || $model->custom_table->table_name !== Its::T_TICKET) {
            return true;
        }

        $event = optional($this->pluginOptions)->event_type;
        try {
            if ($event === PluginEventTrigger::SAVING || $event === null) {
                // After the save the model's original is the new value, so
                // the status it had is noted here for afterSave().
                $original = $model->getOriginal('value');
                $original = is_string($original) ? (array) json_decode($original, true) : (array) $original;
                static::$before[spl_object_id($model)] = $model->exists ? strval($original['status'] ?? '') : '';
                static::$chat[spl_object_id($model)] = ['new' => !$model->exists, 'stage' => strval($original['stage'] ?? '')];
                Tickets::applyRules($model);
            } elseif ($event === PluginEventTrigger::SAVED) {
                $this->afterSave($model);
            }
        } catch (\Throwable $e) {
            // A rule must never stop a person from saving a ticket.
            Log::error('ExmentITSupport rules failed on ticket ' . $model->id . ': ' . $e->getMessage(), ['exception' => $e]);
        }

        return true;
    }

    protected function afterSave($model): void
    {
        $key = spl_object_id($model);
        if (!array_key_exists($key, static::$before)) {
            return;
        }
        $before = static::$before[$key];
        unset(static::$before[$key]);
        $chat = static::$chat[$key] ?? null;
        unset(static::$chat[$key]);
        if (isset($chat) && ChatNotices::enabled()) {
            $stage = strval(array_get($model->value, 'stage'));
            if ($chat['new']) {
                ChatNotices::note($model, 'new', ['from' => strval(array_get($model->value, 'requester_name') ?: array_get($model->value, 'requester_email'))]);
            } elseif ($stage !== '' && $stage !== $chat['stage'] && !ChatNotices::customerWriting()) {
                ChatNotices::note($model, 'stage', ['from' => $chat['stage'], 'to' => $stage, 'progress' => intval(array_get($model->value, 'progress'))]);
            }
        }
        $status = strval(array_get($model->value, 'status'));
        if ($status === $before) {
            return;
        }
        if ($status === 'closed' || $before === 'closed') {
            Tokens::expireClosed(intval($model->id), $status === 'closed');
        }
        if ($status === 'resolved' && $before !== 'resolved') {
            Automation::askForRating($model);
        }
    }
}
