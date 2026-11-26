<?php

use Illuminate\Database\Migrations\Migration;
use Exceedone\Exment\Database\ExtendedBlueprint;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Issues linked to records of Exment (ISS02939): the tables a project links to, and the links.
     */
    public function up(): void
    {
        $schema = DB::connection()->getSchemaBuilder();

        $schema->blueprintResolver(function ($connection, $table, $callback) {
            return new ExtendedBlueprint($connection, $table, $callback);
        });

        if (!\Schema::hasTable('pm_portal_link_tables')) {
            $schema->create('pm_portal_link_tables', function (ExtendedBlueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('project_id');
                $table->string('table_name', 100);
                $table->integer('sort')->default(0);
                $table->timestamps();

                $table->unique(['project_id', 'table_name'], 'pm_portal_link_tables_unique');
            });
        }
        if (!\Schema::hasTable('pm_portal_record_links')) {
            $schema->create('pm_portal_record_links', function (ExtendedBlueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('issue_id');
                $table->string('table_name', 100);
                $table->unsignedBigInteger('record_id');
                $table->unsignedBigInteger('created_user_id')->nullable();
                $table->timestamps();

                $table->unique(['issue_id', 'table_name', 'record_id'], 'pm_portal_record_links_unique');
                $table->index(['table_name', 'record_id'], 'pm_portal_record_links_record_idx');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        \Schema::dropIfExists('pm_portal_record_links');
        \Schema::dropIfExists('pm_portal_link_tables');
    }
};
