<?php

use Illuminate\Database\Migrations\Migration;
use Exceedone\Exment\Database\ExtendedBlueprint;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The codes a project answered to before it was renamed (ISS00440), so
     * an address or a mail link with the old code still opens the project.
     */
    public function up(): void
    {
        $schema = DB::connection()->getSchemaBuilder();

        $schema->blueprintResolver(function ($connection, $table, $callback) {
            return new ExtendedBlueprint($connection, $table, $callback);
        });

        if (!\Schema::hasTable('pm_portal_project_codes')) {
            $schema->create('pm_portal_project_codes', function (ExtendedBlueprint $table) {
                $table->bigIncrements('id');
                // id on the project table
                $table->unsignedBigInteger('project_id');
                // the old code, upper-cased
                $table->string('code', 64);
                $table->timestamps();

                $table->unique(['project_id', 'code'], 'pm_portal_project_codes_unique');
                $table->index('code', 'pm_portal_project_codes_code_idx');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        \Schema::dropIfExists('pm_portal_project_codes');
    }
};
