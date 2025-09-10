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
    
    
function fetchEquipments(unitid) {
  
     $('#equipment_id').empty();
     
     // Add "All" option as default
     $('#equipment_id').append('<option value="All" selected>All</option>');
    
     $.get("core/data/get/getequipmentdetailsformaster.php",
                      {
                      	
                        unit_id: unitid
                        
                      },
                      function(data, status){
                     
                         $('#equipment_id').append(data);
                      });
  
  
  }
  
  
$('#unitid').change(function() { 
 var item=$(this);
    
    fetchEquipments(item.val());

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
					 $.get("core/data/get/getequipmentdetails.php",
                      {
                        unitid: $("#unitid").val(),
                        dept_id: $("#dept_id").val(),
                        equipment_type: $("#equipment_type").val(),
                        equipment_id:$("#equipment_id").val(),
                        etv_mapping_filter: $("#etv_mapping_filter").val()
                        
                      },
                      function(data, status){
                      $('#pleasewaitmodal').modal('hide');
                     $("#displayresults").html(data);
                     
                     // Small delay to ensure DOM is ready, then initialize modern DataTable
                     setTimeout(function() {
                         // Destroy existing DataTable if it exists
                         if ($.fn.DataTable.isDataTable('#tbl-equip-details')) {
                             $('#tbl-equip-details').DataTable().destroy();
                         }
                         
                         // Initialize modern DataTable with enhanced features
                         $('#tbl-equip-details').DataTable({
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
                                 "search": "Search equipments:",
                                 "lengthMenu": "Show _MENU_ entries",
                                 "info": "Showing _START_ to _END_ of _TOTAL_ equipments"
                             }
                         });
                     }, 100); // 100ms delay
                       
                      });
 
		}
		
		
		
		
		





}));















    
    // Equipment Statistics Overview functionality
      function loadEquipmentStatistics() {
        var unitId = $('#stats_unit_selector').val();
        
        console.log('Loading equipment statistics for unit:', unitId);
        
        // Make AJAX call to fetch equipment statistics
        $.get("core/data/get/getequipmentstats.php", {
          unit_id: unitId
        })
        .done(function(response) {
          console.log('Equipment statistics response:', response);
          try {
            var stats = JSON.parse(response);
            $('#active_equipments_count').text(stats.active_equipments || 0);
            $('#inactive_equipments_count').text(stats.inactive_equipments || 0);
            $('#no_etv_mapping_count').text(stats.no_etv_mapping || 0);
          } catch(e) {
            console.log('Error parsing equipment statistics response:', e);
            console.log('Raw response:', response);
          }
        })
        .fail(function(xhr, status, error) {
          console.log('Failed to fetch equipment statistics:', error);
          console.log('Status:', status);
          console.log('Response:', xhr.responseText);
        });
      }
      
      $('#stats_unit_selector').on('change', function(){
        loadEquipmentStatistics();
      });
      
      // Load initial equipment statistics on page load
      loadEquipmentStatistics();

    
    });
    
    
    
    
    </script>
    
    <style>
    /* Reduce Equipment Statistics tile height to 70% */
    .equipment-stats-tile {
        height: 70% !important;
        min-height: 120px !important;
    }
    
    .equipment-stats-tile .card-body {
        padding: 15px 15px 8px 15px !important;
    }
    
    .equipment-stats-tile .display-1 {
        font-size: 2rem !important;
        margin-bottom: 5px !important;
    }
    
    .equipment-stats-tile h4 {
        font-size: 0.9rem !important;
        margin-bottom: 8px !important;
    }
    
    .equipment-stats-tile .card-text {
        font-size: 0.8rem !important;
        margin-bottom: 0 !important;
    }
    
    /* Reduce space around tiles by 50% */
    .equipment-stats-container {
        margin-top: -15px !important;
        margin-bottom: -15px !important;
        padding-bottom: 5px !important;
    }
    
    .equipment-stats-container .grid-margin {
        margin-bottom: 10px !important;
    }
    
    /* Reduce space between dropdown and tiles */
    .equipment-stats-container .form-row.mb-2 {
        margin-bottom: 10px !important;
    }
    
    /* Modern DataTable Styling */
    #tbl-equip-details {
        border-collapse: separate;
        border-spacing: 0;
        border-radius: 8px !important;
        overflow: hidden;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }
    
    #tbl-equip-details thead th {
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
    
    #tbl-equip-details thead th:first-child {
        border-top-left-radius: 8px;
    }
    
    #tbl-equip-details thead th:last-child {
        border-top-right-radius: 8px;
    }
    
    #tbl-equip-details tbody td {
        padding: 12px !important;
        vertical-align: middle !important;
        border-bottom: 1px solid #e3e6f0 !important;
        transition: all 0.3s ease !important;
    }
    
    #tbl-equip-details tbody td:last-child {
        text-align: center !important;
    }
    
    #tbl-equip-details tbody tr {
        transition: all 0.3s ease !important;
    }
    
    #tbl-equip-details tbody tr:hover {
        background-color: #f8f9fe !important;
        transform: scale(1.02) !important;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1) !important;
    }
    
    #tbl-equip-details tbody tr:nth-child(even) {
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
    #tbl-equip-details .btn {
        border-radius: 20px !important;
        font-size: 0.8rem !important;
        padding: 6px 14px !important;
        font-weight: 500 !important;
        transition: all 0.3s ease !important;
        text-transform: uppercase !important;
        letter-spacing: 0.5px !important;
    }
    
    #tbl-equip-details .btn:hover {
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
        
        #tbl-equip-details tbody tr:hover {
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
              <h3 class="page-title"> Search Equipments</h3>
              <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                  <li class="breadcrumb-item"><a class='btn btn-gradient-info btn-sm btn-rounded' href="manageequipmentdetails.php?m=a">+ Add Equipment</a></li>
                </ol>
              </nav>
            </div>
            
            
            <div class="row">
            
            <!-- Equipment Statistics Overview Card -->
            <div class="col-12 grid-margin stretch-card">
                <div class="card">
                  <div class="card-body">
                    <h4 class="card-title">Equipment Statistics Overview</h4>
                    
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
                    <div class="row equipment-stats-container">
                        <div class="col-12 col-sm-6 col-md-4 stretch-card grid-margin">
                            <div class="card bg-gradient-success card-img-holder text-white equipment-stats-tile">
                                <div class="card-body">
                                    <img src="assets/images/dashboard/circle.svg" class="card-img-absolute" alt="circle-image" />
                                    <h4 class="font-weight-normal mb-3">
                                        Active Equipments
                                        <i class="mdi mdi-check-circle mdi-24px float-right"></i>
                                    </h4>
                                    <h2 class="mb-5 display-1" id="active_equipments_count">0</h2>
                                    <h6 class="card-text">Currently active</h6>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-12 col-sm-6 col-md-4 stretch-card grid-margin">
                            <div class="card bg-gradient-dark card-img-holder text-white equipment-stats-tile">
                                <div class="card-body">
                                    <img src="assets/images/dashboard/circle.svg" class="card-img-absolute" alt="circle-image" />
                                    <h4 class="font-weight-normal mb-3">
                                        Inactive Equipments
                                        <i class="mdi mdi-close-circle mdi-24px float-right"></i>
                                    </h4>
                                    <h2 class="mb-5 display-1" id="inactive_equipments_count">0</h2>
                                    <h6 class="card-text">Not active</h6>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-12 col-sm-6 col-md-4 stretch-card grid-margin">
                            <div class="card bg-gradient-warning card-img-holder text-white equipment-stats-tile">
                                <div class="card-body">
                                    <img src="assets/images/dashboard/circle.svg" class="card-img-absolute" alt="circle-image" />
                                    <h4 class="font-weight-normal mb-3">
                                        Equipment without ETV Mapping
                                        <i class="mdi mdi-link-off mdi-24px float-right"></i>
                                    </h4>
                                    <h2 class="mb-5 display-1" id="no_etv_mapping_count">0</h2>
                                    <h6 class="card-text">No ETV mapping</h6>
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
                    
 
						
					/*	if ($_SESSION['is_super_admin']=="Yes")
                       	{
                       	    $results = DB::query("select unit_id, unit_name from units");
                       	    
                       	    
                       	    if(!empty($results))
                       	    {
                       	        foreach ($results as $row) {
                       	            
                       	            $output=$output. "<option value='".$row['unit_id']."'>".$row['unit_name']."</option>";
                       	            
                       	        }
                       	        
                       	        echo $output;
                       	        
                       	    }
                       	    
                       	    
                       	    
                       	    
                       	}
                       	else {
                       	    echo "<option>".$_SESSION['unit_id']."</option>";
                       	}
						
						*/
                       	?>	
                        </select>
                      </div>
                    
                    <div class="form-group  col-md-6">
                        <label for="exampleSelectGender">Department</label>
                        <select class="form-control" id="dept_id" name="dept_id">
                          <option>Select</option>
                        <option value='0'>Quality Control</option>";
                       	<option value='1'>Engineering</option>";
                       	<option value='2'>Production</option>";
                       	<option value='3'>Packing</option>";
                       	<option value='4'>Stores</option>";
                       	<option value='5'>Topical</option>";
                       	<option value='6'>Micro</option>";
                       	<option value='7'>EHS</option>";
                       	<option value='8'>Quality assurance</option>";
                       	<option value='9'>Heads</option>";
                       	
                    
                        </select>
                      </div>
                    
                    
                     
  </div>                    
                       <div class="form-row">
                      <div class="form-group col-md-6">
                        <label for="wfstageid">Equipment Type</label>
                        <select class="form-control" id="equipment_type" name="equipment_type">
                         <option>Select</option>
                          <option value='0'>AHU</option>
                          <option value='1'>Ventilation Unit</option>
                          
                       
                        </select>
                      </div>
                       
                       
                       
                         <div class="form-group  col-md-6">
                        <label for="exampleSelectGender">Equipment Code</label>
                        
                    	  <select class="form-control" id="equipment_id" name="equipment_id">
									
										<option value='All' selected>All</option>
                       	
                        </select>
                       
                       </div>
                       
                       </div>
                       
                       <div class="form-row">
                         <div class="form-group  col-md-6">
                        <label for="etv_mapping_filter">Equipment without ETV Mappings</label>
                        
                    	  <select class="form-control" id="etv_mapping_filter" name="etv_mapping_filter">
									
										<option value='Select'>Select</option>
										<option value='Yes'>Yes</option>
										<option value='No'>No</option>
                       	
                        </select>
                       
                       </div>
                       
                       
                       
                       
                       
                       
                       
                      </div>
                      
                     
        
     
  
            
        
               
                      
                      
                      <input type="submit" id="searchusers" class="btn btn-gradient-primary mr-2"/>
                      
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

