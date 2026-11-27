<?php

return [
    'feature' => [
        'desk' => 'IT Support: desk',
        'desk_help' => 'The agents\' screens (inbox, dashboard, settings, setup), the batch every five minutes and the ticket rules on save. Turn it on before the setup.',
        'portal' => 'IT Support: customer portal',
        'portal_help' => 'The no-login portal (/support), the links in mails, mail and alert intake, and "my requests". Works only while the desk is on.',
        'guest_call' => 'Help desk: calls with customers',
        'guest_call_help' => 'Send a customer a call link from a ticket (30 minutes, the first browser only) and talk with voice and a shared screen. The length goes to the ticket\'s time, the notes to the ticket.',
        'web_chat' => 'Help desk: website chat',
        'web_chat_help' => 'A chat window to paste into a website for each box. Customers without a login talk to the desk; the talk becomes a ticket of that box.',
        'sales_link' => 'Help desk: quotes and trial keys',
        'sales_link_help' => 'Make a quote of the sales pack from a ticket of a quote box, and keep a trial key and its end on a ticket of a trial box for the reply.',
        'pm_link' => 'Help desk: make an ExmentPM issue',
        'pm_link_help' => 'Make an issue of an ExmentPM project from a ticket in one press, and show the status of the issue on the ticket. Only with ExmentPM installed.',
        'web_form' => 'Help desk: website forms',
        'web_form_help' => 'A form to embed in a website for each box (an Exment public form). What is sent becomes a ticket of that box. Exment 7.3.0 or later.',
    ],
];
