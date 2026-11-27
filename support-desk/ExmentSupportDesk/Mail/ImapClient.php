<?php

namespace App\Plugins\ExmentSupportDesk\Mail;

/**
 * A small IMAP4rev1 client over a plain socket.
 *
 * Only the commands the help desk needs: LOGIN, SELECT, UID SEARCH,
 * UID FETCH of the whole message, UID STORE of \Seen. No extension needed,
 * which matters on rental servers and on PHP 8.4, where ext-imap is gone.
 *
 * Messages are read with BODY.PEEK[] so a failed import leaves them unread
 * for the next run; they are marked \Seen only after the ticket is saved.
 */
class ImapClient
{
    /** @var resource|null */
    protected $socket;

    protected int $tag = 0;

    protected int $timeout;

    public function __construct(int $timeout = 30)
    {
        $this->timeout = $timeout;
    }

    /**
     * @param string $security ssl | tls (STARTTLS) | none
     */
    public function connect(string $host, int $port, string $security = 'ssl'): void
    {
        $scheme = $security === 'ssl' ? 'ssl://' : 'tcp://';
        $context = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true]]);
        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client($scheme . $host . ':' . $port, $errno, $errstr, $this->timeout, STREAM_CLIENT_CONNECT, $context);
        if (!is_resource($socket)) {
            throw new MailException('IMAP サーバーにつながりません: ' . ($errstr ?: 'error ' . $errno));
        }
        stream_set_timeout($socket, $this->timeout);
        $this->socket = $socket;

        $greeting = $this->readLine();
        if (stripos($greeting, '* OK') !== 0 && stripos($greeting, '* PREAUTH') !== 0) {
            throw new MailException('IMAP サーバーの応答が想定と違います。');
        }

        if ($security === 'tls') {
            $this->command('STARTTLS');
            if (!@stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new MailException('STARTTLS に失敗しました。');
            }
        }
    }

    public function login(string $user, string $password): void
    {
        $this->command('LOGIN ' . $this->quote($user) . ' ' . $this->quote($password), 'IMAP にログインできません。ユーザー名・パスワードを確かめてください。');
    }

    /**
     * @return array{exists: int, uidvalidity: string}
     */
    public function select(string $folder): array
    {
        $lines = $this->command('SELECT ' . $this->quote($folder), 'フォルダを開けません: ' . $folder);
        $out = ['exists' => 0, 'uidvalidity' => ''];
        foreach ($lines as $line) {
            if (preg_match('/^\* (\d+) EXISTS/i', $line, $m)) {
                $out['exists'] = intval($m[1]);
            }
            if (preg_match('/UIDVALIDITY (\d+)/i', $line, $m)) {
                $out['uidvalidity'] = $m[1];
            }
        }

        return $out;
    }

    /**
     * UIDs of unread messages, oldest first, newer than $afterUid.
     *
     * @return array<int, int>
     */
    public function unseenUids(int $afterUid = 0): array
    {
        $criteria = $afterUid > 0 ? 'UID ' . ($afterUid + 1) . ':* UNSEEN' : 'UNSEEN';
        $uids = [];
        foreach ($this->command('UID SEARCH ' . $criteria) as $line) {
            if (preg_match('/^\* SEARCH(.*)$/i', $line, $m)) {
                foreach (preg_split('/\s+/', trim($m[1])) as $uid) {
                    if (ctype_digit($uid) && intval($uid) > $afterUid) {
                        $uids[] = intval($uid);
                    }
                }
            }
        }
        sort($uids);

        return $uids;
    }

    /** The full raw message, without marking it read. */
    public function fetch(int $uid): string
    {
        $tag = $this->send('UID FETCH ' . $uid . ' BODY.PEEK[]');
        $raw = '';
        while (true) {
            $line = $this->readLine();
            if (preg_match('/\{(\d+)\}\r?\n?$/', $line, $m) && strpos($line, 'FETCH') !== false) {
                $raw = $this->readBytes(intval($m[1]));
                continue;
            }
            if (strpos($line, $tag . ' ') === 0) {
                if (!preg_match('/^' . preg_quote($tag, '/') . ' OK/i', $line)) {
                    throw new MailException('メールを読めません（UID ' . $uid . '）。');
                }
                break;
            }
        }

        return $raw;
    }

    public function markSeen(int $uid): void
    {
        $this->command('UID STORE ' . $uid . ' +FLAGS.SILENT (\\Seen)');
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

    // ---- wire ------------------------------------------------------------

    /**
     * @return array<int, string> the untagged lines
     */
    protected function command(string $command, ?string $error = null): array
    {
        $tag = $this->send($command);
        $lines = [];
        while (true) {
            $line = $this->readLine();
            if (preg_match('/\{(\d+)\}\r?\n?$/', $line, $m)) {
                // A literal we do not need here: read past it.
                $this->readBytes(intval($m[1]));
                continue;
            }
            if (strpos($line, $tag . ' ') === 0) {
                if (!preg_match('/^' . preg_quote($tag, '/') . ' OK/i', $line)) {
                    throw new MailException($error ?? ('IMAP のコマンドに失敗しました: ' . strtok($command, ' ')));
                }

                return $lines;
            }
            $lines[] = rtrim($line, "\r\n");
        }
    }

    protected function send(string $command): string
    {
        if (!is_resource($this->socket)) {
            throw new MailException('IMAP につながっていません。');
        }
        $tag = 'A' . str_pad(strval(++$this->tag), 4, '0', STR_PAD_LEFT);
        if (@fwrite($this->socket, $tag . ' ' . $command . "\r\n") === false) {
            throw new MailException('IMAP に送れません。');
        }

        return $tag;
    }

    protected function readLine(): string
    {
        $line = @fgets($this->socket, 65536);
        if ($line === false) {
            $meta = is_resource($this->socket) ? stream_get_meta_data($this->socket) : [];
            throw new MailException(!empty($meta['timed_out']) ? 'IMAP の応答が時間内にありません。' : 'IMAP の接続が切れました。');
        }

        return $line;
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

    protected function quote(string $value): string
    {
        return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
    }
}
