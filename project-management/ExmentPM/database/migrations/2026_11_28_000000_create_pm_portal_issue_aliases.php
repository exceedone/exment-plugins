<?php

use Illuminate\Database\Migrations\Migration;
use Exceedone\Exment\Database\ExtendedBlueprint;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Other names of an issue (ISS00248): its alias, and the keys it had before a move. Unique
     * across every project, whatever the case.
     */
    public function up(): void
    {
        $schema = DB::connection()->getSchemaBuilder();

        $schema->blueprintResolver(function ($connection, $table, $callback) {
            return new ExtendedBlueprint($connection, $table, $callback);
        });

        if (!\Schema::hasTable('pm_portal_issue_aliases')) {
            $schema->create('pm_portal_issue_aliases', function (ExtendedBlueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('issue_id');
                $table->string('alias', 60);
                $table->string('alias_lower', 60);
                // name: the alias; old: a key it had
                $table->string('kind', 10);
                $table->unsignedBigInteger('updated_user_id')->nullable();
                $table->timestamps();

                $table->unique('alias_lower', 'pm_portal_issue_aliases_unique');
                $table->index('issue_id', 'pm_portal_issue_aliases_issue_idx');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        \Schema::dropIfExists('pm_portal_issue_aliases');
    }
};
