<?php
session_start();
require_once '../database/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$pdo = getDBConnection();
$assetIdParam = trim($_GET['asset_id'] ?? '');
$assetTypeParam = trim($_GET['asset_type'] ?? '');
$message = '';
$error = '';
$assetDetails = null;
$techName = '';

// Get technician name
try {
    $techStmt = $pdo->prepare("SELECT tech_name FROM technician WHERE id = ?");
    $techStmt->execute([$_SESSION['user_id']]);
    $techResult = $techStmt->fetch(PDO::FETCH_ASSOC);
    $techName = $techResult['tech_name'] ?? $_SESSION['full_name'] ?? '';
} catch (PDOException $e) {
    error_log('Error fetching technician name: ' . $e->getMessage());
}

// Fetch asset details
if ($assetIdParam && $assetTypeParam && is_numeric($assetIdParam)) {
    try {
        if ($assetTypeParam === 'laptop_desktop') {
            $stmt = $pdo->prepare("
                SELECT asset_id, serial_num, brand, model, category, status
                FROM laptop_desktop_assets 
                WHERE asset_id = ?
            ");
        } elseif ($assetTypeParam === 'av') {
            $stmt = $pdo->prepare("
                SELECT asset_id, serial_num, brand, model, class as category, status
                FROM av_assets 
                WHERE asset_id = ?
            ");
        } elseif ($assetTypeParam === 'network') {
            $stmt = $pdo->prepare("
                SELECT asset_id, serial, brand, model, status
                FROM net_assets 
                WHERE asset_id = ?
            ");
        } else {
            $stmt = null;
        }
        
        if ($stmt) {
            $stmt->execute([$assetIdParam]);
            $assetDetails = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$assetDetails) {
                $error = 'Asset not found.';
            }
        }
    } catch (PDOException $e) {
        error_log('Error fetching asset: ' . $e->getMessage());
        $error = 'Unable to load asset details.';
    }
} elseif ($assetIdParam || $assetTypeParam) {
    $error = 'Invalid asset ID or type.';
}

// Format asset ID
function formatAssetId($id, $type) {
    if (!$id) return '';
    $prefix = '';
    if ($type === 'laptop_desktop') $prefix = 'LAP';
    elseif ($type === 'av') $prefix = 'AV';
    elseif ($type === 'network') $prefix = 'NET';
    return sprintf('%s-%05d', $prefix, $id);
}

$formattedAssetId = formatAssetId($assetIdParam, $assetTypeParam);
$serialNum = '';
$brandModel = '';

if ($assetDetails) {
    if ($assetTypeParam === 'network') {
        $serialNum = $assetDetails['serial'] ?? '';
    } else {
        $serialNum = $assetDetails['serial_num'] ?? '';
    }
    $brand = trim($assetDetails['brand'] ?? '');
    $model = trim($assetDetails['model'] ?? '');
    $brandModel = trim($brand . ' ' . $model);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Placeholder: persist logic can be added later
    $message = 'Repair request captured (not yet stored).';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Repair Form - UniKL RCMP IT Inventory</title>
    <link rel="icon" type="image/png" href="../public/rcmp.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            margin: 0;
            font-family: 'Inter', sans-serif;
            background: #f5f7fb;
            color: #1f2937;
        }
        .page-container {
            max-width: 1100px;
            margin: 0 auto;
            padding: 30px 20px 60px;
        }
        .card {
            background: #ffffff;
            border: 1px solid rgba(0, 0, 0, 0.05);
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.06);
        }
        .card h1 {
            margin: 0 0 8px 0;
            font-size: 1.6rem;
        }
        .card p.lead {
            margin: 0 0 20px 0;
            color: #4b5563;
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 16px;
        }
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .form-group label {
            font-weight: 600;
            font-size: 0.95rem;
            color: #111827;
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 12px;
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 10px;
            font-size: 0.95rem;
            font-family: 'Inter', sans-serif;
            transition: border 0.2s ease, box-shadow 0.2s ease;
        }
        .form-group textarea {
            min-height: 110px;
            resize: vertical;
        }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #1a1a2e;
            box-shadow: 0 0 0 3px rgba(26, 26, 46, 0.12);
        }
        .form-group.full {
            grid-column: 1 / -1;
        }
        .actions {
            margin-top: 20px;
            display: flex;
            gap: 12px;
        }
        .btn {
            padding: 12px 18px;
            border: none;
            border-radius: 10px;
            font-weight: 700;
            cursor: pointer;
            font-size: 0.95rem;
            transition: all 0.2s ease;
        }
        .btn-primary {
            background: #1a1a2e;
            color: #ffffff;
        }
        .btn-primary:hover {
            background: #0f0f1a;
            box-shadow: 0 8px 18px rgba(26, 26, 46, 0.2);
        }
        .btn-secondary {
            background: #f3f4f6;
            color: #111827;
        }
        .btn-secondary:hover {
            background: #e5e7eb;
        }
        .alert {
            padding: 12px 14px;
            border-radius: 10px;
            margin-bottom: 16px;
            font-weight: 600;
            font-size: 0.95rem;
        }
        .alert-success {
            background: rgba(34, 197, 94, 0.12);
            color: #15803d;
            border: 1px solid rgba(34, 197, 94, 0.25);
        }
        .alert-error {
            background: rgba(239, 68, 68, 0.12);
            color: #dc2626;
            border: 1px solid rgba(239, 68, 68, 0.25);
        }
        .form-group input[readonly],
        .form-group select[disabled] {
            background: #f3f4f6;
            cursor: not-allowed;
        }
    </style>
</head>
<body>
    <?php include_once("../components/HomeHeader.php"); ?>

    <div class="page-container">
        <div class="card">
            <h1>Repair / Faulty Intake</h1>
            <p class="lead">Capture repair details for a faulty asset.</p>

            <?php if (!empty($error)) : ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if (!empty($message)) : ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <?php if (empty($error)) : ?>
            <form method="POST">
                <input type="hidden" name="asset_id" value="<?php echo htmlspecialchars($assetIdParam); ?>">
                <input type="hidden" name="asset_type" value="<?php echo htmlspecialchars($assetTypeParam); ?>">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="asset_id">Asset ID</label>
                        <input type="text" id="asset_id" value="<?php echo htmlspecialchars($formattedAssetId); ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label for="asset_type">Asset Type</label>
                        <select id="asset_type" disabled>
                            <option value="">Select type</option>
                            <option value="laptop_desktop" <?php echo $assetTypeParam === 'laptop_desktop' ? 'selected' : ''; ?>>Laptop / Desktop</option>
                            <option value="av" <?php echo $assetTypeParam === 'av' ? 'selected' : ''; ?>>AV</option>
                            <option value="network" <?php echo $assetTypeParam === 'network' ? 'selected' : ''; ?>>Network</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="serial">Serial Number</label>
                        <input type="text" id="serial" name="serial" value="<?php echo htmlspecialchars($serialNum); ?>" placeholder="Serial number" readonly>
                    </div>
                    <div class="form-group">
                        <label for="brand_model">Brand / Model</label>
                        <input type="text" id="brand_model" name="brand_model" value="<?php echo htmlspecialchars($brandModel); ?>" placeholder="Brand and model" readonly>
                    </div>
                    <div class="form-group">
                        <label for="severity">Severity</label>
                        <select id="severity" name="severity" required>
                            <option value="">Select severity</option>
                            <option value="Low">Low</option>
                            <option value="Medium">Medium</option>
                            <option value="High">High</option>
                            <option value="Critical">Critical</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="reported_by">Reported By</label>
                        <input type="text" id="reported_by" name="reported_by" value="<?php echo htmlspecialchars($techName); ?>" placeholder="Technician or requester name" required>
                    </div>
                    <div class="form-group full">
                        <label for="issue">Issue Description <span style="color: #dc2626;">*</span></label>
                        <textarea id="issue" name="issue" placeholder="Describe the fault or issue..." required></textarea>
                    </div>
                    <div class="form-group full">
                        <label for="actions">Actions Performed</label>
                        <textarea id="actions" name="actions" placeholder="Troubleshooting steps or repairs performed..."></textarea>
                    </div>
                    <div class="form-group">
                        <label for="parts_used">Parts Used</label>
                        <input type="text" id="parts_used" name="parts_used" placeholder="List replacement parts, if any">
                    </div>
                    <div class="form-group">
                        <label for="cost">Estimated Cost (RM)</label>
                        <input type="number" step="0.01" id="cost" name="cost" placeholder="0.00">
                    </div>
                    <div class="form-group">
                        <label for="vendor">Vendor / PIC</label>
                        <input type="text" id="vendor" name="vendor" placeholder="Vendor or person in charge">
                    </div>
                    <div class="form-group">
                        <label for="status">Repair Status</label>
                        <select id="status" name="status">
                            <option value="">Select status</option>
                            <option value="In Repair">In Repair</option>
                            <option value="Completed">Completed</option>
                            <option value="On Hold">On Hold</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="return_target">Target / Return Date</label>
                        <input type="date" id="return_target" name="return_target">
                    </div>
                    <div class="form-group full">
                        <label for="remarks">Remarks</label>
                        <textarea id="remarks" name="remarks" placeholder="Additional notes..."></textarea>
                    </div>
                </div>

                <div class="actions">
                    <button type="button" class="btn btn-secondary" onclick="window.history.back();">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Repair</button>
                </div>
            </form>
            <?php else : ?>
                <div class="actions">
                    <button type="button" class="btn btn-secondary" onclick="window.history.back();">Go Back</button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <footer>
        <?php include_once("../components/Footer.php"); ?>
    </footer>
</body>
</html>

