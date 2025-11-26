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
$allowedStatuses = ['AVAILABLE', 'UNAVAIBLE', 'MAINTENANCE', 'DISPOSED'];
$requiredHeaders = ['serial', 'model', 'brand', 'status'];
$optionalHeaders = ['mac_add', 'ip_add', 'building', 'level', 'remarks'];

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
                    return strtolower(str_replace([' ', '-'], '_', trim((string)$value)));
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
                            INSERT INTO net_assets (
                                serial, model, brand, mac_add, ip_add,
                                building, level, status, remarks, created_by
                            ) VALUES (
                                :serial, :model, :brand, :mac_add, :ip_add,
                                :building, :level, :status, :remarks, :created_by
                            )
                        ");

                        while (($row = fgetcsv($handle)) !== false) {
                            $rawValues = array_map('trim', $row);
                            if (count(array_filter($rawValues, fn($value) => $value !== '')) === 0) {
                                $lineNumber++;
                                continue;
                            }

                            $rowData = [
                                'serial' => '',
                                'model' => '',
                                'brand' => '',
                                'mac_add' => '',
                                'ip_add' => '',
                                'building' => '',
                                'level' => '',
                                'status' => '',
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

                            if ($rowData['status'] !== '' && !in_array(strtolower($rowData['status']), $allowedStatuses, true)) {
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

                            if (!empty($rowErrors)) {
                                $skippedRows[] = "Row {$lineNumber}: " . implode('; ', $rowErrors);
                                $lineNumber++;
                                continue;
                            }

                            $statusValue = strtolower($rowData['status']);

                            $insertStmt->execute([
                                ':serial' => $rowData['serial'],
                                ':model' => $rowData['model'],
                                ':brand' => $rowData['brand'],
                                ':mac_add' => $rowData['mac_add'] ?: null,
                                ':ip_add' => $rowData['ip_add'] ?: null,
                                ':building' => $rowData['building'] ?: null,
                                ':level' => $rowData['level'] ?: null,
                                ':status' => $statusValue,
                                ':remarks' => $rowData['remarks'] ?: null,
                                ':created_by' => $_SESSION['user_id'],
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
    <title>Import Network Assets (CSV) - UniKL RCMP IT Inventory</title>
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

        .sample-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
            font-size: 0.9rem;
        }

        .sample-table th,
        .sample-table td {
            border: 1px solid rgba(0, 0, 0, 0.1);
            padding: 10px;
            text-align: left;
            background: #fff;
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
                        <li>Ensure headers match the template (lowercase, underscores).</li>
                        <li>Required columns: serial, model, brand, status.</li>
                        <li>Optional columns: mac_add, ip_add, building, level, remarks.</li>
                        <li>Strip sensitive credentials from exports before uploading.</li>
                    </ul>
                </div>

                <div class="guidelines">
                    <h3>Column template</h3>
                    <table class="sample-table">
                        <thead>
                            <tr>
                                <th>serial</th>
                                <th>model</th>
                                <th>brand</th>
                                <th>mac_add</th>
                                <th>ip_add</th>
                                <th>building</th>
                                <th>level</th>
                                <th>status</th>
                                <th>remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>SN4452331</td>
                                <td>Catalyst 9300</td>
                                <td>Cisco</td>
                                <td>AA:BB:CC:DD:EE:FF</td>
                                <td>10.10.10.5</td>
                                <td>Data Center</td>
                                <td>Level 2</td>
                                <td>available</td>
                                <td>Core switch for lab</td>
                            </tr>
                        </tbody>
                    </table>
                    <small style="color:#636e72;">Use lowercase headers with underscores to match the schema.</small>
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

