<?php
require_once('../../config/config.php');

// Validate session timeout
require_once('../../security/session_timeout_middleware.php');
validateActiveSession();

// Use centralized session validation
require_once('../../security/session_validation.php');
validateUserSession();

require_once("../../config/db.class.php");

// Set content type to JSON
header('Content-Type: application/json');

// Additional security validation - validate user type
$userType = $_SESSION['logged_in_user'] ?? '';
if (!in_array($userType, ['employee', 'vendor'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

try {
    // Get input parameters
    $test_val_wf_id = $_POST['test_val_wf_id'] ?? '';
    $data_entry_mode = $_POST['data_entry_mode'] ?? '';
    $val_wf_id = $_POST['val_wf_id'] ?? '';
    
    // Validate required parameters
    if (empty($test_val_wf_id) || empty($data_entry_mode)) {
        throw new InvalidArgumentException("Missing required parameters: test_val_wf_id or data_entry_mode");
    }
    
    // Validate mode value
    if (!in_array($data_entry_mode, ['online', 'offline'])) {
        throw new InvalidArgumentException("Invalid data entry mode. Must be 'online' or 'offline'");
    }
    
    // Get user information from session
    $user_id = intval($_SESSION['user_id'] ?? 1);
    $user_unit_id = getUserUnitId();
    
    // Ensure unit_id is a valid integer for database
    if ($user_unit_id === '' || $user_unit_id === null) {
        $user_unit_id = 0;
    }
    $user_unit_id = intval($user_unit_id);
    
    // Get current test data for validation
    $currentData = DB::queryFirstRow("
        SELECT ts.data_entry_mode, ts.test_wf_current_stage, t.paper_on_glass_enabled 
        FROM tbl_test_schedules_tracking ts 
        INNER JOIN tests t ON t.test_id = ts.test_id
        WHERE ts.test_wf_id = %s
    ", $test_val_wf_id);
    
    if (!$currentData) {
        throw new Exception("Test workflow not found: " . $test_val_wf_id);
    }
    
    // Validate workflow stage for offline mode switching
    if ($data_entry_mode === 'offline') {
        $allowed_offline_stages = ['1', '3B', '4B'];
        $current_stage = $currentData['test_wf_current_stage'];
        
        if (!in_array($current_stage, $allowed_offline_stages)) {
            throw new InvalidArgumentException(
                "Offline mode is only available for workflow stages 1, 3B, or 4B. Current stage: " . $current_stage
            );
        }
        
        // Ensure paper-on-glass is enabled
        if (($currentData['paper_on_glass_enabled'] ?? 'No') !== 'Yes') {
            throw new InvalidArgumentException("Offline mode is only available for tests with Paper-on-Glass enabled");
        }
        
        // Ensure current mode is online (cannot switch if already offline)
        if (($currentData['data_entry_mode'] ?? 'online') === 'offline') {
            throw new InvalidArgumentException("Test is already in offline mode and cannot be changed back");
        }
    }
    
    // Prevent switching back to online if currently offline
    if ($data_entry_mode === 'online' && ($currentData['data_entry_mode'] ?? 'online') === 'offline') {
        throw new InvalidArgumentException("Cannot switch back to online mode once offline mode has been selected");
    }
    
    // Update the data entry mode in tbl_test_schedules_tracking
    $result = DB::update('tbl_test_schedules_tracking', [
        'data_entry_mode' => $data_entry_mode,
        'last_modified_date_time' => date('Y-m-d H:i:s')
    ], 'test_wf_id = %s', $test_val_wf_id);
    
    if (!$result) {
        throw new Exception("Failed to save data entry mode");
    }
    
    // Insert enhanced log entry with validation and test workflow IDs
    $log_description = sprintf(
        'Data entry mode selected as %s for Validation Workflow ID: %s, Test Workflow ID: %s',
        strtoupper($data_entry_mode),
        $val_wf_id ?: 'N/A',
        $test_val_wf_id
    );
    
    DB::insert('log', [
        'change_type' => 'data_entry_mode_selection',
        'table_name' => 'tbl_test_schedules_tracking',
        'change_description' => $log_description,
        'change_by' => $user_id,
        'unit_id' => $user_unit_id
    ]);
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Data entry mode saved successfully',
        'mode' => $data_entry_mode
    ]);
    
} catch (InvalidArgumentException $e) {
    error_log("Validation error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to save data entry mode. Please try again. Error: ' . $e->getMessage()
    ]);
}
?>