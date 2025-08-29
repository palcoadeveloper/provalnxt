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

// Validate required data
if (empty($_POST['val_wf_id'])) {
    die("Error: Validation workflow ID is missing");
}

// Get the number of entries from the entry_count field
$entryCount = isset($_POST['entry_count']) ? intval($_POST['entry_count']) : 0;

if ($entryCount == 0) {
    die("Error: No training certificates uploaded");
}

// Process each entry using the indexed field names
$uploadErrors = [];
$uploadSuccess = [];

for ($i = 0; $i < $entryCount; $i++) {
    $fileFieldName = "file_$i";
    $deptFieldName = "dept_$i";
    $empFieldName = "emp_$i";
    
    // Check if this entry exists
    if (!isset($_FILES[$fileFieldName]) || !isset($_POST[$deptFieldName]) || !isset($_POST[$empFieldName])) {
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
    $uploadsDir = realpath(__DIR__ . '/../../../uploads/');
    if (!$uploadsDir) {
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
        
        try {
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
            } else {
                $uploadErrors[] = "Database insert failed for file: $fileName - no result returned";
                unlink($absoluteFilePath);
            }
        } catch (Exception $e) {
            require_once('../../error/error_logger.php');
            logDatabaseError("Database error in createreportdata.php: " . $e->getMessage(), [
                'operation_name' => 'training_file_upload',
                'val_wf_id' => $_POST['val_wf_id'],
                'entry_index' => $i,
                'file_name' => $fileName,
                'dept_id' => $dept_id,
                'employee' => $employee
            ]);
            $uploadErrors[] = "Database error for file: $fileName - " . $e->getMessage();
            unlink($absoluteFilePath);
        }
    } else {
        $uploadErrors[] = "Failed to save file: $fileName";
    }
}

// Insert validation report
try {
    // Check if user_id is available in session
    if (!isset($_SESSION['user_id'])) {
        die("Error: User session not found. Please login again.");
    }
    
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
    
    if (!$reportResult) {
        die("Error: Failed to insert validation report - database insert returned false");
    }
} catch (Exception $e) {
    require_once('../../error/error_logger.php');
    logDatabaseError("Database error in createreportdata.php: " . $e->getMessage(), [
        'operation_name' => 'validation_report_insert',
        'val_wf_id' => $_POST['val_wf_id'],
        'user_id' => $_SESSION['user_id'] ?? 'NOT_SET'
    ]);
    die("Error: Failed to save validation report - " . $e->getMessage());
}

// Handle deviation remarks
if(!empty($_POST['deviation_remark'])) {
    try {
        DB::query("UPDATE validation_reports SET deviation=CONCAT(IFNULL(deviation,''),' ', %s) WHERE val_wf_id=%s",
                  $_POST['deviation_remark'], $_POST['val_wf_id']);
    } catch (Exception $e) {
        require_once('../../error/error_logger.php');
        logDatabaseError("Database error in createreportdata.php: " . $e->getMessage(), [
            'operation_name' => 'deviation_remark_update',
            'val_wf_id' => $_POST['val_wf_id']
        ]);
    }
}

// Final response
if (count($uploadErrors) > 0 && count($uploadSuccess) == 0) {
    die("Error: " . implode('; ', $uploadErrors));
} else if (count($uploadErrors) > 0) {
    echo "Partial success - " . count($uploadSuccess) . " files uploaded, " . count($uploadErrors) . " failed";
} else {
    echo "success";
}
