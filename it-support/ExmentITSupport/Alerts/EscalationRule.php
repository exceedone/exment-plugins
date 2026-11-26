<?php

namespace App\Plugins\ExmentITSupport\Alerts;

/**
 * The steps of the SLA escalation (ManageEngine research of ISS02928, idea 5), without the
 * database, so the CI checks them:
 *
 *   1: the given share of the time to the due time has passed (75% at first) -> the assignee
 *   2: the due time has passed                                                -> the lead
 *   3: the given minutes after the due time have passed                       -> the manager
 *
 * Each step is told once a ticket; a step reached late tells the ones before it no more (only
 * the highest reached). 0: none yet.
 */
class EscalationRule
{
    /**
     * The step reached now.
     *
     * @param int $start when the clock started (unix time; the ticket was made)
     * @param int $due the due time looked at (unix time)
     * @param int $percent the share of step 1 (1-99)
     * @param int $managerAfter the minutes after the due time of step 3 (0: no step 3)
     */
    public static function step(int $start, int $due, int $now, int $percent, int $managerAfter): int
    {
        if ($due <= 0) {
            return 0;
        }
        if ($managerAfter > 0 && $now >= $due + $managerAfter * 60) {
            return 3;
        }
        if ($now >= $due) {
            return 2;
        }
        $span = max(1, $due - $start);
        $percent = max(1, min(99, $percent));
        if (($now - $start) * 100 >= $span * $percent) {
            return 1;
        }

        return 0;
    }

    /**
     * What to send now: the step reached, if it is past the one told (null: nothing).
     */
    public static function toSend(int $reached, int $told): ?int
    {
        return $reached > $told ? $reached : null;
    }

    /**
     * The due time an escalation looks at: the first answer while there is none yet, then the
     * solution.
     *
     * @param array<string, mixed> $values of the ticket
     */
    public static function dueOf(array $values): ?string
    {
        if (empty($values['first_responded_at']) && !empty($values['first_response_due'])) {
            return strval($values['first_response_due']);
        }
        if (empty($values['resolved_at']) && !empty($values['resolve_due'])) {
            return strval($values['resolve_due']);
        }

        return null;
    }

    /**
     * Who is told at a step: the assignee at 1 (every agent when there is none), the leads at 2,
     * the managers at 3; a step with nobody set goes to the one before it.
     *
     * @param array<int, string> $leads
     * @param array<int, string> $managers
     * @param array<int, string> $agents
     * @return array<int, string>
     */
    public static function recipients(int $step, ?string $assignee, array $leads, array $managers, array $agents): array
    {
        $first = ($assignee ?? '') !== '' ? [strval($assignee)] : array_values($agents);
        $second = !empty($leads) ? array_values($leads) : $first;
        $third = !empty($managers) ? array_values($managers) : $second;
        $list = $step >= 3 ? $third : ($step === 2 ? $second : $first);

        return array_values(array_unique(array_filter(array_map('strval', $list), function ($id) {
            return $id !== '';
        })));
    }
}
