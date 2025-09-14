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

// Get units for dropdown
try {
    $units = DB::query("SELECT unit_id, unit_name FROM units WHERE unit_status = 'Active' ORDER BY unit_name");
} catch (Exception $e) {
    error_log("Error loading units: " . $e->getMessage());
    $units = [];
}

?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include_once "assets/inc/_header.php";?>
    <meta name="csrf-token" content="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
    <script>
    $(document).ready(function(){
        
        // Function to fetch equipments based on unit selection
        function fetchEquipments(unitid) {
            $('#equipment_id').empty();
            
            $.get("core/data/get/getequipmentdetailsformaster.php", {
                unit_id: unitid
            }, function(data, status){
                $('#equipment_id').append(data);
            });
        }
        
        // Function to load ERF mapping statistics
        function loadERFMappingStatistics() {
            console.log('Loading ERF mapping statistics...');
            
            $.get("core/data/get/geterfmappingstats.php")
            .done(function(response) {
                console.log('ERF mapping statistics response received');
                try {
                    // Response is already parsed by jQuery when Content-Type is application/json
                    var stats = response;
                    console.log('ERF mapping stats:', stats);
                    
                    // Update the counts
                    $('#active_mappings_count').text(stats.active_mappings || 0);
                    $('#inactive_mappings_count').text(stats.inactive_mappings || 0);
                    $('#total_equipments_count').text(stats.total_equipments || 0);
                    $('#unmapped_equipments_count').text(stats.unmapped_equipments || 0);
                    $('#total_rooms_count').text(stats.total_rooms || 0);
                    $('#unmapped_rooms_count').text(stats.unmapped_rooms || 0);
                    
                } catch(e) {
                    console.log('Error processing ERF mapping statistics response:', e);
                }
            })
            .fail(function(xhr, status, error) {
                console.log('Failed to fetch ERF mapping statistics:', error);
            });
        }
        
        // Load statistics on page load
        loadERFMappingStatistics();
        
        // Unit change event
        $('#unitid').change(function() { 
            var item = $(this);
            fetchEquipments(item.val());
        });
        
        // Form submission
        $("#formreport").on('submit', function(e) {
            e.preventDefault();
            
            if($("#unitid").val() == 'Select'){
                Swal.fire({
                    icon: 'error',
                    title: 'Oops...',
                    text: 'Please select unit.'                
                });
                return;
            }
            
            $('#pleasewaitmodal').modal('show');
            $.get("core/data/get/geterfmappingdetails.php", {
                unitid: $("#unitid").val(),
                equipment_id: $("#equipment_id").val(),
                room_loc_id: $("#room_loc_id").val(),
                mapping_status: $("#mapping_status").val()
            }, function(data, status){
                $('#pleasewaitmodal').modal('hide');
                $("#displayresults").html(data);
                
                // Small delay to ensure DOM is ready, then initialize DataTable
                setTimeout(function() {
                    // Check if table exists and has the correct structure
                    var tableElement = $('#tbl-erf-mapping-details');
                    if (tableElement.length > 0) {
                        // Destroy existing DataTable if it exists
                        if ($.fn.DataTable.isDataTable('#tbl-erf-mapping-details')) {
                            $('#tbl-erf-mapping-details').DataTable().destroy();
                        }
                        
                        // Verify table has proper thead and tbody structure
                        if (tableElement.length > 0) {
                            try {
                                // Initialize DataTable with simple configuration (based on searchmapping.php)
                                $('#tbl-erf-mapping-details').DataTable({
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
                                        "search": "Search ERF mappings:",
                                        "lengthMenu": "Show _MENU_ entries",
                                        "info": "Showing _START_ to _END_ of _TOTAL_ ERF mappings"
                                    }
                                });
                            } catch (e) {
                                console.error('DataTable initialization failed:', e);
                            }
                        }
                    }
                }, 200);
            });
        });
        
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
              <h3 class="page-title"> Search ERF Mappings</h3>
              <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                  <li class="breadcrumb-item"><a class='btn btn-gradient-info btn-sm btn-rounded' href="manageerfmappingdetails.php?m=a">+ Add ERF Mapping</a></li>
                </ol>
              </nav>
            </div>
            
            <div class="row">
            
            <!-- ERF Mapping Statistics Overview Card -->
            <div class="col-12 grid-margin stretch-card">
                <div class="card">
                  <div class="card-body">
                    <h4 class="card-title">ERF Mapping Statistics Overview</h4>
                    <p class="card-description">Equipment Room Filter mapping analytics</p>
                    
                    <!-- ERF Statistics Tiles -->
                    <div class="row erf-stats-container">
                        <div class="col-12 col-sm-6 col-md-4 stretch-card grid-margin">
                            <div class="card bg-gradient-success card-img-holder text-white erf-stats-tile">
                                <div class="card-body">
                                    <img src="assets/images/dashboard/circle.svg" class="card-img-absolute" alt="circle-image" />
                                    <h4 class="font-weight-normal mb-3">
                                        Active Mappings
                                        <i class="mdi mdi-link-variant mdi-24px float-right"></i>
                                    </h4>
                                    <h2 class="mb-5 display-1" id="active_mappings_count">0</h2>
                                    <h6 class="card-text">Currently active</h6>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-12 col-sm-6 col-md-4 stretch-card grid-margin">
                            <div class="card bg-gradient-dark card-img-holder text-white erf-stats-tile">
                                <div class="card-body">
                                    <img src="assets/images/dashboard/circle.svg" class="card-img-absolute" alt="circle-image" />
                                    <h4 class="font-weight-normal mb-3">
                                        Inactive Mappings
                                        <i class="mdi mdi-link-off mdi-24px float-right"></i>
                                    </h4>
                                    <h2 class="mb-5 display-1" id="inactive_mappings_count">0</h2>
                                    <h6 class="card-text">Deactivated</h6>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-12 col-sm-6 col-md-4 stretch-card grid-margin">
                            <div class="card bg-gradient-primary card-img-holder text-white erf-stats-tile">
                                <div class="card-body">
                                    <img src="assets/images/dashboard/circle.svg" class="card-img-absolute" alt="circle-image" />
                                    <h4 class="font-weight-normal mb-3">
                                        Mapped Equipments
                                        <i class="mdi mdi-wrench mdi-24px float-right"></i>
                                    </h4>
                                    <h2 class="mb-5 display-1" id="total_equipments_count">0</h2>
                                    <h6 class="card-text">With ERF mappings</h6>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-12 col-sm-6 col-md-4 stretch-card grid-margin">
                            <div class="card bg-gradient-warning card-img-holder text-white erf-stats-tile">
                                <div class="card-body">
                                    <img src="assets/images/dashboard/circle.svg" class="card-img-absolute" alt="circle-image" />
                                    <h4 class="font-weight-normal mb-3">
                                        Unmapped Equipments
                                        <i class="mdi mdi-alert-circle mdi-24px float-right"></i>
                                    </h4>
                                    <h2 class="mb-5 display-1" id="unmapped_equipments_count">0</h2>
                                    <h6 class="card-text">Require mapping</h6>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-12 col-sm-6 col-md-4 stretch-card grid-margin">
                            <div class="card bg-gradient-info card-img-holder text-white erf-stats-tile">
                                <div class="card-body">
                                    <img src="assets/images/dashboard/circle.svg" class="card-img-absolute" alt="circle-image" />
                                    <h4 class="font-weight-normal mb-3">
                                        Mapped Rooms
                                        <i class="mdi mdi-home-map-marker mdi-24px float-right"></i>
                                    </h4>
                                    <h2 class="mb-5 display-1" id="total_rooms_count">0</h2>
                                    <h6 class="card-text">With ERF mappings</h6>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-12 col-sm-6 col-md-4 stretch-card grid-margin">
                            <div class="card bg-gradient-danger card-img-holder text-white erf-stats-tile">
                                <div class="card-body">
                                    <img src="assets/images/dashboard/circle.svg" class="card-img-absolute" alt="circle-image" />
                                    <h4 class="font-weight-normal mb-3">
                                        Unmapped Rooms
                                        <i class="mdi mdi-home-alert mdi-24px float-right"></i>
                                    </h4>
                                    <h2 class="mb-5 display-1" id="unmapped_rooms_count">0</h2>
                                    <h6 class="card-text">Available rooms</h6>
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
                      <div class="form-group col-md-6">
                        <label for="unitid">Unit</label>
                        <select class="form-control" id="unitid" name="unitid">
                          <option value="Select">Select Unit</option>
                          <?php foreach ($units as $unit): ?>
                            <option value="<?php echo $unit['unit_id']; ?>">
                              <?php echo htmlspecialchars($unit['unit_name'], ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                          <?php endforeach; ?>
                        </select>
                      </div>
                      <div class="form-group col-md-6">
                        <label for="equipment_id">Equipment</label>
                        <select class="form-control" id="equipment_id" name="equipment_id">
                          <option value="Select">Select Equipment</option>
                        </select>
                      </div>
                          </div>
                          <div class="form-row">
                      <div class="form-group col-md-6">
                        <label for="room_loc_id">Room/Location</label>
                        <select class="form-control" id="room_loc_id" name="room_loc_id">
                          <option value="Select">All Rooms</option>
                          <?php 
                          try {
                              $rooms = DB::query("SELECT room_loc_id, room_loc_name FROM room_locations ORDER BY room_loc_name");
                              foreach ($rooms as $room): 
                          ?>
                            <option value="<?php echo $room['room_loc_id']; ?>">
                              <?php echo htmlspecialchars($room['room_loc_name'], ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                          <?php 
                              endforeach;
                          } catch (Exception $e) {
                              // Handle error silently
                          }
                          ?>
                        </select>
                      </div>
                      <div class="form-group col-md-6">
                        <label for="mapping_status">Status</label>
                        <select class="form-control" id="mapping_status" name="mapping_status">
                          <option value="Select">All Status</option>
                          <option value="0">Active</option>
                          <option value="1">Inactive</option>
                        </select>
                      </div>
                      </div>
                      
                      <div class="form-row">
                        <div class="form-group col-md-12">
                          <input type="submit" id="searchmappings" class="btn btn-gradient-original-success mr-2" value="Search ERF Mappings"/>
                        </div>
                      </div>
                      
                    </form>
                  </div>
                </div>
              </div>
            
            
            <div class="col-12 grid-margin stretch-card">
                <div class="card">
                  <div class="card-body">
                <h4 class="card-title">Results</h4>
                    <div class="table-responsive-xl">
	<div id="displayresults">
	<p class="card-description">Select the criteria and hit the Search ERF Mappings button.</p>
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