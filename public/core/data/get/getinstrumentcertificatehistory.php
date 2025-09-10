<?php
require_once(__DIR__ . '/../../config/config.php');

// Validate session timeout
require_once(__DIR__ . '/../../security/session_timeout_middleware.php');
validateActiveSession();

require_once(__DIR__ . '/../../config/db.class.php');

// Include input validation utilities
require_once(__DIR__ . '/../../validation/input_validation_utils.php');

// Set content type to JSON
header('Content-Type: application/json');

try {
    // Validate and sanitize input
    $instrument_id = isset($_GET['instrument_id']) ? InputValidator::sanitizeString($_GET['instrument_id']) : '';
    
    if (empty($instrument_id)) {
        echo json_encode(['success' => false, 'message' => 'Instrument ID is required']);
        exit();
    }
    
    // Validate instrument_id length
    if (strlen($instrument_id) > 100) {
        echo json_encode(['success' => false, 'message' => 'Invalid instrument ID format']);
        exit();
    }
    
    // Get certificate history for this instrument
    $history = DB::query(
        "SELECT 
            ich.history_id,
            ich.instrument_id,
            ich.certificate_file_path,
            ich.calibrated_on,
            ich.calibration_due_on,
            ich.uploaded_by,
            ich.uploaded_date,
            ich.is_active,
            ich.file_size,
            ich.original_filename,
            ich.notes,
            u.user_name as uploaded_by_name,
            CASE 
                WHEN ich.calibration_due_on < CURDATE() THEN 'Expired'
                WHEN ich.calibration_due_on <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'Due Soon'
                ELSE 'Valid'
            END as status
        FROM instrument_certificate_history ich
        LEFT JOIN users u ON ich.uploaded_by = u.user_id
        WHERE ich.instrument_id = %s
        ORDER BY ich.uploaded_date DESC, ich.history_id DESC",
        $instrument_id
    );
    
    // Format file sizes for display
    foreach ($history as &$record) {
        if ($record['file_size']) {
            $record['file_size_formatted'] = formatFileSize($record['file_size']);
        } else {
            $record['file_size_formatted'] = 'Unknown';
        }
        
        // Format dates for display
        $record['uploaded_date_formatted'] = date('M d, Y H:i', strtotime($record['uploaded_date']));
        $record['calibrated_on_formatted'] = date('M d, Y', strtotime($record['calibrated_on']));
        $record['calibration_due_on_formatted'] = date('M d, Y', strtotime($record['calibration_due_on']));
        
        // Clean up file path for download
        $record['download_path'] = $record['certificate_file_path'];
        
        // Extract filename for display
        if (empty($record['original_filename'])) {
            $record['display_filename'] = basename($record['certificate_file_path']);
        } else {
            $record['display_filename'] = $record['original_filename'];
        }
    }
    
    echo json_encode([
        'success' => true, 
        'data' => $history,
        'total_records' => count($history)
    ]);
    
} catch (Exception $e) {
    error_log("Error fetching certificate history: " . $e->getMessage());
    
    // Log security event for the error
    if (class_exists('SecurityUtils')) {
        SecurityUtils::logSecurityEvent('certificate_history_fetch_error', 'Error fetching certificate history', [
            'error' => $e->getMessage(),
            'instrument_id' => $instrument_id ?? 'unknown'
        ]);
    }
    
    echo json_encode(['success' => false, 'message' => 'Error fetching certificate history']);
}

function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}
?>