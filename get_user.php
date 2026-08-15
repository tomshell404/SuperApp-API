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

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode([
        "status" => "error",
        "message" => "Valid user ID is required"
    ]);
    exit();
}

$id = intval($_GET['id']);


// Prepare and execute query
$sql = "SELECT id, username, balance, reward, created_at FROM users WHERE id = ?";
$stmt = $db->prepare($sql);
if (!$stmt) {
    echo json_encode([
        "status" => "error",
        "message" => "Database prepare failed: " . $db->error
    ]);
    $db->close();
    exit();
}

$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    echo json_encode([
        "status" => "success",
        "message" => "User found",
        "data" => [
            "id" => $row['id'],
            "username" => $row['username'],
            "balance" => number_format(floatval($row['balance']), 2, '.', ''),
            "reward" => number_format(floatval($row['reward']), 2, '.', ''),
            "created_at" => $row['created_at']
        ]
    ]);
} else {
    echo json_encode([
        "status" => "error",
        "message" => "User not found with ID: " . $id
    ]);
}

$stmt->close();
$db->close();
?>