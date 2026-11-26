<?php

use Illuminate\Database\Migrations\Migration;
use Exceedone\Exment\Database\ExtendedBlueprint;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The company's days off and working days of the site (ISS00432, stage 2b), read through
     * PortalCalendarDays::calendar() only.
     */
    public function up(): void
    {
        $schema = DB::connection()->getSchemaBuilder();

        $schema->blueprintResolver(function ($connection, $table, $callback) {
            return new ExtendedBlueprint($connection, $table, $callback);
        });

        if (!\Schema::hasTable('pm_portal_calendar_days')) {
            $schema->create('pm_portal_calendar_days', function (ExtendedBlueprint $table) {
                $table->bigIncrements('id');
                $table->date('date');
                // off: a day off; work: a working day (休日出勤)
                $table->string('kind', 10);
                $table->string('name', 50)->nullable();
                $table->unsignedBigInteger('updated_user_id')->nullable();
                $table->timestamps();

                $table->unique('date', 'pm_portal_calendar_days_date_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        \Schema::dropIfExists('pm_portal_calendar_days');
    }
};
