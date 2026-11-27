<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The calendar (ISS02947): the events, an occurrence changed or cancelled one by one, and the
 * attendees with their replies. Times are UTC; an all-day event keeps its days (00:00 UTC to the
 * day after its last); a repeat is an RRULE of RFC 5545 read in the event's time zone.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('gw_cal_events')) {
            Schema::create('gw_cal_events', function (Blueprint $table) {
                $table->bigIncrements('id');
                // Exment's user, who made it (and may change it)
                $table->unsignedBigInteger('owner_user_id')->index();
                $table->string('title', 200);
                $table->string('location', 200)->nullable();
                $table->text('body')->nullable();
                $table->dateTime('starts_at')->index();
                $table->dateTime('ends_at');
                $table->boolean('all_day')->default(false);
                $table->string('tz', 64)->default('Asia/Tokyo');
                $table->string('rrule', 255)->nullable();
                // the last day a repeat can have (from UNTIL or COUNT), for the queries; null: no end
                $table->dateTime('repeat_until')->nullable()->index();
                $table->text('exdates')->nullable();
                $table->text('rdates')->nullable();
                // all | org | attendees | busy
                $table->string('visibility', 16)->default('all');
                $table->unsignedBigInteger('org_id')->nullable();
                // local | link (an outside calendar, later)
                $table->string('source', 16)->default('local');
                $table->unsignedBigInteger('link_id')->nullable()->index();
                $table->string('external_id', 255)->nullable();
                $table->string('external_etag', 255)->nullable();
                $table->unsignedBigInteger('created_user_id')->nullable();
                $table->unsignedBigInteger('updated_user_id')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }
        if (!Schema::hasTable('gw_cal_overrides')) {
            Schema::create('gw_cal_overrides', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('event_id');
                // the start the occurrence had (UTC): RECURRENCE-ID
                $table->dateTime('original_start');
                $table->boolean('cancelled')->default(false);
                $table->dateTime('starts_at')->nullable();
                $table->dateTime('ends_at')->nullable();
                $table->string('title', 200)->nullable();
                $table->string('location', 200)->nullable();
                $table->timestamps();

                $table->unique(['event_id', 'original_start'], 'gw_cal_overrides_unique');
            });
        }
        if (!Schema::hasTable('gw_cal_attendees')) {
            Schema::create('gw_cal_attendees', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('event_id');
                $table->unsignedBigInteger('user_id')->index();
                // none | yes | no | maybe
                $table->string('response', 8)->default('none');
                $table->dateTime('responded_at')->nullable();
                $table->timestamps();

                $table->unique(['event_id', 'user_id'], 'gw_cal_attendees_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('gw_cal_attendees');
        Schema::dropIfExists('gw_cal_overrides');
        Schema::dropIfExists('gw_cal_events');
    }
};
