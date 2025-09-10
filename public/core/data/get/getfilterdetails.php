<?php
require_once('../../config/config.php');

// Validate session timeout
require_once('../../security/session_timeout_middleware.php');
validateActiveSession();

// Use centralized session validation
require_once('../../security/session_validation.php');
validateUserSession();

require_once("../../config/db.class.php");

// Additional security validation - validate user type
$userType = $_SESSION['logged_in_user'] ?? '';
if (!in_array($userType, ['employee', 'vendor'])) {
    http_response_code(403);
    echo '<div class="alert alert-danger">Unauthorized access</div>';
    exit();
}

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo '<div class="alert alert-danger">Method not allowed</div>';
    exit();
}

try {
    // Get input parameters and sanitize
    $unit_id = $_GET['unitid'] ?? '';
    $filter_type = $_GET['filter_type'] ?? '';
    $filter_id = $_GET['filter_id'] ?? '';
    $status_filter = $_GET['status_filter'] ?? '';
    $manufacturer = $_GET['manufacturer'] ?? '';
    
    // Validate required parameters
    if (empty($unit_id) || $unit_id == 'Select') {
        echo '<div class="alert alert-warning">Please select a unit to search filters.</div>';
        exit();
    }
    
    // Get user information from session
    $user_unit_id = getUserUnitId();
    
    // Ensure unit_id is a valid integer for database
    if ($user_unit_id === '' || $user_unit_id === null) {
        $user_unit_id = 0;
    }
    $user_unit_id = intval($user_unit_id);
    $query_unit_id = intval($unit_id);
    
    // Build the base query
    $query = "
        SELECT 
            f.filter_id,
            f.unit_id,
            f.filter_code,
            f.filter_name,
            f.filter_size,
            f.filter_type_id,
            fg.filter_group_name as filter_type,
            f.manufacturer,
            f.specifications,
            f.installation_date,
            f.planned_due_date,
            f.actual_replacement_date,
            f.status,
            f.creation_datetime,
            f.last_modification_datetime,
            u.user_name as created_by_name
        FROM filters f
        LEFT JOIN users u ON f.created_by = u.user_id
        LEFT JOIN filter_groups fg ON f.filter_type_id = fg.filter_group_id
        WHERE 1=1
    ";
    
    $query_params = [];
    
    // Apply unit filtering based on user permissions
    if (!isVendor() && $_SESSION['is_super_admin'] !== "Yes") {
        // Regular employee users: restrict to their unit only
        $query .= " AND f.unit_id = %i";
        $query_params[] = $user_unit_id;
    } else if (!empty($query_unit_id)) {
        // Super admin or vendor: filter by selected unit if specified
        $query .= " AND f.unit_id = %i";
        $query_params[] = $query_unit_id;
    }
    
    // Apply additional filters
    if (!empty($filter_type) && $filter_type != 'Select') {
        $query .= " AND f.filter_type_id = %i";
        $query_params[] = intval($filter_type);
    }
    
    if (!empty($filter_id) && $filter_id != 'All' && $filter_id != 'Select') {
        $query .= " AND f.filter_id = %i";
        $query_params[] = intval($filter_id);
    }
    
    if (!empty($status_filter) && $status_filter != 'Select') {
        $query .= " AND f.status = %s";
        $query_params[] = $status_filter;
    }
    
    if (!empty($manufacturer)) {
        $query .= " AND f.manufacturer LIKE %ss";
        $query_params[] = '%' . $manufacturer . '%';
    }
    
    // Add ordering
    $query .= " ORDER BY f.filter_code ASC, f.creation_datetime DESC";
    
    // Execute query
    $results = DB::query($query, ...$query_params);
    
    if (empty($results)) {
        echo '<div class="alert alert-info">No filters found matching your criteria.</div>';
        exit();
    }
    
    // Generate HTML table
    $html = '
    <table class="table table-striped table-hover" id="tbl-filter-details">
        <thead>
            <tr>
                <th>Filter Code</th>
                <th>Filter Name</th>
                <th>Type</th>
                <th>Size</th>
                <th>Manufacturer</th>
                <th>Installation Date</th>
                <th>Due Date</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>';
    
    foreach ($results as $row) {
        // Format dates
        $installation_date = !empty($row['installation_date']) ? date('d.m.Y', strtotime($row['installation_date'])) : '-';
        $due_date = !empty($row['planned_due_date']) ? date('d.m.Y', strtotime($row['planned_due_date'])) : '-';
        
        // Determine due date status
        $due_status_class = '';
        $due_status_text = '';
        if (!empty($row['planned_due_date'])) {
            $due_timestamp = strtotime($row['planned_due_date']);
            $now = time();
            $days_until_due = ($due_timestamp - $now) / (24 * 60 * 60);
            
            if ($days_until_due < 0) {
                $due_status_class = 'text-danger';
                $due_status_text = ' (Overdue)';
            } else if ($days_until_due <= 30) {
                $due_status_class = 'text-warning';
                $due_status_text = ' (Due Soon)';
            }
        }
        
        // Status badge
        $status_badge = $row['status'] == 'Active' 
            ? '<span class="badge badge-success">Active</span>'
            : '<span class="badge badge-secondary">Inactive</span>';
        
        $html .= '
            <tr>
                <td><strong>' . htmlspecialchars($row['filter_code'], ENT_QUOTES, 'UTF-8') . '</strong></td>
                <td>' . htmlspecialchars($row['filter_name'] ?: '-', ENT_QUOTES, 'UTF-8') . '</td>
                <td>
                    <span class="badge badge-info">' . htmlspecialchars($row['filter_type'], ENT_QUOTES, 'UTF-8') . '</span>
                </td>
                <td>' . htmlspecialchars($row['filter_size'], ENT_QUOTES, 'UTF-8') . '</td>
                <td>' . htmlspecialchars($row['manufacturer'] ?: '-', ENT_QUOTES, 'UTF-8') . '</td>
                <td>' . $installation_date . '</td>
                <td>
                    <span class="' . $due_status_class . '">' . $due_date . $due_status_text . '</span>
                </td>
                <td>' . $status_badge . '</td>
                <td>
                    <a href="managefilterdetails.php?m=m&filter_id=' . intval($row['filter_id']) . '" 
                       class="btn btn-gradient-primary btn-sm" 
                       title="Edit Filter">
                        <i class="mdi mdi-pencil"></i> Edit
                    </a>
                </td>
            </tr>';
    }
    
    $html .= '
        </tbody>
    </table>';
    
    echo $html;
    
} catch (Exception $e) {
    error_log("Filter search error: " . $e->getMessage());
    echo '<div class="alert alert-danger">An error occurred while searching filters. Please try again.</div>';
}
?>