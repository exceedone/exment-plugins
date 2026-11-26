<?php

namespace App\Plugins\ExmentPM;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The custom attributes of a project's issues (ISS02939): the items a project adds to its issues
 * (an OS, a customer's name, a version...), as Backlog's 「カスタム属性」.
 *
 * The definitions are rows of pm_portal_attributes, one project each. The values are on the
 * issue itself, in one column of the issue table (pm_attributes, a textarea): a JSON object keyed
 * by the id of the definition, {"12":"Windows","13":3.5,"14":"2026-12-01","15":["a","b"],"16":[7]}.
 * So Exment's revisions keep their history, and a move, a copy, the trash and an export of the
 * issue carry them. An id, not a name: renaming an attribute changes no issue.
 *
 * A project uses them once its manager turns 「カスタム属性」 on (the switch use_attributes, off
 * at first). A deleted attribute is only marked: its values stay, hidden, and come back with it.
 */
class PortalAttributes
{
    const TABLE = 'pm_portal_attributes';
    const TYPES = ['text', 'number', 'date', 'select', 'multi_select', 'user'];

    const MAX_PER_PROJECT = 30;
    const MAX_OPTIONS = 50;
    const MAX_OPTION_LENGTH = 100;
    const MAX_LABEL = 40;
    const TEXT_MAX = 1000;

    /** @var array<int, array<int, array<string, mixed>>>|null project id => definitions (with the deleted) */
    protected static $cache = null;

    /** @var bool|null */
    protected static $exists = null;

    public static function exists(): bool
    {
        if (static::$exists === null) {
            try {
                static::$exists = \Schema::hasTable(static::TABLE);
            } catch (\Throwable $e) {
                return false;
            }
        }
        return static::$exists;
    }

    /** The issue column that holds the values. */
    public static function column(): string
    {
        return strval(array_get(PortalDefinition::config(), 'issue_extra.attributes', PortalSchema::ISSUE_COLUMNS['attributes']));
    }

    public static function forget(): void
    {
        static::$cache = null;
    }

    /**
     * The definitions of a project, in their order.
     *
     * @return array<int, array{id: int, label: string, type: string, options: array<int, string>, required: bool, unit: string, decimals: int, max: int, sort: int, deleted: bool}>
     */
    public static function forProject(int $projectId, bool $withDeleted = false): array
    {
        if (static::$cache === null) {
            static::$cache = [];
        }
        if (!array_key_exists($projectId, static::$cache)) {
            static::$cache[$projectId] = [];
            if (static::exists()) {
                foreach (DB::table(static::TABLE)->where('project_id', $projectId)->orderBy('sort')->orderBy('id')->get() as $row) {
                    static::$cache[$projectId][] = static::fromRow($row);
                }
            }
        }

        return array_values(array_filter(static::$cache[$projectId], function ($d) use ($withDeleted) {
            return $withDeleted || !$d['deleted'];
        }));
    }

    /** @return array<string, mixed> */
    protected static function fromRow($row): array
    {
        $o = json_decode(strval($row->options ?? ''), true);
        $o = is_array($o) ? $o : [];

        return static::definition([
            'id' => intval($row->id),
            'label' => strval($row->label),
            'type' => strval($row->type),
            'options' => (array) ($o['choices'] ?? []),
            'required' => boolval($row->required),
            'unit' => strval($o['unit'] ?? ''),
            'decimals' => intval($o['decimals'] ?? 0),
            'max' => intval($o['max'] ?? static::TEXT_MAX),
            'sort' => intval($row->sort),
            'deleted' => $row->deleted_at !== null,
        ]);
    }

    /**
     * A definition in one shape (no database: the CI checks it).
     *
     * @param array<string, mixed> $d
     * @return array<string, mixed>
     */
    public static function definition(array $d): array
    {
        $type = in_array($d['type'] ?? '', static::TYPES, true) ? $d['type'] : 'text';
        $options = [];
        if (in_array($type, ['select', 'multi_select'], true)) {
            foreach ((array) ($d['options'] ?? []) as $o) {
                $o = mb_substr(trim(strval($o)), 0, static::MAX_OPTION_LENGTH);
                if ($o !== '' && !in_array($o, $options, true)) {
                    $options[] = $o;
                }
            }
            $options = array_slice($options, 0, static::MAX_OPTIONS);
        }

        return [
            'id' => intval($d['id'] ?? 0),
            'label' => mb_substr(trim(strval($d['label'] ?? '')), 0, static::MAX_LABEL),
            'type' => $type,
            'options' => $options,
            'required' => !empty($d['required']),
            'unit' => $type === 'number' ? mb_substr(trim(strval($d['unit'] ?? '')), 0, 10) : '',
            'decimals' => $type === 'number' ? max(0, min(4, intval($d['decimals'] ?? 0))) : 0,
            'max' => $type === 'text' ? max(1, min(static::TEXT_MAX, intval($d['max'] ?? static::TEXT_MAX))) : 0,
            'sort' => intval($d['sort'] ?? 0),
            'deleted' => !empty($d['deleted']),
        ];
    }

    /**
     * The definitions for the screen (short keys).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function payload(int $projectId): array
    {
        return array_map(function ($d) {
            return ['id' => $d['id'], 'l' => $d['label'], 't' => $d['type'], 'o' => $d['options'], 'r' => $d['required'], 'u' => $d['unit'], 'd' => $d['decimals'], 'mx' => $d['max']];
        }, static::forProject($projectId));
    }

    // ---- values ------------------------------------------------------------

    /**
     * The values kept on an issue.
     *
     * @param mixed $raw the column's value (a JSON string)
     * @return array<string, mixed> definition id => value
     */
    public static function decode($raw): array
    {
        if (is_array($raw)) {
            $v = $raw;
        } else {
            $v = json_decode(strval($raw), true);
        }
        if (!is_array($v)) {
            return [];
        }
        $out = [];
        foreach ($v as $k => $value) {
            if (preg_match('/^[1-9][0-9]{0,9}$/', strval($k)) && $value !== null && $value !== '' && $value !== []) {
                $out[strval($k)] = $value;
            }
        }
        return $out;
    }

    /** @param array<string, mixed> $values */
    public static function encode(array $values): string
    {
        $values = static::decode($values);
        if (empty($values)) {
            return '';
        }
        ksort($values, SORT_NUMERIC);
        return strval(json_encode($values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * One value against its definition (no database: the CI checks it).
     *
     * @param array<string, mixed> $d
     * @param mixed $raw
     * @return array{0: mixed, 1: string|null} the value to keep (null: empty), or the rule it breaks
     */
    public static function normalize(array $d, $raw): array
    {
        if ($raw === null || $raw === '' || $raw === []) {
            return [null, null];
        }
        switch ($d['type']) {
            case 'number':
                if (!is_numeric($raw) || is_bool($raw)) {
                    return [null, 'attr_number'];
                }
                $n = round(floatval($raw), $d['decimals']);
                if (abs($n) > 1e12) {
                    return [null, 'attr_number'];
                }
                return [$d['decimals'] === 0 ? intval($n) : $n, null];
            case 'date':
                $s = is_scalar($raw) ? trim(strval($raw)) : '';
                $dt = \DateTimeImmutable::createFromFormat('!Y-m-d', $s);
                if ($dt === false || $dt->format('Y-m-d') !== $s) {
                    return [null, 'attr_date'];
                }
                return [$s, null];
            case 'select':
                $s = is_scalar($raw) ? strval($raw) : '';
                return in_array($s, $d['options'], true) ? [$s, null] : [null, 'attr_choice'];
            case 'multi_select':
                $list = [];
                foreach ((array) $raw as $one) {
                    $s = is_scalar($one) ? strval($one) : '';
                    if (!in_array($s, $d['options'], true)) {
                        return [null, 'attr_choice'];
                    }
                    if (!in_array($s, $list, true)) {
                        $list[] = $s;
                    }
                }
                // in the order of the choices
                usort($list, function ($a, $b) use ($d) {
                    return array_search($a, $d['options'], true) <=> array_search($b, $d['options'], true);
                });
                return [empty($list) ? null : $list, null];
            case 'user':
                $ids = [];
                foreach ((array) $raw as $one) {
                    if (!is_scalar($one) || !preg_match('/^[1-9][0-9]{0,9}$/', strval($one))) {
                        return [null, 'attr_user'];
                    }
                    $ids[] = intval($one);
                }
                $ids = array_values(array_unique($ids));
                return [empty($ids) ? null : $ids, null];
            default:
                $s = is_scalar($raw) ? trim(str_replace(["\r\n", "\r"], "\n", strval($raw))) : '';
                if (mb_strlen($s) > $d['max']) {
                    return [null, 'attr_text_long'];
                }
                return [$s === '' ? null : $s, null];
        }
    }

    /**
     * The values a write asks for, checked against the definitions of the project (no database:
     * the CI checks it). Values of attributes not defined there (deleted, of another project)
     * are kept as they were.
     *
     * @param array<int, array<string, mixed>> $defs every definition of the project, the deleted too
     * @param mixed $asked the column's new value
     * @param mixed $stored the column's value before
     * @param array<int, int>|null $members the project's members (null: not checked)
     * @return array{0: string, 1: array<int, array{0: string, 1: string}>} the JSON to keep, and the errors [label, rule]
     */
    public static function check(array $defs, $asked, $stored, ?array $members): array
    {
        $new = static::decode($asked);
        $old = static::decode($stored);
        $out = $old;
        $errors = [];
        $byId = [];
        foreach ($defs as $d) {
            $byId[strval($d['id'])] = $d;
        }
        foreach ($byId as $id => $d) {
            if ($d['deleted']) {
                continue;
            }
            [$value, $why] = static::normalize($d, $new[$id] ?? null);
            if ($why === null && $d['type'] === 'user' && $members !== null && $value !== null) {
                $added = array_diff($value, array_map('intval', (array) ($old[$id] ?? [])));
                if (!empty(array_diff($added, $members))) {
                    $why = 'attr_user';
                }
            }
            if ($why === null && $value === null && $d['required']) {
                $why = 'attr_required';
            }
            if ($why !== null) {
                $errors[] = [$d['label'], $why];
                continue;
            }
            if ($value === null) {
                unset($out[$id]);
            } else {
                $out[$id] = $value;
            }
        }

        return [static::encode($out), $errors];
    }

    /**
     * A value as a person reads it.
     *
     * @param array<string, mixed> $d
     * @param mixed $value
     * @param array<int, string> $userNames
     */
    public static function text(array $d, $value, array $userNames = []): string
    {
        if ($value === null || $value === '' || $value === []) {
            return '';
        }
        switch ($d['type']) {
            case 'number':
                $s = number_format(floatval($value), $d['decimals']);
                return $d['unit'] !== '' ? $s . ' ' . $d['unit'] : $s;
            case 'multi_select':
                return implode('、', array_map('strval', (array) $value));
            case 'user':
                return implode('、', array_map(function ($id) use ($userNames) {
                    return $userNames[intval($id)] ?? ('#' . intval($id));
                }, (array) $value));
            default:
                return is_scalar($value) ? strval($value) : '';
        }
    }

    /**
     * The values of an issue moved to another project: kept where the other project has an
     * attribute of the same name and kind (and, for a choice, the same choice), dropped else.
     *
     * @param array<int, array<string, mixed>> $from the definitions of the project it leaves (the deleted too)
     * @param array<int, array<string, mixed>> $to the definitions of the project it goes to
     * @param mixed $stored
     * @param array<int, int>|null $members the people of the project it goes to (a user attribute keeps only them)
     * @return array{0: string, 1: array<int, string>} the JSON for the new project, and the labels dropped
     */
    public static function move(array $from, array $to, $stored, ?array $members = null): array
    {
        $values = static::decode($stored);
        $targets = [];
        foreach ($to as $d) {
            if (!$d['deleted']) {
                $targets[mb_strtolower($d['label']) . "\0" . $d['type']] = $d;
            }
        }
        $out = [];
        $dropped = [];
        foreach ($from as $d) {
            $id = strval($d['id']);
            if (!array_key_exists($id, $values)) {
                continue;
            }
            $t = $targets[mb_strtolower($d['label']) . "\0" . $d['type']] ?? null;
            [$value, $why] = $t === null ? [null, 'none'] : static::normalize($t, $values[$id]);
            if ($t !== null && $why === null && $t['type'] === 'user' && $members !== null && $value !== null) {
                $value = array_values(array_intersect($value, $members));
                $value = empty($value) ? null : $value;
            }
            if ($t === null || $why !== null || $value === null) {
                $dropped[] = $d['label'];
                continue;
            }
            $out[strval($t['id'])] = $value;
        }

        return [static::encode($out), $dropped];
    }

    /**
     * Definitions by their ids, of any project (the history of an issue names them).
     *
     * @param array<int, int|string> $ids
     * @return array<string, array<string, mixed>>
     */
    public static function byIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (empty($ids) || !static::exists()) {
            return [];
        }
        $out = [];
        foreach (DB::table(static::TABLE)->whereIn('id', $ids)->get() as $row) {
            $out[strval($row->id)] = static::fromRow($row);
        }
        return $out;
    }

    /** Whether a project uses custom attributes (its switch). */
    public static function projectOn($project): bool
    {
        $column = strval(array_get(PortalDefinition::config(), 'project_extra.use_attributes', 'use_attributes'));

        return static::exists() && isset($project) && boolval(intval(array_get((array) $project->value, $column, 0)));
    }

    // ---- the definitions ---------------------------------------------------

    /**
     * Add or change a definition.
     *
     * @param array<string, mixed> $input
     * @return array{0: int|null, 1: string|null} the id, or why it was refused
     */
    public static function save(int $projectId, array $input, int $userId): array
    {
        $id = intval($input['id'] ?? 0);
        $all = static::forProject($projectId, true);
        $old = null;
        foreach ($all as $d) {
            if ($d['id'] === $id) {
                $old = $d;
            }
        }
        if ($id > 0 && $old === null) {
            return [null, 'not_found'];
        }
        $d = static::definition([
            'type' => $old['type'] ?? strval($input['type'] ?? ''),
        ] + $input);
        if ($d['label'] === '') {
            return [null, 'label'];
        }
        foreach ($all as $other) {
            if (!$other['deleted'] && $other['id'] !== $id && mb_strtolower($other['label']) === mb_strtolower($d['label'])) {
                return [null, 'label_taken'];
            }
        }
        if (in_array($d['type'], ['select', 'multi_select'], true) && empty($d['options'])) {
            return [null, 'options'];
        }
        if ($id === 0 && count(static::forProject($projectId)) >= static::MAX_PER_PROJECT) {
            return [null, 'too_many'];
        }
        $values = [
            'label' => $d['label'],
            'options' => json_encode(['choices' => $d['options'], 'unit' => $d['unit'], 'decimals' => $d['decimals'], 'max' => $d['max']], JSON_UNESCAPED_UNICODE),
            'required' => $d['required'] ? 1 : 0,
            'updated_user_id' => $userId,
            'updated_at' => now(),
        ];
        if ($old === null) {
            $sort = 1 + intval(DB::table(static::TABLE)->where('project_id', $projectId)->max('sort'));
            $id = intval(DB::table(static::TABLE)->insertGetId($values + [
                'project_id' => $projectId, 'type' => $d['type'], 'sort' => $sort,
                'created_user_id' => $userId, 'created_at' => now(),
            ]));
        } else {
            DB::table(static::TABLE)->where('id', $id)->update($values);
        }
        static::forget();
        Log::info('pm_portal: attribute ' . $id . ' of project ' . $projectId . ' saved by user ' . $userId);

        return [$id, null];
    }

    /** Mark a definition deleted, or bring it back. Its values stay on the issues. */
    public static function setDeleted(int $projectId, int $id, bool $deleted, int $userId): bool
    {
        $n = DB::table(static::TABLE)->where('project_id', $projectId)->where('id', $id)
            ->update(['deleted_at' => $deleted ? now() : null, 'updated_user_id' => $userId, 'updated_at' => now()]);
        static::forget();
        if ($n > 0) {
            Log::info('pm_portal: attribute ' . $id . ' of project ' . $projectId . ($deleted ? ' deleted' : ' restored') . ' by user ' . $userId);
        }
        return $n > 0;
    }

    /** @param array<int, int> $ids the definitions in their new order */
    public static function reorder(int $projectId, array $ids): void
    {
        $n = 0;
        foreach ($ids as $id) {
            DB::table(static::TABLE)->where('project_id', $projectId)->where('id', intval($id))->update(['sort' => ++$n]);
        }
        static::forget();
    }

    /**
     * The state of the settings pane.
     *
     * @return array<string, mixed>
     */
    public static function state(int $projectId): array
    {
        $defs = static::forProject($projectId, true);

        return [
            'on' => static::exists(),
            'types' => static::TYPES,
            'max' => static::MAX_PER_PROJECT,
            'defs' => array_map(function ($d) {
                return ['id' => $d['id'], 'l' => $d['label'], 't' => $d['type'], 'o' => $d['options'], 'r' => $d['required'], 'u' => $d['unit'], 'd' => $d['decimals'], 'mx' => $d['max'], 'del' => $d['deleted']];
            }, $defs),
        ];
    }
}
