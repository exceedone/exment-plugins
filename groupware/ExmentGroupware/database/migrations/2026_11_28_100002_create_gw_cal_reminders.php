<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The reminders of the calendar's events (ISS02947, part 2): when before an occurrence its owner
 * and attendees are told, and what was told (once an occurrence, a person, a reminder).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('gw_cal_reminders')) {
            Schema::create('gw_cal_reminders', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('event_id')->index();
                $table->unsignedInteger('minutes_before');
                $table->timestamps();

                $table->unique(['event_id', 'minutes_before'], 'gw_cal_reminders_unique');
            });
        }
        if (!Schema::hasTable('gw_cal_reminder_logs')) {
            Schema::create('gw_cal_reminder_logs', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('event_id');
                // the occurrence's original start (UTC)
                $table->dateTime('occurrence');
                $table->unsignedBigInteger('user_id');
                $table->unsignedInteger('minutes_before');
                $table->timestamps();

                $table->unique(['event_id', 'occurrence', 'user_id', 'minutes_before'], 'gw_cal_reminder_logs_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('gw_cal_reminder_logs');
        Schema::dropIfExists('gw_cal_reminders');
    }
};
