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
// Note: GET requests do not require CSRF validation as they should be read-only operations

// Input validation helper
class FilterDetailsValidator {
    public static function validateFilterData($mode) {
        $required_fields = [
            'unit_id', 'filter_code', 'filter_type_id', 'filter_size', 
            'installation_date', 'planned_due_date', 'status'
        ];
        
        if ($mode === 'modify') {
            $required_fields[] = 'filter_id';
        }
        
        $validated_data = [];
        
        foreach ($required_fields as $field) {
            $value = safe_get($field, 'string', '');
            
            // Check if field is empty
            if (empty($value)) {
                throw new InvalidArgumentException("Missing required field: $field");
            }
            
            // Additional XSS detection on critical fields
            if (in_array($field, ['filter_code', 'filter_name', 'manufacturer']) && 
                XSSPrevention::detectXSS($value)) {
                XSSPrevention::logXSSAttempt($value, 'save_filter_details');
                throw new InvalidArgumentException("Invalid input detected in $field");
            }
            
            $validated_data[$field] = $value;
        }
        
        // Validate unit_id - must be valid integer
        if (!is_numeric($validated_data['unit_id']) || intval($validated_data['unit_id']) <= 0) {
            throw new InvalidArgumentException("Invalid unit ID - must be a positive number");
        }
        
        // Validate filter_type_id - must be valid integer and exist in filter_groups
        if (!is_numeric($validated_data['filter_type_id']) || intval($validated_data['filter_type_id']) <= 0) {
            throw new InvalidArgumentException("Invalid filter type ID - must be a positive number");
        }
        
        // Check if filter_type_id exists in filter_groups
        $filter_group_exists = DB::queryFirstField("SELECT COUNT(*) FROM filter_groups WHERE filter_group_id = %i AND status = 'Active'", intval($validated_data['filter_type_id']));
        if (!$filter_group_exists) {
            throw new InvalidArgumentException("Invalid filter type - selected filter group does not exist or is inactive");
        }
        
        $valid_filter_sizes = ['Standard', 'Large', 'Small', 'Custom'];
        if (!in_array($validated_data['filter_size'], $valid_filter_sizes)) {
            throw new InvalidArgumentException("Invalid filter size");
        }
        
        $valid_statuses = ['Active', 'Inactive'];
        if (!in_array($validated_data['status'], $valid_statuses)) {
            throw new InvalidArgumentException("Invalid status");
        }
        
        // Validate date format - try Y-m-d first (from JS conversion), then d.m.Y
        $installation_date = DateTime::createFromFormat('Y-m-d', $validated_data['installation_date']);
        if (!$installation_date) {
            $installation_date = DateTime::createFromFormat('d.m.Y', $validated_data['installation_date']);
            if (!$installation_date) {
                throw new InvalidArgumentException("Invalid installation date format. Use DD.MM.YYYY or YYYY-MM-DD");
            }
        }
        $validated_data['installation_date'] = $installation_date->format('Y-m-d');
        
        // Validate planned due date - now required
        $due_date = DateTime::createFromFormat('Y-m-d', $validated_data['planned_due_date']);
        if (!$due_date) {
            $due_date = DateTime::createFromFormat('d.m.Y', $validated_data['planned_due_date']);
            if (!$due_date) {
                throw new InvalidArgumentException("Invalid planned due date format. Use DD.MM.YYYY or YYYY-MM-DD");
            }
        }
        $validated_data['planned_due_date'] = $due_date->format('Y-m-d');
        
        // Validate actual replacement date if provided
        $actual_replacement_date = safe_get('actual_replacement_date', 'string', '');
        if (!empty($actual_replacement_date)) {
            $replacement_date = DateTime::createFromFormat('Y-m-d', $actual_replacement_date);
            if (!$replacement_date) {
                $replacement_date = DateTime::createFromFormat('d.m.Y', $actual_replacement_date);
                if (!$replacement_date) {
                    throw new InvalidArgumentException("Invalid actual replacement date format. Use DD.MM.YYYY or YYYY-MM-DD");
                }
            }
            $validated_data['actual_replacement_date'] = $replacement_date->format('Y-m-d');
        }
        
        if ($mode === 'modify' && (!isset($validated_data['filter_id']) || !is_numeric($validated_data['filter_id']) || intval($validated_data['filter_id']) <= 0)) {
            throw new InvalidArgumentException("Invalid filter ID - must be a positive number");
        }
        
        return $validated_data;
    }
}

// Get safe input values
$mode = safe_get('mode', 'string', '');

if ($mode === 'add') {
    try {
        // Validate input data
        $validated_data = FilterDetailsValidator::validateFilterData('add');
        
        // Execute secure transaction
        $result = executeSecureTransaction(function() use ($validated_data) {
            // Insert filter record
            $insert_data = [
                'unit_id' => intval($validated_data['unit_id']),
                'filter_code' => $validated_data['filter_code'],
                'filter_name' => safe_get('filter_name', 'string', ''),
                'filter_type_id' => intval($validated_data['filter_type_id']),
                'filter_size' => $validated_data['filter_size'],
                'manufacturer' => safe_get('manufacturer', 'string', ''),
                'specifications' => safe_get('specifications', 'string', ''),
                'installation_date' => $validated_data['installation_date'],
                'planned_due_date' => $validated_data['planned_due_date'],
                'status' => $validated_data['status'],
                'created_by' => $_SESSION['user_id']
            ];
            
            // Add optional actual replacement date if provided
            
            if (isset($validated_data['actual_replacement_date'])) {
                $insert_data['actual_replacement_date'] = $validated_data['actual_replacement_date'];
            }
            
            DB::insert('filters', $insert_data);
            
            $filter_id = DB::insertId();
            
            if ($filter_id <= 0) {
                throw new Exception("Failed to insert filter record");
            }
            
            // Insert log entry
            DB::insert('log', [
                'change_type' => 'master_add_filter',
                'table_name' => 'filters',
                'change_description' => 'Added a new filter. Filter ID:' . $filter_id,
                'change_by' => $_SESSION['user_id'],
                'unit_id' => $_SESSION['unit_id']
            ]);
            
            return $filter_id;
        });
        
        if ($result) {
            echo "success";
        } else {
            echo "failure";
        }
        
    } catch (InvalidArgumentException $e) {
        error_log("Filter validation error: " . $e->getMessage());
        echo json_encode(['error' => $e->getMessage()]);
    } catch (Exception $e) {
        error_log("Filter add error: " . $e->getMessage());
        
        // Parse specific database error messages
        $error_message = $e->getMessage();
        
        if (strpos($error_message, 'Duplicate entry') !== false && strpos($error_message, 'unique_filter_code') !== false) {
            // Extract the duplicate value from the error message
            preg_match("/Duplicate entry '(.+?)' for key/", $error_message, $matches);
            $duplicate_code = isset($matches[1]) ? $matches[1] : 'unknown';
            echo json_encode(['error' => "Filter code '$duplicate_code' already exists. Please use a different filter code."]);
        } elseif (strpos($error_message, 'Duplicate entry') !== false) {
            echo json_encode(['error' => 'This record already exists. Please check your input and try again.']);
        } elseif (strpos($error_message, 'foreign key constraint') !== false || strpos($error_message, 'FOREIGN KEY') !== false) {
            echo json_encode(['error' => 'Invalid reference data. Please check your input.']);
        } elseif (strpos($error_message, 'Data too long') !== false) {
            echo json_encode(['error' => 'Input data is too long. Please shorten your entries and try again.']);
        } else {
            echo json_encode(['error' => 'Database error occurred. Please try again or contact support.']);
        }
    }
}
else if ($mode === 'modify') {
    try {
        // Validate input data
        $validated_data = FilterDetailsValidator::validateFilterData('modify');
        
        // Execute secure transaction
        $result = executeSecureTransaction(function() use ($validated_data) {
            // Prepare update data
            $update_data = [
                'unit_id' => intval($validated_data['unit_id']),
                'filter_code' => $validated_data['filter_code'],
                'filter_name' => safe_get('filter_name', 'string', ''),
                'filter_type_id' => intval($validated_data['filter_type_id']),
                'filter_size' => $validated_data['filter_size'],
                'manufacturer' => safe_get('manufacturer', 'string', ''),
                'specifications' => safe_get('specifications', 'string', ''),
                'installation_date' => $validated_data['installation_date'],
                'planned_due_date' => $validated_data['planned_due_date'],
                'status' => $validated_data['status'],
                'last_modification_datetime' => date('Y-m-d H:i:s')
            ];
            
            // Add optional actual replacement date if provided
            
            if (isset($validated_data['actual_replacement_date'])) {
                $update_data['actual_replacement_date'] = $validated_data['actual_replacement_date'];
            } else {
                $update_data['actual_replacement_date'] = null;
            }
            
            // Update filter record
            DB::update('filters', $update_data, 'filter_id=%i', intval($validated_data['filter_id']));
            
            $affected_rows = DB::affectedRows();
            
            if ($affected_rows === 0) {
                throw new Exception("No filter record was updated - filter may not exist");
            }
            
            // Insert log entry
            DB::insert('log', [
                'change_type' => 'master_update_filter',
                'table_name' => 'filters',
                'change_description' => 'Modified an existing filter. Filter ID:' . $validated_data['filter_id'],
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
        error_log("Filter validation error: " . $e->getMessage());
        echo json_encode(['error' => $e->getMessage()]);
    } catch (Exception $e) {
        error_log("Filter modify error: " . $e->getMessage());
        
        // Parse specific database error messages
        $error_message = $e->getMessage();
        
        if (strpos($error_message, 'Duplicate entry') !== false && strpos($error_message, 'unique_filter_code') !== false) {
            // Extract the duplicate value from the error message
            preg_match("/Duplicate entry '(.+?)' for key/", $error_message, $matches);
            $duplicate_code = isset($matches[1]) ? $matches[1] : 'unknown';
            echo json_encode(['error' => "Filter code '$duplicate_code' already exists. Please use a different filter code."]);
        } elseif (strpos($error_message, 'Duplicate entry') !== false) {
            echo json_encode(['error' => 'This record already exists. Please check your input and try again.']);
        } elseif (strpos($error_message, 'foreign key constraint') !== false || strpos($error_message, 'FOREIGN KEY') !== false) {
            echo json_encode(['error' => 'Invalid reference data. Please check your input.']);
        } elseif (strpos($error_message, 'Data too long') !== false) {
            echo json_encode(['error' => 'Input data is too long. Please shorten your entries and try again.']);
        } else {
            echo json_encode(['error' => 'Database error occurred. Please try again or contact support.']);
        }
    }
} else {
    echo json_encode(['error' => 'Invalid mode specified']);
}

?>