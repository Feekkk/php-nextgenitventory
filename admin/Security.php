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

function cleanupOldAuditLogs($pdo) {
    try {
        $threeMonthsAgo = date('Y-m-d H:i:s', strtotime('-3 months'));
        $stmt = $pdo->prepare("DELETE FROM login_audit WHERE login_time < ?");
        $stmt->execute([$threeMonthsAgo]);
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

if (isset($_GET['action']) && $_GET['action'] === 'cleanup') {
    $deleted_count = cleanupOldAuditLogs($pdo);
    if ($deleted_count !== false) {
        $success = "Successfully deleted {$deleted_count} old audit log records (older than 3 months).";
        recordCleanupTime();
    } else {
        $error = 'Failed to cleanup old audit logs.';
    }
}

if (shouldRunAutoCleanup()) {
    $deleted_count = cleanupOldAuditLogs($pdo);
    if ($deleted_count !== false && $deleted_count > 0) {
        $cleanup_message = "Automatically deleted {$deleted_count} old audit log records (older than 3 months).";
        recordCleanupTime();
    } elseif ($deleted_count !== false) {
        recordCleanupTime();
    }
}

$filter_status = $_GET['status'] ?? 'all';
$filter_email = $_GET['email'] ?? '';
$filter_date = $_GET['date'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

try {
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
    
} catch (PDOException $e) {
    $error = 'Failed to load audit logs.';
    $audit_logs = [];
    $stats = ['total_attempts' => 0, 'successful' => 0, 'failed' => 0];
    $total_pages = 0;
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

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 24px;
        }

        .pagination a,
        .pagination span {
            padding: 8px 14px;
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 6px;
            text-decoration: none;
            color: #2d3436;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .pagination a:hover {
            background: #1a1a2e;
            color: #ffffff;
            border-color: #1a1a2e;
        }

        .pagination .current {
            background: #1a1a2e;
            color: #ffffff;
            border-color: #1a1a2e;
        }

        .pagination .disabled {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
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
        }
    </style>
</head>
<body>
    <?php include_once("../components/ADMINheader.php"); ?>

    <div class="security-page-container">
        <div class="page-header" style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 20px;">
            <div>
                <h1><i class="fa fa-shield-alt"></i> Security & Audit Trails</h1>
                <p>Monitor and review all login attempts and security events.</p>
            </div>
            <a href="?action=cleanup&status=<?php echo htmlspecialchars($filter_status); ?>&email=<?php echo htmlspecialchars($filter_email); ?>&date=<?php echo htmlspecialchars($filter_date); ?>" 
               class="btn btn-primary" 
               onclick="return confirm('Are you sure you want to delete all audit logs older than 3 months? This action cannot be undone.');"
               style="padding: 10px 20px; border-radius: 8px; border: none; font-weight: 600; cursor: pointer; font-size: 0.95rem; display: flex; align-items: center; gap: 8px; text-decoration: none; background: #1a1a2e; color: #ffffff; transition: all 0.2s ease;">
                <i class="fa fa-trash-alt"></i>
                Cleanup Old Logs
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
            <div class="stat-card">
                <div class="stat-label">Total Login Attempts</div>
                <div class="stat-value"><?php echo number_format($stats['total_attempts']); ?></div>
            </div>
            <div class="stat-card success">
                <div class="stat-label">Successful Logins</div>
                <div class="stat-value"><?php echo number_format($stats['successful']); ?></div>
            </div>
            <div class="stat-card failed">
                <div class="stat-label">Failed Attempts</div>
                <div class="stat-value"><?php echo number_format($stats['failed']); ?></div>
            </div>
        </div>

        <div class="filters-card">
            <form method="GET" action="" class="filters-form">
                <div class="form-group">
                    <label for="status">Login Status</label>
                    <select id="status" name="status">
                        <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="success" <?php echo $filter_status === 'success' ? 'selected' : ''; ?>>Success</option>
                        <option value="failed" <?php echo $filter_status === 'failed' ? 'selected' : ''; ?>>Failed</option>
                    </select>
                </div>
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
                <?php if ($filter_status !== 'all' || !empty($filter_email) || !empty($filter_date)): ?>
                <div class="form-group">
                    <a href="Security.php" class="btn btn-secondary">
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

                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>&status=<?php echo htmlspecialchars($filter_status); ?>&email=<?php echo htmlspecialchars($filter_email); ?>&date=<?php echo htmlspecialchars($filter_date); ?>">
                                <i class="fa fa-chevron-left"></i> Previous
                            </a>
                        <?php else: ?>
                            <span class="disabled"><i class="fa fa-chevron-left"></i> Previous</span>
                        <?php endif; ?>

                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <?php if ($i == $page): ?>
                                <span class="current"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="?page=<?php echo $i; ?>&status=<?php echo htmlspecialchars($filter_status); ?>&email=<?php echo htmlspecialchars($filter_email); ?>&date=<?php echo htmlspecialchars($filter_date); ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&status=<?php echo htmlspecialchars($filter_status); ?>&email=<?php echo htmlspecialchars($filter_email); ?>&date=<?php echo htmlspecialchars($filter_date); ?>">
                                Next <i class="fa fa-chevron-right"></i>
                            </a>
                        <?php else: ?>
                            <span class="disabled">Next <i class="fa fa-chevron-right"></i></span>
                        <?php endif; ?>
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

