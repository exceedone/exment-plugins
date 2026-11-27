<?php
// ExmentGroupware: Google and Microsoft calendars read into the calendar (ISS02947 part 6), without
// Exment: the fixed hosts of the fetch (HostGuard, no redirect, the limits), the sign-in (state,
// PKCE, a state of another person or used twice refused), the tokens encrypted and renewed, the
// first read and the changes (Google's syncToken, Microsoft's deltaLink, their pages), 410 Gone,
// removals, 「予定あり」 and details (an event private there stays busy), the caps, and that another
// person can neither see nor use a link. On an in-memory stand-in of the database, with a fake
// client and a fake resolver: nothing is ever sent to Google or Microsoft.
//
//   php groupware/tools/test-gw-calendar-cloud.php

namespace Illuminate\Support\Facades {
    // ---- stand-ins of Laravel's facades, enough for the code under test ----------------
    class GwFakeCollection implements \IteratorAggregate, \Countable
    {
        public $items;

        public function __construct(array $items)
        {
            $this->items = array_values($items);
        }

        public function getIterator(): \Iterator
        {
            return new \ArrayIterator($this->items);
        }

        public function count(): int
        {
            return count($this->items);
        }

        public function isEmpty(): bool
        {
            return empty($this->items);
        }

        public function all(): array
        {
            return $this->items;
        }

        public function pluck($k): self
        {
            return new self(array_map(function ($r) use ($k) {
                return is_object($r) ? $r->$k : $r[$k];
            }, $this->items));
        }

        public function map(callable $f): self
        {
            return new self(array_map($f, $this->items));
        }
    }

    class GwFakeQuery
    {
        /** @var string|null */
        public $table;
        /** @var array<int, array{0: string, 1: callable}> */
        public $wheres = [];
        public $order = [];
        public $lim = null;

        public function __construct(?string $table)
        {
            $this->table = $table;
        }

        protected static function value($v)
        {
            if ($v instanceof \DateTimeInterface) {
                return $v->format('Y-m-d H:i:s');
            }
            if (is_object($v) && method_exists($v, 'format')) {
                return $v->format('Y-m-d H:i:s');
            }
            if (is_bool($v)) {
                return $v ? 1 : 0;
            }
            return $v;
        }

        protected function add(string $bool, callable $p): self
        {
            $this->wheres[] = [$bool, $p];
            return $this;
        }

        protected static function cmp($a, string $op, $b): bool
        {
            $b = static::value($b);
            switch ($op) {
                case '=':
                    return $a !== null && strval($a) === strval($b);
                case '<':
                    return $a !== null && strcmp(strval($a), strval($b)) < 0;
                case '<=':
                    return $a !== null && strcmp(strval($a), strval($b)) <= 0;
                case '>':
                    return $a !== null && strcmp(strval($a), strval($b)) > 0;
                case '>=':
                    return $a !== null && strcmp(strval($a), strval($b)) >= 0;
            }
            throw new \LogicException('op ' . $op);
        }

        protected function pred($col, $op, $val, int $n): callable
        {
            if ($col instanceof \Closure) {
                $sub = new self(null);
                $col($sub);
                return function ($r) use ($sub) {
                    return $sub->matches($r);
                };
            }
            if ($n === 2) {
                [$op, $val] = ['=', $op];
            }
            return function ($r) use ($col, $op, $val) {
                return static::cmp($r[$col] ?? null, $op, $val);
            };
        }

        public function where($col, $op = null, $val = null)
        {
            return $this->add('and', $this->pred($col, $op, $val, func_num_args()));
        }

        public function orWhere($col, $op = null, $val = null)
        {
            return $this->add('or', $this->pred($col, $op, $val, func_num_args()));
        }

        public function whereNull($col)
        {
            return $this->add('and', function ($r) use ($col) {
                return ($r[$col] ?? null) === null;
            });
        }

        public function orWhereNull($col)
        {
            return $this->add('or', function ($r) use ($col) {
                return ($r[$col] ?? null) === null;
            });
        }

        public function whereNotNull($col)
        {
            return $this->add('and', function ($r) use ($col) {
                return ($r[$col] ?? null) !== null;
            });
        }

        public function orWhereNotNull($col)
        {
            return $this->add('or', function ($r) use ($col) {
                return ($r[$col] ?? null) !== null;
            });
        }

        public function whereIn($col, $vals)
        {
            $vals = array_map('strval', (array) $vals);
            return $this->add('and', function ($r) use ($col, $vals) {
                return isset($r[$col]) && in_array(strval($r[$col]), $vals, true);
            });
        }

        public function orWhereIn($col, $vals)
        {
            $vals = array_map('strval', (array) $vals);
            return $this->add('or', function ($r) use ($col, $vals) {
                return isset($r[$col]) && in_array(strval($r[$col]), $vals, true);
            });
        }

        public function whereNotIn($col, $vals)
        {
            $vals = array_map('strval', (array) $vals);
            return $this->add('and', function ($r) use ($col, $vals) {
                return !isset($r[$col]) || !in_array(strval($r[$col]), $vals, true);
            });
        }

        /** SQL's precedence: AND before OR. */
        public function matches(array $r): bool
        {
            if (empty($this->wheres)) {
                return true;
            }
            $groups = [[]];
            foreach ($this->wheres as $i => [$bool, $p]) {
                if ($bool === 'or' && $i > 0) {
                    $groups[] = [];
                }
                $groups[count($groups) - 1][] = $p;
            }
            foreach ($groups as $g) {
                $ok = true;
                foreach ($g as $p) {
                    $ok = $ok && $p($r);
                }
                if ($ok) {
                    return true;
                }
            }
            return false;
        }

        public function orderBy($col, $dir = 'asc')
        {
            $this->order[] = [$col, strtolower($dir)];
            return $this;
        }

        public function orderByDesc($col)
        {
            return $this->orderBy($col, 'desc');
        }

        public function limit($n)
        {
            $this->lim = $n;
            return $this;
        }

        /** @return array<int, array<string, mixed>> keyed by id */
        protected function rows(): array
        {
            $out = [];
            foreach (DB::$tables[$this->table] ?? [] as $id => $r) {
                if ($this->matches($r)) {
                    $out[$id] = $r;
                }
            }
            foreach (array_reverse($this->order) as [$col, $dir]) {
                uasort($out, function ($a, $b) use ($col, $dir) {
                    $c = strcmp(strval($a[$col] ?? ''), strval($b[$col] ?? ''));
                    return $dir === 'desc' ? -$c : $c;
                });
            }
            if ($this->lim !== null) {
                $out = array_slice($out, 0, $this->lim, true);
            }
            return $out;
        }

        public function get($cols = ['*'])
        {
            return new GwFakeCollection(array_map(function ($r) {
                return (object) $r;
            }, $this->rows()));
        }

        public function first()
        {
            $r = $this->rows();
            return empty($r) ? null : (object) reset($r);
        }

        public function pluck($col)
        {
            return $this->get()->pluck($col);
        }

        public function count()
        {
            return count($this->rows());
        }

        public function min($col)
        {
            $v = array_column($this->rows(), $col);
            return empty($v) ? null : min($v);
        }

        public function insertGetId(array $v)
        {
            $id = ++DB::$ids[$this->table];
            DB::$tables[$this->table][$id] = ['id' => $id] + array_map([static::class, 'value'], $v);
            return $id;
        }

        public function insert(array $v)
        {
            $this->insertGetId($v);
            return true;
        }

        public function update(array $v)
        {
            $n = 0;
            foreach (array_keys($this->rows()) as $id) {
                DB::$tables[$this->table][$id] = array_map([static::class, 'value'], $v) + DB::$tables[$this->table][$id];
                $n++;
            }
            return $n;
        }

        public function delete()
        {
            $n = 0;
            foreach (array_keys($this->rows()) as $id) {
                unset(DB::$tables[$this->table][$id]);
                $n++;
            }
            return $n;
        }
    }

    class DB
    {
        public static $tables = [];
        public static $ids = [];

        public static function reset(): void
        {
            foreach (['gw_cal_events', 'gw_cal_overrides', 'gw_cal_attendees', 'gw_cal_event_resources', 'gw_cal_links', 'gw_cal_feeds'] as $t) {
                static::$tables[$t] = [];
                static::$ids[$t] = 0;
            }
        }

        public static function table($t)
        {
            return new GwFakeQuery($t);
        }

        public static function transaction(callable $f)
        {
            return $f();
        }

        public static function connection()
        {
            return new class () {
                public function getDatabaseName()
                {
                    return 'fake';
                }
            };
        }
    }

    class Crypt
    {
        public static function encrypt($v)
        {
            return 'encs:' . base64_encode(serialize($v));
        }

        public static function decrypt($v)
        {
            if (strpos(strval($v), 'encs:') !== 0) {
                throw new \RuntimeException('bad');
            }
            return unserialize(base64_decode(substr($v, 5)));
        }

        public static function encryptString($v)
        {
            return 'enc:' . base64_encode(strrev(strval($v)));
        }

        public static function decryptString($v)
        {
            if (strpos(strval($v), 'enc:') !== 0) {
                throw new \RuntimeException('bad');
            }
            return strrev(base64_decode(substr($v, 4)));
        }
    }

    class Log
    {
        public static $lines = [];

        public static function __callStatic($name, $args)
        {
            static::$lines[] = $name . ': ' . strval($args[0] ?? '');
        }
    }

    class Cache
    {
        public static $v = [];

        public static function add($k, $v, $ttl = null)
        {
            if (array_key_exists($k, static::$v)) {
                return false;
            }
            static::$v[$k] = $v;
            return true;
        }

        public static function forget($k)
        {
            unset(static::$v[$k]);
        }

        public static function increment($k)
        {
            return static::$v[$k] = intval(static::$v[$k] ?? 0) + 1;
        }
    }
}

namespace Exceedone\Exment\Form\Field {
    // Exment's EncPassword as it is (prepare: empty keeps the original; formatValue: decrypted into the input)
    class EncPassword
    {
        public $value;
        public $original;
        protected $updateIfEmpty = false;

        public function fill($v)
        {
            $this->value = $v;
            $this->formatValue();
        }

        public function prepare($value)
        {
            if (!$this->updateIfEmpty && ($value === null || $value === '')) {
                return $this->original;
            }
            return \Illuminate\Support\Facades\Crypt::encrypt($value);
        }

        protected function formatValue()
        {
            try {
                $this->value = \Illuminate\Support\Facades\Crypt::decrypt($this->value);
            } catch (\Throwable $e) {
                $this->value = null;
            }
        }
    }
}

namespace Exceedone\Exment\Enums {
    class SystemTableName
    {
        const USER = 'user';
    }
}

namespace {
    /** Exment's users, as Links::userActive and Events::orgsOf ask for them: ids there, ids deleted. */
    class GwFakeUser
    {
        public static $active = [5, 7, 9];
        public $id = null;
        public $deleted = false;

        public static function withoutGlobalScopes()
        {
            return new self();
        }

        public function whereNull($col)
        {
            $this->deleted = true;
            return $this;
        }

        public function where($col, $v)
        {
            $this->id = intval($v);
            return $this;
        }

        public function exists()
        {
            return in_array($this->id, static::$active, true);
        }

        public function find($id)
        {
            return null;
        }
    }
    function getModelName($t)
    {
        return GwFakeUser::class;
    }

    class Schema
    {
        public static function hasColumn($t, $c)
        {
            return true;
        }

        public static function hasTable($t)
        {
            return true;
        }
    }

    class GwFakeNow
    {
        public $t;

        public function __construct(string $t)
        {
            $this->t = new \DateTimeImmutable($t, new \DateTimeZone('UTC'));
        }

        public function format($f)
        {
            return $this->t->format($f);
        }

        public function addMinutes($n)
        {
            return new self($this->t->modify('+' . intval($n) . ' minute')->format('Y-m-d H:i:s'));
        }

        public function setTimezone($tz)
        {
            return $this;
        }

        public function addMinute()
        {
            return $this->addMinutes(1);
        }
    }

    $GLOBALS['gw_now'] = '2026-12-01 00:00:00';
    function now()
    {
        return new GwFakeNow($GLOBALS['gw_now']);
    }
    function config($k)
    {
        return $k === 'app.timezone' ? 'Asia/Tokyo' : null;
    }


    // ---- more stand-ins: the session, the plugin, Exment's encrypt() of the settings ------------
    $GLOBALS['gw_session'] = [];
    function session($k = null, $d = null)
    {
        if (is_array($k)) {
            foreach ($k as $kk => $v) {
                $GLOBALS['gw_session'][$kk] = $v;
            }
            return null;
        }
        return $GLOBALS['gw_session'][$k] ?? $d;
    }

    class GwFakePlugin
    {
        public $o = [];

        public function getCustomOption($k, $d = null)
        {
            return $this->o[$k] ?? $d;
        }

        public function getFullUrl(...$p)
        {
            return 'https://exment.example.invalid/admin/plugins/exment_groupware/' . implode('/', $p);
        }
    }

    $base = __DIR__ . '/../ExmentGroupware';
    foreach (['Mail/MailException', 'Mail/HostGuard', 'FeatureGate', 'Calendar/Rrule', 'Calendar/Occurrences', 'Calendar/Visibility', 'Calendar/Resources', 'Calendar/Events', 'Calendar/Ics', 'Calendar/IcsFetchError', 'Calendar/IcsFetch', 'Calendar/Links', 'Calendar/Feeds',
        'Calendar/CloudFetch', 'Calendar/CloudOAuth', 'Calendar/CloudSync'] as $f) {
        require $base . '/' . $f . '.php';
    }

    use App\Plugins\ExmentGroupware\Calendar\CloudFetch;
    use App\Plugins\ExmentGroupware\Calendar\CloudOAuth;
    use App\Plugins\ExmentGroupware\Calendar\CloudSync;
    use App\Plugins\ExmentGroupware\Calendar\Events;
    use App\Plugins\ExmentGroupware\Calendar\Ics;
    use App\Plugins\ExmentGroupware\Calendar\IcsFetch;
    use App\Plugins\ExmentGroupware\Calendar\IcsFetchError;
    use App\Plugins\ExmentGroupware\Calendar\Links;
    use App\Plugins\ExmentGroupware\Mail\HostGuard;
    use Illuminate\Support\Facades\Crypt;
    use Illuminate\Support\Facades\DB;

    $failed = 0;
    function gw_check(string $name, bool $ok, $extra = null): void
    {
        global $failed;
        echo ($ok ? 'ok   ' : 'FAIL ') . $name . ($ok || $extra === null ? '' : '  ' . var_export($extra, true)) . "\n";
        $failed += $ok ? 0 : 1;
    }
    /** The error code a call throws, or null. */
    function gw_code(callable $f): ?string
    {
        try {
            $f();
        } catch (IcsFetchError $e) {
            return $e->getMessage();
        }
        return null;
    }
    function gw_q(string $url): array
    {
        parse_str(strval(parse_url($url, PHP_URL_QUERY)), $q);
        return $q;
    }
    function gw_json($v): array
    {
        return ['status' => 200, 'headers' => ['Content-Type' => 'application/json'], 'body' => json_encode($v)];
    }

    // the fake servers of Google and Microsoft: no network, no DNS (a fake resolver of the fixed hosts)
    $calls = [];
    $handler = function ($m, $u, $h, $b) {
        return ['status' => 404, 'headers' => [], 'body' => ''];
    };
    $client = function ($method, $url, $headers, $body, $opts) use (&$calls, &$handler) {
        $calls[] = ['method' => $method, 'url' => $url, 'headers' => $headers, 'body' => $body, 'ip' => $opts['ip']];
        return $handler($method, $url, $headers, $body);
    };
    $dnsMap = [];
    foreach (CloudFetch::HOSTS as $h) {
        $dnsMap[$h] = ['203.0.113.50'];
    }
    $resolve = function ($host) use (&$dnsMap) {
        return $dnsMap[$host] ?? [];
    };
    $clockNow = 1000.0;
    $clock = function () use (&$clockNow) {
        return $clockNow;
    };
    $fetch = new CloudFetch($client, $resolve, $clock);
    $plugin = new GwFakePlugin();
    $plugin->o = [
        'cal_google_client_id' => 'gid.apps.example.invalid', 'cal_google_client_secret' => Crypt::encrypt('g-client-secret'),
        'cal_ms_client_id' => 'ms-client-id', 'cal_ms_client_secret' => Crypt::encrypt('ms-client-secret'),
    ];
    $oauth = new CloudOAuth($plugin, $fetch);
    $cloud = new CloudSync($oauth);

    // ---- the fetch: the fixed hosts only -----------------------------------------------------------
    $handler = function ($m, $u) {
        return gw_json(['ok' => true]);
    };
    gw_check('fetch: a fixed host, at the address checked', $fetch->request('GET', 'https://www.googleapis.com/calendar/v3/x')['json'] === ['ok' => true] && $calls[0]['ip'] === '203.0.113.50');
    $calls = [];
    gw_check('fetch: another host refused before any connection', gw_code(function () use ($fetch) {
        $fetch->request('GET', 'https://evil.example.invalid/calendar/v3/x');
    }) === 'host' && $calls === []);
    gw_check('fetch: a look-alike host refused', gw_code(function () use ($fetch) {
        $fetch->request('GET', 'https://www.googleapis.com.evil.example.invalid/x');
    }) === 'host' && gw_code(function () use ($fetch) {
        $fetch->request('GET', 'https://graph.microsoft.com@evil.example.invalid/x');
    }) === 'host');
    gw_check('fetch: http and another port refused', gw_code(function () use ($fetch) {
        $fetch->request('GET', 'http://graph.microsoft.com/v1.0/me');
    }) === 'host' && gw_code(function () use ($fetch) {
        $fetch->request('GET', 'https://graph.microsoft.com:8443/v1.0/me');
    }) === 'host' && $calls === []);
    HostGuard::allowPrivateWeb(true);
    $dnsMap['graph.microsoft.com'] = ['10.0.0.5'];
    gw_check('fetch: a fixed host at an internal address refused, even with the internal network allowed for ICS', gw_code(function () use ($fetch) {
        $fetch->request('GET', 'https://graph.microsoft.com/v1.0/me');
    }) === 'private' && $calls === []);
    $dnsMap['graph.microsoft.com'] = ['169.254.169.254'];
    gw_check('fetch: the metadata address refused', gw_code(function () use ($fetch) {
        $fetch->request('GET', 'https://graph.microsoft.com/v1.0/me');
    }) === 'private');
    $dnsMap['graph.microsoft.com'] = ['203.0.113.50'];
    HostGuard::allowPrivateWeb(false);
    $handler = function () {
        return ['status' => 302, 'headers' => ['Location' => 'https://evil.example.invalid/'], 'body' => ''];
    };
    gw_check('fetch: a redirect is not followed', gw_code(function () use ($fetch) {
        $fetch->request('GET', 'https://graph.microsoft.com/v1.0/me');
    }) === 'redirects' && count($calls) === 1);
    $handler = function () {
        return ['status' => 200, 'headers' => [], 'body' => str_repeat('x', IcsFetch::MAX_BYTES + 1)];
    };
    gw_check('fetch: the size limit of an ICS link (5 MB)', gw_code(function () use ($fetch) {
        $fetch->request('GET', 'https://graph.microsoft.com/v1.0/me');
    }) === 'too_big');
    $fetch->within(30);
    $clockNow += 31;
    gw_check('fetch: past the deadline of the read, no request', gw_code(function () use ($fetch) {
        $fetch->request('GET', 'https://graph.microsoft.com/v1.0/me');
    }) === 'too_slow');
    $fetch->within(100);
    $calls = [];
    $handler = function () {
        return gw_json([]);
    };
    $fetch->request('GET', 'https://graph.microsoft.com/v1.0/me');
    gw_check('fetch: each request within the time limit of an ICS link (15 s)', count($calls) === 1);

    // ---- the settings ------------------------------------------------------------------------------
    gw_check('settings: the client secrets decrypted (Exment encrypts them)', $oauth->config('google')['client_secret'] === 'g-client-secret' && $oauth->config('microsoft')['client_secret'] === 'ms-client-secret');
    gw_check('settings: both providers set', $oauth->configured('google') && $oauth->configured('microsoft') && !$oauth->configured('caldav'));
    $plain = new GwFakePlugin();
    $plain->o = ['cal_google_client_id' => 'x', 'cal_google_client_secret' => 'not-encrypted'];
    gw_check('settings: a secret that is not encrypted is none (not used as it is)', !(new CloudOAuth($plain, $fetch))->configured('google'));
    gw_check('settings: no app, no sign-in', gw_code(function () use ($plain, $fetch) {
        (new CloudOAuth($plain, $fetch))->authorizeUrl('google', 5);
    }) === 'not_configured');
    gw_check('settings: Microsoft\'s tenant organizations at first, a bad one ignored', strpos($oauth->config('microsoft')['token'], '/organizations/oauth2/v2.0/token') !== false
        && (function () use ($fetch) {
            $p = new GwFakePlugin();
            $p->o = ['cal_ms_tenant' => '../x?y'];
            return strpos((new CloudOAuth($p, $fetch))->config('microsoft')['authorize'], '/organizations/') !== false;
        })());

    // ---- the sign-in -------------------------------------------------------------------------------
    $GLOBALS['gw_session'] = [];
    $url = $oauth->authorizeUrl('google', 5);
    $q = gw_q($url);
    gw_check('sign-in: Google\'s page, read only, PKCE S256, offline', strpos($url, 'https://accounts.google.com/o/oauth2/v2/auth?') === 0
        && $q['scope'] === 'https://www.googleapis.com/auth/calendar.readonly' && $q['code_challenge_method'] === 'S256' && strlen($q['code_challenge']) === 43
        && $q['access_type'] === 'offline' && $q['prompt'] === 'consent select_account' && $q['redirect_uri'] === 'https://exment.example.invalid/admin/plugins/exment_groupware/cal/oauth/callback' && $q['client_id'] === 'gid.apps.example.invalid', $q);
    gw_check('sign-in: no secret in the address', strpos($url, 'g-client-secret') === false);
    $state = $q['state'];
    $entry = $GLOBALS['gw_session'][CloudOAuth::SESSION][$state];
    gw_check('sign-in: the state kept in the session with the person', intval($entry['uid']) === 5 && $entry['provider'] === 'google' && $entry['link_id'] === null);
    $mq = gw_q($oauth->authorizeUrl('microsoft', 5));
    gw_check('sign-in: Microsoft\'s scopes Calendars.Read offline_access', $mq['scope'] === 'https://graph.microsoft.com/Calendars.Read offline_access' && $mq['code_challenge_method'] === 'S256');

    $calls = [];
    $handler = function ($m, $u, $h, $b) {
        return gw_json(['access_token' => 'ya29-access-1', 'refresh_token' => 'g-refresh-1', 'expires_in' => 3599]);
    };
    gw_check('callback: a state unknown refused, nothing asked of Google', gw_code(function () use ($oauth) {
        $oauth->finish(5, 'f00d' . str_repeat('0', 28), 'the-code');
    }) === 'oauth_state' && $calls === []);
    gw_check('callback: a state of another person refused (and used up)', gw_code(function () use ($oauth, $state) {
        $oauth->finish(7, $state, 'the-code');
    }) === 'oauth_state' && $calls === [] && !isset($GLOBALS['gw_session'][CloudOAuth::SESSION][$state]));
    gw_check('callback: the same state again refused', gw_code(function () use ($oauth, $state) {
        $oauth->finish(5, $state, 'the-code');
    }) === 'oauth_state' && $calls === []);
    $q = gw_q($oauth->authorizeUrl('google', 5));
    $GLOBALS['gw_session'][CloudOAuth::SESSION][$q['state']]['at'] = time() - 1000;
    gw_check('callback: a state older than 15 minutes refused', gw_code(function () use ($oauth, $q) {
        $oauth->finish(5, $q['state'], 'the-code');
    }) === 'oauth_state' && $calls === []);
    $q = gw_q($oauth->authorizeUrl('google', 5));
    $r = $oauth->finish(5, $q['state'], 'the-code');
    parse_str($calls[0]['body'], $form);
    gw_check('callback: the code into tokens, with the verifier of the challenge', $calls[0]['method'] === 'POST' && $calls[0]['url'] === 'https://oauth2.googleapis.com/token'
        && $form['grant_type'] === 'authorization_code' && $form['code'] === 'the-code'
        && rtrim(strtr(base64_encode(hash('sha256', $form['code_verifier'], true)), '+/', '-_'), '=') === $q['code_challenge'], $form);
    gw_check('callback: the tokens', $r['provider'] === 'google' && $r['secret']['access_token'] === 'ya29-access-1' && $r['secret']['refresh_token'] === 'g-refresh-1' && $r['secret']['expires_at'] > time());
    $handler = function () {
        return ['status' => 400, 'headers' => [], 'body' => '{"error":"invalid_grant","error_description":"<b>x</b>"}'];
    };
    $q = gw_q($oauth->authorizeUrl('google', 5));
    gw_check('callback: a code refused is a code, not the provider\'s words', gw_code(function () use ($oauth, $q) {
        $oauth->finish(5, $q['state'], 'bad');
    }) === 'oauth_token');

    // ---- a Google calendar linked ------------------------------------------------------------------
    DB::reset();
    $GLOBALS['gw_now'] = '2026-12-01 00:00:00';
    $owner = 5;
    $handler = function ($m, $u, $h) {
        return strpos($u, '/calendars/primary') !== false && strpos($u, '/events') === false && ($h['Authorization'] ?? '') === 'Bearer ya29-access-1'
            ? gw_json(['id' => 'Owner@Example.invalid', 'summary' => 'x']) : ['status' => 404, 'headers' => [], 'body' => ''];
    };
    $probe = (object) ['id' => 0, 'provider' => 'google', 'secret_enc' => CloudOAuth::encryptSecret($r['secret'])];
    $account = $cloud->account($probe);
    gw_check('account: the email of the calendar, only that', $account === 'owner@example.invalid', $account);
    [$gid, $why] = Links::connect($owner, 'google', null, $r['secret'], $account, 'Google カレンダー');
    $row = DB::table('gw_cal_links')->where('id', $gid)->first();
    gw_check('connect: a link of provider google, read soon, 「予定あり」 at first', $why === null && $row->provider === 'google' && $row->show_as === 'busy' && $row->account === 'owner@example.invalid' && $row->next_sync_at !== null);
    $dump = json_encode(DB::$tables);
    gw_check('encryption at rest: no token as it is in the table', strpos($dump, 'ya29-access-1') === false && strpos($dump, 'g-refresh-1') === false && CloudOAuth::secret($row)['refresh_token'] === 'g-refresh-1');
    $shown = json_encode(Links::list($owner));
    gw_check('the page: the account\'s email, never a token or the secret', strpos($shown, 'owner@example.invalid') !== false && strpos($shown, 'secret') === false && strpos($shown, 'ya29') === false && strpos($shown, 'refresh') === false, $shown);
    gw_check('another person: does not see the link', Links::list(7) === []);
    gw_check('another person: cannot change, remove or sign in again the link', Links::update(7, $gid, ['name' => 'x', 'show_as' => 'details']) === 'link_not_found'
        && Links::delete(7, $gid) === false && Links::connect(7, 'google', $gid, ['access_token' => 'evil', 'refresh_token' => 'evil'], 'evil@example.invalid', 'x')[1] === 'link_not_found'
        && CloudOAuth::secret(DB::table('gw_cal_links')->where('id', $gid)->first())['access_token'] === 'ya29-access-1');
    gw_check('connect: a link of another provider cannot be signed in again as this one', Links::connect($owner, 'microsoft', $gid, ['access_token' => 'x'], '', 'x')[1] === 'link_not_found');

    // the first read: everything in the window, page by page, with its sync token at the end
    $gItems1 = [
        ['id' => 'ev1', 'status' => 'confirmed', 'summary' => '<img src=x onerror=alert(1)> plan', 'location' => 'Room <b>1</b>', 'description' => 'line <b>one</b><br>line two &amp; more',
            'start' => ['dateTime' => '2026-12-10T10:00:00+09:00', 'timeZone' => 'Asia/Tokyo'], 'end' => ['dateTime' => '2026-12-10T11:00:00+09:00']],
        ['id' => 'allday', 'status' => 'confirmed', 'summary' => 'Holiday trip', 'start' => ['date' => '2026-12-24'], 'end' => ['date' => '2026-12-26']],
        ['id' => 'secret1', 'status' => 'confirmed', 'summary' => 'Doctor', 'visibility' => 'private', 'start' => ['dateTime' => '2026-12-11T01:00:00Z'], 'end' => ['dateTime' => '2026-12-11T02:00:00Z']],
    ];
    $gItems2 = [
        ['id' => 'rec1_20261205T010000Z', 'recurringEventId' => 'rec1', 'status' => 'confirmed', 'summary' => 'Weekly', 'start' => ['dateTime' => '2026-12-05T01:00:00Z'], 'end' => ['dateTime' => '2026-12-05T01:30:00Z']],
        ['id' => 'rec1_20261212T010000Z', 'recurringEventId' => 'rec1', 'status' => 'confirmed', 'summary' => 'Weekly', 'start' => ['dateTime' => '2026-12-12T01:00:00Z'], 'end' => ['dateTime' => '2026-12-12T01:30:00Z']],
        ['id' => 'rec2_20261206T010000Z', 'recurringEventId' => 'rec2', 'status' => 'confirmed', 'summary' => 'Other', 'start' => ['dateTime' => '2026-12-06T01:00:00Z'], 'end' => ['dateTime' => '2026-12-06T01:30:00Z']],
        ['id' => 'rec2_20261213T010000Z', 'recurringEventId' => 'rec2', 'status' => 'confirmed', 'summary' => 'Other', 'start' => ['dateTime' => '2026-12-13T01:00:00Z'], 'end' => ['dateTime' => '2026-12-13T01:30:00Z']],
    ];
    $google = ['full' => function ($q) use (&$gItems1, &$gItems2) {
        return isset($q['pageToken']) ? gw_json(['items' => $gItems2, 'nextSyncToken' => 'sync-1']) : gw_json(['items' => $gItems1, 'nextPageToken' => 'p2']);
    }, 'inc' => null];
    $handler = function ($m, $u, $h) use (&$google) {
        if (strpos($u, CloudSync::GOOGLE_EVENTS) !== 0) {
            return ['status' => 404, 'headers' => [], 'body' => ''];
        }
        if (($h['Authorization'] ?? '') !== 'Bearer ya29-access-1') {
            return ['status' => 401, 'headers' => [], 'body' => '{}'];
        }
        $q = gw_q($u);
        return isset($q['syncToken']) ? ($google['inc'])($q) : ($google['full'])($q);
    };
    $calls = [];
    $res = Links::run(10, null, 0, $cloud);
    $link = DB::table('gw_cal_links')->where('id', $gid)->first();
    $evs = DB::table('gw_cal_events')->where('link_id', $gid)->get()->all();
    $q1 = gw_q($calls[0]['url']);
    gw_check('google: read by the 15-minute run, as an ICS link', $res['read'] === 1 && $link->last_error === null && count($evs) === 7 && intval($link->event_count) === 7, [$res, $link->last_error, count($evs)]);
    gw_check('google: the first read in the window, occurrences as Google expands them', $q1['singleEvents'] === 'true' && $q1['timeMin'] === '2026-11-01T00:00:00Z' && $q1['timeMax'] === '2027-05-30T00:00:00Z' && !isset($q1['syncToken']), $q1);
    gw_check('google: the pages followed, the sync token kept after the last', count($calls) === 2 && (gw_q($calls[1]['url'])['pageToken'] ?? '') === 'p2' && $link->sync_state === 'sync-1' && $link->full_sync_at === '2026-12-01 00:00:00');
    gw_check('google: the token in the header only, never in the address', $calls[0]['headers']['Authorization'] === 'Bearer ya29-access-1' && strpos($calls[0]['url'], 'ya29') === false);
    $byExt = [];
    foreach ($evs as $e) {
        $byExt[$e->external_id] = $e;
    }
    gw_check('google: times in UTC; all day as dates, the end the day after', $byExt['ev1']->starts_at === '2026-12-10 01:00:00' && $byExt['ev1']->ends_at === '2026-12-10 02:00:00' && $byExt['ev1']->tz === 'Asia/Tokyo'
        && intval($byExt['allday']->all_day) === 1 && $byExt['allday']->starts_at === '2026-12-24 00:00:00' && $byExt['allday']->ends_at === '2026-12-26 00:00:00');
    gw_check('google: no RRULE, one event an occurrence', $byExt['rec1_20261205T010000Z']->rrule === null && isset($byExt['rec1_20261212T010000Z']));
    gw_check('google: source=link, the owner\'s, every one 「予定あり」 to the others', count(array_filter($evs, function ($e) use ($owner) {
        return $e->source !== 'link' || intval($e->owner_user_id) !== $owner || $e->visibility !== 'busy';
    })) === 0);
    gw_check('google: markup stays text (the title as it was; the notes as text, no tags)', $byExt['ev1']->title === '<img src=x onerror=alert(1)> plan' && $byExt['ev1']->location === 'Room <b>1</b>'
        && $byExt['ev1']->body === "line one\nline two & more", [$byExt['ev1']->title, $byExt['ev1']->body]);

    $other = Events::window('2026-12-01 00:00:00', '2027-01-01 00:00:00', [$owner], 9, '予定あり');
    $leak = json_encode($other, JSON_UNESCAPED_UNICODE);
    gw_check('busy: others see 「予定あり」 only', count($other) === 7 && strpos($leak, 'plan') === false && strpos($leak, 'Doctor') === false && strpos($leak, 'Weekly') === false && !isset($other[0]['id']), $other);
    $own = Events::window('2026-12-01 00:00:00', '2027-01-01 00:00:00', [$owner], $owner, '予定あり');
    $titles = array_column($own, 'title');
    gw_check('the owner: the details, not editable here', in_array('<img src=x onerror=alert(1)> plan', $titles, true) && in_array('Doctor', $titles, true) && $own[0]['editable'] === false);

    // changes only: one changed, an occurrence removed, a whole series removed, one moved out of the window
    $GLOBALS['gw_now'] = '2026-12-01 00:20:00';
    $calls = [];
    $google['inc'] = function ($q) {
        return $q['syncToken'] === 'sync-1' ? gw_json(['items' => [
            ['id' => 'ev1', 'status' => 'confirmed', 'summary' => 'plan (moved)', 'start' => ['dateTime' => '2026-12-10T12:00:00+09:00'], 'end' => ['dateTime' => '2026-12-10T13:00:00+09:00']],
            ['id' => 'rec1_20261212T010000Z', 'status' => 'cancelled'],
            ['id' => 'rec2', 'status' => 'cancelled'],
            ['id' => 'allday', 'status' => 'confirmed', 'summary' => 'Holiday trip', 'start' => ['date' => '2027-08-01'], 'end' => ['date' => '2027-08-02']],
        ], 'nextSyncToken' => 'sync-2']) : ['status' => 400, 'headers' => [], 'body' => ''];
    };
    $before = DB::table('gw_cal_events')->where('link_id', $gid)->where('external_id', 'ev1')->first();
    $err = Links::sync(DB::table('gw_cal_links')->where('id', $gid)->first(), null, '2026-12-01 00:20:00', $cloud);
    $link = DB::table('gw_cal_links')->where('id', $gid)->first();
    $ext = DB::table('gw_cal_events')->where('link_id', $gid)->pluck('external_id')->all();
    $after = DB::table('gw_cal_events')->where('link_id', $gid)->where('external_id', 'ev1')->first();
    gw_check('google: then the changes only, with the sync token', $err === null && count($calls) === 1 && gw_q($calls[0]['url'])['syncToken'] === 'sync-1' && !isset(gw_q($calls[0]['url'])['timeMin']) && $link->sync_state === 'sync-2', [$err, $calls]);
    gw_check('google: a change made in place (the same row)', $after->id === $before->id && $after->title === 'plan (moved)' && $after->starts_at === '2026-12-10 03:00:00');
    gw_check('google: an occurrence removed there, removed here', !in_array('rec1_20261212T010000Z', $ext, true) && in_array('rec1_20261205T010000Z', $ext, true));
    gw_check('google: a whole series removed there, all its occurrences removed here', !in_array('rec2_20261206T010000Z', $ext, true) && !in_array('rec2_20261213T010000Z', $ext, true));
    gw_check('google: one moved out of the window, removed', !in_array('allday', $ext, true) && intval($link->event_count) === count($ext) && count($ext) === 3, $ext);
    gw_check('google: changes only do not move the time of the full read', $link->full_sync_at === '2026-12-01 00:00:00');

    // 410 Gone: the sync token too old, everything read again; what is no longer there removed
    $calls = [];
    $google['inc'] = function () {
        return ['status' => 410, 'headers' => [], 'body' => '{"error":{"code":410,"message":"Sync token is no longer valid"}}'];
    };
    $google['full'] = function ($q) {
        return gw_json(['items' => [
            ['id' => 'ev1', 'status' => 'confirmed', 'summary' => 'plan (moved)', 'start' => ['dateTime' => '2026-12-10T12:00:00+09:00'], 'end' => ['dateTime' => '2026-12-10T13:00:00+09:00']],
            ['id' => 'new1', 'status' => 'confirmed', 'summary' => 'New one', 'start' => ['dateTime' => '2026-12-15T01:00:00Z'], 'end' => ['dateTime' => '2026-12-15T02:00:00Z']],
        ], 'nextSyncToken' => 'sync-3']);
    };
    $err = Links::sync(DB::table('gw_cal_links')->where('id', $gid)->first(), null, '2026-12-01 00:40:00', $cloud);
    $link = DB::table('gw_cal_links')->where('id', $gid)->first();
    $ext = DB::table('gw_cal_events')->where('link_id', $gid)->pluck('external_id')->all();
    sort($ext);
    gw_check('google: 410 → a full read again at once', $err === null && count($calls) === 2 && isset(gw_q($calls[1]['url'])['timeMin']) && $link->sync_state === 'sync-3' && $link->full_sync_at === '2026-12-01 00:40:00', [$err, count($calls)]);
    gw_check('google: after the full read, only what is there', $ext === ['ev1', 'new1'], $ext);

    // a full read again every day (the window moves on)
    $calls = [];
    $google['inc'] = function () {
        return gw_json(['items' => [], 'nextSyncToken' => 'x']);
    };
    Links::sync(DB::table('gw_cal_links')->where('id', $gid)->first(), null, '2026-12-02 01:00:00', $cloud);
    gw_check('google: a day after the full read, a full read (the window moved on)', isset(gw_q($calls[0]['url'])['timeMin']) && gw_q($calls[0]['url'])['timeMin'] === '2026-11-02T01:00:00Z');

    // details: the others see the title and the place, except an event private there
    $google['full'] = function () use (&$gItems1) {
        return gw_json(['items' => $gItems1, 'nextSyncToken' => 'sync-4']);
    };
    gw_check('show_as: changed to details', Links::update($owner, $gid, ['name' => 'Google', 'show_as' => 'details']) === null);
    $link = DB::table('gw_cal_links')->where('id', $gid)->first();
    gw_check('show_as: still 「予定あり」 until read again, then read in full', $link->sync_state === null && count(array_filter(DB::table('gw_cal_events')->where('link_id', $gid)->get()->all(), function ($e) {
        return $e->visibility !== 'busy';
    })) === 0);
    Links::sync($link, null, '2026-12-02 01:10:00', $cloud);
    $other = Events::window('2026-12-01 00:00:00', '2027-01-01 00:00:00', [$owner], 9, '予定あり');
    $titles = array_column($other, 'title');
    gw_check('details: the others see the title', in_array('<img src=x onerror=alert(1)> plan', $titles, true) && in_array('Holiday trip', $titles, true), $titles);
    gw_check('details: an event private at Google stays 「予定あり」', !in_array('Doctor', $titles, true) && strpos(json_encode($other, JSON_UNESCAPED_UNICODE), 'Doctor') === false && in_array('予定あり', $titles, true));

    // the access token renewed with the refresh token, kept encrypted
    $secret = CloudOAuth::secret(DB::table('gw_cal_links')->where('id', $gid)->first());
    $secret['expires_at'] = time() - 10;
    DB::table('gw_cal_links')->where('id', $gid)->update(['secret_enc' => CloudOAuth::encryptSecret($secret), 'sync_state' => 'sync-4', 'full_sync_at' => '2026-12-02 01:10:00']);
    $calls = [];
    $tokenHandler = $handler;
    $handler = function ($m, $u, $h, $b) use (&$tokenHandler) {
        if ($u === 'https://oauth2.googleapis.com/token') {
            parse_str($b, $f);
            return $f['grant_type'] === 'refresh_token' && $f['refresh_token'] === 'g-refresh-1' && $f['client_secret'] === 'g-client-secret'
                ? gw_json(['access_token' => 'ya29-access-2', 'expires_in' => 3600]) : ['status' => 400, 'headers' => [], 'body' => '{"error":"invalid_grant"}'];
        }
        if (($h['Authorization'] ?? '') === 'Bearer ya29-access-2') {
            $h['Authorization'] = 'Bearer ya29-access-1';
        }
        return $tokenHandler($m, $u, $h, $b);
    };
    $err = Links::sync(DB::table('gw_cal_links')->where('id', $gid)->first(), null, '2026-12-02 01:30:00', $cloud);
    $link = DB::table('gw_cal_links')->where('id', $gid)->first();
    $s2 = CloudOAuth::secret($link);
    gw_check('refresh: an expired access token renewed first', $err === null && $calls[0]['url'] === 'https://oauth2.googleapis.com/token' && $calls[1]['headers']['Authorization'] === 'Bearer ya29-access-2', [$err, array_column($calls, 'url')]);
    gw_check('refresh: the new token kept encrypted, Google\'s refresh token kept', $s2['access_token'] === 'ya29-access-2' && $s2['refresh_token'] === 'g-refresh-1' && strpos(json_encode(DB::$tables), 'ya29-access-2') === false);
    // a 401 of the API: renewed once and tried again
    $calls = [];
    $s2['access_token'] = 'ya29-stale';
    $s2['expires_at'] = time() + 3000;
    DB::table('gw_cal_links')->where('id', $gid)->update(['secret_enc' => CloudOAuth::encryptSecret($s2)]);
    $err = Links::sync(DB::table('gw_cal_links')->where('id', $gid)->first(), null, '2026-12-02 01:50:00', $cloud);
    gw_check('refresh: a 401 renews the token once and asks again', $err === null && count($calls) === 3 && $calls[1]['url'] === 'https://oauth2.googleapis.com/token', array_column($calls, 'url'));
    // the refresh token revoked: sign in again (reconnect), the events kept
    $n = DB::table('gw_cal_events')->where('link_id', $gid)->count();
    $s3 = CloudOAuth::secret(DB::table('gw_cal_links')->where('id', $gid)->first());
    $s3['refresh_token'] = 'g-refresh-revoked';
    $s3['expires_at'] = time() - 10;
    DB::table('gw_cal_links')->where('id', $gid)->update(['secret_enc' => CloudOAuth::encryptSecret($s3)]);
    $err = Links::sync(DB::table('gw_cal_links')->where('id', $gid)->first(), null, '2026-12-02 02:10:00', $cloud);
    $l = Links::list($owner)[0];
    gw_check('revoked: last_error auth, the page offers to sign in again, the events kept', $err === 'auth' && $l['last_error'] === 'auth' && $l['reconnect'] === true && DB::table('gw_cal_events')->where('link_id', $gid)->count() === $n);
    $q = gw_q($oauth->authorizeUrl('google', $owner, $gid, 'owner@example.invalid'));
    gw_check('reconnect: the sign-in for that link, the account as a hint', $GLOBALS['gw_session'][CloudOAuth::SESSION][$q['state']]['link_id'] === $gid && $q['login_hint'] === 'owner@example.invalid');
    [$again] = Links::connect($owner, 'google', $gid, ['access_token' => 'ya29-access-9', 'refresh_token' => 'g-refresh-9', 'expires_at' => time() + 3600], 'owner@example.invalid', 'x');
    $l = DB::table('gw_cal_links')->where('id', $gid)->first();
    gw_check('reconnect: the same link, its tokens new, the error gone, its events kept', $again === $gid && $l->last_error === null && CloudOAuth::secret($l)['refresh_token'] === 'g-refresh-9' && DB::table('gw_cal_events')->where('link_id', $gid)->count() === $n
        && DB::table('gw_cal_links')->count() === 1);
    gw_check('reconnect: the same account too, read in full again (the cursor and the full read forgotten)', $l->sync_state === null && $l->full_sync_at === null);
    [$again] = Links::connect($owner, 'google', $gid, ['access_token' => 'a', 'refresh_token' => 'b', 'expires_at' => time() + 3600], 'someone-else@example.invalid', 'x');
    $l = DB::table('gw_cal_links')->where('id', $gid)->first();
    gw_check('reconnect: another account, the events of the old one go and it is read from nothing', $l->account === 'someone-else@example.invalid' && DB::table('gw_cal_events')->where('link_id', $gid)->count() === 0 && $l->sync_state === null);

    // a read stopped halfway (too many pages) changes nothing; the cursor stays
    DB::table('gw_cal_links')->where('id', $gid)->update(['secret_enc' => CloudOAuth::encryptSecret(['access_token' => 'ya29-access-1', 'refresh_token' => 'g-refresh-1', 'expires_at' => time() + 3600]), 'sync_state' => 'sync-5', 'full_sync_at' => '2026-12-02 02:00:00', 'fails' => 0]);
    $handler = $tokenHandler;
    $google['inc'] = function ($q) {
        return gw_json(['items' => [['id' => 'p' . ($q['pageToken'] ?? '0'), 'status' => 'confirmed', 'summary' => 'x', 'start' => ['dateTime' => '2026-12-20T01:00:00Z'], 'end' => ['dateTime' => '2026-12-20T02:00:00Z']]], 'nextPageToken' => strval(intval($q['pageToken'] ?? 0) + 1)]);
    };
    $calls = [];
    $err = Links::sync(DB::table('gw_cal_links')->where('id', $gid)->first(), null, '2026-12-02 02:30:00', $cloud);
    $l = DB::table('gw_cal_links')->where('id', $gid)->first();
    gw_check('pages: at most ' . CloudSync::MAX_PAGES . ' a read; past it nothing changes, the cursor stays', $err === 'too_slow' && count($calls) === CloudSync::MAX_PAGES && $l->sync_state === 'sync-5' && DB::table('gw_cal_events')->where('link_id', $gid)->count() === 0 && intval($l->fails) === 1, [$err, count($calls)]);

    // the cap of an ICS link: 5000 events a calendar
    $many = [];
    for ($i = 0; $i < Ics::MAX_EVENTS + 20; $i++) {
        $many[] = ['id' => 'm' . $i, 'status' => 'confirmed', 'summary' => 'many ' . $i, 'start' => ['dateTime' => '2026-12-20T01:00:00Z'], 'end' => ['dateTime' => '2026-12-20T02:00:00Z']];
    }
    $google['full'] = function () use (&$many) {
        return gw_json(['items' => $many, 'nextSyncToken' => 'sync-many']);
    };
    DB::table('gw_cal_links')->where('id', $gid)->update(['sync_state' => null]);
    $err = Links::sync(DB::table('gw_cal_links')->where('id', $gid)->first(), null, '2026-12-02 03:00:00', $cloud);
    $l = DB::table('gw_cal_links')->where('id', $gid)->first();
    gw_check('cap: at most 5000 events a link (too_many, kept as a code, not a failure)', $err === 'too_many' && DB::table('gw_cal_events')->where('link_id', $gid)->count() === Ics::MAX_EVENTS && intval($l->event_count) === Ics::MAX_EVENTS && intval($l->fails) === 0, [$err, DB::table('gw_cal_events')->where('link_id', $gid)->count()]);
    $google['inc'] = function () {
        return gw_json(['items' => [['id' => 'one-more', 'status' => 'confirmed', 'summary' => 'x', 'start' => ['dateTime' => '2026-12-21T01:00:00Z'], 'end' => ['dateTime' => '2026-12-21T02:00:00Z']]], 'nextSyncToken' => 'sync-more']);
    };
    $err = Links::sync(DB::table('gw_cal_links')->where('id', $gid)->first(), null, '2026-12-02 03:20:00', $cloud);
    gw_check('cap: a change does not go past it either', $err === 'too_many' && DB::table('gw_cal_events')->where('link_id', $gid)->count() === Ics::MAX_EVENTS);
    $w = Events::window('2026-12-01 00:00:00', '2027-01-01 00:00:00', [$owner], $owner, '予定あり');
    gw_check('cap: the window of the calendar keeps its limit (' . Events::MAX_ITEMS . ')', count($w) <= Events::MAX_ITEMS && count($w) === Ics::MAX_EVENTS);

    // removed: Google's token revoked (one short request), the events go
    $calls = [];
    $handler = function ($m, $u, $h, $b) {
        return $u === 'https://oauth2.googleapis.com/revoke' ? gw_json([]) : ['status' => 404, 'headers' => [], 'body' => ''];
    };
    gw_check('delete: the person\'s own link removed', Links::delete($owner, $gid, $oauth) === true);
    parse_str($calls[0]['body'] ?? '', $rf);
    gw_check('delete: Google\'s refresh token revoked', count($calls) === 1 && $calls[0]['url'] === 'https://oauth2.googleapis.com/revoke' && ($rf['token'] ?? '') === 'g-refresh-1');
    gw_check('delete: its events and its tokens gone', DB::table('gw_cal_events')->where('link_id', $gid)->count() === 0 && DB::table('gw_cal_links')->count() === 0);

    // ---- a Microsoft 365 calendar ------------------------------------------------------------------
    DB::reset();
    $GLOBALS['gw_now'] = '2026-12-01 00:00:00';
    [$mid] = Links::connect($owner, 'microsoft', null, ['access_token' => 'ms-access-1', 'refresh_token' => 'ms-refresh-1', 'expires_at' => time() + 3600], 'owner@example.invalid', 'Microsoft 365');
    $msFull1 = ['value' => [
        ['id' => 'AAMk1', 'type' => 'singleInstance', 'subject' => '<script>x</script> Review', 'location' => ['displayName' => 'Room A'], 'body' => ['contentType' => 'text', 'content' => 'notes'],
            'start' => ['dateTime' => '2026-12-03T01:00:00.0000000', 'timeZone' => 'UTC'], 'end' => ['dateTime' => '2026-12-03T02:00:00.0000000', 'timeZone' => 'UTC'], 'isAllDay' => false, 'sensitivity' => 'normal'],
        ['id' => 'AAMkSeries', 'type' => 'seriesMaster', 'subject' => 'Series', 'start' => ['dateTime' => '2026-01-01T01:00:00.0000000', 'timeZone' => 'UTC'], 'end' => ['dateTime' => '2026-01-01T02:00:00.0000000', 'timeZone' => 'UTC']],
    ], '@odata.nextLink' => 'https://graph.microsoft.com/v1.0/me/calendarView/delta?$skiptoken=page2'];
    $msFull2 = ['value' => [
        ['id' => 'AAMkOcc1', 'type' => 'occurrence', 'seriesMasterId' => 'AAMkSeries', 'subject' => 'Standup', 'start' => ['dateTime' => '2026-12-04T00:00:00.0000000', 'timeZone' => 'UTC'], 'end' => ['dateTime' => '2026-12-04T00:15:00.0000000', 'timeZone' => 'UTC']],
        ['id' => 'AAMkOcc2', 'type' => 'occurrence', 'seriesMasterId' => 'AAMkSeries', 'subject' => 'Standup', 'start' => ['dateTime' => '2026-12-05T00:00:00.0000000', 'timeZone' => 'UTC'], 'end' => ['dateTime' => '2026-12-05T00:15:00.0000000', 'timeZone' => 'UTC']],
        ['id' => 'AAMkDay', 'type' => 'singleInstance', 'subject' => 'Off', 'isAllDay' => true, 'start' => ['dateTime' => '2026-12-28T00:00:00.0000000', 'timeZone' => 'UTC'], 'end' => ['dateTime' => '2026-12-29T00:00:00.0000000', 'timeZone' => 'UTC']],
        ['id' => 'AAMkPriv', 'type' => 'singleInstance', 'subject' => 'Private talk', 'sensitivity' => 'private', 'start' => ['dateTime' => '2026-12-07T05:00:00.0000000', 'timeZone' => 'UTC'], 'end' => ['dateTime' => '2026-12-07T06:00:00.0000000', 'timeZone' => 'UTC']],
    ], '@odata.deltaLink' => 'https://graph.microsoft.com/v1.0/me/calendarView/delta?$deltatoken=' . str_repeat('d', 400)];
    $ms = ['inc' => null];
    $handler = function ($m, $u, $h) use (&$ms, $msFull1, $msFull2) {
        if (($h['Authorization'] ?? '') !== 'Bearer ms-access-1') {
            return ['status' => 401, 'headers' => [], 'body' => '{}'];
        }
        if (strpos($u, 'startDateTime=') !== false) {
            return gw_json($msFull1);
        }
        if (strpos($u, 'skiptoken=page2') !== false) {
            return gw_json($msFull2);
        }
        if (strpos($u, 'deltatoken=') !== false && $ms['inc'] !== null) {
            return ($ms['inc'])($u);
        }
        return ['status' => 404, 'headers' => [], 'body' => ''];
    };
    $calls = [];
    $err = Links::sync(DB::table('gw_cal_links')->where('id', $mid)->first(), null, '2026-12-01 00:00:00', $cloud);
    $link = DB::table('gw_cal_links')->where('id', $mid)->first();
    $byExt = [];
    foreach (DB::table('gw_cal_events')->where('link_id', $mid)->get() as $e) {
        $byExt[$e->external_id] = $e;
    }
    $q0 = gw_q($calls[0]['url']);
    gw_check('microsoft: calendarView delta of the window, times in UTC as text', $err === null && strpos($calls[0]['url'], CloudSync::MS_DELTA . '?') === 0 && $q0['startDateTime'] === '2026-11-01T00:00:00Z' && $q0['endDateTime'] === '2027-05-30T00:00:00Z'
        && strpos($calls[0]['headers']['Prefer'], 'outlook.timezone="UTC"') !== false && strpos($calls[0]['headers']['Prefer'], 'outlook.body-content-type="text"') !== false, [$err, $calls[0] ?? null]);
    gw_check('microsoft: the nextLink followed, the deltaLink kept (longer than 255)', count($calls) === 2 && strlen(strval($link->sync_state)) > 255 && strpos($link->sync_state, 'deltatoken=') !== false);
    gw_check('microsoft: occurrences one by one, the series itself not', count($byExt) === 5 && !isset($byExt['AAMkSeries']) && isset($byExt['AAMkOcc1'], $byExt['AAMkOcc2']), array_keys($byExt));
    gw_check('microsoft: times and all day', $byExt['AAMk1']->starts_at === '2026-12-03 01:00:00' && intval($byExt['AAMkDay']->all_day) === 1 && $byExt['AAMkDay']->starts_at === '2026-12-28 00:00:00' && $byExt['AAMkDay']->ends_at === '2026-12-29 00:00:00');
    gw_check('microsoft: the title with markup kept as text', $byExt['AAMk1']->title === '<script>x</script> Review');
    $ms['inc'] = function ($u) {
        return gw_json(['value' => [
            ['id' => 'AAMkOcc2', '@removed' => ['reason' => 'deleted']],
            ['id' => 'AAMk1', 'type' => 'singleInstance', 'subject' => 'Review 2', 'start' => ['dateTime' => '2026-12-03T03:00:00.0000000', 'timeZone' => 'UTC'], 'end' => ['dateTime' => '2026-12-03T04:00:00.0000000', 'timeZone' => 'UTC']],
            ['id' => 'AAMkDay', 'isCancelled' => true, 'type' => 'singleInstance', 'start' => ['dateTime' => '2026-12-28T00:00:00.0000000', 'timeZone' => 'UTC'], 'end' => ['dateTime' => '2026-12-29T00:00:00.0000000', 'timeZone' => 'UTC']],
        ], '@odata.deltaLink' => 'https://graph.microsoft.com/v1.0/me/calendarView/delta?$deltatoken=second']);
    };
    $calls = [];
    $before = DB::table('gw_cal_events')->where('link_id', $mid)->where('external_id', 'AAMk1')->first();
    $err = Links::sync(DB::table('gw_cal_links')->where('id', $mid)->first(), null, '2026-12-01 00:20:00', $cloud);
    $ext = DB::table('gw_cal_events')->where('link_id', $mid)->pluck('external_id')->all();
    $after = DB::table('gw_cal_events')->where('link_id', $mid)->where('external_id', 'AAMk1')->first();
    gw_check('microsoft: then the changes with the deltaLink', $err === null && count($calls) === 1 && strpos($calls[0]['url'], 'deltatoken=ddd') !== false && DB::table('gw_cal_links')->where('id', $mid)->first()->sync_state === 'https://graph.microsoft.com/v1.0/me/calendarView/delta?$deltatoken=second');
    gw_check('microsoft: removed (@removed) and cancelled removed here, a change in place', !in_array('AAMkOcc2', $ext, true) && !in_array('AAMkDay', $ext, true) && $after->id === $before->id && $after->title === 'Review 2', $ext);
    // the private one: busy even with details
    Links::update($owner, $mid, ['name' => 'MS', 'show_as' => 'details']);
    Links::sync(DB::table('gw_cal_links')->where('id', $mid)->first(), null, '2026-12-01 00:40:00', $cloud);
    $other = Events::window('2026-12-01 00:00:00', '2027-01-01 00:00:00', [$owner], 9, '予定あり');
    $t = array_column($other, 'title');
    gw_check('microsoft: details shown, a private one 「予定あり」', in_array('<script>x</script> Review', $t, true) && !in_array('Private talk', $t, true) && in_array('予定あり', $t, true), $t);
    // 410: everything again
    $ms['inc'] = function () {
        return ['status' => 410, 'headers' => [], 'body' => '{"error":{"code":"SyncStateNotFound","message":"x"}}'];
    };
    $calls = [];
    $err = Links::sync(DB::table('gw_cal_links')->where('id', $mid)->first(), null, '2026-12-01 01:00:00', $cloud);
    gw_check('microsoft: 410 → a full read again at once', $err === null && count($calls) === 3 && strpos($calls[1]['url'], 'startDateTime=') !== false, [$err, array_column($calls, 'url')]);
    // a nextLink to another host: not followed, nothing changed
    $n = DB::table('gw_cal_events')->where('link_id', $mid)->count();
    $ms['inc'] = function () {
        return gw_json(['value' => [['id' => 'AAMk1', '@removed' => ['reason' => 'deleted']]], '@odata.nextLink' => 'https://graph.microsoft.com.evil.example.invalid/next']);
    };
    $calls = [];
    $err = Links::sync(DB::table('gw_cal_links')->where('id', $mid)->first(), null, '2026-12-01 01:20:00', $cloud);
    gw_check('microsoft: a nextLink of another host refused, nothing changed', $err === 'host' && count($calls) === 1 && DB::table('gw_cal_events')->where('link_id', $mid)->count() === $n);
    // Microsoft's refresh token changes each time
    $handler = function ($m, $u, $h, $b) {
        parse_str($b, $f);
        return strpos($u, 'login.microsoftonline.com/organizations/oauth2/v2.0/token') !== false && $f['refresh_token'] === 'ms-refresh-1' && $f['scope'] === 'https://graph.microsoft.com/Calendars.Read offline_access'
            ? gw_json(['access_token' => 'ms-access-2', 'refresh_token' => 'ms-refresh-2', 'expires_in' => 3600]) : ['status' => 400, 'headers' => [], 'body' => '{}'];
    };
    $tok = $oauth->accessToken(DB::table('gw_cal_links')->where('id', $mid)->first(), true);
    $s = CloudOAuth::secret(DB::table('gw_cal_links')->where('id', $mid)->first());
    gw_check('microsoft: a renewed token, its new refresh token kept at once', $tok === 'ms-access-2' && $s['refresh_token'] === 'ms-refresh-2');
    $calls = [];
    gw_check('microsoft: removed without a request (only forgotten)', Links::delete($owner, $mid, $oauth) === true && $calls === [] && DB::table('gw_cal_events')->count() === 0);

    // a person who is gone: never asked of Google
    DB::reset();
    [$gone] = Links::connect(42, 'google', null, ['access_token' => 'ya29-access-1', 'refresh_token' => 'x', 'expires_at' => time() + 3600], '', 'x');
    $calls = [];
    $res = Links::run(10, null, 0, $cloud);
    gw_check('a deleted person\'s Google link: not read', $res['failed'] === 1 && $calls === [] && DB::table('gw_cal_links')->where('id', $gone)->first()->last_error === 'not_allowed');

    // ---- the fixes of the review of part 6 --------------------------------------------------------
    // each provider only its own hosts
    $calls = [];
    $handler = function () {
        return gw_json([]);
    };
    gw_check('hosts: a Google request never goes to Microsoft\'s hosts, nor the other way', gw_code(function () use ($fetch) {
        $fetch->request('GET', 'https://graph.microsoft.com/v1.0/me', [], null, 'google');
    }) === 'host' && gw_code(function () use ($fetch) {
        $fetch->request('POST', 'https://oauth2.googleapis.com/token', [], [], 'microsoft');
    }) === 'host' && $calls === [] && CloudFetch::allowedUrl('https://login.microsoftonline.com/x', 'microsoft') && !CloudFetch::allowedUrl('https://www.googleapis.com/x', 'microsoft'));
    DB::reset();
    [$mid] = Links::connect($owner, 'microsoft', null, ['access_token' => 'ms-access-1', 'refresh_token' => 'ms-refresh-1', 'expires_at' => time() + 3600], 'owner@example.invalid', 'MS');
    DB::table('gw_cal_links')->where('id', $mid)->update(['sync_state' => 'https://www.googleapis.com/calendar/v3/x?$deltatoken=1', 'full_sync_at' => '2026-12-01 00:00:00']);
    $handler = function ($m, $u) {
        return strpos($u, 'startDateTime=') !== false ? gw_json(['value' => [], '@odata.nextLink' => 'https://www.googleapis.com/next']) : ['status' => 404, 'headers' => [], 'body' => ''];
    };
    $calls = [];
    $err = Links::sync(DB::table('gw_cal_links')->where('id', $mid)->first(), null, '2026-12-01 00:10:00', $cloud);
    gw_check('hosts: a Microsoft cursor or nextLink of a Google host: never asked (a full read; the nextLink refused)', $err === 'host' && count($calls) === 1 && strpos($calls[0]['url'], CloudSync::MS_DELTA) === 0, [$err, array_column($calls, 'url')]);

    // the scope granted is checked
    $tok = function (string $provider, array $answer) use ($oauth, &$handler) {
        $handler = function () use ($answer) {
            return gw_json($answer);
        };
        $q = gw_q($oauth->authorizeUrl($provider, 5));
        return gw_code(function () use ($oauth, $q) {
            $oauth->finish(5, $q['state'], 'the-code');
        });
    };
    gw_check('scope: Google without calendar.readonly refused (oauth_scope)', $tok('google', ['access_token' => 'a', 'refresh_token' => 'r', 'scope' => 'openid https://www.googleapis.com/auth/userinfo.email']) === 'oauth_scope');
    gw_check('scope: Google with calendar.readonly accepted', $tok('google', ['access_token' => 'a', 'refresh_token' => 'r', 'scope' => 'https://www.googleapis.com/auth/calendar.readonly']) === null);
    gw_check('scope: Microsoft without Calendars.Read refused', $tok('microsoft', ['access_token' => 'a', 'refresh_token' => 'r', 'scope' => 'User.Read offline_access']) === 'oauth_scope');
    gw_check('scope: Microsoft Calendars.Read (short or as a URI) accepted', $tok('microsoft', ['access_token' => 'a', 'refresh_token' => 'r', 'scope' => 'Calendars.Read offline_access']) === null
        && $tok('microsoft', ['access_token' => 'a', 'refresh_token' => 'r', 'scope' => 'https://graph.microsoft.com/Calendars.Read']) === null);
    $lang = include $base . '/resources/lang/ja/groupware.php';
    $langEn = include $base . '/resources/lang/en/groupware.php';
    gw_check('scope: its words in ja and en', isset($lang['cal_links']['e_oauth_scope'], $langEn['cal_links']['e_oauth_scope'], $lang['cal_links']['e_oauth_client'], $langEn['cal_links']['e_oauth_client']));

    // the site's app refused (invalid_client): the administrator's to fix, no 「再接続」
    DB::table('gw_cal_links')->where('id', $mid)->update(['secret_enc' => CloudOAuth::encryptSecret(['access_token' => 'x', 'refresh_token' => 'ms-refresh-1', 'expires_at' => time() - 10]), 'sync_state' => null]);
    $handler = function () {
        return ['status' => 401, 'headers' => [], 'body' => '{"error":"invalid_client","error_description":"AADSTS7000222: the client secret expired"}'];
    };
    $err = Links::sync(DB::table('gw_cal_links')->where('id', $mid)->first(), null, '2026-12-01 00:30:00', $cloud);
    $l = Links::list($owner)[0];
    gw_check('invalid_client: a code of its own (oauth_client), no reconnect offered', $err === 'oauth_client' && $l['last_error'] === 'oauth_client' && $l['reconnect'] === false);

    // read now: the row taken first, as the batch takes it
    $row = DB::table('gw_cal_links')->where('id', $mid)->first();
    $stale = clone $row;
    gw_check('take: the first read takes the link, a second one at once does not', Links::take($row, '2026-12-01 01:00:00') && !Links::take($stale, '2026-12-01 01:00:00'));
    $page = file_get_contents($base . '/Calendar/LinksPage.php');
    gw_check('take: 「今すぐ読む」 takes the link before it reads it', preg_match('/Links::take\(\$l, Links::utcNow\(\)\)[\s\S]{0,200}Links::sync\(\$l/', $page) === 1);

    // rows of one event twice (two reads at once): the extras removed by the next read
    DB::reset();
    [$gid] = Links::connect($owner, 'google', null, ['access_token' => 'ya29-access-1', 'refresh_token' => 'g-refresh-1', 'expires_at' => time() + 3600], 'owner@example.invalid', 'G');
    foreach ([1, 2, 3] as $i) {
        DB::table('gw_cal_events')->insertGetId(['owner_user_id' => $owner, 'source' => 'link', 'link_id' => $gid, 'external_id' => 'dup', 'external_etag' => 'old', 'title' => 'dup ' . $i,
            'starts_at' => '2026-12-10 01:00:00', 'ends_at' => '2026-12-10 02:00:00', 'all_day' => 0, 'tz' => 'UTC', 'rrule' => null, 'visibility' => 'busy', 'deleted_at' => null]);
    }
    $handler = function ($m, $u) {
        return strpos($u, CloudSync::GOOGLE_EVENTS) === 0 ? gw_json(['items' => [['id' => 'dup', 'status' => 'confirmed', 'summary' => 'once', 'start' => ['dateTime' => '2026-12-10T01:00:00Z'], 'end' => ['dateTime' => '2026-12-10T02:00:00Z']]], 'nextSyncToken' => 's1'])
            : ['status' => 404, 'headers' => [], 'body' => ''];
    };
    DB::table('gw_cal_links')->where('id', $gid)->update(['sync_state' => 's0', 'full_sync_at' => '2026-12-01 00:00:00']);
    $err = Links::sync(DB::table('gw_cal_links')->where('id', $gid)->first(), null, '2026-12-01 00:10:00', $cloud);
    $rows = DB::table('gw_cal_events')->where('link_id', $gid)->get()->all();
    gw_check('duplicates: one row an event again (the extras removed), changed in place', $err === null && count($rows) === 1 && $rows[0]->title === 'once' && intval(DB::table('gw_cal_links')->where('id', $gid)->first()->event_count) === 1, [$err, count($rows)]);
    // the ICS links too
    $ics = DB::table('gw_cal_links')->insertGetId(['user_id' => $owner, 'provider' => 'ics', 'name' => 'I', 'show_as' => 'busy', 'enabled' => 1]);
    foreach ([1, 2] as $i) {
        DB::table('gw_cal_events')->insertGetId(['owner_user_id' => $owner, 'source' => 'link', 'link_id' => $ics, 'external_id' => 'u1', 'external_etag' => 'x', 'title' => 't', 'starts_at' => '2026-12-10 01:00:00', 'ends_at' => '2026-12-10 02:00:00']);
    }
    Links::apply(DB::table('gw_cal_links')->where('id', $ics)->first(), ['u1' => ['title' => 'one', 'starts_at' => '2026-12-10 01:00:00', 'ends_at' => '2026-12-10 02:00:00', 'all_day' => false, 'tz' => 'UTC']]);
    gw_check('duplicates: an ICS link\'s extras removed too', DB::table('gw_cal_events')->where('link_id', $ics)->count() === 1);
    // the cursor goes with the events (apply writes it in its transaction)
    $tm = false;
    CloudSync::apply(DB::table('gw_cal_links')->where('id', $gid)->first(), [], false, '2026-11-01 00:00:00', '2027-05-30 00:00:00', $tm, ['sync_state' => 'with-the-events']);
    gw_check('cursor: written by apply, with the events', DB::table('gw_cal_links')->where('id', $gid)->first()->sync_state === 'with-the-events');
    $src = file_get_contents($base . '/Calendar/CloudSync.php');
    gw_check('cursor: in the same transaction as the events', preg_match('/DB::transaction\(function \(\) use \([^)]*\$state[\s\S]*?update\(\$state \+ \[\'event_count\'/', $src) === 1);

    // no columns of part 6 (the migration not run): a Google link cannot be changed
    $setReady = function ($v) {
        static::$cloudReady = $v;
    };
    \Closure::bind($setReady, null, Links::class)(false);
    gw_check('migration missing: a change of a Google link refused (not_ready)', Links::update($owner, $gid, ['name' => 'x', 'show_as' => 'details']) === 'not_ready');
    \Closure::bind($setReady, null, Links::class)(true);

    // not allowed for 30 days: the tokens forgotten, the link and its events kept
    DB::reset();
    [$gid] = Links::connect(42, 'google', null, ['access_token' => 'ya29-access-1', 'refresh_token' => 'g-refresh-1', 'expires_at' => time() + 3600], '', 'G');
    DB::table('gw_cal_links')->where('id', $gid)->update(['last_error' => 'not_allowed', 'last_sync_at' => '2026-10-01 00:00:00', 'next_sync_at' => null]);
    $GLOBALS['gw_now'] = '2026-10-20 00:00:00';
    Links::run(10, null, 0, $cloud);
    gw_check('not allowed: the tokens kept before 30 days', !empty(DB::table('gw_cal_links')->where('id', $gid)->first()->secret_enc));
    $GLOBALS['gw_now'] = '2026-11-05 00:00:00';
    DB::table('gw_cal_links')->where('id', $gid)->update(['next_sync_at' => null]);
    Links::run(10, null, 0, $cloud);
    $l = DB::table('gw_cal_links')->where('id', $gid)->first();
    gw_check('not allowed: after 30 days the tokens forgotten, the link kept (sign in again)', empty($l->secret_enc) && $l->last_error === 'not_allowed' && Links::list(42)[0]['reconnect'] === true);
    $GLOBALS['gw_now'] = '2026-12-01 00:00:00';

    // the client secrets of the settings never written back into the page
    require $base . '/SettingSecretField.php';
    $sf = new \App\Plugins\ExmentGroupware\SettingSecretField();
    $sf->original = Crypt::encrypt('g-client-secret');
    $sf->fill($sf->original);
    gw_check('settings: the secret\'s input empty (not the decrypted secret)', $sf->value === '');
    gw_check('settings: saved empty, the secret kept; a new one saved encrypted', $sf->prepare('') === $sf->original && Crypt::decrypt($sf->prepare('new-secret')) === 'new-secret');
    $ps = file_get_contents($base . '/PluginSetting.php');
    gw_check('settings: both calendar secrets use that field', substr_count($ps, "gwSettingSecret('cal_") === 2 && strpos($ps, "encpassword('cal_") === false);

    gw_check('logs: never a token or a secret', !preg_match('/ya29|refresh-|client-secret|ms-access/', implode("\n", \Illuminate\Support\Facades\Log::$lines)), \Illuminate\Support\Facades\Log::$lines);

    // the page
    $src = file_get_contents($base . '/resources/views/calendar_links.blade.php');
    gw_check('page: no markup from data', !preg_match('/innerHTML|outerHTML|insertAdjacentHTML|document\.write/', $src));
    gw_check('page: the Google and Microsoft buttons, the account, sign in again', strpos($src, "'connect_' + p") !== false && strpos($src, 'l.account') !== false && strpos($src, "t('reconnect')") !== false && !preg_match('/secret|access_token|refresh_token/', $src));
    gw_check('page: only an https address of the server is followed', strpos($src, "/^https:\\/\\//.test") !== false);
    foreach (['ja', 'en'] as $lang) {
        $l = include $base . '/resources/lang/' . $lang . '/groupware.php';
        $missing = array_diff(['cloud_title', 'cloud_help', 'connect_google', 'connect_microsoft', 'reconnect', 'name_google', 'name_microsoft', 'provider_google', 'provider_microsoft', 'oauth_connected',
            'e_oauth_denied', 'e_oauth_failed', 'e_oauth_state', 'e_oauth_token', 'e_not_configured', 'e_auth', 'e_host', 'err_not_configured'], array_keys($l['cal_links']));
        gw_check('lang ' . $lang . ': the words of part 6', $missing === [], $missing);
    }
    $cfg = json_decode(file_get_contents($base . '/config.json'), true);
    $routes = array_column($cfg['route'], 'function', 'uri');
    $page = file_get_contents($base . '/PluginPage.php');
    gw_check('routes: the sign-in, sign-in again and the way back, each with its method', ($routes['cal/api/oauth/{provider}'] ?? '') === 'calLinkOauth' && ($routes['cal/api/link/{id}/reconnect'] ?? '') === 'calLinkReconnect'
        && ($routes['cal/oauth/callback'] ?? '') === 'calOauthCallback' && strpos($page, 'function calLinkOauth(') !== false && strpos($page, 'function calLinkReconnect(') !== false && strpos($page, 'function calOauthCallback(') !== false);
    $byUri = [];
    foreach ($cfg['route'] as $r) {
        $byUri[$r['uri']] = $r;
    }
    gw_check('routes: the sign-in started by POST (CSRF), the way back a GET behind the login', $byUri['cal/api/oauth/{provider}']['method'] === 'post' && $byUri['cal/api/link/{id}/reconnect']['method'] === 'post'
        && $byUri['cal/oauth/callback']['method'] === 'get' && empty($byUri['cal/oauth/callback']['middleware_except']));

    echo $failed === 0 ? "all passed\n" : "$failed failed\n";
    exit($failed === 0 ? 0 : 1);
}
