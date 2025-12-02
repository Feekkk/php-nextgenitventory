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
$allowedStatuses = ['ONLINE', 'OFFLINE', 'MAINTENANCE', 'DISPOSE', 'RESERVED', 'UNDER MAINTENANCE'];
$formData = [
    'serial' => '',
    'brand' => '',
    'model' => '',
    'mac_add' => '',
    'ip_add' => '',
    'building' => '',
    'level' => '',
    'status' => '',
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

    if ($formData['serial'] === '') {
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

    if ($formData['ip_add'] !== '' && !filter_var($formData['ip_add'], FILTER_VALIDATE_IP)) {
        $errors[] = 'Enter a valid IP address.';
    }

    if ($formData['mac_add'] !== '') {
        $mac = strtoupper(str_replace('-', ':', $formData['mac_add']));
        if (!preg_match('/^([0-9A-F]{2}:){5}[0-9A-F]{2}$/', $mac)) {
            $errors[] = 'Enter a valid MAC address (e.g., AA:BB:CC:DD:EE:FF).';
        } else {
            $formData['mac_add'] = $mac;
        }
    }

    if ($formData['PURCHASE_COST'] !== '' && !is_numeric($formData['PURCHASE_COST'])) {
        $errors[] = 'Purchase cost must be a valid number.';
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO net_assets (
                    serial, model, brand, mac_add, ip_add,
                    building, level, status, `PO_DATE`, `PO_NUM`,
                    `DO_DATE`, `DO_NUM`, `INVOICE_DATE`, `INVOICE_NUM`,
                    `PURCHASE_COST`, remarks, created_by
                ) VALUES (
                    :serial, :model, :brand, :mac_add, :ip_add,
                    :building, :level, :status, :po_date, :po_num,
                    :do_date, :do_num, :invoice_date, :invoice_num,
                    :purchase_cost, :remarks, :created_by
                )
            ");

            $poDate = $formData['PO_DATE'] ?: null;
            $invoiceDate = $formData['INVOICE_DATE'] ?: null;
            $purchaseCost = $formData['PURCHASE_COST'] !== '' ? $formData['PURCHASE_COST'] : null;

            $stmt->execute([
                ':serial' => $formData['serial'],
                ':model' => $formData['model'],
                ':brand' => $formData['brand'],
                ':mac_add' => $formData['mac_add'] ?: null,
                ':ip_add' => $formData['ip_add'] ?: null,
                ':building' => $formData['building'] ?: null,
                ':level' => $formData['level'] ?: null,
                ':status' => $formData['status'],
                ':po_date' => $poDate,
                ':po_num' => $formData['PO_NUM'] ?: null,
                ':do_date' => $formData['DO_DATE'] ?: null,
                ':do_num' => $formData['DO_NUM'] ?: null,
                ':invoice_date' => $invoiceDate,
                ':invoice_num' => $formData['INVOICE_NUM'] ?: null,
                ':purchase_cost' => $purchaseCost,
                ':remarks' => $formData['remarks'] ?: null,
                ':created_by' => $_SESSION['user_id'],
            ]);

            // Asset trail: record network asset creation
            try {
                $newAssetId = (int)$pdo->lastInsertId();
                $trailStmt = $pdo->prepare("
                    INSERT INTO asset_trails (
                        asset_type, asset_id, action_type, changed_by,
                        field_name, old_value, new_value, description,
                        ip_address, user_agent
                    ) VALUES (
                        'network', :asset_id, 'CREATE', :changed_by,
                        NULL, NULL, NULL, :description,
                        :ip_address, :user_agent
                    )
                ");
                $trailStmt->execute([
                    ':asset_id' => $newAssetId,
                    ':changed_by' => $_SESSION['user_id'] ?? null,
                    ':description' => 'Created network asset with serial ' . $formData['serial'],
                    ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                    ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                ]);
            } catch (PDOException $e) {
                error_log('Failed to write network asset trail: ' . $e->getMessage());
            }

            $successMessage = 'Network asset saved successfully.';
            foreach (array_keys($formData) as $field) {
                $formData[$field] = '';
            }
        } catch (PDOException $e) {
            $errors[] = 'Unable to save asset right now. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Network Asset - UniKL RCMP IT Inventory</title>
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

        .network-form {
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
            <h1>Add Network Asset</h1>
            <p>Register routers, switches, wireless gear, and other network equipment with complete deployment details.</p>
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

        <form class="network-form" method="POST" autocomplete="off">
            <div class="form-section">
                <h3 class="form-section-title">Asset Information</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="serial">Serial Number <span style="color:#c0392b;">*</span></label>
                        <input type="text" id="serial" name="serial" placeholder="Enter serial number" value="<?php echo htmlspecialchars($formData['serial']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="brand">Brand <span style="color:#c0392b;">*</span></label>
                        <input type="text" id="brand" name="brand" placeholder="e.g., Cisco" value="<?php echo htmlspecialchars($formData['brand']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="model">Model <span style="color:#c0392b;">*</span></label>
                        <input type="text" id="model" name="model" placeholder="e.g., Catalyst 9300" value="<?php echo htmlspecialchars($formData['model']); ?>" required>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3 class="form-section-title">Network Configuration</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="mac_add">MAC Address</label>
                        <input type="text" id="mac_add" name="mac_add" placeholder="e.g., AA:BB:CC:DD:EE:FF" value="<?php echo htmlspecialchars($formData['mac_add']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="ip_add">Management IP Address</label>
                        <input type="text" id="ip_add" name="ip_add" placeholder="e.g., 10.10.10.5" value="<?php echo htmlspecialchars($formData['ip_add']); ?>">
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3 class="form-section-title">Deployment Details</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="building">Building</label>
                        <input type="text" id="building" name="building" placeholder="e.g., Main Campus" value="<?php echo htmlspecialchars($formData['building']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="level">Level / Floor</label>
                        <input type="text" id="level" name="level" placeholder="e.g., Level 3" value="<?php echo htmlspecialchars($formData['level']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="status">Status <span style="color:#c0392b;">*</span></label>
                        <select id="status" name="status" required>
                            <option value="">Select status</option>
                            <?php foreach ($allowedStatuses as $status) : ?>
                                <option value="<?php echo htmlspecialchars($status); ?>" <?php echo $formData['status'] === $status ? 'selected' : ''; ?>>
                                    <?php echo ucwords(str_replace('-', ' ', strtolower($status))); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3 class="form-section-title">Purchase Information</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="PO_DATE">P.O. Date</label>
                        <input type="date" id="PO_DATE" name="PO_DATE" value="<?php echo htmlspecialchars($formData['PO_DATE']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="PO_NUM">P.O. Number</label>
                        <input type="text" id="PO_NUM" name="PO_NUM" placeholder="Enter P.O. number" value="<?php echo htmlspecialchars($formData['PO_NUM']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="DO_DATE">D.O. Date</label>
                        <input type="text" id="DO_DATE" name="DO_DATE" placeholder="Enter D.O. date" value="<?php echo htmlspecialchars($formData['DO_DATE']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="DO_NUM">D.O. Number</label>
                        <input type="text" id="DO_NUM" name="DO_NUM" placeholder="Enter D.O. number" value="<?php echo htmlspecialchars($formData['DO_NUM']); ?>">
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
                        <input type="number" step="0.01" id="PURCHASE_COST" name="PURCHASE_COST" placeholder="Enter cost" value="<?php echo htmlspecialchars($formData['PURCHASE_COST']); ?>">
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3 class="form-section-title">Additional Information</h3>
                <div class="form-grid">
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label for="remarks">Remarks</label>
                        <textarea id="remarks" name="remarks" placeholder="Add deployment notes, maintenance info, etc."><?php echo htmlspecialchars($formData['remarks']); ?></textarea>
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
</body>
</html>
