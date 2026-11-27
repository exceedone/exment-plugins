<?php

namespace App\Plugins\ExmentSupportDesk;

use Carbon\Carbon;
use Exceedone\Exment\Model\CustomValue;

/**
 * The SLA clock.
 *
 * A due time is "start + N minutes of business time": weekdays from the
 * settings, between the opening and closing time, skipping the holiday list.
 * A policy with business_hours_only off counts every minute instead.
 *
 * While a ticket waits for the customer the clock stops (Its::PAUSED_STATUSES):
 * waiting for an answer is not the support desk being slow, and every tool on
 * the market that ignores this ends up with SLA reports nobody believes.
 */
class Sla
{
    /** Share of the time left under which a ticket is "close to the limit". */
    const WARN_RATIO = 0.25;

    /** Never call a ticket close to the limit earlier than this, in minutes. */
    const WARN_MIN_MINUTES = 30;

    /**
     * @return array{days: array<int, int>, start: int, end: int, holidays: array<string, bool>}
     */
    public static function calendar(): array
    {
        $days = array_values(array_filter(array_map('intval', explode(',', strval(Its::setting('business_days', '1,2,3,4,5'))))));
        $start = static::minutesOf(strval(Its::setting('business_start', '09:00')), 9 * 60);
        $end = static::minutesOf(strval(Its::setting('business_end', '18:00')), 18 * 60);
        if ($end <= $start) {
            $end = $start + 60;
        }
        $holidays = [];
        foreach (preg_split('/[\s,]+/', strval(Its::setting('holidays', ''))) as $d) {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
                $holidays[$d] = true;
            }
        }

        return ['days' => empty($days) ? [1, 2, 3, 4, 5] : $days, 'start' => $start, 'end' => $end, 'holidays' => $holidays];
    }

    protected static function minutesOf(string $hhmm, int $default): int
    {
        if (!preg_match('/^(\d{1,2}):(\d{2})$/', trim($hhmm), $m)) {
            return $default;
        }

        return min(24 * 60, intval($m[1]) * 60 + intval($m[2]));
    }

    public static function isBusinessDay(Carbon $day, array $cal): bool
    {
        return in_array(intval($day->format('N')), $cal['days'], true) && !isset($cal['holidays'][$day->format('Y-m-d')]);
    }

    /**
     * Start + $minutes, counted in business time when $businessOnly.
     */
    public static function addMinutes(Carbon $start, int $minutes, bool $businessOnly, ?array $cal = null): Carbon
    {
        if (!$businessOnly) {
            return $start->copy()->addMinutes($minutes);
        }
        $cal = $cal ?? static::calendar();
        $t = $start->copy()->second(0);
        $left = max(0, $minutes);

        // 3 years of days is far more than any SLA; the cap keeps a broken
        // calendar (no business day at all) from looping for ever.
        for ($guard = 0; $guard < 1200; $guard++) {
            if (!static::isBusinessDay($t, $cal)) {
                $t = $t->copy()->addDay()->startOfDay()->addMinutes($cal['start']);
                continue;
            }
            $now = $t->hour * 60 + $t->minute;
            if ($now < $cal['start']) {
                $t = $t->copy()->startOfDay()->addMinutes($cal['start']);
                $now = $cal['start'];
            }
            if ($now >= $cal['end']) {
                $t = $t->copy()->addDay()->startOfDay()->addMinutes($cal['start']);
                continue;
            }
            $room = $cal['end'] - $now;
            if ($left <= $room) {
                return $t->copy()->addMinutes($left);
            }
            $left -= $room;
            $t = $t->copy()->addDay()->startOfDay()->addMinutes($cal['start']);
        }

        return $start->copy()->addMinutes($minutes);
    }

    /**
     * Business minutes between two moments (for pausing the clock).
     */
    public static function minutesBetween(Carbon $from, Carbon $to, bool $businessOnly, ?array $cal = null): int
    {
        if ($to->lessThanOrEqualTo($from)) {
            return 0;
        }
        if (!$businessOnly) {
            return intval($from->diffInMinutes($to));
        }
        $cal = $cal ?? static::calendar();
        $total = 0;
        $day = $from->copy()->startOfDay();
        for ($guard = 0; $guard < 1200 && $day->lessThanOrEqualTo($to); $guard++) {
            if (static::isBusinessDay($day, $cal)) {
                $open = $day->copy()->addMinutes($cal['start']);
                $close = $day->copy()->addMinutes($cal['end']);
                $a = $from->greaterThan($open) ? $from : $open;
                $b = $to->lessThan($close) ? $to : $close;
                if ($b->greaterThan($a)) {
                    $total += intval($a->diffInMinutes($b));
                }
            }
            $day = $day->copy()->addDay();
        }

        return $total;
    }

    /**
     * The policy for a ticket: the customer's, else the one marked default.
     */
    public static function policyFor(CustomValue $ticket): ?CustomValue
    {
        $customerId = array_get($ticket->value, 'customer');
        if (!empty($customerId) && ($customers = Its::table(Its::T_CUSTOMER)) !== null) {
            $customer = $customers->getValueModel()->query()->withoutGlobalScope(\Exceedone\Exment\Model\CustomValueModelScope::class)->find($customerId);
            $slaId = isset($customer) ? array_get($customer->value, 'sla') : null;
            if (!empty($slaId) && ($slas = Its::table(Its::T_SLA)) !== null) {
                $sla = $slas->getValueModel()->query()->withoutGlobalScope(\Exceedone\Exment\Model\CustomValueModelScope::class)->find($slaId);
                if (isset($sla)) {
                    return $sla;
                }
            }
        }

        $slas = Its::table(Its::T_SLA);
        if (!isset($slas)) {
            return null;
        }
        // the box's own SLA (ISS02941), before the default
        $boxSla = Tickets::boxOf(array_get($ticket->value, 'category'))['sla'];
        if (!empty($boxSla) && ctype_digit(strval($boxSla))) {
            $sla = $slas->getValueModel()->query()->withoutGlobalScope(\Exceedone\Exment\Model\CustomValueModelScope::class)->find(intval($boxSla));
            if (isset($sla)) {
                return $sla;
            }
        }

        return $slas->getValueModel()->query()->withoutGlobalScope(\Exceedone\Exment\Model\CustomValueModelScope::class)->where('value->is_default', '1')->first();
    }

    /**
     * Set the two due times on a ticket that has none yet.
     */
    public static function applyDue(CustomValue $ticket, ?Carbon $from = null): void
    {
        $policy = static::policyFor($ticket);
        if (!isset($policy)) {
            return;
        }
        $from = $from ?? ($ticket->created_at ? Carbon::parse($ticket->created_at) : Carbon::now());
        $priority = strval(array_get($ticket->value, 'priority', 'normal')) ?: 'normal';
        $business = boolval(array_get($policy->value, 'business_hours_only', '1'));
        $cal = static::calendar();

        $first = intval(array_get($policy->value, 'first_' . $priority, 0));
        $resolve = intval(array_get($policy->value, 'resolve_' . $priority, 0));

        $values = [];
        if ($first > 0 && empty(array_get($ticket->value, 'first_response_due'))) {
            $values['first_response_due'] = static::addMinutes($from, $first, $business, $cal)->format('Y-m-d H:i:s');
        }
        if ($resolve > 0 && empty(array_get($ticket->value, 'resolve_due'))) {
            $values['resolve_due'] = static::addMinutes($from, $resolve * 60, $business, $cal)->format('Y-m-d H:i:s');
        }
        if (!empty($values)) {
            $ticket->setValue($values);
        }
    }

    /**
     * Move the resolve due time on by the time the clock was stopped.
     */
    public static function resume(CustomValue $ticket): void
    {
        $paused = array_get($ticket->value, 'sla_paused_at');
        if (empty($paused)) {
            return;
        }
        $due = array_get($ticket->value, 'resolve_due');
        if (!empty($due)) {
            $policy = static::policyFor($ticket);
            $business = isset($policy) ? boolval(array_get($policy->value, 'business_hours_only', '1')) : true;
            $minutes = static::minutesBetween(Carbon::parse($paused), Carbon::now(), $business);
            if ($minutes > 0) {
                $ticket->setValue(['resolve_due' => static::addMinutes(Carbon::parse($due), $minutes, $business)->format('Y-m-d H:i:s')]);
            }
        }
        $ticket->setValue(['sla_paused_at' => null]);
    }

    /**
     * ok / warning / breached / met / none, from the values on the ticket.
     */
    public static function state(CustomValue $ticket, ?Carbon $now = null): string
    {
        $v = (array) $ticket->value;
        $now = $now ?? Carbon::now();
        $status = strval(array_get($v, 'status', 'new'));

        $checks = [];
        if (!empty($v['first_response_due'])) {
            $checks[] = [Carbon::parse($v['first_response_due']), empty($v['first_responded_at']) ? null : Carbon::parse($v['first_responded_at'])];
        }
        if (!empty($v['resolve_due'])) {
            $checks[] = [Carbon::parse($v['resolve_due']), empty($v['resolved_at']) ? null : Carbon::parse($v['resolved_at'])];
        }
        if (empty($checks)) {
            return 'none';
        }

        $paused = in_array($status, Its::PAUSED_STATUSES, true);
        $state = 'met';
        foreach ($checks as [$due, $done]) {
            if (isset($done)) {
                if ($done->greaterThan($due)) {
                    return 'breached';
                }
                continue;
            }
            if (!in_array($status, Its::ACTIVE_STATUSES, true)) {
                continue;
            }
            if ($paused) {
                $state = $state === 'met' ? 'ok' : $state;
                continue;
            }
            if ($now->greaterThan($due)) {
                return 'breached';
            }
            $created = $ticket->created_at ? Carbon::parse($ticket->created_at) : $now;
            $span = max(1, $created->diffInMinutes($due));
            $left = $now->diffInMinutes($due);
            $warn = max(static::WARN_MIN_MINUTES, $span * static::WARN_RATIO);
            $state = $left <= $warn ? 'warning' : ($state === 'warning' ? 'warning' : 'ok');
        }

        return $state;
    }

    /** "あと 2 時間 10 分" / "35 分 超過" for the screens. */
    public static function humanLeft(?string $due): string
    {
        if (empty($due)) {
            return '';
        }
        $due = Carbon::parse($due);
        $now = Carbon::now();
        $minutes = intval(abs($now->diffInMinutes($due)));
        $text = $minutes >= 1440 ? intdiv($minutes, 1440) . ' 日 ' . intdiv($minutes % 1440, 60) . ' 時間'
            : ($minutes >= 60 ? intdiv($minutes, 60) . ' 時間 ' . ($minutes % 60) . ' 分' : $minutes . ' 分');

        return $now->greaterThan($due) ? $text . ' 超過' : 'あと ' . $text;
    }
}
