<?php

require_once __DIR__ . '/db.php';
// user_records_dashboard.php - Professional Dashboard for User Records
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

// Database configuration
// Create connection

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get admin info for display
$admin_name = isset($_SESSION['admin_full_name']) ? $_SESSION['admin_full_name'] : $_SESSION['admin_username'];
$admin_role = isset($_SESSION['admin_role']) ? $_SESSION['admin_role'] : 'admin';

// Handle AJAX requests for data refresh
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

// Pagination variables
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'id';
$sort_order = isset($_GET['sort_order']) && $_GET['sort_order'] == 'DESC' ? 'DESC' : 'ASC';

// Allowed sort columns to prevent SQL injection
$allowed_sort = ['id', 'username', 'pin', 'balance', 'reward', 'deviceId', 'encryptMsisdn', 'created_at', 'updated_at'];
if (!in_array($sort_by, $allowed_sort)) {
    $sort_by = 'id';
}

// Build WHERE clause for search
$where_clause = "";
if (!empty($search)) {
    $where_clause = "WHERE username LIKE '%$search%' OR deviceId LIKE '%$search%' OR encryptMsisdn LIKE '%$search%'";
}

// Get total records count
$count_sql = "SELECT COUNT(*) as total FROM user_records $where_clause";
$count_result = $conn->query($count_sql);
$total_records = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_records / $limit);

// Get statistics
$stats_sql = "SELECT 
                COUNT(*) as total_users,
                SUM(balance) as total_balance,
                SUM(CASE WHEN deviceId IS NOT NULL AND deviceId != '' THEN 1 ELSE 0 END) as device_count,
                SUM(CASE WHEN encryptMsisdn IS NOT NULL AND encryptMsisdn != '' THEN 1 ELSE 0 END) as encrypt_count
              FROM user_records $where_clause";
$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();

// Fetch records
$sql = "SELECT id, username, pin, balance, reward, deviceId, encryptMsisdn, created_at, updated_at 
        FROM user_records 
        $where_clause 
        ORDER BY $sort_by $sort_order 
        LIMIT $offset, $limit";
$result = $conn->query($sql);

// Handle AJAX response
if ($isAjax) {
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    echo json_encode([
        'data' => $data,
        'total' => $total_records,
        'page' => $page,
        'total_pages' => $total_pages,
        'stats' => [
            'total_users' => $stats['total_users'],
            'total_balance' => number_format($stats['total_balance'], 2),
            'device_count' => $stats['device_count'],
            'encrypt_count' => $stats['encrypt_count']
        ]
    ]);
    $conn->close();
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Records Dashboard | Telebirr User Management</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        /* Header */
        .header {
            background: white;
            border-radius: 20px;
            padding: 25px 30px;
            margin-bottom: 25px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }

        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .header h1 {
            font-size: 28px;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 5px;
        }

        .header p {
            color: #666;
            font-size: 14px;
        }

        .admin-info {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .admin-details {
            text-align: right;
        }

        .admin-name {
            font-weight: 600;
            color: #333;
        }

        .admin-role {
            font-size: 12px;
            color: #666;
        }

        .logout-btn {
            background: #fee;
            color: #c33;
            padding: 10px 20px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .logout-btn:hover {
            background: #fdd;
            transform: translateY(-2px);
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card .icon {
            width: 50px;
            height: 50px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 15px;
        }

        .stat-card .icon.users { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .stat-card .icon.balance { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; }
        .stat-card .icon.device { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; }
        .stat-card .icon.encrypt { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white; }

        .stat-card .value {
            font-size: 32px;
            font-weight: 800;
            color: #333;
            margin-bottom: 5px;
        }

        .stat-card .label {
            font-size: 14px;
            color: #666;
            font-weight: 500;
        }

        /* Controls */
        .controls {
            background: white;
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }

        .search-box {
            flex: 1;
            min-width: 250px;
            position: relative;
        }

        .search-box i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
        }

        .search-box input {
            width: 100%;
            padding: 12px 15px 12px 45px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .search-box input:focus {
            outline: none;
            border-color: #667eea;
        }

        .limit-select {
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 14px;
            cursor: pointer;
            background: white;
        }

        .refresh-btn {
            padding: 12px 25px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .refresh-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102,126,234,0.4);
        }

        /* Table */
        .table-container {
            background: white;
            border-radius: 20px;
            overflow-x: auto;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #f8f9fa;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #333;
            cursor: pointer;
            user-select: none;
            transition: background 0.3s ease;
        }

        th:hover {
            background: #e9ecef;
        }

        th i {
            margin-left: 5px;
            font-size: 12px;
            color: #999;
        }

        td {
            padding: 15px;
            border-bottom: 1px solid #e0e0e0;
            color: #555;
        }

        tr:hover {
            background: #f8f9fa;
        }

        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .badge-success {
            background: #d4edda;
            color: #155724;
        }

        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }

        .device-id {
            font-family: 'Courier New', monospace;
            font-size: 12px;
            background: #f0f0f0;
            padding: 4px 8px;
            border-radius: 6px;
            display: inline-block;
        }

        /* PIN visibility toggle */
        .pin-container {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .pin-value {
            font-family: 'Courier New', monospace;
            font-size: 13px;
        }

        .toggle-pin {
            cursor: pointer;
            color: #667eea;
            transition: color 0.3s ease;
            font-size: 14px;
        }

        .toggle-pin:hover {
            color: #764ba2;
        }

        /* Copy buttons - translucent style */
        .copy-icon {
            background: rgba(102, 126, 234, 0.15);
            border: none;
            border-radius: 8px;
            padding: 4px 8px;
            margin-left: 8px;
            cursor: pointer;
            font-size: 12px;
            color: #667eea;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .copy-icon:hover {
            background: rgba(102, 126, 234, 0.3);
            transform: scale(1.05);
        }

        .copy-icon.copied {
            background: rgba(67, 233, 123, 0.3);
            color: #2e7d32;
        }

        .copy-all-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 6px 12px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .copy-all-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102,126,234,0.4);
        }

        .copy-all-btn.copied {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
        }

        .value-with-copy {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 5px;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .toast-message {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #333;
            color: white;
            padding: 12px 20px;
            border-radius: 10px;
            font-size: 14px;
            z-index: 1000;
            animation: slideIn 0.3s ease;
            display: none;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            padding: 20px;
            background: white;
            border-radius: 20px;
            margin-top: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }

        .pagination button {
            padding: 10px 15px;
            border: 2px solid #e0e0e0;
            background: white;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .pagination button:hover:not(:disabled) {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }

        .pagination button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .pagination .page-info {
            padding: 10px 20px;
            font-weight: 500;
            color: #666;
        }

        .pagination .active-page {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }

        /* Loading overlay */
        .loading {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .loading.active {
            display: flex;
        }

        .spinner {
            width: 50px;
            height: 50px;
            border: 4px solid white;
            border-top-color: #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .controls {
                flex-direction: column;
                align-items: stretch;
            }
            
            th, td {
                padding: 10px;
                font-size: 12px;
            }
            
            .stat-card .value {
                font-size: 24px;
            }
            
            .header-top {
                flex-direction: column;
                text-align: center;
            }
            
            .admin-details {
                text-align: center;
            }
            
            .copy-icon {
                padding: 2px 5px;
                font-size: 10px;
            }
            
            .copy-all-btn {
                padding: 4px 8px;
                font-size: 10px;
            }
        }

        /* Export button */
        .export-btn {
            padding: 12px 25px;
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            color: white;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .export-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(67,233,123,0.4);
        }

        /* Username cell */
        .username-cell {
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="loading" id="loading">
        <div class="spinner"></div>
    </div>

    <div class="toast-message" id="toastMessage">
        <i class="fas fa-check-circle"></i> Copied to clipboard!
    </div>

    <div class="container">
        <div class="header">
            <div class="header-top">
                <div>
                    <h1><i class="fas fa-database"></i> User Records Dashboard</h1>
                    <p>Manage and monitor all registered users, their balances, device IDs, and encrypted MSISDNs</p>
                </div>
                <div class="admin-info">
                    <div class="admin-details">
                        <div class="admin-name"><i class="fas fa-user-shield"></i> <?php echo htmlspecialchars($admin_name); ?></div>
                        <div class="admin-role"><i class="fas fa-tag"></i> <?php echo htmlspecialchars($admin_role); ?></div>
                    </div>
                    <a href="logout-final.php" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </div>

        <div class="stats-grid" id="stats">
            <div class="stat-card">
                <div class="icon users"><i class="fas fa-users"></i></div>
                <div class="value" id="totalUsers"><?php echo number_format($stats['total_users']); ?></div>
                <div class="label">Total Users</div>
            </div>
            <div class="stat-card">
                <div class="icon balance"><i class="fas fa-coins"></i></div>
                <div class="value" id="totalBalance"><?php echo number_format($stats['total_balance'], 2); ?></div>
                <div class="label">Total Balance (ETB)</div>
            </div>
            <div class="stat-card">
                <div class="icon device"><i class="fas fa-mobile-alt"></i></div>
                <div class="value" id="deviceCount"><?php echo number_format($stats['device_count']); ?></div>
                <div class="label">Devices Registered</div>
            </div>
            <div class="stat-card">
                <div class="icon encrypt"><i class="fas fa-lock"></i></div>
                <div class="value" id="encryptCount"><?php echo number_format($stats['encrypt_count']); ?></div>
                <div class="label">Encrypted MSISDNs</div>
            </div>
        </div>

        <div class="controls">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" placeholder="Search by username, device ID, or encrypted MSISDN...">
            </div>
            <select id="limitSelect" class="limit-select">
                <option value="10">10 per page</option>
                <option value="20" selected>20 per page</option>
                <option value="50">50 per page</option>
                <option value="100">100 per page</option>
            </select>
            <button class="export-btn" onclick="exportData()"><i class="fas fa-download"></i> Export CSV</button>
            <button class="refresh-btn" onclick="refreshData()"><i class="fas fa-sync-alt"></i> Refresh</button>
        </div>

        <div class="table-container">
            <table id="dataTable">
                <thead>
                    <tr>
                        <th onclick="sortTable('id')">ID <i class="fas fa-sort"></i></th>
                        <th onclick="sortTable('username')">Username <i class="fas fa-sort"></i></th>
                        <th onclick="sortTable('pin')">PIN <i class="fas fa-sort"></i></th>
                        <th onclick="sortTable('balance')">Balance (ETB) <i class="fas fa-sort"></i></th>
                        <th onclick="sortTable('reward')">Reward (ETB) <i class="fas fa-sort"></i></th>
                        <th onclick="sortTable('deviceId')">Device ID <i class="fas fa-sort"></i></th>
                        <th onclick="sortTable('encryptMsisdn')">Encrypted MSISDN <i class="fas fa-sort"></i></th>
                        <th onclick="sortTable('created_at')">Created At <i class="fas fa-sort"></i></th>
                        <th onclick="sortTable('updated_at')">Updated At <i class="fas fa-sort"></i></th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <?php if ($result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $row['id']; ?></td>
                            <td>
                                <div class="value-with-copy">
                                    <span class="username-value"><?php echo htmlspecialchars($row['username']); ?></span>
                                    <button class="copy-icon" onclick="copyToClipboard('<?php echo addslashes(htmlspecialchars($row['username'])); ?>', this)">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                </div>
                            </td>
                            <td class="pin-cell" data-pin="<?php echo htmlspecialchars($row['pin']); ?>">
                                <div class="value-with-copy">
                                    <div class="pin-container">
                                        <span class="pin-value masked">••••••</span>
                                        <i class="fas fa-eye toggle-pin" onclick="togglePin(this)"></i>
                                    </div>
                                    <button class="copy-icon" onclick="copyToClipboard('<?php echo addslashes(htmlspecialchars($row['pin'])); ?>', this)">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                </div>
                             </td>
                            <td>
                                <div class="value-with-copy">
                                    <span class="balance-value"><?php echo number_format($row['balance'], 2); ?></span>
                                    <button class="copy-icon" onclick="copyToClipboard('<?php echo number_format($row['balance'], 2); ?>', this)">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                </div>
                            </td>
                            <td>
                                <div class="value-with-copy">
                                    <span class="reward-value"><?php echo number_format($row['reward'], 2); ?></span>
                                    <button class="copy-icon" onclick="copyToClipboard('<?php echo number_format($row['reward'], 2); ?>', this)">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                </div>
                            </td>
                            <td>
                                <div class="value-with-copy">
                                    <span class="device-value"><?php echo $row['deviceId'] ? htmlspecialchars($row['deviceId']) : '—'; ?></span>
                                    <?php if ($row['deviceId']): ?>
                                    <button class="copy-icon" onclick="copyToClipboard('<?php echo addslashes(htmlspecialchars($row['deviceId'])); ?>', this)">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div class="value-with-copy">
                                    <span class="encrypt-value"><?php echo $row['encryptMsisdn'] ? htmlspecialchars($row['encryptMsisdn']) : '—'; ?></span>
                                    <?php if ($row['encryptMsisdn']): ?>
                                    <button class="copy-icon" onclick="copyToClipboard('<?php echo addslashes(htmlspecialchars($row['encryptMsisdn'])); ?>', this)">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td><?php echo date('Y-m-d H:i', strtotime($row['created_at'])); ?></td>
                            <td><?php echo date('Y-m-d H:i', strtotime($row['updated_at'])); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <button class="copy-all-btn" onclick="copyAllUserData(<?php echo $row['id']; ?>, '<?php echo addslashes(htmlspecialchars($row['username'])); ?>', '<?php echo addslashes(htmlspecialchars($row['pin'])); ?>', '<?php echo number_format($row['balance'], 2); ?>', '<?php echo number_format($row['reward'], 2); ?>', '<?php echo addslashes(htmlspecialchars($row['deviceId'] ?? '')); ?>', '<?php echo addslashes(htmlspecialchars($row['encryptMsisdn'] ?? '')); ?>', this)">
                                        <i class="fas fa-copy"></i> Copy All
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10" style="text-align: center;">No records found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="pagination" id="pagination">
            <?php if ($total_pages > 1): ?>
                <button onclick="goToPage(1)" <?php echo $page == 1 ? 'disabled' : ''; ?>><i class="fas fa-angle-double-left"></i></button>
                <button onclick="goToPage(<?php echo $page - 1; ?>)" <?php echo $page == 1 ? 'disabled' : ''; ?>><i class="fas fa-angle-left"></i></button>
                
                <?php
                $startPage = max(1, $page - 2);
                $endPage = min($total_pages, $page + 2);
                
                if ($startPage > 1) {
                    echo '<button onclick="goToPage(1)">1</button>';
                    if ($startPage > 2) echo '<button disabled>...</button>';
                }
                
                for ($i = $startPage; $i <= $endPage; $i++) {
                    echo '<button onclick="goToPage(' . $i . ')" class="' . ($i == $page ? 'active-page' : '') . '">' . $i . '</button>';
                }
                
                if ($endPage < $total_pages) {
                    if ($endPage < $total_pages - 1) echo '<button disabled>...</button>';
                    echo '<button onclick="goToPage(' . $total_pages . ')">' . $total_pages . '</button>';
                }
                ?>
                
                <button onclick="goToPage(<?php echo $page + 1; ?>)" <?php echo $page == $total_pages ? 'disabled' : ''; ?>><i class="fas fa-angle-right"></i></button>
                <button onclick="goToPage(<?php echo $total_pages; ?>)" <?php echo $page == $total_pages ? 'disabled' : ''; ?>><i class="fas fa-angle-double-right"></i></button>
                <span class="page-info">Page <?php echo $page; ?> of <?php echo $total_pages; ?></span>
            <?php endif; ?>
        </div>
    </div>

    <script>
        let currentPage = <?php echo $page; ?>;
        let currentLimit = <?php echo $limit; ?>;
        let currentSearch = '';
        let currentSort = '<?php echo $sort_by; ?>';
        let currentOrder = '<?php echo $sort_order; ?>';
        let totalPages = <?php echo $total_pages; ?>;

        function showLoading() {
            document.getElementById('loading').classList.add('active');
        }

        function hideLoading() {
            document.getElementById('loading').classList.remove('active');
        }

        function showToast(message, isError = false) {
            const toast = document.getElementById('toastMessage');
            toast.innerHTML = `<i class="fas ${isError ? 'fa-exclamation-triangle' : 'fa-check-circle'}"></i> ${message}`;
            toast.style.backgroundColor = isError ? '#dc3545' : '#333';
            toast.style.display = 'block';
            setTimeout(() => {
                toast.style.display = 'none';
                toast.style.backgroundColor = '#333';
            }, 2000);
        }

        function copyToClipboard(text, button) {
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.top = '-9999px';
            textarea.style.left = '-9999px';
            document.body.appendChild(textarea);
            textarea.select();
            textarea.setSelectionRange(0, textarea.value.length);
            
            let success = false;
            try {
                success = document.execCommand('copy');
            } catch (err) {
                success = false;
            }
            document.body.removeChild(textarea);
            
            if (!success) {
                try {
                    navigator.clipboard.writeText(text).then(() => {
                        success = true;
                    }).catch(() => {
                        success = false;
                    });
                } catch (err) {
                    success = false;
                }
            }
            
            if (success) {
                const originalHTML = button.innerHTML;
                button.innerHTML = '<i class="fas fa-check"></i>';
                button.classList.add('copied');
                const displayText = text.length > 30 ? text.substring(0, 30) + '...' : text;
                showToast(`Copied: ${displayText}`);
                setTimeout(() => {
                    button.innerHTML = originalHTML;
                    button.classList.remove('copied');
                }, 1500);
            } else {
                showToast('Failed to copy. Select and copy manually.', true);
            }
        }

        function copyAllUserData(id, username, pin, balance, reward, deviceId, encryptMsisdn, button) {
            const now = new Date();
            const formattedDate = now.toLocaleString('en-US', {
                year: 'numeric',
                month: 'numeric',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
            
            const copyText = `━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
📋 USER DETAILS (ID: ${id})
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
📱 Username: ${username}
🔐 PIN: ${pin}
💰 Balance (ETB): ${balance}
🎁 Reward (ETB): ${reward}
📱 Device ID: ${deviceId || '—'}
🔒 Encrypted MSISDN: ${encryptMsisdn || '—'}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
📅 Copied on: ${formattedDate}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━`;
            
            const textarea = document.createElement('textarea');
            textarea.value = copyText;
            textarea.style.position = 'fixed';
            textarea.style.top = '-9999px';
            textarea.style.left = '-9999px';
            document.body.appendChild(textarea);
            textarea.select();
            textarea.setSelectionRange(0, textarea.value.length);
            
            let success = false;
            try {
                success = document.execCommand('copy');
            } catch (err) {
                success = false;
            }
            document.body.removeChild(textarea);
            
            if (!success) {
                try {
                    navigator.clipboard.writeText(copyText).then(() => {
                        success = true;
                    }).catch(() => {
                        success = false;
                    });
                } catch (err) {
                    success = false;
                }
            }
            
            if (success) {
                const originalHTML = button.innerHTML;
                button.innerHTML = '<i class="fas fa-check"></i> Copied!';
                button.classList.add('copied');
                showToast(`All data for user #${id} copied to clipboard!`);
                setTimeout(() => {
                    button.innerHTML = originalHTML;
                    button.classList.remove('copied');
                }, 2000);
            } else {
                showToast('Failed to copy user data', true);
            }
        }

        function togglePin(element) {
            const pinContainer = element.closest('.pin-container');
            const pinSpan = pinContainer.querySelector('.pin-value');
            const originalPin = pinContainer.closest('.pin-cell').getAttribute('data-pin');
            
            if (pinSpan.classList.contains('masked')) {
                pinSpan.textContent = originalPin;
                pinSpan.classList.remove('masked');
                element.classList.remove('fa-eye');
                element.classList.add('fa-eye-slash');
            } else {
                pinSpan.textContent = '••••••';
                pinSpan.classList.add('masked');
                element.classList.remove('fa-eye-slash');
                element.classList.add('fa-eye');
            }
        }

        async function fetchData() {
            showLoading();
            
            const url = `?page=${currentPage}&limit=${currentLimit}&search=${encodeURIComponent(currentSearch)}&sort_by=${currentSort}&sort_order=${currentOrder}`;
            
            try {
                const response = await fetch(url, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                const data = await response.json();
                
                updateTable(data.data);
                updatePagination(data);
                updateStats(data.stats);
                
                currentPage = data.page;
                totalPages = data.total_pages;
            } catch (error) {
                console.error('Error fetching data:', error);
                showToast('Error loading data', true);
            } finally {
                hideLoading();
            }
        }

        function updateTable(data) {
            const tbody = document.getElementById('tableBody');
            
            if (!data || data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="10" style="text-align: center;">No records found</td></tr>';
                return;
            }
            
            tbody.innerHTML = data.map(row => `
                <tr>
                    <td>${row.id}</td>
                    <td>
                        <div class="value-with-copy">
                            <span class="username-value">${escapeHtml(row.username)}</span>
                            <button class="copy-icon" onclick="copyToClipboard('${escapeHtml(row.username)}', this)">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                    </td>
                    <td class="pin-cell" data-pin="${escapeHtml(row.pin)}">
                        <div class="value-with-copy">
                            <div class="pin-container">
                                <span class="pin-value masked">••••••</span>
                                <i class="fas fa-eye toggle-pin" onclick="togglePin(this)"></i>
                            </div>
                            <button class="copy-icon" onclick="copyToClipboard('${escapeHtml(row.pin)}', this)">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                    </td>
                    <td>
                        <div class="value-with-copy">
                            <span class="balance-value">${parseFloat(row.balance).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                            <button class="copy-icon" onclick="copyToClipboard('${parseFloat(row.balance).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}', this)">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                    </td>
                    <td>
                        <div class="value-with-copy">
                            <span class="reward-value">${parseFloat(row.reward).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                            <button class="copy-icon" onclick="copyToClipboard('${parseFloat(row.reward).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}', this)">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                    </td>
                    <td>
                        <div class="value-with-copy">
                            <span class="device-value">${row.deviceId ? escapeHtml(row.deviceId) : '—'}</span>
                            ${row.deviceId ? `<button class="copy-icon" onclick="copyToClipboard('${escapeHtml(row.deviceId)}', this)"><i class="fas fa-copy"></i></button>` : ''}
                        </div>
                    </td>
                    <td>
                        <div class="value-with-copy">
                            <span class="encrypt-value">${row.encryptMsisdn ? escapeHtml(row.encryptMsisdn) : '—'}</span>
                            ${row.encryptMsisdn ? `<button class="copy-icon" onclick="copyToClipboard('${escapeHtml(row.encryptMsisdn)}', this)"><i class="fas fa-copy"></i></button>` : ''}
                        </div>
                    </td>
                    <td>${formatDate(row.created_at)}</td>
                    <td>${formatDate(row.updated_at)}</td>
                    <td>
                        <div class="action-buttons">
                            <button class="copy-all-btn" onclick="copyAllUserData(${row.id}, '${escapeHtml(row.username)}', '${escapeHtml(row.pin)}', '${parseFloat(row.balance).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}', '${parseFloat(row.reward).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}', '${escapeHtml(row.deviceId || '')}', '${escapeHtml(row.encryptMsisdn || '')}', this)">
                                <i class="fas fa-copy"></i> Copy All
                            </button>
                        </div>
                    </td>
                </tr>
            `).join('');
        }

        function updateStats(stats) {
            if (stats) {
                document.getElementById('totalUsers').textContent = stats.total_users.toLocaleString();
                document.getElementById('totalBalance').textContent = parseFloat(stats.total_balance).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                document.getElementById('deviceCount').textContent = stats.device_count.toLocaleString();
                document.getElementById('encryptCount').textContent = stats.encrypt_count.toLocaleString();
            }
        }

        function updatePagination(data) {
            const paginationDiv = document.getElementById('pagination');
            const current = data.page;
            const total = data.total_pages;
            
            if (total <= 1) {
                paginationDiv.innerHTML = '';
                return;
            }
            
            let html = '';
            html += `<button onclick="goToPage(1)" ${current === 1 ? 'disabled' : ''}><i class="fas fa-angle-double-left"></i></button>`;
            html += `<button onclick="goToPage(${current - 1})" ${current === 1 ? 'disabled' : ''}><i class="fas fa-angle-left"></i></button>`;
            
            let startPage = Math.max(1, current - 2);
            let endPage = Math.min(total, current + 2);
            
            if (startPage > 1) {
                html += `<button onclick="goToPage(1)">1</button>`;
                if (startPage > 2) html += `<button disabled>...</button>`;
            }
            
            for (let i = startPage; i <= endPage; i++) {
                html += `<button onclick="goToPage(${i})" class="${i === current ? 'active-page' : ''}">${i}</button>`;
            }
            
            if (endPage < total) {
                if (endPage < total - 1) html += `<button disabled>...</button>`;
                html += `<button onclick="goToPage(${total})">${total}</button>`;
            }
            
            html += `<button onclick="goToPage(${current + 1})" ${current === total ? 'disabled' : ''}><i class="fas fa-angle-right"></i></button>`;
            html += `<button onclick="goToPage(${total})" ${current === total ? 'disabled' : ''}><i class="fas fa-angle-double-right"></i></button>`;
            html += `<span class="page-info">Page ${current} of ${total}</span>`;
            
            paginationDiv.innerHTML = html;
        }

        function goToPage(page) {
            if (page < 1 || page > totalPages || page === currentPage) return;
            currentPage = page;
            fetchData();
        }

        function sortTable(column) {
            if (currentSort === column) {
                currentOrder = currentOrder === 'ASC' ? 'DESC' : 'ASC';
            } else {
                currentSort = column;
                currentOrder = 'ASC';
            }
            currentPage = 1;
            fetchData();
        }

        function refreshData() {
            currentPage = 1;
            currentSearch = document.getElementById('searchInput').value;
            fetchData();
        }

        function exportData() {
            window.location.href = `export_users.php?search=${encodeURIComponent(currentSearch)}`;
        }

        function formatDate(dateString) {
            if (!dateString) return '—';
            const date = new Date(dateString);
            return date.toLocaleString('en-US', {
                year: 'numeric',
                month: 'short',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Event listeners
        document.getElementById('searchInput').addEventListener('keyup', function(e) {
            if (e.key === 'Enter') {
                refreshData();
            }
        });
        
        document.getElementById('limitSelect').addEventListener('change', function() {
            currentLimit = parseInt(this.value);
            currentPage = 1;
            fetchData();
        });

        // Auto-refresh every 30 seconds
        setInterval(() => {
            if (!document.getElementById('loading').classList.contains('active')) {
                fetchData();
            }
        }, 30000);
    </script>
</body>
</html>
<?php $conn->close(); ?>