<?php

namespace App\Plugins\ExmentPM;

use Exceedone\Exment\Enums\SystemTableName;
use Exceedone\Exment\Model\CustomTable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * The download of the issue list (ISS00275): CSV or Excel (.xlsx), every column of an issue or
 * the columns of the list. The page sends the ids of the issues it lists, in its order (its
 * filters and sort); the server reads them again, with the caller's permissions, and writes the
 * names of the people and the labels of the values, never their ids.
 *
 * POST {id}/export  ids=1,5,3  fmt=csv|xlsx  cols=all|screen
 */
trait PortalExportTrait
{
    /** The issues one download holds at most. */
    protected static $EXPORT_MAX = 5000;

    public function exportIssues(Request $request, $id)
    {
        $project = $this->findProject($id);
        $fmt = $request->input('fmt') === 'xlsx' ? 'xlsx' : 'csv';
        $all = $request->input('cols') !== 'screen';
        $ids = [];
        foreach (explode(',', strval($request->input('ids', ''))) as $one) {
            if (ctype_digit(trim($one)) && intval($one) > 0) {
                $ids[intval($one)] = true;
            }
        }
        $ids = array_slice(array_keys($ids), 0, static::$EXPORT_MAX);
        if (empty($ids)) {
            return $this->messageError(422, 'export_empty');
        }

        // the permission scope of Exment applies to this query: a row the caller may not read is
        // not there
        $byId = [];
        foreach (array_chunk($ids, 500) as $chunk) {
            foreach ($this->issue_table->getValueModel()->query()
                ->whereIn('id', $chunk)
                ->where('parent_id', $project->id)
                ->where('parent_type', $this->project_table->table_name)
                ->get() as $issue) {
                $byId[intval($issue->id)] = $issue;
            }
        }
        $issues = [];
        foreach ($ids as $one) {
            if (isset($byId[$one])) {
                $issues[] = $byId[$one];
            }
        }
        if (empty($issues)) {
            return $this->messageError(422, 'export_empty');
        }

        $columns = $this->exportColumns($all);
        // the project's custom attributes, a column each, after the others (ISS02939)
        $attrNames = [];
        if (PortalAttributes::projectOn($project)) {
            $people = [];
            foreach (PortalAttributes::forProject(intval($project->id)) as $d) {
                $columns['attr:' . $d['id']] = ['label' => $d['label'], 'type' => $d['type'] === 'number' ? 'number' : ($d['type'] === 'date' ? 'date' : 'text'), 'col' => null, 'attr' => $d];
                if ($d['type'] === 'user') {
                    foreach ($issues as $issue) {
                        $people = array_merge($people, (array) (PortalAttributes::decode(array_get($issue->value, PortalAttributes::column()))[strval($d['id'])] ?? []));
                    }
                }
            }
            foreach ($this->usersFor($people) as $uid => $u) {
                $attrNames[intval($uid)] = strval($u['n'] ?? '');
            }
        }
        $this->exportAttrNames = $attrNames;
        $users = $this->exportUserNames($issues);
        $related = $all ? $this->exportRelated($project, $issues) : [];
        $rows = [];
        foreach ($issues as $issue) {
            $row = [];
            foreach ($columns as $key => $column) {
                $row[] = $this->exportCell($project, $issue, $key, $column, $users, $related);
            }
            $rows[] = $row;
        }
        $heads = array_map(function ($c) {
            return $c['label'];
        }, array_values($columns));
        $types = array_map(function ($c) {
            return $c['type'];
        }, array_values($columns));
        $name = (PortalDefinition::projectSegment($project) ?: 'issues') . '-' . now()->format('Ymd');
        $name = preg_replace('/[^0-9A-Za-z_.-]/', '_', $name);

        try {
            return $fmt === 'xlsx'
                ? $this->exportXlsx($heads, $types, $rows, $name . '.xlsx')
                : $this->exportCsv($heads, $rows, $name . '.csv');
        } catch (\Throwable $e) {
            Log::error('pm_portal export of project ' . $project->id . ' failed: ' . $e->getMessage(), ['exception' => $e]);

            return PortalDefinition::jsonError(500, 'export_failed');
        }
    }

    /**
     * The columns, in order: key => [label, type (text|date|datetime|number), source].
     *
     * @return array<string, array{label: string, type: string, col: ?string}>
     */
    protected function exportColumns(bool $all): array
    {
        $is = $this->def['issue'];
        $extra = (array) array_get($this->def, 'issue_extra', []);
        $t = function ($k) {
            return strval(PortalDefinition::trans('export.col.' . $k));
        };
        $screen = [
            'type' => ['label' => $t('type'), 'type' => 'text', 'col' => $is['type'] ?? null],
            'key' => ['label' => $t('key'), 'type' => 'text', 'col' => null],
            'subject' => ['label' => $t('subject'), 'type' => 'text', 'col' => $is['subject'] ?? null],
            'assignees' => ['label' => $t('assignees'), 'type' => 'text', 'col' => $is['assignees'] ?? null],
            'status' => ['label' => $t('status'), 'type' => 'text', 'col' => $is['status'] ?? null],
            'priority' => ['label' => $t('priority'), 'type' => 'text', 'col' => $is['priority'] ?? null],
            'milestone' => ['label' => $t('milestone'), 'type' => 'text', 'col' => $is['milestone'] ?? null],
            'category' => ['label' => $t('category'), 'type' => 'text', 'col' => $is['category'] ?? null],
            'start' => ['label' => $t('start'), 'type' => 'date', 'col' => $is['start'] ?? null],
            'due' => ['label' => $t('due'), 'type' => 'date', 'col' => $is['due'] ?? null],
            'progress' => ['label' => $t('progress'), 'type' => 'number', 'col' => $is['progress'] ?? null],
            'updated_at' => ['label' => $t('updated_at'), 'type' => 'datetime', 'col' => null],
        ];
        if (!$all) {
            return $screen;
        }
        $out = [
            'key' => $screen['key'],
            'subject' => $screen['subject'],
            'subject_sub' => ['label' => $t('subject_sub'), 'type' => 'text', 'col' => 'subject_sub'],
            'type' => $screen['type'],
            'status' => $screen['status'],
            'priority' => $screen['priority'],
            'assignees' => $screen['assignees'],
            'sub_assignees' => ['label' => $t('sub_assignees'), 'type' => 'text', 'col' => $extra['sub_assignees'] ?? 'sub_assignees'],
            'managers' => ['label' => $t('managers'), 'type' => 'text', 'col' => $extra['managers'] ?? 'managers'],
            'reviewers' => ['label' => $t('reviewers'), 'type' => 'text', 'col' => $extra['reviewers'] ?? 'reviewers'],
            'category' => $screen['category'],
            'milestone' => $screen['milestone'],
            'parent' => ['label' => $t('parent'), 'type' => 'text', 'col' => $is['parent'] ?? null],
            'start' => $screen['start'],
            'due' => $screen['due'],
            'due_time' => ['label' => $t('due_time'), 'type' => 'text', 'col' => $extra['due_time'] ?? 'due_time'],
            'progress' => $screen['progress'],
            'estimate' => ['label' => $t('estimate'), 'type' => 'number', 'col' => $is['estimate'] ?? null],
            'actual' => ['label' => $t('actual'), 'type' => 'number', 'col' => $is['actual'] ?? null],
            'created_user' => ['label' => $t('created_user'), 'type' => 'text', 'col' => null],
            'created_at' => ['label' => $t('created_at'), 'type' => 'datetime', 'col' => null],
            'updated_user' => ['label' => $t('updated_user'), 'type' => 'text', 'col' => null],
            'updated_at' => $screen['updated_at'],
            'description' => ['label' => $t('description'), 'type' => 'text', 'col' => $is['description'] ?? null],
            'related' => ['label' => $t('related'), 'type' => 'text', 'col' => null],
        ];
        // a column this install does not have yet is left out
        return array_filter($out, function ($c, $key) {
            if (in_array($key, ['key', 'created_user', 'created_at', 'updated_user', 'updated_at', 'related'], true)) {
                return true;
            }
            return !is_nullorempty($c['col']) && $this->columnOf($this->issue_table, $c['col']) !== null;
        }, ARRAY_FILTER_USE_BOTH);
    }

    /**
     * @param array<int, \Exceedone\Exment\Model\CustomValue> $issues
     * @return array<int, string> user id => name
     */
    protected function exportUserNames(array $issues): array
    {
        $ids = [];
        foreach ($issues as $issue) {
            $ids[intval($issue->created_user_id)] = true;
            $ids[intval($issue->updated_user_id)] = true;
        }
        unset($ids[0]);
        $names = [];
        $table = CustomTable::getEloquent(SystemTableName::USER);
        if (!isset($table) || empty($ids)) {
            return $names;
        }
        foreach (array_chunk(array_keys($ids), 500) as $chunk) {
            foreach ($table->getValueModel()->query()->withoutGlobalScopes()->whereIn('id', $chunk)->get() as $user) {
                $names[intval($user->id)] = strval($user->getLabel());
            }
        }

        return $names;
    }

    /**
     * The keys of the issues each one is related to.
     *
     * @return array<int, array<int, string>> issue id => keys
     */
    protected function exportRelated($project, array $issues): array
    {
        $table = CustomTable::getEloquent(array_get($this->def, 'related_table'));
        $rel = (array) array_get($this->def, 'related_row', []);
        $from = strval(array_get($rel, 'from'));
        $to = strval(array_get($rel, 'to'));
        if (!isset($table) || $from === '' || $to === '') {
            return [];
        }
        $ids = array_map(function ($i) {
            return intval($i->id);
        }, $issues);
        $out = [];
        foreach (array_chunk($ids, 500) as $chunk) {
            $links = $table->getValueModel()->query()
                ->where(function ($q) use ($from, $to, $chunk) {
                    $q->whereIn('value->' . $from, $chunk)->orWhereIn('value->' . $to, $chunk);
                })->get();
            foreach ($links as $link) {
                $a = intval(array_get($link->value, $from));
                $b = intval(array_get($link->value, $to));
                if ($a > 0 && $b > 0) {
                    $out[$a][] = $this->issueKey($project, $b);
                    $out[$b][] = $this->issueKey($project, $a);
                }
            }
        }
        foreach ($out as $k => $keys) {
            $out[$k] = array_values(array_unique($keys));
        }

        return $out;
    }

    /**
     * One cell: a string, a number, a date (Y-m-d) or a date and time (Y-m-d H:i:s), or null.
     */
    /** @var array<int, string> the names of the people in the custom attributes of an export */
    protected $exportAttrNames = [];

    protected function exportCell($project, $issue, string $key, array $column, array $users, array $related)
    {
        if (isset($column['attr'])) {
            $d = $column['attr'];
            $value = PortalAttributes::decode(array_get($issue->value, PortalAttributes::column()))[strval($d['id'])] ?? null;
            if ($value === null) {
                return null;
            }
            if ($d['type'] === 'number') {
                return floatval($value);
            }
            return PortalAttributes::text($d, $value, $this->exportAttrNames);
        }
        switch ($key) {
            case 'key':
                return $this->issueKey($project, $issue->id);
            case 'created_user':
                return $users[intval($issue->created_user_id)] ?? '';
            case 'updated_user':
                return $users[intval($issue->updated_user_id)] ?? '';
            case 'created_at':
                return $issue->created_at ? $issue->created_at->format('Y-m-d H:i:s') : null;
            case 'updated_at':
                $at = $issue->updated_at ?? $issue->created_at;
                return $at ? $at->format('Y-m-d H:i:s') : null;
            case 'related':
                return implode(' / ', $related[intval($issue->id)] ?? []);
            case 'parent':
                $parent = intval(array_get($issue->value, $column['col']));
                return $parent > 0 ? $this->issueKey($project, $parent) : '';
        }
        $raw = array_get($issue->value, $column['col']);
        if ($column['type'] === 'number') {
            return is_numeric($raw) ? floatval($raw) : null;
        }
        if ($column['type'] === 'date') {
            $d = $this->rawDate($raw);
            return $d === '' || $d === null ? null : $d;
        }
        if ($key === 'due_time') {
            return $this->dueTime($issue->value);
        }
        if (is_nullorempty($raw)) {
            return '';
        }
        // the label Exment shows: names of the people, labels of the selections
        $text = $issue->getValue($column['col'], true);
        if ($text instanceof \Illuminate\Support\Collection) {
            $text = $text->implode(' / ');
        } elseif (is_array($text)) {
            $text = implode(' / ', $text);
        }
        $text = strval($text);
        // the label of a selection is HTML in some versions of Exment
        return html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $text)), ENT_QUOTES, 'UTF-8');
    }

    /**
     * A cell a spreadsheet would read as a formula gets a leading apostrophe.
     */
    protected static function exportSafe(string $s): string
    {
        return (preg_match('/^[=+@\t\r]/', $s) || preg_match('/^-(?!\d+(\.\d+)?$)/', $s)) ? "'" . $s : $s;
    }

    protected function exportCsv(array $heads, array $rows, string $file)
    {
        $out = fopen('php://temp', 'r+');
        // the byte order mark: Excel on a Japanese Windows reads UTF-8 without it as Shift_JIS
        fwrite($out, "\xEF\xBB\xBF");
        foreach (array_merge([$heads], $rows) as $row) {
            fputcsv($out, array_map(function ($v) {
                return $v === null ? '' : (is_string($v) ? static::exportSafe($v) : $v);
            }, $row), ',', '"', '');
            // fputcsv ends a line with LF; Excel reads CRLF as well, and so does every reader
        }
        rewind($out);
        $body = str_replace("\n", "\r\n", str_replace("\r\n", "\n", stream_get_contents($out)));
        fclose($out);

        return response($body, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $file . '"',
            'Cache-Control' => 'no-store',
        ]);
    }

    protected function exportXlsx(array $heads, array $types, array $rows, string $file)
    {
        $book = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('issues');
        $string = \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING;
        foreach ($heads as $c => $head) {
            $sheet->setCellValueExplicitByColumnAndRow($c + 1, 1, $head, $string);
        }
        $sheet->getStyle('1:1')->getFont()->setBold(true);
        foreach ($rows as $r => $row) {
            foreach ($row as $c => $v) {
                if ($v === null || $v === '') {
                    continue;
                }
                $type = $types[$c] ?? 'text';
                if (($type === 'date' || $type === 'datetime') && is_string($v) && ($at = date_create($v)) !== false) {
                    $sheet->setCellValueByColumnAndRow($c + 1, $r + 2, \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel($at));
                    $sheet->getStyleByColumnAndRow($c + 1, $r + 2)->getNumberFormat()
                        ->setFormatCode($type === 'date' ? 'yyyy/mm/dd' : 'yyyy/mm/dd hh:mm');
                } elseif ($type === 'number' && is_numeric($v)) {
                    $sheet->setCellValueByColumnAndRow($c + 1, $r + 2, $v);
                } else {
                    // as text, so nothing is read as a formula; a cell of Excel holds 32,767 characters
                    $sheet->setCellValueExplicitByColumnAndRow($c + 1, $r + 2, mb_substr(strval($v), 0, 32000), $string);
                }
            }
        }
        $sheet->freezePane('A2');
        $sheet->setAutoFilter($sheet->calculateWorksheetDimension());
        $sheet->getDefaultColumnDimension()->setWidth(16);
        foreach ($heads as $c => $head) {
            if (in_array($head, [strval(PortalDefinition::trans('export.col.subject')), strval(PortalDefinition::trans('export.col.description'))], true)) {
                $sheet->getColumnDimensionByColumn($c + 1)->setWidth(48);
            }
        }
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book);
        ob_start();
        $writer->save('php://output');
        $body = ob_get_clean();
        $book->disconnectWorksheets();

        return response($body, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $file . '"',
            'Cache-Control' => 'no-store',
        ]);
    }
}
