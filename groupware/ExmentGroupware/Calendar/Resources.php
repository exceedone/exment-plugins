<?php

namespace App\Plugins\ExmentGroupware\Calendar;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The calendar's rooms and equipment (ISS02947, part 3). The system administrators keep the list;
 * anyone who makes an event may book what is on it. Two events that book one room at overlapping
 * times clash: the calendar warns (the maker confirms), or refuses when the site says so (the
 * plugin's setting 「会議室・備品が重なったとき」).
 */
class Resources
{
    const TABLE = 'gw_cal_resources';
    const LINKS = 'gw_cal_event_resources';
    const KINDS = ['room', 'equipment'];
    const MAX_PER_EVENT = 10;
    /** How far a repeating event is checked for clashes. */
    const HORIZON_DAYS = 366;
    const MAX_CLASHES = 20;

    public static function ready(): bool
    {
        try {
            return \Schema::hasTable(static::TABLE) && \Schema::hasTable(static::LINKS);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** @return array<int, array<string, mixed>> */
    public static function all(bool $withDisabled = false): array
    {
        if (!static::ready()) {
            return [];
        }
        $q = DB::table(static::TABLE)->orderBy('sort')->orderBy('id');
        if (!$withDisabled) {
            $q->where('enabled', 1);
        }
        return $q->get()->map(function ($r) {
            return ['id' => intval($r->id), 'name' => strval($r->name), 'kind' => strval($r->kind), 'capacity' => $r->capacity === null ? null : intval($r->capacity), 'note' => strval($r->note ?? ''), 'enabled' => boolval($r->enabled)];
        })->all();
    }

    /**
     * Add or change one (a system administrator).
     *
     * @param array<string, mixed> $in
     */
    public static function save(?int $id, array $in): ?string
    {
        $name = trim(mb_substr(strval($in['name'] ?? ''), 0, 100));
        if ($name === '') {
            return 'res_name';
        }
        $kind = strval($in['kind'] ?? 'room');
        if (!in_array($kind, static::KINDS, true)) {
            return 'res_kind';
        }
        $cap = $in['capacity'] ?? null;
        if ($cap !== null && $cap !== '' && (!ctype_digit(strval($cap)) || intval($cap) > 100000)) {
            return 'res_capacity';
        }
        $values = [
            'name' => $name, 'kind' => $kind,
            'capacity' => ($cap === null || $cap === '') ? null : intval($cap),
            'note' => trim(mb_substr(strval($in['note'] ?? ''), 0, 500)) ?: null,
            'enabled' => !array_key_exists('enabled', $in) || !empty($in['enabled']) ? 1 : 0,
            'updated_at' => now(),
        ];
        if ($id === null) {
            $values['sort'] = 1 + intval(DB::table(static::TABLE)->max('sort'));
            DB::table(static::TABLE)->insert($values + ['created_at' => now()]);
        } elseif (DB::table(static::TABLE)->where('id', $id)->update($values) === 0) {
            return 'res_not_found';
        }
        Log::info('gw_calendar: resource ' . ($id ?? 'new') . ' saved');
        return null;
    }

    /**
     * The rooms of the form, checked: ones on the list and in use (a room taken off the list stays
     * on the events that had it).
     *
     * @param mixed $ids
     * @param array<int, int> $kept the ones the event has now
     * @return array<int, int>|null null: one that may not be booked
     */
    public static function checked($ids, array $kept = []): ?array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) $ids))));
        if (count($ids) > static::MAX_PER_EVENT) {
            return null;
        }
        if (empty($ids)) {
            return [];
        }
        $usable = DB::table(static::TABLE)->whereIn('id', $ids)->where('enabled', 1)->pluck('id')->map(function ($v) {
            return intval($v);
        })->all();
        foreach ($ids as $id) {
            if (!in_array($id, $usable, true) && !in_array($id, $kept, true)) {
                return null;
            }
        }
        return $ids;
    }

    /** @return array<int, int> the resources of an event */
    public static function of(int $eventId): array
    {
        if (!static::ready()) {
            return [];
        }
        return DB::table(static::LINKS)->where('event_id', $eventId)->pluck('resource_id')->map(function ($v) {
            return intval($v);
        })->all();
    }

    /** @param array<int, int> $ids */
    public static function set(int $eventId, array $ids): void
    {
        DB::table(static::LINKS)->where('event_id', $eventId)->whereNotIn('resource_id', $ids ?: [0])->delete();
        foreach ($ids as $r) {
            DB::table(static::LINKS)->insertOrIgnore(['event_id' => $eventId, 'resource_id' => $r, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    /**
     * The clashes of two lists of occurrences (no database: the CI checks it): each pair that
     * overlaps (an end at the other's start is not a clash).
     *
     * @param array<int, array{start: string, end: string}> $mine
     * @param array<int, array{start: string, end: string}> $theirs
     * @return array<int, array{start: string, end: string}> the times of mine that clash
     */
    public static function overlaps(array $mine, array $theirs): array
    {
        $out = [];
        foreach ($mine as $a) {
            foreach ($theirs as $b) {
                if (strcmp($a['start'], $b['end']) < 0 && strcmp($b['start'], $a['end']) < 0) {
                    $out[] = ['start' => $a['start'], 'end' => $a['end']];
                    break;
                }
            }
            if (count($out) >= static::MAX_CLASHES) {
                break;
            }
        }
        return $out;
    }

    /**
     * The clashes of an event (its fields, not saved yet) with the other events that book its
     * rooms: per room, the times. Only times and the room are said, never the other event's title
     * (the maker may not see it).
     *
     * @param array<string, mixed> $f from Events::fields()
     * @param array<int, int> $rooms
     * @return array<int, array{resource: int, name: string, times: array<int, array{start: string, end: string}>}>
     */
    public static function clashes(?int $eventId, array $f, array $rooms): array
    {
        if (empty($rooms) || !static::ready()) {
            return [];
        }
        $utc = new \DateTimeZone('UTC');
        $start = new \DateTimeImmutable(strval($f['starts_at']), $utc);
        if (empty($f['rrule'])) {
            $from = $start->format('Y-m-d H:i:s');
            $to = strval($f['ends_at']);
        } else {
            // a repeating one: from today (its past times are past), for a year
            $today = new \DateTimeImmutable('today', $utc);
            $begin = $start > $today ? $start : $today;
            $from = $begin->format('Y-m-d H:i:s');
            $to = $begin->modify('+' . static::HORIZON_DAYS . ' day')->format('Y-m-d H:i:s');
        }
        $mine = Occurrences::between($f + ['exdates' => $f['exdates'] ?? null], $from, $to, $eventId !== null ? static::overridesOf($eventId) : []);
        if (empty($mine)) {
            return [];
        }
        $names = [];
        foreach (DB::table(static::TABLE)->whereIn('id', $rooms)->get() as $r) {
            $names[intval($r->id)] = strval($r->name);
        }
        $out = [];
        foreach ($rooms as $room) {
            $others = DB::table(static::LINKS)->where('resource_id', $room)->when($eventId !== null, function ($q) use ($eventId) {
                $q->where('event_id', '<>', $eventId);
            })->pluck('event_id')->all();
            if (empty($others)) {
                continue;
            }
            $theirs = [];
            foreach (DB::table(Events::EVENTS)->whereNull('deleted_at')->whereIn('id', $others)->get() as $row) {
                $e = (array) $row;
                foreach (Occurrences::between($e, $from, $to, static::overridesOf(intval($e['id']))) as $occ) {
                    $theirs[] = $occ;
                }
            }
            $times = static::overlaps($mine, $theirs);
            if (!empty($times)) {
                $out[] = ['resource' => $room, 'name' => $names[$room] ?? '#' . $room, 'times' => $times];
            }
        }
        return $out;
    }

    /** @return array<int, array<string, mixed>> */
    protected static function overridesOf(int $eventId): array
    {
        return DB::table(Events::OVERRIDES)->where('event_id', $eventId)->get()->map(function ($o) {
            return (array) $o;
        })->all();
    }
}
