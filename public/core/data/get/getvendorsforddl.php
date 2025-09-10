<?php 
session_start();

// Load configuration first
require_once(__DIR__ . '/../../config/config.php');

// Validate session timeout
require_once(__DIR__ . '/../../security/session_timeout_middleware.php');

// Only validate session if we're in a web request
if (!empty($_SERVER['REQUEST_METHOD'])) {
    validateActiveSession();
}

require_once __DIR__ . '/../../config/db.class.php';

try {
    // Fetch all active vendors
    $vendors = DB::query("SELECT vendor_id, vendor_name FROM vendors WHERE vendor_status = 'Active' ORDER BY vendor_name ASC");
    
    // Generate options HTML
    echo '<option value="">All Vendors</option>';
    
    if (!empty($vendors)) {
        foreach ($vendors as $vendor) {
            echo '<option value="' . intval($vendor['vendor_id']) . '">' . 
                 htmlspecialchars($vendor['vendor_name'], ENT_QUOTES, 'UTF-8') . '</option>';
        }
    }
    
} catch (Exception $e) {
    error_log("Get vendors for dropdown error: " . $e->getMessage());
    echo '<option value="">Error loading vendors</option>';
}

?>