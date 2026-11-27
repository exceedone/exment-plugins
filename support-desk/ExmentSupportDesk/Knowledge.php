<?php

namespace App\Plugins\ExmentITSupport;

use Exceedone\Exment\Model\CustomValue;

/**
 * Finding the answer that already exists.
 *
 * The same search serves three places: the request form (suggest an article
 * before the customer sends - fewer tickets), the reply box (suggest an
 * article to the agent - faster answers) and the FAQ page. Partial match on
 * every word, all words required, ranked by where they matched; reviews of
 * the market leaders complain exactly about search that needs the whole
 * word.
 */
class Knowledge
{
    /**
     * @return array<int, array{id: int, title: string, snippet: string, public: bool, score: int}>
     */
    public static function search(string $q, bool $publicOnly, int $limit = 5): array
    {
        $table = Its::table(Its::T_KNOWLEDGE);
        $words = static::words($q);
        if (!isset($table) || empty($words)) {
            return [];
        }

        $query = $table->getValueModel()->query()->withoutGlobalScope(\Exceedone\Exment\Model\CustomValueModelScope::class);
        if ($publicOnly) {
            $query->where('value->public', '1');
        }
        // Any word may match; the ranking below prefers rows that match all.
        $query->where(function ($w) use ($words) {
            foreach ($words as $word) {
                $like = '%' . static::escapeLike($word) . '%';
                $w->orWhere('value->title', 'like', $like)
                    ->orWhere('value->keywords', 'like', $like)
                    ->orWhere('value->body', 'like', $like);
            }
        });

        $rows = [];
        foreach ($query->limit(200)->get() as $row) {
            $title = mb_strtolower(strval(array_get($row->value, 'title')));
            $keys = mb_strtolower(strval(array_get($row->value, 'keywords')));
            $body = mb_strtolower(strval(array_get($row->value, 'body')));
            $score = 0;
            $hits = 0;
            foreach ($words as $word) {
                $hit = false;
                if (mb_strpos($title, $word) !== false) {
                    $score += 6;
                    $hit = true;
                }
                if (mb_strpos($keys, $word) !== false) {
                    $score += 4;
                    $hit = true;
                }
                if (mb_strpos($body, $word) !== false) {
                    $score += 1;
                    $hit = true;
                }
                $hits += $hit ? 1 : 0;
            }
            if ($hits === 0) {
                continue;
            }
            $score += $hits === count($words) ? 10 : 0;
            $score += min(5, intval(array_get($row->value, 'helpful', 0)) - intval(array_get($row->value, 'not_helpful', 0)));
            $rows[] = [
                'id' => intval($row->id),
                'title' => strval(array_get($row->value, 'title')),
                'snippet' => static::snippet(strval(array_get($row->value, 'body')), $words),
                'public' => boolval(array_get($row->value, 'public')),
                'score' => $score,
            ];
        }
        usort($rows, function ($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        return array_slice($rows, 0, $limit);
    }

    /**
     * Words of the query. Japanese has no spaces, so a long run of text is
     * also cut into overlapping pieces of three characters - crude, but it
     * makes "プリンターが印刷できない" find "プリンターで印刷できないとき".
     *
     * @return array<int, string>
     */
    public static function words(string $q): array
    {
        $q = mb_strtolower(trim(mb_convert_kana($q, 'asKV')));
        $q = preg_replace('/[、。，．！？!?「」『』（）()\[\]・:：;；"\'\/\\\\]+/u', ' ', $q) ?? $q;
        $out = [];
        foreach (preg_split('/\s+/u', $q) as $w) {
            if ($w === '') {
                continue;
            }
            if (mb_strlen($w) <= 4 || preg_match('/^[a-z0-9._@-]+$/', $w)) {
                $out[] = $w;
                continue;
            }
            // Split at particles first, then n-grams for long runs.
            foreach (preg_split('/(?:が|を|に|で|は|の|と|も|から|まで|ない|です|ます|ません|したい|できない)/u', $w) as $part) {
                if (mb_strlen($part) < 2) {
                    continue;
                }
                if (mb_strlen($part) <= 5) {
                    $out[] = $part;
                    continue;
                }
                for ($i = 0; $i + 3 <= mb_strlen($part); $i += 2) {
                    $out[] = mb_substr($part, $i, 3);
                }
            }
        }

        return array_slice(array_values(array_unique($out)), 0, 12);
    }

    protected static function snippet(string $body, array $words): string
    {
        $flat = preg_replace('/\s+/u', ' ', $body) ?? $body;
        $pos = 0;
        foreach ($words as $w) {
            $p = mb_stripos($flat, $w);
            if ($p !== false) {
                $pos = max(0, $p - 20);
                break;
            }
        }
        $s = mb_substr($flat, $pos, 90);

        return ($pos > 0 ? '…' : '') . $s . (mb_strlen($flat) > $pos + 90 ? '…' : '');
    }

    protected static function escapeLike(string $s): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $s);
    }

    public static function find($id, bool $publicOnly): ?CustomValue
    {
        $table = Its::table(Its::T_KNOWLEDGE);
        if (!isset($table) || !is_numeric($id)) {
            return null;
        }
        $q = $table->getValueModel()->query()->withoutGlobalScope(\Exceedone\Exment\Model\CustomValueModelScope::class);
        if ($publicOnly) {
            $q->where('value->public', '1');
        }

        return $q->find(intval($id));
    }

    /**
     * @return array<int, CustomValue>
     */
    public static function popular(int $limit = 10): array
    {
        $table = Its::table(Its::T_KNOWLEDGE);
        if (!isset($table)) {
            return [];
        }

        // Sorted here rather than in SQL: Exment runs on MySQL, MariaDB and
        // SQL Server, which disagree on how to cast a JSON value.
        return $table->getValueModel()->query()->withoutGlobalScope(\Exceedone\Exment\Model\CustomValueModelScope::class)->where('value->public', '1')
            ->orderByDesc('id')->limit(300)->get()
            ->sortByDesc(function ($row) {
                return intval(array_get($row->value, 'views', 0)) * 2 + intval(array_get($row->value, 'helpful', 0)) * 5;
            })->take($limit)->values()->all();
    }

    /** Count up a number column without going through the save rules. */
    public static function bump(CustomValue $row, string $column): void
    {
        $row->setValue([$column => intval(array_get($row->value, $column, 0)) + 1]);
        $row->save();
    }
}
