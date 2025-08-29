<?php
/**
 * Centralized Database Error Logging Utility
 * 
 * This utility provides consistent database error logging across the application
 * using the existing error_log table structure.
 */

require_once(__DIR__ . '/../config/db.class.php');

/**
 * Log database errors to the error_log table
 * 
 * @param string $errorMessage The error message to log
 * @param array $context Optional context information including:
 *                      - operation_name: Name of the operation that failed
 *                      - equip_id: Equipment ID if relevant
 *                      - val_wf_id: Validation workflow ID if relevant
 *                      - unit_id: Unit ID (defaults to session unit_id)
 *                      - planned_start_date: Planned start date if relevant
 * @return bool True on success, false on failure
 */
function logDatabaseError($errorMessage, $context = []) {
    try {
        // Extract context parameters with defaults
        $equip_id = isset($context['equip_id']) ? intval($context['equip_id']) : null;
        $val_wf_id = $context['val_wf_id'] ?? null;
        $unit_id = null;
        $planned_date = $context['planned_start_date'] ?? null;
        $operation = $context['operation_name'] ?? null;
        
        // Try to get unit_id from context first, then session
        if (isset($context['unit_id'])) {
            $unit_id = intval($context['unit_id']);
        } elseif (isset($_SESSION['unit_id'])) {
            $unit_id = intval($_SESSION['unit_id']);
        }
        
        // Sanitize the error message
        $errorMessage = htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8');
        
        // Log to database
        DB::query("INSERT INTO error_log (error_message, equip_id, current_val_wf_id, 
                   current_unit_id, current_planned_start_date, operation_name) 
                   VALUES (%s, %i, %s, %i, %?, %s)", 
                   $errorMessage, $equip_id, $val_wf_id, $unit_id, 
                   ($planned_date ? DB::sqleval($planned_date) : null), $operation);
        
        // Also log to system error log as backup
        error_log("Database Error Logged: " . $errorMessage . " [Operation: " . $operation . "]");
        
        return true;
        
    } catch (Exception $e) {
        // If database logging fails, fall back to system error log
        error_log("Failed to log database error to error_log table: " . $e->getMessage() . 
                  " | Original error: " . $errorMessage);
        return false;
    }
}

/**
 * Get operation name based on current file
 * 
 * @param string $filename Optional filename (defaults to current file)
 * @return string Standardized operation name
 */
function getOperationName($filename = null) {
    if ($filename === null) {
        $filename = basename($_SERVER['PHP_SELF'], '.php');
    } else {
        $filename = basename($filename, '.php');
    }
    
    // Map common files to operation names
    $operationMap = [
        'generateplannedvsactualrpt' => 'report_planned_vs_actual',
        'generateplannedvsactualrtrpt' => 'report_planned_vs_actual_rt',
        'generateprotocolreport_rev' => 'report_protocol_generation',
        'generateschedulereport' => 'report_schedule_generation',
        'generatertschedulereport' => 'report_rt_schedule_generation',
        'getschedule' => 'search_schedule_validation',
        'searchuser' => 'search_user_list',
        'searchequipments' => 'search_equipment_list',
        'searchmapping' => 'search_mapping_list',
        'manageuserdetails' => 'manage_user_details',
        'addvalrequest' => 'validation_request_creation',
        'addroutinetest' => 'routine_test_creation',
        'showaudittrail' => 'audit_trail_display',
        'updatetaskdetails' => 'task_update_workflow',
        'savelevel1approvaldata' => 'validation_level1_approval',
        'savelevel2approvaldata' => 'validation_level2_approval',
        'savelevel3approvaldata' => 'validation_level3_approval',
    ];
    
    return $operationMap[$filename] ?? 'operation_' . $filename;
}

/**
 * Extract context from common GET/POST parameters and session
 * 
 * @return array Context array with available parameters
 */
function extractCommonContext() {
    $context = [];
    
    // Common parameters that might be available
    if (isset($_GET['val_wf_id'])) {
        $context['val_wf_id'] = htmlspecialchars($_GET['val_wf_id'], ENT_QUOTES, 'UTF-8');
    }
    if (isset($_GET['equipment_id'])) {
        $context['equip_id'] = intval($_GET['equipment_id']);
    }
    if (isset($_GET['unit_id'])) {
        $context['unit_id'] = intval($_GET['unit_id']);
    }
    if (isset($_SESSION['unit_id'])) {
        $context['unit_id'] = intval($_SESSION['unit_id']);
    }
    
    // Add operation name based on current file
    $context['operation_name'] = getOperationName();
    
    return $context;
}