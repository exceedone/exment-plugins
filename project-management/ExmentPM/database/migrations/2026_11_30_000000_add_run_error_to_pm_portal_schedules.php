<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Why a recurring schedule makes no issues (PortalRunOwner::NO_OWNER, NO_RIGHT) and since when:
     * shown on the schedule screen, and logged once.
     */
    public function up(): void
    {
        if (!\Schema::hasTable('pm_portal_schedules')) {
            return;
        }
        if (!\Schema::hasColumn('pm_portal_schedules', 'run_error')) {
            \Schema::table('pm_portal_schedules', function (Blueprint $table) {
                $table->string('run_error', 32)->nullable();
                $table->dateTime('run_error_at')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (\Schema::hasTable('pm_portal_schedules') && \Schema::hasColumn('pm_portal_schedules', 'run_error')) {
            \Schema::table('pm_portal_schedules', function (Blueprint $table) {
                $table->dropColumn(['run_error', 'run_error_at']);
            });
        }
    }
};
