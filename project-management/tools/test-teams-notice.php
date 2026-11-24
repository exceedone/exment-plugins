<?php
// ExmentPM: the issue notices in Teams (ISS02863). The text of the card, and nothing sent while
// the plugin setting is off.
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

$body = PortalTeams::body('ログインできない', ['状態を「対応中」に変えました', "コメント：\n確認しました"]);
ok($body === "**ログインできない**\n\n1. 状態を「対応中」に変えました\n2. コメント： 確認しました", 'the subject in bold, the lines as a numbered list', json_encode($body, JSON_UNESCAPED_UNICODE));
$one = PortalTeams::body('件名', ['ひとこと']);
ok($one === "**件名**\n\nひとこと", 'one line is a paragraph', json_encode($one, JSON_UNESCAPED_UNICODE));
ok(strpos(PortalTeams::body('${value:x}', []), '${') === false, 'a ${...} in a subject is not read by Exment');

// the plugin setting off (the default): nothing is sent
$plugin = \App\Plugins\ExmentPM\PortalDefinition::plugin();
$was = isset($plugin) ? $plugin->getCustomOption('notify_teams') : null;
if (isset($plugin)) {
    $plugin->setCustomOption('notify_teams', '0');
}
ok(PortalTeams::available() === false, 'off: not available');
if (isset($plugin) && method_exists('Exceedone\Exment\Services\NotifyService', 'notifyTeamsPersonal')) {
    $plugin->setCustomOption('notify_teams', '1');
    ok(PortalTeams::available() === true, 'on, with an Exment that has it: available');
    $plugin->setCustomOption('notify_teams', $was);
}
echo $fail === 0 ? "all ok\n" : "$fail failed\n";
exit($fail === 0 ? 0 : 1);
