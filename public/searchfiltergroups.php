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
    
    <style>
    /* Modern DataTable Styling */
    #tbl-filtergroup-details {
        border-collapse: separate;
        border-spacing: 0;
        border-radius: 8px !important;
        overflow: hidden;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }
    
    #tbl-filtergroup-details thead th {
        background: linear-gradient(135deg, #b967db 0%, #9c4ac7 100%) !important;
        color: white !important;
        font-weight: 600 !important;
        text-transform: uppercase !important;
        font-size: 0.85rem !important;
        letter-spacing: 0.5px !important;
        padding: 15px 12px !important;
        border: none !important;
        position: relative !important;
        vertical-align: middle !important;
        text-align: center !important;
    }
    
    #tbl-filtergroup-details thead th:first-child {
        border-top-left-radius: 8px;
    }
    
    #tbl-filtergroup-details thead th:last-child {
        border-top-right-radius: 8px;
    }
    
    #tbl-filtergroup-details tbody td {
        padding: 12px !important;
        vertical-align: middle !important;
        border-bottom: 1px solid #e3e6f0 !important;
        transition: all 0.3s ease !important;
    }
    
    #tbl-filtergroup-details tbody td:last-child {
        text-align: center !important;
    }
    
    #tbl-filtergroup-details tbody tr {
        transition: all 0.3s ease !important;
    }
    
    #tbl-filtergroup-details tbody tr:hover {
        background-color: #f8f9fe !important;
        transform: scale(1.02) !important;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1) !important;
    }
    
    #tbl-filtergroup-details tbody tr:nth-child(even) {
        background-color: #fafbfc !important;
    }
    
    /* DataTable Controls Styling */
    .dataTables_wrapper .dataTables_length select {
        padding: 6px 12px !important;
        border: 2px solid #e3e6f0 !important;
        border-radius: 6px !important;
        background-color: white !important;
        transition: all 0.3s ease !important;
    }
    
    .dataTables_wrapper .dataTables_length select:focus {
        border-color: #b967db !important;
        outline: none !important;
        box-shadow: 0 0 0 3px rgba(185, 103, 219, 0.1) !important;
    }
    
    .dataTables_wrapper .dataTables_filter input {
        padding: 8px 16px !important;
        border: 2px solid #e3e6f0 !important;
        border-radius: 25px !important;
        background-color: white !important;
        transition: all 0.3s ease !important;
        width: 250px !important;
    }
    
    .dataTables_wrapper .dataTables_filter input:focus {
        border-color: #348fe2 !important;
        outline: none !important;
        box-shadow: 0 0 0 3px rgba(52, 143, 226, 0.1) !important;
        width: 300px !important;
    }
    
    .dataTables_wrapper .dataTables_paginate .paginate_button {
        padding: 8px 16px !important;
        margin: 0 2px !important;
        border: 1px solid #e3e6f0 !important;
        border-radius: 6px !important;
        background: white !important;
        color: #5a5c69 !important;
        transition: all 0.3s ease !important;
    }
    
    .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
        background: #b967db !important;
        color: white !important;
        border-color: #b967db !important;
        transform: translateY(-1px) !important;
    }
    
    .dataTables_wrapper .dataTables_paginate .paginate_button.current {
        background: linear-gradient(135deg, #b967db 0%, #9c4ac7 100%) !important;
        color: white !important;
        border-color: #b967db !important;
        font-weight: 600 !important;
    }
    
    .dataTables_wrapper .dataTables_info {
        color: #6c757d !important;
        font-weight: 500 !important;
        padding-top: 12px !important;
    }
    
    /* Action Buttons Enhancement */
    #tbl-filtergroup-details .btn {
        border-radius: 20px !important;
        font-size: 0.8rem !important;
        padding: 6px 14px !important;
        font-weight: 500 !important;
        transition: all 0.3s ease !important;
        text-transform: uppercase !important;
        letter-spacing: 0.5px !important;
    }
    
    #tbl-filtergroup-details .btn:hover {
        transform: translateY(-2px) !important;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15) !important;
    }
    
    /* Loading and Processing States */
    .dataTables_processing {
        background: rgba(255, 255, 255, 0.9) !important;
        border: 1px solid #ddd !important;
        border-radius: 8px !important;
        color: #348fe2 !important;
        font-weight: 600 !important;
        padding: 16px 24px !important;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1) !important;
    }
    
    /* Responsive Improvements */
    @media (max-width: 768px) {
        .dataTables_wrapper .dataTables_filter input {
            width: 200px !important;
        }
        
        .dataTables_wrapper .dataTables_filter input:focus {
            width: 240px !important;
        }
        
        #tbl-filtergroup-details tbody tr:hover {
            transform: none !important;
        }
    }
    </style>
    
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
                        <div class="form-group col-md-4">
                          <label for="status">Status</label>
                          <select class="form-control" id="status" name="status">
                            <option value="">All Status</option>
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                          </select>
                        </div>
                        <div class="form-group col-md-8 d-flex align-items-end">
                          <input type="submit" id="searchfiltergroups" class="btn btn-gradient-primary mr-2" value="Search Filter Groups"/>
                          <button type="button" class="btn btn-light" onclick="window.location.reload();">Reset</button>
                        </div>
                      </div>
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