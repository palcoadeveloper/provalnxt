<?php

session_start();

// Load configuration first
require_once(__DIR__ . '/../../config/config.php');
// Validate session timeout
require_once(__DIR__ . '/../../security/session_timeout_middleware.php');
validateActiveSession();
require_once __DIR__ . '/../../config/db.class.php';

date_default_timezone_set("Asia/Kolkata");

try {
    // Get all active rooms/locations for dropdown
    $rooms = DB::query("SELECT room_loc_id, room_loc_name FROM room_locations ORDER BY room_loc_name ASC");
    
    echo '<option value="Select">Select Room/Location</option>';
    
    if (!empty($rooms)) {
        foreach ($rooms as $room) {
            echo '<option value="' . $room['room_loc_id'] . '">' . 
                 htmlspecialchars($room['room_loc_name'], ENT_QUOTES, 'UTF-8') . 
                 '</option>';
        }
    }
    
} catch (Exception $e) {
    error_log("Error loading room details for dropdown: " . $e->getMessage());
    echo '<option value="Select">Error loading rooms</option>';
}

?>