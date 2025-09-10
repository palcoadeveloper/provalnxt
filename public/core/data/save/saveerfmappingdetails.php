<?php
session_start();

// Load configuration first
require_once(__DIR__ . '/../../config/config.php');
// Validate session timeout
require_once(__DIR__ . '/../../security/session_timeout_middleware.php');
validateActiveSession();
require_once(__DIR__ . '/../../config/db.class.php');

// Include secure transaction wrapper
require_once(__DIR__ . '/../../security/secure_transaction_wrapper.php');

date_default_timezone_set("Asia/Kolkata");

// Set JSON content type header
header('Content-Type: application/json');

// CSRF token validation
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode([
        'success' => false, 
        'message' => 'Security validation failed. Please refresh the page and try again.'
    ]);
    exit();
}

// Input validation class for ERF mapping data
class ERFMappingValidator {
    public static function validateERFMappingData($mode) {
        $errors = [];
        
        // Validate equipment selection
        if (!isset($_POST['equipment_id']) || empty($_POST['equipment_id']) || $_POST['equipment_id'] === 'Select') {
            $errors[] = "Equipment selection is required";
        } elseif (!is_numeric($_POST['equipment_id']) || intval($_POST['equipment_id']) <= 0) {
            $errors[] = "Invalid equipment selection";
        }
        
        // Validate room selection  
        if (!isset($_POST['room_loc_id']) || empty($_POST['room_loc_id']) || $_POST['room_loc_id'] === 'Select') {
            $errors[] = "Room/Location selection is required";
        } elseif (!is_numeric($_POST['room_loc_id']) || intval($_POST['room_loc_id']) <= 0) {
            $errors[] = "Invalid room/location selection";
        }
        
        // Validate filter name (optional field)
        if (isset($_POST['filter_name']) && !empty(trim($_POST['filter_name']))) {
            if (strlen(trim($_POST['filter_name'])) > 200) {
                $errors[] = "Filter name cannot exceed 200 characters";
            }
        }
        
        // Validate filter group (conditional validation)
        $filter_name = trim($_POST['filter_name'] ?? '');
        $filter_group_id = $_POST['filter_group_id'] ?? '';
        
        if (!empty($filter_name) && ($filter_group_id === 'Select' || empty($filter_group_id))) {
            $errors[] = "Filter group selection is required when filter name is provided";
        }
        
        // Validate filter group ID if provided
        if ($filter_group_id !== 'Select' && !empty($filter_group_id)) {
            if (!is_numeric($filter_group_id) || intval($filter_group_id) <= 0) {
                $errors[] = "Invalid filter group selection";
            } else {
                // Verify filter group exists and is active
                $filter_group_exists = DB::queryFirstRow("SELECT filter_group_id FROM filter_groups WHERE filter_group_id = %i AND status = %s", intval($filter_group_id), 'Active');
                if (!$filter_group_exists) {
                    $errors[] = "Selected filter group is not found or inactive";
                }
            }
        }
        
        // Validate area classification
        if (!isset($_POST['area_classification']) || empty(trim($_POST['area_classification']))) {
            $errors[] = "Area classification is required";
        } elseif (strlen(trim($_POST['area_classification'])) > 200) {
            $errors[] = "Area classification cannot exceed 200 characters";
        }
        
        // Validate status
        if (!isset($_POST['erf_mapping_status']) || !in_array($_POST['erf_mapping_status'], ['Active', 'Inactive'])) {
            $errors[] = "Valid status selection is required";
        }
        
        // Check for duplicate combination (Equipment + Room + Filter)
        if (empty($errors)) {
            $equipment_id = intval($_POST['equipment_id']);
            $room_loc_id = intval($_POST['room_loc_id']);
            $filter_name = !empty(trim($_POST['filter_name'] ?? '')) ? trim($_POST['filter_name']) : null;
            
            // Handle NULL filter names properly in duplicate check
            if ($filter_name === null) {
                $duplicate_check_query = "SELECT erf_mapping_id FROM erf_mappings 
                                        WHERE equipment_id = %i AND room_loc_id = %i AND filter_name IS NULL";
                $params = [$equipment_id, $room_loc_id];
            } else {
                $duplicate_check_query = "SELECT erf_mapping_id FROM erf_mappings 
                                        WHERE equipment_id = %i AND room_loc_id = %i AND filter_name = %s";
                $params = [$equipment_id, $room_loc_id, $filter_name];
            }
            
            // For modify mode, exclude current record
            if ($mode === 'modify' && isset($_POST['erf_mapping_id'])) {
                $duplicate_check_query .= " AND erf_mapping_id != %i";
                $params[] = intval($_POST['erf_mapping_id']);
            }
            
            $duplicate = DB::queryFirstRow($duplicate_check_query, ...$params);
            if ($duplicate) {
                $filter_display = $filter_name ? "Filter: $filter_name" : "No Filter";
                $errors[] = "This Equipment + Room + $filter_display combination already exists";
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
    $validation_errors = ERFMappingValidator::validateERFMappingData($mode);
    
    if (!empty($validation_errors)) {
        echo json_encode([
            'success' => false,
            'message' => implode('<br>', $validation_errors)
        ]);
        exit();
    }
    
    // Prepare data for database operation
    $filter_id = $_POST['filter_id'] ?? '';
    $filter_group_id = $_POST['filter_group_id'] ?? '';
    
    // Get filter name from filter_id if provided
    $filter_name = null;
    if (!empty($filter_id) && $filter_id !== 'Select' && is_numeric($filter_id)) {
        $filter_details = DB::queryFirstRow("SELECT filter_code FROM filters WHERE filter_id = %i", intval($filter_id));
        if ($filter_details) {
            $filter_name = $filter_details['filter_code'];
        }
    }
    
    // Add filter_id to data if provided
    $filter_id_value = null;
    if (!empty($filter_id) && $filter_id !== 'Select' && is_numeric($filter_id)) {
        $filter_id_value = intval($filter_id);
    }
    
    $data = [
        'equipment_id' => intval($_POST['equipment_id']),
        'room_loc_id' => intval($_POST['room_loc_id']),
        'filter_id' => $filter_id_value,
        'filter_name' => $filter_name,
        'filter_group_id' => ($filter_group_id !== 'Select' && !empty($filter_group_id)) ? intval($filter_group_id) : null,
        'area_classification' => trim($_POST['area_classification']),
        'erf_mapping_status' => $_POST['erf_mapping_status'],
        'last_modification_datetime' => date('Y-m-d H:i:s')
    ];
    
    if ($mode === 'add') {
        $data['creation_datetime'] = date('Y-m-d H:i:s');
        
        $mapping_id = executeSecureTransaction(function() use ($data) {
            $mapping_id = DB::insert('erf_mappings', $data);
            
            // Log the activity
            $equipment_result = DB::queryFirstRow("SELECT equipment_code FROM equipments WHERE equipment_id = %i", $data['equipment_id']);
            $room_result = DB::queryFirstRow("SELECT room_loc_name FROM room_locations WHERE room_loc_id = %i", $data['room_loc_id']);
            
            error_log(sprintf(
                "ERF Mapping created: ID=%d, Equipment=%s, Room=%s, Filter=%s, Area=%s by User=%s",
                $mapping_id,
                $equipment_result['equipment_code'] ?? 'Unknown',
                $room_result['room_loc_name'] ?? 'Unknown', 
                $data['filter_name'] ?? 'No Filter',
                $data['area_classification'],
                $_SESSION['user_name'] ?? 'Unknown'
            ));
            
            return $mapping_id;
        }, 'erf_mapping_creation');
        
        echo json_encode([
            'success' => true,
            'message' => 'ERF mapping created successfully!',
            'mapping_id' => $mapping_id
        ]);
        
    } else { // modify mode
        if (!isset($_POST['erf_mapping_id']) || !is_numeric($_POST['erf_mapping_id'])) {
            throw new Exception("Invalid mapping ID for modification");
        }
        
        $mapping_id = intval($_POST['erf_mapping_id']);
        
        // Verify mapping exists
        $existing = DB::queryFirstRow("SELECT erf_mapping_id FROM erf_mappings WHERE erf_mapping_id = %i", $mapping_id);
        if (!$existing) {
            throw new Exception("ERF mapping not found");
        }
        
        executeSecureTransaction(function() use ($data, $mapping_id) {
            DB::update('erf_mappings', $data, "erf_mapping_id=%i", $mapping_id);
            
            // Log the activity
            $equipment_result = DB::queryFirstRow("SELECT equipment_code FROM equipments WHERE equipment_id = %i", $data['equipment_id']);
            $room_result = DB::queryFirstRow("SELECT room_loc_name FROM room_locations WHERE room_loc_id = %i", $data['room_loc_id']);
            
            error_log(sprintf(
                "ERF Mapping updated: ID=%d, Equipment=%s, Room=%s, Filter=%s, Area=%s by User=%s",
                $mapping_id,
                $equipment_result['equipment_code'] ?? 'Unknown',
                $room_result['room_loc_name'] ?? 'Unknown',
                $data['filter_name'] ?? 'No Filter',
                $data['area_classification'], 
                $_SESSION['user_name'] ?? 'Unknown'
            ));
            
            return $mapping_id;
        }, 'erf_mapping_update');
        
        echo json_encode([
            'success' => true,
            'message' => 'ERF mapping updated successfully!',
            'mapping_id' => $mapping_id
        ]);
    }
    
} catch (Exception $e) {
    error_log("ERF Mapping save error: " . $e->getMessage());
    error_log("ERF Mapping POST data: " . print_r($_POST, true));
    
    // Provide more specific error messages based on the exception
    $error_message = $e->getMessage() ?: 'Unknown error occurred';
    $user_message = 'An error occurred while saving the ERF mapping. Please try again.';
    
    if (strpos($error_message, 'Duplicate entry') !== false) {
        $user_message = 'This ERF mapping already exists. Please check your selections.';
    } elseif (strpos($error_message, 'foreign key') !== false || strpos($error_message, 'FOREIGN KEY') !== false) {
        $user_message = 'Invalid reference data. Please check your equipment and room selections.';
    } elseif (strpos($error_message, 'Data too long') !== false) {
        $user_message = 'Input data is too long. Please shorten your entries and try again.';
    } elseif (strpos($error_message, 'cannot be null') !== false || strpos($error_message, 'NULL') !== false) {
        $user_message = 'Required fields are missing. Please fill in all required information.';
    }
    
    // In development mode, show the actual error
    if (defined('ENVIRONMENT') && ENVIRONMENT === 'dev') {
        $user_message .= " (Debug: " . $error_message . ")";
    }
    
    echo json_encode([
        'success' => false,
        'message' => $user_message
    ]);
}
?>