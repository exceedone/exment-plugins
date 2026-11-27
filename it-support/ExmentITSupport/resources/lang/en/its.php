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
    // a setting of the call with a server outside this site
    'call_outside' => [
        'title' => 'Calls reach an outside server',
        'lead' => 'This setting has servers that are not this site (:hosts).',
        'ip' => 'When a call starts, the browsers of the agent and of the customer each tell these servers their IP address. The voice and the screen do not pass through a STUN server.',
        'turn' => 'A TURN server relays the voice and the screen, still encrypted, when the browsers cannot reach each other directly.',
        'firewall' => 'Browsers reach these servers over UDP (or TCP). A company firewall or policy may block them. These are the ones to allow:',
        'avoid' => 'To make no outside connection, leave this empty (calls then work only inside one LAN or VPN), or run your own STUN / TURN server (coturn, for example) and enter it here.',
        'note' => 'To connect the call, an outside server (:hosts) is used.',
        'more' => 'Learn more',
        'close' => 'Close',
    ],
];
