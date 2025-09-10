<?php
require_once('../../config/config.php');

// Session is already started by config.php via session_init.php

// Validate session timeout
require_once('../../security/session_timeout_middleware.php');
validateActiveSession();

// Use centralized session validation
require_once('../../security/session_validation.php');
validateUserSession();

require_once("../../config/db.class.php");
require_once('../../security/secure_query_wrapper.php');

// Set content type to JSON
header('Content-Type: application/json');

// Additional security validation - validate user type
$userType = $_SESSION['logged_in_user'] ?? '';
if (!in_array($userType, ['employee', 'vendor'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

try {
    // Secure input validation
    $search_term = secure_get('q', 'string');
    $test_val_wf_id = secure_get('test_val_wf_id', 'string');
    
    // Validate required parameters
    if (empty($search_term) || empty($test_val_wf_id)) {
        throw new InvalidArgumentException("Missing required parameters");
    }
    
    // Minimum 2 characters required for search
    if (strlen($search_term) < 2) {
        echo json_encode(['instruments' => []]);
        exit();
    }
    
    // Sanitize search term for LIKE query
    $search_pattern = '%' . $search_term . '%';
    
    
    // Build query based on user type
    if (isVendor()) {
        // Vendors can search across all units they have access to
        $query = "
            SELECT 
                i.instrument_id,
                i.instrument_type,
                i.serial_number,
                v.vendor_name,
                CASE 
                    WHEN i.calibration_due_on < NOW() THEN 'Expired'
                    WHEN i.calibration_due_on < DATE_ADD(NOW(), INTERVAL 30 DAY) THEN 'Due Soon' 
                    ELSE 'Valid'
                END as calibration_status
            FROM instruments i
            LEFT JOIN vendors v ON i.vendor_id = v.vendor_id
            LEFT JOIN test_instruments ti ON i.instrument_id = ti.instrument_id 
                AND ti.test_val_wf_id = %s 
                AND ti.is_active = 1
            WHERE (
                i.instrument_id LIKE %s 
                OR i.serial_number LIKE %s
                OR i.instrument_type LIKE %s
            )
            AND i.instrument_status = 'Active'
            AND ti.instrument_id IS NULL
            ORDER BY i.instrument_type ASC, i.instrument_id ASC
            LIMIT 10
        ";
        
        $instruments = DB::query(
            $query,
            $test_val_wf_id, $search_pattern, $search_pattern, $search_pattern
        );
    } else {
        // Employees are restricted to their unit (no unit restriction in current table)
        $query = "
            SELECT 
                i.instrument_id,
                i.instrument_type,
                i.serial_number,
                v.vendor_name,
                CASE 
                    WHEN i.calibration_due_on < NOW() THEN 'Expired'
                    WHEN i.calibration_due_on < DATE_ADD(NOW(), INTERVAL 30 DAY) THEN 'Due Soon' 
                    ELSE 'Valid'
                END as calibration_status
            FROM instruments i
            LEFT JOIN vendors v ON i.vendor_id = v.vendor_id
            LEFT JOIN test_instruments ti ON i.instrument_id = ti.instrument_id 
                AND ti.test_val_wf_id = %s 
                AND ti.is_active = 1
            WHERE (
                i.instrument_id LIKE %s 
                OR i.serial_number LIKE %s
                OR i.instrument_type LIKE %s
            )
            AND i.instrument_status = 'Active'
            AND ti.instrument_id IS NULL
            ORDER BY i.instrument_type ASC, i.instrument_id ASC
            LIMIT 10
        ";
        
        $instruments = DB::query(
            $query,
            $test_val_wf_id, $search_pattern, $search_pattern, $search_pattern
        );
    }
    
    // Format results for frontend
    $results = [];
    if ($instruments) {
        foreach ($instruments as $instrument) {
            $results[] = [
                'id' => $instrument['instrument_id'],
                'code' => htmlspecialchars($instrument['instrument_id'], ENT_QUOTES, 'UTF-8'),
                'name' => htmlspecialchars($instrument['instrument_type'] . ' (' . $instrument['instrument_id'] . ')', ENT_QUOTES, 'UTF-8'),
                'type' => htmlspecialchars($instrument['instrument_type'], ENT_QUOTES, 'UTF-8'),
                'serial_number' => htmlspecialchars($instrument['serial_number'], ENT_QUOTES, 'UTF-8'),
                'calibration_status' => $instrument['calibration_status'],
                'vendor_name' => htmlspecialchars($instrument['vendor_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8'),
                'display_text' => sprintf(
                    "%s - %s - %s", 
                    htmlspecialchars($instrument['instrument_id'], ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars($instrument['instrument_type'], ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars($instrument['serial_number'], ENT_QUOTES, 'UTF-8')
                )
            ];
        }
    }
    
    // Return JSON response
    echo json_encode([
        'instruments' => $results,
        'count' => count($results)
    ]);
    
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
} catch (Exception $e) {
    // Log error and return generic message
    error_log("Instrument search error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Search failed. Please try again.']);
}
?>