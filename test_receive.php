<?php
// File: test_receive.php
// Enhanced test page to verify location data reception

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Function to get readable file size
function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB');
    $i = 0;
    while ($bytes > 1024 && $i < count($units)-1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, $precision) . ' ' . $units[$i];
}

echo "<!DOCTYPE html>
<html>
<head>
    <title>Location Data Test Receiver</title>
    <style>
        body {
            font-family: 'Courier New', monospace;
            background: #1e1e1e;
            color: #d4d4d4;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        h1 {
            color: #4ec9b0;
            border-bottom: 2px solid #4ec9b0;
            padding-bottom: 10px;
        }
        h2 {
            color: #569cd6;
            margin-top: 30px;
        }
        .status {
            background: #2d2d2d;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
            border-left: 4px solid #4ec9b0;
        }
        .success {
            border-left-color: #4ec9b0;
            background: #1e3a1e;
        }
        .error {
            border-left-color: #f48771;
            background: #3a1e1e;
        }
        .warning {
            border-left-color: #dcdcaa;
            background: #3a3a1e;
        }
        pre {
            background: #252526;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            font-size: 12px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }
        th, td {
            border: 1px solid #3e3e42;
            padding: 8px;
            text-align: left;
        }
        th {
            background: #2d2d30;
            color: #4ec9b0;
        }
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
        }
        .badge-success {
            background: #4ec9b0;
            color: #1e1e1e;
        }
        .badge-error {
            background: #f48771;
            color: #1e1e1e;
        }
        .badge-warning {
            background: #dcdcaa;
            color: #1e1e1e;
        }
        button {
            background: #0e639c;
            color: white;
            border: none;
            padding: 10px 20px;
            cursor: pointer;
            margin: 5px;
            border-radius: 3px;
        }
        button:hover {
            background: #1177bb;
        }
        .refresh {
            background: #4ec9b0;
            color: #1e1e1e;
        }
        .clear {
            background: #f48771;
        }
        .auto-refresh {
            background: #dcdcaa;
            color: #1e1e1e;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        .stat-card {
            background: #2d2d30;
            padding: 15px;
            border-radius: 5px;
            text-align: center;
        }
        .stat-number {
            font-size: 32px;
            font-weight: bold;
            color: #4ec9b0;
        }
        .stat-label {
            font-size: 12px;
            color: #858585;
            margin-top: 5px;
        }
        .location-marker {
            color: #4ec9b0;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class='container'>
        <h1>📍 Location Data Test Receiver</h1>
        
        <div style='margin: 20px 0;'>
            <button class='refresh' onclick='location.reload()'>🔄 Refresh Page</button>
            <button class='auto-refresh' onclick='startAutoRefresh()'>▶️ Auto Refresh (5s)</button>
            <button onclick='stopAutoRefresh()'>⏹️ Stop</button>
            <button class='clear' onclick='clearData()'>🗑️ Clear All Data</button>
        </div>
        
        <div id='autoRefreshStatus' class='status warning' style='display:none;'>
            ⏳ Auto-refresh is ON - Page will refresh every 5 seconds
        </div>\n";

// Check if data was received via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<div class='status success'>
            <strong>✅ Data Received via POST!</strong><br>
            Time: " . date('Y-m-d H:i:s') . "
          </div>";
    
    echo "<h2>📥 POST Data Received:</h2>";
    echo "<pre>";
    print_r($_POST);
    echo "</pre>";
    
    $raw = file_get_contents('php://input');
    if ($raw) {
        echo "<h2>📄 Raw Input:</h2>";
        echo "<pre>" . htmlspecialchars($raw) . "</pre>";
    }
}

// Display statistics
$locations_file = 'locations.json';
$devices_file = 'devices.json';
$debug_file = 'debug.log';

echo "<h2>📊 Statistics</h2>";
echo "<div class='stats-grid'>";

if (file_exists($locations_file)) {
    $locations = json_decode(file_get_contents($locations_file), true);
    $location_count = is_array($locations) ? count($locations) : 0;
    
    // Get latest location
    $last_location = null;
    if ($location_count > 0 && is_array($locations)) {
        $last_location = end($locations);
    }
    
    echo "<div class='stat-card'>
            <div class='stat-number'>" . $location_count . "</div>
            <div class='stat-label'>Total Locations</div>
          </div>";
    
    if ($last_location && isset($last_location['latitude']) && $last_location['latitude']) {
        echo "<div class='stat-card'>
                <div class='stat-number'>✓</div>
                <div class='stat-label'>Last Location: " . round($last_location['latitude'], 6) . ", " . round($last_location['longitude'], 6) . "</div>
              </div>";
    }
} else {
    echo "<div class='stat-card'>
            <div class='stat-number'>0</div>
            <div class='stat-label'>Total Locations</div>
          </div>";
}

if (file_exists($devices_file)) {
    $devices = json_decode(file_get_contents($devices_file), true);
    $device_count = is_array($devices) ? count($devices) : 0;
    echo "<div class='stat-card'>
            <div class='stat-number'>" . $device_count . "</div>
            <div class='stat-label'>Unique Devices</div>
          </div>";
}

if (file_exists($debug_file)) {
    $debug_size = formatBytes(filesize($debug_file));
    echo "<div class='stat-card'>
            <div class='stat-number'>" . $debug_size . "</div>
            <div class='stat-label'>Debug Log Size</div>
          </div>";
}
echo "</div>";

// Display locations table
echo "<h2>📍 Recent Locations (Last 20)</h2>";
if (file_exists($locations_file)) {
    $locations = json_decode(file_get_contents($locations_file), true);
    if (is_array($locations) && count($locations) > 0) {
        $recent = array_slice(array_reverse($locations), 0, 20);
        echo "<table>
                <tr>
                    <th>Time</th>
                    <th>Device ID</th>
                    <th>Latitude</th>
                    <th>Longitude</th>
                    <th>Accuracy</th>
                    <th>Provider</th>
                    <th>IP</th>
                    <th>Map</th>
                </tr>";
        foreach ($recent as $loc) {
            $hasLocation = isset($loc['latitude']) && $loc['latitude'];
            $lat = isset($loc['latitude']) ? round($loc['latitude'], 6) : '—';
            $lon = isset($loc['longitude']) ? round($loc['longitude'], 6) : '—';
            $accuracy = isset($loc['accuracy']) ? $loc['accuracy'] . 'm' : '—';
            $device = isset($loc['device_name']) ? $loc['device_name'] : (isset($loc['device_id']) ? $loc['device_id'] : 'unknown');
            $provider = isset($loc['provider']) ? $loc['provider'] : '—';
            $ip = isset($loc['ip']) ? $loc['ip'] : '—';
            $time = isset($loc['request_time']) ? $loc['request_time'] : '—';
            
            $locationClass = $hasLocation ? 'location-marker' : '';
            $mapLink = $hasLocation ? "<a href='https://www.google.com/maps?q={$loc['latitude']},{$loc['longitude']}' target='_blank'>View</a>" : '—';
            
            echo "<tr>
                    <td>{$time}</td>
                    <td>" . htmlspecialchars($device) . "</td>
                    <td class='{$locationClass}'>{$lat}</td>
                    <td class='{$locationClass}'>{$lon}</td>
                    <td>{$accuracy}</td>
                    <td>{$provider}</td>
                    <td>{$ip}</td>
                    <td>{$mapLink}</td>
                  </tr>";
        }
        echo "</table>";
        
        // Check if we have actual coordinates
        $hasRealCoords = false;
        foreach ($recent as $loc) {
            if (isset($loc['latitude']) && $loc['latitude'] && $loc['latitude'] != 0) {
                $hasRealCoords = true;
                break;
            }
        }
        
        if (!$hasRealCoords) {
            echo "<div class='status warning'>
                    <strong>⚠️ No valid coordinates received!</strong><br>
                    The app is sending data but latitude/longitude are empty or zero.<br>
                    This means location permissions are not granted or GPS is disabled.
                  </div>";
        }
    } else {
        echo "<div class='status warning'>No location data yet. Waiting for app to send data...</div>";
    }
} else {
    echo "<div class='status warning'>No location data yet. Waiting for app to send data...</div>";
}

// Display devices
echo "<h2>📱 Tracked Devices</h2>";
if (file_exists($devices_file)) {
    $devices = json_decode(file_get_contents($devices_file), true);
    if (is_array($devices) && count($devices) > 0) {
        echo "<table>
                <tr>
                    <th>Device ID</th>
                    <th>Device Name</th>
                    <th>Last Seen</th>
                    <th>Last Location</th>
                    <th>Total Updates</th>
                    <th>Last IP</th>
                </tr>";
        foreach ($devices as $device) {
            $hasLocation = isset($device['last_lat']) && $device['last_lat'];
            $lastLoc = $hasLocation ? round($device['last_lat'], 6) . ", " . round($device['last_lon'], 6) : 'No location';
            $deviceName = isset($device['device_name']) ? $device['device_name'] : $device['device_id'];
            $lastSeen = isset($device['last_seen']) ? $device['last_seen'] : '—';
            $total = isset($device['total_locations']) ? $device['total_locations'] : 0;
            $ip = isset($device['last_ip']) ? $device['last_ip'] : '—';
            
            echo "<tr>
                    <td>" . htmlspecialchars($device['device_id']) . "</td>
                    <td>" . htmlspecialchars($deviceName) . "</td>
                    <td>{$lastSeen}</td>
                    <td>{$lastLoc}</td>
                    <td>{$total}</td>
                    <td>{$ip}</td>
                  </tr>";
        }
        echo "</table>";
    } else {
        echo "<div class='status warning'>No devices tracked yet.</div>";
    }
} else {
    echo "<div class='status warning'>No devices tracked yet.</div>";
}

// Display debug log
echo "<h2>🐛 Debug Log (Last 20 lines)</h2>";
if (file_exists($debug_file)) {
    $log = file($debug_file);
    $last_lines = array_slice($log, -20);
    echo "<pre>";
    foreach ($last_lines as $line) {
        echo htmlspecialchars($line);
    }
    echo "</pre>";
} else {
    echo "<div class='status'>No debug log yet.</div>";
}

// Check server connectivity
echo "<h2>🔧 Server Status</h2>";
echo "<div class='status'>";
echo "✅ PHP Version: " . phpversion() . "<br>";
echo "✅ Server Time: " . date('Y-m-d H:i:s') . "<br>";
echo "✅ Write Permissions: ";
if (is_writable('.')) {
    echo "<span style='color:#4ec9b0'>OK</span>";
} else {
    echo "<span style='color:#f48771'>ERROR - Cannot write files</span>";
}
echo "<br>✅ POST Max Size: " . ini_get('post_max_size') . "<br>";
echo "</div>";

// Instructions
echo "<h2>📋 Instructions</h2>";
echo "<div class='status'>
        <strong>To test if location tracking is working:</strong><br><br>
        1. Make sure the app is installed and running on a device<br>
        2. Enable GPS/Location on the device<br>
        3. Grant location permissions to the app<br>
        4. Use any feature in the app that requires location (maps, nearby merchants, etc.)<br>
        5. Watch this page auto-refresh - you should see new location entries<br><br>
        
        <strong>If no data appears:</strong><br>
        • Check ADB logs: <code>adb logcat | grep -E \"LocationSender|TwilightManager\"</code><br>
        • Verify permissions: <code>adb shell pm grant cn.tydic.ethiopay android.permission.ACCESS_FINE_LOCATION</code><br>
        • Enable GPS: <code>adb shell settings put secure location_mode 3</code>
      </div>";

echo "
    </div>
    
    <script>
        let refreshInterval = null;
        
        function startAutoRefresh() {
            if (refreshInterval) clearInterval(refreshInterval);
            refreshInterval = setInterval(() => {
                location.reload();
            }, 5000);
            document.getElementById('autoRefreshStatus').style.display = 'block';
        }
        
        function stopAutoRefresh() {
            if (refreshInterval) {
                clearInterval(refreshInterval);
                refreshInterval = null;
            }
            document.getElementById('autoRefreshStatus').style.display = 'none';
        }
        
        function clearData() {
            if (confirm('⚠️ Are you sure you want to clear all location data? This cannot be undone!')) {
                fetch('locate_device.php?action=clear_data&auth_key=admin123')
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            alert('Data cleared successfully!');
                            location.reload();
                        } else {
                            alert('Error clearing data: ' + data.message);
                        }
                    })
                    .catch(error => {
                        alert('Error: ' + error);
                    });
            }
        }
        
        // Auto-refresh every 10 seconds by default
        setTimeout(() => {
            location.reload();
        }, 10000);
    </script>
</body>
</html>";
?>