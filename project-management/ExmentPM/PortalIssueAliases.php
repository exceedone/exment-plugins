<?php

namespace App\Plugins\ExmentPM;

use Illuminate\Support\Facades\DB;

/**
 * Other names of an issue (ISS00248): its alias ("EXMENT_AI_P1.1"), which the list shows beside
 * the key and sorts by, and the keys it had before it was moved to another project. The key of an
 * issue never changes; an alias is one more name that finds it - in an address
 * (#issue-<alias>, /view/<alias>) and in the search.
 *
 * pm_portal_issue_aliases: one row per name, unique across every project whatever the case
 * (alias_lower). kind "name" is the alias (one per issue); kind "old" an earlier key.
 *
 * An alias is letters, digits and the four marks an address keeps as they are (- . _ ~), with
 * a letter in it (a number alone is an issue's id), at most 60.
 */
class PortalIssueAliases
{
    const TABLE = 'pm_portal_issue_aliases';
    const NAME = 'name';
    const OLD = 'old';
    const PATTERN = '/^(?=.*[A-Za-z])[A-Za-z0-9._~-]{1,60}$/';

    public static function exists(): bool
    {
        return \Schema::hasTable(static::TABLE);
    }

    public static function valid(string $alias): bool
    {
        return preg_match(static::PATTERN, $alias) === 1;
    }

    /** @return array<int, string> issue id => its alias, for some issues */
    public static function names(array $issueIds): array
    {
        $out = [];
        if (empty($issueIds) || !static::exists()) {
            return $out;
        }
        foreach (array_chunk(array_values(array_unique(array_map('intval', $issueIds))), 500) as $chunk) {
            foreach (DB::table(static::TABLE)->where('kind', static::NAME)->whereIn('issue_id', $chunk)->get(['issue_id', 'alias']) as $r) {
                $out[intval($r->issue_id)] = strval($r->alias);
            }
        }

        return $out;
    }

    /** The issue a name (an alias or an old key, either case) is of, or null. */
    public static function issueOf(string $name): ?int
    {
        if ($name === '' || strlen($name) > 200 || !static::exists()) {
            return null;
        }
        $id = DB::table(static::TABLE)->where('alias_lower', mb_strtolower($name))->value('issue_id');

        return $id === null ? null : intval($id);
    }

    /** @return array<int, int> the issues whose alias contains a word (the search) */
    public static function search(string $word, int $limit = 50): array
    {
        if ($word === '' || !static::exists()) {
            return [];
        }
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower($word)) . '%';

        return DB::table(static::TABLE)->where('alias_lower', 'like', $like)->limit($limit)->pluck('issue_id')->map(function ($v) {
            return intval($v);
        })->unique()->values()->all();
    }

    /**
     * Set the alias of an issue ('' takes it off). Returns null when it is set, or the key of
     * the message why not: not_valid, taken (another issue's name).
     */
    public static function setName(int $issueId, string $alias, int $userId): ?string
    {
        $alias = trim($alias);
        if ($alias === '') {
            DB::table(static::TABLE)->where('issue_id', $issueId)->where('kind', static::NAME)->delete();

            return null;
        }
        if (!static::valid($alias)) {
            return 'alias_not_valid';
        }
        $other = static::issueOf($alias);
        if ($other !== null && $other !== $issueId) {
            return 'alias_taken';
        }
        $now = now();
        DB::transaction(function () use ($issueId, $alias, $userId, $now) {
            DB::table(static::TABLE)->where('issue_id', $issueId)->where('kind', static::NAME)->delete();
            // the same name as an old key of this issue becomes its alias
            DB::table(static::TABLE)->where('alias_lower', mb_strtolower($alias))->delete();
            DB::table(static::TABLE)->insert([
                'issue_id' => $issueId, 'alias' => $alias, 'alias_lower' => mb_strtolower($alias), 'kind' => static::NAME,
                'updated_user_id' => $userId, 'created_at' => $now, 'updated_at' => $now,
            ]);
        });

        return null;
    }

    /** Keep a key an issue had (before a move), unless the name is somebody's already. */
    public static function addOld(int $issueId, string $key): void
    {
        $key = trim($key);
        if ($key === '' || strlen($key) > 60 || !static::exists() || static::issueOf($key) !== null) {
            return;
        }
        $now = now();
        DB::table(static::TABLE)->insertOrIgnore([
            'issue_id' => $issueId, 'alias' => $key, 'alias_lower' => mb_strtolower($key), 'kind' => static::OLD,
            'updated_user_id' => null, 'created_at' => $now, 'updated_at' => $now,
        ]);
    }
}
