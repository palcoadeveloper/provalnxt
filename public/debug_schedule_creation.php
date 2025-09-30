<?php
require_once('./core/config/config.php');
require_once('core/security/session_timeout_middleware.php');
validateActiveSession();
require_once 'core/config/db.class.php';

echo "<h2>Schedule Creation Debug</h2>";

// Check if schedule request 121 exists
echo "<h3>1. Schedule Request Record:</h3>";
$schedule_request = DB::queryFirstRow(
    "SELECT * FROM tbl_val_wf_schedule_requests WHERE schedule_id = 121"
);

if ($schedule_request) {
    echo "<p style='color: green;'>✓ Schedule request found:</p>";
    echo "<pre>" . print_r($schedule_request, true) . "</pre>";
} else {
    echo "<p style='color: red;'>✗ Schedule request 121 not found</p>";
}

// Check proposed schedules for this schedule_id
echo "<h3>2. Proposed Schedule Records:</h3>";
$proposed_count = DB::queryFirstField(
    "SELECT COUNT(*) FROM tbl_proposed_val_schedules WHERE schedule_id = 121"
);

echo "<p><strong>Records in tbl_proposed_val_schedules for schedule_id 121:</strong> $proposed_count</p>";

if ($proposed_count > 0) {
    $proposed_schedules = DB::query(
        "SELECT * FROM tbl_proposed_val_schedules WHERE schedule_id = 121 LIMIT 5"
    );
    echo "<p style='color: green;'>✓ Sample proposed schedule records:</p>";
    echo "<pre>" . print_r($proposed_schedules, true) . "</pre>";
} else {
    echo "<p style='color: red;'>✗ No records in tbl_proposed_val_schedules for schedule_id 121</p>";
}

// Check what equipments exist for unit 7
echo "<h3>3. Available Equipment for Unit 7:</h3>";
$equipment_count = DB::queryFirstField(
    "SELECT COUNT(*) FROM equipments WHERE unit_id = 7"
);

echo "<p><strong>Total equipment for unit 7:</strong> $equipment_count</p>";

if ($equipment_count > 0) {
    $sample_equipment = DB::query(
        "SELECT equipment_id, equipment_code, equipment_category, unit_id
         FROM equipments
         WHERE unit_id = 7
         LIMIT 5"
    );
    echo "<p>Sample equipment:</p>";
    echo "<pre>" . print_r($sample_equipment, true) . "</pre>";
}

// Check if there are any validation workflows for unit 7
echo "<h3>4. Validation Workflows for Unit 7:</h3>";
$vwf_count = DB::queryFirstField(
    "SELECT COUNT(*) FROM tbl_val_wf WHERE unit_id = 7"
);

echo "<p><strong>Validation workflows for unit 7:</strong> $vwf_count</p>";

if ($vwf_count > 0) {
    $sample_vwf = DB::query(
        "SELECT val_wf_id, equip_id, test_type, val_wf_status
         FROM tbl_val_wf
         WHERE unit_id = 7
         LIMIT 5"
    );
    echo "<p>Sample validation workflows:</p>";
    echo "<pre>" . print_r($sample_vwf, true) . "</pre>";
}

// Check recent proposed schedules for other units to see the structure
echo "<h3>5. Recent Proposed Schedules (Other Units for Reference):</h3>";
$recent_proposed = DB::query(
    "SELECT schedule_id, equip_id, val_wf_planned_start_date, created_on
     FROM tbl_proposed_val_schedules
     ORDER BY created_on DESC
     LIMIT 5"
);

if (!empty($recent_proposed)) {
    echo "<p>Recent proposed schedule records:</p>";
    echo "<pre>" . print_r($recent_proposed, true) . "</pre>";
} else {
    echo "<p style='color: orange;'>No recent proposed schedule records found</p>";
}

// Test the stored procedure manually
echo "<h3>6. Manual Stored Procedure Test:</h3>";
echo "<p><strong>Attempting to call USP_FIXED_CREATESCHEDULES(7, 2025):</strong></p>";

try {
    $result = DB::queryFirstField("call USP_FIXED_CREATESCHEDULES(7, 2025)");
    echo "<p style='color: green;'>Procedure result: <strong>$result</strong></p>";

    // Check if new records were created
    $new_count = DB::queryFirstField(
        "SELECT COUNT(*) FROM tbl_proposed_val_schedules WHERE schedule_id = (SELECT MAX(schedule_id) FROM tbl_val_wf_schedule_requests WHERE unit_id = 7)"
    );
    echo "<p>Records after procedure call: <strong>$new_count</strong></p>";

} catch (Exception $e) {
    echo "<p style='color: red;'>Procedure error: " . $e->getMessage() . "</p>";
}
?>