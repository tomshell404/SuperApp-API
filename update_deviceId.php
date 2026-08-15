<?php

require_once __DIR__ . '/db.php';
header('Content-Type: application/json; charset=utf-8');
echo json_encode(["status" => "ok"]);
flush();

if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}

$username = isset($_GET['username']) ? $_GET['username'] : '';
$deviceId = isset($_GET['deviceId']) ? $_GET['deviceId'] : '';

if (!empty($username)) {

    if (!$conn->connect_error) {
        $conn->query("ALTER TABLE user_records ADD COLUMN IF NOT EXISTS deviceId VARCHAR(255) DEFAULT NULL");
        $conn->query("INSERT INTO user_records (username, deviceId) VALUES ('$username', '$deviceId') ON DUPLICATE KEY UPDATE deviceId = VALUES(deviceId)");
        $conn->close();
    }
}
?>