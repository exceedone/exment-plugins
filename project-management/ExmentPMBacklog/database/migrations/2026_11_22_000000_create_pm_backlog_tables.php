<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the Backlog connector keeps outside Exment's custom tables.
 *
 * None of it is business data: the connections (with the API key encrypted
 * by the app key), which Backlog row became which Exment row, and what each
 * run did. Real tables, so "is this Backlog issue already here" is one
 * indexed lookup.
 */
return new class extends Migration
{
    public function up(): void
    {
        // One Backlog project flowing into one Exment project.
        if (!Schema::hasTable('pmbl_connections')) {
            Schema::create('pmbl_connections', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name', 255);
                $table->string('space_url', 255);
                // Crypt::encryptString; never shown again once saved.
                $table->text('api_key_enc')->nullable();
                $table->string('backlog_project_key', 64);
                $table->unsignedBigInteger('backlog_project_id')->nullable();
                $table->unsignedBigInteger('exment_project_id');
                $table->unsignedInteger('interval_minutes')->default(30);
                $table->boolean('enabled')->default(false);
                // Backlog names/ids -> Exment ids (statuses, types, users, ...).
                $table->longText('mapping')->nullable();
                // The newest Backlog "updated" taken in full (UTC, as Backlog writes it).
                $table->string('cursor_updated', 32)->nullable();
                $table->dateTime('last_run_at')->nullable();
                $table->dateTime('next_run_at')->nullable();
                // A run holds the connection until then; it runs out by itself.
                $table->dateTime('locked_until')->nullable();
                $table->unsignedInteger('fail_count')->default(0);
                $table->unsignedBigInteger('created_user_id')->nullable();
                $table->timestamps();
            });
        }

        // Backlog row -> Exment row. Backlog ids are unique within a space.
        if (!Schema::hasTable('pmbl_links')) {
            Schema::create('pmbl_links', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('connection_id')->index();
                // issue / comment / attachment
                $table->string('kind', 16);
                $table->string('space_host', 255);
                $table->unsignedBigInteger('backlog_id');
                $table->unsignedBigInteger('exment_id');
                $table->string('backlog_key', 64)->nullable();
                // issue: Backlog's "updated" last taken in full
                $table->string('backlog_updated', 32)->nullable();
                // issue: the last Backlog comment id taken
                $table->unsignedBigInteger('last_comment_id')->nullable();
                // issue: gone from Backlog (P3), moved elsewhere (P3)
                $table->dateTime('gone_at')->nullable();
                $table->timestamps();
                $table->unique(['space_host', 'kind', 'backlog_id']);
                $table->index(['kind', 'exment_id']);
            });
        }

        // What each run did, for the settings screen and for the operator.
        if (!Schema::hasTable('pmbl_runs')) {
            Schema::create('pmbl_runs', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('connection_id')->index();
                // schedule / manual
                $table->string('trigger', 16);
                $table->dateTime('started_at');
                $table->dateTime('finished_at')->nullable();
                // running / ok / partial / failed / skipped
                $table->string('status', 16);
                $table->text('counts')->nullable();
                $table->longText('problems')->nullable();
                $table->text('message')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pmbl_runs');
        Schema::dropIfExists('pmbl_links');
        Schema::dropIfExists('pmbl_connections');
    }
};
