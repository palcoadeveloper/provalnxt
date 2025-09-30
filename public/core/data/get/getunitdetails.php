<?php

session_start();

// Load configuration first
require_once(__DIR__ . '/../../config/config.php');
// Validate session timeout
require_once(__DIR__ . '/../../security/session_timeout_middleware.php');
validateActiveSession();
require_once __DIR__ . '/../../config/db.class.php';

// Check for superadmin role
if (!isset($_SESSION['is_super_admin']) || $_SESSION['is_super_admin'] !== 'Yes') {
    http_response_code(403);
    echo '<tr><td colspan="7">Access Denied: Superadmin access required.</td></tr>';
    exit();
}

date_default_timezone_set("Asia/Kolkata");

if($_GET['unit_status']!='Select')
{
    $unit_details = DB::query("SELECT u.unit_id, u.unit_name, u.unit_status, u.primary_test_id, u.secondary_test_id, 
                               u.two_factor_enabled, u.otp_validity_minutes, u.otp_digits, u.otp_resend_delay_seconds,
                               pt.test_name as primary_test_name, pt.test_description as primary_test_description,
                               st.test_name as secondary_test_name, st.test_description as secondary_test_description
                        FROM units u
                        LEFT JOIN tests pt ON u.primary_test_id = pt.test_id
                        LEFT JOIN tests st ON u.secondary_test_id = st.test_id
                        WHERE u.unit_status = %s", $_GET['unit_status']);
}
else
{
    $unit_details = DB::query("SELECT u.unit_id, u.unit_name, u.unit_status, u.primary_test_id, u.secondary_test_id, 
                               u.two_factor_enabled, u.otp_validity_minutes, u.otp_digits, u.otp_resend_delay_seconds,
                               pt.test_name as primary_test_name, pt.test_description as primary_test_description,
                               st.test_name as secondary_test_name, st.test_description as secondary_test_description
                        FROM units u
                        LEFT JOIN tests pt ON u.primary_test_id = pt.test_id
                        LEFT JOIN tests st ON u.secondary_test_id = st.test_id");
}

echo "<table id='tbl-unit-details' class='table table-bordered'>
                      <thead>
                        <tr>
                          <th> # </th>
                          <th> Unit Name</th>
                          <th> Unit Status</th>
                          <th> Primary Test</th>
                          <th> Secondary Test</th>
                          <th> 2FA Enabled</th>
                          <th> Action</th>
                        </tr>
                      </thead>
                      <tbody>
                    ";

if(empty($unit_details))
{
    echo "<tr><td colspan='7'>Nothing found.</td></tr>";
}
else 
{
    $count=1;
    foreach ($unit_details as $row) {
        echo "<tr>";
        echo "<td>".$count."</td>";
        echo "<td>".htmlspecialchars($row['unit_name'], ENT_QUOTES, 'UTF-8')."</td>";
        echo "<td>".htmlspecialchars($row['unit_status'], ENT_QUOTES, 'UTF-8')."</td>";
        
        // Primary Test
        $primary_test = $row['primary_test_name'] ? 
                       htmlspecialchars($row['primary_test_name'], ENT_QUOTES, 'UTF-8') . ' - ' . 
                       htmlspecialchars($row['primary_test_description'], ENT_QUOTES, 'UTF-8') : 
                       'Not Assigned';
        echo "<td>".$primary_test."</td>";
        
        // Secondary Test
        $secondary_test = $row['secondary_test_name'] ? 
                         htmlspecialchars($row['secondary_test_name'], ENT_QUOTES, 'UTF-8') . ' - ' . 
                         htmlspecialchars($row['secondary_test_description'], ENT_QUOTES, 'UTF-8') : 
                         'Not Assigned';
        echo "<td>".$secondary_test."</td>";
        
        echo "<td>".htmlspecialchars($row['two_factor_enabled'], ENT_QUOTES, 'UTF-8')."</td>";
       
        // Build search parameters for back navigation
        $search_params = http_build_query([
            'unit_status' => $_GET['unit_status'] ?? '',
            'from_search' => '1'
        ]);

        echo "<td><a href='manageunitdetails.php?unit_id=".$row["unit_id"]."&m=r&".$search_params."' class='btn btn-sm btn-gradient-danger btn-icon-text' role='button' aria-pressed='true'>View</a>&nbsp;&nbsp;
<a href='manageunitdetails.php?unit_id=".$row["unit_id"]."&m=m&".$search_params."' class='btn btn-sm btn-gradient-info btn-icon-text' role='button' aria-pressed='true'>Edit</a> </td>";
        echo "</tr>";
        $count++;
    }
    echo "  </tbody></table>";
}

?>