<?php

/**
 * ExmentPM: the morning digest (ISS02939) - a person's own setting, a mention in a comment, and
 * the words of a digest - without Exment.
 *
 *   php project-management/tools/test-pm-digest.php
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
    use App\Plugins\ExmentPM\PortalDigest as G;

    if (!function_exists('array_get')) {
        function array_get($a, $k, $d = null)
        {
            if (!is_array($a)) {
                return $d;
            }
            if (array_key_exists($k, $a)) {
                return $a[$k];
            }
            foreach (explode('.', strval($k)) as $p) {
                if (!is_array($a) || !array_key_exists($p, $a)) {
                    return $d;
                }
                $a = $a[$p];
            }
            return $a;
        }
    }
    require __DIR__ . '/../ExmentPM/PortalNotifyPrefs.php';
    require __DIR__ . '/../ExmentPM/PortalDigest.php';

    $failed = 0;
    $check = function ($name, $cond, $extra = '') use (&$failed) {
        echo ($cond ? 'ok   ' : 'FAIL ') . $name . ($cond ? '' : '  ' . var_export($extra, true)) . PHP_EOL;
        if (!$cond) {
            $failed++;
        }
    };

    // ---- a person's own setting
    $check('off unless asked', G::personal(null) === ['on' => false, 'time' => '08:00'] && G::personal(['digest' => null])['on'] === false);
    $check('on, at the time chosen', G::personal(['digest' => ['on' => true, 'time' => '7:15']]) === ['on' => true, 'time' => '07:15']);

    // ---- a mention
    $check('@code at the start, in a sentence, before punctuation', G::mentions('@yamada 見てください', 'yamada') && G::mentions('お願いします @yamada。', 'yamada') && G::mentions('cc:@yamada,@sato', 'sato'));
    $check('not a longer code, not a mail address', !G::mentions('@yamada2 です', 'yamada') && !G::mentions('mail yamada@yamada.invalid', 'yamada') && !G::mentions('@@yamada', 'yamada'));
    $check('a code with a dot or a dash', G::mentions('@t.yamada-1 へ', 't.yamada-1') && !G::mentions('@t.yamada-12', 't.yamada-1'));

    // ---- the words
    $m = G::compose([
        'due' => [
            ['id' => 2, 'key' => 'DEV-2', 'subject' => '請求書', 'due' => '2026-11-28', 'over' => false],
            ['id' => 1, 'key' => 'DEV-1', 'subject' => '見積', 'due' => '2026-11-20', 'over' => true],
        ],
        'assigned' => [['id' => 3, 'key' => 'OPS-3', 'subject' => 'サーバー']],
        'mention' => [],
        'status' => [['id' => 4, 'key' => 'DEV-4', 'subject' => 'ログイン', 'to' => '完了']],
    ], '2026-11-28', 'AI のおすすめ：まず DEV-1 見積 から');
    $check('the title with the day', $m['title'] === '今日のまとめ（11/28）', $m['title']);
    $check('the AI line first', $m['lines'][0] === 'AI のおすすめ：まず DEV-1 見積 から');
    $check('the oldest due first, overdue said', $m['lines'][1] === '■ 今日が期限・期限切れ（2）' && $m['lines'][2] === '・DEV-1 見積（期限 11/20 を過ぎています）' && $m['lines'][3] === '・DEV-2 請求書（今日まで）', $m['lines']);
    $check('an empty section is left out', !in_array('■ あなたあてのメンション（0）', $m['lines'], true));
    $check('a status change with where it went', in_array('・DEV-4 ログイン（→ 完了）', $m['lines'], true), $m['lines']);
    $many = [];
    for ($i = 1; $i <= 13; $i++) {
        $many[] = ['id' => $i, 'key' => 'K-' . $i, 'subject' => 's'];
    }
    $mm = G::compose(['assigned' => $many], '2026-11-28');
    $check('ten a section, the rest counted', count($mm['lines']) === 12 && end($mm['lines']) === '　ほか 3 件', $mm['lines']);
    $check('no AI: no AI line', G::compose(['assigned' => $many], '2026-11-28', null)['lines'][0] === '■ 新しく担当になった課題（13）');

    echo PHP_EOL . ($failed === 0 ? 'all ok' : $failed . ' failed') . PHP_EOL;
    exit($failed === 0 ? 0 : 1);
}
