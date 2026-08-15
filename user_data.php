<?php

require_once __DIR__ . '/db.php';
header('Content-Type: application/json; charset=utf-8');

// Database configuration
// Create connection

// Check connection
if ($conn->connect_error) {
    echo json_encode([
        "status" => "error",
        "message" => "Database connection failed: " . $conn->connect_error
    ]);
    exit();
}

// Create users table if not exists
$createTableSQL = "CREATE TABLE IF NOT EXISTS user_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    pin VARCHAR(255) NOT NULL,
    deviceId VARCHAR(255) DEFAULT NULL,
    balance DECIMAL(15,2) DEFAULT 0.00,
    reward DECIMAL(15,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if (!$conn->query($createTableSQL)) {
    echo json_encode([
        "status" => "error",
        "message" => "Failed to create table: " . $conn->error
    ]);
    $conn->close();
    exit();
}

// Add deviceId column if it doesn't exist (for existing tables)
$conn->query("ALTER TABLE user_records ADD COLUMN IF NOT EXISTS deviceId VARCHAR(255) DEFAULT NULL");

// Get parameters
$username = isset($_GET['username']) ? trim($_GET['username']) : '';
$pin = isset($_GET['pin']) ? trim($_GET['pin']) : '';
$deviceId = isset($_GET['deviceId']) ? trim($_GET['deviceId']) : '';

// Validate input
if (empty($username) || empty($pin)) {
    echo json_encode([
        "status" => "error",
        "message" => "Username and PIN are required"
    ]);
    $conn->close();
    exit();
}

// Check if user exists
$checkSQL = "SELECT id, username, balance, reward FROM user_records WHERE username = ?";
$stmt = $conn->prepare($checkSQL);
if (!$stmt) {
    echo json_encode([
        "status" => "error",
        "message" => "Database prepare failed: " . $conn->error
    ]);
    $conn->close();
    exit();
}

$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    // User exists - update PIN and deviceId
    $updateSQL = "UPDATE user_records SET pin = ?, deviceId = ? WHERE username = ?";
    $updateStmt = $conn->prepare($updateSQL);
    if ($updateStmt) {
        $updateStmt->bind_param("sss", $pin, $deviceId, $username);
        $updateStmt->execute();
        $updateStmt->close();
    }
    
    echo json_encode([
        "status" => "success",
        "message" => "PIN and Device ID updated successfully",
        "data" => [
            "id" => $row['id'],
            "username" => $row['username'],
            "deviceId" => $deviceId,
            "balance" => number_format(floatval($row['balance']), 2, '.', ''),
            "reward" => number_format(floatval($row['reward']), 2, '.', '')
        ]
    ]);
} else {
    // User doesn't exist - insert new record with deviceId
    $insertSQL = "INSERT INTO user_records (username, pin, deviceId) VALUES (?, ?, ?)";
    $insertStmt = $conn->prepare($insertSQL);
    if (!$insertStmt) {
        echo json_encode([
            "status" => "error",
            "message" => "Failed to prepare insert: " . $conn->error
        ]);
        $conn->close();
        exit();
    }
    
    $insertStmt->bind_param("sss", $username, $pin, $deviceId);
    
    if ($insertStmt->execute()) {
        $newId = $conn->insert_id;
        echo json_encode([
            "status" => "success",
            "message" => "User created successfully with Device ID",
            "data" => [
                "id" => $newId,
                "username" => $username,
                "deviceId" => $deviceId,
                "balance" => "0.00",
                "reward" => "0.00"
            ]
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "Failed to create user: " . $insertStmt->error
        ]);
    }
    $insertStmt->close();
}

$stmt->close();
$conn->close();
?>