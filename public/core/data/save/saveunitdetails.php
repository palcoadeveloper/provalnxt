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

// Check for superadmin role
if (!isset($_SESSION['is_super_admin']) || $_SESSION['is_super_admin'] !== 'Yes') {
    http_response_code(403);
    echo json_encode(['error' => 'Access Denied: Superadmin access required.']);
    exit();
}

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
class UnitDetailsValidator {
    public static function validateUnitData($mode) {
        $required_fields = ['unit_name', 'unit_status', 'primary_test_id', 'validation_scheduling_logic'];
        
        if ($mode === 'modify') {
            $required_fields[] = 'unit_id';
        } else if ($mode === 'add') {
            $required_fields[] = 'unit_id_input';
        }
        
        $validated_data = [];
        
        foreach ($required_fields as $field) {
            if (!isset($_GET[$field]) || trim($_GET[$field]) === '') {
                throw new InvalidArgumentException("Field $field is required");
            }
            $validated_data[$field] = trim($_GET[$field]);
        }
        
        // Validate unit_name
        if (strlen($validated_data['unit_name']) > 100) {
            throw new InvalidArgumentException("Unit name must be 100 characters or less");
        }
        
        // Validate unit_status
        if (!in_array($validated_data['unit_status'], ['Active', 'Inactive'])) {
            throw new InvalidArgumentException("Invalid unit status");
        }
        
        // Validate validation_scheduling_logic
        if (!in_array($validated_data['validation_scheduling_logic'], ['dynamic', 'fixed'])) {
            throw new InvalidArgumentException("Invalid validation scheduling logic");
        }
        
        // Validate unit_id_input for add mode
        if ($mode === 'add' && isset($validated_data['unit_id_input'])) {
            if (!is_numeric($validated_data['unit_id_input'])) {
                throw new InvalidArgumentException("Unit ID must be numeric");
            }
            if ($validated_data['unit_id_input'] <= 0) {
                throw new InvalidArgumentException("Unit ID must be a positive number");
            }
        }
        
        // Validate optional fields
        $optional_fields = ['secondary_test_id', 'two_factor_enabled', 
                           'otp_validity_minutes', 'otp_digits', 'otp_resend_delay_seconds'];
        
        foreach ($optional_fields as $field) {
            if (isset($_GET[$field]) && trim($_GET[$field]) !== '') {
                $validated_data[$field] = trim($_GET[$field]);
            }
        }
        
        // Validate primary test ID (required)
        if (empty($validated_data['primary_test_id']) || !is_numeric($validated_data['primary_test_id'])) {
            throw new InvalidArgumentException("Primary Test is required and must be valid");
        }
        
        // Validate secondary test ID if provided
        if (!empty($validated_data['secondary_test_id'])) {
            if (!is_numeric($validated_data['secondary_test_id'])) {
                throw new InvalidArgumentException("Invalid secondary test ID");
            }
            // Secondary test cannot be selected without primary test
            if (empty($validated_data['primary_test_id'])) {
                throw new InvalidArgumentException("Secondary Test cannot be selected without Primary Test");
            }
        }
        
        // Validate 2FA settings
        if (isset($validated_data['two_factor_enabled'])) {
            if (!in_array($validated_data['two_factor_enabled'], ['Yes', 'No'])) {
                throw new InvalidArgumentException("Invalid two factor enabled value");
            }
            
            // If 2FA is enabled, validate related fields
            if ($validated_data['two_factor_enabled'] === 'Yes') {
                if (!isset($validated_data['otp_validity_minutes']) || 
                    !is_numeric($validated_data['otp_validity_minutes']) ||
                    $validated_data['otp_validity_minutes'] < 1 || 
                    $validated_data['otp_validity_minutes'] > 15) {
                    throw new InvalidArgumentException("OTP validity must be between 1-15 minutes");
                }
                
                if (!isset($validated_data['otp_digits']) || 
                    !is_numeric($validated_data['otp_digits']) ||
                    $validated_data['otp_digits'] < 4 || 
                    $validated_data['otp_digits'] > 8) {
                    throw new InvalidArgumentException("OTP digits must be between 4-8");
                }
                
                if (!isset($validated_data['otp_resend_delay_seconds']) || 
                    !is_numeric($validated_data['otp_resend_delay_seconds']) ||
                    $validated_data['otp_resend_delay_seconds'] < 30 || 
                    $validated_data['otp_resend_delay_seconds'] > 300) {
                    throw new InvalidArgumentException("OTP resend delay must be between 30-300 seconds");
                }
            }
        }
        
        return $validated_data;
    }
}

try {
    // Regenerate CSRF token for next request
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    
    $mode = $_GET['mode'] ?? '';
    if (!in_array($mode, ['add', 'modify'])) {
        throw new InvalidArgumentException('Invalid operation mode');
    }
    
    // Validate input data
    $validated_data = UnitDetailsValidator::validateUnitData($mode);
    
    // Prepare data for database
    $unit_data = [
        'unit_name' => $validated_data['unit_name'],
        'unit_status' => $validated_data['unit_status'],
        'validation_scheduling_logic' => $validated_data['validation_scheduling_logic'],
        'primary_test_id' => !empty($validated_data['primary_test_id']) ? intval($validated_data['primary_test_id']) : null,
        'secondary_test_id' => !empty($validated_data['secondary_test_id']) ? intval($validated_data['secondary_test_id']) : null,
        'two_factor_enabled' => $validated_data['two_factor_enabled'] ?? 'No',
        'otp_validity_minutes' => isset($validated_data['otp_validity_minutes']) ? intval($validated_data['otp_validity_minutes']) : 5,
        'otp_digits' => isset($validated_data['otp_digits']) ? intval($validated_data['otp_digits']) : 6,
        'otp_resend_delay_seconds' => isset($validated_data['otp_resend_delay_seconds']) ? intval($validated_data['otp_resend_delay_seconds']) : 60
    ];
    
    // Execute database operation in secure transaction
    $result = executeSecureTransaction(function() use ($mode, $validated_data, $unit_data) {
        if ($mode === 'add') {
            $custom_unit_id = intval($validated_data['unit_id_input']);
            
            // Check for duplicate unit_id
            $existing_id = DB::queryFirstRow("SELECT unit_id FROM units WHERE unit_id = %i", $custom_unit_id);
            if ($existing_id) {
                throw new InvalidArgumentException("Unit ID already exists. Please choose a different ID.");
            }
            
            // Check for duplicate unit name
            $existing_name = DB::queryFirstRow("SELECT unit_id FROM units WHERE unit_name = %s", $unit_data['unit_name']);
            if ($existing_name) {
                throw new InvalidArgumentException("Unit with this name already exists");
            }
            
            // Add the custom unit_id to the data
            $unit_data['unit_id'] = $custom_unit_id;
            $unit_data['unit_creation_datetime'] = date('Y-m-d H:i:s');
            
            // Use insertUpdate to handle the custom primary key
            $result = DB::insertUpdate('units', $unit_data);
            if (!$result) {
                throw new InvalidArgumentException("Failed to create unit record");
            }
            
            // Insert log entry
            DB::insert('log', [
                'change_type' => 'master_add_unit',
                'table_name' => 'units',
                'change_description' => 'Added a new unit. Unit ID:' . $custom_unit_id,
                'change_by' => $_SESSION['user_id'],
                'unit_id' => $_SESSION['unit_id']
            ]);
            
            return $custom_unit_id;
        } else { // modify
            $unit_id = intval($validated_data['unit_id']);
            
            // Check if unit exists
            $existing = DB::queryFirstRow("SELECT unit_id FROM units WHERE unit_id = %i", $unit_id);
            if (!$existing) {
                throw new InvalidArgumentException("Unit not found");
            }
            
            // Check for duplicate unit name (excluding current record)
            $duplicate = DB::queryFirstRow("SELECT unit_id FROM units WHERE unit_name = %s AND unit_id != %i", 
                                         $unit_data['unit_name'], $unit_id);
            if ($duplicate) {
                throw new InvalidArgumentException("Unit with this name already exists");
            }
            
            $unit_data['unit_last_modification_datetime'] = date('Y-m-d H:i:s');
            
            $affected_rows = DB::update('units', $unit_data, 'unit_id=%i', $unit_id);
            if ($affected_rows === false) {
                throw new InvalidArgumentException("Failed to update unit record");
            }
            
            // Insert log entry
            DB::insert('log', [
                'change_type' => 'master_update_unit',
                'table_name' => 'units',
                'change_description' => 'Modified an existing unit. Unit ID:' . $unit_id,
                'change_by' => $_SESSION['user_id'],
                'unit_id' => $_SESSION['unit_id']
            ]);
            
            return $unit_id;
        }
    }, 'unit_' . $mode);
    
    if ($result) {
        echo json_encode([
            'status' => 'success',
            'message' => 'Unit ' . ($mode === 'add' ? 'created' : 'updated') . ' successfully',
            'csrf_token' => $_SESSION['csrf_token']
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Operation failed',
            'csrf_token' => $_SESSION['csrf_token']
        ]);
    }
    
} catch (InvalidArgumentException $e) {
    error_log("Unit validation error: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'csrf_token' => $_SESSION['csrf_token']
    ]);
} catch (Exception $e) {
    error_log("Unit operation error: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'csrf_token' => $_SESSION['csrf_token']
    ]);
}
?>