<?php
// logout.php - Destroy session and redirect to login
session_start();

// Clear all session variables
$_SESSION = array();

// Destroy the session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-3600, '/');
}

// Destroy the session
session_destroy();

// Redirect to login page with full URL
header('Location: https://superapp-api-dgf3.onrender.com/login.php');
exit();
?>