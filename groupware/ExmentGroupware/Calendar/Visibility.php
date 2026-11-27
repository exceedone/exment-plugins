<?php

namespace App\Plugins\ExmentGroupware\Calendar;

/**
 * What a person sees of an event of the calendar (ISS02947), without the database. One place for
 * every way out: the calendar's pages, its API, the people side by side, the ICS export.
 *
 * - the owner and the attendees: all of it
 * - visibility "all": everyone, all of it
 * - visibility "org": the members of its organization all of it, the others 「予定あり」
 * - visibility "attendees" or "busy": the others only 「予定あり」 (the time, nothing else)
 * - an event from an outside calendar shown "busy" (its link's show_as): the others 「予定あり」
 */
class Visibility
{
    const VALUES = ['all', 'org', 'attendees', 'busy'];
    const FULL = 'full';
    const BUSY = 'busy';

    /**
     * @param array<string, mixed> $e owner_user_id, visibility, org_id, show_as (of its link)
     * @param array<int, int> $attendees the user ids of its attendees
     * @param array<int, int> $viewerOrgs the organizations of the viewer
     */
    public static function of(array $e, int $viewer, array $attendees, array $viewerOrgs): string
    {
        if ($viewer > 0 && ($viewer === intval($e['owner_user_id'] ?? 0) || in_array($viewer, array_map('intval', $attendees), true))) {
            return static::FULL;
        }
        if (strval($e['show_as'] ?? '') === 'busy') {
            return static::BUSY;
        }
        switch (strval($e['visibility'] ?? 'all')) {
            case 'all':
                return static::FULL;
            case 'org':
                return in_array(intval($e['org_id'] ?? 0), array_map('intval', $viewerOrgs), true) && intval($e['org_id'] ?? 0) > 0 ? static::FULL : static::BUSY;
            default:
                return static::BUSY;
        }
    }

    /**
     * An occurrence as the viewer may see it: the busy one has its time and nothing else (no
     * title, place, notes, attendees, rooms, link, not even its id: it cannot be opened).
     *
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    public static function shown(array $item, string $level, string $busyLabel): array
    {
        if ($level === static::FULL) {
            return $item;
        }
        return [
            'busy' => true,
            'start' => $item['start'],
            'end' => $item['end'],
            'all_day' => !empty($item['all_day']),
            'owner' => $item['owner'] ?? null,
            'title' => $busyLabel,
        ];
    }
}
