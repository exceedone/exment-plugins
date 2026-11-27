<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The calendar's outside calendars (ISS02947, part 5): a person's links to other calendars (an
 * ICS address now; Google, Microsoft and CalDAV later), read into events with source=link, and
 * the ICS export addresses of a person's calendar (only the hash of the token is kept).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('gw_cal_links')) {
            Schema::create('gw_cal_links', function (Blueprint $table) {
                $table->bigIncrements('id');
                // Exment's user, whose calendar it is read into
                $table->unsignedBigInteger('user_id')->index();
                // ics | google | microsoft | caldav
                $table->string('provider', 16)->default('ics');
                $table->string('name', 100);
                // the address, encrypted with APP_KEY; never shown back (the host is, to tell links apart)
                $table->text('url_enc');
                $table->string('url_host', 255)->nullable();
                // a password or tokens (later providers), encrypted
                $table->text('secret_enc')->nullable();
                // read (both: later)
                $table->string('direction', 8)->default('read');
                // busy: the others see 「予定あり」 | details: as an event open to all
                $table->string('show_as', 8)->default('busy');
                $table->string('sync_token', 255)->nullable();
                $table->string('etag', 255)->nullable();
                $table->string('last_modified', 64)->nullable();
                $table->dateTime('last_sync_at')->nullable();
                // a code (ok: null), never the address
                $table->string('last_error', 64)->nullable();
                $table->unsignedInteger('fails')->default(0);
                $table->dateTime('next_sync_at')->nullable()->index();
                $table->unsignedInteger('event_count')->default(0);
                $table->boolean('enabled')->default(true);
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('gw_cal_feeds')) {
            Schema::create('gw_cal_feeds', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('user_id')->index();
                // sha256 of the token: the token itself is shown once and not kept
                $table->string('token_hash', 64)->unique();
                $table->dateTime('last_used_at')->nullable();
                $table->dateTime('revoked_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('gw_cal_feeds');
        Schema::dropIfExists('gw_cal_links');
    }
};
