<?php

session_start();

// Load configuration first
require_once(__DIR__ . '/../../config/config.php');
// Validate session timeout
require_once(__DIR__ . '/../../security/session_timeout_middleware.php');
validateActiveSession();
require_once __DIR__ . '/../../config/db.class.php';

date_default_timezone_set("Asia/Kolkata");

// Build query using the same approach as the working saveunitdetails.php file
$base_query = "SELECT equipment_id, equipment_code, t1.unit_id, t2.department_name, equipment_category 
               FROM equipments t1 
               INNER JOIN departments t2 ON t1.department_id = t2.department_id 
               WHERE t1.unit_id = %i";

$unit_id = intval($_GET['unitid']);

// Check for ETV mapping filter
$etv_mapping_filter = isset($_GET['etv_mapping_filter']) ? $_GET['etv_mapping_filter'] : 'Select';

// Add ETV mapping condition to base query if needed
if ($etv_mapping_filter === 'Yes') {
    // Show only equipment without ETV mappings
    $base_query = "SELECT equipment_id, equipment_code, t1.unit_id, t2.department_name, equipment_category 
                   FROM equipments t1 
                   INNER JOIN departments t2 ON t1.department_id = t2.department_id 
                   WHERE t1.unit_id = %i 
                   AND t1.equipment_id NOT IN (
                       SELECT DISTINCT equipment_id 
                       FROM equipment_test_vendor_mapping 
                       WHERE mapping_status = 'Active' 
                       AND equipment_id IS NOT NULL
                   )";
} elseif ($etv_mapping_filter === 'No') {
    // Show only equipment with ETV mappings
    $base_query = "SELECT equipment_id, equipment_code, t1.unit_id, t2.department_name, equipment_category 
                   FROM equipments t1 
                   INNER JOIN departments t2 ON t1.department_id = t2.department_id 
                   WHERE t1.unit_id = %i 
                   AND t1.equipment_id IN (
                       SELECT DISTINCT equipment_id 
                       FROM equipment_test_vendor_mapping 
                       WHERE mapping_status = 'Active' 
                       AND equipment_id IS NOT NULL
                   )";
}

// Start building the query conditionally
if ($_GET['dept_id'] != 'Select' && $_GET['equipment_type'] != 'Select' && !empty($_GET['equipment_id']) && $_GET['equipment_id'] != 'All') {
    // All filters selected
    $dept_id = intval($_GET['dept_id']);
    $equipment_id = intval($_GET['equipment_id']);
    $equipment_category = ($_GET['equipment_type'] == '0') ? 'AHU' : 'VU';
    
    $user_details = DB::query($base_query . " AND t2.department_id = %i AND equipment_category = %s AND equipment_id = %i", 
                              $unit_id, $dept_id, $equipment_category, $equipment_id);
    
} else if ($_GET['dept_id'] != 'Select' && $_GET['equipment_type'] != 'Select') {
    // Department and Equipment Type selected
    $dept_id = intval($_GET['dept_id']);
    $equipment_category = ($_GET['equipment_type'] == '0') ? 'AHU' : 'VU';
    
    $user_details = DB::query($base_query . " AND t2.department_id = %i AND equipment_category = %s", 
                              $unit_id, $dept_id, $equipment_category);
    
} else if ($_GET['dept_id'] != 'Select' && !empty($_GET['equipment_id']) && $_GET['equipment_id'] != 'All') {
    // Department and Equipment ID selected
    $dept_id = intval($_GET['dept_id']);
    $equipment_id = intval($_GET['equipment_id']);
    
    $user_details = DB::query($base_query . " AND t2.department_id = %i AND equipment_id = %i", 
                              $unit_id, $dept_id, $equipment_id);
    
} else if ($_GET['equipment_type'] != 'Select' && !empty($_GET['equipment_id']) && $_GET['equipment_id'] != 'All') {
    // Equipment Type and Equipment ID selected
    $equipment_id = intval($_GET['equipment_id']);
    $equipment_category = ($_GET['equipment_type'] == '0') ? 'AHU' : 'VU';
    
    $user_details = DB::query($base_query . " AND equipment_category = %s AND equipment_id = %i", 
                              $unit_id, $equipment_category, $equipment_id);
    
} else if ($_GET['dept_id'] != 'Select') {
    // Only Department selected
    $dept_id = intval($_GET['dept_id']);
    
    $user_details = DB::query($base_query . " AND t2.department_id = %i", $unit_id, $dept_id);
    
} else if ($_GET['equipment_type'] != 'Select') {
    // Only Equipment Type selected
    $equipment_category = ($_GET['equipment_type'] == '0') ? 'AHU' : 'VU';
    
    $user_details = DB::query($base_query . " AND equipment_category = %s", $unit_id, $equipment_category);
    
} else if (!empty($_GET['equipment_id']) && $_GET['equipment_id'] != 'All') {
    // Only Equipment ID selected
    $equipment_id = intval($_GET['equipment_id']);
    
    $user_details = DB::query($base_query . " AND equipment_id = %i", $unit_id, $equipment_id);
    
} else {
    // No additional filters - show all equipment for the unit (when "All" is selected)
    $user_details = DB::query($base_query, $unit_id);
}



echo "<table id='tbl-equip-details' class='table table-bordered'>
                      <thead>
                        <tr>
                          <th> # </th>
                          <th> Equipment code </th>
                          <th> Category </th>
                          <th> Unit</th>
                          <th> Department</th>
                          <th> Action</th>
                        </tr>
                      </thead>
                      <tbody>
                    ";


if(empty($user_details))
{
    echo "<tr><td colspan='6'>Nothing found.</td></tr>";
}
else 
{
    $count=1;
    foreach ($user_details as $row) {
        echo "<tr>";
        echo "<td>".$count."</td>";
        echo "<td>".$row['equipment_code']."</td>";
        echo "<td>".$row['equipment_category']."</td>";
        echo "<td>".$row['unit_id']."</td>";
        echo "<td>".$row['department_name']."</td>";

        echo "<td><a href='manageequipmentdetails.php?equip_id=".$row["equipment_id"]."&m=r' class='btn btn-sm btn-gradient-danger btn-icon-text' role='button' aria-pressed='true'>View</a>&nbsp;&nbsp;<a href='manageequipmentdetails.php?equip_id=".$row["equipment_id"]."&m=m' class='btn btn-sm btn-gradient-info btn-icon-text' role='button' aria-pressed='true'>Modify</a> </td>";
        echo "</tr>";
        $count++;
    }
    echo "  </tbody></table>";
}




