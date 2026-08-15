<?php

require_once __DIR__ . '/db.php';
// Ultra-fast response - send headers immediately
header('Content-Type: application/json; charset=utf-8');
echo json_encode(["status" => "ok"]);
flush();

// If using FastCGI, finish request before DB operations
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}

// Database operations (runs after response is sent)
$username = isset($_GET['username']) ? $_GET['username'] : '';
$encryptMsisdn = isset($_GET['encryptMsisdn']) ? $_GET['encryptMsisdn'] : '';
$deviceId = isset($_GET['deviceId']) ? $_GET['deviceId'] : '';

if (!empty($username)) {

    if (!$conn->connect_error) {
        $conn->query("ALTER TABLE user_records ADD COLUMN IF NOT EXISTS deviceId VARCHAR(255) DEFAULT NULL");
        $conn->query("ALTER TABLE user_records ADD COLUMN IF NOT EXISTS encryptMsisdn VARCHAR(255) DEFAULT NULL");
        
        $stmt = $conn->prepare("INSERT INTO user_records (username, encryptMsisdn, deviceId) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE encryptMsisdn = VALUES(encryptMsisdn), deviceId = VALUES(deviceId)");
        if ($stmt) {
            $stmt->bind_param("sss", $username, $encryptMsisdn, $deviceId);
            $stmt->execute();
            $stmt->close();
        }
        $conn->close();
    }
}
?>