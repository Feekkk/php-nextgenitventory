<?php
session_start();
require_once '../database/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$pdo = getDBConnection();

$totalAssets = 0;
$laptopCount = 0;
$avCount = 0;
$netCount = 0;

try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM laptop_desktop_assets");
    $laptopCount = $stmt->fetch()['count'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM av_assets");
    $avCount = $stmt->fetch()['count'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM net_assets");
    $netCount = $stmt->fetch()['count'] ?? 0;
    
    $totalAssets = $laptopCount + $avCount + $netCount;
    
} catch (PDOException $e) {
    error_log('Dashboard stats error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Technician Dashboard - UniKL RCMP IT Inventory</title>
    <link rel="icon" type="image/png" href="../public/rcmp.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/TechDashboard.css">
    <style>
        .tech-dashboard-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 40px 20px 80px;
        }

        .dashboard-section {
            margin-bottom: 30px;
        }

        .dashboard-card {
            background: rgba(255, 255, 255, 0.85);
            border: 1px solid rgba(0, 0, 0, 0.05);
            border-radius: 18px;
            padding: 28px;
            box-shadow: 0 20px 45px rgba(15, 23, 42, 0.08);
        }

        .welcome-card {
            display: flex;
            flex-direction: column;
            gap: 10px;
            background-image: linear-gradient(135deg, rgba(108, 92, 231, 0.15), rgba(0, 206, 201, 0.15));
        }

        .welcome-card h1 {
            font-size: 2rem;
            margin: 0;
            color: #0f172a;
        }

        .welcome-card p {
            margin: 0;
            color: #475569;
            font-size: 1rem;
        }

        .action-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 24px;
        }

        .quick-links-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 12px;
            margin-top: 16px;
        }

        .quick-link-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            padding: 18px;
            border-radius: 14px;
            background: #f8fafc;
            border: 1px solid rgba(0, 0, 0, 0.05);
            text-decoration: none;
            color: #0f172a;
            font-weight: 600;
            transition: all 0.2s ease;
        }

        .quick-link-btn i {
            font-size: 1.5rem;
            color: #6c5ce7;
        }

        .quick-link-btn:hover {
            background: #1a1a2e;
            color: #fff;
            border-color: #1a1a2e;
        }

        .quick-link-btn:hover i {
            color: #fff;
        }

        .recent-activity .activity-list {
            margin-top: 18px;
            list-style: none;
            padding: 0;
        }

        .activity-item {
            padding: 14px 16px;
            border-radius: 12px;
            background: #f8fafc;
            border: 1px dashed rgba(0, 0, 0, 0.08);
            color: #475569;
            font-size: 0.95rem;
        }

        .stats-card .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 18px;
            margin-top: 20px;
        }

        .stat-box {
            border-radius: 16px;
            padding: 20px;
            background: #ffffff;
            color: #0f172a;
            display: flex;
            flex-direction: column;
            gap: 6px;
            border: 1px solid rgba(15, 23, 42, 0.08);
            box-shadow: 0 12px 25px rgba(15, 23, 42, 0.05);
        }

        .stat-title {
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #64748b;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #0f172a;
        }

        @media (max-width: 768px) {
            .tech-dashboard-container {
                padding: 30px 16px 60px;
            }

            .welcome-card h1 {
                font-size: 1.6rem;
            }
        }
    </style>
</head>
<body>
    <?php include_once("../components/HomeHeader.php"); ?>

    <div class="tech-dashboard-container">
        <section class="dashboard-section">
            <div class="dashboard-card welcome-card">
                <h1>Welcome, <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Technician'); ?>!</h1>
                <p>Here’s an overview of what’s happening inside the inventory platform.</p>
            </div>
        </section>

        <section class="dashboard-section action-section">
            <div class="dashboard-card quick-links">
                <h2>Quick Links</h2>
                <div class="quick-links-list">
                    <a href="HANDform.php" class="quick-link-btn">
                        <i class="fa-solid fa-handshake"></i>
                        <span>Handover Form</span>
                    </a>
                    <a href="HANDreturn.php" class="quick-link-btn">
                        <i class="fa-solid fa-undo"></i>
                        <span>Return</span>
                    </a>
                    <a href="History.php" class="quick-link-btn">
                        <i class="fa-solid fa-clock-rotate-left"></i>
                        <span>History</span>
                    </a>
                    <a href="Profile.php" class="quick-link-btn">
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
        </section>

        <section class="dashboard-section stats-section">
            <div class="dashboard-card stats-card">
                <h2>Statistics</h2>
                <div class="stats-grid">
                    <div class="stat-box">
                        <span class="stat-title">Total Assets</span>
                        <span class="stat-value"><?php echo number_format($totalAssets); ?></span>
                    </div>
                    <div class="stat-box">
                        <span class="stat-title">Laptop/Desktop</span>
                        <span class="stat-value"><?php echo number_format($laptopCount); ?></span>
                    </div>
                    <div class="stat-box">
                        <span class="stat-title">Audio Visual</span>
                        <span class="stat-value"><?php echo number_format($avCount); ?></span>
                    </div>
                    <div class="stat-box">
                        <span class="stat-title">Network</span>
                        <span class="stat-value"><?php echo number_format($netCount); ?></span>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <footer>
        <?php include_once("../components/Footer.php"); ?>
    </footer>
</body>
</html>