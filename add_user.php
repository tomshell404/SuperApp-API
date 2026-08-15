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

// Get POST data
$username = isset($_POST['username']) ? trim($_POST['username']) : '';
$balance = isset($_POST['balance']) ? floatval($_POST['balance']) : 0.00;
$reward = isset($_POST['reward']) ? floatval($_POST['reward']) : 0.00;

// Validate input
if (empty($username)) {
    echo json_encode([
        "status" => "error",
        "message" => "Username is required"
    ]);
    $conn->close();
    exit();
}

// Check if username already exists
$check_sql = "SELECT id FROM users WHERE username = ?";
$check_stmt = $conn->prepare($check_sql);
if (!$check_stmt) {
    echo json_encode([
        "status" => "error",
        "message" => "Database prepare failed: " . $conn->error
    ]);
    $conn->close();
    exit();
}

$check_stmt->bind_param("s", $username);
$check_stmt->execute();
$check_stmt->store_result();

if ($check_stmt->num_rows > 0) {
    echo json_encode([
        "status" => "error",
        "message" => "Username already exists"
    ]);
    $check_stmt->close();
    $conn->close();
    exit();
}
$check_stmt->close();

// Insert new user
$sql = "INSERT INTO users (username, balance, reward, created_at) VALUES (?, ?, ?, NOW())";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode([
        "status" => "error",
        "message" => "Database prepare failed: " . $conn->error
    ]);
    $conn->close();
    exit();
}

$stmt->bind_param("sdd", $username, $balance, $reward);

if ($stmt->execute()) {
    $new_user_id = $conn->insert_id;
    
    echo json_encode([
        "status" => "success",
        "message" => "User added successfully",
        "data" => [
            "id" => $new_user_id,
            "username" => $username,
            "balance" => number_format($balance, 2, '.', ''),
            "reward" => number_format($reward, 2, '.', ''),
            "created_at" => date('Y-m-d H:i:s')
        ]
    ]);
} else {
    echo json_encode([
        "status" => "error",
        "message" => "Failed to add user: " . $stmt->error
    ]);
}

$stmt->close();
$conn->close();
?>