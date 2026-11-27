<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The menu 「チャット」 (ISS02945): a custom menu to the chat's page, like the mail's. The
 * page itself answers as Feature management says (off at first).
 */
return new class extends Migration
{
    protected const MENU_NAME = 'exment_groupware_chat';
    protected const URI = 'plugins/exment_groupware/chat';

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
            'title' => str_starts_with(strval(app()->getLocale()), 'ja') ? 'チャット' : 'Chat',
            'icon' => 'fa-comments',
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
