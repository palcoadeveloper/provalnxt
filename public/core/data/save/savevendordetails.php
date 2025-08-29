<?php 
session_start();


// Load configuration first
require_once(__DIR__ . '/../../config/config.php');
// Include XSS protection middleware (auto-initializes)
require_once('../../security/xss_integration_middleware.php');

// Validate session timeout
require_once('../../security/session_timeout_middleware.php');
validateActiveSession();

// Include rate limiting
require_once('../../security/rate_limiting_utils.php');

// Include secure transaction wrapper
require_once('../../security/secure_transaction_wrapper.php');

include_once("../../config/db.class.php");
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
class VendorDetailsValidator {
    public static function validateVendorData($mode) {
        $required_fields = ['vendor_name', 'spoc_name', 'spoc_mobile', 'spoc_email', 'vendor_status'];
        
        if ($mode === 'modify') {
            $required_fields[] = 'vendor_id';
        }
        
        $validated_data = [];
        
        foreach ($required_fields as $field) {
            $value = safe_get($field, 'string', '');
            
            if (empty($value)) {
                throw new InvalidArgumentException("Missing required field: $field");
            }
            
            // XSS detection on critical fields
            if (in_array($field, ['vendor_name', 'spoc_name']) && 
                XSSPrevention::detectXSS($value)) {
                XSSPrevention::logXSSAttempt($value, 'save_vendor_details');
                throw new InvalidArgumentException("Invalid input detected in $field");
            }
            
            $validated_data[$field] = $value;
        }
        
        // Validate email format
        if (!filter_var($validated_data['spoc_email'], FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("Invalid email format");
        }
        
        // Validate mobile format (basic numeric check)
        if (!preg_match('/^[0-9+\-\s()]+$/', $validated_data['spoc_mobile'])) {
            throw new InvalidArgumentException("Invalid mobile number format");
        }
        
        // Validate vendor status
        if (!in_array($validated_data['vendor_status'], ['Active', 'Inactive'])) {
            throw new InvalidArgumentException("Invalid vendor status");
        }
        
        // Validate vendor_id for modify mode
        if ($mode === 'modify') {
            $vendor_id = safe_get('vendor_id', 'int', 0);
            if ($vendor_id <= 0) {
                throw new InvalidArgumentException("Invalid vendor ID");
            }
            $validated_data['vendor_id'] = $vendor_id;
        }
        
        return $validated_data;
    }
}

// Get safe input values
$mode = safe_get('mode', 'string', '');

if ($mode === 'add') {
    try {
        // Validate input data
        $validated_data = VendorDetailsValidator::validateVendorData('add');
        
        // Execute secure transaction
        $result = executeSecureTransaction(function() use ($validated_data) {
            // Insert vendor record
            DB::insert('vendors', [
                'vendor_name' => $validated_data['vendor_name'],
                'vendor_spoc_name' => $validated_data['spoc_name'],
                'vendor_spoc_mobile' => $validated_data['spoc_mobile'],
                'vendor_spoc_email' => $validated_data['spoc_email'],
                'vendor_status' => $validated_data['vendor_status']
            ]);
            
            $vendor_id = DB::insertId();
            
            if ($vendor_id <= 0) {
                throw new Exception("Failed to insert vendor record");
            }
            
            // Insert log entry
            DB::insert('log', [
                'change_type' => 'master_add_vendors',
                'table_name' => 'vendors',
                'change_description' => 'Added a new vendor. Vendor ID:' . $vendor_id,
                'change_by' => $_SESSION['user_id'],
                'unit_id' => $_SESSION['unit_id']
            ]);
            
            return $vendor_id;
        });
        
        if ($result > 0) {
            echo "success";
        } else {
            echo "failure";
        }
        
    } catch (InvalidArgumentException $e) {
        error_log("Vendor validation error: " . $e->getMessage());
        echo json_encode(['error' => $e->getMessage()]);
    } catch (Exception $e) {
        error_log("Vendor add error: " . $e->getMessage());
        echo json_encode(['error' => 'Database error occurred']);
    }
}
else if ($mode === 'modify') {
    try {
        // Validate input data
        $validated_data = VendorDetailsValidator::validateVendorData('modify');
        
        // Execute secure transaction
        $result = executeSecureTransaction(function() use ($validated_data) {
            // Update vendor record
            DB::query(
                "UPDATE vendors SET 
                vendor_name = %s, 
                vendor_spoc_name = %s,
                vendor_spoc_mobile = %s, 
                vendor_spoc_email = %s,
                vendor_status = %s  
                WHERE vendor_id = %i",
                $validated_data['vendor_name'],
                $validated_data['spoc_name'],
                $validated_data['spoc_mobile'],
                $validated_data['spoc_email'],
                $validated_data['vendor_status'],
                $validated_data['vendor_id']
            );
            
            $affected_rows = DB::affectedRows();
            
            if ($affected_rows === 0) {
                throw new Exception("No vendor record was updated - vendor may not exist");
            }
            
            // Insert log entry
            DB::insert('log', [
                'change_type' => 'master_update_vendors',
                'table_name' => 'vendors',
                'change_description' => 'Modified an existing vendor. Vendor ID:' . $validated_data['vendor_id'],
                'change_by' => $_SESSION['user_id'],
                'unit_id' => $_SESSION['unit_id']
            ]);
            
            return $affected_rows;
        });
        
        if ($result > 0) {
            echo "success";
        } else {
            echo "failure";
        }
        
    } catch (InvalidArgumentException $e) {
        error_log("Vendor validation error: " . $e->getMessage());
        echo json_encode(['error' => $e->getMessage()]);
    } catch (Exception $e) {
        error_log("Vendor modify error: " . $e->getMessage());
        echo json_encode(['error' => 'Database error occurred']);
    }
} else {
    echo json_encode(['error' => 'Invalid mode specified']);
}

?>