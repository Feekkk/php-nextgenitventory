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
$allowedStatuses = ['DEPLOY', 'FAULTY', 'DISPOSE', 'RESERVED', 'UNDER MAINTENANCE', 'NON-ACTIVE', 'LOST', 'ACTIVE'];

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
    
    return (int)($prefix . str_pad($nextSequence, 3, '0', STR_PAD_LEFT));
}
$requiredHeaders = ['serial_num', 'brand', 'model', 'status'];

if (!function_exists('convertExcelDate')) {
    function convertExcelDate($value) {
        if (empty($value) || !is_numeric($value)) {
            return $value;
        }
        $numValue = (float)$value;
        if ($numValue < 1 || $numValue > 1000000) {
            return $value;
        }
        try {
            $excelEpoch = new DateTime('1899-12-30');
            $days = (int)$numValue;
            $excelEpoch->modify("+{$days} days");
            $result = $excelEpoch->format('Y-m-d');
            if ($excelEpoch->format('Y') < 1900 || $excelEpoch->format('Y') > 2100) {
                return $value;
            }
            return $result;
        } catch (Exception $e) {
            return $value;
        }
    }
}
$optionalHeaders = ['category', 'staff_id', 'assignment_type', 'location', 'processor', 'memory', 'os', 'storage', 'gpu', 'warranty_expiry', 'part_number', 'supplier', 'period', 'activity_log', 'p.o_date', 'p.o_num', 'd.o_date', 'd.o_num', 'invoice_date', 'invoice_num', 'purchase_cost', 'remarks'];

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
                        'asset_id' => 'asset_id',
                        'serial_number' => 'serial_num',
                        'serial_num' => 'serial_num',
                        'employer_id' => 'staff_id',
                        'staff_id' => 'staff_id',
                        'assignment_type' => 'assignment_type',
                        'location' => 'location',
                        'operating_system' => 'os',
                        'operating_system_os' => 'os',
                        'os' => 'os',
                        'warranty_expires' => 'warranty_expiry',
                        'warranty' => 'warranty_expiry',
                        'warranty_expiry' => 'warranty_expiry',
                        'p.0_number' => 'p.o_num',
                        'p.o_number' => 'p.o_num',
                        'p.o_num' => 'p.o_num',
                        'po_number' => 'p.o_num',
                        'po_num' => 'p.o_num',
                        'd.o_no' => 'd.o_num',
                        'd.o_number' => 'd.o_num',
                        'd.o_num' => 'd.o_num',
                        'do_number' => 'd.o_num',
                        'do_no' => 'd.o_num',
                        'invoice_no' => 'invoice_num',
                        'invoice_number' => 'invoice_num',
                        'invoice_num' => 'invoice_num',
                        'categories' => 'category',
                        'category' => 'category',
                        'part_no' => 'part_number',
                        'part_number' => 'part_number',
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
                    $originalHeaders = $headerRow;

                    $headerDisplayNames = [
                        'serial_num' => 'Serial Number (or SERIAL_NUMBER)',
                        'brand' => 'Brand',
                        'model' => 'Model',
                        'status' => 'Status'
                    ];
                    
                    foreach ($requiredHeaders as $required) {
                        if (!in_array($required, $availableColumns, true)) {
                            $displayName = $headerDisplayNames[$required] ?? $required;
                            $foundColumns = implode(', ', array_filter($originalHeaders, fn($h) => !empty(trim($h))));
                            $errors[] = "Missing required column: {$displayName}. Your CSV has: {$foundColumns}";
                        }
                    }
                }

                if (empty($errors)) {
                    try {
                        $year = date('y');
                        $prefix = '11' . $year;
                        $stmt = $pdo->prepare("SELECT asset_id FROM laptop_desktop_assets WHERE asset_id LIKE ? ORDER BY asset_id DESC LIMIT 1");
                        $stmt->execute([$prefix . '%']);
                        $lastId = $stmt->fetchColumn();
                        $currentSequence = $lastId ? (int)substr($lastId, -3) : 0;
                        
                        $csvAssetIds = [];
                        $tempLineNumber = 2;
                        $tempHandle = fopen($file['tmp_name'], 'r');
                        $tempHeaderRow = fgetcsv($tempHandle);
                        $tempHeaders = array_map($normalize, $tempHeaderRow);
                        $tempSequence = $currentSequence;
                        
                        while (($tempRow = fgetcsv($tempHandle)) !== false) {
                            $tempValues = array_map('trim', $tempRow);
                            if (count(array_filter($tempValues, fn($value) => $value !== '')) === 0) {
                                $tempLineNumber++;
                                continue;
                            }
                            
                            $tempRowData = [];
                            foreach ($tempHeaders as $index => $columnName) {
                                if ($columnName && isset($tempRow[$index])) {
                                    $tempRowData[$columnName] = trim($tempRow[$index]);
                                }
                            }
                            
                            if (!empty($tempRowData['asset_id']) && is_numeric($tempRowData['asset_id'])) {
                                $csvAssetIds[] = (int)$tempRowData['asset_id'];
                            } else {
                                $tempSequence++;
                                $csvAssetIds[] = (int)($prefix . str_pad($tempSequence, 3, '0', STR_PAD_LEFT));
                            }
                            $tempLineNumber++;
                        }
                        fclose($tempHandle);
                        
                        if (!empty($csvAssetIds)) {
                            $placeholders = implode(',', array_fill(0, count($csvAssetIds), '?'));
                            $checkStmt = $pdo->prepare("SELECT asset_id FROM laptop_desktop_assets WHERE asset_id IN ($placeholders)");
                            $checkStmt->execute($csvAssetIds);
                            $existingIds = $checkStmt->fetchAll(PDO::FETCH_COLUMN);
                            
                            if (!empty($existingIds)) {
                                $errors[] = 'Import rejected: The following Asset IDs already exist in the database: ' . implode(', ', $existingIds) . '. Please remove these duplicates from your CSV file or use different Asset IDs.';
                            }
                        }
                        
                        if (empty($errors)) {
                            rewind($handle);
                            fgetcsv($handle);
                            $pdo->beginTransaction();
                            $lineNumber = 2;
                            $currentSequence = $lastId ? (int)substr($lastId, -3) : 0;
                            
                            $insertStmt = $pdo->prepare("
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
                        $importedAssetIds = [];

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
                                    $value = trim($row[$index]);
                                    if (in_array($columnName, ['p.o_date', 'd.o_date', 'invoice_date', 'warranty_expiry']) && is_numeric($value) && $value > 0) {
                                        $value = convertExcelDate($value);
                                    }
                                    if ($columnName === 'status') {
                                        $value = preg_replace('/[\x00-\x1F\x7F-\x9F]/u', '', $value);
                                        $value = str_replace(["\xEF\xBB\xBF", "\xC2\xA0"], '', $value);
                                    }
                                    $rowData[$columnName] = $value;
                                }
                            }

                            $rowErrors = [];

                            $columnDisplayNames = [
                                'serial_num' => 'Serial Number',
                                'brand' => 'Brand',
                                'model' => 'Model',
                                'status' => 'Status',
                                'staff_id' => 'Staff ID',
                                'asset_id' => 'Asset ID',
                                'p.o_date' => 'P.O. Date',
                                'd.o_date' => 'D.O. Date',
                                'invoice_date' => 'Invoice Date',
                                'warranty_expiry' => 'Warranty Expiry',
                                'purchase_cost' => 'Purchase Cost'
                            ];

                            foreach ($requiredHeaders as $requiredColumn) {
                                if ($rowData[$requiredColumn] === '') {
                                    $displayName = $columnDisplayNames[$requiredColumn] ?? $requiredColumn;
                                    $rowErrors[] = "Missing required field: {$displayName}";
                                }
                            }

                            if ($rowData['status'] !== '') {
                                $normalizedStatus = strtoupper(trim($rowData['status']));
                                $normalizedStatus = preg_replace('/\s+/', ' ', $normalizedStatus);
                                $normalizedStatus = trim($normalizedStatus);
                                if (!in_array($normalizedStatus, $allowedStatuses, true)) {
                                    $validStatuses = implode(', ', $allowedStatuses);
                                    $rowErrors[] = "Invalid Status value: '{$rowData['status']}'. Valid values are: {$validStatuses}";
                                } else {
                                    $rowData['status'] = $normalizedStatus;
                                }
                            }

                            if ($rowData['p.o_date'] !== '') {
                                $date = DateTime::createFromFormat('Y-m-d', $rowData['p.o_date']);
                                if (!$date || $date->format('Y-m-d') !== $rowData['p.o_date']) {
                                    $rowErrors[] = "Invalid P.O. Date format: '{$rowData['p.o_date']}'. Expected format: YYYY-MM-DD (e.g., 2024-01-15)";
                                }
                            }

                            if ($rowData['d.o_date'] !== '') {
                                $date = DateTime::createFromFormat('Y-m-d', $rowData['d.o_date']);
                                if (!$date || $date->format('Y-m-d') !== $rowData['d.o_date']) {
                                    $rowErrors[] = "Invalid D.O. Date format: '{$rowData['d.o_date']}'. Expected format: YYYY-MM-DD (e.g., 2024-01-20)";
                                }
                            }

                            if ($rowData['invoice_date'] !== '') {
                                $date = DateTime::createFromFormat('Y-m-d', $rowData['invoice_date']);
                                if (!$date || $date->format('Y-m-d') !== $rowData['invoice_date']) {
                                    $rowErrors[] = "Invalid Invoice Date format: '{$rowData['invoice_date']}'. Expected format: YYYY-MM-DD (e.g., 2024-01-25)";
                                }
                            }

                            if ($rowData['warranty_expiry'] !== '') {
                                $date = DateTime::createFromFormat('Y-m-d', $rowData['warranty_expiry']);
                                if (!$date || $date->format('Y-m-d') !== $rowData['warranty_expiry']) {
                                    $rowErrors[] = "Invalid Warranty Expiry format: '{$rowData['warranty_expiry']}'. Expected format: YYYY-MM-DD (e.g., 2026-01-15)";
                                }
                            }

                            if ($rowData['purchase_cost'] !== '' && !is_numeric($rowData['purchase_cost'])) {
                                $rowErrors[] = "Invalid Purchase Cost: '{$rowData['purchase_cost']}'. Must be a number (e.g., 1200.50)";
                            }

                            if ($rowData['staff_id'] !== '') {
                                if (!is_numeric($rowData['staff_id'])) {
                                    $rowErrors[] = "Invalid Staff ID: '{$rowData['staff_id']}'. Must be a number";
                                } else {
                                    $checkStaffStmt = $pdo->prepare("SELECT COUNT(*) FROM staff_list WHERE staff_id = ?");
                                    $checkStaffStmt->execute([(int)$rowData['staff_id']]);
                                    $staffExists = $checkStaffStmt->fetchColumn();
                                    if ($staffExists == 0) {
                                        $rowErrors[] = "Staff ID '{$rowData['staff_id']}' does not exist in the staff list. Please add this staff member first or leave Staff ID empty";
                                    }
                                }
                            }

                            if ($rowData['asset_id'] !== '' && !is_numeric($rowData['asset_id'])) {
                                $rowErrors[] = "Invalid Asset ID: '{$rowData['asset_id']}'. Must be a number (or leave empty for auto-generation)";
                            }

                            if (!empty($rowErrors)) {
                                $skippedRows[] = "Row {$lineNumber}: " . implode('; ', $rowErrors);
                                $lineNumber++;
                                continue;
                            }

                            $statusValue = $rowData['status'];
                            
                            if ($rowData['asset_id'] !== '') {
                                $assetId = (int)$rowData['asset_id'];
                            } else {
                                $currentSequence++;
                                $assetId = (int)($prefix . str_pad($currentSequence, 3, '0', STR_PAD_LEFT));
                            }
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

                            $importedAssetIds[] = $assetId;

                            $importedCount++;
                            $lineNumber++;
                        }

                        $pdo->commit();

                        if ($importedCount > 0) {
                            $successMessage = "Imported {$importedCount} asset(s) successfully.";
                            
                            $firstAssetId = !empty($importedAssetIds) ? $importedAssetIds[0] : 0;
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
                                ':asset_id' => $firstAssetId,
                                ':changed_by' => $_SESSION['user_id'],
                                ':description' => "Bulk CSV import: Created {$importedCount} laptop/desktop asset(s) via CSV import",
                                ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                                ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                            ]);
                        }
                    } catch (PDOException $e) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        error_log('LAPTOP CSV Import Error: ' . $e->getMessage());
                        
                        if (empty($errors)) {
                            $errorMessage = 'Unable to import CSV. ';
                            if (strpos($e->getMessage(), '1452') !== false && strpos($e->getMessage(), 'staff_id') !== false) {
                                $errorMessage .= 'One or more Staff IDs in your CSV do not exist in the staff list. ';
                                $errorMessage .= 'Please check the skipped rows below for details, or add the missing staff members first.';
                            } elseif (strpos($e->getMessage(), '1062') !== false) {
                                $errorMessage .= 'Duplicate entry detected. This may be due to duplicate Serial Numbers or Asset IDs. ';
                                $errorMessage .= 'Please check your CSV for duplicates.';
                            } elseif (strpos($e->getMessage(), '1451') !== false) {
                                $errorMessage .= 'Referenced record not found. Please check that all referenced IDs exist in the system.';
                            } else {
                                $errorMessage .= 'Database error: ' . htmlspecialchars($e->getMessage());
                            }
                            
                            $errors[] = $errorMessage;
                        }
                    } catch (Exception $e) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        if (empty($errors)) {
                            $errors[] = 'Import failed: ' . $e->getMessage();
                        }
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
    <link rel="icon" type="image/png" href="../public/rcmp.png">
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
                    <strong style="display: block; margin-bottom: 10px;">
                        <i class="fa-solid fa-exclamation-triangle"></i> Import Errors
                    </strong>
                    <ul>
                        <?php foreach ($errors as $error) : ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid rgba(192, 57, 43, 0.2); font-size: 0.9rem;">
                        <strong>What to do:</strong> Fix the issues above and try importing again. Check that all required columns are present and data formats are correct.
                    </div>
                </div>
            <?php elseif ($successMessage) : ?>
                <div class="alert alert-success">
                    <strong style="display: block; margin-bottom: 5px;">
                        <i class="fa-solid fa-check-circle"></i> Success!
                    </strong>
                    <?php echo htmlspecialchars($successMessage); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($skippedRows)) : ?>
                <div class="skipped-list">
                    <strong style="display: block; margin-bottom: 10px; color: #c0392b;">
                        <i class="fa-solid fa-exclamation-circle"></i> Skipped Rows (<?php echo count($skippedRows); ?>)
                    </strong>
                    <p style="margin-bottom: 10px; color: #636e72; font-size: 0.9rem;">
                        The following rows were skipped due to validation errors. Please fix these issues in your CSV and re-import.
                    </p>
                    <ul>
                        <?php foreach ($skippedRows as $skipped) : ?>
                            <li><?php echo htmlspecialchars($skipped); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid rgba(0, 0, 0, 0.1); font-size: 0.85rem; color: #636e72;">
                        <strong>Common fixes:</strong>
                        <ul style="margin-top: 5px; padding-left: 20px;">
                            <li>Staff ID errors: Add the staff member to the staff list first, or leave Staff ID empty</li>
                            <li>Date errors: Use YYYY-MM-DD format (e.g., 2024-01-15)</li>
                            <li>Status errors: Use one of the valid status values shown in the template</li>
                            <li>Numeric errors: Ensure Purchase Cost and Staff ID are numbers only</li>
                        </ul>
                    </div>
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
                        <li>Optional columns: Asset Tag, Category, Employer ID, Assignment Type, Location, P.O Date, P.O Number, D.O Number, Invoice Date, Invoice Number, Purchase Cost, Processor, Memory, Operating System, Storage, Warranty Expires, Part Number, Supplier, Period, Activity Log.</li>
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

