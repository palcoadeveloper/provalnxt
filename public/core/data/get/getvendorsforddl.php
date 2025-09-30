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
    // Check if user is vendor and restrict dropdown accordingly
    if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'vendor' &&
        isset($_SESSION['vendor_id']) && $_SESSION['vendor_id'] > 0) {

        // Vendor users: Show only their vendor
        $vendors = DB::query("SELECT vendor_id, vendor_name FROM vendors
                             WHERE vendor_status = 'Active' AND vendor_id = %i
                             ORDER BY vendor_name ASC", $_SESSION['vendor_id']);

        // Generate options HTML - no "All Vendors" option for vendor users
        if (!empty($vendors)) {
            foreach ($vendors as $vendor) {
                echo '<option value="' . intval($vendor['vendor_id']) . '" selected>' .
                     htmlspecialchars($vendor['vendor_name'], ENT_QUOTES, 'UTF-8') . '</option>';
            }
        } else {
            echo '<option value="">No vendor found</option>';
        }

    } else {
        // Admin users: Show all vendors
        $vendors = DB::query("SELECT vendor_id, vendor_name FROM vendors WHERE vendor_status = 'Active' ORDER BY vendor_name ASC");

        // Generate options HTML with "All Vendors" option for admin users
        echo '<option value="">All Vendors</option>';

        if (!empty($vendors)) {
            foreach ($vendors as $vendor) {
                echo '<option value="' . intval($vendor['vendor_id']) . '">' .
                     htmlspecialchars($vendor['vendor_name'], ENT_QUOTES, 'UTF-8') . '</option>';
            }
        }
    }
    
} catch (Exception $e) {
    error_log("Get vendors for dropdown error: " . $e->getMessage());
    echo '<option value="">Error loading vendors</option>';
}

?>