<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The menu "Mail" with the menus of every day (0.4.3): 0.4.2 put it at the
 * end of the top level, below "Admin Setting".
 *
 * It goes right below "File server" (the system menu of the file server, at
 * the top level), or else right below "HOME", or else right above "Admin
 * Setting". The top-level menus below that place go down by one, in the same
 * order (a site whose menus were reordered on the menu page has no gaps
 * between the orders).
 *
 * Moved only when it is still where 0.4.2 put it: at the top level, below
 * "Admin Setting". A menu an administrator has moved (into a folder, or above
 * "Admin Setting") stays where it is. This runs once, on installing the
 * plugin (after the migration of 0.4.2 has made the menu) or on updating it
 * to 0.4.3.
 */
return new class extends Migration
{
    protected const MENU_NAME = 'exment_groupware_mail';

    public function up(): void
    {
        $menuTable = config('admin.database.menu_table', 'admin_menu');
        if (!Schema::hasTable($menuTable) || !Schema::hasColumn($menuTable, 'menu_name')) {
            return;
        }
        DB::transaction(function () use ($menuTable) {
            $mail = DB::table($menuTable)->where('menu_name', static::MENU_NAME)->first();
            if (!isset($mail) || intval($mail->parent_id) !== 0) {
                return;
            }
            $top = DB::table($menuTable)->where('parent_id', 0);
            $admin = (clone $top)->where('menu_type', 'parent_node')->where('menu_name', 'admin')->first();
            // moved by an administrator: above "Admin Setting" already (or no "Admin Setting" to be below)
            if (!isset($admin) || intval($mail->order) <= intval($admin->order)) {
                return;
            }

            $anchor = (clone $top)->where('menu_type', 'system')->where('menu_name', 'file_server')->first()
                ?? (clone $top)->where('menu_type', 'system')->where('menu_name', 'home')->first();
            if (isset($anchor) && intval($anchor->order) < intval($admin->order)) {
                $order = intval($anchor->order) + 1;
            } else {
                $order = intval($admin->order);
            }

            // the menus from that place go down by one ("Admin Setting" too, when it is reached)
            DB::table($menuTable)
                ->where('parent_id', 0)
                ->where('id', '<>', $mail->id)
                ->where('order', '>=', $order)
                ->increment('order');
            DB::table($menuTable)->where('id', $mail->id)->update([
                'order' => $order,
                'updated_at' => now(),
            ]);
        });
        $this->clearCache();
    }

    public function down(): void
    {
        // the order is not put back (an administrator may have reordered the menus since)
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
