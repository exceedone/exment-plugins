<?php

use Illuminate\Database\Migrations\Migration;
use Exceedone\Exment\Database\ExtendedBlueprint;

return new class extends Migration
{
    /**
     * Every call the portal makes to the AI, with what came back.
     *
     * Three readers: the per-project daily cap (count of today's rows), the
     * statistics block on the project settings tab, and the backtest that
     * compares "suggested" with "applied" - the values the person finally
     * saved, written by the feedback endpoint or by the batch itself.
     *
     * The raw answers are kept as JSON so a change to the criteria can be
     * re-scored from stored responses without paying for the calls again.
     *
     * issue_id is nullable: the button runs while the issue is still being
     * written, and the row is linked to the issue only when the save
     * succeeds. exment:refreshdata renumbers issues from 1, so the batch
     * command sweeps rows whose issue is gone or newer than the row's last
     * write (updated_at - the link itself is written after the save).
     */
    public function up(): void
    {
        $schema = DB::connection()->getSchemaBuilder();

        $schema->blueprintResolver(function ($connection, $table, $callback) {
            return new ExtendedBlueprint($connection, $table, $callback);
        });

        if (!\Schema::hasTable('pm_portal_ai_logs')) {
            $schema->create('pm_portal_ai_logs', function (ExtendedBlueprint $table) {
                $table->bigIncrements('id');
                // id on the project custom table
                $table->unsignedBigInteger('project_id')->index();
                // id on the issue custom table, null until the issue exists
                $table->unsignedBigInteger('issue_id')->nullable()->index();
                // suggest (button) | batch | backtest | test
                $table->string('kind', 16);
                // sha1 of subject + description: whether two calls saw the
                // same text
                $table->char('input_hash', 40);
                // the model the remote side says it used; drift shows here
                $table->string('model', 32)->nullable();
                $table->unsignedInteger('input_tokens')->default(0);
                $table->unsignedInteger('latency_ms')->default(0);
                // raw answers block of the response
                $table->json('answers')->nullable();
                // after normalisation: column => {value, label, confidence, ok}
                $table->json('suggested')->nullable();
                // what was finally saved for the same columns
                $table->json('applied')->nullable();
                // ok | error
                $table->string('status', 8)->default('ok');
                $table->string('error', 200)->nullable();
                $table->timestamps();
                // ModelBase fills created_user_id on creating; that column is
                // who pressed the button, null for the batch and the event.
                $table->timeusers();

                $table->index(['project_id', 'created_at'], 'pm_portal_ai_logs_project_created');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        \Schema::dropIfExists('pm_portal_ai_logs');
    }
};
