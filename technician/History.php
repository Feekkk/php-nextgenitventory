
<?php
session_start();
require_once '../database/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$pdo = getDBConnection();
$assetTrails = [];
$perPage = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $perPage;
$totalRows = 0;
$totalPages = 1;

try {
    // Count total rows for pagination
    $countStmt = $pdo->query("SELECT COUNT(*) AS total FROM asset_trails");
    $totalRows = (int)($countStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    $totalPages = max(1, (int)ceil($totalRows / $perPage));

    $stmt = $pdo->prepare("
        SELECT at.*, tech.tech_name, tech.tech_id AS technician_code
        FROM asset_trails at
        LEFT JOIN technician tech ON at.changed_by = tech.id
        ORDER BY at.created_at DESC
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $assetTrails = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Failed to load asset trails: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Trail - UniKL RCMP IT Inventory</title>
    <link rel="icon" type="image/png" href="../public/rcmp.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/TechDashboard.css">
    <style>
        .history-page-container {
            max-width: 1500px;
            margin: 0 auto;
            padding: 40px 20px 80px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 30px;
        }

        .page-header h1 {
            font-size: 2rem;
            font-weight: 700;
            color: #000000;
            margin-bottom: 8px;
        }

        .page-header p {
            color: #636e72;
            max-width: 600px;
        }

        .filters-bar {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        .search-box {
            position: relative;
        }

        .search-box input {
            padding: 10px 16px 10px 44px;
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 10px;
            font-size: 0.95rem;
            width: 280px;
            transition: all 0.2s ease;
        }

        .search-box i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #636e72;
        }

        .search-box input:focus {
            outline: none;
            border-color: #1a1a2e;
            box-shadow: 0 0 0 3px rgba(26, 26, 46, 0.1);
        }

        .filter-select,
        .date-filter {
            padding: 10px 16px;
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 10px;
            font-size: 0.95rem;
            background: #ffffff;
        }

        .timeline-card {
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(0, 0, 0, 0.05);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.05);
        }

        .timeline {
            display: flex;
            flex-direction: column;
            gap: 20px;
            position: relative;
        }

        .timeline::before {
            content: '';
            position: absolute;
            left: 28px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: rgba(26, 26, 46, 0.1);
        }

        .timeline-item {
            position: relative;
            padding-left: 70px;
        }

        .timeline-badge {
            position: absolute;
            left: 12px;
            top: 12px;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #1a1a2e;
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            box-shadow: 0 8px 20px rgba(26, 26, 46, 0.25);
        }

        .timeline-content {
            background: rgba(26, 26, 46, 0.02);
            border: 1px solid rgba(0, 0, 0, 0.05);
            border-radius: 16px;
            padding: 16px 20px;
            display: flex;
            justify-content: space-between;
            gap: 20px;
            flex-wrap: wrap;
        }

        .timeline-title {
            font-weight: 600;
            color: #1a1a2e;
            margin-bottom: 4px;
        }

        .timeline-meta {
            color: #636e72;
            font-size: 0.9rem;
            margin-bottom: 8px;
        }

        .timeline-details {
            color: #2d3436;
            font-size: 0.95rem;
        }

        .tag {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            border-radius: 10px;
            font-size: 0.8rem;
            font-weight: 600;
            background: rgba(26, 26, 46, 0.08);
            color: #1a1a2e;
            margin-right: 6px;
        }

        .tag.create {
            background: rgba(0, 184, 148, 0.15);
            color: #00b894;
        }

        .tag.update {
            background: rgba(108, 92, 231, 0.15);
            color: #6c5ce7;
        }

        .tag.delete {
            background: rgba(243, 69, 69, 0.15);
            color: #d63031;
        }

        .tag.handover {
            background: rgba(253, 203, 110, 0.2);
            color: #e1a500;
        }

        .timeline-actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-action {
            padding: 8px 14px;
            border-radius: 9px;
            border: 1px solid rgba(0, 0, 0, 0.1);
            background: #ffffff;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 0.85rem;
        }

        .btn-action:hover {
            background: #1a1a2e;
            color: #ffffff;
            border-color: #1a1a2e;
        }

        .pagination {
            margin-top: 20px;
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .pagination a, .pagination span {
            padding: 8px 14px;
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 9px;
            background: #ffffff;
            color: #1a1a2e;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.2s ease;
        }

        .pagination a:hover {
            background: #1a1a2e;
            color: #ffffff;
            border-color: #1a1a2e;
        }

        .pagination .disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .pagination .current {
            background: #1a1a2e;
            color: #ffffff;
            border-color: #1a1a2e;
        }

        @media (max-width: 768px) {
            .timeline::before {
                left: 18px;
            }
            .timeline-badge {
                left: 2px;
            }
            .timeline-item {
                padding-left: 60px;
            }
        }
    </style>
</head>
<body>
    <?php include_once("../components/HomeHeader.php"); ?>

    <div class="history-page-container">
        <div class="page-header">
            <div>
                <h1>Audit Trail</h1>
                <p>See who changed what across inventory, handover, and disposal modules. Use the filters to focus on a specific asset or technician.</p>
            </div>
            <div class="filters-bar">
                <div class="search-box">
                    <i class="fa-solid fa-search"></i>
                    <input type="text" id="searchInput" placeholder="Search by user, asset, action...">
                </div>
                <select class="filter-select" id="moduleFilter">
                    <option value="">All modules</option>
                    <option value="laptop">Laptop/Desktop</option>
                    <option value="av">Audio/Visual</option>
                    <option value="network">Network</option>
                    <option value="handover">Handover</option>
                    <option value="profile">Profile</option>
                </select>
                <select class="filter-select" id="actionFilter">
                    <option value="">All actions</option>
                    <option value="create">Create</option>
                    <option value="update">Update</option>
                    <option value="delete">Delete</option>
                    <option value="handover">Handover</option>
                    <option value="login">Login</option>
                </select>
                <input type="date" class="date-filter" id="dateFilter">
            </div>
        </div>

        <div class="timeline-card">
            <div class="timeline" id="historyTimeline">
                <?php if (empty($assetTrails)) : ?>
                    <div class="timeline-item">
                        <div class="timeline-content">
                            <div>
                                <div class="timeline-title">No asset activity yet</div>
                                <div class="timeline-meta">Activity will appear here when assets are created or updated.</div>
                            </div>
                        </div>
                    </div>
                <?php else : ?>
                    <?php foreach ($assetTrails as $trail) :
                        $module = 'laptop';
                        if ($trail['asset_type'] === 'av') {
                            $module = 'av';
                        } elseif ($trail['asset_type'] === 'network') {
                            $module = 'network';
                        }

                        $rawAction = strtoupper($trail['action_type']);
                        $actionForFilter = strtolower($rawAction);
                        if (in_array($rawAction, ['STATUS_CHANGE', 'ASSIGNMENT_CHANGE', 'LOCATION_CHANGE'], true)) {
                            $actionForFilter = 'update';
                        }

                        $tagClass = 'update';
                        if ($rawAction === 'CREATE') {
                            $tagClass = 'create';
                        } elseif ($rawAction === 'DELETE') {
                            $tagClass = 'delete';
                        }

                        $iconClass = 'fa-box';
                        if ($trail['asset_type'] === 'av') {
                            $iconClass = 'fa-tv';
                        } elseif ($trail['asset_type'] === 'network') {
                            $iconClass = 'fa-plug';
                        }

                        $when = $trail['created_at'] ?? null;
                        $dateAttr = $when ? date('Y-m-d', strtotime($when)) : '';
                        $whenLabel = $when ? date('Y-m-d H:i', strtotime($when)) : 'Unknown time';
                        $who = $trail['tech_name'] ?? 'Unknown user';
                        $assetIdLabel = strtoupper($trail['asset_type']) . ' #' . $trail['asset_id'];
                        $description = $trail['description'] ?: ($trail['field_name'] ? ($trail['field_name'] . ' changed') : '');
                    ?>
                        <div class="timeline-item"
                             data-module="<?php echo htmlspecialchars($module); ?>"
                             data-action="<?php echo htmlspecialchars($actionForFilter); ?>"
                             data-date="<?php echo htmlspecialchars($dateAttr); ?>">
                            <div class="timeline-badge"><i class="fa-solid <?php echo htmlspecialchars($iconClass); ?>"></i></div>
                            <div class="timeline-content">
                                <div>
                                    <div class="timeline-title">
                                        <?php echo htmlspecialchars($assetIdLabel); ?> - <?php echo htmlspecialchars(ucwords(strtolower(str_replace('_', ' ', $rawAction)))); ?>
                                    </div>
                                    <div class="timeline-meta">
                                        <?php echo htmlspecialchars($whenLabel); ?> · by <?php echo htmlspecialchars($who); ?>
                                    </div>
                                    <div class="timeline-details">
                                        <span class="tag <?php echo htmlspecialchars($tagClass); ?>">
                                            <?php echo htmlspecialchars(ucwords(strtolower(str_replace('_', ' ', $rawAction)))); ?>
                                        </span>
                                        <?php echo htmlspecialchars($description ?: 'No additional details.'); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>">&laquo; Prev</a>
                <?php else: ?>
                    <span class="disabled">&laquo; Prev</span>
                <?php endif; ?>

                <span class="current">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>

                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?php echo $page + 1; ?>">Next &raquo;</a>
                <?php else: ?>
                    <span class="disabled">Next &raquo;</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <footer>
        <?php include_once("../components/Footer.php"); ?>
    </footer>

    <script>
        const searchInput = document.getElementById('searchInput');
        const moduleFilter = document.getElementById('moduleFilter');
        const actionFilter = document.getElementById('actionFilter');
        const dateFilter = document.getElementById('dateFilter');
        const items = Array.from(document.querySelectorAll('.timeline-item'));

        function applyFilters() {
            const searchTerm = searchInput.value.toLowerCase();
            const moduleTerm = moduleFilter.value;
            const actionTerm = actionFilter.value;
            const dateTerm = dateFilter.value;

            items.forEach(item => {
                const text = item.textContent.toLowerCase();
                const module = item.dataset.module;
                const action = item.dataset.action;
                const date = item.dataset.date;

                let visible = true;

                if (searchTerm && !text.includes(searchTerm)) visible = false;
                if (visible && moduleTerm && module !== moduleTerm) visible = false;
                if (visible && actionTerm && action !== actionTerm) visible = false;
                if (visible && dateTerm && date !== dateTerm) visible = false;

                item.style.display = visible ? '' : 'none';
            });
        }

        searchInput.addEventListener('input', applyFilters);
        moduleFilter.addEventListener('change', applyFilters);
        actionFilter.addEventListener('change', applyFilters);
        dateFilter.addEventListener('change', applyFilters);
    </script>
</body>
</html>

