<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Calls from a chat room (ISS02946 step 1): the call, and the signals the two browsers send
 * each other to connect (WebRTC offer / answer / ICE), read by polling while the call lasts.
 * The voice and the screen go browser to browser; nothing of them is kept here.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('gw_chat_calls')) {
            Schema::create('gw_chat_calls', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('room_id')->index();
                $table->unsignedBigInteger('started_by');
                $table->dateTime('started_at');
                $table->dateTime('answered_at')->nullable();
                $table->dateTime('ended_at')->nullable();
            });
        }
        if (!Schema::hasTable('gw_chat_signals')) {
            Schema::create('gw_chat_signals', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('call_id');
                $table->unsignedBigInteger('from_user');
                $table->unsignedBigInteger('to_user');
                $table->string('type', 16);
                $table->mediumText('payload')->nullable();
                $table->dateTime('created_at');
                $table->index(['to_user', 'id']);
                $table->index('call_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('gw_chat_signals');
        Schema::dropIfExists('gw_chat_calls');
    }
};
