<?php
require_once('./core/config/config.php');

// Optimized session validation - single comprehensive check
require_once('core/security/optimized_session_validation.php');
OptimizedSessionValidation::validateOnce();

require_once("core/config/db.class.php");

// Load APCu caching system
require_once('core/utils/ProValCache.php');

// Dashboard Configuration Constants
define('DEPT_ENGINEERING', 1);
define('DEPT_QA', 8);
define('DEPT_QC', 0);
define('DEPT_MICROBIOLOGY', 6);
define('DEPT_HSE', 7);

define('STAGE_NEW_TASK', '1');
define('STAGE_PENDING_APPROVAL', '2');
define('STAGE_UNIT_HEAD_APPROVAL', '3');
define('STAGE_QA_HEAD_APPROVAL', '4');
define('STAGE_COMPLETED', '5');
define('STAGE_REASSIGNED_A', '3A');
define('STAGE_REASSIGNED_B', '3B');
define('STAGE_REASSIGNED_4B', '4B');

// Simple in-memory cache for dashboard data (valid for current request)
$dashboardCache = [];

?>
<!DOCTYPE html>
<html lang="en">
<head>
<!-- Required meta tags -->
<meta charset="utf-8">
<meta name="viewport"
	content="width=device-width, initial-scale=1, shrink-to-fit=no">
<title>Palcoa ProVal - HVAC Validation System</title>
<!-- plugins:css -->
<link rel="stylesheet"
	href="assets/vendors/mdi/css/materialdesignicons.min.css">
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
<link rel="stylesheet" href="assets/css/modern-manage-ui.css">

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



					<div class="row">

						<div class="col-12 grid-margin stretch-card">
							<div class="card">
								<div class="card-body">




									<div class="page-header">
										<h3 class="page-title">
											Validation Workflow Related Tasks 
										</h3>
										
									</div>


									<div class="row">

<?php
// Function to render dashboard cards
function renderDashboardCard($colorClass, $title, $count, $subtitle, $icon) {
    ?>
    <div class="col-12 col-sm-6 col-md-4 col-lg-4 stretch-card grid-margin">
        <div class="card bg-gradient-<?php echo htmlspecialchars($colorClass); ?> card-img-holder text-white">
            <div class="card-body">
                <img src="assets/images/dashboard/circle.svg" class="card-img-absolute" alt="circle-image" />
                <h4 class="font-weight-normal mb-3">
                    <?php echo htmlspecialchars($title); ?>
                    <i class="mdi <?php echo htmlspecialchars($icon); ?> mdi-24px float-right"></i>
                </h4>
                <h2 class="mb-5 display-1"><?php echo intval($count); ?></h2>
                <h6 class="card-text"><?php echo htmlspecialchars($subtitle); ?></h6>
            </div>
        </div>
    </div>
    <?php
}

// Department mapping for approval fields
function getDepartmentApprovalField($deptId) {
    $mapping = [
        DEPT_ENGINEERING => 'level1_eng_approval_by',
        DEPT_QA => 'level1_qa_approval_by',
        DEPT_QC => 'level1_qc_approval_by',
        DEPT_MICROBIOLOGY => 'level1_qc_approval_by', // Uses same approval field as QC
        DEPT_HSE => 'level1_hse_approval_by'
    ];
    return $mapping[$deptId] ?? 'level1_user_dept_approval_by';
}

// Get count for team approval pending - optimized with single query
function getTeamApprovalPendingCount($unitId, $userId, $deptId) {
    global $dashboardCache;
    
    // Check cache first
    $cacheKey = "team_approval_{$unitId}_{$userId}_{$deptId}";
    if (isset($dashboardCache[$cacheKey])) {
        return $dashboardCache[$cacheKey];
    }
    
    $approvalField = getDepartmentApprovalField($deptId);
    
    // Optimized single query that filters everything at database level
    $query = "
        SELECT COUNT(DISTINCT t.val_wf_id) as approval_count
        FROM tbl_val_wf_tracking_details t
        JOIN tbl_val_wf_approval_tracking_details ap ON t.val_wf_id = ap.val_wf_id
        JOIN tbl_report_approvers ra ON t.val_wf_id = ra.val_wf_id
        WHERE t.val_wf_current_stage = %s
          AND t.unit_id = %i
          AND (ap.level1_user_dept_approval_by IS NULL
               OR ap.level1_eng_approval_by IS NULL
               OR ap.level1_hse_approval_by IS NULL
               OR ap.level1_qc_approval_by IS NULL
               OR ap.level1_qa_approval_by IS NULL)
          AND (ra.level1_approver_engg = %i
               OR ra.level1_approver_hse = %i
               OR ra.level1_approver_qc = %i
               OR ra.level1_approver_qa = %i
               OR ra.level1_approver_user = %i)
          AND ap.$approvalField IS NULL";
    
    $result = DB::queryFirstField($query, STAGE_PENDING_APPROVAL, $unitId, $userId, $userId, $userId, $userId, $userId);
    
    // Cache the result
    $dashboardCache[$cacheKey] = (int)$result;
    
    return $dashboardCache[$cacheKey];
}

// Main Dashboard Logic - using cached user data
$userData = OptimizedSessionValidation::getUserData();
$userType = $userData['user_type'];
$userId = $userData['user_id'];
$unitId = $userData['unit_id'];
$deptId = $userData['department_id'];

// Vendor Dashboard
if ($userType === 'vendor') {
    try {
        // Use APCu caching for vendor dashboard data
        $vendorData = ProValCache::getDashboardData('vendor', $userId, $userData['vendor_id'], function() use ($userData) {
            // Combined query for better performance using ETV mapping
            $vendorQuery = "
                SELECT
                    COUNT(CASE WHEN t1.test_wf_current_stage = %s THEN 1 END) as new_tasks,
                    COUNT(CASE WHEN t1.test_wf_current_stage IN ('1PRV', '3BPRV', '4BPRV') THEN 1 END) as offline_tasks,
                    COUNT(CASE WHEN t1.test_wf_current_stage IN ('3B', '4B', '1RRV') THEN 1 END) as reassigned_tasks
                FROM tbl_test_schedules_tracking t1
                JOIN equipments t2 ON t1.equip_id = t2.equipment_id
                JOIN equipment_test_vendor_mapping etvm ON t1.equip_id = etvm.equipment_id AND t1.test_id = etvm.test_id
                WHERE etvm.vendor_id = %i AND etvm.mapping_status = 'Active'";

            return DB::queryFirstRow($vendorQuery, STAGE_NEW_TASK, $userData['vendor_id']);
        });

        if ($vendorData) {
            renderDashboardCard('danger', 'Newly Assigned Tasks', $vendorData['new_tasks'], 'New tasks', 'mdi-chart-line');
            renderDashboardCard('warning', 'Offline Tasks - Pending Review', $vendorData['offline_tasks'], 'Offline tasks pending review', 'mdi-file-document-outline');
            renderDashboardCard('info', 'Re-assigned Tasks', $vendorData['reassigned_tasks'], 'Tests requiring re-work', 'mdi-refresh');
        }
    } catch (Exception $e) {
        // Log error and show fallback
        error_log("Dashboard error for vendor: " . $e->getMessage());
        renderDashboardCard('secondary', 'Dashboard Error', 0, 'Unable to load data', 'mdi-alert');
    }
}

// Employee Dashboard - Engineering Department
elseif ($userType === 'employee' && $deptId == DEPT_ENGINEERING) {
    try {
        // Cache engineering dashboard metrics
        $engineeringData = ProValCache::getDashboardData('engineering', $userId, $unitId, function() use ($unitId) {
            $engineeringQuery = "
                SELECT
                    COUNT(CASE WHEN t1.test_wf_current_stage = %s AND t1.vendor_id = 0 AND t1.unit_id = %i THEN 1 END) as new_tasks,
                    COUNT(CASE WHEN t1.test_wf_current_stage = %s AND t1.unit_id = %i THEN 1 END) as approval_pending
                FROM tbl_test_schedules_tracking t1
                JOIN equipments t2 ON t1.equip_id = t2.equipment_id";

            return DB::queryFirstRow($engineeringQuery,
                STAGE_NEW_TASK, $unitId,
                STAGE_PENDING_APPROVAL, $unitId
            );
        });

        if ($engineeringData) {
            renderDashboardCard('success', 'Newly Assigned Tasks', $engineeringData['new_tasks'], 'New tasks (Internal)', 'mdi-chart-line');
            renderDashboardCard('dark', 'Tasks Pending For Approval', $engineeringData['approval_pending'], 'Total tasks completed so far', 'mdi-diamond');
        }

        // Cache pending submission count
        $pendingSubmissionCount = ProValCache::get("eng_pending_submission_{$unitId}", function() use ($unitId) {
            $pendingSubmissionQuery = "
                SELECT COUNT(*)
                FROM tbl_val_wf_tracking_details t
                JOIN equipments e ON t.equipment_id = e.equipment_id
                WHERE t.val_wf_current_stage = %s
                  AND t.unit_id = %i
                  AND t.val_wf_id IN (
                      SELECT val_wf_id
                      FROM tbl_test_schedules_tracking
                      GROUP BY val_wf_id
                      HAVING SUM(CASE WHEN test_wf_current_stage = %s THEN 1 ELSE 0 END) = COUNT(test_wf_id)
                  )";

            return DB::queryFirstField($pendingSubmissionQuery, STAGE_NEW_TASK, $unitId, STAGE_COMPLETED);
        });

        renderDashboardCard('info', 'Pending For Team Approval Submission', $pendingSubmissionCount, 'Pending for Team Approval submission', 'mdi-diamond');

        // Cache team approval count
        $teamApprovalCount = ProValCache::get("eng_team_approval_{$unitId}_{$userId}", function() use ($unitId, $userId, $deptId) {
            return getTeamApprovalPendingCount($unitId, $userId, $deptId);
        });
        renderDashboardCard('warning', 'Team Approval Pending', $teamApprovalCount, 'Pending for Team approval', 'mdi-diamond');
        
    } catch (Exception $e) {
        error_log("Dashboard error for engineering: " . $e->getMessage());
        renderDashboardCard('secondary', 'Dashboard Error', 0, 'Unable to load data', 'mdi-alert');
    }
}

// Employee Dashboard - QA Department
elseif ($userType === 'employee' && $deptId == DEPT_QA) {
    try {
        // Tasks Pending For Approval
        $approvalPendingQuery = "
            SELECT COUNT(*)
            FROM tbl_test_schedules_tracking t1
            JOIN equipments t2 ON t1.equip_id = t2.equipment_id
            WHERE t1.test_wf_current_stage = %s
              AND t1.unit_id = %i";
        $approvalPendingCount = DB::queryFirstField($approvalPendingQuery, STAGE_REASSIGNED_A, $unitId);
        
        renderDashboardCard('info', 'Tasks Pending For Approval', $approvalPendingCount, 'Total tasks completed so far', 'mdi-diamond');
        
        // Team Approval Pending (exclude Unit Head and QA Head users)
        if (!OptimizedSessionValidation::hasRole('unit_head') && !OptimizedSessionValidation::hasRole('qa_head')) {
            $teamApprovalCount = getTeamApprovalPendingCount($unitId, $userId, $deptId);
            renderDashboardCard('warning', 'Team Approval Pending', $teamApprovalCount, 'Pending for Team approval', 'mdi-diamond');
        }
        
    } catch (Exception $e) {
        error_log("Dashboard error for QA: " . $e->getMessage());
        renderDashboardCard('secondary', 'Dashboard Error', 0, 'Unable to load data', 'mdi-alert');
    }
}

// Employee Dashboard - Other Departments (QC, HSE, USER)
elseif ($userType === 'employee') {
    try {
        // Single card for Team Approval Pending (exclude Unit Head and QA Head users)
        if (!OptimizedSessionValidation::hasRole('unit_head') && !OptimizedSessionValidation::hasRole('qa_head')) {
            $teamApprovalCount = getTeamApprovalPendingCount($unitId, $userId, $deptId);
            renderDashboardCard('warning', 'Team Approval Pending', $teamApprovalCount, 'Pending for Team approval', 'mdi-diamond');
        }
        
    } catch (Exception $e) {
        error_log("Dashboard error for other departments: " . $e->getMessage());
        renderDashboardCard('secondary', 'Dashboard Error', 0, 'Unable to load data', 'mdi-alert');
    }
}

// Role-Based Cards - QA Head (Independent of department)
if (OptimizedSessionValidation::isEmployee() && OptimizedSessionValidation::hasRole('qa_head')) {
    try {
        // Get QA Head approval pending count
        $qaApprovalPending = DB::queryFirstField(
            "SELECT COUNT(*) FROM tbl_val_wf_tracking_details WHERE val_wf_current_stage = %s AND unit_id = %i", 
            STAGE_QA_HEAD_APPROVAL, $unitId
        );
        
        // Get validation schedule requests count
        $validationScheduleRequests = DB::queryFirstField(
            "SELECT COUNT(*) FROM tbl_val_wf_schedule_requests WHERE schedule_request_status = '2' AND schedule_year != '0' AND unit_id = %i", 
            $unitId
        );
        
        // Get routine test schedule requests count
        $routineScheduleRequests = DB::queryFirstField(
            "SELECT COUNT(*) FROM tbl_routine_test_wf_schedule_requests WHERE schedule_request_status = '2' AND schedule_year != '0' AND unit_id = %i",
            $unitId
        );

        // Get termination requests pending QA Head approval (stage 98B)
        $terminationRequests = DB::queryFirstField(
            "SELECT COUNT(*) FROM tbl_val_wf_tracking_details WHERE val_wf_current_stage = '98B' AND unit_id = %i",
            $unitId
        );

        renderDashboardCard('danger', 'QA Head Approval Pending', $qaApprovalPending, 'Pending for QA Head approval', 'mdi-diamond');
        renderDashboardCard('secondary', 'Validation Schedule Requests Pending', $validationScheduleRequests, 'Validation protocol schedule requests pending', 'mdi-calendar-clock');
        renderDashboardCard('primary', 'Routine Test Schedule Requests Pending', $routineScheduleRequests, 'QA approval needed for routine test schedules', 'mdi-calendar-check');
        renderDashboardCard('warning', 'Pending Termination Requests', $terminationRequests, 'Termination requests awaiting QA Head approval', 'mdi-alert-circle');

    } catch (Exception $e) {
        error_log("Dashboard error for QA Head: " . $e->getMessage());
        renderDashboardCard('secondary', 'Dashboard Error', 0, 'Unable to load QA Head data', 'mdi-alert');
    }
}

// Role-Based Cards - Unit Head (Independent of department)
if (OptimizedSessionValidation::isEmployee() && OptimizedSessionValidation::hasRole('unit_head')) {
    try {
        $unitHeadCount = DB::queryFirstField(
            "SELECT COUNT(*) FROM tbl_val_wf_tracking_details t WHERE t.val_wf_current_stage = %s AND t.unit_id = %i", 
            STAGE_UNIT_HEAD_APPROVAL, $unitId
        );
        renderDashboardCard('primary', 'Unit Head Approval Pending', $unitHeadCount, 'Pending for Unit head approval', 'mdi-diamond');
        
    } catch (Exception $e) {
        error_log("Dashboard error for Unit Head: " . $e->getMessage());
        renderDashboardCard('secondary', 'Dashboard Error', 0, 'Unable to load Unit Head data', 'mdi-alert');
    }
}

// Role-Based Cards - Engineering Department Head
if (OptimizedSessionValidation::isEmployee() && OptimizedSessionValidation::inDepartment(DEPT_ENGINEERING) && OptimizedSessionValidation::hasRole('dept_head')) {
    try {
        // Get validation schedule requests count for Engineering approval
        $validationScheduleRequests = DB::queryFirstField(
            "SELECT COUNT(*) FROM tbl_val_wf_schedule_requests WHERE schedule_request_status = '1' AND schedule_year != '0' AND unit_id = %i", 
            $unitId
        );
        
        // Get routine test schedule requests count for Engineering approval
        $routineScheduleRequests = DB::queryFirstField(
            "SELECT COUNT(*) FROM tbl_routine_test_wf_schedule_requests WHERE schedule_request_status = '1' AND schedule_year != '0' AND unit_id = %i",
            $unitId
        );

        // Get termination requests pending Engineering Dept Head review (stage 98A)
        $terminationRequests = DB::queryFirstField(
            "SELECT COUNT(*) FROM tbl_val_wf_tracking_details WHERE val_wf_current_stage = '98A' AND unit_id = %i",
            $unitId
        );

        renderDashboardCard('secondary', 'Validation Schedule Requests Pending Approval', $validationScheduleRequests, 'Engineering approval needed for schedule requests', 'mdi-calendar-clock');
        renderDashboardCard('primary', 'Routine Test Schedule Requests Pending', $routineScheduleRequests, 'Engineering approval needed for routine test schedules', 'mdi-calendar-check');
        renderDashboardCard('warning', 'Pending Termination Requests', $terminationRequests, 'Termination requests awaiting Engineering Dept Head review', 'mdi-alert-circle');

    } catch (Exception $e) {
        error_log("Dashboard error for Engineering Head: " . $e->getMessage());
        renderDashboardCard('secondary', 'Dashboard Error', 0, 'Unable to load Engineering Head data', 'mdi-alert');
    }
}

?>
            </div>
















								</div>
							</div>
						</div>
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
