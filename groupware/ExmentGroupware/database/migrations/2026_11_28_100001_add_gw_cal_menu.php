<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The menu "Calendar" (ISS02947): a custom menu to the calendar's page
 * (plugins/exment_groupware/cal), in the group "Groupware" when Exment has the groups. On an
 * Exment with Feature management it goes while the calendar is not used or the user may not use
 * it (Features::hidesMenuUri). Made once; one removed by an administrator does not come back.
 */
return new class extends Migration
{
    protected const MENU_NAME = 'exment_groupware_calendar';
    protected const URI = 'plugins/exment_groupware/cal';

    public function up(): void
    {
        $menuTable = config('admin.database.menu_table', 'admin_menu');
        if (!Schema::hasTable($menuTable) || !Schema::hasColumn($menuTable, 'menu_type')) {
            return;
        }
        $exists = DB::table($menuTable)
            ->where(function ($query) {
                $query->where('menu_name', static::MENU_NAME)
                    ->orWhere('uri', static::URI)
                    ->orWhere('uri', '/' . static::URI);
            })
            ->exists();
        if ($exists) {
            return;
        }
        // in the group "Groupware" (Exment 7.2.1 and later), else at the top
        $parent = 0;
        if (method_exists(\Exceedone\Exment\Model\Menu::class, 'groupParentId')) {
            $parent = intval(\Exceedone\Exment\Model\Menu::groupParentId('groupware') ?? 0);
        }
        $order = intval(DB::table($menuTable)->where('parent_id', $parent)->max('order')) + 1;
        DB::table($menuTable)->insert([
            'parent_id' => $parent,
            'order' => $order,
            'title' => str_starts_with(strval(app()->getLocale()), 'ja') ? 'カレンダー' : 'Calendar',
            'icon' => 'fa-calendar',
            'uri' => static::URI,
            'menu_type' => 'custom',
            'menu_name' => static::MENU_NAME,
            'menu_target' => static::URI,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->clearCache();
    }

    public function down(): void
    {
        $menuTable = config('admin.database.menu_table', 'admin_menu');
        if (!Schema::hasTable($menuTable)) {
            return;
        }
        DB::table($menuTable)->where('menu_name', static::MENU_NAME)->delete();
        $this->clearCache();
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
