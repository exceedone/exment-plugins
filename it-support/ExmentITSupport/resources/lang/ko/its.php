<?php

return [
    'feature' => [
        'desk' => 'IT 지원: 접수함',
        'desk_help' => '담당자 화면(접수함, 대시보드, 설정, 초기 설정), 5분마다 실행되는 일괄 처리, 저장 시 티켓 규칙입니다. 초기 설정 전에 먼저 켜십시오.',
        'portal' => 'IT 지원: 고객 포털',
        'portal_help' => '로그인 없이 쓰는 포털(/support), 메일에 넣는 링크, 메일과 모니터링 경고의 접수, "내 요청"입니다. 접수함이 켜져 있을 때만 동작합니다.',
        'guest_call' => 'IT 지원: 고객과 통화',
        'guest_call_help' => '티켓에서 고객에게 통화 링크(30분, 처음 연 브라우저만)를 보내 음성과 화면 공유로 이야기합니다. 통화 시간은 티켓의 작업 시간에, 메모는 티켓에 남습니다.',
        'web_chat' => 'IT 지원: 웹사이트 채팅',
        'web_chat_help' => '접수함마다 웹사이트에 붙일 채팅 창을 만듭니다. 로그인하지 않은 고객과 이야기할 수 있고, 대화는 그 접수함의 티켓이 됩니다.',
        'sales_link' => 'IT 지원: 견적·체험 키',
        'sales_link_help' => '견적 접수함의 티켓에서 영업 팩의 "견적"을 만들고, 체험 키 접수함의 티켓에 키와 기한을 넣어 답장에 씁니다.',
        'pm_link' => 'IT 지원: ExmentPM 과제로 만들기',
        'pm_link_help' => '티켓에서 버튼 하나로 ExmentPM 프로젝트의 과제를 만들고, 티켓에 과제의 상태를 표시합니다. ExmentPM이 설치되어 있을 때만.',
        'web_form' => 'IT 지원: 웹사이트 폼',
        'web_form_help' => '접수함마다 웹사이트에 넣을 폼(Exment 공개 폼)을 만듭니다. 보낸 내용은 그 접수함의 티켓이 됩니다. Exment 7.3.0 이상.',
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
