<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * The menu "メール" goes into the group "グループウェア" of the left menu (the
 * feature map of exment.net: グループウェア ▸ 文書管理 / メール), when the core
 * has the groups (Exment 7.2.1, Menu::groupParentId()).
 *
 * Only a menu still at the top level, where 2026_11_22_000000_add_gw_mail_menu
 * put it, moves: a menu an administrator moved somewhere stays there. An older
 * core (no groups) changes nothing.
 */
return new class extends Migration
{
    protected const MENU_NAME = 'exment_groupware_mail';

    public function up(): void
    {
        $menuTable = config('admin.database.menu_table', 'admin_menu');
        if (!Schema::hasTable($menuTable) || !Schema::hasColumn($menuTable, 'menu_type')) {
            return;
        }
        if (!method_exists(\Exceedone\Exment\Model\Menu::class, 'groupParentId')) {
            return;
        }
        $mail = DB::table($menuTable)->where('menu_name', static::MENU_NAME)->first();
        if (!isset($mail) || intval($mail->parent_id) !== 0) {
            return;
        }
        $group = \Exceedone\Exment\Model\Menu::groupParentId('groupware');
        if (!isset($group)) {
            return;
        }
        // after what the group has (the documents of the core)
        $order = intval(DB::table($menuTable)->where('parent_id', $group)->max('order')) + 1;
        DB::table($menuTable)->where('id', $mail->id)->update(['parent_id' => $group, 'order' => $order, 'updated_at' => now()]);
        $this->clearCache();
    }

    public function down(): void
    {
        // the menu stays where it is
    }

    protected function clearCache(): void
    {
        try {
            if (class_exists(\Exceedone\Exment\Model\System::class)) {
                \Exceedone\Exment\Model\System::clearCache();
            }
        } catch (\Throwable $e) {
            // the menu shows on the next cache
        }
    }
};
