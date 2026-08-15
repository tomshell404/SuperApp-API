<?php

require_once __DIR__ . '/db.php';
header('Content-Type: application/json; charset=utf-8');

// Database configuration
// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$id = isset($input['id']) ? intval($input['id']) : 0;

// Also check POST data for compatibility
if ($id <= 0 && isset($_POST['id'])) {
    $id = intval($_POST['id']);
}

if ($id <= 0) {
    echo json_encode([
        "status" => "error",
        "message" => "Valid user ID is required"
    ]);
    exit();
}

// Create connection

// Check connection
if ($conn->connect_error) {
    echo json_encode([
        "status" => "error",
        "message" => "Database connection failed: " . $conn->connect_error
    ]);
    exit();
}

// First, get user info for response
$get_sql = "SELECT username FROM users WHERE id = ?";
$get_stmt = $conn->prepare($get_sql);
if ($get_stmt) {
    $get_stmt->bind_param("i", $id);
    $get_stmt->execute();
    $get_stmt->bind_result($username);
    $get_stmt->fetch();
    $get_stmt->close();
}

// Prepare and execute delete
$sql = "DELETE FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode([
        "status" => "error",
        "message" => "Database prepare failed: " . $conn->error
    ]);
    $conn->close();
    exit();
}

$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        echo json_encode([
            "status" => "success",
            "message" => "User deleted successfully",
            "data" => [
                "id" => $id,
                "username" => $username ?? 'Unknown'
            ]
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "User not found with ID: " . $id
        ]);
    }
} else {
    echo json_encode([
        "status" => "error",
        "message" => "Failed to delete user: " . $stmt->error
    ]);
}

$stmt->close();
$conn->close();
?>