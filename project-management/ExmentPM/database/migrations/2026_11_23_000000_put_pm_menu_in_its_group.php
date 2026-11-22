<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * The menu "プロジェクト管理" goes into the group "プロジェクト" of the left menu
 * (the feature map of exment.net), when the core has the groups (Exment
 * 7.2.1, Menu::groupParentId()). The plugin's migrations run when it is
 * installed or updated, so a site gets it with the update of the plugin, also
 * when its data structure needs no update.
 *
 * Only the plugin's own entry (a plugin menu pointing at this plugin) still at
 * the top level moves: one an administrator moved stays where it is. A core
 * without the groups changes nothing. PortalSchema::menus() puts a new entry
 * in the group directly.
 */
return new class extends Migration
{
    public function up(): void
    {
        $menuTable = config('admin.database.menu_table', 'admin_menu');
        if (!Schema::hasTable($menuTable) || !Schema::hasColumn($menuTable, 'menu_type') || !Schema::hasTable('plugins')) {
            return;
        }
        if (!method_exists(\Exceedone\Exment\Model\Menu::class, 'groupParentId')) {
            return;
        }
        $pluginIds = DB::table('plugins')->whereIn('plugin_name', ['ExmentPM', 'ExmentPluginPM', 'PmPortal'])->pluck('id')->map(function ($id) {
            return strval($id);
        })->all();
        if (count($pluginIds) === 0) {
            return;
        }
        $menus = DB::table($menuTable)->where('menu_type', 'plugin')->where('parent_id', 0)->whereIn('menu_target', $pluginIds)->get();
        if ($menus->isEmpty()) {
            return;
        }
        $group = \Exceedone\Exment\Model\Menu::groupParentId('project');
        if (!isset($group)) {
            return;
        }
        $order = intval(DB::table($menuTable)->where('parent_id', $group)->max('order'));
        foreach ($menus as $menu) {
            DB::table($menuTable)->where('id', $menu->id)->update(['parent_id' => $group, 'order' => ++$order, 'updated_at' => now()]);
        }
        try {
            if (class_exists(\Exceedone\Exment\Model\System::class)) {
                \Exceedone\Exment\Model\System::clearCache();
            }
        } catch (\Throwable $e) {
            // the menu shows on the next cache
        }
    }

    public function down(): void
    {
        // the menu stays where it is
    }
};
