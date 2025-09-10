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
    
    // Build the WHERE clause
    $where_conditions = [];
    $params = [];
    $param_types = [];
    
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
    
    // Handle vendor filter
    if (!empty($vendor_id) && is_numeric($vendor_id)) {
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
    
    // Build the complete query
    $sql = "SELECT 
                i.instrument_id,
                i.serial_number,
                i.instrument_type,
                v.vendor_name,
                i.calibrated_on as last_calibration_date,
                i.calibration_due_on as next_calibration_date,
                i.instrument_status,
                DATEDIFF(i.calibration_due_on, CURDATE()) as days_until_calibration,
                CASE 
                    WHEN i.calibration_due_on < CURDATE() THEN 'Expired'
                    WHEN i.calibration_due_on BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'Due Soon'
                    WHEN i.calibration_due_on > DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'Valid'
                    ELSE 'Not Set'
                END as calibration_status
            FROM instruments i
            LEFT JOIN vendors v ON i.vendor_id = v.vendor_id";
    
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
                        <th>Serial Number</th>
                        <th>Type</th>
                        <th>Vendor</th>
                        <th>Last Calibration</th>
                        <th>Next Calibration</th>
                        <th>Status</th>
                        <th>Days Until</th>
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
            
            echo '<tr>
                    <td>' . htmlspecialchars($instrument['instrument_id'], ENT_QUOTES, 'UTF-8') . '</td>
                    <td>' . htmlspecialchars($instrument['serial_number'], ENT_QUOTES, 'UTF-8') . '</td>
                    <td>' . htmlspecialchars($instrument['instrument_type'], ENT_QUOTES, 'UTF-8') . '</td>
                    <td>' . htmlspecialchars($instrument['vendor_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8') . '</td>
                    <td>' . $last_cal_date . '</td>
                    <td>' . $next_cal_date . '</td>
                    <td><span class="badge ' . $status_class . '">' . htmlspecialchars($instrument['calibration_status'], ENT_QUOTES, 'UTF-8') . '</span></td>
                    <td>' . $days_display . '</td>
                    <td>
                        <a href="manageinstrumentdetails.php?m=e&instrument_id=' . urlencode($instrument['instrument_id']) . '" class="btn btn-gradient-info btn-sm">Edit</a>
                    </td>
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