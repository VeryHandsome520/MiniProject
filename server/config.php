<?php
// DB Config
$servername = "localhost";
$username = "smartbot_3R";
$password = "O@COzsgcp@vi4n43";
$dbname = "smartbot_3R";

// Telegram Config
define('TELEGRAM_TOKEN', '8449840888:AAGORGf40dX8Biu6IRkOlVDEgE3UF7SmS3I');
define('TELEGRAM_CHAT_ID', '8422288650');

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set timezone
date_default_timezone_set("Asia/Bangkok");
?>