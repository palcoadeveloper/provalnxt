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
    
    <style>
    /* Instrument Statistics Tiles Styling */
    .instrument-stats-tile {
        height: 70% !important;
        min-height: 120px !important;
    }
    
    .instrument-stats-tile .card-body {
        padding: 15px 15px 8px 15px !important;
    }
    
    .instrument-stats-tile .display-1 {
        font-size: 2rem !important;
        margin-bottom: 5px !important;
    }
    
    .instrument-stats-tile h4 {
        font-size: 0.9rem !important;
        margin-bottom: 8px !important;
    }
    
    .instrument-stats-tile .card-text {
        font-size: 0.8rem !important;
        margin-bottom: 0 !important;
    }
    
    .instrument-stats-container {
        margin-top: 15px !important;
        margin-bottom: -15px !important;
        padding-bottom: 5px !important;
    }
    
    .instrument-stats-container .grid-margin {
        margin-bottom: 10px !important;
    }
    
    /* Modern DataTable Styling */
    #tbl-instrument-details {
        border-collapse: separate;
        border-spacing: 0;
        border-radius: 8px !important;
        overflow: hidden;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }
    
    #tbl-instrument-details thead th {
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
    
    #tbl-instrument-details thead th:first-child {
        border-top-left-radius: 8px;
    }
    
    #tbl-instrument-details thead th:last-child {
        border-top-right-radius: 8px;
    }
    
    #tbl-instrument-details tbody td {
        padding: 12px !important;
        vertical-align: middle !important;
        border-bottom: 1px solid #e3e6f0 !important;
        transition: all 0.3s ease !important;
    }
    
    #tbl-instrument-details tbody td:last-child {
        text-align: center !important;
    }
    
    #tbl-instrument-details tbody tr {
        transition: all 0.3s ease !important;
    }
    
    #tbl-instrument-details tbody tr:hover {
        background-color: #f8f9fe !important;
        transform: scale(1.02) !important;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1) !important;
    }
    
    #tbl-instrument-details tbody tr:nth-child(even) {
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
    #tbl-instrument-details .btn {
        border-radius: 20px !important;
        font-size: 0.8rem !important;
        padding: 6px 14px !important;
        font-weight: 500 !important;
        transition: all 0.3s ease !important;
        text-transform: uppercase !important;
        letter-spacing: 0.5px !important;
    }
    
    #tbl-instrument-details .btn:hover {
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
        
        #tbl-instrument-details tbody tr:hover {
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
                      <div class="form-group col-md-4">
                        <label for="vendor_id">Vendor</label>
                        <select class="form-control" id="vendor_id" name="vendor_id">
                          <option value="">All Vendors</option>
                        </select>
                      </div>
                       <div class="form-group col-md-4">
                        <label for="instrument_type">Instrument Type</label>
                        <select class="form-control" id="instrument_type" name="instrument_type">
                          <option value="">All Types</option>
                          <option value="Air Capture Hood">Air Capture Hood</option>
                          <option value="Anemometer">Anemometer</option>
                          <option value="Photo Meter">Photo Meter</option>
                          <option value="Particle Counter">Particle Counter</option>
                        </select>
                      </div>
                      <div class="form-group col-md-4">
                        <label for="calibration_status">Calibration Status</label>
                        <select class="form-control" id="calibration_status" name="calibration_status">
                          <option value="">All Status</option>
                          <option value="Valid">Valid</option>
                          <option value="Due Soon">Due Soon (30 Days)</option>
                          <option value="Expired">Expired</option>
                        </select>
                      </div>
                      </div>
                      
                      <input type="submit" value="Search Results" class="btn btn-gradient-primary mr-2"/>
                      
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