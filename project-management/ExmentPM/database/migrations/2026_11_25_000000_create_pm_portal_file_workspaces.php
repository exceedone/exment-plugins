<?php

use Illuminate\Database\Migrations\Migration;
use Exceedone\Exment\Database\ExtendedBlueprint;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The workspace of the file server a project's Files tab shows (ISS02825): one row per
     * project, one project per workspace. The plugin's own table, not a column of the project:
     * the link is made from the project settings by its manager, and the permissions of the
     * workspace follow from it.
     */
    public function up(): void
    {
        $schema = DB::connection()->getSchemaBuilder();

        $schema->blueprintResolver(function ($connection, $table, $callback) {
            return new ExtendedBlueprint($connection, $table, $callback);
        });

        if (!\Schema::hasTable('pm_portal_file_workspaces')) {
            $schema->create('pm_portal_file_workspaces', function (ExtendedBlueprint $table) {
                $table->bigIncrements('id');
                // id on the project custom table
                $table->unsignedBigInteger('project_id')->unique();
                // id on file_workspaces (Exment's file server)
                $table->unsignedBigInteger('workspace_id')->unique();
                $table->timestamps();
                $table->timeusers();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        \Schema::dropIfExists('pm_portal_file_workspaces');
    }
};
