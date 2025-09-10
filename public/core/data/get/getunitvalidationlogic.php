<?php
session_start();

// Load configuration first
require_once(__DIR__ . '/../../config/config.php');
// Include security middleware  
require_once('../../security/session_timeout_middleware.php');
validateActiveSession();

include_once("../../config/db.class.php");

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit();
}

$unit_id = safe_get('unit_id', 'int', 0);

if ($unit_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid unit ID']);
    exit();
}

try {
    $unit_details = DB::queryFirstRow("SELECT validation_scheduling_logic FROM units WHERE unit_id = %d", $unit_id);
    
    if ($unit_details) {
        echo json_encode([
            'validation_scheduling_logic' => $unit_details['validation_scheduling_logic']
        ]);
    } else {
        echo json_encode([
            'validation_scheduling_logic' => 'dynamic' // default fallback
        ]);
    }
} catch (Exception $e) {
    error_log("Database error in getunitvalidationlogic.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error occurred',
        'validation_scheduling_logic' => 'dynamic' // fallback
    ]);
}
?>