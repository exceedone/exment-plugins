<?php

namespace App\Plugins\ExmentGroupware\Calendar;

// The calendar's classes (ISS02947), for code of the plugin outside the calendar's pages (the
// chat's call of an event): Exment does not autoload a plugin's subfolders. One list, kept here.
foreach (['Rrule', 'Occurrences', 'Visibility', 'JapaneseHolidays', 'Holidays', 'Events', 'Reminders', 'CalendarPage'] as $gwCalClass) {
    require_once __DIR__ . '/' . $gwCalClass . '.php';
}
unset($gwCalClass);
