<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The chat (ISS02945): rooms, their members, messages and the history of an edit. See
 * docs/chat-design.md. A guest (a customer by a one-time link, later from the help desk) has
 * room in gw_chat_members.guest_id and gw_chat_guests; step 1 uses Exment users only.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('gw_chat_rooms')) {
            Schema::create('gw_chat_rooms', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('kind', 8);                       // group | direct
                $table->string('name', 100)->nullable();
                $table->text('description')->nullable();
                $table->string('direct_key', 64)->nullable()->unique();
                $table->string('source_type', 16)->nullable();   // organization | role_group | its_ticket
                $table->unsignedBigInteger('source_id')->nullable();
                $table->unsignedInteger('retention_days')->nullable();
                $table->unsignedBigInteger('created_by');
                $table->dateTime('archived_at')->nullable();
                $table->unsignedBigInteger('last_message_id')->nullable();
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('gw_chat_guests')) {
            Schema::create('gw_chat_guests', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name', 100);
                $table->string('email', 255)->nullable();
                $table->string('token_hash', 64)->unique();
                $table->dateTime('expires_at');
                $table->string('ip', 45)->nullable();
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('gw_chat_members')) {
            Schema::create('gw_chat_members', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('room_id');
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->unsignedBigInteger('guest_id')->nullable()->index();
                $table->string('role', 8)->default('member');   // owner | member
                $table->boolean('muted')->default(false);
                $table->unsignedBigInteger('last_read_id')->default(0);
                $table->dateTime('joined_at');
                $table->unique(['room_id', 'user_id']);
                $table->unique(['room_id', 'guest_id']);
            });
        }
        if (!Schema::hasTable('gw_chat_messages')) {
            Schema::create('gw_chat_messages', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('room_id');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->unsignedBigInteger('guest_id')->nullable();
                $table->mediumText('body')->nullable();
                $table->unsignedBigInteger('reply_to')->nullable();
                $table->json('mentions')->nullable();
                $table->json('files')->nullable();
                $table->dateTime('edited_at')->nullable();
                $table->dateTime('deleted_at')->nullable();
                $table->dateTime('created_at');
                $table->index(['room_id', 'id']);
            });
        }
        if (!Schema::hasTable('gw_chat_revisions')) {
            Schema::create('gw_chat_revisions', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('message_id')->index();
                $table->mediumText('body')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->dateTime('created_at');
            });
        }
    }

    public function down(): void
    {
        foreach (['gw_chat_revisions', 'gw_chat_messages', 'gw_chat_members', 'gw_chat_guests', 'gw_chat_rooms'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
