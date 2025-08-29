<?php

// Load configuration first
require_once(__DIR__ . '/../../config/config.php');
// Include XSS protection middleware (auto-initializes)
require_once('../../security/xss_integration_middleware.php');

if(!isset($_SESSION)) {
    session_start();
    
    // Validate session timeout
    require_once('../../security/session_timeout_middleware.php');
    validateActiveSession();
} 

// Include rate limiting
require_once('../../security/rate_limiting_utils.php');

// Include secure transaction wrapper
require_once('../../security/secure_transaction_wrapper.php');

// Include error logging utility
require_once('../../error/error_logger.php');

include_once '../../config/db.class.php';
date_default_timezone_set("Asia/Kolkata");

// Apply rate limiting for form submissions
if (!RateLimiter::checkRateLimit('form_submission')) {
    http_response_code(429);
    echo json_encode(['error' => 'Rate limit exceeded. Too many form submissions.']);
    exit();
}

// Validate CSRF token for POST requests using simple approach (consistent with rest of application)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid CSRF token']);
        exit();
    }
}

// Input validation helper
class TrainingUpdateValidator {
    public static function validateTrainingUpdateData() {
        $required_fields = ['record_id', 'val_wf_id'];
        $validated_data = [];
        
        foreach ($required_fields as $field) {
            if (!isset($_POST[$field]) || empty($_POST[$field])) {
                throw new InvalidArgumentException("Missing required field: $field");
            }
            
            $value = $_POST[$field];
            
            // XSS detection on critical fields
            if (in_array($field, ['val_wf_id']) && XSSPrevention::detectXSS($value)) {
                XSSPrevention::logXSSAttempt($value, 'update_training_details');
                throw new InvalidArgumentException("Invalid input detected in $field");
            }
            
            $validated_data[$field] = $value;
        }
        
        // Validate numeric fields
        if (!is_numeric($validated_data['record_id'])) {
            throw new InvalidArgumentException("Invalid record ID");
        }
        
        $validated_data['record_id'] = intval($validated_data['record_id']);
        
        return $validated_data;
    }
}

try {
    // Validate input data
    $validated_data = TrainingUpdateValidator::validateTrainingUpdateData();
    
    // Execute secure transaction
    $result = executeSecureTransaction(function() use ($validated_data) {
        // Check if record exists and is currently active
        $existing_record = DB::queryFirstRow(
            "SELECT record_status FROM tbl_training_details WHERE id = %i",
            $validated_data['record_id']
        );
        
        if (!$existing_record) {
            throw new Exception("Training record not found");
        }
        
        if ($existing_record['record_status'] === 'Inactive') {
            throw new Exception("Training record is already inactive");
        }
        
        // Update training record status to inactive
        DB::query(
            "UPDATE tbl_training_details SET record_status = 'Inactive' WHERE id = %i",
            $validated_data['record_id']
        );
        
        // Check if the update operation succeeded by verifying the record state
        $updated_record = DB::queryFirstRow(
            "SELECT record_status FROM tbl_training_details WHERE id = %i",
            $validated_data['record_id']
        );
        
        if (!$updated_record || $updated_record['record_status'] !== 'Inactive') {
            throw new Exception("Failed to update training record status");
        }
        
        $affected_rows = 1; // Successful update verified
        
        // Insert log entry
        $unit_id = !empty($_SESSION['unit_id']) ? $_SESSION['unit_id'] : 0;
        DB::insert('log', [
            'change_type' => 'tran_traindtls_removed',
            'table_name' => 'tbl_training_details',
            'change_description' => 'Training details removed. Record ID:' . $validated_data['record_id'] . 
                                  ' Val WF ID: ' . $validated_data['val_wf_id'],
            'change_by' => $_SESSION['user_id'],
            'unit_id' => $unit_id
        ]);
        
        return $affected_rows;
    });
    
    if ($result > 0) {
        echo json_encode([
            'status' => 'success',
            'message' => 'Training details removed successfully'
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to remove training details'
        ]);
    }
    
} catch (InvalidArgumentException $e) {
    logDatabaseError("Training update validation error: " . $e->getMessage(), [
        'operation_name' => 'update_training_details',
        'val_wf_id' => $_POST['val_wf_id'] ?? null,
        'record_id' => $_POST['record_id'] ?? null
    ]);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
} catch (Exception $e) {
    logDatabaseError("Training update error: " . $e->getMessage(), [
        'operation_name' => 'update_training_details',
        'val_wf_id' => $_POST['val_wf_id'] ?? null,
        'record_id' => $_POST['record_id'] ?? null
    ]);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error occurred'. $e->getMessage()
    ]);
}

?>