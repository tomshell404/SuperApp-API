<?php
header('Content-Type: application/json; charset=utf-8');

// Database connection with SSL
$db = mysqli_init();
if (!$db) {
    echo json_encode(["status" => "error", "message" => "MySQLi initialization failed"]);
    exit();
}

$ssl_cert = __DIR__ . '/ca.pem';
$db->ssl_set(NULL, NULL, $ssl_cert, NULL, NULL);

$db_host = getenv('DB_HOST') ?: 'telebirr-mysql-tomshell404-6264.c.aivencloud.com';
$db_user = getenv('DB_USER') ?: 'avnadmin'; 
$db_pass = getenv('DB_PASS') ?: 'AVNS_55Fv7fJr2wfxEf34fhF';
$db_name = getenv('DB_NAME') ?: 'custom_users';
$db_port = getenv('DB_PORT') ?: 11426;

$connection_success = @$db->real_connect($db_host, $db_user, $db_pass, $db_name, $db_port);

if (!$connection_success) {
    echo json_encode([
        "status" => "error", 
        "message" => "Database connection failed",
        "debug" => $db->connect_error
    ]);
    exit();
}

// Modified query to include reward field (assuming it's in the users table)
$stmt = $db->prepare("SELECT balance, reward FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$stmt->bind_result($balance, $reward);

if ($stmt->fetch()) {
    // Check if reward exists, if not default to 0.00
    $reward_value = isset($reward) ? $reward : 0.00;
    
    echo json_encode([
        "status" => "ok",
        "balance" => number_format((float)$balance, 2, '.', ''),
        "reward" => number_format((float)$reward_value, 2, '.', '')
    ]);
} else {
    // User not found, return default values
    echo json_encode([
        "status" => "ok",
        "balance" => "0.00",
        "reward" => "0.00"
    ]);
}

$stmt->close();
$db->close();
?>