<?php
session_start();
require_once '../database/config.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - UniKL RCMP IT Inventory</title>
    <link rel="icon" type="image/png" href="../public/rcmp.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/TechDashboard.css">
</head>
<body>
    <?php include_once("../components/HomeHeader.php"); ?>

    <div class="tech-dashboard-container">
        <div class="dashboard-header">
            <h1>Welcome, <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Administrator'); ?>!</h1>
            <p class="dashboard-greeting">Admin dashboard overview.</p>
        </div>
        <div class="dashboard-main">
            <div class="dashboard-card quick-links">
                <h2>Admin Functions</h2>
                <div class="quick-links-list">
                    <a href="#" class="quick-link-btn">
                        <i class="fa-solid fa-users"></i>
                        <span>Manage Users</span>
                    </a>
                    <a href="#" class="quick-link-btn">
                        <i class="fa-solid fa-cog"></i>
                        <span>Settings</span>
                    </a>
                    <a href="#" class="quick-link-btn">
                        <i class="fa-solid fa-chart-bar"></i>
                        <span>Reports</span>
                    </a>
                    <a href="#" class="quick-link-btn">
                        <i class="fa-solid fa-shield-alt"></i>
                        <span>Security</span>
                    </a>
                </div>
            </div>
            <div class="dashboard-card recent-activity">
                <h2>Recent Activity</h2>
                <ul class="activity-list">
                    <li class="activity-item">No recent activity</li>
                </ul>
            </div>
            <div class="dashboard-card stats">
                <h2>Statistics</h2>
                <div class="stats-grid">
                    <div class="stat-box">
                        <span class="stat-title">Total Users</span>
                        <span class="stat-value">--</span>
                    </div>
                    <div class="stat-box">
                        <span class="stat-title">Total Assets</span>
                        <span class="stat-value">--</span>
                    </div>
                    <div class="stat-box">
                        <span class="stat-title">Active Sessions</span>
                        <span class="stat-value">--</span>
                    </div>
                    <div class="stat-box">
                        <span class="stat-title">System Status</span>
                        <span class="stat-value">--</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer>
        <?php include_once("../components/footer.php"); ?>
    </footer>
</body>
</html>

