<?php
// ExmentITSupport: what is told to a chat, and to which (ChatNotices, ISS02941). Plain PHP:
//   php it-support/tools/test-chat-notices.php
require __DIR__ . '/../ExmentITSupport/ChatNotices.php';

use App\Plugins\ExmentITSupport\ChatNotices as C;

$ok = 0;
$bad = 0;
$check = function (string $label, bool $cond, $extra = '') use (&$ok, &$bad) {
    echo ($cond ? 'OK  ' : 'NG  '), $label, $cond ? '' : '  ' . var_export($extra, true), "\n";
    $cond ? $ok++ : $bad++;
};

// which events of which boxes
$check('an event on the list, all boxes', C::wants('new,customer', '', 'new', '5'));
$check('an event not on the list', !C::wants('new', '', 'stage', '5'));
$check('a box on the list', C::wants('new', '3,5', 'new', '5'));
$check('a box not on the list', !C::wants('new', '3,5', 'new', '4'));
$check('no box: "0" on the list', C::wants('new', '0', 'new', '') && !C::wants('new', '3', 'new', ''));
$check('nothing chosen: nothing told', !C::wants('', '', 'new', '1'));

$t = ['no' => 'SUP-00012', 'subject' => 'お見積のお願い', 'box' => '見積のご依頼', 'url' => 'https://exment.invalid/admin/plugins/exment_it_support/ticket/12'];
$m = C::compose('new', $t, ['from' => '山田']);
$check('new: title with the box and the ticket', $m['title'] === '新しいチケット｜【見積のご依頼】SUP-00012 お見積のお願い', $m['title']);
$check('new: who, then the link', $m['lines'] === ['依頼した人: 山田', $t['url']], $m['lines']);
$m = C::compose('stage', $t, ['from' => '見積を作る', 'to' => '送る', 'progress' => 50]);
$check('stage: from → to (progress)', strpos($m['title'], '段階: 見積を作る → 送る（50%）') === 0, $m['title']);
$m = C::compose('customer', $t, ['excerpt' => str_repeat('あ', 250) . "\n\n"]);
$check('customer: the reply cut at 200', mb_strlen($m['lines'][0]) === 201 && mb_substr($m['lines'][0], -1) === '…');
$check('no box: no 【】', strpos(C::compose('new', ['box' => ''] + $t)['title'], '【') === false);

// Slack: its marks are text
$s = C::slackText(C::compose('new', ['subject' => '<!channel> & <https://evil.invalid|x>'] + $t, []));
$check('Slack: <, >, & escaped in the subject', strpos($s, '&lt;!channel&gt; &amp; &lt;https://evil.invalid|x&gt;') !== false && strpos($s, '<!channel>') === false, $s);
$check('Slack: the link at the end', substr($s, -strlen('|開く>')) === '|開く>' && strpos($s, '<' . $t['url'] . '|開く>') !== false, $s);

// the bodies per target
$m = C::compose('new', $t, []);
$check('Teams: text', array_keys(C::payload('teams', 'new', $m, $t)) === ['text']);
$check('Slack: text', array_keys(C::payload('slack', 'new', $m, $t)) === ['text']);
$w = C::payload('webhook', 'new', $m, $t);
$check('Webhook: event and the fields', $w['event'] === 'ticket_new' && $w['ticket_no'] === 'SUP-00012' && $w['box'] === '見積のご依頼' && $w['url'] === $t['url']);

echo "\n", $bad === 0 ? "all passed ($ok)" : "$bad failed, $ok passed", "\n";
exit($bad === 0 ? 0 : 1);
