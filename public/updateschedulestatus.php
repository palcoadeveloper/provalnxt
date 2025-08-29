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

require_once 'core/config/db.class.php';
require_once 'core/security/secure_query_wrapper.php';

// Generate CSRF token if not already set
if (!isset($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Secure input validation
$sch_type = secure_get('sch_type', 'string');
$schedule_id = secure_get('schedule_id', 'int');

// Map rt to routine for backward compatibility
//if ($sch_type === 'rt') {
//    $sch_type = 'routine';
//}

// Validate required parameters
if (!in_array($sch_type, ['val', 'rt']) || !$schedule_id) {
    SecurityUtils::logSecurityEvent('invalid_parameters', 'Invalid parameters for schedule status update', [
        'sch_type' => $sch_type,
        'schedule_id' => $schedule_id
    ]);
    header('Location: home.php?error=invalid_parameters');
    exit;
}

// Secure parameterized queries using MeekroDB format
if ($sch_type == 'val') {
  $query = "SELECT proposed_sch_row_id,schedule_id, equipment_id,equipment_code,department_name,val_wf_planned_start_date
            FROM tbl_proposed_val_schedules t1 
            LEFT JOIN equipments t2 ON t1.equip_id=t2.equipment_id
            LEFT JOIN departments t3 ON t2.department_id=t3.department_id
            WHERE schedule_id=%i AND t1.unit_id=%i";
  $results = DB::query($query, $schedule_id, $_SESSION['unit_id']);
} else {
  $query = "SELECT proposed_sch_row_id,schedule_id, equipment_id,equipment_code,department_name,routine_test_wf_planned_start_date
            FROM tbl_proposed_routine_test_schedules t1 
            LEFT JOIN equipments t2 ON t1.equip_id=t2.equipment_id
            LEFT JOIN departments t3 ON t2.department_id=t3.department_id
            WHERE schedule_id=%i AND t1.unit_id=%i";
  $results = DB::query($query, $schedule_id, $_SESSION['unit_id']);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <?php include_once "assets/inc/_header.php"; ?>
  
  <!-- CSRF Token for AJAX requests -->
  <meta name="csrf-token" content="<?php echo $_SESSION['csrf_token']; ?>">

  <script>
    $(document).ready(function() {
      // Store CSRF token from PHP session in a JavaScript variable
      var csrfToken = "<?php echo $_SESSION['csrf_token']; ?>";
      
      $('.actionButton').click(function(e) {
        e.preventDefault();
        var action = $(this).data('action');
        $('#frmschedule').append('<input type="hidden" name="action" value="' + action + '">');
        
        // Set success callback for e-signature modal
        setSuccessCallback(function(response) {
          // Now proceed with the form submission
          var formData = $('#frmschedule').serialize();
          // Add CSRF token to serialized form data
          formData += '&csrf_token=' + csrfToken;

          $.ajax({
            type: 'POST',
            url: 'core/workflow/approvedenyschedulerequest.php',
            data: formData,
            success: function(response) {
              // Trim the response to remove any whitespace
              response = response.trim();
              console.log("Raw response:", response);
              
              let message = '';
              let icon = 'success';
              
              // Handle error responses
              if (response.startsWith('error:')) {
                icon = 'error';
                switch(response) {
                  case 'error:missing_params':
                    message = 'Required parameters are missing. Please try again.';
                    break;
                  case 'error:invalid_session':
                    message = 'Your session has expired. Please log in again.';
                    break;
                  case 'error:invalid_permissions':
                    message = 'You do not have permission to perform this action.';
                    break;
                  case 'error:invalid_action':
                    message = 'Invalid action requested.';
                    break;
                  case 'error:invalid_schedule_type':
                    message = 'Invalid schedule type.';
                    break;
                  case 'error:database_error':
                    message = 'A database error occurred. Please try again.';
                    break;
                  default:
                    message = 'An unexpected error occurred. Please try again.';
                }
              } else {
                // Handle success responses
                switch(response) {
                  case 'vsch_rej_edh':
                    message = 'Validation schedule rejected by Engg Dept Head';
                    break;
                  case 'vsch_rej_qah':
                    message = 'Validation schedule rejected by QA Head';
                    break;
                  case 'rsch_rej_edh':
                    message = 'Routine schedule rejected by Engg Dept Head';
                    break;
                  case 'vsch_app_edh':
                    message = 'Validation schedule approved by Engg Dept Head';
                    break;
                  case 'rsch_app_edh':
                    message = 'Routine schedule approved by Engg Dept Head';
                    break;
                  case 'rsch_rej_qah':
                    message = 'Routine schedule rejected by QA Head';
                    break;
                  case 'vsch_app_qah':
                    message = 'Validation schedule approved by QA Head';
                    break;
                  case 'rsch_app_qah':
                    message = 'Routine schedule approved by QA Head';
                    break;
                  default:
                    message = 'Operation completed successfully';
                    console.warn('Unexpected response:', response);
                }
              }

              console.log("Processed response:", response);
              console.log("Message to display:", message);
              
              Swal.fire({
                icon: icon,
                title: icon === 'success' ? 'Success' : 'Error',
                text: message
              }).then((result) => {
                if (icon === 'success') {
                  window.location.href = "assignedcases.php";
                }
              });
            },
            error: function(error) {
              console.error("Error in approvedenyschedulerequest.php:", error);
              Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'An error occurred while processing your request. Please try again.'
              });
            }
          });
        });
        
        // Show e-signature modal
        $('#enterPasswordRemark').modal('show');
      });

      // Setup AJAX global settings to include CSRF token in all requests
      $.ajaxSetup({
        beforeSend: function(xhr, settings) {
          // Only add token to POST, PUT, DELETE requests
          if (!/^(GET|HEAD|OPTIONS|TRACE)$/i.test(settings.type)) {
            // Check if the data is already a string (already serialized)
            if (typeof settings.data === 'string') {
              if (settings.data.indexOf('csrf_token') === -1) {
                settings.data += (settings.data.length > 0 ? '&' : '') + 'csrf_token=' + csrfToken;
              }
            } 
            // If it's a data object
            else if (settings.data instanceof Object && settings.data !== null) {
              settings.data = settings.data || {};
              settings.data.csrf_token = csrfToken;
            }
          }
        }
      });
    });
  </script>

  <style>
    #prgmodaladd {
      display: none;
    }
  </style>
</head>

<body>
  <?php include_once "assets/inc/_esignmodal.php"; ?>

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


          <form id='frmschedule' method='post' action='core/workflow/approvedenyschedulerequest.php'>
            <?php echo "<input type='hidden' name='sch_type' id='sch_type' value='" . htmlspecialchars($sch_type, ENT_QUOTES, 'UTF-8') . "'/>"; ?>
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <div class="row">
              <div class="col-lg-12 grid-margin stretch-card">
                <div class="card">
                  <div class="card-body">
                    <h4 class="card-title"><?php echo (($sch_type == 'val') ? "Validation" : "Routine") . " Schedule Details for Year " . htmlspecialchars(secure_get('schedule_year', 'int'), ENT_QUOTES, 'UTF-8'); ?></h4>
                    <table class="table table-bordered">
                      <thead>
                        <tr>
                          <th> Equipment Code </th>
                          <th> Department Name </th>
                          <th> Scheduled Date </th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php
                        // $results already populated above with parameters, no need to query again
                        if (empty($results)) {
                        } else {

                          foreach ($results as $row) {
                            echo "<tr>";
                            echo "<td>" . htmlspecialchars($row['equipment_code'], ENT_QUOTES, 'UTF-8') . "</td>";
                            echo "<td>" . htmlspecialchars($row['department_name'], ENT_QUOTES, 'UTF-8') . "</td>";
                            echo "<td>";

                            if ($sch_type == 'val') {
                              $formatted_date = htmlspecialchars(date('d.m.Y', strtotime($row['val_wf_planned_start_date'])), ENT_QUOTES, 'UTF-8');
                              $sch_id = htmlspecialchars($row['proposed_sch_row_id'], ENT_QUOTES, 'UTF-8');
                              echo "<input type='text' class='form-control' value='" . $formatted_date . "' name='sch-" . $sch_id . "' disabled/>";
                            } else {
                              $formatted_date = htmlspecialchars(date('d.m.Y', strtotime($row['routine_test_wf_planned_start_date'])), ENT_QUOTES, 'UTF-8');
                              $sch_id = htmlspecialchars($row['proposed_sch_row_id'], ENT_QUOTES, 'UTF-8');
                              echo "<input type='text' class='form-control' value='" . $formatted_date . "' name='sch-" . $sch_id . "' disabled/>";
                            }

                            echo "</td>";
                            echo "</tr>";
                          }
                        }
                        ?>
                        <tr>
                          <td colspan='3' style='text-align: center;'>
                            <?php echo "<input type='hidden' name='schedule_id' value='" . htmlspecialchars($row['schedule_id'], ENT_QUOTES, 'UTF-8') . "'/>"; ?>

                            <button id='btnSubmit' class='btn btn-success btn-small actionButton' aria-pressed='true' data-action="approve">Approve</button>
                            <button id='btnReject' class='btn btn-danger btn-small actionButton' aria-pressed='true' data-action="reject">Reject</button>
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
          </form>

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