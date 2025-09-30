<?php

session_start();

// Load configuration first
require_once(__DIR__ . '/../../config/config.php');
// Validate session timeout
require_once(__DIR__ . '/../../security/session_timeout_middleware.php');
validateActiveSession();
require_once __DIR__ . '/../../config/db.class.php';

date_default_timezone_set("Asia/Kolkata");

// Build the query for ERF mappings
$query = "SELECT 
    em.erf_mapping_id,
    em.equipment_id,
    e.equipment_code,
    em.room_loc_id,
    rl.room_loc_name,
    em.filter_name,
    fg.filter_group_name,
    em.area_classification,
    em.erf_mapping_status,
    em.creation_datetime
FROM erf_mappings em
INNER JOIN equipments e ON em.equipment_id = e.equipment_id  
INNER JOIN room_locations rl ON em.room_loc_id = rl.room_loc_id
LEFT JOIN filter_groups fg ON em.filter_group_id = fg.filter_group_id
WHERE 1=1";

// Filter by unit if provided (through equipment's unit_id)
if (isset($_GET['unitid']) && $_GET['unitid'] != 'Select' && !empty($_GET['unitid'])) {
    $unit_id = intval($_GET['unitid']);
    $query .= " AND e.unit_id = " . $unit_id;
}

// Filter by equipment if provided
if (isset($_GET['equipment_id']) && $_GET['equipment_id'] != 'Select' && !empty($_GET['equipment_id'])) {
    $equipment_id = intval($_GET['equipment_id']);
    $query .= " AND em.equipment_id = " . $equipment_id;
}

// Filter by room if provided
if (isset($_GET['room_loc_id']) && $_GET['room_loc_id'] != 'Select' && !empty($_GET['room_loc_id'])) {
    $room_loc_id = intval($_GET['room_loc_id']);
    $query .= " AND em.room_loc_id = " . $room_loc_id;
}

// Filter by status if provided
if (isset($_GET['mapping_status']) && $_GET['mapping_status'] != 'Select' && !empty($_GET['mapping_status'])) {
    $status = ($_GET['mapping_status'] == "0") ? "Active" : "Inactive";
    $query .= " AND em.erf_mapping_status = '" . DB::escapeString($status) . "'";
}

// Order by creation date (newest first)
$query .= " ORDER BY em.creation_datetime DESC";

$mapping_details = DB::query($query);

echo "<table id='tbl-erf-mapping-details' class='table table-bordered'>
                      <thead>
                        <tr>
                          <th> # </th>
                          <th> Equipment Code </th>
                          <th> Room/Location </th>
                          <th> Filter Name </th>
                          <th> Filter Group </th>
                          <th> Area Classification </th>
                          <th> Status </th>
                          <th> Action </th>
                        </tr>
                      </thead>
                      <tbody>
                    ";

if (empty($mapping_details)) {
    echo "<tr><td colspan='8'>No ERF mappings found.</td></tr>";
    echo "</tbody></table>";
} else {
    $count = 1;
    foreach ($mapping_details as $row) {
        echo "<tr>";
        echo "<td>" . $count . "</td>";
        echo "<td>" . htmlspecialchars($row['equipment_code'], ENT_QUOTES, 'UTF-8') . "</td>";
        echo "<td>" . htmlspecialchars($row['room_loc_name'], ENT_QUOTES, 'UTF-8') . "</td>";
        echo "<td>" . htmlspecialchars($row['filter_name'] ?? 'No Filter', ENT_QUOTES, 'UTF-8') . "</td>";
        echo "<td>" . htmlspecialchars($row['filter_group_name'] ?? 'No Group', ENT_QUOTES, 'UTF-8') . "</td>";
        echo "<td>" . htmlspecialchars($row['area_classification'], ENT_QUOTES, 'UTF-8') . "</td>";
        echo "<td>" . htmlspecialchars($row['erf_mapping_status'], ENT_QUOTES, 'UTF-8') . "</td>";
        // Build search parameters for back navigation
        $search_params = http_build_query([
            'unitid' => $_GET['unitid'] ?? '',
            'equipment_id' => $_GET['equipment_id'] ?? '',
            'room_loc_id' => $_GET['room_loc_id'] ?? '',
            'mapping_status' => $_GET['mapping_status'] ?? '',
            'from_search' => '1'
        ]);

        echo "<td>
                <a href='manageerfmappingdetails.php?erf_mapping_id=" . $row["erf_mapping_id"] . "&m=r&" . $search_params . "' class='btn btn-xs btn-gradient-danger' role='button' aria-pressed='true'>View</a>&nbsp;
                <a href='manageerfmappingdetails.php?erf_mapping_id=" . $row["erf_mapping_id"] . "&m=m&" . $search_params . "' class='btn btn-xs btn-gradient-info' role='button' aria-pressed='true'>Edit</a>
              </td>";
        echo "</tr>";
        $count++;
    }
    echo "</tbody></table>";
}

?>