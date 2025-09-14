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

?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include_once "assets/inc/_header.php";?>
    <meta name="csrf-token" content="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
    <script>
    $(document).ready(function(){
   
    // Filter Group Statistics Overview functionality
    function loadFilterGroupStatistics() {
        console.log('Loading filter group statistics...');
        
        // Make AJAX call to fetch filter group statistics
        $.get("core/data/get/getfiltergroupstats.php")
        .done(function(response) {
            console.log('Filter group statistics response received');
            console.log('Raw response:', response);
            try {
                var stats = JSON.parse(response);
                console.log('Parsed filter group stats:', stats);
                
                // Update the counts
                $('#active_filtergroups_count').text(stats.active_filtergroups || 0);
                $('#inactive_filtergroups_count').text(stats.inactive_filtergroups || 0);
                
            } catch(e) {
                console.log('Error parsing filter group statistics response:', e);
                console.log('Raw response that failed to parse:', response);
            }
        })
        .fail(function(xhr, status, error) {
            console.log('Failed to fetch filter group statistics');
            console.log('Error:', error);
            console.log('Status:', status);
            console.log('Response text:', xhr.responseText);
            console.log('Status code:', xhr.status);
            
            // Try to parse error response
            try {
                var errorResponse = JSON.parse(xhr.responseText);
                if (errorResponse.error) {
                    console.log('Server error message:', errorResponse.error);
                }
            } catch(e) {
                // Response is not JSON, maybe HTML redirect
                if (xhr.status === 302 || xhr.responseText.includes('login.php')) {
                    console.log('User session expired or not authenticated');
                }
            }
            
            // Set default values if API call fails
            $('#active_filtergroups_count').text('0');
            $('#inactive_filtergroups_count').text('0');
        });
    }
    
    // Load statistics on page load
    loadFilterGroupStatistics();
    
    
 	$("#formreport").on('submit',(function(e) {
e.preventDefault();

		$('#pleasewaitmodal').modal('show');
					 $.get("core/data/get/getfiltergroupdetails.php",
                      {
                        status: $("#status").val()
                      },
                      function(data, status){
                     $('#pleasewaitmodal').modal('hide');
                     $("#displayresults").html(data);
                     
                     // Small delay to ensure DOM is ready, then initialize modern DataTable
                     setTimeout(function() {
                         // Destroy existing DataTable if it exists
                         if ($.fn.DataTable.isDataTable('#tbl-filtergroup-details')) {
                             $('#tbl-filtergroup-details').DataTable().destroy();
                         }
                         
                         // Initialize modern DataTable with enhanced features
                         $('#tbl-filtergroup-details').DataTable({
                             "pagingType": "numbers",
                             "pageLength": 25,
                             "lengthMenu": [[10, 25, 50, 100], [10, 25, 50, 100]],
                             "searching": true,
                             "ordering": true,
                             "info": true,
                             "autoWidth": false,
                             "responsive": false,
                             "scrollX": false,
                             "columnDefs": [
                                 {
                                     "targets": -1,
                                     "orderable": false,
                                     "searchable": false
                                 }
                             ],
                             "order": [[ 1, "asc" ]], // Sort by filter group name ascending
                             "language": {
                                 "search": "Search Filter Groups:",
                                 "lengthMenu": "Show _MENU_ filter groups per page",
                                 "info": "Showing _START_ to _END_ of _TOTAL_ filter groups",
                                 "infoEmpty": "No filter groups found",
                                 "zeroRecords": "No matching filter groups found",
                                 "paginate": {
                                     "first": "First",
                                     "last": "Last",
                                     "next": "Next",
                                     "previous": "Previous"
                                 }
                             }
                         });
                     }, 100);
                     
                    });
                   
	}));
    
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
              <h3 class="page-title"> Search Filter Groups </h3>
              <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                  <li class="breadcrumb-item"><a class='btn btn-gradient-info btn-sm btn-rounded' href="managefiltergroups.php?m=a">+ Add Filter Group</a></li>
                </ol>
              </nav>
            </div>
            
            <!-- Statistics Cards -->
            <div class="row">
              <div class="col-md-6 grid-margin stretch-card">
                <div class="card">
                  <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                      <div class="d-flex align-items-center">
                        <i class="mdi mdi-filter-variant icon-lg text-success mr-3"></i>
                        <div>
                          <h6 class="mb-0">Active Filter Groups</h6>
                        </div>
                      </div>
                      <h3 class="mb-0 text-success" id="active_filtergroups_count">-</h3>
                    </div>
                  </div>
                </div>
              </div>
              <div class="col-md-6 grid-margin stretch-card">
                <div class="card">
                  <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                      <div class="d-flex align-items-center">
                        <i class="mdi mdi-filter-variant-remove icon-lg text-danger mr-3"></i>
                        <div>
                          <h6 class="mb-0">Inactive Filter Groups</h6>
                        </div>
                      </div>
                      <h3 class="mb-0 text-danger" id="inactive_filtergroups_count">-</h3>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            
            <div class="row">
            	<div class="col-12 grid-margin stretch-card">
                <div class="card">
                  <div class="card-body">
                    <h4 class="card-title">Search Filters</h4>
                    <p class="card-description"> Use the filters below to search for specific filter groups </p>
                    
                    <form class="forms-sample" id="formreport">
                      <div class="form-row">
                        <div class="form-group col-md-12">
                          <label for="status">Status</label>
                          <select class="form-control" id="status" name="status">
                            <option value="">All Status</option>
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                          </select>
                        </div>
                        
                         
                      </div>
                       <input type="submit" id="searchfiltergroups" class="btn btn-gradient-original-success mr-2" value="Search Filter Groups"/>
                          
                    </form>
                  </div>
                </div>
              </div>
            </div>
            
            <div class="row">
            	<div class="col-12 grid-margin stretch-card">
                <div class="card">
                  <div class="card-body">
                    <h4 class="card-title">Filter Group Results</h4>
                    <div id="displayresults">
                      <div class="text-center text-muted py-4">
                        <i class="mdi mdi-filter-variant icon-lg mb-2"></i>
                        <p>Use the search filters above to find filter groups</p>
                      </div>
                    </div>
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