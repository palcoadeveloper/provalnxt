<style>
    

	/* Make DataTables length dropdown smaller in height */
.dataTables_length select.custom-select {
    height: 30px !important;
    padding-top: 0.2rem !important;
    padding-bottom: 0.2rem !important;
    border-top: 1px solid #dee2e6 !important;
}

/* Make DataTables search input smaller in height */
.dataTables_filter input.form-control {
    height: 30px !important;
    padding-top: 0.2rem !important;
    padding-bottom: 0.2rem !important;
}
</style>
<?php if (defined('DISABLE_RIGHT_CLICK') && DISABLE_RIGHT_CLICK === true): ?>
<script>document.addEventListener('contextmenu', function(event) {
  event.preventDefault();
  alert("<?php echo defined('RIGHT_CLICK_ALERT_MESSAGE') ? RIGHT_CLICK_ALERT_MESSAGE : 'Right-click is disabled on this page.'; ?>");
  return false;
}, false);</script>
<?php endif; ?>
<script src="assets/js/read-http-get.js"></script>
	<!-- container-scroller -->
	<!-- plugins:js -->
	
	<script src="assets/vendors/js/vendor.bundle.base.js"></script> 

	<script src="assets/vendors/js/jquery.dataTables.js"></script>
	<script src="assets/vendors/js/dataTables.bootstrap4.js"></script>
	<!-- endinject -->
	<!-- Plugin js for this page -->
	<!-- End plugin js for this page -->
	<!-- inject:js -->
	<script src="assets/js/off-canvas.js"></script>
	<script src="assets/js/hoverable-collapse.js"></script>
	<script src="assets/js/misc.js"></script>
	<script src="assets/js/sweetalert2.all.min.js"></script>
	<script	src="assets/js/form-validation.js"></script>
	<script src="assets/js/jquery-ui.min.js"></script> 
	<script src="assets/js/responsive-tables.js"></script>
	<!-- endinject -->
	<!-- Custom js for this page -->
	<!-- End custom js for this page -->

<!-- Global error handler for PDF viewer -->
<script>
// Global error handler to suppress PDF viewer touch point errors
window.addEventListener('error', function(event) {
  // Check if the error is from the PDF viewer touch point script
  if (event.filename && event.filename.includes('embedded-pdf-touch-point.js')) {
    // Prevent the error from showing in console
    event.preventDefault();
    return true;
  }
}, true);

// Apply error handler to iframes when they load
$(document).ready(function() {
  // Handle existing iframes
  $('iframe').on('load', function() {
    try {
      var iframe = this;
      if (iframe.contentWindow) {
        iframe.contentWindow.onerror = function(message, source, lineno, colno, error) {
          if (source && source.includes('embedded-pdf-touch-point.js')) {
            return true; // Suppress the error
          }
        };
      }
    } catch (e) {
      // Ignore errors in our error handler
    }
  });
  
  // Set up a mutation observer to handle dynamically added iframes
  var observer = new MutationObserver(function(mutations) {
    mutations.forEach(function(mutation) {
      if (mutation.addedNodes && mutation.addedNodes.length > 0) {
        for (var i = 0; i < mutation.addedNodes.length; i++) {
          var node = mutation.addedNodes[i];
          if (node.tagName && node.tagName.toLowerCase() === 'iframe') {
            $(node).on('load', function() {
              try {
                var iframe = this;
                if (iframe.contentWindow) {
                  iframe.contentWindow.onerror = function(message, source, lineno, colno, error) {
                    if (source && source.includes('embedded-pdf-touch-point.js')) {
                      return true; // Suppress the error
                    }
                  };
                }
              } catch (e) {
                // Ignore errors in our error handler
              }
            });
          }
        }
      }
    });
  });
  
  // Start observing the document body for changes
  observer.observe(document.body, { childList: true, subtree: true });
});
</script>

<!-- Session Timeout Management -->
<script>
$(document).ready(function() {
    // Check if we're on a login/logout page - skip session management
    var currentPage = window.location.pathname.split('/').pop();
    var excludedPages = ['login.php', 'logout.php', 'checklogin.php'];
    
    if (excludedPages.includes(currentPage)) {
        console.log('Skipping session management on page:', currentPage);
        return; // Exit early - no session management on these pages
    }
    
    // Session configuration from PHP
    var SESSION_TIMEOUT = <?php echo defined('SESSION_TIMEOUT') ? SESSION_TIMEOUT : 300; ?>; // seconds
    var SESSION_WARNING_TIME = <?php echo defined('SESSION_WARNING_TIME') ? SESSION_WARNING_TIME : 180; ?>; // seconds
    
    var sessionTimer;
    var warningShown = false;
    var lastActivity = Date.now();
    
    // Update activity timestamp
    function updateActivity() {
        lastActivity = Date.now();
        if (warningShown) {
            hideSessionWarning();
        }
    }
    
    // Send heartbeat to server to extend session
    function sendHeartbeat() {
        console.log('Sending session heartbeat...');
        $.ajax({
            url: 'core/security/session_heartbeat.php',
            type: 'POST',
            dataType: 'json',
            data: { action: 'heartbeat' },
            success: function(response) {
                if (response.status === 'success') {
                    console.log('Session extended successfully. Remaining time:', response.remaining_time + 's');
                    updateActivity();
                } else if (response.status === 'expired') {
                    // Session has expired on server
                    console.warn('Server reports session expired during heartbeat');
                    handleSessionExpiry();
                }
            },
            error: function() {
                console.warn('Failed to send session heartbeat - network error');
            }
        });
    }
    
    // Show session warning dialog
    function showSessionWarning() {
        if (warningShown) return;
        
        warningShown = true;
        var remainingTime = 2; // Default fallback, will be updated by server data
        
        // Create warning modal if it doesn't exist
        if ($('#sessionWarningModal').length === 0) {
            $('body').append(`
                <div class="modal fade" id="sessionWarningModal" tabindex="-1" role="dialog" aria-labelledby="sessionWarningTitle" aria-hidden="true" data-backdrop="static" data-keyboard="false">
                    <div class="modal-dialog modal-dialog-centered" role="document">
                        <div class="modal-content">
                            <div class="modal-header bg-warning text-dark">
                                <h5 class="modal-title" id="sessionWarningTitle">
                                    <i class="mdi mdi-clock-alert"></i> Session Timeout Warning
                                </h5>
                            </div>
                            <div class="modal-body">
                                <p>Your session will expire in <strong id="warningCountdown">${remainingTime}</strong> minute(s) due to inactivity.</p>
                                <p>Click "Stay Logged In" to continue your session, or "Logout" to end your session now.</p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-success" id="stayLoggedIn">
                                    <i class="mdi mdi-account-check"></i> Stay Logged In
                                </button>
                                <button type="button" class="btn btn-secondary" id="logoutNow">
                                    <i class="mdi mdi-logout"></i> Logout
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `);
            
            // Handle stay logged in button
            $('#stayLoggedIn').on('click', function() {
                sendHeartbeat();
                hideSessionWarning();
            });
            
            // Handle logout button
            $('#logoutNow').on('click', function() {
                window.location.href = 'logout.php';
            });
        }
        
        $('#sessionWarningModal').modal('show');
        
        // Get initial remaining time from server and start countdown
        $.ajax({
            url: 'core/security/session_heartbeat.php',
            type: 'POST',
            dataType: 'json',
            data: { action: 'status' },
            success: function(response) {
                if (response.session && response.session.active) {
                    var remainingMinutes = Math.ceil(response.session.remaining_time / 60);
                    $('#warningCountdown').text(remainingMinutes);
                }
                startWarningCountdown();
            },
            error: function() {
                startWarningCountdown();
            }
        });
    }
    
    // Hide session warning dialog
    function hideSessionWarning() {
        warningShown = false;
        $('#sessionWarningModal').modal('hide');
    }
    
    // Start countdown in warning dialog - uses server-side remaining time
    function startWarningCountdown() {
        var countdownInterval = setInterval(function() {
            // Get current remaining time from server
            $.ajax({
                url: 'core/security/session_heartbeat.php',
                type: 'POST',
                dataType: 'json',
                data: { action: 'status' },
                success: function(response) {
                    if (response.session && response.session.active) {
                        var remainingMinutes = Math.ceil(response.session.remaining_time / 60);
                        $('#warningCountdown').text(remainingMinutes);
                        
                        if (response.session.remaining_time <= 0) {
                            clearInterval(countdownInterval);
                            handleSessionExpiry();
                        }
                    } else {
                        clearInterval(countdownInterval);
                        handleSessionExpiry();
                    }
                },
                error: function() {
                    clearInterval(countdownInterval);
                    handleSessionExpiry();
                }
            });
        }, 30000); // Update every 30 seconds for more accuracy
    }
    
    // Handle session expiry
    function handleSessionExpiry() {
        // Double-check we're not on login page (safety check)
        var currentPage = window.location.pathname.split('/').pop();
        if (['login.php', 'logout.php', 'checklogin.php'].includes(currentPage)) {
            console.log('Session expiry ignored - already on login page');
            return;
        }
        
        hideSessionWarning();
        alert('Your session has expired. You will be redirected to the login page.');
        window.location.href = 'logout.php';
    }
    
    // Check session status periodically
    function checkSession() {
        console.log('Checking session status...');
        // Check server-side session status instead of relying on client-side timer
        $.ajax({
            url: 'core/security/session_heartbeat.php',
            type: 'POST',
            dataType: 'json',
            data: { action: 'status' },
            success: function(response) {
                if (response.status === 'success' && response.session) {
                    const session = response.session;
                    console.log('Session status:', {
                        active: session.active,
                        remaining_time: session.remaining_time,
                        show_warning: session.show_warning,
                        user_type: session.user_type
                    });
                    
                    // If session is not active on server, logout
                    if (!session.active) {
                        console.warn('Server reports session inactive - logging out');
                        handleSessionExpiry();
                        return;
                    }
                    
                    // Show warning based on server-side calculation
                    if (session.show_warning && !warningShown) {
                        console.log('Showing session warning - remaining time:', session.remaining_time + 's');
                        showSessionWarning();
                    }
                    
                    // Hide warning if server says we're good
                    if (!session.show_warning && warningShown) {
                        console.log('Hiding session warning - session extended');
                        hideSessionWarning();
                    }
                } else {
                    console.warn('Invalid session status response:', response);
                }
            },
            error: function() {
                // If server is unreachable, assume session expired
                console.warn('Session status check failed - network error - assuming session expired');
                handleSessionExpiry();
            }
        });
    }
    
    // Activity event listeners
    $(document).on('mousedown keydown scroll touchstart', function() {
        updateActivity();
    });
    
    // Start session monitoring
    updateActivity(); // Initialize
    
    // Send immediate heartbeat on page load to ensure session is refreshed
    sendHeartbeat();
    
    // Wait a moment after page load heartbeat to check session status
    setTimeout(function() {
        // Do an initial session check to sync with server state
        checkSession();
    }, 2000); // Increased delay to allow heartbeat to process
    
    // Check session status every 30 seconds
    setInterval(checkSession, 30000);
    
    // Send periodic heartbeat every 2 minutes if user is active
    setInterval(function() {
        var timeSinceLastActivity = (Date.now() - lastActivity) / 1000;
        
        // Only send heartbeat if user has been active in the last 2 minutes
        if (timeSinceLastActivity < 120) {
            sendHeartbeat();
        }
    }, 120000);
    
    console.log('Session timeout management initialized. Timeout: ' + SESSION_TIMEOUT + 's, Warning: ' + SESSION_WARNING_TIME + 's');
});
</script>