<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The menu "Mail" (0.4.2): installing the plugin made no menu, so the users
 * found the web mail only from the links of the file server.
 *
 * A custom menu to the page of the mail (plugins/exment_groupware/mail), not
 * a plugin menu: a plugin menu always opens the top of the plugin. On an
 * Exment with Feature management, the menu goes when the mail is not used or
 * the user may not use it (Features::hidesMenuUri), as its pages do; who may
 * see it is the permission of the plugin, as for its other pages.
 *
 * Made once, when the plugin is installed or updated to 0.4.2 (the plugin's
 * migrations run then). A site that has a menu to the mail already (made by
 * hand) gets no second one; a menu removed by an administrator does not come back.
 */
return new class extends Migration
{
    protected const MENU_NAME = 'exment_groupware_mail';
    protected const URI = 'plugins/exment_groupware/mail';

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
        $order = intval(DB::table($menuTable)->where('parent_id', 0)->max('order')) + 1;
        DB::table($menuTable)->insert([
            'parent_id' => 0,
            'order' => $order,
            'title' => str_starts_with(strval(app()->getLocale()), 'ja') ? 'メール' : 'Mail',
            'icon' => 'fa-envelope',
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
