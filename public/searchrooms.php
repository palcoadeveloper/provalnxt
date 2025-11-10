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
            console.log('Restoring room search state from URL parameters');

            // Restore form values
            const roomName = getUrlParameter('room_name');

            if (roomName && roomName !== '') {
                $('#room_name').val(roomName);

                // Auto-submit the form to show results
                setTimeout(function() {
                    console.log('Auto-submitting restored room search');

                    // Show a brief notification that search is being restored
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            title: 'Restoring Search Results',
                            text: 'Taking you back to your previous room search...',
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
            }
        }
    }

    // Call restore function on page load
    restoreSearchState();

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

                             // Smooth scroll to results section when coming back from room details
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
              <h3 class="page-title"> Search Rooms/Locations</h3>
              <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                  <li class="breadcrumb-item"><a class='btn btn-gradient-info btn-sm btn-rounded' href="manageroomdetails.php?m=a"><i class="mdi mdi-plus-circle"></i> Add Room/Location</a></li>
                  
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
                      <div class="form-group col-md-12">
                        <label for="room_name">Room/Location Name</label>
                        <input type="text" class="form-control" id="room_name" name="room_name" placeholder="Enter room name to search...">
                      </div>
</div>
<div class="form-row">
                      <div class="form-group col-md-12">
                        <button type="submit" id="searchrooms" class="btn btn-gradient-primary btn-icon-text">
                          <i class="mdi mdi-magnify"></i> Search Rooms
                        </button>
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
	<div class="text-center text-muted py-4">
                        <i class="mdi mdi-filter-variant icon-lg mb-2"></i>
                        <p> Use the search filters above to find rooms</p>
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