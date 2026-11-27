<?php

return [
    'feature' => [
        'desk' => 'IT 지원: 접수함',
        'desk_help' => '담당자 화면(접수함, 대시보드, 설정, 초기 설정), 5분마다 실행되는 일괄 처리, 저장 시 티켓 규칙입니다. 초기 설정 전에 먼저 켜십시오.',
        'portal' => 'IT 지원: 고객 포털',
        'portal_help' => '로그인 없이 쓰는 포털(/support), 메일에 넣는 링크, 메일과 모니터링 경고의 접수, "내 요청"입니다. 접수함이 켜져 있을 때만 동작합니다.',
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
