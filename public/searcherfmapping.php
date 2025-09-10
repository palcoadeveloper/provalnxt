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
    
    <style>
    /* ERF Mapping Statistics Tiles Styling */
    .erf-stats-tile {
        height: 70% !important;
        min-height: 120px !important;
    }
    
    .erf-stats-tile .card-body {
        padding: 15px 15px 8px 15px !important;
    }
    
    .erf-stats-tile .display-1 {
        font-size: 2rem !important;
        margin-bottom: 5px !important;
    }
    
    .erf-stats-tile h4 {
        font-size: 0.9rem !important;
        margin-bottom: 8px !important;
    }
    
    .erf-stats-tile .card-text {
        font-size: 0.8rem !important;
        margin-bottom: 0 !important;
    }
    
    .erf-stats-container {
        margin-top: 15px !important;
        margin-bottom: -15px !important;
        padding-bottom: 5px !important;
    }
    
    .erf-stats-container .grid-margin {
        margin-bottom: 10px !important;
    }
    
    /* Modern DataTable Styling */
    #tbl-erf-mapping-details {
        border-collapse: separate;
        border-spacing: 0;
        border-radius: 8px !important;
        overflow: hidden;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        width: 100% !important;
        table-layout: fixed !important;
    }
    
    /* Fixed Column Widths to Show All Headers Properly */
    #tbl-erf-mapping-details th:nth-child(1),
    #tbl-erf-mapping-details td:nth-child(1) { width: 50px !important; }  /* # */
    
    #tbl-erf-mapping-details th:nth-child(2),
    #tbl-erf-mapping-details td:nth-child(2) { width: 150px !important; } /* Equipment Code */
    
    #tbl-erf-mapping-details th:nth-child(3),
    #tbl-erf-mapping-details td:nth-child(3) { width: 180px !important; } /* Room/Location */
    
    #tbl-erf-mapping-details th:nth-child(4),
    #tbl-erf-mapping-details td:nth-child(4) { width: 200px !important; } /* Filter Name */
    
    #tbl-erf-mapping-details th:nth-child(5),
    #tbl-erf-mapping-details td:nth-child(5) { width: 150px !important; } /* Filter Group */
    
    #tbl-erf-mapping-details th:nth-child(6),
    #tbl-erf-mapping-details td:nth-child(6) { width: 180px !important; } /* Area Classification */
    
    #tbl-erf-mapping-details th:nth-child(7),
    #tbl-erf-mapping-details td:nth-child(7) { width: 100px !important; } /* Status */
    
    #tbl-erf-mapping-details th:nth-child(8),
    #tbl-erf-mapping-details td:nth-child(8) { width: 200px !important; } /* Actions */
    
    #tbl-erf-mapping-details thead th {
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
        visibility: visible !important;
        opacity: 1 !important;
        display: table-cell !important;
    }
    
    #tbl-erf-mapping-details thead th:first-child {
        border-top-left-radius: 8px;
    }
    
    #tbl-erf-mapping-details thead th:last-child {
        border-top-right-radius: 8px;
    }
    
    #tbl-erf-mapping-details tbody td {
        padding: 12px !important;
        vertical-align: middle !important;
        border-bottom: 1px solid #e3e6f0 !important;
        transition: all 0.3s ease !important;
        max-width: 0 !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
        white-space: nowrap !important;
    }
    
    /* Override for Action column to show all buttons */
    #tbl-erf-mapping-details tbody td:nth-child(8) {
        max-width: none !important;
        overflow: visible !important;
        text-overflow: visible !important;
        white-space: nowrap !important;
    }
    
    /* Text Wrapping for Room/Location Column */
    #tbl-erf-mapping-details td:nth-child(3) {
        white-space: normal !important;
        word-wrap: break-word !important;
        word-break: break-word !important;
        line-height: 1.3 !important;
    }
    
    /* Allow wrapping for longer content columns */
    #tbl-erf-mapping-details td:nth-child(4),
    #tbl-erf-mapping-details td:nth-child(5) {
        white-space: normal !important;
        word-wrap: break-word !important;
        line-height: 1.2 !important;
    }
    
    /* Action Column - Ensure buttons are always visible */
    #tbl-erf-mapping-details td:nth-child(8) {
        white-space: nowrap !important;
        text-overflow: visible !important;
        overflow: visible !important;
        padding: 8px !important;
        vertical-align: middle !important;
        text-align: center !important;
        min-width: 200px !important;
        width: 200px !important;
    }
    
    #tbl-erf-mapping-details tbody td:last-child {
        text-align: center !important;
    }
    
    #tbl-erf-mapping-details tbody tr {
        transition: all 0.3s ease !important;
    }
    
    #tbl-erf-mapping-details tbody tr:hover {
        background-color: #f8f9fe !important;
        transform: scale(1.02) !important;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1) !important;
    }
    
    #tbl-erf-mapping-details tbody tr:nth-child(even) {
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
    #tbl-erf-mapping-details .btn {
        border-radius: 20px !important;
        font-size: 0.8rem !important;
        padding: 6px 14px !important;
        font-weight: 500 !important;
        transition: all 0.3s ease !important;
        text-transform: uppercase !important;
        letter-spacing: 0.5px !important;
    }
    
    #tbl-erf-mapping-details .btn:hover {
        transform: translateY(-2px) !important;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15) !important;
    }
    
    /* Ensure table headers are always visible */
    #tbl-erf-mapping-details thead th {
        visibility: visible !important;
        opacity: 1 !important;
        display: table-cell !important;
        height: auto !important;
        min-height: 45px !important;
    }
    
    /* Force DataTable header to be visible */
    .dataTables_wrapper .dataTables_scrollHead {
        overflow: visible !important;
        border: none !important;
    }
    
    .dataTables_wrapper .dataTables_scrollHeadInner {
        box-sizing: content-box !important;
        overflow: visible !important;
    }
    
    /* Fix header positioning */
    #tbl-erf-mapping-details_wrapper .dataTables_scroll .dataTables_scrollHead table thead tr {
        display: table-row !important;
        visibility: visible !important;
    }
    
    #tbl-erf-mapping-details_wrapper .dataTables_scroll .dataTables_scrollHead table thead tr th {
        display: table-cell !important;
        visibility: visible !important;
        opacity: 1 !important;
    }
    
    /* Simple header styling - no scroll complications */
    #tbl-erf-mapping-details thead th {
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
    
    /* Responsive Improvements */
    @media (max-width: 768px) {
        .dataTables_wrapper .dataTables_filter input {
            width: 200px !important;
        }
        
        .dataTables_wrapper .dataTables_filter input:focus {
            width: 240px !important;
        }
        
        #tbl-erf-mapping-details tbody tr:hover {
            transform: none !important;
        }
        
        /* Adjust column widths for mobile */
        #tbl-erf-mapping-details th:nth-child(3),
        #tbl-erf-mapping-details td:nth-child(3) { width: 120px !important; }
        
        #tbl-erf-mapping-details th:nth-child(4),
        #tbl-erf-mapping-details td:nth-child(4) { width: 150px !important; }
        
        #tbl-erf-mapping-details th:nth-child(5),
        #tbl-erf-mapping-details td:nth-child(5) { width: 100px !important; }
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
                      <div class="form-group col-md-3">
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
                      <div class="form-group col-md-3">
                        <label for="equipment_id">Equipment</label>
                        <select class="form-control" id="equipment_id" name="equipment_id">
                          <option value="Select">Select Equipment</option>
                        </select>
                      </div>
                      <div class="form-group col-md-3">
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
                      <div class="form-group col-md-3">
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
                          <input type="submit" id="searchmappings" class="btn btn-gradient-primary mr-2" value="Search ERF Mappings"/>
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