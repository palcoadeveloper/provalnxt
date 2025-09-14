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
        
        // Instrument Statistics Overview functionality
        function loadInstrumentStatistics() {
            // Make AJAX call to fetch instrument statistics
            $.get("core/data/get/getinstrumentstats.php")
            .done(function(response) {
                try {
                    var stats = JSON.parse(response);
                    
                    // Update the counts
                    $('#active_instruments_count').text(stats.active_instruments || 0);
                    $('#expired_instruments_count').text(stats.expired_instruments || 0);
                    $('#due_soon_instruments_count').text(stats.due_soon_instruments || 0);
                    
                } catch(e) {
                    console.error('Error parsing instrument statistics response:', e);
                }
            })
            .fail(function(xhr, status, error) {
                console.error('Failed to fetch instrument statistics:', error);
            });
        }
        
        // Load statistics on page load
        loadInstrumentStatistics();
        
        // Load vendors dropdown
        function loadVendorsDropdown() {
            $.get("core/data/get/getvendorsforddl.php")
            .done(function(data) {
                $("#vendor_id").html(data);
            })
            .fail(function() {
                console.error('Failed to load vendors dropdown');
            });
        }
        
        // Load vendors on page load
        loadVendorsDropdown();
        
        // Show/hide search input based on search criteria
        $('#search_criteria').change(function() {
            var selectedValue = $(this).val();
            if (selectedValue === 'All Instruments') {
                $('#search_input').prop('disabled', true);
                $('#search_input').val('');
                $('#search_input_label').text('Search Input (disabled for All Instruments)');
            } else {
                $('#search_input').prop('disabled', false);
                if (selectedValue === 'Instrument ID') {
                    $('#search_input_label').text('Enter Instrument ID');
                } else if (selectedValue === 'Serial Number') {
                    $('#search_input_label').text('Enter Serial Number');
                }
            }
        });
        
        // Initialize the search input state
        $('#search_criteria').trigger('change');
        
        $("#formreport").on('submit',(function(e) {
            e.preventDefault();
            
            $('#pleasewaitmodal').modal('show');
            $.get("core/data/get/getinstrumentdetails.php",
                {
                    search_criteria: $("#search_criteria").val(),
                    search_input: $("#search_input").val(),
                    vendor_id: $("#vendor_id").val(),
                    instrument_type: $("#instrument_type").val(),
                    calibration_status: $("#calibration_status").val()
                },
                function(data, status){
                    $('#pleasewaitmodal').modal('hide');
                    $("#displayresults").html(data);
                    
                    // Small delay to ensure DOM is ready, then initialize modern DataTable
                    setTimeout(function() {
                        // Destroy existing DataTable if it exists
                        if ($.fn.DataTable.isDataTable('#tbl-instrument-details')) {
                            $('#tbl-instrument-details').DataTable().destroy();
                        }
                        
                        // Initialize modern DataTable with enhanced features
                        $('#tbl-instrument-details').DataTable({
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
                                "search": "Search instruments:",
                                "lengthMenu": "Show _MENU_ entries",
                                "info": "Showing _START_ to _END_ of _TOTAL_ instruments"
                            }
                        });
                    }, 100); // 100ms delay
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
              <h3 class="page-title"> Search Instruments</h3>
              <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                  <li class="breadcrumb-item"><a class='btn btn-gradient-info btn-sm btn-rounded' href="manageinstrumentdetails.php?m=a">+ Add Instrument</a></li>
                </ol>
              </nav>
            </div>
            
            <div class="row">
            
            <!-- Instrument Statistics Overview Card -->
            <div class="col-12 grid-margin stretch-card">
                <div class="card">
                  <div class="card-body">
                    <h4 class="card-title">Instruments Statistics Overview</h4>
                    
                    <!-- Instrument Statistics Tiles -->
                    <div class="row instrument-stats-container">
                        <div class="col-12 col-sm-4 col-md-4 stretch-card grid-margin">
                            <div class="card bg-gradient-success card-img-holder text-white instrument-stats-tile">
                                <div class="card-body">
                                    <img src="assets/images/dashboard/circle.svg" class="card-img-absolute" alt="circle-image" />
                                    <h4 class="font-weight-normal mb-3">
                                        Active Instruments
                                        <i class="mdi mdi-check-circle mdi-24px float-right"></i>
                                    </h4>
                                    <h2 class="mb-5 display-1" id="active_instruments_count">0</h2>
                                    <h6 class="card-text">Currently active</h6>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-12 col-sm-4 col-md-4 stretch-card grid-margin">
                            <div class="card bg-gradient-danger card-img-holder text-white instrument-stats-tile">
                                <div class="card-body">
                                    <img src="assets/images/dashboard/circle.svg" class="card-img-absolute" alt="circle-image" />
                                    <h4 class="font-weight-normal mb-3">
                                        Expired Instruments
                                        <i class="mdi mdi-alert-circle mdi-24px float-right"></i>
                                    </h4>
                                    <h2 class="mb-5 display-1" id="expired_instruments_count">0</h2>
                                    <h6 class="card-text">Calibration expired</h6>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-12 col-sm-4 col-md-4 stretch-card grid-margin">
                            <div class="card bg-gradient-warning card-img-holder text-white instrument-stats-tile">
                                <div class="card-body">
                                    <img src="assets/images/dashboard/circle.svg" class="card-img-absolute" alt="circle-image" />
                                    <h4 class="font-weight-normal mb-3">
                                        Calibration Due Soon
                                        <i class="mdi mdi-clock-alert mdi-24px float-right"></i>
                                    </h4>
                                    <h2 class="mb-5 display-1" id="due_soon_instruments_count">0</h2>
                                    <h6 class="card-text">Within 30 days</h6>
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
                    <h4 class="card-title">Search Criteria</h4>
                    
                    <form class="forms-sample" id="formreport">
                  
                       <div class="form-row">
                      <div class="form-group col-md-6">
                        <label for="search_criteria">Search Criteria</label>
                        <select class="form-control" id="search_criteria" name="search_criteria">
                          <option value="All Instruments">All Instruments</option>
                          <option value="Instrument ID">Instrument ID</option>
                          <option value="Serial Number">Serial Number</option>
                        </select>
                      </div>
                       <div class="form-group col-md-6">
                        <label for="search_input" id="search_input_label">Search Input</label>
                        <input type="text" class="form-control" id="search_input" name="search_input" placeholder="Enter search value">
                      </div>
                      </div>
                      
                      <div class="form-row">
                      <div class="form-group col-md-6">
                        <label for="vendor_id">Vendor</label>
                        <select class="form-control" id="vendor_id" name="vendor_id">
                          <option value="">All Vendors</option>
                        </select>
                      </div>
                       <div class="form-group col-md-6">
                        <label for="instrument_type">Instrument Type</label>
                        <select class="form-control" id="instrument_type" name="instrument_type">
                          <option value="">All Types</option>
                          <option value="Air Capture Hood">Air Capture Hood</option>
                          <option value="Anemometer">Anemometer</option>
                          <option value="Photo Meter">Photo Meter</option>
                          <option value="Particle Counter">Particle Counter</option>
                        </select>
                      </div>
</div>
<div class="form-row">
                      <div class="form-group col-md-6">
                        <label for="calibration_status">Calibration Status</label>
                        <select class="form-control" id="calibration_status" name="calibration_status">
                          <option value="">All Status</option>
                          <option value="Valid">Valid</option>
                          <option value="Due Soon">Due Soon (30 Days)</option>
                          <option value="Expired">Expired</option>
                        </select>
                      </div>
                      </div>
                      
                      <input type="submit" value="Search Results" class="btn btn-gradient-original-success mr-2"/>
                      
                    </form>
                  </div>
                </div>
              </div>
            
            <div class="col-12 grid-margin stretch-card">
                <div class="card">
                  <div class="card-body">
                <h4 class="card-title">Search Results</h4>
                    <div class="table-responsive-xl">
	<div id="displayresults">
	<p class="card-description">Select the criteria and hit the Search Results button.</p>
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