<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Who is in a call (ISS02946 step 2: up to 4 people, each connected to each other). */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('gw_chat_call_members')) {
            return;
        }
        Schema::create('gw_chat_call_members', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('call_id');
            $table->unsignedBigInteger('user_id');
            $table->dateTime('joined_at');
            $table->dateTime('left_at')->nullable();
            $table->unique(['call_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gw_chat_call_members');
    }
};
