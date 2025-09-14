<?php
require_once('./core/config/config.php');

// Session is already started by config.php via session_init.php

// Check for proper authentication
if (!isset($_SESSION['logged_in_user']) || !isset($_SESSION['user_name'])) {
    session_destroy();
    header('Location: ' . BASE_URL . 'login.php?msg=session_required');
    exit();
}

// Validate session timeout
require_once('core/security/session_timeout_middleware.php');
validateActiveSession();

// Generate CSRF token if not present
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once 'core/config/db.class.php';

try {
    $dept_details = DB::query("SELECT department_id, department_name, department_status FROM departments ORDER BY department_name ASC");
} catch (Exception $e) {
    error_log("Error fetching department details: " . $e->getMessage());
    $dept_details = [];
}


?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include_once "assets/inc/_header.php";?>
    <meta name="csrf-token" content="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
  
    <script>
    $(document).ready(function(){
        // Department Statistics Overview functionality
        function loadDepartmentStatistics() {
            console.log('=== DEBUGGING DEPARTMENT STATS ===');
            console.log('Loading department statistics...');
            
            // Make AJAX call to fetch department statistics
            $.get("core/data/get/getdepartmentstats.php")
            .done(function(response) {
                console.log('SUCCESS: Department statistics response received');
                console.log('Raw response:', response);
                try {
                    var stats = JSON.parse(response);
                    console.log('Parsed department stats:', stats);
                    
                    // Update the counts and log each update
                    $('#active_departments_count').text(stats.active_departments || 0);
                    console.log('Updated active_departments_count to:', stats.active_departments || 0);
                    
                    $('#inactive_departments_count').text(stats.inactive_departments || 0);
                    console.log('Updated inactive_departments_count to:', stats.inactive_departments || 0);
                    
                } catch(e) {
                    console.log('ERROR: Error parsing department statistics response:', e);
                    console.log('Raw response that failed to parse:', response);
                }
            })
            .fail(function(xhr, status, error) {
                console.log('FAIL: Failed to fetch department statistics');
                console.log('Error:', error);
                console.log('Status:', status);
                console.log('Response text:', xhr.responseText);
                console.log('Status code:', xhr.status);
            });
        }
        
        // Load statistics on page load
        loadDepartmentStatistics();
        
        // Initialize DataTable for departments table on page load
        setTimeout(function() {
            // Initialize modern DataTable with enhanced features
            $('#tbl-department-details').DataTable({
                "pagingType": "numbers",
                "pageLength": 25,
                "lengthMenu": [[10, 25, 50, 100], [10, 25, 50, 100]],
                "searching": true,
                "ordering": true,
                "info": true,
                "language": {
                    "search": "Search departments:",
                    "lengthMenu": "Show _MENU_ entries",
                    "info": "Showing _START_ to _END_ of _TOTAL_ departments"
                }
            });
        }, 100); // 100ms delay to ensure DOM is ready
    });
    </script>
    
    <link rel="stylesheet" href="assets/css/modern-manage-ui.css">
    
  </head>
  <body>
    <?php include_once "assets/inc/_pleasewaitmodal.php"; ?>
    <div class="container-scroller">
	<?php include "assets/inc/_navbar.php"; ?>
    <!-- partial -->
	<div class="container-fluid page-body-wrapper">
    <!-- partial:assets/inc/_sidebar.php -->
	<?php include "assets/inc/_sidebar.php"; ?>
    <!-- partial -->
	<div class="main-panel">
	<div class="content-wrapper">
	<?php include "assets/inc/_sessiontimeout.php"; ?>
			<div class="page-header">
              <h3 class="page-title"> Department Details</h3>
              
            </div>
            
            
            <div class="row">
            
            <!-- Department Statistics Overview Card -->
            <div class="col-12 grid-margin stretch-card">
                <div class="card">
                  <div class="card-body">
                    <h4 class="card-title">Department Statistics Overview</h4>
                    
                    <!-- Department Statistics Tiles -->
                    <div class="row department-stats-container">
                        <div class="col-12 col-sm-6 col-md-6 stretch-card grid-margin">
                            <div class="card bg-gradient-success card-img-holder text-white department-stats-tile">
                                <div class="card-body">
                                    <img src="assets/images/dashboard/circle.svg" class="card-img-absolute" alt="circle-image" />
                                    <h4 class="font-weight-normal mb-3">
                                        Active Departments
                                        <i class="mdi mdi-check-circle mdi-24px float-right"></i>
                                    </h4>
                                    <h2 class="mb-5 display-1" id="active_departments_count">0</h2>
                                    <h6 class="card-text">Currently active</h6>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-12 col-sm-6 col-md-6 stretch-card grid-margin">
                            <div class="card bg-gradient-dark card-img-holder text-white department-stats-tile">
                                <div class="card-body">
                                    <img src="assets/images/dashboard/circle.svg" class="card-img-absolute" alt="circle-image" />
                                    <h4 class="font-weight-normal mb-3">
                                        Inactive Departments
                                        <i class="mdi mdi-close-circle mdi-24px float-right"></i>
                                    </h4>
                                    <h2 class="mb-5 display-1" id="inactive_departments_count">0</h2>
                                    <h6 class="card-text">Not active</h6>
                                </div>
                            </div>
                        </div>
                    </div>
                  </div>
                </div>
              </div>
            
            	<div class="col-12 grid-margin stretch-card">
                <div class="card">
                  <div class="card-body">
                    <h4 class="card-title">Departments</h4>
                    
                    
                    <?php 
                    
                    echo "<div class='table-responsive-xl'>";
                    echo "<table id='tbl-department-details' class='table table-bordered'>
                      <thead>
                        <tr>
                          <th>Department ID</th>
                          <th>Department Name</th>
                          <th>Department Status</th>
                        </tr>
                      </thead>
                      <tbody>
                    ";
                    
                    foreach($dept_details as $row){
                        echo "<tr>";
                        echo "<td>" . htmlspecialchars($row['department_id'], ENT_QUOTES, 'UTF-8') . "</td>";
                        echo "<td>" . htmlspecialchars($row['department_name'], ENT_QUOTES, 'UTF-8') . "</td>";
                        echo "<td>" . htmlspecialchars($row['department_status'], ENT_QUOTES, 'UTF-8') . "</td>";
                        echo "</tr>";
                    }
                    
                    echo "  </tbody></table>";
                    echo "</div>";
                    
                    ?>
                    
                    
                    
                    
                    
                    
                    
                    
                    
                    
                  </div>
                </div>
              </div>
            
            
     
            
            
            
            </div>
            
          
          </div>
<!-- content-wrapper ends -->
<!-- partial:assets/inc/_footer.php -->
<?php include "assets/inc/_footercopyright.php"; ?>
<!-- partial -->
</div>
<!-- main-panel ends -->
</div>
<!-- page-body-wrapper ends -->
</div>
 <?php include "assets/inc/_footerjs.php"; ?>
</body>
</html>
