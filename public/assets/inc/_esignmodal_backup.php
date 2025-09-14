<?php
// Ensure the session is started if not already
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
?>

<!-- Modal -->
<div class="modal fade" id="enterPasswordRemark" tabindex="-1" role="dialog" aria-labelledby="passwordModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-responsive" role="document">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title" id="passwordModalTitle">Enter your password and remarks</h5>
        <button type="button" id="modalbtncross" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>

      <!-- Spinner loader -->
      <!-- <div class="d-flex justify-content-center" id="prgmodaladd" style="display: none;">
        <div class="spinner-grow text-primary" style="width: 3rem; height: 3rem;" role="status">
          <span class="sr-only">Loading...</span>
        </div>
      </div> -->

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
  // Variable to store success callback function
  var onSuccessCallback = null;
  
  // Function to set the success callback
  function setSuccessCallback(callback) {
    onSuccessCallback = callback;
  }
  
  $("#mdlbtnsubmit").on('click', function(e) {
    e.preventDefault();
    
    // Get the form element
    const form = document.getElementById('formmodalvalidation');
    
    // Check if the form is valid
    if (form.checkValidity() === false) {
      e.stopPropagation();
      // Add the was-validated class to show Bootstrap's validation feedback
      form.classList.add('was-validated');
      return;
    }
    
    // If we get here, the form is valid
    const remark = $("#user_remark").val().trim();
    const password = $("#user_password").val().trim();

    adduserremark(remark, password);
  });

  /**
   * Unified response handler for all modal operations
   * @param {Object} parsedResponse - The parsed JSON response from the server
   * @param {Function} successCallback - Optional success callback function
   */
  function handleModalResponse(parsedResponse, successCallback = null) {
    // Update CSRF token in all forms if a new one was provided
    if (parsedResponse.csrf_token) {
      $("input[name='csrf_token']").val(parsedResponse.csrf_token);
    }
    
    // Handle force redirect (account locked)
    if (parsedResponse.forceRedirect && parsedResponse.redirect) {
      $('#enterPasswordRemark').modal('hide');
      Swal.fire({
        icon: 'error',
        title: 'Account Locked',
        text: "Your account has been locked. Please contact admin.",
        showConfirmButton: true
      }).then(() => window.location.href = parsedResponse.redirect);
      return;
    }

    // Handle regular redirect
    if (parsedResponse.redirect) {
      $('#enterPasswordRemark').modal('hide');
      Swal.fire({
        icon: 'info',
        title: 'Redirecting',
        text: "Click OK to continue.",
        showConfirmButton: true
      }).then(() => window.location.href = parsedResponse.redirect);
      return;
    }

    // Handle success
    if (parsedResponse.status === "success") {
      $('#enterPasswordRemark').modal('hide');
      
      if (typeof successCallback === 'function') {
        successCallback(parsedResponse);
      } else {
        // Default success behavior
        Swal.fire({
          icon: 'success',
          title: 'Success',
          text: parsedResponse.message || "Operation completed successfully."
        }).then(() => {
          if (typeof url !== 'undefined') {
            window.location.href = url;
          } else {
            window.location.reload();
          }
        });
      }
    } else {
      // Handle errors
      let msg = "An error occurred.";
      let shouldCloseModal = false;
      
      if (parsedResponse.message === "invalid_credentials") {
        msg = "Incorrect password. " + (parsedResponse.attempts_left ? `Attempts left: ${parsedResponse.attempts_left}` : "");
        shouldCloseModal = false; // Keep modal open for retry
      } else if (parsedResponse.message === "account_locked") {
        msg = "Account locked. Please contact administrator.";
        shouldCloseModal = true;
      } else if (parsedResponse.message === "security_error") {
        msg = "Security error. Refresh the page and try again.";
        shouldCloseModal = true;
      } else {
        msg = parsedResponse.message || "An error occurred while processing the request";
        shouldCloseModal = true;
      }
      
      if (shouldCloseModal) {
        $('#enterPasswordRemark').modal('hide');
      }
      
      Swal.fire({ icon: 'error', title: 'Error', text: msg });
      
      // Don't clear remarks for invalid credentials (keep modal open for retry)
      resetModalUI(shouldCloseModal);
    } else {
      // For successful operations, always clear everything
      resetModalUI(true);
    }
  }

  function adduserremark(ur, up) {
    console.log('adduserremark called with remark:', ur ? 'present' : 'missing');
    console.log('Workflow IDs available:', {
      val_wf_id: typeof val_wf_id !== 'undefined' ? val_wf_id : 'undefined',
      val_wf_id_modal: typeof val_wf_id_modal !== 'undefined' ? val_wf_id_modal : 'undefined',
      test_val_wf_id: typeof test_val_wf_id !== 'undefined' ? test_val_wf_id : 'undefined'
    });
    
    const csrfToken = $("input[name='csrf_token']").val();
    
    // Immediately clear the password from the input field
    const passwordInput = document.getElementById('user_password');
    const password = passwordInput.value;
    passwordInput.value = '';
    passwordInput.blur();
    
    // Disable UI
    //$("#prgmodaladd").show();
    $("#mdlbtnsubmit").html("Please wait...").prop('disabled', true);
    $("#mdlbtnclose, #modalbtncross").prop('disabled', true);

    // Create a temporary variable that will be cleared after use
    let tempPassword = password;
    
    // Check if this is an offline test review action
    if (typeof offline_action !== 'undefined' && typeof url !== 'undefined' && url.includes('offline_test_review.php')) {
      // Handle offline test review
      $.ajax({
        url: url,
        type: "POST",
        dataType: "json",
        data: {
          csrf_token: csrfToken,
          action: offline_action,
          test_wf_id: typeof test_val_wf_id !== 'undefined' ? test_val_wf_id : '',
          val_wf_id: typeof val_wf_id !== 'undefined' ? val_wf_id : (typeof val_wf_id_modal !== 'undefined' ? val_wf_id_modal : ''),
          test_id: typeof test_id !== 'undefined' ? test_id : '',
          user_remark: ur,
          user_password: tempPassword
        },
        success: function(response) {
          // Clear the temporary password variable first
          tempPassword = null;
          
          // Parse JSON response if it's a string
          let parsedResponse;
          try {
            parsedResponse = typeof response === 'string' ? JSON.parse(response) : response;
          } catch (e) {
            console.error("JSON parse error:", e, response);
            parsedResponse = { status: 'error', message: 'Invalid response format' };
          }
          
          // Use unified handler with offline-specific success callback
          const offlineSuccessCallback = (parsedResponse) => {
            Swal.fire({
              icon: 'success',
              title: 'Success',
              text: parsedResponse.message || 'Test ' + offline_action + 'd successfully'
            }).then(() => {
              window.location.reload(); // Reload to show updated status
            });
          };
          
          handleModalResponse(parsedResponse, offlineSuccessCallback);
        },
        error: function(xhr, status, error) {
          // Clear the temporary password variable
          tempPassword = null;
          
          // Handle error
          $("#mdlbtnsubmit").html("Proceed").prop('disabled', false);
          $("#mdlbtnclose, #modalbtncross").prop('disabled', false);
          
          // Try to parse error response for CSRF token
          try {
            const errorResponse = JSON.parse(xhr.responseText);
            if (errorResponse.csrf_token) {
              $("input[name='csrf_token']").val(errorResponse.csrf_token);
            }
          } catch (e) {
            console.error("Error parsing response:", e);
          }
          
          // Show error message
          $('#enterPasswordRemark').modal('hide');
          Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'An error occurred while processing your request. Please try again.'
          });
        }
      });
      return; // Exit early for offline test review
    }
    
    // Original logic for regular remark submission
    $.ajax({
      url: "core/validation/addremarks.php",
      type: "POST",
      dataType: "json",
      data: {
        csrf_token: csrfToken,
        user_remark: ur,
        user_password: tempPassword,
        wf_id: typeof val_wf_id !== 'undefined' ? val_wf_id : (typeof val_wf_id_modal !== 'undefined' ? val_wf_id_modal : ''),
        test_wf_id: typeof test_val_wf_id !== 'undefined' ? test_val_wf_id : '',
        operation_context: typeof operation_context !== 'undefined' ? operation_context : '',
        status_from: typeof window.statusChangeData !== 'undefined' && window.statusChangeData.status_from ? window.statusChangeData.status_from : '',
        status_to: typeof window.statusChangeData !== 'undefined' && window.statusChangeData.status_to ? window.statusChangeData.status_to : ''
      },
      success: function(response) {
        // Clear the temporary password variable first
        tempPassword = null;
        
        // Parse JSON response if it's a string
        let parsedResponse;
        try {
          parsedResponse = typeof response === 'string' ? JSON.parse(response) : response;
        } catch (e) {
          console.error("JSON parse error:", e, response);
          // Handle non-JSON response for backward compatibility
          if (response.trim() === "success") {
            parsedResponse = { status: 'success' };
          } else {
            parsedResponse = { status: 'error', message: 'invalid_credentials' };
          }
        }
        
        // Create standard success callback
        const standardSuccessCallback = (parsedResponse) => {
          if (typeof onSuccessCallback === 'function') {
            onSuccessCallback(parsedResponse);
          } else {
            // Default behavior if no callback is set
            Swal.fire({
              icon: 'success',
              title: 'Success',
              text: "Data saved successfully."
            }).then(() => {
              if (typeof url !== 'undefined') {
                window.location.href = url;
              }
            });
          }
        };
        
        // Use unified handler
        handleModalResponse(parsedResponse, standardSuccessCallback);
      },
      error: function(xhr, status, error) {
        // Handle error
        $("#mdlbtnsubmit").html("Proceed").prop('disabled', false);
        $("#mdlbtnclose, #modalbtncross").prop('disabled', false);
        
        // Try to parse error response for CSRF token
        try {
          const errorResponse = JSON.parse(xhr.responseText);
          if (errorResponse.csrf_token) {
            $("input[name='csrf_token']").val(errorResponse.csrf_token);
          }
        } catch (e) {
          console.error("Error parsing response:", e);
        }
        
        // Show error message
        Swal.fire({
          icon: 'error',
          title: 'Error',
          text: 'An error occurred while processing your request. Please try again.'
        });
      }
    });
  }

  function resetModalUI(clearRemarks = true) {
    // Clear password field and ensure it's not stored in memory
    const passwordInput = document.getElementById('user_password');
    passwordInput.value = '';
    passwordInput.blur();
    
    $("#mdlbtnsubmit").html("Proceed").prop('disabled', false);
    $("#mdlbtnclose, #modalbtncross").prop('disabled', false);
    
    // Only clear remarks if specified (don't clear on retry for invalid credentials)
    if (clearRemarks) {
      $("#user_remark").val("");
    }
  }

  // Add event listener to clear password on modal close
  $('#enterPasswordRemark').on('hidden.bs.modal', function () {
    const passwordInput = document.getElementById('user_password');
    passwordInput.value = '';
    passwordInput.blur();
  });

  // Prevent password field from being copied
  document.getElementById('user_password').addEventListener('copy', function(e) {
    e.preventDefault();
    return false;
  });

  // Prevent password field from being pasted
  document.getElementById('user_password').addEventListener('paste', function(e) {
    e.preventDefault();
    return false;
  });
</script>