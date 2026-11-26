<?php

use Illuminate\Database\Migrations\Migration;
use Exceedone\Exment\Database\ExtendedBlueprint;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Recurring schedules (ISS00432, stage 2): the schedules, a date moved or skipped, and the
     * issue made for each date ((schedule, date) unique: no date makes two).
     */
    public function up(): void
    {
        $schema = DB::connection()->getSchemaBuilder();

        $schema->blueprintResolver(function ($connection, $table, $callback) {
            return new ExtendedBlueprint($connection, $table, $callback);
        });

        if (!\Schema::hasTable('pm_portal_schedules')) {
            $schema->create('pm_portal_schedules', function (ExtendedBlueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('project_id');
                $table->string('name', 100);
                $table->unsignedBigInteger('template_issue_id');
                $table->string('rrule', 255)->default('');
                $table->date('start_date');
                $table->date('end_date')->nullable();
                $table->text('rdates')->nullable();
                $table->string('shift', 10)->default('keep');
                $table->unsignedInteger('lead_days')->default(7);
                $table->text('form')->nullable();
                $table->boolean('enabled')->default(true);
                $table->unsignedBigInteger('created_user_id')->nullable();
                $table->unsignedBigInteger('updated_user_id')->nullable();
                $table->timestamps();

                $table->index('project_id', 'pm_portal_schedules_project_idx');
            });
        }
        if (!\Schema::hasTable('pm_portal_schedule_exceptions')) {
            $schema->create('pm_portal_schedule_exceptions', function (ExtendedBlueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('schedule_id');
                $table->date('original_date');
                // null: the date is skipped
                $table->date('new_date')->nullable();
                $table->timestamps();

                $table->unique(['schedule_id', 'original_date'], 'pm_portal_schedule_exceptions_unique');
            });
        }
        if (!\Schema::hasTable('pm_portal_schedule_items')) {
            $schema->create('pm_portal_schedule_items', function (ExtendedBlueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('schedule_id');
                $table->date('original_date');
                $table->unsignedBigInteger('issue_id');
                $table->timestamps();

                $table->unique(['schedule_id', 'original_date'], 'pm_portal_schedule_items_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        \Schema::dropIfExists('pm_portal_schedule_items');
        \Schema::dropIfExists('pm_portal_schedule_exceptions');
        \Schema::dropIfExists('pm_portal_schedules');
    }
};
