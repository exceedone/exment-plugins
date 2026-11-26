<?php

namespace App\Plugins\ExmentITSupport;

use Carbon\Carbon;
use Exceedone\Exment\Model\CustomValue;

/**
 * Problems (the ManageEngine research of ISS02928, idea 3): the tickets of one cause under one
 * problem, with what caused it, how to work around it and how it was solved; the tickets closed
 * together, and the problem turned into knowledge.
 *
 * Off at first (the setting "problems_enabled"): the desk shows nothing of it, and its pages
 * answer "not used".
 */
class Problems
{
    public static function enabled(): bool
    {
        return Its::setting('problems_enabled', '0') === '1' && Its::table(Its::T_PROBLEM) !== null;
    }

    public static function query()
    {
        return Its::table(Its::T_PROBLEM)->getValueModel()->query()->withoutGlobalScope(\Exceedone\Exment\Model\CustomValueModelScope::class);
    }

    public static function find($id): ?CustomValue
    {
        if (!ctype_digit(strval($id)) || Its::table(Its::T_PROBLEM) === null) {
            return null;
        }

        return static::query()->find(intval($id));
    }

    /** PRB-00012, or 12. */
    public static function findByNo(string $no): ?CustomValue
    {
        $no = strtoupper(trim($no));
        if (ctype_digit($no)) {
            $no = 'PRB-' . str_pad($no, 5, '0', STR_PAD_LEFT);
        }

        return $no === '' ? null : static::query()->where('value->problem_no', $no)->first();
    }

    /**
     * A new problem made from a ticket, and the ticket linked to it.
     */
    public static function createFrom(CustomValue $ticket): CustomValue
    {
        $problem = Its::table(Its::T_PROBLEM)->getValueModel();
        $problem->setValue([
            'title' => mb_substr(strval(array_get($ticket->value, 'subject')), 0, 250),
            'status' => 'open',
            'assignee' => array_get($ticket->value, 'assignee'),
            'description' => mb_substr(strval(array_get($ticket->value, 'description')), 0, 20000),
        ]);
        $problem->save();
        static::link($ticket, $problem);

        return $problem;
    }

    public static function link(CustomValue $ticket, CustomValue $problem): void
    {
        $ticket->setValue(['problem' => $problem->id]);
        $ticket->save();
        Tickets::addMessage($ticket, ['kind' => 'system', 'body' => '問題 ' . array_get($problem->value, 'problem_no') . '「' . array_get($problem->value, 'title') . '」につなぎました。', 'via' => 'staff']);
    }

    public static function unlink(CustomValue $ticket): void
    {
        $ticket->setValue(['problem' => null]);
        $ticket->save();
    }

    /**
     * @return \Illuminate\Support\Collection<int, CustomValue>
     */
    public static function tickets(CustomValue $problem)
    {
        return Tickets::query()->where('value->problem', strval($problem->id))->orderBy('id')->get();
    }

    /**
     * Every open ticket of the problem resolved, with the solution (or the workaround) written on
     * it for the agents; the problem marked solved.
     *
     * @return int the tickets resolved
     */
    public static function resolveAll(CustomValue $problem, string $note = ''): int
    {
        $text = trim($note) !== '' ? trim($note) : (trim(strval(array_get($problem->value, 'resolution'))) ?: trim(strval(array_get($problem->value, 'workaround'))));
        $n = 0;
        foreach (static::tickets($problem) as $ticket) {
            if (!in_array(strval(array_get($ticket->value, 'status')), Its::ACTIVE_STATUSES, true)) {
                continue;
            }
            Tickets::addMessage($ticket, [
                'kind' => 'note',
                'body' => '問題 ' . array_get($problem->value, 'problem_no') . ' をまとめて解決にしました。' . ($text !== '' ? "\n\n" . mb_substr($text, 0, 5000) : ''),
                'via' => 'staff',
            ]);
            // no mail to the customer, the survey of a resolved ticket either (the page says so)
            $quiet = Automation::$quiet;
            Automation::$quiet = true;
            try {
                $ticket->setValue(['status' => 'resolved']);
                $ticket->save();
            } finally {
                Automation::$quiet = $quiet;
            }
            $n++;
        }
        $problem->setValue(['status' => 'resolved', 'resolved_at' => Carbon::now()->format('Y-m-d H:i:s')]);
        $problem->save();

        return $n;
    }

    /**
     * A draft of knowledge from the cause, the workaround and the solution (not public: the
     * agent reads it again first).
     */
    public static function toKnowledge(CustomValue $problem): ?CustomValue
    {
        $table = Its::table(Its::T_KNOWLEDGE);
        if (!isset($table)) {
            return null;
        }
        $v = (array) $problem->value;
        $parts = [];
        foreach (['description' => '【起きていること】', 'root_cause' => '【原因】', 'workaround' => '【回避のしかた】', 'resolution' => '【解決のしかた】'] as $col => $head) {
            if (trim(strval($v[$col] ?? '')) !== '') {
                $parts[] = $head . "\n" . trim(strval($v[$col]));
            }
        }
        $row = $table->getValueModel();
        $row->setValue([
            'title' => strval($v['title'] ?? ''),
            'body' => implode("\n\n", $parts),
            'public' => '0',
        ]);
        $row->save();
        $problem->setValue(['knowledge_id' => $row->id]);
        $problem->save();

        return $row;
    }

    /**
     * The problems shown in the list: the open ones first, then the newest.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function listRows(bool $all = false): array
    {
        $q = static::query()->orderByDesc('id')->limit(200);
        if (!$all) {
            $q->whereIn('value->status', ['open', 'known_error']);
        }
        $rows = [];
        foreach ($q->get() as $p) {
            $count = Tickets::query()->where('value->problem', strval($p->id))->count();
            $open = Tickets::query()->where('value->problem', strval($p->id))->whereIn('value->status', Its::ACTIVE_STATUSES)->count();
            $rows[] = [
                'id' => intval($p->id),
                'no' => strval(array_get($p->value, 'problem_no')),
                'title' => strval(array_get($p->value, 'title')),
                'status' => strval(array_get($p->value, 'status')),
                'tickets' => $count,
                'open' => $open,
                'updated' => strval($p->updated_at),
            ];
        }

        return $rows;
    }
}
