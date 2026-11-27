<?php

return [
    'feature' => [
        'desk' => 'IT-Support: Eingang',
        'desk_help' => 'Die Bildschirme der Bearbeiter (Eingang, Dashboard, Einstellungen, Einrichtung), der Batch alle fünf Minuten und die Ticketregeln beim Speichern. Schalten Sie dies vor der Einrichtung ein.',
        'portal' => 'IT-Support: Kundenportal',
        'portal_help' => 'Das Portal ohne Anmeldung (/support), die Links in E-Mails, die Annahme von E-Mails und Überwachungsalarmen sowie „Meine Anfragen“. Funktioniert nur, solange der Eingang eingeschaltet ist.',
        'guest_call' => 'IT-Support: Anrufe mit Kunden',
        'guest_call_help' => 'Senden Sie einem Kunden aus einem Ticket einen Anruflink (30 Minuten, nur der erste Browser) und sprechen Sie mit Stimme und geteiltem Bildschirm. Die Dauer geht in die Arbeitszeit des Tickets, die Notizen in das Ticket.',
        'web_chat' => 'IT-Support: Website-Chat',
        'web_chat_help' => 'Ein Chatfenster zum Einfügen in eine Website, je Eingang. Kunden ohne Anmeldung sprechen mit dem Support; das Gespräch wird ein Ticket dieses Eingangs.',
        'sales_link' => 'IT-Support: Angebote und Testschlüssel',
        'sales_link_help' => 'Aus einem Ticket eines Angebotseingangs ein Angebot des Vertriebspakets erstellen und in einem Ticket eines Testschlüsseleingangs einen Schlüssel und sein Ablaufdatum für die Antwort festhalten.',
        'pm_link' => 'IT-Support: Zum ExmentPM-Vorgang machen',
        'pm_link_help' => 'Mit einem Klick aus einem Ticket einen Vorgang eines ExmentPM-Projekts erstellen und den Status des Vorgangs am Ticket zeigen. Nur wenn ExmentPM installiert ist.',
        'web_form' => 'IT-Support: Website-Formulare',
        'web_form_help' => 'Ein Formular zum Einbetten in eine Website, je Eingang (ein öffentliches Exment-Formular). Was gesendet wird, wird ein Ticket dieses Eingangs. Ab Exment 7.3.0.',
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
