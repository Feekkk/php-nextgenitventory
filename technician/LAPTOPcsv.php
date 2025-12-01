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
$skippedRows = [];
$importedCount = 0;
$allowedStatuses = ['AVAILABLE', 'UNAVAILABLE', 'MAINTENANCE', 'DISPOSED'];
$requiredHeaders = ['serial_num', 'brand', 'model', 'status'];
$optionalHeaders = ['acquisition_type', 'category', 'staff_id', 'assignment_type', 'location', 'processor', 'memory', 'os', 'storage', 'gpu', 'warranty_expiry', 'part_number', 'supplier', 'period', 'activity_log', 'p.o_date', 'p.o_num', 'd.o_date', 'd.o_num', 'invoice_date', 'invoice_num', 'purchase_cost', 'remarks'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_FILES['csvFile']) || $_FILES['csvFile']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Please upload a valid CSV file.';
    } else {
        $file = $_FILES['csvFile'];
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if ($extension !== 'csv') {
            $errors[] = 'Only CSV files are supported.';
        }

        if ($file['size'] > 5 * 1024 * 1024) {
            $errors[] = 'CSV file must be smaller than 5MB.';
        }

        if (empty($errors)) {
            $handle = fopen($file['tmp_name'], 'r');

            if ($handle === false) {
                $errors[] = 'Unable to read the uploaded file.';
            } else {
                $normalize = function ($value) {
                    $value = trim((string)$value);
                    $value = strtolower(str_replace([' ', '-'], '_', $value));
                    $value = str_replace(['(', ')'], '', $value);
                    
                    $columnMapping = [
                        'asset_tag' => 'asset_id',
                        'serial_number' => 'serial_num',
                        'employer_id' => 'staff_id',
                        'assignment_type' => 'assignment_type',
                        'location' => 'location',
                        'operating_system' => 'os',
                        'operating_system_os' => 'os',
                        'warranty_expires' => 'warranty_expiry',
                        'p.0_number' => 'p.o_num',
                        'p.o_number' => 'p.o_num',
                        'd.o_no' => 'd.o_num',
                        'd.o_number' => 'd.o_num',
                        'invoice_no' => 'invoice_num',
                        'invoice_number' => 'invoice_num',
                        'acquisition_type' => 'acquisition_type',
                    ];
                    
                    if (isset($columnMapping[$value])) {
                        return $columnMapping[$value];
                    }
                    
                    return $value;
                };

                $headerRow = fgetcsv($handle);

                if ($headerRow === false) {
                    $errors[] = 'The CSV file is empty.';
                } else {
                    $headers = array_map($normalize, $headerRow);
                    $availableColumns = array_unique(array_filter($headers));

                    foreach ($requiredHeaders as $required) {
                        if (!in_array($required, $availableColumns, true)) {
                            $errors[] = "Missing required column: {$required}";
                        }
                    }
                }

                if (empty($errors)) {
                    try {
                        $pdo->beginTransaction();
                        $lineNumber = 2;
                        $insertStmt = $pdo->prepare("
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

                        while (($row = fgetcsv($handle)) !== false) {
                            $rawValues = array_map('trim', $row);
                            if (count(array_filter($rawValues, fn($value) => $value !== '')) === 0) {
                                $lineNumber++;
                                continue;
                            }

                            $rowData = [
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
                                'p.o_date' => '',
                                'p.o_num' => '',
                                'd.o_date' => '',
                                'd.o_num' => '',
                                'invoice_date' => '',
                                'invoice_num' => '',
                                'purchase_cost' => '',
                                'remarks' => '',
                            ];

                            foreach ($headers as $index => $columnName) {
                                if ($columnName && array_key_exists($columnName, $rowData) && isset($row[$index])) {
                                    $rowData[$columnName] = trim($row[$index]);
                                }
                            }

                            $rowErrors = [];

                            foreach ($requiredHeaders as $requiredColumn) {
                                if ($rowData[$requiredColumn] === '') {
                                    $rowErrors[] = "{$requiredColumn} is required";
                                }
                            }

                            if ($rowData['status'] !== '' && !in_array(strtoupper($rowData['status']), $allowedStatuses, true)) {
                                $rowErrors[] = 'Invalid status value';
                            }

                            if ($rowData['p.o_date'] !== '') {
                                $date = DateTime::createFromFormat('Y-m-d', $rowData['p.o_date']);
                                if (!$date || $date->format('Y-m-d') !== $rowData['p.o_date']) {
                                    $rowErrors[] = 'Invalid P.O. date format (use YYYY-MM-DD)';
                                }
                            }

                            if ($rowData['d.o_date'] !== '') {
                                $date = DateTime::createFromFormat('Y-m-d', $rowData['d.o_date']);
                                if (!$date || $date->format('Y-m-d') !== $rowData['d.o_date']) {
                                    $rowErrors[] = 'Invalid D.O. date format (use YYYY-MM-DD)';
                                }
                            }

                            if ($rowData['invoice_date'] !== '') {
                                $date = DateTime::createFromFormat('Y-m-d', $rowData['invoice_date']);
                                if (!$date || $date->format('Y-m-d') !== $rowData['invoice_date']) {
                                    $rowErrors[] = 'Invalid invoice date format (use YYYY-MM-DD)';
                                }
                            }

                            if ($rowData['warranty_expiry'] !== '') {
                                $date = DateTime::createFromFormat('Y-m-d', $rowData['warranty_expiry']);
                                if (!$date || $date->format('Y-m-d') !== $rowData['warranty_expiry']) {
                                    $rowErrors[] = 'Invalid warranty expiry date format (use YYYY-MM-DD)';
                                }
                            }

                            if ($rowData['purchase_cost'] !== '' && !is_numeric($rowData['purchase_cost'])) {
                                $rowErrors[] = 'Purchase cost must be a number';
                            }

                            if ($rowData['staff_id'] !== '' && !is_numeric($rowData['staff_id'])) {
                                $rowErrors[] = 'Staff ID must be a number';
                            }

                            if ($rowData['asset_id'] !== '' && !is_numeric($rowData['asset_id'])) {
                                $rowErrors[] = 'Asset ID must be a number';
                            }

                            if (!empty($rowErrors)) {
                                $skippedRows[] = "Row {$lineNumber}: " . implode('; ', $rowErrors);
                                $lineNumber++;
                                continue;
                            }

                            $statusValue = strtoupper($rowData['status']);
                            
                            $assetId = $rowData['asset_id'] !== '' ? (int)$rowData['asset_id'] : null;
                            $poDate = $rowData['p.o_date'] ?: null;
                            $doDate = $rowData['d.o_date'] ?: null;
                            $invoiceDate = $rowData['invoice_date'] ?: null;
                            $warrantyExpiry = $rowData['warranty_expiry'] ?: null;
                            $purchaseCost = $rowData['purchase_cost'] !== '' ? $rowData['purchase_cost'] : null;
                            $staffId = $rowData['staff_id'] !== '' ? $rowData['staff_id'] : null;

                            $insertStmt->execute([
                                ':asset_id' => $assetId,
                                ':serial_num' => $rowData['serial_num'],
                                ':brand' => $rowData['brand'],
                                ':model' => $rowData['model'],
                                ':acquisition_type' => $rowData['acquisition_type'] ?: null,
                                ':category' => $rowData['category'] ?: null,
                                ':status' => $statusValue,
                                ':staff_id' => $staffId,
                                ':assignment_type' => $rowData['assignment_type'] ?: null,
                                ':location' => $rowData['location'] ?: null,
                                ':processor' => $rowData['processor'] ?: null,
                                ':memory' => $rowData['memory'] ?: null,
                                ':os' => $rowData['os'] ?: null,
                                ':storage' => $rowData['storage'] ?: null,
                                ':gpu' => $rowData['gpu'] ?: null,
                                ':warranty_expiry' => $warrantyExpiry,
                                ':part_number' => $rowData['part_number'] ?: null,
                                ':supplier' => $rowData['supplier'] ?: null,
                                ':period' => $rowData['period'] ?: null,
                                ':activity_log' => $rowData['activity_log'] ?: null,
                                ':po_date' => $poDate,
                                ':po_num' => $rowData['p.o_num'] ?: null,
                                ':do_date' => $doDate,
                                ':do_num' => $rowData['d.o_num'] ?: null,
                                ':invoice_date' => $invoiceDate,
                                ':invoice_num' => $rowData['invoice_num'] ?: null,
                                ':purchase_cost' => $purchaseCost,
                                ':remarks' => $rowData['remarks'] ?: null,
                            ]);

                            $importedCount++;
                            $lineNumber++;
                        }

                        $pdo->commit();

                        if ($importedCount > 0) {
                            $successMessage = "Imported {$importedCount} asset(s) successfully.";
                        }
                    } catch (PDOException $e) {
                        $pdo->rollBack();
                        $errors[] = 'Unable to import CSV right now. Please try again.';
                    }
                }

                fclose($handle);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Laptop/Desktop Assets (CSV) - UniKL RCMP IT Inventory</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/TechDashboard.css">
    <style>
        .import-page-container {
            max-width: 800px;
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

        .import-card {
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(0, 0, 0, 0.05);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.05);
        }

        .upload-area {
            border: 2px dashed rgba(26, 26, 46, 0.15);
            border-radius: 16px;
            padding: 30px;
            text-align: center;
            background: rgba(26, 26, 46, 0.02);
            margin-bottom: 25px;
        }

        .upload-area i {
            font-size: 2.5rem;
            color: #0984e3;
            margin-bottom: 15px;
        }

        .upload-area p {
            margin: 6px 0;
            color: #2d3436;
        }

        .upload-area small {
            color: #636e72;
        }

        .upload-area input[type="file"] {
            margin-top: 15px;
        }

        .guidelines {
            margin-bottom: 25px;
        }

        .guidelines h3 {
            font-size: 1.05rem;
            color: #1a1a2e;
            margin-bottom: 10px;
        }

        .guidelines ul {
            list-style: disc;
            margin-left: 20px;
            color: #2d3436;
            line-height: 1.6;
        }

        .sample-table-wrapper {
            width: 100%;
            overflow-x: auto;
            margin-bottom: 25px;
        }

        .sample-table {
            min-width: 800px;
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }

        .sample-table th,
        .sample-table td {
            border: 1px solid rgba(0, 0, 0, 0.1);
            padding: 10px;
            text-align: left;
            background: #fff;
            white-space: nowrap;
        }

        .sample-table th {
            background: rgba(26, 26, 46, 0.05);
            font-weight: 600;
        }

        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            margin-top: 20px;
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

        .btn-primary:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .btn-secondary {
            background: #f1f2f6;
            color: #2d3436;
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

        .skipped-list {
            margin-top: 15px;
            background: rgba(255, 255, 255, 0.6);
            border: 1px solid rgba(0, 0, 0, 0.05);
            border-radius: 12px;
            padding: 15px;
        }

        .skipped-list ul {
            margin: 0;
            padding-left: 20px;
            color: #c0392b;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <?php include_once("../components/HomeHeader.php"); ?>

    <div class="import-page-container">
        <div class="page-header">
            <h1>Import Laptop/Desktop Assets</h1>
            <p>Bulk upload workstations, laptops, and desktops using the CSV template so assignments and specs stay accurate.</p>
        </div>

        <div class="import-card">
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

            <?php if (!empty($skippedRows)) : ?>
                <div class="skipped-list">
                    <strong>Skipped rows</strong>
                    <ul>
                        <?php foreach ($skippedRows as $skipped) : ?>
                            <li><?php echo htmlspecialchars($skipped); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form class="import-form" method="POST" enctype="multipart/form-data">
                <div class="upload-area">
                    <i class="fa-solid fa-file-csv"></i>
                    <p><strong>Drag & drop your CSV file here</strong></p>
                    <p>or</p>
                    <input type="file" name="csvFile" accept=".csv">
                    <small>Accepted format: .csv only. Max size 5MB.</small>
                </div>

                <div class="guidelines">
                    <h3>Before uploading</h3>
                    <ul>
                        <li>CSV headers will be automatically normalized (spaces/hyphens to underscores, case-insensitive).</li>
                        <li>Required columns: Serial Number, Brand, Model, Status.</li>
                        <li>Optional columns: Asset Tag, ACQUISITION TYPE, Category, Employer ID, Assignment Type, Location, P.O Date, P.O Number, D.O Number, Invoice Date, Invoice Number, Purchase Cost, Processor, Memory, Operating System, Storage, Warranty Expires, Part Number, Supplier, Period, Activity Log.</li>
                        <li>Date columns (P.O Date, D.O Date, Invoice Date, Warranty Expires) should be in YYYY-MM-DD format.</li>
                        <li>Asset Tag column maps to asset_id (must be numeric if provided).</li>
                    </ul>
                </div>

                <div class="guidelines">
                    <h3>Column template</h3>
                    <div class="sample-table-wrapper">
                        <table class="sample-table">
                            <thead>
                                <tr>
                                    <th>Asset Tag</th>
                                    <th>Serial Number</th>
                                    <th>Brand</th>
                                    <th>Model</th>
                                    <th>ACQUISITION TYPE</th>
                                    <th>Category</th>
                                    <th>Status</th>
                                    <th>Employer ID</th>
                                    <th>Assignment Type</th>
                                    <th>Location</th>
                                    <th>P.O Date</th>
                                    <th>P.O Number</th>
                                    <th>D.O Number</th>
                                    <th>Invoice Date</th>
                                    <th>Invoice No</th>
                                    <th>Purchase Cost</th>
                                    <th>Processor</th>
                                    <th>Memory</th>
                                    <th>Operating System</th>
                                    <th>Storage</th>
                                    <th>Warranty Expires</th>
                                    <th>Part Number</th>
                                    <th>Supplier</th>
                                    <th>Period</th>
                                    <th>Activity Log</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>AT-001</td>
                                    <td>SN8745632</td>
                                    <td>Dell</td>
                                    <td>Latitude 7430</td>
                                    <td>Purchase</td>
                                    <td>Laptop</td>
                                    <td>AVAILABLE</td>
                                    <td>1</td>
                                    <td>Permanent</td>
                                    <td>Building A, Level 2</td>
                                    <td>2024-01-15</td>
                                    <td>PO-2024-001</td>
                                    <td>DO-2024-001</td>
                                    <td>2024-01-25</td>
                                    <td>INV-2024-001</td>
                                    <td>3500.00</td>
                                    <td>Intel Core i7-1185G7</td>
                                    <td>16GB DDR4</td>
                                    <td>Windows 11 Pro</td>
                                    <td>512GB NVMe SSD</td>
                                    <td>2026-01-15</td>
                                    <td>PN-12345</td>
                                    <td>Dell Inc</td>
                                    <td>Q1 2024</td>
                                    <td>Initial setup</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <small style="color:#636e72;">Column names are case-insensitive and will be automatically normalized. Asset Tag column is optional and will be ignored.</small>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="window.history.back()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Upload CSV</button>
                </div>
            </form>
        </div>
    </div>

    <footer>
        <?php include_once("../components/Footer.php"); ?>
    </footer>
</body>
</html>

