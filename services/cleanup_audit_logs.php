<?php
require_once '../database/config.php';

function cleanupOldAuditLogs($pdo) {
    try {
        $threeMonthsAgo = date('Y-m-d H:i:s', strtotime('-3 months'));
        $stmt = $pdo->prepare("DELETE FROM login_audit WHERE login_time < ?");
        $stmt->execute([$threeMonthsAgo]);
        $deleted_count = $stmt->rowCount();
        return $deleted_count;
    } catch (PDOException $e) {
        error_log("Failed to cleanup audit logs: " . $e->getMessage());
        return false;
    }
}

try {
    $pdo = getDBConnection();
    $deleted_count = cleanupOldAuditLogs($pdo);
    
    if ($deleted_count !== false) {
        echo "Successfully deleted {$deleted_count} old audit log records (older than 3 months).\n";
        exit(0);
    } else {
        echo "Failed to cleanup old audit logs.\n";
        exit(1);
    }
} catch (Exception $e) {
    error_log("Cleanup script error: " . $e->getMessage());
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>

