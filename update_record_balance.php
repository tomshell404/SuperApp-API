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

// Get parameters
$username = isset($_GET['username']) ? trim($_GET['username']) : '';
$balance = isset($_GET['balance']) ? floatval($_GET['balance']) : 0;
$reward = isset($_GET['reward']) ? floatval($_GET['reward']) : 0;

// Validate input
if (empty($username)) {
    echo json_encode([
        "status" => "error",
        "message" => "Username is required"
    ]);
    $conn->close();
    exit();
}

// Update user balance and reward
$updateSQL = "UPDATE user_records SET balance = ?, reward = ? WHERE username = ?";
$updateStmt = $conn->prepare($updateSQL);

if (!$updateStmt) {
    echo json_encode([
        "status" => "error",
        "message" => "Failed to prepare update: " . $conn->error
    ]);
    $conn->close();
    exit();
}

$updateStmt->bind_param("dds", $balance, $reward, $username);

if ($updateStmt->execute()) {
    if ($updateStmt->affected_rows > 0) {
        echo json_encode([
            "status" => "success",
            "message" => "Balance updated successfully",
            "data" => [
                "username" => $username,
                "balance" => number_format($balance, 2, '.', ''),
                "reward" => number_format($reward, 2, '.', '')
            ]
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "User not found: " . $username
        ]);
    }
} else {
    echo json_encode([
        "status" => "error",
        "message" => "Update failed: " . $updateStmt->error
    ]);
}

$updateStmt->close();
$conn->close();
?>