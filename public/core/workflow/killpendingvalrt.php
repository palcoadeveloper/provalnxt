<?php
// your_server_endpoint.php

// Include MeekroDB and your database configuration

require_once '../config/db.class.php';
// Set headers for JSON response
header('Content-Type: application/json');

// Check if it's a POST request and the action is 'get_pending_counts'
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_pending_counts') {
    
    // Get the equipment ID from the POST data
    $equipId = isset($_POST['equip_id']) ? intval($_POST['equip_id']) : 0;

    if ($equipId <= 0) {
        echo json_encode(['error' => 'Invalid equipment ID']);
        exit;
    }

    try {
        // Call the stored procedure using MeekroDB
        $result = DB::queryFirstRow("CALL get_pending_val_routinetests_count(%i)", $equipId);
        
        if ($result === null) {
            echo json_encode(['error' => 'No data returned from the procedure']);
        } else {
            // Return the result as JSON
            echo json_encode($result);
        }
    } catch (MeekroDBException $e) {
        // Handle any database errors
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    // If it's not a valid request
    echo json_encode(['error' => 'Invalid request']);
}