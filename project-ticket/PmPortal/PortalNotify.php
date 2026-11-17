<?php

namespace App\Plugins\PmPortal;

use Exceedone\Exment\Model\CustomTable;
use Exceedone\Exment\Model\CustomValue;
use Exceedone\Exment\Model\NotifyNavbar;
use Illuminate\Support\Facades\Log;

/**
 * Bells about portal rows go only to people who can open the row, and say
 * nothing the dashboard could take for markup.
 *
 * Exment notifies the creator, the assignees and every user a row names, on
 * every change and every comment, without asking whether they can still open
 * the row. Someone taken off a project went on receiving the subject of every
 * issue they had once opened or been given, and on a comment the comment
 * itself. The dashboard's notification box prints the subject as HTML, so a
 * title was also a way to put markup on a colleague's screen.
 *
 * Hooked onto NotifyNavbar's creating event (PluginEvent::registerGuards), so
 * it holds for Exment's own bells and the portal's alike, and for a bell that
 * a queue worker writes later. E-mails go out from Exment's notify service
 * directly and cannot be held back from a plugin; only the bell can.
 */
class PortalNotify
{
    /**
     * Rows already looked up in this request, by "table:id" (see rowOf).
     *
     * @var array<string, CustomValue|null>
     */
    protected static $rowCache = [];

    /**
     * Whether a bell may be written, its subject made safe on the way.
     *
     * Fails closed: a bell that cannot be checked is not written. A missing
     * bell costs a click; a bell to the wrong person cannot be taken back.
     */
    public static function allow(NotifyNavbar $bell): bool
    {
        try {
            $row = static::rowOf($bell);
            if ($row === null) {
                return true;
            }

            $bell->notify_subject = PortalDefinition::notifySubject(strval($bell->notify_subject));

            return in_array(intval($bell->target_user_id), PortalMembership::readers($row), true);
        } catch (\Throwable $e) {
            Log::error('pm_portal bell check failed: ' . $e->getMessage(), ['exception' => $e]);

            return false;
        }
    }

    /**
     * The portal row a bell is about, or null for a bell about anything else.
     *
     * Looked up whoever is asking: a bell is written in the name of whoever
     * triggered it, and what counts is who receives it.
     */
    protected static function rowOf(NotifyNavbar $bell): ?CustomValue
    {
        $type = strval($bell->parent_type);
        $id = $bell->parent_id;
        if ($type === '' || !is_numeric($id) || !in_array($type, static::portalTables(), true)) {
            return null;
        }

        // ISS00257: Exment writes one bell per recipient, so a save that
        // notifies sixteen people asked for the same row sixteen times.
        $key = $type . ':' . intval($id);
        if (!array_key_exists($key, static::$rowCache)) {
            $table = CustomTable::getEloquent($type);
            static::$rowCache[$key] = $table ? $table->getValueModel()->newQueryWithoutScopes()->find(intval($id)) : null;
        }
        $row = static::$rowCache[$key];

        return $row instanceof CustomValue ? $row : null;
    }

    /**
     * Every table the portal is built from.
     *
     * @return array<int, string>
     */
    protected static function portalTables(): array
    {
        $names = [];
        foreach (PortalDefinition::config() as $key => $value) {
            if (is_string($value) && $value !== '' && substr($key, -6) === '_table') {
                $names[] = $value;
            }
        }

        return $names;
    }
}
