<?php

require_once __DIR__ . '/db.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

// Optional: Check session timeout (30 minutes)
if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time'] > 1800)) {
    header('Location: logout.php?timeout=1');
    exit();
}

// Refresh session timeout
$_SESSION['login_time'] = time();
// Database connection

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get stats
$total_users = $conn->query("SELECT COUNT(*) as total FROM users")->fetch_assoc()['total'];
$total_balance = $conn->query("SELECT SUM(balance) as total FROM users")->fetch_assoc()['total'] ?? 0;
$total_rewards = $conn->query("SELECT SUM(reward) as total FROM users")->fetch_assoc()['total'] ?? 0;
$avg_balance = $total_users > 0 ? $total_balance / $total_users : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Telebirr User Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3a0ca3;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --info-color: #17a2b8;
        }
        
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', 'Roboto', -apple-system, BlinkMacSystemFont, sans-serif;
            font-size: 14px;
            overflow-x: hidden;
        }
        
        /* Mobile Header */
        .mobile-header {
            background: white;
            padding: 12px 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1030;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .menu-toggle {
            background: none;
            border: none;
            font-size: 24px;
            color: var(--primary-color);
            padding: 5px;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        /* Mobile Sidebar */
        .mobile-sidebar {
            background: linear-gradient(180deg, #2c3e50 0%, #34495e 100%);
            color: white;
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 280px;
            transform: translateX(-100%);
            transition: transform 0.3s ease;
            z-index: 1040;
            box-shadow: 3px 0 15px rgba(0,0,0,0.1);
            overflow-y: auto;
            display: flex;
            flex-direction: column;
        }
        
        .mobile-sidebar.active {
            transform: translateX(0);
        }
        
        /* Desktop Sidebar */
        .desktop-sidebar {
            background: linear-gradient(180deg, #2c3e50 0%, #34495e 100%);
            color: white;
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            width: 250px;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            z-index: 1000;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
        }
        
        .sidebar-header {
            padding: 20px 15px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            background: rgba(0,0,0,0.2);
        }
        
        .sidebar-menu {
            padding: 20px 0;
            flex: 1;
        }
        
        .sidebar-menu .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            margin: 5px 15px;
            border-radius: 8px;
            transition: all 0.3s;
            font-weight: 500;
            text-decoration: none;
            display: flex;
            align-items: center;
        }
        
        .sidebar-menu .nav-link:hover,
        .sidebar-menu .nav-link.active {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        
        .sidebar-menu .nav-link i {
            width: 24px;
            margin-right: 10px;
            font-size: 16px;
        }
        
        .sidebar-menu .nav-link.text-danger:hover {
            background: rgba(220, 53, 69, 0.2);
            color: #ff6b6b !important;
        }
        
        .sidebar-stats {
            padding: 15px;
            background: rgba(0,0,0,0.2);
            border-radius: 10px;
            margin: 20px 15px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        /* User info in sidebar */
        .sidebar-user {
            padding: 15px;
            border-top: 1px solid rgba(255,255,255,0.1);
            margin-top: auto;
        }
        
        .user-avatar-sm {
            width: 36px;
            height: 36px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }
        
        .very-small {
            font-size: 11px;
        }
        
        /* Overlay for mobile */
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1039;
            display: none;
        }
        
        .sidebar-overlay.active {
            display: block;
        }
        
        /* Main Content */
        .main-content {
            min-height: 100vh;
            background-color: #f8f9fa;
            transition: all 0.3s ease;
        }
        
        /* Mobile specific padding */
        .mobile-content {
            padding-top: 60px;
            padding-bottom: 70px;
            padding-left: 15px;
            padding-right: 15px;
        }
        
        /* Desktop specific layout */
        .desktop-content {
            margin-left: 250px;
            padding: 25px;
            width: calc(100% - 250px);
        }
        
        /* Page Header */
        .page-header {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 25px;
            border-left: 4px solid var(--primary-color);
        }
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }
        
        @media (min-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(4, 1fr);
                gap: 20px;
                margin-bottom: 30px;
            }
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 15px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
            border: none;
            transition: transform 0.3s, box-shadow 0.3s;
            border-top: 4px solid transparent;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        
        @media (min-width: 768px) {
            .stat-card {
                padding: 20px;
            }
        }
        
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.12);
        }
        
        .stat-card.users { border-top-color: #4361ee; }
        .stat-card.balance { border-top-color: #28a745; }
        .stat-card.rewards { border-top-color: #ffc107; }
        .stat-card.average { border-top-color: #17a2b8; }
        
        .stat-icon {
            width: 45px;
            height: 45px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
            font-size: 22px;
        }
        
        @media (max-width: 767px) {
            .stat-icon {
                width: 40px;
                height: 40px;
                margin-bottom: 10px;
            }
        }
        
        .stat-icon.users { background: rgba(67, 97, 238, 0.1); color: #4361ee; }
        .stat-icon.balance { 
            background: rgba(40, 167, 69, 0.15);
            color: #28a745; 
            border: 1px solid rgba(40, 167, 69, 0.2);
        }
        .stat-icon.rewards { background: rgba(255, 193, 7, 0.1); color: #ffc107; }
        .stat-icon.average { background: rgba(23, 162, 184, 0.1); color: #17a2b8; }
        
        .stat-value {
            font-size: 20px;
            font-weight: 700;
            color: #2c3e50;
            line-height: 1.2;
            margin-bottom: 5px;
            min-height: 28px;
        }
        
        @media (min-width: 768px) {
            .stat-value {
                font-size: 24px;
            }
        }
        
        .stat-value.balance {
            color: #28a745;
            font-size: 22px;
        }
        
        @media (min-width: 768px) {
            .stat-value.balance {
                font-size: 26px;
            }
        }
        
        .stat-label {
            font-size: 12px;
            color: #7f8c8d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
            margin-bottom: 0;
        }
        
        .stat-label.balance {
            color: #28a745;
            font-weight: 700;
        }
        
        /* Table Container */
        .table-container {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
            margin-top: 20px;
            width: 100%;
        }
        
        .table-header {
            padding: 15px;
            border-bottom: 1px solid #eee;
            display: flex;
            flex-direction: column;
            gap: 15px;
            background: #f8f9fa;
        }
        
        @media (min-width: 768px) {
            .table-header {
                padding: 20px;
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
            }
        }
        
        /* Search Box */
        .search-box {
            position: relative;
            width: 100%;
        }
        
        .search-box input {
            padding-left: 40px;
            border-radius: 25px;
            border: 1px solid #e0e0e0;
            font-size: 14px;
            height: 40px;
            width: 100%;
            transition: all 0.3s;
        }
        
        .search-box input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(67, 97, 238, 0.25);
        }
        
        .search-box i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #adb5bd;
        }
        
        /* Table */
        .table-wrapper {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            width: 100%;
        }
        
        .table {
            margin: 0;
            width: 100%;
            min-width: 600px;
        }
        
        @media (min-width: 768px) {
            .table {
                min-width: auto;
            }
        }
        
        .table thead th {
            background: #f8f9fa;
            color: #495057;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 12px;
            border-bottom: 2px solid #dee2e6;
            white-space: nowrap;
        }
        
        @media (min-width: 768px) {
            .table thead th {
                font-size: 13px;
                padding: 15px;
            }
        }
        
        .table tbody td {
            padding: 12px;
            vertical-align: middle;
            border-bottom: 1px solid #f0f0f0;
            font-size: 13px;
        }
        
        @media (min-width: 768px) {
            .table tbody td {
                padding: 15px;
                font-size: 14px;
            }
        }
        
        .table tbody tr:hover {
            background-color: rgba(67, 97, 238, 0.02);
        }
        
        /* User Avatar */
        .user-avatar {
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 14px;
        }
        
        @media (min-width: 768px) {
            .user-avatar {
                width: 40px;
                height: 40px;
                font-size: 16px;
            }
        }
        
        /* Action Buttons */
        .action-buttons {
            display: flex;
            flex-direction: column;
            gap: 8px;
            min-width: 80px;
        }
        
        @media (min-width: 768px) {
            .action-buttons {
                flex-direction: row;
                gap: 8px;
                min-width: auto;
            }
        }
        
        .btn-action-sm {
            padding: 6px 12px;
            font-size: 12px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            min-height: 36px;
        }
        
        /* User Cards for Mobile */
        .user-cards {
            display: none;
            grid-template-columns: 1fr;
            gap: 15px;
            padding: 15px;
        }
        
        @media (min-width: 480px) and (max-width: 767px) {
            .user-cards {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        .user-card {
            background: white;
            border-radius: 10px;
            padding: 15px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
            border-left: 4px solid var(--primary-color);
            transition: transform 0.3s;
        }
        
        .user-card:hover {
            transform: translateY(-3px);
        }
        
        .user-card-header {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .user-info {
            flex: 1;
        }
        
        .user-id {
            font-size: 11px;
            color: #6c757d;
            margin-bottom: 2px;
        }
        
        .user-name {
            font-weight: 600;
            font-size: 16px;
            color: #2c3e50;
            word-break: break-all;
        }
        
        .user-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin: 15px 0;
        }
        
        .user-stat {
            text-align: center;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .user-stat-label {
            font-size: 11px;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }
        
        .user-stat-value {
            font-weight: 700;
            font-size: 14px;
            color: #2c3e50;
        }
        
        .user-date {
            font-size: 11px;
            color: #adb5bd;
            margin-top: 12px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        /* Badges */
        .badge-balance {
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
            padding: 5px 10px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 12px;
            border: 1px solid rgba(40, 167, 69, 0.2);
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .badge-reward {
            background: rgba(255, 193, 7, 0.1);
            color: #ffc107;
            padding: 5px 10px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 12px;
            border: 1px solid rgba(255, 193, 7, 0.2);
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        /* Toast Notification */
        .toast-container {
            position: fixed;
            top: 70px;
            right: 10px;
            left: 10px;
            z-index: 9999;
        }
        
        @media (min-width: 768px) {
            .toast-container {
                top: 20px;
                right: 20px;
                left: auto;
                max-width: 350px;
            }
        }
        
        .toast {
            border-radius: 8px;
            border: none;
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
            margin-bottom: 10px;
            width: 100%;
        }
        
        /* View Toggle for Mobile */
        .view-toggle {
            display: none;
            background: #f8f9fa;
            border-radius: 8px;
            padding: 3px;
            margin-left: auto;
        }
        
        @media (max-width: 767px) {
            .view-toggle {
                display: flex;
            }
        }
        
        .view-toggle-btn {
            padding: 6px 12px;
            border: none;
            background: none;
            border-radius: 6px;
            font-size: 12px;
            color: #6c757d;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .view-toggle-btn.active {
            background: white;
            color: var(--primary-color);
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        /* Floating Action Button (Mobile) */
        .fab {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 56px;
            height: 56px;
            background: var(--primary-color);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            box-shadow: 0 4px 15px rgba(67, 97, 238, 0.4);
            z-index: 1020;
            border: none;
            transition: all 0.3s;
        }
        
        .fab:hover {
            background: var(--secondary-color);
            transform: scale(1.1);
            box-shadow: 0 6px 20px rgba(67, 97, 238, 0.5);
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px 15px;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }
        
        /* Desktop Title */
        .desktop-title {
            display: none;
        }
        
        @media (min-width: 768px) {
            .desktop-title {
                display: block;
            }
        }
        
        /* Mobile Title */
        .mobile-title {
            display: block;
            font-size: 18px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        @media (min-width: 768px) {
            .mobile-title {
                display: none;
            }
        }
        
        /* Responsive utilities */
        .mobile-only {
            display: block;
        }
        
        .desktop-only {
            display: none;
        }
        
        @media (min-width: 768px) {
            .mobile-only {
                display: none !important;
            }
            
            .desktop-only {
                display: block !important;
            }
        }
        
        /* Fix modal for mobile */
        .modal-dialog {
            margin: 10px;
        }
        
        @media (min-width: 576px) {
            .modal-dialog {
                margin: 30px auto;
                max-width: 500px;
            }
        }
        
        /* Better touch targets */
        .btn, .form-control, .form-select {
            min-height: 44px;
        }
        
        .btn-sm {
            min-height: 36px;
        }
        
        /* Card actions spacing */
        .card-actions {
            display: flex;
            gap: 8px;
            margin-top: 15px;
        }
        
        .card-actions .btn {
            flex: 1;
        }
        
        /* Balance card highlight */
        .balance-highlight {
            position: relative;
            overflow: hidden;
        }
        
        .balance-highlight::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #28a745, #20c997);
        }
        
        /* Loader */
        .loader {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Form validation */
        .was-validated .form-control:invalid {
            border-color: var(--danger-color);
        }
        
        .was-validated .form-control:valid {
            border-color: var(--success-color);
        }
        
        /* Welcome message */
        .welcome-message {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .welcome-message h4 {
            margin: 0;
            font-size: 18px;
        }
        
        .welcome-message p {
            margin: 5px 0 0;
            opacity: 0.9;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <!-- Mobile Header -->
    <div class="mobile-header mobile-only">
        <button class="menu-toggle" onclick="toggleMobileSidebar()">
            <i class="bi bi-list"></i>
        </button>
        <div class="mobile-title">Telebirr Dashboard</div>
        <div style="width: 40px;"></div>
    </div>
    
    <!-- Mobile Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleMobileSidebar()"></div>
    
    <!-- Mobile Sidebar -->
    <div class="mobile-sidebar mobile-only" id="mobileSidebar">
        <div class="sidebar-header">
            <h5 class="mb-1"><i class="bi bi-speedometer2 me-2"></i>Dashboard</h5>
            <p class="text-white-50 mb-0 small">Telebirr Admin</p>
        </div>
        
        <div class="sidebar-menu">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a href="#" class="nav-link active">
                        <i class="bi bi-house-door"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link" data-bs-toggle="modal" data-bs-target="#addUserModal">
                        <i class="bi bi-person-plus"></i> Add User
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link" onclick="refreshDashboard()">
                        <i class="bi bi-arrow-clockwise"></i> Refresh
                    </a>
                </li>
                <li class="nav-item mt-4">
                    <a href="logout.php" class="nav-link text-danger">
                        <i class="bi bi-box-arrow-right"></i> Logout
                    </a>
                </li>
            </ul>
        </div>
        
        <div class="sidebar-stats">
            <h6 class="text-white mb-3"><i class="bi bi-graph-up me-2"></i>Quick Stats</h6>
            <div class="d-flex justify-content-between mb-2">
                <small>Total Users</small>
                <strong><?php echo $total_users; ?></strong>
            </div>
            <div class="d-flex justify-content-between mb-2">
                <small>Total Balance</small>
                <strong class="text-success">$<?php echo number_format($total_balance, 2); ?></strong>
            </div>
            <div class="d-flex justify-content-between mb-2">
                <small>Avg Balance</small>
                <strong>$<?php echo number_format($avg_balance, 2); ?></strong>
            </div>
            <div class="d-flex justify-content-between">
                <small>Total Rewards</small>
                <strong>$<?php echo number_format($total_rewards, 2); ?></strong>
            </div>
        </div>

        <!-- User info in mobile sidebar -->
        <div class="sidebar-user">
            <div class="d-flex align-items-center">
                <div class="user-avatar-sm me-2">
                    <i class="bi bi-person-circle fs-4"></i>
                </div>
                <div class="flex-grow-1">
                    <div class="text-white small"><?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'Admin'); ?></div>
                    <div class="text-white-50 very-small"><?php echo htmlspecialchars($_SESSION['admin_role'] ?? 'Administrator'); ?></div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Desktop Sidebar -->
    <div class="desktop-sidebar desktop-only">
        <div class="sidebar-header">
            <h5 class="mb-1"><i class="bi bi-speedometer2 me-2"></i>Telebirr Dashboard</h5>
            <p class="text-white-50 mb-0 small">Admin Panel</p>
        </div>
        
        <div class="sidebar-menu">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a href="#" class="nav-link active">
                        <i class="bi bi-house-door"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link" data-bs-toggle="modal" data-bs-target="#addUserModal">
                        <i class="bi bi-person-plus"></i> Add User
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link" onclick="refreshDashboard()">
                        <i class="bi bi-arrow-clockwise"></i> Refresh
                    </a>
                </li>
                <li class="nav-item mt-4">
                    <a href="logout.php" class="nav-link text-danger">
                        <i class="bi bi-box-arrow-right"></i> Logout
                    </a>
                </li>
            </ul>
        </div>
        
        <div class="sidebar-stats">
            <h6 class="text-white mb-3"><i class="bi bi-graph-up me-2"></i>Quick Stats</h6>
            <div class="d-flex justify-content-between mb-2">
                <small>Total Users</small>
                <strong><?php echo $total_users; ?></strong>
            </div>
            <div class="d-flex justify-content-between mb-2">
                <small>Total Balance</small>
                <strong class="text-success">$<?php echo number_format($total_balance, 2); ?></strong>
            </div>
            <div class="d-flex justify-content-between mb-2">
                <small>Avg Balance</small>
                <strong>$<?php echo number_format($avg_balance, 2); ?></strong>
            </div>
            <div class="d-flex justify-content-between">
                <small>Total Rewards</small>
                <strong>$<?php echo number_format($total_rewards, 2); ?></strong>
            </div>
        </div>

        <!-- User info in desktop sidebar -->
        <div class="sidebar-user">
            <div class="d-flex align-items-center">
                <div class="user-avatar-sm me-2">
                    <i class="bi bi-person-circle fs-4"></i>
                </div>
                <div class="flex-grow-1">
                    <div class="text-white small"><?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'Admin'); ?></div>
                    <div class="text-white-50 very-small"><?php echo htmlspecialchars($_SESSION['admin_role'] ?? 'Administrator'); ?></div>
                </div>
                <a href="logout.php" class="text-white-50" title="Logout">
                    <i class="bi bi-box-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content <?php echo (isset($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'], 'Mobile') !== false) ? 'mobile-content' : 'desktop-content'; ?>">
        
        <!-- Desktop Title -->
        <div class="desktop-title">
            <div class="page-header">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="h4 mb-1" style="color: #2c3e50;">Telebirr User Management</h2>
                        <p class="text-muted mb-0">Welcome back, <?php echo htmlspecialchars($_SESSION['admin_name'] ?? $_SESSION['admin_username'] ?? 'Admin'); ?>!</p>
                    </div>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                        <i class="bi bi-person-plus me-2"></i> Add New User
                    </button>
                </div>
            </div>
        </div>

        <!-- Mobile Welcome Message -->
        <div class="welcome-message mobile-only">
            <h4>Welcome, <?php echo htmlspecialchars($_SESSION['admin_name'] ?? $_SESSION['admin_username'] ?? 'Admin'); ?>!</h4>
            <p><?php echo date('l, F j, Y'); ?></p>
        </div>
        
        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card users">
                <div class="stat-icon users">
                    <i class="bi bi-people-fill"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo $total_users; ?></div>
                    <div class="stat-label">Total Users</div>
                </div>
            </div>
            
            <div class="stat-card balance balance-highlight">
                <div class="stat-icon balance">
                    <i class="bi bi-wallet2"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value balance">$<?php echo number_format($total_balance, 2); ?></div>
                    <div class="stat-label balance">Total Balance</div>
                </div>
            </div>
            
            <div class="stat-card rewards">
                <div class="stat-icon rewards">
                    <i class="bi bi-gift-fill"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value">$<?php echo number_format($total_rewards, 2); ?></div>
                    <div class="stat-label">Total Rewards</div>
                </div>
            </div>
            
            <div class="stat-card average">
                <div class="stat-icon average">
                    <i class="bi bi-graph-up"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value">$<?php echo number_format($avg_balance, 2); ?></div>
                    <div class="stat-label">Average Balance</div>
                </div>
            </div>
        </div>
        
        <!-- Table Header with View Toggle -->
        <div class="table-header">
            <div class="search-box">
                <i class="bi bi-search"></i>
                <input type="text" class="form-control" placeholder="Search users..." id="searchInput">
            </div>
            <div class="d-flex align-items-center gap-2">
                <div class="view-toggle mobile-only">
                    <button class="view-toggle-btn active" onclick="setView('table')">
                        <i class="bi bi-table"></i>
                    </button>
                    <button class="view-toggle-btn" onclick="setView('cards')">
                        <i class="bi bi-grid"></i>
                    </button>
                </div>
                <button class="btn btn-outline-secondary btn-sm desktop-only" onclick="refreshDashboard()">
                    <i class="bi bi-arrow-clockwise"></i> Refresh
                </button>
                <button class="btn btn-outline-secondary btn-sm desktop-only" onclick="exportData()">
                    <i class="bi bi-download"></i> Export
                </button>
            </div>
        </div>
        
        <!-- Table View -->
        <div class="table-container">
            <div class="table-wrapper" id="tableView">
                <table class="table" id="usersTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Balance</th>
                            <th>Reward</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $result = $conn->query("SELECT * FROM users ORDER BY id DESC");
                        if ($result->num_rows > 0):
                            while($row = $result->fetch_assoc()):
                                $initial = strtoupper(substr($row['username'], 0, 1));
                        ?>
                        <tr data-id="<?php echo $row['id']; ?>">
                            <td>
                                <span class="badge bg-dark">#<?php echo $row['id']; ?></span>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="user-avatar me-2">
                                        <?php echo $initial; ?>
                                    </div>
                                    <div>
                                        <strong class="d-block"><?php echo htmlspecialchars($row['username']); ?></strong>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge-balance">
                                    <i class="bi bi-currency-dollar"></i>
                                    <?php echo number_format($row['balance'], 2); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge-reward">
                                    <i class="bi bi-gift"></i>
                                    <?php echo number_format($row['reward'], 2); ?>
                                </span>
                            </td>
                            <td>
                                <?php 
                                $date = new DateTime($row['created_at']);
                                echo '<span class="d-block">' . $date->format('M d, Y') . '</span>';
                                echo '<small class="text-muted">' . $date->format('h:i A') . '</small>';
                                ?>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn btn-warning btn-sm btn-action-sm" onclick="editUser(<?php echo $row['id']; ?>)">
                                        <i class="bi bi-pencil"></i> <span class="desktop-only">Edit</span>
                                    </button>
                                    <button class="btn btn-info btn-sm btn-action-sm" onclick="copyToClipboard('<?php echo $row['username']; ?>')">
                                        <i class="bi bi-copy"></i> <span class="desktop-only">Copy</span>
                                    </button>
                                    <button class="btn btn-danger btn-sm btn-action-sm" onclick="deleteUser(<?php echo $row['id']; ?>)">
                                        <i class="bi bi-trash"></i> <span class="desktop-only">Delete</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php 
                            endwhile;
                        else:
                        ?>
                        <tr>
                            <td colspan="6" class="text-center py-4">
                                <div class="empty-state">
                                    <i class="bi bi-people"></i>
                                    <h5>No users found</h5>
                                    <p>Add your first user</p>
                                    <button class="btn btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#addUserModal">
                                        <i class="bi bi-person-plus me-2"></i> Add User
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Cards View (Mobile) -->
            <div class="user-cards" id="cardsView">
                <?php
                // Reset result pointer
                if ($result->num_rows > 0):
                    $result->data_seek(0);
                    while($row = $result->fetch_assoc()):
                        $initial = strtoupper(substr($row['username'], 0, 1));
                ?>
                <div class="user-card" data-username="<?php echo htmlspecialchars($row['username']); ?>">
                    <div class="user-card-header">
                        <div class="user-avatar me-3">
                            <?php echo $initial; ?>
                        </div>
                        <div class="user-info">
                            <div class="user-id">ID: #<?php echo $row['id']; ?></div>
                            <div class="user-name"><?php echo htmlspecialchars($row['username']); ?></div>
                        </div>
                    </div>
                    
                    <div class="user-stats">
                        <div class="user-stat">
                            <div class="user-stat-label">Balance</div>
                            <div class="user-stat-value text-success">
                                $<?php echo number_format($row['balance'], 2); ?>
                            </div>
                        </div>
                        <div class="user-stat">
                            <div class="user-stat-label">Reward</div>
                            <div class="user-stat-value text-warning">
                                $<?php echo number_format($row['reward'], 2); ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="user-date">
                        <i class="bi bi-calendar"></i>
                        <?php echo date('M d, Y', strtotime($row['created_at'])); ?>
                    </div>
                    
                    <div class="card-actions">
                        <button class="btn btn-warning btn-sm" onclick="editUser(<?php echo $row['id']; ?>)">
                            <i class="bi bi-pencil me-1"></i> Edit
                        </button>
                        <button class="btn btn-info btn-sm" onclick="copyToClipboard('<?php echo $row['username']; ?>')">
                            <i class="bi bi-copy me-1"></i> Copy
                        </button>
                        <button class="btn btn-danger btn-sm" onclick="deleteUser(<?php echo $row['id']; ?>)">
                            <i class="bi bi-trash me-1"></i> Delete
                        </button>
                    </div>
                </div>
                <?php 
                    endwhile;
                endif;
                ?>
            </div>
        </div>
    </div>
    
    <!-- Floating Action Button (Mobile) -->
    <button class="fab mobile-only" data-bs-toggle="modal" data-bs-target="#addUserModal">
        <i class="bi bi-plus-lg"></i>
    </button>
    
    <!-- Toast Container -->
    <div class="toast-container"></div>
    
    <!-- Add User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-person-plus me-2"></i>Add New User
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="addUserForm" method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="username" class="form-label">Username *</label>
                            <input type="text" class="form-control" id="username" name="username" required 
                                   placeholder="Enter username">
                            <div class="invalid-feedback">Please enter a username.</div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="balance" class="form-label">Balance ($)</label>
                                <input type="number" class="form-control" id="balance" name="balance" 
                                       step="0.01" value="0.00" min="0" required>
                                <div class="invalid-feedback">Please enter a valid balance.</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="reward" class="form-label">Reward ($)</label>
                                <input type="number" class="form-control" id="reward" name="reward" 
                                       step="0.01" value="0.00" min="0" required>
                                <div class="invalid-feedback">Please enter a valid reward amount.</div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="addUserBtn">
                            <i class="bi bi-person-plus me-2"></i>Add User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-pencil me-2"></i>Edit User
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="editUserForm" method="POST">
                    <div class="modal-body">
                        <input type="hidden" id="edit_id" name="id">
                        <div class="mb-3">
                            <label for="edit_username" class="form-label">Username *</label>
                            <input type="text" class="form-control" id="edit_username" name="username" required>
                            <div class="invalid-feedback">Please enter a username.</div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_balance" class="form-label">Balance ($)</label>
                                <input type="number" class="form-control" id="edit_balance" name="balance" 
                                       step="0.01" min="0" required>
                                <div class="invalid-feedback">Please enter a valid balance.</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_reward" class="form-label">Reward ($)</label>
                                <input type="number" class="form-control" id="edit_reward" name="reward" 
                                       step="0.01" min="0" required>
                                <div class="invalid-feedback">Please enter a valid reward amount.</div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning text-white" id="editUserBtn">
                            <i class="bi bi-check-circle me-2"></i>Update User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle Mobile Sidebar
        function toggleMobileSidebar() {
            const sidebar = document.getElementById('mobileSidebar');
            const overlay = document.getElementById('sidebarOverlay');
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
        }
        
        // Close sidebar when clicking on a link (mobile)
        document.querySelectorAll('.mobile-sidebar .nav-link').forEach(link => {
            link.addEventListener('click', function() {
                if (window.innerWidth <= 767) {
                    setTimeout(() => {
                        toggleMobileSidebar();
                    }, 300);
                }
            });
        });
        
        // View Toggle for Mobile
        function setView(view) {
            const tableView = document.getElementById('tableView');
            const cardsView = document.getElementById('cardsView');
            const viewButtons = document.querySelectorAll('.view-toggle-btn');
            
            viewButtons.forEach(btn => btn.classList.remove('active'));
            
            if (view === 'table') {
                tableView.style.display = 'block';
                cardsView.style.display = 'none';
                event.target.classList.add('active');
                localStorage.setItem('preferredView', 'table');
            } else {
                tableView.style.display = 'none';
                cardsView.style.display = 'grid';
                event.target.classList.add('active');
                localStorage.setItem('preferredView', 'cards');
            }
        }
        
        // Load preferred view on mobile
        window.addEventListener('load', () => {
            if (window.innerWidth <= 767) {
                const preferredView = localStorage.getItem('preferredView') || 'cards';
                setView(preferredView);
            }
            
            // Check for session timeout message
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('timeout') === '1') {
                showToast('Session expired. Please login again.', 'warning');
            }
        });
        
        // Search Functionality
        document.getElementById('searchInput').addEventListener('input', function(e) {
            const searchTerm = this.value.toLowerCase();
            
            // Table search
            const tableRows = document.querySelectorAll('#usersTable tbody tr');
            tableRows.forEach(row => {
                if (row.cells[1]) {
                    const username = row.cells[1].textContent.toLowerCase();
                    row.style.display = username.includes(searchTerm) ? '' : 'none';
                }
            });
            
            // Cards search
            const cards = document.querySelectorAll('.user-card');
            cards.forEach(card => {
                const username = card.getAttribute('data-username').toLowerCase();
                card.style.display = username.includes(searchTerm) ? '' : 'none';
            });
        });
        
        // Add User Form Submission
        document.getElementById('addUserForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const form = this;
            const submitBtn = document.getElementById('addUserBtn');
            const originalText = submitBtn.innerHTML;
            
            // Validate form
            if (!form.checkValidity()) {
                form.classList.add('was-validated');
                return;
            }
            
            // Show loading state
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Adding...';
            submitBtn.disabled = true;
            
            try {
                const formData = new FormData(form);
                
                const response = await fetch('add_user.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if(data.status === 'success') {
                    showToast(data.message || 'User added successfully!', 'success');
                    
                    // Close modal and reset form
                    const modal = bootstrap.Modal.getInstance(document.getElementById('addUserModal'));
                    modal.hide();
                    form.reset();
                    form.classList.remove('was-validated');
                    
                    // Refresh the page after a delay
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                    
                } else {
                    showToast(data.message || 'Error adding user', 'error');
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }
                
            } catch (error) {
                showToast('Network error. Please try again.', 'error');
                console.error('Error:', error);
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }
        });
        
        // Edit User Form Submission
        document.getElementById('editUserForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const form = this;
            const submitBtn = document.getElementById('editUserBtn');
            const originalText = submitBtn.innerHTML;
            
            // Validate form
            if (!form.checkValidity()) {
                form.classList.add('was-validated');
                return;
            }
            
            // Show loading state
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Updating...';
            submitBtn.disabled = true;
            
            try {
                const formData = new FormData(form);
                
                const response = await fetch('update_user.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if(data.status === 'success' || data.status === 'info') {
                    showToast(data.message, data.status === 'success' ? 'success' : 'info');
                    
                    // Close modal and reset form
                    const modal = bootstrap.Modal.getInstance(document.getElementById('editUserModal'));
                    modal.hide();
                    form.reset();
                    form.classList.remove('was-validated');
                    
                    // Refresh the page after a delay
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                    
                } else {
                    showToast(data.message || 'Error updating user', 'error');
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }
                
            } catch (error) {
                showToast('Network error. Please try again.', 'error');
                console.error('Error:', error);
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }
        });
        
        // Edit User Function
        async function editUser(id) {
            try {
                const response = await fetch('get_user.php?id=' + id);
                const data = await response.json();
                
                if(data.status === 'success') {
                    document.getElementById('edit_id').value = data.data.id;
                    document.getElementById('edit_username').value = data.data.username;
                    document.getElementById('edit_balance').value = data.data.balance;
                    document.getElementById('edit_reward').value = data.data.reward;
                    
                    const editModal = new bootstrap.Modal(document.getElementById('editUserModal'));
                    editModal.show();
                } else {
                    showToast(data.message || 'Error loading user data', 'error');
                }
            } catch (error) {
                showToast('Network error. Please try again.', 'error');
                console.error('Error:', error);
            }
        }
        
        // Delete User Function
        async function deleteUser(id) {
            if(!confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
                return;
            }
            
            try {
                const response = await fetch('delete_user.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({id: id})
                });
                
                const data = await response.json();
                
                if(data.status === 'success') {
                    showToast(data.message, 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast(data.message || 'Error deleting user', 'error');
                }
            } catch (error) {
                showToast('Network error. Please try again.', 'error');
                console.error('Error:', error);
            }
        }
        
        // Copy to Clipboard
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                showToast('Username copied to clipboard!', 'success');
            }).catch(err => {
                showToast('Failed to copy to clipboard', 'error');
                console.error('Copy error:', err);
            });
        }
        
        // Refresh Dashboard
        function refreshDashboard() {
            showToast('Refreshing data...', 'info');
            setTimeout(() => location.reload(), 500);
        }
        
        // Export Data
        function exportData() {
            showToast('Export feature coming soon!', 'info');
        }
        
        // Show toast notification
        function showToast(message, type = 'info') {
            // Create toast container if it doesn't exist
            let toastContainer = document.querySelector('.toast-container');
            if (!toastContainer) {
                toastContainer = document.createElement('div');
                toastContainer.className = 'toast-container';
                document.body.appendChild(toastContainer);
            }
            
            const toastId = 'toast-' + Date.now();
            
            let icon = 'bi-info-circle';
            let bgClass = 'bg-primary';
            
            switch(type) {
                case 'success':
                    icon = 'bi-check-circle';
                    bgClass = 'bg-success';
                    break;
                case 'error':
                    icon = 'bi-exclamation-circle';
                    bgClass = 'bg-danger';
                    break;
                case 'warning':
                    icon = 'bi-exclamation-triangle';
                    bgClass = 'bg-warning';
                    break;
            }
            
            const toastHTML = `
                <div id="${toastId}" class="toast ${bgClass} text-white" role="alert">
                    <div class="toast-header ${bgClass} text-white border-0">
                        <i class="bi ${icon} me-2"></i>
                        <strong class="me-auto">${type.charAt(0).toUpperCase() + type.slice(1)}</strong>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
                    </div>
                    <div class="toast-body">
                        ${message}
                    </div>
                </div>
            `;
            
            toastContainer.insertAdjacentHTML('beforeend', toastHTML);
            
            const toastElement = document.getElementById(toastId);
            const toast = new bootstrap.Toast(toastElement, {
                autohide: true,
                delay: 3000
            });
            toast.show();
            
            // Remove toast after hiding
            toastElement.addEventListener('hidden.bs.toast', function () {
                this.remove();
            });
        }
        
        // Initialize modals
        document.addEventListener('DOMContentLoaded', function() {
            // Clear form on modal close
            const addModal = document.getElementById('addUserModal');
            if (addModal) {
                addModal.addEventListener('hidden.bs.modal', function () {
                    const form = document.getElementById('addUserForm');
                    form.reset();
                    form.classList.remove('was-validated');
                });
            }
            
            const editModal = document.getElementById('editUserModal');
            if (editModal) {
                editModal.addEventListener('hidden.bs.modal', function () {
                    const form = document.getElementById('editUserForm');
                    form.reset();
                    form.classList.remove('was-validated');
                });
            }
        });
        
        // Handle window resize
        window.addEventListener('resize', function() {
            if (window.innerWidth > 767) {
                // Show table view on desktop
                document.getElementById('tableView').style.display = 'block';
                document.getElementById('cardsView').style.display = 'none';
            } else {
                // Load preferred view on mobile
                const preferredView = localStorage.getItem('preferredView') || 'cards';
                setView(preferredView);
            }
        });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + F to focus search
            if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
                e.preventDefault();
                document.getElementById('searchInput').focus();
            }
            
            // Escape to clear search
            if (e.key === 'Escape' && document.activeElement.id === 'searchInput') {
                document.getElementById('searchInput').value = '';
                document.getElementById('searchInput').dispatchEvent(new Event('input'));
            }
        });
        
        // Auto-refresh every 30 seconds (optional)
        // setInterval(refreshDashboard, 30000);
    </script>
</body>
</html>
<?php $conn->close(); ?>