<?php

namespace App\Plugins\ExmentGroupware\Calendar;

// The calendar's classes (ISS02947), for code of the plugin outside the calendar's pages (the
// chat's call of an event): Exment does not autoload a plugin's subfolders. One list, kept here.
foreach (['Rrule', 'Occurrences', 'Visibility', 'JapaneseHolidays', 'Holidays', 'Events', 'Reminders', 'Resources', 'PmLayer', 'CalendarPage'] as $gwCalClass) {
    require_once __DIR__ . '/' . $gwCalClass . '.php';
}
unset($gwCalClass);
// the outside calendars and the ICS export (part 5), with the address check of the mail
foreach (['MailException', 'HostGuard'] as $gwCalClass) {
    if (!class_exists('App\\Plugins\\ExmentGroupware\\Mail\\' . $gwCalClass, false)) {
        require_once __DIR__ . '/../Mail/' . $gwCalClass . '.php';
    }
}
// the secrets of the settings (Google and Microsoft calendars, part 6)
if (!class_exists('App\\Plugins\\ExmentGroupware\\SecretOption', false)) {
    require_once __DIR__ . '/../SecretOption.php';
}
foreach (['Ics', 'IcsFetchError', 'IcsFetch', 'Links', 'Feeds', 'CloudFetch', 'CloudOAuth', 'CloudSync', 'LinksPage'] as $gwCalClass) {
    require_once __DIR__ . '/' . $gwCalClass . '.php';
}
unset($gwCalClass);
