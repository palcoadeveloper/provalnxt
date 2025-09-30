<?php

session_start();

// Load configuration first
require_once(__DIR__ . '/../../config/config.php');

// Validate session timeout
require_once(__DIR__ . '/../../security/session_timeout_middleware.php');
validateActiveSession();
require_once __DIR__ . '/../../config/db.class.php';

date_default_timezone_set("Asia/Kolkata");

//Show All PHP Errors
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);



// Get employee status parameter
$employee_status = trim($_GET['employee_status'] ?? '');

// Build secure parameterized query with workflow columns
$base_query = "SELECT u.user_id, u.employee_id, u.user_type, u.vendor_id, u.user_name, u.user_email, u.user_status,
               u.submitted_by, u.checker_id, submitter.user_name as submitter_name, checker.user_name as checker_name
               FROM users u
               LEFT JOIN users submitter ON u.submitted_by = submitter.user_id
               LEFT JOIN users checker ON u.checker_id = checker.user_id";
$user_details = [];

// Build employee status condition
$status_condition = "";
$status_params = [];
if (!empty($employee_status)) {
    $status_condition = " AND u.user_status = %s";
    $status_params[] = $employee_status;
}

if ($_GET['usertype'] == 'IE') {
    // Employee search
    if (empty($_GET['searchinput'])) {
        // No search input - show all employees for unit
        if ($_GET['unitid'] != 'select') {
            $query = $base_query . " WHERE (u.unit_id = %i OR u.unit_id IS NULL) AND u.user_type = %s" . $status_condition;
            $params = array_merge([intval($_GET['unitid']), 'employee'], $status_params);
            $user_details = DB::query($query, ...$params);
        } else {
            $query = $base_query . " WHERE u.user_type = %s" . $status_condition;
            $params = array_merge(['employee'], $status_params);
            $user_details = DB::query($query, ...$params);
        }
    } else {
        // Search with input
        $search_input = '%' . $_GET['searchinput'] . '%';

        if ($_GET['searchcriteria'] == '0') {
            // Search by user name
            if ($_GET['unitid'] == 'select') {
                $query = $base_query . " WHERE u.user_name LIKE %s AND u.user_type = %s" . $status_condition;
                $params = array_merge([$search_input, 'employee'], $status_params);
                $user_details = DB::query($query, ...$params);
            } else {
                $query = $base_query . " WHERE u.user_name LIKE %s AND u.unit_id = %i AND u.user_type = %s" . $status_condition;
                $params = array_merge([$search_input, intval($_GET['unitid']), 'employee'], $status_params);
                $user_details = DB::query($query, ...$params);
            }
        } else {
            // Search by employee ID
            if ($_GET['unitid'] == 'select') {
                $query = $base_query . " WHERE u.employee_id LIKE %s AND u.user_type = %s" . $status_condition;
                $params = array_merge([$search_input, 'employee'], $status_params);
                $user_details = DB::query($query, ...$params);
            } else {
                $query = $base_query . " WHERE u.employee_id LIKE %s AND u.unit_id = %i AND u.user_type = %s" . $status_condition;
                $params = array_merge([$search_input, intval($_GET['unitid']), 'employee'], $status_params);
                $user_details = DB::query($query, ...$params);
            }
        }
    }
} else if ($_GET['usertype'] == 'VE') {
    // Vendor employee search
    if (empty($_GET['searchinput']) && $_GET['vendorid'] == 'select') {
        // No filters - show all vendor employees
        $query = $base_query . " WHERE u.user_type = %s" . $status_condition;
        $params = array_merge(['vendor'], $status_params);
        $user_details = DB::query($query, ...$params);
    } else {
        // Search with filters
        $search_input = empty($_GET['searchinput']) ? '' : '%' . $_GET['searchinput'] . '%';

        if ($_GET['searchcriteria'] == '0') {
            // Search by user name
            if ($_GET['vendorid'] == 'select') {
                if (!empty($search_input)) {
                    $query = $base_query . " WHERE u.user_name LIKE %s AND u.user_type = %s" . $status_condition;
                    $params = array_merge([$search_input, 'vendor'], $status_params);
                    $user_details = DB::query($query, ...$params);
                } else {
                    $query = $base_query . " WHERE u.user_type = %s" . $status_condition;
                    $params = array_merge(['vendor'], $status_params);
                    $user_details = DB::query($query, ...$params);
                }
            } else {
                if (!empty($search_input)) {
                    $query = $base_query . " WHERE u.user_name LIKE %s AND u.vendor_id = %i AND u.user_type = %s" . $status_condition;
                    $params = array_merge([$search_input, intval($_GET['vendorid']), 'vendor'], $status_params);
                    $user_details = DB::query($query, ...$params);
                } else {
                    $query = $base_query . " WHERE u.vendor_id = %i AND u.user_type = %s" . $status_condition;
                    $params = array_merge([intval($_GET['vendorid']), 'vendor'], $status_params);
                    $user_details = DB::query($query, ...$params);
                }
            }
        } else {
            // Search by employee ID
            if ($_GET['vendorid'] == 'select') {
                if (!empty($search_input)) {
                    $query = $base_query . " WHERE u.employee_id LIKE %s AND u.user_type = %s" . $status_condition;
                    $params = array_merge([$search_input, 'vendor'], $status_params);
                    $user_details = DB::query($query, ...$params);
                } else {
                    $query = $base_query . " WHERE u.user_type = %s" . $status_condition;
                    $params = array_merge(['vendor'], $status_params);
                    $user_details = DB::query($query, ...$params);
                }
            } else {
                if (!empty($search_input)) {
                    $query = $base_query . " WHERE u.employee_id LIKE %s AND u.vendor_id = %i AND u.user_type = %s" . $status_condition;
                    $params = array_merge([$search_input, intval($_GET['vendorid']), 'vendor'], $status_params);
                    $user_details = DB::query($query, ...$params);
                } else {
                    $query = $base_query . " WHERE u.vendor_id = %i AND u.user_type = %s" . $status_condition;
                    $params = array_merge([intval($_GET['vendorid']), 'vendor'], $status_params);
                    $user_details = DB::query($query, ...$params);
                }
            }
        }
    }
}


















echo "<table id='tbl-user-details' class='table table-bordered'>
                      <thead>
                        <tr>
                          <th> # </th>
                          <th> Employee ID </th>
                          <th> User name</th>
                          <th> Email</th>
                          <th> Status</th>
                          <th> Action</th>
                        </tr>
                      </thead>
                      <tbody>
                    ";


if(empty($user_details))
{
  //  echo "<tr><td colspan='6'>Nothing found.</td></tr>";
}
else
{
    $count=1;
    foreach ($user_details as $row) {
        // Add row class for pending records
        $row_class = ($row['user_status'] == 'Pending') ? "class='table-warning'" : "";

        echo "<tr $row_class>";
        echo "<td>".$count."</td>";
        echo "<td>".$row['employee_id']."</td>";

        echo "<td>".$row['user_name']."</td>";
        echo "<td>".$row['user_email']."</td>";

        // Status column with badges and workflow info
        echo "<td>";
        switch($row['user_status']) {
            case 'Active':
                echo "<span class='badge badge-success'>Active</span>";
                break;
            case 'Inactive':
                echo "<span class='badge badge-secondary'>Inactive</span>";
                break;
            case 'Pending':
                echo "<span class='badge badge-warning'>Pending</span>";
                if (!empty($row['submitter_name'])) {
                    echo "<br><small class='text-muted'>Submitted by: " . htmlspecialchars($row['submitter_name']) . "</small>";
                }
                break;
            default:
                echo "<span class='badge badge-light'>" . htmlspecialchars($row['user_status']) . "</span>";
        }
        echo "</td>";

        // Build search parameters for back navigation
        $search_params = http_build_query([
            'usertype' => $_GET['usertype'] ?? '',
            'unitid' => $_GET['unitid'] ?? '',
            'searchcriteria' => $_GET['searchcriteria'] ?? '',
            'searchinput' => $_GET['searchinput'] ?? '',
            'vendorid' => $_GET['vendorid'] ?? '',
            'employee_status' => $_GET['employee_status'] ?? '',
            'from_search' => '1'
        ]);

        echo "<td><a href='manageuserdetails.php?user_id=".$row["user_id"]."&m=m&u=".(($row['user_type']=='vendor')?"v":"c")."&".$search_params."' class='btn btn-sm btn-gradient-danger btn-icon-text' role='button' aria-pressed='true'>Manage</a> </td>";
        echo "</tr>";
        $count++;
    }
    echo "  </tbody></table>";
}




