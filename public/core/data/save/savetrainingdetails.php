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

// Include error logging utility
require_once('../../error/error_logger.php');

include_once '../../config/db.class.php';
date_default_timezone_set("Asia/Kolkata");

// Apply rate limiting for form submissions
if (!RateLimiter::checkRateLimit('form_submission')) {
    http_response_code(429);
    echo json_encode([
        'status' => 'error',
        'message' => 'Rate limit exceeded. Too many form submissions.',
        'csrf_token' => $_SESSION['csrf_token']
    ]);
    exit();
}

// Verify CSRF token
if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid CSRF token',
        'csrf_token' => $_SESSION['csrf_token']
    ]);
    exit;
}

// Input validation helper
class TrainingDetailsValidator {
    public static function validateTrainingData() {
        $required_fields = ['val_wf_id', 'department', 'employee'];
        $validated_data = [];
        
        foreach ($required_fields as $field) {
            if (!isset($_POST[$field]) || empty($_POST[$field])) {
                throw new InvalidArgumentException("Missing required field: $field");
            }
            
            $value = $_POST[$field];
            
            // XSS detection on critical fields
            if (in_array($field, ['val_wf_id']) && XSSPrevention::detectXSS($value)) {
                XSSPrevention::logXSSAttempt($value, 'save_training_details');
                throw new InvalidArgumentException("Invalid input detected in $field");
            }
            
            $validated_data[$field] = $value;
        }
        
        // Validate numeric fields
        if (!is_numeric($validated_data['employee'])) {
            throw new InvalidArgumentException("Invalid employee ID");
        }
        
        if ($validated_data['department'] !== '99' && !is_numeric($validated_data['department'])) {
            throw new InvalidArgumentException("Invalid department ID");
        }
        
        return $validated_data;
    }
    
    public static function validateFileUpload() {
        if (!isset($_FILES['trainingpdffile'])) {
            throw new InvalidArgumentException("Training PDF file is required");
        }
        
        $file = $_FILES['trainingpdffile'];
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException("File upload error: " . $file['error']);
        }
        
        if (empty($file['name'])) {
            throw new InvalidArgumentException("No file selected");
        }
        
        // Validate file size (limit to 10MB)
        $max_size = 10 * 1024 * 1024; // 10MB
        if ($file['size'] > $max_size) {
            throw new InvalidArgumentException("File size exceeds maximum limit of 10MB");
        }
        
        // Validate file type (PDF only)
        $allowed_types = ['application/pdf'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mime_type, $allowed_types)) {
            throw new InvalidArgumentException("Only PDF files are allowed");
        }
        
        // Validate file extension
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($file_extension !== 'pdf') {
            throw new InvalidArgumentException("File must have .pdf extension");
        }
        
        return $file;
    }
}

try {
    // Validate input data
    $validated_data = TrainingDetailsValidator::validateTrainingData();
    $file = TrainingDetailsValidator::validateFileUpload();
    
    // Determine department
    if ($validated_data['department'] == '99') {
        $department = DB::queryFirstField(
            "SELECT department_id FROM users WHERE user_id = %i", 
            intval($validated_data['employee'])
        );
        
        if (!$department) {
            throw new Exception("Employee department not found");
        }
    } else {
        $department = intval($validated_data['department']);
    }
    
    $employee = intval($validated_data['employee']);
    
    // Execute secure transaction
    $result = executeSecureTransaction(function() use ($validated_data, $file, $department, $employee) {
        // Check for existing active record
        $existingRecord = DB::queryFirstRow(
            "SELECT id FROM tbl_training_details 
            WHERE record_status = 'Active' 
            AND user_id = %i 
            AND department_id = %i 
            AND val_wf_id = %s",
            $employee,
            $department,
            $validated_data['val_wf_id']
        );

        if ($existingRecord) {
            throw new Exception("A training certificate already exists for this employee in the selected department.");
        }

        // Create secure unique filename to avoid overwriting existing files
        $fileName = $file['name'];
        $fileSize = $file['size'];
        $fileTmpName = $file['tmp_name'];
        
        // Generate secure filename with timestamp and random component
        $file_extension = pathinfo($fileName, PATHINFO_EXTENSION);
        $base_name = pathinfo($fileName, PATHINFO_FILENAME);
        $uniqueFileName = time() . '_' . bin2hex(random_bytes(8)) . '_' . $base_name . '.' . $file_extension;
        $targetFilePath = "./../../../uploads/" . $uniqueFileName;

        // Ensure uploads directory exists and is writable
      //  $uploads_dir = dirname($targetFilePath);
      //  if (!is_dir($uploads_dir)) {
       //     if (!mkdir($uploads_dir, 0755, true)) {
       //         throw new Exception("Failed to create uploads directory");
       //     }
       // }

        // Check if the file was successfully uploaded
        if (!move_uploaded_file($fileTmpName, $targetFilePath)) {
            throw new Exception("Error uploading file: " . $fileName);
        }

        // Insert data into the database table
        $result = DB::insert('tbl_training_details', [
            'val_wf_id' => $validated_data['val_wf_id'],
            'department_id' => $department,
            'user_id' => $employee,
            'file_name' => $fileName,
            'file_path' => substr($targetFilePath, 3), // Remove '../' prefix
            'file_size' => $fileSize,
            'record_status' => 'Active'
        ]);

        if (!$result) {
            // Clean up uploaded file on database failure
            if (file_exists($targetFilePath)) {
                unlink($targetFilePath);
            }
            throw new Exception("Database insert failed");
        }

        $record_id = DB::insertId();

        // Get employee name for logging
        $employee_name = DB::queryFirstField("SELECT user_name FROM users WHERE user_id = %i", $employee);

        // Insert log entry
        $unit_id = !empty($_SESSION['unit_id']) ? $_SESSION['unit_id'] : 0;
        DB::insert('log', [
            'change_type' => 'tran_traindtls_added',
            'table_name' => 'tbl_training_details',
            'change_description' => 'Training details added for ' . $employee_name . '. Record ID:' . $record_id . 
                                  ' Val WF ID: ' . $validated_data['val_wf_id'] . ' Stage: Team Approval Submission Pending.',
            'change_by' => $_SESSION['user_id'],
            'unit_id' => $unit_id
        ]);

        return $record_id;
    });
    
    if ($result) {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success',
            'message' => 'Training details saved successfully.',
            'csrf_token' => $_SESSION['csrf_token']
        ]);
    } else {
        throw new Exception("Transaction failed");
    }
    
} catch (InvalidArgumentException $e) {
    logDatabaseError("Training details validation error: " . $e->getMessage(), [
        'operation_name' => 'save_training_details',
        'val_wf_id' => $_POST['val_wf_id'] ?? null,
        'employee' => $_POST['employee'] ?? null,
        'department' => $_POST['department'] ?? null
    ]);
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'csrf_token' => $_SESSION['csrf_token']
    ]);
} catch (Exception $e) {
    logDatabaseError("Training details error: " . $e->getMessage(), [
        'operation_name' => 'save_training_details',
        'val_wf_id' => $_POST['val_wf_id'] ?? null,
        'employee' => $_POST['employee'] ?? null,
        'department' => $_POST['department'] ?? null
    ]);
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'csrf_token' => $_SESSION['csrf_token']
    ]);
}

?>