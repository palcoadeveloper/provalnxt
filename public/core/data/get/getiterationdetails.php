<?php

// Load configuration first
require_once(__DIR__ . '/../../config/config.php');
if(!isset($_SESSION))
{
    session_start();

// Validate session timeout
require_once('../../security/session_timeout_middleware.php');
validateActiveSession();
} 

// DB class already included by parent file
date_default_timezone_set("Asia/Kolkata");

$query = "SELECT 
    t1.iteration_id,
    DATE_FORMAT(t1.iteration_start_datetime, '%d.%m.%Y %H:%i:%s') AS iteration_start_datetime,
    u2.user_name AS initiated_by,
    CASE 
        WHEN t1.iteration_id = (
            SELECT MAX(t2.iteration_id)
            FROM tbl_val_wf_approval_tracking_details t2
            WHERE t2.val_wf_id = t1.val_wf_id
            AND (t2.iteration_completion_status = 'pending' OR t2.iteration_completion_status = 'complete')
            AND t2.iteration_status = 'Active'
        ) THEN 'Y'
        ELSE 'N'
    END AS is_current_iteration,
    t1.iteration_completion_status,
    u1.user_name AS sent_back_by
FROM 
    tbl_val_wf_approval_tracking_details t1
LEFT JOIN 
    users u1 ON t1.iteration_rejected_by = u1.user_id
LEFT JOIN 
    users u2 ON t1.engg_app_sbmitted_by = u2.user_id
WHERE 
    t1.val_wf_id = '" . $_GET["val_wf_id"] . "'
    AND t1.iteration_id IS NOT NULL
ORDER BY 
    t1.iteration_id DESC";

$results = DB::query($query);

$output = "<style>
    .centered-table td, .centered-table th {
        text-align: center;
        vertical-align: middle;
    }
</style>";

$output .= "<table class='table table-bordered centered-table'><tr><th>Iteration #</th><th>Initiated Date Time</th><th>Initiated By</th><th>Current Iteration?</th><th>Iteration Status</th><th>Iteration Sent Back By</th><th>Actions</th></tr>";
if(empty($results))
{
    $output = $output . "<tr><td colspan='7'>Nothing to display.</td></tr>";
}
else
{
    foreach ($results as $row) {
        $output = $output . "<tr>";
        $output = $output . "<td>" . $row['iteration_id'] . "</td>";
        $output = $output . "<td>" . $row['iteration_start_datetime'] . "</td>";
        $output = $output . "<td>" . $row['initiated_by'] . "</td>";
      // $output = $output . "<td>" . $row['is_current_iteration'] . "</td>";
       $output = $output . "<td>" . (isset($row['is_current_iteration']) && $row['is_current_iteration'] == 'N' ? 'No' : 'Yes') . "</td>";
        $output = $output . "<td>" . $row['iteration_completion_status'] . "</td>";
        $output = $output . "<td>" . (!empty($row['sent_back_by']) ? $row['sent_back_by'] : "-") . "</td>";
        $output = $output . "<td><a href='#' class='btn btn-info btn-sm view-details' data-toggle='modal' data-target='#approvalDetailsModal' data-val-wf-id='" . $_GET["val_wf_id"] . "' data-iteration-id='" . $row['iteration_id'] . "'>View Details</a></td>";
        $output = $output . "</tr>";
    }
}
$output = $output . "</table>";

// Add the modal for approval details
$output .= <<<EOT
<!-- Approval Details Modal -->
<div class="modal fade" id="approvalDetailsModal" tabindex="-1" role="dialog" aria-labelledby="approvalDetailsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="approvalDetailsModalLabel">Approval Details</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div id="approvalDetailsContent" class="table-responsive">
          <!-- Content will be loaded here via AJAX -->
          <div class="text-center">
            <div class="spinner-border text-primary" role="status">
              <span class="sr-only">Loading...</span>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script>
$(document).ready(function() {
    $('.view-details').on('click', function() {
        var valWfId = $(this).data('val-wf-id');
        var iterationId = $(this).data('iteration-id');
        
        // Load approval details via AJAX
        $.ajax({
            url: 'core/data/get/getapprovaldetails.php',
            type: 'GET',
            data: {
                val_wf_id: valWfId,
                iteration_id: iterationId,
                csrf_token: $("input[name='csrf_token']").val()
            },
            success: function(response) {
                $('#approvalDetailsContent').html(response);
            },
            error: function() {
                $('#approvalDetailsContent').html('<div class="alert alert-danger">Error loading approval details.</div>');
            }
        });
    });
});
</script>
EOT;

echo $output;
?>