<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The senders of alerts (ISS02928): each monitoring tool its own key, so one
 * can be stopped or replaced without touching the others. Only the SHA-256 of
 * the key is kept; the signing secret, which has to be read back to check a
 * signature, is encrypted with the app key.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('sd_intake_sources')) {
            Schema::create('sd_intake_sources', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name', 100);
                // generic | zabbix | mackerel | alertmanager | azure
                $table->string('kind', 16);
                $table->char('key_hash', 64)->unique();
                // the first characters of the key, to tell the keys apart on the screen
                $table->string('key_hint', 16);
                $table->text('secret_enc')->nullable();
                $table->boolean('require_signature')->default(false);
                // off until an administrator turns it on
                $table->boolean('enabled')->default(false);
                // note | resolve | close
                $table->string('on_resolved', 8)->default('note');
                // a problem back within this many minutes of its recovery opens the same ticket again (0: never)
                $table->unsignedInteger('flap_minutes')->default(30);
                $table->boolean('link_asset')->default(true);
                // one address or CIDR range a line; empty: any
                $table->text('allowed_ips')->nullable();
                $table->dateTime('last_used_at')->nullable();
                $table->unsignedInteger('use_count')->default(0);
                $table->unsignedBigInteger('created_user_id')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sd_intake_sources');
    }
};
