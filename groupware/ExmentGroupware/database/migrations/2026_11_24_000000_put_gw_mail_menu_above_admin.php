<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The menu "メール" on an Exment without the menu groups (before 7.2.1): 0.4.2 put it at the
 * end of the left menu, under the administrator settings. It goes just above them, with the
 * other menus of the site.
 *
 * Only the menu as the plugin made it moves (at the top level, after the settings, not changed
 * since it was made): a menu an administrator moved or renamed stays where it is. On an Exment
 * with the groups, 2026_11_23_000000_put_gw_mail_menu_in_its_group puts it in the group
 * "グループウェア" instead, and nothing is done here.
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
        if (method_exists(\Exceedone\Exment\Model\Menu::class, 'groupParentId')) {
            return;
        }
        $mail = DB::table($menuTable)->where('menu_name', static::MENU_NAME)->first();
        if (!isset($mail) || intval($mail->parent_id) !== 0 || !in_array($mail->title, ['メール', 'Mail'], true)) {
            return;
        }
        if (isset($mail->created_at, $mail->updated_at) && strval($mail->created_at) !== strval($mail->updated_at)) {
            return;
        }
        $admin = DB::table($menuTable)->where('parent_id', 0)->where('menu_type', 'parent_node')->where('menu_name', 'admin')->first();
        if (!isset($admin) || intval($mail->order) <= intval($admin->order)) {
            return;
        }
        // the settings and what comes after them go one down, the mail takes their place
        DB::table($menuTable)->where('parent_id', 0)->where('id', '<>', $mail->id)
            ->where('order', '>=', intval($admin->order))->increment('order');
        DB::table($menuTable)->where('id', $mail->id)->update(['order' => intval($admin->order)]);
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
