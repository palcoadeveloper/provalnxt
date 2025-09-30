<?php
require_once('./core/config/config.php');
require_once('core/security/session_timeout_middleware.php');
validateActiveSession();
require_once 'core/config/db.class.php';

echo "<h2>Schedule Creation Dependencies Debug</h2>";

$unit_id = 7;
$year = 2025;

// Check unit configuration
echo "<h3>1. Unit Configuration:</h3>";
$unit_info = DB::queryFirstRow(
    "SELECT unit_id, unit_name, unit_status, validation_scheduling_logic
     FROM units
     WHERE unit_id = %d", $unit_id
);

if ($unit_info) {
    echo "<pre>" . print_r($unit_info, true) . "</pre>";
    echo "<p><strong>Validation Logic:</strong> " . ($unit_info['validation_scheduling_logic'] ?? 'Not set') . "</p>";
} else {
    echo "<p style='color: red;'>Unit $unit_id not found</p>";
}

// Check completed validation workflows (prerequisite for fixed scheduling)
echo "<h3>2. Completed Validation Workflows for Unit $unit_id:</h3>";
$completed_vwf = DB::query(
    "SELECT val_wf_id, equip_id, test_type, val_wf_status, val_wf_approval_level_3_datetime
     FROM tbl_val_wf
     WHERE unit_id = %d AND val_wf_status = 'Approved'
     ORDER BY val_wf_approval_level_3_datetime DESC
     LIMIT 10", $unit_id
);

echo "<p><strong>Completed workflows:</strong> " . count($completed_vwf) . "</p>";
if (!empty($completed_vwf)) {
    foreach ($completed_vwf as $vwf) {
        echo "<p>• Val WF ID: {$vwf['val_wf_id']}, Equipment: {$vwf['equip_id']}, Type: {$vwf['test_type']}, Approved: {$vwf['val_wf_approval_level_3_datetime']}</p>";
    }
} else {
    echo "<p style='color: orange;'>No completed validation workflows found for unit $unit_id</p>";
}

// Check equipment with validation schedules
echo "<h3>3. Equipment Validation Schedule Information:</h3>";
$equipment_schedules = DB::query(
    "SELECT e.equipment_id, e.equipment_code, e.equipment_category,
            e.validation_frequency_months, e.next_validation_due_date
     FROM equipments e
     WHERE e.unit_id = %d
     ORDER BY e.equipment_code
     LIMIT 10", $unit_id
);

echo "<p><strong>Equipment with schedule info:</strong> " . count($equipment_schedules) . "</p>";
if (!empty($equipment_schedules)) {
    foreach ($equipment_schedules as $eq) {
        echo "<p>• {$eq['equipment_code']} ({$eq['equipment_category']}) - Frequency: {$eq['validation_frequency_months']} months, Next Due: {$eq['next_validation_due_date']}</p>";
    }
} else {
    echo "<p style='color: orange;'>No equipment schedule information found</p>";
}

// Check any existing schedules for reference
echo "<h3>4. Existing Schedule Patterns:</h3>";
$existing_schedules = DB::query(
    "SELECT vsr.schedule_id, vsr.unit_id, vsr.schedule_year, vsr.schedule_generation_datetime,
            COUNT(pvs.schedule_id) as proposed_count
     FROM tbl_val_wf_schedule_requests vsr
     LEFT JOIN tbl_proposed_val_schedules pvs ON vsr.schedule_id = pvs.schedule_id
     WHERE vsr.unit_id = %d
     GROUP BY vsr.schedule_id
     ORDER BY vsr.schedule_id DESC
     LIMIT 5", $unit_id
);

if (!empty($existing_schedules)) {
    echo "<table border='1'>";
    echo "<tr><th>Schedule ID</th><th>Year</th><th>Generated</th><th>Proposed Count</th></tr>";
    foreach ($existing_schedules as $sched) {
        $color = $sched['proposed_count'] > 0 ? 'green' : 'red';
        echo "<tr style='color: $color'>";
        echo "<td>{$sched['schedule_id']}</td>";
        echo "<td>{$sched['schedule_year']}</td>";
        echo "<td>{$sched['schedule_generation_datetime']}</td>";
        echo "<td>{$sched['proposed_count']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No existing schedules found for unit $unit_id</p>";
}

// Check table structure to see if there are any constraints
echo "<h3>5. Table Structure Check:</h3>";
try {
    $table_info = DB::query("DESCRIBE tbl_proposed_val_schedules");
    echo "<p><strong>tbl_proposed_val_schedules structure:</strong></p>";
    echo "<table border='1'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    foreach ($table_info as $column) {
        echo "<tr>";
        echo "<td>{$column['Field']}</td>";
        echo "<td>{$column['Type']}</td>";
        echo "<td>{$column['Null']}</td>";
        echo "<td>{$column['Key']}</td>";
        echo "<td>{$column['Default']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} catch (Exception $e) {
    echo "<p style='color: red;'>Error getting table structure: " . $e->getMessage() . "</p>";
}
?>