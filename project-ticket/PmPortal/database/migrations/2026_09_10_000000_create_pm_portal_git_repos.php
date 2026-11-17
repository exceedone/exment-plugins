<?php

use Illuminate\Database\Migrations\Migration;
use Exceedone\Exment\Database\ExtendedBlueprint;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One row per project that asked for a Git repository. The row is the
     * portal's own record of that request - who asked, what name, how far
     * the provisioning got - and is deliberately NOT a custom column on the
     * project table: anybody who may edit the project could then flip the
     * status to "ready" from the raw data grid, and the status is supposed
     * to be something only the provisioner writes.
     */
    public function up(): void
    {
        $schema = DB::connection()->getSchemaBuilder();

        $schema->blueprintResolver(function ($connection, $table, $callback) {
            return new ExtendedBlueprint($connection, $table, $callback);
        });

        if (!\Schema::hasTable('pm_portal_git_repos')) {
            $schema->create('pm_portal_git_repos', function (ExtendedBlueprint $table) {
                $table->bigIncrements('id');
                // id on the project custom table; one repository per project
                $table->unsignedBigInteger('project_id')->unique();
                $table->string('repo_name', 100);
                // gitea / gitlab / github..., null while no provider is set
                $table->string('provider', 20)->nullable();
                // pending: requested, waiting for a configured provisioner
                // ready:   the repository exists on the Git server
                // failed:  the last provisioning attempt threw
                $table->string('status', 20)->default('pending');
                $table->string('remote_url', 255)->nullable();
                $table->text('last_error')->nullable();
                // id on the user custom table (the custom value, not login user)
                $table->unsignedBigInteger('requested_user_id')->nullable();
                $table->timestamp('provisioned_at')->nullable();
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
        \Schema::dropIfExists('pm_portal_git_repos');
    }
};
