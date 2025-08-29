<?php 

// Load configuration first
require_once('../../config/config.php');

// Session is already started by config.php via session_init.php

// Validate session timeout and extend for transaction
require_once('../../security/session_timeout_middleware.php');
validateActiveSession();
extendSessionForTransaction('validation_report_creation');
date_default_timezone_set("Asia/Kolkata");
require_once("../../config/db.class.php");

// Enable error reporting for debugging (always on for this issue)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if we can increase limits temporarily for debugging
if (function_exists('ini_set')) {
    ini_set('max_input_vars', 5000);
    ini_set('post_max_size', '50M');
    ini_set('upload_max_filesize', '10M');
}

// Debug multipart data parsing
error_log("=== CREATEREPORTDATA.PHP CALLED ===");
error_log("Timestamp: " . date('Y-m-d H:i:s'));
error_log("Content-Type: " . ($_SERVER['CONTENT_TYPE'] ?? 'NOT_SET'));
error_log("Request Method: " . $_SERVER['REQUEST_METHOD']);
error_log("Session user_id: " . ($_SESSION['user_id'] ?? 'NOT_SET'));

// Check PHP configuration for multipart handling
error_log("PHP Config - max_input_vars: " . ini_get('max_input_vars'));
error_log("PHP Config - post_max_size: " . ini_get('post_max_size'));
error_log("PHP Config - upload_max_filesize: " . ini_get('upload_max_filesize'));
error_log("Content Length: " . ($_SERVER['CONTENT_LENGTH'] ?? 'NOT_SET'));

// Check if POST data is properly parsed, if not, parse manually
if (empty($_POST)) {
    error_log("ERROR: POST array is empty, attempting manual parsing");
    
    $rawInput = file_get_contents('php://input');
    error_log("Raw input length: " . strlen($rawInput));
    
    if (strpos($rawInput, 'val_wf_id') !== false) {
        error_log("Raw input contains form data, parsing manually");
        
        // Parse multipart data manually
        $parsedData = parseMultipartData($rawInput, $_SERVER['CONTENT_TYPE'] ?? '');
        $_POST = $parsedData['post'];
        $_FILES = $parsedData['files'];
        
        error_log("Manual parsing complete. POST keys: " . implode(', ', array_keys($_POST)));
        error_log("Manual parsing complete. FILES keys: " . implode(', ', array_keys($_FILES)));
    } else {
        error_log("Raw input: " . substr($rawInput, 0, 500));
        die("Error: Missing required form data.Form Data: " . substr($rawInput, 0, 500));
    }
}

// Function to manually parse multipart form data
function parseMultipartData($rawData, $contentType) {
    $post = [];
    $files = [];
    
    // Extract boundary
    if (preg_match('/boundary=(.+)/', $contentType, $matches)) {
        $boundary = '--' . $matches[1];
    } else {
        return ['post' => $post, 'files' => $files];
    }
    
    // Split by boundary
    $parts = explode($boundary, $rawData);
    
    foreach ($parts as $part) {
        if (empty(trim($part)) || $part === '--') continue;
        
        // Split headers and content
        $headerEndPos = strpos($part, "\r\n\r\n");
        if ($headerEndPos === false) continue;
        
        $headers = substr($part, 0, $headerEndPos);
        $content = substr($part, $headerEndPos + 4);
        
        // Remove trailing CRLF
        $content = rtrim($content, "\r\n");
        
        // Parse Content-Disposition header
        if (preg_match('/name="([^"]+)"/', $headers, $nameMatches)) {
            $fieldName = $nameMatches[1];
            
            // Check if it's a file field
            if (preg_match('/filename="([^"]*)"/', $headers, $filenameMatches)) {
                // It's a file
                $filename = $filenameMatches[1];
                
                // Get content type
                $fileContentType = 'application/octet-stream';
                if (preg_match('/Content-Type:\s*(.+)/', $headers, $contentTypeMatches)) {
                    $fileContentType = trim($contentTypeMatches[1]);
                }
                
                // Create temporary file
                $tempFile = tempnam(sys_get_temp_dir(), 'upload_');
                file_put_contents($tempFile, $content);
                
                $files[$fieldName] = [
                    'name' => $filename,
                    'type' => $fileContentType,
                    'tmp_name' => $tempFile,
                    'error' => UPLOAD_ERR_OK,
                    'size' => strlen($content)
                ];
            } else {
                // It's a regular field
                $post[$fieldName] = $content;
            }
        }
    }
    
    return ['post' => $post, 'files' => $files];
}

error_log("POST data keys: " . implode(', ', array_keys($_POST)));
error_log("FILES data keys: " . implode(', ', array_keys($_FILES)));

// Validate required data
if (empty($_POST['val_wf_id'])) {
    error_log("ERROR: val_wf_id is missing");
    die("Error: Validation workflow ID is missing");
}

// Get the number of entries from the new field
$entryCount = isset($_POST['entry_count']) ? intval($_POST['entry_count']) : 0;
error_log("Entry count received: " . $entryCount);

if ($entryCount == 0) {
    error_log("ERROR: No entries to process");
    die("Error: No training certificates uploaded");
}

// Process each entry using the unique field names
$uploadErrors = [];
$uploadSuccess = [];

for ($i = 0; $i < $entryCount; $i++) {
    $fileFieldName = "file_$i";
    $deptFieldName = "dept_$i";
    $empFieldName = "emp_$i";
    
    // Check if this entry exists
    if (!isset($_FILES[$fileFieldName]) || !isset($_POST[$deptFieldName]) || !isset($_POST[$empFieldName])) {
        error_log("ERROR: Missing data for entry $i");
        $uploadErrors[] = "Missing data for entry $i";
        continue;
    }
    
    // Get the file info
    $fileName = $_FILES[$fileFieldName]['name'];
    $fileTmpName = $_FILES[$fileFieldName]['tmp_name'];
    $fileSize = $_FILES[$fileFieldName]['size'];
    $fileError = $_FILES[$fileFieldName]['error'];
    
    // Get department and employee
    $department = $_POST[$deptFieldName];
    $employee = $_POST[$empFieldName];
    
    error_log("Processing entry $i: File=$fileName, Dept=$department, Emp=$employee");
    
    // Check for upload errors
    if ($fileError !== UPLOAD_ERR_OK) {
        $uploadErrors[] = "File upload error for $fileName (Error code: $fileError)";
        continue;
    }
    
    // Validate file size (5MB limit)
    if ($fileSize > 5 * 1024 * 1024) {
        $uploadErrors[] = "File too large: $fileName (Max 5MB allowed)";
        continue;
    }

    // Validate file extension
    $allowedExtensions = ['pdf'];
    $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    if (!in_array($fileExtension, $allowedExtensions)) {
        $uploadErrors[] = "Invalid file type for $fileName. Only PDF files allowed.";
        continue;
    }

    // Create a unique filename
    $uniqueFileName = time() . '_' . $i . '_' . $fileName;

    // Set the destination path for file operations
    $uploadsDir = realpath(__DIR__ . '/../uploads/');
    if (!$uploadsDir) {
        error_log("ERROR: uploads directory not found");
        $uploadErrors[] = "Upload directory not accessible";
        break;
    }
    
    $absoluteFilePath = $uploadsDir . '/' . $uniqueFileName;
    
    // Set the relative path for database storage
    $relativeFilePath = 'uploads/' . $uniqueFileName;
    
    // Upload the file
    if (move_uploaded_file($fileTmpName, $absoluteFilePath)) {
        // Get department ID from user
        $dept_id = DB::queryFirstField("SELECT department_id FROM users WHERE user_id = %i", intval($employee));
        
        if (!$dept_id) {
            $uploadErrors[] = "Employee not found for file: $fileName";
            unlink($absoluteFilePath);
            continue;
        }
        
        // Insert into database with relative path
        $result = DB::insert('tbl_training_details', array(
            'val_wf_id' => $_POST['val_wf_id'],
            'department_id' => $dept_id,
            'user_id' => intval($employee),
            'file_name' => $fileName,
            'file_path' => $relativeFilePath,  // Use relative path for database
            'file_size' => $fileSize     
        ));

        if ($result) {
            $uploadSuccess[] = "File $fileName uploaded successfully";
            error_log("SUCCESS: File uploaded for entry $i");
        } else {
            $uploadErrors[] = "Database error for file: $fileName";
            error_log("ERROR: Database insert failed for entry $i");
            unlink($absoluteFilePath);
        }
    } else {
        $uploadErrors[] = "Failed to save file: $fileName";
        error_log("ERROR: Failed to move file for entry $i");
    }
}

// Log results
error_log("Upload results - Success: " . count($uploadSuccess) . ", Errors: " . count($uploadErrors));

// Insert validation report
try {
    $reportResult = DB::insert('validation_reports', [
        'val_wf_id' => $_POST['val_wf_id'],
        'justification'=> $_POST['justification'] ?? '',
        'sop1_doc_number' => $_POST['sop1'] ?? '',
        'sop1_entered_by_user_id'=>$_SESSION['user_id'],
        'sop1_entered_date'=>DB::sqleval("NOW()"),
        'sop2_doc_number' => $_POST['sop2'] ?? '',
        'sop2_entered_by_user_id'=>$_SESSION['user_id'],
        'sop2_entered_date'=>DB::sqleval("NOW()"),
        'sop3_doc_number' => $_POST['sop3'] ?? '',
        'sop3_entered_by_user_id'=>$_SESSION['user_id'],
        'sop3_entered_date'=>DB::sqleval("NOW()"),
        'sop4_doc_number' => $_POST['sop4'] ?? '',
        'sop4_entered_by_user_id'=>$_SESSION['user_id'],
        'sop4_entered_date'=>DB::sqleval("NOW()"),
        'sop5_doc_number' => $_POST['sop5'] ?? '',
        'sop5_entered_by_user_id'=>$_SESSION['user_id'],
        'sop5_entered_date'=>DB::sqleval("NOW()"),
        'sop6_doc_number' => $_POST['sop6'] ?? '',
        'sop6_entered_by_user_id'=>$_SESSION['user_id'],
        'sop6_entered_date'=>DB::sqleval("NOW()"),
        'sop7_doc_number' => $_POST['sop7'] ?? '',
        'sop7_entered_by_user_id'=>$_SESSION['user_id'],
        'sop7_entered_date'=>DB::sqleval("NOW()"),
        'sop8_doc_number' => $_POST['sop8'] ?? '',
        'sop8_entered_by_user_id'=>$_SESSION['user_id'],
        'sop8_entered_date'=>DB::sqleval("NOW()"),
        'sop9_doc_number' => $_POST['sop9'] ?? '',
        'sop9_entered_by_user_id'=>$_SESSION['user_id'],
        'sop9_entered_date'=>DB::sqleval("NOW()"),
        'sop10_doc_number' => $_POST['sop10'] ?? '',
        'sop10_entered_by_user_id'=>$_SESSION['user_id'],
        'sop10_entered_date'=>DB::sqleval("NOW()"),
        'sop11_doc_number' => $_POST['sop11'] ?? '',
        'sop11_entered_by_user_id'=>$_SESSION['user_id'],
        'sop11_entered_date'=> DB::sqleval("NOW()"),
        'sop12_doc_number' => $_POST['sop12'] ?? '',
        'sop12_entered_by_user_id'=>$_SESSION['user_id'],
        'sop12_entered_date'=>DB::sqleval("NOW()"),
        'sop13_doc_number' => $_POST['sop13'] ?? '',
        'sop13_entered_by_user_id'=>$_SESSION['user_id'],
        'sop13_entered_date'=>DB::sqleval("NOW()")
    ]);
    
    if ($reportResult) {
        error_log("SUCCESS: Validation report inserted");
    }
} catch (Exception $e) {
    error_log("ERROR: Validation report insert failed: " . $e->getMessage());
    die("Error: Failed to save validation report");
}

// Handle deviation remarks
if(!empty($_POST['deviation_remark'])) {
    try {
        DB::query("UPDATE validation_reports SET deviation=CONCAT(IFNULL(deviation,''),' ', %s) WHERE val_wf_id=%s",
                  $_POST['deviation_remark'], $_POST['val_wf_id']);
        error_log("SUCCESS: Deviation remark added");
    } catch (Exception $e) {
        error_log("ERROR: Failed to add deviation remark: " . $e->getMessage());
    }
}

// Final response
if (count($uploadErrors) > 0 && count($uploadSuccess) == 0) {
    error_log("FAILURE: All uploads failed");
    die("Error: " . implode('; ', $uploadErrors));
} else if (count($uploadErrors) > 0) {
    error_log("PARTIAL SUCCESS: Some uploads failed");
    echo "Partial success - " . count($uploadSuccess) . " files uploaded, " . count($uploadErrors) . " failed";
} else {
    error_log("SUCCESS: All operations completed");
    echo "success";
}