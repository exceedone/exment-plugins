<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 0.2.0: the daily look for issues deleted or moved in Backlog, and which
 * issue an attachment belongs to.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('pmbl_connections', 'last_full_check_at')) {
            Schema::table('pmbl_connections', function (Blueprint $table) {
                $table->dateTime('last_full_check_at')->nullable();
            });
        }
        if (!Schema::hasColumn('pmbl_links', 'parent_backlog_id')) {
            Schema::table('pmbl_links', function (Blueprint $table) {
                // attachment: the Backlog issue it hangs on
                $table->unsignedBigInteger('parent_backlog_id')->nullable()->index();
                // issue: why it is gone (deleted / moved:<project key>)
                $table->string('gone_reason', 128)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('pmbl_links', 'parent_backlog_id')) {
            Schema::table('pmbl_links', function (Blueprint $table) {
                $table->dropColumn(['parent_backlog_id', 'gone_reason']);
            });
        }
        if (Schema::hasColumn('pmbl_connections', 'last_full_check_at')) {
            Schema::table('pmbl_connections', function (Blueprint $table) {
                $table->dropColumn('last_full_check_at');
            });
        }
    }
};
