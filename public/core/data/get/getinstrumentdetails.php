<?php 
session_start();

// Load configuration first
require_once(__DIR__ . '/../../config/config.php');

// Validate session timeout
require_once(__DIR__ . '/../../security/session_timeout_middleware.php');

// Include XSS protection middleware to get safe_get function
require_once(__DIR__ . '/../../security/xss_integration_middleware.php');

// Only validate session if we're in a web request
if (!empty($_SERVER['REQUEST_METHOD'])) {
    validateActiveSession();
}

require_once __DIR__ . '/../../config/db.class.php';
date_default_timezone_set("Asia/Kolkata");

// Basic validation is sufficient for search operations
// Complex validation utilities not needed here

try {
    // Get and validate input parameters
    $search_criteria = trim($_GET['search_criteria'] ?? '');
    $search_input = trim($_GET['search_input'] ?? '');
    $vendor_id = trim($_GET['vendor_id'] ?? '');
    $instrument_type = trim($_GET['instrument_type'] ?? '');
    $calibration_status = trim($_GET['calibration_status'] ?? '');
    $instrument_status = trim($_GET['instrument_status'] ?? '');
    
    // Build the WHERE clause
    $where_conditions = [];
    $params = [];
    $param_types = [];

    // SECURITY: Mandatory vendor filtering for vendor users
    if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'vendor' &&
        isset($_SESSION['vendor_id']) && $_SESSION['vendor_id'] > 0) {

        // Force vendor filtering regardless of search criteria for data isolation
        $where_conditions[] = "i.vendor_id = %i";
        $params[] = intval($_SESSION['vendor_id']);
        $param_types[] = 'i';

        error_log("DEBUG: Applying mandatory vendor filter for vendor_id: " . $_SESSION['vendor_id']);
    }

    // Handle search criteria
    if (!empty($search_criteria) && !empty($search_input)) {
        if ($search_criteria === 'Instrument ID') {
            $where_conditions[] = "i.instrument_id LIKE %s";
            $params[] = '%' . $search_input . '%';
            $param_types[] = 's';
        } elseif ($search_criteria === 'Serial Number') {
            $where_conditions[] = "i.serial_number LIKE %s";
            $params[] = '%' . $search_input . '%';
            $param_types[] = 's';
        }
    }

    // Handle vendor filter (for admin users only, since vendor users are already filtered above)
    if (!empty($vendor_id) && is_numeric($vendor_id) &&
        (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'vendor')) {
        $where_conditions[] = "i.vendor_id = %i";
        $params[] = intval($vendor_id);
        $param_types[] = 'i';
    }
    
    // Handle instrument type filter
    if (!empty($instrument_type)) {
        $where_conditions[] = "i.instrument_type = %s";
        $params[] = $instrument_type;
        $param_types[] = 's';
    }
    
    // Handle calibration status filter (calculated based on due date)
    if (!empty($calibration_status)) {
        if ($calibration_status === 'Expired') {
            $where_conditions[] = "i.calibration_due_on < CURDATE()";
        } elseif ($calibration_status === 'Due Soon') {
            $where_conditions[] = "i.calibration_due_on BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
        } elseif ($calibration_status === 'Valid') {
            $where_conditions[] = "i.calibration_due_on > DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
        }
    }

    // Handle instrument status filter (Active, Inactive, Pending)
    if (!empty($instrument_status)) {
        $where_conditions[] = "i.instrument_status = %s";
        $params[] = $instrument_status;
        $param_types[] = 's';
    }
    
    // Build the complete query
    $sql = "SELECT
                i.instrument_id,
                i.serial_number,
                i.instrument_type,
                v.vendor_name,
                i.calibrated_on as last_calibration_date,
                i.calibration_due_on as next_calibration_date,
                i.instrument_status,
                i.submitted_by,
                i.checker_id,
                u_submitter.user_name as submitter_name,
                u_checker.user_name as checker_name,
                DATEDIFF(i.calibration_due_on, CURDATE()) as days_until_calibration,
                CASE
                    WHEN i.calibration_due_on < CURDATE() THEN 'Expired'
                    WHEN i.calibration_due_on BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'Due Soon'
                    WHEN i.calibration_due_on > DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'Valid'
                    ELSE 'Not Set'
                END as calibration_status
            FROM instruments i
            LEFT JOIN vendors v ON i.vendor_id = v.vendor_id
            LEFT JOIN users u_submitter ON i.submitted_by = u_submitter.user_id
            LEFT JOIN users u_checker ON i.checker_id = u_checker.user_id";
    
    // Add WHERE clause if there are conditions
    if (!empty($where_conditions)) {
        $sql .= " WHERE " . implode(" AND ", $where_conditions);
    }
    
    $sql .= " ORDER BY i.instrument_id ASC";
    
    // Execute the query with parameters
    if (!empty($params)) {
        $instruments = DB::query($sql, ...$params);
    } else {
        $instruments = DB::query($sql);
    }
    
    // Generate the HTML table
    if (!empty($instruments)) {
        echo '<table id="tbl-instrument-details" class="table table-striped table-bordered">
                <thead>
                    <tr>
                        <th>Instrument ID</th>
                        <th>Serial number</th>
                        <th>Type</th>
                        <th>Vendor</th>
                        <th>Last calibration</th>
                        <th>Next calibration</th>
                        <th>Calibration Status</th>
                        <th>Days until</th>
                        <th>Instrument Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>';
        
        foreach ($instruments as $instrument) {
            // Format dates
            $last_cal_date = !empty($instrument['last_calibration_date']) ? 
                date('d-M-Y', strtotime($instrument['last_calibration_date'])) : 'Not Set';
            $next_cal_date = !empty($instrument['next_calibration_date']) ? 
                date('d-M-Y', strtotime($instrument['next_calibration_date'])) : 'Not Set';
            
            // Determine status badge color
            $status_class = 'badge-secondary';
            switch ($instrument['calibration_status']) {
                case 'Valid':
                    $status_class = 'badge-success';
                    break;
                case 'Due Soon':
                    $status_class = 'badge-warning';
                    break;
                case 'Expired':
                    $status_class = 'badge-danger';
                    break;
            }
            
            // Days until calibration display
            $days_display = '';
            if (!empty($instrument['next_calibration_date'])) {
                $days = intval($instrument['days_until_calibration']);
                if ($days < 0) {
                    $days_display = '<span class="text-danger font-weight-bold">' . abs($days) . ' days overdue</span>';
                } elseif ($days <= 30) {
                    $days_display = '<span class="text-warning font-weight-bold">' . $days . ' days</span>';
                } else {
                    $days_display = '<span class="text-success">' . $days . ' days</span>';
                }
            } else {
                $days_display = '<span class="text-muted">N/A</span>';
            }
            
            // Determine instrument status badge color and icon
            $instrument_status_class = 'badge-secondary';
            $instrument_status_icon = '';
            $is_submitter = (isset($_SESSION['user_id']) && $instrument['submitted_by'] == $_SESSION['user_id']);
            $pending_indicator = '';

            switch ($instrument['instrument_status']) {
                case 'Active':
                    $instrument_status_class = 'badge-success';
                    $instrument_status_icon = '<i class="mdi mdi-check-circle"></i> ';
                    break;
                case 'Inactive':
                    $instrument_status_class = 'badge-secondary';
                    $instrument_status_icon = '<i class="mdi mdi-close-circle"></i> ';
                    break;
                case 'Pending':
                    $instrument_status_class = 'badge-warning';
                    $instrument_status_icon = '<i class="mdi mdi-clock-outline"></i> ';
                    if ($is_submitter) {
                        $pending_indicator = ' <small class="text-muted">(Your submission)</small>';
                    }
                    break;
            }

            echo '<tr>
                    <td>' . htmlspecialchars($instrument['instrument_id'], ENT_QUOTES, 'UTF-8') . '</td>
                    <td>' . htmlspecialchars($instrument['serial_number'], ENT_QUOTES, 'UTF-8') . '</td>
                    <td>' . htmlspecialchars($instrument['instrument_type'], ENT_QUOTES, 'UTF-8') . '</td>
                    <td>' . htmlspecialchars($instrument['vendor_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8') . '</td>
                    <td>' . $last_cal_date . '</td>
                    <td>' . $next_cal_date . '</td>
                    <td><span class="badge ' . $status_class . '">' . htmlspecialchars($instrument['calibration_status'], ENT_QUOTES, 'UTF-8') . '</span></td>
                    <td>' . $days_display . '</td>
                    <td><span class="badge ' . $instrument_status_class . '">' . $instrument_status_icon . htmlspecialchars($instrument['instrument_status'], ENT_QUOTES, 'UTF-8') . '</span>' . $pending_indicator . '</td>
                    <td>';

        // Build search parameters for back navigation
        $search_params = http_build_query([
            'search_criteria' => $_GET['search_criteria'] ?? '',
            'search_input' => $_GET['search_input'] ?? '',
            'vendor_id' => $_GET['vendor_id'] ?? '',
            'instrument_type' => $_GET['instrument_type'] ?? '',
            'calibration_status' => $_GET['calibration_status'] ?? '',
            'instrument_status' => $_GET['instrument_status'] ?? '',
            'from_search' => '1'
        ]);

        // Determine which actions to show based on instrument status and user permissions
        $can_edit = true;
        $can_approve = false;
        $current_user_id = $_SESSION['user_id'] ?? 0;
        $is_admin = (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === 'Yes') ||
                   (isset($_SESSION['is_super_admin']) && $_SESSION['is_super_admin'] === 'Yes');
        $is_vendor = (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'vendor');

        if ($instrument['instrument_status'] === 'Pending') {
            // For pending records, only submitters can edit (vendors) or any admin can edit
            if ($is_vendor) {
                $can_edit = ($instrument['submitted_by'] == $current_user_id);
                // Vendor users can approve if they're not the submitter and belong to same vendor
                $can_approve = ($instrument['submitted_by'] != $current_user_id);
            } else {
                // Admin users can always edit and approve
                $can_edit = $is_admin;
                $can_approve = $is_admin;
            }
        }

        // Show Edit button
        if ($can_edit) {
            echo '<a href="manageinstrumentdetails.php?m=e&instrument_id=' . urlencode($instrument['instrument_id']) . '&' . $search_params . '" class="btn btn-gradient-info btn-sm">Edit</a> ';
        }

        // Show Approve/Reject buttons for pending records
        if ($instrument['instrument_status'] === 'Pending' && $can_approve) {
            echo '<a href="#" class="btn btn-gradient-success btn-sm" onclick="approveInstrument(\'' . htmlspecialchars($instrument['instrument_id'], ENT_QUOTES, 'UTF-8') . '\')">Approve</a> ';
            echo '<a href="#" class="btn btn-gradient-danger btn-sm" onclick="rejectInstrument(\'' . htmlspecialchars($instrument['instrument_id'], ENT_QUOTES, 'UTF-8') . '\')">Reject</a>';
        }

        echo '</td>
                </tr>';
        }
        
        echo '</tbody></table>';
        
        // Display summary
        echo '<div class="mt-3">
                <p class="text-muted"><strong>Total Results:</strong> ' . count($instruments) . ' instruments found</p>
              </div>';
        
    } else {
        echo '<div class="text-center p-4">
                <i class="mdi mdi-information-outline mdi-48px text-muted"></i>
                <h5 class="mt-3 text-muted">No Instruments Found</h5>
                <p class="text-muted">No instruments match your search criteria. Try adjusting your filters.</p>
              </div>';
    }
    
} catch (Exception $e) {
    error_log("Get instrument details error: " . $e->getMessage());
    error_log("Get instrument details stack trace: " . $e->getTraceAsString());
    
    echo '<div class="alert alert-danger" role="alert">
            <i class="mdi mdi-alert-circle-outline"></i>
            <strong>Error:</strong> Unable to fetch instrument data. Please try again.
          </div>';
}

?>