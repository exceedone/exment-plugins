<?php

use Illuminate\Database\Migrations\Migration;
use Exceedone\Exment\Database\ExtendedBlueprint;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The custom attributes of each project's issues (ISS02939): the definitions. The values are on
     * the issues, in the issue table's column pm_attributes (PortalAttributes).
     */
    public function up(): void
    {
        $schema = DB::connection()->getSchemaBuilder();

        $schema->blueprintResolver(function ($connection, $table, $callback) {
            return new ExtendedBlueprint($connection, $table, $callback);
        });

        if (!\Schema::hasTable('pm_portal_attributes')) {
            $schema->create('pm_portal_attributes', function (ExtendedBlueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('project_id');
                $table->string('label', 60);
                // text, number, date, select, multi_select, user
                $table->string('type', 20);
                // json: choices, unit, decimals, max
                $table->text('options')->nullable();
                $table->boolean('required')->default(false);
                $table->integer('sort')->default(0);
                $table->unsignedBigInteger('created_user_id')->nullable();
                $table->unsignedBigInteger('updated_user_id')->nullable();
                $table->timestamps();
                $table->timestamp('deleted_at')->nullable();

                $table->index('project_id', 'pm_portal_attributes_project_idx');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        \Schema::dropIfExists('pm_portal_attributes');
    }
};
