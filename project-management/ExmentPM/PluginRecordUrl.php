<?php

namespace App\Plugins\ExmentPM;

use Exceedone\Exment\Model\CustomValue;

/**
 * Where a record of the portal's tables opens, for Exment.
 *
 * Exment asks this when a notification is opened, and for ${value_url} in a
 * mail (Exment 7.2.1 and later, Services\RecordUrl): an issue opens at its
 * project's page with #issue-ISS00239, a wiki page with #wiki-<id>, a project
 * at its page. Any other record answers null and opens Exment's data page.
 * Exment before 7.2.1 asks PortalDefinition::portalRecordUrl() itself, from
 * the bell only.
 */
class PluginRecordUrl
{
    public static function recordUrl(CustomValue $custom_value): ?string
    {
        return PortalDefinition::portalRecordUrl($custom_value);
    }
}
