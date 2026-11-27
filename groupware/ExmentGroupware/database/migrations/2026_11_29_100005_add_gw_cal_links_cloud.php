<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Google and Microsoft calendars read into a person's calendar (ISS02947, part 6), as links of
 * gw_cal_links (provider google | microsoft; their tokens encrypted in secret_enc):
 *
 *   account       the email address of the account signed in (the only thing the page shows of it)
 *   sync_state    where the last read ended: Google's nextSyncToken or Microsoft's deltaLink
 *                 (a whole URL, longer than sync_token's 255)
 *   full_sync_at  the last read of the whole window (one every day moves the window on)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('gw_cal_links')) {
            return;
        }
        Schema::table('gw_cal_links', function (Blueprint $table) {
            if (!Schema::hasColumn('gw_cal_links', 'account')) {
                $table->string('account', 255)->nullable();
            }
            if (!Schema::hasColumn('gw_cal_links', 'sync_state')) {
                $table->text('sync_state')->nullable();
            }
            if (!Schema::hasColumn('gw_cal_links', 'full_sync_at')) {
                $table->dateTime('full_sync_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('gw_cal_links')) {
            return;
        }
        Schema::table('gw_cal_links', function (Blueprint $table) {
            foreach (['account', 'sync_state', 'full_sync_at'] as $c) {
                if (Schema::hasColumn('gw_cal_links', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
