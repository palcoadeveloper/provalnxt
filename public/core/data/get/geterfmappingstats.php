<?php

session_start();

// Load configuration first  
require_once(__DIR__ . '/../../config/config.php');
// Validate session timeout
require_once(__DIR__ . '/../../security/session_timeout_middleware.php');
validateActiveSession();
require_once __DIR__ . '/../../config/db.class.php';

date_default_timezone_set("Asia/Kolkata");

// Set JSON content type header
header('Content-Type: application/json');

try {
    // Initialize statistics
    $stats = [
        'active_mappings' => 0,
        'inactive_mappings' => 0,
        'total_equipments' => 0,
        'unmapped_equipments' => 0,
        'total_rooms' => 0,
        'unmapped_rooms' => 0
    ];
    
    // Get active mappings count
    $result = DB::queryFirstRow("SELECT COUNT(*) as count FROM erf_mappings WHERE erf_mapping_status = %s", 'Active');
    $stats['active_mappings'] = $result ? intval($result['count']) : 0;
    
    // Get inactive mappings count
    $result = DB::queryFirstRow("SELECT COUNT(*) as count FROM erf_mappings WHERE erf_mapping_status = %s", 'Inactive');
    $stats['inactive_mappings'] = $result ? intval($result['count']) : 0;
    
    // Get total equipments with mappings
    $result = DB::queryFirstRow("SELECT COUNT(DISTINCT equipment_id) as count FROM erf_mappings");
    $stats['total_equipments'] = $result ? intval($result['count']) : 0;
    
    // Get unmapped equipments (equipments without any ERF mapping)
    $result = DB::queryFirstRow("
        SELECT COUNT(*) as count 
        FROM equipments e 
        WHERE e.equipment_status = %s 
        AND e.equipment_id NOT IN (SELECT DISTINCT equipment_id FROM erf_mappings)", 
        'Active'
    );
    $stats['unmapped_equipments'] = $result ? intval($result['count']) : 0;
    
    // Get total rooms with mappings
    $result = DB::queryFirstRow("SELECT COUNT(DISTINCT room_loc_id) as count FROM erf_mappings");
    $stats['total_rooms'] = $result ? intval($result['count']) : 0;
    
    // Get unmapped rooms (rooms without any ERF mapping)
    $result = DB::queryFirstRow("
        SELECT COUNT(*) as count 
        FROM room_locations rl 
        WHERE rl.room_loc_id NOT IN (SELECT DISTINCT room_loc_id FROM erf_mappings)"
    );
    $stats['unmapped_rooms'] = $result ? intval($result['count']) : 0;
    
    // Return JSON response
    echo json_encode($stats);
    
} catch (Exception $e) {
    error_log("Error fetching ERF mapping statistics: " . $e->getMessage());
    // Return default stats on error
    echo json_encode([
        'active_mappings' => 0,
        'inactive_mappings' => 0, 
        'total_equipments' => 0,
        'unmapped_equipments' => 0,
        'total_rooms' => 0,
        'unmapped_rooms' => 0
    ]);
}

?>