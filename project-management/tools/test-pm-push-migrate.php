<?php
// ExmentPM: the upgrade that encrypts the Web Push subscriptions kept before (database/migrations/
// 2026_12_01_000000_encrypt_pm_portal_push_subs.php). Plain PHP: the table is in memory, the
// application key a key of this test; run twice, the second run changes nothing.
//   php project-management/tools/test-pm-push-migrate.php

namespace Illuminate\Database\Migrations {
    class Migration
    {
    }
}

namespace {
    $GLOBALS['__test_app_key'] = random_bytes(32);
    function encrypt($value)
    {
        $iv = random_bytes(16);

        return base64_encode($iv . openssl_encrypt(serialize($value), 'aes-256-cbc', $GLOBALS['__test_app_key'], OPENSSL_RAW_DATA, $iv));
    }
    function decrypt_test($payload)
    {
        $raw = base64_decode(substr($payload, 4));

        return unserialize(openssl_decrypt(substr($raw, 16), 'aes-256-cbc', $GLOBALS['__test_app_key'], OPENSSL_RAW_DATA, substr($raw, 0, 16)));
    }

    /** The table: rows by id, and its columns. */
    $GLOBALS['__rows'] = [];
    $GLOBALS['__cols'] = ['id', 'user_id', 'endpoint', 'endpoint_hash', 'p256dh', 'auth'];

    class Schema
    {
        public static function hasTable($t)
        {
            return $t === 'pm_portal_push_subs';
        }

        public static function hasColumn($t, $c)
        {
            return in_array($c, $GLOBALS['__cols'], true);
        }

        public static function table($t, $f)
        {
            $f(new class () {
                public function text($c)
                {
                    $GLOBALS['__cols'][] = $c;
                    foreach ($GLOBALS['__rows'] as &$r) {
                        $r[$c] = null;
                    }

                    return $this;
                }

                public function nullable()
                {
                    return $this;
                }
            });
        }
    }

    class DB
    {
        public static function table($t)
        {
            return new class () {
                private $nulls = [];
                private $id = null;

                public function whereNull($c)
                {
                    $this->nulls[] = $c;

                    return $this;
                }

                public function orderBy($c)
                {
                    return $this;
                }

                public function where($c, $v)
                {
                    $this->id = $v;

                    return $this;
                }

                public function get()
                {
                    $out = [];
                    foreach ($GLOBALS['__rows'] as $r) {
                        foreach ($this->nulls as $c) {
                            if ($r[$c] !== null) {
                                continue 2;
                            }
                        }
                        $out[] = (object) $r;
                    }

                    return $out;
                }

                public function update($values)
                {
                    $GLOBALS['__rows'][$this->id] = array_merge($GLOBALS['__rows'][$this->id], $values);

                    return 1;
                }
            };
        }
    }

    $ok = 0;
    $bad = 0;
    $check = function ($label, $cond, $extra = '') use (&$ok, &$bad) {
        echo($cond ? 'OK  ' : 'NG  '), $label, $cond ? '' : '  ' . var_export($extra, true), "\n";
        $cond ? $ok++ : $bad++;
    };

    $e1 = 'https://fcm.googleapis.com/fcm/send/test-device-1';
    $e2 = 'https://web.push.apple.com/test-device-2';
    $GLOBALS['__rows'] = [
        1 => ['id' => 1, 'user_id' => 7, 'endpoint' => $e1, 'endpoint_hash' => hash('sha256', $e1), 'p256dh' => 'BPUBLICKEY1', 'auth' => 'AUTH1'],
        2 => ['id' => 2, 'user_id' => 8, 'endpoint' => $e2, 'endpoint_hash' => hash('sha256', $e2), 'p256dh' => 'BPUBLICKEY2', 'auth' => 'AUTH2'],
    ];
    $m = require __DIR__ . '/../ExmentPM/database/migrations/2026_12_01_000000_encrypt_pm_portal_push_subs.php';
    $m->up();
    $r1 = $GLOBALS['__rows'][1];
    $check('the encrypted columns are added', in_array('p256dh_enc', $GLOBALS['__cols'], true) && in_array('auth_enc', $GLOBALS['__cols'], true));
    $check('the endpoint is encrypted (the application key), no plain address left', strpos($r1['endpoint'], 'app:') === 0 && strpos(json_encode($GLOBALS['__rows']), 'googleapis') === false && strpos(json_encode($GLOBALS['__rows']), 'apple.com') === false);
    $check('p256dh and auth move to the encrypted columns, the plain ones emptied', strpos($r1['p256dh_enc'], 'app:') === 0 && strpos($r1['auth_enc'], 'app:') === 0
        && $r1['p256dh'] === '' && $r1['auth'] === '' && strpos(json_encode($GLOBALS['__rows']), 'PUBLICKEY') === false && strpos(json_encode($GLOBALS['__rows']), 'AUTH1') === false);
    $check('they decrypt to the old values', decrypt_test($r1['endpoint']) === $e1 && decrypt_test($r1['p256dh_enc']) === 'BPUBLICKEY1' && decrypt_test($r1['auth_enc']) === 'AUTH1'
        && decrypt_test($GLOBALS['__rows'][2]['endpoint']) === $e2);
    $check('endpoint_hash stays as it was (the lookups)', $r1['endpoint_hash'] === hash('sha256', $e1) && $GLOBALS['__rows'][2]['endpoint_hash'] === hash('sha256', $e2));

    $before = $GLOBALS['__rows'];
    $cols = $GLOBALS['__cols'];
    $m->up();
    $check('run again: nothing changes (no double encryption, no column added twice)', $GLOBALS['__rows'] === $before && $GLOBALS['__cols'] === $cols);

    // a row of half an earlier run (endpoint already encrypted, keys not yet): the endpoint is not encrypted twice
    $GLOBALS['__rows'][3] = ['id' => 3, 'user_id' => 9, 'endpoint' => $r1['endpoint'], 'endpoint_hash' => 'h3', 'p256dh' => 'BP3', 'auth' => 'A3', 'p256dh_enc' => null, 'auth_enc' => null];
    $m->up();
    $check('an endpoint already encrypted is not encrypted again', $GLOBALS['__rows'][3]['endpoint'] === $r1['endpoint'] && decrypt_test($GLOBALS['__rows'][3]['p256dh_enc']) === 'BP3');
    $m->down();
    $check('down leaves the values encrypted', $GLOBALS['__rows'][1]['endpoint'] === $r1['endpoint']);

    echo "\n", $bad === 0 ? "all passed ($ok)" : "$bad failed, $ok passed", "\n";
    exit($bad === 0 ? 0 : 1);
}
