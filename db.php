<?php
// Shared Aiven SSL database connection for this project.
// Each PHP API endpoint that needs the database should do:
//     require_once __DIR__ . '/db.php';
// After inclusion, both $db and the backward-compatible alias $conn are available.

header("Access-Control-Allow-Origin: *");

// 1. Initialize MySQLi
$db = mysqli_init();
if (!$db) {
    echo json_encode(["status" => "error", "message" => "MySQLi initialization failed"]);
    exit();
}

// 2. Enforce Aiven SSL parameters
$ssl_cert = __DIR__ . '/ca.pem';
$db->ssl_set(NULL, NULL, $ssl_cert, NULL, NULL);

// 3. Define Connection Credentials with Fallbacks
$db_host = getenv('DB_HOST') ?: 'telebirr-mysql-tomshell404-6264.c.aivencloud.com';
$db_user = getenv('DB_USER') ?: 'avnadmin'; 
$db_pass = getenv('DB_PASS') ?: 'AVNS_55Fv7fJr2wfxEf34fhF';
$db_name = getenv('DB_NAME') ?: 'custom_users';
$db_port = getenv('DB_PORT') ?: 11426; // Replace with your exact Aiven port number

// 4. Establish Connection
$connection_success = @$db->real_connect($db_host, $db_user, $db_pass, $db_name, $db_port);

if (!$connection_success) {
    echo json_encode([
        "status" => "error", 
        "message" => "Database connection failed",
        "debug" => $db->connect_error
    ]);
    exit();
}

// Backward-compatible alias so existing endpoints using $conn keep working
$conn = $db;