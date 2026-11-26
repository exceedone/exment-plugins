<?php
// ExmentITSupport: the request catalog with its approval. In the folder of an Exment with
// ExmentITSupport set up:
//   php /path/to/it-support/tools/test-catalog.php
// The tables are brought up to date first; what the test makes is in a transaction rolled back
// at the end (no mail leaves: the mail driver is "array" here).
require getcwd() . '/vendor/autoload.php';
$app = require getcwd() . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['mail.default' => 'array']);

use Exceedone\Exment\Model\LoginUser;
use Exceedone\Exment\Model\Plugin;
use Exceedone\Exment\Model\System;

$ok = 0; $bad = 0;
$check = function ($label, $cond, $extra = '') use (&$ok, &$bad) { echo ($cond ? 'OK  ' : 'NG  '), $label, $cond ? '' : "  $extra", "\n"; $cond ? $ok++ : $bad++; };

$plugin = Plugin::where('plugin_name', 'ExmentITSupport')->first();
if (!$plugin) { echo "ExmentITSupport is not installed here\n"; exit(1); }
$plugin->requirePlugin();
$I = 'App\Plugins\ExmentITSupport\Its';
$Schema = 'App\Plugins\ExmentITSupport\Schema';
$C = 'App\Plugins\ExmentITSupport\Catalog';
$T = 'App\Plugins\ExmentITSupport\Tickets';
$admin = LoginUser::query()->whereIn('base_user_id', (array) System::system_admin_users())->first();
$other = LoginUser::query()->whereNotIn('base_user_id', (array) System::system_admin_users())->first();
Auth::guard('admin')->setUser($admin);

if (!$I::isInstalled()) { echo "set up ExmentITSupport first\n"; exit(1); }
$r = $Schema::install(false);
$check('the tables are up to date', $r['ok'] && $I::table($I::T_CATALOG) !== null, strval($r['message'] ?? ''));

DB::beginTransaction();
try {
    $I::saveSettings(['catalog_enabled' => '0']);
    $check('off: no items on the portal', $C::publicItems() === []);
    $I::saveSettings(['catalog_enabled' => '1']);
    $n = $C::installTemplates();
    $check('the templates go in once', $n >= 0 && $C::installTemplates() === 0, (string) $n);
    $items = collect($C::publicItems())->keyBy('name');
    $check('five ready-made items', $items->has('PC の貸し出し') && $items->has('パスワードの再設定') && $items->has('アクセスの申請（フォルダー・システム）'));

    // no approver yet: taken without an approval
    $pc = $C::item($items['PC の貸し出し']['id']);
    $values = $C::ticketValues($pc, ['f1' => '山田', 'f2' => '2026-12-01', 'f3' => '2026-12-05']);
    $check('no approver: no approval', $values['approval_state'] === 'none' && !isset($values['status']));
    $check('the subject and the text from the item', $values['subject'] === '【申請】PC の貸し出し' && strpos($values['description'], '■ 使う人: 山田') !== false);

    // with an approver
    $pc->setValue(['approver' => $admin->base_user_id]);
    $pc->save();
    $values = $C::ticketValues($C::item($pc->id), ['f1' => '山田', 'f2' => '2026-12-01', 'f3' => '2026-12-05'], '急ぎです');
    $check('with an approver: on hold, waiting', $values['approval_state'] === 'waiting' && $values['status'] === 'on_hold' && strpos($values['tags'], '承認待ち') !== false);
    $ticket = $T::create(array_merge($values, ['requester_email' => 'catalog-test@example.invalid', 'requester_name' => 'テスト']));
    $C::askApproval($ticket);
    $check('the ticket waits', array_get($T::find($ticket->id)->value, 'status') === 'on_hold');
    $check('the admin may decide', $C::mayDecide($T::find($ticket->id)));
    if ($other) {
        Auth::guard('admin')->setUser($other);
        System::clearRequestSession();
        $check('another user may not', !$C::mayDecide($T::find($ticket->id)));
        Auth::guard('admin')->setUser($admin);
        System::clearRequestSession();
    }

    $check('approved', $C::decide($T::find($ticket->id), true, 'OK です'));
    $t = $T::find($ticket->id);
    $check('open, approved, the tag gone', array_get($t->value, 'status') === 'open' && array_get($t->value, 'approval_state') === 'approved' && strpos(strval(array_get($t->value, 'tags')), '承認待ち') === false);
    $check('not twice', !$C::decide($T::find($ticket->id), false));

    $second = $T::create(array_merge($values, ['requester_email' => 'catalog-test@example.invalid', 'requester_name' => 'テスト']));
    $check('rejected', $C::decide($T::find($second->id), false, '在庫がありません'));
    $t = $T::find($second->id);
    $check('closed and rejected', array_get($t->value, 'status') === 'closed' && array_get($t->value, 'approval_state') === 'rejected');
    $check('the requester is told', $T::messages($t)->contains(function ($m) { return strpos(strval(array_get($m->value, 'body')), '却下のメール') !== false; }));

    $pc->setValue(['public' => '0']);
    $pc->save();
    $check('an item not public is not shown or used', $C::item($pc->id) === null && !collect($C::publicItems())->contains('id', intval($pc->id)));
} finally {
    DB::rollBack();
    $I::forget();
}

echo "\n$ok OK, $bad NG\n";
exit($bad === 0 ? 0 : 1);
