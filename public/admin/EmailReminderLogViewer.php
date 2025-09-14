<?php
/**
 * EmailReminder Log Viewer
 * 
 * Administrative interface for viewing and analyzing EmailReminder system logs.
 * Provides detailed views of job executions, email delivery logs, and system events.
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

// Get filter parameters
$filterJob = $_GET['job'] ?? '';
$filterStatus = $_GET['status'] ?? '';
$filterDate = $_GET['date'] ?? '';
$view = $_GET['view'] ?? 'jobs'; // jobs, emails, system, stats
$executionId = $_GET['execution'] ?? '';
$limit = intval($_GET['limit'] ?? 50);
$page = intval($_GET['page'] ?? 1);
$offset = ($page - 1) * $limit;

// Handle AJAX requests
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'get_job_details':
            try {
                $jobExecutionId = intval($_POST['job_execution_id']);
                
                $jobDetails = DB::queryFirstRow(
                    "SELECT * FROM tbl_email_reminder_job_logs WHERE job_execution_id = %i",
                    $jobExecutionId
                );
                
                $emailLogs = DB::query(
                    "SELECT * FROM tbl_email_reminder_logs WHERE job_execution_id = %i ORDER BY sent_datetime DESC",
                    $jobExecutionId
                );
                
                echo json_encode([
                    'success' => true,
                    'job_details' => $jobDetails,
                    'email_logs' => $emailLogs
                ]);
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to get job details: ' . $e->getMessage()
                ]);
            }
            exit;
            
        case 'get_email_recipients':
            try {
                $emailLogId = intval($_POST['email_log_id']);
                
                $recipients = DB::query(
                    "SELECT * FROM tbl_email_reminder_recipients WHERE email_log_id = %i ORDER BY recipient_type, recipient_email",
                    $emailLogId
                );
                
                echo json_encode([
                    'success' => true,
                    'recipients' => $recipients
                ]);
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to get recipients: ' . $e->getMessage()
                ]);
            }
            exit;
            
        case 'cleanup_logs':
            try {
                $jobDays = intval($_POST['job_days']);
                $emailDays = intval($_POST['email_days']);
                
                // Call the stored procedure
                $result = DB::queryFirstRow(
                    "CALL sp_clean_emailreminder_logs(%i, %i)",
                    $jobDays, $emailDays
                );
                
                $logger->logInfo('EmailReminderLogViewer', 
                    "Log cleanup completed: {$result['deleted_job_logs']} job logs, {$result['deleted_email_logs']} email logs, {$result['deleted_system_logs']} system logs");
                
                echo json_encode([
                    'success' => true,
                    'message' => "Cleanup completed: {$result['deleted_job_logs']} job logs, {$result['deleted_email_logs']} email logs, {$result['deleted_system_logs']} system logs deleted"
                ]);
            } catch (Exception $e) {
                $logger->logError('EmailReminderLogViewer', "Failed to cleanup logs: " . $e->getMessage());
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to cleanup logs: ' . $e->getMessage()
                ]);
            }
            exit;
    }
}

// Build filters for queries
$whereConditions = [];
$queryParams = [];

if (!empty($filterJob)) {
    $whereConditions[] = "job_name = %s";
    $queryParams[] = $filterJob;
}

if (!empty($filterStatus)) {
    $whereConditions[] = "status = %s";
    $queryParams[] = $filterStatus;
}

if (!empty($filterDate)) {
    $whereConditions[] = "DATE(execution_start_time) = %s";
    $queryParams[] = $filterDate;
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

try {
    switch ($view) {
        case 'jobs':
            // Get job execution logs
            $logs = DB::query(
                "SELECT 
                    job_execution_id,
                    job_name,
                    execution_start_time,
                    execution_end_time,
                    status,
                    emails_sent,
                    emails_failed,
                    execution_time_seconds,
                    final_message
                 FROM tbl_email_reminder_job_logs 
                 $whereClause
                 ORDER BY execution_start_time DESC 
                 LIMIT %i OFFSET %i",
                ...array_merge($queryParams, [$limit, $offset])
            );
            
            // Get total count for pagination
            $totalCount = DB::queryFirstField(
                "SELECT COUNT(*) FROM tbl_email_reminder_job_logs $whereClause",
                ...$queryParams
            );
            break;
            
        case 'emails':
            // Get email delivery logs
            $emailWhereConditions = [];
            $emailQueryParams = [];
            
            if (!empty($filterJob)) {
                $emailWhereConditions[] = "job_name = %s";
                $emailQueryParams[] = $filterJob;
            }
            
            if (!empty($filterDate)) {
                $emailWhereConditions[] = "DATE(sent_datetime) = %s";
                $emailQueryParams[] = $filterDate;
            }
            
            $emailWhereClause = !empty($emailWhereConditions) ? 'WHERE ' . implode(' AND ', $emailWhereConditions) : '';
            
            $logs = DB::query(
                "SELECT 
                    email_log_id,
                    job_name,
                    unit_id,
                    email_subject,
                    sender_email,
                    sent_datetime,
                    delivery_status,
                    total_recipients,
                    successful_sends,
                    failed_sends,
                    error_message
                 FROM tbl_email_reminder_logs 
                 $emailWhereClause
                 ORDER BY sent_datetime DESC 
                 LIMIT %i OFFSET %i",
                ...array_merge($emailQueryParams, [$limit, $offset])
            );
            
            $totalCount = DB::queryFirstField(
                "SELECT COUNT(*) FROM tbl_email_reminder_logs $emailWhereClause",
                ...$emailQueryParams
            );
            break;
            
        case 'system':
            // Get system logs
            $systemWhereConditions = [];
            $systemQueryParams = [];
            
            if (!empty($filterDate)) {
                $systemWhereConditions[] = "DATE(log_datetime) = %s";
                $systemQueryParams[] = $filterDate;
            }
            
            $systemWhereClause = !empty($systemWhereConditions) ? 'WHERE ' . implode(' AND ', $systemWhereConditions) : '';
            
            $logs = DB::query(
                "SELECT 
                    log_id,
                    log_level,
                    log_source,
                    log_message,
                    log_data,
                    log_datetime
                 FROM tbl_email_reminder_system_logs 
                 $systemWhereClause
                 ORDER BY log_datetime DESC 
                 LIMIT %i OFFSET %i",
                ...array_merge($systemQueryParams, [$limit, $offset])
            );
            
            $totalCount = DB::queryFirstField(
                "SELECT COUNT(*) FROM tbl_email_reminder_system_logs $systemWhereClause",
                ...$systemQueryParams
            );
            break;
            
        case 'stats':
            // Get statistics
            $stats = [];
            
            // Job execution statistics
            $stats['job_stats'] = DB::queryFirstRow(
                "SELECT 
                    COUNT(*) as total_executions,
                    COUNT(CASE WHEN status = 'completed' THEN 1 END) as successful_executions,
                    COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed_executions,
                    COUNT(CASE WHEN status = 'running' THEN 1 END) as running_executions,
                    AVG(execution_time_seconds) as avg_execution_time,
                    SUM(emails_sent) as total_emails_sent,
                    SUM(emails_failed) as total_emails_failed
                 FROM tbl_email_reminder_job_logs 
                 WHERE execution_start_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
            );
            
            // Email delivery statistics
            $stats['email_stats'] = DB::queryFirstRow(
                "SELECT 
                    COUNT(*) as total_emails,
                    COUNT(CASE WHEN delivery_status = 'sent' THEN 1 END) as sent_emails,
                    COUNT(CASE WHEN delivery_status = 'failed' THEN 1 END) as failed_emails,
                    COUNT(CASE WHEN delivery_status = 'pending' THEN 1 END) as pending_emails,
                    AVG(total_recipients) as avg_recipients_per_email
                 FROM tbl_email_reminder_logs 
                 WHERE sent_datetime >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
            );
            
            // Job performance by type
            $stats['job_performance'] = DB::query(
                "SELECT 
                    job_name,
                    COUNT(*) as execution_count,
                    COUNT(CASE WHEN status = 'completed' THEN 1 END) as success_count,
                    COUNT(CASE WHEN status = 'failed' THEN 1 END) as failure_count,
                    AVG(execution_time_seconds) as avg_execution_time,
                    SUM(emails_sent) as total_emails_sent,
                    MAX(execution_start_time) as last_execution
                 FROM tbl_email_reminder_job_logs 
                 WHERE execution_start_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                 GROUP BY job_name 
                 ORDER BY execution_count DESC"
            );
            
            // System error summary
            $stats['error_summary'] = DB::query(
                "SELECT 
                    log_level,
                    log_source,
                    COUNT(*) as error_count,
                    MAX(log_datetime) as last_occurrence
                 FROM tbl_email_reminder_system_logs 
                 WHERE log_level IN ('ERROR', 'WARNING')
                   AND log_datetime >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                 GROUP BY log_level, log_source 
                 ORDER BY error_count DESC 
                 LIMIT 20"
            );
            
            $logs = $stats;
            $totalCount = 0;
            break;
    }
    
    // Get available job names for filter dropdown
    $availableJobs = DB::query(
        "SELECT DISTINCT job_name FROM tbl_email_reminder_job_logs ORDER BY job_name"
    );
    
} catch (Exception $e) {
    $error_message = "Failed to load logs: " . $e->getMessage();
    $logger->logError('EmailReminderLogViewer', $error_message);
    $logs = [];
    $availableJobs = [];
    $totalCount = 0;
}

// Calculate pagination
$totalPages = ceil($totalCount / $limit);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include_once "../assets/inc/_header.php"; ?>
    <style>
        .log-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        .filter-section {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .log-entry {
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 15px;
        }
        .log-entry.status-completed {
            border-left: 5px solid #28a745;
        }
        .log-entry.status-failed {
            border-left: 5px solid #dc3545;
        }
        .log-entry.status-running {
            border-left: 5px solid #ffc107;
        }
        .log-entry.level-error {
            background-color: #f8d7da;
            border-left: 5px solid #dc3545;
        }
        .log-entry.level-warning {
            background-color: #fff3cd;
            border-left: 5px solid #ffc107;
        }
        .log-entry.level-info {
            background-color: #d1ecf1;
            border-left: 5px solid #17a2b8;
        }
        .log-details {
            font-family: monospace;
            font-size: 0.9em;
            background-color: #f8f9fa;
            padding: 10px;
            border-radius: 4px;
            margin-top: 10px;
        }
        .nav-tabs .nav-link.active {
            background-color: #007bff;
            border-color: #007bff;
            color: white;
        }
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            margin-bottom: 20px;
        }
        .stats-card h3 {
            margin: 0;
            font-size: 2em;
            font-weight: bold;
        }
        .pagination-info {
            color: #6c757d;
            font-size: 0.9em;
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
                                <i class="mdi mdi-file-document"></i>
                            </span>
                            EmailReminder Log Viewer
                        </h3>
                        <nav aria-label="breadcrumb">
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="../home.php">Dashboard</a></li>
                                <li class="breadcrumb-item"><a href="EmailReminderDashboard.php">EmailReminder</a></li>
                                <li class="breadcrumb-item active" aria-current="page">Logs</li>
                            </ul>
                        </nav>
                    </div>

                    <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="mdi mdi-alert-circle"></i> <?php echo htmlspecialchars($error_message); ?>
                    </div>
                    <?php endif; ?>

                    <!-- View Tabs -->
                    <ul class="nav nav-tabs mb-4" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link <?php echo $view === 'jobs' ? 'active' : ''; ?>" 
                               href="?view=jobs<?php echo $filterJob ? '&job=' . urlencode($filterJob) : ''; ?>">
                                <i class="mdi mdi-play-circle"></i> Job Executions
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $view === 'emails' ? 'active' : ''; ?>" 
                               href="?view=emails<?php echo $filterJob ? '&job=' . urlencode($filterJob) : ''; ?>">
                                <i class="mdi mdi-email"></i> Email Delivery
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $view === 'system' ? 'active' : ''; ?>" 
                               href="?view=system">
                                <i class="mdi mdi-alert"></i> System Logs
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $view === 'stats' ? 'active' : ''; ?>" 
                               href="?view=stats">
                                <i class="mdi mdi-chart-line"></i> Statistics
                            </a>
                        </li>
                    </ul>

                    <?php if ($view !== 'stats'): ?>
                    <!-- Filters -->
                    <div class="filter-section">
                        <div class="row">
                            <div class="col-md-9">
                                <h4><i class="mdi mdi-filter"></i> Filters</h4>
                                <form method="GET" class="form-inline">
                                    <input type="hidden" name="view" value="<?php echo htmlspecialchars($view); ?>">
                                    
                                    <?php if ($view === 'jobs'): ?>
                                    <div class="form-group mr-3">
                                        <label for="job" class="mr-2">Job:</label>
                                        <select name="job" id="job" class="form-control">
                                            <option value="">All Jobs</option>
                                            <?php foreach ($availableJobs as $job): ?>
                                            <option value="<?php echo htmlspecialchars($job['job_name']); ?>" 
                                                    <?php echo $filterJob === $job['job_name'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($job['job_name']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group mr-3">
                                        <label for="status" class="mr-2">Status:</label>
                                        <select name="status" id="status" class="form-control">
                                            <option value="">All Status</option>
                                            <option value="completed" <?php echo $filterStatus === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                            <option value="failed" <?php echo $filterStatus === 'failed' ? 'selected' : ''; ?>>Failed</option>
                                            <option value="running" <?php echo $filterStatus === 'running' ? 'selected' : ''; ?>>Running</option>
                                        </select>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($view === 'emails'): ?>
                                    <div class="form-group mr-3">
                                        <label for="job" class="mr-2">Job:</label>
                                        <select name="job" id="job" class="form-control">
                                            <option value="">All Jobs</option>
                                            <?php foreach ($availableJobs as $job): ?>
                                            <option value="<?php echo htmlspecialchars($job['job_name']); ?>" 
                                                    <?php echo $filterJob === $job['job_name'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($job['job_name']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="form-group mr-3">
                                        <label for="date" class="mr-2">Date:</label>
                                        <input type="date" name="date" id="date" class="form-control" 
                                               value="<?php echo htmlspecialchars($filterDate); ?>">
                                    </div>
                                    
                                    <div class="form-group mr-3">
                                        <label for="limit" class="mr-2">Limit:</label>
                                        <select name="limit" id="limit" class="form-control">
                                            <option value="25" <?php echo $limit === 25 ? 'selected' : ''; ?>>25</option>
                                            <option value="50" <?php echo $limit === 50 ? 'selected' : ''; ?>>50</option>
                                            <option value="100" <?php echo $limit === 100 ? 'selected' : ''; ?>>100</option>
                                            <option value="200" <?php echo $limit === 200 ? 'selected' : ''; ?>>200</option>
                                        </select>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary mr-2">
                                        <i class="mdi mdi-filter"></i> Apply
                                    </button>
                                    <a href="?view=<?php echo htmlspecialchars($view); ?>" class="btn btn-secondary">
                                        <i class="mdi mdi-filter-remove"></i> Clear
                                    </a>
                                </form>
                            </div>
                            <div class="col-md-3 text-right">
                                <button onclick="showCleanupModal()" class="btn btn-warning">
                                    <i class="mdi mdi-delete-sweep"></i> Cleanup Logs
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Main Content -->
                    <?php if ($view === 'stats'): ?>
                        <!-- Statistics View -->
                        <div class="row">
                            <div class="col-md-3">
                                <div class="stats-card">
                                    <h3><?php echo $logs['job_stats']['total_executions'] ?? 0; ?></h3>
                                    <p>Total Executions (30 days)</p>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stats-card" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
                                    <h3><?php echo $logs['job_stats']['successful_executions'] ?? 0; ?></h3>
                                    <p>Successful Executions</p>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stats-card" style="background: linear-gradient(135deg, #fc4a1a 0%, #f7b733 100%);">
                                    <h3><?php echo $logs['job_stats']['failed_executions'] ?? 0; ?></h3>
                                    <p>Failed Executions</p>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stats-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                                    <h3><?php echo round($logs['job_stats']['avg_execution_time'] ?? 0, 1); ?>s</h3>
                                    <p>Average Execution Time</p>
                                </div>
                            </div>
                        </div>

                        <!-- Job Performance -->
                        <div class="log-card">
                            <h4><i class="mdi mdi-chart-bar"></i> Job Performance (30 days)</h4>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Job Name</th>
                                            <th>Executions</th>
                                            <th>Success Rate</th>
                                            <th>Avg Time</th>
                                            <th>Emails Sent</th>
                                            <th>Last Execution</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($logs['job_performance'])): ?>
                                            <?php foreach ($logs['job_performance'] as $perf): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($perf['job_name']); ?></td>
                                                <td><?php echo $perf['execution_count']; ?></td>
                                                <td>
                                                    <?php 
                                                    $successRate = $perf['execution_count'] > 0 ? 
                                                        round(($perf['success_count'] / $perf['execution_count']) * 100, 1) : 0;
                                                    $badgeClass = $successRate >= 95 ? 'success' : ($successRate >= 80 ? 'warning' : 'danger');
                                                    ?>
                                                    <span class="badge badge-<?php echo $badgeClass; ?>">
                                                        <?php echo $successRate; ?>%
                                                    </span>
                                                </td>
                                                <td><?php echo round($perf['avg_execution_time'], 1); ?>s</td>
                                                <td><?php echo $perf['total_emails_sent']; ?></td>
                                                <td><?php echo date('M j, H:i', strtotime($perf['last_execution'])); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="6" class="text-center">No performance data available</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Error Summary -->
                        <?php if (!empty($logs['error_summary'])): ?>
                        <div class="log-card">
                            <h4><i class="mdi mdi-alert-circle"></i> Recent Errors & Warnings (7 days)</h4>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Level</th>
                                            <th>Source</th>
                                            <th>Count</th>
                                            <th>Last Occurrence</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($logs['error_summary'] as $error): ?>
                                        <tr>
                                            <td>
                                                <span class="badge badge-<?php echo $error['log_level'] === 'ERROR' ? 'danger' : 'warning'; ?>">
                                                    <?php echo $error['log_level']; ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($error['log_source']); ?></td>
                                            <td><?php echo $error['error_count']; ?></td>
                                            <td><?php echo date('M j, H:i', strtotime($error['last_occurrence'])); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <?php endif; ?>

                    <?php else: ?>
                        <!-- Regular Log Views -->
                        <div class="log-card">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h4>
                                    <i class="mdi mdi-<?php echo $view === 'jobs' ? 'play-circle' : 
                                                                ($view === 'emails' ? 'email' : 'alert'); ?>"></i>
                                    <?php 
                                    echo $view === 'jobs' ? 'Job Execution Logs' : 
                                        ($view === 'emails' ? 'Email Delivery Logs' : 'System Logs'); 
                                    ?>
                                </h4>
                                <?php if ($totalCount > 0): ?>
                                <div class="pagination-info">
                                    Showing <?php echo $offset + 1; ?>-<?php echo min($offset + $limit, $totalCount); ?> 
                                    of <?php echo $totalCount; ?> entries
                                </div>
                                <?php endif; ?>
                            </div>

                            <?php if (!empty($logs)): ?>
                                <?php foreach ($logs as $log): ?>
                                    <?php if ($view === 'jobs'): ?>
                                    <div class="log-entry status-<?php echo $log['status']; ?>">
                                        <div class="row">
                                            <div class="col-md-8">
                                                <h5><?php echo htmlspecialchars($log['job_name']); ?></h5>
                                                <p class="text-muted mb-1">
                                                    <i class="mdi mdi-clock"></i> 
                                                    <?php echo date('M j, Y H:i:s', strtotime($log['execution_start_time'])); ?>
                                                    <?php if ($log['execution_end_time']): ?>
                                                     - <?php echo date('H:i:s', strtotime($log['execution_end_time'])); ?>
                                                    <?php endif; ?>
                                                </p>
                                                <p class="mb-1">
                                                    <span class="badge badge-<?php 
                                                        echo $log['status'] === 'completed' ? 'success' : 
                                                            ($log['status'] === 'failed' ? 'danger' : 'warning'); 
                                                    ?>">
                                                        <?php echo strtoupper($log['status']); ?>
                                                    </span>
                                                    <span class="ml-2">Duration: <?php echo $log['execution_time_seconds']; ?>s</span>
                                                    <span class="ml-2">Emails: <?php echo $log['emails_sent']; ?> sent, <?php echo $log['emails_failed']; ?> failed</span>
                                                </p>
                                                <?php if ($log['final_message']): ?>
                                                <div class="log-details">
                                                    <?php echo htmlspecialchars($log['final_message']); ?>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="col-md-4 text-right">
                                                <button onclick="viewJobDetails(<?php echo $log['job_execution_id']; ?>)" 
                                                        class="btn btn-sm btn-outline-primary">
                                                     View Details
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <?php elseif ($view === 'emails'): ?>
                                    <div class="log-entry">
                                        <div class="row">
                                            <div class="col-md-8">
                                                <h5><?php echo htmlspecialchars($log['job_name']); ?></h5>
                                                <p class="mb-1"><strong>Subject:</strong> <?php echo htmlspecialchars($log['email_subject']); ?></p>
                                                <p class="text-muted mb-1">
                                                    <i class="mdi mdi-clock"></i> 
                                                    <?php echo date('M j, Y H:i:s', strtotime($log['sent_datetime'])); ?>
                                                </p>
                                                <p class="mb-1">
                                                    <span class="badge badge-<?php 
                                                        echo $log['delivery_status'] === 'sent' ? 'success' : 
                                                            ($log['delivery_status'] === 'failed' ? 'danger' : 'warning'); 
                                                    ?>">
                                                        <?php echo strtoupper($log['delivery_status']); ?>
                                                    </span>
                                                    <span class="ml-2">Recipients: <?php echo $log['total_recipients']; ?></span>
                                                    <span class="ml-2">Successful: <?php echo $log['successful_sends']; ?></span>
                                                    <span class="ml-2">Failed: <?php echo $log['failed_sends']; ?></span>
                                                </p>
                                                <?php if ($log['error_message']): ?>
                                                <div class="log-details">
                                                    <strong>Error:</strong> <?php echo htmlspecialchars($log['error_message']); ?>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="col-md-4 text-right">
                                                <button onclick="viewEmailRecipients(<?php echo $log['email_log_id']; ?>)" 
                                                        class="btn btn-sm btn-outline-primary">
                                                    <i class="mdi mdi-account-multiple"></i> Recipients
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <?php else: // system logs ?>
                                    <div class="log-entry level-<?php echo strtolower($log['log_level']); ?>">
                                        <div class="row">
                                            <div class="col-md-12">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div>
                                                        <h6>
                                                            <span class="badge badge-<?php 
                                                                echo $log['log_level'] === 'ERROR' ? 'danger' : 
                                                                    ($log['log_level'] === 'WARNING' ? 'warning' : 'info'); 
                                                            ?>">
                                                                <?php echo $log['log_level']; ?>
                                                            </span>
                                                            <?php echo htmlspecialchars($log['log_source']); ?>
                                                        </h6>
                                                        <p class="mb-1"><?php echo htmlspecialchars($log['log_message']); ?></p>
                                                        <?php if ($log['log_data']): ?>
                                                        <div class="log-details">
                                                            <?php echo htmlspecialchars($log['log_data']); ?>
                                                        </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <small class="text-muted">
                                                        <?php echo date('M j, H:i:s', strtotime($log['log_datetime'])); ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>

                                <!-- Pagination -->
                                <?php if ($totalPages > 1): ?>
                                <nav aria-label="Log pagination">
                                    <ul class="pagination justify-content-center">
                                        <?php
                                        $baseUrl = "?view=" . urlencode($view) . 
                                                  ($filterJob ? "&job=" . urlencode($filterJob) : '') .
                                                  ($filterStatus ? "&status=" . urlencode($filterStatus) : '') .
                                                  ($filterDate ? "&date=" . urlencode($filterDate) : '') .
                                                  "&limit=" . $limit;
                                        ?>
                                        
                                        <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="<?php echo $baseUrl; ?>&page=<?php echo $page - 1; ?>">Previous</a>
                                        </li>
                                        <?php endif; ?>
                                        
                                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="<?php echo $baseUrl; ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                        </li>
                                        <?php endfor; ?>
                                        
                                        <?php if ($page < $totalPages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="<?php echo $baseUrl; ?>&page=<?php echo $page + 1; ?>">Next</a>
                                        </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                                <?php endif; ?>
                                
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="mdi mdi-file-document-outline" style="font-size: 4em; color: #ccc;"></i>
                                    <h4 class="text-muted">No logs found</h4>
                                    <p class="text-muted">Try adjusting your filters or check back later.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                </div>
                <?php include_once "../assets/inc/_footercopyright.php"; ?>
            </div>
        </div>
    </div>

    <!-- Job Details Modal -->
    <div class="modal fade" id="jobDetailsModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-xl" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Job Execution Details</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body" id="job-details-content">
                    <!-- Content loaded via AJAX -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Email Recipients Modal -->
    <div class="modal fade" id="emailRecipientsModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Email Recipients</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body" id="recipients-content">
                    <!-- Content loaded via AJAX -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Cleanup Modal -->
    <div class="modal fade" id="cleanupModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Cleanup Old Logs</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <form id="cleanupForm">
                    <div class="modal-body">
                        <div class="alert alert-warning">
                            <i class="mdi mdi-warning"></i>
                            This action will permanently delete old log entries and cannot be undone.
                        </div>
                        
                        <div class="form-group">
                            <label>Delete job logs older than (days):</label>
                            <input type="number" id="job-days" class="form-control" value="90" min="1" max="365">
                        </div>
                        
                        <div class="form-group">
                            <label>Delete email logs older than (days):</label>
                            <input type="number" id="email-days" class="form-control" value="365" min="1" max="730">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">
                            <i class="mdi mdi-delete-sweep"></i> Cleanup Logs
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include_once "../assets/inc/_footerjs.php"; ?>
    
    <script>
        function viewJobDetails(jobExecutionId) {
            $('#job-details-content').html('<div class="text-center"><i class="mdi mdi-loading mdi-spin"></i> Loading...</div>');
            $('#jobDetailsModal').modal('show');
            
            $.post('', {
                action: 'get_job_details',
                job_execution_id: jobExecutionId
            }, function(response) {
                if (response.success) {
                    let html = '<div class="row">';
                    html += '<div class="col-md-6>';
                    html += '<h6>Job Information</h6>';
                    html += '<table class="table table-sm">';
                    html += '<tr><td><strong>Job Name:</strong></td><td>' + response.job_details.job_name + '</td></tr>';
                    html += '<tr><td><strong>Start Time:</strong></td><td>' + response.job_details.execution_start_time + '</td></tr>';
                    html += '<tr><td><strong>End Time:</strong></td><td>' + (response.job_details.execution_end_time || 'Not finished') + '</td></tr>';
                    html += '<tr><td><strong>Status:</strong></td><td><span class="badge badge-' + 
                            (response.job_details.status === 'completed' ? 'success' : 
                             response.job_details.status === 'failed' ? 'danger' : 'warning') + '">' +
                            response.job_details.status.toUpperCase() + '</span></td></tr>';
                    html += '<tr><td><strong>Duration:</strong></td><td>' + response.job_details.execution_time_seconds + ' seconds</td></tr>';
                    html += '<tr><td><strong>Emails Sent:</strong></td><td>' + response.job_details.emails_sent + '</td></tr>';
                    html += '<tr><td><strong>Emails Failed:</strong></td><td>' + response.job_details.emails_failed + '</td></tr>';
                    html += '</table>';
                    
                    if (response.job_details.final_message) {
                        html += '<h6>Final Message</h6>';
                        html += '<div class="alert alert-info"><pre>' + response.job_details.final_message + '</pre></div>';
                    }
                    
                    html += '</div>';
                    html += '<div class="col-md-6">';
                    html += '<h6>Email Logs (' + response.email_logs.length + ')</h6>';
                    
                    if (response.email_logs.length > 0) {
                        html += '<div style="max-height: 400px; overflow-y: auto;">';
                        response.email_logs.forEach(function(email) {
                            html += '<div class="card mb-2">';
                            html += '<div class="card-body p-2">';
                            html += '<h6 class="card-title mb-1">' + email.email_subject + '</h6>';
                            html += '<small class="text-muted">' + email.sent_datetime + '</small><br>';
                            html += '<span class="badge badge-' + 
                                    (email.delivery_status === 'sent' ? 'success' : 
                                     email.delivery_status === 'failed' ? 'danger' : 'warning') + '">' +
                                    email.delivery_status.toUpperCase() + '</span>';
                            html += '<span class="ml-2">Recipients: ' + email.total_recipients + '</span>';
                            html += '</div></div>';
                        });
                        html += '</div>';
                    } else {
                        html += '<p class="text-muted">No email logs found for this job execution.</p>';
                    }
                    
                    html += '</div></div>';
                    
                    $('#job-details-content').html(html);
                } else {
                    $('#job-details-content').html('<div class="alert alert-danger">' + response.message + '</div>');
                }
            }, 'json').fail(function() {
                $('#job-details-content').html('<div class="alert alert-danger">Failed to load job details</div>');
            });
        }
        
        function viewEmailRecipients(emailLogId) {
            $('#recipients-content').html('<div class="text-center"><i class="mdi mdi-loading mdi-spin"></i> Loading...</div>');
            $('#emailRecipientsModal').modal('show');
            
            $.post('', {
                action: 'get_email_recipients',
                email_log_id: emailLogId
            }, function(response) {
                if (response.success) {
                    let html = '<div class="table-responsive">';
                    html += '<table class="table table-striped">';
                    html += '<thead><tr><th>Type</th><th>Email</th><th>Status</th><th>Delivery Time</th><th>Response</th></tr></thead>';
                    html += '<tbody>';
                    
                    response.recipients.forEach(function(recipient) {
                        html += '<tr>';
                        html += '<td><span class="badge badge-secondary">' + recipient.recipient_type.toUpperCase() + '</span></td>';
                        html += '<td>' + recipient.recipient_email + '</td>';
                        html += '<td><span class="badge badge-' + 
                                (recipient.delivery_status === 'sent' ? 'success' : 
                                 recipient.delivery_status === 'failed' ? 'danger' : 'warning') + '">' +
                                recipient.delivery_status.toUpperCase() + '</span></td>';
                        html += '<td>' + recipient.delivery_datetime + '</td>';
                        html += '<td><small>' + (recipient.smtp_response || recipient.bounce_reason || '-') + '</small></td>';
                        html += '</tr>';
                    });
                    
                    html += '</tbody></table></div>';
                    
                    $('#recipients-content').html(html);
                } else {
                    $('#recipients-content').html('<div class="alert alert-danger">' + response.message + '</div>');
                }
            }, 'json').fail(function() {
                $('#recipients-content').html('<div class="alert alert-danger">Failed to load recipients</div>');
            });
        }
        
        function showCleanupModal() {
            $('#cleanupModal').modal('show');
        }
        
        $('#cleanupForm').on('submit', function(e) {
            e.preventDefault();
            
            const jobDays = $('#job-days').val();
            const emailDays = $('#email-days').val();
            
            Swal.fire({
                title: 'Confirm Cleanup',
                text: `This will delete job logs older than ${jobDays} days and email logs older than ${emailDays} days. This action cannot be undone!`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ffc107',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, cleanup logs!'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.post('', {
                        action: 'cleanup_logs',
                        job_days: jobDays,
                        email_days: emailDays
                    }, function(response) {
                        $('#cleanupModal').modal('hide');
                        if (response.success) {
                            Swal.fire('Success!', response.message, 'success').then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire('Error', response.message, 'error');
                        }
                    }, 'json');
                }
            });
        });
    </script>
</body>
</html>