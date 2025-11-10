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

    // Function to get URL parameters
    function getUrlParameter(name) {
        name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
        var regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
        var results = regex.exec(location.search);
        return results === null ? '' : decodeURIComponent(results[1].replace(/\+/g, ' '));
    }

    // Function to restore search state from URL parameters
    function restoreSearchState() {
        const restoreFlag = getUrlParameter('restore_search');
        if (restoreFlag === '1') {
            console.log('Restoring search state from URL parameters');

            // Restore form values
            const unitid = getUrlParameter('unitid');
            const deptId = getUrlParameter('dept_id');
            const equipmentType = getUrlParameter('equipment_type');
            const equipmentId = getUrlParameter('equipment_id');
            const etvMappingFilter = getUrlParameter('etv_mapping_filter');

            if (unitid && unitid !== '') {
                $('#unitid').val(unitid);
                // Trigger change to populate equipment dropdown
                fetchEquipments(unitid);
            }

            if (deptId && deptId !== '') {
                $('#dept_id').val(deptId);
            }

            if (equipmentType && equipmentType !== '') {
                $('#equipment_type').val(equipmentType);
            }

            if (etvMappingFilter && etvMappingFilter !== '') {
                $('#etv_mapping_filter').val(etvMappingFilter);
            }

            // Set equipment ID after a delay to ensure dropdown is populated
            setTimeout(function() {
                if (equipmentId && equipmentId !== '') {
                    $('#equipment_id').val(equipmentId);
                }

                // Auto-submit the form to show results
                setTimeout(function() {
                    console.log('Auto-submitting restored search');

                    // Show a brief notification that search is being restored
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            title: 'Restoring Search Results',
                            text: 'Taking you back to your previous search...',
                            icon: 'info',
                            timer: 1500,
                            timerProgressBar: true,
                            showConfirmButton: false,
                            toast: true,
                            position: 'top-end'
                        });
                    }

                    // Show loading indicator
                    $('#pleasewaitmodal').modal('show');
                    $('#formreport').submit();

                    // Clean up URL parameters after search is restored
                    setTimeout(function() {
                        const cleanUrl = window.location.pathname;
                        window.history.replaceState({}, document.title, cleanUrl);
                    }, 2000);
                }, 500);
            }, 1000);
        }
    }

    // Call restore function on page load
    restoreSearchState();

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

                         // Highlight previously viewed equipment if coming back from details
                         const viewedEquipId = getUrlParameter('viewed_equip_id');
                         if (viewedEquipId) {
                             setTimeout(function() {
                                 $('a[href*="equip_id=' + viewedEquipId + '"]').closest('tr').addClass('table-warning');
                             }, 200);
                         }

                         // Smooth scroll to results section when coming back from equipment details
                         const restoreFlag = getUrlParameter('restore_search');
                         if (restoreFlag === '1') {
                             setTimeout(function() {
                                 const resultsSection = $('#displayresults');
                                 if (resultsSection.length && resultsSection.is(':visible')) {
                                     $('html, body').animate({
                                         scrollTop: resultsSection.offset().top - 100
                                     }, 800, 'swing', function() {
                                         // Add a subtle highlight effect to the results area
                                         resultsSection.addClass('highlight-results');
                                         setTimeout(function() {
                                             resultsSection.removeClass('highlight-results');
                                         }, 2000);
                                     });
                                 }
                             }, 300);
                         }
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
    
    <link rel="stylesheet" href="assets/css/modern-manage-ui.css">

    <style>
    /* Enhanced search results highlight animation */
    .highlight-results {
        animation: gentle-glow 2s ease-in-out;
        border-radius: 8px;
    }

    @keyframes gentle-glow {
        0% {
            box-shadow: 0 0 5px rgba(0, 123, 255, 0.3);
            background-color: rgba(0, 123, 255, 0.05);
        }
        50% {
            box-shadow: 0 0 20px rgba(0, 123, 255, 0.4);
            background-color: rgba(0, 123, 255, 0.08);
        }
        100% {
            box-shadow: 0 0 5px rgba(0, 123, 255, 0.1);
            background-color: transparent;
        }
    }

    /* Smooth scroll behavior */
    html {
        scroll-behavior: smooth;
    }

    /* Enhanced table row highlighting for previously viewed equipment */
    .table-warning {
        background-color: rgba(255, 193, 7, 0.15) !important;
        border-left: 4px solid #ffc107;
        animation: highlight-fade 3s ease-in-out;
    }

    @keyframes highlight-fade {
        0% {
            background-color: rgba(255, 193, 7, 0.3) !important;
        }
        100% {
            background-color: rgba(255, 193, 7, 0.15) !important;
        }
    }

    /* Fix vertical scrollbar on equipment stats cards */
    .equipment-stats-tile {
        overflow: hidden !important;
    }

    .equipment-stats-tile .card-body {
        overflow: hidden !important;
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
                  <li class="breadcrumb-item"><a class='btn btn-gradient-info btn-sm btn-rounded' href="manageequipmentdetails.php?m=a"><i class="mdi mdi-plus-circle"></i> Add Equipment</a></li>
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
                        <div class="form-group col-md-12">
                            <label for="stats_unit_selector">Select Unit:</label>
                            <select class="form-control" id="stats_unit_selector" name="stats_unit_selector">
                            <?php 
                            try {
                                if ($_SESSION['is_super_admin']=="Yes") {
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
                    <div class="row equipment-stats-container">
                        <div class="col-12 col-sm-6 col-md-3 stretch-card grid-margin">
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
                        
                        <div class="col-12 col-sm-6 col-md-3 stretch-card grid-margin">
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
                        
                        <div class="col-12 col-sm-6 col-md-3 stretch-card grid-margin">
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
                       	        $results = DB::query("SELECT unit_id, unit_name FROM units where unit_status='Active' ORDER BY unit_name ASC");
                       	        
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
                       	        $unit_name = DB::queryFirstField("SELECT unit_name FROM units WHERE unit_id = %i and unit_status='Active'", intval($_SESSION['unit_id']));
                       	        
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
                      
                     
        
     
  
            
        
               
                      
                      
                      <button type="submit" id="searchusers" class="btn btn-gradient-primary btn-icon-text">
                        <i class="mdi mdi-magnify"></i> Search Equipments
                      </button>
                      
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
	<div class="text-center text-muted py-4">
                        <i class="mdi mdi-filter-variant icon-lg mb-2"></i>
                        <p> Use the search filters above to find equipments</p>
                      </div>
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

