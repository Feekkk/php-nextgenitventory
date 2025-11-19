<?php
session_start();
require_once '../database/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Technician Dashboard - UniKL RCMP IT Inventory</title>
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
            <h1>Welcome, <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Technician'); ?>!</h1>
            <p class="dashboard-greeting">Here's an overview of your dashboard.</p>
        </div>
        <div class="dashboard-main">
            <div class="dashboard-card quick-links">
                <h2>Quick Links</h2>
                <div class="quick-links-list">
                    <a href="#" class="quick-link-btn">
                        <i class="fa-solid fa-handshake"></i>
                        <span>Handover</span>
                    </a>
                    <a href="#" class="quick-link-btn">
                        <i class="fa-solid fa-recycle"></i>
                        <span>Disposal</span>
                    </a>
                    <a href="#" class="quick-link-btn">
                        <i class="fa-solid fa-clock-rotate-left"></i>
                        <span>History</span>
                    </a>
                    <a href="#" class="quick-link-btn">
                        <i class="fa-solid fa-user"></i>
                        <span>Profile</span>
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
                        <span class="stat-title">Total Assets</span>
                        <span class="stat-value">--</span>
                    </div>
                    <div class="stat-box">
                        <span class="stat-title">Pending Issues</span>
                        <span class="stat-value">--</span>
                    </div>
                    <div class="stat-box">
                        <span class="stat-title">Resolved Today</span>
                        <span class="stat-value">--</span>
                    </div>
                    <div class="stat-box">
                        <span class="stat-title">Open Requests</span>
                        <span class="stat-value">--</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer>
        <?php include_once("../components/Footer.php"); ?>
    </footer>
</body>
</html>