<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The calendar's rooms and equipment (ISS02947, part 3): what can be booked with an event (a
 * meeting room, the company car, a projector...), and which events book what.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('gw_cal_resources')) {
            Schema::create('gw_cal_resources', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name', 100);
                // room | equipment
                $table->string('kind', 16)->default('room');
                $table->unsignedInteger('capacity')->nullable();
                $table->string('note', 500)->nullable();
                $table->boolean('enabled')->default(true);
                $table->integer('sort')->default(0);
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('gw_cal_event_resources')) {
            Schema::create('gw_cal_event_resources', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('event_id');
                $table->unsignedBigInteger('resource_id')->index();
                $table->timestamps();

                $table->unique(['event_id', 'resource_id'], 'gw_cal_event_resources_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('gw_cal_event_resources');
        Schema::dropIfExists('gw_cal_resources');
    }
};
