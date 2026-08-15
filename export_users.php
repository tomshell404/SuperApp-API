<?php

require_once __DIR__ . '/db.php';
// export_users.php - Export user records to CSV with proper formatting
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get search parameter
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';

// Build WHERE clause for search
$where_clause = "";
if (!empty($search)) {
    $where_clause = "WHERE username LIKE '%$search%' OR deviceId LIKE '%$search%' OR encryptMsisdn LIKE '%$search%'";
}

// Fetch all records
$sql = "SELECT id, username, pin, balance, reward, deviceId, encryptMsisdn, created_at, updated_at 
        FROM user_records 
        $where_clause 
        ORDER BY id DESC";
$result = $conn->query($sql);

if (!$result) {
    die("Query failed: " . $conn->error);
}

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="user_records_' . date('Y-m-d_H-i-s') . '.csv"');
header('Pragma: no-cache');
header('Expires: 0');

// Create output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM for Excel compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Add headers
fputcsv($output, [
    'ID', 
    'Username', 
    'PIN', 
    'Balance (ETB)', 
    'Reward (ETB)', 
    'Device ID', 
    'Encrypted MSISDN', 
    'Created At', 
    'Updated At'
]);

// Add data rows with proper formatting to prevent scientific notation
while ($row = $result->fetch_assoc()) {
    // Format username as text to prevent scientific notation in Excel
    $username = "=\"" . $row['username'] . "\"";
    
    fputcsv($output, [
        $row['id'],
        $username,  // Prevents Excel from converting to scientific notation
        $row['pin'],
        number_format($row['balance'], 2, '.', ''),
        number_format($row['reward'], 2, '.', ''),
        $row['deviceId'] ? $row['deviceId'] : '',
        $row['encryptMsisdn'] ? $row['encryptMsisdn'] : '',
        date('Y-m-d H:i:s', strtotime($row['created_at'])),
        date('Y-m-d H:i:s', strtotime($row['updated_at']))
    ]);
}

fclose($output);
$conn->close();
exit();
?>