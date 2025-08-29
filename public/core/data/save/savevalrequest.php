<?php 

// Load configuration first
require_once(__DIR__ . '/../../config/config.php');

// Session is already started by config.php via session_init.php
// Include XSS protection middleware (auto-initializes)
require_once('../../security/xss_integration_middleware.php');

// Validate session timeout
require_once('../../security/session_timeout_middleware.php');
validateActiveSession();

// Include rate limiting
require_once('../../security/rate_limiting_utils.php');

// Include secure transaction wrapper
require_once('../../security/secure_transaction_wrapper.php');

require_once '../../config/db.class.php';
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
class ValidationRequestValidator {
    public static function validateValidationRequest() {
        $required_fields = ['unitid', 'equipment_id', 'startdate'];
        
        $validated_data = [];
        
        foreach ($required_fields as $field) {
            $value = safe_get($field, 'string', '');
            
            if (empty($value)) {
                throw new InvalidArgumentException("Missing required field: $field");
            }
            
            $validated_data[$field] = $value;
        }
        
        // Validate numeric fields
        if (!is_numeric($validated_data['unitid'])) {
            throw new InvalidArgumentException("Invalid unit ID");
        }
        
        if (!is_numeric($validated_data['equipment_id'])) {
            throw new InvalidArgumentException("Invalid equipment ID");
        }
        
        // Handle date format conversion - the frontend sends dates in dd.mm.yyyy format
        $startdate = $validated_data['startdate'];
        
        // Try parsing dd.mm.yyyy format first
        $date = DateTime::createFromFormat('d.m.Y', $startdate);
        if (!$date) {
            // Fallback to other common formats
            $date = DateTime::createFromFormat('Y-m-d', $startdate);
            if (!$date) {
                // Try strtotime as last resort
                $timestamp = strtotime($startdate);
                if ($timestamp === false) {
                    throw new InvalidArgumentException("Invalid start date format. Expected dd.mm.yyyy or yyyy-mm-dd");
                }
                $date = new DateTime();
                $date->setTimestamp($timestamp);
            }
        }
        
        // Convert to proper format for stored procedure
        $validated_data['formatted_startdate'] = $date->format('Y-m-d');
        
        return $validated_data;
    }
}

try {
    // Validate input data
    $validated_data = ValidationRequestValidator::validateValidationRequest();
    
    // Execute secure transaction
    $result = executeSecureTransaction(function() use ($validated_data) {
        // Call stored procedure for adding ad-hoc validation request
        // Using parameterized query to prevent SQL injection
        $addhoc_v_sch_id = DB::queryFirstField(
            "CALL USP_ADDVALREQADHOC(%i, %i, %s, %i)",
            intval($validated_data['unitid']),
            intval($validated_data['equipment_id']),
            $validated_data['formatted_startdate'],
            intval($_SESSION['user_id'])
        );
        
        if (!$addhoc_v_sch_id || $addhoc_v_sch_id <= 0) {
            throw new Exception("Failed to create ad-hoc validation request");
        }
        
        // Get the validation workflow ID
        $adhoc_val_wf_id = DB::queryFirstField(
            'SELECT val_wf_id FROM tbl_val_schedules WHERE val_sch_id = %i',
            intval($addhoc_v_sch_id)
        );
        
        if (!$adhoc_val_wf_id) {
            throw new Exception("Failed to retrieve validation workflow ID");
        }
        
        // Insert log entry
        DB::insert('log', [
            'change_type' => 'tran_valadhoc_add',
            'table_name' => 'tbl_val_schedules',
            'change_description' => 'Added ad-hoc validation request. Val WF ID:' . $adhoc_val_wf_id,
            'change_by' => $_SESSION['user_id'],
            'unit_id' => $_SESSION['unit_id']
        ]);
        
        return $addhoc_v_sch_id;
    });
    
    if ($result > 0) {
        echo "success";
    } else {
        echo "failure";
    }
    
} catch (InvalidArgumentException $e) {
    error_log("Validation request validation error: " . $e->getMessage());
    echo json_encode(['error' => $e->getMessage()]);
} catch (Exception $e) {
    error_log("Validation request error: " . $e->getMessage());
    echo json_encode(['error' => 'Database error occurred: ' . $e->getMessage()]);
}

?>