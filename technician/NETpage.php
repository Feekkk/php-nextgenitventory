<?php
session_start();
require_once '../database/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$pdo = getDBConnection();
$netAssets = [];
$netAssetsError = '';
$statuses = [];
$brands = [];
$buildings = [];
$levels = [];

try {
    $stmt = $pdo->query("
        SELECT na.*, t.tech_name AS created_by_name
        FROM net_assets na
        LEFT JOIN technician t ON na.created_by = t.id
        ORDER BY na.created_at DESC, na.asset_id DESC
    ");
    $netAssets = $stmt->fetchAll();
    
    $statusStmt = $pdo->query("SELECT DISTINCT status FROM net_assets WHERE status IS NOT NULL AND status != '' ORDER BY status");
    $statuses = $statusStmt->fetchAll(PDO::FETCH_COLUMN);
    
    $brandStmt = $pdo->query("SELECT DISTINCT brand FROM net_assets WHERE brand IS NOT NULL AND brand != '' ORDER BY brand");
    $brands = $brandStmt->fetchAll(PDO::FETCH_COLUMN);
    
    $buildingStmt = $pdo->query("SELECT DISTINCT building FROM net_assets WHERE building IS NOT NULL AND building != '' ORDER BY building");
    $buildings = $buildingStmt->fetchAll(PDO::FETCH_COLUMN);
    
    $levelStmt = $pdo->query("SELECT DISTINCT level FROM net_assets WHERE level IS NOT NULL AND level != '' ORDER BY level");
    $levels = $levelStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $netAssetsError = 'Unable to load network assets right now. Please try again later.';
}

function formatAssetId($id)
{
    return sprintf('NET-%05d', $id);
}

function formatStatusClass($status)
{
    $status = strtoupper(trim($status ?? ''));
    $map = [
        'AVAILABLE' => 'available',
        'ONLINE' => 'online',
        'DEPLOY' => 'deploy',
        'IN-USE' => 'in-use',
        'MAINTENANCE' => 'maintenance',
        'UNDER MAINTENANCE' => 'under-maintenance',
        'DISPOSED' => 'disposed',
        'DISPOSE' => 'dispose',
        'FAULTY' => 'faulty',
        'RESERVED' => 'reserved',
        'OFFLINE' => 'offline',
        'NON-ACTIVE' => 'non-active',
        'LOST' => 'lost',
        'UNAVAILABLE' => 'unavailable',
    ];
    return $map[$status] ?? 'unknown';
}

function formatStatusLabel($status)
{
    $status = trim((string)$status);
    return $status === '' ? 'Unknown' : ucwords(str_replace('-', ' ', $status));
}

function formatStatusIcon($status)
{
    $status = strtoupper(trim($status ?? ''));
    $iconMap = [
        'AVAILABLE' => 'fa-circle-check',
        'DEPLOY' => 'fa-circle-check',
        'ONLINE' => 'fa-circle-check',
        'IN-USE' => 'fa-laptop',
        'FAULTY' => 'fa-triangle-exclamation',
        'DISPOSE' => 'fa-trash',
        'DISPOSED' => 'fa-trash',
        'RESERVED' => 'fa-bookmark',
        'UNDER MAINTENANCE' => 'fa-wrench',
        'MAINTENANCE' => 'fa-wrench',
        'OFFLINE' => 'fa-circle-xmark',
        'NON-ACTIVE' => 'fa-circle-pause',
        'LOST' => 'fa-circle-question',
        'UNAVAILABLE' => 'fa-circle-xmark',
    ];
    return $iconMap[$status] ?? 'fa-circle-question';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Network Assets - UniKL RCMP IT Inventory</title>
    <link rel="icon" type="image/png" href="../public/rcmp.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/TechDashboard.css">
    <style>
        .assets-page-container {
            max-width: 1600px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 20px;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: #000000;
        }

        .page-actions {
            display: flex;
            gap: 15px;
            align-items: center;
            position: relative;
        }

        .filter-section {
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(0, 0, 0, 0.05);
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }

        .filter-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            cursor: pointer;
        }

        .filter-header h3 {
            font-size: 1.1rem;
            font-weight: 600;
            color: #1a1a2e;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .filter-header i {
            transition: transform 0.3s ease;
        }

        .filter-header.collapsed i {
            transform: rotate(-90deg);
        }

        .filter-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .filter-content.collapsed {
            display: none;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .filter-group label {
            font-size: 0.9rem;
            font-weight: 500;
            color: #2d3436;
        }

        .filter-group input,
        .filter-group select {
            padding: 10px 14px;
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            font-size: 0.95rem;
            transition: all 0.2s ease;
            background: #ffffff;
        }

        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: #1a1a2e;
            box-shadow: 0 0 0 3px rgba(26, 26, 46, 0.1);
        }

        .search-box {
            position: relative;
            display: flex;
            align-items: center;
        }

        .search-box input {
            padding: 10px 16px 10px 44px;
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            font-size: 0.95rem;
            width: 100%;
            transition: all 0.2s ease;
        }

        .search-box input:focus {
            outline: none;
            border-color: #1a1a2e;
            box-shadow: 0 0 0 3px rgba(26, 26, 46, 0.1);
        }

        .search-box i {
            position: absolute;
            left: 16px;
            color: #636e72;
        }

        .btn-clear-filters {
            padding: 10px 20px;
            background: #f1f2f6;
            color: #2d3436;
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            font-weight: 500;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.2s ease;
            align-self: flex-end;
        }

        .btn-clear-filters:hover {
            background: #e3e6ed;
        }

        .btn-add {
            padding: 10px 20px;
            background: #1a1a2e;
            color: #ffffff;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-add:hover {
            background: #0f0f1a;
            box-shadow: 0 4px 12px rgba(26, 26, 46, 0.3);
        }

        .btn-add i.fa-chevron-down {
            font-size: 0.8rem;
        }

        .dropdown-menu {
            position: absolute;
            top: 110%;
            right: 0;
            background: #ffffff;
            border: 1px solid rgba(0, 0, 0, 0.08);
            border-radius: 12px;
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.15);
            min-width: 220px;
            overflow: hidden;
            opacity: 0;
            transform: translateY(-10px);
            pointer-events: none;
            transition: all 0.2s ease;
            z-index: 10;
        }

        .dropdown-menu.open {
            opacity: 1;
            transform: translateY(0);
            pointer-events: auto;
        }

        .dropdown-menu button {
            width: 100%;
            background: transparent;
            border: none;
            padding: 14px 18px;
            text-align: left;
            font-size: 0.95rem;
            color: #2d3436;
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            transition: background 0.15s ease;
        }

        .dropdown-menu button:hover {
            background: rgba(26, 26, 46, 0.05);
        }

        .dropdown-menu button i {
            color: #6c5ce7;
            width: 18px;
            text-align: center;
        }

        .dropdown-menu button.import i {
            color: #0984e3;
        }

        .assets-table-container {
            background: rgba(255, 255, 255, 0.7);
            border: 1px solid rgba(0, 0, 0, 0.05);
            border-radius: 16px;
            padding: 30px;
            backdrop-filter: blur(10px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            overflow-x: auto;
        }

        .assets-table {
            width: 100%;
            border-collapse: collapse;
        }

        .assets-table thead {
            background: rgba(26, 26, 46, 0.05);
        }

        .assets-table th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #2d3436;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid rgba(26, 26, 46, 0.1);
        }

        .assets-table td {
            padding: 18px 15px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            color: #2d3436;
            font-size: 0.95rem;
        }

        .assets-table tbody tr {
            transition: all 0.2s ease;
        }

        .assets-table tbody tr:hover {
            background: rgba(26, 26, 46, 0.03);
        }

        .asset-id {
            font-weight: 600;
            color: #1a1a2e;
        }

        .asset-type {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .asset-type.router {
            background: rgba(108, 92, 231, 0.1);
            color: #6c5ce7;
        }

        .asset-type.switch {
            background: rgba(0, 206, 201, 0.1);
            color: #00cec9;
        }

        .asset-type.access-point {
            background: rgba(253, 121, 168, 0.1);
            color: #fd79a8;
        }

        .asset-type.other {
            background: rgba(99, 110, 114, 0.1);
            color: #636e72;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            font-size: 1rem;
            cursor: pointer;
            position: relative;
            transition: all 0.2s ease;
        }

        .status-badge:hover {
            transform: scale(1.1);
        }

        .status-badge.available,
        .status-badge.deploy,
        .status-badge.online {
            background: rgba(0, 184, 148, 0.15);
            color: #00b894;
        }

        .status-badge.in-use {
            background: rgba(108, 92, 231, 0.15);
            color: #6c5ce7;
        }

        .status-badge.maintenance,
        .status-badge.under-maintenance {
            background: rgba(253, 121, 168, 0.15);
            color: #fd79a8;
        }

        .status-badge.disposed,
        .status-badge.dispose {
            background: rgba(99, 110, 114, 0.15);
            color: #636e72;
        }

        .status-badge.faulty {
            background: rgba(214, 48, 49, 0.15);
            color: #d63031;
        }

        .status-badge.reserved {
            background: rgba(9, 132, 227, 0.15);
            color: #0984e3;
        }

        .status-badge.offline,
        .status-badge.unavailable {
            background: rgba(214, 48, 49, 0.15);
            color: #d63031;
        }

        .status-badge.non-active {
            background: rgba(99, 110, 114, 0.15);
            color: #636e72;
        }

        .status-badge.lost {
            background: rgba(214, 48, 49, 0.15);
            color: #d63031;
        }

        .status-badge.spare {
            background: rgba(255, 159, 67, 0.15);
            color: #d35400;
        }

        .status-badge.unknown {
            background: rgba(99, 110, 114, 0.15);
            color: #636e72;
        }

        .status-tooltip {
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            margin-bottom: 8px;
            padding: 6px 12px;
            background: #1a1a2e;
            color: #ffffff;
            border-radius: 6px;
            font-size: 0.85rem;
            white-space: nowrap;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.2s ease;
            z-index: 1000;
        }

        .status-tooltip::after {
            content: '';
            position: absolute;
            top: 100%;
            left: 50%;
            transform: translateX(-50%);
            border: 5px solid transparent;
            border-top-color: #1a1a2e;
        }

        .status-badge:hover .status-tooltip {
            opacity: 1;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .btn-action {
            padding: 6px 12px;
            border: 1px solid rgba(0, 0, 0, 0.1);
            background: #ffffff;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
            color: #2d3436;
            font-size: 0.85rem;
        }

        .btn-action:hover {
            background: #1a1a2e;
            color: #ffffff;
            border-color: #1a1a2e;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #636e72;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            color: rgba(26, 26, 46, 0.2);
        }

        .empty-state p {
            font-size: 1.1rem;
            margin-bottom: 10px;
        }

        .empty-state span {
            font-size: 0.9rem;
            color: #636e72;
        }

        .asset-meta {
            display: flex;
            flex-direction: column;
            gap: 4px;
            font-size: 0.85rem;
            color: #636e72;
        }

        .asset-meta span:first-child {
            font-weight: 600;
        }

        .data-message {
            text-align: center;
            padding: 20px;
            color: #c0392b;
            font-weight: 600;
        }

        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .filter-content {
                grid-template-columns: 1fr;
            }

            .assets-table-container {
                padding: 15px;
            }

            .assets-table {
                font-size: 0.85rem;
            }

            .assets-table th,
            .assets-table td {
                padding: 10px 8px;
            }
        }
    </style>
</head>
<body>
    <?php include_once("../components/HomeHeader.php"); ?>

    <div class="assets-page-container">
        <div class="page-header">
            <h1 class="page-title">Network Assets</h1>
            <div class="page-actions">
                <div>
                    <button class="btn-add" id="btn-add" type="button">
                        <i class="fa-solid fa-plus"></i>
                        Add Assets
                        <i class="fa-solid fa-chevron-down"></i>
                    </button>
                    <div class="dropdown-menu" id="addDropdown">
                        <button type="button" onclick="window.location.href='NETadd.php'">
                            <i class="fa-solid fa-file-circle-plus"></i>
                            Add single asset
                        </button>
                        <button type="button" class="import" onclick="window.location.href='NETcsv.php'">
                            <i class="fa-solid fa-file-import"></i>
                            Import via CSV
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="filter-section">
            <div class="filter-header" id="filterHeader">
                <h3>
                    <i class="fa-solid fa-filter"></i>
                    Filters & Search
                </h3>
                <i class="fa-solid fa-chevron-down"></i>
            </div>
            <div class="filter-content" id="filterContent">
                <div class="filter-group" style="grid-column: 1 / -1;">
                    <label for="searchInput">Search</label>
                    <div class="search-box">
                        <i class="fa-solid fa-search"></i>
                        <input type="text" placeholder="Search Asset ID, Serial, Brand, Model, MAC, IP, Location..." id="searchInput">
                    </div>
                </div>
                <div class="filter-group">
                    <label for="filterStatus">Status</label>
                    <select id="filterStatus">
                        <option value="">All Statuses</option>
                        <?php foreach ($statuses as $status) : ?>
                            <option value="<?php echo htmlspecialchars($status); ?>"><?php echo htmlspecialchars($status); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="filterBrand">Brand</label>
                    <select id="filterBrand">
                        <option value="">All Brands</option>
                        <?php foreach ($brands as $brand) : ?>
                            <option value="<?php echo htmlspecialchars($brand); ?>"><?php echo htmlspecialchars($brand); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="filterBuilding">Building</label>
                    <select id="filterBuilding">
                        <option value="">All Buildings</option>
                        <?php foreach ($buildings as $building) : ?>
                            <option value="<?php echo htmlspecialchars($building); ?>"><?php echo htmlspecialchars($building); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="filterLevel">Level</label>
                    <select id="filterLevel">
                        <option value="">All Levels</option>
                        <?php foreach ($levels as $level) : ?>
                            <option value="<?php echo htmlspecialchars($level); ?>"><?php echo htmlspecialchars($level); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <button type="button" class="btn-clear-filters" id="btnClearFilters">
                        <i class="fa-solid fa-times"></i> Clear Filters
                    </button>
                </div>
            </div>
        </div>

        <div class="assets-table-container">
            <table class="assets-table">
                <thead>
                    <tr>
                        <th>Asset ID</th>
                        <th>Brand / Model</th>
                        <th>Serial Number</th>
                        <th>MAC Address</th>
                        <th>IP Address</th>
                        <th>Location</th>
                        <th>Status</th>
                        <th>Remarks</th>
                        <th>Created By</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="assetsTableBody">
                    <?php if ($netAssetsError) : ?>
                        <tr>
                            <td colspan="10">
                                <div class="data-message"><?php echo htmlspecialchars($netAssetsError); ?></div>
                            </td>
                        </tr>
                    <?php elseif (empty($netAssets)) : ?>
                        <tr>
                            <td colspan="10">
                                <div class="empty-state">
                                    <i class="fa-solid fa-network-wired"></i>
                                    <p>No assets found</p>
                                    <span>Start by adding your first network equipment</span>
                                </div>
                            </td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($netAssets as $asset) : ?>
                            <?php
                                $statusClass = formatStatusClass($asset['status'] ?? '');
                                $statusLabel = formatStatusLabel($asset['status'] ?? '');
                                $locationParts = array_filter([$asset['building'] ?? '', $asset['level'] ?? '']);
                                $locationLabel = !empty($locationParts) ? implode(', ', $locationParts) : '-';
                                $createdMeta = $asset['created_at'] ? date('d M Y, H:i', strtotime($asset['created_at'])) : '-';
                                $remarks = $asset['remarks'] ?? '';
                                $brand = trim((string)($asset['brand'] ?? ''));
                                $model = trim((string)($asset['model'] ?? ''));
                                $brandModel = trim($brand . ' ' . $model);
                                if ($brandModel === '') {
                                    $brandModel = '-';
                                }
                                $serial = trim((string)($asset['serial'] ?? ''));
                                if ($serial === '') {
                                    $serial = '-';
                                }
                            ?>
                            <tr data-asset-id="<?php echo htmlspecialchars(formatAssetId($asset['asset_id'])); ?>"
                                data-serial="<?php echo htmlspecialchars(strtolower($serial)); ?>"
                                data-brand="<?php echo htmlspecialchars(strtolower($brand)); ?>"
                                data-model="<?php echo htmlspecialchars(strtolower($model)); ?>"
                                data-status="<?php echo htmlspecialchars(strtoupper($asset['status'] ?? '')); ?>"
                                data-mac="<?php echo htmlspecialchars(strtolower($asset['mac_add'] ?? '')); ?>"
                                data-ip="<?php echo htmlspecialchars(strtolower($asset['ip_add'] ?? '')); ?>"
                                data-building="<?php echo htmlspecialchars(strtolower($asset['building'] ?? '')); ?>"
                                data-level="<?php echo htmlspecialchars(strtolower($asset['level'] ?? '')); ?>">
                                <td class="asset-id"><?php echo htmlspecialchars(formatAssetId($asset['asset_id'])); ?></td>
                                <td><?php echo htmlspecialchars($brandModel); ?></td>
                                <td><?php echo htmlspecialchars($serial); ?></td>
                                <td><?php echo htmlspecialchars($asset['mac_add'] ?: '-'); ?></td>
                                <td><?php echo htmlspecialchars($asset['ip_add'] ?: '-'); ?></td>
                                <td><?php echo htmlspecialchars($locationLabel); ?></td>
                                <td>
                                    <span class="status-badge <?php echo htmlspecialchars($statusClass); ?>" title="<?php echo htmlspecialchars($statusLabel); ?>">
                                        <i class="fa-solid <?php echo htmlspecialchars(formatStatusIcon($asset['status'] ?? '')); ?>"></i>
                                        <span class="status-tooltip"><?php echo htmlspecialchars($statusLabel); ?></span>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($remarks !== '' ? $remarks : '-'); ?></td>
                                <td>
                                    <div class="asset-meta">
                                        <span><?php echo htmlspecialchars($asset['created_by_name'] ?? 'Unknown'); ?></span>
                                        <span><?php echo htmlspecialchars($createdMeta); ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn-action" onclick="window.location.href='../pages/NETview.php?id=<?php echo $asset['asset_id']; ?>'">
                                            <i class="fa-solid fa-eye"></i> View
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <footer>
        <?php include_once("../components/Footer.php"); ?>
    </footer>

    <script>
        const filterHeader = document.getElementById('filterHeader');
        const filterContent = document.getElementById('filterContent');
        
        filterHeader.addEventListener('click', () => {
            filterHeader.classList.toggle('collapsed');
            filterContent.classList.toggle('collapsed');
        });

        function filterTable() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase().trim();
            const statusFilterValue = document.getElementById('filterStatus').value.trim();
            const statusFilter = statusFilterValue ? statusFilterValue.toUpperCase() : '';
            const brandFilter = document.getElementById('filterBrand').value.toLowerCase().trim();
            const buildingFilter = document.getElementById('filterBuilding').value.toLowerCase().trim();
            const levelFilter = document.getElementById('filterLevel').value.toLowerCase().trim();
            const rows = document.querySelectorAll('.assets-table tbody tr');
            
            rows.forEach(row => {
                if (row.querySelector('.data-message') || row.querySelector('.empty-state')) {
                    return;
                }
                
                let show = true;
                
                if (searchTerm) {
                    const assetId = (row.dataset.assetId || '').toLowerCase();
                    const serial = row.dataset.serial || '';
                    const brand = row.dataset.brand || '';
                    const model = row.dataset.model || '';
                    const mac = row.dataset.mac || '';
                    const ip = row.dataset.ip || '';
                    const building = row.dataset.building || '';
                    const level = row.dataset.level || '';
                    
                    const matchesSearch = assetId.includes(searchTerm) ||
                                        serial.includes(searchTerm) ||
                                        brand.includes(searchTerm) ||
                                        model.includes(searchTerm) ||
                                        mac.includes(searchTerm) ||
                                        ip.includes(searchTerm) ||
                                        building.includes(searchTerm) ||
                                        level.includes(searchTerm);
                    
                    if (!matchesSearch) {
                        show = false;
                    }
                }
                
                if (statusFilter && row.dataset.status !== statusFilter) {
                    show = false;
                }
                
                if (brandFilter && row.dataset.brand !== brandFilter) {
                    show = false;
                }
                
                if (buildingFilter && row.dataset.building !== buildingFilter) {
                    show = false;
                }
                
                if (levelFilter && row.dataset.level !== levelFilter) {
                    show = false;
                }
                
                row.style.display = show ? '' : 'none';
            });
        }
        
        document.getElementById('searchInput').addEventListener('input', filterTable);
        document.getElementById('filterStatus').addEventListener('change', filterTable);
        document.getElementById('filterBrand').addEventListener('change', filterTable);
        document.getElementById('filterBuilding').addEventListener('change', filterTable);
        document.getElementById('filterLevel').addEventListener('change', filterTable);
        
        document.getElementById('btnClearFilters').addEventListener('click', function() {
            document.getElementById('searchInput').value = '';
            document.getElementById('filterStatus').value = '';
            document.getElementById('filterBrand').value = '';
            document.getElementById('filterBuilding').value = '';
            document.getElementById('filterLevel').value = '';
            filterTable();
        });

        const addButton = document.getElementById('btn-add');
        const dropdown = document.getElementById('addDropdown');

        addButton.addEventListener('click', () => {
            dropdown.classList.toggle('open');
        });

        document.addEventListener('click', (event) => {
            if (!addButton.contains(event.target) && !dropdown.contains(event.target)) {
                dropdown.classList.remove('open');
            }
        });
    </script>
</body>
</html>

