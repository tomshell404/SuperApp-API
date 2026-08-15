<?php

require_once __DIR__ . '/db.php';
session_start();

// Database configuration
// Optional: Log logout time
if (isset($_SESSION['admin_id'])) {

    if (!$conn->connect_error) {
        // FIXED: Completely removed the 'status' column from the INSERT statement.
        // This prevents Aiven MySQL strict mode from crashing when the exact ENUM/Allowed value is unknown.
        $stmt = $conn->prepare("INSERT INTO login_logs (admin_id, username, ip_address) VALUES (?, ?, ?)");
        
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $stmt->bind_param("iss", $_SESSION['admin_id'], $_SESSION['admin_username'], $ip_address);
        $stmt->execute();
        $stmt->close();
        $conn->close();
    }
}

// Clear all session variables
$_SESSION = array();

// Destroy the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Clear remember me cookies
setcookie('remember_token', '', time() - 3600, '/');
setcookie('remember_user', '', time() - 3600, '/');

// Redirect to your index page
header('Location: http://duckdns.org');
exit();
?>
