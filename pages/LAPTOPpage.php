<?php
session_start();
require_once '../database/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$pdo = getDBConnection();
$laptopAssets = [];
$laptopAssetsError = '';
$categories = [];
$statuses = [];
$brands = [];
$assignmentTypes = [];
$locations = [];

try {
    $stmt = $pdo->query("
        SELECT la.*, sl.staff_name AS assigned_to_name
        FROM laptop_desktop_assets la
        LEFT JOIN staff_list sl ON la.staff_id = sl.staff_id
        ORDER BY la.created_at DESC, la.asset_id DESC
    ");
    $laptopAssets = $stmt->fetchAll();
    
    $categoryStmt = $pdo->query("SELECT DISTINCT category FROM laptop_desktop_assets WHERE category IS NOT NULL AND category != '' ORDER BY category");
    $categories = $categoryStmt->fetchAll(PDO::FETCH_COLUMN);
    
    $statusStmt = $pdo->query("SELECT DISTINCT status FROM laptop_desktop_assets WHERE status IS NOT NULL AND status != '' ORDER BY status");
    $statuses = $statusStmt->fetchAll(PDO::FETCH_COLUMN);
    
    $brandStmt = $pdo->query("SELECT DISTINCT brand FROM laptop_desktop_assets WHERE brand IS NOT NULL AND brand != '' ORDER BY brand");
    $brands = $brandStmt->fetchAll(PDO::FETCH_COLUMN);
    
    $assignmentStmt = $pdo->query("SELECT DISTINCT assignment_type FROM laptop_desktop_assets WHERE assignment_type IS NOT NULL AND assignment_type != '' ORDER BY assignment_type");
    $assignmentTypes = $assignmentStmt->fetchAll(PDO::FETCH_COLUMN);
    
    $locationStmt = $pdo->query("SELECT DISTINCT location FROM laptop_desktop_assets WHERE location IS NOT NULL AND location != '' ORDER BY location");
    $locations = $locationStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $laptopAssetsError = 'Unable to load laptop/desktop assets right now. Please try again later.';
}

function formatAssetId($id)
{
    return sprintf('LAP-%05d', $id);
}

function formatStatusClass($status)
{
    $status = strtoupper(trim($status ?? ''));
    $map = [
        'ACTIVE' => 'active',
        'DEPLOY' => 'deploy',
        'IN-USE' => 'in-use',
        'MAINTENANCE' => 'maintenance',
        'DISPOSED' => 'disposed',
        'DISPOSE' => 'dispose',
        'FAULTY' => 'faulty',
        'RESERVED' => 'reserved',
        'NON-ACTIVE' => 'non-active',
        'LOST' => 'lost',
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
        'ACTIVE' => 'fa-circle-check',
        'DEPLOY' => 'fa-circle-check',
        'IN-USE' => 'fa-laptop',
        'FAULTY' => 'fa-triangle-exclamation',
        'DISPOSE' => 'fa-trash',
        'DISPOSED' => 'fa-trash',
        'RESERVED' => 'fa-bookmark',
        'MAINTENANCE' => 'fa-wrench',
        'NON-ACTIVE' => 'fa-circle-pause',
        'LOST' => 'fa-circle-question',
    ];
    return $iconMap[$status] ?? 'fa-circle-question';
}

function formatCategoryClass($category)
{
    $category = trim((string)($category ?? ''));
    if ($category === '') {
        return 'other';
    }
    
    $categoryUpper = strtoupper($category);
    $categoryNormalized = str_replace([' ', '_'], '-', strtolower($category));
    
    $categoryMap = [
        'NOTEBOOK' => 'notebook',
        'NOTEBOOK-STANDBY' => 'notebook-standby',
        'DESKTOP AIO' => 'desktop-aio',
        'DESKTOP-AIO' => 'desktop-aio',
        'DESKTOP AIO-SHARING' => 'desktop-aio-sharing',
        'DESKTOP-AIO-SHARING' => 'desktop-aio-sharing'
    ];
    
    if (isset($categoryMap[$categoryUpper])) {
        return $categoryMap[$categoryUpper];
    }
    
    if (in_array($categoryNormalized, ['notebook', 'notebook-standby', 'desktop-aio', 'desktop-aio-sharing'], true)) {
        return $categoryNormalized;
    }
    
    return 'other';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laptop & Desktop Assets - UniKL RCMP IT Inventory</title>
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
            flex-wrap: wrap;
        }

        .actions-group {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .dropdown-wrapper {
            position: relative;
        }

        .stock-type-tabs {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(0, 0, 0, 0.05);
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }

        .stock-tab {
            flex: 1;
            padding: 15px 25px;
            background: #f1f2f6;
            border: 2px solid transparent;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            color: #2d3436;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .stock-tab:hover {
            background: #e3e6ed;
            transform: translateY(-2px);
        }

        .stock-tab.active {
            background: #1a1a2e;
            color: #ffffff;
            border-color: #1a1a2e;
            box-shadow: 0 4px 12px rgba(26, 26, 46, 0.3);
        }

        .stock-tab i {
            font-size: 1.1rem;
        }

        .search-section {
            margin-bottom: 25px;
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(0, 0, 0, 0.05);
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }

        .search-box {
            position: relative;
            display: flex;
            align-items: center;
        }

        .search-box input {
            width: 100%;
            padding: 12px 16px 12px 48px;
            border: 2px solid rgba(0, 0, 0, 0.1);
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.2s ease;
        }

        .search-box input:focus {
            outline: none;
            border-color: #1a1a2e;
            box-shadow: 0 0 0 3px rgba(26, 26, 46, 0.1);
        }

        .search-box i {
            position: absolute;
            left: 18px;
            color: #636e72;
            font-size: 1.1rem;
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

        .btn-queue {
            padding: 10px 20px;
            background: #0984e3;
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
            box-shadow: 0 4px 12px rgba(9, 132, 227, 0.2);
        }

        .btn-queue:hover {
            background: #0770c4;
            box-shadow: 0 6px 16px rgba(9, 132, 227, 0.3);
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

        .assets-table th:last-child,
        .assets-table td:last-child {
            text-align: center;
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

        .asset-type.notebook {
            background: rgba(9, 132, 227, 0.15);
            color: #0984e3;
        }

        .asset-type.notebook-standby {
            background: rgba(116, 185, 255, 0.15);
            color: #74b9ff;
        }

        .asset-type.desktop-aio {
            background: rgba(0, 206, 201, 0.15);
            color: #00cec9;
        }

        .asset-type.desktop-aio-sharing {
            background: rgba(108, 92, 231, 0.15);
            color: #6c5ce7;
        }

        .asset-type.other {
            background: rgba(99, 110, 114, 0.15);
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
        .status-badge.active,
        .status-badge.deploy {
            background: rgba(0, 184, 148, 0.15);
            color: #00b894;
        }

        .status-badge.in-use {
            background: rgba(108, 92, 231, 0.15);
            color: #6c5ce7;
        }

        .status-badge.maintenance,
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

        .status-badge.non-active {
            background: rgba(99, 110, 114, 0.15);
            color: #636e72;
        }

        .status-badge.lost {
            background: rgba(214, 48, 49, 0.15);
            color: #d63031;
        }

        .status-badge.unavailable {
            background: rgba(214, 48, 49, 0.15);
            color: #d63031;
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
            justify-content: center;
        }

        .btn-action {
            width: 32px;
            height: 32px;
            padding: 0;
            border-radius: 50%;
            border: 1px solid rgba(0, 0, 0, 0.1);
            background: #ffffff;
            cursor: pointer;
            transition: all 0.2s ease;
            color: #2d3436;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .btn-action:hover {
            background: #1a1a2e;
            color: #ffffff;
            border-color: #1a1a2e;
            transform: scale(1.05);
        }

        .btn-action.view {
            color: #0984e3;
        }

        .btn-action.view:hover {
            color: #ffffff;
        }

        .btn-action.handover {
            color: #00b894;
        }

        .btn-action.handover:hover {
            color: #ffffff;
        }

        .btn-action.return {
            color: #e17055;
        }

        .btn-action.return:hover {
            color: #ffffff;
        }

        .btn-action.repair {
            color: #d35400;
            border-color: rgba(211, 84, 0, 0.25);
        }

        .btn-action.repair:hover {
            background: #d35400;
            color: #ffffff;
            border-color: #d35400;
        }

        .action-tooltip {
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            margin-bottom: 8px;
            padding: 6px 10px;
            background: #1a1a2e;
            color: #ffffff;
            border-radius: 6px;
            font-size: 0.8rem;
            white-space: nowrap;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.2s ease;
            z-index: 1000;
        }

        .action-tooltip::after {
            content: '';
            position: absolute;
            top: 100%;
            left: 50%;
            transform: translateX(-50%);
            border: 5px solid transparent;
            border-top-color: #1a1a2e;
        }

        .btn-action:hover .action-tooltip {
            opacity: 1;
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
            <h1 class="page-title">Laptop & Desktop Assets</h1>
            <div class="page-actions">
                <div class="actions-group">
                    <div class="dropdown-wrapper">
                        <button class="btn-add" id="btn-add" type="button">
                            <i class="fa-solid fa-plus"></i>
                            Add Assets
                            <i class="fa-solid fa-chevron-down"></i>
                        </button>
                        <div class="dropdown-menu" id="addDropdown">
                            <button type="button" onclick="window.location.href='LAPTOPadd.php'">
                                <i class="fa-solid fa-file-circle-plus"></i>
                                Add single asset
                            </button>
                            <button type="button" class="import" onclick="window.location.href='LAPTOPcsv.php'">
                                <i class="fa-solid fa-file-import"></i>
                                Import via CSV
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="stock-type-tabs">
            <div class="stock-tab active" data-stock-type="in-stock" id="tabInStock">
                <i class="fa-solid fa-warehouse"></i>
                <span>In Stock</span>
            </div>
            <div class="stock-tab" data-stock-type="out-stock" id="tabOutStock">
                <i class="fa-solid fa-box-open"></i>
                <span>Out Stock</span>
            </div>
        </div>

        <div class="search-section">
            <div class="search-box">
                <i class="fa-solid fa-search"></i>
                <input type="text" placeholder="Search Asset ID, Serial, Brand, Model, Assigned To..." id="searchInput">
            </div>
        </div>

        <div class="assets-table-container">
            <table class="assets-table">
                <thead>
                    <tr>
                        <th>Asset ID</th>
                        <th>Type</th>
                        <th>Brand/Model</th>
                        <th>Serial Number</th>
                        <th>Assigned To</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="assetsTableBody">
                    <?php if ($laptopAssetsError) : ?>
                        <tr>
                            <td colspan="7">
                                <div class="data-message"><?php echo htmlspecialchars($laptopAssetsError); ?></div>
                            </td>
                        </tr>
                    <?php elseif (empty($laptopAssets)) : ?>
                        <tr>
                            <td colspan="7">
                                <div class="empty-state">
                                    <i class="fa-solid fa-laptop"></i>
                                    <p>No assets found</p>
                                    <span>Start by adding your first laptop or desktop asset</span>
                                </div>
                            </td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($laptopAssets as $asset) : ?>
                            <?php
                                $statusClass = formatStatusClass($asset['status'] ?? '');
                                $statusLabel = formatStatusLabel($asset['status'] ?? '');
                                $rawStatus = strtoupper(trim((string)($asset['status'] ?? '')));
                                $category = trim((string)($asset['category'] ?? ''));
                                $categoryClass = formatCategoryClass($category);
                                $brand = trim((string)($asset['brand'] ?? ''));
                                $model = trim((string)($asset['model'] ?? ''));
                                $brandModel = trim($brand . ' ' . $model);
                                if ($brandModel === '') {
                                    $brandModel = '-';
                                }
                                $serial = trim((string)($asset['serial_num'] ?? ''));
                                if ($serial === '') {
                                    $serial = '-';
                                }
                                $assignedTo = trim((string)($asset['assigned_to_name'] ?? ''));
                                if ($assignedTo === '') {
                                    $assignedTo = '-';
                                }
                            ?>
                            <tr data-asset-id="<?php echo htmlspecialchars(formatAssetId($asset['asset_id'])); ?>"
                                data-serial="<?php echo htmlspecialchars(strtolower($serial)); ?>"
                                data-brand="<?php echo htmlspecialchars(strtolower($brand)); ?>"
                                data-model="<?php echo htmlspecialchars(strtolower($model)); ?>"
                                data-category="<?php echo htmlspecialchars($category); ?>"
                                data-status="<?php echo htmlspecialchars(strtoupper($asset['status'] ?? '')); ?>"
                                data-assigned="<?php echo htmlspecialchars(strtolower($assignedTo)); ?>"
                                data-assignment-type="<?php echo htmlspecialchars(strtolower($asset['assignment_type'] ?? '')); ?>"
                                data-location="<?php echo htmlspecialchars(strtolower($asset['location'] ?? '')); ?>">
                                <td class="asset-id"><?php echo htmlspecialchars(formatAssetId($asset['asset_id'])); ?></td>
                                <td>
                                    <span class="asset-type <?php echo htmlspecialchars($categoryClass); ?>">
                                        <?php echo htmlspecialchars($category ?: 'Other'); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($brandModel); ?></td>
                                <td><?php echo htmlspecialchars($serial); ?></td>
                                <td><?php echo htmlspecialchars($assignedTo); ?></td>
                                <td>
                                    <span class="status-badge <?php echo htmlspecialchars($statusClass); ?>" title="<?php echo htmlspecialchars($statusLabel); ?>">
                                        <i class="fa-solid <?php echo htmlspecialchars(formatStatusIcon($asset['status'] ?? '')); ?>"></i>
                                        <span class="status-tooltip"><?php echo htmlspecialchars($statusLabel); ?></span>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn-action view" onclick="window.location.href='LAPTOPview.php?id=<?php echo $asset['asset_id']; ?>'" aria-label="View details">
                                            <i class="fa-solid fa-eye"></i>
                                            <span class="action-tooltip">View details</span>
                                        </button>
                                        <?php if ($rawStatus === 'ACTIVE') : ?>
                                            <button class="btn-action handover" onclick="window.location.href='../technician/HANDform.php?asset_id=<?php echo $asset['asset_id']; ?>&asset_type=laptop_desktop'" aria-label="Handover asset">
                                                <i class="fa-solid fa-hand-holding"></i>
                                                <span class="action-tooltip">Handover</span>
                                            </button>
                                        <?php elseif ($rawStatus === 'DEPLOY') : ?>
                                            <button class="btn-action return" onclick="window.location.href='../technician/HANDreturn.php?asset_id=<?php echo $asset['asset_id']; ?>&asset_type=laptop_desktop'" aria-label="Return asset">
                                                <i class="fa-solid fa-rotate-left"></i>
                                                <span class="action-tooltip">Return</span>
                                            </button>
                                        <?php elseif ($rawStatus === 'FAULTY' || $rawStatus === 'MAINTENANCE') : ?>
                                            <button class="btn-action repair" onclick="openRepairForm(<?php echo $asset['asset_id']; ?>, 'laptop_desktop')" aria-label="Repair asset">
                                                <i class="fa-solid fa-screwdriver-wrench"></i>
                                                <span class="action-tooltip">Repair</span>
                                            </button>
                                        <?php endif; ?>
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
        const inStockStatuses = ['ACTIVE', 'FAULTY', 'DISPOSE', 'DISPOSED', 'RESERVED', 'MAINTENANCE', 'NON-ACTIVE', 'LOST'];
        const outStockStatuses = ['DEPLOY', 'IN-USE', 'LOST'];
        let currentStockType = 'in-stock';

        const stockTabs = document.querySelectorAll('.stock-tab');
        stockTabs.forEach(tab => {
            tab.addEventListener('click', function() {
                stockTabs.forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                currentStockType = this.dataset.stockType;
                updateStockView();
            });
        });

        function getStockType(status) {
            const statusUpper = status ? status.toUpperCase() : '';
            if (inStockStatuses.includes(statusUpper)) {
                return 'in-stock';
            } else if (outStockStatuses.includes(statusUpper)) {
                return 'out-stock';
            }
            return 'unknown';
        }

        function updateStockView() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase().trim();
            const rows = document.querySelectorAll('.assets-table tbody tr');
            
            rows.forEach(row => {
                if (row.querySelector('.data-message') || row.querySelector('.empty-state')) {
                    row.style.display = 'none';
                    return;
                }
                
                const status = row.dataset.status || '';
                const stockType = getStockType(status);
                
                let show = stockType === currentStockType;
                
                if (show && searchTerm) {
                    const assetId = (row.dataset.assetId || '').toLowerCase();
                    const serial = row.dataset.serial || '';
                    const brand = row.dataset.brand || '';
                    const model = row.dataset.model || '';
                    const assigned = row.dataset.assigned || '';
                    
                    show = assetId.includes(searchTerm) ||
                           serial.includes(searchTerm) ||
                           brand.includes(searchTerm) ||
                           model.includes(searchTerm) ||
                           assigned.includes(searchTerm);
                }
                
                row.style.display = show ? '' : 'none';
            });
        }

        document.getElementById('searchInput').addEventListener('input', updateStockView);
        updateStockView();

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

        function openRepairForm(assetId, assetType) {
            const formData = new FormData();
            formData.append('asset_id', assetId);
            formData.append('asset_type', assetType);

            fetch('../services/set_maintenance_status.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = `FAULTYform.php?asset_id=${assetId}&asset_type=${assetType}`;
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while updating asset status. Please try again.');
            });
        }
    </script>
</body>
</html>

