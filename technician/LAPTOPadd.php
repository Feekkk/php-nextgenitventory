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
$allowedStatuses = ['DEPLOY', 'FAULTY', 'DISPOSE', 'RESERVED', 'UNDER MAINTENANCE', 'NON-ACTIVE', 'LOST'];
$staffName = '';

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
    'acquisition_type' => '',
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
    'P.O_DATE' => '',
    'P.O_NUM' => '',
    'D.O_DATE' => '',
    'D.O_NUM' => '',
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

    if ($formData['PURCHASE_COST'] !== '' && !is_numeric($formData['PURCHASE_COST'])) {
        $errors[] = 'Purchase cost must be a valid number.';
    }

    if ($formData['staff_id'] !== '' && !is_numeric($formData['staff_id'])) {
        $errors[] = 'Staff ID must be a valid number.';
    }

    if ($formData['asset_id'] !== '' && !is_numeric($formData['asset_id'])) {
        $errors[] = 'Asset ID must be a valid number.';
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO laptop_desktop_assets (
                    asset_id, serial_num, brand, model, acquisition_type, category, status, staff_id,
                    assignment_type, location, processor, memory, os, storage, gpu, warranty_expiry, part_number,
                    supplier, period, activity_log, `P.O_DATE`, `P.O_NUM`, `D.O_DATE`, `D.O_NUM`,
                    `INVOICE_DATE`, `INVOICE_NUM`, `PURCHASE_COST`, remarks
                ) VALUES (
                    :asset_id, :serial_num, :brand, :model, :acquisition_type, :category, :status, :staff_id,
                    :assignment_type, :location, :processor, :memory, :os, :storage, :gpu, :warranty_expiry, :part_number,
                    :supplier, :period, :activity_log, :po_date, :po_num, :do_date, :do_num,
                    :invoice_date, :invoice_num, :purchase_cost, :remarks
                )
            ");

            $assetId = $formData['asset_id'] !== '' ? (int)$formData['asset_id'] : null;
            $poDate = $formData['P.O_DATE'] ?: null;
            $doDate = $formData['D.O_DATE'] ?: null;
            $invoiceDate = $formData['INVOICE_DATE'] ?: null;
            $warrantyExpiry = $formData['warranty_expiry'] ?: null;
            $purchaseCost = $formData['PURCHASE_COST'] !== '' ? $formData['PURCHASE_COST'] : null;
            $staffId = $formData['staff_id'] !== '' ? $formData['staff_id'] : null;

            $stmt->execute([
                ':asset_id' => $assetId,
                ':serial_num' => $formData['serial_num'],
                ':brand' => $formData['brand'],
                ':model' => $formData['model'],
                ':acquisition_type' => $formData['acquisition_type'] ?: null,
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
                ':po_num' => $formData['P.O_NUM'] ?: null,
                ':do_date' => $doDate,
                ':do_num' => $formData['D.O_NUM'] ?: null,
                ':invoice_date' => $invoiceDate,
                ':invoice_num' => $formData['INVOICE_NUM'] ?: null,
                ':purchase_cost' => $purchaseCost,
                ':remarks' => $formData['remarks'] ?: null,
            ]);

            $successMessage = 'Laptop/Desktop asset saved successfully.';
            foreach (array_keys($formData) as $field) {
                $formData[$field] = '';
            }
            $staffName = '';
        } catch (PDOException $e) {
            $errors[] = 'Unable to save asset right now. Please try again.';
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
                        <label for="acquisition_type">Acquisition Type</label>
                        <select id="acquisition_type" name="acquisition_type">
                            <option value="">Select acquisition type</option>
                            <option value="OWNERSHIP" <?php echo $formData['acquisition_type'] === 'OWNERSHIP' ? 'selected' : ''; ?>>OWNERSHIP</option>
                            <option value="LEASE" <?php echo $formData['acquisition_type'] === 'LEASE' ? 'selected' : ''; ?>>LEASE</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="part_number">Part Number</label>
                        <input type="text" id="part_number" name="part_number" placeholder="Enter part number" value="<?php echo htmlspecialchars($formData['part_number']); ?>">
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
                        <input type="number" id="staff_id" name="staff_id" placeholder="Enter staff ID" value="<?php echo htmlspecialchars($formData['staff_id']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="staff_name">Staff Name</label>
                        <input type="text" id="staff_name" name="staff_name" placeholder="Auto-filled from Staff ID" value="<?php echo htmlspecialchars($staffName); ?>" readonly style="background-color: #f5f5f5; cursor: not-allowed;">
                    </div>
                    <div class="form-group">
                        <label for="assignment_type">Assignment Type</label>
                        <select id="assignment_type" name="assignment_type">
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
                        <input type="text" id="location" name="location" placeholder="e.g., Building A, Level 2" value="<?php echo htmlspecialchars($formData['location']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="warranty_expiry">Warranty Expiry</label>
                        <input type="date" id="warranty_expiry" name="warranty_expiry" value="<?php echo htmlspecialchars($formData['warranty_expiry']); ?>">
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
                    <div class="form-group">
                        <label for="supplier">Supplier</label>
                        <input type="text" id="supplier" name="supplier" placeholder="Enter supplier name" value="<?php echo htmlspecialchars($formData['supplier']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="period">Period</label>
                        <input type="text" id="period" name="period" placeholder="e.g., 2024-2025" value="<?php echo htmlspecialchars($formData['period']); ?>">
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3 class="form-section-title">Purchase Information</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="P.O_DATE">P.O. Date</label>
                        <input type="date" id="P.O_DATE" name="P.O_DATE" value="<?php echo htmlspecialchars($formData['P.O_DATE']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="P.O_NUM">P.O. Number</label>
                        <input type="text" id="P.O_NUM" name="P.O_NUM" placeholder="Enter P.O. number" value="<?php echo htmlspecialchars($formData['P.O_NUM']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="D.O_DATE">D.O. Date</label>
                        <input type="date" id="D.O_DATE" name="D.O_DATE" value="<?php echo htmlspecialchars($formData['D.O_DATE']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="D.O_NUM">D.O. Number</label>
                        <input type="text" id="D.O_NUM" name="D.O_NUM" placeholder="Enter D.O. number" value="<?php echo htmlspecialchars($formData['D.O_NUM']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="INVOICE_DATE">Invoice Date</label>
                        <input type="date" id="INVOICE_DATE" name="INVOICE_DATE" value="<?php echo htmlspecialchars($formData['INVOICE_DATE']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="INVOICE_NUM">Invoice Number</label>
                        <input type="text" id="INVOICE_NUM" name="INVOICE_NUM" placeholder="Enter invoice number" value="<?php echo htmlspecialchars($formData['INVOICE_NUM']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="PURCHASE_COST">Purchase Cost (MYR)</label>
                        <input type="number" step="0.01" id="PURCHASE_COST" name="PURCHASE_COST" placeholder="Enter purchase cost" value="<?php echo htmlspecialchars($formData['PURCHASE_COST']); ?>">
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
                <button type="submit" class="btn btn-primary">Save Asset</button>
            </div>
        </form>
    </div>

    <footer>
        <?php include_once("../components/Footer.php"); ?>
    </footer>

    <script>
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