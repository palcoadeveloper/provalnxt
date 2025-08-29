<?php 
session_start();


// Load configuration first
require_once(__DIR__ . '/../../config/config.php');
// Include XSS protection middleware (auto-initializes)
require_once('../../security/xss_integration_middleware.php');

// Validate session timeout
require_once(__DIR__ . '/../../security/session_timeout_middleware.php');
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
class InputValidator {
    public static function validateEquipmentData($mode) {
        $required_fields = [
            'equipment_code', 'unit_id', 'department_id', 'equipment_category', 
            'validation_frequency', 'area_served', 'section', 'design_acph', 
            'area_classification', 'area_classification_in_operation', 'equipment_type', 
            'design_cfm', 'equipment_status', 'equipment_addition_date'
        ];
        
        if ($mode === 'modify') {
            $required_fields[] = 'equipment_id';
        }
        
        $validated_data = [];
        
        foreach ($required_fields as $field) {
            $value = safe_get($field, 'string', '');
            
            if (empty($value) && !in_array($field, ['design_acph', 'design_cfm'])) {
                throw new InvalidArgumentException("Missing required field: $field");
            }
            
            // Additional XSS detection on critical fields
            if (in_array($field, ['equipment_code', 'area_served', 'section']) && 
                XSSPrevention::detectXSS($value)) {
                XSSPrevention::logXSSAttempt($value, 'save_equipment_details');
                throw new InvalidArgumentException("Invalid input detected in $field");
            }
            
            $validated_data[$field] = $value;
        }
        
        // Validate numeric fields
        if (!empty($validated_data['unit_id']) && !is_numeric($validated_data['unit_id'])) {
            throw new InvalidArgumentException("Invalid unit ID");
        }
        
        if (!empty($validated_data['department_id']) && !is_numeric($validated_data['department_id'])) {
            throw new InvalidArgumentException("Invalid department ID");
        }
        
        if ($mode === 'modify' && (!isset($validated_data['equipment_id']) || !is_numeric($validated_data['equipment_id']))) {
            throw new InvalidArgumentException("Invalid equipment ID");
        }
        
        return $validated_data;
    }
}

// Get safe input values
$mode = safe_get('mode', 'string', '');

if ($mode === 'add') {
    try {
        // Validate input data
        $validated_data = InputValidator::validateEquipmentData('add');
        
        // Execute secure transaction
        $result = executeSecureTransaction(function() use ($validated_data) {
            // Insert equipment record
            DB::insert('equipments', [
                'equipment_code' => $validated_data['equipment_code'],
                'unit_id' => intval($validated_data['unit_id']),
                'department_id' => intval($validated_data['department_id']),
                'equipment_category' => $validated_data['equipment_category'],
                'validation_frequency' => $validated_data['validation_frequency'],
                'area_served' => $validated_data['area_served'],
                'section' => $validated_data['section'],
                'design_acph' => $validated_data['design_acph'],
                'area_classification' => $validated_data['area_classification'],
                'area_classification_in_operation' => $validated_data['area_classification_in_operation'],
                'equipment_type' => $validated_data['equipment_type'],
                'design_cfm' => $validated_data['design_cfm'],
                'filteration_fresh_air' => safe_get('filteration_fresh_air', 'string', ''),
                'filteration_pre_filter' => safe_get('filteration_pre_filter', 'string', ''),
                'filteration_intermediate' => safe_get('filteration_intermediate', 'string', ''),
                'filteration_final_filter_plenum' => safe_get('filteration_final_filter_plenum', 'string', ''),
                'filteration_exhaust_pre_filter' => safe_get('filteration_exhaust_pre_filter', 'string', ''),
                'filteration_exhaust_final_filter' => safe_get('filteration_exhaust_final_filter', 'string', ''),
                'filteration_terminal_filter' => safe_get('filteration_terminal_filter', 'string', ''),
                'filteration_terminal_filter_on_riser' => safe_get('filteration_terminal_filter_on_riser', 'string', ''),
                'filteration_bibo_filter' => safe_get('filteration_bibo_filter', 'string', ''),
                'filteration_relief_filter' => safe_get('filteration_relief_filter', 'string', ''),
                'filteration_reativation_filter' => safe_get('filteration_reativation_filter', 'string', ''),
                'equipment_status' => $validated_data['equipment_status'],
                'equipment_addition_date' => $validated_data['equipment_addition_date']
            ]);
            
            $equipment_id = DB::insertId();
            
            if ($equipment_id <= 0) {
                throw new Exception("Failed to insert equipment record");
            }
            
            // Insert log entry
            DB::insert('log', [
                'change_type' => 'master_add_eq',
                'table_name' => 'equipments',
                'change_description' => 'Added a new equipment. Equipment ID:' . $equipment_id,
                'change_by' => $_SESSION['user_id'],
                'unit_id' => $_SESSION['unit_id']
            ]);
            
            return $equipment_id;
        });
        
        if ($result) {
            echo "success";
        } else {
            echo "failure";
        }
        
    } catch (InvalidArgumentException $e) {
        error_log("Equipment validation error: " . $e->getMessage());
        echo json_encode(['error' => $e->getMessage()]);
    } catch (Exception $e) {
        error_log("Equipment add error: " . $e->getMessage());
        echo json_encode(['error' => 'Database error occurred']);
    }
}
else if ($mode === 'modify') {
    try {
        // Validate input data
        $validated_data = InputValidator::validateEquipmentData('modify');
        
        // Execute secure transaction
        $result = executeSecureTransaction(function() use ($validated_data) {
            // Update equipment record
            DB::query(
                "UPDATE equipments SET
                equipment_code = %s,
                unit_id = %i,
                department_id = %i,
                equipment_category = %s,
                validation_frequency = %s,
                area_served = %s,
                section = %s,
                design_acph = %s,
                area_classification = %s,
                area_classification_in_operation = %s,
                equipment_type = %s,
                design_cfm = %s,
                filteration_fresh_air = %s,
                filteration_pre_filter = %s,
                filteration_intermediate = %s,
                filteration_final_filter_plenum = %s,
                filteration_exhaust_pre_filter = %s,
                filteration_exhaust_final_filter = %s,
                filteration_terminal_filter = %s,
                filteration_terminal_filter_on_riser = %s,
                filteration_bibo_filter = %s,
                filteration_relief_filter = %s,
                filteration_reativation_filter = %s,
                equipment_status = %s,
                equipment_last_modification_datetime = %s,
                equipment_addition_date = %s
                WHERE equipment_id = %i",
                $validated_data['equipment_code'],
                intval($validated_data['unit_id']),
                intval($validated_data['department_id']),
                $validated_data['equipment_category'],
                $validated_data['validation_frequency'],
                $validated_data['area_served'],
                $validated_data['section'],
                $validated_data['design_acph'],
                $validated_data['area_classification'],
                $validated_data['area_classification_in_operation'],
                $validated_data['equipment_type'],
                $validated_data['design_cfm'],
                safe_get('filteration_fresh_air', 'string', ''),
                safe_get('filteration_pre_filter', 'string', ''),
                safe_get('filteration_intermediate', 'string', ''),
                safe_get('filteration_final_filter_plenum', 'string', ''),
                safe_get('filteration_exhaust_pre_filter', 'string', ''),
                safe_get('filteration_exhaust_final_filter', 'string', ''),
                safe_get('filteration_terminal_filter', 'string', ''),
                safe_get('filteration_terminal_filter_on_riser', 'string', ''),
                safe_get('filteration_bibo_filter', 'string', ''),
                safe_get('filteration_relief_filter', 'string', ''),
                safe_get('filteration_reativation_filter', 'string', ''),
                $validated_data['equipment_status'],
                DB::sqleval("NOW()"),
                $validated_data['equipment_addition_date'],
                intval($validated_data['equipment_id'])
            );
            
            $affected_rows = DB::affectedRows();
            
            if ($affected_rows === 0) {
                throw new Exception("No equipment record was updated - equipment may not exist");
            }
            
            // Insert log entry
            DB::insert('log', [
                'change_type' => 'master_update_eq',
                'table_name' => 'equipments',
                'change_description' => 'Modified an existing equipment. Equipment ID:' . $validated_data['equipment_id'],
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
        error_log("Equipment validation error: " . $e->getMessage());
        echo json_encode(['error' => $e->getMessage()]);
    } catch (Exception $e) {
        error_log("Equipment modify error: " . $e->getMessage());
        echo json_encode(['error' => 'Database error occurred']);
    }
} else {
    echo json_encode(['error' => 'Invalid mode specified']);
}

?>