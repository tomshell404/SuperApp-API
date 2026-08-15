<?php
// File: dashboard.php
// Modified to read from MySQL database

// Database configuration - UPDATE THESE
$db_host = 'localhost';
$db_user = 'root';  // Your MySQL username
$db_pass = '';      // Your MySQL password
$db_name = 'location_tracker';

// Connect to database
try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Get initial data for page load
$stmt = $pdo->query("SELECT COUNT(*) as total FROM locations");
$total_locations = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(DISTINCT device_id) as total FROM devices");
$total_devices = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT MAX(created_at) as last FROM locations");
$last_update = $stmt->fetch()['last'];

$devices = $pdo->query("
    SELECT device_id, device_name, last_seen, last_lat, last_lon, last_accuracy, total_locations, last_ip 
    FROM devices 
    ORDER BY last_seen DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Device Location Tracker - Professional Dashboard</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        h1 {
            color: white;
            margin-bottom: 20px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .stat-card h3 {
            color: #667eea;
            margin-bottom: 10px;
            font-size: 14px;
            text-transform: uppercase;
        }
        
        .stat-card .value {
            font-size: 32px;
            font-weight: bold;
            color: #333;
        }
        
        .stat-card .small {
            font-size: 14px;
            color: #666;
            margin-top: 5px;
        }
        
        .controls {
            margin-bottom: 20px;
        }
        
        button {
            background: white;
            color: #667eea;
            border: none;
            padding: 10px 20px;
            margin: 5px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        button:hover {
            background: #667eea;
            color: white;
            transform: translateY(-2px);
        }
        
        .devices-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .device-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .device-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.15);
        }
        
        .device-card.selected {
            border: 3px solid #667eea;
            background: #f0f4ff;
        }
        
        .device-id {
            font-size: 18px;
            font-weight: bold;
            color: #333;
            margin-bottom: 10px;
            word-break: break-all;
        }
        
        .device-info {
            font-size: 14px;
            color: #666;
            margin: 5px 0;
        }
        
        .location-badge {
            background: #4CAF50;
            color: white;
            padding: 5px 10px;
            border-radius: 5px;
            display: inline-block;
            font-size: 12px;
            margin-top: 10px;
        }
        
        .detail-panel {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
            display: none;
        }
        
        .detail-panel.active {
            display: block;
        }
        
        #map {
            height: 400px;
            margin-top: 20px;
            border-radius: 10px;
        }
        
        .location-history {
            margin-top: 20px;
            max-height: 300px;
            overflow-y: auto;
        }
        
        .history-item {
            padding: 10px;
            border-bottom: 1px solid #eee;
            font-size: 14px;
        }
        
        .history-item:hover {
            background: #f5f5f5;
        }
        
        .accuracy-good { color: #4CAF50; }
        .accuracy-fair { color: #FF9800; }
        .accuracy-poor { color: #f44336; }
        
        .live-badge {
            background: #4CAF50;
            color: white;
            padding: 3px 8px;
            border-radius: 20px;
            font-size: 12px;
            display: inline-block;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        
        .debug-info {
            background: #333;
            color: #0f0;
            padding: 10px;
            font-family: monospace;
            font-size: 12px;
            margin-top: 20px;
            border-radius: 5px;
            display: none;
        }
        
        .loading {
            text-align: center;
            color: white;
            padding: 40px;
        }
    </style>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
</head>
<body>
    <div class="container">
        <h1>📍 Professional Device Location Tracker</h1>
        
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Locations</h3>
                <div class="value" id="totalLocations"><?php echo $total_locations; ?></div>
            </div>
            <div class="stat-card">
                <h3>Unique Devices</h3>
                <div class="value" id="uniqueDevices"><?php echo $total_devices; ?></div>
            </div>
            <div class="stat-card">
                <h3>Last Update</h3>
                <div class="value" id="lastUpdate" style="font-size: 16px;"><?php echo $last_update ? date('H:i:s d/m/Y', strtotime($last_update)) : 'Never'; ?></div>
            </div>
            <div class="stat-card">
                <h3>Live Tracking</h3>
                <div class="value" id="liveStatus" style="font-size: 16px;"><span class="live-badge">🟢 Active</span></div>
            </div>
        </div>
        
        <div class="controls">
            <button onclick="loadDevices()">🔄 Refresh Devices</button>
            <button onclick="exportData('json')">📥 Export JSON</button>
            <button onclick="exportData('csv')">📊 Export CSV</button>
            <button onclick="startAutoRefresh()">▶️ Start Auto-Refresh</button>
            <button onclick="stopAutoRefresh()">⏹️ Stop Auto-Refresh</button>
            <button onclick="showDebug()">🐛 Show Debug</button>
            <button onclick="clearData()">🗑️ Clear All Data</button>
        </div>
        
        <h2 style="color: white;">Tracked Devices</h2>
        <div class="devices-grid" id="devices">
            <?php if (count($devices) > 0): ?>
                <?php foreach ($devices as $device): ?>
                    <?php 
                    $hasLocation = $device['last_lat'] && $device['last_lon'];
                    $lastLocation = $hasLocation ? round($device['last_lat'], 6) . ', ' . round($device['last_lon'], 6) : 'No location yet';
                    $accuracyClass = $device['last_accuracy'] && $device['last_accuracy'] < 20 ? 'accuracy-good' : 
                                    ($device['last_accuracy'] && $device['last_accuracy'] < 50 ? 'accuracy-fair' : 'accuracy-poor');
                    ?>
                    <div class="device-card" onclick="showDeviceDetails('<?php echo htmlspecialchars($device['device_id']); ?>')">
                        <div class="device-id">📱 <?php echo htmlspecialchars($device['device_name'] ?: $device['device_id']); ?></div>
                        <div class="device-info">🆔 ID: <?php echo htmlspecialchars($device['device_id']); ?></div>
                        <div class="device-info">📍 Last Location: <?php echo $lastLocation; ?></div>
                        <?php if ($device['last_accuracy']): ?>
                            <div class="device-info">🎯 Accuracy: <span class="<?php echo $accuracyClass; ?>"><?php echo round($device['last_accuracy']); ?> meters</span></div>
                        <?php endif; ?>
                        <div class="device-info">⏱️ Last Seen: <?php echo date('H:i:s d/m/Y', strtotime($device['last_seen'])); ?></div>
                        <div class="device-info">📊 Total Updates: <?php echo $device['total_locations']; ?></div>
                        <?php if ($hasLocation): ?>
                            <div class="location-badge">🌍 Click for map</div>
                        <?php else: ?>
                            <div class="location-badge" style="background:#999;">⏳ Waiting for location</div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="color: white; text-align: center; grid-column: 1/-1;">No devices tracked yet. Waiting for data from the app...</div>
            <?php endif; ?>
        </div>
        
        <div class="detail-panel" id="detailPanel">
            <h2 id="selectedDeviceTitle">Device Details</h2>
            <div id="deviceDetails"></div>
            <div id="map"></div>
            <h3>Location History (Last 20)</h3>
            <div class="location-history" id="locationHistory"></div>
        </div>
        
        <div class="debug-info" id="debugInfo">
            <strong>Debug Information:</strong><br>
            <div id="debugContent"></div>
        </div>
    </div>
    
    <script>
        let map = null;
        let marker = null;
        let autoRefreshInterval = null;
        let currentDeviceId = null;
        
        async function loadStats() {
            try {
                const response = await fetch('locate_device.php?action=get_stats&t=' + Date.now());
                const data = await response.json();
                if (data.status === 'success' && data.stats) {
                    document.getElementById('totalLocations').textContent = data.stats.total_locations || 0;
                    document.getElementById('uniqueDevices').textContent = data.stats.unique_devices || 0;
                    if (data.stats.last_update) {
                        const date = new Date(data.stats.last_update);
                        document.getElementById('lastUpdate').textContent = date.toLocaleString();
                    }
                }
            } catch (error) {
                console.error('Error loading stats:', error);
            }
        }
        
        async function loadDevices() {
            try {
                const response = await fetch('locate_device.php?action=list_devices&t=' + Date.now());
                const data = await response.json();
                if (data.status === 'success') {
                    displayDevices(data.devices);
                    await loadStats();
                }
            } catch (error) {
                console.error('Error loading devices:', error);
                document.getElementById('devices').innerHTML = '<div style="color: white; text-align: center;">Error loading devices. Make sure the server is running.</div>';
            }
        }
        
        function displayDevices(devices) {
            const devicesDiv = document.getElementById('devices');
            if (!devices || devices.length === 0) {
                devicesDiv.innerHTML = '<div style="color: white; text-align: center;">No devices tracked yet. Waiting for data from the app...</div>';
                return;
            }
            
            let html = '';
            devices.forEach(device => {
                const hasLocation = device.last_lat && device.last_lon;
                const lastLocation = hasLocation ? 
                    `${parseFloat(device.last_lat).toFixed(6)}, ${parseFloat(device.last_lon).toFixed(6)}` : 
                    'No location yet';
                
                const accuracyClass = device.last_accuracy && device.last_accuracy < 20 ? 'accuracy-good' : 
                                     (device.last_accuracy && device.last_accuracy < 50 ? 'accuracy-fair' : 'accuracy-poor');
                
                html += `
                    <div class="device-card" onclick="showDeviceDetails('${escapeHtml(device.device_id)}')">
                        <div class="device-id">📱 ${escapeHtml(device.device_name || device.device_id)}</div>
                        <div class="device-info">🆔 ID: ${escapeHtml(device.device_id)}</div>
                        <div class="device-info">📍 Last Location: ${lastLocation}</div>
                        ${device.last_accuracy ? `<div class="device-info">🎯 Accuracy: <span class="${accuracyClass}">${device.last_accuracy} meters</span></div>` : ''}
                        <div class="device-info">⏱️ Last Seen: ${device.last_seen}</div>
                        <div class="device-info">📊 Total Updates: ${device.total_locations}</div>
                        ${hasLocation ? '<div class="location-badge">🌍 Click for map</div>' : '<div class="location-badge" style="background:#999;">⏳ Waiting for location</div>'}
                    </div>
                `;
            });
            devicesDiv.innerHTML = html;
        }
        
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        async function showDeviceDetails(deviceId) {
            currentDeviceId = deviceId;
            document.getElementById('detailPanel').classList.add('active');
            document.getElementById('selectedDeviceTitle').innerHTML = `📍 Device: ${escapeHtml(deviceId)}`;
            
            try {
                const response = await fetch(`locate_device.php?action=get_device&device_id=${encodeURIComponent(deviceId)}&t=${Date.now()}`);
                const data = await response.json();
                
                if (data.status === 'success' && data.device) {
                    displayDeviceDetails(data.device, data.locations);
                    if (data.device.last_lat && data.device.last_lon) {
                        updateMap(parseFloat(data.device.last_lat), parseFloat(data.device.last_lon), data.device.device_name);
                    }
                    displayLocationHistory(data.locations);
                } else {
                    document.getElementById('deviceDetails').innerHTML = '<p>Error loading device details</p>';
                }
            } catch (error) {
                console.error('Error loading device details:', error);
                document.getElementById('deviceDetails').innerHTML = '<p>Error loading device details</p>';
            }
        }
        
        function displayDeviceDetails(device, locations) {
            const hasLocation = device.last_lat && device.last_lon;
            const lastLocation = hasLocation ? 
                `${parseFloat(device.last_lat).toFixed(6)}, ${parseFloat(device.last_lon).toFixed(6)}` : 
                'No location yet';
            
            const mapsLink = hasLocation ? `https://www.google.com/maps?q=${device.last_lat},${device.last_lon}` : '#';
            
            document.getElementById('deviceDetails').innerHTML = `
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-bottom: 20px;">
                    <div><strong>Device Name:</strong><br>${escapeHtml(device.device_name || device.device_id)}</div>
                    <div><strong>Device ID:</strong><br>${escapeHtml(device.device_id)}</div>
                    <div><strong>Current Location:</strong><br>${lastLocation}</div>
                    ${device.last_accuracy ? `<div><strong>Accuracy:</strong><br>${device.last_accuracy} meters</div>` : ''}
                    ${device.last_provider ? `<div><strong>Provider:</strong><br>${device.last_provider}</div>` : ''}
                    <div><strong>Last Seen:</strong><br>${device.last_seen}</div>
                    <div><strong>Total Updates:</strong><br>${device.total_locations}</div>
                    <div><strong>First Seen:</strong><br>${device.first_seen}</div>
                    ${device.last_ip ? `<div><strong>Last IP:</strong><br>${device.last_ip}</div>` : ''}
                    ${hasLocation ? `<div><strong>Google Maps:</strong><br><a href="${mapsLink}" target="_blank">Open in Maps →</a></div>` : ''}
                </div>
            `;
        }
        
        function updateMap(lat, lon, title) {
            if (!map) {
                map = L.map('map').setView([lat, lon], 15);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© OpenStreetMap contributors'
                }).addTo(map);
            } else {
                map.setView([lat, lon], 15);
                if (marker) {
                    map.removeLayer(marker);
                }
            }
            
            marker = L.marker([lat, lon]).addTo(map);
            marker.bindPopup(`<b>${escapeHtml(title || currentDeviceId)}</b><br>Lat: ${lat.toFixed(6)}<br>Lon: ${lon.toFixed(6)}`).openPopup();
        }
        
        function displayLocationHistory(locations) {
            const historyDiv = document.getElementById('locationHistory');
            if (!locations || locations.length === 0) {
                historyDiv.innerHTML = '<p>No location history available</p>';
                return;
            }
            
            let html = '';
            locations.forEach(loc => {
                const accuracyClass = loc.accuracy && loc.accuracy < 20 ? 'accuracy-good' : 
                                     (loc.accuracy && loc.accuracy < 50 ? 'accuracy-fair' : 'accuracy-poor');
                
                html += `
                    <div class="history-item">
                        <strong>${loc.created_at || loc.request_time}</strong><br>
                        📍 ${parseFloat(loc.latitude).toFixed(6)}, ${parseFloat(loc.longitude).toFixed(6)}<br>
                        ${loc.accuracy ? `🎯 Accuracy: <span class="${accuracyClass}">${loc.accuracy} meters</span><br>` : ''}
                        ${loc.provider ? `📡 Provider: ${loc.provider}<br>` : ''}
                        ${loc.speed ? `🚗 Speed: ${loc.speed} m/s<br>` : ''}
                        <a href="https://www.google.com/maps?q=${loc.latitude},${loc.longitude}" target="_blank">View on Map →</a>
                    </div>
                `;
            });
            historyDiv.innerHTML = html;
        }
        
        function exportData(format) {
            if (format === 'json') {
                window.location.href = 'locate_device.php?action=export_json';
            } else if (format === 'csv') {
                window.location.href = 'locate_device.php?action=export_csv';
            }
        }
        
        function startAutoRefresh() {
            if (autoRefreshInterval) clearInterval(autoRefreshInterval);
            autoRefreshInterval = setInterval(() => {
                loadDevices();
                if (currentDeviceId) {
                    showDeviceDetails(currentDeviceId);
                }
            }, 10000);
        }
        
        function stopAutoRefresh() {
            if (autoRefreshInterval) {
                clearInterval(autoRefreshInterval);
                autoRefreshInterval = null;
            }
        }
        
        async function showDebug() {
            const debugDiv = document.getElementById('debugInfo');
            const debugContent = document.getElementById('debugContent');
            
            if (debugDiv.style.display === 'block') {
                debugDiv.style.display = 'none';
            } else {
                try {
                    const response = await fetch('locate_device.php?action=list_devices&t=' + Date.now());
                    const data = await response.json();
                    debugContent.innerHTML = `<pre>${JSON.stringify(data, null, 2)}</pre>`;
                    debugDiv.style.display = 'block';
                } catch (error) {
                    debugContent.innerHTML = 'Error fetching debug info';
                    debugDiv.style.display = 'block';
                }
            }
        }
        
        async function clearData() {
            if (confirm('⚠️ Are you sure you want to clear all location data? This cannot be undone!')) {
                try {
                    const response = await fetch('locate_device.php?action=clear_data&auth_key=admin123');
                    const data = await response.json();
                    if (data.status === 'success') {
                        alert('Data cleared successfully!');
                        location.reload();
                    } else {
                        alert('Error clearing data: ' + (data.message || 'Unknown error'));
                    }
                } catch (error) {
                    alert('Error: ' + error);
                }
            }
        }
        
        // Initial load
        loadDevices();
        startAutoRefresh();
        
        // Refresh stats every 5 seconds
        setInterval(loadStats, 5000);
    </script>
</body>
</html>