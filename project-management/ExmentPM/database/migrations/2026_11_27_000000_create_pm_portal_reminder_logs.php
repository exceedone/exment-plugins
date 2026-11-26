<?php

use Illuminate\Database\Migrations\Migration;
use Exceedone\Exment\Database\ExtendedBlueprint;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The reminders that went out (ISS00432): one row per issue, person and slot
     * (before:3:2026-11-30, today:2026-11-30, over:2026-12-02), so none goes twice.
     */
    public function up(): void
    {
        $schema = DB::connection()->getSchemaBuilder();

        $schema->blueprintResolver(function ($connection, $table, $callback) {
            return new ExtendedBlueprint($connection, $table, $callback);
        });

        if (!\Schema::hasTable('pm_portal_reminder_logs')) {
            $schema->create('pm_portal_reminder_logs', function (ExtendedBlueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('issue_id');
                $table->unsignedBigInteger('user_id');
                $table->string('slot', 40);
                $table->timestamps();

                $table->unique(['issue_id', 'slot', 'user_id'], 'pm_portal_reminder_logs_unique');
                $table->index('created_at', 'pm_portal_reminder_logs_created_idx');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        \Schema::dropIfExists('pm_portal_reminder_logs');
    }
};
