<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Calls with a customer by a link from a ticket (ISS02941): the call (a one-time link: only
 * hashes kept) and the signals the two browsers pass to connect. The voice and the screen go
 * browser to browser; nothing of them is kept here.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('sd_calls')) {
            Schema::create('sd_calls', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('ticket_id')->index();
                $table->unsignedBigInteger('agent_id');
                $table->char('token_hash', 64)->unique();
                // the browser that opened the link first: the second secret it was given
                $table->char('bound_hash', 64)->nullable();
                $table->dateTime('expires_at');
                $table->dateTime('joined_at')->nullable();
                $table->dateTime('answered_at')->nullable();
                $table->dateTime('ended_at')->nullable();
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('sd_call_signals')) {
            Schema::create('sd_call_signals', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('call_id');
                // agent | guest: who sent it (the other one reads it)
                $table->string('side', 8);
                $table->string('type', 16);
                $table->mediumText('payload')->nullable();
                $table->dateTime('created_at');
                $table->index(['call_id', 'id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sd_call_signals');
        Schema::dropIfExists('sd_calls');
    }
};
