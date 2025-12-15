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
    
    $stmt = $pdo->prepare("SELECT status FROM av_assets WHERE asset_id = ?");
    $stmt->execute([$asset_id]);
    $asset = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$asset) {
        throw new Exception('Asset not found in the database. Please verify the asset ID and try again.');
    }
    
    $oldStatus = strtoupper(trim($asset['status'] ?? ''));
    
    if ($oldStatus !== 'DEPLOY') {
        throw new Exception("Cannot mark asset as in stock. Current status is '{$oldStatus}'. Only assets with DEPLOY status can be marked as in stock.");
    }
    
    $updateStmt = $pdo->prepare("UPDATE av_assets SET status = 'ACTIVE' WHERE asset_id = ?");
    $updateStmt->execute([$asset_id]);
    
    $trailStmt = $pdo->prepare("
        INSERT INTO asset_trails (
            asset_type, asset_id, action_type, changed_by,
            field_name, old_value, new_value, description,
            ip_address, user_agent
        ) VALUES (
            'av', :asset_id, 'STATUS_CHANGE', :changed_by,
            'status', :old_status, 'ACTIVE', :description,
            :ip_address, :user_agent
        )
    ");
    
    $trailStmt->execute([
        ':asset_id' => $asset_id,
        ':changed_by' => $_SESSION['user_id'],
        ':old_status' => $oldStatus,
        ':description' => "Asset marked as in stock. Status changed from DEPLOY to ACTIVE.",
        ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
    ]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Asset has been successfully marked as in stock. Status changed to ACTIVE.'
    ]);
    
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Database error in mark_av_in_stock.php: " . $e->getMessage());
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
