<?php

// Load configuration first - session is already started by config.php via session_init.php
require_once(__DIR__ . '/../../config/config.php');

// Validate session timeout
require_once(__DIR__ . '/../../security/session_timeout_middleware.php');
validateActiveSession();
include_once(__DIR__ . "/../../config/db.class.php");
date_default_timezone_set("Asia/Kolkata");

// Validate and sanitize input parameters
if (!isset($_GET['unit_id']) || !isset($_GET['vendor_id']) || !isset($_GET['report_year'])) {
    echo "<div class='alert alert-danger'>Missing required parameters</div>";
    exit();
}

$unit_id = $_GET['unit_id'];
$vendor_id = $_GET['vendor_id'];
$report_year = intval($_GET['report_year']);

// Validate year format
if (!is_numeric($report_year) || $report_year < 2000 || $report_year > 2050) {
    echo "<div class='alert alert-danger'>Invalid year format</div>";
    exit();
}

// Validate unit_id
if ($unit_id !== 'ALL' && (!is_numeric($unit_id) || intval($unit_id) <= 0)) {
    echo "<div class='alert alert-danger'>Invalid unit selection</div>";
    exit();
}

// Validate vendor_id
if ($vendor_id !== 'ALL' && (!is_numeric($vendor_id) || intval($vendor_id) <= 0)) {
    echo "<div class='alert alert-danger'>Invalid vendor selection</div>";
    exit();
}

try {
    // Build the base query with unit and vendor filtering
    $base_query = "
        SELECT DISTINCT
            v.vendor_id,
            v.vendor_name,
            COUNT(DISTINCT vs.val_wf_id) as total_allocations
        FROM tbl_val_schedules vs
        JOIN equipment_test_vendor_mapping etvm ON vs.equip_id = etvm.equipment_id
        JOIN tests t ON etvm.test_id = t.test_id
        JOIN vendors v ON etvm.vendor_id = v.vendor_id
        WHERE vs.val_wf_status = 'Active'
            AND etvm.vendor_id > 0
            AND YEAR(vs.val_wf_planned_start_date) = %i
            AND v.vendor_status = 'Active'
            AND etvm.mapping_status = 'Active'";

    // Add unit filter if specific unit is selected
    if ($unit_id !== 'ALL') {
        $base_query .= " AND vs.unit_id = %i";
    }

    // Add vendor filter if specific vendor is selected
    if ($vendor_id !== 'ALL') {
        $base_query .= " AND etvm.vendor_id = %i";
    }

    $base_query .= "
        GROUP BY v.vendor_id, v.vendor_name
        ORDER BY v.vendor_name";

    // Execute query with appropriate parameters
    if ($unit_id !== 'ALL' && $vendor_id !== 'ALL') {
        $results = DB::query($base_query, $report_year, intval($unit_id), intval($vendor_id));
    } elseif ($unit_id !== 'ALL') {
        $results = DB::query($base_query, $report_year, intval($unit_id));
    } elseif ($vendor_id !== 'ALL') {
        $results = DB::query($base_query, $report_year, intval($vendor_id));
    } else {
        $results = DB::query($base_query, $report_year);
    }

    echo "<table id='datagrid-report' class='table table-bordered'>
    <thead>
    <tr>
    <th>#</th>
    <th>Vendor Name</th>
    <th>Total Allocations</th>
    <th>Action</th>
    </tr>
    </thead><tbody>";

    if(empty($results)) {
        echo "<tr><td colspan='4' class='text-center'>No external test allocations found for the selected criteria.</td></tr>";
    } else {
        $count = 1;
        foreach ($results as $row) {
            echo "<tr>";
            echo "<td>".$count."</td>";
            echo "<td>".htmlspecialchars($row["vendor_name"], ENT_QUOTES, 'UTF-8')."</td>";
            echo "<td>".$row["total_allocations"]."</td>";
            echo "<td><button onclick='generatePDF(\"$unit_id\", ".$row["vendor_id"].", ".$report_year.", \"".addslashes($row["vendor_name"])."\")' class='btn btn-sm btn-gradient-info btn-icon-text'>View PDF</button></td>";
            echo "</tr>";
            $count++;
        }
    }

    echo "</tbody></table>";

} catch (Exception $e) {
    error_log("Error in get_external_test_allocation.php: " . $e->getMessage());
    echo "<div class='alert alert-danger'>An error occurred while fetching data. Please try again.</div>";
}
?>