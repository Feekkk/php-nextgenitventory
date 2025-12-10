<?php
session_start();
require_once '../database/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method. This operation requires a POST request.']);
    exit;
}

$asset_id = isset($_POST['asset_id']) ? (int)$_POST['asset_id'] : 0;

if ($asset_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid asset ID provided. Please select a valid asset.']);
    exit;
}

try {
    $pdo = getDBConnection();
    $pdo->beginTransaction();
    
    // Get current asset data
    $stmt = $pdo->prepare("SELECT status, building FROM net_assets WHERE asset_id = ?");
    $stmt->execute([$asset_id]);
    $asset = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$asset) {
        throw new Exception('Asset not found in the database. Please verify the asset ID and try again.');
    }
    
    $oldStatus = strtoupper(trim($asset['status'] ?? ''));
    
    // Only allow marking in stock if status is ONLINE
    if ($oldStatus !== 'ONLINE') {
        throw new Exception("Cannot mark asset as in stock. Current status is '{$oldStatus}'. Only assets with ONLINE status can be marked as in stock.");
    }
    
    $oldBuilding = $asset['building'] ?? '';
    
    // Update asset status to OFFLINE and building to IT office
    $updateStmt = $pdo->prepare("UPDATE net_assets SET status = 'OFFLINE', building = 'IT office', level = NULL WHERE asset_id = ?");
    $updateStmt->execute([$asset_id]);
    
    // Create trail entry for status change
    $trailStmt = $pdo->prepare("
        INSERT INTO asset_trails (
            asset_type, asset_id, action_type, changed_by,
            field_name, old_value, new_value, description,
            ip_address, user_agent
        ) VALUES (
            'network', :asset_id, 'STATUS_CHANGE', :changed_by,
            'status', :old_status, 'OFFLINE', :description,
            :ip_address, :user_agent
        )
    ");
    
    $trailStmt->execute([
        ':asset_id' => $asset_id,
        ':changed_by' => $_SESSION['user_id'],
        ':old_status' => $oldStatus,
        ':description' => "Asset marked as in stock. Status changed from ONLINE to OFFLINE and location moved to IT office.",
        ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
    ]);
    
    // Create trail entry for location change
    if ($oldBuilding !== 'IT office') {
        $trailStmt = $pdo->prepare("
            INSERT INTO asset_trails (
                asset_type, asset_id, action_type, changed_by,
                field_name, old_value, new_value, description,
                ip_address, user_agent
            ) VALUES (
                'network', :asset_id, 'LOCATION_CHANGE', :changed_by,
                'building', :old_building, 'IT office', :description,
                :ip_address, :user_agent
            )
        ");
        
        $trailStmt->execute([
            ':asset_id' => $asset_id,
            ':changed_by' => $_SESSION['user_id'],
            ':old_building' => $oldBuilding ?: 'UNKNOWN',
            ':description' => "Asset location updated to IT office as part of in-stock marking process.",
            ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ]);
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Asset has been successfully marked as in stock. Status changed to OFFLINE and location updated to IT office.'
    ]);
    
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Database error in mark_in_stock.php: " . $e->getMessage());
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

