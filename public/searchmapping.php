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
        name = name.replace(/[\\[]/, '\\\\[').replace(/[\\]]/, '\\\\]');
        var regex = new RegExp('[\\\\?&]' + name + '=([^&#]*)');
        var results = regex.exec(location.search);
        return results === null ? '' : decodeURIComponent(results[1].replace(/\\+/g, ' '));
    }

    // Function to restore search state from URL parameters
    function restoreSearchState() {
        const restoreFlag = getUrlParameter('restore_search');
        if (restoreFlag === '1') {
            console.log('Restoring mapping search state from URL parameters');

            // Restore form values
            const unitid = getUrlParameter('unitid');
            const deptId = getUrlParameter('dept_id');
            const equipmentType = getUrlParameter('equipment_type');
            const equipmentId = getUrlParameter('equipment_id');
            const etvMappingFilter = getUrlParameter('etv_mapping_filter');

            if (unitid && unitid !== '') {
                $('#unitid').val(unitid);
                // Trigger change to load equipments for this unit
                $('#unitid').trigger('change');
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

            // Set equipment_id after equipments are loaded
            if (equipmentId && equipmentId !== '') {
                setTimeout(function() {
                    $('#equipment_id').val(equipmentId);
                }, 1000); // Wait for equipments to load
            }

            // Auto-submit the form to show results
            setTimeout(function() {
                console.log('Auto-submitting restored mapping search');

                // Show a brief notification that search is being restored
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'Restoring Search Results',
                        text: 'Taking you back to your previous mapping search...',
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
            }, 1500); // Longer delay to allow equipment dropdown to load
        }
    }

    // Call restore function on page load
    restoreSearchState();


function fetchEquipments(unitid) {
  
     $('#equipment_id').empty();
    
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
					 $.get("core/data/get/getmappingdetails.php",
                      {
                        unitid: $("#unitid").val(),
                        equipment_id: $("#equipment_id").val()
                       
                        
                      },
                      function(data, status){
                     $('#pleasewaitmodal').modal('hide');
                            $("#displayresults").html(data);
                            
                            // Small delay to ensure DOM is ready, then initialize modern DataTable
                            setTimeout(function() {
                                // Destroy existing DataTable if it exists
                                if ($.fn.DataTable.isDataTable('#tbl-mapping-details')) {
                                    $('#tbl-mapping-details').DataTable().destroy();
                                }
                                
                                // Initialize modern DataTable with enhanced features
                                $('#tbl-mapping-details').DataTable({
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
                                        "search": "Search mappings:",
                                        "lengthMenu": "Show _MENU_ entries",
                                        "info": "Showing _START_ to _END_ of _TOTAL_ mappings"
                                    }
                                });

                                // Smooth scroll to results section when coming back from mapping details
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
		
		
		
		





    // ETV Mapping Statistics Overview functionality
    function loadMappingStatistics() {
      var unitId = $('#stats_unit_selector').val();
      
      console.log('=== DEBUGGING ETV MAPPING STATS ===');
      console.log('Unit selector element exists:', $('#stats_unit_selector').length > 0);
      console.log('Selected unit ID:', unitId);
      console.log('Loading ETV mapping statistics for unit:', unitId);
      
      // Make AJAX call to fetch ETV mapping statistics
      $.get("core/data/get/getmappingstats.php", {
        unit_id: unitId
      })
      .done(function(response) {
        console.log('SUCCESS: ETV mapping statistics response received');
        console.log('Raw response:', response);
        try {
          var stats = JSON.parse(response);
          console.log('Parsed stats:', stats);
          
          // Update the counts and log each update
          $('#active_mappings_count').text(stats.active_mappings || 0);
          console.log('Updated active_mappings_count to:', stats.active_mappings || 0);
          
          $('#inactive_mappings_count').text(stats.inactive_mappings || 0);
          console.log('Updated inactive_mappings_count to:', stats.inactive_mappings || 0);
          
          $('#total_equipments_count').text(stats.total_equipments || 0);
          console.log('Updated total_equipments_count to:', stats.total_equipments || 0);
          
          $('#unmapped_equipments_count').text(stats.unmapped_equipments || 0);
          console.log('Updated unmapped_equipments_count to:', stats.unmapped_equipments || 0);
          
        } catch(e) {
          console.log('ERROR: Error parsing ETV mapping statistics response:', e);
          console.log('Raw response that failed to parse:', response);
        }
      })
      .fail(function(xhr, status, error) {
        console.log('FAIL: Failed to fetch ETV mapping statistics');
        console.log('Error:', error);
        console.log('Status:', status);
        console.log('Response text:', xhr.responseText);
        console.log('Status code:', xhr.status);
      });
    }
    
    $('#stats_unit_selector').on('change', function(){
      console.log('Unit selector changed, loading new statistics...');
      loadMappingStatistics();
    });
    
    // Load initial ETV mapping statistics on page load
    console.log('Document ready - checking for DOM elements');
    console.log('stats_unit_selector exists:', $('#stats_unit_selector').length);
    console.log('active_mappings_count exists:', $('#active_mappings_count').length);
    console.log('inactive_mappings_count exists:', $('#inactive_mappings_count').length);
    console.log('total_equipments_count exists:', $('#total_equipments_count').length);
    console.log('unmapped_equipments_count exists:', $('#unmapped_equipments_count').length);
    
    loadMappingStatistics();

});















    
    </script>

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
    </style>

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
              <h3 class="page-title"> Search Equipment-Test-Vendor Mapping</h3>
              <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                  <li class="breadcrumb-item"><a class='btn btn-gradient-info btn-sm btn-rounded' href="managemappingdetails.php?m=a">+ Add ETV Mapping</a></li>
                </ol>
              </nav>
            </div>
            
            
            <div class="row">
            
            <!-- ETV Mapping Statistics Overview Card -->
            <div class="col-12 grid-margin stretch-card">
                <div class="card">
                  <div class="card-body">
                    <h4 class="card-title">ETV Mapping Statistics Overview</h4>
                    
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
                    <div class="row mapping-stats-container">
                        <div class="col-12 col-sm-6 col-md-3 stretch-card grid-margin">
                            <div class="card bg-gradient-success card-img-holder text-white mapping-stats-tile">
                                <div class="card-body">
                                    <img src="assets/images/dashboard/circle.svg" class="card-img-absolute" alt="circle-image" />
                                    <h4 class="font-weight-normal mb-3">
                                        Active ETV Mappings
                                        <i class="mdi mdi-link-variant mdi-24px float-right"></i>
                                    </h4>
                                    <h2 class="mb-5 display-1" id="active_mappings_count">0</h2>
                                    <h6 class="card-text">Currently active</h6>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-12 col-sm-6 col-md-3 stretch-card grid-margin">
                            <div class="card bg-gradient-dark card-img-holder text-white mapping-stats-tile">
                                <div class="card-body">
                                    <img src="assets/images/dashboard/circle.svg" class="card-img-absolute" alt="circle-image" />
                                    <h4 class="font-weight-normal mb-3">
                                        Inactive ETV Mappings
                                        <i class="mdi mdi-link-off mdi-24px float-right"></i>
                                    </h4>
                                    <h2 class="mb-5 display-1" id="inactive_mappings_count">0</h2>
                                    <h6 class="card-text">Not active</h6>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-12 col-sm-6 col-md-3 stretch-card grid-margin">
                            <div class="card bg-gradient-primary card-img-holder text-white mapping-stats-tile">
                                <div class="card-body">
                                    <img src="assets/images/dashboard/circle.svg" class="card-img-absolute" alt="circle-image" />
                                    <h4 class="font-weight-normal mb-3">
                                        Total Equipments
                                        <i class="mdi mdi-wrench mdi-24px float-right"></i>
                                    </h4>
                                    <h2 class="mb-5 display-1" id="total_equipments_count">0</h2>
                                    <h6 class="card-text">All equipments</h6>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-12 col-sm-6 col-md-3 stretch-card grid-margin">
                            <div class="card bg-gradient-warning card-img-holder text-white mapping-stats-tile">
                                <div class="card-body">
                                    <img src="assets/images/dashboard/circle.svg" class="card-img-absolute" alt="circle-image" />
                                    <h4 class="font-weight-normal mb-3">
                                        Unmapped Equipments
                                        <i class="mdi mdi-alert-circle mdi-24px float-right"></i>
                                    </h4>
                                    <h2 class="mb-5 display-1" id="unmapped_equipments_count">0</h2>
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
                       	            $output = "";
                       	            foreach ($results as $row) {
                       	                $output=$output. "<option value='" . htmlspecialchars($row['unit_id'], ENT_QUOTES, 'UTF-8') . "'>" . htmlspecialchars($row['unit_name'], ENT_QUOTES, 'UTF-8') . "</option>";
                       	            }
                       	            
                       	            echo $output;
                       	        }
                       	    } catch (Exception $e) {
                       	        require_once('core/error/error_logger.php');
                       	        logDatabaseError("Error fetching units: " . $e->getMessage(), [
                       	            'operation_name' => 'search_mapping_units_load',
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
                       	            'operation_name' => 'search_mapping_unit_name_query',
                       	            'unit_id' => intval($_SESSION['unit_id']),
                       	            'val_wf_id' => null,
                       	            'equip_id' => null
                       	        ]);
                       	    }
                       	}
                    
 
						
						/*
						if ($_SESSION['is_super_admin']=="Yes")
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
                        <label for="exampleSelectGender">Equipment Code</label>
                        
                    	  <select class="form-control" id="equipment_id" name="equipment_id">
									
										<option value='select'>Select </option>
                       	
                        </select>
                     
                    
                    
                    
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
                    <div id="displayresults"><p class="card-description"> Select the criteria and hit the Submit button. </p></div>
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
