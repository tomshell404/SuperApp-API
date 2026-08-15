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
$amount = isset($_GET['amount']) ? floatval($_GET['amount']) : 0;

// Validate input
if (empty($username)) {
    echo json_encode([
        "status" => "error",
        "message" => "Username is required"
    ]);
    $conn->close();
    exit();
}

// Query to get user's current balance
$query = "SELECT balance FROM user_records WHERE username = ?";
$stmt = $conn->prepare($query);

if (!$stmt) {
    echo json_encode([
        "status" => "error",
        "message" => "Failed to prepare query: " . $conn->error
    ]);
    $conn->close();
    exit();
}

$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $balance = floatval($row['balance']);
    
    echo json_encode([
        "status" => "success",
        "message" => "Balance retrieved successfully",
        "data" => [
            "username" => $username,
            "balance" => number_format($balance, 2, '.', ''),
            "requested_amount" => number_format($amount, 2, '.', ''),
            "has_sufficient_balance" => ($balance >= $amount) ? true : false
        ]
    ]);
} else {
    echo json_encode([
        "status" => "error",
        "message" => "User not found: " . $username
    ]);
}

$stmt->close();
$conn->close();
?>