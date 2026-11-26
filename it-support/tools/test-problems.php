<?php
// ExmentITSupport: problems (the tickets of one cause together). In the folder of an Exment with
// ExmentITSupport set up:
//   php /path/to/it-support/tools/test-problems.php
// The tables are brought up to date first (the setup's "テーブルを更新"); what the test makes is
// in a transaction rolled back at the end (the site is not changed).
require getcwd() . '/vendor/autoload.php';
$app = require getcwd() . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

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
$P = 'App\Plugins\ExmentITSupport\Problems';
$T = 'App\Plugins\ExmentITSupport\Tickets';
$admin = LoginUser::query()->whereIn('base_user_id', (array) System::system_admin_users())->first();
Auth::guard('admin')->setUser($admin);

if (!$I::isInstalled()) { echo "set up ExmentITSupport first\n"; exit(1); }
$r = $Schema::install(false);
$check('the tables are up to date', $r['ok'] && $I::table($I::T_PROBLEM) !== null, strval($r['message'] ?? ''));

DB::beginTransaction();
try {
    $before = $I::setting('problems_enabled', '0');
    $check('off at first (or as the site set it)', in_array($before, ['0', '1'], true));
    $I::saveSettings(['problems_enabled' => '0']);
    $check('off: not enabled', !$P::enabled());
    $I::saveSettings(['problems_enabled' => '1']);
    $check('on: enabled', $P::enabled());

    $a = $T::create(['subject' => 'VPN にはいれない（本社）', 'description' => 'VPN がつながらない', 'customer_visible' => '0']);
    $b = $T::create(['subject' => 'VPN 切れる', 'customer_visible' => '0']);
    $c = $T::create(['subject' => 'VPN だめ（解決ずみ）', 'customer_visible' => '0']);
    $c->setValue(['status' => 'closed']);
    $c->save();

    $problem = $P::createFrom($a);
    $no = strval(array_get($problem->value, 'problem_no'));
    $check('a problem from a ticket, numbered', preg_match('/^PRB-\d{5}$/', $no) === 1, $no);
    $check('its title and what happens from the ticket', array_get($problem->value, 'title') === 'VPN にはいれない（本社）' && array_get($problem->value, 'description') === 'VPN がつながらない');
    $check('the ticket is linked', strval(array_get($T::find($a->id)->value, 'problem')) === strval($problem->id));

    $found = $P::findByNo(ltrim(substr($no, 4), '0'));
    $check('found by its number without PRB-', $found !== null && intval($found->id) === intval($problem->id));
    $P::link($T::find($b->id), $problem);
    $P::link($T::find($c->id), $problem);
    $check('three tickets under it', $P::tickets($problem)->count() === 3);
    $rows = collect($P::listRows())->keyBy('id');
    $check('in the list: 3 tickets, 2 open', ($rows[$problem->id]['tickets'] ?? 0) === 3 && ($rows[$problem->id]['open'] ?? 0) === 2);

    $problem->setValue(['root_cause' => '証明書の期限切れ', 'workaround' => '有線でつなぐ', 'resolution' => '証明書を更新した']);
    $problem->save();
    $n = $P::resolveAll($P::find($problem->id));
    $check('the open tickets resolved together (the closed one left)', $n === 2, (string) $n);
    $check('they are resolved', array_get($T::find($a->id)->value, 'status') === 'resolved' && array_get($T::find($b->id)->value, 'status') === 'resolved');
    $check('the closed one stays closed', array_get($T::find($c->id)->value, 'status') === 'closed');
    $notes = $T::messages($T::find($a->id))->filter(function ($m) { return array_get($m->value, 'kind') === 'note'; });
    $check('a note with the solution on each', $notes->contains(function ($m) { return strpos(strval(array_get($m->value, 'body')), '証明書を更新した') !== false; }));
    $check('the problem is solved', array_get($P::find($problem->id)->value, 'status') === 'resolved' && !empty(array_get($P::find($problem->id)->value, 'resolved_at')));

    $k = $P::toKnowledge($P::find($problem->id));
    $check('knowledge drafted, not public', $k !== null && array_get($k->value, 'public') === '0');
    $check('with the cause, the workaround and the solution', $k !== null && strpos(strval(array_get($k->value, 'body')), '【原因】') !== false && strpos(strval(array_get($k->value, 'body')), '有線でつなぐ') !== false);
    $check('the problem keeps it', intval(array_get($P::find($problem->id)->value, 'knowledge_id')) === intval($k->id ?? 0));

    $P::unlink($T::find($b->id));
    $check('unlinked', empty(array_get($T::find($b->id)->value, 'problem')) && $P::tickets($P::find($problem->id))->count() === 2);
} finally {
    DB::rollBack();
    $I::forget();
}

echo "\n$ok OK, $bad NG\n";
exit($bad === 0 ? 0 : 1);
