<?php
session_start();
require_once '../database/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$pdo = getDBConnection();
$errors = [];
$successMessage = '';
$allowedStatuses = ['DEPLOY', 'FAULTY', 'DISPOSE', 'RESERVED', 'MAINTENANCE', 'NON-ACTIVE', 'LOST', 'ACTIVE'];
$staffName = '';

function generateAssetId($pdo, $categoryCode) {
    $year = date('y');
    $prefix = $categoryCode . $year;
    
    $stmt = $pdo->prepare("SELECT asset_id FROM laptop_desktop_assets WHERE asset_id LIKE ? ORDER BY asset_id DESC LIMIT 1");
    $stmt->execute([$prefix . '%']);
    $lastId = $stmt->fetchColumn();
    
    if ($lastId) {
        $lastSequence = (int)substr($lastId, -3);
        $nextSequence = $lastSequence + 1;
    } else {
        $nextSequence = 1;
    }
    
    $newId = (int)($prefix . str_pad($nextSequence, 3, '0', STR_PAD_LEFT));
    
    $maxAttempts = 100;
    $attempts = 0;
    while ($attempts < $maxAttempts) {
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM laptop_desktop_assets WHERE asset_id = ?");
        $checkStmt->execute([$newId]);
        if ($checkStmt->fetchColumn() == 0) {
            return $newId;
        }
        $nextSequence++;
        $newId = (int)($prefix . str_pad($nextSequence, 3, '0', STR_PAD_LEFT));
        $attempts++;
    }
    
    throw new Exception('Unable to generate unique asset ID after multiple attempts');
}

if (isset($_GET['staff_id']) && is_numeric($_GET['staff_id'])) {
    $stmt = $pdo->prepare("SELECT staff_name FROM staff_list WHERE staff_id = ?");
    $stmt->execute([$_GET['staff_id']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    header('Content-Type: application/json');
    echo json_encode(['staff_name' => $result ? $result['staff_name'] : '']);
    exit;
}
$formData = [
    'asset_id' => '',
    'serial_num' => '',
    'brand' => '',
    'model' => '',
    'category' => '',
    'status' => '',
    'staff_id' => '',
    'assignment_type' => '',
    'location' => '',
    'processor' => '',
    'memory' => '',
    'os' => '',
    'storage' => '',
    'gpu' => '',
    'warranty_expiry' => '',
    'part_number' => '',
    'supplier' => '',
    'period' => '',
    'activity_log' => '',
    'PO_DATE' => '',
    'PO_NUM' => '',
    'DO_DATE' => '',
    'DO_NUM' => '',
    'INVOICE_DATE' => '',
    'INVOICE_NUM' => '',
    'PURCHASE_COST' => '',
    'remarks' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (array_keys($formData) as $field) {
        $formData[$field] = trim($_POST[$field] ?? '');
    }

    if ($formData['serial_num'] === '') {
        $errors[] = 'Serial number is required.';
    }

    if ($formData['brand'] === '') {
        $errors[] = 'Brand is required.';
    }

    if ($formData['model'] === '') {
        $errors[] = 'Model is required.';
    }

    if ($formData['status'] === '' || !in_array($formData['status'], $allowedStatuses, true)) {
        $errors[] = 'Select a valid status.';
    }

    if ($formData['status'] === 'DEPLOY' && ($formData['staff_id'] === '' || !is_numeric($formData['staff_id']))) {
        $errors[] = 'Staff ID is required when status is DEPLOY.';
    }

    if ($formData['PO_DATE'] === '') {
        $errors[] = 'P.O. Date is required.';
    }

    if ($formData['PO_NUM'] === '') {
        $errors[] = 'P.O. Number is required.';
    }

    if ($formData['DO_DATE'] === '') {
        $errors[] = 'D.O. Date is required.';
    }

    if ($formData['DO_NUM'] === '') {
        $errors[] = 'D.O. Number is required.';
    }

    if ($formData['INVOICE_DATE'] === '') {
        $errors[] = 'Invoice Date is required.';
    }

    if ($formData['INVOICE_NUM'] === '') {
        $errors[] = 'Invoice Number is required.';
    }

    if ($formData['PURCHASE_COST'] === '') {
        $errors[] = 'Purchase Cost is required.';
    } elseif (!is_numeric($formData['PURCHASE_COST'])) {
        $errors[] = 'Purchase cost must be a valid number.';
    }

    if ($formData['staff_id'] !== '' && !is_numeric($formData['staff_id'])) {
        $errors[] = 'Staff ID must be a valid number.';
    }

    if ($formData['asset_id'] !== '' && !is_numeric($formData['asset_id'])) {
        $errors[] = 'Asset ID must be a valid number.';
    }

    if ($formData['asset_id'] !== '' && is_numeric($formData['asset_id'])) {
        $stmt = $pdo->prepare("SELECT asset_id FROM laptop_desktop_assets WHERE asset_id = ?");
        $stmt->execute([$formData['asset_id']]);
        if ($stmt->fetch()) {
            $errors[] = 'Asset ID already exists. Please use a different Asset ID or leave it empty for auto-generation.';
        }
    }

    if ($formData['staff_id'] !== '' && is_numeric($formData['staff_id'])) {
        $stmt = $pdo->prepare("SELECT staff_id FROM staff_list WHERE staff_id = ?");
        $stmt->execute([$formData['staff_id']]);
        if (!$stmt->fetch()) {
            $errors[] = 'Staff ID does not exist in the system. Please enter a valid Staff ID.';
        }
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO laptop_desktop_assets (
                    asset_id, serial_num, brand, model, category, status, staff_id,
                    assignment_type, location, processor, memory, os, storage, gpu, warranty_expiry, part_number,
                    supplier, period, activity_log, `PO_DATE`, `PO_NUM`, `DO_DATE`, `DO_NUM`,
                    `INVOICE_DATE`, `INVOICE_NUM`, `PURCHASE_COST`, remarks
                ) VALUES (
                    :asset_id, :serial_num, :brand, :model, :category, :status, :staff_id,
                    :assignment_type, :location, :processor, :memory, :os, :storage, :gpu, :warranty_expiry, :part_number,
                    :supplier, :period, :activity_log, :po_date, :po_num, :do_date, :do_num,
                    :invoice_date, :invoice_num, :purchase_cost, :remarks
                )
            ");

            $assetId = $formData['asset_id'] !== '' ? (int)$formData['asset_id'] : generateAssetId($pdo, 11);
            $poDate = $formData['PO_DATE'];
            $doDate = $formData['DO_DATE'];
            $invoiceDate = $formData['INVOICE_DATE'];
            $warrantyExpiry = $formData['warranty_expiry'] !== '' ? $formData['warranty_expiry'] : null;
            $purchaseCost = (float)$formData['PURCHASE_COST'];
            $staffId = $formData['staff_id'] !== '' ? (int)$formData['staff_id'] : null;

            $stmt->execute([
                ':asset_id' => $assetId,
                ':serial_num' => $formData['serial_num'],
                ':brand' => $formData['brand'],
                ':model' => $formData['model'],
                ':category' => $formData['category'] ?: null,
                ':status' => $formData['status'],
                ':staff_id' => $staffId,
                ':assignment_type' => $formData['assignment_type'] ?: null,
                ':location' => $formData['location'] ?: null,
                ':processor' => $formData['processor'] ?: null,
                ':memory' => $formData['memory'] ?: null,
                ':os' => $formData['os'] ?: null,
                ':storage' => $formData['storage'] ?: null,
                ':gpu' => $formData['gpu'] ?: null,
                ':warranty_expiry' => $warrantyExpiry,
                ':part_number' => $formData['part_number'] ?: null,
                ':supplier' => $formData['supplier'] ?: null,
                ':period' => $formData['period'] ?: null,
                ':activity_log' => $formData['activity_log'] ?: null,
                ':po_date' => $poDate,
                ':po_num' => $formData['PO_NUM'],
                ':do_date' => $doDate,
                ':do_num' => $formData['DO_NUM'],
                ':invoice_date' => $invoiceDate,
                ':invoice_num' => $formData['INVOICE_NUM'],
                ':purchase_cost' => $purchaseCost,
                ':remarks' => $formData['remarks'] ?: null,
            ]);

            // Asset trail: record asset creation
            try {
                $trailStmt = $pdo->prepare("
                    INSERT INTO asset_trails (
                        asset_type, asset_id, action_type, changed_by,
                        field_name, old_value, new_value, description,
                        ip_address, user_agent
                    ) VALUES (
                        'laptop_desktop', :asset_id, 'CREATE', :changed_by,
                        NULL, NULL, NULL, :description,
                        :ip_address, :user_agent
                    )
                ");
                $trailStmt->execute([
                    ':asset_id' => $assetId,
                    ':changed_by' => $_SESSION['user_id'] ?? null,
                    ':description' => 'Created laptop/desktop asset with serial ' . $formData['serial_num'],
                    ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                    ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                ]);
            } catch (PDOException $e) {
                error_log('Failed to write laptop asset trail: ' . $e->getMessage());
            }

            if ($formData['status'] === 'DEPLOY' && $staffId !== null) {
                try {
                    $handoverStmt = $pdo->prepare("
                        INSERT INTO handover (
                            staff_id, asset_type, asset_id, handover_date,
                            handover_location, status, created_by
                        ) VALUES (
                            :staff_id, 'laptop_desktop', :asset_id, :handover_date,
                            :handover_location, 'active', :created_by
                        )
                    ");
                    $handoverStmt->execute([
                        ':staff_id' => $staffId,
                        ':asset_id' => $assetId,
                        ':handover_date' => date('Y-m-d'),
                        ':handover_location' => $formData['location'] ?: null,
                        ':created_by' => $_SESSION['user_id'] ?? null,
                    ]);
                } catch (PDOException $e) {
                    error_log('Failed to create handover record: ' . $e->getMessage());
                }
            }

            $successMessage = 'Laptop/Desktop asset saved successfully.';
            foreach (array_keys($formData) as $field) {
                $formData[$field] = '';
            }
            $staffName = '';
        } catch (PDOException $e) {
            error_log('LAPTOPadd.php INSERT Error: ' . $e->getMessage());
            if ($e->getCode() == 23000 || strpos($e->getMessage(), 'Duplicate entry') !== false || strpos($e->getMessage(), 'PRIMARY') !== false) {
                $errors[] = 'Asset ID already exists. Please use a different Asset ID or leave it empty for auto-generation.';
            } else {
                $errors[] = 'Unable to save asset right now. Please try again.';
            }
        }
    }
}

if (!empty($formData['staff_id']) && is_numeric($formData['staff_id'])) {
    $stmt = $pdo->prepare("SELECT staff_name FROM staff_list WHERE staff_id = ?");
    $stmt->execute([$formData['staff_id']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        $staffName = $result['staff_name'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Laptop/Desktop Asset - UniKL RCMP IT Inventory</title>
    <link rel="icon" type="image/png" href="../public/rcmp.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/TechDashboard.css">
    <style>
        .form-page-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px 80px;
        }

        .page-header {
            margin-bottom: 30px;
        }

        .page-header h1 {
            font-size: 2rem;
            font-weight: 700;
            color: #000000;
            margin-bottom: 10px;
        }

        .page-header p {
            color: #636e72;
            font-size: 1rem;
        }

        .asset-form {
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(0, 0, 0, 0.05);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.05);
        }

        .form-section {
            margin-bottom: 30px;
        }

        .form-section-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #1a1a2e;
            margin-bottom: 15px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-group label {
            font-size: 0.95rem;
            font-weight: 500;
            color: #2d3436;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 12px 14px;
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 10px;
            font-size: 0.95rem;
            transition: all 0.2s ease;
            font-family: 'Inter', sans-serif;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #1a1a2e;
            box-shadow: 0 0 0 3px rgba(26, 26, 46, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 120px;
        }

        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            margin-top: 10px;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 10px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 0.95rem;
        }

        .btn-primary {
            background: #1a1a2e;
            color: #ffffff;
        }

        .btn-primary:hover {
            background: #0f0f1a;
            box-shadow: 0 8px 15px rgba(26, 26, 46, 0.25);
        }

        .btn-secondary {
            background: #f1f2f6;
            color: #2d3436;
        }

        .btn-secondary:hover {
            background: #e3e6ed;
        }

        .alert {
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 20px;
            font-size: 0.95rem;
        }

        .alert ul {
            margin: 0;
            padding-left: 20px;
        }

        .alert-error {
            background: rgba(192, 57, 43, 0.1);
            border: 1px solid rgba(192, 57, 43, 0.2);
            color: #c0392b;
        }

        .alert-success {
            background: rgba(39, 174, 96, 0.1);
            border: 1px solid rgba(39, 174, 96, 0.2);
            color: #27ae60;
        }

        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-actions {
                flex-direction: column;
            }

            .form-actions .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <?php include_once("../components/HomeHeader.php"); ?>

    <div class="form-page-container">
        <div class="page-header">
            <h1>Add Laptop/Desktop Asset</h1>
            <p>Register new laptops or desktops, track their assignment, and document key details.</p>
        </div>

        <?php if (!empty($errors)) : ?>
            <div class="alert alert-error">
                <ul>
                    <?php foreach ($errors as $error) : ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php elseif ($successMessage) : ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($successMessage); ?>
            </div>
        <?php endif; ?>

        <form class="asset-form" method="POST" autocomplete="off">
            <div class="form-section">
                <h3 class="form-section-title">Asset Information</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="asset_id">Asset ID</label>
                        <input type="number" id="asset_id" name="asset_id" placeholder="Auto-generated if left empty" value="<?php echo htmlspecialchars($formData['asset_id']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="serial_num">Serial Number <span style="color:#c0392b;">*</span></label>
                        <input type="text" id="serial_num" name="serial_num" placeholder="Enter serial number" value="<?php echo htmlspecialchars($formData['serial_num']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="brand">Brand <span style="color:#c0392b;">*</span></label>
                        <input type="text" id="brand" name="brand" placeholder="e.g., Dell" value="<?php echo htmlspecialchars($formData['brand']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="model">Model <span style="color:#c0392b;">*</span></label>
                        <input type="text" id="model" name="model" placeholder="e.g., Latitude 7420" value="<?php echo htmlspecialchars($formData['model']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="category">Category</label>
                        <select id="category" name="category">
                            <option value="">Select category</option>
                            <option value="DESKTOP AIO" <?php echo $formData['category'] === 'DESKTOP AIO' ? 'selected' : ''; ?>>DESKTOP AIO</option>
                            <option value="DESKTOP AIO-SHARING" <?php echo $formData['category'] === 'DESKTOP AIO-SHARING' ? 'selected' : ''; ?>>DESKTOP AIO-SHARING</option>
                            <option value="NOTEBOOK" <?php echo $formData['category'] === 'NOTEBOOK' ? 'selected' : ''; ?>>NOTEBOOK</option>
                            <option value="NOTEBOOK-STANDBY" <?php echo $formData['category'] === 'NOTEBOOK-STANDBY' ? 'selected' : ''; ?>>NOTEBOOK-STANDBY</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="part_number">Part Number</label>
                        <input type="text" id="part_number" name="part_number" placeholder="Enter part number" value="<?php echo htmlspecialchars($formData['part_number']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="status">Status <span style="color:#c0392b;">*</span></label>
                        <select id="status" name="status" required>
                            <option value="">Select status</option>
                            <?php foreach ($allowedStatuses as $status) : ?>
                                <option value="<?php echo htmlspecialchars($status); ?>" <?php echo $formData['status'] === $status ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($status); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3 class="form-section-title">Hardware Specifications</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="processor">Processor</label>
                        <input type="text" id="processor" name="processor" placeholder="e.g., Intel Core i7-1185G7" value="<?php echo htmlspecialchars($formData['processor']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="memory">Memory</label>
                        <input type="text" id="memory" name="memory" placeholder="e.g., 16GB DDR4" value="<?php echo htmlspecialchars($formData['memory']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="storage">Storage</label>
                        <input type="text" id="storage" name="storage" placeholder="e.g., 512GB NVMe SSD" value="<?php echo htmlspecialchars($formData['storage']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="gpu">GPU</label>
                        <input type="text" id="gpu" name="gpu" placeholder="e.g., Intel Iris Xe" value="<?php echo htmlspecialchars($formData['gpu']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="os">Operating System</label>
                        <input type="text" id="os" name="os" placeholder="e.g., Windows 11 Pro" value="<?php echo htmlspecialchars($formData['os']); ?>">
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3 class="form-section-title">Deployment Details</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="staff_id">Staff ID</label>
                        <input type="number" id="staff_id" name="staff_id" placeholder="Enter staff ID" value="<?php echo htmlspecialchars($formData['staff_id']); ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label for="staff_name">Staff Name</label>
                        <input type="text" id="staff_name" name="staff_name" placeholder="Auto-filled from Staff ID" value="<?php echo htmlspecialchars($staffName); ?>" readonly style="background-color: #f5f5f5; cursor: not-allowed;" disabled>
                    </div>
                    <div class="form-group">
                        <label for="assignment_type">Assignment Type</label>
                        <select id="assignment_type" name="assignment_type" disabled>
                            <option value="">Select assignment type</option>
                            <option value="ACADEMIC" <?php echo $formData['assignment_type'] === 'ACADEMIC' ? 'selected' : ''; ?>>ACADEMIC</option>
                            <option value="SERVICES" <?php echo $formData['assignment_type'] === 'SERVICES' ? 'selected' : ''; ?>>SERVICES</option>
                            <option value="FACILITIES" <?php echo $formData['assignment_type'] === 'FACILITIES' ? 'selected' : ''; ?>>FACILITIES</option>
                            <option value="LOST" <?php echo $formData['assignment_type'] === 'LOST' ? 'selected' : ''; ?>>LOST</option>
                            <option value="OTHERS" <?php echo $formData['assignment_type'] === 'OTHERS' ? 'selected' : ''; ?>>OTHERS</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="location">Location</label>
                        <input type="text" id="location" name="location" placeholder="e.g., Building A, Level 2" value="<?php echo htmlspecialchars($formData['location']); ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label for="warranty_expiry">Warranty Expiry</label>
                        <input type="date" id="warranty_expiry" name="warranty_expiry" value="<?php echo htmlspecialchars($formData['warranty_expiry']); ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label for="supplier">Supplier</label>
                        <input type="text" id="supplier" name="supplier" placeholder="Enter supplier name" value="<?php echo htmlspecialchars($formData['supplier']); ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label for="period">Period</label>
                        <input type="text" id="period" name="period" placeholder="e.g., 2024-2025" value="<?php echo htmlspecialchars($formData['period']); ?>">
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3 class="form-section-title">Purchase Information <span style="color:#c0392b; font-size: 0.9rem; font-weight: 400;">(All fields required)</span></h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="PO_DATE">P.O. Date <span style="color:#c0392b;">*</span></label>
                        <input type="date" id="PO_DATE" name="PO_DATE" value="<?php echo htmlspecialchars($formData['PO_DATE']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="PO_NUM">P.O. Number <span style="color:#c0392b;">*</span></label>
                        <input type="text" id="PO_NUM" name="PO_NUM" placeholder="Enter P.O. number" value="<?php echo htmlspecialchars($formData['PO_NUM']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="DO_DATE">D.O. Date <span style="color:#c0392b;">*</span></label>
                        <input type="date" id="DO_DATE" name="DO_DATE" value="<?php echo htmlspecialchars($formData['DO_DATE']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="DO_NUM">D.O. Number <span style="color:#c0392b;">*</span></label>
                        <input type="text" id="DO_NUM" name="DO_NUM" placeholder="Enter D.O. number" value="<?php echo htmlspecialchars($formData['DO_NUM']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="INVOICE_DATE">Invoice Date <span style="color:#c0392b;">*</span></label>
                        <input type="date" id="INVOICE_DATE" name="INVOICE_DATE" value="<?php echo htmlspecialchars($formData['INVOICE_DATE']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="INVOICE_NUM">Invoice Number <span style="color:#c0392b;">*</span></label>
                        <input type="text" id="INVOICE_NUM" name="INVOICE_NUM" placeholder="Enter invoice number" value="<?php echo htmlspecialchars($formData['INVOICE_NUM']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="PURCHASE_COST">Purchase Cost (MYR) <span style="color:#c0392b;">*</span></label>
                        <input type="number" step="0.01" id="PURCHASE_COST" name="PURCHASE_COST" placeholder="Enter purchase cost" value="<?php echo htmlspecialchars($formData['PURCHASE_COST']); ?>" required>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3 class="form-section-title">Additional Information</h3>
                <div class="form-grid">
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label for="activity_log">Activity Log</label>
                        <textarea id="activity_log" name="activity_log" placeholder="Enter activity log entries"><?php echo htmlspecialchars($formData['activity_log']); ?></textarea>
                    </div>
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label for="remarks">Remarks</label>
                        <textarea id="remarks" name="remarks" placeholder="Add any additional notes or remarks"><?php echo htmlspecialchars($formData['remarks']); ?></textarea>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="window.history.back()">Cancel</button>
                <button type="submit" class="btn btn-primary" onclick="event.preventDefault(); this.form.submit(); setTimeout(function(){ window.location.href = 'LAPTOPpage.php'; }, 100);">Save Asset</button>
            </div>
        </form>
    </div>

    <footer>
        <?php include_once("../components/Footer.php"); ?>
    </footer>

    <script>
        function toggleDeploymentFields() {
            const status = document.getElementById('status').value;
            const isDeploy = status === 'DEPLOY';
            
            const deploymentFields = [
                'staff_id',
                'staff_name',
                'assignment_type',
                'location',
                'warranty_expiry',
                'supplier'
            ];
            
            deploymentFields.forEach(fieldId => {
                const field = document.getElementById(fieldId);
                if (field) {
                    field.disabled = !isDeploy;
                    if (fieldId === 'staff_id') {
                        if (isDeploy) {
                            field.setAttribute('required', 'required');
                        } else {
                            field.removeAttribute('required');
                        }
                    }
                    if (field.type === 'text' || field.type === 'number' || field.type === 'date') {
                        field.style.backgroundColor = isDeploy ? '' : '#f5f5f5';
                        field.style.cursor = isDeploy ? '' : 'not-allowed';
                    }
                    if (field.tagName === 'SELECT') {
                        field.style.backgroundColor = isDeploy ? '' : '#f5f5f5';
                        field.style.cursor = isDeploy ? '' : 'not-allowed';
                    }
                }
            });
        }
        
        document.getElementById('status').addEventListener('change', toggleDeploymentFields);
        toggleDeploymentFields();
        
        document.getElementById('staff_id').addEventListener('input', function() {
            const staffId = this.value.trim();
            const staffNameField = document.getElementById('staff_name');
            
            if (staffId === '') {
                staffNameField.value = '';
                return;
            }
            
            if (!/^\d+$/.test(staffId)) {
                return;
            }
            
            fetch('?staff_id=' + encodeURIComponent(staffId))
                .then(response => response.json())
                .then(data => {
                    staffNameField.value = data.staff_name || '';
                })
                .catch(error => {
                    console.error('Error fetching staff name:', error);
                    staffNameField.value = '';
                });
        });
        
        const staffIdField = document.getElementById('staff_id');
        if (staffIdField.value) {
            staffIdField.dispatchEvent(new Event('input'));
        }
    </script>
</body>
</html>