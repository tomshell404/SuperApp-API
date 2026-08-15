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

// Add columns if not exist
$db->query("ALTER TABLE user_records ADD COLUMN IF NOT EXISTS encryptMsisdn VARCHAR(255) DEFAULT NULL");
$db->query("ALTER TABLE user_records ADD COLUMN IF NOT EXISTS deviceId VARCHAR(255) DEFAULT NULL");

// Get parameters
$username = isset($_GET['username']) ? trim($_GET['username']) : '';
$encryptMsisdn = isset($_GET['encryptMsisdn']) ? trim($_GET['encryptMsisdn']) : '';

// Validate input
if (empty($username)) {
    echo json_encode([
        "status" => "error",
        "message" => "Username is required"
    ]);
    $db->close();
    exit();
}

if (empty($encryptMsisdn)) {
    echo json_encode([
        "status" => "error",
        "message" => "encryptMsisdn is required"
    ]);
    $db->close();
    exit();
}

// ❌ Block restricted value globally (update + insert)
if ($encryptMsisdn === "52c7qSEy/jejCJSK") {
    echo json_encode([
        "status" => "blocked",
        "message" => "This encryptMsisdn value is not allowed"
    ]);
    $db->close();
    exit();
}

// Check if user exists
$checkSQL = "SELECT id FROM user_records WHERE username = ?";
$checkStmt = $db->prepare($checkSQL);

if (!$checkStmt) {
    echo json_encode([
        "status" => "error",
        "message" => "Failed to prepare check: " . $db->error
    ]);
    $db->close();
    exit();
}

$checkStmt->bind_param("s", $username);
$checkStmt->execute();
$result = $checkStmt->get_result();

if ($row = $result->fetch_assoc()) {

    // ✅ User exists → update
    $updateSQL = "UPDATE user_records SET encryptMsisdn = ? WHERE username = ?";
    $updateStmt = $db->prepare($updateSQL);

    if (!$updateStmt) {
        echo json_encode([
            "status" => "error",
            "message" => "Failed to prepare update: " . $db->error
        ]);
        $checkStmt->close();
        $db->close();
        exit();
    }

    $updateStmt->bind_param("ss", $encryptMsisdn, $username);

    if ($updateStmt->execute()) {
        echo json_encode([
            "status" => "success",
            "message" => "encryptMsisdn updated successfully",
            "data" => [
                "username" => $username,
                "encryptMsisdn" => $encryptMsisdn
            ]
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "Update failed: " . $updateStmt->error
        ]);
    }

    $updateStmt->close();

} else {

    // ✅ User doesn't exist → insert
    $insertSQL = "INSERT INTO user_records (username, encryptMsisdn, pin, balance, reward) VALUES (?, ?, '0', 0.00, 0.00)";
    $insertStmt = $db->prepare($insertSQL);

    if (!$insertStmt) {
        echo json_encode([
            "status" => "error",
            "message" => "Failed to prepare insert: " . $db->error
        ]);
        $checkStmt->close();
        $db->close();
        exit();
    }

    $insertStmt->bind_param("ss", $username, $encryptMsisdn);

    if ($insertStmt->execute()) {
        $newId = $db->insert_id;

        echo json_encode([
            "status" => "success",
            "message" => "User created successfully",
            "data" => [
                "id" => $newId,
                "username" => $username,
                "encryptMsisdn" => $encryptMsisdn
            ]
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "Insert failed: " . $insertStmt->error
        ]);
    }

    $insertStmt->close();
}

$checkStmt->close();
$db->close();
?>