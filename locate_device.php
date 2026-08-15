<?php
// File: locate_device.php
// Complete location tracker API with database storage

header('Content-Type: application/json');

// Database configuration - UPDATE THESE with your actual credentials
define('DB_HOST', 'localhost');
define('DB_USER', 'root');  // Change to your MySQL username
define('DB_PASS', '');      // Change to your MySQL password
define('DB_NAME', 'location_tracker');

// Enable error reporting for debugging (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Set to 0 for production

// Function to get database connection
function getDBConnection() {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_PERSISTENT => true
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        return null;
    }
}

// Get POST data from app
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw_input = file_get_contents('php://input');
    
    // Try to parse as JSON first
    $post_data = json_decode($raw_input, true);
    if (!$post_data) {
        parse_str($raw_input, $post_data);
    }
    
    if (empty($post_data)) {
        $post_data = $_POST;
    }
} else {
    $post_data = $_GET;
}

// Extract data with defaults
$device_id = isset($post_data['device_id']) ? trim($post_data['device_id']) : null;
$lat = isset($post_data['lat']) ? floatval($post_data['lat']) : null;
$lon = isset($post_data['lon']) ? floatval($post_data['lon']) : null;
$accuracy = isset($post_data['accuracy']) ? floatval($post_data['accuracy']) : null;
$provider = isset($post_data['provider']) ? $post_data['provider'] : 'unknown';
$timestamp = isset($post_data['timestamp']) ? intval($post_data['timestamp']) : time();
$speed = isset($post_data['speed']) ? floatval($post_data['speed']) : 0;
$bearing = isset($post_data['bearing']) ? floatval($post_data['bearing']) : 0;
$altitude = isset($post_data['altitude']) ? floatval($post_data['altitude']) : 0;

// Get IP and User Agent
$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

// Get device name from device_id or create one
if (empty($device_id) || $device_id == 'null' || $device_id == 'unknown') {
    $device_id = isset($post_data['android_id']) && !empty($post_data['android_id']) 
        ? $post_data['android_id'] 
        : 'device_' . substr(md5($ip), 0, 8);
    $device_name = 'Unknown Device';
} else {
    $device_name = $device_id;
}

// Log received data for debugging
$debug_log = date('Y-m-d H:i:s') . " - Device: $device_id, Lat: $lat, Lon: $lon, Acc: $accuracy, Provider: $provider, IP: $ip\n";
file_put_contents('debug.log', $debug_log, FILE_APPEND);

// Initialize response
$response = ['status' => 'success', 'message' => 'Location recorded'];

// Connect to database
$pdo = getDBConnection();

if ($pdo) {
    try {
        $current_time = date('Y-m-d H:i:s');
        
        // Insert location record
        $stmt = $pdo->prepare("
            INSERT INTO locations (device_id, device_name, latitude, longitude, accuracy, provider, speed, bearing, altitude, ip_address, user_agent, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $device_id,
            $device_name,
            $lat,
            $lon,
            $accuracy,
            $provider,
            $speed,
            $bearing,
            $altitude,
            $ip,
            $user_agent,
            $current_time
        ]);
        
        // Check if device exists
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM devices WHERE device_id = ?");
        $stmt->execute([$device_id]);
        $exists = $stmt->fetch()['count'] > 0;
        
        if ($exists) {
            // Update existing device
            $stmt = $pdo->prepare("
                UPDATE devices SET 
                    device_name = ?,
                    last_seen = ?,
                    last_lat = ?,
                    last_lon = ?,
                    last_accuracy = ?,
                    total_locations = total_locations + 1,
                    last_ip = ?
                WHERE device_id = ?
            ");
            
            $stmt->execute([
                $device_name,
                $current_time,
                $lat,
                $lon,
                $accuracy,
                $ip,
                $device_id
            ]);
        } else {
            // Insert new device
            $stmt = $pdo->prepare("
                INSERT INTO devices (device_id, device_name, first_seen, last_seen, last_lat, last_lon, last_accuracy, total_locations, last_ip)
                VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?)
            ");
            
            $stmt->execute([
                $device_id,
                $device_name,
                $current_time,
                $current_time,
                $lat,
                $lon,
                $accuracy,
                $ip
            ]);
        }
        
        $response['database'] = 'success';
        $response['device_id'] = $device_id;
        $response['location'] = ['lat' => $lat, 'lon' => $lon];
        
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        $response['status'] = 'error';
        $response['message'] = 'Database error: ' . $e->getMessage();
    }
} else {
    $response['status'] = 'error';
    $response['message'] = 'Database connection failed';
    echo json_encode($response, JSON_PRETTY_PRINT);
    exit;
}

// Handle API requests for dashboard
$action = isset($_GET['action']) ? $_GET['action'] : 'record';

switch ($action) {
    case 'record':
        // Just return success for location recording
        break;
        
    case 'get_stats':
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM locations");
            $total = $stmt->fetch();
            
            $stmt = $pdo->query("SELECT COUNT(*) as devices FROM devices");
            $devices_count = $stmt->fetch();
            
            $stmt = $pdo->query("SELECT MAX(created_at) as last FROM locations");
            $last = $stmt->fetch();
            
            $response['stats'] = [
                'total_locations' => intval($total['total']),
                'unique_devices' => intval($devices_count['devices']),
                'last_update' => $last['last']
            ];
            $response['status'] = 'success';
        } catch (PDOException $e) {
            $response['status'] = 'error';
            $response['message'] = $e->getMessage();
        }
        break;
        
    case 'list_devices':
        try {
            $stmt = $pdo->query("
                SELECT device_id, device_name, last_seen, last_lat, last_lon, last_accuracy, total_locations, last_ip 
                FROM devices 
                ORDER BY last_seen DESC
            ");
            $devices = $stmt->fetchAll();
            
            // Convert null values to empty strings for JSON
            foreach ($devices as &$device) {
                if ($device['last_lat'] === null) $device['last_lat'] = '';
                if ($device['last_lon'] === null) $device['last_lon'] = '';
                if ($device['last_accuracy'] === null) $device['last_accuracy'] = '';
            }
            
            $response['devices'] = $devices;
            $response['total_devices'] = count($devices);
            $response['status'] = 'success';
        } catch (PDOException $e) {
            $response['status'] = 'error';
            $response['message'] = $e->getMessage();
        }
        break;
        
    case 'get_device':
        $device_id_param = isset($_GET['device_id']) ? $_GET['device_id'] : null;
        if ($device_id_param) {
            try {
                $stmt = $pdo->prepare("SELECT * FROM devices WHERE device_id = ?");
                $stmt->execute([$device_id_param]);
                $response['device'] = $stmt->fetch();
                
                $stmt = $pdo->prepare("
                    SELECT * FROM locations 
                    WHERE device_id = ? 
                    ORDER BY created_at DESC 
                    LIMIT 20
                ");
                $stmt->execute([$device_id_param]);
                $response['locations'] = $stmt->fetchAll();
                $response['status'] = 'success';
            } catch (PDOException $e) {
                $response['status'] = 'error';
                $response['message'] = $e->getMessage();
            }
        } else {
            $response['status'] = 'error';
            $response['message'] = 'Device ID required';
        }
        break;
        
    case 'export_json':
        try {
            $stmt = $pdo->query("SELECT * FROM locations ORDER BY created_at DESC");
            $data = $stmt->fetchAll();
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="locations_' . date('Y-m-d_H-i-s') . '.json"');
            echo json_encode($data, JSON_PRETTY_PRINT);
            exit;
        } catch (PDOException $e) {
            $response['status'] = 'error';
            $response['message'] = $e->getMessage();
            echo json_encode($response);
            exit;
        }
        break;
        
    case 'export_csv':
        try {
            $stmt = $pdo->query("SELECT * FROM locations ORDER BY created_at DESC");
            $locations = $stmt->fetchAll();
            
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="locations_' . date('Y-m-d_H-i-s') . '.csv"');
            
            $output = fopen('php://output', 'w');
            if (!empty($locations)) {
                // Add UTF-8 BOM for Excel compatibility
                fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
                // Add headers
                fputcsv($output, array_keys($locations[0]));
                // Add data
                foreach ($locations as $loc) {
                    fputcsv($output, $loc);
                }
            }
            fclose($output);
            exit;
        } catch (PDOException $e) {
            $response['status'] = 'error';
            $response['message'] = $e->getMessage();
            echo json_encode($response);
            exit;
        }
        break;
        
    case 'clear_data':
        $auth_key = isset($_GET['auth_key']) ? $_GET['auth_key'] : '';
        if ($auth_key === 'admin123') {
            try {
                $pdo->exec("TRUNCATE TABLE locations");
                $pdo->exec("TRUNCATE TABLE devices");
                $response['status'] = 'success';
                $response['message'] = 'All data cleared successfully';
            } catch (PDOException $e) {
                $response['status'] = 'error';
                $response['message'] = 'Failed to clear data: ' . $e->getMessage();
            }
        } else {
            $response['status'] = 'error';
            $response['message'] = 'Unauthorized. Invalid authentication key.';
        }
        break;
        
    case 'get_latest':
        try {
            $stmt = $pdo->query("
                SELECT l.*, d.device_name 
                FROM locations l 
                JOIN devices d ON l.device_id = d.device_id 
                ORDER BY l.created_at DESC 
                LIMIT 10
            ");
            $response['latest'] = $stmt->fetchAll();
            $response['status'] = 'success';
        } catch (PDOException $e) {
            $response['status'] = 'error';
            $response['message'] = $e->getMessage();
        }
        break;
        
    default:
        $response['status'] = 'error';
        $response['message'] = 'Invalid action. Available actions: record, get_stats, list_devices, get_device, export_json, export_csv, clear_data, get_latest';
        break;
}

// Return JSON response
echo json_encode($response, JSON_PRETTY_PRINT);
?>