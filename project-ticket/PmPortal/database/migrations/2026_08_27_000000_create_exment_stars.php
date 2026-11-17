<?php

use Illuminate\Database\Migrations\Migration;
use Exceedone\Exment\Database\ExtendedBlueprint;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One row per "this user starred / watches this thing". A star is
     * Backlog's いいね on an issue, a comment or a wiki page; a watch is a
     * subscription that turns later changes into navbar notifications.
     */
    public function up(): void
    {
        $schema = DB::connection()->getSchemaBuilder();

        $schema->blueprintResolver(function ($connection, $table, $callback) {
            return new ExtendedBlueprint($connection, $table, $callback);
        });

        if (!\Schema::hasTable('exment_stars')) {
            $schema->create('exment_stars', function (ExtendedBlueprint $table) {
                $table->bigIncrements('id');
                // star or watch
                $table->string('star_type', 10)->default('star');
                // a table name ("pm_issue", "pm_wiki"...) or "comment"
                $table->string('target_type', 100);
                $table->string('target_id', 64);
                // id on the user table (the custom value, not the login user)
                $table->unsignedBigInteger('user_id');
                $table->timestamps();
                $table->timeusers();

                $table->unique(['star_type', 'target_type', 'target_id', 'user_id'], 'exment_stars_unique');
                $table->index(['target_type', 'target_id'], 'exment_stars_target_idx');
                $table->index('user_id', 'exment_stars_user_idx');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        \Schema::dropIfExists('exment_stars');
    }
};
