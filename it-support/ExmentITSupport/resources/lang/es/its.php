<?php

return [
    'feature' => [
        'desk' => 'Soporte de TI: bandeja de entrada',
        'desk_help' => 'Las pantallas de los agentes (bandeja de entrada, panel, configuración, instalación), el proceso por lotes cada cinco minutos y las reglas de los tickets al guardar. Actívelo antes de la instalación.',
        'portal' => 'Soporte de TI: portal de clientes',
        'portal_help' => 'El portal sin inicio de sesión (/support), los enlaces de los correos, la recepción de correos y alertas de monitorización, y "Mis solicitudes". Solo funciona mientras la bandeja de entrada está activada.',
        'guest_call' => 'Soporte de TI: llamadas con clientes',
        'guest_call_help' => 'Envíe a un cliente un enlace de llamada desde un ticket (30 minutos, solo el primer navegador) y hable con voz y pantalla compartida. La duración pasa al tiempo del ticket y las notas, al ticket.',
        'web_chat' => 'Soporte de TI: chat del sitio web',
        'web_chat_help' => 'Una ventana de chat para pegar en un sitio web, por bandeja de entrada. Los clientes sin inicio de sesión hablan con el soporte; la conversación se convierte en un ticket de esa bandeja.',
        'sales_link' => 'Soporte de TI: presupuestos y claves de prueba',
        'sales_link_help' => 'Cree un presupuesto del paquete de ventas desde un ticket de una bandeja de presupuestos, y guarde una clave de prueba y su vencimiento en un ticket de una bandeja de claves de prueba para la respuesta.',
        'pm_link' => 'Soporte de TI: convertir en incidencia de ExmentPM',
        'pm_link_help' => 'Cree con un clic una incidencia de un proyecto de ExmentPM desde un ticket y muestre el estado de la incidencia en el ticket. Solo con ExmentPM instalado.',
        'web_form' => 'Soporte de TI: formularios del sitio web',
        'web_form_help' => 'Un formulario para insertar en un sitio web, por bandeja de entrada (un formulario público de Exment). Lo que se envía se convierte en un ticket de esa bandeja. Exment 7.3.0 o posterior.',
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
