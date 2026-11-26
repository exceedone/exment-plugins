<?php

use Illuminate\Database\Migrations\Migration;
use Exceedone\Exment\Database\ExtendedBlueprint;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The morning digests sent (ISS02939): one row a person a day, so a digest goes once a day.
     */
    public function up(): void
    {
        $schema = DB::connection()->getSchemaBuilder();

        $schema->blueprintResolver(function ($connection, $table, $callback) {
            return new ExtendedBlueprint($connection, $table, $callback);
        });

        if (!\Schema::hasTable('pm_portal_digest_logs')) {
            $schema->create('pm_portal_digest_logs', function (ExtendedBlueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('user_id');
                $table->date('day');
                // the entries it had (0: nothing to say, not sent)
                $table->integer('items')->default(0);
                $table->timestamps();

                $table->unique(['user_id', 'day'], 'pm_portal_digest_logs_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        \Schema::dropIfExists('pm_portal_digest_logs');
    }
};
