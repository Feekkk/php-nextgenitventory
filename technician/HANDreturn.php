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

// Get asset information from URL parameters
$assetTypeParam = trim($_GET['asset_type'] ?? '');
$assetIdParam = trim($_GET['asset_id'] ?? '');

$handoverDetails = null;
$assetDetails = null;

// Fetch asset details first
if ($assetTypeParam && $assetIdParam && is_numeric($assetIdParam)) {
    if ($assetTypeParam === 'laptop_desktop') {
        $stmt = $pdo->prepare("
            SELECT asset_id, serial_num, brand, model, category, status, staff_id,
                   processor, memory, storage, os, gpu
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
    }
    
    if (isset($stmt)) {
        $stmt->execute([$assetIdParam]);
        $assetDetails = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

// Fetch handover details if asset exists
if ($assetDetails && $assetTypeParam && $assetIdParam) {
    $stmt = $pdo->prepare("
        SELECT h.*, 
               s.staff_name, s.email, s.faculty
        FROM handover h
        LEFT JOIN staff_list s ON h.staff_id = s.staff_id
        WHERE h.asset_type = ? AND h.asset_id = ? AND (h.status = 'active' OR h.status = 'returned')
        ORDER BY h.handover_date DESC
        LIMIT 1
    ");
    $stmt->execute([$assetTypeParam, $assetIdParam]);
    $handoverDetails = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // If no handover record but asset has staff_id, get staff info
    if (!$handoverDetails && isset($assetDetails['staff_id']) && $assetDetails['staff_id']) {
        $stmt = $pdo->prepare("SELECT staff_name, email, faculty FROM staff_list WHERE staff_id = ?");
        $stmt->execute([$assetDetails['staff_id']]);
        $staffInfo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($staffInfo) {
            $handoverDetails = [
                'handover_id' => null,
                'staff_id' => $assetDetails['staff_id'],
                'staff_name' => $staffInfo['staff_name'],
                'email' => $staffInfo['email'],
                'faculty' => $staffInfo['faculty'],
                'handover_date' => null,
                'handover_location' => null,
                'status' => 'active',
                'no_handover_record' => true
            ];
        }
    }
}

// Validate asset exists
if (!$assetDetails) {
    $errors[] = 'Asset not found. Please check the asset ID and try again.';
}

// Process return form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $assetDetails) {
    $returnDate = trim($_POST['return_date'] ?? '');
    $returnCondition = trim($_POST['return_condition'] ?? '');
    $returnNotes = trim($_POST['return_notes'] ?? '');
    $conditionCheck = isset($_POST['condition_check']) ? (int)$_POST['condition_check'] : 0;
    $newAssetStatus = trim($_POST['new_status'] ?? '');
    
    // Component conditions and statuses
    $desktopCondition = trim($_POST['desktop_condition'] ?? 'OK');
    $desktopStatus = trim($_POST['desktop_status'] ?? 'RETURN');
    $harddiskCondition = trim($_POST['harddisk_condition'] ?? 'OK');
    $harddiskStatus = trim($_POST['harddisk_status'] ?? 'RETURN');
    $monitorCondition = trim($_POST['monitor_condition'] ?? 'OK');
    $monitorStatus = trim($_POST['monitor_status'] ?? 'RETURN');
    $mouseCondition = trim($_POST['mouse_condition'] ?? 'OK');
    $mouseStatus = trim($_POST['mouse_status'] ?? 'RETURN');
    $keyboardCondition = trim($_POST['keyboard_condition'] ?? 'OK');
    $keyboardStatus = trim($_POST['keyboard_status'] ?? 'RETURN');
    
    // Get technician info for received_by
    $technicianStmt = $pdo->prepare("SELECT tech_name FROM technician WHERE id = ?");
    $technicianStmt->execute([$_SESSION['user_id']]);
    $technicianInfo = $technicianStmt->fetch(PDO::FETCH_ASSOC);
    $receivedByName = $technicianInfo['tech_name'] ?? 'IT Department';
    $receivedByDesignation = 'IT TECHNICIAN';
    
    // Validation
    if (empty($returnDate)) {
        $errors[] = 'Return date is required.';
    }
    
    if (empty($newAssetStatus)) {
        $errors[] = 'Please select the new status for the asset.';
    }
    
    // Set return condition based on component conditions
    if (empty($returnCondition)) {
        $conditions = [$desktopCondition, $harddiskCondition, $monitorCondition, $mouseCondition, $keyboardCondition];
        $returnCondition = in_array('Damage', $conditions) ? 'Damaged' : 'Good';
    }
    
    // Validate return date is not in the future
    if ($returnDate && strtotime($returnDate) > time()) {
        $errors[] = 'Return date cannot be in the future.';
    }
    
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // Update handover record if it exists
            if ($handoverDetails && isset($handoverDetails['handover_id']) && $handoverDetails['handover_id']) {
                $updateHandoverStmt = $pdo->prepare("
                    UPDATE handover 
                    SET status = 'returned',
                        return_date = ?,
                        returned_by = ?,
                        return_condition = ?,
                        return_notes = ?,
                        received_by_name = ?,
                        received_by_designation = ?,
                        desktop_condition = ?,
                        desktop_status = ?,
                        harddisk_condition = ?,
                        harddisk_status = ?,
                        monitor_condition = ?,
                        monitor_status = ?,
                        mouse_condition = ?,
                        mouse_status = ?,
                        keyboard_condition = ?,
                        keyboard_status = ?,
                        handover_notes = CONCAT(COALESCE(handover_notes, ''), 
                            CASE WHEN handover_notes IS NOT NULL AND handover_notes != '' THEN '\n\n--- RETURN ---\n' ELSE '--- RETURN ---\n' END,
                            'Return Date: ', ?, '\n',
                            'Return Condition: ', ?, '\n',
                            'Return Notes: ', COALESCE(?, 'None'))
                    WHERE handover_id = ?
                ");
                $updateHandoverStmt->execute([
                    $returnDate,
                    $_SESSION['user_id'],
                    $returnCondition,
                    $returnNotes ?: null,
                    $receivedByName,
                    $receivedByDesignation,
                    $desktopCondition,
                    $desktopStatus,
                    $harddiskCondition,
                    $harddiskStatus,
                    $monitorCondition,
                    $monitorStatus,
                    $mouseCondition,
                    $mouseStatus,
                    $keyboardCondition,
                    $keyboardStatus,
                    $returnDate,
                    $returnCondition,
                    $returnNotes ?: null,
                    $handoverDetails['handover_id']
                ]);
            }
            
            // Update asset status
            $oldStatus = $assetDetails['status'];
            if ($assetTypeParam === 'laptop_desktop') {
                $updateAssetStmt = $pdo->prepare("
                    UPDATE laptop_desktop_assets 
                    SET status = ?, staff_id = NULL 
                    WHERE asset_id = ?
                ");
                $updateAssetStmt->execute([$newAssetStatus, $assetIdParam]);
            } elseif ($assetTypeParam === 'av') {
                $updateAssetStmt = $pdo->prepare("
                    UPDATE av_assets 
                    SET status = ? 
                    WHERE asset_id = ?
                ");
                $updateAssetStmt->execute([$newAssetStatus, $assetIdParam]);
            } elseif ($assetTypeParam === 'network') {
                $updateAssetStmt = $pdo->prepare("
                    UPDATE net_assets 
                    SET status = ? 
                    WHERE asset_id = ?
                ");
                $updateAssetStmt->execute([$newAssetStatus, $assetIdParam]);
            }
            
            // Create asset trail record
            $handoverIdText = ($handoverDetails && isset($handoverDetails['handover_id']) && $handoverDetails['handover_id']) 
                ? "Handover ID: {$handoverDetails['handover_id']}, " 
                : "No handover record, ";
            
            $trailStmt = $pdo->prepare("
                INSERT INTO asset_trails (
                    asset_type, asset_id, action_type, changed_by,
                    field_name, old_value, new_value, description,
                    ip_address, user_agent
                ) VALUES (
                    :asset_type, :asset_id, 'STATUS_CHANGE', :changed_by,
                    'status', :old_status, :new_status, :description,
                    :ip_address, :user_agent
                )
            ");
            
            $trailStmt->execute([
                ':asset_type' => $assetTypeParam,
                ':asset_id' => (int)$assetIdParam,
                ':changed_by' => $_SESSION['user_id'],
                ':old_status' => $oldStatus,
                ':new_status' => $newAssetStatus,
                ':description' => "Asset returned. {$handoverIdText}Return Date: {$returnDate}, Condition: {$returnCondition}",
                ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            ]);
            
            $pdo->commit();
            
            // Refresh data to get updated information
            if ($assetTypeParam === 'laptop_desktop') {
                $stmt = $pdo->prepare("SELECT asset_id, serial_num, brand, model, category, status FROM laptop_desktop_assets WHERE asset_id = ?");
            } elseif ($assetTypeParam === 'av') {
                $stmt = $pdo->prepare("SELECT asset_id, serial_num, brand, model, class as category, status FROM av_assets WHERE asset_id = ?");
            } elseif ($assetTypeParam === 'network') {
                $stmt = $pdo->prepare("SELECT asset_id, serial, brand, model, status FROM net_assets WHERE asset_id = ?");
            }
            if (isset($stmt)) {
                $stmt->execute([$assetIdParam]);
                $assetDetails = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            
            // Refresh handover details with new return information
            if ($handoverDetails && isset($handoverDetails['handover_id']) && $handoverDetails['handover_id']) {
                $stmt = $pdo->prepare("
                    SELECT h.*, 
                           s.staff_name, s.email, s.faculty
                    FROM handover h
                    LEFT JOIN staff_list s ON h.staff_id = s.staff_id
                    WHERE h.handover_id = ?
                ");
                $stmt->execute([$handoverDetails['handover_id']]);
                $handoverDetails = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            
            // Generate PDF and send email
            if ($handoverDetails && !empty($handoverDetails['email'])) {
                require_once '../services/return_pdf_generator.php';
                require_once '../services/mail_config.php';
                
                // Prepare PDF data (use refreshed handover details for component info)
                $pdfData = [
                    'staff_name' => $handoverDetails['staff_name'] ?? '',
                    'staff_id' => $handoverDetails['staff_id'] ?? '',
                    'staff_designation' => $handoverDetails['staff_designation'] ?? '',
                    'staff_department' => $handoverDetails['faculty'] ?? '',
                    'asset_id' => $assetDetails['asset_id'] ?? '',
                    'category' => $assetDetails['category'] ?? '',
                    'brand' => $assetDetails['brand'] ?? '',
                    'model' => $assetDetails['model'] ?? '',
                    'serial_num' => $assetDetails['serial_num'] ?? $assetDetails['serial'] ?? '',
                    'return_date' => $handoverDetails['return_date'] ? date('d.m.Y', strtotime($handoverDetails['return_date'])) : date('d.m.Y', strtotime($returnDate)),
                    'handover_date' => $handoverDetails['handover_date'] ?? '',
                    'return_notes' => $handoverDetails['return_notes'] ?? $returnNotes,
                    'desktop_condition' => $handoverDetails['desktop_condition'] ?? $desktopCondition,
                    'desktop_status' => $handoverDetails['desktop_status'] ?? $desktopStatus,
                    'harddisk_condition' => $handoverDetails['harddisk_condition'] ?? $harddiskCondition,
                    'harddisk_status' => $handoverDetails['harddisk_status'] ?? $harddiskStatus,
                    'monitor_condition' => $handoverDetails['monitor_condition'] ?? $monitorCondition,
                    'monitor_status' => $handoverDetails['monitor_status'] ?? $monitorStatus,
                    'mouse_condition' => $handoverDetails['mouse_condition'] ?? $mouseCondition,
                    'mouse_status' => $handoverDetails['mouse_status'] ?? $mouseStatus,
                    'keyboard_condition' => $handoverDetails['keyboard_condition'] ?? $keyboardCondition,
                    'keyboard_status' => $handoverDetails['keyboard_status'] ?? $keyboardStatus,
                    'received_by_name' => $handoverDetails['received_by_name'] ?? $receivedByName,
                    'received_by_designation' => $handoverDetails['received_by_designation'] ?? $receivedByDesignation
                ];
                
                $pdfContent = generateReturnPDF($pdfData);
                
                if ($pdfContent) {
                    $tempFile = sys_get_temp_dir() . '/return_' . ($handoverDetails['handover_id'] ?? '') . '_' . time() . '.pdf';
                    file_put_contents($tempFile, $pdfContent);
                    
                    $emailBody = "
                        <html>
                        <head>
                            <style>
                                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                                .header { background: #1a1a2e; color: white; padding: 20px; text-align: center; }
                                .content { padding: 20px; }
                                .footer { background: #f5f5f5; padding: 15px; text-align: center; font-size: 12px; color: #666; }
                            </style>
                        </head>
                        <body>
                            <div class='header'>
                                <h2>Asset Return Confirmation</h2>
                            </div>
                            <div class='content'>
                                <p>Dear {$handoverDetails['staff_name']},</p>
                                <p>This email confirms that the following asset has been returned:</p>
                                <ul>
                                    <li><strong>Asset ID:</strong> {$assetDetails['asset_id']}</li>
                                    <li><strong>Type:</strong> " . ucwords(str_replace('_', ' ', $assetTypeParam)) . "</li>
                                    <li><strong>Category:</strong> " . ($assetDetails['category'] ?? 'N/A') . "</li>
                                    <li><strong>Brand:</strong> " . ($assetDetails['brand'] ?? 'N/A') . "</li>
                                    <li><strong>Model:</strong> " . ($assetDetails['model'] ?? 'N/A') . "</li>
                                    <li><strong>Serial Number:</strong> " . ($assetDetails['serial_num'] ?? $assetDetails['serial'] ?? 'N/A') . "</li>
                                    <li><strong>Return Date:</strong> " . date('d M Y', strtotime($returnDate)) . "</li>
                                    <li><strong>Return Condition:</strong> {$returnCondition}</li>
                                </ul>
                                <p>Please find attached the complete return document for your records.</p>
                                <p>If you have any questions or concerns, please contact the IT Department.</p>
                            </div>
                            <div class='footer'>
                                <p>UNIKL RCMP IT Inventory System</p>
                                <p>This is an automated email. Please do not reply.</p>
                            </div>
                        </body>
                        </html>
                    ";
                    
                    $attachments = [[
                        'path' => $tempFile,
                        'name' => 'Return_Document_' . ($handoverDetails['handover_id'] ?? $assetIdParam) . '_' . date('Ymd') . '.pdf',
                        'type' => 'application/pdf'
                    ]];
                    
                    $emailSent = sendEmail(
                        $handoverDetails['email'],
                        'Asset Return Confirmation - Asset ID: ' . $assetDetails['asset_id'],
                        $emailBody,
                        $attachments
                    );
                    
                    if ($emailSent) {
                        $successMessage = 'Asset return processed successfully! Confirmation email with PDF has been sent.';
                    } else {
                        $successMessage = 'Asset return processed successfully! However, email could not be sent.';
                    }
                    
                    if (file_exists($tempFile)) {
                        unlink($tempFile);
                    }
                } else {
                    $successMessage = 'Asset return processed successfully! However, PDF could not be generated.';
                }
            } else {
                $successMessage = 'Asset return processed successfully.';
            }
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log('HANDreturn.php Error: ' . $e->getMessage());
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}

// Get status options based on asset type
$statusOptions = [];
if ($assetTypeParam === 'laptop_desktop') {
    $statusOptions = ['ACTIVE', 'FAULTY', 'MAINTENANCE', 'RESERVED', 'NON-ACTIVE'];
} elseif ($assetTypeParam === 'av') {
    $statusOptions = ['ACTIVE', 'FAULTY', 'MAINTENANCE', 'RESERVED', 'DISPOSED'];
} elseif ($assetTypeParam === 'network') {
    $statusOptions = ['ACTIVE', 'FAULTY', 'MAINTENANCE', 'RESERVED'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Return Asset - UniKL RCMP IT Inventory</title>
    <link rel="icon" type="image/png" href="../public/rcmp.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/TechDashboard.css">
    <style>
        .return-page-container {
            max-width: 1000px;
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

        .return-form-card {
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(0, 0, 0, 0.05);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
        }

        .info-section {
            background: rgba(26, 26, 46, 0.03);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
        }

        .info-section h3 {
            margin: 0 0 15px 0;
            font-size: 1.1rem;
            color: #1a1a2e;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }

        .info-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .info-label {
            font-size: 0.85rem;
            color: #636e72;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-value {
            font-size: 1rem;
            color: #2d3436;
            font-weight: 600;
        }

        .form-section {
            margin-bottom: 30px;
        }

        .form-section-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #1a1a2e;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid rgba(26, 26, 46, 0.1);
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

        .form-group.full-width {
            grid-column: 1 / -1;
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
            min-height: 100px;
        }

        .condition-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 10px;
        }

        .condition-option {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px;
            border: 2px solid rgba(0, 0, 0, 0.1);
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .condition-option:hover {
            border-color: #1a1a2e;
            background: rgba(26, 26, 46, 0.02);
        }

        .condition-option input[type="radio"] {
            margin: 0;
            cursor: pointer;
        }

        .condition-option label {
            margin: 0;
            cursor: pointer;
            flex: 1;
        }

        .checkbox-group {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 15px;
            background: rgba(253, 203, 110, 0.1);
            border: 2px solid rgba(253, 203, 110, 0.3);
            border-radius: 10px;
            margin-top: 10px;
        }

        .checkbox-group input[type="checkbox"] {
            margin-top: 4px;
            cursor: pointer;
        }

        .checkbox-group label {
            margin: 0;
            cursor: pointer;
            flex: 1;
        }

        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid rgba(0, 0, 0, 0.1);
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

        .alert-warning {
            background: rgba(253, 203, 110, 0.1);
            border: 1px solid rgba(253, 203, 110, 0.2);
            color: #e1a500;
        }

        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .badge.active {
            background: rgba(0, 184, 148, 0.15);
            color: #00b894;
        }

        .badge.returned {
            background: rgba(108, 117, 125, 0.15);
            color: #6c757d;
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

    <div class="return-page-container">
        <div class="page-header">
            <h1>Return Asset</h1>
            <p>Process the return of a loaned asset and update its condition and status.</p>
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

        <?php if ($assetDetails) : ?>
            <div class="return-form-card">
                <div class="info-section">
                    <h3>Asset Information</h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Asset ID</span>
                            <span class="info-value"><?php echo htmlspecialchars($assetDetails['asset_id']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Serial Number</span>
                            <span class="info-value"><?php echo htmlspecialchars($assetDetails['serial_num'] ?? $assetDetails['serial'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Brand & Model</span>
                            <span class="info-value"><?php echo htmlspecialchars(trim(($assetDetails['brand'] ?? '') . ' ' . ($assetDetails['model'] ?? ''))); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Category</span>
                            <span class="info-value"><?php echo htmlspecialchars($assetDetails['category'] ?? 'N/A'); ?></span>
                        </div>
                        <?php if ($handoverDetails) : ?>
                            <div class="info-item">
                                <span class="info-label">Recipient</span>
                                <span class="info-value"><?php echo htmlspecialchars($handoverDetails['staff_name'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Faculty/Department</span>
                                <span class="info-value"><?php echo htmlspecialchars($handoverDetails['faculty'] ?? 'N/A'); ?></span>
                            </div>
                            <?php if ($handoverDetails['handover_date']) : ?>
                                <div class="info-item">
                                    <span class="info-label">Handover Date</span>
                                    <span class="info-value"><?php echo date('d M Y', strtotime($handoverDetails['handover_date'])); ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if ($handoverDetails['handover_location']) : ?>
                                <div class="info-item">
                                    <span class="info-label">Handover Location</span>
                                    <span class="info-value"><?php echo htmlspecialchars($handoverDetails['handover_location']); ?></span>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                        <div class="info-item">
                            <span class="info-label">Current Status</span>
                            <span class="info-value">
                                <span class="badge <?php echo ($handoverDetails && $handoverDetails['status'] === 'returned') ? 'returned' : 'active'; ?>">
                                    <?php echo htmlspecialchars($assetDetails['status']); ?>
                                </span>
                            </span>
                        </div>
                    </div>
                </div>

                <?php if ($handoverDetails && isset($handoverDetails['no_handover_record']) && $handoverDetails['no_handover_record']) : ?>
                    <div class="alert alert-warning">
                        <strong>Note:</strong> No handover record found in the system. This asset may have been deployed manually. You can still process the return.
                    </div>
                <?php endif; ?>

                <?php if (!$handoverDetails || ($handoverDetails && $handoverDetails['status'] === 'active')) : ?>
                    <form method="POST" class="return-form">
                        <div class="form-section">
                            <h3 class="form-section-title">Return Details</h3>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="return_date">Return Date <span style="color:#c0392b;">*</span></label>
                                    <input type="date" id="return_date" name="return_date" 
                                           value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="new_status">New Asset Status <span style="color:#c0392b;">*</span></label>
                                    <select id="new_status" name="new_status" required>
                                        <option value="">Select status</option>
                                        <?php foreach ($statusOptions as $status) : ?>
                                            <option value="<?php echo htmlspecialchars($status); ?>">
                                                <?php echo htmlspecialchars($status); ?>
                                            </option>
                                        <?php endforeach; ?>
                </select>
                                </div>
                                <div class="form-group full-width">
                                    <label>Component Condition & Status</label>
                                    <div style="overflow-x: auto; margin-top: 10px;">
                                        <table style="width: 100%; border-collapse: collapse; border: 1px solid rgba(0,0,0,0.1);">
                                            <thead>
                                                <tr style="background: rgba(26, 26, 46, 0.05);">
                                                    <th style="padding: 10px; text-align: left; border: 1px solid rgba(0,0,0,0.1);">Item</th>
                                                    <th style="padding: 10px; text-align: center; border: 1px solid rgba(0,0,0,0.1);">Condition</th>
                                                    <th style="padding: 10px; text-align: center; border: 1px solid rgba(0,0,0,0.1);">Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td style="padding: 10px; border: 1px solid rgba(0,0,0,0.1);"><strong>Desktop</strong></td>
                                                    <td style="padding: 10px; border: 1px solid rgba(0,0,0,0.1); text-align: center;">
                                                        <select name="desktop_condition" style="width: 100%; padding: 6px; border: 1px solid rgba(0,0,0,0.1); border-radius: 6px;">
                                                            <option value="OK" selected>OK</option>
                                                            <option value="Damage">Damage</option>
                                                        </select>
                                                    </td>
                                                    <td style="padding: 10px; border: 1px solid rgba(0,0,0,0.1); text-align: center;">
                                                        <select name="desktop_status" style="width: 100%; padding: 6px; border: 1px solid rgba(0,0,0,0.1); border-radius: 6px;">
                                                            <option value="RETURN" selected>RETURN</option>
                                                            <option value="MISSING">MISSING</option>
                                                        </select>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td style="padding: 10px 10px 10px 30px; border: 1px solid rgba(0,0,0,0.1);">Hard disk</td>
                                                    <td style="padding: 10px; border: 1px solid rgba(0,0,0,0.1); text-align: center;">
                                                        <select name="harddisk_condition" style="width: 100%; padding: 6px; border: 1px solid rgba(0,0,0,0.1); border-radius: 6px;">
                                                            <option value="OK" selected>OK</option>
                                                            <option value="Damage">Damage</option>
                                                        </select>
                                                    </td>
                                                    <td style="padding: 10px; border: 1px solid rgba(0,0,0,0.1); text-align: center;">
                                                        <select name="harddisk_status" style="width: 100%; padding: 6px; border: 1px solid rgba(0,0,0,0.1); border-radius: 6px;">
                                                            <option value="RETURN" selected>RETURN</option>
                                                            <option value="MISSING">MISSING</option>
                                                        </select>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td style="padding: 10px 10px 10px 30px; border: 1px solid rgba(0,0,0,0.1);">Monitor</td>
                                                    <td style="padding: 10px; border: 1px solid rgba(0,0,0,0.1); text-align: center;">
                                                        <select name="monitor_condition" style="width: 100%; padding: 6px; border: 1px solid rgba(0,0,0,0.1); border-radius: 6px;">
                                                            <option value="OK" selected>OK</option>
                                                            <option value="Damage">Damage</option>
                                                        </select>
                                                    </td>
                                                    <td style="padding: 10px; border: 1px solid rgba(0,0,0,0.1); text-align: center;">
                                                        <select name="monitor_status" style="width: 100%; padding: 6px; border: 1px solid rgba(0,0,0,0.1); border-radius: 6px;">
                                                            <option value="RETURN" selected>RETURN</option>
                                                            <option value="MISSING">MISSING</option>
                                                        </select>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td style="padding: 10px 10px 10px 30px; border: 1px solid rgba(0,0,0,0.1);">Mouse</td>
                                                    <td style="padding: 10px; border: 1px solid rgba(0,0,0,0.1); text-align: center;">
                                                        <select name="mouse_condition" style="width: 100%; padding: 6px; border: 1px solid rgba(0,0,0,0.1); border-radius: 6px;">
                                                            <option value="OK" selected>OK</option>
                                                            <option value="Damage">Damage</option>
                                                        </select>
                                                    </td>
                                                    <td style="padding: 10px; border: 1px solid rgba(0,0,0,0.1); text-align: center;">
                                                        <select name="mouse_status" style="width: 100%; padding: 6px; border: 1px solid rgba(0,0,0,0.1); border-radius: 6px;">
                                                            <option value="RETURN" selected>RETURN</option>
                                                            <option value="MISSING">MISSING</option>
                                                        </select>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td style="padding: 10px 10px 10px 30px; border: 1px solid rgba(0,0,0,0.1);">Keyboard</td>
                                                    <td style="padding: 10px; border: 1px solid rgba(0,0,0,0.1); text-align: center;">
                                                        <select name="keyboard_condition" style="width: 100%; padding: 6px; border: 1px solid rgba(0,0,0,0.1); border-radius: 6px;">
                                                            <option value="OK" selected>OK</option>
                                                            <option value="Damage">Damage</option>
                                                        </select>
                                                    </td>
                                                    <td style="padding: 10px; border: 1px solid rgba(0,0,0,0.1); text-align: center;">
                                                        <select name="keyboard_status" style="width: 100%; padding: 6px; border: 1px solid rgba(0,0,0,0.1); border-radius: 6px;">
                                                            <option value="RETURN" selected>RETURN</option>
                                                            <option value="MISSING">MISSING</option>
                                                        </select>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <div class="form-group full-width">
                                    <label for="return_notes">Return Notes</label>
                                    <textarea id="return_notes" name="return_notes" 
                                              placeholder="Add any notes about the return, damage, or issues found..."></textarea>
                                </div>
                                <div class="form-group full-width">
                                    <div class="checkbox-group">
                                        <input type="checkbox" id="condition_check" name="condition_check" value="1" required>
                                        <label for="condition_check">
                                            <strong>I confirm that I have physically inspected the asset and verified its condition.</strong>
                                            <br>
                                            <small style="color: #636e72;">This includes checking for physical damage, functionality, and completeness of accessories.</small>
                                        </label>
                                    </div>
                                </div>
            </div>
        </div>

                        <div class="form-actions">
                            <button type="button" class="btn btn-secondary" onclick="window.location.href='LAPTOPpage.php'">Cancel</button>
                            <button type="submit" class="btn btn-primary">Process Return</button>
                        </div>
                    </form>
                <?php else : ?>
                    <div class="info-section">
                        <h3>Return Information</h3>
                        <div class="info-grid">
                        <?php if (isset($handoverDetails['return_date']) && $handoverDetails['return_date']) : ?>
                                <div class="info-item">
                                    <span class="info-label">Return Date</span>
                                    <span class="info-value"><?php echo date('d M Y', strtotime($handoverDetails['return_date'])); ?></span>
                                </div>
                        <?php endif; ?>
                            <?php if (!empty($handoverDetails['return_condition'])) : ?>
                                <div class="info-item">
                                    <span class="info-label">Return Condition</span>
                                    <span class="info-value"><?php echo htmlspecialchars($handoverDetails['return_condition']); ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($handoverDetails['received_by_name'])) : ?>
                                <div class="info-item">
                                    <span class="info-label">Received By</span>
                                    <span class="info-value"><?php echo htmlspecialchars($handoverDetails['received_by_name']); ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($handoverDetails['return_notes'])) : ?>
                                <div class="info-item" style="grid-column: 1 / -1;">
                                    <span class="info-label">Return Notes</span>
                                    <span class="info-value" style="white-space: pre-wrap;"><?php echo htmlspecialchars($handoverDetails['return_notes']); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($handoverDetails['desktop_condition']) || !empty($handoverDetails['harddisk_condition'])) : ?>
                            <div style="margin-top: 20px;">
                                <h4 style="margin: 0 0 10px 0; font-size: 0.95rem; color: #1a1a2e;">Component Status</h4>
                                <div style="overflow-x: auto;">
                                    <table style="width: 100%; border-collapse: collapse; border: 1px solid rgba(0,0,0,0.1);">
                                        <thead>
                                            <tr style="background: rgba(26, 26, 46, 0.05);">
                                                <th style="padding: 8px; text-align: left; border: 1px solid rgba(0,0,0,0.1); font-size: 0.85rem;">Item</th>
                                                <th style="padding: 8px; text-align: center; border: 1px solid rgba(0,0,0,0.1); font-size: 0.85rem;">Condition</th>
                                                <th style="padding: 8px; text-align: center; border: 1px solid rgba(0,0,0,0.1); font-size: 0.85rem;">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($handoverDetails['desktop_condition'])) : ?>
                                                <tr>
                                                    <td style="padding: 8px; border: 1px solid rgba(0,0,0,0.1);"><strong>Desktop</strong></td>
                                                    <td style="padding: 8px; border: 1px solid rgba(0,0,0,0.1); text-align: center;"><?php echo htmlspecialchars($handoverDetails['desktop_condition']); ?></td>
                                                    <td style="padding: 8px; border: 1px solid rgba(0,0,0,0.1); text-align: center;"><?php echo htmlspecialchars($handoverDetails['desktop_status'] ?? 'N/A'); ?></td>
                                                </tr>
                                            <?php endif; ?>
                                            <?php if (!empty($handoverDetails['harddisk_condition'])) : ?>
                                                <tr>
                                                    <td style="padding: 8px 8px 8px 30px; border: 1px solid rgba(0,0,0,0.1);">Hard disk</td>
                                                    <td style="padding: 8px; border: 1px solid rgba(0,0,0,0.1); text-align: center;"><?php echo htmlspecialchars($handoverDetails['harddisk_condition']); ?></td>
                                                    <td style="padding: 8px; border: 1px solid rgba(0,0,0,0.1); text-align: center;"><?php echo htmlspecialchars($handoverDetails['harddisk_status'] ?? 'N/A'); ?></td>
                                                </tr>
                                            <?php endif; ?>
                                            <?php if (!empty($handoverDetails['monitor_condition'])) : ?>
                                                <tr>
                                                    <td style="padding: 8px 8px 8px 30px; border: 1px solid rgba(0,0,0,0.1);">Monitor</td>
                                                    <td style="padding: 8px; border: 1px solid rgba(0,0,0,0.1); text-align: center;"><?php echo htmlspecialchars($handoverDetails['monitor_condition']); ?></td>
                                                    <td style="padding: 8px; border: 1px solid rgba(0,0,0,0.1); text-align: center;"><?php echo htmlspecialchars($handoverDetails['monitor_status'] ?? 'N/A'); ?></td>
                                                </tr>
                                            <?php endif; ?>
                                            <?php if (!empty($handoverDetails['mouse_condition'])) : ?>
                                                <tr>
                                                    <td style="padding: 8px 8px 8px 30px; border: 1px solid rgba(0,0,0,0.1);">Mouse</td>
                                                    <td style="padding: 8px; border: 1px solid rgba(0,0,0,0.1); text-align: center;"><?php echo htmlspecialchars($handoverDetails['mouse_condition']); ?></td>
                                                    <td style="padding: 8px; border: 1px solid rgba(0,0,0,0.1); text-align: center;"><?php echo htmlspecialchars($handoverDetails['mouse_status'] ?? 'N/A'); ?></td>
                                                </tr>
                                            <?php endif; ?>
                                            <?php if (!empty($handoverDetails['keyboard_condition'])) : ?>
                                                <tr>
                                                    <td style="padding: 8px 8px 8px 30px; border: 1px solid rgba(0,0,0,0.1);">Keyboard</td>
                                                    <td style="padding: 8px; border: 1px solid rgba(0,0,0,0.1); text-align: center;"><?php echo htmlspecialchars($handoverDetails['keyboard_condition']); ?></td>
                                                    <td style="padding: 8px; border: 1px solid rgba(0,0,0,0.1); text-align: center;"><?php echo htmlspecialchars($handoverDetails['keyboard_status'] ?? 'N/A'); ?></td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endif; ?>
            </div>
                <?php endif; ?>
            </div>
        <?php else : ?>
            <div class="return-form-card">
                <div class="alert alert-error">
                    <p>Unable to load asset information. Please ensure you have selected a valid asset.</p>
                    <p style="margin-top: 10px;">
                        <a href="LAPTOPpage.php" style="color: #c0392b; text-decoration: underline;">Go to Asset List</a>
                    </p>
            </div>
            </div>
        <?php endif; ?>
    </div>

    <footer>
        <?php include_once("../components/Footer.php"); ?>
    </footer>

    <script>
        // Set max date to today for return date
        const returnDateInput = document.getElementById('return_date');
        if (returnDateInput) {
            returnDateInput.setAttribute('max', new Date().toISOString().split('T')[0]);
        }
        
        // Auto-select status based on condition
        const conditionRadios = document.querySelectorAll('input[name="return_condition"]');
        const statusSelect = document.getElementById('new_status');
        
        if (conditionRadios.length > 0 && statusSelect) {
            conditionRadios.forEach(radio => {
                radio.addEventListener('change', function() {
                    if (this.value === 'Excellent' || this.value === 'Good') {
                        // Suggest ACTIVE status for good condition
                        if (statusSelect.querySelector('option[value="ACTIVE"]')) {
                            statusSelect.value = 'ACTIVE';
                        }
                    } else if (this.value === 'Fair' || this.value === 'Poor') {
                        // Suggest FAULTY or MAINTENANCE for damaged condition
                        if (statusSelect.querySelector('option[value="FAULTY"]')) {
                            statusSelect.value = 'FAULTY';
                        } else if (statusSelect.querySelector('option[value="MAINTENANCE"]')) {
                            statusSelect.value = 'MAINTENANCE';
                        }
                    } else if (this.value === 'Damaged') {
                        // Suggest FAULTY or MAINTENANCE for damaged
                        if (statusSelect.querySelector('option[value="FAULTY"]')) {
                            statusSelect.value = 'FAULTY';
                        } else if (statusSelect.querySelector('option[value="MAINTENANCE"]')) {
                            statusSelect.value = 'MAINTENANCE';
                        }
                    }
                });
            });
        }
    </script>
</body>
</html>
