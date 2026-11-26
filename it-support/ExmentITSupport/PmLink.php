<?php

namespace App\Plugins\ExmentITSupport;

use Exceedone\Exment\Enums\RelationType;
use Exceedone\Exment\Model\CustomColumn;
use Exceedone\Exment\Model\CustomRelation;
use Exceedone\Exment\Model\CustomTable;
use Exceedone\Exment\Model\CustomValue;

/**
 * 「ExmentPM の課題にする」 (ISS02941 step 4): a ticket that needs work is made an issue of a
 * project of ExmentPM in one press, and the issue's status is shown on the ticket.
 *
 * The plugins share no code: this reads ExmentPM's tables as Exment's tables (project,
 * pm_issue), the way ExmentPM fills a new issue (the first status and type of the project's
 * own lists, LightApiController::scopedDefault); a list of the whole space stays empty there too.
 * The ticket keeps "<project id>:<issue id>" in its column pm_issue (schema 11).
 */
class PmLink
{
    const PROJECT_TABLE = 'project';
    const ISSUE_TABLE = 'pm_issue';
    /** The issue's lists ExmentPM fills from the project's own rows. */
    const SCOPED = ['status', 'issue_type'];

    public static function available(): bool
    {
        return Feature::on(Feature::PM_LINK) && CustomTable::getEloquent(static::ISSUE_TABLE) !== null && CustomTable::getEloquent(static::PROJECT_TABLE) !== null;
    }

    /** Whether the person may make an issue (the button shows only then; the server asks again). */
    public static function canCreate(): bool
    {
        $table = CustomTable::getEloquent(static::ISSUE_TABLE);

        return isset($table) && boolval($table->hasPermission(\Exceedone\Exment\Enums\Permission::AVAILABLE_EDIT_CUSTOM_VALUE));
    }

    /** "12:345" → [12, 345], or null. */
    public static function parse($value): ?array
    {
        return preg_match('/^(\d+):(\d+)$/', strval($value ?? ''), $m) ? [intval($m[1]), intval($m[2])] : null;
    }

    public static function url(int $projectId, int $issueId): string
    {
        return admin_url('plugins/exment_pm/' . $projectId . '/issue/' . $issueId);
    }

    /**
     * The projects the person may see (Exment's permissions), open ones, for the select.
     *
     * @return array<string, string> id => "CODE 名前"
     */
    public static function projects(): array
    {
        $table = CustomTable::getEloquent(static::PROJECT_TABLE);
        if (!isset($table)) {
            return [];
        }
        $out = [];
        foreach ($table->getValueModel()->query()->orderBy('id', 'desc')->limit(300)->get() as $p) {
            if (!empty(array_get($p->value, 'archived'))) {
                continue;
            }
            $code = strval(array_get($p->value, 'project_code'));
            $out[strval($p->id)] = trim(($code !== '' ? $code . ' ' : '') . strval(array_get($p->value, 'name')));
        }

        return $out;
    }

    /**
     * The issue a ticket became, for the ticket's page: its link, number, subject and status.
     *
     * @return array{url: string, subject: string, status: string}|null
     */
    public static function issueOf(CustomValue $ticket): ?array
    {
        $ids = static::parse(array_get($ticket->value, 'pm_issue'));
        $table = CustomTable::getEloquent(static::ISSUE_TABLE);
        if ($ids === null || !isset($table)) {
            return null;
        }
        $issue = $table->getValueModel()->query()->withoutGlobalScope(\Exceedone\Exment\Model\CustomValueModelScope::class)->find($ids[1]);
        if (!isset($issue)) {
            return ['url' => '', 'subject' => '（消された課題）', 'status' => ''];
        }

        return [
            'url' => static::url($ids[0], $ids[1]),
            'subject' => strval(array_get($issue->value, 'subject')),
            'status' => strval($issue->getValue('status', true) ?? ''),
        ];
    }

    /**
     * Make the issue of a ticket in a project, and note it on the ticket.
     *
     * @return array{ok: bool, message: string, url?: string}
     */
    public static function create(CustomValue $ticket, int $projectId): array
    {
        if (!static::available()) {
            return ['ok' => false, 'message' => 'ExmentPM の課題にする機能は、いま使えません。'];
        }
        if (static::parse(array_get($ticket->value, 'pm_issue')) !== null) {
            return ['ok' => false, 'message' => 'このチケットは、もう課題になっています。'];
        }
        $projects = CustomTable::getEloquent(static::PROJECT_TABLE);
        // the person's own permissions: a project they cannot see is not one to file into
        $project = $projects->getValueModel()->query()->find($projectId);
        if (!isset($project)) {
            return ['ok' => false, 'message' => 'プロジェクトが見つかりません（見られるプロジェクトを選んでください）。'];
        }
        $issues = CustomTable::getEloquent(static::ISSUE_TABLE);
        if (!$issues->hasPermission(\Exceedone\Exment\Enums\Permission::AVAILABLE_EDIT_CUSTOM_VALUE)) {
            return ['ok' => false, 'message' => '課題を作る権限がありません。'];
        }
        $no = strval(array_get($ticket->value, 'ticket_no'));
        $values = [
            'subject' => mb_substr('[' . $no . '] ' . strval(array_get($ticket->value, 'subject')), 0, 250),
            'description' => trim(strval(array_get($ticket->value, 'description'))) . "\n\n---\nサポート窓口のチケット " . $no . '：' . Its::url('ticket', strval($ticket->id)),
        ];
        foreach (static::SCOPED as $col) {
            $first = static::firstOfProject($issues, $col, $projectId);
            if ($first !== null) {
                $values[$col] = $first;
            }
        }
        $issue = $issues->getValueModel();
        $issue->setValue($values);
        $issue->parent_type = static::PROJECT_TABLE;
        $issue->parent_id = $projectId;
        $issue->save();

        $ticket->setValue(['pm_issue' => $projectId . ':' . $issue->id]);
        $ticket->save();
        $url = static::url($projectId, intval($issue->id));
        Tickets::addMessage($ticket, ['kind' => 'system', 'body' => 'ExmentPM の課題にしました：' . $values['subject'] . "\n" . $url, 'via' => 'staff']);

        return ['ok' => true, 'message' => 'ExmentPM の課題にしました。', 'url' => $url];
    }

    /** The first row (by display_order) of the project's own list a column points at, or null. */
    protected static function firstOfProject(CustomTable $issues, string $col, int $projectId): ?string
    {
        $column = CustomColumn::getEloquent($col, $issues);
        if (!isset($column) || $column->column_type !== 'select_table') {
            return null;
        }
        $target = CustomTable::getEloquent(array_get($column->options, 'select_target_table'));
        if (!isset($target)) {
            return null;
        }
        $relation = CustomRelation::getRelationByChild($target, RelationType::ONE_TO_MANY);
        if (!isset($relation) || optional($relation->parent_custom_table)->table_name !== static::PROJECT_TABLE) {
            return null;
        }
        $q = $target->getValueModel()->query()->withoutGlobalScope(\Exceedone\Exment\Model\CustomValueModelScope::class)
            ->where('parent_type', static::PROJECT_TABLE)->where('parent_id', $projectId);
        if (CustomColumn::getEloquent('display_order', $target) !== null) {
            $q->orderByRaw("CAST(JSON_UNQUOTE(JSON_EXTRACT(value, '$.display_order')) AS UNSIGNED) asc");
        }
        $row = $q->orderBy('id')->first();

        return isset($row) ? strval($row->id) : null;
    }
}
