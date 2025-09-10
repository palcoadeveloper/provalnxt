<?php
// Prevent any HTML output before JSON
ob_start();
error_reporting(E_ERROR | E_PARSE); // Only show critical errors
ini_set('display_errors', 0);

session_start();

// Load configuration first
require_once(__DIR__ . '/../../config/config.php');
// Validate session timeout
require_once(__DIR__ . '/../../security/session_timeout_middleware.php');
validateActiveSession();
require_once(__DIR__ . '/../../config/db.class.php');
require_once(__DIR__ . '/../../validation/input_validation_utils.php');
require_once(__DIR__ . '/../../security/secure_transaction_wrapper.php');

date_default_timezone_set("Asia/Kolkata");

// Clear any output buffer before sending JSON
ob_clean();

// CSRF token validation
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false, 
        'message' => 'Security validation failed. Please refresh the page and try again.'
    ]);
    exit();
}

// Input validation class for Filter Group data
class FilterGroupValidator {
    public static function validateFilterGroupData($mode) {
        $errors = [];
        
        // Validate filter group name
        if (!isset($_POST['filter_group_name']) || empty(trim($_POST['filter_group_name']))) {
            $errors[] = "Filter group name is required";
        } elseif (strlen(trim($_POST['filter_group_name'])) > 200) {
            $errors[] = "Filter group name cannot exceed 200 characters";
        }
        
        // Validate status
        if (!isset($_POST['status']) || !in_array($_POST['status'], ['Active', 'Inactive'])) {
            $errors[] = "Valid status selection is required";
        }
        
        // Check for duplicate filter group name
        if (empty($errors)) {
            $filter_group_name = trim($_POST['filter_group_name']);
            
            $duplicate_check_query = "SELECT filter_group_id FROM filter_groups WHERE filter_group_name = %s";
            $params = [$filter_group_name];
            
            // For modify mode, exclude current record
            if ($mode === 'modify' && isset($_POST['filter_group_id'])) {
                $duplicate_check_query .= " AND filter_group_id != %i";
                $params[] = intval($_POST['filter_group_id']);
            }
            
            $duplicate = DB::queryFirstRow($duplicate_check_query, ...$params);
            if ($duplicate) {
                $errors[] = "A filter group with this name already exists";
            }
        }
        
        return $errors;
    }
}

try {
    $mode = isset($_POST['mode']) ? $_POST['mode'] : '';
    
    if (!in_array($mode, ['add', 'modify'])) {
        throw new Exception("Invalid operation mode");
    }
    
    // Validate input data
    $validation_errors = FilterGroupValidator::validateFilterGroupData($mode);
    
    if (!empty($validation_errors)) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => implode('<br>', $validation_errors)
        ]);
        exit();
    }
    
    // Prepare data for database operation
    $data = [
        'filter_group_name' => trim($_POST['filter_group_name']),
        'status' => $_POST['status'],
        'last_modification_datetime' => date('Y-m-d H:i:s')
    ];
    
    if ($mode === 'add') {
        $data['creation_datetime'] = date('Y-m-d H:i:s');
        
        $filter_group_id = executeSecureTransaction(function() use ($data) {
            $filter_group_id = DB::insert('filter_groups', $data);
            
            // Log the activity
            error_log(sprintf(
                "Filter Group created: ID=%d, Name=%s, Status=%s by User=%s",
                $filter_group_id,
                $data['filter_group_name'],
                $data['status'],
                $_SESSION['user_name']
            ));
            
            return $filter_group_id;
        }, 'filter_group_creation');
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'Filter group created successfully!',
            'filter_group_id' => $filter_group_id
        ]);
        
    } else { // modify mode
        if (!isset($_POST['filter_group_id']) || !is_numeric($_POST['filter_group_id'])) {
            throw new Exception("Invalid filter group ID for modification");
        }
        
        $filter_group_id = intval($_POST['filter_group_id']);
        
        // Verify filter group exists
        $existing = DB::queryFirstRow("SELECT filter_group_id FROM filter_groups WHERE filter_group_id = %i", $filter_group_id);
        if (!$existing) {
            throw new Exception("Filter group not found");
        }
        
        executeSecureTransaction(function() use ($data, $filter_group_id) {
            DB::update('filter_groups', $data, "filter_group_id=%i", $filter_group_id);
            
            // Log the activity
            error_log(sprintf(
                "Filter Group updated: ID=%d, Name=%s, Status=%s by User=%s",
                $filter_group_id,
                $data['filter_group_name'],
                $data['status'],
                $_SESSION['user_name']
            ));
            
            return $filter_group_id;
        }, 'filter_group_update');
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'Filter group updated successfully!',
            'filter_group_id' => $filter_group_id
        ]);
    }
    
} catch (Exception $e) {
    error_log("Filter Group save error: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while saving the filter group. Please try again.'
    ]);
}
?>