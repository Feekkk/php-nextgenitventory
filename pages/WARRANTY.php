<?php
session_start();
require_once '../database/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$pdo = getDBConnection();
$assetTypeParam = trim($_GET['asset_type'] ?? '');
$assetIdParam = trim($_GET['asset_id'] ?? '');
$allowedTypes = ['av', 'network'];
$error = '';
$message = '';
$asset = null;
$latestWarranty = null;
$assetStatus = '';

if ($assetTypeParam && $assetIdParam && ctype_digit($assetIdParam) && in_array($assetTypeParam, $allowedTypes, true)) {
    try {
        if ($assetTypeParam === 'network') {
            $stmt = $pdo->prepare("
                SELECT asset_id, serial, brand, model, status
                FROM net_assets
                WHERE asset_id = ?
            ");
        } else {
            $stmt = $pdo->prepare("
                SELECT asset_id, serial_num AS serial, brand, model, status
                FROM av_assets
                WHERE asset_id = ?
            ");
        }
        $stmt->execute([$assetIdParam]);
        $asset = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$asset) {
            $error = 'Asset not found.';
        } else {
            $assetStatus = strtoupper(trim($asset['status'] ?? ''));
            $wStmt = $pdo->prepare("
                SELECT * FROM warranty
                WHERE asset_type = :type AND asset_id = :id
                ORDER BY created_at DESC
                LIMIT 1
            ");
            $wStmt->execute([
                ':type' => $assetTypeParam,
                ':id' => (int)$assetIdParam
            ]);
            $latestWarranty = $wStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }
    } catch (PDOException $e) {
        $error = 'Unable to load asset.';
    }
} elseif ($assetTypeParam || $assetIdParam) {
    $error = 'Invalid asset parameters.';
}

function updateAssetStatusWarranty(PDO $pdo, string $type, int $assetId, string $status): void {
    if ($type === 'network') {
        $stmt = $pdo->prepare("UPDATE net_assets SET status = ? WHERE asset_id = ?");
    } else {
        $stmt = $pdo->prepare("UPDATE av_assets SET status = ? WHERE asset_id = ?");
    }
    $stmt->execute([$status, $assetId]);
}

$isWarrantyStatus = stripos($assetStatus, 'WARRANTY') === 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
    $mode = $_POST['mode'] ?? 'send';
    $postAssetType = trim($_POST['asset_type'] ?? '');
    $postAssetId = trim($_POST['asset_id'] ?? '');

    if (!in_array($postAssetType, $allowedTypes, true) || !ctype_digit($postAssetId)) {
        $error = 'Invalid asset data.';
    } else {
        if ($mode === 'send') {
            $sendDate = trim($_POST['send_date'] ?? '');
            $vendorName = trim($_POST['vendor_name'] ?? '');
            $remarks = trim($_POST['remarks'] ?? '');

            if ($sendDate === '') {
                $error = 'Send date is required.';
            } elseif ($vendorName === '') {
                $error = 'Vendor name is required.';
            } else {
                try {
                    $insert = $pdo->prepare("
                        INSERT INTO warranty (asset_type, asset_id, send_date, receive_date, vendor_name, remarks, created_by)
                        VALUES (:asset_type, :asset_id, :send_date, :receive_date, :vendor_name, :remarks, :created_by)
                    ");
                    $insert->execute([
                        ':asset_type' => $postAssetType,
                        ':asset_id' => (int)$postAssetId,
                        ':send_date' => $sendDate,
                        ':receive_date' => $sendDate, // temporary until receive is captured
                        ':vendor_name' => $vendorName,
                        ':remarks' => $remarks ?: null,
                        ':created_by' => $_SESSION['user_id'] ?? null
                    ]);

                    updateAssetStatusWarranty($pdo, $postAssetType, (int)$postAssetId, 'WARRANTY COVER');
                    $message = 'Warranty dispatched. Asset status set to WARRANTY COVER.';
                    $assetStatus = 'WARRANTY COVER';
                } catch (PDOException $e) {
                    $error = 'Unable to save warranty record.';
                }
            }
        } elseif ($mode === 'receive') {
            $receiveDate = trim($_POST['receive_date'] ?? '');
            if ($receiveDate === '') {
                $error = 'Receive date is required.';
            } elseif (!$latestWarranty) {
                $error = 'No warranty record found to update.';
            } else {
                try {
                    $upd = $pdo->prepare("UPDATE warranty SET receive_date = :receive_date WHERE warranty_id = :id");
                    $upd->execute([
                        ':receive_date' => $receiveDate,
                        ':id' => $latestWarranty['warranty_id']
                    ]);
                    updateAssetStatusWarranty($pdo, $postAssetType, (int)$postAssetId, 'OFFLINE');
                    $message = 'Warranty completed. Asset status set to OFFLINE.';
                    $assetStatus = 'OFFLINE';
                } catch (PDOException $e) {
                    $error = 'Unable to update warranty record.';
                }
            }
        }
    }
}

function formatAssetIdWarranty($id, $type) {
    if (!$id) return '';
    $prefix = $type === 'network' ? 'NET' : 'AV';
    return sprintf('%s-%05d', $prefix, $id);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Warranty - UniKL RCMP IT Inventory</title>
    <link rel="icon" type="image/png" href="../public/rcmp.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { margin: 0; font-family: 'Inter', sans-serif; background: #f5f7fb; color: #1f2937; }
        .page-container { max-width: 1000px; margin: 0 auto; padding: 110px 20px 60px; }
        .card { background: #ffffff; border: 1px solid rgba(0,0,0,0.05); border-radius: 16px; padding: 24px; box-shadow: 0 12px 30px rgba(0,0,0,0.06); }
        .card h1 { margin: 0 0 6px 0; font-size: 1.6rem; }
        .card p.lead { margin: 0 0 20px 0; color: #4b5563; }
        .alert { padding: 12px 14px; border-radius: 10px; margin-bottom: 16px; font-weight: 600; font-size: 0.95rem; }
        .alert-success { background: rgba(34,197,94,0.12); color: #15803d; border: 1px solid rgba(34,197,94,0.25); }
        .alert-error { background: rgba(239,68,68,0.12); color: #dc2626; border: 1px solid rgba(239,68,68,0.25); }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 16px; }
        .form-group { display: flex; flex-direction: column; gap: 6px; }
        .form-group label { font-weight: 600; font-size: 0.95rem; color: #111827; }
        .form-group input, .form-group select, .form-group textarea { padding: 12px; border: 1px solid rgba(0,0,0,0.1); border-radius: 10px; font-size: 0.95rem; font-family: 'Inter', sans-serif; transition: border 0.2s ease, box-shadow 0.2s ease; }
        .form-group textarea { min-height: 110px; resize: vertical; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: #1a1a2e; box-shadow: 0 0 0 3px rgba(26,26,46,0.12); }
        .form-group input[readonly], .form-group select[disabled] { background: #f3f4f6; cursor: not-allowed; }
        .actions { margin-top: 20px; display: flex; gap: 12px; }
        .btn { padding: 12px 18px; border: none; border-radius: 10px; font-weight: 700; cursor: pointer; font-size: 0.95rem; transition: all 0.2s ease; }
        .btn-primary { background: #1a1a2e; color: #ffffff; }
        .btn-primary:hover { background: #0f0f1a; box-shadow: 0 8px 18px rgba(26,26,46,0.2); }
        .btn-secondary { background: #f3f4f6; color: #111827; }
        .btn-secondary:hover { background: #e5e7eb; }
        .asset-summary { background: #f8fafc; border: 1px solid rgba(0,0,0,0.05); border-radius: 12px; padding: 12px 14px; margin-bottom: 16px; }
        .asset-summary strong { display: inline-block; width: 110px; color: #111827; }
    </style>
</head>
<body>
    <?php include_once("../components/HomeHeader.php"); ?>

    <div class="page-container">
        <div class="card">
            <h1>Warranty</h1>
            <p class="lead">Record warranty dispatch to vendor.</p>

            <?php if (!empty($error)) : ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if (!empty($message)) : ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <?php if ($asset) : ?>
                <div class="asset-summary">
                    <div><strong>Asset ID:</strong> <?php echo htmlspecialchars(formatAssetIdWarranty($asset['asset_id'], $assetTypeParam)); ?></div>
                    <div><strong>Type:</strong> <?php echo htmlspecialchars(strtoupper($assetTypeParam)); ?></div>
                    <div><strong>Brand / Model:</strong> <?php echo htmlspecialchars(trim(($asset['brand'] ?? '') . ' ' . ($asset['model'] ?? '')) ?: '-'); ?></div>
                    <div><strong>Serial:</strong> <?php echo htmlspecialchars($asset['serial'] ?? '-'); ?></div>
                    <div><strong>Status:</strong> <?php echo htmlspecialchars($assetStatus ?: ($asset['status'] ?? '-')); ?></div>
                    <?php if ($latestWarranty) : ?>
                        <div><strong>Last Send Date:</strong> <?php echo htmlspecialchars($latestWarranty['send_date']); ?></div>
                        <div><strong>Vendor:</strong> <?php echo htmlspecialchars($latestWarranty['vendor_name']); ?></div>
                        <div><strong>Remarks:</strong> <?php echo htmlspecialchars($latestWarranty['remarks'] ?? '-'); ?></div>
                        <?php if (!empty($latestWarranty['receive_date'])) : ?>
                            <div><strong>Receive Date:</strong> <?php echo htmlspecialchars($latestWarranty['receive_date']); ?></div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <?php if (empty($message)) : ?>
                    <?php if (!$isWarrantyStatus) : ?>
                        <form method="POST">
                            <input type="hidden" name="mode" value="send">
                            <input type="hidden" name="asset_id" value="<?php echo htmlspecialchars($assetIdParam); ?>">
                            <input type="hidden" name="asset_type" value="<?php echo htmlspecialchars($assetTypeParam); ?>">

                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="asset_id_display">Asset ID</label>
                                    <input id="asset_id_display" type="text" value="<?php echo htmlspecialchars(formatAssetIdWarranty($assetIdParam, $assetTypeParam)); ?>" readonly>
                                </div>
                                <div class="form-group">
                                    <label for="asset_type_display">Asset Type</label>
                                    <select id="asset_type_display" disabled>
                                        <option value="network" <?php echo $assetTypeParam === 'network' ? 'selected' : ''; ?>>Network</option>
                                        <option value="av" <?php echo $assetTypeParam === 'av' ? 'selected' : ''; ?>>AV</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="send_date">Send Date <span style="color:#dc2626;">*</span></label>
                                    <input type="date" id="send_date" name="send_date" required>
                                </div>
                                <div class="form-group">
                                    <label for="vendor_name">Vendor Name <span style="color:#dc2626;">*</span></label>
                                    <input type="text" id="vendor_name" name="vendor_name" placeholder="Enter vendor name" required>
                                </div>
                                <div class="form-group" style="grid-column: 1 / -1;">
                                    <label for="remarks">Remarks</label>
                                    <textarea id="remarks" name="remarks" placeholder="Notes about the warranty process..."></textarea>
                                </div>
                            </div>

                            <div class="actions">
                                <button type="button" class="btn btn-secondary" onclick="window.history.back();">Cancel</button>
                                <button type="submit" class="btn btn-primary">Save Warranty</button>
                            </div>
                        </form>
                    <?php else : ?>
                        <form method="POST">
                            <input type="hidden" name="mode" value="receive">
                            <input type="hidden" name="asset_id" value="<?php echo htmlspecialchars($assetIdParam); ?>">
                            <input type="hidden" name="asset_type" value="<?php echo htmlspecialchars($assetTypeParam); ?>">

                            <div class="form-grid">
                                <div class="form-group">
                                    <label>Send Date</label>
                                    <input type="date" value="<?php echo htmlspecialchars($latestWarranty['send_date'] ?? ''); ?>" readonly>
                                </div>
                                <div class="form-group">
                                    <label>Vendor Name</label>
                                    <input type="text" value="<?php echo htmlspecialchars($latestWarranty['vendor_name'] ?? ''); ?>" readonly>
                                </div>
                                <div class="form-group" style="grid-column: 1 / -1;">
                                    <label>Remarks</label>
                                    <textarea readonly><?php echo htmlspecialchars($latestWarranty['remarks'] ?? ''); ?></textarea>
                                </div>
                                <div class="form-group">
                                    <label for="receive_date">Receive Date <span style="color:#dc2626;">*</span></label>
                                    <input type="date" id="receive_date" name="receive_date" required>
                                </div>
                            </div>

                            <div class="actions">
                                <button type="button" class="btn btn-secondary" onclick="window.history.back();">Cancel</button>
                                <button type="submit" class="btn btn-primary">Save Receive</button>
                            </div>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
            <?php elseif (empty($error)) : ?>
                <div class="alert alert-error">Asset data is required.</div>
            <?php endif; ?>
        </div>
    </div>

    <footer>
        <?php include_once("../components/Footer.php"); ?>
    </footer>
</body>
</html>
