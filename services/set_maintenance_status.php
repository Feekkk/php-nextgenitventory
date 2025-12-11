<?php
session_start();
require_once '../database/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access. Please log in.']);
    exit;
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method. This operation requires a POST request.']);
    exit;
}

$asset_id = isset($_POST['asset_id']) ? (int)$_POST['asset_id'] : 0;
$asset_type = isset($_POST['asset_type']) ? trim($_POST['asset_type']) : '';

if ($asset_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid asset ID provided. Please select a valid asset.']);
    exit;
}

$allowedTypes = ['laptop_desktop', 'av', 'network'];
if (!in_array($asset_type, $allowedTypes)) {
    echo json_encode(['success' => false, 'message' => 'Invalid asset type provided.']);
    exit;
}

try {
    $pdo = getDBConnection();
    $pdo->beginTransaction();
    
    $tableName = '';
    if ($asset_type === 'laptop_desktop') {
        $tableName = 'laptop_desktop_assets';
    } elseif ($asset_type === 'av') {
        $tableName = 'av_assets';
    } elseif ($asset_type === 'network') {
        $tableName = 'net_assets';
    }
    
    $stmt = $pdo->prepare("SELECT status FROM {$tableName} WHERE asset_id = ?");
    $stmt->execute([$asset_id]);
    $asset = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$asset) {
        throw new Exception('Asset not found in the database. Please verify the asset ID and try again.');
    }
    
    $oldStatus = strtoupper(trim($asset['status'] ?? ''));
    
    if ($oldStatus === 'MAINTENANCE') {
        $pdo->rollBack();
        echo json_encode([
            'success' => true,
            'message' => 'Asset is already in maintenance status.',
            'already_maintenance' => true
        ]);
        exit;
    }
    
    $allowedStatuses = ['FAULTY', 'MAINTENANCE', 'OFFLINE', 'DEPLOY', 'ACTIVE'];
    if (!in_array($oldStatus, $allowedStatuses)) {
        throw new Exception("Cannot set asset to maintenance. Current status is '{$oldStatus}'. Only assets with FAULTY, MAINTENANCE, OFFLINE, DEPLOY, or ACTIVE status can be set to maintenance.");
    }
    
    $updateStmt = $pdo->prepare("UPDATE {$tableName} SET status = 'MAINTENANCE' WHERE asset_id = ?");
    $updateStmt->execute([$asset_id]);
    
    $trailStmt = $pdo->prepare("
        INSERT INTO asset_trails (
            asset_type, asset_id, action_type, changed_by,
            field_name, old_value, new_value, description,
            ip_address, user_agent
        ) VALUES (
            :asset_type, :asset_id, 'STATUS_CHANGE', :changed_by,
            'status', :old_status, 'MAINTENANCE', :description,
            :ip_address, :user_agent
        )
    ");
    
    $trailStmt->execute([
        ':asset_type' => $asset_type,
        ':asset_id' => $asset_id,
        ':changed_by' => $_SESSION['user_id'],
        ':old_status' => $oldStatus ?: 'UNKNOWN',
        ':description' => "Asset status changed to MAINTENANCE. Repair form accessed by technician.",
        ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
    ]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Asset status has been updated to MAINTENANCE successfully.'
    ]);
    
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Database error in set_maintenance_status.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'A database error occurred while processing your request. Please try again later or contact the administrator.'
    ]);
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

