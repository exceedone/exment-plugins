<?php
// ExmentPM: the notices of an issue by mail and Teams (ISS02863). The mail and the Teams card have
// the same layout; a user without a choice gets the default of the site.
//
// Run inside a test Exment that has ExmentPM set up (docker-exment):
//   cd /path/to/exment && php /path/to/project-management/tools/test-teams-notice.php
require getcwd() . '/vendor/autoload.php';
$app = require getcwd() . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Plugins\ExmentPM\PortalTeams;

$fail = 0;
function ok($cond, $label, $detail = '')
{
    global $fail;
    echo ($cond ? 'ok   ' : 'FAIL ') . $label . ($cond ? '' : '  -> ' . $detail) . "\n";
    if (!$cond) {
        $fail++;
    }
}
$j = function ($v) {
    return json_encode($v, JSON_UNESCAPED_UNICODE);
};

$lines = ['状態を「対応中」に変えました', "コメント：\n確認しました"];
$body = PortalTeams::body('ログインできない', $lines);
ok($body === "**ログインできない**\n\n1. 状態を「対応中」に変えました\n2. コメント： 確認しました", 'Teams: the subject in bold, the lines as a numbered list', $j($body));
ok(PortalTeams::body('件名', ['ひとこと']) === "**件名**\n\nひとこと", 'Teams: one line is a paragraph');

$html = PortalTeams::mailHtml('課題が更新されました（PJ-12）', 'ログインできない <b>', $lines, 'https://x.example.com/p#issue-PJ-12');
ok(strpos($html, '<p><strong>課題が更新されました（PJ-12）</strong></p>') === 0, 'mail: the title in bold first', $html);
ok(strpos($html, '<a href="https://x.example.com/p#issue-PJ-12">') !== false, 'mail: the link');
ok(strpos($html, '<ol><li>状態を「対応中」に変えました</li><li>コメント： 確認しました</li></ol>') !== false, 'mail: the numbered list', $html);
ok(strpos($html, '&lt;b&gt;') !== false && strpos($html, '<b>') === false, 'mail: the text is escaped');
$text = PortalTeams::mailText('課題が更新されました（PJ-12）', 'ログインできない', $lines, 'https://x.example.com/p');
ok($text === "課題が更新されました（PJ-12）\nhttps://x.example.com/p\n\nログインできない\n\n1. 状態を「対応中」に変えました\n2. コメント： 確認しました", 'mail: the text form', $j($text));
ok(strpos(PortalTeams::body('${value:x}', []), '${') === false, 'a ${...} in a subject is not read by Exment');

ok(in_array(PortalTeams::siteDefault(), PortalTeams::CHOICES, true), 'the default of the site is one of the choices');
$user = getModelName('user')::withoutGlobalScopes()->first();
ok(isset($user) && PortalTeams::choiceOf($user) === PortalTeams::siteDefault(), 'a user without a choice: the default of the site');

echo $fail === 0 ? "all ok\n" : "$fail failed\n";
exit($fail === 0 ? 0 : 1);
