<?php
include_once 'config.php';

// ฟังก์ชันส่งข้อความแจ้งเตือนผ่าน Telegram Bot
function sendTelegram($message)
{
    $url = "https://api.telegram.org/bot" . TELEGRAM_TOKEN . "/sendMessage";
    $data = [
        'chat_id' => TELEGRAM_CHAT_ID,
        'text' => $message,
        'parse_mode' => 'HTML'
    ];

    $options = [
        'http' => [
            'header' => "Content-type: application/x-www-form-urlencoded\r\n",
            'method' => 'POST',
            'content' => http_build_query($data),
        ],
    ];
    $context = stream_context_create($options);
    @file_get_contents($url, false, $context);
}
?>