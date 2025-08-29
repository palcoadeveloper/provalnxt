<?php 
session_start();

// Validate session timeout
require_once('../../security/session_timeout_middleware.php');
validateActiveSession();
include_once '../../config/db.class.php';

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

try {
    $fileName = $_FILES['trainingpdffile']['name'];
    $fileTmpName = $_FILES['trainingpdffile']['tmp_name'];
    $fileSize = $_FILES['trainingpdffile']['size'];
    
    if($_POST['department']=='99') {
        $department = DB::queryFirstField("select department_id from users where user_id=%i", $_POST['employee']);
    } else {
        $department = $_POST['department'];
    }
    
    $employee = $_POST['employee'];

    // Check for existing active record
    $existingRecord = DB::queryFirstRow(
        "SELECT id FROM tbl_training_details 
        WHERE record_status = 'Active' 
        AND user_id = %i 
        AND department_id = %i 
        AND val_wf_id = %s",
        $employee,
        $department,
        $_POST['val_wf_id']
    );

    if ($existingRecord) {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error',
            'message' => 'A training certificate already exists for this employee in the selected department.',
            'csrf_token' => $_SESSION['csrf_token']
        ]);
        exit;
    }

    // Create a unique filename to avoid overwriting existing files
    $uniqueFileName = time() . '_' . $fileName;
    $targetFilePath = "../uploads/" . $uniqueFileName;

    // Check if the file was successfully uploaded
    if (move_uploaded_file($fileTmpName, $targetFilePath)) {
        // Insert data into the database table
        $result = DB::insert('tbl_training_details', array(
            'val_wf_id' => $_POST['val_wf_id'],
            'department_id' => $department,
            'user_id' => $employee,
            'file_name' => $fileName,
            'file_path' => substr($targetFilePath,3),
            'file_size' => $fileSize,
            'record_status' => 'Active'
        ));

        if ($result) {
            // Get the inserted record ID
            $record_id = DB::insertId();
            
            // Get employee name for logging
            $employee_name = DB::queryFirstField("SELECT user_name FROM users WHERE user_id = %i", $employee);
            
            // Insert log entry
            $unit_id = !empty($_SESSION['unit_id']) ? $_SESSION['unit_id'] : 0;
            DB::insert('log', [
                'change_type' => 'tran_traindtls_added',
                'table_name' => 'tbl_training_details',
                'change_description' => 'Training details added for ' . $employee_name . '. Record ID:' . $record_id . ' Val WF ID: ' . $_POST['val_wf_id'] . ' Stage: Team Approval Submission Pending.',
                'change_by' => $_SESSION['user_id'],
                'unit_id' => $unit_id
            ]);
            
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'success',
                'message' => 'Training details saved successfully.',
                'csrf_token' => $_SESSION['csrf_token']
            ]);
        } else {
            throw new Exception("Database insert failed");
        }
    } else {
        throw new Exception("Error uploading file $fileName");
    }
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'csrf_token' => $_SESSION['csrf_token']
    ]);
}



?>