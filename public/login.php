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

    // Connectivity checking function
    function checkConnectivity() {
        return new Promise((resolve) => {
            console.log('Checking connectivity...');
            console.log('Navigator online status:', navigator.onLine);

            // For localhost development, if navigator says we're online, trust it
            if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
                console.log('Localhost detected - trusting navigator.onLine:', navigator.onLine);
                resolve(navigator.onLine);
                return;
            }

            // Check browser online status first
            if (!navigator.onLine) {
                console.log('Browser reports offline');
                resolve(false);
                return;
            }

            // Verify actual server connectivity
            console.log('Checking server connectivity...');
            const controller = new AbortController();
            const timeoutId = setTimeout(() => {
                console.log('Connectivity check timed out');
                controller.abort();
                resolve(false);
            }, 3000);

            fetch('connectivity-check.php?' + new Date().getTime(), {
                method: 'HEAD',
                cache: 'no-cache',
                signal: controller.signal
            })
            .then(response => {
                clearTimeout(timeoutId);
                console.log('Server connectivity check result:', response.ok);
                resolve(response.ok);
            })
            .catch(error => {
                clearTimeout(timeoutId);
                console.log('Server connectivity check failed:', error.message);
                resolve(false);
            });
        });
    }

    // Display connectivity error
    function showConnectivityError() {
        console.log('Showing connectivity error modal');

        // Get reason from URL parameters
        const urlParams = new URLSearchParams(window.location.search);
        const reason = urlParams.get('msg') === 'session_timeout' ? 'session_timeout' : 'connection_lost';

        // Create offline modal that matches ProVal design
        var modalHtml = '<div class="modal fade" id="connectivityModal" tabindex="-1" role="dialog" style="z-index: 9999;">' +
            '<div class="modal-dialog modal-dialog-centered" role="document">' +
            '<div class="modal-content">' +
            '<div class="modal-header bg-gradient-primary text-white">' +
            '<h5 class="modal-title"><i class="mdi mdi-wifi-off mr-2"></i>Connection Lost</h5>' +
            '</div>' +
            '<div class="modal-body text-center p-4">' +
            '<div class="mb-3"><i class="mdi mdi-wifi-off" style="font-size: 4rem; color: #b967db;"></i></div>' +
            '<h4 class="mb-3">' + getConnectivityTitle(reason) + '</h4>' +
            '<p class="text-muted mb-4">' + getConnectivityMessage(reason) + '</p>' +
            '</div>' +
            '<div class="modal-footer">' +
            '<small class="text-muted">Automatically checking every 5 seconds...</small>' +
            '</div>' +
            '</div>' +
            '</div>' +
            '</div>';

        // Remove any existing connectivity modal
        var existingModal = document.getElementById('connectivityModal');
        if (existingModal) {
            existingModal.remove();
        }

        // Add modal to page
        document.body.insertAdjacentHTML('beforeend', modalHtml);

        // Show modal
        $('#connectivityModal').modal({
            backdrop: 'static',
            keyboard: false
        });

        // Start auto-checking connectivity
        startLoginConnectivityCheck();
    }

    // Get appropriate title based on reason
    function getConnectivityTitle(reason) {
        switch(reason) {
            case 'session_timeout':
                return 'Session Expired';
            case 'connection_lost':
                return 'Connection Lost';
            default:
                return 'Offline';
        }
    }

    // Get appropriate message based on reason
    function getConnectivityMessage(reason) {
        switch(reason) {
            case 'session_timeout':
                return 'Your session expired and no internet connection is available. Connection is required to log back in.';
            case 'connection_lost':
                return 'Your internet connection has been lost. ProVal requires an active connection for security and compliance.';
            default:
                return 'Please check your network connection and try again.';
        }
    }

    // Start connectivity checking for login modal
    function startLoginConnectivityCheck() {
        window.loginConnectivityInterval = setInterval(function() {
            if (navigator.onLine) {
                // Quick server check
                fetch('connectivity-check.php?' + new Date().getTime(), {
                    method: 'HEAD',
                    cache: 'no-cache'
                })
                .then(function(response) {
                    if (response.ok) {
                        clearInterval(window.loginConnectivityInterval);
                        handleLoginReconnection();
                    }
                })
                .catch(function() {
                    // Still offline
                });
            }
        }, 5000);
    }

    // Handle reconnection from login modal
    function handleLoginReconnection() {
        var modal = document.getElementById('connectivityModal');
        if (modal) {
            var modalBody = modal.querySelector('.modal-body');
            modalBody.innerHTML = '<div class="text-center">' +
                '<i class="mdi mdi-check-circle" style="font-size: 4rem; color: #28a745;"></i>' +
                '<h4 class="mt-3 mb-3">Connection Restored</h4>' +
                '<p class="text-muted">You can now proceed with login.</p>' +
                '</div>';

            setTimeout(function() {
                $('#connectivityModal').modal('hide');

                // Ensure complete cleanup of modal and backdrop after hide animation
                setTimeout(function() {
                    // Remove modal element
                    var modal = document.getElementById('connectivityModal');
                    if (modal) {
                        modal.remove();
                    }
                    // Remove any lingering backdrop
                    $('.modal-backdrop').remove();
                    // Remove modal-open class from body
                    $('body').removeClass('modal-open');
                }, 500);

                // Re-enable the login form
                $('#btnNext').prop('disabled', false).text('LOGIN');
            }, 2000);
        }
    }

    // Manual connectivity check from login modal
    function checkConnectivityManual() {
        var button = document.querySelector('#connectivityModal .btn-gradient-primary');
        if (button) {
            button.innerHTML = '<span class="spinner-border spinner-border-sm mr-2"></span>Checking...';
            button.disabled = true;

            if (navigator.onLine) {
                fetch('connectivity-check.php?' + new Date().getTime(), {
                    method: 'HEAD',
                    cache: 'no-cache'
                })
                .then(function(response) {
                    if (response.ok) {
                        clearInterval(window.loginConnectivityInterval);
                        handleLoginReconnection();
                    } else {
                        throw new Error('Server not responding');
                    }
                })
                .catch(function() {
                    button.innerHTML = 'Try Again';
                    button.disabled = false;
                    var alert = document.querySelector('#connectivityModal .alert');
                    alert.innerHTML = '<strong>❌ Still Offline</strong><br>Connection check failed. Please verify your network settings.';
                    alert.className = 'alert alert-danger';
                });
            } else {
                button.innerHTML = 'Try Again';
                button.disabled = false;
            }
        }
    }

    // Hide connectivity error (legacy function, kept for compatibility)
    function hideConnectivityError() {
        // When connectivity is restored and we're on login page, just ensure form is enabled
        if ($('#loginnotifications').hasClass('alert-warning') && $('#notify').text().includes('No internet connection')) {
            $('#loginnotifications').hide();
            $('#btnNext').prop('disabled', false).text('LOGIN');
        }
    }

    // Check connectivity on page load (skip for localhost)
    if (window.location.hostname !== 'localhost' && window.location.hostname !== '127.0.0.1') {
        checkConnectivity().then(isOnline => {
            if (!isOnline) {
                showConnectivityError();
            }
        });
    } else {
        console.log('Skipping automatic connectivity check for localhost');
    }

    // Monitor connectivity changes
    window.addEventListener('online', () => {
        hideConnectivityError();
    });

    window.addEventListener('offline', () => {
        showConnectivityError();
    });

    // Periodic connectivity check
    setInterval(() => {
        checkConnectivity().then(isOnline => {
            if (isOnline) {
                hideConnectivityError();
            } else {
                showConnectivityError();
            }
        });
    }, <?php echo CONNECTIVITY_CHECK_INTERVAL * 1000; ?>);

    // Handle form submission
    $('#loginform').on('submit', function(e) {
        // Check connectivity before submission
        if (!navigator.onLine) {
            e.preventDefault();
            showConnectivityError();
            return false;
        }

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

        // Show loading state
        $('#btnNext').prop('disabled', true).text('Checking connection...');

        // Final connectivity check before actual submission
        checkConnectivity().then(isOnline => {
            if (isOnline) {
                $('#btnNext').text('Logging in...');
                // Allow form submission
                $('#loginform')[0].submit();
            } else {
                // Redirect to offline page instead of showing inline error
                showConnectivityError();
                return false;
            }
        });

        // Prevent default submission until connectivity check completes
        e.preventDefault();
        return false;
    });

    // Add hover lift effect to login button
    $('#btnNext').hover(
        function() {
            // Mouse enter
            $(this).css({
                'transform': 'translateY(-2px)',
                'box-shadow': '0 8px 25px rgba(185, 103, 219, 0.4)'
            });
        },
        function() {
            // Mouse leave
            $(this).css({
                'transform': 'translateY(0px)',
                'box-shadow': ''
            });
        }
    );

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
        var timeoutSeconds = <?php echo SESSION_TIMEOUT; ?>;
        var timeoutText = timeoutSeconds >= 60 ? Math.round(timeoutSeconds / 60) + " minute" + (Math.round(timeoutSeconds / 60) !== 1 ? "s" : "") : timeoutSeconds + " second" + (timeoutSeconds !== 1 ? "s" : "");
        $("#notify").text("Your session has expired due to " + timeoutText + " of inactivity. Please login again.");
      }
      else if(param == "session_timeout_compliance") {
        $("#loginnotifications").addClass("alert-warning");
        var timeoutSeconds = <?php echo SESSION_TIMEOUT; ?>;
        var timeoutText = timeoutSeconds >= 60 ? Math.round(timeoutSeconds / 60) + " minute" + (Math.round(timeoutSeconds / 60) !== 1 ? "s" : "") : timeoutSeconds + " second" + (timeoutSeconds !== 1 ? "s" : "");
        $("#notify").text("For security compliance, you have been automatically logged out after " + timeoutText + " of inactivity. Please login again.");
      }
      else if(param == "session_destroyed") {
        $("#loginnotifications").addClass("alert-warning");
        $("#notify").text("Your session has been terminated for security reasons. Please login again.");
      }
      else if(param == "session_screen_lock") {
        $("#loginnotifications").addClass("alert-warning");
        $("#notify").text("You have been logged out due to screen lock/lid closure for security. Please login again.");
      }
      else if(param == "connection_lost") {
        $("#loginnotifications").addClass("alert-danger");
        $("#notify").text("You have been logged out due to internet connection loss. Please check your network and login again.");
      }
      else if(param == "session_return_timeout") {
        $("#loginnotifications").addClass("alert-warning");
        var timeoutSeconds = <?php echo SESSION_TIMEOUT; ?>;
        var timeoutText = timeoutSeconds >= 60 ? Math.round(timeoutSeconds / 60) + " minute" + (Math.round(timeoutSeconds / 60) !== 1 ? "s" : "") : timeoutSeconds + " second" + (timeoutSeconds !== 1 ? "s" : "");
        $("#notify").text("You have been logged out after being away from the application for more than " + timeoutText + ". Please login again.");
      }
      else if(param == "session_visibility_timeout") {
        $("#loginnotifications").addClass("alert-warning");
        var timeoutSeconds = <?php echo VISIBILITY_TIMEOUT; ?>;
        var timeoutText = timeoutSeconds >= 60 ? Math.round(timeoutSeconds / 60) + " minute" + (Math.round(timeoutSeconds / 60) !== 1 ? "s" : "") : timeoutSeconds + " second" + (timeoutSeconds !== 1 ? "s" : "");
        $("#notify").text("You have been logged out after switching away from the application for more than " + timeoutText + ". Please login again.");
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
                    
                    <button id="btnNext" class="btn btn-block btn-gradient-primary btn-lg font-weight-medium auth-form-btn" type="submit" style="transition: all 0.3s ease;">LOGIN</button>
                    
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