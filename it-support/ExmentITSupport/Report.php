<?php

namespace App\Plugins\ExmentITSupport;

use Carbon\Carbon;

/**
 * The month in numbers, for the desk and for the customer's monthly report.
 *
 * Computed on request from the tickets themselves - no nightly job, no
 * "refresh" button (one of the complaints about a market tool was a
 * dashboard that only updates by hand).
 */
class Report
{
    /**
     * @return array<string, mixed>
     */
    public static function build(string $month, string $group = ''): array
    {
        $start = Carbon::createFromFormat('Y-m-d H:i:s', $month . '-01 00:00:00');
        $end = $start->copy()->endOfMonth();
        $from = $start->format('Y-m-d H:i:s');
        $to = $end->format('Y-m-d H:i:s');

        $created = Tickets::query()->whereBetween('created_at', [$from, $to])->get();
        $resolved = Tickets::query()->whereBetween('value->resolved_at', [$from, $to])->get();
        $open = Tickets::query()->whereIn('value->status', Its::ACTIVE_STATUSES)->get();
        // IT support or the inquiries only (ISS02941); a ticket of no box is IT
        if (isset(Queue::GROUPS[$group])) {
            $in = function ($t) use ($group) {
                return Tickets::boxOf(array_get($t->value, 'category'))['group'] === $group;
            };
            $created = $created->filter($in)->values();
            $resolved = $resolved->filter($in)->values();
            $open = $open->filter($in)->values();
        }

        // First response time and SLA, over tickets that got an answer.
        $firstMinutes = [];
        $slaChecked = 0;
        $slaMet = 0;
        foreach ($created as $t) {
            $v = (array) $t->value;
            if (!empty($v['first_responded_at'])) {
                $firstMinutes[] = max(0, intval(Carbon::parse($t->created_at)->diffInMinutes(Carbon::parse($v['first_responded_at']))));
            }
        }
        foreach ($resolved as $t) {
            $state = Sla::state($t);
            if ($state === 'none') {
                continue;
            }
            $slaChecked++;
            $slaMet += $state === 'breached' ? 0 : 1;
        }
        sort($firstMinutes);
        $median = empty($firstMinutes) ? null : $firstMinutes[intdiv(count($firstMinutes), 2)];

        $csat = $resolved->filter(function ($t) {
            return !empty(array_get($t->value, 'csat'));
        })->map(function ($t) {
            return intval(array_get($t->value, 'csat'));
        });

        // Work time by customer from the messages of the month.
        $minutesByTicket = [];
        foreach (Its::table(Its::T_MESSAGE)->getValueModel()->query()->withoutGlobalScope(\Exceedone\Exment\Model\CustomValueModelScope::class)
            ->where('parent_type', Its::T_TICKET)->whereBetween('created_at', [$from, $to])->get(['parent_id', 'value']) as $m) {
            $minutesByTicket[$m->parent_id] = ($minutesByTicket[$m->parent_id] ?? 0) + intval(array_get($m->value, 'time_spent', 0));
        }
        $ticketCustomer = [];
        if (!empty($minutesByTicket)) {
            foreach (Tickets::query()->whereIn('id', array_keys($minutesByTicket))->get(['id', 'value']) as $t) {
                $ticketCustomer[$t->id] = strval(array_get($t->value, 'customer'));
            }
        }

        $customers = Its::table(Its::T_CUSTOMER)->getValueModel()->query()->withoutGlobalScope(\Exceedone\Exment\Model\CustomValueModelScope::class)->get()->keyBy('id');
        $by = [];
        $row = function ($cid) use (&$by, $customers) {
            $key = $cid === '' ? '0' : $cid;
            if (!isset($by[$key])) {
                $c = $customers->get(intval($cid));
                $by[$key] = [
                    'id' => $key,
                    'name' => isset($c) ? strval(array_get($c->value, 'name')) : '（お客様なし）',
                    'contract' => isset($c) && strval(array_get($c->value, 'contract_hours')) !== '' ? floatval(array_get($c->value, 'contract_hours')) : null,
                    'count' => 0, 'resolved' => 0, 'minutes' => 0, 'sla_n' => 0, 'sla_ok' => 0, 'csat_n' => 0, 'csat_sum' => 0,
                ];
            }

            return $key;
        };
        foreach ($created as $t) {
            $by[$row(strval(array_get($t->value, 'customer')))]['count']++;
        }
        foreach ($resolved as $t) {
            $k = $row(strval(array_get($t->value, 'customer')));
            $by[$k]['resolved']++;
            $state = Sla::state($t);
            if ($state !== 'none') {
                $by[$k]['sla_n']++;
                $by[$k]['sla_ok'] += $state === 'breached' ? 0 : 1;
            }
            if (!empty($c = array_get($t->value, 'csat'))) {
                $by[$k]['csat_n']++;
                $by[$k]['csat_sum'] += intval($c);
            }
        }
        foreach ($minutesByTicket as $tid => $minutes) {
            $by[$row($ticketCustomer[$tid] ?? '')]['minutes'] += $minutes;
        }
        foreach ($by as &$b) {
            $b['sla'] = $b['sla_n'] > 0 ? intval(round($b['sla_ok'] * 100 / $b['sla_n'])) : null;
            $b['csat'] = $b['csat_n'] > 0 ? round($b['csat_sum'] / $b['csat_n'], 1) : null;
            $b['over'] = $b['contract'] !== null && $b['minutes'] > $b['contract'] * 60;
        }
        unset($b);
        usort($by, function ($a, $b) {
            return $b['minutes'] <=> $a['minutes'] ?: $b['count'] <=> $a['count'];
        });

        $count = function ($items, string $column, array $labels) {
            $out = array_fill_keys(array_keys($labels), 0);
            foreach ($items as $t) {
                $k = strval(array_get($t->value, $column));
                if (isset($out[$k])) {
                    $out[$k]++;
                }
            }

            return $out;
        };

        // Per day, for the little bar chart.
        $daily = [];
        for ($d = $start->copy(); $d->lessThanOrEqualTo($end); $d->addDay()) {
            $daily[$d->format('Y-m-d')] = ['in' => 0, 'out' => 0];
        }
        foreach ($created as $t) {
            $k = Carbon::parse($t->created_at)->format('Y-m-d');
            if (isset($daily[$k])) {
                $daily[$k]['in']++;
            }
        }
        foreach ($resolved as $t) {
            $k = Carbon::parse(array_get($t->value, 'resolved_at'))->format('Y-m-d');
            if (isset($daily[$k])) {
                $daily[$k]['out']++;
            }
        }

        $categories = Tickets::options(Its::T_CATEGORY);
        $byCategory = [];
        foreach ($created as $t) {
            $name = $categories[strval(array_get($t->value, 'category'))] ?? '（受付箱なし）';
            $byCategory[$name] = ($byCategory[$name] ?? 0) + 1;
        }
        arsort($byCategory);

        $agents = Tickets::agentOptions();
        $byAgent = [];
        foreach ($open as $t) {
            $name = $agents[strval(array_get($t->value, 'assignee'))] ?? '（担当者なし）';
            $byAgent[$name] = ($byAgent[$name] ?? 0) + 1;
        }
        arsort($byAgent);

        $deflect = Its::table(Its::T_KNOWLEDGE)->getValueModel()->query()->withoutGlobalScope(\Exceedone\Exment\Model\CustomValueModelScope::class)->where('value->public', '1')->get();

        return [
            'kpi' => [
                'created' => $created->count(),
                'resolved' => $resolved->count(),
                'open' => $open->count(),
                'breached' => $open->filter(function ($t) {
                    return array_get($t->value, 'sla_state') === 'breached';
                })->count(),
                'sla' => $slaChecked > 0 ? intval(round($slaMet * 100 / $slaChecked)) : null,
                'first_median' => $median,
                'csat' => $csat->count() > 0 ? round($csat->avg(), 1) : null,
                'csat_n' => $csat->count(),
                'faq_views' => $deflect->sum(function ($k) {
                    return intval(array_get($k->value, 'views', 0));
                }),
            ],
            'byChannel' => $count($created, 'channel', Its::CHANNELS),
            'byPriority' => $count($created, 'priority', Its::PRIORITIES),
            'byCategory' => $byCategory,
            'byAgent' => $byAgent,
            'byCustomer' => $by,
            'daily' => $daily,
            'byBox' => static::byBox($created, $resolved, $open),
        ];
    }

    /**
     * Each 受付箱 (ISS02941): received and solved in the month, open now with their mean
     * progress, the first reply's median, the SLA kept, and for quotes won / lost and the rate.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function byBox($created, $resolved, $open): array
    {
        $rows = [];
        $row = function ($t) use (&$rows) {
            $box = Tickets::boxOf(array_get($t->value, 'category'));
            $key = strval($box['id'] ?? 0);
            if (!isset($rows[$key])) {
                $rows[$key] = ['id' => $key, 'name' => $box['id'] ? $box['name'] : '（受付箱なし）', 'kind' => $box['kind'], 'stages' => $box['stages'],
                    'created' => 0, 'resolved' => 0, 'open' => 0, 'progress' => [], 'first' => [], 'sla_n' => 0, 'sla_met' => 0, 'won' => 0, 'lost' => 0];
            }

            return $key;
        };
        foreach ($created as $t) {
            $k = $row($t);
            $rows[$k]['created']++;
            $v = (array) $t->value;
            if (!empty($v['first_responded_at'])) {
                $rows[$k]['first'][] = max(0, intval(Carbon::parse($t->created_at)->diffInMinutes(Carbon::parse($v['first_responded_at']))));
            }
        }
        foreach ($resolved as $t) {
            $k = $row($t);
            $rows[$k]['resolved']++;
            $state = Sla::state($t);
            if ($state !== 'none') {
                $rows[$k]['sla_n']++;
                $rows[$k]['sla_met'] += $state === 'breached' ? 0 : 1;
            }
            $outcome = Queue::outcome($rows[$k]['kind'], $rows[$k]['stages'], strval(array_get($t->value, 'stage')));
            if ($outcome !== null) {
                $rows[$k][$outcome]++;
            }
        }
        foreach ($open as $t) {
            $k = $row($t);
            $rows[$k]['open']++;
            $rows[$k]['progress'][] = intval(array_get($t->value, 'progress', 0));
        }
        $out = [];
        foreach ($rows as $r) {
            sort($r['first']);
            $decided = $r['won'] + $r['lost'];
            $out[] = [
                'id' => $r['id'], 'name' => $r['name'], 'kind' => $r['kind'],
                'created' => $r['created'], 'resolved' => $r['resolved'], 'open' => $r['open'],
                'progress' => empty($r['progress']) ? null : intval(round(array_sum($r['progress']) / count($r['progress']))),
                'first_median' => empty($r['first']) ? null : $r['first'][intdiv(count($r['first']), 2)],
                'sla' => $r['sla_n'] > 0 ? intval(round($r['sla_met'] * 100 / $r['sla_n'])) : null,
                'won' => $r['won'], 'lost' => $r['lost'],
                'win_rate' => $decided > 0 ? intval(round($r['won'] * 100 / $decided)) : null,
            ];
        }
        usort($out, function ($a, $b) {
            return ($b['created'] + $b['open']) <=> ($a['created'] + $a['open']);
        });

        return $out;
    }

    public static function minutesText(?int $minutes): string
    {
        if ($minutes === null) {
            return '—';
        }

        return $minutes >= 60 ? intdiv($minutes, 60) . ' 時間 ' . ($minutes % 60) . ' 分' : $minutes . ' 分';
    }
}
