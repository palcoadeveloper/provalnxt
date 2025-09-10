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
   
    // Room Statistics Overview functionality
    function loadRoomStatistics() {
        console.log('=== DEBUGGING ROOM STATS ===');
        console.log('Loading room statistics...');
        
        // Make AJAX call to fetch room statistics
        $.get("core/data/get/getroomstats.php")
        .done(function(response) {
            console.log('SUCCESS: Room statistics response received');
            console.log('Raw response:', response);
            try {
                var stats = JSON.parse(response);
                console.log('Parsed room stats:', stats);
                
                // Update the counts and log each update
                $('#total_rooms_count').text(stats.total_rooms || 0);
                console.log('Updated total_rooms_count to:', stats.total_rooms || 0);
                
                $('#total_volume_count').text(stats.total_volume || '0.00');
                console.log('Updated total_volume_count to:', stats.total_volume || '0.00');
                
            } catch(e) {
                console.log('ERROR: Error parsing room statistics response:', e);
                console.log('Raw response that failed to parse:', response);
            }
        })
        .fail(function(xhr, status, error) {
            console.log('FAIL: Failed to fetch room statistics');
            console.log('Error:', error);
            console.log('Status:', status);
            console.log('Response text:', xhr.responseText);
            console.log('Status code:', xhr.status);
        });
    }
    
    // Load statistics on page load
    loadRoomStatistics();
    
    
 	$("#formreport").on('submit', function(e) {
        e.preventDefault();

		$('#pleasewaitmodal').modal('show');
		$.get("core/data/get/getroomdetails.php",
              {
                room_name: $("#room_name").val()
              },
              function(data, status){
             $('#pleasewaitmodal').modal('hide');
             $("#displayresults").html(data);
             
             // Small delay to ensure DOM is ready, then initialize modern DataTable
             setTimeout(function() {
                 // Check if table exists and has the correct structure
                 var tableElement = $('#tbl-room-details');
                 if (tableElement.length > 0) {
                     // Destroy existing DataTable if it exists
                     if ($.fn.DataTable.isDataTable('#tbl-room-details')) {
                         $('#tbl-room-details').DataTable().destroy();
                     }
                     
                     // Verify table has proper thead and tbody structure
                     if (tableElement.find('thead').length > 0 && tableElement.find('tbody').length > 0) {
                         try {
                             // Initialize modern DataTable with enhanced features
                             $('#tbl-room-details').DataTable({
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
                                     "search": "Search rooms:",
                                     "lengthMenu": "Show _MENU_ entries",
                                     "info": "Showing _START_ to _END_ of _TOTAL_ rooms"
                                 }
                             });
                         } catch (e) {
                             console.error('DataTable initialization failed:', e);
                         }
                     } else {
                         console.warn('Table does not have proper thead/tbody structure');
                     }
                 } else {
                     console.warn('Table #tbl-room-details not found');
                 }
             }, 200); // Increased delay to 200ms
              });
		});

    });
    
    </script>
    
    <style>
    /* Room Statistics Tiles Styling */
    .room-stats-tile {
        height: 70% !important;
        min-height: 120px !important;
    }
    
    .room-stats-tile .card-body {
        padding: 15px 15px 8px 15px !important;
    }
    
    .room-stats-tile .display-1 {
        font-size: 2rem !important;
        margin-bottom: 5px !important;
    }
    
    .room-stats-tile h4 {
        font-size: 0.9rem !important;
        margin-bottom: 8px !important;
    }
    
    .room-stats-tile .card-text {
        font-size: 0.8rem !important;
        margin-bottom: 0 !important;
    }
    
    .room-stats-container {
        margin-top: 15px !important;
        margin-bottom: -15px !important;
        padding-bottom: 5px !important;
    }
    
    .room-stats-container .grid-margin {
        margin-bottom: 10px !important;
    }
    
    /* Modern DataTable Styling */
    #tbl-room-details {
        border-collapse: separate;
        border-spacing: 0;
        border-radius: 8px !important;
        overflow: hidden;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }
    
    #tbl-room-details thead th {
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
    
    #tbl-room-details thead th:first-child {
        border-top-left-radius: 8px;
    }
    
    #tbl-room-details thead th:last-child {
        border-top-right-radius: 8px;
    }
    
    #tbl-room-details tbody td {
        padding: 12px !important;
        vertical-align: middle !important;
        border-bottom: 1px solid #e3e6f0 !important;
        transition: all 0.3s ease !important;
    }
    
    #tbl-room-details tbody td:last-child {
        text-align: center !important;
    }
    
    #tbl-room-details tbody tr {
        transition: all 0.3s ease !important;
    }
    
    #tbl-room-details tbody tr:hover {
        background-color: #f8f9fe !important;
        transform: scale(1.02) !important;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1) !important;
    }
    
    #tbl-room-details tbody tr:nth-child(even) {
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
    #tbl-room-details .btn {
        border-radius: 20px !important;
        font-size: 0.8rem !important;
        padding: 6px 14px !important;
        font-weight: 500 !important;
        transition: all 0.3s ease !important;
        text-transform: uppercase !important;
        letter-spacing: 0.5px !important;
    }
    
    #tbl-room-details .btn:hover {
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
        
        #tbl-room-details tbody tr:hover {
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
              <h3 class="page-title"> Search Rooms/Locations</h3>
              <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                  <li class="breadcrumb-item"><a class='btn btn-gradient-info btn-sm btn-rounded' href="manageroomdetails.php?m=a">+ Add Room/Location</a></li>
                  
                </ol>
              </nav>
            </div>
            
            
            <div class="row">
            
            <!-- Room Statistics Overview Card -->
            <div class="col-12 grid-margin stretch-card">
                <div class="card">
                  <div class="card-body">
                    <h4 class="card-title">Room/Location Statistics Overview</h4>
                    
                    <!-- Room Statistics Tiles -->
                    <div class="row room-stats-container">
                        <div class="col-12 col-sm-6 col-md-6 stretch-card grid-margin">
                            <div class="card bg-gradient-success card-img-holder text-white room-stats-tile">
                                <div class="card-body">
                                    <img src="assets/images/dashboard/circle.svg" class="card-img-absolute" alt="circle-image" />
                                    <h4 class="font-weight-normal mb-3">
                                        Total Rooms/Locations
                                        <i class="mdi mdi-home-map-marker mdi-24px float-right"></i>
                                    </h4>
                                    <h2 class="mb-5 display-1" id="total_rooms_count">0</h2>
                                    <h6 class="card-text">Registered locations</h6>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-12 col-sm-6 col-md-6 stretch-card grid-margin">
                            <div class="card bg-gradient-info card-img-holder text-white room-stats-tile">
                                <div class="card-body">
                                    <img src="assets/images/dashboard/circle.svg" class="card-img-absolute" alt="circle-image" />
                                    <h4 class="font-weight-normal mb-3">
                                        Total Volume
                                        <i class="mdi mdi-cube-outline mdi-24px float-right"></i>
                                    </h4>
                                    <h2 class="mb-5 display-1" id="total_volume_count">0.00</h2>
                                    <h6 class="card-text">Cubic feet (ft³)</h6>
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
                        <label for="room_name">Room/Location Name</label>
                        <input type="text" class="form-control" id="room_name" name="room_name" placeholder="Enter room name to search...">
                      </div>
                      <div class="form-group col-md-6 d-flex align-items-end">
                        <input type="submit" id="searchrooms" class="btn btn-gradient-primary mr-2" value="Search Rooms"/>
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
	<p class="card-description">Select the criteria and hit the Search Rooms button.</p>
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