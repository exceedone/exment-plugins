<?php

use Illuminate\Database\Migrations\Migration;
use Exceedone\Exment\Database\ExtendedBlueprint;

return new class extends Migration
{
    /**
     * Who issued which credential on which repository.
     *
     * The Git server already stores every deploy key and deploy token, so
     * this table exists for the one thing it does not store: an owner the
     * portal can trust. Gitea keeps a title, and a title is a label - the
     * caller supplies it, it can say anything, and authorising a delete
     * against a string somebody typed is not authorising anything. So the
     * owner is recorded here, server-side, at the moment of issue.
     *
     * The Git server stays the source of truth for what exists; this table
     * only answers "whose". A credential with no row here - created by hand
     * in Gitea, or before this table existed - is therefore ownerless, and
     * only a project administrator may remove it.
     *
     * Nothing secret is stored. The token's plaintext is returned once by
     * the Git server and never written down; what is kept is the id the
     * server gave it, which is enough to list and to revoke.
     */
    public function up(): void
    {
        $schema = DB::connection()->getSchemaBuilder();

        $schema->blueprintResolver(function ($connection, $table, $callback) {
            return new ExtendedBlueprint($connection, $table, $callback);
        });

        if (!\Schema::hasTable('pm_portal_git_keys')) {
            $schema->create('pm_portal_git_keys', function (ExtendedBlueprint $table) {
                $table->bigIncrements('id');
                // id on pm_portal_git_repos, not the project: credentials
                // belong to a repository, and a repository outlives the
                // request row's status changes.
                $table->unsignedBigInteger('git_repo_id');
                // The id the Git server gave this credential. Listing and
                // revoking both go through it.
                $table->unsignedBigInteger('provider_key_id');
                // ssh: a public key, token: a password for cloning over HTTP.
                // One Gitea endpoint returns both and key_type separates them.
                $table->string('key_type', 10)->default('token');
                // Kept so a row can still be shown if the Git server is down.
                $table->string('title', 120)->nullable();
                $table->timestamps();
                // timeusers() brings created_user_id and updated_user_id, and
                // ModelBase fills the first on creating with
                // \Exment::getUserId() - the user custom value id. That column
                // IS the owner here; declaring another would duplicate it.
                $table->timeusers();

                // The Git server never reuses an id within a repository, so
                // this also stops a double-recorded credential.
                $table->unique(['git_repo_id', 'provider_key_id'], 'pm_portal_git_keys_repo_key');
                // Indexed because "which credentials are mine" is the question
                // the settings tab asks on every load.
                $table->index('created_user_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        \Schema::dropIfExists('pm_portal_git_keys');
    }
};
