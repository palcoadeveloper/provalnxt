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
    $test_val_wf_id = secure_get('test_val_wf_id', 'string');
    $format = secure_get('format', 'string', 'full'); // 'full' or 'dropdown'
    
    // Validate required parameters
    if (empty($test_val_wf_id)) {
        throw new InvalidArgumentException("Test workflow ID is required");
    }
    
    // Get user unit ID for data segregation
    $user_unit_id = getUserUnitId();
    
    // Build query based on user type
    if (isVendor()) {
        // Vendors can view instruments across units they have access to
        $query = "
            SELECT 
                ti.mapping_id,
                ti.added_date,
                i.instrument_id,
                i.instrument_type,
                i.serial_number,
                CASE 
                    WHEN i.calibration_due_on < NOW() THEN 'Expired'
                    WHEN i.calibration_due_on < DATE_ADD(NOW(), INTERVAL 30 DAY) THEN 'Due Soon' 
                    ELSE 'Valid'
                END as calibration_status,
                u.user_name as added_by_name,
                v.vendor_name
            FROM test_instruments ti
            INNER JOIN instruments i ON ti.instrument_id = i.instrument_id
            LEFT JOIN users u ON ti.added_by = u.user_id
            LEFT JOIN vendors v ON i.vendor_id = v.vendor_id
            WHERE ti.test_val_wf_id = %s 
            AND ti.is_active = 1
            ORDER BY ti.added_date DESC
        ";
        
        $instruments = DB::query($query, $test_val_wf_id);
    } else {
        // Engineering and QA users can access instruments across units for review
        if ($_SESSION['department_id'] == 1 || $_SESSION['department_id'] == 8) {
            $query = "
                SELECT 
                    ti.mapping_id,
                    ti.added_date,
                    i.instrument_id,
                    i.instrument_type,
                    i.serial_number,
                    CASE 
                        WHEN i.calibration_due_on < NOW() THEN 'Expired'
                        WHEN i.calibration_due_on < DATE_ADD(NOW(), INTERVAL 30 DAY) THEN 'Due Soon' 
                        ELSE 'Valid'
                    END as calibration_status,
                    u.user_name as added_by_name,
                    v.vendor_name
                FROM test_instruments ti
                INNER JOIN instruments i ON ti.instrument_id = i.instrument_id
                LEFT JOIN users u ON ti.added_by = u.user_id
                LEFT JOIN vendors v ON i.vendor_id = v.vendor_id
                WHERE ti.test_val_wf_id = %s 
                AND ti.is_active = 1
                ORDER BY ti.added_date DESC
            ";
            
            $instruments = DB::query($query, $test_val_wf_id);
        } else {
            // Other employees are restricted to their unit
            $query = "
                SELECT 
                    ti.mapping_id,
                    ti.added_date,
                    i.instrument_id,
                    i.instrument_type,
                    i.serial_number,
                    CASE 
                        WHEN i.calibration_due_on < NOW() THEN 'Expired'
                        WHEN i.calibration_due_on < DATE_ADD(NOW(), INTERVAL 30 DAY) THEN 'Due Soon' 
                        ELSE 'Valid'
                    END as calibration_status,
                    u.user_name as added_by_name,
                    v.vendor_name
                FROM test_instruments ti
                INNER JOIN instruments i ON ti.instrument_id = i.instrument_id
                LEFT JOIN users u ON ti.added_by = u.user_id
                LEFT JOIN vendors v ON i.vendor_id = v.vendor_id
                WHERE ti.test_val_wf_id = %s 
                AND ti.is_active = 1
                AND ti.unit_id = %i
                ORDER BY ti.added_date DESC
            ";
            
            $instruments = DB::query($query, $test_val_wf_id, $user_unit_id);
        }
    }
    
    // Format results based on requested format
    $results = [];
    if ($instruments) {
        foreach ($instruments as $instrument) {
            if ($format === 'dropdown') {
                // Lightweight format for dropdowns
                $display_name = $instrument['instrument_type'] . ' (' . $instrument['instrument_id'] . ')';
                if ($instrument['serial_number']) {
                    $display_name .= ' - SN: ' . $instrument['serial_number'];
                }
                
                $results[] = [
                    'id' => $instrument['instrument_id'],
                    'display_name' => htmlspecialchars($display_name, ENT_QUOTES, 'UTF-8'),
                    'calibration_status' => $instrument['calibration_status'],
                    'type' => htmlspecialchars($instrument['instrument_type'], ENT_QUOTES, 'UTF-8')
                ];
            } else {
                // Full format for detailed views
                // Determine calibration status badge class
                $status_class = 'badge-success';
                switch ($instrument['calibration_status']) {
                    case 'Due Soon':
                        $status_class = 'badge-warning';
                        break;
                    case 'Expired':
                        $status_class = 'badge-danger';
                        break;
                    case 'Not Calibrated':
                        $status_class = 'badge-secondary';
                        break;
                    default:
                        $status_class = 'badge-success';
                }
                
                $results[] = [
                    'mapping_id' => $instrument['mapping_id'],
                    'instrument_id' => $instrument['instrument_id'],
                    'instrument_code' => htmlspecialchars($instrument['instrument_id'], ENT_QUOTES, 'UTF-8'),
                    'instrument_name' => htmlspecialchars($instrument['instrument_type'] . ' (' . $instrument['instrument_id'] . ')', ENT_QUOTES, 'UTF-8'),
                    'instrument_type' => htmlspecialchars($instrument['instrument_type'], ENT_QUOTES, 'UTF-8'),
                    'serial_number' => htmlspecialchars($instrument['serial_number'], ENT_QUOTES, 'UTF-8'),
                    'manufacturer' => 'N/A',
                    'model_number' => 'N/A',
                    'calibration_status' => $instrument['calibration_status'],
                    'status_class' => $status_class,
                    'vendor_name' => htmlspecialchars($instrument['vendor_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8'),
                    'added_date' => date('d.m.Y H:i', strtotime($instrument['added_date'])),
                    'added_by_name' => htmlspecialchars($instrument['added_by_name'] ?? 'Unknown', ENT_QUOTES, 'UTF-8')
                ];
            }
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
    error_log("Get test instruments error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to load instruments. Please try again.']);
}
?>