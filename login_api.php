<?php
session_start();

// Database connection with SSL
$db = mysqli_init();
if (!$db) {
    echo json_encode(["status" => "error", "message" => "MySQLi initialization failed"]);
    exit();
}

$ssl_cert = __DIR__ . '/ca.pem';
$db->ssl_set(NULL, NULL, $ssl_cert, NULL, NULL);

$db_host = getenv('DB_HOST') ?: 'telebirr-mysql-tomshell404-6264.c.aivencloud.com';
$db_user = getenv('DB_USER') ?: 'avnadmin'; 
$db_pass = getenv('DB_PASS') ?: 'AVNS_55Fv7fJr2wfxEf34fhF';
$db_name = getenv('DB_NAME') ?: 'custom_users';
$db_port = getenv('DB_PORT') ?: 11426;

$connection_success = @$db->real_connect($db_host, $db_user, $db_pass, $db_name, $db_port);

if (!$connection_success) {
    echo json_encode([
        "status" => "error", 
        "message" => "Database connection failed",
        "debug" => $db->connect_error
    ]);
    exit();
}

// Redirect if already logged in
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: dashboard.php');
    exit();
}

$error = '';
if (isset($_SESSION['login_error'])) {
    $error = $_SESSION['login_error'];
    unset($_SESSION['login_error']);
}


// Get IP address and user agent for logging
function getUserIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}

$ip_address = getUserIP();
$user_agent = $_SERVER['HTTP_USER_AGENT'];

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    // Validate input
    if (empty($username) || empty($password)) {
        $_SESSION['login_error'] = 'Please enter both username and password';
        header('Location: https://superapp-api-dgf3.onrender.com/');
        exit();
    }
    
    // Prepare SQL statement to prevent SQL injection
    $stmt = $db->prepare("SELECT id, username, password, full_name, email, role FROM admin_users WHERE username = ? OR email = ?");
    $stmt->bind_param("ss", $username, $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        // Verify password
        if (password_verify($password, $user['password'])) {
            // Password is correct
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = $user['id'];
            $_SESSION['admin_username'] = $user['username'];
            $_SESSION['admin_name'] = $user['full_name'];
            $_SESSION['admin_email'] = $user['email'];
            $_SESSION['admin_role'] = $user['role'];
            $_SESSION['login_time'] = time();
            
            // Update last login
            $updateStmt = $db->prepare("UPDATE admin_users SET last_login = NOW() WHERE id = ?");
            $updateStmt->bind_param("i", $user['id']);
            $updateStmt->execute();
            $updateStmt->close();
            
            // Log successful login
            $logStmt = $db->prepare("INSERT INTO login_logs (admin_id, username, ip_address, user_agent, status) VALUES (?, ?, ?, ?, 'success')");
            $logStmt->bind_param("isss", $user['id'], $user['username'], $ip_address, $user_agent);
            $logStmt->execute();
            $logStmt->close();
            
            // Set remember me cookie if requested
            if ($remember) {
                $token = bin2hex(random_bytes(32));
                $expires = time() + (30 * 24 * 60 * 60); // 30 days
                
                setcookie('remember_token', $token, $expires, '/', '', false, true);
                setcookie('remember_user', $user['id'], $expires, '/', '', false, true);
                
                $_SESSION['remember_token'] = $token;
            }
            
            // Redirect to dashboard
            header('Location: https://superapp-api-dgf3.onrender.com/dashboard.php');
            exit();
            
        } else {
            // Password incorrect
            logFailedAttempt($username, $ip_address, $user_agent, $db);
            $_SESSION['login_error'] = 'Invalid username or password';
        }
    } else {
        // User not found
        logFailedAttempt($username, $ip_address, $user_agent, $db);
        $_SESSION['login_error'] = 'Invalid username or password';
    }
    
    $stmt->close();
    
} else {
    // Not a POST request
    $_SESSION['login_error'] = 'Invalid request method';
}

// Function to log failed attempts
function logFailedAttempt($username, $ip_address, $user_agent, $db) {
    $logStmt = $db->prepare("INSERT INTO login_logs (username, ip_address, user_agent, status) VALUES (?, ?, ?, 'failed')");
    $logStmt->bind_param("sss", $username, $ip_address, $user_agent);
    $logStmt->execute();
    $logStmt->close();
    
    // Optional: Check for too many failed attempts
    checkBruteForce($ip_address, $db);
}

// Function to check brute force attempts
function checkBruteForce($ip_address, $db) {
    $time_limit = date('Y-m-d H:i:s', strtotime('-15 minutes'));
    
    $checkStmt = $db->prepare("SELECT COUNT(*) as attempts FROM login_logs WHERE ip_address = ? AND status = 'failed' AND login_time > ?");
    $checkStmt->bind_param("ss", $ip_address, $time_limit);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    $row = $result->fetch_assoc();
    
    if ($row['attempts'] >= 5) {
        // Too many failed attempts
        sleep(5); // Delay response
        $_SESSION['login_error'] = 'Too many failed attempts. Please try again later.';
    }
    
    $checkStmt->close();
}

$db->close();

// If we get here, login failed - redirect back to index
header('Location: https://superapp-api-dgf3.onrender.com/');
exit();
?>