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
$allowedStatuses = ['ONLINE', 'OFFLINE','FAULTY', 'MAINTENANCE', 'DISPOSE'];
$requiredHeaders = ['serial', 'model', 'brand', 'status'];
$headerMap = [
    'assetid' => 'asset_id',
    'serial' => 'serial',
    'model' => 'model',
    'brand' => 'brand',
    'macaddress' => 'mac_add',
    'macadd' => 'mac_add',
    'ipaddress' => 'ip_add',
    'ipadd' => 'ip_add',
    'building' => 'building',
    'level' => 'level',
    'status' => 'status',
    'remarks' => 'remarks',
    'podate' => 'p.o_date',
    'ponumber' => 'p.o_num',
    'p0number' => 'p.o_num',
    'ponum' => 'p.o_num',
    'dodate' => 'd.o_date',
    'dono' => 'd.o_num',
    'donumber' => 'd.o_num',
    'invoicedate' => 'invoice_date',
    'invoiceno' => 'invoice_num',
    'purchasecost' => 'purchase_cost'
];

if (!function_exists('detectCsvDelimiter')) {
    function detectCsvDelimiter(string $line): string
    {
        $delimiters = [',', ';', "\t", '|'];
        $best = ',';
        $max = 0;
        foreach ($delimiters as $delimiter) {
            $count = substr_count($line, $delimiter);
            if ($count > $max) {
                $best = $delimiter;
                $max = $count;
            }
        }
        return $best;
    }
}

if (!function_exists('normalizeHeaderKey')) {
    function normalizeHeaderKey(string $header): string
    {
        $header = preg_replace('/^\xEF\xBB\xBF/', '', $header);
        $header = strtolower(trim($header));
        return preg_replace('/[^a-z0-9]/', '', $header);
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
                $firstLine = fgets($handle);
                if ($firstLine === false) {
                    $errors[] = 'The CSV file is empty.';
                } else {
                    $delimiter = detectCsvDelimiter($firstLine);
                    rewind($handle);
                    $headerRow = fgetcsv($handle, 0, $delimiter);

                    if ($headerRow === false) {
                        $errors[] = 'Unable to read CSV headers.';
                    } else {
                        $headers = [];
                        foreach ($headerRow as $index => $header) {
                            $normalized = normalizeHeaderKey($header);
                            $headers[$index] = $headerMap[$normalized] ?? '';
                        }

                        $availableColumns = array_unique(array_filter($headers));
                        foreach ($requiredHeaders as $required) {
                            if (!in_array($required, $availableColumns, true)) {
                                $errors[] = "Missing required column: " . strtoupper($required);
                            }
                        }
                    }
                }

                if (empty($errors)) {
                    try {
                        $pdo->beginTransaction();
                        $lineNumber = 2;
                        $insertStmt = $pdo->prepare("
                            INSERT INTO net_assets (
                                serial, model, brand, mac_add, ip_add,
                                building, level, status, `PO_DATE`, `PO_NUM`,
                                `DO_DATE`, `DO_NUM`, `INVOICE_DATE`, `INVOICE_NUM`,
                                `PURCHASE_COST`, remarks, created_by
                            ) VALUES (
                                :serial, :model, :brand, :mac_add, :ip_add,
                                :building, :level, :status, :po_date, :po_num,
                                :do_date, :do_num, :invoice_date,  :invoice_num,
                                :purchase_cost, :remarks, :created_by
                            )
                        ");
                        $importedAssetIds = [];

                        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                            $rawValues = array_map('trim', $row);
                            if (count(array_filter($rawValues, fn($value) => $value !== '')) === 0) {
                                $lineNumber++;
                                continue;
                            }

                            $rowData = [
                                'asset_id' => '',
                                'serial' => '',
                                'model' => '',
                                'brand' => '',
                                'mac_add' => '',
                                'ip_add' => '',
                                'building' => '',
                                'level' => '',
                                'status' => '',
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

                            if ($rowData['ip_add'] !== '' && !filter_var($rowData['ip_add'], FILTER_VALIDATE_IP)) {
                                $rowErrors[] = 'Invalid IP address';
                            }

                            if ($rowData['mac_add'] !== '') {
                                $mac = strtoupper(str_replace('-', ':', $rowData['mac_add']));
                                if (!preg_match('/^([0-9A-F]{2}:){5}[0-9A-F]{2}$/', $mac)) {
                                    $rowErrors[] = 'Invalid MAC address';
                                } else {
                                    $rowData['mac_add'] = $mac;
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

                            $statusValue = strtoupper($rowData['status']);
                            
                            $poDate = $rowData['p.o_date'] ?: null;
                            $invoiceDate = $rowData['invoice_date'] ?: null;
                            $purchaseCost = $rowData['purchase_cost'] !== '' ? $rowData['purchase_cost'] : null;

                            $insertStmt->execute([
                                ':serial' => $rowData['serial'],
                                ':model' => $rowData['model'],
                                ':brand' => $rowData['brand'],
                                ':mac_add' => $rowData['mac_add'] ?: null,
                                ':ip_add' => $rowData['ip_add'] ?: null,
                                ':building' => $rowData['building'] ?: null,
                                ':level' => $rowData['level'] ?: null,
                                ':status' => $statusValue,
                                ':po_date' => $poDate,
                                ':po_num' => $rowData['p.o_num'] ?: null,
                                ':do_date' => $rowData['d.o_date'] ?: null,
                                ':do_num' => $rowData['d.o_num'] ?: null,
                                ':invoice_date' => $invoiceDate,
                                ':invoice_num' => $rowData['invoice_num'] ?: null,
                                ':purchase_cost' => $purchaseCost,
                                ':remarks' => $rowData['remarks'] ?: null,
                                ':created_by' => $_SESSION['user_id'],
                            ]);

                            $newAssetId = (int)$pdo->lastInsertId();
                            $importedAssetIds[] = $newAssetId;

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
                                    'network', :asset_id, 'CREATE', :changed_by,
                                    NULL, NULL, NULL, :description,
                                    :ip_address, :user_agent
                                )
                            ");
                            $trailStmt->execute([
                                ':asset_id' => $firstAssetId,
                                ':changed_by' => $_SESSION['user_id'],
                                ':description' => "Bulk CSV import: Created {$importedCount} network asset(s) via CSV import",
                                ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                                ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                            ]);
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
    <title>Import Network Assets (CSV) - UniKL RCMP IT Inventory</title>
    <link rel="icon" type="image/png" href="../public/rcmp.png">
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
            color: #00b894;
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
            <h1>Import Network Assets</h1>
            <p>Upload routers, switches, wireless gear, and controllers in bulk using the CSV template.</p>
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
                        <li>Ensure the header row matches the template exactly (e.g., "ASSET ID", "MAC ADDRESS").</li>
                        <li>Required columns: SERIAL, MODEL, BRAND, STATUS.</li>
                        <li>Optional columns: ASSET ID, MAC ADDRESS, IP ADDRESS, BUILDING, LEVEL, REMARKS, P.O DATE, P.0 NUMBER, D.O DATE, D.O NO, INVOICE DATE, INVOICE NO, PURCHASE COST.</li>
                        <li>Date columns (P.O DATE, D.O DATE, INVOICE DATE) should use YYYY-MM-DD format.</li>
                        <li>Strip sensitive credentials from exports before uploading.</li>
                    </ul>
                </div>

                <div class="guidelines">
                    <h3>Column template</h3>
                    <div class="sample-table-wrapper">
                        <table class="sample-table">
                            <thead>
                                <tr>
                                    <th>ASSET ID</th>
                                    <th>SERIAL</th>
                                    <th>MODEL</th>
                                    <th>BRAND</th>
                                    <th>MAC ADDRESS</th>
                                    <th>IP ADDRESS</th>
                                    <th>BUILDING</th>
                                    <th>LEVEL</th>
                                    <th>STATUS</th>
                                    <th>REMARKS</th>
                                    <th>P.O DATE</th>
                                    <th>P.0 NUMBER</th>
                                    <th>D.O DATE</th>
                                    <th>D.O NO</th>
                                    <th>INVOICE DATE</th>
                                    <th>INVOICE NO</th>
                                    <th>PURCHASE COST</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>1001</td>
                                    <td>SN4452331</td>
                                    <td>Catalyst 9300</td>
                                    <td>Cisco</td>
                                    <td>AA:BB:CC:DD:EE:FF</td>
                                    <td>10.10.10.5</td>
                                    <td>Data Center</td>
                                    <td>Level 2</td>
                                    <td>AVAILABLE</td>
                                    <td>Core switch for lab</td>
                                    <td>2024-01-15</td>
                                    <td>PO-2024-001</td>
                                    <td>2024-01-20</td>
                                    <td>DO-2024-001</td>
                                    <td>2024-01-25</td>
                                    <td>INV-2024-001</td>
                                    <td>15000.00</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <small style="color:#636e72;">Headers can be uppercase with spaces as shown; the importer will map them to the correct fields.</small>
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

