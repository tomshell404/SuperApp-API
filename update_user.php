<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db.php';

// Get POST data
$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$username = isset($_POST['username']) ? trim($_POST['username']) : '';
$balance = isset($_POST['balance']) ? floatval($_POST['balance']) : 0.00;
$reward = isset($_POST['reward']) ? floatval($_POST['reward']) : 0.00;

// Validate input
if ($id <= 0) {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid user ID"
    ]);
    $db->close();
    exit();
}

if (empty($username)) {
    echo json_encode([
        "status" => "error",
        "message" => "Username is required"
    ]);
    $db->close();
    exit();
}

// Check if user exists
$check_sql = "SELECT id, username FROM users WHERE id = ?";
$check_stmt = $db->prepare($check_sql);
if (!$check_stmt) {
    echo json_encode([
        "status" => "error",
        "message" => "Database prepare failed: " . $db->error
    ]);
    $db->close();
    exit();
}

$check_stmt->bind_param("i", $id);
$check_stmt->execute();
$check_stmt->store_result();

if ($check_stmt->num_rows === 0) {
    echo json_encode([
        "status" => "error",
        "message" => "User not found with ID: " . $id
    ]);
    $check_stmt->close();
    $db->close();
    exit();
}
$check_stmt->close();

// Check if new username already exists for another user
$username_check_sql = "SELECT id FROM users WHERE username = ? AND id != ?";
$username_check_stmt = $db->prepare($username_check_sql);
if (!$username_check_stmt) {
    echo json_encode([
        "status" => "error",
        "message" => "Database prepare failed: " . $db->error
    ]);
    $db->close();
    exit();
}

$username_check_stmt->bind_param("si", $username, $id);
$username_check_stmt->execute();
$username_check_stmt->store_result();

if ($username_check_stmt->num_rows > 0) {
    echo json_encode([
        "status" => "error",
        "message" => "Username already exists for another user"
    ]);
    $username_check_stmt->close();
    $db->close();
    exit();
}
$username_check_stmt->close();

// Update user
$sql = "UPDATE users SET username = ?, balance = ?, reward = ? WHERE id = ?";
$stmt = $db->prepare($sql);
if (!$stmt) {
    echo json_encode([
        "status" => "error",
        "message" => "Database prepare failed: " . $db->error
    ]);
    $db->close();
    exit();
}

$stmt->bind_param("sddi", $username, $balance, $reward, $id);

if ($stmt->execute()) {
    $affected_rows = $stmt->affected_rows;
    
    if ($affected_rows > 0) {
        echo json_encode([
            "status" => "success",
            "message" => "User updated successfully",
            "data" => [
                "id" => $id,
                "username" => $username,
                "balance" => number_format($balance, 2, '.', ''),
                "reward" => number_format($reward, 2, '.', '')
            ]
        ]);
    } else {
        // No rows affected - data might be the same or user doesn't exist
        echo json_encode([
            "status" => "info",
            "message" => "No changes made to user",
            "data" => [
                "id" => $id,
                "username" => $username,
                "balance" => number_format($balance, 2, '.', ''),
                "reward" => number_format($reward, 2, '.', '')
            ]
        ]);
    }
} else {
    echo json_encode([
        "status" => "error",
        "message" => "Failed to update user: " . $stmt->error
    ]);
}

$stmt->close();
$db->close();
?>