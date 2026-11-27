<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The tasks made from the chat's messages (ISS02945 step 2): who does it, by when, done or not.
 * An issue of ExmentPM made from a message is kept here too (pm_issue "<project>:<issue>").
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('gw_chat_tasks')) {
            return;
        }
        Schema::create('gw_chat_tasks', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('message_id')->index();
            $table->unsignedBigInteger('room_id');
            $table->string('title', 200);
            $table->unsignedBigInteger('assignee')->nullable()->index();
            $table->date('due')->nullable();
            $table->dateTime('done_at')->nullable();
            $table->string('pm_issue', 40)->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gw_chat_tasks');
    }
};
