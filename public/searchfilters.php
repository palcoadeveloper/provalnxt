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
    
    
function fetchFilters(unitid) {
  
     $('#filter_id').empty();
     
     // Add "All" option as default
     $('#filter_id').append('<option value="All" selected>All</option>');
    
     $.get("core/data/get/getfiltersforddl.php",
                      {
                      	
                        unit_id: unitid
                        
                      },
                      function(data, status){
                     
                         $('#filter_id').append(data);
                      });
  
  
  }
  
  
$('#unitid').change(function() { 
 var item=$(this);
    
    fetchFilters(item.val());

});


 	$("#formreport").on('submit',(function(e) {
e.preventDefault();



		if($("#unitid").val()=='Select'){
			Swal.fire({
    			icon: 'error',
                        title: 'Oops...',
                        text: 'Please select unit.'                
    			});
		}
		
		else
		{
				$('#pleasewaitmodal').modal('show');
				 $.get("core/data/get/getfilterdetails.php",
                      {
                        unitid: $("#unitid").val(),
                        filter_type: $("#filter_type").val(),
                        filter_id:$("#filter_id").val(),
                        status_filter: $("#status_filter").val(),
                        manufacturer: $("#manufacturer").val()
                        
                      },
                      function(data, status){
                      $('#pleasewaitmodal').modal('hide');
                     $("#displayresults").html(data);
                     
                     // Small delay to ensure DOM is ready, then initialize modern DataTable
                     setTimeout(function() {
                         // Destroy existing DataTable if it exists
                         if ($.fn.DataTable.isDataTable('#tbl-filter-details')) {
                             $('#tbl-filter-details').DataTable().destroy();
                         }
                         
                         // Initialize DataTable with simple configuration (based on searchmapping.php)
                         $('#tbl-filter-details').DataTable({
                             "pagingType": "numbers",
                             "pageLength": 25,
                             "lengthMenu": [[10, 25, 50, 100], [10, 25, 50, 100]],
                             "searching": true,
                             "ordering": true,
                             "info": true,
                             "columnDefs": [
                                 {
                                     "targets": -1,
                                     "orderable": false,
                                     "searchable": false
                                 }
                             ],
                             "language": {
                                 "search": "Search filters:",
                                 "lengthMenu": "Show _MENU_ entries",
                                 "info": "Showing _START_ to _END_ of _TOTAL_ filters"
                             }
                         });
                     }, 100); // 100ms delay
                       
                      });
 

		}
		
		
		
		


}));




    
    // Filter Statistics Overview functionality
      function loadFilterStatistics() {
        var unitId = $('#stats_unit_selector').val();
        
        console.log('Loading filter statistics for unit:', unitId);
        
        // Make AJAX call to fetch filter statistics
        $.get("core/data/get/getfilterstats.php", {
          unit_id: unitId
        })
        .done(function(response) {
          console.log('Filter statistics response:', response);
          try {
            // Response is already parsed by jQuery when Content-Type is application/json
            var stats = response;
            $('#active_filters_count').text(stats.active_filters || 0);
            $('#inactive_filters_count').text(stats.inactive_filters || 0);
            $('#due_replacement_count').text(stats.due_replacement || 0);
          } catch(e) {
            console.log('Error processing filter statistics response:', e);
            console.log('Raw response:', response);
          }
        })
        .fail(function(xhr, status, error) {
          console.log('Failed to fetch filter statistics:', error);
          console.log('Status:', status);
          console.log('Response:', xhr.responseText);
        });
      }
      
      $('#stats_unit_selector').on('change', function(){
        loadFilterStatistics();
      });
      
      // Load initial filter statistics on page load
      loadFilterStatistics();

    
    });
    
    
    
    
    </script>
    
    <style>
    /* Reduce Filter Statistics tile height to 70% */
    .filter-stats-tile {
        height: 70% !important;
        min-height: 120px !important;
    }
    
    .filter-stats-tile .card-body {
        padding: 15px 15px 8px 15px !important;
    }
    
    .filter-stats-tile .display-1 {
        font-size: 2rem !important;
        margin-bottom: 5px !important;
    }
    
    .filter-stats-tile h4 {
        font-size: 0.9rem !important;
        margin-bottom: 8px !important;
    }
    
    .filter-stats-tile .card-text {
        font-size: 0.8rem !important;
        margin-bottom: 0 !important;
    }
    
    /* Reduce space around tiles by 50% */
    .filter-stats-container {
        margin-top: -15px !important;
        margin-bottom: -15px !important;
        padding-bottom: 5px !important;
    }
    
    .filter-stats-container .grid-margin {
        margin-bottom: 10px !important;
    }
    
    /* Reduce space between dropdown and tiles */
    .filter-stats-container .form-row.mb-2 {
        margin-bottom: 10px !important;
    }
    
    /* Modern DataTable Styling */
    #tbl-filter-details {
        border-collapse: separate;
        border-spacing: 0;
        border-radius: 8px !important;
        overflow: hidden;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        width: 100% !important;
        max-width: 100% !important;
    }
    
    /* Table container with horizontal scrolling when needed */
    .table-responsive-xl {
        width: 100%;
        max-width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    
    /* Ensure DataTable doesn't exceed container bounds */
    .dataTables_wrapper {
        width: 100%;
        max-width: 100%;
        overflow: visible;
    }
    
    #tbl-filter-details thead th {
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
    
    #tbl-filter-details thead th:first-child {
        border-top-left-radius: 8px;
    }
    
    #tbl-filter-details thead th:last-child {
        border-top-right-radius: 8px;
    }
    
    #tbl-filter-details tbody td {
        padding: 12px !important;
        vertical-align: middle !important;
        border-bottom: 1px solid #e3e6f0 !important;
        transition: all 0.3s ease !important;
    }
    
    #tbl-filter-details tbody td:last-child {
        text-align: center !important;
    }
    
    #tbl-filter-details tbody tr {
        transition: all 0.3s ease !important;
    }
    
    #tbl-filter-details tbody tr:hover {
        background-color: #f8f9fe !important;
        transform: scale(1.02) !important;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1) !important;
    }
    
    #tbl-filter-details tbody tr:nth-child(even) {
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
    #tbl-filter-details .btn {
        border-radius: 20px !important;
        font-size: 0.8rem !important;
        padding: 6px 14px !important;
        font-weight: 500 !important;
        transition: all 0.3s ease !important;
        text-transform: uppercase !important;
        letter-spacing: 0.5px !important;
    }
    
    #tbl-filter-details .btn:hover {
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
        
        #tbl-filter-details tbody tr:hover {
            transform: none !important;
        }
        
        .table-responsive-xl {
            font-size: 0.85rem;
        }
        
        #tbl-filter-details thead th {
            font-size: 0.75rem !important;
            padding: 10px 8px !important;
        }
        
        #tbl-filter-details tbody td {
            padding: 8px !important;
        }
    }
    
    /* Column width management and content wrapping */
    #tbl-filter-details {
        table-layout: auto !important;
        width: 100% !important;
        min-width: 800px; /* Minimum width to ensure readability */
    }
    
    /* Actions column styling */
    #tbl-filter-details td:last-child {
        white-space: nowrap;
        text-align: center;
        min-width: 120px;
    }
    
    /* Allow text wrapping for content columns but keep headers readable */
    #tbl-filter-details td {
        word-wrap: break-word;
        word-break: break-word;
        max-width: 200px;
    }
    
    /* Prevent action buttons from wrapping */
    #tbl-filter-details td:last-child {
        max-width: none;
        word-wrap: normal;
        word-break: normal;
    }
    
    /* Ensure table container scrolling works properly */
    .card-body {
        overflow-x: visible;
        padding: 1rem;
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
              <h3 class="page-title"> Search Filters</h3>
              <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                  <li class="breadcrumb-item"><a class='btn btn-gradient-info btn-sm btn-rounded' href="managefilterdetails.php?m=a">+ Add Filter</a></li>
                </ol>
              </nav>
            </div>
            
            
            <div class="row">
            
            <!-- Filter Statistics Overview Card -->
            <div class="col-12 grid-margin stretch-card">
                <div class="card">
                  <div class="card-body">
                    <h4 class="card-title">Filter Statistics Overview</h4>
                    
                    <div class="form-row mb-2">
                        <div class="form-group col-md-4">
                            <label for="stats_unit_selector">Select Unit:</label>
                            <select class="form-control" id="stats_unit_selector" name="stats_unit_selector">
                            <?php 
                            try {
                                if ($_SESSION['is_super_admin']=="Yes") {
                                    $units = DB::query("SELECT unit_id, unit_name FROM units ORDER BY unit_name ASC");
                                    if(!empty($units)) {
                                        foreach ($units as $unit) {
                                            echo "<option value='" . htmlspecialchars($unit['unit_id'], ENT_QUOTES, 'UTF-8') . "'>" . htmlspecialchars($unit['unit_name'], ENT_QUOTES, 'UTF-8') . "</option>";
                                        }
                                    }
                                } else {
                                    $unit_name = DB::queryFirstField("SELECT unit_name FROM units WHERE unit_id = %i", intval($_SESSION['unit_id']));
                                    echo "<option value='" . htmlspecialchars($_SESSION['unit_id'], ENT_QUOTES, 'UTF-8') . "'>" . htmlspecialchars($unit_name, ENT_QUOTES, 'UTF-8') . "</option>";
                                }
                            } catch (Exception $e) {
                                error_log("Error fetching units for stats: " . $e->getMessage());
                            }
                            ?>	
                            </select>
                        </div>
                    </div>
                    
                    <!-- Statistics Tiles -->
                    <div class="row filter-stats-container">
                        <div class="col-12 col-sm-6 col-md-4 stretch-card grid-margin">
                            <div class="card bg-gradient-success card-img-holder text-white filter-stats-tile">
                                <div class="card-body">
                                    <img src="assets/images/dashboard/circle.svg" class="card-img-absolute" alt="circle-image" />
                                    <h4 class="font-weight-normal mb-3">
                                        Active Filters
                                        <i class="mdi mdi-filter-check mdi-24px float-right"></i>
                                    </h4>
                                    <h2 class="mb-5 display-1" id="active_filters_count">0</h2>
                                    <h6 class="card-text">Currently active</h6>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-12 col-sm-6 col-md-4 stretch-card grid-margin">
                            <div class="card bg-gradient-dark card-img-holder text-white filter-stats-tile">
                                <div class="card-body">
                                    <img src="assets/images/dashboard/circle.svg" class="card-img-absolute" alt="circle-image" />
                                    <h4 class="font-weight-normal mb-3">
                                        Inactive Filters
                                        <i class="mdi mdi-filter-remove mdi-24px float-right"></i>
                                    </h4>
                                    <h2 class="mb-5 display-1" id="inactive_filters_count">0</h2>
                                    <h6 class="card-text">Not active</h6>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-12 col-sm-6 col-md-4 stretch-card grid-margin">
                            <div class="card bg-gradient-warning card-img-holder text-white filter-stats-tile">
                                <div class="card-body">
                                    <img src="assets/images/dashboard/circle.svg" class="card-img-absolute" alt="circle-image" />
                                    <h4 class="font-weight-normal mb-3">
                                        Due for Replacement
                                        <i class="mdi mdi-calendar-clock mdi-24px float-right"></i>
                                    </h4>
                                    <h2 class="mb-5 display-1" id="due_replacement_count">0</h2>
                                    <h6 class="card-text">Needs replacement</h6>
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
                    <h4 class="card-title">Select Criteria</h4>
                    
                    <form class="forms-sample" id="formreport">
                <div class="form-row">
                    
                    <div class="form-group  col-md-6">
                        <label for="exampleSelectGender">Unit</label>
                        <select class="form-control" id="unitid" name="unitid">
                          <option>Select</option>
                       	<?php 
		
                       	if ($_SESSION['is_super_admin']=="Yes")
                       	{
                       	    try {
                       	        $results = DB::query("SELECT unit_id, unit_name FROM units ORDER BY unit_name ASC");
                       	        
                       	        if(!empty($results))
                       	        {
                       	            foreach ($results as $row) {
                       	                $output=$output. "<option value='" . htmlspecialchars($row['unit_id'], ENT_QUOTES, 'UTF-8') . "'>" . htmlspecialchars($row['unit_name'], ENT_QUOTES, 'UTF-8') . "</option>";
                       	            }
                       	            
                       	            echo $output;
                       	        }
                       	    } catch (Exception $e) {
                       	        error_log("Error fetching units: " . $e->getMessage());
                       	    }
                       	}
                       	else {
                       	    try {
                       	        $unit_name = DB::queryFirstField("SELECT unit_name FROM units WHERE unit_id = %i", intval($_SESSION['unit_id']));
                       	        
                       	        echo "<option value='" . htmlspecialchars($_SESSION['unit_id'], ENT_QUOTES, 'UTF-8') . "'>" . htmlspecialchars($unit_name, ENT_QUOTES, 'UTF-8') . "</option>";
                       	    } catch (Exception $e) {
                       	        error_log("Error fetching unit name: " . $e->getMessage());
                       	    }
                       	}
                    
 

		
                       	?>	
                        </select>
                      </div>
                    
                    <div class="form-group  col-md-6">
                        <label for="exampleSelectGender">Filter Type</label>
                        <select class="form-control" id="filter_type" name="filter_type">
                          <option>Select</option>
                        <?php
                        try {
                            $filter_groups = DB::query("SELECT filter_group_id, filter_group_name FROM filter_groups WHERE status = 'Active' ORDER BY filter_group_name");
                            if (!empty($filter_groups)) {
                                foreach ($filter_groups as $group) {
                                    echo "<option value='" . intval($group['filter_group_id']) . "'>" . htmlspecialchars($group['filter_group_name'], ENT_QUOTES, 'UTF-8') . "</option>";
                                }
                            }
                        } catch (Exception $e) {
                            error_log("Error loading filter groups for search: " . $e->getMessage());
                        }
                        ?>
                    
                        </select>
                      </div>
                    
                    
                     
  </div>                    
                       <div class="form-row">
                      <div class="form-group col-md-6">
                        <label for="wfstageid">Filter Code</label>
                        <select class="form-control" id="filter_id" name="filter_id">
                         <option>Select</option>
                          <option value='All' selected>All</option>
                          
                       
                        </select>
                      </div>
                       
                       
                       
                         <div class="form-group  col-md-6">
                        <label for="exampleSelectGender">Status</label>
                        
                    	  <select class="form-control" id="status_filter" name="status_filter">
				
				<option value='Select'>Select</option>
				<option value='Active'>Active</option>
				<option value='Inactive'>Inactive</option>
                       	
                        </select>
                       
                       </div>
                       
                       </div>
                       
                       <div class="form-row">
                         <div class="form-group  col-md-6">
                        <label for="manufacturer">Manufacturer</label>
                        
                    	  <input type="text" class="form-control" id="manufacturer" name="manufacturer" placeholder="Enter manufacturer name">
                       
                       </div>
                       
                       
                       
                       
                       
                       
                       
                      </div>
                      
                     
        
     
  
            
        
               
                      
                      
                      <input type="submit" id="searchfilters" class="btn btn-gradient-primary mr-2"/>
                      
                    </form>
                  </div>
                </div>
              </div>
            
            
            <div class="col-12 grid-margin stretch-card">
                <div class="card">
                  <div class="card-body">
                <h4 class="card-title">Result</h4>
                    <div class="table-responsive-xl">
	<div id="displayresults">
	<p class="card-description">Select the criteria and hit the Submit button.</p>
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

