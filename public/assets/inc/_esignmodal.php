<?php
// Ensure the session is started if not already
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
?>

<!-- Add Remarks Modal - Unified Implementation -->
<div class="modal fade" id="enterPasswordRemark" tabindex="-1" role="dialog" aria-labelledby="passwordModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-responsive" role="document">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title" id="passwordModalTitle">Enter your password and remarks</h5>
        <button type="button" id="modalbtncross" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>

      <form id="formmodalvalidation" class="needs-validation" novalidate>
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" id="sendBackAction" value="0">

        <div class="modal-body" style="padding: 20px;">
          <div class="form-group" style="margin-bottom: 20px;">
            <label for="user_password" style="margin-bottom: 8px; display: block;">Account Password</label>
            <input type="password" 
                   class="form-control" 
                   id="user_password" 
                   required 
                   autocomplete="current-password"
                   inputmode="text"
                   spellcheck="false"
                   autocapitalize="off"
                   autocorrect="off"
                   style="margin-bottom: 5px;">
            <div class="invalid-feedback">Password is required.</div>
          </div>
          <div class="form-group" style="margin-bottom: 15px;">
            <label for="user_remark" style="margin-bottom: 8px; display: block;">Remarks</label>
            <textarea class="form-control" id="user_remark" rows="3" required maxlength="500" style="margin-bottom: 5px;"></textarea>
            <div class="invalid-feedback">Remarks is required.</div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" id="mdlbtnclose" class="btn btn-secondary" data-dismiss="modal">Close</button>
          <button type="submit" id="mdlbtnsubmit" class="btn btn-primary">Proceed</button>
        </div>
      </form>

    </div>
  </div>
</div>

<!-- JavaScript -->
<script>
  // Global variables to store action context
  var remarksModalContext = {
    action: null,
    endpoint: null,
    data: {},
    successCallback: null
  };
  
  /**
   * Configure the modal for a specific action
   * @param {string} action - The action type (e.g., 'offline_approve', 'offline_reject')
   * @param {string} endpoint - The PHP endpoint to call
   * @param {Object} data - Additional data to send
   * @param {Function} successCallback - Callback for successful operations
   */
  function configureRemarksModal(action, endpoint, data = {}, successCallback = null) {
    remarksModalContext.action = action;
    remarksModalContext.endpoint = endpoint;
    remarksModalContext.data = data;
    remarksModalContext.successCallback = successCallback;
    
    console.log('Modal configured for action:', action, 'with data:', data);
  }
  
  /**
   * Unified response handler for all modal operations
   * @param {Object} response - The server response
   * @param {Function} customSuccessCallback - Optional custom success callback
   */
  function handleRemarksResponse(response, customSuccessCallback = null) {
    // Update CSRF token if provided
    if (response.csrf_token) {
      $("input[name='csrf_token']").val(response.csrf_token);
    }
    
    // Handle force redirect (account locked)
    if (response.forceRedirect && response.redirect) {
      $('#enterPasswordRemark').modal('hide');
      Swal.fire({
        icon: 'error',
        title: 'Account Locked',
        text: "Your account has been locked. Please contact admin.",
        showConfirmButton: true
      }).then(() => window.location.href = response.redirect);
      return;
    }

    // Handle regular redirect
    if (response.redirect && !response.forceRedirect) {
      $('#enterPasswordRemark').modal('hide');
      Swal.fire({
        icon: 'info',
        title: 'Redirecting',
        text: "Click OK to continue.",
        showConfirmButton: true
      }).then(() => window.location.href = response.redirect);
      return;
    }

    // Handle success
    if (response.status === "success") {
      $('#enterPasswordRemark').modal('hide');

      if (typeof customSuccessCallback === 'function') {
        customSuccessCallback(response);
      } else if (typeof remarksModalContext.successCallback === 'function') {
        remarksModalContext.successCallback(response);
      } else if (typeof onSuccessCallback === 'function') {
        // Legacy callback support
        onSuccessCallback(response);
        onSuccessCallback = null; // Clear after use
      } else {
        // Default success behavior
        Swal.fire({
          icon: 'success',
          title: 'Success',
          text: response.message || "Operation completed successfully."
        }).then(() => {
          window.location.reload();
        });
      }

      resetModalUI(true); // Clear everything on success
    } else {
      // Handle errors
      let msg = "An error occurred.";
      let shouldCloseModal = true; // Default to closing modal
      
      if (response.message === "invalid_credentials") {
        msg = "Incorrect password. " + (response.attempts_left ? `Attempts left: ${response.attempts_left}` : "");
        shouldCloseModal = false; // Keep modal open for retry
      } else if (response.message === "account_locked") {
        msg = "Account locked. Please contact administrator.";
        shouldCloseModal = true;
      } else if (response.message === "security_error") {
        msg = "Security error. Refresh the page and try again.";
        shouldCloseModal = true;
      } else {
        msg = response.message || "An error occurred while processing the request";
        shouldCloseModal = true;
      }
      
      if (shouldCloseModal) {
        $('#enterPasswordRemark').modal('hide');
      }
      
      Swal.fire({ icon: 'error', title: 'Error', text: msg });
      
      // Reset UI appropriately
      resetModalUI(shouldCloseModal);
    }
  }
  
  /**
   * Reset the modal UI
   * @param {boolean} clearRemarks - Whether to clear the remarks field
   */
  function resetModalUI(clearRemarks = true) {
    // Always clear password for security
    const passwordInput = document.getElementById('user_password');
    passwordInput.value = '';
    passwordInput.blur();
    
    // Re-enable buttons
    $("#mdlbtnsubmit").html("Proceed").prop('disabled', false);
    $("#mdlbtnclose, #modalbtncross").prop('disabled', false);
    
    // Only clear remarks if specified (preserve on retry for invalid credentials)
    if (clearRemarks) {
      $("#user_remark").val("");
    }
  }
  
  /**
   * Submit the modal form
   */
  $("#mdlbtnsubmit").on('click', function(e) {
    e.preventDefault();
    
    // Get the form element
    const form = document.getElementById('formmodalvalidation');
    
    // Check if the form is valid
    if (form.checkValidity() === false) {
      e.stopPropagation();
      form.classList.add('was-validated');
      return;
    }
    
    // Get form data
    const remark = $("#user_remark").val().trim();
    const password = $("#user_password").val().trim();
    const csrfToken = $("input[name='csrf_token']").val();
    
    if (!remarksModalContext.action || !remarksModalContext.endpoint) {
      Swal.fire({
        icon: 'error',
        title: 'Error',
        text: 'Modal not properly configured. Please try again.'
      });
      return;
    }
    
    // Immediately clear the password from the input field for security
    const passwordInput = document.getElementById('user_password');
    passwordInput.value = '';
    passwordInput.blur();
    
    // Disable UI during processing
    $("#mdlbtnsubmit").html("Please wait...").prop('disabled', true);
    $("#mdlbtnclose, #modalbtncross").prop('disabled', true);

    // Prepare request data
    const requestData = {
      csrf_token: csrfToken,
      action: remarksModalContext.action,
      user_remark: remark,
      user_password: password,
      ...remarksModalContext.data // Merge additional data
    };
    
    console.log('Submitting modal request:', {
      endpoint: remarksModalContext.endpoint,
      action: remarksModalContext.action,
      dataKeys: Object.keys(requestData)
    });
    
    // Make AJAX request
    $.ajax({
      url: remarksModalContext.endpoint,
      type: "POST",
      dataType: "json",
      data: requestData,
      success: function(response) {
        // Parse JSON response if it's a string
        let parsedResponse;
        try {
          parsedResponse = typeof response === 'string' ? JSON.parse(response) : response;
        } catch (e) {
          console.error("JSON parse error:", e, response);
          parsedResponse = { status: 'error', message: 'Invalid response format' };
        }
        
        handleRemarksResponse(parsedResponse);
      },
      error: function(xhr, status, error) {
        console.error('AJAX Error Details:', {
          status: status,
          error: error,
          responseText: xhr.responseText,
          responseStatus: xhr.status,
          endpoint: remarksModalContext.endpoint,
          requestData: {
            endpoint: remarksModalContext.endpoint,
            action: remarksModalContext.action,
            dataKeys: Object.keys(remarksModalContext.data)
          }
        });
        
        // Try to parse error response for CSRF token
        try {
          const errorResponse = JSON.parse(xhr.responseText);
          if (errorResponse.csrf_token) {
            $("input[name='csrf_token']").val(errorResponse.csrf_token);
          }
          
          // Handle parsed error response
          handleRemarksResponse(errorResponse);
        } catch (e) {
          // Fallback for unparseable errors
          console.error('Failed to parse error response:', e);
          console.error('Raw error response:', xhr.responseText);
          
          resetModalUI(true);
          $('#enterPasswordRemark').modal('hide');
          
          // Show more detailed error message in development
          let errorMsg = 'An error occurred while processing your request.';
          if (xhr.status === 0) {
            errorMsg += ' (Network error - please check your connection)';
          } else if (xhr.status >= 500) {
            errorMsg += ' (Server error - please try again later)';
          } else if (xhr.status === 404) {
            errorMsg += ' (Endpoint not found)';
          } else if (xhr.status === 403) {
            errorMsg += ' (Access denied)';
          }
          
          Swal.fire({
            icon: 'error',
            title: 'Error',
            text: errorMsg + ' Please check the console for details.'
          });
        }
      }
    });
  });

  // Clear password on modal close for security
  $('#enterPasswordRemark').on('hidden.bs.modal', function () {
    const passwordInput = document.getElementById('user_password');
    passwordInput.value = '';
    passwordInput.blur();
    
    // Reset form validation
    document.getElementById('formmodalvalidation').classList.remove('was-validated');
  });

  // Prevent password field from being copied or pasted
  document.getElementById('user_password').addEventListener('copy', function(e) {
    e.preventDefault();
    return false;
  });

  document.getElementById('user_password').addEventListener('paste', function(e) {
    e.preventDefault();
    return false;
  });

  // OFFLINE TEST REVIEW INTEGRATION
  // Configure modal when offline buttons are clicked
  $(document).ready(function() {
    // Only set up offline handlers if the buttons exist
    if ($("#offline_approve").length || $("#offline_reject").length) {
      
      $("#offline_approve").click(function() {
        // Get parameters from the current URL since GET params aren't available
        const urlParams = new URLSearchParams(window.location.search);
        
        configureRemarksModal(
          'approve', // action
          'core/data/update/offline_test_review.php', // endpoint
          {
            test_wf_id: urlParams.get('test_val_wf_id') || '',
            val_wf_id: urlParams.get('val_wf_id') || '',
            test_id: urlParams.get('test_id') || ''
          },
          function(response) {
            // Custom success callback for offline approve
            Swal.fire({
              icon: 'success',
              title: 'Success',
              text: response.message || 'Test approved successfully'
            }).then(() => {
              // Redirect to assigned cases after approval
              window.location.href = 'assignedcases.php';
            });
          }
        );
        
        // Check for blocking conditions
        if ($(".navlink-approve")[0]) {
          Swal.fire({
            icon: 'error',
            title: 'Oops...',
            text: 'You have one or more files to be approved.'
          });
        } else {
          $('#enterPasswordRemark').modal('show');
        }
      });
      
      $("#offline_reject").click(function() {
        // Get parameters from the current URL since GET params aren't available
        const urlParams = new URLSearchParams(window.location.search);
        
        configureRemarksModal(
          'reject', // action
          'core/data/update/offline_test_review.php', // endpoint
          {
            test_wf_id: urlParams.get('test_val_wf_id') || '',
            val_wf_id: urlParams.get('val_wf_id') || '',
            test_id: urlParams.get('test_id') || ''
          },
          function(response) {
            // Custom success callback for offline reject
            Swal.fire({
              icon: 'success',
              title: 'Success',
              text: response.message || 'Test rejected successfully'
            }).then(() => {
              // Redirect to assigned cases after rejection
              window.location.href = 'assignedcases.php';
            });
          }
        );
        
        // Check for blocking conditions
        if ($(".navlink-approve")[0]) {
          Swal.fire({
            icon: 'error',
            title: 'Oops...',
            text: 'You have one or more files to be approved.'
          });
        } else {
          $('#enterPasswordRemark').modal('show');
        }
      });
    }
  });

  // LEGACY SUCCESS CALLBACK SUPPORT
  // Variable to store success callback function for backward compatibility
  var onSuccessCallback = null;

  // Function to set the success callback (legacy support)
  function setSuccessCallback(callback) {
    onSuccessCallback = callback;

    // Configure modal context for legacy compatibility
    // Use the standard addremarks endpoint for password verification and remarks
    remarksModalContext.action = 'add_remark';
    remarksModalContext.endpoint = 'core/validation/addremarks.php';
    remarksModalContext.successCallback = callback;

    // Get URL parameters for legacy data support
    const urlParams = new URLSearchParams(window.location.search);
    remarksModalContext.data = {
      wf_id: urlParams.get('val_wf_id') || '',
      test_wf_id: urlParams.get('test_val_wf_id') || '',
      equip_id: urlParams.get('equip_id') || '',
      operation_context: typeof operation_context !== 'undefined' ? operation_context : '',
      status_from: typeof window.statusChangeData !== 'undefined' && window.statusChangeData.status_from ? window.statusChangeData.status_from : '',
      status_to: typeof window.statusChangeData !== 'undefined' && window.statusChangeData.status_to ? window.statusChangeData.status_to : ''
    };

    console.log('Success callback set via legacy setSuccessCallback function with context:', {
      action: remarksModalContext.action,
      endpoint: remarksModalContext.endpoint,
      dataKeys: Object.keys(remarksModalContext.data)
    });
  }

  // LEGACY SUPPORT FUNCTION
  // Keep the old adduserremark function for backward compatibility
  function adduserremark(ur, up) {
    console.log('Legacy adduserremark called - redirecting to new implementation');
    
    // For backward compatibility, configure modal with default values
    const urlParams = new URLSearchParams(window.location.search);
    
    configureRemarksModal(
      'add_remark', // action
      'core/validation/addremarks.php', // endpoint
      {
        wf_id: urlParams.get('val_wf_id') || '',
        test_wf_id: urlParams.get('test_val_wf_id') || '',
        operation_context: typeof operation_context !== 'undefined' ? operation_context : '',
        status_from: typeof window.statusChangeData !== 'undefined' && window.statusChangeData.status_from ? window.statusChangeData.status_from : '',
        status_to: typeof window.statusChangeData !== 'undefined' && window.statusChangeData.status_to ? window.statusChangeData.status_to : ''
      }
    );
    
    // Pre-fill the form
    $("#user_remark").val(ur || '');
    $("#user_password").val(up || '');
    
    // Trigger the submit process
    $("#mdlbtnsubmit").trigger('click');
  }
</script>