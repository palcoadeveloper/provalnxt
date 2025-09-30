<?php

session_start();

// Load configuration first
require_once(__DIR__ . '/../../config/config.php');
// Validate session timeout
require_once(__DIR__ . '/../../security/session_timeout_middleware.php');
validateActiveSession();
require_once __DIR__ . '/../../config/db.class.php';

date_default_timezone_set("Asia/Kolkata");

$query = "SELECT room_loc_id, room_loc_name, room_volume, creation_datetime 
          FROM room_locations";

// Search filter by room name
if (isset($_GET['room_name']) && $_GET['room_name'] != 'Select' && !empty(trim($_GET['room_name']))) {
    $room_name = trim($_GET['room_name']);
    $query .= " WHERE room_loc_name LIKE '%" . DB::escapeString($room_name) . "%'";
}

// Order by creation date (newest first)
$query .= " ORDER BY creation_datetime DESC";

$room_details = DB::query($query);

echo "<table id='tbl-room-details' class='table table-bordered'>
                      <thead>
                        <tr>
                          <th> # </th>
                          <th> Room/Location Name</th>
                          <th> Volume (ft³)</th>
                          <th> Created Date</th>
                          <th> Action</th>
                        </tr>
                      </thead>
                      <tbody>
                    ";

if (empty($room_details)) {
    echo "<tr><td colspan='5'>No rooms found.</td></tr>";
    echo "</tbody></table>";
} else {
    $count = 1;
    foreach ($room_details as $row) {
        echo "<tr>";
        echo "<td>" . $count . "</td>";
        echo "<td>" . htmlspecialchars($row['room_loc_name'], ENT_QUOTES, 'UTF-8') . "</td>";
        echo "<td>" . number_format($row['room_volume'], 2) . "</td>";
        echo "<td>" . date('d.m.Y H:i', strtotime($row['creation_datetime'])) . "</td>";
        // Build search parameters for back navigation
        $search_params = http_build_query([
            'room_name' => $_GET['room_name'] ?? '',
            'from_search' => '1'
        ]);

        echo "<td>
                <a href='manageroomdetails.php?room_loc_id=" . $row["room_loc_id"] . "&m=r&" . $search_params . "' class='btn btn-sm btn-gradient-danger btn-icon-text' role='button' aria-pressed='true'>View</a>&nbsp;&nbsp;
                <a href='manageroomdetails.php?room_loc_id=" . $row["room_loc_id"] . "&m=m&" . $search_params . "' class='btn btn-sm btn-gradient-info btn-icon-text' role='button' aria-pressed='true'>Edit</a>
              </td>";
        echo "</tr>";
        $count++;
    }
    echo "</tbody></table>";
}

?>