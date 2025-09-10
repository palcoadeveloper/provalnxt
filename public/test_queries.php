<?php
require_once('./core/config/config.php');
require_once('core/security/session_timeout_middleware.php');
validateActiveSession();
require_once('core/security/session_validation.php');
validateUserSession();
require_once("core/config/db.class.php");

$test_wf_id = 'T-1-7-1-1756793223';

echo "<h1>Database Query Test for $test_wf_id</h1>";

try {
    echo "<h2>1. Test Info Query</h2>";
    $test_info = DB::queryFirstRow("
        SELECT 
            ts.test_wf_id,
            ts.val_wf_id,
            ts.equip_id,
            ts.test_id,
            ts.test_wf_planned_start_date,
            ts.test_conducted_date,
            t.test_name,
            t.test_purpose,
            t.test_description,
            e.equipment_code,
            e.area_served,
            e.section,
            u.unit_name
        FROM tbl_test_schedules_tracking ts
        JOIN tests t ON ts.test_id = t.test_id
        JOIN equipments e ON ts.equip_id = e.equipment_id
        LEFT JOIN units u ON ts.unit_id = u.unit_id
        WHERE ts.test_wf_id = %s
    ", $test_wf_id);
    
    if ($test_info) {
        echo "✅ Test info found: " . $test_info['equipment_code'] . "<br>";
        echo "Equipment ID: " . $test_info['equip_id'] . "<br>";
    } else {
        echo "❌ No test info found<br>";
    }
    
    echo "<h2>2. Room Info Query</h2>";
    $room_info = DB::queryFirstRow("
        SELECT DISTINCT
            rl.room_loc_name,
            em.area_classification
        FROM erf_mappings em
        JOIN room_locations rl ON em.room_loc_id = rl.room_loc_id
        WHERE em.equipment_id = %i
        AND em.erf_mapping_status = 'Active'
        LIMIT 1
    ", $test_info['equip_id']);
    
    if ($room_info) {
        echo "✅ Room info found: " . $room_info['room_loc_name'] . "<br>";
    } else {
        echo "❌ No room info found<br>";
    }
    
    echo "<h2>3. Instrument Info Query</h2>";
    $instrument_info = DB::queryFirstRow("
        SELECT 
            i.instrument_type,
            i.instrument_id,
            i.serial_number,
            i.calibrated_on,
            i.calibration_due_on,
            i.instrument_status
        FROM test_instruments ti
        JOIN instruments i ON ti.instrument_id = i.instrument_id
        WHERE ti.test_val_wf_id = %s
        AND ti.is_active = '1'
        LIMIT 1
    ", $test_wf_id);
    
    if ($instrument_info) {
        echo "✅ Instrument info found: " . $instrument_info['instrument_id'] . "<br>";
    } else {
        echo "❌ No instrument info found<br>";
    }
    
    echo "<h2>4. Filter Groups Query</h2>";
    $filter_groups = DB::query("
        SELECT DISTINCT
            fg.filter_group_name,
            em.filter_id
        FROM erf_mappings em
        JOIN filter_groups fg ON em.filter_group_id = fg.filter_group_id
        WHERE em.equipment_id = %i
        AND em.erf_mapping_status = 'Active'
        AND fg.status = 'Active'
    ", $test_info['equip_id']);
    
    echo "✅ Found " . count($filter_groups) . " filter groups<br>";
    foreach ($filter_groups as $group) {
        echo "- " . $group['filter_group_name'] . " (Filter ID: " . $group['filter_id'] . ")<br>";
    }
    
    echo "<h2>5. ACPH Filter Data Query</h2>";
    $user_unit_id = getUserUnitId();
    
    if (isVendor()) {
        $filter_data_records = DB::query("
            SELECT tsd.data_json, tsd.section_type, tsd.entered_date, tsd.modified_date, tsd.filter_id,
                   u1.user_name as entered_by_name, u2.user_name as modified_by_name
            FROM test_specific_data tsd
            LEFT JOIN users u1 ON tsd.entered_by = u1.user_id
            LEFT JOIN users u2 ON tsd.modified_by = u2.user_id
            WHERE tsd.test_val_wf_id = %s 
            AND tsd.section_type LIKE 'acph_filter_%'
            AND tsd.status = 'Active'
            ORDER BY tsd.section_type
        ", $test_wf_id);
    } else {
        $filter_data_records = DB::query("
            SELECT tsd.data_json, tsd.section_type, tsd.entered_date, tsd.modified_date, tsd.filter_id,
                   u1.user_name as entered_by_name, u2.user_name as modified_by_name
            FROM test_specific_data tsd
            LEFT JOIN users u1 ON tsd.entered_by = u1.user_id
            LEFT JOIN users u2 ON tsd.modified_by = u2.user_id
            WHERE tsd.test_val_wf_id = %s 
            AND tsd.section_type LIKE 'acph_filter_%'
            AND tsd.status = 'Active'
            AND tsd.unit_id = %i
            ORDER BY tsd.section_type
        ", $test_wf_id, intval($user_unit_id));
    }
    
    echo "✅ Found " . count($filter_data_records) . " ACPH filter records<br>";
    foreach ($filter_data_records as $record) {
        echo "- " . $record['section_type'] . "<br>";
    }
    
    echo "<h2>All Queries Successful! ✅</h2>";
    
} catch (Exception $e) {
    echo "<h2>❌ Error: " . $e->getMessage() . "</h2>";
}
?>