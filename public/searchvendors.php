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
                console.log('Restoring vendor search state from URL parameters');

                // Restore form values
                const searchInput = getUrlParameter('searchinput');

                if (searchInput && searchInput !== '') {
                    $('#search_input').val(searchInput);

                    // Auto-submit the form to show results
                    setTimeout(function() {
                        console.log('Auto-submitting restored vendor search');

                        // Show a brief notification that search is being restored
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                title: 'Restoring Search Results',
                                text: 'Taking you back to your previous vendor search...',
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

        // Vendor Statistics Overview functionality
        function loadVendorStatistics() {
            console.log('=== DEBUGGING VENDOR STATS ===');
            console.log('Loading vendor statistics...');
            
            // Make AJAX call to fetch vendor statistics
            $.get("core/data/get/getvendorstats.php")
            .done(function(response) {
                console.log('SUCCESS: Vendor statistics response received');
                console.log('Raw response:', response);
                try {
                    var stats = JSON.parse(response);
                    console.log('Parsed vendor stats:', stats);
                    
                    // Update the counts and log each update
                    $('#active_vendors_count').text(stats.active_vendors || 0);
                    console.log('Updated active_vendors_count to:', stats.active_vendors || 0);
                    
                    $('#inactive_vendors_count').text(stats.inactive_vendors || 0);
                    console.log('Updated inactive_vendors_count to:', stats.inactive_vendors || 0);
                    
                } catch(e) {
                    console.log('ERROR: Error parsing vendor statistics response:', e);
                    console.log('Raw response that failed to parse:', response);
                }
            })
            .fail(function(xhr, status, error) {
                console.log('FAIL: Failed to fetch vendor statistics');
                console.log('Error:', error);
                console.log('Status:', status);
                console.log('Response text:', xhr.responseText);
                console.log('Status code:', xhr.status);
            });
        }
        
        // Load statistics on page load
        loadVendorStatistics();
        
        
          $('#viewProtocolModal').on('show.bs.modal', function (e) {
    var loadurl = $(e.relatedTarget).data('load-url');
    $(this).find('.modal-body').load(loadurl);
});
    		
    		
         	$("#formreport").on('submit',(function(e) {
    			e.preventDefault();
    			
        			$('#pleasewaitmodal').modal('show');
        			$.get("core/data/get/getvendordetails.php",
                    	{
                        	searchinput: $("#search_input").val()
                       	},
                        function(data, status){
                        	$('#pleasewaitmodal').modal('hide');
                            $("#displayresults").html(data);
                            
                            // Small delay to ensure DOM is ready, then initialize modern DataTable
                            setTimeout(function() {
                                // Destroy existing DataTable if it exists
                                if ($.fn.DataTable.isDataTable('#tbl-vendor-details')) {
                                    $('#tbl-vendor-details').DataTable().destroy();
                                }
                                
                                // Initialize modern DataTable with enhanced features
                                $('#tbl-vendor-details').DataTable({
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
                                        "search": "Search vendors:",
                                        "lengthMenu": "Show _MENU_ entries",
                                        "info": "Showing _START_ to _END_ of _TOTAL_ vendors"
                                    }
                                });

                                // Highlight previously viewed vendor if coming back from details
                                const viewedVendorId = getUrlParameter('viewed_vendor_id');
                                if (viewedVendorId) {
                                    setTimeout(function() {
                                        $('a[href*="vendor_id=' + viewedVendorId + '"]').closest('tr').addClass('table-warning');
                                    }, 200);
                                }

                                // Smooth scroll to results section when coming back from vendor details
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
    			
    		}));
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

    /* Enhanced table row highlighting for previously viewed vendor */
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
	
	
	
	          			
<div class="modal fade bd-example-modal-lg show" id="viewProtocolModal" tabindex="-1" role="dialog" aria-labelledby="myLargeModalLabel" style="padding-right: 17px;">>
  <div class="modal-dialog modal-lg">
    <div class="modal-content">

      <div class="modal-header">
        <h4 class="modal-title" id="myLargeModalLabel">Protocol Report Preview</h4>
        <button id="modalbtncross" type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        

        
        
        
        
        
      </div>
    </div>
  </div>
</div> 
	
	
	
	
	
	
	
	
	
	<div class="page-header">
	<h3 class="page-title">Search Vendors</h3>
	<nav aria-label="breadcrumb">
	<ol class="breadcrumb">
	<li class="breadcrumb-item"><a href='managevendordetails.php?m=a' class='btn btn-gradient-info btn-sm btn-rounded' role='button' aria-pressed='true'>+ Add Vendor</a> </li>
	</ol>
	</nav>
	</div>
	<div class="row">
	
	<!-- Vendor Statistics Overview Card -->
    <div class="col-12 grid-margin stretch-card">
        <div class="card">
          <div class="card-body">
            <h4 class="card-title">Vendor Statistics Overview</h4>
            
            <!-- Vendor Statistics Tiles -->
            <div class="row vendor-stats-container">
                <div class="col-12 col-sm-6 col-md-6 stretch-card grid-margin">
                    <div class="card bg-gradient-success card-img-holder text-white vendor-stats-tile">
                        <div class="card-body">
                            <img src="assets/images/dashboard/circle.svg" class="card-img-absolute" alt="circle-image" />
                            <h4 class="font-weight-normal mb-3">
                                Active Vendors
                                <i class="mdi mdi-check-circle mdi-24px float-right"></i>
                            </h4>
                            <h2 class="mb-5 display-1" id="active_vendors_count">0</h2>
                            <h6 class="card-text">Currently active</h6>
                        </div>
                    </div>
                </div>
                
                <div class="col-12 col-sm-6 col-md-6 stretch-card grid-margin">
                    <div class="card bg-gradient-dark card-img-holder text-white vendor-stats-tile">
                        <div class="card-body">
                            <img src="assets/images/dashboard/circle.svg" class="card-img-absolute" alt="circle-image" />
                            <h4 class="font-weight-normal mb-3">
                                Inactive Vendors
                                <i class="mdi mdi-close-circle mdi-24px float-right"></i>
                            </h4>
                            <h2 class="mb-5 display-1" id="inactive_vendors_count">0</h2>
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
	<div class="form-group col-md-12">
	<label for="planned_start_from">Vendor Name</label> 
	<input type="Text" class="form-control" id="search_input" name="search_input" />
	</div>
	</div>
	
	<input type="submit" id="searchusers" class="btn btn-gradient-original-success mr-2" />
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
