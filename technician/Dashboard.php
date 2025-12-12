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
$statusCounts = [];
$statusByAsset = [
    'laptop' => [],
    'av' => [],
    'network' => [],
];
$statusLabels = [];
$monthlyAdds = [];
$activeHandovers = 0;
$addedThisMonth = 0;
$assetTypeData = [];
$recentActivity = [];

try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM laptop_desktop_assets");
    $laptopCount = $stmt->fetch()['count'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM av_assets");
    $avCount = $stmt->fetch()['count'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM net_assets");
    $netCount = $stmt->fetch()['count'] ?? 0;
    
    $totalAssets = $laptopCount + $avCount + $netCount;
    
    // Status distribution across all assets
    $statusSql = "
        SELECT status, COUNT(*) as count FROM (
            SELECT COALESCE(status, 'Unknown') as status FROM laptop_desktop_assets
            UNION ALL
            SELECT COALESCE(status, 'Unknown') as status FROM av_assets
            UNION ALL
            SELECT COALESCE(status, 'Unknown') as status FROM net_assets
        ) s
        GROUP BY status
        ORDER BY count DESC
    ";
    $stmt = $pdo->query($statusSql);
    $statusCounts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // Status distribution by asset category (laptop, av, network)
    $statusByAsset['laptop'] = $pdo->query("SELECT COALESCE(status, 'Unknown') AS status, COUNT(*) AS cnt FROM laptop_desktop_assets GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
    $statusByAsset['av'] = $pdo->query("SELECT COALESCE(status, 'Unknown') AS status, COUNT(*) AS cnt FROM av_assets GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
    $statusByAsset['network'] = $pdo->query("SELECT COALESCE(status, 'Unknown') AS status, COUNT(*) AS cnt FROM net_assets GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);

    // Build unified status labels
    $statusLabelSet = [];
    foreach ($statusByAsset as $group) {
        foreach (array_keys($group) as $st) {
            $statusLabelSet[$st] = true;
        }
    }
    $statusLabels = array_values(array_keys($statusLabelSet));
    sort($statusLabels);

    // Last 6 months additions (including current month)
    $monthlySql = "
        SELECT DATE_FORMAT(created_at, '%Y-%m') as month_label, COUNT(*) as count FROM (
            SELECT created_at FROM laptop_desktop_assets
            UNION ALL
            SELECT created_at FROM av_assets
            UNION ALL
            SELECT created_at FROM net_assets
        ) t
        WHERE created_at >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 5 MONTH)
        GROUP BY month_label
        ORDER BY month_label
    ";
    $stmt = $pdo->query($monthlySql);
    $monthlyAdds = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // Handovers
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM handover WHERE status = 'active'");
    $activeHandovers = $stmt->fetch()['count'] ?? 0;

    // New assets this month
    $stmt = $pdo->query("
        SELECT COUNT(*) as count FROM (
            SELECT created_at FROM laptop_desktop_assets
            UNION ALL SELECT created_at FROM av_assets
            UNION ALL SELECT created_at FROM net_assets
        ) t
        WHERE created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
    ");
    $addedThisMonth = $stmt->fetch()['count'] ?? 0;

    $assetTypeData = [
        'Laptop/Desktop' => $laptopCount,
        'Audio Visual' => $avCount,
        'Network' => $netCount,
    ];

    $activitySql = "
        SELECT asset_type, asset_id, action_type, field_name, new_value, created_at, tech_id, changed_by
        FROM asset_trails
        ORDER BY created_at DESC
        LIMIT 4
    ";
    $stmt = $pdo->query($activitySql);
    $recentActivity = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-top: 16px;
        }

        .quick-link-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 24px 20px;
            border-radius: 14px;
            background: #f8fafc;
            border: 1px solid rgba(0, 0, 0, 0.05);
            text-decoration: none;
            color: #0f172a;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.2s ease;
            min-height: 100px;
        }

        .quick-link-btn i {
            font-size: 2rem;
            color: #6c5ce7;
        }
        
        .quick-link-btn span {
            font-size: 0.95rem;
            text-align: center;
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

        .activity-meta {
            display: flex;
            gap: 10px;
            font-size: 0.85rem;
            color: #64748b;
            margin-top: 6px;
            flex-wrap: wrap;
        }

        .pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 999px;
            background: #eef2ff;
            color: #4338ca;
            font-size: 0.82rem;
            border: 1px solid rgba(99, 102, 241, 0.2);
        }

        .stats-card .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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

        .chart-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 18px;
            margin-top: 20px;
        }

        .chart-box {
            background: #ffffff;
            border: 1px solid rgba(15, 23, 42, 0.08);
            border-radius: 16px;
            padding: 18px;
            box-shadow: 0 12px 25px rgba(15, 23, 42, 0.05);
            min-height: 260px;
        }

        .chart-box.full-width {
            grid-column: 1 / -1;
            min-height: 400px;
        }

        .chart-box h3 {
            margin: 0 0 12px;
            font-size: 1rem;
            color: #0f172a;
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

            .chart-grid {
                grid-template-columns: 1fr;
            }

            .chart-box.full-width {
                grid-column: 1;
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
                    <a href="../pages/LAPTOPpage.php" class="quick-link-btn">
                        <i class="fa-solid fa-laptop"></i>
                        <span>Laptop / Desktop</span>
                    </a>
                    <a href="../pages/AVpage.php" class="quick-link-btn">
                        <i class="fa-solid fa-tv"></i>
                        <span>Audio / Visual</span>
                    </a>
                    <a href="../pages/NETpage.php" class="quick-link-btn">
                        <i class="fa-solid fa-network-wired"></i>
                        <span>Network</span>
                    </a>
                    <a href="History.php" class="quick-link-btn">
                        <i class="fa-solid fa-clock-rotate-left"></i>
                        <span>Audit Trail</span>
                    </a>
                    <a href="Profile.php" class="quick-link-btn">
                        <i class="fa-solid fa-user"></i>
                        <span>My Profile</span>
                    </a>
                </div>
            </div>

            <div class="dashboard-card recent-activity">
                <h2>Recent Activity</h2>
                <ul class="activity-list">
                    <?php if (empty($recentActivity)): ?>
                    <li class="activity-item">No recent activity</li>
                    <?php else: ?>
                        <?php foreach ($recentActivity as $activity): ?>
                            <li class="activity-item">
                                <div>
                                    <strong><?php echo htmlspecialchars($activity['action_type']); ?></strong>
                                    on <span class="pill"><?php echo htmlspecialchars(strtoupper($activity['asset_type'])); ?></span>
                                    <span>#<?php echo htmlspecialchars($activity['asset_id']); ?></span>
                                </div>
                                <div class="activity-meta">
                                    <span><?php echo htmlspecialchars($activity['field_name'] ?? ''); ?></span>
                                    <?php if (!empty($activity['new_value'])): ?>
                                        <span>→ <?php echo htmlspecialchars(mb_strimwidth($activity['new_value'], 0, 60, '...')); ?></span>
                                    <?php endif; ?>
                                    <span><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($activity['created_at']))); ?></span>
                                    <?php if (!empty($activity['tech_id'])): ?>
                                        <span>by <?php echo htmlspecialchars($activity['tech_id']); ?></span>
                                    <?php elseif (!empty($activity['changed_by'])): ?>
                                        <span>by #<?php echo htmlspecialchars($activity['changed_by']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
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
                    <div class="stat-box">
                        <span class="stat-title">Added This Month</span>
                        <span class="stat-value"><?php echo number_format($addedThisMonth); ?></span>
                    </div>
                    <div class="stat-box">
                        <span class="stat-title">Active Handovers</span>
                        <span class="stat-value"><?php echo number_format($activeHandovers); ?></span>
                    </div>
                </div>

                <div class="chart-grid">
                    <div class="chart-box">
                        <h3>Asset Mix</h3>
                        <canvas id="assetTypeChart"></canvas>
                    </div>
                    <div class="chart-box">
                        <h3>Last 6 Months Additions</h3>
                        <canvas id="monthlyChart"></canvas>
                    </div>
                    <div class="chart-box full-width">
                        <h3>Status Distribution by Category</h3>
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <footer>
        <?php include_once("../components/Footer.php"); ?>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
        const assetTypeLabels = <?php echo json_encode(array_keys($assetTypeData)); ?>;
        const assetTypeValues = <?php echo json_encode(array_values($assetTypeData)); ?>;

        const statusLabels = <?php echo json_encode($statusLabels); ?>;
        const laptopStatus = <?php echo json_encode(array_values(array_map(fn($st) => (int)($statusByAsset['laptop'][$st] ?? 0), array_combine($statusLabels, $statusLabels)))); ?>;
        const avStatus = <?php echo json_encode(array_values(array_map(fn($st) => (int)($statusByAsset['av'][$st] ?? 0), array_combine($statusLabels, $statusLabels)))); ?>;
        const netStatus = <?php echo json_encode(array_values(array_map(fn($st) => (int)($statusByAsset['network'][$st] ?? 0), array_combine($statusLabels, $statusLabels)))); ?>;

        const monthlyLabels = <?php echo json_encode(array_keys($monthlyAdds)); ?>;
        const monthlyValues = <?php echo json_encode(array_values($monthlyAdds)); ?>;

        const palette = ['#6366F1', '#22C55E', '#06B6D4', '#F59E0B', '#EF4444', '#0EA5E9', '#A855F7'];

        const assetCtx = document.getElementById('assetTypeChart');
        if (assetCtx) {
            new Chart(assetCtx, {
                type: 'doughnut',
                data: {
                    labels: assetTypeLabels,
                    datasets: [{
                        data: assetTypeValues,
                        backgroundColor: palette,
                        borderWidth: 1
                    }]
                },
                options: {
                    plugins: { legend: { position: 'bottom' } },
                    cutout: '60%'
                }
            });
        }

        const statusCtx = document.getElementById('statusChart');
        if (statusCtx) {
            new Chart(statusCtx, {
                type: 'bar',
                data: {
                    labels: statusLabels,
                    datasets: [
                        {
                            label: 'Laptop/Desktop',
                            data: laptopStatus,
                            backgroundColor: palette[0],
                            borderRadius: 6,
                        },
                        {
                            label: 'Audio/Visual',
                            data: avStatus,
                            backgroundColor: palette[2],
                            borderRadius: 6,
                        },
                        {
                            label: 'Network',
                            data: netStatus,
                            backgroundColor: palette[4],
                            borderRadius: 6,
                        }
                    ]
                },
                options: {
                    plugins: { legend: { position: 'bottom' } },
                    scales: {
                        y: { beginAtZero: true, ticks: { precision: 0 } }
                    }
                }
            });
        }

        const monthlyCtx = document.getElementById('monthlyChart');
        if (monthlyCtx) {
            new Chart(monthlyCtx, {
                type: 'line',
                data: {
                    labels: monthlyLabels,
                    datasets: [{
                        label: 'Assets Added',
                        data: monthlyValues,
                        borderColor: '#0EA5E9',
                        backgroundColor: 'rgba(14, 165, 233, 0.15)',
                        tension: 0.35,
                        fill: true,
                        pointRadius: 4,
                        pointBackgroundColor: '#0284C7'
                    }]
                },
                options: {
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, ticks: { precision: 0 } }
                    }
                }
            });
        }
    </script>
</body>
</html>