<?php

namespace App\Plugins\ExmentPMBacklog;

use Illuminate\Support\Facades\DB;

/**
 * Backlog's custom fields into ExmentPM's custom attributes (ISS02939).
 *
 * The fields of the Backlog project (GET projects/:id/customFields) become the attributes of the
 * Exment project - the same name and a kind that fits - and the values of each issue go into the
 * issue's attributes (the column pm_attributes, JSON keyed by the attribute's id). The project's
 * switch 「カスタム属性」 is turned on. An attribute somebody deleted in Exment stays deleted;
 * the values of the attributes that are not Backlog's are kept.
 *
 * Written against the tables, not ExmentPM's classes: a batch does not load the other plugin.
 * The shape is ExmentPM's PortalAttributes (pm_portal_attributes.options: choices, unit,
 * decimals, max). An attribute made here is never required: an issue Backlog has without the
 * value would be refused.
 */
class Attributes
{
    const TABLE = 'pm_portal_attributes';
    const COLUMN = 'pm_attributes';
    const SWITCH = 'use_attributes';
    const MAX_PER_PROJECT = 30;
    const MAX_OPTIONS = 50;
    const TEXT_MAX = 1000;

    /** Backlog's typeId => the kind of attribute. */
    const TYPES = [
        1 => 'text',          // 文字列
        2 => 'text',          // 文章
        3 => 'number',        // 数値
        4 => 'date',          // 日付
        5 => 'select',        // 単一リスト
        6 => 'multi_select',  // 複数リスト
        7 => 'multi_select',  // チェックボックス
        8 => 'select',        // ラジオ
    ];

    /** Whether this ExmentPM has custom attributes (its table and the issue's column). */
    public static function available(array $issueColumns): bool
    {
        try {
            return isset($issueColumns[static::COLUMN]) && \Schema::hasTable(static::TABLE);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * The attribute for a Backlog field, from the definitions there are (no database: the CI
     * checks it): the same name and kind, or a new one.
     *
     * @param array<string, mixed> $field a Backlog custom field
     * @param array<int, array<string, mixed>> $defs the project's attributes: id, label, type, options, deleted
     * @return array{0: string, 1: array<string, mixed>|null} what to do (use, update, add, deleted, skip), the attribute
     */
    public static function plan(array $field, array $defs): array
    {
        $type = static::TYPES[intval($field['typeId'] ?? 0)] ?? null;
        $label = mb_substr(trim(strval($field['name'] ?? '')), 0, 40);
        if ($type === null || $label === '') {
            return ['skip', null];
        }
        $items = [];
        foreach ((array) ($field['items'] ?? []) as $item) {
            $name = mb_substr(trim(strval(is_array($item) ? ($item['name'] ?? '') : $item)), 0, 100);
            if ($name !== '' && !in_array($name, $items, true)) {
                $items[] = $name;
            }
        }
        foreach ($defs as $d) {
            if (mb_strtolower($d['label']) !== mb_strtolower($label)) {
                continue;
            }
            if ($d['type'] !== $type) {
                // a name Exment uses for something else: not mixed up
                return ['skip', null];
            }
            if (!empty($d['deleted'])) {
                return ['deleted', $d];
            }
            if (in_array($type, ['select', 'multi_select'], true)) {
                $options = (array) $d['options'];
                foreach ($items as $i) {
                    if (!in_array($i, $options, true)) {
                        $options[] = $i;
                    }
                }
                $options = array_slice($options, 0, static::MAX_OPTIONS);
                if ($options !== (array) $d['options']) {
                    return ['update', array_merge($d, ['options' => $options])];
                }
            }
            return ['use', $d];
        }

        return ['add', [
            'id' => 0,
            'label' => $label,
            'type' => $type,
            'options' => array_slice($items, 0, static::MAX_OPTIONS),
            'unit' => $type === 'number' ? mb_substr(trim(strval($field['unit'] ?? '')), 0, 10) : '',
            'decimals' => $type === 'number' ? 2 : 0,
            'max' => $type === 'text' ? static::TEXT_MAX : 0,
            'deleted' => false,
        ]];
    }

    /**
     * Make the project's attributes from its Backlog fields.
     *
     * @param array<int, array<string, mixed>> $fields
     * @param callable $problem a line for the run's problems
     * @return array<string, array<string, mixed>> Backlog field id => the attribute
     */
    public static function sync(int $projectId, array $fields, int $userId, callable $problem): array
    {
        $defs = static::read($projectId);
        $live = count(array_filter($defs, function ($d) {
            return empty($d['deleted']);
        }));
        $map = [];
        foreach ($fields as $field) {
            if (!is_array($field) || !isset($field['id'])) {
                continue;
            }
            [$what, $d] = static::plan($field, $defs);
            if ($what === 'skip' || $what === 'deleted') {
                continue;
            }
            if ($what === 'add') {
                if ($live >= static::MAX_PER_PROJECT) {
                    $problem(Lang::t('problem_attr_too_many', ['name' => $d['label'], 'max' => static::MAX_PER_PROJECT]));
                    continue;
                }
                $sort = 1 + intval(DB::table(static::TABLE)->where('project_id', $projectId)->max('sort'));
                $d['id'] = intval(DB::table(static::TABLE)->insertGetId([
                    'project_id' => $projectId, 'label' => $d['label'], 'type' => $d['type'],
                    'options' => static::options($d), 'required' => 0, 'sort' => $sort,
                    'created_user_id' => $userId ?: null, 'updated_user_id' => $userId ?: null,
                    'created_at' => now(), 'updated_at' => now(),
                ]));
                $defs[] = $d;
                $live++;
            } elseif ($what === 'update') {
                DB::table(static::TABLE)->where('id', $d['id'])->update(['options' => static::options($d), 'updated_at' => now()]);
                foreach ($defs as $k => $old) {
                    if ($old['id'] === $d['id']) {
                        $defs[$k] = $d;
                    }
                }
            }
            $map[strval($field['id'])] = $d;
        }

        return $map;
    }

    /** @return array<int, array<string, mixed>> */
    protected static function read(int $projectId): array
    {
        $out = [];
        foreach (DB::table(static::TABLE)->where('project_id', $projectId)->orderBy('sort')->orderBy('id')->get() as $row) {
            $o = json_decode(strval($row->options ?? ''), true);
            $o = is_array($o) ? $o : [];
            $out[] = [
                'id' => intval($row->id), 'label' => strval($row->label), 'type' => strval($row->type),
                'options' => array_values((array) ($o['choices'] ?? [])), 'unit' => strval($o['unit'] ?? ''),
                'decimals' => intval($o['decimals'] ?? 0), 'max' => intval($o['max'] ?? static::TEXT_MAX),
                'deleted' => $row->deleted_at !== null,
            ];
        }
        return $out;
    }

    /** @param array<string, mixed> $d */
    protected static function options(array $d): string
    {
        return strval(json_encode(['choices' => array_values($d['options']), 'unit' => $d['unit'] ?? '', 'decimals' => intval($d['decimals'] ?? 0), 'max' => intval($d['max'] ?? 0)], JSON_UNESCAPED_UNICODE));
    }

    /**
     * The attributes of an issue from its Backlog fields (no database: the CI checks it). The
     * attributes that are not Backlog's keep their values; a Backlog field with no value empties
     * its attribute.
     *
     * @param array<string, mixed> $issue a Backlog issue
     * @param array<string, array<string, mixed>> $map Backlog field id => attribute
     * @param mixed $stored the issue's column now
     */
    public static function values(array $issue, array $map, $stored): string
    {
        $values = json_decode(is_string($stored) ? $stored : '', true);
        $values = is_array($values) ? $values : [];
        foreach ($map as $d) {
            unset($values[strval($d['id'])]);
        }
        foreach ((array) ($issue['customFields'] ?? []) as $f) {
            $d = is_array($f) ? ($map[strval($f['id'] ?? '')] ?? null) : null;
            if ($d === null) {
                continue;
            }
            $v = static::value($d, $f['value'] ?? null);
            if ($v !== null) {
                $values[strval($d['id'])] = $v;
            }
        }
        $values = array_filter($values, function ($v, $k) {
            return preg_match('/^[1-9][0-9]{0,9}$/', strval($k)) && $v !== null && $v !== '' && $v !== [];
        }, ARRAY_FILTER_USE_BOTH);
        if (empty($values)) {
            return '';
        }
        ksort($values, SORT_NUMERIC);

        return strval(json_encode($values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * One Backlog value as the attribute keeps it, or null.
     *
     * @param array<string, mixed> $d
     * @param mixed $raw
     * @return mixed
     */
    public static function value(array $d, $raw)
    {
        $name = function ($x) {
            return trim(strval(is_array($x) ? ($x['name'] ?? '') : $x));
        };
        switch ($d['type']) {
            case 'number':
                if (!is_numeric($raw)) {
                    return null;
                }
                $n = round(floatval($raw), intval($d['decimals'] ?? 2));
                return intval($d['decimals'] ?? 2) === 0 ? intval($n) : $n;
            case 'date':
                $s = substr(trim(strval(is_scalar($raw) ? $raw : '')), 0, 10);
                return preg_match('/^\d{4}-\d{2}-\d{2}$/', $s) ? $s : null;
            case 'select':
                $one = is_array($raw) && array_key_exists(0, $raw) ? ($raw[0] ?? null) : $raw;
                $s = $one === null ? '' : $name($one);
                return $s !== '' && in_array($s, (array) $d['options'], true) ? $s : null;
            case 'multi_select':
                $list = [];
                $items = is_array($raw) && !array_key_exists(0, $raw) && isset($raw['name']) ? [$raw] : (array) $raw;
                foreach ($items as $x) {
                    $s = $name($x);
                    if ($s !== '' && in_array($s, (array) $d['options'], true) && !in_array($s, $list, true)) {
                        $list[] = $s;
                    }
                }
                usort($list, function ($a, $b) use ($d) {
                    return array_search($a, $d['options'], true) <=> array_search($b, $d['options'], true);
                });
                return empty($list) ? null : $list;
            default:
                $s = trim(str_replace(["\r\n", "\r"], "\n", strval(is_scalar($raw) ? $raw : (is_array($raw) ? $name($raw) : ''))));
                return $s === '' ? null : mb_substr($s, 0, max(1, intval($d['max'] ?? static::TEXT_MAX)));
        }
    }
}
