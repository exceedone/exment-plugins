<?php

namespace App\Plugins\ExmentPM;

/**
 * The paging of 「最近の更新」 (UX S15, M5), on its own so it can be tested
 * without a database: how one source is read (scan) and how the sources are
 * cut into one page (cut). Used by PortalFeedTrait.
 */
class PortalFeedPage
{
    /**
     * Newest first, $limit of them, each with its shown time. more: there
     * are older ones; next: the time to ask for them with (?before=).
     * $floor: a source stopped there (feedScan) - nothing older is shown.
     *
     * @param array<int, array<string, mixed>> $feed
     * @return array{feed: array<int, array<string, mixed>>, more: bool, next: string}
     */
    public static function cut(array $feed, int $limit, ?string $floor, callable $fmt): array
    {
        if ($floor !== null) {
            $feed = array_values(array_filter($feed, function ($r) use ($floor) {
                return strcmp(strval($r['ts']), $floor) >= 0;
            }));
        }
        usort($feed, function ($a, $b) {
            return strcmp(strval($b['ts']), strval($a['ts']));
        });
        $next = '';
        if (count($feed) > $limit) {
            // Cut by the limit, after the whole of the last second shown: the
            // next page reads strictly before it.
            $n = $limit;
            $last = strval($feed[$n - 1]['ts']);
            while ($n < count($feed) && strval($feed[$n]['ts']) === $last) {
                $n++;
            }
            $rows = array_slice($feed, 0, $n);
            $next = $n < count($feed) ? $last : strval($floor ?? '');
        } else {
            // All there is above the floor; below it, the next page.
            $rows = $feed;
            $next = strval($floor ?? '');
        }
        foreach ($rows as &$row) {
            $row['at'] = $fmt($row['ts']);
        }
        unset($row);

        return ['feed' => $rows, 'more' => $next !== '', 'next' => $next];
    }

    /**
     * Rows of one kind, newest first, a page at a time, keeping those whose
     * $idCol is in $ids, until $want are kept or the pages run out. When it
     * stops before the end (enough kept, or the page limit) $floor is raised
     * to the time of the last row it read or kept: older rows of the other
     * sources are left for the next page, so none of this one's is skipped.
     *
     * @param array<int, int> $ids flipped: id => index
     * @return array<int, object>
     */
    public static function scan(callable $page, array $ids, string $idCol, int $want, ?string &$floor, int $size = 200, int $pages = 5): array
    {
        $out = [];
        $last = null;
        // Once enough are kept, or the page limit is reached, the rest of
        // that same second is still read: the next page reads strictly
        // before it, so a second must never be left half read (a bulk edit
        // writes many lines in one second).
        $stopAt = null;
        for ($n = 0; $n < $pages + 20; $n++) {
            if ($stopAt === null && $n >= $pages) {
                if ($last === null) {
                    break;
                }
                $stopAt = $last;
            }
            $rows = $page($n * $size, $size);
            foreach ($rows as $row) {
                $at = strval($row->created_at);
                if ($stopAt !== null && $at !== $stopAt) {
                    if ($floor === null || strcmp($stopAt, $floor) > 0) {
                        $floor = $stopAt;
                    }
                    return $out;
                }
                $last = $at;
                if (isset($ids[intval($row->{$idCol})])) {
                    $out[] = $row;
                    if ($stopAt === null && count($out) >= $want) {
                        $stopAt = $at;
                    }
                }
            }
            if (count($rows) < $size) {
                // read to the end: nothing older is left unread
                return $out;
            }
        }
        if ($last !== null && ($floor === null || strcmp($last, $floor) > 0)) {
            $floor = $last;
        }

        return $out;
    }
}
