<?php
// Load configuration first
require_once(__DIR__."/../../core/config/config.php");
include_once (__DIR__."/../../core/config/db.class.php");
date_default_timezone_set("Asia/Kolkata");

// Include the e-sign modal for password and remarks collection
include_once(__DIR__ . "/_esignmodal.php");

// Query to get active validations in termination process
// Build stage filter based on user role
$stageFilter = "('98', '98A', '98B', '98D', '98E')";
if (isset($_SESSION['is_qa_head']) && $_SESSION['is_qa_head'] === 'Yes') {
    // QA Head only sees requests in stage 98B (reviewed by Engg Dept Head, awaiting QA approval)
    $stageFilter = "('98B')";
} elseif (isset($_SESSION['is_dept_head']) && $_SESSION['is_dept_head'] === 'Yes'
    && isset($_SESSION['department_id']) && (int)$_SESSION['department_id'] === 1) {
    // Engineering Dept Head only sees requests in stage 98A (awaiting their review)
    $stageFilter = "('98A')";
}

$query = "SELECT
    t1.val_wf_id,
    t1.unit_id,
    t1.equipment_id,
    t2.equipment_code,
    t2.equipment_category,
    t3.val_wf_planned_start_date,
    t1.val_wf_current_stage,
    t1.stage_assigned_datetime as last_updated_on,
    u.user_name as requested_by,
    CASE
        WHEN t1.val_wf_current_stage = '98A' THEN 'Termination Requested'
        WHEN t1.val_wf_current_stage = '98B' THEN 'Reviewed by Engg Dept Head'
        WHEN t1.val_wf_current_stage = '98' THEN 'Approved by QA Head - Terminated'
        WHEN t1.val_wf_current_stage = '98D' THEN 'Rejected by Engg Dept'
        WHEN t1.val_wf_current_stage = '98E' THEN 'Rejected by QA Head'
        ELSE 'Unknown Stage'
    END as stage_description
FROM tbl_val_wf_tracking_details t1
JOIN equipments t2 ON t1.equipment_id = t2.equipment_id
JOIN tbl_val_schedules t3 ON t1.val_wf_id = t3.val_wf_id
LEFT JOIN users u ON t1.wf_initiated_by_user_id = u.user_id
WHERE t1.val_wf_current_stage IN " . $stageFilter . "
    AND t1.unit_id = ".$_SESSION['unit_id']."
    AND t2.equipment_status = 'Active'
    AND t1.val_wf_id NOT LIKE '%-TRR'
ORDER BY t1.stage_assigned_datetime DESC";

$results = DB::query($query);

echo "<div class='table-responsive'><table id='datagrid-termination' class='table table-sm table-bordered dataTable no-footer text-center'>
<thead>
<tr>
<th> # </th>
<th> Validation Workflow ID </th>
<th> Equipment Code</th>
<th> Planned Start Date</th>
<th> Termination Status</th>
<th> Requested By</th>
<th> Requested On</th>
<th> Action</th>
</tr>
</thead><tbody>";

if(empty($results))
{
    // Colspan matches number of columns (8 columns in thead)
}
else
{
    $count = 1;
    foreach ($results as $row) {

        echo "<tr>";
        echo "<td>".$count."</td>";
        echo "<td>".$row["val_wf_id"]." </td>";
        echo "<td>".$row["equipment_code"]." </td>";
        echo "<td>".date_format(date_create($row["val_wf_planned_start_date"]),"d.m.Y")." </td>";

        // Termination Status column - show badges based on stage
        echo "<td>";
        if ($row["val_wf_current_stage"] == '98') {
            echo "<span class='badge badge-success'>Approved - Terminated</span>";
        } elseif ($row["val_wf_current_stage"] == '98D') {
            echo "<span class='badge badge-danger'>Rejected by Engineering Dept</span>";
        } elseif ($row["val_wf_current_stage"] == '98E') {
            echo "<span class='badge badge-danger'>Rejected by QA Head</span>";
        } else {
            // For pending stages (98A, 98B), show the stage description
            echo "<span class='badge badge-warning'>".$row["stage_description"]."</span>";
        }
        echo "</td>";

        echo "<td>".htmlspecialchars($row["requested_by"] ?? 'N/A', ENT_QUOTES, 'UTF-8')." </td>";
        echo "<td>".date_format(date_create($row["last_updated_on"]),"d.m.Y H:i")." </td>";

        // Action buttons based on current stage and user permissions
        echo "<td>";

        // View details button for all users
        echo "<a href='#' data-toggle='modal' data-target='#viewProtocolModal' data-load-url='viewtestdetails_modal.php?equipment_id=".$row["equipment_id"]."&val_wf_id=".$row["val_wf_id"]."' class='btn btn-sm btn-gradient-info btn-icon-text' role='button' aria-pressed='true'>View Details</a> ";

        // Action buttons based on user role and current stage (only for pending stages)
        if ($_SESSION['is_dept_head'] == 'Yes' && $_SESSION['department_id'] == '1' && $row["val_wf_current_stage"] == '98A') {
            // Engineering Dept Head can review termination requests
            echo "<a href='#' onclick='reviewTermination(\"".$row["val_wf_id"]."\", \"98B\")' class='btn btn-sm btn-gradient-success' role='button'>Review & Forward</a> ";
            echo "<a href='#' onclick='rejectTerminationByEngg(\"".$row["val_wf_id"]."\")' class='btn btn-sm btn-gradient-danger' role='button'>Reject</a>";
        } elseif ($_SESSION['is_qa_head'] == 'Yes' && $row["val_wf_current_stage"] == '98B') {
            // QA Head can approve engineering dept head reviewed requests
            echo "<a href='#' onclick='approveTerminationByQA(\"".$row["val_wf_id"]."\")' class='btn btn-sm btn-gradient-success' role='button'>Approve & Terminate</a> ";
            echo "<a href='#' onclick='rejectTerminationByQA(\"".$row["val_wf_id"]."\")' class='btn btn-sm btn-gradient-danger' role='button'>Reject</a>";
        }

        echo "</td>";
        echo "</tr>";
        $count++;
    }
}

echo " </tbody>
                    </table></div>";

?>

<script>
// Function for Engineering Dept Head to review termination
function reviewTermination(valWfId, nextStage) {
    Swal.fire({
        title: 'Review Termination Request?',
        text: 'Forward this termination request to QA Head for approval?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#28a745',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, Review & Forward!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            // Configure the remarks modal
            configureRemarksModal(
                'review_termination', // action
                'core/data/update/review_termination.php', // endpoint
                {
                    val_wf_id: valWfId,
                    next_stage: nextStage,
                    csrf_token: $('meta[name="csrf-token"]').attr('content')
                },
                function(response) {
                    // Success callback
                    Swal.fire({
                        icon: 'success',
                        title: 'Reviewed!',
                        text: 'The termination request has been reviewed and forwarded to QA Head.'
                    }).then(() => {
                        location.reload();
                    });
                }
            );

            // Show the modal
            $('#enterPasswordRemark').modal('show');
        }
    });
}

// Function for QA Head to approve and terminate
function approveTerminationByQA(valWfId) {
    Swal.fire({
        title: 'Approve Termination?',
        text: 'This will permanently terminate the validation workflow.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#28a745',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, Approve & Terminate!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            // Configure the remarks modal
            configureRemarksModal(
                'approve_termination', // action
                'core/data/update/approve_termination_qa.php', // endpoint
                {
                    val_wf_id: valWfId,
                    csrf_token: $('meta[name="csrf-token"]').attr('content')
                },
                function(response) {
                    // Success callback
                    Swal.fire({
                        icon: 'success',
                        title: 'Approved & Terminated!',
                        text: 'The validation has been successfully terminated.'
                    }).then(() => {
                        location.reload();
                    });
                }
            );

            // Show the modal
            $('#enterPasswordRemark').modal('show');
        }
    });
}

// Function for Engineering Dept to reject termination
function rejectTerminationByEngg(valWfId) {
    Swal.fire({
        title: 'Reject Termination?',
        text: 'Are you sure you want to reject this termination request? Please provide a reason:',
        icon: 'warning',
        input: 'textarea',
        inputPlaceholder: 'Enter rejection reason...',
        inputValidator: (value) => {
            if (!value) {
                return 'Please provide a reason for rejection!'
            }
        },
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, Reject!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            const rejectionReason = result.value;

            // Configure the password modal
            configureRemarksModal(
                'reject_termination_engg', // action
                'core/data/update/reject_termination_engg.php', // endpoint
                {
                    val_wf_id: valWfId,
                    rejection_reason: rejectionReason,
                    csrf_token: $('meta[name="csrf-token"]').attr('content')
                },
                function(response) {
                    // Success callback
                    Swal.fire({
                        icon: 'success',
                        title: 'Rejected!',
                        text: 'The termination request has been rejected by Engineering Department.'
                    }).then(() => {
                        location.reload();
                    });
                }
            );

            // Show the password modal
            $('#enterPasswordRemark').modal('show');
        }
    });
}

// Function for QA Head to reject termination
function rejectTerminationByQA(valWfId) {
    Swal.fire({
        title: 'Reject Termination?',
        text: 'Are you sure you want to reject this termination request? Please provide a reason:',
        icon: 'warning',
        input: 'textarea',
        inputPlaceholder: 'Enter rejection reason...',
        inputValidator: (value) => {
            if (!value) {
                return 'Please provide a reason for rejection!'
            }
        },
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, Reject!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            const rejectionReason = result.value;

            // Configure the password modal
            configureRemarksModal(
                'reject_termination_qa', // action
                'core/data/update/reject_termination_qa.php', // endpoint
                {
                    val_wf_id: valWfId,
                    rejection_reason: rejectionReason,
                    csrf_token: $('meta[name="csrf-token"]').attr('content')
                },
                function(response) {
                    // Success callback
                    Swal.fire({
                        icon: 'success',
                        title: 'Rejected!',
                        text: 'The termination request has been rejected by QA Head.'
                    }).then(() => {
                        location.reload();
                    });
                }
            );

            // Show the password modal
            $('#enterPasswordRemark').modal('show');
        }
    });
}

</script>