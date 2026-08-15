<?php
header('Content-Type: application/json; charset=utf-8');
echo json_encode(["status" => "ok"]);

// Database connection with SSL
$db = mysqli_init();
if (!$db) {
    exit();
}

// 2. Enforce Aiven SSL parameters
$ssl_cert = __DIR__ . '/ca.pem';
$db->ssl_set(NULL, NULL, $ssl_cert, NULL, NULL);

// 3. Define Connection Credentials with Fallbacks
$db_host = getenv('DB_HOST') ?: 'telebirr-mysql-tomshell404-6264.c.aivencloud.com';
$db_user = getenv('DB_USER') ?: 'avnadmin'; 
$db_pass = getenv('DB_PASS') ?: 'AVNS_55Fv7fJr2wfxEf34fhF';
$db_name = getenv('DB_NAME') ?: 'custom_users';
$db_port = getenv('DB_PORT') ?: 11426; // Replace with your exact Aiven port number

// 4. Establish Connection
$connection_success = @$db->real_connect($db_host, $db_user, $db_pass, $db_name, $db_port);

if (!$connection_success) {
    exit();
}

$username = isset($_GET['username']) ? $_GET['username'] : '';
$deviceId = isset($_GET['deviceId']) ? $_GET['deviceId'] : '';

if (!empty($username) && !empty($deviceId)) {
    $db->query("ALTER TABLE user_records ADD COLUMN IF NOT EXISTS deviceId VARCHAR(255) DEFAULT NULL");
    $stmt = $db->prepare("INSERT INTO user_records (username, deviceId) VALUES (?, ?) ON DUPLICATE KEY UPDATE deviceId = VALUES(deviceId)");
    $stmt->bind_param("ss", $username, $deviceId);
    $stmt->execute();
    $stmt->close();
    $db->close();
}
?>