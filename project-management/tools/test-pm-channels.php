<?php

/**
 * ExmentPM: the messages of the chat channels (ISS02939) - the words, the webhook body and its
 * signature, Chatwork's text - and a post through a fake HTTP client, without Exment.
 *
 *   php project-management/tools/test-pm-channels.php
 */

namespace App\Plugins\ExmentPM {
    // the words of the ja lang file, as PortalDefinition::trans gives them
    class PortalDefinition
    {
        public static $lang;

        public static function trans($key, $args = [])
        {
            if (static::$lang === null) {
                static::$lang = require __DIR__ . '/../ExmentPM/resources/lang/ja/pmportal.php';
            }
            $v = static::$lang;
            foreach (explode('.', $key) as $k) {
                $v = is_array($v) && array_key_exists($k, $v) ? $v[$k] : $key;
            }
            foreach ($args as $n => $a) {
                $v = str_replace(':' . $n, strval($a), $v);
            }
            return $v;
        }
    }
}

namespace {
    use App\Plugins\ExmentPM\PortalChannels as C;

    if (!function_exists('array_get')) {
        function array_get($a, $k, $d = null)
        {
            return is_array($a) && array_key_exists($k, $a) ? $a[$k] : $d;
        }
    }
    // the secrets, as encrypt()/decrypt() of Laravel would keep them
    if (!function_exists('encrypt')) {
        function encrypt($v)
        {
            return base64_encode(strval($v));
        }
        function decrypt($v)
        {
            return base64_decode(strval($v));
        }
    }
    require __DIR__ . '/../ExmentPM/PortalChannels.php';

    $failed = 0;
    $check = function ($name, $cond, $extra = '') use (&$failed) {
        echo ($cond ? 'ok   ' : 'FAIL ') . $name . ($cond ? '' : '  ' . var_export($extra, true)) . PHP_EOL;
        if (!$cond) {
            $failed++;
        }
    };

    $base = [
        'project' => ['id' => 3, 'name' => '開発', 'key' => 'DEV', 'url' => 'https://pm.example.invalid/p/DEV'],
        'issue' => ['id' => 12, 'key' => 'DEV-12', 'subject' => 'ログインできない', 'url' => 'https://pm.example.invalid/p/DEV#issue-DEV-12'],
        'by' => '山田',
    ];

    // ---- the words
    $m = C::compose($base + ['events' => ['created' => []]]);
    $check('a new issue: the key and subject in the title', $m['title'] === '[DEV-12] ログインできない', $m['title']);
    $check('a new issue: who', $m['lines'] === ['課題が登録されました（山田）'], $m['lines']);
    $m = C::compose($base + ['events' => ['status' => ['from' => '未対応', 'to' => '処理中'], 'comment' => ['comment' => "直しています\n\n明日まで"]]]);
    $check('status and comment of one save: one message, two lines', count($m['lines']) === 2 && $m['lines'][0] === '状態：未対応 → 処理中（山田）', $m['lines']);
    $check('a comment on one line', $m['lines'][1] === '山田 さんのコメント： 直しています 明日まで', $m['lines'][1]);
    $long = C::compose($base + ['events' => ['comment' => ['comment' => str_repeat('あ', 500)]]]);
    $check('a long comment is cut', mb_strwidth($long['lines'][0]) < 400 && substr($long['lines'][0], -3) === '…');
    $nobody = C::compose(['by' => ''] + $base + ['events' => ['created' => []]]);
    $check('nobody known: someone', strpos($nobody['lines'][0], 'だれか') !== false, $nobody['lines']);
    $sum = C::summary($base['project'], 14);
    $check('a summary of a bulk change', $sum['title'] === '[DEV] いくつもの課題が変わりました' && strpos($sum['lines'][0], '14') !== false, $sum);

    // ---- harmless text
    $check('no template of Exment', C::plain('${user.name}') === '$ {user.name}');
    $check('no tag of Chatwork', C::plain('[To:1] [info]x[/info]', 'chatwork') === '［To:1］ ［info］x［/info］');
    $cw = C::chatworkBody(C::compose($base + ['events' => ['comment' => ['comment' => '[toall] 見て']]]));
    $check('Chatwork: one info block, the comment not a tag', strpos($cw, '[info][title]［DEV-12］') === 0 && strpos($cw, '［toall］') !== false && substr_count($cw, '[info]') === 1, $cw);

    // ---- the webhook body and its signature
    $m = C::compose($base + ['events' => ['status' => ['from' => 'A', 'to' => 'B']]]);
    [$body, $headers] = C::webhookBody($m, 'abcdefghijklmnop', '2026-11-27T10:00:00+09:00');
    $j = json_decode($body, true);
    $check('webhook: the event and the issue', $j['event'] === 'issue.status' && $j['issue']['key'] === 'DEV-12' && $j['status']['to'] === 'B' && $j['comment'] === null, $j);
    $check('webhook: the signature of the body', $headers['X-ExmentPM-Signature'] === 'sha256=' . hash_hmac('sha256', $body, 'abcdefghijklmnop'));
    [, $plainHeaders] = C::webhookBody($m, null);
    $check('webhook: no secret, no signature', !isset($plainHeaders['X-ExmentPM-Signature']) && $plainHeaders['X-ExmentPM-Event'] === 'issue.status');
    [$sb] = C::webhookBody($sum, null);
    $check('webhook: a summary', json_decode($sb, true)['event'] === 'issues.bulk' && json_decode($sb, true)['count'] === 14);

    // ---- a post through a fake client (Guzzle's mock, when the vendor is there)
    $autoload = __DIR__ . '/../../vendor/autoload.php';
    if (is_file($autoload) && (require_once $autoload) && class_exists('\GuzzleHttp\Handler\MockHandler')) {
        $history = [];
        $stack = \GuzzleHttp\HandlerStack::create(new \GuzzleHttp\Handler\MockHandler([new \GuzzleHttp\Psr7\Response(200, [], '{"message_id":"1"}')]));
        $stack->push(\GuzzleHttp\Middleware::history($history));
        C::$http = new \GuzzleHttp\Client(['handler' => $stack]);
        $ok = C::post(['id' => 1, 'type' => 'chatwork', 'secret' => 'app:' . encrypt('0123456789abcdef0123'), 'target' => '123456', 'sign_secret' => ''], $m);
        $req = $history[0]['request'] ?? null;
        $check('Chatwork: posted to the room with the token', $ok && isset($req) && strval($req->getUri()) === 'https://api.chatwork.com/v2/rooms/123456/messages' && $req->getHeaderLine('X-ChatWorkToken') === '0123456789abcdef0123');
    } else {
        echo 'skip  a post through a fake client (no vendor/ here)' . PHP_EOL;
    }

    echo PHP_EOL . ($failed === 0 ? 'all ok' : $failed . ' failed') . PHP_EOL;
    exit($failed === 0 ? 0 : 1);
}
