<?php
require_once('./core/config/config.php');

// Session is already started by config.php via session_init.php
// Store all required session variables BEFORE destroying the session
$account_name = !empty($_SESSION['account_name']) ? $_SESSION['account_name'] : 'Unknown';
$user_id = !empty($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
$unit_id = !empty($_SESSION['unit_id']) ? $_SESSION['unit_id'] : 0;

// Now destroy the session
session_destroy();
include_once ("core/config/db.class.php");

// Log the logout using the stored variables
try {
    DB::insert('log', [
        'change_type' => 'tran_logout',
        'table_name'=>'',
        'change_description'=>'User '.$account_name.' has logged out.',
        'change_by'=>$user_id,
        'unit_id' => $unit_id
    ]);
} catch (Exception $e) {
    // If logging fails, still proceed with logout
    error_log("Logout logging failed: " . $e->getMessage());
}

header("Location:login.php?msg=user_logout");
exit(); // Always add exit after header redirect
?>
