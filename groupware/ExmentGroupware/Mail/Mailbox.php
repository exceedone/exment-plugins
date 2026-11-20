<?php

namespace App\Plugins\ExmentGroupware\Mail;

/**
 * One account's mailbox on its IMAP server: folders, lists, one mail,
 * flags, moving, deleting, and keeping copies in Sent and Drafts.
 *
 * Nothing of the mail is copied into Exment: every screen reads the server,
 * so what Outlook or the phone did a moment ago shows here too.
 */
class Mailbox
{
    const PAGE_SIZE = 50;

    /** Inline images bigger than this stay out of the mail view (they are still attachments). */
    const INLINE_LIMIT = 8 * 1024 * 1024;

    const ROLES = ['inbox', 'drafts', 'sent', 'junk', 'trash', 'archive'];

    const ROLE_LABELS = ['inbox' => '受信トレイ', 'drafts' => '下書き', 'sent' => '送信済み', 'junk' => '迷惑メール', 'trash' => 'ごみ箱', 'archive' => 'アーカイブ'];

    /** @var object */
    protected $account;

    /** @var OAuth */
    protected $oauth;

    /** @var ImapClient|null */
    protected $imap;

    /** @var array<int, array<string, mixed>>|null */
    protected $folders;

    protected string $selected = '';

    public function __construct(object $account, OAuth $oauth)
    {
        $this->account = $account;
        $this->oauth = $oauth;
    }

    public function imap(): ImapClient
    {
        if ($this->imap !== null) {
            return $this->imap;
        }
        $a = $this->account;
        $imap = new ImapClient(30);
        try {
            HostGuard::check(strval($a->imap_host), intval($a->imap_port));
            $imap->connect(strval($a->imap_host), intval($a->imap_port), strval($a->imap_security));
            if ($a->auth_type === 'oauth') {
                $imap->authenticateXoauth2(strval($a->username), $this->oauth->accessToken($a));
            } else {
                $imap->login(strval($a->username), strval(Accounts::secret($a)['password'] ?? ''));
            }
        } catch (MailException $e) {
            Accounts::recordError(intval($a->id), $e->getMessage());
            throw $e;
        }
        if (!empty($a->last_error)) {
            Accounts::recordError(intval($a->id), null);
        }

        return $this->imap = $imap;
    }

    public function close(): void
    {
        if ($this->imap !== null) {
            $this->imap->logout();
            $this->imap = null;
        }
    }

    // ---- folders -----------------------------------------------------------

    /**
     * Folders in the order a mail program shows them, with their unread counts.
     *
     * @return array<int, array<string, mixed>>
     */
    public function folders(bool $counts = true): array
    {
        if ($this->folders !== null) {
            return $this->folders;
        }
        $imap = $this->imap();
        $list = $imap->listFolders();
        $roles = $this->roles($list);
        $out = [];
        foreach ($list as $f) {
            $role = array_search($f['name'], $roles, true);
            $label = $f['label'];
            // "INBOX.Sent" and "[Gmail]/送信済みメール": show the last part, indented by depth.
            $delim = $f['delimiter'];
            $depth = 0;
            if ($delim !== '' && strtoupper($f['name']) !== 'INBOX') {
                $parts = explode($delim, $label);
                if (strtoupper($parts[0]) === 'INBOX' && count($parts) > 1) {
                    array_shift($parts);
                }
                $depth = count($parts) - 1;
                $label = strval(end($parts));
            }
            $out[] = [
                'name' => $f['name'],
                // Gmail and Microsoft 365 name these by the user's language; Xserver and KAGOYA in English.
                'label' => $role !== false && isset(static::ROLE_LABELS[$role]) && preg_match('/^[ -~]*$/', $label) === 1 ? static::ROLE_LABELS[$role] : $label,
                'role' => $role === false ? null : $role,
                'depth' => $role === false ? $depth : 0,
                'selectable' => $f['selectable'],
                'unread' => 0,
                'total' => null,
            ];
        }
        usort($out, function ($a, $b) {
            $ra = $a['role'] === null ? 99 : intval(array_search($a['role'], static::ROLES, true));
            $rb = $b['role'] === null ? 99 : intval(array_search($b['role'], static::ROLES, true));
            if ($ra !== $rb) {
                return $ra <=> $rb;
            }

            return strcmp(strval($a['name']), strval($b['name']));
        });
        if ($counts) {
            $n = 0;
            foreach ($out as &$f) {
                // A mailbox with hundreds of folders: count the first ones only.
                if (!$f['selectable'] || $n++ >= 40) {
                    continue;
                }
                try {
                    $st = $imap->status(strval($f['name']), ['UNSEEN', 'MESSAGES']);
                    $f['unread'] = intval($st['UNSEEN'] ?? 0);
                    $f['total'] = intval($st['MESSAGES'] ?? 0);
                } catch (MailException $e) {
                    // a folder that cannot be opened stays without a count
                }
            }
            unset($f);
        }

        return $this->folders = $out;
    }

    /**
     * Which folder is Sent, Trash, ... From the server's special-use flags
     * (RFC 6154: Gmail, Microsoft 365, newer Dovecot) and, when the server
     * has none, from the usual names (Xserver, KAGOYA and older servers).
     *
     * @param array<int, array<string, mixed>> $list
     * @return array<string, string> role => folder name
     */
    protected function roles(array $list): array
    {
        $roles = [];
        $flagMap = ['\\sent' => 'sent', '\\drafts' => 'drafts', '\\trash' => 'trash', '\\junk' => 'junk', '\\archive' => 'archive'];
        $names = [
            'sent' => ['sent', 'sent items', 'sent messages', 'sent mail', '送信済み', '送信済みアイテム', '送信済みメール', '送信箱'],
            'drafts' => ['drafts', 'draft', '下書き'],
            'trash' => ['trash', 'deleted items', 'deleted messages', 'ゴミ箱', 'ごみ箱', '削除済みアイテム'],
            'junk' => ['junk', 'spam', 'junk e-mail', 'junk email', '迷惑メール'],
            'archive' => ['archive', 'archives', 'アーカイブ'],
        ];
        foreach ($list as $f) {
            if (strtoupper($f['name']) === 'INBOX') {
                $roles['inbox'] = $f['name'];
            }
            foreach ($f['flags'] as $flag) {
                if (isset($flagMap[$flag]) && !isset($roles[$flagMap[$flag]])) {
                    $roles[$flagMap[$flag]] = $f['name'];
                }
            }
        }
        foreach ($list as $f) {
            $parts = $f['delimiter'] !== '' ? explode($f['delimiter'], mb_strtolower($f['label'])) : [mb_strtolower($f['label'])];
            // Only the top level or right under INBOX: "INBOX.Sent", "Sent".
            if (count($parts) > 2 || (count($parts) === 2 && $parts[0] !== 'inbox')) {
                continue;
            }
            $last = strval(end($parts));
            foreach ($names as $role => $candidates) {
                if (!isset($roles[$role]) && in_array($last, $candidates, true)) {
                    $roles[$role] = $f['name'];
                }
            }
        }
        // What was set by hand on the account wins.
        foreach (['sent', 'drafts', 'trash', 'junk'] as $role) {
            $col = $role . '_folder';
            if (!empty($this->account->$col)) {
                $roles[$role] = strval($this->account->$col);
            }
        }
        if (!isset($roles['inbox'])) {
            $roles['inbox'] = 'INBOX';
        }

        return $roles;
    }

    /** The folder of a role, created when the server has none (Sent, Drafts, Trash). */
    public function folderFor(string $role, bool $create = true): ?string
    {
        foreach ($this->folders(false) as $f) {
            if ($f['role'] === $role) {
                return strval($f['name']);
            }
        }
        if (!$create) {
            return null;
        }
        $labels = ['sent' => 'Sent', 'drafts' => 'Drafts', 'trash' => 'Trash', 'junk' => 'Junk'];
        if (!isset($labels[$role])) {
            return null;
        }
        // Servers that keep every folder under INBOX ("INBOX.Sent").
        $prefix = '';
        $list = $this->imap()->listFolders();
        foreach ($list as $f) {
            if ($f['delimiter'] !== '' && stripos($f['name'], 'INBOX' . $f['delimiter']) === 0) {
                $prefix = 'INBOX' . $f['delimiter'];
                break;
            }
        }
        $name = $prefix . $labels[$role];
        $this->imap()->create($name);
        Accounts::update(intval($this->account->id), [$role . '_folder' => $name]);
        $col = $role . '_folder';
        $this->account->$col = $name;
        $this->folders = null;

        return $name;
    }

    protected function select(string $folder): array
    {
        // Folder names come from the URL: only the ones the server listed.
        if ($this->selected !== $folder && !in_array($folder, array_column($this->folders(false), 'name'), true)) {
            throw new MailException('フォルダーが見つかりません。');
        }
        $info = $this->imap()->select($folder);
        $this->selected = $folder;

        return $info;
    }

    // ---- lists -------------------------------------------------------------

    /**
     * One page of a folder, newest first.
     *
     * @param string $filter all | unread | flagged
     * @return array<string, mixed>
     */
    public function messages(string $folder, int $page = 1, string $query = '', string $filter = 'all'): array
    {
        $info = $this->select($folder);
        $page = max(1, $page);
        $imap = $this->imap();
        $extra = $filter === 'unread' ? 'UNSEEN' : ($filter === 'flagged' ? 'FLAGGED' : '');
        $items = [];

        if ($query === '' && $extra === '') {
            $total = $info['exists'];
            $hi = $total - ($page - 1) * static::PAGE_SIZE;
            if ($hi >= 1) {
                $lo = max(1, $hi - static::PAGE_SIZE + 1);
                $items = $imap->fetch($lo . ':' . $hi, static::LIST_ITEMS, false);
            }
            uasort($items, function ($a, $b) {
                return $b['SEQ'] <=> $a['SEQ'];
            });
        } else {
            $uids = array_reverse($imap->searchText($query !== '' ? $query : null, $extra));
            $total = count($uids);
            $slice = array_slice($uids, ($page - 1) * static::PAGE_SIZE, static::PAGE_SIZE);
            if (!empty($slice)) {
                $items = $imap->fetch(ImapClient::uidSet($slice), static::LIST_ITEMS);
            }
            uasort($items, function ($a, $b) {
                return intval($b['UID']) <=> intval($a['UID']);
            });
        }

        $out = [];
        foreach ($items as $item) {
            $out[] = static::summary($item);
        }

        return [
            'folder' => $folder,
            'total' => $total,
            'page' => $page,
            'pages' => max(1, intval(ceil($total / static::PAGE_SIZE))),
            'uidvalidity' => $info['uidvalidity'],
            'items' => $out,
        ];
    }

    const LIST_ITEMS = 'UID FLAGS INTERNALDATE RFC822.SIZE BODY.PEEK[HEADER.FIELDS (FROM TO CC SUBJECT DATE CONTENT-TYPE)]';

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    protected static function summary(array $item): array
    {
        [$headers] = MimeParser::split(strval($item['HEADER'] ?? ''));
        [$from, $fromName] = MimeParser::address(MimeParser::first($headers, 'from'));
        $to = MimeParser::namedAddresses(MimeParser::first($headers, 'to'));
        $flags = array_map('strtolower', array_map('strval', is_array($item['FLAGS'] ?? null) ? $item['FLAGS'] : []));
        [$type] = MimeParser::contentType(MimeParser::first($headers, 'content-type'));

        return [
            'uid' => intval($item['UID']),
            'from' => $from,
            'from_name' => $fromName,
            'to' => $to,
            'subject' => MimeParser::decodeHeader(MimeParser::first($headers, 'subject')),
            'date' => static::date(MimeParser::first($headers, 'date'), strval($item['INTERNALDATE'] ?? '')),
            'size' => intval($item['RFC822.SIZE'] ?? 0),
            'seen' => in_array('\\seen', $flags, true),
            'flagged' => in_array('\\flagged', $flags, true),
            'answered' => in_array('\\answered', $flags, true),
            'draft' => in_array('\\draft', $flags, true),
            // From the top Content-Type only (no BODYSTRUCTURE): right for nearly all mail.
            'attachment' => $type === 'multipart/mixed',
        ];
    }

    protected static function date(string $header, string $internal = ''): ?string
    {
        foreach ([$internal, $header] as $value) {
            if ($value !== '' && ($ts = strtotime($value)) !== false) {
                return date('c', $ts);
            }
        }

        return null;
    }

    // ---- one mail ----------------------------------------------------------

    /**
     * One mail, parsed and made safe to show. Opening it marks it read.
     *
     * @return array<string, mixed>
     */
    public function message(string $folder, int $uid, bool $markSeen = true): array
    {
        [$mail, $item] = $this->fetchParsed($folder, $uid);
        $flags = array_map('strtolower', array_map('strval', is_array($item['FLAGS'] ?? null) ? $item['FLAGS'] : []));
        if ($markSeen && !in_array('\\seen', $flags, true)) {
            $this->imap()->store([$uid], true, ['\\Seen']);
        }

        $cid = [];
        $inlineBytes = 0;
        $attachments = [];
        foreach ($mail['attachments'] as $i => $att) {
            $isImage = strpos(strval($att['type']), 'image/') === 0;
            if ($att['cid'] !== '' && $isImage && $inlineBytes + strlen($att['content']) <= static::INLINE_LIMIT) {
                $inlineBytes += strlen($att['content']);
                $cid[$att['cid']] = 'data:' . $att['type'] . ';base64,' . base64_encode($att['content']);
            }
            if ($att['inline'] && $isImage && $mail['html'] !== '' && stripos($mail['html'], 'cid:' . $att['cid']) !== false) {
                continue; // an image of the mail itself, not something to download
            }
            $attachments[] = ['index' => $i, 'name' => $att['name'], 'type' => $att['type'], 'size' => strlen($att['content'])];
        }

        $remote = false;
        if ($mail['html'] !== '') {
            $safe = HtmlSafe::clean($mail['html'], $cid);
            $html = $safe['html'];
            $remote = $safe['remote'];
        } else {
            $html = HtmlSafe::textToHtml($mail['text']);
        }
        $headers = $mail['headers'];

        return [
            'uid' => $uid,
            'folder' => $folder,
            'subject' => $mail['subject'],
            'from' => $mail['from'],
            'from_name' => $mail['from_name'],
            'reply_to' => MimeParser::namedAddresses(MimeParser::first($headers, 'reply-to')),
            'to' => MimeParser::namedAddresses(MimeParser::first($headers, 'to')),
            'cc' => MimeParser::namedAddresses(MimeParser::first($headers, 'cc')),
            'bcc' => MimeParser::namedAddresses(MimeParser::first($headers, 'bcc')),
            'date' => static::date(MimeParser::first($headers, 'date'), strval($item['INTERNALDATE'] ?? '')),
            'message_id' => $mail['message_id'],
            'references' => array_values(array_filter(array_merge($mail['references'], [$mail['in_reply_to']]))),
            'html' => $html,
            'is_html' => $mail['html'] !== '',
            'remote_content' => $remote,
            'text' => $mail['text'],
            // For a reply or a forward, without the mail's own styles (they would leak into the page).
            'quote_html' => $mail['html'] !== '' ? HtmlSafe::clean($mail['html'], [], false, true)['html'] : HtmlSafe::textToHtml($mail['text']),
            'attachments' => $attachments,
            'seen' => true,
            'flagged' => in_array('\\flagged', $flags, true),
            'draft' => in_array('\\draft', $flags, true),
            'size' => intval($item['RFC822.SIZE'] ?? 0),
        ];
    }

    /**
     * @return array{name: string, type: string, content: string}
     */
    public function attachment(string $folder, int $uid, int $index): array
    {
        [$mail] = $this->fetchParsed($folder, $uid);
        $att = $mail['attachments'][$index] ?? null;
        if (!is_array($att)) {
            throw new MailException('添付ファイルが見つかりません。');
        }

        return ['name' => strval($att['name']), 'type' => strval($att['type']), 'content' => strval($att['content'])];
    }

    /** The whole mail as it is on the server (.eml). */
    public function raw(string $folder, int $uid): string
    {
        $this->select($folder);
        $items = $this->imap()->fetch(strval($uid), 'UID BODY.PEEK[]');
        if (!isset($items[$uid])) {
            throw new MailException('メールが見つかりません。ほかの場所で移動・削除された可能性があります。');
        }

        return strval($items[$uid]['BODY'] ?? '');
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    protected function fetchParsed(string $folder, int $uid): array
    {
        $this->select($folder);
        $items = $this->imap()->fetch(strval($uid), 'UID FLAGS INTERNALDATE RFC822.SIZE BODY.PEEK[]');
        if (!isset($items[$uid])) {
            throw new MailException('メールが見つかりません。ほかの場所で移動・削除された可能性があります。');
        }

        return [MimeParser::parse(strval($items[$uid]['BODY'] ?? '')), $items[$uid]];
    }

    // ---- changes -----------------------------------------------------------

    /** @param array<int, int> $uids */
    public function flag(string $folder, array $uids, string $flag, bool $on): void
    {
        $map = ['seen' => '\\Seen', 'flagged' => '\\Flagged', 'answered' => '\\Answered'];
        if (!isset($map[$flag])) {
            throw new MailException('このフラグは変えられません。');
        }
        $this->select($folder);
        $this->imap()->store($uids, $on, [$map[$flag]]);
    }

    /** @param array<int, int> $uids */
    public function move(string $folder, array $uids, string $target): void
    {
        if ($target === $folder) {
            return;
        }
        $known = array_column($this->folders(false), 'name');
        if (!in_array($target, $known, true)) {
            throw new MailException('移動先のフォルダーがありません。');
        }
        $this->select($folder);
        $this->imap()->move($uids, $target);
    }

    /**
     * Into the trash; from the trash (or a server without one), for good.
     *
     * @param array<int, int> $uids
     * @return string trash | deleted
     */
    public function delete(string $folder, array $uids, bool $forever = false): string
    {
        $trash = $this->folderFor('trash', !$forever);
        $this->select($folder);
        if ($forever || $trash === null || $trash === $folder) {
            $this->imap()->deleteForever($uids);

            return 'deleted';
        }
        $this->imap()->move($uids, $trash);

        return 'trash';
    }

    public function createFolder(string $label, ?string $parent = null): string
    {
        $label = trim(str_replace(["\r", "\n"], '', $label));
        if ($label === '' || mb_strlen($label) > 100) {
            throw new MailException('フォルダーの名前を入れてください（100 文字まで）。');
        }
        $delim = '/';
        $prefix = '';
        foreach ($this->imap()->listFolders() as $f) {
            if ($f['delimiter'] !== '') {
                $delim = $f['delimiter'];
            }
            if ($parent !== null && $f['name'] === $parent) {
                $prefix = $parent . $f['delimiter'];
            } elseif ($parent === null && $prefix === '' && $f['delimiter'] !== '' && stripos($f['name'], 'INBOX' . $f['delimiter']) === 0) {
                // A server that keeps every folder under INBOX.
                $prefix = 'INBOX' . $f['delimiter'];
            }
        }
        if (strpos($label, $delim) !== false) {
            throw new MailException('フォルダーの名前に「' . $delim . '」は使えません。');
        }
        $name = $prefix . ImapClient::encodeFolder($label);
        $this->imap()->create($name);
        $this->folders = null;

        return $name;
    }

    /** A sent mail into Sent, for servers that do not keep it themselves. */
    public function saveSent(string $raw): void
    {
        $sent = $this->folderFor('sent');
        if ($sent !== null) {
            $this->imap()->append($sent, $raw, ['\\Seen']);
        }
    }

    /** A draft into Drafts; the draft it replaces goes away. */
    public function saveDraft(string $raw, ?int $replaceUid = null): string
    {
        $drafts = $this->folderFor('drafts');
        if ($drafts === null) {
            throw new MailException('下書きのフォルダーがありません。');
        }
        $this->imap()->append($drafts, $raw, ['\\Seen', '\\Draft']);
        if ($replaceUid) {
            $this->discardDraft($replaceUid);
        }

        return $drafts;
    }

    public function discardDraft(int $uid): void
    {
        $drafts = $this->folderFor('drafts', false);
        if ($drafts === null) {
            return;
        }
        $this->select($drafts);
        $this->imap()->deleteForever([$uid]);
    }

    /** Mark the mail answered after a reply. */
    public function markAnswered(string $folder, int $uid): void
    {
        try {
            $this->select($folder);
            $this->imap()->store([$uid], true, ['\\Answered']);
        } catch (MailException $e) {
            // the reply went out; the mark is a nicety
        }
    }

    /**
     * Unread counts of the inbox, for the badge and the new-mail check.
     *
     * @return array{unread: int, uidnext: int}
     */
    public function inboxState(): array
    {
        $st = $this->imap()->status('INBOX', ['UNSEEN', 'UIDNEXT']);

        return ['unread' => intval($st['UNSEEN'] ?? 0), 'uidnext' => intval($st['UIDNEXT'] ?? 0)];
    }
}
