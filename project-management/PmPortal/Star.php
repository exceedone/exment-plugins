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
    /**
     * "This user has opened this issue", kept by the time of the last opening
     * (updated_at). Read receipts and the list's unread mark come from it.
     */
    public const TYPE_READ = 'read';

    protected $table = 'exment_stars';

    /**
     * Reactions (ISS00259): one row per user, kind and target, stored as
     * star_type "r:<kind>" (the column holds 10 characters). Several kinds
     * per user, the way Slack and Teams do it: 👀 when you start reading,
     * ✅ when it is done.
     */
    public const REACTION_PREFIX = 'r:';

    /** The kinds, in the order the picker shows them. */
    public const REACTIONS = ['like', 'heart', 'haha', 'wow', 'sad', 'angry', 'thanks', 'clap', 'party', 'eyes', 'ok'];

    /**
     * Whether a star_type is one of the reactions.
     */
    public static function isReaction(string $star_type): bool
    {
        return strpos($star_type, self::REACTION_PREFIX) === 0
            && in_array(substr($star_type, strlen(self::REACTION_PREFIX)), self::REACTIONS, true);
    }

    /**
     * Who reacted with what, over many targets at once:
     * target id => kind => user ids, in the order they reacted.
     *
     * @param string $target_type
     * @param array<int, mixed> $target_ids
     * @return array<string, array<string, array<int, int>>>
     */
    public static function reactions(string $target_type, array $target_ids): array
    {
        if (empty($target_ids)) {
            return [];
        }
        $rows = static::where('star_type', 'like', self::REACTION_PREFIX . '%')
            ->where('target_type', $target_type)
            ->whereIn('target_id', array_map('strval', $target_ids))
            ->orderBy('id')
            ->get(['star_type', 'target_id', 'user_id']);

        $out = [];
        foreach ($rows as $row) {
            if (!static::isReaction(strval($row->star_type))) {
                continue;
            }
            $kind = substr(strval($row->star_type), strlen(self::REACTION_PREFIX));
            $out[strval($row->target_id)][$kind][] = intval($row->user_id);
        }

        return $out;
    }

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

    /**
     * Note that the user has read the target now, or mark it unread again.
     *
     * One row per user and target: reading again moves its time on. "Unread"
     * keeps the row with no time, the way a mail client keeps a message it
     * was told to show as new - the list then shows it unread however old
     * its last change is.
     *
     * @param string $target_type
     * @param mixed $target_id
     * @param mixed $user_id
     * @param bool $read
     * @return void
     */
    public static function markRead(string $target_type, $target_id, $user_id, bool $read = true): void
    {
        $query = static::target(self::TYPE_READ, $target_type, $target_id)->where('user_id', $user_id);
        $at = $read ? \Carbon\Carbon::now() : null;
        if ($query->update(['updated_at' => $at]) > 0) {
            return;
        }
        try {
            $row = new static();
            $row->star_type = self::TYPE_READ;
            $row->target_type = $target_type;
            $row->target_id = strval($target_id);
            $row->user_id = $user_id;
            $row->save();
            if (!$read) {
                static::target(self::TYPE_READ, $target_type, $target_id)->where('user_id', $user_id)->update(['updated_at' => null]);
            }
        } catch (\Illuminate\Database\QueryException $e) {
            // Two tabs opening the same issue at once: the other one wrote it.
            static::target(self::TYPE_READ, $target_type, $target_id)->where('user_id', $user_id)->update(['updated_at' => $at]);
        }
    }

    /**
     * Mark many targets read now for one user ("mark all read"): one update
     * for the rows already there and one insert per chunk for the rest,
     * instead of two queries per target.
     *
     * @param string $target_type
     * @param array<int, mixed> $target_ids
     * @param mixed $user_id
     * @return int how many targets were marked
     */
    public static function markReadMany(string $target_type, array $target_ids, $user_id): int
    {
        $ids = array_values(array_unique(array_map('strval', $target_ids)));
        $now = \Carbon\Carbon::now();
        foreach (array_chunk($ids, 500) as $chunk) {
            $base = static::where('star_type', self::TYPE_READ)
                ->where('target_type', $target_type)
                ->where('user_id', $user_id)
                ->whereIn('target_id', $chunk);
            $have = (clone $base)->pluck('target_id')->map(function ($v) {
                return strval($v);
            })->all();
            (clone $base)->update(['updated_at' => $now]);
            $missing = array_values(array_diff($chunk, $have));
            if (empty($missing)) {
                continue;
            }
            $rows = array_map(function ($id) use ($target_type, $user_id, $now) {
                return [
                    'star_type' => self::TYPE_READ,
                    'target_type' => $target_type,
                    'target_id' => $id,
                    'user_id' => $user_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }, $missing);
            try {
                static::insert($rows);
            } catch (\Illuminate\Database\QueryException $e) {
                // Another tab opened one of them meanwhile: go one by one.
                foreach ($missing as $id) {
                    static::markRead($target_type, $id, $user_id);
                }
            }
        }

        return count($ids);
    }

    /**
     * target_id => when this user last read it, over many targets at once;
     * '' for one marked unread by hand, nothing for one never opened.
     *
     * @param string $target_type
     * @param array<int, mixed> $target_ids
     * @param mixed $user_id
     * @return array<string, string>
     */
    public static function readTimes(string $target_type, array $target_ids, $user_id): array
    {
        if (empty($target_ids)) {
            return [];
        }

        return static::where('star_type', self::TYPE_READ)
            ->where('target_type', $target_type)
            ->where('user_id', $user_id)
            ->whereIn('target_id', array_map('strval', $target_ids))
            ->pluck('updated_at', 'target_id')
            ->map(function ($v) {
                // '' = marked unread by hand
                return $v === null ? '' : strval($v);
            })
            ->toArray();
    }

    /**
     * Who read which target and when, over many targets at once.
     *
     * @param string $target_type
     * @param array<int, mixed> $target_ids
     * @return array<string, array<int, array{by: int, ts: string}>> target_id => readers, latest first
     */
    public static function readers(string $target_type, array $target_ids): array
    {
        if (empty($target_ids)) {
            return [];
        }
        $out = [];
        $rows = static::where('star_type', self::TYPE_READ)
            ->where('target_type', $target_type)
            ->whereIn('target_id', array_map('strval', $target_ids))
            ->whereNotNull('updated_at')
            ->orderBy('updated_at', 'desc')
            ->get(['target_id', 'user_id', 'updated_at']);
        foreach ($rows as $row) {
            $out[strval($row->target_id)][] = ['by' => intval($row->user_id), 'ts' => strval($row->updated_at)];
        }

        return $out;
    }
}
