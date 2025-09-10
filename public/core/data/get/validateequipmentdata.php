<?php
// Load configuration first
require_once(__DIR__ . '/../../config/config.php');

// Validate session timeout
require_once('../../security/session_timeout_middleware.php');
validateActiveSession();

require_once '../../config/db.class.php';
date_default_timezone_set("Asia/Kolkata");

// Get parameters
$unit_id = intval($_GET['unitid'] ?? 0);
$schedule_year = intval($_GET['schyear'] ?? 0);

// Validate parameters
if ($unit_id <= 0 || $schedule_year <= 0) {
    echo "Invalid parameters provided.";
    exit();
}

try {
    // Check the unit's validation scheduling logic
    $validation_logic = DB::queryFirstField(
        "SELECT validation_scheduling_logic FROM units WHERE unit_id = %d", 
        $unit_id
    );
    
    // If not fixed scheduling logic, no validation needed
    if ($validation_logic !== 'fixed') {
        echo "valid";
        exit();
    }
    
    // For fixed scheduling logic, validate all active equipment data
    $equipment_list = DB::query(
        "SELECT equipment_id, equipment_code, first_validation_date, 
                validation_frequencies, starting_frequency
         FROM equipments 
         WHERE unit_id = %d AND equipment_status = 'Active'", 
        $unit_id
    );
    
    if (empty($equipment_list)) {
        echo "No active equipment found for this unit.";
        exit();
    }
    
    $missing_data = [];
    
    // Validate each equipment independently
    foreach ($equipment_list as $equipment) {
        $missing_fields = [];
        
        // 1. Check first_validation_date (always required)
        if (empty($equipment['first_validation_date']) || $equipment['first_validation_date'] === null) {
            $missing_fields[] = 'First Validation Date';
        }
        
        // 2. Check frequency type and validate accordingly
        if (!empty($equipment['validation_frequencies']) && $equipment['validation_frequencies'] !== null) {
            // Combined frequency type - validation_frequencies should be complete
            // Since it's not empty, it's considered valid (combined type detected)
        } else {
            // Single frequency type - starting_frequency should not be empty
            if (empty($equipment['starting_frequency']) || $equipment['starting_frequency'] === null) {
                $missing_fields[] = 'Starting Frequency';
            }
        }
        
        // If any fields are missing for this equipment, add to missing_data array
        if (!empty($missing_fields)) {
            $missing_data[] = [
                'equipment_id' => $equipment['equipment_id'],
                'equipment_code' => $equipment['equipment_code'],
                'missing_fields' => $missing_fields
            ];
        }
    }
    
    // Check if there are any missing data
    if (empty($missing_data)) {
        echo "valid";
    } else {
        // Format warning message with missing equipment details
        $warning_message = "The following active equipment have missing validation data required for Fixed Date scheduling:<br><br>";
        
        foreach ($missing_data as $item) {
            $warning_message .= "• Equipment: " . $item['equipment_code'] . " (ID: " . $item['equipment_id'] . ")<br>";
            $warning_message .= "&nbsp;&nbsp;Missing: " . implode(', ', $item['missing_fields']) . "<br><br>";
        }
        
        $warning_message .= "Do you want to proceed with schedule generation anyway?";
        
        echo $warning_message;
    }
    
} catch (Exception $e) {
    error_log("Database error in validateequipmentdata.php: " . $e->getMessage());
    echo "Database error occurred while validating equipment data.";
}
?>