<?php
require_once('./core/config/config.php');

// Session is already started by config.php via session_init.php

// Optimized session validation
require_once('core/security/optimized_session_validation.php');
OptimizedSessionValidation::validateOnce();

// Define workflow stage constants for better code readability
define('DEPT_ENGINEERING', 1);
define('DEPT_QA', 2);
define('STAGE_NEW_TASK', '1');
define('STAGE_PENDING_APPROVAL', '2');
define('STAGE_APPROVED', '3');

// Generate CSRF token if not already set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}


?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Palcoa ProVal - HVAC Validation System</title>
    <!-- plugins:css -->
    <link rel="stylesheet" href="assets/vendors/mdi/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="assets/vendors/css/vendor.bundle.base.css">
    <link rel="stylesheet" href="assets/vendors/css/dataTables.bootstrap4.css">
    <!-- endinject -->
    <!-- Plugin css for this page -->
    <!-- End plugin css for this page -->
    <!-- inject:css -->
    <!-- endinject -->
    <!-- Layout styles -->
    <link rel="stylesheet" href="assets/css/style.css">
    <!-- End layout styles -->
    <link rel="shortcut icon" href="assets/images/favicon.ico" />
    <link rel="stylesheet" href="assets/css/modern-manage-ui.css">
        <script src="assets/js/jquery.min.js" type="text/javascript"></script> 
    
      <script>
    // Function to show dependency alert for blocked tests - defined globally
    function showDependencyAlert(message) {
        Swal.fire({
            icon: 'warning',
            title: 'Cannot Start Test',
            text: message,
            confirmButtonText: 'OK'
        });
    }

    $(document).ready(function(){

     //Required for closing the session timeout warning alert
    $(function(){
    $("[data-hide]").on("click", function(){
        $(this).closest("." + $(this).attr("data-hide")).hide();
    });
});


    $('#viewProtocolModal').on('show.bs.modal', function (e) {
    var loadurl = $(e.relatedTarget).data('load-url');
    $(this).find('.modal-body').load(loadurl);
});
   
   
   
   
   
   
    
     $('#datagrid-newtasks-vendor').DataTable({
        "pagingType": "numbers",
        "pageLength": 25,
        "lengthMenu": [[10, 25, 50, 100], [10, 25, 50, 100]],
        "language": {
            "lengthMenu": "Show _MENU_ entries"
        }
    });
    $('#datagrid-reassignedtasks-vendor').DataTable({
        "pagingType": "numbers",
        "pageLength": 25,
        "lengthMenu": [[10, 25, 50, 100], [10, 25, 50, 100]],
        "language": {
            "lengthMenu": "Show _MENU_ entries"
        }
    });
    $('#datagrid-offlinetasks-vendor').DataTable({
        "pagingType": "numbers",
        "pageLength": 25,
        "lengthMenu": [[10, 25, 50, 100], [10, 25, 50, 100]],
        "language": {
            "lengthMenu": "Show _MENU_ entries"
        }
    });
    $('#datagrid-newtasks-engg').DataTable({
        "pagingType": "numbers",
        "pageLength": 25,
        "lengthMenu": [[10, 25, 50, 100], [10, 25, 50, 100]],
        "language": {
            "lengthMenu": "Show _MENU_ entries"
        }
    });
    $('#datagrid-taskapproval-engg').DataTable({
        "pagingType": "numbers",
        "pageLength": 25,
        "lengthMenu": [[10, 25, 50, 100], [10, 25, 50, 100]],
        "language": {
            "lengthMenu": "Show _MENU_ entries"
        }
    });
    $('#datagrid-schapproval-engg').DataTable({
        "pagingType": "numbers",
        "pageLength": 25,
        "lengthMenu": [[10, 25, 50, 100], [10, 25, 50, 100]],
        "language": {
            "lengthMenu": "Show _MENU_ entries"
        }
    });
    $('#datagrid-taskapproval-qa').DataTable({
        "pagingType": "numbers",
        "pageLength": 25,
        "lengthMenu": [[10, 25, 50, 100], [10, 25, 50, 100]],
        "language": {
            "lengthMenu": "Show _MENU_ entries"
        }
    });


    $('#datagrid-schapproval-qa').DataTable({
        "pagingType": "numbers",
        "pageLength": 25,
        "lengthMenu": [[10, 25, 50, 100], [10, 25, 50, 100]],
        "language": {
            "lengthMenu": "Show _MENU_ entries"
        }
    });
    
    
    
    
    
    
    
    
    
    
    
    
    
    // Disable the back button - Begin
    
  window.history.pushState(null, "", window.location.href);        
      window.onpopstate = function() {
    
          window.history.pushState(null, "", window.location.href);
      };
    // Disable the back button - End
    
      });
    
    
    
    
    </script>
    
    
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
              <h3 class="page-title"> Assigned Tasks </h3>
              
            </div>
          
          
          
          
          
          
          
          
          <div class="row">
              
              
               <?php  include "assets/inc/_assignedcases.php";?> 
                       
              
              
               
              
          
          
          
          
          
          
          
          
          
          
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

