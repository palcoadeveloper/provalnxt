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
        "SELECT validation_scheduling_logic FROM units WHERE unit_id = %d and unit_status='Active'", 
        $unit_id
    );
    
    // Get all active equipment data for validation
    $equipment_list = DB::query(
        "SELECT equipment_id, equipment_code, first_validation_date,
                validation_frequencies, starting_frequency,
                equipment_addition_date, validation_frequency
         FROM equipments
         WHERE unit_id = %d AND equipment_status = 'Active'",
        $unit_id
    );

    if (empty($equipment_list)) {
        echo "No active equipment found for this unit.";
        exit();
    }

    $missing_data = [];

    // Validate each equipment based on scheduling logic
    foreach ($equipment_list as $equipment) {
        $missing_fields = [];

        if ($validation_logic === 'fixed') {
            // Fixed Date Logic: Require ALL three fields

            // 1. First Validation Date (mandatory)
            if (empty($equipment['first_validation_date']) || $equipment['first_validation_date'] === null) {
                $missing_fields[] = 'First Validation Date';
            }

            // 2. Validation Frequencies (mandatory)
            if (empty($equipment['validation_frequencies']) || $equipment['validation_frequencies'] === null) {
                $missing_fields[] = 'Validation Frequencies';
            }

            // 3. Starting Frequency (mandatory)
            if (empty($equipment['starting_frequency']) || $equipment['starting_frequency'] === null) {
                $missing_fields[] = 'Starting Frequency';
            }

        } else {
            // Dynamic Date Logic: Require equipment_addition_date and validation_frequency

            // 1. Equipment Addition Date (mandatory)
            if (empty($equipment['equipment_addition_date']) || $equipment['equipment_addition_date'] === null) {
                $missing_fields[] = 'Equipment Addition Date';
            }

            // 2. Validation Frequency (mandatory)
            if (empty($equipment['validation_frequency']) || $equipment['validation_frequency'] === null) {
                $missing_fields[] = 'Validation Frequency';
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
        // Format error message with missing equipment details
        $scheduling_type = ($validation_logic === 'fixed') ? 'Fixed Date' : 'Dynamic Date';
        $error_message = "ERROR: The following active equipment have missing validation data required for {$scheduling_type} scheduling:<br><br>";

        $total_count = count($missing_data);

        // Show detailed information for first 5 equipments
        $detailed_count = min(5, $total_count);
        for ($i = 0; $i < $detailed_count; $i++) {
            $item = $missing_data[$i];
            $error_message .= "• Equipment: " . htmlspecialchars($item['equipment_code']) . " (ID: " . $item['equipment_id'] . ")<br>";
            $error_message .= "&nbsp;&nbsp;Missing: " . htmlspecialchars(implode(', ', $item['missing_fields'])) . "<br><br>";
        }

        // If more than 5 equipments, show only equipment codes for the remaining
        if ($total_count > 5) {
            $remaining_count = $total_count - 5;
            $error_message .= "<strong>Additional {$remaining_count} equipment(s) with missing data:</strong><br>";
            $remaining_codes = [];
            for ($i = 5; $i < $total_count; $i++) {
                $remaining_codes[] = htmlspecialchars($missing_data[$i]['equipment_code']);
            }
            $error_message .= implode(', ', $remaining_codes) . "<br><br>";
        }

        $error_message .= "<strong>Please complete the missing information before generating the validation schedule.</strong>";

        echo $error_message;
    }
    
} catch (Exception $e) {
    error_log("Database error in validateequipmentdata.php: " . $e->getMessage());
    echo "Database error occurred while validating equipment data.";
}
?>