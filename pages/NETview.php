<?php
session_start();
require_once '../database/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$pdo = getDBConnection();
$asset = null;
$error = '';

$assetId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$assetTrails = [];
$warrantyHistory = [];

if ($assetId > 0) {
    try {
        $stmt = $pdo->prepare("
            SELECT na.*, t.tech_name AS created_by_name
            FROM net_assets na
            LEFT JOIN technician t ON na.created_by = t.id
            WHERE na.asset_id = :id
        ");
        $stmt->execute([':id' => $assetId]);
        $asset = $stmt->fetch();
        
        if (!$asset) {
            $error = 'Asset not found.';
        } else {
            $trailStmt = $pdo->prepare("
                SELECT at.*, t.tech_name, t.tech_id
                FROM asset_trails at
                LEFT JOIN technician t ON at.changed_by = t.id
                WHERE at.asset_type = 'network' AND at.asset_id = :id
                ORDER BY at.created_at ASC
            ");
            $trailStmt->execute([':id' => $assetId]);
            $assetTrails = $trailStmt->fetchAll(PDO::FETCH_ASSOC);

            $wStmt = $pdo->prepare("
                SELECT warranty_id, send_date, receive_date, vendor_name, remarks, created_at
                FROM warranty
                WHERE asset_type = 'network' AND asset_id = :id
                ORDER BY created_at DESC
            ");
            $wStmt->execute([':id' => $assetId]);
            $warrantyHistory = $wStmt->fetchAll(PDO::FETCH_ASSOC);
            
            $hasCreateEntry = false;
            foreach ($assetTrails as $trail) {
                if (strtoupper($trail['action_type']) === 'CREATE') {
                    $hasCreateEntry = true;
                    break;
                }
            }
            
            if (!$hasCreateEntry && !empty($asset['created_at'])) {
                $createdByName = $asset['created_by_name'] ?? null;
                $createdById = $asset['created_by'] ?? null;
                
                $createEntry = [
                    'action_type' => 'CREATE',
                    'field_name' => null,
                    'old_value' => null,
                    'new_value' => 'Asset created in system',
                    'description' => 'Asset was initially added to the inventory system' . ($createdByName ? ' by ' . $createdByName : ''),
                    'created_at' => $asset['created_at'],
                    'tech_name' => $createdByName,
                    'tech_id' => null,
                    'changed_by' => $createdById
                ];
                array_unshift($assetTrails, $createEntry);
            }
            
            $trailCounts = [
                'status' => 0,
                'handover' => 0,
                'return' => 0,
                'create' => 0,
                'update' => 0,
                'repair' => 0,
                'total' => count($assetTrails)
            ];
            
            foreach ($assetTrails as $trail) {
                $actionType = strtoupper(trim($trail['action_type'] ?? ''));
                $fieldName = strtoupper(trim($trail['field_name'] ?? ''));
                $description = strtoupper(trim($trail['description'] ?? ''));
                
                if ($actionType === 'CREATE') {
                    $trailCounts['create']++;
                } elseif (strpos($actionType, 'UPDATE') !== false || strpos($actionType, 'EDIT') !== false || strpos($actionType, 'MODIFY') !== false) {
                    $trailCounts['update']++;
                }
                
                if ($fieldName === 'STATUS' || strpos($description, 'STATUS') !== false) {
                    $trailCounts['status']++;
                }
                if (strpos($description, 'HANDOVER') !== false || strpos($description, 'ASSIGN') !== false || strpos($description, 'ASSIGNED') !== false) {
                    $trailCounts['handover']++;
                }
                if (strpos($description, 'RETURN') !== false || strpos($description, 'RETURNED') !== false) {
                    $trailCounts['return']++;
                }
                if (strpos($description, 'REPAIR') !== false || strpos($description, 'MAINTENANCE') !== false || strpos($description, 'FAULTY') !== false) {
                    $trailCounts['repair']++;
                }
            }
        }
    } catch (PDOException $e) {
        $error = 'Unable to load asset details. Please try again later.';
    }
} else {
    $error = 'Invalid asset ID.';
}

function formatAssetId($id) {
    return sprintf('NET-%05d', $id);
}

function formatStatusClass($status) {
    $status = strtoupper(trim($status ?? ''));
    $map = [
        'AVAILABLE' => 'available',
        'UNAVAILABLE' => 'unavailable',
        'MAINTENANCE' => 'maintenance',
        'DISPOSED' => 'disposed',
    ];
    return $map[$status] ?? 'unknown';
}

function formatStatusLabel($status) {
    $status = trim((string)$status);
    return $status === '' ? 'Unknown' : ucwords(str_replace('-', ' ', $status));
}

function formatDate($date) {
    if (!$date || $date === '0000-00-00') return '-';
    return date('d M Y', strtotime($date));
}

function formatCurrency($amount) {
    if (!$amount || $amount == 0) return '-';
    return 'MYR ' . number_format($amount, 2);
}

function getTrailActionIcon($actionType) {
    $action = strtoupper(trim($actionType ?? ''));
    $icons = [
        'CREATE' => 'fa-circle-plus',
        'UPDATE' => 'fa-pen',
        'DELETE' => 'fa-trash',
        'STATUS' => 'fa-circle-check',
        'EDIT' => 'fa-edit',
        'MODIFY' => 'fa-wrench',
    ];
    return $icons[$action] ?? 'fa-circle-info';
}

function getTrailActionClass($actionType) {
    $action = strtoupper(trim($actionType ?? ''));
    if (strpos($action, 'CREATE') !== false) return 'create';
    if (strpos($action, 'UPDATE') !== false || strpos($action, 'EDIT') !== false || strpos($action, 'MODIFY') !== false) return 'update';
    if (strpos($action, 'DELETE') !== false) return 'delete';
    if (strpos($action, 'STATUS') !== false) return 'status';
    return 'update';
}

function getStatusTrailClassFromValue($value) {
    $status = strtoupper(trim($value ?? ''));
    $map = [
        'MAINTENANCE' => 'status-maintenance',
        'FAULTY' => 'status-faulty',
        'DEPLOY' => 'status-deploy',
        'ACTIVE' => 'status-active',
        'AVAILABLE' => 'status-active',
        'IN-USE' => 'status-inuse',
        'ONLINE' => 'status-active',
        'OFFLINE' => 'status-offline',
        'DISPOSED' => 'status-disposed',
        'DISPOSE' => 'status-disposed',
        'RESERVED' => 'status-reserved',
        'LOST' => 'status-lost',
        'NON-ACTIVE' => 'status-nonactive',
    ];
    return $map[$status] ?? 'status-generic';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Network Asset Details - UniKL RCMP IT Inventory</title>
    <link rel="icon" type="image/png" href="../public/rcmp.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/TechDashboard.css">
    <style>
        .view-page-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 40px 20px 80px;
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

        .btn-back {
            padding: 10px 20px;
            background: #f1f2f6;
            color: #2d3436;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-back:hover {
            background: #e3e6ed;
        }

        .asset-details-container {
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(0, 0, 0, 0.05);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.05);
        }

        .asset-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 40px;
            padding-bottom: 30px;
            border-bottom: 2px solid rgba(26, 26, 46, 0.1);
            flex-wrap: wrap;
            gap: 20px;
        }

        .asset-id-section {
            flex: 1;
        }

        .asset-id-label {
            font-size: 0.9rem;
            color: #636e72;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .asset-id-value {
            font-size: 2rem;
            font-weight: 700;
            color: #1a1a2e;
        }

        .asset-status-section {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 10px;
        }

        .status-badge {
            display: inline-block;
            padding: 8px 20px;
            border-radius: 12px;
            font-size: 0.95rem;
            font-weight: 600;
        }

        .status-badge.available {
            background: rgba(0, 184, 148, 0.1);
            color: #00b894;
        }

        .status-badge.unavailable {
            background: rgba(108, 92, 231, 0.1);
            color: #6c5ce7;
        }

        .status-badge.maintenance {
            background: rgba(253, 121, 168, 0.1);
            color: #fd79a8;
        }

        .status-badge.disposed {
            background: rgba(99, 110, 114, 0.1);
            color: #636e72;
        }

        .status-badge.unknown {
            background: rgba(99, 110, 114, 0.15);
            color: #2d3436;
        }

        .details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
        }

        .detail-section {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .section-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: #1a1a2e;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 2px solid rgba(26, 26, 46, 0.1);
        }

        .detail-item {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .detail-label {
            font-size: 0.85rem;
            color: #636e72;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        .detail-value {
            font-size: 1rem;
            color: #2d3436;
            font-weight: 500;
        }

        .detail-value.empty {
            color: #b2bec3;
            font-style: italic;
        }

        .error-message {
            text-align: center;
            padding: 60px 20px;
            color: #c0392b;
            font-size: 1.1rem;
            font-weight: 600;
        }

        .error-message i {
            font-size: 4rem;
            margin-bottom: 20px;
            color: rgba(192, 57, 43, 0.3);
        }

        @media (max-width: 768px) {
            .view-page-container {
                padding: 90px 15px 60px;
            }

            .asset-details-container {
                padding: 25px;
            }

            .asset-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .asset-status-section {
                align-items: flex-start;
            }

            .details-grid {
                grid-template-columns: 1fr;
                gap: 25px;
            }
        }
        .simple-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #ffffff;
            color: #2d3436;
            height: 70px;
            padding: 0 30px;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1050;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            border-bottom: 1px solid rgba(0, 0, 0, 0.08);
        }

        .simple-header .logo {
            display: flex;
            align-items: center;
            font-size: 1.3rem;
            font-weight: 600;
            gap: 12px;
            text-decoration: none;
            color: #2d3436;
        }

        .simple-header .logo img {
            height: 38px;
        }

        .simple-header .user-menu {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 16px;
            background: #f8f9fa;
            border: 1px solid rgba(0, 0, 0, 0.08);
            border-radius: 20px;
        }

        .simple-header .user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #6c5ce7;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 600;
            font-size: 0.85rem;
            overflow: hidden;
        }

        .simple-header .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .simple-header .user-name {
            font-size: 0.9rem;
            font-weight: 500;
            color: #2d3436;
        }

        .tab-navigation {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            border-bottom: 2px solid rgba(26, 26, 46, 0.1);
        }

        .tab-btn {
            padding: 12px 24px;
            background: transparent;
            border: none;
            border-bottom: 3px solid transparent;
            font-size: 1rem;
            font-weight: 600;
            color: #636e72;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            bottom: -2px;
        }

        .tab-btn:hover {
            color: #6c5ce7;
        }

        .tab-btn.active {
            color: #6c5ce7;
            border-bottom-color: #6c5ce7;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .trail-item {
            padding: 16px 20px;
            margin-bottom: 12px;
            background: #ffffff;
            border-left: 3px solid #6c5ce7;
            border-radius: 8px;
            transition: all 0.2s ease;
        }

        .trail-item:hover {
            background: #f8f9fa;
        }

        .trail-item.create {
            border-left-color: #00b894;
        }

        .trail-item.update {
            border-left-color: #0984e3;
        }

        .trail-item.delete {
            border-left-color: #d63031;
        }

        .trail-item.status {
            border-left-color: #6c5ce7;
        }

        .trail-item.status-maintenance { border-left-color: #fd79a8; }
        .trail-item.status-faulty { border-left-color: #d63031; }
        .trail-item.status-deploy { border-left-color: #00b894; }
        .trail-item.status-active { border-left-color: #00b894; }
        .trail-item.status-inuse { border-left-color: #6c5ce7; }
        .trail-item.status-offline { border-left-color: #e67e22; }
        .trail-item.status-reserved { border-left-color: #0984e3; }
        .trail-item.status-disposed { border-left-color: #636e72; }
        .trail-item.status-lost { border-left-color: #e74c3c; }
        .trail-item.status-nonactive { border-left-color: #636e72; }
        .trail-item.status-generic { border-left-color: #6c5ce7; }

        .trail-item.status-maintenance { border-left-color: #fd79a8; }
        .trail-item.status-faulty { border-left-color: #d63031; }
        .trail-item.status-deploy { border-left-color: #00b894; }
        .trail-item.status-active { border-left-color: #00b894; }
        .trail-item.status-inuse { border-left-color: #6c5ce7; }
        .trail-item.status-offline { border-left-color: #e67e22; }
        .trail-item.status-reserved { border-left-color: #0984e3; }
        .trail-item.status-disposed { border-left-color: #636e72; }
        .trail-item.status-lost { border-left-color: #e74c3c; }
        .trail-item.status-nonactive { border-left-color: #636e72; }
        .trail-item.status-generic { border-left-color: #6c5ce7; }

        .trail-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            flex-wrap: wrap;
            gap: 12px;
        }

        .trail-action-wrapper {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .trail-action-icon {
            width: 32px;
            height: 32px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            flex-shrink: 0;
        }

        .trail-item.create .trail-action-icon {
            background: rgba(0, 184, 148, 0.1);
            color: #00b894;
        }

        .trail-item.update .trail-action-icon {
            background: rgba(9, 132, 227, 0.1);
            color: #0984e3;
        }

        .trail-item.delete .trail-action-icon {
            background: rgba(214, 48, 49, 0.1);
            color: #d63031;
        }

        .trail-item.status .trail-action-icon {
            background: rgba(108, 92, 231, 0.1);
            color: #6c5ce7;
        }

        .trail-action {
            font-weight: 600;
            color: #1a1a2e;
            font-size: 0.95rem;
        }

        .trail-date {
            font-size: 0.85rem;
            color: #636e72;
        }

        .trail-details {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 10px;
        }

        .trail-field {
            display: flex;
            gap: 8px;
            align-items: baseline;
            font-size: 0.9rem;
        }

        .trail-field-label {
            font-weight: 500;
            color: #636e72;
            min-width: 80px;
        }

        .trail-field-value {
            color: #2d3436;
            flex: 1;
        }

        .trail-field-value.old-value {
            color: #d63031;
            text-decoration: line-through;
            opacity: 0.6;
            margin-right: 8px;
        }

        .trail-field-value.new-value {
            color: #00b894;
            font-weight: 500;
        }

        .trail-changed-by {
            display: flex;
            align-items: center;
            gap: 6px;
            padding-top: 10px;
            border-top: 1px solid rgba(0, 0, 0, 0.06);
            font-size: 0.85rem;
            color: #636e72;
        }

        .trail-changed-by i {
            color: #6c5ce7;
            font-size: 0.8rem;
        }

        .trail-changed-by-name {
            font-weight: 500;
            color: #1a1a2e;
        }

        .trail-empty {
            text-align: center;
            padding: 60px 20px;
            color: #636e72;
        }

        .trail-empty i {
            font-size: 3rem;
            margin-bottom: 15px;
            color: rgba(99, 110, 114, 0.3);
        }

        .trail-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
            padding: 20px;
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            border-radius: 12px;
            border: 1px solid rgba(0, 0, 0, 0.08);
        }

        .trail-stat-card {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 16px;
            background: #ffffff;
            border-radius: 10px;
            border: 1px solid rgba(0, 0, 0, 0.08);
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.04);
            transition: all 0.3s ease;
        }

        .trail-stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }

        .trail-stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            margin-bottom: 10px;
        }

        .trail-stat-card.status .trail-stat-icon {
            background: rgba(108, 92, 231, 0.15);
            color: #6c5ce7;
        }

        .trail-stat-card.handover .trail-stat-icon {
            background: rgba(9, 132, 227, 0.15);
            color: #0984e3;
        }

        .trail-stat-card.return .trail-stat-icon {
            background: rgba(0, 184, 148, 0.15);
            color: #00b894;
        }

        .trail-stat-card.create .trail-stat-icon {
            background: rgba(0, 184, 148, 0.15);
            color: #00b894;
        }

        .trail-stat-card.update .trail-stat-icon {
            background: rgba(9, 132, 227, 0.15);
            color: #0984e3;
        }

        .trail-stat-card.repair .trail-stat-icon {
            background: rgba(253, 121, 168, 0.15);
            color: #fd79a8;
        }

        .trail-stat-count {
            font-size: 2rem;
            font-weight: 700;
            color: #1a1a2e;
            margin-bottom: 4px;
        }

        .trail-stat-label {
            font-size: 0.85rem;
            color: #636e72;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            text-align: center;
        }
    </style>
</head>
<body>
    <header class="simple-header">
        <a class="logo" href="../index.php">
            <img src="../public/unikl-rcmp.png" alt="UniKL RCMP Logo">
            <span>UniKL RCMP IT Inventory</span>
        </a>
        <div class="header-actions">
            <?php
            $profile_picture = null;
            if (isset($_SESSION['user_id'])) {
                try {
                    $stmt = $pdo->prepare("SELECT profile_picture FROM technician WHERE id = ?");
                    $stmt->execute([$_SESSION['user_id']]);
                    $result = $stmt->fetch();
                    if ($result && !empty($result['profile_picture'])) {
                        $profile_picture = $result['profile_picture'];
                    }
                } catch (Exception $e) {
                }
            }
            ?>
            <?php if (isset($_SESSION['full_name'])): ?>
                <div class="user-menu">
                    <div class="user-avatar">
                        <?php if (!empty($profile_picture) && file_exists(__DIR__ . '/../' . $profile_picture)): ?>
                            <img src="../<?php echo htmlspecialchars($profile_picture); ?>" alt="Profile Picture">
                        <?php else: ?>
                            <?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?>
                        <?php endif; ?>
                    </div>
                    <span class="user-name"><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                </div>
            <?php endif; ?>
        </div>
    </header>

    <div class="view-page-container">
        <div class="page-header">
            <h1 class="page-title">Network Asset Details</h1>
            <a href="NETpage.php" class="btn-back">
                <i class="fa-solid fa-arrow-left"></i>
                Back to Assets
            </a>
        </div>

        <?php if ($error || !$asset) : ?>
            <div class="asset-details-container">
                <div class="error-message">
                    <i class="fa-solid fa-exclamation-triangle"></i>
                    <p><?php echo htmlspecialchars($error); ?></p>
                </div>
            </div>
        <?php else : ?>
            <div class="asset-details-container">
                <div class="asset-header">
                    <div class="asset-id-section">
                        <div class="asset-id-label">Asset ID</div>
                        <div class="asset-id-value"><?php echo htmlspecialchars(formatAssetId($asset['asset_id'])); ?></div>
                    </div>
                    <div class="asset-status-section">
                        <span class="status-badge <?php echo htmlspecialchars(formatStatusClass($asset['status'] ?? '')); ?>">
                            <?php echo htmlspecialchars(formatStatusLabel($asset['status'] ?? '')); ?>
                        </span>
                    </div>
                </div>

                <div class="tab-navigation">
                    <button class="tab-btn active" onclick="switchTab('info')">
                        <i class="fa-solid fa-info-circle"></i> Asset Information
                    </button>
                    <button class="tab-btn" onclick="switchTab('trails')">
                        <i class="fa-solid fa-history"></i> Asset Trails
                    </button>
                            <button class="tab-btn" onclick="switchTab('warranty')">
                                <i class="fa-solid fa-shield-halved"></i> Warranty
                    </button>
                </div>

                <div id="tab-info" class="tab-content active">
                    <div class="details-grid">
                    <div class="detail-section">
                        <h3 class="section-title">Asset Information</h3>
                        <div class="detail-item">
                            <div class="detail-label">Brand</div>
                            <div class="detail-value <?php echo empty($asset['brand']) ? 'empty' : ''; ?>">
                                <?php echo htmlspecialchars($asset['brand'] ?: '-'); ?>
                            </div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Model</div>
                            <div class="detail-value <?php echo empty($asset['model']) ? 'empty' : ''; ?>">
                                <?php echo htmlspecialchars($asset['model'] ?: '-'); ?>
                            </div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Serial Number</div>
                            <div class="detail-value <?php echo empty($asset['serial']) ? 'empty' : ''; ?>">
                                <?php echo htmlspecialchars($asset['serial'] ?: '-'); ?>
                            </div>
                        </div>
                    </div>

                    <div class="detail-section">
                        <h3 class="section-title">Network Details</h3>
                        <div class="detail-item">
                            <div class="detail-label">MAC Address</div>
                            <div class="detail-value <?php echo empty($asset['mac_add']) ? 'empty' : ''; ?>">
                                <?php echo htmlspecialchars($asset['mac_add'] ?: '-'); ?>
                            </div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">IP Address</div>
                            <div class="detail-value <?php echo empty($asset['ip_add']) ? 'empty' : ''; ?>">
                                <?php echo htmlspecialchars($asset['ip_add'] ?: '-'); ?>
                            </div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Status</div>
                            <div class="detail-value">
                                <span class="status-badge <?php echo htmlspecialchars(formatStatusClass($asset['status'] ?? '')); ?>">
                                    <?php echo htmlspecialchars(formatStatusLabel($asset['status'] ?? '')); ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="detail-section">
                        <h3 class="section-title">Location Details</h3>
                        <div class="detail-item">
                            <div class="detail-label">Building</div>
                            <div class="detail-value <?php echo empty($asset['building']) ? 'empty' : ''; ?>">
                                <?php echo htmlspecialchars($asset['building'] ?: '-'); ?>
                            </div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Level</div>
                            <div class="detail-value <?php echo empty($asset['level']) ? 'empty' : ''; ?>">
                                <?php echo htmlspecialchars($asset['level'] ?: '-'); ?>
                            </div>
                        </div>
                    </div>

                    <div class="detail-section">
                        <h3 class="section-title">Purchase Information</h3>
                        <div class="detail-item">
                            <div class="detail-label">P.O. Date</div>
                            <div class="detail-value <?php echo empty($asset['PO_DATE']) ? 'empty' : ''; ?>">
                                <?php echo formatDate($asset['PO_DATE']); ?>
                            </div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">P.O. Number</div>
                            <div class="detail-value <?php echo empty($asset['PO_NUM']) ? 'empty' : ''; ?>">
                                <?php echo htmlspecialchars($asset['PO_NUM'] ?: '-'); ?>
                            </div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">D.O. Date</div>
                            <div class="detail-value <?php echo empty($asset['DO_DATE']) ? 'empty' : ''; ?>">
                                <?php echo htmlspecialchars($asset['DO_DATE'] ?: '-'); ?>
                            </div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">D.O. Number</div>
                            <div class="detail-value <?php echo empty($asset['DO_NUM']) ? 'empty' : ''; ?>">
                                <?php echo htmlspecialchars($asset['DO_NUM'] ?: '-'); ?>
                            </div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Invoice Date</div>
                            <div class="detail-value <?php echo empty($asset['INVOICE_DATE']) ? 'empty' : ''; ?>">
                                <?php echo formatDate($asset['INVOICE_DATE']); ?>
                            </div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Invoice Number</div>
                            <div class="detail-value <?php echo empty($asset['INVOICE_NUM']) ? 'empty' : ''; ?>">
                                <?php echo htmlspecialchars($asset['INVOICE_NUM'] ?: '-'); ?>
                            </div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Purchase Cost</div>
                            <div class="detail-value <?php echo empty($asset['PURCHASE_COST']) ? 'empty' : ''; ?>">
                                <?php echo formatCurrency($asset['PURCHASE_COST']); ?>
                            </div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Warranty Expiry</div>
                            <div class="detail-value <?php echo empty($asset['warranty_expiry']) ? 'empty' : ''; ?>">
                                <?php echo formatDate($asset['warranty_expiry']); ?>
                            </div>
                        </div>
                    </div>

                    <div class="detail-section">
                        <h3 class="section-title">Additional Information</h3>
                        <div class="detail-item">
                            <div class="detail-label">Remarks</div>
                            <div class="detail-value <?php echo empty($asset['remarks']) ? 'empty' : ''; ?>" style="white-space: pre-wrap;">
                                <?php echo htmlspecialchars($asset['remarks'] ?: '-'); ?>
                            </div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Created By</div>
                            <div class="detail-value <?php echo empty($asset['created_by_name']) ? 'empty' : ''; ?>">
                                <?php echo htmlspecialchars($asset['created_by_name'] ?: 'Unknown'); ?>
                            </div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Created At</div>
                            <div class="detail-value <?php echo empty($asset['created_at']) ? 'empty' : ''; ?>">
                                <?php echo $asset['created_at'] ? date('d M Y, H:i', strtotime($asset['created_at'])) : '-'; ?>
                            </div>
                        </div>
                    </div>
                </div>
                </div>

                <div id="tab-trails" class="tab-content">
                    <?php if (empty($assetTrails)) : ?>
                        <div class="trail-empty">
                            <i class="fa-solid fa-inbox"></i>
                            <p>No trail history found for this asset.</p>
                        </div>
                    <?php else : ?>
                        <?php if (isset($trailCounts)) : ?>
                            <div class="trail-stats">
                                <?php if ($trailCounts['status'] > 0) : ?>
                                    <div class="trail-stat-card status">
                                        <div class="trail-stat-icon">
                                            <i class="fa-solid fa-circle-check"></i>
                                        </div>
                                        <div class="trail-stat-count"><?php echo $trailCounts['status']; ?></div>
                                        <div class="trail-stat-label">Status Changes</div>
                                    </div>
                                <?php endif; ?>
                                <?php if ($trailCounts['handover'] > 0) : ?>
                                    <div class="trail-stat-card handover">
                                        <div class="trail-stat-icon">
                                            <i class="fa-solid fa-hand-holding"></i>
                                        </div>
                                        <div class="trail-stat-count"><?php echo $trailCounts['handover']; ?></div>
                                        <div class="trail-stat-label">Handovers</div>
                                    </div>
                                <?php endif; ?>
                                <?php if ($trailCounts['return'] > 0) : ?>
                                    <div class="trail-stat-card return">
                                        <div class="trail-stat-icon">
                                            <i class="fa-solid fa-rotate-left"></i>
                                        </div>
                                        <div class="trail-stat-count"><?php echo $trailCounts['return']; ?></div>
                                        <div class="trail-stat-label">Returns</div>
                                    </div>
                                <?php endif; ?>
                                <?php if ($trailCounts['repair'] > 0) : ?>
                                    <div class="trail-stat-card repair">
                                        <div class="trail-stat-icon">
                                            <i class="fa-solid fa-screwdriver-wrench"></i>
                                        </div>
                                        <div class="trail-stat-count"><?php echo $trailCounts['repair']; ?></div>
                                        <div class="trail-stat-label">Repairs</div>
                                    </div>
                                <?php endif; ?>
                                <?php if ($trailCounts['create'] > 0) : ?>
                                    <div class="trail-stat-card create">
                                        <div class="trail-stat-icon">
                                            <i class="fa-solid fa-circle-plus"></i>
                                        </div>
                                        <div class="trail-stat-count"><?php echo $trailCounts['create']; ?></div>
                                        <div class="trail-stat-label">Created</div>
                                    </div>
                                <?php endif; ?>
                                <?php if ($trailCounts['update'] > 0) : ?>
                                    <div class="trail-stat-card update">
                                        <div class="trail-stat-icon">
                                            <i class="fa-solid fa-pen"></i>
                                        </div>
                                        <div class="trail-stat-count"><?php echo $trailCounts['update']; ?></div>
                                        <div class="trail-stat-label">Updates</div>
                                    </div>
                                <?php endif; ?>
                                <div class="trail-stat-card" style="border-left: 3px solid #1a1a2e;">
                                    <div class="trail-stat-icon" style="background: rgba(26, 26, 46, 0.15); color: #1a1a2e;">
                                        <i class="fa-solid fa-list"></i>
                                    </div>
                                    <div class="trail-stat-count"><?php echo $trailCounts['total']; ?></div>
                                    <div class="trail-stat-label">Total Actions</div>
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php foreach ($assetTrails as $trail) : 
                            $actionType = $trail['action_type'] ?? '';
                            $actionClass = getTrailActionClass($actionType);
                            $actionIcon = getTrailActionIcon($actionType);
                            $isStatusChange = (strtoupper(trim($trail['field_name'] ?? '')) === 'STATUS' || strpos(strtoupper(trim($trail['description'] ?? '')), 'STATUS') !== false);
                            $statusValue = $trail['new_value'] ?? '';
                            if ($isStatusChange && $statusValue !== '') {
                                $actionClass .= ' ' . getStatusTrailClassFromValue($statusValue);
                            }
                            $actionLabel = $isStatusChange && $statusValue !== '' 
                                ? formatStatusLabel($statusValue)
                                : str_replace('_', ' ', $actionType);
                        ?>
                            <div class="trail-item <?php echo htmlspecialchars($actionClass); ?>">
                                <div class="trail-header">
                                    <div class="trail-action-wrapper">
                                        <div class="trail-action-icon">
                                            <i class="fa-solid <?php echo htmlspecialchars($actionIcon); ?>"></i>
                                        </div>
                                        <div class="trail-action"><?php echo htmlspecialchars($actionLabel); ?></div>
                                    </div>
                                    <div class="trail-date">
                                        <?php echo date('d M Y, H:i', strtotime($trail['created_at'])); ?>
                                    </div>
                                </div>
                                <div class="trail-details">
                                    <?php if (!empty($trail['field_name'])) : ?>
                                        <div class="trail-field">
                                            <span class="trail-field-label">Field:</span>
                                            <span class="trail-field-value"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $trail['field_name']))); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($trail['old_value']) && !empty($trail['new_value'])) : ?>
                                        <div class="trail-field">
                                            <span class="trail-field-label">Changed:</span>
                                            <span class="trail-field-value old-value"><?php echo htmlspecialchars($trail['old_value']); ?></span>
                                            <i class="fa-solid fa-arrow-right" style="color: #636e72; font-size: 0.75rem; margin: 0 4px;"></i>
                                            <span class="trail-field-value new-value"><?php echo htmlspecialchars($trail['new_value']); ?></span>
                                        </div>
                                    <?php elseif (!empty($trail['old_value'])) : ?>
                                        <div class="trail-field">
                                            <span class="trail-field-label">Old Value:</span>
                                            <span class="trail-field-value old-value"><?php echo htmlspecialchars($trail['old_value']); ?></span>
                                        </div>
                                    <?php elseif (!empty($trail['new_value'])) : ?>
                                        <div class="trail-field">
                                            <span class="trail-field-label">New Value:</span>
                                            <span class="trail-field-value new-value"><?php echo htmlspecialchars($trail['new_value']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($trail['description'])) : ?>
                                        <div class="trail-field">
                                            <span class="trail-field-label">Note:</span>
                                            <span class="trail-field-value"><?php echo htmlspecialchars($trail['description']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="trail-changed-by">
                                    <i class="fa-solid fa-user"></i>
                                    <span class="trail-changed-by-name"><?php echo htmlspecialchars($trail['tech_name'] ?: ($trail['tech_id'] ? 'Tech #' . $trail['tech_id'] : 'System')); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div id="tab-warranty" class="tab-content">
                    <?php if (empty($warrantyHistory)) : ?>
                        <div class="trail-empty">
                            <i class="fa-solid fa-shield-halved"></i>
                            <p>No warranty records found for this asset.</p>
                        </div>
                    <?php else : ?>
                        <?php foreach ($warrantyHistory as $wr) : ?>
                            <div class="trail-item status">
                                <div class="trail-header">
                                    <div class="trail-action-wrapper">
                                        <div class="trail-action-icon">
                                            <i class="fa-solid fa-shield-halved"></i>
                                        </div>
                                        <div class="trail-action">Warranty</div>
                                    </div>
                                    <div class="trail-date">
                                        <?php echo date('d M Y, H:i', strtotime($wr['created_at'])); ?>
                                    </div>
                                </div>
                                <div class="trail-details">
                                    <div class="trail-field">
                                        <span class="trail-field-label">Send Date:</span>
                                        <span class="trail-field-value"><?php echo htmlspecialchars(formatDate($wr['send_date'])); ?></span>
                                    </div>
                                    <div class="trail-field">
                                        <span class="trail-field-label">Receive Date:</span>
                                        <span class="trail-field-value"><?php echo !empty($wr['receive_date']) ? htmlspecialchars(formatDate($wr['receive_date'])) : 'Pending'; ?></span>
                                    </div>
                                    <div class="trail-field">
                                        <span class="trail-field-label">Vendor:</span>
                                        <span class="trail-field-value"><?php echo htmlspecialchars($wr['vendor_name']); ?></span>
                                    </div>
                                    <?php if (!empty($wr['remarks'])) : ?>
                                        <div class="trail-field">
                                            <span class="trail-field-label">Remarks:</span>
                                            <span class="trail-field-value"><?php echo htmlspecialchars($wr['remarks']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <footer>
        <?php include_once("../components/Footer.php"); ?>
    </footer>

    <script>
        function switchTab(tabName) {
            const tabs = document.querySelectorAll('.tab-btn');
            const contents = document.querySelectorAll('.tab-content');
            
            tabs.forEach(tab => tab.classList.remove('active'));
            contents.forEach(content => content.classList.remove('active'));
            
            event.target.classList.add('active');
            document.getElementById('tab-' + tabName).classList.add('active');
        }
    </script>
</body>
</html>

