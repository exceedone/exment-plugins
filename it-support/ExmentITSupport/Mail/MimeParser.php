<?php

namespace App\Plugins\ExmentITSupport\Mail;

/**
 * Just enough MIME to turn a customer's mail into a ticket.
 *
 * No extension is needed (ext-imap and ext-mailparse are missing on most
 * hosts, and ext-imap is gone from PHP 8.4). It reads what Japanese mail
 * actually looks like: ISO-2022-JP and Shift_JIS bodies, RFC 2047 encoded
 * subjects and names, RFC 2231 encoded file names, nested multipart.
 */
class MimeParser
{
    /**
     * @return array{
     *   headers: array<string, array<int, string>>,
     *   subject: string, from: string, from_name: string, to: array<int, string>, cc: array<int, string>,
     *   message_id: string, in_reply_to: string, references: array<int, string>, date: ?string,
     *   text: string, html: string, attachments: array<int, array{name: string, type: string, content: string}>
     * }
     */
    public static function parse(string $raw): array
    {
        [$headers, $body] = static::split($raw);
        $mail = [
            'headers' => $headers,
            'subject' => static::decodeHeader(static::first($headers, 'subject')),
            'from' => '',
            'from_name' => '',
            'to' => static::addresses(static::first($headers, 'to')),
            'cc' => static::addresses(static::first($headers, 'cc')),
            'message_id' => static::messageId(static::first($headers, 'message-id')),
            'in_reply_to' => static::messageId(static::first($headers, 'in-reply-to')),
            'references' => static::messageIds(static::first($headers, 'references')),
            'date' => null,
            'text' => '',
            'html' => '',
            'attachments' => [],
        ];

        $from = static::first($headers, 'reply-to') !== '' && static::first($headers, 'from') === ''
            ? static::first($headers, 'reply-to') : static::first($headers, 'from');
        [$mail['from'], $mail['from_name']] = static::address($from);

        $date = static::first($headers, 'date');
        if ($date !== '' && ($ts = strtotime($date)) !== false) {
            $mail['date'] = date('Y-m-d H:i:s', $ts);
        }

        static::walk($headers, $body, $mail);

        if ($mail['text'] === '' && $mail['html'] !== '') {
            $mail['text'] = static::htmlToText($mail['html']);
        }
        $mail['text'] = str_replace(["\r\n", "\r"], "\n", $mail['text']);

        return $mail;
    }

    /**
     * @return array{0: array<string, array<int, string>>, 1: string}
     */
    public static function split(string $raw): array
    {
        $raw = str_replace("\r\n", "\n", $raw);
        $pos = strpos($raw, "\n\n");
        $head = $pos === false ? $raw : substr($raw, 0, $pos);
        $body = $pos === false ? '' : substr($raw, $pos + 2);

        // Unfold continuation lines.
        $head = preg_replace("/\n[ \t]+/", ' ', $head);
        $headers = [];
        foreach (explode("\n", strval($head)) as $line) {
            $c = strpos($line, ':');
            if ($c === false) {
                continue;
            }
            $name = strtolower(trim(substr($line, 0, $c)));
            $headers[$name][] = trim(substr($line, $c + 1));
        }

        return [$headers, $body];
    }

    public static function first(array $headers, string $name): string
    {
        return strval($headers[$name][0] ?? '');
    }

    /**
     * @param array<string, array<int, string>> $headers
     * @param array<string, mixed> $mail
     */
    protected static function walk(array $headers, string $body, array &$mail, int $depth = 0): void
    {
        if ($depth > 10) {
            return;
        }
        [$type, $params] = static::contentType(static::first($headers, 'content-type'));
        $disposition = strtolower(static::first($headers, 'content-disposition'));

        if (strpos($type, 'multipart/') === 0 && !empty($params['boundary'])) {
            foreach (static::parts($body, $params['boundary']) as $part) {
                [$h, $b] = static::split($part);
                static::walk($h, $b, $mail, $depth + 1);
            }

            return;
        }

        if ($type === 'message/rfc822') {
            $mail['attachments'][] = ['name' => 'forwarded.eml', 'type' => $type, 'content' => $body];

            return;
        }

        $content = static::decodeBody($body, strtolower(static::first($headers, 'content-transfer-encoding')));
        $name = static::fileName($headers, $params);
        $isAttachment = strpos($disposition, 'attachment') === 0 || ($name !== '' && strpos($type, 'text/') !== 0);

        if (!$isAttachment && $type === 'text/plain' && $mail['text'] === '') {
            $mail['text'] = static::toUtf8($content, $params['charset'] ?? '');

            return;
        }
        if (!$isAttachment && $type === 'text/html' && $mail['html'] === '') {
            $mail['html'] = static::toUtf8($content, $params['charset'] ?? '');

            return;
        }
        if ($name === '' && !$isAttachment) {
            return;
        }
        $mail['attachments'][] = [
            'name' => $name !== '' ? $name : 'attachment-' . (count($mail['attachments']) + 1),
            'type' => $type ?: 'application/octet-stream',
            'content' => $content,
        ];
    }

    /**
     * @return array<int, string>
     */
    protected static function parts(string $body, string $boundary): array
    {
        $out = [];
        $chunks = explode("--" . $boundary, $body);
        array_shift($chunks); // preamble
        foreach ($chunks as $chunk) {
            if (strpos($chunk, '--') === 0) {
                break; // closing delimiter
            }
            // Drop the rest of the delimiter line.
            $out[] = preg_replace('/^[ \t]*\n/', '', $chunk, 1) ?? $chunk;
        }

        return $out;
    }

    /**
     * @return array{0: string, 1: array<string, string>}
     */
    public static function contentType(string $value): array
    {
        $parts = static::params($value);
        $type = strtolower(trim(array_shift($parts) ?? ''));

        return [$type === '' ? 'text/plain' : $type, $parts];
    }

    /**
     * "a; b=c; d*=utf-8''%E3..." into ['a', 'b' => 'c', 'd' => '...'].
     *
     * @return array<int|string, string>
     */
    protected static function params(string $value): array
    {
        $out = [];
        $cont = [];
        preg_match_all('/(?:^|;)\s*([^=;\s]+)(?:\s*=\s*("(?:[^"\\\\]|\\\\.)*"|[^;]*))?/', $value, $m, PREG_SET_ORDER);
        foreach ($m as $i => $hit) {
            if (!isset($hit[2])) {
                if ($i === 0) {
                    $out[0] = $hit[1];
                }
                continue;
            }
            $key = strtolower($hit[1]);
            $val = trim($hit[2]);
            if (strlen($val) >= 2 && $val[0] === '"') {
                $val = stripcslashes(substr($val, 1, -1));
            }
            // RFC 2231: name*0*=..., name*1*=..., name*=charset''value
            if (preg_match('/^(.+?)\*(\d+)?(\*)?$/', $key, $k)) {
                $base = $k[1];
                $encoded = isset($k[3]) && $k[3] === '*' || (!isset($k[2]) || $k[2] === '') ;
                $cont[$base][] = [intval($k[2] ?? 0), $val, $encoded];
                continue;
            }
            $out[$key] = $val;
        }
        foreach ($cont as $base => $pieces) {
            usort($pieces, function ($a, $b) {
                return $a[0] <=> $b[0];
            });
            $charset = '';
            $joined = '';
            foreach ($pieces as $n => [$idx, $val, $encoded]) {
                if ($n === 0 && $encoded && preg_match("/^([^']*)'[^']*'(.*)$/", $val, $mm)) {
                    $charset = $mm[1];
                    $val = $mm[2];
                }
                $joined .= $encoded ? rawurldecode($val) : $val;
            }
            $out[$base] = $charset !== '' ? static::toUtf8($joined, $charset) : $joined;
        }

        return $out;
    }

    protected static function fileName(array $headers, array $typeParams): string
    {
        $disp = static::params(static::first($headers, 'content-disposition'));
        $name = $disp['filename'] ?? ($typeParams['name'] ?? '');
        $name = static::decodeHeader(strval($name));
        // Keep only the last path element and drop control characters.
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', basename(str_replace('\\', '/', $name)));

        return mb_substr(strval($name), 0, 200);
    }

    public static function decodeBody(string $body, string $encoding): string
    {
        switch (trim($encoding)) {
            case 'base64':
                return strval(base64_decode(preg_replace('/[^A-Za-z0-9+\/=]/', '', $body) ?? ''));
            case 'quoted-printable':
                return quoted_printable_decode($body);
            default:
                return $body;
        }
    }

    public static function toUtf8(string $text, string $charset): string
    {
        $charset = strtoupper(trim($charset));
        if ($charset === '' || $charset === 'US-ASCII') {
            $charset = mb_check_encoding($text, 'UTF-8') ? 'UTF-8' : 'ISO-2022-JP';
            if ($charset === 'UTF-8') {
                return $text;
            }
        }
        $map = [
            'SHIFT_JIS' => 'SJIS-win', 'SHIFT-JIS' => 'SJIS-win', 'SJIS' => 'SJIS-win', 'CP932' => 'SJIS-win', 'WINDOWS-31J' => 'SJIS-win',
            'ISO-2022-JP' => 'ISO-2022-JP-MS', 'EUC-JP' => 'eucJP-win', 'UTF8' => 'UTF-8',
        ];
        $from = $map[$charset] ?? $charset;
        if ($from === 'UTF-8') {
            return mb_check_encoding($text, 'UTF-8') ? $text : mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        }
        try {
            $out = @mb_convert_encoding($text, 'UTF-8', $from);
            if (is_string($out) && $out !== '') {
                return $out;
            }
        } catch (\Throwable $e) {
            // unknown charset name: fall through
        }
        $out = @iconv($charset, 'UTF-8//IGNORE', $text);

        return is_string($out) ? $out : $text;
    }

    /** RFC 2047 "=?ISO-2022-JP?B?...?=" words into UTF-8. */
    public static function decodeHeader(string $value): string
    {
        if (strpos($value, '=?') === false) {
            return static::toUtf8($value, '');
        }
        // Whitespace between two encoded words is not part of the text.
        $value = preg_replace('/\?=\s+=\?/', '?==?', $value) ?? $value;

        return preg_replace_callback('/=\?([^?]+)\?([BbQq])\?([^?]*)\?=/', function ($m) {
            $charset = preg_replace('/\*.*$/', '', $m[1]); // drop RFC 2231 language
            $data = strtoupper($m[2]) === 'B'
                ? strval(base64_decode($m[3]))
                : quoted_printable_decode(str_replace('_', ' ', $m[3]));

            return static::toUtf8($data, strval($charset));
        }, $value) ?? $value;
    }

    /**
     * @return array{0: string, 1: string} [address, display name]
     */
    public static function address(string $value): array
    {
        $value = trim($value);
        if ($value === '') {
            return ['', ''];
        }
        if (preg_match('/^(.*)<([^>]+)>\s*$/', $value, $m)) {
            $name = trim(static::decodeHeader(trim($m[1])), " \"'");

            return [mb_strtolower(trim($m[2])), $name];
        }
        if (preg_match('/([^\s<>()"]+@[^\s<>()"]+)/', $value, $m)) {
            $name = '';
            if (preg_match('/\(([^)]*)\)/', $value, $n)) {
                $name = static::decodeHeader($n[1]);
            }

            return [mb_strtolower($m[1]), $name];
        }

        return ['', static::decodeHeader($value)];
    }

    /**
     * @return array<int, string>
     */
    public static function addresses(string $value): array
    {
        $out = [];
        // Split on commas that are not inside quotes.
        foreach (preg_split('/,(?=(?:[^"]*"[^"]*")*[^"]*$)/', $value) as $one) {
            [$addr] = static::address($one);
            if ($addr !== '') {
                $out[] = $addr;
            }
        }

        return array_values(array_unique($out));
    }

    public static function messageId(string $value): string
    {
        if (preg_match('/<([^>]+)>/', $value, $m)) {
            return trim($m[1]);
        }

        return trim($value);
    }

    /**
     * @return array<int, string>
     */
    public static function messageIds(string $value): array
    {
        preg_match_all('/<([^>]+)>/', $value, $m);

        return array_values(array_unique(array_map('trim', $m[1] ?? [])));
    }

    public static function htmlToText(string $html): string
    {
        $html = preg_replace('#<(script|style|head)[^>]*>.*?</\1>#is', '', $html) ?? $html;
        $html = preg_replace('#<br\s*/?>#i', "\n", $html) ?? $html;
        $html = preg_replace('#</(p|div|tr|li|h[1-6]|blockquote)>#i', "\n", $html) ?? $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/[ \t]+\n/", "\n", $text) ?? $text;

        return trim(preg_replace("/\n{3,}/", "\n\n", $text) ?? $text);
    }

    /**
     * The new part of a reply: everything above the quoted earlier mail.
     * Kept conservative - if no marker is found the whole text stays.
     */
    public static function stripQuoted(string $text): string
    {
        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $text));
        $markers = [
            '/^-{2,}\s*(Original Message|元のメッセージ|Forwarded message|転送されたメッセージ)/iu',
            '/^On .{5,200}wrote:\s*$/u',
            '/^\d{4}[\/年-]\d{1,2}[\/月-]\d{1,2}日?.{0,80}(<[^>]+>|@).{0,40}(:|：|書きました|wrote)\s*$/u',
            '/^(From|差出人|送信者)\s*[:：].+/u',
            '/^_{10,}\s*$/',
            '/^#{3,} この行より上に返信を書いてください/u',
        ];
        $out = [];
        foreach ($lines as $line) {
            $trim = trim($line);
            foreach ($markers as $re) {
                if (preg_match($re, $trim)) {
                    break 2;
                }
            }
            $out[] = $line;
        }
        // Drop a trailing block of '>' quoted lines.
        while (!empty($out) && (trim(end($out)) === '' || strpos(ltrim(end($out)), '>') === 0)) {
            array_pop($out);
        }
        $result = trim(implode("\n", $out));

        return $result === '' ? trim($text) : $result;
    }

    /**
     * Mail no human sent: auto replies, bounces, lists. Answering these is
     * how a help desk ends up mailing a vacation responder in a loop.
     */
    public static function isAutomatic(array $mail): bool
    {
        $h = $mail['headers'];
        $auto = strtolower(static::first($h, 'auto-submitted'));
        if ($auto !== '' && $auto !== 'no') {
            return true;
        }
        if (in_array(strtolower(static::first($h, 'precedence')), ['bulk', 'junk', 'list', 'auto_reply'], true)) {
            return true;
        }
        // X-Auto-Response-Suppress is not one of these: Outlook puts it on
        // ordinary mail to ask that *we* do not auto-reply to it.
        foreach (['x-autoreply', 'x-autorespond'] as $name) {
            if (static::first($h, $name) !== '') {
                return true;
            }
        }
        $from = strval($mail['from']);

        return preg_match('/^(mailer-daemon|postmaster|no-?reply|do-?not-?reply)@/i', $from) === 1;
    }

    /**
     * What the receiving server said about SPF/DKIM/DMARC. "fail" only when
     * a check positively failed - a missing header is not evidence.
     */
    public static function authFailed(array $mail): bool
    {
        $results = strtolower(implode(';', $mail['headers']['authentication-results'] ?? []));
        if ($results === '') {
            return false;
        }

        return preg_match('/\bdmarc=fail\b/', $results) === 1
            || (preg_match('/\bspf=(fail|softfail)\b/', $results) === 1 && preg_match('/\bdkim=fail\b/', $results) === 1);
    }
}
