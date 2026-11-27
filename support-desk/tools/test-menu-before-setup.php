<?php
// ExmentSupportDesk 1.0.9: セットアップの前の左メニュー。ExmentSupportDesk を入れた Exment のフォルダで試す:
//   php /path/to/support-desk/tools/test-menu-before-setup.php
// セットアップ前のサイトは、トランザクションの中で作って最後に戻します（サイトは変わりません）。
require getcwd() . '/vendor/autoload.php';
$app = require getcwd() . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Exceedone\Exment\Enums\RoleType;
use Exceedone\Exment\Model\LoginUser;
use Exceedone\Exment\Model\Menu;
use Exceedone\Exment\Model\Plugin;
use Exceedone\Exment\Model\System;

$ok = 0; $bad = 0;
// Feature management is in the core from Exment 7.2.1; named as a string so an older core still runs this
$Features = 'Exceedone\Exment\Services\Features';
$check = function ($label, $cond, $extra = '') use (&$ok, &$bad) { echo ($cond ? 'OK  ' : 'NG  '), $label, $cond ? '' : "  $extra", "\n"; $cond ? $ok++ : $bad++; };

$plugin = Plugin::where('plugin_name', 'ExmentSupportDesk')->first();
if (!$plugin) { echo "ExmentSupportDesk is not installed here\n"; exit(1); }
$plugin->requirePlugin();
$I = 'App\Plugins\ExmentSupportDesk\Its';
$Schema = 'App\Plugins\ExmentSupportDesk\Schema';
$page = 'App\Plugins\ExmentSupportDesk\PluginPage';
$desk = function () { return DB::table('admin_menu')->where('menu_type', 'plugin')->where('menu_name', 'sd_desk')->get(); };
$admin = LoginUser::query()->whereIn('base_user_id', (array) System::system_admin_users())->first();
$user = LoginUser::query()->whereNotIn('base_user_id', (array) System::system_admin_users())->first();
$sees = function ($login) {
    System::clearCache();
    $login = LoginUser::find($login->id);              // its permissions read again
    Auth::guard('admin')->setUser($login);
    $row = collect((new Menu())->allNodes())->firstWhere('menu_name', 'sd_desk');
    return isset($row) && $login->visible($row);
};

// 1. a site that is set up: nothing changes
if ($I::isInstalled()) {
    $before = $desk()->map(function ($r) { return [$r->id, $r->parent_id, $r->order]; })->all();
    new $page($plugin);
    $check('set up site: the entry as it was', $desk()->map(function ($r) { return [$r->id, $r->parent_id, $r->order]; })->all() === $before);
}

// 2. a fresh site: the plugin installed, 表示する in Feature management, no setup yet
DB::beginTransaction();
try {
    $sd = $I::plugin();
    $options = (array) $sd->options;
    $options[$I::STATE_KEY] = [];
    $sd->options = $options;
    $sd->save();
    DB::table('admin_menu')->where('menu_type', 'plugin')->where('menu_target', strval($plugin->id))->delete();
    // the role groups get the plugin in the setup
    DB::table('role_group_permissions')->where('role_group_permission_type', RoleType::PLUGIN)->where('role_group_target_id', $plugin->id)->delete();
    if (class_exists($Features) && method_exists($Features, 'save')) {
        $on = [];
        foreach (array_keys($Features::definitions()) as $key) {
            if (strpos($key, 'exment_support_desk') === 0) { $on[$key] = ['enabled' => 1]; }
        }
        $Features::save($on);
        $Features::reset();
    }
    $check('fresh site: not set up', !$I::isInstalled());

    new $page($plugin);                                 // what every request does
    new $page($plugin);
    $rows = $desk();
    $check('one entry before the setup', $rows->count() === 1);
    $group = method_exists(Menu::class, 'groupParentId') ? Menu::groupParentId('it_support') : null;
    $check('in the group it_support (top level on an older core)', intval($rows->first()->parent_id ?? -1) === intval($group ?? 0));
    $check('the system administrator sees it', $sees($admin));
    if ($user) {
        $check('a user who is not an administrator does not', !$sees($user));
    }
    Auth::guard('admin')->setUser($admin);
    $id = $rows->first()->id ?? null;

    // the setup keeps the same entry
    $r = $Schema::install(false);
    $check('the setup', $r['ok'] && $I::isInstalled(), strval($r['message'] ?? ''));
    $rows = $desk();
    $check('after the setup: the same single entry', $rows->count() === 1 && $rows->first()->id === $id);
} finally {
    DB::rollBack();
    if (class_exists($Features)) { $Features::reset(); }
}

echo "\n$ok OK, $bad NG\n";
exit($bad ? 1 : 0);
