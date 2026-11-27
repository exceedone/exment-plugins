<?php

namespace App\Plugins\ExmentGroupware\Chat;

/**
 * The parts of the chat with no database (ISS02945): tools/test-chat-text.php runs them.
 */
class ChatText
{
    const MAX_BODY = 20000;
    const MAX_NAME = 100;

    /** The reactions one may give (a fixed list: nothing else is kept). */
    const REACTIONS = ['👍', '🙏', '👀', '🎉', '😄', '✅'];

    public static function reaction($emoji): ?string
    {
        $e = strval($emoji ?? '');

        return in_array($e, static::REACTIONS, true) ? $e : null;
    }

    /** The line a reply shows of the message it answers: one line, at most 80. */
    public static function quote(string $body): string
    {
        $s = trim(preg_replace('/\s+/u', ' ', $body) ?? '');

        return mb_strlen($s) > 80 ? mb_substr($s, 0, 80) . '…' : $s;
    }

    /** The one direct room of two people: the same key whichever opens it. */
    public static function directKey(int $a, int $b): string
    {
        return min($a, $b) . ':' . max($a, $b);
    }

    /** A message's text as it is kept: no control characters but line breaks and tabs, at most 20,000. */
    public static function clean($body): string
    {
        $s = str_replace(["\r\n", "\r"], "\n", strval($body ?? ''));
        $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s) ?? '';

        return mb_substr(trim($s), 0, static::MAX_BODY);
    }

    public static function cleanName($name): string
    {
        $s = trim(preg_replace('/[\x00-\x1F\x7F]/u', '', strval($name ?? '')) ?? '');

        return mb_substr($s, 0, static::MAX_NAME);
    }

    /**
     * Who a message names: "@名前" of a member (the longest name first, so 「山田 太郎」 wins over
     * 「山田」), and "@全員" / "@all" for everyone in a group room.
     *
     * @param array<int, string> $members user id => name
     * @return array<int, int|string> user ids, and "all"
     */
    public static function mentions(string $body, array $members, bool $group): array
    {
        $out = [];
        if ($group && preg_match('/(^|[\s　])@(全員|all)(?=$|[\s　、。,.!?！？])/u', $body)) {
            $out[] = 'all';
        }
        uasort($members, function ($x, $y) {
            return mb_strlen($y) <=> mb_strlen($x);
        });
        $rest = $body;
        foreach ($members as $id => $name) {
            $name = trim(strval($name));
            if ($name === '') {
                continue;
            }
            $pattern = '/(^|[\s　])@' . preg_quote($name, '/') . '(?=$|[\s　、。,.:：!?！？])/u';
            if (preg_match($pattern, $rest)) {
                $out[] = intval($id);
                $rest = preg_replace($pattern, '$1', $rest) ?? $rest;
            }
        }

        return $out;
    }

    /**
     * How many have read a message: the members, not its writer, whose last read reaches it.
     *
     * @param array<int, int> $lastReads member user id => last_read_id
     */
    public static function readCount(array $lastReads, int $messageId, ?int $authorId): int
    {
        $n = 0;
        foreach ($lastReads as $uid => $last) {
            if ($authorId !== null && intval($uid) === $authorId) {
                continue;
            }
            if (intval($last) >= $messageId) {
                $n++;
            }
        }

        return $n;
    }

    /**
     * How long to wait before the next poll, in seconds: 3 while seen, 30 while hidden, none
     * (null) once nothing was done for 30 minutes (the page polls again when the person comes back).
     */
    public static function pollDelay(bool $visible, int $idleSeconds): ?int
    {
        if ($idleSeconds >= 1800) {
            return null;
        }

        return $visible ? 3 : 30;
    }
}
