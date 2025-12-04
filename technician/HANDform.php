<?php
session_start();
require_once '../database/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$pdo = getDBConnection();
$error = '';
$success = '';

// Asset is chosen in listing page and passed via URL (?asset_id=...&asset_type=...)
$assetTypeParam = trim($_GET['asset_type'] ?? '');
$assetIdParam = trim($_GET['asset_id'] ?? '');

// Allow POST to carry same values so the wizard stays consistent
$currentAssetType = trim($_POST['assetType'] ?? $assetTypeParam);
$currentAssetIdRaw = $_POST['assetId'] ?? $assetIdParam;
$currentAssetId = is_numeric($currentAssetIdRaw) ? (int)$currentAssetIdRaw : 0;
$assetDetails = null;

if ($currentAssetType && $currentAssetId) {
    try {
        if ($currentAssetType === 'laptop_desktop') {
            $stmt = $pdo->prepare("SELECT asset_id, serial_num, brand, model, category AS category, status FROM laptop_desktop_assets WHERE asset_id = ?");
        } elseif ($currentAssetType === 'av') {
            $stmt = $pdo->prepare("SELECT asset_id, serial_num, brand, model, class AS category, status FROM av_assets WHERE asset_id = ?");
        } else {
            $stmt = null;
        }

        if ($stmt) {
            $stmt->execute([$currentAssetId]);
            $assetDetails = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        if (!$assetDetails && $_SERVER['REQUEST_METHOD'] !== 'POST') {
            $error = 'Selected asset could not be found.';
        }
    } catch (PDOException $e) {
        error_log('Error fetching asset for handover form: ' . $e->getMessage());
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $error = 'Failed to load selected asset. Please try again.';
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $error = 'No asset selected for handover. Please start from the asset list.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $staff_id = trim($_POST['staff_id'] ?? '');
    $handover_date = trim($_POST['handoverDate'] ?? '');
    $handover_location = trim($_POST['handoverLocation'] ?? '');
    $asset_type = $currentAssetType;
    $asset_id = (string)$currentAssetId;
    $accessories = trim($_POST['accessories'] ?? '');
    $condition_agreement = isset($_POST['conditionAgreement']) ? 1 : 0;
    $handover_notes = trim($_POST['handoverNotes'] ?? '');
    $digital_signoff = trim($_POST['signOff'] ?? '');

    if (empty($staff_id) || empty($handover_date) || empty($handover_location) || 
        empty($asset_type) || empty($asset_id) || 
        !$condition_agreement || empty($digital_signoff)) {
        $error = 'All required fields must be filled.';
    } elseif (!is_numeric($staff_id)) {
        $error = 'Invalid Staff ID.';
    } elseif (!is_numeric($asset_id)) {
        $error = 'Invalid Asset ID.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT staff_id FROM staff_list WHERE staff_id = ?");
            $stmt->execute([$staff_id]);
            if (!$stmt->fetch()) {
                $error = 'Staff ID does not exist.';
            } elseif (!$currentAssetType || !$currentAssetId || !$assetDetails) {
                $error = 'Invalid or missing asset information.';
            } else {
                $pdo->beginTransaction();

                $insertStmt = $pdo->prepare("
                    INSERT INTO handover (
                        staff_id, asset_type, asset_id, accessories,
                        handover_date, handover_location, condition_agreement,
                        handover_notes, digital_signoff, created_by
                    ) VALUES (
                        :staff_id, :asset_type, :asset_id, :accessories,
                        :handover_date, :handover_location, :condition_agreement,
                        :handover_notes, :digital_signoff, :created_by
                    )
                ");
                
                $insertStmt->execute([
                    ':staff_id' => (int)$staff_id,
                    ':asset_type' => $asset_type,
                    ':asset_id' => (int)$asset_id,
                    ':accessories' => $accessories ?: null,
                    ':handover_date' => $handover_date,
                    ':handover_location' => $handover_location,
                    ':condition_agreement' => $condition_agreement,
                    ':handover_notes' => $handover_notes ?: null,
                    ':digital_signoff' => $digital_signoff,
                    ':created_by' => $_SESSION['user_id']
                ]);

                $handover_id = $pdo->lastInsertId();

                if ($asset_type === 'laptop_desktop') {
                    $oldStatusStmt = $pdo->prepare("SELECT status FROM laptop_desktop_assets WHERE asset_id = ?");
                    $oldStatusStmt->execute([(int)$asset_id]);
                    $oldStatus = $oldStatusStmt->fetchColumn();
                    
                    $updateStmt = $pdo->prepare("UPDATE laptop_desktop_assets SET status = 'DEPLOY', staff_id = ? WHERE asset_id = ?");
                    $updateStmt->execute([(int)$staff_id, (int)$asset_id]);
                } else {
                    $oldStatusStmt = $pdo->prepare("SELECT status FROM av_assets WHERE asset_id = ?");
                    $oldStatusStmt->execute([(int)$asset_id]);
                    $oldStatus = $oldStatusStmt->fetchColumn();
                    
                    $updateStmt = $pdo->prepare("UPDATE av_assets SET status = 'DEPLOY' WHERE asset_id = ?");
                    $updateStmt->execute([(int)$asset_id]);
                }

                $trailStmt = $pdo->prepare("
                    INSERT INTO asset_trails (
                        asset_type, asset_id, action_type, changed_by,
                        field_name, old_value, new_value, description,
                        ip_address, user_agent
                    ) VALUES (
                        :asset_type, :asset_id, 'ASSIGNMENT_CHANGE', :changed_by,
                        'status', :old_status, 'DEPLOY', :description,
                        :ip_address, :user_agent
                    )
                ");
                
                $trailStmt->execute([
                    ':asset_type' => $asset_type,
                    ':asset_id' => (int)$asset_id,
                    ':changed_by' => $_SESSION['user_id'],
                    ':old_status' => $oldStatus ?? 'UNKNOWN',
                    ':description' => "Asset handover completed. Handover ID: {$handover_id}, Staff ID: {$staff_id}",
                    ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                    ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                ]);

                $pdo->commit();
                
                $stmt = $pdo->prepare("SELECT staff_name, email, faculty FROM staff_list WHERE staff_id = ?");
                $stmt->execute([$staff_id]);
                $staff = $stmt->fetch();
                
                $stmt = $pdo->prepare("SELECT tech_name FROM technician WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $handoverBy = $stmt->fetch();
                
                // Reuse already loaded asset details where possible
                if ($assetDetails) {
                    $asset = $assetDetails;
                } else {
                    if ($asset_type === 'laptop_desktop') {
                        $assetStmt = $pdo->prepare("SELECT serial_num, brand, model, category FROM laptop_desktop_assets WHERE asset_id = ?");
                    } else {
                        $assetStmt = $pdo->prepare("SELECT serial_num, brand, model, class as category FROM av_assets WHERE asset_id = ?");
                    }
                    $assetStmt->execute([$asset_id]);
                    $asset = $assetStmt->fetch();
                }
                
                $pdfData = [
                    'staff_id' => $staff_id,
                    'staff_name' => $staff['staff_name'] ?? '',
                    'staff_designation' => $staff['faculty'] ?? '',
                    'staff_email' => $staff['email'] ?? '',
                    'asset_id' => $asset_id,
                    'asset_type' => $asset_type,
                    'serial_num' => $asset['serial_num'] ?? '',
                    'brand' => $asset['brand'] ?? '',
                    'model' => $asset['model'] ?? '',
                    'category' => $asset['category'] ?? '',
                    'accessories' => $accessories,
                    'handover_date' => $handover_date,
                    'handover_location' => $handover_location,
                    'handover_notes' => $handover_notes,
                    'digital_signoff' => $digital_signoff,
                    'handover_by_name' => $handoverBy['tech_name'] ?? 'IT Department',
                    'handover_by_designation' => 'IT Staff'
                ];
                
                require_once '../services/pdf_generator.php';
                require_once '../services/mail_config.php';
                
                $pdfContent = generateHandoverPDF($pdfData);
                
                if ($pdfContent && !empty($staff['email'])) {
                    $tempFile = sys_get_temp_dir() . '/handover_' . $handover_id . '_' . time() . '.pdf';
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
                                <h2>Asset Handover Confirmation</h2>
                            </div>
                            <div class='content'>
                                <p>Dear {$staff['staff_name']},</p>
                                <p>This email confirms that you have received the following asset:</p>
                                <ul>
                                    <li><strong>Asset ID:</strong> {$asset_id}</li>
                                    <li><strong>Type:</strong> " . ucwords(str_replace('_', ' ', $asset_type)) . "</li>
                                    <li><strong>Category:</strong> {$asset['category']}</li>
                                    <li><strong>Brand:</strong> {$asset['brand']}</li>
                                    <li><strong>Model:</strong> {$asset['model']}</li>
                                    <li><strong>Serial Number:</strong> {$asset['serial_num']}</li>
                                    <li><strong>Handover Date:</strong> {$handover_date}</li>
                                    <li><strong>Location:</strong> {$handover_location}</li>
                                </ul>
                                <p>Please find attached the complete handover document for your records.</p>
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
                        'name' => 'Handover_Document_' . $handover_id . '.pdf',
                        'type' => 'application/pdf'
                    ]];
                    
                    $emailSent = sendEmail(
                        $staff['email'],
                        'Asset Handover Confirmation - Handover ID: ' . $handover_id,
                        $emailBody,
                        $attachments
                    );
                    
                    if ($emailSent) {
                        $success = 'Handover form submitted successfully! Confirmation email with PDF has been sent.';
                    } else {
                        $success = 'Handover form submitted successfully! However, email could not be sent.';
                    }
                    
                    if (file_exists($tempFile)) {
                        unlink($tempFile);
                    }
                } else {
                    $success = 'Handover form submitted successfully!';
                }
            }
        } catch (PDOException $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Handover form submission error: ' . $e->getMessage());
            $error = 'Failed to submit handover form. Please try again.';
        } catch (Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Handover form error: ' . $e->getMessage());
            $error = 'Failed to process handover form. Please try again.';
        }
    }
}

if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    if ($_GET['action'] === 'get_staff') {
        $staff_id = $_GET['staff_id'] ?? '';
        if (is_numeric($staff_id)) {
            $stmt = $pdo->prepare("SELECT staff_id, staff_name, email, phone, faculty FROM staff_list WHERE staff_id = ?");
            $stmt->execute([$staff_id]);
            $staff = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode($staff ?: ['error' => 'Staff not found']);
        } else {
            echo json_encode(['error' => 'Invalid staff ID']);
        }
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asset Handover Form - UniKL RCMP IT Inventory</title>
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

        .handover-form {
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(0, 0, 0, 0.05);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.05);
        }

        .asset-summary-card {
            margin-bottom: 25px;
            padding: 18px 20px;
            border-radius: 12px;
            border: 1px solid rgba(0, 0, 0, 0.08);
            background: rgba(245, 246, 250, 0.9);
        }

        .asset-summary-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
        }

        .asset-summary-header i {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(26, 26, 46, 0.08);
            color: #1a1a2e;
        }

        .asset-summary-header h4 {
            margin: 0;
            font-size: 1rem;
            font-weight: 600;
            color: #1a1a2e;
        }

        .asset-summary-header p {
            margin: 2px 0 0;
            font-size: 0.85rem;
            color: #636e72;
        }

        .asset-summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 10px 16px;
        }

        .asset-summary-item .label {
            display: block;
            font-size: 0.8rem;
            color: #636e72;
            margin-bottom: 2px;
        }

        .asset-summary-item .value {
            font-size: 0.9rem;
            font-weight: 600;
            color: #2d3436;
        }

        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 40px;
            position: relative;
        }

        .step-indicator::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 0;
            right: 0;
            height: 2px;
            background: #e0e0e0;
            z-index: 0;
        }

        .step-indicator .progress-bar {
            position: absolute;
            top: 20px;
            left: 0;
            height: 2px;
            background: #1a1a2e;
            transition: width 0.3s ease;
            z-index: 1;
        }

        .step-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            z-index: 2;
            flex: 1;
        }

        .step-number {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #ffffff;
            border: 2px solid #e0e0e0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            color: #636e72;
            transition: all 0.3s ease;
        }

        .step-item.active .step-number {
            background: #1a1a2e;
            border-color: #1a1a2e;
            color: #ffffff;
        }

        .step-item.completed .step-number {
            background: #1a1a2e;
            border-color: #1a1a2e;
            color: #ffffff;
        }

        .step-label {
            margin-top: 10px;
            font-size: 0.9rem;
            font-weight: 500;
            color: #636e72;
            text-align: center;
        }

        .step-item.active .step-label {
            color: #1a1a2e;
            font-weight: 600;
        }

        .form-step {
            display: none;
            animation: fadeIn 0.3s ease;
        }

        .form-step.active {
            display: block;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
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

        .form-group textarea {
            resize: vertical;
            min-height: 120px;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #1a1a2e;
            box-shadow: 0 0 0 3px rgba(26, 26, 46, 0.1);
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .checkbox-group label {
            margin: 0;
        }

        .terms-link {
            color: #1a1a2e;
            text-decoration: underline;
            cursor: pointer;
            font-weight: 600;
        }

        .terms-link:hover {
            color: #6c5ce7;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
        }

        .modal-content {
            background: #ffffff;
            margin: 5% auto;
            padding: 30px;
            border-radius: 16px;
            max-width: 700px;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            position: relative;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid rgba(0, 0, 0, 0.1);
        }

        .modal-header h3 {
            margin: 0;
            font-size: 1.5rem;
            color: #1a1a2e;
        }

        .close-modal {
            background: transparent;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #636e72;
            padding: 0;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            transition: all 0.2s ease;
        }

        .close-modal:hover {
            background: rgba(0, 0, 0, 0.05);
            color: #2d3436;
        }

        .modal-body {
            margin-bottom: 20px;
            line-height: 1.6;
            color: #2d3436;
        }

        .modal-body p {
            margin-bottom: 15px;
        }

        .modal-body ul {
            margin-left: 20px;
            margin-bottom: 15px;
        }

        .modal-body li {
            margin-bottom: 10px;
        }

        .modal-footer {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            padding-top: 20px;
            border-top: 1px solid rgba(0, 0, 0, 0.1);
        }

        .btn-modal {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-modal-primary {
            background: #1a1a2e;
            color: #ffffff;
        }

        .btn-modal-primary:hover {
            background: #0f0f1a;
            box-shadow: 0 4px 12px rgba(26, 26, 46, 0.3);
        }

        .btn-modal-secondary {
            background: #f8f9fa;
            color: #2d3436;
        }

        .btn-modal-secondary:hover {
            background: #e9ecef;
        }

        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: space-between;
            margin-top: 30px;
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

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
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

            .step-label {
                font-size: 0.8rem;
            }
        }
    </style>
</head>
<body>
    <?php include_once("../components/HomeHeader.php"); ?>

    <div class="form-page-container">
        <div class="page-header">
            <h1>Asset Handover Form</h1>
            <p>Document the transfer of equipment between personnel or departments. All fields are captured for traceability.</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error" style="background: rgba(214, 48, 49, 0.1); color: #d63031; padding: 14px 20px; border-radius: 8px; margin-bottom: 25px; border: 1px solid rgba(214, 48, 49, 0.2); display: flex; align-items: center; gap: 12px;">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success" style="background: rgba(0, 184, 148, 0.1); color: #00b894; padding: 14px 20px; border-radius: 8px; margin-bottom: 25px; border: 1px solid rgba(0, 184, 148, 0.2); display: flex; align-items: center; gap: 12px;">
                <i class="fa-solid fa-circle-check"></i>
                <span><?php echo htmlspecialchars($success); ?></span>
            </div>
        <?php endif; ?>

        <form class="handover-form" id="handoverForm" method="POST" action="">
            <div class="step-indicator">
                <div class="progress-bar" id="progressBar"></div>
                <div class="step-item active" data-step="1">
                    <div class="step-number">1</div>
                    <div class="step-label">Staff Information</div>
                </div>
                <div class="step-item" data-step="2">
                    <div class="step-number">2</div>
                    <div class="step-label">Agreement</div>
                </div>
            </div>

            <div class="form-step active" id="step1">
                <div class="form-section">
                    <h3 class="form-section-title">Recipient Information</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="staff_id">Staff ID <span style="color:#c0392b;">*</span></label>
                            <input type="number" id="staff_id" name="staff_id" placeholder="Enter staff ID" required>
                        </div>
                        <div class="form-group">
                            <label for="handoverTo">Received By</label>
                            <input type="text" id="handoverTo" name="handoverTo" placeholder="Auto-filled from Staff ID" readonly style="background-color: #f5f5f5; cursor: not-allowed;" required>
                        </div>
                        <div class="form-group">
                            <label for="unitDepartment">Unit / Department</label>
                            <input type="text" id="unitDepartment" name="unitDepartment" placeholder="Auto-filled from Staff ID" readonly style="background-color: #f5f5f5; cursor: not-allowed;" required>
                        </div>
                        <div class="form-group">
                            <label for="contactNumber">Contact Number</label>
                            <input type="text" id="contactNumber" name="contactNumber" placeholder="Auto-filled from Staff ID" readonly style="background-color: #f5f5f5; cursor: not-allowed;" required>
                        </div>
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" placeholder="Auto-filled from Staff ID" readonly style="background-color: #f5f5f5; cursor: not-allowed;" required>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3 class="form-section-title">Handover Information</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="handoverDate">Handover Date</label>
                            <input type="date" id="handoverDate" name="handoverDate" required>
                        </div>
                        <div class="form-group">
                            <label for="handoverLocation">Handover Location</label>
                            <input type="text" id="handoverLocation" name="handoverLocation" placeholder="e.g., IT Support Office, Block B" required>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-step" id="step2">
                <div class="form-section">
                    <h3 class="form-section-title">Acknowledgements</h3>
                    <div class="asset-summary-card">
                        <div class="asset-summary-header">
                            <i class="fa-solid fa-laptop"></i>
                            <div>
                                <h4>Asset Summary</h4>
                                <p>Please confirm this is the correct asset being handed over.</p>
                            </div>
                        </div>
                        <div class="asset-summary-grid">
                            <div class="asset-summary-item">
                                <span class="label">Asset ID</span>
                                <span class="value"><?php echo htmlspecialchars((string)($assetDetails['asset_id'] ?? $currentAssetId)); ?></span>
                            </div>
                            <div class="asset-summary-item">
                                <span class="label">Category</span>
                                <span class="value"><?php echo htmlspecialchars((string)($assetDetails['category'] ?? '-')); ?></span>
                            </div>
                            <div class="asset-summary-item">
                                <span class="label">Brand</span>
                                <span class="value"><?php echo htmlspecialchars((string)($assetDetails['brand'] ?? '-')); ?></span>
                            </div>
                            <div class="asset-summary-item">
                                <span class="label">Model</span>
                                <span class="value"><?php echo htmlspecialchars((string)($assetDetails['model'] ?? '-')); ?></span>
                            </div>
                            <div class="asset-summary-item">
                                <span class="label">Serial Number</span>
                                <span class="value"><?php echo htmlspecialchars((string)($assetDetails['serial_num'] ?? '-')); ?></span>
                            </div>
                        </div>

                        <!-- Hidden fields so POST always carries asset info -->
                        <input type="hidden" id="assetId" name="assetId" value="<?php echo htmlspecialchars((string)$currentAssetId); ?>">
                        <input type="hidden" id="assetType" name="assetType" value="<?php echo htmlspecialchars($currentAssetType); ?>">
                    </div>

                    <div class="form-grid">
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label for="accessories">Included Accessories</label>
                            <input type="text" id="accessories" name="accessories" placeholder="e.g., Charger, Bag, Docking Station">
                        </div>
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <div class="checkbox-group">
                                <input type="checkbox" id="conditionAgreement" name="conditionAgreement" required disabled>
                                <label for="conditionAgreement">Recipient confirms asset condition is acceptable and agrees to report any issues immediately. <a href="#" class="terms-link" onclick="event.preventDefault(); openTermsModal();">Terms & Conditions</a></label>
                            </div>
                        </div>
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label for="handoverNotes">Additional Notes</label>
                            <textarea id="handoverNotes" name="handoverNotes" placeholder="Add remarks about condition, software, tags, etc."></textarea>
                        </div>
                        <div class="form-group">
                            <label for="signOff">Digital Sign-off (Recipient)</label>
                            <input type="text" id="signOff" name="signOff" placeholder="Type full name as signature" required>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <div>
                    <button type="button" class="btn btn-secondary" onclick="window.history.back()">Cancel</button>
                    <button type="button" class="btn btn-secondary" id="prevBtn" style="display: none;" onclick="previousStep()">Previous</button>
                </div>
                <div>
                    <button type="button" class="btn btn-primary" id="nextBtn" onclick="nextStep()">Next</button>
                    <button type="submit" class="btn btn-primary" id="submitBtn" style="display: none;">Submit Handover</button>
                </div>
            </div>
        </form>

        <div id="termsModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Terms & Conditions</h3>
                    <button class="close-modal" onclick="closeTermsModal()">&times;</button>
                </div>
                <div class="modal-body">
                    <div style="margin-bottom: 25px;">
                        <h4 style="color: #1a1a2e; font-size: 1.1rem; margin-bottom: 15px;">UNIVERSITI KUALA LUMPUR ROYAL COLLEGE OF MEDICINE PERAK (UNIKL RCMP)</h4>
                        <p style="font-weight: 600; margin-bottom: 15px; color: #1a1a2e;">Software Policy Regarding the Use of Computer Software</p>
                        <ol style="padding-left: 20px; margin-bottom: 20px;">
                            <li style="margin-bottom: 10px;">UNIKL RCMP licenses the use of computer software from a variety of outside companies. UNIKL RCMP does not own this software or its related documentation, and unless authorized by the software developers, does not have the right to reproduce it, even for back-up purposes, unless explicitly allowed by the software owner. (eg: Microsoft, Adobe and etc).</li>
                            <li style="margin-bottom: 10px;">UNIKL RCMP employees shall use the software only in accordance with the license agreements and will not install unauthorized copies of the commercial software.</li>
                            <li style="margin-bottom: 10px;">UNIKL RCMP employees shall not download or upload unauthorized software over the Internet.</li>
                            <li style="margin-bottom: 10px;">UNIKL RCMP employees learning of any misuse of software or company IT equipment (which includes vandalism of the certificate of authenticity sticker on the PC casing chassis, PC monitors, CD media etc) which could be detrimental to the business of the company shall notify their immediate supervisor.</li>
                            <li style="margin-bottom: 10px;">Under the Copyright Act 1987, offenders can be fined from RM2,000 to RM20,000 for each infringing copy and/or face imprisonment of up to 5 years. UNIKL RCMP does not condone the illegal duplication of software. UNIKL RCMP employees who make, acquire, or use authorized copies of computer software shall be disciplined as appropriate under the circumstances. Such discipline action may include termination.</li>
                            <li style="margin-bottom: 10px;">Any doubts concerning whether any employee may copy/duplicate or use a given software program should be raised with the immediate supervisor before proceeding.</li>
                        </ol>
                    </div>

                    <div style="margin-bottom: 25px; padding-top: 20px; border-top: 2px solid rgba(0, 0, 0, 0.1);">
                        <p style="font-weight: 600; margin-bottom: 15px; color: #1a1a2e;">Please comply with the following company's requirements:</p>
                        <p style="margin-bottom: 20px; color: #2d3436;"><strong>ASSET ID: <span id="modalAssetId" style="color: #1a1a2e;">-</span></strong></p>
                        <ol style="padding-left: 20px; margin-bottom: 20px;">
                            <li style="margin-bottom: 10px;">To comply with Company Notebook/Desktop Usage Policy. (Please refer to <a href="http://it.rcmp.unikl.edu.my" target="_blank">it.rcmp.unikl.edu.my</a>)</li>
                            <li style="margin-bottom: 10px;">To use this Notebook/Desktop for working purposes only.</li>
                            <li style="margin-bottom: 10px;">To use for teaching purposes and use at appropriate place only. (If related)</li>
                            <li style="margin-bottom: 10px;">Installation of any unauthorized/illegal software into this Notebook/Desktop is strictly prohibited.</li>
                            <li style="margin-bottom: 10px;">Any request for repair due to mechanical defect must be forwarded to the IT Department by filling in the requisition form and subject to approval by the management.</li>
                            <li style="margin-bottom: 10px;">The user is responsible for repairing or replacement cost of the damage or loss due to negligence or intentional misconduct.</li>
                        </ol>
                    </div>

                    <div style="margin-top: 25px; padding-top: 20px; border-top: 2px solid rgba(0, 0, 0, 0.1);">
                        <p style="font-weight: 600; margin-bottom: 15px; color: #1a1a2e;">Liability Statement:</p>
                        <p style="font-style: italic; color: #2d3436; background: rgba(26, 26, 46, 0.05); padding: 15px; border-radius: 8px; border-left: 4px solid #1a1a2e;">
                            'I, <strong id="modalStaffName" style="color: #1a1a2e;">[staff name]</strong> agree to pay all costs associated with damage to the above peripherals or its associated peripheral equipment. I also agree to pay for replacement cost of the equipment should it be lost or stolen.'
                        </p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-modal btn-modal-secondary" onclick="closeTermsModal()">Close</button>
                    <button type="button" class="btn-modal btn-modal-primary" onclick="acceptTerms()">I Understood</button>
                </div>
            </div>
        </div>

        <script>
            let currentStep = 1;
            const totalSteps = 2;

            function updateStepIndicator() {
                document.querySelectorAll('.step-item').forEach((item, index) => {
                    const stepNum = index + 1;
                    item.classList.remove('active', 'completed');
                    if (stepNum < currentStep) {
                        item.classList.add('completed');
                    } else if (stepNum === currentStep) {
                        item.classList.add('active');
                    }
                });

                const progress = ((currentStep - 1) / (totalSteps - 1)) * 100;
                document.getElementById('progressBar').style.width = progress + '%';
            }

            function showStep(step) {
                document.querySelectorAll('.form-step').forEach((formStep, index) => {
                    formStep.classList.toggle('active', index + 1 === step);
                });

                document.getElementById('prevBtn').style.display = step === 1 ? 'none' : 'inline-block';
                document.getElementById('nextBtn').style.display = step === totalSteps ? 'none' : 'inline-block';
                document.getElementById('submitBtn').style.display = step === totalSteps ? 'inline-block' : 'none';

                updateStepIndicator();
            }

            function nextStep() {
                const currentForm = document.getElementById('step' + currentStep);
                const requiredFields = currentForm.querySelectorAll('[required]');
                let isValid = true;

                requiredFields.forEach(field => {
                    if (!field.value.trim()) {
                        isValid = false;
                        field.style.borderColor = '#e74c3c';
                        setTimeout(() => {
                            field.style.borderColor = '';
                        }, 2000);
                    } else {
                        field.style.borderColor = '';
                    }

                    if (field.type === 'checkbox' && !field.checked) {
                        isValid = false;
                    }

                    if (field.type === 'email' && field.value && !field.validity.valid) {
                        isValid = false;
                    }
                });

                if (isValid && currentStep < totalSteps) {
                    currentStep++;
                    showStep(currentStep);
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }
            }

            function previousStep() {
                if (currentStep > 1) {
                    currentStep--;
                    showStep(currentStep);
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }
            }

            document.getElementById('handoverForm').addEventListener('submit', function(e) {
                const step2Form = document.getElementById('step2');
                const requiredFields = step2Form.querySelectorAll('[required]');
                let isValid = true;

                requiredFields.forEach(field => {
                    if (!field.value.trim() || (field.type === 'checkbox' && !field.checked)) {
                        isValid = false;
                        field.style.borderColor = '#e74c3c';
                        setTimeout(() => {
                            field.style.borderColor = '';
                        }, 2000);
                    }
                });

                if (!isValid) {
                    e.preventDefault();
                }
            });

            document.getElementById('handoverDate').valueAsDate = new Date();

            function openTermsModal() {
                const assetIdField = document.getElementById('assetId');
                const handoverToField = document.getElementById('handoverTo');
                const modalAssetId = document.getElementById('modalAssetId');
                const modalStaffName = document.getElementById('modalStaffName');
                
                modalAssetId.textContent = assetIdField.value || '-';
                modalStaffName.textContent = handoverToField.value || '[staff name]';
                document.getElementById('termsModal').style.display = 'block';
            }

            function closeTermsModal() {
                document.getElementById('termsModal').style.display = 'none';
            }

            function acceptTerms() {
                document.getElementById('conditionAgreement').checked = true;
                document.getElementById('conditionAgreement').disabled = false;
                closeTermsModal();
            }

            window.onclick = function(event) {
                const modal = document.getElementById('termsModal');
                if (event.target == modal) {
                    closeTermsModal();
                }
            }

            const staffIdField = document.getElementById('staff_id');
            const handoverToField = document.getElementById('handoverTo');
            const unitDepartmentField = document.getElementById('unitDepartment');
            const contactNumberField = document.getElementById('contactNumber');
            const emailField = document.getElementById('email');

            let staffFetchTimeout;
            staffIdField.addEventListener('input', function() {
                const staffId = this.value.trim();
                
                clearTimeout(staffFetchTimeout);
                
                if (staffId === '') {
                    handoverToField.value = '';
                    unitDepartmentField.value = '';
                    contactNumberField.value = '';
                    emailField.value = '';
                    return;
                }
                
                if (!/^\d+$/.test(staffId)) {
                    return;
                }
                
                staffFetchTimeout = setTimeout(() => {
                    fetch('?action=get_staff&staff_id=' + encodeURIComponent(staffId))
                        .then(response => response.json())
                        .then(data => {
                            if (data.error) {
                                handoverToField.value = '';
                                unitDepartmentField.value = '';
                                contactNumberField.value = '';
                                emailField.value = '';
                            } else {
                                handoverToField.value = data.staff_name || '';
                                unitDepartmentField.value = data.faculty || '';
                                contactNumberField.value = data.phone || '';
                                emailField.value = data.email || '';
                            }
                        })
                        .catch(error => {
                            console.error('Error fetching staff:', error);
                            handoverToField.value = '';
                            unitDepartmentField.value = '';
                            contactNumberField.value = '';
                            emailField.value = '';
                        });
                }, 500);
            });

            // Asset is fixed (page is opened from asset list), so there is no in-form asset selection.
        </script>
    </div>

    <footer>
        <?php include_once("../components/Footer.php"); ?>
    </footer>
</body>
</html>


