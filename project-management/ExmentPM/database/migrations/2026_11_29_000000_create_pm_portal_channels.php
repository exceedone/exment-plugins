<?php

use Illuminate\Database\Migrations\Migration;
use Exceedone\Exment\Database\ExtendedBlueprint;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The chat channels of each project (ISS02939): Slack, Teams, LINE WORKS, Chatwork or a
     * webhook, and the events each one takes. The URLs and tokens are kept encrypted.
     */
    public function up(): void
    {
        $schema = DB::connection()->getSchemaBuilder();

        $schema->blueprintResolver(function ($connection, $table, $callback) {
            return new ExtendedBlueprint($connection, $table, $callback);
        });

        if (!\Schema::hasTable('pm_portal_channels')) {
            $schema->create('pm_portal_channels', function (ExtendedBlueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('project_id');
                // slack, teams, line_works, chatwork, webhook
                $table->string('type', 20);
                $table->string('label', 60)->nullable();
                // the webhook URL or the Chatwork token, encrypted
                $table->text('secret')->nullable();
                // the LINE WORKS channel id, the Chatwork room id
                $table->string('target', 100)->nullable();
                // the secret a webhook's body is signed with, encrypted
                $table->text('sign_secret')->nullable();
                // json: created, status, comment, reminder
                $table->text('events')->nullable();
                $table->boolean('enabled')->default(true);
                // the index of a channel moved from the reminders of the project (pm_portal_reminder_rules)
                $table->integer('legacy_index')->nullable();
                $table->unsignedBigInteger('created_user_id')->nullable();
                $table->unsignedBigInteger('updated_user_id')->nullable();
                $table->timestamps();

                $table->index('project_id', 'pm_portal_channels_project_idx');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        \Schema::dropIfExists('pm_portal_channels');
    }
};
