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
            padding: 110px 20px 80px;
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
            <a href="../technician/NETpage.php" class="btn-back">
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
                            <div class="detail-value <?php echo empty($asset['P.O_DATE']) ? 'empty' : ''; ?>">
                                <?php echo formatDate($asset['P.O_DATE']); ?>
                            </div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">P.O. Number</div>
                            <div class="detail-value <?php echo empty($asset['P.O_NUM']) ? 'empty' : ''; ?>">
                                <?php echo htmlspecialchars($asset['P.O_NUM'] ?: '-'); ?>
                            </div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">D.O. Date</div>
                            <div class="detail-value <?php echo empty($asset['D.O_DATE']) ? 'empty' : ''; ?>">
                                <?php echo htmlspecialchars($asset['D.O_DATE'] ?: '-'); ?>
                            </div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">D.O. Number</div>
                            <div class="detail-value <?php echo empty($asset['D.O_NUM']) ? 'empty' : ''; ?>">
                                <?php echo htmlspecialchars($asset['D.O_NUM'] ?: '-'); ?>
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
        <?php endif; ?>
    </div>

    <footer>
        <?php include_once("../components/Footer.php"); ?>
    </footer>
</body>
</html>

