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
  alert("<?php echo defined('RIGHT_CLICK_ALERT_MESSAGE') ? addslashes(RIGHT_CLICK_ALERT_MESSAGE) : 'Right-click is disabled on this page.'; ?>");
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
    // Session management is now handled by _sessiontimeout.php
    // This file only handles SweetAlert enhancements
    
    // All session management is now handled by SessionTimeoutManager in _sessiontimeout.php
    // This includes activity tracking, timeouts, warnings, and multi-tab coordination

    console.log('_footerjs.php loaded - session management delegated to SessionTimeoutManager');
});

// Removed unused handleLogout() and checkConnectivityForLogout() functions
// Using handleLogoutSimple() instead

// Simplified Logout Function
window.handleLogoutSimple = function() {
    console.log('=== LOGOUT FUNCTION CALLED ===');
    console.log('Current page:', window.location.href);

    // Signal to session manager that user initiated logout
    if (window.sessionManager) {
        window.sessionManager.userInitiatedLogout = true;
        console.log('Session manager notified of user-initiated logout');
    }

    // Directly proceed with logout - connectivity issues shouldn't prevent logout
    console.log('Proceeding with logout...');
    window.location.href = 'logout.php';
};

// Function to show offline modal without requiring HTTP request
function showOfflinePage(reason) {
    console.log('Displaying offline modal for reason:', reason);

    // Create offline modal that matches ProVal design
    var modalHtml = '<div class="modal fade" id="offlineModal" tabindex="-1" role="dialog" style="z-index: 9999;">' +
        '<div class="modal-dialog modal-dialog-centered" role="document">' +
        '<div class="modal-content">' +
        '<div class="modal-header bg-gradient-primary text-white">' +
        '<h5 class="modal-title"><i class="mdi mdi-wifi-off mr-2"></i>Connection Lost</h5>' +
        '</div>' +
        '<div class="modal-body text-center p-4">' +
        '<div class="mb-3"><i class="mdi mdi-wifi-off" style="font-size: 4rem; color: #b967db;"></i></div>' +
        '<h4 class="mb-3">' + getOfflineTitle(reason) + '</h4>' +
        '<p class="text-muted mb-4">' + getOfflineMessage(reason) + '</p>' +
        '</div>' +
        '<div class="modal-footer">' +
        '<small class="text-muted">Automatically checking every 5 seconds...</small>' +
        '</div>' +
        '</div>' +
        '</div>' +
        '</div>';

    // Remove any existing offline modal
    var existingModal = document.getElementById('offlineModal');
    if (existingModal) {
        existingModal.remove();
    }

    // Add modal to page
    document.body.insertAdjacentHTML('beforeend', modalHtml);

    // Show modal
    $('#offlineModal').modal({
        backdrop: 'static',
        keyboard: false
    });

    // Start auto-checking connectivity
    startOfflineConnectivityCheck(reason);
}

// Get appropriate title based on reason
function getOfflineTitle(reason) {
    switch(reason) {
        case 'logout':
            return 'Logout Requested';
        case 'session_timeout':
            return 'Session Expired';
        case 'connection_lost':
            return 'Connection Lost';
        default:
            return 'Offline';
    }
}

// Start connectivity checking for offline modal
function startOfflineConnectivityCheck(reason) {
    window.offlineCheckInterval = setInterval(function() {
        if (navigator.onLine) {
            // Quick server check
            fetch('connectivity-check.php?' + new Date().getTime(), {
                method: 'HEAD',
                cache: 'no-cache'
            })
            .then(function(response) {
                if (response.ok) {
                    clearInterval(window.offlineCheckInterval);
                    handleOfflineReconnection(reason);
                }
            })
            .catch(function() {
                // Still offline
            });
        }
    }, 5000);
}

// Handle reconnection from offline modal
function handleOfflineReconnection(reason) {
    var modal = document.getElementById('offlineModal');
    if (modal) {
        var modalBody = modal.querySelector('.modal-body');
        modalBody.innerHTML = '<div class="text-center">' +
            '<i class="mdi mdi-check-circle" style="font-size: 4rem; color: #28a745;"></i>' +
            '<h4 class="mt-3 mb-3">Connection Restored</h4>' +
            '<p class="text-muted">Redirecting you back to ProVal...</p>' +
            '</div>';

        setTimeout(function() {
            $('#offlineModal').modal('hide');

            // Redirect based on reason
            if (reason === 'logout') {
                window.location.href = 'logout.php';
            } else if (reason === 'session_timeout') {
                window.location.href = 'login.php?msg=session_timeout';
            } else {
                window.location.reload();
            }
        }, 2000);
    }
}

// Manual connectivity check from offline modal
function checkConnectivityAndProceed() {
    var button = document.querySelector('#offlineModal .btn-gradient-primary');
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
                    clearInterval(window.offlineCheckInterval);
                    handleOfflineReconnection('manual');
                } else {
                    throw new Error('Server not responding');
                }
            })
            .catch(function() {
                button.innerHTML = 'Try Again';
                button.disabled = false;
                var alert = document.querySelector('#offlineModal .alert');
                alert.innerHTML = '<strong>❌ Still Offline</strong><br>Connection check failed. Please verify your network settings.';
                alert.className = 'alert alert-danger';
            });
        } else {
            button.innerHTML = 'Try Again';
            button.disabled = false;
        }
    }
}

// Function to get appropriate offline message based on reason
function getOfflineMessage(reason) {
    switch(reason) {
        case 'logout':
            return 'You were logged out but no internet connection is available.';
        case 'session_timeout':
            return 'Your session expired due to network connectivity issues.';
        case 'connection_lost':
            return 'Your internet connection was interrupted during your session.';
        default:
            return 'Please check your network connection and try again.';
    }
}
</script>