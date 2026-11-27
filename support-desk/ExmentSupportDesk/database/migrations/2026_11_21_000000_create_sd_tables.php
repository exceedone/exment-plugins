<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tables the help desk keeps outside Exment's custom tables.
 *
 * None of these is business data an administrator would want to edit on a
 * data screen: they are the machinery behind the no-login links and the mail
 * threading. Kept as real tables so a lookup by token hash or Message-ID is
 * one indexed query.
 */
return new class extends Migration
{
    public function up(): void
    {
        // A secret link. Only the SHA-256 of the token is stored, so a copy of
        // the database does not hand out working links (EXMENT_CORE ISS00172).
        if (!Schema::hasTable('sd_tokens')) {
            Schema::create('sd_tokens', function (Blueprint $table) {
                $table->bigIncrements('id');
                // ticket: one ticket / mine: the list for one address / csat: the survey
                $table->string('kind', 16);
                $table->char('token_hash', 64)->unique();
                // The token again, encrypted with the app key, so the next
                // mail of the same ticket can carry the same link. A copy of
                // the database alone still opens nothing.
                $table->text('token_enc')->nullable();
                $table->unsignedBigInteger('ticket_id')->nullable()->index();
                $table->string('email', 255)->nullable()->index();
                $table->dateTime('expires_at')->nullable();
                $table->dateTime('revoked_at')->nullable();
                $table->dateTime('last_used_at')->nullable();
                $table->unsignedInteger('use_count')->default(0);
                $table->unsignedBigInteger('created_user_id')->nullable();
                $table->timestamps();
            });
        }

        // One-time codes for links that ask for one (require_otp).
        if (!Schema::hasTable('sd_otps')) {
            Schema::create('sd_otps', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('token_id')->index();
                $table->char('code_hash', 64);
                $table->dateTime('expires_at');
                $table->unsignedTinyInteger('attempts')->default(0);
                $table->dateTime('used_at')->nullable();
                $table->timestamps();
            });
        }

        // Who opened what, when. The customer's view of a ticket is evidence
        // in a dispute, and an unusual pattern is how a leaked link shows.
        if (!Schema::hasTable('sd_access_logs')) {
            Schema::create('sd_access_logs', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('token_id')->nullable()->index();
                $table->unsignedBigInteger('ticket_id')->nullable()->index();
                $table->string('action', 32);
                $table->string('ip', 64)->nullable();
                $table->string('user_agent', 255)->nullable();
                $table->dateTime('created_at')->nullable();
            });
        }

        // Message-ID to ticket, both ways: what came in and what went out,
        // so a reply finds its ticket even when the subject lost the number.
        if (!Schema::hasTable('sd_mail_index')) {
            Schema::create('sd_mail_index', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('message_id', 191)->unique();
                $table->unsignedBigInteger('ticket_id')->index();
                $table->string('direction', 8);
                $table->dateTime('created_at')->nullable();
            });
        }

        // "Someone is already answering": an agent holds a ticket while the
        // reply box is open, so two people do not answer the same customer.
        if (!Schema::hasTable('sd_locks')) {
            Schema::create('sd_locks', function (Blueprint $table) {
                $table->unsignedBigInteger('ticket_id')->primary();
                $table->unsignedBigInteger('user_id');
                $table->string('user_name', 255)->nullable();
                $table->dateTime('expires_at');
            });
        }
    }

    public function down(): void
    {
        foreach (['sd_locks', 'sd_mail_index', 'sd_access_logs', 'sd_otps', 'sd_tokens'] as $name) {
            Schema::dropIfExists($name);
        }
    }
};
