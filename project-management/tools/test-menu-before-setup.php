<?php
// ExmentPM 1.7.15: 初期セットアップの前の左メニュー。ExmentPM を入れた Exment のフォルダで試す:
//   php /path/to/project-management/tools/test-menu-before-setup.php
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

$plugin = Plugin::where('plugin_name', 'ExmentPM')->first();
if (!$plugin) { echo "ExmentPM is not installed here\n"; exit(1); }
$plugin->requirePlugin();
$S = 'App\Plugins\ExmentPM\PortalSetup';
$page = 'App\Plugins\ExmentPM\PluginPage';
$name = $S::PRESETUP_MENU;
$entries = function () use ($plugin) { return DB::table('admin_menu')->where('menu_type', 'plugin')->where('menu_target', strval($plugin->id))->get(); };
$admin = LoginUser::query()->whereIn('base_user_id', (array) System::system_admin_users())->first();
$user = LoginUser::query()->whereNotIn('base_user_id', (array) System::system_admin_users())->first();
$sees = function ($login, string $menuName) {
    System::clearCache();
    $login = LoginUser::find($login->id);              // its permissions read again
    Auth::guard('admin')->setUser($login);
    $row = collect((new Menu())->allNodes())->firstWhere('menu_name', $menuName);
    return isset($row) && $login->visible($row);
};

// 1. a site that is set up: nothing changes
if ($S::isInstalled()) {
    $before = $entries()->pluck('id')->all();
    new $page($plugin);
    $check('set up site: the same entries', $entries()->pluck('id')->all() === $before);
    $check('set up site: no entry from before the setup', DB::table('admin_menu')->where('menu_name', $name)->count() === 0);
}

// 2. a fresh site: the plugin installed, 表示する in Feature management, no setup yet
DB::beginTransaction();
try {
    $options = (array) $plugin->options;
    $options[$S::STATE_KEY] = [];
    $plugin->options = $options;
    $plugin->save();
    (new ReflectionProperty($S, 'state_cache'))->setValue(null, null);
    DB::table('admin_menu')->whereIn('id', $entries()->pluck('id'))->delete();
    // the role groups get the plugin in the setup
    DB::table('role_group_permissions')->where('role_group_permission_type', RoleType::PLUGIN)->where('role_group_target_id', $plugin->id)->delete();
    if (class_exists($Features) && method_exists($Features, 'save')) {
        $on = [];
        foreach (array_keys($Features::definitions()) as $key) {
            if (strpos($key, 'exment_pm') === 0) { $on[$key] = ['enabled' => 1]; }
        }
        $Features::save($on);
        $Features::reset();
    }
    $check('fresh site: not set up', !$S::isInstalled());

    new $page($plugin);                                 // what every request does
    new $page($plugin);
    $rows = $entries();
    $check('one entry before the setup', $rows->count() === 1 && $rows->first()->menu_name === $name, json_encode($rows->pluck('menu_name')));
    $group = method_exists(Menu::class, 'groupParentId') ? Menu::groupParentId('project') : null;
    $check('in the group "プロジェクト" (top level on an older core)', intval($rows->first()->parent_id ?? -1) === intval($group ?? 0));
    $check('the system administrator sees it', $sees($admin, $name));
    if ($user) {
        $check('a user who is not an administrator does not', !$sees($user, $name));
    }
    Auth::guard('admin')->setUser($admin);

    // the setup ends: its own entry is there (the template's), the one from before goes
    DB::table('admin_menu')->insert(['parent_id' => $group ?? 0, 'order' => 1, 'title' => 'プロジェクト管理', 'icon' => 'fa-tasks', 'uri' => '', 'menu_type' => 'plugin', 'menu_target' => strval($plugin->id), 'menu_name' => 'pm_portal_page', 'created_at' => now(), 'updated_at' => now()]);
    (new ReflectionMethod($S, 'markInstalled'))->invoke(null, '');
    $rows = $entries();
    $check('after the setup: one entry, the setup\'s', $rows->count() === 1 && $rows->first()->menu_name === 'pm_portal_page', json_encode($rows->pluck('menu_name')));
    new $page($plugin);
    $check('after the setup: none comes back', $entries()->count() === 1);
} finally {
    DB::rollBack();
    if (class_exists($Features)) { $Features::reset(); }
}

echo "\n$ok OK, $bad NG\n";
exit($bad ? 1 : 0);
