<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The reactions of the chat's messages (ISS02945 step 2): one of a fixed list per person and message. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('gw_chat_reactions')) {
            return;
        }
        Schema::create('gw_chat_reactions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('message_id');
            $table->unsignedBigInteger('user_id');
            $table->string('emoji', 16);
            $table->dateTime('created_at');
            $table->unique(['message_id', 'user_id', 'emoji']);
            $table->index('message_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gw_chat_reactions');
    }
};
