<?php

namespace App\Plugins\ExmentGroupware\Mail;

/**
 * An IMAP4rev1 client over a plain socket, for the Web mail.
 *
 * No extension is needed: ext-imap is missing on most rental servers and is
 * gone from PHP 8.4. It speaks what a mail program needs - LIST, STATUS,
 * SELECT, UID SEARCH / FETCH / STORE / COPY / MOVE / EXPUNGE, APPEND,
 * CREATE - and logs in with a password (Xserver, KAGOYA, Gmail app
 * passwords, any IMAP server) or with OAuth 2.0 (AUTHENTICATE XOAUTH2:
 * Microsoft 365, Gmail).
 *
 * Folder names are kept as the server sends them (modified UTF-7) and are
 * only decoded for showing, so a name always goes back to the server as it
 * came.
 */
class ImapClient
{
    /** @var resource|null */
    protected $socket;

    protected int $tag = 0;

    protected int $timeout;

    /** @var array<int, string> */
    protected array $capabilities = [];

    public function __construct(int $timeout = 30)
    {
        $this->timeout = $timeout;
    }

    /**
     * @param string $security ssl (993) | tls (STARTTLS, 143) | none
     */
    public function connect(string $host, int $port, string $security = 'ssl'): void
    {
        $scheme = $security === 'ssl' ? 'ssl://' : 'tcp://';
        $context = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true]]);
        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client($scheme . $host . ':' . $port, $errno, $errstr, $this->timeout, STREAM_CLIENT_CONNECT, $context);
        if (!is_resource($socket)) {
            throw new MailException('IMAP サーバー（' . $host . ':' . $port . '）につながりません: ' . ($errstr ?: 'error ' . $errno));
        }
        stream_set_timeout($socket, $this->timeout);
        $this->socket = $socket;

        $greeting = $this->readLine();
        if (stripos($greeting, '* OK') !== 0 && stripos($greeting, '* PREAUTH') !== 0) {
            throw new MailException('IMAP サーバーの応答が想定と違います。');
        }
        $this->takeCapabilities($greeting);

        if ($security === 'tls') {
            $this->command('STARTTLS');
            if (!@stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new MailException('STARTTLS に失敗しました。');
            }
            $this->capabilities = [];
        }
        if (empty($this->capabilities)) {
            $this->capability();
        }
    }

    public function login(string $user, string $password): void
    {
        if ($this->hasCapability('LOGINDISABLED')) {
            throw new MailException('このサーバーは暗号化なしのログインを受け付けません。SSL/TLS を選んでください。');
        }
        $this->commandParts(['LOGIN ', ['literal' => $user], ' ', ['literal' => $password]], 'IMAP にログインできません。ユーザー名・パスワードを確かめてください。');
        $this->capability();
    }

    /** OAuth 2.0 (Microsoft 365, Gmail). */
    public function authenticateXoauth2(string $user, string $accessToken): void
    {
        $sasl = base64_encode('user=' . $user . "\x01auth=Bearer " . $accessToken . "\x01\x01");
        $tag = $this->send('AUTHENTICATE XOAUTH2 ' . $sasl);
        while (true) {
            $line = $this->readLine();
            if (strpos($line, '+') === 0) {
                // The server explains the failure in a challenge; answer empty to get the NO.
                $this->write("\r\n");
                continue;
            }
            if (strpos($line, $tag . ' ') === 0) {
                if (!preg_match('/^' . preg_quote($tag, '/') . ' OK/i', $line)) {
                    throw new MailException('OAuth でログインできません。もう一度「接続する」からサインインしてください。');
                }
                $this->takeCapabilities($line);
                break;
            }
        }
        $this->capability();
    }

    /** @return array<int, string> */
    public function capability(): array
    {
        $this->capabilities = [];
        foreach ($this->command('CAPABILITY') as $resp) {
            if (isset($resp[1]) && static::word($resp[1] ?? null) === 'CAPABILITY') {
                foreach (array_slice($resp, 2) as $cap) {
                    if (is_string($cap)) {
                        $this->capabilities[] = strtoupper($cap);
                    }
                }
            }
        }

        return $this->capabilities;
    }

    public function hasCapability(string $name): bool
    {
        return in_array(strtoupper($name), $this->capabilities, true);
    }

    /**
     * Every folder: raw name, name to show, delimiter, flags.
     *
     * @return array<int, array{name: string, label: string, delimiter: string, flags: array<int, string>, selectable: bool}>
     */
    public function listFolders(): array
    {
        $out = [];
        foreach ($this->command('LIST "" "*"') as $resp) {
            if (!isset($resp[1]) || static::word($resp[1] ?? null) !== 'LIST') {
                continue;
            }
            $flags = array_map(function ($f) {
                return strtolower(strval($f));
            }, is_array($resp[2] ?? null) ? $resp[2] : []);
            $name = strval($resp[4] ?? '');
            if ($name === '') {
                continue;
            }
            $out[] = [
                'name' => $name,
                'label' => static::decodeFolder($name),
                'delimiter' => strval($resp[3] ?? '/'),
                'flags' => $flags,
                'selectable' => !in_array('\\noselect', $flags, true) && !in_array('\\nonexistent', $flags, true),
            ];
        }

        return $out;
    }

    /**
     * @param array<int, string> $items e.g. MESSAGES, UNSEEN
     * @return array<string, int>
     */
    public function status(string $folder, array $items = ['MESSAGES', 'UNSEEN']): array
    {
        $out = [];
        foreach ($this->command('STATUS ' . $this->quote($folder) . ' (' . implode(' ', $items) . ')') as $resp) {
            if (isset($resp[1]) && static::word($resp[1] ?? null) === 'STATUS' && is_array($resp[3] ?? null)) {
                $list = $resp[3];
                for ($i = 0; $i + 1 < count($list); $i += 2) {
                    $out[strtoupper(strval($list[$i]))] = intval($list[$i + 1]);
                }
            }
        }

        return $out;
    }

    /**
     * @return array{exists: int, uidvalidity: string, uidnext: int}
     */
    public function select(string $folder, bool $readOnly = false): array
    {
        $out = ['exists' => 0, 'uidvalidity' => '', 'uidnext' => 0];
        $resps = $this->command(($readOnly ? 'EXAMINE ' : 'SELECT ') . $this->quote($folder), 'フォルダーを開けません: ' . static::decodeFolder($folder));
        foreach ($resps as $resp) {
            if (isset($resp[2]) && static::word($resp[2] ?? null) === 'EXISTS') {
                $out['exists'] = intval($resp[1]);
            }
            // "* OK [UIDVALIDITY 3857529045] UIDs valid": the code is one token.
            if (static::word($resp[1] ?? null) === 'OK' && is_string($resp[2] ?? null)) {
                if (preg_match('/^\[UIDVALIDITY (\d+)\]$/i', $resp[2], $m)) {
                    $out['uidvalidity'] = $m[1];
                }
                if (preg_match('/^\[UIDNEXT (\d+)\]$/i', $resp[2], $m)) {
                    $out['uidnext'] = intval($m[1]);
                }
            }
        }

        return $out;
    }

    /**
     * UID SEARCH. A text to look for goes as a literal, so Japanese works.
     *
     * @return array<int, int> ascending
     */
    public function searchText(?string $text = null, string $extra = ''): array
    {
        $parts = ['UID SEARCH '];
        if ($text !== null && $text !== '') {
            $parts[] = 'CHARSET UTF-8 ';
            $parts[] = 'OR OR OR SUBJECT ';
            $parts[] = ['literal' => $text];
            $parts[] = ' FROM ';
            $parts[] = ['literal' => $text];
            $parts[] = ' TO ';
            $parts[] = ['literal' => $text];
            $parts[] = ' BODY ';
            $parts[] = ['literal' => $text];
        } else {
            $parts[] = 'ALL';
        }
        if ($extra !== '') {
            $parts[] = ' ' . $extra;
        }
        $uids = [];
        foreach ($this->commandParts($parts, '検索できませんでした。') as $resp) {
            if (isset($resp[1]) && static::word($resp[1] ?? null) === 'SEARCH') {
                foreach (array_slice($resp, 2) as $uid) {
                    if (is_string($uid) && ctype_digit($uid)) {
                        $uids[] = intval($uid);
                    }
                }
            }
        }
        sort($uids);

        return $uids;
    }

    /**
     * FETCH by sequence numbers ("1:50") or UIDs ($byUid).
     *
     * @return array<int, array<string, mixed>> keyed by UID
     */
    public function fetch(string $set, string $items, bool $byUid = true): array
    {
        $out = [];
        foreach ($this->command(($byUid ? 'UID FETCH ' : 'FETCH ') . $set . ' (' . $items . ')', 'メールを読めません。') as $resp) {
            if (!isset($resp[2]) || static::word($resp[2] ?? null) !== 'FETCH' || !is_array($resp[3] ?? null)) {
                continue;
            }
            $list = $resp[3];
            $item = ['SEQ' => intval($resp[1])];
            for ($i = 0; $i + 1 < count($list); $i += 2) {
                $key = strtoupper(strval($list[$i]));
                if (strpos($key, 'BODY[HEADER') === 0) {
                    $key = 'HEADER';
                } elseif (strpos($key, 'BODY[') === 0) {
                    $key = 'BODY';
                }
                $item[$key] = $list[$i + 1];
            }
            if (!isset($item['UID'])) {
                continue; // an unsolicited flag update
            }
            $out[intval($item['UID'])] = $item;
        }

        return $out;
    }

    /**
     * @param array<int, int> $uids
     * @param array<int, string> $flags e.g. \Seen \Flagged
     */
    public function store(array $uids, bool $add, array $flags): void
    {
        if (empty($uids)) {
            return;
        }
        $this->command('UID STORE ' . static::uidSet($uids) . ' ' . ($add ? '+' : '-') . 'FLAGS.SILENT (' . implode(' ', $flags) . ')', 'フラグを変えられませんでした。');
    }

    /** @param array<int, int> $uids */
    public function move(array $uids, string $folder): void
    {
        if (empty($uids)) {
            return;
        }
        if ($this->hasCapability('MOVE')) {
            $this->command('UID MOVE ' . static::uidSet($uids) . ' ' . $this->quote($folder), '移動できませんでした。');

            return;
        }
        $this->command('UID COPY ' . static::uidSet($uids) . ' ' . $this->quote($folder), '移動できませんでした。');
        $this->store($uids, true, ['\\Deleted']);
        $this->expunge($uids);
    }

    /** Remove for good the messages marked \Deleted (only these when the server can). */
    public function expunge(array $uids = []): void
    {
        if (!empty($uids) && $this->hasCapability('UIDPLUS')) {
            $this->command('UID EXPUNGE ' . static::uidSet($uids));

            return;
        }
        $this->command('EXPUNGE');
    }

    /** @param array<int, int> $uids */
    public function deleteForever(array $uids): void
    {
        $this->store($uids, true, ['\\Deleted']);
        $this->expunge($uids);
    }

    /**
     * Save a whole message into a folder (Sent, Drafts).
     *
     * @param array<int, string> $flags
     */
    public function append(string $folder, string $raw, array $flags = ['\\Seen']): void
    {
        $raw = preg_replace("/\r?\n/", "\r\n", $raw) ?? $raw;
        $this->commandParts(['APPEND ' . $this->quote($folder) . ' (' . implode(' ', $flags) . ') ', ['literal' => $raw, 'binary' => true]], 'フォルダー「' . static::decodeFolder($folder) . '」に保存できませんでした。');
    }

    public function create(string $folder): void
    {
        $this->command('CREATE ' . $this->quote($folder), 'フォルダーを作れませんでした。');
        try {
            $this->command('SUBSCRIBE ' . $this->quote($folder));
        } catch (\Throwable $e) {
            // not every server keeps subscriptions
        }
    }

    public function logout(): void
    {
        if (!is_resource($this->socket)) {
            return;
        }
        try {
            $this->send('LOGOUT');
        } catch (\Throwable $e) {
            // closing anyway
        }
        @fclose($this->socket);
        $this->socket = null;
    }

    public function __destruct()
    {
        $this->logout();
    }

    // ---- helpers ---------------------------------------------------------

    /** A token as an upper-case word; a list or NIL is not a word. */
    protected static function word($token): string
    {
        return is_string($token) ? strtoupper($token) : '';
    }

    /** Modified UTF-7 (RFC 3501 5.1.3) to UTF-8, for showing. */
    public static function decodeFolder(string $name): string
    {
        if (strpos($name, '&') === false) {
            return $name;
        }
        $out = @mb_convert_encoding($name, 'UTF-8', 'UTF7-IMAP');

        return is_string($out) && $out !== '' ? $out : $name;
    }

    /** UTF-8 to modified UTF-7, for a folder the user names. */
    public static function encodeFolder(string $name): string
    {
        $out = @mb_convert_encoding($name, 'UTF7-IMAP', 'UTF-8');

        return is_string($out) && $out !== '' ? $out : $name;
    }

    /** @param array<int, int> $uids */
    public static function uidSet(array $uids): string
    {
        $uids = array_values(array_unique(array_map('intval', $uids)));
        sort($uids);

        return implode(',', $uids);
    }

    // ---- wire ------------------------------------------------------------

    /**
     * @return array<int, array<int, mixed>> the untagged responses, parsed
     */
    protected function command(string $command, ?string $error = null): array
    {
        return $this->commandParts([$command], $error);
    }

    /**
     * Send a command made of text and literals ({n} and the bytes after the
     * server's "+"), then read to the tagged answer.
     *
     * @param array<int, string|array{literal: string, binary?: bool}> $parts
     * @return array<int, array<int, mixed>>
     */
    protected function commandParts(array $parts, ?string $error = null): array
    {
        $tag = $this->nextTag();
        $line = $tag . ' ';
        $nonSync = $this->hasCapability('LITERAL+');
        foreach ($parts as $part) {
            if (is_string($part)) {
                $line .= $part;
                continue;
            }
            $value = $part['literal'];
            if (empty($part['binary']) && $this->isAtomSafe($value)) {
                $line .= $this->quote($value);
                continue;
            }
            $line .= '{' . strlen($value) . ($nonSync ? '+' : '') . "}\r\n";
            $this->write($line);
            $line = '';
            if (!$nonSync) {
                $cont = $this->readLine();
                if (strpos($cont, '+') !== 0) {
                    throw new MailException($error ?? 'IMAP のサーバーが受け付けませんでした。');
                }
            }
            $this->write($value);
        }
        $this->write($line . "\r\n");

        return $this->readTagged($tag, $error, strtok(is_string($parts[0]) ? $parts[0] : '', ' ') ?: '');
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    protected function readTagged(string $tag, ?string $error, string $name): array
    {
        $resps = [];
        while (true) {
            $raw = $this->readResponse();
            if (strpos($raw, $tag . ' ') === 0) {
                if (!preg_match('/^' . preg_quote($tag, '/') . ' OK/i', $raw)) {
                    $said = trim(preg_replace('/^' . preg_quote($tag, '/') . ' (NO|BAD)\s*/i', '', $raw) ?? '');
                    throw new MailException(($error ?? ('IMAP のコマンドに失敗しました: ' . $name)) . ($said !== '' ? '（' . mb_substr($said, 0, 200) . '）' : ''));
                }
                $this->takeCapabilities($raw);

                return $resps;
            }
            if (strpos($raw, '+') === 0) {
                continue;
            }
            $pos = 0;
            $resps[] = static::parseList($raw, $pos, true);
        }
    }

    /** One response, with its literals ({n} and the n bytes), as one string. */
    protected function readResponse(): string
    {
        $buf = '';
        while (true) {
            $line = $this->readLine();
            $buf .= $line;
            if (preg_match('/\{(\d+)\+?\}\r?\n$/', $line, $m)) {
                $buf .= $this->readBytes(intval($m[1]));
                continue;
            }

            return $buf;
        }
    }

    /**
     * The tokens of a response: atoms and strings as strings, NIL as null,
     * parenthesized lists as arrays.
     *
     * @return array<int, mixed>
     */
    public static function parseList(string $s, int &$i, bool $top = false): array
    {
        $out = [];
        $len = strlen($s);
        while ($i < $len) {
            $c = $s[$i];
            if ($c === ' ' || $c === "\r" || $c === "\n") {
                $i++;
                continue;
            }
            if ($c === '(') {
                $i++;
                $out[] = static::parseList($s, $i);
                continue;
            }
            if ($c === ')') {
                $i++;
                if (!$top) {
                    return $out;
                }
                continue;
            }
            if ($c === '"') {
                $i++;
                $str = '';
                while ($i < $len && $s[$i] !== '"') {
                    if ($s[$i] === '\\' && $i + 1 < $len) {
                        $i++;
                    }
                    $str .= $s[$i];
                    $i++;
                }
                $i++;
                $out[] = $str;
                continue;
            }
            if ($c === '{' && preg_match('/\G\{(\d+)\+?\}\r?\n/', $s, $m, 0, $i)) {
                $n = intval($m[1]);
                $i += strlen($m[0]);
                $out[] = substr($s, $i, $n);
                $i += $n;
                continue;
            }
            // An atom; BODY[HEADER.FIELDS (A B)]<0> keeps its brackets whole.
            $start = $i;
            $depth = 0;
            while ($i < $len) {
                $ch = $s[$i];
                if ($ch === '[') {
                    $depth++;
                } elseif ($ch === ']') {
                    $depth--;
                } elseif ($depth <= 0 && ($ch === ' ' || $ch === '(' || $ch === ')' || $ch === "\r" || $ch === "\n")) {
                    break;
                }
                $i++;
            }
            $atom = substr($s, $start, $i - $start);
            $out[] = strtoupper($atom) === 'NIL' ? null : $atom;
        }

        return $out;
    }

    protected function takeCapabilities(string $line): void
    {
        if (preg_match('/\[CAPABILITY ([^\]]+)\]/i', $line, $m)) {
            $this->capabilities = array_map('strtoupper', preg_split('/\s+/', trim($m[1])) ?: []);
        }
    }

    protected function nextTag(): string
    {
        return 'G' . str_pad(strval(++$this->tag), 4, '0', STR_PAD_LEFT);
    }

    protected function send(string $command): string
    {
        $tag = $this->nextTag();
        $this->write($tag . ' ' . $command . "\r\n");

        return $tag;
    }

    protected function write(string $data): void
    {
        if (!is_resource($this->socket)) {
            throw new MailException('IMAP につながっていません。');
        }
        $len = strlen($data);
        $done = 0;
        while ($done < $len) {
            $n = @fwrite($this->socket, substr($data, $done, 65536));
            if ($n === false || $n === 0) {
                throw new MailException('IMAP に送れません。');
            }
            $done += $n;
        }
    }

    protected function readLine(): string
    {
        if (!is_resource($this->socket)) {
            throw new MailException('IMAP につながっていません。');
        }
        $line = '';
        // A line can be longer than one fgets (a long LIST or SEARCH).
        while (true) {
            $part = @fgets($this->socket, 65536);
            if ($part === false) {
                $meta = stream_get_meta_data($this->socket);
                throw new MailException(!empty($meta['timed_out']) ? 'IMAP の応答が時間内にありません。' : 'IMAP の接続が切れました。');
            }
            $line .= $part;
            if (substr($line, -1) === "\n") {
                return $line;
            }
        }
    }

    protected function readBytes(int $length): string
    {
        $data = '';
        while (strlen($data) < $length) {
            $chunk = @fread($this->socket, min(65536, $length - strlen($data)));
            if ($chunk === false || $chunk === '') {
                $meta = stream_get_meta_data($this->socket);
                if (!empty($meta['timed_out']) || feof($this->socket)) {
                    throw new MailException('IMAP からメールを最後まで読めませんでした。');
                }
                continue;
            }
            $data .= $chunk;
        }

        return $data;
    }

    protected function isAtomSafe(string $value): bool
    {
        return $value !== '' && preg_match('/^[\x20-\x7E]*$/', $value) === 1 && strlen($value) < 1000;
    }

    protected function quote(string $value): string
    {
        // A line break would end the command and start another one.
        if (preg_match('/[\x00\r\n]/', $value) === 1) {
            throw new MailException('使えない文字が入っています。');
        }

        return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
    }
}
