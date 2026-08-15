<?php

require_once __DIR__ . '/db.php';
session_start();

// Database configuration
// Optional: Log logout time
if (isset($_SESSION['admin_id'])) {

    if (!$conn->connect_error) {
        // You might want to add a last_logout column to admin_users table
        // For now, we'll just log to login_logs if you want
        $stmt = $conn->prepare("INSERT INTO login_logs (admin_id, username, ip_address, status) VALUES (?, ?, ?, 'logout')");
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

// Redirect to the specified URL (your index page)
header('Location: http://telebirr.duckdns.org:8090/telebirr/');
exit();
?>