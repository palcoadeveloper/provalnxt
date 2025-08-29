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

require_once 'core/security/secure_query_wrapper.php';

// Generate CSRF token if not already set
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Secure input validation
$val_wf_id = secure_get('val_wf_id', 'string');

// Validate required parameters
if (!$val_wf_id) {
    SecurityUtils::logSecurityEvent('invalid_parameters', 'Missing val_wf_id parameter', [
        'val_wf_id' => $val_wf_id
    ]);
    header('Location: assignedcases.php?error=invalid_parameters');
    exit;
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
    <!-- endinject -->
    <!-- Plugin css for this page -->
    <!-- End plugin css for this page -->
    <!-- inject:css -->
    <!-- endinject -->
    <!-- Layout styles -->
    <link rel="stylesheet" href="assets/css/style.css">
    <!-- End layout styles -->
    <link rel="shortcut icon" href="assets/images/favicon.ico" />
    
    <!-- CSRF Token for AJAX requests -->
    <meta name="csrf-token" content="<?php echo $_SESSION['csrf_token']; ?>">
    
     <script src="assets/js/jquery.min.js" type="text/javascript"></script> 
    
      <script>
    $(document).ready(function(){
    
    //Required for closing the session timeout warning alert   
    $(function(){
    $("[data-hide]").on("click", function(){
        $(this).closest("." + $(this).attr("data-hide")).hide();
    });
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
    <div class="container-scroller">
       <!-- partial:assets/inc/_navbar.php -->
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
              <h3 class="page-title">
                <span class="page-title-icon bg-gradient-primary text-white mr-2">
                  <i class="mdi mdi-home"></i>
                </span> Test Details for <?php echo htmlspecialchars($val_wf_id, ENT_QUOTES, 'UTF-8'); ?> </h3>
              <nav aria-label="breadcrumb">
                <ul class="breadcrumb">
                  <li class="breadcrumb-item active" aria-current="page">
                    <span><a href="assignedcases.php"><< Back</a> <i class="mdi mdi-alert-circle-outline icon-sm text-primary align-middle"></i></span>
                  </li>
                </ul>
              </nav>
            </div>
          <?php include "assets/inc/_testdetails.php";?>
          
          </div>
          <!-- content-wrapper ends -->
          <!-- partial:assets/inc/_footer.php -->
          <?php include "assets/inc/_footer.php"; ?>
          <!-- partial -->
        </div>
        <!-- main-panel ends -->
      </div>
      <!-- page-body-wrapper ends -->
    </div>
    <!-- container-scroller -->
    <!-- plugins:js -->
    <script src="assets/vendors/js/vendor.bundle.base.js"></script>
    <!-- endinject -->
    <!-- Plugin js for this page -->
    <!-- End plugin js for this page -->
    <!-- inject:js -->
    <script src="assets/js/off-canvas.js"></script>
    <script src="assets/js/hoverable-collapse.js"></script>
    <script src="assets/js/misc.js"></script>
    <!-- endinject -->
    <!-- Custom js for this page -->
    <!-- End custom js for this page -->
  </body>
</html>
