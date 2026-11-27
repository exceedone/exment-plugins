<?php

namespace App\Plugins\ExmentGroupware\Calendar;

use Exceedone\Exment\Enums\SystemTableName;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The events of the calendar (ISS02947): read in a window of time for some people, made, changed
 * (all of a repeat, or one occurrence), cancelled, and answered by the attendees. What a person
 * sees of each is Visibility's, here and nowhere else.
 */
class Events
{
    const EVENTS = 'gw_cal_events';
    const OVERRIDES = 'gw_cal_overrides';
    const ATTENDEES = 'gw_cal_attendees';
    const RESPONSES = ['none', 'yes', 'no', 'maybe'];
    const MAX_ATTENDEES = 100;
    const MAX_WINDOW_DAYS = 400;
    const MAX_PEOPLE = 50;

    public static function ready(): bool
    {
        try {
            return \Schema::hasTable(static::EVENTS) && \Schema::hasTable(static::ATTENDEES);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** @return array<int, int> the organizations a user belongs to */
    public static function orgsOf(int $userId): array
    {
        try {
            $user = getModelName(SystemTableName::USER)::withoutGlobalScopes()->find($userId);
            if (!isset($user)) {
                return [];
            }
            // the loaded collection: pluck('id') on the relation's query is ambiguous with its join (MySQL 1052)
            return array_values(array_map(function ($v) {
                return intval($v);
            }, $user->belong_organizations->pluck('id')->all()));
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * The occurrences of the events of some people (theirs, or ones they attend) from $from to
     * $to, as the viewer may see them.
     *
     * @param array<int, int> $people
     * @return array<int, array<string, mixed>>
     */
    public static function window(string $from, string $to, array $people, int $viewer, string $busyLabel): array
    {
        $people = array_slice(array_values(array_unique(array_filter(array_map('intval', $people)))), 0, static::MAX_PEOPLE);
        if (empty($people)) {
            return [];
        }
        $attending = DB::table(static::ATTENDEES)->whereIn('user_id', $people)->pluck('event_id')->all();
        $rows = DB::table(static::EVENTS)->whereNull('deleted_at')
            ->where('starts_at', '<', $to)
            ->where(function ($q) use ($people, $attending) {
                $q->whereIn('owner_user_id', $people);
                if (!empty($attending)) {
                    $q->orWhereIn('id', $attending);
                }
            })
            ->where(function ($q) use ($from) {
                // once: still on at $from; a repeat: not over before $from
                $q->where(function ($once) use ($from) {
                    $once->whereNull('rrule')->where('ends_at', '>', $from);
                })->orWhere(function ($rep) use ($from) {
                    $rep->whereNotNull('rrule')->where(function ($u) use ($from) {
                        $u->whereNull('repeat_until')->orWhere('repeat_until', '>=', $from);
                    });
                })->orWhereNotNull('rdates');
            })
            ->orderBy('starts_at')->limit(5000)->get();
        if ($rows->isEmpty()) {
            return [];
        }
        $ids = $rows->pluck('id')->all();
        $attendees = [];
        foreach (DB::table(static::ATTENDEES)->whereIn('event_id', $ids)->get() as $a) {
            $attendees[intval($a->event_id)][intval($a->user_id)] = strval($a->response);
        }
        $overrides = [];
        foreach (DB::table(static::OVERRIDES)->whereIn('event_id', $ids)->get() as $o) {
            $overrides[intval($o->event_id)][] = (array) $o;
        }
        $viewerOrgs = static::orgsOf($viewer);
        $out = [];
        foreach ($rows as $row) {
            $e = (array) $row;
            $eid = intval($e['id']);
            $att = $attendees[$eid] ?? [];
            $level = Visibility::of($e, $viewer, array_keys($att), $viewerOrgs);
            foreach (Occurrences::between($e, $from, $to, $overrides[$eid] ?? []) as $occ) {
                $item = [
                    'id' => $eid,
                    'start' => $occ['start'],
                    'end' => $occ['end'],
                    'original' => $occ['original'],
                    'all_day' => boolval($e['all_day']),
                    'title' => $occ['title'] ?? strval($e['title']),
                    'location' => $occ['location'] ?? strval($e['location'] ?? ''),
                    'owner' => intval($e['owner_user_id']),
                    'attendees' => array_keys($att),
                    'mine' => $att[$viewer] ?? null,
                    'repeat' => !empty($e['rrule']) || !empty($e['rdates']),
                    'changed' => $occ['changed'],
                    'visibility' => strval($e['visibility']),
                    'editable' => $viewer > 0 && $viewer === intval($e['owner_user_id']) && strval($e['source']) === 'local',
                ];
                $out[] = Visibility::shown($item, $level, $busyLabel);
            }
        }
        usort($out, function ($a, $b) {
            return strcmp($a['start'], $b['start']);
        });
        return $out;
    }

    /**
     * One event in full, for the viewer who may see it; null when they may not (or it is not there).
     *
     * @return array<string, mixed>|null
     */
    public static function get(int $id, int $viewer): ?array
    {
        $row = DB::table(static::EVENTS)->whereNull('deleted_at')->where('id', $id)->first();
        if (!isset($row)) {
            return null;
        }
        $e = (array) $row;
        $att = [];
        foreach (DB::table(static::ATTENDEES)->where('event_id', $id)->orderBy('id')->get() as $a) {
            $att[] = ['user' => intval($a->user_id), 'response' => strval($a->response)];
        }
        if (Visibility::of($e, $viewer, array_column($att, 'user'), static::orgsOf($viewer)) !== Visibility::FULL) {
            return null;
        }
        return [
            'id' => $id,
            'title' => strval($e['title']),
            'location' => strval($e['location'] ?? ''),
            'body' => strval($e['body'] ?? ''),
            'starts_at' => strval($e['starts_at']),
            'ends_at' => strval($e['ends_at']),
            'all_day' => boolval($e['all_day']),
            'tz' => strval($e['tz']),
            'rrule' => strval($e['rrule'] ?? ''),
            'exdates' => Occurrences::days($e['exdates'] ?? null),
            'visibility' => strval($e['visibility']),
            'org_id' => isset($e['org_id']) ? intval($e['org_id']) : null,
            'owner' => intval($e['owner_user_id']),
            'attendees' => $att,
            'editable' => $viewer === intval($e['owner_user_id']) && strval($e['source']) === 'local',
            'reminder' => Reminders::ready() ? DB::table(Reminders::TABLE)->where('event_id', $id)->min('minutes_before') : null,
        ];
    }

    /**
     * An event's fields from a form, checked (no database: the CI checks it).
     *
     * @param array<string, mixed> $in
     * @param array<int, int> $viewerOrgs
     * @return array{0: array<string, mixed>|null, 1: string|null} the fields, or the rule broken
     */
    public static function fields(array $in, array $viewerOrgs): array
    {
        $title = trim(mb_substr(strval($in['title'] ?? ''), 0, 200));
        if ($title === '') {
            return [null, 'title'];
        }
        $tz = strval($in['tz'] ?? '') ?: 'Asia/Tokyo';
        try {
            new \DateTimeZone($tz);
        } catch (\Throwable $e) {
            return [null, 'tz'];
        }
        $allDay = !empty($in['all_day']);
        $utc = new \DateTimeZone('UTC');
        try {
            if ($allDay) {
                $s = Rrule::day($in['start'] ?? null);
                $last = Rrule::day($in['end'] ?? null) ?? $s;
                if ($s === null || $last === null || strcmp($last, $s) < 0) {
                    return [null, 'time'];
                }
                $starts = $s . ' 00:00:00';
                $ends = (new \DateTimeImmutable($last, $utc))->modify('+1 day')->format('Y-m-d') . ' 00:00:00';
            } else {
                // local times of the event's time zone ("2026-12-01T10:00")
                $s = new \DateTimeImmutable(strval($in['start'] ?? ''), new \DateTimeZone($tz));
                $e = new \DateTimeImmutable(strval($in['end'] ?? ''), new \DateTimeZone($tz));
                if ($e < $s) {
                    return [null, 'time'];
                }
                $starts = $s->setTimezone($utc)->format('Y-m-d H:i:s');
                $ends = $e->setTimezone($utc)->format('Y-m-d H:i:s');
            }
        } catch (\Throwable $ex) {
            return [null, 'time'];
        }
        $rrule = strtoupper(trim(preg_replace('/^RRULE:/i', '', strval($in['rrule'] ?? '')) ?? ''));
        if ($rrule !== '' && (Rrule::parse($rrule) === null || strlen($rrule) > 255)) {
            return [null, 'rrule'];
        }
        $visibility = strval($in['visibility'] ?? 'all');
        if (!in_array($visibility, Visibility::VALUES, true)) {
            return [null, 'visibility'];
        }
        $org = intval($in['org_id'] ?? 0);
        if ($visibility === 'org' && !in_array($org, $viewerOrgs, true)) {
            return [null, 'org'];
        }
        $attendees = array_values(array_unique(array_filter(array_map('intval', (array) ($in['attendees'] ?? [])))));
        if (count($attendees) > static::MAX_ATTENDEES) {
            return [null, 'attendees'];
        }
        // the reminder (part 2): one of the choices, or none
        $reminder = $in['reminder'] ?? '';
        if ($reminder !== '' && $reminder !== null && !in_array(intval($reminder), Reminders::CHOICES, true)) {
            return [null, 'reminder'];
        }

        return [[
            'title' => $title,
            'location' => trim(mb_substr(strval($in['location'] ?? ''), 0, 200)) ?: null,
            'body' => mb_substr(strval($in['body'] ?? ''), 0, 20000) ?: null,
            'starts_at' => $starts,
            'ends_at' => $ends,
            'all_day' => $allDay ? 1 : 0,
            'tz' => $tz,
            'rrule' => $rrule !== '' ? $rrule : null,
            'repeat_until' => $rrule !== '' ? static::repeatUntil($rrule, $starts, $tz, $allDay) : null,
            'exdates' => !empty($in['exdates']) ? json_encode(Occurrences::days($in['exdates'])) : null,
            'visibility' => $visibility,
            'org_id' => $visibility === 'org' ? $org : null,
            'attendees' => $attendees,
            'reminders' => ($reminder === '' || $reminder === null) ? [] : [intval($reminder)],
        ], null];
    }

    /** The last moment a repeat reaches (from UNTIL or COUNT), or null when it never ends. */
    public static function repeatUntil(string $rrule, string $startsUtc, string $tz, bool $allDay): ?string
    {
        $r = Rrule::parse($rrule);
        if ($r === null || ($r['UNTIL'] === null && $r['COUNT'] === null)) {
            return null;
        }
        $localStart = $allDay ? substr($startsUtc, 0, 10)
            : (new \DateTimeImmutable($startsUtc, new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone($tz))->format('Y-m-d');
        $far = (new \DateTimeImmutable($localStart))->modify('+100 year')->format('Y-m-d');
        $dates = Rrule::between($rrule, $localStart, $localStart, $r['UNTIL'] ?? $far) ?? [];
        $last = empty($dates) ? $localStart : end($dates);
        // the whole of its last day (and a day more for the time zones and the length)
        return (new \DateTimeImmutable($last))->modify('+2 day')->format('Y-m-d') . ' 00:00:00';
    }

    /**
     * Make an event, or change all of it (the owner only). The attendees that come out of the
     * form; the ones kept keep their replies.
     *
     * @param array<string, mixed> $f from fields()
     */
    public static function save(?int $id, array $f, int $viewer): ?int
    {
        $attendees = $f['attendees'];
        $reminders = $f['reminders'] ?? null;
        unset($f['attendees'], $f['reminders']);
        $users = getModelName(SystemTableName::USER)::withoutGlobalScopes()->whereIn('id', $attendees)->pluck('id')->map(function ($v) {
            return intval($v);
        })->all();
        return DB::transaction(function () use ($id, $f, $viewer, $users, $reminders) {
            if ($id === null) {
                $id = intval(DB::table(static::EVENTS)->insertGetId($f + [
                    'owner_user_id' => $viewer, 'source' => 'local', 'created_user_id' => $viewer, 'updated_user_id' => $viewer,
                    'created_at' => now(), 'updated_at' => now(),
                ]));
            } else {
                $n = DB::table(static::EVENTS)->whereNull('deleted_at')->where('id', $id)->where('owner_user_id', $viewer)->where('source', 'local')
                    ->update($f + ['updated_user_id' => $viewer, 'updated_at' => now()]);
                if ($n === 0) {
                    return null;
                }
            }
            DB::table(static::ATTENDEES)->where('event_id', $id)->whereNotIn('user_id', $users ?: [0])->delete();
            foreach ($users as $u) {
                DB::table(static::ATTENDEES)->insertOrIgnore(['event_id' => $id, 'user_id' => $u, 'response' => $u === $viewer ? 'yes' : 'none', 'created_at' => now(), 'updated_at' => now()]);
            }
            if ($reminders !== null && Reminders::ready()) {
                DB::table(Reminders::TABLE)->where('event_id', $id)->delete();
                foreach ($reminders as $m) {
                    DB::table(Reminders::TABLE)->insert(['event_id' => $id, 'minutes_before' => $m, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
            Log::info('gw_calendar: event ' . $id . ' saved by user ' . $viewer);
            return $id;
        });
    }

    /**
     * One occurrence of a repeat: changed (its times, title, place) or cancelled. The owner only.
     *
     * @param array<string, mixed>|null $change starts_at, ends_at (UTC), title, location; null: cancelled
     */
    public static function override(int $id, string $originalUtc, ?array $change, int $viewer): bool
    {
        $event = DB::table(static::EVENTS)->whereNull('deleted_at')->where('id', $id)->where('owner_user_id', $viewer)->where('source', 'local')->first();
        if (!isset($event)) {
            return false;
        }
        // the title and the place of the occurrence only when they are its own: else it follows
        // the event (a new title of all the occurrences reaches a moved one too)
        if ($change !== null) {
            if (strval($change['title'] ?? '') === strval($event->title)) {
                $change['title'] = null;
            }
            if (strval($change['location'] ?? '') === strval($event->location ?? '')) {
                $change['location'] = null;
            }
        }
        DB::table(static::OVERRIDES)->updateOrInsert(['event_id' => $id, 'original_start' => $originalUtc], [
            'cancelled' => $change === null ? 1 : 0,
            'starts_at' => $change['starts_at'] ?? null,
            'ends_at' => $change['ends_at'] ?? null,
            'title' => $change['title'] ?? null,
            'location' => $change['location'] ?? null,
            'updated_at' => now(),
        ]);
        return true;
    }

    /** Delete an event (all of it). The owner only. */
    public static function delete(int $id, int $viewer): bool
    {
        return DB::table(static::EVENTS)->whereNull('deleted_at')->where('id', $id)->where('owner_user_id', $viewer)
            ->update(['deleted_at' => now(), 'updated_user_id' => $viewer]) > 0;
    }

    /** An attendee's reply (for the whole event). */
    public static function reply(int $id, int $viewer, string $response): bool
    {
        if (!in_array($response, static::RESPONSES, true)) {
            return false;
        }
        return DB::table(static::ATTENDEES)->where('event_id', $id)->where('user_id', $viewer)
            ->update(['response' => $response, 'responded_at' => now(), 'updated_at' => now()]) > 0;
    }
}
