<?php

return [
    'feature' => [
        'desk' => 'IT 支持：受理箱',
        'desk_help' => '负责人的界面（受理箱、仪表板、设置、初始设置）、每 5 分钟运行的批处理，以及保存时的工单规则。请在初始设置之前开启。',
        'portal' => 'IT 支持：客户门户',
        'portal_help' => '无需登录的门户（/support）、邮件中的链接、来自邮件和监控告警的受理，以及“我的请求”。仅在受理箱开启时有效。',
        'guest_call' => 'IT 支持：与客户通话',
        'guest_call_help' => '从工单向客户发送通话链接（30 分钟，仅限第一个打开的浏览器），用语音和屏幕共享交谈。时长计入工单的工作时间，备注保存在工单中。',
        'web_chat' => 'IT 支持：网站聊天',
        'web_chat_help' => '为每个受理箱提供可贴到网站上的聊天窗口。未登录的客户也能交谈，对话会成为该受理箱的工单。',
        'sales_link' => 'IT 支持：报价与试用密钥',
        'sales_link_help' => '从报价受理箱的工单创建销售套件的"报价"，并在试用密钥受理箱的工单中记录密钥和期限，用于回复。',
        'pm_link' => 'IT 支持：转为 ExmentPM 问题',
        'pm_link_help' => '从工单一键创建 ExmentPM 项目的问题，并在工单上显示问题的状态。仅在安装了 ExmentPM 时可用。',
        'web_form' => 'IT 支持：网站表单',
        'web_form_help' => '为每个受理箱创建可嵌入网站的表单（Exment 的公开表单）。提交的内容会成为该受理箱的工单。Exment 7.3.0 及以上。',
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
