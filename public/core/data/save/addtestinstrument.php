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
require_once('../../security/secure_transaction_wrapper.php');

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
    // Debug logging
    error_log("Add instrument request received");
    error_log("POST data: " . print_r($_POST, true));
    error_log("Session user: " . ($_SESSION['logged_in_user'] ?? 'not set'));
    
    // Validate CSRF token
    $csrf_token = secure_post('csrf_token', 'string');
    if (!$csrf_token || $csrf_token !== $_SESSION['csrf_token']) {
        error_log("CSRF token validation failed. Received: $csrf_token, Expected: " . ($_SESSION['csrf_token'] ?? 'not set'));
        throw new InvalidArgumentException("Invalid security token");
    }
    
    // Secure input validation
    $test_val_wf_id = secure_post('test_val_wf_id', 'string');
    $instrument_id = secure_post('instrument_id', 'string');
    
    // Validate required parameters
    if (empty($test_val_wf_id) || !$instrument_id) {
        throw new InvalidArgumentException("Missing required parameters");
    }
    
    // Get user information
    $user_id = $_SESSION['user_id'];
    $user_unit_id = getUserUnitId();
    
    // Verify instrument exists and is accessible
    if (isVendor()) {
        // Vendors can access instruments across units
        $instrument_check = DB::queryFirstRow(
            "SELECT instrument_id, instrument_type, instrument_status 
             FROM instruments 
             WHERE instrument_id = %s 
             AND instrument_status = 'Active'",
            $instrument_id
        );
    } else {
        // Employees are restricted to their unit (no unit restriction in current table)
        $instrument_check = DB::queryFirstRow(
            "SELECT instrument_id, instrument_type, instrument_status 
             FROM instruments 
             WHERE instrument_id = %s 
             AND instrument_status = 'Active'",
            $instrument_id
        );
    }
    
    if (!$instrument_check) {
        throw new InvalidArgumentException("Invalid instrument or instrument not accessible");
    }
    
    // Check if instrument is already added to this test
    $existing_mapping = DB::queryFirstRow(
        "SELECT mapping_id FROM test_instruments 
         WHERE test_val_wf_id = %s 
         AND instrument_id = %s 
         AND is_active = 1",
        $test_val_wf_id,
        $instrument_id
    );
    
    if ($existing_mapping) {
        throw new InvalidArgumentException("Instrument is already added to this test");
    }
    
    // Execute secure transaction to add instrument
    $result = executeSecureTransaction(function() use ($test_val_wf_id, $instrument_id, $user_id, $user_unit_id, $instrument_check) {
        // Insert instrument mapping
        $mapping_id = DB::insert('test_instruments', [
            'test_val_wf_id' => $test_val_wf_id,
            'instrument_id' => $instrument_id,
            'added_by' => $user_id,
            'unit_id' => $user_unit_id,
            'is_active' => 1
        ]);
        
        if (!$mapping_id) {
            throw new Exception("Failed to add instrument to test");
        }
        
        // Insert log entry
        DB::insert('log', [
            'change_type' => 'test_instrument_add',
            'table_name' => 'test_instruments',
            'change_description' => sprintf(
                'Added instrument %s (%s) to test workflow %s',
                $instrument_check['instrument_type'],
                $instrument_check['instrument_id'],
                $test_val_wf_id
            ),
            'change_by' => $user_id,
            'unit_id' => $user_unit_id
        ]);
        
        return $mapping_id;
    });
    
    if ($result) {
        // Generate new CSRF token
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Instrument added successfully',
            'mapping_id' => $result,
            'csrf_token' => $_SESSION['csrf_token']
        ]);
    } else {
        throw new Exception("Failed to add instrument");
    }
    
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
} catch (Exception $e) {
    // Log error and return generic message
    error_log("Add test instrument error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to add instrument. Please try again.'
    ]);
}
?>