<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The connection moved to the data sync of Exment 7.3 (SyncBridge, ISS00113): the mapping of the
 * data sync that runs it now. The batch of the plugin leaves such a connection alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('pmbl_connections', 'sync_mapping_id')) {
            Schema::table('pmbl_connections', function (Blueprint $table) {
                $table->unsignedInteger('sync_mapping_id')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('pmbl_connections', 'sync_mapping_id')) {
            Schema::table('pmbl_connections', function (Blueprint $table) {
                $table->dropColumn('sync_mapping_id');
            });
        }
    }
};
