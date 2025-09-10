<?php 
session_start();

// Load configuration first
require_once(__DIR__ . '/../../config/config.php');

// Validate session timeout
require_once(__DIR__ . '/../../security/session_timeout_middleware.php');

// Include XSS protection middleware to get safe_get function
require_once(__DIR__ . '/../../security/xss_integration_middleware.php');

// Only validate session if we're in a web request
if (!empty($_SERVER['REQUEST_METHOD'])) {
    validateActiveSession();
}

require_once __DIR__ . '/../../config/db.class.php';
date_default_timezone_set("Asia/Kolkata");

//Show All PHP Errors for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Get input values - use direct integer validation to bypass XSS filtering issue
$unit_id = 0;
if (isset($_GET['unit_id']) && is_numeric($_GET['unit_id'])) {
    $unit_id = intval($_GET['unit_id']);
    error_log("DEBUG: Got unit_id from filtered GET: " . $unit_id);
} else {
    // Try to get original value before XSS filtering
    $original_unit_id = XSSIntegrationMiddleware::getRawInput('unit_id', 'GET');
    error_log("DEBUG: Original unit_id from raw input: " . var_export($original_unit_id, true));
    if (!empty($original_unit_id) && is_numeric($original_unit_id)) {
        $unit_id = intval($original_unit_id);
        error_log("DEBUG: Using original unit_id: " . $unit_id);
    }
}

error_log("DEBUG: Final unit_id = " . $unit_id);

try {
    // Initialize statistics with test values first
    $stats = [
        'active_mappings' => 0,
        'inactive_mappings' => 0,
        'total_equipments' => 0,
        'unmapped_equipments' => 0
    ];
    
    // Debug the input
    error_log("DEBUG: Received unit_id = " . $unit_id);
    error_log("DEBUG: Session user_id = " . $_SESSION['user_id']);
    error_log("DEBUG: Session unit_id = " . (isset($_SESSION['unit_id']) ? $_SESSION['unit_id'] : 'not set'));
    error_log("DEBUG: is_super_admin = " . $_SESSION['is_super_admin']);
    
    // Determine unit ID to use
    $target_unit_id = null;
    if (!empty($unit_id) && is_numeric($unit_id)) {
        $target_unit_id = intval($unit_id);
    } elseif ($_SESSION['is_super_admin'] !== "Yes") {
        $target_unit_id = intval($_SESSION['unit_id']);
    } else {
        // For super admin, try to get the first unit
        $first_unit = DB::queryFirstField("SELECT unit_id FROM units ORDER BY unit_id LIMIT 1");
        $target_unit_id = intval($first_unit);
    }
    
    error_log("DEBUG: target_unit_id = " . $target_unit_id);
    
    // Simple queries without complex JOINs first
    $stats['total_equipments'] = intval(DB::queryFirstField("SELECT COUNT(*) FROM equipments WHERE unit_id = %i", $target_unit_id));
    error_log("DEBUG: total_equipments = " . $stats['total_equipments']);
    
    // Check if equipment_test_vendor_mapping table exists and has data
    $mapping_count = intval(DB::queryFirstField("SELECT COUNT(*) FROM equipment_test_vendor_mapping"));
    error_log("DEBUG: total mappings in system = " . $mapping_count);
    
    if ($mapping_count > 0) {
        // Try to get active mappings
        $stats['active_mappings'] = intval(DB::queryFirstField(
            "SELECT COUNT(*) FROM equipment_test_vendor_mapping etvm 
             INNER JOIN equipments e ON etvm.equipment_id = e.equipment_id 
             WHERE etvm.mapping_status = 'Active' AND e.unit_id = %i", 
            $target_unit_id
        ));
        
        // Try to get inactive mappings
        $stats['inactive_mappings'] = intval(DB::queryFirstField(
            "SELECT COUNT(*) FROM equipment_test_vendor_mapping etvm 
             INNER JOIN equipments e ON etvm.equipment_id = e.equipment_id 
             WHERE etvm.mapping_status != 'Active' AND e.unit_id = %i", 
            $target_unit_id
        ));
        
        // Unmapped equipments
        $stats['unmapped_equipments'] = intval(DB::queryFirstField(
            "SELECT COUNT(*) FROM equipments e 
             WHERE e.unit_id = %i AND e.equipment_id NOT IN (
                 SELECT DISTINCT equipment_id FROM equipment_test_vendor_mapping 
                 WHERE mapping_status = 'Active' AND equipment_id IS NOT NULL
             )", 
            $target_unit_id
        ));
    } else {
        // No mappings exist, so all equipments are unmapped
        $stats['unmapped_equipments'] = $stats['total_equipments'];
    }
    
    error_log("DEBUG: Final stats = " . json_encode($stats));
    
    // Return JSON response
    echo json_encode($stats);
    
} catch (Exception $e) {
    error_log("ETV mapping statistics error: " . $e->getMessage());
    error_log("ETV mapping statistics stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}

?>