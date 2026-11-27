<?php

use Illuminate\Database\Migrations\Migration;
use Exceedone\Exment\Database\ExtendedBlueprint;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Web Push for the bells (docs/pwa-design.md, step 2): the browsers that take the notices,
     * one row a browser of a person, and the site's VAPID key pair (one row, the private key
     * encrypted).
     */
    public function up(): void
    {
        $schema = DB::connection()->getSchemaBuilder();

        $schema->blueprintResolver(function ($connection, $table, $callback) {
            return new ExtendedBlueprint($connection, $table, $callback);
        });

        if (!\Schema::hasTable('pm_portal_push_subs')) {
            $schema->create('pm_portal_push_subs', function (ExtendedBlueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('user_id');
                // the push service's address of the browser, and its sha256 (one row a browser)
                $table->text('endpoint');
                $table->string('endpoint_hash', 64);
                // the browser's keys (base64url): P-256 public key and auth secret
                $table->string('p256dh', 100);
                $table->string('auth', 40);
                // "Chrome / Android", from the user agent
                $table->string('device', 100)->nullable();
                // failures in a row (5 remove the row) and the last time one was sent
                $table->integer('failures')->default(0);
                $table->dateTime('last_sent_at')->nullable();
                $table->timestamps();

                $table->unique('endpoint_hash', 'pm_portal_push_subs_endpoint');
                $table->index('user_id', 'pm_portal_push_subs_user');
            });
        }

        if (!\Schema::hasTable('pm_portal_push_keys')) {
            $schema->create('pm_portal_push_keys', function (ExtendedBlueprint $table) {
                $table->bigIncrements('id');
                // base64url of the raw public key (65 bytes)
                $table->string('public_key', 100);
                // the private key's PEM, encrypted (data keys, else the application key)
                $table->text('private_enc');
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        \Schema::dropIfExists('pm_portal_push_subs');
        \Schema::dropIfExists('pm_portal_push_keys');
    }
};
