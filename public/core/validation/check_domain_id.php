<?php

// Load configuration first
require_once('../config/config.php');

// Session is already started by config.php via session_init.php

// Validate session timeout
require_once('../security/session_timeout_middleware.php');
validateActiveSession();
// Check if user is logged in
if(!isset($_SESSION['user_name'])) {
    echo "unauthorized";
    exit;
}

include_once '../config/db.class.php';

// Get the domain ID from the request
$domain_id = trim($_GET['domain_id']);
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

// If it's empty, return error
if(empty($domain_id)) {
    echo "empty";
    exit;
}

// Check if this is an update operation
if($user_id > 0) {
    // For update, check if any OTHER user has this domain ID
    $result = DB::queryFirstRow("SELECT COUNT(*) as count FROM users WHERE user_domain_id = %s AND user_id != %d", 
                               $domain_id, $user_id);
} else {
    // For new user, check if ANY user has this domain ID
    $result = DB::queryFirstRow("SELECT COUNT(*) as count FROM users WHERE user_domain_id = %s", $domain_id);
}

if($result['count'] > 0) {
    echo "exists";
} else {
    echo "available";
}
?>