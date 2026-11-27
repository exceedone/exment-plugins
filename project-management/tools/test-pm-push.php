<?php
// ExmentPM: Web Push for the bells (PortalPush, PortalPushCrypto; docs/pwa-design.md step 2).
// Plain PHP with openssl, no network: the push service is a fake HTTP client (a closure), the
// rows an in-memory store, the application key a test key.
//   php project-management/tools/test-pm-push.php
//
// - RFC 8291's example (section 5): the same body byte for byte; a round trip with new keys
// - RFC 8292: the JWT (ES256, aud, sub = the site's address, no e-mail address) checks out
// - the endpoint allowlist; subscribe / unsubscribe (the person's own only)
// - 404 / 410 / 5 failures remove a subscription; the payload is the subject (<= 120) and a link only
// - the private key is kept encrypted

namespace {
    // the application key (Laravel's encrypt() / decrypt()): AES with a key of this test only
    $GLOBALS['__test_app_key'] = random_bytes(32);
    function encrypt($value)
    {
        $iv = random_bytes(16);
        $c = $iv . openssl_encrypt(serialize($value), 'aes-256-cbc', $GLOBALS['__test_app_key'], OPENSSL_RAW_DATA, $iv);

        return base64_encode(hash_hmac('sha256', $c, $GLOBALS['__test_app_key'], true) . $c);
    }
    function decrypt($payload)
    {
        $raw = strval(base64_decode($payload));
        $c = substr($raw, 32);
        $plain = strlen($raw) > 48 && hash_equals(hash_hmac('sha256', $c, $GLOBALS['__test_app_key'], true), substr($raw, 0, 32))
            ? openssl_decrypt(substr($c, 16), 'aes-256-cbc', $GLOBALS['__test_app_key'], OPENSSL_RAW_DATA, substr($c, 0, 16)) : false;
        if ($plain === false) {
            // as Laravel's: a payload of another key throws
            throw new \RuntimeException('The payload is invalid.');
        }

        return unserialize($plain);
    }
    // APP_URL and the address of the request (a test changes them)
    $GLOBALS['__test_app_url'] = 'https://pm.example.invalid/';
    $GLOBALS['__test_url'] = 'https://pm.example.invalid';
    function config($key)
    {
        return $key === 'app.url' ? $GLOBALS['__test_app_url'] : null;
    }
    function url($path = null)
    {
        return $GLOBALS['__test_url'];
    }
    $GLOBALS['__test_terminating'] = [];
    function app()
    {
        return new class () {
            public function terminating($f)
            {
                $GLOBALS['__test_terminating'][] = $f;
            }
        };
    }
}

namespace Illuminate\Support\Facades {
    class Log
    {
        public static $lines = [];

        public static function warning($m)
        {
            static::$lines[] = $m;
        }
    }
}

namespace Illuminate\Contracts\Cache {
    // what a cache lock throws when it is still held after the wait
    class LockTimeoutException extends \Exception
    {
    }
}

namespace Illuminate\Queue\Jobs {
    // the job of the sync queue (run inside the request) and one of a worker
    class SyncJob
    {
    }
    class RedisJob
    {
    }
}

namespace App\Plugins\ExmentPM {
    class PortalDefinition
    {
        public static function url()
        {
            return 'https://pm.example.invalid/admin/plugins/exment_pm';
        }
    }
}

namespace {
    require __DIR__ . '/../ExmentPM/PortalPushCrypto.php';
    require __DIR__ . '/../ExmentPM/PortalPushStore.php';
    require __DIR__ . '/../ExmentPM/PortalChannels.php';
    require __DIR__ . '/../ExmentPM/PortalPush.php';

    use App\Plugins\ExmentPM\PortalPush as P;
    use App\Plugins\ExmentPM\PortalPushCrypto as C;
    use App\Plugins\ExmentPM\PortalPushStore;

    /** The rows in memory. */
    class MemoryStore extends PortalPushStore
    {
        public $subs = [];
        public $keyRow = null;
        public $next = 1;

        public function exists(): bool
        {
            return true;
        }

        public function forUser(int $userId): array
        {
            $out = array_values(array_filter($this->subs, function ($r) use ($userId) {
                return $r['user_id'] === $userId;
            }));
            usort($out, function ($a, $b) {
                return $b['id'] <=> $a['id'];
            });

            return $out;
        }

        public function byHash(string $hash): ?array
        {
            foreach ($this->subs as $r) {
                if ($r['endpoint_hash'] === $hash) {
                    return $r;
                }
            }

            return null;
        }

        public function insert(array $row): int
        {
            $id = $this->next++;
            $this->subs[$id] = array_merge(['failures' => 0, 'last_sent_at' => null], $row, ['id' => $id]);

            return $id;
        }

        public function update(int $id, array $values): void
        {
            if (isset($this->subs[$id])) {
                $this->subs[$id] = array_merge($this->subs[$id], $values);
            }
        }

        public function delete(int $id): void
        {
            unset($this->subs[$id]);
        }

        public function deleteAll(): int
        {
            $n = count($this->subs);
            $this->subs = [];

            return $n;
        }

        public function keys(): ?array
        {
            return $this->keyRow;
        }

        public $locks = 0;

        public function locked(callable $f)
        {
            $this->locks++;

            return $f();
        }

        public function putKeys(string $public, string $privateEnc): void
        {
            $this->keyRow = ['public_key' => $public, 'private_enc' => $privateEnc];
        }
    }

    $ok = 0;
    $bad = 0;
    $check = function ($label, $cond, $extra = '') use (&$ok, &$bad) {
        echo($cond ? 'OK  ' : 'NG  '), $label, $cond ? '' : '  ' . var_export($extra, true), "\n";
        $cond ? $ok++ : $bad++;
    };

    /** The browser's side of RFC 8291: decrypt a body with the browser's private key. */
    function uaDecrypt(string $body, string $uaPrivatePem, string $uaPublic, string $auth): ?string
    {
        $salt = substr($body, 0, 16);
        $rs = unpack('N', substr($body, 16, 4))[1];
        $idlen = ord($body[20]);
        $asPublic = substr($body, 21, $idlen);
        $rest = substr($body, 21 + $idlen);
        if ($rs < 18 || strlen($rest) > $rs) {
            return null;
        }
        $secret = openssl_pkey_derive(C::publicPem($asPublic), $uaPrivatePem, 32);
        $ikm = hash_hkdf('sha256', $secret, 32, "WebPush: info\0" . $uaPublic . $asPublic, $auth);
        $cek = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salt);
        $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $salt);
        $plain = openssl_decrypt(substr($rest, 0, -16), 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, substr($rest, -16));
        if ($plain === false) {
            return null;
        }
        $plain = rtrim($plain, "\0");

        return substr($plain, -1) === "\x02" ? substr($plain, 0, -1) : null;
    }

    // ---- RFC 8291, section 5 -------------------------------------------------
    $v = [
        'plain' => 'When I grow up, I want to be a watermelon',
        'as_private' => 'yfWPiYE-n46HLnH0KqZOF1fJJU3MYrct3AELtAQ-oRw',
        'as_public' => 'BP4z9KsN6nGRTbVYI_c7VJSPQTBtkgcy27mlmlMoZIIgDll6e3vCYLocInmYWAmS6TlzAC8wEqKK6PBru3jl7A8',
        'ua_private' => 'q1dXpw3UpT5VOmu_cf_v6ih07Aems3njxI-JWgLcM94',
        'ua_public' => 'BCVxsr7N_eNgVRqvHtD0zTZsEc6-VV-JvLexhqUzORcxaOzi6-AYWXvTBHm4bjyPjs7Vd8pZGH6SRpkNtoIAiw4',
        'salt' => 'DGv6ra1nlYgDCS1FRnbzlw',
        'auth' => 'BTBZMqHH6r4Tts7J_aSIgg',
        'body' => 'DGv6ra1nlYgDCS1FRnbzlwAAEABBBP4z9KsN6nGRTbVYI_c7VJSPQTBtkgcy27mlmlMoZIIgDll6e3vCYLocInmYWAmS6TlzAC8wEqKK6PBru3jl7A_yl95bQpu6cVPTpK4Mqgkf1CXztLVBSt2Ks3oZwbuwXPXLWyouBWLVWGNWQexSgSxsj_Qulcy4a-fN',
    ];
    $asPem = C::privatePem(C::unb64u($v['as_private']), C::unb64u($v['as_public']));
    $uaPub = C::unb64u($v['ua_public']);
    $uaPem = C::privatePem(C::unb64u($v['ua_private']), $uaPub);
    $check('RFC 8291: the example keys are pairs', C::publicOf($asPem) === C::unb64u($v['as_public']) && C::publicOf($uaPem) === $uaPub);
    $body = C::encrypt($v['plain'], $uaPub, C::unb64u($v['auth']), ['public' => C::unb64u($v['as_public']), 'private' => $asPem], C::unb64u($v['salt']));
    $check('RFC 8291: the body of the example, byte for byte', C::b64u($body) === $v['body'], C::b64u($body));
    $check('RFC 8291: the header (salt, rs 4096, keyid = the server key)', substr($body, 0, 16) === C::unb64u($v['salt']) && unpack('N', substr($body, 16, 4))[1] === 4096 && ord($body[20]) === 65 && substr($body, 21, 65) === C::unb64u($v['as_public']));
    $check('RFC 8291: the browser reads the example back', uaDecrypt(C::unb64u($v['body']), $uaPem, $uaPub, C::unb64u($v['auth'])) === $v['plain']);

    // a round trip with keys made now (a new server key and salt each time)
    $ua = C::newKeyPair();
    $uaAuth = random_bytes(16);
    $b1 = C::encrypt('hello', $ua['public'], $uaAuth);
    $b2 = C::encrypt('hello', $ua['public'], $uaAuth);
    $check('a round trip with new keys', uaDecrypt($b1, $ua['private'], $ua['public'], $uaAuth) === 'hello');
    $check('a new server key and salt each message', substr($b1, 0, 86) !== substr($b2, 0, 86));
    $check('another auth secret cannot read it', uaDecrypt($b1, $ua['private'], $ua['public'], random_bytes(16)) === null);
    $threw = false;
    try {
        C::encrypt('x', str_repeat("\x04", 65), $uaAuth);
    } catch (\InvalidArgumentException $e) {
        $threw = true;
    }
    $check('a key that is not a point of P-256 is refused', $threw && !C::validPublic(str_repeat("\x04", 65)) && !C::validPublic('abc'));

    // ---- RFC 8292 (VAPID) --------------------------------------------------
    $site = C::newKeyPair();
    $hdr = C::vapid('https://fcm.googleapis.com/fcm/send/abc', 'https://pm.example.invalid', $site['public'], $site['private'], 1000);
    $okHdr = preg_match('/^vapid t=([A-Za-z0-9_-]+)\.([A-Za-z0-9_-]+)\.([A-Za-z0-9_-]+), k=([A-Za-z0-9_-]+)$/', $hdr, $m);
    $check('VAPID: "vapid t=<JWT>, k=<key>"', $okHdr === 1, $hdr);
    $jh = json_decode(C::unb64u($m[1]), true);
    $jc = json_decode(C::unb64u($m[2]), true);
    $check('VAPID: ES256 JWT', $jh === ['typ' => 'JWT', 'alg' => 'ES256'], $jh);
    $check('VAPID: aud is the push service\'s origin, exp within a day', $jc['aud'] === 'https://fcm.googleapis.com' && $jc['exp'] === 1000 + 12 * 3600, $jc);
    $check('VAPID: sub is the site\'s address, no e-mail address', $jc['sub'] === 'https://pm.example.invalid' && strpos(json_encode($jc), '@') === false && strpos(json_encode($jc), 'mailto') === false, $jc);
    $check('VAPID: k is the site\'s public key', C::unb64u($m[4]) === $site['public']);
    $sig = C::unb64u($m[3]);
    $check('VAPID: the signature is r || s (64 bytes) and checks out', strlen($sig) === 64 && openssl_verify($m[1] . '.' . $m[2], C::rawToDer($sig), C::publicPem($site['public']), OPENSSL_ALGO_SHA256) === 1);
    $other = C::newKeyPair();
    $check('VAPID: another key does not check it', openssl_verify($m[1] . '.' . $m[2], C::rawToDer($sig), C::publicPem($other['public']), OPENSSL_ALGO_SHA256) !== 1);
    $check('DER <-> r || s round trip', C::derToRaw(C::rawToDer($sig)) === $sig);

    // ---- the allowlist -------------------------------------------------------
    foreach ([
        'https://fcm.googleapis.com/fcm/send/abc:def',
        'https://updates.push.services.mozilla.com/wpush/v2/gAAA',
        'https://web.push.apple.com/QGx1',
        'https://wns2-par02p.notify.windows.com/w/?token=AwYA',
        'https://FCM.googleapis.com/fcm/send/x',
        'https://fcm.googleapis.com:443/fcm/send/x',
    ] as $url) {
        $check('allowed: ' . $url, P::endpointAllowed($url));
    }
    foreach ([
        'http://fcm.googleapis.com/fcm/send/x' => 'http',
        'https://push.example.invalid/x' => 'another host',
        'https://fcm.googleapis.com.evil.invalid/x' => 'a lookalike',
        'https://evilfcm.googleapis.com/x' => 'another host of the domain',
        'https://push.apple.com/x' => 'the bare domain of a suffix',
        'https://evilpush.apple.com/x' => 'no dot before the suffix',
        'https://x.push.apple.com.invalid/x' => 'a suffix inside',
        'https://fcm.googleapis.com:8443/x' => 'another port',
        'https://user:pw@fcm.googleapis.com/x' => 'userinfo',
        'https://127.0.0.1/x' => 'an IP',
        'https://[::1]/x' => 'an IPv6',
        'https://fcm.googleapis.com./x' => 'a trailing dot',
        "https://fcm.googleapis.com/x\nHost: a" => 'a newline',
        'javascript:alert(1)' => 'not a URL',
        '' => 'empty',
        'https://fcm.googleapis.com/' . str_repeat('a', 2100) => 'too long',
    ] as $url => $why) {
        $check('refused (' . $why . ')', !P::endpointAllowed($url), $url);
    }

    // ---- subscribe / unsubscribe ---------------------------------------------
    $store = new MemoryStore();
    P::$store = $store;
    $sub = function (string $endpoint, ?array $pair = null, ?string $auth = null) {
        $pair = $pair ?? C::newKeyPair();

        return ['endpoint' => $endpoint, 'keys' => ['p256dh' => C::b64u($pair['public']), 'auth' => C::b64u($auth ?? random_bytes(16))], 'expirationTime' => null];
    };
    $ua1 = C::newKeyPair();
    $ua1Auth = random_bytes(16);
    $e1 = 'https://fcm.googleapis.com/fcm/send/test-device-1';
    $r = P::subscribe(7, $sub($e1, $ua1, $ua1Auth), 'Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 Chrome/131.0 Mobile Safari/537.36');
    $check('opt-in: a browser of a push service is kept', $r === ['ok' => true] && count($store->forUser(7)) === 1, $r);
    $row = $store->forUser(7)[0];
    $check('opt-in: the endpoint\'s sha256 is kept (unique key)', $row['endpoint_hash'] === hash('sha256', $e1) && strlen($row['endpoint_hash']) === 64);
    $check('opt-in: the device name from the user agent', $row['device'] === 'Chrome / Android', $row['device']);
    $check('at rest: the endpoint is encrypted (the application key), not in the row in plain', strpos($row['endpoint'], 'app:') === 0
        && strpos(json_encode($row), 'fcm.googleapis.com') === false && strpos(json_encode($row), 'test-device-1') === false, $row['endpoint']);
    $check('at rest: p256dh and auth encrypted, the plain columns empty', strpos($row['p256dh_enc'], 'app:') === 0 && strpos($row['auth_enc'], 'app:') === 0
        && $row['p256dh'] === '' && $row['auth'] === '' && strpos(json_encode($row), C::b64u($ua1['public'])) === false && strpos(json_encode($row), C::b64u($ua1Auth)) === false);
    $check('at rest: they decrypt to what the browser sent', P::open($row['endpoint']) === $e1 && P::open($row['p256dh_enc']) === C::b64u($ua1['public']) && P::open($row['auth_enc']) === C::b64u($ua1Auth));
    $check('a value encrypted with another key: none (not taken as plain)', @P::open('app:' . base64_encode(random_bytes(40))) === null && P::open('') === null);
    $check('opt-in: the state of this browser is on', P::subscribed(7, $e1) && !P::subscribed(8, $e1));
    $check('opt-in: an address on *.invalid is refused', P::subscribe(7, $sub('https://push.example.invalid/x'))['error'] === 'endpoint' && count($store->subs) === 1);
    $check('opt-in: http is refused', P::subscribe(7, $sub('http://fcm.googleapis.com/fcm/send/x'))['error'] === 'endpoint');
    $check('opt-in: a broken key is refused', P::subscribe(7, ['endpoint' => $e1 . 'x', 'keys' => ['p256dh' => C::b64u(str_repeat("\x04", 65)), 'auth' => C::b64u(random_bytes(16))]])['error'] === 'keys');
    $check('opt-in: an auth secret not 16 bytes is refused', P::subscribe(7, $sub($e1 . 'y', null, random_bytes(8)))['error'] === 'keys');
    $check('opt-in: no subscription at all is refused', P::subscribe(7, 'x')['error'] === 'bad' && P::subscribe(7, [])['error'] === 'endpoint');
    $check('opt-in: no user is refused', P::subscribe(0, $sub($e1))['ok'] === false);
    P::subscribe(7, $sub($e1, $ua1, $ua1Auth), 'Firefox/130.0');
    $check('opt-in again on the same browser: still one row', count($store->subs) === 1);
    P::subscribe(8, $sub($e1), '');
    $check('the same browser, another person: it changed hands', count($store->subs) === 1 && $store->forUser(8) && !$store->forUser(7));
    P::subscribe(7, $sub($e1, $ua1, $ua1Auth), '');
    $check('opt-out of another person\'s browser: nothing happens', P::unsubscribe(8, $e1) === false && count($store->forUser(7)) === 1);
    $check('opt-out: removed at once', P::unsubscribe(7, $e1) === true && count($store->subs) === 0 && !P::subscribed(7, $e1));
    $check('opt-out of a browser not kept: nothing', P::unsubscribe(7, $e1) === false);
    for ($i = 1; $i <= P::MAX_PER_USER + 2; $i++) {
        P::subscribe(9, $sub('https://web.push.apple.com/dev-' . $i), '');
    }
    $hashes = array_column($store->forUser(9), 'endpoint_hash');
    $check('at most ' . P::MAX_PER_USER . ' browsers a person: the oldest go', count($hashes) === P::MAX_PER_USER && !in_array(hash('sha256', 'https://web.push.apple.com/dev-1'), $hashes, true) && in_array(hash('sha256', 'https://web.push.apple.com/dev-12'), $hashes, true));
    $store->subs = [];

    // ---- the key pair, kept encrypted -----------------------------------------
    $check('no key pair yet: none without making one', P::siteKeys(false) === null);
    P::subscribe(7, $sub($e1, $ua1, $ua1Auth), '');
    $keys = P::siteKeys();
    $kr = $store->keyRow;
    $check('the key pair is made the first time (the browsers kept)', count($store->subs) === 1 && is_array($keys) && is_array($kr) && C::publicOf($keys['private']) === $keys['public']);
    $check('the private key is kept encrypted (the application key)', strpos($kr['private_enc'], 'app:') === 0
        && strpos($kr['private_enc'], 'PRIVATE KEY') === false && strpos(base64_decode(substr($kr['private_enc'], 4)), 'PRIVATE KEY') === false
        && strpos($kr['private_enc'], C::b64u(substr($keys['private'], 30, 40))) === false);
    $check('the public key is kept as the browsers take it', $kr['public_key'] === C::b64u($keys['public']) && P::publicKey() === $kr['public_key']);
    $check('the same pair next time', P::siteKeys()['public'] === $keys['public']);
    $GLOBALS['__test_app_key_saved'] = $GLOBALS['__test_app_key'];
    $GLOBALS['__test_app_key'] = random_bytes(32);
    $check('another application key: the pair cannot be read (no send)', @P::siteKeys(false) === null);
    $GLOBALS['__test_app_key'] = $GLOBALS['__test_app_key_saved'];
    $renew = P::renewKeys();
    $check('「鍵を作り直す」: a new pair, every subscription goes', $renew === 1 && count($store->subs) === 0 && P::siteKeys()['public'] !== $keys['public'], $renew);

    // ---- sending -------------------------------------------------------------
    $sent = [];
    $answers = [];
    // the push service: a fake HTTP client, nothing leaves this process
    $fake = function (string $url, array $headers, string $body) use (&$sent, &$answers) {
        $sent[] = ['url' => $url, 'headers' => $headers, 'body' => $body];

        return array_shift($answers) ?? 201;
    };
    $site = P::siteKeys();
    P::subscribe(7, $sub($e1, $ua1, $ua1Auth), '');
    $comment = 'SECRET-COMMENT-TEXT the body of a comment';
    $subject = '[PMD-1] ' . str_repeat('ログインできない ', 30);
    $res = P::sendTo(7, $subject, $fake, 'https://pm.example.invalid/admin/plugins/exment_pm/?notices=1');
    $check('a bell goes to the person\'s browser', $res['sent'] === 1 && count($sent) === 1, $res);
    $req = $sent[0];
    $check('to the subscription\'s endpoint', $req['url'] === $e1);
    $check('TTL one day, aes128gcm', $req['headers']['TTL'] === '86400' && $req['headers']['Content-Encoding'] === 'aes128gcm' && $req['headers']['Content-Type'] === 'application/octet-stream');
    $check('VAPID with the site\'s key', strpos($req['headers']['Authorization'], 'vapid t=') === 0 && strpos($req['headers']['Authorization'], 'k=' . C::b64u($site['public'])) !== false);
    $plain = uaDecrypt($req['body'], $ua1['private'], $ua1['public'], $ua1Auth);
    $msg = json_decode(strval($plain), true);
    $check('the browser reads the message', is_array($msg), $plain);
    $check('the message is the subject and the link only', is_array($msg) && array_keys($msg) === ['title', 'url'] && $msg['url'] === 'https://pm.example.invalid/admin/plugins/exment_pm/?notices=1', $msg);
    $check('the subject at most 120 characters', mb_strlen($msg['title']) <= 120 && mb_strlen($msg['title']) >= 100 && strpos($msg['title'], '[PMD-1] ログイン') === 0, mb_strlen($msg['title']));
    $check('the row: last sent, no failures', $store->forUser(7)[0]['failures'] === 0 && $store->forUser(7)[0]['last_sent_at'] !== null);
    $p = json_decode(P::payload("a\n  b <b>c</b>", 'u'), true);
    $check('the payload: one line, markup stays text', $p === ['title' => 'a b <b>c</b>', 'url' => 'u'], $p);
    $check('the payload: 120 characters stay whole', json_decode(P::payload(str_repeat('x', 120), 'u'), true)['title'] === str_repeat('x', 120));

    // after the answer: the bell as Exment writes it, with a comment in its body
    $bell = (object) ['target_user_id' => 7, 'notify_subject' => '[PMD-2] a comment was added', 'notify_body' => $comment, 'parent_type' => 'pm_issue', 'parent_id' => 3];
    $setOn = \Closure::bind(function ($v) {
        static::$on = $v;
    }, null, P::class);
    $setOn(false);
    P::bellCreated($bell);
    $check('the feature off: nothing kept', P::pendingCount() === 0);
    $setOn(true);
    $sent = [];
    P::bellCreated($bell);
    $check('a new bell is kept for after the answer, not sent in the request', P::pendingCount() === 1 && count($sent) === 0 && count($GLOBALS['__test_terminating']) === 1);
    P::bellCreated((object) ['target_user_id' => 0, 'notify_subject' => 'x']);
    $check('a bell of no user: not kept', P::pendingCount() === 1);
    P::$post = $fake;
    ($GLOBALS['__test_terminating'][0])();
    P::$post = null;
    $msg = json_decode(strval(uaDecrypt($sent[0]['body'] ?? '', $ua1['private'], $ua1['public'], $ua1Auth)), true);
    $check('after the answer it is sent', count($sent) === 1 && P::pendingCount() === 0);
    $check('the bell\'s subject and the link to the bell\'s list', is_array($msg) && $msg['title'] === '[PMD-2] a comment was added' && $msg['url'] === 'https://pm.example.invalid/admin/plugins/exment_pm/?notices=1', $msg);
    $check('never the comment\'s text (the bell\'s body)', strpos(json_encode($msg), 'SECRET-COMMENT') === false && strpos(strval(uaDecrypt($sent[0]['body'], $ua1['private'], $ua1['public'], $ua1Auth)), 'SECRET') === false);
    $sent = [];
    for ($i = 1; $i <= 8; $i++) {
        P::bellCreated((object) ['target_user_id' => 7, 'notify_subject' => 'bell ' . $i]);
    }
    P::flush($fake);
    $titles = array_map(function ($s) use ($ua1, $ua1Auth) {
        return json_decode(strval(uaDecrypt($s['body'], $ua1['private'], $ua1['public'], $ua1Auth)), true)['title'] ?? '';
    }, $sent);
    $check('many bells in one request: the newest ' . P::MAX_PER_FLUSH . ' only, in order', $titles === ['bell 6', 'bell 7', 'bell 8'], $titles);
    $check('a second terminating callback is not added', count($GLOBALS['__test_terminating']) === 1);

    // what the push service answers
    foreach ([410 => 'gone', 404 => 'not found'] as $status => $why) {
        $store->subs = [];
        P::subscribe(7, $sub($e1, $ua1, $ua1Auth), '');
        $answers = [$status];
        $res = P::sendTo(7, 'x', $fake, 'u');
        $check($status . ' (' . $why . '): the subscription is removed', $res['removed'] === 1 && count($store->subs) === 0, $res);
    }
    $store->subs = [];
    P::subscribe(7, $sub($e1, $ua1, $ua1Auth), '');
    $answers = [500, 429, 503, 500];
    for ($i = 0; $i < 4; $i++) {
        P::sendTo(7, 'x', $fake, 'u');
    }
    $check('4 failures in a row: kept, counted', count($store->subs) === 1 && $store->forUser(7)[0]['failures'] === 4);
    $answers = [201];
    P::sendTo(7, 'x', $fake, 'u');
    $check('a success counts from 0 again', $store->forUser(7)[0]['failures'] === 0);
    $answers = [500, 500, 500, 500, 500];
    $last = [];
    for ($i = 0; $i < 5; $i++) {
        $last = P::sendTo(7, 'x', $fake, 'u');
    }
    $check('5 failures in a row: removed', count($store->subs) === 0 && $last['removed'] === 1, $last);
    $store->subs = [];
    P::subscribe(7, $sub($e1, $ua1, $ua1Auth), '');
    $boom = function () {
        throw new \RuntimeException('connect to https://fcm.googleapis.com/fcm/send/secret failed');
    };
    Illuminate\Support\Facades\Log::$lines = [];
    $res = P::sendTo(7, 'x', $boom, 'u');
    $check('a network error is a failure, the log has no address', $res['failed'] === 1 && $store->forUser(7)[0]['failures'] === 1 && strpos(implode("\n", Illuminate\Support\Facades\Log::$lines), 'fcm') === false, Illuminate\Support\Facades\Log::$lines);
    // a row whose address is not allowed (written by hand): never sent, removed
    $store->subs = [];
    $id = $store->insert(['user_id' => 7, 'endpoint' => 'https://push.example.invalid/x', 'endpoint_hash' => hash('sha256', 'https://push.example.invalid/x'), 'p256dh' => C::b64u($ua1['public']), 'auth' => C::b64u($ua1Auth)]);
    $sent = [];
    $res = P::sendTo(7, 'x', $fake, 'u');
    $check('a row of another address: no request, removed', count($sent) === 0 && $res['removed'] === 1 && count($store->subs) === 0, $res);
    // a row written before the values were encrypted (until the upgrade ran): still sent
    $store->insert(['user_id' => 7, 'endpoint' => $e1, 'endpoint_hash' => hash('sha256', $e1), 'p256dh' => C::b64u($ua1['public']), 'auth' => C::b64u($ua1Auth)]);
    $sent = [];
    $res = P::sendTo(7, 'old row', $fake, 'u');
    $check('an old plain row: sent, readable by the browser', $res['sent'] === 1 && $sent[0]['url'] === $e1
        && json_decode(strval(uaDecrypt($sent[0]['body'], $ua1['private'], $ua1['public'], $ua1Auth)), true)['title'] === 'old row', $res);
    $store->subs = [];
    $sent = [];
    $check('nobody subscribed: nothing sent', P::sendTo(99, 'x', $fake, 'u') === ['sent' => 0, 'removed' => 0, 'failed' => 0, 'refused' => 0] && count($sent) === 0);

    // 401 / 403: the site's VAPID refused, not the browser's fault: logged, the row kept as it was
    foreach ([401, 403] as $status) {
        $store->subs = [];
        P::subscribe(7, $sub($e1, $ua1, $ua1Auth), '');
        Illuminate\Support\Facades\Log::$lines = [];
        $answers = array_fill(0, 6, $status);
        for ($i = 0; $i < 6; $i++) {
            $res = P::sendTo(7, 'x', $fake, 'u');
        }
        $check($status . ' six times: the subscription is kept, no failure counted, logged', count($store->subs) === 1 && $store->forUser(7)[0]['failures'] === 0
            && $res['refused'] === 1 && strpos(implode("\n", Illuminate\Support\Facades\Log::$lines), 'VAPID') !== false, [$res, Illuminate\Support\Facades\Log::$lines]);
    }

    // a 403 forever (a subscription of an older key): logged once a flush a row, not on every bell
    $store->subs = [];
    P::subscribe(7, $sub($e1, $ua1, $ua1Auth), '');
    $answers = [403, 403, 403, 403, 403, 403];
    $vapidLines = function () {
        return count(array_filter(Illuminate\Support\Facades\Log::$lines, function ($l) {
            return strpos($l, 'VAPID') !== false;
        }));
    };
    Illuminate\Support\Facades\Log::$lines = [];
    $sent = [];
    foreach ([1, 2, 3] as $i) {
        P::bellCreated((object) ['target_user_id' => 7, 'notify_subject' => 'r' . $i]);
    }
    P::flush($fake);
    $check('403 on three bells of one flush: logged once', count($sent) === 3 && $vapidLines() === 1, Illuminate\Support\Facades\Log::$lines);
    foreach ([1, 2, 3] as $i) {
        P::bellCreated((object) ['target_user_id' => 7, 'notify_subject' => 'r' . $i]);
    }
    P::flush($fake);
    $check('the next flush: logged once more, the row still kept', $vapidLines() === 2 && count($store->subs) === 1);

    // the lock of the key pair: still held after 10 s, or no lock at all: run anyway (no 500)
    class LockStore extends PortalPushStore
    {
        public $mode = 'ok';
        public $waited = false;

        protected function lock()
        {
            if ($this->mode === 'none') {
                throw new \BadMethodCallException('This cache store does not support locks.');
            }
            $store = $this;

            return new class ($store) {
                private $store;

                public function __construct($store)
                {
                    $this->store = $store;
                }

                public function block($seconds, $f)
                {
                    $this->store->waited = $seconds;
                    if ($this->store->mode === 'timeout') {
                        throw new \Illuminate\Contracts\Cache\LockTimeoutException();
                    }

                    return $f();
                }
            };
        }
    }
    $ls = new LockStore();
    $check('the lock: waited for at most 10 s, then run', $ls->locked(function () {
        return 'ran';
    }) === 'ran' && $ls->waited === 10);
    $ls->mode = 'timeout';
    $threw = null;
    try {
        $got = $ls->locked(function () {
            return 'ran anyway';
        });
    } catch (\Throwable $e) {
        $threw = get_class($e);
        $got = null;
    }
    $check('the lock still held after 10 s: run anyway, no exception', $threw === null && $got === 'ran anyway', $threw);
    $ls->mode = 'none';
    $check('a cache without locks: run as it is', $ls->locked(function () {
        return 'no lock';
    }) === 'no lock');

    // the VAPID subject must be https (Apple and Mozilla refuse an http one)
    $check('https: APP_URL when it is https', P::httpsOf(['https://a.invalid/', 'https://b.invalid']) === 'https://a.invalid');
    $check('https: the request\'s address when APP_URL is http (behind a TLS proxy)', P::httpsOf(['http://a.invalid', 'https://b.invalid/']) === 'https://b.invalid');
    $check('https: none of them, none', P::httpsOf(['http://a.invalid', 'http://b.invalid', '']) === null && P::httpsOf(['https://']) === null);
    $GLOBALS['__test_app_url'] = 'http://pm.example.invalid';
    $GLOBALS['__test_url'] = 'https://pm.example.invalid';
    $check('APP_URL http, the request https: the request\'s', P::siteUrl() === 'https://pm.example.invalid');
    $GLOBALS['__test_url'] = 'http://pm.example.invalid';
    $store->subs = [];
    P::subscribe(7, $sub($e1, $ua1, $ua1Auth), '');
    $sent = [];
    $res = P::sendTo(7, 'x', $fake, 'u');
    $check('no https address: nothing sent, the subscription kept, not ready', count($sent) === 0 && count($store->subs) === 1 && P::siteUrl() === null && !P::ready(), $res);
    $GLOBALS['__test_app_url'] = 'https://pm.example.invalid/';
    $GLOBALS['__test_url'] = 'https://pm.example.invalid';
    $check('https again: ready', P::ready());

    // aud: no :443
    $hdr443 = C::vapid('https://fcm.googleapis.com:443/fcm/send/x', 'https://pm.example.invalid', $site['public'], $site['private']);
    preg_match('/t=[^.]+\.([^.]+)\./', $hdr443, $m443);
    $hdr8443 = C::vapid('https://push.example.invalid:8443/x', 'https://pm.example.invalid', $site['public'], $site['private']);
    preg_match('/t=[^.]+\.([^.]+)\./', $hdr8443, $m8443);
    $check('VAPID aud: an explicit :443 is dropped, another port kept', json_decode(C::unb64u($m443[1]), true)['aud'] === 'https://fcm.googleapis.com'
        && json_decode(C::unb64u($m8443[1]), true)['aud'] === 'https://push.example.invalid:8443');

    // the sync queue (Exment's default) runs the bell's job inside the request: nothing sent then
    $store->subs = [];
    P::subscribe(7, $sub($e1, $ua1, $ua1Auth), '');
    $sent = [];
    P::bellCreated((object) ['target_user_id' => 7, 'notify_subject' => 'from a job']);
    P::jobProcessed((object) ['job' => new Illuminate\Queue\Jobs\SyncJob()], $fake);
    $check('JobProcessed of a sync job: nothing sent in the request, the bell waits for terminating', count($sent) === 0 && P::pendingCount() === 1);
    P::jobProcessed((object) ['job' => new Illuminate\Queue\Jobs\RedisJob()], $fake);
    $check('JobProcessed of a worker\'s job: sent at its end', count($sent) === 1 && P::pendingCount() === 0);
    $sent = [];
    P::jobProcessed((object) ['job' => new Illuminate\Queue\Jobs\RedisJob()], $fake);
    $check('JobProcessed with nothing kept: nothing sent', count($sent) === 0);

    // one flush stops after DEADLINE seconds (a fake clock: each push takes 15 s)
    $store->subs = [];
    foreach ([21, 22, 23] as $u) {
        P::subscribe($u, $sub('https://web.push.apple.com/user-' . $u), '');
        P::bellCreated((object) ['target_user_id' => $u, 'notify_subject' => 'to ' . $u]);
    }
    $t = 1000.0;
    P::$clock = function () use (&$t) {
        return $t;
    };
    $sent = [];
    $slow = function ($url, $headers, $body) use (&$sent, &$t) {
        $sent[] = $url;
        $t += 15;

        return 201;
    };
    Illuminate\Support\Facades\Log::$lines = [];
    P::flush($slow);
    P::$clock = null;
    $check('flush: stops once ' . P::DEADLINE . ' s are used up', count($sent) === 2 && P::pendingCount() === 0 && strpos(implode("\n", Illuminate\Support\Facades\Log::$lines), 'used up') !== false, $sent);

    // the public key: read on a page view, made only when asked (the push route)
    $resetKey = \Closure::bind(function () {
        static::$publicKey = null;
    }, null, P::class);
    $store->keyRow = null;
    $store->locks = 0;
    $resetKey();
    $check('a page view: no key made', P::publicKey() === '' && $store->keyRow === null);
    $made = P::publicKey(true);
    $check('the push route: the key made, under the lock', $made !== '' && $store->keyRow['public_key'] === $made && $store->locks === 1);
    $store->keyRow = ['public_key' => 'changed', 'private_enc' => 'x'];
    $check('read once a request (no decrypting on a page view)', P::publicKey() === $made);
    $resetKey();
    $check('the next request reads the row', P::publicKey() === 'changed');
    $store->keyRow = null;
    $resetKey();

    // the same browser twice at once: the second insert hits the unique endpoint_hash
    class RaceStore extends MemoryStore
    {
        public $blind = 1;

        public function byHash(string $hash): ?array
        {
            if ($this->blind > 0) {
                $this->blind--;

                return null;
            }

            return parent::byHash($hash);
        }

        public function insert(array $row): int
        {
            if (parent::byHash($row['endpoint_hash']) !== null) {
                throw new \RuntimeException('SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry');
            }

            return parent::insert($row);
        }
    }
    $race = new RaceStore();
    P::$store = $race;
    $race->insert(['user_id' => 7, 'endpoint' => $e1, 'endpoint_hash' => hash('sha256', $e1), 'p256dh' => C::b64u($ua1['public']), 'auth' => C::b64u($ua1Auth)]);
    $r = P::subscribe(8, $sub($e1, $ua1, $ua1Auth), '');
    $check('a duplicate insert: the row is updated instead (no error)', $r === ['ok' => true] && count($race->subs) === 1 && $race->forUser(8), $r);
    P::$store = $store;

    $check('device names', P::deviceName('Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Version/17.0 Mobile/15E148 Safari/604.1') === 'Safari / iPhone'
        && P::deviceName('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/131.0 Safari/537.36 Edg/131.0') === 'Edge / Windows'
        && P::deviceName('') === '');

    echo "\n", $bad === 0 ? "all passed ($ok)" : "$bad failed, $ok passed", "\n";
    exit($bad === 0 ? 0 : 1);
}
