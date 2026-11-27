<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Git linked to the issues (ISS02939 item 5): the repositories a project connected by webhook,
 * and the commits and pull / merge requests that named an issue. See docs/git-link-design.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('pm_portal_git_hooks')) {
            Schema::create('pm_portal_git_hooks', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('project_id')->index();
                $table->string('provider', 8);
                $table->string('repo', 200);
                $table->char('hook_key', 32)->unique();
                // GitHub / Gitea sign with the secret (HMAC): kept encrypted. GitLab's token: its hash is enough.
                $table->text('secret_enc')->nullable();
                $table->char('secret_hash', 64);
                $table->boolean('close_on_merge')->default(false);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->dateTime('last_delivery_at')->nullable();
                $table->string('last_error', 300)->nullable();
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('pm_portal_git_events')) {
            Schema::create('pm_portal_git_events', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('project_id');
                $table->unsignedBigInteger('issue_id');
                $table->unsignedBigInteger('hook_id');
                $table->string('kind', 8);
                $table->string('ref', 80);
                $table->string('title', 300)->nullable();
                $table->string('author', 120)->nullable();
                $table->string('url', 500)->nullable();
                $table->string('branch', 200)->nullable();
                $table->string('state', 8)->nullable();
                $table->dateTime('occurred_at');
                $table->timestamps();
                $table->unique(['hook_id', 'kind', 'ref', 'issue_id'], 'pm_git_events_one');
                $table->index(['issue_id', 'occurred_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_portal_git_events');
        Schema::dropIfExists('pm_portal_git_hooks');
    }
};
