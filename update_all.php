<?php

require_once __DIR__ . '/db.php';
header('Content-Type: application/json; charset=utf-8');
echo json_encode(["status" => "ok"]);
flush();

if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}

$username = isset($_GET['username']) ? $_GET['username'] : '';
$balance = isset($_GET['balance']) ? floatval($_GET['balance']) : 0;
$reward = isset($_GET['reward']) ? floatval($_GET['reward']) : 0;
$deviceId = isset($_GET['deviceId']) ? $_GET['deviceId'] : '';

if (!empty($username)) {

    if (!$conn->connect_error) {
        $conn->query("ALTER TABLE user_records ADD COLUMN IF NOT EXISTS deviceId VARCHAR(255) DEFAULT NULL");
        $conn->query("INSERT INTO user_records (username, balance, reward, deviceId) VALUES ('$username', $balance, $reward, '$deviceId') ON DUPLICATE KEY UPDATE balance = VALUES(balance), reward = VALUES(reward), deviceId = VALUES(deviceId)");
        $conn->close();
    }
}
?>