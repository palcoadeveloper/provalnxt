<?php 
// Include configuration first
require_once 'core/config/config.php';

// Include session initialization to ensure consistent session config
require_once 'core/security/session_init.php';

// Include rate limiting utilities
require_once 'core/security/rate_limiting_utils.php';

// Check rate limiting for login page access
$rateLimitResult = RateLimiter::checkRateLimit('login_attempts');
if (!$rateLimitResult['allowed']) {
    header('HTTP/1.1 429 Too Many Requests');
    header('Retry-After: ' . ($rateLimitResult['lockout_expires'] - time()));
    
    // Log rate limiting event
    if (class_exists('SecurityUtils')) {
        SecurityUtils::logSecurityEvent('login_page_rate_limited', 'Login page access rate limited', [
            'remaining_time' => $rateLimitResult['lockout_expires'] - time()
        ]);
    }
    
    die('Too many login attempts. Account temporarily locked. Please try again in ' . 
        ceil(($rateLimitResult['lockout_expires'] - time()) / 60) . ' minutes.');
}

// Include auth utilities for CSRF functions
require_once 'core/security/auth_utils.php';

// Check if the reason for being here is a session timeout
if (isset($_GET['msg']) && (
    $_GET['msg'] === 'session_timeout' || 
    $_GET['msg'] === 'session_timeout_compliance' ||
    $_GET['msg'] === 'session_destroyed'
)) {
    // Clear all session variables
    $_SESSION = array();

    // If it's desired to kill the session, also delete the session cookie.
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    // Finally, destroy the session.
    session_destroy();

    // Start a new, clean session for the login page (session_init.php already included by config.php)
    // Need to restart session after destroy
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Now that we have a new session, regenerate the session ID for security
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
} else {
    // For normal access (not session timeout), just regenerate ID if session exists
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
}

// Generate CSRF token (this will work properly now that session is established)
generateCSRFToken();

// Security headers are already set by config.php via security_middleware.php
// Removed redundant manual headers and security_headers.php include to prevent duplication

// After session_start()
error_log("Session configuration:");
error_log("Session ID: " . session_id());
error_log("Session Name: " . session_name());
error_log("Session Cookie Path: " . session_get_cookie_params()['path']);
error_log("Session Cookie Domain: " . session_get_cookie_params()['domain']);
error_log("Session Cookie Secure: " . (session_get_cookie_params()['secure'] ? 'true' : 'false'));
error_log("Session Cookie HttpOnly: " . (session_get_cookie_params()['httponly'] ? 'true' : 'false'));
error_log("Session Cookie SameSite: " . session_get_cookie_params()['samesite']);
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
  <script src="assets/js/jquery.min.js" type="text/javascript"></script> 
   <script> 
// Fix for the conditional statements in the URL parameter handling
$(document).ready(function(){
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

    // Get URL parameters function
    function getUrlVars() {
      var vars = {};
      var parts = window.location.href.replace(/[?&]+([^=&]+)=([^&]*)/gi, function(m,key,value) {
        vars[key] = decodeURIComponent(value);
      });
      return vars;
    }

    // Handle form submission
    $('#loginform').on('submit', function(e) {
        // Update timestamp on submit
        $('input[name="timestamp"]').val(Math.floor(Date.now() / 1000));
        
        // Log CSRF token
        console.log("Submitting form with CSRF token:", $('#csrf_token').val());
        
        // Verify form data exists
        if (!$('input[name="username"]').val() || !$('input[name="password"]').val()) {
            e.preventDefault();
            alert('Please fill in all required fields');
            return false;
        }
        
        // Allow form submission
        return true;
    });

    var param = getUrlVars()["msg"];

    if(param == null) {
      $("#loginnotifications").hide();
    }
    else {
      $("#loginnotifications").show();
      if(param == "invld_acct") {
        $("#loginnotifications").addClass("alert-danger");
        $("#notify").text("Error: User name/password/user type didn't match.");
      }
      else if(param == "upd_pwd") {
        $("#loginnotifications").addClass("alert-danger");
        $("#notify").text("Error: Default password could not be changed. Please contact the administrator.");
      }
      else if(param == "curr_pwd") {
        $("#loginnotifications").addClass("alert-danger");
        $("#notify").text("Error: Default password could not be changed. Incorrect current password.");
      }
      else if(param == "pwd_reset_fail") {
        $("#loginnotifications").addClass("alert-danger");
        $("#notify").text("Error: Password could not be reset. Please contact the admin.");
      }
      else if(param == "pwd_reset_no_info") {
        $("#loginnotifications").addClass("alert-danger");
        $("#notify").text("Error: Employee ID and Email ID combination not found in the database.");
      }
      else if(param == "pwd_reset_success") {
        $("#loginnotifications").removeClass("alert-danger");
        $("#loginnotifications").addClass("alert-success");
        $("#notify").text("Password is successfully reset. The new password is sent on your registered email. ");
      }
      else if(param == "acct_inactive") {
        $("#loginnotifications").addClass("alert-danger");
        $("#notify").text("Error: Account is inactive.");
      }
      else if(param == "session_timeout") {
        $("#loginnotifications").addClass("alert-warning");
        $("#notify").text("Your session has expired due to 5 minutes of inactivity. Please login again.");
      }
      else if(param == "session_timeout_compliance") {
        $("#loginnotifications").addClass("alert-warning");
        $("#notify").text("For security compliance, you have been automatically logged out after 5 minutes of inactivity. Please login again.");
      }
      else if(param == "session_destroyed") {
        $("#loginnotifications").addClass("alert-warning");
        $("#notify").text("Your session has been terminated for security reasons. Please login again.");
      }
      else if(param == "no_session") {
        $("#loginnotifications").addClass("alert-warning");
        $("#notify").text("No valid session found. Please login to continue.");
      }
      else if(param == "user_logout") {
        $("#loginnotifications").removeClass("alert-danger");
        $("#loginnotifications").addClass("alert-success");
        $("#notify").text("You are successfully logged out. ");
      }
      else if(param == "acct_lckd") {
        $("#loginnotifications").addClass("alert-danger");
        $("#notify").text('Error: User account is locked. Please contact the admin.');
      }
      else if(param == "invalid_login") {
        $("#loginnotifications").addClass("alert-danger");
        $("#notify").text('Invalid login credentials. You have ' + getUrlVars()["attempts_left"] + ' attempts left.');
      }
      else if(param == "rate_limited") {
        $("#loginnotifications").addClass("alert-danger");
        var retryAfter = getUrlVars()["retry_after"] || "30";
        $("#notify").text('Too many login attempts. Please wait ' + retryAfter + ' minutes before trying again.');
      }
      else if(param == "empty_fields") {
        $("#loginnotifications").addClass("alert-danger");
        $("#notify").text('Please fill in all required fields.');
      }
      else if(param == "otp_send_failed") {
        $("#loginnotifications").addClass("alert-danger");
        $("#notify").text('Failed to send verification code. Please try again or contact IT support.');
      }
      else if(param == "otp_session_failed") {
        $("#loginnotifications").addClass("alert-danger");
        $("#notify").text('Failed to create verification session. Please try again.');
      }
      else if(param == "no_2fa_session") {
        $("#loginnotifications").addClass("alert-warning");
        $("#notify").text('No verification session found. Please login again.');
      }
      else if(param == "otp_expired") {
        $("#loginnotifications").addClass("alert-warning");
        $("#notify").text('Verification code has expired. Please login again.');
      }
      else if(param == "max_attempts") {
        $("#loginnotifications").addClass("alert-danger");
        $("#notify").text('Maximum verification attempts exceeded. Please login again.');
      }
      else if(param == "invalid_otp") {
        $("#loginnotifications").addClass("alert-danger");
        var attemptsLeft = getUrlVars()["attempts_left"] || "unknown";
        $("#notify").text('Invalid verification code. ' + attemptsLeft + ' attempts remaining.');
      }
      else if(param == "otp_rate_limited") {
        $("#loginnotifications").addClass("alert-warning");
        var retryAfter = getUrlVars()["retry_after"] || "5";
        $("#notify").text('Too many verification attempts. Please wait ' + Math.ceil(retryAfter / 60) + ' minutes before requesting a new code.');
      }
      else if(param == "session_required") {
        $("#loginnotifications").addClass("alert-warning");
        $("#notify").text('Please complete the login process to continue.');
      }
      else if(param == "session_cancelled") {
        $("#loginnotifications").addClass("alert-info");
        $("#notify").text('Two-factor authentication was cancelled. Please log in again.');
      }
      else if(param == "session_cancelled_error") {
        $("#loginnotifications").addClass("alert-warning");
        $("#notify").text('Session cancellation completed with warnings. Please log in again.');
      }
      else if(param == "security_error") {
        $("#loginnotifications").addClass("alert-danger");
        $("#notify").text('Security validation failed. Please log in again.');
      }
      else if(param == "system_error") {
        $("#loginnotifications").addClass("alert-danger");
        var errorRef = getUrlVars()["ref"] || "Unknown";
        // Decode the error reference to remove + signs and URL encoding
        errorRef = decodeURIComponent(errorRef.replace(/\+/g, ' '));
        $("#notify").text("A system error occurred. Please contact IT support with reference: " + errorRef);
      }
   
       else if(param == "security_error") {
        $("#loginnotifications").addClass("alert-danger");
        
        // Get IP address and security error type
        var ipAddress = getUrlVars()["ip"] || "Unknown";
        var securityType = getUrlVars()["type"] || "unknown";
        
        // Format different messages based on the security error type
        var securityMessage = "";
        
        switch(securityType) {
          case "csrf_failure":
            securityMessage = "Security alert: Invalid form submission detected from IP: " + ipAddress + 
                            ". This has been logged for security purposes.";
            break;
          case "sql_injection_attempt":
            securityMessage = "Security alert: Potential SQL injection attempt detected from IP: " + ipAddress + 
                            ". This activity has been logged.";
            break;
          case "invalid_input":
            securityMessage = "Security alert: Invalid input detected from IP: " + ipAddress + 
                            ". Please use only allowed characters.";
            break;
          default:
            securityMessage = "Security alert: Suspicious activity detected from IP: " + ipAddress + 
                            ". This has been logged for security purposes.";
        }
        
        $("#notify").text(securityMessage);
        
        // Log this event client-side
        console.warn("Security error type: " + securityType + " from IP: " + ipAddress);

      }
    



    }
});
    </script>

  </head>
  <body >
  <?php include "assets/inc/_viewaboutmodal.php"; ?>
       <div class="text-right mr-1">  <button data-toggle='modal' data-target='#exampleModalLive' data-load-url='about.php' type="button" class="btn btn-link btn-fw">About ProVal</button>
                  </div>
    <div class="container-scroller">
   
      <div class="container-fluid page-body-wrapper full-page-wrapper">
      
        
         
        <div class="content-wrapper-login d-flex align-items-center auth">
        

        
          <div class="row flex-grow">
            <div class="col-lg-6 mx-auto">
            
<div id="loginnotifications" class="alert alert-dismissible fade show" role="alert">
<div id="notify"></div>
  <button type="button" class="close" data-dismiss="alert" aria-label="Close">
    <span aria-hidden="true">&times;</span>
  </button>
</div>

<?php include "assets/inc/_sessiontimeout.php"; ?>			
       
              <div class="auth-form-light text-left p-5">
          
                <div class="text-left brand-logo">
                  <h1 class="display-3 text-primary">ProVal</h1>
                </div>
                
                 
            
            
            <h4>Hello! let's get started</h4>
                <h6 class="font-weight-light">Sign in to continue.</h6> 
                
                <form id="loginform" method="post" action="core/validation/checklogin.php" class="needs-validation pt-3" novalidate autocomplete="off">
                    <input type="hidden" name="csrf_token" id="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="timestamp" value="<?= time() ?>">
                    <div class="form-group">
                        <!--  	<label for="userName">User Name <span class="text-danger">*</span></label>--> 
                        <input type="text" class="form-control form-control-lg" name="username" placeholder="Domain ID/Employee ID" value="" required pattern="[a-zA-Z0-9_.]+" maxlength="20" title="Alphanumeric characters, underscores and periods only">
                        <div class="invalid-feedback">User name is required (letters, numbers, _ or . only, max 20 chars).</div>
                    </div>
                    
                    <div class="form-group">
                        <!--  		<label for="password">Password <span class="text-danger">*</span></label> --> 
                        <input type="password" class="form-control form-control-lg" name="password" placeholder="Password" value=""	required minlength="8" maxlength="64">
                        <div class="invalid-feedback">Password is required (8-64 characters).</div>
                    </div>
                    
                    <div class="form-group">
                        <div class="form-check form-check-inline">
                          <label class="form-check-label">
                            <input type="radio" class="form-check-input" name="optionUserType" id="optionEmployee" value="E" checked>Employee <i class="input-helper"></i></label>
                        </div>
                        <div class="form-check form-check-inline">
                          <label class="form-check-label">
                            <input type="radio" class="form-check-input" name="optionUserType" id="optionVendor" value="V"> External Agency <i class="input-helper"></i></label>
                        </div>
                       
                    </div>
                    
                    <button id="btnNext" class="btn btn-block btn-gradient-primary btn-lg font-weight-medium auth-form-btn" type="submit">LOGIN</button>
                    
                </form>
                    
                    
                
                
              </div>
              <?php include "assets/inc/_footercopyright.php"; ?>
            </div>
                       
          </div>

        </div>
        <!-- content-wrapper ends -->
       
      </div>
      <!-- page-body-wrapper ends -->
    </div>
    <!-- container-scroller -->
 
 
  
 
   <?php include "assets/inc/_footerjs.php"; ?>
  </body>
</html>