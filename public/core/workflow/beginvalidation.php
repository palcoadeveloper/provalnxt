<?php

// Load configuration first
require_once('../config/config.php');

// Session is already started by config.php via session_init.php

// Validate session timeout
require_once('../security/session_timeout_middleware.php');
validateActiveSession();
require_once('../config/db.class.php');
date_default_timezone_set("Asia/Kolkata");
// Input validation
if (!isset($_GET['u']) || !isset($_GET['e']) || !isset($_GET['d']) || !isset($_GET['w']) || !isset($_GET['l'])) {
    header('HTTP/1.1 400 Bad Request');
    die('Error: Missing required parameters');
}

// Validate parameter types
if (!is_numeric($_GET['u']) || !is_numeric($_GET['e']) || !is_numeric($_GET['l'])) {
    header('HTTP/1.1 400 Bad Request');
    die('Error: Invalid parameter types');
}

try {
    $results = DB::query("CALL start_validation_task(%i,%i,%s,%s,%i)", 
                        intval($_GET['u']), 
                        intval($_GET['e']), 
                        $_GET['d'], 
                        $_GET['w'], 
                        intval($_GET['l']));
} catch (Exception $e) {
    require_once('../error/error_logger.php');
    logDatabaseError("Database error in beginvalidation.php: " . $e->getMessage(), [
        'operation_name' => 'start_validation_task',
        'unit_id' => $_GET['u'] ?? null,
        'equipment_id' => $_GET['e'] ?? null,
        'val_wf_id' => $_GET['w'] ?? null,
        'user_id' => $_GET['l'] ?? null
    ]);
    
    header('HTTP/1.1 500 Internal Server Error');
    die('Error: Failed to start validation task');
}



if(empty($results))
{
    // Log that no results were returned
    error_log("Warning: start_validation_task returned no results for workflow ID: " . $_GET['w']);
}
else
{
    try {
        // Log successful validation start
        DB::insert('log', [
            'change_type' => 'tran_valbgn',
            'table_name'=>'',
            'change_description'=>'Validation begin. WorkflowID:'.$_GET['w'],
            'change_by'=>$_SESSION['user_id'],
            'unit_id' => $_SESSION['unit_id']
        ]);
    } catch (Exception $e) {
        require_once('../error/error_logger.php');
        logDatabaseError("Database error logging validation start: " . $e->getMessage(), [
            'operation_name' => 'validation_begin_log',
            'val_wf_id' => $_GET['w']
        ]);
        // Continue with redirect even if logging fails
    }
    
    // Fixed redirect path - go up two levels to reach root
    header('Location: ../../manageprotocols.php');
    exit();
}