<?php
// ExmentGroupware: the web mail's OAuth client secrets in the plugin's settings (SecretOption,
// SecretField, Mail\OAuth, the migration 2026_11_29_000000_encrypt_gw_mail_client_secrets),
// without Exment: the value saved is encrypted (the data keys when the site has one, else the app
// key), the form never writes it into the page, an empty field keeps it, "remove" removes it, the
// token request gets it decrypted (and is never sent with an empty secret), the migration of a
// plain value gives the same result when it runs twice, and the calendar's secrets use the same
// field. Stand-ins of Laravel and of the form field; nothing is sent anywhere (the HTTP
// client is a fake).
//
//   php groupware/tools/test-gw-mail-secret.php

namespace Illuminate\Support\Facades {
    class GwFakeDb
    {
        /** @var array<string, array<int, array<string, mixed>>> table => id => row */
        public static $tables = [];
        /** @var int */
        public static $updates = 0;
    }

    class GwFakeQuery
    {
        public $table;
        public $wheres = [];

        public function __construct(string $table)
        {
            $this->table = $table;
        }

        public function where($col, $val)
        {
            $this->wheres[$col] = $val;
            return $this;
        }

        protected function rows(): array
        {
            return array_filter(GwFakeDb::$tables[$this->table] ?? [], function ($r) {
                foreach ($this->wheres as $c => $v) {
                    if (($r[$c] ?? null) != $v) {
                        return false;
                    }
                }
                return true;
            });
        }

        public function get($cols = ['*'])
        {
            return array_map(function ($r) {
                return (object) $r;
            }, array_values($this->rows()));
        }

        public function update(array $values)
        {
            foreach (array_keys($this->rows()) as $id) {
                GwFakeDb::$tables[$this->table][$id] = array_merge(GwFakeDb::$tables[$this->table][$id], $values);
                GwFakeDb::$updates++;
            }
            return 1;
        }
    }

    class DB
    {
        public static function table($name)
        {
            return new GwFakeQuery($name);
        }
    }

    class Schema
    {
        public static function hasTable($t)
        {
            return true;
        }

        public static function hasColumn($t, $c)
        {
            return true;
        }
    }

    class Crypt
    {
        public static function encryptString($v)
        {
            return 'crypt:' . base64_encode(strrev($v));
        }

        public static function decryptString($v)
        {
            return strrev(base64_decode(substr($v, 6)));
        }

        // a payload like Laravel's encrypt(): base64 of {iv, value, mac}
        public static function encrypt($value)
        {
            return base64_encode(json_encode(['iv' => 'aXY=', 'value' => base64_encode(strrev(serialize($value))), 'mac' => 'fake', 'tag' => '']));
        }

        public static function decrypt($payload)
        {
            $json = json_decode(strval(base64_decode($payload, true)), true);
            if (!is_array($json) || !isset($json['value'])) {
                throw new \RuntimeException('The payload is invalid.');
            }
            return unserialize(strrev(base64_decode($json['value'])));
        }
    }

    class GwFakeResponse
    {
        public $data;

        public function __construct(array $data)
        {
            $this->data = $data;
        }

        public function json()
        {
            return $this->data;
        }

        public function successful()
        {
            return true;
        }

        public function status()
        {
            return 200;
        }
    }

    class Http
    {
        /** @var array<int, array{url: string, form: array<string, string>}> */
        public static $sent = [];

        public static function asForm()
        {
            return new static();
        }

        public function timeout($s)
        {
            return $this;
        }

        public function post($url, $form)
        {
            static::$sent[] = ['url' => $url, 'form' => $form];
            return new GwFakeResponse(['access_token' => 'fake-access', 'refresh_token' => 'fake-refresh-2', 'expires_in' => 3600]);
        }
    }

    class Log
    {
        public static function getFacadeRoot()
        {
            return null;
        }
    }
}

namespace Illuminate\Database\Migrations {
    class Migration
    {
    }
}

namespace Exceedone\Exment\Services\Encryption {
    // Exment 7.3's data keys: on or off by the test
    class DataKeys
    {
        public static $has = false;

        public static function hasKey()
        {
            return static::$has;
        }
    }

    class EncryptedValue
    {
        const PREFIX = 'enc:v1:';

        public static function isEncrypted($v)
        {
            return is_string($v) && strpos($v, static::PREFIX) === 0;
        }

        public static function encrypt(string $plain)
        {
            return static::PREFIX . 'k1:' . base64_encode(strrev($plain));
        }

        public static function decrypt(string $value)
        {
            return strrev(strval(base64_decode(substr($value, strlen(static::PREFIX . 'k1:')))));
        }
    }
}

namespace ExmentAdminCore\Admin\Form\Field {
    // enough of the admin's password field: fill, original, attributes, help, and an input written as the admin writes it
    class Password
    {
        protected $column;
        protected $label;
        protected $value;
        protected $original;
        protected $elementName = '';
        protected $attributes = [];
        protected $placeholder = '';
        protected $helpText = '';

        public function __construct($column = '', $arguments = [])
        {
            $this->column = $column;
            $this->label = $arguments[0] ?? $column;
        }

        public function fill($data)
        {
            if (empty($this->value)) {
                $this->value = $data[$this->column] ?? null;
            }
            $this->formatValue();
        }

        protected function formatValue()
        {
        }

        public function setOriginal($data)
        {
            $this->original = $data[$this->column] ?? null;
        }

        public function setElementName($name)
        {
            $this->elementName = $name;
            return $this;
        }

        public function getElementName()
        {
            return $this->elementName;
        }

        public function attribute($k, $v = null)
        {
            $this->attributes[$k] = $v;
            return $this;
        }

        public function placeholder($p = null)
        {
            $this->placeholder = $p;
            return $this;
        }

        public function help($text = '', $icon = 'fa-info-circle')
        {
            $this->helpText = $text;
            return $this;
        }

        public function prepare($value)
        {
            return $value;
        }

        protected $append;

        public function append($string)
        {
            if ($this->append === null) {
                $this->append = $string;
            }
            return $this;
        }

        public function render()
        {
            $attrs = $this->attributes + ['type' => 'password', 'name' => $this->elementName, 'value' => strval($this->value), 'placeholder' => $this->placeholder];
            $html = '';
            foreach ($attrs as $k => $v) {
                $html .= ' ' . $k . '="' . htmlspecialchars(strval($v)) . '"';
            }
            // as admin-core 2.x: the help goes through html_clean (markup such as a check box is
            // removed); the append is written as it is, in the input group (1.x and 2.x)
            return '<label>' . htmlspecialchars($this->label) . '</label><div class="input-group"><input' . $html . ' />'
                . ($this->append ? '<span class="input-group-addon clearfix">' . $this->append . '</span>' : '')
                . '</div><span class="help-block">' . strip_tags($this->helpText) . '</span>';
        }
    }
}

namespace {
    // ---- Laravel's helpers -------------------------------------------------------------
    $GLOBALS['gwSession'] = [];
    function session($key = null, $default = null)
    {
        if (is_array($key)) {
            $GLOBALS['gwSession'] = array_merge($GLOBALS['gwSession'], $key);
            return null;
        }
        return $GLOBALS['gwSession'][$key] ?? $default;
    }

    function e($v)
    {
        return htmlspecialchars(strval($v), ENT_QUOTES, 'UTF-8');
    }

    $GLOBALS['gwLocale'] = 'ja';
    function trans($key)
    {
        $words = require __DIR__ . '/../ExmentGroupware/resources/lang/' . $GLOBALS['gwLocale'] . '/groupware.php';
        $k = preg_replace('/^exment_exment_groupware::groupware\./', '', $key);
        $v = \Illuminate\Support\Arr::get($words, $k);
        return is_string($v) ? $v : $key;
    }

    $GLOBALS['gwRequest'] = [];
    class GwFakeRequest
    {
        public function input($key)
        {
            return \Illuminate\Support\Arr::get($GLOBALS['gwRequest'], $key);
        }
    }

    function request()
    {
        return new GwFakeRequest();
    }

    class GwFakeNow
    {
        public function addSeconds($s)
        {
            return $this;
        }

        public function toDateTimeString()
        {
            return '2026-11-29 00:00:00';
        }

        public function __toString()
        {
            return '2026-11-29 00:00:00';
        }
    }

    function now()
    {
        return new GwFakeNow();
    }
}

namespace Illuminate\Support {
    class Arr
    {
        public static function get($a, $key)
        {
            foreach (explode('.', $key) as $k) {
                if (!is_array($a) || !array_key_exists($k, $a)) {
                    return null;
                }
                $a = $a[$k];
            }
            return $a;
        }
    }
}

namespace {
    require __DIR__ . '/../ExmentGroupware/SecretOption.php';
    require __DIR__ . '/../ExmentGroupware/SecretField.php';
    require __DIR__ . '/../ExmentGroupware/Mail/MailException.php';
    require __DIR__ . '/../ExmentGroupware/Mail/Providers.php';
    require __DIR__ . '/../ExmentGroupware/Mail/Accounts.php';
    require __DIR__ . '/../ExmentGroupware/Mail/OAuth.php';

    use App\Plugins\ExmentGroupware\Mail\OAuth;
    use App\Plugins\ExmentGroupware\Mail\Providers;
    use App\Plugins\ExmentGroupware\SecretField;
    use App\Plugins\ExmentGroupware\SecretOption as S;
    use Exceedone\Exment\Services\Encryption\DataKeys;
    use Illuminate\Support\Facades\GwFakeDb;
    use Illuminate\Support\Facades\Http;

    $ok = 0;
    $bad = 0;
    $check = function (string $label, bool $cond) use (&$ok, &$bad) {
        // never prints a value: only what was checked
        echo ($cond ? 'OK  ' : 'NG  '), $label, "\n";
        $cond ? $ok++ : $bad++;
    };

    class GwFakePlugin
    {
        public $custom_options = [];

        public function getCustomOption($key, $default = null)
        {
            return $this->custom_options[$key] ?? $default;
        }

        public function getFullUrl(...$parts)
        {
            return 'https://exment.invalid/admin/plugins/exment_groupware/' . implode('/', $parts);
        }
    }

    /** The field as the settings form makes it: filled with the stored settings, then rendered or saved. */
    $field = function (string $column, array $stored): SecretField {
        $f = new SecretField($column, ['クライアント シークレット']);
        $f->setElementName('custom_options[' . $column . ']');
        $f->fill($stored);
        $f->setOriginal($stored);
        return $f;
    };

    $ms = 'ms-Secret~0123456789abcdefXYZ.q';
    $google = 'GOCSPX-abcdefghijklmnopQRSTUV';

    // ---- the round trip: save -> stored encrypted -> used decrypted --------------------
    foreach ([false => 'app key', true => 'data keys'] as $dataKeys => $name) {
        DataKeys::$has = (bool) $dataKeys;
        $GLOBALS['gwRequest'] = [];
        $saved = $field('ms_client_secret', [])->prepare($ms);
        $check("$name: saved, not the plain text", is_string($saved) && strpos($saved, $ms) === false && $saved !== $ms);
        $check("$name: the format", strpos($saved, $dataKeys ? 'enc:v1:' : 'app:') === 0);
        $check("$name: read back", S::reveal($saved) === $ms);
        $check("$name: sealed again, the same", S::seal($saved) === $saved);
    }
    DataKeys::$has = false;

    // the token request: the decrypted secret, renewed with the refresh token (Mail\OAuth)
    $plugin = new GwFakePlugin();
    $plugin->custom_options = [
        'ms_client_id' => 'ms-app', 'ms_client_secret' => S::seal($ms),
        'google_client_id' => 'g-app', 'google_client_secret' => S::seal($google),
    ];
    $oauth = new OAuth($plugin);
    $check('oauth: microsoft configured', $oauth->configured(Providers::MICROSOFT));
    $check('oauth: google configured', $oauth->configured(Providers::GMAIL));
    GwFakeDb::$tables['gw_mail_accounts'] = [7 => ['id' => 7]];
    foreach ([Providers::MICROSOFT => $ms, Providers::GMAIL => $google] as $provider => $plain) {
        Http::$sent = [];
        $account = (object) ['id' => 7, 'provider' => $provider, 'token_expires_at' => null,
            'secret_enc' => \Illuminate\Support\Facades\Crypt::encryptString(json_encode(['access_token' => '', 'refresh_token' => 'fake-refresh']))];
        $token = $oauth->accessToken($account);
        $sent = Http::$sent[0]['form'] ?? [];
        $check("oauth $provider: a refresh request", count(Http::$sent) === 1 && ($sent['grant_type'] ?? '') === 'refresh_token');
        $check("oauth $provider: the secret decrypted in the request", ($sent['client_secret'] ?? null) === $plain);
        $check("oauth $provider: the new token", $token === 'fake-access');
    }

    // a plain value of 0.5.0, before the migration ran: still works
    $plugin->custom_options['ms_client_secret'] = $ms;
    $check('oauth: a plain value of before still read', $oauth->config(Providers::MICROSOFT)['client_secret'] === $ms);
    // a value that cannot be decrypted (another key): no app, rather than a wrong secret
    $plugin->custom_options['ms_client_secret'] = 'app:broken';
    $check('oauth: undecryptable is not configured', !$oauth->configured(Providers::MICROSOFT));

    // the secret removed (or never set): no token request with an empty secret, refused at once
    foreach ([Providers::MICROSOFT => 'ms_client_secret', Providers::GMAIL => 'google_client_secret'] as $provider => $key) {
        $cleared = new GwFakePlugin();
        $cleared->custom_options = ['ms_client_id' => 'ms-app', 'google_client_id' => 'g-app', $key => null];
        $o = new OAuth($cleared);
        $check("cleared $provider: not configured", !$o->configured($provider));
        $refused = function (callable $f): bool {
            Http::$sent = [];
            try {
                $f();
            } catch (\App\Plugins\ExmentGroupware\Mail\MailException $e) {
                return count(Http::$sent) === 0 && strpos($e->getMessage(), 'クライアント シークレット') !== false;
            }
            return false;
        };
        $check("cleared $provider: authorizeUrl refused, nothing sent", $refused(function () use ($o, $provider) {
            $o->authorizeUrl($provider, []);
        }));
        $GLOBALS['gwSession'] = ['gw_mail_oauth' => ['st1' => ['provider' => $provider, 'verifier' => 'v', 'then' => [], 'at' => time()]]];
        $check("cleared $provider: finish refused, no token request", $refused(function () use ($o) {
            $o->finish('st1', 'code-1');
        }));
        $check("cleared $provider: refresh refused, no token request", $refused(function () use ($o, $provider) {
            $o->accessToken((object) ['id' => 7, 'provider' => $provider, 'token_expires_at' => null,
                'secret_enc' => \Illuminate\Support\Facades\Crypt::encryptString(json_encode(['access_token' => '', 'refresh_token' => 'fake-refresh']))]);
        }));
    }
    $set = new GwFakePlugin();
    $set->custom_options = ['google_client_id' => 'g-app', 'google_client_secret' => S::seal($google)];
    $url = (new OAuth($set))->authorizeUrl(Providers::GMAIL, []);
    $check('set: authorizeUrl given, without the secret', strpos($url, 'https://accounts.google.com/') === 0 && strpos($url, $google) === false && strpos($url, 'client_secret') === false);

    // ---- the form never contains the secret --------------------------------------------
    foreach (['ja', 'en'] as $locale) {
        $GLOBALS['gwLocale'] = $locale;
        foreach (['plain' => $ms, 'encrypted' => S::seal($ms)] as $kind => $stored) {
            $html = $field('ms_client_secret', ['ms_client_secret' => $stored])->render();
            $check("$locale form ($kind): no secret", strpos($html, $ms) === false && strpos($html, strval(S::seal($ms))) === false && strpos($html, strval($stored)) === false);
            $check("$locale form ($kind): the value is empty", preg_match('/ value=""/', $html) === 1 && substr_count($html, ' value="') === 2); // the input and the check box
            $check("$locale form ($kind): says it is set", strpos($html, $locale === 'ja' ? '設定済み' : 'Set:') !== false);
            $check("$locale form ($kind): the remove box", strpos($html, 'name="custom_options[ms_client_secret_clear]"') !== false);
            $check("$locale form ($kind): the remove box is a check box after the input", preg_match('#<input type="checkbox" name="custom_options\[ms_client_secret_clear\]" value="1"[^>]*>#', $html) === 1
                && strpos($html, 'input-group-addon') < strpos($html, 'custom_options[ms_client_secret_clear]') && strpos($html, $locale === 'ja' ? '値を消す' : 'Remove the value') !== false);
        }
        $html = $field('google_client_secret', ['ms_client_secret' => S::seal($ms)])->render();
        $check("$locale form (none): not set, no remove box", strpos($html, $locale === 'ja' ? '未設定' : 'Not set') !== false && strpos($html, '_clear') === false && strpos($html, $ms) === false);
    }
    $GLOBALS['gwLocale'] = 'ja';
    // the calendar's secrets: the same field (part 6)
    foreach (['cal_google_client_secret', 'cal_ms_client_secret'] as $key) {
        $calStored = [$key => S::seal('cal-secret-value')];
        $html = $field($key, $calStored)->render();
        $check("calendar $key: not shown, set, a remove box", strpos($html, 'cal-secret-value') === false && strpos($html, strval($calStored[$key])) === false && strpos($html, '設定済み') !== false && strpos($html, 'name="custom_options[' . $key . '_clear]"') !== false);
        $check("calendar $key: empty keeps it", $field($key, $calStored)->prepare(null) === $calStored[$key]);
        $GLOBALS['gwRequest'] = ['custom_options' => [$key . '_clear' => '1']];
        $check("calendar $key: removed", $field($key, $calStored)->prepare(null) === null);
        $GLOBALS['gwRequest'] = [];
        $old = \Illuminate\Support\Facades\Crypt::encrypt('enc-pass-value');
        $check("calendar $key: a value Exment's encpassword saved is kept and read", $field($key, [$key => $old])->prepare('') === $old && S::reveal($old, false) === 'enc-pass-value');
    }
    $check('calendar: a plain value is none (reveal without plain)', S::reveal('plain-value', false) === null && S::reveal('plain-value') === 'plain-value');
    $src = file_get_contents(__DIR__ . '/../ExmentGroupware/PluginSetting.php');
    $check('settings: all four secrets use SecretField', substr_count($src, 'new SecretField(') === 4 && strpos($src, 'gwSettingSecret') === false && !preg_match("/->(enc)?password\('/", $src));
    $check('settings: SettingSecretField removed', !file_exists(__DIR__ . '/../ExmentGroupware/SettingSecretField.php'));

    // ---- an empty submit keeps the value; "remove" removes it; a new value replaces it --
    $stored = ['ms_client_secret' => S::seal($ms)];
    $GLOBALS['gwRequest'] = ['custom_options' => ['ms_client_secret' => null]];
    $check('empty submit: kept as it was', $field('ms_client_secret', $stored)->prepare(null) === $stored['ms_client_secret']);
    $check('empty string submit: kept', $field('ms_client_secret', $stored)->prepare('') === $stored['ms_client_secret']);
    $check('spaces only: kept', $field('ms_client_secret', $stored)->prepare('   ') === $stored['ms_client_secret']);
    $plainKept = $field('ms_client_secret', ['ms_client_secret' => $ms])->prepare(null);
    $check('empty submit over a plain value of before: encrypted now', S::isSealed($plainKept) && S::reveal($plainKept) === $ms);
    $check('empty submit, nothing stored: nothing', $field('ms_client_secret', [])->prepare(null) === null);
    $replaced = $field('ms_client_secret', $stored)->prepare('new-secret-value');
    $check('a new value: replaces it, encrypted', S::reveal($replaced) === 'new-secret-value' && strpos($replaced, 'new-secret-value') === false);
    $GLOBALS['gwRequest'] = ['custom_options' => ['ms_client_secret' => null, 'ms_client_secret_clear' => '1']];
    $check('remove checked: removed', $field('ms_client_secret', $stored)->prepare(null) === null);
    $gStored = S::seal($google);
    $check('remove checked on the other field only: kept', $field('google_client_secret', ['google_client_secret' => $gStored])->prepare(null) === $gStored);
    $check('remove checked with a new value: the new value', S::reveal($field('ms_client_secret', $stored)->prepare('typed-again')) === 'typed-again');
    $GLOBALS['gwRequest'] = [];

    // ---- the migration of the upgrade: plain becomes encrypted, twice the same ----------
    GwFakeDb::$tables['plugins'] = [
        1 => ['id' => 1, 'plugin_name' => 'ExmentGroupware', 'custom_options' => json_encode([
            'ms_client_id' => 'ms-app', 'ms_client_secret' => $ms, 'ms_tenant' => '',
            'google_client_id' => 'g-app', 'google_client_secret' => $google, 'mail_allow_private' => '0',
        ])],
        2 => ['id' => 2, 'plugin_name' => 'ExmentPM', 'custom_options' => json_encode(['ms_client_secret' => 'not-ours'])],
    ];
    $migration = require __DIR__ . '/../ExmentGroupware/database/migrations/2026_11_29_000000_encrypt_gw_mail_client_secrets.php';
    GwFakeDb::$updates = 0;
    $migration->up();
    $once = GwFakeDb::$tables['plugins'][1]['custom_options'];
    $opts = json_decode($once, true);
    $check('migration: one row written', GwFakeDb::$updates === 1);
    $check('migration: no plain secret left', strpos($once, $ms) === false && strpos($once, $google) === false);
    $check('migration: both encrypted', S::isSealed($opts['ms_client_secret']) && S::isSealed($opts['google_client_secret']));
    $check('migration: read back', S::reveal($opts['ms_client_secret']) === $ms && S::reveal($opts['google_client_secret']) === $google);
    $check('migration: the other settings as they were', $opts['ms_client_id'] === 'ms-app' && $opts['ms_tenant'] === '' && $opts['mail_allow_private'] === '0');
    $check('migration: another plugin untouched', json_decode(GwFakeDb::$tables['plugins'][2]['custom_options'], true)['ms_client_secret'] === 'not-ours');
    GwFakeDb::$updates = 0;
    $migration->up();
    $check('migration twice: the same result', GwFakeDb::$tables['plugins'][1]['custom_options'] === $once);
    $check('migration twice: nothing written', GwFakeDb::$updates === 0);
    $first = S::sealOptions(['ms_client_secret' => $ms, 'ms_client_id' => 'ms-app']);
    $check('sealOptions twice: the same', S::sealOptions($first) === $first && S::reveal($first['ms_client_secret']) === $ms);
    $check('sealOptions: empty values stay empty', S::sealOptions(['ms_client_secret' => '', 'google_client_secret' => null]) === ['ms_client_secret' => null, 'google_client_secret' => null]);

    // ---- Exment's encpassword value (Laravel's encrypt() payload) is read, not encrypted again
    $enc = \Illuminate\Support\Facades\Crypt::encrypt('enc-pass-value');
    $check('encpassword: sealed', S::isSealed($enc) && S::seal($enc) === $enc);
    $check('encpassword: read', S::reveal($enc) === 'enc-pass-value');
    $check('a plain value is not taken for encrypted', !S::isSealed($ms) && !S::isSealed($google) && !S::isSealed('abc'));

    echo "\n", $bad === 0 ? "all passed ($ok)" : "$bad failed, $ok passed", "\n";
    exit($bad === 0 ? 0 : 1);
}
