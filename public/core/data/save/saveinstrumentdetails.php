<?php
// Start output buffering to capture any stray output
ob_start();

// Suppress PHP errors/warnings from output (security)
ini_set('display_errors', 0);
error_reporting(E_ERROR | E_PARSE);

// Include configuration first
require_once(__DIR__ . '/../../config/config.php');

// Session is already started by config.php via session_init.php

// Check for proper authentication
if (!isset($_SESSION['logged_in_user']) || !isset($_SESSION['user_name'])) {
    ob_end_clean(); // Clear any output buffer
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit();
}

// Validate session timeout
require_once(__DIR__ . '/../../security/session_timeout_middleware.php');
validateActiveSession();

require_once(__DIR__ . '/../../config/db.class.php');

// Include input validation utilities (includes SecurityUtils class)
require_once(__DIR__ . '/../../validation/input_validation_utils.php');

date_default_timezone_set("Asia/Kolkata");

// Set content type to JSON
header('Content-Type: application/json');

try {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        ob_end_clean(); // Clear any output buffer
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit();
    }
    
    // Validate and sanitize input data
    $action = isset($_POST['mode']) ? InputValidator::sanitizeString($_POST['mode']) : '';
    $instrument_id = isset($_POST['instrument_id']) ? InputValidator::sanitizeString($_POST['instrument_id']) : '';
    $instrument_type = isset($_POST['instrument_type']) ? InputValidator::sanitizeString($_POST['instrument_type']) : '';
    $serial_number = isset($_POST['serial_number']) ? InputValidator::sanitizeString($_POST['serial_number']) : '';
    $instrument_status = isset($_POST['instrument_status']) ? InputValidator::sanitizeString($_POST['instrument_status']) : '';
    
    // Handle vendor_id with proper validation
    $vendor_id = 0;
    if (isset($_POST['vendor_id']) && !empty($_POST['vendor_id'])) {
        $validated_vendor = InputValidator::validateInteger($_POST['vendor_id'], 0);
        if ($validated_vendor !== false) {
            $vendor_id = $validated_vendor;
        }
    }
    
    // Handle dates with proper validation
    $calibrated_on = '';
    if (isset($_POST['calibrated_on'])) {
        $validated_date = InputValidator::validateDate($_POST['calibrated_on']);
        if ($validated_date !== false) {
            $calibrated_on = $validated_date;
        }
    }
    
    $calibration_due_on = '';
    if (isset($_POST['calibration_due_on'])) {
        $validated_date = InputValidator::validateDate($_POST['calibration_due_on']);
        if ($validated_date !== false) {
            $calibration_due_on = $validated_date;
        }
    }
    
    // Validate required fields
    if (!in_array($action, ['a', 'e'], true)) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Invalid mode specified']);
        exit();
    }
    
    if (empty($instrument_id) || strlen($instrument_id) > 100) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Valid instrument ID is required']);
        exit();
    }
    
    if (empty($instrument_type) || strlen($instrument_type) > 100) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Valid instrument type is required']);
        exit();
    }
    
    if (empty($serial_number) || strlen($serial_number) > 100) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Valid serial number is required']);
        exit();
    }
    
    if (empty($calibrated_on)) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Valid calibrated date is required']);
        exit();
    }
    
    if (empty($calibration_due_on)) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Valid calibration due date is required']);
        exit();
    }
    
    if (!in_array($instrument_status, ['Active', 'Inactive'], true)) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Valid instrument status is required']);
        exit();
    }
    
    // Validate date logic
    if (strtotime($calibration_due_on) <= strtotime($calibrated_on)) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Calibration due date must be after calibrated date']);
        exit();
    }
    
    // Get workflow information
    $is_vendor_user = (isset($_POST['is_vendor_user']) && InputValidator::sanitizeString($_POST['is_vendor_user']) === 'true');
    $is_admin_user = (isset($_POST['is_admin_user']) && InputValidator::sanitizeString($_POST['is_admin_user']) === 'true');
    
    // Get logged in user ID with proper security validation
    $logged_in_user_id = $_SESSION['user_id'] ?? $_SESSION['logged_in_user'] ?? null;
    
    // Security check for valid user ID
    if (!$logged_in_user_id || !is_numeric($logged_in_user_id)) {
        // Log security event for invalid user session
        if (class_exists('SecurityUtils')) {
            SecurityUtils::logSecurityEvent('invalid_user_session', 'Invalid user session during instrument save', [
                'session_data' => array_keys($_SESSION)
            ]);
        }
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Invalid user session']);
        exit();
    }
    
    // Check if this is only a status change from Active to Inactive (edit mode exception)
    $isStatusChangeToInactive = false;
    if ($action === 'e' && !empty($instrument_id)) {
        // Get current instrument status
        $currentInstrument = DB::queryFirstRow("
            SELECT instrument_status 
            FROM instruments 
            WHERE instrument_id = %s
        ", $instrument_id);
        
        $currentStatus = $currentInstrument['instrument_status'] ?? '';
        $newStatus = $instrument_status;
        
        $isStatusChangeToInactive = ($currentStatus === 'Active' && $newStatus === 'Inactive');
    }
    
    // Validate file upload (with status change exception)
    if (!isset($_FILES['master_certificate_file']) || $_FILES['master_certificate_file']['error'] !== UPLOAD_ERR_OK) {
        if (!$isStatusChangeToInactive) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Master Certificate File is required (except when only changing status from Active to Inactive)']);
            exit();
        }
    }
    
    // Prepare instrument data array
    $instrument_data = [
        'instrument_id' => $instrument_id,
        'instrument_type' => $instrument_type,
        'vendor_id' => $vendor_id,
        'serial_number' => $serial_number,
        'calibrated_on' => $calibrated_on,
        'calibration_due_on' => $calibration_due_on,
        'instrument_status' => $instrument_status
    ];
    
    // Handle file upload for master certificate with security validation
    $master_certificate_path = null;
    $existing_certificate_path = isset($_POST['existing_certificate_path']) ? InputValidator::sanitizeString($_POST['existing_certificate_path']) : null;
    
    if (isset($_FILES['master_certificate_file']) && $_FILES['master_certificate_file']['error'] == UPLOAD_ERR_OK) {
        // Validate file with security checks
        $file = $_FILES['master_certificate_file'];
        
        // File validation with security checks
        $file_info = pathinfo($file['name']);
        $file_extension = strtolower($file_info['extension']);
        
        if ($file_extension !== 'pdf') {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Only PDF files are allowed for master certificate']);
            exit();
        }
        
        if ($file['size'] > 10 * 1024 * 1024) { // 10MB limit
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'File size must be less than 10MB']);
            exit();
        }
        
        // Additional MIME type check
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if ($mime_type !== 'application/pdf') {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Invalid file type detected. Only PDF files are allowed.']);
            exit();
        }
        
        // Create uploads directory if it doesn't exist
        $upload_dir = __DIR__ . '/../../../uploads/certificates/';
        if (!file_exists($upload_dir)) {
            if (!mkdir($upload_dir, 0755, true)) {
                echo json_encode(['success' => false, 'message' => 'Failed to create certificates directory']);
                exit();
            }
        }
        
        // Verify directory is writable
        if (!is_writable($upload_dir)) {
            echo json_encode(['success' => false, 'message' => 'Certificates directory is not writable']);
            exit();
        }
        
        // Generate unique filename (sanitize instrument ID for filesystem)
        $sanitized_instrument_id = preg_replace('/[^a-zA-Z0-9\-_]/', '_', $instrument_id);
        $filename = 'cert_' . $sanitized_instrument_id . '_' . time() . '.pdf';
        $upload_path = $upload_dir . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $upload_path)) {
            $master_certificate_path = 'uploads/certificates/' . $filename;
            
            // Save certificate to history table for tracking
            try {
                // Mark previous certificates as inactive for this instrument
                DB::query(
                    "UPDATE instrument_certificate_history 
                     SET is_active = 0 
                     WHERE instrument_id = %s", 
                    $instrument_id
                );
                
                // Insert new certificate record
                DB::insert('instrument_certificate_history', [
                    'instrument_id' => $instrument_id,
                    'certificate_file_path' => $master_certificate_path,
                    'calibrated_on' => $calibrated_on,
                    'calibration_due_on' => $calibration_due_on,
                    'uploaded_by' => $logged_in_user_id,
                    'uploaded_date' => date('Y-m-d H:i:s'),
                    'is_active' => 1,
                    'file_size' => $file['size'],
                    'original_filename' => $file['name'],
                    'notes' => 'Certificate uploaded via ' . ($action === 'a' ? 'instrument creation' : 'instrument update')
                ]);
                
                error_log("Certificate history saved for instrument: " . $instrument_id);
            } catch (Exception $e) {
                error_log("Error saving certificate history: " . $e->getMessage());
                // Continue with the process even if history saving fails
            }
            
            // Note: Old certificates are retained for audit trail purposes
            // They are not deleted when new certificates are uploaded
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to upload certificate file']);
            exit();
        }
    } else {
        // If no new file uploaded, keep existing path for edit mode
        if ($action == 'e' && $existing_certificate_path) {
            $master_certificate_path = $existing_certificate_path;
        }
    }
    
    try {
        // Determine if approval workflow is needed
        // Admin and Super Admin users do not require approval, regardless of user type
        $is_session_admin = ($_SESSION['is_admin'] === 'Yes' || $_SESSION['is_super_admin'] === 'Yes');
        $needs_approval = $is_vendor_user && !$is_admin_user && !$is_session_admin;
        
        if ($action == 'a') {
            // Check for uniqueness: Instrument ID + Calibrated On + Status
            $existing = DB::queryFirstRow(
                "SELECT instrument_id FROM instruments 
                 WHERE instrument_id = %s AND calibrated_on = %s AND instrument_status = %s", 
                $instrument_id, $calibrated_on, $instrument_status
            );
            
            if ($existing) {
                echo json_encode(['success' => false, 'message' => 'An instrument with this ID, calibration date, and status already exists']);
                exit();
            }
            
            if ($needs_approval) {
                // Insert with Pending status for vendor users
                DB::insert('instruments', [
                    'instrument_id' => $instrument_id,
                    'instrument_type' => $instrument_type,
                    'vendor_id' => $vendor_id,
                    'serial_number' => $serial_number,
                    'calibrated_on' => $calibrated_on,
                    'calibration_due_on' => $calibration_due_on,
                    'master_certificate_path' => $master_certificate_path,
                    'instrument_status' => 'Pending',
                    'submitted_by' => $logged_in_user_id,
                    'created_by' => $logged_in_user_id,
                    'created_date' => date('Y-m-d H:i:s')
                ]);

                // Log the workflow action
                DB::insert('instrument_workflow_log', [
                    'instrument_id' => $instrument_id,
                    'action_type' => 'Created',
                    'performed_by' => $logged_in_user_id,
                    'action_date' => date('Y-m-d H:i:s'),
                    'new_data' => json_encode($instrument_data),
                    'remarks' => 'Instrument created by vendor - pending checker approval',
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
                ]);

                ob_end_clean();
                echo json_encode([
                    'success' => true,
                    'message' => 'Instrument submitted successfully. It will be visible after checker approval.',
                    'status' => 'pending'
                ]);
            } else {
                // Direct insert for admin users
                DB::insert('instruments', [
                    'instrument_id' => $instrument_id,
                    'instrument_type' => $instrument_type,
                    'vendor_id' => $vendor_id,
                    'serial_number' => $serial_number,
                    'calibrated_on' => $calibrated_on,
                    'calibration_due_on' => $calibration_due_on,
                    'master_certificate_path' => $master_certificate_path,
                    'instrument_status' => $instrument_status,
                    'submitted_by' => $logged_in_user_id,
                    'created_by' => $logged_in_user_id,
                    'created_date' => date('Y-m-d H:i:s'),
                    'approval_status' => 'APPROVED'
                ]);

                // Log the action
                DB::insert('log', [
                    'change_type' => 'INSERT',
                    'table_name' => 'instruments',
                    'change_description' => 'Added new instrument: ' . $instrument_id . ' (Admin - No approval required)',
                    'change_by' => $logged_in_user_id
                ]);
                
                ob_end_clean(); // Clear any output buffer
                echo json_encode(['success' => true, 'message' => 'Instrument details saved successfully']);
            }

        } else if ($action == 'e') {
            if ($needs_approval) {
                // Get original data for audit trail
                $original_instrument = DB::queryFirstRow(
                    "SELECT * FROM instruments WHERE instrument_id = %s",
                    $instrument_id
                );

                // Update with Pending status for vendor users
                $update_data = [
                    'instrument_type' => $instrument_type,
                    'vendor_id' => $vendor_id,
                    'serial_number' => $serial_number,
                    'calibrated_on' => $calibrated_on,
                    'calibration_due_on' => $calibration_due_on,
                    'instrument_status' => 'Pending',
                    'submitted_by' => $logged_in_user_id,
                    'checker_id' => null,
                    'checker_action' => null,
                    'checker_date' => null,
                    'checker_remarks' => null,
                    'original_data' => json_encode($original_instrument)
                ];

                // Update certificate path if new file uploaded
                if ($master_certificate_path) {
                    $update_data['master_certificate_path'] = $master_certificate_path;
                }

                DB::update('instruments', $update_data, "instrument_id = %s", $instrument_id);

                // Log the workflow action
                DB::insert('instrument_workflow_log', [
                    'instrument_id' => $instrument_id,
                    'action_type' => 'Modified',
                    'performed_by' => $logged_in_user_id,
                    'action_date' => date('Y-m-d H:i:s'),
                    'old_data' => json_encode($original_instrument),
                    'new_data' => json_encode($instrument_data),
                    'remarks' => 'Instrument modified by vendor - pending checker approval',
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
                ]);

                ob_end_clean();
                echo json_encode([
                    'success' => true,
                    'message' => 'Instrument updated successfully. Changes will be visible after checker approval.',
                    'status' => 'pending'
                ]);
            } else {
                // Direct update for admin users
                $update_data = [
                    'instrument_type' => $instrument_type,
                    'vendor_id' => $vendor_id,
                    'serial_number' => $serial_number,
                    'calibrated_on' => $calibrated_on,
                    'calibration_due_on' => $calibration_due_on,
                    'instrument_status' => $instrument_status,
                    'reviewed_by' => $logged_in_user_id,
                    'reviewed_date' => date('Y-m-d H:i:s'),
                    'approval_status' => 'APPROVED',
                    'pending_approval_id' => null
                ];
                
                // Update certificate path if new file uploaded
                if ($master_certificate_path) {
                    $update_data['master_certificate_path'] = $master_certificate_path;
                }
                
                DB::update('instruments', $update_data, "instrument_id = %s", $instrument_id);

                // Log the action
                DB::insert('log', [
                    'change_type' => 'UPDATE',
                    'table_name' => 'instruments',
                    'change_description' => 'Updated instrument: ' . $instrument_id . ' (Admin - No approval required)',
                    'change_by' => $logged_in_user_id
                ]);
                
                ob_end_clean(); // Clear any output buffer
                echo json_encode(['success' => true, 'message' => 'Instrument details updated successfully']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid action specified']);
        }

    } catch (Exception $e) {
        error_log("Error in saveinstrumentdetails.php: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    
} catch (Exception $e) {
    error_log("Save instrument details error: " . $e->getMessage());
    error_log("Save instrument details stack trace: " . $e->getTraceAsString());
    
    // Log security event for the error
    if (class_exists('SecurityUtils')) {
        SecurityUtils::logSecurityEvent('instrument_save_error', 'Error saving instrument details', [
            'error' => $e->getMessage()
        ]);
    }
    
    http_response_code(500);
    ob_end_clean(); // Clear any output buffer
    echo json_encode(['success' => false, 'message' => 'An error occurred while saving the instrument']);
}

?>