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
$allowedStatuses = ['ACTIVE','RESERVED','FAULTY','DISPOSED','MAINTENANCE','LOST','DEPLOY'];

function generateAssetId($pdo, $categoryCode) {
    $year = date('y');
    $prefix = $categoryCode . $year;
    
    $stmt = $pdo->prepare("SELECT asset_id FROM av_assets WHERE asset_id LIKE ? ORDER BY asset_id DESC LIMIT 1");
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
$requiredHeaders = ['class', 'brand', 'model', 'serial_num', 'status'];
$optionalHeaders = ['location', 'p.o_date', 'p.o_num', 'd.o_date', 'd.o_num', 'invoice_date', 'invoice_num', 'purchase_cost', 'remarks', 'warranty_expiry'];
$headerMap = [
    'asset_id' => 'asset_id',
    'class' => 'class',
    'brand' => 'brand',
    'model' => 'model',
    'serial_number' => 'serial_num',
    'serial_num' => 'serial_num',
    'location' => 'location',
    'remarks' => 'remarks',
    'status' => 'status',
    'po_date' => 'p.o_date',
    'p.o_date' => 'p.o_date',
    'po_number' => 'p.o_num',
    'p.o_num' => 'p.o_num',
    'do_date' => 'd.o_date',
    'd.o_date' => 'd.o_date',
    'do_no' => 'd.o_num',
    'd.o_num' => 'd.o_num',
    'invoice_date' => 'invoice_date',
    'invoice_no' => 'invoice_num',
    'invoice_num' => 'invoice_num',
    'purchase_cost' => 'purchase_cost',
    'warranty' => 'warranty_expiry',
    'warranty_expiry' => 'warranty_expiry'
];

if (!function_exists('convertExcelDate')) {
    function convertExcelDate($value) {
        if (empty($value)) {
            return $value;
        }
        
        $value = trim($value);
        
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{2,4})$/', $value, $matches)) {
            $day = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
            $month = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
            $year = $matches[3];
            if (strlen($year) == 2) {
                $year = ($year > 50) ? '19' . $year : '20' . $year;
            }
            return $year . '-' . $month . '-' . $day;
        }
        
        if (is_numeric($value)) {
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
        
        return $value;
    }
}

if (!function_exists('normalizeHeaderKey')) {
    function normalizeHeaderKey(string $header): string
    {
        $header = preg_replace('/^\xEF\xBB\xBF/', '', $header);
        $header = strtolower(trim($header));
        return str_replace([' ', '-'], '_', $header);
    }
}

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
                $headerRow = fgetcsv($handle);

                if ($headerRow === false) {
                    $errors[] = 'The CSV file is empty.';
                } else {
                    $headers = [];
                    foreach ($headerRow as $index => $header) {
                        $normalized = normalizeHeaderKey($header);
                        $headers[$index] = $headerMap[$normalized] ?? '';
                    }
                    $availableColumns = array_unique(array_filter($headers));

                    foreach ($requiredHeaders as $required) {
                        if (!in_array($required, $availableColumns, true)) {
                            $errors[] = "Missing required column: {$required}";
                        }
                    }
                }

                if (empty($errors)) {
                    try {
                        $year = date('y');
                        $prefix = '22' . $year;
                        $stmt = $pdo->prepare("SELECT asset_id FROM av_assets WHERE asset_id LIKE ? ORDER BY asset_id DESC LIMIT 1");
                        $stmt->execute([$prefix . '%']);
                        $lastId = $stmt->fetchColumn();
                        $currentSequence = $lastId ? (int)substr($lastId, -3) : 0;
                        
                        $csvAssetIds = [];
                        $tempLineNumber = 2;
                        $tempHandle = fopen($file['tmp_name'], 'r');
                        fgetcsv($tempHandle);
                        $tempSequence = $currentSequence;
                        
                        while (($tempRow = fgetcsv($tempHandle)) !== false) {
                            $tempValues = array_map('trim', $tempRow);
                            if (count(array_filter($tempValues, fn($value) => $value !== '')) === 0) {
                                $tempLineNumber++;
                                continue;
                            }
                            
                            $tempRowData = [];
                            foreach ($headers as $index => $columnName) {
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
                            $checkStmt = $pdo->prepare("SELECT asset_id FROM av_assets WHERE asset_id IN ($placeholders)");
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
                            INSERT INTO av_assets (
                                asset_id, class, brand, model, serial_num, location, status,
                                `PO_DATE`, `PO_NUM`, `DO_DATE`, `DO_NUM`,
                                `INVOICE_DATE`, `INVOICE_NUM`, `PURCHASE_COST`, warranty_expiry, remarks, created_by
                            ) VALUES (
                                :asset_id, :class, :brand, :model, :serial_num, :location, :status,
                                :po_date, :po_num, :do_date, :do_num,
                                :invoice_date, :invoice_num, :purchase_cost, :warranty_expiry, :remarks, :created_by
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
                                'class' => '',
                                'brand' => '',
                                'model' => '',
                                'serial_num' => '',
                                'location' => '',
                                'status' => '',
                                'p.o_date' => '',
                                'p.o_num' => '',
                                'd.o_date' => '',
                                'd.o_num' => '',
                                'invoice_date' => '',
                                'invoice_num' => '',
                                'purchase_cost' => '',
                                'remarks' => '',
                                'warranty_expiry' => '',
                            ];

                            foreach ($headers as $index => $columnName) {
                                if ($columnName && array_key_exists($columnName, $rowData) && isset($row[$index])) {
                                    $value = trim($row[$index]);
                                    if (in_array($columnName, ['p.o_date', 'd.o_date', 'invoice_date', 'warranty_expiry']) && !empty($value)) {
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

                            foreach ($requiredHeaders as $requiredColumn) {
                                if ($rowData[$requiredColumn] === '') {
                                    $rowErrors[] = "{$requiredColumn} is required";
                                }
                            }

                            if ($rowData['status'] !== '') {
                                $normalizedStatus = strtoupper(trim($rowData['status']));
                                $normalizedStatus = preg_replace('/\s+/', ' ', $normalizedStatus);
                                $normalizedStatus = trim($normalizedStatus);
                                if (!in_array($normalizedStatus, $allowedStatuses, true)) {
                                    $rowErrors[] = 'Invalid status value';
                                } else {
                                    $rowData['status'] = $normalizedStatus;
                                }
                            }

                            if ($rowData['p.o_date'] !== '') {
                                $date = DateTime::createFromFormat('Y-m-d', $rowData['p.o_date']);
                                if (!$date || $date->format('Y-m-d') !== $rowData['p.o_date']) {
                                    $rowErrors[] = 'Invalid P.O. date format (use YYYY-MM-DD)';
                                }
                            }

                            if ($rowData['invoice_date'] !== '') {
                                $date = DateTime::createFromFormat('Y-m-d', $rowData['invoice_date']);
                                if (!$date || $date->format('Y-m-d') !== $rowData['invoice_date']) {
                                    $rowErrors[] = 'Invalid invoice date format (use YYYY-MM-DD)';
                                }
                            }

                            if ($rowData['purchase_cost'] !== '' && !is_numeric($rowData['purchase_cost'])) {
                                $rowErrors[] = 'Purchase cost must be a number';
                            }

                            if (!empty($rowErrors)) {
                                $skippedRows[] = "Row {$lineNumber}: " . implode('; ', $rowErrors);
                                $lineNumber++;
                                continue;
                            }

                            $statusValue = $rowData['status'];
                            
                            $currentSequence++;
                            $assetId = (int)($prefix . str_pad($currentSequence, 3, '0', STR_PAD_LEFT));
                            $poDate = $rowData['p.o_date'] ?: null;
                            $invoiceDate = $rowData['invoice_date'] ?: null;
                            $purchaseCost = $rowData['purchase_cost'] !== '' ? $rowData['purchase_cost'] : null;

                            $insertStmt->execute([
                                ':asset_id' => $assetId,
                                ':class' => $rowData['class'],
                                ':brand' => $rowData['brand'],
                                ':model' => $rowData['model'],
                                ':serial_num' => $rowData['serial_num'],
                                ':location' => $rowData['location'] ?: null,
                                ':status' => $statusValue,
                                ':po_date' => $poDate,
                                ':po_num' => $rowData['p.o_num'] ?: null,
                                ':do_date' => $rowData['d.o_date'] ?: null,
                                ':do_num' => $rowData['d.o_num'] ?: null,
                                ':invoice_date' => $invoiceDate,
                                ':invoice_num' => $rowData['invoice_num'] ?: null,
                                ':purchase_cost' => $purchaseCost,
                                ':warranty_expiry' => $rowData['warranty_expiry'] ?: null,
                                ':remarks' => $rowData['remarks'] ?: null,
                                ':created_by' => $_SESSION['user_id'],
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
                                    'av', :asset_id, 'CREATE', :changed_by,
                                    NULL, NULL, NULL, :description,
                                    :ip_address, :user_agent
                                )
                            ");
                            $trailStmt->execute([
                                ':asset_id' => $firstAssetId,
                                ':changed_by' => $_SESSION['user_id'],
                                ':description' => "Bulk CSV import: Created {$importedCount} AV asset(s) via CSV import",
                                ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                                ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                            ]);
                        }
                    }
                } catch (PDOException $e) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        if (empty($errors)) {
                            $errors[] = 'Unable to import CSV right now. Please try again.';
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
    <title>Import AV Assets (CSV) - UniKL RCMP IT Inventory</title>
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
            color: #6c5ce7;
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
            min-width: 600px;
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
            <h1>Import AV Assets</h1>
            <p>Upload a CSV file to add multiple audio-visual assets at once. Follow the template to make sure every record is captured correctly.</p>
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
                        <li>Ensure the header row matches the template exactly (e.g., "ASSET_ID", "SERIAL_NUMBER").</li>
                        <li>Required columns: CLASS, BRAND, MODEL, SERIAL_NUMBER, STATUS.</li>
                        <li>Optional columns: ASSET_ID, LOCATION, REMARKS, PO_DATE, PO_NUMBER, DO_DATE, DO_NO, INVOICE_DATE, INVOICE_NO, PURCHASE_COST.</li>
                        <li>Valid STATUS values: DEPLOY, FAULTY, DISPOSE, RESERVED, MAINTENANCE, NON-ACTIVE, LOST, AVAILABLE, UNAVAILABLE.</li>
                        <li>Date columns (PO_DATE, DO_DATE, INVOICE_DATE) should use YYYY-MM-DD format.</li>
                        <li>Strip sensitive credentials from exports before uploading.</li>
                    </ul>
                </div>

                <div class="guidelines">
                    <h3>Column template</h3>
                    <div class="sample-table-wrapper">
                        <table class="sample-table">
                            <thead>
                                <tr>
                                    <th>ASSET_ID</th>
                                    <th>CLASS</th>
                                    <th>BRAND</th>
                                    <th>MODEL</th>
                                    <th>SERIAL_NUMBER</th>
                                    <th>LOCATION</th>
                                    <th>REMARKS</th>
                                    <th>STATUS</th>
                                    <th>PO_DATE</th>
                                    <th>PO_NUMBER</th>
                                    <th>DO_DATE</th>
                                    <th>DO_NO</th>
                                    <th>INVOICE_DATE</th>
                                    <th>INVOICE_NO</th>
                                    <th>PURCHASE_COST</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>1001</td>
                                    <td>projector</td>
                                    <td>Epson</td>
                                    <td>EB-X06</td>
                                    <td>SN1234567</td>
                                    <td>Lecture Hall A</td>
                                    <td>Main projector for hall</td>
                                    <td>DEPLOY</td>
                                    <td>2024-01-15</td>
                                    <td>PO-2024-001</td>
                                    <td>2024-01-20</td>
                                    <td>DO-2024-001</td>
                                    <td>2024-01-25</td>
                                    <td>INV-2024-001</td>
                                    <td>5000.00</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <small style="color:#636e72;">Headers can be uppercase with underscores as shown; the importer will map them to the correct fields.</small>
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

