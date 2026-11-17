<?php

namespace App\Plugins\PmPortal;

use Exceedone\Exment\Model\ModelBase;

/**
 * A star (いいね) or a watch, put by one user on one thing.
 *
 * The thing is named the same way comments name theirs: target_type is a
 * table name ("pm_issue", "pm_wiki"...) or "comment", target_id the row id.
 *
 * @property mixed $star_type
 * @property mixed $target_type
 * @property mixed $target_id
 * @property mixed $user_id
 * @phpstan-consistent-constructor
 */
class Star extends ModelBase
{
    public const TYPE_STAR = 'star';
    public const TYPE_WATCH = 'watch';

    protected $table = 'exment_stars';

    /**
     * Base query for one target.
     *
     * @param string $star_type
     * @param string $target_type
     * @param mixed $target_id
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected static function target(string $star_type, string $target_type, $target_id)
    {
        return static::where('star_type', $star_type)
            ->where('target_type', $target_type)
            ->where('target_id', strval($target_id));
    }

    /**
     * Flip a star / watch of one user, and answer the new state.
     *
     * @param string $star_type
     * @param string $target_type
     * @param mixed $target_id
     * @param mixed $user_id
     * @return array{on: bool, count: int}
     */
    public static function toggle(string $star_type, string $target_type, $target_id, $user_id): array
    {
        $query = static::target($star_type, $target_type, $target_id)->where('user_id', $user_id);
        if ($query->exists()) {
            $query->delete();
            $on = false;
        } else {
            static::create([
                'star_type' => $star_type,
                'target_type' => $target_type,
                'target_id' => strval($target_id),
                'user_id' => $user_id,
            ]);
            $on = true;
        }

        return [
            'on' => $on,
            'count' => static::target($star_type, $target_type, $target_id)->count(),
        ];
    }

    /**
     * Drop every star and watch on one target.
     *
     * Called when the target itself goes away: a deleted comment leaves its
     * stars behind otherwise, and they are counted by target id, so the next
     * comment to take that id would inherit them.
     *
     * @param string $target_type
     * @param mixed $target_id
     * @return int
     */
    public static function purge(string $target_type, $target_id): int
    {
        return static::where('target_type', $target_type)
            ->where('target_id', strval($target_id))
            ->delete();
    }

    /**
     * target_id => count over many targets at once.
     *
     * @param string $star_type
     * @param string $target_type
     * @param array<int, mixed> $target_ids
     * @return array<string, int>
     */
    public static function counts(string $star_type, string $target_type, array $target_ids): array
    {
        if (empty($target_ids)) {
            return [];
        }

        return static::where('star_type', $star_type)
            ->where('target_type', $target_type)
            ->whereIn('target_id', array_map('strval', $target_ids))
            ->selectRaw('target_id, count(*) as cnt')
            ->groupBy('target_id')
            ->pluck('cnt', 'target_id')
            ->map(function ($v) {
                return intval($v);
            })
            ->toArray();
    }

    /**
     * The target ids, out of the given ones, this user has marked.
     *
     * @param string $star_type
     * @param string $target_type
     * @param array<int, mixed> $target_ids
     * @param mixed $user_id
     * @return array<int, string>
     */
    public static function mine(string $star_type, string $target_type, array $target_ids, $user_id): array
    {
        if (empty($target_ids)) {
            return [];
        }

        return static::where('star_type', $star_type)
            ->where('target_type', $target_type)
            ->where('user_id', $user_id)
            ->whereIn('target_id', array_map('strval', $target_ids))
            ->pluck('target_id')
            ->toArray();
    }

    /**
     * Everyone who marked one target - the watchers a change notifies.
     *
     * @param string $star_type
     * @param string $target_type
     * @param mixed $target_id
     * @return array<int, int>
     */
    public static function userIds(string $star_type, string $target_type, $target_id): array
    {
        return static::target($star_type, $target_type, $target_id)
            ->pluck('user_id')
            ->map(function ($v) {
                return intval($v);
            })
            ->toArray();
    }
}
