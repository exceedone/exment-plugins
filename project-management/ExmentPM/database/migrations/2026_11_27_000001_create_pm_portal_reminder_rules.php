<?php

use Illuminate\Database\Migrations\Migration;
use Exceedone\Exment\Database\ExtendedBlueprint;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The reminders of each project (ISS00432, stage 1b): on or off, the site's rule or its own,
     * and the channels (the webhook URLs encrypted).
     */
    public function up(): void
    {
        $schema = DB::connection()->getSchemaBuilder();

        $schema->blueprintResolver(function ($connection, $table, $callback) {
            return new ExtendedBlueprint($connection, $table, $callback);
        });

        if (!\Schema::hasTable('pm_portal_reminder_rules')) {
            $schema->create('pm_portal_reminder_rules', function (ExtendedBlueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('project_id');
                $table->boolean('enabled')->default(true);
                $table->boolean('use_site')->default(true);
                $table->text('settings')->nullable();
                $table->text('channels')->nullable();
                $table->unsignedBigInteger('updated_user_id')->nullable();
                $table->timestamps();

                $table->unique('project_id', 'pm_portal_reminder_rules_project_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        \Schema::dropIfExists('pm_portal_reminder_rules');
    }
};
