<?php
require_once('../../config/config.php');

// Session is already started by config.php via session_init.php

// Validate session timeout
require_once('../../security/session_timeout_middleware.php');
validateActiveSession();

// Use centralized session validation
require_once('../../security/session_validation.php');
validateUserSession();

require_once("../../config/db.class.php");
require_once('../../security/secure_query_wrapper.php');

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
    // Validate CSRF token
    $csrf_token = secure_post('csrf_token', 'string');
    if (!$csrf_token || $csrf_token !== $_SESSION['csrf_token']) {
        throw new InvalidArgumentException("Invalid security token");
    }
    
    // Secure input validation
    $mapping_id = secure_post('mapping_id', 'int');
    $test_val_wf_id = secure_post('test_val_wf_id', 'string');
    
    // Validate required parameters
    if (!$mapping_id || empty($test_val_wf_id)) {
        throw new InvalidArgumentException("Missing required parameters");
    }
    
    // Get user information
    $user_id = $_SESSION['user_id'];
    $user_unit_id = getUserUnitId();
    
    // Verify mapping exists and is accessible
    if (isVendor()) {
        // Vendors can access mappings across units they have access to
        $mapping_check = DB::queryFirstRow(
            "SELECT ti.mapping_id, ti.test_val_wf_id, i.instrument_type, i.instrument_id 
             FROM test_instruments ti
             INNER JOIN instruments i ON ti.instrument_id = i.instrument_id
             WHERE ti.mapping_id = %i 
             AND ti.test_val_wf_id = %s
             AND ti.is_active = 1",
            $mapping_id,
            $test_val_wf_id
        );
    } else {
        // Employees are restricted to their unit
        $mapping_check = DB::queryFirstRow(
            "SELECT ti.mapping_id, ti.test_val_wf_id, i.instrument_type, i.instrument_id 
             FROM test_instruments ti
             INNER JOIN instruments i ON ti.instrument_id = i.instrument_id
             WHERE ti.mapping_id = %i 
             AND ti.test_val_wf_id = %s
             AND ti.unit_id = %i
             AND ti.is_active = 1",
            $mapping_id,
            $test_val_wf_id,
            $user_unit_id
        );
    }
    
    if (!$mapping_check) {
        throw new InvalidArgumentException("Invalid mapping or mapping not accessible");
    }
    
    // Execute secure transaction to remove instrument (soft delete)
    $result = executeSecureTransaction(function() use ($mapping_id, $user_id, $user_unit_id, $mapping_check) {
        // Update mapping to mark as inactive (soft delete)
        $updated = DB::query(
            "UPDATE test_instruments 
             SET is_active = 0 
             WHERE mapping_id = %i",
            $mapping_id
        );
        
        if (!$updated) {
            throw new Exception("Failed to remove instrument from test");
        }
        
        // Insert log entry
        DB::insert('log', [
            'change_type' => 'test_instrument_remove',
            'table_name' => 'test_instruments',
            'change_description' => sprintf(
                'Removed instrument %s (%s) from test workflow %s',
                $mapping_check['instrument_type'],
                $mapping_check['instrument_id'],
                $mapping_check['test_val_wf_id']
            ),
            'change_by' => $user_id,
            'unit_id' => $user_unit_id
        ]);
        
        return true;
    });
    
    if ($result) {
        // Generate new CSRF token
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Instrument removed successfully',
            'csrf_token' => $_SESSION['csrf_token']
        ]);
    } else {
        throw new Exception("Failed to remove instrument");
    }
    
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
} catch (Exception $e) {
    // Log error and return generic message
    error_log("Remove test instrument error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to remove instrument. Please try again.'
    ]);
}
?>