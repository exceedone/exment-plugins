<?php

return [
    'feature' => [
        'desk' => 'Hỗ trợ IT: hộp tiếp nhận',
        'desk_help' => 'Các màn hình của người phụ trách (hộp tiếp nhận, bảng điều khiển, cài đặt, thiết lập), tác vụ chạy mỗi 5 phút và các quy tắc của phiếu khi lưu. Vui lòng bật tính năng này trước khi thiết lập.',
        'portal' => 'Hỗ trợ IT: cổng khách hàng',
        'portal_help' => 'Cổng không cần đăng nhập (/support), các liên kết trong email, tiếp nhận từ email và cảnh báo giám sát, và "Yêu cầu của tôi". Chỉ hoạt động khi hộp tiếp nhận đang bật.',
        'guest_call' => 'Hỗ trợ IT: gọi với khách hàng',
        'guest_call_help' => 'Gửi cho khách hàng liên kết cuộc gọi từ phiếu (30 phút, chỉ trình duyệt đầu tiên) và trao đổi bằng giọng nói và chia sẻ màn hình. Thời lượng được tính vào thời gian làm việc của phiếu, ghi chú được lưu vào phiếu.',
        'web_chat' => 'Hỗ trợ IT: trò chuyện trên trang web',
        'web_chat_help' => 'Cửa sổ trò chuyện để dán vào trang web, theo từng hộp tiếp nhận. Khách hàng không cần đăng nhập vẫn trò chuyện được; cuộc trò chuyện trở thành phiếu của hộp tiếp nhận đó.',
        'sales_link' => 'Hỗ trợ IT: báo giá và khóa dùng thử',
        'sales_link_help' => 'Tạo báo giá của gói bán hàng từ phiếu của hộp báo giá, và lưu khóa dùng thử cùng hạn dùng vào phiếu của hộp khóa dùng thử để trả lời.',
        'pm_link' => 'Hỗ trợ IT: tạo vấn đề ExmentPM',
        'pm_link_help' => 'Tạo vấn đề của dự án ExmentPM từ phiếu chỉ bằng một nút, và hiện trạng thái của vấn đề trên phiếu. Chỉ khi đã cài ExmentPM.',
        'web_form' => 'Hỗ trợ IT: biểu mẫu trên trang web',
        'web_form_help' => 'Biểu mẫu để nhúng vào trang web, theo từng hộp tiếp nhận (biểu mẫu công khai của Exment). Nội dung được gửi trở thành phiếu của hộp tiếp nhận đó. Exment 7.3.0 trở lên.',
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
