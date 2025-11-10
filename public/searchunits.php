<?php
require_once('./core/config/config.php');

// Session is already started by config.php via session_init.php

// Check for proper authentication
if (!isset($_SESSION['logged_in_user']) || !isset($_SESSION['user_name'])) {
    session_destroy();
    header('Location: ' . BASE_URL . 'login.php?msg=session_required');
    exit();
}

// Check for superadmin role
if (!isset($_SESSION['is_super_admin']) || $_SESSION['is_super_admin'] !== 'Yes') {
    header('HTTP/1.1 403 Forbidden');
    header('Location: ' . BASE_URL . 'error.php?msg=access_denied');
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
            console.log('Restoring unit search state from URL parameters');

            // Restore form values
            const unitStatus = getUrlParameter('unit_status');

            if (unitStatus && unitStatus !== '') {
                $('#unit_status').val(unitStatus);

                // Auto-submit the form to show results
                setTimeout(function() {
                    console.log('Auto-submitting restored unit search');

                    // Show a brief notification that search is being restored
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            title: 'Restoring Search Results',
                            text: 'Taking you back to your previous unit search...',
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



 	$("#formreport").on('submit',(function(e) {
e.preventDefault();

		$('#pleasewaitmodal').modal('show');
					 $.get("core/data/get/getunitdetails.php",
                      {
                        unit_status: $("#unit_status").val()
                
                        
                      },
                      function(data, status){
                     $('#pleasewaitmodal').modal('hide');
                     $("#displayresults").html(data);
                     
                     // Small delay to ensure DOM is ready, then initialize modern DataTable
                     setTimeout(function() {
                         // Destroy existing DataTable if it exists
                         if ($.fn.DataTable.isDataTable('#tbl-unit-details')) {
                             $('#tbl-unit-details').DataTable().destroy();
                         }
                         
                         // Initialize modern DataTable with enhanced features
                         $('#tbl-unit-details').DataTable({
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
                                 "search": "Search units:",
                                 "lengthMenu": "Show _MENU_ entries",
                                 "info": "Showing _START_ to _END_ of _TOTAL_ units"
                             }
                         });

                         // Smooth scroll to results section when coming back from unit details
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
              <h3 class="page-title"> Search Units</h3>
              <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                  <li class="breadcrumb-item"><a class='btn btn-gradient-info btn-sm btn-rounded' href="manageunitdetails.php?m=a"><i class="mdi mdi-plus-circle"></i> Add Unit</a></li>
                  
                </ol>
              </nav>
            </div>
            
            
            <div class="row">
            
            	<div class="col-12 grid-margin stretch-card">
                <div class="card">
                  <div class="card-body">
                    <h4 class="card-title">Select Criteria</h4>
                    
                    <form class="forms-sample" id="formreport">
                  
                       <div class="form-row">
                       <div class="form-group col-md-12">
                        <label for="unit_status">Unit Status</label>
                        <select class="form-control" id="unit_status" name="unit_status">
                         <option>Select</option>
                          <option value='Active'>Active</option>
                          <option value='Inactive'>Inactive</option>
                          
                       
                        </select>
                      </div>
                      </div>
                      
                     
        
     
  
            
        
               
                      
                      
                      <button type="submit" id="searchunits" class="btn btn-gradient-primary btn-icon-text">
                        <i class="mdi mdi-magnify"></i> Search Units
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
                        <p>Use the search filters above to find units</p>
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