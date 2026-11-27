<?php

namespace App\Plugins\ExmentGroupware;

/**
 * A secret of the plugin's settings (the calendar's OAuth client secrets, ISS02947 part 6):
 * saved encrypted, as Exment's encpassword, but never written back into the page. Exment's
 * EncPassword decrypts the saved value into the input (formatValue); this one leaves the input
 * empty, and an empty input keeps the secret saved (EncPassword::prepare returns the original).
 */
class SettingSecretField extends \Exceedone\Exment\Form\Field\EncPassword
{
    /** The input stays empty, whatever is saved. */
    protected function formatValue()
    {
        $this->value = '';
    }
}
