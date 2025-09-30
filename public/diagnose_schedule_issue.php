<?php
require_once('./core/config/config.php');
require_once('core/security/session_timeout_middleware.php');
validateActiveSession();
require_once 'core/config/db.class.php';

echo "<h2>Comprehensive Schedule Issue Diagnosis</h2>";

$unit_id = 7;
$year = 2025;

echo "<h3>🔍 Issue: USP_FIXED_CREATESCHEDULES($unit_id, $year) returns 'success' but no data in tbl_proposed_val_schedules</h3>";

// Step 1: Verify the procedure call and result
echo "<h3>1. Procedure Execution Test</h3>";
try {
    echo "<p>Calling USP_FIXED_CREATESCHEDULES($unit_id, $year)...</p>";
    $result = DB::queryFirstField("call USP_FIXED_CREATESCHEDULES(%d, %d)", $unit_id, $year);
    echo "<p><strong>Result:</strong> <span style='color: green;'>$result</span></p>";

    // Get the latest schedule ID
    $latest_schedule = DB::queryFirstRow(
        "SELECT schedule_id, schedule_generation_datetime, schedule_request_status
         FROM tbl_val_wf_schedule_requests
         WHERE unit_id = %d AND schedule_year = %d
         ORDER BY schedule_id DESC
         LIMIT 1", $unit_id, $year
    );

    if ($latest_schedule) {
        $schedule_id = $latest_schedule['schedule_id'];
        echo "<p><strong>Latest Schedule ID:</strong> $schedule_id</p>";
        echo "<p><strong>Generated:</strong> {$latest_schedule['schedule_generation_datetime']}</p>";
        echo "<p><strong>Status:</strong> {$latest_schedule['schedule_request_status']}</p>";

        // Check if proposed schedules exist
        $proposed_count = DB::queryFirstField(
            "SELECT COUNT(*) FROM tbl_proposed_val_schedules WHERE schedule_id = %d", $schedule_id
        );
        echo "<p><strong>Proposed schedules count:</strong> <span style='color: " . ($proposed_count > 0 ? 'green' : 'red') . ";'>$proposed_count</span></p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'><strong>Error:</strong> " . $e->getMessage() . "</p>";
}

// Step 2: Check prerequisites for fixed scheduling
echo "<h3>2. Prerequisites Check</h3>";

// Check if unit has fixed scheduling logic
$unit_info = DB::queryFirstRow(
    "SELECT validation_scheduling_logic, unit_status FROM units WHERE unit_id = %d", $unit_id
);

if ($unit_info) {
    $logic = $unit_info['validation_scheduling_logic'] ?? 'not set';
    $status = $unit_info['unit_status'];
    echo "<p><strong>Unit Status:</strong> $status</p>";
    echo "<p><strong>Scheduling Logic:</strong> $logic</p>";

    if ($logic !== 'fixed') {
        echo "<p style='color: orange;'>⚠️ Note: Unit is set to '$logic' scheduling, not 'fixed'</p>";
    }
} else {
    echo "<p style='color: red;'>❌ Unit $unit_id not found</p>";
}

// Check for completed validation workflows (required for fixed scheduling)
echo "<h3>3. Validation Workflow Prerequisites</h3>";

$completed_vwf_count = DB::queryFirstField(
    "SELECT COUNT(*) FROM tbl_val_wf
     WHERE unit_id = %d AND val_wf_status = 'Approved'", $unit_id
);

echo "<p><strong>Completed validation workflows:</strong> $completed_vwf_count</p>";

if ($completed_vwf_count == 0) {
    echo "<p style='color: red;'>❌ No completed validation workflows found. Fixed scheduling requires completed validations as templates.</p>";
} else {
    echo "<p style='color: green;'>✅ Completed validations available</p>";

    // Check specific validation workflow details
    $sample_vwf = DB::query(
        "SELECT val_wf_id, equip_id, test_type, val_wf_approval_level_3_datetime,
                validation_frequency_months
         FROM tbl_val_wf
         WHERE unit_id = %d AND val_wf_status = 'Approved'
         ORDER BY val_wf_approval_level_3_datetime DESC
         LIMIT 5", $unit_id
    );

    echo "<p><strong>Recent completed workflows:</strong></p>";
    echo "<table border='1'>";
    echo "<tr><th>Val WF ID</th><th>Equipment ID</th><th>Test Type</th><th>Frequency (months)</th><th>Approved Date</th></tr>";
    foreach ($sample_vwf as $vwf) {
        echo "<tr>";
        echo "<td>{$vwf['val_wf_id']}</td>";
        echo "<td>{$vwf['equip_id']}</td>";
        echo "<td>{$vwf['test_type']}</td>";
        echo "<td>{$vwf['validation_frequency_months']}</td>";
        echo "<td>{$vwf['val_wf_approval_level_3_datetime']}</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Step 4: Check if current year validations are complete (common requirement)
echo "<h3>4. Current Year Validation Status</h3>";

$current_year = date('Y');
$current_year_pending = DB::queryFirstField(
    "SELECT COUNT(*) FROM tbl_val_wf
     WHERE unit_id = %d
     AND YEAR(val_wf_scheduled_start_date) = %d
     AND val_wf_status != 'Approved'", $unit_id, $current_year
);

echo "<p><strong>Pending validations for $current_year:</strong> $current_year_pending</p>";

if ($current_year_pending > 0) {
    echo "<p style='color: orange;'>⚠️ There are $current_year_pending pending validations for $current_year. Some procedures require current year completion.</p>";
}

// Step 5: Manual data insertion test
echo "<h3>5. Manual Insertion Test</h3>";

if (isset($schedule_id)) {
    echo "<p>Testing manual insertion to verify table access...</p>";

    try {
        // Try to insert a test record
        $test_equip_id = DB::queryFirstField(
            "SELECT equipment_id FROM equipments WHERE unit_id = %d LIMIT 1", $unit_id
        );

        if ($test_equip_id) {
            DB::insert('tbl_proposed_val_schedules', [
                'schedule_id' => $schedule_id,
                'equip_id' => $test_equip_id,
                'val_wf_planned_start_date' => '2025-06-15',
                'created_on' => date('Y-m-d H:i:s'),
                'created_by' => $_SESSION['user_id'],
                'frequency_type' => 'Monthly',
                'cycle_position' => 1,
                'cycle_count' => 12
            ]);

            echo "<p style='color: green;'>✅ Manual insertion successful</p>";

            // Verify insertion
            $test_count = DB::queryFirstField(
                "SELECT COUNT(*) FROM tbl_proposed_val_schedules WHERE schedule_id = %d", $schedule_id
            );
            echo "<p><strong>Records after manual insertion:</strong> $test_count</p>";

            // Clean up test record
            DB::query("DELETE FROM tbl_proposed_val_schedules WHERE schedule_id = %d AND equip_id = %d", $schedule_id, $test_equip_id);
            echo "<p><em>Test record cleaned up</em></p>";

        } else {
            echo "<p style='color: red;'>❌ No equipment found for unit $unit_id</p>";
        }

    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Manual insertion failed: " . $e->getMessage() . "</p>";
    }
}

// Step 6: Recommendations
echo "<h3>6. Recommendations</h3>";
echo "<div style='background: #f0f8ff; padding: 10px; border-left: 4px solid #007cba;'>";
echo "<p><strong>Possible causes and solutions:</strong></p>";
echo "<ol>";
echo "<li><strong>Stored procedure logic issue:</strong> The procedure may have internal conditions that prevent data insertion</li>";
echo "<li><strong>Missing prerequisites:</strong> Current year validations may need to be completed first</li>";
echo "<li><strong>Data validation failures:</strong> The procedure may be failing silently on data validation</li>";
echo "<li><strong>Permission issues:</strong> The procedure may lack permissions to insert data</li>";
echo "<li><strong>Missing required fields:</strong> New columns may be required but not handled by the procedure</li>";
echo "</ol>";

echo "<p><strong>Next steps:</strong></p>";
echo "<ul>";
echo "<li>Review the stored procedure code for conditional logic</li>";
echo "<li>Check database logs for any errors during procedure execution</li>";
echo "<li>Verify all required columns have default values or are properly populated</li>";
echo "<li>Consider using the dynamic scheduling procedure as an alternative</li>";
echo "</ul>";
echo "</div>";
?>