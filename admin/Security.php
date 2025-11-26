<?php
session_start();
require_once '../database/config.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

$pdo = getDBConnection();
$error = '';
$success = '';
$cleanup_message = '';

function cleanupOldAuditLogs($pdo, $type = 'login', $deleteAll = false) {
    try {
        if ($deleteAll) {
            if ($type === 'profile') {
                $stmt = $pdo->prepare("DELETE FROM profile_audit");
            } else {
                $stmt = $pdo->prepare("DELETE FROM login_audit");
            }
            $stmt->execute();
        } else {
            $threeMonthsAgo = date('Y-m-d H:i:s', strtotime('-3 months'));
            if ($type === 'profile') {
                $stmt = $pdo->prepare("DELETE FROM profile_audit WHERE action_time < ?");
            } else {
                $stmt = $pdo->prepare("DELETE FROM login_audit WHERE login_time < ?");
            }
            $stmt->execute([$threeMonthsAgo]);
        }
        return $stmt->rowCount();
    } catch (PDOException $e) {
        error_log("Failed to cleanup audit logs: " . $e->getMessage());
        return false;
    }
}

function shouldRunAutoCleanup() {
    $cleanup_file = __DIR__ . '/.last_cleanup';
    if (!file_exists($cleanup_file)) {
        return true;
    }
    $last_cleanup = file_get_contents($cleanup_file);
    $last_cleanup_time = $last_cleanup ? (int)$last_cleanup : 0;
    $one_day_ago = time() - (24 * 60 * 60);
    return $last_cleanup_time < $one_day_ago;
}

function recordCleanupTime() {
    $cleanup_file = __DIR__ . '/.last_cleanup';
    file_put_contents($cleanup_file, time());
}

$audit_type = $_GET['type'] ?? 'login';

if (isset($_GET['action']) && $_GET['action'] === 'cleanup') {
    $deleted_count = cleanupOldAuditLogs($pdo, $audit_type, true);
    if ($deleted_count !== false) {
        $success = "Successfully deleted {$deleted_count} audit log records.";
        recordCleanupTime();
    } else {
        $error = 'Failed to cleanup audit logs.';
    }
}

if (shouldRunAutoCleanup()) {
    $deleted_count_login = cleanupOldAuditLogs($pdo, 'login');
    $deleted_count_profile = cleanupOldAuditLogs($pdo, 'profile');
    $total_deleted = ($deleted_count_login !== false ? $deleted_count_login : 0) + ($deleted_count_profile !== false ? $deleted_count_profile : 0);
    if ($total_deleted > 0) {
        $cleanup_message = "Automatically deleted {$total_deleted} old audit log records (older than 3 months).";
        recordCleanupTime();
    } elseif ($deleted_count_login !== false && $deleted_count_profile !== false) {
        recordCleanupTime();
    }
}

$filter_status = $_GET['status'] ?? 'all';
$filter_action = $_GET['action_type'] ?? 'all';
$filter_email = $_GET['email'] ?? '';
$filter_date = $_GET['date'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;
$total_records = 0;

try {
    if ($audit_type === 'profile') {
        $where_conditions = [];
        $params = [];
        
        if ($filter_action !== 'all') {
            $where_conditions[] = "pa.action_type = ?";
            $params[] = $filter_action;
        }
        
        if (!empty($filter_email)) {
            $where_conditions[] = "pa.email LIKE ?";
            $params[] = "%{$filter_email}%";
        }
        
        if (!empty($filter_date)) {
            $where_conditions[] = "DATE(pa.action_time) = ?";
            $params[] = $filter_date;
        }
        
        $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";
        
        $count_sql = "SELECT COUNT(*) as total FROM profile_audit pa $where_clause";
        $count_stmt = $pdo->prepare($count_sql);
        $count_stmt->execute($params);
        $total_records = $count_stmt->fetch()['total'];
        $total_pages = ceil($total_records / $per_page);
        
        $sql = "SELECT pa.*, t.full_name 
                FROM profile_audit pa 
                LEFT JOIN technician t ON pa.user_id = t.id 
                $where_clause 
                ORDER BY pa.action_time DESC 
                LIMIT ? OFFSET ?";
        
        $params[] = $per_page;
        $params[] = $offset;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $audit_logs = $stmt->fetchAll();
        
        $stats_sql = "SELECT 
                        COUNT(*) as total_edits,
                        COUNT(DISTINCT user_id) as unique_users,
                        SUM(CASE WHEN action_type = 'change_password' THEN 1 ELSE 0 END) as password_changes
                      FROM profile_audit";
        $stats_stmt = $pdo->query($stats_sql);
        $stats = $stats_stmt->fetch();
    } else {
        $where_conditions = [];
        $params = [];
        
        if ($filter_status !== 'all') {
            $where_conditions[] = "la.login_status = ?";
            $params[] = $filter_status;
        }
        
        if (!empty($filter_email)) {
            $where_conditions[] = "la.email LIKE ?";
            $params[] = "%{$filter_email}%";
        }
        
        if (!empty($filter_date)) {
            $where_conditions[] = "DATE(la.login_time) = ?";
            $params[] = $filter_date;
        }
        
        $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";
        
        $count_sql = "SELECT COUNT(*) as total FROM login_audit la $where_clause";
        $count_stmt = $pdo->prepare($count_sql);
        $count_stmt->execute($params);
        $total_records = $count_stmt->fetch()['total'];
        $total_pages = ceil($total_records / $per_page);
        
        $sql = "SELECT la.*, t.full_name 
                FROM login_audit la 
                LEFT JOIN technician t ON la.user_id = t.id 
                $where_clause 
                ORDER BY la.login_time DESC 
                LIMIT ? OFFSET ?";
        
        $params[] = $per_page;
        $params[] = $offset;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $audit_logs = $stmt->fetchAll();
        
        $stats_sql = "SELECT 
                        COUNT(*) as total_attempts,
                        SUM(CASE WHEN login_status = 'success' THEN 1 ELSE 0 END) as successful,
                        SUM(CASE WHEN login_status = 'failed' THEN 1 ELSE 0 END) as failed
                      FROM login_audit";
        $stats_stmt = $pdo->query($stats_sql);
        $stats = $stats_stmt->fetch();
    }
    
} catch (PDOException $e) {
    $error = 'Failed to load audit logs.';
    $audit_logs = [];
    if ($audit_type === 'profile') {
        $stats = ['total_edits' => 0, 'unique_users' => 0, 'password_changes' => 0];
    } else {
        $stats = ['total_attempts' => 0, 'successful' => 0, 'failed' => 0];
    }
    $total_pages = 0;
    $total_records = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security & Audit Trails - UniKL RCMP IT Inventory</title>
    <link rel="icon" type="image/png" href="../public/rcmp.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/TechDashboard.css">
    <style>
        .security-page-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 40px 20px 80px;
        }

        .page-header {
            margin-bottom: 30px;
        }

        .page-header h1 {
            font-size: 2rem;
            font-weight: 700;
            color: #000000;
            margin-bottom: 10px;
        }

        .page-header p {
            color: #636e72;
            font-size: 1rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.7);
            border: 1px solid rgba(0, 0, 0, 0.05);
            border-radius: 12px;
            padding: 24px;
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
        }

        .stat-card .stat-label {
            font-size: 0.85rem;
            color: #636e72;
            font-weight: 500;
            margin-bottom: 8px;
        }

        .stat-card .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #1a1a2e;
        }

        .stat-card.success .stat-value {
            color: #00b894;
        }

        .stat-card.failed .stat-value {
            color: #d63031;
        }

        .filters-card {
            background: rgba(255, 255, 255, 0.7);
            border: 1px solid rgba(0, 0, 0, 0.05);
            border-radius: 12px;
            padding: 24px;
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
        }

        .filters-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-group label {
            font-size: 0.9rem;
            font-weight: 600;
            color: #2d3436;
        }

        .form-group select,
        .form-group input {
            padding: 10px 14px;
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            font-size: 0.95rem;
            transition: all 0.2s ease;
            font-family: 'Inter', sans-serif;
            background: #ffffff;
        }

        .form-group select:focus,
        .form-group input:focus {
            outline: none;
            border-color: #1a1a2e;
            box-shadow: 0 0 0 3px rgba(26, 26, 46, 0.1);
        }

        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-primary {
            background: #1a1a2e;
            color: #ffffff;
        }

        .btn-primary:hover {
            background: #0f0f1a;
            box-shadow: 0 4px 12px rgba(26, 26, 46, 0.3);
        }

        .btn-secondary {
            background: #f8f9fa;
            color: #2d3436;
            border: 1px solid rgba(0, 0, 0, 0.1);
        }

        .btn-secondary:hover {
            background: #e9ecef;
        }

        .table-card {
            background: rgba(255, 255, 255, 0.7);
            border: 1px solid rgba(0, 0, 0, 0.05);
            border-radius: 12px;
            padding: 24px;
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            overflow-x: auto;
        }

        .audit-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }

        .audit-table thead {
            background: #f8f9fa;
            border-bottom: 2px solid rgba(0, 0, 0, 0.1);
        }

        .audit-table th {
            padding: 14px 12px;
            text-align: left;
            font-weight: 600;
            color: #2d3436;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .audit-table td {
            padding: 14px 12px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            color: #2d3436;
        }

        .audit-table tbody tr:hover {
            background: #f8f9fa;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-badge.success {
            background: rgba(0, 184, 148, 0.1);
            color: #00b894;
        }

        .status-badge.failed {
            background: rgba(214, 48, 49, 0.1);
            color: #d63031;
        }

        .pagination-wrapper {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid rgba(0, 0, 0, 0.05);
            flex-wrap: wrap;
            gap: 15px;
        }

        .pagination-info {
            color: #636e72;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
        }

        .pagination a,
        .pagination span {
            padding: 10px 16px;
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            text-decoration: none;
            color: #2d3436;
            font-weight: 600;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.9rem;
        }

        .pagination a:hover {
            background: #1a1a2e;
            color: #ffffff;
            border-color: #1a1a2e;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(26, 26, 46, 0.2);
        }

        .pagination .current {
            background: #1a1a2e;
            color: #ffffff;
            border-color: #1a1a2e;
        }

        .pagination .disabled {
            opacity: 0.4;
            cursor: not-allowed;
            pointer-events: none;
            background: #f8f9fa;
        }

        .pagination-btn {
            padding: 10px 20px;
            background: #1a1a2e;
            color: #ffffff;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
        }

        .pagination-btn:hover:not(:disabled) {
            background: #0f0f1a;
            box-shadow: 0 4px 12px rgba(26, 26, 46, 0.3);
            transform: translateY(-2px);
        }

        .pagination-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            background: #e9ecef;
            color: #636e72;
        }

        .no-data {
            text-align: center;
            padding: 40px;
            color: #636e72;
        }

        .no-data i {
            font-size: 3rem;
            margin-bottom: 15px;
            opacity: 0.3;
        }

        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            border-bottom: 2px solid rgba(0, 0, 0, 0.1);
        }

        .tab {
            padding: 12px 24px;
            background: transparent;
            border: none;
            border-bottom: 3px solid transparent;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.95rem;
            color: #636e72;
            transition: all 0.2s ease;
            position: relative;
            top: 2px;
        }

        .tab:hover {
            color: #1a1a2e;
        }

        .tab.active {
            color: #1a1a2e;
            border-bottom-color: #1a1a2e;
        }

        .action-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            background: rgba(108, 92, 231, 0.1);
            color: #6c5ce7;
        }

        .fields-changed {
            max-width: 300px;
            font-size: 0.85rem;
            color: #636e72;
        }

        @media (max-width: 768px) {
            .filters-form {
                grid-template-columns: 1fr;
            }

            .audit-table {
                font-size: 0.8rem;
            }

            .audit-table th,
            .audit-table td {
                padding: 10px 8px;
            }

            .tabs {
                flex-wrap: wrap;
            }
        }
    </style>
</head>
<body>
    <?php include_once("../components/ADMINheader.php"); ?>

    <div class="security-page-container">
        <div class="page-header" style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 20px;">
            <div>
                <h1><i class="fa fa-shield-alt"></i> Security & Audit Trails</h1>
                <p>Monitor and review all login attempts and profile edit activities.</p>
            </div>
            <a href="?action=cleanup&type=<?php echo htmlspecialchars($audit_type); ?>&status=<?php echo htmlspecialchars($filter_status); ?>&action_type=<?php echo htmlspecialchars($filter_action); ?>&email=<?php echo htmlspecialchars($filter_email); ?>&date=<?php echo htmlspecialchars($filter_date); ?>" 
               class="btn btn-primary" 
               onclick="return confirm('Are you sure you want to delete ALL audit logs? This action cannot be undone.');"
               style="padding: 10px 20px; border-radius: 8px; border: none; font-weight: 600; cursor: pointer; font-size: 0.95rem; display: flex; align-items: center; gap: 8px; text-decoration: none; background: #1a1a2e; color: #ffffff; transition: all 0.2s ease;">
                <i class="fa fa-trash-alt"></i>
                Delete All Logs
            </a>
        </div>

        <div class="tabs">
            <a href="?type=login&status=<?php echo htmlspecialchars($filter_status); ?>&email=<?php echo htmlspecialchars($filter_email); ?>&date=<?php echo htmlspecialchars($filter_date); ?>" 
               class="tab <?php echo $audit_type === 'login' ? 'active' : ''; ?>" 
               style="text-decoration: none; color: inherit;">
                <i class="fa fa-sign-in-alt"></i> Login Audit
            </a>
            <a href="?type=profile&action_type=<?php echo htmlspecialchars($filter_action); ?>&email=<?php echo htmlspecialchars($filter_email); ?>&date=<?php echo htmlspecialchars($filter_date); ?>" 
               class="tab <?php echo $audit_type === 'profile' ? 'active' : ''; ?>" 
               style="text-decoration: none; color: inherit;">
                <i class="fa fa-user-edit"></i> Profile Edit Audit
            </a>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error" style="background: rgba(214, 48, 49, 0.1); color: #d63031; padding: 14px 20px; border-radius: 8px; margin-bottom: 25px; border: 1px solid rgba(214, 48, 49, 0.2); display: flex; align-items: center; gap: 12px;">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success" style="background: rgba(0, 184, 148, 0.1); color: #00b894; padding: 14px 20px; border-radius: 8px; margin-bottom: 25px; border: 1px solid rgba(0, 184, 148, 0.2); display: flex; align-items: center; gap: 12px;">
                <i class="fa-solid fa-circle-check"></i>
                <span><?php echo htmlspecialchars($success); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($cleanup_message): ?>
            <div class="alert alert-info" style="background: rgba(108, 92, 231, 0.1); color: #6c5ce7; padding: 14px 20px; border-radius: 8px; margin-bottom: 25px; border: 1px solid rgba(108, 92, 231, 0.2); display: flex; align-items: center; gap: 12px;">
                <i class="fa-solid fa-info-circle"></i>
                <span><?php echo htmlspecialchars($cleanup_message); ?></span>
            </div>
        <?php endif; ?>

        <div class="stats-grid">
            <?php if ($audit_type === 'profile'): ?>
                <div class="stat-card">
                    <div class="stat-label">Total Profile Edits</div>
                    <div class="stat-value"><?php echo number_format($stats['total_edits'] ?? 0); ?></div>
                </div>
                <div class="stat-card success">
                    <div class="stat-label">Unique Users</div>
                    <div class="stat-value"><?php echo number_format($stats['unique_users'] ?? 0); ?></div>
                </div>
                <div class="stat-card failed">
                    <div class="stat-label">Password Changes</div>
                    <div class="stat-value"><?php echo number_format($stats['password_changes'] ?? 0); ?></div>
                </div>
            <?php else: ?>
                <div class="stat-card">
                    <div class="stat-label">Total Login Attempts</div>
                    <div class="stat-value"><?php echo number_format($stats['total_attempts'] ?? 0); ?></div>
                </div>
                <div class="stat-card success">
                    <div class="stat-label">Successful Logins</div>
                    <div class="stat-value"><?php echo number_format($stats['successful'] ?? 0); ?></div>
                </div>
                <div class="stat-card failed">
                    <div class="stat-label">Failed Attempts</div>
                    <div class="stat-value"><?php echo number_format($stats['failed'] ?? 0); ?></div>
                </div>
            <?php endif; ?>
        </div>

        <div class="filters-card">
            <form method="GET" action="" class="filters-form">
                <input type="hidden" name="type" value="<?php echo htmlspecialchars($audit_type); ?>">
                <?php if ($audit_type === 'profile'): ?>
                    <div class="form-group">
                        <label for="action_type">Action Type</label>
                        <select id="action_type" name="action_type">
                            <option value="all" <?php echo $filter_action === 'all' ? 'selected' : ''; ?>>All Actions</option>
                            <option value="update_profile" <?php echo $filter_action === 'update_profile' ? 'selected' : ''; ?>>Update Profile</option>
                            <option value="change_password" <?php echo $filter_action === 'change_password' ? 'selected' : ''; ?>>Change Password</option>
                            <option value="upload_picture" <?php echo $filter_action === 'upload_picture' ? 'selected' : ''; ?>>Upload Picture</option>
                            <option value="update_email" <?php echo $filter_action === 'update_email' ? 'selected' : ''; ?>>Update Email</option>
                            <option value="update_phone" <?php echo $filter_action === 'update_phone' ? 'selected' : ''; ?>>Update Phone</option>
                            <option value="update_name" <?php echo $filter_action === 'update_name' ? 'selected' : ''; ?>>Update Name</option>
                        </select>
                    </div>
                <?php else: ?>
                    <div class="form-group">
                        <label for="status">Login Status</label>
                        <select id="status" name="status">
                            <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="success" <?php echo $filter_status === 'success' ? 'selected' : ''; ?>>Success</option>
                            <option value="failed" <?php echo $filter_status === 'failed' ? 'selected' : ''; ?>>Failed</option>
                        </select>
                    </div>
                <?php endif; ?>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="text" id="email" name="email" value="<?php echo htmlspecialchars($filter_email); ?>" placeholder="Search by email">
                </div>
                <div class="form-group">
                    <label for="date">Date</label>
                    <input type="date" id="date" name="date" value="<?php echo htmlspecialchars($filter_date); ?>">
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fa fa-search"></i> Filter
                    </button>
                </div>
                <?php if (($audit_type === 'login' && ($filter_status !== 'all' || !empty($filter_email) || !empty($filter_date))) || ($audit_type === 'profile' && ($filter_action !== 'all' || !empty($filter_email) || !empty($filter_date)))): ?>
                <div class="form-group">
                    <a href="Security.php?type=<?php echo htmlspecialchars($audit_type); ?>" class="btn btn-secondary">
                        <i class="fa fa-times"></i> Clear
                    </a>
                </div>
                <?php endif; ?>
            </form>
        </div>

        <div class="table-card">
            <?php if (empty($audit_logs)): ?>
                <div class="no-data">
                    <i class="fa fa-inbox"></i>
                    <p>No audit logs found.</p>
                </div>
            <?php else: ?>
                <?php if ($audit_type === 'profile'): ?>
                    <table class="audit-table">
                        <thead>
                            <tr>
                                <th>Date & Time</th>
                                <th>User</th>
                                <th>Email</th>
                                <th>Staff ID</th>
                                <th>Action Type</th>
                                <th>Fields Changed</th>
                                <th>IP Address</th>
                                <th>User Agent</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($audit_logs as $log): ?>
                                <tr>
                                    <td><?php echo date('Y-m-d H:i:s', strtotime($log['action_time'])); ?></td>
                                    <td><?php echo htmlspecialchars($log['full_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($log['email']); ?></td>
                                    <td><?php echo htmlspecialchars($log['staff_id'] ?? 'N/A'); ?></td>
                                    <td>
                                        <span class="action-badge">
                                            <?php echo htmlspecialchars(str_replace('_', ' ', $log['action_type'])); ?>
                                        </span>
                                    </td>
                                    <td class="fields-changed">
                                        <?php 
                                        $fields = json_decode($log['fields_changed'] ?? '[]', true);
                                        if (!empty($fields)) {
                                            echo htmlspecialchars(implode(', ', $fields));
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($log['ip_address'] ?? 'N/A'); ?></td>
                                    <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo htmlspecialchars($log['user_agent'] ?? 'N/A'); ?>">
                                        <?php echo htmlspecialchars($log['user_agent'] ?? 'N/A'); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <table class="audit-table">
                        <thead>
                            <tr>
                                <th>Date & Time</th>
                                <th>User</th>
                                <th>Email</th>
                                <th>Staff ID</th>
                                <th>IP Address</th>
                                <th>Status</th>
                                <th>Failure Reason</th>
                                <th>User Agent</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($audit_logs as $log): ?>
                                <tr>
                                    <td><?php echo date('Y-m-d H:i:s', strtotime($log['login_time'])); ?></td>
                                    <td><?php echo htmlspecialchars($log['full_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($log['email']); ?></td>
                                    <td><?php echo htmlspecialchars($log['staff_id'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($log['ip_address'] ?? 'N/A'); ?></td>
                                    <td>
                                        <span class="status-badge <?php echo htmlspecialchars($log['login_status']); ?>">
                                            <?php echo htmlspecialchars($log['login_status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($log['failure_reason'] ?? '-'); ?></td>
                                    <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo htmlspecialchars($log['user_agent'] ?? 'N/A'); ?>">
                                        <?php echo htmlspecialchars($log['user_agent'] ?? 'N/A'); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

                <?php if ($total_pages > 1): ?>
                    <div class="pagination-wrapper">
                        <div class="pagination-info">
                            Showing <?php echo $offset + 1; ?> - <?php echo min($offset + $per_page, ($total_records ?? 0)); ?> of <?php echo number_format($total_records ?? 0); ?> logs
                            (Page <?php echo $page; ?> of <?php echo $total_pages; ?>)
                        </div>
                        <div class="pagination">
                            <?php
                            $pagination_params = "type=" . htmlspecialchars($audit_type);
                            if ($audit_type === 'profile') {
                                $pagination_params .= "&action_type=" . htmlspecialchars($filter_action);
                            } else {
                                $pagination_params .= "&status=" . htmlspecialchars($filter_status);
                            }
                            $pagination_params .= "&email=" . htmlspecialchars($filter_email) . "&date=" . htmlspecialchars($filter_date);
                            ?>
                            <?php if ($page > 1): ?>
                                <a href="?page=1&<?php echo $pagination_params; ?>" class="pagination-btn" style="text-decoration: none; padding: 10px 16px;">
                                    <i class="fa fa-angle-double-left"></i> First
                                </a>
                                <a href="?page=<?php echo $page - 1; ?>&<?php echo $pagination_params; ?>" class="pagination-btn" style="text-decoration: none; padding: 10px 20px;">
                                    <i class="fa fa-chevron-left"></i> Previous
                                </a>
                            <?php else: ?>
                                <span class="pagination-btn" style="opacity: 0.5; cursor: not-allowed; pointer-events: none; background: #e9ecef; color: #636e72;">
                                    <i class="fa fa-angle-double-left"></i> First
                                </span>
                                <span class="pagination-btn" style="opacity: 0.5; cursor: not-allowed; pointer-events: none; background: #e9ecef; color: #636e72;">
                                    <i class="fa fa-chevron-left"></i> Previous
                                </span>
                            <?php endif; ?>

                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            
                            if ($start_page > 1): ?>
                                <a href="?page=1&<?php echo $pagination_params; ?>">1</a>
                                <?php if ($start_page > 2): ?>
                                    <span style="padding: 10px 8px; color: #636e72;">...</span>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                <?php if ($i == $page): ?>
                                    <span class="current"><?php echo $i; ?></span>
                                <?php else: ?>
                                    <a href="?page=<?php echo $i; ?>&<?php echo $pagination_params; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endif; ?>
                            <?php endfor; ?>

                            <?php if ($end_page < $total_pages): ?>
                                <?php if ($end_page < $total_pages - 1): ?>
                                    <span style="padding: 10px 8px; color: #636e72;">...</span>
                                <?php endif; ?>
                                <a href="?page=<?php echo $total_pages; ?>&<?php echo $pagination_params; ?>">
                                    <?php echo $total_pages; ?>
                                </a>
                            <?php endif; ?>

                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?php echo $page + 1; ?>&<?php echo $pagination_params; ?>" class="pagination-btn" style="text-decoration: none; padding: 10px 20px;">
                                    Next <i class="fa fa-chevron-right"></i>
                                </a>
                                <a href="?page=<?php echo $total_pages; ?>&<?php echo $pagination_params; ?>" class="pagination-btn" style="text-decoration: none; padding: 10px 16px;">
                                    Last <i class="fa fa-angle-double-right"></i>
                                </a>
                            <?php else: ?>
                                <span class="pagination-btn" style="opacity: 0.5; cursor: not-allowed; pointer-events: none; background: #e9ecef; color: #636e72;">
                                    Next <i class="fa fa-chevron-right"></i>
                                </span>
                                <span class="pagination-btn" style="opacity: 0.5; cursor: not-allowed; pointer-events: none; background: #e9ecef; color: #636e72;">
                                    Last <i class="fa fa-angle-double-right"></i>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="pagination-wrapper">
                        <div class="pagination-info">
                            Showing <?php echo $total_records ?? 0; ?> log<?php echo ($total_records ?? 0) != 1 ? 's' : ''; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <footer>
        <?php include_once("../components/footer.php"); ?>
    </footer>
</body>
</html>

