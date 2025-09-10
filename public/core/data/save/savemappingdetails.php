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
class MappingInputValidator {
    public static function validateMappingData($mode) {
        $required_fields = ['equipment_id', 'test_id', 'test_type', 'frequency_label', 'vendor_id', 'mapping_status'];
        
        if ($mode === 'modify') {
            $required_fields[] = 'mapping_id';
        }
        
        $validated_data = [];
        
        foreach ($required_fields as $field) {
            $value = safe_get($field, 'string', '');
            
            if (empty($value)) {
                throw new InvalidArgumentException("Missing required field: $field");
            }
            
            // Additional XSS detection on critical fields
            if (in_array($field, ['test_type', 'mapping_status']) && 
                XSSPrevention::detectXSS($value)) {
                XSSPrevention::logXSSAttempt($value, 'save_mapping_details');
                throw new InvalidArgumentException("Invalid input detected in $field");
            }
            
            $validated_data[$field] = $value;
        }
        
        // Validate numeric fields
        if (!is_numeric($validated_data['equipment_id'])) {
            throw new InvalidArgumentException("Invalid equipment ID");
        }
        
        if (!is_numeric($validated_data['vendor_id'])) {
            throw new InvalidArgumentException("Invalid vendor ID");
        }
        
        if ($mode === 'modify' && !is_numeric($validated_data['mapping_id'])) {
            throw new InvalidArgumentException("Invalid mapping ID");
        }
        
        // Process test_id - extract numeric part before hyphen if exists
        $test_id = $validated_data['test_id'];
        if (strpos($test_id, '-') !== false) {
            $test_id = substr($test_id, 0, strpos($test_id, '-'));
        }
        
        if (!is_numeric($test_id)) {
            throw new InvalidArgumentException("Invalid test ID");
        }
        
        $validated_data['processed_test_id'] = $test_id;
        
        return $validated_data;
    }
}

// Get safe input values
$mode = safe_get('mode', 'string', '');

if ($mode === 'add') {
    try {
        // Validate input data
        $validated_data = MappingInputValidator::validateMappingData('add');
        
        // Execute secure transaction
        $result = executeSecureTransaction(function() use ($validated_data) {
            // Insert mapping record
            DB::insert('equipment_test_vendor_mapping', [
                'equipment_id' => intval($validated_data['equipment_id']),
                'test_id' => $validated_data['processed_test_id'],
                'test_type' => $validated_data['test_type'],
                'frequency_label' => $validated_data['frequency_label'],
                'vendor_id' => intval($validated_data['vendor_id']),
                'mapping_status' => $validated_data['mapping_status']
            ]);
            
            $mapping_id = DB::insertId();
            
            if ($mapping_id <= 0) {
                throw new Exception("Failed to insert mapping record");
            }
            
            // Insert log entry
            DB::insert('log', [
                'change_type' => 'master_add_etv',
                'table_name' => 'equipment_test_vendor_mapping',
                'change_description' => 'Added a new mapping. Mapping ID:' . $mapping_id,
                'change_by' => $_SESSION['user_id'],
                'unit_id' => $_SESSION['unit_id']
            ]);
            
            return $mapping_id;
        });
        
        if ($result) {
            echo "success";
        } else {
            echo "failure";
        }
        
    } catch (InvalidArgumentException $e) {
        error_log("Mapping validation error: " . $e->getMessage());
        echo json_encode(['error' => $e->getMessage()]);
    } catch (Exception $e) {
        error_log("Mapping add error: " . $e->getMessage());
        
        // Check for duplicate entry error
        if (strpos($e->getMessage(), 'Duplicate entry') !== false && 
            strpos($e->getMessage(), 'equipment_id_UNIQUE') !== false) {
            echo json_encode(['error' => 'This mapping already exists. A mapping for this equipment and test combination has already been configured.']);
        } else {
            echo json_encode(['error' => 'Database error occurred. Please contact support if this issue persists.']);
        }
    }
}
else if ($mode === 'modify') {
    try {
        // Validate input data
        $validated_data = MappingInputValidator::validateMappingData('modify');
        
        // Check vendor change for all tests flag
        $vendor_change_for_all = safe_get('vendorchangeforalltests', 'int', 0);
        
        // Execute secure transaction
        $result = executeSecureTransaction(function() use ($validated_data, $vendor_change_for_all) {
            // Update primary mapping record
            DB::query(
                "UPDATE equipment_test_vendor_mapping SET 
                equipment_id = %i, 
                test_id = %s,
                test_type = %s,
                frequency_label = %s, 
                vendor_id = %i,
                mapping_status = %s  
                WHERE mapping_id = %i",
                intval($validated_data['equipment_id']),
                $validated_data['processed_test_id'],
                $validated_data['test_type'],
                $validated_data['frequency_label'],
                intval($validated_data['vendor_id']),
                $validated_data['mapping_status'],
                intval($validated_data['mapping_id'])
            );
            
            $affected_rows = DB::affectedRows();
            
            if ($affected_rows === 0) {
                throw new Exception("No mapping record was updated - mapping may not exist");
            }
            
            $log_change_description = 'Modified an existing mapping. Mapping ID:' . $validated_data['mapping_id'];
            
            // Handle vendor change for all tests if requested
            if ($vendor_change_for_all == 1) {
                DB::query(
                    "UPDATE equipment_test_vendor_mapping SET vendor_id = %i 
                    WHERE equipment_id = %i AND vendor_id != 0",
                    intval($validated_data['vendor_id']),
                    intval($validated_data['equipment_id'])
                );
                
                $log_change_description = 'Modified an existing mapping. Mapping ID:' . $validated_data['mapping_id'] . 
                                        ' Vendor changed for all tests. Equipment ID:' . $validated_data['equipment_id'];
            }
            
            // Insert log entry
            DB::insert('log', [
                'change_type' => 'master_update_etv',
                'table_name' => 'equipment_test_vendor_mapping',
                'change_description' => $log_change_description,
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
        error_log("Mapping validation error: " . $e->getMessage());
        echo json_encode(['error' => $e->getMessage()]);
    } catch (Exception $e) {
        error_log("Mapping modify error: " . $e->getMessage());
        
        // Check for duplicate entry error
        if (strpos($e->getMessage(), 'Duplicate entry') !== false && 
            strpos($e->getMessage(), 'equipment_id_UNIQUE') !== false) {
            echo json_encode(['error' => 'This mapping already exists. A mapping for this equipment and test combination has already been configured.']);
        } else {
            echo json_encode(['error' => 'Database error occurred. Please contact support if this issue persists.']);
        }
    }
} else {
    echo json_encode(['error' => 'Invalid mode specified']);
}

?>