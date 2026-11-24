<?php

namespace App\Plugins\ExmentGroupware\Mail;

use Illuminate\Http\Request;

/**
 * The Web mail screens and their JSON API (ISS00233 P2).
 *
 * Every call works on an account of the signed-in user only. Errors the
 * user can act on (a wrong password, a folder moved away) come back as
 * {ok: false, message} with status 422; anything else is logged and shown
 * as a short, general message.
 */
class WebMail
{
    /** @var \Exceedone\Exment\Model\Plugin */
    protected $plugin;

    /** @var OAuth */
    protected $oauth;

    /** Uploads of one mail, in total. Most servers refuse much more than this. */
    const MAX_ATTACH_BYTES = 25 * 1024 * 1024;

    /** @param \Exceedone\Exment\Model\Plugin $plugin */
    public function __construct($plugin)
    {
        $this->plugin = $plugin;
        $this->oauth = new OAuth($plugin);
        HostGuard::allowPrivate(boolval($plugin->getCustomOption('mail_allow_private')));
    }

    public function url(string $path = ''): string
    {
        return $this->plugin->getFullUrl(trim('mail/' . $path, '/'));
    }

    protected function userId(): int
    {
        $id = Accounts::currentUserId();
        if ($id === null) {
            abort(403);
        }

        return $id;
    }

    protected function account($id): object
    {
        $a = Accounts::find(intval($id), $this->userId());
        if ($a === null) {
            abort(404);
        }

        return $a;
    }

    /** @param callable $fn */
    protected function json($fn)
    {
        try {
            $data = $fn();

            return response()->json(array_merge(['ok' => true], is_array($data) ? $data : []));
        } catch (MailException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        } catch (\Throwable $e) {
            \Log::error('[ExmentGroupware] mail: ' . $e->getMessage(), ['exception' => $e]);

            return response()->json(['ok' => false, 'message' => 'エラーが起きました。しばらくしてからやり直してください。'], 500);
        }
    }

    // ---- screens -----------------------------------------------------------

    /** The mail program: accounts and folders, list, reading pane. */
    public function page()
    {
        if (!Accounts::ready()) {
            return $this->screen('メール', view('exment_exment_groupware::mail_notready', ['plugin_url' => $this->plugin->getFullUrl()]));
        }
        $accounts = array_map([Accounts::class, 'toPublic'], Accounts::forUser($this->userId()));
        if (empty($accounts)) {
            return redirect($this->url('accounts'));
        }

        return $this->screen('メール', view('exment_exment_groupware::mail', $this->mailViewData($accounts)));
    }

    /**
     * The time zone of the user for the dates of the mails ('' when the core has no user time zone).
     */
    public static function userTimezone(): string
    {
        $service = '\Exceedone\Exment\Services\UserTimezone';
        if (!class_exists($service)) {
            return '';
        }
        try {
            return strval($service::user());
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * @param array<int, mixed> $accounts
     * @return array<string, mixed>
     */
    protected function mailViewData(array $accounts): array
    {
        return [
            'accounts_url' => $this->url('accounts'),
            // For the script of the page (one @json: Blade cannot take an array literal over lines there).
            'cfg' => [
                'base' => $this->url(),
                'csrf' => csrf_token(),
                'accounts' => $accounts,
                'accountsUrl' => $this->url('accounts'),
                'maxAttach' => static::MAX_ATTACH_BYTES,
                // The time zone the dates of the mails are shown in: the one the user chose in
                // Exment (7.2.4), else the site's. '' on an older core: the browser's, as before.
                'timezone' => static::userTimezone(),
            ],
        ];
    }

    /** Adding and changing accounts. */
    public function accounts()
    {
        if (!Accounts::ready()) {
            return $this->screen('メール', view('exment_exment_groupware::mail_notready', ['plugin_url' => $this->plugin->getFullUrl()]));
        }
        $rows = Accounts::forUser($this->userId());
        $accounts = [];
        foreach ($rows as $a) {
            $accounts[] = array_merge(Accounts::toPublic($a), [
                'username' => strval($a->username),
                'imap_host' => strval($a->imap_host),
                'imap_port' => intval($a->imap_port),
                'imap_security' => strval($a->imap_security),
                'smtp_host' => strval($a->smtp_host),
                'smtp_port' => intval($a->smtp_port),
                'smtp_security' => strval($a->smtp_security),
                'append_sent' => boolval($a->append_sent),
                'last_connected_at' => $a->last_connected_at,
            ]);
        }
        $providers = Providers::all();
        foreach ($providers as $key => &$p) {
            $p['oauth_ready'] = in_array('oauth', $p['auth'], true) ? $this->oauth->configured($key) : false;
        }
        unset($p);
        $user = \Exment::user();
        $userEmail = isset($user) && isset($user->base_user) ? strval(array_get($user->base_user->value, 'email')) : '';

        return $this->screen('メール', view('exment_exment_groupware::mail_accounts', [
            'accounts' => $accounts,
            'providers' => $providers,
            'mail_url' => $this->url(),
            'base' => $this->url(),
            'user_name' => isset($user) && isset($user->base_user) ? strval($user->base_user->getLabel()) : '',
            'is_admin' => isset($user) && $user->hasPermission(\Exceedone\Exment\Enums\Permission::SYSTEM),
            'settings_url' => admin_urls('plugin', $this->plugin->id, 'edit'),
            'cfg' => [
                'providers' => $providers,
                'accounts' => $accounts,
                'save' => $this->url('account'),
                'base' => $this->url(),
                'csrf' => csrf_token(),
                'userEmail' => $userEmail,
            ],
        ]));
    }

    /**
     * The screen with its own title ("メール" instead of the plugin's name
     * "Exment Plugin Groupware"): the same page Exment makes for a plugin page,
     * only with another header.
     *
     * @param \Illuminate\Contracts\View\View $view
     * @return mixed
     */
    protected function screen(string $title, $view)
    {
        foreach (['ExmentAdminCore\Admin\Layout\Content', 'Encore\Admin\Layout\Content'] as $class) {
            if (class_exists($class)) {
                $content = new $class();
                $content->row($view->render());

                return response($content->header($title)->headericon('fa-envelope-o'));
            }
        }

        return $view;
    }

    /**
     * Save an account. A password account is tried (IMAP and SMTP) before it
     * is saved; an OAuth account goes to the provider's sign-in page first.
     */
    public function postAccount(Request $request)
    {
        return $this->json(function () use ($request) {
            $userId = $this->userId();
            $id = intval($request->input('id'));
            $existing = $id > 0 ? Accounts::find($id, $userId) : null;
            if ($id > 0 && $existing === null) {
                abort(404);
            }
            $provider = strval($request->input('provider', $existing->provider ?? Providers::OTHER));
            if (!Providers::exists($provider)) {
                throw new MailException('メールのサービスを選んでください。');
            }
            $p = Providers::get($provider);
            $auth = strval($request->input('auth_type', $p['auth'][0]));
            if (!in_array($auth, $p['auth'], true)) {
                $auth = $p['auth'][0];
            }

            $email = mb_strtolower(trim(strval($request->input('email'))));
            if ($auth !== 'oauth' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                throw new MailException('メールアドレスを入れてください。');
            }
            $values = [
                'name' => mb_substr(trim(strval($request->input('name'))) ?: ($email ?: strval($p['short'])), 0, 100),
                'email' => $email,
                'from_name' => mb_substr(trim(strval($request->input('from_name'))), 0, 100) ?: null,
                'provider' => $provider,
                'auth_type' => $auth,
                'signature' => mb_substr(strval($request->input('signature')), 0, 5000) ?: null,
            ];

            if (in_array($provider, [Providers::MICROSOFT, Providers::GMAIL], true)) {
                // Fixed servers: nothing to type.
                $values += [
                    'imap_host' => $p['imap_host'], 'imap_port' => $p['imap_port'], 'imap_security' => $p['imap_security'],
                    'smtp_host' => $p['smtp_host'], 'smtp_port' => $p['smtp_port'], 'smtp_security' => $p['smtp_security'],
                    'append_sent' => $p['append_sent'] ? 1 : 0,
                ];
            } else {
                $imapHost = static::host(strval($request->input('imap_host')));
                if ($imapHost === '') {
                    throw new MailException('受信（IMAP）サーバーを入れてください。例: ' . $p['host_hint']);
                }
                $smtpHost = static::host(strval($request->input('smtp_host')));
                if ($smtpHost === '') {
                    $smtpHost = $p['smtp_same_host'] ? $imapHost : strval($p['smtp_host']);
                }
                if ($smtpHost === '') {
                    throw new MailException('送信（SMTP）サーバーを入れてください。');
                }
                $values += [
                    'imap_host' => $imapHost,
                    'imap_port' => static::port($request->input('imap_port'), intval($p['imap_port'])),
                    'imap_security' => static::security(strval($request->input('imap_security', $p['imap_security']))),
                    'smtp_host' => $smtpHost,
                    'smtp_port' => static::port($request->input('smtp_port'), intval($p['smtp_port'])),
                    'smtp_security' => static::security(strval($request->input('smtp_security', $p['smtp_security']))),
                    'append_sent' => $request->has('append_sent') ? ($request->boolean('append_sent') ? 1 : 0) : ($p['append_sent'] ? 1 : 0),
                ];
            }

            if ($auth === 'oauth') {
                // Who it is comes from the sign-in; the address typed is only a hint.
                $then = $existing ? ['account_id' => intval($existing->id), 'values' => $values] : ['values' => $values];

                return ['redirect' => $this->oauth->authorizeUrl($provider, $then, $email ?: null)];
            }

            $username = trim(strval($request->input('username')));
            if ($username === '') {
                $username = $p['username_is_email'] ? $email : '';
            }
            if ($username === '') {
                throw new MailException('ユーザー名（' . $p['username_help'] . '）を入れてください。');
            }
            $values['username'] = $username;
            $password = strval($request->input('password'));
            if ($password === '' && $existing !== null && $existing->auth_type === 'password') {
                $secret = Accounts::secret($existing);
                $password = strval($secret['password'] ?? '');
            }
            if ($password === '') {
                throw new MailException('パスワードを入れてください。');
            }
            $values['secret_enc'] = Accounts::encryptSecret(['password' => $password]);
            $values['token_expires_at'] = null;

            // Try it before saving, so a typo shows now and not on the first mail.
            $probe = (object) array_merge(['id' => 0, 'last_error' => null, 'sent_folder' => null, 'drafts_folder' => null, 'trash_folder' => null, 'junk_folder' => null], $values);
            static::tryAccount($probe, $this->oauth);

            $values['last_error'] = null;
            $values['last_connected_at'] = now();
            if ($existing !== null) {
                Accounts::update(intval($existing->id), $values);
                $id = intval($existing->id);
            } else {
                $id = Accounts::create($userId, $values);
            }

            return ['id' => $id, 'message' => 'つながりました。保存しました。', 'redirect' => $this->url() . '?account=' . $id];
        });
    }

    /** IMAP sign-in and folder list, then SMTP sign-in. */
    public static function tryAccount(object $account, OAuth $oauth): void
    {
        $box = new Mailbox($account, $oauth);
        try {
            $box->imap();
            $box->folders(false);
        } catch (MailException $e) {
            throw new MailException('受信（IMAP）: ' . $e->getMessage());
        } finally {
            $box->close();
        }
        (new Sender($account, $oauth))->test();
    }

    public function postAccountDelete($id)
    {
        return $this->json(function () use ($id) {
            $a = $this->account($id);
            Accounts::delete(intval($a->id), $this->userId());

            return ['message' => 'アカウントを外しました（サーバーのメールはそのまま残っています）。'];
        });
    }

    /** Sign in again (an expired or revoked OAuth account). */
    public function signin($id)
    {
        $a = $this->account($id);
        try {
            return redirect($this->oauth->authorizeUrl(strval($a->provider), ['account_id' => intval($a->id)], strval($a->email)));
        } catch (MailException $e) {
            static::toast($e->getMessage(), 'error');

            return redirect($this->url('accounts'));
        }
    }

    /** Back from Microsoft or Google. */
    public function oauthCallback(Request $request)
    {
        if ($request->filled('error')) {
            // Only fixed words: anyone can make this URL (error_description is not shown).
            $code = strval($request->input('error'));
            static::toast($code === 'access_denied' ? 'サインインを取り消しました。' : 'サインインできませんでした' . (preg_match('/\A[a-z_]{1,40}\z/', $code) === 1 ? '（' . $code . '）' : '') . '。', 'error');

            return redirect($this->url('accounts'));
        }
        try {
            $userId = $this->userId();
            $r = $this->oauth->finish(strval($request->input('state')), strval($request->input('code')));
            $then = $r['then'];
            $values = (array) ($then['values'] ?? []);
            $values['email'] = $r['email'];
            $values['username'] = $r['email'];
            $values['auth_type'] = 'oauth';
            $values['provider'] = $r['provider'];
            $values['secret_enc'] = Accounts::encryptSecret($r['tokens']);
            $values['token_expires_at'] = $r['expires_at'];
            if (empty($values['name'])) {
                $values['name'] = $r['email'];
            }
            if (empty($values['from_name']) && $r['name'] !== '' && empty($then['account_id'])) {
                $values['from_name'] = $r['name'];
            }
            $p = Providers::get($r['provider']);
            foreach (['imap_host', 'imap_port', 'imap_security', 'smtp_host', 'smtp_port', 'smtp_security'] as $k) {
                $values[$k] = $values[$k] ?? $p[$k];
            }
            $values['append_sent'] = $values['append_sent'] ?? ($p['append_sent'] ? 1 : 0);

            $existing = !empty($then['account_id']) ? Accounts::find(intval($then['account_id']), $userId) : null;
            if ($existing !== null) {
                // The same mailbox only: signing in as someone else makes a new account.
                if (mb_strtolower(strval($existing->email)) !== $r['email'] && $existing->email !== '') {
                    $existing = null;
                }
            }
            $values['last_error'] = null;
            if ($existing !== null) {
                Accounts::update(intval($existing->id), $values);
                $id = intval($existing->id);
            } else {
                $id = Accounts::create($userId, $values);
            }
            $account = Accounts::find($id, $userId);
            try {
                if ($account !== null) {
                    static::tryAccount($account, $this->oauth);
                    Accounts::recordError($id, null);
                }
                static::toast('サインインしました: ' . $r['email']);
            } catch (MailException $e) {
                Accounts::recordError($id, $e->getMessage());
                static::toast('サインインはできましたが、メールのサーバーに入れませんでした: ' . $e->getMessage(), 'warning');
            }

            return redirect($this->url() . '?account=' . $id);
        } catch (MailException $e) {
            static::toast($e->getMessage(), 'error');

            return redirect($this->url('accounts'));
        }
    }

    // ---- API ---------------------------------------------------------------

    protected function box($id): Mailbox
    {
        return new Mailbox($this->account($id), $this->oauth);
    }

    public function apiFolders($id)
    {
        return $this->json(function () use ($id) {
            return ['folders' => $this->box($id)->folders()];
        });
    }

    public function apiMessages(Request $request, $id)
    {
        return $this->json(function () use ($request, $id) {
            $filter = strval($request->input('filter', 'all'));

            return $this->box($id)->messages(
                strval($request->input('folder', 'INBOX')),
                intval($request->input('page', 1)),
                mb_substr(trim(strval($request->input('q', ''))), 0, 200),
                in_array($filter, ['all', 'unread', 'flagged'], true) ? $filter : 'all'
            );
        });
    }

    public function apiMessage(Request $request, $id)
    {
        return $this->json(function () use ($request, $id) {
            return ['message' => $this->box($id)->message(strval($request->input('folder', 'INBOX')), intval($request->input('uid')), !$request->boolean('peek'))];
        });
    }

    public function apiAttachment(Request $request, $id)
    {
        try {
            $att = $this->box($id)->attachment(strval($request->input('folder', 'INBOX')), intval($request->input('uid')), intval($request->input('index')));
        } catch (MailException $e) {
            abort(404, $e->getMessage());
        }
        $inline = $request->boolean('inline') && preg_match('#^(image/(png|jpeg|gif|webp)|application/pdf)$#', $att['type']) === 1;

        return response($att['content'], 200, [
            'Content-Type' => $inline ? $att['type'] : 'application/octet-stream',
            'Content-Disposition' => ($inline ? 'inline' : 'attachment') . "; filename*=UTF-8''" . rawurlencode($att['name']),
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control' => 'private, max-age=0',
        ]);
    }

    public function apiEml(Request $request, $id)
    {
        try {
            $raw = $this->box($id)->raw(strval($request->input('folder', 'INBOX')), intval($request->input('uid')));
        } catch (MailException $e) {
            abort(404, $e->getMessage());
        }

        return response($raw, 200, [
            'Content-Type' => 'message/rfc822',
            'Content-Disposition' => 'attachment; filename="mail-' . intval($request->input('uid')) . '.eml"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** @return array<int, int> */
    protected static function uids(Request $request): array
    {
        $uids = array_values(array_filter(array_map('intval', (array) $request->input('uids', [])), function ($u) {
            return $u > 0;
        }));
        if (empty($uids)) {
            throw new MailException('メールを選んでください。');
        }

        return array_slice($uids, 0, 500);
    }

    public function apiFlag(Request $request, $id)
    {
        return $this->json(function () use ($request, $id) {
            $this->box($id)->flag(strval($request->input('folder')), static::uids($request), strval($request->input('flag')), $request->boolean('on'));

            return [];
        });
    }

    public function apiMove(Request $request, $id)
    {
        return $this->json(function () use ($request, $id) {
            $box = $this->box($id);
            $target = strval($request->input('target'));
            if (in_array($target, ['@trash', '@junk', '@archive', '@inbox'], true)) {
                $target = $box->folderFor(substr($target, 1), $target !== '@archive') ?? '';
                if ($target === '') {
                    throw new MailException('移動先のフォルダーがありません。');
                }
            }
            $box->move(strval($request->input('folder')), static::uids($request), $target);

            return [];
        });
    }

    public function apiDelete(Request $request, $id)
    {
        return $this->json(function () use ($request, $id) {
            $done = $this->box($id)->delete(strval($request->input('folder')), static::uids($request), $request->boolean('forever'));

            return ['result' => $done];
        });
    }

    public function apiCreateFolder(Request $request, $id)
    {
        return $this->json(function () use ($request, $id) {
            $parent = $request->filled('parent') ? strval($request->input('parent')) : null;

            return ['name' => $this->box($id)->createFolder(strval($request->input('name')), $parent)];
        });
    }

    /**
     * What the composer sent, as a message to build.
     *
     * @return array<string, mixed>
     */
    protected function composed(Request $request, Mailbox $box): array
    {
        $attachments = [];
        $bytes = 0;
        // Attachments kept from the mail being forwarded, or from the draft.
        $from = (array) $request->input('keep', []);
        if (!empty($from) && $request->filled('source_folder') && $request->filled('source_uid')) {
            foreach ($from as $index) {
                $att = $box->attachment(strval($request->input('source_folder')), intval($request->input('source_uid')), intval($index));
                $bytes += strlen($att['content']);
                $attachments[] = $att;
            }
        }
        foreach ((array) $request->file('files', []) as $file) {
            if (!$file instanceof \Illuminate\Http\UploadedFile || !$file->isValid()) {
                throw new MailException('添付ファイルを受け取れませんでした（大きすぎる可能性があります）。');
            }
            $bytes += intval($file->getSize());
            $attachments[] = ['name' => $file->getClientOriginalName(), 'type' => strval($file->getMimeType() ?: 'application/octet-stream'), 'content' => strval(file_get_contents($file->getRealPath()))];
        }
        if ($bytes > static::MAX_ATTACH_BYTES) {
            throw new MailException('添付ファイルは合わせて ' . intval(static::MAX_ATTACH_BYTES / 1024 / 1024) . ' MB までです。大きいファイルは、ファイル共有の共有リンクで送ってください。');
        }

        return [
            'to' => strval($request->input('to', '')),
            'cc' => strval($request->input('cc', '')),
            'bcc' => strval($request->input('bcc', '')),
            'subject' => strval($request->input('subject', '')),
            'html' => strval($request->input('html', '')),
            'in_reply_to' => strval($request->input('in_reply_to', '')),
            'references' => array_filter(preg_split('/\s+/', strval($request->input('references', ''))) ?: []),
            'attachments' => $attachments,
        ];
    }

    public function apiSend(Request $request, $id)
    {
        return $this->json(function () use ($request, $id) {
            $account = $this->account($id);
            $box = new Mailbox($account, $this->oauth);
            $sender = new Sender($account, $this->oauth);
            $email = $sender->build($this->composed($request, $box));
            $raw = $sender->send($email);

            $notes = [];
            if ($account->append_sent) {
                try {
                    $box->saveSent($raw);
                } catch (MailException $e) {
                    $notes[] = '送信はできましたが、送信済みフォルダーに保存できませんでした: ' . $e->getMessage();
                }
            }
            if ($request->filled('reply_uid') && $request->filled('reply_folder')) {
                $box->markAnswered(strval($request->input('reply_folder')), intval($request->input('reply_uid')));
            }
            if ($request->filled('draft_uid')) {
                try {
                    $box->discardDraft(intval($request->input('draft_uid')));
                } catch (MailException $e) {
                    // an old draft left behind is harmless
                }
            }

            return ['message' => empty($notes) ? '送信しました。' : implode(' ', $notes)];
        });
    }

    public function apiDraft(Request $request, $id)
    {
        return $this->json(function () use ($request, $id) {
            $account = $this->account($id);
            $box = new Mailbox($account, $this->oauth);
            $m = $this->composed($request, $box);
            $email = (new Sender($account, $this->oauth))->build($m);
            $folder = $box->saveDraft(Sender::draftRaw($email), $request->filled('draft_uid') ? intval($request->input('draft_uid')) : null);
            // The new draft's UID, so the next save replaces it.
            $uid = null;
            $found = $box->messages($folder, 1);
            foreach ($found['items'] as $item) {
                if ($item['draft']) {
                    $uid = $item['uid'];
                    break;
                }
            }

            return ['message' => '下書きに保存しました。', 'draft_uid' => $uid, 'folder' => $folder];
        });
    }

    /** Unread counts of every inbox, for the badges and the new-mail notice. */
    public function apiPoll()
    {
        return $this->json(function () {
            $out = [];
            foreach (Accounts::forUser($this->userId()) as $a) {
                if ($a->auth_type === 'oauth' && empty($a->secret_enc)) {
                    continue;
                }
                $box = new Mailbox($a, $this->oauth);
                try {
                    $out[intval($a->id)] = $box->inboxState();
                } catch (MailException $e) {
                    $out[intval($a->id)] = ['error' => $e->getMessage()];
                } finally {
                    $box->close();
                }
            }

            return ['accounts' => $out];
        });
    }

    // ---- input -------------------------------------------------------------

    protected static function host(string $host): string
    {
        $host = mb_strtolower(trim($host));
        $host = preg_replace('#^[a-z]+://#', '', $host) ?? $host;
        $host = rtrim(strval(strtok($host, '/:')), '.');
        if ($host === '' || preg_match('/^[a-z0-9.\-]+$/', $host) !== 1) {
            return '';
        }

        return $host;
    }

    /** @param mixed $value */
    protected static function port($value, int $default): int
    {
        $port = intval($value);

        return $port > 0 && $port < 65536 ? $port : $default;
    }

    protected static function security(string $value): string
    {
        return in_array($value, ['ssl', 'tls', 'none'], true) ? $value : 'ssl';
    }

    /**
     * A message at the top of the next page. Laravel-admin puts the text into
     * a JavaScript string and toastr shows it as HTML, so it is escaped here
     * (messages can carry words from a mail server).
     */
    public static function toast(string $message, string $level = 'success'): void
    {
        $message = preg_replace('/[\x00-\x1F\x7F\\\\\x{2028}\x{2029}]/u', ' ', $message) ?? '';
        admin_toastr(htmlspecialchars(mb_substr($message, 0, 300), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), $level);
    }
}
