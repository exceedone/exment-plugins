<?php

namespace App\Plugins\ExmentPluginPM;

use Exceedone\Exment\Services\Plugin\PluginValidatorBase;

/**
 * Runs PortalRules on every write to a project or an issue.
 *
 * Exment calls a validator plugin from CustomTable::validateValue, which sits
 * on the one path shared by the admin form, a CSV import, the REST API and
 * this plugin's own lightapi. Putting the rules here rather than in a
 * controller is what stops the admin grid being a way around them.
 */
class PluginValidator extends PluginValidatorBase
{
    /**
     * @return bool false when something is wrong, which makes Exment read messages()
     */
    public function validate()
    {
        if (!isset($this->custom_table)) {
            return true;
        }

        // A move to another project is checked by the saving event, against
        // the new project - here the model still names the old one.
        if (PortalRules::moveRequested($this->custom_table, $this->original_value)) {
            return true;
        }

        $input = is_array($this->input_value) ? $this->input_value : [];
        $this->messages = PortalRules::validate($this->custom_table, $input, $this->original_value);

        return empty($this->messages);
    }

    /**
     * @param mixed $model
     * @return array<string, mixed>|true
     */
    public function validateDestroy($model)
    {
        $reason = PortalRules::validateDestroy($model);
        if ($reason === null) {
            return true;
        }

        return ['status' => false, 'message' => $reason];
    }
}
