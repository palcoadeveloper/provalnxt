<?php

session_start();

// Load configuration first
require_once(__DIR__ . '/../../config/config.php');
// Validate session timeout
require_once(__DIR__ . '/../../security/session_timeout_middleware.php');
validateActiveSession();
require_once __DIR__ . '/../../config/db.class.php';

date_default_timezone_set("Asia/Kolkata");

// Build the query for Filter Groups
$query = "SELECT 
    filter_group_id,
    filter_group_name,
    status
FROM filter_groups
WHERE 1=1";

// Filter by status if provided
if (isset($_GET['status']) && $_GET['status'] != '' && !empty($_GET['status'])) {
    $status = $_GET['status'];
    $query .= " AND status = '" . DB::escapeString($status) . "'";
}

// Order by filter group name (alphabetical)
$query .= " ORDER BY filter_group_name ASC";

$filtergroup_details = DB::query($query);

echo "<table id='tbl-filtergroup-details' class='table table-bordered'>
                      <thead>
                        <tr>
                          <th> # </th>
                          <th> Filter Group Name </th>
                          <th> Status </th>
                          <th> Action </th>
                        </tr>
                      </thead>
                      <tbody>
                    ";

if (empty($filtergroup_details)) {
    echo "<tr><td colspan='4'>No filter groups found.</td></tr>";
    echo "</tbody></table>";
} else {
    $count = 1;
    foreach ($filtergroup_details as $row) {
        echo "<tr>";
        echo "<td>" . $count . "</td>";
        echo "<td>" . htmlspecialchars($row['filter_group_name'], ENT_QUOTES, 'UTF-8') . "</td>";
        echo "<td>";
        if ($row['status'] == 'Active') {
            echo "<span class='badge badge-success'>" . htmlspecialchars($row['status'], ENT_QUOTES, 'UTF-8') . "</span>";
        } else {
            echo "<span class='badge badge-danger'>" . htmlspecialchars($row['status'], ENT_QUOTES, 'UTF-8') . "</span>";
        }
        echo "</td>";
        // Build search parameters for back navigation
        $search_params = http_build_query([
            'status' => $_GET['status'] ?? '',
            'from_search' => '1'
        ]);

        echo "<td>
                <a href='managefiltergroups.php?filter_group_id=" . $row["filter_group_id"] . "&m=r&" . $search_params . "' class='btn btn-sm btn-gradient-danger btn-icon-text' role='button' aria-pressed='true'>View</a>&nbsp;&nbsp;
                <a href='managefiltergroups.php?filter_group_id=" . $row["filter_group_id"] . "&m=m&" . $search_params . "' class='btn btn-sm btn-gradient-info btn-icon-text' role='button' aria-pressed='true'>Edit</a>
              </td>";
        echo "</tr>";
        $count++;
    }
    echo "</tbody></table>";
}

?>