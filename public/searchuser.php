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
    
      $('#user_type').on('change', function(){
    	var demovalue = $(this).val(); 
        $("div.myDiv").hide();
        $("#show"+demovalue).show();
    });
    
      // User Statistics Overview functionality
      function loadUserStatistics() {
        var unitId = $('#stats_unit_selector').val();
        
        console.log('Loading statistics for unit:', unitId);
        
        // Make AJAX call to fetch user statistics
        $.get("core/data/get/getuserstats.php", {
          unit_id: unitId
        })
        .done(function(response) {
          console.log('Statistics response:', response);
          try {
            var stats = JSON.parse(response);
            $('#active_employees_count').text(stats.active_employees || 0);
            $('#inactive_employees_count').text(stats.inactive_employees || 0);
            $('#active_vendor_employees_count').text(stats.active_vendor_employees || 0);
            $('#inactive_vendor_employees_count').text(stats.inactive_vendor_employees || 0);
          } catch(e) {
            console.log('Error parsing statistics response:', e);
            console.log('Raw response:', response);
          }
        })
        .fail(function(xhr, status, error) {
          console.log('Failed to fetch user statistics:', error);
          console.log('Status:', status);
          console.log('Response:', xhr.responseText);
        });
      }
      
      $('#stats_unit_selector').on('change', function(){
        loadUserStatistics();
      });
      
      // Load initial statistics on page load
      loadUserStatistics();

 	$("#formreport").on('submit',(function(e) {
		e.preventDefault();
	
		if($("#unitid").val()=='select' && $('#user_type').val()=='IE'){
			
    			
    				Swal.fire({
    					icon: 'error',
                        title: 'Oops...',
                        text: 'Please select the unit.'                
    				});
		
		}
		else
		{
		$('#pleasewaitmodal').modal('show');
					 $.get("core/data/get/getuserdetails.php",
                      {
                        usertype:$('#user_type').val(),
                        unitid: $("#unitid").val(),
                        searchcriteria: $("#search_criteria").val(),
                        searchinput: $("#search_input").val(),
                        vendorid: $("#vendor_id").val()
                        
                      },
                      function(data, status){
                      $('#pleasewaitmodal').modal('hide');
                     $("#displayresults").html(data);
                     
                     // Small delay to ensure DOM is ready, then initialize modern DataTable
                     setTimeout(function() {
                         // Destroy existing DataTable if it exists
                         if ($.fn.DataTable.isDataTable('#tbl-user-details')) {
                             $('#tbl-user-details').DataTable().destroy();
                         }
                         
                         // Initialize modern DataTable with enhanced features
                         $('#tbl-user-details').DataTable({
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
                                 "search": "Search users:",
                                 "lengthMenu": "Show _MENU_ entries",
                                 "info": "Showing _START_ to _END_ of _TOTAL_ users"
                             }
                         });
                     }, 100); // 100ms delay
                       
                      });
 
		}
		
		
		
		
		





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
              <h3 class="page-title"> Search Users</h3>
              <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                  <li class="breadcrumb-item"><a class='btn btn-gradient-success btn-sm btn-rounded' href="manageuserdetails.php?m=a&u=c">+ Add Employee</a></li>
                   <li class="breadcrumb-item"><a class='btn btn-gradient-info btn-sm btn-rounded' href="manageuserdetails.php?m=a&u=v">+ Add Vendor Employee</a></li>
                </ol>
              </nav>
            </div>
            
            
            <div class="row">
            
            <!-- User Statistics Overview Card -->
            <div class="col-12 grid-margin stretch-card">
                <div class="card">
                  <div class="card-body">
                    <h4 class="card-title">User Statistics Overview</h4>
                    
                    <div class="form-row mb-2">
                        <div class="form-group col-md-12">
                            <label for="stats_unit_selector">Select Unit:</label>
                            <select class="form-control" id="stats_unit_selector" name="stats_unit_selector">
                            <?php 
                            try {
                                if ($_SESSION['is_super_admin']=="Yes") {
                                    // Add 'All Units' option for super admin
                                    echo "<option value='all'>All Units</option>";
                                    
                                    $units = DB::query("SELECT unit_id, unit_name FROM units where unit_status='Active' ORDER BY unit_name ASC");
                                    if(!empty($units)) {
                                        foreach ($units as $unit) {
                                            echo "<option value='" . htmlspecialchars($unit['unit_id'], ENT_QUOTES, 'UTF-8') . "'>" . htmlspecialchars($unit['unit_name'], ENT_QUOTES, 'UTF-8') . "</option>";
                                        }
                                    }
                                } else {
                                    $unit_name = DB::queryFirstField("SELECT unit_name FROM units WHERE unit_id = %i and unit_status='Active'", intval($_SESSION['unit_id']));
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
                    <div class="row user-stats-container">
                        <div class="col-12 col-sm-6 col-md-4 col-lg-3 stretch-card grid-margin">
                            <div class="card bg-gradient-success card-img-holder text-white user-stats-tile">
                                <div class="card-body">
                                    <img src="assets/images/dashboard/circle.svg" class="card-img-absolute" alt="circle-image" />
                                    <h4 class="font-weight-normal mb-3">
                                        Active Employees
                                        <i class="mdi mdi-account-plus mdi-24px float-right"></i>
                                    </h4>
                                    <h2 class="mb-5 display-1" id="active_employees_count">25</h2>
                                    <h6 class="card-text">Currently active</h6>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-12 col-sm-6 col-md-4 col-lg-3 stretch-card grid-margin">
                            <div class="card bg-gradient-dark card-img-holder text-white user-stats-tile">
                                <div class="card-body">
                                    <img src="assets/images/dashboard/circle.svg" class="card-img-absolute" alt="circle-image" />
                                    <h4 class="font-weight-normal mb-3">
                                        Inactive Employees
                                        <i class="mdi mdi-account-remove mdi-24px float-right"></i>
                                    </h4>
                                    <h2 class="mb-5 display-1" id="inactive_employees_count">0</h2>
                                    <h6 class="card-text">Not active</h6>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-12 col-sm-6 col-md-4 col-lg-3 stretch-card grid-margin">
                            <div class="card bg-gradient-info card-img-holder text-white user-stats-tile">
                                <div class="card-body">
                                    <img src="assets/images/dashboard/circle.svg" class="card-img-absolute" alt="circle-image" />
                                    <h4 class="font-weight-normal mb-3">
                                        Active Vendor Employees
                                        <i class="mdi mdi-account-check mdi-24px float-right"></i>
                                    </h4>
                                    <h2 class="mb-5 display-1" id="active_vendor_employees_count">7</h2>
                                    <h6 class="card-text">Currently active</h6>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-12 col-sm-6 col-md-4 col-lg-3 stretch-card grid-margin">
                            <div class="card bg-gradient-warning card-img-holder text-white user-stats-tile">
                                <div class="card-body">
                                    <img src="assets/images/dashboard/circle.svg" class="card-img-absolute" alt="circle-image" />
                                    <h4 class="font-weight-normal mb-3">
                                        Inactive Vendor Employees
                                        <i class="mdi mdi-account-off mdi-24px float-right"></i>
                                    </h4>
                                    <h2 class="mb-5 display-1" id="inactive_vendor_employees_count">0</h2>
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
                    <h4 class="card-title">Select Criteria</h4>
                    
                    <form class="forms-sample" id="formreport">
                <div class="form-row">
                    <div id="showIE" class="myDiv form-group  col-md-6">
                        <label for="exampleSelectGender">Unit</label>
                        <select class="form-control" id="unitid" name="unitid">
                          <option value='select'>Select</option>
                       	<?php if ($_SESSION['is_super_admin']=="Yes")
                       	{
                       	    try {
                       	        $results = DB::query("SELECT unit_id, unit_name FROM units where unit_status='Active' ORDER BY unit_name ASC");
                       	        
                       	        if(!empty($results))
                       	        {
                       	            $output = "";
                       	            foreach ($results as $row) {
                       	                $output .= "<option value='" . htmlspecialchars($row['unit_id'], ENT_QUOTES, 'UTF-8') . "'>" . htmlspecialchars($row['unit_name'], ENT_QUOTES, 'UTF-8') . "</option>";
                       	            }
                       	            
                       	            echo $output;
                       	        }
                       	    } catch (Exception $e) {
                       	        require_once('core/error/error_logger.php');
                       	        logDatabaseError("Error fetching units: " . $e->getMessage(), [
                       	            'operation_name' => 'search_user_units_load',
                       	            'unit_id' => null,
                       	            'val_wf_id' => null,
                       	            'equip_id' => null
                       	        ]);
                       	    }
                       	}
                       	else {
                           try {
                               $unit_name = DB::queryFirstField("SELECT unit_name FROM units WHERE unit_id = %i and unit_status='Active'", intval($_SESSION['unit_id']));
                               
                               echo "<option value='" . htmlspecialchars($_SESSION['unit_id'], ENT_QUOTES, 'UTF-8') . "'>" . htmlspecialchars($unit_name, ENT_QUOTES, 'UTF-8') . "</option>";
                           } catch (Exception $e) {
                               require_once('core/error/error_logger.php');
                               logDatabaseError("Error fetching unit name: " . $e->getMessage(), [
                                   'operation_name' => 'search_user_unit_name_query',
                                   'unit_id' => intval($_SESSION['unit_id']),
                                   'val_wf_id' => null,
                                   'equip_id' => null
                               ]);
                           }
                       	}
                       	?>	
                        </select>
                      </div>

                <div class="form-group  col-md-6">
                <label for="exampleSelectGender">User Type</label>
                <select class="form-control" id="user_type" name="user_type">
                          <option value='IE'>Employee</option>
                          <option value='VE'>Vendor Employee</option>
  </select>
  </div>

<div id="showVE" class="myDiv form-group  col-md-6" style="display:none;">
                        <label for="exampleSelectGender">Vendor</label>
                        
                    	  <select class="form-control" id="vendor_id" name="vendor_id" <?php echo (isset($_GET['u']) && $_GET['u']=='c') ? "disabled":""; ?>>
									
										<option value='select'>Select </option>
                       	<?php 
                       try {
                       	    $vendor_details=DB::query("SELECT vendor_id, vendor_name FROM vendors WHERE vendor_status='Active' ORDER BY vendor_name ASC");
                       	    if(!empty($vendor_details))
                       	    {
                       	        foreach ($vendor_details as $vendor){
                       	            echo "<option value='" . htmlspecialchars($vendor['vendor_id'], ENT_QUOTES, 'UTF-8') . "'>" . htmlspecialchars($vendor['vendor_name'], ENT_QUOTES, 'UTF-8') . "</option>";
                       	        }
                       	    }
                       } catch (Exception $e) {
                           error_log("Error fetching vendor details: " . $e->getMessage());
                       }
                       	
                       	    
                       
                       	?>	
                        </select>
                    
                    
                    
                      </div>
                    
                    
                    
                    
                    
                    
                     
                      
  </div>                    
                       <div class="form-row">
                      <div class="form-group col-md-6">
                        <label for="wfstageid">Search Crietria</label>
                        <select class="form-control" id="search_criteria" name="search_criteria">
                         
                          <option value='0'>Employee Name</option>
                          <option value='1'>Employee ID</option>
                          
                       
                        </select>
                      </div>
                       <div class="form-group col-md-6">
                        <label for="planned_start_from">Input</label>
                        <input type="Text" class="form-control" id="search_input" name="search_input"/>
                      </div>
                      </div>
                      
                     
        
     
  
            
        
               
                      
                      
                      <input type="submit" id="searchusers" class="btn btn-gradient-original-success mr-2"/>
                      
                    </form>
                  </div>
                </div>
              </div>
            
            <div class="col-12 grid-margin stretch-card">
                <div class="card">
                  <div class="card-body">
                <h4 class="card-title">Result</h4>
                    
                    <div class="table-responsive-xl">
                <div id="displayresults"> <p class="card-description"> Select the criteria and hit the Submit button. </p></div>
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
