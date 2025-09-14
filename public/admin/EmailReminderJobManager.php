<?php
/**
 * EmailReminder Job Manager
 * 
 * Administrative interface for managing EmailReminder job execution.
 * Allows manual execution, monitoring, and control of email reminder jobs.
 * 
 * @author ProVal System
 * @version 1.0
 */

require_once('../core/config/config.php');

// Session is already started by config.php via session_init.php
// Validate session timeout
require_once('../core/security/session_timeout_middleware.php');
validateActiveSession();

if(!isset($_SESSION['user_name'])) {
   header('Location:' . BASE_URL . 'login.php');
   exit;
}

// Check admin permissions
if(!($_SESSION['is_admin'] === 'Yes' || $_SESSION['is_super_admin'] === 'Yes')) {
    header('Location:' . BASE_URL . 'home.php');
    exit;
}

include_once '../core/config/db.class.php';
require_once '../core/EmailReminderLogger.php';

// Initialize logger
$logger = new EmailReminderLogger();

// Handle AJAX requests
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'run_job':
            try {
                $jobName = $_POST['job_name'];
                $testMode = isset($_POST['test_mode']) ? '--test' : '';
                
                $command = "cd " . escapeshellarg(dirname(__DIR__) . '/scheduled_jobs') . 
                          " && php EmailReminderJobRunner.php --job=" . escapeshellarg($jobName) . " " . $testMode . " 2>&1";
                
                $output = shell_exec($command);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Job executed successfully',
                    'output' => $output
                ]);
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Job execution failed: ' . $e->getMessage()
                ]);
            }
            exit;
            
        case 'get_job_status':
            try {
                $jobStats = DB::query(
                    "SELECT 
                        job_name,
                        MAX(execution_start_time) as last_execution,
                        status as last_status,
                        emails_sent as last_emails_sent,
                        emails_failed as last_emails_failed,
                        execution_time_seconds as last_duration
                     FROM tbl_email_reminder_job_logs 
                     WHERE execution_start_time >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                     GROUP BY job_name
                     ORDER BY last_execution DESC"
                );
                
                echo json_encode([
                    'success' => true,
                    'data' => $jobStats
                ]);
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to get job status: ' . $e->getMessage()
                ]);
            }
            exit;
    }
}

// Get available jobs
$availableJobs = [
    'EmailReminderValidationNotStarted10Days' => [
        'title' => 'Validation Not Started (10 Days)',
        'description' => 'Reminder for validations starting within 10 days',
        'frequency' => 'Daily',
        'priority' => 'High'
    ],
    'EmailReminderValidationNotStarted30Days' => [
        'title' => 'Validation Not Started (30 Days)',
        'description' => 'Early warning for validations starting within 30 days',
        'frequency' => 'Weekly (Mon/Thu)',
        'priority' => 'Medium'
    ],
    'EmailReminderValidationInProgress30Days' => [
        'title' => 'Validation In Progress (30+ Days)',
        'description' => 'Alert for validations running more than 30 days',
        'frequency' => 'Daily',
        'priority' => 'Medium'
    ],
    'EmailReminderValidationInProgress35Days' => [
        'title' => 'Validation In Progress (35+ Days)',
        'description' => 'Escalation alert for validations running more than 35 days',
        'frequency' => 'Daily',
        'priority' => 'High'
    ],
    'EmailReminderValidationInProgress38Days' => [
        'title' => 'Validation In Progress (38+ Days)',
        'description' => 'Emergency alert for validations running more than 38 days',
        'frequency' => 'Daily',
        'priority' => 'Critical'
    ]
];

// Get recent job executions
try {
    $recentExecutions = DB::query(
        "SELECT 
            job_name,
            execution_start_time,
            execution_end_time,
            status,
            emails_sent,
            emails_failed,
            execution_time_seconds,
            final_message
         FROM tbl_email_reminder_job_logs 
         ORDER BY execution_start_time DESC 
         LIMIT 20"
    );
} catch (Exception $e) {
    $error_message = "Failed to load job execution history: " . $e->getMessage();
    $logger->logError('EmailReminderJobManager', $error_message);
    $recentExecutions = [];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include_once "../assets/inc/_header.php"; ?>
    <style>
        .job-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
            border-left: 5px solid #007bff;
        }
        .job-card.priority-high {
            border-left-color: #ffc107;
        }
        .job-card.priority-critical {
            border-left-color: #dc3545;
        }
        .job-status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
        }
        .status-running { background-color: #ffc107; }
        .status-completed { background-color: #28a745; }
        .status-failed { background-color: #dc3545; }
        .status-unknown { background-color: #6c757d; }
        .action-buttons {
            margin-top: 15px;
        }
        .execution-log {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            margin: 10px 0;
            font-family: monospace;
            font-size: 0.9em;
            max-height: 300px;
            overflow-y: auto;
        }
    </style>
</head>
<body class="sidebar-light">
    <div class="container-scroller">
        <?php include_once "../assets/inc/_navbar.php"; ?>
        <div class="container-fluid page-body-wrapper">
            <?php include_once "../assets/inc/_sidebar.php"; ?>
            <div class="main-panel">
                <div class="content-wrapper">
                    
                    <!-- Page Header -->
                    <div class="page-header">
                        <h3 class="page-title">
                            <span class="page-title-icon bg-gradient-primary text-white mr-2">
                                <i class="mdi mdi-play-circle"></i>
                            </span>
                            EmailReminder Job Manager
                        </h3>
                        <nav aria-label="breadcrumb">
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="../home.php">Dashboard</a></li>
                                <li class="breadcrumb-item"><a href="EmailReminderDashboard.php">EmailReminder</a></li>
                                <li class="breadcrumb-item active" aria-current="page">Job Manager</li>
                            </ul>
                        </nav>
                    </div>

                    <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="mdi mdi-alert-circle"></i> <?php echo htmlspecialchars($error_message); ?>
                    </div>
                    <?php endif; ?>

                    <!-- Control Panel -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body">
                                    <h4><i class="mdi mdi-console"></i> Job Control Panel</h4>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <button onclick="runAllJobs()" class="btn btn-success btn-lg">
                                                <i class="mdi mdi-play"></i> Run All Jobs
                                            </button>
                                            <button onclick="runAllJobs(true)" class="btn btn-info btn-lg ml-2">
                                                <i class="mdi mdi-test-tube"></i> Test Mode
                                            </button>
                                        </div>
                                        <div class="col-md-6 text-right">
                                            <button onclick="refreshJobStatus()" class="btn btn-primary">
                                                <i class="mdi mdi-refresh"></i> Refresh Status
                                            </button>
                                            <button onclick="viewSystemLogs()" class="btn btn-secondary ml-2">
                                                <i class="mdi mdi-file-document"></i> View Logs
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Available Jobs -->
                    <div class="row">
                        <div class="col-12">
                            <h4><i class="mdi mdi-format-list-bulleted"></i> Available Jobs</h4>
                        </div>
                    </div>
                    
                    <div class="row">
                        <?php foreach ($availableJobs as $jobClass => $jobInfo): ?>
                        <div class="col-lg-6">
                            <div class="job-card priority-<?php echo strtolower($jobInfo['priority']); ?>" id="job-card-<?php echo $jobClass; ?>">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h5>
                                            <span class="job-status-indicator status-unknown" id="status-<?php echo $jobClass; ?>"></span>
                                            <?php echo htmlspecialchars($jobInfo['title']); ?>
                                        </h5>
                                        <p class="text-muted"><?php echo htmlspecialchars($jobInfo['description']); ?></p>
                                        <small>
                                            <strong>Frequency:</strong> <?php echo htmlspecialchars($jobInfo['frequency']); ?> | 
                                            <strong>Priority:</strong> <?php echo htmlspecialchars($jobInfo['priority']); ?>
                                        </small>
                                    </div>
                                    <div>
                                        <span class="badge badge-<?php echo $jobInfo['priority'] === 'Critical' ? 'danger' : 
                                                                      ($jobInfo['priority'] === 'High' ? 'warning' : 'info'); ?>">
                                            <?php echo $jobInfo['priority']; ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="action-buttons">
                                    <button onclick="runSingleJob('<?php echo $jobClass; ?>')" 
                                            class="btn btn-sm btn-success">
                                        <i class="mdi mdi-play"></i> Run Now
                                    </button>
                                    <button onclick="runSingleJob('<?php echo $jobClass; ?>', true)" 
                                            class="btn btn-sm btn-info">
                                        <i class="mdi mdi-test-tube"></i> Test
                                    </button>
                                    <button onclick="viewJobLogs('<?php echo $jobClass; ?>')" 
                                            class="btn btn-sm btn-outline-primary">
                                        <i class="mdi mdi-file-document"></i> Logs
                                    </button>
                                    <button onclick="viewJobStats('<?php echo $jobClass; ?>')" 
                                            class="btn btn-sm btn-outline-secondary">
                                        <i class="mdi mdi-chart-line"></i> Stats
                                    </button>
                                </div>
                                
                                <div id="last-execution-<?php echo $jobClass; ?>" class="mt-2" style="display: none;">
                                    <small class="text-muted">
                                        <strong>Last Execution:</strong> <span class="last-exec-time"></span> | 
                                        <strong>Status:</strong> <span class="last-exec-status"></span> | 
                                        <strong>Emails:</strong> <span class="last-exec-emails"></span>
                                    </small>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Recent Executions -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body">
                                    <h4><i class="mdi mdi-history"></i> Recent Job Executions</h4>
                                    <div class="table-responsive">
                                        <table class="table table-striped" id="executions-table">
                                            <thead>
                                                <tr>
                                                    <th>Job Name</th>
                                                    <th>Start Time</th>
                                                    <th>Duration</th>
                                                    <th>Status</th>
                                                    <th>Emails Sent</th>
                                                    <th>Emails Failed</th>
                                                    <th>Message</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (!empty($recentExecutions)): ?>
                                                    <?php foreach ($recentExecutions as $execution): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($execution['job_name']); ?></td>
                                                        <td><?php echo date('M j, H:i:s', strtotime($execution['execution_start_time'])); ?></td>
                                                        <td><?php echo $execution['execution_time_seconds']; ?>s</td>
                                                        <td>
                                                            <span class="badge badge-<?php 
                                                                echo $execution['status'] === 'completed' ? 'success' : 
                                                                    ($execution['status'] === 'failed' ? 'danger' : 'warning'); 
                                                            ?>">
                                                                <?php echo strtoupper($execution['status']); ?>
                                                            </span>
                                                        </td>
                                                        <td><span class="text-success"><?php echo $execution['emails_sent']; ?></span></td>
                                                        <td><span class="text-danger"><?php echo $execution['emails_failed']; ?></span></td>
                                                        <td>
                                                            <small><?php echo htmlspecialchars(substr($execution['final_message'] ?? '', 0, 50)); ?></small>
                                                        </td>
                                                        <td>
                                                            <button onclick="viewExecutionDetails('<?php echo $execution['job_name']; ?>', '<?php echo $execution['execution_start_time']; ?>')" 
                                                                    class="btn btn-sm btn-outline-primary">
                                                                
                                                            </button>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <tr>
                                                        <td colspan="8" class="text-center">No recent executions found</td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
                <?php include_once "../assets/inc/_footercopyright.php"; ?>
            </div>
        </div>
    </div>

    <!-- Execution Output Modal -->
    <div class="modal fade" id="executionOutputModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Job Execution Output</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div id="execution-output" class="execution-log"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <?php include_once "../assets/inc/_footerjs.php"; ?>
    
    <script>
        // Initialize page
        $(document).ready(function() {
            refreshJobStatus();
            
            // Auto-refresh every 30 seconds
            setInterval(refreshJobStatus, 30000);
        });
        
        function runSingleJob(jobName, testMode = false) {
            Swal.fire({
                title: 'Running Job...',
                text: 'Executing ' + jobName + (testMode ? ' in test mode' : ''),
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            $.post('', {
                action: 'run_job',
                job_name: jobName,
                test_mode: testMode
            }, function(response) {
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Job Completed',
                        text: response.message,
                        confirmButtonText: 'OK'
                    }).then(() => {
                        if (response.output) {
                            showExecutionOutput(response.output);
                        }
                        refreshJobStatus();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Job Failed',
                        text: response.message,
                        confirmButtonText: 'OK'
                    });
                }
            }, 'json').fail(function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to execute job',
                    confirmButtonText: 'OK'
                });
            });
        }
        
        function runAllJobs(testMode = false) {
            const jobs = <?php echo json_encode(array_keys($availableJobs)); ?>;
            let currentJob = 0;
            
            function runNextJob() {
                if (currentJob >= jobs.length) {
                    Swal.fire({
                        icon: 'success',
                        title: 'All Jobs Completed',
                        text: 'All email reminder jobs have been executed',
                        confirmButtonText: 'OK'
                    });
                    refreshJobStatus();
                    return;
                }
                
                const jobName = jobs[currentJob];
                Swal.fire({
                    title: 'Running Jobs...',
                    text: `Executing ${jobName} (${currentJob + 1} of ${jobs.length})`,
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showConfirmButton: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                $.post('', {
                    action: 'run_job',
                    job_name: jobName,
                    test_mode: testMode
                }, function(response) {
                    currentJob++;
                    runNextJob();
                }, 'json');
            }
            
            runNextJob();
        }
        
        function refreshJobStatus() {
            $.post('', {
                action: 'get_job_status'
            }, function(response) {
                if (response.success) {
                    updateJobStatuses(response.data);
                }
            }, 'json');
        }
        
        function updateJobStatuses(jobData) {
            // Reset all job statuses
            $('.job-status-indicator').removeClass('status-completed status-failed status-running').addClass('status-unknown');
            
            jobData.forEach(function(job) {
                const statusElement = $('#status-' + job.job_name);
                const lastExecElement = $('#last-execution-' + job.job_name);
                
                statusElement.removeClass('status-unknown').addClass('status-' + job.last_status);
                
                if (lastExecElement.length) {
                    lastExecElement.find('.last-exec-time').text(new Date(job.last_execution).toLocaleString());
                    lastExecElement.find('.last-exec-status').text(job.last_status.toUpperCase());
                    lastExecElement.find('.last-exec-emails').text(job.last_emails_sent + ' sent, ' + job.last_emails_failed + ' failed');
                    lastExecElement.show();
                }
            });
        }
        
        function showExecutionOutput(output) {
            $('#execution-output').text(output);
            $('#executionOutputModal').modal('show');
        }
        
        function viewJobLogs(jobName) {
            window.location.href = 'EmailReminderLogViewer.php?job=' + encodeURIComponent(jobName);
        }
        
        function viewJobStats(jobName) {
            window.location.href = 'EmailReminderLogViewer.php?job=' + encodeURIComponent(jobName) + '&view=stats';
        }
        
        function viewExecutionDetails(jobName, executionTime) {
            window.location.href = 'EmailReminderLogViewer.php?job=' + encodeURIComponent(jobName) + 
                                  '&execution=' + encodeURIComponent(executionTime);
        }
        
        function viewSystemLogs() {
            window.location.href = 'EmailReminderLogViewer.php';
        }
    </script>
</body>
</html>