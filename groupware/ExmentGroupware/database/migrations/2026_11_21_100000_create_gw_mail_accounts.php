<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The mail accounts of the Web mail (one user can have several).
 *
 * The mail itself stays on the customer's mail server (IMAP); only the way
 * to connect is kept here. The password or the OAuth tokens are encrypted
 * with the app key (APP_KEY), so a copy of the database alone opens nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('gw_mail_accounts')) {
            return;
        }
        Schema::create('gw_mail_accounts', function (Blueprint $table) {
            $table->bigIncrements('id');
            // Exment's user (custom value of the user table), the owner.
            $table->unsignedBigInteger('user_id')->index();
            $table->string('name', 100);
            $table->string('email', 255);
            $table->string('from_name', 100)->nullable();
            // xserver | kagoya | microsoft365 | gmail | other
            $table->string('provider', 32);
            // password | oauth
            $table->string('auth_type', 16)->default('password');
            $table->string('username', 255);
            $table->text('secret_enc')->nullable();
            $table->dateTime('token_expires_at')->nullable();
            $table->string('imap_host', 255);
            $table->unsignedInteger('imap_port')->default(993);
            $table->string('imap_security', 8)->default('ssl');
            $table->string('smtp_host', 255);
            $table->unsignedInteger('smtp_port')->default(465);
            $table->string('smtp_security', 8)->default('ssl');
            $table->boolean('append_sent')->default(true);
            // Folder names as the server has them (modified UTF-7); found on first use.
            $table->string('sent_folder', 255)->nullable();
            $table->string('drafts_folder', 255)->nullable();
            $table->string('trash_folder', 255)->nullable();
            $table->string('junk_folder', 255)->nullable();
            $table->text('signature')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('last_error', 500)->nullable();
            $table->dateTime('last_connected_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gw_mail_accounts');
    }
};
