<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
// ตั้งค่าฐานข้อมูล
$servername = "localhost";
$username = "smartbot_3R";
$password = "O@COzsgcp@vi4n43";
$dbname = "smartbot_3R";

// ตั้งค่า Telegram
define('TELEGRAM_TOKEN', '8449840888:AAGORGf40dX8Biu6IRkOlVDEgE3UF7SmS3I');
define('TELEGRAM_CHAT_ID', '8422288650');

// ตั้งค่าความปลอดภัย
define('RATE_LIMIT_PER_MINUTE', 30);
define('DEFAULT_API_KEY', 'smartbot-default-key-2026');

// สร้างการเชื่อมต่อฐานข้อมูล
$conn = new mysqli($servername, $username, $password, $dbname);

// ตรวจสอบการเชื่อมต่อ
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ตั้งค่าโซนเวลา
date_default_timezone_set("Asia/Bangkok");
?>