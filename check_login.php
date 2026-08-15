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


echo "<h2>Login System Diagnostic</h2>";

// Check if admin_users table exists
$table_check = $db->query("SHOW TABLES LIKE 'admin_users'");
if ($table_check->num_rows == 0) {
    echo "<p style='color: red;'>❌ admin_users table does not exist!</p>";
    echo "<p>Creating admin_users table...</p>";
    
    // Create the table
    $sql = "CREATE TABLE IF NOT EXISTS admin_users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        full_name VARCHAR(100),
        role ENUM('admin', 'super_admin') DEFAULT 'admin',
        last_login DATETIME,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    if ($db->query($sql) === TRUE) {
        echo "<p style='color: green;'>✅ admin_users table created successfully!</p>";
    } else {
        echo "<p style='color: red;'>❌ Error creating table: " . $db->error . "</p>";
    }
    
    // Create login_logs table
    $sql2 = "CREATE TABLE IF NOT EXISTS login_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        admin_id INT,
        username VARCHAR(50),
        ip_address VARCHAR(45),
        user_agent TEXT,
        status ENUM('success', 'failed') DEFAULT 'failed',
        login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (admin_id) REFERENCES admin_users(id) ON DELETE SET NULL
    )";
    
    if ($db->query($sql2) === TRUE) {
        echo "<p style='color: green;'>✅ login_logs table created successfully!</p>";
    } else {
        echo "<p style='color: red;'>❌ Error creating login_logs table: " . $db->error . "</p>";
    }
} else {
    echo "<p style='color: green;'>✅ admin_users table exists</p>";
}

// Check if admin user exists
$user_check = $db->query("SELECT * FROM admin_users WHERE username = 'admin'");
if ($user_check->num_rows == 0) {
    echo "<p style='color: red;'>❌ Admin user does not exist!</p>";
    echo "<p>Creating admin user...</p>";
    
    // Create admin user with password 'admin123'
    $password = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $db->prepare("INSERT INTO admin_users (username, password, email, full_name, role) VALUES (?, ?, ?, ?, ?)");
    $username = 'admin';
    $email = 'admin@example.com';
    $full_name = 'Administrator';
    $role = 'super_admin';
    $stmt->bind_param("sssss", $username, $password, $email, $full_name, $role);
    
    if ($stmt->execute()) {
        echo "<p style='color: green;'>✅ Admin user created successfully!</p>";
        echo "<p>Username: <strong>admin</strong></p>";
        echo "<p>Password: <strong>admin123</strong></p>";
    } else {
        echo "<p style='color: red;'>❌ Error creating admin user: " . $stmt->error . "</p>";
    }
    $stmt->close();
} else {
    $user = $user_check->fetch_assoc();
    echo "<p style='color: green;'>✅ Admin user exists</p>";
    echo "<p>Username: <strong>" . $user['username'] . "</strong></p>";
    echo "<p>Email: " . $user['email'] . "</p>";
    echo "<p>Role: " . $user['role'] . "</p>";
    
    // Verify password (optional - to test if password is correct)
    if (password_verify('admin123', $user['password'])) {
        echo "<p style='color: green;'>✅ Password 'admin123' is correct for this user!</p>";
    } else {
        echo "<p style='color: red;'>❌ Password 'admin123' is NOT correct for this user!</p>";
        echo "<p>Updating password to 'admin123'...</p>";
        
        // Update password
        $new_password = password_hash('admin123', PASSWORD_DEFAULT);
        $update = $db->prepare("UPDATE admin_users SET password = ? WHERE id = ?");
        $update->bind_param("si", $new_password, $user['id']);
        if ($update->execute()) {
            echo "<p style='color: green;'>✅ Password updated successfully to 'admin123'</p>";
        } else {
            echo "<p style='color: red;'>❌ Error updating password: " . $update->error . "</p>";
        }
        $update->close();
    }
}

// Show all admin users
echo "<h3>Current Admin Users:</h3>";
$all_users = $db->query("SELECT id, username, email, role, created_at FROM admin_users");
if ($all_users->num_rows > 0) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th><th>Created</th></tr>";
    while ($user = $all_users->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $user['id'] . "</td>";
        echo "<td>" . $user['username'] . "</td>";
        echo "<td>" . $user['email'] . "</td>";
        echo "<td>" . $user['role'] . "</td>";
        echo "<td>" . $user['created_at'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No admin users found.</p>";
}

$db->close();
echo "<p><a href='login.php'>Go to Login Page</a></p>";
?>